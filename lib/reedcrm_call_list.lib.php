<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/reedcrm_call_list.lib.php
 * \ingroup reedcrm
 * \brief   Library functions for CallList card (tabs preparation).
 */

/**
 * Prepare array of tabs for the CallList card.
 *
 * @param  CallList $object CallList object
 * @return array            Array of tabs
 */
function call_list_prepare_head(CallList $object): array
{
    global $conf, $langs, $user;

    saturne_load_langs();

    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id;
    $head[$h][1] = $langs->trans('CallList');
    $head[$h][2] = 'card';
    $h++;

    $head[$h][0] = dol_buildpath('/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id . '&show=notes';
    $head[$h][1] = $langs->trans('Notes');
    $head[$h][2] = 'notes';
    $h++;

    if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
        $head[$h][0] = dol_buildpath('/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id . '&show=agenda';
        $head[$h][1] = $langs->trans('Events');
        $head[$h][2] = 'agenda';
        $h++;
    }

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'call_list@reedcrm');

    return $head;
}

/**
 * Return the id of the user's default call list, creating it if missing.
 *
 * The link user -> default call list is stored in the user personal conf
 * (llx_user_param) under key REEDCRM_DEFAULT_CALL_LIST, read/written through
 * the native Dolibarr API (User::loadPersonalConf / dol_set_user_param).
 * Idempotent: never creates a duplicate.
 *
 * @param  DoliDB $db         Database handler
 * @param  User   $targetUser User the default call list belongs to
 * @return int                Call list id (> 0) on success, <= 0 on failure
 */
function reedcrm_get_or_create_user_default_call_list(DoliDB $db, User $targetUser): int
{
    global $conf, $langs;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
    dol_include_once('/reedcrm/class/calllist.class.php');

    $langs->load('reedcrm@reedcrm');

    $entity = !empty($targetUser->entity) ? (int) $targetUser->entity : (int) $conf->entity;

    // Load the user personal conf so the existence check is reliable whatever
    // the way the user object was fetched (User::fetchAll does not load it)
    $targetUser->loadPersonalConf($entity);

    $existingListId = isset($targetUser->conf->REEDCRM_DEFAULT_CALL_LIST) ? (int) $targetUser->conf->REEDCRM_DEFAULT_CALL_LIST : 0;

    // Already linked and still existing -> reuse
    if ($existingListId > 0) {
        $check = new CallList($db);
        if ($check->fetch($existingListId) > 0) {
            return $existingListId;
        }
    }

    // Create a new default call list assigned to the user
    $callList                 = new CallList($db);
    $callList->label          = $langs->transnoentitiesnoconv('DefaultCallListLabel', dolGetFirstLastname($targetUser->firstname, $targetUser->lastname));
    $callList->status         = CallList::STATUS_DRAFT;
    $callList->fk_user_assign = $targetUser->id;
    $callList->entity         = $entity;

    $newId = $callList->create($targetUser);
    if ($newId <= 0) {
        return -1;
    }

    // Validate right away: assigns the definitive ref (LA…) and the active status, no (PROV…) step
    $callList->validate($targetUser);

    // dol_set_user_param writes the param and keeps $targetUser->conf in sync
    if (dol_set_user_param($db, $conf, $targetUser, ['REEDCRM_DEFAULT_CALL_LIST' => (string) $newId], $entity) < 0) {
        return -2;
    }

    return $newId;
}

/**
 * Check whether a user may see a call list: all of them, their subordinates' ones or their own, by right.
 *
 * @param  User     $user     User to check
 * @param  CallList $callList Call list
 * @return bool               true if the user may see the call list
 */
function reedcrm_call_list_user_can_read(User $user, CallList $callList): bool
{
    if ($user->hasRight('reedcrm', 'call_list', 'read_all') || $user->admin) {
        return true;
    }
    if ($user->hasRight('reedcrm', 'call_list', 'read_subordinates')) {
        return isset($user->getAllChildIds(1)[$callList->fk_user_assign]);
    }

    return $user->hasRight('reedcrm', 'call_list', 'read') && $callList->fk_user_assign == $user->id;
}

/**
 * Call list the App reopens when it is opened without one: the last one the user opened in it.
 *
 * Only while the user may still read it and it is still active, as the selector of the App offers no other:
 * a list deleted, closed or handed over to someone else since then gives way to the default one.
 *
 * @param  DoliDB $db   Database handler
 * @param  User   $user Current user, whose personal conf main.inc.php loads at each request
 * @return int          Call list id, 0 when there is none to reopen
 */
function reedcrm_call_list_get_last_opened(DoliDB $db, User $user): int
{
    $callListId = isset($user->conf->REEDCRM_LAST_CALL_LIST) ? (int) $user->conf->REEDCRM_LAST_CALL_LIST : 0;
    if ($callListId <= 0) {
        return 0;
    }

    dol_include_once('/reedcrm/class/calllist.class.php');

    $callList = new CallList($db);
    if ($callList->fetch($callListId) <= 0 || (int) $callList->status !== CallList::STATUS_ACTIVE || !reedcrm_call_list_user_can_read($user, $callList)) {
        return 0;
    }

    return $callListId;
}

/**
 * Remember the call list the user opens in the App, the one reedcrm_call_list_get_last_opened() reopens.
 *
 * @param  DoliDB $db         Database handler
 * @param  User   $user       Current user
 * @param  int    $callListId Call list opened
 * @return void
 */
function reedcrm_call_list_set_last_opened(DoliDB $db, User $user, int $callListId): void
{
    global $conf;

    // Written on change only: dol_set_user_param deletes then inserts the parameter
    if (isset($user->conf->REEDCRM_LAST_CALL_LIST) && (int) $user->conf->REEDCRM_LAST_CALL_LIST === $callListId) {
        return;
    }

    require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

    // In the entity of the session, which main.inc.php reads the personal conf from
    dol_set_user_param($db, $conf, $user, ['REEDCRM_LAST_CALL_LIST' => (string) $callListId]);
}

/**
 * Fetch a supported call-list source element (project, propal or facture).
 *
 * @param  DoliDB                    $db          Database handler
 * @param  string                    $elementType 'project' | 'propal' | 'facture'
 * @param  int                       $elementId   Element id
 * @return Project|Propal|Facture|null            Loaded object, or null if unsupported / not found
 */
function reedcrm_call_list_fetch_element(DoliDB $db, string $elementType, int $elementId)
{
    if ($elementId <= 0) {
        return null;
    }

    $element = null;
    if ($elementType === 'project' && isModEnabled('projet')) {
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
        $element = new Project($db);
    } elseif ($elementType === 'propal' && isModEnabled('propale')) {
        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
        $element = new Propal($db);
    } elseif ($elementType === 'facture' && isModEnabled('facture')) {
        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
        $element = new Facture($db);
    }

    if ($element === null || $element->fetch($elementId) <= 0) {
        return null;
    }

    return $element;
}

/**
 * Return the best available phone number on a Contact (pro -> mobile -> perso).
 *
 * @param  Contact $contact Loaded contact
 * @return string           Trimmed phone number, or ''
 */
function reedcrm_call_list_pick_contact_phone(Contact $contact): string
{
    foreach ([$contact->phone_pro, $contact->phone_mobile, $contact->phone_perso] as $candidate) {
        $trimmed = trim((string) $candidate);
        if ($trimmed !== '') {
            return $trimmed;
        }
    }

    return '';
}

/**
 * Resolve the linked Project (opportunity) of a call-list element and load its extrafields.
 *
 * @param  DoliDB       $db          Database handler
 * @param  CommonObject $element     Loaded element
 * @param  string       $elementType 'project' | 'propal' | 'facture'
 * @return Project|null              Loaded project with optionals, or null
 */
function reedcrm_call_list_resolve_project(DoliDB $db, CommonObject $element, string $elementType): ?Project
{
    $project = null;

    if ($elementType === 'project' && $element instanceof Project) {
        $project = $element;
    } elseif (!empty($element->fk_project) && isModEnabled('projet')) {
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
        $candidate = new Project($db);
        if ($candidate->fetch((int) $element->fk_project) > 0) {
            $project = $candidate;
        }
    }

    if ($project !== null && (!is_array($project->array_options) || empty($project->array_options))) {
        $project->fetch_optionals();
    }

    return $project;
}

/**
 * Collect unique external contact ids linked to a Dolibarr object (excluding PROJECTADDRESS).
 *
 * @param  CommonObject $element Loaded element
 * @return int[]                 Ordered list of unique positive contact ids
 */
function reedcrm_call_list_collect_contact_ids(CommonObject $element): array
{
    $allContacts = $element->liste_contact(-1, 'external');
    if (!is_array($allContacts) || empty($allContacts)) {
        return [];
    }

    $ids = [];
    foreach ($allContacts as $c) {
        if (($c['code'] ?? '') === 'PROJECTADDRESS') {
            continue;
        }
        $cid = (int) ($c['id'] ?? 0);
        if ($cid > 0 && !in_array($cid, $ids, true)) {
            $ids[] = $cid;
        }
    }

    return $ids;
}

/**
 * Resolve contact and phone number for an element following the call-list fallback chain:
 *   1. Opportunity (Project) phone extrafield (options_projectphone)
 *   2. Associated contact on the element (or its linked project / projectaddress)
 *   3. Thirdparty phone (Societe::$phone)
 *   4. Active contacts of the thirdparty having a phone number
 *
 * @param  DoliDB $db          Database handler
 * @param  string $elementType 'project' | 'propal' | 'facture'
 * @param  int    $elementId   Element id
 * @return array               Resolution payload
 */
function reedcrm_call_list_resolve_element_contact(DoliDB $db, string $elementType, int $elementId): array
{
    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
    require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

    $result = [
        'found'               => false,
        'contact_id'          => 0,
        'lastname'            => '',
        'firstname'           => '',
        'phone'               => '',
        'source'              => '',
        'project_id'          => 0,
        'socid'               => 0,
        'can_save_on_project' => false,
        'project_phone'       => '',
        'thirdparty_contacts' => [],
        'phone_choices'       => [],
    ];

    $element = reedcrm_call_list_fetch_element($db, $elementType, $elementId);
    if ($element === null) {
        return $result;
    }

    $project   = reedcrm_call_list_resolve_project($db, $element, $elementType);
    $projectId = $project !== null ? (int) $project->id : 0;
    $socid     = !empty($element->socid) ? (int) $element->socid : ($project !== null && !empty($project->socid) ? (int) $project->socid : 0);

    $result['project_id']          = $projectId;
    $result['socid']               = $socid;
    $result['can_save_on_project'] = ($projectId > 0);

    $thirdparty = null;
    if ($socid > 0) {
        $socCandidate = new Societe($db);
        if ($socCandidate->fetch($socid) > 0) {
            $thirdparty = $socCandidate;
        }
    }

    // Collect contact ids linked to the element (and to its linked project if different)
    $elementContactIds = reedcrm_call_list_collect_contact_ids($element);
    if ($project !== null) {
        if ($projectId !== (int) $element->id || $elementType !== 'project') {
            foreach (reedcrm_call_list_collect_contact_ids($project) as $cid) {
                if (!in_array($cid, $elementContactIds, true)) {
                    $elementContactIds[] = $cid;
                }
            }
        }
        $projectAddressId = (int) ($project->array_options['options_projectaddress'] ?? 0);
        if ($projectAddressId > 0 && !in_array($projectAddressId, $elementContactIds, true)) {
            $elementContactIds[] = $projectAddressId;
        }
    }

    // Preload first linked contact for display fallback when opportunity phone is used
    $firstContactObj = null;
    if (!empty($elementContactIds)) {
        $candidate = new Contact($db);
        if ($candidate->fetch($elementContactIds[0]) > 0) {
            $firstContactObj = $candidate;
        }
    }

    $projectPhone            = $project !== null ? trim((string) ($project->array_options['options_projectphone'] ?? '')) : '';
    $result['project_phone'] = $projectPhone;
    $thirdpartyPhone         = $thirdparty !== null ? trim((string) $thirdparty->phone) : '';

    // Collect all contacts with a phone (element contacts first, then thirdparty contacts), deduplicated by contact id
    $seenContactIds  = [];
    $elementContacts = [];
    foreach ($elementContactIds as $cid) {
        $contact = new Contact($db);
        if ($contact->fetch($cid) <= 0) {
            continue;
        }
        $seenContactIds[$cid] = true;
        $phone                = reedcrm_call_list_pick_contact_phone($contact);
        if ($phone !== '') {
            $elementContacts[] = [
                'contact_id' => (int) $contact->id,
                'lastname'   => (string) $contact->lastname,
                'firstname'  => (string) $contact->firstname,
                'phone'      => $phone,
            ];
        }
    }

    $tpContacts = [];
    if ($socid > 0) {
        $sql  = 'SELECT rowid FROM ' . $db->prefix() . 'socpeople';
        $sql .= ' WHERE fk_soc = ' . $socid;
        $sql .= ' AND statut = 1';
        $sql .= ' AND entity IN (' . getEntity('socpeople') . ')';
        $sql .= ' ORDER BY rowid ASC';

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $tpCid = (int) $obj->rowid;
                if (isset($seenContactIds[$tpCid])) {
                    continue;
                }
                $tpContact = new Contact($db);
                if ($tpContact->fetch($tpCid) <= 0) {
                    continue;
                }
                $tpPhone = reedcrm_call_list_pick_contact_phone($tpContact);
                if ($tpPhone !== '') {
                    $seenContactIds[$tpCid] = true;
                    $tpContacts[]           = [
                        'contact_id' => (int) $tpContact->id,
                        'lastname'   => (string) $tpContact->lastname,
                        'firstname'  => (string) $tpContact->firstname,
                        'phone'      => $tpPhone,
                    ];
                }
            }
            $db->free($resql);
        }
    }

    // Build all phone choices available for the UX selector (thirdparty + all contacts)
    $allContactsWithPhone = array_merge($elementContacts, $tpContacts);
    $defaultChoiceKey     = '';

    // Resolve winning cascade source:
    // 1. Opportunity phone -> 2. Element contact -> 3. Thirdparty phone -> 4. Thirdparty contact
    if ($projectPhone !== '') {
        $lastname  = $project !== null ? trim((string) ($project->array_options['options_reedcrm_lastname'] ?? '')) : '';
        $firstname = $project !== null ? trim((string) ($project->array_options['options_reedcrm_firstname'] ?? '')) : '';

        if ($lastname === '' && $firstname === '' && $firstContactObj !== null) {
            $lastname  = (string) $firstContactObj->lastname;
            $firstname = (string) $firstContactObj->firstname;
        } elseif ($lastname === '' && $thirdparty !== null) {
            $lastname = (string) $thirdparty->name;
        }

        $result['found']     = true;
        $result['lastname']  = $lastname;
        $result['firstname'] = $firstname;
        $result['phone']     = $projectPhone;
        $result['source']    = 'project_phone';
        $defaultChoiceKey    = 'project';
    } elseif (!empty($elementContacts)) {
        $first                = $elementContacts[0];
        $result['found']      = true;
        $result['contact_id'] = $first['contact_id'];
        $result['lastname']   = $first['lastname'];
        $result['firstname']  = $first['firstname'];
        $result['phone']      = $first['phone'];
        $result['source']     = 'element_contact';
        $defaultChoiceKey     = 'contact_' . $first['contact_id'];
    } elseif ($thirdparty !== null && $thirdpartyPhone !== '') {
        $result['found']    = true;
        $result['lastname'] = (string) $thirdparty->name;
        $result['phone']    = $thirdpartyPhone;
        $result['source']   = 'thirdparty';
        $defaultChoiceKey   = 'thirdparty';
    } elseif (!empty($tpContacts)) {
        $first                         = $tpContacts[0];
        $result['found']               = true;
        $result['contact_id']          = $first['contact_id'];
        $result['lastname']            = $first['lastname'];
        $result['firstname']           = $first['firstname'];
        $result['phone']               = $first['phone'];
        $result['source']              = 'thirdparty_contact';
        $result['thirdparty_contacts'] = $tpContacts;
        $defaultChoiceKey              = 'contact_' . $first['contact_id'];
    } else {
        // No phone found anywhere: provide best available display name as context
        if ($firstContactObj !== null) {
            $result['lastname']  = (string) $firstContactObj->lastname;
            $result['firstname'] = (string) $firstContactObj->firstname;
        } elseif ($project !== null && (!empty($project->array_options['options_reedcrm_lastname']) || !empty($project->array_options['options_reedcrm_firstname']))) {
            $result['lastname']  = (string) ($project->array_options['options_reedcrm_lastname'] ?? '');
            $result['firstname'] = (string) ($project->array_options['options_reedcrm_firstname'] ?? '');
        } elseif ($thirdparty !== null) {
            $result['lastname'] = (string) $thirdparty->name;
        }
    }

    if ($thirdparty !== null && $thirdpartyPhone !== '') {
        $result['phone_choices'][] = [
            'key'        => 'thirdparty',
            'type'       => 'thirdparty',
            'label'      => (string) $thirdparty->name,
            'phone'      => $thirdpartyPhone,
            'contact_id' => 0,
            'selected'   => ($defaultChoiceKey === 'thirdparty'),
        ];
    }

    foreach ($allContactsWithPhone as $cItem) {
        $key = 'contact_' . $cItem['contact_id'];
        $result['phone_choices'][] = [
            'key'        => $key,
            'type'       => 'contact',
            'label'      => trim($cItem['firstname'] . ' ' . $cItem['lastname']),
            'phone'      => $cItem['phone'],
            'contact_id' => $cItem['contact_id'],
            'selected'   => ($defaultChoiceKey === $key),
        ];
    }

    return $result;
}

/**
 * Validate a phone number locally using Dolibarr's native Validate::isPhone() and regex rules
 * without any external call.
 *
 * Accepts:
 * - French national numbers: 0[1-9] + 8 digits (10 digits total, separators space/./-/() allowed)
 * - French international numbers: +33 / 0033 + optional 0 + [1-9] + 8 digits
 * - Other international numbers: 6 to 15 digits (or 8 to 17 when prefixed with 00)
 *
 * @param  DoliDB $db    Database handler
 * @param  string $phone Phone number to validate
 * @return bool          true if valid
 */
function reedcrm_call_list_is_valid_phone(DoliDB $db, string $phone): bool
{
    global $langs;

    $trimmed = trim($phone);
    if ($trimmed === '') {
        return false;
    }

    // Native Dolibarr phone character validator (no external call)
    require_once DOL_DOCUMENT_ROOT . '/core/class/validate.class.php';
    $validate = new Validate($db, $langs);
    if (!$validate->isPhone($trimmed)) {
        return false;
    }

    // '+' may only appear as the leading character
    if (!preg_match('/^\+?[0-9][0-9\s.\-()]*$/', preg_replace('/^\(\s*/', '', $trimmed))) {
        return false;
    }
    if (substr_count($trimmed, '+') > 1 || (strpos($trimmed, '+') !== false && strpos($trimmed, '+') !== 0)) {
        return false;
    }

    $digits = preg_replace('/\D+/', '', $trimmed);
    if ($digits === null || $digits === '') {
        return false;
    }

    // French national format (01..09): strictly 10 digits
    if (preg_match('/^0[1-9]/', $digits)) {
        return (bool) preg_match('/^0[1-9]\d{8}$/', $digits);
    }

    // French international format (+33 or 0033)
    if (strpos($trimmed, '+33') === 0) {
        return (bool) preg_match('/^330?[1-9]\d{8}$/', $digits);
    }
    if (strpos($digits, '0033') === 0) {
        return (bool) preg_match('/^00330?[1-9]\d{8}$/', $digits);
    }

    // Generic international format (ITU-T E.164: max 15 digits, or +2 for 00 prefix)
    if (strpos($digits, '00') === 0) {
        return (bool) preg_match('/^00\d{6,15}$/', $digits);
    }

    return (bool) preg_match('/^\d{6,15}$/', $digits);
}

/**
 * Save a manually entered phone number on the element's opportunity (options_projectphone),
 * or fallback to its first associated contact / thirdparty when no project is linked.
 *
 * @param  DoliDB $db          Database handler
 * @param  User   $user        Acting user
 * @param  string $elementType 'project' | 'propal' | 'facture'
 * @param  int    $elementId   Element id
 * @param  string $phone       Phone number to store
 * @return array               ['success' => bool, 'saved_on' => string, 'message' => string]
 */
function reedcrm_call_list_save_element_phone(DoliDB $db, User $user, string $elementType, int $elementId, string $phone): array
{
    global $langs;

    $langs->load('reedcrm@reedcrm');

    $cleanPhone = trim($phone);
    if ($cleanPhone === '') {
        return ['success' => false, 'saved_on' => '', 'message' => $langs->transnoentitiesnoconv('CallListPhoneRequired')];
    }
    if (!reedcrm_call_list_is_valid_phone($db, $cleanPhone)) {
        return ['success' => false, 'saved_on' => '', 'message' => $langs->transnoentitiesnoconv('CallListInvalidPhone')];
    }

    $element = reedcrm_call_list_fetch_element($db, $elementType, $elementId);
    if ($element === null) {
        return ['success' => false, 'saved_on' => '', 'message' => $langs->transnoentitiesnoconv('CallListWidgetError')];
    }

    $project = reedcrm_call_list_resolve_project($db, $element, $elementType);
    if ($project !== null) {
        $project->array_options['options_projectphone'] = $cleanPhone;
        if ($project->updateExtraField('projectphone', null, $user) > 0) {
            return ['success' => true, 'saved_on' => 'project', 'message' => ''];
        }

        return ['success' => false, 'saved_on' => '', 'message' => $project->error ?: $langs->transnoentitiesnoconv('CallListWidgetError')];
    }

    // Orphan propal/facture without a linked project: persist on its first external contact or thirdparty
    $contactIds = reedcrm_call_list_collect_contact_ids($element);
    if (!empty($contactIds)) {
        require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
        $contact = new Contact($db);
        if ($contact->fetch($contactIds[0]) > 0) {
            $contact->phone_pro = $cleanPhone;
            if ($contact->update($contact->id, $user) > 0) {
                return ['success' => true, 'saved_on' => 'contact', 'message' => ''];
            }

            return ['success' => false, 'saved_on' => '', 'message' => $contact->error ?: $langs->transnoentitiesnoconv('CallListWidgetError')];
        }
    }

    if (!empty($element->socid)) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        $soc = new Societe($db);
        if ($soc->fetch((int) $element->socid) > 0) {
            $soc->phone = $cleanPhone;
            if ($soc->update($soc->id, $user) > 0) {
                return ['success' => true, 'saved_on' => 'thirdparty', 'message' => ''];
            }

            return ['success' => false, 'saved_on' => '', 'message' => $soc->error ?: $langs->transnoentitiesnoconv('CallListWidgetError')];
        }
    }

    return ['success' => false, 'saved_on' => '', 'message' => $langs->transnoentitiesnoconv('CallListWidgetNoContact')];
}

/**
 * Resolve display coordinates (lastname, firstname, phone) for a CallListLine,
 * falling back to the element resolution chain when fk_contact is empty or lacks a phone.
 *
 * @param  DoliDB       $db   Database handler
 * @param  CallListLine $line Call list line
 * @return array              ['lastname' => string, 'firstname' => string, 'phone' => string] (raw values)
 */
function reedcrm_call_list_get_line_contact_display(DoliDB $db, CallListLine $line): array
{
    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

    $lastname  = '';
    $firstname = '';
    $phone     = '';

    if (!empty($line->fk_contact)) {
        $contact = new Contact($db);
        if ($contact->fetch((int) $line->fk_contact) > 0) {
            $lastname  = (string) $contact->lastname;
            $firstname = (string) $contact->firstname;
            $phone     = reedcrm_call_list_pick_contact_phone($contact);
        }
    }

    if ($phone === '' || ($lastname === '' && $firstname === '')) {
        $resolved = reedcrm_call_list_resolve_element_contact($db, (string) $line->element_type, (int) $line->element_id);
        if ($lastname === '' && $firstname === '') {
            $lastname  = (string) $resolved['lastname'];
            $firstname = (string) $resolved['firstname'];
        }
        if ($phone === '' && !empty($resolved['phone'])) {
            $phone = (string) $resolved['phone'];
        }
    }

    return [
        'lastname'  => $lastname,
        'firstname' => $firstname,
        'phone'     => $phone,
    ];
}

/**
 * Add an element (project / propal / facture) to a call list.
 *
 * Shared business core used by the card form, select widget endpoint, default-list star endpoint
 * and list mass actions. Resolves phone/contact via the fallback chain (opportunity -> element contact
 * -> thirdparty -> thirdparty contacts), optionally persists a manually entered phone on the opportunity,
 * guards against duplicates, and creates the CallListLine.
 *
 * @param  DoliDB $db                Database handler
 * @param  User   $user              Acting user
 * @param  int    $callListId        Target call list id
 * @param  string $elementType       'project' | 'propal' | 'facture'
 * @param  int    $elementId         Element id
 * @param  string $manualPhone       Optional phone entered when none was found or when editing opportunity phone
 * @param  int    $selectedContactId Optional explicit contact id (e.g. chosen from thirdparty contacts)
 * @param  string $note              Optional line note
 * @param  string $phoneSource       Optional explicit choice from card UI ('project' | 'thirdparty' | 'contact' | '')
 * @return array                     ['success' => bool, 'message' => string, 'need_phone' => bool, 'line_id' => int]
 */
function reedcrm_add_element_to_call_list(DoliDB $db, User $user, int $callListId, string $elementType, int $elementId, string $manualPhone = '', int $selectedContactId = 0, string $note = '', string $phoneSource = ''): array
{
    global $langs;

    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
    dol_include_once('/reedcrm/class/calllist.class.php');
    dol_include_once('/reedcrm/class/calllistline.class.php');

    $langs->load('reedcrm@reedcrm');

    $callList = new CallList($db);
    if ($callList->fetch($callListId) <= 0) {
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListNotFound')];
    }
    if (!in_array($callList->status, [CallList::STATUS_DRAFT, CallList::STATUS_ACTIVE])) {
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListCannotAddToArchivedList')];
    }

    $lineObject = new CallListLine($db);
    if ($lineObject->existsByElement($callListId, $elementType, $elementId)) {
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetDuplicate')];
    }

    if (reedcrm_call_list_fetch_element($db, $elementType, $elementId) === null) {
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetError')];
    }

    $cleanManualPhone = trim($manualPhone);
    $fkContact        = 0;

    if ($phoneSource === 'project') {
        $saveResult = reedcrm_call_list_save_element_phone($db, $user, $elementType, $elementId, $cleanManualPhone);
        if (empty($saveResult['success'])) {
            return ['success' => false, 'message' => $saveResult['message'] ?: $langs->transnoentitiesnoconv('CallListWidgetError')];
        }
    } elseif ($phoneSource === 'contact') {
        if ($selectedContactId <= 0) {
            return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetNoContact')];
        }
        $contactObj = new Contact($db);
        if ($contactObj->fetch($selectedContactId) <= 0 || reedcrm_call_list_pick_contact_phone($contactObj) === '') {
            return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetNoPhone')];
        }
        $fkContact = (int) $contactObj->id;
    } elseif ($phoneSource === 'thirdparty') {
        $resolved = reedcrm_call_list_resolve_element_contact($db, $elementType, $elementId);
        if (empty($resolved['found'])) {
            return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetNoContact')];
        }
        $fkContact = 0;
    } else {
        if ($cleanManualPhone !== '') {
            $saveResult = reedcrm_call_list_save_element_phone($db, $user, $elementType, $elementId, $cleanManualPhone);
            if (empty($saveResult['success'])) {
                return ['success' => false, 'message' => $saveResult['message'] ?: $langs->transnoentitiesnoconv('CallListWidgetError')];
            }
        }

        if ($selectedContactId > 0) {
            $contactObj = new Contact($db);
            if ($contactObj->fetch($selectedContactId) > 0 && reedcrm_call_list_pick_contact_phone($contactObj) !== '') {
                $fkContact = (int) $contactObj->id;
            }
        }

        if ($fkContact === 0) {
            $resolved = reedcrm_call_list_resolve_element_contact($db, $elementType, $elementId);
            if (empty($resolved['found'])) {
                return [
                    'success'             => false,
                    'need_phone'          => true,
                    'can_save_on_project' => !empty($resolved['can_save_on_project']),
                    'message'             => $langs->transnoentitiesnoconv('CallListWidgetNoContact'),
                ];
            }
            $fkContact = (int) $resolved['contact_id'];
        }
    }

    $newLine               = new CallListLine($db);
    $newLine->fk_call_list = $callListId;
    $newLine->element_type = $elementType;
    $newLine->element_id   = $elementId;
    $newLine->fk_contact   = $fkContact;
    $newLine->status       = CallListLine::STATUS_TO_CALL;
    $newLine->note         = $note;

    if ($newLine->create($user) <= 0) {
        return ['success' => false, 'message' => $newLine->error ?: $langs->transnoentitiesnoconv('CallListWidgetError')];
    }

    // The line id lets the call lists column of the opportunity list take the line off again
    return ['success' => true, 'message' => $langs->transnoentitiesnoconv('CallListWidgetSuccess', $callList->getNomUrl(1)), 'line_id' => (int) $newLine->id];
}

/**
 * Call lists the lines of a call list can be copied or moved to: the draft and active ones the user can see, but that one.
 *
 * @param  DoliDB $db         Database handler
 * @param  User   $user       Acting user
 * @param  int    $callListId Call list the lines are in
 * @return array<int,string>  Label of each call list by id, in the order of the call lists
 */
function reedcrm_call_list_get_transfer_targets(DoliDB $db, User $user, int $callListId): array
{
    dol_include_once('/reedcrm/class/calllist.class.php');

    $callList  = new CallList($db);
    $callLists = $callList->fetchAll('ASC', CallList::SORT_FIELDS, 0, 0, ['customsql' => 't.status IN (' . CallList::STATUS_DRAFT . ', ' . CallList::STATUS_ACTIVE . ') AND t.rowid != ' . $callListId]);

    $targets = [];
    if (is_array($callLists)) {
        foreach ($callLists as $targetList) {
            if (reedcrm_call_list_user_can_read($user, $targetList)) {
                $targets[(int) $targetList->id] = (string) $targetList->label;
            }
        }
    }

    return $targets;
}

/**
 * Copy or move lines of a call list to another call list.
 *
 * A copy is a new line of the target list, to be called there: same element, contact and note, status "to call".
 * A move keeps the line itself, its status, its note and its date of entry. Both go to the end of the target list.
 * An element already in the target list stays where it is, in both cases: a call list never holds it twice.
 *
 * @param  DoliDB $db           Database handler
 * @param  User   $user         Acting user
 * @param  int    $callListId   Call list the lines are in
 * @param  int[]  $lineIds      Lines to copy or move
 * @param  int    $targetListId Call list to copy or move them to
 * @param  bool   $move         true to move the lines, false to copy them
 * @return array{done: int, duplicates: int, errors: string[], target: ?CallList} Lines copied or moved, lines left
 *                                                                                as their element is already in the
 *                                                                                target list, errors, target list
 */
function reedcrm_call_list_transfer_lines(DoliDB $db, User $user, int $callListId, array $lineIds, int $targetListId, bool $move): array
{
    global $langs;

    dol_include_once('/reedcrm/class/calllist.class.php');
    dol_include_once('/reedcrm/class/calllistline.class.php');

    $langs->load('reedcrm@reedcrm');

    $result = ['done' => 0, 'duplicates' => 0, 'errors' => [], 'target' => null];

    // The same lists as the selector: the user may see both, the target still takes calls
    $callList   = new CallList($db);
    $targetList = new CallList($db);
    if ($callList->fetch($callListId) <= 0 || $targetList->fetch($targetListId) <= 0 || $targetListId === $callListId) {
        $result['errors'][] = $langs->transnoentitiesnoconv('CallListNotFound');
        return $result;
    }
    if (!reedcrm_call_list_user_can_read($user, $callList) || !reedcrm_call_list_user_can_read($user, $targetList)) {
        $result['errors'][] = $langs->transnoentitiesnoconv('NotEnoughPermissions');
        return $result;
    }
    if (!in_array((int) $targetList->status, [CallList::STATUS_DRAFT, CallList::STATUS_ACTIVE], true)) {
        $result['errors'][] = $langs->transnoentitiesnoconv('CallListCannotAddToArchivedList');
        return $result;
    }
    $result['target'] = $targetList;

    $lineObject = new CallListLine($db);
    foreach (array_unique(array_map('intval', $lineIds)) as $lineId) {
        $line = new CallListLine($db);
        if ($lineId <= 0 || $line->fetch($lineId) <= 0 || (int) $line->fk_call_list !== $callListId || (int) $line->status === CallListLine::STATUS_DELETED) {
            continue;
        }

        if ($lineObject->existsByElement($targetListId, (string) $line->element_type, (int) $line->element_id)) {
            $result['duplicates']++;
            continue;
        }

        if ($move) {
            // At the end of the target list, as a new call would be
            $line->fk_call_list = $targetListId;
            $line->position     = reedcrm_call_list_next_position($db, $targetListId);
            $saved              = $line->update($user);
        } else {
            $saved = $line->copyToCallList($user, $targetListId);
        }

        if ($saved > 0) {
            $result['done']++;
        } else {
            $result['errors'][] = $line->error ?: $langs->transnoentitiesnoconv('CallListWidgetError');
        }
    }

    return $result;
}

/**
 * Position of a call added at the end of a call list, after the ones ordered by hand.
 *
 * @param  DoliDB $db         Database handler
 * @param  int    $callListId Call list
 * @return int                Position
 */
function reedcrm_call_list_next_position(DoliDB $db, int $callListId): int
{
    $sql   = 'SELECT MAX(position) AS max_position FROM ' . $db->prefix() . 'reedcrm_call_list_line';
    $sql  .= ' WHERE fk_call_list = ' . $callListId;
    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        return (int) $obj->max_position + 1;
    }

    return 0;
}

/**
 * Create follow-up records after a call list line status change (PWA status buttons).
 *
 * Behaviors are driven by the ReedCRM PWA admin toggles:
 * - REEDCRM_CALL_LIST_STATUS_CREATE_ACTIONCOMM: creates a phone event in the agenda, linked to the
 *   line contact / element (propal or project)
 * - REEDCRM_CALL_LIST_STATUS_CREATE_TASK: creates the commercial follow-up task on the related project
 *   if missing (commtask extrafield pattern) and adds the configured time spent (REEDCRM_TASK_TIMESPENT_VALUE)
 *
 * @param  DoliDB       $db     Database handler
 * @param  User         $user   Acting user
 * @param  CallListLine $line   Call list line (already updated with the new status)
 * @param  int          $status New status (STATUS_CALLED | STATUS_NO_ANSWER | STATUS_CALLBACK)
 * @return array                Warning messages (empty on full success)
 */
function reedcrm_call_list_line_record_status_change(DoliDB $db, User $user, CallListLine $line, int $status): array
{
    global $langs;

    $warnings = [];

    $createActioncomm = getDolGlobalInt('REEDCRM_CALL_LIST_STATUS_CREATE_ACTIONCOMM') && isModEnabled('agenda');
    $createTask       = getDolGlobalInt('REEDCRM_CALL_LIST_STATUS_CREATE_TASK') && isModEnabled('project');

    if (!$createActioncomm && !$createTask) {
        return $warnings;
    }

    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
    dol_include_once('/reedcrm/class/calllist.class.php');
    dol_include_once('/reedcrm/class/calllistline.class.php');

    $langs->load('reedcrm@reedcrm');

    $callList = new CallList($db);
    $callList->fetch($line->fk_call_list);

    // Resolve the related propal / project of the line (project either directly or through the propal)
    $propal = null;
    if ($line->element_type === 'propal' && !empty($line->element_id) && isModEnabled('propale')) {
        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
        $propal = new Propal($db);
        if ($propal->fetch($line->element_id) <= 0) {
            $propal = null;
        }
    }

    $projectId = 0;
    if ($line->element_type === 'project' && !empty($line->element_id)) {
        $projectId = (int) $line->element_id;
    } elseif ($propal !== null && $propal->fk_project > 0) {
        $projectId = (int) $propal->fk_project;
    }

    $project = null;
    if ($projectId > 0 && isModEnabled('project')) {
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
        $project = new Project($db);
        if ($project->fetch($projectId) <= 0) {
            $project = null;
        }
    }

    // Wording of the status part of the event title, configurable per status in the call list setup
    // (admin/call_list.php) so teams can log their own standard codes (Repondeur, RLM, RPM...).
    // Left empty, it falls back to the plain status label: no change for an existing setup.
    $statusLabel = getDolGlobalString('REEDCRM_CALL_LIST_EVENT_LABEL_' . $status) ?: $langs->transnoentities('CallListLineStatus' . $status);

    $eventLabel = $langs->transnoentities('CallFrom') . ' ' . $callList->label . ' - ' . $statusLabel;

    // Phone event in the agenda
    if ($createActioncomm) {
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        $actioncomm                 = new ActionComm($db);
        $actioncomm->type_code      = 'AC_TEL';
        $actioncomm->label          = $eventLabel;
        $actioncomm->datep          = dol_now();
        $actioncomm->datef          = dol_now();
        $actioncomm->percentage     = 100;
        $actioncomm->userownerid    = $user->id;
        $actioncomm->fk_user_author = $user->id;

        if (!empty($line->fk_contact)) {
            $contact = new Contact($db);
            if ($contact->fetch($line->fk_contact) > 0) {
                $actioncomm->socpeopleassigned[$contact->id] = ['id' => $contact->id, 'mandatory' => 0];
                if ($contact->socid > 0) {
                    $actioncomm->socid = $contact->socid;
                }
            }
        }
        if (empty($actioncomm->socid)) {
            if ($propal !== null && $propal->socid > 0) {
                $actioncomm->socid = $propal->socid;
            } elseif ($project !== null && $project->socid > 0) {
                $actioncomm->socid = $project->socid;
            }
        }

        // Attach the event to the call list (Agenda tab on the call list card) and to the
        // related project so it stays visible on the object side too
        $actioncomm->fk_element  = $callList->id;
        $actioncomm->elementtype = 'call_list@reedcrm';
        if ($project !== null) {
            $actioncomm->fk_project = $project->id;
        }

        if ($actioncomm->create($user) <= 0) {
            $warnings[] = $actioncomm->error ?: 'ActionComm creation failed';
        }
    }

    // Commercial follow-up task on the related project + time spent
    if ($createTask && $project !== null) {
        require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

        $project->fetch_optionals();
        $commTaskId = (int) ($project->array_options['options_commtask'] ?? 0);

        $task = new Task($db);
        if ($commTaskId > 0 && $task->fetch($commTaskId) <= 0) {
            $commTaskId = 0;
        }

        if ($commTaskId <= 0) {
            $defaultRef  = '';
            $modTaskName = getDolGlobalString('PROJECT_TASK_ADDON', 'mod_task_simple');
            if (is_readable(DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $modTaskName . '.php')) {
                require_once DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $modTaskName . '.php';
                $modTask    = new $modTaskName();
                $defaultRef = $modTask->getNextValue(null, $task);
            }

            $task->fk_project = $project->id;
            $task->ref        = $defaultRef;
            $task->label      = (getDolGlobalString('REEDCRM_TASK_LABEL_VALUE') ?: $langs->transnoentities('CommercialFollowUp')) . ' - ' . $project->title;
            $task->date_c     = dol_now();

            $commTaskId = $task->create($user);
            if ($commTaskId > 0) {
                $internalUserIds = $project->liste_contact(-1, 'internal', 1);
                if (!is_array($internalUserIds) || !in_array($user->id, $internalUserIds)) {
                    $project->add_contact($user->id, 'PROJECTLEADER', 'internal');
                }
                $task->add_contact($user->id, 'TASKEXECUTIVE', 'internal');
                $project->array_options['options_commtask'] = $commTaskId;
                $project->updateExtraField('commtask');
            } else {
                $warnings[] = $task->error ?: 'Task creation failed';
            }
        }

        if ($commTaskId > 0) {
            $timeSpent = getDolGlobalInt('REEDCRM_TASK_TIMESPENT_VALUE');
            if ($timeSpent > 0) {
                $task->timespent_date     = dol_now();
                $task->timespent_note     = $eventLabel;
                $task->timespent_duration = $timeSpent * 60;
                $task->timespent_fk_user  = $user->id;
                if ($task->addTimeSpent($user, 1) <= 0) {
                    $warnings[] = $task->error ?: 'Time spent creation failed';
                }
            }
        }
    }

    return $warnings;
}

/**
 * Return the indicators of the todo cards for the lines of a call list.
 *
 * Each line reads like a todo card:
 * - origin: the object called about, its amount excluding tax rounded to its order of magnitude
 *   and its number of relaunches, counted with the very rule of the todo (reedcrmTodoCountRelaunches())
 * - soc_name: the third party of that object
 * - late / upcoming: same rule as a todo card, applied to the next event still to do on the
 *   object or on its project, the one the line is about to deal with
 * - last_call_date / last_call_label: the last call made about the object or its project
 *
 * Everything is read in batch, a fixed number of queries whatever the number of lines.
 *
 * @param  DoliDB         $db    Database handler
 * @param  CallListLine[] $lines Lines of the call list
 * @return array                 [line id => ['origin' => [], 'soc_name' => , 'late' => , 'upcoming' => , 'next_event_title' => ,
 *                               'next_event_date' => , 'next_event_label' => , 'last_call_date' => , 'last_call_label' => ]]
 */
function reedcrm_call_list_get_line_indicators(DoliDB $db, array $lines): array
{
    global $conf, $langs;

    require_once __DIR__ . '/reedcrm_todo.lib.php';

    $sources = [
        'propal'  => ['table' => 'propal',  'fk_project' => 'fk_projet', 'amount' => 'total_ht',   'url' => '/comm/propal/card.php?id=',    'picto' => 'fa-file-signature',      'event_element' => 'propal',  'module' => 'propale'],
        'facture' => ['table' => 'facture', 'fk_project' => 'fk_projet', 'amount' => 'total_ht',   'url' => '/compta/facture/card.php?id=', 'picto' => 'fa-file-invoice-dollar', 'event_element' => 'invoice', 'module' => 'facture'],
        'project' => ['table' => 'projet',  'fk_project' => 'rowid',     'amount' => 'opp_amount', 'url' => '/projet/card.php?id=',         'picto' => 'fa-project-diagram',     'event_element' => '',        'module' => 'project'],
    ];

    $lineIdsByElement = [];
    foreach ($lines as $line) {
        if (!empty($line->element_id) && isset($sources[$line->element_type]) && isModEnabled($sources[$line->element_type]['module'])) {
            $lineIdsByElement[$line->element_type][(int) $line->element_id][] = (int) $line->id;
        }
    }

    // Object of every line, with its third party and its project
    $origins  = [];
    $socNames = [];
    foreach ($lineIdsByElement as $elementType => $lineIdsByObject) {
        $source = $sources[$elementType];

        $sql  = 'SELECT t.rowid, t.ref, t.fk_soc, t.' . $source['fk_project'] . ' as fk_project, t.' . $source['amount'] . ' as amount, s.nom as soc_name';
        $sql .= ' FROM ' . $db->prefix() . $source['table'] . ' as t';
        $sql .= ' LEFT JOIN ' . $db->prefix() . 'societe as s ON s.rowid = t.fk_soc';
        $sql .= ' WHERE t.rowid IN (' . implode(',', array_keys($lineIdsByObject)) . ')';

        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
            continue;
        }
        while ($obj = $db->fetch_object($resql)) {
            $amount = (float) $obj->amount;
            foreach ($lineIdsByObject[(int) $obj->rowid] as $lineId) {
                $origins[$lineId] = [
                    'type'            => $elementType,
                    'id'              => (int) $obj->rowid,
                    'ref'             => $obj->ref,
                    'url'             => DOL_URL_ROOT . $source['url'] . (int) $obj->rowid,
                    'picto'           => $source['picto'],
                    'event_element'   => $source['event_element'],
                    'amount_ht'       => $amount,
                    'amount_ht_short' => reedcrmTodoFormatAmountMagnitude($amount),
                    'amount_ht_full'  => $langs->trans('TodoOriginAmountHT', price($amount, 0, $langs, 1, -1, -1, $conf->currency)),
                    'soc_id'          => (int) $obj->fk_soc,
                    'project'         => (int) $obj->fk_project,
                ];
                $socNames[$lineId] = (string) $obj->soc_name;
            }
        }
        $db->free($resql);
    }

    if (empty($origins)) {
        return [];
    }

    $relaunchCounts = reedcrmTodoCountRelaunches($db, $origins);
    $nextEvents     = reedcrm_call_list_get_next_events($db, $origins);
    $lastCalls      = reedcrm_call_list_get_last_calls($db, $origins);

    $now      = dol_now();
    $today    = dol_getdate($now);
    $todayEnd = dol_mktime(23, 59, 59, $today['mon'], $today['mday'], $today['year']);

    $indicators = [];
    foreach ($origins as $lineId => $origin) {
        $origin['relaunch_count'] = (int) ($relaunchCounts[$lineId] ?? 0);

        $nextEvent = $nextEvents[$lineId] ?? [];
        $indicators[$lineId] = [
            'origin'           => $origin,
            'soc_name'         => $socNames[$lineId] ?? '',
            'late'             => (!empty($nextEvent) && $nextEvent['datep'] < $now) ? 1 : 0,
            'upcoming'         => (!empty($nextEvent) && $nextEvent['datep'] > $todayEnd) ? 1 : 0,
            'next_event_title' => !empty($nextEvent) ? $nextEvent['label'] . ' - ' . dol_print_date($nextEvent['datep'], 'dayhour') : '',
            'next_event_date'  => !empty($nextEvent) ? $nextEvent['datep'] : 0,
            'next_event_label' => !empty($nextEvent) ? $nextEvent['label'] : '',
            'last_call_date'   => $lastCalls[$lineId]['datep'] ?? 0,
            'last_call_label'  => $lastCalls[$lineId]['label'] ?? '',
        ];
    }

    return $indicators;
}

/**
 * Return the next event still to do on the object of each line, or on its project.
 *
 * "Still to do" is the todo rule: a percentage between 0 and 99 and a start date.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $origins Objects of the lines, indexed by line ID (see reedcrm_call_list_get_line_indicators())
 * @return array           [line id => ['datep' => timestamp, 'label' => string]]
 */
function reedcrm_call_list_get_next_events(DoliDB $db, array $origins): array
{
    return reedcrm_call_list_get_closest_events($db, $origins, 'a.percent >= 0 AND a.percent < 100 AND a.datep IS NOT NULL', 'ASC');
}

/**
 * Return the last call made about the object of each line, or about its project.
 *
 * A call made is a phone event (AC_TEL, the type of the calls the call list and the App record)
 * that has started and is no longer to do: done, or without status (-1). A reminder to call back
 * is a phone event too, still to do: it is not a call made.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $origins Objects of the lines, indexed by line ID (see reedcrm_call_list_get_line_indicators())
 * @return array           [line id => ['datep' => timestamp, 'label' => string]]
 */
function reedcrm_call_list_get_last_calls(DoliDB $db, array $origins): array
{
    $condition = "a.code = 'AC_TEL' AND (a.percent < 0 OR a.percent >= 100) AND a.datep <= '" . $db->idate(dol_now()) . "'";

    return reedcrm_call_list_get_closest_events($db, $origins, $condition, 'DESC');
}

/**
 * Return, for each line, the first event met on its object or on its project, in the order of their start date.
 *
 * Without the right on every action, only the events the user owns or is assigned to count, as on the todo.
 *
 * @param  DoliDB $db        Database handler
 * @param  array  $origins   Objects of the lines, indexed by line ID (see reedcrm_call_list_get_line_indicators())
 * @param  string $condition SQL condition on the events (alias a)
 * @param  string $order     ASC for the closest to come, DESC for the latest
 * @return array             [line id => ['datep' => timestamp, 'label' => string]]
 */
function reedcrm_call_list_get_closest_events(DoliDB $db, array $origins, string $condition, string $order): array
{
    global $user;

    $order = $order === 'DESC' ? 'DESC' : 'ASC';

    if (!isModEnabled('agenda')) {
        return [];
    }

    $objectIds  = [];
    $projectIds = [];
    foreach ($origins as $origin) {
        if (!empty($origin['event_element'])) {
            $objectIds[$origin['event_element']][$origin['id']] = $origin['id'];
        }
        if (!empty($origin['project'])) {
            $projectIds[$origin['project']] = $origin['project'];
        }
    }

    $linkConditions = [];
    foreach ($objectIds as $eventElement => $ids) {
        $linkConditions[] = "(a.elementtype = '" . $db->escape($eventElement) . "' AND a.fk_element IN (" . implode(',', $ids) . '))';
    }
    if (!empty($projectIds)) {
        $linkConditions[] = 'a.fk_project IN (' . implode(',', $projectIds) . ')';
    }
    if (empty($linkConditions)) {
        return [];
    }

    $sql  = 'SELECT a.id, a.label, a.datep, a.elementtype, a.fk_element, a.fk_project';
    $sql .= ' FROM ' . $db->prefix() . 'actioncomm as a';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    $sql .= ' AND ' . $condition;
    $sql .= ' AND (' . implode(' OR ', $linkConditions) . ')';
    if (!$user->hasRight('agenda', 'allactions', 'read')) {
        $sql .= ' AND ' . reedcrmTodoGetUserCondition((int) $user->id);
    }
    $sql .= ' ORDER BY a.datep ' . $order . ', a.id ' . $order;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $firstByObject  = [];
    $firstByProject = [];
    while ($obj = $db->fetch_object($resql)) {
        // Rows come ordered on the date: the first one met is the one wanted
        $event = ['datep' => (int) $db->jdate($obj->datep), 'label' => (string) $obj->label];
        if (!empty($obj->elementtype) && !isset($firstByObject[$obj->elementtype][(int) $obj->fk_element])) {
            $firstByObject[$obj->elementtype][(int) $obj->fk_element] = $event;
        }
        if (!empty($obj->fk_project) && !isset($firstByProject[(int) $obj->fk_project])) {
            $firstByProject[(int) $obj->fk_project] = $event;
        }
    }
    $db->free($resql);

    $events = [];
    foreach ($origins as $lineId => $origin) {
        $candidates = array_filter([
            $firstByObject[$origin['event_element']][$origin['id']] ?? [],
            $firstByProject[$origin['project']] ?? [],
        ]);
        if (!empty($candidates)) {
            usort($candidates, function ($a, $b) use ($order) {
                return $order === 'DESC' ? $b['datep'] <=> $a['datep'] : $a['datep'] <=> $b['datep'];
            });
            $events[$lineId] = $candidates[0];
        }
    }

    return $events;
}

/**
 * Batch-load standard ReedCRM list context (projects, thirdparties, lifecycle steps and source links)
 * for all lines of a call list without N+1 queries.
 *
 * @param  DoliDB         $db             Database handler
 * @param  CallListLine[] $lines          Call list lines
 * @param  array          $lineIndicators Indicators returned by reedcrm_call_list_get_line_indicators()
 * @return array                          ['projects' => Project[], 'thirdparties' => Societe[], 'lifecycle' => array, 'sources' => string[]]
 */
function reedcrm_call_list_get_lines_standard_context(DoliDB $db, array $lines, array $lineIndicators): array
{
    require_once __DIR__ . '/reedcrm_fields.lib.php';
    require_once __DIR__ . '/reedcrm_lifecycle.lib.php';

    $context = [
        'projects'     => [],
        'thirdparties' => [],
        'lifecycle'    => [],
        'sources'      => [],
    ];

    if (empty($lines)) {
        return $context;
    }

    $projectIds = [];
    $socIds     = [];
    $propalIds  = [];
    $factureIds = [];

    foreach ($lines as $line) {
        $origin = $lineIndicators[(int) $line->id]['origin'] ?? [];
        if (!empty($origin['project'])) {
            $projectIds[(int) $origin['project']] = (int) $origin['project'];
        }
        if (!empty($origin['soc_id'])) {
            $socIds[(int) $origin['soc_id']] = (int) $origin['soc_id'];
        }
        if ($line->element_type === 'propal' && !empty($line->element_id)) {
            $propalIds[(int) $line->element_id] = (int) $line->element_id;
        } elseif ($line->element_type === 'facture' && !empty($line->element_id)) {
            $factureIds[(int) $line->element_id] = (int) $line->element_id;
        }
    }

    // 1. Batch-load projects (opportunities)
    if (!empty($projectIds) && isModEnabled('projet')) {
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

        $sql  = 'SELECT rowid, ref, title, fk_soc, fk_opp_status, opp_percent, opp_amount, fk_statut';
        $sql .= ' FROM ' . $db->prefix() . 'projet';
        $sql .= ' WHERE rowid IN (' . implode(',', $projectIds) . ')';

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $proj                = new Project($db);
                $proj->id            = (int) $obj->rowid;
                $proj->ref           = (string) $obj->ref;
                $proj->title         = (string) $obj->title;
                $proj->socid         = (int) $obj->fk_soc;
                $proj->fk_opp_status = (int) $obj->fk_opp_status;
                $proj->opp_percent   = $obj->opp_percent !== null ? (float) $obj->opp_percent : null;
                $proj->opp_amount    = $obj->opp_amount !== null ? (float) $obj->opp_amount : null;
                $proj->fk_statut     = (int) $obj->fk_statut;
                $proj->statut        = (int) $obj->fk_statut;

                $context['projects'][$proj->id] = $proj;
                if ($proj->socid > 0) {
                    $socIds[$proj->socid] = $proj->socid;
                }
            }
            $db->free($resql);
        }

        $context['lifecycle'] = reedcrm_lifecycle_fetch_projects(array_values($projectIds));
    }

    // 2. Batch-load thirdparties with the columns required by Societe::getNomUrl(1)
    if (!empty($socIds)) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

        $socCols = reedcrm_thirdparty_list_columns();
        $select  = ['rowid'];
        foreach ($socCols as $prop => $col) {
            $select[] = $col . ' AS reedcrm_soc_' . $prop;
        }

        $sql  = 'SELECT ' . implode(', ', $select);
        $sql .= ' FROM ' . $db->prefix() . 'societe';
        $sql .= ' WHERE rowid IN (' . implode(',', $socIds) . ')';

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $soc     = new Societe($db);
                $soc->id = (int) $obj->rowid;
                foreach (array_keys($socCols) as $prop) {
                    $soc->$prop = $obj->{'reedcrm_soc_' . $prop};
                }
                $context['thirdparties'][$soc->id] = $soc;
            }
            $db->free($resql);
        }
    }

    // 3. Batch-load propal / facture objects for native getNomUrl(1)
    $propalsById  = [];
    $facturesById = [];
    if (!empty($propalIds) && isModEnabled('propale')) {
        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
        $sql   = 'SELECT rowid, ref, ref_client, fk_statut, total_ht, fk_soc FROM ' . $db->prefix() . 'propal WHERE rowid IN (' . implode(',', $propalIds) . ')';
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $p             = new Propal($db);
                $p->id         = (int) $obj->rowid;
                $p->ref        = (string) $obj->ref;
                $p->ref_client = (string) $obj->ref_client;
                $p->statut     = (int) $obj->fk_statut;
                $p->total_ht   = (float) $obj->total_ht;
                $p->socid      = (int) $obj->fk_soc;
                $propalsById[$p->id] = $p;
            }
            $db->free($resql);
        }
    }
    if (!empty($factureIds) && isModEnabled('facture')) {
        require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
        $sql   = 'SELECT rowid, ref, ref_client, fk_statut, paye, type, total_ht, fk_soc FROM ' . $db->prefix() . 'facture WHERE rowid IN (' . implode(',', $factureIds) . ')';
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $f             = new Facture($db);
                $f->id         = (int) $obj->rowid;
                $f->ref        = (string) $obj->ref;
                $f->ref_client = (string) $obj->ref_client;
                $f->statut     = (int) $obj->fk_statut;
                $f->paye       = (int) $obj->paye;
                $f->type       = (int) $obj->type;
                $f->total_ht   = (float) $obj->total_ht;
                $f->socid      = (int) $obj->fk_soc;
                $facturesById[$f->id] = $f;
            }
            $db->free($resql);
        }
    }

    foreach ($lines as $line) {
        $lid = (int) $line->id;
        $eid = (int) $line->element_id;
        if ($line->element_type === 'project' && isset($context['projects'][$eid])) {
            $context['sources'][$lid] = $context['projects'][$eid]->getNomUrl(1);
        } elseif ($line->element_type === 'propal' && isset($propalsById[$eid])) {
            $context['sources'][$lid] = $propalsById[$eid]->getNomUrl(1);
        } elseif ($line->element_type === 'facture' && isset($facturesById[$eid])) {
            $context['sources'][$lid] = $facturesById[$eid]->getNomUrl(1);
        } else {
            $context['sources'][$lid] = '';
        }
    }

    return $context;
}

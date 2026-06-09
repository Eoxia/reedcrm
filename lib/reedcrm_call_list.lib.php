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
    global $conf, $langs;

    saturne_load_langs();

    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id;
    $head[$h][1] = $langs->trans('CallList');
    $head[$h][2] = 'card';
    $h++;

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id . '&show=notes';
    $head[$h][1] = $langs->trans('Notes');
    $head[$h][2] = 'notes';
    $h++;

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
    require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllist.class.php';

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
    $callList->status         = CallList::STATUS_ACTIVE;
    $callList->fk_user_assign = $targetUser->id;
    $callList->entity         = $entity;

    $newId = $callList->create($targetUser);
    if ($newId <= 0) {
        return -1;
    }

    // dol_set_user_param writes the param and keeps $targetUser->conf in sync
    if (dol_set_user_param($db, $conf, $targetUser, ['REEDCRM_DEFAULT_CALL_LIST' => (string) $newId], $entity) < 0) {
        return -2;
    }

    return $newId;
}

/**
 * Add an element (project / propal / facture) to a call list.
 *
 * Shared business core used by both the select widget endpoint and the
 * default-list star endpoint. Resolves the first external contact (excluding
 * PROJECTADDRESS), guards against duplicates, and creates the CallListLine.
 *
 * @param  DoliDB $db          Database handler
 * @param  User   $user        Acting user
 * @param  int    $callListId  Target call list id
 * @param  string $elementType 'project' | 'propal' | 'facture'
 * @param  int    $elementId   Element id
 * @return array               ['success' => bool, 'message' => string]
 */
function reedcrm_add_element_to_call_list(DoliDB $db, User $user, int $callListId, string $elementType, int $elementId): array
{
    global $langs;

    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
    require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllist.class.php';
    require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllistline.class.php';

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
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetError')];
    }

    $contacts = array_filter(
        (array) $element->liste_contact(-1, 'external'),
        static function ($c) { return $c['code'] !== 'PROJECTADDRESS'; }
    );

    if (empty($contacts)) {
        // No linked Dolibarr contact: for a project, fall back to its own coordinates
        // (ReedCRM extrafields). The line is created without fk_contact and the name/phone
        // are resolved from the project at display time (see call_list_card / pwa_call_list).
        if ($elementType === 'project') {
            $element->fetch_optionals();
            $projectPhone = trim((string) ($element->array_options['options_projectphone'] ?? ''));
            if ($projectPhone !== '') {
                $newLine               = new CallListLine($db);
                $newLine->fk_call_list = $callListId;
                $newLine->element_type = $elementType;
                $newLine->element_id   = $elementId;
                $newLine->fk_contact   = 0;
                $newLine->status       = CallListLine::STATUS_TO_CALL;

                if ($newLine->create($user) <= 0) {
                    return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetError')];
                }

                return ['success' => true, 'message' => $langs->transnoentitiesnoconv('CallListWidgetSuccess', $callList->getNomUrl(1))];
            }
        }

        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetNoContact')];
    }

    $firstContact = reset($contacts);

    $contactObj = new Contact($db);
    if ($contactObj->fetch((int) $firstContact['id']) <= 0) {
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetError')];
    }
    if (empty($contactObj->phone_pro) && empty($contactObj->phone_mobile) && empty($contactObj->phone_perso)) {
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetNoPhone')];
    }

    $newLine               = new CallListLine($db);
    $newLine->fk_call_list = $callListId;
    $newLine->element_type = $elementType;
    $newLine->element_id   = $elementId;
    $newLine->fk_contact   = (int) $contactObj->id;
    $newLine->status       = CallListLine::STATUS_TO_CALL;

    if ($newLine->create($user) <= 0) {
        return ['success' => false, 'message' => $langs->transnoentitiesnoconv('CallListWidgetError')];
    }

    return ['success' => true, 'message' => $langs->transnoentitiesnoconv('CallListWidgetSuccess', $callList->getNomUrl(1))];
}

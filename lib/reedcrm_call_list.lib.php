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

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id;
    $head[$h][1] = $langs->trans('CallList');
    $head[$h][2] = 'card';
    $h++;

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id . '&show=notes';
    $head[$h][1] = $langs->trans('Notes');
    $head[$h][2] = 'notes';
    $h++;

    if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
        $head[$h][0] = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id . '&show=agenda';
        $head[$h][1] = $langs->trans('Events');
        $head[$h][2] = 'agenda';
        $h++;
    }

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'call_list@reedcrm');

    return $head;
}

/**
 * Return the entity the REEDCRM_DEFAULT_CALL_LIST param of a user lives in.
 *
 * Single source of truth so reads and writes always target the same row:
 * a user bound to an entity keeps the param there, a shared user (entity 0)
 * keeps it in the current entity.
 *
 * @param  User $targetUser User to resolve the entity of
 * @return int              Entity the param is stored in
 */
function reedcrm_default_call_list_entity(User $targetUser): int
{
    global $conf;

    return !empty($targetUser->entity) ? (int) $targetUser->entity : (int) $conf->entity;
}

/**
 * Return the id of the default call list of a user, 0 when none is set.
 *
 * Loads the user personal conf so the answer is reliable whatever the way the
 * user object was fetched (User::fetchAll does not load it, User::fetch only
 * loads it on demand and for the current entity).
 *
 * @param  User $targetUser User the default call list belongs to
 * @return int              Call list id, 0 when none is set
 */
function reedcrm_get_user_default_call_list(User $targetUser): int
{
    $targetUser->loadPersonalConf(reedcrm_default_call_list_entity($targetUser));

    return isset($targetUser->conf->REEDCRM_DEFAULT_CALL_LIST) ? (int) $targetUser->conf->REEDCRM_DEFAULT_CALL_LIST : 0;
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

    $entity = reedcrm_default_call_list_entity($targetUser);

    $existingListId = reedcrm_get_user_default_call_list($targetUser);

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
 * Set an existing call list as the default (favorite) call list of a user.
 *
 * Counterpart of reedcrm_get_or_create_user_default_call_list: only rewrites the
 * REEDCRM_DEFAULT_CALL_LIST user param, never creates a list.
 *
 * @param  DoliDB $db         Database handler
 * @param  User   $targetUser User the call list becomes the favorite of
 * @param  int    $callListId Call list id to make favorite
 * @return int                > 0 if OK, < 0 if KO
 */
function reedcrm_set_user_default_call_list(DoliDB $db, User $targetUser, int $callListId): int
{
    global $conf;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

    if ($targetUser->id <= 0 || $callListId <= 0) {
        return -1;
    }

    // dol_set_user_param writes the param and keeps $targetUser->conf in sync
    return dol_set_user_param($db, $conf, $targetUser, ['REEDCRM_DEFAULT_CALL_LIST' => (string) $callListId], reedcrm_default_call_list_entity($targetUser));
}

/**
 * Render the "my favorite call list" star button, toggled client side by js/modules/call_list.js.
 *
 * A star that is already on stays inert: a favorite must always be set, since the star widget
 * of project / propal / facture cards adds into it.
 *
 * @param  int  $callListId Call list the star acts on
 * @param  bool $isDefault  Whether the list already is the favorite of the current user
 * @return string           HTML of the button
 */
function reedcrm_default_call_list_star(int $callListId, bool $isDefault): string
{
    global $langs;

    $langs->load('reedcrm@reedcrm');

    $labelSet = $langs->trans('SetAsDefaultCallList');
    $labelOn  = $langs->trans('MyDefaultCallList');

    $out  = '<button type="button" class="reedcrm-default-call-list-star' . ($isDefault ? ' reedcrm-default-call-list-star-on' : '') . '"';
    $out .= ' data-call-list-id="' . $callListId . '"';
    $out .= ' data-ajax-url="' . dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/set_default_call_list.php', 1)) . '"';
    $out .= ' data-label-on="' . dol_escape_htmltag($labelOn) . '"';
    $out .= '>';
    $out .= '<i class="' . ($isDefault ? 'fas' : 'far') . ' fa-star"></i>';
    $out .= '<span class="reedcrm-default-call-list-star-label">' . dol_escape_htmltag($isDefault ? $labelOn : $labelSet) . '</span>';
    $out .= '</button>';

    return $out;
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

    $eventLabel = $langs->transnoentities('CallFrom') . ' ' . $callList->label . ' - ' . $langs->transnoentities('CallListLineStatus' . $status);

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

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
 *
 * Everything is read in batch, a fixed number of queries whatever the number of lines.
 *
 * @param  DoliDB         $db    Database handler
 * @param  CallListLine[] $lines Lines of the call list
 * @return array                 [line id => ['origin' => [], 'soc_name' => , 'late' => , 'upcoming' => , 'next_event_title' => ,
 *                               'next_event_date' => , 'next_event_label' => ]]
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
        ];
    }

    return $indicators;
}

/**
 * Return the next event still to do on the object of each line, or on its project.
 *
 * "Still to do" is the todo rule: a percentage between 0 and 99 and a start date. Without the
 * right on every action, only the events the user owns or is assigned to count, as on the todo.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $origins Objects of the lines, indexed by line ID (see reedcrm_call_list_get_line_indicators())
 * @return array           [line id => ['datep' => timestamp, 'label' => string]]
 */
function reedcrm_call_list_get_next_events(DoliDB $db, array $origins): array
{
    global $user;

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
    $sql .= ' AND a.percent >= 0 AND a.percent < 100 AND a.datep IS NOT NULL';
    $sql .= ' AND (' . implode(' OR ', $linkConditions) . ')';
    if (!$user->hasRight('agenda', 'allactions', 'read')) {
        $sql .= ' AND ' . reedcrmTodoGetUserCondition((int) $user->id);
    }
    $sql .= ' ORDER BY a.datep ASC, a.id ASC';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $firstByObject  = [];
    $firstByProject = [];
    while ($obj = $db->fetch_object($resql)) {
        // Rows come ordered on the date: the first one met is the next one
        $event = ['datep' => (int) $db->jdate($obj->datep), 'label' => (string) $obj->label];
        if (!empty($obj->elementtype) && !isset($firstByObject[$obj->elementtype][(int) $obj->fk_element])) {
            $firstByObject[$obj->elementtype][(int) $obj->fk_element] = $event;
        }
        if (!empty($obj->fk_project) && !isset($firstByProject[(int) $obj->fk_project])) {
            $firstByProject[(int) $obj->fk_project] = $event;
        }
    }
    $db->free($resql);

    $nextEvents = [];
    foreach ($origins as $lineId => $origin) {
        $candidates = array_filter([
            $firstByObject[$origin['event_element']][$origin['id']] ?? [],
            $firstByProject[$origin['project']] ?? [],
        ]);
        if (!empty($candidates)) {
            usort($candidates, function ($a, $b) {
                return $a['datep'] <=> $b['datep'];
            });
            $nextEvents[$lineId] = $candidates[0];
        }
    }

    return $nextEvents;
}

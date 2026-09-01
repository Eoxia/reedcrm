<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    view/todo_list.php
 * \ingroup reedcrm
 * \brief   Todo board: agenda events on a Kanban whose columns are the event statuses
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_todo.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $moduleName, $user;

// Load translation files required by the page
saturne_load_langs(['agenda', 'commercial', 'projects']);

// Get parameters
$action = GETPOST('action', 'aZ09');

$object = null;

$hookmanager->initHooks(['reedcrmtodolist', 'globalcard']);

// Security check
$permissionToRead  = $user->hasRight('reedcrm', 'read') && $user->hasRight('agenda', 'myactions', 'read');
$permissionToWrite = $user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create');
saturne_check_access($permissionToRead);

/**
 * Answer an AJAX call of the board and stop the page
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $payload JSON payload
 * @param  int    $code    HTTP status code
 * @return void
 */
function reedcrmTodoAjaxAnswer(DoliDB $db, array $payload, int $code = 200)
{
    if ($code != 200) {
        http_response_code($code);
    }
    print json_encode($payload);
    $db->close();
    exit;
}

/**
 * Load the event of an AJAX call, answering right away when it may not be changed
 *
 * The resources are loaded along with the event: ActionComm::update() rewrites the
 * assigned users and contacts from the object, an event updated without them would
 * lose everybody it was assigned to.
 *
 * @param  DoliDB $db      Database handler
 * @param  User   $user    Connected user
 * @param  int    $eventId Event row ID
 * @return ActionComm      Loaded event
 */
function reedcrmTodoLoadEditableEvent(DoliDB $db, User $user, int $eventId): ActionComm
{
    $event = new ActionComm($db);
    if ($event->fetch($eventId) <= 0) {
        reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Event not found'], 404);
    }
    if (!reedcrmTodoCanEditEvent($event, $user)) {
        reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Not allowed'], 403);
    }
    $event->fetchResources();

    return $event;
}

/*
 * Actions
 */

$ajaxActions = ['updateEventPercent', 'updateEventLabel', 'updateEventDate', 'updateEventOwner', 'addEventAssigned', 'removeEventAssigned'];

if (in_array($action, $ajaxActions, true)) {
    header('Content-Type: application/json');

    $eventId = GETPOSTINT('event_id');
    if (empty($eventId)) {
        reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'No event ID'], 400);
    }
    if (!$permissionToWrite) {
        reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Not allowed'], 403);
    }

    $event = reedcrmTodoLoadEditableEvent($db, $user, $eventId);

    // Percentage of the event, moved by a drag & drop between columns or by the progress bar
    if ($action == 'updateEventPercent') {
        $newPercent = GETPOSTINT('new_percent');
        if ($newPercent < -1 || $newPercent > 100) {
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Percent out of range'], 400);
        }

        $event->percentage = $newPercent;
        if ($event->update($user) <= 0) {
            dol_syslog('ReedCRM todo board: updateEventPercent failed on event ' . $eventId . ' - ' . $event->error, LOG_ERR);
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => $event->error], 500);
        }
        reedcrmTodoAjaxAnswer($db, ['success' => 1, 'percent' => $newPercent]);
    }

    // Label of the event, edited in place on the card
    if ($action == 'updateEventLabel') {
        $newLabel = GETPOST('new_label', 'alphanohtml');
        if (empty(trim($newLabel))) {
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Empty label'], 400);
        }

        $event->label = $newLabel;
        if ($event->update($user) <= 0) {
            dol_syslog('ReedCRM todo board: updateEventLabel failed on event ' . $eventId . ' - ' . $event->error, LOG_ERR);
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => $event->error], 500);
        }
        reedcrmTodoAjaxAnswer($db, ['success' => 1, 'label' => $event->label]);
    }

    // Start or end date of the event
    if ($action == 'updateEventDate') {
        $field = GETPOST('field', 'alpha');
        $value = GETPOST('value', 'alpha');
        if (!in_array($field, ['date_start', 'date_end'], true)) {
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Unknown field'], 400);
        }

        $timestamp = !empty($value) ? strtotime($value) : 0;
        if (!empty($value) && empty($timestamp)) {
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Invalid date'], 400);
        }

        if ($field == 'date_start') {
            // An event always starts somewhere, only the end date may be cleared
            if (empty($timestamp)) {
                reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Empty start date'], 400);
            }
            $event->datep = $timestamp;
        } else {
            $event->datef = $timestamp > 0 ? $timestamp : null;
        }

        if ($event->update($user) <= 0) {
            dol_syslog('ReedCRM todo board: updateEventDate failed on event ' . $eventId . ' - ' . $event->error, LOG_ERR);
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => $event->error], 500);
        }

        // Same formats as the card: a readable date on screen, a picker-ready one in data-raw
        $format    = empty($event->fulldayevent) ? 'dayhour' : 'day';
        $rawFormat = empty($event->fulldayevent) ? '%Y-%m-%dT%H:%M' : '%Y-%m-%d';
        reedcrmTodoAjaxAnswer($db, [
            'success'   => 1,
            'formatted' => $timestamp > 0 ? dol_print_date($timestamp, $format) : '',
            'raw'       => $timestamp > 0 ? dol_print_date($timestamp, $rawFormat) : '',
        ]);
    }

    // Owner of the event
    if ($action == 'updateEventOwner') {
        $newUserId = GETPOSTINT('user_id');

        $event->userownerid = $newUserId;
        if ($newUserId > 0) {
            // The owner is also one of the assigned resources of the event
            $event->userassigned[$newUserId] = ['id' => $newUserId];
        }

        if ($event->update($user) <= 0) {
            dol_syslog('ReedCRM todo board: updateEventOwner failed on event ' . $eventId . ' - ' . $event->error, LOG_ERR);
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => $event->error], 500);
        }

        $ownerInfos = $newUserId > 0 ? reedcrmTodoGetUserInfos($db, [$newUserId]) : [];
        reedcrmTodoAjaxAnswer($db, ['success' => 1, 'owner' => $ownerInfos[$newUserId] ?? []]);
    }

    // Assigned users of the event
    if ($action == 'addEventAssigned' || $action == 'removeEventAssigned') {
        $assignedUserId = GETPOSTINT('user_id');
        if (empty($assignedUserId)) {
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'No user selected'], 400);
        }
        if ($action == 'removeEventAssigned' && $assignedUserId == $event->userownerid) {
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => 'Owner can not be unassigned'], 400);
        }

        if ($action == 'addEventAssigned') {
            $event->userassigned[$assignedUserId] = ['id' => $assignedUserId];
        } else {
            unset($event->userassigned[$assignedUserId]);
        }

        if ($event->update($user) <= 0) {
            dol_syslog('ReedCRM todo board: ' . $action . ' failed on event ' . $eventId . ' - ' . $event->error, LOG_ERR);
            reedcrmTodoAjaxAnswer($db, ['success' => 0, 'error' => $event->error], 500);
        }

        $assignedInfos = reedcrmTodoGetUserInfos($db, [$assignedUserId]);
        reedcrmTodoAjaxAnswer($db, ['success' => 1, 'user' => $assignedInfos[$assignedUserId] ?? []]);
    }
}

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/*
 * View
 */

$title   = $langs->trans('TodoBoard');
$helpUrl = 'FR:Module_' . $moduleName;

// Displayed events criteria (user, period, type of event, text), shared by the board and its counters
$todoFilters = reedcrmTodoGetFilters();
$todoColumns = reedcrmTodoGetKanbanColumns();
$todoEvents  = reedcrmTodoGetEvents($db, $todoFilters);
$todoUsers   = reedcrmTodoGetSelectableUsers($db);
$todoTypes   = reedcrmTodoGetEventTypes($db);

// The board scrolls sideways: #id-right is a table cell, only classforhorizontalscrolloftabs bounds it
saturne_header(0, '', $title, $helpUrl, '', 0, 0, [], [], '', 'classforhorizontalscrolloftabs');

$head       = [];
$head[0][0] = $_SERVER['PHP_SELF'];
$head[0][1] = '<i class="fas fa-clipboard-check pictofixedwidth"></i>' . $langs->trans('TodoBoard');
$head[0][2] = 'todo';

print dol_get_fiche_head($head, 'todo', $title, -1, 'fontawesome_fa-clipboard-check_fas_#63ACC9');

// New event button, pulled up onto the tab row: nothing may be printed between the tabs and it
require __DIR__ . '/../core/tpl/todo/todo_toolbar.tpl.php';

// User, period, type of event and text criteria
require __DIR__ . '/../core/tpl/todo/todo_filters.tpl.php';

// The board itself, one column per event status
require __DIR__ . '/../core/tpl/todo/todo_list_kanban.tpl.php';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();

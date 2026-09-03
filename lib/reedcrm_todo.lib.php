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
 * \file    lib/reedcrm_todo.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the todo board (agenda events Kanban)
 */

// Codes carried by the events the relaunch cron jobs create. They sit on the AC_OTH type
// of the dictionary, the same way Dolibarr flags its own events with AC_OTH_AUTO.
const REEDCRM_TODO_CODE_PROPAL_RELAUNCH  = 'AC_REEDCRM_PROPAL_RELAUNCH';
const REEDCRM_TODO_CODE_INVOICE_RELAUNCH = 'AC_REEDCRM_INVOICE_RELAUNCH';

/**
 * Return the Kanban columns of the todo board.
 *
 * Most columns are the statuses an agenda event can take. Dolibarr derives that status
 * from the percentage of the event (see ActionComm::LibStatut): -1 stands for an event
 * carrying no progress at all, 0 for a not started one, 100 for a finished one.
 *
 * The two first columns are the relaunch backlogs: they hold the events created by the
 * cron jobs, on their code rather than on a percentage, until they are done or dropped.
 *
 * @return array Ordered columns, each one carrying either a percentage range or a code
 */
function reedcrmTodoGetKanbanColumns(): array
{
    global $langs;

    return [
        ['key' => 'relaunch_propal',  'label' => $langs->trans('TodoColumnPropalRelaunch'),  'icon' => 'fa-file-signature',      'color' => '#9b59b6', 'min' => null, 'max' => null, 'code' => REEDCRM_TODO_CODE_PROPAL_RELAUNCH],
        ['key' => 'relaunch_invoice', 'label' => $langs->trans('TodoColumnInvoiceRelaunch'), 'icon' => 'fa-file-invoice-dollar', 'color' => '#e67e22', 'min' => null, 'max' => null, 'code' => REEDCRM_TODO_CODE_INVOICE_RELAUNCH],
        ['key' => 'todo',             'label' => $langs->trans('StatusActionToDo'),          'icon' => 'fa-hourglass-start',     'color' => '#e9ad4f', 'min' => 0,    'max' => 0,    'code' => ''],
        ['key' => 'progress',         'label' => $langs->trans('StatusActionInProcess'),     'icon' => 'fa-play',                'color' => '#3085d6', 'min' => 1,    'max' => 99,   'code' => ''],
        ['key' => 'done',             'label' => $langs->trans('StatusActionDone'),          'icon' => 'fa-check',               'color' => '#47e58e', 'min' => 100,  'max' => 100,  'code' => ''],
        ['key' => 'na',               'label' => $langs->trans('StatusNotApplicable'),       'icon' => 'fa-ban',                 'color' => '#8c9ba5', 'min' => -1,   'max' => -1,   'code' => ''],
    ];
}

/**
 * Return the column an event belongs to
 *
 * A relaunch stays in its own backlog as long as it is neither done nor dropped, so that
 * marking it done (100%) or not applicable (-1) is what takes it out of the backlog.
 *
 * @param  array $columns Columns from reedcrmTodoGetKanbanColumns()
 * @param  array $event   One enriched event of reedcrmTodoGetEvents()
 * @return array          Matching column, empty when the event fits none
 */
function reedcrmTodoGetColumnForEvent(array $columns, array $event): array
{
    $percent = (int) $event['percent'];

    foreach ($columns as $column) {
        if (!empty($column['code'])) {
            if (($event['code'] ?? '') === $column['code'] && $percent >= 0 && $percent < 100) {
                return $column;
            }
            continue;
        }
        if ($column['min'] !== null && $percent >= $column['min'] && $percent <= $column['max']) {
            return $column;
        }
    }

    return [];
}

/**
 * Read the criteria of the todo board from the query string
 *
 * The filter bar always posts `filtered`, so an untouched page can be told from a
 * deliberately emptied criterion (the "everybody" user is 0, just like the unset value).
 *
 * @return array Criteria used by reedcrmTodoGetEvents()
 */
function reedcrmTodoGetFilters(): array
{
    global $user;

    if (!GETPOSTINT('filtered')) {
        // Out of the box the board shows everything the connected user has to do, however old:
        // a lower bound on the start date can only ever hide what he is the most late on
        return [
            'user'       => (int) $user->id,
            'date_start' => 0,
            'date_end'   => 0,
            'type'       => 0,
            'search'     => '',
            'hide_auto'  => 1,
        ];
    }

    $dateStart = GETPOST('search_date_start', 'alpha');
    $dateEnd   = GETPOST('search_date_end', 'alpha');

    return [
        'user'       => GETPOSTINT('search_user'),
        'date_start' => !empty($dateStart) ? (int) strtotime($dateStart . ' 00:00:00') : 0,
        'date_end'   => !empty($dateEnd) ? (int) strtotime($dateEnd . ' 23:59:59') : 0,
        'type'       => GETPOSTINT('search_type'),
        'search'     => GETPOST('search_text', 'alphanohtml'),
        'hide_auto'  => GETPOSTINT('search_hide_auto'),
    ];
}

/**
 * Return the SQL condition matching the events a user owns or is assigned to
 *
 * @param  int    $userId User row ID
 * @return string         SQL condition
 */
function reedcrmTodoGetUserCondition(int $userId): string
{
    $sql  = '(a.fk_user_action = ' . $userId;
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'actioncomm_resources as r';
    $sql .= '            WHERE r.fk_actioncomm = a.id AND r.element_type = \'user\' AND r.fk_element = ' . $userId . '))';

    return $sql;
}

/**
 * Fetch the agenda events of the todo board, enriched for the Kanban cards
 *
 * Everything the cards display is loaded in batch (owners, assigned users, type of event)
 * so the board stays on a fixed number of queries whatever the number of events.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @param  int    $limit   Maximum number of events, 0 to use the module configuration
 * @return array           Enriched events, ordered by start date
 */
function reedcrmTodoGetEvents(DoliDB $db, array $filters, array $column, array $columns, string $direction = 'asc', int $offset = 0, int $limit = 0): array
{
    if ($limit <= 0) {
        $limit = reedcrmTodoGetPageSize();
    }
    $order = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

    $sql  = 'SELECT a.id, a.ref, a.label, a.datep, a.datep2, a.datec, a.percent, a.priority, a.location, a.fulldayevent, a.note, a.code,';
    $sql .= ' a.fk_soc, a.fk_project, a.fk_contact, a.fk_user_action, a.fk_element, a.elementtype,';
    $sql .= ' ca.id as type_id, ca.code as type_code, ca.libelle as type_label, ca.color as type_color, ca.picto as type_picto,';
    $sql .= ' s.nom as soc_name, p.ref as project_ref, p.title as project_title';
    $sql .= reedcrmTodoBuildEventsFrom($db, $filters);
    $sql .= ' AND ' . reedcrmTodoGetColumnSqlCondition($column, $columns);
    $sql .= ' ORDER BY ' . reedcrmTodoGetSortExpression() . ' ' . $order . ', a.id ' . $order;
    $sql .= $db->plimit($limit, $offset);

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $events   = [];
    $eventIds = [];
    $ownerIds = [];
    while ($obj = $db->fetch_object($resql)) {
        $events[(int) $obj->id] = $obj;
        $eventIds[]             = (int) $obj->id;
        if ($obj->fk_user_action > 0) {
            $ownerIds[] = (int) $obj->fk_user_action;
        }
    }
    $db->free($resql);

    if (empty($events)) {
        return [];
    }

    return reedcrmTodoEnrichEvents($db, $events, $eventIds, $ownerIds);
}

/**
 * Number of cards a column renders at once, the rest coming from "load more"
 *
 * @return int Page size
 */
function reedcrmTodoGetPageSize(): int
{
    $pageSize = getDolGlobalInt('REEDCRM_TODO_KANBAN_PAGE_SIZE', 30);

    return $pageSize > 0 ? $pageSize : 30;
}

/**
 * Return the FROM and the WHERE the board runs on, criteria included
 *
 * Shared by the counters and by the paged reading of a column, so that a card is never
 * counted on one set of criteria and read on another.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @return string          SQL fragment starting with FROM
 */
function reedcrmTodoBuildEventsFrom(DoliDB $db, array $filters): string
{
    global $user;

    $sql  = ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm as ca ON ca.id = a.fk_action';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = a.fk_project';
    // A relaunch carries no start date: it is ordered on the date of the object it was raised
    // on, the very one its note prints
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "propal as rp ON a.elementtype = 'propal' AND rp.rowid = a.fk_element";
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "facture as rf ON a.elementtype = 'invoice' AND rf.rowid = a.fk_element";
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';

    // Without the right on every action, a user only sees the events he owns or is assigned to
    if (!$user->hasRight('agenda', 'allactions', 'read')) {
        $sql .= ' AND ' . reedcrmTodoGetUserCondition((int) $user->id);
    }
    if (!empty($filters['user'])) {
        $sql .= ' AND ' . reedcrmTodoGetUserCondition((int) $filters['user']);
    }
    // The relaunch events carry no date on purpose: a period must never hide them
    if (!empty($filters['date_start'])) {
        $sql .= " AND (a.datep IS NULL OR a.datep >= '" . $db->idate($filters['date_start']) . "')";
    }
    if (!empty($filters['date_end'])) {
        $sql .= " AND (a.datep IS NULL OR a.datep <= '" . $db->idate($filters['date_end']) . "')";
    }
    if (!empty($filters['type'])) {
        $sql .= ' AND a.fk_action = ' . ((int) $filters['type']);
    }
    if (!empty($filters['hide_auto'])) {
        // Events logged by Dolibarr itself are not something anybody has to do
        $sql .= " AND (ca.type IS NULL OR ca.type <> 'systemauto')";
    }
    if (!empty($filters['search'])) {
        $searchValue = $db->escape($db->escapeforlike($filters['search']));
        $sql .= " AND (a.label LIKE '%" . $searchValue . "%' OR a.location LIKE '%" . $searchValue . "%' OR s.nom LIKE '%" . $searchValue . "%')";
    }

    return $sql;
}

/**
 * Date a column is ordered on, same cascade as the date_sort_ts of a card
 *
 * @return string SQL expression
 */
function reedcrmTodoGetSortExpression(): string
{
    return 'COALESCE(a.datep, rp.date_valid, rp.datep, rf.date_lim_reglement, rf.datef, a.datec)';
}

/**
 * Return the SQL condition matching the events of a column
 *
 * Mirrors reedcrmTodoGetColumnForEvent(): a backlog keyed on a code takes its events as long
 * as they are neither done nor dropped, and a column keyed on a percentage leaves them to it.
 *
 * @param  array $column  Column to match
 * @param  array $columns Every column of the board
 * @return string         SQL condition
 */
function reedcrmTodoGetColumnSqlCondition(array $column, array $columns): string
{
    if (!empty($column['code'])) {
        return "(a.code = '" . $column['code'] . "' AND a.percent >= 0 AND a.percent < 100)";
    }

    if ($column['min'] === null) {
        return '(1 = 0)';
    }

    $backlogCodes = [];
    foreach ($columns as $otherColumn) {
        if (!empty($otherColumn['code'])) {
            $backlogCodes[] = "'" . $otherColumn['code'] . "'";
        }
    }

    $sql = '(a.percent >= ' . ((int) $column['min']) . ' AND a.percent <= ' . ((int) $column['max']);
    if (!empty($backlogCodes)) {
        $sql .= ' AND NOT (a.code IN (' . implode(', ', $backlogCodes) . ') AND a.percent >= 0 AND a.percent < 100)';
    }

    return $sql . ')';
}

/**
 * Count the events of every column in one query, so a column knows how many cards are left
 * behind its "load more" without reading them
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @param  array  $columns Columns from reedcrmTodoGetKanbanColumns()
 * @return array           [column key => number of events]
 */
function reedcrmTodoCountByColumn(DoliDB $db, array $filters, array $columns): array
{
    $counts   = [];
    $selects  = [];
    foreach ($columns as $index => $column) {
        $counts[$column['key']] = 0;
        $selects[] = 'SUM(' . reedcrmTodoGetColumnSqlCondition($column, $columns) . ') as col' . ((int) $index);
    }

    if (empty($selects)) {
        return $counts;
    }

    $sql   = 'SELECT ' . implode(', ', $selects) . reedcrmTodoBuildEventsFrom($db, $filters);
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return $counts;
    }

    $obj = $db->fetch_object($resql);
    $db->free($resql);
    if (empty($obj)) {
        return $counts;
    }

    foreach ($columns as $index => $column) {
        $alias                  = 'col' . ((int) $index);
        $counts[$column['key']] = (int) $obj->$alias;
    }

    return $counts;
}

/**
 * Turn the rows of a column into the cards the templates render
 *
 * @param  DoliDB $db       Database handler
 * @param  array  $events   Rows read, indexed by event ID
 * @param  array  $eventIds IDs of those rows
 * @param  array  $ownerIds Owners to resolve
 * @return array            Enriched events
 */
function reedcrmTodoEnrichEvents(DoliDB $db, array $events, array $eventIds, array $ownerIds): array
{
    global $langs;

    $assignedByEvent = reedcrmTodoGetAssignedUsers($db, $eventIds);

    // Owners are not always part of the assigned resources, load the missing ones
    $ownerInfos = reedcrmTodoGetUserInfos($db, $ownerIds);

    // Proposal or invoice a relaunch event was raised on
    $originInfos = reedcrmTodoGetOriginInfos($db, $events);

    $now       = dol_now();
    $todoCards = [];
    foreach ($events as $eventId => $obj) {
        $percent = (int) $obj->percent;
        // Raw dates feed the native picker of the card: a full day event is edited on a
        // plain date, the others on a date and an hour
        $rawFormat = empty($obj->fulldayevent) ? '%Y-%m-%dT%H:%M' : '%Y-%m-%d';

        $assigned = $assignedByEvent[$eventId] ?? [];
        $owner    = $ownerInfos[(int) $obj->fk_user_action] ?? [];
        // The owner already shows up on his own chip, keep the others as assigned users
        if (!empty($owner)) {
            unset($assigned[$owner['id']]);
        }

        $origin = $originInfos[$eventId] ?? [];

        // Date the columns of the board are sorted on. A relaunch carries no start date on
        // purpose, so that a period never hides it: it falls back on the date of the object
        // it was raised on, the very one its note prints, then on its own creation date
        $dateSortTs = 0;
        if ($obj->datep) {
            $dateSortTs = (int) $db->jdate($obj->datep);
        } elseif (!empty($origin['date_ts'])) {
            $dateSortTs = (int) $origin['date_ts'];
        } elseif ($obj->datec) {
            $dateSortTs = (int) $db->jdate($obj->datec);
        }

        $todoCards[] = [
            'id'             => $eventId,
            'ref'            => !empty($obj->ref) ? $obj->ref : (string) $eventId,
            'code'           => (string) $obj->code,
            'label'          => !empty($obj->label) ? $obj->label : $langs->trans('TodoNoLabel'),
            'percent'        => $percent,
            'origin'         => $origin,
            'priority'       => (int) $obj->priority,
            'location'       => $obj->location,
            'note'           => dol_trunc(dol_string_nohtmltag((string) $obj->note), 160),
            'fullday'        => (int) $obj->fulldayevent,
            'date_start_ts'  => $obj->datep ? (int) $db->jdate($obj->datep) : 0,
            'date_sort_ts'   => $dateSortTs,
            'date_start'     => $obj->datep ? dol_print_date($db->jdate($obj->datep), $rawFormat) : '',
            'date_start_fmt' => $obj->datep ? dol_print_date($db->jdate($obj->datep), empty($obj->fulldayevent) ? 'dayhour' : 'day') : '',
            'date_end'       => $obj->datep2 ? dol_print_date($db->jdate($obj->datep2), $rawFormat) : '',
            'date_end_fmt'   => $obj->datep2 ? dol_print_date($db->jdate($obj->datep2), empty($obj->fulldayevent) ? 'dayhour' : 'day') : '',
            'late'           => ($obj->datep && $db->jdate($obj->datep) < $now && $percent >= 0 && $percent < 100) ? 1 : 0,
            'type_id'        => (int) $obj->type_id,
            'type_code'      => $obj->type_code,
            'type_label'     => reedcrmTodoGetTypeLabel((string) $obj->type_code, (string) $obj->type_label),
            'type_color'     => !empty($obj->type_color) ? '#' . ltrim($obj->type_color, '#') : '',
            'type_picto'     => reedcrmTodoGetTypeIcon((string) $obj->type_code),
            'soc_id'         => (int) $obj->fk_soc,
            'soc_name'       => $obj->soc_name,
            'project_id'     => (int) $obj->fk_project,
            'project_ref'    => $obj->project_ref,
            'project_title'  => $obj->project_title,
            'owner'          => $owner,
            'assigned'       => array_values($assigned),
            'url'            => DOL_URL_ROOT . '/comm/action/card.php?id=' . $eventId,
        ];
    }

    // The rows arrive in the order the column was read: a page of cards must never be
    // reordered here, it would no longer follow the one before it
    return $todoCards;
}

/**
 * Return the proposal or the invoice the relaunch events were raised on
 *
 * Only the two element types the relaunch cron jobs link to are resolved, one query each.
 *
 * The date each source is read on is the one the relaunch cron raised the event on, the very
 * one the note of the card prints: it is what the relaunch backlogs are sorted on, their
 * events carrying no start date.
 *
 * @param  DoliDB $db     Database handler
 * @param  array  $events Rows of the board, indexed by event ID
 * @return array          [event id => ['ref' => , 'url' => , 'type' => , 'date_ts' => ]]
 */
function reedcrmTodoGetOriginInfos(DoliDB $db, array $events): array
{
    $sources = [
        'propal'  => ['table' => 'propal',  'url' => '/comm/propal/card.php?id=', 'date' => 'COALESCE(date_valid, datep)'],
        'invoice' => ['table' => 'facture', 'url' => '/compta/facture/card.php?id=', 'date' => 'COALESCE(date_lim_reglement, datef)'],
    ];

    // Group the linked object IDs per element type
    $idsByType = [];
    foreach ($events as $eventId => $obj) {
        if (empty($obj->fk_element) || !isset($sources[$obj->elementtype])) {
            continue;
        }
        $idsByType[$obj->elementtype][(int) $obj->fk_element][] = $eventId;
    }

    $origins = [];
    foreach ($idsByType as $elementType => $elementIds) {
        $sql   = 'SELECT rowid, ref, ' . $sources[$elementType]['date'] . ' as date_reference';
        $sql  .= ' FROM ' . MAIN_DB_PREFIX . $sources[$elementType]['table'];
        $sql  .= ' WHERE rowid IN (' . implode(',', array_keys($elementIds)) . ')';
        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
            continue;
        }
        while ($obj = $db->fetch_object($resql)) {
            foreach ($elementIds[(int) $obj->rowid] as $eventId) {
                $origins[$eventId] = [
                    'type'    => $elementType,
                    'ref'     => $obj->ref,
                    'url'     => DOL_URL_ROOT . $sources[$elementType]['url'] . (int) $obj->rowid,
                    'date_ts' => $obj->date_reference ? (int) $db->jdate($obj->date_reference) : 0,
                ];
            }
        }
        $db->free($resql);
    }

    return $origins;
}

/**
 * Return the users assigned to a set of events, indexed by event then by user
 *
 * @param  DoliDB $db       Database handler
 * @param  array  $eventIds Event row IDs
 * @return array            [event id => [user id => user infos]]
 */
function reedcrmTodoGetAssignedUsers(DoliDB $db, array $eventIds): array
{
    if (empty($eventIds)) {
        return [];
    }

    $sql  = 'SELECT r.fk_actioncomm, u.rowid, u.firstname, u.lastname, u.photo';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm_resources as r';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'user as u ON u.rowid = r.fk_element';
    $sql .= " WHERE r.element_type = 'user'";
    $sql .= ' AND r.fk_actioncomm IN (' . implode(',', array_map('intval', $eventIds)) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $assigned = [];
    while ($obj = $db->fetch_object($resql)) {
        $assigned[(int) $obj->fk_actioncomm][(int) $obj->rowid] = reedcrmTodoBuildUserInfos($obj);
    }
    $db->free($resql);

    return $assigned;
}

/**
 * Return the display infos of a set of users, indexed by user ID
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $userIds User row IDs
 * @return array           [user id => user infos]
 */
function reedcrmTodoGetUserInfos(DoliDB $db, array $userIds): array
{
    $userIds = array_unique(array_filter(array_map('intval', $userIds)));
    if (empty($userIds)) {
        return [];
    }

    $sql  = 'SELECT u.rowid, u.firstname, u.lastname, u.photo FROM ' . MAIN_DB_PREFIX . 'user as u';
    $sql .= ' WHERE u.rowid IN (' . implode(',', $userIds) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $infos = [];
    while ($obj = $db->fetch_object($resql)) {
        $infos[(int) $obj->rowid] = reedcrmTodoBuildUserInfos($obj);
    }
    $db->free($resql);

    return $infos;
}

/**
 * Return every active internal user, for the owner and assigned users selectors
 *
 * An internal user is one that is not tied to a third party: "Employee" is an optional HR
 * flag a hand made account rarely carries, and filtering on it left real users out of the
 * selectors, hence out of reach of the board.
 *
 * @param  DoliDB $db            Database handler
 * @param  int    $alwaysInclude User to return whatever the criteria, so that the selector
 *                               always holds an option for the one it is filtering on
 * @return array                 User infos, ordered by name
 */
function reedcrmTodoGetSelectableUsers(DoliDB $db, int $alwaysInclude = 0): array
{
    $sql  = 'SELECT u.rowid, u.firstname, u.lastname, u.photo FROM ' . MAIN_DB_PREFIX . 'user as u';
    $sql .= ' WHERE (';
    $sql .= '   (u.statut = 1 AND u.fk_soc IS NULL AND u.entity IN (0, ' . getEntity('user') . '))';
    if ($alwaysInclude > 0) {
        $sql .= ' OR u.rowid = ' . $alwaysInclude;
    }
    $sql .= ' )';
    $sql .= ' ORDER BY u.lastname, u.firstname';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $users = [];
    while ($obj = $db->fetch_object($resql)) {
        $users[] = reedcrmTodoBuildUserInfos($obj);
    }
    $db->free($resql);

    return $users;
}

/**
 * Build the display infos of a user row
 *
 * @param  object $obj Row holding rowid, firstname, lastname and photo
 * @return array       Display infos
 */
function reedcrmTodoBuildUserInfos($obj): array
{
    global $conf;

    $firstname = (string) $obj->firstname;
    $lastname  = (string) $obj->lastname;

    $fullname = trim($firstname . ' ' . $lastname);
    $initials = strtoupper(mb_substr($firstname, 0, 1) . mb_substr($lastname, 0, 1));
    if (empty(trim($initials))) {
        $initials = strtoupper(mb_substr($fullname, 0, 2));
    }

    $photoUrl = '';
    if (!empty($obj->photo)) {
        $photoUrl  = DOL_URL_ROOT . '/viewimage.php?modulepart=userphoto&entity=' . $conf->entity;
        $photoUrl .= '&file=' . urlencode((int) $obj->rowid . '/thumbs/' . preg_replace('/(\.\w+)$/', '_mini$1', $obj->photo));
    }

    return [
        'id'       => (int) $obj->rowid,
        'fullname' => $fullname,
        'initials' => $initials,
        'photo'    => $photoUrl,
    ];
}

/**
 * Return the active types of event, for the criteria selector
 *
 * @param  DoliDB $db Database handler
 * @return array      [type id => translated label]
 */
function reedcrmTodoGetEventTypes(DoliDB $db): array
{
    global $langs;

    $sql  = 'SELECT ca.id, ca.code, ca.libelle FROM ' . MAIN_DB_PREFIX . 'c_actioncomm as ca';
    $sql .= " WHERE ca.active = 1 AND ca.type <> 'systemauto'";
    $sql .= ' ORDER BY ca.position, ca.libelle';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $types = [];
    while ($obj = $db->fetch_object($resql)) {
        $types[(int) $obj->id] = reedcrmTodoGetTypeLabel((string) $obj->code, (string) $obj->libelle);
    }
    $db->free($resql);

    return $types;
}

/**
 * Return the translated label of a type of event
 *
 * Dolibarr names a type of event after its code first (ActionAC_TEL), the label of the
 * dictionary only answers for the types added by hand.
 *
 * @param  string $typeCode  Code of the type of event
 * @param  string $typeLabel Label of the dictionary
 * @return string            Translated label
 */
function reedcrmTodoGetTypeLabel(string $typeCode, string $typeLabel): string
{
    global $langs;

    if (empty($typeCode) && empty($typeLabel)) {
        return '';
    }

    $translated = $langs->trans('Action' . $typeCode);
    if ($translated != 'Action' . $typeCode) {
        return $translated;
    }

    return $langs->trans($typeLabel);
}

/**
 * Return the Font Awesome icon matching a type of event
 *
 * @param  string $typeCode Code of the type of event (AC_TEL, AC_RDV, ...)
 * @return string           Font Awesome class
 */
function reedcrmTodoGetTypeIcon(string $typeCode): string
{
    $icons = [
        'AC_TEL'   => 'fa-phone',
        'AC_FAX'   => 'fa-fax',
        'AC_PROP'  => 'fa-file-invoice',
        'AC_EMAIL' => 'fa-envelope',
        'AC_RDV'   => 'fa-handshake',
        'AC_INT'   => 'fa-wrench',
    ];

    foreach ($icons as $code => $icon) {
        if (strpos($typeCode, $code) === 0) {
            return $icon;
        }
    }

    return 'fa-calendar-alt';
}

/**
 * Tell whether the connected user may change an event of the board
 *
 * @param  ActionComm $event Event to change
 * @param  User       $user  Connected user
 * @return bool              True when the event is editable
 */
function reedcrmTodoCanEditEvent(ActionComm $event, User $user): bool
{
    if ($user->hasRight('agenda', 'allactions', 'create')) {
        return true;
    }
    if (!$user->hasRight('agenda', 'myactions', 'create')) {
        return false;
    }

    if ((int) $event->userownerid === (int) $user->id) {
        return true;
    }

    $event->fetchResources();

    return array_key_exists((int) $user->id, $event->userassigned);
}

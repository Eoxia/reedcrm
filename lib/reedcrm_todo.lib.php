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
 * Read one page of a column, as raw rows
 *
 * Kept apart from the enrichment so that the board can read its six columns first and
 * resolve what their cards display in a single batch, rather than once per column.
 *
 * A status column is read in two passes. The events carrying a start date - all but the
 * relaunches - are read on the module index, which hands them over already ordered on that
 * date and lets the database stop at the page instead of sorting everything the criteria
 * matched. The few carrying none are read apart, on the same index, and ordered on the object
 * they were raised on; merging the two ordered lists gives back exactly the order a single
 * query over the whole cascade would have produced.
 *
 * @param  DoliDB $db        Database handler
 * @param  array  $filters   Criteria from reedcrmTodoGetFilters()
 * @param  array  $column    Column to read
 * @param  array  $columns   Every column of the board
 * @param  string $direction 'asc' (oldest first) or 'desc'
 * @param  int    $offset    Rows to skip
 * @param  int    $limit     Maximum number of events, 0 to use the module configuration
 * @return array             Rows indexed by event ID, in the order they were read
 */
function reedcrmTodoFetchColumnRows(DoliDB $db, array $filters, array $column, array $columns, string $direction = 'asc', int $offset = 0, int $limit = 0): array
{
    if ($limit <= 0) {
        $limit = reedcrmTodoGetPageSize();
    }
    $order = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

    // A relaunch backlog is read on the code of its events, which Dolibarr indexes on its own,
    // and never holds more than the objects still to be relaunched: one pass is enough
    if (!empty($column['code'])) {
        return reedcrmTodoReadColumnRows($db, $filters, $column, $columns, 'all', $order, $offset, $limit);
    }

    // Taking the first page of events from each pass is enough to know the first page of the
    // two put together, whichever pass each of its rows comes from
    $needed  = $offset + $limit;
    $dated   = reedcrmTodoReadColumnRows($db, $filters, $column, $columns, 'dated', $order, 0, $needed);
    $undated = reedcrmTodoReadColumnRows($db, $filters, $column, $columns, 'undated', $order, 0, $needed);

    if (empty($undated)) {
        return array_slice($dated, $offset, $limit, true);
    }

    return reedcrmTodoMergeOnSortDate($dated, $undated, $order, $offset, $limit);
}

/**
 * Read the rows of one pass of a column
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @param  array  $column  Column to read
 * @param  array  $columns Every column of the board
 * @param  string $scope   'dated' for the events carrying a start date, 'undated' for the
 *                         others, 'all' to read a column in one pass
 * @param  string $order   'ASC' or 'DESC'
 * @param  int    $offset  Rows to skip
 * @param  int    $limit   Maximum number of events
 * @return array           Rows indexed by event ID, in the order they were read
 */
function reedcrmTodoReadColumnRows(DoliDB $db, array $filters, array $column, array $columns, string $scope, string $order, int $offset, int $limit): array
{
    // Only the pass that has to fall back on the source object reaches the proposal and the
    // invoice; the dated one already holds the date it is ordered on
    $isDated = $scope === 'dated';

    $sql  = 'SELECT a.id, a.ref, a.label, a.datep, a.datep2, a.datec, a.percent, a.priority, a.location, a.fulldayevent, a.note, a.code,';
    $sql .= ' a.fk_soc, a.fk_project, a.fk_contact, a.fk_user_action, a.fk_element, a.elementtype,';
    $sql .= ' ca.id as type_id, ca.code as type_code, ca.libelle as type_label, ca.color as type_color, ca.picto as type_picto,';
    $sql .= ' s.nom as soc_name, p.ref as project_ref, p.title as project_title,';
    $sql .= ' ' . ($isDated ? 'a.datep' : reedcrmTodoGetSortExpression()) . ' as sort_date';
    $sql .= reedcrmTodoBuildEventsFrom($db, $filters, ['origin' => !$isDated, 'hint' => empty($column['code'])]);
    $sql .= ' AND ' . reedcrmTodoGetColumnSqlCondition($column, $columns);
    if ($scope === 'dated') {
        $sql .= ' AND a.datep IS NOT NULL';
    } elseif ($scope === 'undated') {
        $sql .= ' AND a.datep IS NULL';
    }
    // The dated pass is ordered on the columns of the index, in their order, so the rows come
    // out sorted and the tie between two events sharing a date stays settled
    $sql .= $isDated
        ? ' ORDER BY a.datep ' . $order . ', a.fk_action ' . $order . ', a.id ' . $order
        : ' ORDER BY ' . reedcrmTodoGetSortExpression() . ' ' . $order . ', a.id ' . $order;
    $sql .= $db->plimit($limit, $offset);

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $events = [];
    while ($obj = $db->fetch_object($resql)) {
        $events[(int) $obj->id] = $obj;
    }
    $db->free($resql);

    return $events;
}

/**
 * Merge the two passes of a column back into the one order they share
 *
 * Both lists arrive ordered on the date their cards show, so walking them side by side is
 * enough: no row is ever compared to one it was not already ordered against.
 *
 * @param  array  $dated   Rows of the events carrying a start date
 * @param  array  $undated Rows of the events carrying none
 * @param  string $order   'ASC' or 'DESC'
 * @param  int    $offset  Rows to skip
 * @param  int    $limit   Rows to keep
 * @return array           Rows indexed by event ID, in order
 */
function reedcrmTodoMergeOnSortDate(array $dated, array $undated, string $order, int $offset, int $limit): array
{
    $left    = array_values($dated);
    $right   = array_values($undated);
    $leftNb  = count($left);
    $rightNb = count($right);
    $needed  = $offset + $limit;

    $merged = [];
    $i      = 0;
    $j      = 0;
    while (count($merged) < $needed && ($i < $leftNb || $j < $rightNb)) {
        if ($j >= $rightNb) {
            $merged[] = $left[$i++];
            continue;
        }
        if ($i >= $leftNb) {
            $merged[] = $right[$j++];
            continue;
        }
        // A row without any date at all sorts the way the database sorts a NULL: first when
        // reading from the oldest, last the other way round
        $comparison = strcmp((string) $left[$i]->sort_date, (string) $right[$j]->sort_date);
        $takeLeft   = $order === 'DESC' ? $comparison >= 0 : $comparison <= 0;
        $merged[]   = $takeLeft ? $left[$i++] : $right[$j++];
    }

    $page = [];
    foreach (array_slice($merged, $offset, $limit) as $obj) {
        $page[(int) $obj->id] = $obj;
    }

    return $page;
}

/**
 * Fetch one page of a column, enriched for the Kanban cards
 *
 * @param  DoliDB $db        Database handler
 * @param  array  $filters   Criteria from reedcrmTodoGetFilters()
 * @param  array  $column    Column to read
 * @param  array  $columns   Every column of the board
 * @param  string $direction 'asc' (oldest first) or 'desc'
 * @param  int    $offset    Rows to skip
 * @param  int    $limit     Maximum number of events, 0 to use the module configuration
 * @return array             Enriched events, in the order they were read
 */
function reedcrmTodoGetEvents(DoliDB $db, array $filters, array $column, array $columns, string $direction = 'asc', int $offset = 0, int $limit = 0): array
{
    $events = reedcrmTodoFetchColumnRows($db, $filters, $column, $columns, $direction, $offset, $limit);
    if (empty($events)) {
        return [];
    }

    return reedcrmTodoEnrichEvents($db, $events);
}

/**
 * Fetch the first page of every column of the board at once
 *
 * The six columns are read one query each, then enriched together: owners, assigned users
 * and origins are resolved for the whole board in one batch instead of once per column,
 * which is what keeps the number of queries flat as columns are added.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @param  array  $columns Columns from reedcrmTodoGetKanbanColumns()
 * @return array           [column key => enriched events]
 */
function reedcrmTodoGetColumnPages(DoliDB $db, array $filters, array $columns): array
{
    $rowsByColumn = [];
    $allRows      = [];
    foreach ($columns as $column) {
        $rows                         = reedcrmTodoFetchColumnRows($db, $filters, $column, $columns);
        $rowsByColumn[$column['key']] = array_keys($rows);
        $allRows                     += $rows;
    }

    $cardsById = [];
    foreach (reedcrmTodoEnrichEvents($db, $allRows) as $card) {
        $cardsById[$card['id']] = $card;
    }

    // Each column takes its cards back in the order it read them: a page must never be
    // reordered here, it would no longer follow the one before it
    $pages = [];
    foreach ($rowsByColumn as $columnKey => $eventIds) {
        $pages[$columnKey] = [];
        foreach ($eventIds as $eventId) {
            if (isset($cardsById[$eventId])) {
                $pages[$columnKey][] = $cardsById[$eventId];
            }
        }
    }

    return $pages;
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
 * Return the IDs of the dictionary types Dolibarr flags as logged by itself
 *
 * The "hide the automatic events" criterion used to be written on the type of the joined
 * dictionary row, which forced the database to resolve that join for every candidate event
 * before it could drop any of them - on a base where 97% of the agenda is the automatic log,
 * that is the whole table. The dictionary holds a handful of rows: reading the matching IDs
 * once turns the criterion into a plain test on llx_actioncomm, which an index can serve.
 *
 * @param  DoliDB $db Database handler
 * @return array      Row IDs of the 'systemauto' types
 */
function reedcrmTodoGetAutoTypeIds(DoliDB $db): array
{
    static $autoTypeIds = null;

    if ($autoTypeIds !== null) {
        return $autoTypeIds;
    }

    $autoTypeIds = [];
    $resql       = $db->query('SELECT ca.id FROM ' . MAIN_DB_PREFIX . "c_actioncomm as ca WHERE ca.type = 'systemauto'");
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return $autoTypeIds;
    }
    while ($obj = $db->fetch_object($resql)) {
        $autoTypeIds[] = (int) $obj->id;
    }
    $db->free($resql);

    return $autoTypeIds;
}

/**
 * Return the index hint the columns keyed on a percentage are read through
 *
 * llx_actioncomm carries a one column index on the entity and another on the percentage,
 * and the planner would rather intersect those two than walk the index the module adds -
 * a choice that costs a full sort of everything the criteria matched, since neither of them
 * holds the date the board orders on. Naming the index keeps the read on the first thirty
 * rows it needs.
 *
 * The hint is only emitted once the index has been seen: a base where the module was not
 * activated again after the update simply keeps the plan it had, rather than erroring out.
 *
 * @param  DoliDB $db Database handler
 * @return string     SQL fragment, empty when the index is not there
 */
function reedcrmTodoGetIndexHint(DoliDB $db): string
{
    static $hint = null;

    if ($hint !== null) {
        return $hint;
    }

    $hint  = '';
    $resql = $db->query("SHOW INDEX FROM " . MAIN_DB_PREFIX . "actioncomm WHERE Key_name = 'idx_reedcrm_todo'");
    if ($resql && $db->num_rows($resql) > 0) {
        $hint = ' FORCE INDEX (idx_reedcrm_todo)';
    }
    if ($resql) {
        $db->free($resql);
    }

    return $hint;
}

/**
 * Return the FROM and the WHERE the board runs on, criteria included
 *
 * Shared by the counters and by the paged reading of a column, so that a card is never
 * counted on one set of criteria and read on another. Only the joins the caller answers for
 * are laid out: the counters read no label at all, and the proposal and the invoice are only
 * reached by the two relaunch backlogs, which order their cards on them.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @param  array  $options 'display' to join the label tables, 'origin' the source objects,
 *                         'hint' to name the index of the percentage columns
 * @return string          SQL fragment starting with FROM
 */
function reedcrmTodoBuildEventsFrom(DoliDB $db, array $filters, array $options = []): string
{
    global $user;

    $options += ['display' => true, 'origin' => true, 'hint' => false];
    // The third party is also what a text criterion is matched on, join it either way
    $withSociete = !empty($options['display']) || !empty($filters['search']);

    $sql = ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    if (!empty($options['hint'])) {
        $sql .= reedcrmTodoGetIndexHint($db);
    }
    if (!empty($options['display'])) {
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm as ca ON ca.id = a.fk_action';
    }
    if ($withSociete) {
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = a.fk_soc';
    }
    if (!empty($options['display'])) {
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = a.fk_project';
    }
    // A relaunch carries no start date: it is ordered on the date of the object it was raised
    // on, the very one its note prints
    if (!empty($options['origin'])) {
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "propal as rp ON a.elementtype = 'propal' AND rp.rowid = a.fk_element";
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . "facture as rf ON a.elementtype = 'invoice' AND rf.rowid = a.fk_element";
    }
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
        // Events logged by Dolibarr itself are not something anybody has to do. Written on the
        // type the event points at rather than on the joined dictionary row, so that the whole
        // criterion stays on llx_actioncomm, see reedcrmTodoGetAutoTypeIds()
        $autoTypeIds = reedcrmTodoGetAutoTypeIds($db);
        if (!empty($autoTypeIds)) {
            $sql .= ' AND (a.fk_action IS NULL OR a.fk_action NOT IN (' . implode(', ', $autoTypeIds) . '))';
        }
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
 * It is not only the relaunch backlogs that need the fallback: a relaunch marked done or
 * dropped leaves its backlog for a status column while still carrying no start date, and it
 * has to keep sorting on the object it was raised on rather than land at one end of the
 * column. Reaching that object costs the proposal and the invoice joins, which is why the
 * events that do carry a start date are read without this expression.
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

    // A column standing on a single percentage says so: a range over one value reads the same
    // but costs the index the equality it needs to hand the rows over already sorted on the date
    $sql = (int) $column['min'] === (int) $column['max']
        ? '(a.percent = ' . ((int) $column['min'])
        : '(a.percent >= ' . ((int) $column['min']) . ' AND a.percent <= ' . ((int) $column['max']);

    // The percentage is tested before the code so an event outside the range of a backlog is
    // dropped without ever reading it
    $sql .= reedcrmTodoGetBacklogExclusion($columns);

    return $sql . ')';
}

/**
 * Return the condition leaving the events of the relaunch backlogs to their own column
 *
 * @param  array $columns Every column of the board
 * @return string         SQL condition, empty when no column is keyed on a code
 */
function reedcrmTodoGetBacklogExclusion(array $columns): string
{
    $backlogCodes = [];
    foreach ($columns as $column) {
        if (!empty($column['code'])) {
            $backlogCodes[] = "'" . $column['code'] . "'";
        }
    }

    if (empty($backlogCodes)) {
        return '';
    }

    return ' AND NOT (a.percent >= 0 AND a.percent < 100 AND a.code IN (' . implode(', ', $backlogCodes) . '))';
}

/**
 * Count the events of every column, so a column knows how many cards are left behind its
 * "load more" without reading them
 *
 * Counting has to walk everything the criteria match - there is no page to stop at - so what
 * matters is what each row costs. The percentage columns are counted on their raw ranges,
 * which reads nothing but the columns the module index already holds: the database answers
 * from that index and never opens a row. The relaunch events that would then be counted both
 * in their backlog and in a percentage column are taken back out by a second query, read on
 * the code index, that returns a couple of rows.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @param  array  $columns Columns from reedcrmTodoGetKanbanColumns()
 * @return array           [column key => number of events]
 */
function reedcrmTodoCountByColumn(DoliDB $db, array $filters, array $columns): array
{
    $counts    = [];
    $ranges    = [];
    $keyByCode = [];
    foreach ($columns as $index => $column) {
        $counts[$column['key']] = 0;
        if (!empty($column['code'])) {
            $keyByCode[$column['code']] = $column['key'];
            continue;
        }
        if ($column['min'] === null) {
            continue;
        }
        $ranges[$index] = (int) $column['min'] === (int) $column['max']
            ? 'a.percent = ' . ((int) $column['min'])
            : 'a.percent >= ' . ((int) $column['min']) . ' AND a.percent <= ' . ((int) $column['max']);
    }

    $rangeSelects = [];
    foreach ($ranges as $index => $condition) {
        $rangeSelects[] = 'SUM(' . $condition . ') as col' . ((int) $index);
    }

    // Percentage columns, relaunch events included for now
    if (!empty($rangeSelects)) {
        $sql   = 'SELECT ' . implode(', ', $rangeSelects);
        $sql  .= reedcrmTodoBuildEventsFrom($db, $filters, ['display' => false, 'origin' => false, 'hint' => true]);
        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
            return $counts;
        }
        $obj = $db->fetch_object($resql);
        $db->free($resql);
        if (!empty($obj)) {
            foreach ($ranges as $index => $condition) {
                $alias                           = 'col' . ((int) $index);
                $counts[$columns[$index]['key']] = (int) $obj->$alias;
            }
        }
    }

    if (empty($keyByCode)) {
        return $counts;
    }

    // Relaunch backlogs: their own total, and where their events would otherwise have been
    // counted a second time
    $sql  = 'SELECT a.code as backlog_code, COUNT(*) as nb';
    foreach ($rangeSelects as $rangeSelect) {
        $sql .= ', ' . $rangeSelect;
    }
    $sql .= reedcrmTodoBuildEventsFrom($db, $filters, ['display' => false, 'origin' => false]);
    $sql .= ' AND a.percent >= 0 AND a.percent < 100';
    $sql .= " AND a.code IN ('" . implode("', '", array_keys($keyByCode)) . "')";
    $sql .= ' GROUP BY a.code';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return $counts;
    }
    while ($obj = $db->fetch_object($resql)) {
        $backlogKey = $keyByCode[$obj->backlog_code] ?? '';
        if (empty($backlogKey)) {
            continue;
        }
        $counts[$backlogKey] = (int) $obj->nb;
        foreach ($ranges as $index => $condition) {
            $alias              = 'col' . ((int) $index);
            $columnKey          = $columns[$index]['key'];
            $counts[$columnKey] = max(0, $counts[$columnKey] - (int) $obj->$alias);
        }
    }
    $db->free($resql);

    return $counts;
}

/**
 * Turn rows into the cards the templates render
 *
 * Everything the cards display is resolved in batch (owners, assigned users, source objects),
 * so a whole board costs the same handful of queries as a single column.
 *
 * @param  DoliDB $db     Database handler
 * @param  array  $events Rows read, indexed by event ID
 * @return array          Enriched events
 */
function reedcrmTodoEnrichEvents(DoliDB $db, array $events): array
{
    global $langs;

    if (empty($events)) {
        return [];
    }

    $eventIds = array_keys($events);
    $ownerIds = [];
    foreach ($events as $obj) {
        if ($obj->fk_user_action > 0) {
            $ownerIds[] = (int) $obj->fk_user_action;
        }
    }

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

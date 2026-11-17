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
 * A relaunch is raised without any date and waits in its own backlog: marking it done (100%)
 * or not applicable (-1) takes it out, and so does planning it. A relaunch carrying a date has
 * been taken in hand, it belongs to the column of its percentage like any other event.
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
            if (($event['code'] ?? '') === $column['code'] && $percent >= 0 && $percent < 100
                && empty($event['date_start'])) {
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
 * Close the relaunches whose proposal or invoice no longer waits for anything
 *
 * A relaunch is raised on a proposal still unanswered or an invoice still unpaid. Once the
 * proposal is signed, refused, billed or cancelled, or once the invoice is paid, abandoned or
 * back to draft, there is nobody left to chase: its relaunches are marked done, wherever they
 * stand on the board, instead of waiting for somebody to move them by hand. A relaunch whose
 * object was deleted is closed the same way.
 *
 * The test is the one the cron jobs raise a relaunch on, read on the object as it stands in
 * database: called from the trigger of a payment, it already sees the invoice paid.
 *
 * @param  DoliDB $db          Database handler
 * @param  User   $user        User the relaunches are closed by
 * @param  string $elementType 'propal' or 'invoice', both when empty
 * @param  int    $elementId   Row ID of the proposal or the invoice, all of them when 0
 * @return int                 Number of relaunches closed, < 0 if KO
 */
function reedcrmTodoCloseSettledRelaunches(DoliDB $db, User $user, string $elementType = '', int $elementId = 0): int
{
    // Objects still waiting, the same conditions as ReedcrmTodoCron
    $sources = [
        'propal'  => ['code' => REEDCRM_TODO_CODE_PROPAL_RELAUNCH, 'waiting' => 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'propal WHERE fk_statut = 1'],
        'invoice' => ['code' => REEDCRM_TODO_CODE_INVOICE_RELAUNCH, 'waiting' => 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'facture WHERE fk_statut = 1 AND paye = 0'],
    ];

    $closed = 0;
    foreach ($sources as $type => $source) {
        if ($elementType !== '' && $elementType !== $type) {
            continue;
        }

        $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'actioncomm';
        $sql .= ' SET percent = 100, fk_user_mod = ' . ($user->id > 0 ? (int) $user->id : 'NULL');
        $sql .= " WHERE code = '" . $db->escape($source['code']) . "'";
        $sql .= " AND elementtype = '" . $db->escape($type) . "'";
        $sql .= ' AND entity IN (' . getEntity('agenda') . ')';
        $sql .= ' AND percent >= 0 AND percent < 100';
        if ($elementId > 0) {
            $sql .= ' AND fk_element = ' . $elementId;
        }
        $sql .= ' AND fk_element NOT IN (' . $source['waiting'] . ')';

        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
            return -1;
        }
        $closed += (int) $db->affected_rows($resql);
    }

    return $closed;
}

/**
 * Return the session entry the criteria of the board are kept in
 *
 * Kept per entity: a user switching company must not find the criteria of the other one, the
 * users and the types of event they name belong to the entity they were picked in.
 *
 * @return string Session key
 */
function reedcrmTodoGetFilterSessionKey(): string
{
    global $conf;

    return 'reedcrm_todo_filters_' . (int) $conf->entity;
}

/**
 * Read the criteria of the todo board from the query string
 *
 * The filter bar always posts `filtered`, so an untouched page can be told from a
 * deliberately emptied criterion (the "everybody" user is 0, just like the unset value).
 *
 * A page opened without any criterion falls back on the ones last searched rather than on the
 * default ones: reloading the board, or coming back to it from the menu, finds it as it was
 * left. Only the reset link of the filter bar wipes them, saying so with `removefilter`.
 *
 * @return array Criteria used by reedcrmTodoGetEvents()
 */
function reedcrmTodoGetFilters(): array
{
    global $user;

    // Out of the box the board shows everything the connected user has to do, however old:
    // a lower bound on the start date can only ever hide what he is the most late on
    $defaults = [
        'user'       => (int) $user->id,
        'date_start' => 0,
        'date_end'   => 0,
        'type'        => 0,
        'probability' => '',
        'search'      => '',
        'hide_auto'   => 1,
    ];

    $sessionKey = reedcrmTodoGetFilterSessionKey();

    if (GETPOSTINT('removefilter')) {
        unset($_SESSION[$sessionKey]);

        return $defaults;
    }

    if (!GETPOSTINT('filtered')) {
        return reedcrmTodoNormalizeFilters($_SESSION[$sessionKey] ?? [], $defaults);
    }

    $dateStart = GETPOST('search_date_start', 'alpha');
    $dateEnd   = GETPOST('search_date_end', 'alpha');

    $filters = [
        'user'       => GETPOSTINT('search_user'),
        'date_start' => !empty($dateStart) ? (int) strtotime($dateStart . ' 00:00:00') : 0,
        'date_end'   => !empty($dateEnd) ? (int) strtotime($dateEnd . ' 23:59:59') : 0,
        'type'        => GETPOSTINT('search_type'),
        // Same reading as the numeric search fields of the Dolibarr lists: ">= 50", "< 20", ">= 20 <= 60"
        'probability' => trim(GETPOST('search_probability', 'alpha')),
        'search'      => GETPOST('search_text', 'alphanohtml'),
        'hide_auto'   => GETPOSTINT('search_hide_auto'),
    ];

    $_SESSION[$sessionKey] = $filters;

    return $filters;
}

/**
 * Give back a complete set of criteria from what the session holds
 *
 * The session outlives a release: criteria written by an older version are completed by the
 * default ones instead of leaving the board reading keys that are not there.
 *
 * @param  array $filters  Criteria read back from the session
 * @param  array $defaults Criteria of an untouched board
 * @return array           Complete criteria
 */
function reedcrmTodoNormalizeFilters(array $filters, array $defaults): array
{
    if (empty($filters)) {
        return $defaults;
    }

    $normalized = [];
    foreach ($defaults as $key => $default) {
        $normalized[$key] = array_key_exists($key, $filters) ? $filters[$key] : $default;
    }

    // The searched text and the probability test are the only criteria that are not a number
    $normalized['search']      = (string) $normalized['search'];
    $normalized['probability'] = (string) $normalized['probability'];
    foreach (['user', 'date_start', 'date_end', 'type', 'hide_auto'] as $key) {
        $normalized[$key] = (int) $normalized[$key];
    }

    return $normalized;
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
 * A status column sorted on its date is read in two passes. The events carrying a start date -
 * all but the relaunches - are read on the module index, which hands them over already ordered
 * on that date and lets the database stop at the page instead of sorting everything the
 * criteria matched. The few carrying none are read apart, on the same index, and ordered on the
 * object they were raised on; merging the two ordered lists gives back exactly the order a
 * single query over the whole cascade would have produced.
 *
 * @param  DoliDB $db        Database handler
 * @param  array  $filters   Criteria from reedcrmTodoGetFilters()
 * @param  array  $column    Column to read
 * @param  array  $columns   Every column of the board
 * @param  string $direction 'asc' (oldest first) or 'desc'
 * @param  int    $offset    Rows to skip
 * @param  int    $limit     Maximum number of events, 0 to use the module configuration
 * @param  string $sortKey   'date' for the start date, 'probability' for the one of the opportunity of the project
 * @return array             Rows indexed by event ID, in the order they were read
 */
function reedcrmTodoFetchColumnRows(DoliDB $db, array $filters, array $column, array $columns, string $direction = 'asc', int $offset = 0, int $limit = 0, string $sortKey = 'date'): array
{
    if ($limit <= 0) {
        $limit = reedcrmTodoGetPageSize();
    }
    $order = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

    // A relaunch backlog is read on the code of its events, which Dolibarr indexes on its own,
    // and never holds more than the objects still to be relaunched: one pass is enough. So is a
    // column sorted on the probability, which no index holds: it is sorted in full either way
    if (!empty($column['code']) || $sortKey === 'probability') {
        return reedcrmTodoReadColumnRows($db, $filters, $column, $columns, 'all', $order, $offset, $limit, $sortKey);
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
 * @param  string $sortKey 'date' for the start date, 'probability' for the one of the opportunity of the project
 * @return array           Rows indexed by event ID, in the order they were read
 */
function reedcrmTodoReadColumnRows(DoliDB $db, array $filters, array $column, array $columns, string $scope, string $order, int $offset, int $limit, string $sortKey = 'date'): array
{
    // Only the pass that has to fall back on the source object reaches the proposal and the
    // invoice; the dated one already holds the date it is ordered on
    $isDated = $scope === 'dated';

    $sql  = 'SELECT ' . reedcrmTodoGetSelectFields() . ',';
    $sql .= ' ' . ($isDated ? 'a.datep' : reedcrmTodoGetSortExpression()) . ' as sort_date';
    // The index only pays off on a read ordered on the date, a sort on the probability keeps the plan the database picks
    $sql .= reedcrmTodoBuildEventsFrom($db, $filters, ['origin' => !$isDated, 'hint' => empty($column['code']) && $sortKey !== 'probability']);
    $sql .= ' AND ' . reedcrmTodoGetColumnSqlCondition($column, $columns);
    if ($scope === 'dated') {
        $sql .= ' AND a.datep IS NOT NULL';
    } elseif ($scope === 'undated') {
        $sql .= ' AND a.datep IS NULL';
    }
    if ($sortKey === 'probability') {
        // Events without a probability (no project, or a project followed without opportunity) come last either way,
        // the oldest first among those of a same probability
        $sql .= ' ORDER BY CASE WHEN p.opp_percent IS NULL THEN 1 ELSE 0 END, p.opp_percent ' . $order . ', ' . reedcrmTodoGetSortExpression() . ' ASC, a.id ASC';
    } elseif ($isDated) {
        // The dated pass is ordered on the columns of the index, in their order, so the rows come
        // out sorted. The index carries the ID right after the date, which settles the tie between
        // two events sharing a date the way the single query over the whole cascade did
        $sql .= ' ORDER BY a.datep ' . $order . ', a.id ' . $order;
    } else {
        $sql .= ' ORDER BY ' . reedcrmTodoGetSortExpression() . ' ' . $order . ', a.id ' . $order;
    }
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
 * Columns a card is built from, see reedcrmTodoEnrichEvents()
 *
 * @return string SQL select list, on the joins of reedcrmTodoBuildEventsFrom()
 */
function reedcrmTodoGetSelectFields(): string
{
    $fields  = 'a.id, a.ref, a.label, a.datep, a.datep2, a.datec, a.percent, a.priority, a.location, a.fulldayevent, a.note, a.code,';
    $fields .= ' a.fk_soc, a.fk_project, a.fk_contact, a.fk_user_action, a.fk_element, a.elementtype,';
    $fields .= ' ca.id as type_id, ca.code as type_code, ca.libelle as type_label, ca.color as type_color, ca.picto as type_picto,';
    $fields .= ' s.nom as soc_name, p.ref as project_ref, p.title as project_title';

    return $fields;
}

/**
 * Return the card of a single event, the way the board renders it
 *
 * Read again after a change made on the card, so that what it displays comes from the database.
 *
 * @param  DoliDB $db      Database handler
 * @param  int    $eventId Event row ID
 * @return array           Enriched event, empty when it is not on the board of the user
 */
function reedcrmTodoGetEventCard(DoliDB $db, int $eventId): array
{
    $sql  = 'SELECT ' . reedcrmTodoGetSelectFields();
    $sql .= reedcrmTodoBuildEventsFrom($db, [], ['origin' => false]);
    $sql .= ' AND a.id = ' . $eventId;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }
    $obj = $db->fetch_object($resql);
    $db->free($resql);
    if (!$obj) {
        return [];
    }

    $cards = reedcrmTodoEnrichEvents($db, [(int) $obj->id => $obj]);

    return $cards[0] ?? [];
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
        // reading from the oldest, last the other way round. Two rows sharing a date are told
        // apart on their ID, the second key of both passes
        $comparison = strcmp((string) $left[$i]->sort_date, (string) $right[$j]->sort_date);
        if ($comparison === 0) {
            $comparison = (int) $left[$i]->id <=> (int) $right[$j]->id;
        }
        $takeLeft   = $order === 'DESC' ? $comparison > 0 : $comparison < 0;
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
 * @param  string $sortKey   'date' for the start date, 'probability' for the one of the opportunity of the project
 * @return array             Enriched events, in the order they were read
 */
function reedcrmTodoGetEvents(DoliDB $db, array $filters, array $column, array $columns, string $direction = 'asc', int $offset = 0, int $limit = 0, string $sortKey = 'date'): array
{
    $events = reedcrmTodoFetchColumnRows($db, $filters, $column, $columns, $direction, $offset, $limit, $sortKey);
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
 * An empty column skips its query: hidden automatic events still have to be read through to
 * find none (0.9 s for a user owning 98,000 of them).
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $filters Criteria from reedcrmTodoGetFilters()
 * @param  array  $columns Columns from reedcrmTodoGetKanbanColumns()
 * @param  array  $counts  Number of events of each column, from reedcrmTodoCountByColumn()
 * @return array           [column key => enriched events]
 */
function reedcrmTodoGetColumnPages(DoliDB $db, array $filters, array $columns, array $counts): array
{
    $rowsByColumn = [];
    $allRows      = [];
    foreach ($columns as $column) {
        $rows                         = !empty($counts[$column['key']]) ? reedcrmTodoFetchColumnRows($db, $filters, $column, $columns) : [];
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
    // The third party is also what a text criterion is matched on, and the project what the
    // probability criterion is: join them either way
    $withSociete = !empty($options['display']) || !empty($filters['search']);
    $withProject = !empty($options['display']) || ($filters['probability'] ?? '') !== '';

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
    if ($withProject) {
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
    // "0" is a test of its own, an empty() check would drop it. An event without probability matches no test.
    if (($filters['probability'] ?? '') !== '') {
        $sql .= natural_search('p.opp_percent', $filters['probability'], 1);
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
 * Return the SQL condition an event still waiting in a relaunch backlog matches
 *
 * Mirrors reedcrmTodoGetColumnForEvent(): the cron raises a relaunch without any date, and
 * giving it one is what takes it out of its backlog, just like finishing or dropping it.
 *
 * @return string SQL condition, on the alias of the events table
 */
function reedcrmTodoGetBacklogCondition(): string
{
    return 'a.percent >= 0 AND a.percent < 100 AND a.datep IS NULL';
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
        return "(a.code = '" . $column['code'] . "' AND " . reedcrmTodoGetBacklogCondition() . ')';
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

    return ' AND NOT (' . reedcrmTodoGetBacklogCondition() . ' AND a.code IN (' . implode(', ', $backlogCodes) . '))';
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
    $sql .= ' AND ' . reedcrmTodoGetBacklogCondition();
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

    // Budget, probability and relaunches of the projects the events are linked to
    $projectInfos = reedcrmTodoGetProjectInfos($db, $events);

    // Names of the contacts the events are linked to
    $contactNames = reedcrmTodoGetContactNames($db, $events);

    // Beginning of the summary of the Pocket recordings the events are the calls of
    $pocketSummaries = reedcrmTodoGetPocketSummaries($db, $eventIds);

    $now       = dol_now();
    // An event planned for a later day is still to come. Today is the pivot the two badges
    // leave alone: an event of the day is neither late until its hour has passed, nor to come.
    $today     = dol_getdate($now);
    $todayEnd  = dol_mktime(23, 59, 59, $today['mon'], $today['mday'], $today['year']);
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

        // An event is late once its deadline is past: its end date when it has one, its start date otherwise
        $dueTs = $obj->datep2 ? (int) $db->jdate($obj->datep2) : ($obj->datep ? (int) $db->jdate($obj->datep) : 0);

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
            'late'           => ($dueTs && $dueTs < $now && $percent >= 0 && $percent < 100) ? 1 : 0,
            'upcoming'       => ($obj->datep && $db->jdate($obj->datep) > $todayEnd && $percent >= 0 && $percent < 100) ? 1 : 0,
            'type_id'        => (int) $obj->type_id,
            'type_code'      => $obj->type_code,
            'type_label'     => reedcrmTodoGetTypeLabel((string) $obj->type_code, (string) $obj->type_label),
            'type_color'     => !empty($obj->type_color) ? '#' . ltrim($obj->type_color, '#') : '',
            'type_picto'     => reedcrmTodoGetTypeIcon((string) $obj->type_code),
            'soc_id'         => (int) $obj->fk_soc,
            'soc_name'       => $obj->soc_name,
            'contact_id'     => (int) $obj->fk_contact,
            'contact_name'   => $contactNames[(int) $obj->fk_contact] ?? '',
            'project_id'     => (int) $obj->fk_project,
            'project_ref'    => $obj->project_ref,
            'project_title'  => $obj->project_title,
            'project_infos'  => $projectInfos[(int) $obj->fk_project] ?? [],
            'pocket_summary' => $pocketSummaries[$eventId] ?? [],
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
 * Each source also carries the third party and the project it belongs to, which is what its
 * number of relaunches is read on, see reedcrmTodoCountRelaunches().
 *
 * Its amount excluding tax comes along, rounded to an order of magnitude on the chip and exact
 * in its tooltip: the card tells at a glance how much a relaunch is worth.
 *
 * So do its lines, for the tooltip of its reference, see reedcrmTodoGetOriginLines().
 *
 * @param  DoliDB $db     Database handler
 * @param  array  $events Rows of the board, indexed by event ID
 * @return array          [event id => ['ref' => , 'url' => , 'type' => , 'date_ts' => , 'date_fmt' => , 'date_title' => , 'amount_ht' => , 'amount_ht_short' => , 'amount_ht_full' => , 'soc_id' => , 'project' => , 'lines' => , 'lines_more' => , 'relaunch_count' => ]]
 */
function reedcrmTodoGetOriginInfos(DoliDB $db, array $events): array
{
    global $conf, $langs, $user;

    $sources = [
        'propal'  => ['table' => 'propal',  'url' => '/comm/propal/card.php?id=', 'date' => 'COALESCE(date_valid, datep)', 'date_label' => 'TodoOriginDatePropal', 'line_table' => 'propaldet', 'line_parent' => 'fk_propal', 'right' => 'propal'],
        'invoice' => ['table' => 'facture', 'url' => '/compta/facture/card.php?id=', 'date' => 'COALESCE(date_lim_reglement, datef)', 'date_label' => 'TodoOriginDateInvoice', 'line_table' => 'facturedet', 'line_parent' => 'fk_facture', 'right' => 'facture'],
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
        // What the object is made of is only told to whoever may open it
        $linesByObject = [];
        if ($user->hasRight($sources[$elementType]['right'], 'lire')) {
            $linesByObject = reedcrmTodoGetOriginLines($db, $sources[$elementType]['line_table'], $sources[$elementType]['line_parent'], array_keys($elementIds));
        }

        $sql   = 'SELECT rowid, ref, fk_soc, fk_projet, total_ht, ' . $sources[$elementType]['date'] . ' as date_reference';
        $sql  .= ' FROM ' . MAIN_DB_PREFIX . $sources[$elementType]['table'];
        $sql  .= ' WHERE rowid IN (' . implode(',', array_keys($elementIds)) . ')';
        $resql = $db->query($sql);
        if (!$resql) {
            dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
            continue;
        }
        while ($obj = $db->fetch_object($resql)) {
            $dateTs   = $obj->date_reference ? (int) $db->jdate($obj->date_reference) : 0;
            $amountHt = (float) $obj->total_ht;
            foreach ($elementIds[(int) $obj->rowid] as $eventId) {
                $origins[$eventId] = [
                    'type'            => $elementType,
                    'ref'             => $obj->ref,
                    'url'             => DOL_URL_ROOT . $sources[$elementType]['url'] . (int) $obj->rowid,
                    'date_ts'         => $dateTs,
                    'date_fmt'        => $dateTs ? dol_print_date($dateTs, 'day') : '',
                    'date_title'      => $dateTs ? $langs->trans($sources[$elementType]['date_label'], dol_print_date($dateTs, 'day')) : '',
                    'amount_ht'       => $amountHt,
                    'amount_ht_short' => reedcrmTodoFormatAmountMagnitude($amountHt),
                    'amount_ht_full'  => $langs->trans('TodoOriginAmountHT', price($amountHt, 0, $langs, 1, -1, -1, $conf->currency)),
                    'soc_id'          => (int) $obj->fk_soc,
                    'project'         => (int) $obj->fk_projet,
                    'lines'           => $linesByObject[(int) $obj->rowid]['lines'] ?? [],
                    'lines_more'      => $linesByObject[(int) $obj->rowid]['more'] ?? 0,
                ];
            }
        }
        $db->free($resql);
    }

    // How many relaunches each object has already been through, read in one go for the board
    $relaunchCounts = reedcrmTodoCountRelaunches($db, $origins);
    foreach ($origins as $eventId => $origin) {
        $origins[$eventId]['relaunch_count'] = (int) ($relaunchCounts[$eventId] ?? 0);
    }

    return $origins;
}

/**
 * Return the lines of the proposals or the invoices the relaunch events were raised on
 *
 * They fill the tooltip of the reference on the card, the way the risk of a task does on the
 * action plan of Digirisk: what the object is made of, a label and an amount per line, without
 * leaving the board. Title and subtotal lines (product type 9) carry no amount of their own and
 * are left out.
 *
 * A line is labelled with its own label, then with the one of its product, then with its
 * description: a free line only carries the latter.
 *
 * Only the first lines of an object are kept, the tooltip tells how many more there are.
 *
 * @param  DoliDB $db         Database handler
 * @param  string $table      Table of the lines, without prefix
 * @param  string $parentKey  Column of the lines pointing to their object
 * @param  array  $objectIds  IDs of the objects
 * @return array              [object id => ['lines' => [['label' => , 'amount' => ]], 'more' => ]]
 */
function reedcrmTodoGetOriginLines(DoliDB $db, string $table, string $parentKey, array $objectIds): array
{
    global $conf, $langs;

    $maxLines = 10;

    $sql  = 'SELECT d.' . $parentKey . ' as fk_object, d.label, d.description, d.total_ht, p.ref as product_ref, p.label as product_label';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . $table . ' as d';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = d.fk_product';
    $sql .= ' WHERE d.' . $parentKey . ' IN (' . implode(',', $objectIds) . ')';
    $sql .= ' AND d.product_type <> 9';
    $sql .= ' ORDER BY d.' . $parentKey . ', d.rang, d.rowid';
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $linesByObject = [];
    while ($obj = $db->fetch_object($resql)) {
        $objectId = (int) $obj->fk_object;
        if (!isset($linesByObject[$objectId])) {
            $linesByObject[$objectId] = ['lines' => [], 'more' => 0];
        }
        if (count($linesByObject[$objectId]['lines']) >= $maxLines) {
            $linesByObject[$objectId]['more']++;
            continue;
        }

        $label = '';
        foreach ([$obj->label, $obj->product_label, $obj->description, $obj->product_ref] as $candidate) {
            $label = trim(dol_string_nohtmltag((string) $candidate));
            if ($label !== '') {
                break;
            }
        }

        $linesByObject[$objectId]['lines'][] = [
            'label'  => dol_trunc($label, 90),
            'amount' => price((float) $obj->total_ht, 0, $langs, 1, -1, -1, $conf->currency),
        ];
    }
    $db->free($resql);

    return $linesByObject;
}

/**
 * Return an amount rounded to its order of magnitude, the way the chip of a card shows it
 *
 * Below a thousand the amount keeps its units, above it reads in thousands then in millions,
 * with one decimal as long as the leading figure is a single digit: 280 €, 3,4 k€, 34 k€, 1,2 M€.
 *
 * @param  float  $amount Amount to round
 * @return string         Rounded amount followed by the currency symbol
 */
function reedcrmTodoFormatAmountMagnitude(float $amount): string
{
    global $conf, $langs;

    $symbol  = $langs->getCurrencySymbol($conf->currency);
    $decimal = $langs->transnoentitiesnoconv('SeparatorDecimal');
    $abs     = abs($amount);

    if ($abs < 1000) {
        return price(round($amount), 0, $langs, 1, 0, 0) . ' ' . $symbol;
    }

    $unit     = $abs < 1000000 ? 'k' : 'M';
    $scaled   = $amount / ($abs < 1000000 ? 1000 : 1000000);
    // Rounded first, so that 9 990 € reads 10 k€ and not 10,0 k€
    $decimals = abs(round($scaled, 1)) < 10 ? 1 : 0;

    return number_format($scaled, $decimals, $decimal, ' ') . ' ' . $unit . $symbol;
}

/**
 * Return how many relaunches each origin object has already been through, indexed by event ID
 *
 * A relaunch is what the rest of the module calls one: an event tagged with the commercial
 * relaunch category, read on the third party and the project of the object, exactly the rule
 * the relaunch counters of the project list and of the mobile opportunity view apply
 * (ActionComm::getActions() with the same filter). An object without a project falls back on
 * every relaunch of its third party, the way those counters do.
 *
 * The whole board is counted in a single query: the events are grouped by third party and
 * project, then each object reads the group it belongs to.
 *
 * @param  DoliDB $db      Database handler
 * @param  array  $origins Origins resolved by reedcrmTodoGetOriginInfos(), indexed by event ID
 * @return array           [event id => number of relaunches]
 */
function reedcrmTodoCountRelaunches(DoliDB $db, array $origins): array
{
    $relaunchTag = getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG');
    if ($relaunchTag <= 0 || empty($origins)) {
        return [];
    }

    // A relaunch is only ever read on a third party, the filter of the counters carries it
    $socIds = [];
    foreach ($origins as $origin) {
        if (!empty($origin['soc_id'])) {
            $socIds[(int) $origin['soc_id']] = (int) $origin['soc_id'];
        }
    }
    if (empty($socIds)) {
        return [];
    }

    $sql  = 'SELECT a.fk_soc, a.fk_project, COUNT(a.id) as nb';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    $sql .= ' AND a.fk_soc IN (' . implode(',', $socIds) . ')';
    $sql .= ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $relaunchTag . ')';
    $sql .= ' GROUP BY a.fk_soc, a.fk_project';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $byProject = [];
    $bySoc     = [];
    while ($obj = $db->fetch_object($resql)) {
        $socId = (int) $obj->fk_soc;
        $byProject[$socId][(int) $obj->fk_project] = (int) $obj->nb;
        $bySoc[$socId]                             = ($bySoc[$socId] ?? 0) + (int) $obj->nb;
    }
    $db->free($resql);

    $counts = [];
    foreach ($origins as $eventId => $origin) {
        $socId = (int) ($origin['soc_id'] ?? 0);
        if (empty($socId)) {
            continue;
        }
        if (!empty($origin['project'])) {
            $counts[$eventId] = (int) ($byProject[$socId][(int) $origin['project']] ?? 0);
        } else {
            $counts[$eventId] = (int) ($bySoc[$socId] ?? 0);
        }
    }

    return $counts;
}

/**
 * Return what the cards tell about the projects of their events: budget, probability and relaunches
 *
 * The amount is the budget of the project once it has one (a won opportunity gets it), its
 * opportunity amount otherwise, rounded like the amount of a proposal on its chip and exact in its
 * tooltip. The probability is the one of the opportunity, none for a project followed without one.
 *
 * Its relaunches are counted the way the project list does (ActionComm::getActions() on the
 * project): the events tagged as commercial relaunches linked to the project, restricted to its
 * third party when it has one.
 *
 * The tooltip of the project badge tells the rest of what a salesman looks for on the project card:
 * its status, the stage of the opportunity, the third party, the contact the opportunity came with,
 * its dates, its origin and the beginning of its description.
 *
 * Only told to whoever may read projects. The whole board takes two queries, one for the projects
 * and one for their relaunches.
 *
 * @param  DoliDB $db     Database handler
 * @param  array  $events Rows of the board, indexed by event ID
 * @return array          [project id => ['amount_short' => , 'amount_full' => , 'percent' => , 'relaunch_count' => , 'status' => , 'details' => , 'description' => ]]
 */
function reedcrmTodoGetProjectInfos(DoliDB $db, array $events): array
{
    global $conf, $langs, $user;

    $projectIds = [];
    foreach ($events as $obj) {
        if ($obj->fk_project > 0) {
            $projectIds[(int) $obj->fk_project] = (int) $obj->fk_project;
        }
    }
    if (empty($projectIds) || !$user->hasRight('projet', 'lire')) {
        return [];
    }

    $sql   = 'SELECT p.rowid, p.fk_soc, p.fk_statut, p.dateo, p.datee, p.description, p.budget_amount, p.opp_amount, p.opp_percent,';
    $sql  .= ' s.nom as soc_name, ls.code as opp_status_code, ls.label as opp_status_label,';
    $sql  .= ' pe.reedcrm_firstname, pe.reedcrm_lastname, pe.projectphone, pe.reedcrm_email, ir.code as origin_code, ir.label as origin_label';
    $sql  .= ' FROM ' . MAIN_DB_PREFIX . 'projet as p';
    $sql  .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = p.fk_soc';
    $sql  .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_lead_status as ls ON ls.rowid = p.fk_opp_status';
    $sql  .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_extrafields as pe ON pe.fk_object = p.rowid';
    $sql  .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_input_reason as ir ON ir.rowid = pe.opporigin';
    $sql  .= ' WHERE p.rowid IN (' . implode(',', $projectIds) . ')';
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    // Same labels and colours as the status of the project card
    $statusLabels  = [0 => 'Draft', 1 => 'Opened', 2 => 'Closed'];
    $statusClasses = [0 => 'status0', 1 => 'status4', 2 => 'status6'];

    $infos      = [];
    $projectSoc = [];
    while ($obj = $db->fetch_object($resql)) {
        $projectId = (int) $obj->rowid;
        $budget    = (float) $obj->budget_amount;
        $amount    = $budget > 0 ? $budget : (float) $obj->opp_amount;
        $percent   = ($obj->opp_percent !== null && $obj->opp_percent !== '') ? (int) round((float) $obj->opp_percent) : null;
        $status    = (int) $obj->fk_statut;

        // Lines of the tooltip, left out when the project has nothing to tell
        $details = [];
        if (!empty($obj->opp_status_code)) {
            $stepKey   = 'OppStatus' . $obj->opp_status_code;
            $details[] = ['label' => $langs->transnoentities('TodoProjectStep'), 'value' => $langs->transnoentities($stepKey) !== $stepKey ? $langs->transnoentities($stepKey) : $obj->opp_status_label];
        }
        if ($amount > 0) {
            $details[] = ['label' => $langs->transnoentities($budget > 0 ? 'Budget' : 'OpportunityAmount'), 'value' => price($amount, 0, $langs, 1, -1, -1, $conf->currency)];
        }
        if ($percent !== null) {
            $details[] = ['label' => $langs->transnoentities('OpportunityProbability'), 'value' => $percent . '%'];
        }
        if (!empty($obj->soc_name)) {
            $details[] = ['label' => $langs->transnoentities('TodoProjectThirdParty'), 'value' => $obj->soc_name];
        }
        $contactName = trim($obj->reedcrm_firstname . ' ' . $obj->reedcrm_lastname);
        if ($contactName !== '') {
            $details[] = ['label' => $langs->transnoentities('TodoProjectContact'), 'value' => $contactName];
        }
        if (!empty($obj->projectphone)) {
            $details[] = ['label' => $langs->transnoentities('TodoProjectPhone'), 'value' => $obj->projectphone];
        }
        if (!empty($obj->reedcrm_email)) {
            $details[] = ['label' => $langs->transnoentities('TodoProjectEmail'), 'value' => $obj->reedcrm_email];
        }
        if ($obj->dateo) {
            $details[] = ['label' => $langs->transnoentities('DateStart'), 'value' => dol_print_date($db->jdate($obj->dateo), 'day')];
        }
        if ($obj->datee) {
            $details[] = ['label' => $langs->transnoentities('DateEnd'), 'value' => dol_print_date($db->jdate($obj->datee), 'day')];
        }
        if (!empty($obj->origin_code)) {
            $originKey = 'DemandReasonType' . $obj->origin_code;
            $details[] = ['label' => $langs->transnoentities('TodoProjectOrigin'), 'value' => $langs->transnoentities($originKey) !== $originKey ? $langs->transnoentities($originKey) : $obj->origin_label];
        }

        $projectSoc[$projectId] = (int) $obj->fk_soc;
        $infos[$projectId]      = [
            'amount_short'   => $amount > 0 ? reedcrmTodoFormatAmountMagnitude($amount) : '',
            'amount_full'    => $amount > 0 ? $langs->trans($budget > 0 ? 'TodoProjectBudget' : 'TodoProjectOpportunityAmount', price($amount, 0, $langs, 1, -1, -1, $conf->currency)) : '',
            'percent'        => $percent,
            'relaunch_count' => 0,
            'status'         => isset($statusLabels[$status]) ? dolGetStatus($langs->transnoentitiesnoconv($statusLabels[$status]), '', '', $statusClasses[$status], 5, '', ['tooltip' => 'no']) : '',
            'details'        => $details,
            'description'    => dol_trunc(dol_string_nohtmltag((string) $obj->description), 200),
        ];
    }
    $db->free($resql);

    $relaunchTag = getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG');
    if ($relaunchTag <= 0 || empty($infos)) {
        return $infos;
    }

    $sql  = 'SELECT a.fk_project, a.fk_soc, COUNT(a.id) as nb';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' WHERE a.entity IN (' . getEntity('agenda') . ')';
    $sql .= ' AND a.fk_project IN (' . implode(',', array_keys($infos)) . ')';
    $sql .= ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $relaunchTag . ')';
    $sql .= ' GROUP BY a.fk_project, a.fk_soc';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return $infos;
    }
    while ($obj = $db->fetch_object($resql)) {
        $projectId = (int) $obj->fk_project;
        if (empty($projectSoc[$projectId]) || $projectSoc[$projectId] === (int) $obj->fk_soc) {
            $infos[$projectId]['relaunch_count'] += (int) $obj->nb;
        }
    }
    $db->free($resql);

    return $infos;
}

/**
 * Return the names of the contacts a set of events are linked to
 *
 * @param  DoliDB $db     Database handler
 * @param  array  $events Rows of the board, indexed by event ID
 * @return array          [contact id => full name]
 */
function reedcrmTodoGetContactNames(DoliDB $db, array $events): array
{
    global $user;

    $contactIds = [];
    foreach ($events as $obj) {
        if ($obj->fk_contact > 0) {
            $contactIds[(int) $obj->fk_contact] = (int) $obj->fk_contact;
        }
    }
    if (empty($contactIds) || !$user->hasRight('societe', 'contact', 'lire')) {
        return [];
    }

    $sql   = 'SELECT sp.rowid, sp.firstname, sp.lastname FROM ' . MAIN_DB_PREFIX . 'socpeople as sp';
    $sql  .= ' WHERE sp.rowid IN (' . implode(',', $contactIds) . ')';
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $names = [];
    while ($obj = $db->fetch_object($resql)) {
        $names[(int) $obj->rowid] = dolGetFirstLastname($obj->firstname, $obj->lastname);
    }
    $db->free($resql);

    return $names;
}

/**
 * Return the beginning of the summary of the Pocket recordings a set of events are the calls of
 *
 * Read on the recording, not copied on the event: the summary is written by Pocket once the audio is
 * processed and may be rewritten on the recording card afterwards, the card always shows the current one.
 *
 * @param  DoliDB $db       Database handler
 * @param  array  $eventIds Event row IDs
 * @return array            [event id => ['ref' => , 'excerpt' => ]]
 */
function reedcrmTodoGetPocketSummaries(DoliDB $db, array $eventIds): array
{
    if (empty($eventIds)) {
        return [];
    }

    require_once __DIR__ . '/reedcrm_pocketrecording.lib.php';

    $sql  = 'SELECT r.fk_actioncomm, r.ref, r.summary';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_pocket_recording as r';
    $sql .= ' WHERE r.fk_actioncomm IN (' . implode(',', array_map('intval', $eventIds)) . ')';
    $sql .= " AND r.summary IS NOT NULL AND r.summary <> ''";

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $summaries = [];
    while ($obj = $db->fetch_object($resql)) {
        $excerpt = reedcrm_pocket_summary_excerpt((string) $obj->summary);
        if ($excerpt !== '') {
            $summaries[(int) $obj->fk_actioncomm] = ['ref' => $obj->ref, 'excerpt' => $excerpt];
        }
    }
    $db->free($resql);

    return $summaries;
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

/**
 * Search the third parties, contacts or projects an event of the board may be linked to
 *
 * Contacts and projects are filtered on the third party of the event, the way the selectors of
 * the event card are: a contact belongs to it, a project belongs to it or to nobody. Each kind
 * only lists what the user may read. Asked for a single ID, it tells whether that one qualifies.
 *
 * @param  DoliDB $db       Database handler
 * @param  string $type     'thirdparty', 'contact' or 'project'
 * @param  string $search   Text typed in the selector
 * @param  int    $socId    Third party of the event, 0 for none
 * @param  int    $targetId Only this row, to check a value before saving it
 * @return array            [['id' => , 'text' => , 'soc_id' => ]]
 */
function reedcrmTodoSearchLinkTargets(DoliDB $db, string $type, string $search = '', int $socId = 0, int $targetId = 0): array
{
    global $user;

    // A sales representative only sees the third parties they follow
    $salesRepFilter = '';
    if (!$user->hasRight('societe', 'client', 'voir')) {
        $salesRepFilter = 'SELECT sc.fk_soc FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux as sc WHERE sc.fk_user = ' . ((int) $user->id);
    }

    if ($type == 'thirdparty') {
        if (!$user->hasRight('societe', 'lire')) {
            return [];
        }
        $sql  = 'SELECT s.rowid, s.nom as name, s.name_alias, s.rowid as soc_id FROM ' . MAIN_DB_PREFIX . 'societe as s';
        $sql .= ' WHERE s.entity IN (' . getEntity('societe') . ') AND s.status = 1';
        if ($salesRepFilter) {
            $sql .= ' AND s.rowid IN (' . $salesRepFilter . ')';
        }
        if ($user->socid > 0) {
            $sql .= ' AND s.rowid = ' . ((int) $user->socid);
        }
        $idField      = 's.rowid';
        $searchFields = ['s.nom', 's.name_alias'];
        $orderBy      = 's.nom';
    } elseif ($type == 'contact') {
        if (!$user->hasRight('societe', 'contact', 'lire')) {
            return [];
        }
        $sql  = 'SELECT sp.rowid, sp.firstname, sp.lastname, sp.fk_soc as soc_id, s.nom as soc_name FROM ' . MAIN_DB_PREFIX . 'socpeople as sp';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = sp.fk_soc';
        $sql .= ' WHERE sp.entity IN (' . getEntity('contact') . ') AND sp.statut = 1';
        $sql .= ' AND (sp.priv = 0 OR sp.fk_user_creat = ' . ((int) $user->id) . ')';
        if ($socId > 0) {
            $sql .= ' AND sp.fk_soc = ' . $socId;
        }
        if ($salesRepFilter) {
            $sql .= ' AND (sp.fk_soc IS NULL OR sp.fk_soc IN (' . $salesRepFilter . '))';
        }
        if ($user->socid > 0) {
            $sql .= ' AND sp.fk_soc = ' . ((int) $user->socid);
        }
        $idField      = 'sp.rowid';
        $searchFields = ['sp.lastname', 'sp.firstname', 'sp.email'];
        $orderBy      = 'sp.lastname, sp.firstname';
    } elseif ($type == 'project') {
        if (!$user->hasRight('projet', 'lire')) {
            return [];
        }
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

        $sql  = 'SELECT p.rowid, p.ref, p.title, p.fk_soc as soc_id FROM ' . MAIN_DB_PREFIX . 'projet as p';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = p.fk_soc';
        // A closed project takes no more events
        $sql .= ' WHERE p.entity IN (' . getEntity('project') . ') AND p.fk_statut <> ' . Project::STATUS_CLOSED;
        if (!$user->hasRight('projet', 'all', 'lire')) {
            $projectStatic = new Project($db);
            $projectIds    = $projectStatic->getProjectsAuthorizedForUser($user, 0, 1);
            $sql          .= ' AND p.rowid IN (' . ($projectIds ?: '0') . ')';
        }
        if ($socId > 0 && getDolGlobalString('PROJECT_ALLOW_TO_LINK_FROM_OTHER_COMPANY') != 'all') {
            $sql .= ' AND (p.fk_soc = ' . $socId . ' OR p.fk_soc IS NULL)';
        }
        $idField      = 'p.rowid';
        $searchFields = ['p.ref', 'p.title', 's.nom'];
        $orderBy      = 'p.ref DESC';
    } else {
        return [];
    }

    if ($targetId > 0) {
        $sql .= ' AND ' . $idField . ' = ' . $targetId;
    }
    if ($search !== '') {
        $sql .= natural_search($searchFields, $search);
    }
    $sql .= ' ORDER BY ' . $orderBy;
    $sql .= $db->plimit(20);

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $targets = [];
    while ($obj = $db->fetch_object($resql)) {
        if ($type == 'thirdparty') {
            $text = $obj->name . (!empty($obj->name_alias) ? ' (' . $obj->name_alias . ')' : '');
        } elseif ($type == 'contact') {
            // Without a third party on the event, the company tells two namesakes apart
            $text = dolGetFirstLastname($obj->firstname, $obj->lastname) . ($socId <= 0 && !empty($obj->soc_name) ? ' - ' . $obj->soc_name : '');
        } else {
            $text = $obj->ref . ' - ' . $obj->title;
        }
        $targets[] = ['id' => (int) $obj->rowid, 'text' => $text, 'soc_id' => (int) $obj->soc_id];
    }
    $db->free($resql);

    return $targets;
}

/**
 * Drop the contact and the project of an event that no longer belong to its third party
 *
 * Same rule as the search of reedcrmTodoSearchLinkTargets(): a contact belongs to the third party
 * of the event, a project belongs to it or to nobody.
 *
 * @param  DoliDB     $db    Database handler
 * @param  ActionComm $event Event about to be saved, resources loaded
 * @return void
 */
function reedcrmTodoAlignEventLinks(DoliDB $db, ActionComm $event)
{
    $socId = (int) $event->socid;

    if ($event->contact_id > 0) {
        $resql   = $db->query('SELECT fk_soc FROM ' . MAIN_DB_PREFIX . 'socpeople WHERE rowid = ' . ((int) $event->contact_id));
        $contact = $resql ? $db->fetch_object($resql) : null;
        if ($contact && (int) $contact->fk_soc !== $socId) {
            unset($event->socpeopleassigned[(int) $event->contact_id]);
            $event->contact_id = 0;
        }
    }

    if ($event->fk_project > 0 && $socId > 0 && getDolGlobalString('PROJECT_ALLOW_TO_LINK_FROM_OTHER_COMPANY') != 'all') {
        $resql   = $db->query('SELECT fk_soc FROM ' . MAIN_DB_PREFIX . 'projet WHERE rowid = ' . ((int) $event->fk_project));
        $project = $resql ? $db->fetch_object($resql) : null;
        if ($project && $project->fk_soc > 0 && (int) $project->fk_soc !== $socId) {
            $event->fk_project = 0;
        }
    }
}

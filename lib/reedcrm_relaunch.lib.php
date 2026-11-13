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
 * \file    lib/reedcrm_relaunch.lib.php
 * \ingroup reedcrm
 * \brief   Single source of truth for the commercial relaunch widget.
 *
 *          Before this file the same four per-type pills were rebuilt in four places (the saturne
 *          list helper, the card footer hook, the legacy project list hook and the App bar) and the
 *          bucketing was re-implemented three times. Everything that answers "how many relaunches
 *          does this project have and in what state" now lives here, so the surfaces only choose a
 *          chrome.
 *
 *          Buckets follow the #873 mockup: the left button holds the PAST, the right one the events
 *          still TO DO in the future, and overdue items raise a warning on the left button instead
 *          of moving anywhere.
 */

require_once __DIR__ . '/reedcrm_function.lib.php';

/**
 * Categories that make an agenda event a relaunch.
 *
 * Relaunches and reminders are deliberately tagged apart (a reminder must not inflate the relaunch
 * count), so every read has to union both or the "upcoming" button is structurally empty: reminders
 * are the only events this module ever creates with a future date and a to-do status.
 *
 * @return int[] Category ids, empty when the module was never configured
 */
function reedcrm_get_relaunch_category_ids(): array
{
    $ids = [
        getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG'),
        getDolGlobalInt('REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG'),
    ];

    return array_values(array_filter($ids, static function ($id) {
        return $id > 0;
    }));
}

/**
 * Delay after which a late relaunch stops being amber and turns red.
 *
 * Defaults to the native agenda threshold (MAIN_DELAY_ACTIONS_TODO, in days) so the widget agrees
 * with what Dolibarr already paints elsewhere, and stays overridable per install.
 *
 * @return int Delay in seconds
 */
function reedcrm_get_relaunch_late_delay(): int
{
    $days = getDolGlobalInt('REEDCRM_RELAUNCH_LATE_DELAY_DAYS');
    if ($days <= 0) {
        $days = getDolGlobalInt('MAIN_DELAY_ACTIONS_TODO', 7);
    }

    return $days * 24 * 3600;
}

/**
 * SQL restriction selecting the relaunch events of one project (or one thirdparty).
 *
 * $socid is only used when there is no project: on the saturne list the fetched row carries no
 * socid at all, and 4 of the tagged events of this base have a NULL fk_soc while their project has
 * one - filtering on both would silently hide them. Do not "repair" this.
 *
 * @param  DoliDB $db        Database handler
 * @param  int    $projectId Project id, 0 when rendering on a thirdparty or proposal card
 * @param  int    $socid     Thirdparty id, used only when $projectId is 0
 * @return string            SQL fragment, empty when nothing can be scoped
 */
function reedcrm_get_relaunch_sql_filter(DoliDB $db, int $projectId, int $socid = 0): string
{
    $categoryIds = reedcrm_get_relaunch_category_ids();
    if (empty($categoryIds)) {
        return '';
    }

    if ($projectId > 0) {
        $scope = ' AND a.fk_project = ' . $projectId;
    } elseif ($socid > 0) {
        $scope = ' AND a.fk_soc = ' . $socid;
    } else {
        return '';
    }

    $sql = ' AND a.entity IN (' . getEntity('agenda') . ')';
    $sql .= $scope;
    $sql .= ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c';
    $sql .= ' WHERE c.fk_categorie IN (' . implode(',', $categoryIds) . '))';

    return $sql;
}

/**
 * Counters behind the two buttons, in a single aggregated query.
 *
 * The previous implementation ran ActionComm::getActions() per row, which fetches every matching
 * event in full (1 + 2N queries) only to increment four integers. Here nothing leaves the database
 * but the four numbers and the two days printed under them, and the result is memoised for the page.
 *
 * @param  DoliDB $db        Database handler
 * @param  int    $projectId Project id
 * @param  int    $socid     Thirdparty id, used only when $projectId is 0
 * @return array{past: int, upcoming: int, overdue: int, late: int, last: int, next: int} last and next are timestamps, 0 when there is none
 */
function reedcrm_get_relaunch_counts(DoliDB $db, int $projectId, int $socid = 0): array
{
    static $cache = [];

    $cacheKey = $projectId . '-' . $socid;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $empty = ['past' => 0, 'upcoming' => 0, 'overdue' => 0, 'late' => 0, 'last' => 0, 'next' => 0];

    $filter = reedcrm_get_relaunch_sql_filter($db, $projectId, $socid);
    if (empty($filter)) {
        return $cache[$cacheKey] = $empty;
    }

    $now  = "'" . $db->idate(dol_now()) . "'";
    $late = "'" . $db->idate(dol_now() - reedcrm_get_relaunch_late_delay()) . "'";

    // percent: -1 not applicable, 0 to do, 100 done. Only 0..99 is still waiting for someone.
    $todo = '(a.percent >= 0 AND a.percent < 100)';

    $sql  = 'SELECT';
    $sql .= ' SUM(CASE WHEN a.datep < ' . $now . ' THEN 1 ELSE 0 END) as past,';
    $sql .= ' SUM(CASE WHEN a.datep >= ' . $now . ' AND ' . $todo . ' THEN 1 ELSE 0 END) as upcoming,';
    $sql .= ' SUM(CASE WHEN a.datep < ' . $now . ' AND ' . $todo . ' THEN 1 ELSE 0 END) as overdue,';
    $sql .= ' SUM(CASE WHEN a.datep < ' . $late . ' AND ' . $todo . ' THEN 1 ELSE 0 END) as late,';
    // Same two buckets as the counters: the most recent of the past, the closest of what is to come
    $sql .= ' MAX(CASE WHEN a.datep < ' . $now . ' THEN a.datep END) as last_date,';
    $sql .= ' MIN(CASE WHEN a.datep >= ' . $now . ' AND ' . $todo . ' THEN a.datep END) as next_date';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' WHERE 1 = 1' . $filter;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_get_relaunch_counts ' . $db->lasterror(), LOG_ERR);
        return $cache[$cacheKey] = $empty;
    }

    $obj = $db->fetch_object($resql);
    $db->free($resql);

    return $cache[$cacheKey] = [
        'past'     => (int) ($obj->past ?? 0),
        'upcoming' => (int) ($obj->upcoming ?? 0),
        'overdue'  => (int) ($obj->overdue ?? 0),
        'late'     => (int) ($obj->late ?? 0),
        'last'     => !empty($obj->last_date) ? (int) $db->jdate($obj->last_date) : 0,
        'next'     => !empty($obj->next_date) ? (int) $db->jdate($obj->next_date) : 0,
    ];
}

/**
 * Day of a relaunch as printed under its counter: "Today", "Yesterday", "3 d ago" on the past
 * side, "Tomorrow" or the short weekday and date on the side still to come.
 *
 * Days are compared on the calendar of the user, not as 24 h slices: an event at 23:00 yesterday
 * is "Yesterday" at 08:00 today, not "Today".
 *
 * @param  int    $timestamp Date of the event
 * @return string            Label ready to print
 */
function reedcrm_get_relaunch_day_label(int $timestamp): string
{
    global $langs;

    $utc   = new DateTimeZone('UTC');
    $today = new DateTime(dol_print_date(dol_now(), '%Y-%m-%d', 'tzuserrel'), $utc);
    $day   = new DateTime(dol_print_date($timestamp, '%Y-%m-%d', 'tzuserrel'), $utc);
    $diff  = (int) $today->diff($day)->format('%r%a');

    if ($diff === 0) {
        return $langs->trans('Today');
    }
    if ($diff === -1) {
        return $langs->trans('Yesterday');
    }
    if ($diff === 1) {
        return $langs->trans('Tomorrow');
    }
    if ($diff < 0) {
        return $langs->trans('RelaunchDaysAgo', -$diff);
    }

    // The short date of the language without its year, so the order of day and month follows it
    $dayMonth = preg_replace('/[^%a-zA-Z]*%Y[^%a-zA-Z]*/', '', $langs->trans('FormatDateShort'));

    return dol_print_date($timestamp, '%a ' . $dayMonth, 'tzuserrel');
}

/**
 * Rows behind the panel, for one bucket.
 *
 * Two queries whatever the volume: one for the events, one for the assigned contacts. The contact
 * is read from the resources table because the event handler only ever writes socpeopleassigned -
 * a.fk_contact stays null, which is why the "Qui" column of the old tooltip was always empty.
 *
 * @param  DoliDB $db        Database handler
 * @param  int    $projectId Project id
 * @param  int    $socid     Thirdparty id, used only when $projectId is 0
 * @param  string $scope     'past', 'upcoming' or '' for everything
 * @param  int    $limit     Maximum number of rows
 * @return array[]           Rows ready to print
 */
function reedcrm_get_relaunch_rows(DoliDB $db, int $projectId, int $socid = 0, string $scope = '', int $limit = 20): array
{
    $filter = reedcrm_get_relaunch_sql_filter($db, $projectId, $socid);
    if (empty($filter)) {
        return [];
    }

    $now  = "'" . $db->idate(dol_now()) . "'";
    $todo = '(a.percent >= 0 AND a.percent < 100)';

    if ($scope === 'upcoming') {
        $filter .= ' AND a.datep >= ' . $now . ' AND ' . $todo;
        $order   = ' ORDER BY a.datep ASC';
    } elseif ($scope === 'past') {
        $filter .= ' AND a.datep < ' . $now;
        $order   = ' ORDER BY a.datep DESC';
    } else {
        $order = ' ORDER BY a.datep DESC';
    }

    $sql  = 'SELECT a.id, a.datep, a.percent, a.label, a.note, a.code, a.fk_user_action, a.fk_user_author,';
    $sql .= ' u.rowid as user_id, u.lastname as user_lastname, u.firstname as user_firstname, u.photo as user_photo,';
    $sql .= ' (SELECT r.fk_element FROM ' . MAIN_DB_PREFIX . 'actioncomm_resources as r';
    $sql .= '  WHERE r.fk_actioncomm = a.id AND r.element_type = \'socpeople\' LIMIT 1) as contact_id';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'user as u ON u.rowid = a.fk_user_action';
    $sql .= ' WHERE 1 = 1' . $filter;
    $sql .= $order;
    $sql .= $db->plimit($limit > 0 ? $limit : 20, 0);

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_get_relaunch_rows ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $rows       = [];
    $contactIds = [];
    while ($obj = $db->fetch_object($resql)) {
        $rows[] = [
            'id'            => (int) $obj->id,
            'datep'         => $db->jdate($obj->datep),
            'percent'       => (int) $obj->percent,
            'label'         => (string) $obj->label,
            'note'          => (string) $obj->note,
            'code'          => (string) $obj->code,
            'type_key'      => reedcrm_get_relaunch_type_key((string) $obj->code),
            'user_id'       => (int) $obj->user_id,
            'author_id'     => (int) $obj->fk_user_author,
            'user_name'     => trim(trim((string) $obj->user_lastname) . ' ' . trim((string) $obj->user_firstname)),
            'user_photo'    => (string) $obj->user_photo,
            'contact_id'    => (int) $obj->contact_id,
            'contact_name'  => '',
        ];
        if (!empty($obj->contact_id)) {
            $contactIds[(int) $obj->contact_id] = (int) $obj->contact_id;
        }
    }
    $db->free($resql);

    if (!empty($contactIds)) {
        $sqlContacts  = 'SELECT rowid, lastname, firstname FROM ' . MAIN_DB_PREFIX . 'socpeople';
        $sqlContacts .= ' WHERE rowid IN (' . implode(',', $contactIds) . ')';

        $resqlContacts = $db->query($sqlContacts);
        if ($resqlContacts) {
            $names = [];
            while ($obj = $db->fetch_object($resqlContacts)) {
                $names[(int) $obj->rowid] = trim(trim((string) $obj->lastname) . ' ' . trim((string) $obj->firstname));
            }
            $db->free($resqlContacts);

            foreach ($rows as &$row) {
                if (!empty($row['contact_id']) && isset($names[$row['contact_id']])) {
                    $row['contact_name'] = $names[$row['contact_id']];
                }
            }
            unset($row);
        }
    }

    return $rows;
}

/**
 * State of one relaunch row, as shown by its badge.
 *
 * @param  array $row Row of reedcrm_get_relaunch_rows()
 * @param  int   $now Reference timestamp
 * @return array{key: string, css: string, late_days: int}
 */
function reedcrm_get_relaunch_row_state(array $row, int $now): array
{
    $choices = reedcrm_get_relaunch_status_choices();
    $status  = reedcrm_get_relaunch_status_choice($row['percent']);

    if ($status === -1 || $status === 100) {
        return $choices[$status] + ['late_days' => 0];
    }

    // Started or not, a relaunch whose day has passed is still waiting for someone
    $lateDays = (!empty($row['datep']) && $row['datep'] < $now) ? (int) floor(($now - $row['datep']) / 86400) : 0;
    if ($status === 0 && !empty($row['datep']) && $row['datep'] < $now) {
        return ['key' => 'RelaunchOverdue', 'css' => 'badge-status8 reedcrm-relaunch-row-overdue', 'late_days' => $lateDays];
    }

    return $choices[$status] + ['late_days' => $lateDays];
}

/**
 * Statuses offered by the selector of the panel, the four of the native agenda filter, keyed by the
 * progress written for each one. "Running" writes 50 % as the agenda does.
 *
 * @return array<int, array{key: string, css: string}> In the order of the selector
 */
function reedcrm_get_relaunch_status_choices(): array
{
    return [
        0   => ['key' => 'ToDo', 'css' => 'badge-status1'],
        50  => ['key' => 'ActionRunningShort', 'css' => 'badge-status3'],
        100 => ['key' => 'Done', 'css' => 'badge-status4'],
        -1  => ['key' => 'ActionNotApplicable', 'css' => 'badge-status9'],
    ];
}

/**
 * Status of the selector matching a progress: any progress between 1 and 99 % is "running".
 *
 * @param  int $percent Progress of the event
 * @return int          Key of reedcrm_get_relaunch_status_choices()
 */
function reedcrm_get_relaunch_status_choice(int $percent): int
{
    if ($percent < 0) {
        return -1;
    }
    if ($percent >= 100) {
        return 100;
    }

    return $percent > 0 ? 50 : 0;
}

/**
 * Whether the user may change the status of a relaunch, with the rule of comm/action/card.php:
 * every event, or the events the user wrote or owns.
 *
 * @param  User $user     User
 * @param  int  $authorId Author of the event
 * @param  int  $ownerId  Owner of the event
 * @return bool
 */
function reedcrm_user_can_edit_relaunch(User $user, int $authorId, int $ownerId): bool
{
    if ($user->hasRight('agenda', 'allactions', 'create')) {
        return true;
    }

    return ($authorId == $user->id || $ownerId == $user->id) && $user->hasRight('agenda', 'myactions', 'create');
}

/**
 * What the two buttons print for a set of counters: the content of each one and the classes that
 * colour it. Shared by the widget and by the status toggle of the panel, which repaints the buttons
 * of the page after a change instead of reloading it.
 *
 * @param  array $counts reedcrm_get_relaunch_counts()
 * @return array<string, array{content: string, classes: string[]}> Keyed by scope, 'past' then 'upcoming'
 */
function reedcrm_get_relaunch_segments(array $counts): array
{
    global $langs;

    $segments = [
        'past' => [
            'picto' => 'headset',
            'count' => $counts['past'],
            'day'   => $counts['last'] > 0 ? reedcrm_get_relaunch_day_label($counts['last']) : $langs->trans('RelaunchNever'),
        ],
        'upcoming' => [
            'picto' => $counts['upcoming'] > 0 ? 'bell' : 'bell-slash',
            'count' => $counts['upcoming'],
            'day'   => $counts['next'] > 0 ? reedcrm_get_relaunch_day_label($counts['next']) : $langs->trans('RelaunchNothingPlanned'),
        ],
    ];

    $result = [];
    foreach ($segments as $scope => $segment) {
        $classes = [];
        if ($scope === 'past' && $counts['overdue'] > 0) {
            $classes[] = $counts['late'] > 0 ? 'is-late' : 'is-overdue';
        }
        if ($segment['count'] === 0) {
            $classes[] = 'is-empty';
        }

        // Spans, not divs: the list cells flatten every div they contain (saturne _list-cell.scss)
        $content  = '<span class="reedcrm-relaunch-line">';
        $content .= '<i class="fas fa-' . $segment['picto'] . '"></i>';
        $content .= '<span class="reedcrm-relaunch-count">' . (int) $segment['count'] . '</span>';
        if ($scope === 'past' && $counts['overdue'] > 0) {
            $content .= '<span class="reedcrm-relaunch-overdue" title="' . dol_escape_htmltag($langs->trans('RelaunchOverdueCount', $counts['overdue'])) . '">';
            $content .= '<i class="fas fa-exclamation"></i>' . (int) $counts['overdue'];
            $content .= '</span>';
        }
        $content .= '</span>';
        $content .= '<span class="reedcrm-relaunch-day">' . $segment['day'] . '</span>';

        $result[$scope] = ['content' => $content, 'classes' => $classes];
    }

    return $result;
}

/**
 * The two-button relaunch widget, shared by every surface.
 *
 * Left button holds the past and raises the overdue warning, right button holds what is still to be
 * done. Each one prints its counter on top and, underneath, the day of the last relaunch or of the
 * next one. The "+" of the left button opens the complete event form (event + optional reminder),
 * the one of the right button opens the direct reminder form.
 *
 * @param  array $params projectId, socid, mode ('modal' on desktop, 'link' in the App), canCreate,
 *                       extraQuery appended to the creation urls, wrapperClass, fullEventFocus (selector
 *                       of the complete event form when the page already shows it, as the CardPro does:
 *                       the "+" of the left button goes to it instead of opening a second copy, with the
 *                       same ids, in the modal)
 * @return string        HTML output
 */
function reedcrm_render_relaunch_widget(array $params): string
{
    global $db, $langs, $user;

    $projectId      = (int) ($params['projectId'] ?? 0);
    $socid          = (int) ($params['socid'] ?? 0);
    $mode           = (string) ($params['mode'] ?? 'modal');
    $extraQuery     = (string) ($params['extraQuery'] ?? '');
    $wrapperClass   = (string) ($params['wrapperClass'] ?? '');
    $fullEventFocus = (string) ($params['fullEventFocus'] ?? '');
    $canCreate    = array_key_exists('canCreate', $params)
        ? (bool) $params['canCreate']
        : (bool) $user->hasRight('agenda', 'myactions', 'create');

    $langs->loadLangs(['reedcrm@reedcrm', 'agenda']);

    $counts    = reedcrm_get_relaunch_counts($db, $projectId, $socid);
    $dialogUrl = dol_buildpath('/reedcrm/ajax/get_relaunches_list.php', 1);

    if ($mode === 'link') {
        $createBase = dol_buildpath('/reedcrm/view/frontend/pwa_relaunch.php', 1);
        $createBase .= '?from_id=' . $projectId . '&from_type=project';
    } elseif ($projectId > 0) {
        $createBase = dol_buildpath('/reedcrm/view/procard.php', 1) . '?from_id=' . $projectId . '&from_type=project&project_id=' . $projectId;
    } else {
        $createBase = dol_buildpath('/reedcrm/view/procard.php', 1) . '?from_id=' . $socid . '&from_type=societe';
    }
    $createBase .= $extraQuery;

    $states   = reedcrm_get_relaunch_segments($counts);
    $segments = [
        'past' => [
            'title' => $langs->trans('RelaunchScopePastTooltip'),
            // Without javascript, the link opens the note tab of the form of the page
            'add'   => $fullEventFocus !== '' ? $createBase . '&tab=note#addeventform' : $createBase,
            'addTitle' => $langs->trans('RelaunchAddFullEvent'),
            'focus' => $fullEventFocus,
        ],
        'upcoming' => [
            'title' => $langs->trans('RelaunchScopeUpcomingTooltip'),
            'add'   => $createBase . '&mode=reminder',
            'addTitle' => $langs->trans('RelaunchAddDirectReminder'),
        ],
    ];

    // Framed with the ReedCRM logo on every surface, so that the buttons read as ReedCRM's among those of the page
    $out  = '<div class="reedcrm-relaunch-wrapper' . ($wrapperClass !== '' ? ' ' . $wrapperClass : '') . '">';
    $out .= '<img src="' . dol_buildpath('/reedcrm/img/reedcrm_color.png', 1) . '" class="reedcrm-relaunch-logo" alt="ReedCRM">';
    $out .= '<div class="reedcrm-relaunch-buttons reedcrm-relaunch-duo" data-project-id="' . $projectId . '" data-socid="' . $socid . '">';

    foreach ($segments as $scope => $segment) {
        $classes = array_merge(['reedcrm-relaunch-button', 'reedcrm-relaunch-seg', 'reedcrm-relaunch-seg-' . $scope], $states[$scope]['classes']);

        $out .= '<div class="' . implode(' ', $classes) . '"';
        $out .= ' data-relaunch-scope="' . $scope . '"';
        // Kept for the hover panel, which still keys on the historical attribute
        $out .= ' data-relaunch-type="all"';
        $out .= ' data-project-id="' . $projectId . '"';
        $out .= ' data-socid="' . $socid . '"';
        $out .= ' data-dialog-url="' . dol_escape_htmltag($dialogUrl) . '"';
        $out .= ' data-dialog-title="' . dol_escape_htmltag($segment['title']) . '"';
        $out .= ' data-limit="20">';

        $out .= '<div class="reedcrm-relaunch-btn-content">' . $states[$scope]['content'] . '</div>';

        if ($canCreate) {
            if ($mode === 'link' || !empty($segment['focus'])) {
                $out .= '<a class="reedcrm-relaunch-add" href="' . dol_escape_htmltag($segment['add']) . '"';
                if (!empty($segment['focus'])) {
                    $out .= ' data-focus="' . dol_escape_htmltag($segment['focus']) . '"';
                }
                $out .= ' title="' . dol_escape_htmltag($segment['addTitle']) . '" aria-label="' . dol_escape_htmltag($segment['addTitle']) . '">';
                $out .= '<i class="fas fa-plus"></i>';
                $out .= '</a>';
            } else {
                $out .= '<div class="reedcrm-relaunch-add modal-open reedcrm-modal-open"';
                $out .= ' title="' . dol_escape_htmltag($segment['addTitle']) . '"';
                $out .= ' data-project-id="' . $projectId . '"';
                $out .= ' data-modal-url="' . dol_escape_htmltag($segment['add']) . '">';
                $out .= '<i class="fas fa-plus"></i>';
                $out .= '<input type="hidden" class="modal-options" data-modal-to-open="eventproCardModal">';
                $out .= '</div>';
            }
        }

        $out .= '</div>';
    }

    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

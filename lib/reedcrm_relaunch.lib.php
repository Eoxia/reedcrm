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
 * but the four numbers, and the result is memoised for the page.
 *
 * @param  DoliDB $db        Database handler
 * @param  int    $projectId Project id
 * @param  int    $socid     Thirdparty id, used only when $projectId is 0
 * @return array{past: int, upcoming: int, overdue: int, late: int}
 */
function reedcrm_get_relaunch_counts(DoliDB $db, int $projectId, int $socid = 0): array
{
    static $cache = [];

    $cacheKey = $projectId . '-' . $socid;
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $empty = ['past' => 0, 'upcoming' => 0, 'overdue' => 0, 'late' => 0];

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
    $sql .= ' SUM(CASE WHEN a.datep < ' . $late . ' AND ' . $todo . ' THEN 1 ELSE 0 END) as late';
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
    ];
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

    $sql  = 'SELECT a.id, a.datep, a.percent, a.label, a.note, a.code, a.fk_user_action,';
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
    if ($row['percent'] < 0) {
        return ['key' => 'ActionNotApplicable', 'css' => 'badge-status8', 'late_days' => 0];
    }
    if ($row['percent'] >= 100) {
        return ['key' => 'Done', 'css' => 'badge-status4', 'late_days' => 0];
    }
    if (!empty($row['datep']) && $row['datep'] < $now) {
        return [
            'key'       => 'RelaunchOverdue',
            'css'       => 'badge-status8 reedcrm-relaunch-row-overdue',
            'late_days' => (int) floor(($now - $row['datep']) / 86400),
        ];
    }

    return ['key' => 'ToDo', 'css' => 'badge-status1', 'late_days' => 0];
}

/**
 * The two-button relaunch widget, shared by every surface.
 *
 * Left button holds the past and raises the overdue warning, right button holds what is still to be
 * done. The "+" of the left button opens the complete event form (event + optional reminder), the
 * one of the right button opens the direct reminder form.
 *
 * @param  array $params projectId, socid, mode ('modal' on desktop, 'link' in the App), canCreate,
 *                       extraQuery appended to the creation urls, wrapperClass
 * @return string        HTML output
 */
function reedcrm_render_relaunch_widget(array $params): string
{
    global $db, $langs, $user;

    $projectId    = (int) ($params['projectId'] ?? 0);
    $socid        = (int) ($params['socid'] ?? 0);
    $mode         = (string) ($params['mode'] ?? 'modal');
    $extraQuery   = (string) ($params['extraQuery'] ?? '');
    $wrapperClass = (string) ($params['wrapperClass'] ?? '');
    $canCreate    = array_key_exists('canCreate', $params)
        ? (bool) $params['canCreate']
        : (bool) $user->hasRight('agenda', 'myactions', 'create');

    $langs->loadLangs(['reedcrm@reedcrm', 'agenda']);

    $counts    = reedcrm_get_relaunch_counts($db, $projectId, $socid);
    $dialogUrl = dol_buildpath('/custom/reedcrm/ajax/get_relaunches_list.php', 1);

    if ($mode === 'link') {
        $createBase = dol_buildpath('/custom/reedcrm/view/frontend/pwa_relaunch.php', 1);
        $createBase .= '?from_id=' . $projectId . '&from_type=project';
    } elseif ($projectId > 0) {
        $createBase = DOL_URL_ROOT . '/custom/reedcrm/view/procard.php?from_id=' . $projectId . '&from_type=project&project_id=' . $projectId;
    } else {
        $createBase = DOL_URL_ROOT . '/custom/reedcrm/view/procard.php?from_id=' . $socid . '&from_type=societe';
    }
    $createBase .= $extraQuery;

    $segments = [
        'past' => [
            'picto' => 'calendar-check',
            'count' => $counts['past'],
            'title' => $langs->trans('RelaunchScopePastTooltip'),
            'add'   => $createBase,
            'addTitle' => $langs->trans('RelaunchAddFullEvent'),
        ],
        'upcoming' => [
            'picto' => 'list-ul',
            'count' => $counts['upcoming'],
            'title' => $langs->trans('RelaunchScopeUpcomingTooltip'),
            'add'   => $createBase . '&mode=reminder',
            'addTitle' => $langs->trans('RelaunchAddDirectReminder'),
        ],
    ];

    $out  = '<div class="reedcrm-relaunch-wrapper' . ($wrapperClass !== '' ? ' ' . $wrapperClass : '') . '">';
    $out .= '<div class="reedcrm-relaunch-buttons reedcrm-relaunch-duo" data-project-id="' . $projectId . '" data-socid="' . $socid . '">';

    foreach ($segments as $scope => $segment) {
        $classes = ['reedcrm-relaunch-button', 'reedcrm-relaunch-seg', 'reedcrm-relaunch-seg-' . $scope];
        if ($scope === 'past' && $counts['overdue'] > 0) {
            $classes[] = $counts['late'] > 0 ? 'is-late' : 'is-overdue';
        }
        if ($scope === 'upcoming' && $segment['count'] === 0) {
            $classes[] = 'is-empty';
        }

        $out .= '<div class="' . implode(' ', $classes) . '"';
        $out .= ' data-relaunch-scope="' . $scope . '"';
        // Kept for the hover panel, which still keys on the historical attribute
        $out .= ' data-relaunch-type="all"';
        $out .= ' data-project-id="' . $projectId . '"';
        $out .= ' data-socid="' . $socid . '"';
        $out .= ' data-dialog-url="' . dol_escape_htmltag($dialogUrl) . '"';
        $out .= ' data-dialog-title="' . dol_escape_htmltag($segment['title']) . '"';
        $out .= ' data-limit="20"';
        $out .= ' title="' . dol_escape_htmltag($segment['title']) . '">';

        $out .= '<div class="reedcrm-relaunch-btn-content reedcrm-plist-relaunch-btn-content">';
        $out .= '<i class="fas fa-' . $segment['picto'] . '"></i>';
        $out .= '<span class="reedcrm-relaunch-count reedcrm-plist-relaunch-count">' . (int) $segment['count'] . '</span>';
        $out .= '</div>';

        if ($scope === 'past' && $counts['overdue'] > 0) {
            $out .= '<span class="reedcrm-relaunch-overdue" title="' . dol_escape_htmltag($langs->trans('RelaunchOverdueCount', $counts['overdue'])) . '">';
            $out .= '<i class="fas fa-exclamation"></i>' . (int) $counts['overdue'];
            $out .= '</span>';
        }

        if ($canCreate) {
            if ($mode === 'link') {
                $out .= '<a class="reedcrm-relaunch-add reedcrm-plist-relaunch-add" href="' . dol_escape_htmltag($segment['add']) . '"';
                $out .= ' title="' . dol_escape_htmltag($segment['addTitle']) . '" aria-label="' . dol_escape_htmltag($segment['addTitle']) . '">';
                $out .= '<i class="fas fa-plus"></i>';
                $out .= '</a>';
            } else {
                $out .= '<div class="reedcrm-relaunch-add reedcrm-plist-relaunch-add modal-open reedcrm-modal-open"';
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

/**
 * Create a standalone call reminder.
 *
 * Extracted from the add_event handler so the direct reminder button and the reminder checkbox of
 * the complete event form share one write path. It builds a NEW ActionComm instead of mutating and
 * re-creating the event object: the old way left the reminder with the event's fk_action while its
 * code said AC_OTH, and wrote a socpeople/0 resource whenever no contact was picked.
 *
 * @param  DoliDB $db   Database handler
 * @param  User   $user Author
 * @param  array  $data label, datep, userId, projectId, socid, contactId, typeCode, note
 * @return int          Id of the created event, or a negative value on failure
 */
function reedcrm_create_call_reminder(DoliDB $db, User $user, array $data): int
{
    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncommreminder.class.php';
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
    // dol_time_plus_duree() lives in date.lib.php, which not every host page loads
    require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

    $label = trim((string) ($data['label'] ?? ''));
    if ($label === '') {
        return -1;
    }

    $datep  = (int) ($data['datep'] ?? 0);
    $userId = (int) ($data['userId'] ?? 0) ?: $user->id;

    $reminder                    = new ActionComm($db);
    $reminder->type_code         = (string) ($data['typeCode'] ?? 'AC_OTH');
    $reminder->percentage        = 0; // A reminder is a future "to do", never a logged event
    $reminder->datep             = $datep > 0 ? $datep : dol_now();
    $reminder->label             = $label;
    $reminder->note_private      = (string) ($data['note'] ?? '');
    $reminder->fk_project        = (int) ($data['projectId'] ?? 0);
    $reminder->socid             = (int) ($data['socid'] ?? 0);
    $reminder->userownerid       = $userId;
    $reminder->userassigned      = [$userId => ['id' => $userId]];

    // Writing socpeopleassigned with a 0 key creates a parasite socpeople/0 resource
    $contactId = (int) ($data['contactId'] ?? 0);
    if ($contactId > 0) {
        $reminder->socpeopleassigned = [$contactId => $contactId];
    }

    $reminderId = $reminder->create($user);
    if ($reminderId <= 0) {
        dol_syslog('reedcrm_create_call_reminder ' . $reminder->error, LOG_ERR);
        return $reminderId;
    }

    // Dedicated tag, not the commercial relaunch one: a reminder must not inflate the relaunch
    // history. Both categories are unioned at read time by reedcrm_get_relaunch_sql_filter().
    $reminderCategoryId = reedcrm_get_call_reminder_category_id($db, $user);
    if ($reminderCategoryId > 0) {
        $reminderCategory = new Categorie($db);
        if ($reminderCategory->fetch($reminderCategoryId) > 0) {
            $reminderCategory->add_type($reminder, 'actioncomm');
        }
    }

    $offsetValue = getDolGlobalInt('REEDCRM_QUICK_CREATION_REMINDER_OFFSET');
    $offsetUnit  = getDolGlobalString('REEDCRM_QUICK_CREATION_REMINDER_UNIT');

    $actionCommReminder                = new ActionCommReminder($db);
    $actionCommReminder->dateremind    = dol_time_plus_duree($reminder->datep, -1 * $offsetValue, $offsetUnit);
    $actionCommReminder->typeremind    = 'browser';
    $actionCommReminder->offsetvalue   = $offsetValue;
    $actionCommReminder->offsetunit    = $offsetUnit;
    $actionCommReminder->fk_actioncomm = $reminderId;
    $actionCommReminder->fk_user       = $userId;
    $actionCommReminder->status        = $actionCommReminder::STATUS_TODO;

    if ($actionCommReminder->create($user) <= 0) {
        // The reminder event itself is valid, only the browser notification failed
        dol_syslog('reedcrm_create_call_reminder notification ' . $actionCommReminder->error, LOG_WARNING);
    }

    return $reminderId;
}

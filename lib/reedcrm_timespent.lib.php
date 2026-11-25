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
 * \file    lib/reedcrm_timespent.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the time logged from the quick block of the ticket and project cards.
 *          A ticket logs its time on a task of its project named from the ticket setup of the module,
 *          a project on its commercial follow-up task: both tasks are created on first use.
 */

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

/**
 * Number of colors the initials of the users are drawn from, see _timespent-block.scss
 */
const REEDCRM_TIMESPENT_AVATAR_COLORS = 8;

/**
 * Can the user log time from the quick block ?
 * Same right as the one Dolibarr asks to add time on a task, kept to the users of the company
 *
 * @param  User $user User
 * @return bool
 */
function reedcrm_timespent_user_can_log(User $user): bool
{
    return isModEnabled('project') && empty($user->socid) && $user->hasRight('projet', 'time');
}

/**
 * Project the time of an object is logged on : the project itself, or the one the ticket is linked to
 *
 * @param  DoliDB       $db     Database handler
 * @param  CommonObject $object Ticket or project
 * @return Project|null         Null when the ticket is linked to no project
 */
function reedcrm_timespent_get_project(DoliDB $db, CommonObject $object): ?Project
{
    if ($object instanceof Project) {
        return $object->id > 0 ? $object : null;
    }

    if ($object->element === 'ticket' && $object->fk_project > 0) {
        $project = new Project($db);
        if ($project->fetch((int) $object->fk_project) > 0) {
            return $project;
        }
    }

    return null;
}

/**
 * Label of the task holding the time of a ticket, built from the ticket setup of the module
 *
 * @param  CommonObject $ticket  Ticket
 * @param  Project      $project Project of the ticket
 * @return string
 */
function reedcrm_timespent_ticket_task_label(CommonObject $ticket, Project $project): string
{
    $suffixType = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref');

    $suffix = '';
    if ($suffixType === 'ticket_ref') {
        $suffix = ' ' . $ticket->ref;
    } elseif ($suffixType === 'project_ref') {
        $suffix = ' ' . $project->ref;
    } elseif ($suffixType === 'project_label') {
        $suffix = ' ' . $project->title;
    }

    return trim(getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps') . $suffix);
}

/**
 * Find the task holding the time of a ticket or a project, without creating it
 *
 * @param  DoliDB       $db      Database handler
 * @param  CommonObject $object  Ticket or project
 * @param  Project      $project Project the time is logged on
 * @return int                   Task ID, 0 when the task does not exist yet
 */
function reedcrm_timespent_find_task_id(DoliDB $db, CommonObject $object, Project $project): int
{
    $sql = 'SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'projet_task as t WHERE t.fk_projet = ' . ((int) $project->id);
    if ($object instanceof Project) {
        // The commercial follow-up task may have been deleted since it was set on the project
        $commTaskId = (int) ($project->array_options['options_commtask'] ?? 0);
        if ($commTaskId <= 0) {
            return 0;
        }
        $sql .= ' AND t.rowid = ' . $commTaskId;
    } else {
        $sql .= " AND t.label = '" . $db->escape(reedcrm_timespent_ticket_task_label($object, $project)) . "'";
        $sql .= ' ORDER BY t.rowid ASC';
    }

    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? (int) $obj->rowid : 0;
}

/**
 * Fetch the task holding the time of a ticket or a project, created when it does not exist yet
 *
 * @param  DoliDB       $db      Database handler
 * @param  User         $user    User logging the time
 * @param  CommonObject $object  Ticket or project
 * @param  Project      $project Project the time is logged on
 * @return Task|string           Task, or the error message
 * @throws Exception
 */
function reedcrm_timespent_get_or_create_task(DoliDB $db, User $user, CommonObject $object, Project $project)
{
    global $langs;

    $task   = new Task($db);
    $taskId = reedcrm_timespent_find_task_id($db, $object, $project);
    if ($taskId > 0) {
        return $task->fetch($taskId) > 0 ? $task : $task->error;
    }

    $task->fk_project = $project->id;
    $task->date_c     = dol_now();

    if ($object instanceof Project) {
        // Same commercial follow-up task as the one of the quick events and of the call lists
        $modTaskName = getDolGlobalString('PROJECT_TASK_ADDON', 'mod_task_simple');
        if (is_readable(DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $modTaskName . '.php')) {
            require_once DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $modTaskName . '.php';
            $modTask   = new $modTaskName();
            $task->ref = $modTask->getNextValue(null, $task);
        }
        $task->label = (getDolGlobalString('REEDCRM_TASK_LABEL_VALUE') ?: $langs->transnoentities('CommercialFollowUp')) . ' - ' . $project->title;

        if ($task->create($user) <= 0) {
            return $task->error ?: 'Task creation failed';
        }

        $internalUserIds = $project->liste_contact(-1, 'internal', 1);
        if (!is_array($internalUserIds) || !in_array($user->id, $internalUserIds)) {
            $project->add_contact($user->id, 'PROJECTLEADER', 'internal');
        }
        $task->add_contact($user->id, 'TASKEXECUTIVE', 'internal');

        $project->array_options['options_commtask'] = $task->id;
        $project->updateExtraField('commtask');
    } else {
        $task->ref         = $object->ref;
        $task->label       = reedcrm_timespent_ticket_task_label($object, $project);
        $task->description = $langs->transnoentities('TicketTimeTaskDescription', $object->ref);
        $task->date_start  = dol_now();
        $task->date_end    = dol_now();
        $task->progress    = 0;
        $task->status      = 1;

        if ($task->create($user) <= 0) {
            return $task->error ?: 'Task creation failed';
        }
    }

    return $task;
}

/**
 * Log time on a ticket or a project, and keep a trace of it in the agenda
 *
 * @param  DoliDB       $db       Database handler
 * @param  User         $user     User logging the time
 * @param  CommonObject $object   Ticket or project
 * @param  int          $minutes  Time spent, in minutes
 * @param  string       $note     Note of the time spent, plain text
 * @param  int          $date     Date the time was spent, now when empty
 * @param  bool         $addEvent False when the time comes from an event already in the agenda
 * @return string                 Error message, empty on success
 * @throws Exception
 */
function reedcrm_timespent_add(DoliDB $db, User $user, CommonObject $object, int $minutes, string $note, int $date = 0, bool $addEvent = true): string
{
    global $langs;

    $langs->load('reedcrm@reedcrm');

    $project = reedcrm_timespent_get_project($db, $object);
    if ($project === null) {
        return $langs->transnoentities('PleaseLinkProjectFirst');
    }

    $task = reedcrm_timespent_get_or_create_task($db, $user, $object, $project);
    if (!$task instanceof Task) {
        return $task;
    }

    $task->timespent_duration = $minutes * 60;
    $task->timespent_date     = $date ?: dol_now();
    $task->timespent_datehour = $date ?: dol_now();
    $task->timespent_fk_user  = $user->id;
    $task->timespent_note     = $note;
    if ($task->addTimeSpent($user) <= 0) {
        return $task->error ?: 'Time spent creation failed';
    }

    if ($addEvent && isModEnabled('agenda')) {
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        $isProject  = $object instanceof Project;
        $cleanNote  = trim(preg_replace('/\s+/', ' ', $note));
        $eventDesc  = $langs->transnoentities('ReedCRMTimeSpentEventDuration', $minutes);
        if ($note !== '') {
            $eventDesc .= '<br>' . $langs->transnoentities('ReedCRMTimeSpentEventComment') . '<br>' . nl2br(dol_escape_htmltag($note));
        }

        $actioncomm               = new ActionComm($db);
        $actioncomm->type_code    = 'AC_OTH_AUTO';
        $actioncomm->code         = $isProject ? 'PROJECT_TIMESPENT' : 'TICKET_TIMESPENT';
        $actioncomm->label        = $cleanNote !== '' ? dol_trunc($cleanNote, getDolGlobalInt('REEDCRM_TICKET_TIME_TITLE_MAXLENGTH', 200)) : $langs->transnoentities('ReedCRMTimeSpentEventLabel', $minutes);
        $actioncomm->note_private = $eventDesc;
        $actioncomm->socid        = $isProject ? $project->socid : $object->socid;
        $actioncomm->fk_project   = $project->id;
        $actioncomm->elementtype  = $isProject ? 'project' : 'ticket';
        $actioncomm->fk_element   = $object->id;
        $actioncomm->datep        = dol_now();
        $actioncomm->datef        = dol_now();
        $actioncomm->percentage   = 100;
        $actioncomm->userownerid  = $user->id;
        $actioncomm->userassigned = [$user->id => ['id' => $user->id, 'transparency' => 0]];
        $actioncomm->create($user);
    }

    return '';
}

/**
 * Time spent on a ticket, read on its task, or on a project, read on all its tasks
 *
 * @param  DoliDB       $db       Database handler
 * @param  CommonObject $object   Ticket or project
 * @param  int          $nbLatest Number of latest time entries to return
 * @return array                  ['task_id' => , 'task_ref' => , 'url' => , 'count' => , 'total' => , 'planned' => ,
 *                                'latest' => [], 'per_user' => [], 'per_task' => []], durations in seconds
 */
function reedcrm_timespent_get_summary(DoliDB $db, CommonObject $object, int $nbLatest = 5): array
{
    $summary = ['task_id' => 0, 'task_ref' => '', 'url' => '', 'count' => 0, 'total' => 0, 'planned' => 0, 'latest' => [], 'per_user' => [], 'per_task' => []];

    $project = reedcrm_timespent_get_project($db, $object);
    if ($project === null) {
        return $summary;
    }

    $summary['task_id'] = reedcrm_timespent_find_task_id($db, $object, $project);
    if ($object instanceof Project) {
        $scope          = 't.fk_projet = ' . ((int) $project->id);
        $summary['url'] = DOL_URL_ROOT . '/projet/tasks/time.php?projectid=' . ((int) $project->id) . '&withproject=1';
    } elseif ($summary['task_id'] > 0) {
        $scope          = 't.rowid = ' . $summary['task_id'];
        $summary['url'] = DOL_URL_ROOT . '/projet/tasks/time.php?id=' . $summary['task_id'] . '&withproject=1';
    } else {
        return $summary;
    }

    $from  = ' FROM ' . MAIN_DB_PREFIX . 'element_time as et';
    $from .= ' INNER JOIN ' . MAIN_DB_PREFIX . "projet_task as t ON t.rowid = et.fk_element AND et.elementtype = 'task'";
    $from .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'user as u ON u.rowid = et.fk_user';
    $from .= ' WHERE ' . $scope;

    $resql = $db->query('SELECT et.fk_user, u.login, u.firstname, u.lastname, COUNT(et.rowid) as nb, SUM(et.element_duration) as duration' . $from . ' GROUP BY et.fk_user, u.login, u.firstname, u.lastname ORDER BY duration DESC');
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $summary['count']     += (int) $obj->nb;
            $summary['total']     += (int) $obj->duration;
            $summary['per_user'][] = ['name' => trim($obj->firstname . ' ' . $obj->lastname) ?: (string) $obj->login, 'duration' => (int) $obj->duration];
        }
    }

    // A ticket holds one task, its split by task would only repeat the total
    if ($object instanceof Project) {
        $resql = $db->query('SELECT t.rowid, t.ref, t.label, SUM(et.element_duration) as duration' . $from . ' GROUP BY t.rowid, t.ref, t.label ORDER BY duration DESC');
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $summary['per_task'][] = ['ref' => $obj->ref, 'label' => $obj->label, 'duration' => (int) $obj->duration];
            }
        }
    }

    $resql = $db->query('SELECT SUM(t.planned_workload) as planned, MIN(t.ref) as ref FROM ' . MAIN_DB_PREFIX . 'projet_task as t WHERE ' . $scope);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        $summary['planned']  = (int) $obj->planned;
        $summary['task_ref'] = $summary['task_id'] > 0 && !($object instanceof Project) ? (string) $obj->ref : '';
    }

    // On a project, the same planned time as its monthly card: the time sold by its signed proposals, else the
    // workload of its tasks, year by year
    if ($object instanceof Project) {
        $summary['planned'] = 0;
        foreach (reedcrm_timespent_get_project_monthly($db, $object) as $yearMonths) {
            $summary['planned'] += (int) round(array_sum($yearMonths['planned']));
        }
    }

    $resql = $db->query('SELECT et.element_datehour, et.element_duration, et.note, u.login' . $from . ' ORDER BY et.element_datehour DESC, et.rowid DESC' . $db->plimit($nbLatest));
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $summary['latest'][] = ['date' => $db->jdate($obj->element_datehour), 'duration' => (int) $obj->element_duration, 'note' => (string) $obj->note, 'login' => (string) $obj->login];
        }
    }

    return $summary;
}

/**
 * Time sold on a project: the services of its signed proposals, quantity x duration of the service
 *
 * Each part of it falls on the date of the service: the intervention dates of the line (one per unit of quantity),
 * else the service period of the line, else the delivery date of the proposal, else its signature date. A service
 * whose duration is not in minutes, hours or days (a subscription of a year...) sells no working time.
 *
 * @param  DoliDB  $db      Database handler
 * @param  Project $project Project
 * @return array            List of ['seconds' => , 'start' => timestamp, 'end' => timestamp], empty when nothing is sold
 */
function reedcrm_timespent_get_project_sold_time(DoliDB $db, Project $project): array
{
    require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
    require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
    require_once __DIR__ . '/../class/reedcrmticketmoney.class.php';

    $sql  = 'SELECT pd.rowid, pd.qty, pd.date_start, pd.date_end, p.duration, pr.date_livraison, pr.date_signature, pr.date_cloture, pr.datep';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propaldet as pd';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'propal as pr ON pr.rowid = pd.fk_propal';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = pd.fk_product';
    $sql .= ' WHERE pr.fk_projet = ' . ((int) $project->id);
    $sql .= ' AND pr.entity IN (' . getEntity('propal') . ')';
    $sql .= ' AND pr.fk_statut IN (' . Propal::STATUS_SIGNED . ', ' . Propal::STATUS_BILLED . ')';
    $sql .= ' AND pd.product_type = ' . Product::TYPE_SERVICE . ' AND pd.qty > 0';
    $resql = $db->query($sql);
    if (!$resql) {
        return [];
    }

    $lines = [];
    while ($obj = $db->fetch_object($resql)) {
        $unitSeconds = ReedcrmTicketMoney::durationToSeconds((string) $obj->duration);
        if ($unitSeconds > 0) {
            $lines[(int) $obj->rowid] = ['obj' => $obj, 'seconds' => (float) $obj->qty * $unitSeconds, 'dates' => []];
        }
    }
    if (empty($lines)) {
        return [];
    }

    $sql  = 'SELECT fk_element_line, date_intervention FROM ' . MAIN_DB_PREFIX . 'reedcrm_intervention_date';
    $sql .= " WHERE element_type = 'propal' AND date_intervention IS NOT NULL AND fk_element_line IN (" . implode(',', array_keys($lines)) . ')';
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $lines[(int) $obj->fk_element_line]['dates'][] = (int) $db->jdate($obj->date_intervention);
        }
    }

    $parts = [];
    foreach ($lines as $line) {
        $obj   = $line['obj'];
        $units = max(1, (int) ceil((float) $obj->qty));
        $left  = $line['seconds'];

        // A quantity of 2.5 asks for 3 intervention dates: each one holds a third of the time sold
        foreach (array_slice($line['dates'], 0, $units) as $date) {
            $parts[] = ['seconds' => $line['seconds'] / $units, 'start' => $date, 'end' => $date];
            $left   -= $line['seconds'] / $units;
        }
        if ($left <= 0) {
            continue;
        }

        $lineStart = (int) $db->jdate($obj->date_start);
        if ($lineStart > 0) {
            $parts[] = ['seconds' => $left, 'start' => $lineStart, 'end' => ((int) $db->jdate($obj->date_end)) ?: $lineStart];
        } else {
            $date    = (int) ($db->jdate($obj->date_livraison) ?: ($db->jdate($obj->date_signature) ?: ($db->jdate($obj->date_cloture) ?: $db->jdate($obj->datep))));
            $parts[] = ['seconds' => $left, 'start' => $date, 'end' => $date];
        }
    }

    return $parts;
}

/**
 * Time planned and time spent on a project, month by month
 *
 * The time planned is the time sold by the signed proposals of the project, on the month of the service (see
 * reedcrm_timespent_get_project_sold_time()). A year without any takes the workload planned on the tasks, spread
 * evenly over the days of each task: a task without dates takes the ones of the project, or falls on the day it was
 * created.
 *
 * @param  DoliDB  $db      Database handler
 * @param  Project $project Project
 * @return array            [year => ['planned' => [month => seconds], 'spent' => [month => seconds]]], months 1 to 12, years sorted
 */
function reedcrm_timespent_get_project_monthly(DoliDB $db, Project $project): array
{
    $months = [];
    $add    = function (string $kind, int $year, int $month, float $seconds) use (&$months) {
        if (!isset($months[$year])) {
            $months[$year] = ['planned' => array_fill(1, 12, 0.0), 'spent' => array_fill(1, 12, 0.0)];
        }
        $months[$year][$kind][$month] += $seconds;
    };

    // Days are counted between midnights, rounded: a day of a change of time lasts 23 or 25 hours
    $midnight = function (int $date): int {
        return dol_mktime(0, 0, 0, (int) dol_print_date($date, '%m'), (int) dol_print_date($date, '%d'), (int) dol_print_date($date, '%Y'));
    };

    // A planned time spread evenly over the days from its start to its end, into [year => [month => seconds]]
    $spread = function (array &$target, int $start, int $end, float $seconds) use ($midnight) {
        $start  = $midnight($start);
        $end    = max($start, $midnight($end));
        $perDay = $seconds / ((int) round(($end - $start) / 86400) + 1);
        $cursor = $start;
        while ($cursor <= $end) {
            $year     = (int) dol_print_date($cursor, '%Y');
            $month    = (int) dol_print_date($cursor, '%m');
            $monthEnd = min($end, dol_mktime(0, 0, 0, $month, (int) date('t', mktime(0, 0, 0, $month, 1, $year)), $year));
            $target[$year][$month] = ($target[$year][$month] ?? 0) + $perDay * ((int) round(($monthEnd - $cursor) / 86400) + 1);
            $cursor = dol_mktime(0, 0, 0, $month % 12 + 1, 1, $month === 12 ? $year + 1 : $year);
        }
    };

    $sold = [];
    foreach (reedcrm_timespent_get_project_sold_time($db, $project) as $part) {
        $spread($sold, $part['start'], $part['end'], $part['seconds']);
    }

    $tasks = [];
    $resql = $db->query('SELECT t.planned_workload, t.dateo, t.datee, t.datec FROM ' . MAIN_DB_PREFIX . 'projet_task as t WHERE t.fk_projet = ' . ((int) $project->id) . ' AND t.planned_workload > 0');
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $start = (int) ($db->jdate($obj->dateo) ?: ($project->date_start ?: $db->jdate($obj->datec)));
            $spread($tasks, $start, (int) ($db->jdate($obj->datee) ?: ($project->date_end ?: $start)), (float) $obj->planned_workload);
        }
    }

    // The time sold wins over the workload of the tasks year by year: the two would count the same work twice, and a
    // proposal signed for one year does not erase the workload planned on the tasks of another
    foreach (array_unique(array_merge(array_keys($sold), array_keys($tasks))) as $year) {
        foreach (array_sum($sold[$year] ?? []) > 0 ? $sold[$year] : ($tasks[$year] ?? []) as $month => $seconds) {
            $add('planned', (int) $year, (int) $month, (float) $seconds);
        }
    }

    // One line per day, summed by month here : YEAR() and MONTH() are not portable
    $sql  = 'SELECT et.element_date, SUM(et.element_duration) as duration FROM ' . MAIN_DB_PREFIX . 'element_time as et';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "projet_task as t ON t.rowid = et.fk_element AND et.elementtype = 'task'";
    $sql .= ' WHERE t.fk_projet = ' . ((int) $project->id);
    $sql .= ' GROUP BY et.element_date';
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $date = $db->jdate($obj->element_date);
            $add('spent', (int) dol_print_date($date, '%Y'), (int) dol_print_date($date, '%m'), (float) $obj->duration);
        }
    }

    ksort($months);

    return $months;
}

/**
 * Format a duration in hours, the way the time block and the monthly table show it (12h, 3h30, +2h, -1h15)
 *
 * @param  float  $seconds Duration in seconds
 * @param  bool   $signed  Show the sign of a positive duration too
 * @return string
 */
function reedcrm_timespent_format_hours(float $seconds, bool $signed = false): string
{
    $minutes = (int) round(abs($seconds) / 60);
    $sign    = $seconds < 0 && $minutes > 0 ? '-' : ($signed && $minutes > 0 ? '+' : '');

    return $sign . intdiv($minutes, 60) . 'h' . ($minutes % 60 > 0 ? sprintf('%02d', $minutes % 60) : '');
}

/**
 * Format a duration in whole hours, for the narrow month columns of the project table (42h, -43h)
 * A duration under one hour keeps its minutes, rounded it would read as nothing
 *
 * @param  float  $seconds Duration in seconds
 * @param  bool   $signed  Show the sign of a positive duration too
 * @return string
 */
function reedcrm_timespent_format_hours_short(float $seconds, bool $signed = false): string
{
    return reedcrm_timespent_format_hours(abs($seconds) < 3600 ? $seconds : round($seconds / 3600) * 3600, $signed);
}

/**
 * Duration in hours for the totals of the project card, its "h" set apart so that the figures read at a glance
 *
 * @param  float  $seconds Duration in seconds
 * @return string          HTML
 */
function reedcrm_timespent_format_hours_html(float $seconds): string
{
    return str_replace('h', '<span class="reedcrm-project-time-unit">h</span>', reedcrm_timespent_format_hours($seconds));
}

/**
 * Totals of a year of the project chart
 *
 * @param  array $months Months of a year returned by reedcrm_timespent_get_project_monthly()
 * @return array         ['planned' => seconds, 'spent' => seconds, 'over' => seconds spent beyond the planned time, month
 *                       by month, 'average' => seconds spent per month holding time]
 */
function reedcrm_timespent_get_year_totals(array $months): array
{
    $totals = ['planned' => 0.0, 'spent' => 0.0, 'over' => 0.0, 'average' => 0.0];
    $active = 0;
    for ($month = 1; $month <= 12; $month++) {
        $totals['planned'] += $months['planned'][$month];
        $totals['spent']   += $months['spent'][$month];
        $totals['over']    += max(0, $months['spent'][$month] - $months['planned'][$month]);
        $active            += ($months['planned'][$month] > 0 || $months['spent'][$month] > 0) ? 1 : 0;
    }
    $totals['average'] = $totals['spent'] / max(1, $active);

    return $totals;
}

/**
 * Heights of the bars of a month of the project chart, as steps of 2.5 % of the largest month of the year: the
 * stylesheet holds one class per step, so that the heights need no inline style. A time keeps at least one step.
 * The spent bar is split at the planned time: within it, then the part beyond it stacked on top.
 *
 * @param  float $planned Time planned on the month, in seconds
 * @param  float $spent   Time spent on the month, in seconds
 * @param  float $max     Largest planned or spent time of a month of the year, in seconds
 * @return array          ['planned' => step, 'within' => step, 'over' => step], steps from 0 to 40
 */
function reedcrm_timespent_get_month_bars(float $planned, float $spent, float $max): array
{
    $step = function (float $seconds) use ($max): int {
        return ($seconds <= 0 || $max <= 0) ? 0 : max(1, min(40, (int) round($seconds / $max * 40)));
    };

    $bars = ['planned' => $step($planned), 'within' => $step(min($spent, $planned)), 'over' => 0];
    if (round(($spent - $planned) / 60) > 0) {
        $bars['over']   = max(1, $step($spent) - $bars['within']);
        $bars['within'] = min($bars['within'], 40 - $bars['over']);
    }

    return $bars;
}

/**
 * Tooltip of the counter of the block: the latest time entries
 *
 * @param  array  $summary Summary returned by reedcrm_timespent_get_summary()
 * @return string          HTML, to be escaped in the title attribute
 */
function reedcrm_timespent_render_latest_tooltip(array $summary): string
{
    global $langs;

    if ($summary['count'] <= 0) {
        return $langs->trans('ReedCRMNoTimeEntries');
    }

    $tooltip = '<b>' . ($summary['task_ref'] !== '' ? dol_escape_htmltag($summary['task_ref']) . ' - ' : '') . $langs->trans('ReedCRMTimeEntriesLatest', count($summary['latest']), $summary['count']) . '</b><br><br>';
    foreach ($summary['latest'] as $entry) {
        $tooltip .= dol_print_date($entry['date'], '%d/%m/%y %H:%M') . ' | ' . dol_escape_htmltag($entry['login']) . ' | ' . reedcrm_timespent_format_hours($entry['duration']);
        $note     = dol_trunc(strip_tags($entry['note']), 100);
        if ($note !== '') {
            $tooltip .= ' | ' . dol_escape_htmltag($note);
        }
        $tooltip .= '<br>';
    }

    return $tooltip;
}

/**
 * Line of the latest time entry, under the input of the block
 *
 * @param  array  $summary Summary returned by reedcrm_timespent_get_summary()
 * @return string          HTML, empty when no time was logged yet
 */
function reedcrm_timespent_render_last_entry(array $summary): string
{
    if (empty($summary['latest'])) {
        return '';
    }

    $entry = $summary['latest'][0];
    $note  = dol_trunc(strip_tags($entry['note']), 100);
    $color = hexdec(substr(md5($entry['login']), 0, 6)) % REEDCRM_TIMESPENT_AVATAR_COLORS;

    $out  = '<div class="reedcrm-timespent-last">';
    $out .= '<span class="reedcrm-timespent-last-date">' . dol_print_date($entry['date'], '%d/%m/%y %H:%M') . '</span>';
    $out .= '<span class="reedcrm-timespent-avatar reedcrm-timespent-avatar-' . $color . '" title="' . dol_escape_htmltag($entry['login']) . '">' . dol_escape_htmltag(dol_strtoupper(dol_substr($entry['login'], 0, 1))) . '</span>';
    $out .= '<span class="reedcrm-timespent-last-duration">| ' . reedcrm_timespent_format_hours($entry['duration']) . '</span>';
    if ($note !== '') {
        $out .= '<span class="reedcrm-timespent-last-separator">|</span>';
        $out .= '<span class="reedcrm-timespent-last-note" title="' . dol_escape_htmltag($note) . '">' . dol_escape_htmltag($note) . '</span>';
    }
    $out .= '</div>';

    return $out;
}

/**
 * Tooltip of the recap of the block: the time spent split by user, and by task for a project
 *
 * @param  array  $summary Summary returned by reedcrm_timespent_get_summary()
 * @return string          HTML, to be escaped in the title attribute
 */
function reedcrm_timespent_render_recap_tooltip(array $summary): string
{
    global $langs;

    if (empty($summary['per_user'])) {
        return $langs->trans('ReedCRMNoTimeEntries');
    }

    $tooltip = '<b>' . $langs->trans('ReedCRMTimeSpentPerUser') . '</b><br>';
    foreach ($summary['per_user'] as $userTime) {
        $tooltip .= dol_escape_htmltag($userTime['name']) . ' : ' . reedcrm_timespent_format_hours($userTime['duration']) . '<br>';
    }
    if (!empty($summary['per_task'])) {
        $tooltip .= '<br><b>' . $langs->trans('ReedCRMTimeSpentPerTask') . '</b><br>';
        foreach ($summary['per_task'] as $taskTime) {
            $tooltip .= dol_escape_htmltag($taskTime['ref'] . ' - ' . dol_trunc($taskTime['label'], 40)) . ' : ' . reedcrm_timespent_format_hours($taskTime['duration']) . '<br>';
        }
    }

    return $tooltip;
}

/**
 * Recap of the total time spent, at the bottom of the block, against the workload planned on the tasks when there is one
 *
 * @param  array        $summary Summary returned by reedcrm_timespent_get_summary()
 * @param  CommonObject $object  Ticket or project
 * @return string                HTML
 */
function reedcrm_timespent_render_recap(array $summary, CommonObject $object): string
{
    global $langs;

    $out  = '<div class="reedcrm-timespent-recap classfortooltip" title="' . dol_escape_htmltag(reedcrm_timespent_render_recap_tooltip($summary), 1, 1, 'br,b') . '">';
    $out .= '<i class="far fa-clock"></i>';
    $out .= '<span class="reedcrm-timespent-recap-label">' . $langs->trans($object instanceof Project ? 'ReedCRMTimeSpentTotalProject' : 'ReedCRMTimeSpentTotalTicket') . '</span>';
    $out .= '<span class="reedcrm-timespent-recap-total">' . reedcrm_timespent_format_hours($summary['total']) . '</span>';
    $out .= '<span class="reedcrm-timespent-recap-planned"' . ($summary['planned'] > 0 ? '' : ' hidden') . '>' . ($summary['planned'] > 0 ? reedcrm_timespent_format_hours($summary['planned']) : '') . '</span>';
    $out .= '</div>';

    return $out;
}

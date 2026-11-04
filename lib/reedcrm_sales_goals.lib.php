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
 * \file    lib/reedcrm_sales_goals.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the goals of the sales team.
 */

/**
 * Goals a sales representative can be given, mapped to the constant holding the default of the whole team.
 *
 * The calls are counted per working day, everything else per month: a day is what a sales representative plans,
 * a month is what the management reviews. An empty goal means no goal, the dashboards then show the figure alone.
 */
const REEDCRM_SALES_GOALS = [
    'calls_per_day'           => 'REEDCRM_SALES_GOAL_CALLS_PER_DAY',
    'signed_per_month'        => 'REEDCRM_SALES_GOAL_SIGNED_PER_MONTH',
    'signed_amount_per_month' => 'REEDCRM_SALES_GOAL_SIGNED_AMOUNT_PER_MONTH',
    'opportunities_per_month' => 'REEDCRM_SALES_GOAL_OPPORTUNITIES_PER_MONTH'
];

/**
 * Default value of each goal, the ones the sales meeting of #694 settled on: about 30 calls a day and 3 signed
 * proposals a month.
 */
const REEDCRM_SALES_GOALS_DEFAULTS = [
    'calls_per_day'           => 30,
    'signed_per_month'        => 3,
    'signed_amount_per_month' => 0,
    'opportunities_per_month' => 0
];

/**
 * Get the translation key naming a goal, its description being the same key followed by "Description".
 *
 * @param  string $goal Goal key, one of REEDCRM_SALES_GOALS
 * @return string       Translation key, SalesGoalCallsPerDay for calls_per_day
 */
function reedcrm_sales_goal_label_key(string $goal): string
{
    return 'SalesGoal' . str_replace(' ', '', ucwords(str_replace('_', ' ', $goal)));
}

/**
 * Get the goals the whole sales team shares, before any personal goal.
 *
 * @return array<string,float> Value of each goal, 0 when the goal is not set
 */
function reedcrm_sales_goals_get_defaults(): array
{
    $goals = [];
    foreach (REEDCRM_SALES_GOALS as $goal => $constName) {
        // An admin clearing the field means "no goal", only a constant never saved falls back on the default
        $goals[$goal] = (float) getDolGlobalString($constName, (string) REEDCRM_SALES_GOALS_DEFAULTS[$goal]);
    }

    return $goals;
}

/**
 * Get the personal goals set for some sales representatives, without the team defaults.
 *
 * @return array<int,array<string,float>> Goals set for each user, keyed by user id
 */
function reedcrm_sales_goals_get_personal(): array
{
    $personalGoals = json_decode(getDolGlobalString('REEDCRM_SALES_GOALS_PER_USER'), true);
    if (!is_array($personalGoals)) {
        return [];
    }

    $goals = [];
    foreach ($personalGoals as $userId => $userGoals) {
        if ((int) $userId <= 0 || !is_array($userGoals)) {
            continue;
        }
        foreach (array_keys(REEDCRM_SALES_GOALS) as $goal) {
            if (isset($userGoals[$goal]) && $userGoals[$goal] !== '') {
                $goals[(int) $userId][$goal] = (float) $userGoals[$goal];
            }
        }
    }

    return $goals;
}

/**
 * Get the goals of a sales representative, a personal goal overriding the one of the team.
 *
 * @param  int                 $userId Id of the user, 0 for the team defaults alone
 * @return array<string,float>         Value of each goal, 0 when the goal is not set
 */
function reedcrm_sales_goals_get(int $userId = 0): array
{
    $goals = reedcrm_sales_goals_get_defaults();
    if ($userId <= 0) {
        return $goals;
    }

    $personalGoals = reedcrm_sales_goals_get_personal();

    return array_merge($goals, $personalGoals[$userId] ?? []);
}

/**
 * Save the goals of the team and the personal goals of its members.
 *
 * A personal goal equal to the one of the team is not stored: the member then follows the team default when it
 * changes, instead of keeping a copy of the old value.
 *
 * @param  DoliDB                         $db            Database handler
 * @param  array<string,string>           $defaultGoals  Value of each team goal, as typed in the form
 * @param  array<int,array<string,string>> $personalGoals Value of each personal goal, keyed by user id, as typed in the form
 * @return void
 */
function reedcrm_sales_goals_save(DoliDB $db, array $defaultGoals, array $personalGoals): void
{
    global $conf;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

    $defaults = [];
    foreach (REEDCRM_SALES_GOALS as $goal => $constName) {
        $value           = price2num($defaultGoals[$goal] ?? '');
        $defaults[$goal] = $value === '' ? 0 : (float) $value;
        dolibarr_set_const($db, $constName, (string) $defaults[$goal], 'chaine', 0, '', $conf->entity);
    }

    $toStore = [];
    foreach ($personalGoals as $userId => $userGoals) {
        if ((int) $userId <= 0 || !is_array($userGoals)) {
            continue;
        }
        foreach (array_keys(REEDCRM_SALES_GOALS) as $goal) {
            $value = price2num($userGoals[$goal] ?? '');
            if ($value === '' || (float) $value == $defaults[$goal]) {
                continue;
            }
            $toStore[(int) $userId][$goal] = (float) $value;
        }
    }

    dolibarr_set_const($db, 'REEDCRM_SALES_GOALS_PER_USER', json_encode($toStore), 'chaine', 0, '', $conf->entity);
}

/**
 * Get the members of the sales team.
 *
 * The team is the user group picked in the setup. Without one, the team is whoever has a sales activity, which the
 * dashboards work out from the figures themselves: an empty array is returned so they know there is no group.
 *
 * @param  DoliDB $db Database handler
 * @return int[]      Ids of the enabled members of the group, empty when no group is set
 */
function reedcrm_sales_team_get_user_ids(DoliDB $db): array
{
    $groupId = getDolGlobalInt('REEDCRM_SALES_TEAM_GROUP');
    if ($groupId <= 0) {
        return [];
    }

    $sql  = 'SELECT u.rowid';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'usergroup_user AS ug';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'user AS u ON u.rowid = ug.fk_user';
    $sql .= ' WHERE ug.fk_usergroup = ' . $groupId;
    $sql .= ' AND u.statut = 1';
    $sql .= ' AND ug.entity IN (' . getEntity('usergroup') . ')';

    $userIds = [];
    $resql   = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $userIds[] = (int) $obj->rowid;
        }
        $db->free($resql);
    }

    return array_values(array_unique($userIds));
}

/**
 * Midnight GMT of the calendar day a timestamp falls on, in the server time zone.
 *
 * num_open_day() and num_public_holiday() count whole days between GMT dates: two midnights of the server time zone
 * are not a multiple of 86400 s apart across a daylight saving change (October, March), and the core then returns an
 * error string instead of a number. A server midnight is also the day before in GMT, where the public holidays are
 * looked up.
 *
 * @param  int $timestamp Timestamp of any hour of the day
 * @return int            Midnight GMT of the same calendar day
 */
function reedcrm_sales_gmt_day(int $timestamp): int
{
    $date = dol_getdate($timestamp);

    return (int) dol_mktime(0, 0, 0, $date['mon'], $date['mday'], $date['year'], 'gmt');
}

/**
 * Count the working days between two dates, both included.
 *
 * Weekends and the public holidays of the company country are left out, so a goal set per day is not asked for on
 * a day nobody calls.
 *
 * @param  int $start Timestamp of the first day
 * @param  int $end   Timestamp of the last day
 * @return int        Number of working days, 0 when the range is empty
 */
function reedcrm_sales_count_working_days(int $start, int $end): int
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

    $start = reedcrm_sales_gmt_day($start);
    $end   = reedcrm_sales_gmt_day($end);
    if ($end < $start) {
        return 0;
    }

    $nbDays = num_open_day($start, $end, 0, 1);

    return is_numeric($nbDays) ? max(0, (int) $nbDays) : 0;
}

/**
 * Check whether a day is a working day.
 *
 * @param  int  $day Timestamp of any hour of the day
 * @return bool      True when the day is neither a weekend day nor a public holiday of the company country
 */
function reedcrm_sales_is_working_day(int $day): bool
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

    $day = reedcrm_sales_gmt_day($day);

    // The public holidays are cached by Dolibarr after the first call, checking a month day by day stays cheap
    return num_public_holiday($day, $day, '', 1) == 0;
}

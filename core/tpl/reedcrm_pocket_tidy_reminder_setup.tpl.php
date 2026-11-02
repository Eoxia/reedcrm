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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/reedcrm_pocket_tidy_reminder_setup.tpl.php
 * \ingroup reedcrm
 * \brief   Daily reminder to file the Pocket calls: time, next run, scheduled job and recipients.
 *          Rendered by admin/pocket.php (inherits its scope), the form is run by its set_pocket_tidy_reminder action.
 *
 * Expects:
 *  - $pocketAccount (PocketAccount) Shared account of the setup.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $db, $form, $langs;

require_once __DIR__ . '/../../class/pocketcron.class.php';

$tidyReminderTime = getDolGlobalString(PocketCron::TIDY_REMINDER_TIME);

// The job runs every quarter of an hour, a finer time would only be honoured at the next quarter
$tidyReminderOptions = ['' => $langs->trans('PocketTidyReminderOff')];
for ($minutes = 0; $minutes < 1440; $minutes += 15) {
    $quarter                       = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    $tidyReminderOptions[$quarter] = $quarter;
}
if ($tidyReminderTime !== '' && !isset($tidyReminderOptions[$tidyReminderTime])) {
    $tidyReminderOptions[$tidyReminderTime] = $tidyReminderTime;
}

// The scheduled job is only declared by the activation of the module
$tidyReminderJob = null;
$sql  = 'SELECT rowid, status FROM ' . MAIN_DB_PREFIX . 'cronjob';
$sql .= " WHERE classesname = '/reedcrm/class/pocketcron.class.php' AND methodename = 'createTidyReminders'";
$sql .= ' AND entity = ' . ((int) $conf->entity);
$resql = $db->query($sql);
if ($resql) {
    $tidyReminderJob = $db->fetch_object($resql) ?: null;
    $db->free($resql);
}

print load_fiche_titre($langs->trans('PocketTidyReminderTitle'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set_pocket_tidy_reminder">';
print '<tr class="oddeven">';
print '<td>' . $form->textwithpicto($langs->trans('PocketTidyReminderTime'), $langs->trans('PocketTidyReminderTimeHelp')) . '</td>';
print '<td>' . $form->selectarray('pocket_tidy_reminder_time', $tidyReminderOptions, $tidyReminderTime, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150', 1) . '</td>';
print '<td class="center"><input type="submit" class="button" value="' . $langs->trans('Save') . '"></td>';
print '</tr>';
print '</form>';

if ($tidyReminderTime !== '') {
    $now          = dol_now();
    $todayCheck   = (int) PocketCron::getTidyReminderDate($now);
    $nextReminder = getDolGlobalString(PocketCron::TIDY_REMINDER_LAST_DAY) === PocketCron::formatTidyReminderDate($todayCheck, '%Y-%m-%d') ? (int) PocketCron::getTidyReminderDate($now, 1) : $todayCheck;

    print '<tr class="oddeven">';
    print '<td>' . $langs->trans('PocketTidyReminderNext') . '</td>';
    print '<td colspan="2">' . PocketCron::formatTidyReminderDate($nextReminder) . ' <span class="opacitymedium">(' . dol_escape_htmltag(PocketCron::getTidyReminderTimezone()->getName()) . ')</span>';
    print ($nextReminder < $now ? ' <span class="opacitymedium">' . $langs->trans('PocketTidyReminderPending') . '</span>' : '') . '</td>';
    print '</tr>';

    // Who the reminder of the shared account goes to, the personal accounts each remind their owner
    if ($pocketAccount->isConfigured()) {
        $recipientLinks = [];
        $recipient      = new User($db);
        foreach ($pocketAccount->getUserIds() as $recipientId) {
            if ($recipient->fetch($recipientId) > 0) {
                $recipientLinks[] = $recipient->getNomUrl(-1);
            }
        }

        print '<tr class="oddeven">';
        print '<td>' . $form->textwithpicto($langs->trans('PocketTidyReminderSharedRecipients'), $langs->trans('PocketTidyReminderSharedRecipientsHelp')) . '</td>';
        print '<td colspan="2">' . (!empty($recipientLinks) ? implode(' ', $recipientLinks) : '<span class="opacitymedium">' . $langs->trans('PocketTidyReminderNoRecipient') . '</span>') . '</td>';
        print '</tr>';
    }

    print '<tr class="oddeven">';
    print '<td>' . $langs->trans('PocketTidyReminderJob') . '</td>';
    print '<td colspan="2">';
    if ($tidyReminderJob === null) {
        print '<span class="error">' . $langs->trans('PocketTidyReminderJobMissing') . '</span>';
    } elseif (!isModEnabled('cron')) {
        print '<span class="error">' . $langs->trans('PocketTidyReminderCronDisabled') . '</span>';
    } else {
        print '<a href="' . DOL_URL_ROOT . '/cron/card.php?id=' . ((int) $tidyReminderJob->rowid) . '">' . $langs->trans('PocketTidyReminderCronLabel') . '</a> ';
        print empty($tidyReminderJob->status) ? '<span class="badge badge-status8 badge-status">' . $langs->trans('Disabled') . '</span>' : '<span class="badge badge-status4 badge-status">' . $langs->trans('Enabled') . '</span>';
    }
    print '</td>';
    print '</tr>';
}

print '</table>';

if ($tidyReminderTime !== '' && !getDolGlobalString('AGENDA_REMINDER_BROWSER')) {
    print info_admin($langs->trans('PocketTidyReminderNoBrowserNotification', DOL_URL_ROOT . '/admin/agenda_reminder.php'), 0, 0, 'warning', '');
}

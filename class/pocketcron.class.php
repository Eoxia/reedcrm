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
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

/**
 * \file    class/pocketcron.class.php
 * \ingroup reedcrm
 * \brief   Scheduled jobs of Pocket: import of the recordings, reminder to file the calls.
 */

require_once __DIR__ . '/pocketsync.class.php';

/**
 * Class holding the Pocket scheduled jobs.
 */
class PocketCron
{
    /**
     * @var string Code of the events reminding to file the calls.
     */
    public const TIDY_REMINDER_CODE = 'AC_REEDCRM_POCKET_TIDY';

    /**
     * @var string Constant holding the time of the daily reminder to file the calls, HH:MM, unset when disabled.
     */
    public const TIDY_REMINDER_TIME = 'REEDCRM_POCKET_TIDY_REMINDER_TIME';

    /**
     * @var string Constant holding the timezone the time was set in, the one of the browser of the admin.
     */
    public const TIDY_REMINDER_TZ = 'REEDCRM_POCKET_TIDY_REMINDER_TZ';

    /**
     * @var string Constant holding the last day every account was checked for calls to file, Y-m-d.
     */
    public const TIDY_REMINDER_LAST_DAY = 'REEDCRM_POCKET_TIDY_REMINDER_LAST_DAY';

    /**
     * @var int Number of recordings listed in the note of a reminder, the label still counts them all.
     */
    private const TIDY_REMINDER_MAX_LINES = 30;

    /**
     * @var DoliDB Database handler.
     */
    public DoliDB $db;

    /**
     * @var string Output produced by the last job run (read by the cron manager).
     */
    public string $output = '';

    /**
     * @var string Error read by the cron manager when a job fails.
     */
    public string $error = '';

    /**
     * @var string[] Errors read by the cron manager when a job fails.
     */
    public array $errors = [];

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Job: mirror the recordings of every configured Pocket account into ReedCRM.
     *
     * @return int 0 if OK, < 0 if KO.
     */
    public function syncPocketRecordings(): int
    {
        global $langs, $user;

        $langs->loadLangs(['reedcrm@reedcrm']);

        $outcome = PocketSync::syncAllAccounts($this->db, $user);
        $report  = $outcome['report'];

        // Nothing configured is not a failure: the job is declared on every install
        if ($outcome['accounts'] == 0) {
            $this->output = $langs->trans('PocketNoAccountConfigured');
            return 0;
        }

        // trans() takes four values at most, the number of accounts gets a sentence of its own
        $this->output = $langs->trans('PocketSyncAccounts', $outcome['accounts']) . ' - ' . $langs->trans('PocketSyncReport', $report['created'], $report['updated'], $report['skipped'], $report['errors']);
        if (!empty($outcome['errors'])) {
            $this->output .= "\n" . implode("\n", $outcome['errors']);
        }

        return ($report['errors'] > 0 || !empty($outcome['errors'])) ? -1 : 0;
    }

    /**
     * Job: once a day at the time of the setup, remind the users of each account to file their calls.
     *
     * A call left out of the folders is picked up by neither the "I just hung up" button nor the
     * synchronisation. For every configured account holding recordings that reached Pocket during the
     * last 24 hours and still sit in no folder, an event listing them is created at the time of the
     * setup, with a browser notification for each user of the account. Nothing is created when
     * everything is filed.
     *
     * The job runs every quarter of an hour and does nothing until the time is reached. The day is
     * marked done once every account was read: an account the API failed on is tried again on the
     * next run, the accounts already reminded are recognised by their event.
     *
     * @return int 0 if OK, < 0 if KO.
     */
    public function createTidyReminders(): int
    {
        global $conf, $langs;

        $langs->loadLangs(['reedcrm@reedcrm']);

        $now          = dol_now();
        $reminderDate = self::getTidyReminderDate($now);
        if ($reminderDate === null) {
            $this->output = $langs->transnoentities('PocketTidyReminderDisabled');
            return 0;
        }

        $day = self::formatTidyReminderDate($reminderDate, '%Y-%m-%d');
        if (getDolGlobalString(self::TIDY_REMINDER_LAST_DAY) === $day) {
            $this->output = $langs->transnoentities('PocketTidyReminderNextRun', self::formatTidyReminderDate((int) self::getTidyReminderDate($now, 1)));
            return 0;
        }
        if ($now < $reminderDate) {
            $this->output = $langs->transnoentities('PocketTidyReminderNextRun', self::formatTidyReminderDate($reminderDate));
            return 0;
        }

        // The period runs from the reminder of the day before: a recording reaching Pocket just after
        // a reminder is listed by the next one
        $since = (int) self::getTidyReminderDate($now, -1);

        $accounts = 0;
        $created  = 0;
        $errors   = [];
        foreach (PocketAccount::getAllConfigured($this->db) as $account) {
            $accounts++;

            $userIds = $account->getUserIds();
            if (empty($userIds) || $this->tidyReminderExists($userIds[0], $reminderDate)) {
                continue;
            }

            $pocketSync = new PocketSync($this->db, $account);
            $recordings = $pocketSync->findUnfiledRecordings($since, $reminderDate);
            if ($recordings === null) {
                $errors[] = $account->getName() . ' : ' . $pocketSync->error;
                continue;
            }

            if (empty($recordings)) {
                continue;
            }

            if ($this->createTidyReminder($account, $userIds, $recordings, $since, $reminderDate) > 0) {
                $created++;
            } else {
                $errors[] = $account->getName() . ' : ' . $this->error;
            }
        }

        if (empty($errors)) {
            require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
            dolibarr_set_const($this->db, self::TIDY_REMINDER_LAST_DAY, $day, 'chaine', 0, '', $conf->entity);
        }

        $this->output = $langs->transnoentities('PocketTidyReminderCronResult', (string) $created, (string) $accounts);
        if (!empty($errors)) {
            $this->errors = $errors;
            $this->output .= "\n" . implode("\n", $errors);
        }

        return empty($errors) ? 0 : -1;
    }

    /**
     * Get the timezone the time of the reminder is read in.
     *
     * The one of the browser of the admin who set the time: servers often run in UTC, and a reminder
     * set at 18:00 from Paris would otherwise ring at 20:00. The timezone of the server when none was
     * recorded.
     *
     * @return DateTimeZone Timezone of the reminder.
     */
    public static function getTidyReminderTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone(getDolGlobalString(self::TIDY_REMINDER_TZ) ?: date_default_timezone_get());
        } catch (Exception $e) {
            return new DateTimeZone(date_default_timezone_get());
        }
    }

    /**
     * Get the date of the reminder to file the calls on the day of a date.
     *
     * @param  int      $time      Date giving the day.
     * @param  int      $dayOffset Number of days to move from that day, -1 for the day before.
     * @return int|null            Date of the reminder, null when the reminder is disabled.
     */
    public static function getTidyReminderDate(int $time, int $dayOffset = 0): ?int
    {
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', getDolGlobalString(self::TIDY_REMINDER_TIME), $matches)) {
            return null;
        }

        $date = new DateTime('@' . $time);
        $date->setTimezone(self::getTidyReminderTimezone());
        if ($dayOffset != 0) {
            $date->modify(sprintf('%+d day', $dayOffset));
        }
        $date->setTime((int) $matches[1], (int) $matches[2]);

        return $date->getTimestamp();
    }

    /**
     * Print a date in the timezone of the reminder.
     *
     * @param  int            $time        Date to print.
     * @param  string         $format      Format of dol_print_date().
     * @param  Translate|null $outputLangs Translations of the month and day names, the current ones when null.
     * @return string                      Printed date.
     */
    public static function formatTidyReminderDate(int $time, string $format = 'dayhour', ?Translate $outputLangs = null): string
    {
        // dol_print_date() only knows the timezones of the server and of the user: the offset of the
        // reminder one is added to a date printed as GMT
        $offset = self::getTidyReminderTimezone()->getOffset(new DateTime('@' . $time));

        return dol_print_date($time + $offset, $format, 'gmt', $outputLangs);
    }

    /**
     * Tell whether the reminder of the day was already created for an account.
     *
     * @param  int  $ownerId      Owner of the reminder, the first user of the account.
     * @param  int  $reminderDate Date of the reminder of the day.
     * @return bool               True when the reminder exists, or when the check failed.
     */
    private function tidyReminderExists(int $ownerId, int $reminderDate): bool
    {
        global $conf;

        $sql  = 'SELECT COUNT(*) as nb FROM ' . MAIN_DB_PREFIX . 'actioncomm';
        $sql .= " WHERE code = '" . $this->db->escape(self::TIDY_REMINDER_CODE) . "'";
        $sql .= ' AND fk_user_action = ' . $ownerId;
        $sql .= " AND datep = '" . $this->db->idate($reminderDate) . "'";
        $sql .= ' AND entity = ' . ((int) $conf->entity);

        $resql = $this->db->query($sql);
        if (!$resql) {
            dol_syslog(__METHOD__ . ': ' . $this->db->lasterror(), LOG_ERR);
            // On a failed check, hold the creation back rather than risk a duplicate
            return true;
        }

        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $obj && $obj->nb > 0;
    }

    /**
     * Create the event reminding the users of an account to file their calls.
     *
     * @param  PocketAccount                  $account      Account the recordings come from.
     * @param  int[]                          $userIds      Users of the account, the first one owns the event.
     * @param  array<int,array<string,mixed>> $recordings   Recordings to file, as returned by the list endpoint.
     * @param  int                            $since        Start of the period the recordings reached Pocket in.
     * @param  int                            $reminderDate Date of the reminder.
     * @return int                                          Id of the event, < 0 on error.
     */
    private function createTidyReminder(PocketAccount $account, array $userIds, array $recordings, int $since, int $reminderDate): int
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncommreminder.class.php';
        require_once __DIR__ . '/../lib/reedcrm_pocketrecording.lib.php';

        $outputLangs  = $this->getOutputLangs($userIds[0]);
        $nbRecordings = count($recordings);

        $lines = [];
        foreach (array_slice($recordings, 0, self::TIDY_REMINDER_MAX_LINES) as $recording) {
            $recordingDate = (int) dol_stringtotime((string) ($recording['recording_at'] ?? $recording['created_at'] ?? ''));
            $duration      = reedcrm_pocket_format_duration((int) ($recording['duration'] ?? 0));
            $title         = trim((string) ($recording['title'] ?? '')) ?: $outputLangs->transnoentities('PocketTidyReminderUntitled');

            $lines[] = '<li>' . self::formatTidyReminderDate($recordingDate, 'dayhour', $outputLangs) . ($duration !== '' ? ' (' . $outputLangs->transnoentities('PocketTidyReminderDuration', $duration) . ')' : '') . ' - ' . dol_escape_htmltag($title) . '</li>';
        }
        if ($nbRecordings > self::TIDY_REMINDER_MAX_LINES) {
            $lines[] = '<li>' . $outputLangs->transnoentities('PocketTidyReminderMore', (string) ($nbRecordings - self::TIDY_REMINDER_MAX_LINES)) . '</li>';
        }

        $note  = '<p>' . $outputLangs->transnoentities('PocketTidyReminderNote', self::formatTidyReminderDate($since, 'dayhour', $outputLangs), dol_escape_htmltag($account->folderLabel ?: $account->folderId)) . '</p>';
        $note .= '<ul>' . implode('', $lines) . '</ul>';

        $this->db->begin();

        $event               = new ActionComm($this->db);
        $event->type_code    = 'AC_OTH';
        $event->code         = self::TIDY_REMINDER_CODE;
        $event->label        = $outputLangs->transnoentities($nbRecordings > 1 ? 'PocketTidyReminderLabelPlural' : 'PocketTidyReminderLabel', (string) $nbRecordings);
        $event->note_private = $note;
        $event->datep        = $reminderDate;
        $event->percentage   = 0;
        $event->userownerid  = $userIds[0];
        // One event for the whole account: whoever files the calls marks it done for everyone
        foreach ($userIds as $userId) {
            $event->userassigned[$userId] = ['id' => $userId, 'transparency' => 0];
        }

        $eventId = $event->create($user);
        if ($eventId <= 0) {
            $this->error = $event->error . ' ' . implode(', ', $event->errors);
            dol_syslog(__METHOD__ . ': ' . $this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }

        // Without the browser reminders of the agenda, no page would ever pick the notification up
        if (getDolGlobalString('AGENDA_REMINDER_BROWSER')) {
            foreach ($userIds as $userId) {
                $reminder                = new ActionCommReminder($this->db);
                $reminder->dateremind    = $reminderDate;
                $reminder->typeremind    = 'browser';
                $reminder->offsetvalue   = 0;
                $reminder->offsetunit    = 'i';
                $reminder->fk_actioncomm = $eventId;
                $reminder->fk_user       = $userId;
                $reminder->status        = ActionCommReminder::STATUS_TODO;

                if ($reminder->create($user) <= 0) {
                    $this->error = $reminder->error . ' ' . implode(', ', $reminder->errors);
                    dol_syslog(__METHOD__ . ': ' . $this->error, LOG_ERR);
                    $this->db->rollback();
                    return -1;
                }
            }
        }

        $this->db->commit();

        return $eventId;
    }

    /**
     * Get the language the reminder of a user is written in.
     *
     * A scheduled job has no browser: with the default language left on "auto", $langs falls back on
     * English. The language of the user comes first, then the default one of the instance.
     *
     * @param  int       $userId User the reminder is written for.
     * @return Translate         Translations to write the reminder with.
     */
    private function getOutputLangs(int $userId): Translate
    {
        global $conf, $langs;

        $langCode = '';

        $recipient = new User($this->db);
        if ($recipient->fetch($userId) > 0 && !empty($recipient->lang)) {
            $langCode = (string) $recipient->lang;
        } elseif (!in_array(getDolGlobalString('MAIN_LANG_DEFAULT'), ['', 'auto'], true)) {
            $langCode = getDolGlobalString('MAIN_LANG_DEFAULT');
        }

        if ($langCode === '' || $langCode === $langs->getDefaultLang()) {
            return $langs;
        }

        $outputLangs = new Translate('', $conf);
        $outputLangs->setDefaultLang($langCode);
        $outputLangs->loadLangs(['main', 'reedcrm@reedcrm']);

        return $outputLangs;
    }
}

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
 * \brief   Scheduled import of the Pocket recordings.
 */

require_once __DIR__ . '/pocketsync.class.php';

/**
 * Class holding the Pocket scheduled jobs.
 */
class PocketCron
{
    /**
     * @var DoliDB Database handler.
     */
    public DoliDB $db;

    /**
     * @var string Output produced by the last job run (read by the cron manager).
     */
    public string $output = '';

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
}

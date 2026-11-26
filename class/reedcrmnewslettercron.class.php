<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * \file    class/reedcrmnewslettercron.class.php
 * \ingroup reedcrm
 * \brief   Cron job sending the scheduled newsletters and mail reminders
 */

require_once __DIR__ . '/reedcrmnewsletter.class.php';

/**
 * Class ReedcrmNewsletterCron
 */
class ReedcrmNewsletterCron
{
    /**
     * @var DoliDB Database handler
     */
    public DoliDB $db;

    /**
     * @var string Last output from end job execution
     */
    public string $output = '';

    /**
     * @var string Last error
     */
    public string $error = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Job: send the newsletters and the reminders whose date has come, the oldest first. At most
     * REEDCRM_NEWSLETTER_MAX_PER_RUN mails per run, all newsletters together: the rest leaves at the next run
     *
     * @return int 0 if OK, < 0 if KO
     */
    public function sendScheduledNewsletters(): int
    {
        global $conf, $langs, $user;

        $langs->loadLangs(['main', 'mails', 'reedcrm@reedcrm']);

        // Due, or started before its date by "Send now": its other recipients are served as for a due one
        $sql  = 'SELECT n.rowid FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter as n';
        $sql .= ' WHERE n.entity = ' . ((int) $conf->entity);
        $sql .= ' AND n.status = ' . ReedcrmNewsletter::STATUS_SCHEDULED;
        $sql .= " AND n.date_send IS NOT NULL AND (n.date_send <= '" . $this->db->idate(dol_now()) . "'";
        $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send as ns WHERE ns.fk_newsletter = n.rowid AND ns.occurrence = n.date_send))';
        $sql .= ' ORDER BY n.date_send, n.rowid';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error  = $this->db->lasterror();
            $this->output = $this->error;
            return -1;
        }
        $ids = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $ids[] = (int) $obj->rowid;
        }
        $this->db->free($resql);

        $budget = max(1, getDolGlobalInt('REEDCRM_NEWSLETTER_MAX_PER_RUN', 100));
        $lines  = [];
        foreach ($ids as $id) {
            if ($budget <= 0) {
                break;
            }

            $newsletter = new ReedcrmNewsletter($this->db);
            if ($newsletter->fetch($id) <= 0) {
                continue;
            }

            // In the language of the author (or of the company): the job runs in the one of the server
            $result  = $newsletter->sendBatch($user, $budget, $newsletter->getMailLangs($langs));
            $budget -= $result['sent'] + $result['errors'];
            $lines[] = $langs->transnoentities('NewsletterCronLine', $newsletter->ref, $result['sent'], $result['errors'], $result['remaining']);
        }

        $this->output = empty($lines) ? $langs->transnoentities('NewsletterCronNothing') : implode("\n", $lines);

        return 0;
    }
}

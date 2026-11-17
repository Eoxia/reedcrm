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
 * \file    lib/reedcrm_ticket_time.lib.php
 * \ingroup reedcrm
 * \brief   Time logged on a ticket : one task "<prefix> <suffix>" in the project of the ticket, plus an agenda event.
 *          Used by the money banner and the technician screen; the quick time block of the ticket and project cards
 *          goes through the same reedcrm_timespent_add().
 */

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once __DIR__ . '/reedcrm_timespent.lib.php';

/**
 * Log time on a ticket
 *
 * @param  DoliDB $db      Database handler
 * @param  User   $user    User who spent the time
 * @param  Ticket $ticket  Ticket
 * @param  int    $minutes Time spent, in minutes
 * @param  string $note    Comment of the time line
 * @return string          Empty if OK, error message if KO
 * @throws Exception
 */
function reedcrm_ticket_log_time(DoliDB $db, User $user, Ticket $ticket, int $minutes, string $note = ''): string
{
    global $langs;

    if ($minutes <= 0) {
        $langs->load('reedcrm@reedcrm');

        return $langs->transnoentities('ReedCRMTimeSpentInvalidMinutes');
    }

    return reedcrm_timespent_add($db, $user, $ticket, $minutes, $note);
}

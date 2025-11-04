<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    ajax/check_call_events.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint to check for new call events
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '0');
}

// Load main Dolibarr environment
if (file_exists(__DIR__ . '/../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../saturne/saturne.main.inc.php';
} elseif (file_exists(__DIR__ . '/../../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../../saturne/saturne.main.inc.php';
} else {
    die('Include of saturne main fails');
}

require_once __DIR__ . '/../lib/reedcrm_function.lib.php';

// Load translation files required by the page
$langs->loadLangs(array("reedcrm@reedcrm"));

/*
 * View
 */

top_httphead('application/json');

global $user, $db, $langs, $conf;

$eventfound = array();

if ($user->id > 0) {
    // Get pending call events for current user
    $events = get_pending_call_events($user->id);

    foreach ($events as $event) {

        $contact_url = dol_buildpath('/reedcrm/view/procard.php?from_id=' . $event->fk_soc . '&from_type=societe', 1);

        $eventfound[] = array(
            'type' => 'call',
            'id' => $event->rowid,
            'id_contact' => $event->fk_contact,
            'label' => $langs->trans("IncomingCall") . ': ' . $event->firstname . ' ' . $event->lastname,
            'caller' => $event->caller,
            'callee' => $event->callee,
            'call_date' => dol_print_date($event->call_date, 'dayhour'),
            'contact_name' => $event->firstname . ' ' . $event->lastname,
            'contact_phone' => $event->phone ?: $event->phone_mobile,
            'contact_email' => $event->email,
            'url' => $contact_url,
            'icon' => 'phone'
        );

        // Mark event as processed
        mark_call_event_processed($event->rowid);
    }
}

// Output JSON response
print json_encode($eventfound);

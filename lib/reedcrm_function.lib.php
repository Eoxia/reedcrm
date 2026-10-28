<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
* \file    lib/reedcrm_function.lib.php
* \ingroup reedcrm
* \brief   Library files with common functions for ReedCRM
*/

/**
 * Set notation object contact
 *
 * @param  CommonObject $object Object
 * @return int                  -1 = error, O = did nothing, 1 = OK
 * @throws Exception
 */
function set_notation_object_contact(CommonObject $object): int
{
    $notationObjectContacts = get_notation_object_contacts($object);
    $notationObjectContact  = array_shift($notationObjectContacts);
    $object->fetch_optionals();
    $percentage = is_array($notationObjectContact) && isset($notationObjectContact['percentage']) ? $notationObjectContact['percentage'] : 0;
    $object->array_options['options_notation_' . $object->element . '_contact'] = ($percentage ?: 0) . ' %';
    return $object->updateExtraField('notation_' . $object->element . '_contact');
}

/**
 * Get notation object contacts
 *
 * @param  CommonObject $object                 Object
 * @param  string       $haveRole               Object contacts presence role
 * @return array        $notationObjectContacts Multidimensional associative array
 * @throws Exception
 */
function get_notation_object_contacts(CommonObject $object, string $haveRole = ''): array
{
    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
    require_once __DIR__ . '/../../saturne/lib/object.lib.php';

    $notationObjectContacts = [];
    $contacts               = saturne_fetch_all_object_type('Contact', '', '', 0, 0, ['customsql' => 't.fk_soc = ' . ($object->element == 'societe' ? $object->id : ($object->fk_soc > 0 ? $object->fk_soc : $object->socid))]);
    if (is_array($contacts) && !empty($contacts)) {
        foreach ($contacts as $contact) {
            $contact->fetchRoles();
            $notationObjectContacts[$contact->id]['lastname']     = dol_strlen($contact->lastname) > 0 ? 5 : 0;
            $notationObjectContacts[$contact->id]['firstname']    = dol_strlen($contact->firstname) > 0 ? 5 : 0;
            $notationObjectContacts[$contact->id]['phone']        = dol_strlen($contact->phone) > 0 ? 5 : 0;
            $notationObjectContacts[$contact->id]['phone_mobile'] = dol_strlen($contact->phone_mobile) > 0 ? 5 : 0;
            $notationObjectContacts[$contact->id]['email']        = dol_strlen($contact->email) > 0 ? 40 : 0;

            $checkRolesArray  = in_array('facture', array_column($contact->roles, 'element'));
            $checkRolesArray += in_array('external', array_column($contact->roles, 'source'));
            $checkRolesArray += in_array('BILLING', array_column($contact->roles, 'code'));
            $notationObjectContacts[$contact->id]['role'] = $checkRolesArray == 3 ? 40 : 0;

            $percentage = 0;
            foreach ($notationObjectContacts[$contact->id] as $notationObjectContactsField) {
                $percentage += $notationObjectContactsField;
            }

            $notationObjectContacts[$contact->id]['percentage'] = price2num($percentage, 'MT', 1);
            if ($haveRole == 'facture_external_BILLINGS' && $checkRolesArray != 3) {
                unset($notationObjectContacts[$contact->id]);
            }
        }
        uasort($notationObjectContacts, 'compareByPercentage');
    }
    return $notationObjectContacts;
}

/**
 * The function compares two elements using the value of the 'percentage' key
 * It is designed to be used with sort functions such as usort() or uasort()
 *
 * @param  array $first  First element
 * @param  array $second Second element
 *
 * @return int           Returns an integer indicating the comparison relationship between the two elements
 */
function compareByPercentage(array $first, array $second): int
{
    if ($first['percentage'] === $second['percentage']) {
        return 0;
    }
    return ($first['percentage'] > $second['percentage']) ? -1 : 1;
}

/**
 * Load dictionary from database
 *
 * @param  string    $tableName SQL table name
 * @param  string    $moreWhere More SQl filter
 * @return int|array            0 < if KO, array of records if OK
 */
function reedcrm_fetch_dictionary(string $tableName, string $moreWhere = '')
{
    global $db;

    $sql  = 'SELECT *';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . $tableName . ' as t';
    $sql .= ' WHERE 1 = 1';
    if ($moreWhere) {
        $sql .= $moreWhere;
    }

    $resql = $db->query($sql);
    if ($resql) {
        $num     = $db->num_rows($resql);
        $i       = 0;
        $records = [];
        while ($i < $num) {
            $obj = $db->fetch_object($resql);

            $records[$obj->rowid] = $obj;

            $i++;
        }

        $db->free($resql);

        return $records;
    } else {
        return -1;
    }
}


function _normalize_phone(string $s): string {
    // Garde chiffres et + uniquement
    $s = preg_replace('~[^0-9+]~', '', $s ?? '');
    // Optionnel : transformer +33X... en 0X... (à activer si tu veux un match strict FR)
    // if (strpos($s, '+33') === 0) $s = '0'.substr($s, 3);
    return $s;
}

function _phone_tail(string $s, int $len = 9): string {
    $s = _normalize_phone($s);
    $s = ltrim($s, '+');               // retire + pour éviter faux négatifs
    return substr($s, -$len);          // fins de numéro robustes
}


function get_and_show_contact(string $caller, string $callee): array
{
    global $db, $user, $langs;
    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
    require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
    require_once __DIR__ . '/../../saturne/lib/object.lib.php';

    $contact = new Contact($db);
    $userCalled = new User($db);
    $result = ['user' => null, 'contact' => null, 'call_event_id' => null];

    $callerTail = _phone_tail($caller);
    $calleeTail = _phone_tail($callee);

    log_to_file("Searching for user with phone ending: " . $calleeTail);
    log_to_file("Searching for contact with phone ending: " . $callerTail);

    // Recherche de l'utilisateur appelé
    $userMatches = saturne_fetch_all_object_type('User', '', '', 0, 0, ['customsql' => 'office_phone LIKE "%'.$calleeTail.'" OR personal_mobile LIKE "%'.$calleeTail.'" OR user_mobile LIKE "%'.$calleeTail.'"'] );

    // Recherche du contact appelant
    $contactMatches = saturne_fetch_all_object_type('Contact', '', '', 0, 0, ['customsql' => 'phone LIKE "%'.$callerTail.'" OR phone_mobile LIKE "%'.$callerTail.'"' ] );

    if (is_array($userMatches) && !empty($userMatches)) {
        $userMatch = array_shift($userMatches);
        $userCalled->fetch($userMatch->id);
        $result['user'] = $userCalled;
        log_to_file("Found user: " . $userCalled->login . " (ID: " . $userCalled->id . ")");
    }

    if (is_array($contactMatches) && !empty($contactMatches)) {
        $contactMatch = array_shift($contactMatches);
        $contact->fetch($contactMatch->id);
        $result['contact'] = $contact;
        log_to_file("Found contact: " . $contact->getFullName($langs) . " (ID: " . $contact->id . ")");
    }

    // Sans utilisateur appelé, l'événement n'aurait pas de propriétaire : personne ne le verrait
    if (empty($result['user'])) {
        log_to_file("No user found for the called number, no call event stored");
        return $result;
    }

    $recentCallEventId = reedcrm_get_recent_call_event_id((int) $result['user']->id, $caller);
    if ($recentCallEventId > 0) {
        $result['call_event_id'] = $recentCallEventId;
        log_to_file("Same call as event ID: " . $recentCallEventId . ", no new call event stored");
        return $result;
    }

    $call_event_id = store_call_event($result['user']->id, $result['contact'] ? $result['contact']->id : 0, $caller, $callee);
    $result['call_event_id'] = $call_event_id;
    log_to_file("Stored call event with ID: " . $call_event_id . ($result['contact'] ? '' : " for contact ID 0 (no contact found)"));

    return $result;
}

/**
 * Find the call event a Keyyo notification belongs to.
 *
 * Keyyo notifies one call several times: once per call state (SETUP, CONNECT, RELEASE) when the
 * notification URL does not filter on the type, and once per ringing line of the same user. A
 * call event of the same caller created for that user a few minutes ago is that same call.
 *
 * @param  int    $userId Row ID of the called user
 * @param  string $caller Caller number, as sent by Keyyo
 * @return int            Row ID of the call event, 0 when the notification is a new call
 */
function reedcrm_get_recent_call_event_id(int $userId, string $caller): int
{
    global $db;

    if ($caller === '') {
        return 0;
    }

    $delay = getDolGlobalInt('REEDCRM_KEYYO_SAME_CALL_DELAY', 5) * 60;

    // The caller is matched as a bare string: extraparams is JSON encoded twice by ActionComm
    $sql  = 'SELECT a.id FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
    $sql .= " WHERE a.code = 'AC_TEL'";
    $sql .= ' AND a.entity IN (' . getEntity('agenda') . ')';
    $sql .= ' AND a.fk_user_action = ' . $userId;
    $sql .= " AND a.datec >= '" . $db->idate(dol_now() - $delay) . "'";
    $sql .= " AND a.extraparams LIKE '%" . $db->escape($db->escapeforlike($caller)) . "%'";
    $sql .= ' ORDER BY a.id DESC';
    $sql .= $db->plimit(1);

    $resql = $db->query($sql);
    if (!$resql) {
        return 0;
    }
    $obj = $db->fetch_object($resql);
    $db->free($resql);

    return $obj ? (int) $obj->id : 0;
}

/**
 * Stocker l'événement d'appel en base de données via ActionComm
 */
function store_call_event($user_id, $contact_id, $caller, $callee) {
    global $db, $user, $langs, $conf;
    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

    $contact = new Contact($db);
    $contact_socid = 0;

    if ($contact_id > 0) {
        $contact->fetch($contact_id);
        $contact_name = $contact->getFullName($langs);
        $contact_socid = $contact->fk_soc;
    } else {
        $contact_name = $langs->trans("UnknownContact");
    }

    $actioncomm = new ActionComm($db);
    $actioncomm->type_code = 'AC_TEL';
    $actioncomm->label = $langs->trans("IncomingCall") . ' - ' . $contact_name;
    $actioncomm->datep = dol_now();
    $actioncomm->datef = dol_now();
    $actioncomm->percentage = 0;
    $actioncomm->userownerid = $user_id;
    $actioncomm->fk_user_action = $user_id;
    $actioncomm->contact_id = $contact_id;
    $actioncomm->socid = $contact_socid;

    $call_data = [
        'caller' => $caller,
        'callee' => $callee,
        'call_date' => dol_print_date(dol_now(), 'dayhour')
    ];
    $actioncomm->note_private = "Appel téléphonique entrant\n";
    $actioncomm->note_private .= "De: " . $caller . "\n";
    $actioncomm->note_private .= "Vers: " . $callee . "\n";
    $actioncomm->note_private .= "Date: " . $call_data['call_date'];

    $actioncomm->extraparams = json_encode($call_data);

    $savedUser = null;
    if (empty($user) || $user->id <= 0) {
        $savedUser = $user;
        $user = new User($db);
        $user->fetch($user_id);
    }

    $result = $actioncomm->create($user);

    if ($savedUser !== null) {
        $user = $savedUser;
    }

    if ($result > 0) {
        log_to_file("Created ActionComm ID: " . $actioncomm->id);
        return $actioncomm->id;
    } else {
        log_to_file('Error creating ActionComm: ' . $actioncomm->error);
        return false;
    }
}

/**
 * Récupérer les événements d'appel non traités pour un utilisateur depuis ActionComm
 */
function get_pending_call_events($user_id) {
    global $db;

    $sql = "SELECT a.id as rowid, a.fk_contact, a.datep as call_date, a.label, a.extraparams, a.fk_soc, ";
    $sql .= "c.lastname, c.firstname, c.phone, c.phone_mobile, c.email ";
    $sql .= "FROM " . MAIN_DB_PREFIX . "actioncomm a ";
    $sql .= "LEFT JOIN " . MAIN_DB_PREFIX . "socpeople c ON a.fk_contact = c.rowid ";
    $sql .= "WHERE a.fk_user_action = " . (int)$user_id . " ";
    $sql .= "AND a.code = 'AC_TEL' ";
    $sql .= "AND a.percent = 0 ";
    $sql .= "ORDER BY a.datep DESC";

    $resql = $db->query($sql);
    $events = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            if (!empty($obj->extraparams)) {
                $extraparams = json_decode($obj->extraparams, true);
                if (is_array($extraparams)) {
                    $obj->caller = $extraparams['caller'] ?? '';
                    $obj->callee = $extraparams['callee'] ?? '';
                }
            }
            $events[] = $obj;
        }
        $db->free($resql);
    }

    return $events;
}

/**
 * Marquer un événement d'appel comme traité dans ActionComm
 */
function mark_call_event_processed($event_id) {
    global $db, $user;
    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

    $actioncomm = new ActionComm($db);
    $result = $actioncomm->fetch($event_id);

    if ($result > 0) {
        $actioncomm->percentage = 100;

        return $actioncomm->update($user, 1);
    }

    return false;
}

/**
 * Compter le nombre d'appels pour un tiers (via ses contacts)
 */
function count_thirdparty_calls($socid) {
    global $db;

    $sql = "SELECT COUNT(DISTINCT a.id) as nb";
    $sql .= " FROM " . MAIN_DB_PREFIX . "actioncomm a";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople c ON a.fk_contact = c.rowid";
    $sql .= " WHERE c.fk_soc = " . (int)$socid;
    $sql .= " AND a.code = 'AC_TEL'";

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        return $obj->nb;
    }

    return 0;
}

/**
 * Récupérer tous les appels pour un tiers (via ses contacts)
 */
function get_thirdparty_calls($socid, $sortfield = 'a.datep', $sortorder = 'DESC', $limit = 0, $offset = 0, $filters = array()) {
    global $db;

    $sql = "SELECT a.id, a.datep, a.datef, a.label, a.percent, a.fk_contact, a.fk_user_action, a.extraparams, a.note_private,";
    $sql .= " c.lastname, c.firstname, c.phone, c.phone_mobile, c.email,";
    $sql .= " u.lastname as user_lastname, u.firstname as user_firstname, u.login";
    $sql .= " FROM " . MAIN_DB_PREFIX . "actioncomm a";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople c ON a.fk_contact = c.rowid";
    $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON a.fk_user_action = u.rowid";
    $sql .= " WHERE c.fk_soc = " . (int)$socid;
    $sql .= " AND a.code = 'AC_TEL'";

    // Filters
    if (!empty($filters['status'])) {
        if ($filters['status'] == 'new') {
            $sql .= " AND a.percent = 0";
        } elseif ($filters['status'] == 'processed') {
            $sql .= " AND a.percent = 100";
        }
    }

    if (!empty($filters['contact_id'])) {
        $sql .= " AND a.fk_contact = " . (int)$filters['contact_id'];
    }

    if (!empty($filters['user_id'])) {
        $sql .= " AND a.fk_user_action = " . (int)$filters['user_id'];
    }

    // Sorting
    if ($sortfield && $sortorder) {
        $sql .= " ORDER BY " . $sortfield . " " . $sortorder;
    }

    // Limit
    if ($limit > 0) {
        $sql .= $db->plimit($limit, $offset);
    }

    $resql = $db->query($sql);
    $calls = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            // Récupérer les infos caller/callee depuis extraparams
            if (!empty($obj->extraparams)) {
                $extraparams = json_decode($obj->extraparams, true);
                if (is_array($extraparams)) {
                    $obj->caller = $extraparams['caller'] ?? '';
                    $obj->callee = $extraparams['callee'] ?? '';
                }
            }
            $calls[] = $obj;
        }
        $db->free($resql);
    }

    return $calls;
}

/**
 * Archive an imported CSV file in the history directory structure
 *
 * @param string $sourceFile Full path to the source CSV file
 * @param string $tagLabel Category label (for reference, not used in folder name anymore)
 * @param string $historyBaseDir Base directory for import history (e.g., DOL_DATA_ROOT/reedcrm/{entity}/import/project)
 * @param int $categoryId Category ID (used as folder name)
 * @return void
 */
function reedcrm_archive_import_file(string $sourceFile, string $tagLabel, string $historyBaseDir, int $categoryId): void
{
    if (empty($sourceFile) || !is_readable($sourceFile)) {
        return;
    }

    // Use category ID as folder name
    $tagDir = (string) max(0, $categoryId);
    $finalDir = $historyBaseDir . '/' . $tagDir;
    dol_mkdir($finalDir);

    $finalName = dol_sanitizeFileName(basename($sourceFile));
    if (empty($finalName)) {
        $finalName = 'import_' . dol_print_date(dol_now(), '%Y%m%d%H%M%S') . '.csv';
    }

    $destFile = $finalDir . '/' . $finalName;
    dol_move($sourceFile, $destFile, 1);
    @touch($destFile, dol_now());
}

/**
 * Count meaningful CSV lines (excluding header) for history display
 *
 * @param string $filePath Full path to CSV file
 * @return int|null Number of lines or null on error
 */
function reedcrm_count_csv_lines(string $filePath): ?int
{
    if (!is_readable($filePath)) {
        return null;
    }

    $handle = fopen($filePath, 'r');
    if (!$handle) {
        return null;
    }

    $count = 0;
    $isFirstLine = true;
    while (($line = fgetcsv($handle)) !== false) {
        if ($isFirstLine) {
            $isFirstLine = false;
            continue;
        }
        if (count($line) === 1 && trim($line[0]) === '') {
            continue;
        }
        $count++;
    }
    fclose($handle);

    return $count;
}

/**
 * Convert the cells of an imported CSV row to UTF-8, spreadsheets often save CSV files in Windows-1252
 *
 * @param  string[] $row CSV row
 * @return string[]      Same row in UTF-8
 */
function reedcrm_import_csv_row_to_utf8(array $row): array
{
    foreach ($row as $key => $cell) {
        if (is_string($cell) && !utf8_check($cell)) {
            $row[$key] = mb_convert_encoding($cell, 'UTF-8', 'Windows-1252');
        }
    }

    return $row;
}

/**
 * Read the header line of an imported CSV file
 *
 * @param  resource $handle Handle of the CSV file, at its beginning
 * @return array{0: string, 1: string[]} Delimiter, then the column labels
 */
function reedcrm_read_import_csv_header($handle): array
{
    $firstLine = str_replace("\xEF\xBB\xBF", '', (string) fgets($handle));
    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
    $headers   = reedcrm_import_csv_row_to_utf8(array_map('trim', str_getcsv($firstLine, $delimiter)));

    return [$delimiter, $headers];
}

/**
 * Normalize a CSV header or a user reference so that it matches whatever its case and accents
 *
 * @param  string $value Raw value
 * @return string        Lowercase value without accents nor repeated spaces
 */
function reedcrm_normalize_import_key(string $value): string
{
    return strtolower(trim(preg_replace('/\s+/', ' ', dol_string_unaccent($value))));
}

/**
 * Index the active internal users that can be assigned to an imported opportunity
 *
 * ID, login, email and full name identify a user ('strong' keys). First name alone, last name alone and first
 * name followed by the initial of the last name ('weak' keys) are only used when no strong key matches.
 *
 * @return array{strong: array<string, int[]>, weak: array<string, int[]>} Normalized key => user IDs, several IDs for homonyms
 */
function reedcrm_get_import_users_index(): array
{
    global $db;

    // Superadmins (entity 0) reach every entity, the other users are those of the current entity
    $sql = 'SELECT u.rowid, u.login, u.email, u.firstname, u.lastname FROM ' . MAIN_DB_PREFIX . 'user as u WHERE (u.entity = 0 OR ';
    if (isModEnabled('multicompany') && getDolGlobalInt('MULTICOMPANY_TRANSVERSE_MODE')) {
        $sql .= 'u.rowid IN (SELECT ug.fk_user FROM ' . MAIN_DB_PREFIX . 'usergroup_user as ug WHERE ug.entity IN (' . getEntity('usergroup') . ')))';
    } else {
        $sql .= 'u.entity IN (' . getEntity('user') . '))';
    }
    $sql .= ' AND u.statut = 1 AND (u.fk_soc IS NULL OR u.fk_soc = 0)';

    $usersIndex = ['strong' => [], 'weak' => []];
    $resql      = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $firstname = reedcrm_normalize_import_key((string) $obj->firstname);
            $lastname  = reedcrm_normalize_import_key((string) $obj->lastname);
            $keys      = [
                'strong' => [(string) $obj->rowid, reedcrm_normalize_import_key((string) $obj->login), reedcrm_normalize_import_key((string) $obj->email)],
                'weak'   => [$firstname, $lastname]
            ];
            if ($firstname !== '' && $lastname !== '') {
                array_push($keys['strong'], $firstname . ' ' . $lastname, $lastname . ' ' . $firstname);
                array_push($keys['weak'], $firstname . ' ' . $lastname[0], $firstname . $lastname[0]);
            }
            foreach ($keys as $level => $levelKeys) {
                foreach (array_unique($levelKeys) as $key) {
                    if ($key !== '') {
                        $usersIndex[$level][$key][] = (int) $obj->rowid;
                    }
                }
            }
        }
        $db->free($resql);
    }

    return $usersIndex;
}

/**
 * Resolve the users listed in the assignees cell of an imported CSV row
 *
 * @param  string $cell       Logins, emails or names, separated by a comma, a semicolon, |, /, &, +, "et" or "and"
 * @param  array  $usersIndex Index built by reedcrm_get_import_users_index()
 * @return array{0: int[], 1: string[]} IDs of the users found, then the values matching no user or several users
 */
function reedcrm_resolve_import_users(string $cell, array $usersIndex): array
{
    $userIds    = [];
    $unresolved = [];
    foreach (preg_split('/\s*[|,;\/&+\r\n]\s*|\s+(?:et|and)\s+/i', $cell) as $value) {
        $value = trim($value);
        if ($value === '') {
            continue;
        }

        $key             = reedcrm_normalize_import_key($value);
        $matchingUserIds = $usersIndex['strong'][$key] ?? ($usersIndex['weak'][$key] ?? []);
        if (count($matchingUserIds) == 1) {
            $userIds[] = $matchingUserIds[0];
        } else {
            $unresolved[] = $value;
        }
    }

    return [array_values(array_unique($userIds)), $unresolved];
}

/**
 * Read the date of an imported CSV cell, as a spreadsheet writes it
 *
 * Day first (25/10/2026, 25-10-26, 25.10.2026) or ISO (2026-10-25), then an optional time (14:30, 14h30, 14h).
 * A date without time is set at 9:00, in the timezone of the user importing the file.
 *
 * @param  string $value Raw cell
 * @return int           Timestamp, 0 when the cell holds no valid date
 */
function reedcrm_parse_import_date(string $value): int
{
    $value = trim($value);
    $time  = '(?:[ T]+(\d{1,2})(?:[:hH](\d{2})?)?(?::(\d{2}))?)?';
    if (preg_match('/^(\d{1,2})[\/.-](\d{1,2})[\/.-](\d{4}|\d{2})' . $time . '$/', $value, $matches)) {
        list(, $day, $month, $year) = $matches;
    } elseif (preg_match('/^(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})' . $time . '$/', $value, $matches)) {
        list(, $year, $month, $day) = $matches;
    } else {
        return 0;
    }

    $year   = (int) $year < 100 ? 2000 + (int) $year : (int) $year;
    $hour   = (isset($matches[4]) && $matches[4] !== '') ? (int) $matches[4] : 9;
    $minute = (int) ($matches[5] ?? 0);
    $second = (int) ($matches[6] ?? 0);
    if (!checkdate((int) $month, (int) $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
        return 0;
    }

    return (int) dol_mktime($hour, $minute, $second, (int) $month, (int) $day, $year, 'tzuserrel');
}

/**
 * Turn the note of an imported CSV cell into an opportunity probability, ten percent per point
 *
 * @param  string   $value Raw cell, a note from 0 to 10 (2,5 and 2.5 are accepted)
 * @return int|null        Probability from 0 to 100, null when the cell holds no valid note
 */
function reedcrm_parse_import_note(string $value): ?int
{
    $value = str_replace(',', '.', trim($value));
    if (!preg_match('/^\d{1,2}(?:\.\d+)?$/', $value) || (float) $value > 10) {
        return null;
    }

    return (int) round((float) $value * 10);
}

/**
 * Tell whether an imported CSV cell says there is nothing, "Aucune" in an action column for instance
 *
 * @param  string $value Raw cell
 * @return bool          True for an empty cell or a value meaning none or unknown
 */
function reedcrm_is_import_empty_value(string $value): bool
{
    return (bool) preg_match('/^(|-+|\?+|aucun|aucune|rien|non|none|n\/?a|inconnu|inconnue|unknown|nsp|je ne sais pas|ne sais pas|i don\'?t know)$/', reedcrm_normalize_import_key($value));
}

/**
 * Opportunity status matching a probability: the active status of the highest probability not above it,
 * the lowest one when all of them are above it
 *
 * Won and lost close an opportunity, a probability alone never leads to them.
 *
 * @param  int $percent Probability from 0 to 100
 * @return int          Row ID of the status in c_lead_status, 0 when the dictionary has none
 */
function reedcrm_get_opp_status_for_percent(int $percent): int
{
    global $db;

    // Read once per request, an import asks for it on every row
    static $statuses = null;
    if ($statuses === null) {
        $statuses = [];
        $sql      = 'SELECT rowid, percent FROM ' . MAIN_DB_PREFIX . "c_lead_status WHERE active = 1 AND code NOT IN ('WON', 'LOST') ORDER BY percent ASC, position ASC";
        $resql    = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $statuses[] = ['id' => (int) $obj->rowid, 'percent' => (float) $obj->percent];
            }
            $db->free($resql);
        }
    }

    $statusId = $statuses[0]['id'] ?? 0;
    foreach ($statuses as $status) {
        if ($status['percent'] > $percent) {
            break;
        }
        $statusId = $status['id'];
    }

    return $statusId;
}

/**
 * Get (and lazily create) the actioncomm category used to tag automatic call reminders.
 *
 * The reminder events created from the ProCard/EventPro checkbox must NOT reuse the commercial
 * relaunch tag, otherwise they would inflate relaunch counts. They get their own dedicated tag,
 * stored in the REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG constant and created on demand if missing.
 *
 * @param  DoliDB $db   Database handler
 * @param  User   $user User creating the category when it does not exist yet
 * @return int          Category id (> 0), or 0 on failure
 */
function reedcrm_get_call_reminder_category_id(DoliDB $db, User $user): int
{
    global $conf, $langs;

    $categoryID = getDolGlobalInt('REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG');
    if ($categoryID > 0) {
        return $categoryID;
    }

    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

    $category        = new Categorie($db);
    $category->label = $langs->transnoentities('CallReminderCategory');
    $category->type  = 'actioncomm';

    $categoryID = $category->create($user);
    if ($categoryID > 0) {
        dolibarr_set_const($db, 'REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG', $categoryID, 'integer', 0, '', $conf->entity);
        return $categoryID;
    }

    return 0;
}

/**
 * Create an automatic call reminder: a future to-do event and its browser notification.
 *
 * Same reminder as the ProCard/EventPro "AddReminder" checkbox: tagged with the dedicated call
 * reminder category so the dashboard lists it in its upcoming reminders, and notified
 * REEDCRM_QUICK_CREATION_REMINDER_OFFSET / _UNIT before its date.
 *
 * @param  DoliDB $db        Database handler
 * @param  User   $user      User creating the reminder
 * @param  string $label     Label of the reminder event
 * @param  int    $date      Date of the reminder (timestamp)
 * @param  int    $userId    User to remind
 * @param  int    $projectId Linked project id (0 = none)
 * @param  int    $socId     Linked thirdparty id (0 = none)
 * @param  int    $contactId Linked contact id (0 = none)
 * @param  string $typeCode  Type of the reminder event, one of reedcrm_get_relaunch_types()
 * @param  int[]  $otherUserIds Other users to remind, assigned to the same event and notified too
 * @param  array{due_date?: int, note?: string, notify?: bool} $options 'due_date': deadline of the event (its end date),
 *                           notified before it rather than before $date; 'note': private note of the event;
 *                           'notify': false to create no browser notification
 * @return int               Id of the reminder event (> 0), or -1 on error
 */
function reedcrm_create_call_reminder(DoliDB $db, User $user, string $label, int $date, int $userId, int $projectId = 0, int $socId = 0, int $contactId = 0, string $typeCode = 'AC_OTH', array $otherUserIds = [], array $options = []): int
{
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncommreminder.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

    $dueDate = (int) ($options['due_date'] ?? 0);

    $db->begin();

    $actionComm               = new ActionComm($db);
    $actionComm->type_code    = $typeCode;
    $actionComm->percentage   = 0; // A reminder is a future "to do"
    $actionComm->datep        = $date;
    if ($dueDate > 0) {
        $actionComm->datef = $dueDate;
    }
    if (!empty($options['note'])) {
        $actionComm->note_private = $options['note'];
    }
    $actionComm->label        = $label;
    $actionComm->fk_project   = $projectId;
    $actionComm->socid        = $socId;
    $actionComm->userownerid  = $userId; // create() assigns the event to its owner
    // A single event for every user to remind: whoever calls back marks it done for all of them
    $remindedUserIds = array_values(array_unique(array_merge([$userId], array_map('intval', $otherUserIds))));
    if (count($remindedUserIds) > 1) {
        foreach ($remindedUserIds as $remindedUserId) {
            $actionComm->userassigned[$remindedUserId] = ['id' => $remindedUserId, 'transparency' => 0];
        }
    }
    if ($contactId > 0) {
        $actionComm->socpeopleassigned = [$contactId => $contactId];
    }

    $actionCommID = $actionComm->create($user);
    if ($actionCommID <= 0) {
        dol_syslog(__FUNCTION__ . ' ' . $actionComm->error . ' ' . implode(', ', $actionComm->errors), LOG_ERR);
        $db->rollback();
        return -1;
    }

    $categoryID = reedcrm_get_call_reminder_category_id($db, $user);
    if ($categoryID > 0) {
        $category = new Categorie($db);
        if ($category->fetch($categoryID) > 0) {
            $category->add_type($actionComm, 'actioncomm');
        }
    }

    $offsetValue = getDolGlobalInt('REEDCRM_QUICK_CREATION_REMINDER_OFFSET', 30);
    $offsetUnit  = getDolGlobalString('REEDCRM_QUICK_CREATION_REMINDER_UNIT', 'i');

    foreach (($options['notify'] ?? true) ? $remindedUserIds : [] as $remindedUserId) {
        $actionCommReminder                = new ActionCommReminder($db);
        $actionCommReminder->dateremind    = dol_time_plus_duree($dueDate > 0 ? $dueDate : $date, -1 * $offsetValue, $offsetUnit);
        $actionCommReminder->typeremind    = 'browser';
        $actionCommReminder->offsetvalue   = $offsetValue;
        $actionCommReminder->offsetunit    = $offsetUnit;
        $actionCommReminder->fk_actioncomm = $actionCommID;
        $actionCommReminder->fk_user       = $remindedUserId;
        $actionCommReminder->status        = ActionCommReminder::STATUS_TODO;

        if ($actionCommReminder->create($user) <= 0) {
            dol_syslog(__FUNCTION__ . ' ' . $actionCommReminder->error . ' ' . implode(', ', $actionCommReminder->errors), LOG_ERR);
            $db->rollback();
            return -1;
        }
    }

    $db->commit();

    return $actionCommID;
}

/**
 * Resolve the person to call for an opportunity.
 *
 * A project carries its caller in three mutually exclusive ways, in decreasing priority:
 * a real contact referenced by the projectaddress extrafield, the free-text ReedCRM
 * extrafields (lastname/firstname/phone/email) or, as a last resort, the linked thirdparty.
 * The call list, its mobile view and the opportunity App pages all need the same resolution,
 * hence this shared helper.
 *
 * @param  Project $project Project to resolve the contact of (optionals are fetched on demand)
 * @return array            ['contact_id', 'lastname', 'firstname', 'phone', 'email'] — raw values, NOT escaped
 */
function reedcrm_get_project_contact_details(Project $project): array
{
    $details = ['contact_id' => 0, 'lastname' => '', 'firstname' => '', 'phone' => '', 'email' => ''];

    if (empty($project->id)) {
        return $details;
    }

    if (!is_array($project->array_options) || empty($project->array_options)) {
        $project->fetch_optionals();
    }

    if (!empty($project->array_options['options_projectaddress'])) {
        require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

        $contact = new Contact($project->db);
        if ($contact->fetch($project->array_options['options_projectaddress']) > 0) {
            $details['contact_id'] = $contact->id;
            $details['lastname']   = $contact->lastname;
            $details['firstname']  = $contact->firstname;
            $details['phone']      = $contact->phone_pro ?: $contact->phone_mobile ?: '';
            $details['email']      = $contact->email;

            return $details;
        }
    }

    if (!empty($project->array_options['options_reedcrm_lastname']) || !empty($project->array_options['options_projectphone'])) {
        $details['lastname']  = (string) ($project->array_options['options_reedcrm_lastname'] ?? '');
        $details['firstname'] = (string) ($project->array_options['options_reedcrm_firstname'] ?? '');
        $details['phone']     = (string) ($project->array_options['options_projectphone'] ?? '');
        $details['email']     = (string) ($project->array_options['options_reedcrm_email'] ?? '');

        return $details;
    }

    if ($project->socid > 0) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

        $thirdparty = new Societe($project->db);
        if ($thirdparty->fetch($project->socid) > 0) {
            $details['lastname'] = $thirdparty->name;
            $details['phone']    = $thirdparty->phone;
            $details['email']    = $thirdparty->email;
        }
    }

    return $details;
}

/**
 * Relaunch types shown on the commercial relaunch widgets (project list, opportunity App page).
 *
 * The keys drive the CSS modifiers (reedcrm-plist-relaunch-btn-<key>) and every event whose
 * type_code is not explicitly mapped falls into 'other'.
 *
 * @return array<string, array{picto: string, actioncode: string}>
 */
function reedcrm_get_relaunch_types(): array
{
    return [
        'call'  => ['picto' => 'headset',      'actioncode' => 'AC_TEL'],
        'email' => ['picto' => 'envelope',     'actioncode' => 'AC_EMAIL'],
        'rdv'   => ['picto' => 'calendar',     'actioncode' => 'AC_RDV'],
        'other' => ['picto' => 'comment-dots', 'actioncode' => 'AC_OTH'],
    ];
}

/**
 * Relaunch types as select options, the type code as key and its translated label as value.
 *
 * @return array<string, string> Translated labels indexed by type code (AC_TEL, AC_EMAIL, ...)
 */
function reedcrm_get_relaunch_type_options(): array
{
    global $langs;

    $langs->load('commercial');

    $options = [];
    foreach (reedcrm_get_relaunch_types() as $type) {
        $options[$type['actioncode']] = $langs->trans('Action' . $type['actioncode']);
    }

    return $options;
}

/**
 * Statuses offered when an event is created, the event percentage as key and its label key as value.
 *
 * @return array<int, string> Translation keys indexed by percentage
 */
function reedcrm_get_event_statuses(): array
{
    return [
        0   => 'ActionsToDoShort',
        50  => 'ActionRunningShort',
        100 => 'ActionDoneShort',
    ];
}

/**
 * Map an event type_code to its relaunch bucket.
 *
 * @param  string $typeCode ActionComm type code (AC_TEL, AC_EMAIL, ...)
 * @return string           Bucket key of reedcrm_get_relaunch_types()
 */
function reedcrm_get_relaunch_type_key(string $typeCode): string
{
    foreach (reedcrm_get_relaunch_types() as $key => $type) {
        if ($type['actioncode'] === $typeCode) {
            return $key;
        }
    }

    return 'other';
}

/**
 * Build the criteria filtering a Dolibarr list on a date range
 *
 * A Dolibarr list expects one day, month and year parameter per bound, so a range is spelled out field by field.
 *
 * @param  string $prefix Prefix of the search parameters of the list, without its bound suffix
 * @param  int    $start  Timestamp the range starts at, 0 for an open lower bound
 * @param  int    $end    Timestamp the range ends at, 0 for an open upper bound
 * @return string         Criteria, url encoded and ready to be appended to a list URL
 */
function reedcrm_get_date_range_filter(string $prefix, int $start = 0, int $end = 0): string
{
    $filter = [];
    foreach (['start' => $start, 'end' => $end] as $bound => $timestamp) {
        if (empty($timestamp)) {
            continue;
        }

        $date     = dol_getdate($timestamp);
        $filter[] = $prefix . '_' . $bound . 'day=' . $date['mday'];
        $filter[] = $prefix . '_' . $bound . 'month=' . $date['mon'];
        $filter[] = $prefix . '_' . $bound . 'year=' . $date['year'];
    }

    return implode('&', $filter);
}

/**
 * Read the user each recurring invoice template hands its relaunches to
 *
 * The template carries it in the 'reedcrm_relaunch_user' extrafield. A user since disabled is
 * left out, so his templates fall back on the owner the relaunch would have had without the
 * feature rather than filling the board of someone gone.
 *
 * @param  DoliDB         $db Database handler
 * @return array<int,int>     Row ID of the template => row ID of the user
 */
function reedcrm_get_template_relaunch_users(DoliDB $db): array
{
    $relaunchUsers = [];

    $sql  = 'SELECT ef.fk_object, ef.reedcrm_relaunch_user';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facture_rec_extrafields as ef';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'user as u ON u.rowid = ef.reedcrm_relaunch_user AND u.statut = 1';
    $sql .= ' WHERE ef.reedcrm_relaunch_user > 0';

    // The column only exists once the module has been activated: without it, the relaunches keep
    // the owner they had before the feature
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ': ' . $db->lasterror(), LOG_WARNING);
        return $relaunchUsers;
    }

    while ($obj = $db->fetch_object($resql)) {
        $relaunchUsers[(int) $obj->fk_object] = (int) $obj->reedcrm_relaunch_user;
    }
    $db->free($resql);

    return $relaunchUsers;
}

/**
 * Build the full URL of a module asset, carrying a version derived from the file itself
 *
 * A stylesheet printed without a version is a second cache entry for the same file, and the
 * browser can serve it stale. Suffixing with the modification time follows the convention the
 * framework already uses for its own bundle.
 *
 * @param  string $relativePath Asset path relative to the custom directory, leading slash included
 * @return string               Full URL, suffixed with the version of the file when it exists
 */
function reedcrm_asset_full_url(string $relativePath): string
{
    $url = dol_buildpath($relativePath, 1);

    $absolutePath = dol_buildpath($relativePath, 0);
    if (!file_exists($absolutePath)) {
        return $url;
    }

    return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . filemtime($absolutePath);
}

/**
 * Render the media block of the quick creation form
 *
 * The block is rendered again after each photo upload: going through here keeps its options, the
 * photo wand included.
 *
 * @param  string $subDir Upload folder of the user, under the project directory
 * @return string         HTML of the block
 */
function reedcrm_quickcreation_media_block(string $subDir): string
{
    dol_include_once('/saturne/lib/medias.lib.php');

    // The readFileAI action of the quick creation only knows how to query ChatGPT
    $showAi = getDolGlobalString('AI_API_SERVICE') == 'chatgpt';

    return saturne_render_media_block('project', $subDir, 'quickcreation_', '', ['show_photo' => true, 'show_audio' => true, 'show_ai' => $showAi]);
}

/**
 * Delete the time spent on the tasks of projects about to be deleted, as Dolibarr announces it will.
 *
 * Task::delete() refuses a task holding time spent, so the deletion of its project fails without a
 * message. The time already invoiced is kept: those projects still cannot be deleted.
 *
 * @param  DoliDB            $db         Database handler
 * @param  array<int|string> $projectIds Ids of the projects about to be deleted
 * @return int                           Number of time spent lines deleted, -1 on error
 */
function reedcrm_delete_projects_time_spent(DoliDB $db, array $projectIds): int
{
    $projectIds = array_filter(array_map('intval', $projectIds));
    if (empty($projectIds)) {
        return 0;
    }

    $sql  = 'DELETE FROM ' . MAIN_DB_PREFIX . "element_time WHERE elementtype = 'task'";
    $sql .= ' AND (invoice_id IS NULL OR invoice_id = 0)';
    $sql .= ' AND fk_element IN (SELECT pt.rowid FROM ' . MAIN_DB_PREFIX . 'projet_task AS pt';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet AS p ON p.rowid = pt.fk_projet';
    $sql .= ' WHERE p.rowid IN (' . $db->sanitize(implode(',', $projectIds)) . ') AND p.entity IN (' . getEntity('project') . '))';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror(), LOG_ERR);
        return -1;
    }

    return $db->affected_rows($resql);
}

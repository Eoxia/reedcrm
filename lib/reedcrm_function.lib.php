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
    $object->array_options['options_notation_' . $object->element . '_contact'] = ($notationObjectContact['percentage'] ?: 0) . ' %';
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

    // Si on a trouvé un utilisateur et un contact, on stocke l'événement
    if ($result['user'] && $result['contact']) {
        $call_event_id = store_call_event($result['user']->id, $result['contact']->id, $caller, $callee);
        $result['call_event_id'] = $call_event_id;
        log_to_file("Stored call event with ID: " . $call_event_id);
    }

    return $result;
}

/**
 * Stocker l'événement d'appel en base de données via ActionComm
 */
function store_call_event($user_id, $contact_id, $caller, $callee) {
    global $db, $user, $langs, $conf;
    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
    require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

    $contact = new Contact($db);
    $contact->fetch($contact_id);

    $actioncomm = new ActionComm($db);
    $actioncomm->type_code = 'AC_TEL';
    $actioncomm->label = $langs->trans("IncomingCall") . ' - ' . $contact->getFullName($langs);
    $actioncomm->datep = dol_now();
    $actioncomm->datef = dol_now();
    $actioncomm->percentage = 0;
    $actioncomm->userownerid = $user_id;
    $actioncomm->fk_user_action = $user_id;
    $actioncomm->contact_id = $contact_id;
    $actioncomm->socid = $contact->fk_soc;

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

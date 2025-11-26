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
 * \file    view/reedcrmtools.php
 * \ingroup reedcrm
 * \brief   Tools page of ReedCRM top menu
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_function.lib.php';
require_once __DIR__ . '/../class/reedcrmnotifiy.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$action = (GETPOSTISSET('action') ? GETPOST('action', 'aZ09') : 'view');

// Initialize technical objects
$facture       = new Facture($db);
$thirdparty    = new Societe($db);
$actioncomm    = new ActionComm($db);
$reedcrmNotify = new ReedcrmNotify($db);
$contact       = new Contact($db);

// Security check - Protection if external user
$permissionToRead = $user->rights->reedcrm->adminpage->read;
saturne_check_access($permissionToRead);

/*
 * Actions
 */

if ($action == 'update_object_contact') {
    $factures = saturne_fetch_all_object_type('Facture', '', '', 0, 0,  ['customsql' => 't.fk_statut = ' . Facture::STATUS_VALIDATED]);
    if (is_array($factures) && !empty($factures)) {
        $typeContactID                     = 0;
        $checkObjectContact                = 0;
        $objectContactUpdated              = 0;
        $alreadyCheckObjectContact         = [];
        $societyObjectContactNotDefinedIDs = [];
        $typeContactsID                    = reedcrm_fetch_dictionary('c_type_contact', " AND element = 'facture' AND source = 'external' AND code = 'BILLING'");
        if (is_array($typeContactsID) && !empty($typeContactsID)) {
            $typeContactID = key($typeContactsID);
        }
        foreach ($factures as $facture) {
            $objectContacts = $facture->liste_contact(-1, 'external', 0, 'BILLING');
            if (is_array($objectContacts) && empty($objectContacts)) {
                $notationObjectContacts   = get_notation_object_contacts($facture, 'facture_external_BILLINGS');
                $notationObjectContactKey = key($notationObjectContacts);
                $notationObjectContact    = array_shift($notationObjectContacts);
                if (!empty($notationObjectContact)) {
                    $facture->add_contact($notationObjectContactKey, $typeContactID);
                    setEventMessage($langs->trans('ObjectContactUpdated', $langs->trans('FactureMin'), $facture->ref));
                    $objectContactUpdated++;
                } elseif (!in_array($facture->fk_soc, $alreadyCheckObjectContact)) {
                    $thirdparty->fetch($facture->fk_soc);
                    setEventMessages($langs->trans('SocietyObjectContactNotDefined') . ' ' . $thirdparty->getNomUrl(1), [],'warnings');
                    $checkObjectContact++;
                    $alreadyCheckObjectContact[]         = $facture->fk_soc;
                    $societyObjectContactNotDefinedIDs[] = $thirdparty->id;
                }
            }
        }
        $societyObjectContactNotDefinedIDs = json_encode($societyObjectContactNotDefinedIDs);
        dolibarr_set_const($db, 'REEDCRM_OBJECT_CONTACT_UPDATED', $objectContactUpdated, 'integer', 0, '', $conf->entity);
        dolibarr_set_const($db, 'REEDCRM_ALREADY_CHECK_OBJECT_CONTACT', count($alreadyCheckObjectContact), 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'REEDCRM_SOCIETY_OBJECT_CONTACT_NOT_DEFINED', $societyObjectContactNotDefinedIDs, 'chaine', 0, '', $conf->entity);
        if ($checkObjectContact == 0) {
            setEventMessage($langs->trans('AllObjectHaveContact', $langs->trans('FactureMins')));
        }
        $user->call_trigger('USER_UPDATE_OBJECT_CONTACT', $user);
    } else {
        setEventMessage($langs->trans('NoObject', $langs->trans('FactureMin')), 'errors');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if ($action == 'add_contact_notification') {
    $thirdparties = saturne_fetch_all_object_type('Societe');
    if (is_array($thirdparties) && !empty($thirdparties)) {
        $actionTriggerID                         = 0;
        $checkContactNotification                = 0;
        $contactNotificationAdded                = 0;
        $societyContactNotificationNotDefinedIDs = [];
        $actionTriggersID                        = reedcrm_fetch_dictionary('c_action_trigger', " AND elementtype = 'facture' AND code = 'BILL_VALIDATE'");
        if (is_array($actionTriggersID) && !empty($actionTriggersID)) {
            $actionTriggerID = key($actionTriggersID);
        }
        foreach ($thirdparties as $thirdparty) {
            $notationObjectContacts = get_notation_object_contacts($thirdparty, 'facture_external_BILLINGS');
            if (!empty($notationObjectContacts)) {
                foreach ($notationObjectContacts as $key => $notationObjectContact) {
                    $reedcrmNotify->datec      = $db->idate(dol_now());
                    $reedcrmNotify->fk_action  = $actionTriggerID;
                    $reedcrmNotify->fk_soc     = $thirdparty->id;
                    $reedcrmNotify->fk_contact = $key;
                    $reedcrmNotify->create($user, true);
                    $contact->fetch($key);
                    setEventMessage($langs->trans('ContactNotificationAdded') . ' ' . $thirdparty->getNomUrl(1) . ' - ' . $contact->getNomUrl(1));
                    $contactNotificationAdded++;
                }
            } else {
                setEventMessages($langs->trans('SocietyContactNotificationNotDefined') . ' ' . $thirdparty->getNomUrl(1), [],'warnings');
                $checkContactNotification++;
                $societyContactNotificationNotDefinedIDs[] = $thirdparty->id;
            }
        }
        dolibarr_set_const($db, 'REEDCRM_CONTACT_NOTIFICATION_ADDED', $contactNotificationAdded, 'integer', 0, '', $conf->entity);
        dolibarr_set_const($db, 'REEDCRM_ALREADY_CHECK_CONTACT_NOTIFICATION', count($societyContactNotificationNotDefinedIDs), 'chaine', 0, '', $conf->entity);
        $societyContactNotificationNotDefinedIDs = json_encode($societyContactNotificationNotDefinedIDs);
        dolibarr_set_const($db, 'REEDCRM_SOCIETY_CONTACT_NOTIFICATION_NOT_DEFINED', $societyContactNotificationNotDefinedIDs, 'chaine', 0, '', $conf->entity);
        if ($checkContactNotification == 0) {
            setEventMessage($langs->trans('AllContactHaveNotification'));
        }
        $user->call_trigger('USER_ADD_CONTACT_NOTIFICATION', $user);
    } else {
        setEventMessage($langs->trans('NoContact'), 'errors');
    }
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title   = $langs->trans('Tools');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0,'', $title, $helpUrl);

print load_fiche_titre($title, '', 'wrench');

print load_fiche_titre($langs->trans('UpdateObjectContactManagement'), '', '');

print '<form name="update-object-contact-from" id="update-object-contact-from" action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update_object_contact">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('UpdateObjectContact', $langs->trans('FactureMin'));
print '</td><td>';
print $langs->trans('UpdateObjectContactDescription', $conf->global->REEDCRM_OBJECT_CONTACT_UPDATED, $langs->trans('FactureMins'));
$actionComms = $actioncomm->getActions(0, 0,'', " AND code = 'AC_USER_UPDATE_OBJECT_CONTACT'", 'id','DESC', 1);
if (is_array($actionComms) && !empty($actionComms)) {
    print ' : ' . dol_print_date($actionComms[0]->datec, 'dayhour', 'tzuser');
}
print '</td>';

print '<td class="center">';
if ($user->rights->facture->creer) {
    print '<input type="submit" class="button" name="update_object_contact" value="' . $langs->trans('Validate') . '">';
} else {
    print '<span class="butActionRefused classfortooltip" title="' . dol_escape_htmltag($langs->trans('PermissionDenied')) . '">' . $langs->trans('Validate') . '</span>';
}
print '</td></tr>';

print '</table>';
print '</form>'; ?>

    <div class="wpeo-notice notice-info">
        <div class="notice-content">
            <div class="notice-title"><strong><?php echo $langs->trans('SocietyObjectContactNotDefinedTitle', $conf->global->REEDCRM_ALREADY_CHECK_OBJECT_CONTACT); ?></strong></div>
            <div class="notice-subtitle">
                <?php $societyObjectContactNotDefinedIDs = $conf->global->REEDCRM_SOCIETY_OBJECT_CONTACT_NOT_DEFINED;
                $societyObjectContactNotDefinedIDs = json_decode($societyObjectContactNotDefinedIDs);
                if (is_array($societyObjectContactNotDefinedIDs) && !empty($societyObjectContactNotDefinedIDs)) {
                    foreach ($societyObjectContactNotDefinedIDs as $societyObjectContactNotDefinedID) {
                        $thirdparty->fetch($societyObjectContactNotDefinedID);
                        print $langs->trans('SocietyObjectContactNotDefined') . ' ' . $thirdparty->getNomUrl(1) . '<br>';
                    }
                } ?>
            </div>
        </div>
    </div>

<?php print load_fiche_titre($langs->trans('AddContactNotificationManagement'), '', '');

print '<form name="add-contact-notification-from" id="add-contact-notification-from" action="' . $_SERVER['PHP_SELF'] . '" method="POST">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="add_contact_notification">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('AddContactNotification', $langs->trans('FactureMin'));
print '</td><td>';
print $langs->trans('AddContactNotificationDescription', $conf->global->REEDCRM_CONTACT_NOTIFICATION_ADDED);
$actionComms = $actioncomm->getActions(0, 0,'', " AND code = 'AC_USER_ADD_CONTACT_NOTIFICATION'", 'id','DESC', 1);
if (is_array($actionComms) && !empty($actionComms)) {
    print ' : ' . dol_print_date($actionComms[0]->datec, 'dayhour', 'tzuser');
}
print '</td>';

print '<td class="center">';
print '<input type="submit" class="button" name="add_contact_notification" value="' . $langs->trans('Validate') . '">';
print '</td></tr>';

print '</table>';
print '</form>'; ?>

    <div class="wpeo-notice notice-info">
        <div class="notice-content">
            <div class="notice-title"><strong><?php echo $langs->trans('SocietyContactNotificationNotDefinedTitle', $conf->global->REEDCRM_ALREADY_CHECK_CONTACT_NOTIFICATION); ?></strong></div>
            <div class="notice-subtitle">
                <?php $societyContactNotificationNotDefinedIDs = $conf->global->REEDCRM_SOCIETY_CONTACT_NOTIFICATION_NOT_DEFINED;
                $societyContactNotificationNotDefinedIDs = json_decode($societyContactNotificationNotDefinedIDs);
                if (is_array($societyContactNotificationNotDefinedIDs) && !empty($societyContactNotificationNotDefinedIDs)) {
                    foreach ($societyContactNotificationNotDefinedIDs as $societyContactNotificationNotDefinedID) {
                        $thirdparty->fetch($societyContactNotificationNotDefinedID);
                        print $langs->trans('SocietyContactNotificationNotDefined') . ' ' . $thirdparty->getNomUrl(1) . '<br>';
                    }
                } ?>
            </div>
        </div>
    </div>

<?php // End of page
llxFooter();
$db->close();

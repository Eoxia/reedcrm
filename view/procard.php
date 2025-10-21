<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    procard.php
 * \ingroup reedcrm
 * \brief   Commerce tab for third party
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
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/project.lib.php';
require_once DOL_DOCUMENT_ROOT. '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT. '/core/class/doleditor.class.php';
if (isModEnabled('project'))  require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
if (isModEnabled('ticket')) {
    require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formticket.class.php';
    require_once DOL_DOCUMENT_ROOT . '/core/lib/ticket.lib.php';
    require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
}

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$objectId = GETPOSTINT('from_id');
$objectType = GETPOST('from_type');
$current_tab = GETPOST('tab', 'aZ09');

// Initialize objects
$societe = new Societe($db);
$project = new Project($db);
$form        = new Form($db);
$formmail    = new FormMail($db);
$formproject = (isModEnabled('project') ? new FormProjets($db) : null);
$formactions = new FormActions($db);
$formticket  = (isModEnabled('ticket') ? new FormTicket($db) : null);

$hookmanager->initHooks([$objectType . 'eventpro', $objectType . 'eventpro', 'reedcrmglobal', 'globalcard']); // Note that conf->hooks_modules contains array


// Load third party or project
if ($objectType == 'project') {
    $project->fetch($objectId);
    $socid = $project->socid;
} else if ($objectType == 'societe') {
    $socid = $objectId;
}

if ($socid > 0) {
    $result = $societe->fetch($socid);
    if ($result < 0) {
        setEventMessages($societe->error, $societe->errors, 'errors');
    }
}

// Security check - Protection if external user
$permissiontoread   = $user->rights->reedcrm->eventpro->read;
$permissiontoadd    = $user->rights->reedcrm->eventpro->write;
$permissiontodelete = $user->rights->reedcrm->eventpro->delete;
saturne_check_access($permissiontoread);


/*
*  Actions
*/

$parameters = ['id' => $objectId];
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $objectLinked, $action); // Note that $action and $objectLinked may have been modified by some hooks
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
    // Action to add commercial relaunch event
    if (GETPOST('action') == 'addevent') {
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        $actioncomm = new ActionComm($db);
        $category = new Categorie($db);

        // Get form data
        $actioncomm->label = GETPOST('label', 'alphanohtml');
        $actioncomm->note_private = GETPOST('note', 'restricthtml');
        $actioncomm->socid = $societe->id;
        $actioncomm->contact_id = GETPOST('contactid', 'int');
        $actioncomm->fk_project = GETPOST('project_id', 'int');
        $actioncomm->userownerid = $user->id;
        $actioncomm->userassigned = array($user->id => array('id' => $user->id));

        // Get event type
        $actioncode = GETPOST('actioncode', 'aZ09');
        if (!empty($actioncode)) {
            $actioncomm->type_code = $actioncode;
        } else {
            $actioncomm->type_code = 'AC_TEL'; // Default to incoming call
        }

        // Get date
        $datep = dol_mktime(GETPOST('event_hour', 'int'), GETPOST('event_min', 'int'), 0, GETPOST('event_month', 'int'), GETPOST('event_day', 'int'), GETPOST('event_year', 'int'));
        if ($datep > 0) {
            $actioncomm->datep = $datep;
        } else {
            $actioncomm->datep = dol_now();
        }

        $result = $actioncomm->create($user);

        $category->fetch($conf->global->REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG);
        $category->add_type($actioncomm, 'actioncomm');

        if ($result > 0) {
            setEventMessages($langs->trans("EventCreated"), null, 'mesgs');
            // Redirect to avoid resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . '?from_id=' . $objectId . '&from_type=' . $objectType);
            exit;
        } else {
            setEventMessages($actioncomm->error, $actioncomm->errors, 'errors');
        }
    }

    // Action to create ticket
    if (GETPOST('action') == 'createticket' && isModEnabled('ticket')) {

        $ticket = new Ticket($db);

        // Get form data
        $ticket->ref = $ticket->getDefaultRef($user);
        $ticket->subject = GETPOST('ticket_subject', 'alphanohtml');
        $ticket->message = GETPOST('ticket_message', 'restricthtml');
        $ticket->fk_project = GETPOST('project_id');
        $ticket->fk_soc = GETPOST('ticket_socid', 'int') ?: $societe->id;
        $ticket->fk_user_assign = GETPOST('ticket_user_assign', 'int');
        $ticket->type_code = GETPOST('ticket_type', 'aZ09');
        $ticket->category_code = GETPOST('ticket_category', 'aZ09');
        $ticket->timing = GETPOST('ticket_timing', 'int');
        $ticket->status = 0; // New ticket

        // Handle date start
        $date_start = GETPOST('ticket_date_start', 'int');
        if ($date_start > 0) {
            $ticket->datec = $date_start;
        } else {
            $ticket->datec = dol_now();
        }

        // Set contact if selected
        $contactid = GETPOST('ticket_contact_id', 'int');
        if ($contactid > 0) {
            $ticket->context['contactid'] = $contactid;
        }

        // Disable email notifications for ticket creation
        $ticket->context['disableticketemail'] = 1;

        // Create the ticket
        $result = $ticket->create($user);

        if ($result > 0) {
            setEventMessages($langs->trans("TicketCreated"), null, 'mesgs');
            // Redirect to avoid resubmission
            header("Location: " . $_SERVER['PHP_SELF'] . '?from_id=' . $objectId . '&from_type=' . $objectType);
            exit;
        } else {
            setEventMessages($ticket->error, $ticket->errors, 'errors');
        }
    }
}

/*
*	View
*/
$title   = $langs->trans('Commerce');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

if ($socid > 0) {
    if ($objectType == 'project') {
        saturne_get_fiche_head($project, 'event', $title);
        $morehtml = '<a href="' . dol_buildpath('/' . $project->element . '/list.php', 1) . '?restore_lastsearch_values=1&from_type=' . $project->element . '">' . $langs->trans('BackToList') . '</a>';
        saturne_banner_tab($project, 'ref', $morehtml, 1, 'ref', 'ref', '', !empty($societe->photo));

    } else if ($objectType == 'societe') {
        saturne_get_fiche_head($societe, 'event', $title);
        $morehtml = '<a href="' . dol_buildpath('/' . $societe->element . '/list.php', 1) . '?restore_lastsearch_values=1&from_type=' . $societe->element . '">' . $langs->trans('BackToList') . '</a>';
        saturne_banner_tab($societe, 'ref', $morehtml, 1, 'ref', 'ref', '', !empty($societe->photo));
    }


    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    $current_tab = GETPOST('tab', 'aZ09');
    if (empty($current_tab)) $current_tab = 'note';

    print '<div class="tabs">';

    $isactive = ($current_tab == 'note');
    print '<div class="inline-block tabsElem'.($isactive ? ' tabsElemActive' : '').'">';
    print '<div class="tab tab'.($isactive ? 'active' : 'unactive').'" style="margin: 0 !important">';
    print '<a class="tab inline-block valignmiddle" href="'.$_SERVER['PHP_SELF'].'?from_id='.$objectId.'&from_type='. $objectType .'&tab=note" title="'.$langs->trans("CustomerNote").'">';
    print img_picto($langs->trans("CommercialRelaunching"), 'object_contact', '', 0, 0, 0, '', 'imgTabTitle paddingright marginrightonlyshort');
    print $langs->trans("CommercialRelaunching");
    print '</a>';
    print '</div>';
    print '</div>';

    $isactive = ($current_tab == 'email');
    print '<div class="inline-block tabsElem'.($isactive ? ' tabsElemActive' : '').'">';
    print '<div class="tab tab'.($isactive ? 'active' : 'unactive').'" style="margin: 0 !important">';
    print '<a class="tab inline-block valignmiddle" href="'.$_SERVER['PHP_SELF'].'?from_id='.$objectId.'&from_type='. $objectType .'&tab=email" title="'.$langs->trans("SendEmail").'">';
    print img_picto($langs->trans("SendEmail"), 'email', '', 0, 0, 0, '', 'imgTabTitle paddingright marginrightonlyshort');
    print $langs->trans("SendEmail");
    print '</a>';
    print '</div>';
    print '</div>';

    // Ticket tab
    if (isModEnabled('ticket')) {
        $isactive = ($current_tab == 'ticket');
        print '<div class="inline-block tabsElem'.($isactive ? ' tabsElemActive' : '').'">';
        print '<div class="tab tab'.($isactive ? 'active' : 'unactive').'" style="margin: 0 !important">';
        print '<a class="tab inline-block valignmiddle" href="'.$_SERVER['PHP_SELF'].'?from_id='.$objectId.'&from_type='. $objectType .'&tab=ticket" title="'.$langs->trans("Ticket").'">';
        print img_picto($langs->trans("Ticket"), 'ticket', '', 0, 0, 0, '', 'imgTabTitle paddingright marginrightonlyshort');
        print $langs->trans("Ticket");
        print '</a>';
        print '</div>';
        print '</div>';
    }

    print '</div>';

    print '<div class="fichehalfleft">';
    print '<div class="div-table-responsive-no-min">';


    print '<div class="tab-content">';

    if ($current_tab == 'note') {
        print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" class="border" id="addeventform">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="addevent">';
        print '<input type="hidden" name="from_id" value="'.((int) $objectId).'">';
        print '<input type="hidden" name="from_type" value="'.$objectType.'">';
        print '<input type="hidden" name="tab" value="'.$current_tab.'">';

        print '<table class="border centpercent">';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("ThirdParty").'</td>';
        print '<td>';
        print $form->select_company($societe->id, 'socid', '', 'minwidth200', 0, 0, array(), 0, 'maxwidth300');
        print '</td>';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans('Contact').'</td>';
        print '<td colspan="3">';
        print $form->selectcontacts($societe->id, '', 'contactid', 0, '', 0, 'minwidth200', 0, '', 0, '', 1);
        print '</td>';
        print '</tr>';


        print '<td class="nowrap">';
        print $langs->trans("Type").'</td><td>';
        print $formactions->select_type_actions(GETPOSTISSET('actioncode') ? GETPOST('actioncode', 'aZ09') : $conf->global->REEDCRM_EVENT_TYPE_CODE_VALUE, 'actioncode', 'systemauto', 0, -1, 0, 1);
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td>'.$langs->trans("Date").'</td><td>';
        print $form->selectDate(dol_now(), 'event_', 1, 1, 0, '', 1, 0);
        print '</td>';
        print '</tr>';
        print '<tr class="oddeven">';
        print '<td>'.$langs->trans("Project").'</td><td colspan="3">';
        print $formproject->select_projects($societe->id, $project->id, 'project_id');
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td>'.$langs->trans("Title").'</td><td colspan="3">';
        print '<input type="text" name="label" class="quatrevingtpercent" maxlength="50" placeholder="'.$langs->trans("Title").'">';
        print '</td>';
        print '</tr>';
        print '<tr class="oddeven">';
        print '<td class="tdtop">'.$langs->trans("Note").'</td><td colspan="3">';
        print '<textarea name="note" class="quatrevingtpercent" rows="6" placeholder="'.$langs->trans("Note").'"></textarea>';
        print '</td>';
        print '</tr>';
        print '</table>';

        if ($permissiontoadd) {
            print '<div class="center" style="margin-top: 10px;">';
            print '<button type="submit" class="butAction">'.$langs->trans("Add").'</button>';
            print '</div>';
        }
    } elseif ($current_tab == 'email') {
        $langs->load("mails");

        $formmail = new FormMail($db);
        $formmail->param['langsmodels'] = $langs->defaultlang;
        $formmail->fromtype = 'user';
        $formmail->fromid = $user->id;
        $formmail->trackid = 'thi'.$societe->id;
        $formmail->withfrom = 1;
        $formmail->withlayout = 'email';
        $formmail->withaiprompt = 'html';

        $liste = array();
        foreach ($societe->thirdparty_and_contact_email_array(1) as $key => $value) {
            $liste[$key] = $value;
        }

        // Find all extrnal contact addresses
        $contactarr = $societe->liste_contact(-1, 'external', 0, '', 1);
        if (is_array($contactarr) && count($contactarr) > 0) {
            require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
            $contactstatic = new Contact($db);
            $tmpcompany = new Societe($db);

            foreach ($contactarr as $contact) {
                $contactstatic->fetch($contact['id']);
                if (empty($liste[$contact['id']])) {
                    $contacttoshow = '';
                    if ($contactstatic->fk_soc != $societe->id) {
                        $tmpcompany->fetch($contactstatic->fk_soc);
                        if ($tmpcompany->id > 0) {
                            $contacttoshow .= $tmpcompany->name.': ';
                        }
                    }
                    $contacttoshow .= $contactstatic->getFullName($langs, 1);
                    $contacttoshow .= " <".($contactstatic->email ? $contactstatic->email : $langs->transnoentitiesnoconv("NoEMail")) .">";
                    $liste[$contact['id']] = $contacttoshow;
                }
            }
        }

        $formmail->withto = $liste;
        $formmail->withtofree = '1';
        $formmail->withtocc = $liste;
        $formmail->withtoccc = getDolGlobalString('MAIN_EMAIL_USECCC');
        $formmail->withtopic = $langs->trans('Information', '__REF__');
        $formmail->withfile = 2;
        $formmail->withbody = 1;
        $formmail->withdeliveryreceipt = 1;
        $formmail->withcancel = 1;

        $substitutionarray = getCommonSubstitutionArray($langs, 0, null, $societe);
        $formmail->substit = $substitutionarray;

        $formmail->param['action'] = 'send';
        $formmail->param['models'] = 'thirdparty';
        $formmail->param['id'] = $societe->id;
        $formmail->param['returnurl'] = $_SERVER["PHP_SELF"].'?from_id='.$societe->id;
        $formmail->param['object_entity'] = $societe->entity;

        print $formmail->get_form();
    } elseif ($current_tab == 'ticket' && isModEnabled('ticket')) {
        // Ticket creation form
        print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" class="border" id="createticketform">';
        print '<input type="hidden" name="token" value="'.newToken().'">';
        print '<input type="hidden" name="action" value="createticket">';
        print '<input type="hidden" name="from_id" value="'.((int) $objectId).'">';
        print '<input type="hidden" name="from_type" value="'.$objectType.'">';
        print '<input type="hidden" name="tab" value="'.$current_tab.'">';

        print '<table class="border centpercent">';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("Type").'</td>';
        print '<td>';
        if ($formticket) {
            print $formticket->selectTypesTickets(GETPOST('ticket_type', 'aZ09'), 'ticket_type', '', 2);
        } else {
            print '<input type="text" name="ticket_type" class="minwidth200" value="'.GETPOST('ticket_type', 'aZ09').'">';
        }
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("Label").'</td>';
        print '<td>';
        print '<input type="text" name="ticket_subject" class="quatrevingtpercent" maxlength="255" value="'.GETPOST('ticket_subject', 'alphanohtml').'" required>';
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("DateStart").'</td>';
        print '<td>';
        print $form->select_date(GETPOST('ticket_date_start', 'int') ? GETPOST('ticket_date_start', 'int') : dol_now(), 'ticket_date_start', 1, 1, 1, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzuserrel');
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("TimeSpent").'</td>';
        print '<td>';
        print '<input type="number" name="ticket_timing" class="minwidth100" min="0" value="'.GETPOST('ticket_timing', 'int').'"> '.$langs->trans("Minutes");
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("Reminder").'</td>';
        print '<td>';
        print '<select name="ticket_reminder" class="minwidth200">';
        print '<option value="">'.$langs->trans("None").'</option>';
        print '<option value="15"'.(GETPOST('ticket_reminder', 'int') == 15 ? ' selected' : '').'>15 '.$langs->trans("Minutes").'</option>';
        print '<option value="30"'.(GETPOST('ticket_reminder', 'int') == 30 ? ' selected' : '').'>30 '.$langs->trans("Minutes").'</option>';
        print '<option value="60"'.(GETPOST('ticket_reminder', 'int') == 60 ? ' selected' : '').'>1 '.$langs->trans("Hour").'</option>';
        print '<option value="120"'.(GETPOST('ticket_reminder', 'int') == 120 ? ' selected' : '').'>2 '.$langs->trans("Hours").'</option>';
        print '<option value="240"'.(GETPOST('ticket_reminder', 'int') == 240 ? ' selected' : '').'>4 '.$langs->trans("Hours").'</option>';
        print '<option value="480"'.(GETPOST('ticket_reminder', 'int') == 480 ? ' selected' : '').'>8 '.$langs->trans("Hours").'</option>';
        print '<option value="1440"'.(GETPOST('ticket_reminder', 'int') == 1440 ? ' selected' : '').'>1 '.$langs->trans("Day").'</option>';
        print '</select>';
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("ThirdParty").'</td>';
        print '<td>';
        print $form->select_company($societe->id, 'ticket_socid', '', 'minwidth200', 0, 0, array(), 0, 'maxwidth300');
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td>'.$langs->trans("Project").'</td><td colspan="3">';
        print $formproject->select_projects($societe->id, $project->id, 'project_id');
        print '</td>';
        print '</tr>';

        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("AssignedTo").'</td>';
        print '<td>';
        print $form->select_dolusers(GETPOST('ticket_user_assign', 'int')?:$user->id, 'ticket_user_assign', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 1);
        print '</td>';
        print '</tr>';

        print '</table>';

        print '<table class="border centpercent">';
        print '<tr class="oddeven">';
        print '<td class="tdtop">'.$langs->trans("Description").'</td>';
        print '<td>';
        $doleditor = new DolEditor('ticket_message', GETPOST('ticket_message', 'restricthtml'), '', 200, 'dolibarr_notes', 'In', false, true, true, 10, 200);
        $doleditor->Create();
        print '</td>';
        print '</tr>';
        print '</table>';

        print '<table class="border centpercent">';
        print '<tr class="oddeven">';
        print '<td class="titlefield">'.$langs->trans("Categories").'</td>';
        print '<td>';
        if ($formticket) {
            print $formticket->selectGroupTickets(GETPOST('ticket_category', 'aZ09'), 'ticket_category', '', 1);
        } else {
            print '<input type="text" name="ticket_category" class="minwidth200" value="'.GETPOST('ticket_category', 'aZ09').'">';
        }
        print '</td>';
        print '</tr>';
        print '</table>';
        if ($permissiontoadd) {
            print '<div class="center" style="margin-top: 10px;">';
            print '<button type="submit" form="createticketform" class="butAction">'.$langs->trans("CreateTicket").'</button>';
            print '</div>';
        }
        print '</form>';
    }

    print '</div>';

     print '</div>';
     print '</div>';

    print '<div class="fichehalfright">';

    $boxstat = '';
    $MAXLIST = getDolGlobalString('MAIN_SIZE_SHORTLIST_LIMIT');

    $boxstat .= '<div class="box divboxtable box-halfright">';
    $boxstat .= '<table summary="'.dol_escape_htmltag($langs->trans("DolibarrStateBoard")).'" class="border boxtable boxtablenobottom boxtablenotop boxtablenomarginbottom centpercent">';
    $boxstat .= '<tr class="impair nohover"><td colspan="2" class="tdboxstats nohover">';

    if (isModEnabled("propal") && $user->hasRight('propal', 'lire')) {
        // Box proposals
        $tmp = $societe->getOutstandingProposals();
        $outstandingOpened = $tmp['opened'];
        $outstandingTotal = $tmp['total_ht'];
        $outstandingTotalIncTax = $tmp['total_ttc'];
        $text = $langs->trans("OverAllProposals");
        $link = DOL_URL_ROOT.'/comm/propal/list.php?socid='.$societe->id;
        $icon = 'bill';
        if ($link) {
            $boxstat .= '<a href="'.$link.'" class="boxstatsindicator thumbstat nobold nounderline">';
        }
        $boxstat .= '<div class="boxstats" title="'.dol_escape_htmltag($text).'">';
        $boxstat .= '<span class="boxstatstext">'.img_object("", $icon).' <span>'.$text.'</span></span><br>';
        $boxstat .= '<span class="boxstatsindicator">'.price($outstandingTotal, 1, $langs, 1, -1, -1, $conf->currency).'</span>';
        $boxstat .= '</div>';
        if ($link) {
            $boxstat .= '</a>';
        }
    }

    if (isModEnabled('order') && $user->hasRight('commande', 'lire')) {
        // Box orders
        $tmp = $societe->getOutstandingOrders();
        $outstandingOpened = $tmp['opened'];
        $outstandingTotal = $tmp['total_ht'];
        $outstandingTotalIncTax = $tmp['total_ttc'];
        $text = $langs->trans("OverAllOrders");
        $link = DOL_URL_ROOT.'/commande/list.php?socid='.$societe->id;
        $icon = 'bill';
        if ($link) {
            $boxstat .= '<a href="'.$link.'" class="boxstatsindicator thumbstat nobold nounderline">';
        }
        $boxstat .= '<div class="boxstats" title="'.dol_escape_htmltag($text).'">';
        $boxstat .= '<span class="boxstatstext">'.img_object("", $icon).' <span>'.$text.'</span></span><br>';
        $boxstat .= '<span class="boxstatsindicator">'.price($outstandingTotal, 1, $langs, 1, -1, -1, $conf->currency).'</span>';
        $boxstat .= '</div>';
        if ($link) {
            $boxstat .= '</a>';
        }
    }

    if (isModEnabled('invoice') && $user->hasRight('facture', 'lire')) {
        // Box invoices
        $tmp = $societe->getOutstandingBills('customer', 0);
        $outstandingOpened = $tmp['opened'];
        $outstandingTotal = $tmp['total_ht'];
        $outstandingTotalIncTax = $tmp['total_ttc'];

        $text = $langs->trans("OverAllInvoices");
        $link = DOL_URL_ROOT.'/compta/facture/list.php?socid='.$societe->id;
        $icon = 'bill';
        if ($link) {
            $boxstat .= '<a href="'.$link.'" class="boxstatsindicator thumbstat nobold nounderline">';
        }
        $boxstat .= '<div class="boxstats" title="'.dol_escape_htmltag($text).'">';
        $boxstat .= '<span class="boxstatstext">'.img_object("", $icon).' <span>'.$text.'</span></span><br>';
        $boxstat .= '<span class="boxstatsindicator">'.price($outstandingTotal, 1, $langs, 1, -1, -1, $conf->currency).'</span>';
        $boxstat .= '</div>';
        if ($link) {
            $boxstat .= '</a>';
        }

        // Box outstanding bill
        $warn = '';
        if ($societe->outstanding_limit != '' && $societe->outstanding_limit < $outstandingOpened) {
            $warn = ' '.img_warning($langs->trans("OutstandingBillReached"));
        }
        $text = $langs->trans("CurrentOutstandingBill");
        $link = DOL_URL_ROOT.'/compta/recap-compta.php?socid='.$societe->id;
        $icon = 'bill';
        if ($link) {
            $boxstat .= '<a href="'.$link.'" class="boxstatsindicator thumbstat nobold nounderline">';
        }
        $boxstat .= '<div class="boxstats" title="'.dol_escape_htmltag($text).'">';
        $boxstat .= '<span class="boxstatstext">'.img_object("", $icon).' <span>'.$text.'</span></span><br>';
        $boxstat .= '<span class="boxstatsindicator'.($outstandingOpened > 0 ? ' amountremaintopay' : '').'">'.price($outstandingOpened, 1, $langs, 1, -1, -1, $conf->currency).$warn.'</span>';
        $boxstat .= '</div>';
        if ($link) {
            $boxstat .= '</a>';
        }
    }

    $boxstat .= '</td></tr>';
    $boxstat .= '</table>';
    $boxstat .= '</div>';

    print $boxstat;

    // Latest proposals (from comm/card.php)
    if (isModEnabled("propal") && $user->hasRight('propal', 'lire')) {
        $langs->load("propal");

        $sql = "SELECT s.nom, s.rowid, p.rowid as propalid, p.fk_projet, p.fk_statut, p.total_ht";
        $sql .= ", p.total_tva";
        $sql .= ", p.total_ttc";
        $sql .= ", p.ref, p.ref_client, p.remise";
        $sql .= ", p.datep as dp, p.fin_validite as date_limit, p.entity";
        $sql .= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."propal as p, ".MAIN_DB_PREFIX."c_propalst as c";
        $sql .= " WHERE p.fk_soc = s.rowid AND p.fk_statut = c.id";
        $sql .= " AND s.rowid = ".((int) $societe->id);
        $sql .= " AND p.entity IN (".getEntity('propal').")";
        $sql .= " ORDER BY p.datep DESC";

        $resql = $db->query($sql);
        if ($resql) {
            $propal_static = new Propal($db);

            $num = $db->num_rows($resql);
            if ($num > 0) {
                print '<div class="div-table-responsive-no-min">';
                print '<table class="noborder centpercent lastrecordtable">';

                print '<tr class="liste_titre">';
                print '<td colspan="5"><table width="100%" class="nobordernopadding"><tr><td>'.$langs->trans("LastPropals", ($num <= $MAXLIST ? "" : $MAXLIST)).'</td><td class="right"><a class="notasortlink" href="'.DOL_URL_ROOT.'/comm/propal/list.php?socid='.$societe->id.'"><span class="hideonsmartphone">'.$langs->trans("AllPropals").'</span><span class="badge marginleftonlyshort">'.$num.'</span></a></td>';
                print '<td width="20px" class="right"><a href="'.DOL_URL_ROOT.'/comm/propal/stats/index.php?socid='.$societe->id.'">'.img_picto($langs->trans("Statistics"), 'stats').'</a></td>';
                print '</tr></table></td>';
                print '</tr>';
            }

            $i = 0;
            while ($i < $num && $i < $MAXLIST) {
                $objp = $db->fetch_object($resql);

                print '<tr class="oddeven">';
                print '<td class="nowraponall">';
                $propal_static->id = $objp->propalid;
                $propal_static->ref = $objp->ref;
                $propal_static->ref_client = $objp->ref_client;
                $propal_static->ref_customer = $objp->ref_client;
                $propal_static->fk_project = $objp->fk_projet;
                $propal_static->total_ht = $objp->total_ht;
                $propal_static->total_tva = $objp->total_tva;
                $propal_static->total_ttc = $objp->total_ttc;
                print $propal_static->getNomUrl(1);
                print '</td><td class="tdoverflowmax125">';
                if ($propal_static->fk_project > 0) {
                    $project = new Project($db);
                    $project->fetch($propal_static->fk_project);
                    print $project->getNomUrl(1);
                }
                if (($db->jdate($objp->date_limit) < ($now - $conf->propal->cloture->warning_delay)) && $objp->fk_statut == $propal_static::STATUS_VALIDATED) {
                    print " ".img_warning();
                }
                print '</td><td class="right" width="80px">'.dol_print_date($db->jdate($objp->dp), 'day')."</td>\n";
                print '<td class="right nowraponall">'.price($objp->total_ht).'</td>';
                print '<td class="right" style="min-width: 60px" class="nowrap">'.$propal_static->LibStatut($objp->fk_statut, 5).'</td></tr>';
                $i++;
            }
            $db->free($resql);

            if ($num > 0) {
                print "</table>";
                print '</div>';
            }
        } else {
            dol_print_error($db);
        }
    }

    // Latest projects (from comm/card.php)
    if (isModEnabled('project') && $user->hasRight('projet', 'lire')) {
        $langs->load("projects");

        $sql = "SELECT s.nom, s.rowid, p.rowid as projectid, p.ref, p.title, p.fk_statut, p.datec, p.dateo, p.date_close, p.budget_amount";
        $sql .= " FROM ".MAIN_DB_PREFIX."societe as s, ".MAIN_DB_PREFIX."projet as p";
        $sql .= " WHERE p.fk_soc = s.rowid";
        $sql .= " AND s.rowid = ".((int) $societe->id);
        $sql .= " AND p.entity IN (".getEntity('project').")";
        $sql .= " ORDER BY p.datec DESC";

        $resql = $db->query($sql);
        if ($resql) {
            $project_static = new Project($db);

            $num = $db->num_rows($resql);
            if ($num > 0) {
                print '<div class="div-table-responsive-no-min">';
                print '<table class="noborder centpercent lastrecordtable">';

                print '<tr class="liste_titre">';
                print '<td colspan="4"><table width="100%" class="nobordernopadding"><tr><td>'.$langs->trans("LastProjects", ($num <= $MAXLIST ? "" : $MAXLIST)).'</td><td class="right"><a class="notasortlink" href="'.DOL_URL_ROOT.'/projet/list.php?socid='.$societe->id.'"><span class="hideonsmartphone">'.$langs->trans("AllProjects").'</span><span class="badge marginleftonlyshort">'.$num.'</span></a></td>';
                print '<td width="20px" class="right"><a href="'.DOL_URL_ROOT.'/projet/stats/index.php?socid='.$societe->id.'">'.img_picto($langs->trans("Statistics"), 'stats').'</a></td>';
                print '</tr></table></td>';
                print '</tr>';
            }

            $i = 0;
            while ($i < $num && $i < $MAXLIST) {
                $objp = $db->fetch_object($resql);

                print '<tr class="oddeven">';
                print '<td class="nowraponall">';
                $project_static->id = $objp->projectid;
                $project_static->ref = $objp->ref;
                $project_static->title = $objp->title;
                $project_static->budget_amount = $objp->budget_amount;
                print $project_static->getNomUrl(1);
                print '</td><td class="tdoverflowmax125">';
                print dol_trunc($objp->title, 30);
                print '</td><td class="right" width="80px">'.dol_print_date($db->jdate($objp->datec), 'day')."</td>\n";
                print '<td class="right" style="min-width: 60px" class="nowrap">'.$project_static->LibStatut($objp->fk_statut, 5).'</td></tr>';
                $i++;
            }
            $db->free($resql);

            if ($num > 0) {
                print "</table>";
                print '</div>';
            }
        } else {
            dol_print_error($db);
        }
    }

    print '</div>';

    print '</div>';
}


llxFooter();

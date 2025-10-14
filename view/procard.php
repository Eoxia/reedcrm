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
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/doleditor.class.php';
if (isModEnabled('project'))  require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$socid = GETPOSTINT('from_id');

// Security check
$result = restrictedArea($user, 'societe', $socid, 'societe&societe');

// Initialize objects
$object = new Societe($db);
$form        = new Form($db);
$formmail    = new FormMail($db);
$formproject = (isModEnabled('project') ? new FormProjets($db) : null);
$formactions = new FormActions($db);

// Load third party
if ($socid > 0) {
    $result = $object->fetch($socid);
    if ($result < 0) {
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

if (empty($object->id)) {
    accessforbidden();
}
$title   = $langs->trans('Commerce');
$helpUrl = 'FR:Module_ReedCRM';
saturne_header(0, '', $title, $helpUrl);

// Process form submission
if (GETPOST('action') == 'addevent') {
    require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

    $actioncomm = new ActionComm($db);

    // Get form data
    $actioncomm->label = GETPOST('label', 'alphanohtml');
    $actioncomm->note_private = GETPOST('note', 'restricthtml');
    $actioncomm->socid = $object->id;
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

    // Handle email data if email tab is active
    $current_tab = GETPOST('tab', 'aZ09');
    if ($current_tab == 'email') {
        // For email tab, we don't create an event, we let the FormMail handle the email sending
        // The FormMail will handle the email sending through its own form processing
        setEventMessages($langs->trans("EmailFormReady"), null, 'mesgs');
    } else {
        // Create the event only for note tab
        $result = $actioncomm->create($user);
        
        if ($result > 0) {
            setEventMessages($langs->trans("EventCreated"), null, 'mesgs');
            // Redirect to avoid resubmission
            header("Location: ".$_SERVER['PHP_SELF'].'?from_id='.$object->id);
            exit;
        } else {
            setEventMessages($actioncomm->error, $actioncomm->errors, 'errors');
        }
    }
}


if ($socid > 0) {
    saturne_get_fiche_head($object, 'event', $title);

    $morehtml = '<a href="' . dol_buildpath('/' . $object->element . '/list.php', 1) . '?restore_lastsearch_values=1&from_type=' . $object->element . '">' . $langs->trans('BackToList') . '</a>';
    saturne_banner_tab($object, 'ref', $morehtml, 1, 'ref', 'ref', '', !empty($object->photo));

    print '<div class="fichecenter">';
    print '<div class="underbanner clearboth"></div>';

    // Left column - Event creation form
    print '<div class="fichehalfleft">';
    print '<div class="div-table-responsive-no-min">';

    print '<form action="'.$_SERVER['PHP_SELF'].'" method="POST" class="border" id="addeventform">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="addevent">';
    print '<input type="hidden" name="from_id" value="'.((int) $object->id).'">';

    print '<table class="border centpercent">';

    print '<tr class="oddeven">';
    print '<td class="titlefield">'.$langs->trans("ThirdParty").'</td>';
    print '<td>';
    print $form->select_company($object->id, 'socid', '', 'minwidth200', 0, 0, array(), 0, 'maxwidth300');  // readonly look
    print '</td>';

    print '<tr class="oddeven">';
    print '<td class="titlefield">'.$langs->trans('Contact').'</td>';
    print '<td colspan="3">';
    print $form->selectcontacts($object->id, '', 'contactid', 0, '', 0, 'minwidth200', 0, '', 0, '', 1);
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
    print $formproject->select_projects($object->id, '', 'project_id', 0, 0, 1, 0, 0, 0, '', 1, $object->id);
    print '</td>';
    print '</tr>';

    print '</table>';

    // Tabs like banner_tab
    $current_tab = GETPOST('tab', 'aZ09');
    if (empty($current_tab)) $current_tab = 'note';

    print '<div class="tabs">';

    // Note client tab
    $isactive = ($current_tab == 'note');
    print '<div class="inline-block tabsElem'.($isactive ? ' tabsElemActive' : '').'">';
    print '<div class="tab tab'.($isactive ? 'active' : 'unactive').'" style="margin: 0 !important">';
    print '<a class="tab inline-block valignmiddle" href="'.$_SERVER['PHP_SELF'].'?from_id='.$object->id.'&tab=note" title="'.$langs->trans("CustomerNote").'">';
    print img_picto($langs->trans("CustomerNote"), 'object_contact', '', 0, 0, 0, '', 'imgTabTitle paddingright marginrightonlyshort');
    print $langs->trans("CustomerNote");
    print '</a>';
    print '</div>';
    print '</div>';

    // Envoyer email tab
    $isactive = ($current_tab == 'email');
    print '<div class="inline-block tabsElem'.($isactive ? ' tabsElemActive' : '').'">';
    print '<div class="tab tab'.($isactive ? 'active' : 'unactive').'" style="margin: 0 !important">';
    print '<a class="tab inline-block valignmiddle" href="'.$_SERVER['PHP_SELF'].'?from_id='.$object->id.'&tab=email" title="'.$langs->trans("SendEmail").'">';
    print img_picto($langs->trans("SendEmail"), 'email', '', 0, 0, 0, '', 'imgTabTitle paddingright marginrightonlyshort');
    print $langs->trans("SendEmail");
    print '</a>';
    print '</div>';
    print '</div>';

    print '</div>';

    // Tab content
    print '<div class="tab-content">';

    if ($current_tab == 'note') {
        // Note client content
        print '<table class="border centpercent">';
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
    } elseif ($current_tab == 'email') {
        // Email content - Use Dolibarr standard email form
        $langs->load("mails");
        
        // Initialize FormMail for email form
        $formmail = new FormMail($db);
        $formmail->param['langsmodels'] = $langs->defaultlang;
        $formmail->fromtype = 'user';
        $formmail->fromid = $user->id;
        $formmail->trackid = 'thi'.$object->id;
        $formmail->withfrom = 1;
        $formmail->withlayout = 'email';
        $formmail->withaiprompt = 'html';
        
        // Define recipients list (third party and contacts)
        $liste = array();
        foreach ($object->thirdparty_and_contact_email_array(1) as $key => $value) {
            $liste[$key] = $value;
        }
        
        // Find all external contact addresses
        $contactarr = $object->liste_contact(-1, 'external', 0, '', 1);
        if (is_array($contactarr) && count($contactarr) > 0) {
            require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
            $contactstatic = new Contact($db);
            $tmpcompany = new Societe($db);
            
            foreach ($contactarr as $contact) {
                $contactstatic->fetch($contact['id']);
                if (empty($liste[$contact['id']])) {
                    $contacttoshow = '';
                    if ($contactstatic->fk_soc != $object->id) {
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
        
        // Set substitution array
        $substitutionarray = getCommonSubstitutionArray($langs, 0, null, $object);
        $formmail->substit = $substitutionarray;
        
        // Array of other parameters
        $formmail->param['action'] = 'send';
        $formmail->param['models'] = 'thirdparty';
        $formmail->param['id'] = $object->id;
        $formmail->param['returnurl'] = $_SERVER["PHP_SELF"].'?from_id='.$object->id;
        $formmail->param['object_entity'] = $object->entity;
        
        // Show form
        print $formmail->get_form();
    }

    print '</div>';

    // Submit button (only for note tab)
    if ($current_tab == 'note') {
        print '<div class="center" style="margin-top: 10px;">';
        print '<button type="submit" class="butAction">'.$langs->trans("Add").'</button>';
        print '</div>';
    }

    print '</form>';

     print '</div>'; // div-table-responsive-no-min
     print '</div>'; // fichehalfleft

    // Right column - Widgets from comm/card.php
    print '<div class="fichehalfright">';

    // Box statistics (from comm/card.php)
    $boxstat = '';
    $MAXLIST = getDolGlobalString('MAIN_SIZE_SHORTLIST_LIMIT');

    // Link summary/status board
    $boxstat .= '<div class="box divboxtable box-halfright">';
    $boxstat .= '<table summary="'.dol_escape_htmltag($langs->trans("DolibarrStateBoard")).'" class="border boxtable boxtablenobottom boxtablenotop boxtablenomarginbottom centpercent">';
    $boxstat .= '<tr class="impair nohover"><td colspan="2" class="tdboxstats nohover">';

    if (isModEnabled("propal") && $user->hasRight('propal', 'lire')) {
        // Box proposals
        $tmp = $object->getOutstandingProposals();
        $outstandingOpened = $tmp['opened'];
        $outstandingTotal = $tmp['total_ht'];
        $outstandingTotalIncTax = $tmp['total_ttc'];
        $text = $langs->trans("OverAllProposals");
        $link = DOL_URL_ROOT.'/comm/propal/list.php?socid='.$object->id;
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
        $tmp = $object->getOutstandingOrders();
        $outstandingOpened = $tmp['opened'];
        $outstandingTotal = $tmp['total_ht'];
        $outstandingTotalIncTax = $tmp['total_ttc'];
        $text = $langs->trans("OverAllOrders");
        $link = DOL_URL_ROOT.'/commande/list.php?socid='.$object->id;
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
        $tmp = $object->getOutstandingBills('customer', 0);
        $outstandingOpened = $tmp['opened'];
        $outstandingTotal = $tmp['total_ht'];
        $outstandingTotalIncTax = $tmp['total_ttc'];

        $text = $langs->trans("OverAllInvoices");
        $link = DOL_URL_ROOT.'/compta/facture/list.php?socid='.$object->id;
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
        if ($object->outstanding_limit != '' && $object->outstanding_limit < $outstandingOpened) {
            $warn = ' '.img_warning($langs->trans("OutstandingBillReached"));
        }
        $text = $langs->trans("CurrentOutstandingBill");
        $link = DOL_URL_ROOT.'/compta/recap-compta.php?socid='.$object->id;
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
        $sql .= " AND s.rowid = ".((int) $object->id);
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
                print '<td colspan="5"><table width="100%" class="nobordernopadding"><tr><td>'.$langs->trans("LastPropals", ($num <= $MAXLIST ? "" : $MAXLIST)).'</td><td class="right"><a class="notasortlink" href="'.DOL_URL_ROOT.'/comm/propal/list.php?socid='.$object->id.'"><span class="hideonsmartphone">'.$langs->trans("AllPropals").'</span><span class="badge marginleftonlyshort">'.$num.'</span></a></td>';
                print '<td width="20px" class="right"><a href="'.DOL_URL_ROOT.'/comm/propal/stats/index.php?socid='.$object->id.'">'.img_picto($langs->trans("Statistics"), 'stats').'</a></td>';
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
        $sql .= " AND s.rowid = ".((int) $object->id);
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
                print '<td colspan="4"><table width="100%" class="nobordernopadding"><tr><td>'.$langs->trans("LastProjects", ($num <= $MAXLIST ? "" : $MAXLIST)).'</td><td class="right"><a class="notasortlink" href="'.DOL_URL_ROOT.'/projet/list.php?socid='.$object->id.'"><span class="hideonsmartphone">'.$langs->trans("AllProjects").'</span><span class="badge marginleftonlyshort">'.$num.'</span></a></td>';
                print '<td width="20px" class="right"><a href="'.DOL_URL_ROOT.'/projet/stats/index.php?socid='.$object->id.'">'.img_picto($langs->trans("Statistics"), 'stats').'</a></td>';
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

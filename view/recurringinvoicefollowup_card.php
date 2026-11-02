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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    view/recurringinvoicefollowup_card.php
 * \ingroup reedcrm
 * \brief   Page to create/edit/view a recurring invoice follow-up.
 */

// Load ReedCRM environment.
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

// Load ReedCRM libraries.
require_once __DIR__ . '/../class/recurringinvoicefollowup.class.php';
require_once __DIR__ . '/../lib/reedcrm_followup.lib.php';

global $conf, $db, $hookmanager, $langs, $moduleNameLowerCase, $user;

// Load translation files required by the page.
saturne_load_langs();

// Get parameters.
$id                  = GETPOSTINT('id');
$ref                 = GETPOST('ref', 'alpha');
$action              = GETPOST('action', 'aZ09');
$confirm             = GETPOST('confirm', 'alpha');
$cancel              = GETPOST('cancel', 'aZ09');
$contextpage         = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : 'recurringinvoicefollowupcard';
$backtopage          = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize technical objects.
$object      = new RecurringInvoiceFollowup($db);
$extrafields = new ExtraFields($db);
$form        = new Form($db);

// Initialize view objects.
$hookmanager->initHooks(['recurringinvoicefollowupcard', 'globalcard']);

// Load object.
$object->fetch($id, $ref);

// Load numbering module.
$numberingModules = [$object->element => getDolGlobalString('REEDCRM_RECURRINGINVOICEFOLLOWUP_ADDON')];
list($refReedcrmMod) = saturne_require_objects_mod($numberingModules, $moduleNameLowerCase);

// Opened from a recurring template (frec): find its annotation follow-up, or create it on the fly
// (the follow-up is now an annotation store keyed by the active recurring template).
$frec = GETPOSTINT('frec');
if ($id <= 0 && $ref === '' && $frec > 0) {
    $resFrec = $db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'reedcrm_facturerec_followup WHERE fk_facture_rec = ' . $frec . ' AND entity IN (' . getEntity('reedcrm_facturerec_followup') . ') ' . $db->plimit(1));
    if ($resFrec && $rowFrec = $db->fetch_object($resFrec)) {
        $object->fetch((int) $rowFrec->rowid);
    } else {
        $resTpl = $db->query('SELECT fk_soc, titre, total_ttc, date_when FROM ' . MAIN_DB_PREFIX . 'facture_rec WHERE rowid = ' . $frec . ' AND entity IN (' . getEntity('facturerec') . ')');
        if ($resTpl && $tpl = $db->fetch_object($resTpl)) {
            $object->fk_soc         = (int) $tpl->fk_soc;
            $object->fk_facture_rec = $frec;
            $object->period         = !empty($tpl->date_when) ? $db->jdate($tpl->date_when) : dol_now();
            $object->montant_ttc    = (float) $tpl->total_ttc;
            $object->prestation     = reedcrmFollowupGuessPrestation((string) $tpl->titre);
            $object->temps_sav      = reedcrmFollowupSavSecondsForPrestation($object->prestation);
            $object->status         = $object::STATUS_ACTIVE;
            if ($object->create($user) > 0) {
                $id = $object->id;
            }
        }
    }
}

// Security check.
$permissiontoread   = $user->hasRight('reedcrm', 'followup', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'followup', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'followup', 'delete');

saturne_check_access($permissiontoread, $object);

/*
 * Actions
 */

$parameters = [];
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
    $backurlforlist = dol_buildpath('/reedcrm/view/recurringinvoicefollowup_list.php', 1);

    if (empty($backtopage) || ($cancel && empty($id))) {
        if (empty($backtopage) || ($cancel && strpos($backtopage, '__ID__'))) {
            if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
                $backtopage = $backurlforlist;
            } else {
                $backtopage = dol_buildpath('/reedcrm/view/recurringinvoicefollowup_card.php', 1) . '?id=' . ($id > 0 ? $id : '__ID__');
            }
        }
    }

    $triggermodname = 'REEDCRM_RECURRINGINVOICEFOLLOWUP_MODIFY';

    // Actions cancel, add, update, delete... managed by the generic include.
    require_once DOL_DOCUMENT_ROOT . '/core/actions_addupdatedelete.inc.php';
}

/*
 * View
 */

$title   = $langs->trans('RecurringInvoiceFollowup') . (dol_strlen($object->ref) > 0 && $action != 'create' ? ' ' . $object->ref : '');
$helpUrl = '';

saturne_header(1, '', $title, $helpUrl);

// Fields shown in the create/update forms (editable, visible on form).
$formFields = ['fk_soc', 'fk_facture_rec', 'period', 'prestation', 'montant_ttc', 'date_derniere_facture', 'facture_creee', 'facture_envoyee', 'facture_payee', 'paiement_ok', 'date_relance', 'nb_relances', 'client_contacte', 'date_contact', 'temps_sav', 'digirisk_existant', 'digirisk_ajour', 'acces_ok', 'version_dolibarr', 'version_digirisk', 'date_maj_du', 'dernier_audit_du', 'besoin', 'proposition', 'reaction', 'montant_pr', 'commentaire'];

// Create / edit mode.
if ($action == 'create' || (($id || $ref) && $action == 'edit')) {
    $isCreate = ($action == 'create');
    print load_fiche_titre($langs->trans($isCreate ? 'NewObject' : 'ModifyObject', $langs->transnoentities('RecurringInvoiceFollowup')), '', 'object_' . $object->picto);

    print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
    print '<input type="hidden" name="token" value="' . newToken() . '">';
    print '<input type="hidden" name="action" value="' . ($isCreate ? 'add' : 'update') . '">';
    if (!$isCreate) {
        print '<input type="hidden" name="id" value="' . $object->id . '">';
    }
    if ($backtopage) {
        print '<input type="hidden" name="backtopage" value="' . dol_escape_htmltag($backtopage) . '">';
    }

    print dol_get_fiche_head();
    print '<table class="border centpercent tableforfieldcreate">';

    foreach ($formFields as $key) {
        if (!isset($object->fields[$key])) {
            continue;
        }
        $val = $object->fields[$key];
        if (array_key_exists('enabled', $val) && !verifCond($val['enabled'])) {
            continue;
        }

        $value = GETPOSTISSET($key) ? GETPOST($key, 'alphanohtml') : (isset($object->$key) ? $object->$key : ($val['default'] ?? ''));

        print '<tr class="field_' . $key . '"><td';
        if (!empty($val['notnull'])) {
            print ' class="fieldrequired"';
        }
        print '>';
        print '<span>' . $langs->trans($val['label']) . '</span>';
        if (!empty($val['help'])) {
            print ' ' . $form->textwithpicto('', $langs->trans($val['help']));
        }
        print '</td><td>';
        print $object->showInputField($val, $key, $value, '', '', '', 0);
        print '</td></tr>';
    }

    print '</table>';
    print dol_get_fiche_end();
    print $form->buttonsSaveCancel($isCreate ? 'Create' : 'Save');
    print '</form>';
} elseif ($id > 0 || !empty($ref)) {
    // View mode.
    saturne_get_fiche_head($object, 'card', $title);

    $moreParams  = [];
    $moreHtmlRef = '';
    // Follow-up operational status badge next to the ref.
    $followupStatus = $object->getFollowupStatus();
    $moreHtmlRef   .= '<div class="refidno">' . dolGetStatus($followupStatus['label'], $followupStatus['label'], '', $followupStatus['badge'], 3) . ' ' . $followupStatus['label'] . '</div>';

    saturne_banner_tab($object, 'id', '', 1, 'rowid', 'ref', $moreHtmlRef, '', 0, '', '', 1);

    // Confirmation to delete.
    $formConfirm = '';
    if ($action == 'delete') {
        $formConfirm = $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('DeleteObject', $langs->transnoentities('RecurringInvoiceFollowup')), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
    }
    print $formConfirm;

    // Field groups displayed as blocks in the card.
    $viewGroups = [
        'FollowupGroupIdentity'   => ['fk_soc', 'fk_facture_rec', 'period', 'prestation'],
        'FollowupGroupBilling'    => ['montant_ttc', 'date_derniere_facture', 'facture_creee', 'facture_envoyee', 'facture_payee', 'paiement_ok'],
        'FollowupGroupRelance'    => ['date_relance', 'client_contacte', 'date_contact', 'nb_relances', 'temps_sav'],
        'FollowupGroupDigirisk'   => ['digirisk_existant', 'digirisk_ajour', 'acces_ok', 'version_dolibarr', 'version_digirisk'],
        'FollowupGroupCommercial' => ['besoin', 'proposition', 'reaction', 'montant_pr', 'commentaire'],
    ];

    print '<div class="fichecenter">';
    print '<div class="fichehalfleft">';

    foreach ($viewGroups as $groupLabel => $keys) {
        print '<div class="div-table-responsive-no-min">';
        print '<table class="border centpercent tableforfield">';
        print '<tr class="liste_titre"><td colspan="2">' . $langs->trans($groupLabel) . '</td></tr>';
        foreach ($keys as $key) {
            if (!isset($object->fields[$key])) {
                continue;
            }
            $val = $object->fields[$key];
            print '<tr><td class="titlefield">' . $langs->trans($val['label']) . '</td><td>';
            print $object->showOutputField($val, $key, $object->$key, '');
            print '</td></tr>';
        }
        print '</table>';
        print '</div><br>';
    }

    print '</div>';

    // Right column : Document Unique annual cycle.
    print '<div class="fichehalfright">';
    print '<div class="div-table-responsive-no-min">';
    print '<table class="border centpercent tableforfield">';
    print '<tr class="liste_titre"><td colspan="2">' . $langs->trans('FollowupGroupDu') . '</td></tr>';

    print '<tr><td class="titlefield">' . $langs->trans('FollowupDuUpdateBilledDate') . '</td><td>' . (!empty($object->date_maj_du) ? dol_print_date($object->date_maj_du, 'day') : '<span class="opacitymedium">' . $langs->trans('NotDefined') . '</span>') . '</td></tr>';

    if (!empty($object->next_maj_du)) {
        $offsetMonths = (int) getDolGlobalInt('REEDCRM_DU_ALERT_OFFSET_MONTHS', 1);
        $alertDate    = dol_time_plus_duree((int) $object->next_maj_du, -$offsetMonths, 'm');
        $isDue        = dol_now() >= $alertDate;

        print '<tr><td class="titlefield">' . $langs->trans('FollowupDuNextUpdate') . '</td><td><strong>' . dol_print_date($object->next_maj_du, 'day') . '</strong></td></tr>';
        print '<tr><td class="titlefield">' . $langs->trans('FollowupDuPrepareFrom') . '</td><td>';
        print dol_print_date($alertDate, 'day');
        if ($isDue) {
            print ' ' . dolGetStatus($langs->trans('FollowupDuToPrepare'), $langs->trans('FollowupDuToPrepare'), '', 'status3', 3) . ' <span class="badge badge-status1 badge-status">' . $langs->trans('FollowupDuToPrepare') . '</span>';
        }
        print '</td></tr>';
    } else {
        print '<tr><td class="titlefield">' . $langs->trans('FollowupDuNextUpdate') . '</td><td><span class="opacitymedium">' . $langs->trans('NotDefined') . '</span></td></tr>';
    }

    print '<tr><td class="titlefield">' . $langs->trans('FollowupLastDuAudit') . '</td><td>' . (!empty($object->dernier_audit_du) ? dol_print_date($object->dernier_audit_du, 'day') : '<span class="opacitymedium">' . $langs->trans('NotDefined') . '</span>') . '</td></tr>';

    print '</table>';
    print '</div>';
    print '</div>';

    print '</div>';
    print '<div class="clearboth"></div>';

    print dol_get_fiche_end();

    // Buttons.
    print '<div class="tabsAction">';
    if ($permissiontoadd) {
        print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=edit&token=' . newToken(), '', $permissiontoadd);
    }
    if ($permissiontodelete) {
        print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete&token=' . newToken(), '', $permissiontodelete);
    }
    print '</div>';
}

// End of page.
llxFooter();
$db->close();

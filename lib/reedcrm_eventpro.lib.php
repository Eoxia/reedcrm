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
 * \file    lib/reedcrm_eventpro.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for ReedCRM eventPro
 */

function showEventProTabs($id, $fromType, $currentTab): string
{
    global $langs;

    $out = '<div class="tabs">';

    $tabs = [
        'note' => [
            'label' => 'CustomerNote',
            'picto' => 'contact',
        ],
        'email' => [
            'label' => 'SendEmail',
            'picto' => 'fontawesome_fa-envelope_fas',
        ],
        'ticket' => [
            'label' => 'Ticket',
            'picto' => 'ticket',
        ]
    ];
    foreach ($tabs as $tabKey => $tabInfos) {
        $isActive = ($currentTab == $tabKey);
        $out .= '<div class="inline-block tabsElem' . ($isActive ? ' tabsElemActive' : '') . '">';
            $out .= '<div class="tab tab' . ($isActive ? 'active' : 'unactive') . '">';
                $out .= '<a class="tab inline-block valignmiddle" href="' . $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType . '&tab=' . $tabKey . '" title="' . $langs->trans($tabInfos['label']) . '">';
                    $out .= img_picto($langs->trans($tabInfos['label']), $tabInfos['picto'], 'class="pictofixedwidth"');
                    $out .= $langs->trans($tabInfos['label']);
                $out .= '</a>';
            $out .= '</div>';
        $out .= '</div>';
    }
    $out .= '</div>';

    return $out;
}

function showLatestProposals($object, $maxSizeShortListLimit = 3): string
{
    global $conf, $db, $langs, $user;

    $out = '';

    // Latest proposals (from comm/card.php)
    if (isModEnabled('propal') && $user->hasRight('propal', 'lire')) {
        $langs->load('propal');

        $sql = "SELECT s.nom, s.rowid, p.rowid as propalid, p.fk_projet, p.fk_statut, p.total_ht";
        $sql .= ", p.total_tva";
        $sql .= ", p.total_ttc";
        $sql .= ", p.ref, p.ref_client, p.remise";
        $sql .= ", p.datep as dp, p.fin_validite as date_limit, p.entity";
        $sql .= " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "propal as p, " . MAIN_DB_PREFIX . "c_propalst as c";
        $sql .= " WHERE p.fk_soc = s.rowid AND p.fk_statut = c.id";
        $sql .= " AND s.rowid = " . $object->thirdparty->id;
        $sql .= " AND p.entity IN (" . getEntity('propal') . ")";
        $sql .= " ORDER BY p.datep DESC";

        $resql = $db->query($sql);
        if ($resql) {
            $propal = new Propal($db);

            $num = $db->num_rows($resql);
            if ($num > 0) {
                $out .= '<div class="div-table-responsive-no-min">';
                $out .= '<table class="noborder centpercent lastrecordtable">';
                $out .= '<tr class="liste_titre">';
                $out .= '<td colspan="5"><table width="100%" class="nobordernopadding"><tr><td>' . $langs->trans('LastPropals', ($num <= $maxSizeShortListLimit ? '' : $maxSizeShortListLimit)) . '</td><td class="right"><a class="notasortlink" href="' . DOL_URL_ROOT.'/comm/propal/list.php?socid=' . $societe->id.'"><span class="hideonsmartphone">' . $langs->trans('AllPropals') . '</span><span class="badge marginleftonlyshort">' . $num . '</span></a></td>';
                $out .= '<td width="20px" class="right"><a href="' . DOL_URL_ROOT . '/comm/propal/stats/index.php?socid=' . $object->thirdparty->id . '">' . img_picto($langs->trans("Statistics"), 'stats') . '</a></td>';
                $out .= '</tr></table></td>';
                $out .= '</tr>';
            }

            $i = 0;
            while ($i < $num && $i < $maxSizeShortListLimit) {
                $objp = $db->fetch_object($resql);

                $out .= '<tr class="oddeven">';
                $out .= '<td class="nowraponall">';
                $propal->id           = $objp->propalid;
                $propal->ref          = $objp->ref;
                $propal->ref_customer = $objp->ref_client;
                $propal->fk_project   = $objp->fk_projet;
                $propal->total_ht     = $objp->total_ht;
                $propal->total_tva    = $objp->total_tva;
                $propal->total_ttc    = $objp->total_ttc;
                $out .= $propal->getNomUrl(1);
                $out .= '</td><td class="tdoverflowmax125">';
                if ($propal->fk_project > 0) {
                    $project = new Project($db);
                    $project->fetch($propal->fk_project);
                    $out .= $project->getNomUrl(1);
                }
                if (($db->jdate($objp->date_limit) < (dol_now() - $conf->propal->cloture->warning_delay)) && $objp->fk_statut == $propal::STATUS_VALIDATED) {
                    $out .= ' ' . img_warning();
                }
                $out .= '</td><td class="right" width="80px">' . dol_print_date($db->jdate($objp->dp), 'day') . '</td>';
                $out .= '<td class="right nowraponall">' . price($objp->total_ht) . '</td>';
                $out .= '<td class="right" style="min-width: 60px" class="nowrap">' . $propal->LibStatut($objp->fk_statut, 5) . '</td></tr>';
                $i++;
            }
            $db->free($resql);

            if ($num > 0) {
                $out .= "</table>";
                $out .= '</div>';
            }
        } else {
            dol_print_error($db);
        }
    }

    return $out;
}

function showLatestProjects($object, $maxSizeShortListLimit = 3): string
{
    global $db, $langs, $user;

    $out = '';

    // Latest projects (from comm/card.php)
    if (isModEnabled('project') && $user->hasRight('projet', 'lire')) {
        $langs->load('projects');

        $sql = "SELECT s.nom, s.rowid, p.rowid as projectid, p.ref, p.title, p.fk_statut, p.datec, p.dateo, p.date_close, p.budget_amount";
        $sql .= " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "projet as p";
        $sql .= " WHERE p.fk_soc = s.rowid";
        $sql .= " AND s.rowid = " . $object->thirdparty->id;
        $sql .= " AND p.entity IN (" . getEntity('project') . ")";
        $sql .= " ORDER BY p.datec DESC";

        $resql = $db->query($sql);
        if ($resql) {
            $project = new Project($db);

            $num = $db->num_rows($resql);
            if ($num > 0) {
                $out .= '<div class="div-table-responsive-no-min">';
                $out .= '<table class="noborder centpercent lastrecordtable">';
                $out .= '<tr class="liste_titre">';
                $out .= '<td colspan="4"><table width="100%" class="nobordernopadding"><tr><td>' . $langs->trans('LastProjects', ($num <= $maxSizeShortListLimit ? '' : $maxSizeShortListLimit)) . '</td><td class="right"><a class="notasortlink" href="' . DOL_URL_ROOT . '/projet/list.php?socid=' . $object->thirdparty->id . '"><span class="hideonsmartphone">' . $langs->trans('AllProjects') . '</span><span class="badge marginleftonlyshort">' . $num . '</span></a></td>';
                $out .= '<td width="20px" class="right"><a href="' . DOL_URL_ROOT . '/projet/stats/index.php?socid=' . $object->thirdparty->id . '">' . img_picto($langs->trans('Statistics'), 'stats') . '</a></td>';
                $out .= '</tr></table></td>';
                $out .= '</tr>';
            }

            $i = 0;
            while ($i < $num && $i < $maxSizeShortListLimit) {
                $objp = $db->fetch_object($resql);

                $out .= '<tr class="oddeven">';
                $out .= '<td class="nowraponall">';
                $project->id            = $objp->projectid;
                $project->ref           = $objp->ref;
                $project->title         = $objp->title;
                $project->budget_amount = $objp->budget_amount;
                $out .= $project->getNomUrl(1);
                $out .= '</td><td class="tdoverflowmax125">';
                $out .= dol_trunc($objp->title, 30);
                $out .= '</td><td class="right" width="80px">' . dol_print_date($db->jdate($objp->datec), 'day') . '</td>';
                $out .= '<td class="right" style="min-width: 60px" class="nowrap">' . $project->LibStatut($objp->fk_statut, 5) . '</td></tr>';
                $i++;
            }
            $db->free($resql);

            if ($num > 0) {
                $out .= '</table>';
                $out .= '</div>';
            }
        } else {
            dol_print_error($db);
        }
    }

    return $out;
}

function showOutstandingProposals(CommonObject $object): string
{
    global $conf, $langs, $user;

    $out = '';

    // Box proposals
    if (isModEnabled('propal') && $user->hasRight('propal', 'lire')) {
        $outstandingProposals = $object->thirdparty->getOutstandingProposals();

        $text = $langs->trans('OverAllProposals');
        $link = DOL_URL_ROOT . '/comm/propal/list.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator">' . price($outstandingProposals['total_ht'], 1, $langs, 1, -1, -1, $conf->currency) . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }

    return $out;
}

function showOutstandingOrders(CommonObject $object): string
{
    global $conf, $langs, $user;

    $out = '';

    // Box orders
    if (isModEnabled('order') && $user->hasRight('commande', 'lire')) {
        $outstandingOrders = $object->thirdparty->getOutstandingOrders();

        $text = $langs->trans('OverAllOrders');
        $link = DOL_URL_ROOT . '/commande/list.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator">'.price($outstandingOrders['total_ht'], 1, $langs, 1, -1, -1, $conf->currency) . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }

    return $out;
}

function showOutstandingBills(CommonObject $object): string
{
    global $conf, $langs, $user;

    $out = '';

    // Box invoices
    if (isModEnabled('invoice') && $user->hasRight('facture', 'lire')) {
        $outstandingBills  = $object->thirdparty->getOutstandingBills('customer', 0);
        $outstandingOpened = $outstandingBills['opened'];

        $text = $langs->trans('OverAllInvoices');
        $link = DOL_URL_ROOT . '/compta/facture/list.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator">' . price($outstandingBills['total_ht'], 1, $langs, 1, -1, -1, $conf->currency).'</span>';
        $out .= '</div>';
        $out .= '</a>';

        // Box outstanding bill
        $warn = '';
        if ($object->thirdparty->outstanding_limit != '' && $object->thirdparty->outstanding_limit < $outstandingOpened) {
            $warn = ' ' . img_warning($langs->trans("OutstandingBillReached"));
        }
        $text = $langs->trans('CurrentOutstandingBill');
        $link = DOL_URL_ROOT . '/compta/recap-compta.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator' . ($outstandingOpened > 0 ? ' amountremaintopay' : '') . '">' . price($outstandingOpened, 1, $langs, 1, -1, -1, $conf->currency) . $warn . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }

    return $out;
}

function showEventProInfos(CommonObject $object): string
{
    global $langs;

    $out  = '<div class="box divboxtable box-halfright">';
    $out .= '<table summary="' . dol_escape_htmltag($langs->trans('DolibarrStateBoard')) . '" class="border boxtable boxtablenobottom boxtablenotop boxtablenomarginbottom centpercent">';
    $out .= '<tr class="impair nohover"><td colspan="2" class="tdboxstats nohover">';

    $out .= showOutstandingProposals($object);
    $out .= showOutstandingOrders($object);
    $out .= showOutstandingBills($object);

    $out .= '</td></tr>';
    $out .= '</table>';
    $out .= '</div>';

    $maxSizeShortListLimit = getDolGlobalString('MAIN_SIZE_SHORTLIST_LIMIT');

    $out .= showLatestProposals($object, $maxSizeShortListLimit);
    $out .= showLatestProjects($object, $maxSizeShortListLimit);

    return $out;
}

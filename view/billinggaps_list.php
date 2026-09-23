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
 * \file    view/billinggaps_list.php
 * \ingroup reedcrm
 * \brief   Billing follow-up: everything signed / ordered / due that has not been invoiced yet.
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
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Load ReedCRM libraries.
require_once __DIR__ . '/../lib/reedcrm_followup.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs();

$action = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';

$hookmanager->initHooks(['billinggapslist']);

// Security check (reuse the followup permissions).
$permissiontoread = $user->hasRight('reedcrm', 'followup', 'read');
saturne_check_access($permissiontoread);

/*
 * View.
 */
$title = $langs->trans('BillingGapsMenu');

$signed    = reedcrmBillingGetSignedUnbilledProposals($db);
$orders    = reedcrmBillingGetUnbilledOrders($db);
$recurring = reedcrmBillingGetOverdueRecurring($db);

$sumArr = function (array $rows, string $key = 'total_ttc'): float {
    $t = 0.0;
    foreach ($rows as $r) {
        $t += (float) $r[$key];
    }
    return $t;
};

// Draft invoices (never validated), last 3 years.
$draftNb = 0;
$draftTot = 0.0;
$resD = $db->query('SELECT COUNT(*) as n, SUM(total_ttc) as t FROM ' . MAIN_DB_PREFIX . 'facture WHERE entity IN (' . getEntity('facture') . ') AND fk_statut = 0 AND datec >= DATE_SUB(NOW(), INTERVAL 3 YEAR)');
if ($resD && $oD = $db->fetch_object($resD)) {
    $draftNb  = (int) $oD->n;
    $draftTot = (float) $oD->t;
}

// Validated unpaid invoices overdue by more than 30 days (remaining amount).
$unpaidNb = 0;
$unpaidRest = 0.0;
$sqlU  = 'SELECT COUNT(*) as n, SUM(f.total_ttc - COALESCE((SELECT SUM(pf.amount) FROM ' . MAIN_DB_PREFIX . 'paiement_facture pf WHERE pf.fk_facture = f.rowid), 0)) as reste';
$sqlU .= ' FROM ' . MAIN_DB_PREFIX . 'facture f WHERE f.entity IN (' . getEntity('facture') . ') AND f.fk_statut = 1 AND f.paye = 0 AND f.date_lim_reglement < DATE_SUB(NOW(), INTERVAL 30 DAY)';
$resU = $db->query($sqlU);
if ($resU && $oU = $db->fetch_object($resU)) {
    $unpaidNb   = (int) $oU->n;
    $unpaidRest = (float) $oU->reste;
}

saturne_header(0, '', $title, '');

print '<style>
.rbg-intro{margin:0 0 14px;color:#777}
.rbg-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:0 0 20px}
.rbg-tile{border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;padding:12px 14px;background:var(--colorbacklinepair2,#fff);position:relative;overflow:hidden}
.rbg-tile:before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:#cf4257}
.rbg-tile.warn:before{background:#c8871a}.rbg-tile.info:before{background:#2f6f9f}.rbg-tile.viol:before{background:#6f42c1}
.rbg-tile .k{font-size:.82em;color:#777;font-weight:600}
.rbg-tile .v{font-size:1.7em;font-weight:800;line-height:1.15}
.rbg-tile .a{font-size:.9em;color:#555;font-weight:600}
</style>';

print load_fiche_titre('<i class="fas fa-file-invoice-dollar paddingright"></i>' . $title, '', '');
print '<div class="rbg-intro">' . $langs->trans('BillingGapsIntro') . '</div>';

// KPI tiles.
print '<div class="rbg-tiles">';
printf('<div class="rbg-tile"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('BillingGapSignedProposals'), count($signed), price($sumArr($signed), 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rbg-tile warn"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('BillingGapOrders'), count($orders), price($sumArr($orders), 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rbg-tile info"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('BillingGapRecurring'), count($recurring), price($sumArr($recurring), 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rbg-tile viol"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('BillingGapDrafts'), $draftNb, price($draftTot, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rbg-tile"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('BillingGapUnpaid'), $unpaidNb, price($unpaidRest, 0, $langs, 1, -1, 0, $conf->currency));
print '</div>';

$thirdpartyStatic = new Societe($db);

// Reusable renderer for a document row (thirdparty, ref link, date, amount, invoice-from-origin button).
$renderDocTable = function (array $rows, string $titleKey, string $icon, string $cardUrl, string $origin) use (&$thirdpartyStatic, $langs, $conf) {
    print '<br>';
    print load_fiche_titre('<i class="' . $icon . ' paddingright"></i>' . $langs->trans($titleKey) . ' <span class="badge">' . count($rows) . '</span>', '', '');
    print '<div class="div-table-responsive"><table class="tagtable liste">';
    print '<tr class="liste_titre"><th>' . $langs->trans('ThirdParty') . '</th><th>' . $langs->trans('Ref') . '</th><th class="center">' . $langs->trans('Date') . '</th><th class="right">' . $langs->trans('AmountTTC') . '</th><th class="center maxwidthsearch"></th></tr>';
    if (empty($rows)) {
        print '<tr class="oddeven"><td colspan="5" class="center opacitymedium">' . $langs->trans('BillingGapNone') . '</td></tr>';
    } else {
        $tot = 0.0;
        foreach ($rows as $r) {
            $tot                      += (float) $r['total_ttc'];
            $thirdpartyStatic->id     = $r['fk_soc'];
            $thirdpartyStatic->name   = $r['thirdparty'];
            $thirdpartyStatic->status = 1;
            print '<tr class="oddeven">';
            print '<td class="tdoverflowmax200">' . $thirdpartyStatic->getNomUrl(1) . '</td>';
            print '<td class="nowraponall"><a href="' . DOL_URL_ROOT . $cardUrl . ((int) $r['id']) . '" target="_blank" rel="noopener"><i class="' . $icon . ' paddingright opacitymedium"></i>' . dol_escape_htmltag($r['ref']) . '</a></td>';
            print '<td class="center nowraponall">' . (!empty($r['date']) ? dol_print_date($r['date'], 'day') : '') . '</td>';
            print '<td class="right nowraponall">' . price((float) $r['total_ttc'], 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
            print '<td class="center">';
            if ($origin !== '') {
                print '<a class="button smallpaddingimp" target="_blank" rel="noopener" href="' . DOL_URL_ROOT . '/compta/facture/card.php?action=create&origin=' . $origin . '&originid=' . ((int) $r['id']) . '&socid=' . ((int) $r['fk_soc']) . '" title="' . dol_escape_htmltag($langs->trans('BillingGapInvoice')) . '"><i class="fas fa-file-invoice-dollar paddingright"></i>' . $langs->trans('BillingGapInvoice') . '</a>';
            }
            print '</td></tr>';
        }
        print '<tr class="liste_total"><td colspan="3">' . $langs->trans('Total') . '</td><td class="right">' . price($tot, 0, $langs, 1, -1, -1, $conf->currency) . '</td><td></td></tr>';
    }
    print '</table></div>';
};

// Detail tables.
$renderDocTable($signed, 'BillingGapSignedProposals', 'fas fa-file-signature', '/comm/propal/card.php?id=', 'propal');
$renderDocTable($orders, 'BillingGapOrders', 'fas fa-box', '/commande/card.php?id=', 'commande');

// Overdue recurring templates (dedicated: link to the template, generate from there).
print '<br>';
print load_fiche_titre('<i class="fas fa-redo paddingright"></i>' . $langs->trans('BillingGapRecurring') . ' <span class="badge">' . count($recurring) . '</span>', '', '');
print '<div class="div-table-responsive"><table class="tagtable liste">';
print '<tr class="liste_titre"><th>' . $langs->trans('ThirdParty') . '</th><th>' . $langs->trans('Ref') . '</th><th class="center">' . $langs->trans('BillingGapDueSince') . '</th><th class="right">' . $langs->trans('AmountTTC') . '</th><th class="center maxwidthsearch"></th></tr>';
if (empty($recurring)) {
    print '<tr class="oddeven"><td colspan="5" class="center opacitymedium">' . $langs->trans('BillingGapNone') . '</td></tr>';
} else {
    $tot = 0.0;
    foreach ($recurring as $r) {
        $tot                      += (float) $r['total_ttc'];
        $thirdpartyStatic->id     = $r['fk_soc'];
        $thirdpartyStatic->name   = $r['thirdparty'];
        $thirdpartyStatic->status = 1;
        print '<tr class="oddeven">';
        print '<td class="tdoverflowmax200">' . $thirdpartyStatic->getNomUrl(1) . '</td>';
        print '<td class="tdoverflowmax300"><a href="' . DOL_URL_ROOT . '/compta/facture/card-rec.php?id=' . ((int) $r['id']) . '" target="_blank" rel="noopener"><i class="fas fa-redo paddingright opacitymedium"></i>' . dol_escape_htmltag($r['titre']) . '</a></td>';
        print '<td class="center nowraponall"><span style="color:#cf4257;font-weight:bold">' . (!empty($r['date_when']) ? dol_print_date($r['date_when'], 'day') : '') . '</span></td>';
        print '<td class="right nowraponall">' . price((float) $r['total_ttc'], 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
        print '<td class="center"><a class="button smallpaddingimp" target="_blank" rel="noopener" href="' . DOL_URL_ROOT . '/compta/facture/card-rec.php?id=' . ((int) $r['id']) . '" title="' . dol_escape_htmltag($langs->trans('BillingGapGenerate')) . '"><i class="fas fa-file-invoice-dollar paddingright"></i>' . $langs->trans('BillingGapGenerate') . '</a></td>';
        print '</tr>';
    }
    print '<tr class="liste_total"><td colspan="3">' . $langs->trans('Total') . '</td><td class="right">' . price($tot, 0, $langs, 1, -1, -1, $conf->currency) . '</td><td></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();

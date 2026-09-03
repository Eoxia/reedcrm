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
 * \file    view/signedunbilled_list.php
 * \ingroup reedcrm
 * \brief   Signed quotes that were never invoiced, cross-checked against the customer invoices
 *          to spot the ones actually billed outside the quote -> invoice link.
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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

// Load ReedCRM libraries.
require_once __DIR__ . '/../lib/reedcrm_followup.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs();

// Parameters.
$action        = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
$searchMonths  = GETPOSTINT('search_months') ?: 24; // "more than 2 years old is pointless"
$searchStatus  = GETPOST('search_status', 'aZ09') ?: 'all';
$searchSoc     = GETPOST('search_soc', 'alphanohtml');
$searchUser    = GETPOSTINT('search_user');
$searchMin     = price2num(GETPOST('search_min', 'alpha'));
$sortfield     = GETPOST('sortfield', 'aZ09') ?: 'date_sig';
$sortorder     = strtoupper(GETPOST('sortorder', 'aZ09')) === 'ASC' ? 'ASC' : 'DESC';
if (!in_array($searchMonths, [12, 24, 36, 60], true)) {
    $searchMonths = 24;
}
if (!in_array($searchStatus, ['all', 'todo', 'suspect'], true)) {
    $searchStatus = 'all';
}
if (!in_array($sortfield, ['date_sig', 'total_ttc', 'thirdparty'], true)) {
    $sortfield = 'date_sig';
}

$form = new Form($db);
$hookmanager->initHooks(['signedunbilledlist']);

// Security check (same permissions as the other follow-up pages).
$permissiontoread  = $user->hasRight('reedcrm', 'followup', 'read');
$permissiontobill  = $user->hasRight('facture', 'creer');
$permissiontowrite = $user->hasRight('propal', 'creer');

saturne_check_access($permissiontoread);

/*
 * Actions.
 */
// Classify a quote as billed (used when the invoice was made separately, so the line leaves the list).
if ($action === 'classifybilled' && $permissiontowrite) {
    $propalId = GETPOSTINT('id');
    $propal   = new Propal($db);
    if ($propalId > 0 && $propal->fetch($propalId) > 0) {
        if ($propal->classifyBilled($user) > 0) {
            setEventMessages($langs->trans('SignedUnbilledMarkedBilled', $propal->ref), []);
        } else {
            setEventMessages($propal->error, $propal->errors, 'errors');
        }
    }
    $action = 'view';
}

$rows = reedcrmSignedUnbilledGetProposals($db, $searchMonths);

// Apply the display filters.
$userCache = [];
$userName  = function (int $id) use (&$userCache, $db): string {
    if ($id <= 0) {
        return '';
    }
    if (!isset($userCache[$id])) {
        $u = new User($db);
        $u->fetch($id);
        $userCache[$id] = $u->id > 0 ? dolGetFirstLastname($u->firstname, $u->lastname) : '';
    }
    return $userCache[$id];
};

$filtered = [];
foreach ($rows as $row) {
    if ($searchStatus === 'todo' && $row['match'] !== '') {
        continue;
    }
    if ($searchStatus === 'suspect' && $row['match'] === '') {
        continue;
    }
    if ($searchSoc !== '' && stripos($row['thirdparty'], $searchSoc) === false && stripos($row['ref'], $searchSoc) === false) {
        continue;
    }
    if ($searchUser > 0 && $row['fk_user'] !== $searchUser) {
        continue;
    }
    if ($searchMin !== '' && (float) $row['total_ttc'] < (float) $searchMin) {
        continue;
    }
    $filtered[] = $row;
}

usort($filtered, function ($a, $b) use ($sortfield, $sortorder) {
    if ($sortfield === 'thirdparty') {
        $cmp = strcasecmp($a['thirdparty'], $b['thirdparty']);
    } elseif ($sortfield === 'total_ttc') {
        $cmp = $a['total_ttc'] <=> $b['total_ttc'];
    } else {
        $cmp = $a['date_sig'] <=> $b['date_sig'];
    }
    return $sortorder === 'ASC' ? $cmp : -$cmp;
});

// CSV export of the filtered list.
if ($action === 'exportcsv' && $permissiontoread) {
    $sep = ';';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pr_signees_non_facturees_' . dol_print_date(dol_now(), 'dayxcard') . '.csv"');
    print "\xEF\xBB\xBF";
    print implode($sep, ['Devis', 'Tiers', 'Localisation', 'Signe le', 'Anciennete (jours)', 'Montant HT', 'Montant TTC', 'Commercial', 'Projet', 'Verification', 'Factures liees', 'Factures client depuis signature', 'Montant facture depuis signature']) . "\n";
    foreach ($filtered as $r) {
        $matchLabel = $r['match'] !== '' ? $langs->transnoentities('SignedUnbilledMatch' . ucfirst($r['match'])) : $langs->transnoentities('SignedUnbilledMatchNone');
        $matchRefs  = implode(' ', array_map(function ($i) {
            return $i['ref'];
        }, $r['match_invoices']));
        $cells = [
            $r['ref'], $r['thirdparty'], $r['location'],
            $r['date_sig'] ? dol_print_date($r['date_sig'], 'day') : '',
            $r['date_sig'] ? (int) floor((dol_now() - $r['date_sig']) / 86400) : '',
            price2num($r['total_ht']), price2num($r['total_ttc']),
            $userName((int) $r['fk_user']), $r['project_ref'],
            $matchLabel, $matchRefs, $r['client_nb'], price2num($r['client_total']),
        ];
        print implode($sep, array_map(function ($v) {
            return '"' . str_replace('"', '""', (string) $v) . '"';
        }, $cells)) . "\n";
    }
    exit;
}

/*
 * View.
 */
$title = $langs->trans('SignedUnbilledMenu');

saturne_header(0, '', $title, '');

// Totals over the whole (unfiltered) period, for the KPI band.
$totToBill    = 0.0;
$nbToBill     = 0;
$totSuspect   = 0.0;
$nbSuspect    = 0;
$totOld       = 0.0;
$nbOld        = 0;
$now          = dol_now();
foreach ($rows as $r) {
    if ($r['match'] === '') {
        $nbToBill++;
        $totToBill += $r['total_ttc'];
        if ($r['date_sig'] && ($now - $r['date_sig']) > 180 * 86400) {
            $nbOld++;
            $totOld += $r['total_ttc'];
        }
    } else {
        $nbSuspect++;
        $totSuspect += $r['total_ttc'];
    }
}

print '<style>
.rsu-intro{margin:0 0 14px;color:#777}
.rsu-tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin:0 0 16px}
.rsu-tile{border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;padding:12px 14px;background:var(--colorbacklinepair2,#fff);position:relative;overflow:hidden}
.rsu-tile:before{content:"";position:absolute;left:0;top:0;bottom:0;width:3px;background:#cf4257}
.rsu-tile.warn:before{background:#c8871a}.rsu-tile.info:before{background:#2f6f9f}.rsu-tile.good:before{background:#2e9e6c}
.rsu-tile .k{font-size:.82em;color:#777;font-weight:600}
.rsu-tile .v{font-size:1.7em;font-weight:800;line-height:1.15}
.rsu-tile .a{font-size:.9em;color:#555;font-weight:600}
.rsu-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:0 0 12px;padding:12px 14px;border:1px solid var(--colortopbordertitle1,#ddd);border-radius:8px;background:var(--colorbacklinepair2,#fff)}
.rsu-filters label{display:block;font-size:.8em;color:#777;font-weight:600;margin-bottom:3px}
.rsu-filters select,.rsu-filters input{padding:5px 8px;border:1px solid var(--colortopbordertitle1,#ccc);border-radius:6px;background:transparent;color:inherit}
.rsu-badge{display:inline-block;padding:2px 8px;border-radius:11px;font-size:.8em;font-weight:700;white-space:nowrap}
.rsu-badge.todo{background:#fbe9ec;color:#a3293c}
.rsu-badge.chain{background:#e6f4ec;color:#1f7a52}
.rsu-badge.amount{background:#fdf1de;color:#96631b}
.rsu-badge.products{background:#eaf1f8;color:#2f6f9f}
.rsu-inv{display:block;font-size:.85em;margin-top:3px}
.rsu-age{font-weight:700}
.rsu-age.mid{color:#c8871a}.rsu-age.old{color:#cf4257}
.rsu-actions{display:flex;gap:5px;justify-content:flex-end;white-space:nowrap}
</style>';

print load_fiche_titre('<i class="fas fa-file-signature paddingright"></i>' . $title, '', '');
print '<div class="rsu-intro">' . $langs->trans('SignedUnbilledIntro') . '</div>';

// KPI tiles.
print '<div class="rsu-tiles">';
printf('<div class="rsu-tile"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('SignedUnbilledToBill'), $nbToBill, price($totToBill, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rsu-tile warn"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('SignedUnbilledOver6Months'), $nbOld, price($totOld, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rsu-tile info"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('SignedUnbilledSuspect'), $nbSuspect, price($totSuspect, 0, $langs, 1, -1, 0, $conf->currency));
printf('<div class="rsu-tile good"><div class="k">%s</div><div class="v">%d</div><div class="a">%s</div></div>', $langs->trans('SignedUnbilledDisplayed'), count($filtered), $langs->trans('SignedUnbilledPeriodMonths', $searchMonths));
print '</div>';

// Filters.
print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" class="rsu-filters">';
print '<div><label>' . $langs->trans('SignedUnbilledPeriod') . '</label><select name="search_months" onchange="this.form.submit()">';
foreach ([12 => 12, 24 => 24, 36 => 36, 60 => 60] as $m => $lbl) {
    print '<option value="' . $m . '"' . ($m === $searchMonths ? ' selected' : '') . '>' . $langs->trans('SignedUnbilledPeriodMonths', $lbl) . '</option>';
}
print '</select></div>';
print '<div><label>' . $langs->trans('SignedUnbilledVerification') . '</label><select name="search_status" onchange="this.form.submit()">';
foreach (['all' => 'SignedUnbilledFilterAll', 'todo' => 'SignedUnbilledFilterTodo', 'suspect' => 'SignedUnbilledFilterSuspect'] as $k => $lbl) {
    print '<option value="' . $k . '"' . ($k === $searchStatus ? ' selected' : '') . '>' . $langs->trans($lbl) . '</option>';
}
print '</select></div>';
print '<div><label>' . $langs->trans('ThirdParty') . ' / ' . $langs->trans('Ref') . '</label><input type="text" name="search_soc" value="' . dol_escape_htmltag($searchSoc) . '"></div>';
print '<div><label>' . $langs->trans('SignedUnbilledSalesRep') . '</label>' . $form->select_dolusers($searchUser ?: '', 'search_user', 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'minwidth150') . '</div>';
print '<div><label>' . $langs->trans('SignedUnbilledMinAmount') . '</label><input type="text" name="search_min" size="7" value="' . dol_escape_htmltag((string) $searchMin) . '"></div>';
print '<div><input type="hidden" name="sortfield" value="' . $sortfield . '"><input type="hidden" name="sortorder" value="' . $sortorder . '">';
print '<button type="submit" class="button smallpaddingimp"><i class="fas fa-search paddingright"></i>' . $langs->trans('Search') . '</button> ';
print '<a class="button button-cancel smallpaddingimp" href="' . $_SERVER['PHP_SELF'] . '">' . $langs->trans('Reset') . '</a></div>';
print '</form>';

// Export button.
$exportParams = 'search_months=' . $searchMonths . '&search_status=' . $searchStatus . '&search_soc=' . urlencode($searchSoc) . '&search_user=' . $searchUser . '&search_min=' . urlencode((string) $searchMin);
print '<div class="right" style="margin-bottom:8px">';
print '<a class="button smallpaddingimp" href="' . $_SERVER['PHP_SELF'] . '?' . $exportParams . '&action=exportcsv&token=' . newToken() . '"><i class="fas fa-file-csv paddingright"></i>' . $langs->trans('ExportCsv') . '</a>';
print '</div>';

// Sortable header helper.
$sortLink = function (string $field, string $label) use ($sortfield, $sortorder, $exportParams) {
    $neworder = ($sortfield === $field && $sortorder === 'DESC') ? 'ASC' : 'DESC';
    $arrow    = $sortfield === $field ? ($sortorder === 'DESC' ? ' &#9660;' : ' &#9650;') : '';
    return '<a href="' . $_SERVER['PHP_SELF'] . '?' . $exportParams . '&sortfield=' . $field . '&sortorder=' . $neworder . '">' . $label . $arrow . '</a>';
};

$thirdpartyStatic = new Societe($db);
$invoiceStatic    = new Facture($db);
$propalStatic     = new Propal($db);

print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>' . $sortLink('thirdparty', $langs->trans('ThirdParty')) . '</th>';
print '<th>' . $langs->trans('Ref') . '</th>';
print '<th class="center">' . $sortLink('date_sig', $langs->trans('DateSigning')) . '</th>';
print '<th class="center">' . $langs->trans('SignedUnbilledAge') . '</th>';
print '<th class="right">' . $langs->trans('AmountHT') . '</th>';
print '<th class="right">' . $sortLink('total_ttc', $langs->trans('AmountTTC')) . '</th>';
print '<th>' . $langs->trans('SignedUnbilledSalesRep') . '</th>';
print '<th>' . $langs->trans('SignedUnbilledVerification') . '</th>';
print '<th class="center">' . $langs->trans('SignedUnbilledClientInvoices') . '</th>';
print '<th class="center maxwidthsearch"></th>';
print '</tr>';

if (empty($filtered)) {
    print '<tr class="oddeven"><td colspan="10" class="center opacitymedium">' . $langs->trans('SignedUnbilledNone') . '</td></tr>';
} else {
    $totHt  = 0.0;
    $totTtc = 0.0;
    foreach ($filtered as $r) {
        $totHt  += $r['total_ht'];
        $totTtc += $r['total_ttc'];

        $thirdpartyStatic->id     = $r['fk_soc'];
        $thirdpartyStatic->name   = $r['thirdparty'];
        $thirdpartyStatic->status = $r['soc_status'];

        $days    = $r['date_sig'] ? (int) floor(($now - $r['date_sig']) / 86400) : 0;
        $ageCss  = $days > 180 ? 'old' : ($days > 90 ? 'mid' : '');

        print '<tr class="oddeven">';
        print '<td class="tdoverflowmax200">' . $thirdpartyStatic->getNomUrl(1);
        if (!empty($r['location'])) {
            print '<br><span class="opacitymedium small">' . dol_escape_htmltag($r['location']) . '</span>';
        }
        print '</td>';

        print '<td class="nowraponall"><a href="' . DOL_URL_ROOT . '/comm/propal/card.php?id=' . $r['id'] . '" target="_blank" rel="noopener"><i class="fas fa-file-signature paddingright opacitymedium"></i>' . dol_escape_htmltag($r['ref']) . '</a>';
        if (!empty($r['project_ref'])) {
            print '<br><span class="opacitymedium small">' . dol_escape_htmltag($r['project_ref']) . '</span>';
        }
        print '</td>';

        print '<td class="center nowraponall">' . ($r['date_sig'] ? dol_print_date($r['date_sig'], 'day') : '') . '</td>';
        print '<td class="center nowraponall"><span class="rsu-age ' . $ageCss . '">' . ($r['date_sig'] ? $langs->trans('SignedUnbilledDays', $days) : '') . '</span></td>';
        print '<td class="right nowraponall">' . price($r['total_ht'], 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
        print '<td class="right nowraponall"><strong>' . price($r['total_ttc'], 0, $langs, 1, -1, -1, $conf->currency) . '</strong></td>';
        print '<td class="tdoverflowmax125">' . dol_escape_htmltag($userName((int) $r['fk_user'])) . '</td>';

        // Verification: what the cross-check against the customer invoices found.
        print '<td class="tdoverflowmax300">';
        if ($r['match'] === '') {
            print '<span class="rsu-badge todo">' . $langs->trans('SignedUnbilledMatchNone') . '</span>';
        } else {
            print '<span class="rsu-badge ' . $r['match'] . '">' . $langs->trans('SignedUnbilledMatch' . ucfirst($r['match'])) . '</span>';
            $shown = 0;
            foreach ($r['match_invoices'] as $inv) {
                if ($shown >= 3) {
                    print '<span class="rsu-inv opacitymedium">…</span>';
                    break;
                }
                print '<span class="rsu-inv"><a href="' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . ((int) $inv['id']) . '" target="_blank" rel="noopener"><i class="fas fa-file-invoice paddingright opacitymedium"></i>' . dol_escape_htmltag($inv['ref']) . '</a> ' . ($inv['date'] ? dol_print_date($inv['date'], 'day') : '') . ' — ' . price($inv['total_ttc'], 0, $langs, 1, -1, -1, $conf->currency);
                if ((int) $inv['status'] === Facture::STATUS_DRAFT) {
                    print ' <span class="opacitymedium">(' . $langs->trans('Draft') . ')</span>';
                }
                print '</span>';
                $shown++;
            }
        }
        print '</td>';

        // Customer invoices issued since the signature: manual cross-check shortcut.
        print '<td class="center nowraponall">';
        if ($r['client_nb'] > 0) {
            print '<a href="' . DOL_URL_ROOT . '/compta/facture/list.php?socid=' . $r['fk_soc'] . '" target="_blank" rel="noopener">' . $r['client_nb'] . '</a>';
            print '<br><span class="opacitymedium small">' . price($r['client_total'], 0, $langs, 1, -1, 0, $conf->currency) . '</span>';
        } else {
            print '<span class="opacitymedium">0</span>';
        }
        print '</td>';

        print '<td><div class="rsu-actions">';
        if ($permissiontobill) {
            print '<a class="button smallpaddingimp" target="_blank" rel="noopener" href="' . DOL_URL_ROOT . '/compta/facture/card.php?action=create&origin=propal&originid=' . $r['id'] . '&socid=' . $r['fk_soc'] . '" title="' . dol_escape_htmltag($langs->trans('SignedUnbilledInvoice')) . '"><i class="fas fa-file-invoice-dollar"></i></a>';
        }
        if ($permissiontowrite) {
            print '<a class="button button-cancel smallpaddingimp" href="' . $_SERVER['PHP_SELF'] . '?' . $exportParams . '&sortfield=' . $sortfield . '&sortorder=' . $sortorder . '&action=classifybilled&id=' . $r['id'] . '&token=' . newToken() . '" onclick="return confirm(\'' . dol_escape_js($langs->transnoentities('SignedUnbilledConfirmBilled', $r['ref'])) . '\')" title="' . dol_escape_htmltag($langs->trans('SignedUnbilledClassifyBilled')) . '"><i class="fas fa-check"></i></a>';
        }
        print '</div></td>';
        print '</tr>';
    }
    print '<tr class="liste_total"><td colspan="4">' . $langs->trans('Total') . '</td>';
    print '<td class="right">' . price($totHt, 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
    print '<td class="right">' . price($totTtc, 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
    print '<td colspan="4"></td></tr>';
}
print '</table></div>';

print '<div class="opacitymedium" style="margin-top:10px;font-size:.9em">' . $langs->trans('SignedUnbilledLegend') . '</div>';

llxFooter();
$db->close();

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
 * \file    lib/reedcrm.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for ReedCRM
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function reedcrm_admin_prepare_head(): array
{
    // Global variables definitions
    global $conf, $langs;

    // Load translation files required by the page
    saturne_load_langs(['products', 'sendings']);

    // Initialize values
    $h = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/reedcrm/admin/setup.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-cog pictofixedwidth"></i>' . $langs->trans('ModuleSettings') : '<i class="fas fa-cog"></i>';
    $head[$h][2] = 'settings';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/pwa.php', 1). '?module_name=ReedCRM&start_url=' . dol_buildpath('custom/reedcrm/view/frontend/quickcreation.php?source=pwa', 3);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-mobile pictofixedwidth"></i>' . $langs->trans('App') : '<i class="fas fa-mobile"></i>';
    $head[$h][2] = 'pwa';
    $h++;

    $head[$h][0] = dol_buildpath('/reedcrm/admin/App-ReedCRM.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-mobile pictofixedwidth"></i>' . $langs->trans('App-ReedCRM') : '<i class="fas fa-mobile"></i>';
    $head[$h][2] = 'pwa_reedcrm';
    $h++;

    $head[$h][0] = dol_buildpath('/reedcrm/admin/call_notifications.php', 1) . '?module_name=ReedCRM';
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-bell pictofixedwidth"></i>' . $langs->trans('CallNotifications') : '<i class="fas fa-bell"></i>';
    $head[$h][2] = 'notifications';
    $h++;

    $head[$h][0] = dol_buildpath('/reedcrm/admin/call_list.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-list pictofixedwidth"></i>' . $langs->trans('CallList') : '<i class="fas fa-list"></i>';
    $head[$h][2] = 'call_list';
    $h++;

    $head[$h][0] = dol_buildpath('/reedcrm/admin/product.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-cube pictofixedwidth"></i>' . $langs->trans('Product') : '<i class="fas fa-cube"></i>';
    $head[$h][2] = 'product';
    $h++;

    $head[$h][0] = dol_buildpath('/reedcrm/admin/ticket.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-ticket-alt pictofixedwidth"></i>' . $langs->trans('Ticket') : '<i class="fas fa-ticket-alt"></i>';
    $head[$h][2] = 'ticket';
    $h++;

    $head[$h][0] = dol_buildpath('/saturne/admin/about.php', 1) . '?module_name=ReedCRM';
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fab fa-readme pictofixedwidth"></i>' . $langs->trans('About') : '<i class="fab fa-readme"></i>';
    $head[$h][2] = 'about';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'reedcrm@reedcrm');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'reedcrm@reedcrm', 'remove');

    return $head;
}

/**
 * Bulk fetch documents for a list of project IDs.
 *
 * @param  int[] $projectIds Array of project IDs.
 * @param  bool  $includeAll When true, each piece key also carries an 'all' list of every document
 *                           of that type (ordered by rowid); the top-level fields stay the latest one.
 * @return array Multi-dimensional array: [project_id][doc_type] => ['ref' => ..., 'amount' => ..., 'url' => ..., 'all' => [...]].
 */
function reedcrm_get_pwa_projects_documents(array $projectIds, bool $includeAll = false): array
{
    global $db, $conf;

    $results = [];
    if (empty($projectIds)) {
        return $results;
    }

    $idListStr = implode(',', array_map('intval', $projectIds));

    // Initialize defaults for all project IDs
    foreach ($projectIds as $id) {
        $results[$id] = [
            'montant' => null,
            'propal' => null,
            'commande' => null,
            'commande_fourn' => null,
            'reception' => null,
            'facture_fourn' => null,
            'expedition' => null,
            'facture' => null,
            'payment' => null,
        ];
    }

    // 1. Montant (Opportunity)
    if (!empty($conf->global->REEDCRM_PWA_SHOW_OPP_AMOUNT)) {
        $sql = "SELECT rowid as fk_projet, ref, opp_amount as total_ht FROM " . MAIN_DB_PREFIX . "projet WHERE rowid IN (" . $idListStr . ")";
        $res = $db->query($sql);
        if ($res) {
            while ($row = $db->fetch_object($res)) {
                $entry = [
                    'ref'    => $row->ref,
                    'amount' => (float) $row->total_ht,
                    'status' => null,
                    'url'    => DOL_URL_ROOT . '/projet/card.php?id=' . $row->fk_projet
                ];
                $results[$row->fk_projet]['montant'] = $entry;
                if ($includeAll) {
                    $results[$row->fk_projet]['montant']['all'] = [$entry];
                }
            }
        }
    }

    // Helper closure to query simple tables
    $querySimpleTable = function ($constantName, $tableName, $key, $urlPath, $hasAmount = true) use ($db, $idListStr, $includeAll, &$results) {
        global $conf;
        if (empty($conf->global->$constantName)) {
            return;
        }

        $cols = "t.fk_projet, t.rowid, t.ref, t.fk_statut AS status" . ($hasAmount ? ", t.total_ht" : "");
        if ($includeAll) {
            $sql = "SELECT " . $cols . " FROM " . MAIN_DB_PREFIX . $tableName . " t"
                 . " WHERE t.fk_projet IN (" . $idListStr . ") ORDER BY t.fk_projet, t.rowid";
        } else {
            $sql = "SELECT " . $cols . "
                    FROM " . MAIN_DB_PREFIX . $tableName . " t
                    INNER JOIN (
                        SELECT fk_projet, MAX(rowid) as max_id
                        FROM " . MAIN_DB_PREFIX . $tableName . "
                        WHERE fk_projet IN (" . $idListStr . ")
                        GROUP BY fk_projet
                    ) latest ON t.rowid = latest.max_id";
        }

        $res = $db->query($sql);
        if ($res) {
            while ($row = $db->fetch_object($res)) {
                $entry = [
                    'ref'    => $row->ref,
                    'amount' => $hasAmount ? (float) $row->total_ht : null,
                    'status' => isset($row->status) ? (int) $row->status : null,
                    'url'    => DOL_URL_ROOT . $urlPath . $row->rowid
                ];
                if ($includeAll) {
                    if (!is_array($results[$row->fk_projet][$key])) {
                        $results[$row->fk_projet][$key] = ['all' => []];
                    }
                    $results[$row->fk_projet][$key]['all'][] = $entry;
                    // Rows are ordered by rowid ASC: the last iteration is the representative latest.
                    $results[$row->fk_projet][$key]['ref']    = $entry['ref'];
                    $results[$row->fk_projet][$key]['amount'] = $entry['amount'];
                    $results[$row->fk_projet][$key]['status'] = $entry['status'];
                    $results[$row->fk_projet][$key]['url']    = $entry['url'];
                } else {
                    $results[$row->fk_projet][$key] = $entry;
                }
            }
        }
    };

    // 2. Propal
    $querySimpleTable('REEDCRM_PWA_SHOW_PROPAL', 'propal', 'propal', '/comm/propal/card.php?id=');

    // 3. Commande
    $querySimpleTable('REEDCRM_PWA_SHOW_COMMANDE', 'commande', 'commande', '/commande/card.php?id=');

    // 4. Commande fournisseur
    $querySimpleTable('REEDCRM_PWA_SHOW_COMMANDE_FOURN', 'commande_fournisseur', 'commande_fourn', '/fourn/commande/card.php?id=');

    // 5. Reception
    $querySimpleTable('REEDCRM_PWA_SHOW_RECEPTION', 'reception', 'reception', '/reception/card.php?id=', false);

    // 6. Facture fournisseur
    $querySimpleTable('REEDCRM_PWA_SHOW_FACTURE_FOURN', 'facture_fourn', 'facture_fourn', '/fourn/facture/card.php?id=');

    // 7. Expedition
    $querySimpleTable('REEDCRM_PWA_SHOW_EXPEDITION', 'expedition', 'expedition', '/expedition/card.php?id=', false);

    // 8. Facture
    $querySimpleTable('REEDCRM_PWA_SHOW_FACTURE', 'facture', 'facture', '/compta/facture/card.php?id=');

    // 9. Payment (transitively linked via payments on invoices)
    if (!empty($conf->global->REEDCRM_PWA_SHOW_PAYMENT)) {
        if ($includeAll) {
            $sql = "SELECT DISTINCT f.fk_projet, p.ref, p.amount, p.rowid
                    FROM " . MAIN_DB_PREFIX . "paiement p
                    JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid
                    JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture
                    WHERE f.fk_projet IN (" . $idListStr . ")
                    ORDER BY f.fk_projet, p.rowid";
        } else {
            $sql = "SELECT f.fk_projet, p.ref, p.amount, p.rowid
                    FROM " . MAIN_DB_PREFIX . "paiement p
                    JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid
                    JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture
                    INNER JOIN (
                        SELECT f2.fk_projet, MAX(p2.rowid) as max_id
                        FROM " . MAIN_DB_PREFIX . "paiement p2
                        JOIN " . MAIN_DB_PREFIX . "paiement_facture pf2 ON pf2.fk_paiement = p2.rowid
                        JOIN " . MAIN_DB_PREFIX . "facture f2 ON f2.rowid = pf2.fk_facture
                        WHERE f2.fk_projet IN (" . $idListStr . ")
                        GROUP BY f2.fk_projet
                    ) latest ON p.rowid = latest.max_id AND f.fk_projet = latest.fk_projet";
        }

        $res = $db->query($sql);
        if ($res) {
            while ($row = $db->fetch_object($res)) {
                $entry = [
                    'ref'    => $row->ref,
                    'amount' => (float) $row->amount,
                    'status' => null,
                    'url'    => DOL_URL_ROOT . '/compta/paiement/card.php?id=' . $row->rowid
                ];
                if ($includeAll) {
                    if (!is_array($results[$row->fk_projet]['payment'])) {
                        $results[$row->fk_projet]['payment'] = ['all' => []];
                    }
                    $results[$row->fk_projet]['payment']['all'][] = $entry;
                    $results[$row->fk_projet]['payment']['ref']    = $entry['ref'];
                    $results[$row->fk_projet]['payment']['amount'] = $entry['amount'];
                    $results[$row->fk_projet]['payment']['status'] = $entry['status'];
                    $results[$row->fk_projet]['payment']['url']    = $entry['url'];
                } else {
                    $results[$row->fk_projet]['payment'] = $entry;
                }
            }
        }
    }

    // Project-level totals for the "encaissement incomplet" rule — only needed for the invoice/payment pieces
    if (!empty($conf->global->REEDCRM_PWA_SHOW_FACTURE) || !empty($conf->global->REEDCRM_PWA_SHOW_PAYMENT)) {
        $sqlInv = "SELECT fk_projet, SUM(total_ttc) AS invoiced FROM " . MAIN_DB_PREFIX . "facture"
            . " WHERE fk_projet IN (" . $idListStr . ") AND fk_statut IN (1, 2) GROUP BY fk_projet";
        $resInv = $db->query($sqlInv);
        if ($resInv) {
            while ($row = $db->fetch_object($resInv)) {
                $results[$row->fk_projet]['totals']['invoiced'] = (float) $row->invoiced;
            }
        }
        $sqlPaid = "SELECT f.fk_projet, SUM(pf.amount) AS paid FROM " . MAIN_DB_PREFIX . "paiement_facture pf"
            . " JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture"
            . " WHERE f.fk_projet IN (" . $idListStr . ") GROUP BY f.fk_projet";
        $resPaid = $db->query($sqlPaid);
        if ($resPaid) {
            while ($row = $db->fetch_object($resPaid)) {
                $results[$row->fk_projet]['totals']['paid'] = (float) $row->paid;
            }
        }
    }

    return $results;
}

/**
 * Compute, per piece, the chain state (done/current/todo) and the inconsistencies for one opportunity.
 * Pure function: everything is derived from the documents array of reedcrm_get_pwa_projects_documents().
 *
 * @param  array $docs One project entry: [ key => ['ref','amount','status','url'] | null, 'totals' => ['invoiced','paid'] ]
 * @return array<string,array{state:string,issues:array<array{level:string,msg:string}>}>
 */
function reedcrm_compute_opportunity_chain(array $docs): array
{
    global $langs;

    // Full display order (matches the admin pieces order)
    $order = ['montant', 'propal', 'commande', 'commande_fourn', 'reception', 'facture_fourn', 'expedition', 'facture', 'payment'];

    $present = [];
    foreach ($order as $key) {
        $present[$key] = !empty($docs[$key]) && !empty($docs[$key]['ref']);
    }

    // Current = last present piece in the display order
    $current = '';
    foreach ($order as $key) {
        if ($present[$key]) {
            $current = $key;
        }
    }

    $chain = [];
    foreach ($order as $key) {
        $state = !$present[$key] ? 'todo' : ($key === $current ? 'current' : 'done');
        $chain[$key] = ['state' => $state, 'issues' => []];
    }

    $addIssue = function ($key, $level, $msg) use (&$chain) {
        if (isset($chain[$key])) {
            $chain[$key]['issues'][] = ['level' => $level, 'msg' => $msg];
        }
    };

    // Reference amount: opportunity amount, fallback to the proposal amount
    $ref = null;
    if (!empty($docs['montant']['amount'])) {
        $ref = (float) $docs['montant']['amount'];
    } elseif (!empty($docs['propal']['amount'])) {
        $ref = (float) $docs['propal']['amount'];
    }
    $tolerance = (float) getDolGlobalString('REEDCRM_PWA_AMOUNT_TOLERANCE', '0.01');

    // 1. Montant divergent (warn) — customer-side amount pieces vs the reference amount
    if ($ref !== null && $ref > 0) {
        // When montant is absent, propal becomes the reference, so the propal check below is a harmless no-op.
        foreach (['propal', 'commande', 'facture'] as $key) {
            if ($present[$key] && isset($docs[$key]['amount']) && abs((float) $docs[$key]['amount'] - $ref) > $tolerance) {
                $addIssue($key, 'warn', $langs->trans('PwaIssueAmountMismatch', price($docs[$key]['amount']), price($ref)));
            }
        }
    }

    // 2. Document annulé/refusé (warn) — cancelled/refused latest doc status
    // Cancelled/refused latest doc (propal "not signed" = 3, commande canceled = -1, facture abandoned = 3). Supplier pieces deferred to v2.
    $cancelled = ['propal' => [3], 'commande' => [-1], 'facture' => [3]];
    foreach ($cancelled as $key => $badStatuses) {
        if ($present[$key] && isset($docs[$key]['status']) && in_array((int) $docs[$key]['status'], $badStatuses, true)) {
            $addIssue($key, 'warn', $langs->trans('PwaIssueCancelled'));
        }
    }

    // 3. Étape manquante (err) — a downstream customer piece exists without its upstream
    $requires = ['facture' => 'commande', 'expedition' => 'commande', 'payment' => 'facture'];
    foreach ($requires as $downstream => $upstream) {
        if ($present[$downstream] && !$present[$upstream]) {
            $addIssue($upstream, 'err', $langs->trans('PwaIssueMissingStep', $langs->trans('PwaPieceLabel_' . $downstream)));
        }
    }

    // 4. Encaissement incomplet (err) — paid total below invoiced total
    $invoiced = (float) ($docs['totals']['invoiced'] ?? 0);
    $paid = (float) ($docs['totals']['paid'] ?? 0);
    if ($invoiced > 0 && $paid + $tolerance < $invoiced) {
        $addIssue('payment', 'err', $langs->trans('PwaIssuePaymentIncomplete', price($paid), price($invoiced)));
    }

    return $chain;
}

/**
 * Return the <style> block for the opportunity chain bar, once per request only.
 * Shared by the PWA opportunities list, the project Overview hook and procard.php.
 *
 * @return string The <style> block on the first call, '' afterwards.
 */
function reedcrm_chain_bar_styles(): string
{
    static $emitted = false;
    if ($emitted) {
        return '';
    }
    $emitted = true;

    return '<style>
    .pwa-doc-bar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; background: #f8fafc; padding: 8px 10px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 10px; width: 100%; box-sizing: border-box; }
    .pwa-doc-item { position: relative; display: inline-flex; align-items: center; gap: 6px; font-size: 0.85em; background: #fff; padding: 4px 10px; border-radius: 6px; border: 1px solid #cbd5e0; color: #475569; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .pwa-doc-item.na { border-style: dashed; color: #94a3b8; background: #fdfdfd; box-shadow: none; }
    .pwa-doc-item a { color: #1d4ed8; font-weight: 600; text-decoration: none; border-bottom: 1px dashed #cbd5e0; }
    .pwa-doc-item a:hover { color: #2563eb; }
    .pwa-doc-item.is-done { background:#e7f5ea; border-color:#bfe3c7; color:#1f8a3b; }
    .pwa-doc-item.is-current { background:#e8f0fe; border-color:#3b76e8; box-shadow:0 0 0 2px rgba(59,118,232,.25); color:#1f57c3; }
    .pwa-doc-item.is-todo { background:#fafbfc; border-style:dashed; color:#94a3b8; box-shadow:none; }
    .pwa-doc-item.has-warn { border-color:#e8923b !important; box-shadow:0 0 0 2px rgba(232,146,59,.25); }
    .pwa-doc-item.has-err { border-color:#d34a4a !important; box-shadow:0 0 0 2px rgba(211,74,74,.25); }
    .pwa-doc-badge { position:absolute; top:-7px; right:-7px; width:16px; height:16px; border-radius:50%; color:#fff; font-size:10px; line-height:16px; text-align:center; background:#e8923b; }
    .pwa-doc-badge.err { background:#d34a4a; }
    .pwa-doc-curtag { position:absolute; top:-8px; left:50%; transform:translateX(-50%); background:#3b76e8; color:#fff; font-size:8px; padding:1px 6px; border-radius:8px; font-weight:700; white-space:nowrap; }
    .pwa-doc-bar.icons-only .pwa-doc-item { padding:0; width:34px; height:34px; justify-content:center; }
    .pwa-doc-bar.icons-only .pwa-doc-label, .pwa-doc-bar.icons-only .pwa-doc-item > span:not(.pwa-doc-badge), .pwa-doc-bar.icons-only .pwa-doc-item > a { display:none; }
    .pwa-doc-label { font-weight: 500; color: #64748b; }
</style>';
}

/**
 * Return the <style> block for the opportunity chain matrix, once per request only.
 * Reuses the is-done/is-current/is-todo/has-warn/has-err semantics of reedcrm_chain_bar_styles().
 *
 * @return string The <style> block on the first call, '' afterwards.
 */
function reedcrm_chain_matrix_styles(): string
{
    static $emitted = false;
    if ($emitted) {
        return '';
    }
    $emitted = true;

    return '<style>
    .pwa-matrix-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff; }
    table.pwa-doc-matrix { border-collapse: separate; border-spacing: 0; width: max-content; min-width: 100%; font-size: 0.82em; }
    table.pwa-doc-matrix th, table.pwa-doc-matrix td { border-bottom: 1px solid #eef2f7; border-right: 1px solid #eef2f7; padding: 6px 8px; vertical-align: top; text-align: left; }
    table.pwa-doc-matrix thead th { position: sticky; top: 0; z-index: 3; background: #f1f5f9; color: #334155; font-weight: 600; white-space: nowrap; box-shadow: inset 0 -1px 0 #cbd5e0; }
    table.pwa-doc-matrix tbody th.pwa-matrix-rowhead, table.pwa-doc-matrix thead th.pwa-matrix-corner { position: sticky; left: 0; z-index: 2; background: #f8fafc; color: #475569; font-weight: 600; white-space: nowrap; box-shadow: inset -1px 0 0 #cbd5e0; }
    table.pwa-doc-matrix thead th.pwa-matrix-corner { z-index: 4; }
    table.pwa-doc-matrix th.pwa-matrix-rowhead i { width: 16px; text-align: center; margin-right: 6px; color: #64748b; }
    .pwa-matrix-projhead a { color: #1e293b; font-weight: 600; text-decoration: none; }
    .pwa-matrix-projhead .pwa-matrix-projtitle { display: block; font-weight: 400; color: #64748b; font-size: 0.85em; max-width: 160px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .pwa-matrix-cell { position: relative; }
    .pwa-matrix-cell.is-done    { background: #e7f5ea; }
    .pwa-matrix-cell.is-current { background: #e8f0fe; box-shadow: inset 0 0 0 2px rgba(59,118,232,.45); }
    .pwa-matrix-cell.is-todo    { background: #fff; }
    .pwa-matrix-cell.has-warn   { box-shadow: inset 0 0 0 2px rgba(232,146,59,.55); }
    .pwa-matrix-cell.has-err    { box-shadow: inset 0 0 0 2px rgba(211,74,74,.6); }
    .pwa-matrix-count { display: inline-block; min-width: 16px; height: 16px; line-height: 16px; text-align: center; font-size: 10px; font-weight: 700; color: #fff; background: #64748b; border-radius: 8px; padding: 0 4px; margin-bottom: 3px; }
    .pwa-matrix-cell.is-done .pwa-matrix-count    { background: #1f8a3b; }
    .pwa-matrix-cell.is-current .pwa-matrix-count { background: #1f57c3; }
    .pwa-matrix-doc { display: block; white-space: nowrap; }
    .pwa-matrix-doc a { color: #1d4ed8; text-decoration: none; }
    .pwa-matrix-doc.is-bad a { color: #b91c1c; text-decoration: line-through; }
    .pwa-matrix-doc .pwa-matrix-status { color: #64748b; }
    .pwa-matrix-badge { position: absolute; top: 2px; right: 2px; width: 15px; height: 15px; border-radius: 50%; color: #fff; font-size: 9px; line-height: 15px; text-align: center; background: #e8923b; }
    .pwa-matrix-badge.err { background: #d34a4a; }
    .pwa-matrix-toggle { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; background: #1e293b; color: #fff !important; text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap; }
    .pwa-matrix-toggle:hover { background: #334155; }
</style>';
}

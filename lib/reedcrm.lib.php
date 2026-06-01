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
    saturne_load_langs(['products']);

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

    $head[$h][0] = dol_buildpath('/reedcrm/admin/product.php', 1);
    $head[$h][1] = $conf->browser->layout != 'phone' ? '<i class="fas fa-cube pictofixedwidth"></i>' . $langs->trans('Product') : '<i class="fas fa-cube"></i>';
    $head[$h][2] = 'product';
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
 * Bulk fetch latest documents for a list of project IDs.
 *
 * @param array $projectIds Array of project IDs
 * @return array Multi-dimensional array: [project_id][doc_type] => ['ref' => ..., 'amount' => ..., 'url' => ...]
 */
function reedcrm_get_pwa_projects_documents(array $projectIds): array
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
                $results[$row->fk_projet]['montant'] = [
                    'ref' => $row->ref,
                    'amount' => (float)$row->total_ht,
                    'status' => null,
                    'url' => DOL_URL_ROOT . '/projet/card.php?id=' . $row->fk_projet
                ];
            }
        }
    }

    // Helper closure to query simple tables
    $querySimpleTable = function ($constantName, $tableName, $key, $urlPath, $hasAmount = true) use ($db, $idListStr, &$results) {
        global $conf;
        if (empty($conf->global->$constantName)) {
            return;
        }

        $sql = "SELECT t.fk_projet, t.rowid, t.ref, t.fk_statut AS status" . ($hasAmount ? ", t.total_ht" : "") . "
                FROM " . MAIN_DB_PREFIX . $tableName . " t
                INNER JOIN (
                    SELECT fk_projet, MAX(rowid) as max_id
                    FROM " . MAIN_DB_PREFIX . $tableName . "
                    WHERE fk_projet IN (" . $idListStr . ")
                    GROUP BY fk_projet
                ) latest ON t.rowid = latest.max_id";

        $res = $db->query($sql);
        if ($res) {
            while ($row = $db->fetch_object($res)) {
                $results[$row->fk_projet][$key] = [
                    'ref' => $row->ref,
                    'amount' => $hasAmount ? (float)$row->total_ht : null,
                    'status' => isset($row->status) ? (int) $row->status : null,
                    'url' => DOL_URL_ROOT . $urlPath . $row->rowid
                ];
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

        $res = $db->query($sql);
        if ($res) {
            while ($row = $db->fetch_object($res)) {
                $results[$row->fk_projet]['payment'] = [
                    'ref' => $row->ref,
                    'amount' => (float)$row->amount,
                    'status' => null,
                    'url' => DOL_URL_ROOT . '/compta/paiement/card.php?id=' . $row->rowid
                ];
            }
        }
    }

    // Project-level totals for the "encaissement incomplet" rule (validated invoices only)
    $sqlInv = "SELECT fk_projet, SUM(total_ttc) AS invoiced FROM " . MAIN_DB_PREFIX . "facture"
        . " WHERE fk_projet IN (" . $idListStr . ") AND fk_statut > 0 GROUP BY fk_projet";
    $resInv = $db->query($sqlInv);
    if ($resInv) {
        while ($row = $db->fetch_object($resInv)) {
            $results[$row->fk_projet]['totals']['invoiced'] = (float) $row->invoiced;
        }
    }
    $sqlPaid = "SELECT f.fk_projet, SUM(p.amount) AS paid FROM " . MAIN_DB_PREFIX . "paiement p"
        . " JOIN " . MAIN_DB_PREFIX . "paiement_facture pf ON pf.fk_paiement = p.rowid"
        . " JOIN " . MAIN_DB_PREFIX . "facture f ON f.rowid = pf.fk_facture"
        . " WHERE f.fk_projet IN (" . $idListStr . ") GROUP BY f.fk_projet";
    $resPaid = $db->query($sqlPaid);
    if ($resPaid) {
        while ($row = $db->fetch_object($resPaid)) {
            $results[$row->fk_projet]['totals']['paid'] = (float) $row->paid;
        }
    }

    return $results;
}


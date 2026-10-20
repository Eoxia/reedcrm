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
 * \file    view/sepamandate_check.php
 * \ingroup reedcrm
 * \brief   Read-only check of the SEPA mandates: what will keep each direct debit from leaving correctly.
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
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Load ReedCRM libraries.
require_once __DIR__ . '/../lib/reedcrm_sepa_mandate.lib.php';

global $conf, $db, $form, $hookmanager, $langs, $user;

saturne_load_langs(['banks', 'withdrawals']);

$action = GETPOST('action', 'aZ09') ?: 'view';

$hookmanager->initHooks(['sepamandatecheck']);

// Security check: the page shows bank data, so the direct debit read permission of the core is required too
$permissiontoread = $user->hasRight('reedcrm', 'read') && isModEnabled('prelevement') && $user->hasRight('prelevement', 'bons', 'lire');
saturne_check_access($permissiontoread);

/*
 * Filters.
 */
$searchSeverity = GETPOST('search_severity', 'aZ09');
$searchPending  = GETPOSTINT('search_pending');

if (GETPOST('button_removefilter', 'alpha') || GETPOST('button_removefilter_x', 'alpha')) {
    $searchSeverity = '';
    $searchPending  = 0;
}
if (!in_array($searchSeverity, ['', 'issues', REEDCRM_SEPA_MANDATE_BLOCKING, REEDCRM_SEPA_MANDATE_WARNING], true)) {
    $searchSeverity = '';
}

/*
 * Data.
 */
$accounts = reedcrmSepaMandateGetBankAccounts($db);
$requests = reedcrmSepaMandateGetPendingRequests($db);

// Third parties with a pending request and no bank account at all still need a name on their row
$socWithoutAccount = [];
$socWithAccount    = array_column($accounts, 'socid', 'socid');
foreach ($requests as $request) {
    if (!isset($socWithAccount[$request['socid']])) {
        $socWithoutAccount[] = $request['socid'];
    }
}
$socNames = reedcrmSepaMandateGetThirdpartyNames($db, $socWithoutAccount);

$check   = reedcrmSepaMandateAnalyse($accounts, $requests, $socNames, DOL_VERSION, dol_now(), (bool) getDolGlobalInt('WITHDRAWAL_WITHOUT_BIC'));
$summary = $check['summary'];
$rule    = $check['rule'];

$rows = [];
foreach ($check['rows'] as $row) {
    if ($searchSeverity === 'issues' && $row['severity'] === '') {
        continue;
    }
    if (in_array($searchSeverity, [REEDCRM_SEPA_MANDATE_BLOCKING, REEDCRM_SEPA_MANDATE_WARNING], true) && $row['severity'] !== $searchSeverity) {
        continue;
    }
    if ($searchPending && empty($row['requests'])) {
        continue;
    }
    $rows[] = $row;
}

/*
 * View.
 */
// saturne_header() already loads saturne.min.* and reedcrm.min.*
$title = $langs->trans('SepaMandateCheckTitle');

saturne_header(0, '', $title, '');

print load_fiche_titre($title, '', 'fontawesome_fa-university_fas_#63ACC9');

// Which signature date the running version writes is the key of half the verdicts: say it once, on top
print info_admin($langs->trans('SepaMandateRuleIntro') . ' ' . $langs->trans('SepaMandateRule' . ucfirst($rule), DOL_VERSION), 0, 0, '1', $rule === 'fallback' ? '' : 'warning');

$thirdpartyStatic = new Societe($db);

require __DIR__ . '/../core/tpl/reedcrm_sepa_mandate_summary.tpl.php';
require __DIR__ . '/../core/tpl/reedcrm_sepa_mandate_filters.tpl.php';
require __DIR__ . '/../core/tpl/reedcrm_sepa_mandate_table.tpl.php';

llxFooter();
$db->close();

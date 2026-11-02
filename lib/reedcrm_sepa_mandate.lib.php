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
 * \file    lib/reedcrm_sepa_mandate.lib.php
 * \ingroup reedcrm
 * \brief   Read-only check of the SEPA mandates: what will keep a direct debit from leaving correctly, bank account
 *          by bank account, told the way the core will actually build the file.
 */

/**
 * A problem that makes the bank reject the file, or makes Dolibarr leave the invoice out of it.
 */
const REEDCRM_SEPA_MANDATE_BLOCKING = 'blocking';

/**
 * A problem worth fixing that does not stop the debit on its own.
 */
const REEDCRM_SEPA_MANDATE_WARNING = 'warning';

/**
 * The bank account keeps no full IBAN on screen: only its last characters are shown.
 */
const REEDCRM_SEPA_MANDATE_IBAN_VISIBLE_CHARS = 4;

/**
 * Which signature date the core writes in <DtOfSgntr> when a request designates its bank account.
 *
 * The default path writes the mandate date, and the creation date of the bank account when there is none. From 24.0.0
 * a request may designate the bank account to debit, and that path reloads the mandate date without the fallback:
 * up to 24.0.2 at least, the tag goes out empty and the bank rejects the file. The fix is Dolibarr PR #42020; whether a
 * later version ships it cannot be told from here, so those versions are reported as uncertain rather than safe.
 *
 * @param  string $version Dolibarr version, DOL_VERSION
 * @return string          'fallback' before 24, 'nofallback' from 24.0.0 to 24.0.2, 'uncertain' after
 */
function reedcrmSepaMandateDateRule(string $version): string
{
    // A pre-release (24.0.0-beta) already carries the 24 code, so only the numbers count
    if (!preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $version, $matches)) {
        return 'uncertain';
    }

    $number = sprintf('%d.%d.%d', (int) $matches[1], (int) ($matches[2] ?? 0), (int) ($matches[3] ?? 0));
    if (version_compare($number, '24.0.0', '<')) {
        return 'fallback';
    }
    if (version_compare($number, '24.0.2', '<=')) {
        return 'nofallback';
    }

    return 'uncertain';
}

/**
 * Hide an IBAN but its last characters.
 *
 * @param  string $iban Clear IBAN
 * @return string       Masked IBAN, empty when there is none
 */
function reedcrmSepaMandateMaskIban(string $iban): string
{
    $iban = preg_replace('/\s+/', '', $iban) ?? '';
    if ($iban === '') {
        return '';
    }

    return '•••• ' . strtoupper(substr($iban, -REEDCRM_SEPA_MANDATE_IBAN_VISIBLE_CHARS));
}

/**
 * Bank accounts of the third parties, as the direct debit will read them.
 *
 * The IBAN is decrypted to be checked here and never leaves the function: the caller only gets its masked form and
 * the verdict of the core validators.
 *
 * @param  DoliDB                  $db Database handler
 * @return array<int,array<string,mixed>> Bank accounts keyed by rowid
 */
function reedcrmSepaMandateGetBankAccounts(DoliDB $db): array
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/bank.lib.php';

    $accounts = [];

    $sql  = 'SELECT sr.rowid, sr.fk_soc, sr.label, sr.iban_prefix, sr.bic, sr.rum, sr.date_rum, sr.datec, sr.frstrecur,';
    $sql .= ' sr.default_rib, sr.status, s.nom as thirdparty, s.status as soc_status';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'societe_rib as sr';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = sr.fk_soc';
    $sql .= " WHERE sr.type = 'ban'";
    $sql .= ' AND s.entity IN (' . getEntity('societe') . ')';
    $sql .= ' ORDER BY s.nom, sr.rowid';

    $resql = $db->query($sql);
    if (!$resql) {
        return $accounts;
    }

    while ($obj = $db->fetch_object($resql)) {
        $iban = trim((string) dolDecrypt((string) $obj->iban_prefix));
        $bic  = trim((string) $obj->bic);

        $accounts[(int) $obj->rowid] = [
            'id'          => (int) $obj->rowid,
            'socid'       => (int) $obj->fk_soc,
            'thirdparty'  => (string) $obj->thirdparty,
            'soc_status'  => (int) $obj->soc_status,
            'label'       => (string) $obj->label,
            'iban_masked' => reedcrmSepaMandateMaskIban($iban),
            'iban_empty'  => $iban === '',
            'iban_valid'  => $iban !== '' && checkIbanForAccount(null, $iban),
            'bic'         => $bic,
            'bic_valid'   => $bic !== '' && checkSwiftForAccount(null, $bic),
            'rum'         => trim((string) $obj->rum),
            'date_rum'    => $obj->date_rum ? (int) $db->jdate($obj->date_rum) : 0,
            'datec'       => $obj->datec ? (int) $db->jdate($obj->datec) : 0,
            'frstrecur'   => (string) $obj->frstrecur,
            'default'     => (int) $obj->default_rib === 1,
            'active'      => (int) $obj->status === 1,
        ];
    }
    $db->free($resql);

    return $accounts;
}

/**
 * Direct debit requests still waiting for a withdrawal order, restricted to the ones the core would pick up.
 *
 * Same filters as BonPrelevement::create(): validated unpaid customer invoice with a positive total, request not yet
 * processed nor handed to an external payment site. Credit transfers are left out, they carry no mandate.
 *
 * @param  DoliDB                         $db Database handler
 * @return array<int,array<string,mixed>>     Requests keyed by rowid
 */
function reedcrmSepaMandateGetPendingRequests(DoliDB $db): array
{
    $requests = [];

    $sql  = 'SELECT pd.rowid, pd.fk_facture, pd.fk_societe_rib, pd.amount, pd.date_demande, f.ref, f.fk_soc';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'prelevement_demande as pd';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = pd.fk_facture';
    $sql .= ' WHERE pd.traite = 0';
    $sql .= ' AND pd.ext_payment_id IS NULL';
    $sql .= " AND (pd.type IS NULL OR pd.type <> 'bank-transfer')";
    $sql .= ' AND f.fk_statut = 1';
    $sql .= ' AND f.paye = 0';
    $sql .= ' AND f.total_ttc > 0';
    $sql .= ' AND f.entity IN (' . getEntity('invoice') . ')';
    $sql .= ' ORDER BY pd.date_demande, pd.rowid';

    $resql = $db->query($sql);
    if (!$resql) {
        return $requests;
    }

    while ($obj = $db->fetch_object($resql)) {
        $requests[(int) $obj->rowid] = [
            'id'         => (int) $obj->rowid,
            'invoice_id' => (int) $obj->fk_facture,
            'invoice'    => (string) $obj->ref,
            'socid'      => (int) $obj->fk_soc,
            'account_id' => (int) $obj->fk_societe_rib,
            'amount'     => (float) $obj->amount,
            'date'       => $obj->date_demande ? (int) $db->jdate($obj->date_demande) : 0,
        ];
    }
    $db->free($resql);

    return $requests;
}

/**
 * Names of the third parties that have a pending request, for the ones that have no bank account at all.
 *
 * @param  DoliDB            $db     Database handler
 * @param  int[]             $socIDs Third party rowids
 * @return array<int,string>         Name keyed by rowid
 */
function reedcrmSepaMandateGetThirdpartyNames(DoliDB $db, array $socIDs): array
{
    $names  = [];
    $socIDs = array_filter(array_map('intval', $socIDs));
    if (empty($socIDs)) {
        return $names;
    }

    $sql  = 'SELECT rowid, nom FROM ' . MAIN_DB_PREFIX . 'societe';
    $sql .= ' WHERE rowid IN (' . $db->sanitize(implode(',', $socIDs)) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        return $names;
    }

    while ($obj = $db->fetch_object($resql)) {
        $names[(int) $obj->rowid] = (string) $obj->nom;
    }
    $db->free($resql);

    return $names;
}

/**
 * Add an issue to a row and raise the row severity accordingly.
 *
 * @param  array<string,mixed> $row      Row of the check
 * @param  string              $severity REEDCRM_SEPA_MANDATE_BLOCKING or REEDCRM_SEPA_MANDATE_WARNING
 * @param  string              $key      Issue code, the language key is SepaMandateIssue + key
 * @param  array<int,mixed>    $params   Parameters of the message
 * @return void
 */
function reedcrmSepaMandateAddIssue(array &$row, string $severity, string $key, array $params = []): void
{
    $row['issues'][] = ['severity' => $severity, 'key' => $key, 'params' => $params];

    // A row is as severe as its worst issue
    if ($severity === REEDCRM_SEPA_MANDATE_BLOCKING) {
        $row['severity'] = REEDCRM_SEPA_MANDATE_BLOCKING;
    } elseif ($row['severity'] === '') {
        $row['severity'] = REEDCRM_SEPA_MANDATE_WARNING;
    }
}

/**
 * Run every check over the bank accounts and the pending requests.
 *
 * Pure function: no database access, so the verdict only depends on its arguments. The bank account a request is
 * debited on follows the core: the designated one when it still exists, the default one of the third party otherwise.
 *
 * @param  array<int,array<string,mixed>> $accounts   Bank accounts from reedcrmSepaMandateGetBankAccounts()
 * @param  array<int,array<string,mixed>> $requests   Requests from reedcrmSepaMandateGetPendingRequests()
 * @param  array<int,string>              $socNames   Names of the third parties without any bank account
 * @param  string                         $version    Dolibarr version, DOL_VERSION
 * @param  int                            $now        Current timestamp
 * @param  bool                           $bicOptional True when WITHDRAWAL_WITHOUT_BIC lets an empty BIC through
 * @return array{rows: array<int,array<string,mixed>>, summary: array<string,int>, rule: string}
 */
function reedcrmSepaMandateAnalyse(array $accounts, array $requests, array $socNames, string $version, int $now, bool $bicOptional): array
{
    $rule = reedcrmSepaMandateDateRule($version);

    // Bank accounts of each third party, and its default ones: the core needs exactly one
    $defaultsBySoc = [];
    $accountsBySoc = [];
    foreach ($accounts as $accountID => $account) {
        $accountsBySoc[$account['socid']][] = $accountID;
        if ($account['default']) {
            $defaultsBySoc[$account['socid']][] = $accountID;
        }
    }

    // Requests of each bank account, split on how the core reaches it
    $usedBy        = [];
    $thirdpartyRow = [];
    foreach ($requests as $request) {
        $socID      = $request['socid'];
        $designated = $request['account_id'];
        $defaults   = $defaultsBySoc[$socID] ?? [];

        if (!isset($thirdpartyRow[$socID])) {
            $thirdpartyRow[$socID] = ['requests' => [], 'missing' => []];
        }
        $thirdpartyRow[$socID]['requests'][] = $request;

        if ($designated > 0 && isset($accounts[$designated]) && $accounts[$designated]['socid'] === $socID) {
            $usedBy[$designated]['designated'][] = $request;
        } else {
            // A designated bank account that is gone makes the core fall back on the default one, silently
            if ($designated > 0) {
                $thirdpartyRow[$socID]['missing'][] = $request;
            }
            // Several default bank accounts stop the core altogether: the request is shown on each of them
            foreach ($defaults as $defaultID) {
                $usedBy[$defaultID]['default'][] = $request;
            }
        }
    }

    // How many bank accounts share each RUM, a mandate reference has to be unique
    $rumOwners = [];
    foreach ($accounts as $accountID => $account) {
        if ($account['rum'] !== '' && $account['active']) {
            $rumOwners[strtoupper($account['rum'])][] = $accountID;
        }
    }

    $rows = [];
    foreach ($accounts as $accountID => $account) {
        $designatedRequests = $usedBy[$accountID]['designated'] ?? [];
        $defaultRequests    = $usedBy[$accountID]['default'] ?? [];
        $pending            = array_merge($designatedRequests, $defaultRequests);

        // An inactive bank account is out of scope, unless a request still points at it
        if (!$account['active'] && empty($designatedRequests)) {
            continue;
        }

        $row = [
            'socid'       => $account['socid'],
            'thirdparty'  => $account['thirdparty'],
            'account'     => $account,
            'requests'    => $pending,
            'designated'  => !empty($designatedRequests),
            'issues'      => [],
            'severity'    => '',
            'date_written' => 0,
            'date_reason' => '',
        ];

        // The date the file will carry: the designated path of 24 has no fallback on the creation date
        if ($row['designated'] && $rule !== 'fallback') {
            $row['date_written'] = $account['date_rum'];
            $row['date_reason']  = $account['date_rum'] ? 'mandate' : ($rule === 'nofallback' ? 'empty' : 'emptyunlessfix');
        } elseif ($account['date_rum']) {
            $row['date_written'] = $account['date_rum'];
            $row['date_reason']  = 'mandate';
        } elseif ($account['datec']) {
            $row['date_written'] = $account['datec'];
            $row['date_reason']  = 'creation';
        } else {
            $row['date_reason'] = 'empty';
        }

        if (!$account['active']) {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_WARNING, 'DesignatedInactive');
        }

        if (!$account['date_rum']) {
            if ($row['date_reason'] === 'empty' && $row['designated'] && $rule === 'nofallback') {
                reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'DateMissingEmpty', [$version]);
            } elseif ($row['date_reason'] === 'emptyunlessfix') {
                reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'DateMissingUncertain', [$version]);
            } elseif ($row['date_reason'] === 'empty') {
                reedcrmSepaMandateAddIssue($row, empty($pending) ? REEDCRM_SEPA_MANDATE_WARNING : REEDCRM_SEPA_MANDATE_BLOCKING, 'DateMissingNoDate');
            } else {
                reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_WARNING, 'DateMissingFallback', [$account['datec']]);
            }
        } elseif ($account['date_rum'] > $now) {
            reedcrmSepaMandateAddIssue($row, empty($pending) ? REEDCRM_SEPA_MANDATE_WARNING : REEDCRM_SEPA_MANDATE_BLOCKING, 'DateFuture', [$account['date_rum']]);
        }

        if ($account['rum'] === '') {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_WARNING, 'RumMissing');
        } elseif (count($rumOwners[strtoupper($account['rum'])] ?? []) > 1) {
            $others = [];
            foreach ($rumOwners[strtoupper($account['rum'])] as $otherID) {
                if ($otherID !== $accountID) {
                    $others[] = $accounts[$otherID]['thirdparty'] . ($accounts[$otherID]['label'] !== '' ? ' (' . $accounts[$otherID]['label'] . ')' : '');
                }
            }
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'RumDuplicate', [implode(', ', $others)]);
        }

        if ($account['iban_empty']) {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'IbanMissing');
        } elseif (!$account['iban_valid']) {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'IbanInvalid');
        }

        if ($account['bic'] === '') {
            if (!$bicOptional) {
                reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'BicMissing');
            }
        } elseif (!$account['bic_valid']) {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'BicInvalid', [$account['bic']]);
        }

        if ($account['default'] && count($defaultsBySoc[$account['socid']] ?? []) > 1) {
            reedcrmSepaMandateAddIssue($row, empty($thirdpartyRow[$account['socid']]['requests']) ? REEDCRM_SEPA_MANDATE_WARNING : REEDCRM_SEPA_MANDATE_BLOCKING, 'DuplicateDefault');
        }

        $rows[] = $row;
    }

    // Problems of the third party itself: no bank account the core can reach for its pending requests
    foreach ($thirdpartyRow as $socID => $info) {
        $row = [
            'socid'        => $socID,
            'thirdparty'   => $socNames[$socID] ?? ($accounts[($accountsBySoc[$socID][0] ?? 0)]['thirdparty'] ?? ''),
            'account'      => null,
            'requests'     => $info['requests'],
            'designated'   => false,
            'issues'       => [],
            'severity'     => '',
            'date_written' => 0,
            'date_reason'  => '',
        ];

        foreach ($info['missing'] as $request) {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_WARNING, 'DesignatedMissing', [$request['invoice']]);
        }

        $nbRequests = count($info['requests']);
        if (empty($accountsBySoc[$socID])) {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'NoAccount', [$nbRequests]);
        } elseif (empty($defaultsBySoc[$socID])) {
            reedcrmSepaMandateAddIssue($row, REEDCRM_SEPA_MANDATE_BLOCKING, 'NoDefault', [$nbRequests]);
        }

        if (!empty($row['issues'])) {
            $rows[] = $row;
        }
    }

    // Requests that will leave with an error: their bank account or their third party carries a blocking issue
    $failing = [];
    foreach ($rows as $row) {
        if ($row['severity'] !== REEDCRM_SEPA_MANDATE_BLOCKING) {
            continue;
        }
        foreach ($row['requests'] as $request) {
            $failing[$request['id']] = true;
        }
    }

    $summary = ['accounts' => 0, 'blocking' => 0, 'warning' => 0, 'thirdparties' => 0, 'requests' => count($requests), 'failing' => count($failing)];
    foreach ($rows as $row) {
        if ($row['account'] === null) {
            $summary['thirdparties']++;
            continue;
        }
        $summary['accounts']++;
        if ($row['severity'] === REEDCRM_SEPA_MANDATE_BLOCKING) {
            $summary['blocking']++;
        } elseif ($row['severity'] === REEDCRM_SEPA_MANDATE_WARNING) {
            $summary['warning']++;
        }
    }

    // Worst first, so what blocks a pending debit opens the page
    usort($rows, function (array $a, array $b): int {
        $weight = function (array $row): int {
            $severity = $row['severity'] === REEDCRM_SEPA_MANDATE_BLOCKING ? 2 : ($row['severity'] === REEDCRM_SEPA_MANDATE_WARNING ? 1 : 0);
            return $severity * 2 + (empty($row['requests']) ? 0 : 1);
        };

        return [$weight($b), $a['thirdparty']] <=> [$weight($a), $b['thirdparty']];
    });

    return ['rows' => $rows, 'summary' => $summary, 'rule' => $rule];
}

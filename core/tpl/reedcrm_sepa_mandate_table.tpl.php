<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/reedcrm_sepa_mandate_table.tpl.php
 * \ingroup reedcrm
 * \brief   One row per bank account, or per third party when it has none the core can use, with what to fix.
 *
 * Expects: $rows (filtered rows of reedcrmSepaMandateAnalyse()), $thirdpartyStatic, $langs.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $langs;

// Issues whose first parameter is a date, to print it the way the reader writes it
$dateParamIssues = ['DateMissingFallback', 'DateFuture'];

// Pending requests shown by name on a row, the others are counted
$requestsShown = 3;
?>

<div class="reedcrm-sepamandate-tablewrap">
    <table class="reedcrm-sepamandate-table">
        <thead>
            <tr>
                <th><?php echo dol_escape_htmltag($langs->trans('SepaMandateSeverity')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('ThirdParty')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('SepaMandateBankAccount')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('SepaMandateRum')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('SepaMandateDateRum')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('SepaMandateDateWritten')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('SepaMandatePendingRequests')); ?></th>
                <th><?php echo dol_escape_htmltag($langs->trans('SepaMandateIssues')); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)) : ?>
                <tr>
                    <td colspan="9" class="reedcrm-sepamandate-empty"><?php echo dol_escape_htmltag($langs->trans('SepaMandateNone')); ?></td>
                </tr>
            <?php endif; ?>

            <?php foreach ($rows as $row) :
                $account  = $row['account'];
                $severity = $row['severity'] !== '' ? $row['severity'] : 'ok';

                $thirdpartyStatic->id     = $row['socid'];
                $thirdpartyStatic->name   = $row['thirdparty'];
                $thirdpartyStatic->status = $account !== null ? $account['soc_status'] : 1;
                ?>
                <tr class="reedcrm-sepamandate-row-<?php echo $severity; ?>">
                    <td>
                        <span class="reedcrm-sepamandate-badge reedcrm-sepamandate-is-<?php echo $severity; ?>"><?php echo dol_escape_htmltag($langs->trans('SepaMandateSeverity' . ucfirst($severity))); ?></span>
                    </td>

                    <td class="tdoverflowmax200"><?php echo $thirdpartyStatic->getNomUrl(1); ?></td>

                    <td>
                        <?php if ($account === null) : ?>
                            <span class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('SepaMandateNoUsableAccount')); ?></span>
                        <?php else : ?>
                            <span class="reedcrm-sepamandate-label"><?php echo dol_escape_htmltag($account['label'] !== '' ? $account['label'] : $langs->trans('SepaMandateNoLabel')); ?></span>
                            <?php if ($account['default']) : ?>
                                <span class="reedcrm-sepamandate-tag"><?php echo dol_escape_htmltag($langs->trans('Default')); ?></span>
                            <?php endif; ?>
                            <?php if (!$account['active']) : ?>
                                <span class="reedcrm-sepamandate-tag reedcrm-sepamandate-is-inactive"><?php echo dol_escape_htmltag($langs->trans('Disabled')); ?></span>
                            <?php endif; ?>
                            <span class="reedcrm-sepamandate-sub">
                                <?php echo dol_escape_htmltag($account['iban_masked'] !== '' ? $account['iban_masked'] : $langs->trans('SepaMandateNoIban')); ?>
                                <?php echo $account['bic'] !== '' ? ' · ' . dol_escape_htmltag($account['bic']) : ''; ?>
                            </span>
                        <?php endif; ?>
                    </td>

                    <td class="nowraponall"><?php echo $account !== null && $account['rum'] !== '' ? dol_escape_htmltag($account['rum']) : '&mdash;'; ?></td>

                    <td class="nowraponall"><?php echo $account !== null && $account['date_rum'] ? dol_print_date($account['date_rum'], 'day') : '&mdash;'; ?></td>

                    <td>
                        <?php if ($account !== null) : ?>
                            <span class="reedcrm-sepamandate-written <?php echo $row['date_written'] ? '' : 'reedcrm-sepamandate-is-empty'; ?>">
                                <?php echo $row['date_written'] ? dol_print_date($row['date_written'], 'day') : dol_escape_htmltag($langs->trans('SepaMandateDateEmpty')); ?>
                            </span>
                            <span class="reedcrm-sepamandate-sub"><?php echo dol_escape_htmltag($langs->trans('SepaMandateDateReason' . ucfirst($row['date_reason']))); ?></span>
                        <?php else : ?>
                            &mdash;
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (empty($row['requests'])) : ?>
                            <span class="opacitymedium">0</span>
                        <?php else : ?>
                            <strong><?php echo count($row['requests']); ?></strong>
                            <?php foreach (array_slice($row['requests'], 0, $requestsShown) as $request) : ?>
                                <a class="reedcrm-sepamandate-sub" href="<?php echo DOL_URL_ROOT . '/compta/facture/prelevement.php?facid=' . ((int) $request['invoice_id']); ?>" target="_blank" rel="noopener">
                                    <i class="fas fa-file-invoice paddingright opacitymedium"></i><?php echo dol_escape_htmltag($request['invoice']); ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if (count($row['requests']) > $requestsShown) : ?>
                                <span class="reedcrm-sepamandate-sub opacitymedium">&hellip;</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if (empty($row['issues'])) : ?>
                            <span class="opacitymedium"><?php echo dol_escape_htmltag($langs->trans('SepaMandateNoIssue')); ?></span>
                        <?php else : ?>
                            <ul class="reedcrm-sepamandate-issues">
                                <?php foreach ($row['issues'] as $issue) :
                                    $params = $issue['params'];
                                    if (in_array($issue['key'], $dateParamIssues, true) && !empty($params[0])) {
                                        $params[0] = dol_print_date($params[0], 'day');
                                    }
                                    ?>
                                    <li class="reedcrm-sepamandate-is-<?php echo $issue['severity']; ?>">
                                        <?php echo $langs->trans('SepaMandateIssue' . $issue['key'], (string) ($params[0] ?? ''), (string) ($params[1] ?? '')); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </td>

                    <td class="nowraponall">
                        <a class="reedcrm-btn" href="<?php echo DOL_URL_ROOT . '/societe/paymentmodes.php?socid=' . ((int) $row['socid']); ?>" target="_blank" rel="noopener" title="<?php echo dol_escape_htmltag($langs->trans('SepaMandateFixOnThirdparty')); ?>">
                            <i class="fas fa-pen"></i><?php echo dol_escape_htmltag($langs->trans('SepaMandateFix')); ?>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="reedcrm-sepamandate-note"><?php echo dol_escape_htmltag($langs->trans('SepaMandateReadOnlyNote')); ?></div>

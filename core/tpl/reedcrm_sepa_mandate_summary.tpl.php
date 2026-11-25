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
 * \file    core/tpl/reedcrm_sepa_mandate_summary.tpl.php
 * \ingroup reedcrm
 * \brief   Figures of the SEPA mandate check put forward above the detail.
 *
 * Expects: $summary (from reedcrmSepaMandateAnalyse()), $langs.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $langs;
?>

<div class="reedcrm-sepamandate-tiles">
    <div class="reedcrm-sepamandate-tile">
        <span class="reedcrm-sepamandate-tile-key"><?php echo dol_escape_htmltag($langs->trans('SepaMandateCheckedAccounts')); ?></span>
        <span class="reedcrm-sepamandate-tile-value"><?php echo (int) $summary['accounts']; ?></span>
    </div>

    <div class="reedcrm-sepamandate-tile <?php echo $summary['blocking'] > 0 ? 'reedcrm-sepamandate-is-blocking' : 'reedcrm-sepamandate-is-ok'; ?>">
        <span class="reedcrm-sepamandate-tile-key"><?php echo dol_escape_htmltag($langs->trans('SepaMandateAccountsBlocking')); ?></span>
        <span class="reedcrm-sepamandate-tile-value"><?php echo (int) $summary['blocking']; ?></span>
    </div>

    <div class="reedcrm-sepamandate-tile <?php echo $summary['warning'] > 0 ? 'reedcrm-sepamandate-is-warning' : 'reedcrm-sepamandate-is-ok'; ?>">
        <span class="reedcrm-sepamandate-tile-key"><?php echo dol_escape_htmltag($langs->trans('SepaMandateAccountsWarning')); ?></span>
        <span class="reedcrm-sepamandate-tile-value"><?php echo (int) $summary['warning']; ?></span>
    </div>

    <div class="reedcrm-sepamandate-tile <?php echo $summary['failing'] > 0 ? 'reedcrm-sepamandate-is-blocking' : 'reedcrm-sepamandate-is-ok'; ?>">
        <span class="reedcrm-sepamandate-tile-key"><?php echo dol_escape_htmltag($langs->trans('SepaMandateFailingRequests')); ?></span>
        <span class="reedcrm-sepamandate-tile-value"><?php echo (int) $summary['failing']; ?> <small>/ <?php echo (int) $summary['requests']; ?></small></span>
        <span class="reedcrm-sepamandate-tile-hint"><?php echo dol_escape_htmltag($langs->trans('SepaMandateFailingRequestsHint')); ?></span>
    </div>

    <?php if ($summary['thirdparties'] > 0) : ?>
        <div class="reedcrm-sepamandate-tile reedcrm-sepamandate-is-blocking">
            <span class="reedcrm-sepamandate-tile-key"><?php echo dol_escape_htmltag($langs->trans('SepaMandateThirdpartiesInError')); ?></span>
            <span class="reedcrm-sepamandate-tile-value"><?php echo (int) $summary['thirdparties']; ?></span>
        </div>
    <?php endif; ?>
</div>

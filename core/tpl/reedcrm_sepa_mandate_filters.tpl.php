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
 * \file    core/tpl/reedcrm_sepa_mandate_filters.tpl.php
 * \ingroup reedcrm
 * \brief   Severity and pending-only filters of the SEPA mandate check.
 *
 * Expects: $searchSeverity, $searchPending, $form, $langs.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $form, $langs;

$severityChoices = [
    ''                             => $langs->transnoentities('SepaMandateFilterAll'),
    'issues'                       => $langs->transnoentities('SepaMandateFilterIssues'),
    REEDCRM_SEPA_MANDATE_BLOCKING => $langs->transnoentities('SepaMandateSeverityBlocking'),
    REEDCRM_SEPA_MANDATE_WARNING  => $langs->transnoentities('SepaMandateSeverityWarning'),
];
?>

<form method="GET" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="reedcrm-sepamandate-filters">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">

    <div class="reedcrm-sepamandate-filter">
        <label for="search_severity"><?php echo dol_escape_htmltag($langs->trans('SepaMandateSeverity')); ?></label>
        <?php echo $form->selectarray('search_severity', $severityChoices, $searchSeverity, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200'); ?>
    </div>

    <div class="reedcrm-sepamandate-filter reedcrm-sepamandate-filter-check">
        <input type="checkbox" id="search_pending" name="search_pending" value="1"<?php echo $searchPending ? ' checked' : ''; ?>>
        <label for="search_pending"><?php echo dol_escape_htmltag($langs->trans('SepaMandateFilterPending')); ?></label>
    </div>

    <div class="reedcrm-sepamandate-filter reedcrm-sepamandate-filter-actions">
        <button type="submit" class="reedcrm-btn reedcrm-btn-primary"><i class="fas fa-search"></i><?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
        <button type="submit" class="reedcrm-btn" name="button_removefilter" value="1"><i class="fas fa-eraser"></i><?php echo dol_escape_htmltag($langs->trans('RemoveFilter')); ?></button>
    </div>
</form>

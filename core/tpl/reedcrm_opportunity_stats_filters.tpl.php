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
 * \file    core/tpl/reedcrm_opportunity_stats_filters.tpl.php
 * \ingroup reedcrm
 * \brief   Tag, depth and reference date of the monthly opportunity table.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $form, $langs;

$tagChoices = [0 => $langs->transnoentities('OpportunityStatsEveryTag')];
foreach ($projectTags as $tagID => $tagLabel) {
    $tagChoices[$tagID] = $tagLabel;
}

$yearChoices = [];
foreach (REEDCRM_OPPORTUNITY_STATS_YEAR_SPANS as $span) {
    $yearChoices[$span] = $langs->trans('OpportunityStatsLastYears', $span);
}

$dateChoices = [
    'datec' => $langs->transnoentities('OpportunityStatsDateCreation'),
    'dateo' => $langs->transnoentities('OpportunityStatsDateStart'),
];
?>

<form method="GET" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="reedcrm-oppstats-filters">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">

    <div class="reedcrm-oppstats-filter">
        <label for="search_tag"><?php echo dol_escape_htmltag($langs->trans('OpportunityStatsTag')); ?></label>
        <?php echo $form->selectarray('search_tag', $tagChoices, $searchTagID, 0, 0, 0, '', 0, 0, 0, '', 'minwidth200'); ?>
    </div>

    <div class="reedcrm-oppstats-filter">
        <label for="search_years"><?php echo dol_escape_htmltag($langs->trans('OpportunityStatsDepth')); ?></label>
        <?php echo $form->selectarray('search_years', $yearChoices, $searchYears, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150'); ?>
    </div>

    <div class="reedcrm-oppstats-filter">
        <label for="search_date_type"><?php echo dol_escape_htmltag($langs->trans('OpportunityStatsDateType')); ?></label>
        <?php echo $form->selectarray('search_date_type', $dateChoices, $searchDateType, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150'); ?>
    </div>

    <div class="reedcrm-oppstats-filter reedcrm-oppstats-filter-actions">
        <button type="submit" class="reedcrm-btn reedcrm-btn-primary"><i class="fas fa-search"></i><?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
        <button type="submit" class="reedcrm-btn" name="button_removefilter" value="1"><i class="fas fa-eraser"></i><?php echo dol_escape_htmltag($langs->trans('RemoveFilter')); ?></button>
    </div>
</form>

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
 * \file    core/tpl/reedcrm_intervention_calendar_filters.tpl.php
 * \ingroup reedcrm
 * \brief   Period navigation, filters and figures of the intervention calendar.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $conf, $form, $langs, $user;

// Every link of the page keeps the filters in hand
$baseParams  = '&view_mode=' . urlencode($viewMode);
$baseParams .= $searchSocID > 0 ? '&search_socid=' . $searchSocID : '';
$baseParams .= $searchStatus >= 0 ? '&search_status=' . $searchStatus : '';
$baseParams .= $searchPropalStatus > -2 ? '&search_propal_status=' . $searchPropalStatus : '';
foreach ($searchUsers as $searchUserID) {
    $baseParams .= '&search_users[]=' . (int) $searchUserID;
}

$propalStatuses = [
    Propal::STATUS_DRAFT     => $langs->trans('PropalStatusDraft'),
    Propal::STATUS_VALIDATED => $langs->trans('PropalStatusValidated'),
    Propal::STATUS_SIGNED    => $langs->trans('PropalStatusSigned'),
    Propal::STATUS_NOTSIGNED => $langs->trans('PropalStatusNotSigned'),
    Propal::STATUS_BILLED    => $langs->trans('PropalStatusBilled'),
];

$interventionStatuses = [
    InterventionDate::STATUS_PLANNED => $langs->trans('InterventionPlanned'),
    InterventionDate::STATUS_DONE    => $langs->trans('InterventionDone'),
];

// The "my interventions" shortcut is on when the filter holds nobody but me
$onlyMine = count($searchUsers) === 1 && (int) reset($searchUsers) === (int) $user->id;
?>

<div class="reedcrm-intervention-toolbar">
    <div class="reedcrm-intervention-period">
        <a class="reedcrm-btn reedcrm-btn-icon" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF'] . '?month=' . $previousMonth['month'] . '&year=' . $previousMonth['year'] . $baseParams); ?>" title="<?php echo dol_escape_htmltag($langs->trans('Previous')); ?>"><i class="fas fa-chevron-left"></i></a>
        <a class="reedcrm-btn reedcrm-btn-icon" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF'] . '?month=' . $nextMonth['month'] . '&year=' . $nextMonth['year'] . $baseParams); ?>" title="<?php echo dol_escape_htmltag($langs->trans('Next')); ?>"><i class="fas fa-chevron-right"></i></a>
        <span class="reedcrm-intervention-period-label"><?php echo dol_escape_htmltag(dol_print_date($firstDay, '%B %Y')); ?></span>
        <a class="reedcrm-btn" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF'] . '?' . ltrim($baseParams, '&')); ?>"><?php echo dol_escape_htmltag($langs->trans('Today')); ?></a>
    </div>

    <div class="reedcrm-intervention-views">
        <a class="reedcrm-btn<?php echo $viewMode === 'month' ? ' reedcrm-btn-on' : ''; ?>" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF'] . '?month=' . $month . '&year=' . $year . str_replace('&view_mode=list', '&view_mode=month', $baseParams)); ?>"><i class="fas fa-calendar-alt"></i><?php echo dol_escape_htmltag($langs->trans('InterventionViewMonth')); ?></a>
        <a class="reedcrm-btn<?php echo $viewMode === 'list' ? ' reedcrm-btn-on' : ''; ?>" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF'] . '?month=' . $month . '&year=' . $year . str_replace('&view_mode=month', '&view_mode=list', $baseParams)); ?>"><i class="fas fa-list"></i><?php echo dol_escape_htmltag($langs->trans('InterventionViewList')); ?></a>
    </div>
</div>

<form method="GET" action="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>" class="reedcrm-intervention-filters">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="month" value="<?php echo (int) $month; ?>">
    <input type="hidden" name="year" value="<?php echo (int) $year; ?>">
    <input type="hidden" name="view_mode" value="<?php echo dol_escape_htmltag($viewMode); ?>">

    <div class="reedcrm-intervention-filter">
        <label for="search_users"><?php echo dol_escape_htmltag($langs->trans('InterventionUsers')); ?></label>
        <?php echo $form->multiselectarray('search_users', $users, $searchUsers, 0, 0, 'minwidth200', 0, '250px', '', '', $langs->trans('InterventionUsers')); ?>
    </div>

    <div class="reedcrm-intervention-filter">
        <label for="search_socid"><?php echo dol_escape_htmltag($langs->trans('ThirdParty')); ?></label>
        <?php echo $form->select_company($searchSocID, 'search_socid', '', 'SelectThirdParty', 0, 0, [], 0, 'minwidth200'); ?>
    </div>

    <div class="reedcrm-intervention-filter">
        <label for="search_status"><?php echo dol_escape_htmltag($langs->trans('InterventionStatus')); ?></label>
        <?php echo $form->selectarray('search_status', $interventionStatuses, $searchStatus >= 0 ? $searchStatus : '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth150'); ?>
    </div>

    <div class="reedcrm-intervention-filter">
        <label for="search_propal_status"><?php echo dol_escape_htmltag($langs->trans('InterventionPropalStatus')); ?></label>
        <?php echo $form->selectarray('search_propal_status', $propalStatuses, $searchPropalStatus > -2 ? $searchPropalStatus : '', 1, 0, 0, '', 0, 0, 0, '', 'minwidth150'); ?>
    </div>

    <div class="reedcrm-intervention-filter reedcrm-intervention-filter-actions">
        <button type="submit" class="reedcrm-btn reedcrm-btn-primary"><i class="fas fa-search"></i><?php echo dol_escape_htmltag($langs->trans('Search')); ?></button>
        <button type="submit" class="reedcrm-btn" name="button_removefilter" value="1"><i class="fas fa-eraser"></i><?php echo dol_escape_htmltag($langs->trans('RemoveFilter')); ?></button>
        <?php // The button toggles : a second click hands the whole team back
        $onlyMineUrl = $_SERVER['PHP_SELF'] . '?month=' . $month . '&year=' . $year . '&view_mode=' . urlencode($viewMode) . ($onlyMine ? '' : '&mine=1'); ?>
        <a class="reedcrm-btn<?php echo $onlyMine ? ' reedcrm-btn-on' : ''; ?>" href="<?php echo dol_escape_htmltag($onlyMineUrl); ?>"><i class="fas fa-user"></i><?php echo dol_escape_htmltag($langs->trans('InterventionOnlyMine')); ?></a>
    </div>
</form>

<div class="reedcrm-intervention-stats">
    <div class="reedcrm-intervention-stat">
        <span class="reedcrm-intervention-stat-value"><?php echo count($rows); ?></span>
        <span class="reedcrm-intervention-stat-label"><?php echo dol_escape_htmltag($langs->trans('InterventionsThisMonth')); ?></span>
    </div>
    <div class="reedcrm-intervention-stat">
        <span class="reedcrm-intervention-stat-value"><?php echo $doneCount; ?></span>
        <span class="reedcrm-intervention-stat-label"><?php echo dol_escape_htmltag($langs->trans('InterventionDone')); ?></span>
    </div>
    <div class="reedcrm-intervention-stat">
        <span class="reedcrm-intervention-stat-value"><?php echo (int) $unplannedCount; ?></span>
        <span class="reedcrm-intervention-stat-label"><?php echo dol_escape_htmltag($langs->trans('InterventionToPlan')); ?></span>
    </div>
    <div class="reedcrm-intervention-stat">
        <span class="reedcrm-intervention-stat-value"><?php echo count($monthUsers); ?></span>
        <span class="reedcrm-intervention-stat-label"><?php echo dol_escape_htmltag($langs->trans('InterventionUsers')); ?></span>
    </div>
</div>

<?php if (!empty($monthUsers)) { ?>
    <div class="reedcrm-intervention-legend">
        <?php foreach ($monthUsers as $legendUserID => $legendUserLabel) { ?>
            <span class="reedcrm-intervention-legend-item">
                <span class="reedcrm-intervention-legend-color" style="background:<?php echo dol_escape_htmltag(reedcrmInterventionUserColor((int) $legendUserID)); ?>"></span>
                <?php echo dol_escape_htmltag($legendUserLabel); ?>
            </span>
        <?php } ?>
    </div>
<?php } ?>

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
 * \file    core/tpl/reedcrm_lifecycle_timeline.tpl.php
 * \ingroup reedcrm
 * \brief   Lifecycle timeline of one object, printed in the banner of its card by the
 *          formDolBanner hook. Expects $lifecycleSteps as returned by reedcrm_get_lifecycle_steps()
 *          and $lifecycleElement, the element of the object it describes. With $lifecycleCompact set,
 *          as in the rows of the opportunity list, only the dots are printed, the current step
 *          being written once underneath.
 */

// The template is required from a hook method, the globals it leans on have to be imported first
global $conf, $langs;

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

if (empty($lifecycleSteps)) {
    return;
}

$lifecycleCompact = !empty($lifecycleCompact);
?>
<div class="reedcrm-lifecycle<?php echo $lifecycleCompact ? ' reedcrm-lifecycle-compact' : ''; ?>" data-element="<?php echo dol_escape_htmltag($lifecycleElement); ?>">
    <?php foreach ($lifecycleSteps as $lifecycleStep) :
        $tooltipLines = [$lifecycleStep['label']];
        if (!empty($lifecycleStep['date'])) {
            $tooltipLines[] = dol_print_date($lifecycleStep['date'], 'dayhour', 'tzuser');
        } else {
            $tooltipLines[] = $langs->transnoentities('LifecycleTooltipPending');
        }
        if ($lifecycleStep['state'] == 'late') {
            $tooltipLines[] = $langs->transnoentities('LifecycleTooltipOverdue');
        }
        $tooltipLines = array_merge($tooltipLines, $lifecycleStep['detail']);

        // Each line is escaped on its own so the <br> separating them survives into the tooltip
        $stepTooltip = implode('<br>', array_map('dol_escape_htmltag', $tooltipLines));

        // Documents behind the milestone, the most recent first, as a table under the lines of detail
        if (!empty($lifecycleStep['documents']['rows'])) {
            $stepDocuments = $lifecycleStep['documents'];

            $stepTooltip .= '<table class="reedcrm-lifecycle-tooltip-documents"><tr>';
            foreach (['Ref' => '', 'Date' => '', 'AmountHT' => 'reedcrm-lifecycle-tooltip-amount', 'Status' => ''] as $columnLabel => $columnClass) {
                $stepTooltip .= '<th' . ($columnClass ? ' class="' . $columnClass . '"' : '') . '>' . dol_escape_htmltag($langs->transnoentities($columnLabel)) . '</th>';
            }
            $stepTooltip .= '</tr>';
            foreach (array_slice($stepDocuments['rows'], 0, REEDCRM_LIFECYCLE_TOOLTIP_DOCUMENT_LIMIT) as $document) {
                $stepTooltip .= '<tr>';
                $stepTooltip .= '<td class="reedcrm-lifecycle-tooltip-ref">' . dol_escape_htmltag($document['ref']) . '</td>';
                $stepTooltip .= '<td>' . (!empty($document['date']) ? dol_print_date($document['date'], 'day', 'tzuser') : '&ndash;') . '</td>';
                $stepTooltip .= '<td class="reedcrm-lifecycle-tooltip-amount">' . price($document['total_ht'], 0, $langs, 1, -1, -1, $conf->currency) . '</td>';
                $stepTooltip .= '<td>' . reedcrm_lifecycle_document_status($stepDocuments['element'], $document) . '</td>';
                $stepTooltip .= '</tr>';
            }
            $stepTooltip .= '</table>';

            $hiddenDocumentCount = count($stepDocuments['rows']) - REEDCRM_LIFECYCLE_TOOLTIP_DOCUMENT_LIMIT;
            if ($hiddenDocumentCount > 0) {
                $stepTooltip .= '<div class="reedcrm-lifecycle-tooltip-more">' . dol_escape_htmltag($langs->transnoentities('LifecycleTooltipMoreDocuments', $hiddenDocumentCount)) . '</div>';
            }
        }

        // The tooltip shows its title as HTML: escaped once more as a whole, the markup comes back out of the
        // attribute while the text escaped above stays text
        $stepTooltip = htmlspecialchars($stepTooltip, ENT_QUOTES, 'UTF-8');
        ?>
        <div class="reedcrm-lifecycle-step reedcrm-lifecycle-step-<?php echo dol_escape_htmltag($lifecycleStep['state']); ?><?php echo !empty($lifecycleStep['current']) ? ' reedcrm-lifecycle-step-current' : ''; ?>" data-step="<?php echo dol_escape_htmltag($lifecycleStep['key']); ?>">
            <span class="reedcrm-lifecycle-dot classfortooltip" title="<?php echo $stepTooltip; ?>"><i class="fas <?php echo dol_escape_htmltag($lifecycleStep['picto']); ?>"></i></span>
            <?php if (!$lifecycleCompact) : ?>
                <span class="reedcrm-lifecycle-label"><?php echo dol_escape_htmltag($lifecycleStep['label']); ?></span>
                <span class="reedcrm-lifecycle-date"><?php echo !empty($lifecycleStep['date']) ? dol_print_date($lifecycleStep['date'], 'day') : '&ndash;'; ?></span>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($lifecycleCompact) :
        $currentSteps = array_filter($lifecycleSteps, function ($lifecycleStep) {
            return !empty($lifecycleStep['current']);
        });
        $currentStep = reset($currentSteps);
        ?>
        <div class="reedcrm-lifecycle-caption">
            <?php if (!empty($currentStep)) : ?>
                <span class="reedcrm-lifecycle-caption-label"><?php echo dol_escape_htmltag($currentStep['label']); ?></span>
                <span class="reedcrm-lifecycle-caption-date"><?php echo dol_print_date($currentStep['date'], 'day'); ?></span>
            <?php else : ?>
                &ndash;
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

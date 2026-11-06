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
 * \file    core/tpl/todo/todo_kanban_card_links.tpl.php
 * \ingroup reedcrm
 * \brief   Third party, contact, project and source object of a card of the todo board
 *
 * Rendered with the card, then alone each time a link is changed from the card.
 *
 * Variables expected from calling TPL:
 * - $t                 array     One enriched event of reedcrmTodoGetEvents()
 * - $permissionToWrite bool      Whether the user may change the event
 * - $langs             Translate Translation object
 */

// Hovering a relaunch counter lists its relaunches, the tooltip of the relaunch buttons of the project
// list (eventpro.js). That list is read on the same third party and project as the counter.
$relaunchListUrl = dol_buildpath('/reedcrm/ajax/get_relaunches_list.php', 1);

// What the selectors of the links panel open on, see todoKanban.openLinksPanel()
$cardLinks = [
    'socid'     => ['id' => $t['soc_id'], 'text' => (string) $t['soc_name']],
    'contactid' => ['id' => $t['contact_id'], 'text' => $t['contact_name']],
    'projectid' => ['id' => $t['project_id'], 'text' => $t['project_id'] > 0 ? $t['project_ref'] . ' - ' . $t['project_title'] : ''],
];
?>
<?php if ($permissionToWrite || !empty($t['soc_id']) || !empty($t['contact_name']) || !empty($t['project_id']) || !empty($t['origin'])) : ?>
<div class="todo-card-links"<?php echo $permissionToWrite ? ' data-links="' . dol_escape_htmltag(json_encode($cardLinks)) . '"' : ''; ?>>
    <?php if (!empty($t['origin'])) : ?>
        <?php
        // The amount and the counter are glued to the reference they describe: the badge
        // gives up its right corners so they read as a single chip, and the wrapper keeps
        // them on one line
        $originRelaunchCount = (int) ($t['origin']['relaunch_count'] ?? 0);
        $originHasAmount     = !empty($t['origin']['amount_ht']);
        $originPicto         = $t['origin']['type'] == 'propal' ? 'fa-file-signature' : 'fa-file-invoice-dollar';
        ?>
        <span class="todo-origin-wrapper">
            <a class="todo-link-badge todo-link-origin<?php echo ($originRelaunchCount > 0 || $originHasAmount) ? ' todo-link-origin-counted' : ''; ?>" target="_blank" href="<?php echo $t['origin']['url']; ?>">
                <i class="fas <?php echo $originPicto; ?>"></i>
                <?php echo dol_escape_htmltag($t['origin']['ref']); ?>
            </a>
            <?php // Lines of the object, shown on hovering its reference the way the risk of a task is on the action plan of Digirisk ?>
            <?php if (!empty($t['origin']['lines'])) : ?>
                <span class="todo-origin-tooltip">
                    <span class="todo-origin-tooltip-header">
                        <i class="fas <?php echo $originPicto; ?>"></i>
                        <strong><?php echo dol_escape_htmltag($t['origin']['ref']); ?></strong>
                        <span class="todo-origin-tooltip-total"><?php echo dol_escape_htmltag($t['origin']['amount_ht_full']); ?></span>
                    </span>
                    <?php foreach ($t['origin']['lines'] as $originLine) : ?>
                        <span class="todo-origin-tooltip-line">
                            <span class="todo-origin-tooltip-label"><?php echo dol_escape_htmltag($originLine['label']); ?></span>
                            <span class="todo-origin-tooltip-amount"><?php echo dol_escape_htmltag($originLine['amount']); ?></span>
                        </span>
                    <?php endforeach; ?>
                    <?php if (!empty($t['origin']['lines_more'])) : ?>
                        <span class="todo-origin-tooltip-more"><?php echo dol_escape_htmltag($langs->trans('TodoOriginMoreLines', $t['origin']['lines_more'])); ?></span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ($originHasAmount) : ?>
                <span class="todo-origin-amount" title="<?php echo dol_escape_htmltag($t['origin']['amount_ht_full']); ?>">
                    <?php echo dol_escape_htmltag($t['origin']['amount_ht_short']); ?>
                </span>
            <?php endif; ?>
            <?php if ($originRelaunchCount > 0) : ?>
                <?php // An object without a project lists every relaunch of its third party, the way it is counted ?>
                <span class="todo-relaunch-count reedcrm-relaunch-tooltip-trigger" data-relaunch-type="all"
                      data-project-id="<?php echo (int) $t['origin']['project']; ?>" data-socid="<?php echo (int) $t['origin']['soc_id']; ?>"
                      data-dialog-url="<?php echo dol_escape_htmltag($relaunchListUrl); ?>">
                    <i class="fas fa-headset"></i> <?php echo $originRelaunchCount; ?>
                </span>
            <?php endif; ?>
        </span>
    <?php endif; ?>
    <?php if (!empty($t['soc_id'])) : ?>
        <a class="todo-link-badge todo-link-soc" target="_blank"
           href="<?php echo DOL_URL_ROOT . '/societe/card.php?socid=' . $t['soc_id']; ?>"
           title="<?php echo dol_escape_htmltag($t['soc_name']); ?>">
            <i class="fas fa-building"></i> <?php echo dol_escape_htmltag($t['soc_name']); ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($t['contact_name'])) : ?>
        <a class="todo-link-badge todo-link-contact" target="_blank"
           href="<?php echo DOL_URL_ROOT . '/contact/card.php?id=' . $t['contact_id']; ?>"
           title="<?php echo dol_escape_htmltag($t['contact_name']); ?>">
            <i class="fas fa-address-book"></i> <?php echo dol_escape_htmltag($t['contact_name']); ?>
        </a>
    <?php endif; ?>
    <?php if (!empty($t['project_id'])) : ?>
        <?php
        // Budget, probability and relaunches of the project are glued to its badge, the way the
        // amount and the counter of a proposal are to its reference
        $projectInfos         = $t['project_infos'] ?? [];
        $projectHasAmount     = !empty($projectInfos['amount_short']);
        $projectHasPercent    = isset($projectInfos['percent']);
        $projectRelaunchCount = (int) ($projectInfos['relaunch_count'] ?? 0);
        $projectCounted       = $projectHasAmount || $projectHasPercent || $projectRelaunchCount > 0;
        // The tooltip replaces the native title of the badge, kept for whoever may not read projects
        $projectHasTooltip    = !empty($projectInfos);
        ?>
        <span class="todo-origin-wrapper todo-project-wrapper">
            <a class="todo-link-badge todo-link-project<?php echo $projectCounted ? ' todo-link-origin-counted' : ''; ?>" target="_blank"
               href="<?php echo DOL_URL_ROOT . '/projet/card.php?id=' . $t['project_id']; ?>"
               <?php if (!$projectHasTooltip) : ?>title="<?php echo dol_escape_htmltag($t['project_ref'] . ' - ' . $t['project_title']); ?>"<?php endif; ?>>
                <i class="fas fa-project-diagram"></i> <?php echo dol_escape_htmltag($t['project_title'] ?: $t['project_ref']); ?>
            </a>
            <?php // Infos of the project, shown on hovering its badge the same way the lines of a proposal are ?>
            <?php if ($projectHasTooltip) : ?>
                <span class="todo-origin-tooltip todo-project-tooltip">
                    <span class="todo-origin-tooltip-header">
                        <i class="fas fa-project-diagram"></i>
                        <strong><?php echo dol_escape_htmltag($t['project_ref']); ?></strong>
                        <span class="todo-origin-tooltip-total"><?php echo $projectInfos['status']; ?></span>
                    </span>
                    <?php if (!empty($t['project_title'])) : ?>
                        <span class="todo-project-tooltip-title"><?php echo dol_escape_htmltag($t['project_title']); ?></span>
                    <?php endif; ?>
                    <?php foreach ($projectInfos['details'] as $projectDetail) : ?>
                        <span class="todo-origin-tooltip-line">
                            <span class="todo-origin-tooltip-label"><?php echo dol_escape_htmltag($projectDetail['label']); ?></span>
                            <span class="todo-origin-tooltip-amount"><?php echo dol_escape_htmltag($projectDetail['value']); ?></span>
                        </span>
                    <?php endforeach; ?>
                    <?php if ($projectRelaunchCount > 0) : ?>
                        <span class="todo-origin-tooltip-line">
                            <span class="todo-origin-tooltip-label"><?php echo dol_escape_htmltag($langs->trans('TodoProjectRelaunches')); ?></span>
                            <span class="todo-origin-tooltip-amount"><i class="fas fa-headset"></i> <?php echo $projectRelaunchCount; ?></span>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($projectInfos['description'])) : ?>
                        <span class="todo-origin-tooltip-more todo-project-tooltip-description"><?php echo dol_escape_htmltag($projectInfos['description']); ?></span>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ($projectHasAmount) : ?>
                <span class="todo-origin-amount" title="<?php echo dol_escape_htmltag($projectInfos['amount_full']); ?>">
                    <?php echo dol_escape_htmltag($projectInfos['amount_short']); ?>
                </span>
            <?php endif; ?>
            <?php if ($projectHasPercent) : ?>
                <span class="todo-origin-amount" title="<?php echo dol_escape_htmltag($langs->trans('TodoProjectProbability', $projectInfos['percent'] . '%')); ?>">
                    <?php echo (int) $projectInfos['percent']; ?>%
                </span>
            <?php endif; ?>
            <?php if ($projectRelaunchCount > 0) : ?>
                <?php // The list falls back on the third party of the project, the one its relaunches are counted on ?>
                <span class="todo-relaunch-count reedcrm-relaunch-tooltip-trigger" data-relaunch-type="all"
                      data-project-id="<?php echo (int) $t['project_id']; ?>"
                      data-dialog-url="<?php echo dol_escape_htmltag($relaunchListUrl); ?>">
                    <i class="fas fa-headset"></i> <?php echo $projectRelaunchCount; ?>
                </span>
            <?php endif; ?>
        </span>
    <?php endif; ?>
    <?php if ($permissionToWrite) : ?>
        <button type="button" class="todo-links-btn" title="<?php echo dol_escape_htmltag($langs->trans('TodoEditLinks')); ?>">
            <i class="fas fa-link"></i>
        </button>
    <?php endif; ?>
</div>
<?php endif; ?>

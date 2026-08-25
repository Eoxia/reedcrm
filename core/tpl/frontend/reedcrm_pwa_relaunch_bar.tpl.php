<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/reedcrm_pwa_relaunch_bar.tpl.php
 * \ingroup reedcrm
 * \brief   Sticky relaunch bar of the opportunity App page: one counter per relaunch type, each with
 *          a quick add shortcut. Same counting rule as the desktop project list (only events carrying
 *          the commercial relaunch tag), so both screens show the same numbers.
 *          Expects $project (Project) and $oppRelaunchCounts (type key => count). $callListId, when
 *          the page was reached from a call list, is carried over so the whole add-an-event round
 *          trip comes back with the way home still in the header.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $langs, $user;

$canAddEvent   = $user->hasRight('agenda', 'myactions', 'create') && $user->hasRight('reedcrm', 'eventpro', 'write');
$relaunchUrl   = dol_buildpath('/custom/reedcrm/view/frontend/pwa_relaunch.php', 1);
$relaunchQuery = '?from_id=' . (int) $project->id . '&from_type=project';
$relaunchQuery .= !empty($callListId) ? '&call_list_id=' . (int) $callListId : '';
?>
<div class="pwa-opp-relaunch-bar">
    <div class="reedcrm-relaunch-buttons">
        <?php foreach (reedcrm_get_relaunch_types() as $typeKey => $type) :
            $count = (int) ($oppRelaunchCounts[$typeKey] ?? 0); ?>
            <div class="reedcrm-relaunch-button reedcrm-relaunch-btn-<?php echo $typeKey; ?><?php echo $count === 0 ? ' count-zero' : ''; ?>">
                <div class="reedcrm-relaunch-btn-content">
                    <i class="fas fa-<?php echo $type['picto']; ?>"></i>
                    <span class="reedcrm-relaunch-count"><?php echo $count; ?></span>
                </div>
                <?php if ($canAddEvent) : ?>
                    <a class="reedcrm-relaunch-add" href="<?php echo dol_escape_htmltag($relaunchUrl . $relaunchQuery . '&actioncode=' . $type['actioncode']); ?>" title="<?php echo dol_escape_htmltag($langs->trans('QuickEventCreation')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('QuickEventCreation')); ?>">
                        <i class="fas fa-plus"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

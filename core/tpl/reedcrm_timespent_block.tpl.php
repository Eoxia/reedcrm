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
 * \file    core/tpl/reedcrm_timespent_block.tpl.php
 * \ingroup reedcrm
 * \brief   Quick time logging block of the ticket and project cards, with the recap of the time spent.
 *          Rendered by the printCommonFooter hook (inherits its scope): the JS moves it up into the card header.
 *          Expects: $object (Ticket or Project), $db, $langs, $user.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

require_once __DIR__ . '/../../lib/reedcrm_timespent.lib.php';

$timeSpentSummary    = reedcrm_timespent_get_summary($db, $object);
$timeSpentHasProject = $object instanceof Project || $object->fk_project > 0;
$timeSpentCanLog     = $timeSpentHasProject && reedcrm_timespent_user_can_log($user);
?>
<div id="reedcrm-timespent-block" class="reedcrm-timespent-block<?php echo $timeSpentHasProject ? '' : ' reedcrm-timespent-block-warning'; ?>" hidden
     data-url="<?php echo dol_escape_htmltag(dol_buildpath('/reedcrm/ajax/save_time_spent.php', 1)); ?>"
     data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
     data-element-type="<?php echo $object instanceof Project ? 'project' : 'ticket'; ?>"
     data-element-id="<?php echo (int) $object->id; ?>"
     data-trans-error="<?php echo dol_escape_htmltag($langs->trans('ReedCRMTimeSpentSaveError')); ?>">
    <div class="reedcrm-timespent-row">
        <img class="reedcrm-timespent-logo" src="<?php echo dol_escape_htmltag(dol_buildpath('/reedcrm/img/reedcrm_color.png', 1)); ?>" alt="ReedCRM">
        <span class="reedcrm-timespent-entries-wrap">
            <a class="reedcrm-timespent-entries classfortooltip"<?php echo $timeSpentSummary['url'] !== '' ? ' href="' . dol_escape_htmltag($timeSpentSummary['url']) . '"' : ''; ?> title="<?php echo dol_escape_htmltag(reedcrm_timespent_render_latest_tooltip($timeSpentSummary), 1, 1, 'br,b'); ?>">
                <i class="fas fa-list"></i>
                <span class="reedcrm-timespent-count"<?php echo $timeSpentSummary['count'] > 0 ? '' : ' hidden'; ?>><?php echo (int) $timeSpentSummary['count']; ?></span>
            </a>
        </span>
        <?php if (!$timeSpentHasProject) { ?>
            <span class="reedcrm-timespent-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo dol_escape_htmltag($langs->trans('PleaseLinkProjectFirst')); ?></span>
        <?php } elseif ($timeSpentCanLog) { ?>
            <textarea class="reedcrm-timespent-note" rows="1" placeholder="<?php echo dol_escape_htmltag($langs->trans('Note')); ?>"></textarea>
            <input type="number" class="reedcrm-timespent-minutes" min="1" value="<?php echo getDolGlobalInt('REEDCRM_TICKET_TIME_DEFAULT_MINUTES', 15); ?>">
            <span class="reedcrm-timespent-unit"><?php echo $langs->trans('MinuteShort'); ?></span>
            <button type="button" class="reedcrm-timespent-save" title="<?php echo dol_escape_htmltag($langs->trans('Save')); ?>"><i class="fas fa-save"></i></button>
        <?php } ?>
    </div>
    <?php echo reedcrm_timespent_render_last_entry($timeSpentSummary); ?>
    <?php if ($timeSpentHasProject) {
        echo reedcrm_timespent_render_recap($timeSpentSummary, $object);
    } ?>
</div>

<script type="text/javascript" src="<?php echo dol_escape_htmltag(dol_buildpath('/reedcrm/js/modules/timespent_block.js', 1)); ?>"></script>

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
 * \file    core/tpl/frontend/reedcrm_pwa_opportunity_timeline.tpl.php
 * \ingroup reedcrm
 * \brief   Day-grouped event timeline of one opportunity, for the App.
 *          Expects $oppEvents (ActionComm[] as returned by ActionComm::getActions(), newest first).
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $db, $form, $langs, $user;

$canEditEvent = $user->hasRight('agenda', 'myactions', 'create');

if (empty($oppEvents)) : ?>
    <div class="pwa-call-list-empty">
        <i class="fas fa-inbox"></i>
        <p><?php echo $langs->trans('NoEventOnOpportunity'); ?></p>
    </div>
<?php else :
    // One User fetch per owner instead of one per event: a busy opportunity repeats the same few owners
    $timelineUsers = [];
    $currentDayKey = null;
    ?>
    <div class="pwa-opp-timeline">
    <?php foreach ($oppEvents as $event) :
        $eventDate = !empty($event->datep) ? $event->datep : $event->datec;
        $dayKey    = dol_print_date($eventDate, '%Y%m%d', 'tzuser');

        if ($dayKey !== $currentDayKey) {
            $currentDayKey = $dayKey;
            print '<div class="pwa-opp-timeline-day">' . dol_print_date($eventDate, 'daytext', 'tzuser') . '</div>';
        }

        $ownerId = (int) $event->userownerid;
        if ($ownerId > 0 && !isset($timelineUsers[$ownerId])) {
            $owner = new User($db);
            $timelineUsers[$ownerId] = ($owner->fetch($ownerId) > 0) ? $owner : null;
        }
        $owner = $timelineUsers[$ownerId] ?? null;

        $percentage = (int) $event->percentage;
        $note       = trim(dol_string_nohtmltag((string) $event->note_private, 0));
        $typeKey    = reedcrm_get_relaunch_type_key((string) $event->type_code);
        ?>
        <div class="pwa-opp-event" data-relaunch-type="<?php echo dol_escape_htmltag($typeKey); ?>">
            <div class="pwa-opp-event-bullet">
                <?php echo $owner !== null ? $form->showphoto('userphoto', $owner, 0, 0, 0, 'pwa-opp-event-avatar', 'mini', 0) : '<i class="fas fa-user-circle"></i>'; ?>
            </div>
            <div class="pwa-opp-event-card">
                <div class="pwa-opp-event-meta">
                    <span class="pwa-opp-event-user"><?php echo $owner !== null ? dol_escape_htmltag($owner->firstname ?: $owner->lastname) : $langs->trans('Unknown'); ?></span>
                    <span class="pwa-opp-event-percent"><?php echo $percentage < 0 ? $langs->trans('ActionNotApplicable') : $percentage . ' %'; ?></span>
                    <span class="pwa-opp-event-date"><i class="fas fa-clock"></i> <?php echo dol_print_date($eventDate, 'dayhour', 'tzuser'); ?></span>
                    <span class="pwa-opp-event-id"><i class="fas fa-calendar-alt"></i> <?php echo (int) $event->id; ?></span>
                    <?php if ($canEditEvent) : ?>
                        <a class="pwa-opp-event-edit" href="<?php echo dol_buildpath('/comm/action/card.php', 1) . '?id=' . (int) $event->id . '&action=edit&token=' . newToken(); ?>" title="<?php echo dol_escape_htmltag($langs->trans('Modify')); ?>"><i class="fas fa-pencil-alt"></i></a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($event->label)) : ?>
                    <div class="pwa-opp-event-label"><?php echo dol_escape_htmltag($event->label); ?></div>
                <?php endif; ?>
                <?php if ($note !== '') : ?>
                    <?php /* keepn = 1: the default turns the real line breaks of a note into visible "\n" */ ?>
                    <div class="pwa-opp-event-note"><?php echo nl2br(dol_escape_htmltag($note, 0, 1)); ?></div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>

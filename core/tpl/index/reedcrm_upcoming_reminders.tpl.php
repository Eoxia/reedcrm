<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/index/reedcrm_upcoming_reminders.tpl.php
 * \ingroup reedcrm
 * \brief   Dashboard panel listing the upcoming automatic call reminders of the current user.
 *          Reuses the native Saturne .wpeo-infobox chrome so it reads as a dashboard card.
 */

/**
 * The following variables must be defined by the caller:
 *
 * @var DoliDB    $db
 * @var Translate $langs
 * @var array     $reminders List of reminder objects (id, label, datep, fk_soc, thirdparty_name)
 */

?>
<div class="reedcrm-reminders-slot">
<div class="wpeo-infobox reedcrm-reminders">
    <div class="wpeo-infobox__header">
        <div class="header__icon-container">
            <span class="header__icon-background" style="background: #0d8aff;"></span>
            <i class="header__icon fas fa-bell" style="color: #0d8aff;"></i>
        </div>
        <div class="header__title"><?php echo $langs->trans('UpcomingCallReminders'); ?></div>
        <?php if (!empty($reminders)) : ?>
            <span class="reedcrm-reminders__count"><?php echo count($reminders); ?></span>
        <?php endif; ?>
    </div>
    <div class="wpeo-infobox__body">
        <?php if (empty($reminders)) : ?>
            <div class="reedcrm-reminders__empty">
                <i class="far fa-bell-slash"></i>
                <?php echo $langs->trans('NoUpcomingCallReminder'); ?>
            </div>
        <?php else : ?>
            <ul class="reedcrm-reminders__list">
                <?php foreach ($reminders as $reminder) :
                    $reminderTs = $db->jdate($reminder->datep); ?>
                    <li class="reedcrm-reminders__item">
                        <span class="reedcrm-reminders__tile">
                            <span class="reedcrm-reminders__day"><?php echo dol_print_date($reminderTs, '%d', 'tzuserrel'); ?></span>
                            <span class="reedcrm-reminders__month"><?php echo rtrim($langs->trans('MonthShort' . dol_print_date($reminderTs, '%m', 'tzuserrel')), '.'); ?></span>
                        </span>
                        <span class="reedcrm-reminders__content">
                            <a class="reedcrm-reminders__label" href="<?php echo dol_buildpath('/comm/action/card.php', 1) . '?id=' . ((int) $reminder->id); ?>"><?php echo dol_escape_htmltag($reminder->label); ?></a>
                            <span class="reedcrm-reminders__meta">
                                <span class="reedcrm-reminders__time"><i class="far fa-clock"></i><?php echo dol_print_date($reminderTs, '%H:%M', 'tzuserrel'); ?></span>
                                <?php if (!empty($reminder->fk_soc)) : ?>
                                    <a class="reedcrm-reminders__thirdparty" href="<?php echo DOL_URL_ROOT . '/societe/card.php?socid=' . ((int) $reminder->fk_soc); ?>"><i class="fas fa-building"></i><?php echo dol_escape_htmltag($reminder->thirdparty_name); ?></a>
                                <?php endif; ?>
                            </span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
</div>

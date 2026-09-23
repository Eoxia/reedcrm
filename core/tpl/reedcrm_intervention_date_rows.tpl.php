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
 * \file    core/tpl/reedcrm_intervention_date_rows.tpl.php
 * \ingroup reedcrm
 * \brief   One row per intervention expected by the quantity of a service line.
 *          Rendered by ajax/get_intervention_dates.php and dropped into the modal.
 *
 * Expected variables : $interventionDates, $interventionExpected,
 *                      $interventionUsers, $interventionDefaultDuration, $interventionCanWrite
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $db, $langs;

$readonly = $interventionCanWrite ? '' : ' disabled';
?>
<?php if ($interventionExpected <= 0) { ?>
    <div class="reedcrm-intervention-empty"><?php echo dol_escape_htmltag($langs->trans('InterventionDateNoQuantity')); ?></div>
<?php } else { ?>
    <?php for ($position = 1; $position <= $interventionExpected; $position++) {
        $interventionDate = $interventionDates[$position] ?? null;
        $timestamp        = 0;
        if (!empty($interventionDate) && !empty($interventionDate->date_intervention)) {
            $timestamp = is_numeric($interventionDate->date_intervention) ? (int) $interventionDate->date_intervention : (int) $db->jdate($interventionDate->date_intervention);
        }
        $duration = !empty($interventionDate) && $interventionDate->duration > 0 ? (int) $interventionDate->duration : $interventionDefaultDuration;
        $userID   = !empty($interventionDate) ? (int) $interventionDate->fk_user_intervenant : 0;
        $status   = !empty($interventionDate) ? (int) $interventionDate->status : InterventionDate::STATUS_TO_PLAN;
        ?>
        <div class="reedcrm-intervention-row<?php echo $timestamp > 0 ? ' reedcrm-intervention-row-planned' : ''; ?>" data-position="<?php echo $position; ?>">
            <span class="reedcrm-intervention-index" style="background:<?php echo dol_escape_htmltag(reedcrmInterventionUserColor($userID)); ?>"><?php echo $position; ?></span>

            <label class="reedcrm-intervention-field">
                <span class="reedcrm-intervention-label"><?php echo dol_escape_htmltag($langs->trans('Date')); ?></span>
                <input type="date" name="intervention_date[<?php echo $position; ?>]" value="<?php echo $timestamp > 0 ? dol_print_date($timestamp, '%Y-%m-%d') : ''; ?>"<?php echo $readonly; ?>>
            </label>

            <label class="reedcrm-intervention-field reedcrm-intervention-field-time">
                <span class="reedcrm-intervention-label"><?php echo dol_escape_htmltag($langs->trans('Hour')); ?></span>
                <input type="time" name="intervention_time[<?php echo $position; ?>]" value="<?php echo $timestamp > 0 ? dol_print_date($timestamp, '%H:%M') : ''; ?>"<?php echo $readonly; ?>>
            </label>

            <label class="reedcrm-intervention-field reedcrm-intervention-field-duration">
                <span class="reedcrm-intervention-label"><?php echo dol_escape_htmltag($langs->trans('InterventionDurationMinutes')); ?></span>
                <input type="number" min="0" step="15" name="intervention_duration[<?php echo $position; ?>]" value="<?php echo $duration; ?>"<?php echo $readonly; ?>>
            </label>

            <label class="reedcrm-intervention-field reedcrm-intervention-field-user">
                <span class="reedcrm-intervention-label"><?php echo dol_escape_htmltag($langs->trans('InterventionUser')); ?></span>
                <?php // The select2:open handler of lib_head.js.php builds a CSS selector out of the id, then the name :
                      // brackets would make it invalid and the dropdown would stay on "Searching..." forever ?>
                <select id="reedcrm_intervention_user_<?php echo $position; ?>" name="intervention_user[<?php echo $position; ?>]" class="reedcrm-intervention-user"<?php echo $readonly; ?>>
                    <option value="0"><?php echo dol_escape_htmltag('-- ' . $langs->trans('InterventionNoUser') . ' --'); ?></option>
                    <?php foreach ($interventionUsers as $interventionUserID => $interventionUserName) { ?>
                        <option value="<?php echo $interventionUserID; ?>"<?php echo $userID == $interventionUserID ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($interventionUserName); ?></option>
                    <?php } ?>
                </select>
            </label>

            <label class="reedcrm-intervention-field reedcrm-intervention-field-location">
                <span class="reedcrm-intervention-label"><?php echo dol_escape_htmltag($langs->trans('InterventionLocation')); ?></span>
                <input type="text" maxlength="255" list="reedcrm-intervention-locations" name="intervention_location[<?php echo $position; ?>]" value="<?php echo !empty($interventionDate) ? dol_escape_htmltag($interventionDate->location) : ''; ?>" placeholder="<?php echo dol_escape_htmltag($interventionLocations[0] ?? ''); ?>"<?php echo $readonly; ?>>
            </label>

            <label class="reedcrm-intervention-field reedcrm-intervention-field-note">
                <span class="reedcrm-intervention-label"><?php echo dol_escape_htmltag($langs->trans('Note')); ?></span>
                <input type="text" maxlength="255" name="intervention_note[<?php echo $position; ?>]" value="<?php echo !empty($interventionDate) ? dol_escape_htmltag($interventionDate->note) : ''; ?>"<?php echo $readonly; ?>>
            </label>

            <label class="reedcrm-intervention-done">
                <input type="checkbox" name="intervention_done[<?php echo $position; ?>]" value="1"<?php echo $status == InterventionDate::STATUS_DONE ? ' checked' : ''; ?><?php echo $readonly; ?>>
                <span><?php echo dol_escape_htmltag($langs->trans('InterventionDone')); ?></span>
            </label>

            <?php if (!empty($interventionDate) && $interventionDate->fk_actioncomm > 0) { ?>
                <a class="reedcrm-intervention-event" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/comm/action/card.php?id=' . (int) $interventionDate->fk_actioncomm); ?>" target="_blank" title="<?php echo dol_escape_htmltag($langs->trans('InterventionSeeEvent')); ?>"><i class="fas fa-calendar-alt"></i></a>
            <?php } ?>
        </div>
    <?php } ?>

    <?php // Addresses of the third party and of the project, offered to every row of the modal ?>
    <datalist id="reedcrm-intervention-locations">
        <?php foreach ($interventionLocations as $interventionLocation) { ?>
            <option value="<?php echo dol_escape_htmltag($interventionLocation); ?>"></option>
        <?php } ?>
    </datalist>

    <p class="reedcrm-intervention-help"><?php echo dol_escape_htmltag($langs->trans('InterventionDateHelp')); ?></p>
<?php } ?>

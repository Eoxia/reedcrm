<?php

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

?>

<div class="wpeo-grid grid-1">
    <?php // The titles of the sections are only shown in the App (eventpro-app-only), the desktop boxes carry placeholders ?>
    <div>
        <span class="eventpro-field-title eventpro-app-only"><i class="fas fa-pen reedcrm-reminder-label-picto"></i><?php echo $langs->trans('Title'); ?></span>
        <label for="title">
            <input type="text" id="title" name="title"  maxlength="255" placeholder="<?php echo $langs->trans('Title'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')); ?>">
        </label>
    </div>
    <div>
        <span class="eventpro-field-title eventpro-app-only"><i class="fas fa-align-left reedcrm-reminder-label-picto"></i><?php echo $langs->trans('Description'); ?></span>
        <div<?php echo !empty($eventProVoiceBlock) ? ' class="eventpro-description-with-voice"' : ''; ?>>
            <label for="description">
                <textarea name="description" id="description" rows="6" placeholder="<?php echo $langs->trans('Description'); ?>"><?php echo dol_escape_htmltag((GETPOSTISSET('description') ? GETPOST('description', 'restricthtml') : '')); ?></textarea>
            </label>
            <?php // Voice note beside the description, on the pages offering it (App, see reedcrm_eventpro_voice_context()) ?>
            <?php echo $eventProVoiceBlock ?? ''; ?>
        </div>
    </div>
    <div class="event-status-row">
        <label for="event_percentage">
            <span class="event-status-row__label"><i class="fas fa-tasks reedcrm-reminder-label-picto eventpro-app-only"></i><?php echo $langs->trans('Status'); ?></span>
            <?php echo $form->selectarray('event_percentage', reedcrm_get_event_statuses(), GETPOSTISSET('event_percentage') ? GETPOSTINT('event_percentage') : 100, 0, 0, 0, '', 1, 0, 0, '', 'maxwidth200'); ?>
        </label>
    </div>

    <label class="eventpro-reminder-toggle" style="cursor: pointer; display: inline-flex; align-items: center; gap: 6px; margin-top: 4px;">
        <input type="checkbox" id="toggle_reminder" name="add_reminder" value="1" style="margin: 0;">
        <i class="far fa-bell"></i>
        <?= $langs->trans('AddReminder'); ?>
    </label>

    <?php // Titled in the App with the questions of the direct reminder form (view_eventpro_reminder.tpl.php) ?>
    <div id="reminder_fields" class="reminder-fields" style="display: none;">
        <div>
            <span class="eventpro-field-title eventpro-app-only"><i class="fas fa-pen reedcrm-reminder-label-picto"></i><?php echo $langs->trans('RelaunchWhat'); ?></span>
            <label for="reminder_title">
                <input type="text" id="reminder_title" name="reminder_title" maxlength="255" placeholder="<?php echo $langs->trans('Title'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('reminder_title') ? GETPOST('reminder_title') : '')); ?>">
            </label>
        </div>

        <div class="reminder-type-row">
            <span class="eventpro-field-title eventpro-app-only"><i class="fas fa-tags reedcrm-reminder-label-picto"></i><?php echo $langs->trans('Type'); ?></span>
            <?php echo $form->selectarray('reminder_actioncode', reedcrm_get_relaunch_type_options(), GETPOSTISSET('reminder_actioncode') ? GETPOST('reminder_actioncode', 'aZ09') : 'AC_OTH', 0, 0, 0, '', 0, 0, 0, '', 'maxwidth200'); ?>
        </div>

        <div class="reminder-date-row">
            <span class="eventpro-field-title eventpro-app-only"><i class="far fa-clock reedcrm-reminder-label-picto"></i><?php echo $langs->trans('RelaunchWhen'); ?></span>
            <?php // Tomorrow by default: a reminder set to now is already past, so it would never be listed as upcoming ?>
            <?php echo $form->selectDate(dol_time_plus_duree(dol_now(), 1, 'd'), 'reminder_', 1, 1, 0, "addeventform", 1, 0, 0, '', '', '', '', 1, '', '', 'tzuserrel'); ?>
        </div>

        <div class="reminder-user-row">
            <span class="eventpro-field-title eventpro-app-only"><i class="fas fa-user reedcrm-reminder-label-picto"></i><?php echo $langs->trans('RelaunchWho'); ?></span>
            <?php echo img_picto('', 'user'); ?>
            <?php echo $form->select_dolusers(GETPOSTISSET('reminder_user_id') ? GETPOSTINT('reminder_user_id') : $user->id, 'reminder_user_id', 0, null, 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200'); ?>
        </div>
    </div>
    <script>
    $(function() {
        /*
         * Fix mise en page selectDate dans wpeo-grid (flex).
         * jQuery UI injecte le bouton calendrier à l'intérieur de .divfordateinput (après l'<input>),
         * ce qui le "colle" visuellement au champ date. On le déplace ici entre le champ date
         * et le <span> des heures pour obtenir : [date] [calendrier] [heure:min].
         * Limité aux prefixes de cette page (reminder_, event_) pour ne pas affecter les autres pages.
         */
        ['#reminder_', '#event_'].forEach(function(selector) {
            var $input = $(selector);
            if (!$input.length) return;
            var $trigger = $input.siblings('.ui-datepicker-trigger');
            var $hoursSpan = $input.closest('.divfordateinput').nextAll('span.nowraponall').first();
            if ($trigger.length && $hoursSpan.length) {
                $hoursSpan.before($trigger.detach());
            }
        });
    });
    </script>

    <?php if ($permissiontoadd): ?>
        <div class="center">
            <button type="submit" class="butAction"><?php echo$langs->trans('Add'); ?></button>
        </div>
    <?php endif; ?>

</div>

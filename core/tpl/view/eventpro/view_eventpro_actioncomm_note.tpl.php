<?php

?>

<div class="wpeo-grid grid-1">
    <div>
        <label for="title">
            <input type="text" id="title" name="title"  maxlength="255" placeholder="<?php echo $langs->trans('Title'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')); ?>">
        </label>
    </div>
    <div>
        <label for="description">
            <textarea name="description" id="description" rows="6" placeholder="<?php echo $langs->trans('Description'); ?>"><?php echo dol_escape_htmltag((GETPOSTISSET('description') ? GETPOST('description', 'restricthtml') : '')); ?></textarea>
        </label>
    </div>

    <label>
        <i class="far fa-bell"></i>
        <?= $langs->trans('AddReminder'); ?>
        <input type="checkbox" name="add_reminder" value="1">
    </label>

    <div>
        <label for="title">
            <input type="text" id="title" name="reminder_title"  maxlength="255" placeholder="<?php echo $langs->trans('Title'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('reminder_title') ? GETPOST('reminder_title') : '')); ?>">
        </label>
    </div>


    <div style="display: flex; align-items: center;">
        <?php echo $form->selectDate(dol_now('tzuser'), 'reminder_', 1, 1); ?>
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

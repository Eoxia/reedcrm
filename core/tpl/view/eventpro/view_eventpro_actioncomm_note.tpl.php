<?php

?>

<div class="wpeo-grid grid-1">
    <div>
        <label for="title">
            <input type="text" id="title" name="title"  maxlength="50" placeholder="<?php echo $langs->trans('Title'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')); ?>">
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
            <input type="text" id="title" name="reminder_title"  maxlength="50" placeholder="<?php echo $langs->trans('Title'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('reminder_title') ? GETPOST('reminder_title') : '')); ?>">
        </label>
    </div>


    <div>
        <label>
            <div class="select2-container"><?php echo $form->selectDate(dol_now('tzuser'), 'reminder_', 1, 1); ?></div>
        </label>
    </div>

    <?php if ($permissiontoadd) : ?>
        <div class="center">
            <button type="submit" class="butAction"><?php echo$langs->trans('Add'); ?></button>
        </div>
    <?php endif; ?>

</div>


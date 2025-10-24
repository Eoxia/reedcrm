<?php

?>

<div class="grid-2">
    <label for="title">
        <input type="text" id="title" name="title"  maxlength="50" placeholder="<?php echo $langs->trans('Title'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')); ?>">
    </label>
    <label for="description">
        <textarea name="description" id="description" rows="6" placeholder="<?php echo $langs->trans('Description'); ?>"><?php echo dol_escape_htmltag((GETPOSTISSET('description') ? GETPOST('description', 'restricthtml') : '')); ?></textarea>
    </label>

    <?php if ($permissiontoadd) : ?>
        <div class="center">
            <button type="submit" class="butAction"><?php echo$langs->trans('Add'); ?></button>
        </div>
    <?php endif; ?>
</div>


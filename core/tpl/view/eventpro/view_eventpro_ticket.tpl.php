<?php

?>

<form action="<?php echo $_SERVER['PHP_SELF']; ?>?from_id=' . $id; ?>&from_type=' . $fromType; ?>&tab=' . $currentTab; ?>" method="POST" class="border" id="createticketform">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="create_ticket">
    <input type="hidden" name="from_id" value="<?php echo $id; ?>">
    <input type="hidden" name="from_type" value="<?php echo $fromType; ?>">
    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">

    <table class="border centpercent">
        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("Type"); ?></td>
            <td>
                <?php print $formTicket->selectTypesTickets(GETPOST('ticket_type', 'aZ09'), 'ticket_type', '', 2); ?>
            </td>
        </tr>

        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("Label"); ?></td>
            <td>
                <input type="text" name="ticket_subject" class="quatrevingtpercent" maxlength="255" value="<?php echo GETPOST('ticket_subject'); ?>" required>
            </td>
        </tr>

        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("DateStart"); ?></td>
            <td>
                <?php $form->selectDate(GETPOST('ticket_date_start', 'int') ? GETPOST('ticket_date_start', 'int') : dol_now(), 'ticket_date_start', 1, 1, 1); ?>
            </td>
        </tr>

        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("TimeSpent"); ?></td>
            <td>
                <input type="number" name="ticket_timing" class="minwidth100" min="0" value="<?php echo GETPOST('ticket_timing', 'int'); ?>"><?php echo $langs->trans("Minutes"); ?>
            </td>
        </tr>

        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("Reminder"); ?></td>
            <td>
                <select name="ticket_reminder" class="minwidth200">
                    <option value=""><?php echo $langs->trans("None"); ?></option>
                    <option value="15"<?php echo (GETPOST('ticket_reminder', 'int') == 15 ? ' selected' : ''); ?>>15 <?php echo $langs->trans("Minutes"); ?></option>
                    <option value="30"<?php echo (GETPOST('ticket_reminder', 'int') == 30 ? ' selected' : ''); ?>>30 <?php echo $langs->trans("Minutes"); ?></option>
                    <option value="60"<?php echo (GETPOST('ticket_reminder', 'int') == 60 ? ' selected' : ''); ?>>1 <?php echo $langs->trans("Hour"); ?></option>
                    <option value="120"<?php echo (GETPOST('ticket_reminder', 'int') == 120 ? ' selected' : ''); ?>>2 <?php echo $langs->trans("Hours"); ?></option>
                    <option value="240"<?php echo (GETPOST('ticket_reminder', 'int') == 240 ? ' selected' : ''); ?>>4 <?php echo $langs->trans("Hours"); ?></option>
                    <option value="480"<?php echo (GETPOST('ticket_reminder', 'int') == 480 ? ' selected' : ''); ?>>8 <?php echo $langs->trans("Hours"); ?></option>
                    <option value="1440"<?php echo (GETPOST('ticket_reminder', 'int') == 1440 ? ' selected' : ''); ?>>1 <?php echo $langs->trans("Day"); ?></option>
                </select>
            </td>
        </tr>

        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("ThirdParty"); ?></td>
            <td>
                <?php $form->select_company($object->thirdparty->id, 'ticket_socid', '', 'minwidth200', 0, 0, [], 0, 'maxwidth300'); ?>
            </td>
        </tr>

        <tr class="oddeven">
            <td><?php echo $langs->trans("Project"); ?></td><td colspan="3">
                <?php $formProject->select_projects($object->thirdparty->id, $object->id, 'project_id'); ?>
            </td>
        </tr>

        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("AssignedTo"); ?></td>
            <td>
                <?php print $form->select_dolusers(GETPOST('ticket_user_assign', 'int') ?: $user->id, 'ticket_user_assign', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 1); ?>
            </td>
        </tr>
    </table>

    <table class="border centpercent">
        <tr class="oddeven">
            <td class="tdtop"><?php echo $langs->trans("Description"); ?></td>
            <td>
                <?php
                    $dolEditor = new DolEditor('ticket_message', GETPOST('ticket_message', 'restricthtml'), '', 200, 'dolibarr_notes', 'In', false, true, true, 10, 200);
                    $dolEditor->Create();
                ?>
            </td>
        </tr>
    </table>

    <table class="border centpercent">
        <tr class="oddeven">
            <td class="titlefield"><?php echo $langs->trans("Categories"); ?></td>
            <td>
                <?php print $formTicket->selectGroupTickets(GETPOST('ticket_category', 'aZ09'), 'ticket_category', '', 1); ?>
            </td>
        </tr>
    </table>

    <?php if ($permissiontoadd) : ?>
        <div class="center" style="margin-top: 10px;">
            <button type="submit" form="createticketform" class="butAction"><?php echo $langs->trans("CreateTicket"); ?></button>
        </div>
    <?php endif; ?>
</form>

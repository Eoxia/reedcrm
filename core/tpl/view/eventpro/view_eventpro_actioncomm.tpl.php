<?php

?>

<form action="<?php echo $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType . '&tab=' . $currentTab; ?>" method="POST" class="border" id="addeventform">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="add_event">
    <input type="hidden" name="from_id" value="<?php echo $id; ?>">
    <input type="hidden" name="from_type" value="<?php echo $fromType; ?>">
    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">

    <div id="id-container" class="template-pwa">
        <div class="wpeo-grid grid-3">
            <div>
                <label for="socid">
                    <?php echo img_picto('', 'company'); ?>
                    <div class="select2-container"><?php echo $form->select_company($object->thirdparty->id, 'socid', '', 1, 0, 0, [], 0, ''); ?></div>
                </label>
            </div>
            <div>
                <label for="contactid">
                    <?php echo img_picto('', 'contact'); ?>
                    <div class="select2-container"><?php echo $form->selectcontacts($object->thirdparty->id, '', 'contactid', 1, '', '', 0, ''); ?></div>
                </label>
            </div>
            <div>
                <label for="actioncode">
                    <?php //echo img_picto('', 'setting'); ?>
                    <?php echo $formActions->select_type_actions(GETPOSTISSET('actioncode') ? GETPOST('actioncode', 'aZ09') : getDolGlobalString('REEDCRM_EVENT_TYPE_CODE_VALUE'), 'actioncode', 'systemauto', 0, -1, 0, 1); ?>
                </label>
            </div>
        </div>
        <div class="wpeo-grid grid-2">
            <div>
                <label>
                    <?php //echo img_picto('', 'agenda'); ?>
                    <div class="select2-container"><?php echo $form->selectDate(dol_now('tzuser'), 'event_', 1, 1); ?></div>
                </label>
            </div>
            <div>
                <label for="project_id">
                    <?php echo img_picto('', 'project'); ?>
                    <div class="select2-container"><?php echo $formProject->select_projects($object->thirdparty->id, $object->id, 'project_id'); ?></div>
                </label>
            </div>
        </div>

        <?php
            print showEventProTabs($id, $fromType, $currentTab);

            if ($currentTab == 'note') {
                require_once __DIR__ . '/view_eventpro_actioncomm_note.tpl.php';
            }

            if ($currentTab == 'email') {
                // Presend form
                $modelmail    = 'thirdparty';
                $defaulttopic = 'InformationMessage';
                //$diroutput    = $conf->dolisirh->dir_output;
                $trackid      = $object->element . $object->id;
                $action       = 'presend';

                require_once DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';
            }

            if ($currentTab == 'ticket' && isModEnabled('ticket')) {
                require_once __DIR__ . '/view_eventpro_ticket.tpl.php';
            }
        ?>
    </div>
</form>

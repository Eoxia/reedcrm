<?php

?>

<form action="<?php echo $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType . '&tab=' . $currentTab; ?>" method="POST" class="border" id="addeventform">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="add_event">
    <input type="hidden" name="from_id" value="<?php echo $id; ?>">
    <input type="hidden" name="from_type" value="<?php echo $fromType; ?>">
    <input type="hidden" name="tab" value="<?php echo $currentTab; ?>">

    <div id="id-container" class="template-pwa">
        <div id="reedcrm-modal-title-data" style="display:none;"><?= img_picto('', $object->picto, 'class="pictofixedwidth"') ?><?= dol_escape_htmltag($langs->trans('Opportunities')) ?>&nbsp;&nbsp;<?= dol_escape_htmltag($object->ref) ?></div>
        <?php if ($object->element === 'project' && !empty($object->usage_opportunity)) {
            $oppAmount  = (float) $object->opp_amount;
            $oppPercent = (float) $object->opp_percent;
            $imgPath    = dol_buildpath('/custom/reedcrm/img/reedcrm.png', 1);
        ?>
        <div class="reedcrm-header-stats" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; margin-bottom: 4px; vertical-align: middle; font-weight: 600; font-size: 0.95em;">
            <img src="<?= dol_escape_htmltag($imgPath) ?>" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM">
            <span><?= dol_escape_htmltag($langs->trans('Label')) ?> : <strong><?= dol_escape_htmltag($object->title) ?></strong></span>
        </div>
        <br>
        <div class="reedcrm-header-stats" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; margin-bottom: 6px; vertical-align: middle; font-weight: 600; font-size: 0.95em;">
            <img src="<?= dol_escape_htmltag($imgPath) ?>" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM">
            <span class="inline-edit-proj-percent" data-project-id="<?= (int)$object->id ?>" data-val="<?= $oppPercent ?>" style="color: #0f172a; cursor: pointer; border-bottom: 1px dashed #cbd5e0; padding-bottom: 1px; transition: color 0.3s; display: inline-flex; align-items: center; white-space: nowrap; line-height: 1;" title="<?= dol_escape_htmltag($langs->trans('OpportunityProbability')) ?>"><?= price2num($oppPercent, 1) ?> %</span>
            <span style="color: #cbd5e0; margin: 0 6px;">-</span>
            <span class="inline-edit-proj-amount" data-project-id="<?= (int)$object->id ?>" data-val="<?= $oppAmount ?>" style="color: #3b82f6; cursor: pointer; border-bottom: 1px dashed #cbd5e0; padding-bottom: 1px; transition: color 0.3s; display: inline-flex; align-items: center; white-space: nowrap; line-height: 1;" title="<?= dol_escape_htmltag($langs->trans('OpportunityAmount')) ?>"><?= price($oppAmount, 0, $langs, 1, -1, -1, $conf->currency) ?></span>
        </div>
        <?php } ?>
        <?php if (!empty($object->usage_opportunity)) { ?>
        <input type="hidden" name="new_opportunity_amount" value="<?= (float)$object->opp_amount ?>">
        <input type="hidden" name="new_opportunity_percent" value="<?= (float)$object->opp_percent ?>">
        <input type="hidden" name="new_opportunity_status" value="<?= (int)$object->opp_status ?>">
        <?php } ?>
        <div class="wpeo-grid grid-3">
            <div>
                <label for="socid">
                    <?php echo img_picto('', 'company'); ?>
                    <div class="select2-container"><?php echo $form->select_company(!empty($object->thirdparty) ? $object->thirdparty->id : 0, 'socid', '', 1, 0, 0, [], 0, ''); ?></div>
                </label>
            </div>
            <div>
                <div class="reedcrm-contact-field-wrapper">
                    <label for="contactid">
                        <?php echo img_picto('', 'contact'); ?>
                        <div class="select2-container"><?php echo $form->selectcontacts(!empty($object->thirdparty) ? $object->thirdparty->id : 0, '', 'contactid', 1, '', '', 0, ''); ?></div>
                    </label>
                    <button type="button" class="reedcrm-add-contact-btn" title="<?php echo $langs->trans('AddContact'); ?>">
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <div class="reedcrm-add-contact-form" style="display: none;">
                    <div class="wpeo-grid grid-2">
                        <div>
                            <label for="new_contact_lastname"><?php echo $langs->trans('Lastname'); ?></label>
                            <input type="text" id="new_contact_lastname" name="new_contact_lastname" class="maxwidth200" />
                        </div>
                        <div>
                            <label for="new_contact_firstname"><?php echo $langs->trans('Firstname'); ?></label>
                            <input type="text" id="new_contact_firstname" name="new_contact_firstname" class="maxwidth200" />
                        </div>
                    </div>
                    <div class="wpeo-grid grid-2">
                        <div>
                            <label for="new_contact_phone_pro"><?php echo $langs->trans('PhonePro'); ?></label>
                            <input type="text" id="new_contact_phone_pro" name="new_contact_phone_pro" class="maxwidth200" />
                        </div>
                        <div>
                            <label for="new_contact_email"><?php echo $langs->trans('Email'); ?></label>
                            <input type="email" id="new_contact_email" name="new_contact_email" class="maxwidth200" />
                        </div>
                    </div>
                    <div class="reedcrm-add-contact-actions">
                        <button type="button" class="reedcrm-add-contact-submit button"><?php echo $langs->trans('Add'); ?></button>
                        <button type="button" class="reedcrm-add-contact-cancel button button-cancel"><?php echo $langs->trans('Cancel'); ?></button>
                    </div>
                </div>
            </div>
            <div>
                <label for="actioncode">
                    <?php //echo img_picto('', 'setting'); ?>
                    <?php echo $formActions->select_type_actions(GETPOSTISSET('actioncode') ? GETPOST('actioncode', 'aZ09') : getDolGlobalString('REEDCRM_EVENT_TYPE_CODE_VALUE'), 'actioncode', 'systemauto', 0, -1, 0, 1, ''); ?>
                </label>
            </div>
        </div>
        <div class="wpeo-grid grid-2">
            <div>
                <label>
                    <?php //echo img_picto('', 'agenda'); ?>
                    <div class="select2-container" style="display: flex; align-items: center;"><?php echo $form->selectDate(dol_now('tzuser'), 'event_', 1, 1); ?></div>
                </label>
            </div>
            <div>
                <label for="project_id">
                    <?php echo img_picto('', 'project'); ?>
                    <div class="select2-container"><?php echo $formProject->select_projects(!empty($object->thirdparty) ? $object->thirdparty->id : 0, $object->id, 'project_id', 64); ?></div>
                </label>
            </div>
        </div>

        <?php
            print showEventProTabs($id, $fromType, $currentTab);

            if ($currentTab == 'note') {
                require_once __DIR__ . '/view_eventpro_actioncomm_note.tpl.php';
            }

            if ($currentTab == 'email') {
                $originalProjectAddonPdf = getDolGlobalString('PROJECT_ADDON_PDF');
                if ($object->element == 'project' && !empty($originalProjectAddonPdf)) {
                    $conf->global->PROJECT_ADDON_PDF = '';
                }

                $modelmail    = 'thirdparty';
                $defaulttopic = 'InformationMessage';
                if ($object->element == 'project') {
                    $diroutput = $conf->project->multidir_output[$object->entity] . '/' . dol_sanitizeFileName($object->ref);
                } else {
                    $diroutput = '';
                }
                $trackid      = $object->element . $object->id;
                $action       = 'presend';

                require_once DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';

                if ($object->element == 'project' && isset($originalProjectAddonPdf)) {
                    $conf->global->PROJECT_ADDON_PDF = $originalProjectAddonPdf;
                }
            }

            if ($currentTab == 'ticket' && isModEnabled('ticket')) {
                require_once __DIR__ . '/view_eventpro_ticket.tpl.php';
            }
        ?>
    </div>
</form>

<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/actions/reedcrm_project_quickcreation_frontend.tpl.php
 * \ingroup reedcrm
 * \brief   Template page for quick creation project frontend
 */

/**
 * The following vars must be defined :
 * Global   : $conf, $langs
 * Objects  : $extraFields, $form, $project
 * Variable : $permissionToAddProject
 */

// Protection to avoid direct call of template
if (!$permissionToAddProject) {
    exit;
}

require_once __DIR__ . '/../../../../saturne/core/tpl/medias/media_editor_modal.tpl.php'; ?>

<!-- File start-->
<div id="id-top" class="page-header side-nav-vert">
    <div class="col">
        <!-- @todo Logo here -->
    </div>
    <div class="col">
<!--        <div class="subtitle">Custom subtitle</div>-->
        <div class="title"><?php echo $langs->trans('Lead'); ?></div>
    </div>
    <div class="col">
        <?php $backToMap = img_picto('map', 'fontawesome_map-marked-alt_fas_#ffffff');
        print '<a class="nav-element" href="' . dol_buildpath('custom/reedcrm/view/map.php?from_type=project&source=pwa', 1) . '">' . $backToMap . '</a>'; ?>
    </div>
</div>

<div id="id-container" class="page-content">
    <?php print saturne_show_notice('', '', 'error', 'notice-infos', false, true, '', ['Error' => $langs->transnoentities('Error')]); ?>

    <div class="wpeo-grid grid-2 grid-no-responsive">
        <div class="grid-2">
            <!-- Project label -->
            <label for="title"> <!-- @todo class css pour le design des input et textarea -->
                <?php echo img_picto('', 'fontawesome_project-diagram_fas_#6C6AA8'); ?>
                <input type="text" id="title" name="title" placeholder="<?php echo $langs->trans('ProjectLabel'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')); ?>" required>
            </label>
        </div>
        <div class="grid-2">
            <!-- Description -->
            <?php if ($conf->global->REEDCRM_PROJECT_DESCRIPTION_VISIBLE > 0) : ?>
                <label for="description">
                    <?php echo img_picto('', 'fontawesome_comment_fas_#263C5C'); ?>
                    <textarea name="description" id="description" rows="6" placeholder="<?php echo $langs->trans('Description'); ?>"><?php echo dol_escape_htmltag((GETPOSTISSET('description') ? GETPOST('description', 'restricthtml') : '')); ?></textarea>
                </label>
            <?php endif; ?>
        </div>
        <!-- Audio button moved to the bottom -->

        <!-- ExtraFields -->
        <?php if (getDolGlobalInt('REEDCRM_PROJECT_EXTRAFIELDS_VISIBLE')) :
            $extraFields->attributes['projet']['picto']['projectphone']      = 'phone';
            $extraFields->attributes['projet']['picto']['reedcrm_lastname']  = 'fa-user-tie';
            $extraFields->attributes['projet']['picto']['reedcrm_firstname'] = 'fa-user';
            $extraFields->attributes['projet']['picto']['reedcrm_email']     = 'fa-at';

            $positions = $extraFields->attributes['projet']['pos'];
            asort($positions);
            $sortedType = [];
            foreach ($positions as $key => $pos) {
                $sortedType[$key] = $extraFields->attributes['projet']['type'][$key];
            }
            $extraFields->attributes['projet']['type'] = $sortedType; ?>

            <?php foreach ($extraFields->attributes['projet']['type'] as $key => $value) {
            if (strpos($key, 'reedcrm') === false && $key != 'projectphone') {
                continue;
            }

            $inputType = 'text';
            if ($value == 'mail') {
                $inputType = 'email';
            }
            if ($value == 'phone') {
                $inputType = 'tel';
            }

            print '<div>';
            print '<label for="' . $key . '" class="extrafields-content">';
//                print 'img_picto('', $extraFields->attributes['projet']['picto'][$key], 'class="pictofixedwidth"')';
            print '<input type="' . $inputType . '" id="' . $key . '" name="options_' . $key . '" placeholder="' . $langs->trans($extraFields->attributes['projet']['label'][$key]) . '" value="' . dol_escape_htmltag((GETPOSTISSET($key) ? GETPOST($key) : '')) . '">';
            print '</label>';
            print '</div>';
        } ?>
        <?php endif; ?>

        <!-- Media (Audio & Images) Block definition -->
        <?php ob_start(); ?>
        <div class="<?php echo empty($conf->global->PROJECT_USE_OPPORTUNITIES) ? 'grid-2' : ''; ?>" style="display: flex; gap: 15px; align-items: center; <?php echo empty($conf->global->PROJECT_USE_OPPORTUNITIES) ? 'margin-top: 15px;' : 'height: 40px; margin: 0;'; ?>">
            <!-- Audio -->
            <div>
                <button type="button" id="start-recording" class="butAction button-square" style="height: 40px; margin: 0;">
                    <?php echo img_picto('', 'fontawesome_microphone_fas_#ffffff'); ?>
                </button>
            </div>
            
            <!-- Images -->
            <input hidden id="upload-image" type="file" name="userfile[]" capture="environment" accept="image/*">
            <div class="linked-medias project" style="margin: 0;">
                <div class="linked-medias-list" style="display: flex; gap: 10px;">
                    <label for="upload-image" style="margin: 0;">
                        <div class="butAction button-square" style="display: flex; align-items: center; justify-content: center; position: relative; height: 40px; margin: 0;">
                            <input type="hidden" class="modal-options" data-photo-class="project"/>
                            <?php echo img_picto('', 'fontawesome_camera_fas_#ffffff'); ?>
                            <span style="position: absolute; top: 4px; right: 4px; font-size: 0.6em;">
                                <?php echo img_picto('', 'fontawesome_plus-circle_fas_#ffffff', 'class="button-icon"'); ?>
                            </span>
                        </div>
                    </label>
                    <?php print saturne_show_medias_linked('reedcrm', $conf->reedcrm->multidir_output[$conf->entity] . '/project/tmp/0/project_photos', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'project/tmp/0/project_photos', $project, '', 0, 1, 0, 0, '', 1, ['useAi' => 1]); ?>
                </div>
            </div>
            
            <!-- Recording Indicator -->
            <div id="recording-indicator" class="blinking" style="display: none; align-self: center;"><?php echo $langs->trans('RecordingInProgress'); ?></div>
        </div>
        <?php $mediaBlockHtml = ob_get_clean(); ?>

        <!-- Opportunity option -->
        <?php if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) : ?>
            <div class="grid-2" style="display: flex; gap: 15px; margin-top: 15px; width: 100%;">
                <!-- Media moved to the left column -->
                <div style="flex: 1; display: flex; align-items: center; justify-content: flex-start; height: 100%; margin: 0; padding: 0;">
                    <?php echo $mediaBlockHtml; ?>
                </div>
                
                <?php if ($conf->global->REEDCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) : ?>
                    <div style="flex: 1; display: flex; align-items: center; justify-content: flex-start; height: 100%; margin: 0; padding: 0;">
                        <div style="border: 1px solid #ced4da; border-radius: 4px; display: flex; align-items: center; padding: 0 10px; width: 100%; height: 40px; box-sizing: border-box; background: #fff;">
                            <span style="font-weight: bold; margin-right: 8px; font-size: 1.1em; line-height: 1;">&euro;</span>
                            <input type="text" inputmode="decimal" name="opp_amount" id="opp_amount" placeholder="<?php echo $langs->trans('OpportunityAmount'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('opp_amount') ? GETPOST('opp_amount', 'int') : '')); ?>" style="border: 0px !important; box-shadow: none !important; outline: none !important; background: transparent !important; width: 100%; height: 100%; padding: 0; margin: 0; flex: 1;">
                        </div>
                    </div>
                <?php else : ?>
                    <div style="flex: 1; display: flex; align-items: center; justify-content: flex-start; height: 100%; margin: 0; padding: 0;"></div>
                <?php endif; ?>
            </div>

            <div class="grid-2">
                <div class="opp-percent" style="display: flex; align-items: center; width: 100%; margin-top: 15px;">
                    <?php echo img_picto('', 'fontawesome_fa-frown-open_fas_#c62828_2em', 'class="percent-image" style="margin-right: 15px;"'); ?>
                    
                    <div style="position: relative; flex-grow: 1; display: flex; align-items: center; height: 40px;" oninput="this.style.setProperty('--val', event.target.value)">
                        <input type="range" class="range" name="opp_percent" id="opp_percent" min="0" max="100" step="10" value="0" style="width: 100%; margin: 0; position: relative; z-index: 1;">
                        <span class="opp_percent-value" style="position: absolute; left: calc(var(--val, 0) * 1% + (19px - var(--val, 0) * 0.38px)); top: 50%; transform: translate(-50%, -50%); pointer-events: none; z-index: 2; font-weight: bold; font-family: 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 0.8em; color: #1a202c; text-align: center; white-space: nowrap; background: #ffffff; width: 38px; height: 38px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.25); border: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: center;">0 %</span>
                    </div>

                    <?php echo img_picto('', 'fontawesome_fa-laugh-beam_fas_#388e3c_2em', 'class="percent-image" style="margin-left: 15px;"'); ?>
                </div>
            </div>
        <?php else : ?>
            <?php echo $mediaBlockHtml; ?>
        <?php endif; ?>



        <!-- GPS -->
        <input type="hidden" id="latitude"  name="latitude" value="">
        <input type="hidden" id="longitude" name="longitude" value="">
        <input type="hidden" id="geolocation-error" name="geolocation-error" value="">

    </div>
</div>

<div id="id-bot" class="page-footer center">
    <button type="submit" class="button button-add">
        <?php echo $langs->trans('Save'); ?>
    </button>
</div>
<?php

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
        <div>
            <!-- Audio -->
            <button type="button" id="start-recording" class="butAction button-square">
                <?php echo img_picto('', 'fontawesome_microphone_fas_#ffffff'); ?>
            </button>
        </div>
        <div>
            <div id="recording-indicator" class="blinking"><?php echo  $langs->trans('RecordingInProgress'); ?></div>
        </div>

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

        <!-- Opportunity option -->
        <?php if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) : ?>
            <!-- Opportunity percent -->
            <div class="grid-2">
                <div for="opp_percent">
                    <div class="opp-percent-label">
                        <span class="label"><?php echo $langs->trans('OpportunityProbability'); ?></span>
                        <span class="opp_percent-value">0 %</span>
                    </div>
                    <div class="opp-percent">
                        <?php echo img_picto('', 'fontawesome_fa-frown-open_fas_#c62828_2em', 'class="percent-image"'); ?>
                        <input type="range" class="range" name="opp_percent" id="opp_percent" min="0" max="100" step="10" value="0">
                        <?php echo img_picto('', 'fontawesome_fa-laugh-beam_fas_#388e3c_2em', 'class="percent-image"'); ?>
                    </div>
                </div>
            </div>
            <!-- Opportunity amount -->
            <?php if ($conf->global->REEDCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) : ?>
                <div class="grid-2">
                    <label for="opp_amount">
                        <?php echo img_picto('', 'fontawesome_euro-sign_fas_#000000'); ?>
                        <input type="number" name="opp_amount" id="opp_amount" min="0" placeholder="<?php echo $langs->trans('OpportunityAmount'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('opp_amount') ? GETPOST('opp_amount', 'int') : '')); ?>">
                    </label>
                </div>
            <?php endif;
        endif; ?>

        <!-- Images -->
        <div class="grid-2">
            <input hidden multiple id="upload-image" type="file" name="userfile[]" capture="environment" accept="image/*">
            <div class="linked-medias project">
                <div class="linked-medias-list">
                    <label for="upload-image">
                        <div class="butAction button-square">
                            <input type="hidden" class="modal-options" data-photo-class="project"/>
                            <?php echo img_picto('', 'fontawesome_camera_fas_#ffffff'); ?>
                            <?php echo img_picto('', 'fontawesome_plus-circle_fas_#ffffff', 'class="button-icon"'); ?>
                        </div>
                    </label>
                    <?php print saturne_show_medias_linked('reedcrm', $conf->reedcrm->multidir_output[$conf->entity] . '/project/tmp/0/project_photos', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'project/tmp/0/project_photos', $project, '', 0); ?>
                </div>
            </div>
        </div>

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

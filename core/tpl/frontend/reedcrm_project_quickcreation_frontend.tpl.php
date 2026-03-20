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

        <!-- Media (Audio & Images) Block definition is now in a separate template -->

        <!-- Opportunity option -->
        <?php if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) : ?>
            <div class="grid-2 reedcrm-opp-wrapper">
                <!-- Media moved to the left column -->
                <div class="reedcrm-opp-col">
                    <?php include __DIR__ . '/reedcrm_project_quickcreation_media_frontend.tpl.php'; ?>
                </div>
                
                <?php if ($conf->global->REEDCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) : ?>
                    <div class="reedcrm-opp-col">
                        <div class="reedcrm-opp-amount-input-group">
                            <span>&euro;</span>
                            <input type="text" inputmode="decimal" name="opp_amount" id="opp_amount" placeholder="<?php echo $langs->trans('OpportunityAmount'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('opp_amount') ? GETPOST('opp_amount', 'int') : '')); ?>">
                        </div>
                    </div>
                <?php else : ?>
                    <div class="reedcrm-opp-col"></div>
                <?php endif; ?>
            </div>

            <div class="grid-2">
                <div class="opp-percent reedcrm-opp-percent-wrapper">
                    <?php echo img_picto('', 'fontawesome_fa-frown-open_fas_#c62828_2em', 'class="percent-image left"'); ?>
                    
                    <div class="range-container" oninput="this.style.setProperty('--val', event.target.value)">
                        <input type="range" class="range" name="opp_percent" id="opp_percent" min="0" max="100" step="10" value="0">
                        <span class="opp_percent-value">0 %</span>
                    </div>

                    <?php echo img_picto('', 'fontawesome_fa-laugh-beam_fas_#388e3c_2em', 'class="percent-image right"'); ?>
                </div>
            </div>
        <?php else : ?>
            <?php include __DIR__ . '/reedcrm_project_quickcreation_media_frontend.tpl.php'; ?>
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

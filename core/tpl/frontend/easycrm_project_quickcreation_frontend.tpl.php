<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/actions/easycrm_project_quickcreation_frontend.tpl.php
 * \ingroup easycrm
 * \brief   Template page for quick creation project frontend
 */

/**
 * The following vars must be defined :
 * Global   : $conf, $langs
 * Objects  : $form, $project
 * Variable : $permissionToAddProject
 */

// Protection to avoid direct call of template
if (!$permissionToAddProject) {
    exit;
}

require_once __DIR__ . '/easycrm_media_editor_frontend.tpl.php'; ?>

<!-- File start-->
<div class="project-container">
    <div class="page-header">
        <div class="page-title"><?php echo $langs->trans('Lead'); ?></div> <?php
        $backToMap = img_picto('map', 'fontawesome_map-marked-alt_fas_#ffffff') . ' ' . img_picto('back', 'fontawesome_arrow-right_fas_#ffffff');
        print '<a class="wpeo-button" href="' . dol_buildpath('custom/easycrm/view/map.php?from_type=project&source=pwa', 1) . '">' . $backToMap . '</a>'; ?>
    </div>

    <div class="page-content">
        <!-- Project label -->
        <label for="title">
            <?php echo $langs->trans('ProjectLabel'); ?>
            <input type="text" id="title" name="title" placeholder="<?php echo $langs->trans('ProjectLabel'); ?>" value="<?php echo dol_escape_htmltag((GETPOSTISSET('title') ? GETPOST('title') : '')); ?>" required>
        </label>

        <!-- Description -->
        <?php if ($conf->global->EASYCRM_PROJECT_DESCRIPTION_VISIBLE > 0) : ?>
            <label for="description">
                <?php echo $langs->trans('Description'); ?>
                <textarea name="description" id="description" rows="6"><?php echo dol_escape_htmltag((GETPOSTISSET('description') ? GETPOST('description', 'restricthtml') : '')); ?></textarea>
            </label>
        <?php endif; ?>

        <!-- Opportunity option -->
        <?php if (!empty($conf->global->PROJECT_USE_OPPORTUNITIES)) : ?>
            <!-- Opportunity percent -->
            <label for="opp_percent">
                <div class="opp-percent-label">
                    <span class="label"><?php echo $langs->trans('OpportunityProbability'); ?></span>
                    <span class="opp_percent-value">0 %</span>
                </div>
                <div class="opp-percent">
                    <?php echo img_picto('', 'fontawesome_fa-frown-open_fas_#c62828_2em', 'class="percent-image"'); ?>
                    <input type="range" class="range" name="opp_percent" id="opp_percent" min="0" max="100" step="10" value="0">
                    <?php echo img_picto('', 'fontawesome_fa-laugh-beam_fas_#388e3c_2em', 'class="percent-image"'); ?>
                </div>
            </label>
            <!-- Opportunity amount -->
            <?php if ($conf->global->EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE > 0) : ?>
                <label for="opp_amount">
                    <?php echo $langs->trans('OpportunityAmount'); ?>
                    <input type="number" name="opp_amount" id="opp_amount" min="0" value="<?php echo dol_escape_htmltag((GETPOSTISSET('opp_amount') ? GETPOST('opp_amount', 'int') : '')); ?>">
                </label>
            <?php endif;
        endif; ?>

        <!-- Categories -->
<!--        --><?php //if (isModEnabled('categorie') && $conf->global->EASYCRM_PROJECT_CATEGORIES_VISIBLE > 0) : ?>
<!--            <label for="categories-project">-->
<!--                --><?php //echo $langs->trans('Categories'); ?>
<!--                --><?php //$cateArbo = $form->select_all_categories(Categorie::TYPE_PROJECT, '', 'parent', 64, 0, 1); ?>
<!--                --><?php //print img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('categories_project', $cateArbo, GETPOST('categories_project', 'array'), '', 0, 'quatrevingtpercent widthcentpercentminusx'); ?>
<!--            </label>-->
<!--        --><?php //endif; ?>

        <!-- Images -->
        <input hidden multiple id="upload-image" type="file" name="userfile[]" capture="environment" accept="image/*">
        <label class="linked-medias project" for="upload-image">
            <div class="linked-medias-list">
                <div class="wpeo-button button-square-50">
                    <input type="hidden" class="modal-options" data-photo-class="project"/>
                    <i class="fas fa-camera"></i><i class="fas fa-plus-circle button-add"></i>
                </div>
                <?php print saturne_show_medias_linked('easycrm', $conf->easycrm->multidir_output[$conf->entity] . '/project/tmp/0/project_photos', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'project/tmp/0/project_photos', $project, '', 0); ?>
            </div>
        </label>

        <!-- GPS -->
        <input type="hidden" id="latitude"  name="latitude" value="">
        <input type="hidden" id="longitude" name="longitude" value="">
        <input type="hidden" id="geolocation-error" name="geolocation-error" value="">
    </div>

    <div class="page-footer">
        <button type="submit"><?php echo $langs->trans('Save'); ?></button>
    </div>
</div>
<?php

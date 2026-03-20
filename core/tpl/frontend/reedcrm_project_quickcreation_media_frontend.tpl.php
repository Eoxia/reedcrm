<?php
/**
 * Media (Audio & Images) Block definition
 */
?>
<div class="reedcrm-media-container <?php echo empty($conf->global->PROJECT_USE_OPPORTUNITIES) ? 'grid-2 no-opp' : 'with-opp'; ?>">
    <!-- Audio -->
    <div>
        <button type="button" id="start-recording" class="butAction button-square reedcrm-btn-square-media">
            <?php echo img_picto('', 'fontawesome_microphone_fas_#ffffff'); ?>
        </button>
    </div>
    
    <!-- Images -->
    <input hidden id="upload-image" type="file" name="userfile[]" capture="environment" accept="image/*">
    <div class="linked-medias project reedcrm-linked-medias-wrapper">
        <div class="linked-medias-list">
            <label for="upload-image">
                <div class="butAction button-square reedcrm-btn-square-media">
                    <input type="hidden" class="modal-options" data-photo-class="project"/>
                    <?php echo img_picto('', 'fontawesome_camera_fas_#ffffff'); ?>
                    <span class="reedcrm-camera-icon-badge">
                        <?php echo img_picto('', 'fontawesome_plus-circle_fas_#ffffff', 'class="button-icon"'); ?>
                    </span>
                </div>
            </label>
            <?php print saturne_show_medias_linked('reedcrm', $conf->reedcrm->multidir_output[$conf->entity] . '/project/tmp/0/project_photos', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'project/tmp/0/project_photos', $project, '', 0, 1, 0, 0, '', 1, ['useAi' => 1]); ?>
        </div>
    </div>
    
    <!-- Recording Indicator -->
    <div id="recording-indicator" class="blinking recording-indicator"><?php echo $langs->trans('RecordingInProgress'); ?></div>
</div>

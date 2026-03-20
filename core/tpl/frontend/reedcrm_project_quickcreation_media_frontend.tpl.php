<div class="linked-medias project" style="display: contents;">
    <!-- Memo vocal -->
    <div class="action-col">
        <div class="file-paste-zone action-box">
            <button type="button" id="start-recording" class="btn-secondary" style="margin:auto; border-radius: 50%; width: 40px; height: 40px; padding: 0;">
                <?php echo img_picto('', 'fontawesome_microphone_fas_#ffffff'); ?>
            </button>
        </div>
        <!-- Recording Indicator -->
        <div id="recording-indicator" class="blinking recording-indicator" style="display:none; font-size:11px; margin-top:5px; color: #e74c3c; text-align: center; width: 100%;"><?php echo $langs->trans('RecordingInProgress'); ?></div>
    </div>
    
    <!-- Photos -->
    <div class="action-col">
        <input hidden id="upload-image" type="file" name="userfile[]" capture="environment" accept="image/*">
        <label for="upload-image" style="margin:0; height:100%; cursor:pointer; display:block;">
            <div class="btn-orange action-box" style="margin:0; height: 60px;">
                <input type="hidden" class="modal-options" data-photo-class="project"/>
                <?php echo img_picto('', 'fontawesome_camera_fas_#ffffff'); ?>
            </div>
        </label>
    </div>

    <!-- Linked Medias rendering -->
    <div class="action-col" style="justify-content: flex-end;">
        <div class="reedcrm-linked-medias-wrapper">
            <div class="linked-medias-list action-box" style="border: none; background: transparent;">
                <?php print saturne_show_medias_linked('reedcrm', $conf->reedcrm->multidir_output[$conf->entity] . '/project/tmp/0/project_photos', 'small', '', 0, 0, 0, 50, 50, 0, 0, 0, 'project/tmp/0/project_photos', $project, '', 0, 1, 0, 0, '', 1, ['useAi' => 1]); ?>
            </div>
        </div>
    </div>
</div>

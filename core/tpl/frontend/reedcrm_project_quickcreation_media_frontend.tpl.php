<?php
/* Copyright (C) 2023-2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/reedcrm_project_quickcreation_media_frontend.tpl.php
 * \ingroup reedcrm
 * \brief   Template page for quick creation project media frontend
 */

require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
$uploadContext = 'reedcrm_quickcreation_' . $user->id;
$subDir = 'tmp/' . saturne_get_upload_token($uploadContext);
?>

<div class="linked-medias project" id="master-media-row-container" style="width: 100%; display: flex; flex-wrap: wrap; gap: 5px; align-items: center; box-sizing: border-box; min-height: 48px;">
    
    <style>
    #master-media-row-container .linked-medias.medias        { width: auto !important; display: inline-flex; align-items: center; flex-direction: row; }
    #master-media-row-container .saturne-media-upload-block  { display: inline-flex; align-items: center; flex-direction: row; gap: 6px; }
    #master-media-row-container .saturne-media-gallery       { display: inline-flex; align-items: center; }
    #master-media-row-container .saturne-audio-controls      { display: inline-flex; align-items: center; flex-direction: row; gap: 6px; }
    #master-media-row-container .saturne-play-recording-wrapper { display: inline-flex; align-items: center; }
    #master-media-row-container .open-media-editor-as-gallery {
        position: relative; display: inline-flex; align-items: center; justify-content: center;
        width: 44px; height: 44px; border-radius: 12px;
        overflow: visible; cursor: pointer; flex-shrink: 0; align-self: center;
    }
    #master-media-row-container .open-media-editor-as-gallery img {
        width: 44px; height: 44px; object-fit: cover; border-radius: 12px; display: block;
    }
    #master-media-row-container .open-media-editor-as-gallery .saturne-media-count-badge {
        position: absolute; top: -6px; right: -6px; z-index: 2;
    }
    </style>
    <div style="flex: 1; min-width: 0; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <?php print saturne_render_media_block('project', $subDir, 'quickcreation_', '', ['show_photo' => true, 'show_audio' => true]); ?>
    </div>

    <div class="media-logical-block block-actions" style="margin-left: auto;">
        <!-- Submit Button -->
        <button type="submit" class="btn-submit-purple" style="border: none; cursor:pointer; margin:0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 12px; width: 48px; height: 48px; padding: 0; display:flex; justify-content:center; align-items:center; transition: all 0.2s ease; background-color: #9b59b6;">
            <i class="fas fa-save" style="font-size: 24px; color: #fff;"></i>
        </button>
    </div>
    
</div>

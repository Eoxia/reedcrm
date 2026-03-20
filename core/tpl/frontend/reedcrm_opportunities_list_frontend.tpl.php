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
 * \file    core/tpl/frontend/reedcrm_opportunities_list_frontend.tpl.php
 * \ingroup reedcrm
 * \brief   Template page for quick creation project frontend list
 */

/**
 * The following vars must be defined :
 * Global   : $conf, $langs, $db
 * Variable : $latestProjects
 */

$listTitle = $langs->trans('LatestCreatedOpportunities');

print '<div class="page-content" style="margin-top: 30px; padding: 0 15px;">';
print '<div class="title" style="text-transform: uppercase; color: #5a7b97; font-size: 1.1em; font-weight: bold; margin-bottom: 15px;">' . $listTitle . '</div>';



foreach ($latestProjects as $project) {

    $ref       = $project->ref;
    $title     = $project->title;
    $lastname  = $project->array_options['options_reedcrm_lastname'] ?? '';
    $firstname = $project->array_options['options_reedcrm_firstname'] ?? '';
    $phone     = $project->array_options['options_projectphone'] ?? '';
    $email     = $project->array_options['options_reedcrm_email'] ?? '';
    
    // Creation date
    $valDate = !empty($project->date_c) ? $project->date_c : (!empty($project->datec) ? $project->datec : (!empty($project->tms) ? $project->tms : null));
    $creationDate = $valDate ? dol_print_date($valDate, 'day') : '';
    
    // Creator initials
    $userId = !empty($project->user_author_id) ? $project->user_author_id : (!empty($project->fk_user_creat) ? $project->fk_user_creat : null);
    $userInitials = '';
    if (!empty($userId)) {
        $author = new User($db);
        $author->fetch($userId);
        $userInitials = trim(strtoupper(substr($author->firstname, 0, 1) . substr($author->lastname, 0, 1)));
        if (strlen($userInitials) < 2) {
            $fullName = trim($author->firstname . $author->lastname);
            if (strlen($fullName) >= 2) {
                $userInitials = strtoupper(substr($fullName, 0, 2));
            } elseif (empty($userInitials)) {
                $userInitials = strtoupper(substr($author->login, 0, 2));
            }
        }
    }

    // Audio payload
    $projectDir = $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($project->ref);
    $audioFiles = dol_dir_list($projectDir, 'files', 0, '\.(mp3|ogg|wav|m4a|aac|webm|opus)$', null, 'date', SORT_DESC);
    $audioPlayerHtml = '';
    if (!empty($audioFiles)) {
        $lastAudio = $audioFiles[0];
        $fileUrl = DOL_URL_ROOT . '/document.php?modulepart=projet&file=' . urlencode(dol_sanitizeFileName($project->ref) . '/' . $lastAudio['name']);
        $audioPlayerHtml = '<div style="margin-left: 8px;"><audio class="minimal-audio" controls controlslist="nodownload noplaybackrate" preload="metadata" style="height: 28px; width: 150px; outline: none; border-radius: 20px;"><source src="' . dol_escape_htmltag($fileUrl) . '" type="audio/wav"></audio></div>';
    }

    // Probability and amount
    $percent   = $project->opp_percent ? $project->opp_percent . ' %' : '0.00 %';
    $amount    = $project->opp_amount ? price($project->opp_amount, 0, '', 11, -1, -1, 'auto') : '0 €';
    
    $url       = DOL_URL_ROOT . '/projet/card.php?id=' . $project->id;
    
    // Ensure some styling for the cards to match image
    print '<div class="card" style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin-bottom: 10px; background-color: #f8fbff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
    
    // --- ROW 1: Meta, Initials, Audio & Amounts ---
    print '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 10px;">';
    
        // Top Left
        print '<div style="display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">';
            print '<div style="color: #004b87; font-weight: 600; font-size: 1.1em;">' . $ref . '</div>';
            
            print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;">&bull;</span>';
            print '<div style="font-size: 0.85em; color: #718096;"><i class="far fa-calendar-alt" style="margin-right: 4px;"></i>' . $creationDate . '</div>';
            
            if (!empty($userInitials)) {
                print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;">&bull;</span>';
                print '<div title="' . dol_escape_htmltag($author->getFullName($langs)) . '" style="font-size: 0.7em; color: #fff; background: #9b59b6; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; letter-spacing: 0.5px;">' . $userInitials . '</div>';
            }
            
            if (!empty($audioPlayerHtml)) {
                print '<div style="margin-left: 4px;">' . $audioPlayerHtml . '</div>';
            } else {
                print '<div class="inline-audio-recorder" data-project-id="' . $project->id . '" style="display: flex; gap: 4px; padding: 2px 6px; background: #f1f3f4; border-radius: 20px; align-items: center; margin-left: 2px;">';
                print '<button type="button" class="btn-inline-record" style="width: 24px; height: 24px; border-radius: 6px; border: none; background: #7b68ee; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"><i class="fas fa-microphone" style="font-size: 11px;"></i></button>';
                print '<div style="position: relative; display: flex;">';
                print '<button type="button" class="btn-inline-play" disabled style="width: 24px; height: 24px; border-radius: 6px; border: none; background: #cbd5e1; color: white; cursor: not-allowed; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"><i class="fas fa-play" style="font-size: 11px;"></i></button>';
                print '<button type="button" class="btn-inline-delete" style="display: none; position: absolute; top: -5px; right: -5px; width: 14px; height: 14px; border-radius: 50%; background-color: #e74c3c; color: white; border: none; font-size: 8px; cursor: pointer; justify-content: center; align-items: center; z-index: 10; padding: 0; line-height: 1;"><i class="fas fa-times"></i></button>';
                print '</div>';
                print '<button type="button" class="btn-inline-save" disabled style="width: 24px; height: 24px; border-radius: 6px; border: none; background: #9b59b6; color: white; cursor: not-allowed; opacity: 0.5; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"><i class="fas fa-save" style="font-size: 11px;"></i></button>';
                print '</div>';
            }
        print '</div>';
        
        // Top Right
        print '<div style="display: flex; align-items: center; font-weight: 600; font-size: 0.95em;">';
            print '<span style="color: #0f172a;">' . $percent . '</span>';
            print '<span style="color: #cbd5e0; margin: 0 6px;">-</span>';
            print '<span style="color: #3b82f6;">' . $amount . '</span>';
        print '</div>';
        
    print '</div>';
    
    // --- ROW 2: Title ---
    if (!empty($title)) {
        $descParts = [];
        if (!empty($project->description)) $descParts[] = trim(dol_string_nohtmltag($project->description, 1));
        if (!empty($project->note_public)) $descParts[] = trim(dol_string_nohtmltag($project->note_public, 1));
        if (!empty($project->note_private)) $descParts[] = trim(dol_string_nohtmltag($project->note_private, 1));

        $descClean = !empty($descParts) ? implode(" \n---\n ", $descParts) : '(Aucune description / note)';
        $descAttr = ' data-tooltip="' . dol_escape_htmltag($descClean) . '"';
        
        print '<div class="fast-css-tooltip" ' . $descAttr . ' style="color: #4a5568; margin-bottom: 8px; font-size: 0.95em; display: flex; align-items: center; position: relative; cursor: pointer;">';
            print '<i class="fas fa-project-diagram" style="color: #64748b; margin-right: 6px;"></i>';
            print '<span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">' . dol_escape_htmltag($title) . '</span>';
        print '</div>';
    }
    
    // --- ROW 3: Contact details & Action Button ---
    print '<div style="display: flex; justify-content: space-between; align-items: center;">';

        // Bottom Left: Contact Info
        print '<div style="color: #718096; font-size: 0.9em; flex: 1;">';
            $contactName = trim($firstname . ' ' . $lastname);
            if (!empty($contactName)) {
                print '<div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">';
                    print '<i class="fas fa-address-book" style="color: #64748b; font-size: 1.1em;"></i>';
                    print '<span style="font-weight: 500;">' . dol_escape_htmltag($contactName) . '</span>';
                    
                    $poste = $project->array_options['options_reedcrm_poste'] ?? '';
                    if (!empty($poste)) {
                        print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;">&bull;</span>';
                        print '<span>' . dol_escape_htmltag($poste) . '</span>';
                    }
                    
                    if (!empty($phone)) {
                        print '<span style="color: #cbd5e0; font-size: 0.8em; margin-left: 8px;"></span>';
                        print '<span>' . dol_escape_htmltag($phone) . '</span>';
                    }
                    if (!empty($email)) {
                        print '<span style="color: #4a5568; margin-left: 8px;">' . dol_escape_htmltag($email) . '</span>';
                    }
                print '</div>';
            }
        print '</div>';
        
        // Bottom Right: Open link button
        print '<div style="display: flex; align-items: center; margin-left: 10px;">';
            print '<div style="width: 6px; height: 6px; background-color: #2ecc71; border-radius: 50%; margin-right: 6px;"></div>';
            print '<a href="' . $url . '" target="_blank" style="color: #6b7280; font-size: 1.3em; line-height: 1; transition: color 0.2s ease;">';
            print '<i class="fas fa-external-link-square-alt"></i>';
            print '</a>';
        print '</div>';
        
    print '</div>'; // End ROW 3
    print '</div>'; // End Card
}

print '</div>';
?>
<style>
.fast-css-tooltip::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15, 23, 42, 0.95);
    color: #f8fafc;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.85em;
    font-weight: 400;
    white-space: pre-wrap;
    width: max-content;
    max-width: 320px;
    z-index: 1000;
    pointer-events: none;
    line-height: 1.4;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    opacity: 0;
    transition: opacity 0.15s ease-in-out;
    visibility: hidden;
}
.fast-css-tooltip:hover::after {
    opacity: 1;
    visibility: visible;
}

@keyframes listPlayingPulseAnim {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(123, 104, 238, 0.7); background-color: #7b68ee; color: white; }
    50% { transform: scale(1.1); box-shadow: 0 0 0 8px rgba(123, 104, 238, 0); background-color: #6a5acd; color: white; }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(123, 104, 238, 0); background-color: #7b68ee; color: white; }
}
.list-playing-pulse-active {
    animation: listPlayingPulseAnim 1.5s infinite !important;
    background-color: #7b68ee !important;
    color: white !important;
    transform-origin: center;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    $('.inline-audio-recorder').each(function() {
        const container = $(this);
        const btnRecord = container.find('.btn-inline-record');
        const btnPlay = container.find('.btn-inline-play');
        const btnDelete = container.find('.btn-inline-delete');
        const btnSave = container.find('.btn-inline-save');
        const projectId = container.data('project-id');
        
        let mediaRecorder = null;
        let audioChunks = [];
        let audioBlob = null;
        let localAudioUrl = null;
        let player = null;
        
        btnRecord.on('click', async function() {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
                btnRecord.find('i').removeClass('fa-stop').addClass('fa-microphone');
                btnRecord.removeClass('recording-pulse-active');
            } else {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({audio: true});
                    mediaRecorder = new MediaRecorder(stream);
                    audioChunks = [];
                    
                    mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
                    
                    mediaRecorder.onstop = function() {
                        audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                        localAudioUrl = URL.createObjectURL(audioBlob);
                        
                        btnPlay.prop('disabled', false).css({'background-color': '#cbd5e1', 'cursor': 'pointer'});
                        btnSave.prop('disabled', false).css({'opacity': '1', 'cursor': 'pointer'});
                        btnDelete.css('display', 'flex');
                    };
                    
                    mediaRecorder.start();
                    btnRecord.find('i').removeClass('fa-microphone').addClass('fa-stop');
                    btnRecord.addClass('recording-pulse-active');
                    
                    btnPlay.prop('disabled', true).css({'background-color': '#cbd5e1', 'cursor': 'not-allowed'});
                    btnSave.prop('disabled', true).css({'opacity': '0.5', 'cursor': 'not-allowed'});
                    btnDelete.css('display', 'none');
                    
                } catch(e) {
                    console.error("Microphone access denied", e);
                }
            }
        });
        
        btnDelete.on('click', function() {
            if (player) {
                player.pause();
                player.currentTime = 0;
            }
            audioChunks = [];
            audioBlob = null;
            if (localAudioUrl) { URL.revokeObjectURL(localAudioUrl); localAudioUrl = null; }
            
            btnPlay.prop('disabled', true).css({'background-color': '#cbd5e1', 'cursor': 'not-allowed'});
            btnPlay.removeClass('list-playing-pulse-active');
            btnPlay.find('i').removeClass('fa-stop').addClass('fa-play');
            
            btnSave.prop('disabled', true).css({'opacity': '0.5', 'cursor': 'not-allowed'});
            btnDelete.css('display', 'none');
        });
        
        btnPlay.on('click', function() {
            if (!localAudioUrl) return;
            
            if (player && !player.paused) {
                player.pause();
                player.currentTime = 0;
                btnPlay.removeClass('list-playing-pulse-active');
                btnPlay.find('i').removeClass('fa-stop').addClass('fa-play');
                return;
            }
            if (player) {
                player.pause();
                player.currentTime = 0;
            }
            player = new Audio(localAudioUrl);
            player.onended = function() {
                btnPlay.removeClass('list-playing-pulse-active');
                btnPlay.find('i').removeClass('fa-stop').addClass('fa-play');
            };
            btnPlay.addClass('list-playing-pulse-active');
            btnPlay.css('background-color', ''); // Strip inline color so animation applies natively
            btnPlay.find('i').removeClass('fa-play').addClass('fa-stop');
            player.play();
        });
        
        btnSave.on('click', function() {
            if (!audioBlob) return;
            
            btnSave.html('<i class="fas fa-spinner fa-spin" style="font-size:11px;"></i>');
            
            const formData = new FormData();
            formData.append('audio', audioBlob, 'note_vocale.wav');
            formData.append('projectid', projectId);
            
            let querySeparator = document.URL.indexOf('?') > -1 ? '&' : '?';
            let token = document.querySelector('input[name="token"]') ? document.querySelector('input[name="token"]').value : '';
            
            $.ajax({
                url: document.URL.split('?')[0] + '?action=add_audio_existing&token=' + token,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function() {
                    window.location.reload();
                },
                error: function() {
                    btnSave.html('<i class="fas fa-times" style="font-size:11px;"></i>').css('background-color', '#e74c3c');
                }
            });
        });
    });
});
</script>
<?php

<div class="linked-medias project" id="photo-thumbnail-container" style="position: relative; width: 100%; display: flex; flex-wrap: wrap; gap: 15px; align-items: center; padding-right: 130px; box-sizing: border-box; min-height: 48px;">
    
    <!-- LEFT CLUSTER ITEMS -->
    
    <!-- Mic -->
    <button type="button" id="start-recording" class="btn-secondary" style="order: 1; border: none; cursor:pointer; margin:0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 12px; width: 48px; height: 48px; padding: 0; display:flex; justify-content:center; align-items:center; transition: all 0.2s ease;">
        <i class="fas fa-microphone" style="font-size: 22px; color: #fff;"></i>
    </button>
    
    <!-- Play -->
    <div style="order: 2; position: relative;">
        <button type="button" id="play-recording" class="btn-secondary" disabled style="border: none; cursor:not-allowed; margin:0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 12px; width: 48px; height: 48px; padding: 0; display:flex; justify-content:center; align-items:center; transition: all 0.2s ease; background-color: #cbd5e1;">
            <i class="fas fa-play" style="font-size: 22px; color: #fff;"></i>
        </button>
        <button type="button" id="delete-recording" style="display:none; position: absolute; top: -6px; right: -6px; width: 20px; height: 20px; border-radius: 50%; background-color: #e74c3c; color: white; border: none; font-size: 11px; cursor: pointer; justify-content: center; align-items: center; z-index: 10; padding: 0; line-height: 1;">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <!-- Camera -->
    <label for="upload-photo" id="label-upload-photo" class="btn-orange" style="order: 3; cursor:pointer; display:flex; justify-content:center; align-items:center; width: 48px; height: 48px; margin: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 12px; transition: all 0.2s ease;">
        <i class="fas fa-camera" style="font-size: 22px; color: #fff;"></i>
        <input type="file" id="upload-photo" name="userfile[]" accept="image/*" capture="environment" multiple style="display: none;">
    </label>
    
    <!-- Gallery (Dotted Border) -->
    <label for="upload-photo-gallery" id="gallery-add-btn" class="add-thumbnail-btn" style="order: 99; width: 44px; height: 44px; border: 2px dashed #94a3b8; border-radius: 12px; display: flex; justify-content: center; align-items: center; color: #94a3b8; cursor: pointer; transition: all 0.2s ease; margin: 0;">
        <i class="fas fa-image" style="font-size: 22px;"></i>
        <input type="file" id="upload-photo-gallery" class="file-upload-input" name="userfile[]" accept="image/*" multiple style="display: none;">
    </label>
    
    <div id="recording-indicator" class="blinking recording-indicator" style="order: 100; display:none; font-size:11px; margin-left: 5px; color: #e74c3c; width: 100%;"><?php echo $langs->trans('RecordingInProgress'); ?></div>

    <!-- RIGHT CLUSTER (Absolute) -->
    <div style="position: absolute; right: 0; top: 0; display: flex; align-items: center; gap: 15px;">
            <!-- Upload File -->
            <label for="upload-media" style="background-color: #9ca3af; cursor:pointer; display:flex; justify-content:center; align-items:center; width: 48px; height: 48px; margin: 0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 12px; transition: all 0.2s ease;">
                <i class="fas fa-upload" style="font-size: 22px; color: #fff;"></i>
                <input type="file" id="upload-media" class="file-upload-input" name="userfile[]" accept="*/*" multiple style="display: none;">
            </label>
            
            <!-- Submit Button -->
            <button type="submit" class="btn-submit-purple" style="border: none; cursor:pointer; margin:0; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border-radius: 12px; width: 48px; height: 48px; padding: 0; display:flex; justify-content:center; align-items:center; transition: all 0.2s ease; background-color: #9b59b6;">
                <i class="fas fa-save" style="font-size: 22px; color: #fff;"></i>
            </button>
        </div>
        
    </div>
    
    <!-- File size checker message positioned absolutely below right cluster -->
    <div id="file-size-preview" style="position: absolute; right: 0; top: 100%; margin-top: 5px; font-size: 11px; color: #64748b; text-align: right;"></div>
    
    <!-- File size checker -->
    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const fileInputs = document.querySelectorAll(".file-upload-input");
        const previewDiv = document.getElementById("file-size-preview");
        
        <?php 
        $maxUploadKb = empty($conf->global->MAIN_UPLOAD_DOC) ? 2048 : $conf->global->MAIN_UPLOAD_DOC; 
        $maxUploadBytes = $maxUploadKb * 1024;
        ?>
        const maxSizeBytes = <?php echo $maxUploadBytes; ?>;
        const maxSizeMB = (maxSizeBytes / (1024 * 1024)).toFixed(1);

        function updatePreview() {
            previewDiv.innerHTML = "";
            let hasError = false;
            let errorMessages = [];
            let previewHtml = "";
            
            fileInputs.forEach(function(input) {
                if (input.files && input.files.length > 0) {
                    for (let i = 0; i < input.files.length; i++) {
                        let file = input.files[i];
                        let sizeMB = (file.size / (1024 * 1024)).toFixed(2);
                        
                        // Check if text is too long (responsive display)
                        let shortName = file.name;
                        if (shortName.length > 15) {
                            shortName = shortName.substring(0, 7) + "..." + shortName.substring(shortName.length - 6);
                        }
                        
                        if (file.size > maxSizeBytes) {
                            hasError = true;
                            errorMessages.push("- " + file.name + " (" + sizeMB + " Mo)");
                            previewHtml += '<div style="color: #ef4444; margin-top: 2px;"><i class="fas fa-exclamation-circle"></i> ' + shortName + ' (' + sizeMB + ' Mo)</div>';
                        } else {
                            previewHtml += '<div style="color: #22c55e; margin-top: 2px;"><i class="fas fa-check-circle"></i> ' + shortName + ' (' + sizeMB + ' Mo)</div>';
                        }
                    }
                }
            });
            
            previewDiv.innerHTML = previewHtml;
            
            if (hasError) {
                // do not show alert popup, red text is enough
            }
            return hasError;
        }

        fileInputs.forEach(function(input) {
            input.addEventListener("change", function () {
                if (updatePreview()) {
                    this.value = ""; // Clear input to prevent submitting bad file
                    updatePreview(); // Refresh view
                }
            });
        });
    });
</script>

<style>
@keyframes recordingPulseAnim {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.7); background-color: #e74c3c; color: white; }
    50% { transform: scale(1.1); box-shadow: 0 0 0 12px rgba(231, 76, 60, 0); background-color: #c0392b; color: white; }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); background-color: #e74c3c; color: white; }
}
.recording-pulse-active {
    animation: recordingPulseAnim 1.5s infinite !important;
    background-color: #e74c3c !important;
    color: white !important;
}

@keyframes playingPulseAnim {
    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(123, 104, 238, 0.7); background-color: #7b68ee; color: white; }
    50% { transform: scale(1.1); box-shadow: 0 0 0 12px rgba(123, 104, 238, 0); background-color: #6a5acd; color: white; }
    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(123, 104, 238, 0); background-color: #7b68ee; color: white; }
}
.playing-pulse-active {
    animation: playingPulseAnim 1.5s infinite !important;
    background-color: #7b68ee !important;
    color: white !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // We bind after Saturne initialization
    setTimeout(function() {
        if(window.saturne && window.saturne.audio) {
            // Unbind the original events to force the use of our overrides
            $(document).off('click', '#start-recording');
            $(document).off('click', '#stop-recording');
            
            const newStartRecording = async function() {
                $('#start-recording i').removeClass('fa-microphone').addClass('fa-stop');
                $('#start-recording').addClass('recording-pulse-active');
                
                $('#play-recording').prop('disabled', true).css({'background-color': '#cbd5e1', 'cursor': 'not-allowed'});
                $('#delete-recording').css('display', 'none');
                
                try {
                    const stream = await window.saturne.audio.getMediaStream();
                    window.saturne.mediaRecoder = new MediaRecorder(stream);
                    let audioChunks = [];
                    window.saturne.mediaRecoder.ondataavailable = function(event) {
                        audioChunks.push(event.data);
                    };
                    window.saturne.mediaRecoder.start();
                    // $('#recording-indicator').show(); // removed per user request
                    $('#start-recording').attr('id', 'stop-recording');
                    $('.page-footer button').prop('disabled', true);

                    window.saturne.mediaRecoder.onstop = function() {
                        const audioBlob = new Blob(audioChunks, { type: 'audio/wav' });
                        const formData  = new FormData();
                        formData.append('audio', audioBlob, 'recording.wav');

                        const localAudioUrl = URL.createObjectURL(audioBlob);
                        
                        $('#play-recording')
                            .prop('disabled', false)
                            .css({'background-color': '#7b68ee', 'cursor': 'pointer'})
                            .off('click')
                            .on('click', function() {
                                const btn = $(this);
                                if (window.saturne.audio.player && !window.saturne.audio.player.paused) {
                                    window.saturne.audio.player.pause();
                                    window.saturne.audio.player.currentTime = 0;
                                    btn.removeClass('playing-pulse-active');
                                    btn.find('i').removeClass('fa-stop').addClass('fa-play');
                                    return;
                                }
                                if (window.saturne.audio.player) {
                                    window.saturne.audio.player.pause();
                                    window.saturne.audio.player.currentTime = 0;
                                }
                                window.saturne.audio.player = new Audio(localAudioUrl);
                                
                                btn.addClass('playing-pulse-active');
                                btn.find('i').removeClass('fa-play').addClass('fa-stop');
                                
                                window.saturne.audio.player.onended = function() {
                                    btn.removeClass('playing-pulse-active');
                                    btn.find('i').removeClass('fa-stop').addClass('fa-play');
                                };
                                
                                window.saturne.audio.player.play();
                            });
                            
                        // Show delete button
                        $('#delete-recording').css('display', 'flex');

                        let token          = window.saturne.toolbox.getToken();
                        let querySeparator = window.saturne.toolbox.getQuerySeparator(document.URL);

                        $.ajax({
                            url: document.URL + querySeparator + 'action=add_audio&token=' + token,
                            type: 'POST',
                            data: formData,
                            processData: false,
                            contentType: false,
                            xhr: function() {
                                let xhr = new XMLHttpRequest();
                                xhr.upload.onprogress = function(event) {
                                    let percent = Math.round((event.loaded / event.total) * 100);
                                    $('#recording-indicator').show().text('Téléchargement en cours : ' + percent + ' %');
                                };
                                return xhr;
                            },
                            complete: function(resp) {
                                $('.page-footer button').prop('disabled', false);
                                $('#recording-indicator').replaceWith($(resp.responseText).find('#recording-indicator'));
                            },
                        });
                    };

                } catch(e) { console.error(e); }
            };

            const newStopRecording = async function() {
                if (window.saturne.mediaRecoder && window.saturne.mediaRecoder.state !== 'inactive') {
                    window.saturne.mediaRecoder.stop();
                    let btn = $('#stop-recording');
                    btn.find('i').removeClass('fa-stop').addClass('fa-microphone');
                    btn.removeClass('recording-pulse-active');
                    btn.attr('id', 'start-recording');
                    
                    // Disable the microphone button to prevent overwrite
                    btn.prop('disabled', true).css({'background-color': '#cbd5e1', 'cursor': 'not-allowed'});
                }
            };
            
            // Re-bind our new functions explicitly to the document
            $(document).on('click', '#start-recording', newStartRecording);
            $(document).on('click', '#stop-recording', newStopRecording);
            
            // Overwrite the objects just in case other things call it
            window.saturne.audio.startRecording = newStartRecording;
            window.saturne.audio.stopRecording = newStopRecording;
        }
    }, 1000);
    
    $(document).on('click', '#delete-recording', function() {
        if (window.saturne.audio && window.saturne.audio.player) {
            window.saturne.audio.player.pause();
        }
        $('#play-recording').prop('disabled', true).css({'background-color': '#cbd5e1', 'cursor': 'not-allowed'}).off('click');
        $(this).css('display', 'none');
        
        // Reactivate the microphone button
        $('#start-recording').prop('disabled', false).css({'background-color': '', 'cursor': 'pointer'});
        
        $('#recording-indicator').text('Enregistrement supprimé.').css('color', '#64748b').show();
        setTimeout(() => $('#recording-indicator').hide(), 2000);
    });
});
</script>

<!-- PHOTO EDITOR MODAL -->
<div id="photo-editor-modal" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; padding: 15px;">
    <div style="width: 100%; max-width: 600px; max-height: 95vh; background: #ffffff; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">
        
        <!-- Header -->
        <div style="display:flex; justify-content: flex-start; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #3498db; padding-bottom: 5px; width: fit-content;">
            <i class="fas fa-crop-alt" style="color: #f39c12; margin-right: 8px; font-size: 1.2em;"></i>
            <h3 style="margin: 0; font-size: 1.2em; color: #333; font-weight: 600;">Éditer la photo</h3>
        </div>

        <!-- Canvas Area -->
        <div style="flex:1; display:flex; justify-content:center; align-items:center; overflow:hidden; background:#1e293b; border-radius: 8px; position:relative; min-height: 250px; min-width: 0; min-height: 0; width: 100%; height: 100%;" id="doli-editor-canvas-container">
            <canvas id="photo-editor-canvas" style="max-width:100%; max-height:100%; object-fit:contain; touch-action: none; cursor: crosshair;"></canvas>
            <div id="doli-crop-selection" style="display: none; position: absolute; border: 2px dashed #fff; background: rgba(255,255,255,0.2); pointer-events: none;"></div>
        </div>
        
        <!-- Unified Horizontal Toolbar -->
        <div style="margin-top: 15px; display:flex; flex-wrap: nowrap; overflow-x: auto; padding-bottom: 5px; justify-content: flex-start; align-items: center; gap: 6px;">
            <button type="button" class="doli-tool-btn" data-mode="crop" title="Recadrer" style="flex-shrink: 0; background-color: #34495e; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: background 0.2s;">
                <i class="fas fa-crop"></i>
            </button>
            <button type="button" class="doli-tool-btn" data-mode="rotate" title="Pivoter" style="flex-shrink: 0; background-color: #34495e; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: background 0.2s;">
                <i class="fas fa-undo"></i>
            </button>
            <button type="button" class="doli-tool-btn" id="btn-undo-action" title="Annuler dessin" style="flex-shrink: 0; background-color: #7f8c8d; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: background 0.2s;">
                <i class="fas fa-reply"></i>
            </button>
            <div style="flex-shrink: 0; display: flex; background-color: #3498db; border-radius: 4px; overflow: hidden; height: 40px;" id="pencil-tool-container">
                <button type="button" class="doli-tool-btn active" data-mode="pencil" title="Crayon" style="background-color: transparent; color: white; border: none; width: 40px; height: 100%; cursor: pointer; display:flex; justify-content:center; align-items:center;">
                    <i class="fas fa-pencil-alt"></i>
                </button>
                <div style="padding: 4px; display: flex; align-items: center; background: rgba(0,0,0,0.1);">
                    <input type="color" id="draw-color-picker" value="#e74c3c" style="width: 24px; height: 24px; border:none; padding:0; cursor:pointer;" title="Couleur" />
                </div>
            </div>
            <button type="button" class="doli-tool-btn" data-mode="text" title="Texte" style="flex-shrink: 0; background-color: #34495e; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: background 0.2s;">
                <i class="fas fa-font"></i>
            </button>
            <button type="button" class="doli-tool-btn" data-mode="arrow" title="Flèche" style="flex-shrink: 0; background-color: #34495e; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: background 0.2s;">
                <i class="fas fa-location-arrow" style="transform: rotate(-45deg);"></i>
            </button>
            <button type="button" class="doli-tool-btn" data-mode="rect" title="Cadre" style="flex-shrink: 0; background-color: #34495e; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: background 0.2s;">
                <i class="far fa-square"></i>
            </button>
            <button type="button" class="doli-tool-btn" data-mode="blur" title="Flouter" style="flex-shrink: 0; background-color: #34495e; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: background 0.2s;">
                <i class="fas fa-tint-slash"></i>
            </button>
            <button type="button" class="doli-tool-btn" data-mode="sequence" title="Puce Numérotée" style="flex-shrink: 0; background-color: #34495e; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; font-weight: bold; font-family: sans-serif;">
                <i class="fas fa-list-ol"></i>
            </button>
            
            <div style="position: relative; flex-shrink: 0; width: 40px; height: 40px; background-color: #34495e; border-radius: 4px; display: flex; justify-content: center; align-items: center; color: white; margin-left: auto;" title="Réglages de qualité de l'image">
                <i class="fas fa-cog"></i>
                <select id="photo-size-select" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; -webkit-appearance: none; appearance: none;">
                    <option value="hd" selected>HD (720p)</option>
                    <option value="fullhd">Full HD (1080p)</option>
                    <option value="full">Originale (FULL)</option>
                </select>
            </div>

            <button type="button" id="btn-cancel-photo" title="Annuler (Reprendre)" style="flex-shrink: 0; background-color:#e74c3c; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: opacity 0.2s;">
                <i class="fas fa-camera"></i>
            </button>
            
            <button type="button" id="btn-validate-photo" title="Valider" style="flex-shrink: 0; background-color:#2ecc71; color: white; border: none; width: 40px; height: 40px; border-radius: 4px; cursor: pointer; display:flex; justify-content:center; align-items:center; transition: opacity 0.2s;">
                <i class="fas fa-check" style="font-size: 1.2em;"></i>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    window.photoFilesArray = [];

    const photoInput = document.getElementById('upload-photo');
    const modal = document.getElementById('photo-editor-modal');
    const canvas = document.getElementById('photo-editor-canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    
    const colorPicker = document.getElementById('draw-color-picker');
    const btnCancel = document.getElementById('btn-cancel-photo');
    const btnValidate = document.getElementById('btn-validate-photo');
    const btnUndo = document.getElementById('btn-undo-action');
    const sizeSelect = document.getElementById('photo-size-select');
    const thumbnailContainer = document.getElementById('photo-thumbnail-container');
    const cropSelectionDiv = document.getElementById('doli-crop-selection');

    let originalBlobUrl = null;
    let currentMode = 'pencil'; // pencil, crop, rotate, arrow, text, rect, blur, sequence
    let isDrawing = false;
    let startX = 0, startY = 0;
    let startClientX = 0, startClientY = 0;
    var snapshot = null;
    let sequenceCounter = 1;
    let historyStack = []; // Pour Undo

    // Stylisation des boutons
    const toolBtns = document.querySelectorAll('.doli-tool-btn[data-mode]');
    toolBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const mode = btn.getAttribute('data-mode');
            if (mode === 'rotate') {
                rotateCanvas();
                return; // ne pas changer de mode actif
            }
            
            toolBtns.forEach(b => {
                if (b.parentElement.id === 'pencil-tool-container') {
                    b.parentElement.style.backgroundColor = '#34495e';
                } else {
                    b.style.backgroundColor = '#34495e'; 
                }
            });
            
            if (btn.parentElement.id === 'pencil-tool-container') {
                btn.parentElement.style.backgroundColor = '#3498db';
            } else {
                btn.style.backgroundColor = '#3498db';
            }
            
            currentMode = mode;
            if (currentMode === 'text') canvas.style.cursor = 'text';
            else canvas.style.cursor = 'crosshair';
            
            if (currentMode === 'sequence') sequenceCounter = 1;
        });
    });

    function getMousePos(e) {
        const rect = canvas.getBoundingClientRect();
        let clientX = e.clientX;
        let clientY = e.clientY;
        if(e.touches && e.touches.length > 0) {
            clientX = e.touches[0].clientX;
            clientY = e.touches[0].clientY;
        }
        const logicalX = clientX - rect.left;
        const logicalY = clientY - rect.top;
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        return {
            logicalX: logicalX,
            logicalY: logicalY,
            x: logicalX * scaleX,
            y: logicalY * scaleY,
            clientX: clientX,
            clientY: clientY
        };
    }

    function saveState() {
        historyStack.push(ctx.getImageData(0, 0, canvas.width, canvas.height));
        if(historyStack.length > 20) historyStack.shift(); // Max 20 undo
    }

    btnUndo.addEventListener('click', () => {
        if(historyStack.length > 0) {
            const lastState = historyStack.pop();
            canvas.width = lastState.width; // Restaurer potentiellement la taille (si crop)
            canvas.height = lastState.height;
            ctx.putImageData(lastState, 0, 0);
        }
    });

    const onMouseDown = (e) => {
        if (e.target.id === 'doli-floating-text-input') return;
        
        saveState();

        if (currentMode === 'text') {
            e.preventDefault();
            const pos = getMousePos(e);
            addTextInput(pos.x, pos.y, pos.clientX, pos.clientY);
            return;
        }

        isDrawing = true;
        const pos = getMousePos(e);
        startX = pos.x;
        startY = pos.y;
        startClientX = pos.clientX;
        startClientY = pos.clientY;

        snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);

        if (currentMode === 'pencil') {
            ctx.beginPath();
            ctx.arc(startX, startY, 3, 0, Math.PI*2);
            ctx.fillStyle = colorPicker.value;
            ctx.fill();
            ctx.beginPath();
        } else if (currentMode === 'sequence') {
            drawSequenceCircle(ctx, startX, startY, sequenceCounter, colorPicker.value);
        } else if (currentMode === 'crop') {
            const containerRect = canvas.parentElement.getBoundingClientRect();
            cropSelectionDiv.style.left = (startClientX - containerRect.left) + 'px';
            cropSelectionDiv.style.top = (startClientY - containerRect.top) + 'px';
            cropSelectionDiv.style.width = '0px';
            cropSelectionDiv.style.height = '0px';
            cropSelectionDiv.style.display = 'block';
        }
    };

    const onMouseMove = (e) => {
        if (!isDrawing) return;
        e.preventDefault();
        const pos = getMousePos(e);

        if (currentMode === 'pencil') {
            ctx.strokeStyle = colorPicker.value;
            ctx.lineWidth = 6;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.moveTo(startX, startY);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            startX = pos.x;
            startY = pos.y;
        } else if (currentMode === 'arrow') {
            ctx.putImageData(snapshot, 0, 0);
            drawArrow(ctx, startX, startY, pos.x, pos.y, colorPicker.value);
        } else if (currentMode === 'rect') {
            ctx.putImageData(snapshot, 0, 0);
            drawRect(ctx, startX, startY, pos.x, pos.y, colorPicker.value);
        } else if (currentMode === 'blur') {
            ctx.putImageData(snapshot, 0, 0);
            ctx.fillStyle = 'rgba(100, 100, 100, 0.5)';
            ctx.fillRect(startX, startY, pos.x - startX, pos.y - startY);
        } else if (currentMode === 'sequence') {
            ctx.putImageData(snapshot, 0, 0);
            const dx = pos.x - startX;
            const dy = pos.y - startY;
            if (Math.hypot(dx, dy) > 20) {
                drawArrow(ctx, startX, startY, pos.x, pos.y, colorPicker.value);
            }
            drawSequenceCircle(ctx, startX, startY, sequenceCounter, colorPicker.value);
        } else if (currentMode === 'crop') {
            const containerRect = canvas.parentElement.getBoundingClientRect();
            const canvasRect = canvas.getBoundingClientRect();
            
            const currentX = Math.max(canvasRect.left, Math.min(pos.clientX, canvasRect.right));
            const currentY = Math.max(canvasRect.top, Math.min(pos.clientY, canvasRect.bottom));
            const clampedStartX = Math.max(canvasRect.left, Math.min(startClientX, canvasRect.right));
            const clampedStartY = Math.max(canvasRect.top, Math.min(startClientY, canvasRect.bottom));

            const x = Math.min(currentX, clampedStartX) - containerRect.left;
            const y = Math.min(currentY, clampedStartY) - containerRect.top;
            const w = Math.abs(currentX - clampedStartX);
            const h = Math.abs(currentY - clampedStartY);

            cropSelectionDiv.style.left = x + 'px';
            cropSelectionDiv.style.top = y + 'px';
            cropSelectionDiv.style.width = w + 'px';
            cropSelectionDiv.style.height = h + 'px';
        }
    };

    const onMouseUp = (e) => {
        if (!isDrawing) return;
        isDrawing = false;
        const pos = getMousePos(e);

        if (currentMode === 'pencil') {
            ctx.closePath();
        } else if (currentMode === 'arrow') {
            ctx.putImageData(snapshot, 0, 0);
            drawArrow(ctx, startX, startY, pos.x, pos.y, colorPicker.value);
        } else if (currentMode === 'rect') {
            ctx.putImageData(snapshot, 0, 0);
            drawRect(ctx, startX, startY, pos.x, pos.y, colorPicker.value);
        } else if (currentMode === 'blur') {
            ctx.putImageData(snapshot, 0, 0);
            const w = pos.x - startX;
            const h = pos.y - startY;
            if (Math.abs(w) > 5 && Math.abs(h) > 5) {
                applyAreaBlur(ctx, startX, startY, w, h, 10);
            } else {
                 historyStack.pop(); // Annuler saveState si clic simple
            }
        } else if (currentMode === 'sequence') {
            ctx.putImageData(snapshot, 0, 0); 
            const dx = pos.x - startX;
            const dy = pos.y - startY;
            if (Math.hypot(dx, dy) > 20) {
                drawArrow(ctx, startX, startY, pos.x, pos.y, colorPicker.value);
            }
            drawSequenceCircle(ctx, startX, startY, sequenceCounter, colorPicker.value);
            sequenceCounter++;
        } else if (currentMode === 'crop') {
            cropSelectionDiv.style.display = 'none';
            // Clamping the release values to ensure we map correctly without negative indices inside applyCrop
            const clampedPosX = Math.max(0, Math.min(pos.x, canvas.width));
            const clampedPosY = Math.max(0, Math.min(pos.y, canvas.height));
            const clampedStartX = Math.max(0, Math.min(startX, canvas.width));
            const clampedStartY = Math.max(0, Math.min(startY, canvas.height));
            
            const w = Math.abs(clampedPosX - clampedStartX);
            const h = Math.abs(clampedPosY - clampedStartY);
            if (w > 20 && h > 20) {
                applyCrop(Math.min(clampedPosX, clampedStartX), Math.min(clampedPosY, clampedStartY), w, h);
            } else {
                 historyStack.pop(); // Annuler saveState si crop trop petit
            }
        }
    };

    canvas.addEventListener('mousedown', onMouseDown);
    window.addEventListener('mousemove', onMouseMove);
    window.addEventListener('mouseup', onMouseUp);
    canvas.addEventListener('touchstart', onMouseDown, {passive: false});
    window.addEventListener('touchmove', onMouseMove, {passive: false});
    window.addEventListener('touchend', onMouseUp);

    // Formes natives
    function drawArrow(context, fromX, fromY, toX, toY, color) {
        const headlen = 20; 
        const angle = Math.atan2(toY - fromY, toX - fromX);
        const lineEndX = toX - 15 * Math.cos(angle);
        const lineEndY = toY - 15 * Math.sin(angle);
        context.beginPath();
        context.strokeStyle = color;
        context.lineWidth = 6;
        context.moveTo(fromX, fromY);
        context.lineTo(lineEndX, lineEndY);
        context.stroke();
        context.beginPath();
        context.fillStyle = color;
        context.moveTo(toX, toY);
        context.lineTo(toX - headlen * Math.cos(angle - Math.PI / 6), toY - headlen * Math.sin(angle - Math.PI / 6));
        context.lineTo(toX - headlen * Math.cos(angle + Math.PI / 6), toY - headlen * Math.sin(angle + Math.PI / 6));
        context.lineTo(toX, toY);
        context.fill();
    }

    function drawRect(context, fromX, fromY, toX, toY, color) {
        context.beginPath();
        context.strokeStyle = color;
        context.lineWidth = 6;
        context.rect(fromX, fromY, toX - fromX, toY - fromY);
        context.stroke();
    }

    function drawSequenceCircle(context, x, y, number, color) {
        const radius = 20;
        context.beginPath();
        context.arc(x, y, radius, 0, 2 * Math.PI, false);
        context.fillStyle = color;
        context.fill();
        context.lineWidth = 3;
        context.strokeStyle = '#ffffff';
        context.stroke();
        context.fillStyle = '#ffffff';
        context.font = 'bold 20px Arial';
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(number.toString(), x, y + 2);
    }

    function applyAreaBlur(context, x, y, w, h, blurAmount) {
        const rx = w < 0 ? x + w : x;
        const ry = h < 0 ? y + h : y;
        const rw = Math.abs(w);
        const rh = Math.abs(h);
        const imageData = context.getImageData(rx, ry, rw, rh);
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = rw; tempCanvas.height = rh;
        tempCanvas.getContext('2d').putImageData(imageData, 0, 0);
        const blurCanvas = document.createElement('canvas');
        blurCanvas.width = rw; blurCanvas.height = rh;
        const bCtx = blurCanvas.getContext('2d');
        bCtx.filter = `blur(${blurAmount}px)`;
        bCtx.drawImage(tempCanvas, 0, 0);
        context.drawImage(blurCanvas, rx, ry);
    }

    function applyCrop(x, y, w, h) {
        const croppedImage = ctx.getImageData(x, y, w, h);
        canvas.width = w;
        canvas.height = h;
        ctx.putImageData(croppedImage, 0, 0);
    }

    function rotateCanvas() {
        saveState();
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = canvas.height;
        tempCanvas.height = canvas.width;
        const tctx = tempCanvas.getContext('2d');
        tctx.translate(tempCanvas.width / 2, tempCanvas.height / 2);
        tctx.rotate(90 * Math.PI / 180);
        tctx.drawImage(canvas, -canvas.width / 2, -canvas.height / 2);
        canvas.width = tempCanvas.width;
        canvas.height = tempCanvas.height;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(tempCanvas, 0, 0);
    }

    function addTextInput(canvasX, canvasY, clientX, clientY) {
        const existing = document.getElementById('doli-floating-text-input');
        if (existing) existing.blur();

        const initialRect = canvas.getBoundingClientRect();
        const initialScaleY = canvas.height / initialRect.height;

        const input = document.createElement('input');
        input.type = 'text';
        input.id = 'doli-floating-text-input';
        input.spellcheck = false; 
        input.autocomplete = 'off';
        input.style.position = 'fixed';
        input.style.left = clientX + 'px';
        input.style.top = clientY + 'px';
        input.style.color = colorPicker.value;
        input.style.fontSize = '24px';
        input.style.fontWeight = 'bold';
        input.style.fontFamily = 'Arial';
        input.style.outline = 'none';
        input.style.border = '2px dotted rgba(255, 255, 255, 0.8)';
        input.style.padding = '2px 8px';
        input.style.background = 'rgba(0, 0, 0, 0.15)'; // Very subtle glassmorphism
        input.style.borderRadius = '4px';
        input.style.textShadow = '1px 1px 3px rgba(0,0,0,0.8)';
        input.style.boxShadow = '0 0 6px rgba(0,0,0,0.3)';
        input.style.zIndex = '999999';
        input.style.minWidth = '150px';
        input.style.height = '40px';
        input.style.boxSizing = 'border-box';
        input.placeholder = "Texte...";
        document.body.appendChild(input);

        input.addEventListener('input', function() {
            this.style.width = 'auto';
            this.style.width = Math.max(150, this.scrollWidth + 10) + 'px';
        });

        requestAnimationFrame(function() {
            if (input) input.focus();
        });

        input.addEventListener('blur', () => {
            const text = input.value.trim();
            if (text) {
                const fontSize = Math.max(20, Math.floor(24 * initialScaleY));
                ctx.font = 'bold ' + fontSize + 'px Arial';
                ctx.fillStyle = colorPicker.value;
                ctx.textBaseline = 'top';
                ctx.shadowColor = 'rgba(0,0,0,0.8)';
                ctx.shadowBlur = 4;
                ctx.shadowOffsetX = 1;
                ctx.shadowOffsetY = 1;
                ctx.fillText(text, canvasX, canvasY);
                ctx.shadowColor = 'transparent'; // reset
            } else {
                historyStack.pop(); // Rien d'écrit, on annule l'undo
            }
            if(input.parentNode) input.parentNode.removeChild(input);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            }
        });
    }

    photoInput.addEventListener('change', function(e) {
        if (this.files && this.files.length > 0) {
            const originalFile = this.files[this.files.length - 1];
            originalBlobUrl = URL.createObjectURL(originalFile);
            const img = new Image();
            img.onload = function() {
                const isFullHD = sizeSelect.value === 'fullhd';
                const maxDim = isFullHD ? 1920 : 1280;
                let width = img.width; let height = img.height; let ratio = 1;
                if (width > maxDim || height > maxDim) {
                    ratio = maxDim / Math.max(width, height);
                }
                canvas.width = width * ratio;
                canvas.height = height * ratio;
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                
                historyStack = []; // Reset undo stack
                modal.style.display = 'flex';
            };
            img.src = originalBlobUrl;
            updateFileInput();
        }
    });

    function renderThumbnails() {
        const wrappers = thumbnailContainer.querySelectorAll('.doli-thumbnail-wrapper');
        wrappers.forEach(w => w.remove());
        
        if (window.photoFilesArray.length > 0) {
            window.photoFilesArray.forEach((file, index) => {
                const wrap = document.createElement('div');
                wrap.style.position = 'relative';
                wrap.style.order = '4'; // Places it exactly between Camera (3) and Gallery (99)
                wrap.className = 'doli-thumbnail-wrapper';
                
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.style.width = '48px'; img.style.height = '48px';
                img.style.objectFit = 'cover'; img.style.borderRadius = '12px';
                img.style.border = '1px solid #e2e8f0';
                
                const delBtn = document.createElement('button');
                delBtn.type = 'button';
                delBtn.innerHTML = '<i class="fas fa-times"></i>';
                delBtn.style.cssText = 'position: absolute; top: -6px; right: -6px; width: 22px; height: 22px; border-radius: 50%; background-color: #e74c3c; color: white; border: none; font-size: 11px; cursor: pointer; display: flex; justify-content: center; align-items: center; z-index: 10; padding: 0; line-height: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.2);';
                delBtn.onclick = function() {
                    window.photoFilesArray.splice(index, 1);
                    updateFileInput();
                    renderThumbnails();
                };
                wrap.appendChild(img); wrap.appendChild(delBtn);
                thumbnailContainer.appendChild(wrap);
            });
        }
    }
    
    function updateFileInput() {
        const dt = new DataTransfer();
        window.photoFilesArray.forEach(f => dt.items.add(f));
        photoInput.files = dt.files;
    }

    btnCancel.addEventListener('click', function() {
        modal.style.display = 'none';
        if (originalBlobUrl) { URL.revokeObjectURL(originalBlobUrl); originalBlobUrl = null; }
        requestAnimationFrame(function() { photoInput.click(); });
        updateFileInput();
    });

    btnValidate.addEventListener('click', function() {
        const activeText = document.getElementById('doli-floating-text-input');
        if (activeText) activeText.blur();

        canvas.toBlob(function(blob) {
            let filename = "photo_" + new Date().getTime() + ".jpg";
            const newFile = new File([blob], filename, { type: "image/jpeg", lastModified: new Date().getTime() });
            window.photoFilesArray.push(newFile);
            updateFileInput();
            renderThumbnails();
            modal.style.display = 'none';
            if (originalBlobUrl) { URL.revokeObjectURL(originalBlobUrl); originalBlobUrl = null; }
        }, 'image/jpeg', 0.85); 
    });


});
</script>
</div>

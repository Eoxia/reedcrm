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
print '<div class="page-content" style="margin-top: 5px; padding-top: 0; max-width: 1000px; margin: 5px auto 0 auto;">';

print '<div class="title" style="color: #5a7b97; font-size: 0.95em; font-weight: bold; margin-bottom: 15px; padding-left: 20px;">' . $listTitle . '</div>';



foreach ($latestProjects as $project) {
    if (empty($project->array_options)) {
        $project->fetch_optionals();
    }

    $ref       = $project->getNomUrl(1);
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
    
    // Photo payload
    $photoFiles = dol_dir_list($projectDir, 'files', 0, '\.(png|jpg|jpeg|gif|webp)$', null, 'date', SORT_DESC);
    $photoCount = is_array($photoFiles) ? count($photoFiles) : 0;
    
    $photoThumbHtml = '';
    if ($photoCount > 0) {
        $firstPhoto = $photoFiles[0];
        $photoUrl = DOL_URL_ROOT . '/document.php?modulepart=projet&file=' . urlencode(dol_sanitizeFileName($project->ref) . '/' . $firstPhoto['name']);
        
        $allPhotoUrls = [];
        foreach($photoFiles as $pf) {
            $allPhotoUrls[] = DOL_URL_ROOT . '/document.php?modulepart=projet&file=' . urlencode(dol_sanitizeFileName($project->ref) . '/' . $pf['name']);
        }
        $photosJson = htmlspecialchars(json_encode($allPhotoUrls), ENT_QUOTES, 'UTF-8');
        
        $photoThumbHtml = '
        <div class="project-photo-trigger" data-project-id="' . $project->id . '" data-photos="' . $photosJson . '" style="position:relative; width:52px; height:52px; border-radius:8px; cursor:pointer; margin-left: 12px; border: 1px solid #e2e8f0; background: #f1f5f9; background-image: url(\'' . dol_escape_htmltag($photoUrl) . '\'); background-size: cover; background-position: center; flex-shrink: 0; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s;">
            <div style="position:absolute; top:-6px; right:-6px; background:#94a3b8; color:white; font-size:11px; font-weight:bold; border-radius:12px; min-width: 16px; text-align: center; padding: 2px 5px; box-shadow:0 1px 2px rgba(0,0,0,0.2); border: 2px solid white;">' . $photoCount . '</div>
        </div>';
    }
    $audioPlayerHtml = '';
    if (!empty($audioFiles)) {
        $lastAudio = $audioFiles[0];
        $fileUrl = DOL_URL_ROOT . '/document.php?modulepart=projet&file=' . urlencode(dol_sanitizeFileName($project->ref) . '/' . $lastAudio['name']);
        $audioPlayerHtml = '<div style="margin-left: 4px;"><audio class="minimal-audio" controls controlslist="nodownload noplaybackrate" preload="metadata" style="height: 28px; width: 155px; outline: none; border-radius: 20px;"><source src="' . dol_escape_htmltag($fileUrl) . '" type="audio/wav"></audio></div>';
    }

    // Probability and amount
    $percent   = $project->opp_percent ? $project->opp_percent . ' %' : '0.00 %';
    $amount    = $project->opp_amount ? price($project->opp_amount, 0, '', 11, -1, -1, 'auto') : '0 €';
    
    $url       = DOL_URL_ROOT . '/projet/card.php?id=' . $project->id;
    
    // Ensure some styling for the cards to match image, explicitly neutrering Dolibarr's native .card >1024px breakpoints
    print '<div class="project-history-card" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 10px; margin: 0 0 10px 0 !important; background-color: #f8fbff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
    
    // --- ROW 1: Meta, Initials & Amounts ---
    print '<div style="display: flex; justify-content: space-between; align-items: stretch; margin-bottom: 8px;">';
    
        // Top Left
        print '<div style="display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 4px;">';
            print '<div style="font-weight: 600; font-size: 1.1em;">' . $ref . '</div>';
            print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;">&bull;</span>';
            print '<div style="font-size: 0.85em; color: #718096;"><i class="far fa-calendar-alt" style="margin-right: 4px;"></i>' . $creationDate . '</div>';
            
            if (!empty($userInitials)) {
                print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;"></span>';
                print '<div title="' . dol_escape_htmltag($author->getFullName($langs)) . '" style="font-size: 0.7em; color: #fff; background: #9b59b6; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">' . $userInitials . '</div>';
            }
        print '</div>';
        
        // Top Right
        $rawAmount = empty($project->opp_amount) ? 0 : (float)$project->opp_amount;
        print '<div style="display: flex; align-items: center; flex-shrink: 0; font-weight: 600; font-size: 0.95em;">';
            print '<span class="inline-edit-percent" data-project-id="'.$project->id.'" data-val="'.(int)$project->opp_percent.'" style="color: #0f172a; cursor: pointer; border-bottom: 1px dashed #cbd5e0; padding-bottom: 1px; transition: color 0.3s; display: inline-flex; align-items: center; white-space: nowrap; line-height: 1;" title="Modifier la probabilité">' . $percent . '</span>';
            print '<span style="color: #cbd5e0; margin: 0 6px;">-</span>';
            print '<span class="inline-edit-amount" data-project-id="'.$project->id.'" data-val="'.$rawAmount.'" style="color: #3b82f6; cursor: pointer; border-bottom: 1px dashed #cbd5e0; padding-bottom: 1px; transition: color 0.3s; display: inline-flex; align-items: center; white-space: nowrap; line-height: 1;" title="Modifier le montant">' . $amount . '</span>';
        print '</div>';
        
    print '</div>';
    
    // --- ROW 2: Body (Title, Contact, Audio) AND Media Right ---
    print '<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 10px;">';
        
        // Left Column
        print '<div style="display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 0;">';
            
            // Title
            if (!empty($title)) {
                $descParts = [];
                if (!empty($project->description)) $descParts[] = trim(dol_string_nohtmltag($project->description, 1));
                if (!empty($project->note_public)) $descParts[] = trim(dol_string_nohtmltag($project->note_public, 1));
                if (!empty($project->note_private)) $descParts[] = trim(dol_string_nohtmltag($project->note_private, 1));

                $descClean = !empty($descParts) ? implode(" \n---\n ", $descParts) : '(Aucune description / note)';
                $descAttr = ' data-tooltip="' . dol_escape_htmltag($descClean) . '"';
                
                print '<div class="fast-css-tooltip" ' . $descAttr . ' style="color: #4a5568; font-size: 0.95em; display: flex; align-items: center; position: relative; cursor: pointer; width: 100%; max-width: 100%; overflow: hidden;">';
                    $statVal = isset($project->status) ? $project->status : (isset($project->statut) ? $project->statut : (isset($project->fk_statut) ? $project->fk_statut : 1));
                    if ($statVal == 1) {
                        print '<div style="width: 10px; height: 10px; background-color: #2ecc71; border-radius: 50%; display: inline-block; flex-shrink: 0;" title="Ouvert"></div>';
                    } elseif ($statVal == 0) {
                        print '<div style="width: 10px; height: 10px; background-color: #fff; border: 2px solid #e74c3c; border-radius: 50%; display: inline-block; flex-shrink: 0;" title="Brouillon"></div>';
                    } else {
                        print '<div style="width: 10px; height: 10px; background-color: #95a5a6; border-radius: 50%; display: inline-block; flex-shrink: 0;" title="Clôturé"></div>';
                    }
                    print '<span class="inline-edit-title" data-project-id="' . $project->id . '" data-val="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; display: block; width: 100%; margin-left: 6px;" title="Modifier le titre">' . dol_escape_htmltag($title) . '</span>';
                print '</div>';
            }
            
            // Contact
            $cFirstName = trim($firstname);
            $cLastName = trim($lastname);
            $cPhone = trim($phone);
            $cEmail = trim($email);
            $cWeb = isset($project->array_options['options_reedcrm_website']) ? trim($project->array_options['options_reedcrm_website']) : '';
            
            $hFirstName = $cFirstName ? dol_escape_htmltag($cFirstName) : '<span style="color:#cbd5e0; font-style:italic;">Prénom</span>';
            $hLastName = $cLastName ? dol_escape_htmltag($cLastName) : '<span style="color:#cbd5e0; font-style:italic;">Nom</span>';
            $hPhone = $cPhone ? dol_escape_htmltag($cPhone) : '<span style="color:#cbd5e0; font-style:italic;">Téléphone</span>';
            $hEmail = $cEmail ? dol_escape_htmltag($cEmail) : '<span style="color:#cbd5e0; font-style:italic;">Email</span>';
            $hWeb = $cWeb ? dol_escape_htmltag($cWeb) : '<span style="color:#cbd5e0; font-style:italic;">Site Web</span>';
            
            $linkPhone = $cPhone ? '<a href="tel:'.dol_escape_htmltag($cPhone).'" class="prevent-edit-click" style="color: inherit; text-decoration: none;" title="Appeler"><i class="fas fa-phone copy-action-icon" data-copy="'.dol_escape_htmltag($cPhone).'" style="color: #64748b; margin-right: 6px; cursor: pointer;"></i></a>' : '<i class="fas fa-phone" style="color: #64748b; margin-right: 6px;"></i>';
            $linkEmail = $cEmail ? '<a href="mailto:'.dol_escape_htmltag($cEmail).'" class="prevent-edit-click" style="color: inherit; text-decoration: none;" title="Envoyer un email"><i class="fas fa-envelope copy-action-icon" data-copy="'.dol_escape_htmltag($cEmail).'" style="color: #64748b; margin-right: 6px; cursor: pointer;"></i></a>' : '<i class="fas fa-envelope" style="color: #64748b; margin-right: 6px;"></i>';
            $webHref = strpos($cWeb, 'http') === 0 ? $cWeb : 'https://' . $cWeb;
            $linkWeb = $cWeb ? '<a href="'.dol_escape_htmltag($webHref).'" target="_blank" class="prevent-edit-click" style="color: inherit; text-decoration: none;" title="Ouvrir le site web"><i class="fas fa-globe copy-action-icon" data-copy="'.dol_escape_htmltag($cWeb).'" style="color: #64748b; margin-right: 6px; cursor: pointer;"></i></a>' : '<i class="fas fa-globe" style="color: #64748b; margin-right: 6px;"></i>';

            print '<div class="contact-inline-wrapper" style="color: #718096; font-size: 0.9em; margin-bottom: 2px; position: relative;" data-project-id="'.$project->id.'">';
                print '<div class="contact-display-area" style="display: flex; align-items: center; gap: 0px; flex-wrap: wrap; padding: 2px 0;">';
                    print '<i class="fas fa-address-book" style="color: #64748b; font-size: 1.1em; margin-right: 6px;"></i>';
                    print '<span class="inline-edit-contact" data-field="firstname" data-val="'.dol_escape_htmltag($cFirstName).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 4px;" title="Modifier le prénom">' . $hFirstName . '</span>';
                    print '<span class="inline-edit-contact" data-field="lastname" data-val="'.dol_escape_htmltag($cLastName).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 8px;" title="Modifier le nom">' . $hLastName . '</span>';
                    print '<span style="color: #cbd5e0; margin-right: 8px;">&bull;</span>';
                    
                    print $linkPhone;
                    print '<span class="inline-edit-contact" data-field="phone" data-val="'.dol_escape_htmltag($cPhone).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 8px;" title="Modifier le téléphone">' . $hPhone . '</span>';
                    print '<span style="color: #cbd5e0; margin-right: 8px;">&bull;</span>';
                    
                    print $linkEmail;
                    print '<span class="inline-edit-contact" data-field="email" data-val="'.dol_escape_htmltag($cEmail).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 8px;" title="Modifier l\'email">' . $hEmail . '</span>';
                    print '<span style="color: #cbd5e0; margin-right: 8px;">&bull;</span>';
                    
                    print $linkWeb;
                    print '<span class="inline-edit-contact" data-field="website" data-val="'.dol_escape_htmltag($cWeb).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s;" title="Modifier le site web">' . $hWeb . '</span>';
                print '</div>';
            print '</div>';

            // Thirdparty (Tiers)
            $tiersId = !empty($project->socid) ? $project->socid : (!empty($project->fk_soc) ? $project->fk_soc : 0);
            if ($tiersId > 0) {
                $soc = new Societe($db);
                if ($soc->fetch($tiersId) > 0) {
                    print '<div style="color: #64748b; font-size: 0.95em; margin-bottom: 2px; display: flex; align-items: center; gap: 0px;" title="Tiers du projet">';
                        print (method_exists($soc, 'getLibStatut') ? $soc->getLibStatut(3) . ' ' : '');
                        print '<span style="font-weight: 500; margin-left: 2px;">' . $soc->getNomUrl(1) . '</span>';
                        if (!empty($soc->phone)) {
                            print '<span style="color: #cbd5e0; margin: 0 4px;">&bull;</span>';
                            print '<i class="fas fa-phone copy-action-icon" data-copy="'.dol_escape_htmltag($soc->phone).'" style="color: #64748b; font-size: 1.0em; margin-right: 6px; cursor: copy;" title="Copier le numéro"></i>';
                            print '<a href="tel:' . dol_escape_htmltag($soc->phone) . '" class="prevent-edit-click" style="color: inherit; text-decoration: none;" title="Appeler le numéro">' . dol_escape_htmltag($soc->phone) . '</a>';
                        }
                        if (!empty($soc->email)) {
                            print '<span style="color: #cbd5e0; margin: 0 4px;">&bull;</span>';
                            print '<i class="fas fa-envelope copy-action-icon" data-copy="'.dol_escape_htmltag($soc->email).'" style="color: #64748b; font-size: 1.0em; margin-right: 6px; cursor: copy;" title="Copier l\'email"></i>';
                            print '<a href="mailto:' . dol_escape_htmltag($soc->email) . '" class="prevent-edit-click" style="color: inherit; text-decoration: none;" title="Envoyer un email">' . dol_escape_htmltag($soc->email) . '</a>';
                        }
                    print '</div>';
                }
            }
            
            // Audio System
            if (!empty($audioPlayerHtml)) {
                print '<div style="margin-top: 4px;">' . str_replace("width: 155px;", "width: 100%; max-width: 200px;", $audioPlayerHtml) . '</div>';
            } else {
                print '<div class="inline-audio-recorder" data-project-id="' . $project->id . '" style="display: flex; gap: 8px; padding: 6px; background: #f1f5f9; border-radius: 12px; align-items: center; width: max-content; margin-top: 4px;">';
                print '<button type="button" class="btn-inline-record" style="width: 48px; height: 48px; border-radius: 10px; border: none; background: #7b68ee; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i class="fas fa-microphone" style="font-size: 24px;"></i></button>';
                
                print '<div style="position: relative; display: flex;">';
                print '<button type="button" class="btn-inline-play" disabled style="width: 48px; height: 48px; border-radius: 10px; border: none; background: #cbd5e1; color: white; cursor: not-allowed; display: flex; align-items: center; justify-content: center;"><i class="fas fa-play" style="font-size: 24px;"></i></button>';
                print '<button type="button" class="btn-inline-delete" style="display: none; position: absolute; top: -6px; right: -6px; width: 22px; height: 22px; border-radius: 50%; background-color: #e74c3c; color: white; border: none; font-size: 12px; cursor: pointer; justify-content: center; align-items: center; z-index: 10;"><i class="fas fa-times"></i></button>';
                print '</div>';

                print '<button type="button" class="btn-inline-save" disabled style="width: 48px; height: 48px; border-radius: 10px; border: none; background: #9b59b6; color: white; cursor: not-allowed; opacity: 0.5; display: flex; align-items: center; justify-content: center;"><i class="fas fa-save" style="font-size: 24px;"></i></button>';
                print '</div>';
            }
            
        print '</div>'; // End Left Column
        
        // Right Column
        print '<div style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; width: 110px;">';
            
            // Top Right: Photo & Link
            $thumbToPrint = $photoCount > 0 ? $photoThumbHtml : '
                <div class="project-photo-trigger empty-thumbnail" data-project-id="' . $project->id . '" data-photos="[]" style="position:relative; width:52px; height:52px; border-radius:8px; cursor:pointer; margin-left: 12px; border: 2px dashed #94a3b8; background: transparent; flex-shrink: 0; display: flex; justify-content: center; align-items: center; color: #94a3b8;">
                    <i class="fas fa-image" style="font-size: 20px;"></i>
                </div>';
                
            print '<div style="display: flex; align-items: flex-start; gap: 6px;">';
                print $thumbToPrint;
            print '</div>';
            
            // Bottom Right: Fast Action Buttons
            print '<div style="display: flex; gap: 8px; justify-content: flex-end; width: 100%; margin-top: 4px;">';
                print '<button type="button" class="fast-trigger-camera" data-project-id="' . $project->id . '" style="width: 48px; height: 48px; border-radius: 10px; border: none; background: #f39c12; color: white; cursor: pointer; display: flex; justify-content: center; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"><i class="fas fa-camera" style="font-size: 24px;"></i></button>';

                print '<label for="inline-upload-' . $project->id . '" style="width: 48px; height: 48px; border-radius: 10px; border: none; background: #3b82f6; color: white; cursor: pointer; display: flex; justify-content: center; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin: 0;">';
                    print '<i class="fas fa-upload" style="font-size: 24px;"></i>';
                    print '<input type="file" id="inline-upload-' . $project->id . '" class="inline-generic-upload" data-project-id="' . $project->id . '" name="userfile[]" multiple style="display: none;">';
                print '</label>';
            print '</div>';
            
        print '</div>'; // End Right Column
            
    print '</div>'; // End Body Row
    print '</div>'; // End Card
}
print '</div>';
?>

<!-- PROJECT GALLERY MODAL -->
<style>
.gallery-modal-content {
    width: 100%; max-width: 800px; max-height: 95vh; background: #ffffff; border-radius: 12px; padding: 20px; display: flex; flex-direction: column; box-shadow: 0 10px 25px rgba(0,0,0,0.5); margin: 0 auto; box-sizing: border-box;
}
.gallery-toolbar {
    margin-top: 15px; display:flex; flex-wrap: wrap; padding-bottom: 5px; justify-content: center; align-items: center; gap: 10px;
}
@media (max-width: 600px) {
    .gallery-modal-content {
        padding: 10px;
        border-radius: 8px;
        max-height: 98vh;
    }
    .gallery-toolbar button {
        width: 38px !important;
        height: 38px !important;
    }
    #gallery-prev-btn, #gallery-next-btn {
        width: 44px !important;
        height: 44px !important;
        font-size: 1.4em !important;
    }
}
</style>
<div id="project-gallery-modal" style="display:none; position:fixed; z-index:10000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.85); align-items:center; justify-content:center; padding: 10px; box-sizing: border-box;">
    
    <div class="gallery-modal-content" onclick="event.stopPropagation();">
        
        <!-- Header -->
        <div style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
            <div style="display:flex; align-items: center;">
                <i class="fas fa-camera-retro" style="color: #f39c12; margin-right: 8px; font-size: 1.2em;"></i>
                <h3 style="margin: 0; font-size: 1.1em; color: #333; font-weight: 600;">Consulter les photos</h3>
            </div>
            <div id="gallery-counter-text" style="color: #64748b; font-weight: bold; font-family: sans-serif; font-size: 0.9em; background: #f1f5f9; padding: 4px 10px; border-radius: 12px;"></div>
        </div>

        <!-- Image Container -->
        <div style="flex:1; display:flex; justify-content:center; align-items:center; overflow:hidden; background:#1e293b; border-radius: 8px; position:relative; min-height: 300px; width: 100%; height: 100%;">
            <button id="gallery-prev-btn" style="position:absolute; left:10px; padding: 0; background:rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2); color:white; font-size:1.4em; cursor:pointer; opacity:1; transition: all 0.2s; border-radius: 50%; z-index: 10; width: 44px; height: 44px; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.4);">&#10094;</button>
            
            <img id="gallery-main-img" src="" style="max-width:100%; max-height:100%; object-fit:contain; border-radius: 8px; box-shadow: 0 5px 25px rgba(0,0,0,0.5); transition: opacity 0.2s;" />
            
            <button id="gallery-next-btn" style="position:absolute; right:10px; padding: 0; background:rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); border: 1px solid rgba(255,255,255,0.2); color:white; font-size:1.4em; cursor:pointer; opacity:1; transition: all 0.2s; border-radius: 50%; z-index: 10; width: 44px; height: 44px; display: flex; justify-content: center; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.4);">&#10095;</button>
        </div>
        
        <!-- Unified Toolbar -->
        <div class="gallery-toolbar">
            <button id="gallery-add-photo-btn" title="Ajouter une nouvelle photo" style="flex-shrink: 0; background:#f39c12; color:white; border:none; border-radius:8px; width:44px; height:44px; cursor:pointer; font-size:1.2em; display:flex; justify-content:center; align-items:center; box-shadow: 0 4px 6px rgba(0,0,0,0.15); transition: transform 0.2s;">
                <i class="fas fa-camera"></i>
            </button>
            <button id="gallery-edit-photo-btn" title="Annoter cette photo" style="flex-shrink: 0; background:#2ecc71; color:white; border:none; border-radius:8px; width:44px; height:44px; cursor:pointer; font-size:1.2em; display:flex; justify-content:center; align-items:center; box-shadow: 0 4px 6px rgba(0,0,0,0.15); transition: transform 0.2s;">
                <i class="fas fa-pencil-alt"></i>
            </button>
            <div style="flex-grow: 1;"></div>
            <button id="gallery-close-btn" title="Fermer" style="flex-shrink: 0; background:#e74c3c; color:white; border:none; border-radius:8px; width:44px; height:44px; cursor:pointer; font-size:1.2em; display:flex; justify-content:center; align-items:center; box-shadow: 0 4px 6px rgba(0,0,0,0.15); transition: transform 0.2s;">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
    </div>
</div>

<style>
.iti-container-fluid .iti {
    width: 100%;
    display: block;
}
.fast-css-tooltip::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(15, 23, 42, 0.75);
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

    // Inline percent editing
    $('.inline-edit-percent').on('click', function() {
        if ($(this).find('input').length > 0) return;
        let span = $(this);
        let currentVal = span.data('val');
        let projId = span.data('project-id');
        let input = $('<input type="number" min="0" max="100" class="percent-input" style="width: 25px; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0; font-weight: 600; font-size: 1em; color: #0f172a; outline: none; box-sizing: border-box; background: transparent; margin: 0; display: inline-block; vertical-align: middle; line-height: normal; -moz-appearance: textfield;" value="'+currentVal+'">');
        
        if ($('#css-no-spinners').length === 0) {
            $('head').append('<style id="css-no-spinners">input[type="number"].percent-input::-webkit-outer-spin-button, input[type="number"].percent-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }</style>');
        }
        
        span.html('').append(input).append('<span style="font-weight:normal; margin-left: 2px;">%</span>');
        input.focus();
        
        let submitValue = function() {
            let newVal = parseInt(input.val());
            if (isNaN(newVal) || newVal < 0) newVal = 0;
            if (newVal > 100) newVal = 100;
            
            if (newVal === currentVal) {
                span.html(originalText);
                return;
            }
            
            span.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6; line-height: 22px;"></i>');
            
            let token = document.querySelector('input[name="token"]') ? document.querySelector('input[name="token"]').value : '';
            
            $.ajax({
                url: document.URL.split('?')[0] + '?action=updateopppercent&token=' + token,
                type: 'POST',
                data: { projectid: projId, percent: newVal },
                success: function(res) {
                    if(res && res.success) {
                        if (res.action_error) {
                            console.warn("L'ActionComm n'a pas pu être inséré:", res.action_error);
                        }
                        span.data('val', newVal);
                        span.html(newVal + ' %');
                        span.css({color: '#2ecc71'});
                        setTimeout(() => span.css({color: '#0f172a'}), 1500);
                    } else {
                        console.error("Erreur serveur lors de la mise à jour :", res);
                        span.html(originalText);
                        span.css({color: '#e74c3c'});
                        setTimeout(() => span.css({color: '#0f172a'}), 1500);
                    }
                },
                error: function(err) {
                    console.error("Erreur AJAX :", err);
                    span.html(originalText);
                    span.css({color: '#e74c3c'});
                    setTimeout(() => span.css({color: '#0f172a'}), 1500);
                }
            });
        };
        
        input.on('blur', submitValue);
        input.on('keypress', function(e) {
            if (e.which === 13) {
                input.off('blur', submitValue);
                submitValue();
            }
        });
    });

    // Inline amount editing
    $('.inline-edit-amount').on('click', function() {
        if ($(this).find('input').length > 0) return;
        let span = $(this);
        let currentVal = parseFloat(span.data('val'));
        let projId = span.data('project-id');
        let originalText = span.text();
        
        let inputWidth = originalText.length > 8 ? '80px' : '55px';
        let input = $('<input type="text" class="amount-input" style="width: '+inputWidth+'; text-align: center; border: 1px solid #cbd5e1; border-radius: 4px; padding: 0 2px; font-weight: 600; font-size: 1em; color: #3b82f6; outline: none; box-sizing: border-box; background: transparent; margin: 0; display: inline-block; vertical-align: middle; line-height: normal; -moz-appearance: textfield;" value="'+currentVal+'">');
        
        span.html('').append(input).append('<span style="font-weight:normal; margin-left: 4px;">€</span>');
        input.focus();
        
        let submitValueAmount = function() {
            let userStr = input.val().replace(',', '.');
            let newVal = parseFloat(userStr);
            if (isNaN(newVal) || newVal < 0) newVal = 0;
            
            if (newVal === currentVal) {
                span.html(originalText);
                return;
            }
            
            span.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6; line-height: 22px;"></i>');
            
            let token = document.querySelector('input[name="token"]') ? document.querySelector('input[name="token"]').value : '';
            
            $.ajax({
                url: document.URL.split('?')[0] + '?action=updateoppamount&token=' + token,
                type: 'POST',
                data: { projectid: projId, amount: newVal },
                success: function(res) {
                    if(res && res.success) {
                        if (res.action_error) {
                            console.warn("L'ActionComm n'a pas pu être inséré:", res.action_error);
                        }
                        span.data('val', newVal);
                        span.html(res.formatted_amount);
                        span.css({color: '#2ecc71'});
                        setTimeout(() => span.css({color: '#3b82f6'}), 1500);
                    } else {
                        console.error("Erreur serveur :", res);
                        alert("Erreur lors de la sauvegarde : " + (res.error ? JSON.stringify(res.error) : "Inconnue"));
                        span.html(originalText);
                        span.css({color: '#e74c3c'});
                        setTimeout(() => span.css({color: '#3b82f6'}), 1500);
                    }
                },
                error: function(err) {
                    console.error("Erreur AJAX :", err);
                    alert("Erreur réseau");
                    span.html(originalText);
                    span.css({color: '#e74c3c'});
                    setTimeout(() => span.css({color: '#3b82f6'}), 1500);
                }
            });
        };
        
        input.on('blur', submitValueAmount);
        input.on('keypress', function(e) {
            if (e.which === 13) {
                input.off('blur', submitValueAmount);
                submitValueAmount();
            }
        });
    });

    // Inline title editing
    $('.inline-edit-title').on('click', function() {
        if ($(this).find('input').length > 0) return;
        let span = $(this);
        let currentVal = span.data('val');
        let projId = span.data('project-id');
        let originalText = span.text();
        
        let minWidth = '100%';
        let input = $('<input type="text" class="title-input" style="width: '+minWidth+'; max-width: 100%; border: 1px solid #3b82f6; border-radius: 4px; padding: 2px 6px; font-weight: inherit; font-size: inherit; color: #0f172a; outline: none; box-sizing: border-box; background: white; margin: 0; display: block; line-height: normal; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);" value="'+currentVal+'">');
        
        span.html('').append(input);
        input.focus();
        
        input.select();
        
        let submitValueTitle = function() {
            let newVal = input.val().trim();
            if (newVal === '') newVal = "Nouvelle opportunité";
            
            if (newVal === currentVal) {
                span.html(originalText);
                return;
            }
            
            span.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6;"></i>');
            
            let token = document.querySelector('input[name="token"]') ? document.querySelector('input[name="token"]').value : '';
            
            $.ajax({
                url: document.URL.split('?')[0] + '?action=updateopptitle&token=' + token,
                type: 'POST',
                data: { projectid: projId, title: newVal },
                success: function(res) {
                    if(res && res.success) {
                        span.data('val', newVal);
                        span.html(res.escaped_title);
                        span.css({color: '#2ecc71'});
                        setTimeout(() => span.css({color: '#4a5568'}), 1500);
                    } else {
                        console.error("Erreur serveur :", res);
                        alert("Erreur lors de la sauvegarde : " + (res.error ? JSON.stringify(res.error) : "Inconnue"));
                        span.html(originalText);
                        span.css({color: '#e74c3c'});
                        setTimeout(() => span.css({color: '#4a5568'}), 1500);
                    }
                },
                error: function(err) {
                    console.error("Erreur AJAX :", err);
                    alert("Erreur réseau");
                    span.html(originalText);
                    span.css({color: '#e74c3c'});
                    setTimeout(() => span.css({color: '#4a5568'}), 1500);
                }
            });
        };
        
        input.on('blur', submitValueTitle);
        input.on('keypress', function(e) {
            // Prevent fast-css-tooltip from reacting strangely
            e.stopPropagation();
            if (e.which === 13) {
                input.off('blur', submitValueTitle);
                submitValueTitle();
            }
        });
        
        input.on('click', function(e) { e.stopPropagation(); });
    });

    // Gallery Logic
    window.galleryImages = [];
    window.galleryCurrentIndex = 0;
    
    function updateGalleryView() {
        if (!window.galleryImages || window.galleryImages.length === 0) return;
        $('#gallery-main-img').attr('src', window.galleryImages[window.galleryCurrentIndex]);
        $('#gallery-counter-text').text((window.galleryCurrentIndex + 1) + ' / ' + window.galleryImages.length);
        
        if (window.galleryImages.length <= 1) {
            $('#gallery-prev-btn, #gallery-next-btn').hide();
        } else {
            $('#gallery-prev-btn, #gallery-next-btn').show();
        }
    }

    $('.project-photo-trigger').on('click', function(e) {
        e.stopPropagation();
        window.galleryImages = $(this).data('photos');
        window.editingExistingProjectId = $(this).data('project-id');
        window.galleryCurrentIndex = 0;
        
        if (window.galleryImages && window.galleryImages.length > 0) {
            updateGalleryView();
            $('#project-gallery-modal').css('display', 'flex');
        }
    });

    $('#gallery-prev-btn').on('click', function(e) {
        e.stopPropagation();
        window.galleryCurrentIndex = (window.galleryCurrentIndex - 1 + window.galleryImages.length) % window.galleryImages.length;
        updateGalleryView();
    });

    $('#gallery-next-btn').on('click', function(e) {
        e.stopPropagation();
        window.galleryCurrentIndex = (window.galleryCurrentIndex + 1) % window.galleryImages.length;
        updateGalleryView();
    });

    function closeGalleryModal() {
        $('#project-gallery-modal').hide();
    }

    $('#gallery-close-btn').on('click', function(e) {
        e.stopPropagation();
        closeGalleryModal();
        window.editingExistingProjectId = null;
    });

    $('#gallery-edit-photo-btn').on('click', function(e) {
        e.stopPropagation();
        if (!window.galleryImages || window.galleryImages.length === 0) return;
        const imageUrl = window.galleryImages[window.galleryCurrentIndex];
        
        closeGalleryModal();
        if (typeof window.openPhotoEditorWithUrl === 'function') {
            window.openPhotoEditorWithUrl(imageUrl);
        }
    });

    $('#project-gallery-modal').on('click', function(e) {
        if (e.target.id === 'project-gallery-modal') {
            closeGalleryModal();
            window.editingExistingProjectId = null;
        }
    });

    $(document).on('keydown', function(e) {
        if ($('#project-gallery-modal').is(':visible')) {
            if (e.key === 'Escape') {
                closeGalleryModal();
                window.editingExistingProjectId = null;
            } else if (e.key === 'ArrowRight') {
                $('#gallery-next-btn').click();
            } else if (e.key === 'ArrowLeft') {
                $('#gallery-prev-btn').click();
            }
        }
    });

    $('#gallery-add-photo-btn').on('click', function(e) {
        e.stopPropagation();
        closeGalleryModal();
        if ($('#upload-photo').length > 0) {
            $('#upload-photo').click();
        } else {
            console.error("Le bouton d'ajout de photo global est introuvable sur cette page.");
        }
    });


    // Fast Action Media Interceptors
    $('.fast-trigger-camera').on('click', function(e) {
        e.stopPropagation();
        window.editingExistingProjectId = $(this).data('project-id');
        if ($('#upload-photo').length > 0) {
            $('#upload-photo').click();
        } else {
            console.error("Le composant caméra global est manquant.");
        }
    });

    $('.inline-generic-upload').on('change', function(e) {
        let projectId = $(this).data('project-id');
        let files = e.target.files;
        if (files.length === 0) return;
        
        let label = $('label[for="inline-upload-' + projectId + '"]');
        let originalHtml = label.html();
        label.html('<i class="fas fa-spinner fa-spin" style="font-size:14px;"></i>');
        
        let formData = new FormData();
        formData.append('projectid', projectId);
        for (let i = 0; i < files.length; i++) {
            formData.append('userfile[]', files[i]);
        }
        
        let token = document.querySelector('input[name="token"]') ? document.querySelector('input[name="token"]').value : '';
        
        $.ajax({
            url: document.URL.split('?')[0] + '?action=add_file_existing&token=' + token,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                window.location.reload();
            },
            error: function() {
                label.html('<i class="fas fa-times" style="font-size:14px; color:#fff;"></i>').css('background-color', '#e74c3c');
                setTimeout(() => { label.html(originalHtml).css('background-color', '#3b82f6'); }, 2000);
            }
        });
    });
});
</script>
<?php

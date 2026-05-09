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

print '<input type="hidden" name="token" value="' . newToken() . '">';

print '<div class="title" style="color: #5a7b97; font-size: 0.95em; font-weight: bold; margin-bottom: 15px; padding-left: 20px;">' . $listTitle . '</div>';

print '<style>
.opp-media-row .linked-medias.medias { width: auto !important; }
</style>';

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
            // Saturne Media Block
            print '<div class="opp-media-row" style="margin-top: 8px; width: 100%; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">';
            require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
            print saturne_render_media_block('project', dol_sanitizeFileName($project->ref), 'opp_' . $project->id, '', ['show_photo' => true, 'show_audio' => true]);
            print '</div>';
            
        print '</div>'; // End Left Column
            
    print '</div>'; // End Body Row
    print '</div>'; // End Card
}
print '</div>';
?>

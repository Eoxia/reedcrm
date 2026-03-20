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
        $audioPlayerHtml = '<div style="margin-top: 12px;"><audio class="minimal-audio" controls controlslist="nodownload noplaybackrate" preload="metadata" style="height: 35px; width: 175px; outline: none; border-radius: 20px;"><source src="' . dol_escape_htmltag($fileUrl) . '" type="audio/wav"></audio></div>';
    }

    // Probability and amount
    $percent   = $project->opp_percent ? $project->opp_percent . ' %' : '0.00 %';
    $amount    = $project->opp_amount ? price($project->opp_amount, 0, '', 11, -1, -1, 'auto') : '0 €';
    
    $url       = DOL_URL_ROOT . '/projet/card.php?id=' . $project->id;
    
    // Ensure some styling for the cards to match image
    print '<div class="card" style="border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px; margin-bottom: 10px; background-color: #f8fbff; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">';
    
    // Flex container for horizontal layout
    print '<div style="display: flex; justify-content: space-between;">';
    
    // Left column
    print '<div style="flex: 1; padding-right: 10px;">';
    
    print '<div style="display: flex; align-items: center; margin-bottom: 5px; flex-wrap: wrap; gap: 6px;">';
    print '<div style="color: #004b87; font-weight: 600; font-size: 1.1em;">' . $ref . '</div>';
    
    print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;">&bull;</span>';
    print '<div style="font-size: 0.85em; color: #718096;"><i class="far fa-calendar-alt" style="margin-right: 4px;"></i>' . $creationDate . '</div>';
    
    if (!empty($userInitials)) {
        print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;">&bull;</span>';
        print '<div title="' . dol_escape_htmltag($author->getFullName($langs)) . '" style="font-size: 0.7em; color: #fff; background: #9b59b6; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; letter-spacing: 0.5px;">' . $userInitials . '</div>';
    }
    print '</div>';
    
    if (!empty($title)) {
        print '<div style="color: #4a5568; margin-bottom: 8px; font-size: 0.95em;">' . $title . '</div>';
    }
    
    // Contact details
    print '<div style="color: #718096; font-size: 0.9em; line-height: 1.5;">';
    $contactName = trim($firstname . ' ' . $lastname);
    if (!empty($contactName)) {
        print $contactName;
        if (!empty($phone)) {
            print ' <span style="color: #cbd5e0; margin: 0 5px;">&bull;</span> ' . $phone;
        }
        print '<br>';
    }
    if (!empty($email)) {
        print '<div style="color: #4a5568; margin-top: 2px;">' . $email . '</div>';
    }
    print '</div>';
    
    if (!empty($audioPlayerHtml)) {
        print $audioPlayerHtml;
    }
    print '</div>';
    
    // Right column
    print '<div style="display: flex; flex-direction: column; justify-content: flex-end; align-items: flex-end; min-width: 80px;">';
    
    // Only show percent and amount if they are set, to match the layout
    if ($project->opp_percent !== null || $project->opp_amount !== null) {
        print '<div style="font-weight: 600; color: #2d3748; margin-bottom: 2px;">' . $percent . '</div>';
        print '<div style="color: #007bff; margin-bottom: 8px;">' . $amount . '</div>';
    }
    
    print '<div style="display: flex; align-items: center; margin-top: auto;">';
    // Status dot (Green for example)
    print '<div style="width: 8px; height: 8px; border-radius: 50%; background-color: #38c172; margin-right: 8px;"></div>';
    // External link icon
    print '<a href="' . $url . '" target="_blank" style="color: #3490dc; font-size: 1.2em; line-height: 1;">';
    print '<i class="fas fa-external-link-alt"></i>';
    print '</a>';
    print '</div>';
    
    print '</div>';
    
    print '</div>'; // End flex container
    print '</div>'; // End Card
}

print '</div>';

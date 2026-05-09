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
        // --- ROW 1: Ref, Date, Initials (left) | Financials 50%/€ (right) ---
    print '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 6px;">';

        // Left: ref + date + initials
        print '<div style="display: flex; align-items: center; flex-wrap: wrap; gap: 6px;">';
            print '<div style="font-weight: 600; font-size: 1.1em;">' . $ref . '</div>';
            print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 2px;">&bull;</span>';
            print '<div style="font-size: 0.85em; color: #718096;"><i class="far fa-calendar-alt" style="margin-right: 4px;"></i>' . $creationDate . '</div>';
            if (!empty($userInitials)) {
                print '<span style="color: #cbd5e0; font-size: 0.8em; margin: 0 4px;">&bull;</span>';
                print '<div title="' . dol_escape_htmltag($author->getFullName($langs)) . '" style="font-size: 0.7em; color: #fff; background: #9b59b6; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;">' . $userInitials . '</div>';
            }
        print '</div>';

        // Right: financials only (status dot + % + amount)
        $rawAmount = empty($project->opp_amount) ? 0 : (float)$project->opp_amount;
        $statVal   = isset($project->status) ? $project->status : (isset($project->statut) ? $project->statut : (isset($project->fk_statut) ? $project->fk_statut : 1));
        print '<div style="display: flex; align-items: center; gap: 6px; font-weight: 600; font-size: 0.95em; flex-shrink: 0;">';
            if ($statVal == 1) {
                print '<div style="width:10px;height:10px;background:#2ecc71;border-radius:50%;flex-shrink:0;" title="Ouvert"></div>';
            } elseif ($statVal == 0) {
                print '<div style="width:10px;height:10px;background:#fff;border:2px solid #e74c3c;border-radius:50%;flex-shrink:0;" title="Brouillon"></div>';
            } else {
                print '<div style="width:10px;height:10px;background:#95a5a6;border-radius:50%;flex-shrink:0;" title="Clôturé"></div>';
            }
            print '<span class="inline-edit-percent" data-project-id="'.$project->id.'" data-val="'.(int)$project->opp_percent.'" style="color:#0f172a;cursor:pointer;border-bottom:1px dashed #cbd5e0;padding-bottom:1px;white-space:nowrap;" title="Modifier la probabilité">' . $percent . '</span>';
            print '<span style="color:#cbd5e0;margin:0 2px;">-</span>';
            print '<span class="inline-edit-amount" data-project-id="'.$project->id.'" data-val="'.$rawAmount.'" style="color:#3b82f6;cursor:pointer;border-bottom:1px dashed #cbd5e0;padding-bottom:1px;white-space:nowrap;" title="Modifier le montant">' . $amount . '</span>';
        print '</div>';

    print '</div>'; // End Row 1

    // --- ROW 2: Libellé (inline-editable) + Contact details ---
    print '<div style="display: flex; flex-direction: column; gap: 6px; margin-bottom: 6px;">';

        // Libellé (titre du projet, modifiable inline)
        if (!empty($title)) {
            $descParts = [];
            if (!empty($project->description)) $descParts[] = trim(dol_string_nohtmltag($project->description, 1));
            if (!empty($project->note_public))  $descParts[] = trim(dol_string_nohtmltag($project->note_public, 1));
            if (!empty($project->note_private)) $descParts[] = trim(dol_string_nohtmltag($project->note_private, 1));
            $descClean = !empty($descParts) ? implode(" \n---\n ", $descParts) : '(Aucune description / note)';

            print '<div class="fast-css-tooltip" data-tooltip="' . dol_escape_htmltag($descClean) . '" style="color:#4a5568;font-size:0.95em;display:flex;align-items:center;overflow:hidden;">';
                print '<span style="color:#64748b;font-weight:500;margin-right:6px;white-space:nowrap;flex-shrink:0;">Libellé :</span>';
                print '<span class="inline-edit-proj-title" data-project-id="' . $project->id . '" data-val="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;border-bottom:1px dashed #cbd5e0;line-height:1;padding-bottom:1px;cursor:pointer;display:block;flex:1;min-width:0;font-weight:600;color:#1e293b;" title="Modifier le titre">' . dol_escape_htmltag($title) . '</span>';
            print '</div>';
        }

        // Contact details (firstname, lastname, phone, email, web)
        $cFirstName = trim($firstname);
        $cLastName  = trim($lastname);
        $cPhone     = trim($phone);
        $cEmail     = trim($email);
        $cWeb       = isset($project->array_options['options_reedcrm_website']) ? trim($project->array_options['options_reedcrm_website']) : '';

        $hFirstName = $cFirstName ? dol_escape_htmltag($cFirstName) : '<span style="color:#cbd5e0;font-style:italic;">Prénom</span>';
        $hLastName  = $cLastName  ? dol_escape_htmltag($cLastName)  : '<span style="color:#cbd5e0;font-style:italic;">Nom</span>';
        $hPhone     = $cPhone     ? dol_escape_htmltag($cPhone)     : '<span style="color:#cbd5e0;font-style:italic;">Téléphone</span>';
        $hEmail     = $cEmail     ? dol_escape_htmltag($cEmail)     : '<span style="color:#cbd5e0;font-style:italic;">Email</span>';
        $hWeb       = $cWeb       ? dol_escape_htmltag($cWeb)       : '<span style="color:#cbd5e0;font-style:italic;">Site Web</span>';

        $linkPhone = $cPhone ? '<a href="tel:'.dol_escape_htmltag($cPhone).'" class="prevent-edit-click" style="color:inherit;text-decoration:none;"><i class="fas fa-phone" style="color:#64748b;margin-right:6px;cursor:pointer;"></i></a>' : '<i class="fas fa-phone" style="color:#64748b;margin-right:6px;"></i>';
        $linkEmail = $cEmail ? '<a href="mailto:'.dol_escape_htmltag($cEmail).'" class="prevent-edit-click" style="color:inherit;text-decoration:none;"><i class="fas fa-envelope" style="color:#64748b;margin-right:6px;cursor:pointer;"></i></a>' : '<i class="fas fa-envelope" style="color:#64748b;margin-right:6px;"></i>';
        $webHref   = strpos($cWeb, 'http') === 0 ? $cWeb : 'https://' . $cWeb;
        $linkWeb   = $cWeb ? '<a href="'.dol_escape_htmltag($webHref).'" target="_blank" class="prevent-edit-click" style="color:inherit;text-decoration:none;"><i class="fas fa-globe" style="color:#64748b;margin-right:6px;cursor:pointer;"></i></a>' : '<i class="fas fa-globe" style="color:#64748b;margin-right:6px;"></i>';

        print '<div class="contact-inline-wrapper" style="color:#718096;font-size:0.9em;position:relative;" data-project-id="'.$project->id.'">';
            print '<div class="contact-display-area" style="display:flex;align-items:center;flex-wrap:wrap;gap:0;padding:2px 0;">';
                print '<i class="fas fa-address-book" style="color:#64748b;font-size:1.1em;margin-right:6px;"></i>';
                print '<span class="inline-edit-contact" data-field="firstname" data-val="'.dol_escape_htmltag($cFirstName).'" style="cursor:pointer;border-bottom:1px dashed #cbd5e0;line-height:1;padding-bottom:1px;margin-right:4px;" title="Modifier le prénom">' . $hFirstName . '</span>';
                print '<span class="inline-edit-contact" data-field="lastname"  data-val="'.dol_escape_htmltag($cLastName). '" style="cursor:pointer;border-bottom:1px dashed #cbd5e0;line-height:1;padding-bottom:1px;margin-right:8px;" title="Modifier le nom">'     . $hLastName  . '</span>';
                print '<span style="color:#cbd5e0;margin-right:8px;">&bull;</span>';
                print $linkPhone;
                print '<span class="inline-edit-contact" data-field="phone"     data-val="'.dol_escape_htmltag($cPhone).    '" style="cursor:pointer;border-bottom:1px dashed #cbd5e0;line-height:1;padding-bottom:1px;margin-right:8px;" title="Modifier le téléphone">' . $hPhone     . '</span>';
                print '<span style="color:#cbd5e0;margin-right:8px;">&bull;</span>';
                print $linkEmail;
                print '<span class="inline-edit-contact" data-field="email"     data-val="'.dol_escape_htmltag($cEmail).    '" style="cursor:pointer;border-bottom:1px dashed #cbd5e0;line-height:1;padding-bottom:1px;margin-right:8px;" title="Modifier l\'email">'     . $hEmail     . '</span>';
                print '<span style="color:#cbd5e0;margin-right:8px;">&bull;</span>';
                print $linkWeb;
                print '<span class="inline-edit-contact" data-field="website"   data-val="'.dol_escape_htmltag($cWeb).      '" style="cursor:pointer;border-bottom:1px dashed #cbd5e0;line-height:1;padding-bottom:1px;word-break:break-all;" title="Modifier le site web">' . $hWeb        . '</span>';
            print '</div>';
        print '</div>';

    print '</div>'; // End Row 2

    // --- ROW MEDIA: Action buttons aligned on their own row ---
    print '<div class="opp-media-row" style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-bottom:6px;">';
        require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
        print saturne_render_media_block('project', dol_sanitizeFileName($project->ref), 'opp_' . $project->id, '', ['show_photo' => true, 'show_audio' => true]);
    print '</div>';

    // --- Separator before selectors row ---
    print '<hr style="border-top:1px dashed #cbd5e0;border-bottom:none;border-left:none;border-right:none;margin:4px 0 6px 0;width:100%;">';

    // --- ROW 3: Client selector | Contact selector | Propal € | Invoice € ---
    print '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;width:100%;">';

        $tiersId = !empty($project->socid) ? $project->socid : (!empty($project->fk_soc) ? $project->fk_soc : 0);

        print '<div class="reedcrm-pwa-selectors-group" style="display:flex;gap:10px;flex-wrap:wrap;position:relative;">';

        // Client selector button
        if ($tiersId > 0) {
            $soc = new Societe($db);
            if ($soc->fetch($tiersId) > 0) {
                print '<div class="pwa-client-selector" data-project-id="'.$project->id.'" style="background:#fff;border:1px solid #cbd5e0;border-radius:4px;padding:4px 8px;color:#475569;font-size:0.85em;display:flex;align-items:center;gap:6px;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.05);" title="Changer le tiers">';
                    print (method_exists($soc, 'getLibStatut') ? $soc->getLibStatut(3) . ' ' : '');
                    print '<i class="far fa-building" style="color:#64748b;"></i>';
                    print '<span style="font-weight:500;">' . dol_escape_htmltag($soc->name) . '</span>';
                    print '<i class="fas fa-chevron-down" style="color:#94a3b8;font-size:0.8em;margin-left:4px;"></i>';
                print '</div>';
            }
        } else {
            print '<div class="pwa-client-selector" data-project-id="'.$project->id.'" style="background:#fff;border:1px dashed #cbd5e0;border-radius:4px;padding:4px 8px;color:#94a3b8;font-size:0.85em;display:flex;align-items:center;gap:6px;cursor:pointer;" title="Associer un tiers">';
                print '<i class="far fa-building"></i>';
                print '<span style="font-style:italic;">Client</span>';
                print '<i class="fas fa-chevron-down" style="font-size:0.8em;margin-left:4px;"></i>';
            print '</div>';
        }

        // Contact selector button
        print '<div class="pwa-contact-selector" data-project-id="'.$project->id.'" style="background:#fff;border:1px solid #cbd5e0;border-radius:4px;padding:4px 8px;color:#475569;font-size:0.85em;display:flex;align-items:center;gap:6px;cursor:pointer;box-shadow:0 1px 2px rgba(0,0,0,0.05);" title="Changer le contact">';
            print '<i class="far fa-address-book" style="color:#64748b;"></i>';
            print '<span style="font-weight:500;">Contact</span>';
            print '<i class="fas fa-chevron-down" style="color:#94a3b8;font-size:0.8em;margin-left:4px;"></i>';
        print '</div>';

        // Hidden placeholder for contact data-tiers-id
        print '<div class="reedcrm-hidden-contact-selector-wrap" id="reedcrm-hidden-contact-selector-pwa-'.$project->id.'" style="display:none;" data-tiers-id="'.$tiersId.'"></div>';

        print '</div>'; // End selectors group

        // Propal amounts
        $sql_propal   = "SELECT SUM(total_ht) as amount FROM " . MAIN_DB_PREFIX . "propal WHERE fk_projet = " . (int)$project->id . " AND fk_statut IN (1, 2)";
        $res_propal   = $db->query($sql_propal);
        $obj_propal   = $db->fetch_object($res_propal);
        $propal_amount  = $obj_propal && $obj_propal->amount ? $obj_propal->amount : 0;

        $sql_facture  = "SELECT SUM(total_ht) as amount FROM " . MAIN_DB_PREFIX . "facture WHERE fk_projet = " . (int)$project->id . " AND fk_statut IN (0, 1)";
        $res_facture  = $db->query($sql_facture);
        $obj_facture  = $db->fetch_object($res_facture);
        $facture_amount = $obj_facture && $obj_facture->amount ? $obj_facture->amount : 0;

        $urlPropal  = DOL_URL_ROOT . '/comm/propal/list.php?search_projet=' . $project->id;
        $urlFacture = DOL_URL_ROOT . '/compta/facture/list.php?search_projet=' . $project->id;

        print '<a href="' . $urlPropal . '" style="display:flex;align-items:center;gap:6px;text-decoration:none;color:#475569;font-size:0.95em;font-weight:600;margin-left:8px;" title="Devis en cours">';
        print '<i class="fas fa-file-signature" style="color:#38a169;font-size:1.1em;"></i> ' . price($propal_amount, 0, '', 11, -1, -1, 'auto');
        print '</a>';

        print '<a href="' . $urlFacture . '" style="display:flex;align-items:center;gap:6px;text-decoration:none;color:#475569;font-size:0.95em;font-weight:600;margin-left:8px;" title="Factures en cours">';
        print '<i class="fas fa-file-invoice-dollar" style="color:#38a169;font-size:1.1em;"></i> ' . price($facture_amount, 0, '', 11, -1, -1, 'auto');
        print '</a>';

    print '</div>'; // End Row 3

    print '</div>'; // End project-history-card
}
print '</div>'; // End page-content

// Shared hidden overlay for AJAX selectors (pwa_selectors module)
print '<div id="reedcrm-hidden-company-selector-pwa" style="display:none;"></div>';
?>

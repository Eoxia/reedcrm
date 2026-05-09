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
 * \brief   Template for the latest opportunities list on the PWA frontend.
 *
 * Required vars: $conf, $langs, $db, $latestProjects
 */

require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
?>

<input type="hidden" name="token" value="<?php echo newToken(); ?>">

<style>
    .opp-media-row .linked-medias.medias { width: auto !important; }
    .pwa-card { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 10px; margin: 0 0 10px 0 !important; background: #f8fbff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .pwa-card-row1 { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
    .pwa-card-row1-left { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
    .pwa-card-row1-right { display: flex; align-items: center; gap: 6px; font-weight: 600; font-size: 0.95em; flex-shrink: 0; }
    .pwa-card-row2 { display: flex; flex-direction: column; gap: 5px; margin-bottom: 6px; }
    .pwa-card-libelle { display: flex; align-items: center; overflow: hidden; font-size: 0.95em; }
    .pwa-card-libelle-label { color: #64748b; font-weight: 500; margin-right: 6px; white-space: nowrap; flex-shrink: 0; }
    .inline-edit-proj-title { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; cursor: pointer; display: block; flex: 1; min-width: 0; font-weight: 600; color: #1e293b; }
    .pwa-card-contacts { color: #718096; font-size: 0.9em; display: flex; align-items: center; flex-wrap: wrap; gap: 0; padding: 2px 0; }
    .inline-edit-contact { cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; }
    .pwa-card-media-row { display: flex; align-items: center; justify-content: flex-end; gap: 8px; margin-bottom: 6px; }
    .pwa-card-separator { border-top: 1px dashed #cbd5e0; border-bottom: none; border-left: none; border-right: none; margin: 4px 0 6px 0; }
    .pwa-card-row3 { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .pwa-selectors-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .pwa-client-selector,
    .pwa-contact-selector { display: flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 4px; font-size: 0.85em; cursor: pointer; background: #fff; border: 1px solid #cbd5e0; color: #475569; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    .pwa-client-selector.empty { border-style: dashed; color: #94a3b8; box-shadow: none; }
    .pwa-amount-link { display: flex; align-items: center; gap: 4px; text-decoration: none; color: #475569; font-size: 0.9em; font-weight: 600; }
    .pwa-status-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
    .inline-edit-percent,
    .inline-edit-amount { cursor: pointer; border-bottom: 1px dashed #cbd5e0; padding-bottom: 1px; white-space: nowrap; }
    .inline-edit-percent { color: #0f172a; }
    .inline-edit-amount { color: #3b82f6; }
    .initials-badge { font-size: 0.7em; color: #fff; background: #9b59b6; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
    .dot-sep { color: #cbd5e0; margin: 0 6px; }
</style>

<div class="page-content" style="margin-top: 5px; max-width: 1000px; margin: 5px auto 0 auto;">

    <div class="title" style="color: #5a7b97; font-size: 0.95em; font-weight: bold; margin-bottom: 15px; padding-left: 20px;">
        <?php echo $langs->trans('LatestCreatedOpportunities'); ?>
    </div>

    <?php foreach ($latestProjects as $project) :

        // Load extra fields if needed
        if (empty($project->array_options)) {
            $project->fetch_optionals();
        }

        // --- Basic fields ---
        $ref       = $project->getNomUrl(1);
        $title     = $project->title;
        $lastname  = $project->array_options['options_reedcrm_lastname']  ?? '';
        $firstname = $project->array_options['options_reedcrm_firstname'] ?? '';
        $phone     = $project->array_options['options_projectphone']      ?? '';
        $email     = $project->array_options['options_reedcrm_email']     ?? '';
        $cWeb      = $project->array_options['options_reedcrm_website']   ?? '';

        // --- Creation date ---
        $valDate      = $project->date_c ?? $project->datec ?? $project->tms ?? null;
        $creationDate = $valDate ? dol_print_date($valDate, 'day') : '';

        // --- Creator initials ---
        $userId       = $project->user_author_id ?? $project->fk_user_creat ?? null;
        $userInitials = '';
        $author       = null;
        if (!empty($userId)) {
            $author = new User($db);
            $author->fetch($userId);
            $userInitials = trim(strtoupper(substr($author->firstname, 0, 1) . substr($author->lastname, 0, 1)));
            if (strlen($userInitials) < 2) {
                $fullName = trim($author->firstname . $author->lastname);
                $userInitials = strlen($fullName) >= 2 ? strtoupper(substr($fullName, 0, 2)) : strtoupper(substr($author->login, 0, 2));
            }
        }

        // --- Financials ---
        $rawAmount = (float)($project->opp_amount ?? 0);
        $percent   = $project->opp_percent ? $project->opp_percent . ' %' : '0 %';
        $amount    = $rawAmount ? price($rawAmount, 0, '', 11, -1, -1, 'auto') : '0 €';
        $statVal   = $project->status ?? $project->statut ?? $project->fk_statut ?? 1;

        // Status dot color
        $dotStyle = ($statVal == 1) ? 'background:#2ecc71;' : (($statVal == 0) ? 'background:#fff;border:2px solid #e74c3c;' : 'background:#95a5a6;');
        $dotTitle = ($statVal == 1) ? 'Ouvert' : (($statVal == 0) ? 'Brouillon' : 'Clôturé');

        // --- Contact HTML helpers ---
        $cFirstName = trim($firstname);
        $cLastName  = trim($lastname);
        $cPhone     = trim($phone);
        $cEmail     = trim($email);
        $cWeb       = trim($cWeb);

        $hFirstName = $cFirstName ?: '<span style="color:#cbd5e0;font-style:italic;">Prénom</span>';
        $hLastName  = $cLastName  ?: '<span style="color:#cbd5e0;font-style:italic;">Nom</span>';
        $hPhone     = $cPhone     ?: '<span style="color:#cbd5e0;font-style:italic;">Téléphone</span>';
        $hEmail     = $cEmail     ?: '<span style="color:#cbd5e0;font-style:italic;">Email</span>';
        $hWeb       = $cWeb       ?: '<span style="color:#cbd5e0;font-style:italic;">Site Web</span>';

        $linkPhone = $cPhone ? '<a href="tel:' . dol_escape_htmltag($cPhone) . '" class="prevent-edit-click" style="color:inherit;text-decoration:none;"><i class="fas fa-phone" style="color:#64748b;margin-right:4px;"></i></a>' : '<i class="fas fa-phone" style="color:#cbd5e0;margin-right:4px;"></i>';
        $linkEmail = $cEmail ? '<a href="mailto:' . dol_escape_htmltag($cEmail) . '" class="prevent-edit-click" style="color:inherit;text-decoration:none;"><i class="fas fa-envelope" style="color:#64748b;margin-right:4px;"></i></a>' : '<i class="fas fa-envelope" style="color:#cbd5e0;margin-right:4px;"></i>';
        $webHref   = $cWeb ? (strpos($cWeb, 'http') === 0 ? $cWeb : 'https://' . $cWeb) : '#';
        $linkWeb   = $cWeb ? '<a href="' . dol_escape_htmltag($webHref) . '" target="_blank" class="prevent-edit-click" style="color:inherit;text-decoration:none;"><i class="fas fa-globe" style="color:#64748b;margin-right:4px;"></i></a>' : '<i class="fas fa-globe" style="color:#cbd5e0;margin-right:4px;"></i>';

        // --- Thirdparty ---
        $tiersId  = (int)($project->socid ?? $project->fk_soc ?? 0);
        $socName  = '';
        $socBadge = '';
        if ($tiersId > 0) {
            $soc = new Societe($db);
            if ($soc->fetch($tiersId) > 0) {
                $socName  = dol_escape_htmltag($soc->name);
                $socBadge = method_exists($soc, 'getLibStatut') ? $soc->getLibStatut(3) . ' ' : '';
            }
        }

        // --- Description tooltip ---
        $descParts = [];
        if (!empty($project->description)) $descParts[] = trim(dol_string_nohtmltag($project->description, 1));
        if (!empty($project->note_public))  $descParts[] = trim(dol_string_nohtmltag($project->note_public, 1));
        if (!empty($project->note_private)) $descParts[] = trim(dol_string_nohtmltag($project->note_private, 1));
        $descClean = $descParts ? implode(" \n---\n ", $descParts) : '(Aucune description / note)';

        // --- Propal & Invoice amounts ---
        $resPropal  = $db->query("SELECT SUM(total_ht) as a FROM " . MAIN_DB_PREFIX . "propal   WHERE fk_projet=" . (int)$project->id . " AND fk_statut IN (1,2)");
        $resFacture = $db->query("SELECT SUM(total_ht) as a FROM " . MAIN_DB_PREFIX . "facture  WHERE fk_projet=" . (int)$project->id . " AND fk_statut IN (0,1)");
        $propalAmt  = ($o = $db->fetch_object($resPropal))  ? (float)$o->a : 0;
        $factureAmt = ($o = $db->fetch_object($resFacture)) ? (float)$o->a : 0;
    ?>

    <div class="pwa-card">

        <!-- ROW 1 : ref + date + initiales | ● % - € -->
        <div class="pwa-card-row1">
            <div class="pwa-card-row1-left">
                <div style="font-weight:600;font-size:1.1em;"><?php echo $ref; ?></div>
                <span class="dot-sep">&bull;</span>
                <div style="font-size:0.85em;color:#718096;"><i class="far fa-calendar-alt" style="margin-right:3px;"></i><?php echo $creationDate; ?></div>
                <?php if (!empty($userInitials) && $author) : ?>
                    <span class="dot-sep">&bull;</span>
                    <div class="initials-badge" title="<?php echo dol_escape_htmltag($author->getFullName($langs)); ?>"><?php echo $userInitials; ?></div>
                <?php endif; ?>
            </div>
            <div class="pwa-card-row1-right">
                <div class="pwa-status-dot" style="<?php echo $dotStyle; ?>" title="<?php echo $dotTitle; ?>"></div>
                <span class="inline-edit-percent" data-project-id="<?php echo $project->id; ?>" data-val="<?php echo (int)$project->opp_percent; ?>" title="Modifier la probabilité"><?php echo $percent; ?></span>
                <span style="color:#cbd5e0;margin:0 2px;">-</span>
                <span class="inline-edit-amount"  data-project-id="<?php echo $project->id; ?>" data-val="<?php echo $rawAmount; ?>"           title="Modifier le montant"><?php echo $amount; ?></span>
            </div>
        </div>

        <!-- ROW 2 : libellé (inline-éditable) + contact -->
        <div class="pwa-card-row2">

            <?php if (!empty($title)) : ?>
            <div class="pwa-card-libelle fast-css-tooltip" data-tooltip="<?php echo dol_escape_htmltag($descClean); ?>">
                <span class="pwa-card-libelle-label">Libellé :</span>
                <span class="inline-edit-proj-title"
                      data-project-id="<?php echo $project->id; ?>"
                      data-val="<?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>"
                      title="Modifier le titre"><?php echo dol_escape_htmltag($title); ?></span>
            </div>
            <?php endif; ?>

            <div class="contact-inline-wrapper pwa-card-contacts" data-project-id="<?php echo $project->id; ?>">
                <i class="fas fa-address-book" style="color:#64748b;font-size:1.1em;margin-right:6px;"></i>
                <span class="inline-edit-contact" data-field="firstname" data-val="<?php echo dol_escape_htmltag($cFirstName); ?>" style="margin-right:4px;" title="Modifier le prénom"><?php echo $hFirstName; ?></span>
                <span class="inline-edit-contact" data-field="lastname"  data-val="<?php echo dol_escape_htmltag($cLastName);  ?>" style="margin-right:8px;" title="Modifier le nom"><?php echo $hLastName; ?></span>
                <span class="dot-sep">&bull;</span>
                <?php echo $linkPhone; ?>
                <span class="inline-edit-contact" data-field="phone"   data-val="<?php echo dol_escape_htmltag($cPhone); ?>" style="margin-right:8px;" title="Modifier le téléphone"><?php echo $hPhone; ?></span>
                <span class="dot-sep">&bull;</span>
                <?php echo $linkEmail; ?>
                <span class="inline-edit-contact" data-field="email"   data-val="<?php echo dol_escape_htmltag($cEmail); ?>" style="margin-right:8px;" title="Modifier l'email"><?php echo $hEmail; ?></span>
                <span class="dot-sep">&bull;</span>
                <?php echo $linkWeb; ?>
                <span class="inline-edit-contact" data-field="website" data-val="<?php echo dol_escape_htmltag($cWeb); ?>"   style="word-break:break-all;" title="Modifier le site web"><?php echo $hWeb; ?></span>
            </div>

        </div><!-- /ROW 2 -->

        <!-- ROW MEDIA : boutons alignés à droite -->
        <div class="pwa-card-media-row">
            <?php echo saturne_render_media_block('project', dol_sanitizeFileName($project->ref), 'opp_' . $project->id, '', ['show_photo' => true, 'show_audio' => true]); ?>
        </div>

        <hr class="pwa-card-separator">

        <!-- ROW 3 : sélecteurs client/contact + montants devis/factures -->
        <div class="pwa-card-row3">

            <div class="pwa-selectors-group">

                <!-- Client -->
                <?php if ($tiersId > 0 && $socName) : ?>
                <div class="pwa-client-selector" data-project-id="<?php echo $project->id; ?>" title="Changer le tiers">
                    <?php echo $socBadge; ?>
                    <i class="far fa-building" style="color:#64748b;"></i>
                    <span style="font-weight:500;"><?php echo $socName; ?></span>
                    <i class="fas fa-chevron-down" style="color:#94a3b8;font-size:0.8em;"></i>
                </div>
                <?php else : ?>
                <div class="pwa-client-selector empty" data-project-id="<?php echo $project->id; ?>" title="Associer un tiers">
                    <i class="far fa-building"></i>
                    <span style="font-style:italic;">Client</span>
                    <i class="fas fa-chevron-down" style="font-size:0.8em;"></i>
                </div>
                <?php endif; ?>

                <!-- Contact -->
                <div class="pwa-contact-selector" data-project-id="<?php echo $project->id; ?>" title="Changer le contact">
                    <i class="far fa-address-book" style="color:#64748b;"></i>
                    <span style="font-weight:500;">Contact</span>
                    <i class="fas fa-chevron-down" style="color:#94a3b8;font-size:0.8em;"></i>
                </div>

                <!-- Hidden placeholder for JS (data-tiers-id used by pwa_selectors module) -->
                <div id="reedcrm-hidden-contact-selector-pwa-<?php echo $project->id; ?>"
                     class="reedcrm-hidden-contact-selector-wrap"
                     data-tiers-id="<?php echo $tiersId; ?>"
                     style="display:none;"></div>

            </div><!-- /pwa-selectors-group -->

            <!-- Devis -->
            <a href="<?php echo DOL_URL_ROOT; ?>/comm/propal/list.php?search_projet=<?php echo $project->id; ?>"
               class="pwa-amount-link" title="Devis en cours">
                <i class="fas fa-file-signature" style="color:#38a169;font-size:1.1em;"></i>
                <?php echo price($propalAmt, 0, '', 11, -1, -1, 'auto'); ?>
            </a>

            <!-- Factures -->
            <a href="<?php echo DOL_URL_ROOT; ?>/compta/facture/list.php?search_projet=<?php echo $project->id; ?>"
               class="pwa-amount-link" title="Factures en cours">
                <i class="fas fa-file-invoice-dollar" style="color:#38a169;font-size:1.1em;"></i>
                <?php echo price($factureAmt, 0, '', 11, -1, -1, 'auto'); ?>
            </a>

        </div><!-- /ROW 3 -->

    </div><!-- /pwa-card -->

    <?php endforeach; ?>

</div><!-- /page-content -->

<!-- Shared hidden overlay used by pwa_selectors JS module -->
<div id="reedcrm-hidden-company-selector-pwa" style="display:none;"></div>

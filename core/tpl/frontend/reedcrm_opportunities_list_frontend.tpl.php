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
    /* Select2 scroll fix on touch devices */
    .select2-results__options { touch-action: pan-y !important; -ms-touch-action: pan-y !important; }
    .select2-dropdown { touch-action: pan-y !important; }
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
    /* Force all media block children to the same height & vertical alignment */
    .pwa-card-media-row [id$="master-media-row-container-photo"] { flex-direction: row !important; gap: 6px !important; }
    .pwa-card-media-row .linked-medias.medias        { display: inline-flex; align-items: center; flex-direction: row; }
    .pwa-card-media-row .saturne-media-upload-block  { display: inline-flex !important; align-items: center !important; flex-direction: row !important; gap: 6px !important; margin-top: 0 !important; }
    .pwa-card-media-row .saturne-media-gallery       { display: inline-flex; align-items: center; }
    .pwa-card-media-row .saturne-audio-controls      { display: inline-flex !important; align-items: center !important; flex-direction: row !important; gap: 6px !important; margin-top: 0 !important; }
    .pwa-card-media-row .saturne-play-recording-wrapper { display: inline-flex; align-items: center; }
    /* Override Saturne 50px buttons → uniform 44×44px */
    .pwa-card-media-row [id$="master-media-row-container-audio"] { padding: 0 !important; }
    .pwa-card-media-row .saturne-upload-label {
        width: 44px !important; height: 44px !important;
        min-width: 44px !important; min-height: 44px !important;
        display: inline-flex !important; align-items: center !important; justify-content: center !important;
        box-sizing: border-box; flex-shrink: 0;
    }
    .pwa-card-media-row .saturne-media-btn {
        width: 44px !important; height: 44px !important;
        min-width: 44px !important; min-height: 44px !important;
        box-sizing: border-box;
    }
    .pwa-card-media-row .open-media-editor-as-gallery {
        position: relative; display: inline-flex !important; align-items: center; justify-content: center;
        width: 44px !important; height: 44px !important;
        min-width: 44px !important; min-height: 44px !important;
        border-radius: 12px;
        overflow: visible; cursor: pointer; flex-shrink: 0; align-self: center;
    }
    .pwa-card-media-row .open-media-editor-as-gallery img {
        width: 44px !important; height: 44px !important;
        object-fit: cover !important; border-radius: 12px !important;
        display: block !important;
    }
    .pwa-card-media-row .open-media-editor-as-gallery .saturne-media-count-badge {
        position: absolute; top: -6px; right: -6px; z-index: 2;
    }
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
    .inline-edit-proj-percent { color: #0f172a; }
    .inline-edit-proj-amount  { color: #3b82f6; }
    .initials-badge { font-size: 0.7em; color: #fff; background: #9b59b6; width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
    .dot-sep { color: #cbd5e0; margin: 0 6px; }
    /* Multi-contact tag chips */
    .pwa-contact-tags-wrap { display:flex; align-items:center; flex-wrap:wrap; gap:4px; position:relative; }
    .pwa-contact-icon-link { display:inline-flex; align-items:center; color:#64748b; font-size:1.15em; flex-shrink:0; text-decoration:none; }
    .pwa-contact-icon-nolink { color:#cbd5e0; font-size:1.1em; }
    .pwa-contact-chip { display:inline-flex; align-items:center; gap:3px; background:#e8f0fe; border-radius:10px; padding:2px 6px 2px 8px; font-size:0.82em; color:#334155; white-space:nowrap; }
    .pwa-contact-chip a { color:#3b5bdb; text-decoration:none; }
    .pwa-chip-role { color:#64748b; font-style:italic; font-size:0.9em; }
    .pwa-chip-remove { cursor:pointer; color:#94a3b8; padding:0 3px; margin-left:2px; transition:color 0.15s; line-height:1; display:inline-flex; align-items:center; }
    .pwa-chip-remove:hover { color:#e74c3c; }
    .pwa-add-contact-btn { cursor:pointer; display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#f1f5f9; color:#64748b; font-size:0.75em; border:1px solid #cbd5e0; transition:background 0.15s; flex-shrink:0; }
    .pwa-add-contact-btn:hover { background:#dbeafe; color:#3b5bdb; border-color:#3b5bdb; }
    .pwa-contact-add-panel { position:absolute; top:calc(100% + 4px); left:0; z-index:9999; background:#fff; border:1px solid #e2e8f0; border-radius:6px; min-width:220px; box-shadow:0 4px 12px rgba(0,0,0,0.15); overflow:hidden; }
    /* Custom native dropdown — replaces Select2 entirely */
    .pwa-contact-list { list-style:none; margin:0; padding:2px 0; max-height:200px; overflow-y:auto; }
    .pwa-contact-list li { padding:6px 12px; font-size:0.83em; cursor:pointer; display:flex; align-items:center; justify-content:space-between; color:#334155; white-space:nowrap; transition:background 0.1s; }
    .pwa-contact-list li:hover:not(.pwa-contact-list-empty):not(.pwa-contact-list-linked) { background:#eff6ff; color:#1d4ed8; }
    .pwa-contact-list-empty { color:#94a3b8; font-style:italic; cursor:default; }
    .pwa-contact-list-linked { color:#94a3b8; cursor:not-allowed; }
    .pwa-linked-check { color:#38a169; margin-left:8px; font-size:0.8em; }
    .pwa-contact-loading-inline { padding:8px 12px; color:#64748b; font-size:0.83em; display:flex; align-items:center; gap:6px; }
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
                $socName    = dol_escape_htmltag($soc->name);
                $socBadge   = method_exists($soc, 'getLibStatut') ? $soc->getLibStatut(3) . ' ' : '';
                $socNomUrl  = $soc->getNomUrl(1); // lien cliquable vers la fiche client
            }
        }
        $socNomUrl = $socNomUrl ?? '';

        // --- Linked contacts (all PROJECTCONTRIBUTOR/LEADER/SALESREP) ---
        $linkedContacts = [];
        $resLC = $db->query(
            "SELECT ec.rowid as link_id, ec.fk_socpeople,
                    CONCAT(sp.firstname, ' ', sp.lastname) as fullname,
                    ctc.code as role_code, ctc.libelle as role_label
               FROM " . MAIN_DB_PREFIX . "element_contact ec
               JOIN " . MAIN_DB_PREFIX . "socpeople sp  ON sp.rowid  = ec.fk_socpeople
               JOIN " . MAIN_DB_PREFIX . "c_type_contact ctc ON ctc.rowid = ec.fk_c_type_contact
              WHERE ec.element_id = " . (int)$project->id . "
                AND ctc.element = 'project'
                AND ctc.code IN ('PROJECTLEADER','PROJECTCONTRIBUTOR','SALESREPINTERNAL')
              ORDER BY ec.rowid ASC"
        );
        if ($resLC) {
            while ($lcRow = $db->fetch_object($resLC)) {
                $linkedContacts[] = [
                    'link_id'    => (int)$lcRow->link_id,
                    'contact_id' => (int)$lcRow->fk_socpeople,
                    'name'       => trim(dol_escape_htmltag($lcRow->fullname)),
                    'role_code'  => $lcRow->role_code,
                    'role_label' => dol_escape_htmltag($lcRow->role_label),
                ];
            }
        }
        // Build tooltip for icon
        $contactTooltip = !empty($linkedContacts)
            ? implode('&#10;', array_map(fn($c) => $c['name'] . ' (' . $c['role_label'] . ')', $linkedContacts))
            : 'Ajouter un contact';

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
                <span class="inline-edit-proj-percent" data-project-id="<?php echo $project->id; ?>" data-val="<?php echo (int)$project->opp_percent; ?>" title="Modifier la probabilité"><?php echo $percent; ?></span>
                <span style="color:#cbd5e0;margin:0 2px;">-</span>
                <span class="inline-edit-proj-amount"  data-project-id="<?php echo $project->id; ?>" data-val="<?php echo $rawAmount; ?>"           title="Modifier le montant"><?php echo $amount; ?></span>
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

                <!-- Client : icône externe (lien) + bouton + select inline -->
                <div class="pwa-selector-wrap" style="position:relative; display:flex; align-items:center; gap:4px;">
                    <?php if ($tiersId > 0 && $socName) : ?>
                    <a href="<?php echo DOL_URL_ROOT; ?>/societe/card.php?socid=<?php echo $tiersId; ?>" class="prevent-edit-click" title="Voir la fiche client" style="display:inline-flex;align-items:center;color:#64748b;font-size:1.15em;flex-shrink:0;">
                        <i class="fas fa-building"></i>
                    </a>
                    <div class="pwa-client-selector" data-project-id="<?php echo $project->id; ?>" title="Changer le tiers">
                        <?php echo $socBadge; ?>
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
                    <!-- Select inline (initialisé en Select2 AJAX par JS) -->
                    <div class="pwa-inline-select-wrap" id="pwa-client-wrap-<?php echo $project->id; ?>" style="display:none; position:absolute; top:100%; left:0; z-index:9999; min-width:220px;">
                        <select id="pwa-client-select-<?php echo $project->id; ?>"
                                class="pwa-client-select"
                                data-project-id="<?php echo $project->id; ?>"
                                style="width:100%;"></select>
                    </div>
                </div>

                <span class="dot-sep">&bull;</span>

                <!-- Contact : icône + chips multi-contact + bouton + -->
                <div class="pwa-contact-tags-wrap"
                     data-project-id="<?php echo $project->id; ?>"
                     data-tiers-id="<?php echo $tiersId; ?>">

                    <!-- Icône 📒 : lien vers 1er contact si existant, sinon grisée -->
                    <?php if (!empty($linkedContacts)) : ?>
                    <a href="<?php echo DOL_URL_ROOT; ?>/contact/card.php?id=<?php echo $linkedContacts[0]['contact_id']; ?>"
                       class="pwa-contact-icon-link prevent-edit-click"
                       title="<?php echo $contactTooltip; ?>">
                        <i class="fas fa-address-book"></i>
                    </a>
                    <?php else : ?>
                    <i class="fas fa-address-book pwa-contact-icon-nolink" title="<?php echo $contactTooltip; ?>"></i>
                    <?php endif; ?>

                    <!-- Chips contacts liés -->
                    <?php foreach ($linkedContacts as $lc) : ?>
                    <span class="pwa-contact-chip"
                          data-link-id="<?php echo $lc['link_id']; ?>"
                          data-contact-id="<?php echo $lc['contact_id']; ?>">
                        <a href="<?php echo DOL_URL_ROOT; ?>/contact/card.php?id=<?php echo $lc['contact_id']; ?>"
                           class="prevent-edit-click"
                           title="Voir la fiche contact"><?php echo $lc['name']; ?></a>
                        <span class="pwa-chip-role">- <?php echo $lc['role_label']; ?></span>
                        <span class="pwa-chip-remove prevent-edit-click"
                              data-link-id="<?php echo $lc['link_id']; ?>"
                              title="Retirer ce contact"><i class="fas fa-unlink" style="font-size:0.75em;"></i></span>
                    </span>
                    <?php endforeach; ?>

                    <!-- Bouton + Ajouter -->
                    <span class="pwa-add-contact-btn prevent-edit-click" title="Ajouter un contact">
                        <i class="fas fa-plus"></i>
                    </span>

                    <!-- Panel d'ajout (hidden) — contient la liste native (pas de Select2) -->
                    <div class="pwa-contact-add-panel prevent-edit-click" style="display:none;">
                        <!-- La liste est construite dynamiquement par JS -->
                    </div>

                </div><!-- /pwa-contact-tags-wrap -->

            </div><!-- /pwa-selectors-group -->

            <span class="dot-sep">&bull;</span>

            <!-- Devis -->
            <a href="<?php echo DOL_URL_ROOT; ?>/comm/propal/list.php?search_projet=<?php echo $project->id; ?>"
               class="pwa-amount-link" title="Devis en cours">
                <i class="fas fa-file-signature" style="color:#38a169;font-size:1.1em;"></i>
                <?php echo price($propalAmt, 0, '', 11, -1, -1, 'auto'); ?>
            </a>

            <!-- Factures -->
            <span class="dot-sep">&bull;</span>
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

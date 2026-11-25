<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    lib/reedcrm_fields.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for fields of custom list
 */

/**
 * Render the relaunch commercial field: the two-button widget (past / upcoming).
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_relaunch_commercial(array $parameters, CommonObject $object): string
{
    if (!isModEnabled('agenda')) {
        return '';
    }

    // In the saturne list loop the record id is $object->id (rowid is 0); the raw row, carrying
    // the real project id, is passed through the hook param 'obj'.
    $row       = !empty($parameters['obj']) ? $parameters['obj'] : $object;
    $projectId = (int) (!empty($row->id) ? $row->id : $object->id);

    require_once __DIR__ . '/reedcrm_relaunch.lib.php';

    return reedcrm_render_relaunch_widget([
        'projectId'    => $projectId,
        'wrapperClass' => 'reedcrm-plist-relaunch-wrapper',
    ]);
}

/**
 * Render the contact details field (name, email, phone).
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_contact_details(array $parameters, CommonObject $object): string
{
    global $user;

    // Extrafield values live on the raw fetched row (setVarsFromFetchObj only fills $object->fields)
    $row = !empty($parameters['obj']) ? $parameters['obj'] : $object;

    $lastname  = !empty($row->options_reedcrm_lastname)  ? (string) $row->options_reedcrm_lastname  : '';
    $firstname = !empty($row->options_reedcrm_firstname) ? (string) $row->options_reedcrm_firstname : '';
    $email     = !empty($row->options_reedcrm_email)     ? (string) $row->options_reedcrm_email     : '';
    $phone     = !empty($row->options_projectphone)      ? (string) $row->options_projectphone      : '';
    $website   = !empty($row->options_reedcrm_website)   ? (string) $row->options_reedcrm_website   : '';

    $canEdit = $user->hasRight('projet', 'creer');
    $id      = (int) $object->id;

    // Reuse the contact_inline.js editor (intlTelInput phone, email/website validation, save via
    // quickcreation.php?action=updateoppcontact) by rendering the .contact-inline-wrapper markup it
    // binds to. When the user cannot write, render the same layout with plain (non-clickable) spans.
    $span = function (string $field, string $value, string $placeholder, string $extraStyle = '') use ($canEdit) {
        $display = $value !== '' ? dol_escape_htmltag($value) : $placeholder;
        if ($canEdit) {
            return '<span class="inline-edit-contact" data-field="' . $field . '" data-val="' . dol_escape_htmltag($value) . '" style="cursor:pointer; border-bottom:1px dashed #cbd5e0; padding-bottom:1px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; ' . $extraStyle . '">' . $display . '</span>';
        }
        return '<span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; ' . $extraStyle . '">' . $display . '</span>';
    };

    $wrapperAttr = $canEdit ? ' data-project-id="' . $id . '"' : '';

    $out  = '<div class="reedcrm-plist-coordonnees contact-inline-wrapper"' . $wrapperAttr . '>';

    $out .= '<div class="reedcrm-plist-coordonnees-box">';
    $out .= '<div class="reedcrm-plist-coordonnees-name" style="display:flex; align-items:center; padding-left:8px;">';
    $out .= '<i class="fas fa-address-book" style="color:#64748b; margin-right:4px; flex-shrink:0;"></i>';
    $out .= $span('lastname', $lastname, 'Nom', 'margin-right:4px;');
    $out .= $span('firstname', $firstname, 'Prénom', 'flex-grow:1;');
    $out .= '</div>';

    $out .= '<div class="reedcrm-plist-coordonnees-email" style="display:flex; align-items:center; padding-left:8px;">';
    $out .= '<i class="fas fa-envelope" style="color:#64748b; margin-right:4px; flex-shrink:0;"></i>';
    $out .= $span('email', $email, 'Email', 'flex-grow:1;');
    $out .= '</div>';

    $out .= '<div class="reedcrm-plist-coordonnees-website" style="display:flex; align-items:center; padding-left:8px;">';
    $out .= '<i class="fas fa-globe" style="color:#64748b; margin-right:4px; flex-shrink:0;"></i>';
    $out .= $span('website', $website, 'Site web', 'flex-grow:1;');
    $out .= '</div>';
    $out .= '</div>';

    $out .= '<div class="reedcrm-plist-coordonnees-phone-wrapper" style="margin-left:auto; text-align:right;">';
    $out .= $span('phone', $phone, 'Téléphone', 'font-size:13px; color:#2c3e50; margin-right:6px;');
    if ($phone !== '') {
        $out .= '<a href="tel:' . dol_escape_htmltag(preg_replace('/[^0-9+]/', '', $phone)) . '" title="Appeler" style="color:#64748b; text-decoration:none;"><i class="fas fa-phone-alt reedcrm-icon-hover" style="font-size:13px; transition:color 0.2s;"></i></a>';
    } else {
        $out .= '<i class="fas fa-phone-alt" style="font-size:13px; color:#64748b;"></i>';
    }
    $out .= '</div>';

    $out .= '</div>';

    return $out;
}

/**
 * Render the opportunity status field (avoids core calling getNomUrl() on CLeadStatus).
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_opp_status(array $parameters, CommonObject $object): string
{
    global $db, $langs, $user;

    $statusId = (int) $object->fk_opp_status;

    // Lead statuses are loaded once per page request (avoids an N+1 query per row). The dictionary has no entity
    // column, and inactive statuses are kept: a project may still carry one
    static $leadStatuses = null;
    if ($leadStatuses === null) {
        $leadStatuses = [];
        $langs->load('projects');
        $sql  = 'SELECT rowid, code, label, percent, active FROM ' . MAIN_DB_PREFIX . 'c_lead_status';
        $sql .= ' ORDER BY position ASC, rowid ASC';
        $resql = $db->query($sql);
        if ($resql) {
            while ($row = $db->fetch_object($resql)) {
                // Same label as the screens of Dolibarr: the translation of OppStatus<code> when there is one. Saturne
                // gives that key the label of the dictionary when the status was renamed there
                $transKey  = 'OppStatus' . $row->code;
                $transText = $langs->transnoentitiesnoconv($transKey);
                $leadStatuses[(int) $row->rowid] = [
                    'code'    => $row->code,
                    'label'   => $transText !== $transKey ? $transText : (string) $row->label,
                    'percent' => $row->percent !== null ? (float) $row->percent : null,
                    'active'  => (int) $row->active,
                ];
            }
            $db->free($resql);
        }
    }

    $currentCode = $statusId && isset($leadStatuses[$statusId]) ? $leadStatuses[$statusId]['code'] : '';

    // Read-only badge when the user cannot edit projects
    if (!$user->hasRight('projet', 'creer')) {
        if (empty($statusId) || !isset($leadStatuses[$statusId])) {
            return '';
        }
        return '<span class="reedcrm-opp-status reedcrm-opp-status-' . dol_escape_htmltag($currentCode) . '">' . dol_escape_htmltag($leadStatuses[$statusId]['label']) . '</span>';
    }

    // Inline editable select (saved through the generic saturne_update_field endpoint)
    $out  = '<select class="saturne-inline-select reedcrm-opp-status-select reedcrm-opp-status-' . dol_escape_htmltag($currentCode) . '"';
    $out .= ' data-field="fk_opp_status" data-element="' . dol_escape_htmltag($object->element) . '" data-id="' . (int) $object->id . '">';
    $out .= '<option value="0" data-code="" data-percent=""' . (empty($statusId) ? ' selected' : '') . '>&nbsp;</option>';
    foreach ($leadStatuses as $rowid => $leadStatus) {
        // An inactive status is offered only to the project that still carries it
        if (empty($leadStatus['active']) && $rowid !== $statusId) {
            continue;
        }
        $selected   = ($rowid === $statusId) ? ' selected' : '';
        $defaultPct = isset($leadStatus['percent']) ? rtrim(rtrim(sprintf('%.2f', $leadStatus['percent']), '0'), '.') : '';
        $out       .= '<option value="' . $rowid . '" data-code="' . dol_escape_htmltag($leadStatus['code']) . '" data-percent="' . dol_escape_htmltag($defaultPct) . '"' . $selected . '>' . dol_escape_htmltag($leadStatus['label']) . '</option>';
    }
    $out .= '</select>';

    return $out;
}

/**
 * Render the photo field (project thumbnail).
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_photo(array $parameters, CommonObject $object): string
{
    global $conf;

    $projectDir = $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($object->ref) . '/';

    // A project has no favorite media : 'ref' names no file of its directory, and an empty field name
    // would be read as a property for each file found. No placeholder either, most rows have no photo.
    // 66px is the height of the Coordonnées block of the same row
    $out  = '<div class="reedcrm-list-photo">';
    $out .= saturne_show_medias_linked('projet', $projectDir, 'small', 1, -1, 0, 0, 66, 66, 0, 1, 0, dol_sanitizeFileName($object->ref), $object, 'ref', 0, 0, 0, 0, '', 1, ['useAi' => 0, 'filter' => '\.(png|jpe?g|gif|webp)$', 'hideNoPhoto' => 1]);
    $out .= '</div>';

    return $out;
}

/**
 * Render the vocal field: a play button on the last voice note of the project.
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_vocal(array $parameters, CommonObject $object): string
{
    global $conf;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

    $projectRef = dol_sanitizeFileName($object->ref);
    $audioFiles = dol_dir_list($conf->project->multidir_output[$conf->entity] . '/' . $projectRef, 'files', 0, '\.(mp3|ogg|wav|m4a|aac|webm|opus)$', null, 'date', SORT_DESC);

    // Never an empty string: the list would fall back on its generic rendering of a field that does not exist
    $out = '<div class="reedcrm-vocal-cell">';
    if (!empty($audioFiles)) {
        $lastAudio = $audioFiles[0];
        $fileUrl   = DOL_URL_ROOT . '/document.php?modulepart=projet&file=' . urlencode($projectRef . '/' . $lastAudio['name']);
        $out .= '<div class="reedcrm-vocal-player reedcrm-vocal-player-purple" data-audio-url="' . dol_escape_htmltag($fileUrl) . '" title="' . dol_escape_htmltag($lastAudio['name']) . '">';
        $out .= '<i class="fas fa-play"></i><span class="reedcrm-vocal-time"></span>';
        $out .= '</div>';
    }
    $out .= '</div>';

    return $out;
}
/**
 * Render the status field as a colored badge (pastille).
 *
 * For projects, getLibStatut() reads $object->statut which is not populated in the list loop
 * (only $object->fk_statut is), so build the badge explicitly from fk_statut. Other objects
 * keep their native getLibStatut(5) rendering.
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_status_badge(array $parameters, CommonObject $object): string
{
    global $langs, $user;

    if ($object->element === 'project') {
        $status  = (int) ($object->fk_statut ?? $object->statut ?? 0);
        $labels  = [0 => 'Draft', 1 => 'Validated', 2 => 'Closed'];
        $classes = [0 => 'status0', 1 => 'status4', 2 => 'status6'];
        $label   = $langs->trans($labels[$status] ?? 'Unknown');

        // Per-user display preference: colored dot with a small custom CSS tooltip
        if (isset($user->conf->REEDCRM_STATUS_DISPLAY) && $user->conf->REEDCRM_STATUS_DISPLAY === 'dot') {
            return '<span class="reedcrm-status-dot reedcrm-status-dot-' . $status . '" data-tooltip="' . dol_escape_htmltag($label) . '"></span>';
        }

        return dolGetStatus($label, $label, '', $classes[$status] ?? 'status0', 5);
    }

    return $object->getLibStatut(5);
}

/**
 * Render the opp_percent field as a colored badge and inject row background color.
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_opp_percent(array $parameters, CommonObject $object): string
{
    global $langs, $user;

    $oppPercent = $object->opp_percent;
    $canEdit    = $user->hasRight('projet', 'creer');
    $rowId      = (int) $object->id;

    if (!isset($oppPercent) && !$canEdit) {
        return '';
    }

    $out = '';
    if (isset($oppPercent)) {
        if ($oppPercent < 20) {
            $statusBadge = 8;
            $rowColor    = 'rgba(107, 114, 128, 0.08)';
        } elseif ($oppPercent < 60) {
            $statusBadge = 1;
            $rowColor    = 'rgba(239, 68, 68, 0.08)';
        } else {
            $statusBadge = 4;
            $rowColor    = 'rgba(34, 197, 94, 0.08)';
        }

        $out .= '<style data-opp-row-style="' . $rowId . '">tr[data-rowid="' . $rowId . '"]{background-color:' . $rowColor . '!important}</style>';
    }

    // Inline editable percentage when the user can edit projects, otherwise the read-only badge
    if ($canEdit) {
        // Format with 2 decimals first, then trim only the trailing decimal zeros (so 60 stays "60", not "6")
        $displayValue = isset($oppPercent) ? rtrim(rtrim(sprintf('%.2f', (float) $oppPercent), '0'), '.') : '';
        $errorLabel   = $langs->transnoentities('OppPercentRangeError');
        if ($errorLabel === 'OppPercentRangeError') {
            $errorLabel = '0 à 100 %';
        }
        $out .= '<span class="saturne-inline-percent">';
        $out .= '<span class="contenteditable" contenteditable="true" role="textbox" aria-label="' . dol_escape_htmltag($langs->trans('OpportunityProbabilityShort')) . '" data-field="opp_percent" data-id="' . $rowId . '" data-element="' . dol_escape_htmltag($object->element) . '" data-table="' . dol_escape_htmltag($object->table_element) . '" data-type="number" data-validate-pattern="^(?:100(?:[.,]0{1,2})?|[0-9]{1,2}(?:[.,][0-9]{1,2})?)$" data-success="Enregistré" data-error="' . dol_escape_htmltag($errorLabel) . '">' . dol_escape_htmltag($displayValue) . '</span>';
        $out .= '<span class="saturne-inline-percent-suffix">%</span>';
        $out .= '</span>';
    } else {
        $out .= dolGetBadge($oppPercent . ' %', '', 'status' . $statusBadge);
    }

    return $out;
}

/**
 * Render the project ref cell with quick-access actions (call / email / preview).
 *
 * Only on the project list; returns '' on other lists so the default ref rendering applies.
 * Reuses the standard ref output, then appends a compact action group:
 * tel: (project phone), mailto: (contact email) and an eye opening the detail in the eventpro side panel.
 *
 * @param  array        $parameters Hook parameters (key, val, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output ('' to fall back to default rendering)
 */
function reedcrm_field_ref_with_actions(array $parameters, CommonObject $object): string
{
    global $langs;

    if (strpos($parameters['context'] ?? '', 'projectlist') === false) {
        return '';
    }

    $refHtml = $object->showOutputField($parameters['val'], $parameters['key'], $object->ref);

    return '<div class="reedcrm-ref-cell">' . $refHtml . '</div>';
}

/**
 * Render the opportunity amount cell (inline-editable when the user can edit projects).
 *
 * @param  CommonObject $object The project object
 * @return string               HTML output
 */
function reedcrm_field_opp_amount(CommonObject $object): string
{
    global $conf, $langs, $user;

    $hasAmount = isset($object->opp_amount) && $object->opp_amount !== '' && $object->opp_amount !== null;
    if ($user->hasRight('projet', 'creer')) {
        $amountNum = $hasAmount ? rtrim(rtrim(number_format((float) $object->opp_amount, 2, '.', ''), '0'), '.') : '';

        return '<span class="contenteditable reedcrm-ce-inline" contenteditable="true" role="textbox" aria-label="' . dol_escape_htmltag($langs->trans('OpportunityAmount')) . '" data-field="opp_amount" data-id="' . (int) $object->id . '" data-element="' . dol_escape_htmltag($object->element) . '" data-type="number" data-success="Enregistré" data-error="Valeur invalide">' . dol_escape_htmltag($amountNum) . '</span> ' . dol_escape_htmltag($conf->currency);
    }

    return $hasAmount ? price((float) $object->opp_amount, 0, $langs, 1, -1, -1, $conf->currency) : '';
}

/**
 * Render the merged "Opportunité" cell: status + probability (both inline-editable) + amount.
 *
 * Reuses reedcrm_field_opp_status, reedcrm_field_opp_percent and reedcrm_field_opp_amount so inline
 * editing keeps working. All values come from standard project fields on $object.
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_opportunity_details(array $parameters, CommonObject $object): string
{
    $statusHtml  = reedcrm_field_opp_status($parameters, $object);
    $percentHtml = reedcrm_field_opp_percent($parameters, $object);
    $amountHtml  = reedcrm_field_opp_amount($object);

    $out  = '<div class="reedcrm-opp-cell">';
    $out .= '<div class="reedcrm-opp-cell-row">' . $statusHtml . '</div>';
    $out .= '<div class="reedcrm-opp-cell-row">' . $percentHtml . '</div>';
    if ($amountHtml !== '') {
        $out .= '<div class="reedcrm-opp-cell-row reedcrm-opp-cell-amount"><i class="fas fa-coins"></i> ' . $amountHtml . '</div>';
    }
    $out .= '</div>';

    return $out;
}

/**
 * Render the merged dates field (start + end) for the project list.
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_date_details(array $parameters, CommonObject $object): string
{
    global $db, $langs, $user;

    // Date columns hold the raw SQL value on the fetched row; convert with jdate()
    $row   = !empty($parameters['obj']) ? $parameters['obj'] : $object;
    $start = !empty($row->dateo) ? $db->jdate($row->dateo) : 0;
    $end   = !empty($row->datee) ? $db->jdate($row->datee) : 0;

    $canEdit = $user->hasRight('projet', 'creer');
    $id      = (int) $object->id;
    $element = $object->element ?? 'project';
    $table   = $object->table_element ?? 'projet';

    // Same datepicker editor markup as the generic saturne list loop (flatpickr + calendar button)
    $calBtn = '<button class="contenteditable-cal-btn" type="button" title="Ouvrir le calendrier" aria-label="Ouvrir le calendrier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></button>';

    // Inline-editable datepicker when the user can write (even when empty), otherwise plain date
    $field = function (string $key, int $ts, string $icon, string $labelKey) use ($canEdit, $id, $element, $table, $calBtn, $langs) {
        $label    = dol_escape_htmltag($langs->trans($labelKey));
        $text     = !empty($ts) ? dol_print_date($ts, 'day') : '';
        $iconHtml = '<i class="far ' . $icon . '" title="' . $label . '"></i> ';
        if ($canEdit) {
            return '<div class="reedcrm-dates-row">' . $iconHtml
                . '<div class="contenteditable-wrap"><div class="contenteditable reedcrm-ce-inline" contenteditable="true" role="textbox" aria-label="' . $label . '" data-field="' . $key . '" data-id="' . $id . '" data-element="' . dol_escape_htmltag($element) . '" data-table="' . dol_escape_htmltag($table) . '" data-type="datepicker" data-success="Enregistré" data-error="Format invalide">' . $text . '</div>' . $calBtn . '</div></div>';
        }
        if ($text === '') {
            return '';
        }
        return '<div class="reedcrm-dates-row">' . $iconHtml . $text . '</div>';
    };

    $startHtml = $field('dateo', $start, 'fa-calendar-plus', 'DateStart');
    $endHtml   = $field('datee', $end, 'fa-calendar-check', 'DateEnd');

    if ($startHtml === '' && $endHtml === '') {
        return '';
    }

    return '<div class="reedcrm-dates-cell">' . $startHtml . $endHtml . '</div>';
}

/**
 * Ids of the objects shown on the current page of a saturne list.
 *
 * The loop renders the rows one by one: a column reading data stored elsewhere loads it for the
 * whole page on the first row instead. The page is read back from the list query ($sqlForList,
 * exposed by the saturne list TPL) with the sort and the pagination of the list.
 *
 * @return int[] Ids of the objects of the page, empty outside a saturne list
 */
function reedcrm_load_list_page_ids(): array
{
    global $db, $limit, $offset, $sortfield, $sortorder, $sqlForList;

    if (empty($sqlForList)) {
        return [];
    }

    $sql   = 'SELECT listpage.rowid FROM (' . $sqlForList;
    $sql  .= $db->order($sortfield, $sortorder);
    $sql  .= (!empty($limit) ? $db->plimit($limit + 1, $offset) : '');
    $sql  .= ') AS listpage';
    $resql = $db->query($sql);
    if (!$resql) {
        return [];
    }

    $objectIds = [];
    while ($obj = $db->fetch_object($resql)) {
        $objectIds[] = (int) $obj->rowid;
    }
    $db->free($resql);

    return $objectIds;
}

/**
 * Load, for a whole page of listed objects, the categories carried by each of them.
 *
 * The categories live in llx_categorie_<element>, so rendering them row by row would cost one
 * query per line. Every tag of the page is loaded in a single query instead.
 *
 * @param  CommonObject $object The listed object (gives the element, hence the link table)
 * @return array                Categories (id, label, color) indexed by object id
 */
function reedcrm_load_list_categories(CommonObject $object): array
{
    global $db;

    $element   = $object->element;
    $linkTable = MAIN_DB_PREFIX . 'categorie_' . $element;
    $linkField = 'fk_' . $element;

    $objectIds = reedcrm_load_list_page_ids();
    if (empty($objectIds)) {
        return [];
    }

    $sql   = 'SELECT link.' . $linkField . ' AS fk_object, categorie.rowid, categorie.label, categorie.color';
    $sql  .= ' FROM ' . $linkTable . ' AS link';
    $sql  .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie AS categorie ON categorie.rowid = link.fk_categorie';
    $sql  .= ' WHERE link.' . $linkField . ' IN (' . implode(',', $objectIds) . ')';
    $sql  .= ' AND categorie.entity IN (' . getEntity('category') . ')';
    $sql  .= ' ORDER BY categorie.label ASC';
    $resql = $db->query($sql);
    if (!$resql) {
        return [];
    }

    $categories = [];
    while ($obj = $db->fetch_object($resql)) {
        $categories[(int) $obj->fk_object][] = ['id' => (int) $obj->rowid, 'label' => $obj->label, 'color' => $obj->color];
    }
    $db->free($resql);

    return $categories;
}

/**
 * Render the tags/categories cell, like the ticket list does.
 *
 * The column is virtual : a project carries its tags in llx_categorie_project, not in its own
 * table, so it is declared in $excludeFields and filled here. Each tag links back to the list
 * with the category added to the tag filter of the list header.
 *
 * @param  array        $parameters Hook parameters (key, context, obj, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_categories(array $parameters, CommonObject $object): string
{
    global $conf, $param;

    $objectId = (int) (!empty($object->id) ? $object->id : ($parameters['obj']->rowid ?? 0));
    if ($objectId <= 0) {
        return '';
    }

    $element = $object->element;
    if (!isset($conf->cache['reedcrmListCategories'][$element])) {
        $conf->cache['reedcrmListCategories'][$element] = reedcrm_load_list_categories($object);
    }

    $categories = $conf->cache['reedcrmListCategories'][$element][$objectId] ?? [];
    if (empty($categories)) {
        return '';
    }

    $listUrl = $_SERVER['PHP_SELF'] . '?' . ltrim($param ?? '', '&');

    $out = '';
    foreach ($categories as $category) {
        // Same tag rendering as the native lists : colored pill, text forced to keep a readable contrast
        $color     = preg_match('/^[0-9a-f]{6}$/i', ltrim((string) $category['color'], '#')) ? ltrim($category['color'], '#') : 'bbbbbb';
        $textColor = colorIsLight($color) == 1 ? 'categtextblack' : 'categtextwhite';
        $tagUrl    = $listUrl . '&search_categories_filter[]=' . $category['id'];

        $out .= '<span class="noborderoncategories" style="background: #' . $color . ';">';
        $out .= '<a class="' . $textColor . '" href="' . dol_escape_htmltag($tagUrl) . '" title="' . dol_escape_htmltag($category['label']) . '">';
        $out .= '<span class="fas fa-tag paddingright"></span>' . dol_escape_htmltag($category['label']);
        $out .= '</a></span>';
    }

    return $out;
}

/**
 * Label of a call list without the prefix of the default lists: "Liste d'appel — Jean Dupont" gives
 * "Jean Dupont". The headset of the pill already says it is a call list. The prefix depends on the
 * language the list was created in (DefaultCallListLabel), both are removed.
 *
 * @param  string $label Label of the call list
 * @return string        Label without the prefix
 */
function reedcrm_call_list_short_label(string $label): string
{
    global $langs;

    foreach (array_unique([$langs->transnoentitiesnoconv('CallList'), 'Call list', "Liste d'appel"]) as $prefix) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '\s*[—–-]\s*(.+)$/u', $label, $matches)) {
            return $matches[1];
        }
    }

    return $label;
}

/**
 * Load, for a whole page of listed opportunities, the call lists each of them is on.
 *
 * One query for the page instead of one per row, like the tags. Only the lists the user may see
 * are kept (all of them, their subordinates' ones or their own, by right).
 *
 * @return array Call lists by project id: [projectId => [['id', 'line_id', 'label', 'status'], ...]]
 */
function reedcrm_load_list_call_lists(): array
{
    global $db, $user;

    dol_include_once('/reedcrm/class/calllist.class.php');
    dol_include_once('/reedcrm/class/calllistline.class.php');
    dol_include_once('/reedcrm/lib/reedcrm_call_list.lib.php');

    $objectIds = reedcrm_load_list_page_ids();
    if (empty($objectIds)) {
        return [];
    }

    $sql   = 'SELECT line.rowid AS line_id, line.element_id, cl.rowid, cl.label, cl.status, cl.fk_user_assign';
    $sql  .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_call_list_line AS line';
    $sql  .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'reedcrm_call_list AS cl ON cl.rowid = line.fk_call_list';
    $sql  .= " WHERE line.element_type = 'project'";
    $sql  .= ' AND line.element_id IN (' . implode(',', $objectIds) . ')';
    // An unlinked line stays in the table with the deleted status
    $sql  .= ' AND line.status != ' . CallListLine::STATUS_DELETED;
    $sql  .= ' AND cl.entity IN (' . getEntity('call_list') . ')';
    $sql  .= ' ORDER BY cl.position ASC, cl.label ASC';
    $resql = $db->query($sql);
    if (!$resql) {
        return [];
    }

    $callList  = new CallList($db);
    $callLists = [];
    while ($obj = $db->fetch_object($resql)) {
        $callList->fk_user_assign = (int) $obj->fk_user_assign;
        if (!reedcrm_call_list_user_can_read($user, $callList)) {
            continue;
        }
        $callLists[(int) $obj->element_id][] = ['id' => (int) $obj->rowid, 'line_id' => (int) $obj->line_id, 'label' => reedcrm_call_list_short_label($obj->label), 'title' => $obj->label, 'status' => (int) $obj->status];
    }
    $db->free($resql);

    return $callLists;
}

/**
 * Settings of the call lists column, printed once on the page and read by call_lists_column.js.
 *
 * The lists an opportunity can be added to are the draft and active ones the user may see, like
 * the widget of the cards. The phone labels are those the widget prompt reads.
 *
 * @return string HTML output
 */
function reedcrm_call_lists_column_settings(): string
{
    global $db, $langs, $user;

    $callList  = new CallList($db);
    $available = $callList->fetchAll('ASC', CallList::SORT_FIELDS, 0, 0, ['customsql' => 't.status IN (' . CallList::STATUS_DRAFT . ', ' . CallList::STATUS_ACTIVE . ')']);

    $lists = [];
    if (is_array($available)) {
        foreach ($available as $availableList) {
            if ($availableList instanceof CallList && reedcrm_call_list_user_can_read($user, $availableList)) {
                $lists[] = ['id' => (int) $availableList->id, 'label' => reedcrm_call_list_short_label($availableList->label), 'title' => $availableList->label];
            }
        }
    }

    $out  = '<div id="reedcrm-call-lists-settings" class="hidden"';
    $out .= ' data-lists="' . dol_escape_htmltag(json_encode($lists)) . '"';
    $out .= ' data-add-url="' . dol_escape_htmltag(dol_buildpath('/reedcrm/ajax/add_to_call_list.php', 1)) . '"';
    $out .= ' data-unlink-url="' . dol_escape_htmltag(dol_buildpath('/reedcrm/ajax/unlink_call_list_line.php', 1)) . '"';
    $out .= ' data-card-url="' . dol_escape_htmltag(dol_buildpath('/reedcrm/view/call_list_card.php', 1)) . '"';
    $out .= ' data-can-unlink="' . ($user->hasRight('reedcrm', 'call_list', 'delete') ? 1 : 0) . '"';
    $out .= ' data-title-label="' . dol_escape_htmltag($langs->trans('CallLists')) . '"';
    $out .= ' data-empty-label="' . dol_escape_htmltag($langs->trans('NoActiveCallList')) . '"';
    $out .= ' data-unlink-forbidden-label="' . dol_escape_htmltag($langs->trans('NotEnoughPermissions')) . '"';
    $out .= ' data-enter-phone-label="' . dol_escape_htmltag($langs->trans('CallListEnterPhonePlaceholder')) . '"';
    $out .= ' data-enter-phone-generic-label="' . dol_escape_htmltag($langs->trans('CallListEnterPhoneGenericPlaceholder')) . '"';
    $out .= ' data-phone-required-label="' . dol_escape_htmltag($langs->trans('CallListPhoneRequired')) . '"';
    $out .= ' data-phone-invalid-label="' . dol_escape_htmltag($langs->trans('CallListInvalidPhone')) . '"';
    $out .= ' data-save-label="' . dol_escape_htmltag($langs->trans('CallListSaveAndAdd')) . '"';
    $out .= ' data-cancel-label="' . dol_escape_htmltag($langs->trans('Cancel')) . '"></div>';

    return $out;
}

/**
 * Render the call lists cell of the opportunity list: one pill per list, linking to its card, and
 * the pen opening the checklist that adds the opportunity to a list or takes it off one.
 *
 * @param  array        $parameters Hook parameters (key, context, obj, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_call_lists(array $parameters, CommonObject $object): string
{
    global $conf, $langs, $user;

    $objectId = (int) (!empty($object->id) ? $object->id : ($parameters['obj']->rowid ?? 0));
    if ($objectId <= 0) {
        return '';
    }

    if (!isset($conf->cache['reedcrmListCallLists'])) {
        $conf->cache['reedcrmListCallLists'] = reedcrm_load_list_call_lists();
    }

    $out = '';
    $canWrite = $user->hasRight('reedcrm', 'call_list', 'write');
    if ($canWrite && empty($conf->cache['reedcrmListCallListsSettings'])) {
        $conf->cache['reedcrmListCallListsSettings'] = 1;
        $out .= reedcrm_call_lists_column_settings();
    }

    $cardUrl = dol_buildpath('/reedcrm/view/call_list_card.php', 1);

    $out .= '<div class="reedcrm-call-lists-cell" data-element-type="project" data-element-id="' . $objectId . '">';
    $out .= '<span class="reedcrm-call-lists-pills">';
    foreach ($conf->cache['reedcrmListCallLists'][$objectId] ?? [] as $callList) {
        $archived = !in_array($callList['status'], [CallList::STATUS_DRAFT, CallList::STATUS_ACTIVE]);
        $out .= '<a class="reedcrm-call-list-pill' . ($archived ? ' reedcrm-call-list-pill-archived' : '') . '" href="' . dol_escape_htmltag($cardUrl . '?id=' . $callList['id']) . '"';
        $out .= ' data-call-list-id="' . $callList['id'] . '" data-line-id="' . $callList['line_id'] . '" title="' . dol_escape_htmltag($callList['title']) . '">';
        $out .= '<span class="fas fa-headset"></span>' . dol_escape_htmltag($callList['label']) . '</a>';
    }
    $out .= '</span>';
    if ($canWrite) {
        $out .= '<button type="button" class="reedcrm-call-lists-edit" title="' . dol_escape_htmltag($langs->trans('AddToCallList')) . '"><span class="fas fa-pen"></span></button>';
    }
    $out .= '</div>';

    return $out;
}

/**
 * Columns of the thirdparty selected with the rows of the opportunity list.
 *
 * getNomUrl() and its tooltip read these properties: the thirdparty of each row is rebuilt from
 * them, as the native project list does, instead of being fetched line by line. Each column is
 * selected as reedcrm_soc_<property>.
 *
 * @return array<string,string> Columns of llx_societe indexed by property of Societe
 */
function reedcrm_thirdparty_list_columns(): array
{
    return [
        'name'             => 'nom',
        'name_alias'       => 'name_alias',
        'client'           => 'client',
        'fournisseur'      => 'fournisseur',
        'code_client'      => 'code_client',
        'code_fournisseur' => 'code_fournisseur',
        'email'            => 'email',
        'phone'            => 'phone',
        'address'          => 'address',
        'zip'              => 'zip',
        'town'             => 'town',
        'status'           => 'status',
    ];
}

/**
 * Sort key of the thirdparty column of the opportunity list: the name of the thirdparty.
 *
 * Many opportunities have no thirdparty (quick creation, PWA): they come last whatever the order,
 * rather than filling the first pages of an ascending sort. A flag ahead of the name does it, the
 * database putting the empty values first in an ascending order.
 *
 * @param  string $alias     Alias of the joined thirdparty table
 * @param  string $sortOrder Order of the list (asc or desc, first one of several)
 * @return string            SQL expression
 */
function reedcrm_thirdparty_list_sort_sql(string $alias, string $sortOrder): string
{
    $isDescending = strtolower(trim(explode(',', $sortOrder)[0])) === 'desc';

    return "CONCAT(IF(" . $alias . ".nom IS NULL, '" . ($isDescending ? '0' : '1') . "', '" . ($isDescending ? '1' : '0') . "'), COALESCE(" . $alias . ".nom, ''))";
}

/**
 * Extrafields of the project shown in the contact details cell of the opportunity list.
 *
 * Only those defined on the instance are kept: one deleted by an administrator would end the
 * query of the list in an SQL error.
 *
 * @param  array    $extrafieldLabels Labels of the extrafields of the project, indexed by name
 * @return string[]                   Names of the extrafields, last name and first name first
 */
function reedcrm_contact_list_extrafields(array $extrafieldLabels): array
{
    return array_values(array_intersect(['reedcrm_lastname', 'reedcrm_firstname', 'reedcrm_email', 'projectphone', 'reedcrm_website'], array_keys($extrafieldLabels)));
}

/**
 * Sort key of the contact details column of the opportunity list: the name of the contact, last name then first name.
 *
 * The projects without a contact name come last whatever the order, as in the thirdparty column.
 *
 * @param  string   $alias           Alias of the joined table of the project extrafields
 * @param  string[] $extrafieldNames Extrafields of the cell (reedcrm_contact_list_extrafields)
 * @param  string   $sortOrder       Order of the list (asc or desc, first one of several)
 * @return string                    SQL expression
 */
function reedcrm_contact_list_sort_sql(string $alias, array $extrafieldNames, string $sortOrder): string
{
    $nameColumns = [];
    foreach (array_intersect(['reedcrm_lastname', 'reedcrm_firstname'], $extrafieldNames) as $extrafieldName) {
        $nameColumns[] = $alias . '.' . $extrafieldName;
    }
    if (empty($nameColumns)) {
        return "''";
    }

    $isDescending = strtolower(trim(explode(',', $sortOrder)[0])) === 'desc';
    $name         = "TRIM(CONCAT_WS(' ', " . implode(', ', $nameColumns) . "))";

    return "CONCAT(CASE WHEN " . $name . " = '' THEN '" . ($isDescending ? '0' : '1') . "' ELSE '" . ($isDescending ? '1' : '0') . "' END, " . $name . ")";
}

/**
 * Filter of the contact details column of the opportunity list: each word typed is looked for in the
 * name, the email, the phone or the website of the contact.
 *
 * In a subquery: the list counts its records without the join of the extrafields, a condition on
 * that join would end the count in an SQL error.
 *
 * @param  DoliDB   $db              Database handler
 * @param  string   $alias           Alias of the project table
 * @param  string[] $extrafieldNames Extrafields of the cell (reedcrm_contact_list_extrafields)
 * @param  string   $search          Text typed in the filter
 * @return string                    SQL condition, starting with AND, empty when there is nothing to filter
 */
function reedcrm_contact_list_search_sql(DoliDB $db, string $alias, array $extrafieldNames, string $search): string
{
    if (empty($extrafieldNames) || trim($search) === '') {
        return '';
    }

    $columns = [];
    foreach ($extrafieldNames as $extrafieldName) {
        $columns[] = 'reedcrm_contact.' . $extrafieldName;
    }

    return ' AND EXISTS (SELECT 1 FROM ' . $db->prefix() . 'projet_extrafields AS reedcrm_contact WHERE reedcrm_contact.fk_object = ' . $alias . '.rowid' . natural_search($columns, $search) . ')';
}

/**
 * Render the thirdparty cell of the opportunity list: the link to the thirdparty card.
 *
 * @param  array        $parameters Hook parameters (key, context, obj, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_thirdparty(array $parameters, CommonObject $object): string
{
    global $db;

    // Never an empty string: the list would fall back on its generic rendering, which prints the sort key
    $row          = $parameters['obj'] ?? null;
    $thirdpartyId = (int) ($row->fk_soc ?? 0);
    if ($thirdpartyId <= 0 || !isset($row->reedcrm_soc_name)) {
        return '<span></span>';
    }

    require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

    $thirdparty     = new Societe($db);
    $thirdparty->id = $thirdpartyId;
    foreach (array_keys(reedcrm_thirdparty_list_columns()) as $property) {
        $thirdparty->$property = $row->{'reedcrm_soc_' . $property};
    }

    return '<div class="tdoverflowmax200">' . $thirdparty->getNomUrl(1) . '</div>';
}

/**
 * Render the lifecycle cell: the timeline of the project card, in its compact form.
 *
 * The timeline is built from the documents the project carries: they are read for the projects of
 * the whole page on the first row. The column is sorted on the current step by the printFieldListSelect
 * hook, and filtered on the milestones reached by the printFieldListSearch hook.
 *
 * @param  array        $parameters Hook parameters (key, context, obj, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_lifecycle(array $parameters, CommonObject $object): string
{
    global $conf;

    require_once __DIR__ . '/reedcrm_lifecycle.lib.php';

    if (!isset($conf->cache['reedcrmListLifecycle'])) {
        $conf->cache['reedcrmListLifecycle'] = reedcrm_lifecycle_fetch_projects(reedcrm_load_list_page_ids());
    }

    $project          = $conf->cache['reedcrmListLifecycle'][(int) $object->id] ?? [];
    $lifecycleSteps   = !empty($project) ? reedcrm_lifecycle_mark_current(reedcrm_build_project_lifecycle_steps($project)) : [];
    $lifecycleElement = $object->element;
    $lifecycleCompact = true;

    // Never an empty string: the list would fall back on its generic rendering of a field that does not exist
    ob_start();
    print '<div class="reedcrm-lifecycle-cell">';
    require __DIR__ . '/../core/tpl/reedcrm_lifecycle_timeline.tpl.php';
    print '</div>';

    return ob_get_clean();
}

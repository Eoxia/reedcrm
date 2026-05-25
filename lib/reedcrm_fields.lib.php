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
 * Render the relaunch commercial field (ActionComm buttons by type).
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_relaunch_commercial(array $parameters, CommonObject $object): string
{
    global $conf, $db, $langs, $user;

    $out = '';

    if (!isModEnabled('agenda')) {
        return $out;
    }

    // In the saturne list loop the record id is $object->id (rowid is 0); the raw row
    // (carrying the real project id and socid) is passed via the hook param 'obj'.
    $row       = !empty($parameters['obj']) ? $parameters['obj'] : $object;
    $projectId = (int) (!empty($row->id) ? $row->id : $object->id);
    $socid     = (int) ($row->socid ?? 0);

    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

    $actionComm = new ActionComm($db);

    $filter      = ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $conf->global->REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG . ')';
    $actionComms = $actionComm->getActions($socid, $projectId, 'project', $filter, 'a.datec');

    $actonComsByType = [
        'call' => [
            'picto'      => 'headset',
            'actioncode' => 'AC_TEL',
            'nb'         => 0
        ],
        'email' => [
            'picto'      => 'envelope',
            'actioncode' => 'AC_EMAIL',
            'nb'         => 0
        ],
        'rdv' => [
            'picto'      => 'calendar',
            'actioncode' => 'AC_RDV',
            'nb'         => 0
        ],
        'other' => [
            'picto'      => 'comment-dots',
            'actioncode' => 'AC_OTH',
            'nb'         => 0
        ],
    ];

    if (is_array($actionComms) && !empty($actionComms)) {
        foreach ($actionComms as $ac) {
            if ($ac->type_code == 'AC_TEL') {
                $actonComsByType['call']['nb']++;
            } elseif ($ac->type_code == 'AC_EMAIL') {
                $actonComsByType['email']['nb']++;
            } elseif ($ac->type_code == 'AC_RDV') {
                $actonComsByType['rdv']['nb']++;
            } else {
                $actonComsByType['other']['nb']++;
            }
        }
    }

    $cardProUrl = '/custom/reedcrm/view/procard.php?from_id=' . $projectId . '&from_type=project&project_id=' . $projectId;

    $out .= '<div class="reedcrm-plist-relaunch-wrapper">';
    $out .= '<div class="reedcrm-plist-relaunch-buttons reedcrm-relaunch-buttons">';

    foreach ($actonComsByType as $actionCommType => $actonComByType) {
        $dialogUrl = dol_buildpath('custom/reedcrm/core/ajax/get_relaunches_list.php', 1);

        $out .= '<div id="btn-relaunch-' . $actionCommType . '-' . $projectId . '" class="ui-dialog-open reedcrm-relaunch-button reedcrm-plist-relaunch-btn-' . $actionCommType . '"';
        $out .= ' data-dialog-id="dialog-relaunch-' . $actionCommType . '-' . $projectId . '" data-dialog-title="' . $langs->trans($actionCommType) . '" data-dialog-icon="fas fa-' . $actonComByType['picto'] . '" data-dialog-align="center" data-dialog-url="' . $dialogUrl . '" data-dialog-footer="none" data-project-id="' . $projectId . '" data-action-comm-type="' . $actonComByType['actioncode'] . '">';

        $out .= '<div class="reedcrm-plist-relaunch-btn-content">';
        $out .= '<i class="fas fa-' . $actonComByType['picto'] . '"></i>';
        $out .= '<span class="reedcrm-plist-relaunch-count">' . $actonComByType['nb'] . '</span>';
        $out .= '</div>';

        if ($user->hasRight('agenda', 'myactions', 'create')) {
            $cardProUrlFull = DOL_URL_ROOT . $cardProUrl . '&actioncode=' . $actonComByType['actioncode'];
            $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $projectId . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
            $out .= '<input type="hidden" class="modal-options" data-modal-to-open="eventproCardModal">';
            $out .= '</span>';
        }

        $out .= '</div>';
    }

    $out .= '</div>';
    $out .= '</div>';

    return $out;
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
    $out .= $span('firstname', $firstname, 'Prénom', 'margin-right:4px;');
    $out .= $span('lastname', $lastname, 'Nom', 'flex-grow:1;');
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

    // Lead statuses are loaded once per page request (avoids an N+1 query per row)
    static $leadStatuses = null;
    if ($leadStatuses === null) {
        $leadStatuses = [];
        $sql  = 'SELECT rowid, code, label FROM ' . MAIN_DB_PREFIX . 'c_lead_status';
        $sql .= ' WHERE active = 1 AND entity IN (' . getEntity('c_lead_status') . ')';
        $sql .= ' ORDER BY position ASC';
        $resql = $db->query($sql);
        if ($resql) {
            while ($row = $db->fetch_object($resql)) {
                $leadStatuses[(int) $row->rowid] = ['code' => $row->code, 'label' => $row->label];
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
        return '<span class="reedcrm-opp-status reedcrm-opp-status-' . dol_escape_htmltag($currentCode) . '">' . dol_escape_htmltag($langs->trans($leadStatuses[$statusId]['label'])) . '</span>';
    }

    // Inline editable select (saved through the generic saturne_update_field endpoint)
    $out  = '<select class="saturne-inline-select reedcrm-opp-status-select reedcrm-opp-status-' . dol_escape_htmltag($currentCode) . '"';
    $out .= ' data-field="fk_opp_status" data-element="' . dol_escape_htmltag($object->element) . '" data-id="' . (int) $object->id . '">';
    $out .= '<option value="0"' . (empty($statusId) ? ' selected' : '') . '>&nbsp;</option>';
    foreach ($leadStatuses as $rowid => $leadStatus) {
        $selected = ($rowid === $statusId) ? ' selected' : '';
        $out     .= '<option value="' . $rowid . '"' . $selected . '>' . dol_escape_htmltag($langs->trans($leadStatus['label'])) . '</option>';
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

    return saturne_show_medias_linked('projet', $projectDir, 'small', 1, -1, 0, 0, 30, 30, 0, 1, 0, dol_sanitizeFileName($object->ref), $object, '', 0, 0, 0, 0, '', 1, ['useAi' => 0, 'filter' => '\.(png|jpg|gif)$']);
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

    if (!isset($oppPercent)) {
        return '';
    }

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

    $rowId = (int) $object->id;
    $out   = '<style>tr[data-rowid="' . $rowId . '"]{background-color:' . $rowColor . '!important}</style>';

    // Inline editable percentage when the user can edit projects, otherwise the read-only badge
    if ($user->hasRight('projet', 'creer')) {
        // Format with 2 decimals first, then trim only the trailing decimal zeros (so 60 stays "60", not "6")
        $displayValue = rtrim(rtrim(sprintf('%.2f', (float) $oppPercent), '0'), '.');
        $out .= '<span class="saturne-inline-percent">';
        $out .= '<span class="contenteditable" contenteditable="true" role="textbox" aria-label="' . dol_escape_htmltag($langs->trans('OpportunityProbabilityShort')) . '" data-field="opp_percent" data-id="' . $rowId . '" data-element="' . dol_escape_htmltag($object->element) . '" data-table="' . dol_escape_htmltag($object->table_element) . '" data-type="number" data-success="Enregistré" data-error="Valeur invalide">' . dol_escape_htmltag($displayValue) . '</span>';
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

    // Quick preview (call/email now live in the merged "Coordonnées" column)
    $id         = (int) $object->id;
    $previewUrl = DOL_URL_ROOT . '/custom/reedcrm/view/procard.php?from_id=' . $id . '&from_type=project&project_id=' . $id;
    $actions    = '<span class="reedcrm-row-actions">';
    $actions   .= '<button type="button" class="reedcrm-row-action reedcrm-card-modal-open" title="' . dol_escape_htmltag($langs->trans('Preview')) . '" data-project-id="' . $id . '" data-modal-url="' . dol_escape_htmltag($previewUrl) . '"><i class="fas fa-eye"></i></button>';
    $actions   .= '</span>';

    return '<div class="reedcrm-ref-cell">' . $refHtml . $actions . '</div>';
}

/**
 * Render the merged "Opportunité" cell: status + probability (both inline-editable) + amount.
 *
 * Reuses reedcrm_field_opp_status and reedcrm_field_opp_percent so inline editing keeps working,
 * and appends the opportunity amount. All values come from standard project fields on $object.
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_opportunity_details(array $parameters, CommonObject $object): string
{
    global $conf, $langs, $user;

    $statusHtml  = reedcrm_field_opp_status($parameters, $object);
    $percentHtml = reedcrm_field_opp_percent($parameters, $object);

    // Amount (budget): inline-editable when the user can write (even when empty), otherwise formatted price
    $hasAmount = isset($object->opp_amount) && $object->opp_amount !== '' && $object->opp_amount !== null;
    if ($user->hasRight('projet', 'creer')) {
        $amountNum  = $hasAmount ? rtrim(rtrim(number_format((float) $object->opp_amount, 2, '.', ''), '0'), '.') : '';
        $amountHtml = '<span class="contenteditable reedcrm-ce-inline" contenteditable="true" role="textbox" aria-label="' . dol_escape_htmltag($langs->trans('OpportunityAmount')) . '" data-field="opp_amount" data-id="' . (int) $object->id . '" data-element="' . dol_escape_htmltag($object->element) . '" data-type="number" data-success="Enregistré" data-error="Valeur invalide">' . dol_escape_htmltag($amountNum) . '</span> ' . dol_escape_htmltag($conf->currency);
    } else {
        $amountHtml = $hasAmount ? price((float) $object->opp_amount, 0, $langs, 1, -1, -1, $conf->currency) : '';
    }

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
    global $db, $langs;

    // Date columns hold the raw SQL value on the fetched row; convert with jdate()
    $row   = !empty($parameters['obj']) ? $parameters['obj'] : $object;
    $start = !empty($row->dateo) ? $db->jdate($row->dateo) : 0;
    $end   = !empty($row->datee) ? $db->jdate($row->datee) : 0;

    if (empty($start) && empty($end)) {
        return '';
    }

    $out = '<div class="reedcrm-dates-cell">';
    if (!empty($start)) {
        $out .= '<div class="reedcrm-dates-row"><i class="far fa-calendar-plus" title="' . dol_escape_htmltag($langs->trans('DateStart')) . '"></i> ' . dol_print_date($start, 'day') . '</div>';
    }
    if (!empty($end)) {
        $out .= '<div class="reedcrm-dates-row"><i class="far fa-calendar-check" title="' . dol_escape_htmltag($langs->trans('DateEnd')) . '"></i> ' . dol_print_date($end, 'day') . '</div>';
    }
    $out .= '</div>';

    return $out;
}

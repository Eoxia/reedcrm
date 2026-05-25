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

    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

    $actionComm = new ActionComm($db);

    $filter      = ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $conf->global->REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG . ')';
    $actionComms = $actionComm->getActions($object->socid, $object->rowid, 'project', $filter, 'a.datec');

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

    $cardProUrl = '/custom/reedcrm/view/procard.php?from_id=' . $object->rowid . '&from_type=project&project_id=' . $object->rowid;

    $out .= '<div class="reedcrm-plist-relaunch-wrapper">';
    $out .= '<div class="reedcrm-plist-relaunch-buttons reedcrm-relaunch-buttons">';

    foreach ($actonComsByType as $actionCommType => $actonComByType) {
        $dialogUrl = dol_buildpath('custom/reedcrm/core/ajax/get_relaunches_list.php', 1);

        $out .= '<div id="btn-relaunch-' . $actionCommType . '-' . $object->rowid . '" class="ui-dialog-open reedcrm-relaunch-button reedcrm-plist-relaunch-btn-' . $actionCommType . '"';
        $out .= ' data-dialog-id="dialog-relaunch-' . $actionCommType . '-' . $object->rowid . '" data-dialog-title="' . $langs->trans($actionCommType) . '" data-dialog-icon="fas fa-' . $actonComByType['picto'] . '" data-dialog-align="center" data-dialog-url="' . $dialogUrl . '" data-dialog-footer="none" data-project-id="' . $object->rowid . '" data-action-comm-type="' . $actonComByType['actioncode'] . '">';

        $out .= '<div class="reedcrm-plist-relaunch-btn-content">';
        $out .= '<i class="fas fa-' . $actonComByType['picto'] . '"></i>';
        $out .= '<span class="reedcrm-plist-relaunch-count">' . $actonComByType['nb'] . '</span>';
        $out .= '</div>';

        if ($user->hasRight('agenda', 'myactions', 'create')) {
            $cardProUrlFull = DOL_URL_ROOT . $cardProUrl . '&actioncode=' . $actonComByType['actioncode'];
            $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $object->rowid . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
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
    global $langs;

    $out = '';

    // Extrafield values live on the raw fetched row (setVarsFromFetchObj only fills $object->fields)
    $row = !empty($parameters['obj']) ? $parameters['obj'] : $object;

    $thirdPartyName  = !empty($row->options_reedcrm_lastname)  ? dol_escape_htmltag($row->options_reedcrm_lastname)  : '';
    $thirdPartyName2 = !empty($row->options_reedcrm_firstname) ? dol_escape_htmltag($row->options_reedcrm_firstname) : '';
    $thirdPartyEmail = !empty($row->options_reedcrm_email)     ? dol_escape_htmltag($row->options_reedcrm_email)     : '';
    $thirdPartyPhone = !empty($row->options_projectphone)      ? dol_escape_htmltag($row->options_projectphone)      : '';

    $out .= '<div class="reedcrm-plist-coordonnees">';
    $out .= '<div class="reedcrm-plist-coordonnees-box">';

    $thirdPartyName = $thirdPartyName ? ($thirdPartyName . ' ' . $thirdPartyName2) : 'N/A';
    $out .= '<div class="reedcrm-plist-coordonnees-name">' . $thirdPartyName . '</div>';
    $out .= '<div class="reedcrm-plist-coordonnees-email"><i class="fas fa-envelope"></i>' . ($thirdPartyEmail ?: 'N/A') . '</div>';
    $out .= '<div class="reedcrm-plist-coordonnees-phone"><i class="fas fa-phone-alt"></i>' . ($thirdPartyPhone ?: 'N/A') . '</div>';
    $out .= '</div>';

    $out .= '<div class="reedcrm-plist-coordonnees-actions">';
    if ($thirdPartyPhone) {
        $out .= '<a href="tel:' . dol_escape_htmltag(preg_replace('/\s+/', '', $thirdPartyPhone)) . '" class="reedcrm-plist-coordonnees-btn" title="' . dol_escape_htmltag($langs->trans('Phone')) . '"><i class="fas fa-phone-alt"></i></a>';
    } else {
        $out .= '<div class="reedcrm-plist-coordonnees-btn disabled" title="' . dol_escape_htmltag($langs->trans('Phone')) . '"><i class="fas fa-phone-alt"></i></div>';
    }
    if ($thirdPartyEmail) {
        $out .= '<a href="mailto:' . dol_escape_htmltag($thirdPartyEmail) . '" class="reedcrm-plist-coordonnees-btn" title="' . dol_escape_htmltag($langs->trans('EMail')) . '"><i class="fas fa-envelope"></i></a>';
    } else {
        $out .= '<div class="reedcrm-plist-coordonnees-btn disabled" title="' . dol_escape_htmltag($langs->trans('EMail')) . '"><i class="fas fa-envelope"></i></div>';
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

        // Per-user display preference: colored dot (with a tooltip) instead of the full badge
        if (isset($user->conf->REEDCRM_STATUS_DISPLAY) && $user->conf->REEDCRM_STATUS_DISPLAY === 'dot') {
            return '<span class="reedcrm-status-dot reedcrm-status-dot-' . $status . ' classfortooltip" title="' . dol_escape_htmltag($label) . '"></span>';
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
    global $conf, $langs;

    $statusHtml  = reedcrm_field_opp_status($parameters, $object);
    $percentHtml = reedcrm_field_opp_percent($parameters, $object);
    $amountHtml  = isset($object->opp_amount) ? price((float) $object->opp_amount, 0, $langs, 1, -1, -1, $conf->currency) : '';

    $out  = '<div class="reedcrm-opp-cell">';
    $out .= '<div class="reedcrm-opp-cell-row">' . $statusHtml . '</div>';
    $out .= '<div class="reedcrm-opp-cell-row">' . $percentHtml . '</div>';
    if ($amountHtml !== '') {
        $out .= '<div class="reedcrm-opp-cell-row reedcrm-opp-cell-amount"><i class="fas fa-coins"></i> ' . $amountHtml . '</div>';
    }
    $out .= '</div>';

    return $out;
}

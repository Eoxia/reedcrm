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
    $out = '';

    $thirdPartyName  = !empty($object->options_reedcrm_lastname)  ? dol_escape_htmltag($object->options_reedcrm_lastname)  : '';
    $thirdPartyName2 = !empty($object->options_reedcrm_firstname) ? dol_escape_htmltag($object->options_reedcrm_firstname) : '';
    $thirdPartyEmail = !empty($object->options_reedcrm_email)     ? dol_escape_htmltag($object->options_reedcrm_email)     : '';
    $thirdPartyPhone = !empty($object->options_projectphone)      ? dol_escape_htmltag($object->options_projectphone)      : '';

    $out .= '<div class="reedcrm-plist-coordonnees">';
    $out .= '<div class="reedcrm-plist-coordonnees-box">';

    $thirdPartyName = $thirdPartyName ? ($thirdPartyName . ' ' . $thirdPartyName2) : 'N/A';
    $out .= '<div class="reedcrm-plist-coordonnees-name">' . $thirdPartyName . '</div>';
    $out .= '<div class="reedcrm-plist-coordonnees-email"><i class="fas fa-envelope"></i>' . ($thirdPartyEmail ?: 'N/A') . '</div>';
    $out .= '<div class="reedcrm-plist-coordonnees-phone"><i class="fas fa-phone-alt"></i>' . ($thirdPartyPhone ?: 'N/A') . '</div>';
    $out .= '</div>';

    $out .= '<div class="reedcrm-plist-coordonnees-actions">';
    if ($thirdPartyPhone) {
        $out .= '<a href="tel:' . preg_replace('/\s+/', '', $object->phone) . '" class="reedcrm-plist-coordonnees-btn"><i class="fas fa-phone-alt"></i></a>';
    } else {
        $out .= '<div class="reedcrm-plist-coordonnees-btn disabled"><i class="fas fa-phone-alt"></i></div>';
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
    global $db, $langs;

    $statusId = $object->fk_opp_status;

    if (empty($statusId)) {
        return '';
    }

    $label = dol_getIdFromCode($db, $statusId, 'c_lead_status', 'rowid', 'label');
    $code  = dol_getIdFromCode($db, $statusId, 'c_lead_status', 'rowid', 'code');

    return '<span class="reedcrm-opp-status reedcrm-opp-status-' . dol_escape_htmltag($code) . '">' . dol_escape_htmltag($langs->trans($label)) . '</span>';
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
 * Render the propal status field via getLibStatut().
 *
 * @param  array        $parameters Hook parameters (key, context, ...)
 * @param  CommonObject $object     The object
 * @return string                   HTML output
 */
function reedcrm_field_propal_status(array $parameters, CommonObject $object): string
{
    return $object->getLibStatut(5);
}

<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/view/eventpro/eventpro_tab_content.tpl.php
 * \ingroup reedcrm
 * \brief   eventPro tab bar and the body of the active tab, shared by the desktop card
 *          (core/tpl/view/eventpro/view_eventpro_actioncomm.tpl.php) and the mobile App form
 *          (view/frontend/pwa_relaunch.php). Expects the eventPro page context ($object, $id,
 *          $fromType, $currentTab, $form, $formProject, $formTicket, $permissiontoadd).
 *          $eventProTabExtraParams may carry extra query params to keep across tab switches.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $conf, $langs, $user;

$eventProTabExtraParams = $eventProTabExtraParams ?? '';

print showEventProTabs($id, $fromType, $currentTab, $eventProTabExtraParams);

if ($currentTab == 'note') {
    require_once __DIR__ . '/view_eventpro_actioncomm_note.tpl.php';
}

if ($currentTab == 'email') {
    $originalProjectAddonPdf = getDolGlobalString('PROJECT_ADDON_PDF');
    if ($object->element == 'project' && !empty($originalProjectAddonPdf)) {
        $conf->global->PROJECT_ADDON_PDF = '';
    }

    $modelmail    = 'thirdparty';
    $defaulttopic = 'InformationMessage';
    if ($object->element == 'project') {
        $diroutput = $conf->project->multidir_output[$object->entity] . '/' . dol_sanitizeFileName($object->ref);
    } else {
        $diroutput = '';
    }
    $trackid      = $object->element . $object->id;
    $action       = 'presend';

    require_once DOL_DOCUMENT_ROOT . '/core/tpl/card_presend.tpl.php';

    if ($object->element == 'project' && isset($originalProjectAddonPdf)) {
        $conf->global->PROJECT_ADDON_PDF = $originalProjectAddonPdf;
    }
}

if ($currentTab == 'ticket' && isModEnabled('ticket')) {
    require_once __DIR__ . '/view_eventpro_ticket.tpl.php';
}

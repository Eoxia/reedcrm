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
 * \file    ajax/save_kpi_layout.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint to save/reset the per-user opportunity KPI banner layout (order + hidden cards)
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';

header('Content-Type: application/json');

global $conf, $db, $user;

if (empty($user->id)) {
    echo json_encode(['success' => false, 'error' => 'NotLoggedIn']);
    exit;
}

$action = GETPOST('action', 'aZ09');
$key    = 'REEDCRM_KPI_LAYOUT';

if ($action === 'save') {
    $layoutRaw = htmlspecialchars_decode(GETPOST('layout', 'restricthtml'), ENT_QUOTES);
    $decoded   = json_decode($layoutRaw, true);
    if (!is_array($decoded)) {
        echo json_encode(['success' => false, 'error' => 'InvalidLayout']);
        exit;
    }

    // Keep only simple card ids in order/hidden
    $clean = ['order' => [], 'hidden' => []];
    foreach (['order', 'hidden'] as $listKey) {
        if (!empty($decoded[$listKey]) && is_array($decoded[$listKey])) {
            foreach ($decoded[$listKey] as $id) {
                $id = preg_replace('/[^a-z0-9_]/i', '', (string) $id);
                if ($id !== '') {
                    $clean[$listKey][] = $id;
                }
            }
        }
    }

    $res = dol_set_user_param($db, $conf, $user, [$key => json_encode($clean)]);
    echo json_encode($res > 0 ? ['success' => true] : ['success' => false, 'error' => 'SaveFailed']);
    exit;
}

if ($action === 'set_status_display') {
    $mode = GETPOST('mode', 'aZ09');
    $mode = in_array($mode, ['badge', 'dot'], true) ? $mode : 'badge';
    $res  = dol_set_user_param($db, $conf, $user, ['REEDCRM_STATUS_DISPLAY' => $mode]);
    echo json_encode($res > 0 ? ['success' => true] : ['success' => false, 'error' => 'SaveFailed']);
    exit;
}

if ($action === 'reset') {
    // An empty value makes dol_set_user_param delete the parameter
    $res = dol_set_user_param($db, $conf, $user, [$key => '']);
    echo json_encode($res > 0 ? ['success' => true] : ['success' => false, 'error' => 'ResetFailed']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'UnknownAction']);

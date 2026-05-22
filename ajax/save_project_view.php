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
 * \file    ajax/save_project_view.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint to save/delete per-user saved views (stored in llx_user_param)
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
$prefix = 'REEDCRM_VIEW_PROJECT_';

if ($action === 'save') {
    $label = trim(GETPOST('label', 'restricthtml'));
    if ($label === '') {
        echo json_encode(['success' => false, 'error' => 'EmptyLabel']);
        exit;
    }

    // Keep only safe querystring characters (search params)
    $query = preg_replace('/[^A-Za-z0-9_\-=&%\[\].]/', '', GETPOST('query', 'restricthtml'));

    $slug = trim(strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $label)), '_');
    if ($slug === '') {
        $slug = (string) dol_now();
    }

    $value = json_encode(['label' => $label, 'query' => $query]);
    $res   = dol_set_user_param($db, $conf, $user, [$prefix . $slug => $value]);

    echo json_encode($res > 0 ? ['success' => true] : ['success' => false, 'error' => 'SaveFailed']);
    exit;
}

if ($action === 'delete') {
    $key = GETPOST('key', 'alphanohtml');
    if (strpos($key, $prefix) !== 0) {
        echo json_encode(['success' => false, 'error' => 'InvalidKey']);
        exit;
    }

    // An empty value makes dol_set_user_param delete the parameter
    $res = dol_set_user_param($db, $conf, $user, [$key => '']);

    echo json_encode($res > 0 ? ['success' => true] : ['success' => false, 'error' => 'DeleteFailed']);
    exit;
}

echo json_encode(['success' => false, 'error' => 'UnknownAction']);

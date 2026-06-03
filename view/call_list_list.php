<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    view/call_list_list.php
 * \ingroup reedcrm
 * \brief   Backward-compatible redirect to the generic saturne list for call lists
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// The call list now uses the generic saturne list page.
// Keep this entry point so existing bookmarks and back-to-list links keep working,
// forwarding any extra query parameters (filters, sort, ...) to the generic list.
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$target      = dol_buildpath('/custom/saturne/view/saturne_list.php', 1) . '?object_type=call_list';
if (!empty($queryString)) {
    $target .= '&' . $queryString;
}

header('Location: ' . $target);
exit;

<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    view/newsletter/newsletter_list.php
 * \ingroup reedcrm
 * \brief   Entry point of the list of the newsletters, shown by the generic saturne list
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// The back-to-list links of Saturne point here: the filters, sort... of the query string go along
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$target      = dol_buildpath('/saturne/view/saturne_list.php', 1) . '?object_type=newsletter';
if (!empty($queryString)) {
    $target .= '&' . preg_replace('/(^|&)object_type=[^&]*/', '', $queryString);
}

header('Location: ' . $target);
exit;

<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    view/reedcrm_imported_projects.php
 * \ingroup reedcrm
 * \brief   List of previous project imports
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_function.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Security check - Protection if external user
$permissionToRead = $user->rights->reedcrm->adminpage->read;
saturne_check_access($permissionToRead);

$reedcrmEntityDir = '';
if (!empty($conf->reedcrm->multidir_output[$conf->entity])) {
    $reedcrmEntityDir = $conf->reedcrm->multidir_output[$conf->entity];
} else {
    $reedcrmEntityDir = DOL_DATA_ROOT . '/reedcrm/' . ((int) $conf->entity);
}

$importHistoryDir = $reedcrmEntityDir . '/import/project';

/*
 * View
 */

$title   = $langs->trans('ImportHistory');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

print load_fiche_titre($title, '<a href="' . dol_buildpath('/custom/reedcrm/view/reedcrmimport.php', 1) . '" class="butAction">' . $langs->trans('NewImport') . '</a>', 'wrench');

$historyFiles = [];
if (is_dir($importHistoryDir)) {
    $historyFiles = dol_dir_list($importHistoryDir, 'files', 1, '', '', 'date', SORT_DESC);
}

if (empty($historyFiles)) {
    print '<div class="opacitymedium">' . $langs->trans('None') . '</div>';
} else {
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans('Tag') . '</td>';
    print '<td>' . $langs->trans('File') . '</td>';
    print '<td class="center">' . $langs->trans('Date') . '</td>';
    print '<td class="center">' . $langs->trans('NbLines') . '</td>';
    print '</tr>';

    $categoryCache = [];
    foreach ($historyFiles as $fileInfo) {
        $relative = ltrim(str_replace($importHistoryDir, '', $fileInfo['fullname']), '/\\');
        $parts = preg_split('#[\\/]+#', $relative, 2);
        $folderName = $parts[0] ?: '';
        $catId = 0;

        if (is_numeric($folderName) && (int) $folderName > 0) {
            $catId = (int) $folderName;
        }

        $fileName = $fileInfo['name'];
        $downloadPath = 'import/project/' . $folderName . '/' . $fileName;
        $downloadUrl = DOL_URL_ROOT . '/document.php?modulepart=reedcrm&attachment=1&file=' . urlencode($downloadPath);

        $tagDisplay = '-';
        if ($catId > 0) {
            if (!array_key_exists($catId, $categoryCache)) {
                $catObj = new Categorie($db);
                if ($catObj->fetch($catId) > 0) {
                    $categoryCache[$catId] = $catObj;
                } else {
                    $categoryCache[$catId] = null;
                }
            }
            if (!empty($categoryCache[$catId])) {
                $label = $categoryCache[$catId]->label;
                $listUrl = DOL_URL_ROOT . '/projet/list.php?search_category_project_list[]=' . $catId;
                $tagDisplay = '<a class="badge badge-status4" href="' . $listUrl . '">' . dol_escape_htmltag($label) . '</a>';
            }
        }

        print '<tr class="oddeven">';
        print '<td>' . $tagDisplay . '</td>';
        print '<td><a href="' . $downloadUrl . '">' . dol_escape_htmltag($fileName) . '</a></td>';
        $lineCount = reedcrm_count_csv_lines($fileInfo['fullname']);

        print '<td class="center">' . dol_print_date($fileInfo['date'] ?: dol_now(), 'dayhour') . '</td>';
        print '<td class="center">' . ($lineCount !== null ? (int) $lineCount : '-') . '</td>';
        print '</tr>';
    }

    print '</table>';
    print '</div>';
}

llxFooter();
$db->close();

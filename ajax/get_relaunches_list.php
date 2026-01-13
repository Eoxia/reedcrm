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
 * \file    ajax/get_relaunches_list.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint to get filtered list of relaunches for tooltip
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '0');
}

// Load Dolibarr environment
if (file_exists('../../main.inc.php')) {
    require_once __DIR__ . '/../../main.inc.php';
} elseif (file_exists('../../../main.inc.php')) {
    require_once __DIR__ . '/../../../main.inc.php';
} else {
    die('Include of main fails');
}

global $conf, $db, $langs, $user;
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

// Security check
if (!$user->hasRight('agenda', 'myactions', 'read') && !$user->hasRight('agenda', 'allactions', 'read')) {
    top_httphead('application/json');
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$projectId = GETPOSTINT('project_id');
$actionType = GETPOST('action_type', 'aZ09'); // AC_TEL, AC_EMAIL, AC_RDV, or other
$socid = GETPOSTINT('socid');

if (empty($projectId) || empty($actionType)) {
    top_httphead('application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Load project
$project = new Project($db);
if ($project->fetch($projectId) <= 0) {
    top_httphead('application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Project not found']);
    exit;
}

$filter = ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . ((int) $conf->global->REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG) . ')';

$actionComm = new ActionComm($db);
$actionComms = $actionComm->getActions($socid ?: $project->socid, $projectId, 'project', $filter, 'a.datec');

if (is_string($actionComms)) {
    top_httphead('application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error fetching actions: ' . $actionComms]);
    exit;
}

ob_start();

if (is_array($actionComms) && !empty($actionComms)) {
    print '<div class="reedcrm-relaunch-tooltip-content">';
    print '<table class="noborder centpercent">';

    foreach ($actionComms as $ac) {
        $matchesType = false;
        if ($actionType == 'AC_TEL' && ($ac->type_code == 'AC_TEL' || (isset($ac->code) && $ac->code == 'AC_TEL'))) {
            $matchesType = true;
        } elseif ($actionType == 'AC_EMAIL' && ($ac->type_code == 'AC_EMAIL' || (isset($ac->code) && $ac->code == 'AC_EMAIL'))) {
            $matchesType = true;
        } elseif ($actionType == 'AC_RDV' && ($ac->type_code == 'AC_RDV' || (isset($ac->code) && $ac->code == 'AC_RDV'))) {
            $matchesType = true;
        } elseif ($actionType != 'AC_TEL' && $actionType != 'AC_EMAIL' && $actionType != 'AC_RDV') {
            // For "other" type, exclude AC_TEL, AC_EMAIL, AC_RDV
            if ($ac->type_code != 'AC_TEL' && $ac->type_code != 'AC_EMAIL' && $ac->type_code != 'AC_RDV' &&
                (!isset($ac->code) || ($ac->code != 'AC_TEL' && $ac->code != 'AC_EMAIL' && $ac->code != 'AC_RDV'))) {
                $matchesType = true;
            }
        }

        if (!$matchesType) {
            continue;
        }

        $contactName = '';
        if (!empty($ac->contact_id)) {
            require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
            $contact = new Contact($db);
            if ($contact->fetch($ac->contact_id) > 0) {
                $contactName = $contact->getFullName($langs);
            }
        }

        $userName = '';
        if (!empty($ac->userownerid)) {
            require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
            $userOwner = new User($db);
            if ($userOwner->fetch($ac->userownerid) > 0) {
                $userName = $userOwner->getFullName($langs);
            }
        }

        print '<tr class="oddeven">';
        print '<td class="nowrap" style="min-width: 150px;">';
        print dol_print_date($ac->datep, 'dayhour', 'tzuser');
        print '</td>';
        print '<td class="tdoverflowmax200">';
        print '<strong>' . dol_escape_htmltag($ac->label) . '</strong>';
        if (!empty($ac->note_private)) {
            $note = dolGetFirstLineOfText(dol_string_nohtmltag($ac->note_private, 1));
            print '<br><span class="opacitymedium">' . dol_escape_htmltag(dol_trunc($note, 80)) . '</span>';
        }
        print '</td>';
        print '<td class="nowrap">';
        if ($contactName) {
            print '<span class="opacitymedium">' . img_picto('', 'contact', 'class="pictofixedwidth"') . ' ' . dol_escape_htmltag($contactName) . '</span>';
        }
        if ($userName) {
            if ($contactName) print '<br>';
            print '<span class="opacitymedium">' . img_picto('', 'user', 'class="pictofixedwidth"') . ' ' . dol_escape_htmltag($userName) . '</span>';
        }
        print '</td>';
        if (isset($ac->percentage) && $ac->percentage >= 100) {
            print '<td class="center">';
            print '<span class="badge badge-status4">' . $langs->trans('Done') . '</span>';
            print '</td>';
        } elseif (isset($ac->percentage) && $ac->percentage > 0) {
            print '<td class="center">';
            print '<span class="badge">' . $ac->percentage . '%</span>';
            print '</td>';
        } else {
            print '<td></td>';
        }
        print '</tr>';
    }

    print '</table>';
    print '</div>';
} else {
    print '<div class="reedcrm-relaunch-tooltip-empty">' . $langs->trans('NoEvents') . '</div>';
}

$html = ob_get_clean();

top_httphead('application/json');

echo json_encode(['success' => true, 'html' => $html]);
exit;


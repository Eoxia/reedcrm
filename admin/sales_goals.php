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
 * \file    admin/sales_goals.php
 * \ingroup reedcrm
 * \brief   ReedCRM sales team and sales goals config page.
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

require_once __DIR__ . '/../lib/reedcrm.lib.php';
require_once __DIR__ . '/../lib/reedcrm_sales_goals.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin', 'users']);

// Get parameters
$action     = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissiontoread = $user->hasRight('reedcrm', 'adminpage', 'read');

saturne_check_access($permissiontoread);

/*
 * Actions
 */

if ($action == 'set_config') {
    // The empty choice of the group selector posts -1
    dolibarr_set_const($db, 'REEDCRM_SALES_TEAM_GROUP', max(0, GETPOSTINT('sales_team_group')), 'integer', 0, '', $conf->entity);

    $defaultGoals = [];
    foreach (array_keys(REEDCRM_SALES_GOALS) as $goal) {
        $defaultGoals[$goal] = GETPOST('goal_' . $goal, 'alphanohtml');
    }

    // The personal goals come as goal_user[<user id>][<goal>], only the members still listed are posted
    $personalGoals = [];
    $postedGoals   = GETPOST('goal_user', 'array');
    if (is_array($postedGoals)) {
        foreach ($postedGoals as $userId => $userGoals) {
            if (!is_array($userGoals)) {
                continue;
            }
            foreach (array_keys(REEDCRM_SALES_GOALS) as $goal) {
                $personalGoals[(int) $userId][$goal] = dol_string_nohtmltag((string) ($userGoals[$goal] ?? ''));
            }
        }
    }

    reedcrm_sales_goals_save($db, $defaultGoals, $personalGoals);

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$form = new Form($db);

$title    = $langs->trans('ModuleSetup', 'ReedCRM');
$help_url = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'reedcrm_color@reedcrm');

// Configuration header
$head = reedcrm_admin_prepare_head();
print dol_get_fiche_head($head, 'sales_goals', $title, -1, 'reedcrm_color@reedcrm');

$defaultGoals  = reedcrm_sales_goals_get_defaults();
$personalGoals = reedcrm_sales_goals_get_personal();
$teamUserIds   = reedcrm_sales_team_get_user_ids($db);

print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="set_config">';

// Sales team
print load_fiche_titre($langs->trans('SalesTeam'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameters') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('SalesTeamGroup');
print '</td><td>';
print $langs->transnoentities('SalesTeamGroupDescription');
print '</td><td>';
print $form->select_dolgroups(getDolGlobalInt('REEDCRM_SALES_TEAM_GROUP'), 'sales_team_group', 1);
print '</td></tr>';

print '</table>';

// Goals of the whole team
print load_fiche_titre($langs->trans('SalesGoals'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameters') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '</tr>';

foreach (array_keys(REEDCRM_SALES_GOALS) as $goal) {
    $goalKey = reedcrm_sales_goal_label_key($goal);

    print '<tr class="oddeven"><td>';
    print $langs->trans($goalKey);
    print '</td><td>';
    print $langs->transnoentities($goalKey . 'Description');
    print '</td><td>';
    print '<input type="number" min="0" step="any" class="width100" name="goal_' . $goal . '" value="' . (!empty($defaultGoals[$goal]) ? dol_escape_htmltag((string) $defaultGoals[$goal]) : '') . '">';
    print '</td></tr>';
}

print '</table>';

// Personal goals of each member, only reachable once the team is a known group
print load_fiche_titre($langs->trans('SalesPersonalGoals'), '', '');

if (empty($teamUserIds)) {
    print '<div class="opacitymedium">' . $langs->trans('SalesPersonalGoalsNeedGroup') . '</div>';
} else {
    print '<div class="opacitymedium marginbottomonly">' . $langs->trans('SalesPersonalGoalsDescription') . '</div>';

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans('User') . '</td>';
    foreach (array_keys(REEDCRM_SALES_GOALS) as $goal) {
        print '<td class="center">' . $langs->trans(reedcrm_sales_goal_label_key($goal)) . '</td>';
    }
    print '</tr>';

    foreach ($teamUserIds as $teamUserId) {
        $teamUser = new User($db);
        if ($teamUser->fetch($teamUserId) <= 0) {
            continue;
        }

        print '<tr class="oddeven">';
        print '<td>' . $teamUser->getNomUrl(-1) . '</td>';
        foreach (array_keys(REEDCRM_SALES_GOALS) as $goal) {
            $value = $personalGoals[$teamUserId][$goal] ?? '';

            // The team value is the placeholder, an empty field keeps the member on it
            print '<td class="center">';
            print '<input type="number" min="0" step="any" class="width100" name="goal_user[' . $teamUserId . '][' . $goal . ']"';
            print ' value="' . dol_escape_htmltag((string) $value) . '"';
            print ' placeholder="' . dol_escape_htmltag(!empty($defaultGoals[$goal]) ? (string) $defaultGoals[$goal] : '') . '">';
            print '</td>';
        }
        print '</tr>';
    }

    print '</table>';
    print '</div>';
}

print '<div class="tabsAction"><input type="submit" class="butAction" name="save" value="' . $langs->trans('Save') . '"></div>';
print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();

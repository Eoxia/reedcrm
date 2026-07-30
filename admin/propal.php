<?php
/* Copyright (C) 2024-2025 EVARISK <technique@evarisk.com>
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
 * \file    admin/propal.php
 * \ingroup reedcrm
 * \brief   ReedCRM propal config page.
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
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once __DIR__ . '/../lib/reedcrm.lib.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin', 'categories']);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissiontoread = $user->hasRight('reedcrm','adminpage','read');
saturne_check_access($permissiontoread);

/*
 * Actions
 */

if (!empty($conf->global->REEDCRM_PROPAL_MODELS_ENABLED)) {
    // Check if categorie module is enabled
    if (empty($conf->categorie->enabled)) {
        dolibarr_set_const($db, 'MAIN_MODULE_CATEGORIE', 1, 'chaine', 0, '', $conf->entity);
        $conf->categorie->enabled = 1; // Update in memory for current page load
    }

    // Create Tag "Proposition modèle" for propals
    require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
    $cat = new Categorie($db);
    $res = $cat->fetch(0, 'Proposition modèle', Categorie::TYPE_PROPOSAL);
    if ($res <= 0) {
        $cat->label = 'Proposition modèle';
        $cat->type = Categorie::TYPE_PROPOSAL;
        $cat->visible = 1;
        $cat->create($user);
    }
    if (empty($conf->global->REEDCRM_PROPAL_MODEL_TAG_ID) && $cat->id > 0) {
        dolibarr_set_const($db, 'REEDCRM_PROPAL_MODEL_TAG_ID', $cat->id, 'chaine', 0, '', $conf->entity);
        $conf->global->REEDCRM_PROPAL_MODEL_TAG_ID = $cat->id;
    }
}

if (!empty($conf->global->CATEGORY_EDIT_IN_MENU_NOT_IN_POPUP)) {
    $sql = "SELECT rowid, note, visible FROM " . MAIN_DB_PREFIX . "const WHERE name = 'CATEGORY_EDIT_IN_MENU_NOT_IN_POPUP'";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        if ($obj->visible == 0 || empty($obj->note)) {
            $db->query("UPDATE " . MAIN_DB_PREFIX . "const SET visible = 1, note = 'Ajouté par ReedCRM-" . date('YmdHis') . "' WHERE name = 'CATEGORY_EDIT_IN_MENU_NOT_IN_POPUP'");
        }
    }
}

if ($action == 'update_tag') {
    $tag_id = GETPOST('reedcrm_propal_model_tag_id', 'int');
    dolibarr_set_const($db, 'REEDCRM_PROPAL_MODEL_TAG_ID', $tag_id, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

/*
 * View
 */

$title    = $langs->trans('ModuleSetup', 'ReedCRM');
$help_url = 'FR:Module_ReedCRM';

saturne_header(0,'', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'reedcrm_color@reedcrm');

// Configuration header
$head = reedcrm_admin_prepare_head();
print dol_get_fiche_head($head, 'propal', $title, -1, 'reedcrm_color@reedcrm');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update_tag">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">Propositions modèles</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print 'Activation du module Tags/catégories qui permettra d\'identifier les propositions modèles';
print '<br><small class="opacitymedium">(Création du tag par défaut Proposition modèle)</small>';
print '</td>';
print '<td class="right">' . ajax_constantonoff('REEDCRM_PROPAL_MODELS_ENABLED', [], null, 0, 0, 1) . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print 'Tag utilisé pour identifier les modèles de propositions';
print '</td>';
print '<td class="right">';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
$tmpcat = new Categorie($db);
$type_id = isset($tmpcat->MAP_ID[Categorie::TYPE_PROPOSAL]) ? $tmpcat->MAP_ID[Categorie::TYPE_PROPOSAL] : 23;
$sql = "SELECT rowid, label FROM " . MAIN_DB_PREFIX . "categorie WHERE type = " . (int)$type_id . " AND entity IN (0, " . $conf->entity . ") ORDER BY label";
$resql = $db->query($sql);
print '<select name="reedcrm_propal_model_tag_id" class="flat" style="margin-right: 10px; min-width: 150px;">';
print '<option value="0">-- ' . $langs->trans("Select") . ' --</option>';
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $selected = (!empty($conf->global->REEDCRM_PROPAL_MODEL_TAG_ID) && $conf->global->REEDCRM_PROPAL_MODEL_TAG_ID == $obj->rowid) ? ' selected="selected"' : '';
        print '<option value="' . $obj->rowid . '"' . $selected . '>' . dol_escape_htmltag($obj->label) . '</option>';
    }
}
print '</select>';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print 'Affichage du menu Tags/catégories';
print '<br><small class="opacitymedium">(Ce toggle ajoute ou retire CATEGORY_EDIT_IN_MENU_NOT_IN_POPUP dans l\'onglet Divers)</small>';
print '</td>';
print '<td class="right">' . ajax_constantonoff('CATEGORY_EDIT_IN_MENU_NOT_IN_POPUP', [], null, 0, 0, 1) . '</td>';
print '</tr>';

print '</table>';
print '</form>';

llxFooter();
$db->close();

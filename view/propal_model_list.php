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
 * \file    view/propal_model_list.php
 * \ingroup reedcrm
 * \brief   List of the proposal templates, the proposals carrying the template tag
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
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
if (isModEnabled('categorie')) {
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
}

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_propal_model.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['propal', 'companies']);

// Get parameters
$action     = GETPOSTISSET('action') ? GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = '';                                                          // No mass action on the templates, see below

// Get list parameters
$toselect                                   = [];
[$confirm, $contextpage, $optioncss, $mode] = ['', '', '', ''];
$listParameters                             = saturne_load_list_parameters('propalmodel');
foreach ($listParameters as $listParameterKey => $listParameter) {
    $$listParameterKey = $listParameter;
}

// The hooks of the module restrict the list to the templates on this context: it must not be changed from the url
$contextpage = 'propalmodellist';

// Get pagination parameters
[$limit, $page, $offset] = [0, 0, 0];
[$sortfield, $sortorder] = ['', ''];
$paginationParameters    = saturne_load_pagination_parameters();
foreach ($paginationParameters as $paginationParameterKey => $paginationParameter) {
    $$paginationParameterKey = $paginationParameter;
}

// Initialize technical objects
$object      = new Propal($db);
$extrafields = new ExtraFields($db);
if (isModEnabled('categorie')) {
    $categorie = new Categorie($db);
}

// Initialize view objects
$form = new Form($db);

$hookmanager->initHooks([$contextpage, 'saturnelist']); // Note that conf->hooks_modules contains array

// Fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

if (isModEnabled('categorie')) {
    $searchCategories = GETPOST('search_category_' . $object->element . '_list', 'array');
}

// Default sort order (if not yet defined by previous GETPOST)
$sortfield = $paginationParameters['sortfield'] ?: 't.ref';
$sortorder = $paginationParameters['sortorder'] ?: 'DESC';

// Definition of the columns. A template is edited on its card, so the list is read only, and it shows no total:
// the amounts of templates do not add up to anything
foreach ($object->fields as $key => $val) {
    $object->fields[$key]['noedit'] = 1;
    unset($object->fields[$key]['isameasure']);
}
foreach (['ref', 'ref_client', 'fk_soc', 'datep', 'total_ht', 'fk_statut'] as $key) {
    $object->fields[$key]['visible'] = 1;
}
$object->fields['fk_soc']['label']            = 'Company';
$object->fields['total_ht']['label']          = 'AmountHT';
$object->fields['total_ht']['type']           = 'price';
$object->fields['fk_statut']['csslist']       = 'center';
$object->fields['fk_statut']['arrayofkeyval'] = [
    Propal::STATUS_DRAFT     => 'PropalStatusDraftShort',
    Propal::STATUS_VALIDATED => 'PropalStatusValidatedShort',
    Propal::STATUS_SIGNED    => 'PropalStatusSignedShort',
    Propal::STATUS_NOTSIGNED => 'PropalStatusNotSignedShort',
    Propal::STATUS_BILLED    => 'PropalStatusBilledShort',
];

// The label tells the templates apart: it is an extrafield, whose column the list can neither resize nor move, so it
// is shown as a column of the proposal named after the alias the extrafield is selected under, which sorts the list.
// Its search input carries the name of the extrafield filter: the hooks of the module do that filtering, not the
// generic one of the extrafields
unset($search_array_options['search_options_reedcrm_propal_label']);
$object->fields['options_reedcrm_propal_label'] =['type' => 'varchar(255)', 'label' => 'ReedCRMPropalLabel', 'enabled' => 1, 'position' => 21, 'visible' => 1, 'noedit' => 1, 'otheralias' => '', 'csslist' => 'minwidth200'];

// Button creating a proposal from the template, rendered by the hooks of the module. The type is only there for the
// shape of $fields
$object->fields['create_from_model'] = ['type' => 'varchar(255)', 'label' => 'PropalModelCreate', 'enabled' => 1, 'position' => 1000, 'visible' => 1, 'noedit' => 1, 'disablesort' => 1, 'disablesearch' => 1, 'csslist' => 'center'];

$excludeFields = ['options_reedcrm_propal_label', 'create_from_model'];

// Initialize array of search criterias
$searchAll = trim(GETPOST('search_all'));
$search    = [];
foreach ($object->fields as $key => $val) {
    if (GETPOST('search_' . $key, 'alpha') !== '') {
        $search[$key] = GETPOST('search_' . $key, 'alpha');
    }
    if (isset($val['type']) && in_array($val['type'], ['date', 'datetime', 'timestamp'])) {
        $search[$key . '_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_' . $key . '_dtstartmonth'), GETPOSTINT('search_' . $key . '_dtstartday'), GETPOSTINT('search_' . $key . '_dtstartyear'));
        $search[$key . '_dtend']   = dol_mktime(23, 59, 59, GETPOSTINT('search_' . $key . '_dtendmonth'), GETPOSTINT('search_' . $key . '_dtendday'), GETPOSTINT('search_' . $key . '_dtendyear'));
    }
}

// List of fields to search into when doing a "search in all"
$fieldsToSearchAll = [];
foreach ($object->fields as $key => $val) {
    if (!empty($val['searchall'])) {
        $fieldsToSearchAll['t.' . $key] = $val['label'];
    }
}

// Definition of array of fields for columns
foreach ($object->fields as $key => $val) {
    if (!empty($val['visible'])) {
        $visible = (int) dol_eval((string) $val['visible']);
        $arrayfields['t.' . $key] = [
            'label'    => $val['label'],
            'checked'  => (($visible < 0) ? 0 : 1),
            'enabled'  => ($visible != 3 && dol_eval((string) $val['enabled'])),
            'position' => $val['position'],
            'help'     => $val['help'] ?? '',
        ];
    }
}

// Extra fields
require_once DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_array_fields.tpl.php';

// The label already has its column, the other extrafields are offered in the column picker only
unset($arrayfields['ef.reedcrm_propal_label']);
foreach ($arrayfields as $key => $val) {
    if (strpos($key, 'ef.') === 0) {
        $arrayfields[$key]['checked'] = 0;
    }
}

$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields    = dol_sort_array($arrayfields, 'position');

// The generic proposal list keeps its column layout under the element: this one needs its own
$listLayoutId = 'propal_model';

// The "New" button makes a new template: the proposal is created with the template tag
$tagID     = reedcrm_propal_model_get_tag_id($db);
$createUrl = DOL_URL_ROOT . '/comm/propal/card.php?action=create' . ($tagID > 0 ? '&categories[]=' . $tagID : '');

// Permissions
$permissiontoread   = $user->hasRight('propal', 'lire');
$permissiontoadd    = $user->hasRight('propal', 'creer');
$permissiontodelete = 0;

// Security check
saturne_check_access($permissiontoread, $object);

// The list header always offers to archive the selected objects, which a proposal cannot be: a template is
// deleted from its card, so the list offers no mass action at all
$_GET['nomassaction'] = 1;

/*
 * Actions
 */

$parameters = ['arrayfields' => &$arrayfields];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    // Selection of new fields
    require_once DOL_DOCUMENT_ROOT . '/core/actions_changeselectedfields.inc.php';

    // Purge search criteria
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
        foreach ($object->fields as $key => $val) {
            $search[$key] = '';
            if (isset($val['type']) && in_array($val['type'], ['date', 'datetime', 'timestamp'])) {
                $search[$key . '_dtstart'] = '';
                $search[$key . '_dtend']   = '';
            }
        }
        $searchAll            = '';
        $toselect             = [];
        $search_array_options = [];
        $searchCategories     = [];
    }
}

/*
 * View
 */

$title   = $langs->trans('PropalModelList');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl, '', 0, 0, [], [], '', 'mod-' . $object->element . ' page-list bodyforlist');

$tag      = new Categorie($db);
$tagLabel = ($tagID > 0 && $tag->fetch($tagID) > 0) ? $tag->label : REEDCRM_PROPAL_MODEL_DEFAULT_TAG_LABEL;
// trans() encodes the accents of its parameters but keeps some of the tags they hold: only those are escaped here
print '<div class="info">' . $langs->trans('PropalModelListHelp', dol_escape_htmltag($tagLabel, 0, 0, '', 1)) . '</div>';

require_once dol_buildpath('/saturne/core/tpl/list/objectfields_list_build_sql_select.tpl.php');
require_once dol_buildpath('/saturne/core/tpl/list/objectfields_list_header.tpl.php');
require_once dol_buildpath('/saturne/core/tpl/list/objectfields_list_search_input.tpl.php');
require_once dol_buildpath('/saturne/core/tpl/list/objectfields_list_search_title.tpl.php');
require_once dol_buildpath('/saturne/core/tpl/list/objectfields_list_loop_object.tpl.php');
require_once dol_buildpath('/saturne/core/tpl/list/objectfields_list_footer.tpl.php');

// End of page
llxFooter();
$db->close();

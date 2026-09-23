<?php
// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

global $langs, $conf, $user, $db;

$langs->loadLangs(array("propal", "companies"));

// Access control
if (!$user->rights->propal->lire) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_company = GETPOST('search_company', 'alpha');

if (!$sortfield) $sortfield = "p.ref";
if (!$sortorder) $sortorder = "DESC";

// View
llxHeader('', 'Modèles de propositions', '');

$form = new Form($db);

print load_fiche_titre('Modèles de propositions', '', 'propal');

print '<div class="info">Retrouvez ici toutes les propositions commerciales marquées du tag <b>Proposition modèle</b>. Cliquez sur <i>Créer</i> pour générer une nouvelle proposition basée sur le modèle.</div>';
print '<br>';

// List
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="' . $sortfield . '">';
print '<input type="hidden" name="sortorder" value="' . $sortorder . '">';

print '<div class="div-table-responsive-no-min">';
print '<table class="tagtable liste">'."\n";

print '<tr class="liste_titre">';
print_liste_field_titre('Ref', $_SERVER['PHP_SELF'], 'p.ref', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Libellé', $_SERVER['PHP_SELF'], 'pe.reedcrm_propal_label', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Company', $_SERVER['PHP_SELF'], 's.nom', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('RefCustomer', $_SERVER['PHP_SELF'], 'p.ref_client', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('Date', $_SERVER['PHP_SELF'], 'p.datep', '', '', '', $sortfield, $sortorder);
print_liste_field_titre('AmountHT', $_SERVER['PHP_SELF'], 'p.total_ht', '', '', '', $sortfield, $sortorder, 'right ');
print_liste_field_titre('Status', $_SERVER['PHP_SELF'], 'p.fk_statut', '', '', '', $sortfield, $sortorder, 'center ');
print_liste_field_titre('', $_SERVER['PHP_SELF'], '', '', '', '', '', '', 'center ');
print '</tr>';

// Search row
print '<tr class="liste_titre">';
print '<td class="liste_titre"><input type="text" class="flat maxwidth75" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '"></td>';
print '<td class="liste_titre"><input type="text" class="flat" name="search_label" value="' . dol_escape_htmltag($search_label) . '"></td>';
print '<td class="liste_titre"><input type="text" class="flat" name="search_company" value="' . dol_escape_htmltag($search_company) . '"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre"></td>';
print '<td class="liste_titre maxwidthsearch center">';
$searchpicto = $form->showFilterAndCheckAddButtons(0);
print $searchpicto;
print '</td>';
print '</tr>';

$sql = "SELECT p.rowid, p.ref, p.total_ht, p.fk_statut, p.datep, p.ref_client, s.nom as name, pe.reedcrm_propal_label";
$sql .= " FROM " . MAIN_DB_PREFIX . "propal as p";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "propal_extrafields as pe ON pe.fk_object = p.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON p.fk_soc = s.rowid";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie_propal as cp ON cp.fk_propal = p.rowid";
$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie as c ON c.rowid = cp.fk_categorie";
if (!empty($conf->global->REEDCRM_PROPAL_MODEL_TAG_ID)) {
    $sql .= " WHERE c.rowid = " . (int)$conf->global->REEDCRM_PROPAL_MODEL_TAG_ID . " AND p.entity IN (" . getEntity('propal') . ")";
} else {
    $sql .= " WHERE c.label = 'Proposition modèle' AND p.entity IN (" . getEntity('propal') . ")";
}

if ($search_ref) $sql .= natural_search("p.ref", $search_ref);
if ($search_label) $sql .= natural_search("pe.reedcrm_propal_label", $search_label);
if ($search_company) $sql .= natural_search("s.nom", $search_company);

$sql .= $db->order($sortfield, $sortorder);

$resql = $db->query($sql);
if ($resql) {
    $num = $db->num_rows($resql);
    if ($num > 0) {
        $i = 0;
        while ($i < $num) {
            $obj = $db->fetch_object($resql);
            
            $propal = new Propal($db);
            $propal->fetch($obj->rowid);
            $propal->fetch_thirdparty();

            print '<tr class="oddeven">';
            print '<td>' . $propal->getNomUrl(1) . '</td>';
            print '<td class="tdoverflowmax150">' . dol_escape_htmltag($obj->reedcrm_propal_label) . '</td>';
            print '<td class="tdoverflowmax150">' . $propal->thirdparty->getNomUrl(1) . '</td>';
            print '<td class="tdoverflowmax150">' . dol_escape_htmltag($obj->ref_client) . '</td>';
            print '<td>' . dol_print_date($db->jdate($obj->datep), 'day') . '</td>';
            print '<td class="right">' . price($obj->total_ht) . '</td>';
            print '<td class="center">' . $propal->getLibStatut(5) . '</td>';
            print '<td class="center">';
            print '<a class="butAction" href="' . DOL_URL_ROOT . '/comm/propal/card.php?id=' . $propal->id . '&action=clone">' . $langs->trans('Create') . '</a>';
            print '</td>';
            print '</tr>';
            $i++;
        }
    } else {
        print '<tr><td colspan="8" class="opacitymedium">Aucun modèle trouvé. Créez une proposition et ajoutez-lui le tag "Proposition modèle".</td></tr>';
    }
} else {
    dol_print_error($db);
}

print '</table>';
print '</div>';
print '</form>';

llxFooter();
$db->close();

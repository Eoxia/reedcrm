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
 * \file    view/reedcrmimport.php
 * \ingroup reedcrm
 * \brief   Import page of ReedCRM top menu
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../lib/reedcrm_function.lib.php';
require_once __DIR__ . '/../class/reedcrmnotifiy.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs();

// Get parameters
$action = (GETPOSTISSET('action') ? GETPOST('action', 'aZ09') : 'view');

// Initialize technical objects
$form          = new Form($db);
$facture       = new Facture($db);
$thirdparty    = new Societe($db);
$project       = new Project($db);
$actioncomm    = new ActionComm($db);
$reedcrmNotify = new ReedcrmNotify($db);
$contact       = new Contact($db);
// Security check - Protection if external user
$permissionToRead = $user->rights->reedcrm->adminpage->read;
saturne_check_access($permissionToRead);

$formconfirm = '';
$uploadDir = DOL_DATA_ROOT . '/reedcrm/import';
dol_mkdir($uploadDir);

$reedcrmEntityDir = '';
if (!empty($conf->reedcrm->multidir_output[$conf->entity])) {
    $reedcrmEntityDir = $conf->reedcrm->multidir_output[$conf->entity];
} else {
    $reedcrmEntityDir = DOL_DATA_ROOT . '/reedcrm/' . ((int) $conf->entity);
}
dol_mkdir($reedcrmEntityDir);

$importHistoryDir = $reedcrmEntityDir . '/import/project';
dol_mkdir($importHistoryDir);

/*
 * Actions
 */

if ($action == 'import_projects') {
    $uploadedFile = $_FILES['import_file'] ?? null;
    if (!empty($uploadedFile['tmp_name']) && $uploadedFile['error'] === UPLOAD_ERR_OK) {
        $sanitizedName = dol_sanitizeFileName($uploadedFile['name']);
        if (empty($sanitizedName)) {
            $sanitizedName = 'import.csv';
        }
        $uniqueName = dol_now() . '_' . $user->id . '_' . $sanitizedName;
        $uniqueName = dol_sanitizeFileName($uniqueName);
        $destPath = $uploadDir . '/' . $uniqueName;

        $resUpload = dol_move_uploaded_file($uploadedFile['tmp_name'], $destPath, 1, 0, $uploadedFile['error'], $uploadedFile['size']);
        if ($resUpload > 0) {
            $formquestion = [
                [
                    'type'  => 'hidden',
                    'name'  => 'import_file',
                    'value' => $uniqueName
                ],
                [
                    'type'     => 'text',
                    'label'    => $langs->trans('CategoryName'),
                    'name'     => 'category_name',
                    'required' => 1
                ]
            ];
            $formconfirm = $form->formconfirm(
                $_SERVER['PHP_SELF'],
                $langs->trans('ConfirmImportProjectsTitle'),
                $langs->trans('ConfirmImportProjectsQuestion'),
                'confirm_import_projects',
                $formquestion,
                '',
                1,
                400
            );
        } else {
            setEventMessages($langs->trans('ImportFileUploadError'), null, 'errors');
        }
    } else {
        setEventMessages($langs->trans('NoFileUploaded'), null, 'errors');
    }
    $action = 'view';
}

if ($action == 'confirm_import_projects') {
    $importFile = GETPOST('import_file', 'alpha');
    $categoryName = trim(GETPOST('category_name', 'alphanohtml'));

    if (empty($importFile) || empty($categoryName)) {
        setEventMessages($langs->trans('ImportParametersMissing'), null, 'errors');
        $action = 'view';
    } else {
        $fullPath = $uploadDir . '/' . $importFile;
        if (!is_readable($fullPath)) {
            setEventMessages($langs->trans('ImportFileNotFound'), null, 'errors');
            $action = 'view';
        } else {
            $category = new Categorie($db);
            $resCat = $category->fetch(0, $categoryName, 'project');
            if ($resCat <= 0) {
                $category->type = Categorie::TYPE_PROJECT;
                $category->label = $categoryName;
                $resCat = $category->create($user);
            }
            list($modProject) = saturne_require_objects_mod(['project' => $conf->global->PROJECT_ADDON]);

            if ($resCat > 0) {
                $categoryId = $category->id;
                $handle = fopen($fullPath, 'r');
                if ($handle) {
                    $firstLine = fgets($handle);
                    $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
                    $headers = array_map('trim', str_getcsv($firstLine, $delimiter));
                    $map = [];
                    foreach ($headers as $idx => $headerLabel) {
                        $map[strtolower($headerLabel)] = $idx;
                    }

                    $required = ['prenom', 'nom', 'email', 'tel', 'note'];
                    $missing = array_diff($required, array_keys($map));
                    if (!empty($missing)) {
                        fclose($handle);
                        dol_delete_file($fullPath);
                        setEventMessages($langs->trans('ImportFileInvalidColumns'), null, 'errors');
                    } else {
                        $total = 0;
                        $created = 0;
                        $errors = 0;

                        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                            if (count($row) == 1 && trim($row[0]) === '') {
                                continue;
                            }
                            $total++;
                            $description = dol_htmlentitiesbr(trim($row[$map['note']] ?? ''));
                            $mail = dol_htmlentitiesbr(trim($row[$map['email']] ?? ''));
                            $phone = dol_htmlentitiesbr(trim($row[$map['tel']] ?? ''));
                            $nom = dol_htmlentitiesbr(trim($row[$map['nom']] ?? ''));
                            $prenom = dol_htmlentitiesbr(trim($row[$map['prenom']] ?? ''));
                            $socid = !empty($map['socid']) ? (int)trim($row[$map['socid']] ?? 0) : 0;


                            $proj = new Project($db);
                            $defaultref = $modProject->getNextValue($thirdparty, $proj);
                            $proj->ref = $defaultref;
                            $proj->title = '';
                            $proj->description = $description;
                            $proj->array_options['reedcrm_lastname'] = $nom;
                            $proj->array_options['reedcrm_firstname'] = $prenom;
                            $proj->array_options['reedcrm_email'] = $mail;
                            $proj->array_options['projectphone'] = $phone;
                            $proj->status = Project::STATUS_DRAFT;
                            $proj->date_start = dol_now();
                            $proj->public = 1;

                            $resCreate = $proj->create($user);

                            if ($resCreate > 0) {
                                $resSetCat = $proj->setCategories(array($categoryId), Categorie::TYPE_PROJECT);
                                if ($resSetCat <= 0) {
                                    $errors++;
                                    continue;
                                }
                                $created++;
                            } else {
                                $errors++;
                            }
                        }

                        fclose($handle);

                        reedcrm_archive_import_file($fullPath, $categoryName, $importHistoryDir, $categoryId);

                        if ($created > 0) {
                            setEventMessages($langs->trans('ProjectsImported', $created, $total), null, 'mesgs');
                        }
                        if ($errors > 0) {
                            setEventMessages($langs->trans('ProjectsImportErrors', $errors), null, 'warnings');
                        }
                    }
                } else {
                    setEventMessages($langs->trans('ImportFileNotReadable'), null, 'errors');
                    dol_delete_file($fullPath);
                }
            } else {
                setEventMessages($langs->trans('CategoryCreationError'), $category->errors, 'errors');
                dol_delete_file($fullPath);
            }
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}


/*
 * View
 */

$title   = $langs->trans('Tools');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0,'', $title, $helpUrl);

print load_fiche_titre($title, '', 'wrench');

if (!empty($formconfirm)) {
    print $formconfirm;
}

print load_fiche_titre($langs->trans('ImportProjectsFromCSV'), '', '');

print '<form name="import-projects" id="import-projects" action="' . $_SERVER['PHP_SELF'] . '" method="POST" enctype="multipart/form-data">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="import_projects">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Name') . '</td>';
print '<td>' . $langs->trans('Description') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<tr class="oddeven"><td>';
print $langs->trans('ImportProjectsFromCSV');
print '</td><td>';
print $langs->trans('ImportProjectsDescription') . '<br><span class="opacitymedium">' . $langs->trans('CSVExpectedColumns') . '</span>';
print '</td>';
print '<td class="center">';
print '<input type="file" name="import_file" accept=".csv" required class="flat">';
print ' <input type="submit" class="button" name="import_projects" value="' . $langs->trans('UploadImport') . '">';
print '</td></tr>';

print '</table>';
print '</form>';

print '<br>';
print '<a href="' . dol_buildpath('/custom/reedcrm/view/reedcrm_imported_projects.php', 1) . '" class="butAction">' . $langs->trans('ViewImportHistory') . '</a>';

llxFooter();
$db->close();

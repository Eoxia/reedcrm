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
            // Build existing project categories for native multiselect
            $existingCats = [];
            $catTmp = new Categorie($db);
            $catList = $catTmp->rechercher(0, '', Categorie::TYPE_PROJECT, true);
            if (is_array($catList)) {
                foreach ($catList as $cat) {
                    $existingCats[$cat->id] = $cat->label;
                }
            }

            // Columns of the file, the user picks the one listing the people to assign (keyed by column number, 1 = first column)
            $columns                    = [];
            $defaultAssigneesColumn     = '';
            $defaultReminderLabelColumn = '';
            $defaultReminderDateColumn  = '';
            $defaultNoteColumn          = '';
            $handle                     = fopen($destPath, 'r');
            if ($handle) {
                list($delimiter, $headers) = reedcrm_read_import_csv_header($handle);
                foreach ($headers as $idx => $headerLabel) {
                    if ($headerLabel === '') {
                        continue;
                    }
                    $columns[$idx + 1] = $headerLabel;
                    $headerKey         = reedcrm_normalize_import_key($headerLabel);
                    if ($defaultAssigneesColumn === '' && preg_match('/^assigne|^affecte|qui qualifie/', $headerKey)) {
                        $defaultAssigneesColumn = (string) ($idx + 1);
                    }
                    if (strpos($headerKey, 'echeance') !== false) {
                        $defaultReminderDateColumn = $defaultReminderDateColumn ?: (string) ($idx + 1);
                    } elseif (preg_match('/rappel|relance|evenement|event|action a realiser/', $headerKey)) {
                        if (strpos($headerKey, 'date') !== false) {
                            $defaultReminderDateColumn = $defaultReminderDateColumn ?: (string) ($idx + 1);
                        } else {
                            $defaultReminderLabelColumn = $defaultReminderLabelColumn ?: (string) ($idx + 1);
                        }
                    }
                    if ($defaultNoteColumn === '' && preg_match('/^note\b|^score|probabilit|pourcentage/', $headerKey)) {
                        $defaultNoteColumn = (string) ($idx + 1);
                    }
                }
                // A "Note" column may as well hold free comments: it is only preselected when every value is a note from 0 to 10
                while ($defaultNoteColumn !== '' && ($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $noteValue = trim((string) ($row[(int) $defaultNoteColumn - 1] ?? ''));
                    if ($noteValue !== '' && reedcrm_parse_import_note($noteValue) === null) {
                        $defaultNoteColumn = '';
                    }
                }
                fclose($handle);
            }

            $formquestion = [
                [
                    'type'  => 'hidden',
                    'name'  => 'import_file',
                    'value' => $uniqueName
                ],
                [
                    'type'    => 'multiselect',
                    'label'   => $langs->trans('ChooseExistingTag'),
                    'name'    => 'existing_category_ids',
                    'values'  => $existingCats,
                    'default' => '',
                    'morecss' => 'minwidth300',
                ],
                [
                    'type'  => 'text',
                    'label' => $langs->trans('OrCreateNewTag'),
                    'name'  => 'category_name',
                ],
                [
                    'type'    => 'select',
                    'label'   => $form->textwithpicto($langs->trans('AssigneesColumn'), $langs->trans('AssigneesColumnHelp')),
                    'name'    => 'assignees_column',
                    'values'  => $columns,
                    'default' => $defaultAssigneesColumn,
                    'morecss' => 'minwidth300 maxwidth300',
                ],
                [
                    'type'    => 'select',
                    'label'   => $form->textwithpicto($langs->trans('NoteColumn'), $langs->trans('NoteColumnHelp')),
                    'name'    => 'note_column',
                    'values'  => $columns,
                    'default' => $defaultNoteColumn,
                    'morecss' => 'minwidth300 maxwidth300',
                ],
                [
                    'type'    => 'select',
                    'label'   => $form->textwithpicto($langs->trans('ReminderLabelColumn'), $langs->trans('ReminderLabelColumnHelp')),
                    'name'    => 'reminder_label_column',
                    'values'  => $columns,
                    'default' => $defaultReminderLabelColumn,
                    'morecss' => 'minwidth300 maxwidth300',
                ],
                [
                    'type'    => 'select',
                    'label'   => $form->textwithpicto($langs->trans('ReminderDateColumn'), $langs->trans('ReminderDateColumnHelp')),
                    'name'    => 'reminder_date_column',
                    'values'  => $columns,
                    'default' => $defaultReminderDateColumn,
                    'morecss' => 'minwidth300 maxwidth300',
                ],
            ];
            $formconfirm = $form->formconfirm(
                $_SERVER['PHP_SELF'],
                $langs->trans('ConfirmImportProjectsTitle'),
                $langs->trans('ConfirmImportProjectsQuestion'),
                'confirm_import_projects',
                $formquestion,
                '',
                1,
                460
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
    $existingCategoryIds = GETPOST('existing_category_ids', 'array');
    $categoryName = trim(GETPOST('category_name', 'alphanohtml'));
    $assigneesColumn = GETPOSTINT('assignees_column');
    $reminderLabelColumn = GETPOSTINT('reminder_label_column');
    $reminderDateColumn = GETPOSTINT('reminder_date_column');
    $noteColumn = GETPOSTINT('note_column');

    // Clean existing IDs
    $existingCategoryIds = array_filter(array_map('intval', $existingCategoryIds));

    if (empty($importFile) || (empty($existingCategoryIds) && empty($categoryName))) {
        setEventMessages($langs->trans('ImportParametersMissing'), null, 'errors');
        $action = 'view';
    } else {
        $fullPath = $uploadDir . '/' . $importFile;
        if (!is_readable($fullPath)) {
            setEventMessages($langs->trans('ImportFileNotFound'), null, 'errors');
            $action = 'view';
        } else {
            $categoryIds = [];
            $categoryNameForArchive = '';

            // Fetch all selected existing categories
            foreach ($existingCategoryIds as $eCatId) {
                $catTmp = new Categorie($db);
                if ($catTmp->fetch($eCatId) > 0) {
                    $categoryIds[] = $catTmp->id;
                    if (empty($categoryNameForArchive)) {
                        $categoryNameForArchive = $catTmp->label;
                    }
                }
            }

            // Handle new category name
            if (!empty($categoryName)) {
                $catNew = new Categorie($db);
                $resCat = $catNew->fetch(0, $categoryName, 'project');
                if ($resCat > 0 && !GETPOSTINT('confirm_use_existing')) {
                    $formquestion = [
                        ['type' => 'hidden', 'name' => 'import_file', 'value' => $importFile],
                        ['type' => 'hidden', 'name' => 'category_name', 'value' => $categoryName],
                        ['type' => 'hidden', 'name' => 'confirm_use_existing', 'value' => 1],
                        ['type' => 'hidden', 'name' => 'assignees_column', 'value' => $assigneesColumn],
                        ['type' => 'hidden', 'name' => 'reminder_label_column', 'value' => $reminderLabelColumn],
                        ['type' => 'hidden', 'name' => 'reminder_date_column', 'value' => $reminderDateColumn],
                        ['type' => 'hidden', 'name' => 'note_column', 'value' => $noteColumn],
                    ];
                    // Re-pass existing category IDs as hidden fields
                    foreach ($existingCategoryIds as $hidCatId) {
                        $formquestion[] = ['type' => 'hidden', 'name' => 'existing_category_ids[]', 'value' => $hidCatId];
                    }
                    $formconfirm = $form->formconfirm(
                        $_SERVER['PHP_SELF'],
                        $langs->trans('TagAlreadyExistsTitle'),
                        $langs->trans('TagAlreadyExistsQuestion', $categoryName),
                        'confirm_import_projects',
                        $formquestion,
                        '',
                        1,
                        300
                    );
                    $action = 'view';
                    goto skipImport;
                }
                if ($resCat <= 0) {
                    $catNew->type = Categorie::TYPE_PROJECT;
                    $catNew->label = $categoryName;
                    $resCat = $catNew->create($user);
                }
                if ($catNew->id > 0) {
                    $categoryIds[] = $catNew->id;
                    if (empty($categoryNameForArchive)) {
                        $categoryNameForArchive = $catNew->label;
                    }
                }
            }

            if (empty($categoryIds)) {
                setEventMessages($langs->trans('CategoryCreationError'), null, 'errors');
                dol_delete_file($fullPath);
            } else {
                list($modProject) = saturne_require_objects_mod(['project' => getDolGlobalString('PROJECT_ADDON')]);
                $categoryId = $categoryIds[0];
                $handle = fopen($fullPath, 'r');
                if ($handle) {
                    list($delimiter, $headers) = reedcrm_read_import_csv_header($handle);
                    $map = [];
                    foreach ($headers as $idx => $headerLabel) {
                        $map[reedcrm_normalize_import_key($headerLabel)] = $idx;
                    }

                    // Column number posted by the confirmation form, 0 or less when no column was chosen
                    $assigneesIdx = ($assigneesColumn > 0 && isset($headers[$assigneesColumn - 1])) ? $assigneesColumn - 1 : null;
                    $usersIndex   = ($assigneesIdx !== null) ? reedcrm_get_import_users_index() : [];

                    // Either reminder column is enough: a missing text gets a default label, a missing date leaves the reminder without deadline
                    $reminderLabelIdx = ($reminderLabelColumn > 0 && isset($headers[$reminderLabelColumn - 1])) ? $reminderLabelColumn - 1 : null;
                    $reminderDateIdx  = ($reminderDateColumn > 0 && isset($headers[$reminderDateColumn - 1])) ? $reminderDateColumn - 1 : null;
                    $hasReminders     = ($reminderLabelIdx !== null || $reminderDateIdx !== null);
                    $noteIdx          = ($noteColumn > 0 && isset($headers[$noteColumn - 1])) ? $noteColumn - 1 : null;
                    $now              = dol_now();

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
                        $unresolvedAssignees = [];
                        $assignmentErrors = 0;
                        $remindersCreated = 0;
                        $remindersWithoutText = 0;
                        $remindersWithoutDueDate = 0;
                        $remindersOverdue = 0;
                        $reminderErrors = 0;
                        $notesInvalid = 0;

                        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                            if (count($row) == 1 && trim($row[0]) === '') {
                                continue;
                            }
                            $row = reedcrm_import_csv_row_to_utf8($row);
                            $total++;
                            $noteValue = ($noteIdx !== null) ? trim((string) ($row[$noteIdx] ?? '')) : '';
                            $oppPercent = ($noteValue !== '') ? reedcrm_parse_import_note($noteValue) : null;
                            // The "Note" column is no description when it holds the probability of the opportunity, a comment stays one
                            $description = ($noteIdx === $map['note'] && $oppPercent !== null) ? '' : dol_htmlentitiesbr(trim($row[$map['note']] ?? ''));
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

                            if ($oppPercent !== null) {
                                $proj->usage_opportunity = 1;
                                $proj->opp_percent       = (float) $oppPercent;
                                $proj->opp_status        = reedcrm_get_opp_status_for_percent($oppPercent);
                            } elseif ($noteValue !== '') {
                                $notesInvalid++;
                            }

                            $resCreate = $proj->create($user);

                            if ($resCreate > 0) {
                                $assigneeIds = [];
                                if ($assigneesIdx !== null) {
                                    list($resolvedIds, $unresolved) = reedcrm_resolve_import_users((string) ($row[$assigneesIdx] ?? ''), $usersIndex);
                                    foreach ($resolvedIds as $resolvedId) {
                                        if ($proj->add_contact($resolvedId, 'SALESREPINTERNAL', 'internal') < 0) {
                                            $assignmentErrors++;
                                        } else {
                                            $assigneeIds[] = $resolvedId;
                                        }
                                    }
                                    foreach ($unresolved as $unresolvedValue) {
                                        $unresolvedAssignees[$unresolvedValue] = $unresolvedValue;
                                    }
                                }

                                if ($hasReminders) {
                                    $reminderLabel     = ($reminderLabelIdx !== null) ? dol_string_nohtmltag(trim((string) ($row[$reminderLabelIdx] ?? ''))) : '';
                                    $reminderDateValue = ($reminderDateIdx !== null) ? trim((string) ($row[$reminderDateIdx] ?? '')) : '';
                                    $reminderLabel     = reedcrm_is_import_empty_value($reminderLabel) ? '' : $reminderLabel;
                                    $reminderDateValue = reedcrm_is_import_empty_value($reminderDateValue) ? '' : $reminderDateValue;
                                    if ($reminderLabel !== '' || $reminderDateValue !== '') {
                                        $dueDate         = reedcrm_parse_import_date($reminderDateValue);
                                        // Only a deadline still to come is notified: a past one would pop at once, for every line of the file
                                        $reminderOptions = ['due_date' => $dueDate, 'notify' => $dueDate > $now];
                                        if ($reminderLabel === '') {
                                            $remindersWithoutText++;
                                            $contactName   = dol_string_nohtmltag(trim(($row[$map['prenom']] ?? '') . ' ' . ($row[$map['nom']] ?? '')));
                                            $reminderLabel = $langs->transnoentities('QuickCreationReminderDefaultLabel', $contactName !== '' ? $contactName : $proj->ref);
                                        }
                                        if ($dueDate <= 0) {
                                            $remindersWithoutDueDate++;
                                            // "3-6 mois" is no date but still tells when to call back
                                            if ($reminderDateValue !== '') {
                                                $reminderOptions['note'] = $langs->transnoentities('ImportReminderDueDateAsWritten', dol_string_nohtmltag($reminderDateValue));
                                            }
                                        } elseif ($dueDate < $now) {
                                            $remindersOverdue++;
                                        }

                                        // The reminder starts when it is imported and ends on the date of the file, its deadline. An
                                        // overdue one starts on its deadline, Dolibarr would otherwise move the deadline to the start.
                                        $reminderStart   = ($dueDate > 0) ? min($now, $dueDate) : $now;
                                        // The people assigned to the opportunity are the ones to remind, the importer when nobody is
                                        $reminderUserIds = !empty($assigneeIds) ? $assigneeIds : [(int) $user->id];
                                        $reminderOwnerId = (int) array_shift($reminderUserIds);
                                        $reminderId      = reedcrm_create_call_reminder($db, $user, dol_substr($reminderLabel, 0, 255), $reminderStart, $reminderOwnerId, (int) $proj->id, max(0, (int) $proj->socid), 0, 'AC_OTH', $reminderUserIds, $reminderOptions);
                                        if ($reminderId > 0) {
                                            $remindersCreated++;
                                        } else {
                                            $reminderErrors++;
                                        }
                                    }
                                }

                                $resSetCat = $proj->setCategories($categoryIds, Categorie::TYPE_PROJECT);
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

                        reedcrm_archive_import_file($fullPath, $categoryNameForArchive, $importHistoryDir, $categoryId);

                        if ($created > 0) {
                            setEventMessages($langs->trans('ProjectsImported', $created, $total), null, 'mesgs');
                        }
                        if ($errors > 0) {
                            setEventMessages($langs->trans('ProjectsImportErrors', $errors), null, 'warnings');
                        }
                        if (!empty($unresolvedAssignees)) {
                            setEventMessages($langs->transnoentities('ImportAssigneesNotFound', dol_escape_htmltag(implode(', ', $unresolvedAssignees))), null, 'warnings');
                        }
                        if ($assignmentErrors > 0) {
                            setEventMessages($langs->trans('ImportAssignmentErrors', $assignmentErrors), null, 'warnings');
                        }
                        if ($notesInvalid > 0) {
                            setEventMessages($langs->trans('ImportNotesInvalid', $notesInvalid), null, 'warnings');
                        }
                        if ($remindersCreated > 0) {
                            setEventMessages($langs->trans('ImportRemindersCreated', $remindersCreated), null, 'mesgs');
                        }
                        if ($remindersWithoutText > 0) {
                            setEventMessages($langs->trans('ImportRemindersWithoutText', $remindersWithoutText), null, 'warnings');
                        }
                        if ($remindersWithoutDueDate > 0) {
                            setEventMessages($langs->trans('ImportRemindersWithoutDueDate', $remindersWithoutDueDate), null, 'warnings');
                        }
                        if ($remindersOverdue > 0) {
                            setEventMessages($langs->trans('ImportRemindersOverdue', $remindersOverdue), null, 'warnings');
                        }
                        if ($reminderErrors > 0) {
                            setEventMessages($langs->trans('ImportReminderErrors', $reminderErrors), null, 'warnings');
                        }
                    }
                } else {
                    setEventMessages($langs->trans('ImportFileNotReadable'), null, 'errors');
                    dol_delete_file($fullPath);
                }
            }
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
skipImport:


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
print $langs->trans('ImportProjectsDescription') . '<br><span class="opacitymedium">' . $langs->trans('CSVExpectedColumns') . '<br>' . $langs->trans('CSVAssigneesColumn') . '<br>' . $langs->trans('CSVNoteColumn') . '<br>' . $langs->trans('CSVReminderColumns') . '</span>';
print '</td>';
print '<td class="center">';
print '<input type="file" name="import_file" accept=".csv" required class="flat">';
print ' <input type="submit" class="button" name="import_projects" value="' . $langs->trans('UploadImport') . '">';
print '</td></tr>';

print '</table>';
print '</form>';

// Import history inline
print '<br>';
print load_fiche_titre($langs->trans('ImportHistory'), '', '');

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
        $parts = preg_split('#[\\\\/]+#', $relative, 2);
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

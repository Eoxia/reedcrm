<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    class/actions_easycrm.class.php
 * \ingroup easycrm
 * \brief   EasyCRM hook overload.
 */

/**
 * Class ActionsEasycrm
 */
class ActionsEasycrm
{
    /**
     * @var DoliDB Database handler.
     */
    public DoliDB $db;

    /**
     * @var string Error code (or message)
     */
    public string $error = '';

    /**
     * @var array Errors
     */
    public array $errors = [];

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public array $results = [];

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     *  Overloading the addMoreBoxStatsCustomer function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param  string       $action     Current action (if set). Generally create or edit or null
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function addMoreBoxStatsCustomer(array $parameters, CommonObject $object, string $action): int
    {
        global $conf, $langs, $user;

        // Do something only for the current context
        if (strpos($parameters['context'], 'thirdpartycomm') !== false) {
            if (isModEnabled('project') && $user->hasRight('projet', 'lire') && isModEnabled('saturne')) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                require_once __DIR__ . '/../../saturne/lib/object.lib.php';

                $projects    = saturne_fetch_all_object_type('Project', '', '', 0, 0, ['customsql' => 't.fk_soc = ' . $object->id]);
                $projectData = [];
                if (is_array($projects) && !empty($projects)) {
                    foreach ($projects as $project) {
                        $projectData['total_opp_amount'] += $project->opp_amount;
                        $projectData['total_opp_weighted_amount'] += $project->opp_amount * $project->opp_percent / 100;
                    }
                }

                // Project box opportunity amount
                $boxTitle = $langs->transnoentities('OpportunityAmount');
                $link = DOL_URL_ROOT . '/projet/list.php?socid=' . $object->id;
                $boxStat = '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
                $boxStat .= '<div class="boxstats" title="' . dol_escape_htmltag($boxTitle) . '">';
                $boxStat .= '<span class="boxstatstext">' . img_object('', 'project') . ' <span>' . $boxTitle . '</span></span><br>';
                $boxStat .= '<span class="boxstatsindicator">' . price($projectData['total_opp_amount'], 1, $langs, 1, 0, -1, $conf->currency) . '</span>';
                $boxStat .= '</div>';
                $boxStat .= '</a>';

                // Project box opportunity weighted amount
                $boxTitle = $langs->transnoentities('OpportunityWeightedAmount');
                $boxStat .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
                $boxStat .= '<div class="boxstats" title="' . dol_escape_htmltag($boxTitle) . '">';
                $boxStat .= '<span class="boxstatstext">' . img_object('', 'project') . ' <span>' . $boxTitle . '</span></span><br>';
                $boxStat .= '<span class="boxstatsindicator">' . price($projectData['total_opp_weighted_amount'], 1, $langs, 1, 0, -1, $conf->currency) . '</span>';
                $boxStat .= '</div>';
                $boxStat .= '</a>';

                $this->resprints = $boxStat;
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     *  Overloading the addMoreRecentObjects function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param  string       $action     Current action (if set). Generally create or edit or null
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function addMoreRecentObjects(array $parameters, CommonObject $object, string $action): int
    {
        global $conf, $db, $langs, $user;

        // Do something only for the current context
        if (strpos($parameters['context'], 'thirdpartycomm') !== false) {
            if (isModEnabled('project') && $user->hasRight('projet', 'lire') && isModEnabled('saturne')) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                require_once __DIR__ . '/../../saturne/lib/object.lib.php';

                $projects = saturne_fetch_all_object_type('Project', 'DESC', 'datec', 0, 0, ['customsql' => 't.fk_soc = ' . $object->id]);
                if (is_array($projects) && !empty($projects)) {
                    $countProjects = 0;
                    $nbProjects    = count($projects);
                    $maxList       = $conf->global->MAIN_SIZE_SHORTLIST_LIMIT;

                    $out = '<div class="div-table-responsive-no-min">';
                    $out .= '<table class="noborder centpercent lastrecordtable">';

                    $out .= '<tr class="liste_titre">';
                    $out .= '<td colspan="4"><table class="nobordernopadding centpercent"><tr>';
                    $out .= '<td>' . $langs->trans('LastProjects', ($nbProjects <= $maxList ? '' : $maxList)) . '</td>';
                    $out .= '<td class="right"><a class="notasortlink" href="' . DOL_URL_ROOT . '/projet/list.php?socid=' . $object->id . '">' . $langs->trans('AllProjects') . '<span class="badge marginleftonlyshort">' . $nbProjects .'</span></a></td>';
                    $out .= '<td class="right" style="width: 20px;"><a href="' . DOL_URL_ROOT . '/projet/stats/index.php?socid=' . $object->id . '">' . img_picto($langs->trans('Statistics'), 'stats') . '</a></td>';
                    $out .= '</tr></table></td>';
                    $out .= '</tr>';

                    foreach ($projects as $project) {
                        if ($countProjects == $maxList) {
                            break;
                        } else {
                            $countProjects++;
                        }
                        $out .= '<tr class="oddeven">';
                        $out .= '<td class="nowraponall">';
                        $out .= $project->getNomUrl(1);
                        // Preview
                        $filedir = $conf->projet->multidir_output[$project->entity] . '/' . dol_sanitizeFileName($project->ref);
                        $fileList = null;
                        if (!empty($filedir)) {
                            $fileList = dol_dir_list($filedir, 'files', 0, '', '(\.meta|_preview.*.*\.png)$', 'date', SORT_DESC);
                        }
                        if (is_array($fileList) && !empty($fileList)) {
                            // Defined relative dir to DOL_DATA_ROOT
                            $relativedir = '';
                            if ($filedir) {
                                $relativedir = preg_replace('/^' . preg_quote(DOL_DATA_ROOT, '/') . '/', '', $filedir);
                                $relativedir = preg_replace('/^\//', '', $relativedir);
                            }
                            // Get list of files stored into database for same relative directory
                            if ($relativedir) {
                                completeFileArrayWithDatabaseInfo($fileList, $relativedir);
                                if (!empty($sortfield) && !empty($sortorder)) {	// If $sortfield is for example 'position_name', we will sort on the property 'position_name' (that is concat of position+name)
                                    $fileList = dol_sort_array($fileList, $sortfield, $sortorder);
                                }
                            }
                            require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';

                            $formfile = new FormFile($db);

                            $relativepath = dol_sanitizeFileName($project->ref) . '/' . dol_sanitizeFileName($project->ref) . '.pdf';
                            $out .= $formfile->showPreview($fileList, $project->element, $relativepath);
                        }
                        $out .= '</td><td class="right" style="width: 80px;">' . dol_print_date($project->datec, 'day') . '</td>';
                        $out .= '<td class="right" style="min-width: 60px;">' . price($project->budget_amount) . '</td>';
                        $out .= '<td class="right" style="min-width: 60px;" class="nowrap">' . $project->LibStatut($project->fk_statut, 5) . '</td></tr>';
                    }

                    $out .= '</table>';
                    $out .= '</div>';

                    $this->resprints = $out;
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     *  Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param  string       $action     Current action (if set). Generally create or edit or null
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function addMoreActionsButtons(array $parameters, CommonObject $object, string $action): int
    {
        global $langs, $user;

        // Do something only for the current context
        if (preg_match('/thirdpartycomm|projectcard/', $parameters['context'])) {
            if (empty(GETPOST('action')) || GETPOST('action') == 'update') {
                if (strpos($parameters['context'], 'thirdpartycomm') !== false) {
                    $socid = $object->id;
                    $moreparam = '';
                } else {
                    $socid = $object->socid;
                    $moreparam = '&project_id=' . $object->id;
                }
                $url = '?socid=' . $socid . '&fromtype=' . $object->element . $moreparam . '&action=create&token=' . newToken();
                print dolGetButtonAction('', $langs->trans('QuickEventCreation'), 'default', dol_buildpath('/easycrm/view/quickevent.php', 1) . $url, '', $user->rights->agenda->myactions->create);
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     Current object
     * @param  string       $action     Current action
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function doActions(array $parameters, $object, string $action): int
    {
        if (preg_match('/invoicecard|invoicereccard|thirdpartycomm|thirdpartycard/', $parameters['context'])) {
            if ($action == 'set_notation_object_contact') {
                require_once __DIR__ . '/../lib/easycrm_function.lib.php';

                set_notation_object_contact($object);

                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
                exit;
            }
        } else if (strpos($parameters['context'], 'projectlist') !== false) {
            if ($action == 'reload_opp_percent') {
                require_once __DIR__ . '/../../saturne/lib/object.lib.php';

                $projects = saturne_fetch_all_object_type('Project', '', '', 0, 0, ['customsql' => 't.fk_statut IN (' . Project::STATUS_DRAFT . ',' . Project::STATUS_VALIDATED . ') AND t.fk_opp_status IS NOT NULL AND t.opp_percent IS NULL']);

                if (is_array($projects) && !empty($projects)) {
                    foreach ($projects as $project) {
                        if ($project->fk_opp_status > 0) {
                            $oppPercent = dol_getIdFromCode($this->db, $project->fk_opp_status, 'c_lead_status', 'rowid', 'percent');
                        } else if (isModEnabled('agenda')) {
                            require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

                            $actionComm = new ActionComm($this->db);

                            $actionComms = $actionComm->getActions($project->socid, $project->id, 'project');
                            $oppPercent  = (100 - (count($actionComms) * 20)) < 0 ? 0 : count($actionComms) * 20;
                        } else {
                            continue;
                        }
                        $project->setValueFrom('opp_percent', $oppPercent);
                    }
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the printCommonFooter function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadatas (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function printCommonFooter(array $parameters): int
    {
        global $conf, $db, $langs, $object, $user;

        // Do something only for the current context
        if (preg_match('/thirdpartycomm|projectcard/', $parameters['context'])) {
            if (isModEnabled('agenda')) {
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

                $pictopath = dol_buildpath('/easycrm/img/easycrm_color.png', 1);
                $picto     = img_picto('', $pictopath, '', 1, 0, 0, '', 'pictoModule');

                $actionComm = new ActionComm($db);

                $filter      = ' AND a.id IN (SELECT c.fk_actioncomm FROM '  . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $conf->global->EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG . ')';
                $actionComms = $actionComm->getActions(GETPOST('socid'), ((strpos($parameters['context'], 'thirdpartycomm') !== false) ? '' : GETPOST('id')), ((strpos($parameters['context'], 'thirdpartycomm') !== false) ? '' : 'project'), $filter, 'a.datec');
                if (is_array($actionComms) && !empty($actionComms)) {
                    $nbActionComms  = count($actionComms);
                    $lastActionComm = array_shift($actionComms);
                } else {
                    $nbActionComms = 0;
                }

                if ($nbActionComms == 0) {
                    $badgeClass = 1;
                } else if ($nbActionComms == 1 || $nbActionComms == 2) {
                    $badgeClass = 4;
                } else {
                    $badgeClass = 8;
                }

                $url = '?socid=' . $object->socid . '&fromtype=project' . '&project_id=' . $object->id . '&action=create&token=' . newToken();
                $out = '<tr><td class="titlefield">' . $picto . $langs->trans('CommercialsRelaunching') . '</td>';

                $picto = img_picto($langs->trans('CommercialsRelaunching'), 'fontawesome_fa-headset_fas');

                $out .= '<td>' . dolGetBadge($picto . ' : ' . $nbActionComms, '', 'status' . $badgeClass);
                if ($nbActionComms > 0) {
                    $out .= ' - ' . '<span>' . $langs->trans('LastCommercialReminderDate') . ' : ' . dol_print_date($lastActionComm->datec, 'dayhourtext', 'tzuser') . '</span>';
                }
                if ($user->hasRight('agenda', 'myactions', 'create')) {
                    $out .= dolButtonToOpenUrlInDialogPopup('quickEventCreation' . $object->id, $langs->transnoentities('QuickEventCreation'), '<span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('QuickEventCreation') . '"></span>', '/custom/easycrm/view/quickevent.php' . $url);
                }
                if (!empty($lastActionComm)) {
                    $out .= '<br>' . dolButtonToOpenUrlInDialogPopup('lastActionComm' . $object->id, $langs->transnoentities('LastEvent') . ' : ' . $lastActionComm->label, img_picto('', $lastActionComm->picto) . ' ' . $lastActionComm->label, '/comm/action/card.php?id=' . $lastActionComm->id);
                }
                $out .= '</td></tr>';

                ?>
                <script>
                    jQuery('.tableforfield').last().append(<?php echo json_encode($out); ?>)
                </script>
                <?php
            }
        }

        // Do something only for the current context
        if (strpos($parameters['context'], 'projectcard') !== false) {
            if (empty(GETPOST('action')) || GETPOST('action') == 'update') {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

                $project = new Project($db);
                $task    = new Task($db);

                $project->fetch(GETPOST('id'));
                $project->fetch_optionals();

                if (!empty($project->array_options['options_commtask'])) {
                    $task->fetch($project->array_options['options_commtask']);
                    $out2 = $task->getNomUrl(1, '', 'task', 1);
                } ?>

                <script>
                    jQuery('.project_extras_commtask').html(<?php echo json_encode($out2); ?>)
                </script>
                <?php
            }
        }

        if (preg_match('/invoicelist|invoicereclist|thirdpartylist/', $parameters['context'])) {
            $cssPath = dol_buildpath('/saturne/css/saturne.min.css', 1);
            print '<link href="' . $cssPath . '" rel="stylesheet">';

            $jQueryElement = 'notation_' . $object->element . '_contact';
            $pictoPath     = dol_buildpath('/easycrm/img/easycrm_color.png', 1);
            $picto         = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule'); ?>

            <script>
                var objectElement = <?php echo "'" . $jQueryElement . "'"; ?>;
                var outJS         = <?php echo json_encode($picto); ?>;
                var cell          = $('.liste > tbody > tr.liste_titre').find('th[data-titlekey="' + objectElement + '"]');
                cell.prepend(outJS);
            </script>
            <?php
        }

        if (preg_match('/invoicecard|invoicereccard|thirdpartycomm|thirdpartycard/', $parameters['context'])) {
            $cssPath = dol_buildpath('/saturne/css/saturne.min.css', 1);
            print '<link href="' . $cssPath . '" rel="stylesheet">';

            $jQueryElement = '.' . $object->element . '_extras_notation_' . $object->element . '_contact';
            $pictoPath     = dol_buildpath('/easycrm/img/easycrm_color.png', 1);
            $picto         = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');

            $out  = $picto;
            $out .= '<div class="wpeo-button button-strong ' . (($object->array_options['options_notation_' . $object->element . '_contact'] >= 80) ? 'button-green' : 'button-red') . '" style="padding: 0; line-height: 1;">';
            $out .= '<span>' . $object->array_options['options_notation_' . $object->element . '_contact'] . '</span>';
            $out .= '</div>';
            $out .= '<a class="reposition editfielda" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=set_notation_object_contact&token=' . newToken() . '">';
            $out .= img_picto($langs->trans('SetNotationObjectContact'), 'fontawesome_fa-redo_fas_#444', 'class="paddingleft"') . '</a>'; ?>

            <script>
                var objectElement = <?php echo "'" . $jQueryElement . "'"; ?>;
                jQuery(objectElement).html(<?php echo json_encode($out); ?>);
            </script>
            <?php
        }

        if (strpos($parameters['context'], 'contactcard') !== false) {
            if (in_array(GETPOST('action'), ['create', 'edit'])) {
                $out = img_picto('', 'fontawesome_fa-id-card-alt_fas', 'class="pictofixedwidth"'); ?>
                <script>
                    jQuery('#roles').before(<?php echo json_encode($out); ?>);
                </script>
                <?php
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the addHtmlHeader function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadata (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function addHtmlHeader(array $parameters): int
    {
        if (strpos($_SERVER['PHP_SELF'], 'easycrm') !== false) {
            ?>
            <script>
                $('link[rel="manifest"]').remove();
            </script>
            <?php

            $this->resprints = '<link rel="manifest" href="' . DOL_URL_ROOT . '/custom/easycrm/manifest.json.php' . '" />';
        }

        return 0; // or return 1 to replace standard code-->
    }

    /**
     * Overloading the printFieldListTitle function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadatas (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function printFieldListTitle(array $parameters): int
    {
        global $langs, $user;

        if (strpos($parameters['context'], 'projectlist') !== false) {
            if (isModEnabled('project') && $user->hasRight('projet', 'lire') && isModEnabled('saturne')) {
                $out = '';
                if ($user->hasRight('projet', 'creer')) {
                    $out .= '<a title="' . $langs->transnoentities('ReloadOppPercent') . '" class="reposition" href="' . $_SERVER['PHP_SELF'] . '?action=reload_opp_percent">';
                    $out .= dolGetBadge(img_picto('', 'refresh'));
                    $out .= '</a>';
                }
                ?>
                 <script>
                        var outJS = <?php echo json_encode($out); ?>;

                        var probCell = $('.liste > tbody > tr.liste_titre').find('th.right').has('a[href*="opp_percent"]');

                        probCell.append(outJS);
                    </script>
                <?php
            }
        }


        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the printFieldListValue function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadatas (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function printFieldListValue(array $parameters): int
    {
        global $conf, $db, $langs, $object, $user;

        // Do something only for the current context
        if (strpos($parameters['context'], 'projectlist') !== false) {
            if (isModEnabled('project') && $user->hasRight('projet', 'lire') && isModEnabled('saturne')) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

                $picto  = img_picto($langs->trans('CommercialsRelaunching'), 'fontawesome_fa-headset_fas');
                $filter = ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $conf->global->EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG . ')';
                if (is_object($parameters['obj']) && !empty($parameters['obj'])) {
                    if (!empty($parameters['obj']->id)) {
                        $out = '<td class="tdoverflowmax200">';
                        if (isModEnabled('agenda')) {
                            require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                            $actionComm = new ActionComm($db);

                            $actionComms = $actionComm->getActions($parameters['obj']->socid, $parameters['obj']->id, 'project', $filter, 'a.datec');
                            if (is_array($actionComms) && !empty($actionComms)) {
                                $nbActionComms  = count($actionComms);
                                $lastActionComm = array_shift($actionComms);
                            } else {
                                $nbActionComms = 0;
                            }

                            // @todo is a backward, should be removed one day when corrupted tools repair is added in saturne
                            if ($parameters['obj']->options_commrelaunch != $nbActionComms) {
                                $project = new Project($db);
                                $project->fetch($parameters['obj']->id);
                                $project->array_options['options_commrelaunch'] = $nbActionComms;
                                $project->updateExtrafield('commrelaunch');
                            }

                            if ($nbActionComms == 0) {
                                $badgeClass = 1;
                            } else if ($nbActionComms == 1 || $nbActionComms == 2) {
                                $badgeClass = 4;
                            } else {
                                $badgeClass = 8;
                            }

                            $url = '?socid=' . $parameters['obj']->socid . '&fromtype=project' . '&project_id=' . $parameters['obj']->id . '&action=create&token=' . newToken();

                            $out .= dolGetBadge($picto . ' : ' . $nbActionComms, '', 'status' . $badgeClass);
                            // -- Old design --
                            //$out .= '<span class="badge badge-info" title="' . $langs->trans('CommercialsRelaunching') . '">' . $nbActionComms . '</span> &nbsp';
                            if ($nbActionComms > 0) {
                                $out .= '<span> ' . dol_print_date($lastActionComm->datec, '%d/%m/%y %H:%M', 'tzuser') . '</span>';
                            }

                            // Extrafield commRelaunch
                            if ($user->hasRight('agenda', 'myactions', 'create')) {
                                $out .= dolButtonToOpenUrlInDialogPopup('quickEventCreation' . $parameters['obj']->id, $langs->transnoentities('QuickEventCreation'), '<span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('QuickEventCreation') . '"></span>', '/custom/easycrm/view/quickevent.php' . $url);
                                // @todo find somewhere to add a user->conf to choose between popup dialog or open in current tab
                                //$out .= '<a href="' . dol_buildpath('/easycrm/view/quickevent.php', 1) . $url . '" target="_blank"><span class="fa fa-plus-circle valignmiddle paddingleft" title="' . $langs->trans('QuickEventCreation') . '"></span></a>';
                            }

                            if (!empty($lastActionComm)) {
                                $out .= '<br>' . dolButtonToOpenUrlInDialogPopup('lastActionComm' . $parameters['obj']->id, $langs->transnoentities('LastEvent') . ' : ' . $lastActionComm->label, img_picto('', $lastActionComm->picto) . ' ' . $lastActionComm->label, '/comm/action/card.php?id=' . $lastActionComm->id);
                                //$out .= '&nbsp' . $lastActionComm->getNomUrl(1);
                            }
                        }
                        $out .= '</td>';

                        // Extrafield commTask
                        $out2 = '<td class="tdoverflowmax200">';
                        if (!empty($parameters['obj']->options_commtask)) {
                            $task = new Task($this->db);
                            $task->fetch($parameters['obj']->options_commtask);
                            $out2 .= $task->getNomUrl(1, '', 'task', 1);
                        }
                        $out2 .= '</td>';

                        // projectField opp_percent
                        $out3 = '<td class="center"><span data-project_id="'. $parameters['obj']->id . '">';
                        if (isset($parameters['obj']->opp_percent)) {
                            switch ($parameters['obj']->opp_percent) {
                                case $parameters['obj']->opp_percent < 20:
                                    $statusBadge = 8;
                                    break;
                                case $parameters['obj']->opp_percent < 60:
                                    $statusBadge = 1;
                                    break;
                                default:
                                    $statusBadge = 4;
                                    break;
                            }
                            $out3 .= dolGetBadge($parameters['obj']->opp_percent . ' %', '', 'status' . $statusBadge);
                        }
                        $out3 .= '</span></td>';
                    } ?>
                    <script>
                        var outJS  = <?php echo json_encode($out); ?>;
                        var outJS2 = <?php echo json_encode($out2); ?>;
                        var outJS3 = <?php echo json_encode($out3); ?>;

                        var commRelauchCell = $('.liste > tbody > tr.oddeven').find('td[data-key="projet.commrelaunch"]').last();
                        var commTaskCell    = $('.liste > tbody > tr.oddeven').find('td[data-key="projet.commtask"]').last();
                        var probCell        = $('.liste > tbody > tr.oddeven').find("td.right:contains('%')").last();

                        commRelauchCell.replaceWith(outJS);
                        commTaskCell.replaceWith(outJS2);
                        probCell.replaceWith(outJS3);

                    </script>
                    <?php
                }
            }
        }

        if (preg_match('/invoicelist|invoicereclist|thirdpartylist/', $parameters['context'])) {
            if (isModEnabled('facture') && $user->hasRight('facture', 'lire')) {
                $extrafieldName = 'options_notation_' . $object->element . '_contact';
                if ($object->element == 'facturerec') {
                    $specialName = 'facture_rec';
                } else {
                    $specialName = $object->element;
                }
                $jQueryElement  = $specialName . '.notation_' . $object->element . '_contact';
                $out            = '<div class="wpeo-button button-strong ' . (($parameters['obj']->$extrafieldName >= 80) ? 'button-green' : 'button-red') . '" style="padding: 0; line-height: 1;">';
                $out           .= '<span>' . $parameters['obj']->$extrafieldName . '</span>';
                $out           .= '</div>'; ?>

                <script>
                    var objectElement = <?php echo "'" . $jQueryElement . "'"; ?>;
                    var outJS         = <?php echo json_encode($out); ?>;
                    var cell          = $('.liste > tbody > tr.oddeven').find('td[data-key="' + objectElement + '"]').last();
                    cell.html(outJS);
                </script>
                <?php
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the formConfirm hook
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function formConfirm(array $parameters, $object): int
    {
        if (strpos($parameters['context'], 'propalcard') !== false) {
            if (empty($object->thirdparty->id)) {
                $object->fetch_thirdparty();
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the completeTabsHead function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadatas (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function completeTabsHead(array $parameters): int
    {
        global $langs;

        if (preg_match('/invoicereccard|invoicereccontact/', $parameters['context'])) {
            $nbContact = 0;
            // Enable caching of thirdrparty count Contacts
            require_once DOL_DOCUMENT_ROOT . '/core/lib/memory.lib.php';
            $cacheKey      = 'count_contacts_thirdparty_' . $parameters['object']->id;
            $dataRetrieved = dol_getcache($cacheKey);

            if (!is_null($dataRetrieved)) {
                $nbContact = $dataRetrieved;
            } else {
                $sql  = "SELECT COUNT(p.rowid) as nb";
                $sql .= " FROM " . MAIN_DB_PREFIX . "socpeople as p";
                $sql .= " WHERE p.fk_soc = " . $parameters['object']->socid;
                $resql = $this->db->query($sql);
                if ($resql) {
                    $obj       = $this->db->fetch_object($resql);
                    $nbContact = $obj->nb;
                }

                dol_setcache($cacheKey, $nbContact, 120); // If setting cache fails, this is not a problem, so we do not test result
            }
            $parameters['head'][1][0] = DOL_URL_ROOT . '/custom/easycrm/view/contact.php?id=' . $parameters['object']->id;
            $parameters['head'][1][1] = $langs->trans('ContactsAddresses');
            if ($nbContact > 0) {
                $parameters['head'][1][1] .= '<span class="badge marginleftonlyshort">' . $nbContact . '</span>';
            }
            $parameters['head'][1][2] = 'contact';

            $this->results = $parameters;
        }

        return 0; // or return 1 to replace standard code
    }

  /**
	 * Overloading the addMoreMassActions function
	 *
	 * @param   array $parameters Hook metadatas (context, etc...)
	 * @return  int               < 0 on error, 0 on success, 1 to replace standard code
	 */
    public function addMoreMassActions($parameters)
    {
        global $user, $langs;

        if (strpos($parameters['context'], 'projectlist') !== false && $user->hasRight('projet', 'creer')) {
            $selected = '';
            $ret      = '';

            if (GETPOST('massaction') == 'assignOppStatus') {
                $selected = ' selected="selected" ';
            }
            $ret .= '<option value="assignOppStatus"' . $selected . '>' . $langs->trans('AddAssignOppStatus') . '</option>';

            $this->resprints = $ret;
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the doPreMassActions function
     *
     * @param   array $parameters Hook metadatas (context, etc...)
     * @return  int               < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doPreMassActions($parameters)
    {
        global $user, $langs;

        $massAction = GETPOST('massaction');

        if (strpos($parameters['context'], 'projectlist') !== false && $user->hasRight('projet', 'creer') && $massAction == 'assignOppStatus') {
            require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';

            $formproject = new FormProjets($this->db);

            $out  = '<div style="padding: 10px 0 20px 0;">';
            $out .= '<fieldset>';
            $out .= '<legend>' . $langs->trans('SelectOppStatus') . '</legend>';
            $out .= '<table>';

            $out .= '<tr>';
            $out .= '<td><label>' . $langs->trans('OpportunityStatus') . '</label></td>';
            $out .= '<td>' . $formproject->selectOpportunityStatus('opp_status', '', 1, 0, 0, 0, '', 0, 1) . '</td>';
            $out .= '</tr>';

            $out .= '</table>';

            $out .= '<input type="hidden" name="oppStatus" value="projet" />';
            $out .= '<input type="hidden" name="massaction" value="assignOppStatus" />';

            $out .= '<div style="margin-top: 20px;">';
            $out .= '<button class="button" type="submit" name="massaction_confirm" value="assignOppStatus">' . $langs->trans('Apply') . '</button>';
            $out .= '<button class="button" type="submit" name="massaction" value="">' . $langs->trans('Cancel') . '</button>';
            $out .= '</div>';

            $out .= '</fieldset>';
            $out .= '</div>';

            $this->resprints = $out;
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the doMassActions function
     *
     * @param  array  $parameters Hook metadatas (context, etc...)
     * @param  Object $object
     * @return int                < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, $object)
    {
        global $user, $langs;

        $massActionConfirm = GETPOST('massaction_confirm');
        $oppStatus         = GETPOST('opp_status');

        // MASS ACTION
        if (strpos($parameters['context'], 'projectlist') !== false && $user->hasRight('projet', 'creer') && $massActionConfirm == 'assignOppStatus') {

            $toSelect = $parameters['toselect'];

            if (empty($toSelect)) {
                $this->error = $langs->trans('ErrorSelectAtLeastOne');
                return 0;
            }

            if ($toSelect > 0) {
                $count = 0;
                $res   = 0;

                foreach ($toSelect as $selectedId) {
                    $object->fetch($selectedId);
                    $object->fk_opp_status = $oppStatus;

                    $res = $object->setValueFrom('fk_opp_status', $oppStatus, 'projet');

                    if ($res <= 0) {
                        $this->errors[] = $object->errorsToString();
                        return -1;
                    } else {
                        $count++;
                    }
                }

                if ($res > 0) {
                    setEventMessages($langs->trans('OppStatusAssignedTo', $count), []);
                    header('Location:' . $_SERVER['PHP_SELF']);
                }
            }
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the saturneAdminPWAAdditionalConfig function : replacing the parent's function with the one below
     *
     * @param  array $parameters Hook metadatas (context, etc...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function saturneAdminPWAAdditionalConfig(array $parameters): int
    {
        global $langs;

        if (strpos($parameters['context'], 'pwaadmin') !== false) {
            // PWA configuration
            $out = load_fiche_titre($langs->trans('Config'), '', '');

            $out .= '<table class="noborder centpercent">';
            $out .= '<tr class="liste_titre">';
            $out .= '<td>' . $langs->trans('Parameters') . '</td>';
            $out .= '<td>' . $langs->trans('Description') . '</td>';
            $out .= '<td class="center">' . $langs->trans('Status') . '</td>';
            $out .= '</tr>';

            // PWA close project when probability zero
            $out .= '<tr class="oddeven"><td>';
            $out .= $langs->trans('PWACloseProjectOpportunityZero');
            $out .= '</td><td>';
            $out .= $langs->trans('PWACloseProjectOpportunityZeroDescription');
            $out .= '</td><td class="center">';
            $out .= ajax_constantonoff('EASYCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO');
            $out .= '</td></tr>';

            $out .= '</table>';

            $this->resprints = $out;
        }

        return 0; // or return 1 to replace standard code
    }
}

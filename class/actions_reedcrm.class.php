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
 * \file    class/actions_reedcrm.class.php
 * \ingroup reedcrm
 * \brief   ReedCRM hook overload.
 */

/**
 * Class ActionsReedcrm
 */
class ActionsReedcrm
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
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function addMoreActionsButtons(array $parameters, CommonObject $object): int
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
                
                // Print the standard quick event creation
                print dolGetButtonAction('', $langs->trans('QuickEventCreation'), 'default', dol_buildpath('/reedcrm/view/quickevent.php', 1) . $url, '', $user->rights->agenda->myactions->create);
                
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
                require_once __DIR__ . '/../lib/reedcrm_function.lib.php';

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
        global $conf, $db, $form, $langs, $object, $user;

        if (empty($conf->global->REEDCRM_CALL_NOTIFICATIONS_DISABLED) && !empty($user->id)) {
            // Inject call notifications config
            $checkUrl = dol_buildpath('/custom/reedcrm/ajax/check_call_events.php', 1);
            $frequency = getDolGlobalInt('REEDCRM_CALL_CHECK_FREQUENCY');
            $autoOpen = getDolGlobalInt('REEDCRM_AUTO_OPEN_CONTACT', 0);
            $openNewTab = getDolGlobalInt('REEDCRM_OPEN_IN_NEW_TAB', 1);

            $langs->load('reedcrm@reedcrm');
            ?>
            <div id="reedcrm-call-config" style="display:none;"
                 data-check-url="<?= dol_escape_htmltag($checkUrl) ?>"
                 data-frequency="<?= (int)$frequency ?>"
                 data-auto-open="<?= (int)$autoOpen ?>"
                 data-open-new-tab="<?= (int)$openNewTab ?>"
                 data-trans-incoming-call="<?= dol_escape_htmltag($langs->trans('IncomingCall')) ?>"
                 data-trans-from="<?= dol_escape_htmltag($langs->trans('From')) ?>"
                 data-trans-phone="<?= dol_escape_htmltag($langs->trans('Phone')) ?>"
                 data-trans-email="<?= dol_escape_htmltag($langs->trans('Email')) ?>"
                 data-trans-view-contact="<?= dol_escape_htmltag($langs->trans('ViewContact')) ?>">
            </div>
            <?php
            // Load call notifications module (dev mode - load directly until gulp build)
            $callNotifJs = dol_buildpath('/custom/reedcrm/js/modules/call_notifications.js', 1);
            if (!empty($callNotifJs)) { ?>
                <script type="text/javascript" src="<?= dol_escape_htmltag($callNotifJs) ?>"></script>
            <?php }
        }

        // Do something only for the current context
        if (preg_match('/thirdpartycomm|projectcard/', $parameters['context'])) {
            $pictoPath = dol_buildpath('/reedcrm/img/reedcrm_color.png', 1);
            $pictoMod  = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');

            if (isModEnabled('agenda')) {
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

                $actionComm          = new ActionComm($db);
                $socid               = (GETPOSTISSET('socid') ? GETPOST('socid') : $object->socid);
                $isThirdpartyContext = (strpos($parameters['context'], 'thirdpartycomm') !== false);
                $projectId           = $isThirdpartyContext ? 0 : (int) $object->id;

                $filter      = ' AND a.id IN (SELECT c.fk_actioncomm FROM '  . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG') . ')';
                $actionComms = $actionComm->getActions($socid, ($isThirdpartyContext ? '' : GETPOST('id')), ($isThirdpartyContext ? '' : 'project'), $filter, 'a.datec');
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

                $url = '?socid=' . $socid . (strpos($_SERVER['PHP_SELF'], 'projet') ? '&fromtype=project' . '&project_id=' . $object->id : '') . '&action=create&token=' . newToken();
                $out = '<tr><td class="titlefield">' . $pictoMod . $langs->trans('CommercialsRelaunching') . '</td>';

                $picto     = img_picto($langs->trans('CommercialsRelaunching'), 'fontawesome_fa-headset_fas');
                $socidAttr = $isThirdpartyContext ? ' data-socid="' . (int) $socid . '"' : '';

                // Badge wrapped in hover-tooltip trigger (same popup mechanic as list)
                $out .= '<td><div class="reedcrm-relaunch-buttons reedcrm-card-relaunch-wrapper"' . $socidAttr . '>';
                $out .= '<div class="reedcrm-relaunch-button reedcrm-card-badge-trigger" data-relaunch-type="all" data-limit="3">';
                $out .= '<span class="reedcrm-card-badge-ref reedcrm-modal-open" data-project-id="' . $projectId . '"></span>';
                $out .= dolGetBadge($picto . ' : ' . $nbActionComms, '', 'status' . $badgeClass);
                $out .= '</div></div>';

                if ($nbActionComms > 0) {
                    $out .= ' - ' . '<span>' . $langs->trans('LastCommercialReminderDate') . ' : ' . dol_print_date($lastActionComm->datec, 'dayhourtext', 'tzuser') . '</span>';
                }
                if ($user->hasRight('agenda', 'myactions', 'create')) {
                    $modalId = 'eventproCardModal';
                    if ($isThirdpartyContext) {
                        $cardProUrl = DOL_URL_ROOT . '/custom/reedcrm/view/procard.php?from_id=' . $socid . '&from_type=societe';
                    } else {
                        $cardProUrl = DOL_URL_ROOT . '/custom/reedcrm/view/procard.php?from_id=' . $object->id . '&from_type=project&project_id=' . $object->id;
                    }
                    $out .= ' <span class="fa fa-plus-circle valignmiddle paddingleft reedcrm-card-modal-open" style="cursor:pointer;" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . ($isThirdpartyContext ? '' : $object->id) . '" data-modal-url="' . dol_escape_htmltag($cardProUrl) . '">';
                    $out .= '<input type="hidden" class="modal-options" data-modal-to-open="' . $modalId . '">';
                    $out .= '</span>';
                }
                if (!empty($lastActionComm)) {
                    $out .= '<br>' . dolButtonToOpenUrlInDialogPopup('lastActionComm' . $object->id, $langs->transnoentities('LastEvent') . ' : ' . $lastActionComm->label, $form->textwithpicto(img_picto('', $lastActionComm->picto) . ' ' . $lastActionComm->label, $lastActionComm->note_private), '/comm/action/card.php?id=' . $lastActionComm->id, '', 'classlink button bordertransp', "window.saturne.toolbox.checkIframeCreation();");
                }
                $out .= '</td></tr>';

                ?>
                <script>
                    jQuery('.tableforfield').last().append(<?php echo json_encode($out); ?>)
                </script>
                <?php

                // Inject CSS for the eventpro side modal
                $reedcrmMainCssPath = dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1);
                $reedcrmCssPath = dol_buildpath('/custom/reedcrm/css/temp-framework.css', 1);
                print '<link href="' . $reedcrmMainCssPath . '" rel="stylesheet">';
                print '<link href="' . $reedcrmCssPath . '" rel="stylesheet">';

                $modalId = 'eventproCardModal';
                $langs->load('reedcrm@reedcrm');
                ?>
                <div class="wpeo-modal modal-eventpro" id="<?php echo $modalId; ?>">
                    <div class="modal-container wpeo-modal-event">
                        <div class="modal-header">
                            <h2 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('QuickEventCreation')); ?></h2>
                            <div class="modal-close"><i class="fas fa-times"></i></div>
                        </div>
                        <div class="modal-content">
                            <div id="eventproCardModal-loader" class="wpeo-loader"></div>
                            <div id="eventproCardModal-content"></div>
                        </div>
                    </div>
                </div>
                <script>
                    // Only load eventpro.js if not already loaded (avoid double loading saturne.min.js)
                    if (typeof window.reedcrm === 'undefined' || typeof window.reedcrm.eventpro === 'undefined') {
                        var script = document.createElement('script');
                        script.src = '<?php echo dol_buildpath('/custom/reedcrm/js/modules/eventpro.js', 1); ?>';
                        script.onload = function() {
                            if (window.reedcrm && window.reedcrm.eventpro && window.reedcrm.eventpro.init) {
                                window.reedcrm.eventpro.init();
                            }
                        };
                        document.body.appendChild(script);
                    } else {
                        // Already loaded, just re-init
                        window.reedcrm.eventpro.init();
                    }
                </script>
                <?php
            }

            if (!empty($object->array_options['options_projectaddress'])) {
                $contact = new Contact($db);
                $result  = $contact->fetch($object->array_options['options_projectaddress']);
                if ($result > 0) {
                    $pictoContact = img_picto('', 'contact', 'class="pictofixedwidth"') . $contact->lastname;
                    $outAddress = '<td>';
                    $outAddress .= dolButtonToOpenUrlInDialogPopup('address' . $result, $langs->transnoentities('FavoriteAddress'), $pictoContact, '/contact/card.php?id='. $contact->id, '', 'classlink button bordertransp', "window.saturne.toolbox.checkIframeCreation();");
                    $outAddress .= '</td></tr>';
                    ?>
                    <script>
                        jQuery('.valuefield.project_extras_projectaddress').replaceWith(<?php echo json_encode($outAddress); ?>)
                    </script>
                    <?php
                }
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
                }
                if ($object && $object->element == 'project') {
                    if (empty($object->array_options)) {
                        $object->fetch_optionals();
                    }
                    $opt_lastname  = trim($object->array_options['options_reedcrm_lastname'] ?? '');
                    $opt_firstname = trim($object->array_options['options_reedcrm_firstname'] ?? '');
                    $opt_phone     = trim($object->array_options['options_projectphone'] ?? '');
                    $opt_email     = trim($object->array_options['options_reedcrm_email'] ?? '');
                    $opt_website   = trim($object->array_options['options_reedcrm_website'] ?? '');
                    $opt_contactName = trim($opt_firstname . ' ' . $opt_lastname);
                // Data is now passed to JS via saturneBannerTab 
                // and DOM mutations are handled purely by module contact_inline.js
                }
            }
        }

        if (preg_match('/invoicelist|invoicereclist|thirdpartylist|projectlist/', $parameters['context'])) {
            $cssPath = dol_buildpath('/saturne/css/saturne.min.css', 1);
            print '<link href="' . $cssPath . '" rel="stylesheet">';
            // Load reedcrm modal CSS and JS for projectlist
            if (strpos($parameters['context'], 'projectlist') !== false) {
                global $langs;
                // Load main reedcrm CSS
                $reedcrmMainCssPath = dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1);
                print '<link href="' . $reedcrmMainCssPath . '" rel="stylesheet">';
                $reedcrmCssPath = dol_buildpath('/custom/reedcrm/css/temp-framework.css', 1);
                print '<link href="' . $reedcrmCssPath . '" rel="stylesheet">';
                $jsPath = dol_buildpath('/saturne/js/saturne.min.js', 1);
                print '<script src="' . $jsPath . '"></script>';

                // Single modal for all projects
                $modalId = 'eventproCardModal';
                $langs->load('reedcrm@reedcrm');
                ?>
                <div class="wpeo-modal modal-eventpro" id="<?php echo $modalId; ?>">
                    <div class="modal-container wpeo-modal-event">
                        <!-- Modal-Header -->
                        <div class="modal-header">
                            <h2 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('QuickEventCreation')); ?></h2>
                            <div class="modal-close"><i class="fas fa-times"></i></div>
                        </div>
                        <!-- Modal-Content -->
                        <div class="modal-content">
                            <div id="eventproCardModal-loader" class="wpeo-loader"></div>
                            <div id="eventproCardModal-content"></div>
                        </div>
                    </div>
                </div>
                <script type="text/javascript" src="<?php echo dol_buildpath('/custom/reedcrm/js/modules/eventpro.js', 1); ?>"></script>
                <script type="text/javascript"
                    src="<?php echo dol_buildpath('/custom/reedcrm/js/modules/vocal-player.js', 1); ?>"></script>
                <script>
                    jQuery(document).ready(function () {
                        if (typeof window.reedcrm !== 'undefined' && window.reedcrm.eventpro && window.reedcrm.eventpro.init) {
                            window.reedcrm.eventpro.init();
                        }
                    });
                </script>
                <?php
            }

            $jQueryElement = 'notation_' . $object->element . '_contact';
            $pictoPath     = dol_buildpath('/reedcrm/img/reedcrm_color.png', 1);
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
            $pictoPath     = dol_buildpath('/reedcrm/img/reedcrm_color.png', 1);
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

        if (strpos($parameters['context'], 'projectlist') !== false) {
            if (isModEnabled('project') && $user->hasRight('projet', 'lire') && isModEnabled('saturne')) {
                ?>
                <script>
                    $('table tr.oddeven td').css('padding', 2);

                    var titles = ["Réf.", "Date fin", "Assigné à", "Statut opportunité", "Relances commerciales", "Montant pondéré opp.", "Montant opportunité", "Vocal"];

                    // Récupère les index correspondants
                    var indexes = [];

                    titles.forEach(function(title) {
                        var index = $('th[title="' + title + '"]').index();
                        if (index !== -1) {
                            indexes.push(index);
                        }
                    });

                    // Applique le traitement sur chaque colonne trouvée
                    indexes.forEach(function(index) {
                        var cells = $('table tr').find('td:eq(' + index + ')');
                        cells.removeClass('tdoverflowmax200');
                        cells.removeClass('right');
                        cells.removeClass('tdoverflowmax150');
                        cells.addClass('tdoverflowmax75');
                        cells.find('span.fa-project-diagram').remove();
                    });
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
    public function hookSetManifest(array $parameters): int
    {
        if (strpos($_SERVER['PHP_SELF'], 'reedcrm') !== false) {
            $this->resprints = DOL_URL_ROOT . '/custom/reedcrm/manifest.json.php';

            return 1;
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
                $filter = ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . $conf->global->REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG . ')';
                if (is_object($parameters['obj']) && !empty($parameters['obj'])) {
                    if (!empty($parameters['obj']->id)) {
                        $out = '<td class="tdoverflowmax200">';
                        if (isModEnabled('agenda')) {
                            require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                            $actionComm = new ActionComm($db);

                            $actionComms = $actionComm->getActions($parameters['obj']->socid, $parameters['obj']->id, 'project', $filter, 'a.datec');

                            $countsByType = [
                                'call' => 0,
                                'email' => 0,
                                'rdv' => 0,
                                'other' => 0
                            ];

                            $relaunchesByType = [
                                'call' => [],
                                'email' => [],
                                'rdv' => [],
                                'other' => []
                            ];

                            if (is_array($actionComms) && !empty($actionComms)) {
                                $nbActionComms = count($actionComms);

                                require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
                                require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

                                foreach ($actionComms as $ac) {
                                    $contactName = '';
                                    if (!empty($ac->contact_id)) {
                                        $contact = new Contact($db);
                                        if ($contact->fetch($ac->contact_id) > 0) {
                                            $contactName = $contact->getFullName($langs);
                                        }
                                    }

                                    $userName = '';
                                    if (!empty($ac->userownerid)) {
                                        $userOwner = new User($db);
                                        if ($userOwner->fetch($ac->userownerid) > 0) {
                                            $userName = $userOwner->getFullName($langs);
                                        }
                                    }

                                    $status = '';
                                    if (isset($ac->percentage)) {
                                        if ($ac->percentage >= 100) {
                                            $status = $langs->trans('Done');
                                        } elseif ($ac->percentage > 0) {
                                            $status = $ac->percentage . '%';
                                        }
                                    }

                                    $note = '';
                                    if (!empty($ac->note_private)) {
                                        $note = dolGetFirstLineOfText(dol_string_nohtmltag($ac->note_private, 1));
                                        $note = dol_trunc($note, 100);
                                    }

                                    $relaunchData = [
                                        'date' => dol_print_date($ac->datec, 'dayhourtext', 'tzuser'),
                                        'datep' => dol_print_date($ac->datep, 'dayhour', 'tzuser'),
                                        'label' => $ac->label,
                                        'note' => $note,
                                        'contact' => $contactName,
                                        'user' => $userName,
                                        'status' => $status,
                                        'id' => $ac->id
                                    ];

                                    if ($ac->type_code == 'AC_TEL') {
                                        $countsByType['call']++;
                                        $relaunchesByType['call'][] = $relaunchData;
                                    } elseif ($ac->type_code == 'AC_EMAIL') {
                                        $countsByType['email']++;
                                        $relaunchesByType['email'][] = $relaunchData;
                                    } elseif ($ac->type_code == 'AC_RDV') {
                                        $countsByType['rdv']++;
                                        $relaunchesByType['rdv'][] = $relaunchData;
                                    } else {
                                        $countsByType['other']++;
                                        $relaunchesByType['other'][] = $relaunchData;
                                    }
                                }
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

                            $modalId = 'eventproCardModal';
                            $cardProUrl = '/custom/reedcrm/view/procard.php?from_id=' . $parameters['obj']->id . '&from_type=project&project_id=' . $parameters['obj']->id;

                            $out .= '<div class="reedcrm-plist-relaunch-wrapper">';
                            $out .= '<div class="reedcrm-plist-relaunch-buttons reedcrm-relaunch-buttons">';

                            $out .= '<div class="reedcrm-relaunch-button reedcrm-plist-relaunch-btn-call" data-relaunch-type="call" data-relaunches="' . dol_escape_htmltag(json_encode($relaunchesByType['call'])) . '">';
                            $out .= '<div class="reedcrm-plist-relaunch-btn-content' . ($countsByType['call'] == 0 ? ' count-zero' : '') . '">';
                            $out .= '<i class="fas fa-headset"></i>';
                            $out .= '<span class="reedcrm-plist-relaunch-count">' . $countsByType['call'] . '</span>';
                            $out .= '</div>';
                            if ($user->hasRight('agenda', 'myactions', 'create')) {
                                $cardProUrlFull = DOL_URL_ROOT . $cardProUrl . '&actioncode=AC_TEL';
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $parameters['obj']->id . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
                                $out .= '<input type="hidden" class="modal-options" data-modal-to-open="' . $modalId . '">';
                                $out .= '</span>';
                            }
                            $out .= '</div>';

                            $out .= '<div class="reedcrm-relaunch-button reedcrm-plist-relaunch-btn-email" data-relaunch-type="email" data-relaunches="' . dol_escape_htmltag(json_encode($relaunchesByType['email'])) . '">';
                            $out .= '<div class="reedcrm-plist-relaunch-btn-content' . ($countsByType['email'] == 0 ? ' count-zero' : '') . '">';
                            $out .= '<i class="fas fa-envelope"></i>';
                            $out .= '<span class="reedcrm-plist-relaunch-count">' . $countsByType['email'] . '</span>';
                            $out .= '</div>';
                            if ($user->hasRight('agenda', 'myactions', 'create')) {
                                $cardProUrlFull = DOL_URL_ROOT . $cardProUrl . '&actioncode=AC_EMAIL';
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $parameters['obj']->id . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
                                $out .= '<input type="hidden" class="modal-options" data-modal-to-open="' . $modalId . '">';
                                $out .= '</span>';
                            }
                            $out .= '</div>';

                            $out .= '<div class="reedcrm-relaunch-button reedcrm-plist-relaunch-btn-rdv" data-relaunch-type="rdv" data-relaunches="' . dol_escape_htmltag(json_encode($relaunchesByType['rdv'])) . '">';
                            $out .= '<div class="reedcrm-plist-relaunch-btn-content' . ($countsByType['rdv'] == 0 ? ' count-zero' : '') . '">';
                            $out .= '<i class="fas fa-calendar"></i>';
                            $out .= '<span class="reedcrm-plist-relaunch-count">' . $countsByType['rdv'] . '</span>';
                            $out .= '</div>';
                            if ($user->hasRight('agenda', 'myactions', 'create')) {
                                $cardProUrlFull = DOL_URL_ROOT . $cardProUrl . '&actioncode=AC_RDV';
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $parameters['obj']->id . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
                                $out .= '<input type="hidden" class="modal-options" data-modal-to-open="' . $modalId . '">';
                                $out .= '</span>';
                            }
                            $out .= '</div>';

                            $out .= '<div class="reedcrm-relaunch-button reedcrm-plist-relaunch-btn-other" data-relaunch-type="other" data-relaunches="' . dol_escape_htmltag(json_encode($relaunchesByType['other'])) . '">';
                            $out .= '<div class="reedcrm-plist-relaunch-btn-content' . ($countsByType['other'] == 0 ? ' count-zero' : '') . '">';
                            $out .= '<i class="fas fa-comment-dots"></i>';
                            $out .= '<span class="reedcrm-plist-relaunch-count">' . $countsByType['other'] . '</span>';
                            $out .= '</div>';
                            if ($user->hasRight('agenda', 'myactions', 'create')) {
                                $cardProUrlFull = DOL_URL_ROOT . $cardProUrl . '&actioncode=AC_OTH';
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $parameters['obj']->id . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
                                $out .= '<input type="hidden" class="modal-options" data-modal-to-open="' . $modalId . '">';
                                $out .= '</span>';
                            }
                            $out .= '</div>';

                            $out .= '</div>';

//                            $oppPercent = isset($parameters['obj']->opp_percent) ? (int) $parameters['obj']->opp_percent : 0;
//                            // Adding progress bar right after badges
//                            $out .= '<div class="reedcrm-plist-progress-wrapper">';
//                            $out .= '<div class="reedcrm-plist-progress-bg">';
//                            $out .= '<div class="reedcrm-plist-progress-fill" style="width: ' . $oppPercent . '%;"></div>';
//                            $out .= '</div>';
//                            $out .= '<span class="reedcrm-plist-progress-text"><i class="fas fa-redo"></i> ' . $oppPercent . '%</span>';
//                            $out .= '</div>';
//
//                            $out .= '</div>';
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

                        // Workaround: Display project description in the custom extrafield 'description' (or 'descrpitiion' as typoed by user)
                        $out6 = '<td class="tdoverflowmax500">';
                        $tmpProject = new Project($this->db);
                        if ($tmpProject->fetch($parameters['obj']->id) > 0) {
                            $desc = $tmpProject->description;
                            $out6 .= !empty($desc) ? (dol_textishtml($desc) ? $desc : dol_nl2br(dol_escape_htmltag($desc))) : '';
                        }
                        $out6 .= '</td>';

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

                        // Extrafield vocal - bouton play violet + badge Agent Digital
                        require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
                        $projectDir = $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($parameters['obj']->ref);
                        $audioFiles = dol_dir_list($projectDir, 'files', 0, '\.(mp3|ogg|wav|m4a|aac|webm|opus)$', null, 'date', SORT_DESC);
                        $out4 = '<td class="center valignmiddle">';
                        $out4 .= '<div class="reedcrm-vocal-cell">';
                        if (!empty($audioFiles)) {
                            $lastAudio = $audioFiles[0];
                            $fileUrl = DOL_URL_ROOT . '/document.php?modulepart=projet&file=' . urlencode(dol_sanitizeFileName($parameters['obj']->ref) . '/' . $lastAudio['name']);
                            $out4 .= '<div class="reedcrm-vocal-player reedcrm-vocal-player-purple" data-audio-url="' . dol_escape_htmltag($fileUrl) . '" title="' . dol_escape_htmltag($lastAudio['name']) . '">';
                            $out4 .= '<i class="fas fa-play"></i>';
                            $out4 .= '</div>';
                        } else {
                            // Display disabled/empty state if no audio file instead of nothing to match table layout
                            $out4 .= '<div class="reedcrm-vocal-player reedcrm-vocal-player-purple disabled">';
                            $out4 .= '<i class="fas fa-play"></i>';
                            $out4 .= '</div>';
                        }
                        $out4 .= '</div>';
                        $out4 .= '</td>';

                        // Coordonnées - infos contact (nom tiers, email, tél, icônes)
                        $thirdPartyName = !empty($parameters['obj']->options_reedcrm_lastname) ? dol_escape_htmltag($parameters['obj']->options_reedcrm_lastname) : '';
                        $thirdPartyName2 = !empty($parameters['obj']->options_reedcrm_firstname) ? dol_escape_htmltag($parameters['obj']->options_reedcrm_firstname) : '';
                        $thirdPartyEmail = !empty($parameters['obj']->options_reedcrm_email) ? dol_escape_htmltag($parameters['obj']->options_reedcrm_email) : '';
                        $thirdPartyPhone = !empty($parameters['obj']->options_projectphone) ? dol_escape_htmltag($parameters['obj']->options_projectphone) : '';

                        // Retrieve the Societe logo using exact native approach as dol_banner_tab
                        /*$logoHtml = '';
                        if (!empty($parameters['obj']->socid)) {
                            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
                            $tmpsoc = new Societe($this->db);
                            if ($tmpsoc->fetch($parameters['obj']->socid) > 0) {
                                require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
                                if (!in_array('Form', get_declared_classes())) {
                                    $form = new Form($this->db);
                                } else {
                                    $form = new Form($this->db);
                                }
                                // Call showphoto exactly as dol_banner_tab does, but adapted width
                                $logoHtml = $form->showphoto('societe', $tmpsoc, 42, 42, 0, 'reedcrm-coordonnees-avatar-img', 'small', 0, 0);
                            }
                        }

                        if (empty($logoHtml)) {
                            $logoPlaceholderUrl = dol_buildpath('/theme/common/company.png', 1);
                            $logoHtml = '<img src="' . $logoPlaceholderUrl . '" alt="" class="reedcrm-plist-coordonnees-avatar-img" width="42" height="42">';
                        }*/

                        $out5 = '<td class="tdoverflowmax200 valignmiddle">';
                        $out5 .= '<div class="reedcrm-plist-coordonnees">';

                        // We remove the explicit height/width from the wrapper if showphoto generates a div to avoid layout breaks
//                        $out5 .= '<div class="reedcrm-plist-coordonnees-avatar">';
//                        $out5 .= $logoHtml;
//                        $out5 .= '</div>';

                        $out5 .= '<div class="reedcrm-plist-coordonnees-box">';
                        if ($thirdPartyName) {
                            $out5 .= '<div class="reedcrm-plist-coordonnees-name">' . $thirdPartyName . ' ' . $thirdPartyName2 . '</div>';
                        }
                        if ($thirdPartyEmail) {
                            $out5 .= '<div class="reedcrm-plist-coordonnees-email"><i class="fas fa-envelope"></i>' . $thirdPartyEmail . '</div>';
                        }
                        if ($thirdPartyPhone) {
                            $out5 .= '<div class="reedcrm-plist-coordonnees-phone"><i class="fas fa-phone-alt"></i>' . $thirdPartyPhone . '</div>';
                        }
                        if (!$thirdPartyName && !$thirdPartyEmail && !$thirdPartyPhone) {
                            $out5 .= '<span class="opacitymedium">-</span>';
                        }
                        $out5 .= '</div>';

                        $out5 .= '<div class="reedcrm-plist-coordonnees-actions">';
                        if ($thirdPartyPhone) {
                            $out5 .= '<a href="tel:' . preg_replace('/\s+/', '', $parameters['obj']->phone) . '" class="reedcrm-plist-coordonnees-btn"><i class="fas fa-phone-alt"></i></a>';
                        }
                        $out5 .= '</div>';

                        $out5 .= '</div>';
                        $out5 .= '</td>';

                    }
                    $rowId = (int) $parameters['obj']->id; ?>
                    <script>
                        (function () {
                            var rowId = <?php echo $rowId; ?>;
                            var $row = jQuery('tr[data-rowid="' + rowId + '"]');
                            var outJS = <?php echo json_encode($out); ?>;
                            var outJS2 = <?php echo json_encode($out2); ?>;
                            var outJS3 = <?php echo json_encode($out3); ?>;
                            var outJS4 = <?php echo json_encode($out4); ?>;
                            var outJS5 = <?php echo json_encode($out5); ?>;
                            var outJS6 = <?php echo json_encode($out6); ?>;
                            var commRelauchCell = $row.find('td[data-key="projet.commrelaunch"]');
                            var commTaskCell = $row.find('td[data-key="projet.commtask"]');
                            var probCell = $row.find("td.right").filter(function () { return jQuery(this).text().indexOf('%') >= 0; });
                            var vocalCell = $row.find('td[data-key="projet.vocal"]');
                            var coordonneesCell = $row.find('td[data-key="projet.contact_informations"]');
                            var descriptionCell = $row.find('td[data-key="projet.description"], td[data-key="ef.description"], td.projet_extras_description');

                            if (commRelauchCell.length) commRelauchCell.replaceWith(outJS);
                            if (commTaskCell.length) commTaskCell.replaceWith(outJS2);
                            if (probCell.length) probCell.replaceWith(outJS3);
                            if (vocalCell.length) vocalCell.replaceWith(outJS4);
                            if (coordonneesCell.length) coordonneesCell.replaceWith(outJS5);
                            if (descriptionCell.length) descriptionCell.replaceWith(outJS6);
                        })();
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
            $parameters['head'][1][0] = DOL_URL_ROOT . '/custom/reedcrm/view/contact.php?id=' . $parameters['object']->id;
            $parameters['head'][1][1] = $langs->trans('ContactsAddresses');
            if ($nbContact > 0) {
                $parameters['head'][1][1] .= '<span class="badge marginleftonlyshort">' . $nbContact . '</span>';
            }
            $parameters['head'][1][2] = 'contact';

            $this->results = $parameters;
        }

        if (strpos($parameters['context'], 'main') !== false) {
            if (!empty($parameters['head'])) {
                foreach ($parameters['head'] as $headKey => $headTab) {
                    if (is_array($headTab) && count($headTab) > 0) {
                        if (isset($headTab[2]) && $headTab[2] === 'address' && is_string($headTab[1]) && strpos($headTab[1], $langs->transnoentities('Addresses')) !== false && strpos($headTab[1], 'badge') === false) {
                            $listContact = $parameters['object']->liste_contact();
                            $contactCount = 0;
                            if ($listContact != -1) {
                                $filteredContact = array_filter($listContact, function ($var) {return $var['code'] == 'PROJECTADDRESS';});
                                $contactCount    = count($filteredContact);
                            }
                            $parameters['head'][$headKey][1] .= '<span class="badge marginleftonlyshort">' . $contactCount . '</span>';
                        }
                    }
                }
            }
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

        if (GETPOST('module_name') == 'ReedCRM' && strpos($parameters['context'], 'pwaadmin') !== false) {
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
            $out .= ajax_constantonoff('REEDCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO');
            $out .= '</td></tr>';

            $out .= '</table>';

            $this->resprints = $out;
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the getTooltipContent function : intercepting the tooltip content
     *
     * @param  array        $parameters Hook metadatas (context, etc...)
     * @param  CommonObject $object     Current object
     * @param  string       $action     Current action
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function getTooltipContent(array $parameters, CommonObject $object, string $action): int
    {
        if (strpos($parameters['context'], 'projectdao') !== false) {
            if (isset($parameters['tooltipcontentarray'])) {
                global $langs, $conf;
                $data = &$parameters['tooltipcontentarray'];

                // Top row: Picto / Status and Opportunity Amount (flex layout)
                if (isset($data['picto'])) {
                    $oppAmount = price($object->opp_amount, 1, $langs, 1, -1, -1, $conf->currency);
                    $oppAmountStr = '<b>' . $langs->trans('OpportunityAmount') . '</b> &nbsp;' . $oppAmount;
                    $data['picto'] = '<div style="display: flex; justify-content: space-between; align-items: center; gap: 20px;"><div>' . $data['picto'] . '</div><div>' . $oppAmountStr . '</div></div>';
                }

                // Second row: Ref, Date start, Date end
                $refLine = '<div style="margin-top: 5px;">';
                if (isset($data['ref'])) {
                    $refLine .= '<b>' . $langs->trans('Ref') . '.:</b> ' . $object->ref;
                }
                if (!empty($object->date_start)) {
                    $refLine .= ' - <b>' . $langs->trans('DateStart') . ':</b> ' . dol_print_date($object->date_start, 'day');
                }
                if (!empty($object->date_end)) {
                    $refLine .= ' &nbsp;&nbsp;&nbsp;<b>' . $langs->trans('DateEnd') . ':</b> ' . dol_print_date($object->date_end, 'day');
                }
                $refLine .= '</div>';
                $data['ref'] = $refLine;

                // Remove the standard datestart and dateend, as they are now on the ref line
                unset($data['datestart']);
                unset($data['dateend']);

                // Third row: Libellé
                if (isset($data['label'])) {
                    $data['label'] = '<div><span style="color: #9b2226; font-weight: bold;">' . $langs->trans('Label') . '</span> &nbsp;&nbsp;' . $object->title . '</div>';
                }

                // Fourth row (or below): Description
                unset($data['description']); // ensure no duplication
                if (!empty($object->description)) {
                    $langs->load('projects');
                    $data['custom_desc'] = '<div style="margin-top: 5px;"><b>' . $langs->trans('Description') . ':</b> ' . dol_string_nohtmltag($object->description) . '</div>';
                }

                // Add the 5 Relaunch actions directly into the tooltip (the hover)
                $urlQuickEvent = DOL_URL_ROOT . '/custom/reedcrm/view/quickevent.php?projectid=' . $object->id;
                $buttons = '<div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">';
                $buttons .= '<a class="butAction" style="padding: 2px 5px; font-size: 10px;" href="' . $urlQuickEvent . '&label=' . urlencode('RELANCE J0') . '">RELANCE J0</a>';
                $buttons .= '<a class="butAction" style="padding: 2px 5px; font-size: 10px;" href="' . $urlQuickEvent . '&label=' . urlencode('RELANCE J7') . '">RELANCE J7</a>';
                $buttons .= '<a class="butAction" style="padding: 2px 5px; font-size: 10px;" href="' . $urlQuickEvent . '&label=' . urlencode('RELANCE J14') . '">RELANCE J14</a>';
                $buttons .= '<a class="butAction" style="padding: 2px 5px; font-size: 10px;" href="' . $urlQuickEvent . '&label=' . urlencode('RELANCE M1') . '">RELANCE M1</a>';
                $buttons .= '<a class="butAction" style="padding: 2px 5px; font-size: 10px;" href="' . $urlQuickEvent . '&label=' . urlencode('RELANCE M6+') . '">RELANCE M6+</a>';
                $buttons .= '</div>';
                $data['relaunch_actions'] = $buttons;

                // Remove unwanted fields and extra margin wrappings
                unset($data['visibility']);
                unset($data['vocal']);
                unset($data['contact_informations']);
                unset($data['opp_amount']); // In case opp_amount exists as extrafield
                unset($data['more_extrafields']); // Remove the "..." added when there are too many extrafields
                unset($data['opendivextra']); // Remove empty div margins
                unset($data['closedivextra']);
            }
        }

        return 0; // or return 1 to replace standard code
    }
    public function formDolBanner($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $user, $db;

        if (strpos($parameters['context'], 'projectcard') !== false) {
            if ($object && $object->element == 'project') {
                $object->fetch_optionals();
                
                $opt_lastname  = trim($object->array_options['options_reedcrm_lastname'] ?? '');
                $opt_firstname = trim($object->array_options['options_reedcrm_firstname'] ?? '');
                $opt_phone     = trim($object->array_options['options_projectphone'] ?? '');
                $opt_email     = trim($object->array_options['options_reedcrm_email'] ?? '');
                $opt_website   = trim($object->array_options['options_reedcrm_website'] ?? '');
                
                $hFirstName = $opt_firstname ? dol_escape_htmltag($opt_firstname) : '<span style="color:#cbd5e0; font-style:italic;">Prénom</span>';
                $hLastName = $opt_lastname ? dol_escape_htmltag($opt_lastname) : '<span style="color:#cbd5e0; font-style:italic;">Nom</span>';
                $hPhone = $opt_phone ? dol_escape_htmltag($opt_phone) : '<span style="color:#cbd5e0; font-style:italic;">Téléphone</span>';
                $hEmail = $opt_email ? dol_escape_htmltag($opt_email) : '<span style="color:#cbd5e0; font-style:italic;">Email</span>';
                $hWebClean = $opt_website ? preg_replace('#^https?://#', '', $opt_website) : '';
                $hWeb = $opt_website ? dol_escape_htmltag($hWebClean) : '<span style="color:#cbd5e0; font-style:italic;">Site Web</span>';
                
                $linkPhone = $opt_phone ? '<a href="tel:'.dol_escape_htmltag($opt_phone).'" style="color: inherit; text-decoration: none;" title="Appeler"><i class="fas fa-phone" style="color: #64748b; margin-right: 6px; cursor: pointer;"></i></a>' : '<i class="fas fa-phone" style="color: #64748b; margin-right: 6px;"></i>';
                $linkEmail = $opt_email ? '<a href="mailto:'.dol_escape_htmltag($opt_email).'" style="color: inherit; text-decoration: none;" title="Envoyer un email"><i class="fas fa-envelope" style="color: #64748b; margin-right: 6px; cursor: pointer;"></i></a>' : '<i class="fas fa-envelope" style="color: #64748b; margin-right: 6px;"></i>';
                $linkWeb = $opt_website ? '<a href="' . (strpos($opt_website, 'http') === 0 ? dol_escape_htmltag($opt_website) : 'https://'.dol_escape_htmltag($opt_website)) . '" target="_blank" style="color: inherit; text-decoration: none;" title="Ouvrir le site web"><i class="fas fa-globe" style="color: #64748b; margin-right: 6px; cursor: pointer;"></i></a>' : '<i class="fas fa-globe" style="color: #64748b; margin-right: 6px;"></i>';
                
                $logoPath = dol_buildpath('/reedcrm/img/reedcrm.png', 1);
                
                // Append the Contact Editor natively to the refidno block via the hook.
                $contactHtml = '<div class="contact-inline-wrapper reedcrm-header-contact-master" data-project-id="' . (int)$object->id . '" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;">' .
                    '<img src="' . $logoPath . '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />' .
                    '<i class="fas fa-address-book" style="color: #64748b; margin-right: 6px;"></i>' .
                    '<span class="inline-edit-contact" data-field="firstname" data-val="'.dol_escape_htmltag($opt_firstname).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 4px;" title="Modifier le prénom">' . $hFirstName . '</span>' .
                    '<span class="inline-edit-contact" data-field="lastname" data-val="'.dol_escape_htmltag($opt_lastname).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 8px;" title="Modifier le nom">' . $hLastName . '</span>' .
                    '<span style="color: #cbd5e0; margin-right: 8px;">&bull;</span>' .
                    $linkPhone .
                    '<span class="inline-edit-contact" data-field="phone" data-val="'.dol_escape_htmltag($opt_phone).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 8px;" title="Modifier le téléphone">' . $hPhone . '</span>' .
                    '<span style="color: #cbd5e0; margin-right: 8px;">&bull;</span>' .
                    $linkEmail .
                    '<span class="inline-edit-contact" data-field="email" data-val="'.dol_escape_htmltag($opt_email).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s; margin-right: 8px;" title="Modifier l\'email">' . $hEmail . '</span>' .
                    '<span style="color: #cbd5e0; margin-right: 8px;">&bull;</span>' .
                    $linkWeb .
                    '<span class="inline-edit-contact" data-field="website" data-val="'.dol_escape_htmltag($opt_website).'" style="cursor: pointer; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px; transition: color 0.3s;" title="Modifier le site web">' . $hWeb . '</span>' .
                '</div>';
                
                $rawAmount = empty($object->opp_amount) ? '0' : (float)$object->opp_amount;
                $percentStr = $object->opp_percent ? (float)$object->opp_percent . ' %' : '0 %';
                $amountStrRaw = $object->opp_amount ? price($object->opp_amount, 0, $langs, 11, -1, -1, 'auto') : '0 €';
                $amountStr = str_replace([',00', '.00'], '', $amountStrRaw);
                
                $langs->load('companies');
                $newThirdPartyUrl = DOL_URL_ROOT . '/societe/card.php?action=create&projectid=' . $object->id;
                $newThirdPartyLabel = dol_escape_htmltag($langs->trans('CreateThirdparty'));
                $userCanCreate = $user->hasRight('societe', 'creer') ? 1 : 0;
                
                // Mount a data island so `contact_inline.js` can initialize the rest.
                $jsMountDataHtml = '<div id="reedcrm-inline-data" style="display:none;" ' .
                    'data-project-id="' . (int)$object->id . '" ' .
                    'data-amount="' . $rawAmount . '" ' .
                    'data-percent-val="' . (int)$object->opp_percent . '" ' .
                    'data-percent-str="' . dol_escape_htmltag($percentStr) . '" ' .
                    'data-amount-str="' . dol_escape_htmltag($amountStr) . '" ' .
                    'data-btn-create="' . $userCanCreate . '" ' .
                    'data-btn-url="' . $newThirdPartyUrl . '" ' .
                    'data-btn-label="' . $newThirdPartyLabel . '" ' .
                    'data-logo-path="' . $logoPath . '" ' .
                    '></div>';

                // Ensure our custom reedcrm assets are injected so the UI logic works
                $cssPath = dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1);
                $jsPath  = dol_buildpath('/custom/reedcrm/js/reedcrm.min.js', 1);
                $assetsHtml = '<link href="' . $cssPath . '" rel="stylesheet">';
                $assetsHtml .= '<script src="' . $jsPath . '?v=' . time() . '"></script>';
                
                // Add intl-tel-input dependencies and mandatory style fixes
                $itiCssPath = dol_buildpath('/reedcrm/js/intl-tel-input/css/intlTelInput.css', 1);
                $itiJsPath  = dol_buildpath('/reedcrm/js/intl-tel-input/js/intlTelInput.min.js', 1);
                $assetsHtml .= '<link href="' . $itiCssPath . '" rel="stylesheet">';
                $assetsHtml .= '<style> .iti { width: 100%; display: block; } .iti input[type="tel"] { padding-left: 52px !important; } input.input-invalid-material { border-color: #e53935 !important; border-bottom: 2px solid #e53935 !important; color: #e53935 !important; } </style>';
                $assetsHtml .= '<script src="' . $itiJsPath . '"></script>';

                // Inject ReedCRM favicon next to the "Origine de l'opportunitée" extrafield label
                $pictoOpporiginHtml = img_picto('', $logoPath, '', 1, 0, 0, '', 'pictoModule');
                $assetsHtml .= '<script>
                    (function() {
                        var iconHtml = ' . json_encode($pictoOpporiginHtml) . ';
                        var labelText = "' . dol_escape_js($langs->transnoentities('OpportunityOrigin')) . '";
                        
                        function addFaviconToLabel() {
                            var tr = null;
                            // Find the row containing the extrafield
                            var input = document.getElementById("options_opporigin");
                            if (input) {
                                tr = input.closest("tr");
                            } else {
                                var valCell = document.querySelector("td.projet_extras_opporigin, td.project_extras_opporigin");
                                if (valCell) {
                                    tr = valCell.closest("tr");
                                }
                            }
                            
                            if (tr) {
                                var labelTd = tr.firstElementChild;
                                if (labelTd && !labelTd.querySelector("img.pictoModule")) {
                                    var targetContainer = labelTd;
                                    // Traverse to the deepest element that contains the text but NO tables
                                    // This preserves Dolibarr native layout tables used for the "Edit Pencil" icon alignment
                                    var innerNodes = labelTd.querySelectorAll("td, span, div, label");
                                    for (var i = 0; i < innerNodes.length; i++) {
                                        var node = innerNodes[i];
                                        if (node.textContent.trim().indexOf(labelText) !== -1 && !node.querySelector("table")) {
                                            targetContainer = node;
                                        }
                                    }
                                    targetContainer.insertAdjacentHTML("afterbegin", iconHtml + "&nbsp;");
                                }
                            }
                        }
                        
                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", addFaviconToLabel);
                        } else {
                            addFaviconToLabel();
                        }
                    })();
                </script>';
                // Inject Refusal Reason stylistic badges natively
                $assetsHtml .= '<script>
                    (function() {
                        var badgesConfig = {
                            "Trop compliqué": { color: "#ffffff", bg: "#e67e22", icon: "fas fa-puzzle-piece" },
                            "Pas assez cher": { color: "#ffffff", bg: "#f39c12", icon: "fas fa-arrow-down" },
                            "Trop cher": { color: "#ffffff", bg: "#e74c3c", icon: "fas fa-money-bill-wave" },
                            "A signer ailleurs": { color: "#ffffff", bg: "#3498db", icon: "fas fa-pen-fancy" },
                            "Ce n\'est plus un projet": { color: "#ffffff", bg: "#7f8c8d", icon: "fas fa-ban" },
                            "Repart sur Excel": { color: "#ffffff", bg: "#2ecc71", icon: "fas fa-file-excel" },
                            "Ne veulent pas le dire": { color: "#ffffff", bg: "#95a5a6", icon: "fas fa-comment-slash" },
                            "Interface": { color: "#ffffff", bg: "#9b59b6", icon: "fas fa-desktop" },
                            "Autre": { color: "#ffffff", bg: "#bdc3c7", icon: "fas fa-ellipsis-h" }
                        };

                        function wrapBadge(text) {
                            var c = badgesConfig[text];
                            if(!c) return text;
                            return \'<span style="display:inline-flex; align-items:center; height:24px; padding:0 8px; border-radius:3px; font-size:11.5px; font-weight:normal; background-color:\'+c.bg+\'; color:\'+c.color+\'; box-shadow: 0 1px 1px rgba(0,0,0,0.1);"><i class="\'+c.icon+\'" style="margin-right:5px;"></i>\' + text + \'</span>\';
                        }

                        // 1. Static viewing (Project & Propal card fields)
                        function styleStaticBadges() {
                            var nodes = document.querySelectorAll("td[class*=\'_extras_opprefusal\'], td[class*=\'_extras_commrefusal\']");
                            nodes.forEach(function(n) {
                                if (n.querySelector("select, input, textarea")) return;
                                var t = n.textContent.trim();
                                if (badgesConfig[t]) {
                                    n.innerHTML = wrapBadge(t);
                                }
                            });
                        }

                        // 2. Dynamic Dropdowns (Select2 Options & Selection)
                        var observer = new MutationObserver(function(mutations) {
                            mutations.forEach(function(mutation) {
                                if (mutation.addedNodes) {
                                    mutation.addedNodes.forEach(function(node) {
                                        if (node.nodeType === 1) {
                                            if (node.classList && node.classList.contains("select2-results__option")) {
                                                var t = node.textContent.trim();
                                                if (badgesConfig[t]) {
                                                    node.innerHTML = wrapBadge(t);
                                                    node.style.padding = "2px 6px";
                                                }
                                            } else if (node.querySelectorAll) {
                                                var opts = node.querySelectorAll(".select2-results__option");
                                                opts.forEach(function(opt) {
                                                    // Ignore if already styled
                                                    if (opt.querySelector("span[style]")) return; 
                                                    var t = opt.textContent.trim();
                                                    // Ensure we are inside a refusal dropdown context or the generic option string exactly matches
                                                    if (badgesConfig[t]) {
                                                        opt.innerHTML = wrapBadge(t);
                                                        opt.style.padding = "2px 6px";
                                                    }
                                                });
                                                
                                                var selOpts = node.querySelectorAll(".select2-selection__rendered");
                                                selOpts.forEach(function(opt) {
                                                    if (opt.querySelector("span[style]")) return; 
                                                    var t = opt.textContent.trim();
                                                    if (badgesConfig[t]) {
                                                        opt.innerHTML = wrapBadge(t);
                                                    }
                                                });
                                            }
                                            
                                            if (node.classList && node.classList.contains("select2-selection__rendered")) {
                                                var rt = node.textContent.trim();
                                                if (badgesConfig[rt]) node.innerHTML = wrapBadge(rt);
                                            }
                                        }
                                    });
                                }
                                
                                // Catch text updates inside selections
                                if (mutation.type === "characterData" || mutation.type === "childList") {
                                    var target = mutation.target;
                                    if (target.nodeType === 3) target = target.parentElement; // Get element if text node
                                    if (target && target.classList && target.classList.contains("select2-selection__rendered")) {
                                        if (target.querySelector("span[style]")) return;
                                        var txt = target.textContent.trim();
                                        if (badgesConfig[txt]) {
                                            target.innerHTML = wrapBadge(txt);
                                        }
                                    }
                                }
                            });
                        });

                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", function() {
                                styleStaticBadges();
                                observer.observe(document.body, { childList: true, subtree: true, characterData: true });
                            });
                        } else {
                            styleStaticBadges();
                            observer.observe(document.body, { childList: true, subtree: true, characterData: true });
                        }
                    })();
                </script>';
                // Quick closure UI Widget injected under the Action Tabs
                $closureWidgetHtml = '';
                // Only displays if the project is open and still tracked as an opportunity
                if ((int)$object->statut < 2 && (!isset($object->usage_opportunity) || $object->usage_opportunity == 1)) {
                    $sqlReasons = "SELECT rowid, ref, label FROM " . MAIN_DB_PREFIX . "c_refusal_reason WHERE active = 1 ORDER BY position ASC, rowid ASC";
                    $resReasons = $db->query($sqlReasons);
                    $reasonOptions = '';
                    if ($resReasons) {
                        $langs->load('reedcrm@reedcrm');
                        while ($obj = $db->fetch_object($resReasons)) {
                            $translated = $langs->trans($obj->ref);
                            if ($translated == $obj->ref) $translated = $obj->label;
                            $reasonOptions .= '<option value="' . (int)$obj->rowid . '">' . dol_escape_htmltag($translated) . '</option>';
                        }
                    }

                    $closureWidgetHtml = '
                    <script>
                        $(document).ready(function() {
                            var $target = $(".arearef, .titre").first();
                            if ($target.length > 0 && !$("#reedcrm-closure-widget").length) {
                                var widgetHtml = `
                                    <div id="reedcrm-closure-widget" style="display:inline-block; background:#fff; border:1px solid #ced4da; border-radius:6px; padding:6px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-top:5px; margin-bottom:15px; clear:both; float:right;">
                                        <div style="display:flex; align-items:center; gap:8px;">
                                            <img src="' . dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1) . '" style="height:24px; width:auto;" alt="ReedCRM" />
                                            <select id="rcrm-close-reason" style="border:1px solid #ced4da; border-radius:4px; padding:3px; outline:none; font-size:12px; min-width:130px;" class="flat">
                                                <option value="" disabled selected>-- Sélectionnez une raison --</option>
                                                ' . str_replace(["\r", "\n", "'"], ["", "", "\\'"], $reasonOptions) . '
                                            </select>
                                            <input type="date" id="rcrm-won-date" style="display:none; border:1px solid #ced4da; border-radius:4px; padding:3px; outline:none; font-size:13px; width:115px;" value="' . date("Y-m-d") . '" />
                                            <input type="number" id="rcrm-won-budget" step="0.01" style="display:none; border:1px solid #ced4da; border-radius:4px; padding:3px; outline:none; font-size:13px; width:70px; text-align:right;" value="' . (float)$object->opp_amount . '" />
                                            <span id="rcrm-won-currency" style="display:none; font-size:13px; font-weight:600; color:#495057;">€</span>
                                            <button type="button" id="rcrm-btn-lost" style="background:none; border:2px solid transparent; border-radius:4px; padding:2px; font-size:20px; cursor:pointer; opacity:1; transition:all 0.2s; line-height:1;" title="Perdu">😭</button>
                                            <button type="button" id="rcrm-btn-won" style="background:none; border:2px solid transparent; border-radius:4px; padding:2px; font-size:20px; cursor:pointer; opacity:1; transition:all 0.2s; line-height:1;" title="Gagné">🤩</button>
                                        </div>
                                        <div id="rcrm-comment-row" style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                                            <input type="text" id="rcrm-close-comment" placeholder="La raison du refus..." style="flex-grow:1; border:1px solid #ced4da; border-radius:4px; font-size:12px; padding:4px; outline:none;" />
                                            <button type="button" id="rcrm-btn-save" disabled title="Enregistrer et clôturer" style="background:#f8f9fa; border:2px solid #ced4da; color:#adb5bd; border-radius:4px; padding:2px 6px; cursor:not-allowed; font-size:14px; transition:all 0.2s;"><i class="fas fa-save"></i></button>
                                        </div>
                                    </div>
                                `;
                                
                                $target.after(widgetHtml);

                                var selectedStatus = null;

                                function checkSaveEnabled() {
                                    var reason = $("#rcrm-close-reason").val();
                                    var comment = $("#rcrm-close-comment").val().trim();
                                    var btn = $("#rcrm-btn-save");
                                    var isValid = false;
                                    
                                    if (selectedStatus === "WON") {
                                        isValid = true;
                                    } else if (selectedStatus === "LOST" && reason && comment) {
                                        isValid = true;
                                    }
                                    
                                    if (isValid) {
                                        btn.prop("disabled", false).css({"background": "#fff", "border-color": "#28a745", "color": "#28a745", "cursor": "pointer"});
                                    } else {
                                        btn.prop("disabled", true).css({"background": "#f8f9fa", "border-color": "#ced4da", "color": "#adb5bd", "cursor": "not-allowed"});
                                    }
                                }

                                $("#rcrm-close-reason").on("change", checkSaveEnabled);
                                $("#rcrm-close-comment").on("input", checkSaveEnabled);

                                $("#rcrm-btn-lost").on("click", function(e) {
                                    e.preventDefault();
                                    selectedStatus = "LOST";
                                    $(this).css("border-color", "#dc3545");
                                    $("#rcrm-btn-won").css("border-color", "transparent");
                                    $("#rcrm-close-reason").show();
                                    $("#rcrm-won-date, #rcrm-won-budget, #rcrm-won-currency").hide();
                                    $("#rcrm-close-comment").attr("placeholder", "La raison du refus...");
                                    checkSaveEnabled();
                                });
                                $("#rcrm-btn-won").on("click", function(e) {
                                    e.preventDefault();
                                    selectedStatus = "WON";
                                    $(this).css("border-color", "#28a745");
                                    $("#rcrm-btn-lost").css("border-color", "transparent");
                                    $("#rcrm-close-reason").hide().val("");
                                    $("#rcrm-won-date, #rcrm-won-budget, #rcrm-won-currency").show();
                                    $("#rcrm-close-comment").attr("placeholder", "Félicitations ! Commentaire optionnel...");
                                    checkSaveEnabled();
                                });

                                $("#rcrm-btn-save").on("click", function(e) {
                                    e.preventDefault();
                                    var reason = $("#rcrm-close-reason").val();
                                    var comment = $("#rcrm-close-comment").val();
                                    var endDate = $("#rcrm-won-date").val();
                                    var budget = $("#rcrm-won-budget").val();
                                    var objId = ' . (int)$object->id . ';
                                    var type = "' . dol_escape_js($object->element) . '";
                                    
                                    $(this).html("<i class=\'fas fa-spinner fa-spin\'></i>");
                                    
                                    $.ajax({
                                        url: "' . dol_buildpath('/custom/reedcrm/ajax/close_record.php', 1) . '",
                                        method: "POST",
                                        data: {
                                            id: objId,
                                            type: type,
                                            status: selectedStatus,
                                            reason: reason,
                                            comment: comment,
                                            end_date: endDate,
                                            budget: budget,
                                            token: "' . newToken() . '"
                                        },
                                        dataType: "json",
                                        success: function(res) {
                                            if (res.success) {
                                                window.location.reload();
                                            } else {
                                                alert("Erreur: " + (res.error || "Inconnue"));
                                                $("#rcrm-btn-save").html("<i class=\'fas fa-save\'></i>");
                                            }
                                        },
                                        error: function(xhr) {
                                            alert("Erreur de connexion.");
                                            $("#rcrm-btn-save").html("<i class=\'fas fa-save\'></i>");
                                        }
                                    });
                                });
                            }
                        });
                    </script>';
                } else if ((int)$object->statut >= 2 || (isset($object->usage_opportunity) && $object->usage_opportunity == 0 && $object->opp_status > 0)) {
                    $sqlEvent  = "SELECT a.id, a.datep, a.fk_user_author as authorid, a.label FROM " . MAIN_DB_PREFIX . "actioncomm as a";
                    $sqlEvent .= " INNER JOIN " . MAIN_DB_PREFIX . "actioncomm_extrafields as ae ON a.id = ae.fk_object";
                    $sqlEvent .= " WHERE a.fk_project = " . ((int)$object->id) . " AND ae.reedcrm_status_object = 'project_closed'";
                    $sqlEvent .= " ORDER BY a.id DESC LIMIT 1";
                    
                    $resEvent = $db->query($sqlEvent);
                    
                    if ($resEvent && $db->num_rows($resEvent) > 0) {
                        $objEvent = $db->fetch_object($resEvent);
                        
                        // Load User and Avatar
                        require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
                        $author = new User($db);
                        $author->fetch($objEvent->authorid);
                        $avatarHtml = $author->getNomUrl(-1);
                        $avatarHtmlSafe = str_replace('`', '\\`', $avatarHtml);
                        
                        // Manual URL construction to keep it compact and add target="_blank"
                        $eventUrl = dol_buildpath('/comm/action/card.php', 1) . '?id=' . ((int)$objEvent->id);
                        $eventNomUrlSafe = '<a href="' . $eventUrl . '" target="_blank" style="color:#000; text-decoration:none;" title="Ouvrir l\'événement complet"><strong>' . ((int)$objEvent->id) . '</strong></a>';
                        
                        // Action Date
                        $dateFormatted = dol_print_date($db->jdate($objEvent->datep), 'dayhour');
                        
                        // Truncate Label
                        $rawLabel = str_replace(["\r", "\n"], [" ", " "], $objEvent->label);
                        $truncatedLabel = dol_trunc($rawLabel, 60);
                        
                        // Status Emoji borders
                        $sqlStatus = "SELECT code FROM " . MAIN_DB_PREFIX . "c_lead_status WHERE rowid = " . ((int)$object->opp_status);
                        $resStatus = $db->query($sqlStatus);
                        $statusCode = '';
                        if ($resStatus && $db->num_rows($resStatus) > 0) {
                            $objSt = $db->fetch_object($resStatus);
                            $statusCode = $objSt->code;
                        }
                        
                        $isWon = ($statusCode === 'WON');
                        $lostBorder = ($statusCode === 'LOST') ? 'border:2px solid #dc3545;' : 'border:2px solid transparent; opacity:0.6;';
                        $wonBorder = $isWon ? 'border:2px solid #28a745;' : 'border:2px solid transparent; opacity:0.6;';
                        
                        $btnIcon = 'fa-undo';
                        $btnTitle = $isWon ? 'Annuler la signature et repasser en opportunité' : 'Annuler la clôture et repasser en opportunité';
                        
                        $closureWidgetHtml = '
                        <script>
                            $(document).ready(function() {
                                var $target = $(".arearef, .titre").first();
                                if ($target.length > 0 && !$("#reedcrm-closure-widget").length) {
                                    var widgetHtml = `
                                        <div id="reedcrm-closure-widget" style="display:inline-block; background:#fff; border:1px solid #ced4da; border-radius:6px; padding:6px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-top:5px; margin-bottom:15px; clear:both; float:right;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="' . dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1) . '" style="height:24px; width:auto;" alt="ReedCRM" />
                                                <i class="far fa-calendar-alt" style="color:#6c757d;"></i>
                                                <span style="font-size:13px; font-weight:normal; color:#000;">' . $eventNomUrlSafe . '</span>
                                                <span style="font-size:13px; color:#495057;">' . $dateFormatted . '</span>
                                                <div style="margin-left:4px; height:24px; display:inline-flex; align-items:center;">' . $avatarHtmlSafe . '</div>
                                                <div style="margin-left:8px; display:flex; gap:8px;">
                                                    <span style="' . $lostBorder . ' border-radius:4px; padding:2px; font-size:20px; line-height:1; display:inline-block;" title="Perdu">😭</span>
                                                    <span style="' . $wonBorder . ' border-radius:4px; padding:2px; font-size:20px; line-height:1; display:inline-block;" title="Gagné">🤩</span>
                                                </div>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                                                <div style="flex-grow:1; border:1px solid #ced4da; background:#f8f9fa; border-radius:4px; font-size:12px; padding:4px 8px; color:#495057; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:300px;" title="' . htmlspecialchars($rawLabel, ENT_QUOTES) . '">
                                                    ' . htmlspecialchars($truncatedLabel, ENT_QUOTES) . '
                                                </div>
                                                <button type="button" id="rcrm-btn-reopen" title="' . $btnTitle . '" style="margin-left:auto; background:#fff; border:2px solid #28a745; color:#28a745; border-radius:4px; padding:2px 6px; cursor:pointer; font-size:14px; transition:all 0.2s;"><i class="fas ' . $btnIcon . '"></i></button>
                                            </div>
                                        </div>
                                    `;
                                    
                                    $target.after(widgetHtml);
                                    
                                    $("#rcrm-btn-reopen").on("click", function(e) {
                                        e.preventDefault();
                                        var objId = ' . (int)$object->id . ';
                                        var type = "' . dol_escape_js($object->element) . '";
                                        $(this).html("<i class=\'fas fa-spinner fa-spin\'></i>");
                                        
                                        $.ajax({
                                            url: "' . dol_buildpath('/custom/reedcrm/ajax/close_record.php', 1) . '",
                                            method: "POST",
                                            data: { action: "reopen", id: objId, type: type, token: "' . newToken() . '" },
                                            dataType: "json",
                                            success: function(res) { if (res.success) window.location.reload(); else { alert("Erreur: " + res.error); $("#rcrm-btn-reopen").html("<i class=\'fas ' . $btnIcon . '\'></i>"); } },
                                            error: function() { alert("Erreur de connexion."); $("#rcrm-btn-reopen").html("<i class=\'fas ' . $btnIcon . '\'></i>"); }
                                        });
                                    });
                                }
                            });
                        </script>';
                    } else {
                        // Fallback: No actioncomm found for this project
                        $closureWidgetHtml = '
                        <script>
                            $(document).ready(function() {
                                var $target = $(".arearef, .titre").first();
                                if ($target.length > 0 && !$("#reedcrm-closure-widget").length) {
                                    var widgetHtml = `
                                        <div id="reedcrm-closure-widget" style="display:inline-block; background:#fff; border:1px solid #ced4da; border-radius:6px; padding:6px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-top:5px; margin-bottom:15px; clear:both; float:right;">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="' . dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1) . '" style="height:24px; width:auto;" alt="ReedCRM" />
                                                <i class="far fa-calendar-alt" style="color:#6c757d;"></i>
                                                <span style="font-size:13px; font-weight:bold; color:#000;">N/A</span>
                                                <span style="font-size:13px; color:#495057;">--/--/-- --:--</span>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                                                <div style="flex-grow:1; border:1px solid #ced4da; background:#f8f9fa; border-radius:4px; font-size:12px; padding:4px 8px; color:#495057;">
                                                    Projet clôturé (aucun événement historique trouvé)
                                                </div>
                                                <button type="button" id="rcrm-btn-reopen" title="\' . ($btnTitle ?? \'Rouvrir le projet\') . \'" style="margin-left:auto; background:#fff; border:2px solid #28a745; color:#28a745; border-radius:4px; padding:2px 6px; cursor:pointer; font-size:14px; transition:all 0.2s;"><i class="fas \' . ($btnIcon ?? \'fa-undo\') . \'"></i></button>
                                            </div>
                                        </div>
                                    `;
                                    $target.after(widgetHtml);
                                    $("#rcrm-btn-reopen").on("click", function(e) {
                                        e.preventDefault();
                                        var objId = ' . (int)$object->id . ';
                                        var type = "' . dol_escape_js($object->element) . '";
                                        $(this).html("<i class=\'fas fa-spinner fa-spin\'></i>");
                                        $.ajax({
                                            url: "' . dol_buildpath('/custom/reedcrm/ajax/close_record.php', 1) . '",
                                            method: "POST",
                                            data: { action: "reopen", id: objId, type: type, token: "' . newToken() . '" },
                                            dataType: "json",
                                            success: function(res) { if (res.success) window.location.reload(); },
                                            error: function() { alert("Erreur de connexion."); $("#rcrm-btn-reopen").html("<i class=\'fas ' . ($btnIcon ?? 'fa-undo') . '\'></i>"); }
                                        });
                                    });
                                }
                            });
                        </script>';
                    }
                }

                $this->resprints = $contactHtml . $jsMountDataHtml . $assetsHtml . $closureWidgetHtml;
            }
        }
        return 0;
    }
}

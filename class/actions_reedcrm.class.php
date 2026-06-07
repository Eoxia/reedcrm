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
                $projectData = [
                    'total_opp_amount' => 0,
                    'total_opp_weighted_amount' => 0,
                ];
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

        // ReedCRM: on the validated Reception card, the core line view renders only the empty line
        // description and exposes no per-line hook, so the product description never shows. We inject it
        // client-side from a data-island computed here. (Shipment already shows it natively via the order line.)
        if (strpos($parameters['context'], 'receptioncard') !== false) {
            $this->printLineProductDescriptions($object);
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Print the opportunity chain bar once at the top of the standard project Overview tab
     * (projet/element.php). The core hook fires once per referent type in a loop, so a static
     * guard emits the bar only once. Returns 0 (we add output, never replace the default rendering).
     *
     * @param  array        $parameters Hook metadata (context, ...)
     * @param  CommonObject $object     The project currently displayed
     * @return int
     */
    public function printOverviewProfit(array $parameters, CommonObject $object): int
    {
        global $conf, $langs;

        static $done = false;
        if ($done || strpos($parameters['context'], 'projectOverview') === false) {
            return 0;
        }
        if (empty($object->id)) {
            return 0;
        }
        $done = true;

        require_once __DIR__ . '/../lib/reedcrm.lib.php';
        $reedcrmChainDocs = reedcrm_get_pwa_projects_documents([$object->id]);
        $chainBarDocs     = $reedcrmChainDocs[$object->id] ?? [];

        print reedcrm_chain_bar_styles();
        include __DIR__ . '/../core/tpl/frontend/reedcrm_opportunity_chain_bar.tpl.php';

        return 0;
    }

    /**
     * Emit a JSON data-island { productId: htmlDescription } and load the JS that injects each
     * product/service description under its line, on the validated Reception card. Pure-module
     * workaround: that core view renders only the (empty) line description and exposes no
     * per-line hook, so the DOM is augmented client-side.
     *
     * @param  CommonObject $object Reception currently displayed
     * @return void
     */
    private function printLineProductDescriptions(CommonObject $object): void
    {
        if (empty($object->lines) || !is_array($object->lines)) {
            return;
        }

        // Distinct predefined products on the document
        $productIds = [];
        foreach ($object->lines as $line) {
            if (!empty($line->fk_product) && $line->fk_product > 0) {
                $productIds[(int) $line->fk_product] = (int) $line->fk_product;
            }
        }
        if (empty($productIds)) {
            return;
        }

        // Single query (no N+1) to fetch each product description
        $descriptions = [];
        $sql  = 'SELECT rowid, description FROM ' . MAIN_DB_PREFIX . 'product';
        $sql .= ' WHERE rowid IN (' . implode(',', array_keys($productIds)) . ')';
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $desc = trim((string) $obj->description);
                if ($desc !== '') {
                    // Render like the native template so the output matches the other document types
                    $descriptions[(int) $obj->rowid] = dol_htmlentitiesbr($desc);
                }
            }
            $this->db->free($resql);
        }
        if (empty($descriptions)) {
            return;
        }

        // Data only (JSON, escaped so it is safe inside a <script> tag) + targeted JS load
        $json = json_encode($descriptions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        print "\n" . '<script type="application/json" id="reedcrm-linedesc-data">' . $json . '</script>' . "\n";
        print '<script src="' . dol_buildpath('/reedcrm/js/reedcrm_line_description.js', 1) . '"></script>' . "\n";
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
        // Auto-log time when a ticket message is sent and the checkbox is checked
        if (strpos($parameters['context'], 'ticketcard') !== false && $action == 'add_message') {
            if (GETPOSTISSET('reedcrm_log_time') && GETPOSTINT('reedcrm_log_time') == 1) {
                global $user, $db, $conf;

                $ticketId     = $object->id;
                $minutes      = GETPOSTINT('reedcrm_log_minutes');
                $note         = GETPOST('message', 'restricthtml'); // the message body
                // Strip HTML tags for the note
                $note         = strip_tags($note);
                if ($minutes <= 0) {
                    $minutes = getDolGlobalInt('REEDCRM_TICKET_TIME_DEFAULT_MINUTES', 15);
                }

                if ($ticketId > 0 && $object->fk_project > 0) {
                    require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
                    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

                    $prefix      = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps');
                    $suffix_type = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref');

                    $project = new Project($db);
                    $project->fetch($object->fk_project);

                    $suffix_str = '';
                    if ($suffix_type === 'ticket_ref') {
                        $suffix_str = ' ' . $object->ref;
                    } elseif ($suffix_type === 'project_ref') {
                        $suffix_str = ' ' . $project->ref;
                    } elseif ($suffix_type === 'project_label') {
                        $suffix_str = ' ' . $project->title;
                    }
                    $expected_label = trim($prefix . $suffix_str);

                    // Find or create the task
                    $sql    = 'SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'projet_task as t';
                    $sql   .= ' WHERE t.fk_projet = ' . (int)$object->fk_project;
                    $sql   .= " AND t.label = '" . $db->escape($expected_label) . "'";
                    $resql  = $db->query($sql);
                    $task   = new Task($db);
                    if ($resql && ($obj = $db->fetch_object($resql)) && $obj) {
                        $task->fetch($obj->rowid);
                    } else {
                        $task->fk_project  = $object->fk_project;
                        $task->ref         = $object->ref;
                        $task->label       = $expected_label;
                        $task->description = 'Tâche générée automatiquement pour le ticket ' . $object->ref;
                        $task->date_c      = dol_now();
                        $task->date_start  = dol_now();
                        $task->date_end    = dol_now();
                        $task->progress    = 0;
                        $resCreate = $task->create($user);
                        if ($resCreate <= 0) {
                            $task = null;
                        }
                    }

                    if (!empty($task) && $task->id > 0) {
                        $task->timespent_duration = $minutes * 60;
                        $task->timespent_date     = dol_now();
                        $task->timespent_datehour = dol_now();
                        $task->timespent_fk_user  = $user->id;
                        $task->timespent_note     = $note;
                        $task->addTimeSpent($user);

                        // Also create an ActionComm
                        $titleMaxLength      = getDolGlobalInt('REEDCRM_TICKET_TIME_TITLE_MAXLENGTH', 200);
                        $clean_label         = dol_trunc(trim(preg_replace('/\s+/', ' ', $note)), $titleMaxLength);
                        $actioncomm          = new ActionComm($db);
                        $actioncomm->type_code = 'AC_OTH_AUTO';
                        $actioncomm->code    = 'TICKET_TIMESPENT';
                        $actioncomm->socid   = $object->socid;
                        $actioncomm->fk_project = $object->fk_project;
                        $actioncomm->fk_element = $ticketId;
                        $actioncomm->elementtype = 'ticket';
                        $actioncomm->label   = !empty($clean_label) ? $clean_label : 'Temps consigné (' . $minutes . ' min)';
                        $desc = 'Temps : ' . $minutes . ' min';
                        if (!empty($note)) {
                            $desc .= '<br>Commentaire :<br>' . nl2br(dol_escape_htmltag($note));
                        }
                        $actioncomm->note_private = $desc;
                        $actioncomm->datep        = dol_now();
                        $actioncomm->datef        = dol_now();
                        $actioncomm->userownerid  = $user->id;
                        $actioncomm->percentage   = 100;
                        $actioncomm->create($user);
                    }
                }
            }
        }

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
        if (preg_match('/thirdpartycomm|thirdpartycard|projectcard|propalcard/', $parameters['context'])) {
            $pictoPath = dol_buildpath('/reedcrm/img/reedcrm_color.png', 1);
            $pictoMod  = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');

            if (isModEnabled('agenda')) {
                require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

                $actionComm       = new ActionComm($db);
                $isProjectContext = (strpos($parameters['context'], 'projectcard') !== false);
                $isThirdpartyContext = preg_match('/thirdpartycomm|thirdpartycard/', $parameters['context']);

                if (strpos($parameters['context'], 'thirdpartycard') !== false) {
                    $socid = (int) $object->id;
                } else {
                    $socid = (GETPOSTISSET('socid') ? GETPOST('socid') : $object->socid);
                }
                $projectId = $isProjectContext ? (int) $object->id : 0;

                $filter      = ' AND a.id IN (SELECT c.fk_actioncomm FROM '  . MAIN_DB_PREFIX . 'categorie_actioncomm as c WHERE c.fk_categorie = ' . getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG') . ')';
                $actionComms = $actionComm->getActions($socid, ($isProjectContext ? GETPOST('id') : ''), ($isProjectContext ? 'project' : ''), $filter, 'a.datec');
                $actonComsByType = [
                    'call'  => ['picto' => 'headset',      'actioncode' => 'AC_TEL',   'nb' => 0],
                    'email' => ['picto' => 'envelope',     'actioncode' => 'AC_EMAIL', 'nb' => 0],
                    'rdv'   => ['picto' => 'calendar',     'actioncode' => 'AC_RDV',   'nb' => 0],
                    'other' => ['picto' => 'comment-dots', 'actioncode' => 'AC_OTH',   'nb' => 0],
                ];
                if (is_array($actionComms) && !empty($actionComms)) {
                    foreach ($actionComms as $ac) {
                        if ($ac->type_code == 'AC_TEL')        $actonComsByType['call']['nb']++;
                        elseif ($ac->type_code == 'AC_EMAIL')  $actonComsByType['email']['nb']++;
                        elseif ($ac->type_code == 'AC_RDV')    $actonComsByType['rdv']['nb']++;
                        else                                   $actonComsByType['other']['nb']++;
                    }
                }

                $out = '<tr id="reedcrm-relaunch-row-hidden" style="display:none;"><td colspan="2">';

                $out .= '<div class="contact-inline-wrapper reedcrm-header-relaunch-master" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;">';
                $out .= '<img src="' . $pictoPath . '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />';

                $out .= '<div class="reedcrm-plist-relaunch-buttons reedcrm-relaunch-buttons" style="display: inline-flex; align-items: center; gap: 4px;">';
                $relaunchAjaxUrl = dol_buildpath('/custom/reedcrm/ajax/get_relaunches_list.php', 1);

                foreach ($actonComsByType as $actionCommType => $actonComByType) {
                    $out .= '<div id="btn-relaunch-' . $actionCommType . '-' . $object->id . '" class="ui-dialog-open reedcrm-relaunch-button reedcrm-plist-relaunch-btn-' . $actionCommType . '"';
                    $out .= ' data-dialog-id="dialog-relaunch-' . $actionCommType . '-' . $object->id . '"';
                    $out .= ' data-dialog-title="' . $langs->trans($actionCommType) . '"';
                    $out .= ' data-dialog-icon="fas fa-' . $actonComByType['picto'] . '"';
                    $out .= ' data-dialog-align="center"';
                    $out .= ' data-dialog-url="' . dol_escape_htmltag($relaunchAjaxUrl) . '"';
                    $out .= ' data-dialog-footer="none"';
                    $out .= ' data-project-id="' . $projectId . '"';
                    $out .= ' data-action-comm-type="' . $actonComByType['actioncode'] . '">';

                    $out .= '<div class="reedcrm-plist-relaunch-btn-content">';
                    $out .= '<i class="fas fa-' . $actonComByType['picto'] . '"></i>';
                    $out .= '<span class="reedcrm-plist-relaunch-count">' . $actonComByType['nb'] . '</span>';
                    $out .= '</div>';

                    if ($user->hasRight('agenda', 'myactions', 'create')) {
                        if ($isProjectContext) {
                            $cardProUrlFull = DOL_URL_ROOT . '/custom/reedcrm/view/procard.php?from_id=' . $object->id . '&from_type=project&project_id=' . $object->id . '&actioncode=' . $actonComByType['actioncode'];
                        } else {
                            $cardProUrlFull = DOL_URL_ROOT . '/custom/reedcrm/view/procard.php?from_id=' . $socid . '&from_type=societe&actioncode=' . $actonComByType['actioncode'];
                        }
                        $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $projectId . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
                        $out .= '<input type="hidden" class="modal-options" data-modal-to-open="eventproCardModal">';
                        $out .= '</span>';
                    }

                    $out .= '</div>';
                }

                $out .= '</div>'; // End reedcrm-plist-relaunch-buttons
                $out .= '</div>'; // End wrapper block

                // Teleport the block to the header area
                $out .= '<script>
                    $(document).ready(function() {
                        setTimeout(function() {
                            let flexContainer = document.querySelector(".reedcrm-card-header-blocks");
                            let relaunchBlock = document.querySelector(".reedcrm-header-relaunch-master");
                            if (flexContainer && relaunchBlock) {
                                flexContainer.appendChild(relaunchBlock);
                                relaunchBlock.style.marginLeft = "0";
                                relaunchBlock.style.marginBottom = "0";
                            } else {
                                let refBlock = document.querySelector(".refid");
                                if (refBlock && relaunchBlock) {
                                    let wrapper = document.createElement("div");
                                    wrapper.style.clear = "both";
                                    wrapper.style.marginTop = "6px";
                                    wrapper.appendChild(relaunchBlock);
                                    refBlock.insertAdjacentElement("afterend", wrapper);
                                }
                            }
                        }, 50); // slight delay to allow contact_inline.js to build the flex container
                    });
                </script>';

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

                $project->fetch(GETPOSTINT('id'));
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

        if (preg_match('/invoicelist|invoicereclist|thirdpartylist|projectlist|propallist/', $parameters['context'])) {
            $cssPath = dol_buildpath('/saturne/css/saturne.min.css', 1);
            print '<link href="' . $cssPath . '" rel="stylesheet">';
            // Load reedcrm modal CSS and JS for projectlist and propallist
            if (preg_match('/projectlist|propallist/', $parameters['context'])) {
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
        } else if (strpos($parameters['context'], 'projectcard') !== false) {

            if (!empty($object->array_options['options_reedcrm_gravityform'])) {
                ?>
                <script>
                    $('.tabsAction').first().prepend('<a class="butAction" href="<?= $object->array_options['options_reedcrm_gravityform']; ?>" title="" aria-label="" target="_blank"><span class="textbutton"><?=  $langs->trans('GravityFormLink') ?></span></a>')
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
        if (strpos($parameters['context'], 'ticketcard') !== false && $object instanceof Ticket) {
            global $db;
            $defaultMinutes = getDolGlobalInt('REEDCRM_TICKET_TIME_DEFAULT_MINUTES', 15);
            
            $task_id = 0;
            $timeCount = 0;
            $timeEntries = [];
            if (!empty($object->fk_project)) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
                
                $prefix      = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps');
                $suffix_type = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref');
                $project = new Project($db);
                $project->fetch($object->fk_project);
                
                $suffix_str = '';
                if ($suffix_type === 'ticket_ref') {
                    $suffix_str = ' ' . $object->ref;
                } elseif ($suffix_type === 'project_ref') {
                    $suffix_str = ' ' . $project->ref;
                } elseif ($suffix_type === 'project_label') {
                    $suffix_str = ' ' . $project->title;
                }
                $expected_label = trim($prefix . $suffix_str);
                
                $sql    = 'SELECT t.rowid, t.ref, t.duration_effective, t.planned_workload FROM ' . MAIN_DB_PREFIX . 'projet_task as t';
                $sql   .= ' WHERE t.fk_projet = ' . (int)$object->fk_project;
                $sql   .= " AND t.label = '" . $db->escape($expected_label) . "'";
                $resql  = $db->query($sql);
                if ($resql && ($objTask = $db->fetch_object($resql)) && $objTask) {
                    $task_id = $objTask->rowid;
                    $task_ref = $objTask->ref;
                    $task_duration_effective = (float)$objTask->duration_effective;
                    $task_planned_workload = (float)$objTask->planned_workload;
                }
                
                if ($task_id > 0) {
                    $sqlC = "SELECT COUNT(rowid) as nb FROM " . MAIN_DB_PREFIX . "element_time WHERE elementtype = 'task' AND fk_element = " . (int)$task_id;
                    $resC = $db->query($sqlC);
                    if ($resC && ($objC = $db->fetch_object($resC))) {
                        $timeCount = $objC->nb;
                    }
                    
                    $sqlE = "SELECT pt.element_datehour as task_datehour, pt.element_duration as task_duration, pt.note, u.login FROM " . MAIN_DB_PREFIX . "element_time as pt LEFT JOIN " . MAIN_DB_PREFIX . "user as u ON u.rowid = pt.fk_user WHERE pt.elementtype = 'task' AND pt.fk_element = " . (int)$task_id . " ORDER BY pt.element_datehour DESC LIMIT 5";
                    $resE = $db->query($sqlE);
                    if ($resE) {
                        while ($objE = $db->fetch_object($resE)) {
                            $timeEntries[] = $objE;
                        }
                    }
                }
            }
            
            $tooltipHtml = '';
            if ($timeCount > 0) {
                $effectiveTimeStr = convertSecondToTime($task_duration_effective, 'allhourmin');
                $plannedTimeStr = convertSecondToTime($task_planned_workload, 'allhourmin');
                if (empty($effectiveTimeStr)) $effectiveTimeStr = '00:00';
                if (empty($plannedTimeStr)) $plannedTimeStr = '00:00';
                
                $headerTitle = $task_ref . ' - ' . $langs->trans('ReedCRMTimeEntriesLatest', count($timeEntries), $timeCount);
                $headerTitle .= ' <span style=\'float:right\'>' . $effectiveTimeStr . ' / ' . $plannedTimeStr . '</span>';
                
                $tooltipHtml .= '<b>' . $headerTitle . "</b><br><br>";
                foreach ($timeEntries as $te) {
                    $dateTs   = $db->jdate($te->task_datehour);
                    $dateStr  = dol_print_date($dateTs, '%d/%m/%y %H:%M');
                    $userStr  = $te->login;
                    $noteStr  = dol_trunc(strip_tags($te->note), 100);
                    $dureeStr = convertSecondToTime($te->task_duration, 'allhourmin');
                    
                    $tooltipHtml .= $dateStr . " | " . $userStr . " | " . $dureeStr;
                    if (!empty($noteStr)) {
                        $tooltipHtml .= " | " . $noteStr;
                    }
                    $tooltipHtml .= "<br>";
                }
            } else {
                $tooltipHtml = $langs->trans('ReedCRMNoTimeEntries');
            }
            
            $logoSrc = dol_buildpath('/custom/reedcrm/img/reedcrm_color.png', 1);
            $reedLogoHtml = '<img src="' . dol_escape_htmltag($logoSrc) . '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />';
            
            $logoHtml = '<div style="position: relative; margin-right: 8px; padding-right: 8px; border-right: 1px solid #cbd5e0; display: inline-flex; align-items: center;">';
            $logoHtml .= $reedLogoHtml;
            
            // Link to the task timesheet if task_id exists
            if ($task_id > 0) {
                $taskUrl = DOL_URL_ROOT . '/projet/tasks/time.php?id=' . $task_id . '&withproject=1';
                $logoHtml .= '<a href="' . $taskUrl . '">';
            }
            
            $logoHtml .= '<span class="classfortooltip" title="' . dol_escape_htmltag($tooltipHtml, 1, 1, 'br,span') . '" style="display: inline-flex; align-items: center; justify-content: center; background: #edf2f7; color: #2b6cb0; border-radius: 50%; width: 26px; height: 26px; font-size: 0.9em; cursor: pointer;">';
            $logoHtml .= '<i class="fas fa-list"></i>';
            if ($timeCount > 0) {
                $logoHtml .= '<span id="reedcrm-ticket-time-count" style="position: absolute; top: -6px; right: -2px; background: #e53e3e; color: white; border-radius: 10px; font-size: 0.65em; padding: 2px 5px; font-weight: bold; border: 1px solid #fff; line-height: 1; transition: transform 0.2s;">' . $timeCount . '</span>';
            } else {
                $logoHtml .= '<span id="reedcrm-ticket-time-count" style="display: none; position: absolute; top: -6px; right: -2px; background: #e53e3e; color: white; border-radius: 10px; font-size: 0.65em; padding: 2px 5px; font-weight: bold; border: 1px solid #fff; line-height: 1; transition: transform 0.2s;">0</span>';
            }
            $logoHtml .= '</span>';
            
            if ($task_id > 0) {
                $logoHtml .= '</a>';
            }
            $logoHtml .= '</div>';

            $lastTimeHtml = '';
            if (!empty($timeEntries)) {
                $te = $timeEntries[0];
                $dateTs   = $db->jdate($te->task_datehour);
                $dateStr  = dol_print_date($dateTs, '%d/%m/%y %H:%M');
                $userStr  = $te->login;
                $noteStr  = dol_trunc(strip_tags($te->note), 100);
                $dureeStr = convertSecondToTime($te->task_duration, 'allhourmin');
                
                $initial = strtoupper(substr($userStr, 0, 1));
                $colorHash = substr(md5($userStr), 0, 6);
                $userHtml = '<span style="display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; background-color: #'.$colorHash.'; color: white; font-size: 0.7em; font-weight: bold; margin: 0 4px;" title="'.dol_escape_htmltag($userStr).'">'.$initial.'</span>';

                $lineStr = '<div style="display: flex; align-items: center; width: 100%;">';
                $lineStr .= '<span style="color: #a0aec0; margin-right: 4px; white-space: nowrap;">' . dol_escape_htmltag($dateStr) . '</span>';
                $lineStr .= $userHtml;
                $lineStr .= '<span style="margin: 0 4px; white-space: nowrap;">| ' . dol_escape_htmltag($dureeStr) . '</span>';
                if (!empty($noteStr)) {
                    $lineStr .= '<span style="margin: 0 4px; white-space: nowrap;">|</span><span style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex-grow: 1;" title="' . dol_escape_htmltag($noteStr) . '">' . dol_escape_htmltag($noteStr) . '</span>';
                }
                $lineStr .= '</div>';

                $lastTimeHtml = '<div id="reedcrm-ticket-last-time" style="flex-basis: 100%; font-weight: normal; font-size: 0.85em; color: #718096; padding-left: 4px; margin-top: 2px; max-width: 100%; overflow: hidden;">' . $lineStr . '</div>';
            }

            if (!empty($object->fk_project)) {
                  $html = '
                  <div id="reedcrm-ticket-time-block" class="contact-inline-wrapper" style="display:none; flex-direction: column; align-items: flex-start; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568; gap: 4px; max-width: 400px;">
                      <div style="display: flex; align-items: center; gap: 5px; width: 100%;">
                          ' . $logoHtml . '
                          <textarea id="reedcrm-ticket-time-note" placeholder="' . dol_escape_htmltag($langs->trans('Note')) . '" rows="1" style="border: 1px solid #cbd5e0; border-radius: 4px; padding: 2px 6px; font-size: 0.95em; width: 150px; background: #fff; height: 24px; resize: horizontal; overflow: hidden; line-height: 1.5; white-space: nowrap;"></textarea>
                          <input type="number" id="reedcrm-ticket-time-minutes" value="' . $defaultMinutes . '" min="1" style="border: 1px solid #cbd5e0; border-radius: 4px; padding: 2px 6px; font-size: 0.95em; width: 50px; background: #fff;"> Min
                          <button type="button" id="reedcrm-ticket-time-save" style="background: #f8f9fa; border: 1px solid #cbd5e0; color: #4a5568; padding: 0; margin: 0; border-radius: 4px; font-size: 0.9em; height: 24px; width: 24px; min-width: 0; display: inline-flex; align-items: center; justify-content: center; opacity: 0.6; transition: all 0.2s; cursor: pointer;">
                              <i class="fas fa-save"></i>
                          </button>
                      </div>
                      ' . $lastTimeHtml . '
                  </div>
                  <script>
                      jQuery(document).ready(function() {
                          var block = jQuery("#reedcrm-ticket-time-block");
                          block.css("display", "inline-flex");
                          var flexContainer = document.querySelector(".reedcrm-card-header-blocks");
                          if (flexContainer) {
                              flexContainer.appendChild(block[0]);
                          } else {
                              var titreRight = jQuery("div.titre_right").first();
                              if (titreRight.length) {
                                  var divWrap = jQuery("<div></div>").css({"clear": "both", "margin-top": "6px", "float": "right"}).append(block);
                                  titreRight.after(divWrap);
                              } else {
                                  jQuery(".refidno").first().after(block);
                              }
                          }

                        // Change button color when typing
                        var saveBtn = jQuery("#reedcrm-ticket-time-save");
                        jQuery("#reedcrm-ticket-time-note, #reedcrm-ticket-time-minutes").on("input", function() {
                            if (jQuery("#reedcrm-ticket-time-note").val().trim() !== "") {
                                saveBtn.css({"background": "#48bb78", "color": "#fff", "border-color": "#48bb78", "opacity": "1"});
                            } else {
                                saveBtn.css({"background": "#f8f9fa", "color": "#4a5568", "border-color": "#cbd5e0", "opacity": "0.6"});
                            }
                        });

                        saveBtn.on("click", function() {
                            var note = jQuery("#reedcrm-ticket-time-note").val();
                            var minutes = jQuery("#reedcrm-ticket-time-minutes").val();
                            var btn = jQuery(this);

                            btn.prop("disabled", true).html("<i class=\'fas fa-spinner fa-spin\'></i>");

                            jQuery.ajax({
                                url: "' . DOL_URL_ROOT . '/custom/reedcrm/core/ajax/ticket_time.php",
                                method: "POST",
                                data: {
                                    action: "save_time",
                                    ticket_id: ' . ((int)$object->id) . ',
                                    note: note,
                                    minutes: minutes,
                                    token: "' . newToken() . '"
                                },
                                dataType: "json",
                                success: function(response) {
                                    btn.prop("disabled", false).html("<i class=\'fas fa-save\'></i>");
                                    if (response.success) {
                                        btn.css({"box-shadow": "0 0 0 2px #48bb78", "border-color": "#48bb78", "background": "#48bb78", "color": "#fff", "opacity": "1"});
                                        jQuery("#reedcrm-ticket-time-note").val("");
                                        var counter = jQuery("#reedcrm-ticket-time-count");
                                        if (counter.length) {
                                            var currentCount = parseInt(counter.text()) || 0;
                                            counter.text(currentCount + 1).show();
                                            counter.css("transform", "scale(1.3)");
                                            setTimeout(function() { counter.css("transform", "scale(1)"); }, 300);
                                        }
                                        if (response.new_line_html) {
                                            var lastTimeDiv = jQuery("#reedcrm-ticket-last-time");
                                            if (lastTimeDiv.length) {
                                                lastTimeDiv.replaceWith(response.new_line_html);
                                            } else {
                                                jQuery("#reedcrm-ticket-time-block").append(response.new_line_html);
                                            }
                                        }
                                        setTimeout(function(){
                                            btn.css({"box-shadow": "", "border-color": "#cbd5e0", "background": "#f8f9fa", "color": "#4a5568", "opacity": "0.6"});
                                        }, 1500);
                                    } else {
                                        $.jnotify(response.error, "error");
                                    }
                                },
                                error: function() {
                                    btn.prop("disabled", false).html("<i class=\'fas fa-save\'></i>");
                                    $.jnotify("Erreur réseau", "error");
                                }
                            });
                        });
                    });
                </script>
                ';
            } else {
                $html = '
                <div id="reedcrm-ticket-time-block" class="contact-inline-wrapper" style="display:none; align-items: center; background: #fffaf0; border: 1px solid #feebc8; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #c05621; gap: 5px;">
                    ' . $logoHtml . '
                    <span><i class="fas fa-exclamation-triangle"></i> ' . dol_escape_htmltag($langs->trans('PleaseLinkProjectFirst')) . '</span>
                </div>
                <script>
                    jQuery(document).ready(function() {
                        var block = jQuery("#reedcrm-ticket-time-block");
                        block.css("display", "inline-flex");
                        var flexContainer = document.querySelector(".reedcrm-card-header-blocks");
                        if (flexContainer) {
                            flexContainer.appendChild(block[0]);
                        } else {
                            var titreRight = jQuery("div.titre_right").first();
                            if (titreRight.length) {
                                var wrapper = jQuery("<div></div>").css({"clear": "both", "margin-top": "6px", "float": "right"}).append(block);
                                titreRight.after(wrapper);
                            } else {
                                jQuery(".refidno").first().after(block);
                            }
                        }
                    });
                </script>
                ';
            }

            $sqlSev = "SELECT code, label FROM " . MAIN_DB_PREFIX . "c_ticket_severity WHERE active > 0 ORDER BY pos";
            $resSev = $db->query($sqlSev);
            $severities = [];
            if ($resSev) {
                while ($objSev = $db->fetch_object($resSev)) {
                    $severities[] = $objSev;
                }
            }
            $sevOptions = '';
            $currentSevLabel = '<span style="color:#cbd5e0; font-style:italic;">' . dol_escape_htmltag($langs->trans('Severity')) . '</span>';
            foreach ($severities as $sev) {
                $label = ($langs->trans("TicketSeverityShort" . $sev->code) != "TicketSeverityShort" . $sev->code) ? $langs->trans("TicketSeverityShort" . $sev->code) : $sev->label;
                if ($object->severity_code == $sev->code) {
                    $selected = ' selected';
                    $currentSevLabel = dol_escape_htmltag($label);
                } else {
                    $selected = '';
                }
                $sevOptions .= '<option value="' . dol_escape_htmltag($sev->code) . '"' . $selected . '>' . dol_escape_htmltag($label) . '</option>';
            }

            // Fetch users for assign select
            $sqlUsers = "SELECT rowid, firstname, lastname FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 ORDER BY lastname, firstname";
            $resqlUsers = $db->query($sqlUsers);
            $assignUsers = [];
            if ($resqlUsers) {
                while ($uObj = $db->fetch_object($resqlUsers)) {
                    $assignUsers[] = [
                        'id' => (int)$uObj->rowid,
                        'name' => trim($uObj->firstname . ' ' . $uObj->lastname)
                    ];
                }
            }

            $assignUserId = (int)$object->fk_user_assign;
            $assignName = '';
            $assignOptions = '<option value="">' . dol_escape_htmltag($langs->trans('None')) . '</option>';
            foreach ($assignUsers as $u) {
                if ($assignUserId == $u['id']) {
                    $assignName = $u['name'];
                    $selected = ' selected';
                } else {
                    $selected = '';
                }
                $assignOptions .= '<option value="' . $u['id'] . '"' . $selected . '>' . dol_escape_htmltag($u['name']) . '</option>';
            }

            if (empty($assignName)) {
                $assignLabel = '<span style="color:#cbd5e0; font-style:italic;">' . dol_escape_htmltag($langs->trans('AssignedTo')) . '</span>';
            } else {
                $assignLabel = dol_escape_htmltag($assignName);
            }

            $logoSrcSev = dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1);

            $html .= '
            <div id="reedcrm-ticket-severity-block" class="contact-inline-wrapper" style="display:none; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;">
                <img src="' . dol_escape_htmltag($logoSrcSev) . '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />
                <i class="far fa-exclamation-triangle" style="color: #64748b; margin-right: 6px;"></i>
                <a href="#" id="reedcrm-ticket-severity-badge" class="classlink" style="cursor: pointer; transition: color 0.3s; color: #0f172a; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px;" title="' . dol_escape_htmltag($langs->trans('Edit')) . '">' . $currentSevLabel . '</a>
                <div id="reedcrm-ticket-severity-selector-wrap" style="display:none; margin-left:6px;">
                    <select id="reedcrm-ticket-severity-select" class="reedcrm-select2" style="min-width: 120px;">
                        ' . $sevOptions . '
                    </select>
                </div>
            </div>
            
            <div id="reedcrm-ticket-assign-block" class="contact-inline-wrapper" style="display:none; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;">
                <img src="' . dol_escape_htmltag($logoSrcSev) . '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />
                <i class="fas fa-user-tie" style="color: #64748b; margin-right: 6px;"></i>
                <a href="#" id="reedcrm-ticket-assign-badge" class="classlink" style="cursor: pointer; transition: color 0.3s; color: #0f172a; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px;" title="' . dol_escape_htmltag($langs->trans('Edit')) . '">' . $assignLabel . '</a>
                <div id="reedcrm-ticket-assign-selector-wrap" style="display:none; margin-left:6px;">
                    <select id="reedcrm-ticket-assign-select" class="reedcrm-select2" style="min-width: 150px;">
                        ' . $assignOptions . '
                    </select>
                </div>
            </div>
            
            <script>
                jQuery(document).ready(function() {
                    var blockSev = jQuery("#reedcrm-ticket-severity-block");
                    var blockAssign = jQuery("#reedcrm-ticket-assign-block");
                    
                    // Hide native assigned user row if found
                    var trHidden = false;
                    var assignInput = document.getElementById("fk_user_assign");
                    if (assignInput) {
                        var assignTr = assignInput.closest("tr");
                        if (assignTr) { assignTr.style.display = "none"; trHidden = true; }
                    }
                    if (!trHidden) {
                        // Search by label text (Dolibarr translates it)
                        jQuery("td.tdtitle, td.titlefield").filter(function() {
                            return jQuery(this).text().trim().indexOf("' . dol_escape_js($langs->trans('AssignedTo')) . '") === 0;
                        }).closest("tr").hide();
                        
                        // Also try the ticket assigned class if it exists
                        jQuery(".ticket_user_assign, .user_assign").closest("tr").hide();
                    }

                    // Create a flex container to group them together
                    var container = jQuery("<div></div>").css({
                        "display": "flex",
                        "flex-direction": "column",
                        "gap": "6px",
                        "align-items": "flex-end",
                        "margin-top": "6px",
                        "float": "right",
                        "clear": "both"
                    });
                    
                    blockSev.css("display", "inline-flex");
                    blockAssign.css("display", "inline-flex");
                    container.append(blockAssign).append(blockSev);

                    // Teleport to the right side (under the green Assign button)
                    // We look for common Dolibarr right-aligned containers
                    var target = jQuery(".statusref, .statusrefbox").last();
                    if (target.length) {
                        target.after(container);
                        container.css({
                            "float": "right",
                            "clear": "right",
                            "margin-top": "12px",
                            "margin-bottom": "8px"
                        });
                    } else {
                        var titreRight = jQuery("div.titre_right").first();
                        var arearefonsamedir = jQuery("div.arearefonsamedir > div:first-child");
                        
                        if (arearefonsamedir.length) {
                            arearefonsamedir.append(container);
                        } else if (titreRight.length) {
                            titreRight.append(container);
                        } else {
                            // Try arearef banner container
                            var arearef = jQuery("div.arearef").first();
                            if (arearef.length) {
                                arearef.append(container);
                                container.css({
                                    "float": "right",
                                    "clear": "right",
                                    "margin-top": "12px"
                                });
                            } else {
                                // Fallback
                                jQuery(".refidno").first().after(container);
                            }
                        }
                    }

                    // Handlers for Severity
                    var badgeSev = jQuery("#reedcrm-ticket-severity-badge");
                    var wrapSev = jQuery("#reedcrm-ticket-severity-selector-wrap");
                    var selectSev = jQuery("#reedcrm-ticket-severity-select");

                    if (jQuery.fn.select2) {
                        selectSev.select2({ width: "resolve" });
                    }

                    badgeSev.on("click", function(e) {
                        e.preventDefault();
                        badgeSev.hide();
                        wrapSev.show();
                        if (jQuery.fn.select2) {
                            selectSev.select2("open");
                        } else {
                            selectSev.focus();
                        }
                    });

                    selectSev.on("select2:close", function() {
                        wrapSev.hide();
                        badgeSev.show();
                    });

                    selectSev.on("change", function() {
                        var severityCode = selectSev.val();
                        selectSev.prop("disabled", true);
                        wrapSev.css("opacity", "0.5");

                        jQuery.ajax({
                            url: "' . DOL_URL_ROOT . '/custom/reedcrm/core/ajax/ticket_severity.php",
                            method: "POST",
                            data: {
                                action: "save_severity",
                                ticket_id: ' . ((int)$object->id) . ',
                                severity_code: severityCode,
                                token: "' . newToken() . '"
                            },
                            dataType: "json",
                            success: function(response) {
                                selectSev.prop("disabled", false);
                                wrapSev.css("opacity", "1");
                                if (response.success) {
                                    var newText = selectSev.find("option:selected").text();
                                    if(severityCode === "") {
                                        newText = \'<span style="color:#cbd5e0; font-style:italic;">\' + "' . dol_escape_js($langs->trans('Severity')) . '" + \'</span>\';
                                        badgeSev.html(newText);
                                    } else {
                                        badgeSev.text(newText);
                                    }
                                    
                                    wrapSev.hide();
                                    badgeSev.show();
                                    
                                    blockSev.css({"box-shadow": "0 0 0 2px #48bb78", "border-color": "#48bb78"});
                                    setTimeout(function(){
                                        blockSev.css({"box-shadow": "", "border-color": "#e2e8f0"});
                                    }, 1500);
                                } else {
                                    $.jnotify(response.error, "error");
                                    wrapSev.hide();
                                    badgeSev.show();
                                }
                            },
                            error: function() {
                                selectSev.prop("disabled", false);
                                wrapSev.css("opacity", "1");
                                $.jnotify("Erreur réseau", "error");
                                wrapSev.hide();
                                badgeSev.show();
                            }
                        });
                    });
                    
                    // Handlers for Assigned To
                    var badgeAssign = jQuery("#reedcrm-ticket-assign-badge");
                    var wrapAssign = jQuery("#reedcrm-ticket-assign-selector-wrap");
                    var selectAssign = jQuery("#reedcrm-ticket-assign-select");

                    if (jQuery.fn.select2) {
                        selectAssign.select2({ width: "resolve" });
                    }

                    badgeAssign.on("click", function(e) {
                        e.preventDefault();
                        badgeAssign.hide();
                        wrapAssign.show();
                        if (jQuery.fn.select2) {
                            selectAssign.select2("open");
                        } else {
                            selectAssign.focus();
                        }
                    });

                    selectAssign.on("select2:close", function() {
                        wrapAssign.hide();
                        badgeAssign.show();
                    });

                    selectAssign.on("change", function() {
                        var userAssign = selectAssign.val();
                        selectAssign.prop("disabled", true);
                        wrapAssign.css("opacity", "0.5");

                        jQuery.ajax({
                            url: "' . DOL_URL_ROOT . '/custom/reedcrm/core/ajax/ticket_assign.php",
                            method: "POST",
                            data: {
                                action: "save_assign",
                                ticket_id: ' . ((int)$object->id) . ',
                                user_assign: userAssign,
                                token: "' . newToken() . '"
                            },
                            dataType: "json",
                            success: function(response) {
                                selectAssign.prop("disabled", false);
                                wrapAssign.css("opacity", "1");
                                if (response.success) {
                                    var newText = selectAssign.find("option:selected").text();
                                    if(userAssign === "") {
                                        newText = \'<span style="color:#cbd5e0; font-style:italic;">\' + "' . dol_escape_js($langs->trans('AssignedTo')) . '" + \'</span>\';
                                        badgeAssign.html(newText);
                                    } else {
                                        badgeAssign.text(newText);
                                    }
                                    
                                    wrapAssign.hide();
                                    badgeAssign.show();
                                    
                                    blockAssign.css({"box-shadow": "0 0 0 2px #48bb78", "border-color": "#48bb78"});
                                    setTimeout(function(){
                                        blockAssign.css({"box-shadow": "", "border-color": "#e2e8f0"});
                                    }, 1500);
                                } else {
                                    $.jnotify(response.error, "error");
                                    wrapAssign.hide();
                                    badgeAssign.show();
                                }
                            },
                            error: function() {
                                selectAssign.prop("disabled", false);
                                wrapAssign.css("opacity", "1");
                                $.jnotify("Erreur réseau", "error");
                                wrapAssign.hide();
                                badgeAssign.show();
                            }
                        });
                    });
                });
            </script>
            ';


            print $html;
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
                    
                    // Inject intlTelInput into list view
                    if (typeof window.intlTelInput === 'undefined') {
                        var cssId = 'intlTelInputCss';
                        if (!document.getElementById(cssId)) {
                            var head  = document.getElementsByTagName('head')[0];
                            var link  = document.createElement('link');
                            link.id   = cssId;
                            link.rel  = 'stylesheet';
                            link.type = 'text/css';
                            link.href = '<?php echo dol_buildpath('/reedcrm/js/intl-tel-input/css/intlTelInput.css', 1); ?>';
                            link.media = 'all';
                            head.appendChild(link);
                        }
                        
                        var jsId = 'intlTelInputJs';
                        if (!document.getElementById(jsId)) {
                            var script = document.createElement('script');
                            script.id = jsId;
                            script.src = '<?php echo dol_buildpath('/reedcrm/js/intl-tel-input/js/intlTelInput.min.js', 1); ?>';
                            document.head.appendChild(script);
                        }
                    }
                        
                    var reedJsId = 'reedcrmMainJs';
                    if (!document.getElementById(reedJsId) && (typeof window.saturne === 'undefined' || typeof window.saturne.contact_inline === 'undefined')) {
                        var scriptMain = document.createElement('script');
                        scriptMain.id = reedJsId;
                        scriptMain.src = '<?php echo dol_buildpath('/custom/reedcrm/js/reedcrm.min.js', 1); ?>';
                        document.head.appendChild(scriptMain);
                    }
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
                    $objId = (int) ($parameters['obj']->id ?? $parameters['obj']->rowid ?? 0);
                    $socId = (int) ($parameters['obj']->socid ?? $parameters['obj']->fk_soc ?? $parameters['obj']->fk_societe ?? 0);
                    if (!empty($objId)) {
                        $out = '<td class="tdoverflowmax200">';
                        if (isModEnabled('agenda')) {
                            require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
                            $actionComm = new ActionComm($db);

                            $actionComms = $actionComm->getActions($socId, $objId, 'project', $filter, 'a.datec');

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
                                $project->fetch($objId);
                                $project->array_options['options_commrelaunch'] = $nbActionComms;
                                $project->updateExtrafield('commrelaunch');
                            }

                            $modalId = 'eventproCardModal';
                            $cardProUrl = '/custom/reedcrm/view/procard.php?from_id=' . $objId . '&from_type=project&project_id=' . $objId;

                            $out .= '<div class="reedcrm-plist-relaunch-wrapper">';
                            $out .= '<div class="reedcrm-plist-relaunch-buttons reedcrm-relaunch-buttons">';

                            $out .= '<div class="reedcrm-relaunch-button reedcrm-plist-relaunch-btn-call" data-relaunch-type="call" data-relaunches="' . dol_escape_htmltag(json_encode($relaunchesByType['call'])) . '">';
                            $out .= '<div class="reedcrm-plist-relaunch-btn-content' . ($countsByType['call'] == 0 ? ' count-zero' : '') . '">';
                            $out .= '<i class="fas fa-headset"></i>';
                            $out .= '<span class="reedcrm-plist-relaunch-count">' . $countsByType['call'] . '</span>';
                            $out .= '</div>';
                            if ($user->hasRight('agenda', 'myactions', 'create')) {
                                $cardProUrlFull = DOL_URL_ROOT . $cardProUrl . '&actioncode=AC_TEL';
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $objId . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
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
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $objId . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
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
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $objId . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
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
                                $out .= '<span class="fa fa-plus reedcrm-plist-relaunch-add modal-open reedcrm-modal-open" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $objId . '" data-modal-url="' . dol_escape_htmltag($cardProUrlFull) . '">';
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
                        if ($tmpProject->fetch($objId) > 0) {
                            $desc = $tmpProject->description;
                            $out6 .= !empty($desc) ? (dol_textishtml($desc) ? $desc : dol_nl2br(dol_escape_htmltag($desc))) : '';
                        }
                        $out6 .= '</td>';

                        // projectField opp_percent
                        $out3 = '<td class="center"><span data-project_id="'. $objId . '">';
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

                        // We can generate avatar using dolGetFirstLastname tooltip logic or just uncommenting logoHtml if needed
                        $out5 = '<td class="tdoverflowmax300 valignmiddle">';
                        $out5 .= '<div class="reedcrm-plist-coordonnees contact-inline-wrapper" data-project-id="' . $objId . '">';

                        $out5 .= '<div class="reedcrm-plist-coordonnees-avatar" style="width:24px; height:24px; display:inline-flex; align-items:center; justify-content:center; flex-shrink:0;">';
                        $out5 .= '<img src="' . dol_buildpath('/reedcrm/img/reedcrm_color.png', 1) . '" style="width:20px; height:20px; object-fit:contain;" alt="ReedCRM">';
                        $out5 .= '</div>';
                        
                        $out5 .= '<div class="reedcrm-plist-coordonnees-box">';
                        $out5 .= '<div class="reedcrm-plist-coordonnees-name" style="display: flex; align-items: center; border-left: 1px solid #e2e8f0; padding-left: 8px;">';
                        $out5 .= '<i class="fas fa-address-book" style="color:#64748b; margin-right:4px; flex-shrink: 0;"></i>';
                        $out5 .= '<span class="inline-edit-contact" data-field="firstname" data-val="' . dol_escape_htmltag($thirdPartyName2) . '" style="cursor:pointer; border-bottom:1px dashed #cbd5e0; padding-bottom:1px; margin-right:4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . ($thirdPartyName2 ? $thirdPartyName2 : 'Prénom') . '</span>';
                        $out5 .= '<span class="inline-edit-contact" data-field="lastname" data-val="' . dol_escape_htmltag($thirdPartyName) . '" style="cursor:pointer; border-bottom:1px dashed #cbd5e0; padding-bottom:1px; flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . ($thirdPartyName ? $thirdPartyName : 'Nom') . '</span>';
                        $out5 .= '</div>';
                        
                        $out5 .= '<div class="reedcrm-plist-coordonnees-email" style="display: flex; align-items: center; padding-left: 8px;">';
                        $out5 .= '<i class="fas fa-envelope" style="color:#64748b; margin-right:4px; flex-shrink: 0;"></i>';
                        $out5 .= '<span class="inline-edit-contact" data-field="email" data-val="' . dol_escape_htmltag($thirdPartyEmail) . '" style="cursor:pointer; border-bottom:1px dashed #cbd5e0; padding-bottom:1px; flex-grow: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' . ($thirdPartyEmail ? $thirdPartyEmail : 'Email') . '</span>';
                        $out5 .= '</div>';
                        
                        // Hidden field for website to prevent JS errors
                        $out5 .= '<span class="inline-edit-contact" data-field="website" data-val="" style="display:none;"></span>';
                        $out5 .= '</div>';

                        $out5 .= '<div class="reedcrm-plist-coordonnees-phone-wrapper" style="margin-left: auto; text-align: right;">';
                        $out5 .= '<span class="inline-edit-contact" data-field="phone" data-val="' . dol_escape_htmltag($thirdPartyPhone) . '" style="cursor:pointer; border-bottom:1px dashed #cbd5e0; padding-bottom:1px; font-size: 13px; color: #2c3e50; margin-right: 6px;">' . ($thirdPartyPhone ? $thirdPartyPhone : 'Téléphone') . '</span>';
                        if ($thirdPartyPhone) {
                            $out5 .= '<a href="tel:' . dol_escape_htmltag(preg_replace('/[^0-9+]/', '', $thirdPartyPhone)) . '" title="Appeler" style="color: #64748b; text-decoration: none;"><i class="fas fa-phone-alt fa-lg reedcrm-icon-hover" style="transition: color 0.2s;"></i></a>';
                        } else {
                            $out5 .= '<i class="fas fa-phone-alt fa-lg" style="color: #64748b;"></i>';
                        }
                        $out5 .= '</div>';

                        $out5 .= '</div>';
                        $out5 .= '</td>';

                    }
                    $rowId = (int) $objId; ?>
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

            $this->resprints .= $ret;
        }

        $isTargetContext = strpos($parameters['context'], 'projectlist') !== false
            || strpos($parameters['context'], 'propallist') !== false;

        if ($isTargetContext && $user->hasRight('reedcrm', 'call_list', 'write')) {
            $selected = GETPOST('massaction') == 'addToCallList' ? ' selected="selected"' : '';
            $this->resprints .= '<option value="addToCallList"' . $selected . '>' . $langs->trans('AddToCallList') . '</option>';
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

        $isTargetContext = strpos($parameters['context'], 'projectlist') !== false
            || strpos($parameters['context'], 'propallist') !== false;

        if ($isTargetContext && $user->hasRight('reedcrm', 'call_list', 'write') && $massAction == 'addToCallList') {
            require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllist.class.php';

            $callList  = new CallList($this->db);
            $callLists = $callList->fetchAll('ASC', 'label', 0, 0, ['customsql' => 't.status IN (' . CallList::STATUS_DRAFT . ', ' . CallList::STATUS_ACTIVE . ')']);

            $out  = '<div style="padding: 10px 0 20px 0;">';
            $out .= '<fieldset>';
            $out .= '<legend>' . $langs->trans('SelectCallList') . '</legend>';
            $out .= '<table>';
            $out .= '<tr>';
            $out .= '<td><label for="fk_call_list">' . $langs->trans('CallList') . '</label></td>';
            $out .= '<td>';
            $out .= '<select id="fk_call_list" name="fk_call_list" class="flat">';
            if (is_array($callLists) && !empty($callLists)) {
                foreach ($callLists as $cl) {
                    $out .= '<option value="' . $cl->id . '">' . dol_htmlentities($cl->ref . ' — ' . $cl->label) . '</option>';
                }
            } else {
                $out .= '<option value="">' . $langs->trans('NoActiveCallList') . '</option>';
            }
            $out .= '</select>';
            $out .= '</td>';
            $out .= '</tr>';
            $out .= '</table>';
            $referer    = $_SERVER['HTTP_REFERER'] ?? '';
            $parsed     = parse_url($referer);
            $returnUrl  = ($parsed['path'] ?? $_SERVER['PHP_SELF']) . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

            $out .= '<input type="hidden" name="massaction" value="addToCallList" />';
            $out .= '<input type="hidden" name="return_url" value="' . dol_htmlentities($returnUrl) . '" />';
            $out .= '<div style="margin-top: 20px;">';
            $out .= '<button class="button" type="submit" name="massaction_confirm" value="addToCallList">' . $langs->trans('Confirm') . '</button>';
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

        $isTargetContext = strpos($parameters['context'], 'projectlist') !== false
            || strpos($parameters['context'], 'propallist') !== false;

        if ($isTargetContext && $user->hasRight('reedcrm', 'call_list', 'write') && $massActionConfirm == 'addToCallList') {
            require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllist.class.php';
            require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllistline.class.php';

            $fkCallList = GETPOSTINT('fk_call_list');
            $toSelect   = $parameters['toselect'];

            if (empty($toSelect)) {
                $this->errors[] = $langs->trans('ErrorSelectAtLeastOne');
                return -1;
            }

            $callList = new CallList($this->db);
            if ($callList->fetch($fkCallList) <= 0) {
                $this->errors[] = $langs->trans('CallListNotFound');
                return -1;
            }
            if (!in_array($callList->status, [CallList::STATUS_DRAFT, CallList::STATUS_ACTIVE])) {
                $this->errors[] = $langs->trans('CallListCannotAddToArchivedList');
                return -1;
            }

            $isProjectContext = strpos($parameters['context'], 'projectlist') !== false;
            $elementType      = $isProjectContext ? 'project' : 'propal';

            if ($isProjectContext) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                $element = new Project($this->db);
            } else {
                require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
                $element = new Propal($this->db);
            }

            $lineObject = new CallListLine($this->db);
            $countAdded = 0;

            foreach ($toSelect as $selectedId) {
                if ($element->fetch((int) $selectedId) <= 0) {
                    continue;
                }

                $contacts = array_filter(
                    (array) $element->liste_contact(-1, 'external'),
                    static function ($c) { return $c['code'] !== 'PROJECTADDRESS'; }
                );
                if (empty($contacts)) {
                    setEventMessages($langs->trans('CallListSkippedNoContactElement', $element->ref), null, 'errors');
                    continue;
                }

                $firstContact = reset($contacts);
                $fkContact    = (int) $firstContact['id'];

                if ($lineObject->existsByElement($fkCallList, $elementType, (int) $selectedId)) {
                    setEventMessages($langs->trans('CallListLineDuplicateElement', $element->ref), null, 'warnings');
                    continue;
                }

                $newLine               = new CallListLine($this->db);
                $newLine->fk_call_list = $fkCallList;
                $newLine->element_type = $elementType;
                $newLine->element_id   = (int) $selectedId;
                $newLine->fk_contact   = $fkContact;
                $newLine->status       = CallListLine::STATUS_TO_CALL;
                $newLine->create($user);
                $countAdded++;
            }

            if ($countAdded > 0) {
                setEventMessages($langs->trans('CallListAddedCount', $countAdded), null, 'mesgs');
            }

            $returnUrl = GETPOST('return_url');
            if (empty($returnUrl) || strpos($returnUrl, '/') !== 0 || strpos($returnUrl, '//') === 0) {
                $returnUrl = $_SERVER['PHP_SELF'];
            }
            header('Location: ' . $returnUrl);
            exit;
        }

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the saturneExtendGetObjectsMetadata hook : register ReedCRM objects in the
     * generic saturne object registry so they can use saturne_list.php, the agenda, attendants, etc.
     *
     * @param  array $parameters Hook metadatas (objectsMetadata, objectType)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function saturneExtendGetObjectsMetadata(array $parameters): int
    {
        $this->results['call_list'] = [
            'mainmenu'       => 'reedcrm',
            'leftmenu'       => 'call_list',
            'langs'          => 'CallList',
            'langfile'       => 'reedcrm@reedcrm',
            'picto'          => 'fontawesome_fa-phone_fas_#63ACC9',
            'color'          => '#63ACC9',
            'class_name'     => 'CallList',
            'name_field'     => 'ref',
            'post_name'      => 'fk_call_list',
            'link_name'      => 'call_list',
            'tab_type'       => 'call_list',
            'table_element'  => 'reedcrm_call_list',
            'hook_name_card' => 'call_listcard',
            'hook_name_list' => 'call_list_list',
            'create_url'     => 'custom/reedcrm/view/call_list_card.php',
            'list_url'       => 'custom/reedcrm/view/call_list_list.php',
            'defaultsort'    => 't.rowid',
            'defaultorder'   => 'DESC',
            'class_path'     => 'custom/reedcrm/class/calllist.class.php',
            'lib_path'       => 'custom/reedcrm/lib/reedcrm_call_list.lib.php',
        ];

        return 0; // or return 1 to replace standard code
    }

    /**
     * Overloading the saturneListAddCustomFields hook : inject custom fields for propal list
     *
     * @param  array        $parameters Hook metadatas (objectType, excludeFields)
     * @param  CommonObject $object     The object to process
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     */
    public function saturneListAddCustomFields(array $parameters, CommonObject $object): int
    {
        // Project list: merge the individual contact extrafields into a single "Coordonnées" column
        if ($parameters['objectType'] === 'project') {
            global $extrafields;

            // Merge the opportunity fields (status, probability, amount) into one column
            $object->fields['opportunity_details'] = ['label' => 'OpportunityDetails', 'enabled' => 1, 'position' => 75, 'visible' => 1, 'csslist' => 'minwidth150', 'disablesort' => 1];
            foreach (['fk_opp_status', 'opp_percent', 'opp_amount'] as $oppField) {
                if (isset($object->fields[$oppField])) {
                    $object->fields[$oppField]['visible'] = 0; // hidden as standalone columns, still selected + read by the combined renderer
                }
            }

            // Merge the start/end dates into one "Dates" column
            $object->fields['date_details'] = ['label' => 'Dates', 'enabled' => 1, 'position' => 50, 'visible' => 1, 'csslist' => 'nowraponall minwidth150', 'disablesort' => 1];
            foreach (['dateo', 'datee'] as $dateField) {
                if (isset($object->fields[$dateField])) {
                    $object->fields[$dateField]['visible'] = 0; // hidden as standalone columns, still selected + read by the combined renderer
                }
            }

            // Center the status (État) column header + cells
            if (isset($object->fields['fk_statut'])) {
                $object->fields['fk_statut']['csslist'] = 'center';
            }

            // Merge the individual contact extrafields into one "Coordonnées" column
            $object->fields['contact_details'] = ['label' => 'ContactInformations', 'enabled' => 1, 'position' => 161, 'visible' => 1, 'csslist' => 'minwidth200', 'disablesort' => 1];
            foreach (['reedcrm_lastname', 'reedcrm_firstname', 'reedcrm_email', 'projectphone'] as $efName) {
                if (isset($extrafields->attributes[$object->table_element]['list'][$efName])) {
                    $extrafields->attributes[$object->table_element]['list'][$efName] = 0;
                }
            }

            // Declutter the list by hiding redundant (already shown in a merged cell) and technical
            // extrafield columns by default — users can re-enable them via the "Colonnes" picker.
            $hiddenByDefault = [
                'reedcrm_website',      // shown in the Coordonnées cell
                'commrelaunch',         // count shown in the Relances column
                'qc_frequency',         // DigiQuali control frequency, not CRM
                'projecturlgithub1',    // technical
                'easy_url_all_link',    // technical
                'reedcrm_gravityform',  // technical
                'opporigin',            // situational
                'opprefusal',           // situational
                'commtask',             // situational
                'projectaddress',       // rarely needed in the list
            ];
            foreach ($hiddenByDefault as $efName) {
                if (isset($extrafields->attributes[$object->table_element]['list'][$efName])) {
                    $extrafields->attributes[$object->table_element]['list'][$efName] = 0;
                }
            }

            // Commercial relaunch column (call / email / rdv / other counters + quick add)
            $object->fields['relauch_commercial'] = ['label' => 'CommercialsRelaunching', 'enabled' => 1, 'position' => 160, 'visible' => 1, 'csslist' => 'center', 'disablesort' => 1];

            // Virtual columns (not real DB columns)
            $this->results['excludeFields'] = array_merge($parameters['excludeFields'], ['contact_details', 'opportunity_details', 'relauch_commercial', 'date_details']);

            return 1;
        }

        if ($parameters['objectType'] !== 'propal') {
            return 0;
        }

        // Override visible for fields we want shown by default in the list
        $visibleFields = ['ref', 'fk_soc', 'datec', 'datep', 'fin_validite', 'fk_statut', 'total_ht', 'total_ttc', 'fk_user_author', 'fk_projet'];
        foreach ($visibleFields as $fieldKey) {
            if (isset($object->fields[$fieldKey])) {
                $object->fields[$fieldKey]['visible'] = 1;
            }
        }

        $object->fields['relauch_commercial'] = ['label' => 'RelauchCommercial', 'enabled' => 1, 'position' => 150, 'visible' => 1, 'csslist' => 'center', 'disablesort' => 1];
        $object->fields['contact_details']    = ['label' => 'ContactDetails',    'enabled' => 1, 'position' => 151, 'visible' => 1, 'csslist' => 'center', 'disablesort' => 1];
        $object->fields['fk_statut']          = ['type' => 'smallint', 'label' => 'Status', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 1, 'searchmulti' => 1, 'arrayofkeyval' => [0 => 'Draft', 1 => 'Validated', 2 => 'Signed', 3 => 'NotSigned', 4 => 'Billed'], 'csslist' => 'minwidth200', 'disablesort' => 1];
        $object->fields['fk_projet']['label'] = 'Project';

        $this->results['excludeFields'] = array_merge($parameters['excludeFields'], ['relauch_commercial', 'contact_details']);

        return 1;
    }

    /**
     * Overloading the saturneListTopBanner hook : display opportunity KPI cards + view presets above the project list
     * (rendered above the title bar, outside the list header)
     *
     * @param  array $parameters Hook metadatas (context, ...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function saturneListTopBanner(array $parameters): int
    {
        global $conf, $db, $langs, $user;

        // Only on the saturne project list, when the opportunity feature is enabled
        if (strpos($parameters['context'], 'projectlist') === false || strpos($parameters['context'], 'saturnelist') === false) {
            return 0;
        }
        if (!getDolGlobalString('PROJECT_USE_OPPORTUNITIES')) {
            return 0;
        }

        require_once __DIR__ . '/../../saturne/lib/saturne_functions.lib.php';

        // Predefined view presets (one-click filtered views)
        $presetsBar = $this->reedcrmRenderProjectPresets();

        // Snapshot of the current filtered query, exposed by the generic list before sort/pagination
        $baseSql = $GLOBALS['sqlForList'] ?? '';
        if (empty($baseSql)) {
            $this->resprints = $presetsBar;
            return 0;
        }

        // Aggregates computed over the whole filtered set
        $aggregates = saturne_get_list_aggregates($db, $baseSql, [
            'nb'       => 'COUNT(*)',
            'total'    => 'COALESCE(SUM(opp_amount), 0)',
            'weighted' => 'COALESCE(SUM(opp_amount * opp_percent / 100), 0)',
            'avgproba' => 'AVG(NULLIF(opp_percent, 0))',
        ]);
        if ($aggregates === null) {
            $this->resprints = $presetsBar;
            return 0;
        }

        $cards = [
            'nb' => [
                'id'    => 'nb',
                'label' => $langs->trans('ReedCRMKpiNbOpportunities'),
                'value' => (string) ((int) $aggregates->nb),
                'icon'  => 'fas fa-bullseye',
                'color' => 'blue',
            ],
            'total' => [
                'id'    => 'total',
                'label' => $langs->trans('ReedCRMKpiTotalAmount'),
                'value' => price((float) $aggregates->total, 0, $langs, 1, -1, -1, $conf->currency),
                'icon'  => 'fas fa-coins',
                'color' => 'grey',
            ],
            'weighted' => [
                'id'    => 'weighted',
                'label' => $langs->trans('ReedCRMKpiWeightedAmount'),
                'value' => price((float) $aggregates->weighted, 0, $langs, 1, -1, -1, $conf->currency),
                'icon'  => 'fas fa-balance-scale',
                'color' => 'green',
            ],
            'avgproba' => [
                'id'    => 'avgproba',
                'label' => $langs->trans('ReedCRMKpiAvgProbability'),
                'value' => price2num((float) $aggregates->avgproba, 1) . ' %',
                'icon'  => 'fas fa-percent',
                'color' => 'yellow',
            ],
        ];

        // Per-user params (REEDCRM_*) are not yet loaded into $user->conf when this banner hook
        // runs (the per-row hooks fire later, once it is), so load them explicitly here — needed
        // by the KPI layout, the status-display toggle and the density toggle below.
        $user->loadPersonalConf();

        // Apply the per-user saved layout (order + hidden cards), stored in llx_user_param
        $cards = $this->reedcrmApplyKpiLayout($cards);

        // Customize controls (edit-mode toggle + reset)
        $statusDisplay = (isset($user->conf->REEDCRM_STATUS_DISPLAY) && $user->conf->REEDCRM_STATUS_DISPLAY === 'dot') ? 'dot' : 'badge';
        $statusTarget  = $statusDisplay === 'dot' ? 'badge' : 'dot';
        $statusLabel   = $statusDisplay === 'dot' ? $langs->trans('ReedCRMStatusAsBadge') : $langs->trans('ReedCRMStatusAsDot');

        // Row density (per-user): 'compact' (default) packs more rows, 'comfortable' is airier
        $density       = (isset($user->conf->REEDCRM_LIST_DENSITY) && $user->conf->REEDCRM_LIST_DENSITY === 'comfortable') ? 'comfortable' : 'compact';
        $densityTarget = $density === 'compact' ? 'comfortable' : 'compact';
        $densityLabel  = $density === 'compact' ? 'Affichage aéré' : 'Affichage compact';
        $densityIcon   = $density === 'compact' ? 'fa-expand-alt' : 'fa-compress-alt';

        $customizeBar  = '<div class="reedcrm-kpi-customize-bar" data-density="' . $density . '">';
        $customizeBar .= '<button type="button" class="reedcrm-kpi-customize-toggle" title="' . dol_escape_htmltag($langs->trans('ReedCRMKpiCustomize')) . '"><i class="fas fa-sliders-h"></i> ' . dol_escape_htmltag($langs->trans('ReedCRMKpiCustomize')) . '</button>';
        $customizeBar .= '<button type="button" class="reedcrm-status-display-toggle' . ($statusDisplay === 'dot' ? ' active' : '') . '" data-mode="' . $statusTarget . '" title="' . dol_escape_htmltag($statusLabel) . '"><i class="fas fa-circle"></i> ' . dol_escape_htmltag($statusLabel) . '</button>';
        $customizeBar .= '<button type="button" class="reedcrm-list-density-toggle' . ($density === 'compact' ? ' active' : '') . '" data-mode="' . $densityTarget . '" title="' . dol_escape_htmltag($densityLabel) . '"><i class="fas ' . $densityIcon . '"></i> ' . dol_escape_htmltag($densityLabel) . '</button>';
        $customizeBar .= '<button type="button" class="reedcrm-kpi-customize-reset" title="' . dol_escape_htmltag($langs->trans('ReedCRMKpiReset')) . '"><i class="fas fa-undo"></i> ' . dol_escape_htmltag($langs->trans('ReedCRMKpiReset')) . '</button>';
        $customizeBar .= '</div>';

        $this->resprints = $presetsBar . $customizeBar . saturne_render_kpi_cards(array_values($cards));

        return 0;
    }

    /**
     * Apply the per-user KPI banner layout (order + hidden cards) read from llx_user_param.
     *
     * @param  array<string,array> $cards KPI cards keyed by id
     * @return array<string,array>        Reordered cards with hidden ones flagged
     */
    protected function reedcrmApplyKpiLayout(array $cards): array
    {
        global $user;

        $raw = isset($user->conf->REEDCRM_KPI_LAYOUT) ? $user->conf->REEDCRM_KPI_LAYOUT : '';
        if (empty($raw)) {
            return $cards;
        }
        $layout = json_decode($raw, true);
        if (!is_array($layout)) {
            return $cards;
        }

        if (!empty($layout['hidden']) && is_array($layout['hidden'])) {
            foreach ($layout['hidden'] as $id) {
                if (isset($cards[$id])) {
                    $cards[$id]['hidden'] = true;
                }
            }
        }

        if (!empty($layout['order']) && is_array($layout['order'])) {
            $ordered = [];
            foreach ($layout['order'] as $id) {
                if (isset($cards[$id])) {
                    $ordered[$id] = $cards[$id];
                }
            }
            // Keep any card not present in the saved order (e.g. newly added) at the end
            foreach ($cards as $id => $card) {
                if (!isset($ordered[$id])) {
                    $ordered[$id] = $card;
                }
            }
            $cards = $ordered;
        }

        return $cards;
    }

    /**
     * Build the predefined view presets bar for the opportunity project list.
     *
     * @return string HTML presets bar (uses the generic saturne_render_list_presets renderer)
     */
    protected function reedcrmRenderProjectPresets(): string
    {
        global $langs;

        $activePreset = GETPOST('search_preset', 'aZ09');
        $activeView   = GETPOST('reedcrm_view', 'alphanohtml');
        // Keep the opportunity scope on every preset link
        $baseUrl      = $_SERVER['PHP_SELF'] . '?object_type=project&search_usage_opportunity=1';

        $presetDefs = [
            'mine'       => ['label' => $langs->trans('ReedCRMPresetMine'),       'icon' => 'fas fa-user'],
            'hot'        => ['label' => $langs->trans('ReedCRMPresetHot'),        'icon' => 'fas fa-fire'],
            'open'       => ['label' => $langs->trans('ReedCRMPresetOpen'),       'icon' => 'fas fa-folder-open'],
            'torelaunch' => ['label' => $langs->trans('ReedCRMPresetToRelaunch'), 'icon' => 'fas fa-bell'],
        ];

        $presets = [[
            'label'  => $langs->trans('All'),
            'icon'   => 'fas fa-list',
            'url'    => $baseUrl,
            'active' => empty($activePreset) && empty($activeView),
        ]];
        foreach ($presetDefs as $key => $def) {
            $presets[] = [
                'label'  => $def['label'],
                'icon'   => $def['icon'],
                'url'    => $baseUrl . '&search_preset=' . $key,
                'active' => ($activePreset === $key),
            ];
        }

        // Per-user saved views (stored in llx_user_param)
        global $user;
        foreach (get_object_vars($user->conf) as $paramKey => $paramVal) {
            if (strpos($paramKey, 'REEDCRM_VIEW_PROJECT_') !== 0) {
                continue;
            }
            $decoded = json_decode($paramVal, true);
            if (empty($decoded['label'])) {
                continue;
            }
            $viewQuery  = !empty($decoded['query']) ? $decoded['query'] : '';
            $presets[]  = [
                'label'       => $decoded['label'],
                'icon'        => 'fas fa-star',
                'url'         => $baseUrl . ($viewQuery !== '' ? '&' . $viewQuery : '') . '&reedcrm_view=' . urlencode($paramKey),
                'active'      => ($activeView === $paramKey),
                'removeKey'   => $paramKey,
                'removeTitle' => $langs->trans('Delete'),
            ];
        }

        // "Save current view" button (raw caller-built chip)
        $saveLabel = dol_escape_htmltag($langs->trans('ReedCRMSaveView'));
        $presets[] = ['raw' => '<button type="button" class="saturne-list-preset reedcrm-save-view" title="' . $saveLabel . '"><i class="fas fa-save"></i> ' . $saveLabel . '</button>'];

        return saturne_render_list_presets($presets);
    }

    /**
     * Overloading the printFieldListSearchParam hook : keep the active preset across sort/pagination links.
     *
     * @param  array $parameters Hook metadatas (context, ...)
     * @return int               0 < on error, 0 on success, 1 to replace standard code
     */
    public function printFieldListSearchParam(array $parameters): int
    {
        if (strpos($parameters['context'], 'projectlist') !== false) {
            $param  = '';
            $preset = GETPOST('search_preset', 'aZ09');
            if (!empty($preset)) {
                $param .= '&search_preset=' . urlencode($preset);
            }
            $view = GETPOST('reedcrm_view', 'alphanohtml');
            if (!empty($view)) {
                $param .= '&reedcrm_view=' . urlencode($view);
            }
            $this->resprints = $param;
        }

        return 0;
    }

    /**
     * Overloading the printFieldListWhere hook : add WHERE conditions for propal list
     *
     * @param  array        $parameters Hook metadatas (context, search, ...)
     * @param  CommonObject $object     The object to process
     * @param  string       $action     Current action
     * @return int                      0 < on error, 0 on success, 1 to replace standard code
     */
    public function printFieldListWhere(array $parameters, ?CommonObject $object, string $action): int
    {
        if (strpos($parameters['context'], 'propallist') !== false && strpos($parameters['context'], 'saturnelist') !== false) {
            $this->resprints = ' AND t.fk_statut >= 0';
        }

        if (strpos($parameters['context'], 'projectlist') !== false && strpos($parameters['context'], 'saturnelist') !== false) {
            global $db, $user;

            $preset    = GETPOST('search_preset', 'aZ09');
            $notClosed = ' (t.fk_opp_status IS NULL OR t.fk_opp_status NOT IN (SELECT rowid FROM ' . MAIN_DB_PREFIX . "c_lead_status WHERE code IN ('WON', 'LOST')))";
            $sql       = '';

            switch ($preset) {
                case 'mine':
                    $sql = ' AND EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_contact ec'
                         . ' INNER JOIN ' . MAIN_DB_PREFIX . 'c_type_contact tc ON tc.rowid = ec.fk_c_type_contact'
                         . " AND tc.element = 'project' AND tc.source = 'internal'"
                         . ' WHERE ec.element_id = t.rowid AND ec.fk_socpeople = ' . (int) $user->id . ')';
                    break;
                case 'hot':
                    $sql = ' AND t.opp_percent >= 60';
                    break;
                case 'open':
                    $sql = ' AND' . $notClosed;
                    break;
                case 'torelaunch':
                    $relaunchTag = getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG');
                    $sql  = ' AND' . $notClosed;
                    $sql .= ' AND NOT EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'actioncomm a'
                          . ' WHERE a.fk_project = t.rowid AND a.datep >= ' . "'" . $db->idate(dol_now() - 30 * 24 * 3600) . "'";
                    if ($relaunchTag > 0) {
                        $sql .= ' AND a.id IN (SELECT c.fk_actioncomm FROM ' . MAIN_DB_PREFIX . 'categorie_actioncomm c WHERE c.fk_categorie = ' . $relaunchTag . ')';
                    }
                    $sql .= ')';
                    break;
            }

            $this->resprints = $sql;
        }

        return 0;
    }

    public function saturnePrintFieldListLoopObject(array $parameters, CommonObject $object): int
    {
        if (preg_match('/propallist|projectlist/', $parameters['context'])) {
            require_once __DIR__ . '/../lib/reedcrm_fields.lib.php';

            $fieldMap = [
                'ref'                 => 'reedcrm_field_ref_with_actions',
                'opportunity_details' => 'reedcrm_field_opportunity_details',
                'date_details'        => 'reedcrm_field_date_details',
                'relauch_commercial'  => 'reedcrm_field_relaunch_commercial',
                'contact_details'     => 'reedcrm_field_contact_details',
                'photo'              => 'reedcrm_field_photo',
                'fk_opp_status'      => 'reedcrm_field_opp_status',
                'fk_statut'          => 'reedcrm_field_status_badge',
                'opp_percent'        => 'reedcrm_field_opp_percent',
            ];

            $key = $parameters['key'];

            if (isset($fieldMap[$key])) {
                $fn                = $fieldMap[$key];
                $this->results     = [$key => $fn($parameters, $object)];
                return 1;
            }
        }

        // Call list: render the ref as a clickable link to the card (generic loop prints it as plain text)
        if (strpos($parameters['context'], 'call_list_list') !== false && $parameters['key'] === 'ref') {
            $this->results = ['ref' => $object->getNomUrl(1)];
            return 1;
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
            // App configuration
            $out = load_fiche_titre($langs->trans('Config'), '', '');

            $out .= '<table class="noborder centpercent">';
            $out .= '<tr class="liste_titre">';
            $out .= '<td>' . $langs->trans('Parameters') . '</td>';
            $out .= '<td>' . $langs->trans('Description') . '</td>';
            $out .= '<td class="center">' . $langs->trans('Status') . '</td>';
            $out .= '</tr>';

            // App close project when probability zero
            $out .= '<tr class="oddeven"><td>';
            $out .= $langs->trans('AppCloseProjectOpportunityZero');
            $out .= '</td><td>';
            $out .= $langs->trans('AppCloseProjectOpportunityZeroDescription');
            $out .= '</td><td class="center">';
            $out .= ajax_constantonoff('REEDCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO');
            $out .= '</td></tr>';

            // Create ActionComm on call list call button
            $out .= '<tr class="oddeven"><td>';
            $out .= $langs->transnoentities('CallListCreateActioncomm');
            $out .= '</td><td>';
            $out .= $langs->transnoentities('CallListCreateActioncommDesc');
            $out .= '</td><td class="center">';
            $out .= ajax_constantonoff('REEDCRM_CALL_LIST_CREATE_ACTIONCOMM');
            $out .= '</td></tr>';

            $out .= '</table>';

            $this->resprints = $out;
        }

        return 0; // or return 1 to replace standard code
    }

    public function tabContentCreateThirdparty($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        if (strpos($parameters['context'], 'thirdpartycard') !== false && $action == 'create') {

            ?>

            <script>

                $(window).on('load', function() {

                    function updateLink() {
                        let $td = $('input[name="name"]').closest('td');

                        let name = $('input[name="name"]').val();
                        let town = $('input[name="town"]').val();

                        let query = name;

                        if (town && town.trim() !== '') {
                            query += ' ' + town;
                        }

                        query += ' SIRET';

                        let searchUrl = "https://www.google.com/search?q=" + encodeURIComponent(query);

                        let $link = $td.find('a.test');

                        if ($link.length === 0) {
                            $link = $('<a>', {
                                class: 'test',
                                target: '_blank',
                                html: '<i class="fas fa-external-link-alt"></i>'
                            }).appendTo($td);
                        }

                        $link.attr('href', searchUrl);
                    }

                    $('input[name="name"], input[name="town"]').on('input', updateLink);

                });
            </script>

            <?php

        }
    }

    /**
     * Overloading the formObjectOptions function : replacing the parent's function with the one below
     *
     * @param  array     $parameters Hook metadata (context, etc...)
     * @return int                   0 < on error, 0 on success, 1 to replace standard code
     * @throws Exception
     */
    public function formObjectOptions(array $parameters, $object, $action): int
    {
        global $extrafields, $langs, $conf;

        if (strpos($parameters['context'], 'projectcard') !== false && $object instanceof Project) {
            $picto            = img_picto('', 'reedcrm_color@reedcrm', 'class="pictoModule"');
            $extraFieldsNames = ['opporigin'];
            foreach ($extraFieldsNames as $extraFieldsName) {
                $extrafields->attributes['projet']['label'][$extraFieldsName] = $picto . $langs->transnoentities($extrafields->attributes['projet']['label'][$extraFieldsName]);
            }
        }

        // Add time-logging checkbox below the message form on ticket card
        if (strpos($parameters['context'], 'ticketcard') !== false && in_array($action, ['presend', 'presend_addmessage', 'add_message'])) {
            $defaultMinutes = getDolGlobalInt('REEDCRM_TICKET_TIME_DEFAULT_MINUTES', 15);
            $hasProject     = !empty($object->fk_project) && $object->fk_project > 0;

            if ($hasProject) {
                // Construction du logo propre à partir du helper Dolibarr
                $logoHtml = img_picto('', 'reedcrm_color@reedcrm', 'class="pictoModule" style="width: 22px; height: auto;"');
                
                print '<div id="reedcrm-ticket-time-inline" style="display: none; align-items: center; gap: 8px; font-size: 0.95em; color: #4a5568; margin-right: 15px;">';
                print $logoHtml;
                print '<input type="checkbox" name="reedcrm_log_time" id="reedcrm_log_time" value="1" checked style="cursor: pointer; margin:0;">';
                print '<input type="number" name="reedcrm_log_minutes" id="reedcrm_log_minutes" value="' . $defaultMinutes . '" min="1" style="width: 50px; border: 1px solid #cbd5e0; border-radius: 4px; padding: 2px 6px; background: #fff; text-align: center;"> Min';
                print '</div>';

                print '<script>';
                print 'jQuery(document).ready(function() {';
                print '    var timeBlock = jQuery("#reedcrm-ticket-time-inline");';
                // Cherche le bouton submit du formmail (id addmessage ou name btn_add_message)
                print '    var btn = jQuery("#addmessage");';
                print '    if (btn.length === 0) btn = jQuery("input[name=\'btn_add_message\'], button[name=\'btn_add_message\']").first();';
                print '    if (btn.length > 0) {';
                print '        btn.before(timeBlock);'; // Place juste avant le bouton
                print '        timeBlock.css("display", "inline-flex");'; // Affiche une fois placé
                print '    }';
                print '});';
                print '</script>';
            }
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

                // Setup the hidden full ThirdParty combobox that JS will grab
                require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
                if (!isset($form) || !is_object($form)) {
                    $form = new Form($db);
                }
                
                $jsMountDataHtml .= '<div id="reedcrm-hidden-company-selector" style="display:none; width: 100%;">';
                // params: ($selected, $htmlname, $filter, $showempty, $showtype, $forcecombo, $events, $limit, $morecss, $moreparam, $selected_input_value, $hidelabel)
                $jsMountDataHtml .= $form->select_company($object->socid, 'reedcrm_inline_socid', '', 'Rechercher un tiers...', 1, 0, array(), 0, 'minwidth100', '', '', 1);
                $jsMountDataHtml .= '</div>';

                // Ensure our custom reedcrm assets are injected so the UI logic works
                $cssPath = dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1);
                $jsPath  = dol_buildpath('/custom/reedcrm/js/reedcrm.min.js', 1);
                $assetsHtml = '<link href="' . $cssPath . '" rel="stylesheet">';
                $assetsHtml .= '<script src="' . $jsPath . '?v=' . time() . '"></script>';
                
                // Add intl-tel-input dependencies and mandatory style fixes
                $itiCssPath = dol_buildpath('/reedcrm/js/intl-tel-input/css/intlTelInput.css', 1);
                $itiJsPath  = dol_buildpath('/reedcrm/js/intl-tel-input/js/intlTelInput.min.js', 1);
                $assetsHtml .= '<link href="' . $itiCssPath . '" rel="stylesheet">';
                $assetsHtml .= '<style> @media (max-width: 768px) { .reedcrm-header-contact-master, .reedcrm-header-origin-master, .reedcrm-header-title-wrapper { flex-wrap: wrap !important; height: auto !important; padding-bottom: 6px !important; margin-right: 0 !important; } .rcrm-inline-title-container { max-width: 100vw !important; flex-wrap: wrap !important; } .rcrm-inline-title-container input { width: 100% !important; margin-bottom: 5px !important; } } .iti { width: 100%; display: block; } .iti input[type="tel"] { padding-left: 52px !important; } input.input-invalid-material { border-color: #e53935 !important; border-bottom: 2px solid #e53935 !important; color: #e53935 !important; } </style>';
                $assetsHtml .= '<script src="' . $itiJsPath . '"></script>';

                // Origin (Provenance) Inline Edit UI implementation
                $oppOriginVal = isset($object->array_options['options_opporigin']) ? $object->array_options['options_opporigin'] : '';
                
                require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
                $extrafieldsObj = new ExtraFields($db);
                $extrafieldsObj->fetch_name_optionals_label($object->table_element);
                
                // Natively output the text label of the selected origin and strip native HTML wrappers that trigger native Dolibarr conflicts
                $rawOutput = $extrafieldsObj->showOutputField('opporigin', $oppOriginVal, '', $object->table_element);
                $cleanLabelText = trim(strip_tags($rawOutput));
                
                if (empty($cleanLabelText)) {
                    $oppOriginLabel = '<span style="color:#cbd5e0; font-style:italic;">' . dol_escape_htmltag($langs->transnoentities('OpportunityOrigin')) . '</span>';
                } else {
                    $oppOriginLabel = $cleanLabelText;
                }
                
                // Mount the `<select>` markup locally to guarantee DOM presence
                $hiddenOriginSelect = $extrafieldsObj->showInputField('opporigin', $oppOriginVal, 'id="reedcrm_inline_opporigin"', '', '', '', $object, $object->table_element, 0);
                if (empty($hiddenOriginSelect) || strpos($hiddenOriginSelect, '<select') === false) {
                    // Fallback if dolibarr fails to generate the tag
                    $hiddenOriginSelect = '<select id="reedcrm_inline_opporigin" name="options_opporigin" class="flat"><option value="">' . dol_escape_htmltag($langs->trans('None')) . '</option></select>';
                }

                // Build exactly like contactHtml wrapper
                $originHtml = '<div class="contact-inline-wrapper reedcrm-header-origin-master" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;">';
                $originHtml .= '<img src="' . dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1) . '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />';
                $originHtml .= '<i class="fas fa-bullseye" style="color: #64748b; margin-right: 6px;"></i>';
                $originHtml .= '<a href="#" class="classlink inline-edit-origin-badge" style="cursor: pointer; transition: color 0.3s; color: #0f172a; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px;" title="' . dol_escape_htmltag($langs->trans('Edit')) . '">' . $oppOriginLabel . '</a>';
                $originHtml .= '<div class="reedcrm-hidden-origin-selector-wrap" style="display:none; margin-left:6px;">' . $hiddenOriginSelect . '</div>';
                $originHtml .= '</div>';

                // Fetch linked SALESREPINTERNAL user (Commercial affecté au projet)
                $salesrepUserId = 0;
                $salesrepName = '';
                $sqlSalesRep = "SELECT u.rowid, u.firstname, u.lastname
                                FROM " . MAIN_DB_PREFIX . "element_contact ec
                                JOIN " . MAIN_DB_PREFIX . "c_type_contact ctc ON ctc.rowid = ec.fk_c_type_contact
                                JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = ec.fk_socpeople
                                WHERE ec.element_id = " . (int)$object->id . "
                                  AND ctc.element = 'project'
                                  AND ctc.code = 'SALESREPINTERNAL'
                                  AND ctc.source = 'internal'
                                LIMIT 1";
                $resqlSalesRep = $db->query($sqlSalesRep);
                if ($resqlSalesRep && $db->num_rows($resqlSalesRep) > 0) {
                    $objSalesRep = $db->fetch_object($resqlSalesRep);
                    $salesrepUserId = (int)$objSalesRep->rowid;
                    $salesrepName = trim($objSalesRep->firstname . ' ' . $objSalesRep->lastname);
                }
                
                if (empty($salesrepName)) {
                    $salesrepLabel = '<span style="color:#cbd5e0; font-style:italic;">Commercial</span>';
                } else {
                    $salesrepLabel = dol_escape_htmltag($salesrepName);
                }

                // Query list of active users to build select HTML options
                $salesrepUsers = [];
                $sqlUsers = "SELECT rowid, firstname, lastname FROM " . MAIN_DB_PREFIX . "user WHERE statut = 1 ORDER BY lastname, firstname";
                $resqlUsers = $db->query($sqlUsers);
                if ($resqlUsers) {
                    while ($uObj = $db->fetch_object($resqlUsers)) {
                        $salesrepUsers[] = [
                            'id' => (int)$uObj->rowid,
                            'name' => trim($uObj->firstname . ' ' . $uObj->lastname)
                        ];
                    }
                }

                $hiddenSalesRepSelect = '<select id="reedcrm_inline_salesrep" name="salesrepinternal" class="flat" style="width: 180px;">';
                $hiddenSalesRepSelect .= '<option value="">' . dol_escape_htmltag($langs->trans('None')) . '</option>';
                foreach ($salesrepUsers as $u) {
                    $selected = ($u['id'] == $salesrepUserId) ? ' selected' : '';
                    $hiddenSalesRepSelect .= '<option value="' . $u['id'] . '"' . $selected . '>' . dol_escape_htmltag($u['name']) . '</option>';
                }
                $hiddenSalesRepSelect .= '</select>';

                // Build Salesrep HTML badge
                $salesrepHtml = '<div class="contact-inline-wrapper reedcrm-header-salesrep-master" style="display: inline-flex; align-items: center; background: #f8fbff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 4px 8px 4px 6px; vertical-align: middle; font-weight: 500; font-size: 0.9em; margin-bottom: 2px; color: #4a5568;">';
                $salesrepHtml .= '<img src="' . dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1) . '" style="height: 18px; width: 18px; object-fit: contain; margin-right: 8px; border-right: 1px solid #cbd5e0; padding-right: 8px;" alt="ReedCRM" />';
                $salesrepHtml .= '<i class="fas fa-user-tie" style="color: #64748b; margin-right: 6px;"></i>';
                $salesrepHtml .= '<a href="#" class="classlink inline-edit-salesrep-badge" style="cursor: pointer; transition: color 0.3s; color: #0f172a; border-bottom: 1px dashed #cbd5e0; line-height: 1; padding-bottom: 1px;" title="' . dol_escape_htmltag($langs->trans('Edit')) . '">' . $salesrepLabel . '</a>';
                $salesrepHtml .= '<div class="reedcrm-hidden-salesrep-selector-wrap" style="display:none; margin-left:6px;">' . $hiddenSalesRepSelect . '</div>';
                $salesrepHtml .= '</div>';

                $assetsHtml .= '<script>
                    (function() {
                        var originHtmlStr = ' . json_encode($originHtml) . ';
                        var salesrepHtmlStr = ' . json_encode($salesrepHtml) . ';
                        
                        function processOriginField() {
                            // 1. Hide the original row
                            var tr = null;
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
                                tr.style.display = "none";
                            }
                            
                            // 2. Append the new UI teleport block to the header flex container
                            setTimeout(function() {
                                var flexContainer = document.querySelector(".reedcrm-card-header-blocks") || document.querySelector("div.arearefonsamedir > div:first-child");
                                if (flexContainer) {
                                    var tempWrap = document.createElement("div");
                                    tempWrap.innerHTML = originHtmlStr;
                                    var node = tempWrap.firstElementChild;
                                    
                                    var companyWrapper = flexContainer.querySelector(".reedcrm-header-company-wrapper");
                                    if (companyWrapper) {
                                        // Wrap company and origin in a horizontal flex row to display side-by-side
                                        if (companyWrapper.parentElement && !companyWrapper.parentElement.classList.contains("rcrm-co-org-row")) {
                                            var row = document.createElement("div");
                                            row.className = "rcrm-co-org-row";
                                            row.style.display = "flex";
                                            row.style.gap = "8px";
                                            row.style.alignItems = "center";
                                            row.style.flexWrap = "wrap";
                                            companyWrapper.parentNode.insertBefore(row, companyWrapper);
                                            row.appendChild(companyWrapper);
                                        }
                                        companyWrapper.parentElement.appendChild(node);
                                        
                                        // Teleport salesrep node
                                        var tempWrapSales = document.createElement("div");
                                        tempWrapSales.innerHTML = salesrepHtmlStr;
                                        var nodeSales = tempWrapSales.firstElementChild;
                                        companyWrapper.parentElement.appendChild(nodeSales);
                                    } else {
                                        flexContainer.appendChild(node);
                                        
                                        var tempWrapSales = document.createElement("div");
                                        tempWrapSales.innerHTML = salesrepHtmlStr;
                                        var nodeSales = tempWrapSales.firstElementChild;
                                        flexContainer.appendChild(nodeSales);
                                    }
                                }
                            }, 100);
                        }
                        
                        if (document.readyState === "loading") {
                            document.addEventListener("DOMContentLoaded", processOriginField);
                        } else {
                            processOriginField();
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
                            var $statusRef = $(".statusref").first();
                            var $fallback = $(".arearef, .titre").first();
                            
                            if (($statusRef.length > 0 || $fallback.length > 0) && !$("#reedcrm-closure-widget").length) {
                                var widgetHtml = `
                                    <div id="reedcrm-closure-widget" style="position:absolute; top:calc(100% + 8px); right:0; z-index:50; width:max-content; display:block; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:6px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
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
                                
                                if ($statusRef.length > 0) {
                                    if ($statusRef.css("position") === "static") $statusRef.css("position", "relative");
                                    $statusRef.css("overflow", "visible");
                                    $statusRef.append(widgetHtml);
                                } else {
                                    if ($fallback.css("position") === "static") $fallback.css("position", "relative");
                                    $fallback.css("overflow", "visible");
                                    $fallback.append(widgetHtml);
                                }

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
                        
                        $btnTitleSafe = dol_escape_js($btnTitle);
                        $btnIconSafe  = dol_escape_js($btnIcon);
                        $objElementSafe = dol_escape_js($object->element);
                        $objIdSecure = (int)$object->id;
                        $closeAjaxUrl = dol_buildpath('/custom/reedcrm/ajax/close_record.php', 1);
                        $imgLogo = dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1);
                        $newTokenStr = newToken();
                        $labelSafe = htmlspecialchars($rawLabel, ENT_QUOTES);
                        $labelTruncSafe = htmlspecialchars($truncatedLabel, ENT_QUOTES);
                        
                        $closureWidgetHtml = <<<EOT
                        <script>
                            $(document).ready(function() {
                                var \$statusRef = $(".statusref").first();
                                var \$fallback = $(".arearef, .titre").first();
                                if ((\$statusRef.length > 0 || \$fallback.length > 0) && !$("#reedcrm-closure-widget").length) {
                                    var widgetHtml = `
                                        <div id="reedcrm-closure-widget" style="position:absolute; top:calc(100% + 8px); right:0; z-index:50; width:max-content; display:block; background:#fff; border:1px solid #ced4da; border-radius:6px; padding:6px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="{$imgLogo}" style="height:24px; width:auto;" alt="ReedCRM" />
                                                <i class="far fa-calendar-alt" style="color:#6c757d;"></i>
                                                <span style="font-size:13px; font-weight:normal; color:#000;">{$eventNomUrlSafe}</span>
                                                <span style="font-size:13px; color:#495057;">{$dateFormatted}</span>
                                                <div style="margin-left:4px; height:24px; display:inline-flex; align-items:center;">{$avatarHtmlSafe}</div>
                                                <div style="margin-left:8px; display:flex; gap:8px;">
                                                    <span style="{$lostBorder} border-radius:4px; padding:2px; font-size:20px; line-height:1; display:inline-block;" title="Perdu">😭</span>
                                                    <span style="{$wonBorder} border-radius:4px; padding:2px; font-size:20px; line-height:1; display:inline-block;" title="Gagné">🤩</span>
                                                </div>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                                                <div style="flex-grow:1; border:1px solid #ced4da; background:#f8f9fa; border-radius:4px; font-size:12px; padding:4px 8px; color:#495057; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:300px;" title="{$labelSafe}">
                                                    {$labelTruncSafe}
                                                </div>
                                                <button type="button" id="rcrm-btn-reopen" title="{$btnTitleSafe}" style="margin-left:auto; background:#fff; border:2px solid #28a745; color:#28a745; border-radius:4px; padding:2px 6px; cursor:pointer; font-size:14px; transition:all 0.2s;"><i class="fas {$btnIconSafe}"></i></button>
                                            </div>
                                        </div>
                                    `;
                                    
                                    if (\$statusRef.length > 0) {
                                        if (\$statusRef.css("position") === "static") \$statusRef.css("position", "relative");
                                        \$statusRef.css("overflow", "visible");
                                        \$statusRef.append(widgetHtml);
                                    } else {
                                        if (\$fallback.css("position") === "static") \$fallback.css("position", "relative");
                                        \$fallback.css("overflow", "visible");
                                        \$fallback.append(widgetHtml);
                                    }
                                    
                                    $("#rcrm-btn-reopen").on("click", function(e) {
                                        e.preventDefault();
                                        $(this).html("<i class='fas fa-spinner fa-spin'></i>");
                                        
                                        $.ajax({
                                            url: "{$closeAjaxUrl}",
                                            method: "POST",
                                            data: { action: "reopen", id: {$objIdSecure}, type: "{$objElementSafe}", token: "{$newTokenStr}" },
                                            dataType: "json",
                                            success: function(res) { if (res.success) window.location.reload(); else { alert("Erreur: " + res.error); $("#rcrm-btn-reopen").html("<i class='fas {$btnIconSafe}'></i>"); } },
                                            error: function() { alert("Erreur de connexion."); $("#rcrm-btn-reopen").html("<i class='fas {$btnIconSafe}'></i>"); }
                                        });
                                    });
                                }
                            });
                        </script>
EOT;
                    } else {
                        // Fallback: No actioncomm found for this project
                        $btnTitleSafe = dol_escape_js('Rouvrir le projet');
                        $btnIconSafe  = dol_escape_js('fa-undo');
                        $objElementSafe = dol_escape_js($object->element);
                        $objIdSecure = (int)$object->id;
                        $closeAjaxUrl = dol_buildpath('/custom/reedcrm/ajax/close_record.php', 1);
                        $imgLogo = dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1);
                        $newTokenStr = newToken();
                        
                        $closureWidgetHtml = <<<EOT
                        <script>
                            $(document).ready(function() {
                                var \$statusRef = $(".statusref").first();
                                var \$fallback = $(".arearef, .titre").first();
                                if ((\$statusRef.length > 0 || \$fallback.length > 0) && !$("#reedcrm-closure-widget").length) {
                                    var widgetHtml = `
                                        <div id="reedcrm-closure-widget" style="position:absolute; top:calc(100% + 8px); right:0; z-index:50; width:max-content; display:block; background:#fff; border:1px solid #ced4da; border-radius:6px; padding:6px; box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <img src="{$imgLogo}" style="height:24px; width:auto;" alt="ReedCRM" />
                                                <i class="far fa-calendar-alt" style="color:#6c757d;"></i>
                                                <span style="font-size:13px; font-weight:bold; color:#000;">N/A</span>
                                                <span style="font-size:13px; color:#495057;">--/--/-- --:--</span>
                                            </div>
                                            <div style="display:flex; align-items:center; gap:8px; margin-top:6px;">
                                                <div style="flex-grow:1; border:1px solid #ced4da; background:#f8f9fa; border-radius:4px; font-size:12px; padding:4px 8px; color:#495057;">
                                                    Projet clôturé (aucun événement historique trouvé)
                                                </div>
                                                <button type="button" id="rcrm-btn-reopen" title="{$btnTitleSafe}" style="margin-left:auto; background:#fff; border:2px solid #28a745; color:#28a745; border-radius:4px; padding:2px 6px; cursor:pointer; font-size:14px; transition:all 0.2s;"><i class="fas {$btnIconSafe}"></i></button>
                                            </div>
                                        </div>
                                    `;
                                    if (\$statusRef.length > 0) {
                                        if (\$statusRef.css("position") === "static") \$statusRef.css("position", "relative");
                                        \$statusRef.css("overflow", "visible");
                                        \$statusRef.append(widgetHtml);
                                    } else {
                                        if (\$fallback.css("position") === "static") \$fallback.css("position", "relative");
                                        \$fallback.css("overflow", "visible");
                                        \$fallback.append(widgetHtml);
                                    }
                                    
                                    $("#rcrm-btn-reopen").on("click", function(e) {
                                        e.preventDefault();
                                        $(this).html("<i class='fas fa-spinner fa-spin'></i>");
                                        $.ajax({
                                            url: "{$closeAjaxUrl}",
                                            method: "POST",
                                            data: { action: "reopen", id: {$objIdSecure}, type: "{$objElementSafe}", token: "{$newTokenStr}" },
                                            dataType: "json",
                                            success: function(res) { if (res.success) window.location.reload(); },
                                            error: function() { alert("Erreur de connexion."); $("#rcrm-btn-reopen").html("<i class='fas {$btnIconSafe}'></i>"); }
                                        });
                                    });
                                }
                            });
                        </script>
EOT;
                    }
                }

                $callListWidgetHtml = $this->renderCallListWidget('project', (int) $object->id);
                if (!empty($callListWidgetHtml)) {
                    $assetsHtml .= '<script>
                        (function() {
                            var clWidget = ' . json_encode($callListWidgetHtml) . ';
                            function mountClWidget() {
                                setTimeout(function() {
                                    if (document.querySelector(".reedcrm-add-to-call-list-wrapper")) return;
                                    var t = document.createElement("div");
                                    t.innerHTML = clWidget;
                                    var node = t.firstElementChild;
                                    var closureWidget = document.getElementById("reedcrm-closure-widget");
                                    if (closureWidget && closureWidget.parentElement) {
                                        var parent = closureWidget.parentElement;
                                        var closureH = closureWidget.offsetHeight;
                                        node.style.position = "absolute";
                                        node.style.top = "calc(100% + " + (8 + closureH + 6) + "px)";
                                        node.style.right = "0";
                                        node.style.zIndex = "49";
                                        parent.appendChild(node);
                                    } else {
                                        var statsBlock = document.querySelector(".reedcrm-header-stats");
                                        if (statsBlock) {
                                            statsBlock.insertAdjacentElement("afterend", node);
                                        } else {
                                            var row = document.querySelector(".rcrm-co-org-row");
                                            var container = row || document.querySelector(".reedcrm-card-header-blocks") || document.querySelector("div.arearefonsamedir > div:first-child");
                                            if (!container) return;
                                            container.appendChild(node);
                                        }
                                    }
                                    if (window.reedcrm && window.reedcrm.call_list_widget) {
                                        window.reedcrm.call_list_widget.initSelect2();
                                    }
                                }, 300);
                            }
                            if (document.readyState === "loading") {
                                document.addEventListener("DOMContentLoaded", mountClWidget);
                            } else {
                                mountClWidget();
                            }
                        })();
                    </script>';
                }
                $this->resprints = $contactHtml . $jsMountDataHtml . $assetsHtml . $closureWidgetHtml;
            }
        }

        if (strpos($parameters['context'], 'propalcard') !== false) {
            $widgetHtml = $this->renderCallListWidget('propal', (int) $object->id);
            if (!empty($widgetHtml)) {
                $cssPath = dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1);
                $jsPath  = dol_buildpath('/custom/reedcrm/js/reedcrm.min.js', 1);
                $this->resprints .= '<link href="' . $cssPath . '" rel="stylesheet">';
                $this->resprints .= '<script src="' . $jsPath . '?v=' . time() . '"></script>';
                $this->resprints .= $widgetHtml;
            }
        }

        if (strpos($parameters['context'], 'invoicecard') !== false) {
            $widgetHtml = $this->renderCallListWidget('facture', (int) $object->id);
            if (!empty($widgetHtml)) {
                $cssPath = dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1);
                $jsPath  = dol_buildpath('/custom/reedcrm/js/reedcrm.min.js', 1);
                $this->resprints .= '<link href="' . $cssPath . '" rel="stylesheet">';
                $this->resprints .= '<script src="' . $jsPath . '?v=' . time() . '"></script>';
                $this->resprints .= $widgetHtml;
            }
        }

        return 0;
    }

    /**
     * Overloading the saturneBannerTab function : replacing the core function with the custom one
     *
     * @param  array      $parameters Hook metadata
     * @param  CommonObject $object   Current object
     * @return int                    0 = no replace, 1 = replace
     */
    public function saturneBannerTab(array $parameters, CommonObject $object): int
    {
        global $langs;

        if (strpos($parameters['context'], 'call_list_card') !== false) {
            $mobileUrl        = dol_buildpath('/custom/reedcrm/view/frontend/pwa_call_list.php', 1) . '?id=' . (int) $object->id;
            $this->resprints  = '<div class="refidno">';
            $this->resprints .= '<a href="' . dol_escape_htmltag($mobileUrl) . '" target="_blank">';
            $this->resprints .= '<i class="fas fa-mobile-alt"></i> ' . $langs->transnoentities('MobileView');
            $this->resprints .= '</a>';
            $this->resprints .= '</div>';
        }
        return 0;
    }

    /**
     * Renders the "Add to call list" widget HTML for a card banner.
     *
     * @param  string $elementType  'project', 'propal', or 'facture'
     * @param  int    $elementId    ID of the element
     * @return string               HTML widget, or '' if no permission / no active lists
     */
    private function renderCallListWidget(string $elementType, int $elementId): string
    {
        global $user, $langs;

        if (!$user->hasRight('reedcrm', 'call_list', 'write')) {
            return '';
        }

        $langs->load('reedcrm@reedcrm');

        require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/calllist.class.php';

        $callListObj = new CallList($this->db);
        $callLists   = $callListObj->fetchAll('', '', 0, 0, [
            'customsql' => 'status IN (' . CallList::STATUS_DRAFT . ', ' . CallList::STATUS_ACTIVE . ')'
        ]);

        if (empty($callLists) || !is_array($callLists)) {
            return '';
        }

        $ajaxUrl = dol_buildpath('/custom/reedcrm/ajax/add_to_call_list.php', 1);

        $defaultAjaxUrl = dol_buildpath('/custom/reedcrm/ajax/add_to_default_call_list.php', 1);

        $logoPath = dol_buildpath('/custom/reedcrm/img/object_reedcrm_color.png', 1);

        $html  = '<div class="reedcrm-add-to-call-list-wrapper"';
        $html .= ' data-element-type="' . dol_escape_htmltag($elementType) . '"';
        $html .= ' data-element-id="' . (int) $elementId . '"';
        $html .= ' data-ajax-url="' . dol_escape_htmltag($ajaxUrl) . '"';
        $html .= ' data-default-ajax-url="' . dol_escape_htmltag($defaultAjaxUrl) . '">';
        $html .= '<img src="' . dol_escape_htmltag($logoPath) . '" class="reedcrm-add-to-call-list-logo" alt="ReedCRM" />';
        $html .= '<i class="fas fa-phone" style="color:#64748b;"></i>';
        $html .= '<i class="fas fa-star reedcrm-call-list-default-btn" title="' . dol_escape_htmltag($langs->trans('AddToMyCallList')) . '"></i>';
        $html .= '<select class="reedcrm-call-list-select">';
        $html .= '<option value="">' . dol_escape_htmltag($langs->trans('SelectCallList')) . '</option>';
        foreach ($callLists as $cl) {
            $html .= '<option value="' . (int) $cl->id . '">' . dol_escape_htmltag($cl->label) . '</option>';
        }
        $html .= '</select>';
        $html .= '<button type="button" class="reedcrm-call-list-add-btn" disabled><i class="fas fa-save"></i></button>';
        $html .= '</div>';

        return $html;
    }
}

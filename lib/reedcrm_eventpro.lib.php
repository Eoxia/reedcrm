<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    lib/reedcrm_eventpro.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for ReedCRM eventPro
 */

/**
 * Render the eventPro tab bar (customer note / email / ticket).
 *
 * @param  int    $id          Id of the object the events belong to
 * @param  string $fromType    Element type of that object (project, societe, ...)
 * @param  string $currentTab  Active tab key
 * @param  string $extraParams Extra query string (starting with &) kept across tab switches
 * @return string              HTML output
 */
function showEventProTabs($id, $fromType, $currentTab, string $extraParams = ''): string
{
    global $langs;

    $out = '<div class="tabs">';

    $tabs = [
        'note' => [
            'label' => 'CustomerNote',
            'picto' => 'contact',
        ],
        'email' => [
            'label' => 'SendEmail',
            'picto' => 'fontawesome_fa-envelope_fas',
        ],
        'ticket' => [
            'label' => 'Ticket',
            'picto' => 'ticket',
        ]
    ];
    foreach ($tabs as $tabKey => $tabInfos) {
        $isActive = ($currentTab == $tabKey);
        $out .= '<div class="inline-block tabsElem' . ($isActive ? ' tabsElemActive' : '') . '">';
            $out .= '<div class="tab tab' . ($isActive ? 'active' : 'unactive') . '">';
                $out .= '<a class="tab inline-block valignmiddle" href="' . $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType . '&tab=' . $tabKey . $extraParams . '" title="' . $langs->trans($tabInfos['label']) . '">';
                    $out .= img_picto($langs->trans($tabInfos['label']), $tabInfos['picto'], 'class="pictofixedwidth"');
                    $out .= $langs->trans($tabInfos['label']);
                $out .= '</a>';
            $out .= '</div>';
        $out .= '</div>';
    }
    $out .= '</div>';

    return $out;
}

/**
 * Render the title row of a block of the right column of the CardPro: its title, the link to the full list with
 * the count, the statistics and the add button when given
 *
 * @param  int    $colspan   Number of columns of the block
 * @param  string $title     Title, translated
 * @param  string $listUrl   URL of the full list
 * @param  string $listLabel Label of the link to the full list, translated
 * @param  int    $num       Number of records, shown in the badge
 * @param  string $statsUrl  URL of the statistics, empty for none
 * @param  string $addUrl    URL that creates a record, empty for no add button
 * @param  string $addLabel  Tooltip of the add button, translated
 * @param  string $addFocus  Selector of the field of the CardPro form to go to, when the page already shows it, instead of
 *                           following $addUrl (js/modules/eventpro.js)
 * @return string            HTML output
 */
function reedcrm_cardpro_block_title(int $colspan, string $title, string $listUrl, string $listLabel, int $num, string $statsUrl = '', string $addUrl = '', string $addLabel = '', string $addFocus = ''): string
{
    global $langs;

    $out  = '<tr class="liste_titre">';
    $out .= '<td colspan="' . $colspan . '"><table width="100%" class="nobordernopadding"><tr>';
    $out .= '<td>' . $title . '</td>';
    $out .= '<td class="right"><a class="notasortlink" href="' . $listUrl . '"><span class="hideonsmartphone">' . $listLabel . '</span><span class="badge marginleftonlyshort">' . $num . '</span></a></td>';
    if (!empty($statsUrl)) {
        $out .= '<td class="right cardpro-block-tool"><a href="' . $statsUrl . '">' . img_picto($langs->trans('Statistics'), 'stats') . '</a></td>';
    }
    if (!empty($addUrl)) {
        $out .= '<td class="right cardpro-block-tool"><a class="cardpro-block-add" href="' . $addUrl . '" title="' . dol_escape_htmltag($addLabel) . '"' . (!empty($addFocus) ? ' data-focus="' . dol_escape_htmltag($addFocus) . '"' : '') . '>';
        $out .= '<span class="fas fa-plus"></span>';
        $out .= '</a></td>';
    }
    $out .= '</tr></table></td>';
    $out .= '</tr>';

    return $out;
}

/**
 * Render the line of an empty block of the right column of the CardPro. The blocks show even when empty, so that
 * their add button stays at hand
 *
 * @param  int    $colspan Number of columns of the block
 * @param  string $label   Message, translated
 * @return string          HTML output
 */
function reedcrm_cardpro_block_empty(int $colspan, string $label): string
{
    return '<tr class="oddeven"><td colspan="' . $colspan . '" class="cardpro-block-empty">' . $label . '</td></tr>';
}

/**
 * URL of the CardPro of an object, on one of the tabs of its form
 *
 * @param  CommonObject $object Thirdparty or project of the CardPro
 * @param  string       $tab    Tab of the form (note, email, ticket)
 * @return string               URL, anchored on the form
 */
function reedcrm_cardpro_url(CommonObject $object, string $tab): string
{
    return dol_buildpath('/reedcrm/view/procard.php', 1) . '?from_id=' . ((int) $object->id) . '&from_type=' . urlencode($object->element) . '&tab=' . urlencode($tab) . '#addeventform';
}

function showLatestProposals($object, $maxSizeShortListLimit = 3): string
{
    global $conf, $db, $langs, $user;

    $out = '';

    // Latest proposals (from comm/card.php)
    if (isModEnabled('propal') && $user->hasRight('propal', 'lire')) {
        $langs->load('propal');

        $sql = "SELECT s.nom, s.rowid, p.rowid as propalid, p.fk_projet, p.fk_statut, p.total_ht";
        $sql .= ", p.total_tva";
        $sql .= ", p.total_ttc";
        $sql .= ", p.ref, p.ref_client, p.remise";
        $sql .= ", p.datep as dp, p.fin_validite as date_limit, p.entity";
        $sql .= " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "propal as p, " . MAIN_DB_PREFIX . "c_propalst as c";
        $sql .= " WHERE p.fk_soc = s.rowid AND p.fk_statut = c.id";
        $sql .= " AND s.rowid = " . $object->thirdparty->id;
        $sql .= " AND p.entity IN (" . getEntity('propal') . ")";
        $sql .= " ORDER BY p.datep DESC";

        $resql = $db->query($sql);
        if ($resql) {
            $propal = new Propal($db);

            $num = $db->num_rows($resql);

            // Back to the CardPro when the creation is canceled, with the project of a project CardPro already chosen
            $addUrl = '';
            if ($user->hasRight('propal', 'creer')) {
                $addUrl  = DOL_URL_ROOT . '/comm/propal/card.php?action=create&socid=' . ((int) $object->thirdparty->id);
                $addUrl .= ($object->element == 'project' ? '&projectid=' . ((int) $object->id) : '');
                $addUrl .= '&backtopage=' . urlencode(reedcrm_cardpro_url($object, 'note'));
            }

            $out .= '<div class="div-table-responsive-no-min">';
            $out .= '<table class="noborder centpercent lastrecordtable">';
            $out .= reedcrm_cardpro_block_title(5, $langs->trans('LastPropals', ($num <= $maxSizeShortListLimit ? '' : $maxSizeShortListLimit)), DOL_URL_ROOT . '/comm/propal/list.php?socid=' . ((int) $object->thirdparty->id), $langs->trans('AllPropals'), $num, DOL_URL_ROOT . '/comm/propal/stats/index.php?socid=' . ((int) $object->thirdparty->id), $addUrl, $langs->trans('NewPropal'));
            if ($num == 0) {
                $out .= reedcrm_cardpro_block_empty(5, $langs->trans('EventProNoProposal'));
            }

            $i = 0;
            while ($i < $num && $i < $maxSizeShortListLimit) {
                $objp = $db->fetch_object($resql);

                // The cardpro-col-* classes size the columns, so that the dates and statuses of every block line up
                $out .= '<tr class="oddeven">';
                $out .= '<td class="nowraponall cardpro-col-ref">';
                $propal->id           = $objp->propalid;
                $propal->ref          = $objp->ref;
                $propal->ref_customer = $objp->ref_client;
                $propal->fk_project   = $objp->fk_projet;
                $propal->total_ht     = $objp->total_ht;
                $propal->total_tva    = $objp->total_tva;
                $propal->total_ttc    = $objp->total_ttc;
                $out .= $propal->getNomUrl(1);
                $out .= '</td><td class="cardpro-col-main">';
                if ($propal->fk_project > 0) {
                    $project = new Project($db);
                    $project->fetch($propal->fk_project);
                    $out .= $project->getNomUrl(1);
                }
                if (($db->jdate($objp->date_limit) < (dol_now() - $conf->propal->cloture->warning_delay)) && $objp->fk_statut == $propal::STATUS_VALIDATED) {
                    $out .= ' ' . img_warning();
                }
                // Amount before the date: the date column then sits where it is in the other blocks
                $out .= '</td><td class="right nowraponall cardpro-col-amount">' . price($objp->total_ht) . '</td>';
                $out .= '<td class="right nowraponall cardpro-col-date">' . dol_print_date($db->jdate($objp->dp), 'day') . '</td>';
                $out .= '<td class="right nowraponall cardpro-col-status">' . $propal->LibStatut($objp->fk_statut, 5) . '</td></tr>';
                $i++;
            }
            $db->free($resql);

            $out .= '</table>';
            $out .= '</div>';
        } else {
            dol_print_error($db);
        }
    }

    return $out;
}

function showLatestProjects($object, $maxSizeShortListLimit = 3): string
{
    global $db, $langs, $user;

    $out = '';

    // Latest projects (from comm/card.php)
    if (isModEnabled('project') && $user->hasRight('projet', 'lire')) {
        $langs->load('projects');

        $sql = "SELECT s.nom, s.rowid, p.rowid as projectid, p.ref, p.title, p.fk_statut, p.datec, p.dateo, p.date_close, p.budget_amount";
        $sql .= " FROM " . MAIN_DB_PREFIX . "societe as s, " . MAIN_DB_PREFIX . "projet as p";
        $sql .= " WHERE p.fk_soc = s.rowid";
        $sql .= " AND s.rowid = " . $object->thirdparty->id;
        $sql .= " AND p.entity IN (" . getEntity('project') . ")";
        $sql .= " ORDER BY p.datec DESC";

        $resql = $db->query($sql);
        if ($resql) {
            $project = new Project($db);

            $num = $db->num_rows($resql);

            $addUrl = '';
            if ($user->hasRight('projet', 'creer')) {
                $addUrl = DOL_URL_ROOT . '/projet/card.php?action=create&socid=' . ((int) $object->thirdparty->id) . '&backtopage=' . urlencode(reedcrm_cardpro_url($object, 'note'));
            }

            $out .= '<div class="div-table-responsive-no-min">';
            $out .= '<table class="noborder centpercent lastrecordtable">';
            $out .= reedcrm_cardpro_block_title(4, $langs->trans('LastProjects', ($num <= $maxSizeShortListLimit ? '' : $maxSizeShortListLimit)), DOL_URL_ROOT . '/projet/list.php?socid=' . ((int) $object->thirdparty->id), $langs->trans('AllProjects'), $num, DOL_URL_ROOT . '/projet/stats/index.php?socid=' . ((int) $object->thirdparty->id), $addUrl, $langs->trans('NewProject'));
            if ($num == 0) {
                $out .= reedcrm_cardpro_block_empty(4, $langs->trans('EventProNoProject'));
            }

            $i = 0;
            while ($i < $num && $i < $maxSizeShortListLimit) {
                $objp = $db->fetch_object($resql);

                $out .= '<tr class="oddeven">';
                $out .= '<td class="nowraponall cardpro-col-ref">';
                $project->id            = $objp->projectid;
                $project->ref           = $objp->ref;
                $project->title         = $objp->title;
                $project->budget_amount = $objp->budget_amount;
                $out .= $project->getNomUrl(1);
                // Cut by the width of the column, the whole title stays in the tooltip
                $out .= '</td><td class="cardpro-col-main" title="' . dol_escape_htmltag($objp->title) . '">';
                $out .= dol_escape_htmltag($objp->title);
                $out .= '</td><td class="right nowraponall cardpro-col-date">' . dol_print_date($db->jdate($objp->datec), 'day') . '</td>';
                $out .= '<td class="right nowraponall cardpro-col-status">' . $project->LibStatut($objp->fk_statut, 5) . '</td></tr>';
                $i++;
            }
            $db->free($resql);

            $out .= '</table>';
            $out .= '</div>';
        } else {
            dol_print_error($db);
        }
    }

    return $out;
}

/**
 * Render the open tickets of the thirdparty, neither closed nor canceled, latest first (#997).
 *
 * @param  CommonObject $object                Object of the card, with its thirdparty loaded
 * @param  int          $maxSizeShortListLimit Number of tickets listed
 * @return string                              HTML output, empty without the ticket module or the right to read tickets
 */
function showOpenTickets(CommonObject $object, int $maxSizeShortListLimit = 3): string
{
    global $db, $langs, $user;

    $out = '';

    if (isModEnabled('ticket') && $user->hasRight('ticket', 'read')) {
        require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';

        $langs->load('ticket');

        $sql  = 'SELECT t.rowid, t.ref, t.track_id, t.subject, t.fk_statut, t.progress, t.datec';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'ticket as t';
        $sql .= ' WHERE t.fk_soc = ' . ((int) $object->thirdparty->id);
        $sql .= ' AND t.entity IN (' . getEntity('ticket') . ')';
        $sql .= ' AND t.fk_statut NOT IN (' . Ticket::STATUS_CLOSED . ', ' . Ticket::STATUS_CANCELED . ')';
        // Same restriction as the ticket list, which the link of the title opens
        if (!$user->admin && getDolGlobalString('TICKET_LIMIT_VIEW_ASSIGNED_ONLY')) {
            $sql .= ' AND t.fk_user_assign = ' . ((int) $user->id);
        }
        $sql .= ' ORDER BY t.datec DESC';

        $resql = $db->query($sql);
        if ($resql) {
            $ticket = new Ticket($db);

            $num = $db->num_rows($resql);

            // The ticket tab of the CardPro form, as quick as the note one
            $addUrl = '';
            if ($user->hasRight('ticket', 'write') && $user->hasRight('reedcrm', 'eventpro', 'write')) {
                $addUrl = reedcrm_cardpro_url($object, 'ticket');
            }

            $out .= '<div class="div-table-responsive-no-min">';
            $out .= '<table class="noborder centpercent lastrecordtable">';
            $out .= reedcrm_cardpro_block_title(4, $langs->trans('EventProOpenTickets'), DOL_URL_ROOT . '/ticket/list.php?socid=' . ((int) $object->thirdparty->id) . '&search_fk_statut=openall', $langs->trans('EventProAllOpenTickets'), $num, '', $addUrl, $langs->trans('NewTicket'), 'input[name="ticket_subject"]');
            if ($num == 0) {
                $out .= reedcrm_cardpro_block_empty(4, $langs->trans('EventProNoOpenTickets'));
            }

            $i = 0;
            while ($i < $num && $i < $maxSizeShortListLimit) {
                $objp = $db->fetch_object($resql);

                $ticket->id       = (int) $objp->rowid;
                $ticket->ref      = $objp->ref;
                $ticket->track_id = $objp->track_id;
                $ticket->subject  = $objp->subject;
                $ticket->status   = (int) $objp->fk_statut;
                $ticket->progress = (int) $objp->progress;

                $out .= '<tr class="oddeven">';
                $out .= '<td class="nowraponall cardpro-col-ref">' . $ticket->getNomUrl(1) . '</td>';
                $out .= '<td class="cardpro-col-main" title="' . dol_escape_htmltag($objp->subject) . '">' . dol_escape_htmltag($objp->subject) . '</td>';
                $out .= '<td class="right nowraponall cardpro-col-date">' . dol_print_date($db->jdate($objp->datec), 'day') . '</td>';
                $out .= '<td class="right nowraponall cardpro-col-status">' . $ticket->LibStatut((int) $objp->fk_statut, 5, 0, (int) $objp->progress) . '</td>';
                $out .= '</tr>';
                $i++;
            }
            $db->free($resql);

            $out .= '</table>';
            $out .= '</div>';
        } else {
            dol_print_error($db);
        }
    }

    return $out;
}

/**
 * Render the open notes of the thirdparty: its events still "to do" or "in progress", latest first (#997).
 * These are the two statuses the "Customer note" tab offers besides "done".
 *
 * @param  CommonObject $object                Object of the card, with its thirdparty loaded
 * @param  int          $maxSizeShortListLimit Number of events listed
 * @return string                              HTML output, empty without the agenda module or the right to read events
 */
function showOpenNotes(CommonObject $object, int $maxSizeShortListLimit = 3): string
{
    global $db, $langs, $user;

    $out = '';

    if (isModEnabled('agenda') && $user->hasRight('agenda', 'myactions', 'read')) {
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        $langs->load('agenda');

        $canReadAllEvents = $user->hasRight('agenda', 'allactions', 'read');

        $sql  = 'SELECT a.id, a.label, a.datep, a.percent, a.fk_user_author, a.fk_user_action, c.code as type_code, c.libelle as type_label, c.picto as type_picto';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm as a';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'c_actioncomm as c ON c.id = a.fk_action';
        $sql .= ' WHERE a.fk_soc = ' . ((int) $object->thirdparty->id);
        $sql .= ' AND a.entity IN (' . getEntity('agenda') . ')';
        // The "todo" filter of the agenda list, which the link of the title opens: -1 is "not applicable", the automatic events
        $sql .= ' AND a.percent >= 0 AND a.percent < 100';
        // Without the right to see every event, the agenda list only shows the ones the user is assigned to
        if (!$canReadAllEvents) {
            $sql .= ' AND EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . "actioncomm_resources as ar WHERE ar.fk_actioncomm = a.id AND ar.element_type = 'user' AND ar.fk_element = " . ((int) $user->id) . ')';
        }
        $sql .= ' ORDER BY a.datep DESC, a.id DESC';

        $resql = $db->query($sql);
        if ($resql) {
            $actionComm = new ActionComm($db);

            $num = $db->num_rows($resql);

            // The note tab of the CardPro form: on the page, the "+" goes to its title field
            $addUrl = '';
            if ($user->hasRight('agenda', 'myactions', 'create') && $user->hasRight('reedcrm', 'eventpro', 'write')) {
                $addUrl = reedcrm_cardpro_url($object, 'note');
            }

            $out .= '<div class="div-table-responsive-no-min">';
            $out .= '<table class="noborder centpercent lastrecordtable">';
            // Without disabledefaultvalues the list keeps the default event type of the agenda setup (AGENDA_DEFAULT_FILTER_TYPE),
            // and without search_filtert=-1 only the events of the user, so its count would not be the one of the badge
            $out .= reedcrm_cardpro_block_title(4, $langs->trans('EventProOpenNotes'), DOL_URL_ROOT . '/comm/action/list.php?mode=show_list&disabledefaultvalues=1&search_filtert=-1&socid=' . ((int) $object->thirdparty->id) . '&search_status=todo', $langs->trans('EventProAllOpenNotes'), $num, '', $addUrl, $langs->trans('EventProNewNote'), '#addeventform #title');
            if ($num == 0) {
                $out .= reedcrm_cardpro_block_empty(4, $langs->trans('EventProNoOpenNotes'));
            }

            $i = 0;
            while ($i < $num && $i < $maxSizeShortListLimit) {
                $objp = $db->fetch_object($resql);

                $actionComm->id          = (int) $objp->id;
                $actionComm->ref         = (string) $objp->id;
                $actionComm->label       = $objp->label;
                $actionComm->type_code   = $objp->type_code;
                $actionComm->type_label  = $objp->type_label;
                $actionComm->type_picto  = $objp->type_picto;
                $actionComm->authorid    = (int) $objp->fk_user_author;
                $actionComm->userownerid = (int) $objp->fk_user_action;
                // Every line is an event the user is assigned to when they cannot see them all: getNomUrl() checks it to give the link
                $actionComm->userassigned = $canReadAllEvents ? [] : [(int) $user->id => ['id' => (int) $user->id, 'transparency' => 0]];

                $typeLabel = $langs->trans('Action' . $objp->type_code);
                if ($typeLabel == 'Action' . $objp->type_code) {
                    $typeLabel = $objp->type_label;
                }

                // The short status of ActionComm is the bare percentage ("0%"): the word reads better, the percentage stays in the tooltip
                $isToDo      = ((int) $objp->percent === 0);
                $statusLabel = $langs->trans($isToDo ? 'StatusActionToDo' : 'StatusActionInProcess');
                $statusBadge = dolGetStatus($statusLabel, $statusLabel, '', $isToDo ? 'status1' : 'status3', 5, '', ['badgeParams' => ['attr' => ['title' => $statusLabel . ' (' . ((int) $objp->percent) . '%)']]]);

                $out .= '<tr class="oddeven">';
                $out .= '<td class="cardpro-col-main">' . $actionComm->getNomUrl(1) . '</td>';
                $out .= '<td class="cardpro-col-type" title="' . dol_escape_htmltag($typeLabel) . '">' . dol_escape_htmltag($typeLabel) . '</td>';
                $out .= '<td class="right nowraponall cardpro-col-date">' . dol_print_date($db->jdate($objp->datep), 'day', 'tzuserrel') . '</td>';
                $out .= '<td class="right nowraponall cardpro-col-status">' . $statusBadge . '</td>';
                $out .= '</tr>';
                $i++;
            }
            $db->free($resql);

            $out .= '</table>';
            $out .= '</div>';
        } else {
            dol_print_error($db);
        }
    }

    return $out;
}

function showOutstandingProposals(CommonObject $object): string
{
    global $conf, $langs, $user;

    $out = '';

    // Box proposals
    if (isModEnabled('propal') && $user->hasRight('propal', 'lire')) {
        $outstandingProposals = $object->thirdparty->getOutstandingProposals();

        $text = $langs->trans('OverAllProposals');
        $link = DOL_URL_ROOT . '/comm/propal/list.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator">' . price($outstandingProposals['total_ht'], 1, $langs, 1, -1, -1, $conf->currency) . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }

    return $out;
}

function showOutstandingOrders(CommonObject $object): string
{
    global $conf, $langs, $user;

    $out = '';

    // Box orders
    if (isModEnabled('order') && $user->hasRight('commande', 'lire')) {
        $outstandingOrders = $object->thirdparty->getOutstandingOrders();

        $text = $langs->trans('OverAllOrders');
        $link = DOL_URL_ROOT . '/commande/list.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator">'.price($outstandingOrders['total_ht'], 1, $langs, 1, -1, -1, $conf->currency) . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }

    return $out;
}

function showOutstandingBills(CommonObject $object): string
{
    global $conf, $langs, $user;

    $out = '';

    // Box invoices
    if (isModEnabled('invoice') && $user->hasRight('facture', 'lire')) {
        $outstandingBills  = $object->thirdparty->getOutstandingBills('customer', 0);
        $outstandingOpened = $outstandingBills['opened'];

        $text = $langs->trans('OverAllInvoices');
        $link = DOL_URL_ROOT . '/compta/facture/list.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator">' . price($outstandingBills['total_ht'], 1, $langs, 1, -1, -1, $conf->currency).'</span>';
        $out .= '</div>';
        $out .= '</a>';

        // Box outstanding bill
        $warn = '';
        if ($object->thirdparty->outstanding_limit != '' && $object->thirdparty->outstanding_limit < $outstandingOpened) {
            $warn = ' ' . img_warning($langs->trans("OutstandingBillReached"));
        }
        $text = $langs->trans('CurrentOutstandingBill');
        $link = DOL_URL_ROOT . '/compta/recap-compta.php?socid=' . $object->thirdparty->id;

        $out .= '<a href="' . $link . '" class="boxstatsindicator thumbstat nobold nounderline">';
        $out .= '<div class="boxstats" title="' . dol_escape_htmltag($text) . '">';
        $out .= '<span class="boxstatstext">' . img_object('', 'bill', 'class="pictofixedwidth"') . '<span>' . $text . '</span></span><br>';
        $out .= '<span class="boxstatsindicator' . ($outstandingOpened > 0 ? ' amountremaintopay' : '') . '">' . price($outstandingOpened, 1, $langs, 1, -1, -1, $conf->currency) . $warn . '</span>';
        $out .= '</div>';
        $out .= '</a>';
    }

    return $out;
}

function showEventProInfos(CommonObject $object): string
{
    global $langs;

    $out  = '<div class="box divboxtable box-halfright">';
    $out .= '<table summary="' . dol_escape_htmltag($langs->trans('DolibarrStateBoard')) . '" class="border boxtable boxtablenobottom boxtablenotop boxtablenomarginbottom centpercent">';
    $out .= '<tr class="impair nohover"><td colspan="2" class="tdboxstats nohover">';
    // Laid out as a grid by the theme, the tiles share the width instead of floating in the middle
    $out .= '<div class="cardpro-stats">';

    $out .= showOutstandingProposals($object);
    $out .= showOutstandingOrders($object);
    $out .= showOutstandingBills($object);

    $out .= '</div>';
    $out .= '</td></tr>';
    $out .= '</table>';
    $out .= '</div>';

    $maxSizeShortListLimit = getDolGlobalString('MAIN_SIZE_SHORTLIST_LIMIT');

    $out .= showLatestProposals($object, $maxSizeShortListLimit);
    // Under the proposals, what is still open with the thirdparty (#997)
    $out .= showOpenTickets($object, (int) $maxSizeShortListLimit);
    $out .= showOpenNotes($object, (int) $maxSizeShortListLimit);
    $out .= showLatestProjects($object, $maxSizeShortListLimit);

    return $out;
}

/**
 * Render a compact opportunity summary header for the project preview modal.
 *
 * Shows the project ref + thirdparty, the opportunity amount, the weighted pipeline
 * (amount x probability / 100), the probability and the opportunity status.
 *
 * @param  CommonObject $object Project object
 * @return string               HTML summary header (empty for non-project objects)
 */
function reedcrm_project_summary_header(CommonObject $object): string
{
    global $conf, $db, $langs;

    if ($object->element !== 'project') {
        return '';
    }

    $langs->load('projects');

    $socName = '';
    if (!empty($object->socid)) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
        $thirdparty = new Societe($db);
        if ($thirdparty->fetch($object->socid) > 0) {
            $socName = $thirdparty->name;
        }
    }

    $oppAmount  = (float) $object->opp_amount;
    $oppPercent = (float) $object->opp_percent;
    $weighted   = $oppAmount * $oppPercent / 100;

    $statusLabel = '';
    if (!empty($object->fk_opp_status)) {
        $statusLabel = $langs->trans(dol_getIdFromCode($db, $object->fk_opp_status, 'c_lead_status', 'rowid', 'label'));
    }

    $metrics = [
        ['label' => $langs->trans('OpportunityAmount'),           'value' => price($oppAmount, 0, $langs, 1, -1, -1, $conf->currency)],
        ['label' => $langs->trans('ReedCRMKpiWeightedAmount'),    'value' => price($weighted, 0, $langs, 1, -1, -1, $conf->currency)],
        ['label' => $langs->trans('OpportunityProbabilityShort'), 'value' => price2num($oppPercent, 1) . ' %'],
    ];
    if ($statusLabel !== '') {
        $metrics[] = ['label' => $langs->trans('OpportunityStatus'), 'value' => dol_escape_htmltag($statusLabel)];
    }

    $out  = '<div class="reedcrm-preview-summary">';
    $out .= '<div class="reedcrm-preview-summary-title">' . dol_escape_htmltag($object->ref) . ($socName !== '' ? ' — ' . dol_escape_htmltag($socName) : '') . '</div>';
    $out .= '<div class="reedcrm-preview-summary-metrics">';
    foreach ($metrics as $metric) {
        $out .= '<div class="reedcrm-preview-metric">';
        $out .= '<span class="reedcrm-preview-metric-label">' . $metric['label'] . '</span>';
        $out .= '<span class="reedcrm-preview-metric-value">' . $metric['value'] . '</span>';
        $out .= '</div>';
    }
    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

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
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

/**
 * \file    view/ticket_money.php
 * \ingroup reedcrm
 * \brief   Tickets seen from the money : what to bill, who owes, which quote to make. One line per ticket, the most
 *          money at stake first, and one button only : the next action. Replaces the "List" entry of the Tickets menu.
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
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../class/reedcrmticketmoney.class.php';
require_once __DIR__ . '/../lib/reedcrm_ticket_money.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['ticket', 'bills', 'propal', 'projects']);

// Get parameters
$action       = GETPOST('action', 'aZ09');
$massaction   = GETPOST('massaction', 'alpha');
$toselect     = GETPOST('toselect', 'array:int');
$id           = GETPOSTINT('id');
$mode         = GETPOST('mode', 'aZ09');
$searchAction = GETPOST('search_action', 'aZ09');
$searchUser   = GETPOSTINT('search_user');
$searchText   = trim(GETPOST('search_text', 'alphanohtml'));

if ($mode === 'mine') {
    $searchUser = (int) $user->id;
}
$filterActions = [
    ReedcrmTicketMoney::ACTION_BILL     => [ReedcrmTicketMoney::ACTION_BILL],
    ReedcrmTicketMoney::ACTION_RELAUNCH => [ReedcrmTicketMoney::ACTION_RELAUNCH],
    ReedcrmTicketMoney::ACTION_QUOTE    => [ReedcrmTicketMoney::ACTION_QUOTE],
    'unbillable'                        => [ReedcrmTicketMoney::ACTION_ATTACH_THIRDPARTY, ReedcrmTicketMoney::ACTION_ATTACH_PROJECT],
    ReedcrmTicketMoney::ACTION_CLOSE    => [ReedcrmTicketMoney::ACTION_CLOSE],
];
if (!array_key_exists($searchAction, $filterActions)) {
    $searchAction = '';
}

// Initialize technical objects
$form  = new Form($db);
$money = new ReedcrmTicketMoney($db);

$hookmanager->initHooks(['reedcrmticketmoneylist']);

// Security check
$permissiontoread  = $user->hasRight('reedcrm', 'read') && $user->hasRight('ticket', 'read');
$permissiontobill  = $user->hasRight('facture', 'creer');
$permissiontoquote = $user->hasRight('propal', 'creer');
$permissiontoreserve = $user->hasRight('ticket', 'write');
saturne_check_access($permissiontoread);

$listParams = ($searchAction ? '&search_action=' . urlencode($searchAction) : '') . ($searchUser > 0 ? '&search_user=' . $searchUser : '') . ($searchText !== '' ? '&search_text=' . urlencode($searchText) : '');
$selfUrl    = $_SERVER['PHP_SELF'] . '?' . ltrim($listParams, '&');

/*
 * Actions
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $money, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    $ticketsToBill = [];
    if ($action === 'bill' && $id > 0) {
        $ticketsToBill = [$id];
    } elseif ($massaction === 'reedcrm_bill' && !empty($toselect)) {
        $ticketsToBill = $toselect;
    }

    if (!empty($ticketsToBill) && $permissiontobill) {
        if (GETPOST('token', 'alpha') !== currentToken()) {
            accessforbidden('Bad value for CSRF token');
        }
        $invoiceIds = $money->billTickets($user, $ticketsToBill);
        if (!empty($money->errors)) {
            setEventMessages('', $money->errors, 'errors');
        }
        // One invoice : straight to it, it is a draft to check and validate
        if (count($invoiceIds) === 1 && empty($money->errors)) {
            setEventMessages($langs->trans('TicketMoneyInvoiceCreated'), []);
            header('Location: ' . DOL_URL_ROOT . '/compta/facture/card.php?id=' . reset($invoiceIds));
            exit;
        }
        if (!empty($invoiceIds)) {
            require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
            $links = [];
            foreach ($invoiceIds as $invoiceId) {
                $invoice = new Facture($db);
                $invoice->fetch($invoiceId);
                $links[] = $invoice->getNomUrl(1);
            }
            setEventMessages($langs->trans('TicketMoneyInvoicesCreated', count($invoiceIds)) . ' ' . implode(', ', $links), []);
        }
        header('Location: ' . $selfUrl);
        exit;
    }

    if ($action === 'quote' && $id > 0 && $permissiontoquote) {
        if (GETPOST('token', 'alpha') !== currentToken()) {
            accessforbidden('Bad value for CSRF token');
        }
        $proposalId = $money->createProposal($user, $id);
        if ($proposalId > 0) {
            header('Location: ' . DOL_URL_ROOT . '/comm/propal/card.php?id=' . $proposalId);
            exit;
        }
        setEventMessages($money->error, $money->errors, 'errors');
        header('Location: ' . $selfUrl);
        exit;
    }

    // Customer late in paying : the ticket is handled anyway, under reserve, or goes back to the chasing
    if (in_array($action, ['reserve', 'unreserve'], true) && $id > 0 && $permissiontoreserve) {
        if (GETPOST('token', 'alpha') !== currentToken()) {
            accessforbidden('Bad value for CSRF token');
        }
        $ticket = new Ticket($db);
        if ($ticket->fetch($id) > 0 && $money->setReserve($user, $ticket, $action === 'reserve') > 0) {
            $message = $action === 'reserve' ? $langs->trans('TicketMoneyReserveSaved', $ticket->ref, dol_print_date(dol_now() + ReedcrmTicketMoney::getReserveDays() * 86400, 'day')) : $langs->trans('TicketMoneyReserveRemoved', $ticket->ref);
            setEventMessages($message, []);
        } else {
            setEventMessages($money->error ?: $ticket->error, [], 'errors');
        }
        header('Location: ' . $selfUrl . '#rtm-group-' . ($action === 'reserve' ? 'reserve' : ReedcrmTicketMoney::ACTION_RELAUNCH));
        exit;
    }
}

/*
 * Data
 */

if ($money->load() < 0) {
    setEventMessages($money->error, $money->errors, 'errors');
}
$rows = array_values(array_filter($money->getTargets(), function ($row) use ($searchUser, $searchText) {
    if ($searchUser > 0 && $row->fk_user_assign !== $searchUser) {
        return false;
    }
    if ($searchText !== '') {
        $haystack = $row->ref . ' ' . $row->subject . ' ' . $row->soc_name . ' ' . $row->project_ref;
        if (stripos($haystack, $searchText) === false) {
            return false;
        }
    }
    return true;
}));
$summary = $money->getSummary($rows);
if ($searchAction !== '') {
    $rows = array_values(array_filter($rows, function ($row) use ($filterActions, $searchAction) {
        if ($searchAction === ReedcrmTicketMoney::ACTION_BILL) {
            return $row->amount_to_bill > 0;
        }
        return in_array($row->action, $filterActions[$searchAction], true);
    }));
}

// Sections of the list, in the order of the money : one per next action, the rows keep their order inside
$actionStyles = [
    ReedcrmTicketMoney::ACTION_RELAUNCH          => ['css' => 'bad', 'icon' => 'fa-bell'],
    ReedcrmTicketMoney::SECTION_RESERVE          => ['css' => 'warn', 'icon' => 'fa-hand-holding-usd'],
    ReedcrmTicketMoney::ACTION_BILL              => ['css' => 'good', 'icon' => 'fa-file-invoice-dollar'],
    ReedcrmTicketMoney::ACTION_QUOTE             => ['css' => 'info', 'icon' => 'fa-file-signature'],
    ReedcrmTicketMoney::ACTION_ATTACH_THIRDPARTY => ['css' => 'warn', 'icon' => 'fa-building'],
    ReedcrmTicketMoney::ACTION_ATTACH_PROJECT    => ['css' => 'warn', 'icon' => 'fa-project-diagram'],
    ReedcrmTicketMoney::ACTION_LOG_TIME          => ['css' => 'info', 'icon' => 'fa-stopwatch'],
    ReedcrmTicketMoney::ACTION_CLOSE             => ['css' => 'mute', 'icon' => 'fa-archive'],
    ReedcrmTicketMoney::ACTION_NONE              => ['css' => 'mute', 'icon' => 'fa-check'],
];
$groups     = array_fill_keys(array_keys($actionStyles), []);
$groupStats = [];
foreach ($rows as $row) {
    // A ticket under reserve keeps its own next action, but waits in its section while the customer owes money
    $groups[$money->isInReserve($row) ? ReedcrmTicketMoney::SECTION_RESERVE : $row->action][] = $row;
}
$groups = array_filter($groups);
foreach ($groups as $action => $groupRows) {
    $groupStats[$action] = ['nb' => count($groupRows), 'overdue' => 0.0, 'socs' => [], 'to_bill' => 0.0];
    foreach ($groupRows as $row) {
        $groupStats[$action]['to_bill'] += $row->amount_to_bill;
        $society = $row->fk_soc > 0 ? $money->getSociety($row->fk_soc) : null;
        // The overdue amount is the customer's : counted once, however many tickets it has in the section
        if (in_array($action, [ReedcrmTicketMoney::ACTION_RELAUNCH, ReedcrmTicketMoney::SECTION_RESERVE], true) && $society !== null && !isset($groupStats[$action]['socs'][$row->fk_soc])) {
            $groupStats[$action]['socs'][$row->fk_soc] = 1;
            $groupStats[$action]['overdue']            += $society->overdue;
        }
    }
}

$userNames = [];
$userName  = function (int $userId) use ($db, $langs, &$userNames): string {
    if ($userId <= 0) {
        return '';
    }
    if (!isset($userNames[$userId])) {
        $tmpUser = new User($db);
        $userNames[$userId] = $tmpUser->fetch($userId) > 0 ? $tmpUser->getFullName($langs) : '';
    }
    return $userNames[$userId];
};

/*
 * View
 */

$title   = $langs->trans('TicketMoneyTitle');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

$now      = dol_now();
$currency = $conf->currency;

$morehtmlright  = '<a class="butAction" href="' . DOL_URL_ROOT . '/ticket/card.php?action=create&mode=init&mainmenu=ticket">' . $langs->trans('NewTicket') . '</a>';
$morehtmlright .= '<a class="butAction" href="' . dol_buildpath('/reedcrm/view/ticket_tech.php', 1) . '?mainmenu=ticket&leftmenu=tickettech">' . $langs->trans('TicketTechMenu') . '</a>';
$morehtmlright .= '<a class="butActionRefused classfortooltip rtm-native-list" href="' . DOL_URL_ROOT . '/ticket/list.php?search_fk_statut=openall&mainmenu=ticket">' . $langs->trans('TicketMoneyFullList') . '</a>';
print load_fiche_titre($title, $morehtmlright, 'ticket');

// Styles : css/scss/pages/_ticket-money.scss
print '<div class="rtm-intro">' . $langs->trans('TicketMoneyIntro') . '</div>';

if (!ReedcrmTicketMoney::isTimePerTicket()) {
    print info_admin($langs->trans('TicketMoneyNeedsTimePerTicket'), 0, 0, 'warning');
}
if (empty($money->getProduct())) {
    print info_admin($langs->trans('TicketMoneyNoProduct', getDolGlobalString('REEDCRM_TICKET_MONEY_PRODUCT_REF', 'AT-Technique-1H')), 0, 0, 'warning');
}

// Tiles : each one is a filter
$tiles = [
    ReedcrmTicketMoney::ACTION_BILL     => ['css' => 'good', 'value' => price($summary[ReedcrmTicketMoney::ACTION_BILL]['amount'], 0, $langs, 1, 0, 0, $currency), 'sub' => $langs->trans('TicketMoneyTilesTickets', $summary[ReedcrmTicketMoney::ACTION_BILL]['nb'])],
    ReedcrmTicketMoney::ACTION_RELAUNCH => ['css' => 'bad', 'value' => price($summary[ReedcrmTicketMoney::ACTION_RELAUNCH]['amount'], 0, $langs, 1, 0, 0, $currency), 'sub' => $langs->trans('TicketMoneyTilesCustomers', $summary[ReedcrmTicketMoney::ACTION_RELAUNCH]['nb'])],
    ReedcrmTicketMoney::ACTION_QUOTE    => ['css' => 'info', 'value' => $summary[ReedcrmTicketMoney::ACTION_QUOTE]['nb'], 'sub' => $langs->trans('TicketMoneyTileQuoteSub')],
    'unbillable'                        => ['css' => 'warn', 'value' => $summary['unbillable']['nb'], 'sub' => $langs->trans('TicketMoneyTileUnbillableSub')],
    ReedcrmTicketMoney::ACTION_CLOSE    => ['css' => 'mute', 'value' => $summary[ReedcrmTicketMoney::ACTION_CLOSE]['nb'], 'sub' => $langs->trans('TicketMoneyTileCloseSub', getDolGlobalInt('REEDCRM_TICKET_MONEY_DORMANT_DAYS', 90))],
];
$baseParams = ($searchUser > 0 ? '&search_user=' . $searchUser : '') . ($searchText !== '' ? '&search_text=' . urlencode($searchText) : '');
print '<div class="rtm-tiles">';
foreach ($tiles as $key => $tile) {
    $active = $searchAction === $key;
    $href   = $_SERVER['PHP_SELF'] . '?' . ltrim(($active ? '' : '&search_action=' . $key) . $baseParams, '&');
    print '<a class="rtm-tile ' . $tile['css'] . ($active ? ' active' : '') . '" href="' . dol_escape_htmltag($href) . '">';
    print '<span class="k">' . $langs->trans('TicketMoneyTile_' . $key) . '</span>';
    print '<span class="v">' . $tile['value'] . '</span>';
    print '<span class="a">' . $tile['sub'] . '</span>';
    print '</a>';
}
print '</div>';

// Number of tickets per next action, each one jumps to its section
if (!empty($groups)) {
    print '<div class="rtm-groups-nav">';
    foreach ($groupStats as $action => $stats) {
        print '<a class="rtm-chip ' . $actionStyles[$action]['css'] . '" href="#rtm-group-' . $action . '">';
        print '<i class="fas ' . $actionStyles[$action]['icon'] . ' paddingright"></i>' . $langs->trans('TicketMoneyGroup_' . $action);
        print ' <span class="rtm-chip-count">' . $stats['nb'] . '</span>';
        print '</a>';
    }
    print '</div>';
}

// Filters
print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" class="rtm-filters">';
print '<input type="hidden" name="search_action" value="' . dol_escape_htmltag($searchAction) . '">';
print '<div class="rtm-switch">';
print '<a class="' . ($searchUser <= 0 ? 'on' : '') . '" href="' . dol_escape_htmltag($_SERVER['PHP_SELF'] . '?' . ltrim(($searchAction ? '&search_action=' . $searchAction : '') . ($searchText !== '' ? '&search_text=' . urlencode($searchText) : ''), '&')) . '">' . $langs->trans('TicketMoneyEverybody') . '</a>';
print '<a class="' . ($searchUser === (int) $user->id ? 'on' : '') . '" href="' . dol_escape_htmltag($_SERVER['PHP_SELF'] . '?search_user=' . $user->id . ($searchAction ? '&search_action=' . $searchAction : '') . ($searchText !== '' ? '&search_text=' . urlencode($searchText) : '')) . '">' . $langs->trans('TicketMoneyMine') . '</a>';
print '</div>';
print '<div>' . $form->select_dolusers($searchUser > 0 ? $searchUser : '', 'search_user', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'minwidth150') . '</div>';
print '<div><input type="text" name="search_text" class="minwidth200" placeholder="' . dol_escape_htmltag($langs->trans('TicketMoneySearchPlaceholder')) . '" value="' . dol_escape_htmltag($searchText) . '"></div>';
print '<div><button type="submit" class="button smallpaddingimp"><i class="fas fa-search paddingright"></i>' . $langs->trans('Search') . '</button> ';
print '<a class="button button-cancel smallpaddingimp" href="' . $_SERVER['PHP_SELF'] . '">' . $langs->trans('Reset') . '</a></div>';
print '</form>';

// List
print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?' . ltrim($listParams, '&') . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="massaction" value="reedcrm_bill">';

print '<div class="div-table-responsive"><table class="tagtable liste centpercent rtm-table">';
print '<tr class="liste_titre">';
print '<th class="center width20"></th>';
print '<th>' . $langs->trans('Ticket') . '</th>';
print '<th>' . $langs->trans('Customer') . '</th>';
print '<th>' . $langs->trans('TicketMoneyCoverage') . '</th>';
print '<th class="right">' . $langs->trans('TicketMoneyTime') . '</th>';
print '<th class="right">' . $langs->trans('TicketMoneyToBill') . '</th>';
print '<th class="right">' . $langs->trans('TicketMoneyNextAction') . '</th>';
print '</tr>';

if (empty($rows)) {
    print '<tr class="oddeven"><td colspan="7" class="center opacitymedium">' . $langs->trans('TicketMoneyNone') . '</td></tr>';
}

$societyStatic = new Societe($db);
$totalToBill   = 0.0;
$billableShown = 0;
foreach ($groups as $action => $groupRows) {
    // Section header : what to do, how many tickets, how much money
    $groupStyle = $actionStyles[$action];
    print '<tr class="rtm-group ' . $groupStyle['css'] . '" id="rtm-group-' . $action . '"><td colspan="7">';
    print '<i class="fas ' . $groupStyle['icon'] . ' paddingright"></i><span class="rtm-group-label">' . $langs->trans('TicketMoneyGroup_' . $action) . '</span>';
    print '<span class="rtm-group-count">' . $langs->trans('TicketMoneyGroupCount', $groupStats[$action]['nb']) . '</span>';
    if ($groupStats[$action]['overdue'] > 0) {
        print '<span class="rtm-group-money bad">' . $langs->trans('TicketMoneyGroupOverdue', price($groupStats[$action]['overdue'], 0, $langs, 1, 0, 0, $currency), count($groupStats[$action]['socs'])) . '</span>';
    }
    if ($groupStats[$action]['to_bill'] > 0) {
        print '<span class="rtm-group-money good">' . $langs->trans('TicketMoneyGroupToBill', price($groupStats[$action]['to_bill'], 0, $langs, 1, 0, 0, $currency)) . '</span>';
    }
    print '</td></tr>';

    foreach ($groupRows as $row) {
        $society  = $row->fk_soc > 0 ? $money->getSociety($row->fk_soc) : null;
        $cardUrl  = DOL_URL_ROOT . '/ticket/card.php?id=' . $row->id;
        $ageDays  = (int) floor(($now - $row->datec) / 86400);
        $lastMove = max($row->datec, $row->last_activity);

        print '<tr class="oddeven rtm-row rtm-action-' . $row->action . '">';

        // Selection : only a ticket with time to bill goes in the mass billing
        print '<td class="center">';
        if ($row->amount_to_bill > 0 && $permissiontobill) {
            print '<input type="checkbox" class="flat checkforselect" name="toselect[]" value="' . $row->id . '">';
            $billableShown++;
        }
        print '</td>';

        // Ticket
        print '<td class="rtm-col-ticket">';
        print '<a class="rtm-ref" href="' . $cardUrl . '">' . img_picto('', 'ticket', 'class="pictofixedwidth"') . dol_escape_htmltag($row->ref) . '</a>';
        $priority = reedcrm_ticket_money_priority_badge($row, true);
        if ($priority !== '') {
            print ' ' . $priority;
        }
        if ($row->type_code === ReedcrmTicketMoney::COMMERCIAL_TYPE) {
            print ' <span class="rtm-badge info">' . $langs->trans('TicketMoneyCommercial') . '</span>';
        }
        if (!$row->is_open) {
            print ' <span class="rtm-badge mute">' . $langs->trans('TicketMoneyClosed') . '</span>';
        }
        print '<div class="rtm-subject" title="' . dol_escape_htmltag($row->subject) . '">' . dol_escape_htmltag(dol_trunc($row->subject, 70)) . '</div>';
        $project = reedcrm_ticket_money_project_link($row);
        if ($project !== '') {
            print '<div class="rtm-line rtm-meta">' . $project . '</div>';
        }
        print '<div class="rtm-meta">' . $langs->trans('TicketMoneyAge', $ageDays);
        if ($lastMove < $now - 30 * 86400) {
            print ' · ' . $langs->trans('TicketMoneyIdle', (int) floor(($now - $lastMove) / 86400));
        }
        $assignee = $userName($row->fk_user_assign);
        print ' · ' . ($assignee !== '' ? dol_escape_htmltag($assignee) : '<span class="rtm-warn">' . $langs->trans('TicketMoneyNobody') . '</span>');
        print '</div>';
        print '</td>';

        // Customer and its payment state
        print '<td class="rtm-col">';
        if ($society === null) {
            print '<span class="rtm-badge warn">' . $langs->trans('TicketMoneyNoThirdparty') . '</span>';
        } else {
            $societyStatic->id     = $row->fk_soc;
            $societyStatic->name   = $row->soc_name;
            $societyStatic->status = $row->soc_status;
            print $societyStatic->getNomUrl(1, '', 28);
            print '<div class="rtm-line">';
            if ($society->overdue > 0) {
                print '<span class="rtm-badge bad classfortooltip" title="' . dol_escape_htmltag($langs->trans('TicketMoneyOverdueTooltip', $society->overdue_count, dol_print_date($society->overdue_oldest, 'day'))) . '">';
                print $langs->trans('TicketMoneyOverdue', price($society->overdue, 0, $langs, 1, 0, 0, $currency)) . '</span>';
            } else {
                print '<span class="rtm-badge good">' . $langs->trans('TicketMoneyUpToDate') . '</span>';
            }
            print '</div>';
        }
        print '</td>';

        // Coverage : credit of the subscription, frozen, or none
        print '<td class="rtm-col">';
        if ($row->billing === ReedcrmTicketMoney::BILLING_FREE) {
            print '<span class="rtm-badge mute classfortooltip" title="' . dol_escape_htmltag($row->billing_note) . '">' . $langs->trans('TicketMoneyBillingFree') . '</span>';
        } elseif ($row->billing === ReedcrmTicketMoney::BILLING_BILLABLE) {
            print '<span class="rtm-badge info">' . $langs->trans('TicketMoneyBillingBillable') . '</span>';
        } elseif ($society === null) {
            print '<span class="opacitymedium">—</span>';
        } elseif ($society->frozen) {
            print '<span class="rtm-badge bad">' . $langs->trans('TicketMoneyCreditFrozen') . '</span>';
            if ($money->isInReserve($row)) {
                print '<div class="rtm-line"><span class="rtm-badge warn">' . $langs->trans('TicketMoneyReserveUntil', dol_print_date($row->reserve_until, 'day')) . '</span></div>';
            }
        } elseif ($society->period !== null) {
            $left = max(0, $society->period->credit - $society->period->used);
            $pct  = $society->period->credit > 0 ? min(100, (int) round($society->period->used / $society->period->credit * 100)) : 100;
            print '<div class="rtm-credit"><span class="rtm-credit-bar"><span style="width:' . $pct . '%"></span></span>';
            print '<span>' . $langs->trans('TicketMoneyCreditLeft', ReedcrmTicketMoney::formatDuration($left), ReedcrmTicketMoney::formatDuration($society->period->credit)) . '</span></div>';
            print '<div class="rtm-meta">' . dol_escape_htmltag($society->period->product_ref) . ' · ' . $langs->trans('TicketMoneyUntil', dol_print_date($society->period->end, 'day')) . '</div>';
        } else {
            print '<span class="rtm-badge warn">' . $langs->trans('TicketMoneyNoSubscription') . '</span>';
        }
        print '</td>';

        // Time
        print '<td class="right rtm-col-time">';
        if ($row->time_total > 0) {
            print '<strong>' . ReedcrmTicketMoney::formatDuration($row->time_total) . '</strong>';
            $details = [];
            if ($row->time_included > 0) {
                $details[] = $langs->trans('TicketMoneyIncluded', ReedcrmTicketMoney::formatDuration($row->time_included));
            }
            if ($row->time_invoiced > 0) {
                $details[] = $langs->trans('TicketMoneyInvoiced', ReedcrmTicketMoney::formatDuration($row->time_invoiced));
            }
            if ($row->time_free > 0) {
                $details[] = $langs->trans('TicketMoneyFree', ReedcrmTicketMoney::formatDuration($row->time_free));
            }
            if (!empty($details)) {
                print '<div class="rtm-meta">' . implode(' · ', $details) . '</div>';
            }
        } else {
            print '<span class="opacitymedium">—</span>';
        }
        print '</td>';

        // Amount to bill
        print '<td class="right nowraponall">';
        if ($row->amount_to_bill > 0) {
            $totalToBill += $row->amount_to_bill;
            print '<span class="rtm-amount">' . price($row->amount_to_bill, 0, $langs, 1, -1, -1, $currency) . '</span>';
            print '<div class="rtm-meta">' . ReedcrmTicketMoney::formatDuration(ReedcrmTicketMoney::roundBillable($row->time_to_bill)) . '</div>';
        } else {
            print '<span class="opacitymedium">—</span>';
        }
        print '</td>';

        // Next action : one button
        print '<td class="right nowraponall">';
        print reedcrm_ticket_money_action_button($money, $row, $selfUrl, ['bill' => $permissiontobill, 'quote' => $permissiontoquote]);
        // Customer late in paying : chase, or handle the ticket anyway under reserve (and take it back)
        if ($permissiontoreserve && $row->action === ReedcrmTicketMoney::ACTION_RELAUNCH) {
            print '<div class="rtm-line"><a class="rtm-action warn rtm-action-small" href="' . dol_escape_htmltag($selfUrl . '&action=reserve&id=' . $row->id . '&token=' . newToken()) . '" title="' . dol_escape_htmltag($langs->trans('TicketMoneyActionReserveHelp', ReedcrmTicketMoney::getReserveDays())) . '"><i class="fas fa-hand-holding-usd paddingright"></i>' . $langs->trans('TicketMoneyActionReserve') . '</a></div>';
        } elseif ($permissiontoreserve && $money->isInReserve($row)) {
            print '<div class="rtm-line"><a class="rtm-action mute rtm-action-small" href="' . dol_escape_htmltag($selfUrl . '&action=unreserve&id=' . $row->id . '&token=' . newToken()) . '" title="' . dol_escape_htmltag($langs->trans('TicketMoneyActionUnreserveHelp')) . '"><i class="fas fa-undo paddingright"></i>' . $langs->trans('TicketMoneyActionUnreserve') . '</a></div>';
        }
        print '</td>';

        print '</tr>';
    }
}

if (!empty($rows)) {
    print '<tr class="liste_total"><td colspan="5">' . $langs->trans('TicketMoneyTotalToBill', count($rows)) . '</td>';
    print '<td class="right nowraponall">' . price($totalToBill, 0, $langs, 1, -1, -1, $currency) . '</td><td></td></tr>';
}
print '</table></div>';

if ($billableShown > 0) {
    print '<div class="rtm-massaction"><button type="submit" class="button" name="confirmmassaction" value="1"><i class="fas fa-file-invoice-dollar paddingright"></i>' . $langs->trans('TicketMoneyMassBill') . '</button>';
    print ' <span class="opacitymedium">' . $langs->trans('TicketMoneyMassBillHelp') . '</span></div>';
}
print '</form>';

$product = $money->getProduct();
print '<div class="rtm-legend">' . $langs->trans(
    'TicketMoneyLegend',
    implode(', ', ReedcrmTicketMoney::getSubscriptionProductRefs()),
    !empty($product) ? dol_escape_htmltag($product['ref']) : '-',
    !empty($product) ? price($product['price'], 0, $langs, 1, 0, 0, $currency) : '-',
    getDolGlobalInt('REEDCRM_TICKET_MONEY_ROUNDING_MINUTES', 15)
) . '</div>';

// End of page
llxFooter();
$db->close();

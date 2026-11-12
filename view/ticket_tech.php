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
 * \file    view/ticket_tech.php
 * \ingroup reedcrm
 * \brief   Tech view of the tickets : what to do first (by priority), and what can be billed at the end of the month,
 *          per technician (the one who logged the time) and per customer.
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
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../class/reedcrmticketmoney.class.php';
require_once __DIR__ . '/../lib/reedcrm_ticket_money.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['ticket', 'bills', 'projects']);

// Get parameters
$action = GETPOST('action', 'aZ09');
$socid  = GETPOSTINT('socid');
$month  = GETPOST('month', 'alpha');
// The technician is the one who logged the time : me by default, everybody with -1
$searchTech = GETPOSTISSET('search_tech') ? GETPOSTINT('search_tech') : (int) $user->id;

if (!preg_match('/^(\d{4})-(\d{2})$/', (string) $month, $matches) || (int) $matches[2] < 1 || (int) $matches[2] > 12) {
    $month   = dol_print_date(dol_now(), '%Y-%m');
    $matches = explode('-', '-' . $month);
}
$year       = (int) $matches[1];
$monthNum   = (int) $matches[2];
$monthStart = dol_get_first_day($year, $monthNum);
$monthEnd   = dol_get_last_day($year, $monthNum);

// Initialize technical objects
$form  = new Form($db);
$money = new ReedcrmTicketMoney($db);

$hookmanager->initHooks(['reedcrmtickettech']);

// Security check
$permissiontoread  = $user->hasRight('reedcrm', 'read') && $user->hasRight('ticket', 'read');
$permissiontobill  = $user->hasRight('facture', 'creer');
$permissiontoquote = $user->hasRight('propal', 'creer');
saturne_check_access($permissiontoread);

$techUrl = $_SERVER['PHP_SELF'] . '?search_tech=' . $searchTech . '&month=' . $month;
$listUrl = dol_buildpath('/reedcrm/view/ticket_money.php', 1);

/*
 * Data
 */

if ($money->load(['time_since' => $monthStart]) < 0) {
    setEventMessages($money->error, $money->errors, 'errors');
}
$isTech = function (int $fkUser) use ($searchTech): bool {
    return $searchTech <= 0 || $fkUser === $searchTech;
};

// Per technician : time logged in the month, part included in a subscription, free, and still to bill up to the end of the month
$techStats = [];
foreach ($money->getTimeEntries() as $ticketId => $entries) {
    $ticket = $money->getTicket((int) $ticketId);
    if ($ticket === null) {
        continue;
    }
    foreach ($entries as $entry) {
        if (!$isTech($entry->fk_user)) {
            continue;
        }
        if (!isset($techStats[$entry->fk_user])) {
            $techStats[$entry->fk_user] = ['logged' => 0, 'included' => 0, 'free' => 0, 'to_bill' => 0, 'amount' => 0.0];
        }
        $stats = &$techStats[$entry->fk_user];
        if ($entry->date >= $monthStart && $entry->date <= $monthEnd) {
            $stats['logged']   += $entry->duration;
            $stats['included'] += $entry->included;
            if ($ticket->billing === ReedcrmTicketMoney::BILLING_FREE) {
                $stats['free'] += $entry->duration;
            }
        }
        if ($entry->invoice_id <= 0 && $entry->to_bill > 0 && $entry->date <= $monthEnd && $ticket->fk_soc > 0) {
            $stats['to_bill'] += $entry->to_bill;
            $stats['amount']  += $entry->to_bill / 3600 * $money->getHourlyRate($ticket->fk_soc);
        }
        unset($stats);
    }
}

// Per customer : tickets with time to bill up to the end of the month (the technician's tickets only when one is chosen)
$clients = [];
foreach ($money->getTargets() as $row) {
    if ($row->fk_soc <= 0) {
        continue;
    }
    $seconds = $money->getSecondsToBill($row->id, $monthEnd);
    if ($seconds <= 0) {
        continue;
    }
    $mine = 0;
    foreach ($money->getEntriesToBill($row->id, $monthEnd) as $entry) {
        if ($isTech($entry->fk_user)) {
            $mine += $entry->to_bill;
        }
    }
    if ($mine <= 0) {
        continue;
    }
    $billed = ReedcrmTicketMoney::roundBillable($seconds);
    if (!isset($clients[$row->fk_soc])) {
        $clients[$row->fk_soc] = ['name' => $row->soc_name, 'status' => $row->soc_status, 'tickets' => [], 'seconds' => 0, 'amount' => 0.0];
    }
    $amount = round($billed / 3600 * $money->getHourlyRate($row->fk_soc), 2);
    $clients[$row->fk_soc]['tickets'][] = ['row' => $row, 'seconds' => $billed, 'mine' => $mine, 'amount' => $amount];
    $clients[$row->fk_soc]['seconds'] += $billed;
    $clients[$row->fk_soc]['amount']  += $amount;
}
uasort($clients, function ($first, $second) {
    return $second['amount'] <=> $first['amount'];
});

// To do first : open tickets of the technician, the most urgent priority first, then the longest without news
$todo = array_values(array_filter($money->getTargets(), function ($row) use ($searchTech) {
    return $row->is_open && ($searchTech <= 0 || $row->fk_user_assign === $searchTech);
}));
usort($todo, function ($first, $second) {
    $firstMove  = max($first->datec, $first->last_activity);
    $secondMove = max($second->datec, $second->last_activity);
    return [ReedcrmTicketMoney::getPriorityRank($first->severity_code), $firstMove] <=> [ReedcrmTicketMoney::getPriorityRank($second->severity_code), $secondMove];
});

/*
 * Actions
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $money, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

// Bill a customer, or every customer shown, up to the end of the month : the time logged after it stays to bill
if (empty($resHook) && $action === 'bill_month' && $permissiontobill) {
    if (GETPOST('token', 'alpha') !== currentToken()) {
        accessforbidden('Bad value for CSRF token');
    }
    $ticketIds = [];
    foreach ($clients as $clientId => $client) {
        if ($socid > 0 && $clientId !== $socid) {
            continue;
        }
        foreach ($client['tickets'] as $line) {
            $ticketIds[] = $line['row']->id;
        }
    }
    $invoiceIds = !empty($ticketIds) ? $money->billTickets($user, $ticketIds, $monthEnd) : [];
    if (!empty($money->errors)) {
        setEventMessages('', $money->errors, 'errors');
    }
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
    header('Location: ' . $techUrl . '#rtm-tech-bill');
    exit;
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

$title   = $langs->trans('TicketTechTitle');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

$now        = dol_now();
$currency   = $conf->currency;
$monthLabel = dol_print_date($monthStart, '%B %Y');
$endLabel   = dol_print_date($monthEnd, 'day');

$morehtmlright = '<a class="butAction" href="' . dol_escape_htmltag($listUrl . '?mainmenu=ticket&leftmenu=ticketlist') . '">' . $langs->trans('TicketMoneyMenuList') . '</a>';
print load_fiche_titre($title, $morehtmlright, 'ticket');

// Styles : css/scss/pages/_ticket-money.scss
print '<div class="rtm-intro">' . $langs->trans('TicketTechIntro') . '</div>';

// Who and which month
$totals = ['logged' => 0, 'included' => 0, 'free' => 0, 'to_bill' => 0, 'amount' => 0.0];
foreach ($techStats as $stats) {
    foreach ($totals as $key => $value) {
        $totals[$key] += $stats[$key];
    }
}
print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '" class="rtm-filters">';
print '<div class="rtm-switch">';
print '<a class="' . ($searchTech === (int) $user->id ? 'on' : '') . '" href="' . dol_escape_htmltag($_SERVER['PHP_SELF'] . '?search_tech=' . $user->id . '&month=' . $month) . '">' . $langs->trans('TicketTechMine') . '</a>';
print '<a class="' . ($searchTech <= 0 ? 'on' : '') . '" href="' . dol_escape_htmltag($_SERVER['PHP_SELF'] . '?search_tech=-1&month=' . $month) . '">' . $langs->trans('TicketMoneyEverybody') . '</a>';
print '</div>';
print '<div>' . $form->select_dolusers($searchTech > 0 ? $searchTech : '', 'search_tech', 1, null, 0, '', '', '0', 0, 0, '', 0, '', 'minwidth150') . '</div>';
print '<div><input type="month" name="month" value="' . dol_escape_htmltag($month) . '"></div>';
print '<div><button type="submit" class="button smallpaddingimp"><i class="fas fa-search paddingright"></i>' . $langs->trans('Search') . '</button></div>';
print '</form>';

// Tiles : what to do, what to bill
$urgent = count(array_filter($todo, function ($row) {
    return ReedcrmTicketMoney::getPriorityRank($row->severity_code) <= 1;
}));
// What the buttons will bill : whole tickets, rounded per ticket ; the technician's own share is only a detail of it
$billTotal   = array_sum(array_column($clients, 'amount'));
$billSeconds = array_sum(array_column($clients, 'seconds'));
$billSub     = $langs->trans('TicketTechTileToBillSub', ReedcrmTicketMoney::formatDuration($billSeconds), count($clients));
if ($searchTech > 0) {
    $billSub .= '<br>' . $langs->trans('TicketTechTileToBillMine', price($totals['amount'], 0, $langs, 1, 0, 0, $currency));
}
print '<div class="rtm-tiles">';
print '<a class="rtm-tile bad" href="#rtm-tech-todo"><span class="k">' . $langs->trans('TicketTechTileTodo') . '</span><span class="v">' . count($todo) . '</span><span class="a">' . $langs->trans('TicketTechTileTodoSub', $urgent) . '</span></a>';
print '<a class="rtm-tile good" href="#rtm-tech-bill"><span class="k">' . $langs->trans('TicketTechTileToBill', $endLabel) . '</span><span class="v">' . price($billTotal, 0, $langs, 1, 0, 0, $currency) . '</span><span class="a">' . $billSub . '</span></a>';
print '<a class="rtm-tile info" href="#rtm-tech-bill"><span class="k">' . $langs->trans('TicketTechTileLogged', $monthLabel) . '</span><span class="v">' . ReedcrmTicketMoney::formatDuration($totals['logged']) . '</span><span class="a">' . $langs->trans('TicketTechTileLoggedSub', ReedcrmTicketMoney::formatDuration($totals['included']), ReedcrmTicketMoney::formatDuration($totals['free'])) . '</span></a>';
print '</div>';

// 1. To do first, by priority
print '<h3 class="rtm-tech-title" id="rtm-tech-todo"><i class="fas fa-tasks paddingright"></i>' . $langs->trans('TicketTechTodo') . ' <span class="rtm-group-count">' . $langs->trans('TicketMoneyGroupCount', count($todo)) . '</span></h3>';
print '<div class="div-table-responsive"><table class="tagtable liste centpercent rtm-table">';
print '<tr class="liste_titre"><th>' . $langs->trans('TicketTechPriority') . '</th><th>' . $langs->trans('Ticket') . '</th><th>' . $langs->trans('Customer') . '</th>';
print '<th class="right">' . $langs->trans('TicketMoneyTime') . '</th><th class="right">' . $langs->trans('TicketMoneyNextAction') . '</th></tr>';
if (empty($todo)) {
    print '<tr class="oddeven"><td colspan="5" class="center opacitymedium">' . $langs->trans('TicketTechNothingTodo') . '</td></tr>';
}
$societyStatic = new Societe($db);
foreach ($todo as $row) {
    $society  = $row->fk_soc > 0 ? $money->getSociety($row->fk_soc) : null;
    $lastMove = max($row->datec, $row->last_activity);

    print '<tr class="oddeven rtm-row">';
    print '<td class="nowraponall">' . reedcrm_ticket_money_priority_badge($row) . '</td>';

    print '<td class="rtm-col-ticket">';
    print '<a class="rtm-ref" href="' . DOL_URL_ROOT . '/ticket/card.php?id=' . $row->id . '">' . img_picto('', 'ticket', 'class="pictofixedwidth"') . dol_escape_htmltag($row->ref) . '</a>';
    print '<div class="rtm-subject" title="' . dol_escape_htmltag($row->subject) . '">' . dol_escape_htmltag(dol_trunc($row->subject, 70)) . '</div>';
    $project = reedcrm_ticket_money_project_link($row);
    if ($project !== '') {
        print '<div class="rtm-line rtm-meta">' . $project . '</div>';
    }
    print '<div class="rtm-meta">' . $langs->trans('TicketMoneyAge', (int) floor(($now - $row->datec) / 86400));
    if ($lastMove < $now - 7 * 86400) {
        print ' · <span class="rtm-warn">' . $langs->trans('TicketMoneyIdle', (int) floor(($now - $lastMove) / 86400)) . '</span>';
    }
    if ($searchTech <= 0) {
        $assignee = $userName($row->fk_user_assign);
        print ' · ' . ($assignee !== '' ? dol_escape_htmltag($assignee) : '<span class="rtm-warn">' . $langs->trans('TicketMoneyNobody') . '</span>');
    }
    print '</div></td>';

    print '<td class="rtm-col">';
    if ($society === null) {
        print '<span class="rtm-badge warn">' . $langs->trans('TicketMoneyNoThirdparty') . '</span>';
    } else {
        $societyStatic->id     = $row->fk_soc;
        $societyStatic->name   = $row->soc_name;
        $societyStatic->status = $row->soc_status;
        print $societyStatic->getNomUrl(1, '', 28);
        if ($society->overdue > 0) {
            print '<div class="rtm-line"><span class="rtm-badge bad">' . $langs->trans('TicketMoneyOverdue', price($society->overdue, 0, $langs, 1, 0, 0, $currency)) . '</span></div>';
        }
    }
    print '</td>';

    print '<td class="right rtm-col-time">' . ($row->time_total > 0 ? '<strong>' . ReedcrmTicketMoney::formatDuration($row->time_total) . '</strong>' : '<span class="opacitymedium">—</span>') . '</td>';
    print '<td class="right nowraponall">' . reedcrm_ticket_money_action_button($money, $row, $listUrl, ['bill' => $permissiontobill, 'quote' => $permissiontoquote]) . '</td>';
    print '</tr>';
}
print '</table></div>';

// 2. To bill at the end of the month
print '<h3 class="rtm-tech-title" id="rtm-tech-bill"><i class="fas fa-file-invoice-dollar paddingright"></i>' . $langs->trans('TicketTechToBill', $endLabel) . ' <span class="rtm-group-money good">' . price($billTotal, 0, $langs, 1, 0, 0, $currency) . '</span></h3>';

// Per technician, when the whole team is shown
if ($searchTech <= 0 && count($techStats) > 0) {
    print '<div class="div-table-responsive"><table class="tagtable liste centpercent rtm-table rtm-tech-table">';
    print '<tr class="liste_titre"><th>' . $langs->trans('TicketTechTechnician') . '</th><th class="right">' . $langs->trans('TicketTechTileLogged', $monthLabel) . '</th>';
    print '<th class="right">' . $langs->trans('TicketTechIncluded') . '</th><th class="right">' . $langs->trans('TicketTechFree') . '</th>';
    print '<th class="right">' . $langs->trans('TicketTechTimeToBill') . '</th><th class="right classfortooltip" title="' . dol_escape_htmltag($langs->trans('TicketTechShareHelp')) . '">' . $langs->trans('TicketTechShare') . '</th></tr>';
    uasort($techStats, function ($first, $second) {
        return $second['amount'] <=> $first['amount'];
    });
    foreach ($techStats as $techId => $stats) {
        print '<tr class="oddeven">';
        print '<td><a href="' . dol_escape_htmltag($_SERVER['PHP_SELF'] . '?search_tech=' . $techId . '&month=' . $month) . '">' . dol_escape_htmltag($userName((int) $techId) ?: '?') . '</a></td>';
        print '<td class="right">' . ReedcrmTicketMoney::formatDuration($stats['logged']) . '</td>';
        print '<td class="right">' . ReedcrmTicketMoney::formatDuration($stats['included']) . '</td>';
        print '<td class="right">' . ReedcrmTicketMoney::formatDuration($stats['free']) . '</td>';
        print '<td class="right">' . ReedcrmTicketMoney::formatDuration($stats['to_bill']) . '</td>';
        print '<td class="right"><span class="rtm-amount">' . price($stats['amount'], 0, $langs, 1, 0, 0, $currency) . '</span></td>';
        print '</tr>';
    }
    print '</table></div>';
}

// Per customer, with the button that bills it up to the end of the month
print '<div class="div-table-responsive"><table class="tagtable liste centpercent rtm-table">';
print '<tr class="liste_titre"><th>' . $langs->trans('Customer') . '</th><th>' . $langs->trans('TicketTechTickets') . '</th>';
print '<th class="right">' . $langs->trans('TicketTechTimeToBill') . '</th><th class="right">' . $langs->trans('TicketTechAmountToBill') . '</th><th class="right"></th></tr>';
if (empty($clients)) {
    print '<tr class="oddeven"><td colspan="5" class="center opacitymedium">' . $langs->trans('TicketTechNothingToBill', $endLabel) . '</td></tr>';
}
foreach ($clients as $clientId => $client) {
    $society = $money->getSociety((int) $clientId);

    print '<tr class="oddeven rtm-row">';
    print '<td class="rtm-col">';
    $societyStatic->id     = (int) $clientId;
    $societyStatic->name   = $client['name'];
    $societyStatic->status = $client['status'];
    print $societyStatic->getNomUrl(1, '', 28);
    print '<div class="rtm-line">';
    if ($society !== null && $society->overdue > 0) {
        print '<span class="rtm-badge bad">' . $langs->trans('TicketMoneyOverdue', price($society->overdue, 0, $langs, 1, 0, 0, $currency)) . '</span>';
    } else {
        print '<span class="rtm-badge good">' . $langs->trans('TicketMoneyUpToDate') . '</span>';
    }
    print '</div></td>';

    print '<td class="rtm-col-ticket">';
    foreach ($client['tickets'] as $line) {
        $row = $line['row'];
        print '<div class="rtm-tech-ticket">';
        print '<a class="rtm-ref" href="' . DOL_URL_ROOT . '/ticket/card.php?id=' . $row->id . '">' . dol_escape_htmltag($row->ref) . '</a> ';
        print '<span class="rtm-meta">' . dol_escape_htmltag(dol_trunc($row->subject, 45)) . ' · ' . ReedcrmTicketMoney::formatDuration($line['seconds']);
        if ($searchTech > 0 && $line['mine'] < $line['seconds']) {
            print ' (' . $langs->trans('TicketTechMine') . ' ' . ReedcrmTicketMoney::formatDuration($line['mine']) . ')';
        }
        print '</span>';
        $project = reedcrm_ticket_money_project_link($row);
        if ($project !== '') {
            print '<div class="rtm-meta">' . $project . '</div>';
        }
        print '</div>';
    }
    print '</td>';

    print '<td class="right nowraponall"><strong>' . ReedcrmTicketMoney::formatDuration($client['seconds']) . '</strong></td>';
    print '<td class="right nowraponall"><span class="rtm-amount">' . price($client['amount'], 0, $langs, 1, -1, -1, $currency) . '</span></td>';
    print '<td class="right nowraponall">';
    if ($permissiontobill) {
        print '<a class="rtm-action good" href="' . dol_escape_htmltag($techUrl . '&action=bill_month&socid=' . $clientId . '&token=' . newToken()) . '"><i class="fas fa-file-invoice-dollar paddingright"></i>' . $langs->trans('TicketTechBillUntil', $endLabel) . '</a>';
    }
    print '</td>';
    print '</tr>';
}
if (count($clients) > 1) {
    print '<tr class="liste_total"><td colspan="2">' . $langs->trans('Total') . '</td>';
    print '<td class="right nowraponall">' . ReedcrmTicketMoney::formatDuration(array_sum(array_column($clients, 'seconds'))) . '</td>';
    print '<td class="right nowraponall">' . price(array_sum(array_column($clients, 'amount')), 0, $langs, 1, -1, -1, $currency) . '</td>';
    print '<td class="right nowraponall">';
    if ($permissiontobill) {
        print '<a class="rtm-action good" href="' . dol_escape_htmltag($techUrl . '&action=bill_month&token=' . newToken()) . '" onclick="return confirm(\'' . dol_escape_js($langs->transnoentities('TicketTechBillAllConfirm', count($clients), $endLabel)) . '\')"><i class="fas fa-file-invoice-dollar paddingright"></i>' . $langs->trans('TicketTechBillAll') . '</a>';
    }
    print '</td></tr>';
}
print '</table></div>';

print '<div class="rtm-legend">' . $langs->trans('TicketTechLegend', $endLabel) . '</div>';

// End of page
llxFooter();
$db->close();

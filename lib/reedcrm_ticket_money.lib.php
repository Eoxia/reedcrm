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
 * \file    lib/reedcrm_ticket_money.lib.php
 * \ingroup reedcrm
 * \brief   Pieces of a ticket line shared by the money list (view/ticket_money.php) and the tech view
 *          (view/ticket_tech.php) : next action button, priority badge, project link.
 */

require_once __DIR__ . '/../class/reedcrmticketmoney.class.php';

/**
 * Button of the next action of a ticket
 *
 * @param  ReedcrmTicketMoney  $money   Money figures, loaded
 * @param  stdClass            $row     Ticket with its figures
 * @param  string              $listUrl URL of the money list, with its filters, that bills and makes the quotes
 * @param  array<string,bool>  $perms   Rights : bill, quote
 * @return string                       HTML
 */
function reedcrm_ticket_money_action_button(ReedcrmTicketMoney $money, stdClass $row, string $listUrl, array $perms): string
{
    global $conf, $langs;

    $society  = $row->fk_soc > 0 ? $money->getSociety($row->fk_soc) : null;
    $cardUrl  = DOL_URL_ROOT . '/ticket/card.php?id=' . $row->id;
    $currency = $conf->currency;
    $sep      = strpos($listUrl, '?') === false ? '?' : '&';

    $button = ['label' => $langs->trans('TicketMoneyActionOpen'), 'url' => $cardUrl, 'css' => 'mute', 'icon' => 'fa-arrow-right'];
    switch ($row->action) {
        case ReedcrmTicketMoney::ACTION_ATTACH_THIRDPARTY:
            $button = ['label' => $langs->trans('TicketMoneyActionAttachThirdparty'), 'url' => DOL_URL_ROOT . '/ticket/card.php?track_id=' . urlencode($row->track_id) . '&action=editcustomer&token=' . newToken(), 'css' => 'warn', 'icon' => 'fa-building'];
            break;
        case ReedcrmTicketMoney::ACTION_RELAUNCH:
            $button = ['label' => $langs->trans('TicketMoneyActionRelaunch', price($society !== null ? $society->overdue : 0, 0, $langs, 1, 0, 0, $currency)), 'url' => DOL_URL_ROOT . '/compta/facture/list.php?socid=' . $row->fk_soc . '&search_status=1&search_option=late', 'css' => 'bad', 'icon' => 'fa-bell'];
            break;
        case ReedcrmTicketMoney::ACTION_BILL:
            if (!empty($perms['bill'])) {
                $button = ['label' => $langs->trans('TicketMoneyActionBill', price($row->amount_to_bill, 0, $langs, 1, 0, 0, $currency)), 'url' => $listUrl . $sep . 'action=bill&id=' . $row->id . '&token=' . newToken(), 'css' => 'good', 'icon' => 'fa-file-invoice-dollar'];
            }
            break;
        case ReedcrmTicketMoney::ACTION_QUOTE:
            if (!empty($perms['quote'])) {
                $button = ['label' => $langs->trans('TicketMoneyActionQuote'), 'url' => $listUrl . $sep . 'action=quote&id=' . $row->id . '&token=' . newToken(), 'css' => 'info', 'icon' => 'fa-file-signature'];
            }
            break;
        case ReedcrmTicketMoney::ACTION_ATTACH_PROJECT:
            $button = ['label' => $langs->trans('TicketMoneyActionAttachProject'), 'url' => DOL_URL_ROOT . '/ticket/card.php?id=' . $row->id . '&action=classify&token=' . newToken(), 'css' => 'warn', 'icon' => 'fa-project-diagram'];
            break;
        case ReedcrmTicketMoney::ACTION_LOG_TIME:
            $button = ['label' => $langs->trans('TicketMoneyActionLogTime'), 'url' => $cardUrl . '#reedcrm-ticket-money', 'css' => 'info', 'icon' => 'fa-stopwatch'];
            break;
        case ReedcrmTicketMoney::ACTION_CLOSE:
            $button = ['label' => $langs->trans('TicketMoneyActionClose'), 'url' => DOL_URL_ROOT . '/ticket/card.php?track_id=' . urlencode($row->track_id) . '&action=close&token=' . newToken(), 'css' => 'mute', 'icon' => 'fa-archive'];
            break;
    }

    return '<a class="rtm-action ' . $button['css'] . '" href="' . dol_escape_htmltag($button['url']) . '"><i class="fas ' . $button['icon'] . ' paddingright"></i>' . $button['label'] . '</a>';
}

/**
 * Badge of the priority of a ticket (its severity)
 *
 * @param  stdClass $row        Ticket with its figures
 * @param  bool     $urgentOnly True to show only the high and blocking ones (money list), false for every priority (tech view)
 * @return string               HTML, empty when nothing to show
 */
function reedcrm_ticket_money_priority_badge(stdClass $row, bool $urgentOnly = false): string
{
    global $langs;

    $code = $row->severity_code !== '' ? $row->severity_code : 'NORMAL';
    $rank = ReedcrmTicketMoney::getPriorityRank($code);
    if ($urgentOnly && $rank > 1) {
        return '';
    }
    $css = ['bad', 'warn', 'mute', 'light'][$rank] ?? 'mute';

    return '<span class="rtm-badge rtm-priority ' . $css . '">' . $langs->trans('TicketSeverityShort' . $code) . '</span>';
}

/**
 * Link to the project of a ticket : ref and title
 *
 * @param  stdClass $row Ticket with its figures
 * @return string        HTML, empty without project
 */
function reedcrm_ticket_money_project_link(stdClass $row): string
{
    if ($row->fk_project <= 0) {
        return '';
    }
    $label = $row->project_ref . ($row->project_title !== '' ? ' - ' . dol_trunc($row->project_title, 45) : '');

    return '<a class="rtm-project" href="' . DOL_URL_ROOT . '/projet/card.php?id=' . $row->fk_project . '" title="' . dol_escape_htmltag($row->project_ref . ' - ' . $row->project_title) . '">' . img_picto('', 'project', 'class="pictofixedwidth"') . dol_escape_htmltag($label) . '</a>';
}

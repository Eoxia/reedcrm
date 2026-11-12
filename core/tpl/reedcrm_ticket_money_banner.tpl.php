<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/reedcrm_ticket_money_banner.tpl.php
 * \ingroup reedcrm
 * \brief   Money banner on top of the ticket card : is the customer paying, what does its subscription cover, what
 *          this ticket cost and what is left to bill, with the buttons that turn it into money.
 *          Loaded by the formConfirm hook, printed between the tabs and the banner of the card.
 *
 * Variables expected:
 *   $moneyTicket      stdClass       Ticket with its figures (ReedcrmTicketMoney::getTicket())
 *   $moneySociety     stdClass|null  Customer with its figures, null without customer
 *   $moneyCardUrl     string         URL of the ticket card, without action
 *   $moneyListUrl     string         URL of the money list
 *   $moneyCanLogTime  bool           The user may log time and change the billing mode
 *   $moneyCanBill     bool           The user may create invoices
 *   $moneyCanQuote    bool           The user may create proposals
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

// reedcrm_asset_full_url() used by the stylesheet printed below: without a version, the browser keeps serving the previous release
require_once __DIR__ . '/../../lib/reedcrm_function.lib.php';

$currency = $conf->currency;
$token    = newToken();
// The native Dolibarr cards never load reedcrm.min.css, and the banner is printed long before the footer that does
?>
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(reedcrm_asset_full_url('/reedcrm/css/reedcrm.min.css')); ?>">
<div id="reedcrm-ticket-money" class="rtm-banner<?php echo ($moneySociety !== null && $moneySociety->overdue > 0) ? ' overdue' : ''; ?>">
    <div class="rtm-banner-cell">
        <span class="rtm-banner-label"><?php echo $langs->trans('Customer'); ?></span>
        <?php if ($moneySociety === null) : ?>
            <span class="rtm-badge warn"><?php echo $langs->trans('TicketMoneyNoThirdparty'); ?></span>
            <a class="rtm-action warn" href="<?php echo dol_escape_htmltag($moneyCardUrl . '&action=editcustomer&token=' . $token); ?>"><i class="fas fa-building paddingright"></i><?php echo $langs->trans('TicketMoneyActionAttachThirdparty'); ?></a>
        <?php elseif ($moneySociety->overdue > 0) : ?>
            <span class="rtm-badge bad"><?php echo $langs->trans('TicketMoneyOverdue', price($moneySociety->overdue, 0, $langs, 1, 0, 0, $currency)); ?></span>
            <span class="rtm-meta"><?php echo $langs->trans('TicketMoneyOverdueTooltip', $moneySociety->overdue_count, dol_print_date($moneySociety->overdue_oldest, 'day')); ?></span>
            <a class="rtm-action bad" href="<?php echo dol_escape_htmltag(DOL_URL_ROOT . '/compta/facture/list.php?socid=' . $moneyTicket->fk_soc . '&search_status=1&search_option=late'); ?>"><i class="fas fa-bell paddingright"></i><?php echo $langs->trans('TicketMoneyActionRelaunchShort'); ?></a>
        <?php else : ?>
            <span class="rtm-badge good"><?php echo $langs->trans('TicketMoneyUpToDate'); ?></span>
        <?php endif; ?>
    </div>

    <div class="rtm-banner-cell">
        <span class="rtm-banner-label"><?php echo $langs->trans('TicketMoneyCoverage'); ?></span>
        <?php if ($moneyTicket->billing === ReedcrmTicketMoney::BILLING_FREE) : ?>
            <span class="rtm-badge mute"><?php echo $langs->trans('TicketMoneyBillingFree'); ?></span>
            <span class="rtm-meta"><?php echo dol_escape_htmltag($moneyTicket->billing_note); ?></span>
        <?php elseif ($moneyTicket->billing === ReedcrmTicketMoney::BILLING_BILLABLE) : ?>
            <span class="rtm-badge info"><?php echo $langs->trans('TicketMoneyBillingBillable'); ?></span>
        <?php elseif ($moneySociety === null) : ?>
            <span class="opacitymedium">—</span>
        <?php elseif ($moneySociety->frozen) : ?>
            <span class="rtm-badge bad"><?php echo $langs->trans('TicketMoneyCreditFrozen'); ?></span>
            <span class="rtm-meta"><?php echo $langs->trans('TicketMoneyCreditFrozenHelp'); ?></span>
        <?php elseif ($moneySociety->period !== null) : ?>
            <?php
            $left = max(0, $moneySociety->period->credit - $moneySociety->period->used);
            $pct  = $moneySociety->period->credit > 0 ? min(100, (int) round($moneySociety->period->used / $moneySociety->period->credit * 100)) : 100;
            ?>
            <span class="rtm-credit"><span class="rtm-credit-bar"><span style="width:<?php echo $pct; ?>%"></span></span>
            <span><?php echo $langs->trans('TicketMoneyCreditLeft', ReedcrmTicketMoney::formatDuration($left), ReedcrmTicketMoney::formatDuration($moneySociety->period->credit)); ?></span></span>
            <span class="rtm-meta"><?php echo dol_escape_htmltag($moneySociety->period->product_ref) . ' · ' . $langs->trans('TicketMoneyUntil', dol_print_date($moneySociety->period->end, 'day')); ?></span>
        <?php else : ?>
            <span class="rtm-badge warn"><?php echo $langs->trans('TicketMoneyNoSubscription'); ?></span>
            <span class="rtm-meta"><?php echo $langs->trans('TicketMoneyNoSubscriptionHelp'); ?></span>
        <?php endif; ?>
    </div>

    <div class="rtm-banner-cell">
        <span class="rtm-banner-label"><?php echo $langs->trans('TicketMoneyThisTicket'); ?></span>
        <span><strong><?php echo ReedcrmTicketMoney::formatDuration($moneyTicket->time_total); ?></strong>
        <?php
        $details = [];
        if ($moneyTicket->time_included > 0) {
            $details[] = $langs->trans('TicketMoneyIncluded', ReedcrmTicketMoney::formatDuration($moneyTicket->time_included));
        }
        if ($moneyTicket->time_invoiced > 0) {
            $details[] = $langs->trans('TicketMoneyInvoiced', ReedcrmTicketMoney::formatDuration($moneyTicket->time_invoiced));
        }
        if ($moneyTicket->time_free > 0) {
            $details[] = $langs->trans('TicketMoneyFree', ReedcrmTicketMoney::formatDuration($moneyTicket->time_free));
        }
        echo !empty($details) ? '<span class="rtm-meta"> · ' . implode(' · ', $details) . '</span>' : '';
        ?>
        </span>
        <?php if ($moneyTicket->amount_to_bill > 0) : ?>
            <span class="rtm-amount"><?php echo $langs->trans('TicketMoneyLeftToBill', price($moneyTicket->amount_to_bill, 0, $langs, 1, -1, -1, $currency)); ?></span>
        <?php elseif ($moneyTicket->time_total > 0) : ?>
            <span class="rtm-meta"><?php echo $langs->trans('TicketMoneyNothingLeftToBill'); ?></span>
        <?php endif; ?>
    </div>

    <div class="rtm-banner-actions">
        <?php if ($moneyCanLogTime && $moneyTicket->fk_project > 0) : ?>
            <span class="rtm-time-buttons">
                <?php foreach ([15 => '+15 min', 30 => '+30 min', 60 => '+1 h'] as $minutes => $label) : ?>
                    <a class="rtm-action info" href="<?php echo dol_escape_htmltag($moneyCardUrl . '&action=reedcrm_money_time&minutes=' . $minutes . '&token=' . $token); ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </span>
        <?php elseif ($moneyCanLogTime) : ?>
            <a class="rtm-action warn" href="<?php echo dol_escape_htmltag($moneyCardUrl . '&action=classify&token=' . $token); ?>"><i class="fas fa-project-diagram paddingright"></i><?php echo $langs->trans('TicketMoneyActionAttachProject'); ?></a>
        <?php endif; ?>
        <?php if ($moneyCanBill && $moneyTicket->amount_to_bill > 0) : ?>
            <a class="rtm-action good" href="<?php echo dol_escape_htmltag($moneyListUrl . '?action=bill&id=' . $moneyTicket->id . '&token=' . $token); ?>"><i class="fas fa-file-invoice-dollar paddingright"></i><?php echo $langs->trans('TicketMoneyActionBill', price($moneyTicket->amount_to_bill, 0, $langs, 1, 0, 0, $currency)); ?></a>
        <?php endif; ?>
        <?php if ($moneyCanQuote && $moneyTicket->fk_soc > 0) : ?>
            <a class="rtm-action <?php echo $moneyTicket->action === ReedcrmTicketMoney::ACTION_QUOTE ? 'info' : 'mute'; ?>" href="<?php echo dol_escape_htmltag($moneyListUrl . '?action=quote&id=' . $moneyTicket->id . '&token=' . $token); ?>"><i class="fas fa-file-signature paddingright"></i><?php echo $langs->trans(empty($moneyTicket->proposal_ids) ? 'TicketMoneyActionQuote' : 'TicketMoneyActionQuoteAnother'); ?></a>
        <?php endif; ?>
        <?php if ($moneyCanLogTime) : ?>
            <?php if ($moneyTicket->billing !== ReedcrmTicketMoney::BILLING_FREE) : ?>
                <a class="rtm-action mute" href="<?php echo dol_escape_htmltag($moneyCardUrl . '&action=reedcrm_money_free&token=' . $token); ?>" title="<?php echo dol_escape_htmltag($langs->trans('TicketMoneyActionFreeHelp')); ?>"><i class="fas fa-gift paddingright"></i><?php echo $langs->trans('TicketMoneyActionFree'); ?></a>
            <?php endif; ?>
            <?php if ($moneyTicket->billing !== ReedcrmTicketMoney::BILLING_BILLABLE) : ?>
                <a class="rtm-action mute" href="<?php echo dol_escape_htmltag($moneyCardUrl . '&action=reedcrm_money_billing&mode=' . ReedcrmTicketMoney::BILLING_BILLABLE . '&token=' . $token); ?>" title="<?php echo dol_escape_htmltag($langs->trans('TicketMoneyActionBillableHelp')); ?>"><i class="fas fa-euro-sign paddingright"></i><?php echo $langs->trans('TicketMoneyActionBillable'); ?></a>
            <?php endif; ?>
            <?php if ($moneyTicket->billing !== ReedcrmTicketMoney::BILLING_AUTO) : ?>
                <a class="rtm-action mute" href="<?php echo dol_escape_htmltag($moneyCardUrl . '&action=reedcrm_money_billing&mode=' . ReedcrmTicketMoney::BILLING_AUTO . '&token=' . $token); ?>" title="<?php echo dol_escape_htmltag($langs->trans('TicketMoneyActionAutoHelp')); ?>"><i class="fas fa-undo paddingright"></i><?php echo $langs->trans('TicketMoneyActionAuto'); ?></a>
            <?php endif; ?>
        <?php endif; ?>
        <a class="rtm-link" href="<?php echo dol_escape_htmltag($moneyListUrl); ?>"><?php echo $langs->trans('TicketMoneyBackToList'); ?></a>
    </div>
</div>

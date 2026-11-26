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
 * \file    core/tpl/newsletter/newsletter_received.tpl.php
 * \ingroup reedcrm
 * \brief   Newsletters received by a contact or a third party. Whole on the "Newsletters" tab (where the address stands,
 *          every mail, the unsubscribed contacts of a third party); compact in the latest objects of the commercial
 *          card of a third party, as the other blocks of that column
 *
 * Variables from view/newsletter/newsletter_received.php or ActionsReedcrm::addMoreRecentObjects():
 * @var Contact|Societe $object               Contact or third party
 * @var bool            $isThirdparty         Third party or contact
 * @var array           $received             Mails, see reedcrm_newsletter_fetch_received()
 * @var int             $receivedCount        Number of mails
 * @var array|null      $subscription         Contact: [email, valid, since] (since = date of the unsubscribe, 0 if none)
 * @var array           $unsubscribedContacts Third party: its unsubscribed contacts
 * @var bool            $permissiontoadd      Right to write newsletters (subscribe again)
 * @var string          $pageUrl              URL of the "Newsletters" tab
 * @var bool            $compact              Block of the commercial card
 * @var Translate       $langs                Translations
 * @var array           $writers              Contact, read only: users who may subscribe it again
 * @var array           $sharedContacts       Contact: other contacts of its address
 */

$writers        = $writers ?? [];
$sharedContacts = $sharedContacts ?? [];

$cardUrl = dol_buildpath('/reedcrm/view/newsletter/newsletter_card.php', 1);

/**
 * State of a mail: sent, in error (with its message), to send again
 *
 * @param  object $line Mail
 * @return string       HTML
 */
$mailState = function ($line) use ($langs): string {
    $status = (int) $line->status;
    if ($status === ReedcrmNewsletter::SEND_STATUS_SENT) {
        return dolGetStatus($langs->trans('NewsletterSendStatusSent'), $langs->trans('NewsletterSendStatusSent'), '', 'status4', 1);
    }
    if ($status === ReedcrmNewsletter::SEND_STATUS_RETRY) {
        return dolGetStatus($langs->trans('NewsletterSendStatusRetry'), $langs->trans('NewsletterSendStatusRetry'), '', 'status1', 1);
    }

    return dolGetStatus($langs->trans('NewsletterSendStatusError'), $langs->trans('NewsletterSendStatusError'), '', 'status8', 1)
        . '<div class="small opacitymedium">' . dol_escape_htmltag((string) $line->error_msg) . '</div>';
};
?>
<?php if ($compact) : ?>
    <div class="div-table-responsive-no-min">
        <table class="noborder centpercent lastrecordtable reedcrm-newsletter-received-block">
            <tr class="liste_titre">
                <td colspan="3">
                    <table class="nobordernopadding centpercent">
                        <tr>
                            <td class="tdoverflowmax150"><?php echo $langs->trans('NewsletterLastReceived'); ?></td>
                            <td class="right nowraponall"><a class="notasortlink" href="<?php echo $pageUrl; ?>"><?php echo $langs->trans('NewsletterAllReceived'); ?><span class="badge marginleftonlyshort"><?php echo (int) $receivedCount; ?></span></a></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <?php foreach ($received as $line) : ?>
                <?php // The date first and whole: on a phone the label is cut, not the date nor the link "All" ?>
                <tr class="oddeven">
                    <td class="nowraponall"><?php echo dol_print_date($line->date_send, 'day'); ?></td>
                    <td class="tdoverflowmax150" title="<?php echo dol_escape_htmltag((string) $line->label); ?>"><a href="<?php echo $cardUrl . '?id=' . ((int) $line->fk_newsletter); ?>"><?php echo img_picto('', 'fontawesome_fa-envelope-open-text_fas_#63ACC9', 'class="pictofixedwidth"') . dol_escape_htmltag((string) $line->label); ?></a></td>
                    <td class="tdoverflowmax100 opacitymedium hideonsmartphone"><?php echo dol_escape_htmltag(!empty($line->fk_contact) ? dolGetFirstLastname((string) $line->firstname, (string) $line->lastname) : (string) $line->email); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
<?php else : ?>
    <?php if ($subscription !== null) : ?>
        <table class="border centpercent tableforfield reedcrm-newsletter-subscription">
            <tr>
                <td class="titlefield"><?php echo $langs->trans('Email'); ?></td>
                <td><?php echo $subscription['email'] !== '' ? dol_print_email($subscription['email'], 0, 0, 1) : '<span class="opacitymedium">' . $langs->trans('NoEMail') . '</span>'; ?></td>
            </tr>
            <tr>
                <td><?php echo $langs->trans('NewsletterSubscription'); ?></td>
                <td>
                    <?php if (!$subscription['valid']) : ?>
                        <span class="opacitymedium"><?php echo $langs->trans('NewsletterSubscriptionNoEmail'); ?></span>
                    <?php elseif ($subscription['since'] > 0) : ?>
                        <?php echo dolGetStatus($langs->trans('NewsletterSubscriptionOut'), $langs->trans('NewsletterSubscriptionOut'), '', 'status8', 4); ?>
                        <span class="opacitymedium marginleftonly"><?php echo $langs->trans('NewsletterUnsubscribedOn', dol_print_date($subscription['since'], 'day')); ?></span>
                    <?php else : ?>
                        <?php echo dolGetStatus($langs->trans('NewsletterSubscriptionIn'), $langs->trans('NewsletterSubscriptionIn'), '', 'status4', 4); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if (!empty($sharedContacts)) : ?>
                <?php // The newsletters read the address: what is done for one of these contacts is done for all ?>
                <?php
                $sharedNames = [];
                foreach ($sharedContacts as $sharedContact) {
                    $sharedNames[] = dolGetFirstLastname((string) $sharedContact->firstname, (string) $sharedContact->lastname) . (!empty($sharedContact->thirdparty_name) ? ' (' . $sharedContact->thirdparty_name . ')' : '');
                }
                ?>
                <tr>
                    <td><?php echo $langs->trans('NewsletterSharedAddress'); ?></td>
                    <td class="opacitymedium"><?php echo dol_escape_htmltag(implode(', ', $sharedNames)); ?></td>
                </tr>
            <?php endif; ?>
        </table>
        <?php if ($subscription['valid'] && $subscription['since'] > 0) : ?>
            <div class="tabsAction">
                <?php echo dolGetButtonAction($permissiontoadd ? '' : $langs->trans('NewsletterActionNoRight'), $langs->trans('NewsletterResubscribe'), 'default', $pageUrl . '&action=resubscribe&token=' . newToken(), '', $permissiontoadd); ?>
            </div>
            <?php if (!$permissiontoadd) : ?>
                <?php // Read only: whom to ask, rather than a greyed button ?>
                <?php
                $writerNames = [];
                foreach ($writers as $writer) {
                    $writerNames[] = dolGetFirstLastname((string) $writer->firstname, (string) $writer->lastname) ?: (string) $writer->login;
                }
                ?>
                <div class="opacitymedium reedcrm-newsletter-inline-help"><?php echo !empty($writerNames) ? $langs->trans('NewsletterResubscribeAsk', implode(', ', $writerNames)) : $langs->trans('NewsletterResubscribeAskAdmin'); ?></div>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php print load_fiche_titre($langs->trans('NewsletterReceived') . ($receivedCount > 0 ? ' <span class="opacitymedium">(' . $receivedCount . ')</span>' : ''), '', 'fontawesome_fa-envelope-open-text_fas'); ?>
    <?php // On a phone each mail is a card: the newsletter, then the date, the recipient and the state (see .reedcrm-newsletter-stack) ?>
    <div class="div-table-responsive-no-min">
        <table class="noborder centpercent reedcrm-newsletter-stack">
            <tr class="liste_titre">
                <th><?php echo $langs->trans('Newsletter'); ?></th>
                <th><?php echo $langs->trans('Date'); ?></th>
                <?php if ($isThirdparty) : ?>
                    <th><?php echo $langs->trans('NewsletterRecipient'); ?></th>
                <?php endif; ?>
                <th class="right"><?php echo $langs->trans('Status'); ?></th>
            </tr>
            <?php if (empty($received)) : ?>
                <tr class="oddeven">
                    <td colspan="<?php echo $isThirdparty ? 4 : 3; ?>"><span class="opacitymedium"><?php echo $langs->trans('NewsletterNothingReceived'); ?></span></td>
                </tr>
            <?php endif; ?>
            <?php foreach ($received as $line) : ?>
                <tr class="oddeven">
                    <td class="reedcrm-newsletter-stack-head">
                        <span class="reedcrm-newsletter-edition-ref" title="<?php echo dol_escape_htmltag((string) $line->ref . ' - ' . (string) $line->label); ?>">
                            <a href="<?php echo $cardUrl . '?id=' . ((int) $line->fk_newsletter); ?>"><?php echo img_picto('', 'fontawesome_fa-envelope-open-text_fas_#63ACC9', 'class="pictofixedwidth"') . dol_escape_htmltag((string) $line->ref); ?></a>
                            <span class="opacitymedium"><?php echo dol_escape_htmltag((string) $line->label); ?></span>
                        </span>
                        <?php if (!empty($line->date_unsubscribe)) : ?>
                            <div class="small warning"><?php echo $langs->trans('NewsletterUnsubscribedThrough', dol_print_date($line->date_unsubscribe, 'day')); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="nowraponall" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('Date')); ?>"><?php echo dol_print_date($line->date_send, 'dayhour'); ?></td>
                    <?php if ($isThirdparty) : ?>
                        <td class="tdoverflowmax200" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterRecipient')); ?>">
                            <span class="reedcrm-newsletter-stack-value">
                                <?php if (!empty($line->fk_contact)) : ?>
                                    <a href="<?php echo DOL_URL_ROOT . '/contact/card.php?id=' . ((int) $line->fk_contact); ?>"><?php echo img_picto('', 'contact', 'class="pictofixedwidth"') . dol_escape_htmltag(dolGetFirstLastname((string) $line->firstname, (string) $line->lastname)); ?></a>
                                <?php endif; ?>
                                <span class="small opacitymedium reedcrm-newsletter-stack-sub"><?php echo dol_escape_htmltag((string) $line->email); ?></span>
                            </span>
                        </td>
                    <?php endif; ?>
                    <td class="right" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('Status')); ?>"><?php echo $mailState($line); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <?php if ($isThirdparty && !empty($unsubscribedContacts)) : ?>
        <?php print load_fiche_titre($langs->trans('NewsletterUnsubscribedContacts') . ' <span class="opacitymedium">(' . count($unsubscribedContacts) . ')</span>', '', 'fontawesome_fa-user-slash_fas'); ?>
        <div class="div-table-responsive-no-min">
            <table class="noborder centpercent reedcrm-newsletter-stack">
                <tr class="liste_titre">
                    <th><?php echo $langs->trans('Contact'); ?></th>
                    <th class="right"><?php echo $langs->trans('NewsletterUnsubscribedSince'); ?></th>
                </tr>
                <?php foreach ($unsubscribedContacts as $contactLine) : ?>
                    <tr class="oddeven">
                        <td class="reedcrm-newsletter-stack-head">
                            <span class="reedcrm-newsletter-edition-ref">
                                <a href="<?php echo dol_buildpath('/reedcrm/view/newsletter/newsletter_received.php', 1) . '?contactid=' . ((int) $contactLine->rowid); ?>"><?php echo img_picto('', 'contact', 'class="pictofixedwidth"') . dol_escape_htmltag(dolGetFirstLastname((string) $contactLine->firstname, (string) $contactLine->lastname)); ?></a>
                                <span class="opacitymedium"><?php echo dol_escape_htmltag((string) $contactLine->email); ?></span>
                            </span>
                        </td>
                        <td class="right nowraponall" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterUnsubscribedSince')); ?>"><?php echo dol_print_date($contactLine->date_creat, 'day'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

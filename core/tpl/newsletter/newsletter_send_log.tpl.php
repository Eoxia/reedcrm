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
 * \file    core/tpl/newsletter/newsletter_send_log.tpl.php
 * \ingroup reedcrm
 * \brief   Last mails sent by a newsletter, in the left half under the buttons: one column per piece of information
 *          that changes from a line to the other
 *
 * Variables from view/newsletter/newsletter_card.php:
 * @var ReedcrmNewsletter $object       Newsletter
 * @var Translate         $langs        Translations
 * @var array             $sendLog      Last lines of the log
 * @var int               $sendLogCount Number of lines of the log
 */

global $db, $user;

$isProject = $object->target_type === ReedcrmNewsletter::TARGET_PROJECT;
// The planned date tells the sendings apart only when there are several: reminders and sendings after the creation
$showOccurrence = $object->mail_type === ReedcrmNewsletter::TYPE_REMINDER || $object->isAfterCreation();
$nbColumns      = 3 + ($isProject ? 1 : 0);
?>
<?php print load_fiche_titre($langs->trans('NewsletterSendLog') . ($sendLogCount > 0 ? ' <span class="opacitymedium">(' . $sendLogCount . ')</span>' : ''), '', 'fontawesome_fa-history_fas'); ?>
<div class="div-table-responsive-no-min">
    <table class="noborder centpercent">
        <tr class="liste_titre">
            <th><?php echo $langs->trans('Date'); ?></th>
            <th><?php echo $langs->trans('NewsletterRecipient'); ?></th>
            <?php if ($isProject) : ?>
                <th><?php echo $langs->trans('Project'); ?></th>
            <?php endif; ?>
            <th class="right"><?php echo $langs->trans('Status'); ?></th>
        </tr>
        <?php if (empty($sendLog)) : ?>
            <tr class="oddeven">
                <td colspan="<?php echo $nbColumns; ?>"><span class="opacitymedium"><?php echo $langs->trans('NewsletterNeverSent'); ?></span></td>
            </tr>
        <?php endif; ?>
        <?php foreach ($sendLog as $line) : ?>
            <tr class="oddeven">
                <td class="nowraponall">
                    <?php echo dol_print_date($line->date_send, 'dayhour'); ?>
                    <?php if ($showOccurrence) : ?>
                        <div class="small opacitymedium"><?php echo $langs->trans('NewsletterOccurrenceOf', dol_print_date($line->occurrence, 'day')); ?></div>
                    <?php endif; ?>
                </td>
                <td class="tdoverflowmax250">
                    <?php $canOpen = reedcrm_newsletter_user_can_open_thirdparty($user, $db, (int) $line->fk_soc); ?>
                    <?php if (!empty($line->fk_contact)) : ?>
                        <?php $who = img_picto('', 'contact', 'class="pictofixedwidth"') . dol_escape_htmltag(dolGetFirstLastname((string) $line->firstname, (string) $line->lastname)); ?>
                        <?php echo $canOpen ? '<a href="' . DOL_URL_ROOT . '/contact/card.php?id=' . ((int) $line->fk_contact) . '">' . $who . '</a>' : $who; ?>
                    <?php elseif (!empty($line->fk_soc)) : ?>
                        <?php $who = img_picto('', 'company', 'class="pictofixedwidth"') . dol_escape_htmltag((string) $line->thirdparty_name); ?>
                        <?php echo $canOpen ? '<a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . ((int) $line->fk_soc) . '">' . $who . '</a>' : $who; ?>
                    <?php endif; ?>
                    <div class="small opacitymedium tdoverflowmax250"><?php echo dol_escape_htmltag($line->email); ?></div>
                </td>
                <?php if ($isProject) : ?>
                    <td class="tdoverflowmax150">
                        <?php if (!empty($line->fk_project)) : ?>
                            <a href="<?php echo DOL_URL_ROOT . '/projet/card.php?id=' . ((int) $line->fk_project); ?>"><?php echo img_picto('', 'project', 'class="pictofixedwidth"') . dol_escape_htmltag((string) $line->project_ref); ?></a>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
                <td class="right">
                    <?php if ((int) $line->status === ReedcrmNewsletter::SEND_STATUS_SENT) : ?>
                        <?php echo dolGetStatus($langs->trans('NewsletterSendStatusSent'), $langs->trans('NewsletterSendStatusSent'), '', 'status4', 1); ?>
                        <?php if (!empty($line->date_unsubscribe)) : ?>
                            <div class="small warning"><?php echo $langs->trans('NewsletterUnsubscribedThrough', dol_print_date($line->date_unsubscribe, 'day')); ?></div>
                        <?php endif; ?>
                    <?php elseif ((int) $line->status === ReedcrmNewsletter::SEND_STATUS_ERROR) : ?>
                        <?php echo dolGetStatus($langs->trans('NewsletterSendStatusError'), $langs->trans('NewsletterSendStatusError'), '', 'status8', 1); ?>
                        <div class="small opacitymedium"><?php echo dol_escape_htmltag((string) $line->error_msg); ?></div>
                    <?php elseif ((int) $line->status === ReedcrmNewsletter::SEND_STATUS_RETRY) : ?>
                        <?php // Put back in the queue: the error stays readable until the mail leaves again ?>
                        <?php echo dolGetStatus($langs->trans('NewsletterSendStatusRetry'), $langs->trans('NewsletterSendStatusRetry'), '', 'status1', 1); ?>
                        <div class="small opacitymedium"><?php echo dol_escape_htmltag((string) $line->error_msg); ?></div>
                    <?php else : ?>
                        <?php echo dolGetStatus($langs->trans('NewsletterSendStatusPending'), $langs->trans('NewsletterSendStatusPending'), '', 'status0', 1); ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if ($sendLogCount > count($sendLog)) : ?>
            <tr class="oddeven">
                <td colspan="<?php echo $nbColumns; ?>"><span class="opacitymedium"><?php echo $langs->trans('NewsletterOlderSendings', $sendLogCount - count($sendLog)); ?></span></td>
            </tr>
        <?php endif; ?>
    </table>
</div>

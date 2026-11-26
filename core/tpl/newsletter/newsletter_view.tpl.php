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
 * \file    core/tpl/newsletter/newsletter_view.tpl.php
 * \ingroup reedcrm
 * \brief   Card of a newsletter: settings, preview of the mail and recipients, above the buttons. The log of the
 *          sendings and the events are under the buttons, see newsletter_activity.tpl.php
 *
 * Variables from view/newsletter/newsletter_card.php:
 * @var ReedcrmNewsletter $object          Newsletter
 * @var Form              $form            Form helper
 * @var Translate         $langs           Translations
 * @var User              $user            Current user
 * @var array             $tags            Tags: [url, label, color, class]
 * @var int               $recipientsCount Number of recipients
 * @var array             $recipientsShown First recipients
 * @var array|null        $emailTemplate   Template in template mode
 * @var array             $preview         [subject, html] of the mail of the first recipient
 * @var array             $excluded        Targets left out: [unsubscribed => n, noemail => n]
 * @var string            $contentError    Why the newsletter cannot leave, '' when it can
 * @var string[]          $emptyKeys       Keys of the projects in a mail to contacts
 * @var array             $stateCounts     After the creation: [RECIPIENT_* => number of recipients]
 * @var ReedcrmNewsletter|null $previousEdition Newsletter this one was prepared from
 * @var ReedcrmNewsletter|null $nextEdition     Newsletter prepared from this one
 * @var bool              $datePassed      Draft whose planned date has passed
 * @var array             $excludeTags     Tags left out: [url, label, color, class]
 * @var User|null         $salesRep        Sales representative of the targeting
 * @var array             $sendStats       Results: [sent, errors, unsubscribed]
 * @var int               $hiddenRecipients Recipients out of the reach of the user, not listed
 * @var string            $replyTo         Where the answers go when it is not the sender, '' otherwise
 * @var string            $sendingDuration How long a big sending takes, '' for a small one
 */

$isReminder      = $object->mail_type === ReedcrmNewsletter::TYPE_REMINDER;
$isAfterCreation = $object->isAfterCreation();
?>
<?php if ($object->isDeleted()) : ?>
    <div class="warning reedcrm-newsletter-notice"><?php echo img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('NewsletterDeletedNotice'); ?></div>
<?php elseif ($contentError !== '') : ?>
    <div class="error reedcrm-newsletter-notice"><?php echo img_picto('', 'error', 'class="pictofixedwidth"') . $langs->trans('NewsletterCannotLeave', dol_escape_htmltag($contentError)); ?></div>
<?php endif; ?>
<?php if (!empty($emptyKeys) && !$object->isDeleted()) : ?>
    <div class="warning reedcrm-newsletter-notice"><?php echo img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('NewsletterEmptyKeysForContacts', implode(', ', $emptyKeys)); ?></div>
<?php endif; ?>
<?php if ($datePassed) : ?>
    <div class="warning reedcrm-newsletter-notice"><?php echo img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('NewsletterDraftDatePassed', dol_print_date($object->date_send, 'dayhour')); ?></div>
<?php endif; ?>
<div class="fichecenter reedcrm-newsletter-card">
    <div class="fichehalfleft">
        <div class="underbanner clearboth"></div>
        <table class="border centpercent tableforfield">
            <tr>
                <td class="titlefield"><?php echo $langs->trans('NewsletterMailType'); ?></td>
                <td><?php echo $langs->trans($isReminder ? 'NewsletterTypeReminder' : 'NewsletterTypeNewsletter'); ?></td>
            </tr>
            <?php if ($isReminder) : ?>
                <tr>
                    <td><?php echo $langs->trans('NewsletterFrequency'); ?></td>
                    <td><?php echo dol_escape_htmltag(reedcrm_newsletter_frequency_text((int) $object->frequency, (string) $object->frequency_unit, $langs)); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td><?php echo $langs->trans('NewsletterTargetType'); ?></td>
                <td><?php echo $langs->trans($object->target_type === ReedcrmNewsletter::TARGET_PROJECT ? 'NewsletterTargetProject' : 'NewsletterTargetContact'); ?></td>
            </tr>
            <tr>
                <td><?php echo $langs->trans('NewsletterTargetTags'); ?></td>
                <td>
                    <?php $chips = $tags; ?>
                    <?php require __DIR__ . '/newsletter_tag_chips.tpl.php'; ?>
                </td>
            </tr>
            <?php if (!empty($excludeTags)) : ?>
                <tr>
                    <td><?php echo $langs->trans('NewsletterExcludeTags'); ?></td>
                    <td>
                        <?php $chips = $excludeTags; ?>
                        <?php require __DIR__ . '/newsletter_tag_chips.tpl.php'; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if (in_array($object->thirdparty_type, [ReedcrmNewsletter::THIRDPARTY_CUSTOMER, ReedcrmNewsletter::THIRDPARTY_PROSPECT], true)) : ?>
                <tr>
                    <td><?php echo $langs->trans('NewsletterThirdpartyType'); ?></td>
                    <td><?php echo $langs->trans($object->thirdparty_type === ReedcrmNewsletter::THIRDPARTY_CUSTOMER ? 'NewsletterThirdpartyCustomers' : 'NewsletterThirdpartyProspects'); ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($salesRep !== null) : ?>
                <tr>
                    <td><?php echo $langs->trans('NewsletterSalesRep'); ?></td>
                    <td><?php echo $salesRep->getNomUrl(-1); ?></td>
                </tr>
            <?php endif; ?>
            <tr>
                <td><?php echo $langs->trans('NewsletterRecipients'); ?></td>
                <td>
                    <span class="badge badge-info"><?php echo $recipientsCount; ?></span> <span class="opacitymedium"><?php echo $langs->trans('NewsletterRecipientsHelp'); ?></span>
                    <?php if (!empty($excluded['unsubscribed']) || !empty($excluded['noemail']) || !empty($excluded['filtered'])) : ?>
                        <div class="opacitymedium reedcrm-newsletter-excluded">
                            <?php
                            // The parts as plain text: trans() encodes the whole sentence once, a part already
                            // encoded by trans() would show "d&eacute;sinscrit"
                            $parts = [];
                            if (!empty($excluded['filtered'])) {
                                $parts[] = $langs->transnoentitiesnoconv('NewsletterExcludedFiltered', (int) $excluded['filtered']);
                            }
                            if (!empty($excluded['unsubscribed'])) {
                                $parts[] = $langs->transnoentitiesnoconv('NewsletterExcludedUnsubscribed', (int) $excluded['unsubscribed']);
                            }
                            if (!empty($excluded['noemail'])) {
                                $parts[] = $langs->transnoentitiesnoconv('NewsletterExcludedNoEmail', (int) $excluded['noemail']);
                            }
                            echo $langs->trans('NewsletterExcluded', implode(', ', $parts));
                            ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($sendingDuration !== '') : ?>
                        <div class="opacitymedium reedcrm-newsletter-inline-help"><?php echo $sendingDuration; ?></div>
                    <?php endif; ?>
                    <?php if ($isAfterCreation && $recipientsCount > 0) : ?>
                        <div class="reedcrm-newsletter-state-counts">
                            <?php foreach ([ReedcrmNewsletter::RECIPIENT_SENT, ReedcrmNewsletter::RECIPIENT_DUE, ReedcrmNewsletter::RECIPIENT_UPCOMING, ReedcrmNewsletter::RECIPIENT_SKIPPED, ReedcrmNewsletter::RECIPIENT_ERROR] as $stateKey) : ?>
                                <?php if (!empty($stateCounts[$stateKey])) : ?>
                                    <span class="reedcrm-newsletter-state-count"><?php echo $langs->trans('NewsletterStateCount_' . $stateKey, (int) $stateCounts[$stateKey]); ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><?php echo $langs->trans('NewsletterContentMode'); ?></td>
                <td>
                    <?php if ($object->content_mode === ReedcrmNewsletter::MODE_TEMPLATE) : ?>
                        <?php echo $langs->trans('NewsletterContentModeTemplate') . ' : '; ?>
                        <?php echo $emailTemplate !== null ? dol_escape_htmltag($emailTemplate['label']) : '<span class="warning">' . $langs->trans('NewsletterTemplateNotFound') . '</span>'; ?>
                        <?php if ($emailTemplate !== null && $user->admin) : ?>
                            <a class="paddingleft" href="<?php echo DOL_URL_ROOT . '/admin/mails_templates.php?action=edit&token=' . newToken() . '&id=' . ((int) $object->fk_email_template); ?>"><?php echo img_edit($langs->trans('Modify')); ?></a>
                        <?php endif; ?>
                    <?php elseif ($object->content_mode === ReedcrmNewsletter::MODE_BLOCKS) : ?>
                        <?php echo $langs->trans('NewsletterContentModeBlocks'); ?>
                    <?php else : ?>
                        <?php echo $langs->trans('NewsletterContentModeHtml'); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><?php echo $langs->trans('MailFrom'); ?></td>
                <td>
                    <?php echo dol_escape_htmltag($object->getSenderAddress()); ?>
                    <?php if ($replyTo !== '') : ?>
                        <div class="opacitymedium small reedcrm-newsletter-inline-help"><?php echo $langs->trans('NewsletterReplyToHelp', $replyTo); ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><?php echo $langs->trans('NewsletterTrigger'); ?></td>
                <td><?php echo $isAfterCreation ? dol_escape_htmltag(dol_ucfirst(reedcrm_newsletter_trigger_text($object, $langs))) : $langs->trans('NewsletterTriggerDate'); ?></td>
            </tr>
            <tr>
                <?php if ($isAfterCreation) : ?>
                    <td><?php echo $form->textwithpicto($langs->trans('NewsletterStartDate'), $langs->trans('NewsletterStartDateHelp')); ?></td>
                <?php else : ?>
                    <td><?php echo $langs->trans($isReminder ? 'NewsletterNextDateSend' : 'NewsletterDateSend'); ?></td>
                <?php endif; ?>
                <td>
                    <?php if (!empty($object->date_send)) : ?>
                        <?php echo dol_print_date($object->date_send, 'dayhour'); ?>
                        <?php // Beside the date, the state: a date on a draft plans nothing ?>
                        <?php if (!$isAfterCreation && (int) $object->status === ReedcrmNewsletter::STATUS_DRAFT && !$object->isDeleted()) : ?>
                            <div class="warning small reedcrm-newsletter-inline-help"><?php echo $langs->trans('NewsletterDateNotPlanned'); ?></div>
                        <?php elseif (!$isAfterCreation && !empty($object->date_last_send) && (int) $object->date_last_send < (int) $object->date_send) : ?>
                            <div class="opacitymedium small reedcrm-newsletter-inline-help"><?php echo $langs->trans('NewsletterSentEarly', dol_print_date($object->date_last_send, 'dayhour')); ?></div>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="opacitymedium"><?php echo $langs->trans($isAfterCreation ? 'NewsletterStartOnActivation' : 'NewsletterNoDateSend'); ?></span>
                    <?php endif; ?>
                    <?php if ($isAfterCreation) : ?>
                        <?php // In clear: the help of the label has no touch trigger, a phone would never show it ?>
                        <div class="opacitymedium small reedcrm-newsletter-inline-help"><?php echo $langs->trans('NewsletterStartDateShortHelp'); ?></div>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($object->canPrepareNext()) : ?>
                <tr>
                    <td><?php echo $langs->trans('NewsletterAutoNext'); ?></td>
                    <td><?php echo $langs->trans(!empty($object->auto_next) ? 'NewsletterAutoNextOn' : 'NewsletterAutoNextOff'); ?></td>
                </tr>
            <?php endif; ?>
            <?php if ($previousEdition !== null || $nextEdition !== null) : ?>
                <tr>
                    <td><?php echo $langs->trans('NewsletterEditions'); ?></td>
                    <td>
                        <?php if ($previousEdition !== null) : ?>
                            <div><span class="opacitymedium"><?php echo $langs->trans('NewsletterPreviousEdition'); ?> :</span> <?php echo $previousEdition->getNomUrl(1, '', 0, '', -1, 1); ?></div>
                        <?php endif; ?>
                        <?php if ($nextEdition !== null) : ?>
                            <div><span class="opacitymedium"><?php echo $langs->trans('NewsletterNextEdition'); ?> :</span> <?php echo $nextEdition->getNomUrl(1, '', 0, '', -1, 1) . ' ' . $nextEdition->getLibStatut(5); ?></div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endif; ?>
            <tr>
                <td><?php echo $langs->trans('NewsletterDateLastSend'); ?></td>
                <td><?php echo !empty($object->date_last_send) ? dol_print_date($object->date_last_send, 'dayhour') : '<span class="opacitymedium">' . $langs->trans('NewsletterNeverSent') . '</span>'; ?></td>
            </tr>
            <tr>
                <td><?php echo $langs->trans('NewsletterNbSent'); ?></td>
                <td>
                    <?php echo (int) $object->nb_sent; ?>
                    <?php if ((int) $object->nb_errors > 0) : ?>
                        <span class="badge badge-danger marginleftonly"><?php echo $langs->trans('NewsletterNbErrorsShort', (int) $object->nb_errors); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($sendStats['sent'] > 0) : ?>
                <tr>
                    <td><?php echo $form->textwithpicto($langs->trans('NewsletterUnsubscribes'), $langs->trans('NewsletterUnsubscribesHelp')); ?></td>
                    <td>
                        <?php echo (int) $sendStats['unsubscribed']; ?>
                        <span class="opacitymedium">(<?php echo price(round(100 * $sendStats['unsubscribed'] / $sendStats['sent'], 1), 0, '', 0, -1, 1); ?> %)</span>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="fichehalfright">
        <div class="underbanner clearboth"></div>
        <div class="reedcrm-newsletter-preview reedcrm-newsletter-preview--card">
            <div class="reedcrm-newsletter-preview-title">
                <span class="opacitymedium"><?php echo $langs->trans('MailTopic'); ?> :</span>
                <?php echo dol_escape_htmltag($preview[0]); ?>
            </div>
            <iframe class="reedcrm-newsletter-preview-frame" sandbox="" title="<?php echo dol_escape_htmltag($langs->trans('Preview')); ?>" srcdoc="<?php echo htmlspecialchars($preview[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"></iframe>
            <div class="opacitymedium reedcrm-newsletter-preview-help">
                <?php echo $langs->trans(!empty($recipientsShown) ? 'NewsletterPreviewFirstRecipient' : 'NewsletterPreviewNoRecipient', !empty($recipientsShown) ? $recipientsShown[0]['email'] : ''); ?>
            </div>
        </div>
    </div>
</div>
<div class="clearboth"></div>

<?php require __DIR__ . '/newsletter_recipients.tpl.php'; ?>

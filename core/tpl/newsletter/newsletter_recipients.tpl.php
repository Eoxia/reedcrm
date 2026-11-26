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
 * \file    core/tpl/newsletter/newsletter_recipients.tpl.php
 * \ingroup reedcrm
 * \brief   First recipients of a newsletter, as the tags give them today
 *
 * Variables from view/newsletter/newsletter_card.php:
 * @var ReedcrmNewsletter $object          Newsletter
 * @var Translate         $langs           Translations
 * @var int               $recipientsCount Number of recipients
 * @var array             $recipientsShown First recipients
 * @var array             $recipientStates After the creation: [index => state of getRecipientState()]
 * @var int               $hiddenRecipients Recipients out of the reach of the user, not listed
 * @var array             $excluded        Targets left out: [unsubscribed, noemail, filtered]
 */


global $db, $user;

$isProject       = $object->target_type === ReedcrmNewsletter::TARGET_PROJECT;
$isAfterCreation = $object->isAfterCreation();
$nbColumns       = 2 + ($isProject ? 1 : 0) + ($isAfterCreation ? 2 : 0);
$canReadProjects = $user->hasRight('projet', 'lire');

// Badge of each state, as the statuses of Dolibarr
$stateBadges = [
    ReedcrmNewsletter::RECIPIENT_SENT     => 'status4',
    ReedcrmNewsletter::RECIPIENT_ERROR    => 'status8',
    ReedcrmNewsletter::RECIPIENT_PENDING  => 'status0',
    ReedcrmNewsletter::RECIPIENT_DUE      => 'status1',
    ReedcrmNewsletter::RECIPIENT_UPCOMING => 'status0',
    ReedcrmNewsletter::RECIPIENT_SKIPPED  => 'status9',
    ReedcrmNewsletter::RECIPIENT_NO_DATE  => 'status9',
];
$isActive = (int) $object->status === ReedcrmNewsletter::STATUS_SCHEDULED;
?>
<br>
<?php print load_fiche_titre($langs->trans('NewsletterRecipients') . ' <span class="opacitymedium">(' . $recipientsCount . ')</span>', '', 'fontawesome_fa-users_fas'); ?>
<div class="div-table-responsive-no-min">
    <table class="noborder centpercent reedcrm-newsletter-recipients">
        <tr class="liste_titre">
            <?php // The name with its address under it, then the sending: on a phone both stay in sight together ?>
            <th><?php echo $langs->trans('NewsletterRecipient'); ?></th>
            <?php if ($isAfterCreation) : ?>
                <th><?php echo $langs->trans('NewsletterRecipientSending'); ?></th>
                <th class="hideonsmartphone"><?php echo $langs->trans($isProject ? 'NewsletterProjectCreated' : 'NewsletterContactCreated'); ?></th>
            <?php endif; ?>
            <th class="hideonsmartphone"><?php echo $langs->trans('ThirdParty'); ?></th>
            <?php if ($isProject) : ?>
                <th><?php echo $langs->trans('Project'); ?></th>
            <?php endif; ?>
        </tr>
        <?php if ($recipientsCount === 0) : ?>
            <tr class="oddeven">
                <td colspan="<?php echo $nbColumns; ?>">
                    <?php if (!empty($excluded['filtered'])) : ?>
                        <?php // The tags give recipients, the narrower targeting leaves them all out: said so, not "check the tags" ?>
                        <span class="warning"><?php echo $langs->trans('NewsletterNoRecipientFiltered', (int) $excluded['filtered']); ?></span>
                    <?php else : ?>
                        <span class="opacitymedium"><?php echo $langs->trans($isAfterCreation ? 'NewsletterNoRecipientYet' : 'NewsletterNoRecipient'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endif; ?>
        <?php foreach ($recipientsShown as $index => $recipient) : ?>
            <?php
            // A link only where the card would open: a sales representative sees the contacts of his customers
            $canOpen = reedcrm_newsletter_user_can_open_thirdparty($user, $db, (int) $recipient['fk_soc']);
            $name    = dol_escape_htmltag(dolGetFirstLastname($recipient['firstname'], $recipient['lastname']));
            ?>
            <tr class="oddeven">
                <td class="tdoverflowmax250">
                    <?php if (!empty($recipient['fk_contact']) && $canOpen) : ?>
                        <a href="<?php echo DOL_URL_ROOT . '/contact/card.php?id=' . ((int) $recipient['fk_contact']); ?>"><?php echo img_picto('', 'contact', 'class="pictofixedwidth"') . $name; ?></a>
                    <?php elseif ($name !== '') : ?>
                        <?php echo img_picto('', 'contact', 'class="pictofixedwidth"') . $name; ?>
                    <?php endif; ?>
                    <div class="small opacitymedium tdoverflowmax250"><?php echo dol_escape_htmltag($recipient['email']); ?></div>
                </td>
                <?php if ($isAfterCreation) : ?>
                    <?php
                    $state    = $recipientStates[$index] ?? ['state' => ReedcrmNewsletter::RECIPIENT_NO_DATE, 'date' => 0, 'next' => 0, 'sent_at' => 0];
                    $stateKey = $state['state'] === ReedcrmNewsletter::RECIPIENT_DUE && !$isActive ? 'due_draft' : $state['state'];
                    $date     = !empty($state['date']) ? dol_print_date($state['date'], 'day') : '';
                    if ($state['state'] === ReedcrmNewsletter::RECIPIENT_SENT && !empty($state['sent_at'])) {
                        $date = dol_print_date($state['sent_at'], 'day');
                    }
                    $label = $langs->trans('NewsletterRecipientState_' . $stateKey, $date);
                    ?>
                    <td>
                        <?php echo dolGetStatus($label, $label, '', $stateBadges[$state['state']] ?? 'status0', 1); ?>
                        <?php if (!empty($state['next']) && $state['next'] !== $state['date']) : ?>
                            <div class="opacitymedium small"><?php echo $langs->trans('NewsletterRecipientNext', dol_print_date($state['next'], 'day')); ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="nowraponall hideonsmartphone"><?php echo !empty($recipient['date_creation']) ? dol_print_date($recipient['date_creation'], 'day') : ''; ?></td>
                <?php endif; ?>
                <td class="tdoverflowmax200 hideonsmartphone">
                    <?php if (!empty($recipient['fk_soc']) && $canOpen) : ?>
                        <a href="<?php echo DOL_URL_ROOT . '/societe/card.php?socid=' . ((int) $recipient['fk_soc']); ?>"><?php echo img_picto('', 'company', 'class="pictofixedwidth"') . dol_escape_htmltag($recipient['thirdparty_name']); ?></a>
                    <?php elseif (!empty($recipient['fk_soc'])) : ?>
                        <?php echo img_picto('', 'company', 'class="pictofixedwidth"') . dol_escape_htmltag($recipient['thirdparty_name']); ?>
                    <?php endif; ?>
                </td>
                <?php if ($isProject) : ?>
                    <td class="tdoverflowmax200">
                        <?php $projectLabel = img_picto('', 'project', 'class="pictofixedwidth"') . dol_escape_htmltag($recipient['project_ref'] . ' - ' . $recipient['project_title']); ?>
                        <?php if ($canReadProjects && $canOpen) : ?>
                            <a href="<?php echo DOL_URL_ROOT . '/projet/card.php?id=' . ((int) $recipient['fk_project']); ?>"><?php echo $projectLabel; ?></a>
                        <?php else : ?>
                            <?php echo $projectLabel; ?>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php $notListed = $recipientsCount - $hiddenRecipients - count($recipientsShown); ?>
        <?php if ($notListed > 0) : ?>
            <tr class="oddeven">
                <td colspan="<?php echo $nbColumns; ?>"><span class="opacitymedium"><?php echo $langs->trans('NewsletterMoreRecipients', $notListed); ?></span></td>
            </tr>
        <?php endif; ?>
        <?php if ($hiddenRecipients > 0) : ?>
            <?php // The contacts of the other sales representatives: counted, not shown ?>
            <tr class="oddeven">
                <td colspan="<?php echo $nbColumns; ?>"><span class="opacitymedium"><?php echo $langs->trans('NewsletterHiddenRecipients', $hiddenRecipients); ?></span></td>
            </tr>
        <?php endif; ?>
    </table>
</div>

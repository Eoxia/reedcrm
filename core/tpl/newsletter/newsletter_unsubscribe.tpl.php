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
 * \file    core/tpl/newsletter/newsletter_unsubscribe.tpl.php
 * \ingroup reedcrm
 * \brief   Public unsubscribe page of the newsletters, in the public card of Saturne (page-public-card) as the
 *          other public pages of the modules: signature, tickets, answers
 *
 * Variables from public/newsletter/unsubscribe.php:
 * @var Translate $langs     Translations
 * @var string    $pageState test, confirm, already, done, invalid or error
 * @var string    $pageUrl   URL of the page
 * @var string    $email     Address
 * @var string    $key       Key of the link
 * @var int       $entity    Entity
 * @var int       $since     Date of the unsubscription, state already
 * @var int       $newsletterId Newsletter of the link, 0 when unknown
 */

global $mysoc;

// Icon and words of each state
$states = [
    'test'    => ['icon' => 'fa-flask', 'title' => 'NewsletterUnsubscribeTestTitle'],
    'confirm' => ['icon' => 'fa-envelope-open-text', 'title' => 'NewsletterUnsubscribeTitle'],
    'already' => ['icon' => 'fa-check', 'title' => 'NewsletterUnsubscribeAlreadyTitle'],
    'done'    => ['icon' => 'fa-check', 'title' => 'NewsletterUnsubscribeDoneTitle'],
    'undone'  => ['icon' => 'fa-envelope-open-text', 'title' => 'NewsletterUnsubscribeUndoneTitle'],
    'invalid' => ['icon' => 'fa-unlink', 'title' => 'NewsletterUnsubscribeInvalidTitle'],
    'error'   => ['icon' => 'fa-exclamation-triangle', 'title' => 'NewsletterUnsubscribeErrorTitle'],
];
$state       = $states[$pageState] ?? $states['invalid'];
$companyMail = !empty($mysoc->email) && isValidEmail($mysoc->email) ? $mysoc->email : '';
$mailLink    = $companyMail !== '' ? '<a href="mailto:' . dol_escape_htmltag($companyMail) . '">' . dol_escape_htmltag($companyMail) . '</a>' : '';
$stateClass  = in_array($pageState, ['already', 'done', 'undone'], true) ? 'done' : (in_array($pageState, ['invalid', 'error'], true) ? 'invalid' : 'confirm');
?>
<div class="public-card__container reedcrm-newsletter-unsubscribe reedcrm-newsletter-unsubscribe--<?php echo $stateClass; ?>">
    <?php if (!empty($mysoc->name)) : ?>
        <div class="reedcrm-newsletter-unsubscribe__company"><?php echo dol_escape_htmltag($mysoc->name); ?></div>
    <?php endif; ?>

    <div class="reedcrm-newsletter-unsubscribe__icon"><i class="fas <?php echo $state['icon']; ?>"></i></div>
    <h1 class="reedcrm-newsletter-unsubscribe__title"><?php echo $langs->trans($state['title']); ?></h1>

    <?php if ($pageState === 'test') : ?>
        <p class="reedcrm-newsletter-unsubscribe__text"><?php echo $langs->trans('NewsletterUnsubscribeTest'); ?></p>
    <?php elseif ($pageState === 'confirm') : ?>
        <p class="reedcrm-newsletter-unsubscribe__text"><?php echo $langs->trans('NewsletterUnsubscribeConfirm'); ?></p>
        <div class="reedcrm-newsletter-unsubscribe__email"><?php echo dol_escape_htmltag($email); ?></div>
        <form method="POST" action="<?php echo dol_escape_htmltag($pageUrl); ?>">
            <input type="hidden" name="action" value="unsubscribe">
            <input type="hidden" name="email" value="<?php echo dol_escape_htmltag($email); ?>">
            <input type="hidden" name="key" value="<?php echo dol_escape_htmltag($key); ?>">
            <input type="hidden" name="entity" value="<?php echo (int) $entity; ?>">
            <?php if ($newsletterId > 0) : ?>
                <input type="hidden" name="nl" value="<?php echo (int) $newsletterId; ?>">
            <?php endif; ?>
            <button type="submit" class="wpeo-button button-blue button-radius-2 reedcrm-newsletter-unsubscribe__button"><?php echo $langs->trans('NewsletterUnsubscribeButton'); ?></button>
        </form>
        <p class="reedcrm-newsletter-unsubscribe__note">
            <?php echo $langs->trans('NewsletterUnsubscribeScope'); ?>
            <?php if ($mailLink !== '') : ?>
                <br><?php echo $langs->trans('NewsletterUnsubscribeNotYours', $mailLink); ?>
            <?php endif; ?>
        </p>
    <?php elseif ($pageState === 'already' || $pageState === 'done') : ?>
        <div class="reedcrm-newsletter-unsubscribe__email"><?php echo dol_escape_htmltag($email); ?></div>
        <p class="reedcrm-newsletter-unsubscribe__text">
            <?php echo $pageState === 'already' ? $langs->trans('NewsletterUnsubscribeAlready', dol_print_date($since, 'day')) : $langs->trans('NewsletterUnsubscribeDone'); ?>
        </p>
        <?php if ($pageState === 'done') : ?>
            <?php // A wrong click, just after: undone from here, see public/newsletter/unsubscribe.php ?>
            <form method="POST" action="<?php echo dol_escape_htmltag($pageUrl); ?>" class="reedcrm-newsletter-unsubscribe__undo">
                <input type="hidden" name="action" value="undo">
                <input type="hidden" name="email" value="<?php echo dol_escape_htmltag($email); ?>">
                <input type="hidden" name="key" value="<?php echo dol_escape_htmltag($key); ?>">
                <input type="hidden" name="entity" value="<?php echo (int) $entity; ?>">
                <?php if ($newsletterId > 0) : ?>
                    <input type="hidden" name="nl" value="<?php echo (int) $newsletterId; ?>">
                <?php endif; ?>
                <button type="submit" class="reedcrm-newsletter-unsubscribe__link-button"><?php echo $langs->trans('NewsletterUnsubscribeUndo'); ?></button>
            </form>
        <?php endif; ?>
        <p class="reedcrm-newsletter-unsubscribe__note">
            <?php echo $langs->trans('NewsletterUnsubscribeScope'); ?>
            <?php if ($mailLink !== '') : ?>
                <br><?php echo $langs->trans('NewsletterUnsubscribeMistake', $mailLink); ?>
            <?php endif; ?>
        </p>
    <?php elseif ($pageState === 'undone') : ?>
        <div class="reedcrm-newsletter-unsubscribe__email"><?php echo dol_escape_htmltag($email); ?></div>
        <p class="reedcrm-newsletter-unsubscribe__text"><?php echo $langs->trans('NewsletterUnsubscribeUndone'); ?></p>
    <?php elseif ($pageState === 'error') : ?>
        <p class="reedcrm-newsletter-unsubscribe__text"><?php echo $langs->trans('NewsletterUnsubscribeError'); ?></p>
        <?php if ($mailLink !== '') : ?>
            <p class="reedcrm-newsletter-unsubscribe__note"><?php echo $langs->trans('NewsletterUnsubscribeWriteUs', $mailLink); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <p class="reedcrm-newsletter-unsubscribe__text"><?php echo $langs->trans('NewsletterUnsubscribeInvalid'); ?></p>
        <?php // A way out all the same: the address of the company ?>
        <?php if ($mailLink !== '') : ?>
            <p class="reedcrm-newsletter-unsubscribe__note"><?php echo $langs->trans('NewsletterUnsubscribeWriteUs', $mailLink); ?></p>
        <?php endif; ?>
    <?php endif; ?>
</div>

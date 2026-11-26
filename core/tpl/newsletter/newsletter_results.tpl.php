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
 * \file    core/tpl/newsletter/newsletter_results.tpl.php
 * \ingroup reedcrm
 * \brief   Results of the editions of a monthly newsletter, under the buttons: one line per edition, the newest first,
 *          the one open lit. Shown once an edition of the series has left
 *
 * Variables from view/newsletter/newsletter_card.php:
 * @var ReedcrmNewsletter $object        Newsletter open
 * @var Form              $form          Form helper
 * @var Translate         $langs         Translations
 * @var array             $editionSeries Editions: [[newsletter, stats => [sent, errors, unsubscribed]]]
 */

$hasSent = false;
foreach ($editionSeries as $edition) {
    if ($edition['stats']['sent'] > 0 || $edition['stats']['errors'] > 0) {
        $hasSent = true;
    }
}

/**
 * Unsubscribe rate of an edition, as a text: "1.5 %", "–" when nothing left
 *
 * @param  array $stats Results, see ReedcrmNewsletter::getSendStats()
 * @return string
 */
$rate = function (array $stats): string {
    return $stats['sent'] > 0 ? price(round(100 * $stats['unsubscribed'] / $stats['sent'], 1), 0, '', 0, -1, 1) . ' %' : '–';
};
?>
<?php if ($hasSent) : ?>
    <div class="reedcrm-newsletter-results">
        <?php print load_fiche_titre($langs->trans('NewsletterResults'), '', 'fontawesome_fa-chart-line_fas'); ?>
        <?php // On a phone each edition is a card: its month, then one line per figure (see .reedcrm-newsletter-stack) ?>
        <div class="div-table-responsive-no-min">
            <table class="noborder centpercent reedcrm-newsletter-stack">
                <tr class="liste_titre">
                    <th><?php echo $langs->trans('NewsletterEdition'); ?></th>
                    <th><?php echo $langs->trans('NewsletterResultsSent'); ?></th>
                    <th class="right"><?php echo $langs->trans('NewsletterNbSent'); ?></th>
                    <th class="right"><?php echo $langs->trans('NewsletterNbErrors'); ?></th>
                    <th class="right"><?php echo $langs->trans('NewsletterUnsubscribes'); ?></th>
                    <th class="right"><?php echo $form->textwithpicto($langs->trans('NewsletterUnsubscribeRate'), $langs->trans('NewsletterUnsubscribeRateHelp')); ?></th>
                </tr>
                <?php foreach ($editionSeries as $edition) : ?>
                    <?php $newsletter = $edition['newsletter']; ?>
                    <?php $stats = $edition['stats']; ?>
                    <tr class="oddeven<?php echo (int) $newsletter->id === (int) $object->id ? ' reedcrm-newsletter-results--current' : ''; ?>">
                        <?php // The month first: it is what tells two editions apart, the label ends with it and is cut there ?>
                        <td class="reedcrm-newsletter-stack-head">
                            <span class="reedcrm-newsletter-edition-month"><?php echo !empty($newsletter->date_send) ? dol_escape_htmltag(dol_ucfirst(dol_print_date($newsletter->date_send, '%B %Y'))) : '–'; ?></span>
                            <span class="reedcrm-newsletter-edition-ref" title="<?php echo dol_escape_htmltag($newsletter->ref . ' - ' . $newsletter->label); ?>"><?php echo $newsletter->getNomUrl(1, '', 1, '', -1, 1); ?></span>
                        </td>
                        <td class="nowraponall" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterResultsSent')); ?>">
                            <?php // A date as soon as mails left, even put back to draft since ?>
                            <?php if (!empty($newsletter->date_last_send)) : ?>
                                <?php echo dol_print_date($newsletter->date_last_send, 'day'); ?>
                                <?php if ((int) $newsletter->status === ReedcrmNewsletter::STATUS_DRAFT) : ?>
                                    <span class="opacitymedium small"><?php echo $langs->trans('NewsletterBackToDraft'); ?></span>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo $newsletter->getLibStatut(5); ?>
                            <?php endif; ?>
                        </td>
                        <td class="right" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterNbSent')); ?>"><?php echo (int) $stats['sent']; ?></td>
                        <td class="right" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterNbErrors')); ?>"><?php echo $stats['errors'] > 0 ? '<span class="badge badge-danger">' . ((int) $stats['errors']) . '</span>' : '0'; ?></td>
                        <td class="right" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterUnsubscribes')); ?>"><?php echo (int) $stats['unsubscribed']; ?></td>
                        <td class="right" data-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterUnsubscribeRate')); ?>"><?php echo dol_escape_htmltag($rate($stats)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="opacitymedium small reedcrm-newsletter-results-help"><?php echo $langs->trans('NewsletterResultsHelp'); ?></div>
    </div>
<?php endif; ?>

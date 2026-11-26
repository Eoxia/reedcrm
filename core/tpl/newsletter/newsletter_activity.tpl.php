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
 * \file    core/tpl/newsletter/newsletter_activity.tpl.php
 * \ingroup reedcrm
 * \brief   Under the buttons, as on the cards of Dolibarr: the log of the sendings on the left, the latest events
 *          linked to the newsletter on the right (the mails sent when the setup asks for it, the events added by hand)
 *
 * Variables from view/newsletter/newsletter_card.php:
 * @var ReedcrmNewsletter $object       Newsletter
 * @var Translate         $langs        Translations
 * @var array             $sendLog      Last lines of the log
 * @var int               $sendLogCount Number of lines of the log
 * @var FormActions|null  $formActions  Events helper, null without the agenda module or its rights
 */
?>
<div class="fichecenter reedcrm-newsletter-activity">
    <div class="<?php echo $formActions !== null ? 'fichehalfleft' : ''; ?>">
        <?php require __DIR__ . '/newsletter_send_log.tpl.php'; ?>
    </div>
    <?php if ($formActions !== null) : ?>
        <div class="fichehalfright">
            <?php $formActions->showactions($object, ReedcrmNewsletter::AGENDA_ELEMENT_TYPE, 0, 1, '', 10); ?>
        </div>
    <?php endif; ?>
</div>

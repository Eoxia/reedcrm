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
 * \file    core/tpl/newsletter/newsletter_tag_chips.tpl.php
 * \ingroup reedcrm
 * \brief   Tags of a newsletter as on the cards of Dolibarr: each one in its colour, linked to its page. Used for the
 *          tags chosen and for the tags left out
 *
 * Variables from core/tpl/newsletter/newsletter_view.tpl.php:
 * @var array     $chips Tags: [url, label, color, class]
 * @var Translate $langs Translations
 */
?>
<?php if (empty($chips)) : ?>
    <span class="opacitymedium"><?php echo $langs->trans('None'); ?></span>
<?php else : ?>
    <div class="select2-container-multi-dolibarr">
        <ul class="select2-choices-dolibarr">
            <?php foreach ($chips as $index => $chip) : ?>
                <?php // The color is the one of the tag, as Form::showCategories() prints it ?>
                <li class="select2-search-choice-dolibarr noborderoncategories <?php echo $chip['class'] . ($index === 0 ? ' nomarginleft' : ''); ?>" style="background: #<?php echo dol_escape_htmltag($chip['color']); ?>;">
                    <a href="<?php echo $chip['url']; ?>" class="<?php echo $chip['class']; ?>"><?php echo img_picto('', 'category', 'class="paddingright"') . dol_escape_htmltag($chip['label']); ?></a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

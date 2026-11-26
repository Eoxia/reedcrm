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
 * \file    core/tpl/newsletter/newsletter_block.tpl.php
 * \ingroup reedcrm
 * \brief   One block of the simple editor. Printed for each block saved, and once per type inside a <template> that
 *          js/modules/newsletter.js copies when a block is added. The fields have no name: the script gathers them in
 *          the hidden field body_blocks
 *
 * Variables from core/tpl/newsletter/newsletter_form.tpl.php:
 * @var array     $block      Block, see reedcrm_newsletter_clean_blocks()
 * @var string    $imageBase  Public address of the images, the path of the file is added
 * @var string    $thumbBase  Thumb of an image of the library not public yet, its name is added
 * @var Translate $langs      Translations
 */

$blockType  = $block['type'];
$blockIcons = ['title' => 'fa-heading', 'text' => 'fa-paragraph', 'image' => 'fa-image', 'button' => 'fa-hand-pointer', 'divider' => 'fa-minus'];
$blockTypes = reedcrm_newsletter_block_types();
$imageSrc   = '';
if ($blockType === 'image') {
    if ($block['file'] !== '') {
        $imageSrc = $imageBase . rawurlencode($block['file']);
    } elseif (($block['library'] ?? '') !== '') {
        $imageSrc = $thumbBase . rawurlencode($block['library']);
    } else {
        $imageSrc = $block['url'];
    }
}
?>
<div class="reedcrm-newsletter-block" data-type="<?php echo $blockType; ?>">
    <div class="reedcrm-newsletter-block-head">
        <span class="reedcrm-newsletter-block-name"><i class="fas <?php echo $blockIcons[$blockType]; ?> pictofixedwidth"></i><?php echo $langs->trans($blockTypes[$blockType]); ?></span>
        <span class="reedcrm-newsletter-block-tools">
            <button type="button" class="reedcrm-newsletter-block-tool reedcrm-newsletter-block-up" title="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockUp')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockUp')); ?>"><i class="fas fa-arrow-up"></i></button>
            <button type="button" class="reedcrm-newsletter-block-tool reedcrm-newsletter-block-down" title="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockDown')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockDown')); ?>"><i class="fas fa-arrow-down"></i></button>
            <button type="button" class="reedcrm-newsletter-block-tool reedcrm-newsletter-block-remove" title="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockRemove')); ?>" aria-label="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockRemove')); ?>"><i class="fas fa-trash-alt"></i></button>
        </span>
    </div>
    <div class="reedcrm-newsletter-block-body">
        <?php if ($blockType === 'title') : ?>
            <input type="text" class="flat reedcrm-newsletter-block-field" data-field="text" maxlength="255" value="<?php echo dol_escape_htmltag($block['text']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockTitlePlaceholder')); ?>">
        <?php elseif ($blockType === 'text') : ?>
            <div class="reedcrm-newsletter-text-tools">
                <button type="button" class="wpeo-button button-grey button-size-small reedcrm-newsletter-button reedcrm-newsletter-text-tool" data-format="bold" title="<?php echo dol_escape_htmltag($langs->trans('NewsletterTextBoldHelp')); ?>"><i class="fas fa-bold pictofixedwidth"></i><?php echo $langs->trans('NewsletterTextBold'); ?></button>
                <button type="button" class="wpeo-button button-grey button-size-small reedcrm-newsletter-button reedcrm-newsletter-text-tool" data-format="link" title="<?php echo dol_escape_htmltag($langs->trans('NewsletterTextLinkHelp')); ?>"><i class="fas fa-link pictofixedwidth"></i><?php echo $langs->trans('NewsletterTextLink'); ?></button>
                <span class="opacitymedium reedcrm-newsletter-text-help"><?php echo $langs->trans('NewsletterTextHelp'); ?></span>
            </div>
            <textarea class="flat reedcrm-newsletter-block-field" data-field="text" rows="6" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterBlockTextPlaceholder')); ?>"><?php echo dol_escape_htmltag($block['text'], 0, 1); ?></textarea>
        <?php elseif ($blockType === 'image') : ?>
            <div class="reedcrm-newsletter-image-row">
                <div class="reedcrm-newsletter-image-thumb<?php echo $imageSrc === '' ? ' reedcrm-newsletter-image-thumb--empty' : ''; ?>">
                    <img src="<?php echo dol_escape_htmltag($imageSrc); ?>" alt=""<?php echo $imageSrc === '' ? ' hidden' : ''; ?>>
                    <i class="fas fa-image"<?php echo $imageSrc === '' ? '' : ' hidden'; ?>></i>
                </div>
                <div class="reedcrm-newsletter-image-fields">
                    <button type="button" class="wpeo-button button-grey button-size-small reedcrm-newsletter-button reedcrm-newsletter-pick-image"><i class="fas fa-images pictofixedwidth"></i><?php echo $langs->trans('NewsletterPickImage'); ?></button>
                    <input type="hidden" class="reedcrm-newsletter-block-field" data-field="file" value="<?php echo dol_escape_htmltag($block['file']); ?>">
                    <input type="hidden" class="reedcrm-newsletter-block-field" data-field="library" value="<?php echo dol_escape_htmltag($block['library'] ?? ''); ?>">
                    <input type="text" inputmode="url" class="flat reedcrm-newsletter-block-field" data-field="url" maxlength="2000" value="<?php echo dol_escape_htmltag($block['url']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterImageUrlPlaceholder')); ?>">
                    <input type="text" class="flat reedcrm-newsletter-block-field" data-field="alt" maxlength="255" value="<?php echo dol_escape_htmltag($block['alt']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterImageAltPlaceholder')); ?>">
                    <input type="text" inputmode="url" class="flat reedcrm-newsletter-block-field" data-field="link" maxlength="2000" value="<?php echo dol_escape_htmltag($block['link']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterImageLinkPlaceholder')); ?>">
                </div>
            </div>
        <?php elseif ($blockType === 'button') : ?>
            <div class="reedcrm-newsletter-button-fields">
                <input type="text" class="flat reedcrm-newsletter-block-field" data-field="label" maxlength="100" value="<?php echo dol_escape_htmltag($block['label']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterButtonLabelPlaceholder')); ?>">
                <input type="text" inputmode="url" class="flat reedcrm-newsletter-block-field" data-field="url" maxlength="2000" value="<?php echo dol_escape_htmltag($block['url']); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterButtonUrlPlaceholder')); ?>">
            </div>
        <?php else : ?>
            <span class="opacitymedium"><?php echo $langs->trans('NewsletterBlockDividerHelp'); ?></span>
        <?php endif; ?>
        <div class="reedcrm-newsletter-block-warning warning hidden"></div>
    </div>
</div>

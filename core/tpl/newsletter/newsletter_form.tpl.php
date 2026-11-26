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
 * \file    core/tpl/newsletter/newsletter_form.tpl.php
 * \ingroup reedcrm
 * \brief   Create and edit form of a newsletter. What a monthly newsletter needs comes first (label, tags, subject,
 *          content, date); the type, the target, the trigger and the sender fold away under "Advanced options"
 *
 * Variables from view/newsletter/newsletter_card.php:
 * @var ReedcrmNewsletter $object            Newsletter
 * @var Form              $form              Form helper
 * @var Translate         $langs             Translations
 * @var bool              $isCreate          Create or edit
 * @var string            $cardUrl           URL of the card
 * @var array             $tagChoices        [target => tags of select_all_categories(), mode 3]
 * @var array             $emailTemplates    Templates of reedcrm_newsletter_get_email_templates()
 * @var array             $substitutionKeys  Keys of reedcrm_newsletter_substitution_keys()
 * @var string            $emailTemplatesUrl Setup page of the email templates, '' when not admin
 * @var string            $cronFrequency     How often the job of the newsletters runs, as a text
 * @var array             $blocksData        Blocks of the simple editor, see reedcrm_newsletter_clean_blocks()
 * @var array             $editorUrls        [editor, imageBase, thumbBase, library, canUpload, maxUpload]
 * @var bool              $advancedOpen      Whether the advanced settings are shown at once
 * @var string            $salesRepSelect    Select of the sales representative of the targeting
 * @var bool              $canSaveAndSchedule Whether "Save and plan" is offered
 */

$isReminder      = $object->mail_type === ReedcrmNewsletter::TYPE_REMINDER;
$contentMode     = $object->content_mode;
$isAfterCreation = $object->isAfterCreation();
$tagIds          = $object->getTagIds();
$delayUnits      = ['d' => $langs->trans('Days'), 'w' => $langs->trans('Weeks'), 'm' => $langs->trans('Month'), 'y' => $langs->trans('Years')];
$modeHidden      = function (array $modes) use ($contentMode): string {
    return in_array($contentMode, $modes, true) ? '' : ' hideobject';
};
$advancedClass   = 'reedcrm-newsletter-advanced-row' . ($advancedOpen ? '' : ' reedcrm-newsletter-collapsed');
$imageBase       = $editorUrls['imageBase'];
$thumbBase       = $editorUrls['thumbBase'];
$projectKeys     = reedcrm_newsletter_project_keys();
$maxUploadText   = price(round($editorUrls['maxUpload'] / 1024, 1), 0, $langs, 0, 0);
$blockDefaults   = [
    'title'   => ['type' => 'title', 'text' => ''],
    'text'    => ['type' => 'text', 'text' => ''],
    'image'   => ['type' => 'image', 'file' => '', 'library' => '', 'url' => '', 'alt' => '', 'link' => ''],
    'button'  => ['type' => 'button', 'label' => '', 'url' => ''],
    'divider' => ['type' => 'divider'],
];
$blockIcons      = ['title' => 'fa-heading', 'text' => 'fa-paragraph', 'image' => 'fa-image', 'button' => 'fa-hand-pointer', 'divider' => 'fa-minus'];
?>
<form method="POST" action="<?php echo $cardUrl . ($isCreate ? '' : '?id=' . $object->id); ?>" class="reedcrm-newsletter-form" id="reedcrm-newsletter-form">
    <input type="hidden" name="token" value="<?php echo newToken(); ?>">
    <input type="hidden" name="action" value="<?php echo $isCreate ? 'add' : 'update'; ?>">
    <?php if (!$isCreate) : ?>
        <input type="hidden" name="id" value="<?php echo $object->id; ?>">
    <?php endif; ?>
    <?php if (!$isCreate && (int) $object->status === ReedcrmNewsletter::STATUS_SCHEDULED) : ?>
        <div class="warning reedcrm-newsletter-notice"><?php echo img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('NewsletterEditScheduledNotice', !empty($object->date_send) ? dol_print_date($object->date_send, 'dayhour') : ''); ?></div>
    <?php endif; ?>

    <table class="border centpercent tableforfieldcreate">
        <tr>
            <td class="titlefieldcreate fieldrequired"><?php echo $langs->trans('Label'); ?></td>
            <td><input type="text" name="label" class="flat minwidth400" maxlength="255" value="<?php echo dol_escape_htmltag($object->label); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterLabelPlaceholder')); ?>" autofocus></td>
        </tr>

        <?php foreach ($tagChoices as $targetType => $tagsOfTarget) : ?>
            <tr class="reedcrm-newsletter-target-row<?php echo $object->target_type === $targetType ? '' : ' hideobject'; ?>" data-target="<?php echo $targetType; ?>">
                <td class="fieldrequired"><?php echo $form->textwithpicto($langs->trans($targetType === ReedcrmNewsletter::TARGET_PROJECT ? 'NewsletterTargetTagsProject' : 'NewsletterTargetTagsContact'), $langs->trans($targetType === ReedcrmNewsletter::TARGET_PROJECT ? 'NewsletterTargetTagsProjectHelp' : 'NewsletterTargetTagsContactHelp')); ?></td>
                <td>
                    <?php if (!isModEnabled('categorie')) : ?>
                        <span class="warning"><?php echo $langs->trans('NewsletterTagsModuleDisabled'); ?></span>
                    <?php elseif (empty($tagsOfTarget)) : ?>
                        <span class="opacitymedium"><?php echo $langs->trans('NewsletterNoTagOfType'); ?></span>
                    <?php else : ?>
                        <?php echo img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('tags_' . $targetType, $tagsOfTarget, $object->target_type === $targetType ? $tagIds : [], 0, 0, 'minwidth300 widthcentpercentminusx', 0, '90%'); ?>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <?php // Recipients of the targeting, counted again at each change of the tags or of the advanced filters ?>
        <tr>
            <td></td>
            <td><span class="reedcrm-newsletter-live-count opacitymedium" aria-live="polite"></span></td>
        </tr>

        <tr class="reedcrm-newsletter-mode-row<?php echo $modeHidden([ReedcrmNewsletter::MODE_BLOCKS, ReedcrmNewsletter::MODE_HTML]); ?>" data-modes="<?php echo ReedcrmNewsletter::MODE_BLOCKS . ' ' . ReedcrmNewsletter::MODE_HTML; ?>">
            <td class="fieldrequired"><?php echo $langs->trans('MailTopic'); ?></td>
            <td><input type="text" name="subject" class="flat minwidth400 widthcentpercentminusx reedcrm-newsletter-key-target" maxlength="255" value="<?php echo dol_escape_htmltag($object->subject); ?>" placeholder="<?php echo dol_escape_htmltag($langs->trans('NewsletterSubjectPlaceholder')); ?>"></td>
        </tr>

        <tr>
            <td class="fieldrequired"><?php echo $langs->trans('NewsletterContentMode'); ?></td>
            <td class="reedcrm-newsletter-choices">
                <?php foreach ([ReedcrmNewsletter::MODE_BLOCKS => 'NewsletterContentModeBlocks', ReedcrmNewsletter::MODE_HTML => 'NewsletterContentModeHtml', ReedcrmNewsletter::MODE_TEMPLATE => 'NewsletterContentModeTemplate'] as $mode => $modeLabel) : ?>
                    <label><input type="radio" name="content_mode" value="<?php echo $mode; ?>" class="reedcrm-newsletter-content-mode"<?php echo $contentMode === $mode ? ' checked' : ''; ?>> <?php echo $langs->trans($modeLabel); ?></label>
                <?php endforeach; ?>
                <span class="opacitymedium"><?php echo $langs->trans('NewsletterContentModeHelp'); ?></span>
            </td>
        </tr>

        <?php // Simple editor: the blocks on the left, the mail on the right, on the whole width of the form ?>
        <tr class="reedcrm-newsletter-mode-row<?php echo $modeHidden([ReedcrmNewsletter::MODE_BLOCKS]); ?>" data-modes="<?php echo ReedcrmNewsletter::MODE_BLOCKS; ?>">
            <td colspan="2" class="reedcrm-newsletter-wide-cell">
                <div class="fieldrequired reedcrm-newsletter-wide-label"><?php echo $langs->trans('NewsletterBlocks'); ?></div>
                <input type="hidden" name="body_blocks" class="reedcrm-newsletter-blocks-input" value="<?php echo dol_escape_htmltag(json_encode($blocksData)); ?>">
                <div class="reedcrm-newsletter-editor">
                    <div class="reedcrm-newsletter-builder" data-editor-url="<?php echo dol_escape_htmltag($editorUrls['editor']); ?>" data-image-base="<?php echo dol_escape_htmltag($imageBase); ?>" data-thumb-base="<?php echo dol_escape_htmltag($thumbBase); ?>" data-max-upload="<?php echo (int) $editorUrls['maxUpload']; ?>" data-error-too-large="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterImageTooLarge', $maxUploadText)); ?>" data-error-bad-url="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterBadUrl')); ?>" data-error-bad-image-url="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterBadImageUrl')); ?>" data-error-bad-text-link="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterBadTextLink', '%s')); ?>" data-error-button-incomplete="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterButtonIncomplete')); ?>" data-error-not-image="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterErrorNotAnImage')); ?>" data-error-request="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterEditorRequestError')); ?>" data-confirm-remove="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterBlockRemoveConfirm')); ?>" data-confirm-leave="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterLeaveUnsaved')); ?>" data-link-prompt="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterTextLinkPrompt')); ?>" data-link-label="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterTextLinkLabel')); ?>">
                        <div class="reedcrm-newsletter-builder-settings">
                            <label>
                                <span><?php echo $langs->trans('NewsletterLayout'); ?></span>
                                <select class="flat reedcrm-newsletter-setting" data-setting="layout">
                                    <?php foreach (reedcrm_newsletter_block_layouts() as $layout => $layoutLabel) : ?>
                                        <option value="<?php echo $layout; ?>"<?php echo $blocksData['layout'] === $layout ? ' selected' : ''; ?>><?php echo $langs->trans($layoutLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span><?php echo $langs->trans('NewsletterColor'); ?></span>
                                <input type="color" class="reedcrm-newsletter-setting" data-setting="color" value="<?php echo dol_escape_htmltag($blocksData['color']); ?>">
                            </label>
                            <label>
                                <input type="checkbox" class="reedcrm-newsletter-setting" data-setting="logo" value="1"<?php echo !empty($blocksData['logo']) ? ' checked' : ''; ?>>
                                <span><?php echo $langs->trans('NewsletterShowLogo'); ?></span>
                            </label>
                            <label class="reedcrm-newsletter-preheader">
                                <span><?php echo $form->textwithpicto($langs->trans('NewsletterPreheader'), $langs->trans('NewsletterPreheaderHelp')); ?></span>
                                <input type="text" class="flat reedcrm-newsletter-setting" data-setting="preheader" maxlength="150" value="<?php echo dol_escape_htmltag($blocksData['preheader'] ?? ''); ?>" placeholder="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('NewsletterPreheaderPlaceholder')); ?>">
                            </label>
                        </div>

                        <div class="reedcrm-newsletter-blocks">
                            <?php foreach ($blocksData['blocks'] as $block) : ?>
                                <?php require __DIR__ . '/newsletter_block.tpl.php'; ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="reedcrm-newsletter-add-blocks">
                            <span class="opacitymedium"><?php echo $langs->trans('NewsletterAddBlock'); ?></span>
                            <?php foreach (reedcrm_newsletter_block_types() as $type => $typeLabel) : ?>
                                <button type="button" class="wpeo-button button-grey button-size-small reedcrm-newsletter-button reedcrm-newsletter-add-block" data-add-block="<?php echo $type; ?>"><i class="fas <?php echo $blockIcons[$type]; ?> pictofixedwidth"></i><?php echo $langs->trans($typeLabel); ?></button>
                            <?php endforeach; ?>
                        </div>

                        <?php // Copied by the script when a block is added ?>
                        <?php foreach ($blockDefaults as $type => $block) : ?>
                            <template class="reedcrm-newsletter-block-template" data-type="<?php echo $type; ?>">
                                <?php require __DIR__ . '/newsletter_block.tpl.php'; ?>
                            </template>
                        <?php endforeach; ?>
                    </div>
                    <div class="reedcrm-newsletter-preview reedcrm-newsletter-preview--sticky">
                        <div class="reedcrm-newsletter-preview-title">
                            <?php echo $langs->trans('Preview'); ?>
                            <span class="reedcrm-newsletter-preview-subject opacitymedium"></span>
                        </div>
                        <iframe class="reedcrm-newsletter-blocks-frame" sandbox="" title="<?php echo dol_escape_htmltag($langs->trans('Preview')); ?>"></iframe>
                        <div class="opacitymedium reedcrm-newsletter-preview-help"><?php echo $langs->trans('NewsletterEditorPreviewHelp'); ?></div>
                    </div>
                </div>
            </td>
        </tr>

        <tr class="reedcrm-newsletter-mode-row<?php echo $modeHidden([ReedcrmNewsletter::MODE_HTML]); ?>" data-modes="<?php echo ReedcrmNewsletter::MODE_HTML; ?>">
            <td colspan="2" class="reedcrm-newsletter-wide-cell">
                <div class="fieldrequired reedcrm-newsletter-wide-label"><?php echo $form->textwithpicto($langs->trans('NewsletterBodyHtml'), $langs->trans('NewsletterBodyHtmlHelp')); ?></div>
                <div class="reedcrm-newsletter-editor">
                    <textarea name="body_html" class="reedcrm-newsletter-code reedcrm-newsletter-key-target" rows="22" spellcheck="false" placeholder="<!DOCTYPE html>&#10;<html>&#10;  <body>…</body>&#10;</html>"><?php echo htmlspecialchars((string) $object->body_html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
                    <div class="reedcrm-newsletter-preview">
                        <div class="reedcrm-newsletter-preview-title"><?php echo $langs->trans('Preview'); ?></div>
                        <iframe class="reedcrm-newsletter-preview-frame" sandbox="" title="<?php echo dol_escape_htmltag($langs->trans('Preview')); ?>"></iframe>
                    </div>
                </div>
            </td>
        </tr>

        <tr class="reedcrm-newsletter-mode-row<?php echo $modeHidden([ReedcrmNewsletter::MODE_TEMPLATE]); ?>" data-modes="<?php echo ReedcrmNewsletter::MODE_TEMPLATE; ?>">
            <td class="fieldrequired"><?php echo $langs->trans('NewsletterEmailTemplate'); ?></td>
            <td>
                <select name="fk_email_template" class="flat minwidth300 reedcrm-newsletter-template">
                    <option value="0">&nbsp;</option>
                    <?php foreach ($emailTemplates as $templateId => $template) : ?>
                        <option value="<?php echo $templateId; ?>" data-target="<?php echo in_array($template['type'], ['contact', 'project'], true) ? $template['type'] : ''; ?>" data-topic="<?php echo dol_escape_htmltag($template['topic']); ?>" data-content="<?php echo htmlspecialchars($template['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"<?php echo (int) $object->fk_email_template === $templateId ? ' selected' : ''; ?>>
                            <?php echo dol_escape_htmltag($template['label'] . ' (' . $langs->transnoentities('NewsletterTemplateType_' . $template['type']) . ($template['lang'] ? ', ' . $template['lang'] : '') . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($emailTemplatesUrl) : ?>
                    <a class="paddingleft" href="<?php echo $emailTemplatesUrl; ?>" target="_blank" rel="noopener"><?php echo img_picto('', 'setup', 'class="pictofixedwidth"') . $langs->trans('NewsletterManageTemplates'); ?></a>
                <?php endif; ?>
                <div class="opacitymedium"><?php echo $langs->trans('NewsletterTemplateHelp'); ?></div>
            </td>
        </tr>

        <tr class="reedcrm-newsletter-mode-row<?php echo $modeHidden([ReedcrmNewsletter::MODE_TEMPLATE]); ?>" data-modes="<?php echo ReedcrmNewsletter::MODE_TEMPLATE; ?>">
            <td class="tdtop"><?php echo $langs->trans('Preview'); ?></td>
            <td>
                <div class="reedcrm-newsletter-preview reedcrm-newsletter-preview--template">
                    <div class="reedcrm-newsletter-preview-title reedcrm-newsletter-template-topic"></div>
                    <iframe class="reedcrm-newsletter-template-frame" sandbox="" title="<?php echo dol_escape_htmltag($langs->trans('Preview')); ?>"></iframe>
                </div>
            </td>
        </tr>

        <tr>
            <td class="tdtop"><?php echo $langs->trans('NewsletterSubstitutionKeys'); ?></td>
            <td>
                <div class="reedcrm-newsletter-keys">
                    <?php foreach ($substitutionKeys as $key => $description) : ?>
                        <?php // The keys of the projects for the projects only: to contacts they stay empty ?>
                        <?php $isProjectKey = in_array($key, $projectKeys, true); ?>
                        <button type="button" class="reedcrm-newsletter-key<?php echo $isProjectKey ? ' reedcrm-newsletter-target-only' . ($object->target_type === ReedcrmNewsletter::TARGET_PROJECT ? '' : ' hideobject') : ''; ?>"<?php echo $isProjectKey ? ' data-target="' . ReedcrmNewsletter::TARGET_PROJECT . '"' : ''; ?> data-key="<?php echo $key; ?>" title="<?php echo dol_escape_htmltag($langs->trans('NewsletterKeyInsert')); ?>"><code><?php echo $key; ?></code> <?php echo $langs->trans($description); ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="opacitymedium"><?php echo $langs->trans('NewsletterSubstitutionKeysClickHelp') . ' ' . $langs->trans('NewsletterSubstitutionKeysHelpSimple'); ?></div>
            </td>
        </tr>

        <tr>
            <td>
                <span class="reedcrm-newsletter-trigger-only<?php echo $isAfterCreation ? ' hideobject' : ''; ?>" data-trigger="<?php echo ReedcrmNewsletter::TRIGGER_DATE; ?>"><?php echo $form->textwithpicto($langs->trans('NewsletterDateSend'), $langs->trans('NewsletterDateSendHelp', $cronFrequency)); ?></span>
                <span class="reedcrm-newsletter-trigger-only<?php echo $isAfterCreation ? '' : ' hideobject'; ?>" data-trigger="<?php echo ReedcrmNewsletter::TRIGGER_CREATION; ?>"><?php echo $form->textwithpicto($langs->trans('NewsletterStartDate'), $langs->trans('NewsletterStartDateHelp')); ?></span>
            </td>
            <td><?php echo $form->selectDate($object->date_send ?: -1, 'date_send', 1, 1, 1, '', 1, 1); ?></td>
        </tr>

        <tr class="reedcrm-newsletter-auto-next-row<?php echo $object->canPrepareNext() ? '' : ' hideobject'; ?>">
            <td></td>
            <td>
                <label><input type="checkbox" name="auto_next" value="1"<?php echo !empty($object->auto_next) ? ' checked' : ''; ?>> <?php echo $langs->trans('NewsletterAutoNextLabel'); ?></label>
                <div class="opacitymedium reedcrm-newsletter-inline-help"><?php echo $langs->trans('NewsletterAutoNextHelp', ReedcrmNewsletter::PREPARE_REMINDER_DAYS); ?></div>
            </td>
        </tr>

        <tr class="reedcrm-newsletter-advanced-toggle-row">
            <td colspan="2">
                <button type="button" class="reedcrm-newsletter-advanced-toggle" aria-expanded="<?php echo $advancedOpen ? 'true' : 'false'; ?>">
                    <i class="fas fa-caret-right pictofixedwidth"></i><?php echo $langs->trans('NewsletterAdvancedOptions'); ?>
                </button>
                <span class="opacitymedium"><?php echo $langs->trans('NewsletterAdvancedOptionsHelp'); ?></span>
            </td>
        </tr>

        <tr class="<?php echo $advancedClass; ?>">
            <td class="fieldrequired"><?php echo $langs->trans('NewsletterMailType'); ?></td>
            <td class="reedcrm-newsletter-choices">
                <label><input type="radio" name="mail_type" value="<?php echo ReedcrmNewsletter::TYPE_NEWSLETTER; ?>" class="reedcrm-newsletter-mail-type"<?php echo $isReminder ? '' : ' checked'; ?>> <?php echo $langs->trans('NewsletterTypeNewsletter'); ?></label>
                <label><input type="radio" name="mail_type" value="<?php echo ReedcrmNewsletter::TYPE_REMINDER; ?>" class="reedcrm-newsletter-mail-type"<?php echo $isReminder ? ' checked' : ''; ?>> <?php echo $langs->trans('NewsletterTypeReminder'); ?></label>
                <span class="opacitymedium"><?php echo $langs->trans('NewsletterMailTypeHelp'); ?></span>
            </td>
        </tr>

        <tr class="<?php echo $advancedClass; ?> reedcrm-newsletter-reminder-row<?php echo $isReminder ? '' : ' hideobject'; ?>">
            <td><?php echo $form->textwithpicto($langs->trans('NewsletterFrequency'), $langs->trans('NewsletterFrequencyHelp')); ?></td>
            <td>
                <?php echo $langs->trans('NewsletterEvery'); ?>
                <input type="number" name="frequency" class="flat width75" min="0" step="1" value="<?php echo (int) $object->frequency; ?>">
                <select name="frequency_unit" class="flat">
                    <?php foreach (['d' => 'Days', 'w' => 'Weeks', 'm' => 'Month', 'y' => 'Years'] as $unit => $unitLabel) : ?>
                        <option value="<?php echo $unit; ?>"<?php echo $object->frequency_unit === $unit ? ' selected' : ''; ?>><?php echo $langs->trans($unitLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>

        <tr class="<?php echo $advancedClass; ?>">
            <td class="fieldrequired"><?php echo $langs->trans('NewsletterTargetType'); ?></td>
            <td class="reedcrm-newsletter-choices">
                <?php foreach ([ReedcrmNewsletter::TARGET_CONTACT => 'NewsletterTargetContact', ReedcrmNewsletter::TARGET_PROJECT => 'NewsletterTargetProject'] as $targetType => $targetLabel) : ?>
                    <label><input type="radio" name="target_type" value="<?php echo $targetType; ?>" class="reedcrm-newsletter-target-type"<?php echo $object->target_type === $targetType ? ' checked' : ''; ?>> <?php echo $langs->trans($targetLabel); ?></label>
                <?php endforeach; ?>
            </td>
        </tr>

        <?php // Narrower targeting: tags left out (of the type of the target), customers or prospects, a sales representative ?>
        <?php foreach ($tagChoices as $targetType => $tagsOfTarget) : ?>
            <tr class="<?php echo $advancedClass; ?> reedcrm-newsletter-target-only<?php echo $object->target_type === $targetType ? '' : ' hideobject'; ?>" data-target="<?php echo $targetType; ?>">
                <td><?php echo $form->textwithpicto($langs->trans('NewsletterExcludeTags'), $langs->trans($targetType === ReedcrmNewsletter::TARGET_PROJECT ? 'NewsletterExcludeTagsProjectHelp' : 'NewsletterExcludeTagsContactHelp')); ?></td>
                <td>
                    <?php if (isModEnabled('categorie') && !empty($tagsOfTarget)) : ?>
                        <?php echo img_picto('', 'category', 'class="pictofixedwidth"') . $form->multiselectarray('exclude_tags_' . $targetType, $tagsOfTarget, $object->target_type === $targetType ? $object->getExcludeTagIds() : [], 0, 0, 'minwidth300 widthcentpercentminusx', 0, '90%'); ?>
                    <?php else : ?>
                        <span class="opacitymedium"><?php echo $langs->trans('NewsletterNoTagOfType'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>

        <tr class="<?php echo $advancedClass; ?>">
            <td><?php echo $form->textwithpicto($langs->trans('NewsletterThirdpartyType'), $langs->trans('NewsletterThirdpartyTypeHelp')); ?></td>
            <td class="reedcrm-newsletter-choices">
                <?php foreach (['' => 'NewsletterThirdpartyAll', ReedcrmNewsletter::THIRDPARTY_CUSTOMER => 'NewsletterThirdpartyCustomers', ReedcrmNewsletter::THIRDPARTY_PROSPECT => 'NewsletterThirdpartyProspects'] as $thirdpartyType => $thirdpartyLabel) : ?>
                    <label><input type="radio" name="thirdparty_type" value="<?php echo $thirdpartyType; ?>"<?php echo (string) $object->thirdparty_type === (string) $thirdpartyType ? ' checked' : ''; ?>> <?php echo $langs->trans($thirdpartyLabel); ?></label>
                <?php endforeach; ?>
            </td>
        </tr>

        <tr class="<?php echo $advancedClass; ?>">
            <td><?php echo $form->textwithpicto($langs->trans('NewsletterSalesRep'), $langs->trans('NewsletterSalesRepHelp')); ?></td>
            <td><?php echo img_picto('', 'user', 'class="pictofixedwidth"') . $salesRepSelect; ?></td>
        </tr>

        <tr class="<?php echo $advancedClass; ?>">
            <td class="fieldrequired"><?php echo $form->textwithpicto($langs->trans('NewsletterTrigger'), $langs->trans('NewsletterTriggerHelp')); ?></td>
            <td class="reedcrm-newsletter-choices">
                <label><input type="radio" name="send_trigger" value="<?php echo ReedcrmNewsletter::TRIGGER_DATE; ?>" class="reedcrm-newsletter-trigger"<?php echo $isAfterCreation ? '' : ' checked'; ?>> <?php echo $langs->trans('NewsletterTriggerDate'); ?></label>
                <label><input type="radio" name="send_trigger" value="<?php echo ReedcrmNewsletter::TRIGGER_CREATION; ?>" class="reedcrm-newsletter-trigger"<?php echo $isAfterCreation ? ' checked' : ''; ?>> <?php echo $langs->trans('NewsletterTriggerCreation'); ?></label>
            </td>
        </tr>

        <tr class="<?php echo $advancedClass; ?> reedcrm-newsletter-trigger-only<?php echo $isAfterCreation ? '' : ' hideobject'; ?>" data-trigger="<?php echo ReedcrmNewsletter::TRIGGER_CREATION; ?>">
            <td class="fieldrequired"><?php echo $langs->trans('NewsletterDelay'); ?></td>
            <td>
                <input type="number" name="send_delay" class="flat width75" min="0" step="1" value="<?php echo (int) $object->send_delay; ?>">
                <select name="send_delay_unit" class="flat">
                    <?php foreach ($delayUnits as $unit => $unitLabel) : ?>
                        <option value="<?php echo $unit; ?>"<?php echo $object->send_delay_unit === $unit ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($unitLabel); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php foreach ([ReedcrmNewsletter::TARGET_CONTACT => 'NewsletterDelayAfterContact', ReedcrmNewsletter::TARGET_PROJECT => 'NewsletterDelayAfterProject'] as $targetType => $afterLabel) : ?>
                    <span class="reedcrm-newsletter-target-only<?php echo $object->target_type === $targetType ? '' : ' hideobject'; ?>" data-target="<?php echo $targetType; ?>"><?php echo $langs->trans($afterLabel); ?></span>
                <?php endforeach; ?>
            </td>
        </tr>

        <tr class="<?php echo $advancedClass; ?>">
            <td><?php echo $form->textwithpicto($langs->trans('MailFrom'), $langs->trans('NewsletterEmailFromHelp')); ?></td>
            <td><input type="text" name="email_from" class="flat minwidth400" maxlength="255" value="<?php echo dol_escape_htmltag($object->email_from); ?>" placeholder="<?php echo dol_escape_htmltag($object->getSenderAddress() ?: 'Company <contact@example.com>'); ?>"></td>
        </tr>
    </table>

    <?php print dol_get_fiche_end(); ?>
    <?php // A draft saved does not leave: said beside the button, and "Save and plan" does both in one click ?>
    <?php $moreButtons = $canSaveAndSchedule ? ['name' => 'save_and_schedule', 'label_key' => 'NewsletterSaveAndSchedule', 'addclass' => 'reedcrm-newsletter-save-schedule'] : []; ?>
    <?php print $form->buttonsSaveCancel($isCreate ? 'Create' : 'Save', 'Cancel', $moreButtons); ?>
    <div class="center opacitymedium reedcrm-newsletter-save-help"><?php echo $langs->trans((int) $object->status === ReedcrmNewsletter::STATUS_SCHEDULED ? 'NewsletterSaveHelpScheduled' : 'NewsletterSaveHelpDraft'); ?></div>
</form>

<?php // Images of the simple editor: the media library of ReedCRM, and an upload that joins it ?>
<div class="wpeo-modal modal-close-only-with-button reedcrm-newsletter-images-modal" id="reedcrm-newsletter-images">
    <div class="modal-container wpeo-modal-event">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo $langs->trans('NewsletterPickImage'); ?></h2>
            <div class="modal-close"><i class="fas fa-2x fa-times"></i></div>
        </div>
        <div class="modal-content">
            <div class="reedcrm-newsletter-images-toolbar">
                <?php if (!empty($editorUrls['canUpload'])) : ?>
                    <label class="wpeo-button button-blue button-size-small reedcrm-newsletter-button reedcrm-newsletter-image-upload-label">
                        <i class="fas fa-upload pictofixedwidth"></i><?php echo $langs->trans('NewsletterUploadImage'); ?>
                        <input type="file" class="reedcrm-newsletter-image-upload" accept="image/png,image/jpeg,image/gif,image/webp">
                    </label>
                <?php endif; ?>
                <?php if ($editorUrls['library'] !== '') : ?>
                    <a href="<?php echo dol_escape_htmltag($editorUrls['library']); ?>" target="_blank" rel="noopener"><i class="fas fa-external-link-alt pictofixedwidth"></i><?php echo $langs->trans('NewsletterOpenLibrary'); ?></a>
                <?php endif; ?>
                <span class="opacitymedium"><?php echo $langs->trans('NewsletterImagesHelp'); ?></span>
                <?php if (!empty($editorUrls['canUpload'])) : ?>
                    <span class="opacitymedium"><?php echo $langs->trans('NewsletterImagesLimit', $maxUploadText); ?></span>
                <?php endif; ?>
            </div>
            <div class="reedcrm-newsletter-images-status opacitymedium hidden"></div>
            <div class="reedcrm-newsletter-images-grid"></div>
            <div class="reedcrm-newsletter-images-empty opacitymedium hidden"><?php echo $langs->trans('NewsletterNoImageYet'); ?></div>
            <template class="reedcrm-newsletter-image-item-template">
                <button type="button" class="reedcrm-newsletter-image-item">
                    <img src="" alt="" loading="lazy">
                    <span class="reedcrm-newsletter-image-name"></span>
                </button>
            </template>
        </div>
    </div>
</div>

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
 * \file    js/modules/newsletter.js
 * \ingroup reedcrm
 * \brief   Form of the newsletters: rows of the type, of the target and of the content mode, advanced options, the
 *          simple editor (blocks, image picker, preview made by the server) and the live preview of the HTML typed
 *          and of the email template chosen. The previews are sandboxed frames: no script of the HTML runs
 */

window.reedcrm.newsletter = {};

/**
 * Timer of the preview, refreshed once the typing pauses
 *
 * @type {?number}
 */
window.reedcrm.newsletter.previewTimer = null;

/**
 * Timer of the preview of the simple editor
 *
 * @type {?number}
 */
window.reedcrm.newsletter.blocksTimer = null;

/**
 * Number of the last preview asked to the server: an older answer arriving late is dropped
 *
 * @type {number}
 */
window.reedcrm.newsletter.blocksRequest = 0;

/**
 * HTML of the last preview of the simple editor: the HTML mode starts from it when its field is empty
 *
 * @type {string}
 */
window.reedcrm.newsletter.blocksHtml = '';

/**
 * Block whose image is being chosen
 *
 * @type {?jQuery}
 */
window.reedcrm.newsletter.pickerBlock = null;

/**
 * Last field focused that takes a key (subject, HTML, fields of the blocks)
 *
 * @type {?HTMLElement}
 */
window.reedcrm.newsletter.keyTarget = null;

/**
 * Last block a field of which was focused: a block added goes under it
 *
 * @type {?jQuery}
 */
window.reedcrm.newsletter.currentBlock = null;

/**
 * The form as it was opened (serialized): leaving it changed asks first. Compared rather than flagged, select2 fires
 * its own change events while it starts
 *
 * @type {?string}
 */
window.reedcrm.newsletter.initialState = null;

/**
 * Whether the form is being sent: leaving the page then asks nothing
 *
 * @type {boolean}
 */
window.reedcrm.newsletter.submitting = false;

/**
 * Timer and number of the count of the recipients asked to the server
 *
 * @type {?number}
 */
window.reedcrm.newsletter.countTimer   = null;
window.reedcrm.newsletter.countRequest = 0;

/**
 * Init
 *
 * @return {void}
 */
window.reedcrm.newsletter.init = function() {
  window.reedcrm.newsletter.event();
  window.reedcrm.newsletter.filterTemplates();
  window.reedcrm.newsletter.refreshHtmlPreview();
  window.reedcrm.newsletter.refreshTemplatePreview();
  if ($('.reedcrm-newsletter-builder').length) {
    $('.reedcrm-newsletter-builder .reedcrm-newsletter-block').each(function() {
      window.reedcrm.newsletter.checkBlock($(this));
    });
    window.reedcrm.newsletter.writeBlocks();
    window.reedcrm.newsletter.refreshBlocksPreview();
  }
  if ($('#reedcrm-newsletter-form').length) {
    window.reedcrm.newsletter.captureState();
    // Taken again once the page is loaded: the selects have their final values then
    $(window).one('load', window.reedcrm.newsletter.captureState);
    window.reedcrm.newsletter.refreshCount();
  }
};

/**
 * Events. Namespaced: the bundle runs init() itself and through load_list_script(), one binding only
 *
 * @return {void}
 */
window.reedcrm.newsletter.event = function() {
  $(document).off('.reedcrmNewsletter');
  $(document).on('change.reedcrmNewsletter', '.reedcrm-newsletter-mail-type', window.reedcrm.newsletter.toggleMailType);
  $(document).on('change.reedcrmNewsletter', '.reedcrm-newsletter-target-type', window.reedcrm.newsletter.toggleTarget);
  $(document).on('change.reedcrmNewsletter', '.reedcrm-newsletter-trigger', window.reedcrm.newsletter.toggleTrigger);
  $(document).on('change.reedcrmNewsletter', '.reedcrm-newsletter-content-mode', window.reedcrm.newsletter.toggleContentMode);
  $(document).on('input.reedcrmNewsletter', '.reedcrm-newsletter-code', window.reedcrm.newsletter.schedulePreview);
  $(document).on('change.reedcrmNewsletter', '.reedcrm-newsletter-template', window.reedcrm.newsletter.refreshTemplatePreview);
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-advanced-toggle', window.reedcrm.newsletter.toggleAdvanced);

  // Simple editor
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-add-block', window.reedcrm.newsletter.addBlock);
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-block-up', window.reedcrm.newsletter.moveBlockUp);
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-block-down', window.reedcrm.newsletter.moveBlockDown);
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-block-remove', window.reedcrm.newsletter.removeBlock);
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-text-tool', window.reedcrm.newsletter.formatText);
  $(document).on('input.reedcrmNewsletter change.reedcrmNewsletter', '.reedcrm-newsletter-builder .reedcrm-newsletter-block-field, .reedcrm-newsletter-builder .reedcrm-newsletter-setting', window.reedcrm.newsletter.onBlockChange);
  $(document).on('change.reedcrmNewsletter', '.reedcrm-newsletter-builder input[inputmode="url"]', window.reedcrm.newsletter.completeUrlField);
  $(document).on('focusin.reedcrmNewsletter', '.reedcrm-newsletter-builder .reedcrm-newsletter-block', window.reedcrm.newsletter.rememberBlock);
  $(document).on('input.reedcrmNewsletter', 'input[name="subject"]', window.reedcrm.newsletter.onSubjectChange);
  $(document).on('submit.reedcrmNewsletter', '#reedcrm-newsletter-form', window.reedcrm.newsletter.onSubmit);

  // A form changed and left without saving asks first
  $(window).off('beforeunload.reedcrmNewsletter').on('beforeunload.reedcrmNewsletter', window.reedcrm.newsletter.confirmLeave);

  // Recipients counted again when the targeting changes (select2 fires change on the select)
  $(document).on('change.reedcrmNewsletter', '#reedcrm-newsletter-form select[name^="tags_"], #reedcrm-newsletter-form select[name^="exclude_tags_"], #reedcrm-newsletter-form [name="thirdparty_type"], #reedcrm-newsletter-form [name="fk_sales_rep"], .reedcrm-newsletter-target-type, .reedcrm-newsletter-mail-type', window.reedcrm.newsletter.scheduleCount);

  // Images
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-pick-image', window.reedcrm.newsletter.openImagePicker);
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-image-item', window.reedcrm.newsletter.useImage);
  $(document).on('change.reedcrmNewsletter', '.reedcrm-newsletter-image-upload', window.reedcrm.newsletter.uploadImage);

  // Keys: a click writes the key where the cursor was
  $(document).on('focusin.reedcrmNewsletter', '.reedcrm-newsletter-key-target, .reedcrm-newsletter-builder input[type="text"].reedcrm-newsletter-block-field, .reedcrm-newsletter-builder textarea', window.reedcrm.newsletter.rememberKeyTarget);
  $(document).on('mousedown.reedcrmNewsletter', '.reedcrm-newsletter-key', window.reedcrm.newsletter.keepFocus);
  $(document).on('click.reedcrmNewsletter', '.reedcrm-newsletter-key', window.reedcrm.newsletter.insertKey);
};

/**
 * Frequency row for the reminders only, "every month" for the newsletters at a date only
 *
 * @return {void}
 */
window.reedcrm.newsletter.toggleMailType = function() {
  var isReminder = $('.reedcrm-newsletter-mail-type:checked').val() === 'reminder';
  $('.reedcrm-newsletter-reminder-row').toggleClass('hideobject', !isReminder);
  window.reedcrm.newsletter.toggleAutoNext();
};

/**
 * "Every month: prepare the next draft", for a newsletter sent at a date
 *
 * @return {void}
 */
window.reedcrm.newsletter.toggleAutoNext = function() {
  var isNewsletter = $('.reedcrm-newsletter-mail-type:checked').val() !== 'reminder';
  var atDate       = $('.reedcrm-newsletter-trigger:checked').val() !== 'creation';
  $('.reedcrm-newsletter-auto-next-row').toggleClass('hideobject', !(isNewsletter && atDate));
};

/**
 * Advanced options shown or folded
 *
 * @return {void}
 */
window.reedcrm.newsletter.toggleAdvanced = function() {
  var open = $(this).attr('aria-expanded') !== 'true';
  $(this).attr('aria-expanded', open ? 'true' : 'false');
  $('.reedcrm-newsletter-advanced-row').toggleClass('reedcrm-newsletter-collapsed', !open);
};

/**
 * Tags of the target chosen, and the words of the delay ("after the creation of the contact")
 *
 * @return {void}
 */
window.reedcrm.newsletter.toggleTarget = function() {
  var target = $('.reedcrm-newsletter-target-type:checked').val();
  $('.reedcrm-newsletter-target-row, .reedcrm-newsletter-target-only').each(function() {
    $(this).toggleClass('hideobject', $(this).data('target') !== target);
  });
  window.reedcrm.newsletter.filterTemplates();
};

/**
 * Email templates of the target chosen: a template of the projects on contacts would leave its keys empty. The
 * templates for all and the ones of the newsletters stay
 *
 * @return {void}
 */
window.reedcrm.newsletter.filterTemplates = function() {
  var target  = $('.reedcrm-newsletter-target-type:checked').val();
  var $select = $('.reedcrm-newsletter-template');
  if (!$select.length || !target) {
    return;
  }
  $select.find('option').each(function() {
    var optionTarget = $(this).attr('data-target') || '';
    var hidden       = optionTarget !== '' && optionTarget !== target;
    $(this).prop('hidden', hidden).prop('disabled', hidden);
  });
  if ($select.find('option:selected').prop('disabled')) {
    $select.val('0');
    window.reedcrm.newsletter.refreshTemplatePreview();
  }
};

/**
 * Delay after the creation, and the date row named as a start date rather than a sending date
 *
 * @return {void}
 */
window.reedcrm.newsletter.toggleTrigger = function() {
  var trigger = $('.reedcrm-newsletter-trigger:checked').val();
  $('.reedcrm-newsletter-trigger-only').each(function() {
    $(this).toggleClass('hideobject', $(this).data('trigger') !== trigger);
  });
  window.reedcrm.newsletter.toggleAutoNext();
};

/**
 * Rows of the content mode chosen: simple editor, HTML or template. The HTML mode, empty, starts from the mail made
 * by the simple editor
 *
 * @return {void}
 */
window.reedcrm.newsletter.toggleContentMode = function() {
  var mode = $('.reedcrm-newsletter-content-mode:checked').val();
  $('.reedcrm-newsletter-mode-row').each(function() {
    var modes = String($(this).data('modes') || '').split(' ');
    $(this).toggleClass('hideobject', modes.indexOf(mode) === -1);
  });
  if (mode === 'html') {
    var $code = $('.reedcrm-newsletter-code');
    if ($.trim($code.val()) === '' && window.reedcrm.newsletter.blocksHtml !== '') {
      $code.val(window.reedcrm.newsletter.blocksHtml);
    }
    window.reedcrm.newsletter.refreshHtmlPreview();
  } else if (mode === 'blocks') {
    window.reedcrm.newsletter.refreshBlocksPreview();
  } else {
    window.reedcrm.newsletter.refreshTemplatePreview();
  }
};

/**
 * Preview refreshed 300 ms after the last key
 *
 * @return {void}
 */
window.reedcrm.newsletter.schedulePreview = function() {
  window.clearTimeout(window.reedcrm.newsletter.previewTimer);
  window.reedcrm.newsletter.previewTimer = window.setTimeout(window.reedcrm.newsletter.refreshHtmlPreview, 300);
};

/**
 * Preview of the HTML typed
 *
 * @return {void}
 */
window.reedcrm.newsletter.refreshHtmlPreview = function() {
  var $code  = $('.reedcrm-newsletter-code');
  var $frame = $('.reedcrm-newsletter-form .reedcrm-newsletter-preview-frame');
  if (!$code.length || !$frame.length) {
    return;
  }
  window.reedcrm.newsletter.setFrameContent($frame, window.reedcrm.newsletter.toHtml($code.val()));
};

/**
 * Preview of the email template chosen, as Dolibarr stores it (the keys are filled in at the sending)
 *
 * @return {void}
 */
window.reedcrm.newsletter.refreshTemplatePreview = function() {
  var $option = $('.reedcrm-newsletter-template option:selected');
  var $frame  = $('.reedcrm-newsletter-template-frame');
  if (!$frame.length) {
    return;
  }
  $('.reedcrm-newsletter-template-topic').text($option.data('topic') || '');
  window.reedcrm.newsletter.setFrameContent($frame, window.reedcrm.newsletter.toHtml($option.attr('data-content') || ''));
};

/**
 * Content of a preview frame: a copy of the frame carrying the new srcdoc replaces it. Changing the srcdoc of the
 * frame in place leaves it unpainted once its row was hidden (Edge and Chrome), a frame inserted with its content
 * is always painted
 *
 * @param  {jQuery} $frame Frame
 * @param  {string} html   Content
 * @return {void}
 */
window.reedcrm.newsletter.setFrameContent = function($frame, html) {
  if ($frame.attr('srcdoc') === html) {
    return;
  }
  $frame.replaceWith($frame.clone().attr('srcdoc', html));
};

/**
 * HTML of a content: a plain text gets its line breaks, as at the sending
 *
 * @param  {string} content Content
 * @return {string}         HTML
 */
window.reedcrm.newsletter.toHtml = function(content) {
  content = content || '';
  if (/<[a-z][\s\S]*>/i.test(content)) {
    return content;
  }
  return $('<div>').text(content).html().replace(/\r?\n/g, '<br>');
};

/*
 * Simple editor
 */

/**
 * Builder of the simple editor, with its addresses and its messages
 *
 * @return {jQuery}
 */
window.reedcrm.newsletter.builder = function() {
  return $('.reedcrm-newsletter-builder');
};

/**
 * Blocks as typed, in the format the server reads (see reedcrm_newsletter_clean_blocks())
 *
 * @return {Object}
 */
window.reedcrm.newsletter.readBlocks = function() {
  var $builder = window.reedcrm.newsletter.builder();
  var data     = {
    layout: $builder.find('[data-setting="layout"]').val(),
    color : $builder.find('[data-setting="color"]').val(),
    logo  : $builder.find('[data-setting="logo"]').is(':checked') ? 1 : 0,
    blocks: []
  };
  $builder.find('.reedcrm-newsletter-blocks > .reedcrm-newsletter-block').each(function() {
    var block = { type: $(this).data('type') };
    $(this).find('.reedcrm-newsletter-block-field').each(function() {
      block[$(this).data('field')] = $(this).val();
    });
    data.blocks.push(block);
  });
  return data;
};

/**
 * Blocks written in the hidden field that the form posts
 *
 * @return {void}
 */
window.reedcrm.newsletter.writeBlocks = function() {
  if (!window.reedcrm.newsletter.builder().length) {
    return;
  }
  $('.reedcrm-newsletter-blocks-input').val(JSON.stringify(window.reedcrm.newsletter.readBlocks()));
};

/**
 * A field of a block or a setting changed: the hidden field and the preview follow
 *
 * @return {void}
 */
window.reedcrm.newsletter.onBlockChange = function() {
  var $block = $(this).closest('.reedcrm-newsletter-block');
  if ($block.length) {
    // An address typed for an image replaces the image chosen in the library
    if ($block.data('type') === 'image' && $(this).data('field') === 'url' && $.trim($(this).val()) !== '') {
      $block.find('[data-field="file"], [data-field="library"]').val('');
    }
    window.reedcrm.newsletter.checkBlock($block);
  }
  window.reedcrm.newsletter.blocksChanged();
};

/**
 * Hidden field written at once, preview asked once the typing pauses
 *
 * @return {void}
 */
window.reedcrm.newsletter.blocksChanged = function() {
  window.reedcrm.newsletter.writeBlocks();
  window.clearTimeout(window.reedcrm.newsletter.blocksTimer);
  window.reedcrm.newsletter.blocksTimer = window.setTimeout(window.reedcrm.newsletter.refreshBlocksPreview, 400);
};

/**
 * Link typed as people type it, completed as the server does (reedcrm_newsletter_complete_url()): "www.site.fr" and
 * "site.fr/page" get https://, "contact@site.fr" gets mailto:
 *
 * @param  {string} url Link
 * @return {string}
 */
window.reedcrm.newsletter.completeUrl = function(url) {
  url = $.trim(url || '');
  if (url === '' || /^[a-z][a-z0-9+.-]*:/i.test(url) || url.indexOf('__') === 0) {
    return url;
  }
  if (/^[^@\s\/:]+@[^@\s\/:]+\.[a-z]{2,}$/i.test(url)) {
    return 'mailto:' + url;
  }
  if (/^(www\.)?[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}(?::\d+)?(?:[\/?#].*)?$/i.test(url)) {
    return 'https://' + url;
  }
  return url;
};

/**
 * Field of a link left: completed in place, the person sees the address kept
 *
 * @return {void}
 */
window.reedcrm.newsletter.completeUrlField = function() {
  var completed = window.reedcrm.newsletter.completeUrl($(this).val());
  if (completed !== $.trim($(this).val())) {
    $(this).val(completed).trigger('input');
  }
};

/**
 * Whether a link typed would be kept: http, https, mailto, tel or a key, once completed. The server drops the others.
 * An image takes no key: the address of the recipient would leave to the server of the image
 *
 * @param  {string}  url       Link
 * @param  {boolean} imageOnly Address of an image: http and https only
 * @return {boolean}
 */
window.reedcrm.newsletter.isValidUrl = function(url, imageOnly) {
  url = window.reedcrm.newsletter.completeUrl(url);
  if (url === '') {
    return true;
  }
  if (imageOnly && /__[A-Z0-9_]+__/.test(url)) {
    return false;
  }
  if (/^__[A-Z0-9_]+__$/.test(url)) {
    return !imageOnly;
  }
  return imageOnly ? /^https?:\/\/[^\/?#\s]/i.test(url) : /^(https?:\/\/[^\/?#\s]|mailto:[^\/\s]|tel:\+?[0-9(])/i.test(url);
};

/**
 * Warnings of a block: a link that would be dropped (fields of the links, links of a text), a button that would not
 * show (a text without address or the other way round). And the image shown for the address typed
 *
 * @param  {jQuery} $block Block
 * @return {void}
 */
window.reedcrm.newsletter.checkBlock = function($block) {
  var $builder = window.reedcrm.newsletter.builder();
  var type     = $block.data('type');
  var warnings = [];
  $block.find('[data-field="url"], [data-field="link"]').each(function() {
    var imageOnly = type === 'image' && $(this).data('field') === 'url';
    if (!window.reedcrm.newsletter.isValidUrl($(this).val(), imageOnly)) {
      warnings.push($builder.data(imageOnly && /__[A-Z0-9_]+__/.test($(this).val()) ? 'error-bad-image-url' : 'error-bad-url'));
    }
  });
  if (type === 'text') {
    var text = String($block.find('[data-field="text"]').val() || '');
    var re   = /\[([^\]\n]+)\]\(([^)\s]+)\)/g;
    var match;
    while ((match = re.exec(text)) !== null) {
      if (!window.reedcrm.newsletter.isValidUrl(match[2], false)) {
        warnings.push(String($builder.data('error-bad-text-link')).replace('%s', match[2]));
      }
    }
  }
  if (type === 'button') {
    var label = $.trim($block.find('[data-field="label"]').val());
    var url   = $.trim($block.find('[data-field="url"]').val());
    if ((label === '') !== (url === '')) {
      warnings.push($builder.data('error-button-incomplete'));
    }
  }
  warnings = warnings.filter(function(warning, index) {
    return warnings.indexOf(warning) === index;
  });
  $block.find('.reedcrm-newsletter-block-warning').text(warnings.join(' ')).toggleClass('hidden', warnings.length === 0);

  if (type === 'image') {
    var file    = $block.find('[data-field="file"]').val();
    var library = $block.find('[data-field="library"]').val();
    var address = window.reedcrm.newsletter.completeUrl($block.find('[data-field="url"]').val());
    var src     = '';
    if (file) {
      src = $builder.data('image-base') + encodeURIComponent(file);
    } else if (library) {
      src = $builder.data('thumb-base') + encodeURIComponent(library);
    } else if (window.reedcrm.newsletter.isValidUrl(address, true)) {
      src = address;
    }
    window.reedcrm.newsletter.showBlockImage($block, src);
  }
};

/**
 * Image shown in an image block
 *
 * @param  {jQuery} $block Block
 * @param  {string} src    Address, '' for none
 * @return {void}
 */
window.reedcrm.newsletter.showBlockImage = function($block, src) {
  var $thumb = $block.find('.reedcrm-newsletter-image-thumb');
  var $img   = $thumb.find('img');
  if (src) {
    if ($img.attr('src') !== src) {
      $img.attr('src', src);
    }
    $img.prop('hidden', false);
  } else {
    $img.removeAttr('src').prop('hidden', true);
  }
  $thumb.find('i').prop('hidden', !!src);
  $thumb.toggleClass('reedcrm-newsletter-image-thumb--empty', !src);
};

/**
 * Preview of the simple editor, made by the server with the renderer of the sending
 *
 * @return {void}
 */
window.reedcrm.newsletter.refreshBlocksPreview = function() {
  var $builder = window.reedcrm.newsletter.builder();
  if (!$builder.length) {
    return;
  }
  window.reedcrm.newsletter.writeBlocks();
  var request = ++window.reedcrm.newsletter.blocksRequest;
  $.ajax({
    url     : $builder.data('editor-url') + '?action=preview',
    type    : 'POST',
    dataType: 'json',
    data    : {
      token      : window.saturne.toolbox.getToken(),
      body_blocks: $('.reedcrm-newsletter-blocks-input').val(),
      subject    : $('input[name="subject"]').val() || '',
      target_type: $('.reedcrm-newsletter-target-type:checked').val() || '',
      id         : $('#reedcrm-newsletter-form input[name="id"]').val() || 0
    }
  }).done(function(resp) {
    if (request !== window.reedcrm.newsletter.blocksRequest) {
      return;
    }
    window.reedcrm.newsletter.blocksHtml = resp.html || '';
    window.reedcrm.newsletter.setFrameContent($('.reedcrm-newsletter-blocks-frame'), window.reedcrm.newsletter.blocksHtml);
    $('.reedcrm-newsletter-preview-subject').text(resp.subject ? '· ' + resp.subject : '');
  });
};

/**
 * Subject typed: the preview of the simple editor shows it filled in
 *
 * @return {void}
 */
window.reedcrm.newsletter.onSubjectChange = function() {
  if (window.reedcrm.newsletter.builder().length) {
    window.reedcrm.newsletter.blocksChanged();
  }
};

/**
 * Remember the block being written: a block added goes under it
 *
 * @return {void}
 */
window.reedcrm.newsletter.rememberBlock = function() {
  window.reedcrm.newsletter.currentBlock = $(this);
};

/**
 * Add a block of the type of the button, copied from its template, under the block being written (at the end when
 * none is)
 *
 * @return {void}
 */
window.reedcrm.newsletter.addBlock = function() {
  var type     = $(this).data('add-block');
  var template = $('.reedcrm-newsletter-block-template[data-type="' + type + '"]').get(0);
  if (!template || !template.content.firstElementChild) {
    return;
  }
  var $block   = $(template.content.firstElementChild.cloneNode(true));
  var $current = window.reedcrm.newsletter.currentBlock;
  if ($current && $current.length && document.body.contains($current.get(0)) && $current.parent().is('.reedcrm-newsletter-blocks')) {
    $current.after($block);
  } else {
    $('.reedcrm-newsletter-blocks').append($block);
  }
  window.reedcrm.newsletter.currentBlock = $block;
  $block.find('.reedcrm-newsletter-block-field:not([type="hidden"])').first().trigger('focus', { preventScroll: true });
  window.reedcrm.newsletter.highlightBlock($block);
  window.reedcrm.newsletter.blocksChanged();
  // An image block is there to show an image: the picker opens at once
  if (type === 'image') {
    window.reedcrm.newsletter.openImagePicker.call($block.find('.reedcrm-newsletter-pick-image').get(0));
  }
};

/**
 * Move a block one place up
 *
 * @return {void}
 */
window.reedcrm.newsletter.moveBlockUp = function() {
  var button = this;
  var $block = $(this).closest('.reedcrm-newsletter-block');
  var $prev  = $block.prev('.reedcrm-newsletter-block');
  if ($prev.length) {
    window.reedcrm.newsletter.animateMove($block, function() {
      $prev.before($block);
    });
    $(button).trigger('focus');
    window.reedcrm.newsletter.blocksChanged();
  }
};

/**
 * Move a block one place down
 *
 * @return {void}
 */
window.reedcrm.newsletter.moveBlockDown = function() {
  var button = this;
  var $block = $(this).closest('.reedcrm-newsletter-block');
  var $next  = $block.next('.reedcrm-newsletter-block');
  if ($next.length) {
    window.reedcrm.newsletter.animateMove($block, function() {
      $next.after($block);
    });
    $(button).trigger('focus');
    window.reedcrm.newsletter.blocksChanged();
  }
};

/**
 * Move blocks with a slide: each block starts from where it was and glides to its new place (the transition of
 * .reedcrm-newsletter-block), the block moved is lit for a moment and kept in view. Without it, the block jumps and
 * the eye does not see that anything moved
 *
 * @param  {jQuery}   $block Block moved
 * @param  {Function} move   Moves the block in the DOM
 * @return {void}
 */
window.reedcrm.newsletter.animateMove = function($block, move) {
  var $blocks = $('.reedcrm-newsletter-blocks > .reedcrm-newsletter-block');
  var before  = $blocks.map(function() {
    return this.getBoundingClientRect().top;
  }).get();

  move();

  // Each block put back where it was, at once, then let go: the transition brings it to its new place
  $blocks.each(function(index) {
    var delta = before[index] - this.getBoundingClientRect().top;
    if (delta !== 0) {
      this.style.transition = 'none';
      this.style.transform  = 'translateY(' + delta + 'px)';
    }
  });
  $blocks.get(0).getBoundingClientRect();
  $blocks.each(function() {
    if (this.style.transform) {
      this.style.transition = '';
      this.style.transform  = '';
    }
  });

  window.reedcrm.newsletter.highlightBlock($block);
};

/**
 * Light a block for a moment and bring it into view
 *
 * @param  {jQuery} $block Block
 * @return {void}
 */
window.reedcrm.newsletter.highlightBlock = function($block) {
  window.clearTimeout($block.data('highlightTimer'));
  $block.removeClass('reedcrm-newsletter-block--moved');
  $block.get(0).getBoundingClientRect();
  $block.addClass('reedcrm-newsletter-block--moved');
  $block.data('highlightTimer', window.setTimeout(function() {
    $block.removeClass('reedcrm-newsletter-block--moved');
  }, 1200));
  var rect = $block.get(0).getBoundingClientRect();
  if (rect.top < 60 || rect.bottom > window.innerHeight) {
    $block.get(0).scrollIntoView({ block: 'nearest', behavior: 'smooth' });
  }
};

/**
 * Remove a block; one already filled in asks first
 *
 * @return {void}
 */
window.reedcrm.newsletter.removeBlock = function() {
  var $block = $(this).closest('.reedcrm-newsletter-block');
  var filled = false;
  $block.find('.reedcrm-newsletter-block-field').each(function() {
    if ($.trim($(this).val()) !== '') {
      filled = true;
    }
  });
  if (filled && !window.confirm(window.reedcrm.newsletter.builder().data('confirm-remove'))) {
    return;
  }
  $block.remove();
  window.reedcrm.newsletter.blocksChanged();
};

/**
 * Bold or link around the text selected in a text block: **text** and [text](https://…), read by the server
 *
 * @return {void}
 */
window.reedcrm.newsletter.formatText = function() {
  var $builder = window.reedcrm.newsletter.builder();
  var textarea = $(this).closest('.reedcrm-newsletter-block').find('textarea').get(0);
  if (!textarea) {
    return;
  }
  var start    = textarea.selectionStart;
  var end      = textarea.selectionEnd;
  var selected = textarea.value.substring(start, end);
  var insert   = '';
  if ($(this).data('format') === 'bold') {
    insert = '**' + (selected || $builder.data('link-label')) + '**';
  } else {
    var url = window.prompt($builder.data('link-prompt'), 'https://');
    if (!url || $.trim(url) === '' || $.trim(url) === 'https://') {
      textarea.focus();
      return;
    }
    insert = '[' + (selected || $builder.data('link-label')) + '](' + window.reedcrm.newsletter.completeUrl(url).replace(/\s/g, '%20') + ')';
  }
  textarea.setRangeText(insert, start, end, 'end');
  textarea.focus();
  $(textarea).trigger('input');
};

/*
 * Images
 */

/**
 * Open the picker for the block of the button clicked, and load the images of the library
 *
 * @return {void}
 */
window.reedcrm.newsletter.openImagePicker = function() {
  window.reedcrm.newsletter.pickerBlock = $(this).closest('.reedcrm-newsletter-block');
  $('#reedcrm-newsletter-images').addClass('modal-active');
  window.reedcrm.newsletter.loadImages();
};

/**
 * Status line of the picker: a message, or nothing
 *
 * @param  {string}  message Message, '' to hide
 * @param  {boolean} isError Whether it is an error
 * @return {void}
 */
window.reedcrm.newsletter.pickerStatus = function(message, isError) {
  $('.reedcrm-newsletter-images-status').text(message || '').toggleClass('hidden', !message).toggleClass('error', !!isError).toggleClass('opacitymedium', !isError);
};

/**
 * Images of the library, as tiles
 *
 * @return {void}
 */
window.reedcrm.newsletter.loadImages = function() {
  var $builder = window.reedcrm.newsletter.builder();
  var token    = window.saturne.toolbox.getToken();
  var $grid    = $('.reedcrm-newsletter-images-grid');
  var template = $('.reedcrm-newsletter-image-item-template').get(0);
  window.reedcrm.newsletter.pickerStatus('', false);
  $.ajax({
    url     : $builder.data('editor-url'),
    type    : 'GET',
    dataType: 'json',
    data    : { action: 'images', token: token }
  }).done(function(resp) {
    $grid.empty();
    (resp.images || []).forEach(function(image) {
      var $item = $(template.content.firstElementChild.cloneNode(true));
      $item.attr('data-name', image.name).attr('title', image.name + ' (' + image.date + ')');
      $item.find('img').attr('src', $builder.data('editor-url') + '?action=thumb&token=' + encodeURIComponent(token) + '&name=' + encodeURIComponent(image.name)).attr('alt', image.name);
      $item.find('.reedcrm-newsletter-image-name').text(image.name);
      $grid.append($item);
    });
    $('.reedcrm-newsletter-images-empty').toggleClass('hidden', (resp.images || []).length > 0);
  }).fail(function(xhr) {
    window.reedcrm.newsletter.pickerStatus((xhr.responseJSON && xhr.responseJSON.error) || $builder.data('error-request'), true);
  });
};

/**
 * Image of the library clicked: in the block. It is made public when the newsletter is saved, not now
 *
 * @return {void}
 */
window.reedcrm.newsletter.useImage = function() {
  window.reedcrm.newsletter.setPickedImage($(this).attr('data-name'));
};

/**
 * Image sent from the computer: joins the library and goes in the block
 *
 * @return {void}
 */
window.reedcrm.newsletter.uploadImage = function() {
  var $builder  = window.reedcrm.newsletter.builder();
  var input     = this;
  var file      = input.files && input.files[0];
  var maxUpload = parseInt($builder.data('max-upload'), 10) || 0;
  if (!file) {
    return;
  }
  if (!/^image\/(png|jpeg|gif|webp)$/.test(file.type)) {
    window.reedcrm.newsletter.pickerStatus($builder.data('error-not-image'), true);
    input.value = '';
    return;
  }
  // Above the limit of Dolibarr the server would get an empty request: said before sending
  if (maxUpload > 0 && file.size > maxUpload * 1024) {
    window.reedcrm.newsletter.pickerStatus($builder.data('error-too-large'), true);
    input.value = '';
    return;
  }

  var formData = new FormData();
  formData.append('token', window.saturne.toolbox.getToken());
  formData.append('userfile', file);
  window.reedcrm.newsletter.pickerStatus('…', false);
  // uploadform=1: above the limit of PHP, Dolibarr answers "file too large" rather than a refusal of the token
  $.ajax({
    url        : $builder.data('editor-url') + '?action=upload&uploadform=1',
    type       : 'POST',
    dataType   : 'json',
    data       : formData,
    processData: false,
    contentType: false
  }).done(function(resp) {
    window.reedcrm.newsletter.setPickedImage(resp.name);
  }).fail(function(xhr) {
    var message = xhr.responseJSON && xhr.responseJSON.error;
    // An answer that is no JSON: the request did not reach the editor, the file was too large for PHP
    if (!message && xhr.status !== 403 && xhr.status !== 0) {
      message = $builder.data('error-too-large');
    }
    window.reedcrm.newsletter.pickerStatus(message || $builder.data('error-request'), true);
  }).always(function() {
    input.value = '';
  });
};

/**
 * Image chosen: in the block whose picker was opened, the picker closes. An image of the library is made public when
 * the newsletter is saved
 *
 * @param  {string} name Name of the image in the library
 * @return {void}
 */
window.reedcrm.newsletter.setPickedImage = function(name) {
  var $block = window.reedcrm.newsletter.pickerBlock;
  if ($block && $block.length && name) {
    $block.find('[data-field="library"]').val(name);
    $block.find('[data-field="file"], [data-field="url"]').val('');
    window.reedcrm.newsletter.checkBlock($block);
    window.reedcrm.newsletter.blocksChanged();
  }
  window.reedcrm.newsletter.pickerStatus('', false);
  $('#reedcrm-newsletter-images').removeClass('modal-active');
};

/*
 * Leaving the form, count of the recipients
 */

/**
 * The form as it is now, the blocks written in their field
 *
 * @return {string}
 */
window.reedcrm.newsletter.formState = function() {
  window.reedcrm.newsletter.writeBlocks();
  return $('#reedcrm-newsletter-form').serialize();
};

/**
 * Keep the form as it is as the state opened
 *
 * @return {void}
 */
window.reedcrm.newsletter.captureState = function() {
  window.reedcrm.newsletter.initialState = window.reedcrm.newsletter.formState();
};

/**
 * The form is sent: the blocks are written, leaving the page asks nothing
 *
 * @return {void}
 */
window.reedcrm.newsletter.onSubmit = function() {
  window.reedcrm.newsletter.writeBlocks();
  window.reedcrm.newsletter.submitting = true;
};

/**
 * Leaving a form changed and not saved (F5, a link, the back button): the browser asks first
 *
 * @param  {Event} event Before unload
 * @return {string|undefined}
 */
window.reedcrm.newsletter.confirmLeave = function(event) {
  if (window.reedcrm.newsletter.submitting || !$('#reedcrm-newsletter-form').length || window.reedcrm.newsletter.initialState === null || window.reedcrm.newsletter.formState() === window.reedcrm.newsletter.initialState) {
    return undefined;
  }
  var message = window.reedcrm.newsletter.builder().data('confirm-leave') || '';
  if (event.originalEvent) {
    event.originalEvent.returnValue = message;
  }
  event.preventDefault();
  return message;
};

/**
 * Count of the recipients asked once the targeting stops changing
 *
 * @return {void}
 */
window.reedcrm.newsletter.scheduleCount = function() {
  window.clearTimeout(window.reedcrm.newsletter.countTimer);
  window.reedcrm.newsletter.countTimer = window.setTimeout(window.reedcrm.newsletter.refreshCount, 300);
};

/**
 * Recipients of the tags and filters chosen, as the card will count them, without saving
 *
 * @return {void}
 */
window.reedcrm.newsletter.refreshCount = function() {
  var $count  = $('.reedcrm-newsletter-live-count');
  var url     = $('.reedcrm-newsletter-builder').data('editor-url') || $count.data('editor-url');
  var target  = $('.reedcrm-newsletter-target-type:checked').val() || 'contact';
  if (!$count.length || !url) {
    return;
  }
  var request = ++window.reedcrm.newsletter.countRequest;
  $.ajax({
    url        : url + '?action=count',
    type       : 'POST',
    dataType   : 'json',
    traditional: true,
    data       : {
      token          : window.saturne.toolbox.getToken(),
      target_type    : target,
      mail_type      : $('.reedcrm-newsletter-mail-type:checked').val() || 'newsletter',
      'tags[]'        : $('select[name="tags_' + target + '[]"]').val() || [],
      'exclude_tags[]': $('select[name="exclude_tags_' + target + '[]"]').val() || [],
      thirdparty_type: $('[name="thirdparty_type"]:checked').val() || '',
      fk_sales_rep   : $('[name="fk_sales_rep"]').val() || 0
    }
  }).done(function(resp) {
    if (request === window.reedcrm.newsletter.countRequest) {
      $count.text(resp.text || '');
    }
  });
};

/*
 * Keys
 */

/**
 * Remember the field focused: the key clicked next goes there
 *
 * @return {void}
 */
window.reedcrm.newsletter.rememberKeyTarget = function() {
  window.reedcrm.newsletter.keyTarget = this;
};

/**
 * The mouse down on a key keeps the cursor in its field
 *
 * @param  {Event} event Mouse down
 * @return {void}
 */
window.reedcrm.newsletter.keepFocus = function(event) {
  event.preventDefault();
};

/**
 * Write the key clicked at the cursor of the last field focused, else in the main field of the content mode
 *
 * @return {void}
 */
window.reedcrm.newsletter.insertKey = function() {
  var key    = $(this).data('key');
  var target = window.reedcrm.newsletter.keyTarget;
  if (!target || !document.body.contains(target) || $(target).closest('.hideobject').length) {
    var mode = $('.reedcrm-newsletter-content-mode:checked').val();
    target   = mode === 'html' ? $('.reedcrm-newsletter-code').get(0) : $('.reedcrm-newsletter-blocks textarea').last().get(0);
    target   = target || $('input[name="subject"]').get(0);
  }
  if (!target) {
    return;
  }
  var start = typeof target.selectionStart === 'number' ? target.selectionStart : target.value.length;
  var end   = typeof target.selectionEnd === 'number' ? target.selectionEnd : start;
  target.setRangeText(key, start, end, 'end');
  target.focus();
  $(target).trigger('input');
};

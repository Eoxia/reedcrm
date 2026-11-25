/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    js/modules/email_preview.js
 * \ingroup reedcrm
 * \brief   Adds a preview icon on .msg/.eml attachments and shows their content in a modal.
 *
 * Part of the reedcrm.min.js bundle, and also loaded alone by core/tpl/reedcrm_email_preview.tpl.php on the
 * document tabs and cards of Dolibarr, which never load the bundle.
 */

(function () {
    'use strict';

    // Both copies can be on the same page: the first one to run does the job
    if (window.reedcrmEmailPreviewLoaded) {
        return;
    }
    window.reedcrmEmailPreviewLoaded = true;

    /** Request being displayed: a slower previous answer must not replace it */
    var requestId = 0;

    /**
     * Escape a string for safe HTML injection.
     * @param  {*} s Value
     * @return {string}
     */
    function escapeHtml(s) {
        return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /**
     * URL of the endpoint and translated labels, printed by the template; the bundle alone only knows the module URL.
     * @return {{url: string, title: string, error: string}}
     */
    function settings() {
        var el   = document.getElementById('reedcrm-email-preview-config');
        var data = el ? el.dataset : {};
        var url  = data.url || '';
        if (!url && window.reedcrm && typeof window.reedcrm.moduleUrl === 'function') {
            url = window.reedcrm.moduleUrl('/ajax/preview_email.php');
        }
        return { url: url, title: data.transPreview || '', error: data.transError || 'Error' };
    }

    /**
     * Value of a parameter of a document.php link. A space of the file name is a '+' there.
     * @param  {string} href Link
     * @param  {string} name Parameter name
     * @return {string}
     */
    function queryParam(href, name) {
        var match = href.match(new RegExp('[?&]' + name + '=([^&#]*)'));
        if (!match) {
            return '';
        }
        try {
            return decodeURIComponent(match[1].replace(/\+/g, ' '));
        } catch (e) {
            return '';
        }
    }

    /**
     * Put a preview button after each .msg/.eml link of the page, where Dolibarr puts its own preview of images and PDF.
     * @return {void}
     */
    function injectPreviewButtons() {
        var title = settings().title;
        var links = document.querySelectorAll('a[href*="document.php"]');
        Array.prototype.forEach.call(links, function (a) {
            var href       = a.getAttribute('href') || '';
            var file       = queryParam(href, 'file');
            var modulepart = queryParam(href, 'modulepart');
            if (!file || !modulepart || !/\.(msg|eml)$/i.test(file)) {
                return;
            }
            var td = a.closest ? a.closest('td') : a.parentNode;
            if (!td || td.querySelector('.reedcrm-email-preview-btn')) {
                return;
            }
            var btn = document.createElement('span');
            btn.className = 'reedcrm-email-preview-btn';
            btn.setAttribute('role', 'button');
            btn.setAttribute('tabindex', '0');
            if (title) {
                btn.setAttribute('title', title);
            }
            btn.dataset.modulepart = modulepart;
            btn.dataset.file       = file;
            btn.dataset.entity     = queryParam(href, 'entity');
            btn.innerHTML = '<span class="fas fa-search-plus pictofixedwidth"></span>';
            td.appendChild(btn);
        });
    }

    /**
     * URL of the endpoint for an email file.
     * @param  {{modulepart: string, file: string, entity: string}} source File
     * @param  {Object} params Other parameters (part, attachment)
     * @return {string}
     */
    function endpoint(source, params) {
        var url = settings().url + '?modulepart=' + encodeURIComponent(source.modulepart) + '&file=' + encodeURIComponent(source.file);
        if (source.entity) {
            url += '&entity=' + encodeURIComponent(source.entity);
        }
        Object.keys(params).forEach(function (key) {
            if (params[key] !== '' && params[key] != null) {
                url += '&' + key + '=' + encodeURIComponent(params[key]);
            }
        });
        return url;
    }

    /**
     * Lazily build the singleton modal element.
     * @return {HTMLElement}
     */
    function ensureModal() {
        var modal = document.getElementById('reedcrm-email-modal');
        if (modal) {
            return modal;
        }
        modal = document.createElement('div');
        modal.id = 'reedcrm-email-modal';
        modal.className = 'reedcrm-email-modal';
        modal.innerHTML =
            '<div class="reedcrm-email-modal-card">' +
                '<div class="reedcrm-email-modal-head">' +
                    '<span class="reedcrm-email-modal-title"></span>' +
                    '<span class="reedcrm-email-modal-close" data-action="close-email-modal">&times;</span>' +
                '</div>' +
                '<div class="reedcrm-email-modal-meta"></div>' +
                '<div class="reedcrm-email-modal-bodywrap">' +
                    '<iframe class="reedcrm-email-modal-body" sandbox="allow-popups allow-popups-to-escape-sandbox"></iframe>' +
                '</div>' +
                '<div class="reedcrm-email-modal-attach"></div>' +
            '</div>';
        document.body.appendChild(modal);
        return modal;
    }

    /**
     * One line of the header block of the email.
     * @param  {string} label Translated label
     * @param  {string} value Escaped value
     * @return {string}
     */
    function metaLine(label, value) {
        return '<div><span class="lbl">' + escapeHtml(label) + '</span> ' + value + '</div>';
    }

    /**
     * Fetch and display an email file, or an email attached to it, in the modal.
     * @param  {{modulepart: string, file: string, entity: string}} source File
     * @param  {string} part   Path of the attached email ('' = the file itself, '1', '1.0'...)
     * @return {void}
     */
    function openPreview(source, part) {
        var conf   = settings();
        var modal  = ensureModal();
        var title  = modal.querySelector('.reedcrm-email-modal-title');
        var meta   = modal.querySelector('.reedcrm-email-modal-meta');
        var attach = modal.querySelector('.reedcrm-email-modal-attach');
        var iframe = modal.querySelector('.reedcrm-email-modal-body');
        var id     = ++requestId;

        title.innerHTML = '<span class="fas fa-spinner fa-spin"></span>';
        meta.innerHTML = '';
        attach.innerHTML = '';
        iframe.srcdoc = '';
        modal.classList.add('is-open');
        modal.reedcrmSource = source;

        var showError = function (message) {
            title.textContent = conf.title;
            meta.innerHTML = '<div class="reedcrm-email-modal-error">' + escapeHtml(message || conf.error) + '</div>';
        };

        fetch(endpoint(source, { part: part }), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (id !== requestId) {
                    return;
                }
                if (!d || !d.success) {
                    showError(d && d.message);
                    return;
                }
                var labels = d.labels || {};
                title.textContent = d.subject;

                var html = '';
                if (part !== '') {
                    html += '<div class="reedcrm-email-back"><a href="#" data-part="' + escapeHtml(part.split('.').slice(0, -1).join('.')) + '" class="reedcrm-email-open-part">'
                        + '<span class="fas fa-arrow-left pictofixedwidth"></span>' + escapeHtml(labels.back) + '</a></div>';
                }
                if (d.fromName || d.fromEmail) {
                    html += metaLine(labels.from, escapeHtml(d.fromName) + (d.fromEmail ? ' &lt;' + escapeHtml(d.fromEmail) + '&gt;' : ''));
                }
                if (d.to && d.to.length) {
                    html += metaLine(labels.to, escapeHtml(d.to.join(', ')));
                }
                if (d.cc && d.cc.length) {
                    html += metaLine(labels.cc, escapeHtml(d.cc.join(', ')));
                }
                if (d.date) {
                    html += metaLine(labels.date, escapeHtml(d.date));
                }
                meta.innerHTML = html;

                iframe.srcdoc = '<!doctype html><meta charset="utf-8"><base target="_blank">'
                    + '<style>body{font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#1a2d40;margin:12px;word-wrap:break-word;}'
                    + 'img{max-width:100%;height:auto;}table{max-width:100%;}pre.reedcrm-email-text{white-space:pre-wrap;font-family:inherit;margin:0;}</style>'
                    + (d.body || '');

                if (d.attachments && d.attachments.length) {
                    var list = '<div class="reedcrm-email-attach-title"><span class="fas fa-paperclip pictofixedwidth"></span>' + escapeHtml(labels.attachments) + '</div><ul>';
                    d.attachments.forEach(function (att) {
                        var path = (part !== '' ? part + '.' : '') + att.index;
                        list += '<li>';
                        if (att.message) {
                            list += '<a href="#" class="reedcrm-email-open-part" data-part="' + escapeHtml(path) + '">'
                                + '<span class="fas fa-envelope pictofixedwidth"></span>' + escapeHtml(att.filename) + '</a>'
                                + ' <span class="sz">(' + escapeHtml(labels.message) + ')</span>';
                            if (att.download) {
                                list += ' <a href="' + escapeHtml(endpoint(source, { part: part, attachment: att.index })) + '" class="reedcrm-email-download"><span class="fas fa-download"></span></a>';
                            }
                        } else if (att.download) {
                            list += '<a href="' + escapeHtml(endpoint(source, { part: part, attachment: att.index })) + '">'
                                + '<span class="fas fa-download pictofixedwidth"></span>' + escapeHtml(att.filename) + '</a>'
                                + ' <span class="sz">(' + escapeHtml(att.size) + ')</span>';
                        }
                        list += '</li>';
                    });
                    attach.innerHTML = list + '</ul>';
                }
            })
            .catch(function () {
                if (id === requestId) {
                    showError('');
                }
            });
    }

    /**
     * Hide the modal.
     * @return {void}
     */
    function closeModal() {
        var modal = document.getElementById('reedcrm-email-modal');
        if (modal && modal.classList.contains('is-open')) {
            requestId++;
            modal.classList.remove('is-open');
            var iframe = modal.querySelector('.reedcrm-email-modal-body');
            if (iframe) {
                iframe.srcdoc = '';
            }
        }
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(function ($) {
            injectPreviewButtons();

            $(document).off('.reedcrmEmailPreview');
            $(document).on('click.reedcrmEmailPreview', '.reedcrm-email-preview-btn', function () {
                openPreview({ modulepart: this.dataset.modulepart, file: this.dataset.file, entity: this.dataset.entity || '' }, '');
            });
            $(document).on('keydown.reedcrmEmailPreview', '.reedcrm-email-preview-btn', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).trigger('click');
                }
            });
            $(document).on('click.reedcrmEmailPreview', '.reedcrm-email-open-part', function (e) {
                e.preventDefault();
                var modal = document.getElementById('reedcrm-email-modal');
                if (modal && modal.reedcrmSource) {
                    openPreview(modal.reedcrmSource, this.dataset.part || '');
                }
            });
            $(document).on('click.reedcrmEmailPreview', '[data-action="close-email-modal"]', closeModal);
            $(document).on('click.reedcrmEmailPreview', '#reedcrm-email-modal', function (e) {
                if (e.target === this) {
                    closeModal();
                }
            });
            $(document).on('keydown.reedcrmEmailPreview', function (e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });
        });
    }
})();

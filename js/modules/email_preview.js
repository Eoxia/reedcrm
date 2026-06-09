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
 * \brief   Adds an "eye" preview icon on .msg/.eml attachments and shows their content in a modal.
 */

(function () {
    'use strict';

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
     * Scan the page for .msg/.eml attachment links and inject a preview button before each.
     * @return {void}
     */
    function injectPreviewButtons() {
        var links = document.querySelectorAll('a[href*="document.php"]');
        Array.prototype.forEach.call(links, function (a) {
            var href = a.getAttribute('href') || '';
            var fileMatch = href.match(/[?&]file=([^&]+)/);
            var modMatch  = href.match(/[?&]modulepart=([^&]+)/);
            if (!fileMatch || !modMatch) {
                return;
            }
            var file, modulepart;
            try {
                file = decodeURIComponent(fileMatch[1]);
                modulepart = decodeURIComponent(modMatch[1]);
            } catch (e) {
                return;
            }
            if (!/\.(msg|eml)$/i.test(file)) {
                return;
            }
            // Place the icon where the image "magnifying glass +" sits: at the end of the
            // file-name cell (<td>), after the filename — same spot/look as image previews.
            var td = a.closest ? a.closest('td') : a.parentNode;
            if (!td || td.querySelector('.reedcrm-email-preview-btn')) {
                return;
            }
            var btn = document.createElement('span');
            btn.className = 'reedcrm-email-preview-btn';
            btn.setAttribute('title', 'Aperçu du mail');
            btn.dataset.modulepart = modulepart;
            btn.dataset.file = file;
            btn.dataset.base = a.href.split('/document.php')[0];
            btn.innerHTML = '<span class="fa fa-search-plus pictofixedwidth"></span>';
            td.appendChild(btn);
        });
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
     * Fetch and display an email attachment in the modal.
     * @param  {string} modulepart Dolibarr modulepart
     * @param  {string} file       Relative file path
     * @param  {string} base       Dolibarr root URL
     * @return {void}
     */
    function openPreview(modulepart, file, base) {
        var modal  = ensureModal();
        var title  = modal.querySelector('.reedcrm-email-modal-title');
        var meta   = modal.querySelector('.reedcrm-email-modal-meta');
        var attach = modal.querySelector('.reedcrm-email-modal-attach');
        var iframe = modal.querySelector('.reedcrm-email-modal-body');

        title.textContent = 'Chargement…';
        meta.innerHTML = '';
        attach.innerHTML = '';
        iframe.srcdoc = '';
        modal.classList.add('is-open');

        var url = base + '/custom/reedcrm/ajax/preview_email.php'
            + '?modulepart=' + encodeURIComponent(modulepart)
            + '&file=' + encodeURIComponent(file);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.success) {
                    title.textContent = 'Aperçu du mail';
                    meta.innerHTML = '<div class="reedcrm-email-modal-error">' + escapeHtml(d && d.message ? d.message : 'Erreur') + '</div>';
                    return;
                }
                title.textContent = d.subject || '(sans objet)';

                var html = '';
                html += '<div><span class="lbl">De</span> ' + escapeHtml(d.fromName) + (d.fromEmail ? ' &lt;' + escapeHtml(d.fromEmail) + '&gt;' : '') + '</div>';
                if (d.to && d.to.length) { html += '<div><span class="lbl">À</span> ' + escapeHtml(d.to.join(', ')) + '</div>'; }
                if (d.cc && d.cc.length) { html += '<div><span class="lbl">Cc</span> ' + escapeHtml(d.cc.join(', ')) + '</div>'; }
                if (d.date) { html += '<div><span class="lbl">Date</span> ' + escapeHtml(d.date) + '</div>'; }
                meta.innerHTML = html;

                iframe.srcdoc = '<!doctype html><meta charset="utf-8"><base target="_blank">'
                    + '<style>body{font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#1a2d40;margin:12px;word-wrap:break-word;}img{max-width:100%;height:auto;}table{max-width:100%;}</style>'
                    + (d.body || '');

                if (d.attachments && d.attachments.length) {
                    var a = '<div class="reedcrm-email-attach-title"><i class="fas fa-paperclip"></i> Pièces jointes (' + d.attachments.length + ')</div><ul>';
                    d.attachments.forEach(function (att) {
                        var href = 'data:' + att.mime + ';base64,' + att.data;
                        a += '<li><a download="' + escapeHtml(att.filename) + '" href="' + href + '">'
                            + '<i class="fas fa-download"></i> ' + escapeHtml(att.filename)
                            + ' <span class="sz">(' + escapeHtml(att.size) + ')</span></a></li>';
                    });
                    a += '</ul>';
                    attach.innerHTML = a;
                }
            })
            .catch(function () {
                title.textContent = 'Aperçu du mail';
                meta.innerHTML = '<div class="reedcrm-email-modal-error">Erreur réseau</div>';
            });
    }

    /**
     * Hide the modal.
     * @return {void}
     */
    function closeModal() {
        var modal = document.getElementById('reedcrm-email-modal');
        if (modal) {
            modal.classList.remove('is-open');
            var iframe = modal.querySelector('.reedcrm-email-modal-body');
            if (iframe) { iframe.srcdoc = ''; }
        }
    }

    if (typeof jQuery !== 'undefined') {
        jQuery(function ($) {
            injectPreviewButtons();

            $(document).on('click', '.reedcrm-email-preview-btn', function () {
                openPreview(this.dataset.modulepart, this.dataset.file, this.dataset.base);
            });
            $(document).on('click', '[data-action="close-email-modal"]', closeModal);
            $(document).on('click', '#reedcrm-email-modal', function (e) {
                if (e.target === this) { closeModal(); }
            });
            $(document).on('keydown', function (e) {
                if (e.key === 'Escape') { closeModal(); }
            });
        });
    }
})();

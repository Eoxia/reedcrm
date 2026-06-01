/**
 * @file    js/reedcrm_line_description.js
 * @brief   Inject the product/service description under each line on Reception/Shipment
 *          cards. Data is provided as a data-island by the PHP hook. Standalone and
 *          self-initialising: the saturne.js bootstrap is NOT loaded on Dolibarr core pages.
 */
(function () {
    'use strict';

    function injectLineDescriptions() {
        var dataEl = document.getElementById('reedcrm-linedesc-data');
        if (!dataEl) {
            return;
        }

        var map;
        try {
            map = JSON.parse(dataEl.textContent || dataEl.innerHTML);
        } catch (e) {
            return;
        }

        var cells = document.querySelectorAll('td.linecoldescription');
        for (var i = 0; i < cells.length; i++) {
            var cell = cells[i];
            // Idempotent (possible AJAX refreshes)
            if (cell.getAttribute('data-reedcrm-desc') === '1') {
                continue;
            }

            var link = cell.querySelector('a[href*="product/card.php?id="]');
            if (!link) {
                continue;
            }

            var match = link.getAttribute('href').match(/product\/card\.php\?id=(\d+)/);
            if (!match) {
                continue;
            }

            var desc = map[match[1]];
            if (!desc) {
                continue;
            }

            var span = document.createElement('span');
            span.className = 'reedcrm-line-desc';
            span.innerHTML = '<br>' + desc;
            cell.appendChild(span);
            cell.setAttribute('data-reedcrm-desc', '1');
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectLineDescriptions);
    } else {
        injectLineDescriptions();
    }
})();

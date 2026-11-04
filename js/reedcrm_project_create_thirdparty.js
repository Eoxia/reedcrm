/**
 * @file    js/reedcrm_project_create_thirdparty.js
 * @brief   Keep what was already typed in the project creation form when the "+" next to the thirdparty
 *          is used to create one: Dolibarr comes back to an empty form (?action=create), the label lost.
 *          The fields are added to the backtopage of the link when it is clicked.
 *          Standalone: the saturne.js bootstrap is NOT loaded on Dolibarr core pages.
 */
$(document).on('click', 'form a[href*="/societe/card.php"][href*="action=create"]', function () {
    // Fields the thirdparty creation must not carry back: it appends the new thirdparty itself
    var skipped = ['token', 'action', 'backtopage', 'backtopageforcancel', 'dol_openinpopup', 'socid'];
    // Apache refuses a request line over 8 KB: a long description is left behind rather than losing it all
    var maxLength = 6000;
    // Values the firewall of Dolibarr (waf.inc.php) refuses in a GET parameter: carried back, they would
    // turn the page into an "Access refused" one, so they stay behind as before
    var refusedInGet = /["<>]|\b(select|delete)\s+from\b|\binsert\s+into\b|\bunion\b.+\bselect\b/i;

    if (typeof CKEDITOR !== 'undefined') {
        for (var instance in CKEDITOR.instances) {
            CKEDITOR.instances[instance].updateElement();
        }
    }

    var link        = new URL(this.href, window.location.href);
    var backToPage  = new URL(link.searchParams.get('backtopage') || window.location.pathname + '?action=create', window.location.href);
    var initialBack = backToPage.pathname + backToPage.search;

    $(this).closest('form').serializeArray().forEach(function (field) {
        if (field.value === '' || skipped.indexOf(field.name) !== -1 || refusedInGet.test(field.value)) {
            return;
        }
        var candidate = new URLSearchParams(backToPage.search);
        candidate.append(field.name, field.value);
        if ((backToPage.pathname + '?' + candidate.toString()).length <= maxLength) {
            backToPage.search = candidate.toString();
        }
    });

    if (backToPage.pathname + backToPage.search !== initialBack) {
        link.searchParams.set('backtopage', backToPage.pathname + backToPage.search);
        this.href = link.toString();
    }
});

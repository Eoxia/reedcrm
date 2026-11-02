/**
 * @file    js/reedcrm_propal_model.js
 * @brief   Proposal templates on the Dolibarr cards. On the project card, the "Create a proposal from a template"
 *          entry printed hidden by the module joins the "Create" dropdown, which the core builds with no hook.
 *          On the proposal creation form reached from that entry, the template selector opens by itself.
 *          Standalone: the saturne.js bootstrap is NOT loaded on Dolibarr core pages.
 */
$(function () {
    // On top of the dropdown, next to the proposal entry. That entry is missing when the core does not offer a
    // proposal for the project (thirdparty neither customer nor prospect...): the template one then stays hidden
    var $entry       = $('.reedcrm-propal-model-create');
    var $propalEntry = $('.dropdown-content a[href*="/comm/propal/card.php?action=create"]').not($entry).first();
    if ($entry.length && $propalEntry.length) {
        $propalEntry.before($entry.removeClass('hidden'));
    }

    // The core turns the selector into a select2 in a ready handler printed after this script: the selector is
    // opened once all the ready handlers have run
    window.setTimeout(function () {
        var $selector = $('#reedcrm_propal_model[data-open-on-load]');
        if ($selector.hasClass('select2-hidden-accessible')) {
            $selector.select2('open');
        }
    }, 0);
});

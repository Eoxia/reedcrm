/**
 * @file    js/reedcrm_opp_percent.js
 * @brief   Make the opportunity probability follow the opportunity status chosen on the project card
 *          (creation form, edit form and inline status edit). Dolibarr keeps the previous probability
 *          when the new status has a lower default one, so an opportunity could never go back down.
 *          Standalone: the saturne.js bootstrap is NOT loaded on Dolibarr core pages.
 */
$(document).on('change', '#opp_status', function () {
    // Delegated, so it runs after the handler bound by Dolibarr on the select and has the last word
    var defaultPercent = $(this).find('option:selected').attr('defaultpercent');
    if (defaultPercent !== undefined && defaultPercent !== '') {
        // defaultpercent is the raw DB value (double(5,2), ie "20.00")
        $('#opp_percent').val(parseFloat(defaultPercent));
    }
});

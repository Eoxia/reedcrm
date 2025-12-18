/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    js/stats.js
 * \ingroup reedcrm
 * \brief   JavaScript file for statistics page
 */

'use strict';
 
/**
 * Init stats JS
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @type {Object}
 */
window.reedcrm.stats = {};

/**
 * Stats init
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.stats.init = function() {
    window.reedcrm.stats.event();
};

/**
 * Stats event
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @returns {void}
 */
window.reedcrm.stats.event = function() {
    // Collapse/expand filters
    var hasCustomFilters = jQuery('#filters-header').data('has-filters') === 'true';

    jQuery("#filters-header").click(function() {
        jQuery(".reedcrm-stats-filters").toggle();
        jQuery(this).toggleClass("collapsed");
    });

    if (hasCustomFilters !== 'true') {
        jQuery(".reedcrm-stats-filters").hide();
        jQuery("#filters-header").addClass("collapsed");
    }

    // Sales funnel filter button (from buildSalesFunnelFilters)
    jQuery(document).off("click", "#apply-salesfunnel-filter-btn").on("click", "#apply-salesfunnel-filter-btn", function () {
        var dateStartDay = jQuery("select[name='salesfunnel_date_startday']").val() || "";
        var dateStartMonth = jQuery("select[name='salesfunnel_date_startmonth']").val() || "";
        var dateStartYear = jQuery("select[name='salesfunnel_date_startyear']").val() || "";
        var dateEndDay = jQuery("select[name='salesfunnel_date_endday']").val() || "";
        var dateEndMonth = jQuery("select[name='salesfunnel_date_endmonth']").val() || "";
        var dateEndYear = jQuery("select[name='salesfunnel_date_endyear']").val() || "";

        var $form = jQuery("#dashBoardForm");
        if (!$form.length) {
            $form = jQuery("#statsform").first();
        }
        if (!$form.length) {
            $form = jQuery("form.dashboard").first();
        }

        function setHidden($context, name, value) {
            var $field = $context.find("input[name='" + name + "']");
            if (value === "" || value === null) {
                $field.remove();
                return;
            }
            if (!$field.length) {
                $field = jQuery("<input>", { type: "hidden", name: name });
                $context.append($field);
            }
            $field.val(value);
        }

        if ($form.length) {
            setHidden($form, "salesfunnel_date_startday", dateStartDay);
            setHidden($form, "salesfunnel_date_startmonth", dateStartMonth);
            setHidden($form, "salesfunnel_date_startyear", dateStartYear);
            setHidden($form, "salesfunnel_date_endday", dateEndDay);
            setHidden($form, "salesfunnel_date_endmonth", dateEndMonth);
            setHidden($form, "salesfunnel_date_endyear", dateEndYear);
            setHidden($form, "apply_salesfunnel_filter", 1);
            $form.submit();
            return;
        }

        var params = new URLSearchParams(window.location.search);
        params.delete("salesfunnel_date_startday");
        params.delete("salesfunnel_date_startmonth");
        params.delete("salesfunnel_date_startyear");
        params.delete("salesfunnel_date_endday");
        params.delete("salesfunnel_date_endmonth");
        params.delete("salesfunnel_date_endyear");
        if (dateStartDay && dateStartMonth && dateStartYear) {
            params.set("salesfunnel_date_startday", dateStartDay);
            params.set("salesfunnel_date_startmonth", dateStartMonth);
            params.set("salesfunnel_date_startyear", dateStartYear);
        }
        if (dateEndDay && dateEndMonth && dateEndYear) {
            params.set("salesfunnel_date_endday", dateEndDay);
            params.set("salesfunnel_date_endmonth", dateEndMonth);
            params.set("salesfunnel_date_endyear", dateEndYear);
        }
        window.location.href = window.location.pathname + "?" + params.toString();
    });
};


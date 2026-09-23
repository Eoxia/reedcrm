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
 * \file    core/tpl/reedcrm_card_empty_fields_toggle.tpl.php
 * \ingroup reedcrm
 * \brief   Toggle hiding the fields left empty, for every object card.
 *          Loaded by the printCommonFooter hook on every page carrying the globalcard context:
 *          the JS moves the bar up into the card and only reveals it when a field is empty.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $langs, $user;

// Per-user display preference: every field is shown as long as the user has not asked otherwise
$cardFieldsDisplay = (isset($user->conf->REEDCRM_CARD_FIELDS_DISPLAY) && $user->conf->REEDCRM_CARD_FIELDS_DISPLAY === 'filled') ? 'filled' : 'all';

// The native Dolibarr cards never load reedcrm.min.css nor the module bundle, both are needed here
?>
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1)); ?>">

<div id="reedcrm-card-fields-config"
     data-url="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/save_kpi_layout.php', 1)); ?>"
     data-token="<?php echo dol_escape_htmltag(newToken()); ?>"
     data-mode="<?php echo dol_escape_htmltag($cardFieldsDisplay); ?>"
     data-trans-hide-empty="<?php echo dol_escape_htmltag($langs->trans('CardHideEmptyFields')); ?>"
     data-trans-show-all="<?php echo dol_escape_htmltag($langs->trans('CardShowAllFields')); ?>"
     data-trans-hidden-count="<?php echo dol_escape_htmltag($langs->trans('CardEmptyFieldsHiddenCount', '%s')); ?>"></div>

<div class="reedcrm-card-fields-bar" id="reedcrm-card-fields-bar" hidden>
    <button type="button" class="reedcrm-card-fields-toggle" data-mode="<?php echo dol_escape_htmltag($cardFieldsDisplay); ?>" title="<?php echo dol_escape_htmltag($langs->trans('CardEmptyFieldsTooltip')); ?>">
        <i class="reedcrm-card-fields-icon fas fa-eye-slash"></i>
        <span class="reedcrm-card-fields-label"><?php echo dol_escape_htmltag($langs->trans('CardHideEmptyFields')); ?></span>
        <span class="reedcrm-card-fields-count"></span>
    </button>
</div>

<script type="text/javascript" src="<?php echo dol_escape_htmltag(dol_buildpath('/custom/reedcrm/js/modules/card_empty_fields.js', 1)); ?>"></script>

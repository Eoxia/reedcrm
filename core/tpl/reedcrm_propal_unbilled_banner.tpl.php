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
 * \file    core/tpl/reedcrm_propal_unbilled_banner.tpl.php
 * \ingroup reedcrm
 * \brief   Red banner on top of a proposal card carrying the "not billed" tag.
 *          Loaded by the formConfirm hook, printed between the tabs and the banner of the card.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $langs;

// The native Dolibarr cards never load reedcrm.min.css, and the banner is printed long before the footer that does
?>
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(dol_buildpath('/reedcrm/css/reedcrm.min.css', 1)); ?>">

<div class="reedcrm-propal-unbilled-banner" role="alert">
    <i class="fas fa-file-invoice-dollar reedcrm-propal-unbilled-banner-icon"></i>
    <span><?php echo dol_escape_htmltag($langs->trans('PropalUnbilledBanner')); ?></span>
</div>

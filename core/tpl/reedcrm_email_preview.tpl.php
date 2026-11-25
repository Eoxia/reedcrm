<?php
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
 * \file    core/tpl/reedcrm_email_preview.tpl.php
 * \ingroup reedcrm
 * \brief   Preview of the .msg/.eml attachments, on every card and document tab of Dolibarr.
 *          Loaded by the printCommonFooter hook: these pages never load the reedcrm.min.js bundle,
 *          so the module script is loaded alone, with the URL of its endpoint.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $langs;

// $parameters: the hook parameters of printCommonFooter(), which includes this template

// reedcrm_asset_full_url() used by the assets printed below: without a version, the browser keeps serving the previous release
require_once __DIR__ . '/../../lib/reedcrm_function.lib.php';

// The globalcard pages already get reedcrm.min.css from the empty fields toggle, the other document tabs do not
if (strpos($parameters['context'], 'globalcard') === false) : ?>
<link rel="stylesheet" href="<?php echo dol_escape_htmltag(reedcrm_asset_full_url('/reedcrm/css/reedcrm.min.css')); ?>">
<?php endif; ?>

<div id="reedcrm-email-preview-config" hidden
     data-url="<?php echo dol_escape_htmltag(dol_buildpath('/reedcrm/ajax/preview_email.php', 1)); ?>"
     data-trans-preview="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('ReedCRMEmailPreview')); ?>"
     data-trans-error="<?php echo dol_escape_htmltag($langs->transnoentitiesnoconv('ReedCRMEmailPreviewNetworkError')); ?>"></div>

<script type="text/javascript" src="<?php echo dol_escape_htmltag(reedcrm_asset_full_url('/reedcrm/js/modules/email_preview.js')); ?>"></script>

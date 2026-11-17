<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/view/eventpro/eventpro_modal.tpl.php
 * \ingroup reedcrm
 * \brief   Side modal the "+" of the relaunch widget load their form into (eventpro.js loadModalContent)
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $langs;

$langs->load('reedcrm@reedcrm'); ?>
<div class="wpeo-modal modal-eventpro" id="eventproCardModal">
    <div class="modal-container wpeo-modal-event">
        <div class="modal-header">
            <h2 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('QuickEventCreation')); ?></h2>
            <div class="modal-close"><i class="fas fa-times"></i></div>
        </div>
        <div class="modal-content">
            <div id="eventproCardModal-loader" class="wpeo-loader"></div>
            <div id="eventproCardModal-content"></div>
        </div>
    </div>
</div>

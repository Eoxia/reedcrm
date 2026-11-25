<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/reedcrm_pwa_relaunch_bar.tpl.php
 * \ingroup reedcrm
 * \brief   Sticky relaunch bar of the opportunity App page: one counter per relaunch type, each with
 *          the desktop surfaces (past / upcoming), rendered by reedcrm_render_relaunch_widget() so the
 *          screens can never drift apart again.
 *          Expects $project (Project). $callListId, when
 *          the page was reached from a call list, is carried over so the whole add-an-event round
 *          trip comes back with the way home still in the header.
 */
if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

global $langs, $user;

$canAddEvent   = $user->hasRight('agenda', 'myactions', 'create') && $user->hasRight('reedcrm', 'eventpro', 'write');
?>
<div class="pwa-opp-relaunch-bar">
    <?php
    require_once __DIR__ . '/../../../lib/reedcrm_relaunch.lib.php';

    print reedcrm_render_relaunch_widget([
        'projectId'  => (int) $project->id,
        'mode'       => 'link',
        'canCreate'  => $canAddEvent,
        'extraQuery' => !empty($callListId) ? '&call_list_id=' . (int) $callListId : '',
    ]);
    ?>
</div>

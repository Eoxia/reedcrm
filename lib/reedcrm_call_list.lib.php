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
 * \file    lib/reedcrm_call_list.lib.php
 * \ingroup reedcrm
 * \brief   Library functions for CallList card (tabs preparation).
 */

/**
 * Prepare array of tabs for the CallList card.
 *
 * @param  CallList $object CallList object
 * @return array            Array of tabs
 */
function call_list_prepare_head(CallList $object): array
{
    global $conf, $langs;

    saturne_load_langs();

    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id;
    $head[$h][1] = $langs->trans('CallList');
    $head[$h][2] = 'card';
    $h++;

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $object->id . '&show=notes';
    $head[$h][1] = $langs->trans('Notes');
    $head[$h][2] = 'notes';
    $h++;

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'call_list@reedcrm');

    return $head;
}

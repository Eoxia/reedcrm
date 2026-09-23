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
 * \file    core/modules/reedcrm/call_list/mod_call_list_standard.php
 * \ingroup reedcrm
 * \brief   Standard numbering module for CallList (LA-0001).
 */

require_once __DIR__ . '/modules_calllist.php';

/**
 * Class for standard CallList numbering.
 */
class mod_call_list_standard extends ModeleNumRefCallList
{
    /**
     * @var string Numbering module ref prefix.
     */
    public string $prefix = 'LA';

    /**
     * @var string Name.
     */
    public string $name = 'Standard';
}

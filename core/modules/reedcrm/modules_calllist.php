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
 * \file    core/modules/reedcrm/modules_calllist.php
 * \ingroup reedcrm
 * \brief   PDF model provider for CallList — used by FormFile::showdocuments.
 */

/**
 * PDF model provider for FormFile::showdocuments('reedcrm:CallList', ...).
 */
class ModelePDFCallList
{
    /**
     * Return list of available PDF models.
     *
     * @param  DoliDB $db Database handler
     * @return array      Associative array [classname => label]
     */
    public static function liste_modeles(DoliDB $db): array
    {
        return ['pdf_calllist_standard' => 'Standard'];
    }
}

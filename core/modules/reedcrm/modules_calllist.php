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
 * \brief   Abstract class for CallList PDF generation modules.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/commondocgenerator.class.php';

/**
 * Abstract class for CallList PDF generation modules.
 */
abstract class ModelePDFCallList extends CommonDocGenerator
{
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     * Return list of active PDF models for CallList.
     *
     * @param  DoliDB $db                Database handler
     * @param  int    $maxfilenamelength Max length of filename
     * @return array                     List of models
     */
    public static function liste_modeles($db, $maxfilenamelength = 0)
    {
        // phpcs:enable
        return [getDolGlobalString('REEDCRM_CALL_LIST_GENERATE_DOCUMENTS_ADDON', 'pdf_calllist_standard') => 'Standard'];
    }
}

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
 * \file    lib/reedcrm_propal_model.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the proposal templates, the proposals carrying the template tag.
 */

require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

// Label of the template tag the setup creates
const REEDCRM_PROPAL_MODEL_DEFAULT_TAG_LABEL = 'Proposition modèle';

/**
 * Id of the tag marking the proposal templates.
 *
 * The tag picked in the setup, else the one the setup creates by default, found by its label as the
 * template list always did before the setup offered to pick another one.
 *
 * @param  DoliDB $db Database handler
 * @return int        Tag id, 0 when there is none
 */
function reedcrm_propal_model_get_tag_id(DoliDB $db): int
{
    static $tagID = null;

    if ($tagID === null) {
        $tagID = getDolGlobalInt('REEDCRM_PROPAL_MODEL_TAG_ID');
        if ($tagID <= 0) {
            $category = new Categorie($db);
            $tagID    = $category->fetch(0, REEDCRM_PROPAL_MODEL_DEFAULT_TAG_LABEL, Categorie::TYPE_PROPOSAL) > 0 ? (int) $category->id : 0;
        }
    }

    return $tagID;
}

/**
 * SQL condition, true when the proposal of the given alias is a template.
 *
 * @param  DoliDB $db    Database handler
 * @param  string $alias Alias of the proposal table
 * @return string        SQL condition, never true when no template tag exists
 */
function reedcrm_propal_model_sql_condition(DoliDB $db, string $alias): string
{
    return 'EXISTS (SELECT 1 FROM ' . $db->prefix() . 'categorie_propal AS cpmodel WHERE cpmodel.fk_propal = ' . $alias . '.rowid AND cpmodel.fk_categorie = ' . reedcrm_propal_model_get_tag_id($db) . ')';
}

/**
 * Proposal templates, to be picked on the proposal creation form.
 *
 * @param  DoliDB $db Database handler
 * @return array<int,string> Label of each template (ref, label and amount excl. tax), by proposal id
 */
function reedcrm_propal_model_get_select_options(DoliDB $db): array
{
    global $conf, $langs;

    static $options = null;

    if ($options === null) {
        $options = [];
        if (reedcrm_propal_model_get_tag_id($db) <= 0) {
            return $options;
        }

        $sql  = 'SELECT p.rowid, p.ref, p.total_ht, pe.reedcrm_propal_label';
        $sql .= ' FROM ' . $db->prefix() . 'propal AS p';
        $sql .= ' LEFT JOIN ' . $db->prefix() . 'propal_extrafields AS pe ON pe.fk_object = p.rowid';
        $sql .= ' WHERE p.entity IN (' . getEntity('propal') . ')';
        $sql .= ' AND ' . reedcrm_propal_model_sql_condition($db, 'p');
        $sql .= ' ORDER BY pe.reedcrm_propal_label, p.ref';

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $label                      = $obj->ref . (dol_strlen($obj->reedcrm_propal_label) > 0 ? ' - ' . $obj->reedcrm_propal_label : '');
                $options[(int) $obj->rowid] = $label . ' (' . price($obj->total_ht, 0, $langs, 1, -1, -1, $conf->currency) . ' ' . $langs->transnoentities('HT') . ')';
            }
            $db->free($resql);
        }
    }

    return $options;
}

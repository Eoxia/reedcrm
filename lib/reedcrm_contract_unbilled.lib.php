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
 * \file    lib/reedcrm_contract_unbilled.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the "not billed" banner of the contracts.
 */

require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

/**
 * SQL condition, true when an invoice is linked to the given object.
 *
 * The link is stored either way round depending on how the invoice was made, and an abandoned
 * invoice billed nothing.
 *
 * @param  string $elementTypeSql Element type of the object, as an SQL expression
 * @param  string $elementIDSql   Id of the object, as an SQL expression
 * @return string                 SQL condition
 */
function reedcrmContractUnbilledInvoiceLinkedSql(string $elementTypeSql, string $elementIDSql): string
{
    $invoiceAlive = ' AND f.fk_statut <> ' . Facture::STATUS_ABANDONED;

    $sql  = '(EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_element as ef';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = ef.fk_target' . $invoiceAlive;
    $sql .= ' WHERE ef.fk_source = ' . $elementIDSql . ' AND ef.sourcetype = ' . $elementTypeSql . " AND ef.targettype = 'facture')";
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_element as ef';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = ef.fk_source' . $invoiceAlive;
    $sql .= ' WHERE ef.fk_target = ' . $elementIDSql . ' AND ef.targettype = ' . $elementTypeSql . " AND ef.sourcetype = 'facture'))";

    return $sql;
}

/**
 * SQL condition, true when the contract aliased "c" is billed.
 *
 * The invoices generated from a template invoice inherit its links, so the recurring billing of
 * the contract is linked to it too. The proposal or the order the contract was made from may have
 * been invoiced instead: the deal is then billed, as for the "not billed" tag of the proposals.
 *
 * @return string SQL condition
 */
function reedcrmContractUnbilledBilledSql(): string
{
    $origins = "('propal', 'commande')";

    $sql  = '(' . reedcrmContractUnbilledInvoiceLinkedSql("'contrat'", 'c.rowid');
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_element as eo';
    $sql .= " WHERE eo.fk_target = c.rowid AND eo.targettype = 'contrat' AND eo.sourcetype IN " . $origins;
    $sql .= ' AND ' . reedcrmContractUnbilledInvoiceLinkedSql('eo.sourcetype', 'eo.fk_source') . ')';
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_element as eo';
    $sql .= " WHERE eo.fk_source = c.rowid AND eo.sourcetype = 'contrat' AND eo.targettype IN " . $origins;
    $sql .= ' AND ' . reedcrmContractUnbilledInvoiceLinkedSql('eo.targettype', 'eo.fk_target') . '))';

    return $sql;
}

/**
 * Is the contract validated and not billed yet ?
 *
 * Dolibarr has no tag on the contracts: the rule is checked live, so the banner goes away as soon
 * as an invoice is linked.
 *
 * @param  DoliDB $db         Database handler
 * @param  int    $contractID Contract id
 * @return bool
 */
function reedcrmContractIsUnbilled(DoliDB $db, int $contractID): bool
{
    if ($contractID <= 0) {
        return false;
    }

    $sql  = 'SELECT 1 FROM ' . MAIN_DB_PREFIX . 'contrat as c';
    $sql .= ' WHERE c.rowid = ' . $contractID . ' AND c.statut = ' . Contrat::STATUS_VALIDATED;
    $sql .= ' AND NOT ' . reedcrmContractUnbilledBilledSql();

    $resql = $db->query($sql);

    return $resql && $db->num_rows($resql) > 0;
}

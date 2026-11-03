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
 * \file    lib/reedcrm_propal_unbilled.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the "not billed" tag of the proposals.
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

/**
 * Id of the "not billed" proposal tag, created when it is missing and a user is given.
 *
 * The id is kept in a constant, but the tag can be deleted from the categories pages: it is then
 * looked up by its label before being created again, so a tag made by hand is reused, not doubled.
 *
 * @param  DoliDB    $db   Database handler
 * @param  User|null $user User creating the tag, null to only read the existing one
 * @return int             Tag id, 0 when there is none (or its creation failed)
 */
function reedcrmPropalUnbilledGetTagID(DoliDB $db, ?User $user = null): int
{
    global $conf, $langs;

    $category = new Categorie($db);
    $tagID    = getDolGlobalInt('REEDCRM_PROPAL_UNBILLED_TAG');
    if ($tagID > 0 && $category->fetch($tagID) > 0 && $category->type == $category->MAP_ID[Categorie::TYPE_PROPOSAL]) {
        return $tagID;
    }
    if ($user === null) {
        return 0;
    }

    $langs->load('reedcrm@reedcrm');
    $label    = $langs->transnoentities('PropalUnbilledTag');
    $category = new Categorie($db);
    if ($category->fetch(0, $label, Categorie::TYPE_PROPOSAL) > 0) {
        $tagID = (int) $category->id;
    } else {
        $category->label   = $label;
        $category->type    = Categorie::TYPE_PROPOSAL;
        $category->visible = 1;
        $tagID             = $category->create($user);
    }

    // Never store the negative error code of create(): the tag would look configured and never be retried
    if ($tagID <= 0) {
        return 0;
    }
    dolibarr_set_const($db, 'REEDCRM_PROPAL_UNBILLED_TAG', $tagID, 'integer', 0, '', $conf->entity);

    return $tagID;
}

/**
 * SQL condition, true when the proposal aliased "pr" is billed.
 *
 * Classifying a proposal as billed is a manual step, or a workflow setting, that is often skipped:
 * an invoice linked to the proposal, or to the order or the contract created from it, is therefore
 * enough. The link is stored either way round depending on how the invoice was made, and an
 * abandoned invoice billed nothing.
 *
 * @return string SQL condition
 */
function reedcrmPropalUnbilledBilledSql(): string
{
    $invoiceAlive = ' AND f.fk_statut <> ' . Facture::STATUS_ABANDONED;

    $sql  = '(pr.fk_statut = ' . Propal::STATUS_BILLED;
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_element as ee';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = ee.fk_target' . $invoiceAlive;
    $sql .= " WHERE ee.fk_source = pr.rowid AND ee.sourcetype = 'propal' AND ee.targettype = 'facture')";
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_element as ee';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = ee.fk_source' . $invoiceAlive;
    $sql .= " WHERE ee.fk_target = pr.rowid AND ee.targettype = 'propal' AND ee.sourcetype = 'facture')";
    $sql .= ' OR EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'element_element as e1';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "element_element as e2 ON e2.fk_source = e1.fk_target AND e2.sourcetype = e1.targettype AND e2.targettype = 'facture'";
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = e2.fk_target' . $invoiceAlive;
    $sql .= " WHERE e1.fk_source = pr.rowid AND e1.sourcetype = 'propal' AND e1.targettype IN ('commande', 'contrat')))";

    return $sql;
}

/**
 * SQL condition, true when the proposal aliased "pr" is signed and not billed yet.
 *
 * @return string SQL condition
 */
function reedcrmPropalUnbilledSql(): string
{
    return '(pr.fk_statut = ' . Propal::STATUS_SIGNED . ' AND NOT ' . reedcrmPropalUnbilledBilledSql() . ')';
}

/**
 * SQL condition, true when the proposal aliased "pr" carries the tag.
 *
 * @param  int    $tagID Tag id
 * @return string        SQL condition
 */
function reedcrmPropalUnbilledTaggedSql(int $tagID): string
{
    return 'EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . 'categorie_propal as cp WHERE cp.fk_categorie = ' . $tagID . ' AND cp.fk_propal = pr.rowid)';
}

/**
 * What a synchronisation of the tag would change right now.
 *
 * @param  DoliDB $db    Database handler
 * @param  int    $tagID Tag id, 0 when it does not exist yet
 * @return array{to_tag: int, to_untag: int}
 */
function reedcrmPropalUnbilledCountChanges(DoliDB $db, int $tagID): array
{
    $counts = ['to_tag' => 0, 'to_untag' => 0];

    $sql  = 'SELECT SUM(CASE WHEN ' . reedcrmPropalUnbilledSql() . ($tagID > 0 ? ' AND NOT ' . reedcrmPropalUnbilledTaggedSql($tagID) : '') . ' THEN 1 ELSE 0 END) as to_tag';
    $sql .= ', SUM(CASE WHEN ' . ($tagID > 0 ? reedcrmPropalUnbilledTaggedSql($tagID) . ' AND ' . reedcrmPropalUnbilledBilledSql() : '1 = 0') . ' THEN 1 ELSE 0 END) as to_untag';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propal as pr';
    $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ')';

    $resql = $db->query($sql);
    if ($resql && ($obj = $db->fetch_object($resql))) {
        $counts['to_tag']   = (int) $obj->to_tag;
        $counts['to_untag'] = (int) $obj->to_untag;
    }

    return $counts;
}

/**
 * Put the tag on every proposal that is signed and not billed, and take it off the billed ones.
 *
 * Plain SQL rather than Categorie::add_type(): the backlog can hold thousands of proposals, each
 * link would open its own transaction and fire its own CATEGORY_MODIFY trigger.
 *
 * @param  DoliDB $db    Database handler
 * @param  int    $tagID Tag id
 * @return array{tagged: int, untagged: int}|int Number of proposals tagged and untagged, -1 on error
 */
function reedcrmPropalUnbilledSync(DoliDB $db, int $tagID)
{
    if ($tagID <= 0) {
        return -1;
    }

    $db->begin();

    $sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'categorie_propal (fk_categorie, fk_propal)';
    $sql .= ' SELECT ' . $tagID . ', pr.rowid FROM ' . MAIN_DB_PREFIX . 'propal as pr';
    $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ') AND ' . reedcrmPropalUnbilledSql();
    $sql .= ' AND NOT ' . reedcrmPropalUnbilledTaggedSql($tagID);
    $resqlTag = $db->query($sql);
    // MySQL only reports the rows of the last query run on the connection: read it before the next one
    $tagged = $resqlTag ? (int) $db->affected_rows($resqlTag) : 0;

    // Only the billed proposals lose the tag: one put by hand on a proposal not signed yet stays
    $sql  = 'DELETE FROM ' . MAIN_DB_PREFIX . 'categorie_propal WHERE fk_categorie = ' . $tagID;
    $sql .= ' AND fk_propal IN (SELECT pr.rowid FROM ' . MAIN_DB_PREFIX . 'propal as pr';
    $sql .= ' WHERE pr.entity IN (' . getEntity('propal') . ') AND ' . reedcrmPropalUnbilledBilledSql() . ')';
    $resqlUntag = $resqlTag ? $db->query($sql) : false;
    $untagged   = $resqlUntag ? (int) $db->affected_rows($resqlUntag) : 0;

    if (!$resqlTag || !$resqlUntag) {
        dol_syslog(__FUNCTION__ . ' ' . $db->lasterror(), LOG_ERR);
        $db->rollback();
        return -1;
    }
    $db->commit();

    return ['tagged' => $tagged, 'untagged' => $untagged];
}

/**
 * Does the proposal carry the "not billed" tag ?
 *
 * @param  DoliDB $db       Database handler
 * @param  int    $propalID Proposal id
 * @return bool
 */
function reedcrmPropalUnbilledHasTag(DoliDB $db, int $propalID): bool
{
    $tagID = getDolGlobalInt('REEDCRM_PROPAL_UNBILLED_TAG');
    if ($tagID <= 0 || $propalID <= 0 || !isModEnabled('categorie')) {
        return false;
    }

    $sql   = 'SELECT 1 FROM ' . MAIN_DB_PREFIX . 'categorie_propal WHERE fk_categorie = ' . $tagID . ' AND fk_propal = ' . $propalID;
    $resql = $db->query($sql);

    return $resql && $db->num_rows($resql) > 0;
}

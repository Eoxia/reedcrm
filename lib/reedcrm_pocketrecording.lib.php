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
 * \file    lib/reedcrm_pocketrecording.lib.php
 * \ingroup reedcrm
 * \brief   Library files with functions for the Pocket recordings and their linked objects.
 */

dol_include_once('/saturne/lib/object.lib.php');
dol_include_once('/saturne/lib/linked_object.lib.php');

// Configuration constant prefix driving every link of a Pocket recording.
define('REEDCRM_POCKET_LINK_CONST_PREFIX', 'REEDCRM_POCKET_LINK_');

// ReedCRM objects are never offered as a link target of a recording.
define('REEDCRM_POCKET_LINK_EXCLUDED_PREFIX', 'reedcrm_');

// Element type carrying the links on the ReedCRM side, as written in llx_element_element.
// CommonObject::getElementType() prefixes the element with the module for every non core module,
// so the rows hold reedcrm_pocketrecording and not the bare element name.
define('REEDCRM_POCKET_LINK_ELEMENT_TYPE', 'reedcrm_pocketrecording');

/**
 * Return the tabs of a Pocket recording card.
 *
 * @param  PocketRecording $object Recording the tabs are built for.
 * @return array<int,array<int,string>> Tabs accepted by dol_get_fiche_head().
 */
function pocketrecording_prepare_head(PocketRecording $object): array
{
    global $conf, $langs, $user;

    saturne_load_langs();

    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/pocketrecording/pocketrecording_card.php', 1) . '?id=' . $object->id;
    $head[$h][1] = $langs->trans('PocketRecording');
    $head[$h][2] = 'card';
    $h++;

    $head[$h][0] = dol_buildpath('/custom/reedcrm/view/pocketrecording/pocketrecording_card.php', 1) . '?id=' . $object->id . '&show=transcript';
    $head[$h][1] = $langs->trans('PocketTranscript');
    $head[$h][2] = 'transcript';
    $h++;

    if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
        $head[$h][0] = dol_buildpath('/custom/saturne/view/saturne_agenda.php', 1) . '?id=' . $object->id . '&module_name=ReedCRM&object_type=' . $object->element;
        $head[$h][1] = $langs->trans('Events');
        $head[$h][2] = 'agenda';
        $h++;
    }

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'pocketrecording@reedcrm');

    return $head;
}

/**
 * Get the objects a Pocket recording may be linked to.
 *
 * @return array<string,array<string,mixed>> Subset of saturne_get_objects_metadata().
 */
function reedcrm_pocket_get_linkable_objects(): array
{
    global $conf;

    if (!function_exists('saturne_get_objects_metadata') || !function_exists('saturne_filter_linkable_objects')) {
        return [];
    }

    if (empty($conf->cache['reedcrmObjectsMetadata'])) {
        $conf->cache['reedcrmObjectsMetadata'] = saturne_get_objects_metadata();
    }

    return saturne_filter_linkable_objects($conf->cache['reedcrmObjectsMetadata'], [REEDCRM_POCKET_LINK_EXCLUDED_PREFIX]);
}

/**
 * Get the object types whose link is enabled by configuration.
 *
 * @return string[] List of enabled object types.
 */
function reedcrm_pocket_get_enabled_linked_object_types(): array
{
    if (!function_exists('saturne_get_enabled_linked_object_types')) {
        return [];
    }

    return saturne_get_enabled_linked_object_types(reedcrm_pocket_get_linkable_objects(), REEDCRM_POCKET_LINK_CONST_PREFIX);
}

/**
 * Measure how much each linkable object is used by the recordings.
 *
 * The module carries no extrafield on the linked objects, but the admin view reads both counters:
 * the same link count is returned twice so the usage column and the confirmation stay consistent.
 *
 * @return array<string,array{links:int,extrafields:array<string,int>}> objectType => usage counters.
 */
function reedcrm_pocket_get_linked_object_usage(): array
{
    if (!function_exists('saturne_get_linked_object_usage')) {
        return [];
    }

    $usage = saturne_get_linked_object_usage(
        reedcrm_pocket_get_linkable_objects(),
        [],
        [REEDCRM_POCKET_LINK_ELEMENT_TYPE]
    );

    foreach ($usage as $objectType => $counters) {
        $usage[$objectType]['extrafields'][REEDCRM_POCKET_LINK_ELEMENT_TYPE] = $counters['links'];
    }

    return $usage;
}

/**
 * Align the tabs and hooks on the enabled links.
 *
 * Idempotent: replaying it converges to the same state whatever the starting point.
 * Must be called from a web request, see saturne_refresh_module_registrations().
 *
 * @return array{tabs:int,hooks:int,errors:int} Synchronisation report.
 */
function reedcrm_pocket_sync_linked_objects(): array
{
    if (!function_exists('saturne_refresh_module_registrations')) {
        return ['tabs' => 0, 'hooks' => 0, 'errors' => 0];
    }

    return saturne_refresh_module_registrations('reedcrm', 'modReedCRM');
}

/**
 * Enable every link that already carries data, so a cleanup can never hide existing recordings.
 *
 * Only missing constants are written: a link explicitly disabled and unused stays disabled.
 *
 * @return string[] List of object types enabled by this call.
 */
function reedcrm_pocket_run_linked_object_backward(): array
{
    global $conf, $db;

    $usage   = reedcrm_pocket_get_linked_object_usage();
    $enabled = [];

    foreach (array_keys(reedcrm_pocket_get_linkable_objects()) as $objectType) {
        $constName = REEDCRM_POCKET_LINK_CONST_PREFIX . strtoupper($objectType);

        if (getDolGlobalInt($constName) > 0 || empty($usage[$objectType]['links'])) {
            continue;
        }

        dolibarr_set_const($db, $constName, 1, 'integer', 0, '', $conf->entity);
        $enabled[] = $objectType;
    }

    return $enabled;
}

/**
 * Get the metadata of an object from its element link name.
 *
 * Tabs carry the link name of the element (fromtype=commande), which is not always the key the
 * metadata array is indexed with (order): reading the array with it lands on a missing entry.
 *
 * @param  string               $linkName Element link name, ex. 'commande'.
 * @return array<string,mixed>            Matching metadata, empty array when none matches.
 */
function reedcrm_pocket_get_object_metadata_from_link_name(string $linkName): array
{
    if (empty($linkName)) {
        return [];
    }

    foreach (reedcrm_pocket_get_linkable_objects() as $objectMetadata) {
        if (($objectMetadata['link_name'] ?? '') == $linkName) {
            return $objectMetadata;
        }
    }

    return [];
}

/**
 * Link a recording to a business object.
 *
 * The gesture belongs to the object side: you are on a ticket and you attach the conversation you
 * had about it. The recording card only ever displays the result.
 *
 * @param  PocketRecording $recording Recording to attach.
 * @param  string          $linkName  Element link name of the target, ex. 'ticket'.
 * @param  int             $objectId  Target object ID.
 * @return int                        > 0 if OK, <= 0 if KO.
 */
function reedcrm_pocket_link_recording(PocketRecording $recording, string $linkName, int $objectId): int
{
    if (empty($linkName) || $objectId <= 0 || $recording->id <= 0) {
        return -1;
    }

    $recording->clearObjectLinkedCache();

    return $recording->add_object_linked($linkName, $objectId);
}

/**
 * Remove the link between a recording and a business object.
 *
 * Both directions are cleared: llx_element_element stores the pair in the order Dolibarr chose when
 * the link was created, which is not always the one the caller has in mind.
 *
 * @param  PocketRecording $recording Recording to detach.
 * @param  string          $linkName  Element link name of the target, ex. 'ticket'.
 * @param  int             $objectId  Target object ID.
 * @return int                        >= 0 if OK, < 0 if KO.
 */
function reedcrm_pocket_unlink_recording(PocketRecording $recording, string $linkName, int $objectId): int
{
    global $db;

    if (empty($linkName) || $objectId <= 0 || $recording->id <= 0) {
        return -1;
    }

    $sql  = 'DELETE FROM ' . MAIN_DB_PREFIX . 'element_element';
    $sql .= " WHERE (sourcetype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND fk_source = " . ((int) $recording->id);
    $sql .= " AND targettype = '" . $db->escape($linkName) . "' AND fk_target = " . ((int) $objectId) . ')';
    $sql .= " OR (targettype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND fk_target = " . ((int) $recording->id);
    $sql .= " AND sourcetype = '" . $db->escape($linkName) . "' AND fk_source = " . ((int) $objectId) . ')';

    $recording->clearObjectLinkedCache();

    return $db->query($sql) ? 1 : -1;
}

/**
 * Get the recordings that may still be attached to a given object.
 *
 * Already linked recordings are dropped so the selector never offers a link that exists.
 *
 * @param  string $linkName Element link name of the target, ex. 'ticket'.
 * @param  int    $objectId Target object ID.
 * @param  int    $limit    Maximum number of recordings offered.
 * @return array<int,string>          Recording ID => label shown in the selector.
 */
function reedcrm_pocket_get_linkable_recordings(string $linkName, int $objectId, int $limit = 200): array
{
    global $db, $langs;

    $recordings = [];

    $sql  = 'SELECT t.rowid, t.ref, t.label, t.recording_date';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_pocket_recording as t';
    $sql .= ' WHERE t.entity IN (' . getEntity('pocketrecording') . ')';
    $sql .= " AND NOT EXISTS (SELECT ee.rowid FROM " . MAIN_DB_PREFIX . 'element_element as ee';
    $sql .= " WHERE (ee.sourcetype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND ee.fk_source = t.rowid";
    $sql .= " AND ee.targettype = '" . $db->escape($linkName) . "' AND ee.fk_target = " . ((int) $objectId) . ')';
    $sql .= " OR (ee.targettype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND ee.fk_target = t.rowid";
    $sql .= " AND ee.sourcetype = '" . $db->escape($linkName) . "' AND ee.fk_source = " . ((int) $objectId) . '))';
    $sql .= ' ORDER BY t.recording_date DESC';
    $sql .= $db->plimit($limit);

    $resql = $db->query($sql);
    if (!$resql) {
        return $recordings;
    }

    while ($obj = $db->fetch_object($resql)) {
        $recordingDate = $db->jdate($obj->recording_date);

        $recordings[$obj->rowid] = dol_print_date($recordingDate, 'day') . ' - ' . ($obj->label ?: $obj->ref);
    }
    $db->free($resql);

    return $recordings;
}

/**
 * Format a duration in seconds the way the recordings list shows it.
 *
 * @param  int    $duration Duration in seconds.
 * @return string           Formatted duration, ex. 1:23:45 or 12:07.
 */
function reedcrm_pocket_format_duration(int $duration): string
{
    if ($duration <= 0) {
        return '';
    }

    $hours   = (int) floor($duration / 3600);
    $minutes = (int) floor(($duration % 3600) / 60);
    $seconds = $duration % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $seconds);
    }

    return sprintf('%d:%02d', $minutes, $seconds);
}

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

// One block of the Pocket syntax inside a summary, ex. <pocket:chart type="pie">...</pocket:chart>.
// The pattern captures the whole block so preg_split() hands the blocks back with the text.
define('REEDCRM_POCKET_BLOCK_PATTERN', '#(<pocket:[a-z0-9-]+\b[^>]*>.*?</pocket:[a-z0-9-]+>)#is');

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
 * Get the columns a table actually holds.
 *
 * The objects a recording may be linked to are spread over a dozen tables, each naming its business
 * date and its amount its own way. Rather than maintaining that mapping by hand, the columns are
 * read once per table and cached for the request.
 *
 * @param  string               $table Table name without the Dolibarr prefix, ex. 'facture'.
 * @return array<string,bool>          Column name in lower case => true.
 */
function reedcrm_pocket_get_table_columns(string $table): array
{
    global $conf, $db;

    if (!isset($conf->cache['reedcrmPocketTableColumns'][$table])) {
        $columns = [];

        $resql = $db->DDLDescTable(MAIN_DB_PREFIX . $table);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $column = strtolower((string) ($obj->Field ?? ''));
                if ($column !== '') {
                    $columns[$column] = true;
                }
            }
        }

        $conf->cache['reedcrmPocketTableColumns'][$table] = $columns;
    }

    return $conf->cache['reedcrmPocketTableColumns'][$table];
}

/**
 * Pick the first column a table holds among a list of candidates.
 *
 * @param  array<string,bool> $columns    Columns of the table.
 * @param  string[]           $candidates Candidate columns, most meaningful first.
 * @return string                         Matching column, empty string when the table holds none.
 */
function reedcrm_pocket_pick_column(array $columns, array $candidates): string
{
    foreach ($candidates as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }

    return '';
}

/**
 * Get the objects already linked to a recording, as a set of keys.
 *
 * A link is stored in the direction Dolibarr chose when it was created, both are read.
 *
 * @param  PocketRecording $recording Recording the links are read for.
 * @return array<string,bool>         'linkName:objectId' => true.
 */
function reedcrm_pocket_get_linked_object_keys(PocketRecording $recording): array
{
    global $db;

    $keys = [];
    if ($recording->id <= 0) {
        return $keys;
    }

    $sql  = 'SELECT sourcetype, fk_source, targettype, fk_target FROM ' . MAIN_DB_PREFIX . 'element_element';
    $sql .= " WHERE (sourcetype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND fk_source = " . ((int) $recording->id) . ')';
    $sql .= " OR (targettype = '" . $db->escape(REEDCRM_POCKET_LINK_ELEMENT_TYPE) . "' AND fk_target = " . ((int) $recording->id) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        return $keys;
    }

    while ($obj = $db->fetch_object($resql)) {
        if ($obj->sourcetype == REEDCRM_POCKET_LINK_ELEMENT_TYPE) {
            $keys[$obj->targettype . ':' . $obj->fk_target] = true;
        } else {
            $keys[$obj->sourcetype . ':' . $obj->fk_source] = true;
        }
    }
    $db->free($resql);

    return $keys;
}

/**
 * Search the objects a recording may still be attached to.
 *
 * The conversation was usually held with a thirdparty, so with no search term the objects of that
 * thirdparty come first: they are what the recording talks about nine times out of ten. A term
 * searches every object of the type instead, whoever it belongs to, because a recording sometimes
 * talks about the ticket of somebody else.
 *
 * Only the object types enabled in the module configuration are searched. The tables are read
 * directly: each one names its reference, its date and its amount its own way, and those columns
 * are resolved from the table itself rather than from a mapping kept by hand.
 *
 * @param  PocketRecording $recording  Recording the objects are offered for.
 * @param  string          $objectType Object type to search, every enabled one when empty.
 * @param  string          $search     Search term, empty to list the objects of the thirdparty.
 * @param  int             $limit      Most objects read per type.
 * @return array<int,array{key:string,link_name:string,id:int,type_label:string,picto:string,ref:string,thirdparty:string,date:int,amount:float|null}> Objects, most recent first.
 */
function reedcrm_pocket_search_objects(PocketRecording $recording, string $objectType = '', string $search = '', int $limit = 50): array
{
    global $db, $langs;

    $objects         = [];
    $linkableObjects = reedcrm_pocket_get_linkable_objects();
    $enabledTypes    = reedcrm_pocket_get_enabled_linked_object_types();
    $alreadyLinked   = reedcrm_pocket_get_linked_object_keys($recording);
    $search          = trim($search);

    if ($objectType !== '') {
        if (!in_array($objectType, $enabledTypes, true)) {
            return $objects;
        }
        $enabledTypes = [$objectType];
    }

    foreach ($enabledTypes as $enabledType) {
        $objectMetadata = $linkableObjects[$enabledType] ?? [];
        $table          = $objectMetadata['table_element'] ?? '';
        $linkName       = $objectMetadata['link_name'] ?? '';

        if (empty($table) || empty($linkName)) {
            continue;
        }

        $columns = reedcrm_pocket_get_table_columns($table);

        // Business date first, creation date as a fallback: the user recognises an invoice by the
        // date it carries, not by the day the row was written
        $dateColumn   = reedcrm_pocket_pick_column($columns, ['datep', 'datef', 'date_commande', 'date_contrat', 'datei', 'date_expedition', 'date_reception', 'dateo', 'date_valid', 'datec', 'date_creation']);
        $amountColumn = reedcrm_pocket_pick_column($columns, ['total_ttc', 'total_ht', 'opp_amount']);

        // name_field holds one or several columns (ex. 'ref, title'), shown and searched together
        $nameColumns = [];
        foreach (explode(',', (string) ($objectMetadata['name_field'] ?? 'ref')) as $nameColumn) {
            $nameColumn = trim($nameColumn);
            if ($nameColumn !== '' && isset($columns[$nameColumn])) {
                $nameColumns[] = $nameColumn;
            }
        }
        if (empty($nameColumns)) {
            $nameColumns = isset($columns['ref']) ? ['ref'] : [];
        }
        if (empty($nameColumns)) {
            continue;
        }

        $hasThirdParty = isset($columns['fk_soc']);

        // With no term the list is the one of the thirdparty of the recording: a type that belongs
        // to nobody, a thirdparty itself for instance, would fill it with unrelated rows
        if ($search === '' && $recording->fk_soc > 0 && !$hasThirdParty) {
            continue;
        }

        $sql  = 'SELECT t.rowid, t.' . implode(', t.', $nameColumns);
        $sql .= $dateColumn !== '' ? ', t.' . $dateColumn . ' as object_date' : ', NULL as object_date';
        $sql .= $amountColumn !== '' ? ', t.' . $amountColumn . ' as object_amount' : ', NULL as object_amount';
        $sql .= $hasThirdParty ? ', s.nom as thirdparty_name' : ", '' as thirdparty_name";
        $sql .= ' FROM ' . MAIN_DB_PREFIX . $table . ' as t';
        if ($hasThirdParty) {
            $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = t.fk_soc';
        }
        $sql .= ' WHERE 1 = 1';
        if (isset($columns['entity'])) {
            $sql .= ' AND t.entity IN (' . getEntity($linkName) . ')';
        }
        if ($search !== '') {
            $sql .= natural_search(array_map(function (string $nameColumn) {
                return 't.' . $nameColumn;
            }, $nameColumns), $search);
        } elseif ($hasThirdParty && $recording->fk_soc > 0) {
            $sql .= ' AND t.fk_soc = ' . ((int) $recording->fk_soc);
        }
        $sql .= $dateColumn !== '' ? ' ORDER BY t.' . $dateColumn . ' DESC' : ' ORDER BY t.rowid DESC';
        $sql .= $db->plimit($limit);

        $resql = $db->query($sql);
        if (!$resql) {
            continue;
        }

        $typeLabel = !empty($objectMetadata['langs']) ? $langs->trans($objectMetadata['langs']) : $linkName;

        while ($obj = $db->fetch_object($resql)) {
            if (isset($alreadyLinked[$linkName . ':' . $obj->rowid])) {
                continue;
            }

            $names = [];
            foreach ($nameColumns as $nameColumn) {
                if (!empty($obj->$nameColumn)) {
                    $names[] = (string) $obj->$nameColumn;
                }
            }

            $objects[] = [
                'key'        => $linkName . ':' . $obj->rowid,
                'link_name'  => $linkName,
                'id'         => (int) $obj->rowid,
                'type_label' => $typeLabel,
                'picto'      => (string) ($objectMetadata['picto'] ?? ''),
                'ref'        => implode(' - ', $names),
                'thirdparty' => (string) ($obj->thirdparty_name ?? ''),
                'date'       => !empty($obj->object_date) ? (int) $db->jdate($obj->object_date) : 0,
                'amount'     => $obj->object_amount !== null ? (float) $obj->object_amount : null
            ];
        }
        $db->free($resql);
    }

    // The types are read one after the other, the user reads one list: the most recent objects
    // come first whatever their type
    usort($objects, function (array $first, array $second) {
        return $second['date'] <=> $first['date'];
    });

    return $objects;
}

/**
 * Build the line shown for an object offered as a link target.
 *
 * The thirdparty is part of the line: a search runs across every thirdparty, and two objects of the
 * same type are told apart by who they belong to before anything else.
 *
 * @param  array<string,mixed> $object Object as returned by reedcrm_pocket_search_objects().
 * @return string                      Ready to print label.
 */
function reedcrm_pocket_format_object_choice(array $object): string
{
    global $conf, $langs;

    $choice = $object['type_label'] . ' - ' . $object['ref'];

    if (!empty($object['thirdparty'])) {
        $choice .= ' - ' . $object['thirdparty'];
    }
    if (!empty($object['date'])) {
        $choice .= ' - ' . dol_print_date($object['date'], 'day');
    }
    if ($object['amount'] !== null) {
        $choice .= ' - ' . price($object['amount'], 0, $langs, 0, -1, -1, $conf->currency);
    }

    return $choice;
}

/**
 * Read the business date and the amount an already linked object carries.
 *
 * The instances come from fetchObjectLinked(), so the values are read from the loaded object and
 * not from the database again. Each Dolibarr class names them its own way.
 *
 * @param  CommonObject $object Linked object.
 * @return array{date:int,amount:float|null} Date as a timestamp, amount when the object carries one.
 */
function reedcrm_pocket_get_object_date_and_amount(CommonObject $object): array
{
    $date   = 0;
    $amount = null;

    foreach (['date', 'datep', 'datef', 'date_commande', 'date_contrat', 'datei', 'date_start', 'dateo', 'date_creation', 'datec'] as $property) {
        if (!empty($object->$property)) {
            $date = is_numeric($object->$property) ? (int) $object->$property : (int) dol_stringtotime($object->$property);
            break;
        }
    }

    foreach (['total_ttc', 'total_ht', 'opp_amount'] as $property) {
        if (isset($object->$property) && $object->$property !== '' && $object->$property !== null) {
            $amount = (float) $object->$property;
            break;
        }
    }

    return ['date' => $date, 'amount' => $amount];
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

/**
 * Clean a summary rewritten by a user, keeping the Pocket blocks it embeds.
 *
 * The summary is markdown carrying tags of the Pocket syntax (<pocket:chart>, <pocket:timeline>,
 * ...). No HTML sanitizer knows them: restricthtml drops the tags and keeps only the lines between
 * them, so every graph of a summary was lost as soon as the text around it was edited. The blocks
 * are set aside, the markdown between them goes through the sanitizer restricthtml uses, then the
 * blocks are stitched back where they were.
 *
 * Keeping a block out of the sanitizer costs nothing: none of it ever reaches the page as HTML.
 * reedcrm_pocket_render_block() escapes every label it prints and only lets through the two values
 * it validates itself, the type of the block and the colour of a bar.
 *
 * @param  string $summary Summary as the editor posted it, raw.
 * @return string          Summary ready to be stored.
 */
function reedcrm_pocket_sanitize_summary(string $summary): string
{
    if (trim($summary) === '') {
        return '';
    }

    $parts = preg_split(REEDCRM_POCKET_BLOCK_PATTERN, $summary, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        // The blocks could not be told apart, so nothing may be spared from the sanitizer
        return trim(htmlspecialchars_decode(dol_htmlwithnojs($summary, 1, 1), ENT_QUOTES));
    }

    $cleaned = '';
    foreach ($parts as $index => $part) {
        // preg_split() gives the captured blocks at the odd offsets, the text at the even ones
        if ($index % 2 === 1) {
            $cleaned .= $part;
            continue;
        }

        // dol_htmlwithnojs() is what GETPOST does for restricthtml. It encodes the ampersands and
        // the quotes of the markdown, and the summary is escaped again when it is printed: without
        // the decoding, an edit saved twice would store its own source
        $cleaned .= htmlspecialchars_decode(dol_htmlwithnojs($part, 1, 1), ENT_QUOTES);
    }

    return trim($cleaned);
}

/**
 * Render the markdown summary of a recording, Pocket blocks included.
 *
 * Pocket enriches its summary with its own tags (chart, flowchart, timeline, decision tree), which
 * no markdown parser knows. Parsedown runs in safe mode and escapes every tag it does not handle,
 * so those blocks used to be printed as raw source in the middle of the text: they are pulled out
 * of the markdown, rendered on their own, then stitched back at the place they came from.
 *
 * @param  string $summary Markdown summary as returned by Pocket.
 * @return string          Ready to print HTML.
 */
function reedcrm_pocket_summary_to_html(string $summary): string
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/parsemd.lib.php';

    if (trim($summary) === '') {
        return '';
    }

    $parts = preg_split(REEDCRM_POCKET_BLOCK_PATTERN, $summary, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($parts)) {
        return dolMd2Html($summary);
    }

    $html = '';
    foreach ($parts as $part) {
        if (trim($part) === '') {
            continue;
        }

        if (preg_match('#^<pocket:([a-z0-9-]+)\b([^>]*)>(.*)</pocket:[a-z0-9-]+>$#is', $part, $match)) {
            $html .= reedcrm_pocket_render_block($match[1], $match[2], $match[3]);
        } else {
            $html .= dolMd2Html($part);
        }
    }

    return $html;
}

/**
 * Render one Pocket block into HTML.
 *
 * A block whose type is unknown, or whose lines do not follow the expected syntax, still carries
 * analysis: it falls back to its raw text rather than being dropped.
 *
 * @param  string $type          Block type, ex. 'chart'.
 * @param  string $rawAttributes Attributes of the opening tag, ex. ' type="pie" title="..."'.
 * @param  string $content       Content between the opening and the closing tag.
 * @return string                Ready to print HTML.
 */
function reedcrm_pocket_render_block(string $type, string $rawAttributes, string $content): string
{
    $attributes = [];
    if (preg_match_all('/([a-zA-Z0-9_-]+)\s*=\s*"([^"]*)"/', $rawAttributes, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = $match[2];
        }
    }

    $lines = [];
    foreach (preg_split('/\R/', $content) as $line) {
        $line = trim($line);
        if ($line !== '') {
            $lines[] = $line;
        }
    }

    switch (strtolower($type)) {
        case 'chart':
            $body = reedcrm_pocket_render_chart($lines, $attributes);
            break;
        case 'flowchart':
            $body = reedcrm_pocket_render_flowchart($lines);
            break;
        case 'timeline':
            $body = reedcrm_pocket_render_timeline($lines);
            break;
        case 'decision-tree':
        case 'decisiontree':
            $body = reedcrm_pocket_render_decision_tree($lines);
            break;
        default:
            $body = '';
    }

    if ($body === '') {
        $body = '<div class="reedcrm-pocket-block-raw">' . dol_escape_htmltag(implode("\n", $lines), 0, 1) . '</div>';
    }

    $html  = '<div class="reedcrm-pocket-block reedcrm-pocket-block-' . dol_escape_htmltag(strtolower($type)) . '">';
    if (!empty($attributes['title'])) {
        $html .= '<div class="reedcrm-pocket-block-title">' . dol_escape_htmltag($attributes['title']) . '</div>';
    }
    $html .= $body;
    $html .= '</div>';

    return $html;
}

/**
 * Render a Pocket chart as one bar per value.
 *
 * Lines read 'Label | value | #color', the colour being optional. The bar length and the colour are
 * the only data driven parts, they travel as CSS variables so the styling itself stays in the SCSS.
 *
 * The bar is drawn against the scale Pocket itself draws: a bar chart is read on an axis running to
 * the highest value, so a value of 100 out of a 0-100 axis fills the bar. Only a pie or a doughnut
 * splits a whole, and there alone the value is a share of the total.
 *
 * @param  string[]             $lines      Lines of the block.
 * @param  array<string,string> $attributes Attributes of the opening tag, ex. ['type' => 'pie'].
 * @return string                           HTML, empty string when the lines are not chart data.
 */
function reedcrm_pocket_render_chart(array $lines, array $attributes = []): string
{
    $entries = [];
    $total   = 0.0;
    $highest = 0.0;

    foreach ($lines as $line) {
        $cells = array_map('trim', explode('|', $line));
        if (count($cells) < 2 || !is_numeric(str_replace(',', '.', $cells[1]))) {
            return '';
        }

        $value = (float) str_replace(',', '.', $cells[1]);
        $color = (isset($cells[2]) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $cells[2])) ? $cells[2] : '';

        $entries[] = ['label' => $cells[0], 'value' => $value, 'color' => $color];
        $total    += $value;
        $highest   = max($highest, $value);
    }

    if (empty($entries) || $total <= 0) {
        return '';
    }

    $isShareChart = in_array(strtolower($attributes['type'] ?? ''), ['pie', 'doughnut', 'donut'], true);
    $scale        = $isShareChart ? $total : $highest;

    if ($scale <= 0) {
        return '';
    }

    // Pocket does not always send a colour, the fallback keeps the bars distinguishable
    $palette = ['#63acc9', '#f0a500', '#7cb342', '#c0392b', '#8e6fbe', '#26a69a'];

    $html = '<ul class="reedcrm-pocket-chart">';
    foreach ($entries as $index => $entry) {
        $color = $entry['color'] !== '' ? $entry['color'] : $palette[$index % count($palette)];
        $share = round(($entry['value'] / $scale) * 100);
        $value = rtrim(rtrim(number_format($entry['value'], 2, '.', ''), '0'), '.');

        $html .= '<li style="--pocket-share: ' . $share . '%; --pocket-color: ' . $color . ';">';
        $html .= '<span class="reedcrm-pocket-chart-label">' . dol_escape_htmltag($entry['label']) . '</span>';
        $html .= '<span class="reedcrm-pocket-chart-track"><span class="reedcrm-pocket-chart-bar"></span></span>';
        $html .= '<span class="reedcrm-pocket-chart-value">' . dol_escape_htmltag($value) . ($isShareChart ? ' (' . $share . '%)' : '') . '</span>';
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

/**
 * Render a Pocket flowchart as one row per transition.
 *
 * Each line is drawn as its own edge instead of being chained into a single path: the steps Pocket
 * writes are free text, so two lines meant to follow each other rarely spell their common step the
 * same way, and a chain built on that comparison would silently drop a step.
 *
 * @param  string[] $lines Lines of the block.
 * @return string          HTML, empty string when a line holds no transition.
 */
function reedcrm_pocket_render_flowchart(array $lines): string
{
    $edges = [];

    foreach ($lines as $line) {
        $nodes = preg_split('/\s*(?:->|=>)\s*/', $line);
        if (!is_array($nodes) || count($nodes) < 2) {
            return '';
        }

        $edges[] = array_values(array_filter(array_map('trim', $nodes), 'strlen'));
    }

    if (empty($edges)) {
        return '';
    }

    $html = '<ul class="reedcrm-pocket-flow">';
    foreach ($edges as $nodes) {
        $html .= '<li>';
        foreach ($nodes as $node) {
            $html .= '<span class="reedcrm-pocket-flow-node">' . dol_escape_htmltag($node) . '</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

/**
 * Render a Pocket timeline as an ordered list of steps.
 *
 * Lines read 'Step | detail', a line without separator being a step with no detail.
 *
 * @param  string[] $lines Lines of the block.
 * @return string          HTML, empty string when the block holds no line.
 */
function reedcrm_pocket_render_timeline(array $lines): string
{
    if (empty($lines)) {
        return '';
    }

    $html = '<ol class="reedcrm-pocket-timeline">';
    foreach ($lines as $line) {
        $cells  = array_map('trim', explode('|', $line, 2));
        $html  .= '<li>';
        $html  .= '<span class="reedcrm-pocket-timeline-step">' . dol_escape_htmltag($cells[0]) . '</span>';
        if (!empty($cells[1])) {
            $html .= '<span class="reedcrm-pocket-timeline-detail">' . dol_escape_htmltag($cells[1]) . '</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ol>';

    return $html;
}

/**
 * Render a Pocket decision tree as its list of nodes and their branches.
 *
 * A node opens with 'id::Label', a branch reads 'Answer => target_id' and any other line completes
 * the node it follows. Branch targets are resolved to the label of the node they point at, so the
 * reader never has to translate an identifier by themselves.
 *
 * @param  string[] $lines Lines of the block.
 * @return string          HTML, empty string when no node was declared.
 */
function reedcrm_pocket_render_decision_tree(array $lines): string
{
    $nodes     = [];
    $currentId = '';

    foreach ($lines as $line) {
        if (preg_match('/^([A-Za-z0-9_-]+)::\s*(.*)$/', $line, $match)) {
            $currentId         = $match[1];
            $nodes[$currentId] = ['label' => trim($match[2]), 'text' => [], 'branches' => []];
            continue;
        }

        if ($currentId === '') {
            return '';
        }

        $branch = ltrim($line, "-*\t ");
        if (preg_match('/^(.*?)\s*=>\s*([A-Za-z0-9_-]+)$/', $branch, $match)) {
            $nodes[$currentId]['branches'][] = ['answer' => trim($match[1]), 'target' => $match[2]];
            continue;
        }

        $nodes[$currentId]['text'][] = $line;
    }

    if (empty($nodes)) {
        return '';
    }

    $html = '<ul class="reedcrm-pocket-tree">';
    foreach ($nodes as $node) {
        $html .= '<li>';
        $html .= '<span class="reedcrm-pocket-tree-label">' . dol_escape_htmltag($node['label']) . '</span>';

        if (!empty($node['text'])) {
            $html .= '<span class="reedcrm-pocket-tree-text">' . dol_escape_htmltag(implode(' ', $node['text'])) . '</span>';
        }

        if (!empty($node['branches'])) {
            $html .= '<ul class="reedcrm-pocket-tree-branches">';
            foreach ($node['branches'] as $branch) {
                $target = isset($nodes[$branch['target']]) ? $nodes[$branch['target']]['label'] : $branch['target'];

                $html .= '<li>';
                $html .= '<span class="reedcrm-pocket-tree-answer">' . dol_escape_htmltag($branch['answer']) . '</span>';
                $html .= '<span class="reedcrm-pocket-tree-target">' . dol_escape_htmltag($target) . '</span>';
                $html .= '</li>';
            }
            $html .= '</ul>';
        }

        $html .= '</li>';
    }
    $html .= '</ul>';

    return $html;
}

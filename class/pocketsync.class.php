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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/pocketsync.class.php
 * \ingroup reedcrm
 * \brief   Import of the Pocket recordings into the local mirror.
 */

require_once __DIR__ . '/pocketapi.class.php';
require_once __DIR__ . '/pocketrecording.class.php';
require_once __DIR__ . '/pocketactionitem.class.php';

/**
 * Class PocketSync.
 *
 * Pulls the recordings of the configured Pocket folder and mirrors them into
 * llx_reedcrm_pocket_recording. Idempotent: a recording already imported is updated in place,
 * matched on its Pocket id, and the fields the user owns (status, note, links, thirdparty) are
 * never overwritten.
 */
class PocketSync
{
    /**
     * @var DoliDB Database handler.
     */
    public DoliDB $db;

    /**
     * @var PocketApi API client.
     */
    public PocketApi $api;

    /**
     * @var string Last error message.
     */
    public string $error = '';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db  = $db;
        $this->api = new PocketApi($db);
    }

    /**
     * Import the recordings of the configured folder.
     *
     * @param  User $user     User the created records are attributed to.
     * @param  int  $maxPages Safety bound on the number of API pages walked in one run.
     * @return array{created:int,updated:int,skipped:int,errors:int} Import report.
     */
    public function syncRecordings(User $user, int $maxPages = 20): array
    {
        global $langs;

        $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

        if (!$this->api->isConfigured()) {
            $this->error = $langs->trans('PocketApiKeyMissing');
            return $report;
        }

        // An empty folder means "not configured yet": importing the whole account instead would
        // flood the list, so the sync deliberately does nothing until a folder is chosen.
        $folderId = getDolGlobalString('REEDCRM_POCKET_FOLDER_ID');
        if (empty($folderId)) {
            $this->error = $langs->trans('PocketFolderMissing');
            return $report;
        }

        $folderLabel = getDolGlobalString('REEDCRM_POCKET_FOLDER_LABEL');
        $page        = 1;
        $hasMore     = false;

        // Read once: the loop below asks it for every recording of every page.
        $mirroredPocketIds = $this->getMirroredPocketIds();

        do {
            $response = $this->api->getRecordings($folderId, 100, $page);
            if ($response === null) {
                $this->error = $this->api->error;
                $report['errors']++;
                break;
            }

            $recordings = $response['data'] ?? [];
            foreach ($recordings as $recording) {
                // The folder filter is documented but currently not honoured by the API, which
                // answers the whole account instead of failing on it. The page is therefore
                // filtered again here, otherwise a silently unfiltered answer imports everything.
                // A recording already mirrored keeps being refreshed whatever folder it sits in:
                // the hang up button imports recordings before Pocket files them, and dropping them
                // here would freeze them without transcript nor summary.
                $recordingFolderId = (string) ($recording['folder_id'] ?? '');
                if ($recordingFolderId !== $folderId && empty($mirroredPocketIds[(string) ($recording['id'] ?? '')])) {
                    $report['skipped']++;
                    continue;
                }

                // Each recording is stamped with the folder it really sits in, which is not always
                // the configured one now that unfiled recordings can already be mirrored.
                $result = $this->importRecording($recording, $user, $recordingFolderId, $recordingFolderId === $folderId ? $folderLabel : '');
                if ($result < 0) {
                    $report['errors']++;
                } elseif ($result == 1) {
                    $report['created']++;
                } else {
                    $report['updated']++;
                }
            }

            $hasMore = !empty($response['pagination']['has_more']);
            $page++;
        } while ($hasMore && $page <= $maxPages);

        return $report;
    }

    /**
     * Get the most recent recording available in Pocket.
     *
     * Answers the "I just hung up" button: the conversation that just ended is the last thing the
     * dictaphone recorded. The folder filter of the API is not honoured (see syncRecordings), so the
     * pages are filtered here, and more loosely than the synchronisation does: a recording made
     * seconds ago is usually not filed in a folder yet, so an unfiled one is accepted too. A
     * recording sitting in another folder belongs to another scope and is never picked.
     *
     * @param  int $maxPages Safety bound on the number of API pages walked when nothing matches.
     * @return array<string,mixed>|null Recording as returned by the list endpoint, null when none.
     */
    public function findLastRecording(int $maxPages = 3): ?array
    {
        global $langs;

        $this->error = '';

        if (!$this->api->isConfigured()) {
            $this->error = $langs->trans('PocketApiKeyMissing');
            return null;
        }

        $folderId = getDolGlobalString('REEDCRM_POCKET_FOLDER_ID');
        $last     = null;
        $lastDate = 0;
        $page     = 1;

        do {
            $response = $this->api->getRecordings($folderId, 100, $page);
            if ($response === null) {
                $this->error = $this->api->error;
                return null;
            }

            foreach ($response['data'] ?? [] as $recording) {
                if (empty($recording['id']) || !$this->isRealRecording($recording)) {
                    continue;
                }

                $recordingFolder = (string) ($recording['folder_id'] ?? '');
                if (!empty($folderId) && !empty($recordingFolder) && $recordingFolder !== $folderId) {
                    continue;
                }

                $recordingDate = $this->getRecordingTimestamp($recording);
                if ($last === null || $recordingDate > $lastDate) {
                    $last     = $recording;
                    $lastDate = $recordingDate;
                }
            }

            // The API answers newest first: once a page brought a candidate, the following pages can
            // only hold older ones. They are walked only while nothing matched at all.
            $hasMore = !empty($response['pagination']['has_more']);
            $page++;
        } while ($last === null && $hasMore && $page <= $maxPages);

        return $last;
    }

    /**
     * Import the most recent Pocket recording into the local mirror.
     *
     * @param  User $user User the created record is attributed to.
     * @return PocketRecording|null Recording as stored in Dolibarr, null on error, $this->error is then set.
     */
    public function importLastRecording(User $user): ?PocketRecording
    {
        global $langs;

        $recording = $this->findLastRecording();
        if ($recording === null) {
            if (empty($this->error)) {
                $this->error = $langs->trans('PocketHangupNoRecording');
            }

            return null;
        }

        // The recording keeps the folder it actually sits in: stamping a still unfiled recording
        // with the configured folder would claim a filing Pocket has not done.
        $folderId    = (string) ($recording['folder_id'] ?? '');
        $folderLabel = ($folderId !== '' && $folderId === getDolGlobalString('REEDCRM_POCKET_FOLDER_ID'))
            ? getDolGlobalString('REEDCRM_POCKET_FOLDER_LABEL')
            : '';

        if ($this->importRecording($recording, $user, $folderId, $folderLabel) < 0) {
            $this->error = $langs->trans('PocketHangupImportFailed');
            return null;
        }

        $importedRecording = new PocketRecording($this->db);
        if ($importedRecording->fetchByPocketId((string) $recording['id']) <= 0) {
            $this->error = $langs->trans('PocketHangupImportFailed');
            return null;
        }

        return $importedRecording;
    }

    /**
     * Get the Pocket identifiers already mirrored in the entity.
     *
     * @return array<string,bool> Pocket id => true.
     */
    private function getMirroredPocketIds(): array
    {
        $pocketIds = [];

        $sql = 'SELECT pocket_id FROM ' . MAIN_DB_PREFIX . 'reedcrm_pocket_recording WHERE entity IN (' . getEntity('pocketrecording') . ')';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $pocketIds;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $pocketIds[(string) $obj->pocket_id] = true;
        }
        $this->db->free($resql);

        return $pocketIds;
    }

    /**
     * Tell a recording apart from the digests Pocket mixes into the same list.
     *
     * The list endpoint also answers the daily summaries the assistant writes on its own
     * (id 'daily-highlights-<account>-<date>', no folder, no duration, state pending). They are the
     * most recent entries of the account every evening, and attaching one to a card as the call
     * that just ended would be plain wrong. A real recording is identified by its UUID, the digests
     * never have one.
     *
     * @param  array<string,mixed> $recording Recording as returned by the list endpoint.
     * @return bool                           True when the entry is an actual recording.
     */
    private function isRealRecording(array $recording): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', (string) ($recording['id'] ?? ''));
    }

    /**
     * Read the date of a recording from the list payload.
     *
     * @param  array<string,mixed> $recording Recording as returned by the list endpoint.
     * @return int                            Timestamp, 0 when Pocket gave no usable date.
     */
    private function getRecordingTimestamp(array $recording): int
    {
        foreach (['recording_at', 'created_at', 'updated_at'] as $field) {
            if (!empty($recording[$field])) {
                return (int) dol_stringtotime((string) $recording[$field]);
            }
        }

        return 0;
    }

    /**
     * Import or refresh a single recording.
     *
     * @param  array<string,mixed> $recording   Recording as returned by the list endpoint.
     * @param  User                $user        User the record is attributed to.
     * @param  string              $folderId    Configured folder id.
     * @param  string              $folderLabel Configured folder label.
     * @return int                              1 created, 0 updated, < 0 on error.
     */
    public function importRecording(array $recording, User $user, string $folderId, string $folderLabel): int
    {
        if (empty($recording['id'])) {
            return -1;
        }

        $pocketRecording = new PocketRecording($this->db);
        $isNew           = $pocketRecording->fetchByPocketId((string) $recording['id']) <= 0;
        $previousSync    = $isNew ? 0 : (int) $pocketRecording->last_sync_date;

        if ($isNew) {
            $pocketRecording->pocket_id      = (string) $recording['id'];
            $pocketRecording->status         = PocketRecording::STATUS_NEW;
            $pocketRecording->recording_date = !empty($recording['recording_at']) ? dol_stringtotime($recording['recording_at']) : dol_now();
        }

        $pocketRecording->label               = dol_trunc((string) ($recording['title'] ?? ''), 255, 'right', 'UTF-8', 1);
        $pocketRecording->duration            = (int) ($recording['duration'] ?? 0);
        $pocketRecording->language            = (string) ($recording['language'] ?? '');
        $pocketRecording->pocket_state        = (string) ($recording['state'] ?? '');
        $pocketRecording->pocket_folder_id    = $folderId;
        $pocketRecording->pocket_folder_label = $folderLabel;
        $pocketRecording->pocket_tags         = $this->formatTags($recording['tags'] ?? []);
        $pocketRecording->last_sync_date      = dol_now();

        if (!$isNew && !empty($recording['recording_at'])) {
            $pocketRecording->recording_date = dol_stringtotime($recording['recording_at']);
        }

        // Transcript and AI outputs only live on the detail endpoint, and only once Pocket is done
        // processing: a pending recording is imported now and enriched on a later run. The detail
        // costs one API call per recording, so it is only fetched when it can bring something new:
        // a recording never enriched, or one Pocket touched since the last synchronisation.
        $remoteUpdate  = !empty($recording['updated_at']) ? (int) dol_stringtotime($recording['updated_at']) : 0;
        $needsDetail   = $isNew || empty($pocketRecording->transcript) || $previousSync <= 0 || $remoteUpdate > $previousSync;

        if (($recording['state'] ?? '') === 'completed' && $needsDetail) {
            $this->fillFromDetail($pocketRecording);
        }

        if ($isNew) {
            $result = $pocketRecording->create($user);
            if ($result <= 0) {
                return -1;
            }

            $this->syncActionItems($pocketRecording, $user);

            return 1;
        }

        $result = $pocketRecording->update($user);
        if ($result <= 0) {
            return -1;
        }

        $this->syncActionItems($pocketRecording, $user);

        return 0;
    }

    /**
     * Mirror the action items of a recording into their own rows.
     *
     * Keyed on the identifier Pocket gives each action, so a re-import refreshes the wording of an
     * action already known instead of duplicating it. The fields the user owns are never touched
     * here: the assigned Dolibarr user, the event created from the action, and the whole wording of
     * an action rewritten on the card, since undoing an edit is worse than showing a stale wording.
     *
     * @param  PocketRecording $pocketRecording Recording whose actions are mirrored.
     * @param  User            $user            User the created rows are attributed to.
     * @return void
     */
    public function syncActionItems(PocketRecording $pocketRecording, User $user): void
    {
        $actions = $pocketRecording->getActionItems();
        if (empty($actions) || $pocketRecording->id <= 0) {
            return;
        }

        foreach ($actions as $action) {
            // Pocket exposes two identifiers, the stable one across recordings and the local one
            $pocketActionId = (string) ($action['globalActionItemId'] ?? $action['id'] ?? '');
            if (empty($pocketActionId)) {
                continue;
            }

            $actionItem = new PocketActionItem($this->db);
            $isNewItem  = $actionItem->fetchByPocketActionId($pocketRecording->id, $pocketActionId) <= 0;

            if ($isNewItem) {
                $actionItem->fk_pocket_recording = $pocketRecording->id;
                $actionItem->pocket_action_id    = $pocketActionId;
                $actionItem->status              = PocketActionItem::STATUS_TODO;
            }

            if (empty($actionItem->user_edited)) {
                $actionItem->label       = dol_trunc((string) ($action['label'] ?? ''), 255, 'right', 'UTF-8', 1);
                $actionItem->description = (string) ($action['context'] ?? '');
                $actionItem->due_date    = !empty($action['dueDate']) ? dol_stringtotime($action['dueDate']) : null;
            }

            // The priority is not shown nor edited on the card, it stays what Pocket says it is
            $actionItem->priority        = (string) ($action['priority'] ?? '');
            $actionItem->pocket_assignee = dol_trunc((string) ($action['assignee'] ?? ''), 128, 'right', 'UTF-8', 1);
            $actionItem->pocket_status   = (string) ($action['status'] ?? '');

            // Pocket marking the action done closes the Dolibarr row too, the other way round is
            // left to the user: closing it here would fight the event they created from it
            if (!empty($action['isCompleted']) || ($action['status'] ?? '') === 'DONE') {
                $actionItem->status = PocketActionItem::STATUS_DONE;
            }

            if ($isNewItem) {
                $actionItem->create($user);
            } else {
                $actionItem->update($user);
            }
        }
    }

    /**
     * Fill the transcript, summary and action items from the recording detail endpoint.
     *
     * @param  PocketRecording $pocketRecording Recording being imported.
     * @return void
     */
    private function fillFromDetail(PocketRecording $pocketRecording): void
    {
        $detail = $this->api->getRecording($pocketRecording->pocket_id);
        if ($detail === null) {
            return;
        }

        $data = $detail['data'] ?? [];

        $pocketRecording->transcript = (string) ($data['transcript']['text'] ?? '');

        // Pocket keys the summarizations by their own id, the module only mirrors the completed one.
        foreach ($data['summarizations'] ?? [] as $summarization) {
            if (($summarization['processingStatus'] ?? '') !== 'completed') {
                continue;
            }

            // A summary rewritten on the card belongs to the user: Pocket only fills it back once
            // the user emptied it, which is how they ask for the generated text again
            if (empty($pocketRecording->summary_edited)) {
                $pocketRecording->summary = (string) ($summarization['v2']['summary']['markdown'] ?? '');
            }

            $actions = $summarization['v2']['actionItems']['actions'] ?? [];
            if (!empty($actions)) {
                $pocketRecording->action_items = json_encode($actions);
            }

            break;
        }
    }

    /**
     * Turn the Pocket tag objects into a readable comma separated list.
     *
     * @param  array<int,array<string,mixed>> $tags Tags of the recording.
     * @return string                               Comma separated tag names.
     */
    private function formatTags(array $tags): string
    {
        $names = [];
        foreach ($tags as $tag) {
            if (!empty($tag['name'])) {
                $names[] = (string) $tag['name'];
            }
        }

        return dol_trunc(implode(', ', $names), 255, 'right', 'UTF-8', 1);
    }
}

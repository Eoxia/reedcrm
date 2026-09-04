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
                if (($recording['folder_id'] ?? '') !== $folderId) {
                    $report['skipped']++;
                    continue;
                }

                $result = $this->importRecording($recording, $user, $folderId, $folderLabel);
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
     * action already known instead of duplicating it. The two fields the user owns, the assigned
     * Dolibarr user and the event created from the action, are never touched here.
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

            $actionItem->label           = dol_trunc((string) ($action['label'] ?? ''), 255, 'right', 'UTF-8', 1);
            $actionItem->description     = (string) ($action['context'] ?? '');
            $actionItem->priority        = (string) ($action['priority'] ?? '');
            $actionItem->pocket_assignee = dol_trunc((string) ($action['assignee'] ?? ''), 128, 'right', 'UTF-8', 1);
            $actionItem->pocket_status   = (string) ($action['status'] ?? '');
            $actionItem->due_date        = !empty($action['dueDate']) ? dol_stringtotime($action['dueDate']) : null;

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

            $pocketRecording->summary = (string) ($summarization['v2']['summary']['markdown'] ?? '');

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

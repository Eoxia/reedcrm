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

require_once __DIR__ . '/pocketaccount.class.php';
require_once __DIR__ . '/pocketapi.class.php';
require_once __DIR__ . '/pocketrecording.class.php';
require_once __DIR__ . '/pocketactionitem.class.php';

/**
 * Class PocketSync.
 *
 * Pulls the recordings of the folder of one Pocket account and mirrors them into
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
     * @var PocketAccount Account the recordings are read from.
     */
    public PocketAccount $account;

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
     * @param DoliDB        $db      Database handler.
     * @param PocketAccount $account Account the recordings are read from.
     */
    public function __construct(DoliDB $db, PocketAccount $account)
    {
        $this->db      = $db;
        $this->account = $account;
        $this->api     = $account->getApi();
    }

    /**
     * Import the recordings of every configured account, the shared one and the personal ones.
     *
     * An account in error does not stop the others: the dictaphone of one salesperson being out of
     * reach is no reason to leave the calls of the whole team without transcript.
     *
     * @param  DoliDB $db   Database handler.
     * @param  User   $user User the created records are attributed to.
     * @return array{report:array{created:int,updated:int,skipped:int,errors:int},errors:string[],accounts:int} Summed report, error of each failing account, and number of accounts walked.
     */
    public static function syncAllAccounts(DoliDB $db, User $user): array
    {
        $outcome = ['report' => ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0], 'errors' => [], 'accounts' => 0];

        foreach (PocketAccount::getAllConfigured($db) as $account) {
            $pocketSync = new self($db, $account);
            $report     = $pocketSync->syncRecordings($user);

            foreach ($report as $counter => $value) {
                $outcome['report'][$counter] += $value;
            }

            if (!empty($pocketSync->error)) {
                $outcome['errors'][] = $account->getName() . ' : ' . $pocketSync->error;
            }

            $outcome['accounts']++;
        }

        return $outcome;
    }

    /**
     * Import the recordings of the folder of the account.
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
        $folderId = $this->account->folderId;
        if (empty($folderId)) {
            $this->error = $langs->trans('PocketFolderMissing');
            return $report;
        }

        $folderLabel = $this->account->folderLabel;
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
                // A recording already mirrored keeps being refreshed whatever folder it sits in: one
                // moved out of the folder after its import, or attached unfiled by the hang up button
                // before it was restricted to the folder, would otherwise freeze without transcript.
                $recordingFolderId = (string) ($recording['folder_id'] ?? '');
                if ($recordingFolderId !== $folderId && empty($mirroredPocketIds[(string) ($recording['id'] ?? '')])) {
                    $report['skipped']++;
                    continue;
                }

                // Each recording is stamped with the folder it really sits in, which is not always
                // the configured one for those refreshed above.
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
     * Get the most recent recording of the folder of the account.
     *
     * Answers the "I just hung up" button: the conversation that just ended is the last thing the
     * dictaphone recorded. The folder filter of the API is not honoured (see syncRecordings), so the
     * pages are filtered here, as strictly as the synchronisation does. An unfiled recording is not
     * picked either: the folders of an account split the companies of a group, and until it is
     * filed nothing tells which one the call belongs to.
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

        $folderId = $this->account->folderId;
        if (empty($folderId)) {
            $this->error = $langs->trans('PocketFolderMissing');
            return null;
        }

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

                if ((string) ($recording['folder_id'] ?? '') !== $folderId) {
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
     * Get the recordings that reached the account during a period and are still in no folder.
     *
     * Answers the daily reminder to file the calls: until a call is filed, neither the "I just hung up"
     * button nor the synchronisation pick it up. The period is read on the date the recording reached
     * Pocket, not on the date of the call: the dictaphone uploads in batches, sometimes days after the
     * call, and a recording uploaded late would otherwise fall before every period and never be
     * reminded. The API answers the most recently uploaded first, the pages stop once one only holds
     * older recordings.
     *
     * @param  int $since    Start of the period, excluded.
     * @param  int $until    End of the period, included.
     * @param  int $maxPages Safety bound on the number of API pages walked.
     * @return array<int,array<string,mixed>>|null Recordings as returned by the list endpoint, most recent call first, null on error.
     */
    public function findUnfiledRecordings(int $since, int $until, int $maxPages = 5): ?array
    {
        global $langs;

        $this->error = '';

        if (!$this->api->isConfigured()) {
            $this->error = $langs->trans('PocketApiKeyMissing');
            return null;
        }

        $unfiled = [];
        $page    = 1;

        do {
            $response = $this->api->getRecordings('', 100, $page);
            if ($response === null) {
                $this->error = $this->api->error;
                return null;
            }

            $newestOfPage = 0;
            foreach ($response['data'] ?? [] as $recording) {
                $uploadDate   = $this->getUploadTimestamp($recording);
                $newestOfPage = max($newestOfPage, $uploadDate);

                if (!$this->isRealRecording($recording) || !empty($recording['folder_id'])) {
                    continue;
                }

                if ($uploadDate > $since && $uploadDate <= $until) {
                    $unfiled[] = $recording;
                }
            }

            $hasMore = !empty($response['pagination']['has_more']);
            $page++;
        } while ($hasMore && $newestOfPage > $since && $page <= $maxPages);

        usort($unfiled, function (array $a, array $b): int {
            return $this->getRecordingTimestamp($b) <=> $this->getRecordingTimestamp($a);
        });

        return $unfiled;
    }

    /**
     * Import the most recent recording of the folder into the local mirror.
     *
     * @param  User $user   User the created record is attributed to.
     * @param  int  $maxAge Age in seconds past which the last recording is refused, 0 to accept any.
     * @return PocketRecording|null Recording as stored in Dolibarr, null on error, $this->error is then set.
     */
    public function importLastRecording(User $user, int $maxAge = 0): ?PocketRecording
    {
        global $langs;

        $folderName = dol_escape_htmltag($this->account->folderLabel ?: $this->account->folderId);

        $recording = $this->findLastRecording();
        if ($recording === null) {
            if (empty($this->error)) {
                $this->error = $langs->trans('PocketHangupNoRecordingInFolder', $folderName);
            }

            return null;
        }

        // The call that just ended is not filed yet when the last recording of the folder is that
        // old: attaching it would put an earlier conversation on the card, so nothing is attached
        // and the user is told to file the call first.
        $recordingDate = $this->getRecordingTimestamp($recording);
        if ($maxAge > 0 && $recordingDate > 0 && (dol_now() - $recordingDate) > $maxAge) {
            $this->error = $langs->trans('PocketHangupNotFiled', $folderName, dol_print_date($recordingDate, 'dayhour'));
            return null;
        }

        if ($this->importRecording($recording, $user, $this->account->folderId, $this->account->folderLabel) < 0) {
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
     * Read the date a recording reached Pocket from the list payload.
     *
     * @param  array<string,mixed> $recording Recording as returned by the list endpoint.
     * @return int                            Timestamp, 0 when Pocket gave no usable date.
     */
    private function getUploadTimestamp(array $recording): int
    {
        foreach (['created_at', 'recording_at'] as $field) {
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

        // The owner is only set on creation: an account configured twice, as the shared one and as
        // someone's own, sees the same recordings, and the refresh must not hand them over
        if ($isNew) {
            $pocketRecording->pocket_id      = (string) $recording['id'];
            $pocketRecording->fk_user_pocket = $this->account->fk_user > 0 ? $this->account->fk_user : null;
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

            // An action dropped from the card stays dropped: Pocket keeps sending it, and rewriting
            // its wording or closing it on what Pocket says would put it back under the eyes
            if (!$isNewItem && (int) $actionItem->status === PocketActionItem::STATUS_DISMISSED) {
                continue;
            }

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

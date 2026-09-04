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
 * \file    class/pocketrecording.class.php
 * \ingroup reedcrm
 * \brief   CRUD class for a Pocket recording mirrored into Dolibarr.
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for PocketRecording.
 *
 * One record per Pocket recording imported from the API. The row is a local mirror: Pocket stays
 * the source of truth for the transcript and the AI outputs, Dolibarr owns the status, the note
 * and the links towards the business objects (thirdparty, project, ticket, invoice, ...).
 */
class PocketRecording extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'pocketrecording';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_pocket_recording';

    /**
     * @var int Multicompany managed by field entity.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Extrafields managed ? 1 = Yes.
     */
    public $isextrafieldmanaged = 1;

    /**
     * @var string Icon.
     */
    public string $picto = 'fontawesome_fa-microphone_fas_#63ACC9';

    public const STATUS_NEW       = 0;
    public const STATUS_PROCESSED = 1;
    public const STATUS_ARCHIVED  = 2;

    /**
     * @var array<string,array<string,mixed>> Fields.
     */
    public $fields = [
        'rowid'               => ['type' => 'integer',      'label' => 'TechnicalID',            'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'ref'                 => ['type' => 'varchar(128)', 'label' => 'Ref',                    'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1],
        'ref_ext'             => ['type' => 'varchar(128)', 'label' => 'RefExt',                 'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 0],
        'entity'              => ['type' => 'integer',      'label' => 'Entity',                 'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'       => ['type' => 'datetime',     'label' => 'DateCreation',           'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'tms'                 => ['type' => 'timestamp',    'label' => 'DateModification',       'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 0],
        'import_key'          => ['type' => 'varchar(14)',  'label' => 'ImportId',               'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0],
        'status'              => ['type' => 'smallint',     'label' => 'Status',                 'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 2, 'index' => 1, 'default' => 0, 'arrayofkeyval' => [0 => 'PocketRecordingStatusNew', 1 => 'PocketRecordingStatusProcessed', 2 => 'PocketRecordingStatusArchived']],
        'label'               => ['type' => 'varchar(255)', 'label' => 'Label',                  'enabled' => 1, 'position' => 80,  'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'csslist' => 'tdoverflowmax300'],
        'recording_date'      => ['type' => 'datetime',     'label' => 'PocketRecordingDate',    'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 1],
        'duration'            => ['type' => 'integer',      'label' => 'Duration',               'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1, 'default' => 0, 'css' => 'maxwidth50', 'noteditable' => 1],
        'pocket_folder_label' => ['type' => 'varchar(255)', 'label' => 'PocketFolder',           'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1, 'noteditable' => 1],
        'pocket_tags'         => ['type' => 'varchar(255)', 'label' => 'PocketTags',             'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1, 'noteditable' => 1, 'searchall' => 1],
        'language'            => ['type' => 'varchar(8)',   'label' => 'Language',               'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => -1, 'noteditable' => 1, 'css' => 'maxwidth50'],
        'fk_soc'              => ['type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'picto' => 'company', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1, 'index' => 1],
        'pocket_id'           => ['type' => 'varchar(64)',  'label' => 'PocketRecordingId',      'enabled' => 1, 'position' => 150, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'pocket_folder_id'    => ['type' => 'varchar(64)',  'label' => 'PocketFolderId',         'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
        'pocket_state'        => ['type' => 'varchar(32)',  'label' => 'PocketState',            'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
        'last_sync_date'      => ['type' => 'datetime',     'label' => 'PocketLastSyncDate',     'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => -2, 'noteditable' => 1],
        'summary'             => ['type' => 'html',         'label' => 'PocketSummary',          'enabled' => 1, 'position' => 190, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
        'transcript'          => ['type' => 'text',         'label' => 'PocketTranscript',       'enabled' => 1, 'position' => 200, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1, 'searchall' => 1],
        'action_items'        => ['type' => 'text',         'label' => 'PocketActionItems',      'enabled' => 1, 'position' => 210, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
        'note_public'         => ['type' => 'html',         'label' => 'NotePublic',             'enabled' => 1, 'position' => 220, 'notnull' => 0, 'visible' => 0],
        'note_private'        => ['type' => 'html',         'label' => 'NotePrivate',            'enabled' => 1, 'position' => 230, 'notnull' => 0, 'visible' => 0],
        'fk_user_creat'       => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 240, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif'       => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'picto' => 'user', 'enabled' => 1, 'position' => 250, 'notnull' => 0, 'visible' => 0, 'foreignkey' => 'user.rowid']
    ];

    /**
     * @var int ID.
     */
    public $id;

    /**
     * @var string Ref.
     */
    public $ref;

    /**
     * @var int Status.
     */
    public $status;

    /**
     * @var string|null Recording title, taken from Pocket.
     */
    public $label;

    /**
     * @var string Pocket recording UUID, natural key of the mirror.
     */
    public $pocket_id;

    /**
     * @var string|null Pocket folder (space) UUID the recording belongs to.
     */
    public $pocket_folder_id;

    /**
     * @var string|null Pocket folder label, denormalised so the list stays readable offline.
     */
    public $pocket_folder_label;

    /**
     * @var string|null Pocket processing state (completed, pending, ...).
     */
    public $pocket_state;

    /**
     * @var string|null Comma separated Pocket tag names.
     */
    public $pocket_tags;

    /**
     * @var string|null Recording language code.
     */
    public $language;

    /**
     * @var int Recording duration in seconds.
     */
    public $duration;

    /**
     * @var int|string|null Date the conversation was recorded.
     */
    public $recording_date;

    /**
     * @var int|string|null Date of the last successful synchronisation.
     */
    public $last_sync_date;

    /**
     * @var string|null Markdown summary generated by Pocket.
     */
    public $summary;

    /**
     * @var string|null Full transcript.
     */
    public $transcript;

    /**
     * @var string|null JSON encoded action items.
     */
    public $action_items;

    /**
     * @var int|null Thirdparty ID.
     */
    public $fk_soc;

    /**
     * @var int User author.
     */
    public $fk_user_creat;

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->element);
    }

    /**
     * Create object into database, assigning a readable reference.
     *
     * No numbering module here: the ref is derived from the Pocket data so a re-import always
     * lands on the same reference, and uniqueness is guaranteed by the pocket_id unique key.
     *
     * @param  User     $user      User creating.
     * @param  int<0,1> $noTrigger 0 = triggers, 1 = no trigger.
     * @return int                 <= 0 if KO, id if OK.
     */
    public function create(User $user, int $noTrigger = 0): int
    {
        if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
            $this->ref = $this->buildRef();
        }

        return parent::create($user, $noTrigger);
    }

    /**
     * Build the reference of the recording from its Pocket identity.
     *
     * @return string Reference, ex. PKT260904-5F33E0.
     */
    public function buildRef(): string
    {
        $timestamp = !empty($this->recording_date)
            ? (is_numeric($this->recording_date) ? (int) $this->recording_date : (int) dol_stringtotime($this->recording_date))
            : dol_now();

        return 'PKT' . dol_print_date($timestamp, '%y%m%d') . '-' . dol_strtoupper(substr((string) $this->pocket_id, 0, 6));
    }

    /**
     * Load a recording from its Pocket identifier.
     *
     * @param  string $pocketId Pocket recording UUID.
     * @return int              < 0 if KO, 0 if not found, > 0 if OK.
     */
    public function fetchByPocketId(string $pocketId): int
    {
        if (empty($pocketId)) {
            return 0;
        }

        return $this->fetch(0, null, " AND t.pocket_id = '" . $this->db->escape($pocketId) . "'");
    }

    /**
     * Get the action items Pocket extracted from the conversation.
     *
     * @return array<int,array<string,mixed>> Action items, empty when none was stored.
     */
    public function getActionItems(): array
    {
        if (empty($this->action_items)) {
            return [];
        }

        $decoded = json_decode($this->action_items, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Return the recording status label.
     *
     * @param  int    $status Status ID.
     * @param  int    $mode   Display mode.
     * @return string         Label.
     */
    public function LibStatut(int $status, int $mode = 0): string
    {
        if (empty($this->labelStatus)) {
            global $langs;
            $langs->loadLangs(['reedcrm@reedcrm']);
            $this->labelStatus[self::STATUS_NEW]       = $langs->transnoentities('PocketRecordingStatusNew');
            $this->labelStatus[self::STATUS_PROCESSED] = $langs->transnoentities('PocketRecordingStatusProcessed');
            $this->labelStatus[self::STATUS_ARCHIVED]  = $langs->transnoentities('PocketRecordingStatusArchived');
            $this->labelStatusShort                    = $this->labelStatus;
        }

        $statusType = 'status1';
        if ($status == self::STATUS_PROCESSED) {
            $statusType = 'status4';
        }
        if ($status == self::STATUS_ARCHIVED) {
            $statusType = 'status6';
        }

        return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
    }
}

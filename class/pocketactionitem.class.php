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
 * \file    class/pocketactionitem.class.php
 * \ingroup reedcrm
 * \brief   CRUD class for an action item extracted by Pocket from a recording.
 */

require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for PocketActionItem.
 *
 * One row per action item Pocket extracted from a recording. The row is stored instead of being
 * read from the recording JSON because the user owns two of its fields, the assigned Dolibarr user
 * and the created event: re-importing the recording refreshes the wording, never the assignment.
 */
class PocketActionItem extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'pocketactionitem';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_pocket_action_item';

    /**
     * @var int Multicompany managed by field entity.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Extrafields managed ? 0 = No.
     */
    public $isextrafieldmanaged = 0;

    /**
     * @var string Icon.
     */
    public string $picto = 'fontawesome_fa-tasks_fas_#63ACC9';

    public const STATUS_TODO = 0;
    public const STATUS_DONE = 1;

    /**
     * @var array<string,array<string,mixed>> Fields.
     */
    public $fields = [
        'rowid'               => ['type' => 'integer',      'label' => 'TechnicalID',        'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'entity'              => ['type' => 'integer',      'label' => 'Entity',             'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'       => ['type' => 'datetime',     'label' => 'DateCreation',       'enabled' => 1, 'position' => 20,  'notnull' => 1, 'visible' => 0],
        'tms'                 => ['type' => 'timestamp',    'label' => 'DateModification',   'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0],
        'import_key'          => ['type' => 'varchar(14)',  'label' => 'ImportId',           'enabled' => 1, 'position' => 40,  'notnull' => 0, 'visible' => 0],
        'status'              => ['type' => 'smallint',     'label' => 'Status',             'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 0, 'arrayofkeyval' => [0 => 'PocketActionItemTodo', 1 => 'PocketActionItemDone']],
        'fk_pocket_recording' => ['type' => 'integer',      'label' => 'PocketRecording',    'enabled' => 1, 'position' => 60,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'pocket_action_id'    => ['type' => 'varchar(128)', 'label' => 'PocketActionItemId', 'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 0, 'noteditable' => 1],
        'label'               => ['type' => 'varchar(255)', 'label' => 'Label',              'enabled' => 1, 'position' => 80,  'notnull' => 0, 'visible' => 1],
        'description'         => ['type' => 'text',         'label' => 'Description',        'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 0],
        'due_date'            => ['type' => 'datetime',     'label' => 'DateDeadline',       'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1],
        'priority'            => ['type' => 'varchar(16)',  'label' => 'Priority',           'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1],
        'pocket_assignee'     => ['type' => 'varchar(128)', 'label' => 'PocketAssignee',     'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1, 'noteditable' => 1],
        'pocket_status'       => ['type' => 'varchar(32)',  'label' => 'PocketState',        'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
        'fk_user_assign'      => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'AssignedUser', 'picto' => 'user', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1, 'index' => 1],
        'fk_actioncomm'       => ['type' => 'integer:ActionComm:comm/action/class/actioncomm.class.php', 'label' => 'Event', 'picto' => 'action', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 1],
        'fk_user_creat'       => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 160, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif'       => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'picto' => 'user', 'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => 0, 'foreignkey' => 'user.rowid']
    ];

    /**
     * @var int ID.
     */
    public $id;

    /**
     * @var int Status.
     */
    public $status;

    /**
     * @var int Parent recording ID.
     */
    public $fk_pocket_recording;

    /**
     * @var string Identifier of the action item on the Pocket side.
     */
    public $pocket_action_id;

    /**
     * @var string|null Action label.
     */
    public $label;

    /**
     * @var string|null Context sentence explaining the action.
     */
    public $description;

    /**
     * @var int|string|null Due date suggested by Pocket.
     */
    public $due_date;

    /**
     * @var string|null Priority (low, medium, high).
     */
    public $priority;

    /**
     * @var string|null Assignee as named by Pocket, free text.
     */
    public $pocket_assignee;

    /**
     * @var string|null Status on the Pocket side (TODO, DONE).
     */
    public $pocket_status;

    /**
     * @var int|null Dolibarr user the action is assigned to.
     */
    public $fk_user_assign;

    /**
     * @var int|null Agenda event created from the action.
     */
    public $fk_actioncomm;

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
     * Load an action item from its recording and its Pocket identifier.
     *
     * @param  int    $recordingId    Parent recording ID.
     * @param  string $pocketActionId Identifier on the Pocket side.
     * @return int                    < 0 if KO, 0 if not found, > 0 if OK.
     */
    public function fetchByPocketActionId(int $recordingId, string $pocketActionId): int
    {
        if ($recordingId <= 0 || empty($pocketActionId)) {
            return 0;
        }

        $moreWhere  = ' AND t.fk_pocket_recording = ' . $recordingId;
        $moreWhere .= " AND t.pocket_action_id = '" . $this->db->escape($pocketActionId) . "'";

        return $this->fetch(0, null, $moreWhere);
    }

    /**
     * Get every action item of a recording, oldest first.
     *
     * @param  int                    $recordingId Parent recording ID.
     * @return array<int,self>|int                 Action items, < 0 on error.
     */
    public function fetchAllByRecording(int $recordingId)
    {
        return $this->fetchAll('ASC', 't.rowid', 0, 0, ['customsql' => 't.fk_pocket_recording = ' . ((int) $recordingId)]);
    }
}

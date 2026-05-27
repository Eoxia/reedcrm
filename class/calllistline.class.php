<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * \file        class/calllistline.class.php
 * \ingroup     reedcrm
 * \brief       CRUD class for CallListLine
 */

require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for CallListLine
 */
class CallListLine extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'call_list_line';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_call_list_line';

    /**
     * @var int Does this object support multicompany module?
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Does object support extrafields?
     */
    public $isextrafieldmanaged = 0;

    /**
     * @var string Picto.
     */
    public string $picto = 'fontawesome_fa-phone_fas_#63ACC9';

    public const STATUS_TO_CALL   = 0;
    public const STATUS_CALLED    = 1;
    public const STATUS_NO_ANSWER = 2;
    public const STATUS_CALLBACK  = 3;

    /**
     * @var array Array with all fields and their property.
     */
    public $fields = [
        'rowid'         => ['type' => 'integer',      'label' => 'TechnicalID',    'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'entity'        => ['type' => 'integer',      'label' => 'Entity',         'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation' => ['type' => 'datetime',     'label' => 'DateCreation',   'enabled' => 1, 'position' => 20,  'notnull' => 1, 'visible' => 0],
        'tms'           => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 0],
        'fk_call_list'  => ['type' => 'integer',      'label' => 'CallList',       'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'element_type'  => ['type' => 'varchar(255)', 'label' => 'ElementType',    'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 1],
        'element_id'    => ['type' => 'integer',      'label' => 'ElementId',      'enabled' => 1, 'position' => 60,  'notnull' => 1, 'visible' => 0],
        'fk_contact'    => ['type' => 'integer:Contact:contact/class/contact.class.php', 'label' => 'Contact', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1],
        'status'        => ['type' => 'smallint',     'label' => 'Status',         'enabled' => 1, 'position' => 80,  'notnull' => 1, 'visible' => 1, 'default' => 0],
        'note'          => ['type' => 'text',         'label' => 'Note',           'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 1],
        'fk_user_creat' => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1],
        'fk_user_modif' => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
    ];

    /**
     * @var int fk_call_list
     */
    public $fk_call_list = 0;

    /**
     * @var string element_type
     */
    public $element_type = '';

    /**
     * @var int element_id
     */
    public $element_id = 0;

    /**
     * @var int fk_contact
     */
    public $fk_contact = 0;

    /**
     * @var string note
     */
    public $note = '';

    /**
     * @var int status
     */
    public $status = 0;

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
     * Return label of status.
     *
     * @param  int    $mode Display mode
     * @return string
     */
    public function getLibStatut(int $mode = 0): string
    {
        return $this->LibStatut($this->status, $mode);
    }

    /**
     * Return label of a given status.
     *
     * @param  int    $status Status value
     * @param  int    $mode   Display mode
     * @return string
     */
    public function LibStatut(int $status, int $mode = 0): string
    {
        if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
            global $langs;
            $this->labelStatus[self::STATUS_TO_CALL]   = $langs->transnoentities('CallListLineStatus0');
            $this->labelStatus[self::STATUS_CALLED]    = $langs->transnoentities('CallListLineStatus1');
            $this->labelStatus[self::STATUS_NO_ANSWER] = $langs->transnoentities('CallListLineStatus2');
            $this->labelStatus[self::STATUS_CALLBACK]  = $langs->transnoentities('CallListLineStatus3');

            $this->labelStatusShort[self::STATUS_TO_CALL]   = $langs->transnoentitiesnoconv('CallListLineStatusShort0');
            $this->labelStatusShort[self::STATUS_CALLED]    = $langs->transnoentitiesnoconv('CallListLineStatusShort1');
            $this->labelStatusShort[self::STATUS_NO_ANSWER] = $langs->transnoentitiesnoconv('CallListLineStatusShort2');
            $this->labelStatusShort[self::STATUS_CALLBACK]  = $langs->transnoentitiesnoconv('CallListLineStatusShort3');
        }

        $statusType = 'status1';
        if ($status == self::STATUS_CALLED) {
            $statusType = 'status4';
        }
        if ($status == self::STATUS_NO_ANSWER) {
            $statusType = 'status8';
        }
        if ($status == self::STATUS_CALLBACK) {
            $statusType = 'status3';
        }

        return dolGetStatus($this->labelStatus[$status] ?? '', $this->labelStatusShort[$status] ?? '', '', $statusType, $mode);
    }

    /**
     * Fetch all lines of a given call list.
     *
     * @param  int   $fkCallList ID of the parent CallList
     * @return array Array of CallListLine objects, or empty array
     */
    public function fetchAllByCallList(int $fkCallList): array
    {
        $sql  = 'SELECT rowid FROM ' . $this->db->prefix() . $this->table_element;
        $sql .= ' WHERE fk_call_list = ' . ((int) $fkCallList);
        $sql .= ' AND entity = ' . ((int) $this->db->entity);
        $sql .= ' ORDER BY rowid ASC';

        $lines = [];

        $resql = $this->db->query($sql);
        if (!$resql) {
            return [];
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $line = new self($this->db);
            $line->fetch($obj->rowid);
            $lines[] = $line;
        }

        return $lines;
    }
}

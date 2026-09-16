<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file        class/interventiondate.class.php
 * \ingroup     reedcrm
 * \brief       CRUD class for InterventionDate : one intervention date per unit of quantity
 *              of a service line (a qty of 2.5 asks for 3 dates).
 */

require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for InterventionDate
 */
class InterventionDate extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'intervention_date';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_intervention_date';

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
    public string $picto = 'fontawesome_fa-calendar-check_fas_#63ACC9';

    /**
     * Date not filled yet.
     */
    public const STATUS_TO_PLAN = 0;

    /**
     * Date filled, the agenda event is scheduled.
     */
    public const STATUS_PLANNED = 1;

    /**
     * Intervention carried out.
     */
    public const STATUS_DONE = 2;

    /**
     * @var array Array with all fields and their property.
     */
    public $fields = [
        'rowid'               => ['type' => 'integer',      'label' => 'TechnicalID',      'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'entity'              => ['type' => 'integer',      'label' => 'Entity',           'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'       => ['type' => 'datetime',     'label' => 'DateCreation',     'enabled' => 1, 'position' => 20,  'notnull' => 1, 'visible' => 0],
        'tms'                 => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0],
        'element_type'        => ['type' => 'varchar(64)',  'label' => 'ElementType',      'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'element_id'          => ['type' => 'integer',      'label' => 'ElementId',        'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'fk_element_line'     => ['type' => 'integer',      'label' => 'ElementLine',      'enabled' => 1, 'position' => 60,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'position'            => ['type' => 'integer',      'label' => 'Position',         'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 1, 'default' => 1],
        'date_intervention'   => ['type' => 'datetime',     'label' => 'InterventionDate', 'enabled' => 1, 'position' => 80,  'notnull' => 0, 'visible' => 1],
        'duration'            => ['type' => 'integer',      'label' => 'Duration',         'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 1],
        'fk_user_intervenant' => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'InterventionUser', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1],
        'fk_actioncomm'       => ['type' => 'integer:ActionComm:comm/action/class/actioncomm.class.php', 'label' => 'Event', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1],
        'location'            => ['type' => 'varchar(255)', 'label' => 'InterventionLocation', 'enabled' => 1, 'position' => 115, 'notnull' => 0, 'visible' => 1],
        'note'                => ['type' => 'text',         'label' => 'Note',             'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1],
        'status'              => ['type' => 'smallint',     'label' => 'Status',           'enabled' => 1, 'position' => 130, 'notnull' => 1, 'visible' => 1, 'default' => 0],
        'fk_user_creat'       => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1],
        'fk_user_modif'       => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
    ];

    /**
     * @var string element_type
     */
    public $element_type = 'propal';

    /**
     * @var int element_id
     */
    public $element_id = 0;

    /**
     * @var int fk_element_line
     */
    public $fk_element_line = 0;

    /**
     * @var int position
     */
    public $position = 1;

    /**
     * @var int|string date_intervention
     */
    public $date_intervention = '';

    /**
     * @var int duration in minutes
     */
    public $duration = 0;

    /**
     * @var int fk_user_intervenant
     */
    public $fk_user_intervenant = 0;

    /**
     * @var int fk_actioncomm
     */
    public $fk_actioncomm = 0;

    /**
     * @var string location
     */
    public $location = '';

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
     * How many intervention dates a quantity asks for : a qty of 2 asks for 2 dates, a qty of 2.5 asks for 3.
     *
     * @param  float $qty Quantity of the line
     * @return int        Number of dates expected, capped by REEDCRM_INTERVENTION_DATE_MAX_PER_LINE
     */
    public static function getExpectedCount(float $qty): int
    {
        if ($qty <= 0) {
            return 0;
        }

        // round() first so a 2.0000001 coming from a float column does not ask for a third date
        $expected = (int) ceil(round($qty, 6));

        return min($expected, getDolGlobalInt('REEDCRM_INTERVENTION_DATE_MAX_PER_LINE', 24));
    }

    /**
     * Load every intervention date of a document line, ordered by position.
     *
     * @param  string $elementType Type of the parent document ('propal')
     * @param  int    $lineId      ID of the document line
     * @return array              Array of InterventionDate keyed by position
     */
    public function fetchAllByLine(string $elementType, int $lineId): array
    {
        $records = $this->fetchAll('ASC', 'position', 0, 0, [
            'customsql' => "t.element_type = '" . $this->db->escape($elementType) . "' AND t.fk_element_line = " . $lineId
        ]);

        if (!is_array($records)) {
            return [];
        }

        $byPosition = [];
        foreach ($records as $record) {
            $byPosition[(int) $record->position] = $record;
        }

        return $byPosition;
    }

    /**
     * Count the dates already scheduled, per line, for a whole document. One query for every line of the document.
     *
     * @param  string $elementType Type of the parent document ('propal')
     * @param  int    $elementId   ID of the parent document
     * @return array              [line id => number of dates filled]
     */
    public function countPlannedByElement(string $elementType, int $elementId): array
    {
        $counts = [];

        $sql  = 'SELECT fk_element_line, COUNT(rowid) as nb FROM ' . $this->db->prefix() . $this->table_element;
        $sql .= " WHERE element_type = '" . $this->db->escape($elementType) . "'";
        $sql .= ' AND element_id = ' . (int) $elementId;
        $sql .= ' AND entity IN (' . getEntity($this->element) . ')';
        $sql .= ' AND date_intervention IS NOT NULL';
        $sql .= ' GROUP BY fk_element_line';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return $counts;
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $counts[(int) $obj->fk_element_line] = (int) $obj->nb;
        }

        return $counts;
    }

    /**
     * Create, update or delete the agenda event mirroring this intervention date.
     * The event is the one the calendar page and the Dolibarr agenda both display.
     *
     * @param  User         $user   User doing the change
     * @param  CommonObject $parent Parent document the line belongs to
     * @param  string       $label  Label of the service line
     * @return int                  < 0 on error, >= 0 on success
     */
    public function syncEvent(User $user, CommonObject $parent, string $label): int
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        if (!isModEnabled('agenda') || !getDolGlobalInt('REEDCRM_INTERVENTION_DATE_CREATE_EVENT', 1)) {
            return 0;
        }

        // No date left : the event has nothing to sit on any more
        if (empty($this->date_intervention)) {
            return $this->deleteEvent($user);
        }

        $langs->load('reedcrm@reedcrm');

        $actionComm = new ActionComm($this->db);
        $isUpdate   = false;
        if ($this->fk_actioncomm > 0 && $actionComm->fetch($this->fk_actioncomm) > 0) {
            $isUpdate = true;
        } else {
            $actionComm = new ActionComm($this->db);
        }

        $start    = is_numeric($this->date_intervention) ? (int) $this->date_intervention : (int) $this->db->jdate($this->date_intervention);
        $duration = $this->duration > 0 ? (int) $this->duration : getDolGlobalInt('REEDCRM_INTERVENTION_DATE_DEFAULT_DURATION', 60);

        $actionComm->type_code   = 'AC_REEDCRM_INTERVENTION';
        $actionComm->label       = dol_trunc($langs->transnoentities('InterventionEventLabel', $this->position, $label, $parent->ref), 128);
        $actionComm->datep       = $start;
        $actionComm->datef       = $start + ($duration * 60);
        $actionComm->percentage  = $this->status == self::STATUS_DONE ? 100 : 0;
        $actionComm->userownerid = $this->fk_user_intervenant > 0 ? (int) $this->fk_user_intervenant : (int) $user->id;
        $actionComm->location    = dol_trunc((string) $this->location, 128, 'right', 'UTF-8', 1);
        $actionComm->note_private = (string) $this->note;
        $actionComm->fk_element  = (int) $parent->id;
        $actionComm->elementtype = $parent->element;

        if (!empty($parent->socid)) {
            $actionComm->socid = (int) $parent->socid;
            // fk_soc is the column the event card reads, socid the one create() cleans
            $actionComm->fk_soc = (int) $parent->socid;
        }
        if (!empty($parent->fk_project)) {
            $actionComm->fk_project = (int) $parent->fk_project;
        }

        // The intervenant is the only assignee, an event kept in sync must not pile up the previous ones
        $actionComm->userassigned = [$actionComm->userownerid => ['id' => $actionComm->userownerid, 'mandatory' => 0, 'transparency' => 0]];

        if ($isUpdate) {
            $result = $actionComm->update($user);
        } else {
            $actionComm->fk_user_author = $user->id;
            $result                     = $actionComm->create($user);
        }

        if ($result <= 0) {
            $this->error  = $actionComm->error;
            $this->errors = $actionComm->errors;

            return -1;
        }

        if (!$isUpdate) {
            $this->fk_actioncomm = (int) $actionComm->id;
        }

        return 1;
    }

    /**
     * Delete the agenda event mirroring this intervention date, if any.
     *
     * @param  User $user User doing the change
     * @return int        < 0 on error, >= 0 on success
     */
    public function deleteEvent(User $user): int
    {
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        if ($this->fk_actioncomm <= 0) {
            return 0;
        }

        $actionComm = new ActionComm($this->db);
        if ($actionComm->fetch($this->fk_actioncomm) > 0) {
            $result = $actionComm->delete($user);
            if ($result < 0) {
                $this->error  = $actionComm->error;
                $this->errors = $actionComm->errors;

                return -1;
            }
        }

        $this->fk_actioncomm = 0;

        return 1;
    }

    /**
     * Delete the intervention date and the agenda event it owns.
     *
     * @param  User $user       User doing the change
     * @param  int  $noTrigger  1 = does not execute triggers, 0 = execute triggers
     * @param  bool $softDelete 1 = the object is deleted, 0 = the object is only flagged
     * @return int              < 0 on error, > 0 on success
     */
    public function delete(User $user, int $noTrigger = 0, bool $softDelete = false): int
    {
        $this->deleteEvent($user);

        return parent::delete($user, $noTrigger, $softDelete);
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
    public function LibStatut($status, $mode = 0): string
    {
        global $langs;

        $langs->load('reedcrm@reedcrm');

        $labels = [
            self::STATUS_TO_PLAN => $langs->transnoentities('InterventionToPlan'),
            self::STATUS_PLANNED => $langs->transnoentities('InterventionPlanned'),
            self::STATUS_DONE    => $langs->transnoentities('InterventionDone'),
        ];
        $pictos = [
            self::STATUS_TO_PLAN => 'status0',
            self::STATUS_PLANNED => 'status4',
            self::STATUS_DONE    => 'status6',
        ];

        $label = $labels[$status] ?? '';
        $picto = $pictos[$status] ?? 'status0';

        return dolGetStatus($label, $label, '', $picto, $mode);
    }
}

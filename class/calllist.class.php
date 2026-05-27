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
 * \file        class/calllist.class.php
 * \ingroup     reedcrm
 * \brief       CRUD class for CallList
 */

require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for CallList
 */
class CallList extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'call_list';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_call_list';

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

    public const STATUS_ACTIVE   = 1;

    /**
     * @var array Array with all fields and their property.
     */
    public $fields = [
        'rowid'          => ['type' => 'integer',      'label' => 'TechnicalID',   'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'ref'            => ['type' => 'varchar(128)', 'label' => 'Ref',           'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1],
        'ref_ext'        => ['type' => 'varchar(128)', 'label' => 'RefExt',        'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 0],
        'entity'         => ['type' => 'integer',      'label' => 'Entity',        'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'  => ['type' => 'datetime',     'label' => 'DateCreation',  'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'tms'            => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 0],
        'import_key'     => ['type' => 'varchar(14)',  'label' => 'ImportId',      'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0],
        'status'         => ['type' => 'smallint',     'label' => 'Status',        'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 0],
        'label'          => ['type' => 'varchar(255)', 'label' => 'Label',         'enabled' => 1, 'position' => 80,  'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'autofocusoncreate' => 1],
        'note_public'    => ['type' => 'html',         'label' => 'NotePublic',    'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 0],
        'note_private'   => ['type' => 'html',         'label' => 'NotePrivate',   'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 0],
        'fk_user_assign' => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'AssignedTo', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1],
        'date_start'     => ['type' => 'datetime',     'label' => 'DateStart',     'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1],
        'date_end'       => ['type' => 'datetime',     'label' => 'DateEnd',       'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1],
        'fk_user_creat'  => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1],
        'fk_user_modif'  => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
    ];

    /**
     * @var string label
     */
    public $label = '';

    /**
     * @var string note_public
     */
    public $note_public = '';

    /**
     * @var string note_private
     */
    public $note_private = '';

    /**
     * @var int fk_user_assign
     */
    public $fk_user_assign = 0;

    /**
     * @var int|string date_start
     */
    public $date_start;

    /**
     * @var int|string date_end
     */
    public $date_end;

    /**
     * @var string ref
     */
    public $ref = '';

    /**
     * @var string ref_ext
     */
    public $ref_ext = '';

    /**
     * @var int entity
     */
    public $entity = 1;

    /**
     * @var string date_creation
     */
    public $date_creation = '';

    /**
     * @var string tms
     */
    public $tms = '';

    /**
     * @var string import_key
     */
    public $import_key = '';

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
     * @param  int    $mode 0=long label, 1=short label, 2=Picto+short, 3=Picto, 4=Picto+long, 5=Short+Picto, 6=Long+Picto
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
            $this->labelStatus[parent::STATUS_DRAFT]    = $langs->transnoentities('CallListStatus0');
            $this->labelStatus[self::STATUS_ACTIVE]   = $langs->transnoentities('CallListStatus1');
            $this->labelStatus[self::STATUS_ARCHIVED] = $langs->transnoentities('CallListStatus2');

            $this->labelStatusShort[parent::STATUS_DRAFT]    = $langs->transnoentitiesnoconv('CallListStatusShort0');
            $this->labelStatusShort[self::STATUS_ACTIVE]   = $langs->transnoentitiesnoconv('CallListStatusShort1');
            $this->labelStatusShort[self::STATUS_ARCHIVED] = $langs->transnoentitiesnoconv('CallListStatusShort2');
        }

        $statusType = 'status0';
        if ($status == self::STATUS_ACTIVE) {
            $statusType = 'status4';
        }
        if ($status == self::STATUS_ARCHIVED) {
            $statusType = 'status6';
        }

        return dolGetStatus($this->labelStatus[$status] ?? '', $this->labelStatusShort[$status] ?? '', '', $statusType, $mode);
    }

    /**
     * Return URL of object.
     *
     * @param  int    $withpicto Add picto (1=picto, 2=label, 3=picto+label)
     * @param  string $option    ''=normal link
     * @param  int    $notooltip 1=Disable tooltip
     * @param  string $morecss   More CSS
     * @param  int    $save_lastsearch_value Save last search value (-1=autodetect)
     * @return string HTML string
     */
    public function getNomUrl(int $withpicto = 0, string $option = '', int $notooltip = 0, string $morecss = '', int $save_lastsearch_value = -1): string
    {
        global $langs;

        $result = '';
        $label  = img_picto('', $this->picto) . ' <u>' . $langs->trans('CallList') . '</u>';
        $label .= '<br><b>' . $langs->trans('Ref') . ':</b> ' . $this->ref;
        $label .= '<br><b>' . $langs->trans('Label') . ':</b> ' . dol_htmlentities($this->label);

        $url = dol_buildpath('/custom/reedcrm/view/call_list_card.php', 1) . '?id=' . $this->id;

        $linkclose = '';
        if (empty($notooltip)) {
            $linkclose .= ' title="' . dol_escape_htmltag($label, 1) . '"';
            $linkclose .= ' class="classfortooltip' . ($morecss ? ' ' . $morecss : '') . '"';
        } else {
            $linkclose .= ($morecss ? ' class="' . $morecss . '"' : '');
        }

        $linkstart = '<a href="' . $url . '"' . $linkclose . '>';
        $linkend   = '</a>';

        if ($withpicto) {
            $result .= $linkstart . img_object(($notooltip ? '' : $label), $this->picto, ($notooltip ? '' : 'class="classfortooltip"'), 0, 0, $notooltip ? 0 : 1) . $linkend;
            if ($withpicto != 2) {
                $result .= ' ';
            }
        }

        if ($withpicto != 2) {
            $result .= $linkstart . $this->ref . $linkend;
        }

        return $result;
    }
}

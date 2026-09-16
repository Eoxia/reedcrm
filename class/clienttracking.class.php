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
 * \file    class/clienttracking.class.php
 * \ingroup reedcrm
 * \brief   CRUD class for the follow-up of the other client engagements (support, training, sprint).
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for ClientTracking.
 *
 * Same board as the Document Unique audits, for everything else followed client by client. Unlike
 * the audits, nothing is seeded from invoices: every line is added by hand.
 */
class ClientTracking extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'clienttracking';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_client_tracking';

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
    public string $picto = 'fontawesome_fa-headset_fas_#63ACC9';

    public const STATUS_TODO = 0;
    public const STATUS_DONE = 2;

    /**
     * Engagement types followed on the board.
     */
    public const TYPES = ['assistance', 'formation', 'sprint', 'other'];

    /**
     * @var array<string,array<string,mixed>> Fields.
     */
    public $fields = [
        'rowid'                => ['type' => 'integer',      'label' => 'TechnicalID',      'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'ref'                  => ['type' => 'varchar(128)', 'label' => 'Ref',              'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1],
        'entity'               => ['type' => 'integer',      'label' => 'Entity',           'enabled' => 1, 'position' => 20,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'        => ['type' => 'datetime',     'label' => 'DateCreation',     'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0],
        'tms'                  => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'status'               => ['type' => 'smallint',     'label' => 'Status',           'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 2, 'index' => 1, 'default' => 0],
        'type'                 => ['type' => 'varchar(24)',  'label' => 'Type',             'enabled' => 1, 'position' => 60,  'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 'assistance'],
        'fk_soc'               => ['type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'picto' => 'company', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1, 'index' => 1],
        'date_planned'         => ['type' => 'date',         'label' => 'FollowupTrackingPlanned', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'index' => 1],
        'date_rdv'             => ['type' => 'date',         'label' => 'FollowupAuditRdv', 'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 1],
        'date_done'            => ['type' => 'date',         'label' => 'FollowupAuditRealDate', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1],
        'label'                => ['type' => 'text',         'label' => 'FollowupTrackingLabel', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1],
        'montant'              => ['type' => 'price',        'label' => 'FollowupAmountTTC', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1, 'isameasure' => 1],
        'fk_user_assign'       => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'FollowupAssignedTo', 'picto' => 'user', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1, 'foreignkey' => 'user.rowid'],
        'fk_propal'            => ['type' => 'integer:Propal:comm/propal/class/propal.class.php', 'label' => 'FollowupLinkedQuote', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1],
        'fk_facture'           => ['type' => 'integer:Facture:compta/facture/class/facture.class.php', 'label' => 'FollowupLinkedInvoice', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 1],
        'fk_intervention_date' => ['type' => 'integer',      'label' => 'FollowupLinkedIntervention', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 1],
        'fk_user_creat'        => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif'        => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0],
    ];

    /**
     * @var int ID.
     */
    public int $rowid;

    /**
     * @var string Ref.
     */
    public $ref;

    /**
     * @var int Status.
     */
    public $status;

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
     * @param  User     $user      User creating.
     * @param  int<0,1> $noTrigger 0 = triggers, 1 = no trigger.
     * @return int                 <= 0 if KO, id if OK.
     */
    public function create(User $user, int $noTrigger = 0): int
    {
        // The reference is only built once the row has an id: a client can very well have several
        // engagements in the same month (an assistance and a sprint), so it cannot key on the client.
        $this->ref = '(PROV)';
        $result    = parent::create($user, $noTrigger);

        if ($result > 0) {
            $month     = !empty($this->date_planned) ? dol_print_date(is_numeric($this->date_planned) ? (int) $this->date_planned : (int) dol_stringtotime($this->date_planned), '%Y%m') : dol_print_date(dol_now(), '%Y%m');
            $this->ref = 'SUI' . $month . '-' . ((int) $this->id);
            $sql       = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element . " SET ref = '" . $this->db->escape($this->ref) . "' WHERE rowid = " . ((int) $this->id);
            $this->db->query($sql);
        }

        return $result;
    }

    /**
     * Return the label of an engagement type.
     *
     * @param  string $type Type code.
     * @return string       Translated label.
     */
    public static function typeLabel(string $type): string
    {
        global $langs;

        $key = 'FollowupTrackingType' . ucfirst($type);
        $out = $langs->transnoentities($key);

        return $out === $key ? $type : $out;
    }
}

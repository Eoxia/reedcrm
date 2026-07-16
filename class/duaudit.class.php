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
 * \file    class/duaudit.class.php
 * \ingroup reedcrm
 * \brief   CRUD class for a Document Unique audit (yearly DU review to prepare per client).
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for DuAudit.
 *
 * One record per planned Document Unique audit. Seeded from invoiced DU audit services
 * (product ref "DU_AU%") but fully editable: the planned date and status are user-controlled.
 */
class DuAudit extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'duaudit';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_du_audit';

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
    public string $picto = 'fontawesome_fa-clipboard-check_fas_#63ACC9';

    public const STATUS_TODO     = 0;
    public const STATUS_PREPARED = 1;
    public const STATUS_DONE     = 2;

    /**
     * @var array<string,array<string,mixed>> Fields.
     */
    public $fields = [
        'rowid'             => ['type' => 'integer',      'label' => 'TechnicalID',   'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'ref'               => ['type' => 'varchar(128)', 'label' => 'Ref',           'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1],
        'ref_ext'           => ['type' => 'varchar(128)', 'label' => 'RefExt',        'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 0],
        'entity'            => ['type' => 'integer',      'label' => 'Entity',        'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'     => ['type' => 'datetime',     'label' => 'DateCreation',  'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'tms'               => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 0],
        'import_key'        => ['type' => 'varchar(14)',  'label' => 'ImportId',      'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0],
        'status'            => ['type' => 'smallint',     'label' => 'Status',        'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 2, 'index' => 1, 'default' => 0, 'arrayofkeyval' => [0 => 'FollowupAuditToPrepare', 1 => 'FollowupAuditPrepared', 2 => 'FollowupAuditDone']],
        'fk_soc'            => ['type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'picto' => 'company', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'index' => 1],
        'last_audit_date'   => ['type' => 'date',         'label' => 'FollowupLastDuAudit', 'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 1],
        'next_audit_date'   => ['type' => 'date',         'label' => 'FollowupNextAudit',   'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'index' => 1],
        'date_done'         => ['type' => 'date',         'label' => 'FollowupAuditRealDate', 'enabled' => 1, 'position' => 105, 'notnull' => 0, 'visible' => 1],
        'fk_facture_source' => ['type' => 'integer',      'label' => 'SourceInvoice', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 0],
        'source'            => ['type' => 'varchar(16)',  'label' => 'Source',        'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 0, 'default' => 'manual'],
        'proposal_sent_date' => ['type' => 'date',        'label' => 'FollowupProposalSent', 'enabled' => 1, 'position' => 125, 'notnull' => 0, 'visible' => 1],
        'fk_propal'         => ['type' => 'integer:Propal:comm/propal/class/propal.class.php', 'label' => 'FollowupLinkedQuote', 'enabled' => 1, 'position' => 126, 'notnull' => 0, 'visible' => 1],
        'fk_user_assign'    => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'FollowupAssignedTo', 'picto' => 'user', 'enabled' => 1, 'position' => 128, 'notnull' => 0, 'visible' => 1, 'foreignkey' => 'user.rowid'],
        'note'              => ['type' => 'text',         'label' => 'Note',          'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 1],
        'montant'           => ['type' => 'price',        'label' => 'FollowupAmountTTC', 'enabled' => 1, 'position' => 135, 'notnull' => 0, 'visible' => 1, 'isameasure' => 1],
        'fk_user_creat'     => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif'     => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0],
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
        if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
            $month     = !empty($this->next_audit_date) ? dol_print_date(is_numeric($this->next_audit_date) ? (int) $this->next_audit_date : (int) dol_stringtotime($this->next_audit_date), '%Y%m') : dol_print_date(dol_now(), '%Y%m');
            $this->ref = 'DUA' . $month . '-' . ((int) $this->fk_soc);
        }

        return parent::create($user, $noTrigger);
    }

    /**
     * Return the audit status label.
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
            $this->labelStatus[self::STATUS_TODO]          = $langs->transnoentities('FollowupAuditToPrepare');
            $this->labelStatus[self::STATUS_PREPARED]      = $langs->transnoentities('FollowupAuditPrepared');
            $this->labelStatus[self::STATUS_DONE]          = $langs->transnoentities('FollowupAuditDone');
            $this->labelStatusShort = $this->labelStatus;
        }

        $statusType = 'status1';
        if ($status == self::STATUS_PREPARED) {
            $statusType = 'status3';
        }
        if ($status == self::STATUS_DONE) {
            $statusType = 'status4';
        }

        return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
    }
}

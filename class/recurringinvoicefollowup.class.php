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
 * \file        class/recurringinvoicefollowup.class.php
 * \ingroup     reedcrm
 * \brief       CRUD class file for RecurringInvoiceFollowup (recurring invoice monthly follow-up)
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';

// Load Saturne libraries.
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for RecurringInvoiceFollowup.
 *
 * One record per client subscription and per month: tracks billing, payment, follow-up,
 * DigiRisk health, the annual "Document Unique" update cycle and the commercial status.
 */
class RecurringInvoiceFollowup extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'recurringinvoicefollowup';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_facturerec_followup';

    /**
     * @var int Does this object support multicompany module ? 1 = Test with field entity.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Does object support extrafields ? 0 = No, 1 = Yes.
     */
    public $isextrafieldmanaged = 0;

    /**
     * @var string Icon of the object (part after 'fontawesome_' or a picto name).
     */
    public string $picto = 'fontawesome_fa-arrows-rotate_fas_#63ACC9';

    public const STATUS_ARCHIVED = 0;
    public const STATUS_ACTIVE   = 1;

    /**
     * @var array<string,array<string,mixed>> Fields and their properties.
     */
    public $fields = [
        'rowid'                 => ['type' => 'integer',      'label' => 'TechnicalID',                 'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'comment' => 'Id'],
        'ref'                   => ['type' => 'varchar(128)', 'label' => 'Ref',                         'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1, 'comment' => 'Reference of object'],
        'ref_ext'               => ['type' => 'varchar(128)', 'label' => 'RefExt',                      'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 0],
        'entity'                => ['type' => 'integer',      'label' => 'Entity',                      'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'         => ['type' => 'datetime',     'label' => 'DateCreation',                'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'tms'                   => ['type' => 'timestamp',    'label' => 'DateModification',            'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 0],
        'import_key'            => ['type' => 'varchar(14)',  'label' => 'ImportId',                    'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0],
        'status'                => ['type' => 'smallint',     'label' => 'Status',                      'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 2, 'index' => 1, 'default' => 1, 'arrayofkeyval' => [0 => 'Archived', 1 => 'Active']],

        // Identity
        'fk_soc'                => ['type' => 'integer:Societe:societe/class/societe.class.php',                'label' => 'ThirdParty',                'picto' => 'company', 'enabled' => 1, 'position' => 80,  'notnull' => 1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth300 widthcentpercentminusxx', 'csslist' => 'tdoverflowmax150'],
        'fk_facture_rec'        => ['type' => 'integer:FactureRec:compta/facture/class/facture-rec.class.php', 'label' => 'RecurringInvoiceTemplate',  'picto' => 'bill',    'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => -1, 'index' => 1, 'css' => 'maxwidth300', 'csslist' => 'tdoverflowmax150'],
        'period'                => ['type' => 'date',         'label' => 'Period',                      'enabled' => 1, 'position' => 100, 'notnull' => 1, 'visible' => 1, 'index' => 1],
        'prestation'            => ['type' => 'varchar(32)',  'label' => 'FollowupSubscription',        'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1, 'arrayofkeyval' => ['tpe' => 'FollowupTierTpe', 'small_company' => 'FollowupTierSmall', 'company' => 'FollowupTierCompany', 'company_plus' => 'FollowupTierCompanyPlus', 'unlimited' => 'FollowupTierUnlimited']],

        // Billing & payment
        'montant_ttc'           => ['type' => 'price',        'label' => 'FollowupAmountTTC',           'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1,  'isameasure' => 1, 'css' => 'maxwidth100', 'csslist' => 'right'],
        'date_derniere_facture' => ['type' => 'date',         'label' => 'FollowupLastBillingDate',     'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => -1],
        'facture_creee'         => ['type' => 'boolean',      'label' => 'FollowupInvoiceCreated',      'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 1,  'default' => 0],
        'facture_envoyee'       => ['type' => 'boolean',      'label' => 'FollowupInvoiceSent',         'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => -1, 'default' => 0],
        'facture_payee'         => ['type' => 'boolean',      'label' => 'FollowupInvoicePaid',         'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 1,  'default' => 0],
        'paiement_ok'           => ['type' => 'boolean',      'label' => 'FollowupPaymentOk',           'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => -1, 'default' => 0],

        // Follow-up & contact
        'date_relance'          => ['type' => 'date',         'label' => 'FollowupRelanceDate',         'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => 1],
        'nb_relances'           => ['type' => 'integer',      'label' => 'FollowupRelanceCount',        'enabled' => 1, 'position' => 190, 'notnull' => 0, 'visible' => -1, 'default' => 0],
        'client_contacte'       => ['type' => 'boolean',      'label' => 'FollowupClientContacted',     'enabled' => 1, 'position' => 200, 'notnull' => 0, 'visible' => -1, 'default' => 0],
        'date_contact'          => ['type' => 'date',         'label' => 'FollowupContactDate',         'enabled' => 1, 'position' => 210, 'notnull' => 0, 'visible' => -1],
        'temps_sav'             => ['type' => 'duration',     'label' => 'FollowupSavTime',             'enabled' => 1, 'position' => 220, 'notnull' => 0, 'visible' => -1],

        // DigiRisk health
        'digirisk_existant'     => ['type' => 'boolean',      'label' => 'FollowupDigiriskExists',      'enabled' => 1, 'position' => 230, 'notnull' => 0, 'visible' => -1, 'default' => 0],
        'digirisk_ajour'        => ['type' => 'boolean',      'label' => 'FollowupDigiriskUpToDate',    'enabled' => 1, 'position' => 240, 'notnull' => 0, 'visible' => -1, 'default' => 0],
        'acces_ok'              => ['type' => 'boolean',      'label' => 'FollowupClientAccessOk',      'enabled' => 1, 'position' => 250, 'notnull' => 0, 'visible' => -1, 'default' => 0],
        'version_dolibarr'      => ['type' => 'varchar(32)',  'label' => 'FollowupDolibarrVersion',     'enabled' => 1, 'position' => 260, 'notnull' => 0, 'visible' => -1],
        'version_digirisk'      => ['type' => 'varchar(32)',  'label' => 'FollowupDigiriskVersion',     'enabled' => 1, 'position' => 270, 'notnull' => 0, 'visible' => -1],

        // Document Unique - annual cycle
        'date_maj_du'           => ['type' => 'date',         'label' => 'FollowupDuUpdateBilledDate',  'enabled' => 1, 'position' => 280, 'notnull' => 0, 'visible' => 1,  'help' => 'FollowupDuUpdateBilledDateHelp'],
        'next_maj_du'           => ['type' => 'date',         'label' => 'FollowupDuNextUpdate',        'enabled' => 1, 'position' => 290, 'notnull' => 0, 'visible' => 5,  'noteditable' => 1, 'help' => 'FollowupDuNextUpdateHelp'],
        'dernier_audit_du'      => ['type' => 'date',         'label' => 'FollowupLastDuAudit',         'enabled' => 1, 'position' => 300, 'notnull' => 0, 'visible' => -1],

        // Commercial
        'besoin'                => ['type' => 'checkbox',     'label' => 'FollowupNeed',                'enabled' => 1, 'position' => 310, 'notnull' => 0, 'visible' => 1,  'arrayofkeyval' => ['accompagnement' => 'Accompagnement', 'formation' => 'Formation', 'maj' => 'MAJ', 'autre' => 'Autre']],
        'proposition'           => ['type' => 'varchar(32)',  'label' => 'FollowupProposal',            'enabled' => 1, 'position' => 320, 'notnull' => 0, 'visible' => -1, 'arrayofkeyval' => ['aucune' => 'Aucune', 'envoyee' => 'Envoyée', 'acceptee' => 'Acceptée', 'refusee' => 'Refusée']],
        'reaction'              => ['type' => 'text',         'label' => 'FollowupClientReaction',      'enabled' => 1, 'position' => 330, 'notnull' => 0, 'visible' => -1],
        'montant_pr'            => ['type' => 'price',        'label' => 'FollowupSalesAmount',         'enabled' => 1, 'position' => 340, 'notnull' => 0, 'visible' => -1, 'isameasure' => 1],
        'commentaire'           => ['type' => 'text',         'label' => 'Comment',                     'enabled' => 1, 'position' => 350, 'notnull' => 0, 'visible' => -1],

        'fk_user_creat'         => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif'         => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0, 'foreignkey' => 'user.rowid'],
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
     * @var int|null Recurring invoice template ID.
     */
    public $fk_facture_rec;

    /**
     * @var int|string|null Reference date of the last billed DU update.
     */
    public $date_maj_du;

    /**
     * @var int|string|null Computed date of the next DU update (date_maj_du + 1 year).
     */
    public $next_maj_du;

    /**
     * @var int User ID author.
     */
    public $fk_user_creat;

    /**
     * @var int|null User ID last modifier.
     */
    public $fk_user_modif;

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
     * Create object into database.
     *
     * @param  User     $user      User that creates.
     * @param  int<0,1> $noTrigger 0 = launch triggers after, 1 = disable triggers.
     * @return int                 Return integer <= 0 if KO, ID of created object if OK.
     */
    public function create(User $user, int $noTrigger = 0): int
    {
        $this->computeDuDates();

        // Assign the definitive reference at creation (no draft/validated lifecycle on this object).
        if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
            $nextRef = $this->getNextNumRef();
            if (!empty($nextRef)) {
                $this->ref = $nextRef;
            }
        }

        return parent::create($user, $noTrigger);
    }

    /**
     * Update object into database.
     *
     * @param  User     $user      User that modifies.
     * @param  int<0,1> $noTrigger 0 = launch triggers after, 1 = disable triggers.
     * @return int                 Return integer <= 0 if KO, > 0 if OK.
     */
    public function update(User $user, int $noTrigger = 0): int
    {
        $this->computeDuDates();

        return parent::update($user, $noTrigger);
    }

    /**
     * Recompute the next DU update date from the billed-update reference date (+ 1 year).
     *
     * @return void
     */
    protected function computeDuDates(): void
    {
        if (!empty($this->date_maj_du)) {
            $reference         = is_numeric($this->date_maj_du) ? (int) $this->date_maj_du : (int) dol_stringtotime($this->date_maj_du);
            $this->next_maj_du = dol_time_plus_duree($reference, 1, 'y');
        } else {
            $this->next_maj_du = null;
        }
    }

    /**
     * Compute the operational follow-up status from the billing booleans.
     * This is what tells "what to process": to bill, to send, awaiting payment, late/unpaid or paid.
     *
     * @return array{code:string,label:string,badge:string} Status code, translatable label key and Dolibarr badge type.
     */
    public function getFollowupStatus(): array
    {
        global $langs;
        $langs->loadLangs(['reedcrm@reedcrm']);

        if (!empty($this->facture_payee)) {
            return ['code' => 'paid', 'label' => $langs->transnoentities('FollowupStatusPaid'), 'badge' => 'status4'];
        }

        $relanceReached = !empty($this->date_relance) && (int) $this->date_relance <= dol_now();
        if (empty($this->paiement_ok) && ($relanceReached || (!empty($this->facture_envoyee)))) {
            if ($relanceReached) {
                return ['code' => 'late', 'label' => $langs->transnoentities('FollowupStatusLate'), 'badge' => 'status8'];
            }
        }

        if (empty($this->facture_creee)) {
            return ['code' => 'tobill', 'label' => $langs->transnoentities('FollowupStatusToBill'), 'badge' => 'status1'];
        }
        if (empty($this->facture_envoyee)) {
            return ['code' => 'tosend', 'label' => $langs->transnoentities('FollowupStatusToSend'), 'badge' => 'status3'];
        }

        return ['code' => 'awaiting', 'label' => $langs->transnoentities('FollowupStatusAwaiting'), 'badge' => 'status0'];
    }

    /**
     * Return the record status label (active / archived).
     *
     * @param  int    $status Status ID.
     * @param  int    $mode   0 = long, 1 = short, 2 = Picto + short, 3 = Picto, 4 = Picto + long, 5 = Short + Picto, 6 = Long + Picto.
     * @return string         Label of status.
     */
    public function LibStatut(int $status, int $mode = 0): string
    {
        if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
            global $langs;
            $this->labelStatus[self::STATUS_ARCHIVED]      = $langs->transnoentities('Archived');
            $this->labelStatus[self::STATUS_ACTIVE]        = $langs->transnoentities('Active');
            $this->labelStatusShort[self::STATUS_ARCHIVED] = $langs->transnoentitiesnoconv('Archived');
            $this->labelStatusShort[self::STATUS_ACTIVE]   = $langs->transnoentitiesnoconv('Active');
        }

        $statusType = $status == self::STATUS_ACTIVE ? 'status4' : 'status9';

        return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
    }
}

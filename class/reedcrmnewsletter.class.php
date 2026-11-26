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
 * \file        class/reedcrmnewsletter.class.php
 * \ingroup     reedcrm
 * \brief       CRUD class and sending engine of the newsletters and mail reminders sent by tags
 */

// dol_time_plus_duree() is not loaded by default, the cron runner would fatal on it
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

dol_include_once('/saturne/class/saturneobject.class.php');

require_once __DIR__ . '/../lib/reedcrm_newsletter.lib.php';

/**
 * Class for ReedcrmNewsletter
 *
 * A newsletter is mailed to the contacts, or to the projects, that carry one of its tags. A reminder is the
 * same mail sent again every period (frequency + unit) until it is put back to draft.
 *
 * Both leave at a date for every recipient, or after the creation: each recipient gets the mail a delay after its
 * contact (or project) was created, "30 days after a prospect comes in". Such a newsletter stays active, the cron
 * serves the recipients one by one as their date comes.
 */
class ReedcrmNewsletter extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'reedcrm';

    /**
     * @var string Element type of object.
     */
    public $element = 'newsletter';

    /**
     * @var string Name of table without prefix where object is stored.
     */
    public $table_element = 'reedcrm_newsletter';

    /**
     * @var int<0,1>|string Does this object support multicompany module?
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int<0,1> Does object support extrafields?
     */
    public $isextrafieldmanaged = 0;

    /**
     * @var string Picto.
     */
    public string $picto = 'fontawesome_fa-envelope-open-text_fas_#63ACC9';

    public const STATUS_SCHEDULED = 1;
    public const STATUS_SENT      = 2;

    public const TYPE_NEWSLETTER = 'newsletter';
    public const TYPE_REMINDER   = 'reminder';

    public const TARGET_CONTACT = 'contact';
    public const TARGET_PROJECT = 'project';

    public const MODE_HTML     = 'html';
    public const MODE_TEMPLATE = 'template';
    // Simple editor: a layout and blocks (title, text, image, button, divider), the HTML is made by the module
    public const MODE_BLOCKS   = 'blocks';

    public const TRIGGER_DATE     = 'date';
    public const TRIGGER_CREATION = 'creation';

    /**
     * Third parties targeted: '' all, customers or prospects only (client field of the third party: 1 customer,
     * 2 prospect, 3 both)
     */
    public const THIRDPARTY_CUSTOMER = 'customer';
    public const THIRDPARTY_PROSPECT = 'prospect';

    /**
     * Where a recipient stands on a newsletter sent after the creation, see getRecipientState()
     */
    public const RECIPIENT_SENT     = 'sent';
    public const RECIPIENT_ERROR    = 'error';
    public const RECIPIENT_PENDING  = 'pending';
    public const RECIPIENT_DUE      = 'due';
    public const RECIPIENT_UPCOMING = 'upcoming';
    public const RECIPIENT_SKIPPED  = 'skipped';
    public const RECIPIENT_NO_DATE  = 'nodate';

    /**
     * Units of the delays and frequencies, as dol_time_plus_duree() knows them
     */
    public const UNITS = ['d', 'w', 'm', 'y'];

    public const SEND_STATUS_ERROR   = -1;
    public const SEND_STATUS_PENDING = 0;
    public const SEND_STATUS_SENT    = 1;
    // An error put back in the queue by "Retry the errors": the line is sent again in place, its error kept until then
    public const SEND_STATUS_RETRY   = -2;

    /**
     * Type of the email templates written for the newsletters, see ActionsReedcrm::emailElementlist()
     */
    public const EMAIL_TEMPLATE_TYPE = 'reedcrm_newsletter';

    /**
     * Element type of the events of the sendings. dolGetElementUrl(), which links them back in the agenda tabs and on
     * the event card, finds the class from it: "x@module" loads module/class/x.class.php, class x. "newsletter@reedcrm"
     * would look for a newsletter.class.php that does not exist and show no link
     */
    public const AGENDA_ELEMENT_TYPE = 'reedcrmnewsletter@reedcrm';

    /**
     * Code of the event that reminds the author to prepare the draft of the next month, see addPrepareReminder()
     */
    public const AGENDA_PREPARE_CODE = 'AC_REEDCRM_NL_PREPARE';

    /**
     * Days between the reminder to prepare the next month and the planned sending
     */
    public const PREPARE_REMINDER_DAYS = 7;

    /**
     * Fields a clone and the next edition take from their source: the targeting, the content and the settings. Not
     * the date, the counters nor the log
     */
    public const COPIED_FIELDS = ['mail_type', 'target_type', 'target_tags', 'exclude_tags', 'thirdparty_type', 'fk_sales_rep', 'content_mode', 'fk_email_template', 'email_from', 'subject', 'body_html', 'body_blocks', 'send_trigger', 'send_delay', 'send_delay_unit', 'frequency', 'frequency_unit', 'auto_next', 'note_private'];

    /**
     * @var array<string,array{type:string,label:string,langfile?:string,enabled:int<0,2>|string,position:int,notnull?:int,visible:int<-6,6>|string,alwayseditable?:int<0,1>|string,noteditable?:int<0,1>,default?:string,index?:int,foreignkey?:string,searchall?:int<0,1>,isameasure?:int<0,1>,css?:string,cssview?:string,csslist?:string,help?:string,showoncombobox?:int<0,4>|string,disabled?:int<0,1>,arrayofkeyval?:array<int|string,string>,autofocusoncreate?:int<0,1>,comment?:string,copytoclipboard?:int<1,2>,validate?:int<0,1>,showonheader?:int<0,1>,searchmulti?:int<0,1>}> Array with all fields and their property, typed as in CommonObject
     */
    public $fields = [
        'rowid'             => ['type' => 'integer',      'label' => 'TechnicalID',            'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1],
        'ref'               => ['type' => 'varchar(128)', 'label' => 'Ref',                    'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1],
        'ref_ext'           => ['type' => 'varchar(128)', 'label' => 'RefExt',                 'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 0],
        'entity'            => ['type' => 'integer',      'label' => 'Entity',                 'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation'     => ['type' => 'datetime',     'label' => 'DateCreation',           'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'tms'               => ['type' => 'timestamp',    'label' => 'DateModification',       'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 0],
        'import_key'        => ['type' => 'varchar(14)',  'label' => 'ImportId',               'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0],
        'status'            => ['type' => 'smallint',     'label' => 'Status',                 'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 2, 'index' => 1, 'default' => '0', 'disableedit' => 1, 'arrayofkeyval' => [0 => 'NewsletterStatus0', 1 => 'NewsletterStatus1', 2 => 'NewsletterStatus2']],
        'label'             => ['type' => 'varchar(255)', 'label' => 'Label',                  'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'autofocusoncreate' => 1, 'disableedit' => 1, 'csslist' => 'minwidth200 tdoverflowmax400'],
        'mail_type'         => ['type' => 'varchar(16)',  'label' => 'NewsletterListMailType', 'enabled' => 1, 'position' => 80,  'notnull' => 1, 'visible' => 1, 'default' => 'newsletter', 'disableedit' => 1, 'arrayofkeyval' => ['newsletter' => 'NewsletterTypeNewsletter', 'reminder' => 'NewsletterTypeReminder']],
        'target_type'       => ['type' => 'varchar(16)',  'label' => 'NewsletterTargetType',   'enabled' => 1, 'position' => 90,  'notnull' => 1, 'visible' => 1, 'default' => 'contact', 'disableedit' => 1, 'arrayofkeyval' => ['contact' => 'NewsletterTargetContact', 'project' => 'NewsletterTargetProject']],
        'target_tags'       => ['type' => 'varchar(255)', 'label' => 'NewsletterTargetTags',   'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 0],
        'exclude_tags'      => ['type' => 'varchar(255)', 'label' => 'NewsletterExcludeTags',  'enabled' => 1, 'position' => 101, 'notnull' => 0, 'visible' => 0],
        'thirdparty_type'   => ['type' => 'varchar(16)',  'label' => 'NewsletterThirdpartyType', 'enabled' => 1, 'position' => 102, 'notnull' => 0, 'visible' => 0, 'default' => ''],
        'fk_sales_rep'      => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'NewsletterSalesRep', 'enabled' => 1, 'position' => 103, 'notnull' => 0, 'visible' => 0],
        'content_mode'      => ['type' => 'varchar(16)',  'label' => 'NewsletterContentMode',  'enabled' => 1, 'position' => 110, 'notnull' => 1, 'visible' => -1, 'default' => 'html', 'arrayofkeyval' => ['blocks' => 'NewsletterContentModeBlocks', 'html' => 'NewsletterContentModeHtml', 'template' => 'NewsletterContentModeTemplate']],
        'fk_email_template' => ['type' => 'integer',      'label' => 'NewsletterEmailTemplate', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 0],
        'email_from'        => ['type' => 'varchar(255)', 'label' => 'MailFrom',               'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => -1],
        'subject'           => ['type' => 'varchar(255)', 'label' => 'MailTopic',              'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => -1, 'csslist' => 'tdoverflowmax200'],
        'body_html'         => ['type' => 'html',         'label' => 'NewsletterBodyHtml',     'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => 0],
        'body_blocks'       => ['type' => 'text',         'label' => 'NewsletterBodyBlocks',   'enabled' => 1, 'position' => 152, 'notnull' => 0, 'visible' => 0],
        'send_trigger'      => ['type' => 'varchar(16)',  'label' => 'NewsletterListTrigger',  'enabled' => 1, 'position' => 155, 'notnull' => 1, 'visible' => 1, 'default' => 'date', 'disableedit' => 1, 'arrayofkeyval' => ['date' => 'NewsletterTriggerDate', 'creation' => 'NewsletterTriggerCreation']],
        'date_send'         => ['type' => 'datetime',     'label' => 'NewsletterListDateSend', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => 1, 'disableedit' => 1],
        'send_delay'        => ['type' => 'integer',      'label' => 'NewsletterDelay',        'enabled' => 1, 'position' => 165, 'notnull' => 1, 'visible' => 0, 'default' => '30'],
        'send_delay_unit'   => ['type' => 'varchar(1)',   'label' => 'NewsletterDelayUnit',    'enabled' => 1, 'position' => 166, 'notnull' => 1, 'visible' => 0, 'default' => 'd'],
        'frequency'         => ['type' => 'integer',      'label' => 'NewsletterFrequency',    'enabled' => 1, 'position' => 170, 'notnull' => 1, 'visible' => 0, 'default' => '0'],
        'frequency_unit'    => ['type' => 'varchar(1)',   'label' => 'NewsletterFrequencyUnit', 'enabled' => 1, 'position' => 175, 'notnull' => 1, 'visible' => 0, 'default' => 'm'],
        'auto_next'         => ['type' => 'integer',      'label' => 'NewsletterAutoNext',     'enabled' => 1, 'position' => 176, 'notnull' => 1, 'visible' => 0, 'default' => '0'],
        'fk_previous'       => ['type' => 'integer',      'label' => 'NewsletterPrevious',     'enabled' => 1, 'position' => 177, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
        'date_last_send'    => ['type' => 'datetime',     'label' => 'NewsletterDateLastSend', 'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => 2, 'noteditable' => 1, 'disableedit' => 1],
        'nb_sent'           => ['type' => 'integer',      'label' => 'NewsletterListNbSent',   'enabled' => 1, 'position' => 185, 'notnull' => 1, 'visible' => 2, 'noteditable' => 1, 'default' => '0', 'disableedit' => 1, 'csslist' => 'right'],
        'nb_errors'         => ['type' => 'integer',      'label' => 'NewsletterNbErrors',     'enabled' => 1, 'position' => 190, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'default' => '0', 'disableedit' => 1],
        'note_private'      => ['type' => 'html',         'label' => 'NotePrivate',            'enabled' => 1, 'position' => 300, 'notnull' => 0, 'visible' => 0],
        'fk_user_creat'     => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1],
        'fk_user_modif'     => ['type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif',  'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => 0, 'noteditable' => 1],
    ];

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
     * @var string import_key
     */
    public $import_key = '';

    /**
     * @var int status
     */
    public $status = 0;

    /**
     * @var string label
     */
    public $label = '';

    /**
     * @var string newsletter (sent once) or reminder (sent again every period)
     */
    public $mail_type = self::TYPE_NEWSLETTER;

    /**
     * @var string contact or project: the objects whose tags are read
     */
    public $target_type = self::TARGET_CONTACT;

    /**
     * @var string Ids of the tags, comma separated
     */
    public $target_tags = '';

    /**
     * @var string Ids of the tags whose contacts (or projects) are left out, comma separated
     */
    public $exclude_tags = '';

    /**
     * @var string '' all the third parties, customer or prospect only
     */
    public $thirdparty_type = '';

    /**
     * @var int|null Sales representative: only the contacts (or projects) of the third parties he follows
     */
    public $fk_sales_rep;

    /**
     * @var string html (subject and HTML typed on the newsletter) or template (an email template of Dolibarr)
     */
    public $content_mode = self::MODE_HTML;

    /**
     * @var int|null Id of the email template (llx_c_email_templates) in template mode
     */
    public $fk_email_template;

    /**
     * @var string Sender, empty = the sender of the mass emailings
     */
    public $email_from = '';

    /**
     * @var string Subject in HTML mode
     */
    public $subject = '';

    /**
     * @var string Body in HTML mode, a whole document or a fragment. In blocks mode, the HTML made from the blocks at
     *             the last save: switching to the HTML mode starts from it
     */
    public $body_html = '';

    /**
     * @var string Blocks mode: JSON {layout, color, logo, blocks}, see reedcrm_newsletter_clean_blocks()
     */
    public $body_blocks = '';

    /**
     * @var int|string Planned date of the next sending. After the creation: the date from which the recipients are
     *                 served, a recipient whose sending date falls before is not caught up
     */
    public $date_send;

    /**
     * @var string date (every recipient at the date of sending) or creation (each recipient a delay after the
     *             creation of its contact, or of its project)
     */
    public $send_trigger = self::TRIGGER_DATE;

    /**
     * @var int After the creation: number of units between the creation and the sending
     */
    public $send_delay = 30;

    /**
     * @var string After the creation: d (days), w (weeks), m (months) or y (years)
     */
    public $send_delay_unit = 'd';

    /**
     * @var int Reminder: number of units between two sendings, 0 = sent once
     */
    public $frequency = 0;

    /**
     * @var string Reminder: d (days), w (weeks), m (months) or y (years)
     */
    public $frequency_unit = 'm';

    /**
     * @var int 1 = once sent, the draft of the next month is prepared on its own and its author reminded of it
     */
    public $auto_next = 0;

    /**
     * @var int|null Newsletter this one was prepared from by "Prepare next month"
     */
    public $fk_previous;

    /**
     * @var int|string Date of the end of the last sending
     */
    public $date_last_send;

    /**
     * @var int Mails sent, all sendings
     */
    public $nb_sent = 0;

    /**
     * @var int Mails in error, all sendings
     */
    public $nb_errors = 0;

    /**
     * @var string note_private
     */
    public $note_private = '';

    /**
     * @var int fk_user_creat
     */
    public $fk_user_creat;

    /**
     * @var int fk_user_modif
     */
    public $fk_user_modif;

    /**
     * @var array{unsubscribed: int, noemail: int} Targets left out by the last getRecipients(): unsubscribed addresses,
     *                                              contacts or projects without a valid email
     */
    public $excluded = ['unsubscribed' => 0, 'noemail' => 0];

    /**
     * @var string Subject of the last mail rendered by sendToRecipient(), stored on its agenda event
     */
    public $lastSubject = '';

    /**
     * @var array<int, int[]> Sales representatives by third party, see getSalesRepresentatives()
     */
    private array $salesRepresentatives = [];

    /**
     * @var array<string, array{0: string, 1: string}> Content of the last render, see getContentSource()
     */
    private array $contentSourceCache = [];

    /**
     * @var array<string, array<string, string>> Keys of Dolibarr by language, see renderMail()
     */
    private array $commonSubstitutions = [];

    /**
     * @var Translate|null Language of the mails, see getMailLangs()
     */
    private ?Translate $mailLangs = null;

    /**
     * @var callable|null Preview of the form only: address of an image of the library not public yet, see
     *                    reedcrm_newsletter_render_blocks()
     */
    public $previewLibrarySrc = null;

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->element);

        // Deleting only sets the status to STATUS_DELETED: the arrows of the banner skip those, as the list does
        $this->next_prev_filter = '(te.status:>=:' . self::STATUS_DRAFT . ')';
    }

    /**
     * Create object into database, with its definitive ref at once: a newsletter has no validation step
     *
     * @param  User $user      User that creates
     * @param  int  $noTrigger 0 = launch triggers after, 1 = disable triggers
     * @return int             Return integer 0 < if KO, ID of created object if OK
     */
    public function create(User $user, int $noTrigger = 0): int
    {
        if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
            $this->ref = $this->getNextNumRef();
        }
        $ref = $this->ref;

        $result = parent::create($user, $noTrigger);

        // createCommon() sets (PROVid) in memory after the insert even when the row kept its ref: the next update()
        // would write it in database
        if ($result > 0 && !empty($ref) && !preg_match('/^\(?PROV/i', $ref)) {
            $this->ref = $ref;
        }

        return $result;
    }

    /**
     * Load an object from its id and create a new one in database, as a draft never sent
     *
     * @param  User $user     User making the clone
     * @param  int  $fromId   Id of the newsletter to clone
     * @return int            New id if OK, < 0 if KO
     */
    public function createFromClone(User $user, int $fromId): int
    {
        global $langs;

        $source = new self($this->db);
        if ($source->fetch($fromId) <= 0) {
            $this->error = $source->error;
            return -1;
        }

        foreach (self::COPIED_FIELDS as $field) {
            $this->$field = $source->$field;
        }
        $this->label  = $langs->transnoentities('CopyOf') . ' ' . $source->label;
        $this->ref    = '';
        $this->status = self::STATUS_DRAFT;

        return $this->create($user);
    }

    /**
     * Whether the next month can be prepared from this newsletter: a newsletter sent at a date. A reminder already
     * leaves again on its own, a newsletter after the creation never ends
     *
     * @return bool
     */
    public function canPrepareNext(): bool
    {
        return !$this->isDeleted() && $this->mail_type === self::TYPE_NEWSLETTER && !$this->isAfterCreation();
    }

    /**
     * Edition prepared from this newsletter. A deleted edition is walked through: the one prepared from it takes its
     * place, so that the series goes on and the next month is never prepared twice
     *
     * @return int Id, 0 when there is none
     */
    public function fetchNextEdition(): int
    {
        if ($this->id <= 0) {
            return 0;
        }

        $currentId = (int) $this->id;
        $walked    = [];
        for ($i = 0; $i < 240 && !isset($walked[$currentId]); $i++) {
            $walked[$currentId] = true;

            // The editions not deleted first
            $sql  = 'SELECT rowid, status FROM ' . MAIN_DB_PREFIX . $this->table_element;
            $sql .= ' WHERE fk_previous = ' . $currentId;
            $sql .= ' AND entity IN (' . getEntity($this->element) . ')';
            $sql .= ' ORDER BY CASE WHEN status >= ' . self::STATUS_DRAFT . ' THEN 0 ELSE 1 END, rowid DESC' . $this->db->plimit(1);

            $resql = $this->db->query($sql);
            $obj   = $resql ? $this->db->fetch_object($resql) : null;
            if (!$obj) {
                return 0;
            }
            if ((int) $obj->status >= self::STATUS_DRAFT) {
                return (int) $obj->rowid;
            }
            $currentId = (int) $obj->rowid;
        }

        return 0;
    }

    /**
     * Edition this newsletter was prepared from, the deleted ones walked through
     *
     * @return ReedcrmNewsletter|null
     */
    public function fetchPreviousEdition(): ?ReedcrmNewsletter
    {
        $previousId = (int) $this->fk_previous;
        $walked     = [(int) $this->id => true];
        while ($previousId > 0 && !isset($walked[$previousId]) && count($walked) < 240) {
            $walked[$previousId] = true;
            $previous = new self($this->db);
            if ($previous->fetch($previousId) <= 0) {
                return null;
            }
            if (!$previous->isDeleted()) {
                return $previous;
            }
            $previousId = (int) $previous->fk_previous;
        }

        return null;
    }

    /**
     * Day of the month of the series: the day of this edition, or the one of the editions before when a short month
     * cut it. A newsletter of the 31st sent on the 28th of February goes back to the 31st in March
     *
     * @return int
     */
    public function getEditionAnchorDay(): int
    {
        // Back through the editions sent on the last day of their month (maybe a longer day cut short), up to the first
        // one that is not: the longest day met is the one of the series
        $anchor  = 0;
        $edition = $this;
        for ($i = 0; $i < 24 && $edition !== null && !empty($edition->date_send); $i++) {
            $date    = dol_getdate((int) $edition->date_send);
            $anchor  = max($anchor, (int) $date['mday']);
            $lastDay = (int) date('t', mktime(12, 0, 0, $date['mon'], 1, $date['year']));
            if ((int) $date['mday'] < $lastDay || $anchor >= 31) {
                break;
            }
            $edition = $edition->fetchPreviousEdition();
        }

        return $anchor > 0 ? $anchor : (int) dol_getdate(dol_now())['mday'];
    }

    /**
     * Date of the next edition: the day of the series and the hour of this edition, one month after its planned date
     * (the last day of a shorter month), moved on by months while it is past. An edition sent before its date keeps
     * its planned date: the next one does not start from the hour of the click
     *
     * @return int
     */
    public function getNextEditionDate(): int
    {
        $now       = dol_now();
        $date      = dol_getdate((int) $this->date_send ?: $now);
        $anchorDay = $this->getEditionAnchorDay();
        $next      = 0;
        for ($months = 1; $months <= 240; $months++) {
            $month = $date['mon'] + $months;
            $year  = $date['year'] + intdiv($month - 1, 12);
            $month = ($month - 1) % 12 + 1;
            $day   = min($anchorDay, (int) date('t', mktime(12, 0, 0, $month, 1, $year)));
            $next  = (int) dol_mktime($date['hours'], $date['minutes'], 0, $month, $day, $year);
            if ($next > $now) {
                break;
            }
        }

        return $next;
    }

    /**
     * Language of the mails: the one of the author, else the one of the company. The scheduled job runs with the
     * language of the server ("auto" gives English on the command line): its mails, the events and the next month
     * would come out in English
     *
     * @param  Translate|null $fallback Language when none is set, null = the current one
     * @return Translate
     */
    public function getMailLangs(?Translate $fallback = null): Translate
    {
        global $conf, $langs, $mysoc;

        if ($this->mailLangs !== null) {
            return $this->mailLangs;
        }

        $isLang = function ($code): bool {
            return is_string($code) && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $code);
        };

        $code = '';
        if (!empty($this->fk_user_creat)) {
            $author = new User($this->db);
            if ($author->fetch((int) $this->fk_user_creat, '', '', 1) > 0) {
                $code = $isLang($author->lang) ? $author->lang : ($author->conf->MAIN_LANG_DEFAULT ?? '');
            }
        }
        if (!$isLang($code)) {
            $code = getDolGlobalString('MAIN_LANG_DEFAULT');
        }
        if (!$isLang($code) && !empty($mysoc->country_code)) {
            $code = (string) getLanguageCodeFromCountryCode($mysoc->country_code);
        }

        $current = $fallback ?: $langs;
        if (!$isLang($code) || $code === $current->defaultlang) {
            $current->loadLangs(['main', 'mails', 'dict', 'reedcrm@reedcrm']);
            return $this->mailLangs = $current;
        }

        $outputLangs = new Translate('', $conf);
        $outputLangs->setDefaultLang($code);
        $outputLangs->loadLangs(['main', 'mails', 'dict', 'reedcrm@reedcrm']);

        return $this->mailLangs = $outputLangs;
    }

    /**
     * Prepare the draft of the next month: a copy of this newsletter for the same day of the next month, its month
     * names moved in the label, the subject and the titles ("November" becomes "December"). Once only: the edition
     * already prepared is given back instead of a second one
     *
     * @param  User      $user        User preparing, author of the draft
     * @param  Translate $outputLangs Language of the month names
     * @param  bool      $created     Set to true when the draft is made by this call, false when it existed
     * @return int                    Id of the next edition, < 0 if KO
     */
    public function prepareNextEdition(User $user, Translate $outputLangs, bool &$created = false): int
    {
        $created = false;
        if ($this->id <= 0 || !$this->canPrepareNext()) {
            $this->error = 'NewsletterActionNotAllowed';
            return -1;
        }
        $existing = $this->fetchNextEdition();
        if ($existing > 0) {
            return $existing;
        }

        $next = new self($this->db);
        foreach (self::COPIED_FIELDS as $field) {
            $next->$field = $this->$field;
        }
        $from              = (int) $this->date_send ?: dol_now();
        $next->date_send   = $this->getNextEditionDate();
        $next->label       = reedcrm_newsletter_next_label((string) $this->label, $from, $next->date_send, $outputLangs);
        $next->subject     = reedcrm_newsletter_shift_month_text((string) $this->subject, $from, $next->date_send, $outputLangs);
        $next->fk_previous = $this->id;
        $next->ref         = '';
        $next->status      = self::STATUS_DRAFT;

        if (!empty($this->body_blocks)) {
            $blocks = reedcrm_newsletter_clean_blocks((string) $this->body_blocks);
            foreach ($blocks['blocks'] as $index => $block) {
                if ($block['type'] === 'title') {
                    $blocks['blocks'][$index]['text'] = reedcrm_newsletter_shift_month_text($block['text'], $from, $next->date_send, $outputLangs);
                }
            }
            $next->body_blocks = json_encode($blocks);
            if ($next->content_mode === self::MODE_BLOCKS) {
                $next->body_html = reedcrm_newsletter_render_blocks($blocks, $outputLangs);
            }
        }

        if ($next->create($user) <= 0) {
            $this->error  = $next->error;
            $this->errors = $next->errors;
            return -1;
        }
        $created = true;

        return (int) $next->id;
    }

    /**
     * Once sent with "every month" checked: the draft of the next month is prepared for the author, and an event of
     * the agenda reminds him a week before its date. Nothing when the next month was already prepared by hand
     *
     * @param  User      $user        User sending (the user of the scheduled job)
     * @param  Translate $outputLangs Language of the month names and of the event
     * @return void
     */
    protected function prepareNextOnItsOwn(User $user, Translate $outputLangs): void
    {
        if (empty($this->auto_next) || $this->fetchNextEdition() > 0) {
            return;
        }

        $author = new User($this->db);
        if (empty($this->fk_user_creat) || $author->fetch((int) $this->fk_user_creat) <= 0) {
            $author = $user;
        }

        $created = false;
        $nextId  = $this->prepareNextEdition($author, $outputLangs, $created);
        if ($nextId < 0) {
            dol_syslog(__METHOD__ . ' newsletter ' . $this->id . ' next month not prepared: ' . $this->error, LOG_WARNING);
            return;
        }
        if ($created) {
            $next = new self($this->db);
            if ($next->fetch($nextId) > 0) {
                $next->addPrepareReminder($author, $user, $outputLangs);
            }
        }
    }

    /**
     * Event "To do" of the author: prepare this draft, a week before its date (9 am), or now when the date is closer.
     * An email reminder goes with it when the reminders of the agenda are on
     *
     * @param  User      $author      Author of the draft, owner of the event
     * @param  User      $user        User creating
     * @param  Translate $outputLangs Language of the event
     * @return int                    Id of the event, 0 when the agenda is off, < 0 if KO
     */
    public function addPrepareReminder(User $author, User $user, Translate $outputLangs): int
    {
        if (!isModEnabled('agenda') || empty($this->date_send)) {
            return 0;
        }

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncommreminder.class.php';

        $now      = dol_now();
        $remindAt = dol_getdate((int) dol_time_plus_duree((int) $this->date_send, -self::PREPARE_REMINDER_DAYS, 'd'));
        $remindAt = (int) dol_mktime(9, 0, 0, $remindAt['mon'], $remindAt['mday'], $remindAt['year']);
        if ($remindAt <= $now || $remindAt >= (int) $this->date_send) {
            $remindAt = $now;
        }

        $actionComm               = new ActionComm($this->db);
        $actionComm->type_code    = 'AC_OTH';
        $actionComm->code         = self::AGENDA_PREPARE_CODE;
        $actionComm->label        = $outputLangs->transnoentities('NewsletterPrepareEventLabel', $this->label);
        $actionComm->note_private = $outputLangs->transnoentities('NewsletterPrepareEventNote', $this->ref, dol_print_date($this->date_send, 'dayhour', 'tzserver', $outputLangs));
        $actionComm->datep        = $remindAt;
        $actionComm->datef        = $remindAt + 1800;
        $actionComm->percentage   = 0;
        $actionComm->userownerid  = $author->id;
        $actionComm->userassigned = [$author->id => ['id' => $author->id, 'mandatory' => 0, 'answer_status' => 0, 'transparency' => 0]];
        $actionComm->fk_element   = $this->id;
        $actionComm->elementtype  = self::AGENDA_ELEMENT_TYPE;

        $eventId = $actionComm->create($user, 1);
        if ($eventId <= 0) {
            dol_syslog(__METHOD__ . ' ' . $actionComm->error, LOG_WARNING);
            return -1;
        }

        if (getDolGlobalString('AGENDA_REMINDER_EMAIL')) {
            $reminder                = new ActionCommReminder($this->db);
            $reminder->dateremind    = $remindAt;
            $reminder->typeremind    = 'email';
            $reminder->offsetunit    = 'i';
            $reminder->offsetvalue   = 0;
            $reminder->status        = ActionCommReminder::STATUS_TODO;
            $reminder->fk_actioncomm = $eventId;
            $reminder->fk_user       = $author->id;
            if ($reminder->create($user) <= 0) {
                dol_syslog(__METHOD__ . ' reminder ' . $reminder->error, LOG_WARNING);
            }
        }

        return $eventId;
    }

    /**
     * The draft is planned: its "prepare" events are done, their email reminders not sent anymore
     *
     * @return void
     */
    protected function closePrepareReminders(): void
    {
        if ($this->id <= 0) {
            return;
        }

        $where  = " WHERE code = '" . $this->db->escape(self::AGENDA_PREPARE_CODE) . "' AND fk_element = " . ((int) $this->id);
        $where .= " AND elementtype = '" . $this->db->escape(self::AGENDA_ELEMENT_TYPE) . "'";

        $this->db->query('DELETE FROM ' . MAIN_DB_PREFIX . 'actioncomm_reminder WHERE status = 0 AND fk_actioncomm IN (SELECT id FROM ' . MAIN_DB_PREFIX . 'actioncomm' . $where . ')');
        $this->db->query('UPDATE ' . MAIN_DB_PREFIX . 'actioncomm SET percent = 100' . $where . ' AND percent >= 0 AND percent < 100');
    }

    /**
     * Delete the newsletter (its status only, its card stays readable). The series goes on without it: the edition
     * prepared from it follows the one before, nothing points to a deleted edition. Its "prepare" events are closed
     *
     * @param  User $user       User deleting
     * @param  int  $noTrigger  0 = launch triggers after, 1 = disable triggers
     * @param  bool $softDelete Only the status (true) or the row
     * @return int              > 0 if OK, < 0 if KO
     */
    public function delete(User $user, int $noTrigger = 0, bool $softDelete = true): int
    {
        $result = parent::delete($user, $noTrigger, $softDelete);
        if ($result > 0 && $this->id > 0) {
            $sql  = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element;
            $sql .= ' SET fk_previous = ' . (!empty($this->fk_previous) ? (int) $this->fk_previous : 'NULL');
            $sql .= ' WHERE fk_previous = ' . ((int) $this->id) . ' AND rowid <> ' . ((int) $this->id);
            $this->db->query($sql);
            $this->closePrepareReminders();
        }

        return $result;
    }

    /**
     * Event of the agenda kept as a trace on the newsletter: who did what, when ("Every month" turned on). Done at once,
     * owned by the user
     *
     * @param  User   $user  User acting
     * @param  string $code  Code of the event
     * @param  string $label Label
     * @param  string $note  Note
     * @return int           Id of the event, 0 when the agenda is off, < 0 if KO
     */
    public function addTraceEvent(User $user, string $code, string $label, string $note = ''): int
    {
        if (!isModEnabled('agenda') || $this->id <= 0) {
            return 0;
        }

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        $actionComm               = new ActionComm($this->db);
        $actionComm->type_code    = 'AC_OTH_AUTO';
        $actionComm->code         = $code;
        $actionComm->label        = dol_trunc($label, 255, 'right', 'UTF-8', 1);
        $actionComm->note_private = $note;
        $actionComm->datep        = dol_now();
        $actionComm->datef        = $actionComm->datep;
        $actionComm->percentage   = -1;
        $actionComm->userownerid  = $user->id;
        $actionComm->fk_element   = $this->id;
        $actionComm->elementtype  = self::AGENDA_ELEMENT_TYPE;

        $eventId = $actionComm->create($user, 1);
        if ($eventId <= 0) {
            dol_syslog(__METHOD__ . ' ' . $actionComm->error, LOG_WARNING);
            return -1;
        }

        return $eventId;
    }

    /**
     * Return label of status.
     *
     * @param  int    $mode 0=long label, 1=short label, 2=Picto+short, 3=Picto, 4=Picto+long, 5=Short+Picto, 6=Long+Picto
     * @return string
     */
    public function getLibStatut(int $mode = 0): string
    {
        return $this->LibStatut((int) $this->status, $mode);
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
        global $langs;

        // Not cached: the list prints every row with the same object, and a newsletter sent after the creation is
        // "Active" rather than "Scheduled"
        $labelKey = 'NewsletterStatus' . $status;
        if ($status == self::STATUS_SCHEDULED && $this->isAfterCreation()) {
            $labelKey = 'NewsletterStatusActive';
        } elseif ($status == self::STATUS_DELETED) {
            $labelKey = 'NewsletterStatusDeleted';
        }
        $label = in_array($status, [self::STATUS_DELETED, self::STATUS_DRAFT, self::STATUS_SCHEDULED, self::STATUS_SENT], true) ? $langs->transnoentitiesnoconv($labelKey) : '';

        $statusType = 'status0';
        if ($status == self::STATUS_SCHEDULED) {
            $statusType = 'status1';
        } elseif ($status == self::STATUS_SENT) {
            $statusType = 'status4';
        } elseif ($status == self::STATUS_DELETED) {
            $statusType = 'status9';
        }

        return dolGetStatus($label, $label, '', $statusType, $mode);
    }

    /**
     * Return URL of object.
     *
     * @param  int    $withPicto           Add picto (1=picto, 2=picto only)
     * @param  string $option              Unused
     * @param  int    $noToolTip           1=Disable tooltip
     * @param  string $moreCSS             More CSS
     * @param  int    $saveLastSearchValue Unused
     * @param  int    $addLabel            1=Add the label after the ref
     * @return string                      HTML string
     */
    public function getNomUrl(int $withPicto = 0, string $option = '', int $noToolTip = 0, string $moreCSS = '', int $saveLastSearchValue = -1, int $addLabel = 0): string
    {
        global $langs;

        $result = '';
        $label  = img_picto('', $this->picto) . ' <u>' . $langs->trans('Newsletter') . '</u>';
        $label .= '<br><b>' . $langs->trans('Ref') . ':</b> ' . $this->ref;
        $label .= '<br><b>' . $langs->trans('Label') . ':</b> ' . dol_escape_htmltag($this->label);

        $url = dol_buildpath('/reedcrm/view/newsletter/newsletter_card.php', 1) . '?id=' . $this->id;

        $linkclose = '';
        if (empty($noToolTip)) {
            $linkclose .= ' title="' . dol_escape_htmltag($label, 1) . '"';
            $linkclose .= ' class="classfortooltip' . ($moreCSS ? ' ' . $moreCSS : '') . '"';
        } else {
            $linkclose .= ($moreCSS ? ' class="' . $moreCSS . '"' : '');
        }

        $linkstart = '<a href="' . $url . '"' . $linkclose . '>';
        $linkend   = '</a>';

        if ($withPicto) {
            $result .= $linkstart . img_object(($noToolTip ? '' : $label), $this->picto, ($noToolTip ? '' : 'class="classfortooltip"'), 0, 0, $noToolTip ? 0 : 1) . $linkend;
            if ($withPicto != 2) {
                $result .= ' ';
            }
        }

        if ($withPicto != 2) {
            $result .= $linkstart . $this->ref . ($addLabel && $this->label ? ' - ' . dol_escape_htmltag($this->label) : '') . $linkend;
        }

        return $result;
    }

    /**
     * Whether the content and the targeting can still be changed: not once the newsletter is sent. A planned one is
     * changed by a user who may send only, see canBeEditedBy()
     *
     * @return bool
     */
    public function isEditable(): bool
    {
        return in_array((int) $this->status, [self::STATUS_DRAFT, self::STATUS_SCHEDULED], true);
    }

    /**
     * Whether a user may change the newsletter as it is: a draft with the right to write, a planned (or active) one
     * with the right to send too. A planned newsletter leaves on its own: changing its date, its tags or its text is
     * sending it
     *
     * @param  User $user User
     * @return bool
     */
    public function canBeEditedBy(User $user): bool
    {
        if (!$this->isEditable() || !$user->hasRight('reedcrm', 'newsletter', 'write')) {
            return false;
        }

        return (int) $this->status === self::STATUS_DRAFT || $user->hasRight('reedcrm', 'newsletter', 'send');
    }

    /**
     * Whether the newsletter was deleted: its card only shows it, no action brings it back
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return (int) $this->status === self::STATUS_DELETED;
    }

    /**
     * Inline edit of Saturne refused: a newsletter is changed from its card only, which cleans the HTML, checks the
     * sender, the template and the state. The endpoint of the inline edit would otherwise write any field (status,
     * body, sender) with the write right alone
     *
     * @return bool
     */
    public function isModifiable(): bool
    {
        return false;
    }

    /**
     * Why the newsletter cannot be sent as it is: its template is gone, its subject or its body is empty
     *
     * @param  Translate $outputLangs Language of the message
     * @return string                 '' when it can be sent, else the reason
     */
    public function getContentError(Translate $outputLangs): string
    {
        if ($this->content_mode === self::MODE_TEMPLATE) {
            return $this->fetchEmailTemplate() === null ? $outputLangs->transnoentitiesnoconv('NewsletterTemplateNotFound') : '';
        }
        if ($this->content_mode === self::MODE_BLOCKS) {
            $hasContent = reedcrm_newsletter_blocks_have_content(reedcrm_newsletter_clean_blocks((string) $this->body_blocks));
            return trim((string) $this->subject) === '' || !$hasContent ? $outputLangs->transnoentitiesnoconv('NewsletterErrorEmptyContent') : '';
        }
        if (trim((string) $this->subject) === '' || !reedcrm_newsletter_has_content((string) $this->body_html)) {
            return $outputLangs->transnoentitiesnoconv('NewsletterErrorEmptyContent');
        }

        return '';
    }

    /**
     * Whether each recipient gets the mail a delay after the creation of its contact (or project)
     *
     * @return bool
     */
    public function isAfterCreation(): bool
    {
        return $this->send_trigger === self::TRIGGER_CREATION;
    }

    /**
     * Whether the mail is sent again every period: a reminder with a frequency
     *
     * @return bool
     */
    public function isRecurring(): bool
    {
        return $this->mail_type === self::TYPE_REMINDER && (int) $this->frequency > 0;
    }

    /**
     * Unit known by dol_time_plus_duree()
     *
     * @param  string $unit    Unit stored
     * @param  string $default Unit when the stored one is unknown
     * @return string          d, w, m or y
     */
    public static function getUnit(string $unit, string $default): string
    {
        return in_array($unit, self::UNITS, true) ? $unit : $default;
    }

    /**
     * Ids of the tags chosen on the newsletter
     *
     * @return int[]
     */
    public function getTagIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->target_tags)))));
    }

    /**
     * Store the ids of the tags chosen on the newsletter
     *
     * @param  array $tagIds Ids of the tags
     * @return void
     */
    public function setTagIds(array $tagIds): void
    {
        $this->target_tags = implode(',', array_values(array_unique(array_filter(array_map('intval', $tagIds)))));
    }

    /**
     * Ids of the tags left out: a contact (or a project) carrying one of them gets nothing, even with a tag chosen
     *
     * @return int[]
     */
    public function getExcludeTagIds(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->exclude_tags)))));
    }

    /**
     * Store the ids of the tags left out
     *
     * @param  array $tagIds Ids of the tags
     * @return void
     */
    public function setExcludeTagIds(array $tagIds): void
    {
        $this->exclude_tags = implode(',', array_values(array_unique(array_filter(array_map('intval', $tagIds)))));
    }

    /**
     * Whether the recipients are narrowed beyond the tags: tags left out, customers or prospects only, a sales
     * representative
     *
     * @return bool
     */
    public function hasTargetFilters(): bool
    {
        return !empty($this->getExcludeTagIds()) || in_array($this->thirdparty_type, [self::THIRDPARTY_CUSTOMER, self::THIRDPARTY_PROSPECT], true) || (int) $this->fk_sales_rep > 0;
    }

    /**
     * SQL conditions of the filters beyond the tags, on the target (contact sp or project p) and its third party
     *
     * @param  string $targetAlias  Alias of the target: sp (contacts) or p (projects)
     * @param  string $socIdColumn  Column of the third party of the target
     * @return string               " AND …" conditions, '' when there is none
     */
    protected function getTargetFiltersSql(string $targetAlias, string $socIdColumn): string
    {
        $sql = '';

        $excludeIds = $this->getTagIdsWithDescendants($this->getExcludeTagIds());
        if (!empty($excludeIds)) {
            if ($this->target_type === self::TARGET_PROJECT) {
                $sql .= ' AND ' . $targetAlias . '.rowid NOT IN (SELECT ex.fk_project FROM ' . MAIN_DB_PREFIX . 'categorie_project as ex WHERE ex.fk_categorie IN (' . $this->db->sanitize(implode(',', $excludeIds)) . '))';
            } else {
                $sql .= ' AND ' . $targetAlias . '.rowid NOT IN (SELECT ex.fk_socpeople FROM ' . MAIN_DB_PREFIX . 'categorie_contact as ex WHERE ex.fk_categorie IN (' . $this->db->sanitize(implode(',', $excludeIds)) . '))';
            }
        }

        // Customers: client 1 or 3; prospects: client 2 or 3. A target without third party is left out
        if ($this->thirdparty_type === self::THIRDPARTY_CUSTOMER) {
            $sql .= ' AND ' . $socIdColumn . ' IN (SELECT fs.rowid FROM ' . MAIN_DB_PREFIX . 'societe as fs WHERE fs.client IN (1, 3))';
        } elseif ($this->thirdparty_type === self::THIRDPARTY_PROSPECT) {
            $sql .= ' AND ' . $socIdColumn . ' IN (SELECT fs.rowid FROM ' . MAIN_DB_PREFIX . 'societe as fs WHERE fs.client IN (2, 3))';
        }

        if ((int) $this->fk_sales_rep > 0) {
            $sql .= ' AND ' . $socIdColumn . ' IN (SELECT sc.fk_soc FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux as sc WHERE sc.fk_user = ' . ((int) $this->fk_sales_rep) . ')';
        }

        return $sql;
    }

    /**
     * Type of tag read on the targets (Categorie::TYPE_CONTACT or Categorie::TYPE_PROJECT)
     *
     * @return string
     */
    public function getCategoryType(): string
    {
        return $this->target_type === self::TARGET_PROJECT ? Categorie::TYPE_PROJECT : Categorie::TYPE_CONTACT;
    }

    /**
     * Ids of the tags chosen and of all their sub-tags: a contact tagged "Customers / VIP" is in "Customers"
     *
     * @param  int[]|null $tagIds Tags to widen, null = the tags chosen on the newsletter
     * @return int[]
     */
    public function getTagIdsWithDescendants(?array $tagIds = null): array
    {
        $tagIds = $tagIds ?? $this->getTagIds();
        if (empty($tagIds)) {
            return [];
        }

        $category = new Categorie($this->db);
        $typeId   = $category->MAP_ID[$this->getCategoryType()] ?? -1;

        $children = [];
        $sql      = 'SELECT rowid, fk_parent FROM ' . MAIN_DB_PREFIX . 'categorie';
        $sql     .= ' WHERE type = ' . ((int) $typeId) . ' AND entity IN (' . getEntity('category') . ')';
        $resql    = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $children[(int) $obj->fk_parent][] = (int) $obj->rowid;
            }
            $this->db->free($resql);
        }

        $result = [];
        $queue  = $tagIds;
        while (!empty($queue)) {
            $tagId = array_shift($queue);
            if (isset($result[$tagId])) {
                continue;
            }
            $result[$tagId] = $tagId;
            foreach ($children[$tagId] ?? [] as $childId) {
                $queue[] = $childId;
            }
        }

        return array_values($result);
    }

    /**
     * Recipients of the newsletter: the contacts carrying one of its tags, or the contacts of the projects carrying
     * one of its tags. Unsubscribed addresses are left out, and an address gets one mail (a reminder on projects
     * one per project, as its text speaks of the project)
     *
     * @param  int   $limit 0 = all, else the number of recipients wanted
     * @return array        List of [email, firstname, lastname, civility, fk_contact, fk_soc, thirdparty_name, fk_project, project_ref, project_title, date_creation, dedupe_key]
     *                      date_creation: creation of the contact, or of the project for the projects
     */
    public function getRecipients(int $limit = 0): array
    {
        $this->excluded = ['unsubscribed' => 0, 'noemail' => 0];

        $tagIds = $this->getTagIdsWithDescendants();
        if (empty($tagIds)) {
            return [];
        }

        if ($this->target_type === self::TARGET_PROJECT) {
            $recipients = $this->fetchProjectRecipients($tagIds);
        } else {
            $recipients = $this->fetchContactRecipients($tagIds);
        }

        $unsubscribed = $this->fetchUnsubscribedEmails(array_column($recipients, 'email'));

        $unique       = [];
        $leftOut      = [];
        foreach ($recipients as $recipient) {
            $email = dol_strtolower(trim($recipient['email']));
            if (!isValidEmail($email)) {
                $this->excluded['noemail']++;
                continue;
            }
            if (isset($unsubscribed[$email])) {
                $leftOut[$email] = true;
                continue;
            }

            $recipient['email']      = trim($recipient['email']);
            $recipient['dedupe_key'] = $this->getDedupeKey($recipient);
            if (!isset($unique[$recipient['dedupe_key']])) {
                $unique[$recipient['dedupe_key']] = $recipient;
            }
            if ($limit > 0 && count($unique) >= $limit) {
                break;
            }
        }
        $this->excluded['unsubscribed'] = count($leftOut);

        return array_values($unique);
    }

    /**
     * Recipients a user may see: the ones of the third parties he may open (all of them, or the ones he follows as
     * sales representative), and the ones without a third party. Their names, addresses and mails stay out of sight
     * for the others, as their cards
     *
     * @param  array $recipients Recipients, see getRecipients()
     * @param  User  $user       User
     * @return array             Recipients he may see
     */
    public function filterRecipientsForUser(array $recipients, User $user): array
    {
        return array_values(array_filter($recipients, function ($recipient) use ($user) {
            return reedcrm_newsletter_user_can_open_thirdparty($user, $this->db, (int) ($recipient['fk_soc'] ?? 0));
        }));
    }

    /**
     * Number of recipients the tags give before the narrower targeting (tags left out, customers or prospects, sales
     * representative): the card says how many the targeting leaves out
     *
     * @return int
     */
    public function countRecipientsWithoutFilters(): int
    {
        $copy                  = clone $this;
        $copy->exclude_tags    = '';
        $copy->thirdparty_type = '';
        $copy->fk_sales_rep    = null;

        return count($copy->getRecipients());
    }

    /**
     * Key of a recipient in the log of the sendings
     *
     * @param  array  $recipient Recipient, see getRecipients()
     * @return string            md5
     */
    public function getDedupeKey(array $recipient): string
    {
        $key = dol_strtolower(trim($recipient['email']));
        if ($this->mail_type === self::TYPE_REMINDER && $this->target_type === self::TARGET_PROJECT) {
            $key .= '|' . ((int) ($recipient['fk_project'] ?? 0));
        }

        return md5($key);
    }

    /**
     * Active contacts with an email that carry one of the tags
     *
     * @param  int[] $tagIds Tags and sub-tags
     * @return array         Recipients
     */
    protected function fetchContactRecipients(array $tagIds): array
    {
        $recipients = [];

        $sql  = 'SELECT DISTINCT sp.rowid, sp.email, sp.firstname, sp.lastname, sp.civility, sp.fk_soc, sp.datec, s.nom as thirdparty_name';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'socpeople as sp';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_contact as cc ON cc.fk_socpeople = sp.rowid';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = sp.fk_soc';
        $sql .= ' WHERE cc.fk_categorie IN (' . $this->db->sanitize(implode(',', $tagIds)) . ')';
        $sql .= ' AND sp.entity IN (' . getEntity('contact') . ')';
        $sql .= ' AND sp.statut = 1';
        $sql .= $this->getTargetFiltersSql('sp', 'sp.fk_soc');
        // The contacts without an email are read too: getRecipients() counts them as left out
        $sql .= ' ORDER BY sp.lastname, sp.firstname, sp.rowid';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return [];
        }

        while ($obj = $this->db->fetch_object($resql)) {
            $recipients[] = [
                'email'           => (string) $obj->email,
                'firstname'       => (string) $obj->firstname,
                'lastname'        => (string) $obj->lastname,
                'civility'        => (string) $obj->civility,
                'fk_contact'      => (int) $obj->rowid,
                'fk_soc'          => (int) $obj->fk_soc,
                'thirdparty_name' => (string) $obj->thirdparty_name,
                'fk_project'      => 0,
                'project_ref'     => '',
                'project_title'   => '',
                'date_creation'   => (int) $this->db->jdate($obj->datec),
            ];
        }
        $this->db->free($resql);

        return $recipients;
    }

    /**
     * Projects not closed that carry one of the tags, mailed to their external contacts, else to the email of the
     * opportunity (ReedCRM fields of the project), else to the email of the third party
     *
     * @param  int[] $tagIds Tags and sub-tags
     * @return array         Recipients
     */
    protected function fetchProjectRecipients(array $tagIds): array
    {
        require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

        // The fields of the opportunity are ReedCRM extrafields, an administrator may have removed them
        $extrafields = new ExtraFields($this->db);
        $extrafields->fetch_name_optionals_label('projet');
        $opportunityFields = [];
        foreach (['reedcrm_email', 'reedcrm_firstname', 'reedcrm_lastname'] as $field) {
            if (!empty($extrafields->attributes['projet']['type'][$field])) {
                $opportunityFields[] = $field;
            }
        }

        $sql  = 'SELECT DISTINCT p.rowid, p.ref, p.title, p.fk_soc, p.datec, p.dateo, p.datee, s.nom as thirdparty_name, s.email as thirdparty_email';
        foreach ($opportunityFields as $field) {
            $sql .= ', pe.' . $field;
        }
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'projet as p';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_project as cp ON cp.fk_project = p.rowid';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = p.fk_soc';
        if (!empty($opportunityFields)) {
            $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_extrafields as pe ON pe.fk_object = p.rowid';
        }
        $sql .= ' WHERE cp.fk_categorie IN (' . $this->db->sanitize(implode(',', $tagIds)) . ')';
        $sql .= ' AND p.entity IN (' . getEntity('project') . ')';
        $sql .= ' AND p.fk_statut <> ' . Project::STATUS_CLOSED;
        $sql .= $this->getTargetFiltersSql('p', 'p.fk_soc');
        $sql .= ' ORDER BY p.ref';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return [];
        }

        $projects = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $projects[(int) $obj->rowid] = $obj;
        }
        $this->db->free($resql);

        if (empty($projects)) {
            return [];
        }

        // External contacts of all the projects in one query per chunk
        $contactsByProject = [];
        foreach (array_chunk(array_keys($projects), 500) as $projectIds) {
            $sql  = 'SELECT ec.element_id, sp.rowid, sp.email, sp.firstname, sp.lastname, sp.civility, sp.fk_soc, s.nom as thirdparty_name';
            $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_contact as ec';
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . "c_type_contact as tc ON tc.rowid = ec.fk_c_type_contact AND tc.element = 'project' AND tc.source = 'external'";
            $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'socpeople as sp ON sp.rowid = ec.fk_socpeople';
            $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = sp.fk_soc';
            $sql .= ' WHERE ec.element_id IN (' . $this->db->sanitize(implode(',', $projectIds)) . ')';
            $sql .= ' AND ec.statut = 4 AND sp.statut = 1';
            $sql .= " AND sp.email IS NOT NULL AND sp.email <> ''";
            $sql .= ' ORDER BY ec.element_id, sp.lastname, sp.firstname';

            $resql = $this->db->query($sql);
            if (!$resql) {
                $this->error = $this->db->lasterror();
                return [];
            }
            while ($obj = $this->db->fetch_object($resql)) {
                $contactsByProject[(int) $obj->element_id][] = $obj;
            }
            $this->db->free($resql);
        }

        $recipients = [];
        foreach ($projects as $projectId => $project) {
            // After the creation, a project counts from its own creation, whoever its mail goes to
            $projectData = [
                'fk_project'         => $projectId,
                'project_ref'        => (string) $project->ref,
                'project_title'      => (string) $project->title,
                'date_creation'      => (int) $this->db->jdate($project->datec),
                'project_date_start' => (int) $this->db->jdate($project->dateo),
                'project_date_end'   => (int) $this->db->jdate($project->datee),
            ];

            if (!empty($contactsByProject[$projectId])) {
                foreach ($contactsByProject[$projectId] as $contact) {
                    $recipients[] = [
                        'email'           => (string) $contact->email,
                        'firstname'       => (string) $contact->firstname,
                        'lastname'        => (string) $contact->lastname,
                        'civility'        => (string) $contact->civility,
                        'fk_contact'      => (int) $contact->rowid,
                        'fk_soc'          => (int) ($contact->fk_soc ?: $project->fk_soc),
                        'thirdparty_name' => (string) ($contact->thirdparty_name ?: $project->thirdparty_name),
                    ] + $projectData;
                }
                continue;
            }

            $email     = '';
            $firstname = '';
            $lastname  = '';
            if (!empty($project->reedcrm_email) && isValidEmail(trim($project->reedcrm_email))) {
                $email     = trim($project->reedcrm_email);
                $firstname = (string) ($project->reedcrm_firstname ?? '');
                $lastname  = (string) ($project->reedcrm_lastname ?? '');
            } else {
                // A third party may hold several addresses in its email field: the first valid one is used
                foreach (preg_split('/[\s,;]+/', (string) $project->thirdparty_email) as $thirdpartyEmail) {
                    if (isValidEmail($thirdpartyEmail)) {
                        $email = $thirdpartyEmail;
                        break;
                    }
                }
            }
            if ($email === '') {
                $this->excluded['noemail']++;
                continue;
            }

            $recipients[] = [
                'email'           => $email,
                'firstname'       => $firstname,
                'lastname'        => $lastname,
                'civility'        => '',
                'fk_contact'      => 0,
                'fk_soc'          => (int) $project->fk_soc,
                'thirdparty_name' => (string) $project->thirdparty_name,
            ] + $projectData;
        }

        return $recipients;
    }

    /**
     * Addresses that asked not to receive mass emails anymore (the "No mass emailing" of the contacts, the
     * unsubscribe link of the newsletters and of the emailings of Dolibarr)
     *
     * @param  string[] $emails Addresses to check
     * @return array            [lowercase email => true]
     */
    public function fetchUnsubscribedEmails(array $emails): array
    {
        $emails = array_values(array_unique(array_filter(array_map(function ($email) {
            return dol_strtolower(trim((string) $email));
        }, $emails))));

        $unsubscribed = [];
        foreach (array_chunk($emails, 500) as $chunk) {
            $quoted = array_map(function ($email) {
                return "'" . $this->db->escape($email) . "'";
            }, $chunk);

            $sql  = 'SELECT DISTINCT LOWER(email) as email FROM ' . MAIN_DB_PREFIX . 'mailing_unsubscribe';
            $sql .= ' WHERE entity IN (' . getEntity('mailing') . ')';
            $sql .= ' AND LOWER(email) IN (' . implode(',', $quoted) . ')';

            $resql = $this->db->query($sql);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $unsubscribed[$obj->email] = true;
                }
                $this->db->free($resql);
            }
        }

        return $unsubscribed;
    }

    /**
     * Recipients of the occurrence already sent or attempted, by dedupe key
     *
     * @param  int   $occurrence Planned date of the sending
     * @return array             [dedupe_key => status]
     */
    public function fetchDoneKeys(int $occurrence): array
    {
        $done = [];

        $sql  = 'SELECT dedupe_key, status FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send';
        $sql .= ' WHERE fk_newsletter = ' . ((int) $this->id);
        $sql .= " AND occurrence = '" . $this->db->idate($occurrence) . "'";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $done[$obj->dedupe_key] = (int) $obj->status;
            }
            $this->db->free($resql);
        }

        return $done;
    }

    /**
     * Every line of the log, by recipient: a newsletter sent after the creation has one date per recipient
     *
     * @return array [dedupe_key => [occurrence as stored => [status, date of the sending]]]
     */
    public function fetchSendLines(): array
    {
        $lines = [];

        $sql  = 'SELECT dedupe_key, occurrence, status, date_send FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send';
        $sql .= ' WHERE fk_newsletter = ' . ((int) $this->id);

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $lines[$obj->dedupe_key][(string) $obj->occurrence] = ['status' => (int) $obj->status, 'date' => (int) $this->db->jdate($obj->date_send)];
            }
            $this->db->free($resql);
        }

        return $lines;
    }

    /**
     * Sending dates of a recipient after the creation: the creation of its contact (or project) plus the delay,
     * then by steps of the frequency for a reminder
     *
     * @param  array $recipient Recipient, see getRecipients()
     * @param  int   $now       Current date
     * @return int[]            [last date passed, 0 if none; next date to come, 0 if none]
     */
    public function getRecipientDates(array $recipient, int $now): array
    {
        $created = (int) ($recipient['date_creation'] ?? 0);
        if ($created <= 0) {
            return [0, 0];
        }

        $first = dol_time_plus_duree($created, max(0, (int) $this->send_delay), self::getUnit((string) $this->send_delay_unit, 'd'));
        if ($first > $now) {
            return [0, $first];
        }
        if (!$this->isRecurring()) {
            return [$first, 0];
        }

        // Every step is counted from the first date, so that a date found at one run is the same at the next one
        $frequency = (int) $this->frequency;
        $unit      = self::getUnit((string) $this->frequency_unit, 'm');
        $seconds   = ['d' => 86400, 'w' => 604800, 'm' => 2629800, 'y' => 31557600][$unit];
        $step      = max(0, (int) floor(($now - $first) / ($seconds * $frequency)));
        while ($step > 0 && dol_time_plus_duree($first, $step * $frequency, $unit) > $now) {
            $step--;
        }
        while (dol_time_plus_duree($first, ($step + 1) * $frequency, $unit) <= $now) {
            $step++;
        }

        return [dol_time_plus_duree($first, $step * $frequency, $unit), dol_time_plus_duree($first, ($step + 1) * $frequency, $unit)];
    }

    /**
     * Where a recipient stands on a newsletter sent after the creation
     *
     * A date passed is due, unless it falls before the start of the newsletter (its date of sending, now when there
     * is none): activating "30 days after the creation" does not mail at once every contact created long ago
     *
     * @param  array $recipient Recipient, see getRecipients()
     * @param  int   $now       Current date
     * @param  array $sendLines Log, see fetchSendLines()
     * @return array            [state => RECIPIENT_*, date => date of the sending concerned, next => next date of a reminder or 0,
     *                           sent_at => when the mail left, for a sent one]
     */
    public function getRecipientState(array $recipient, int $now, array $sendLines): array
    {
        list($current, $next) = $this->getRecipientDates($recipient, $now);
        if ($current === 0 && $next === 0) {
            return ['state' => self::RECIPIENT_NO_DATE, 'date' => 0, 'next' => 0, 'sent_at' => 0];
        }

        // Sent once: any line of the address counts, whatever date it was made for (the best one: sent before
        // pending, pending before error). A reminder: the line of its date
        $lines = $sendLines[$recipient['dedupe_key'] ?? $this->getDedupeKey($recipient)] ?? [];
        $line  = null;
        if ($this->isRecurring()) {
            $line = $current > 0 ? ($lines[$this->db->idate($current)] ?? null) : null;
        } else {
            foreach ($lines as $candidate) {
                if ($line === null || $candidate['status'] > $line['status']) {
                    $line = $candidate;
                }
            }
        }
        if ($line !== null) {
            // An error put back in the queue is due again, whatever the start
            if ($line['status'] === self::SEND_STATUS_RETRY) {
                return ['state' => self::RECIPIENT_DUE, 'date' => $current ?: $next, 'next' => $next, 'sent_at' => 0];
            }
            $states = [self::SEND_STATUS_SENT => self::RECIPIENT_SENT, self::SEND_STATUS_ERROR => self::RECIPIENT_ERROR];
            return ['state' => $states[$line['status']] ?? self::RECIPIENT_PENDING, 'date' => $current ?: $next, 'next' => $next, 'sent_at' => $line['status'] === self::SEND_STATUS_SENT ? $line['date'] : 0];
        }

        $start = !empty($this->date_send) ? (int) $this->date_send : $now;
        if ($current > 0 && $current >= $start) {
            return ['state' => self::RECIPIENT_DUE, 'date' => $current, 'next' => $next, 'sent_at' => 0];
        }
        if ($next > 0) {
            return ['state' => self::RECIPIENT_UPCOMING, 'date' => $next, 'next' => $next, 'sent_at' => 0];
        }

        return ['state' => self::RECIPIENT_SKIPPED, 'date' => $current, 'next' => 0, 'sent_at' => 0];
    }

    /**
     * Number of lines of the log of the sendings
     *
     * @param  User|null $user Only the lines this user may see, see getPerimeterSql(); null = all
     * @return int
     */
    public function countSendLines(?User $user = null): int
    {
        $sql   = 'SELECT COUNT(*) as nb FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send as ns WHERE ns.fk_newsletter = ' . ((int) $this->id);
        $sql  .= $user !== null ? self::getPerimeterSql($user, 'ns.fk_soc') : '';
        $resql = $this->db->query($sql);
        if (!$resql) {
            return 0;
        }
        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $obj ? (int) $obj->nb : 0;
    }

    /**
     * Results of the newsletter, read in its log: mails sent, mails in error (put back in the queue included), and
     * recipients who unsubscribed through the link of one of its mails
     *
     * @return array{sent: int, errors: int, unsubscribed: int}
     */
    public function getSendStats(): array
    {
        $stats = ['sent' => 0, 'errors' => 0, 'unsubscribed' => 0];
        if ($this->id <= 0) {
            return $stats;
        }

        $sql  = 'SELECT SUM(CASE WHEN status = ' . self::SEND_STATUS_SENT . ' THEN 1 ELSE 0 END) as sent,';
        $sql .= ' SUM(CASE WHEN status IN (' . self::SEND_STATUS_ERROR . ', ' . self::SEND_STATUS_RETRY . ') THEN 1 ELSE 0 END) as errors,';
        $sql .= ' SUM(CASE WHEN date_unsubscribe IS NOT NULL THEN 1 ELSE 0 END) as unsubscribed';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send WHERE fk_newsletter = ' . ((int) $this->id);

        $resql = $this->db->query($sql);
        $obj   = $resql ? $this->db->fetch_object($resql) : null;
        if ($obj) {
            $stats = ['sent' => (int) $obj->sent, 'errors' => (int) $obj->errors, 'unsubscribed' => (int) $obj->unsubscribed];
        }

        return $stats;
    }

    /**
     * Editions of the series of the newsletter (each one prepared from the previous one by "Prepare next month"), the
     * most recent first, with their results: the newer editions too when an older one is open. Deleted editions are
     * walked through but not listed
     *
     * @param  int   $max Most editions
     * @return array      [[newsletter => ReedcrmNewsletter, stats => see getSendStats()]]
     */
    public function fetchEditionSeries(int $max = 12): array
    {
        if ($this->id <= 0) {
            return [];
        }

        // The most recent edition first: the next ones of this one
        $newest = $this;
        $seen   = [(int) $this->id => true];
        for ($i = 0; $i < 240; $i++) {
            $nextId = $newest->fetchNextEdition();
            if ($nextId <= 0 || isset($seen[$nextId])) {
                break;
            }
            $next = new self($this->db);
            if ($next->fetch($nextId) <= 0) {
                break;
            }
            $seen[$nextId] = true;
            $newest        = $next;
        }

        // Then back through the previous ones
        $series  = [];
        $current = $newest;
        $walked  = [];
        while ($current !== null && count($series) < $max && !isset($walked[(int) $current->id])) {
            $walked[(int) $current->id] = true;
            if (!$current->isDeleted()) {
                $series[] = ['newsletter' => $current, 'stats' => $current->getSendStats()];
            }
            $previous = null;
            if (!empty($current->fk_previous)) {
                $previous = new self($this->db);
                if ($previous->fetch((int) $current->fk_previous) <= 0) {
                    $previous = null;
                }
            }
            $current = $previous;
        }

        return $series;
    }

    /**
     * SQL condition that keeps the lines of the third parties a user may open: all of them, the ones he follows as
     * sales representative, his own for an external user. A line without third party stays
     *
     * @param  User   $user   User
     * @param  string $column Column of the third party
     * @return string         " AND …", '' when he sees all
     */
    public static function getPerimeterSql(User $user, string $column): string
    {
        if (!empty($user->socid)) {
            return ' AND ' . $column . ' = ' . ((int) $user->socid);
        }
        if ($user->hasRight('societe', 'client', 'voir')) {
            return '';
        }

        return ' AND (' . $column . ' IS NULL OR ' . $column . ' = 0 OR ' . $column . ' IN (SELECT psc.fk_soc FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux as psc WHERE psc.fk_user = ' . ((int) $user->id) . '))';
    }

    /**
     * Last lines of the log of the sendings
     *
     * @param  int       $limit Number of lines
     * @param  User|null $user  Only the lines this user may see, see getPerimeterSql(); null = all
     * @return array            Lines, the newest first
     */
    public function fetchSendLog(int $limit = 50, ?User $user = null): array
    {
        $lines = [];

        $sql  = 'SELECT ns.rowid, ns.occurrence, ns.date_send, ns.email, ns.fk_contact, ns.fk_project, ns.fk_soc, ns.status, ns.error_msg, ns.date_unsubscribe,';
        $sql .= ' sp.firstname, sp.lastname, p.ref as project_ref, p.title as project_title, s.nom as thirdparty_name';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send as ns';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'socpeople as sp ON sp.rowid = ns.fk_contact';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = ns.fk_project';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = ns.fk_soc';
        $sql .= ' WHERE ns.fk_newsletter = ' . ((int) $this->id);
        $sql .= $user !== null ? self::getPerimeterSql($user, 'ns.fk_soc') : '';
        $sql .= ' ORDER BY ns.rowid DESC';
        $sql .= $this->db->plimit($limit);

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $obj->occurrence       = $this->db->jdate($obj->occurrence);
                $obj->date_send        = $this->db->jdate($obj->date_send);
                $obj->date_unsubscribe = $this->db->jdate($obj->date_unsubscribe);
                $lines[]               = $obj;
            }
            $this->db->free($resql);
        }

        return $lines;
    }

    /**
     * Email template chosen in template mode
     *
     * @return array|null [label, topic, content] or null when there is none
     */
    public function fetchEmailTemplate(): ?array
    {
        if (empty($this->fk_email_template)) {
            return null;
        }

        // The templates the form offers only: active, of a type for this target, and a private one of the author alone.
        // An id typed by hand never reads the private template of another user
        $types = ["'" . self::EMAIL_TEMPLATE_TYPE . "'", "'all'", "'" . ($this->target_type === self::TARGET_PROJECT ? 'project' : 'contact') . "'"];

        $sql  = 'SELECT label, topic, content FROM ' . MAIN_DB_PREFIX . 'c_email_templates';
        $sql .= ' WHERE rowid = ' . ((int) $this->fk_email_template);
        $sql .= ' AND entity IN (' . getEntity('c_email_templates') . ')';
        $sql .= ' AND active = 1';
        $sql .= ' AND type_template IN (' . implode(',', $types) . ')';
        $sql .= ' AND (private = 0 OR fk_user = ' . ((int) $this->fk_user_creat) . ')';

        $resql = $this->db->query($sql);
        if (!$resql) {
            return null;
        }
        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);

        return $obj ? ['label' => (string) $obj->label, 'topic' => (string) $obj->topic, 'content' => (string) $obj->content] : null;
    }

    /**
     * Subject and body to fill in, before the substitutions: the ones typed on the newsletter or the template ones
     *
     * @param  Translate|null $outputLangs Language of the texts the module adds (simple editor), null = the current one
     * @return array                       [subject, html body]
     */
    public function getContentSource(?Translate $outputLangs = null): array
    {
        global $langs;

        $outputLangs = $outputLangs ?: $langs;

        // A batch renders the same content for every recipient: the template is read once
        $cacheKey = $this->content_mode . '|' . ((int) $this->fk_email_template) . '|' . $outputLangs->defaultlang . '|' . md5($this->subject . "\0" . $this->body_html . "\0" . $this->body_blocks);
        if (!isset($this->contentSourceCache[$cacheKey])) {
            $this->contentSourceCache = [$cacheKey => $this->buildContentSource($outputLangs)];
        }

        return $this->contentSourceCache[$cacheKey];
    }

    /**
     * See getContentSource()
     *
     * @param  Translate $outputLangs Language of the texts the module adds
     * @return array                  [subject, html body], ['', ''] when the template is gone
     */
    protected function buildContentSource(Translate $outputLangs): array
    {
        // Made from the blocks at each sending: the logo and the name of the company are the current ones
        if ($this->content_mode === self::MODE_BLOCKS) {
            $blocks = reedcrm_newsletter_clean_blocks((string) $this->body_blocks);
            if (!reedcrm_newsletter_blocks_have_content($blocks)) {
                return [(string) $this->subject, ''];
            }

            return [(string) $this->subject, reedcrm_newsletter_render_blocks($blocks, $outputLangs, $this->previewLibrarySrc)];
        }

        if ($this->content_mode === self::MODE_TEMPLATE) {
            $template = $this->fetchEmailTemplate();
            if ($template === null) {
                return ['', ''];
            }

            $content = $template['content'];
            if (!dol_textishtml($content)) {
                $content = dol_nl2br(dol_escape_htmltag($content, 0, 1));
            }
            // An email template saved from the setup of Dolibarr loses its styles in the editor: it brings the text
            // only, the newsletter puts the frame around. A template that is already a whole document is kept as is
            if (stripos($content, '<html') === false) {
                $content = reedcrm_newsletter_frame($content);
            }

            return [$template['topic'], $content];
        }

        $body = (string) $this->body_html;
        if ($body !== '' && !dol_textishtml($body)) {
            $body = dol_nl2br(dol_escape_htmltag($body, 0, 1));
        }

        return [(string) $this->subject, $body];
    }

    /**
     * Values of the substitution keys of a recipient, as plain text
     *
     * @param  array     $recipient   Recipient, see getRecipients()
     * @param  Translate $outputLangs Language of the mail
     * @param  bool      $isTest      Test mail: its unsubscribe link only explains itself, it unsubscribes nobody
     * @return array                  [__KEY__ => value]
     */
    public function getRecipientSubstitutions(array $recipient, Translate $outputLangs, bool $isTest = false): array
    {
        $civility = '';
        if (!empty($recipient['civility'])) {
            $outputLangs->load('dict');
            $civility = $outputLangs->transnoentitiesnoconv('Civility' . $recipient['civility']);
            if ($civility === 'Civility' . $recipient['civility']) {
                $civility = '';
            }
        }

        // Date of the sending: the own date of the recipient after the creation, else the planned one
        $sendingDate = (int) ($recipient['occurrence'] ?? 0) ?: ((int) $this->date_send ?: dol_now());
        $printDate   = function ($date) use ($outputLangs) {
            return !empty($date) ? dol_print_date($date, 'day', 'tzserver', $outputLangs) : '';
        };

        return [
            '__EMAIL__'              => (string) $recipient['email'],
            '__FIRSTNAME__'          => (string) ($recipient['firstname'] ?? ''),
            '__LASTNAME__'           => (string) ($recipient['lastname'] ?? ''),
            '__CIVILITY__'           => $civility,
            '__THIRDPARTY_NAME__'    => (string) ($recipient['thirdparty_name'] ?? ''),
            '__PROJECT_REF__'        => (string) ($recipient['project_ref'] ?? ''),
            '__PROJECT_NAME__'       => (string) ($recipient['project_title'] ?? ''),
            '__PROJECT_DATE_START__' => $printDate($recipient['project_date_start'] ?? 0),
            '__PROJECT_DATE_END__'   => $printDate($recipient['project_date_end'] ?? 0),
            '__SENDING_DATE__'       => $printDate($sendingDate),
            '__UNSUBSCRIBE_URL__'    => $isTest ? reedcrm_newsletter_unsubscribe_test_url((int) $this->entity) : reedcrm_newsletter_unsubscribe_url((string) $recipient['email'], (int) $this->entity, (int) $this->id),
        ];
    }

    /**
     * Subject and HTML body of the mail of a recipient
     *
     * Same order as the other mails of the modules: make_substitutions() first with the keys of Dolibarr (company,
     * sender, dates), then the values of the recipient in one strtr() pass, so that a name holding a key is not
     * replaced in turn
     *
     * @param  array     $recipient   Recipient, see getRecipients()
     * @param  Translate $outputLangs Language of the mail
     * @param  bool      $isTest      Test mail, see getRecipientSubstitutions()
     * @return array                  [subject, html]
     */
    public function renderMail(array $recipient, Translate $outputLangs, bool $isTest = false): array
    {
        list($subject, $body) = $this->getContentSource($outputLangs);

        // The keys of Dolibarr (company, sender, dates) are the same for every recipient: built once per language
        $langCode = (string) $outputLangs->defaultlang;
        if (!isset($this->commonSubstitutions[$langCode])) {
            $common = getCommonSubstitutionArray($outputLangs, 0, null, null);
            complete_substitutions_array($common, $outputLangs, null);
            $this->commonSubstitutions[$langCode] = $common;
        }
        $common = $this->commonSubstitutions[$langCode];

        // The values typed on the contacts are text: escaped as they are, a "<" in a name stays visible
        $escape = function ($value) {
            return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };
        $values = $this->getRecipientSubstitutions($recipient, $outputLangs, $isTest);
        $html   = array_map($escape, $values);

        // In the colour of the text around it, on one line: the default blue link would be cut at the end of a line
        $unsubscribeLink           = '<a href="' . $escape($values['__UNSUBSCRIBE_URL__']) . '" style="color:inherit;text-decoration:underline;white-space:nowrap;">' . $escape($outputLangs->transnoentitiesnoconv('NewsletterUnsubscribeLink')) . '</a>';
        $html['__UNSUBSCRIBE__']   = $unsubscribeLink;
        $values['__UNSUBSCRIBE__'] = $values['__UNSUBSCRIBE_URL__'];

        // The text of the author does not read the setup: make_substitutions() would write the value of any constant
        // (__[MAIN_MAIL_SMTPS_ID]__, the salt of the passwords) or translation (__(…)__) typed in a block
        $subject = reedcrm_newsletter_strip_setup_keys($subject);
        $body    = reedcrm_newsletter_strip_setup_keys($body);

        // A subject holds one line
        $subject = strtr(make_substitutions($subject, $common, $outputLangs), array_map(function ($text) {
            return trim(preg_replace('/\s+/', ' ', $text));
        }, $values));
        $body = reedcrm_newsletter_substitute_in_attributes(make_substitutions($body, $common, $outputLangs, 1), $values, $html['__UNSUBSCRIBE_URL__']);
        $body = strtr($body, $html);

        // No newsletter without a way out: a footer is added when the text has no unsubscribe link of its own
        if ($body !== '' && getDolGlobalInt('REEDCRM_NEWSLETTER_UNSUBSCRIBE_LINK', 1) && strpos($body, $values['__UNSUBSCRIBE_URL__']) === false && strpos($body, $html['__UNSUBSCRIBE_URL__']) === false) {
            $footer = reedcrm_newsletter_unsubscribe_footer($unsubscribeLink, $outputLangs);
            if (stripos($body, '</body>') !== false) {
                $body = preg_replace('#</body>#i', $footer . '</body>', $body, 1);
            } else {
                $body .= $footer;
            }
        }

        return [$subject, $body];
    }

    /**
     * Sender of the mails: the one of the newsletter, else the sender of the mass emailings, else the main sender.
     * A bare address gets the name of the company: the recipient sees who writes, not "dolibarr@…"
     *
     * @return string
     */
    public function getSenderAddress(): string
    {
        global $mysoc;

        // The addresses of a fresh install (dolibarr@domain.com, robot@domain.com) are nobody's: skipped
        $from = (string) $this->email_from;
        foreach ([getDolGlobalString('MAILING_EMAIL_FROM'), getDolGlobalString('MAIN_MAIL_EMAIL_FROM'), (string) ($mysoc->email ?? '')] as $candidate) {
            if ($from !== '') {
                break;
            }
            if (trim($candidate) !== '' && !self::isPlaceholderAddress($candidate)) {
                $from = trim($candidate);
            }
        }
        if ($from !== '' && strpos($from, '<') === false && !empty($mysoc->name)) {
            // A comma, a quote or a chevron in the name would break the address
            $name = trim(preg_replace('/[",<>;]+/', ' ', (string) $mysoc->name));
            if ($name !== '') {
                $from = $name . ' <' . trim($from) . '>';
            }
        }

        return $from;
    }

    /**
     * Whether an address is one of the examples of a fresh install of Dolibarr
     *
     * @param  string $address Address, bare or "Name <address>"
     * @return bool
     */
    public static function isPlaceholderAddress(string $address): bool
    {
        return (bool) preg_match('/@(domain|example)\.(com|net|org)\s*>?\s*$/i', trim($address));
    }

    /**
     * Where the answers go: the address of the company when the newsletter leaves from the sender of the mass emailings
     * (often a no-reply one), the sender itself otherwise. "Just answer this mail" then reaches someone
     *
     * @return string Address, '' to answer the sender
     */
    public function getReplyToAddress(): string
    {
        global $mysoc;

        if (!empty($this->email_from) || empty($mysoc->email) || !isValidEmail(trim((string) $mysoc->email))) {
            return '';
        }
        $companyMail = trim((string) $mysoc->email);
        $from        = $this->getSenderAddress();
        $fromMail    = preg_match('/<([^>]+)>\s*$/', $from, $matches) ? $matches[1] : $from;
        if (dol_strtolower(trim($fromMail)) === dol_strtolower($companyMail)) {
            return '';
        }

        $name = trim(preg_replace('/[",<>;]+/', ' ', (string) ($mysoc->name ?? '')));

        return $name !== '' ? $name . ' <' . $companyMail . '>' : $companyMail;
    }

    /**
     * Send the mail of a recipient
     *
     * @param  array     $recipient   Recipient, see getRecipients()
     * @param  Translate $outputLangs Language of the mail
     * @param  string    $trackId     Id of the mail, in its headers
     * @param  string    $sendTo      Address to send to instead of the one of the recipient (test mail)
     * @param  string    $subjectPrefix Text put before the subject (test mail)
     * @return string                 '' if sent, else the error
     */
    public function sendToRecipient(array $recipient, Translate $outputLangs, string $trackId, string $sendTo = '', string $subjectPrefix = ''): string
    {
        require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';

        // A test goes to another address: its unsubscribe link must not be the one of the real recipient
        list($subject, $html) = $this->renderMail($recipient, $outputLangs, $sendTo !== '');
        $this->lastSubject    = $subjectPrefix . $subject;
        if ($this->content_mode === self::MODE_TEMPLATE && $html === '') {
            return $outputLangs->transnoentitiesnoconv('NewsletterTemplateNotFound');
        }
        if (trim($subject) === '' || !reedcrm_newsletter_has_content($html)) {
            return $outputLangs->transnoentitiesnoconv('NewsletterErrorEmptyContent');
        }

        $from = $this->getSenderAddress();
        if (empty($from)) {
            return $outputLangs->transnoentitiesnoconv('NewsletterErrorNoSender');
        }

        $moreInHeader = '';
        if (getDolGlobalInt('REEDCRM_NEWSLETTER_UNSUBSCRIBE_LINK', 1) && $sendTo === '') {
            // One click unsubscribe of the mail clients (RFC 8058): they POST to the URL, the public page accepts it
            $moreInHeader .= 'List-Unsubscribe: <' . reedcrm_newsletter_unsubscribe_url((string) $recipient['email'], (int) $this->entity, (int) $this->id) . ">\r\n";
            $moreInHeader .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
        }

        // Viewport, styles hidden from the text version, one line per block: see reedcrm_newsletter_prepare_mail_html()
        $html = reedcrm_newsletter_prepare_mail_html($html);

        // CMailFile::checkIfHTML() wraps in <html><body> any message that does not start with <html: a whole document
        // opened by its doctype would end up nested in another one, its <head> and <style> inside a <body>
        $html = (string) preg_replace('/^\s*(<\?xml[^>]*>\s*)?(<!DOCTYPE[^>]*>\s*)?/i', '', $html);

        // An address refused by the constructor leaves its error there, and sendfile() then fails on it
        $mailFile = new CMailFile($subjectPrefix . $subject, $sendTo !== '' ? $sendTo : $recipient['email'], $from, $html, [], [], [], '', '', 0, 1, '', '', $trackId, $moreInHeader, 'emailing', $this->getReplyToAddress());
        if (!$mailFile->sendfile()) {
            return $mailFile->error ?: $outputLangs->transnoentitiesnoconv('ErrorFailedToSendMail', $from, $recipient['email']);
        }

        return '';
    }

    /**
     * Send a test mail, filled in with the first recipient (or with the sender when there is none)
     *
     * @param  User      $user        User sending the test
     * @param  string    $email       Address of the test
     * @param  Translate $outputLangs Language of the mail
     * @return string                 '' if sent, else the error
     */
    public function sendTest(User $user, string $email, Translate $outputLangs): string
    {
        // Filled in with a recipient this user may see: the test would otherwise show him a contact out of his reach
        $recipients = self::getPerimeterSql($user, 'x') === '' ? $this->getRecipients(1) : $this->filterRecipientsForUser($this->getRecipients(), $user);
        $recipient  = $recipients[0] ?? [
            'email'           => $email,
            'firstname'       => $user->firstname,
            'lastname'        => $user->lastname,
            'civility'        => '',
            'fk_contact'      => 0,
            'fk_soc'          => 0,
            'thirdparty_name' => '',
            'fk_project'      => 0,
            'project_ref'     => '',
            'project_title'   => '',
        ];

        return $this->sendToRecipient($recipient, $outputLangs, 'reedcrmnl' . $this->id . 'test', $email, '[' . $outputLangs->transnoentitiesnoconv('NewsletterTestPrefix') . '] ');
    }

    /**
     * Put the newsletter in the queue of the cron: it leaves at the planned date, now when there is none
     *
     * @param  User $user User scheduling
     * @return int        > 0 if OK, < 0 if KO
     */
    public function schedule(User $user): int
    {
        if (empty($this->date_send)) {
            $this->date_send = dol_now();
        }
        $this->status = self::STATUS_SCHEDULED;

        $result = $this->update($user);
        if ($result > 0) {
            $this->closePrepareReminders();
        }

        return $result;
    }

    /**
     * Send the mails of the current occurrence not sent yet, $max at most. The rest leaves at the next run of the
     * cron. When every recipient is done, the newsletter is sent, the reminder moves to its next date
     *
     * After the creation, the mails of the recipients whose own date has come, the oldest first. The newsletter
     * stays active: the contacts created or tagged later are served in their turn
     *
     * @param  User      $user        User sending (the cron user for the scheduled sendings)
     * @param  int       $max         Most mails sent, 0 = the setup value
     * @param  Translate $outputLangs Language of the mails, null = the current one
     * @return array                  [sent, errors, remaining, total]
     */
    public function sendBatch(User $user, int $max = 0, ?Translate $outputLangs = null): array
    {
        global $langs;

        $result = ['sent' => 0, 'errors' => 0, 'remaining' => 0, 'total' => 0, 'error' => ''];
        if ((int) $this->status !== self::STATUS_SCHEDULED || empty($this->date_send)) {
            return $result;
        }

        $outputLangs = $outputLangs ?: $langs;
        $outputLangs->loadLangs(['main', 'mails', 'reedcrm@reedcrm']);

        // Nothing leaves without a content: a template deleted since the planning would write one error per recipient
        // and end as "Sent". The newsletter waits, its card says why
        $contentError = $this->getContentError($outputLangs);
        if ($contentError !== '') {
            $this->error     = $contentError;
            $result['error'] = $contentError;
            dol_syslog(__METHOD__ . ' newsletter ' . $this->id . ' not sent: ' . $contentError, LOG_WARNING);
            return $result;
        }

        $max        = $max > 0 ? $max : max(1, getDolGlobalInt('REEDCRM_NEWSLETTER_MAX_PER_RUN', 100));
        $now        = dol_now();
        $recipients = $this->getRecipients();

        // The recipients to serve, each with the date its line is written for
        $queue = [];
        if ($this->isAfterCreation()) {
            $sendLines = $this->fetchSendLines();
            foreach ($recipients as $recipient) {
                $state = $this->getRecipientState($recipient, $now, $sendLines);
                if ($state['state'] === self::RECIPIENT_DUE) {
                    $recipient['occurrence'] = $state['date'];
                    $queue[] = [$state['date'], $recipient];
                }
            }
            usort($queue, function ($a, $b) {
                return $a[0] <=> $b[0];
            });
        } else {
            $occurrence = (int) $this->date_send;
            $done       = $this->fetchDoneKeys($occurrence);
            // After "Retry the errors", only the lines put back in the queue leave, not the recipients tagged since
            $retryOnly = in_array(self::SEND_STATUS_RETRY, $done, true);
            foreach ($recipients as $recipient) {
                $status = $done[$recipient['dedupe_key']] ?? null;
                if ($retryOnly ? $status === self::SEND_STATUS_RETRY : $status === null) {
                    $recipient['occurrence'] = $occurrence;
                    $queue[] = [$occurrence, $recipient];
                }
            }
        }

        // A line to retry whose recipient left (unsubscribed, tag removed) goes back to an error that says so
        $closed = $this->closeLeftRetries(array_column($recipients, 'dedupe_key'), $outputLangs);

        $result['total'] = count($recipients);
        foreach ($queue as list($occurrence, $recipient)) {
            if ($result['sent'] + $result['errors'] >= $max) {
                $result['remaining']++;
                continue;
            }

            $lineId = $this->claimRecipient($occurrence, $recipient);
            if ($lineId === 0) {
                // Taken by a run in progress in the same time
                continue;
            }
            if ($lineId < 0) {
                // The log cannot be written: stop there, rather than mail again at the next run the ones sent
                $result['remaining'] += 1;
                break;
            }

            $trackId = 'reedcrmnl' . $this->id . '-' . $lineId;
            $error   = $this->sendToRecipient($recipient, $outputLangs, $trackId);
            $this->setSendLineResult($lineId, $error);
            if ($error === '') {
                $result['sent']++;
                $this->addAgendaEvent($user, $recipient, $trackId, $outputLangs);
            } else {
                $result['errors']++;
                dol_syslog(__METHOD__ . ' newsletter ' . $this->id . ' to ' . $recipient['email'] . ': ' . $error, LOG_WARNING);
            }
        }

        $sentSomething = $result['sent'] + $result['errors'] > 0;
        $justSent      = false;
        if ($this->isAfterCreation()) {
            // Never finished. A run with nothing due leaves the newsletter as it is, rather than touch it at every run
            if (!$sentSomething && $closed === 0) {
                return $result;
            }
            if ($sentSomething) {
                $this->date_last_send = $now;
            }
        } elseif ($result['remaining'] === 0) {
            $this->finishOccurrence($sentSomething);
            // A sending again with nothing left to send (everybody already served) prepares no other draft: the one of
            // the next month may have been deleted on purpose
            $justSent = (int) $this->status === self::STATUS_SENT && $sentSomething;
        }
        $this->nb_sent   = (int) $this->nb_sent + $result['sent'];
        $this->nb_errors = (int) $this->nb_errors + $result['errors'] + $closed;
        $this->update($user, 1);

        // "Every month": the next draft waits in the list as soon as this one is out
        if ($justSent) {
            $this->prepareNextOnItsOwn($user, $outputLangs);
        }

        return $result;
    }

    /**
     * Lines put back in the queue whose recipient is not one anymore: back to an error that says why, rather than
     * waiting for ever as "to send again"
     *
     * @param  string[]  $activeKeys  Dedupe keys of the current recipients
     * @param  Translate $outputLangs Language of the message
     * @return int                    Number of lines closed
     */
    protected function closeLeftRetries(array $activeKeys, Translate $outputLangs): int
    {
        // The lines to retry only, usually none: the keys of all the recipients are not sent to the database
        $leftIds = [];
        $active  = array_flip($activeKeys);
        $resql   = $this->db->query('SELECT rowid, dedupe_key FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send WHERE fk_newsletter = ' . ((int) $this->id) . ' AND status = ' . self::SEND_STATUS_RETRY);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                if (!isset($active[$obj->dedupe_key])) {
                    $leftIds[] = (int) $obj->rowid;
                }
            }
            $this->db->free($resql);
        }
        if (empty($leftIds)) {
            return 0;
        }

        $closed = 0;
        foreach (array_chunk($leftIds, 500) as $chunk) {
            $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send SET status = ' . self::SEND_STATUS_ERROR;
            $sql .= ", error_msg = '" . $this->db->escape($outputLangs->transnoentitiesnoconv('NewsletterRetryLeft')) . "'";
            $sql .= ' WHERE rowid IN (' . $this->db->sanitize(implode(',', $chunk)) . ') AND status = ' . self::SEND_STATUS_RETRY;
            $resql = $this->db->query($sql);
            $closed += $resql ? (int) $this->db->affected_rows($resql) : 0;
        }

        return $closed;
    }

    /**
     * Claim a recipient for the occurrence by inserting its line: the unique key refuses a second claim
     *
     * @param  int   $occurrence Planned date of the sending
     * @param  array $recipient  Recipient, see getRecipients()
     * @return int               Id of the line, 0 if already claimed, < 0 if KO
     */
    protected function claimRecipient(int $occurrence, array $recipient): int
    {
        // A line put back in the queue by retryErrors() is taken again in place: the update claims it atomically
        $where  = ' WHERE fk_newsletter = ' . ((int) $this->id) . " AND dedupe_key = '" . $this->db->escape($recipient['dedupe_key']) . "'";
        $where .= " AND occurrence = '" . $this->db->idate($occurrence) . "'";
        $resql  = $this->db->query('UPDATE ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send SET status = ' . self::SEND_STATUS_PENDING . $where . ' AND status = ' . self::SEND_STATUS_RETRY);
        if ($resql && $this->db->affected_rows($resql) > 0) {
            $resql = $this->db->query('SELECT rowid FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send' . $where);
            $obj   = $resql ? $this->db->fetch_object($resql) : null;
            return $obj ? (int) $obj->rowid : -1;
        }

        $sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send (entity, fk_newsletter, occurrence, dedupe_key, email, fk_contact, fk_project, fk_soc, status)';
        $sql .= ' VALUES (' . ((int) $this->entity) . ', ' . ((int) $this->id) . ", '" . $this->db->idate($occurrence) . "'";
        $sql .= ", '" . $this->db->escape($recipient['dedupe_key']) . "', '" . $this->db->escape(dol_trunc($recipient['email'], 255, 'right', 'UTF-8', 1)) . "'";
        $sql .= ', ' . (!empty($recipient['fk_contact']) ? (int) $recipient['fk_contact'] : 'NULL');
        $sql .= ', ' . (!empty($recipient['fk_project']) ? (int) $recipient['fk_project'] : 'NULL');
        $sql .= ', ' . (!empty($recipient['fk_soc']) ? (int) $recipient['fk_soc'] : 'NULL');
        $sql .= ', ' . self::SEND_STATUS_PENDING . ')';

        $resql = $this->db->query($sql);
        if ($resql) {
            return (int) $this->db->last_insert_id(MAIN_DB_PREFIX . 'reedcrm_newsletter_send');
        }

        // Refused by the unique key: another run has just taken the recipient. Any other error is a real one
        $done = $this->fetchDoneKeys($occurrence);
        if (isset($done[$recipient['dedupe_key']])) {
            return 0;
        }
        $this->error = $this->db->lasterror();
        dol_syslog(__METHOD__ . ' ' . $this->error, LOG_ERR);

        return -1;
    }

    /**
     * Store the result of a mail in its line
     *
     * @param  int    $lineId Id of the line
     * @param  string $error  '' if sent, else the error
     * @return void
     */
    protected function setSendLineResult(int $lineId, string $error): void
    {
        $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send SET';
        $sql .= ' status = ' . ($error === '' ? self::SEND_STATUS_SENT : self::SEND_STATUS_ERROR);
        $sql .= ", date_send = '" . $this->db->idate(dol_now()) . "'";
        $sql .= ', error_msg = ' . ($error === '' ? 'NULL' : "'" . $this->db->escape(dol_trunc($error, 250, 'right', 'UTF-8', 1)) . "'");
        $sql .= ' WHERE rowid = ' . ((int) $lineId);

        $this->db->query($sql);
    }

    /**
     * Put the errors of the last occurrence back in the queue: those recipients only are mailed again, each line
     * keeps its error until it is sent again in place
     *
     * @param  User $user User asking
     * @return int        Number of lines put back, < 0 if KO
     */
    public function retryErrors(User $user): int
    {
        if ($this->isDeleted() || (int) $this->status === self::STATUS_DRAFT) {
            $this->error = 'NewsletterActionNotAllowed';
            return -1;
        }
        if ($this->isAfterCreation()) {
            return $this->retryAllErrors($user);
        }

        $sql  = 'SELECT occurrence FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send';
        $sql .= ' WHERE fk_newsletter = ' . ((int) $this->id) . ' AND status = ' . self::SEND_STATUS_ERROR;
        $sql .= ' ORDER BY occurrence DESC' . $this->db->plimit(1);

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        $obj = $this->db->fetch_object($resql);
        $this->db->free($resql);
        if (!$obj) {
            return 0;
        }

        $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send SET status = ' . self::SEND_STATUS_RETRY;
        $sql .= ' WHERE fk_newsletter = ' . ((int) $this->id) . ' AND status = ' . self::SEND_STATUS_ERROR;
        $sql .= " AND occurrence = '" . $this->db->escape($obj->occurrence) . "'";
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        $nbRetried = (int) $this->db->affected_rows($resql);

        // The occurrence in error is the current one again: its other recipients are kept as done
        $this->nb_errors = max(0, (int) $this->nb_errors - $nbRetried);
        $this->date_send = $this->db->jdate($obj->occurrence);
        $this->status    = self::STATUS_SCHEDULED;
        if ($this->update($user, 1) < 0) {
            return -1;
        }

        return $nbRetried;
    }

    /**
     * After the creation, each recipient has its own date: all the errors are put back in the queue, the cron serves
     * them again. Nothing to put back changes nothing: the newsletter is not activated for that
     *
     * @param  User $user User asking
     * @return int        Number of lines put back, < 0 if KO
     */
    protected function retryAllErrors(User $user): int
    {
        $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send SET status = ' . self::SEND_STATUS_RETRY;
        $sql .= ' WHERE fk_newsletter = ' . ((int) $this->id) . ' AND status = ' . self::SEND_STATUS_ERROR;
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }
        $nbRetried = (int) $this->db->affected_rows($resql);
        if ($nbRetried === 0) {
            return 0;
        }

        $this->nb_errors = max(0, (int) $this->nb_errors - $nbRetried);
        if ($this->update($user, 1) < 0) {
            return -1;
        }

        return $nbRetried;
    }

    /**
     * End of an occurrence: a newsletter is sent, a reminder moves to its next date after now
     *
     * @param  bool $sentSomething Whether a mail left (or failed): "last sending" does not move for an empty run
     * @return void
     */
    protected function finishOccurrence(bool $sentSomething = true): void
    {
        $now = dol_now();

        if ($sentSomething) {
            $this->date_last_send = $now;
        }
        if ($this->isRecurring()) {
            // Sent before its date ("Send now"), the reminder moves on from its planned date, not from today
            $this->date_send = $this->getNextDate((int) $this->date_send, max($now, (int) $this->date_send));
            return;
        }

        $this->status = self::STATUS_SENT;
    }

    /**
     * Next date of a reminder after a given date, by steps of its frequency from its date
     *
     * @param  int $from  Date of the last occurrence
     * @param  int $after Date to pass
     * @return int        Next date
     */
    public function getNextDate(int $from, int $after): int
    {
        $unit = self::getUnit((string) $this->frequency_unit, 'm');
        $next = $from;
        // dol_time_plus_duree() knows days, weeks, months and years; the loop jumps the periods missed
        for ($i = 0; $next <= $after && $i < 1000; $i++) {
            $next = dol_time_plus_duree($next, (int) $this->frequency, $unit);
        }

        return $next;
    }

    /**
     * Event "Outgoing email" on the contact, the third party and the project of the recipient, when the setup asks
     * for it. The sales representatives of the third party are assigned: with "their own events" only, they see the
     * mails of their customers, whoever (or whichever scheduled job) sent them
     *
     * @param  User           $user        User sending
     * @param  array          $recipient   Recipient, see getRecipients()
     * @param  string         $trackId     Id of the mail in its headers
     * @param  Translate|null $outputLangs Language of the event, null = the current one
     * @return void
     */
    protected function addAgendaEvent(User $user, array $recipient, string $trackId = '', ?Translate $outputLangs = null): void
    {
        global $langs;

        if (!getDolGlobalInt('REEDCRM_NEWSLETTER_AGENDA_EVENT') || !isModEnabled('agenda')) {
            return;
        }
        $eventLangs = $outputLangs ?: $langs;

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

        $who = trim(dolGetFirstLastname((string) ($recipient['firstname'] ?? ''), (string) ($recipient['lastname'] ?? '')));

        $actionComm                = new ActionComm($this->db);
        $actionComm->type_code     = 'AC_EMAIL';
        $actionComm->code          = 'AC_REEDCRM_NEWSLETTER';
        $actionComm->label         = $eventLangs->transnoentities('NewsletterEventLabel', $this->label, $who !== '' ? $who : $recipient['email']);
        $actionComm->note_private  = $eventLangs->transnoentities('NewsletterEventNote', $this->ref, $recipient['email']);
        $actionComm->datep         = dol_now();
        $actionComm->datef         = $actionComm->datep;
        $actionComm->percentage    = -1;
        $actionComm->userownerid   = $user->id;
        $actionComm->socid         = (int) $recipient['fk_soc'];
        $actionComm->contact_id    = (int) $recipient['fk_contact'];
        $actionComm->fk_project    = (int) $recipient['fk_project'];
        $actionComm->fk_element    = $this->id;
        $actionComm->elementtype   = self::AGENDA_ELEMENT_TYPE;
        $actionComm->email_from    = $this->getSenderAddress();
        $actionComm->email_to      = (string) $recipient['email'];
        $actionComm->email_subject = $this->lastSubject;
        $actionComm->email_msgid   = $trackId;
        if (!empty($recipient['fk_contact'])) {
            $actionComm->socpeopleassigned = [(int) $recipient['fk_contact'] => ['id' => (int) $recipient['fk_contact'], 'mandatory' => 0, 'answer_status' => 0, 'transparency' => 0]];
        }
        $assigned = [$user->id => ['id' => $user->id, 'mandatory' => 0, 'answer_status' => 0, 'transparency' => 0]];
        foreach ($this->getSalesRepresentatives((int) $recipient['fk_soc']) as $salesRepId) {
            $assigned[$salesRepId] = ['id' => $salesRepId, 'mandatory' => 0, 'answer_status' => 0, 'transparency' => 0];
        }
        $actionComm->userassigned = $assigned;

        if ($actionComm->create($user, 1) < 0) {
            dol_syslog(__METHOD__ . ' ' . $actionComm->error, LOG_WARNING);
        }
    }

    /**
     * Sales representatives of a third party, cached for a batch
     *
     * @param  int   $socId Third party
     * @return int[]        Ids of the users
     */
    public function getSalesRepresentatives(int $socId): array
    {
        if ($socId <= 0) {
            return [];
        }
        if (!isset($this->salesRepresentatives[$socId])) {
            $this->salesRepresentatives[$socId] = [];
            $resql = $this->db->query('SELECT fk_user FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux WHERE fk_soc = ' . $socId);
            if ($resql) {
                while ($obj = $this->db->fetch_object($resql)) {
                    $this->salesRepresentatives[$socId][] = (int) $obj->fk_user;
                }
                $this->db->free($resql);
            }
        }

        return $this->salesRepresentatives[$socId];
    }
}

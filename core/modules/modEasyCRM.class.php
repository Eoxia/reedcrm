<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * 	\defgroup   easycrm     Module EasyCRM
 *  \brief      EasyCRM module descriptor.
 *
 *  \file       core/modules/modEasyCRM.class.php
 *  \ingroup    easycrm
 *  \brief      Description and activation file for module EasyCRM
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module EasyCRM
 */
class modEasyCRM extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

        if (file_exists(__DIR__ . '/../../../saturne/lib/saturne_functions.lib.php')) {
			require_once __DIR__ . '/../../../saturne/lib/saturne_functions.lib.php';
            saturne_load_langs(['easycrm@easycrm']);
        } else {
            $this->error++;
            $this->errors[] = $langs->trans('activateModuleDependNotSatisfied', 'EasyCRM', 'Saturne');
        }

        // ID for module (must be unique).
        // Use here a free id (See in Home -> System information -> Dolibarr for list of used module id).
        $this->numero = 436351;

        // Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'easycrm';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other','...'
        // It is used to group modules by family in module setup page
		$this->family = '';

        // Module position in the family on 2 digits ('01', '10', '20', ...)
		$this->module_position = '';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        $this->familyinfo = ['Eoxia' => ['position' => '01', 'label' => 'Eoxia']];
        // Module label (no space allowed), used if translation string 'ModulePriseoName' not found (Priseo is name of module).
		$this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description, used if translation string 'ModulePriseoDesc' not found (Priseo is name of module).
        $this->description = $langs->trans('EasyCRMDescription');
        // Used only if file README.md and README-LL.md not found.
        $this->descriptionlong = $langs->trans('EasyCRMDescriptionLong');

        // Author
		$this->editor_name = 'Eoxia';
		$this->editor_url = 'https://www.eoxia.com';

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
		$this->version = '1.4.0';

        // Url to the file with your last numberversion of this module
        //$this->url_last_version = 'http://www.example.com/versionmodule.txt';

        // Key used in llx_const table to save module status enabled/disabled (where EASYCRM is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Name of image file used for this module.
        // If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
        // If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
        // To use a supported fa-xxx css style of font awesome, use this->picto='xxx'
		$this->picto = 'easycrm_color@easycrm';

        // Define some features supported by module (triggers, login, substitutions, menus, css, etc...)
		$this->module_parts = [
			// Set this to 1 if module has its own trigger directory (core/triggers)
			'triggers' => 1,
			// Set this to 1 if module has its own login method file (core/login)
			'login' => 0,
			// Set this to 1 if module has its own substitution function file (core/substitutions)
			'substitutions' => 0,
			// Set this to 1 if module has its own menus handler directory (core/menus)
			'menus' => 0,
			// Set this to 1 if module overwrite template dir (core/tpl)
			'tpl' => 0,
			// Set this to 1 if module has its own barcode directory (core/modules/barcode)
			'barcode' => 0,
			// Set this to 1 if module has its own models' directory (core/modules/xxx)
			'models' => 0,
			// Set this to 1 if module has its own printing directory (core/modules/printing)
			'printing' => 0,
			// Set this to 1 if module has its own theme directory (theme)
			'theme' => 0,
			// Set this to relative path of css file if module has its own css file
			'css' => [],
			// Set this to relative path of js file if module must load a js on all pages
			'js' => [],
			// Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all'
			'hooks' => [
                'thirdpartycomm',
                'projectcard',
                'projectlist',
                'propalcard',
                'invoicereccard',
                'invoicereccontact',
                'invoicereclist',
                'invoicelist',
                'invoicecard',
                'contactcard',
                'thirdpartycard',
                'thirdpartylist',
                'main',
                'pwaadmin'
            ],
			// Set this to 1 if features of module are opened to external users
			'moduleforexternal' => 0,
        ];

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/easycrm/temp","/easycrm/subdir");
		$this->dirs = ['/easycrm/temp'];

		// Config pages. Put here list of php page, stored into easycrm/admin directory, to use to set up module.
		$this->config_page_url = ['setup.php@easycrm'];

		// Dependencies
		// A condition to hide module
		$this->hidden = false;
		// List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
		$this->depends = ['modSaturne', 'modFckeditor', 'modAgenda', 'modSociete', 'modProjet', 'modCategorie', 'modPropale', 'modCron'];
		$this->requiredby = []; // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
		$this->conflictwith = []; // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)

		// The language file dedicated to your module
		$this->langfiles = ['easycrm@easycrm'];

		// Prerequisites
		$this->phpmin = [7, 4]; // Minimum version of PHP required by module
		$this->need_dolibarr_version = [16, 0]; // Minimum version of Dolibarr required by module

		// Messages at activation
		$this->warnings_activation = []; // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		$this->warnings_activation_ext = []; // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
		//$this->automatic_activation = array('FR'=>'EasyCRMWasAutomaticallyActivatedBecauseOfYourCountryChoice');
		//$this->always_enabled = true;								// If true, can't be disabled

		// Constants
		// List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
		// Example: $this->const=array(1 => array('EASYCRM_MYNEWCONST1', 'chaine', 'myvalue', 'This is a constant to add', 1),
		//                             2 => array('EASYCRM_MYNEWCONST2', 'chaine', 'myvalue', 'This is another constant to add', 0, 'current', 1)
		// );
        $i = 0;
		$this->const = [
            // CONST CONFIGURATION
            // CONST THIRDPARTY
            $i++ => ['EASYCRM_THIRDPARTY_CLIENT_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_CLIENT_VALUE', 'integer', 2, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_NAME_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_PHONE_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_EMAIL_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_WEB_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_COMMERCIAL_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_PRIVATE_NOTE_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_THIRDPARTY_CATEGORIES_VISIBLE', 'integer', 1, '', 0, 'current'],

            // CONST CONTACT
            $i++ => ['EASYCRM_CONTACT_LASTNAME_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_CONTACT_FIRSTNAME_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_CONTACT_JOB_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_CONTACT_PHONEPRO_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_CONTACT_EMAIL_VISIBLE', 'integer', 1, '', 0, 'current'],

            // CONST PROJECT
            $i++ => ['EASYCRM_PROJECT_LABEL_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_OPPORTUNITY_STATUS_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_OPPORTUNITY_STATUS_VALUE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_OPPORTUNITY_AMOUNT_VALUE', 'integer', 3000, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_DATE_START_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_DESCRIPTION_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_EXTRAFIELDS_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_PROJECT_CATEGORIES_VISIBLE', 'integer', 1, '', 0, 'current'],

            // CONST TASK
            $i++ => ['EASYCRM_TASK_LABEL_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_TASK_LABEL_VALUE', 'chaine', $langs->trans('CommercialFollowUp'), '', 0, 'current'],
            $i++ => ['EASYCRM_TASK_TIMESPENT_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_TASK_TIMESPENT_VALUE', 'integer', 15, '', 0, 'current'],

            // CONST EVENT
            $i++ => ['EASYCRM_EVENT_TYPE_CODE_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_TYPE_CODE_VALUE', 'chaine', 'AC_TEL', '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_LABEL_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_DATE_START_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_DATE_END_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_STATUS_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_STATUS_VALUE', 'integer', -1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_DESCRIPTION_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_CATEGORIES_VISIBLE', 'integer', 1, '', 0, 'current'],

            // CONST PWA
            $i++ => ['EASYCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO', 'integer', 0, '', 0, 'current'],

			// CONST ADDRESS
			$i++ => ['EASYCRM_DISPLAY_MAIN_ADDRESS', 'integer', 0, '', 0, 'current'],
            $i++ => ['EASYCRM_ADDRESS_ADDON', 'chaine', 'mod_address_standard', '', 0, 'current'],

            // CONST MODULE
			$i++ => ['EASYCRM_VERSION','chaine', $this->version, '', 0, 'current'],
			$i++ => ['EASYCRM_DB_VERSION', 'chaine', $this->version, '', 0, 'current'],
            $i++ => ['EASYCRM_SHOW_PATCH_NOTE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG', 'integer', 0, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_WIDTH_MINI', 'integer', 128, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_HEIGHT_MINI', 'integer', 72, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_WIDTH_SMALL', 'integer', 480, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_HEIGHT_SMALL', 'integer', 270, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_WIDTH_MEDIUM', 'integer', 854, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_HEIGHT_MEDIUM', 'integer', 480, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_WIDTH_LARGE', 'integer', 1280, '', 0, 'current'],
            $i++ => ['EASYCRM_MEDIA_MAX_HEIGHT_LARGE', 'integer', 720, '', 0, 'current'],
            $i   => ['EASYCRM_DISPLAY_NUMBER_MEDIA_GALLERY', 'integer', 8, '', 0, 'current'],
        ];

        // Some keys to add into the overwriting translation tables
        $this->overwrite_translation = [
            'fr_FR:ActionAC_EMAIL_IN' => 'Email entrant',
            'fr_FR:ActionAC_EMAIL'    => 'Email sortant',
            'fr_FR:ActionAC_RDV'      => 'Rendez-vous physique ou visioconférence'
        ];

		if (!isset($conf->easycrm) || !isset($conf->easycrm->enabled)) {
			$conf->easycrm = new stdClass();
			$conf->easycrm->enabled = 0;
		}

		// Array to add new pages in new tabs
		// Example:
		// $this->tabs[] = array('data'=>'objecttype:+tabname2:SUBSTITUTION_Title2:mylangfile@easycrm:$user->rights->othermodule->read:/easycrm/mynewtab2.php?id=__ID__',  	// To add another new tab identified by code tabname2. Label will be result of calling all substitution functions on 'Title2' key.
		// $this->tabs[] = array('data'=>'objecttype:-tabname:NU:conditiontoremove');

		$pictopath    = dol_buildpath('/custom/easycrm/img/easycrm_color.png', 1);
		$pictoEasycrm = img_picto('', $pictopath, '', 1, 0, 0, '', 'pictoModule');

        $this->tabs   = [];
        $this->tabs[] = ['data' => 'project' . ':+address:' . $pictoEasycrm . $langs->trans('Addresses') . ':easycrm@easycrm:$user->rights->easycrm->read:/custom/easycrm/view/address_card.php?from_id=__ID__&from_type=project'];
        $this->tabs[] = ['data' => 'project' . ':+map:' . $pictoEasycrm . $langs->trans('Map') . ':easycrm@easycrm:$user->rights->easycrm->read:/custom/easycrm/view/map.php?from_type=project&from_id=__ID__'];

		// Dictionaries.
		$this->dictionaries = [
			'langs' => 'easycrm@easycrm',
			// List of tables we want to see into dictonnary editor.
			'tabname' => [
				MAIN_DB_PREFIX . 'c_commercial_status',
				MAIN_DB_PREFIX . 'c_refusal_reason',
				MAIN_DB_PREFIX . 'c_address_type'
			],
			// Label of tables.
			'tablib' => [
				'CommercialStatus',
				'RefusalReason',
				'AddressType'
			],
			// Request to select fields.
			'tabsql' => [
				'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.element_type, f.active, f.position FROM ' . MAIN_DB_PREFIX . 'c_commercial_status as f',
				'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.element_type, f.active, f.position FROM ' . MAIN_DB_PREFIX . 'c_refusal_reason as f',
				'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.active, f.position FROM ' . MAIN_DB_PREFIX . 'c_address_type as f'
			],
			// Sort order.
			'tabsqlsort' => [
				'position ASC',
				'position ASC',
				'position ASC'
			],
			// List of fields (result of select to show dictionary).
			'tabfield' => [
				'ref,label,description,element_type,position',
				'ref,label,description,element_type,position',
				'ref,label,description,position'
			],
			// List of fields (list of fields to edit a record).
			'tabfieldvalue' => [
				'ref,label,description,element_type,position',
				'ref,label,description,element_type,position',
				'ref,label,description,position'
			],
			// List of fields (list of fields for insert).
			'tabfieldinsert' => [
				'ref,label,description,element_type,position',
				'ref,label,description,element_type,position',
				'ref,label,description,position'
			],
			// Name of columns with primary key (try to always name it 'rowid').
			'tabrowid' => [
				'rowid',
				'rowid',
				'rowid'
			],
			// Condition to show each dictionary.
			'tabcond' => [
				$conf->easycrm->enabled,
				$conf->easycrm->enabled,
				$conf->easycrm->enabled
			]
		];

		// Boxes/Widgets
		$this->boxes = [];

        // Cronjobs (List of cron jobs entries to add when module is enabled)
        // unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
        $this->cronjobs = [
            0 => [
                'label'         => $langs->transnoentities('UpdateNotationObjectContactsJob', $langs->transnoentities('FactureMins')),
                'jobtype'       => 'method',
                'class'         => '/easycrm/class/easycrmcron.class.php',
                'objectname'    => 'EasycrmCron',
                'method'        => 'updateNotationObjectContacts',
                'parameters'    => 'Facture, AND t.fk_statut = 1',
                'comment'       => $langs->transnoentities('UpdateNotationObjectContactsJobComment', $langs->transnoentities('FactureMins')),
                'frequency'     => 1,
                'unitfrequency' => 86400,
                'status'        => 1,
                'test'          => '$conf->saturne->enabled && $conf->easycrm->enabled',
                'priority'      => 50
            ],
            1 => [
                'label'         => $langs->transnoentities('UpdateNotationObjectContactsJob', $langs->transnoentities('FactureRecMins')),
                'jobtype'       => 'method',
                'class'         => '/easycrm/class/easycrmcron.class.php',
                'objectname'    => 'EasycrmCron',
                'method'        => 'updateNotationObjectContacts',
                'parameters'    => 'FactureRec',
                'comment'       => $langs->transnoentities('UpdateNotationObjectContactsJobComment', $langs->transnoentities('FactureRecMins')),
                'frequency'     => 1,
                'unitfrequency' => 86400,
                'status'        => 1,
                'test'          => '$conf->saturne->enabled && $conf->easycrm->enabled',
                'priority'      => 50
            ],
            2 => [
                'label'         => $langs->transnoentities('UpdateNotationObjectContactsJob', $langs->transnoentities('ThirdPartyMins')),
                'jobtype'       => 'method',
                'class'         => '/easycrm/class/easycrmcron.class.php',
                'objectname'    => 'EasycrmCron',
                'method'        => 'updateNotationObjectContacts',
                'parameters'    => 'Societe',
                'comment'       => $langs->transnoentities('UpdateNotationObjectContactsJobComment', $langs->transnoentities('ThirdPartyMins')),
                'frequency'     => 1,
                'unitfrequency' => 86400,
                'status'        => 1,
                'test'          => '$conf->saturne->enabled && $conf->easycrm->enabled',
                'priority'      => 50
            ]
        ];

		// Permissions provided by this module
		$this->rights = [];
		$r = 0;

        /* EASYCRM PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->trans('LireModule', 'EasyCRM');
        $this->rights[$r][4] = 'lire';
        $this->rights[$r][5] = 1;
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->trans('ReadModule', 'EasyCRM');
        $this->rights[$r][4] = 'read';
        $this->rights[$r][5] = 1;
        $r++;

        /* ADDRESS PERMISSSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = $langs->transnoentities('ReadObjects',$langs->transnoentities('Address')); // Permission label
        $this->rights[$r][4] = 'address'; // In php code, permission will be checked by test if ($user->rights->easycrm->level1->level2)
        $this->rights[$r][5] = 'read'; // In php code, permission will be checked by test if ($user->rights->easycrm->level1->level2)
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = $langs->transnoentities('CreateObjects', $langs->transnoentities('Address')); // Permission label
        $this->rights[$r][4] = 'address'; // In php code, permission will be checked by test if ($user->rights->easycrm->level1->level2)
        $this->rights[$r][5] = 'write'; // In php code, permission will be checked by test if ($user->rights->easycrm->level1->level2)
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1); // Permission id (must not be already used)
        $this->rights[$r][1] = $langs->transnoentities('DeleteObjects', $langs->transnoentities('Address')); // Permission label
        $this->rights[$r][4] = 'address'; // In php code, permission will be checked by test if ($user->rights->easycrm->level1->level2)
        $this->rights[$r][5] = 'delete'; // In php code, permission will be checked by test if ($user->rights->easycrm->level1->level2)
        $r++;

        /* ADMINPAGE PANEL ACCESS PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadAdminPage', 'EasyCRM');
        $this->rights[$r][4] = 'adminpage';
        $this->rights[$r][5] = 'read';

		// Main menu entries to add
		$this->menu = [];
		$r = 0;

		// Add here entries to declare new menus
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=easycrm', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'     => 'top', // This is a Top menu entry
            'titre'    => 'EasyCRM',
            'prefix'   => '<i class="fas fa-home pictofixedwidth"></i>',
            'mainmenu' => 'easycrm',
            'leftmenu' => '',
            'url'      => '/easycrm/easycrmindex.php', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => '$conf->easycrm->enabled', // Define condition to show or hide menu entry. Use '$conf->easycrm->enabled' if entry must be visible if module is enabled.
            'perms'    => '$user->rights->easycrm->lire', // Use 'perms'=>'$user->rights->easycrm->myobject->read' if you want your menu with a permission rules
            'target'   => '',
            'user'     => 0, // 0=Menu for internal users, 1=external users, 2=both
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=easycrm', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'     => 'left', // This is a Top menu entry
            'titre'    => $langs->transnoentities('QuickCreation'),
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'easycrm',
            'leftmenu' => 'quickcreation',
            'url'      => '/easycrm/view/quickcreation.php', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => '$conf->easycrm->enabled', // Define condition to show or hide menu entry. Use '$conf->easycrm->enabled' if entry must be visible if module is enabled.
            'perms'    => '$user->rights->easycrm->read', // Use 'perms'=>'$user->rights->easycrm->myobject->read' if you want your menu with a permission rules
            'target'   => '',
            'user'     => 0, // 0=Menu for internal users, 1=external users, 2=both
        ];

        $menuEnabled = ($conf->browser->layout != 'classic') ? 1 : 0;

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=easycrm', // '' if this is a top menu. For left menu, use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
            'type'     => 'left', // This is a Top menu entry
            'titre'    => $langs->transnoentities('QuickCreation'),
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'easycrm',
            'leftmenu' => 'quickcreationfrontend',
            'url'      => '/easycrm/view/frontend/quickcreation.php', // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => $menuEnabled, // Define condition to show or hide menu entry. Use '$conf->easycrm->enabled' if entry must be visible if module is enabled.
            'perms'    => '$user->rights->easycrm->read', // Use 'perms'=>'$user->rights->easycrm->myobject->read' if you want your menu with a permission rules
            'target'   => '',
            'user'     => 0, // 0=Menu for internal users, 1=external users, 2=both
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=easycrm',
            'type'     => 'left',
            'titre'    => $langs->trans('Tools'),
            'prefix'   => '<i class="fas fa-wrench pictofixedwidth"></i>',
            'mainmenu' => 'easycrm',
            'leftmenu' => 'easycrmtools',
            'url'      => '/easycrm/view/easycrmtools.php',
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => '$conf->easycrm->enabled',
            'perms'    => '$user->rights->easycrm->adminpage->read',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=project,fk_leftmenu=projects',
            'type'     => 'left',
            'titre'    => '<i class="fas fa-map-marked-alt pictofixedwidth" style="padding-right: 4px; color: #63ACC9;"></i>' . $langs->transnoentities('Map'),
            'leftmenu' => 'map',
            'url'      => 'easycrm/view/map.php?from_type=project',
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => '$conf->easycrm->enabled',
            'perms'    => '$user->rights->easycrm->address->read',
            'target'   => '',
            'user'     => 0,
        ];
    }

    /**
     *  Function called when module is enabled.
     *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     *  It also creates data directories
     *
     * @param  string    $options Options when enabling module ('', 'noboxes')
     * @return int                1 if OK, 0 if KO
     * @throws Exception
     */
	public function init($options = ''): int
    {
		global $conf, $langs, $user;

        if ($this->error > 0) {
            setEventMessages('', $this->errors, 'errors');
            return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
        }

        $sql = [];

		// Load sql sub folders
		$sqlFolder = scandir(__DIR__ . '/../../sql');
		foreach ($sqlFolder as $subFolder) {
			if ( ! preg_match('/\./', $subFolder)) {
				$this->_load_tables('/easycrm/sql/' . $subFolder . '/');
			}
		}

		$result = $this->_load_tables('/easycrm/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

        dolibarr_set_const($this->db, 'EASYCRM_VERSION', $this->version, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'EASYCRM_DB_VERSION', $this->version, 'chaine', 0, '', $conf->entity);

        // Create extrafields during init
        include_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
        $extrafields = new ExtraFields($this->db);

        $objectsMetadata = saturne_get_objects_metadata();

        $extrafields->addExtraField('commrelaunch', $langs->transnoentities('CommercialsRelaunching'), 'text', 100, 2000, 'projet', 0, 0, '', '', 0, '', 2);
        $extrafields->update('commtask', $langs->transnoentities('CommercialTask'), 'sellist', '', 'projet', 0, 0, 100, 'a:1:{s:7:"options";a:1:{s:21:"projet_task:ref:rowid";N;}}', 1, '', 4);
        $extrafields->addExtraField('commtask', $langs->transnoentities('CommercialTask'), 'sellist', 100, '', 'projet', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:21:"projet_task:ref:rowid";N;}}', 1, '', 4);
        $extrafields->addExtraField('projectphone', $langs->transnoentities('ProjectPhone'), 'phone', 100, '', 'projet', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:0:"";N;}}', 1, '', 1);
		$extrafields->addExtraField('commstatus', $langs->transnoentities('CommercialStatus'), 'sellist', 100, '', 'propal', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:34:"c_commercial_status:label:rowid::1";N;}}', 1, '', 1, 'CommercialStatusHelp');
		$extrafields->addExtraField('commrefusal', $langs->transnoentities('RefusalReason'), 'sellist', 100, '', 'propal', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:31:"c_refusal_reason:label:rowid::1";N;}}', 1, '', 1, 'RefusalReasonHelp');
        $extrafields->addExtraField('opporigin', $langs->transnoentities('OpportunityOrigin'), 'sellist', 100, '', 'projet', 0, 0, '', 'a:1:{s:7:"options";a:1:{s:19:"c_input_reason:code";N;}}', 1, '', 1);

        // Societe extrafields
        $extrafields->update('notation_societe_contact', 'NotationObjectContact', 'text', '', 'societe', 0, 0, 100, '', '', '', 5, 'NotationObjectContactHelp', '', '', 0, 'easycrm@easycrm', 1, 0, 0, ['csslist' => 'center']);
        $extrafields->addExtraField('notation_societe_contact', 'NotationObjectContact', 'text', 100, '', 'societe', 0, 0, '', '', '', '', 5, 'NotationObjectContactHelp', '', 0, 'easycrm@easycrm', 1, 0, 0, ['csslist' => 'center']);

        // Facture extrafields
        $extrafields->update('notation_facture_contact', 'NotationObjectContact', 'text', '', 'facture', 0, 0, 100, '', '', '', 5, 'NotationObjectContactHelp', '', '', 0, 'easycrm@easycrm', 1, 0, 0, ['csslist' => 'center']);
        $extrafields->addExtraField('notation_facture_contact', 'NotationObjectContact', 'text', 100, '', 'facture', 0, 0, '', '', '', '', 5, 'NotationObjectContactHelp', '', 0, 'easycrm@easycrm', 1, 0, 0, ['csslist' => 'center']);

        // Facturerec extrafields
        $extrafields->update('notation_facturerec_contact', 'NotationObjectContact', 'text', '', 'facture_rec', 0, 0, 100, '', '', '', 5, 'NotationObjectContactHelp', '', '', 0, 'easycrm@easycrm', 1, 0, 0, ['csslist' => 'center']);
        $extrafields->addExtraField('notation_facturerec_contact', 'NotationObjectContact', 'text', 100, '', 'facture_rec', 0, 0, '', '', '', '', 5, 'NotationObjectContactHelp', '', 0, 'easycrm@easycrm', 1, 0, 0, ['csslist' => 'center']);

        if (is_array($objectsMetadata) && !empty($objectsMetadata)) {
            foreach ($objectsMetadata as $objectType => $objectMetadata) {
                $extrafieldParam     = 'easycrm_address:name:rowid::element_id=$ID$ AND element_type="' . $objectType . '" AND status>0';
                $extrafieldParamSize = dol_strlen($extrafieldParam);
                $extrafields->update($objectType . 'address', 'FavoriteAddress', 'sellist', 255, $objectMetadata['table_element'], 0, 0, 101, 'a:1:{s:7:"options";a:1:{s:' . $extrafieldParamSize . ':"' . $extrafieldParam .'";N;}}', 1, '$user->rights->easycrm->address->write', 1, '', '', '', '', 'easycrm@easycrm', '1', 0, 0, ['css => minwidth100 maxwidth300 widthcentpercentminusx']);
                $extrafields->addExtraField($objectType . 'address', 'FavoriteAddress', 'sellist', 101, 255, $objectMetadata['table_element'], 0, 0, '', 'a:1:{s:7:"options";a:1:{s:' . $extrafieldParamSize . ':"' . $extrafieldParam .'";N;}}', 1, '$user->rights->easycrm->address->write', 1, '', '', '', 'easycrm@easycrm', '1', 0, 0, ['css => minwidth100 maxwidth300 widthcentpercentminusx']);
            }
        }
        if (empty($conf->global->EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG)) {
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

            $category = new Categorie($this->db);

            $category->label = $langs->trans('CommercialRelaunching');
            $category->type  = 'actioncomm';
            $categoryID      = $category->create($user);

            dolibarr_set_const($this->db, 'EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG', $categoryID, 'integer', 0, '', $conf->entity);
        }

		// Permissions
		$this->remove($options);

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *  Remove from database constants, boxes and permissions from Dolibarr database.
	 *  Data directories are not deleted
	 *
	 *  @param      string	$options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, 0 if KO
	 */
	public function remove($options = ''): int
    {
		$sql = [];
		return $this->_remove($sql, $options);
	}
}

<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \defgroup easycrm     Module EasyCRM
 * \brief    EasyCRM module descriptor
 *
 * \file    core/modules/modEasyCRM.class.php
 * \ingroup easycrm
 * \brief   Description and activation file for module EasyCRM
 */

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module EasyCRM
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

        parent::__construct($db);

        if (file_exists(__DIR__ . '/../../../saturne/lib/saturne_functions.lib.php')) {
            require_once __DIR__ . '/../../../saturne/lib/saturne_functions.lib.php';
            saturne_load_langs(['easycrm@easycrm']);
        } else {
            $this->error++;
            $this->errors[] = $langs->trans('activateModuleDependNotSatisfied', 'EasyCRM', 'Saturne');
        }

        // ID for module (must be unique)
        $this->numero = 436351;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'easycrm';

        // Family can be 'base' (core modules),'crm','financial','hr','projects','products','ecm','technic' (transverse modules),'interface' (link with external tools),'other', 'etc.'
        // It is used to group modules by family in module setup page
        $this->family = '';

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '';

        // Gives the possibility for the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        $this->familyinfo = ['Eoxia' => ['position' => '01', 'label' => 'Eoxia']];
        // Module label (no space allowed), used if translation string 'ModuleEasyCRMName' not found (EasyCRM is name of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // DESCRIPTION_FLAG
        // Module description, used if translation string 'ModuleEasyCRMDesc' not found (EasyCRM is name of module)
        $this->description = $langs->transnoentities('EasyCRMDescription');
        // Used only if file README.md and README-LL.md not found
        $this->descriptionlong = $langs->transnoentities('EasyCRMDescription');

        // Author
        $this->editor_name = 'Eoxia';
        $this->editor_url = 'https://www.eoxia.com';
        //$this->editor_squarred_logo = ''; // Must be image filename into the easycrm/img directory followed with @easycrm. Example: 'easycrm.png@easycrm'

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
        $this->version = '1.5.0';

        // Url to the file with your last numberversion of this module
        //$this->url_last_version = 'http://www.example.com/versionmodule.txt';

        // Key used in llx_const table to save module status enabled/disabled (where EASYCRM is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        // Name of image file used for this module
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
            // Set here all hooks context managed by module. To find available hook context, make a "grep -r '>initHooks(' *" on source code. You can also set hook context to 'all')
            /* BEGIN MODULEBUILDER HOOKSCONTEXTS */
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
            /* END MODULEBUILDER HOOKSCONTEXTS */
            // Set this to 1 if features of module are opened to external users
            'moduleforexternal' => 0,
            // Set this to 1 if the module provides a website template into doctemplates/websites/website_template-mytemplate
            'websitetemplates' => 0,
            // Set this to 1 if the module provides a captcha driver
            'captcha' => 0
        ];

        // Data directories to create when module is enabled
        $this->dirs = ['/easycrm/temp'];

        // Config pages. Put here list of php page, stored into easycrm/admin directory, to use to set up module
        $this->config_page_url = ['setup.php@easycrm'];

        // Dependencies
        // A condition to hide module
        $this->hidden = getDolGlobalInt('MODULE_' . strtoupper($this->name) . '_DISABLED'); // A condition to disable module
        // List of module class names as string that must be enabled if this module is enabled. Example: array('always1'=>'modModuleToEnable1','always2'=>'modModuleToEnable2', 'FR1'=>'modModuleToEnableFR'...)
        $this->depends = ['modSaturne', 'modFckeditor', 'modAgenda', 'modSociete', 'modProjet', 'modCategorie', 'modPropale', 'modCron'];
        // List of module class names as string to disable if this one is disabled. Example: array('modModuleToDisable1', ...)
        $this->requiredby = [];
        // List of module class names as string this module is in conflict with. Example: array('modModuleToDisable1', ...)
        $this->conflictwith = [];

        // The language file dedicated to your module
        $this->langfiles = ['easycrm@easycrm'];

        // Prerequisites
        $this->phpmin                  = [7, 4];  // Minimum version of PHP required by module
        // $this->phpmax               = [8, 0];  // Maximum version of PHP required by module
        $this->need_dolibarr_version   = [20, 0]; // Minimum version of Dolibarr required by module
        // $this->max_dolibarr_version = [21, 0]; // Maximum version of Dolibarr required by module
        $this->need_javascript_ajax    = 0;

        // Messages at activation
        $this->warnings_activation     = []; // Warning to show when we activate module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        $this->warnings_activation_ext = []; // Warning to show when we activate an external module. array('always'='text') or array('FR'='textfr','MX'='textmx'...)
        //$this->automatic_activation  = ['FR'=>'EasyCRMWasAutomaticallyActivatedBecauseOfYourCountryChoice'];
        //$this->always_enabled        = true; // If true, can't be disabled

        // Constants
        // List of particular constants to add when module is enabled (key, 'chaine', value, desc, visible, 'current' or 'allentities', deleteonunactive)
        $i           = 0;
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
            $i++ => ['EASYCRM_EVENT_LABEL_MAX_LENGTH_VALUE', 'integer', 128, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_DATE_START_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_DATE_END_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_STATUS_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_STATUS_VALUE', 'integer', -1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_DESCRIPTION_VISIBLE', 'integer', 1, '', 0, 'current'],
            $i++ => ['EASYCRM_EVENT_CATEGORIES_VISIBLE', 'integer', 1, '', 0, 'current'],

            // CONST PWA
            $i++ => ['EASYCRM_PWA_CLOSE_PROJECT_WHEN_OPPORTUNITY_ZERO', 'integer', 0, '', 0, 'current'],

            // CONST ADDRESS
            //$i++ => ['EASYCRM_DISPLAY_MAIN_ADDRESS', 'integer', 0, '', 0, 'current'],
            $i++ => ['EASYCRM_ADDRESS_ADDON', 'chaine', 'mod_address_standard', '', 0, 'current'],

            // CONST MODULE
            $i++ => ['EASYCRM_VERSION','chaine', $this->version, '', 0, 'current'],
            $i++ => ['EASYCRM_DB_VERSION', 'chaine', $this->version, '', 0, 'current'],
            $i++ => ['EASYCRM_SHOW_PATCH_NOTE', 'integer', 1, '', 0, 'current'],
            $i   => ['EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG', 'integer', 0, '', 0, 'current']
        ];

        // Some keys to add into the overwriting translation tables
        $this->overwrite_translation = [
            'fr_FR:ActionAC_EMAIL_IN' => 'Email entrant',
            'fr_FR:ActionAC_EMAIL'    => 'Email sortant',
            'fr_FR:ActionAC_RDV'      => 'Rendez-vous physique ou visioconférence'
        ];

        if (!isModEnabled('easycrm')) {
            $conf->easycrm = new stdClass();
            $conf->easycrm->enabled = 0;
        }

        // Array to add new pages in new tabs
        /* BEGIN MODULEBUILDER TABS */
        $pictoPath    = dol_buildpath('custom/easycrm/img/easycrm_color.png', 1);
        $pictoEasycrm = img_picto('', $pictoPath, '', 1, 0, 0, '', 'pictoModule');
        $this->tabs   = [];
        $this->tabs[] = ['data' => 'project' . ':+address:' . $pictoEasycrm . $langs->transnoentities('Addresses') . ':easycrm@easycrm:$user->hasRight(\'easycrm\', \'address\', \'read\'):/custom/easycrm/view/address_card.php?from_id=__ID__&from_type=project'];
        $this->tabs[] = ['data' => 'project' . ':+map:' . $pictoEasycrm . $langs->transnoentities('Map') . ':easycrm@easycrm:$user->hasRight(\'project\', \'read\'):/custom/easycrm/view/map.php?from_id=__ID__&from_type=project'];
        /* END MODULEBUILDER TABS */

        // Dictionaries
        /* BEGIN MODULEBUILDER DICTIONARIES */
        $this->dictionaries = [
            'langs' => 'easycrm@easycrm',
            // List of tables we want to see into dictionary editor
            'tabname' => [
                MAIN_DB_PREFIX . 'c_commercial_status',
                MAIN_DB_PREFIX . 'c_refusal_reason',
                MAIN_DB_PREFIX . 'c_address_type'
            ],
            // Label of tables
            'tablib' => [
                'CommercialStatus',
                'RefusalReason',
                'AddressType'
            ],
            // Request to select fields
            'tabsql' => [
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.element_type, f.active, f.position FROM ' . $this->db->prefix() . 'c_commercial_status as f',
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.element_type, f.active, f.position FROM ' . $this->db->prefix() . 'c_refusal_reason as f',
                'SELECT f.rowid as rowid, f.ref, f.label, f.description, f.active, f.position FROM ' . $this->db->prefix() . 'c_address_type as f'
            ],
            // Sort order
            'tabsqlsort' => [
                'position ASC',
                'position ASC',
                'position ASC'
            ],
            // List of fields (result of select to show dictionary)
            'tabfield' => [
                'ref,label,description,element_type,position',
                'ref,label,description,element_type,position',
                'ref,label,description,position'
            ],
            // List of fields (list of fields to edit a record)
            'tabfieldvalue' => [
                'ref,label,description,element_type,position',
                'ref,label,description,element_type,position',
                'ref,label,description,position'
            ],
            // List of fields (list of fields for insert)
            'tabfieldinsert' => [
                'ref,label,description,element_type,position',
                'ref,label,description,element_type,position',
                'ref,label,description,position'
            ],
            // Name of columns with primary key (try to always name it 'rowid')
            'tabrowid' => [
                'rowid',
                'rowid',
                'rowid'
            ],
            // Condition to show each dictionary
            'tabcond' => [
                isModEnabled('easycrm'),
                isModEnabled('easycrm'),
                isModEnabled('easycrm')
            ]
        ];

        // Boxes/Widgets
        // Add here list of php file(s) stored in priseo/core/boxes that contains a class to show a widget
        /* BEGIN MODULEBUILDER WIDGETS */
        $this->boxes = [];
        /* END MODULEBUILDER WIDGETS */

        // Cronjobs (List of cron jobs entries to add when module is enabled)
        // unit_frequency must be 60 for minute, 3600 for hour, 86400 for day, 604800 for week
        /* BEGIN MODULEBUILDER CRON */
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
                'test'          => 'isModEnabled(\'saturne\') && isModEnabled(\'easycrm\') && isModEnabled(\'invoice\')',
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
                'test'          => 'isModEnabled(\'saturne\') && isModEnabled(\'easycrm\') && isModEnabled(\'societe\')',
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
                'test'          => 'isModEnabled(\'saturne\') && isModEnabled(\'easycrm\') && isModEnabled(\'societe\')',
                'priority'      => 50
            ]
        ];
        /* END MODULEBUILDER CRON */

        // Permissions provided by this module
        $this->rights = [];
        $r = 0;
        /* BEGIN MODULEBUILDER PERMISSIONS */

        /* EASYCRM PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadModule', $this->name);
        $this->rights[$r][4] = 'read';
        $r++;

        /* ADDRESS PERMISSSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadObjects',$langs->transnoentities('Address'));
        $this->rights[$r][4] = 'address';
        $this->rights[$r][5] = 'read';
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('CreateObjects', $langs->transnoentities('Address'));
        $this->rights[$r][4] = 'address';
        $this->rights[$r][5] = 'write';
        $r++;
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('DeleteObjects', $langs->transnoentities('Address'));
        $this->rights[$r][4] = 'address';
        $this->rights[$r][5] = 'delete';
        $r++;

        /* ADMINPAGE PANEL ACCESS PERMISSIONS */
        $this->rights[$r][0] = $this->numero . sprintf('%02d', $r + 1);
        $this->rights[$r][1] = $langs->transnoentities('ReadAdminPage', $this->name);
        $this->rights[$r][4] = 'adminpage';
        $this->rights[$r][5] = 'read';

        /* END MODULEBUILDER PERMISSIONS */

        // Main menu entries to add
        $this->menu = [];
        $r = 0;

        // Add here entries to declare new menus
        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=easycrm',
            'type'     => 'top',
            'titre'    => 'EasyCRM',
            'prefix'   => '<i class="fas fa-home pictofixedwidth"></i>',
            'mainmenu' => 'easycrm',
            'leftmenu' => '',
            'url'      => '/easycrm/easycrmindex.php',
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'easycrm\')',
            'perms'    => '$user->hasRight(\'easycrm\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=easycrm',
            'type'     => 'left',
            'titre'    => $langs->transnoentities('QuickCreation'),
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'easycrm',
            'leftmenu' => 'quickcreation',
            'url'      => '/easycrm/view/quickcreation.php',
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'easycrm\')',
            'perms'    => '$user->hasRight(\'easycrm\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];

        $menuEnabled = ($conf->browser->layout != 'classic') ? 1 : 0;

        $this->menu[$r++] = [
            'fk_menu'  => 'fk_mainmenu=easycrm',
            'type'     => 'left',
            'titre'    => $langs->transnoentities('QuickCreation'),
            'prefix'   => '<i class="fas fa-plus-circle pictofixedwidth"></i>',
            'mainmenu' => 'easycrm',
            'leftmenu' => 'quickcreationfrontend',
            'url'      => '/easycrm/view/frontend/quickcreation.php',
            'langs'    => 'easycrm@easycrm',
            'position' => 1000 + $r,
            'enabled'  => 'isModEnabled(\'easycrm\') && ' . $menuEnabled,
            'perms'    => '$user->hasRight(\'easycrm\', \'read\')',
            'target'   => '',
            'user'     => 0,
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
            'enabled'  => 'isModEnabled(\'easycrm\')',
            'perms'    => '$user->hasRight(\'easycrm\', \'adminpage\', \'read\')',
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
            'enabled'  => 'isModEnabled(\'easycrm\')',
            'perms'    => '$user->hasRight(\'easycrm\', \'address\', \'read\')',
            'target'   => '',
            'user'     => 0,
        ];
    }

    /**
     * Function called when module is enabled
     * The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database
     * It also creates data directories
     *
     * @param  string    $options Options when enabling module ('', 'noboxes')
     * @return int                1 if OK, 0 if KO
     * @throws Exception
     */
    public function init($options = ''): int
    {
        global $conf, $langs, $user;

        // Load sql sub folders
        $sqlFolder = scandir(__DIR__ . '/../../sql');
        foreach ($sqlFolder as $subFolder) {
            if (!preg_match('/\./', $subFolder)) {
                $this->_load_tables('/easycrm/sql/' . $subFolder . '/');
            }
        }

        // Create tables of module at module activation
        $result = $this->_load_tables('/easycrm/sql/');
        if ($result < 0) {
            return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
        }

        dolibarr_set_const($this->db, 'EASYCRM_VERSION', $this->version, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($this->db, 'EASYCRM_DB_VERSION', $this->version, 'chaine', 0, '', $conf->entity);

        $commonExtraFieldsValue = ['entity' => 0, 'langfile' => 'easycrm@easycrm'];

        $extraFieldsArrays = [
            'commrelaunch'   => ['Label' => 'CommercialsRelaunching', 'type' => 'text', 'length' => 2000,   'elementtype' => ['projet'], 'position' => $this->numero . 10, 'list' => 2, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'project\')'],
            'commtask'       => ['Label' => 'CommercialTask',         'type' => 'sellist',                  'elementtype' => ['projet'], 'position' => $this->numero . 20, 'list' => 4, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'project\')', 'alwayseditable' => 1, 'params' => ['projet_task:ref:rowid' => null]],
            'projectphone'   => ['Label' => 'ProjectPhone',           'type' => 'phone',                    'elementtype' => ['projet'], 'position' => $this->numero . 30, 'list' => 1, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'project\')', 'alwayseditable' => 1],
            'opporigin'      => ['Label' => 'OpportunityOrigin',      'type' => 'sellist',                  'elementtype' => ['projet'], 'position' => $this->numero . 40, 'list' => 1, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'project\')', 'alwayseditable' => 1, 'params' => ['c_input_reason:label:rowid' => null]],
            'projectaddress' => ['Label' => 'FavoriteAddress',        'type' => 'sellist',                  'elementtype' => ['projet'], 'position' => $this->numero . 50, 'list' => 1, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'project\')', 'alwayseditable' => 1, 'params' => ['easycrm_address:name:rowid::((element_type:=:\'project\') AND (status:=:1))' => null], 'perms' => '$user->hasRight(\'easycrm\', \'address\', \'write\')', 'moreparams' => ['css' => 'minwidth100 maxwidth300 widthcentpercentminusx']],

            'commstatus'  => ['Label' => 'CommercialStatus', 'type' => 'sellist', 'elementtype' => ['propal'], 'position' => $this->numero . 10, 'list' => 1, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'propal\')', 'alwayseditable' => 1, 'params' => ['c_commercial_status:label:rowid' => null], 'help' => 'CommercialStatusHelp'],
            'commrefusal' => ['Label' => 'RefusalReason',    'type' => 'sellist', 'elementtype' => ['propal'], 'position' => $this->numero . 20, 'list' => 1, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'propal\')', 'alwayseditable' => 1, 'params' => ['c_refusal_reason:label:rowid' => null],    'help' => 'RefusalReasonHelp'],

            'notation_societe_contact'    => ['Label' => 'NotationObjectContact', 'type' => 'text', 'elementtype' => ['societe'],     'position' => $this->numero . 10, 'list' => 5, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'societe\')',  'help' => 'NotationObjectContactHelp', 'moreparams' => ['csslist' => 'center']],
            'notation_facture_contact'    => ['Label' => 'NotationObjectContact', 'type' => 'text', 'elementtype' => ['facture'],     'position' => $this->numero . 10, 'list' => 5, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'invoice\')',  'help' => 'NotationObjectContactHelp', 'moreparams' => ['csslist' => 'center']],
            'notation_facturerec_contact' => ['Label' => 'NotationObjectContact', 'type' => 'text', 'elementtype' => ['facture_rec'], 'position' => $this->numero . 10, 'list' => 5, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'invoice\')',  'help' => 'NotationObjectContactHelp', 'moreparams' => ['csslist' => 'center']],

            'address_status' => ['Label' => 'AddressStatus', 'type' => 'select', 'elementtype' => ['contact'], 'position' => $this->numero . 10, 'list' => 5, 'enabled' => 'isModEnabled(\'easycrm\') && isModEnabled(\'societe\')', 'params' => ['NotFound', 'Geolocated']]
        ];

        saturne_manage_extrafields($extraFieldsArrays, $commonExtraFieldsValue);

        $objectsMetadata = saturne_get_objects_metadata();
        if (!empty($objectsMetadata)) {
            require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

            $extrafields = new ExtraFields($this->db);

            foreach ($objectsMetadata as $objectType => $objectMetadata) {
                if ($objectType != 'project') {
                    // Backward compatibility
                    if ($objectType == 'entrepot') {
                        $objectType = 'warehouse';
                    }
                    $extrafields->delete($objectType . 'address', $objectMetadata['table_element']);
                }
            }
        }

        if (getDolGlobalInt('EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG') == 0) {
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

            $category = new Categorie($this->db);

            $category->label = $langs->transnoentities('CommercialRelaunching');
            $category->type  = 'actioncomm';
            $categoryID      = $category->create($user);

            dolibarr_set_const($this->db, 'EASYCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG', $categoryID, 'integer', 0, '', $conf->entity);
        }

        if (getDolGlobalInt('EASYCRM_ADDRESS_BACKWARD_COMPATIBILITY') == 0) {
            require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
            require_once __DIR__ . '/../../class/geolocation.class.php';
            require_once __DIR__ . '/../../class/address.class.php';

            $contact     = new Contact($this->db);
            $category    = new Categorie($this->db);
            $address     = new Address($this->db);
            $geolocation = new Geolocation($this->db);

            $addresses = $address->fetchAll('', '', 0, 0, ['customsql' => ' status > 0 AND latitude > 0 AND longitude > 0']);
            if (is_array($addresses) && !empty($addresses)) {
                $categoryId = saturne_create_category($langs->transnoentities('ProjectAddress'), 'contact', 0, '', '', $langs->transnoentities('ProjectAddress'));
                $category->fetch($categoryId);

                foreach ($addresses as $address) {
                    $contact->lastname   = $address->name;
                    $contact->address    = $address->address;
                    $contact->fk_project = $address->element_id;
                    $contact->fk_pays    = $address->fk_country;
                    $contact->zip        = $address->zip;
                    $contact->town       = $address->town;

                    $contactID = $contact->create($user);
                    $category->add_type($contact);

                    $geolocation->element_type = 'contact';
                    $geolocation->latitude     = $address->latitude;
                    $geolocation->longitude    = $address->longitude;
                    $geolocation->fk_element   = $contactID;
                    $geolocation->gis          = 'osm';
                    if ($address->latitude <= 0 && $address->longitude <= 0) {
                        $geolocation->status = Geolocation::STATUS_NOTFOUND;
                    } else {
                        $geolocation->status = Geolocation::STATUS_GEOLOCATED;
                    }

                    $contact->array_options['options_address_status'] = $geolocation->status;
                    $contact->updateExtraField('address_status');
                    $geolocation->create($user);
                }
                dolibarr_set_const($this->db, 'EASYCRM_ADDRESS_MAIN_CATEGORY', $categoryId, 'integer', 0, '', $conf->entity);
                dolibarr_set_const($this->db, 'EASYCRM_ADDRESS_BACKWARD_COMPATIBILITY', 1, 'integer', 0, '', $conf->entity);
            }
        }

        // Permissions
        $this->remove($options);

        return $this->_init([], $options);
    }
}

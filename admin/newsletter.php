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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    admin/newsletter.php
 * \ingroup reedcrm
 * \brief   ReedCRM newsletter config page.
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';

require_once __DIR__ . '/../lib/reedcrm.lib.php';
require_once __DIR__ . '/../class/reedcrmnewsletter.class.php';

// Global variables definitions
global $conf, $db, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['admin', 'mails']);

// Get parameters
$action     = GETPOST('action', 'alpha');
$backtopage = GETPOST('backtopage', 'alpha');

// Security check - Protection if external user
$permissiontoread = $user->hasRight('reedcrm', 'adminpage', 'read');

saturne_check_access($permissiontoread);

$moduleName          = 'ReedCRM';
$moduleNameLowerCase = 'reedcrm';
$object              = new ReedcrmNewsletter($db);

require_once dol_buildpath('/saturne/core/tpl/actions/admin_conf_actions.tpl.php');

/*
 * Actions
 */

if ($action == 'set_config' && $permissiontoread) {
    dolibarr_set_const($db, 'REEDCRM_NEWSLETTER_MAX_PER_RUN', max(1, GETPOSTINT('max_per_run')), 'integer', 0, '', $conf->entity);

    setEventMessage('SavedConfig');
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

/*
 * View
 */

$title    = $langs->trans('ModuleSetup', 'ReedCRM');
$help_url = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $help_url);

// Subheader
$linkback = '<a href="' . ($backtopage ?: DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans('BackToModuleList') . '</a>';
print load_fiche_titre($title, $linkback, 'reedcrm_color@reedcrm');

// Configuration header
$head = reedcrm_admin_prepare_head();
print dol_get_fiche_head($head, 'newsletter', $title, -1, 'reedcrm_color@reedcrm');

print load_fiche_titre($langs->trans('Configs', $langs->trans('Newsletters')), '', '');

// Numbering Module
unset($documentPath);
unset($objectModSubdir);
require_once dol_buildpath('/saturne/core/tpl/admin/object/object_numbering_module_view.tpl.php');

$form              = new Form($db);
$emailTemplatesUrl = DOL_URL_ROOT . '/admin/mails_templates.php?search_type_template=' . ReedcrmNewsletter::EMAIL_TEMPLATE_TYPE;
$cronListUrl       = DOL_URL_ROOT . '/cron/list.php?search_label=' . urlencode($langs->transnoentities('NewsletterCronLabel'));
$maxPerRun         = getDolGlobalInt('REEDCRM_NEWSLETTER_MAX_PER_RUN', 100);
$cronFrequency     = reedcrm_newsletter_cron_frequency_text($db, $langs);

// What a deployment on an existing installation may miss: the module is activated again to create the job, the
// tables and the rights, and the rights are given by hand. Said here rather than found out by the users
$deploymentWarnings = [];
$resql              = $db->query('SELECT status FROM ' . MAIN_DB_PREFIX . "cronjob WHERE methodename = 'sendScheduledNewsletters' AND entity IN (0, " . ((int) $conf->entity) . ')');
$cronJob            = $resql ? $db->fetch_object($resql) : null;
if (!$cronJob) {
    $deploymentWarnings[] = $langs->trans('NewsletterDeployNoCron');
} elseif ((int) $cronJob->status !== 1) {
    $deploymentWarnings[] = $langs->trans('NewsletterDeployCronDisabled');
}
$sql    = 'SELECT COUNT(DISTINCT ur.fk_user) as nb FROM ' . MAIN_DB_PREFIX . 'user_rights as ur';
$sql   .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'rights_def as rd ON rd.id = ur.fk_id';
$sql   .= " WHERE rd.module = 'reedcrm' AND rd.perms = 'newsletter' AND rd.subperms = 'send' AND ur.entity = " . ((int) $conf->entity);
$resql  = $db->query($sql);
$rights = $resql ? $db->fetch_object($resql) : null;
$resql  = $db->query('SELECT COUNT(DISTINCT gr.fk_usergroup) as nb FROM ' . MAIN_DB_PREFIX . 'usergroup_rights as gr INNER JOIN ' . MAIN_DB_PREFIX . "rights_def as rd ON rd.id = gr.fk_id WHERE rd.module = 'reedcrm' AND rd.perms = 'newsletter' AND rd.subperms = 'send'");
$groups = $resql ? $db->fetch_object($resql) : null;
if ((!$rights || (int) $rights->nb === 0) && (!$groups || (int) $groups->nb === 0)) {
    $deploymentWarnings[] = $langs->trans('NewsletterDeployNoRights');
}

// Whether the job really runs: the page says "every 15 minutes" from the setting of the job, its last run says if the
// host calls it at all
$cronLastRun = reedcrm_newsletter_cron_last_run($db);
$cronPeriod  = reedcrm_newsletter_cron_period($db);
if ($cronLastRun !== null && $cronLastRun['status'] === 1 && isModEnabled('cron')) {
    if ($cronLastRun['last'] <= 0) {
        $deploymentWarnings[] = $langs->trans('NewsletterDeployCronNeverRun');
    } elseif ($cronPeriod > 0 && $cronLastRun['last'] < dol_now() - 3 * max($cronPeriod, 900)) {
        $deploymentWarnings[] = $langs->trans('NewsletterDeployCronLate', dol_print_date($cronLastRun['last'], 'dayhour'));
    }
}
// A sender left to the example of the installation: the mails would leave from nobody
if (ReedcrmNewsletter::isPlaceholderAddress(getDolGlobalString('MAILING_EMAIL_FROM')) && ReedcrmNewsletter::isPlaceholderAddress(getDolGlobalString('MAIN_MAIL_EMAIL_FROM')) && empty($mysoc->email)) {
    $deploymentWarnings[] = $langs->trans('NewsletterDeploySenderPlaceholder');
}

require __DIR__ . '/../core/tpl/newsletter/newsletter_admin_setup.tpl.php';

// Page end
print dol_get_fiche_end();
llxFooter();
$db->close();

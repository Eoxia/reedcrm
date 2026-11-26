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
 * \file    public/newsletter/unsubscribe.php
 * \ingroup reedcrm
 * \brief   Public page of the unsubscribe link of the newsletters
 *
 * The link carries the address and a key computed from it: only the mail received unsubscribes its address. A GET
 * shows a button, so that the link scanners of the mail servers unsubscribe nobody; the mail clients that read the
 * List-Unsubscribe header POST "List-Unsubscribe=One-Click" (RFC 8058) and are answered without a page. The link of
 * a test mail (test=1) unsubscribes nobody, it explains itself
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', 1);
}
if (!defined('NOLOGIN')) {     // This means this output page does not require to be logged
    define('NOLOGIN', 1);
}
if (!defined('NOCSRFCHECK')) { // The mail clients POST the one click unsubscribe without a token, the key guards the page
    define('NOCSRFCHECK', 1);
}
if (!defined('NOIPCHECK')) {   // Do not check IP defined into conf $dolibarr_main_restrict_ip
    define('NOIPCHECK', 1);
}
if (!defined('NOBROWSERNOTIF')) {
    define('NOBROWSERNOTIF', 1);
}

// The entity of the newsletter, needed before the configuration is loaded, as on the public pages of Dolibarr
$entity = (!empty($_GET['entity']) && is_scalar($_GET['entity']) ? (int) $_GET['entity'] : (!empty($_POST['entity']) && is_scalar($_POST['entity']) ? (int) $_POST['entity'] : 1));
if ($entity > 0) {
    define('DOLENTITY', $entity);
}

// Dolibarr environment, without saturne.main.inc.php: its check of the modules sends anyone to the login page of
// Dolibarr when ReedCRM is off in the entity of the link. A recipient gets "invalid link" instead
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/main.inc.php';
}
if (!$res && file_exists(__DIR__ . '/../../../../main.inc.php')) {
    $res = @include __DIR__ . '/../../../../main.inc.php';
}
if (!$res && file_exists(__DIR__ . '/../../../main.inc.php')) {
    $res = @include __DIR__ . '/../../../main.inc.php';
}
if (!$res) {
    die('Include of main fails');
}

$moduleName          = 'ReedCRM';
$moduleNameLowerCase = 'reedcrm';
$moduleNameUpperCase = 'REEDCRM';
dol_include_once('/saturne/lib/saturne_functions.lib.php');

require_once __DIR__ . '/../../lib/reedcrm_newsletter.lib.php';

global $conf, $db, $hookmanager, $langs;

$langs->loadLangs(['main', 'mails', 'reedcrm@reedcrm']);

// An array in a parameter (email[]=…) is no address and no key: read as empty rather than passed to trim()
$readString = function (string $name, string $filter): string {
    $value = GETPOST($name, $filter);
    return is_string($value) ? trim($value) : '';
};

$email     = $readString('email', 'alphanohtml');
$key       = $readString('key', 'aZ09');
$action    = $readString('action', 'aZ09');
$isTest    = $readString('test', 'aZ09') === '1';
// Newsletter of the mail whose link was clicked: the unsubscribe is counted in its results
$newsletterId = max(0, (int) $readString('nl', 'int'));
$oneClick  = $readString('List-Unsubscribe', 'alpha') === 'One-Click';
$isPost    = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$entity    = (int) $conf->entity;
$isValid   = isModEnabled('reedcrm') && isValidEmail($email) && $key !== '' && hash_equals(reedcrm_newsletter_unsubscribe_key($email, $entity), $key);
$since     = 0;
$pageState = $isValid ? 'confirm' : 'invalid';
if ($isTest && isModEnabled('reedcrm')) {
    $pageState = 'test';
} elseif ($isValid && !$isPost) {
    // A link opened again: the address is already out, the page says so instead of asking again
    $since = reedcrm_newsletter_unsubscribed_since($db, $email, $entity);
    if ($since > 0) {
        $pageState = 'already';
    }
}

$hookmanager->initHooks(['reedcrmnewsletterunsubscribe', 'saturnepublicinterface']);

/*
 * Actions
 */

if ($isValid && $isPost && ($oneClick || $action === 'unsubscribe')) {
    $result    = reedcrm_newsletter_unsubscribe($db, $email, $entity);
    $pageState = $result >= 0 ? 'done' : 'error';
    // Only a new unsubscribe counts: an address already out clicking again changes no result
    if ($result === 1) {
        reedcrm_newsletter_record_unsubscribe($db, $newsletterId, $email, $entity);
    }

    if ($oneClick) {
        http_response_code($result >= 0 ? 200 : 500);
        header('Content-Type: text/plain; charset=utf-8');
        print $result >= 0 ? 'OK' : 'KO';
        exit;
    }
}

// "I made a mistake", just after: the person subscribes again from the page she unsubscribed on, within the hour. Later,
// she writes to the company, which subscribes her again from the card of the contact (traced there)
if ($isValid && $isPost && $action === 'undo') {
    $since     = reedcrm_newsletter_unsubscribed_since($db, $email, $entity);
    $pageState = 'already';
    if ($since > 0 && $since >= dol_now() - reedcrm_newsletter_undo_delay()) {
        $previous = null;
        if (reedcrm_newsletter_resubscribe($db, $email, $entity, $previous) > 0) {
            reedcrm_newsletter_unrecord_unsubscribe($db, $newsletterId, $email, $entity);
            dol_syslog('reedcrm newsletter unsubscribe of ' . $email . ' undone from ' . getUserRemoteIP() . ' (unsubscribed on ' . dol_print_date($since, 'dayhourlog') . ')', LOG_NOTICE);
            $pageState = 'undone';
        } else {
            $pageState = 'error';
        }
    }
}

if (!$isValid && $oneClick) {
    http_response_code(403);
    exit;
}

/*
 * View
 */

$title   = $langs->trans('NewsletterUnsubscribeTitle');
$pageUrl = $_SERVER['PHP_SELF'];

// The page shows an address: not to be indexed (the two last arguments keep the noindex, nofollow of Dolibarr)
if (function_exists('saturne_header')) {
    saturne_header(0, '', $title, '', '', 0, 0, [], [], '', 'page-public-card page-public-newsletter-unsubscribe');
} else {
    top_htmlhead('', $title, 0, 0, [], [], 0, 0);
    print '<body class="page-public-card page-public-newsletter-unsubscribe">';
}

require __DIR__ . '/../../core/tpl/newsletter/newsletter_unsubscribe.tpl.php';

llxFooter('', 'public');
$db->close();

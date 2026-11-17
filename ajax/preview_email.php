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
 * \file    ajax/preview_email.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint of the preview of an email attached as a .msg/.eml file.
 *          Without attachment parameter: JSON content of the email (or of an email attached to it, part=1.0).
 *          With attachment=N: download of its Nth attachment, read from the file only when asked.
 */

if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';
require_once __DIR__ . '/../class/reedcrm_email_message.class.php';

global $conf, $db, $langs, $user;

$langs->loadLangs(['errors', 'reedcrm@reedcrm']);

$modulepart = GETPOST('modulepart', 'aZ09');
$file       = GETPOST('file', 'alphanohtml');
$entity     = GETPOSTINT('entity') ? GETPOSTINT('entity') : $conf->entity;
$part       = GETPOST('part', 'alphanohtml');
$attachment = GETPOSTISSET('attachment') ? GETPOSTINT('attachment') : -1;

/**
 * Send a JSON error and stop.
 *
 * @param  int    $httpCode HTTP status
 * @param  string $message  Translated message
 * @return never
 */
function reedcrm_email_preview_fail(int $httpCode, string $message)
{
    top_httphead('application/json');
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

if (empty($modulepart) || empty($file) || !preg_match('/^(\d+(\.\d+)*)?$/', $part)) {
    reedcrm_email_preview_fail(400, $langs->transnoentitiesnoconv('ErrorBadParameters'));
}

// Path traversal hardening (same as document.php)
$file = preg_replace('/\.\.+/', '..', $file);
$file = str_replace(['../', '..\\'], '/', $file);

// Access control and path resolution by the core, as document.php does: every modulepart with its own permissions
$check = dol_check_secure_access_document($modulepart, $file, $entity, $user, '', 'read');
if (empty($check['accessallowed'])) {
    reedcrm_email_preview_fail(403, $langs->transnoentitiesnoconv('NotEnoughPermissions'));
}

$fullpath = $check['original_file'];
if (preg_match('/\.\./', $fullpath) || preg_match('/[<>|]/', $fullpath)) {
    reedcrm_email_preview_fail(403, $langs->transnoentitiesnoconv('NotEnoughPermissions'));
}
if (!preg_match('/\.(msg|eml)$/i', $fullpath) || !is_file($fullpath)) {
    reedcrm_email_preview_fail(404, $langs->transnoentitiesnoconv('ReedCRMEmailPreviewNotFound'));
}

try {
    $msg = ReedcrmEmailMessage::fromFile($fullpath);
} catch (Throwable $e) {
    dol_syslog('reedcrm preview_email error: ' . $e->getMessage(), LOG_WARNING);
    reedcrm_email_preview_fail(422, $langs->transnoentitiesnoconv('ReedCRMEmailPreviewParseError'));
}

$msg = $msg->getPart($part);
if ($msg === null) {
    reedcrm_email_preview_fail(404, $langs->transnoentitiesnoconv('ReedCRMEmailPreviewNotFound'));
}

// Download of one attachment: always as a file, never rendered by the browser from our origin
if ($attachment >= 0) {
    $attached = $msg->attachments[$attachment] ?? null;
    if ($attached === null || $attached['data'] === '') {
        reedcrm_email_preview_fail(404, $langs->transnoentitiesnoconv('ReedCRMEmailPreviewNotFound'));
    }
    $asciiName = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $attached['filename']);
    top_httphead('application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($attached['filename']));
    header('Content-Length: ' . strlen($attached['data']));
    header('X-Content-Type-Options: nosniff');
    echo $attached['data'];
    exit;
}

// Body for a sandboxed iframe: the inline images are embedded, scripts and event handlers removed
$usedCids = [];
if ($msg->htmlBody !== '') {
    $body = $msg->htmlBody;
    foreach ($msg->attachments as $attached) {
        if ($attached['cid'] === '' || stripos($body, 'cid:' . $attached['cid']) === false) {
            continue;
        }
        $mime = preg_match('/^image\/[a-z0-9.+-]+$/i', $attached['mime']) ? $attached['mime'] : 'application/octet-stream';
        $body = str_ireplace('cid:' . $attached['cid'], 'data:' . $mime . ';base64,' . base64_encode($attached['data']), $body);
        $usedCids[$attached['cid']] = true;
    }
    // The sanitizer drops <style>, <title>, Outlook <xml> and conditional comments but keeps their text: drop them whole
    $body = (string) preg_replace('/<!--.*?-->/s', '', $body);
    $body = (string) preg_replace('#<(style|script|title|head|xml)\b[^>]*>.*?</\1\s*>#is', '', $body);
    $body = dol_string_onlythesehtmltags($body, 0, 0, 1, 0);
} else {
    $body = '<pre class="reedcrm-email-text">' . dol_escape_htmltag($msg->textBody, 0, 1) . '</pre>';
}

// Every attachment not shown in the body, read on demand: an inline image of a body that could not be shown stays reachable
$attachments = [];
foreach ($msg->attachments as $index => $attached) {
    if ($attached['cid'] !== '' && isset($usedCids[$attached['cid']])) {
        continue;
    }
    $attachments[] = [
        'index'    => $index,
        'filename' => $attached['filename'],
        'size'     => $attached['data'] !== '' ? dol_print_size(strlen($attached['data']), 1, 1) : '',
        'message'  => $attached['message'] !== null,
        'download' => $attached['data'] !== '',
    ];
}

top_httphead('application/json');
echo json_encode([
    'success'     => true,
    'subject'     => $msg->subject !== '' ? $msg->subject : $langs->transnoentitiesnoconv('ReedCRMEmailPreviewNoSubject'),
    'fromName'    => $msg->fromName,
    'fromEmail'   => $msg->fromEmail,
    'to'          => $msg->to,
    'cc'          => $msg->cc,
    'date'        => $msg->date ? dol_print_date($msg->date, 'dayhour', 'tzuserrel') : '',
    'body'        => $body,
    'attachments' => $attachments,
    'labels'      => [
        'from'        => $langs->transnoentitiesnoconv('ReedCRMEmailPreviewFrom'),
        'to'          => $langs->transnoentitiesnoconv('ReedCRMEmailPreviewTo'),
        'cc'          => $langs->transnoentitiesnoconv('ReedCRMEmailPreviewCc'),
        'date'        => $langs->transnoentitiesnoconv('ReedCRMEmailPreviewDate'),
        'attachments' => $langs->transnoentitiesnoconv('ReedCRMEmailPreviewAttachments', count($attachments)),
        'message'     => $langs->transnoentitiesnoconv('ReedCRMEmailPreviewAttachedMessage'),
        'back'        => $langs->transnoentitiesnoconv('ReedCRMEmailPreviewBack'),
    ],
]);

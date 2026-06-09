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
 * \brief   AJAX endpoint — parses a .msg/.eml attachment and returns its content for the preview modal.
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/custom/reedcrm/class/reedcrm_email_message.class.php';

global $conf, $db, $user, $langs;

$langs->loadLangs(['reedcrm@reedcrm']);

header('Content-Type: application/json');

$modulepart = GETPOST('modulepart', 'aZ09');
$file       = GETPOST('file', 'alphanohtml');
$entity     = GETPOSTINT('entity') ? GETPOSTINT('entity') : $conf->entity;

if (empty($modulepart) || empty($file)) {
    echo json_encode(['success' => false, 'message' => $langs->trans('ErrorBadParameters')]);
    exit;
}

// Path traversal hardening (same as document.php)
$file = preg_replace('/\.\.+/', '..', $file);
$file = str_replace(['../', '..\\'], '/', $file);

// Delegate access control + path resolution to Dolibarr core (handles every modulepart + permissions)
$check = dol_check_secure_access_document($modulepart, $file, $entity, $user, '', 'read');
if (empty($check['accessallowed'])) {
    echo json_encode(['success' => false, 'message' => $langs->trans('NotEnoughPermissions')]);
    exit;
}

$fullpath = $check['original_file']; // absolute path
if (preg_match('/\.\./', $fullpath) || preg_match('/[<>|]/', $fullpath)) {
    echo json_encode(['success' => false, 'message' => $langs->trans('NotEnoughPermissions')]);
    exit;
}
if (!preg_match('/\.(msg|eml)$/i', $fullpath) || !is_file($fullpath)) {
    echo json_encode(['success' => false, 'message' => $langs->trans('FileNotFound')]);
    exit;
}

try {
    $msg = ReedcrmEmailMessage::fromFile($fullpath);
} catch (Throwable $e) {
    dol_syslog('reedcrm preview_email error: ' . $e->getMessage(), LOG_WARNING);
    echo json_encode(['success' => false, 'message' => $langs->trans('ReedCRMEmailPreviewParseError')]);
    exit;
}

// Build the body HTML (sanitized) destined for a sandboxed iframe
if ($msg->htmlBody !== '') {
    $body = reedcrm_email_inline_cids($msg->htmlBody, $msg->attachments);
    // Strip scripts / event handlers / dangerous tags while keeping formatting
    $body = dol_string_onlythesehtmltags($body, 0, 0, 1, 0);
} else {
    $body = '<pre style="white-space:pre-wrap;font-family:inherit;margin:0;">' . dol_escape_htmltag($msg->textBody) . '</pre>';
}

// Non-inline attachments → downloadable list (data-URI, built client side from base64)
$attachments = [];
foreach ($msg->attachments as $a) {
    if (!empty($a['inline'])) {
        continue;
    }
    $attachments[] = [
        'filename' => $a['filename'],
        'mime'     => $a['mime'] !== '' ? $a['mime'] : 'application/octet-stream',
        'size'     => dol_print_size(strlen($a['data']), 1, 1),
        'data'     => base64_encode($a['data']),
    ];
}

echo json_encode([
    'success'     => true,
    'subject'     => $msg->subject,
    'fromName'    => $msg->fromName,
    'fromEmail'   => $msg->fromEmail,
    'to'          => $msg->to,
    'cc'          => $msg->cc,
    'date'        => $msg->date ? dol_print_date($msg->date, 'dayhour') : '',
    'isHtml'      => $msg->htmlBody !== '',
    'body'        => $body,
    'attachments' => $attachments,
]);
exit;

/**
 * Replace inline "cid:" image references in an HTML body with embedded data-URIs.
 *
 * @param  string $html        HTML body
 * @param  array  $attachments Parsed attachments
 * @return string
 */
function reedcrm_email_inline_cids(string $html, array $attachments): string
{
    foreach ($attachments as $a) {
        if (empty($a['cid'])) {
            continue;
        }
        $mime    = $a['mime'] !== '' ? $a['mime'] : 'application/octet-stream';
        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($a['data']);
        $cid     = preg_quote($a['cid'], '/');
        $html    = preg_replace('/cid:' . $cid . '/i', $dataUri, $html);
    }
    return $html;
}

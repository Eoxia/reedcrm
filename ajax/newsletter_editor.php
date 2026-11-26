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
 * \file    ajax/newsletter_editor.php
 * \ingroup reedcrm
 * \brief   AJAX endpoint of the simple editor of the newsletters
 *
 * - preview (POST body_blocks, subject): subject and HTML of the mail, made by the same renderer as the sending, the
 *   keys filled in with the user as an example of recipient;
 * - images: images of the picker (the media library of ReedCRM), the most recent first;
 * - thumb (name): small image of the picker, read with the newsletter right rather than the one of the ECM;
 * - upload (POST userfile): an image joins the media library. It is made public when the newsletter is saved;
 * - count (POST targeting): number of recipients of the tags and filters being chosen.
 *
 * Every call needs the right to write newsletters, and the token of the page.
 */

// The blocks are plain texts and links, checked field by field by reedcrm_newsletter_clean_blocks() and escaped by
// the renderer: a sentence holding "javascript:" is text, not refused for that (see view/newsletter/newsletter_card.php)
if (!defined('NOSCANPOSTFORINJECTION')) {
    define('NOSCANPOSTFORINJECTION', ['body_blocks']);
}
// The calls follow each other while the form is typed: they keep the token of the page
if (!defined('NOTOKENRENEWAL')) {
    define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', 1);
}

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once __DIR__ . '/../lib/reedcrm_newsletter.lib.php';
require_once __DIR__ . '/../class/reedcrmnewsletter.class.php';

global $conf, $db, $langs, $mysoc, $user;

$langs->loadLangs(['main', 'errors', 'mails', 'reedcrm@reedcrm']);

$action = GETPOST('action', 'aZ09');

/**
 * Answer in JSON and stop
 *
 * @param  array $data Answer
 * @param  int   $code HTTP code
 * @return void
 */
$respond = function (array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
};

if (!isModEnabled('reedcrm') || !$user->hasRight('reedcrm', 'newsletter', 'write')) {
    $respond(['error' => $langs->transnoentitiesnoconv('NewsletterActionNoRight')], 403);
}

if ($action === 'preview') {
    $blocks = reedcrm_newsletter_clean_blocks((string) GETPOST('body_blocks', 'none'));
    if (!reedcrm_newsletter_blocks_have_content($blocks)) {
        $respond(['html' => '', 'subject' => '']);
    }

    // The mail as it leaves (keys, footer, unsubscribe link), filled in with the user: the keys typed do not show as
    // they are. The images of the library not public yet are shown from the library itself
    $newsletter               = new ReedcrmNewsletter($db);
    $newsletter->entity       = (int) $conf->entity;
    $newsletter->content_mode = ReedcrmNewsletter::MODE_BLOCKS;
    $newsletter->subject      = GETPOST('subject', 'alphanohtml');
    $newsletter->target_type  = GETPOST('target_type', 'aZ09') === ReedcrmNewsletter::TARGET_PROJECT ? ReedcrmNewsletter::TARGET_PROJECT : ReedcrmNewsletter::TARGET_CONTACT;
    $newsletter->body_blocks  = json_encode($blocks);
    // The language of the mails is the one of the author: the user for a new newsletter
    $newsletter->fk_user_creat = (int) $user->id;
    if (GETPOSTINT('id') > 0) {
        $existing = new ReedcrmNewsletter($db);
        if ($existing->fetch(GETPOSTINT('id')) > 0 && !empty($existing->fk_user_creat)) {
            $newsletter->fk_user_creat = (int) $existing->fk_user_creat;
        }
    }
    $newsletter->previewLibrarySrc = 'reedcrm_newsletter_library_preview_src';
    $mailLangs                     = $newsletter->getMailLangs($langs);
    $sample                        = ['email' => (string) $user->email, 'firstname' => (string) $user->firstname, 'lastname' => (string) $user->lastname, 'civility' => '', 'fk_contact' => 0, 'fk_soc' => 0, 'thirdparty_name' => (string) ($mysoc->name ?? ''), 'fk_project' => 0, 'project_ref' => '', 'project_title' => ''];

    list($subject, $html) = $newsletter->renderMail($sample, $mailLangs, true);
    $respond(['html' => $html, 'subject' => $subject]);
}

if ($action === 'count') {
    // The targeting being chosen, as the card would read it once saved
    $newsletter              = new ReedcrmNewsletter($db);
    $newsletter->entity      = (int) $conf->entity;
    $newsletter->mail_type   = GETPOST('mail_type', 'aZ09') === ReedcrmNewsletter::TYPE_REMINDER ? ReedcrmNewsletter::TYPE_REMINDER : ReedcrmNewsletter::TYPE_NEWSLETTER;
    $newsletter->target_type = GETPOST('target_type', 'aZ09') === ReedcrmNewsletter::TARGET_PROJECT ? ReedcrmNewsletter::TARGET_PROJECT : ReedcrmNewsletter::TARGET_CONTACT;
    $newsletter->setTagIds(GETPOST('tags', 'array:int'));
    $newsletter->setExcludeTagIds(GETPOST('exclude_tags', 'array:int'));
    $thirdpartyType               = GETPOST('thirdparty_type', 'aZ09');
    $newsletter->thirdparty_type  = in_array($thirdpartyType, [ReedcrmNewsletter::THIRDPARTY_CUSTOMER, ReedcrmNewsletter::THIRDPARTY_PROSPECT], true) ? $thirdpartyType : '';
    $newsletter->fk_sales_rep     = GETPOSTINT('fk_sales_rep') > 0 ? GETPOSTINT('fk_sales_rep') : null;
    if (empty($newsletter->getTagIds())) {
        $respond(['count' => 0, 'text' => '']);
    }

    $count    = count($newsletter->getRecipients());
    $excluded = $newsletter->excluded;
    $filtered = $newsletter->hasTargetFilters() ? max(0, $newsletter->countRecipientsWithoutFilters() - $count) : 0;
    $parts    = [];
    if ($filtered > 0) {
        $parts[] = $langs->transnoentitiesnoconv('NewsletterExcludedFiltered', $filtered);
    }
    if (!empty($excluded['unsubscribed'])) {
        $parts[] = $langs->transnoentitiesnoconv('NewsletterExcludedUnsubscribed', (int) $excluded['unsubscribed']);
    }
    if (!empty($excluded['noemail'])) {
        $parts[] = $langs->transnoentitiesnoconv('NewsletterExcludedNoEmail', (int) $excluded['noemail']);
    }
    $text = $langs->transnoentitiesnoconv('NewsletterLiveCount', $count) . (!empty($parts) ? ' · ' . $langs->transnoentitiesnoconv('NewsletterExcluded', implode(', ', $parts)) : '');
    $respond(['count' => $count, 'text' => $text]);
}

if ($action === 'images') {
    $images = [];
    foreach (reedcrm_newsletter_list_picker_images() as $image) {
        $images[] = ['name' => $image['name'], 'date' => dol_print_date($image['date'], 'day')];
    }
    $respond(['images' => $images]);
}

if ($action === 'thumb') {
    $name = reedcrm_newsletter_picker_image_name(GETPOST('name', 'alphanohtml'));
    if ($name === '') {
        http_response_code(404);
        exit;
    }
    $dir   = reedcrm_newsletter_picker_dir();
    $thumb = $dir . '/' . getImageFileNameForSize($name, '_small');
    $path  = is_file($thumb) ? $thumb : $dir . '/' . $name;
    $mime  = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!preg_match('#^image/(png|jpeg|gif|webp)$#', (string) $mime)) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

if ($action === 'upload') {
    // The real limit: the setting of Dolibarr and the ones of PHP
    $maxUpload     = getDolGlobalInt('MAIN_UPLOAD_DOC') > 0 ? (int) getMaxFileSizeArray()['maxmin'] : 0;
    $maxUploadText = price(round($maxUpload / 1024, 1), 0, $langs, 0, 0);
    $file          = $_FILES['userfile'] ?? null;
    // Above post_max_size PHP empties the request: a file was sent, none arrived
    if ($file === null && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $maxUpload > 0) {
        $respond(['error' => $langs->transnoentitiesnoconv('NewsletterImageTooLarge', $maxUploadText)], 400);
    }
    if ($maxUpload <= 0 || !is_array($file) || is_array($file['name'] ?? null)) {
        $respond(['error' => $langs->transnoentitiesnoconv('NewsletterEditorRequestError')], 400);
    }
    if (in_array((int) $file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || (int) $file['size'] > $maxUpload * 1024) {
        $respond(['error' => $langs->transnoentitiesnoconv('NewsletterImageTooLarge', $maxUploadText)], 400);
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
        $respond(['error' => $langs->transnoentitiesnoconv('NewsletterEditorRequestError')], 400);
    }

    // An image of a mail: png, jpeg, gif or webp, whatever its name says. No svg, it can carry a script
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
    $name = dol_sanitizeFileName(basename((string) $file['name']));
    if (!preg_match('#^image/(png|jpeg|gif|webp)$#', (string) $mime) || !preg_match('/\.(png|jpe?g|gif|webp)$/i', $name) || image_format_supported($name) < 0) {
        $respond(['error' => $langs->transnoentitiesnoconv('NewsletterErrorNotAnImage')], 400);
    }
    // Too many pixels for the memory (a photo of 48 Mpx): refused before anything is written, the thumbs would stop
    // PHP in the middle and leave a file without thumbs in the library
    $sizeError = reedcrm_newsletter_image_size_error((string) $file['tmp_name'], $langs);
    if ($sizeError !== '') {
        $respond(['error' => $sizeError], 400);
    }

    $dir = reedcrm_newsletter_picker_dir();
    if (dol_mkdir($dir) < 0) {
        $respond(['error' => $langs->transnoentitiesnoconv('NewsletterEditorRequestError')], 500);
    }
    // Same name as an image of the library: the new one is kept beside it, the old one stays where it is used
    if (file_exists($dir . '/' . $name)) {
        $name = dol_print_date(dol_now(), '%Y%m%d%H%M%S') . '_' . $name;
    }
    $result = dol_move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name, 0, 0, (int) $file['error']);
    if ($result !== 1) {
        // The messages of the core carry HTML and technical words: a plain one instead, the detail in the log
        dol_syslog('newsletter_editor upload ' . $name . ': ' . (is_string($result) ? $result : 'KO'), LOG_WARNING);
        $respond(['error' => $langs->transnoentitiesnoconv('NewsletterEditorUploadError')], 400);
    }

    // Thumbs as the media library makes them (the medium one is the preview of the form), and the index of the ECM
    // when the library is there
    vignette($dir . '/' . $name, getDolGlobalInt('MAIN_DOCUMENT_THUMB_SMALL_WIDTH', 160), getDolGlobalInt('MAIN_DOCUMENT_THUMB_SMALL_HEIGHT', 120), '_small', 50, 'thumbs');
    vignette($dir . '/' . $name, 32, 32, '_mini', 50, 'thumbs');
    // The core makes small and mini only: the medium one by Saturne, as its media library does
    dol_include_once('/saturne/lib/dolibarr.lib.php');
    if (function_exists('saturne_vignette')) {
        saturne_vignette($dir . '/' . $name, getDolGlobalInt('SATURNE_MEDIA_MAX_WIDTH_MEDIUM', 854), getDolGlobalInt('SATURNE_MEDIA_MAX_HEIGHT_MEDIUM', 480), '_medium', 70);
    }
    if (reedcrm_newsletter_library_dir() !== '') {
        addFileIntoDatabaseIndex($dir, $name, (string) $file['name'], 'uploaded', 0);
    }

    // Not public yet: it is when the newsletter is saved with it
    $respond(['name' => $name]);
}

$respond(['error' => 'Unknown action'], 400);

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
 * \file    view/newsletter/newsletter_card.php
 * \ingroup reedcrm
 * \brief   Card of a newsletter or mail reminder sent to the contacts or projects of some tags
 */

// The HTML of a newsletter holds <style> blocks and data: images, which the anti-injection filter of main.inc.php
// refuses in any POST. Only this field is left out of it, as the website module does for the content of its pages:
// reedcrm_newsletter_clean_html() removes the active content, and the HTML is only shown in sandboxed frames. The
// blocks of the simple editor are plain texts and links: reedcrm_newsletter_clean_blocks() checks every field, the
// renderer escapes every text, a sentence typed with "javascript:" in it is not refused for that
if (!defined('NOSCANPOSTFORINJECTION')) {
    define('NOSCANPOSTFORINJECTION', ['body_html', 'body_blocks']);
}

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

require_once __DIR__ . '/../../class/reedcrmnewsletter.class.php';
require_once __DIR__ . '/../../lib/reedcrm_newsletter.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs(['mails', 'categories']);

$id         = GETPOSTINT('id');
$ref        = GETPOST('ref', 'alpha');
$action     = GETPOST('action', 'aZ09');
$confirm    = GETPOST('confirm', 'alpha');
$cancel     = GETPOST('cancel', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$object = new ReedcrmNewsletter($db);
$form   = new Form($db);

$hookmanager->initHooks(['newslettercard', 'reedcrmglobal', 'globalcard']);

$permissiontoread   = $user->hasRight('reedcrm', 'newsletter', 'read');
$permissiontoadd    = $user->hasRight('reedcrm', 'newsletter', 'write');
$permissiontodelete = $user->hasRight('reedcrm', 'newsletter', 'delete');
$permissiontosend   = $user->hasRight('reedcrm', 'newsletter', 'send');

if ($id > 0 || !empty($ref)) {
    $object->fetch($id, $ref);
}

saturne_check_access($permissiontoread, $object);

// No create form for who could not save it: it would end on an empty page
if (in_array($action, ['create', 'add'], true) && !$permissiontoadd) {
    accessforbidden();
}

$cardUrl = dol_buildpath('/reedcrm/view/newsletter/newsletter_card.php', 1);
$listUrl = dol_buildpath('/saturne/view/saturne_list.php', 1) . '?object_type=newsletter';

/**
 * Why an action cannot be done on the newsletter as it is, by this user: the buttons, the confirmations and the
 * actions themselves all ask here, an URL typed by hand meets the same rules as a click
 *
 * @param  string $name Action: edit, send_test, schedule, send_now, setdraft, retry_errors, clone, add_next, delete
 * @return string       '' when it can be done, else the reason to show
 */
$refusal = function (string $name) use ($object, $langs, $user, $permissiontoadd, $permissiontodelete, $permissiontosend): string {
    static $recipientsCount = null;

    $status = (int) $object->status;
    if ($object->id <= 0) {
        return $langs->trans('ErrorRecordNotFound');
    }
    if ($object->isDeleted()) {
        return $langs->trans('NewsletterActionDeleted');
    }

    $rights = ['edit' => $permissiontoadd, 'send_test' => $permissiontoadd, 'clone' => $permissiontoadd, 'add_next' => $permissiontoadd, 'delete' => $permissiontodelete, 'schedule' => $permissiontosend, 'send_now' => $permissiontosend, 'setdraft' => $permissiontosend, 'retry_errors' => $permissiontosend];
    if (empty($rights[$name])) {
        return $langs->trans('NewsletterActionNoRight');
    }

    $states = [
        'edit'         => $object->isEditable(),
        'schedule'     => $status === ReedcrmNewsletter::STATUS_DRAFT,
        'send_now'     => $object->isEditable(),
        'setdraft'     => in_array($status, [ReedcrmNewsletter::STATUS_SCHEDULED, ReedcrmNewsletter::STATUS_SENT], true),
        'retry_errors' => in_array($status, [ReedcrmNewsletter::STATUS_SCHEDULED, ReedcrmNewsletter::STATUS_SENT], true),
        'add_next'     => $object->canPrepareNext() && in_array($status, [ReedcrmNewsletter::STATUS_SCHEDULED, ReedcrmNewsletter::STATUS_SENT], true),
    ];
    if (isset($states[$name]) && !$states[$name]) {
        return $langs->trans('NewsletterActionState', $object->getLibStatut(1));
    }
    // A planned newsletter leaves on its own: changing it is sending it, the right to send is asked too
    if ($name === 'edit' && !$object->canBeEditedBy($user)) {
        return $langs->trans('NewsletterEditScheduledNoRight', $object->getLibStatut(1));
    }
    if ($name === 'retry_errors' && (int) $object->nb_errors <= 0) {
        return $langs->trans('NewsletterNoErrorToRetry');
    }

    if (in_array($name, ['send_test', 'schedule', 'send_now'], true)) {
        $contentError = $object->getContentError($langs);
        if ($contentError !== '') {
            return $langs->trans('NewsletterCannotLeave', $contentError);
        }
    }

    // After the creation, a newsletter may be activated before anyone carries its tags; not sent now to nobody
    if ($name === 'send_now' || ($name === 'schedule' && !$object->isAfterCreation())) {
        if ($recipientsCount === null) {
            $recipientsCount = count($object->getRecipients(1));
        }
        if ($recipientsCount === 0) {
            return $langs->trans('NewsletterNoRecipient');
        }
    }

    return '';
};

/**
 * Fill the newsletter from the form, return the errors of the required fields
 *
 * @param  ReedcrmNewsletter $object Newsletter
 * @return string[]                  Errors
 */
$fillFromPost = function (ReedcrmNewsletter $object) use ($db, $langs, $user): array {
    $object->label        = GETPOST('label', 'alphanohtml');
    $object->mail_type    = GETPOST('mail_type', 'aZ09') === ReedcrmNewsletter::TYPE_REMINDER ? ReedcrmNewsletter::TYPE_REMINDER : ReedcrmNewsletter::TYPE_NEWSLETTER;
    $object->target_type  = GETPOST('target_type', 'aZ09') === ReedcrmNewsletter::TARGET_PROJECT ? ReedcrmNewsletter::TARGET_PROJECT : ReedcrmNewsletter::TARGET_CONTACT;
    $contentMode          = GETPOST('content_mode', 'aZ09');
    $object->content_mode = in_array($contentMode, [ReedcrmNewsletter::MODE_TEMPLATE, ReedcrmNewsletter::MODE_BLOCKS], true) ? $contentMode : ReedcrmNewsletter::MODE_HTML;
    $object->setTagIds(GETPOST('tags_' . $object->target_type, 'array:int'));

    // Narrower targeting: tags left out, customers or prospects only, a sales representative
    $object->setExcludeTagIds(GETPOST('exclude_tags_' . $object->target_type, 'array:int'));
    $thirdpartyType          = GETPOST('thirdparty_type', 'aZ09');
    $object->thirdparty_type = in_array($thirdpartyType, [ReedcrmNewsletter::THIRDPARTY_CUSTOMER, ReedcrmNewsletter::THIRDPARTY_PROSPECT], true) ? $thirdpartyType : '';
    $object->fk_sales_rep    = GETPOSTINT('fk_sales_rep') > 0 ? GETPOSTINT('fk_sales_rep') : null;

    $object->fk_email_template = GETPOSTINT('fk_email_template') ?: null;
    // "Name <address>": alphanohtml would remove "<address>" as a tag
    $object->email_from = trim((string) GETPOST('email_from', 'alphawithlgt'));
    $object->subject    = GETPOST('subject', 'alphanohtml');
    if ($object->content_mode === ReedcrmNewsletter::MODE_BLOCKS) {
        // The HTML made from the blocks is kept too: switching to the HTML mode later starts from it
        $blocks              = reedcrm_newsletter_clean_blocks((string) GETPOST('body_blocks', 'none'));
        $object->body_blocks = json_encode($blocks);
        $object->body_html   = reedcrm_newsletter_render_blocks($blocks, $object->getMailLangs($langs));
    } else {
        // The blocks stay: coming back to the simple editor finds them as they were
        $object->body_html = reedcrm_newsletter_clean_html((string) GETPOST('body_html', 'none'));
    }

    $dateSend          = dol_mktime(GETPOSTINT('date_sendhour'), GETPOSTINT('date_sendmin'), 0, GETPOSTINT('date_sendmonth'), GETPOSTINT('date_sendday'), GETPOSTINT('date_sendyear'));
    $object->date_send = $dateSend ?: '';

    $object->frequency      = $object->mail_type === ReedcrmNewsletter::TYPE_REMINDER ? max(0, GETPOSTINT('frequency')) : 0;
    $object->frequency_unit = ReedcrmNewsletter::getUnit(GETPOST('frequency_unit', 'aZ09'), 'm');

    $object->send_trigger    = GETPOST('send_trigger', 'aZ09') === ReedcrmNewsletter::TRIGGER_CREATION ? ReedcrmNewsletter::TRIGGER_CREATION : ReedcrmNewsletter::TRIGGER_DATE;
    $object->send_delay      = max(0, GETPOSTINT('send_delay'));
    $object->send_delay_unit = ReedcrmNewsletter::getUnit(GETPOST('send_delay_unit', 'aZ09'), 'd');

    // "Every month" only means something for a newsletter sent at a date
    $object->auto_next = $object->canPrepareNext() && GETPOSTINT('auto_next') ? 1 : 0;

    $errors = [];
    if (trim($object->label) === '') {
        $errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label'));
    }
    if (empty($object->getTagIds())) {
        $errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('NewsletterTargetTags'));
    }
    // A tag both chosen and left out would send to nobody, by a contradiction hard to see later
    if (!empty(array_intersect($object->getTagIds(), $object->getExcludeTagIds()))) {
        $errors[] = $langs->trans('NewsletterTagChosenAndExcluded');
    }
    if ($object->content_mode === ReedcrmNewsletter::MODE_TEMPLATE) {
        if (empty($object->fk_email_template)) {
            $errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('NewsletterEmailTemplate'));
        } elseif (!array_key_exists((int) $object->fk_email_template, reedcrm_newsletter_get_email_templates($db, $user, $object->target_type))) {
            // Only the templates the list offers: an id typed by hand never reaches a private template of another user
            $errors[] = $langs->trans('NewsletterTemplateNotAllowed');
        }
    } else {
        if (trim($object->subject) === '') {
            $errors[] = $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('MailTopic'));
        }
        if ($object->content_mode === ReedcrmNewsletter::MODE_BLOCKS) {
            if (!reedcrm_newsletter_blocks_have_content(reedcrm_newsletter_clean_blocks($object->body_blocks))) {
                $errors[] = $langs->trans('NewsletterErrorBlocksEmpty');
            }
        } elseif (!reedcrm_newsletter_has_content($object->body_html)) {
            $errors[] = $langs->trans('NewsletterErrorBodyEmpty');
        }
    }
    if ($object->email_from !== '') {
        $fromEmail = preg_match('/<([^>]+)>\s*$/', $object->email_from, $matches) ? $matches[1] : $object->email_from;
        if (preg_match('/[\r\n]/', $object->email_from) || !isValidEmail(trim($fromEmail))) {
            $errors[] = $langs->trans('ErrorBadEMail', $object->email_from);
        }
    }

    return $errors;
};

/**
 * Images of the library chosen in the blocks, made public once the form is valid: an image tried and left in the form
 * is never public. The HTML kept for the HTML mode is made again with the public addresses
 *
 * @param  ReedcrmNewsletter $object Newsletter filled from the form
 * @return string[]                  Errors, one per image that could not be published
 */
$publishImages = function (ReedcrmNewsletter $object) use ($langs): array {
    $errors = [];
    if ($object->content_mode !== ReedcrmNewsletter::MODE_BLOCKS && empty($object->body_blocks)) {
        return $errors;
    }
    $blocks              = reedcrm_newsletter_publish_block_images(reedcrm_newsletter_clean_blocks((string) $object->body_blocks), $errors);
    $object->body_blocks = json_encode($blocks);
    if ($object->content_mode === ReedcrmNewsletter::MODE_BLOCKS) {
        $object->body_html = reedcrm_newsletter_render_blocks($blocks, $object->getMailLangs($langs));
    }

    return $errors;
};

/**
 * After a save: a draft says it does not leave by itself, a planned one says when it leaves. "Save and plan" opens the
 * confirmation of the planning
 *
 * @param  ReedcrmNewsletter $object Newsletter saved
 * @return void
 */
$afterSave = function (ReedcrmNewsletter $object) use ($db, $langs, $cardUrl, $permissiontosend): void {
    $isDraft = (int) $object->status === ReedcrmNewsletter::STATUS_DRAFT;
    if ($isDraft && GETPOST('save_and_schedule', 'alpha') !== '' && $permissiontosend) {
        header('Location: ' . $cardUrl . '?id=' . $object->id . '&action=schedule&token=' . newToken());
        exit;
    }
    if ($isDraft && !$object->isAfterCreation() && !empty($object->date_send)) {
        setEventMessages($langs->trans('NewsletterSavedDraftWithDate', dol_print_date($object->date_send, 'dayhour')), null, 'warnings');
    } elseif ($isDraft) {
        setEventMessages($langs->trans('NewsletterSavedDraft'), null);
    } else {
        setEventMessages($langs->trans('NewsletterSaved'), null);
    }
    reedcrm_newsletter_cleanup_public_images($db);
};

/*
 * Actions
 */

$parameters = ['id' => $id];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    if ($cancel) {
        if (!empty($backtopage)) {
            header('Location: ' . $backtopage);
        } elseif ($object->id > 0) {
            header('Location: ' . $cardUrl . '?id=' . $object->id);
        } else {
            header('Location: ' . $listUrl);
        }
        exit;
    }

    // An action refused (no right, deleted, wrong state, nothing to send) says why instead of doing nothing
    $asked = in_array($action, ['update', 'edit'], true) ? 'edit' : preg_replace('/^confirm_/', '', (string) $action);
    if ($object->id > 0 && in_array($asked, ['edit', 'send_test', 'schedule', 'send_now', 'setdraft', 'retry_errors', 'clone', 'add_next', 'delete'], true)) {
        $why = $refusal($asked);
        if ($why !== '') {
            setEventMessages($why, null, 'errors');
            header('Location: ' . $cardUrl . '?id=' . $object->id);
            exit;
        }
    }

    if ($action === 'add') {
        $errors = $fillFromPost($object);
        if (empty($errors)) {
            $errors = $publishImages($object);
        }
        if (empty($errors)) {
            $object->status = ReedcrmNewsletter::STATUS_DRAFT;
            if ($object->create($user) > 0) {
                if (!empty($object->auto_next)) {
                    $object->addTraceEvent($user, 'AC_REEDCRM_NL_AUTONEXT', $langs->transnoentities('NewsletterAutoNextEventOn', $object->ref), $langs->transnoentities('NewsletterAutoNextEventNote', $user->getFullName($langs)));
                }
                $afterSave($object);
                header('Location: ' . $cardUrl . '?id=' . $object->id);
                exit;
            }
            $errors = array_merge([$object->error], $object->errors);
        }
        setEventMessages('', $errors, 'errors');
        $action = 'create';
    }

    if ($action === 'update') {
        $wasAutoNext = (int) $object->auto_next;
        $errors      = $fillFromPost($object);
        if (empty($errors)) {
            $errors = $publishImages($object);
        }
        if (empty($errors)) {
            if ($object->update($user) > 0) {
                // Who set the series going (or stopped it): the drafts and the reminders go to the author, not to him
                if ($wasAutoNext !== (int) $object->auto_next) {
                    $object->addTraceEvent($user, 'AC_REEDCRM_NL_AUTONEXT', $langs->transnoentities(!empty($object->auto_next) ? 'NewsletterAutoNextEventOn' : 'NewsletterAutoNextEventOff', $object->ref), $langs->transnoentities('NewsletterAutoNextEventNote', $user->getFullName($langs)));
                }
                $afterSave($object);
                header('Location: ' . $cardUrl . '?id=' . $object->id);
                exit;
            }
            $errors = array_merge([$object->error], $object->errors);
        }
        setEventMessages('', $errors, 'errors');
        $action = 'edit';
    }

    if ($action === 'confirm_send_test' && $confirm === 'yes') {
        $testEmail = trim(GETPOST('test_email', 'alphanohtml'));
        if (!isValidEmail($testEmail)) {
            setEventMessages($langs->trans('ErrorBadEMail', $testEmail), null, 'errors');
        } else {
            $error = $object->sendTest($user, $testEmail, $object->getMailLangs($langs));
            if ($error === '') {
                setEventMessages($langs->trans('NewsletterTestSent', $testEmail), null);
            } else {
                setEventMessages($langs->trans('NewsletterTestError', $error), null, 'errors');
            }
        }
        header('Location: ' . $cardUrl . '?id=' . $object->id);
        exit;
    }

    if ($action === 'confirm_schedule' && $confirm === 'yes') {
        if ($object->schedule($user) > 0) {
            setEventMessages($langs->trans($object->isAfterCreation() ? 'NewsletterActivated' : 'NewsletterScheduled', dol_print_date($object->date_send, 'dayhour')), null);
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: ' . $cardUrl . '?id=' . $object->id);
        exit;
    }

    if ($action === 'confirm_send_now' && $confirm === 'yes') {
        // At a date, the planned date is kept: it names the edition, the next month counts from it, and the scheduled
        // job goes on with a sending started before it. After the creation, the start is brought forward to now.
        // A date already passed is the sending in progress: its recipients already served are not mailed again
        if (empty($object->date_send) || ($object->isAfterCreation() && $object->date_send > dol_now())) {
            $object->date_send = dol_now();
        }
        if ($object->schedule($user) > 0) {
            $result = $object->sendBatch($user, 0, $object->getMailLangs($langs));
            if ($result['error'] !== '') {
                setEventMessages($langs->trans('NewsletterCannotLeave', $result['error']), null, 'errors');
            } elseif ($result['sent'] + $result['errors'] + $result['remaining'] === 0) {
                // Nothing left: after the creation nobody is due yet, at a date everybody was already served
                setEventMessages($langs->trans($object->isAfterCreation() ? 'NewsletterNothingDue' : 'NewsletterAllServed'), null, 'warnings');
            } else {
                setEventMessages($langs->trans('NewsletterSendResult', $result['sent'], $result['errors']), null, $result['errors'] > 0 ? 'warnings' : 'mesgs');
            }
            if ($result['remaining'] > 0) {
                setEventMessages($langs->trans('NewsletterSendRemaining', $result['remaining']), null, 'warnings');
            }
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: ' . $cardUrl . '?id=' . $object->id);
        exit;
    }

    if ($action === 'confirm_setdraft' && $confirm === 'yes') {
        $object->status = ReedcrmNewsletter::STATUS_DRAFT;
        if ($object->update($user) < 0) {
            setEventMessages($object->error, $object->errors, 'errors');
        }
        header('Location: ' . $cardUrl . '?id=' . $object->id);
        exit;
    }

    if ($action === 'confirm_retry_errors' && $confirm === 'yes') {
        $nbRetried = $object->retryErrors($user);
        if ($nbRetried < 0) {
            setEventMessages($langs->trans($object->error), $object->errors, 'errors');
        } else {
            setEventMessages($langs->trans('NewsletterErrorsRetried', $nbRetried), null);
        }
        header('Location: ' . $cardUrl . '?id=' . $object->id);
        exit;
    }

    // After its confirmation (which says what it makes): a draft is made, or the one already made is opened. Nothing leaves
    if ($action === 'confirm_add_next' && $confirm === 'yes') {
        $created = false;
        $nextId  = $object->prepareNextEdition($user, $object->getMailLangs($langs), $created);
        if ($nextId > 0) {
            $next = new ReedcrmNewsletter($db);
            $next->fetch($nextId);
            setEventMessages($langs->trans($created ? 'NewsletterNextPrepared' : 'NewsletterNextAlreadyPrepared', $next->label), null, $created ? 'mesgs' : 'warnings');
            header('Location: ' . $cardUrl . '?id=' . $nextId . ($created ? '&action=edit' : ''));
            exit;
        }
        setEventMessages($langs->trans($object->error), $object->errors, 'errors');
        header('Location: ' . $cardUrl . '?id=' . $object->id);
        exit;
    }

    if ($action === 'confirm_clone' && $confirm === 'yes') {
        $clone = new ReedcrmNewsletter($db);
        if ($clone->createFromClone($user, $object->id) > 0) {
            header('Location: ' . $cardUrl . '?id=' . $clone->id);
            exit;
        }
        setEventMessages($clone->error, $clone->errors, 'errors');
    }

    // Opened already prepared: no confirmation to ask, its card opens
    if ($action === 'add_next' && $object->fetchNextEdition() > 0) {
        header('Location: ' . $cardUrl . '?id=' . $object->fetchNextEdition());
        exit;
    }

    if ($action === 'confirm_delete' && $confirm === 'yes') {
        if ($object->delete($user) > 0) {
            header('Location: ' . $listUrl);
            exit;
        }
        setEventMessages($object->error, $object->errors, 'errors');
    }
}

/*
 * View
 */

$title   = $langs->trans('Newsletter') . ($object->id > 0 ? ' - ' . $object->ref : '');
$helpUrl = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $helpUrl);

// Data of the form: the tags of both targets (the form shows the ones of the target chosen) and the templates
if ($action === 'create' || ($action === 'edit' && $object->id > 0)) {
    if ($action === 'create') {
        $object->mail_type    = GETPOST('mail_type', 'aZ09') === ReedcrmNewsletter::TYPE_REMINDER ? ReedcrmNewsletter::TYPE_REMINDER : $object->mail_type;
        $object->target_type  = GETPOST('target_type', 'aZ09') === ReedcrmNewsletter::TARGET_PROJECT ? ReedcrmNewsletter::TARGET_PROJECT : $object->target_type;
        $object->send_trigger = GETPOST('send_trigger', 'aZ09') === ReedcrmNewsletter::TRIGGER_CREATION ? ReedcrmNewsletter::TRIGGER_CREATION : $object->send_trigger;
        // A new newsletter starts in the simple editor; a form sent back with an error keeps the mode chosen
        if (!GETPOSTISSET('content_mode')) {
            $object->content_mode = ReedcrmNewsletter::MODE_BLOCKS;
        }
    }

    // Simple editor: the blocks saved, else a title, a greeting and a button to fill in
    $blocksData = !empty($object->body_blocks) ? reedcrm_newsletter_clean_blocks((string) $object->body_blocks) : reedcrm_newsletter_default_blocks($langs);
    // The real limit of an upload: the setting of Dolibarr, and the ones of PHP (above post_max_size the server gets an
    // empty request)
    $maxUpload  = getDolGlobalInt('MAIN_UPLOAD_DOC') > 0 ? (int) getMaxFileSizeArray()['maxmin'] : 0;
    $editorUrls = [
        'editor'     => dol_buildpath('/reedcrm/ajax/newsletter_editor.php', 1),
        'imageBase'  => DOL_URL_ROOT . '/viewimage.php?modulepart=medias&entity=' . ((int) $conf->entity) . '&file=',
        // Images of the library not public yet: their thumb, read with the session
        'thumbBase'  => dol_buildpath('/reedcrm/ajax/newsletter_editor.php', 1) . '?action=thumb&token=' . newToken() . '&name=',
        'library'    => reedcrm_newsletter_library_dir() !== '' ? dol_buildpath('/saturne/view/saturne_medias.php', 1) . '?module_name=ReedCRM&pending=0' : '',
        'canUpload'  => $maxUpload > 0 ? 1 : 0,
        'maxUpload'  => $maxUpload,
    ];
    // "Save and plan": one click for a draft whose author may send
    $canSaveAndSchedule = $permissiontosend && ($action === 'create' || (int) $object->status === ReedcrmNewsletter::STATUS_DRAFT);
    // The advanced settings fold away, unless one of them differs from a plain newsletter: nothing set is hidden
    $advancedOpen = $object->mail_type === ReedcrmNewsletter::TYPE_REMINDER || $object->target_type === ReedcrmNewsletter::TARGET_PROJECT
        || $object->isAfterCreation() || trim((string) $object->email_from) !== '' || $object->hasTargetFilters();

    // Sales representative of the third parties: the active users, "none" first
    $salesRepSelect = $form->select_dolusers((int) $object->fk_sales_rep > 0 ? (int) $object->fk_sales_rep : -1, 'fk_sales_rep', 1, null, 0, '', '', '0', 0, 0, '(u.statut:=:1)', 0, '', 'minwidth250 maxwidth300');

    $tagChoices = [
        ReedcrmNewsletter::TARGET_CONTACT => isModEnabled('categorie') ? $form->select_all_categories(Categorie::TYPE_CONTACT, '', '', 64, 0, 3) : [],
        ReedcrmNewsletter::TARGET_PROJECT => isModEnabled('categorie') ? $form->select_all_categories(Categorie::TYPE_PROJECT, '', '', 64, 0, 3) : [],
    ];
    $emailTemplates    = reedcrm_newsletter_get_email_templates($db, $user);
    $substitutionKeys  = reedcrm_newsletter_substitution_keys();
    $emailTemplatesUrl = $user->admin ? DOL_URL_ROOT . '/admin/mails_templates.php' : '';
    $cronFrequency     = reedcrm_newsletter_cron_frequency_text($db, $langs);
    $isCreate          = $action === 'create';

    if ($isCreate) {
        print load_fiche_titre($langs->trans('NewNewsletter'), '', $object->picto);
        print dol_get_fiche_head();
    } else {
        saturne_get_fiche_head($object, 'card', $title);
    }

    require __DIR__ . '/../../core/tpl/newsletter/newsletter_form.tpl.php';

    llxFooter();
    $db->close();
    exit;
}

if ($object->id <= 0) {
    llxFooter();
    $db->close();
    exit;
}

saturne_get_fiche_head($object, 'card', $title);

$linkBack = '<a href="' . $listUrl . '&restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';
saturne_banner_tab($object, 'id', $linkBack, 1, 'rowid', 'ref');

// Confirmations, only for an action that can be done (the actions above refuse the others with their reason)
$formConfirm = '';
if (in_array($action, ['send_test', 'schedule', 'send_now', 'setdraft', 'retry_errors', 'clone', 'add_next', 'delete'], true) && $refusal($action) === '') {
    $cronFrequency = reedcrm_newsletter_cron_frequency_text($db, $langs);
    $maxPerRun     = max(1, getDolGlobalInt('REEDCRM_NEWSLETTER_MAX_PER_RUN', 100));
    // Who will get the mail now: the recipients of this sending not served yet (after the creation: the ones due)
    $toServe = 0;
    if (in_array($action, ['schedule', 'send_now'], true)) {
        $confirmRecipients = $object->getRecipients();
        if ($object->isAfterCreation()) {
            $confirmLines = $object->fetchSendLines();
            foreach ($confirmRecipients as $confirmRecipient) {
                $toServe += $object->getRecipientState($confirmRecipient, dol_now(), $confirmLines)['state'] === ReedcrmNewsletter::RECIPIENT_DUE ? 1 : 0;
            }
        } else {
            $confirmDone = !empty($object->date_send) ? $object->fetchDoneKeys((int) $object->date_send) : [];
            foreach ($confirmRecipients as $confirmRecipient) {
                $toServe += isset($confirmDone[$confirmRecipient['dedupe_key']]) ? 0 : 1;
            }
        }
    }
    if ($action === 'send_test') {
        $formQuestion = [['type' => 'text', 'name' => 'test_email', 'label' => $langs->trans('NewsletterTestEmail'), 'value' => $user->email, 'morecss' => 'minwidth300']];
        $formConfirm  = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('NewsletterSendTest'), $langs->trans('NewsletterSendTestConfirm'), 'confirm_send_test', $formQuestion, 'yes', 1);
    } elseif ($action === 'schedule' && $object->isAfterCreation()) {
        $startText   = !empty($object->date_send) ? dol_print_date($object->date_send, 'dayhour') : dol_print_date(dol_now(), 'dayhour');
        $confirmKey  = $object->target_type === ReedcrmNewsletter::TARGET_PROJECT ? 'NewsletterActivateConfirmProject' : 'NewsletterActivateConfirm';
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('NewsletterActivate'), $langs->trans($confirmKey, reedcrm_newsletter_trigger_text($object, $langs), $startText, $cronFrequency), 'confirm_schedule', '', 'yes', 1);
    } elseif ($action === 'schedule') {
        if (!empty($object->date_send) && $object->date_send > dol_now()) {
            $question = $langs->trans('NewsletterScheduleConfirm', dol_print_date($object->date_send, 'dayhour'));
        } else {
            $question = $langs->trans('NewsletterScheduleConfirmNow', $cronFrequency);
        }
        $question    = $langs->trans('NewsletterConfirmRecipientsCount', $toServe) . ' ' . $question;
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('NewsletterSchedule'), $question, 'confirm_schedule', '', 'yes', 1);
    } elseif ($action === 'send_now') {
        if ($object->isAfterCreation()) {
            $question = $langs->trans('NewsletterSendNowCreationConfirm', $maxPerRun);
        } elseif ($toServe <= $maxPerRun) {
            $question = $langs->trans('NewsletterSendNowConfirmAll');
        } else {
            $question = $langs->trans('NewsletterSendNowConfirm', $maxPerRun);
        }
        $question    = $langs->trans('NewsletterConfirmRecipientsCount', $toServe) . ' ' . $question;
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('NewsletterSendNow'), $question, 'confirm_send_now', '', 'yes', 1);
    } elseif ($action === 'add_next') {
        $nextDate    = $object->getNextEditionDate();
        $nextLabel   = reedcrm_newsletter_next_label((string) $object->label, (int) $object->date_send ?: dol_now(), $nextDate, $object->getMailLangs($langs));
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('NewsletterPrepareNext'), $langs->trans('NewsletterPrepareNextConfirm', $nextLabel, dol_print_date($nextDate, 'dayhour')), 'confirm_add_next', '', 'yes', 1);
    } elseif ($action === 'setdraft') {
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('NewsletterSetDraft'), $langs->trans($object->isAfterCreation() ? 'NewsletterSetDraftCreationConfirm' : 'NewsletterSetDraftConfirm'), 'confirm_setdraft', '', 'yes', 1);
    } elseif ($action === 'retry_errors') {
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('NewsletterRetryErrors'), $langs->trans('NewsletterRetryErrorsConfirm'), 'confirm_retry_errors', '', 'yes', 1);
    } elseif ($action === 'clone') {
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('ToClone'), $langs->trans('ConfirmCloneAsk', $object->ref), 'confirm_clone', '', 'yes', 1);
    } elseif ($action === 'delete') {
        $formConfirm = $form->formconfirm($cardUrl . '?id=' . $object->id, $langs->trans('DeleteObject', $langs->transnoentities('Newsletter')), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 'no', 1);
    }
}
print $formConfirm;

// Data of the card: the tags shown as on the cards of Dolibarr, in their color, the chosen ones and the left out ones
$tagChips = function (array $tagIds) use ($db): array {
    $chips = [];
    foreach ($tagIds as $tagId) {
        $category = new Categorie($db);
        if ($category->fetch($tagId) > 0) {
            $chips[] = [
                'url'   => DOL_URL_ROOT . '/categories/viewcat.php?id=' . ((int) $category->id) . '&type=' . urlencode($category->type),
                'label' => $category->label,
                'color' => $category->color ?: 'bbb',
                'class' => $category->color && colorIsLight($category->color) ? 'categtextblack' : 'categtextwhite',
            ];
        }
    }
    return $chips;
};
$tags        = $tagChips($object->getTagIds());
$excludeTags = $tagChips($object->getExcludeTagIds());

// Sales representative of the targeting
$salesRep = null;
if ((int) $object->fk_sales_rep > 0) {
    $salesRep = new User($db);
    if ($salesRep->fetch((int) $object->fk_sales_rep) <= 0) {
        $salesRep = null;
    }
}

// Results: of this newsletter, and of the editions of its series to compare them
$sendStats     = $object->getSendStats();
$editionSeries = $object->canPrepareNext() ? $object->fetchEditionSeries(12) : [];

$mailLangs       = $object->getMailLangs($langs);
$recipients      = $object->getRecipients();
$excluded        = $object->excluded;
$recipientsCount = count($recipients);
// A user who does not see all the third parties sees the recipients of his own only: names, addresses and mails
$visibleRecipients = ReedcrmNewsletter::getPerimeterSql($user, 'x') === '' ? $recipients : $object->filterRecipientsForUser($recipients, $user);
$hiddenRecipients  = $recipientsCount - count($visibleRecipients);
$recipientsShown   = array_slice($visibleRecipients, 0, 20);
// What the narrower targeting leaves out, beside the unsubscribed and the addresses missing
$excluded['filtered'] = $object->hasTargetFilters() ? max(0, $object->countRecipientsWithoutFilters() - $recipientsCount) : 0;
$emailTemplate   = $object->content_mode === ReedcrmNewsletter::MODE_TEMPLATE ? $object->fetchEmailTemplate() : null;
$sendLog         = $object->fetchSendLog(50, $user);
$sendLogCount    = $object->countSendLines($user);
$contentError    = $object->isDeleted() ? '' : $object->getContentError($langs);

$preview = null;
if (!empty($visibleRecipients)) {
    $preview = $object->renderMail($visibleRecipients[0], $mailLangs);
} else {
    $preview = $object->renderMail(['email' => $user->email, 'firstname' => $user->firstname, 'lastname' => $user->lastname, 'civility' => '', 'fk_contact' => 0, 'fk_soc' => 0, 'thirdparty_name' => '', 'fk_project' => 0, 'project_ref' => '', 'project_title' => ''], $mailLangs);
}

// Keys of the projects in a mail to contacts: they will stay empty, said before the sending
$emptyKeys = [];
if ($object->target_type === ReedcrmNewsletter::TARGET_CONTACT) {
    list($sourceSubject, $sourceBody) = $object->getContentSource();
    foreach (reedcrm_newsletter_project_keys() as $projectKey) {
        if (strpos($sourceSubject . $sourceBody, $projectKey) !== false) {
            $emptyKeys[] = $projectKey;
        }
    }
}

// Where the answers go, when it is not the sender
$replyTo = $object->getReplyToAddress();

// A big sending leaves by batches: how long it takes, said before it starts
$sendingDuration = '';
$maxPerRun       = max(1, getDolGlobalInt('REEDCRM_NEWSLETTER_MAX_PER_RUN', 100));
$cronPeriod      = reedcrm_newsletter_cron_period($db);
if (!$object->isAfterCreation() && $recipientsCount > $maxPerRun && $cronPeriod > 0 && in_array((int) $object->status, [ReedcrmNewsletter::STATUS_DRAFT, ReedcrmNewsletter::STATUS_SCHEDULED], true)) {
    $runs            = (int) ceil($recipientsCount / $maxPerRun);
    $sendingDuration = $langs->trans('NewsletterSendingDuration', $runs, reedcrm_newsletter_rough_duration_text(($runs - 1) * $cronPeriod, $langs), $maxPerRun);
}

// The editions of a monthly newsletter: the one it was prepared from, the one prepared from it (the deleted ones
// walked through)
$previousEdition = $object->fetchPreviousEdition();
$nextEdition   = null;
$nextEditionId = $object->canPrepareNext() ? $object->fetchNextEdition() : 0;
if ($nextEditionId > 0) {
    $nextEdition = new ReedcrmNewsletter($db);
    if ($nextEdition->fetch($nextEditionId) <= 0) {
        $nextEdition = null;
    }
}

// A draft whose date has passed leaves nothing: said, the date alone does not plan it
$datePassed = (int) $object->status === ReedcrmNewsletter::STATUS_DRAFT && !$object->isAfterCreation() && !empty($object->date_send) && $object->date_send < dol_now();

// After the creation: where each recipient stands, and how many in each state
$recipientStates = [];
$stateCounts     = [];
if ($object->isAfterCreation()) {
    $now       = dol_now();
    $sendLines = $object->fetchSendLines();
    foreach ($recipients as $recipient) {
        $state = $object->getRecipientState($recipient, $now, $sendLines);
        $stateCounts[$state['state']] = ($stateCounts[$state['state']] ?? 0) + 1;
    }
    foreach ($recipientsShown as $index => $recipient) {
        $recipientStates[$index] = $object->getRecipientState($recipient, $now, $sendLines);
    }
}

require __DIR__ . '/../../core/tpl/newsletter/newsletter_view.tpl.php';

print dol_get_fiche_end();

// Buttons: shown in the states where they make sense, greyed with their reason when this user cannot use them
$parameters = [];
$resHook    = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
if (empty($resHook) && !$object->isDeleted()) {
    $status    = (int) $object->status;
    $urlAction = $cardUrl . '?id=' . $object->id . '&token=' . newToken() . '&action=';
    $button    = function (string $label, string $name, string $type = 'default') use ($refusal, $urlAction, $langs) {
        $why = $refusal($name);
        return dolGetButtonAction($why, $langs->trans($label), $type, $urlAction . $name, '', $why === '', ['attr' => ['title' => $why]]);
    };

    // "Prepare next month": what it does, before the click
    $nextHelp = $object->canPrepareNext() ? $langs->trans('NewsletterPrepareNextHelp') : '';

    print '<div class="tabsAction">';
    if ($object->isEditable()) {
        print $button('Modify', 'edit');
    }
    print $button('NewsletterSendTest', 'send_test');
    if ($status === ReedcrmNewsletter::STATUS_DRAFT) {
        print $button($object->isAfterCreation() ? 'NewsletterActivate' : 'NewsletterSchedule', 'schedule');
    }
    if ($object->isEditable()) {
        print $button('NewsletterSendNow', 'send_now');
    }
    if ($status !== ReedcrmNewsletter::STATUS_DRAFT) {
        print $button('NewsletterSetDraft', 'setdraft');
    }
    if ((int) $object->nb_errors > 0 && $status !== ReedcrmNewsletter::STATUS_DRAFT) {
        print $button('NewsletterRetryErrors', 'retry_errors');
    }
    // Once planned or sent, the next month is one click away; prepared already, the button opens it
    if ($object->canPrepareNext() && $status !== ReedcrmNewsletter::STATUS_DRAFT) {
        if ($nextEdition !== null) {
            print dolGetButtonAction('', $langs->trans('NewsletterOpenNext'), 'default', $cardUrl . '?id=' . $nextEdition->id, '', true);
        } else {
            $why = $refusal('add_next');
            print dolGetButtonAction($why, $langs->trans('NewsletterPrepareNext'), 'default', $urlAction . 'add_next', '', $why === '', ['attr' => ['title' => $why !== '' ? $why : $nextHelp]]);
        }
    }
    print $button('ToClone', 'clone');
    print $button('Delete', 'delete', 'delete');
    print '</div>';
}

// Under the buttons: the results of the editions, then the log on the left and the latest events on the right
require __DIR__ . '/../../core/tpl/newsletter/newsletter_results.tpl.php';

$formActions = null;
if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formactions.class.php';
    $formActions = new FormActions($db);
}
require __DIR__ . '/../../core/tpl/newsletter/newsletter_activity.tpl.php';

llxFooter();
$db->close();

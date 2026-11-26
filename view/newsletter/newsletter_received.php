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
 * \file    view/newsletter/newsletter_received.php
 * \ingroup reedcrm
 * \brief   "Newsletters" tab of a contact or of a third party: the mails of the newsletters received, and for a contact
 *          whether it still receives them, with a way to subscribe it again at its request
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/contact.lib.php';

require_once __DIR__ . '/../../class/reedcrmnewsletter.class.php';
require_once __DIR__ . '/../../lib/reedcrm_newsletter.lib.php';

global $conf, $db, $hookmanager, $langs, $user;

saturne_load_langs(['companies', 'mails']);

$contactId = GETPOSTINT('contactid');
$socid     = GETPOSTINT('socid');
$action    = GETPOST('action', 'aZ09');
$confirm   = GETPOST('confirm', 'alpha');

$isThirdparty = $contactId <= 0;
$object       = $isThirdparty ? new Societe($db) : new Contact($db);
$form         = new Form($db);

$hookmanager->initHooks([$isThirdparty ? 'thirdpartynewsletters' : 'contactnewsletters', 'globalcard']);

$permissiontoread = $user->hasRight('reedcrm', 'newsletter', 'read');
$permissiontoadd  = $user->hasRight('reedcrm', 'newsletter', 'write');

// The rights of the contact or of the third party first: a sales representative opens the ones he follows only
if ($isThirdparty) {
    restrictedArea($user, 'societe', $socid, '&societe');
    $result = $object->fetch($socid);
} else {
    restrictedArea($user, 'contact', $contactId, 'socpeople&societe', '', '', 'rowid', 0);
    $result = $object->fetch($contactId);
}
if (!$permissiontoread || $result <= 0) {
    accessforbidden();
}

$pageUrl = dol_buildpath('/reedcrm/view/newsletter/newsletter_received.php', 1) . '?' . ($isThirdparty ? 'socid=' . $object->id : 'contactid=' . $object->id);

/*
 * Actions
 */

// Subscribe the contact again: asked by a confirmation, the person must have asked for it
if (!$isThirdparty && $action === 'confirm_resubscribe' && $confirm === 'yes') {
    if (!$permissiontoadd) {
        setEventMessages($langs->trans('NewsletterActionNoRight'), null, 'errors');
    } elseif (!isValidEmail((string) $object->email)) {
        setEventMessages($langs->trans('ErrorBadEMail', (string) $object->email), null, 'errors');
    } else {
        $previous = null;
        $result   = reedcrm_newsletter_resubscribe($db, (string) $object->email, (int) $conf->entity, $previous);
        if ($result > 0) {
            // The proof of who did it, when, and of the unsubscribe removed
            reedcrm_newsletter_trace_resubscribe($db, $user, $object, (array) $previous, reedcrm_newsletter_fetch_contacts_by_email($db, (string) $object->email, (int) $object->id), $langs);
            setEventMessages($langs->trans('NewsletterResubscribed', (string) $object->email), null);
        } elseif ($result === 0) {
            setEventMessages($langs->trans('NewsletterNotUnsubscribed', (string) $object->email), null, 'warnings');
        } else {
            setEventMessages($db->lasterror(), null, 'errors');
        }
    }
    header('Location: ' . $pageUrl);
    exit;
}

/*
 * View
 */

$title = $langs->trans('Newsletters') . ' - ' . ($isThirdparty ? $object->name : $object->getFullName($langs));
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-reedcrm page-newsletter-received');

if ($isThirdparty) {
    $head = societe_prepare_head($object);
    print dol_get_fiche_head($head, 'reedcrmnewsletter', $langs->trans('ThirdParty'), -1, $object->picto);
    $linkback = '<a href="' . DOL_URL_ROOT . '/societe/list.php?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';
    dol_banner_tab($object, 'socid', $linkback, ($user->socid ? 0 : 1), 'rowid', 'nom');
} else {
    $head = contact_prepare_head($object);
    print dol_get_fiche_head($head, 'reedcrmnewsletter', $langs->trans('Contact'), -1, 'contact');
    $linkback    = '<a href="' . DOL_URL_ROOT . '/contact/list.php?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';
    $morehtmlref = '<div class="refidno">';
    if ($object->socid > 0) {
        $thirdparty = new Societe($db);
        $thirdparty->fetch($object->socid);
        $morehtmlref .= $thirdparty->getNomUrl(1);
    }
    $morehtmlref .= '</div>';
    dol_banner_tab($object, 'id', $linkback, 1, 'rowid', 'ref', $morehtmlref);
}

// Data: the mails received, and for a contact where its address stands
$received      = reedcrm_newsletter_fetch_received($db, $isThirdparty ? 'thirdparty' : 'contact', (int) $object->id, 200);
$receivedCount = reedcrm_newsletter_count_received($db, $isThirdparty ? 'thirdparty' : 'contact', (int) $object->id);
$subscription  = null;
$unsubscribedContacts = [];
$sharedContacts       = [];
$writers              = [];
if (!$isThirdparty) {
    $email        = trim((string) $object->email);
    $subscription = [
        'email' => $email,
        'valid' => isValidEmail($email),
        'since' => isValidEmail($email) ? reedcrm_newsletter_unsubscribed_since($db, $email, (int) $conf->entity) : 0,
    ];
    $sharedContacts = $subscription['valid'] ? reedcrm_newsletter_fetch_contacts_by_email($db, $email, (int) $object->id) : [];
    if ($subscription['since'] > 0 && !$permissiontoadd) {
        $writers = reedcrm_newsletter_fetch_writers($db);
    }
} else {
    $unsubscribedContacts = reedcrm_newsletter_fetch_unsubscribed_contacts($db, (int) $object->id, (int) $conf->entity);
}
$compact = false;

// Confirmation of the new subscription: the other contacts of the address are subscribed again with it, said so
if (!$isThirdparty && $action === 'resubscribe' && $permissiontoadd) {
    $question = $langs->trans('NewsletterResubscribeConfirm', (string) $object->email);
    if (!empty($sharedContacts)) {
        $sharedNames = [];
        foreach ($sharedContacts as $sharedContact) {
            $sharedNames[] = dolGetFirstLastname((string) $sharedContact->firstname, (string) $sharedContact->lastname);
        }
        $question .= '<br><br>' . $langs->trans('NewsletterResubscribeShared', implode(', ', $sharedNames));
    }
    print $form->formconfirm($pageUrl, $langs->trans('NewsletterResubscribe'), $question, 'confirm_resubscribe', '', 'no', 1);
}

print '<div class="underbanner clearboth"></div>';

require __DIR__ . '/../../core/tpl/newsletter/newsletter_received.tpl.php';

print dol_get_fiche_end();

llxFooter();
$db->close();

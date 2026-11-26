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
 * \file    lib/reedcrm_newsletter.lib.php
 * \ingroup reedcrm
 * \brief   Library functions of the newsletters and mail reminders sent by tags
 */

/**
 * Prepare array of tabs for the newsletter card
 *
 * @param  CommonObject $object Newsletter
 * @return array                Array of tabs
 */
function newsletter_prepare_head(CommonObject $object): array
{
    global $conf, $langs;

    saturne_load_langs();

    $h    = 0;
    $head = [];

    $head[$h][0] = dol_buildpath('/reedcrm/view/newsletter/newsletter_card.php', 1) . '?id=' . $object->id;
    $head[$h][1] = $langs->trans('Newsletter');
    $head[$h][2] = 'card';
    $h++;

    complete_head_from_modules($conf, $langs, $object, $head, $h, 'newsletter@reedcrm');

    return $head;
}

/**
 * Key of the unsubscribe link of an address: only the mail received gives the link that unsubscribes it
 *
 * @param  string $email  Address
 * @param  int    $entity Entity of the newsletter
 * @return string         Key
 */
function reedcrm_newsletter_unsubscribe_key(string $email, int $entity): string
{
    global $conf;

    $secret = !empty($conf->file->instance_unique_id) ? $conf->file->instance_unique_id : DOL_DOCUMENT_ROOT . getDolGlobalString('MAIN_INFO_SOCIETE_NOM');

    return substr(hash_hmac('sha256', 'reedcrm_newsletter_unsubscribe|' . dol_strtolower(trim($email)) . '|' . $entity, $secret), 0, 32);
}

/**
 * Public URL that unsubscribes an address from the newsletters
 *
 * @param  string $email        Address
 * @param  int    $entity       Entity of the newsletter
 * @param  int    $newsletterId Newsletter of the mail: the unsubscribe is counted in its results. Not part of the key:
 *                              a recipient could only move his own unsubscribe to another newsletter
 * @return string               URL with the domain of the configuration, the mails are also sent by the cron
 */
function reedcrm_newsletter_unsubscribe_url(string $email, int $entity, int $newsletterId = 0): string
{
    $params = [
        'email'  => trim($email),
        'entity' => $entity,
        'key'    => reedcrm_newsletter_unsubscribe_key($email, $entity),
    ];
    if ($newsletterId > 0) {
        $params['nl'] = $newsletterId;
    }

    return dol_buildpath('/reedcrm/public/newsletter/unsubscribe.php', 3) . '?' . http_build_query($params, '', '&');
}

/**
 * Count an unsubscribe in the results of the newsletter whose link was clicked: its last mail sent to the address
 * gets the date. A newsletter that never mailed the address counts nothing
 *
 * @param  DoliDB $db           Database handler
 * @param  int    $newsletterId Newsletter of the link
 * @param  string $email        Address unsubscribed
 * @param  int    $entity       Entity
 * @return bool                 Whether a mail of the newsletter got the unsubscribe
 */
function reedcrm_newsletter_record_unsubscribe(DoliDB $db, int $newsletterId, string $email, int $entity): bool
{
    if ($newsletterId <= 0) {
        return false;
    }

    $sql  = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send';
    $sql .= ' WHERE fk_newsletter = ' . $newsletterId . ' AND entity = ' . $entity . ' AND status = 1';
    $sql .= " AND LOWER(email) = '" . $db->escape(dol_strtolower(trim($email))) . "' AND date_unsubscribe IS NULL";
    $sql .= ' ORDER BY date_send DESC, rowid DESC' . $db->plimit(1);
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;
    if (!$obj) {
        return false;
    }

    return (bool) $db->query('UPDATE ' . MAIN_DB_PREFIX . "reedcrm_newsletter_send SET date_unsubscribe = '" . $db->idate(dol_now()) . "' WHERE rowid = " . ((int) $obj->rowid));
}

/**
 * Seconds during which an unsubscribe can be undone from the public page, "I made a mistake"
 *
 * @return int
 */
function reedcrm_newsletter_undo_delay(): int
{
    return 3600;
}

/**
 * An unsubscribe undone from the public page: the results of the newsletter do not count it anymore
 *
 * @param  DoliDB $db           Database handler
 * @param  int    $newsletterId Newsletter of the link
 * @param  string $email        Address
 * @param  int    $entity       Entity
 * @return void
 */
function reedcrm_newsletter_unrecord_unsubscribe(DoliDB $db, int $newsletterId, string $email, int $entity): void
{
    if ($newsletterId <= 0) {
        return;
    }

    $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send SET date_unsubscribe = NULL';
    $sql .= ' WHERE fk_newsletter = ' . $newsletterId . ' AND entity = ' . $entity;
    $sql .= " AND LOWER(email) = '" . $db->escape(dol_strtolower(trim($email))) . "'";
    $sql .= " AND date_unsubscribe >= '" . $db->idate(dol_now() - reedcrm_newsletter_undo_delay()) . "'";
    $db->query($sql);
}

/**
 * Subscribe again an address unsubscribed from the mass emails, at the request of the person
 *
 * The line of the unsubscribe is removed (the emailings of Dolibarr read the same table): its date and its IP are given
 * back, the caller keeps them in a trace, see reedcrm_newsletter_trace_resubscribe()
 *
 * @param  DoliDB     $db       Database handler
 * @param  string     $email    Address
 * @param  int        $entity   Entity
 * @param  array|null $previous Set to the unsubscribe removed: [date, ip]
 * @return int                  1 if subscribed again, 0 if it was not unsubscribed, < 0 if KO
 */
function reedcrm_newsletter_resubscribe(DoliDB $db, string $email, int $entity, ?array &$previous = null): int
{
    $where = ' WHERE entity = ' . $entity . " AND LOWER(email) = '" . $db->escape(dol_strtolower(trim($email))) . "'";

    $previous = null;
    $resql    = $db->query('SELECT date_creat, ip FROM ' . MAIN_DB_PREFIX . 'mailing_unsubscribe' . $where . ' ORDER BY date_creat' . $db->plimit(1));
    $obj      = $resql ? $db->fetch_object($resql) : null;
    if ($obj) {
        $previous = ['date' => (int) $db->jdate($obj->date_creat), 'ip' => (string) $obj->ip];
    }

    $resql = $db->query('DELETE FROM ' . MAIN_DB_PREFIX . 'mailing_unsubscribe' . $where);
    if (!$resql) {
        return -1;
    }

    return $db->affected_rows($resql) > 0 ? 1 : 0;
}

/**
 * Other active contacts that share an address: subscribing one again subscribes them all, the newsletters read the
 * address only
 *
 * @param  DoliDB $db        Database handler
 * @param  string $email     Address
 * @param  int    $excludeId Contact left out (the one subscribed again)
 * @return array             Lines: rowid, firstname, lastname, fk_soc, thirdparty_name
 */
function reedcrm_newsletter_fetch_contacts_by_email(DoliDB $db, string $email, int $excludeId = 0): array
{
    $sql  = 'SELECT sp.rowid, sp.firstname, sp.lastname, sp.fk_soc, s.nom as thirdparty_name FROM ' . MAIN_DB_PREFIX . 'socpeople as sp';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = sp.fk_soc';
    $sql .= " WHERE LOWER(sp.email) = '" . $db->escape(dol_strtolower(trim($email))) . "'";
    $sql .= ' AND sp.rowid <> ' . $excludeId . ' AND sp.entity IN (' . getEntity('contact') . ')';
    $sql .= ' ORDER BY sp.lastname, sp.firstname' . $db->plimit(20);

    $lines = [];
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $lines[] = $obj;
        }
        $db->free($resql);
    }

    return $lines;
}

/**
 * Trace of a new subscription on the contact: an event of the agenda says who subscribed the address again, when, and
 * the unsubscribe it removed (date, IP). It is the proof asked when the person complains
 *
 * @param  DoliDB    $db       Database handler
 * @param  User      $user     User who subscribed again
 * @param  Contact   $contact  Contact
 * @param  array     $previous Unsubscribe removed, see reedcrm_newsletter_resubscribe()
 * @param  array     $shared   Other contacts of the address, see reedcrm_newsletter_fetch_contacts_by_email()
 * @param  Translate $langs    Translations
 * @return int                 Id of the event, 0 when the agenda is off, < 0 if KO
 */
function reedcrm_newsletter_trace_resubscribe(DoliDB $db, User $user, Contact $contact, array $previous, array $shared, Translate $langs): int
{
    if (!isModEnabled('agenda')) {
        dol_syslog(__FUNCTION__ . ' ' . $contact->email . ' subscribed again by ' . $user->login . ', unsubscribed on ' . dol_print_date($previous['date'] ?? 0, 'dayhour') . ' from ' . ($previous['ip'] ?? ''), LOG_NOTICE);
        return 0;
    }

    require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

    $names = [];
    foreach ($shared as $other) {
        $names[] = dolGetFirstLastname((string) $other->firstname, (string) $other->lastname);
    }
    $note  = $langs->transnoentities('NewsletterResubscribeEventNote', $user->getFullName($langs), dol_print_date(dol_now(), 'dayhour'), (string) $contact->email, !empty($previous['date']) ? dol_print_date($previous['date'], 'dayhour') : '?', (string) ($previous['ip'] ?? ''));
    if (!empty($names)) {
        $note .= "\n" . $langs->transnoentities('NewsletterResubscribeEventShared', implode(', ', $names));
    }

    $actionComm               = new ActionComm($db);
    $actionComm->type_code    = 'AC_OTH_AUTO';
    $actionComm->code         = 'AC_REEDCRM_NL_RESUBSCRIBE';
    $actionComm->label        = $langs->transnoentities('NewsletterResubscribeEventLabel', (string) $contact->email);
    $actionComm->note_private = dol_nl2br(dol_escape_htmltag($note));
    $actionComm->datep        = dol_now();
    $actionComm->datef        = $actionComm->datep;
    $actionComm->percentage   = -1;
    $actionComm->userownerid  = $user->id;
    $actionComm->socid        = (int) $contact->socid;
    $actionComm->contact_id   = (int) $contact->id;
    $actionComm->socpeopleassigned = [(int) $contact->id => ['id' => (int) $contact->id, 'mandatory' => 0, 'answer_status' => 0, 'transparency' => 0]];

    $eventId = $actionComm->create($user, 1);
    if ($eventId <= 0) {
        dol_syslog(__FUNCTION__ . ' ' . $actionComm->error, LOG_WARNING);
        return -1;
    }

    return $eventId;
}

/**
 * Mails of the newsletters received by a contact, or by the contacts and the address of a third party, the newest
 * first, with the newsletter they belong to
 *
 * @param  DoliDB $db    Database handler
 * @param  string $by    contact or thirdparty
 * @param  int    $id    Id of the contact or of the third party
 * @param  int    $limit Most lines, 0 = all
 * @return array         Lines: fk_newsletter, ref, label, date_send, status, error_msg, email, fk_contact,
 *                       firstname, lastname, date_unsubscribe
 */
function reedcrm_newsletter_fetch_received(DoliDB $db, string $by, int $id, int $limit = 0): array
{
    $sql  = 'SELECT ns.fk_newsletter, n.ref, n.label, ns.date_send, ns.status, ns.error_msg, ns.email, ns.fk_contact,';
    $sql .= ' sp.firstname, sp.lastname, ns.date_unsubscribe';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send as ns';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'reedcrm_newsletter as n ON n.rowid = ns.fk_newsletter';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'socpeople as sp ON sp.rowid = ns.fk_contact';
    $sql .= ' WHERE ' . reedcrm_newsletter_received_where($by, $id);
    $sql .= ' AND n.entity IN (' . getEntity('newsletter') . ')';
    $sql .= ' AND ns.status <> 0';
    $sql .= ' ORDER BY ns.date_send DESC, ns.rowid DESC';
    if ($limit > 0) {
        $sql .= $db->plimit($limit);
    }

    $lines = [];
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $obj->date_send        = $db->jdate($obj->date_send);
            $obj->date_unsubscribe = $db->jdate($obj->date_unsubscribe);
            $lines[]               = $obj;
        }
        $db->free($resql);
    }

    return $lines;
}

/**
 * Condition on the log of the mails received: by a third party (its contacts and its own address), or by a contact,
 * that is the mails of the contact and the ones sent to its address through another contact. Two contacts sharing an
 * address get one mail, logged on the first one: the other one received it all the same
 *
 * @param  string $by contact or thirdparty
 * @param  int    $id Id of the contact or of the third party
 * @return string     SQL condition on the alias ns
 */
function reedcrm_newsletter_received_where(string $by, int $id): string
{
    if ($by === 'thirdparty') {
        return 'ns.fk_soc = ' . $id;
    }

    return '(ns.fk_contact = ' . $id . ' OR LOWER(ns.email) = (SELECT LOWER(rsp.email) FROM ' . MAIN_DB_PREFIX . "socpeople as rsp WHERE rsp.rowid = " . $id . " AND rsp.email <> ''))";
}

/**
 * Contacts of a third party whose address is unsubscribed from the mass emails
 *
 * @param  DoliDB $db     Database handler
 * @param  int    $socId  Third party
 * @param  int    $entity Entity
 * @return array          Lines: rowid, firstname, lastname, email, date_creat (of the unsubscribe)
 */
function reedcrm_newsletter_fetch_unsubscribed_contacts(DoliDB $db, int $socId, int $entity): array
{
    $sql  = 'SELECT sp.rowid, sp.firstname, sp.lastname, sp.email, mu.date_creat';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'socpeople as sp';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'mailing_unsubscribe as mu ON LOWER(mu.email) = LOWER(sp.email) AND mu.entity = ' . $entity;
    $sql .= ' WHERE sp.fk_soc = ' . $socId . " AND sp.email IS NOT NULL AND sp.email <> ''";
    $sql .= ' ORDER BY sp.lastname, sp.firstname';

    $lines = [];
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $obj->date_creat = $db->jdate($obj->date_creat);
            $lines[]         = $obj;
        }
        $db->free($resql);
    }

    return $lines;
}

/**
 * Number of mails of the newsletters received by a contact or a third party, the ones still being sent aside
 *
 * @param  DoliDB $db Database handler
 * @param  string $by contact or thirdparty
 * @param  int    $id Id of the contact or of the third party
 * @return int
 */
function reedcrm_newsletter_count_received(DoliDB $db, string $by, int $id): int
{
    $sql  = 'SELECT COUNT(*) as nb FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter_send as ns';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'reedcrm_newsletter as n ON n.rowid = ns.fk_newsletter';
    $sql .= ' WHERE ' . reedcrm_newsletter_received_where($by, $id);
    $sql .= ' AND n.entity IN (' . getEntity('newsletter') . ') AND ns.status <> 0';

    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? (int) $obj->nb : 0;
}

/**
 * Unsubscribe link of a test mail: the page explains that the real mail carries the link of its recipient, and
 * unsubscribes nobody. A test is filled in with the first recipient, its own link would unsubscribe a customer
 *
 * @param  int    $entity Entity of the newsletter
 * @return string         URL
 */
function reedcrm_newsletter_unsubscribe_test_url(int $entity): string
{
    return dol_buildpath('/reedcrm/public/newsletter/unsubscribe.php', 3) . '?' . http_build_query(['test' => 1, 'entity' => $entity], '', '&');
}

/**
 * Date an address was unsubscribed from the mass emails
 *
 * @param  DoliDB $db     Database handler
 * @param  string $email  Address
 * @param  int    $entity Entity
 * @return int            Date, 0 when it is not unsubscribed
 */
function reedcrm_newsletter_unsubscribed_since(DoliDB $db, string $email, int $entity): int
{
    $sql   = 'SELECT date_creat FROM ' . MAIN_DB_PREFIX . 'mailing_unsubscribe';
    $sql  .= ' WHERE entity = ' . ((int) $entity) . " AND LOWER(email) = '" . $db->escape(dol_strtolower(trim($email))) . "'";
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? max(1, (int) $db->jdate($obj->date_creat)) : 0;
}

/**
 * Unsubscribe an address from the mass emails: the newsletters, the emailings of Dolibarr and the
 * "No mass emailing" of the contacts all read the same table
 *
 * @param  DoliDB $db     Database handler
 * @param  string $email  Address
 * @param  int    $entity Entity
 * @return int            1 if unsubscribed, 0 if it already was, < 0 if KO
 */
function reedcrm_newsletter_unsubscribe(DoliDB $db, string $email, int $entity): int
{
    $email = trim($email);

    $sql   = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'mailing_unsubscribe';
    $sql  .= ' WHERE entity = ' . ((int) $entity) . " AND LOWER(email) = '" . $db->escape(dol_strtolower($email)) . "'";
    $resql = $db->query($sql);
    if (!$resql) {
        return -1;
    }
    if ($db->num_rows($resql) > 0) {
        return 0;
    }

    $sql  = 'INSERT INTO ' . MAIN_DB_PREFIX . 'mailing_unsubscribe (entity, email, unsubscribegroup, ip, date_creat)';
    $sql .= ' VALUES (' . ((int) $entity) . ", '" . $db->escape($email) . "', '', '" . $db->escape(getUserRemoteIP()) . "', '" . $db->idate(dol_now()) . "')";

    return $db->query($sql) ? 1 : -1;
}

/**
 * Frame of the mails written with an email template: the editor of the templates removes the styles of the
 * blocks, the template brings the text and the newsletter the layout
 *
 * @param  string $content HTML of the template, filled in
 * @return string          Whole HTML document
 */
function reedcrm_newsletter_frame(string $content): string
{
    // Inline styles: the mail clients ignore the stylesheets
    $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>';
    $html .= '<body style="margin:0;padding:24px 12px;background:#f4f5f7;">';
    $html .= '<div style="max-width:640px;margin:0 auto;padding:24px;background:#ffffff;border-radius:6px;font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.55;color:#1f2933;">';
    $html .= $content;
    $html .= '</div></body></html>';

    return $html;
}

/**
 * Footer added to the mails whose text has no unsubscribe link of its own
 *
 * @param  string    $unsubscribeLink HTML link
 * @param  Translate $outputLangs     Language of the mail
 * @return string                     HTML
 */
function reedcrm_newsletter_unsubscribe_footer(string $unsubscribeLink, Translate $outputLangs): string
{
    return '<p style="margin:24px auto 0;max-width:640px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.5;color:#4b5563;text-align:center;">' . $outputLangs->transnoentitiesnoconv('NewsletterUnsubscribeFooter', $unsubscribeLink) . '</p>';
}

/**
 * Text of a newsletter without the keys that read the setup of Dolibarr: __[CONSTANT]__ gives the value of any
 * constant (the login of the SMTP server, the salt of the passwords), __(Key)__ a translation whose text may hold
 * such a constant. Whoever writes a newsletter would read them on its card and in the mails
 *
 * @param  string $text Subject or body, before the substitutions
 * @return string
 */
function reedcrm_newsletter_strip_setup_keys(string $text): string
{
    return (string) preg_replace('/__\[[^\]\r\n]*\]__|__\([^)\r\n]*\)__/', '', $text);
}

/**
 * Keys of the recipient written inside the links and the images of a body: encoded in a link (an address with a
 * "+" stays one), left out in the address of an image. An image loaded from another server with the address of the
 * recipient in it would tell that server who opens the mail
 *
 * @param  string $html           Body, before the keys of the recipient are replaced
 * @param  array  $values         Values of the keys as text, see ReedcrmNewsletter::getRecipientSubstitutions()
 * @param  string $unsubscribeUrl Unsubscribe URL, escaped for an attribute
 * @return string
 */
function reedcrm_newsletter_substitute_in_attributes(string $html, array $values, string $unsubscribeUrl): string
{
    if (strpos($html, '__') === false) {
        return $html;
    }

    return (string) preg_replace_callback('/(\s(?:href|src|srcset|background)\s*=\s*)(["\'])(.*?)\2/is', function ($matches) use ($values, $unsubscribeUrl) {
        if (strpos($matches[3], '__') === false) {
            return $matches[0];
        }
        $isLink = stripos(ltrim($matches[1]), 'href') === 0;
        $map    = [];
        foreach ($values as $key => $value) {
            if (!$isLink) {
                $map[$key] = '';
            } elseif (in_array($key, ['__UNSUBSCRIBE__', '__UNSUBSCRIBE_URL__'], true)) {
                $map[$key] = $unsubscribeUrl;
            } else {
                $map[$key] = htmlspecialchars(rawurlencode((string) $value), ENT_QUOTES, 'UTF-8');
            }
        }

        return $matches[1] . $matches[2] . strtr($matches[3], $map) . $matches[2];
    }, $html);
}

/**
 * Clean the HTML typed in the HTML mode
 *
 * The filters of GETPOST() are not used: restricthtml empties a whole document (html, head, body) and
 * restricthtmlallowunvalid removes the <style> blocks, which a newsletter needs. The HTML is parsed (the browsers
 * read "<svg/onload>", "javascript&colon;" or a tab in "java	script:" as code, a regular expression does not) and
 * only an allow list is kept: the tags of a mail layout, their attributes but the on* ones, the links in http,
 * https, mailto, tel or cid (and data: images), the meta of the charset and of the viewport
 *
 * @param  string $html HTML typed
 * @return string       HTML without active content
 */
function reedcrm_newsletter_clean_html(string $html): string
{
    if (trim($html) === '' || !dol_textishtml($html)) {
        return $html;
    }

    $isDocument = (bool) preg_match('/<html[\s>]/i', $html);
    $doctype    = preg_match('/^\s*(<!DOCTYPE[^>]*>)/i', $html, $matches) ? $matches[1] : '';

    // The characters out of ASCII as entities: libxml then keeps the accents whatever charset it guesses
    $source = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');
    if (!$isDocument) {
        $source = '<html><body><div id="reedcrm-newsletter-fragment">' . $source . '</div></body></html>';
    }

    $previous = libxml_use_internal_errors(true);
    $dom      = new DOMDocument();
    $loaded   = $dom->loadHTML($source, LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        return '';
    }

    reedcrm_newsletter_clean_node($dom, $dom);

    // Node by node: the whole document would come out with named entities instead of the UTF-8 characters
    $out = '';
    if ($isDocument) {
        foreach ($dom->childNodes as $node) {
            if (!$node instanceof DOMDocumentType) {
                $out .= $dom->saveHTML($node);
            }
        }
        return ($doctype !== '' ? $doctype . "\n" : '') . $out;
    }

    $fragment = $dom->getElementById('reedcrm-newsletter-fragment');
    if ($fragment) {
        foreach ($fragment->childNodes as $node) {
            $out .= $dom->saveHTML($node);
        }
    }

    return $out;
}

/**
 * Clean the children of a node, see reedcrm_newsletter_clean_html()
 *
 * @param  DOMDocument $dom  Document
 * @param  DOMNode     $node Node whose children are cleaned
 * @return void
 */
function reedcrm_newsletter_clean_node(DOMDocument $dom, DOMNode $node): void
{
    // Removed with their content: they run code, load a page or send a form
    $dangerous = ['script', 'noscript', 'template', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'param', 'base', 'link', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math', 'video', 'audio', 'source', 'track', 'canvas', 'dialog', 'portal'];
    // Kept: the layout of a mail. Any other tag is replaced by its content
    $allowed = ['html', 'head', 'body', 'title', 'meta', 'style', 'div', 'span', 'p', 'br', 'hr', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'colgroup', 'col', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'small', 'big', 'sub', 'sup', 'blockquote', 'pre', 'code', 'center', 'font', 'section', 'article', 'header', 'footer', 'nav', 'main', 'aside', 'figure', 'figcaption', 'picture', 'abbr', 'address', 'cite', 'del', 'ins', 'mark', 'q', 'time', 'wbr', 'label'];
    $urlAttributes = ['href', 'src', 'background', 'action', 'formaction', 'poster', 'cite', 'longdesc', 'usemap', 'dynsrc', 'lowsrc', 'xlink:href', 'data', 'codebase'];

    // A copy of the list: the loop removes and replaces children
    $children = [];
    foreach ($node->childNodes as $child) {
        $children[] = $child;
    }

    foreach ($children as $child) {
        if ($child instanceof DOMComment) {
            // Conditional comments of Outlook are kept, unless they carry code
            if (preg_match('/<\s*script|javascript\s*:|vbscript\s*:/i', $child->data)) {
                $node->removeChild($child);
            }
            continue;
        }
        if (!$child instanceof DOMElement) {
            continue;
        }

        $tag = strtolower($child->nodeName);
        if (in_array($tag, $dangerous, true)) {
            $node->removeChild($child);
            continue;
        }

        reedcrm_newsletter_clean_node($dom, $child);

        if (!in_array($tag, $allowed, true)) {
            while ($child->firstChild) {
                $node->insertBefore($child->firstChild, $child);
            }
            $node->removeChild($child);
            continue;
        }

        if ($tag === 'meta') {
            $name      = strtolower((string) $child->getAttribute('name'));
            $httpEquiv = strtolower((string) $child->getAttribute('http-equiv'));
            $keep      = $child->hasAttribute('charset') || $httpEquiv === 'content-type' || in_array($name, ['viewport', 'format-detection', 'x-apple-disable-message-reformatting', 'color-scheme', 'supported-color-schemes'], true);
            if (!$keep) {
                $node->removeChild($child);
                continue;
            }
        }

        if ($tag === 'style') {
            $child->textContent = reedcrm_newsletter_clean_css($child->textContent);
        }

        $attributes = [];
        foreach ($child->attributes as $attribute) {
            $attributes[] = $attribute->nodeName;
        }
        foreach ($attributes as $attributeName) {
            $name  = strtolower($attributeName);
            $value = (string) $child->getAttribute($attributeName);
            if (strpos($name, 'on') === 0 || $name === 'srcdoc' || $name === 'formaction') {
                $child->removeAttribute($attributeName);
            } elseif (in_array($name, $urlAttributes, true) || $name === 'srcset') {
                if (!reedcrm_newsletter_is_safe_url($value, $tag === 'img' && $name === 'src')) {
                    $child->removeAttribute($attributeName);
                }
            } elseif ($name === 'style') {
                $child->setAttribute($attributeName, reedcrm_newsletter_clean_css($value));
            }
        }
    }
}

/**
 * Whether a link of the HTML can be kept: no scheme (relative link, anchor, __KEY__), http, https, mailto, tel,
 * cid; data: for the images only. The value is the one decoded by the parser, entities and control characters
 * are already resolved or removed here
 *
 * @param  string $url        Value of the attribute
 * @param  bool   $allowImage Whether a data:image is accepted (src of an img)
 * @return bool
 */
function reedcrm_newsletter_is_safe_url(string $url, bool $allowImage): bool
{
    // libxml does not know the entities added by HTML5 ("&colon;"): decoded here as a browser would
    $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // The browsers ignore the spaces and control characters inside a scheme: "java\tscript:" is "javascript:"
    $normalized = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $url));
    // srcset: several URLs separated by commas, each one checked
    foreach (explode(',', $normalized) as $part) {
        if (!preg_match('/^([a-z][a-z0-9+.-]*):/', $part, $matches)) {
            continue;
        }
        if (in_array($matches[1], ['http', 'https', 'mailto', 'tel', 'cid'], true)) {
            continue;
        }
        if ($allowImage && preg_match('#^data:image/(png|jpe?g|gif|webp);#', $part)) {
            continue;
        }
        return false;
    }

    return true;
}

/**
 * Clean a style block or a style attribute: no import of another sheet, no old script expressions, no url()
 * outside http, https, cid and data: images
 *
 * @param  string $css CSS
 * @return string      CSS
 */
function reedcrm_newsletter_clean_css(string $css): string
{
    // CSS escapes ("\6a avascript") and comments can hide a word: removed before the checks
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    $css = preg_replace('/@import[^;]*;?/i', '', $css);
    $css = preg_replace('/(expression\s*\(|behavior\s*:|-moz-binding\s*:)/i', 'x-removed(', $css);
    $css = preg_replace_callback('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/is', function ($matches) {
        $url = $matches[2];
        if (strpos($url, '\\') !== false) {
            return 'none';
        }
        $normalized = strtolower(preg_replace('/[\x00-\x20\x7f]+/', '', $url));
        if (preg_match('/^([a-z][a-z0-9+.-]*):/', $normalized, $scheme) && !in_array($scheme[1], ['http', 'https', 'cid'], true) && !preg_match('#^data:image/(png|jpe?g|gif|webp);#', $normalized)) {
            return 'none';
        }
        return $matches[0];
    }, $css);

    return (string) $css;
}

/**
 * Email templates offered in the template mode: the ones written for the newsletters, the ones of the contacts
 * or of the projects, and the ones for all
 *
 * @param  DoliDB $db         Database handler
 * @param  User   $user       Current user, his private templates are offered too
 * @param  string $targetType contact or project, '' = both
 * @return array              [id => [label, type, topic, content, lang]]
 */
function reedcrm_newsletter_get_email_templates(DoliDB $db, User $user, string $targetType = ''): array
{
    $types = ["'reedcrm_newsletter'", "'all'"];
    if ($targetType !== 'project') {
        $types[] = "'contact'";
    }
    if ($targetType !== 'contact') {
        $types[] = "'project'";
    }

    $sql  = 'SELECT rowid, label, type_template, topic, content, lang FROM ' . MAIN_DB_PREFIX . 'c_email_templates';
    $sql .= ' WHERE entity IN (' . getEntity('c_email_templates') . ')';
    $sql .= ' AND active = 1';
    $sql .= ' AND type_template IN (' . implode(',', $types) . ')';
    $sql .= ' AND (private = 0 OR fk_user = ' . ((int) $user->id) . ')';
    $sql .= ' ORDER BY type_template, position, label';

    $templates = [];
    $resql     = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $templates[(int) $obj->rowid] = [
                'label'   => (string) $obj->label,
                'type'    => (string) $obj->type_template,
                'topic'   => (string) $obj->topic,
                'content' => (string) $obj->content,
                'lang'    => (string) $obj->lang,
            ];
        }
        $db->free($resql);
    }

    return $templates;
}

/**
 * Keys that can be written in the subject and the body, with what they become
 *
 * @return array [__KEY__ => translation key of the description]
 */
function reedcrm_newsletter_substitution_keys(): array
{
    return [
        '__CIVILITY__'           => 'NewsletterKeyCivility',
        '__FIRSTNAME__'          => 'NewsletterKeyFirstname',
        '__LASTNAME__'           => 'NewsletterKeyLastname',
        '__EMAIL__'              => 'NewsletterKeyEmail',
        '__THIRDPARTY_NAME__'    => 'NewsletterKeyThirdparty',
        // The keys of the projects are offered for the projects only, see reedcrm_newsletter_project_keys()
        '__PROJECT_REF__'        => 'NewsletterKeyProjectRef',
        '__PROJECT_NAME__'       => 'NewsletterKeyProjectName',
        '__PROJECT_DATE_START__' => 'NewsletterKeyProjectDateStart',
        '__PROJECT_DATE_END__'   => 'NewsletterKeyProjectDateEnd',
        '__SENDING_DATE__'       => 'NewsletterKeySendingDate',
        '__UNSUBSCRIBE__'        => 'NewsletterKeyUnsubscribe',
        '__UNSUBSCRIBE_URL__'    => 'NewsletterKeyUnsubscribeUrl',
        '__MYCOMPANY_NAME__'     => 'NewsletterKeyCompany',
    ];
}

/**
 * Keys that mean something for the projects only: a mail to contacts leaves them empty
 *
 * @return string[]
 */
function reedcrm_newsletter_project_keys(): array
{
    return ['__PROJECT_REF__', '__PROJECT_NAME__', '__PROJECT_DATE_START__', '__PROJECT_DATE_END__'];
}

/**
 * Users who may subscribe a contact again (right to write the newsletters, or administrators): a reader is told whom
 * to ask
 *
 * @param  DoliDB $db    Database handler
 * @param  int    $limit Most users
 * @return array         Lines: rowid, firstname, lastname, login
 */
function reedcrm_newsletter_fetch_writers(DoliDB $db, int $limit = 5): array
{
    global $conf;

    $rightSql = 'SELECT id FROM ' . MAIN_DB_PREFIX . "rights_def WHERE module = 'reedcrm' AND perms = 'newsletter' AND subperms = 'write' AND entity = " . ((int) $conf->entity);

    $sql  = 'SELECT DISTINCT u.rowid, u.firstname, u.lastname, u.login FROM ' . MAIN_DB_PREFIX . 'user as u';
    $sql .= ' WHERE u.statut = 1 AND u.entity IN (0, ' . ((int) $conf->entity) . ')';
    $sql .= ' AND (u.admin = 1';
    $sql .= ' OR u.rowid IN (SELECT ur.fk_user FROM ' . MAIN_DB_PREFIX . 'user_rights as ur WHERE ur.fk_id IN (' . $rightSql . '))';
    $sql .= ' OR u.rowid IN (SELECT ugu.fk_user FROM ' . MAIN_DB_PREFIX . 'usergroup_user as ugu INNER JOIN ' . MAIN_DB_PREFIX . 'usergroup_rights as ugr ON ugr.fk_usergroup = ugu.fk_usergroup WHERE ugr.fk_id IN (' . $rightSql . ')))';
    $sql .= ' ORDER BY u.admin ASC, u.lastname, u.firstname' . $db->plimit($limit);

    $lines = [];
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $lines[] = $obj;
        }
        $db->free($resql);
    }

    return $lines;
}

/**
 * Last run of the job of the newsletters, as the scheduled jobs store it
 *
 * @param  DoliDB $db Database handler
 * @return array|null [id, status, last (date of the last run, 0 if never), next, result (0 if OK), output]; null when the job is not there
 */
function reedcrm_newsletter_cron_last_run(DoliDB $db): ?array
{
    global $conf;

    $sql   = 'SELECT rowid, status, datelastrun, datenextrun, lastresult, lastoutput FROM ' . MAIN_DB_PREFIX . 'cronjob';
    $sql  .= " WHERE methodename = 'sendScheduledNewsletters' AND entity IN (0, " . ((int) $conf->entity) . ')';
    $sql  .= ' ORDER BY entity DESC' . $db->plimit(1);
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;
    if (!$obj) {
        return null;
    }

    return [
        'id'     => (int) $obj->rowid,
        'status' => (int) $obj->status,
        'last'   => (int) $db->jdate($obj->datelastrun),
        'next'   => (int) $db->jdate($obj->datenextrun),
        'result' => (string) $obj->lastresult,
        'output' => (string) $obj->lastoutput,
    ];
}

/**
 * Seconds between two runs of the job of the newsletters, 0 when unknown
 *
 * @param  DoliDB $db Database handler
 * @return int
 */
function reedcrm_newsletter_cron_period(DoliDB $db): int
{
    global $conf;

    $sql   = 'SELECT frequency, unitfrequency FROM ' . MAIN_DB_PREFIX . 'cronjob';
    $sql  .= " WHERE methodename = 'sendScheduledNewsletters' AND entity IN (0, " . ((int) $conf->entity) . ')';
    $sql  .= ' ORDER BY entity DESC' . $db->plimit(1);
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;

    return $obj ? max(0, (int) $obj->frequency * (int) $obj->unitfrequency) : 0;
}

/**
 * A duration in seconds as a text, rounded: "45 minutes", "3 hours", "5 days"
 *
 * @param  int       $seconds Seconds
 * @param  Translate $langs   Translations
 * @return string
 */
function reedcrm_newsletter_rough_duration_text(int $seconds, Translate $langs): string
{
    if ($seconds < 5400) {
        return $langs->transnoentities('NewsletterRoughMinutes', (string) max(1, (int) round($seconds / 60)));
    }
    if ($seconds < 172800) {
        return $langs->transnoentities('NewsletterRoughHours', (string) (int) round($seconds / 3600));
    }

    return $langs->transnoentities('NewsletterRoughDays', (string) (int) round($seconds / 86400));
}

/**
 * A duration as a text, "30 days", "1 month"
 *
 * @param  int       $value Number of units
 * @param  string    $unit  d, w, m or y
 * @param  Translate $langs Translations
 * @return string
 */
function reedcrm_newsletter_duration_text(int $value, string $unit, Translate $langs): string
{
    $unit = in_array($unit, ['d', 'w', 'm', 'y'], true) ? $unit : 'd';

    return $langs->transnoentities('NewsletterDuration_' . $unit . ($value === 1 ? '_1' : ''), (string) $value);
}

/**
 * When the mail of a newsletter sent after the creation leaves, "30 days after the creation of the contact"
 *
 * @param  ReedcrmNewsletter $object Newsletter
 * @param  Translate         $langs  Translations
 * @return string
 */
function reedcrm_newsletter_trigger_text(ReedcrmNewsletter $object, Translate $langs): string
{
    $isProject = $object->target_type === ReedcrmNewsletter::TARGET_PROJECT;
    if ((int) $object->send_delay <= 0) {
        return $langs->transnoentities($isProject ? 'NewsletterAtCreationProject' : 'NewsletterAtCreationContact');
    }
    $duration = reedcrm_newsletter_duration_text((int) $object->send_delay, (string) $object->send_delay_unit, $langs);

    return $langs->transnoentities($isProject ? 'NewsletterAfterCreationProject' : 'NewsletterAfterCreationContact', $duration);
}

/**
 * Whether a body has something to read: a text, or an image (a flyer made of one picture is a content)
 *
 * @param  string $html HTML
 * @return bool
 */
function reedcrm_newsletter_has_content(string $html): bool
{
    // The style blocks and the title are not read
    $html = preg_replace('#<(style|title|head)\b[^>]*>.*?</\1\s*>#is', '', $html);

    return trim(html_entity_decode(strip_tags((string) $html, '<img>'), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '';
}

/**
 * HTML of a mail, prepared for its sending
 *
 * - a viewport when the document has none: without it, some phones lay the mail out on 980 px and shrink it;
 * - the style blocks inside HTML comments: the CSS still applies (the parsers skip the comment markers at the top
 *   of a sheet), but strip_tags(), with which Dolibarr makes the text version of the mail, drops the comments;
 * - no title: the mail clients do not show it, and it would open the text version;
 * - a line break after each block: the text version keeps its paragraphs
 *
 * @param  string $html HTML of the mail, filled in
 * @return string       HTML
 */
function reedcrm_newsletter_prepare_mail_html(string $html): string
{
    if (preg_match('#<head\b[^>]*>#i', $html) && !preg_match('#<meta\b[^>]*name\s*=\s*["\']?viewport#i', $html)) {
        $html = preg_replace('#(<head\b[^>]*>)#i', '$1<meta name="viewport" content="width=device-width, initial-scale=1">', $html, 1);
    }

    $html = preg_replace_callback('#(<style\b[^>]*>)(.*?)(</style\s*>)#is', function ($matches) {
        $css = trim($matches[2]);
        if ($css === '' || strpos($css, '<!--') === 0) {
            return $matches[0];
        }
        return $matches[1] . '<!--' . "\n" . str_replace('-->', '', $css) . "\n" . '-->' . $matches[3];
    }, $html);

    $html = preg_replace('#<title\b[^>]*>.*?</title\s*>#is', '', $html);
    $html = preg_replace('#(</(p|div|h[1-6]|li|tr|table|blockquote|section|article|header|footer)>|<br\s*/?>)(?!\n)#i', "$1\n", $html);

    return (string) $html;
}

/**
 * Frequency of a reminder as a text: "Every year", "Every 3 months"
 *
 * @param  int       $value Number of units, 0 = sent once
 * @param  string    $unit  d, w, m or y
 * @param  Translate $langs Translations
 * @return string
 */
function reedcrm_newsletter_frequency_text(int $value, string $unit, Translate $langs): string
{
    $unit = in_array($unit, ['d', 'w', 'm', 'y'], true) ? $unit : 'm';
    if ($value <= 0) {
        return $langs->transnoentities('NewsletterOnce');
    }
    if ($value === 1) {
        return $langs->transnoentities('NewsletterEvery_' . $unit . '_1');
    }

    return $langs->transnoentities('NewsletterEveryN', reedcrm_newsletter_duration_text($value, $unit, $langs));
}

/**
 * How often the job of the newsletters runs, as set on the job: "every 15 minutes". The texts do not write it
 * in hard, a host may run the jobs every hour
 *
 * @param  DoliDB    $db    Database handler
 * @param  Translate $langs Translations
 * @return string           Text, the generic "with the scheduled job" when the job is not found
 */
function reedcrm_newsletter_cron_frequency_text(DoliDB $db, Translate $langs): string
{
    static $cache = [];
    $key = (string) $langs->defaultlang;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $sql   = 'SELECT frequency, unitfrequency FROM ' . MAIN_DB_PREFIX . 'cronjob';
    $sql  .= " WHERE methodename = 'sendScheduledNewsletters' AND entity IN (0, " . ((int) $GLOBALS['conf']->entity) . ')';
    $sql  .= ' ORDER BY entity DESC' . $db->plimit(1);
    $resql = $db->query($sql);
    $obj   = $resql ? $db->fetch_object($resql) : null;
    if (!$obj || (int) $obj->frequency <= 0) {
        return $cache[$key] = $langs->transnoentities('NewsletterCronEveryUnknown');
    }

    // unitfrequency is in seconds: 60, 3600, 86400 or 604800 from the card of the job
    $units = [60 => 'Minutes', 3600 => 'Hours', 86400 => 'Days', 604800 => 'Weeks'];
    $value = (int) $obj->frequency;
    $unit  = $units[(int) $obj->unitfrequency] ?? '';
    if ($unit === '') {
        $value = max(1, (int) round($value * (int) $obj->unitfrequency / 60));
        $unit  = 'Minutes';
    }

    return $cache[$key] = $langs->transnoentities('NewsletterCronEvery_' . $unit . ($value === 1 ? '_1' : ''), (string) $value);
}

/**
 * Whether the user may open the card of a third party (and of its contacts): all of them, or the ones he follows
 * as sales representative. The lists of the newsletters link a name only when the card would open
 *
 * @param  User   $user  User
 * @param  DoliDB $db    Database handler
 * @param  int    $socId Third party, 0 = a contact without third party
 * @return bool
 */
function reedcrm_newsletter_user_can_open_thirdparty(User $user, DoliDB $db, int $socId): bool
{
    static $followed = [];

    if (!empty($user->socid)) {
        return $socId > 0 && $socId === (int) $user->socid;
    }
    if ($user->hasRight('societe', 'client', 'voir') || $socId <= 0) {
        return true;
    }
    $userId = (int) $user->id;
    if (!isset($followed[$userId])) {
        $followed[$userId] = [];
        $resql = $db->query('SELECT fk_soc FROM ' . MAIN_DB_PREFIX . 'societe_commerciaux WHERE fk_user = ' . $userId);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $followed[$userId][(int) $obj->fk_soc] = true;
            }
        }
    }

    return isset($followed[$userId][$socId]);
}

/*
 * Simple editor: the newsletter is a layout and a list of blocks typed in fields, the module makes the HTML. Nobody
 * writes code, and the HTML made is the same in the preview of the form, on the card and in the mails
 */

/**
 * Layouts of the simple editor
 *
 * @return array [layout => translation key]
 */
function reedcrm_newsletter_block_layouts(): array
{
    return [
        'classic' => 'NewsletterLayoutClassic',
        'light'   => 'NewsletterLayoutLight',
        'letter'  => 'NewsletterLayoutLetter',
    ];
}

/**
 * Blocks of the simple editor
 *
 * @return array [type => translation key]
 */
function reedcrm_newsletter_block_types(): array
{
    return [
        'title'   => 'NewsletterBlockTitle',
        'text'    => 'NewsletterBlockText',
        'image'   => 'NewsletterBlockImage',
        'button'  => 'NewsletterBlockButton',
        'divider' => 'NewsletterBlockDivider',
    ];
}

/**
 * Colour of a new newsletter: the dark blue of Dolibarr, changed once and kept by the next editions
 *
 * @return string #rrggbb
 */
function reedcrm_newsletter_default_color(): string
{
    return '#263c5c';
}

/**
 * Content of a new newsletter in the simple editor: a title, a text that greets the recipient and a button, the
 * empty ones are left out of the mail until they are filled in
 *
 * @param  Translate $langs Translations
 * @return array            See reedcrm_newsletter_clean_blocks()
 */
function reedcrm_newsletter_default_blocks(Translate $langs): array
{
    return [
        'layout' => 'classic',
        'color'  => reedcrm_newsletter_default_color(),
        'logo'   => 1,
        'blocks' => [
            ['type' => 'title', 'text' => ''],
            ['type' => 'text', 'text' => $langs->transnoentitiesnoconv('NewsletterStarterText')],
            ['type' => 'button', 'label' => '', 'url' => ''],
        ],
    ];
}

/**
 * Text typed in a block: UTF-8, no control character but the line breaks, at most $max characters
 *
 * @param  mixed $value     Value posted
 * @param  int   $max       Most characters
 * @param  bool  $multiline Whether the line breaks are kept
 * @return string
 */
function reedcrm_newsletter_clean_block_text($value, int $max, bool $multiline = false): string
{
    $text = is_scalar($value) ? (string) $value : '';
    if (!mb_check_encoding($text, 'UTF-8')) {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    if (!$multiline) {
        $text = trim(str_replace(["\n", "\t"], ' ', $text));
    }

    return mb_substr($text, 0, $max, 'UTF-8');
}

/**
 * Link typed as people type it, completed: "www.site.fr" and "site.fr/page" get https://, "contact@site.fr" gets
 * mailto:. Anything else is given back as it is
 *
 * @param  string $url Link typed
 * @return string
 */
function reedcrm_newsletter_complete_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) || strpos($url, '__') === 0) {
        return $url;
    }
    if (preg_match('/^[^@\s\/:]+@[^@\s\/:]+\.[a-z]{2,}$/i', $url)) {
        return 'mailto:' . $url;
    }
    if (preg_match('/^(www\.)?[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)*\.[a-z]{2,}(?::\d+)?(?:[\/?#].*)?$/i', $url)) {
        return 'https://' . $url;
    }

    return $url;
}

/**
 * Link of a block (button, image, link of a text): http, https, mailto, tel or a key such as __UNSUBSCRIBE_URL__. An
 * image takes http and https only, and no key: the address of the recipient sent to the server of an image would
 * tell it who opens the mail. "www.site.fr" is completed, see reedcrm_newsletter_complete_url()
 *
 * @param  mixed $value     Value typed
 * @param  bool  $imageOnly Address of an image
 * @return string           The link, '' when it is refused
 */
function reedcrm_newsletter_block_url($value, bool $imageOnly = false): string
{
    $url = reedcrm_newsletter_complete_url(is_scalar($value) ? (string) $value : '');
    // A space pasted with the address would cut it in the mail
    $url = str_replace(' ', '%20', $url);
    if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x1F\x7F"<>\\\\`]/', $url)) {
        return '';
    }
    if ($imageOnly && preg_match('/__[A-Z0-9_]+__/', $url)) {
        return '';
    }
    if (preg_match('/^__[A-Z0-9_]+__$/', $url)) {
        return $imageOnly ? '' : $url;
    }
    $pattern = $imageOnly ? '#^https?://[^/?\#]#i' : '#^(https?://[^/?\#]|mailto:[^/]|tel:\+?[0-9(])#i';
    if (!preg_match($pattern, $url)) {
        return '';
    }

    return reedcrm_newsletter_is_safe_url($url, false) ? $url : '';
}

/**
 * Whether a file is an image of the newsletters in the public medias directory, see reedcrm_newsletter_publish_image()
 *
 * @param  mixed $file Path relative to the medias directory
 * @return bool
 */
function reedcrm_newsletter_is_public_image($file): bool
{
    return is_string($file) && (bool) preg_match('#^reedcrm/newsletter/[A-Za-z0-9][A-Za-z0-9._-]*\.(png|jpe?g|gif|webp)$#i', $file) && strpos($file, '..') === false;
}

/**
 * Blocks of the simple editor, checked and normalized: what the form posts, what the database holds and what the
 * renderer reads all go through here. Unknown types and fields are dropped, texts are cut, links checked, the colour
 * is a #rrggbb. Empty blocks are kept, the form is being written; the renderer leaves them out
 *
 * @param  mixed $input JSON or array
 * @return array        [layout, color, logo, blocks => [[type, …]]]
 */
function reedcrm_newsletter_clean_blocks($input): array
{
    $data = is_array($input) ? $input : json_decode((string) $input, true);
    if (!is_array($data)) {
        $data = [];
    }

    $layout = is_string($data['layout'] ?? null) ? $data['layout'] : '';
    $color  = is_string($data['color'] ?? null) ? $data['color'] : '';
    $clean  = [
        'layout'    => array_key_exists($layout, reedcrm_newsletter_block_layouts()) ? $layout : 'classic',
        'color'     => preg_match('/^#[0-9a-f]{6}$/i', $color) ? strtolower($color) : reedcrm_newsletter_default_color(),
        'logo'      => !empty($data['logo']) ? 1 : 0,
        // Text shown by the inbox after the subject, '' = the start of the first text
        'preheader' => reedcrm_newsletter_clean_block_text($data['preheader'] ?? '', 150),
        'blocks'    => [],
    ];

    $blocks = isset($data['blocks']) && is_array($data['blocks']) ? array_values($data['blocks']) : [];
    foreach (array_slice($blocks, 0, 60) as $block) {
        if (!is_array($block) || !is_string($block['type'] ?? null)) {
            continue;
        }
        switch ($block['type']) {
            case 'title':
                $clean['blocks'][] = ['type' => 'title', 'text' => reedcrm_newsletter_clean_block_text($block['text'] ?? '', 255)];
                break;
            case 'text':
                $clean['blocks'][] = ['type' => 'text', 'text' => reedcrm_newsletter_clean_block_text($block['text'] ?? '', 20000, true)];
                break;
            case 'image':
                $file = reedcrm_newsletter_is_public_image($block['file'] ?? '') ? $block['file'] : '';
                $clean['blocks'][] = [
                    'type'    => 'image',
                    'file'    => $file,
                    // An image of the library chosen in the form: made public when the newsletter is saved, see
                    // reedcrm_newsletter_publish_block_images()
                    'library' => $file === '' ? reedcrm_newsletter_picker_image_name($block['library'] ?? '') : '',
                    'url'     => reedcrm_newsletter_block_url($block['url'] ?? '', true),
                    'alt'     => reedcrm_newsletter_clean_block_text($block['alt'] ?? '', 255),
                    'link'    => reedcrm_newsletter_block_url($block['link'] ?? ''),
                ];
                break;
            case 'button':
                $clean['blocks'][] = [
                    'type'  => 'button',
                    'label' => reedcrm_newsletter_clean_block_text($block['label'] ?? '', 100),
                    'url'   => reedcrm_newsletter_block_url($block['url'] ?? ''),
                ];
                break;
            case 'divider':
                $clean['blocks'][] = ['type' => 'divider'];
                break;
        }
    }

    return $clean;
}

/**
 * Whether blocks give something to read: a title, a text, an image or a whole button. A divider alone is nothing
 *
 * @param  array $data Blocks, see reedcrm_newsletter_clean_blocks()
 * @return bool
 */
function reedcrm_newsletter_blocks_have_content(array $data): bool
{
    foreach ($data['blocks'] ?? [] as $block) {
        if (in_array($block['type'], ['title', 'text'], true) && trim($block['text']) !== '') {
            return true;
        }
        if ($block['type'] === 'image' && ($block['file'] !== '' || ($block['library'] ?? '') !== '' || $block['url'] !== '')) {
            return true;
        }
        if ($block['type'] === 'button' && $block['label'] !== '' && $block['url'] !== '') {
            return true;
        }
    }

    return false;
}

/**
 * Public address of the logo of the company for the mails: its small thumb (light, enough for a header 44 px high),
 * else the logo itself. '' when the file is missing: viewimage.php would answer with a placeholder picture, and the
 * mail would show a broken logo instead of the name of the company
 *
 * @return string URL, '' when there is no logo file
 */
function reedcrm_newsletter_company_logo_url(): string
{
    global $conf, $mysoc;

    $logoDir = !empty($conf->mycompany->multidir_output[$conf->entity]) ? $conf->mycompany->multidir_output[$conf->entity] . '/logos' : '';
    if ($logoDir === '' || empty($mysoc->logo)) {
        return '';
    }

    foreach ([!empty($mysoc->logo_small) ? 'thumbs/' . $mysoc->logo_small : '', $mysoc->logo] as $file) {
        if ($file !== '' && is_file($logoDir . '/' . $file)) {
            return DOL_MAIN_URL_ROOT . '/viewimage.php?modulepart=mycompany&entity=' . ((int) $conf->entity) . '&file=' . rawurlencode('logos/' . $file);
        }
    }

    return '';
}

/**
 * Text colour that reads on a background colour: dark on a light one, white on a dark one. By perceived brightness
 * (YIQ): colorIsLight() of Dolibarr takes the HSL lightness, a pure yellow would get white text
 *
 * @param  string $color Background, #rrggbb
 * @return string        #rrggbb
 */
function reedcrm_newsletter_text_color_on(string $color): string
{
    $hex = ltrim($color, '#');
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
        return '#ffffff';
    }
    $brightness = (hexdec(substr($hex, 0, 2)) * 299 + hexdec(substr($hex, 2, 2)) * 587 + hexdec(substr($hex, 4, 2)) * 114) / 1000;

    return $brightness >= 150 ? '#1f2933' : '#ffffff';
}

/**
 * Public address of an image of the newsletters: the medias directory of Dolibarr is public by design (website
 * module, editor images), the mail clients load it without a session
 *
 * @param  string $file   Path relative to the medias directory
 * @param  int    $entity Entity
 * @return string         URL
 */
function reedcrm_newsletter_public_image_url(string $file, int $entity): string
{
    return DOL_MAIN_URL_ROOT . '/viewimage.php?modulepart=medias&entity=' . $entity . '&file=' . rawurlencode($file);
}

/**
 * Text of a text block as HTML: escaped first, then **bold**, [label](link), paragraphs on the blank lines and line
 * breaks. Nothing typed is ever read as HTML
 *
 * @param  string $text      Text
 * @param  string $linkColor Colour of the links, #rrggbb
 * @return string            HTML
 */
function reedcrm_newsletter_format_block_text(string $text, string $linkColor): string
{
    $html = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    $html = (string) preg_replace_callback('/\[([^\]\n]+)\]\(([^)\s]+)\)/', function ($matches) use ($linkColor) {
        $url = reedcrm_newsletter_block_url(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($url === '') {
            // A link that cannot be kept reads as a text, not as its brackets
            return $matches[1] . ' (' . $matches[2] . ')';
        }
        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="color:' . $linkColor . ';">' . $matches[1] . '</a>';
    }, $html);
    // On one line: a bold left open does not swallow the next paragraphs
    $html = (string) preg_replace('/\*\*(?=\S)([^\n]+?)(?<=\S)\*\*/', '<strong>$1</strong>', $html);

    $out = '';
    foreach (preg_split('/\n[ \t]*\n+/', trim($html)) as $paragraph) {
        if (trim($paragraph) !== '') {
            $out .= '<p style="margin:0 0 14px;">' . nl2br(trim($paragraph), false) . '</p>';
        }
    }

    return $out;
}

/**
 * HTML of a newsletter of the simple editor: a whole document, tables and inline styles as the mail clients need,
 * the logo of the company, the blocks, and a footer with the company and the unsubscribe link
 *
 * @param  array         $data        Blocks, see reedcrm_newsletter_clean_blocks()
 * @param  Translate     $outputLangs Language of the footer
 * @param  callable|null $librarySrc  Address of an image of the library not public yet (preview of the form):
 *                                    function (string $name): string. Null: such an image is left out
 * @return string                     HTML document
 */
function reedcrm_newsletter_render_blocks(array $data, Translate $outputLangs, ?callable $librarySrc = null): string
{
    global $conf, $mysoc;

    $data     = reedcrm_newsletter_clean_blocks($data);
    $layout   = $data['layout'];
    $color    = $data['color'];
    $onColor  = reedcrm_newsletter_text_color_on($color);
    $isLetter = $layout === 'letter';
    $isLight  = $layout === 'light';
    $font     = $isLetter ? "Georgia,'Times New Roman',serif" : 'Arial,Helvetica,sans-serif';
    $pageBg   = $isLetter ? '#f3f0ea' : '#f4f5f7';
    $cardBg   = $isLetter ? '#fffdf9' : '#ffffff';
    $ink      = $isLetter ? '#2b2622' : '#1f2933';
    // The colour as a text on white (name, titles of the letter, links): a light colour would not read, the ink does
    $accent   = $onColor === '#ffffff' ? $color : $ink;
    $align    = $isLight ? 'center' : 'left';
    $escape   = function ($value) {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $companyName = (string) ($mysoc->name ?? '');
    $logoUrl     = !empty($data['logo']) ? reedcrm_newsletter_company_logo_url() : '';

    // Text of the inbox after the subject: the one typed, else the start of the first text. Without it, the inbox
    // shows the name of the company again, already read as the sender
    $preheader = $data['preheader'];
    if ($preheader === '') {
        foreach ($data['blocks'] as $block) {
            if ($block['type'] === 'text' && trim($block['text']) !== '') {
                $plain     = preg_replace(['/\*\*(.+?)\*\*/', '/\[([^\]\n]+)\]\([^)\s]+\)/', '/\s+/'], ['$1', '$1', ' '], $block['text']);
                $preheader = trim(mb_substr(trim((string) $plain), 0, 140, 'UTF-8'));
                break;
            }
        }
    }

    $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>';
    $html .= '<body style="margin:0;padding:0;background:' . $pageBg . ';">';
    if ($preheader !== '') {
        $html .= '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:' . $pageBg . ';opacity:0;">' . $escape($preheader) . '</div>';
    }
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . $pageBg . ';"><tr><td align="center" style="padding:24px 12px;">';
    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:' . $cardBg . ';border-radius:6px;' . ($isLight ? 'border-top:4px solid ' . $color . ';' : '') . '">';

    // Header: the logo, or the name of the company when there is no logo
    $header = $logoUrl !== ''
        ? '<img src="' . $escape($logoUrl) . '" alt="' . $escape($companyName) . '" height="44" style="display:' . ($isLight ? 'inline-block' : 'block') . ';height:44px;width:auto;max-width:240px;border:0;">'
        : '<span style="font-family:' . $font . ';font-size:20px;font-weight:bold;color:' . $accent . ';">' . $escape($companyName) . '</span>';
    $html .= '<tr><td align="' . $align . '" style="padding:20px 28px;' . ($layout === 'classic' ? 'border-bottom:4px solid ' . $color . ';' : '') . '">' . $header . '</td></tr>';

    $blocks = $data['blocks'];
    // Classic layout: a first title is a band in the colour, under the logo
    if ($layout === 'classic' && isset($blocks[0]) && $blocks[0]['type'] === 'title' && $blocks[0]['text'] !== '') {
        $html .= '<tr><td style="padding:26px 28px;background:' . $color . ';"><h1 style="margin:0;font-family:' . $font . ';font-size:26px;line-height:1.3;color:' . $onColor . ';">' . $escape($blocks[0]['text']) . '</h1></td></tr>';
        array_shift($blocks);
    }

    $body       = '';
    $titleCount = 0;
    foreach ($blocks as $block) {
        switch ($block['type']) {
            case 'title':
                if ($block['text'] === '') {
                    break;
                }
                $tag   = $titleCount === 0 ? 'h1' : 'h2';
                $size  = $titleCount === 0 ? 24 : 20;
                $body .= '<' . $tag . ' style="margin:0 0 16px;font-family:' . $font . ';font-size:' . $size . 'px;line-height:1.3;color:' . ($isLetter ? $accent : $ink) . ';">' . $escape($block['text']) . '</' . $tag . '>';
                $titleCount++;
                break;
            case 'text':
                if (trim($block['text']) !== '') {
                    $body .= '<div style="margin:0 0 6px;">' . reedcrm_newsletter_format_block_text($block['text'], $accent) . '</div>';
                }
                break;
            case 'image':
                $src = $block['url'];
                if ($block['file'] !== '') {
                    $src = reedcrm_newsletter_public_image_url($block['file'], (int) $conf->entity);
                } elseif (($block['library'] ?? '') !== '') {
                    $src = $librarySrc !== null ? (string) $librarySrc($block['library']) : '';
                }
                if ($src === '') {
                    break;
                }
                $image = '<img src="' . $escape($src) . '" alt="' . $escape($block['alt']) . '" width="544" style="display:block;width:100%;max-width:544px;height:auto;border:0;border-radius:4px;' . ($isLight ? 'margin:0 auto;' : '') . '">';
                if ($block['link'] !== '') {
                    $image = '<a href="' . $escape($block['link']) . '">' . $image . '</a>';
                }
                $body .= '<div style="margin:0 0 20px;">' . $image . '</div>';
                break;
            case 'button':
                if ($block['label'] === '' || $block['url'] === '') {
                    break;
                }
                $body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:4px ' . ($isLight ? 'auto' : '0') . ' 22px;"><tr><td style="background:' . $color . ';border-radius:4px;">';
                // 46 px high: a thumb hits it on a phone
                $body .= '<a href="' . $escape($block['url']) . '" style="display:inline-block;padding:14px 26px;font-family:' . $font . ';font-size:15px;line-height:18px;font-weight:bold;color:' . $onColor . ';text-decoration:none;">' . $escape($block['label']) . '</a>';
                $body .= '</td></tr></table>';
                break;
            case 'divider':
                $body .= '<div style="margin:6px 0 22px;height:1px;line-height:1px;font-size:1px;' . ($isLetter ? 'border-top:1px dashed #d8cfc2;' : 'background:#e1e4e8;') . '">&nbsp;</div>';
                break;
        }
    }
    if ($body !== '') {
        $html .= '<tr><td align="' . $align . '" style="padding:26px 28px 12px;font-family:' . $font . ';font-size:' . ($isLetter ? 16 : 15) . 'px;line-height:1.6;color:' . $ink . ';text-align:' . $align . ';">' . $body . '</td></tr>';
    }

    // Footer: who writes, and the way out
    $footer = $escape($companyName . (!empty($mysoc->town) ? ' · ' . $mysoc->town : ''));
    if (getDolGlobalInt('REEDCRM_NEWSLETTER_UNSUBSCRIBE_LINK', 1)) {
        $footer .= ($footer !== '' ? '<br>' : '') . $outputLangs->transnoentitiesnoconv('NewsletterUnsubscribeFooter', '__UNSUBSCRIBE__');
    }
    if ($footer !== '') {
        // Dark enough to be read (7:1 on white), the link in the same colour, underlined
        $html .= '<tr><td align="center" style="padding:16px 28px;border-top:1px solid ' . ($isLetter ? '#ece4d8' : '#eceff1') . ';font-family:' . $font . ';font-size:13px;line-height:1.6;color:#4b5563;text-align:center;">' . $footer . '</td></tr>';
    }

    $html .= '</table></td></tr></table></body></html>';

    return $html;
}

/**
 * The month names of a text moved by the months between two dates: "Newsletter November 2026" prepared for the
 * next month becomes "Newsletter December 2026", "December 2026" becomes "January 2027". A year is moved only when it
 * follows a month name: "Review of 2026" stays as it is. The case of each name is kept. English month names are
 * always capitalized, the lowercase "may" of a sentence is not a month
 *
 * Other languages write the months in lowercase: a capitalized name followed by another capitalized word is a proper
 * name ("Mars Expo", "Juin Construction") and stays, unless a year follows it. In French the "de" before the month
 * follows the new name: "de novembre" becomes "d'octobre", "d'avril" becomes "de mai"
 *
 * @param  string    $text  Text
 * @param  int       $from  Date the text was written for
 * @param  int       $to    Date of the new edition
 * @param  Translate $langs Language of the month names
 * @return string
 */
function reedcrm_newsletter_shift_month_text(string $text, int $from, int $to, Translate $langs): string
{
    if ($text === '' || $from <= 0 || $to <= 0) {
        return $text;
    }
    $fromDate = dol_getdate($from);
    $toDate   = dol_getdate($to);
    $delta    = ($toDate['year'] * 12 + $toDate['mon']) - ($fromDate['year'] * 12 + $fromDate['mon']);
    if ($delta === 0) {
        return $text;
    }

    $capitalize = function (string $name): string {
        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($name, 1, null, 'UTF-8');
    };

    $names = [];
    for ($month = 1; $month <= 12; $month++) {
        $names[$month] = mb_strtolower($langs->transnoentitiesnoconv('Month' . sprintf('%02d', $month)), 'UTF-8');
    }
    $isEnglish = strpos((string) $langs->defaultlang, 'en') === 0;
    $isFrench  = strpos((string) $langs->defaultlang, 'fr') === 0;
    // English: "January" and "JANUARY" only, matched with the case. Other languages: any case
    $forms = [];
    foreach ($names as $name) {
        if ($isEnglish) {
            $forms[] = $capitalize($name);
            $forms[] = mb_strtoupper($name, 'UTF-8');
        } else {
            $forms[] = $name;
        }
    }
    usort($forms, function ($a, $b) {
        return mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8');
    });
    // French: the "de" or "d'" just before the name, rewritten for the new one
    $preposition = $isFrench ? '(?:(?<![\p{L}\d])([dD])(e\s+|[\'’]))?' : '()()';
    $pattern     = '/' . $preposition . '(?<![\p{L}\d])(' . implode('|', array_map(function ($form) {
        return preg_quote($form, '/');
    }, $forms)) . ')(?:(\s+)(\d{4}))?(?![\p{L}\d])(?=(\s+\p{Lu}[\p{L}]*)?)/u' . ($isEnglish ? '' : 'i');

    return (string) preg_replace_callback($pattern, function ($matches) use ($names, $delta, $capitalize, $isEnglish, $isFrench) {
        $found = $matches[3];
        $month = array_search(mb_strtolower($found, 'UTF-8'), $names, true);
        if ($month === false) {
            return $matches[0];
        }
        $hasYear     = isset($matches[5]) && $matches[5] !== '';
        $isLowerCase = $found === mb_strtolower($found, 'UTF-8');
        $isUpperCase = !$isLowerCase && $found === mb_strtoupper($found, 'UTF-8') && mb_strlen($found, 'UTF-8') > 1;
        // A proper name: capitalized, a capitalized word after it, no year
        if (!$isEnglish && !$isLowerCase && !$hasYear && !empty($matches[6])) {
            return $matches[0];
        }

        $total    = $month - 1 + $delta;
        $newMonth = (($total % 12) + 12) % 12 + 1;
        $yearMove = (int) floor($total / 12);
        $newName  = $names[$newMonth];
        if ($isUpperCase) {
            $newName = mb_strtoupper($newName, 'UTF-8');
        } elseif (!$isLowerCase) {
            $newName = $capitalize($newName);
        }

        $before = '';
        if ($isFrench && !empty($matches[1])) {
            $elided = (bool) preg_match('/^[aeiouyhàâäéèêëîïôöûü]/iu', $newName);
            $before = $matches[1] . ($elided ? (strpos($matches[2], '’') !== false ? '’' : "'") : (strpos($matches[2], 'E') === 0 ? 'E ' : 'e '));
        }
        if ($hasYear) {
            return $before . $newName . $matches[4] . ((int) $matches[5] + $yearMove);
        }

        return $before . $newName;
    }, $text);
}

/**
 * Label of the next edition: its month names moved (see reedcrm_newsletter_shift_month_text()), or, when it has none,
 * the month and year of the edition added after a dash. Each edition has its own label in the list
 *
 * @param  string    $label Label of the edition sent
 * @param  int       $from  Its date
 * @param  int       $to    Date of the next edition
 * @param  Translate $langs Translations
 * @return string
 */
function reedcrm_newsletter_next_label(string $label, int $from, int $to, Translate $langs): string
{
    $next = reedcrm_newsletter_shift_month_text($label, $from, $to, $langs);
    if ($next !== $label) {
        return $next;
    }
    $toDate = dol_getdate($to);
    $month  = $langs->transnoentitiesnoconv('Month' . sprintf('%02d', $toDate['mon']));
    // Lowercase but in English, as the months are written in a sentence: "Newsletter - décembre 2026"
    if (strpos((string) $langs->defaultlang, 'en') !== 0) {
        $month = mb_strtolower($month, 'UTF-8');
    }
    $suffix = $month . ' ' . $toDate['year'];

    return dol_trunc(trim($label) . ' - ' . $suffix, 255, 'right', 'UTF-8', 1);
}

/**
 * Directory of the media library of ReedCRM (Saturne): the images uploaded once are offered again in every
 * newsletter. '' when the ECM module, which holds it, is off: the public directory of the newsletters is used instead
 *
 * @return string
 */
function reedcrm_newsletter_library_dir(): string
{
    global $conf;

    if (!isModEnabled('ecm') || empty($conf->ecm->multidir_output[$conf->entity])) {
        return '';
    }

    return $conf->ecm->multidir_output[$conf->entity] . '/reedcrm/medias';
}

/**
 * Public directory of the images put in the newsletters, inside the medias directory of Dolibarr
 *
 * @return string
 */
function reedcrm_newsletter_public_image_dir(): string
{
    global $conf;

    $root = !empty($conf->medias->multidir_output[$conf->entity]) ? $conf->medias->multidir_output[$conf->entity] : DOL_DATA_ROOT . '/medias';

    return $root . '/reedcrm/newsletter';
}

/**
 * Directory the image picker reads and uploads to: the media library, else the public directory
 *
 * @return string
 */
function reedcrm_newsletter_picker_dir(): string
{
    $library = reedcrm_newsletter_library_dir();

    return $library !== '' ? $library : reedcrm_newsletter_public_image_dir();
}

/**
 * Images of the picker, the most recent first
 *
 * @param  int   $limit Most images
 * @return array        [[name, date]]
 */
function reedcrm_newsletter_list_picker_images(int $limit = 80): array
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

    $dir = reedcrm_newsletter_picker_dir();
    if (!is_dir($dir)) {
        return [];
    }

    $images = [];
    foreach (dol_dir_list($dir, 'files', 0, '\.(png|jpe?g|gif|webp)$', null, 'date', SORT_DESC) as $file) {
        $images[] = ['name' => $file['name'], 'date' => (int) $file['date']];
        if (count($images) >= $limit) {
            break;
        }
    }

    return $images;
}

/**
 * Name of an image of the picker, when it is a plain image name of its directory
 *
 * @param  mixed $name Name posted
 * @return string      Name, '' when refused
 */
function reedcrm_newsletter_picker_image_name($name): string
{
    $name = is_string($name) ? $name : '';
    if ($name === '' || $name !== basename($name) || strpos($name, '..') !== false || !preg_match('/\.(png|jpe?g|gif|webp)$/i', $name)) {
        return '';
    }

    return is_file(reedcrm_newsletter_picker_dir() . '/' . $name) ? $name : '';
}

/**
 * Most pixels an image may have to be read by GD with the memory PHP gives: about 6 bytes a pixel (the image, its
 * reduced copy, the work of GD). The limit is raised when PHP lets it, up to 512 MB
 *
 * @param  int $pixels Pixels of the image to read, the limit is raised for it when possible
 * @return int
 */
function reedcrm_newsletter_max_image_pixels(int $pixels = 0): int
{
    $toBytes = function ($value): int {
        $value = trim((string) $value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $number = (float) $value;
        switch (strtolower(substr($value, -1))) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
        }
        return (int) $number;
    };

    $limit = $toBytes(ini_get('memory_limit'));
    if ($limit < 0) {
        return 80000000;
    }
    $needed = memory_get_usage(true) + 16 * 1048576 + $pixels * 6;
    if ($pixels > 0 && $needed > $limit && $needed <= 512 * 1048576 && @ini_set('memory_limit', (string) $needed) !== false) {
        $limit = $toBytes(ini_get('memory_limit'));
    }

    return max(1000000, (int) (($limit - memory_get_usage(true) - 16 * 1048576) / 6));
}

/**
 * Why an image cannot be read as it is: too many pixels for the memory (a photo of 48 Mpx stops PHP in the middle of
 * its thumbs). Checked from the header of the file, nothing is decoded
 *
 * @param  string    $path  File
 * @param  Translate $langs Translations
 * @return string           '' when it can be read, else the message
 */
function reedcrm_newsletter_image_size_error(string $path, Translate $langs): string
{
    $info = @getimagesize($path);
    if (empty($info[0]) || empty($info[1])) {
        return $langs->transnoentitiesnoconv('NewsletterErrorNotAnImage');
    }
    $pixels = (int) $info[0] * (int) $info[1];
    $max    = reedcrm_newsletter_max_image_pixels($pixels);
    if ($pixels <= $max) {
        return '';
    }

    return $langs->transnoentitiesnoconv('NewsletterImageTooManyPixels', $info[0] . ' × ' . $info[1], (string) floor($max / 1000000));
}

/**
 * Copy of an image at most $maxWidth wide: kept as it is when it is narrow enough, else reduced, turned upright
 * (the orientation a phone writes in the EXIF of a photo is lost when the image is written again) and compressed:
 * JPEG and WEBP at 82, PNG at the highest level. A reduced copy heavier than the original gives way to the original
 *
 * @param  string $source   Image
 * @param  string $target   Copy
 * @param  int    $maxWidth Most pixels wide
 * @return bool
 */
function reedcrm_newsletter_resize_image(string $source, string $target, int $maxWidth): bool
{
    $info = @getimagesize($source);
    if (empty($info[0]) || empty($info[1])) {
        return false;
    }
    $type        = (int) $info[2];
    $orientation = 1;
    if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif        = @exif_read_data($source);
        $orientation = (int) ($exif['Orientation'] ?? 1);
    }
    if ((int) $info[0] <= $maxWidth && in_array($orientation, [0, 1], true)) {
        return @copy($source, $target);
    }

    $loaders = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_GIF => 'imagecreatefromgif', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
    if (!isset($loaders[$type]) || !function_exists($loaders[$type])) {
        return @copy($source, $target);
    }
    $image = @$loaders[$type]($source);
    if (!$image) {
        return false;
    }
    $angles = [3 => 180, 6 => -90, 8 => 90];
    if (isset($angles[$orientation])) {
        $rotated = imagerotate($image, $angles[$orientation], 0);
        if ($rotated) {
            imagedestroy($image);
            $image = $rotated;
        }
    }

    $width  = imagesx($image);
    $height = imagesy($image);
    $newWidth  = min($maxWidth, $width);
    $newHeight = max(1, (int) round($height * $newWidth / $width));
    $copy      = imagecreatetruecolor($newWidth, $newHeight);
    if ($type !== IMAGETYPE_JPEG) {
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagefill($copy, 0, 0, imagecolorallocatealpha($copy, 255, 255, 255, 127));
    }
    imagecopyresampled($copy, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    imagedestroy($image);

    switch ($type) {
        case IMAGETYPE_JPEG:
            imageinterlace($copy, true);
            $written = imagejpeg($copy, $target, 82);
            break;
        case IMAGETYPE_PNG:
            $written = imagepng($copy, $target, 9);
            break;
        case IMAGETYPE_WEBP:
            $written = imagewebp($copy, $target, 82);
            break;
        default:
            $written = imagegif($copy, $target);
    }
    imagedestroy($copy);
    if (!$written) {
        return false;
    }

    if (in_array($orientation, [0, 1], true) && filesize($target) > filesize($source)) {
        return @copy($source, $target);
    }

    return true;
}

/**
 * Put an image of the picker in the public directory of the newsletters, where the mail clients can load it. Done
 * when the newsletter is saved, not when the image is chosen: an image tried in the form and left is never made
 * public. The copy is named after its content: the same image saved twice is copied once. A large image is reduced
 * to 1200 px wide, a mail does not need more
 *
 * @param  string $name  Name of the image in the picker directory, see reedcrm_newsletter_picker_image_name()
 * @param  string $error Set to the reason when it fails
 * @return string        Path relative to the medias directory, '' if KO
 */
function reedcrm_newsletter_publish_image(string $name, string &$error = ''): string
{
    global $langs, $user;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

    $error  = '';
    $source = reedcrm_newsletter_picker_dir() . '/' . $name;
    if ($name === '' || !is_file($source)) {
        $error = $langs->transnoentitiesnoconv('NewsletterImageNotFound');
        return '';
    }
    $error = reedcrm_newsletter_image_size_error($source, $langs);
    if ($error !== '') {
        return '';
    }

    $publicDir = reedcrm_newsletter_public_image_dir();
    if (dol_mkdir($publicDir) < 0) {
        $error = $langs->transnoentitiesnoconv('NewsletterEditorRequestError');
        return '';
    }

    $extension  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $base       = dol_sanitizeFileName(pathinfo($name, PATHINFO_FILENAME), '_', 1, 0, 1);
    $publicName = substr(md5_file($source), 0, 10) . '-' . substr(preg_replace('/[^A-Za-z0-9._-]/', '_', $base), 0, 60) . '.' . $extension;
    $target     = $publicDir . '/' . $publicName;
    if (!is_file($target)) {
        if (!reedcrm_newsletter_resize_image($source, $target, 1200)) {
            @unlink($target);
            $error = $langs->transnoentitiesnoconv('NewsletterErrorNotAnImage');
            return '';
        }
        dolChmod($target);
        // Who made what public, the public directory has no index of its own
        dol_syslog(__FUNCTION__ . ' ' . $name . ' published as ' . $publicName . ' by ' . (is_object($user) ? $user->login : '?'), LOG_NOTICE);
    }

    return 'reedcrm/newsletter/' . $publicName;
}

/**
 * Images of the library chosen in the blocks, made public: see reedcrm_newsletter_publish_image()
 *
 * @param  array $data   Blocks, see reedcrm_newsletter_clean_blocks()
 * @param  array $errors Errors added, one per image that cannot be published
 * @return array         Blocks, the images published pointing to their public copy
 */
function reedcrm_newsletter_publish_block_images(array $data, array &$errors): array
{
    global $langs;

    foreach ($data['blocks'] as $index => $block) {
        if ($block['type'] !== 'image' || ($block['library'] ?? '') === '') {
            continue;
        }
        $error = '';
        $file  = reedcrm_newsletter_publish_image($block['library'], $error);
        if ($file === '') {
            $errors[] = $langs->trans('NewsletterImagePublishError', $block['library'], $error);
            continue;
        }
        $data['blocks'][$index]['file']    = $file;
        $data['blocks'][$index]['library'] = '';
    }

    return $data;
}

/**
 * Address of an image of the library in the preview of the form, before it is public: the image itself, as data. The
 * preview is a sandboxed frame, it does not send the session to an address of Dolibarr
 *
 * @param  string $name Name of the image in the picker directory
 * @return string       data: URL, '' when there is no such image
 */
function reedcrm_newsletter_library_preview_src(string $name): string
{
    global $langs;

    require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
    dol_include_once('/saturne/lib/dolibarr.lib.php');

    $dir  = reedcrm_newsletter_picker_dir();
    $path = $dir . '/' . $name;
    if ($name === '' || !is_file($path)) {
        return '';
    }

    // The medium thumb of the media library of Saturne (854 px at most), made once. The core knows small and mini only
    $medium = function_exists('saturne_get_image_file_name_for_size') ? saturne_get_image_file_name_for_size($path, '_medium') : '';
    if ($medium !== '' && !is_file($medium) && reedcrm_newsletter_image_size_error($path, $langs) === '') {
        saturne_vignette($path, getDolGlobalInt('SATURNE_MEDIA_MAX_WIDTH_MEDIUM', 854), getDolGlobalInt('SATURNE_MEDIA_MAX_HEIGHT_MEDIUM', 480), '_medium', 70);
    }
    $file = $medium !== '' && is_file($medium) ? $medium : $dir . '/' . getImageFileNameForSize($name, '_small');
    if (!is_file($file)) {
        return '';
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file);
    if (!preg_match('#^image/(png|jpeg|gif|webp)$#', (string) $mime)) {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($file));
}

/**
 * Remove the public copies no newsletter uses: images chosen once and replaced, before the images were made public at
 * the saving only. A copy used by any newsletter stays, a deleted one too: its mails were maybe sent and still show it
 *
 * @param  DoliDB $db Database handler
 * @return int        Number of files removed
 */
function reedcrm_newsletter_cleanup_public_images(DoliDB $db): int
{
    require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';

    $dir = reedcrm_newsletter_public_image_dir();
    if (!is_dir($dir)) {
        return 0;
    }
    // An hour of margin: a copy made by a save still running is not removed under it
    $unused = [];
    foreach (dol_dir_list($dir, 'files', 0, '\.(png|jpe?g|gif|webp)$') as $file) {
        if ((int) $file['date'] < dol_now() - 3600) {
            $unused[$file['name']] = $file['fullname'];
        }
    }
    if (empty($unused)) {
        return 0;
    }

    $resql = $db->query('SELECT body_blocks, body_html FROM ' . MAIN_DB_PREFIX . 'reedcrm_newsletter');
    if (!$resql) {
        return 0;
    }
    while (!empty($unused) && ($obj = $db->fetch_object($resql))) {
        $text = (string) $obj->body_blocks . ' ' . (string) $obj->body_html;
        foreach ($unused as $name => $path) {
            if (strpos($text, $name) !== false || strpos($text, rawurlencode($name)) !== false) {
                unset($unused[$name]);
            }
        }
    }
    $db->free($resql);

    $removed = 0;
    foreach ($unused as $name => $path) {
        if (dol_delete_file($path, 0, 1)) {
            $removed++;
        }
    }

    return $removed;
}

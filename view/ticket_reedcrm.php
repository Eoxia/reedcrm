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
 * \file       htdocs/custom/reedcrm/view/ticket_reedcrm.php
 * \ingroup    reedcrm
 * \brief      ReedCRM tab for ticket
 */

// Load Dolibarr environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once '../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once '../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/ticket.lib.php';

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$track_id = GETPOST('track_id', 'alpha');

$object = new Ticket($db);

// Load object
if ($id > 0 || !empty($track_id) || !empty($ref)) {
    $res = $object->fetch($id, $ref, $track_id);
    if ($res <= 0) {
        dol_print_error($db, $object->error);
        exit;
    }
}

// View
$title = $langs->trans("Ticket");
saturne_header(0, '', $title);

$head = ticket_prepare_head($object);
print dol_get_fiche_head($head, 'reedcrm_ticket_reedcrm', $langs->trans("Ticket"), -1, 'ticket');

$morehtmlref = '<div class="refidno">';
$morehtmlref .= $object->subject;

// Author
if ($object->fk_user_create > 0) {
    $morehtmlref .= '<br>';
    $fuser = new User($db);
    $fuser->fetch($object->fk_user_create);
    $morehtmlref .= $fuser->getNomUrl(-1);
} elseif (!empty($object->email_msgid)) {
    $morehtmlref .= '<br>';
    $morehtmlref .= img_picto('', 'email', 'class="paddingrightonly"');
    $morehtmlref .= dol_escape_htmltag($object->origin_email) . ' <small class="hideonsmartphone opacitymedium">(' . $langs->trans("CreatedByEmailCollector") . ': ' . $object->email_msgid . ')</small>';
} elseif (!empty($object->origin_email)) {
    $morehtmlref .= '<br>';
    $morehtmlref .= img_picto('', 'email', 'class="paddingrightonly"');
    $morehtmlref .= dol_escape_htmltag($object->origin_email) . ' <small class="hideonsmartphone opacitymedium">(' . $langs->trans("CreatedByPublicPortal") . ')</small>';
}
$morehtmlref .= '</div>';

$linkback = '<a href="' . dol_buildpath('/ticket/list.php', 1) . '"><strong>' . $langs->trans("BackToList") . '</strong></a> ';

dol_banner_tab($object, 'ref', $linkback, (empty($user->socid) ? 1 : 0), 'ref', 'ref', $morehtmlref, '', 0, '', '', 1, '');

print dol_get_fiche_end();

// Content
print '<div class="fichecenter">';
print '<div class="fichehalfleft">';
print '<!-- Add your ReedCRM ticket specific content here -->';
print '</div>';
print '<div class="fichehalfright">';
print '</div>';
print '<div class="clearboth"></div>';
print '</div>';

// End of page
saturne_footer();
$db->close();

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
 * \file    view/frontend/pwa_call_list.php
 * \ingroup reedcrm
 * \brief   Mobile-optimized PWA view for a CallList — card per contact, direct call button, AJAX status update.
 */

// Removed NOCSRFCHECK to keep security intact

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once __DIR__ . '/../../lib/reedcrm_function.lib.php';
require_once __DIR__ . '/../../lib/reedcrm_call_list.lib.php';
require_once __DIR__ . '/../../lib/reedcrm_relaunch.lib.php';
require_once __DIR__ . '/../../class/calllist.class.php';
require_once __DIR__ . '/../../class/calllistline.class.php';

global $conf, $db, $langs, $user;

saturne_load_langs();

$id = GETPOSTINT('id');

if (!$user->hasRight('reedcrm', 'call_list', 'read') && !$user->hasRight('reedcrm', 'call_list', 'read_subordinates') && !$user->hasRight('reedcrm', 'call_list', 'read_all')) {
    accessforbidden($langs->trans('NotEnoughPermissions'));
    exit;
}

// Without an explicit id (PWA nav), open the user's own default call list
if ($id <= 0) {
    $id = reedcrm_get_or_create_user_default_call_list($db, $user);
}
$defaultCallListId = isset($user->conf->REEDCRM_DEFAULT_CALL_LIST) ? (int) $user->conf->REEDCRM_DEFAULT_CALL_LIST : 0;

$object     = new CallList($db);
$lineObject = new CallListLine($db);

if ($id > 0) {
    $object->fetch($id);
}

if (empty($object->id)) {
    accessforbidden($langs->trans('ErrorRecordNotFound'));
    exit;
}

if (!reedcrm_call_list_user_can_read($user, $object)) {
    accessforbidden($langs->trans('NotEnoughPermissions'));
    exit;
}

$lines = $lineObject->fetchAllByCallList($object->id);

// Same indicators as the todo cards: object + amount + relaunches, third party, late / upcoming
$lineIndicators = reedcrm_call_list_get_line_indicators($db, $lines);

$toCallCount = 0;
foreach ($lines as $line) {
    if ((int) $line->status === CallListLine::STATUS_TO_CALL) {
        $toCallCount++;
    }
}

$title   = dol_escape_htmltag($object->label);
$helpUrl = 'FR:Module_ReedCRM';
$moreJS  = ['/saturne/js/saturne.min.js', '/reedcrm/js/reedcrm.min.js'];
$moreCSS = ['/saturne/css/saturne.min.css', '/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

llxHeader('', $title, $helpUrl, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-call-list');

$pwaHeaderCenterHtml = '<div style="background:#e2e8f0;padding:4px 10px;border-radius:12px;font-size:13px;font-weight:bold;color:#475569;">'
    . '<i class="fas fa-phone"></i> ' . dol_escape_htmltag($object->label)
    . ' — <span class="pwa-call-list-to-call-count">' . $toCallCount . '</span>/<span class="pwa-call-list-total-count">' . count($lines) . '</span> à appeler'
    . '</div>';
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

// Page styles live in css/scss/pages/_pwa-call-list.scss (compiled into reedcrm.min.css)

$actioncommAjaxUrl = dol_buildpath('/reedcrm/ajax/create_call_actioncomm.php', 1);
$unlinkAjaxUrl     = dol_buildpath('/reedcrm/ajax/unlink_call_list_line.php', 1);
// Same endpoint as the arrows of the call list card, the move itself being handled by js/modules/call_list.js
$moveAjaxUrl       = dol_buildpath('/reedcrm/ajax/move_call_list_line.php', 1);
$createActioncomm  = getDolGlobalInt('REEDCRM_CALL_LIST_CREATE_ACTIONCOMM');
// Same right as the unlink button of the call list card
$canUnlink         = $user->hasRight('reedcrm', 'call_list', 'delete');
// Same rights as the arrows of the call list card and the relaunch bar of the opportunity page
$canMove           = $user->hasRight('reedcrm', 'call_list', 'write');
$canAddEvent       = $user->hasRight('agenda', 'myactions', 'create') && $user->hasRight('reedcrm', 'eventpro', 'write');
$token             = newToken();

print '<div class="pwa-call-list-container"'
    . ' data-move-url="' . dol_escape_htmltag($moveAjaxUrl) . '"'
    . ' data-actioncomm-url="' . dol_escape_htmltag($actioncommAjaxUrl) . '"'
    . ' data-unlink-url="' . dol_escape_htmltag($unlinkAjaxUrl) . '"'
    . ' data-unlink-confirm="' . dol_escape_htmltag($langs->transnoentities('ConfirmCallListLineUnlink')) . '"'
    . ' data-unlink-error="' . dol_escape_htmltag($langs->transnoentities('ErrorCallListLineUnlink')) . '"'
    . ' data-create-actioncomm="' . (int) $createActioncomm . '"'
    . ' data-default-list="' . ((int) $object->id === $defaultCallListId ? 1 : 0) . '"'
    . ' data-token="' . dol_escape_htmltag($token) . '">';

// Saturne reads the token of its AJAX calls from an input named "token" in the DOM
print '<input type="hidden" name="token" value="' . dol_escape_htmltag($token) . '">';

$dateStart = $object->date_start ? dol_print_date($object->date_start, 'day') : '—';
$dateEnd   = $object->date_end   ? dol_print_date($object->date_end,   'day') : '—';
print '<div class="pwa-call-list-header">';

// Only employees' lists are offered: a default call list is created for every user, external
// users (client contacts with a login) included, which floods the selector with lists nobody calls.
// The current user's own lists and the list being displayed always stay reachable.
$sqlLists = "SELECT cl.rowid, cl.label, cl.fk_user_assign FROM " . MAIN_DB_PREFIX . "reedcrm_call_list cl";
$sqlLists .= " LEFT JOIN " . MAIN_DB_PREFIX . "user u ON u.rowid = cl.fk_user_assign";
$sqlLists .= " WHERE cl.entity IN (" . getEntity('call_list') . ")";
$sqlLists .= " AND cl.status = " . CallList::STATUS_ACTIVE;
$sqlLists .= " AND (u.employee = 1 OR cl.fk_user_assign = " . ((int) $user->id) . " OR cl.rowid = " . ((int) $object->id) . ")";
// Same order as the list and the selectors of the cards, see CallList::SORT_FIELDS
$sqlLists .= " ORDER BY cl.position ASC, cl.label ASC";
$resqlLists = $db->query($sqlLists);

$myLists = [];
$otherLists = [];
if ($resqlLists) {
    $childIds = $user->getAllChildIds(1);
    while ($objList = $db->fetch_object($resqlLists)) {
        $allowed = false;
        $isMine = ($objList->fk_user_assign == $user->id);
        
        if ($user->hasRight('reedcrm', 'call_list', 'read_all')) {
            $allowed = true;
        } elseif ($user->hasRight('reedcrm', 'call_list', 'read_subordinates') && isset($childIds[$objList->fk_user_assign])) {
            $allowed = true;
        } elseif ($user->hasRight('reedcrm', 'call_list', 'read') && $isMine) {
            $allowed = true;
        }
        
        if ($allowed) {
            if ($isMine) {
                $myLists[] = $objList;
            } else {
                $otherLists[] = $objList;
            }
        }
    }
}

print '<select id="pwa-call-list-select" class="pwa-call-list-select">';
if (!empty($myLists)) {
    print '<optgroup label="' . dol_escape_htmltag($langs->trans('MyCallLists')) . '">';
    foreach ($myLists as $lst) {
        $sel = ($lst->rowid == $object->id) ? ' selected' : '';
        print '<option value="' . $lst->rowid . '"' . $sel . '>' . dol_escape_htmltag($lst->label) . '</option>';
    }
    print '</optgroup>';
}
if (!empty($otherLists)) {
    print '<optgroup label="' . dol_escape_htmltag($langs->trans('OtherCallLists')) . '">';
    foreach ($otherLists as $lst) {
        $sel = ($lst->rowid == $object->id) ? ' selected' : '';
        print '<option value="' . $lst->rowid . '"' . $sel . '>' . dol_escape_htmltag($lst->label) . '</option>';
    }
    print '</optgroup>';
}
print '</select>';

// select2 turns the plain select into a searchable dropdown, mandatory once the instance
// holds dozens of lists. Navigation itself is handled in js/modules/pwa_call_list.js
print ajax_combobox('pwa-call-list-select');

print '</div>';

if (empty($lines)) {
    print '<div class="pwa-call-list-empty"><i class="fas fa-inbox"></i><p>Aucune ligne dans cette liste d\'appels.</p></div>';
} else {
    $contact = new Contact($db);

    foreach ($lines as $lineIndex => $line) {
        $displayInfo = reedcrm_call_list_get_line_contact_display($db, $line);
        $lastname    = dol_escape_htmltag($displayInfo['lastname']);
        $firstname   = dol_escape_htmltag($displayInfo['firstname']);
        $phone       = dol_escape_htmltag($displayInfo['phone']);
        $sourceHtml  = '';

        $sourceTitleHtml = '';
        $oppPercent = null;
        $oppProjectId = 0;

        if ($line->element_type === 'propal' && isModEnabled('propale')) {
            require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
            $propal = new Propal($db);
            if ($propal->fetch($line->element_id) > 0) {
                if ($propal->fk_project > 0) {
                    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                    $project = new Project($db);
                    if ($project->fetch($propal->fk_project) > 0) {
                        $sourceTitleHtml = $project->title;
                        $oppPercent = $project->opp_percent;
                        $oppProjectId = $project->id;
                    }
                }
            }
        } elseif ($line->element_type === 'facture' && isModEnabled('facture')) {
            require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
            $facture = new Facture($db);
            if ($facture->fetch($line->element_id) > 0 && $facture->fk_project > 0) {
                require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                $project = new Project($db);
                if ($project->fetch($facture->fk_project) > 0) {
                    $sourceTitleHtml = $project->title;
                    $oppPercent = $project->opp_percent;
                    $oppProjectId = $project->id;
                }
            }
        } elseif ($line->element_type === 'project' && isModEnabled('projet')) {
            require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
            $project = new Project($db);
            if ($project->fetch($line->element_id) > 0) {
                $sourceTitleHtml = $project->title;
                $oppPercent = $project->opp_percent;
                $oppProjectId = $project->id;
            }
        }

        $currentStatus = (int) $line->status;
        $indicators    = $lineIndicators[(int) $line->id] ?? [];
        $origin        = $indicators['origin'] ?? [];

        // call-list-line-row: moved by its arrows like the rows of the call list card (js/modules/call_list.js)
        $cardClass = 'pwa-call-card call-list-line-row';
        if (!empty($indicators['late'])) {
            $cardClass .= ' pwa-call-card--late';
        } elseif (!empty($indicators['upcoming'])) {
            $cardClass .= ' pwa-call-card--upcoming';
        }

        print '<div class="' . $cardClass . '" data-line-id="' . (int) $line->id . '" data-status="' . $currentStatus . '">';

        if ($canUnlink) {
            print '<button type="button" class="pwa-call-btn-unlink" data-action="unlink-line" title="' . dol_escape_htmltag($langs->trans('CallListLineUnlink')) . '" aria-label="' . dol_escape_htmltag($langs->trans('CallListLineUnlink')) . '"><i class="fas fa-unlink"></i></button>';
        }

        // Ligne 1 : Nom + prochain événement en retard ou à venir, comme sur la todo
        print '<div class="pwa-call-card-header">';
        if ($lastname || $firstname) {
            print '<span class="pwa-call-name"><i class="fas fa-user" style="color:#94a3b8;"></i> ' . $lastname . ' ' . $firstname . '</span>';
        } else {
            print '<span class="pwa-call-name pwa-call-name--empty"><i class="fas fa-user-slash" style="color:#cbd5e1;"></i> Contact non renseigné</span>';
        }
        if (!empty($indicators['late'])) {
            print '<span class="todo-card-late-badge" title="' . dol_escape_htmltag($indicators['next_event_title']) . '"><i class="fas fa-exclamation-circle"></i> ' . $langs->trans('TodoLateEvent') . '</span>';
        } elseif (!empty($indicators['upcoming'])) {
            print '<span class="todo-card-upcoming-badge" title="' . dol_escape_htmltag($indicators['next_event_title']) . '"><i class="fas fa-clock"></i> ' . $langs->trans('TodoUpcomingEvent') . '</span>';
        }
        print '</div>';

        if (!empty($line->date_creation)) {
            print '<div class="pwa-call-added-date"><i class="far fa-calendar-plus"></i> ' . $langs->trans('CallListLineAddedOn', dol_print_date($line->date_creation, 'day', 'tzuser')) . '</div>';
        }

        // Last call made about the object or its project. Always rendered: tapping the number fills it (js/modules/pwa_call_list.js)
        $lastCallDate = $indicators['last_call_date'] ?? 0;
        print '<div class="pwa-call-last-call' . ($lastCallDate ? '' : ' hideobject') . '"' . ($lastCallDate ? ' title="' . dol_escape_htmltag($indicators['last_call_label']) . '"' : '') . '>';
        print '<i class="fas fa-phone-alt"></i> <span class="pwa-call-last-call-text">' . ($lastCallDate ? $langs->trans('CallListLineLastCall', dol_print_date($lastCallDate, 'dayhour', 'tzuser')) : '') . '</span>';
        print '</div>';

        // Ligne 2 : Gros bouton d'appel vert + bouton copier le numéro
        if ($phone) {
            print '<div class="pwa-call-phone-row">';
            print '<a class="pwa-call-btn-call" href="tel:' . dol_escape_htmltag($phone) . '"><i class="fas fa-phone"></i> ' . $phone . '</a>';
            print '<button type="button" class="pwa-call-btn-copy" data-action="copy-phone" data-phone="' . dol_escape_htmltag($phone) . '" title="Copier le numéro" aria-label="Copier le numéro"><i class="fas fa-copy"></i></button>';
            print '</div>';
        } else {
            print '<div><span class="pwa-call-phone" style="color:#94a3b8;"><i class="fas fa-phone-slash" style="color:#94a3b8;"></i> Pas de téléphone</span></div>';
        }

        // Ligne 3 : puces des cartes de la todo (objet + montant HT arrondi + relances, tiers) puis % opp
        if (!empty($origin)) {
            $originCounted = !empty($origin['amount_ht']) || !empty($origin['relaunch_count']);
            print '<div class="pwa-call-indicators">';
            print '<span class="todo-origin-wrapper">';
            print '<a class="todo-link-badge todo-link-origin' . ($originCounted ? ' todo-link-origin-counted' : '') . '" href="' . dol_escape_htmltag($origin['url']) . '">';
            print '<i class="fas ' . $origin['picto'] . '"></i> ' . dol_escape_htmltag($origin['ref']) . '</a>';
            if (!empty($origin['amount_ht'])) {
                print '<span class="todo-origin-amount" title="' . dol_escape_htmltag($origin['amount_ht_full']) . '">' . dol_escape_htmltag($origin['amount_ht_short']) . '</span>';
            }
            if (!empty($origin['relaunch_count'])) {
                print '<span class="todo-relaunch-count" title="' . dol_escape_htmltag($langs->trans('TodoRelaunchCount', $origin['relaunch_count'])) . '"><i class="fas fa-headset"></i> ' . $origin['relaunch_count'] . '</span>';
            }
            print '</span>';
            if (!empty($indicators['soc_name'])) {
                print '<span class="todo-link-badge todo-link-soc" title="' . dol_escape_htmltag($indicators['soc_name']) . '"><i class="fas fa-building"></i> ' . dol_escape_htmltag($indicators['soc_name']) . '</span>';
            }
            if ($oppPercent !== null) {
                print '<span class="pwa-call-percent">' . round($oppPercent) . ' %</span>';
            }
            print '</div>';

            // Prochain événement à faire, en clair : un téléphone n'a pas de survol pour lire l'infobulle du badge
            if (!empty($indicators['next_event_date'])) {
                $isLate = !empty($indicators['late']);
                print '<div class="pwa-call-next-event' . ($isLate ? ' pwa-call-next-event--late' : '') . '">';
                print '<span class="pwa-call-next-event-date"' . ($isLate ? ' title="' . dol_escape_htmltag($langs->trans('TodoLateEvent')) . '"' : '') . '>';
                print '<i class="fas ' . ($isLate ? 'fa-exclamation-triangle' : 'fa-clock') . '"></i> ' . dol_print_date($indicators['next_event_date'], 'dayhour', 'tzuser');
                print '</span>';
                if ($indicators['next_event_label'] !== '') {
                    print '<span class="pwa-call-next-event-label">' . dol_escape_htmltag($indicators['next_event_label']) . '</span>';
                }
                print '</div>';
            }

            if ($oppProjectId > 0) {
                // Drill down to the App opportunity page, keeping the call list in the header.
                // Rendered as an explicit disclosure row: a plain coloured title reads as decoration
                // on a card that already holds five buttons, and nobody finds it.
                $oppUrl = dol_buildpath('/reedcrm/view/frontend/pwa_opportunity.php', 1)
                    . '?from_id=' . (int) $oppProjectId . '&from_type=project&call_list_id=' . (int) $object->id;
                print '<a class="pwa-call-opp-link" href="' . dol_escape_htmltag($oppUrl) . '">';
                print '<span class="pwa-call-opp-link-body">';
                if ($sourceTitleHtml) {
                    print '<span class="pwa-call-opp-link-title">' . dol_escape_htmltag($sourceTitleHtml) . '</span>';
                }
                print '<span class="pwa-call-opp-link-action">' . $langs->trans('SeeOpportunityTimeline') . '</span>';
                print '</span>';
                print '<span class="pwa-call-opp-link-chevron"><i class="fas fa-chevron-right"></i></span>';
                print '</a>';
            } elseif ($sourceTitleHtml) {
                print '<div class="pwa-call-title">' . dol_escape_htmltag($sourceTitleHtml) . '</div>';
            } else {
                print '<div style="margin-bottom:12px;"></div>';
            }
        }

        // Ligne 4 : les deux boutons ReedCRM de l'opportunité (événement passé / à venir), comme dans la barre
        // de la page opportunité, puis les flèches qui priorisent l'appel dans la liste
        $relaunchHtml = '';
        if ($oppProjectId > 0) {
            $relaunchHtml = reedcrm_render_relaunch_widget([
                'projectId'  => $oppProjectId,
                'mode'       => 'link',
                'canCreate'  => $canAddEvent,
                // The event form keeps the way back to the call list in its header
                'extraQuery' => '&call_list_id=' . (int) $object->id,
            ]);
        }
        if ($relaunchHtml !== '' || $canMove) {
            print '<div class="pwa-call-actions">';
            print $relaunchHtml;
            if ($canMove) {
                // Both always rendered, the JS hiding the up one of the first card and the down one of the last card after each move
                print '<div class="pwa-call-move-btns">';
                print '<button type="button" class="pwa-call-btn-move call-list-line-move' . ($lineIndex === 0 ? ' hideobject' : '') . '" data-direction="up" title="' . dol_escape_htmltag($langs->trans('CallListLineMoveUp')) . '" aria-label="' . dol_escape_htmltag($langs->trans('CallListLineMoveUp')) . '"><i class="fas fa-arrow-up"></i></button>';
                print '<button type="button" class="pwa-call-btn-move call-list-line-move' . ($lineIndex === count($lines) - 1 ? ' hideobject' : '') . '" data-direction="down" title="' . dol_escape_htmltag($langs->trans('CallListLineMoveDown')) . '" aria-label="' . dol_escape_htmltag($langs->trans('CallListLineMoveDown')) . '"><i class="fas fa-arrow-down"></i></button>';
                print '</div>';
            }
            print '</div>';
        }

        print '<div class="pwa-call-error" style="display:none;"></div>';

        print '</div>';
    }
}

print '</div>';

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

llxFooter();
$db->close();

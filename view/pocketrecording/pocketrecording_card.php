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
 * \file    view/pocketrecording/pocketrecording_card.php
 * \ingroup reedcrm
 * \brief   Card of a Pocket recording: summary, action items, transcript and linked objects.
 */

// Load ReedCRM environment
if (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} elseif (file_exists('../../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/parsemd.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formprojet.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

// Load ReedCRM libraries
require_once __DIR__ . '/../../class/pocketrecording.class.php';
require_once __DIR__ . '/../../class/pocketactionitem.class.php';
require_once __DIR__ . '/../../class/pocketapi.class.php';
require_once __DIR__ . '/../../class/pocketsync.class.php';
require_once __DIR__ . '/../../lib/reedcrm_pocketrecording.lib.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['agenda']);

// Get parameters
$id      = GETPOSTINT('id');
$action  = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$show    = GETPOST('show', 'aZ09') ?: 'card';

// Initialize technical objects
$object      = new PocketRecording($db);
$form        = new Form($db);
$contextpage = 'pocketrecordingcard';
$hookmanager->initHooks([$contextpage]);

// Security check
$permissiontoread    = $user->hasRight('reedcrm', 'pocketrecording', 'read');
$permissiontoadd     = $user->hasRight('reedcrm', 'pocketrecording', 'write');
$permissiontodelete  = $user->hasRight('reedcrm', 'pocketrecording', 'delete');

saturne_check_access($permissiontoread);

if ($id > 0 && $object->fetch($id) <= 0) {
    accessforbidden($langs->trans('ErrorRecordNotFound'));
}

/*
 * Actions
 */

// The thirdparty is set from the banner, as on every Saturne card. The template writes the field
// the banner form names and reloads the object, so the view below already shows the new value.
require_once __DIR__ . '/../../../saturne/core/tpl/actions/banner_actions.tpl.php';

// Attaching from the card: the objects offered are the ones of the thirdparty of the recording
if ($action == 'link_object' && $permissiontoadd) {
    // The selector carries 'linkName:objectId', the empty option of Dolibarr carries -1 and means
    // the form was submitted without a choice: nothing to attach, and nothing to complain about
    list($linkName, $linkedObjectId) = array_pad(explode(':', GETPOST('object_to_link', 'alphanohtml'), 2), 2, '');

    if (!empty($linkName) && (int) $linkedObjectId > 0) {
        if (reedcrm_pocket_link_recording($object, $linkName, (int) $linkedObjectId) > 0) {
            setEventMessage($langs->trans('PocketObjectLinked'));
        } else {
            setEventMessages($langs->trans('PocketRecordingLinkFailed'), [], 'errors');
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
    exit;
}

if ($action == 'unlink_object' && $permissiontoadd) {
    $linkName       = GETPOST('link_name', 'alphanohtml');
    $linkedObjectId = GETPOSTINT('linked_object_id');

    if (!empty($linkName) && $linkedObjectId > 0 && reedcrm_pocket_unlink_recording($object, $linkName, $linkedObjectId) > 0) {
        setEventMessage($langs->trans('PocketObjectUnlinked'));
    } else {
        setEventMessages($langs->trans('PocketRecordingLinkFailed'), [], 'errors');
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
    exit;
}

if ($action == 'refresh_from_pocket' && $permissiontoadd) {
    $pocketSync = new PocketSync($db);
    $recording  = $pocketSync->api->getRecording($object->pocket_id);

    if ($recording === null) {
        setEventMessages($pocketSync->api->error, [], 'errors');
    } else {
        $result = $pocketSync->importRecording(
            $recording['data'] ?? [],
            $user,
            (string) $object->pocket_folder_id,
            (string) $object->pocket_folder_label
        );
        if ($result < 0) {
            setEventMessages($langs->trans('PocketRefreshFailed'), [], 'errors');
        } else {
            setEventMessage($langs->trans('PocketRefreshDone'));
        }
    }

    header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
    exit;
}

// The signed audio URL expires quickly, it is resolved on demand and never stored
if ($action == 'play_audio' && $permissiontoread) {
    $pocketApi = new PocketApi($db);
    $audioUrl  = $pocketApi->getAudioUrl($object->pocket_id);

    if (empty($audioUrl)) {
        setEventMessages($pocketApi->error ?: $langs->trans('PocketAudioUnavailable'), [], 'errors');
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $object->id);
    } else {
        header('Location: ' . $audioUrl);
    }
    exit;
}

if ($action == 'confirm_delete' && $confirm == 'yes' && $permissiontodelete) {
    if ($object->delete($user) > 0) {
        setEventMessage($langs->trans('RecordDeleted'));
        header('Location: ' . dol_buildpath('/custom/reedcrm/view/pocketrecording/pocketrecording_list.php', 1));
        exit;
    }

    setEventMessages($object->error, $object->errors, 'errors');
}

/*
 * View
 */

$title    = $langs->trans('PocketRecording') . ' - ' . $object->ref;
$help_url = 'FR:Module_ReedCRM';

saturne_header(0, '', $title, $help_url);

$head = pocketrecording_prepare_head($object);
print dol_get_fiche_head($head, $show == 'transcript' ? 'transcript' : 'card', $langs->trans('PocketRecording'), -1, $object->picto);

$moreHtmlRef = '<div class="refidno">';
$moreHtmlRef .= $langs->trans('PocketRecordingDate') . ': ' . dol_print_date($object->recording_date, 'dayhour');
if (!empty($object->duration)) {
    $moreHtmlRef .= ' - ' . $langs->trans('Duration') . ': ' . reedcrm_pocket_format_duration((int) $object->duration);
}
if (!empty($object->pocket_folder_label)) {
    $moreHtmlRef .= '<br>' . $langs->trans('PocketFolder') . ': ' . dol_escape_htmltag($object->pocket_folder_label);
}
$moreHtmlRef .= '</div>';

$linkBack = '<a href="' . dol_buildpath('/custom/reedcrm/view/pocketrecording/pocketrecording_list.php', 1) . '?restore_lastsearch_values=1">' . $langs->trans('BackToList') . '</a>';
saturne_banner_tab($object, 'id', $linkBack, 1, 'rowid', 'ref', $moreHtmlRef);

// The status is changed on the badge of the banner, where it is read, instead of taking a row of
// the table below. The banner is printed by Dolibarr, so the data the picker needs travels through
// this block and the script hangs the choices under the badge it finds.
if ($permissiontoadd) {
    $statusChoices = [];
    foreach ($object->fields['status']['arrayofkeyval'] as $statusKey => $statusLabel) {
        $statusChoices[] = ['key' => (int) $statusKey, 'label' => $langs->transnoentities($statusLabel)];
    }

    print '<span class="reedcrm-pocket-status-picker" hidden';
    print ' data-url="' . dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/pocket_recording.php', 1)) . '"';
    print ' data-recording-id="' . $object->id . '"';
    print ' data-token="' . newToken() . '"';
    print ' data-title="' . dol_escape_htmltag($langs->trans('PocketChangeStatus')) . '"';
    print ' data-statuses="' . dol_escape_htmltag(json_encode($statusChoices)) . '"';
    print '></span>';
}

if ($action == 'delete') {
    print $form->formconfirm($_SERVER['PHP_SELF'] . '?id=' . $object->id, $langs->trans('DeletePocketRecording'), $langs->trans('ConfirmDeletePocketRecording'), 'confirm_delete', '', 'no', 1);
}

print '<div class="fichecenter">';

if ($show == 'transcript') {
    print '<div class="underbanner clearboth"></div>';
    // The block keeps the original line breaks through white-space: pre-wrap, so the raw text is
    // escaped and printed as is instead of being converted to <br>
    print '<div class="reedcrm-pocket-transcript">';
    print !empty($object->transcript) ? dol_escape_htmltag($object->transcript) : '<span class="opacitymedium">' . $langs->trans('PocketNoTranscript') . '</span>';
    print '</div>';
} else {
    print '<div class="underbanner clearboth"></div>';
    print '<table class="border centpercent tableforfield">';

    print '<tr><td class="titlefield">' . $langs->trans('PocketTags') . '</td><td>' . dol_escape_htmltag($object->pocket_tags) . '</td></tr>';

    // The URL Pocket signs expires within the hour, so the player carries the endpoint that
    // resolves it rather than the URL itself: the native <audio> is injected on the first play
    print '<tr><td>' . $langs->trans('PocketAudio') . '</td><td>';
    print '<div class="reedcrm-pocket-audio" data-url="' . dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/pocket_audio_url.php', 1) . '?id=' . $object->id) . '">';
    print '<span class="reedcrm-pocket-audio-load"><i class="fas fa-play"></i>' . $langs->trans('PocketPlayAudio') . '</span>';
    print '</div>';
    print '</td></tr>';

    print '<tr><td>' . $langs->trans('PocketLastSyncDate') . '</td><td>' . dol_print_date($object->last_sync_date, 'dayhour') . '</td></tr>';

    print '</table>';

    // Summary, edited in place like the action items: a click on the text swaps the rendered
    // markdown for its source, and leaving the field saves it. The edition stays on the markdown
    // because the Pocket blocks it embeds would not survive a round trip through a rich editor.
    print '<br>';
    print load_fiche_titre($langs->trans('PocketSummary'), '', '');
    print '<div class="underbanner clearboth"></div>';

    print '<div class="reedcrm-pocket-summary-block"';
    if ($permissiontoadd) {
        print ' data-url="' . dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/pocket_recording.php', 1)) . '"';
        print ' data-recording-id="' . $object->id . '"';
        print ' data-token="' . newToken() . '"';
    }
    print '>';

    print '<div class="reedcrm-pocket-summary"' . ($permissiontoadd ? ' title="' . dol_escape_htmltag($langs->trans('PocketEditSummary')) . '"' : '') . '>';
    print !empty($object->summary) ? reedcrm_pocket_summary_to_html($object->summary) : '<span class="opacitymedium">' . $langs->trans('PocketNoSummary') . '</span>';
    print '</div>';

    if ($permissiontoadd) {
        // The source travels in its own field rather than in an attribute: it is a multi line text
        print '<textarea class="reedcrm-pocket-summary-edit" hidden>' . dol_escape_htmltag((string) $object->summary, 0, 1) . '</textarea>';
        print '<div class="opacitymedium small reedcrm-pocket-summary-help" hidden>' . $langs->trans('PocketSummaryEditHelp') . '</div>';
    }

    print '<div class="opacitymedium small reedcrm-pocket-summary-edited"' . (empty($object->summary_edited) ? ' hidden' : '') . '>' . $langs->trans('PocketSummaryEditedHint') . '</div>';

    print '</div>';

    // Action items. Read from their own rows and not from the recording JSON: the assigned user
    // and the created event belong to Dolibarr and must survive a re-import from Pocket.
    $actionItemStatic = new PocketActionItem($db);
    $actionItems      = $actionItemStatic->fetchAllByRecording($object->id);
    $actionItemUrl    = dol_buildpath('/custom/reedcrm/ajax/pocket_action_item.php', 1);
    $eventStatic      = new ActionComm($db);

    print '<br>';
    print load_fiche_titre($langs->trans('PocketActionItems'), '', '');
    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans('Label') . '</td>';
    print '<td class="center">' . $langs->trans('Deadline') . '</td>';
    print '<td>' . $langs->trans('PocketAssignedUser') . '</td>';
    print '<td class="center">' . $langs->trans('Event') . '</td>';
    print '</tr>';

    if (is_array($actionItems)) {
        foreach ($actionItems as $actionItem) {
            print '<tr class="oddeven pocket-action-row" data-action-item-id="' . $actionItem->id . '"';
            print ' data-url="' . dol_escape_htmltag($actionItemUrl) . '" data-token="' . newToken() . '">';

            // The wording extracted by Pocket is rarely usable as is, so both the label and its
            // description are edited in place: each field saves itself when it loses the focus
            print '<td class="pocket-action-text">';
            if ($permissiontoadd) {
                print '<input type="text" class="pocket-action-label" value="' . dol_escape_htmltag((string) $actionItem->label) . '" placeholder="' . dol_escape_htmltag($langs->trans('Label')) . '">';
                print '<textarea class="pocket-action-description" rows="2" placeholder="' . dol_escape_htmltag($langs->trans('Description')) . '">' . dol_escape_htmltag((string) $actionItem->description) . '</textarea>';
            } else {
                print dol_escape_htmltag((string) $actionItem->label);
                if (!empty($actionItem->description)) {
                    print '<br><span class="opacitymedium small">' . dol_escape_htmltag($actionItem->description) . '</span>';
                }
            }
            print '</td>';

            print '<td class="center nowraponall">';
            if ($permissiontoadd) {
                print '<input type="date" class="flat pocket-action-due-date" value="' . (!empty($actionItem->due_date) ? dol_print_date($actionItem->due_date, '%Y-%m-%d') : '') . '">';
            } else {
                print !empty($actionItem->due_date) ? dol_print_date($actionItem->due_date, 'day') : '';
            }
            print '</td>';

            print '<td>';
            if ($permissiontoadd) {
                print $form->select_dolusers($actionItem->fk_user_assign, 'fk_user_assign_' . $actionItem->id, 1, null, 0, '', '', 0, 0, 0, '', 0, '', 'pocket-action-assign minwidth150');
            } elseif ($actionItem->fk_user_assign > 0) {
                $assignedUser = new User($db);
                $assignedUser->fetch($actionItem->fk_user_assign);
                print $assignedUser->getNomUrl(1);
            }
            // Pocket also names an assignee, kept as a hint since it is free text, not a Dolibarr user
            if (!empty($actionItem->pocket_assignee)) {
                print '<br><span class="opacitymedium small">' . $langs->trans('PocketAssignee') . ': ' . dol_escape_htmltag($actionItem->pocket_assignee) . '</span>';
            }
            print '</td>';

            print '<td class="center">';
            if ($actionItem->fk_actioncomm > 0 && $eventStatic->fetch($actionItem->fk_actioncomm) > 0) {
                print $eventStatic->getNomUrl(1);
            } elseif ($permissiontoadd && isModEnabled('agenda')) {
                print '<span class="butAction butActionSmall pocket-action-create-event" data-created-label="' . dol_escape_htmltag($langs->trans('Event')) . '">';
                print $langs->trans('PocketCreateEvent');
                print '</span>';
            }
            print '</td>';

            print '</tr>';
        }
    }

    if (empty($actionItems)) {
        print '<tr><td colspan="4" class="opacitymedium center">' . $langs->trans('PocketNoActionItem') . '</td></tr>';
    }

    print '</table>';
    print '</div>';
}

print '</div>';

print dol_get_fiche_end();

/*
 * Action bar
 */

print '<div class="tabsAction">';

if ($permissiontoadd) {
    print '<a class="butAction" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=refresh_from_pocket&token=' . newToken() . '">' . $langs->trans('PocketRefreshFromApi') . '</a>';
}
print '<a class="butAction" target="_blank" rel="noopener" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=play_audio&token=' . newToken() . '">' . $langs->trans('PocketDownloadAudio') . '</a>';
if ($permissiontodelete) {
    print '<a class="butActionDelete" href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=delete&token=' . newToken() . '">' . $langs->trans('Delete') . '</a>';
}

print '</div>';

/*
 * Linked objects
 */

// A recording is attached either from the tab of the business object, or from here: the
// conversation was held with a thirdparty, so what it talks about is one of its objects
if ($show != 'transcript') {
    $object->fetchObjectLinked();

    print load_fiche_titre($langs->trans('PocketLinkedObjects'), '', '');

    // Attach form. The objects are searched by the module and not through the native link block:
    // the search runs on the types enabled for the recordings, across every thirdparty, and the
    // list opens already filled with the objects of the thirdparty of the recording.
    if ($permissiontoadd) {
        $linkableObjectTypes = [];
        foreach (reedcrm_pocket_get_enabled_linked_object_types() as $linkableType) {
            $linkableMetadata = reedcrm_pocket_get_linkable_objects()[$linkableType] ?? [];
            if (!empty($linkableMetadata['langs'])) {
                $linkableObjectTypes[$linkableType] = $langs->trans($linkableMetadata['langs']);
            }
        }

        $initialObjects = reedcrm_pocket_search_objects($object, '', '', 20);

        print '<form method="POST" action="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '">';
        print '<input type="hidden" name="token" value="' . newToken() . '">';
        print '<input type="hidden" name="action" value="link_object">';
        print '<input type="hidden" name="object_to_link" value="">';
        print '<div class="reedcrm-pocket-link-form" data-url="' . dol_escape_htmltag(dol_buildpath('/custom/reedcrm/ajax/pocket_recording.php', 1)) . '"';
        print ' data-recording-id="' . $object->id . '" data-token="' . newToken() . '">';

        print '<span>' . $langs->trans('PocketLinkObject') . '</span>';
        print $form->selectarray('object_type', $linkableObjectTypes, '', $langs->trans('PocketAllObjectTypes'), 0, 0, '', 0, 0, 0, '', 'reedcrm-pocket-object-type minwidth150', 0);

        print '<div class="reedcrm-pocket-object-search-wrapper">';
        print '<input type="text" class="reedcrm-pocket-object-search minwidth300" autocomplete="off" placeholder="' . dol_escape_htmltag($langs->trans('PocketSearchObject')) . '">';

        // The list is printed already filled, so it is usable before a single key is pressed
        print '<ul class="reedcrm-pocket-object-results" hidden data-empty-label="' . dol_escape_htmltag($langs->trans('PocketNoObjectFound')) . '">';
        foreach ($initialObjects as $initialObject) {
            print '<li data-key="' . dol_escape_htmltag($initialObject['key']) . '">' . dol_escape_htmltag(reedcrm_pocket_format_object_choice($initialObject)) . '</li>';
        }
        if (empty($initialObjects)) {
            print '<li class="opacitymedium reedcrm-pocket-object-empty">' . $langs->trans('PocketNoObjectFound') . '</li>';
        }
        print '</ul>';
        print '</div>';

        print '<input type="submit" class="button smallpaddingimp" value="' . $langs->trans('Add') . '" disabled>';
        print '</div>';
        print '</form>';
    }

    print '<div class="div-table-responsive-no-min">';
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans('Type') . '</td>';
    print '<td>' . $langs->trans('Ref') . '</td>';
    print '<td class="center">' . $langs->trans('Date') . '</td>';
    print '<td class="right">' . $langs->trans('Amount') . '</td>';
    print '<td class="center"></td>';
    print '</tr>';

    $linkedCount = 0;
    foreach ($object->linkedObjects as $linkedType => $linkedInstances) {
        $linkedMetadata = reedcrm_pocket_get_object_metadata_from_link_name($linkedType);
        $typeLabel      = !empty($linkedMetadata['langs']) ? $langs->trans($linkedMetadata['langs']) : $linkedType;

        foreach ($linkedInstances as $linkedInstance) {
            $linkedData = reedcrm_pocket_get_object_date_and_amount($linkedInstance);

            print '<tr class="oddeven">';
            print '<td class="minwidth100">' . dol_escape_htmltag($typeLabel) . '</td>';
            print '<td>' . $linkedInstance->getNomUrl(1) . '</td>';
            print '<td class="center nowraponall">' . (!empty($linkedData['date']) ? dol_print_date($linkedData['date'], 'day') : '') . '</td>';
            print '<td class="right nowraponall">' . ($linkedData['amount'] !== null ? price($linkedData['amount'], 0, $langs, 1, -1, -1, $conf->currency) : '') . '</td>';
            print '<td class="center">';
            if ($permissiontoadd) {
                print '<a href="' . $_SERVER['PHP_SELF'] . '?id=' . $object->id . '&action=unlink_object&link_name=' . urlencode($linkedType) . '&linked_object_id=' . $linkedInstance->id . '&token=' . newToken() . '" title="' . dol_escape_htmltag($langs->trans('PocketUnlinkObject')) . '">';
                print img_picto($langs->trans('PocketUnlinkObject'), 'unlink');
                print '</a>';
            }
            print '</td>';
            print '</tr>';
            $linkedCount++;
        }
    }

    if ($linkedCount == 0) {
        print '<tr><td colspan="5" class="opacitymedium center">' . $langs->trans('PocketNoLinkedObject') . '</td></tr>';
    }

    print '</table>';
    print '</div>';
}

llxFooter();
$db->close();

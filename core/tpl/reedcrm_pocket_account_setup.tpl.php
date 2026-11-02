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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/reedcrm_pocket_account_setup.tpl.php
 * \ingroup reedcrm
 * \brief   Connection and imported folder of a Pocket account, shared by the setup page and the
 *          Pocket tab of the user card. The actions are run by reedcrm_pocket_process_account_action().
 *
 * Expects:
 *  - $pocketAccount (PocketAccount) Account shown, shared or personal.
 *  - $pocketFormUrl (string)        Page the forms and links are sent to, its own parameters included.
 */

// Protection to avoid direct call of template
if (empty($conf) || !is_object($conf)) {
    print 'Error, template page can be called as an URL';
    exit;
}

global $form, $langs;

$apiKey      = $pocketAccount->apiKey;
$folderId    = $pocketAccount->folderId;
$folderLabel = $pocketAccount->folderLabel;

// The forms post to the page itself, the parameters of its URL travel as hidden fields
$pocketFormAction = strtok($pocketFormUrl, '?');
$pocketFormParams = [];
parse_str((string) parse_url($pocketFormUrl, PHP_URL_QUERY), $pocketFormParams);
$pocketLinkUrl    = $pocketFormUrl . (strpos($pocketFormUrl, '?') === false ? '?' : '&');

$pocketHiddenFields = '<input type="hidden" name="token" value="' . newToken() . '">';
foreach ($pocketFormParams as $paramName => $paramValue) {
    $pocketHiddenFields .= '<input type="hidden" name="' . dol_escape_htmltag((string) $paramName) . '" value="' . dol_escape_htmltag((string) $paramValue) . '">';
}

// Connection
$accountTitle = $pocketAccount->isShared() ? $langs->trans('PocketSharedAccount') : $langs->trans('PocketPersonalAccount');
$accountHelp  = $pocketAccount->isShared() ? $langs->trans('PocketSharedAccountHelp') : $langs->trans('PocketPersonalAccountHelp');
print load_fiche_titre($form->textwithpicto($accountTitle, $accountHelp), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<form method="POST" action="' . dol_escape_htmltag($pocketFormAction) . '">';
print $pocketHiddenFields;
print '<input type="hidden" name="action" value="set_pocket_api_key">';
print '<tr class="oddeven">';
print '<td>' . $form->textwithpicto($langs->trans('PocketApiKey'), $langs->trans('PocketApiKeyHelp')) . '</td>';
print '<td>';
// The stored key is never sent back to the browser, only the hint of the one in place.
print '<input type="password" class="minwidth300" name="pocket_api_key" autocomplete="new-password" placeholder="' . ($apiKey !== '' ? dol_escape_htmltag(dol_trunc($apiKey, 10, 'right', 'UTF-8', 1)) : 'pk_...') . '">';
print '</td>';
print '<td class="center">';
print '<input type="submit" class="button" value="' . $langs->trans('Save') . '">';
if ($apiKey !== '') {
    print ' <a class="button butActionDelete" href="' . dol_escape_htmltag($pocketLinkUrl . 'action=del_pocket_api_key&token=' . newToken()) . '">' . $langs->trans('Delete') . '</a>';
}
print '</td>';
print '</tr>';
print '</form>';

print '<tr class="oddeven">';
print '<td>' . $langs->trans('PocketConnectionState') . '</td>';
print '<td>';
print $apiKey !== '' ? '<span class="badge badge-status4 badge-status">' . $langs->trans('PocketApiKeySet') . '</span>' : '<span class="opacitymedium">' . $langs->trans('PocketApiKeyMissing') . '</span>';
print '</td>';
print '<td class="center">';
if ($apiKey !== '') {
    print '<a class="button" href="' . dol_escape_htmltag($pocketLinkUrl . 'action=test_pocket_connection&token=' . newToken()) . '">' . $langs->trans('PocketTestConnection') . '</a>';
} else {
    print '<span class="butActionRefused classfortooltip">' . $langs->trans('PocketTestConnection') . '</span>';
}
print '</td>';
print '</tr>';

print '</table>';

// Imported folder
print load_fiche_titre($langs->trans('PocketFolderTitle'), '', '');

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Parameter') . '</td>';
print '<td>' . $langs->trans('Value') . '</td>';
print '<td class="center">' . $langs->trans('Action') . '</td>';
print '</tr>';

print '<form method="POST" action="' . dol_escape_htmltag($pocketFormAction) . '">';
print $pocketHiddenFields;
print '<input type="hidden" name="action" value="set_pocket_folder">';
print '<tr class="oddeven">';
print '<td>' . $form->textwithpicto($langs->trans('PocketFolder'), $langs->trans('PocketFolderHelp')) . '</td>';
print '<td>';

if ($apiKey === '') {
    print '<span class="opacitymedium">' . $langs->trans('PocketApiKeyMissing') . '</span>';
} else {
    $pocketApi = $pocketAccount->getApi();
    $folders   = $pocketApi->getFolders();

    if ($folders === null) {
        // The API is down or the key is wrong: keep the configured folder visible instead of
        // silently offering an empty selector that would clear it on save.
        print '<span class="error">' . dol_escape_htmltag($pocketApi->error) . '</span>';
        if ($folderId !== '') {
            print '<br><span class="opacitymedium">' . dol_escape_htmltag($folderLabel ?: $folderId) . '</span>';
        }
    } else {
        $folderOptions = ['' => $langs->trans('PocketNoFolderSelected')];
        foreach ($folders as $folder) {
            $folderOptions[$folder['id']] = str_repeat('&nbsp;&nbsp;', $folder['depth']) . $folder['label'];
        }
        print $form->selectarray('pocket_folder_id', $folderOptions, $folderId, 0, 0, 0, '', 1, 0, 0, '', 'minwidth300');
    }
}

print '</td>';
print '<td class="center">';
print '<input type="submit" class="button"' . ($apiKey === '' ? ' disabled' : '') . ' value="' . $langs->trans('Save') . '">';
print '</td>';
print '</tr>';
print '</form>';

print '<tr class="oddeven">';
print '<td>' . $form->textwithpicto($langs->trans('PocketManualSync'), $langs->trans('PocketManualSyncHelp')) . '</td>';
print '<td>';
print $folderId !== '' ? dol_escape_htmltag($folderLabel ?: $folderId) : '<span class="opacitymedium">' . $langs->trans('PocketFolderMissing') . '</span>';
print '</td>';
print '<td class="center">';
if ($pocketAccount->isConfigured()) {
    print '<a class="button" href="' . dol_escape_htmltag($pocketLinkUrl . 'action=sync_pocket_recordings&token=' . newToken()) . '">' . $langs->trans('PocketSyncNow') . '</a>';
} else {
    print '<span class="butActionRefused classfortooltip">' . $langs->trans('PocketSyncNow') . '</span>';
}
print '</td>';
print '</tr>';

print '</table>';

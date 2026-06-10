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
require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/medias.lib.php';
require_once __DIR__ . '/../../class/calllist.class.php';
require_once __DIR__ . '/../../class/calllistline.class.php';

global $conf, $db, $langs, $user;

saturne_load_langs();

$id     = GETPOSTINT('id');
$action = GETPOST('action', 'nohtml');

// LOG ALL REQUESTS TO DEBUG
$debugFile = $conf->ecm->dir_output . '/debug_upload_top.log';
file_put_contents($debugFile, "===== REQ to pwa_call_list =====\n", FILE_APPEND);
file_put_contents($debugFile, "GET: " . print_r($_GET, true) . "\n", FILE_APPEND);
file_put_contents($debugFile, "POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
file_put_contents($debugFile, "ACTION: " . $action . "\n", FILE_APPEND);


if (!$user->hasRight('reedcrm', 'call_list', 'read') && !$user->hasRight('reedcrm', 'call_list', 'read_subordinates') && !$user->hasRight('reedcrm', 'call_list', 'read_all')) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

$object     = new CallList($db);
$lineObject = new CallListLine($db);

if ($id > 0) {
    $object->fetch($id);
}

if (empty($object->id)) {
    accessforbidden($langs->trans('RecordNotFound'), 0);
    exit;
}

$canRead = false;
if ($user->hasRight('reedcrm', 'call_list', 'read_all') || $user->admin) {
    $canRead = true;
} elseif ($user->hasRight('reedcrm', 'call_list', 'read_subordinates')) {
    $childIds = $user->getAllChildIds(1);
    if (isset($childIds[$object->fk_user_assign])) {
        $canRead = true;
    }
} elseif ($user->hasRight('reedcrm', 'call_list', 'read')) {
    if ($object->fk_user_assign == $user->id) {
        $canRead = true;
    }
}

if (!$canRead) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}


// Handle audio upload — JS posts to current URL with action=add_audio
if ($action === 'add_audio') {
    $debugFile = $conf->ecm->dir_output . '/debug_upload.log';
    file_put_contents($debugFile, "add_audio triggered\n", FILE_APPEND);
    file_put_contents($debugFile, "POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
    file_put_contents($debugFile, "FILES: " . print_r($_FILES, true) . "\n", FILE_APPEND);
    
    if (!empty($_FILES['audio']['tmp_name'])) {
        $audioModule   = GETPOST('module_name', 'alpha');
        $audioSubDir   = GETPOST('sub_dir', 'nohtml'); // Allow hyphens/slashes
        file_put_contents($debugFile, "module=$audioModule subdir=$audioSubDir\n", FILE_APPEND);
        $audioModLower = !empty($audioModule) ? dol_strtolower($audioModule) : 'reedcrm';

        $uploadDir = !empty($conf->$audioModLower->dir_output)
            ? $conf->$audioModLower->dir_output
            : $conf->ecm->dir_output . '/' . $audioModLower;
        if (!empty($audioSubDir)) {
            $uploadDir .= '/' . $audioSubDir;
        }
        if (!dol_is_dir($uploadDir)) {
            $mkres = dol_mkdir($uploadDir);
            file_put_contents($debugFile, "mkdir $uploadDir res=$mkres\n", FILE_APPEND);
        }
        $destFile = $uploadDir . '/' . dol_print_date(dol_now(), 'dayhourlog') . '_audio.wav';
        $res = move_uploaded_file($_FILES['audio']['tmp_name'], $destFile);
        file_put_contents($debugFile, "move_uploaded_file to $destFile res=$res\n", FILE_APPEND);
    } else {
        file_put_contents($debugFile, "FAILED: tmp_name is empty\n", FILE_APPEND);
    }
}


// Handle audio delete — JS posts to current URL with action=delete_audio
if ($action === 'delete_audio') {
    $audioModule   = GETPOST('module_name', 'alpha');
    $audioSubDir   = GETPOST('sub_dir', 'alpha');
    $audioFilename = GETPOST('filename', 'alpha');
    $audioModLower = !empty($audioModule) ? dol_strtolower($audioModule) : 'reedcrm';

    $uploadDir = !empty($conf->$audioModLower->dir_output)
        ? $conf->$audioModLower->dir_output
        : $conf->ecm->dir_output . '/' . $audioModLower;
    if (!empty($audioSubDir)) {
        $uploadDir .= '/' . $audioSubDir;
    }
    $filePath = $uploadDir . '/' . basename($audioFilename);
    if (!empty($audioFilename) && file_exists($filePath)) {
        dol_delete_file($filePath);
    }
    // Fall through — page renders normally so JS can parse the updated audio block
}

$lines = $lineObject->fetchAllByCallList($object->id);

$toCallCount = 0;
foreach ($lines as $line) {
    if ((int) $line->status === CallListLine::STATUS_TO_CALL) {
        $toCallCount++;
    }
}

$title   = dol_escape_htmltag($object->label);
$helpUrl = 'FR:Module_ReedCRM';
$moreJS  = ['/custom/saturne/js/saturne.min.js', '/custom/reedcrm/js/reedcrm.min.js'];
$moreCSS = ['/custom/saturne/css/saturne.min.css', '/custom/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

llxHeader('', $title, $helpUrl, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-call-list');

$pwaHeaderCenterHtml = '<div style="background:#e2e8f0;padding:4px 10px;border-radius:12px;font-size:13px;font-weight:bold;color:#475569;">'
    . '<i class="fas fa-phone"></i> ' . dol_escape_htmltag($object->label)
    . ' — ' . $toCallCount . '/' . count($lines) . ' à appeler'
    . '</div>';
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

$statusLabels = [
    CallListLine::STATUS_TO_CALL   => 'À appeler',
    CallListLine::STATUS_CALLED    => 'Appelé',
    CallListLine::STATUS_NO_ANSWER => 'Pas de rép.',
    CallListLine::STATUS_CALLBACK  => 'Rappel',
];
$statusColors = [
    CallListLine::STATUS_TO_CALL   => '#3b82f6',
    CallListLine::STATUS_CALLED    => '#22c55e',
    CallListLine::STATUS_NO_ANSWER => '#ef4444',
    CallListLine::STATUS_CALLBACK  => '#f97316',
];

// Page styles live in css/scss/pages/_pwa-call-list.scss (compiled into reedcrm.min.css)

$ajaxUrl          = dol_buildpath('/custom/reedcrm/ajax/update_call_list_line_status.php', 1);
$actioncommAjaxUrl = dol_buildpath('/custom/reedcrm/ajax/create_call_actioncomm.php', 1);
$createActioncomm  = getDolGlobalInt('REEDCRM_CALL_LIST_CREATE_ACTIONCOMM');
$token             = newToken();

print '<div class="pwa-call-list-container"'
    . ' data-ajax-url="' . dol_escape_htmltag($ajaxUrl) . '"'
    . ' data-actioncomm-url="' . dol_escape_htmltag($actioncommAjaxUrl) . '"'
    . ' data-create-actioncomm="' . (int) $createActioncomm . '"'
    . ' data-token="' . dol_escape_htmltag($token) . '">';

// Saturne expects an input named "token" in the DOM for AJAX uploads
print '<input type="hidden" name="token" value="' . dol_escape_htmltag($token) . '">';

$dateStart = $object->date_start ? dol_print_date($object->date_start, 'day') : '—';
$dateEnd   = $object->date_end   ? dol_print_date($object->date_end,   'day') : '—';
print '<div class="pwa-call-list-header">';

$sqlLists = "SELECT rowid, label, fk_user_assign FROM " . MAIN_DB_PREFIX . "reedcrm_call_list";
$sqlLists .= " WHERE entity IN (" . getEntity('call_list') . ")";
$sqlLists .= " AND status = " . CallList::STATUS_ACTIVE;
$sqlLists .= " ORDER BY date_creation DESC";
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

print '<select class="pwa-call-list-select" onchange="if(this.value) window.location.href=\'?id=\'+this.value">';
foreach ($myLists as $lst) {
    $sel = ($lst->rowid == $object->id) ? ' selected' : '';
    print '<option value="' . $lst->rowid . '"' . $sel . '>' . dol_escape_htmltag($lst->label) . '</option>';
}
if (!empty($otherLists)) {
    if (!empty($myLists)) print '<option disabled>---------------</option>';
    foreach ($otherLists as $lst) {
        $sel = ($lst->rowid == $object->id) ? ' selected' : '';
        print '<option value="' . $lst->rowid . '"' . $sel . '>' . dol_escape_htmltag($lst->label) . '</option>';
    }
}
print '</select>';

print '</div>';

if (empty($lines)) {
    print '<div class="pwa-call-list-empty"><i class="fas fa-inbox"></i><p>Aucune ligne dans cette liste d\'appels.</p></div>';
} else {
    $contact = new Contact($db);

    foreach ($lines as $line) {
        $lastname   = '';
        $firstname  = '';
        $phone      = '';
        $sourceHtml = '';

        if (!empty($line->fk_contact)) {
            $contact->fetch($line->fk_contact);
            $lastname  = dol_escape_htmltag($contact->lastname);
            $firstname = dol_escape_htmltag($contact->firstname);
            $phone     = dol_escape_htmltag($contact->phone_pro ?: $contact->phone_mobile ?: '');
        }

        $sourceRefHtml = '';
        $sourceTitleHtml = '';
        $oppPercent = null;
        $oppAmount  = null;
        $saturneModule = 'reedcrm';
        $saturneSubdir = 'calllistline/' . (int) $line->id;

        if ($line->element_type === 'propal' && isModEnabled('propale')) {
            require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
            $propal = new Propal($db);
            if ($propal->fetch($line->element_id) > 0) {
                $sourceRefHtml = $propal->getNomUrl(1);
                $saturneModule = 'propal';
                $saturneSubdir = dol_sanitizeFileName($propal->ref);
                if ($propal->fk_project > 0) {
                    require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
                    $project = new Project($db);
                    if ($project->fetch($propal->fk_project) > 0) {
                        $sourceTitleHtml = $project->title;
                        $oppPercent = $project->opp_percent;
                    }
                }
                $oppAmount = $propal->total_ttc;
            }
        } elseif ($line->element_type === 'project' && isModEnabled('projet')) {
            require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
            $project = new Project($db);
            if ($project->fetch($line->element_id) > 0) {
                $sourceRefHtml = $project->getNomUrl(1);
                $saturneModule = 'projet';
                $saturneSubdir = dol_sanitizeFileName($project->ref);
                $sourceTitleHtml = $project->title;
                $oppPercent = $project->opp_percent;
                $oppAmount  = $project->opp_amount;
                
                if (empty($line->fk_contact) || empty($lastname)) {
                    $project->fetch_optionals();
                    if (!empty($project->array_options['options_projectaddress'])) {
                        $contact->fetch($project->array_options['options_projectaddress']);
                        $lastname  = dol_escape_htmltag($contact->lastname);
                        $firstname = dol_escape_htmltag($contact->firstname);
                        $phone     = dol_escape_htmltag($contact->phone_pro ?: $contact->phone_mobile ?: '');
                    } elseif (!empty($project->array_options['options_reedcrm_lastname']) || !empty($project->array_options['options_projectphone'])) {
                        $lastname  = dol_escape_htmltag($project->array_options['options_reedcrm_lastname'] ?? '');
                        $firstname = dol_escape_htmltag($project->array_options['options_reedcrm_firstname'] ?? '');
                        $phone     = dol_escape_htmltag($project->array_options['options_projectphone'] ?? '');
                    } elseif ($project->socid > 0) {
                        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
                        $soc = new Societe($db);
                        $soc->fetch($project->socid);
                        $lastname = dol_escape_htmltag($soc->name);
                        $phone    = dol_escape_htmltag($soc->phone);
                    }
                }
            }
        }

        $currentStatus = (int) $line->status;

        print '<div class="pwa-call-card" data-line-id="' . (int) $line->id . '" data-status="' . $currentStatus . '">';

        // Ligne 1 : Nom et % opp
        print '<div class="pwa-call-card-header">';
        if ($lastname || $firstname) {
            print '<span class="pwa-call-name"><i class="fas fa-user" style="color:#94a3b8;"></i> ' . $lastname . ' ' . $firstname . '</span>';
        } else {
            print '<span class="pwa-call-name pwa-call-name--empty"><i class="fas fa-user-slash" style="color:#cbd5e1;"></i> Contact non renseigné</span>';
        }
        if ($oppPercent !== null) {
            print '<span class="pwa-call-badge"><i class="fas fa-chart-pie" style="color:#64748b;margin-right:4px;"></i>' . round($oppPercent) . ' %</span>';
        }
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

        // Ligne 3 : Picto + Objet + Montant
        if ($sourceRefHtml) {
            $amountStr = ($oppAmount !== null) ? price($oppAmount, 0, $langs, 0, 0, -1, $conf->currency) : '';
            print '<div class="pwa-call-ref">' . $sourceRefHtml;
            if ($amountStr) {
                print ' <span style="margin:0 6px;">|</span> <strong style="color:#1e293b;">' . $amountStr . '</strong>';
            }
            print '</div>';
            
            if ($sourceTitleHtml) {
                print '<div class="pwa-call-title">' . dol_escape_htmltag($sourceTitleHtml) . '</div>';
            } else {
                print '<div style="margin-bottom:12px;"></div>';
            }
        }

        // Ligne 4 : Actions
        print '<div class="pwa-call-actions">';
        print '<div class="pwa-call-status-btns">';
        
        $statusIcons = [
            CallListLine::STATUS_CALLED    => 'fas fa-check',
            CallListLine::STATUS_NO_ANSWER => 'fas fa-times',
            CallListLine::STATUS_CALLBACK  => 'fas fa-voicemail',
        ];
        
        foreach ($statusIcons as $val => $iconClass) {
            $isActive = ($val === $currentStatus) ? ' pwa-status-btn--active' : '';
            $color    = $statusColors[$val] ?? '#94a3b8';
            print '<button class="pwa-status-btn' . $isActive . '" data-status="' . (int) $val . '" style="--status-color:' . dol_escape_htmltag($color) . '" title="' . dol_escape_htmltag($statusLabels[$val]) . '"><i class="' . $iconClass . '"></i></button>';
        }
        print '</div>';

        print saturne_render_media_block($saturneModule, $saturneSubdir, 'cll_' . (int) $line->id, '', ['show_photo' => false, 'show_audio' => true, 'show_gallery' => false]);
        print '</div>';

        print '<div class="pwa-call-error" style="display:none;"></div>';

        print '</div>';
    }
}

print '</div>';

print '<script>
(function () {
    var container  = document.querySelector(".pwa-call-list-container");
    if (!container) return;
    var ajaxUrl          = container.dataset.ajaxUrl;
    var actioncommUrl    = container.dataset.actioncommUrl;
    var createActioncomm = container.dataset.createActioncomm === "1";
    var token            = container.dataset.token;
    var statusLabels = ' . json_encode($statusLabels) . ';
    var statusColors = ' . json_encode($statusColors) . ';

    window.saturne = window.saturne || {};
    window.saturne.toolbox = window.saturne.toolbox || {};
    window.saturne.toolbox.getToken = function() {
        return token;
    };

    document.querySelectorAll(".pwa-call-btn-call").forEach(function (link) {
        link.addEventListener("click", function () {
            if (!createActioncomm) return;
            var card   = link.closest(".pwa-call-card");
            var lineId = card.dataset.lineId;
            var body   = new URLSearchParams();
            body.append("line_id", lineId);
            body.append("token",   token);
            fetch(actioncommUrl, { method: "POST", body: body });
        });
    });

    document.querySelectorAll(".pwa-status-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var card      = btn.closest(".pwa-call-card");
            var lineId    = card.dataset.lineId;
            var newStatus = parseInt(btn.dataset.status, 10);
            var currentSt = parseInt(card.dataset.status, 10);
            if (currentSt === newStatus) {
                newStatus = 0; // Toggle off if clicking the already active button
            }
            var errorEl   = card.querySelector(".pwa-call-error");

            var body = new URLSearchParams();
            body.append("line_id", lineId);
            body.append("status",  newStatus);
            body.append("token",   token);

            fetch(ajaxUrl, { method: "POST", body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                        if (data.success) {
                            card.querySelectorAll(".pwa-status-btn").forEach(function (b) {
                                b.classList.toggle("pwa-status-btn--active", parseInt(b.dataset.status, 10) === newStatus);
                            });
                        card.dataset.status   = newStatus;
                        errorEl.style.display = "none";
                        if (newStatus !== 0) {
                            card.parentNode.appendChild(card);
                        }
                    } else {
                        errorEl.textContent   = data.error || "Erreur inconnue";
                        errorEl.style.display = "block";
                        setTimeout(function () { errorEl.style.display = "none"; }, 3000);
                    }
                })
                .catch(function () {
                    errorEl.textContent   = "Erreur réseau";
                    errorEl.style.display = "block";
                    setTimeout(function () { errorEl.style.display = "none"; }, 3000);
                });
        });
    });
}());
</script>';

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

llxFooter();
$db->close();

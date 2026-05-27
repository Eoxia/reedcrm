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

$id = GETPOSTINT('id');

if (!$user->hasRight('reedcrm', 'call_list', 'read')) {
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

print '<style>
.pwa-call-list-container{padding:15px;padding-bottom:80px;}
.pwa-call-list-header{margin-bottom:20px;}
.pwa-call-list-header h2{font-size:1.4rem;margin:0 0 4px;color:#1e293b;}
.pwa-call-list-header p{margin:0;color:#64748b;font-size:.9rem;}
.pwa-call-list-empty{text-align:center;padding:60px 20px;color:#94a3b8;font-size:1.1rem;}
.pwa-call-list-empty i{font-size:3rem;display:block;margin-bottom:12px;}
.pwa-call-card{background:#fff;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,.08);padding:16px;margin-bottom:16px;}
.pwa-call-card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;gap:8px;}
.pwa-call-name{font-size:1.3rem;font-weight:bold;color:#1e293b;}
.pwa-call-name--empty{color:#94a3b8;font-style:italic;}
.pwa-call-badge{font-size:.75rem;font-weight:600;color:#fff;padding:3px 10px;border-radius:20px;white-space:nowrap;flex-shrink:0;}
.pwa-call-source{font-size:.85rem;color:#64748b;margin-bottom:12px;}
.pwa-call-actions{display:flex;align-items:stretch;gap:8px;margin:12px 0;}
.pwa-call-btn-call{flex:1;padding:14px;text-align:center;background:#22c55e;color:#fff !important;font-size:1.2rem;font-weight:bold;border-radius:10px;text-decoration:none !important;box-sizing:border-box;display:flex;align-items:center;justify-content:center;}
.pwa-call-btn-copy{flex-shrink:0;width:52px;padding:0;text-align:center;background:#3b82f6;color:#fff !important;font-size:1rem;font-weight:bold;border-radius:10px;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;}
.pwa-call-btn-copy--ok{background:#22c55e;}
.pwa-call-phone--empty{color:#94a3b8;font-size:1rem;margin:12px 0;}
.pwa-call-actions [id$="master-media-row-container-audio"]{padding:0;display:flex;align-items:stretch;}
.pwa-call-actions .saturne-audio-controls{margin-top:0;gap:8px;align-items:stretch;}
.pwa-call-actions .saturne-play-recording-wrapper{display:flex;align-items:stretch;}
.pwa-call-actions .saturne-media-btn{width:52px;min-width:52px;height:auto;min-height:0;border-radius:10px;}
.pwa-call-status-btns{display:flex;gap:8px;flex-wrap:wrap;}
.pwa-status-btn{flex:1;min-width:70px;padding:8px 4px;font-size:.75rem;border-radius:8px;border:2px solid var(--status-color);background:transparent;color:var(--status-color);cursor:pointer;font-weight:600;transition:background .15s,color .15s;}
.pwa-status-btn--active{background:var(--status-color);color:#fff;}
.pwa-call-error{background:#fef2f2;border:1px solid #fca5a5;border-radius:8px;padding:8px 12px;margin-top:8px;color:#dc2626;font-size:.85rem;}
</style>';

$ajaxUrl          = dol_buildpath('/custom/reedcrm/ajax/update_call_list_line_status.php', 1);
$actioncommAjaxUrl = dol_buildpath('/custom/reedcrm/ajax/create_call_actioncomm.php', 1);
$createActioncomm  = getDolGlobalInt('REEDCRM_CALL_LIST_CREATE_ACTIONCOMM');
$token             = newToken();

print '<div class="pwa-call-list-container"'
    . ' data-ajax-url="' . dol_escape_htmltag($ajaxUrl) . '"'
    . ' data-actioncomm-url="' . dol_escape_htmltag($actioncommAjaxUrl) . '"'
    . ' data-create-actioncomm="' . (int) $createActioncomm . '"'
    . ' data-token="' . dol_escape_htmltag($token) . '">';

$dateStart = $object->date_start ? dol_print_date($object->date_start, 'day') : '—';
$dateEnd   = $object->date_end   ? dol_print_date($object->date_end,   'day') : '—';
print '<div class="pwa-call-list-header">';
print '<h2>' . dol_escape_htmltag($object->label) . '</h2>';
print '<p>' . $dateStart . ' → ' . $dateEnd . '</p>';
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

        if ($line->element_type === 'propal' && isModEnabled('propale')) {
            require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';
            $propal = new Propal($db);
            if ($propal->fetch($line->element_id) > 0) {
                $sourceHtml = $propal->getNomUrl(1);
            }
        } elseif ($line->element_type === 'project' && isModEnabled('projet')) {
            require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
            $project = new Project($db);
            if ($project->fetch($line->element_id) > 0) {
                $sourceHtml = $project->getNomUrl(1);
            }
        }

        $currentStatus = (int) $line->status;
        $badgeColor    = $statusColors[$currentStatus] ?? '#94a3b8';
        $badgeLabel    = $statusLabels[$currentStatus] ?? '?';

        print '<div class="pwa-call-card" data-line-id="' . (int) $line->id . '" data-status="' . $currentStatus . '">';

        print '<div class="pwa-call-card-header">';
        if ($lastname || $firstname) {
            print '<span class="pwa-call-name">' . $lastname . ' ' . $firstname . '</span>';
        } else {
            print '<span class="pwa-call-name pwa-call-name--empty">Contact non renseigné</span>';
        }
        print '<span class="pwa-call-badge" style="background:' . dol_escape_htmltag($badgeColor) . '">● ' . dol_escape_htmltag($badgeLabel) . '</span>';
        print '</div>';

        if ($sourceHtml) {
            print '<div class="pwa-call-source">' . $sourceHtml . '</div>';
        }

        if ($phone) {
            print '<div class="pwa-call-actions">';
            print '<a class="pwa-call-btn-call" href="tel:' . dol_escape_htmltag($phone) . '"><i class="fas fa-phone"></i> ' . $phone . '</a>';
            print '<button class="pwa-call-btn-copy" data-phone="' . dol_escape_htmltag($phone) . '" title="Copier le numéro"><i class="fas fa-copy"></i></button>';
            print saturne_render_media_block('reedcrm', 'calllistline_' . (int) $line->id, 'cll_' . (int) $line->id, '', ['show_photo' => false, 'show_audio' => true, 'show_gallery' => true]);
            print '</div>';
        } else {
            print '<div class="pwa-call-phone--empty"><i class="fas fa-phone-slash"></i> Pas de téléphone</div>';
        }

        print '<div class="pwa-call-status-btns">';
        foreach ($statusLabels as $val => $lbl) {
            $isActive = ($val === $currentStatus) ? ' pwa-status-btn--active' : '';
            $color    = $statusColors[$val];
            print '<button class="pwa-status-btn' . $isActive . '" data-status="' . (int) $val . '" style="--status-color:' . dol_escape_htmltag($color) . '">' . dol_escape_htmltag($lbl) . '</button>';
        }
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

    document.querySelectorAll(".pwa-call-btn-copy").forEach(function (btn) {
        btn.addEventListener("click", function () {
            navigator.clipboard.writeText(btn.dataset.phone).then(function () {
                btn.classList.add("pwa-call-btn-copy--ok");
                btn.innerHTML = "<i class=\"fas fa-check\"></i>";
                setTimeout(function () {
                    btn.classList.remove("pwa-call-btn-copy--ok");
                    btn.innerHTML = "<i class=\"fas fa-copy\"></i>";
                }, 1500);
            });
        });
    });

    document.querySelectorAll(".pwa-status-btn").forEach(function (btn) {
        btn.addEventListener("click", function () {
            var card      = btn.closest(".pwa-call-card");
            var lineId    = card.dataset.lineId;
            var newStatus = parseInt(btn.dataset.status, 10);
            var errorEl   = card.querySelector(".pwa-call-error");

            var body = new URLSearchParams();
            body.append("line_id", lineId);
            body.append("status",  newStatus);
            body.append("token",   token);

            fetch(ajaxUrl, { method: "POST", body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        var badge = card.querySelector(".pwa-call-badge");
                        badge.textContent      = "● " + statusLabels[newStatus];
                        badge.style.background = statusColors[newStatus];
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

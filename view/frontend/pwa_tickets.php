<?php
/**
 * \file    view/frontend/pwa_tickets.php
 * \ingroup reedcrm
 * \brief   Page to list Tickets in Kanban view on frontend App
 */

if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

global $conf, $db, $hookmanager, $langs, $user;

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

saturne_load_langs(['ticket', 'users', 'companies', 'main']);

$title    = $langs->trans('Tickets');
$help_url = 'FR:Module_ReedCRM';
$moreJS   = [
    '/custom/saturne/js/saturne.min.js',
    '/custom/reedcrm/js/reedcrm.min.js',
    '/custom/reedcrm/js/reedcrm_tickets_kanban.js'
];
$moreCSS  = [
    '/custom/saturne/css/saturne.min.css',
    '/custom/reedcrm/css/reedcrm.min.css',
    '/custom/reedcrm/css/reedcrm_tickets_kanban.css'
];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;
$conf->global->MAIN_FAVICON_URL = DOL_URL_ROOT . '/custom/reedcrm/img/reedcrm_color_512.png';

// --- ACTIONS (AJAX status change via drag & drop) ---
$action = GETPOST('action', 'aZ09');
if ($action == 'updateticketstatus') {
    header('Content-Type: application/json; charset=utf-8');
    $ticketId  = GETPOST('ticketid', 'int');
    $newStatus = GETPOST('newstatus', 'int');
    $res = ['success' => false];

    if ($ticketId > 0 && in_array($newStatus, [
        Ticket::STATUS_NOT_READ,      // 0
        Ticket::STATUS_READ,          // 1
        Ticket::STATUS_ASSIGNED,      // 2
        Ticket::STATUS_IN_PROGRESS,   // 3
        Ticket::STATUS_NEED_MORE_INFO,// 5
        Ticket::STATUS_WAITING,       // 7
        Ticket::STATUS_CLOSED,        // 8
        Ticket::STATUS_CANCELED,      // 9
    ])) {
        $ticket = new Ticket($db);
        $fetchRes = $ticket->fetch($ticketId);
        if ($fetchRes > 0 && $ticket->id > 0) {
            // Use close() for closing statuses, setStatut() for others
            if ($newStatus == Ticket::STATUS_CLOSED) {
                $result = $ticket->close($user, 0); // mode 0 = solved
            } elseif ($newStatus == Ticket::STATUS_CANCELED) {
                $result = $ticket->close($user, 1); // mode 1 = canceled
            } else {
                $result = $ticket->setStatut($newStatus, null, '', 'TICKET_MODIFY');
            }
            if ($result >= 0) {
                $res['success'] = true;
                $res['new_status'] = $newStatus;
            } else {
                $res['error'] = $ticket->error ?: 'Update failed';
            }
        } else {
            $res['error'] = 'Ticket not found (id=' . $ticketId . ')';
        }
    } else {
        $res['error'] = 'Invalid parameters (id=' . $ticketId . ', status=' . $newStatus . ')';
    }
    echo json_encode($res);
    exit;
}

// --- PERMISSION CHECK ---
llxHeader('', $title, $help_url, '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-tickets-list');

if (!$user->hasRight('ticket', 'read')) {
    accessforbidden($langs->trans('NotEnoughPermissions'), 0);
    exit;
}

// --- FETCH ALL TICKETS ---
$sql  = "SELECT t.rowid, t.ref, t.track_id, t.subject, t.message, t.fk_statut as status,";
$sql .= " t.fk_soc, t.fk_user_assign, t.fk_user_create, t.datec, t.date_read, t.date_close,";
$sql .= " t.severity_code, t.type_code, t.category_code, t.progress,";
$sql .= " s.nom as company_name";
$sql .= " FROM " . MAIN_DB_PREFIX . "ticket as t";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON s.rowid = t.fk_soc";
$sql .= " WHERE t.entity IN (" . getEntity('ticket') . ")";
$sql .= " ORDER BY t.datec DESC";

$tickets = [];
$assignees = [];
$resql = $db->query($sql);
if ($resql) {
    while ($obj = $db->fetch_object($resql)) {
        $tickets[] = $obj;
        if (!empty($obj->fk_user_assign) && $obj->fk_user_assign > 0) {
            $assignees[$obj->fk_user_assign] = true;
        }
    }
    $db->free($resql);
}
$totalTickets = count($tickets);

// --- FETCH ASSIGNEE USER DATA ---
$usersData = [];
if (!empty($assignees)) {
    $userIds = array_keys($assignees);
    $sqlU  = "SELECT u.rowid, u.firstname, u.lastname, u.login, u.photo";
    $sqlU .= " FROM " . MAIN_DB_PREFIX . "user as u";
    $sqlU .= " WHERE u.rowid IN (" . implode(',', array_map('intval', $userIds)) . ")";
    $resU = $db->query($sqlU);
    if ($resU) {
        while ($uObj = $db->fetch_object($resU)) {
            $usersData[$uObj->rowid] = $uObj;
        }
        $db->free($resU);
    }
}

// --- Status Map (Dolibarr 23 Ticket constants) ---
$statusMap = [
    Ticket::STATUS_NOT_READ       => ['label' => 'Non lu',         'color' => '#e74c3c'],
    Ticket::STATUS_READ           => ['label' => 'Lu',             'color' => '#f39c12'],
    Ticket::STATUS_ASSIGNED       => ['label' => 'Assigné',        'color' => '#f1c40f'],
    Ticket::STATUS_IN_PROGRESS    => ['label' => 'En cours',       'color' => '#3498db'],
    Ticket::STATUS_NEED_MORE_INFO => ['label' => "Besoin d'info",  'color' => '#e67e22'],
    Ticket::STATUS_WAITING        => ['label' => 'En attente',     'color' => '#9b59b6'],
    Ticket::STATUS_CLOSED         => ['label' => 'Fermé',          'color' => '#2ecc71'],
    Ticket::STATUS_CANCELED       => ['label' => 'Annulé',         'color' => '#7f8c8d'],
];

// --- Severity Map ---
$severityMap = [
    'LOW'      => 'Basse',
    'NORMAL'   => 'Normale',
    'HIGH'     => 'Haute',
    'BLOCKING' => 'Bloquante',
];

// Group tickets by status
$ticketsByStatus = [];
foreach (array_keys($statusMap) as $st) {
    $ticketsByStatus[$st] = [];
}
foreach ($tickets as $t) {
    $st = (int)$t->status;
    if (!isset($ticketsByStatus[$st])) {
        $ticketsByStatus[$st] = [];
    }
    $ticketsByStatus[$st][] = $t;
}

// --- Expose CSRF token for AJAX ---
$csrfToken = newToken();

// --- SEARCH BAR + COUNTER + VIEW TOGGLE FOR HEADER ---
$pwaHeaderCenterHtml  = '<div style="display: flex; align-items: center; width: 100%; justify-content: center; gap: 8px;">';
$pwaHeaderCenterHtml .= '  <div style="white-space: nowrap; background: #e2e8f0; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: bold; color: #475569;"><i class="fas fa-ticket-alt"></i> ' . $totalTickets . '</div>';

// View toggle: Kanban / List
$pwaHeaderCenterHtml .= '  <div class="kanban-view-toggle" id="kanban-view-toggle">';
$pwaHeaderCenterHtml .= '    <button class="view-toggle-btn active" data-view="kanban" title="Vue Kanban"><i class="fas fa-columns"></i></button>';
$pwaHeaderCenterHtml .= '    <button class="view-toggle-btn" data-view="list" title="Vue Liste"><i class="fas fa-list"></i></button>';
$pwaHeaderCenterHtml .= '  </div>';

$pwaHeaderCenterHtml .= '  <div id="kanban-search-bar" style="display: flex; gap: 6px; flex: 1; max-width: 400px;">';
$pwaHeaderCenterHtml .= '    <div style="position: relative; flex: 1; min-width: 40px;">';
$pwaHeaderCenterHtml .= '      <i class="fas fa-search" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 0.85em;"></i>';
$pwaHeaderCenterHtml .= '      <input type="text" id="kanban-search-input" placeholder="Rechercher..." style="width: 100%; padding: 6px 10px 6px 28px; border: 1px solid #cbd5e0; border-radius: 20px; font-size: 14px; outline: none; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); transition: border-color 0.2s; box-sizing: border-box;">';
$pwaHeaderCenterHtml .= '    </div>';
$pwaHeaderCenterHtml .= '  </div>';
$pwaHeaderCenterHtml .= '</div>';

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

// Output CSRF token for JS
print '<input type="hidden" name="token" value="' . $csrfToken . '">';

// --- KANBAN TEMPLATE ---
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_tickets_kanban.tpl.php';

// --- BOTTOM NAV ---
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

llxFooter();
$db->close();

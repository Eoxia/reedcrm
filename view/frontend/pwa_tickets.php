<?php
/**
 * \file    view/frontend/pwa_tickets.php
 * \ingroup reedcrm
 * \brief   Page to list Tickets in Kanban + Notion table view on frontend PWA
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
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

saturne_load_langs(['ticket', 'users', 'companies', 'main', 'categories']);

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

// --- SERVER-SIDE SORT ---
$sortfield = GETPOST('sortfield', 'aZ09') ?: 'datec';
$sortorder = GETPOST('sortorder', 'aZ') ?: 'DESC';
$allowedSort = ['ref', 'datec', 'subject', 'severity_code', 'fk_statut', 'fk_user_assign'];
if (!in_array($sortfield, $allowedSort)) {
    $sortfield = 'datec';
}
if (!in_array(strtoupper($sortorder), ['ASC', 'DESC'])) {
    $sortorder = 'DESC';
}

// --- COLUMN SEARCH PARAMS ---
$s_ref       = trim(GETPOST('s_ref', 'alpha'));
$s_subject   = trim(GETPOST('s_subject', 'alpha'));
$s_severity  = trim(GETPOST('s_severity', 'alpha'));
$s_author    = trim(GETPOST('s_author', 'alpha'));
$s_assignee  = GETPOST('s_assignee', 'int');
$s_status    = GETPOST('s_status', 'int');
$s_tags      = trim(GETPOST('s_tags', 'alpha'));
$viewmode    = GETPOST('viewmode', 'alpha') ?: 'table';

// --- ACTIONS (AJAX) ---
$action = GETPOST('action', 'aZ09');

// AJAX: Update a ticket field
if ($action == 'updateticketfield') {
    header('Content-Type: application/json; charset=utf-8');
    $ticketId = GETPOST('ticketid', 'int');
    $field    = GETPOST('field', 'aZ09');
    $value    = GETPOST('value', 'alphanohtml');
    $res = ['success' => false];

    if ($ticketId > 0 && !empty($field)) {
        $ticket = new Ticket($db);
        $fetchRes = $ticket->fetch($ticketId);
        if ($fetchRes > 0 && $ticket->id > 0) {
            switch ($field) {
                case 'subject':
                    $ticket->subject = $value;
                    $result = $ticket->update($user);
                    $res['success'] = ($result >= 0);
                    if ($result < 0) $res['error'] = $ticket->error;
                    break;

                case 'severity':
                    $ticket->severity_code = $value;
                    $result = $ticket->update($user);
                    $res['success'] = ($result >= 0);
                    if ($result < 0) $res['error'] = $ticket->error;
                    break;

                case 'status':
                    $newStatus = (int)$value;
                    if ($newStatus == Ticket::STATUS_CLOSED) {
                        $result = $ticket->close($user, 0);
                    } elseif ($newStatus == Ticket::STATUS_CANCELED) {
                        $result = $ticket->close($user, 1);
                    } else {
                        $result = $ticket->setStatut($newStatus, null, '', 'TICKET_MODIFY');
                    }
                    $res['success'] = ($result >= 0);
                    if ($result < 0) $res['error'] = $ticket->error;
                    break;

                case 'assignee':
                    $assigneeId = (int)$value;
                    $result = $ticket->assignUser($user, $assigneeId > 0 ? $assigneeId : 0);
                    $res['success'] = ($result > 0);
                    if ($result <= 0) $res['error'] = $ticket->error;
                    // Return new status
                    $ticket->fetch($ticketId);
                    $res['new_status'] = (int)$ticket->fk_statut;
                    break;

                case 'tags':
                    // value = comma-separated category IDs
                    $catIds = array_filter(array_map('intval', explode(',', $value)));
                    // Remove all existing ticket categories
                    $cat = new Categorie($db);
                    $existingCats = $cat->getListForItem($ticketId, 'ticket');
                    if (is_array($existingCats)) {
                        foreach ($existingCats as $existCat) {
                            $catToDel = new Categorie($db);
                            $catToDel->fetch($existCat['id']);
                            $catToDel->del_type($ticket, 'ticket');
                        }
                    }
                    // Add new categories
                    foreach ($catIds as $catId) {
                        $catToAdd = new Categorie($db);
                        if ($catToAdd->fetch($catId) > 0) {
                            $catToAdd->add_type($ticket, 'ticket');
                        }
                    }
                    $res['success'] = true;
                    break;

                default:
                    $res['error'] = 'Unknown field: ' . $field;
            }
        } else {
            $res['error'] = 'Ticket not found';
        }
    } else {
        $res['error'] = 'Invalid parameters';
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

// --- FETCH DICTIONARIES ---
// Severities
$severities = [];
$resSev = $db->query("SELECT code, label FROM " . MAIN_DB_PREFIX . "c_ticket_severity WHERE active = 1 ORDER BY pos");
if ($resSev) {
    while ($obj = $db->fetch_object($resSev)) {
        $severities[$obj->code] = $langs->trans($obj->label);
    }
    $db->free($resSev);
}

// Types
$ticketTypes = [];
$resType = $db->query("SELECT code, label FROM " . MAIN_DB_PREFIX . "c_ticket_type WHERE active = 1 ORDER BY pos");
if ($resType) {
    while ($obj = $db->fetch_object($resType)) {
        $ticketTypes[$obj->code] = $langs->trans($obj->label);
    }
    $db->free($resType);
}

// All available tag categories for tickets
$allTagCategories = [];
$resCat = $db->query("SELECT c.rowid, c.label, c.color FROM " . MAIN_DB_PREFIX . "categorie c WHERE c.type = " . Categorie::TYPE_TICKET . " AND c.entity IN (" . getEntity('categorie') . ") ORDER BY c.label");
if ($resCat) {
    while ($obj = $db->fetch_object($resCat)) {
        $allTagCategories[$obj->rowid] = ['label' => $obj->label, 'color' => $obj->color ?: 'cccccc'];
    }
    $db->free($resCat);
}

// --- FETCH ALL INTERNAL USERS ---
$allInternalUsers = [];
$sqlU  = "SELECT u.rowid, u.firstname, u.lastname, u.login, u.photo";
$sqlU .= " FROM " . MAIN_DB_PREFIX . "user as u";
$sqlU .= " WHERE u.entity IN (" . getEntity('user') . ") AND u.statut = 1";
$sqlU .= " ORDER BY u.firstname ASC, u.lastname ASC";
$resU = $db->query($sqlU);
if ($resU) {
    while ($uObj = $db->fetch_object($resU)) {
        $allInternalUsers[$uObj->rowid] = $uObj;
    }
    $db->free($resU);
}

// --- FETCH ALL TICKETS ---
$sql  = "SELECT DISTINCT t.rowid, t.ref, t.track_id, t.subject, t.message, t.fk_statut as status,";
$sql .= " t.fk_soc, t.fk_user_assign, t.fk_user_create, t.datec, t.date_read, t.date_close,";
$sql .= " t.severity_code, t.type_code, t.category_code, t.progress,";
$sql .= " s.nom as company_name,";
$sql .= " ua.firstname as assign_firstname, ua.lastname as assign_lastname, ua.login as assign_login, ua.photo as assign_photo,";
$sql .= " uc.firstname as author_firstname, uc.lastname as author_lastname, uc.login as author_login, uc.photo as author_photo";
$sql .= " FROM " . MAIN_DB_PREFIX . "ticket as t";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON s.rowid = t.fk_soc";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as ua ON ua.rowid = t.fk_user_assign";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user as uc ON uc.rowid = t.fk_user_create";

// Join for tag search
if (!empty($s_tags)) {
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie_ticket as ct ON ct.fk_ticket = t.rowid";
    $sql .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie as catf ON catf.rowid = ct.fk_categorie";
}

$sql .= " WHERE t.entity IN (" . getEntity('ticket') . ")";

// Column filters
if (!empty($s_ref)) {
    $sql .= " AND t.ref LIKE '%" . $db->escape($s_ref) . "%'";
}
if (!empty($s_subject)) {
    $sql .= " AND t.subject LIKE '%" . $db->escape($s_subject) . "%'";
}
if (!empty($s_severity)) {
    $sql .= " AND t.severity_code = '" . $db->escape($s_severity) . "'";
}
if (!empty($s_author)) {
    $sql .= " AND (uc.firstname LIKE '%" . $db->escape($s_author) . "%' OR uc.lastname LIKE '%" . $db->escape($s_author) . "%')";
}
if ($s_assignee !== '' && $s_assignee > 0) {
    $sql .= " AND t.fk_user_assign = " . (int)$s_assignee;
} elseif ($s_assignee === '0') {
    $sql .= " AND (t.fk_user_assign IS NULL OR t.fk_user_assign = 0)";
}
if ($s_status !== '' && $s_status >= 0) {
    $sql .= " AND t.fk_statut = " . (int)$s_status;
}
if (!empty($s_tags)) {
    $sql .= " AND catf.label LIKE '%" . $db->escape($s_tags) . "%'";
}

$sql .= " ORDER BY t." . $sortfield . " " . $sortorder;

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

// Fetch tags for each ticket
$ticketTags = [];
if (!empty($tickets)) {
    $ticketIds = array_map(function($t) { return (int)$t->rowid; }, $tickets);
    $sqlTags  = "SELECT ct.fk_ticket, c.rowid as cat_id, c.label, c.color";
    $sqlTags .= " FROM " . MAIN_DB_PREFIX . "categorie_ticket as ct";
    $sqlTags .= " INNER JOIN " . MAIN_DB_PREFIX . "categorie as c ON c.rowid = ct.fk_categorie";
    $sqlTags .= " WHERE ct.fk_ticket IN (" . implode(',', $ticketIds) . ")";
    $sqlTags .= " ORDER BY c.label";
    $resTags = $db->query($sqlTags);
    if ($resTags) {
        while ($tagObj = $db->fetch_object($resTags)) {
            $ticketTags[(int)$tagObj->fk_ticket][] = [
                'id'    => (int)$tagObj->cat_id,
                'label' => $tagObj->label,
                'color' => $tagObj->color ?: 'cccccc'
            ];
        }
        $db->free($resTags);
    }
}

// --- Status Map (Dolibarr 23) ---
$statusMap = [
    Ticket::STATUS_NOT_READ       => ['label' => $langs->trans('Unread') ?: 'Non lu',       'color' => '#e74c3c'],
    Ticket::STATUS_READ           => ['label' => $langs->trans('Read') ?: 'Lu',              'color' => '#f39c12'],
    Ticket::STATUS_ASSIGNED       => ['label' => $langs->trans('Assigned') ?: 'Assigné',     'color' => '#f1c40f'],
    Ticket::STATUS_IN_PROGRESS    => ['label' => $langs->trans('InProgress') ?: 'En cours',  'color' => '#3498db'],
    Ticket::STATUS_NEED_MORE_INFO => ['label' => $langs->trans('NeedMoreInformation') ?: "Besoin d'info", 'color' => '#e67e22'],
    Ticket::STATUS_WAITING        => ['label' => $langs->trans('OnHold') ?: 'En attente',    'color' => '#9b59b6'],
    Ticket::STATUS_CLOSED         => ['label' => $langs->trans('Closed') ?: 'Fermé',         'color' => '#2ecc71'],
    Ticket::STATUS_CANCELED       => ['label' => $langs->trans('Canceled') ?: 'Annulé',      'color' => '#7f8c8d'],
];

$severityMap = [
    'LOW'      => $severities['LOW'] ?? 'Basse',
    'NORMAL'   => $severities['NORMAL'] ?? 'Normale',
    'HIGH'     => $severities['HIGH'] ?? 'Haute',
    'BLOCKING' => $severities['BLOCKING'] ?? 'Bloquante',
];

// Group tickets by status (for Kanban view)
$ticketsByStatus = [];
foreach (array_keys($statusMap) as $st) {
    $ticketsByStatus[$st] = [];
}
foreach ($tickets as $t) {
    $st = (int)$t->status;
    if (!isset($ticketsByStatus[$st])) $ticketsByStatus[$st] = [];
    $ticketsByStatus[$st][] = $t;
}

// UsersData for Kanban filter chips (only assigned users)
$usersData = [];
foreach ($allInternalUsers as $uid => $uObj) {
    if (isset($assignees[$uid])) {
        $usersData[$uid] = $uObj;
    }
}

// --- CSRF TOKEN ---
$csrfToken = newToken();

// --- HEADER ---
$pwaHeaderCenterHtml  = '<div style="display: flex; align-items: center; width: 100%; justify-content: center; gap: 8px;">';
$pwaHeaderCenterHtml .= '  <div style="white-space: nowrap; background: #e2e8f0; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: bold; color: #475569;"><i class="fas fa-ticket-alt"></i> ' . $totalTickets . '</div>';

// View toggle
$pwaHeaderCenterHtml .= '  <div class="kanban-view-toggle" id="kanban-view-toggle">';
$pwaHeaderCenterHtml .= '    <button class="view-toggle-btn' . ($viewmode == 'kanban' ? ' active' : '') . '" data-view="kanban" title="Vue Kanban"><i class="fas fa-columns"></i></button>';
$pwaHeaderCenterHtml .= '    <button class="view-toggle-btn' . ($viewmode == 'table' ? ' active' : '') . '" data-view="table" title="Vue Table"><i class="fas fa-list"></i></button>';
$pwaHeaderCenterHtml .= '  </div>';

$pwaHeaderCenterHtml .= '</div>';

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

// Hidden token
print '<input type="hidden" name="token" value="' . $csrfToken . '">';

// JSON data for JS
$usersJsonData = [];
foreach ($allInternalUsers as $uid => $uObj) {
    $fn = trim(($uObj->firstname ?? '') . ' ' . ($uObj->lastname ?? ''));
    if (empty($fn)) $fn = $uObj->login ?? 'User #' . $uid;
    $usersJsonData[] = [
        'id'       => (int)$uid,
        'name'     => $fn,
        'initials' => mb_strtoupper(mb_substr($uObj->firstname ?? '', 0, 1) . mb_substr($uObj->lastname ?? '', 0, 1)),
        'photo'    => (!empty($uObj->photo) && trim($uObj->photo) !== '') ? DOL_URL_ROOT . '/viewimage.php?modulepart=user&file=' . urlencode($uid . '/' . $uObj->photo) : '',
    ];
}

$tagsJsonData = [];
foreach ($allTagCategories as $catId => $catInfo) {
    $tagsJsonData[] = [
        'id'    => (int)$catId,
        'label' => $catInfo['label'],
        'color' => $catInfo['color'],
    ];
}

print '<script>';
print 'window.KANBAN_USERS = ' . json_encode($usersJsonData) . ';';
print 'window.KANBAN_TAGS = ' . json_encode($tagsJsonData) . ';';
print 'window.KANBAN_STATUSES = ' . json_encode(array_map(function($k, $v) { return ['code' => $k, 'label' => $v['label'], 'color' => $v['color']]; }, array_keys($statusMap), array_values($statusMap))) . ';';
print 'window.KANBAN_SEVERITIES = ' . json_encode(array_map(function($k, $v) { return ['code' => $k, 'label' => $v]; }, array_keys($severityMap), array_values($severityMap))) . ';';
print 'window.KANBAN_VIEW = ' . json_encode($viewmode) . ';';
print '</script>';

// --- TEMPLATE ---
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_tickets_kanban.tpl.php';

// --- BOTTOM NAV ---
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

llxFooter();
$db->close();

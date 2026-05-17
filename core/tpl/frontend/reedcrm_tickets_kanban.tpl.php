<?php
/**
 * \file    core/tpl/frontend/reedcrm_tickets_kanban.tpl.php
 * \ingroup reedcrm
 * \brief   Kanban board + Notion-like table template for PWA tickets
 *
 * Expects from parent:
 *   $tickets          array   All ticket objects
 *   $ticketsByStatus  array   Tickets grouped by status code
 *   $ticketTags       array   Tags per ticket [ticketId => [{id,label,color}]]
 *   $statusMap        array   Status definitions [code => [label, color]]
 *   $severityMap      array   Severity translations
 *   $allInternalUsers array   All active internal users
 *   $usersData        array   Users with assigned tickets
 *   $totalTickets     int     Total ticket count
 *   $sortfield        string  Current sort field
 *   $sortorder        string  Current sort direction
 *   $s_ref, $s_subject, $s_severity, $s_author, $s_assignee, $s_status, $s_tags  search params
 *   $viewmode         string  'kanban' or 'table'
 */

// --- Helper: build sort URL ---
$baseParams = [];
if (!empty($s_ref))      $baseParams['s_ref'] = $s_ref;
if (!empty($s_subject))  $baseParams['s_subject'] = $s_subject;
if (!empty($s_severity)) $baseParams['s_severity'] = $s_severity;
if (!empty($s_author))   $baseParams['s_author'] = $s_author;
if ($s_assignee !== '' && $s_assignee !== null) $baseParams['s_assignee'] = $s_assignee;
if ($s_status !== '' && $s_status !== null)     $baseParams['s_status'] = $s_status;
if (!empty($s_tags))     $baseParams['s_tags'] = $s_tags;
$baseParams['viewmode'] = 'table';
$baseParams['source'] = 'pwa';

/**
 * Build sort link for a column header
 * @param string $field   SQL field name
 * @param string $label   Display label
 * @param string $icon    FontAwesome icon class
 * @param string $currentSort  Current sort field
 * @param string $currentOrder Current sort direction
 * @param array  $baseParams   Base query params
 * @return string HTML
 */
function buildSortHeader($field, $label, $icon, $currentSort, $currentOrder, $baseParams) {
    $isActive = ($currentSort === $field);
    $nextOrder = ($isActive && $currentOrder === 'ASC') ? 'DESC' : 'ASC';
    $params = $baseParams;
    $params['sortfield'] = $field;
    $params['sortorder'] = $nextOrder;
    $url = '?' . http_build_query($params);

    $arrow = '';
    if ($isActive) {
        $arrow = ($currentOrder === 'ASC')
            ? ' <i class="fas fa-sort-up nt-sort-active"></i>'
            : ' <i class="fas fa-sort-down nt-sort-active"></i>';
    } else {
        $arrow = ' <i class="fas fa-sort nt-sort-inactive"></i>';
    }

    return '<a href="' . dol_escape_htmltag($url) . '" class="nt-header-link' . ($isActive ? ' nt-header-sorted' : '') . '" title="Trier par ' . dol_escape_htmltag($label) . '">'
         . '<i class="' . dol_escape_htmltag($icon) . ' nt-header-icon"></i> '
         . dol_escape_htmltag($label)
         . $arrow
         . '</a>';
}
?>

<!-- ============================================================== -->
<!-- NOTION TABLE VIEW                                               -->
<!-- ============================================================== -->
<div class="notion-table-wrapper" id="notion-table-wrapper" style="<?php echo $viewmode !== 'table' ? 'display:none;' : ''; ?>">

<!-- Search form -->
<form method="GET" action="" id="notion-search-form" class="notion-search-form">
    <input type="hidden" name="source" value="pwa">
    <input type="hidden" name="viewmode" value="table">
    <input type="hidden" name="sortfield" value="<?php echo dol_escape_htmltag($sortfield); ?>">
    <input type="hidden" name="sortorder" value="<?php echo dol_escape_htmltag($sortorder); ?>">

    <div class="nt-grid nt-header-row">
        <div class="nt-cell nt-cell-ref" data-col-index="0"><?php echo buildSortHeader('ref', 'Réf', 'fas fa-hashtag', $sortfield, $sortorder, $baseParams); ?></div>
        <div class="nt-cell nt-cell-author" data-col-index="1">
            <span class="nt-header-label"><i class="fas fa-user nt-header-icon"></i> Auteur</span>
        </div>
        <div class="nt-cell nt-cell-subject" data-col-index="2"><?php echo buildSortHeader('subject', 'Sujet', 'fas fa-font', $sortfield, $sortorder, $baseParams); ?></div>
        <div class="nt-cell nt-cell-message" data-col-index="3">
            <span class="nt-header-label"><i class="fas fa-comment-alt nt-header-icon"></i> Message</span>
        </div>
        <div class="nt-cell nt-cell-severity" data-col-index="4"><?php echo buildSortHeader('severity_code', 'Sévérité', 'fas fa-exclamation-triangle', $sortfield, $sortorder, $baseParams); ?></div>
        <div class="nt-cell nt-cell-date" data-col-index="5"><?php echo buildSortHeader('datec', 'Création', 'fas fa-calendar', $sortfield, $sortorder, $baseParams); ?></div>
        <div class="nt-cell nt-cell-assignee" data-col-index="6"><?php echo buildSortHeader('fk_user_assign', 'Assigné à', 'fas fa-user-check', $sortfield, $sortorder, $baseParams); ?></div>
        <div class="nt-cell nt-cell-status" data-col-index="7"><?php echo buildSortHeader('fk_statut', 'État', 'fas fa-circle', $sortfield, $sortorder, $baseParams); ?></div>
        <div class="nt-cell nt-cell-tags" data-col-index="8">
            <span class="nt-header-label"><i class="fas fa-tags nt-header-icon"></i> Tags</span>
        </div>
    </div>

    <!-- SEARCH ROW -->
    <div class="nt-grid nt-search-row">
        <div class="nt-cell nt-cell-ref" data-col-index="0">
            <input type="text" name="s_ref" value="<?php echo dol_escape_htmltag($s_ref); ?>" placeholder="Filtrer..." class="nt-search-input" autocomplete="off">
        </div>
        <div class="nt-cell nt-cell-author" data-col-index="1">
            <input type="text" name="s_author" value="<?php echo dol_escape_htmltag($s_author); ?>" placeholder="Filtrer..." class="nt-search-input" autocomplete="off">
        </div>
        <div class="nt-cell nt-cell-subject" data-col-index="2">
            <input type="text" name="s_subject" value="<?php echo dol_escape_htmltag($s_subject); ?>" placeholder="Filtrer..." class="nt-search-input" autocomplete="off">
        </div>
        <div class="nt-cell nt-cell-message" data-col-index="3"></div>
        <div class="nt-cell nt-cell-severity" data-col-index="4">
            <select name="s_severity" class="nt-search-select">
                <option value="">Tous</option>
                <?php foreach ($severityMap as $code => $label) { ?>
                <option value="<?php echo dol_escape_htmltag($code); ?>"<?php echo ($s_severity === $code) ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($label); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="nt-cell nt-cell-date" data-col-index="5"></div>
        <div class="nt-cell nt-cell-assignee" data-col-index="6">
            <select name="s_assignee" class="nt-search-select">
                <option value="">Tous</option>
                <option value="0"<?php echo ($s_assignee === '0') ? ' selected' : ''; ?>>Non assigné</option>
                <?php foreach ($allInternalUsers as $uid => $uObj) {
                    $uName = trim(($uObj->firstname ?? '') . ' ' . ($uObj->lastname ?? ''));
                    if (empty($uName)) $uName = $uObj->login;
                ?>
                <option value="<?php echo (int)$uid; ?>"<?php echo ($s_assignee == $uid && $s_assignee !== '') ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($uName); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="nt-cell nt-cell-status" data-col-index="7">
            <select name="s_status" class="nt-search-select">
                <option value="">Tous</option>
                <?php foreach ($statusMap as $stCode => $stInfo) { ?>
                <option value="<?php echo (int)$stCode; ?>"<?php echo ($s_status !== '' && (int)$s_status === (int)$stCode) ? ' selected' : ''; ?>><?php echo dol_escape_htmltag($stInfo['label']); ?></option>
                <?php } ?>
            </select>
        </div>
        <div class="nt-cell nt-cell-tags" data-col-index="8">
            <input type="text" name="s_tags" value="<?php echo dol_escape_htmltag($s_tags); ?>" placeholder="Filtrer..." class="nt-search-input" autocomplete="off">
        </div>
    </div>
</form>

<!-- DATA ROWS -->
<div class="nt-body" id="notion-table-body">
<?php
if (empty($tickets)) {
    echo '<div class="nt-empty"><i class="fas fa-inbox"></i> Aucun ticket trouvé</div>';
}
foreach ($tickets as $ticket) {
    $ref = $ticket->ref ?: $ticket->track_id ?: '#' . $ticket->rowid;
    $ticketUrl = DOL_URL_ROOT . '/ticket/card.php?id=' . $ticket->rowid;
    $status = (int)$ticket->status;
    $stInfo = $statusMap[$status] ?? ['label' => '?', 'color' => '#ccc'];
    $sevLabel = $severityMap[strtoupper($ticket->severity_code ?? '')] ?? ($ticket->severity_code ?: '-');
    $assigneeId = (int)($ticket->fk_user_assign ?: 0);

    // Author name
    $authorName = trim(($ticket->author_firstname ?? '') . ' ' . ($ticket->author_lastname ?? ''));
    if (empty($authorName)) $authorName = $ticket->author_login ?? '-';

    // Assignee name
    $assigneeName = '-';
    $assigneeInitials = '?';
    $assigneePhoto = '';
    if ($assigneeId > 0) {
        $assigneeName = trim(($ticket->assign_firstname ?? '') . ' ' . ($ticket->assign_lastname ?? ''));
        if (empty($assigneeName)) $assigneeName = $ticket->assign_login ?? '?';
        $assigneeInitials = mb_strtoupper(mb_substr($ticket->assign_firstname ?? '', 0, 1) . mb_substr($ticket->assign_lastname ?? '', 0, 1));
        if (!empty($ticket->assign_photo) && trim($ticket->assign_photo) !== '') {
            $assigneePhoto = DOL_URL_ROOT . '/viewimage.php?modulepart=user&file=' . urlencode($assigneeId . '/' . $ticket->assign_photo);
        }
    }

    // Date
    $dateFormatted = '';
    if (!empty($ticket->datec)) {
        $dateTs = $db->jdate($ticket->datec);
        $dateFormatted = dol_print_date($dateTs, 'day');
    }

    // Tags for this ticket
    $tTags = $ticketTags[(int)$ticket->rowid] ?? [];
    $tagIds = array_map(function($t) { return $t['id']; }, $tTags);
?>
<div class="nt-grid nt-data-row" data-ticket-id="<?php echo (int)$ticket->rowid; ?>" data-status="<?php echo $status; ?>" data-assignee="<?php echo $assigneeId; ?>" data-severity="<?php echo dol_escape_htmltag($ticket->severity_code ?? ''); ?>" data-tag-ids="<?php echo dol_escape_htmltag(implode(',', $tagIds)); ?>">

    <!-- Réf (link) -->
    <div class="nt-cell nt-cell-ref">
        <a href="<?php echo $ticketUrl; ?>" class="nt-ref-link"><?php echo dol_escape_htmltag($ref); ?></a>
    </div>

    <!-- Auteur (read-only) -->
    <div class="nt-cell nt-cell-author" title="<?php echo dol_escape_htmltag($authorName); ?>">
        <span class="nt-author-text"><?php echo dol_escape_htmltag($authorName); ?></span>
    </div>

    <!-- Sujet (editable text) -->
    <div class="nt-cell nt-cell-subject nt-editable" data-field="subject" data-edit-type="text" data-value="<?php echo dol_escape_htmltag($ticket->subject ?? ''); ?>">
        <span class="nt-cell-text"><?php echo dol_escape_htmltag($ticket->subject ?? 'Sans titre'); ?></span>
    </div>

    <!-- Message initial (read-only, truncated) -->
    <?php
        $rawMsg = strip_tags(str_replace(['<br>', '<br/>', '<br />'], ' ', $ticket->message ?? ''));
        $rawMsg = trim(preg_replace('/\s+/', ' ', $rawMsg));
        $msgPreview = mb_substr($rawMsg, 0, 120);
        if (mb_strlen($rawMsg) > 120) $msgPreview .= '…';
    ?>
    <div class="nt-cell nt-cell-message" title="<?php echo dol_escape_htmltag($rawMsg); ?>">
        <span class="nt-cell-text"><?php echo dol_escape_htmltag($msgPreview ?: '-'); ?></span>
    </div>

    <!-- Sévérité (editable select) -->
    <div class="nt-cell nt-cell-severity nt-editable" data-field="severity" data-edit-type="select" data-value="<?php echo dol_escape_htmltag($ticket->severity_code ?? 'NORMAL'); ?>">
        <span class="nt-severity-badge nt-severity-<?php echo strtolower($ticket->severity_code ?? 'normal'); ?>"><?php echo dol_escape_htmltag($sevLabel); ?></span>
    </div>

    <!-- Date création (read-only) -->
    <div class="nt-cell nt-cell-date">
        <span><?php echo dol_escape_htmltag($dateFormatted); ?></span>
    </div>

    <!-- Assigné à (editable user) -->
    <div class="nt-cell nt-cell-assignee nt-editable" data-field="assignee" data-edit-type="user" data-value="<?php echo $assigneeId; ?>">
        <?php if ($assigneeId > 0) { ?>
        <div class="nt-user-chip">
            <?php if ($assigneePhoto) { ?>
            <img src="<?php echo dol_escape_htmltag($assigneePhoto); ?>" class="nt-user-avatar" alt="">
            <?php } else { ?>
            <span class="nt-user-avatar nt-user-initials"><?php echo dol_escape_htmltag($assigneeInitials); ?></span>
            <?php } ?>
            <span class="nt-user-name"><?php echo dol_escape_htmltag($assigneeName); ?></span>
        </div>
        <?php } else { ?>
        <span class="nt-empty-value">-</span>
        <?php } ?>
    </div>

    <!-- État (editable select) -->
    <div class="nt-cell nt-cell-status nt-editable" data-field="status" data-edit-type="select" data-value="<?php echo $status; ?>">
        <span class="nt-status-badge" style="--st-color: <?php echo $stInfo['color']; ?>">
            <span class="nt-status-dot" style="background: <?php echo $stInfo['color']; ?>"></span>
            <?php echo dol_escape_htmltag($stInfo['label']); ?>
        </span>
    </div>

    <!-- Tags (editable multi-select) -->
    <div class="nt-cell nt-cell-tags nt-editable" data-field="tags" data-edit-type="tags" data-value="<?php echo dol_escape_htmltag(implode(',', $tagIds)); ?>">
        <?php if (!empty($tTags)) {
            foreach ($tTags as $tag) { ?>
            <span class="nt-tag-chip" style="--tag-color: #<?php echo dol_escape_htmltag($tag['color']); ?>" data-tag-id="<?php echo (int)$tag['id']; ?>"><?php echo dol_escape_htmltag($tag['label']); ?></span>
            <?php }
        } else { ?>
        <span class="nt-empty-value">-</span>
        <?php } ?>
    </div>

</div>
<?php } ?>
</div>
</div>

<!-- ============================================================== -->
<!-- KANBAN VIEW (existing)                                          -->
<!-- ============================================================== -->
<div id="kanban-view-section" style="<?php echo $viewmode !== 'kanban' ? 'display:none;' : ''; ?>">

<!-- Assignee filter bar -->
<div class="kanban-filters" id="kanban-filters">
    <div class="kanban-filter-chips">
        <button class="kanban-chip active" data-filter-user="all" title="Tous les tickets">
            <i class="fas fa-users"></i>
            <span>Tous</span>
            <span class="chip-count" id="chip-count-all"><?php echo $totalTickets; ?></span>
        </button>
        <?php
        $unassignedCount = 0;
        foreach ($ticketsByStatus as $stTickets) {
            foreach ($stTickets as $t) {
                if (empty($t->fk_user_assign) || $t->fk_user_assign <= 0) $unassignedCount++;
            }
        }
        ?>
        <button class="kanban-chip" data-filter-user="0" title="Non assigné">
            <span class="kanban-avatar kanban-avatar-placeholder"><i class="fas fa-question"></i></span>
            <span>Non assigné</span>
            <span class="chip-count" id="chip-count-0"><?php echo $unassignedCount; ?></span>
        </button>
        <?php foreach ($usersData as $uid => $uObj) {
            $initials = '?';
            if (!empty($uObj->firstname) && !empty($uObj->lastname)) {
                $initials = mb_strtoupper(mb_substr($uObj->firstname, 0, 1) . mb_substr($uObj->lastname, 0, 1));
            } elseif (!empty($uObj->login)) {
                $initials = mb_strtoupper(mb_substr($uObj->login, 0, 2));
            }
            $fullName = trim(($uObj->firstname ?? '') . ' ' . ($uObj->lastname ?? ''));
            if (empty($fullName)) $fullName = $uObj->login ?? 'User #' . $uid;
            $userCount = 0;
            foreach ($ticketsByStatus as $stTickets) {
                foreach ($stTickets as $t) {
                    if ((int)$t->fk_user_assign === (int)$uid) $userCount++;
                }
            }
        ?>
        <button class="kanban-chip" data-filter-user="<?php echo (int)$uid; ?>" title="<?php echo dol_escape_htmltag($fullName); ?>">
            <span class="kanban-avatar"><?php echo dol_escape_htmltag($initials); ?></span>
            <span><?php echo dol_escape_htmltag($fullName); ?></span>
            <span class="chip-count"><?php echo $userCount; ?></span>
        </button>
        <?php } ?>
    </div>
</div>

<!-- Kanban Board -->
<div class="kanban-board" id="kanban-board">
<?php foreach ($statusMap as $statusCode => $statusInfo) {
    $columnTickets = $ticketsByStatus[$statusCode] ?? [];
    $colCount = count($columnTickets);
    $isCollapsed = ($statusCode == Ticket::STATUS_CANCELED);
?>
<div class="kanban-column<?php echo $isCollapsed ? ' collapsed' : ''; ?>" data-status="<?php echo $statusCode; ?>">
    <div class="kanban-column-header" style="--col-color: <?php echo $statusInfo['color']; ?>">
        <div class="kanban-column-dot" style="background-color: <?php echo $statusInfo['color']; ?>"></div>
        <span class="kanban-column-title"><?php echo dol_escape_htmltag($statusInfo['label']); ?></span>
        <span class="kanban-column-count" id="col-count-<?php echo $statusCode; ?>"><?php echo $colCount; ?></span>
        <button class="kanban-column-toggle" title="Afficher/masquer"><i class="fas fa-chevron-down"></i></button>
    </div>
    <div class="kanban-column-body" id="kanban-body-<?php echo $statusCode; ?>">
    <?php if (empty($columnTickets)) {
        echo '<div class="kanban-empty-col"><i class="fas fa-inbox"></i><span>Aucun ticket</span></div>';
    }
    foreach ($columnTickets as $t) {
        $ref = $t->ref ?: $t->track_id ?: '#' . $t->rowid;
        $companyName = $t->company_name ?: '';
        $severity = $severityMap[strtoupper($t->severity_code ?? '')] ?? ($t->severity_code ?: 'Normal');
        $progressPct = (int)($t->progress ?: 0);
        $dateFormatted = '';
        $elapsedTimeStr = '';
        if (!empty($t->datec)) {
            $dateTs = $db->jdate($t->datec);
            $dateFormatted = dol_print_date($dateTs, 'day');
            $diffSec = dol_now() - $dateTs;
            if ($diffSec > 0) {
                $diffDays = floor($diffSec / 86400);
                $diffHrs = floor(($diffSec % 86400) / 3600);
                $diffMins = floor(($diffSec % 3600) / 60);
                $tps = [];
                if ($diffDays > 0) $tps[] = $diffDays . 'j';
                $tps[] = str_pad($diffHrs, 2, '0', STR_PAD_LEFT) . ':' . str_pad($diffMins, 2, '0', STR_PAD_LEFT);
                $elapsedTimeStr = 'Tps: ' . implode(' ', $tps);
            }
        }
        $initials = '?';
        $photoHtml = '';
        $aId = (int)($t->fk_user_assign ?: 0);
        if ($aId > 0 && isset($allInternalUsers[$aId])) {
            $u = $allInternalUsers[$aId];
            if (!empty($u->firstname) && !empty($u->lastname)) $initials = mb_strtoupper(mb_substr($u->firstname, 0, 1) . mb_substr($u->lastname, 0, 1));
            if (!empty($u->photo) && trim($u->photo) !== '') {
                $pUrl = DOL_URL_ROOT . '/viewimage.php?modulepart=user&file=' . urlencode($u->rowid . '/' . $u->photo);
                $photoHtml = '<img src="' . dol_escape_htmltag($pUrl) . '" class="tc-assignee-img">';
            }
        }
        $rawMsg = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $t->message ?? ''));
        $rawMsg = trim(preg_replace('/\n{3,}/', "\n\n", $rawMsg));
        $safeSubject = dol_escape_htmltag($t->subject ?: 'Sans titre');
        $safeMessage = dol_escape_htmltag(mb_substr($rawMsg, 0, 200));
        $searchStr = mb_strtolower($ref . ' ' . ($t->subject ?: '') . ' ' . $companyName);
        $ticketUrl = DOL_URL_ROOT . '/ticket/card.php?id=' . $t->rowid;
        $stColor = $statusInfo['color'];
        $stLabel = $statusInfo['label'];
    ?>
    <div class="ticket-card-kanban" draggable="true" data-ticket-id="<?php echo (int)$t->rowid; ?>" data-status="<?php echo (int)$t->status; ?>" data-assignee="<?php echo $aId; ?>" data-search="<?php echo dol_escape_htmltag($searchStr); ?>">
        <div class="tc-header">
            <div class="tc-meta" style="flex:1;min-width:0;">
                <a href="<?php echo $ticketUrl; ?>" class="tc-ref"><?php echo dol_escape_htmltag($ref); ?></a>
                <span class="tc-sep">&bull;</span>
                <?php if (!empty($companyName)) { ?>
                <span class="tc-company"><i class="fas fa-building" style="color:#6a7491;"></i> <?php echo dol_escape_htmltag($companyName); ?></span>
                <span class="tc-sep">&bull;</span>
                <?php } ?>
                <span class="tc-date"><?php echo $dateFormatted; ?></span>
                <span class="tc-sep">&bull;</span>
                <span class="tc-time"><?php echo dol_escape_htmltag($elapsedTimeStr); ?></span>
            </div>
            <div class="tc-assignee tc-assignee-picker" data-ticket-id="<?php echo (int)$t->rowid; ?>" data-current-assignee="<?php echo $aId; ?>">
                <?php echo $photoHtml ?: dol_escape_htmltag($initials); ?>
                <i class="fas fa-caret-down tc-assignee-caret"></i>
            </div>
        </div>
        <div class="tc-row-middle">
            <span class="tc-severity"><?php echo dol_escape_htmltag($severity); ?></span>
            <span class="tc-sep">&bull;</span>
            <span class="tc-progress"><?php echo $progressPct; ?>%</span>
        </div>
        <div class="tc-title-row">
            <div class="tc-title"><?php echo $safeSubject; ?></div>
            <div class="tc-actions">
                <div class="tc-status-btn">
                    <div class="tc-status-dot" style="background-color:<?php echo $stColor; ?>"></div>
                    <span class="tc-status-label"><?php echo dol_escape_htmltag($stLabel); ?></span>
                </div>
            </div>
        </div>
        <?php if (!empty($safeMessage)) { ?>
        <div class="tc-body"><div class="tc-message-preview"><?php echo $safeMessage; ?></div></div>
        <?php } ?>
    </div>
    <?php } ?>
    </div>
</div>
<?php } ?>
</div>
</div>

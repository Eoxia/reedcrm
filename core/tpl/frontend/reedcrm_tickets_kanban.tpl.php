<?php
/**
 * \file    core/tpl/frontend/reedcrm_tickets_kanban.tpl.php
 * \ingroup reedcrm
 * \brief   Kanban board template for PWA tickets page
 *
 * Expects from parent:
 *   $ticketsByStatus  array  Tickets grouped by status code
 *   $statusMap        array  Status definitions [code => [label, color]]
 *   $severityMap      array  Severity translations
 *   $usersData        array  User objects indexed by rowid
 *   $totalTickets     int    Total ticket count
 */
?>

<!-- ===== ASSIGNEE FILTER BAR ===== -->
<div class="kanban-filters" id="kanban-filters">
    <div class="kanban-filter-chips">
        <button class="kanban-chip active" data-filter-user="all" title="Tous les tickets">
            <i class="fas fa-users"></i>
            <span>Tous</span>
            <span class="chip-count" id="chip-count-all"><?php echo $totalTickets; ?></span>
        </button>
        <?php
        // Unassigned chip
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
        <?php
        foreach ($usersData as $uid => $uObj) {
            $initials = '?';
            if (!empty($uObj->firstname) && !empty($uObj->lastname)) {
                $initials = mb_strtoupper(mb_substr($uObj->firstname, 0, 1) . mb_substr($uObj->lastname, 0, 1));
            } elseif (!empty($uObj->login)) {
                $initials = mb_strtoupper(mb_substr($uObj->login, 0, 2));
            }
            $fullName = trim(($uObj->firstname ?? '') . ' ' . ($uObj->lastname ?? ''));
            if (empty($fullName)) $fullName = $uObj->login ?? 'User #' . $uid;

            // Count tickets for this user
            $userCount = 0;
            foreach ($ticketsByStatus as $stTickets) {
                foreach ($stTickets as $t) {
                    if ((int)$t->fk_user_assign === (int)$uid) $userCount++;
                }
            }

            $avatarHtml = '';
            if (!empty($uObj->photo) && trim($uObj->photo) !== '') {
                $photoUrl = DOL_URL_ROOT . '/viewimage.php?modulepart=user&file=' . urlencode($uObj->rowid . '/' . $uObj->photo);
                $avatarHtml = '<img src="' . dol_escape_htmltag($photoUrl) . '" alt="' . dol_escape_htmltag($initials) . '" class="kanban-avatar-img">';
            } else {
                $avatarHtml = '<span class="kanban-avatar">' . dol_escape_htmltag($initials) . '</span>';
            }
            ?>
            <button class="kanban-chip" data-filter-user="<?php echo (int)$uid; ?>" title="<?php echo dol_escape_htmltag($fullName); ?>">
                <?php echo $avatarHtml; ?>
                <span><?php echo dol_escape_htmltag($fullName); ?></span>
                <span class="chip-count" id="chip-count-<?php echo (int)$uid; ?>"><?php echo $userCount; ?></span>
            </button>
            <?php
        }
        ?>
    </div>
</div>

<!-- ===== KANBAN BOARD ===== -->
<div class="kanban-board" id="kanban-board">
    <?php
    foreach ($statusMap as $statusCode => $statusInfo) {
        $columnTickets = $ticketsByStatus[$statusCode] ?? [];
        $colCount = count($columnTickets);
        // Annulé (9) and Fermé (8) collapsed by default
        $isCollapsed = in_array($statusCode, [Ticket::STATUS_CANCELED]);
    ?>
    <div class="kanban-column<?php echo $isCollapsed ? ' collapsed' : ''; ?>" data-status="<?php echo $statusCode; ?>" id="kanban-col-<?php echo $statusCode; ?>">
        <div class="kanban-column-header" style="--col-color: <?php echo $statusInfo['color']; ?>">
            <div class="kanban-column-dot" style="background-color: <?php echo $statusInfo['color']; ?>"></div>
            <span class="kanban-column-title"><?php echo dol_escape_htmltag($statusInfo['label']); ?></span>
            <span class="kanban-column-count" id="col-count-<?php echo $statusCode; ?>"><?php echo $colCount; ?></span>
            <button class="kanban-column-toggle" title="Afficher/masquer"><i class="fas fa-chevron-down"></i></button>
        </div>
        <div class="kanban-column-body" id="kanban-body-<?php echo $statusCode; ?>">
            <?php
            if (empty($columnTickets)) {
                echo '<div class="kanban-empty-col"><i class="fas fa-inbox"></i><span>Aucun ticket</span></div>';
            }
            foreach ($columnTickets as $ticket) {
                // --- Build card data ---
                $ref = $ticket->ref ?: $ticket->track_id ?: 'Ticket #' . $ticket->rowid;
                $companyName = $ticket->company_name ?: '';
                $severity = $severityMap[strtoupper($ticket->severity_code ?? '')] ?? ($ticket->severity_code ?: 'Normal');
                $progressPct = (int)($ticket->progress ?: 0);

                // Date & Elapsed
                $dateFormatted = '';
                $elapsedTimeStr = '';
                if (!empty($ticket->datec)) {
                    $dateTs = $db->jdate($ticket->datec);
                    $dateFormatted = dol_print_date($dateTs, 'day');
                    $diffSec = dol_now() - $dateTs;
                    if ($diffSec > 0) {
                        $diffDays = floor($diffSec / 86400);
                        $diffHrs  = floor(($diffSec % 86400) / 3600);
                        $diffMins = floor(($diffSec % 3600) / 60);
                        $tps = [];
                        if ($diffDays > 0) $tps[] = $diffDays . 'j';
                        $tps[] = str_pad($diffHrs, 2, '0', STR_PAD_LEFT) . ':' . str_pad($diffMins, 2, '0', STR_PAD_LEFT);
                        $elapsedTimeStr = 'Tps: ' . implode(' ', $tps);
                    }
                }

                // Assignee
                $initials = '?';
                $photoHtml = '';
                $assigneeId = (int)($ticket->fk_user_assign ?: 0);
                if ($assigneeId > 0 && isset($usersData[$assigneeId])) {
                    $u = $usersData[$assigneeId];
                    if (!empty($u->firstname) && !empty($u->lastname)) {
                        $initials = mb_strtoupper(mb_substr($u->firstname, 0, 1) . mb_substr($u->lastname, 0, 1));
                    } elseif (!empty($u->login)) {
                        $initials = mb_strtoupper(mb_substr($u->login, 0, 2));
                    }
                    if (!empty($u->photo) && trim($u->photo) !== '') {
                        $pUrl = DOL_URL_ROOT . '/viewimage.php?modulepart=user&file=' . urlencode($u->rowid . '/' . $u->photo);
                        $photoHtml = '<img src="' . dol_escape_htmltag($pUrl) . '" alt="' . dol_escape_htmltag($initials) . '" class="tc-assignee-img">';
                    }
                }

                // Clean message
                $rawMsg = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $ticket->message ?? ''));
                $rawMsg = trim(preg_replace('/\n{3,}/', "\n\n", $rawMsg));
                $safeSubject = dol_escape_htmltag($ticket->subject ?: 'Sans titre');
                $safeMessage = dol_escape_htmltag(mb_substr($rawMsg, 0, 200));

                // Search string for JS filter
                $searchStr = mb_strtolower($ref . ' ' . ($ticket->subject ?: '') . ' ' . $companyName);

                // URLs
                $ticketUrl = DOL_URL_ROOT . '/ticket/card.php?id=' . $ticket->rowid;
                $chatUrl   = DOL_URL_ROOT . '/ticket/messaging.php?id=' . $ticket->rowid;

                // Status
                $stColor = $statusInfo['color'];
                $stLabel = $statusInfo['label'];
            ?>
            <div class="ticket-card-kanban" draggable="true"
                 data-ticket-id="<?php echo (int)$ticket->rowid; ?>"
                 data-status="<?php echo (int)$ticket->status; ?>"
                 data-assignee="<?php echo $assigneeId; ?>"
                 data-search="<?php echo dol_escape_htmltag($searchStr); ?>">
                <div class="tc-header">
                    <div class="tc-meta">
                        <a href="<?php echo $ticketUrl; ?>" class="tc-ref"><?php echo dol_escape_htmltag($ref); ?></a>
                        <span class="tc-sep">&bull;</span>
                        <?php if (!empty($companyName)) { ?>
                        <span class="tc-company" title="Tiers"><i class="fas fa-building" style="color: #6a7491;"></i> <?php echo dol_escape_htmltag($companyName); ?></span>
                        <span class="tc-sep">&bull;</span>
                        <?php } ?>
                        <span class="tc-date" title="Créé le"><?php echo $dateFormatted; ?></span>
                        <span class="tc-sep">&bull;</span>
                        <span class="tc-time" title="Temps écoulé"><?php echo dol_escape_htmltag($elapsedTimeStr); ?></span>
                    </div>
                    <div class="tc-assignee" title="Assigné à">
                        <?php echo $photoHtml ?: dol_escape_htmltag($initials); ?>
                    </div>
                </div>
                <div class="tc-row-middle">
                    <span class="tc-severity"><?php echo dol_escape_htmltag($severity); ?></span>
                    <span class="tc-sep">&bull;</span>
                    <span class="tc-progress"><?php echo $progressPct; ?>%</span>
                </div>
                <div class="tc-title-row">
                    <div class="tc-title" title="<?php echo $safeSubject; ?>"><?php echo $safeSubject; ?></div>
                    <div class="tc-actions">
                        <div class="tc-status-btn" title="Statut">
                            <div class="tc-status-dot" style="background-color: <?php echo $stColor; ?>"></div>
                            <span class="tc-status-label"><?php echo dol_escape_htmltag($stLabel); ?></span>
                        </div>
                        <a href="<?php echo $chatUrl; ?>" class="tc-chat-link" title="Messages & Évènements">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>
                        </a>
                    </div>
                </div>
                <?php if (!empty($safeMessage)) { ?>
                <div class="tc-body">
                    <div class="tc-message-preview"><?php echo $safeMessage; ?></div>
                </div>
                <?php } ?>
            </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>


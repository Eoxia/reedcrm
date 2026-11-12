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
 * along with this program.  If not, see https://www.gnu.org/licenses/.
 */

/**
 * \file    lib/reedcrm_ticket_time.lib.php
 * \ingroup reedcrm
 * \brief   Time logged on a ticket : one task "<prefix> <suffix>" in the project of the ticket, plus an agenda event.
 *          Shared by the quick time block of the ticket card (core/ajax/ticket_time.php) and the money banner.
 */

require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

/**
 * Log time on a ticket
 *
 * @param  DoliDB $db      Database handler
 * @param  User   $user    User who spent the time
 * @param  Ticket $ticket  Ticket
 * @param  int    $minutes Time spent, in minutes
 * @param  string $note    Comment of the time line
 * @return string          Empty if OK, error message if KO
 */
function reedcrm_ticket_log_time(DoliDB $db, User $user, Ticket $ticket, int $minutes, string $note = ''): string
{
    if ($minutes <= 0) {
        return 'Paramètres invalides. Veuillez fournir un temps > 0.';
    }
    if (empty($ticket->fk_project)) {
        return 'Le ticket n\'est lié à aucun projet. Veuillez lier un projet au ticket pour consigner du temps.';
    }

    $prefix     = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_PREFIX', 'ticket_tps');
    $suffixType = getDolGlobalString('REEDCRM_TICKET_TIME_TASK_SUFFIX', 'ticket_ref');

    $project = new Project($db);
    $project->fetch($ticket->fk_project);

    $suffix = '';
    if ($suffixType === 'ticket_ref') {
        $suffix = ' ' . $ticket->ref;
    } elseif ($suffixType === 'project_ref') {
        $suffix = ' ' . $project->ref;
    } elseif ($suffixType === 'project_label') {
        $suffix = ' ' . $project->title;
    }
    $expectedLabel = trim($prefix . $suffix);

    // Find the task in this project that matches the expected label
    $sql  = 'SELECT t.rowid FROM ' . MAIN_DB_PREFIX . 'projet_task as t';
    $sql .= ' WHERE t.fk_projet = ' . (int) $ticket->fk_project;
    $sql .= " AND t.label = '" . $db->escape($expectedLabel) . "'";

    $resql = $db->query($sql);
    if (!$resql) {
        return $db->lasterror();
    }
    $obj = $db->fetch_object($resql);

    $task = new Task($db);
    if ($obj) {
        if ($task->fetch($obj->rowid) <= 0) {
            return 'Erreur lors du chargement de la tâche: ' . $task->error;
        }
    } else {
        // Task not found, create it automatically
        $task->fk_project  = $ticket->fk_project;
        $task->ref         = $ticket->ref;
        $task->label       = $expectedLabel;
        $task->description = 'Tâche générée automatiquement pour le ticket ' . $ticket->ref;
        $task->date_c      = dol_now();
        $task->date_start  = dol_now();
        $task->date_end    = dol_now();
        $task->progress    = 0;
        $task->status      = 1;

        if ($task->create($user) <= 0) {
            return 'Erreur lors de la création de la tâche: ' . $task->error;
        }
    }

    // Add time spent
    $task->timespent_duration = $minutes * 60;
    $task->timespent_date     = dol_now();
    $task->timespent_datehour = dol_now();
    $task->timespent_fk_user  = $user->id;
    $task->timespent_note     = $note;
    if ($task->addTimeSpent($user) <= 0) {
        return 'Erreur lors de l\'ajout du temps: ' . $task->error;
    }

    $actioncomm            = new ActionComm($db);
    $actioncomm->type_code = 'AC_OTH_AUTO';
    $actioncomm->code      = 'TICKET_TIMESPENT';
    $actioncomm->socid     = $ticket->socid;

    $titleMaxLength    = getDolGlobalInt('REEDCRM_TICKET_TIME_TITLE_MAXLENGTH', 200);
    $cleanNoteForLabel = trim(preg_replace('/\s+/', ' ', $note));
    $actioncomm->label = !empty($cleanNoteForLabel) ? dol_trunc($cleanNoteForLabel, $titleMaxLength) : 'Temps consigné (' . $minutes . ' min)';

    $desc = 'Temps : ' . $minutes . ' min';
    if (!empty($note)) {
        $desc .= '<br>Commentaire :<br>' . nl2br(dol_escape_htmltag($note));
    }
    $actioncomm->note_private = $desc;

    $actioncomm->userassigned = [$user->id => ['id' => $user->id, 'transparency' => 0]];
    $actioncomm->userownerid  = $user->id;
    $actioncomm->datep        = dol_now();
    $actioncomm->percentage   = 100;
    $actioncomm->elementtype  = 'ticket';
    $actioncomm->fk_element   = $ticket->id;
    $actioncomm->create($user);

    return '';
}

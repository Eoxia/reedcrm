<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/view/eventpro/eventpro_actions.tpl.php
 * \ingroup reedcrm
 * \brief   eventPro action handlers (create_contact, add_event, create_ticket), shared by the
 *          desktop card (view/procard.php) and the mobile App form (view/frontend/pwa_relaunch.php).
 *          Expects $action, $id, $fromType, $object, $actionComm, $category, $isModal, $currentTab,
 *          $langs, $user and $db to be in scope, exactly as the desktop card sets them up.
 *          Both pages redirect to themselves by default; a caller may override the destination with
 *          $eventProRedirectAfterEvent / $eventProRedirectAfterTicket before including this file.
 */

if (!defined('DOL_DOCUMENT_ROOT')) {
    exit;
}

if (empty($eventProRedirectAfterEvent)) {
    $eventProRedirectAfterEvent = $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType . '&tab=note';
}
if (empty($eventProRedirectAfterTicket)) {
    $eventProRedirectAfterTicket = $_SERVER['PHP_SELF'] . '?from_id=' . $id . '&from_type=' . $fromType . '&tab=ticket';
}

    // Action to create contact
    if ($action == 'create_contact') {
        require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
        
        $contact = new Contact($db);
        $contact->socid = GETPOSTINT('socid') ?: (isset($object->thirdparty->id) ? $object->thirdparty->id : 0);
        $contact->lastname = GETPOST('new_contact_lastname', 'alpha');
        $contact->firstname = GETPOST('new_contact_firstname', 'alpha');
        $contact->phone_pro = GETPOST('new_contact_phone_pro', 'alpha');
        $contact->email = GETPOST('new_contact_email', 'email');
        $contact->statut = 1; // Active
        
        // Return JSON response for AJAX
        header('Content-Type: application/json');
        
        if (empty($contact->lastname)) {
            echo json_encode([
                'success' => false,
                'error' => $langs->trans('ErrorFieldRequired', $langs->transnoentities('Lastname'))
            ]);
            exit;
        }
        
        if (empty($contact->socid)) {
            echo json_encode([
                'success' => false,
                'error' => $langs->trans('ErrorFieldRequired', $langs->transnoentities('ThirdParty'))
            ]);
            exit;
        }
        
        $result = $contact->create($user);
        if ($result > 0) {
            // Fetch the contact to get full data
            $contact->fetch($result);
            
            echo json_encode([
                'success' => true,
                'contact_id' => $result,
                'contact_label' => $contact->getFullName($langs)
            ]);
            exit;
        } else {
            $errorMsg = $contact->error;
            if (empty($errorMsg) && !empty($contact->errors)) {
                $errorMsg = implode(', ', $contact->errors);
            }
            if (empty($errorMsg)) {
                $errorMsg = $langs->trans('Error');
            }
            echo json_encode([
                'success' => false,
                'error' => $errorMsg
            ]);
            exit;
        }
    }

    // Action to add commercial relaunch event
    if ($action == 'add_event') {
        $actionComm->socid             = GETPOSTINT('socid');
        $actionComm->socpeopleassigned = [GETPOSTINT('contactid') => GETPOSTINT('contactid')];
        $actionComm->type_code         = GETPOST('actioncode', 'aZ09');
        $actionComm->percentage        = 100;
        
        $datep = dol_mktime(GETPOSTINT('event_hour'), GETPOSTINT('event_min'), 0, GETPOSTINT('event_month'), GETPOSTINT('event_day'), GETPOSTINT('event_year'), 'tzuserrel');
        if ($datep > 0) {
            $actionComm->datep = $datep;
        } else {
            $actionComm->datep = dol_now();
        }

        $actionComm->fk_project   = GETPOST('project_id', 'int');
        $actionComm->userownerid  = $user->id;
        $actionComm->userassigned = [$user->id => ['id' => $user->id]];

        $actionComm->label        = GETPOST('title');
        $actionComm->note_private = GETPOST('description', 'restricthtml');

        $result = $actionComm->create($user);

        $category->fetch(getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG'));
        $category->add_type($actionComm, 'actioncomm');

        if ($result > 0 && !empty(GETPOST('reminder_title'))) {
            require_once __DIR__ . '/../../../../lib/reedcrm_relaunch.lib.php';

            // $result is deliberately not overwritten any more: a failed reminder used to be
            // reported to the user as a failed event, and blocked the opportunity update below.
            reedcrm_create_call_reminder($db, $user, [
                'label'     => GETPOST('reminder_title'),
                'datep'     => dol_mktime(GETPOSTINT('reminder_hour'), GETPOSTINT('reminder_min'), 0, GETPOSTINT('reminder_month'), GETPOSTINT('reminder_day'), GETPOSTINT('reminder_year'), 'tzuserrel'),
                'userId'    => GETPOSTINT('reminder_user_id'),
                'projectId' => GETPOSTINT('project_id'),
                'socid'     => GETPOSTINT('socid'),
                'contactId' => GETPOSTINT('contactid'),
            ]);
        }

        $newOpportunityPercent = GETPOST('new_opportunity_percent');
        $newOpportunityStatus  = GETPOST('new_opportunity_status');
        if ($result > 0 && ($object->opp_percent != $newOpportunityPercent || $object->opp_status != $newOpportunityStatus)) {
            $object->opp_percent = $newOpportunityPercent;
            $object->opp_status  = $newOpportunityStatus;
            $result = $object->update($user);
        }

        if ($result > 0) {
            if ($isModal) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $langs->trans('EventCreated')]);
                exit;
            }
            setEventMessages($langs->trans('EventCreated'), null);
            header('Location: ' . $eventProRedirectAfterEvent);
            exit;
        } else {
            if ($isModal) {
                header('Content-Type: application/json');
                $errorMsg = $actionComm->error;
                if (empty($errorMsg) && !empty($actionComm->errors)) {
                    $errorMsg = implode(', ', $actionComm->errors);
                }
                echo json_encode(['success' => false, 'error' => $errorMsg ?: $langs->trans('Error')]);
                exit;
            }
            setEventMessages($actionComm->error, $actionComm->errors, 'errors');
        }
    }

    // Action to add a direct reminder, without logging a completed event first (#873). This is what
    // the right-hand button of the relaunch widget posts.
    if ($action == 'add_reminder') {
        require_once __DIR__ . '/../../../../lib/reedcrm_relaunch.lib.php';

        $reminderDate = dol_mktime(GETPOSTINT('reminder_hour'), GETPOSTINT('reminder_min'), 0, GETPOSTINT('reminder_month'), GETPOSTINT('reminder_day'), GETPOSTINT('reminder_year'), 'tzuserrel');

        $reminderId = reedcrm_create_call_reminder($db, $user, [
            'label'     => GETPOST('reminder_title'),
            'datep'     => $reminderDate,
            'userId'    => GETPOSTINT('reminder_user_id'),
            'projectId' => GETPOSTINT('project_id') ?: ($fromType == 'project' ? (int) $id : 0),
            'socid'     => GETPOSTINT('socid'),
            'contactId' => GETPOSTINT('contactid'),
            'typeCode'  => GETPOST('actioncode', 'aZ09') ?: 'AC_OTH',
            'note'      => GETPOST('description', 'restricthtml'),
        ]);

        if ($reminderId > 0) {
            if ($isModal) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $langs->trans('RelaunchReminderCreated')]);
                exit;
            }
            setEventMessages($langs->trans('RelaunchReminderCreated'), null);
            header('Location: ' . $eventProRedirectAfterEvent);
            exit;
        }

        if ($isModal) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $langs->trans('RelaunchReminderTitleRequired')]);
            exit;
        }
        setEventMessages($langs->trans('RelaunchReminderTitleRequired'), null, 'errors');
    }

    // Action to create ticket
    if ($action == 'create_ticket' && isModEnabled('ticket')) {
        $error = 0;
        $errorMessages = [];

        // Validate required fields
        if (empty(GETPOST('ticket_subject', 'alphanohtml'))) {
            $errorMessages[] = $langs->trans("ErrorFieldRequired", $langs->transnoentities("Subject"));
            $error++;
        }
        if (empty(GETPOST('ticket_type', 'aZ09'))) {
            $errorMessages[] = $langs->trans("ErrorFieldRequired", $langs->transnoentities("Type"));
            $error++;
        }
        if (empty(GETPOST('ticket_category', 'aZ09'))) {
            $errorMessages[] = $langs->trans("ErrorFieldRequired", $langs->transnoentities("Category"));
            $error++;
        }

        if ($error && $isModal) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => implode(', ', $errorMessages)]);
            exit;
        }

        if ($error && !$isModal) {
            foreach ($errorMessages as $msg) {
                setEventMessages($msg, null, 'errors');
            }
        }

        if (!$error) {
        $ticket = new Ticket($db);

        // Get form data
        $ticket->ref = $ticket->getDefaultRef($user);
        $ticket->subject = GETPOST('ticket_subject', 'alphanohtml');
        $ticket->message = GETPOST('ticket_message', 'restricthtml');
        $ticket->fk_project = GETPOST('project_id');
            $ticket->fk_soc = GETPOST('ticket_socid', 'int') ?: (isset($object->thirdparty->id) ? $object->thirdparty->id : 0);
        $ticket->fk_user_assign = GETPOST('ticket_user_assign', 'int');
        $ticket->type_code = GETPOST('ticket_type', 'aZ09');
        $ticket->category_code = GETPOST('ticket_category', 'aZ09');
        $ticket->timing = GETPOST('ticket_timing', 'int');
        $ticket->status = 0; // New ticket

        // Handle date start
        $date_start = GETPOST('ticket_date_start', 'int');
        if ($date_start > 0) {
            $ticket->datec = $date_start;
        } else {
            $ticket->datec = dol_now();
        }

        // Set contact if selected
        $contactid = GETPOST('ticket_contact_id', 'int');
        if ($contactid > 0) {
            $ticket->context['contactid'] = $contactid;
        }

        // Disable email notifications for ticket creation
        $ticket->context['disableticketemail'] = 1;

        // Create the ticket
        $result = $ticket->create($user);

        if ($result > 0) {
                // Link ticket to project if project is set
                $projectid = GETPOST('project_id', 'int');
                if ($projectid > 0) {
                    $ticket->setProject($projectid);
                } elseif ($fromType == 'project' && $id > 0) {
                    // If created from a project, link to that project
                    $ticket->setProject($id);
                }

                // Link ticket to thirdparty (already set via fk_soc, but ensure link is created)
                if ($ticket->fk_soc > 0) {
                    // The fk_soc is already set, but we can also create an object link if needed
                    // This is optional as fk_soc already links the ticket to the thirdparty
                    $ticket->add_object_linked('societe', $ticket->fk_soc, $user);
                }

            if ($isModal) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => $langs->trans('TicketCreated')]);
                    exit;
                }
            setEventMessages($langs->trans("TicketCreated"), null, 'mesgs');
                // Redirect to avoid resubmission - include tab to show ticket tab
                header('Location: ' . $eventProRedirectAfterTicket);
            exit;
        } else {
                if ($isModal) {
                    header('Content-Type: application/json');
                    $errorMsg = $ticket->error;
                    if (empty($errorMsg) && !empty($ticket->errors)) {
                        $errorMsg = implode(', ', $ticket->errors);
                    }
                    echo json_encode(['success' => false, 'error' => $errorMsg ?: $langs->trans('Error')]);
                    exit;
                }
            setEventMessages($ticket->error, $ticket->errors, 'errors');
                // Stay on ticket tab to show errors
                $currentTab = 'ticket';
            }
        } else {
            // Stay on ticket tab to show errors
            $currentTab = 'ticket';
        }
    }

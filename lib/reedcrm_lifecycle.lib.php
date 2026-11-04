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
 * \file    lib/reedcrm_lifecycle.lib.php
 * \ingroup reedcrm
 * \brief   Library building the lifecycle timeline printed in the banner of the proposal,
 *          order, invoice and project cards, and in the rows of the opportunity list
 */

/**
 * Elements carrying a lifecycle timeline
 */
const REEDCRM_LIFECYCLE_ELEMENTS = ['propal', 'commande', 'facture', 'project'];

/**
 * Milestones of the project timeline in the order they are reached, with the caption of each
 *
 * The position of a milestone is its rank: the opportunity list sorts and filters its rows on the
 * rank of the last milestone reached, computed in SQL by reedcrm_lifecycle_project_current_step_sql()
 */
const REEDCRM_LIFECYCLE_PROJECT_STEPS = [
    'creation'  => 'LifecycleStepCreation',
    'start'     => 'LifecycleStepProjectStart',
    'proposal'  => 'LifecycleStepFirstProposal',
    'signature' => 'LifecycleStepSignature',
    'order'     => 'LifecycleStepFirstOrder',
    'billing'   => 'LifecycleStepFirstInvoice',
    'end'       => 'LifecycleStepProjectEnd',
    'closing'   => 'LifecycleStepClosing'
];

/**
 * Build the lifecycle steps of an object
 *
 * Each step is an array holding:
 *  - key     : stable identifier of the milestone
 *  - label   : short caption printed under the dot
 *  - date    : timestamp of the milestone, null as long as it has not happened
 *  - state   : done | todo | late | ko
 *  - current : set on the last milestone actually reached
 *  - picto   : Font Awesome class of the dot
 *  - detail  : lines of plain text describing the milestone, shown in the tooltip
 *
 * @param  CommonObject $object Object shown by the card
 * @return array                Ordered steps, empty when the object has no timeline
 */
function reedcrm_get_lifecycle_steps(CommonObject $object): array
{
    if (empty($object->id) || !in_array($object->element, REEDCRM_LIFECYCLE_ELEMENTS)) {
        return [];
    }

    switch ($object->element) {
        case 'propal':
            $steps = reedcrm_get_proposal_lifecycle_steps($object);
            break;
        case 'commande':
            $steps = reedcrm_get_order_lifecycle_steps($object);
            break;
        case 'facture':
            $steps = reedcrm_get_invoice_lifecycle_steps($object);
            break;
        case 'project':
            $steps = reedcrm_get_project_lifecycle_steps($object);
            break;
        default:
            $steps = [];
    }

    return reedcrm_lifecycle_mark_current($steps);
}

/**
 * Build the lifecycle steps of a commercial proposal
 *
 * @param  CommonObject $object Proposal
 * @return array                Ordered steps
 */
function reedcrm_get_proposal_lifecycle_steps(CommonObject $object): array
{
    global $langs;

    $row = reedcrm_lifecycle_fetch_row('propal', (int) $object->id, ['datec', 'date_valid', 'fin_validite', 'date_signature', 'date_cloture', 'fk_statut', 'fk_projet', 'fk_soc', 'fk_user_author', 'fk_user_valid', 'fk_user_signature', 'fk_user_cloture']);
    if (empty($row)) {
        return [];
    }

    $status    = (int) $row['fk_statut'];
    $sent      = reedcrm_lifecycle_sent_summary('propal', (int) $object->id);
    $signature = reedcrm_lifecycle_date($row, 'date_signature') ?: reedcrm_lifecycle_date($row, 'date_cloture');
    $isSigned  = in_array($status, [Propal::STATUS_SIGNED, Propal::STATUS_BILLED]);
    $isRefused = ($status == Propal::STATUS_NOTSIGNED);

    // ReedCRM tracks its own commercial pipeline on the proposal, as an ordered dictionary
    $commercialStatus = reedcrm_lifecycle_commercial_status((int) $object->id);

    $steps   = [];
    $steps[] = reedcrm_lifecycle_step('creation', $langs->transnoentities('LifecycleStepCreation'), reedcrm_lifecycle_date($row, 'datec'), 'fa-plus', reedcrm_lifecycle_user_detail((int) $row['fk_user_author']));
    $steps[] = reedcrm_lifecycle_step('validation', $langs->transnoentities('LifecycleStepValidation'), reedcrm_lifecycle_date($row, 'date_valid'), 'fa-check', reedcrm_lifecycle_user_detail((int) $row['fk_user_valid']));

    // Reception of the request, read from the commercial status: that dictionary carries no date
    $steps[] = reedcrm_lifecycle_step('reception', $langs->transnoentities('LifecycleStepReception'), null, 'fa-inbox', reedcrm_lifecycle_commercial_status_detail($commercialStatus), reedcrm_lifecycle_commercial_status_state($commercialStatus, 'Received'));

    // The mail is the dated proof of the sending, the commercial status takes over when there is none
    $sendingStep = reedcrm_lifecycle_sending_step($sent);
    if (empty($sendingStep['date']) && reedcrm_lifecycle_commercial_status_state($commercialStatus, 'Sent') == 'done') {
        $sendingStep['state']  = 'done';
        $sendingStep['detail'] = reedcrm_lifecycle_commercial_status_detail($commercialStatus);
    }
    $steps[] = $sendingStep;

    // Follow-ups run from ReedCRM: the agenda events it tags, carried by the opportunity of the proposal
    $steps[] = reedcrm_lifecycle_relaunch_step((int) $row['fk_projet'], (int) $row['fk_soc']);

    // End of validity turns red as soon as the deadline is gone while the proposal is still waiting for its answer
    $validityEnd = reedcrm_lifecycle_date($row, 'fin_validite');
    $steps[]     = reedcrm_lifecycle_step('validityend', $langs->transnoentities('LifecycleStepValidityEnd'), $validityEnd, 'fa-hourglass-half', [], reedcrm_lifecycle_deadline_state($validityEnd, $isSigned || $isRefused));

    if ($isRefused) {
        $steps[] = reedcrm_lifecycle_step('signature', $langs->transnoentities('LifecycleStepRefusal'), $signature, 'fa-times', reedcrm_lifecycle_user_detail((int) $row['fk_user_cloture']), 'ko');
    } else {
        $signatureDetail = reedcrm_lifecycle_user_detail((int) ($row['fk_user_signature'] ?: $row['fk_user_cloture']));
        $steps[]         = reedcrm_lifecycle_step('signature', $langs->transnoentities('LifecycleStepSignature'), $isSigned ? $signature : null, 'fa-file-signature', $signatureDetail);
    }

    if (isModEnabled('invoice')) {
        $invoices   = reedcrm_lifecycle_linked_summary('propal', (int) $object->id, 'facture', 'facture', 'datec');
        $billedDate = $invoices['first'] ?: (($status == Propal::STATUS_BILLED) ? reedcrm_lifecycle_date($row, 'date_cloture') : null);
        $steps[]    = reedcrm_lifecycle_step('billing', $langs->transnoentities('LifecycleStepBilling'), $billedDate, 'fa-file-invoice-dollar', reedcrm_lifecycle_count_detail($invoices['count']));
    }

    return $steps;
}

/**
 * Build the lifecycle steps of a customer order
 *
 * @param  CommonObject $object Order
 * @return array                Ordered steps
 */
function reedcrm_get_order_lifecycle_steps(CommonObject $object): array
{
    global $langs;

    $row = reedcrm_lifecycle_fetch_row('commande', (int) $object->id, ['date_creation', 'date_valid', 'date_cloture', 'date_livraison', 'fk_statut', 'facture', 'fk_user_author', 'fk_user_valid', 'fk_user_cloture']);
    if (empty($row)) {
        return [];
    }

    $status     = (int) $row['fk_statut'];
    $sent       = reedcrm_lifecycle_sent_summary('order', (int) $object->id);
    $isClosed   = ($status == Commande::STATUS_CLOSED);
    $isCanceled = ($status == Commande::STATUS_CANCELED);

    $steps   = [];
    $steps[] = reedcrm_lifecycle_step('creation', $langs->transnoentities('LifecycleStepCreation'), reedcrm_lifecycle_date($row, 'date_creation'), 'fa-plus', reedcrm_lifecycle_user_detail((int) $row['fk_user_author']));
    $steps[] = reedcrm_lifecycle_step('validation', $langs->transnoentities('LifecycleStepValidation'), reedcrm_lifecycle_date($row, 'date_valid'), 'fa-check', reedcrm_lifecycle_user_detail((int) $row['fk_user_valid']));
    $steps[] = reedcrm_lifecycle_sending_step($sent);

    // Planned delivery turns red once the date is gone while the order is still running
    $delivery = reedcrm_lifecycle_date($row, 'date_livraison');
    $steps[]  = reedcrm_lifecycle_step('delivery', $langs->transnoentities('LifecycleStepDelivery'), $delivery, 'fa-truck', [], reedcrm_lifecycle_deadline_state($delivery, $isClosed || $isCanceled));

    if (isModEnabled('expedition')) {
        // date_expedition holds the day the goods really left, date_delivery only the planned one
        $shipments = reedcrm_lifecycle_linked_summary('commande', (int) $object->id, 'shipping', 'expedition', 'date_expedition');
        $steps[]   = reedcrm_lifecycle_step('shipping', $langs->transnoentities('LifecycleStepShipping'), $shipments['last'], 'fa-dolly', reedcrm_lifecycle_count_detail($shipments['count']));
    }

    if ($isCanceled) {
        $steps[] = reedcrm_lifecycle_step('closing', $langs->transnoentities('LifecycleStepCancellation'), reedcrm_lifecycle_date($row, 'date_cloture'), 'fa-ban', reedcrm_lifecycle_user_detail((int) $row['fk_user_cloture']), 'ko');
    } else {
        $steps[] = reedcrm_lifecycle_step('closing', $langs->transnoentities('LifecycleStepClosing'), $isClosed ? reedcrm_lifecycle_date($row, 'date_cloture') : null, 'fa-flag-checkered', reedcrm_lifecycle_user_detail((int) $row['fk_user_cloture']));
    }

    if (isModEnabled('invoice')) {
        $invoices   = reedcrm_lifecycle_linked_summary('commande', (int) $object->id, 'facture', 'facture', 'datec');
        $billedDate = $invoices['first'] ?: (!empty($row['facture']) ? reedcrm_lifecycle_date($row, 'date_cloture') : null);
        $steps[]    = reedcrm_lifecycle_step('billing', $langs->transnoentities('LifecycleStepBilling'), $billedDate, 'fa-file-invoice-dollar', reedcrm_lifecycle_count_detail($invoices['count']));
    }

    return $steps;
}

/**
 * Build the lifecycle steps of a customer invoice
 *
 * @param  CommonObject $object Invoice
 * @return array                Ordered steps
 */
function reedcrm_get_invoice_lifecycle_steps(CommonObject $object): array
{
    global $langs;

    $row = reedcrm_lifecycle_fetch_row('facture', (int) $object->id, ['datec', 'date_valid', 'date_lim_reglement', 'date_closing', 'fk_statut', 'paye', 'total_ttc', 'fk_user_author', 'fk_user_valid', 'fk_user_closing']);
    if (empty($row)) {
        return [];
    }

    $status      = (int) $row['fk_statut'];
    $isPaid      = !empty($row['paye']);
    $isAbandoned = ($status == Facture::STATUS_ABANDONED);
    $sent        = reedcrm_lifecycle_sent_summary('invoice', (int) $object->id);
    $payments    = reedcrm_lifecycle_payment_summary((int) $object->id);

    $steps   = [];
    $steps[] = reedcrm_lifecycle_step('creation', $langs->transnoentities('LifecycleStepCreation'), reedcrm_lifecycle_date($row, 'datec'), 'fa-plus', reedcrm_lifecycle_user_detail((int) $row['fk_user_author']));
    $steps[] = reedcrm_lifecycle_step('validation', $langs->transnoentities('LifecycleStepValidation'), reedcrm_lifecycle_date($row, 'date_valid'), 'fa-check', reedcrm_lifecycle_user_detail((int) $row['fk_user_valid']));
    $steps[] = reedcrm_lifecycle_sending_step($sent);

    // Due date turns red once it is gone while the invoice is still waiting for its payment
    $dueDate = reedcrm_lifecycle_date($row, 'date_lim_reglement');
    $steps[] = reedcrm_lifecycle_step('duedate', $langs->transnoentities('LifecycleStepDueDate'), $dueDate, 'fa-hourglass-half', [], reedcrm_lifecycle_deadline_state($dueDate, $isPaid || $isAbandoned));

    $paymentDetail = [];
    if ($payments['count'] > 0) {
        $paymentDetail[] = $langs->transnoentities('LifecycleTooltipPaymentCount', $payments['count']);
        $paymentDetail[] = $langs->transnoentities('LifecycleTooltipPaidAmount', price($payments['amount'], 0, $langs, 1, -1, -1, 'auto'), price($row['total_ttc'], 0, $langs, 1, -1, -1, 'auto'));
    }
    $steps[] = reedcrm_lifecycle_step('payment', $langs->transnoentities('LifecycleStepPayment'), $payments['last'], 'fa-money-bill-wave', $paymentDetail);

    if ($isAbandoned) {
        $steps[] = reedcrm_lifecycle_step('closing', $langs->transnoentities('LifecycleStepAbandon'), reedcrm_lifecycle_date($row, 'date_closing'), 'fa-ban', reedcrm_lifecycle_user_detail((int) $row['fk_user_closing']), 'ko');
    } else {
        $closingDate = ($status == Facture::STATUS_CLOSED) ? (reedcrm_lifecycle_date($row, 'date_closing') ?: $payments['last']) : null;
        $steps[]     = reedcrm_lifecycle_step('closing', $langs->transnoentities('LifecycleStepClosing'), $closingDate, 'fa-flag-checkered', reedcrm_lifecycle_user_detail((int) $row['fk_user_closing']));
    }

    return $steps;
}

/**
 * Build the lifecycle steps of a project
 *
 * A project is the opportunity of the CRM: its timeline walks the documents it carries,
 * from the first proposal down to the invoice, so the whole deal reads at a glance
 *
 * @param  CommonObject $object Project
 * @return array                Ordered steps
 */
function reedcrm_get_project_lifecycle_steps(CommonObject $object): array
{
    $projects = reedcrm_lifecycle_fetch_projects([(int) $object->id]);
    if (empty($projects[(int) $object->id])) {
        return [];
    }

    return reedcrm_build_project_lifecycle_steps($projects[(int) $object->id]);
}

/**
 * Build the lifecycle steps of a project from what reedcrm_lifecycle_fetch_projects() read of it
 *
 * Any change to the milestones has to be carried over to reedcrm_lifecycle_project_current_step_sql(),
 * which tells the opportunity list where each project stands
 *
 * @param  array $project Row of the project, the summaries of its documents under 'documents'
 * @return array          Ordered steps
 */
function reedcrm_build_project_lifecycle_steps(array $project): array
{
    global $langs;

    $isClosed = ((int) $project['fk_statut'] == Project::STATUS_CLOSED);
    $captions = REEDCRM_LIFECYCLE_PROJECT_STEPS;

    $steps   = [];
    $steps[] = reedcrm_lifecycle_step('creation', $langs->transnoentities($captions['creation']), reedcrm_lifecycle_date($project, 'datec'), 'fa-plus', reedcrm_lifecycle_user_detail((int) $project['fk_user_creat']));

    // The start of a project can be planned ahead: the milestone is only reached once the day has come
    $startDate = reedcrm_lifecycle_date($project, 'dateo');
    $steps[]   = reedcrm_lifecycle_step('start', $langs->transnoentities($captions['start']), $startDate, 'fa-play', [], reedcrm_lifecycle_deadline_state($startDate, true));

    // A document milestone is only read, hence only shown, when the module of the document is enabled
    $documentPictos = ['proposal' => 'fa-file-alt', 'signature' => 'fa-file-signature', 'order' => 'fa-shopping-cart', 'billing' => 'fa-file-invoice-dollar'];
    foreach ($documentPictos as $stepKey => $picto) {
        if (isset($project['documents'][$stepKey])) {
            $documents = $project['documents'][$stepKey];
            $steps[]   = reedcrm_lifecycle_step($stepKey, $langs->transnoentities($captions[$stepKey]), $documents['first'], $picto, reedcrm_lifecycle_count_detail($documents['count']));
        }
    }

    // Planned end turns red once the date is gone while the project is still open
    $endDate = reedcrm_lifecycle_date($project, 'datee');
    $steps[] = reedcrm_lifecycle_step('end', $langs->transnoentities($captions['end']), $endDate, 'fa-hourglass-half', [], reedcrm_lifecycle_deadline_state($endDate, $isClosed));

    $steps[] = reedcrm_lifecycle_step('closing', $langs->transnoentities($captions['closing']), $isClosed ? reedcrm_lifecycle_date($project, 'date_close') : null, 'fa-flag-checkered', reedcrm_lifecycle_user_detail((int) $project['fk_user_close']));

    return $steps;
}

/**
 * Read what the timeline of several projects is built from
 *
 * The opportunity list prints one timeline per row: each kind of document is read once for the
 * projects of the whole page, which keeps the cost of the column flat whatever the number of rows
 *
 * @param  int[] $projectIds Ids of the projects
 * @return array             Row of each project indexed by its id, the summaries of its documents
 *                           under 'documents', indexed by the milestone they stand for
 */
function reedcrm_lifecycle_fetch_projects(array $projectIds): array
{
    global $db;

    $projectIds = array_filter(array_map('intval', $projectIds));
    if (empty($projectIds)) {
        return [];
    }

    $sql   = 'SELECT rowid, datec, dateo, datee, date_close, fk_statut, fk_user_creat, fk_user_close';
    $sql  .= ' FROM ' . MAIN_DB_PREFIX . 'projet';
    $sql  .= ' WHERE rowid IN (' . implode(',', $projectIds) . ')';
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_lifecycle_fetch_projects ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $projects = [];
    while ($row = $db->fetch_array($resql)) {
        $row['documents']              = [];
        $projects[(int) $row['rowid']] = $row;
    }
    $db->free($resql);

    $documents = [];
    if (isModEnabled('propal')) {
        // Neither the project card nor the list load the proposal class on their own, and its statuses are read below
        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';

        $documents['proposal']  = reedcrm_lifecycle_project_summaries('propal', $projectIds, 'datec');
        $documents['signature'] = reedcrm_lifecycle_project_summaries('propal', $projectIds, 'date_signature', 'fk_statut IN (' . Propal::STATUS_SIGNED . ', ' . Propal::STATUS_BILLED . ')');
    }
    if (isModEnabled('order')) {
        $documents['order'] = reedcrm_lifecycle_project_summaries('commande', $projectIds, 'date_creation');
    }
    if (isModEnabled('invoice')) {
        $documents['billing'] = reedcrm_lifecycle_project_summaries('facture', $projectIds, 'datec');
    }

    foreach (array_keys($projects) as $projectId) {
        foreach ($documents as $stepKey => $summaries) {
            $projects[$projectId]['documents'][$stepKey] = $summaries[$projectId] ?? ['count' => 0, 'first' => null, 'last' => null];
        }
    }

    return $projects;
}

/**
 * SQL expression giving the rank of the last milestone a project reached, -1 when it reached none
 *
 * It mirrors reedcrm_build_project_lifecycle_steps() and reedcrm_lifecycle_mark_current(), so the
 * opportunity list sorts and filters its rows on the very step their timeline flags as current:
 * the milestones are checked from the last one back to the first one, the first one reached wins
 *
 * @param  string $alias Alias of the project table in the query
 * @return string        SQL expression, between brackets
 */
function reedcrm_lifecycle_project_current_step_sql(string $alias): string
{
    global $db;

    $ranks    = array_flip(array_keys(REEDCRM_LIFECYCLE_PROJECT_STEPS));
    $now      = "'" . $db->idate(dol_now()) . "'";
    $isClosed = $alias . '.fk_statut = ' . Project::STATUS_CLOSED;

    $conditions            = [];
    $conditions['closing'] = $isClosed . ' AND ' . $alias . '.date_close IS NOT NULL';
    $conditions['end']     = $isClosed . ' AND ' . $alias . '.datee <= ' . $now;
    if (isModEnabled('invoice')) {
        $conditions['billing'] = reedcrm_lifecycle_project_document_sql('facture', $alias, 'datec');
    }
    if (isModEnabled('order')) {
        $conditions['order'] = reedcrm_lifecycle_project_document_sql('commande', $alias, 'date_creation');
    }
    if (isModEnabled('propal')) {
        require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';

        $conditions['signature'] = reedcrm_lifecycle_project_document_sql('propal', $alias, 'date_signature', 'fk_statut IN (' . Propal::STATUS_SIGNED . ', ' . Propal::STATUS_BILLED . ')');
        $conditions['proposal']  = reedcrm_lifecycle_project_document_sql('propal', $alias, 'datec');
    }
    // A start or an end still ahead is not reached yet, see reedcrm_lifecycle_deadline_state()
    $conditions['start']    = $alias . '.dateo <= ' . $now;
    $conditions['creation'] = $alias . '.datec IS NOT NULL';

    $sql = '(CASE';
    foreach ($conditions as $stepKey => $condition) {
        $sql .= ' WHEN ' . $condition . ' THEN ' . $ranks[$stepKey];
    }
    $sql .= ' ELSE -1 END)';

    return $sql;
}

/**
 * SQL condition true when a project carries a document dating a milestone
 *
 * @param  string $table      Table of the documents, without the Dolibarr prefix
 * @param  string $alias      Alias of the project table in the query
 * @param  string $dateField  Column of the documents dating the milestone
 * @param  string $extraWhere Additional condition on that table, without the leading AND
 * @return string             SQL condition
 */
function reedcrm_lifecycle_project_document_sql(string $table, string $alias, string $dateField, string $extraWhere = ''): string
{
    $sql  = 'EXISTS (SELECT 1 FROM ' . MAIN_DB_PREFIX . $table . ' AS lcd';
    $sql .= ' WHERE lcd.fk_projet = ' . $alias . '.rowid';
    $sql .= ' AND lcd.entity IN (' . getEntity($table) . ')';
    $sql .= ' AND lcd.' . $dateField . ' IS NOT NULL';
    if (!empty($extraWhere)) {
        $sql .= ' AND lcd.' . $extraWhere;
    }
    $sql .= ')';

    return $sql;
}

/**
 * Captions of the project milestones the opportunity list can be filtered on, indexed by their rank
 *
 * @return string[] Translation keys of the captions
 */
function reedcrm_lifecycle_project_step_labels(): array
{
    // A document milestone is left out with the module of its document, as the timeline does
    $modules = ['proposal' => 'propal', 'signature' => 'propal', 'order' => 'order', 'billing' => 'invoice'];

    $labels = [];
    foreach (array_keys(REEDCRM_LIFECYCLE_PROJECT_STEPS) as $rank => $stepKey) {
        if (!isset($modules[$stepKey]) || isModEnabled($modules[$stepKey])) {
            $labels[$rank] = REEDCRM_LIFECYCLE_PROJECT_STEPS[$stepKey];
        }
    }

    return $labels;
}

/**
 * Build one step of a lifecycle timeline
 *
 * @param  string   $key    Stable identifier of the milestone
 * @param  string   $label  Short caption printed under the dot
 * @param  int|null $date   Timestamp of the milestone, null when it has not happened
 * @param  string   $picto  Font Awesome class of the dot
 * @param  string[] $detail Lines of plain text shown in the tooltip
 * @param  string   $state  Forced state, computed from the date when left empty
 * @return array            The step
 */
function reedcrm_lifecycle_step(string $key, string $label, ?int $date, string $picto, array $detail = [], string $state = ''): array
{
    if (empty($state)) {
        $state = empty($date) ? 'todo' : 'done';
    }

    return [
        'key'     => $key,
        'label'   => $label,
        'date'    => $date,
        'state'   => $state,
        'current' => false,
        'picto'   => $picto,
        'detail'  => $detail
    ];
}

/**
 * Build the sending step shared by the proposals, the orders and the invoices
 *
 * @param  array $sent Summary returned by reedcrm_lifecycle_sent_summary()
 * @return array       The step
 */
function reedcrm_lifecycle_sending_step(array $sent): array
{
    global $langs;

    // The dot carries the first sending: the following ones only show up in the tooltip
    $detail = [];
    if ($sent['count'] > 1) {
        $detail[] = $langs->transnoentities('LifecycleTooltipSentCount', $sent['count']);
        $detail[] = $langs->transnoentities('LifecycleTooltipLastSending', dol_print_date($sent['last'], 'dayhour', 'tzuser'));
    }

    return reedcrm_lifecycle_step('sending', $langs->transnoentities('LifecycleStepSending'), $sent['first'], 'fa-paper-plane', $detail);
}

/**
 * Read the ReedCRM commercial status of a proposal, along with the pipeline it belongs to
 *
 * The commstatus extrafield points at the c_commercial_status dictionary, whose position column
 * orders the pipeline. Nothing records when the status changed, so the milestones it feeds are
 * flagged as reached or not, without a date
 *
 * @param  int   $propalId Id of the proposal
 * @return array           label and position of the current status, positions of the whole pipeline
 */
function reedcrm_lifecycle_commercial_status(int $propalId): array
{
    global $conf, $db;

    $status = ['label' => '', 'position' => null, 'pipeline' => []];

    // The whole pipeline is read at once, each row carrying the status the proposal sits on:
    // the extrafield stores the rowid of the dictionary entry, as the sellist declares it
    $sql  = 'SELECT cs.rowid, cs.ref, cs.label, cs.position, pe.commstatus AS currentid';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'c_commercial_status AS cs';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'propal_extrafields AS pe ON pe.fk_object = ' . $propalId;
    $sql .= " WHERE cs.element_type = 'propal'";
    $sql .= ' AND cs.entity IN (0, ' . ((int) $conf->entity) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_lifecycle_commercial_status ' . $db->lasterror(), LOG_ERR);
        return $status;
    }
    while ($obj = $db->fetch_object($resql)) {
        $status['pipeline'][$obj->ref] = (int) $obj->position;
        if (!empty($obj->currentid) && (int) $obj->currentid === (int) $obj->rowid) {
            $status['label']    = $obj->label;
            $status['position'] = (int) $obj->position;
        }
    }
    $db->free($resql);

    return $status;
}

/**
 * State of a milestone read from the commercial pipeline
 *
 * @param  array  $status Status returned by reedcrm_lifecycle_commercial_status()
 * @param  string $stepRef Reference of the pipeline entry standing for the milestone
 * @return string          done | todo
 */
function reedcrm_lifecycle_commercial_status_state(array $status, string $stepRef): string
{
    if ($status['position'] === null || !isset($status['pipeline'][$stepRef])) {
        return 'todo';
    }

    return ($status['position'] >= $status['pipeline'][$stepRef]) ? 'done' : 'todo';
}

/**
 * Tooltip line naming the commercial status behind a milestone
 *
 * @param  array    $status Status returned by reedcrm_lifecycle_commercial_status()
 * @return string[]         Lines of the tooltip, empty when no status is set
 */
function reedcrm_lifecycle_commercial_status_detail(array $status): array
{
    global $langs;

    if (empty($status['label'])) {
        return [];
    }

    return [
        $langs->transnoentities('LifecycleTooltipCommercialStatus', $langs->transnoentities($status['label'])),
        $langs->transnoentities('LifecycleTooltipNoStatusDate')
    ];
}

/**
 * Build the step counting the follow-ups run from ReedCRM
 *
 * ReedCRM tags its follow-up events with a dedicated category and hangs them on the opportunity,
 * so they are read from the project of the proposal, and from its customer when it carries none
 *
 * @param  int   $projectId Id of the project of the object, 0 when it has none
 * @param  int   $socId     Id of the customer
 * @return array            The step
 */
function reedcrm_lifecycle_relaunch_step(int $projectId, int $socId): array
{
    global $langs;

    $summary = ['count' => 0, 'first' => null, 'last' => null];
    $tagId   = getDolGlobalInt('REEDCRM_ACTIONCOMM_COMMERCIAL_RELAUNCH_TAG');

    if (isModEnabled('agenda') && $tagId > 0 && ($projectId > 0 || $socId > 0)) {
        $sql  = 'SELECT COUNT(a.id) AS nb, MIN(a.datep) AS datemin, MAX(a.datep) AS datemax';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_actioncomm AS c ON c.fk_actioncomm = a.id';
        $sql .= ' WHERE c.fk_categorie = ' . $tagId;
        $sql .= ' AND a.entity IN (' . getEntity('agenda') . ')';
        $sql .= ($projectId > 0) ? ' AND a.fk_project = ' . $projectId : ' AND a.fk_soc = ' . $socId;

        $summary = reedcrm_lifecycle_read_summary($sql);
    }

    $detail = [];
    if ($summary['count'] > 0) {
        $detail[] = $langs->transnoentities('LifecycleTooltipRelaunchCount', $summary['count']);
        if ($summary['count'] > 1) {
            $detail[] = $langs->transnoentities('LifecycleTooltipFirstRelaunch', dol_print_date($summary['first'], 'dayhour', 'tzuser'));
        }
    }

    // The dot carries the latest follow-up: that is the one telling how cold the deal has gone
    return reedcrm_lifecycle_step('relaunch', $langs->transnoentities('LifecycleStepRelaunch'), $summary['last'], 'fa-bell', $detail);
}

/**
 * State of a step holding a deadline
 *
 * A deadline still ahead of us is a milestone waiting to happen, never one already reached:
 * it only turns red when the day is gone while the object still had to answer for it
 *
 * @param  int|null $date   Deadline
 * @param  bool     $isOver Whether the object already left the state the deadline was guarding
 * @return string           done | todo | late
 */
function reedcrm_lifecycle_deadline_state(?int $date, bool $isOver): string
{
    if (empty($date) || $date > dol_now()) {
        return 'todo';
    }

    return $isOver ? 'done' : 'late';
}

/**
 * Flag the last milestone actually reached, the one the object sits on right now
 *
 * @param  array $steps Ordered steps
 * @return array        Same steps, one of them flagged
 */
function reedcrm_lifecycle_mark_current(array $steps): array
{
    $currentIndex = -1;
    foreach ($steps as $index => $step) {
        if (in_array($step['state'], ['done', 'ko']) && !empty($step['date'])) {
            $currentIndex = $index;
        }
    }
    if ($currentIndex >= 0) {
        $steps[$currentIndex]['current'] = true;
    }

    return $steps;
}

/**
 * Read the lifecycle columns of one object
 *
 * fetch() leaves out part of the closing dates of the core objects and info() would overwrite
 * properties the card page still needs: the row is read on its own instead
 *
 * @param  string   $table   Table of the object, without the Dolibarr prefix
 * @param  int      $rowId   Id of the object
 * @param  string[] $columns Columns to read
 * @return array             The row, empty on error
 */
function reedcrm_lifecycle_fetch_row(string $table, int $rowId, array $columns): array
{
    global $db;

    $sql   = 'SELECT ' . implode(', ', $columns) . ' FROM ' . MAIN_DB_PREFIX . $table . ' WHERE rowid = ' . $rowId;
    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_lifecycle_fetch_row ' . $db->lasterror(), LOG_ERR);
        return [];
    }
    $row = $db->fetch_array($resql);
    $db->free($resql);

    return is_array($row) ? $row : [];
}

/**
 * Timestamp of one date column of a row read by reedcrm_lifecycle_fetch_row()
 *
 * @param  array  $row   The row
 * @param  string $field Column holding the date
 * @return int|null      Timestamp, null when the column is empty
 */
function reedcrm_lifecycle_date(array $row, string $field): ?int
{
    global $db;

    if (empty($row[$field])) {
        return null;
    }
    $timestamp = $db->jdate($row[$field]);

    return empty($timestamp) ? null : (int) $timestamp;
}

/**
 * Summary of the mails carrying the object, taken from the events the agenda records on its own
 *
 * ActionComm::create() stores the orders and the invoices as order and invoice, not under the element
 * of their object: the type to pass is the one the agenda keeps, never $object->element
 *
 * @param  string $elementType Element type stored by the agenda (propal, order, invoice)
 * @param  int    $objectId    Id of the object
 * @return array               count, first and last sending
 */
function reedcrm_lifecycle_sent_summary(string $elementType, int $objectId): array
{
    global $db;

    if (!isModEnabled('agenda')) {
        return ['count' => 0, 'first' => null, 'last' => null];
    }

    $sql  = 'SELECT COUNT(a.id) AS nb, MIN(a.datep) AS datemin, MAX(a.datep) AS datemax';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
    $sql .= " WHERE a.elementtype = '" . $db->escape($elementType) . "'";
    $sql .= ' AND a.fk_element = ' . $objectId;
    $sql .= " AND a.code LIKE '%SENTBYMAIL'";
    $sql .= ' AND a.entity IN (' . getEntity('agenda') . ')';

    return reedcrm_lifecycle_read_summary($sql);
}

/**
 * Summary of the documents built from the object, read through the link table
 *
 * @param  string $sourceType  Element type of the object
 * @param  int    $sourceId    Id of the object
 * @param  string $targetType  Element type of the linked documents
 * @param  string $targetTable Table of the linked documents, without the Dolibarr prefix
 * @param  string $dateField   Column of the linked documents holding the date to read
 * @return array               count, first and last document
 */
function reedcrm_lifecycle_linked_summary(string $sourceType, int $sourceId, string $targetType, string $targetTable, string $dateField): array
{
    global $db;

    $sql  = 'SELECT COUNT(t.rowid) AS nb, MIN(t.' . $dateField . ') AS datemin, MAX(t.' . $dateField . ') AS datemax';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_element AS ee';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . $targetTable . ' AS t ON t.rowid = ee.fk_target';
    $sql .= " WHERE ee.sourcetype = '" . $db->escape($sourceType) . "'";
    $sql .= ' AND ee.fk_source = ' . $sourceId;
    $sql .= " AND ee.targettype = '" . $db->escape($targetType) . "'";
    $sql .= ' AND t.entity IN (' . getEntity($targetTable) . ')';

    return reedcrm_lifecycle_read_summary($sql);
}

/**
 * Summary of the documents attached to each of several projects
 *
 * @param  string $table      Table of the documents, without the Dolibarr prefix
 * @param  int[]  $projectIds Ids of the projects
 * @param  string $dateField  Column holding the date to read
 * @param  string $extraWhere Additional condition on that table, without the leading AND
 * @return array              count, first and last document, indexed by project id; a project
 *                            carrying no document is left out
 */
function reedcrm_lifecycle_project_summaries(string $table, array $projectIds, string $dateField, string $extraWhere = ''): array
{
    global $db;

    $sql  = 'SELECT t.fk_projet, COUNT(t.rowid) AS nb, MIN(t.' . $dateField . ') AS datemin, MAX(t.' . $dateField . ') AS datemax';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . $table . ' AS t';
    $sql .= ' WHERE t.fk_projet IN (' . implode(',', array_map('intval', $projectIds)) . ')';
    $sql .= ' AND t.entity IN (' . getEntity($table) . ')';
    if (!empty($extraWhere)) {
        $sql .= ' AND t.' . $extraWhere;
    }
    $sql .= ' GROUP BY t.fk_projet';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_lifecycle_project_summaries ' . $db->lasterror(), LOG_ERR);
        return [];
    }

    $summaries = [];
    while ($obj = $db->fetch_object($resql)) {
        $summaries[(int) $obj->fk_projet] = reedcrm_lifecycle_summary_from_row($obj);
    }
    $db->free($resql);

    return $summaries;
}

/**
 * Summary of the payments received on an invoice
 *
 * @param  int   $invoiceId Id of the invoice
 * @return array            count, first and last payment, and amount received
 */
function reedcrm_lifecycle_payment_summary(int $invoiceId): array
{
    global $db;

    $summary = ['count' => 0, 'first' => null, 'last' => null, 'amount' => 0];

    $sql  = 'SELECT COUNT(pf.rowid) AS nb, MIN(p.datep) AS datemin, MAX(p.datep) AS datemax, SUM(pf.amount) AS total';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'paiement_facture AS pf';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'paiement AS p ON p.rowid = pf.fk_paiement';
    $sql .= ' WHERE pf.fk_facture = ' . $invoiceId;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_lifecycle_payment_summary ' . $db->lasterror(), LOG_ERR);
        return $summary;
    }
    $obj = $db->fetch_object($resql);
    $db->free($resql);

    if (empty($obj) || empty($obj->nb)) {
        return $summary;
    }

    return [
        'count'  => (int) $obj->nb,
        'first'  => empty($obj->datemin) ? null : (int) $db->jdate($obj->datemin),
        'last'   => empty($obj->datemax) ? null : (int) $db->jdate($obj->datemax),
        'amount' => (float) $obj->total
    ];
}

/**
 * Run a count/min/max query built by the summary helpers
 *
 * @param  string $sql Query selecting nb, datemin and datemax
 * @return array       count, first and last row
 */
function reedcrm_lifecycle_read_summary(string $sql): array
{
    global $db;

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrm_lifecycle_read_summary ' . $db->lasterror(), LOG_ERR);
        return ['count' => 0, 'first' => null, 'last' => null];
    }
    $obj = $db->fetch_object($resql);
    $db->free($resql);

    return reedcrm_lifecycle_summary_from_row($obj);
}

/**
 * Turn one row selecting nb, datemin and datemax into a summary
 *
 * @param  object|false|null $obj Row read from the database, false or null when there is none
 * @return array                  count, first and last row
 */
function reedcrm_lifecycle_summary_from_row($obj): array
{
    global $db;

    if (empty($obj) || empty($obj->nb)) {
        return ['count' => 0, 'first' => null, 'last' => null];
    }

    return [
        'count' => (int) $obj->nb,
        'first' => empty($obj->datemin) ? null : (int) $db->jdate($obj->datemin),
        'last'  => empty($obj->datemax) ? null : (int) $db->jdate($obj->datemax)
    ];
}

/**
 * Tooltip line naming the user behind a milestone
 *
 * @param  int      $userId Id of the user
 * @return string[]         One line, empty when the milestone carries no user
 */
function reedcrm_lifecycle_user_detail(int $userId): array
{
    global $db, $langs;

    // The opportunity list prints a timeline per row, mostly carried by the same handful of users
    static $userNames = [];

    if ($userId <= 0) {
        return [];
    }

    if (!array_key_exists($userId, $userNames)) {
        $milestoneUser      = new User($db);
        $userNames[$userId] =($milestoneUser->fetch($userId) > 0) ? $milestoneUser->getFullName($langs) : '';
    }
    if ($userNames[$userId] === '') {
        return [];
    }

    return [$langs->transnoentities('LifecycleTooltipBy', $userNames[$userId])];
}

/**
 * Tooltip line counting the documents behind a milestone
 *
 * @param  int      $count Number of documents
 * @return string[]        One line, empty when a single document stands behind the milestone
 */
function reedcrm_lifecycle_count_detail(int $count): array
{
    global $langs;

    if ($count <= 1) {
        return [];
    }

    return [$langs->transnoentities('LifecycleTooltipLinkedCount', $count)];
}

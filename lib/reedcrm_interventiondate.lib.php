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
 * \file    lib/reedcrm_interventiondate.lib.php
 * \ingroup reedcrm
 * \brief   Library files with common functions for the intervention dates carried by the service lines.
 */

/**
 * Colors handed out to the intervenants, so the same user keeps the same color across the calendar.
 */
const REEDCRM_INTERVENTION_USER_COLORS = [
    '#63ACC9', '#E9AD4F', '#7FB77E', '#D96C6C', '#9B8ACB', '#4A9D9C',
    '#C97BA5', '#8FA8C8', '#B5843F', '#5F9E5F', '#C25E5E', '#7D7DB5'
];

/**
 * Is the intervention date feature turned on ?
 *
 * @return bool
 */
function reedcrmInterventionIsEnabled(): bool
{
    return isModEnabled('reedcrm') && getDolGlobalInt('REEDCRM_INTERVENTION_DATE_ENABLED') > 0;
}

/**
 * Default length of an intervention, in minutes.
 *
 * @return int
 */
function reedcrmInterventionDefaultDuration(): int
{
    $duration = getDolGlobalInt('REEDCRM_INTERVENTION_DATE_DEFAULT_DURATION', 60);

    return $duration > 0 ? $duration : 60;
}

/**
 * Oldest proposal the feature looks at : everything dated before is out of scope, so the backlog
 * of the past years does not land in the interventions to plan.
 *
 * @return int Timestamp of the first day taken into account, 0 when no limit is set
 */
function reedcrmInterventionMinPropalDate(): int
{
    $limit = getDolGlobalString('REEDCRM_INTERVENTION_DATE_FROM');
    if (empty($limit) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $limit, $parts)) {
        return 0;
    }

    return (int) dol_mktime(0, 0, 0, (int) $parts[2], (int) $parts[3], (int) $parts[1], 'tzserver');
}

/**
 * Tag (product category) a service must carry to be planned. 0 when every service qualifies.
 *
 * @return int Rowid of the category
 */
function reedcrmInterventionProductTagID(): int
{
    return getDolGlobalInt('REEDCRM_INTERVENTION_DATE_PRODUCT_TAG');
}

/**
 * Does this service line fall in the scope ? Only the lines linked to a product carrying the
 * configured tag are planned : no tag chosen means nothing is planned, and a free line carries
 * no product, so it carries no tag either.
 *
 * @param  int  $productID Product of the line, 0 for a free line
 * @return bool
 */
function reedcrmInterventionProductHasTag(int $productID): bool
{
    global $db;

    $tagID = reedcrmInterventionProductTagID();
    if ($tagID <= 0 || $productID <= 0) {
        return false;
    }

    // The proposal card asks the question for every one of its lines
    static $tagged = [];
    if (isset($tagged[$productID])) {
        return $tagged[$productID];
    }

    $sql  = 'SELECT fk_product FROM ' . MAIN_DB_PREFIX . 'categorie_product';
    $sql .= ' WHERE fk_categorie = ' . $tagID . ' AND fk_product = ' . $productID;

    $resql             = $db->query($sql);
    $tagged[$productID] = (bool) ($resql && $db->num_rows($resql) > 0);

    return $tagged[$productID];
}

/**
 * Color of an intervenant.
 *
 * @param  int    $userID ID of the intervenant, 0 for an unassigned intervention
 * @return string         Hexadecimal color
 */
function reedcrmInterventionUserColor(int $userID): string
{
    if ($userID <= 0) {
        return '#B0B8C1';
    }

    return REEDCRM_INTERVENTION_USER_COLORS[$userID % count(REEDCRM_INTERVENTION_USER_COLORS)];
}

/**
 * Initials of an intervenant, shown in the colored badge of a calendar chip.
 *
 * @param  string $label Full name of the intervenant, empty when nobody is assigned
 * @return string        One or two letters, '?' when nobody is assigned
 */
function reedcrmInterventionUserInitials(string $label): string
{
    $label = trim($label);
    if ($label === '') {
        return '?';
    }

    $initials = '';
    foreach (preg_split('/[\s\-]+/', $label) as $word) {
        if ($word === '') {
            continue;
        }
        $initials .= dol_strtoupper(dol_substr($word, 0, 1));
        if (dol_strlen($initials) >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : '?';
}

/**
 * Addresses worth suggesting for an intervention : the one of the third party, then the ReedCRM
 * addresses attached to the third party or to the project of the document.
 *
 * @param  CommonObject $parent Document the service line belongs to
 * @return string[]             Addresses on one line each, without duplicates
 */
function reedcrmInterventionGetLocationSuggestions(CommonObject $parent): array
{
    global $conf, $db;

    $suggestions = [];

    $flatten = static function (string $address, string $zip, string $town, string $name = ''): string {
        $line = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $address . ' ' . $zip . ' ' . $town)));
        if ($line === '') {
            return '';
        }

        return $name !== '' ? $name . ' - ' . $line : $line;
    };

    if (!empty($parent->socid)) {
        require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

        $thirdparty = new Societe($db);
        if ($thirdparty->fetch((int) $parent->socid) > 0) {
            $line = $flatten((string) $thirdparty->address, (string) $thirdparty->zip, (string) $thirdparty->town);
            if ($line !== '') {
                $suggestions[] = $line;
            }
        }
    }

    $conditions = [];
    if (!empty($parent->socid)) {
        $conditions[] = "(element_type = 'thirdparty' AND element_id = " . (int) $parent->socid . ')';
    }
    if (!empty($parent->fk_project)) {
        $conditions[] = "(element_type = 'project' AND element_id = " . (int) $parent->fk_project . ')';
    }

    if (!empty($conditions)) {
        $sql  = 'SELECT name, address, zip, town FROM ' . MAIN_DB_PREFIX . 'reedcrm_address';
        $sql .= ' WHERE status = 1 AND entity IN (' . getEntity('reedcrm_address') . ')';
        $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        $sql .= ' ORDER BY name ASC';

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $line = $flatten((string) $obj->address, (string) $obj->zip, (string) $obj->town, (string) $obj->name);
                if ($line !== '') {
                    $suggestions[] = $line;
                }
            }
        }
    }

    return array_values(array_unique($suggestions));
}

/**
 * Avatar of the intervenant : the photo of the user when there is one, else his initials on his own color.
 *
 * @param  object $row Row returned by reedcrmInterventionFetchRows()
 * @return string      HTML of the avatar
 */
function reedcrmInterventionUserAvatar(object $row): string
{
    global $db;

    if ((int) $row->fk_user_intervenant <= 0) {
        return '<span class="reedcrm-chip-avatar reedcrm-chip-avatar-none">?</span>';
    }

    if (!empty($row->user_photo)) {
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
        require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

        $userTmp         = new User($db);
        $userTmp->id     = (int) $row->fk_user_intervenant;
        $userTmp->entity = (int) $row->user_entity;
        $userTmp->photo  = $row->user_photo;
        $userTmp->gender = $row->user_gender;

        // No link to the full size : the whole chip is already a click target
        return Form::showphoto('userphoto', $userTmp, 0, 24, 0, 'reedcrm-chip-photo', 'mini', 0);
    }

    return '<span class="reedcrm-chip-avatar" style="background:' . dol_escape_htmltag($row->color) . '">'
        . dol_escape_htmltag(reedcrmInterventionUserInitials($row->user_label)) . '</span>';
}

/**
 * Active users of the entity, the ones an intervention can be handed to.
 *
 * @return array [user id => full name]
 */
function reedcrmInterventionGetUsers(): array
{
    global $db;

    $users = [];

    $sql  = 'SELECT u.rowid, u.lastname, u.firstname FROM ' . MAIN_DB_PREFIX . 'user as u';
    $sql .= ' WHERE u.statut = 1 AND u.entity IN (' . getEntity('user') . ')';
    $sql .= ' ORDER BY u.lastname ASC, u.firstname ASC';

    $resql = $db->query($sql);
    if (!$resql) {
        return $users;
    }

    while ($obj = $db->fetch_object($resql)) {
        $users[(int) $obj->rowid] = dolGetFirstLastname($obj->firstname, $obj->lastname);
    }

    return $users;
}

/**
 * Label shown for a service line : its own label, else the first line of its description.
 *
 * @param  object $line Line object or row read from llx_propaldet
 * @return string       Label, never empty
 */
function reedcrmInterventionGetLineLabel(object $line): string
{
    global $langs;

    if (!empty($line->label)) {
        return (string) $line->label;
    }
    if (!empty($line->product_label)) {
        return (string) $line->product_label;
    }
    if (!empty($line->description)) {
        $description = dol_string_nohtmltag((string) $line->description, 1);
        $firstLine   = trim(strtok($description, "\n"));
        if (!empty($firstLine)) {
            return dol_trunc($firstLine, 80);
        }
    }

    return $langs->transnoentities('Service');
}

/**
 * Intervention dates matching the filters, with everything the calendar needs to draw a chip.
 *
 * @param  array $filters Accepted keys : date_start, date_end (timestamps), user_ids (array),
 *                        socid, status (-1 for every one), propal_status (-2 for every one)
 * @return array          Rows ordered by date then by intervenant
 */
function reedcrmInterventionFetchRows(array $filters): array
{
    global $db;

    $rows = [];

    $sql  = 'SELECT i.rowid, i.position, i.date_intervention, i.duration, i.fk_user_intervenant, i.fk_actioncomm,';
    $sql .= ' i.location, i.note, i.status, i.element_id, i.fk_element_line,';
    $sql .= ' pd.label, pd.description, pd.qty, prod.label as product_label,';
    $sql .= ' p.ref as propal_ref, p.fk_statut as propal_status, p.fk_soc, p.fk_projet,';
    $sql .= ' s.nom as socname, s.town as soctown,';
    $sql .= ' u.firstname, u.lastname, u.photo as user_photo, u.gender as user_gender, u.entity as user_entity';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'reedcrm_intervention_date as i';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'propaldet as pd ON pd.rowid = i.fk_element_line';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'propal as p ON p.rowid = i.element_id';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as prod ON prod.rowid = pd.fk_product';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = p.fk_soc';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'user as u ON u.rowid = i.fk_user_intervenant';
    $sql .= " WHERE i.element_type = 'propal'";
    $sql .= ' AND i.entity IN (' . getEntity('propal') . ')';

    $sql .= ' AND i.date_intervention IS NOT NULL';
    if (!empty($filters['date_start'])) {
        $sql .= " AND i.date_intervention >= '" . $db->idate($filters['date_start']) . "'";
    }
    if (!empty($filters['date_end'])) {
        $sql .= " AND i.date_intervention <= '" . $db->idate($filters['date_end']) . "'";
    }

    if (!empty($filters['user_ids']) && is_array($filters['user_ids'])) {
        $userIDs = array_map('intval', $filters['user_ids']);
        $noUser  = in_array(-1, $userIDs, true);
        $userIDs = array_values(array_filter($userIDs, static function ($id) { return $id > 0; }));

        if (!empty($userIDs) && $noUser) {
            $sql .= ' AND (i.fk_user_intervenant IN (' . implode(',', $userIDs) . ') OR i.fk_user_intervenant IS NULL OR i.fk_user_intervenant = 0)';
        } elseif (!empty($userIDs)) {
            $sql .= ' AND i.fk_user_intervenant IN (' . implode(',', $userIDs) . ')';
        } elseif ($noUser) {
            $sql .= ' AND (i.fk_user_intervenant IS NULL OR i.fk_user_intervenant = 0)';
        }
    }

    if (!empty($filters['socid'])) {
        $sql .= ' AND p.fk_soc = ' . (int) $filters['socid'];
    }
    if (isset($filters['status']) && $filters['status'] >= 0) {
        $sql .= ' AND i.status = ' . (int) $filters['status'];
    }
    if (isset($filters['propal_status']) && $filters['propal_status'] > -2) {
        $sql .= ' AND p.fk_statut = ' . (int) $filters['propal_status'];
    }

    $sql .= ' ORDER BY i.date_intervention ASC, u.lastname ASC, i.position ASC';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrmInterventionFetchRows ' . $db->lasterror(), LOG_ERR);

        return $rows;
    }

    while ($obj = $db->fetch_object($resql)) {
        $obj->rowid               = (int) $obj->rowid;
        $obj->timestamp           = empty($obj->date_intervention) ? 0 : (int) $db->jdate($obj->date_intervention);
        $obj->fk_user_intervenant = (int) $obj->fk_user_intervenant;
        $obj->line_label          = reedcrmInterventionGetLineLabel($obj);
        $obj->user_label          = $obj->fk_user_intervenant > 0 ? dolGetFirstLastname($obj->firstname, $obj->lastname) : '';
        $obj->color               = reedcrmInterventionUserColor($obj->fk_user_intervenant);
        $rows[]                   = $obj;
    }

    return $rows;
}

/**
 * Service lines still missing intervention dates : the quantity asks for more dates than the ones filled.
 * A line nobody ever opened counts too, its whole quantity is waiting.
 *
 * @param  array $filters Accepted keys : socid, propal_status (-2 for the default selection of statuses)
 * @param  int   $limit   Maximum number of lines returned
 * @return array          Rows carrying the number of dates left to plan
 */
function reedcrmInterventionFetchUnplanned(array $filters, int $limit = 100): array
{
    global $db;

    $rows    = [];
    $maximum = getDolGlobalInt('REEDCRM_INTERVENTION_DATE_MAX_PER_LINE', 24);

    // No tag chosen, no service in scope
    $tagID = reedcrmInterventionProductTagID();
    if ($tagID <= 0) {
        return $rows;
    }

    $sql  = 'SELECT pd.rowid as fk_element_line, pd.label, pd.description, pd.qty,';
    $sql .= ' prod.label as product_label, p.rowid as element_id, p.ref as propal_ref,';
    $sql .= ' p.fk_statut as propal_status, p.fk_soc, s.nom as socname,';
    $sql .= ' COUNT(i.rowid) as planned';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'propaldet as pd';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'propal as p ON p.rowid = pd.fk_propal';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = p.fk_soc';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'product as prod ON prod.rowid = pd.fk_product';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'reedcrm_intervention_date as i ON i.fk_element_line = pd.rowid';
    $sql .= "  AND i.element_type = 'propal' AND i.date_intervention IS NOT NULL";

    // Only the services carrying the configured tag are planned
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_product as cp ON cp.fk_product = pd.fk_product AND cp.fk_categorie = ' . $tagID;

    $sql .= ' WHERE pd.product_type = 1';
    $sql .= ' AND pd.qty > 0';
    $sql .= ' AND p.entity IN (' . getEntity('propal') . ')';

    if (isset($filters['propal_status']) && $filters['propal_status'] > -2) {
        $sql .= ' AND p.fk_statut = ' . (int) $filters['propal_status'];
    } else {
        // A draft or refused proposal has no intervention to plan yet
        $sql .= ' AND p.fk_statut IN (1, 2, 4)';
    }
    if (!empty($filters['socid'])) {
        $sql .= ' AND p.fk_soc = ' . (int) $filters['socid'];
    }

    // The proposals older than the go-live date carry no intervention to plan
    $minPropalDate = reedcrmInterventionMinPropalDate();
    if ($minPropalDate > 0) {
        $sql .= " AND p.datep >= '" . $db->idate($minPropalDate) . "'";
    }

    $sql .= ' GROUP BY pd.rowid, pd.label, pd.description, pd.qty, prod.label, p.rowid, p.ref, p.fk_statut, p.fk_soc, s.nom';
    $sql .= ' HAVING COUNT(i.rowid) < LEAST(CEIL(pd.qty), ' . $maximum . ')';
    $sql .= ' ORDER BY p.ref DESC, pd.rang ASC';
    $sql .= $db->plimit($limit);

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('reedcrmInterventionFetchUnplanned ' . $db->lasterror(), LOG_ERR);

        return $rows;
    }

    while ($obj = $db->fetch_object($resql)) {
        $obj->planned    = (int) $obj->planned;
        $obj->expected   = min((int) ceil(round((float) $obj->qty, 6)), $maximum);
        $obj->remaining  = max(0, $obj->expected - $obj->planned);
        $obj->line_label = reedcrmInterventionGetLineLabel($obj);
        $rows[]          = $obj;
    }

    return $rows;
}

/**
 * Group the rows of reedcrmInterventionFetchRows() by day.
 *
 * @param  array $rows Rows to group
 * @return array       [YYYY-MM-DD => rows of that day]
 */
function reedcrmInterventionGroupByDay(array $rows): array
{
    $days = [];

    foreach ($rows as $row) {
        if (empty($row->timestamp)) {
            continue;
        }
        $days[dol_print_date($row->timestamp, '%Y-%m-%d')][] = $row;
    }

    return $days;
}

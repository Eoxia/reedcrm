<?php
/* Copyright (C) 2021-2025 EVARISK <technique@evarisk.com>
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
 * \file    class/reedcrmdashboard.class.php
 * \ingroup reedcrm
 * \brief   Class file for manage ReedcrmDashboard.
 */

/**
 * Class for ReedcrmDashboard.
 */
class ReedcrmDashboard
{
	/**
	 * @var DoliDB Database handler.
	 */
	public DoliDB $db;

	/**
	 * Constructor.
	 *
	 * @param DoliDB $db Database handler.
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Load dashboard info.
	 *
	 * @param  array|null $moreParams Parameters for load dashboard info
	 * @return array
	 * @throws Exception
	 */
	public function load_dashboard(?array $moreParams = []): array
	{
        // The ticket dashboard is a page of its own: it replaces the content instead of adding to it
        if (!empty($moreParams['LoadTicketDashboard'])) {
            require_once __DIR__ . '/reedcrmticketdashboard.class.php';

            $ticketDashboard = new ReedcrmTicketDashboard($this->db);

            return ['ticket' => $ticketDashboard->load_dashboard()];
        }

        // The sales and direction dashboards are pages of their own as well
        if (!empty($moreParams['LoadSalesDashboard'])) {
            require_once __DIR__ . '/reedcrmsalesdashboard.class.php';

            $salesDashboard = new ReedcrmSalesDashboard($this->db);

            return ['sales' => $salesDashboard->load_dashboard()];
        }

        if (!empty($moreParams['LoadDirectionDashboard'])) {
            require_once __DIR__ . '/reedcrmdirectiondashboard.class.php';

            $directionDashboard = new ReedcrmDirectionDashboard($this->db);

            return ['direction' => $directionDashboard->load_dashboard()];
        }

        // The home page is printed by ActionsReedcrm::saturneIndex, the Saturne dashboard has nothing to add to it
        return [];
	}

    /**
     * Get the upcoming automatic call reminders of the current user.
     *
     * Reminders are the future to-do events created from the ProCard/EventPro
     * "Créer une notification de rappel automatique" checkbox. They are identified by their
     * dedicated category (REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG) and their linked actioncomm_reminder row.
     *
     * @param  int   $limit Maximum number of reminders to return
     * @return array        List of reminder objects (id, label, datep, fk_soc, thirdparty_name)
     */
    public function getUpcomingCallReminders(int $limit = 5): array
    {
        global $user;

        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncommreminder.class.php';

        $reminders = [];

        $reminderTag = getDolGlobalInt('REEDCRM_ACTIONCOMM_CALL_REMINDER_TAG');
        if ($reminderTag <= 0) {
            return $reminders;
        }

        // A reminder with a deadline (an imported one) is due on it rather than on its start date
        $sql  = 'SELECT a.id, a.label, COALESCE(a.datep2, a.datep) AS datep, a.fk_soc, s.nom AS thirdparty_name';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'actioncomm AS a';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'actioncomm_reminder AS r ON r.fk_actioncomm = a.id';
        $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'categorie_actioncomm AS c ON c.fk_actioncomm = a.id';
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe AS s ON s.rowid = a.fk_soc';
        $sql .= ' WHERE c.fk_categorie = ' . ((int) $reminderTag);
        $sql .= ' AND r.fk_user = ' . ((int) $user->id);
        $sql .= ' AND r.status = ' . ActionCommReminder::STATUS_TODO;
        $sql .= " AND COALESCE(a.datep2, a.datep) >= '" . $this->db->idate(dol_now()) . "'";
        $sql .= ' AND a.entity IN (' . getEntity('agenda') . ')';
        $sql .= ' ORDER BY COALESCE(a.datep2, a.datep) ASC';
        $sql .= $this->db->plimit($limit);

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $reminders[] = $obj;
            }
            $this->db->free($resql);
        }

        return $reminders;
    }
}

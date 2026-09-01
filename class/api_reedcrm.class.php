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
 * \file    class/api_reedcrm.class.php
 * \ingroup reedcrm
 * \brief   File for API management of ReedCRM.
 */

use Luracast\Restler\RestException;

require_once __DIR__ . '/../core/modules/modReedCRM.class.php';

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';

require_once DOL_DOCUMENT_ROOT . '/custom/saturne/lib/object.lib.php';

/**
 * API class for orders
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class ReedCRM extends DolibarrApi
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var modReedCRM $mod {@type modReedCRM}
	 */
	public $mod;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $db;
		$this->db  = $db;
		$this->mod = new modReedCRM($this->db);
	}

	/**
	 * Test method to check if the API is working.
	 *
	 * @url GET /test
	 *
	 * @return array
	 *
	 * @throws RestException 401 Not allowed
	 */
	public function test() {
		// This is a test method to check if the API is working.
		return array('status' => 'success', 'message' => 'ReedCRM API is working');
	}

	/**
	 * Create project with ReedCRM form.
	 *
	 * @url POST /createProject
	 *
	 * @return array with project ID and status
	 *
	 * @throws RestException 401 Not allowed if user is not authenticated
	 * @throws RestException 400 Bad Request if required parameters are missing
	 * @throws RestException 500 Internal Server Error if project creation fails
	 */
	public function createProject($request_data = null) {

		global $conf;

        if (!DolibarrApiAccess::$user->hasRight('projet', 'all', 'creer') && !DolibarrApiAccess::$user->hasRight('projet', 'creer')) {
            throw new RestException(403);
        }

		$numberingModules = [
			'project'      => $conf->global->PROJECT_ADDON,
			'project/task' => $conf->global->PROJECT_TASK_ADDON,
		];
		list ($refProjectMod, $refTaskMod) = saturne_require_objects_mod($numberingModules);

		$project = new Project($this->db);

		$project->ref         = $refProjectMod->getNextValue(null, $project);
		$project->title       = $request_data['title'] ?? '';
		$project->description = $request_data['description'] ?? '';
		$project->opp_status = 1;

		$project->date_c            = dol_now();
		$project->date_start        = $request_data['date_start'] ?? dol_now();
		$project->status            = Project::STATUS_VALIDATED;
		$project->usage_opportunity = 1;
		$project->usage_task        = 1;

		$project->array_options = [
			'options_reedcrm_lastname'    => $request_data['lastname'] ?? '',
			'options_reedcrm_firstname'   => $request_data['firstname'] ?? '',
			'options_reedcrm_email'       => $request_data['email'] ?? '',
			'options_projectphone'        => $request_data['phone'] ?? '',
			'options_reedcrm_gravityform' => $request_data['gravityform_url'] ?? ''
		];

		$projectID = $project->create(DolibarrApiAccess::$user);
		if ($projectID > 0) {

			$config = getDolGlobalString('REEDCRM_API_QUICK_CREATIONS');
			$config = json_decode($config, true);
			if (!is_array($config)) {
				$config = [];
			}
			$affectedUserId = !empty($config[DolibarrApiAccess::$user->id]) ? $config[DolibarrApiAccess::$user->id]['user_id'] : DolibarrApiAccess::$user->id;

			$project->add_contact($affectedUserId, 'PROJECTLEADER', 'internal');

			$category = new Categorie($this->db);
			$category->fetch($config[DolibarrApiAccess::$user->id]['tag']);
			$category->add_type($project, Categorie::TYPE_PROJECT);
//
//			$task = new Task($this->db);
//
//			$task->fk_project = $projectID;
//			$task->ref        = $refTaskMod->getNextValue(null, $task);
//			$task->label      = (!empty($conf->global->EASYCRM_TASK_LABEL_VALUE) ? $conf->global->EASYCRM_TASK_LABEL_VALUE : $langs->trans('CommercialFollowUp')) . ' - ' . $project->title;
//			$task->date_c     = dol_now();
//
//			$taskID = $task->create(DolibarrApiAccess::$user);
//			if ($taskID > 0) {
//				$task->add_contact($affectedUserId, 'TASKEXECUTIVE', 'internal');
//				$project->array_options['commtask'] = $taskID;
//				$project->updateExtraField('commtask');
//			}

			return array(
				'project_id' => $projectID,
				'status'     => 'success',
			);

		} else {
			throw new RestException(500, 'Failed to create project');
		}

	}

	/**
	 * Test user rights for project creation.
	 *
	 * @url POST /testRights
	 *
	 * @return array with project ID and status
	 *
	 * @throws RestException 403 Not allowed if user does not have write rights on projects
	 */
	public function testRights() {
		if (!DolibarrApiAccess::$user->hasRight('projet', 'all', 'creer') && !DolibarrApiAccess::$user->hasRight('projet', 'creer')) {
			throw new RestException(403);
		}

		return array(
			'status' => 'success',
		);
	}

	/**
	 * Download the latest audio recording of a project.
	 *
	 * @param int $id ID of project
	 * @return array array with file content
	 *
	 * @url GET /project/{id}/audio/download
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 404 Not found
	 */
	public function downloadProjectAudio($id) {
	    global $conf;
		if (!DolibarrApiAccess::$user->hasRight('projet', 'lire') && !DolibarrApiAccess::$user->hasRight('projet', 'all', 'lire')) {
			throw new RestException(403);
		}

		$project = new Project($this->db);
		$res = $project->fetch($id);
		if ($res <= 0) {
			throw new RestException(404, 'Project not found');
		}

		require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
		$projectDir = $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($project->ref);
		$audioFiles = dol_dir_list($projectDir, 'files', 0, '\.(mp3|ogg|wav|m4a|aac|webm|opus)$', null, 'date', SORT_DESC);
		
		if (empty($audioFiles)) {
		    throw new RestException(404, 'No audio file found for this project');
		}
		
		$lastAudio = $audioFiles[0];
		$filePath = $projectDir . '/' . $lastAudio['name'];
		
		if (!file_exists($filePath)) {
		    throw new RestException(404, 'File not found on disk');
		}
		
		$content = file_get_contents($filePath);
		
		return [
		    'filename' => $lastAudio['name'],
		    'content-type' => dol_mimetype($lastAudio['name']),
		    'filecontent' => base64_encode($content),
		    'size' => $lastAudio['size'],
		    'date' => $lastAudio['date']
		];
	}

	/**
	 * Get the ticket dashboard of the instance
	 *
	 * The dashboard is computed here, on the instance owning the tickets, and comes back ready to be displayed: its
	 * labels are translated and its links carry the absolute URL of this instance. A caller watching several
	 * instances has nothing left to compute, and the logged time comes along, which the standard API cannot give:
	 * the time of a ticket lives on a task only this instance knows how to name.
	 *
	 * @param  string $period   Number of days the flow indicators cover, 0 for the whole history
	 * @param  int    $userid   Id of the assignee the dashboard is restricted to, 0 for every assignee
	 * @param  int    $tickets  Number of ticket rows to return alongside the dashboard, 0 for none, -1 for all
	 * @param  int    $openonly 1 to keep only the tickets still open in those rows
	 * @param  string $lang     Language the labels are rendered in, empty for the language of the instance
	 * @return array            Dashboard of the instance: widgets, graphs, lists, filters, summary and tickets
	 *
	 * @url GET /ticketdashboard
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 501 Ticket module not enabled on the instance
	 */
	public function ticketDashboard($period = '365', $userid = 0, $tickets = 0, $openonly = 0, $lang = '')
	{
		$dashboard = $this->prepareTicketDashboard($period, $userid, $lang);

		$data = $dashboard->load_dashboard();
		if ((int) $tickets != 0) {
			$data['tickets'] = $dashboard->getTicketRows((int) $openonly, max(0, (int) $tickets));
		}
		$data['instance'] = $this->getInstanceInfo();

		return $this->absolutizeUrls($data, $this->getInstanceUrl());
	}

	/**
	 * Get the counters of the ticket dashboard, without the dashboard itself
	 *
	 * A caller comparing several instances puts their counters side by side and only opens the dashboard of one of
	 * them: the summary answers that first screen without rendering any graph.
	 *
	 * @param  string $period Number of days the flow indicators cover, 0 for the whole history
	 * @param  int    $userid Id of the assignee the counters are restricted to, 0 for every assignee
	 * @return array          Counters of the instance and what identifies it
	 *
	 * @url GET /ticketsummary
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 501 Ticket module not enabled on the instance
	 */
	public function ticketSummary($period = '365', $userid = 0, $lang = '')
	{
		$dashboard = $this->prepareTicketDashboard($period, $userid, $lang);

		return [
			'summary'  => $dashboard->load_summary(),
			'instance' => $this->getInstanceInfo()
		];
	}

	/**
	 * Get the tickets the ticket dashboard is built on
	 *
	 * The rows carry the third party, the assignee, the exchanges and the logged time the dashboard already
	 * gathered, so a caller listing the tickets of the instance needs this single call.
	 *
	 * @param  string $period   Number of days the flow indicators cover, 0 for the whole history
	 * @param  int    $userid   Id of the assignee the list is restricted to, 0 for every assignee
	 * @param  int    $openonly 1 to keep only the tickets still open
	 * @param  int    $limit    Maximum number of rows, 0 for every ticket of the period
	 * @param  string $lang     Language the labels are rendered in, empty for the language of the instance
	 * @return array            Tickets of the instance, the most recently created first
	 *
	 * @url GET /tickets
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 501 Ticket module not enabled on the instance
	 */
	public function ticketList($period = '365', $userid = 0, $openonly = 0, $limit = 100, $lang = '')
	{
		$dashboard = $this->prepareTicketDashboard($period, $userid, $lang);
		$dashboard->load_dashboard();

		$data = [
			'tickets'  => $dashboard->getTicketRows((int) $openonly, max(0, (int) $limit)),
			'instance' => $this->getInstanceInfo()
		];

		return $this->absolutizeUrls($data, $this->getInstanceUrl());
	}

	/**
	 * Build the ticket dashboard of the instance, with the environment its widgets expect
	 *
	 * @param  string $period Number of days the flow indicators cover, 0 for the whole history
	 * @param  int    $userid Id of the assignee the dashboard is restricted to, 0 for every assignee
	 * @param  string $lang   Language the labels are rendered in, empty for the language of the instance
	 * @return ReedcrmTicketDashboard
	 *
	 * @throws RestException 403 Not allowed
	 * @throws RestException 501 Ticket module not enabled on the instance
	 */
	private function prepareTicketDashboard($period, $userid, $lang = ''): ReedcrmTicketDashboard
	{
		global $db, $langs;

		if (!DolibarrApiAccess::$user->hasRight('reedcrm', 'read') || !DolibarrApiAccess::$user->hasRight('ticket', 'read')) {
			throw new RestException(403);
		}
		if (!isModEnabled('ticket')) {
			throw new RestException(501, 'Ticket module not enabled on this instance');
		}

		require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
		require_once __DIR__ . '/reedcrmticketdashboard.class.php';

		// The labels belong to the caller reading them, not to the user whose token was used: the language it
		// asks for wins over the one of the instance
		if (!empty($lang) && preg_match('/^[a-z]{2}_[A-Z]{2}$/', $lang)) {
			$langs->setDefaultLang($lang);
		}

		// The dashboard renders translated labels: an API request loads none of the language files a page loads
		$langs->loadLangs(['reedcrm@reedcrm', 'ticket', 'projects', 'companies', 'users', 'other']);

		// The widgets ask the shared form object for their tooltips, an API request has none of the globals of a page
		if (!isset($GLOBALS['form']) || !is_object($GLOBALS['form'])) {
			$GLOBALS['form'] = new Form($db);
		}

		$dashboard = new ReedcrmTicketDashboard($this->db);
		$dashboard->setFilters((string) $period, (int) $userid);
		$dashboard->setUrlRoot($this->getInstanceUrl());

		return $dashboard;
	}

	/**
	 * Get what identifies the instance answering the call
	 *
	 * @return array Name, URL, entity and ReedCRM version of the instance
	 */
	private function getInstanceInfo(): array
	{
		global $conf, $mysoc;

		return [
			'name'    => $mysoc->name,
			'url'     => $this->getInstanceUrl(),
			'entity'  => (int) $conf->entity,
			'version' => getDolGlobalString('REEDCRM_VERSION'),
			'date'    => dol_now()
		];
	}

	/**
	 * Get the URL the instance is reached at from the outside
	 *
	 * DOL_MAIN_URL_ROOT is what the instance knows of itself. An instance published behind a reverse proxy answers
	 * on another URL, which REEDCRM_API_PUBLIC_URL overrides.
	 *
	 * @return string Absolute root URL, without its trailing slash
	 */
	private function getInstanceUrl(): string
	{
		return rtrim(getDolGlobalString('REEDCRM_API_PUBLIC_URL', DOL_MAIN_URL_ROOT), '/');
	}

	/**
	 * Rewrite the links of a payload with the absolute URL of the instance
	 *
	 * The dashboard carries rendered HTML, and every link Dolibarr renders is relative to the instance that
	 * rendered it. Displayed on another instance those links would point at pages of the caller, so they are
	 * rewritten once, here, rather than being guessed at the other end. Only the links Dolibarr rendered are
	 * concerned: the dashboard builds its own from the absolute root it was given.
	 *
	 * @param  mixed  $data         Payload to walk through
	 * @param  string $absoluteRoot Absolute root URL of the instance
	 * @return mixed                Payload whose links are absolute
	 */
	private function absolutizeUrls($data, string $absoluteRoot)
	{
		if (is_array($data)) {
			foreach ($data as $key => $value) {
				$data[$key] = $this->absolutizeUrls($value, $absoluteRoot);
			}

			return $data;
		}

		if (!is_string($data) || $data === '') {
			return $data;
		}

		// The URLs the dashboard builds itself are already absolute: matching on the attribute leaves them alone,
		// since what follows it is then the scheme and not the root of the instance
		return preg_replace('#(href|src)="' . preg_quote(DOL_URL_ROOT, '#') . '/#', '$1="' . $absoluteRoot . '/', $data);
	}

	// END ALL UNIQUE OBJECT API ROUTE

}

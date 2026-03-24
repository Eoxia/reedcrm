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
			'options_reedcrm_lastname' => $request_data['lastname'] ?? '',
			'options_reedcrm_firstname' => $request_data['firstname'] ?? '',
			'options_reedcrm_email' => $request_data['email'] ?? '',
			'options_projectphone' => $request_data['phone'] ?? '',
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

	// END ALL UNIQUE OBJECT API ROUTE

}

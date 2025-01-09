<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 * \file    class/easycrmdashboard.class.php
 * \ingroup easycrm
 * \brief   Class file for manage EasycrmDashboard.
 */

/**
 * Class for EasycrmDashboard.
 */
class EasycrmDashboard
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
	 * @return array
	 * @throws Exception
	 */
	public function load_dashboard(): array
	{
        global $user, $langs;

        $confName        = 'EASYCRM_DASHBOARD_CONFIG';
        $dashboardConfig = json_decode($user->conf->$confName);
        $array = ['propal' => ['graphs' => [], 'disabledGraphs' => []]];

        if (empty($dashboardConfig->graphs->PropalStatusCommRepartition->hide)) {
            $array['propal']['graphs'][] = self::getDataFromExtrafieldsAndDictionary('PropalStatusCommRepartition', 'c_commercial_status');
        } else {
            $array['propal']['disabledGraphs']['PropalStatusCommRepartition'] = $langs->transnoentities('PropalStatusCommRepartition');
        }
        if (empty($dashboardConfig->graphs->PropalRefusalReasonRepartition->hide)) {
            $array['propal']['graphs'][] = self::getDataFromExtrafieldsAndDictionary('PropalRefusalReasonRepartition', 'c_refusal_reason', 'commrefusal');
        } else {
            $array['propal']['disabledGraphs']['PropalRefusalReasonRepartition'] = $langs->transnoentities('PropalRefusalReasonRepartition');
        }

		return $array;
	}

	/**
	 * Get repartition of a dataset according to extrafields and dictionary
	 *
	 * @param  string    $title      Title of the graph
	 * @param  string    $dictionary Dictionary with every data
	 * @param  string    $fieldName  Extrafields where we can set the status
	 * @param  string    $class      Class linked to the extrafields
	 * @return array                 Graph datas (label/color/type/title/data etc..).
	 * @throws Exception
	 */
	public function getDataFromExtrafieldsAndDictionary(string $title, string $dictionary, string $fieldName = 'commstatus', string $class = 'propal'): array
	{
		global $langs;

		require_once DOL_DOCUMENT_ROOT . '/comm/propal/class/propal.class.php';

		$propals = saturne_fetch_all_object_type($class, '', '', 0, 0, [], 'AND', true);

		// Graph Title parameters.
		$array['title'] = $langs->transnoentities($title);
        $array['name']  = $title;
		$array['picto'] = $class;

		// Graph parameters.
		$array['width']   = '100%';
		$array['height']  = 400;
		$array['type']    = 'pie';
		$array['dataset'] = 1;

		$dictionaries = saturne_fetch_dictionary($dictionary);

		$i                  = 0;
		$arrayNbDataByLabel = [];

		$array['labels'][$i] = ['label' => 'N/A', 'color' => '#999999'];

		if (is_array($dictionaries) && !empty($dictionaries)) {
			foreach ($dictionaries as $dictionaryValue) {
                ++$i;
                $arrayNbDataByLabel[$i] = 0;
				$array['labels'][$i]    = [
					'label' => $langs->transnoentities($dictionaryValue->label),
					'color' => $this->getColorRange($i)
				];
			}

			if (is_array($propals) && !empty($propals)) {
				foreach ($propals as $propal) {
                    if (!empty($propal->array_options['options_' . $fieldName])) {
                        $commStatus = $propal->array_options['options_' . $fieldName];
                        $arrayNbDataByLabel[$commStatus]++;
                    } else {
                        $arrayNbDataByLabel[0]++;
                    }
				}
				ksort($arrayNbDataByLabel);
			}
		}

		$array['data'] = $arrayNbDataByLabel;

		return $array;
	}

	/**
	 * get color range for key
	 *
	 * @param  int    $key Key to find in color array
	 * @return string
	 */
	public function getColorRange(int $key): string
	{
		$colorArray = ['#f44336', '#e81e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#cddc39', '#ffeb3b', '#ffc107', '#ff9800', '#ff5722', '#795548', '#9e9e9e', '#607d8b'];
		return $colorArray[$key % count($colorArray)];
	}
}

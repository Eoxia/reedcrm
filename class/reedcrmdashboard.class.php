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
	 * @return array
	 * @throws Exception
	 */
	public function load_dashboard(): array
	{
        global $user, $langs;

        $confName        = 'REEDCRM_DASHBOARD_CONFIG';
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

        $array['project'] = ['lists' => [], 'disabledGraphs' => []];
        if (empty($dashboardConfig->graphs->ProjectOpportunitiesList->hide)) {
            $array['project']['lists'][] = self::getProjectOpportunitiesList();
        } else {
            $array['project']['disabledGraphs']['ProjectOpportunitiesList'] = $langs->transnoentities('ProjectOpportunitiesList');
        }

        if (empty($dashboardConfig->graphs->ProductLastSellList->hide)) {
            $array['product']['lists'][] = self::getProductLastSellList();
        } else {
            $array['product']['disabledGraphs']['ProductLastSellList'] = $langs->transnoentities('ProjectOpportunitiesList');
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

    /**
     * Get controls list by next control
     *
     * @return array    $array Graph datas (label/color/type/title/data etc..)
     * @throws Exception
     */
    public function getProjectOpportunitiesList(): array
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

        // Graph Title parameters
        $array['title'] = $langs->transnoentities('ProjectOpportunitiesList');
        $array['name']  = 'ProjectOpportunitiesList';
        $array['picto'] = '';

        // Graph parameters
        $array['type']   = 'list';
        $array['labels'] = ['Ref', 'Label', 'OpportunityAmount', 'OppPercent', 'Relance', 'LastName', 'FirstName', 'Phone', 'Email', 'ButtonActions'];

        $arrayProjectOpportunitiesList = [];
        $projects                      = saturne_fetch_all_object_type('Project', 'DESC', 't.datec', 5, 0, [], 'AND', true);
        if (!is_array($projects) || empty($projects)) {
            $array['data'] = $arrayProjectOpportunitiesList;
            return $array;
        }

        foreach ($projects as $project) {
            $arrayProjectOpportunitiesList[$project->id]['Ref']['value']               = $project->getNomUrl(1);
            $arrayProjectOpportunitiesList[$project->id]['Ref']['morecss']             = 'left';
            $arrayProjectOpportunitiesList[$project->id]['Label']['value']             = $project->title;
            $arrayProjectOpportunitiesList[$project->id]['OpportunityAmount']['value'] = $project->opp_amount ? price($project->opp_amount, 0, '', 11, -1, -1, 'auto') : '-';
            $arrayProjectOpportunitiesList[$project->id]['OppPercent']['value']        = $project->opp_percent ? $project->opp_percent . ' %' : '-';
            $arrayProjectOpportunitiesList[$project->id]['Relance']['value']           = '';
            $arrayProjectOpportunitiesList[$project->id]['LastName']['value']          = $project->array_options['options_reedcrm_lastname'] ?? '-';
            $arrayProjectOpportunitiesList[$project->id]['FirstName']['value']         = $project->array_options['options_reedcrm_firstname'] ?? '-';
            $arrayProjectOpportunitiesList[$project->id]['Phone']['value']             = $project->array_options['options_projectphone'] ?? '-';
            $arrayProjectOpportunitiesList[$project->id]['Email']['value']             = $project->array_options['options_reedcrm_email'] ?? '-';
            $arrayProjectOpportunitiesList[$project->id]['ButtonActions']['value']     = '';
        }

        $array['data'] = $arrayProjectOpportunitiesList;

        return $array;
    }

    /**
     * Get controls list by next control
     *
     * @return array    $array Graph datas (label/color/type/title/data etc..)
     * @throws Exception
     */
    public function getProductLastSellList(): array
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

        // Graph Title parameters
        $array['title'] = $langs->transnoentities('ProductLastSellList');
        $array['name']  = 'ProductLastSellList';
        $array['picto'] = '';

        // Graph parameters
        $array['type']   = 'list';
        $array['labels'] = ['Ref', 'Label', 'LastSell'];

        $arrayProductLastSellList = [];

        $select  = ', SUM(ps.reel) AS stock_reel,';
        $select .= ' MAX(GREATEST(IFNULL(cmd.last_cmd, ""), IFNULL(fac.last_fac, ""))) AS last_sell';

        $moreSelect = ['last_sell'];

        $join  = ' LEFT JOIN ' . $this->db->prefix() . 'product_stock AS ps
                    ON ps.fk_product = t.rowid';

        $join .= ' LEFT JOIN (
                        SELECT cd.fk_product, MAX(c.date_commande) AS last_cmd
                        FROM ' . $this->db->prefix() . 'commandedet cd
                        INNER JOIN ' . $this->db->prefix() . 'commande c
                            ON c.rowid = cd.fk_commande
                            AND c.fk_statut > 0
                        GROUP BY cd.fk_product
                    ) AS cmd ON cmd.fk_product = t.rowid';

        $join .= ' LEFT JOIN (
                        SELECT fd.fk_product, MAX(f.datef) AS last_fac
                        FROM ' . $this->db->prefix() . 'facturedet fd
                        INNER JOIN ' . $this->db->prefix() . 'facture f
                            ON f.rowid = fd.fk_facture
                            AND f.fk_statut > 0
                        GROUP BY fd.fk_product
                    ) AS fac ON fac.fk_product = t.rowid';

        $months = getDolGlobalInt('REEDCRM_DASHBOARD_PRODUCT_INACTIVE_MONTHS', 6);

        $groupBy  = ' GROUP BY t.rowid';
        $groupBy .= ' HAVING stock_reel > 0';
        $groupBy .= ' AND (last_sell < DATE_SUB(CURDATE(), INTERVAL ' . (int) $months . ' MONTH)
                        OR last_sell IS NULL)';

        $products = saturne_fetch_all_object_type('Product', 'ASC', 'last_sell', 0, 0, [], 'AND', false, true, false, $join, [], $select, $moreSelect, $groupBy);
        if (!is_array($products) || empty($products)) {
            $array['data'] = $arrayProductLastSellList;
            return $array;
        }

        foreach ($products as $product) {
            $arrayProductLastSellList[$product->id]['Ref']['value']      = $product->getNomUrl(1);
            $arrayProductLastSellList[$product->id]['Ref']['morecss']    = 'left';
            $arrayProductLastSellList[$product->id]['Label']['value']    = $product->label;
            $arrayProductLastSellList[$product->id]['LastSell']['value'] = $product->last_sell ? dol_print_date($product->last_sell, 'day') : '-';
        }

        $array['data'] = $arrayProductLastSellList;

        return $array;
    }
}

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
        global $conf, $langs;

        require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

        // Graph Title parameters
        $array['title'] = $langs->transnoentities('ProductLastSellList');
        $array['name']  = 'ProductLastSellList';
        $array['picto'] = '';

        // Graph parameters
        $array['type']   = 'list';
        $array['labels'] = ['Ref', 'Label', 'LastSell', 'StockReel'];

        $arrayProductLastSellList = [];

        $select  = ', SUM(ps.reel) AS stock_reel,';
        $select .= ' MAX(GREATEST(IFNULL(cmd.last_cmd, "1970-01-01"), IFNULL(fac.last_fac, "1970-01-01"))) AS last_sell';

        $moreSelect = ['last_sell', 'stock_reel'];

        $join  = ' LEFT JOIN ' . $this->db->prefix() . 'product_stock AS ps
                    ON ps.fk_product = t.rowid
                    AND ps.fk_entrepot IN (
                        SELECT e.rowid
                        FROM ' . $this->db->prefix() . 'entrepot AS e
                        WHERE e.entity = ' . $conf->entity . '
                    )';

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

        $filter = ['customsql' => 't.tosell = 1'];

        $months = getDolGlobalInt('REEDCRM_DASHBOARD_PRODUCT_INACTIVE_MONTHS', 6);

        $groupBy  = ' GROUP BY t.rowid';
        $groupBy .= ' HAVING stock_reel > 0';
        $groupBy .= ' AND (last_sell < DATE_SUB(CURDATE(), INTERVAL ' . (int) $months . ' MONTH)
                        OR last_sell IS NULL)';

        $products = saturne_fetch_all_object_type('Product', 'ASC', 'last_sell', 0, 0, $filter, 'AND', false, true, false, $join, [], $select, $moreSelect, $groupBy);
        if (!is_array($products) || empty($products)) {
            $array['data'] = $arrayProductLastSellList;
            return $array;
        }

        foreach ($products as $product) {
            $arrayProductLastSellList[$product->id]['Ref']['value']       = $product->getNomUrl(1);
            $arrayProductLastSellList[$product->id]['Ref']['morecss']     = 'left';
            $arrayProductLastSellList[$product->id]['Label']['value']     = $product->label;
            $arrayProductLastSellList[$product->id]['LastSell']['value']  = $product->last_sell != '1970-01-01' ? dol_print_date($product->last_sell, 'day') : '-';
            $arrayProductLastSellList[$product->id]['StockReel']['value'] = $product->stock_reel;
        }

        $array['data'] = $arrayProductLastSellList;

        return $array;
    }

/**
     * Get sales funnel data (pyramide inversée)
     *
     * @param array $moreParams Optional parameters including date_start and date_end for filtering
     * @return array    $array Graph datas (label/color/type/title/data etc..)
     * @throws Exception
     */
    public function getSalesFunnel($dateStartTimestamp = null, $dateEndTimestamp = null): array
    {
        global $langs;

        require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

        // Graph Title parameters
        $array['title'] = $langs->transnoentities('SalesFunnel');
        $array['name']  = 'SalesFunnel';
        $array['picto'] = 'project';

        // Graph parameters - Type 'funnel_custom' pour un rendu HTML personnalisé
        $array['width']      = '100%';
        $array['height']     = 400;
        $array['type']       = 'funnel_custom';
        $array['dataset']    = 1;

        $arrayProjectOpportunitiesList = [];
        $dateFilter = [];

		// Get date filters from request parameters or use provided defaults
        require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
        
        // If timestamps are not provided, try to get them from GET parameters
        if ($dateStartTimestamp === null) {
            $dateStartTimestamp = 0;
            $dateStartDay = GETPOST('salesfunnel_date_startday', 'int');
            $dateStartMonth = GETPOST('salesfunnel_date_startmonth', 'int');
            $dateStartYear = GETPOST('salesfunnel_date_startyear', 'int');
            if ($dateStartDay > 0 && $dateStartMonth > 0 && $dateStartYear > 0) {
                $dateStartTimestamp = dol_mktime(0, 0, 0, $dateStartMonth, $dateStartDay, $dateStartYear);
            }
        }
        
        if ($dateEndTimestamp === null) {
            $dateEndTimestamp = 0;
            $dateEndDay = GETPOST('salesfunnel_date_endday', 'int');
            $dateEndMonth = GETPOST('salesfunnel_date_endmonth', 'int');
            $dateEndYear = GETPOST('salesfunnel_date_endyear', 'int');
            if ($dateEndDay > 0 && $dateEndMonth > 0 && $dateEndYear > 0) {
                $dateEndTimestamp = dol_mktime(23, 59, 59, $dateEndMonth, $dateEndDay, $dateEndYear);
            }
        }

        if ($dateStartTimestamp > 0 || $dateEndTimestamp > 0) {
            global $db; // au cas où

            $customSql = '';

            if ($dateStartTimestamp > 0) {
                // t.datec est un champ DATETIME → on formate le timestamp en datetime SQL
                $customSql .= " AND t.datec >= '" . $db->idate($dateStartTimestamp) . "'";
            }

            if ($dateEndTimestamp > 0) {
                $customSql .= " AND t.datec <= '" . $db->idate($dateEndTimestamp) . "'";
            }

            if (!empty($customSql)) {
                $dateFilter['customsql'] = ltrim($customSql, ' AND ');
            }
        }

//        $dateFilter = [];
        $array['morehtmlright'] = $this->buildSalesFunnelFilters($dateStartTimestamp, $dateEndTimestamp);

        $projects = saturne_fetch_all_object_type('Project', 'DESC', 't.datec', 0, 0, $dateFilter, 'AND', true);
        if (!is_array($projects)) {
            $projects = [];
        }

        $rawProjectOpportunity = 0;
        $ponderatedProjectOpportunity = 0;
        $signedProjectOpportunity = 0;

        if (is_array($projects) && !empty($projects)) {
            foreach ($projects as $project) {
                // Récupérer fk_opp_status (peut être 0, null ou une valeur > 0)
                // Utiliser isset() et !== null pour différencier 0 de null
                $oppStatus = isset($project->fk_opp_status) ? $project->fk_opp_status : (isset($project->opp_status) ? $project->opp_status : null);

                // Si fk_opp_status est null ou non défini, c'est une opportunité brute
                if ($oppStatus === null || $oppStatus === '') {
                    $rawProjectOpportunity ++;
                } else {
                    // fk_opp_status < 6 = opportunités pondérées
                    if ($oppStatus < 6) {
                        $ponderatedProjectOpportunity++;
                    }
                    // fk_opp_status == 6 = projets signés
                    elseif ($oppStatus == 6) {
                        $signedProjectOpportunity ++;
                    }
                    // fk_opp_status == 0 peut aussi être considéré comme brut
                    elseif ($oppStatus == 0) {
                        $rawProjectOpportunity ++;
                    }
                }
            }
        }

        $funnelData = [
            ['label' => 'Opportunités de projet brut', 'value' => $rawProjectOpportunity, 'color' => '#2196F3'],
            ['label' => 'Opportunités de projet pondérés', 'value' => $ponderatedProjectOpportunity, 'color' => '#4CAF50'],
            ['label' => 'Projets signés', 'value' => $signedProjectOpportunity, 'color' => '#FF9800'],
        ];

        $labels = [];
        $data = [];
        $colors = [];
        $maxValue = 0;

        foreach ($funnelData as $index => $stage) {
            $labels[$index] = [
                'label' => $langs->transnoentities($stage['label']),
                'color' => $stage['color']
            ];
            $data[$index] = $stage['value'];
            $colors[$index] = $stage['color'];
            if ($stage['value'] > $maxValue) {
                $maxValue = $stage['value'];
            }
        }

        $array['labels'] = $labels;
        $array['data'] = $data;
        $array['colors'] = $colors;
        $array['maxValue'] = $maxValue;

        $array['custom_html'] = $this->generateFunnelHTML($funnelData, $langs);

        return $array;
    }

    /**
     * Generate HTML for funnel chart (pyramide inversée)
     *
     * @param array $funnelData Data array with label, value, color
     * @param Translate $langs Translation object
     * @return string HTML code
     */
    private function generateFunnelHTML(array $funnelData, Translate $langs): string
    {
        $maxValue = 0;
        foreach ($funnelData as $stage) {
            if ($stage['value'] > $maxValue) {
                $maxValue = $stage['value'];
            }
        }

        if ($maxValue <= 0) {
            $maxValue = 1;
        }

        $countStages = count($funnelData);
        $heightPerStage = 60;
        $svgHeight = $countStages * $heightPerStage;
        $svgWidth = 500;

        $html = '<div class="reedcrm-funnel-container">';
        $html .= '<svg viewBox="0 0 ' . $svgWidth . ' ' . $svgHeight . '" preserveAspectRatio="xMidYMin meet" class="reedcrm-funnel-svg">';

        $html .= '<defs>';
        for ($i = 0; $i < $countStages; $i++) {
            $stage = $funnelData[$i];
            $fill = $stage['color'];
            // Darken color by 15% directly
            $color = ltrim($fill, '#');
            $rgb = [
                hexdec(substr($color, 0, 2)),
                hexdec(substr($color, 2, 2)),
                hexdec(substr($color, 4, 2))
            ];
            for ($j = 0; $j < 3; $j++) {
                $rgb[$j] = max(0, min(255, round($rgb[$j] * 0.85)));
            }
            $fillDarker = '#' . str_pad(dechex($rgb[0]), 2, '0', STR_PAD_LEFT)
                         . str_pad(dechex($rgb[1]), 2, '0', STR_PAD_LEFT)
                         . str_pad(dechex($rgb[2]), 2, '0', STR_PAD_LEFT);
            $html .= '<linearGradient id="gradient-' . $i . '" x1="0%" y1="0%" x2="100%" y2="100%">';
            $html .= '<stop offset="0%" stop-color="' . $fill . '" />';
            $html .= '<stop offset="100%" stop-color="' . $fillDarker . '" />';
            $html .= '</linearGradient>';
        }
        $html .= '</defs>';

        for ($i = 0; $i < $countStages; $i++) {
            $stage = $funnelData[$i];
            $topValue = $stage['value'];
            $bottomValue = ($i < $countStages - 1) ? $funnelData[$i + 1]['value'] : $stage['value'] * 0.4;

            // S'assurer qu'il y a une largeur minimale même pour les valeurs à 0
            $minWidth = 50; // Largeur minimale en pixels
            $topWidth = max($minWidth, ($topValue / $maxValue) * ($svgWidth - 100));
            $bottomWidth = max($minWidth * 0.7, ($bottomValue / $maxValue) * ($svgWidth - 100));

            // S'assurer que le bas est toujours plus étroit que le haut
            $bottomWidth = min($bottomWidth, $topWidth * 0.9);

            $topLeft = ($svgWidth - $topWidth) / 2;
            $topRight = $topLeft + $topWidth;

            $bottomLeft = ($svgWidth - $bottomWidth) / 2;
            $bottomRight = $bottomLeft + $bottomWidth;

            $yTop = $i * $heightPerStage;
            $yBottom = $yTop + $heightPerStage;

            $points = [
                $topLeft . ',' . $yTop,
                $topRight . ',' . $yTop,
                $bottomRight . ',' . $yBottom,
                $bottomLeft . ',' . $yBottom
            ];

            // Polygone du segment
            $html .= '<polygon points="' . implode(' ', $points) . '" fill="url(#gradient-' . $i . ')" stroke="#fff" stroke-width="2"></polygon>';

            // Label et valeurs centrés
            $labelX = $svgWidth / 2;
            $labelY = $yTop + ($heightPerStage / 2);

            $labelText = $langs->transnoentities($stage['label']);
            $valueText = number_format($stage['value'], 0, ',', ' ');

            $conversionRate = ($i > 0 && $funnelData[$i - 1]['value'] > 0)
                ? round(($stage['value'] / $funnelData[$i - 1]['value']) * 100, 1) . '%'
                : '';

            // Utiliser une couleur de texte qui contraste bien avec le fond coloré
            $textColor = '#FFFFFF';
            $html .= '<text x="' . $labelX . '" y="' . ($labelY - 8) . '" fill="' . $textColor . '" font-size="14" font-weight="600" text-anchor="middle" class="reedcrm-funnel-text" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">' . dol_escape_htmltag($labelText) . '</text>';
            $html .= '<text x="' . $labelX . '" y="' . ($labelY + 10) . '" fill="' . $textColor . '" font-size="13" text-anchor="middle" class="reedcrm-funnel-text" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">' . $valueText . '</text>';
            if ($conversionRate) {
                $html .= '<text x="' . $labelX . '" y="' . ($labelY + 24) . '" fill="' . $textColor . '" font-size="11" text-anchor="middle" class="reedcrm-funnel-text" style="text-shadow: 1px 1px 2px rgba(0,0,0,0.5);">' . $langs->transnoentities('ConversionRate') . ': ' . $conversionRate . '</text>';
            }
        }

        $html .= '</svg>';

        $html .= '</div>';

        return $html;
    }


    /**
     * Render inline date selectors for the funnel graph
     *
     * @param int $dateStartTimestamp
     * @param int $dateEndTimestamp
     * @return string
     */
    private function buildSalesFunnelFilters(int $dateStartTimestamp, int $dateEndTimestamp): string
    {
        global $form, $langs;

        if (!is_object($form)) {
            $form = new Form($this->db);
        }

        $html = '<div class="funnel-date-filter flex-row align-center gap-small">';
        $html .= '<div class="flex flex-row align-center marginrightonly">';
        $html .= '<label class="marginrightonlysmall">'.$langs->transnoentities('DateStart').'</label>';
        $html .= $form->selectDate($dateStartTimestamp ?: '', 'salesfunnel_date_start', 1, 1, '', 'form', 1, 0, 0, '', '', '', 1);
        $html .= '</div>';

        $html .= '<div class="flex flex-row align-center marginrightonly">';
        $html .= '<label class="marginrightonlysmall">'.$langs->transnoentities('DateEnd').'</label>';
        $html .= $form->selectDate($dateEndTimestamp ?: '', 'salesfunnel_date_end', 1, 1, '', 'form', 1, 0, 0, '', '', '', 1);
        $html .= '</div>';

        $html .= '<button class="button_search" type="button" id="apply-salesfunnel-filter-btn">';
        $html .= img_picto($langs->transnoentities('Filter'), 'fontawesome_redo_fas_grey_1em');
        $html .= '</button>';
        $html .= '</div>';

        return $html;
    }
}

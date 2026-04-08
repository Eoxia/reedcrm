<?php
/* Copyright (C) 2023-2025 EVARISK <technique@evarisk.com>
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
 * \file    view/map.php
 * \ingroup map
 * \brief   Page to show map of object
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Dolibarr libraries
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
if (isModEnabled('categorie')) {
    require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcategory.class.php';
    require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
}

// Load Saturne librairies
require_once __DIR__ . '/../../saturne/lib/object.lib.php';

// Load ReedCRM librairies
require_once __DIR__ . '/../class/geolocation.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['categories']);

// Get map filters parameters
$filterType    = GETPOST('filter_type','aZ');
$fromId        = GETPOST('from_id');
$objectType    = GETPOST('from_type', 'alpha');
$filterId      = GETPOST('filter_id');
$filterCountry = GETPOST('filter_country');
$filterRegion  = GETPOST('filter_region');
$filterState   = GETPOST('filter_state');
$filterTown    = trim(GETPOST('filter_town', 'alpha'));
$filterCat       = GETPOST("search_category_" . $objectType ."_list", 'array');
$filterDateStart   = dol_mktime(0, 0, 0, GETPOST('filter_date_startmonth', 'int'), GETPOST('filter_date_startday', 'int'), GETPOST('filter_date_startyear', 'int'));
$filterDateEnd     = dol_mktime(23, 59, 59, GETPOST('filter_date_endmonth', 'int'), GETPOST('filter_date_endday', 'int'), GETPOST('filter_date_endyear', 'int'));
$filterNearRadius  = GETPOST('filter_near_radius', 'int'); // km, 0 = disabled
$source            = GETPOSTISSET('source') ? GETPOST('source') : '';

// Initialize technical object
$objectInfos  = saturne_get_objects_metadata($objectType);
$className    = $objectInfos['class_name'];
$objectLinked = new $className($db);
$geolocation  = new Geolocation($db);
$project      = new Project($db);
$contact      = new Contact($db);

// Initialize view objects
$form        = new Form($db);
$formCompany = new FormCompany($db);
if (isModEnabled('categorie')) {
    $formCategory = new FormCategory($db);
} else {
    $formCategory = null;
}

$hookmanager->initHooks(['reedcrmmap', $objectType . 'map']);

// Security check - Protection if external user
$permissiontoread   = $user->rights->reedcrm->address->read;
$permissiontoadd    = $user->rights->reedcrm->address->write;
$permissiontodelete = $user->rights->reedcrm->address->delete;
saturne_check_access($permissiontoread);

/*
 * Actions
 */

$parameters = [];
$resHook    = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $objectLinked may have been modified by some hooks
if ($resHook < 0) {
    setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($resHook)) {
    // Purge search criteria
    if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) // All tests are required to be compatible with all browsers
    {
        $filterCat        = [];
        $filterId         = 0;
        $filterCountry    = 0;
        $filterRegion     = 0;
        $filterState      = 0;
        $filterTown       = '';
        $filterType       = '';
        $filterDateStart  = '';
        $filterDateEnd    = '';
        $filterNearRadius = 0;
    }
}

/*
 * View
 */

$title   = $langs->trans('Map');
$helpUrl = 'FR:Module_ReedCRM';

if ($source == 'pwa') {
    $conf->dol_hide_topmenu  = 1;
    $conf->dol_hide_leftmenu = 1;
}

saturne_header(0, '', $title, $helpUrl);

/**
 * Build geoJSON datas
 */

// Filter on address
$filterId      = $fromId > 0 ? $fromId : $filterId;
$IdFilter      = ($filterId > 0 ? 'element_id = "' . $filterId . '" AND ' : '');
$townFilter    = (dol_strlen($filterTown) > 0 ? 'town = "' . $filterTown . '" AND ' : '');
$countryFilter = ($filterCountry > 0 ? 'fk_country = ' . $filterCountry . ' AND ' : '');
$regionFilter  = ($filterRegion > 0 ? 'fk_region = ' . $filterRegion . ' AND ' : '');
$stateFilter   = ($filterState > 0 ? 'fk_department = ' . $filterState . ' AND ' : '');
// @TODO zip filter

$allCat = '';
foreach($filterCat as $catId) {
    $allCat .= $catId . ',';
}
$allCat        = rtrim($allCat, ',');
$catFilter     = (dol_strlen($allCat) > 0 ? 'cp.fk_categorie IN (' . $allCat . ') AND ' : '');

$filter        = ['customsql' => $IdFilter . $townFilter . $countryFilter . $regionFilter . $stateFilter . $catFilter . 'element_type = "'. $objectType .'" AND status >= 0'];

$icon          = dol_buildpath('/reedcrm/img/dot.png', 1);
$objectList    = [];
$features      = [];
$num           = 0;
$allObjects    = saturne_fetch_all_object_type($objectInfos['class_name']);

//if ($conf->global->REEDCRM_DISPLAY_MAIN_ADDRESS) {
//	if (is_array($allObjects) && !empty($allObjects)) {
//		foreach ($allObjects as $objectLinked) {
//			$objectLinked->fetch_optionals();
//
//			if (!isset($objectLinked->array_options['options_' . $objectType . 'address']) || dol_strlen($objectLinked->array_options['options_' . $objectType . 'address']) <= 0) {
//				continue;
//			} else {
//				$addressId = $objectLinked->array_options['options_' . $objectType . 'address'];
//			}
//
//			$object->fetch($addressId);
//
//			if (($filterId > 0 && $filterId != $objectLinked->id) || (dol_strlen($filterType) > 0 && $filterType != $object->type) || (dol_strlen($filterTown) > 0 && $filterTown != $object->town) ||
//				($filterCountry > 0 && $filterCountry != $object->fk_country) || ($filterRegion > 0 && $filterRegion != $object->fk_region) || ($filterState > 0 && $filterState != $object->fk_department)) {
//                continue;
//			}
//
//			if ($object->longitude != 0 && $object->latitude != 0) {
//				$object->convertCoordinates();
//				$num++;
//			} else {
//				continue;
//			}
//
//			$locationID   = $addressId;
//
//			$description  = $objectLinked->getNomUrl(1) . '</br>';
//			$description .= $langs->trans($object->type) . ' : ' . $object->name;
//			$description .= dol_strlen($object->town) > 0 ? '</br>' . $langs->trans('Town') . ' : ' . $object->town : '';
//			$color        = randomColor();
//
//			$objectList[$locationID] = !empty($object->fields['color']) ? $object->fields['color'] : '#' . $color;
//
//			// Add geoJSON point
//			$features[] = [
//				'type' => 'Feature',
//				'geometry' => [
//					'type' => 'Point',
//					'coordinates' => [$object->longitude, $object->latitude],
//				],
//				'properties' => [
//					'desc'    => $description,
//					'address' => $locationID,
//				],
//			];
//		}
//	}
//} else {
$filterSQL  = 't.element_type = ' . "'" . GETPOST('from_type') . "'";
$filterSQL .= ($filterId > 0 ? ' AND t.fk_element = ' . $filterId : '');

// Build date SQL conditions for project creation date
$dateStartSQL = (!empty($filterDateStart) ? " AND p.datec >= '" . date('Y-m-d H:i:s', $filterDateStart) . "'" : '');
$dateEndSQL   = (!empty($filterDateEnd)   ? " AND p.datec <= '" . date('Y-m-d H:i:s', $filterDateEnd)   . "'" : '');

if ($filterId > 0) {
    $project->fetch($filterId);
    // Apply date filter on the single fetched project
    $projectDateC = (int) $project->date_creation;
    if ((!empty($filterDateStart) && $projectDateC < $filterDateStart) || (!empty($filterDateEnd) && $projectDateC > $filterDateEnd)) {
        $contacts = [];
    } else {
        $contacts = $project->liste_contact();
    }
} else {
    $contacts = saturne_fetch_all_object_type('contact', '', '', 0, 0, ['customsql' => 'ct.code = "PROJECTADDRESS"' . $dateStartSQL . $dateEndSQL], 'AND', 0, 0, 0, ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_contact as ec ON t.rowid = ec.fk_socpeople LEFT JOIN ' . MAIN_DB_PREFIX . 'c_type_contact as ct ON ec.fk_c_type_contact = ct.rowid LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON ec.element_id = p.rowid');
}

if (is_array($contacts) && !empty($contacts)) {
    foreach($contacts as $contactSingle) {
        $geolocation = new Geolocation($db);

        if (is_object($contactSingle)) {
            $geolocation->fetch(0, '', ' AND t.fk_element = ' . $contactSingle->id);
            $contactName    = $contactSingle->firstname . ' ' . $contactSingle->lastname;
            $contactAddress = $contactSingle->address;
            $contactPhone   = !empty($contactSingle->phone_mobile) ? $contactSingle->phone_mobile : $contactSingle->phone_pro;
            $contactEmail   = $contactSingle->email;
        } else if (is_array($contactSingle) && $contactSingle['code'] == 'PROJECTADDRESS') {
            $geolocation->fetch(0, '', ' AND t.fk_element = ' . $contactSingle['id']);
            $contact->fetch($contactSingle['id']);
            $contactName    = $contact->firstname . ' ' . $contact->lastname;
            $contactAddress = $contact->address;
            $contactPhone   = !empty($contact->phone_mobile) ? $contact->phone_mobile : $contact->phone_pro;
            $contactEmail   = $contact->email;
        }
        if ($geolocation->latitude > 0 && $geolocation->longitude > 0) {
            // We fill temporarily geolocation with contact data to use them in the description afterward
            $geolocation->address_name = $contactName;
            $geolocation->tmp_address  = $contactAddress;
            $geolocation->tmp_phone    = $contactPhone ?? '';
            $geolocation->tmp_email    = $contactEmail ?? '';
            $geolocations[]            = $geolocation;
        }
    }
} else {
    $geolocation  = new Geolocation($db);
    $geolocations = $geolocation->fetchAll();
}

if (is_array($geolocations) && !empty($geolocations)) {
    foreach($geolocations as $geolocation) {
        $geolocation->convertCoordinates();
        $result = -1;
        if (!empty($fromId)) {
            $result = $objectLinked->fetch($fromId);
        }
        if (empty($fromId) || $result <= 0) {
            $projects     = saturne_fetch_all_object_type('project', 'DESC', 'rowid', 1, 0, ['customsql' => 'ec.fk_socpeople = ' . $geolocation->fk_element], 'AND', false, true, false, ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_contact as ec ON t.rowid = ec.element_id');
            $objectLinked = array_shift($projects);
        }

        if ((!empty($fromId) && $objectLinked->entity != $conf->entity) || ($source == 'pwa' && empty($objectLinked->opp_status) && empty($objectLinked->fk_opp_status)) || empty($objectLinked)) {
            continue;
        }

        $oppPercent = (float) $objectLinked->opp_percent;
        $objectLinkedInfo  = '<div style="min-width:230px;font-family:inherit">';
        $objectLinkedInfo .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px">';
        $objectLinkedInfo .=   '<span style="font-weight:bold">' . $objectLinked->getNomUrl(1) . '</span>';
        $objectLinkedInfo .=   '<span style="color:#555;white-space:nowrap;margin-left:12px">' . number_format($oppPercent, 2) . ' %</span>';
        $objectLinkedInfo .= '</div>';
        $objectLinkedInfo .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">';
        $objectLinkedInfo .=   '<span style="color:#333">' . dol_escape_htmltag($objectLinked->title) . '</span>';
        $objectLinkedInfo .=   '<a href="' . dol_buildpath('/projet/card.php', 1) . '?id=' . $objectLinked->id . '" target="_blank" style="margin-left:8px">' . img_picto('', 'fontawesome_external-link-alt_fas_#28a745') . '</a>';
        $objectLinkedInfo .= '</div>';
        if (!empty($geolocation->address_name) || !empty($geolocation->tmp_phone)) {
            $contactLine = dol_escape_htmltag(trim($geolocation->address_name));
            if (!empty($geolocation->tmp_phone)) {
                $contactLine .= ' - ' . dol_escape_htmltag($geolocation->tmp_phone);
            }
            $objectLinkedInfo .= '<div style="color:#555;font-size:0.9em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . $contactLine . '</div>';
        }
        if (!empty($geolocation->tmp_email)) {
            $objectLinkedInfo .= '<div style="color:#555;font-size:0.9em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . dol_escape_htmltag($geolocation->tmp_email) . '</div>';
        }
        if ($user->hasRight('agenda', 'myactions', 'create')) {
            $cardProUrl        = dol_buildpath('/custom/reedcrm/view/procard.php', 1) . '?from_id=' . $objectLinked->id . '&from_type=project&modal=1';
            $objectLinkedInfo .= '<div style="margin-top:6px;border-top:1px solid #eee;padding-top:6px;text-align:right">';
            $objectLinkedInfo .= '<span class="fa fa-plus-circle reedcrm-card-modal-open" style="cursor:pointer;color:#1e3a5f;font-size:1.1em;" title="' . dol_escape_htmltag($langs->trans('QuickEventCreation')) . '" data-project-id="' . $objectLinked->id . '" data-modal-url="' . dol_escape_htmltag($cardProUrl) . '">';
            $objectLinkedInfo .= '<input type="hidden" class="modal-options" data-modal-to-open="eventproCardModal">';
            $objectLinkedInfo .= '</span>';
            $objectLinkedInfo .= '</div>';
        }
        $objectLinkedInfo .= '</div>';

        $num++;
        if ($objectLinked->opp_percent == 0) {
            $objectList[$num]['color'] = '#95a5a6';
        } elseif ($objectLinked->opp_percent == 100) {
            $objectList[$num]['color'] = '#27ae60';
        } elseif ($objectLinked->opp_percent >= 60) {
            $objectList[$num]['color'] = '#2ecc71';
        } elseif ($objectLinked->opp_percent >= 40) {
            $objectList[$num]['color'] = '#f39c12';
        } elseif ($objectLinked->opp_percent >= 20) {
            $objectList[$num]['color'] = '#e67e22';
        } else {
            $objectList[$num]['color'] = '#e74c3c';
        }
        $objectList[$num]['scale'] = 1;

        // Add geoJSON point
        $features[] = [
            'type'     => 'Feature',
            'geometry' => [
                'type'        => 'Point',
                'coordinates' => [$geolocation->longitude, $geolocation->latitude]
            ],
            'properties' => [
                'desc'    => $objectLinkedInfo,
                'address' => $num
            ]
        ];
    }
}

if ($filterId > 0) {
    $objectLinked->fetch($filterId);

    saturne_get_fiche_head($objectLinked, 'map', $title);

    $morehtml = '<a href="' . dol_buildpath('/' . $objectLinked->element . '/list.php', 1) . '?restore_lastsearch_values=1&from_type=' . $objectLinked->element . '">' . $langs->trans('BackToList') . '</a>';
    saturne_banner_tab($objectLinked, 'ref', $morehtml, 1, 'ref', 'ref', '', !empty($objectLinked->photo));
}

$backToMap = img_picto('project', 'fontawesome_project-diagram_fas_#ffffff') . ' ' . img_picto('create', 'fontawesome_plus_fas_#ffffff');
$iconBTM   = '<a class="wpeo-button" href="' . dol_buildpath('custom/reedcrm/view/frontend/quickcreation.php?source=pwa', 1) . '">' . $backToMap . '</a>';
print_barre_liste($title, '', $_SERVER["PHP_SELF"], '', '', '', '', '', $num, 'fa-map-marked-alt', 0, ($source == 'pwa' ? $iconBTM : ''));

if ($source != 'pwa') {
    print '<form method="post" action="' . $_SERVER["PHP_SELF"] . '?from_type=' . $objectType . '" name="formfilter">';
    print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
    print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';

    // Filter box
    print '<div class="liste_titre liste_titre_bydiv centpercent">';

    $selectArray = [];
    foreach ($allObjects as $singleObject) {
        $selectArray[$singleObject->id] = $singleObject->ref;
    }
    // Object
    print '<div class="divsearchfield">' . img_picto('', $objectInfos['picto']) . ' ' . $langs->trans($objectInfos['langs']). ': ';
    print $form->selectarray('filter_id', $selectArray, $filterId, 1, 0, 0, '', 0, 0, $fromId > 0) . '</div>';

    // Type
    print '<div class="divsearchfield">' . $langs->trans('Type'). ': ';
    print saturne_select_dictionary('filter_type', 'c_address_type', 'ref', 'label', $filterType, 1) . '</div>';

    // Country
    print '<div class="divsearchfield">' . $langs->trans('Country'). ': ';
    print $form->select_country($filterCountry, 'filter_country', '', 0, 'maxwidth100') . '</div>';

    // Region
    print '<div class="divsearchfield">' . $langs->trans('Region'). ': ';
    print $formCompany->select_region($filterRegion, 'filter_region') . '</div>';

    // Department
    print '<div class="divsearchfield">' . $langs->trans('State'). ': ';
    print $formCompany->select_state($filterState, 0, 'filter_state', 'maxwidth100') . '</div>';

    // City
    print '<div class="divsearchfield">' . $langs->trans('Town'). ': ';
    print '<input class="flat searchstring maxwidth200" type="text" name="filter_town" value="' . dol_escape_htmltag($filterTown) . '"></div>';

    // Date start
    print '<div class="divsearchfield">' . $langs->trans('DateStart') . ': ';
    print $form->selectDate($filterDateStart, 'filter_date_start', 0, 0, 1, 'formfilter', 1, 0) . '</div>';

    // Date end + quick presets inline
    print '<div class="divsearchfield">' . $langs->trans('DateEnd') . ': ';
    print $form->selectDate($filterDateEnd, 'filter_date_end', 0, 0, 1, 'formfilter', 1, 0);
    print '<span style="margin-left:6px">';
    print '<button type="button" class="button smallpaddingimp map-preset-btn" data-preset="day">'   . $langs->trans('Today')    . '</button> ';
    print '<button type="button" class="button smallpaddingimp map-preset-btn" data-preset="week">'  . $langs->trans('ThisWeek')  . '</button> ';
    print '<button type="button" class="button smallpaddingimp map-preset-btn" data-preset="month">' . $langs->trans('ThisMonth') . '</button>';
    print '</span></div>';

    // Around-me radius filter — client-side, consistent with other geo selects (Country/Region/State/Town)
    print '<div class="divsearchfield">' . img_picto('', 'fontawesome_search-location_fas_#007BFF') . ' ' . $langs->trans('NearMe') . ': ';
    print '<select name="filter_near_radius" id="filter_near_radius" class="flat" onchange="onNearMeChange(parseInt(this.value))">';
    print '<option value="0">—</option>';
    foreach ([1, 5, 10, 25, 50] as $km) {
        $selected = ($filterNearRadius == $km ? ' selected' : '');
        print '<option value="' . $km . '"' . $selected . '>' . $km . ' km</option>';
    }
    print '</select></div>';

//    //Categories project
//    if (isModEnabled('categorie') && $user->rights->categorie->lire && $fromId <= 0) {
//        if (in_array($objectType, Categorie::$MAP_ID_TO_CODE)) {
//            print '<div class="divsearchfield">';
//            print $langs->trans(ucfirst($objectInfos['langfile']) . 'CategoriesShort') . '</br>' . $formCategory->getFilterBox($objectType, $filterCat) . '</div>';
//        }
//    }

    // Morefilter buttons
    print '<div class="divsearchfield">';
    print $form->showFilterButtons() . '</div></div>';

    print '</form>';
}

$picto = img_picto($langs->trans('MyPosition'), 'fontawesome_search-location_fas_#007BFF');
print '<div id="geolocate-button" class="geolocate-button">' . $picto . '</div>';
$pictoRoute = img_picto($langs->trans('ShowRoute'), 'fontawesome_route_fas_#007BFF');
print '<div id="route-toggle-button" class="route-toggle-button">' . $pictoRoute . '</div>';

?>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/css/ol.css" type="text/css">
	<script src="https://cdn.polyfill.io/v2/polyfill.min.js?features=requestAnimationFrame,Element.prototype.classList"></script>
	<script src="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/build/ol.js"></script>

	<div id="display_map" class="display_map"></div>
	<div id="popup" class="ol-popup">
		<a href="#" id="popup-closer" class="ol-popup-closer"></a>
		<div id="popup-content"></div>
	</div>

	<script type="text/javascript">
		/**
		 * Set map height.
		 */
		var _map = $('#display_map');
		var _map_pos = _map.position();
		var h = Math.max(document.documentElement.clientHeight, window.innerHeight || 0);
		_map.height(h - _map_pos.top - 20);

		/**
		 * Prospect markers geoJSON.
		 */
		var geojsonMarkers = {
			"type": "FeatureCollection",
			"crs": {
				"type": "name",
				"properties": {
					"name": "EPSG:3857"
				}
			},
			"features": []
		};
		<?php
		$result = $geolocation->injectMapFeatures($features, 500);
		if ($result < 0) {
			setEventMessage($langs->trans('ErrorMapFeatureEncoding'), 'errors');
		}
		?>
		console.log("Map metrics: EPSG:3857");
		console.log("Map features length: " + geojsonMarkers.features.length + " map features loaded.");

		/**
		 * Prospect markers styles.
		 */
		var markerStyles = {};
		$.map(<?php print json_encode($objectList) ?>, function (value, key) {
			if (!(key in markerStyles)) {
				markerStyles[key] = new ol.style.Style({
					image: new ol.style.Icon(/** @type {module:ol/style/Icon~Options} */ ({
						anchor: [0.5, 1],
						color: value.color,
						crossOrigin: 'anonymous',
						scale: value.scale,
						src: '<?php print $icon ?>'
					}))
				});
			}
		});
		var styleFunction = function(feature) {
			return markerStyles[feature.get('address')];
		};

		/**
		 * Prospect markers source.
		 */
		var prospectSource = new ol.source.Vector({
			features: (new ol.format.GeoJSON()).readFeatures(geojsonMarkers)
		});

		/**
		 * Prospect markers layer.
		 */
		var prospectLayer = new ol.layer.Vector({
			source: prospectSource,
			style: styleFunction
		});

		/**
		 * Open Street Map layer.
		 */
		var osmLayer = new ol.layer.Tile({
			source: new ol.source.OSM({
				tileLoadFunction: function(tile, src) {
					fetch(src, { referrerPolicy: 'strict-origin-when-cross-origin' })
						.then(function(response) { return response.blob(); })
						.then(function(blob) {
							tile.getImage().src = URL.createObjectURL(blob);
						});
				}
			})
		});

		/**
		 * Elements that make up the popup.
		 */
		var popupContainer = document.getElementById('popup');
		var popupContent = document.getElementById('popup-content');
		var popupCloser = document.getElementById('popup-closer');

		/**
		 * Create an overlay to anchor the popup to the map.
		 */
		var popupOverlay = new ol.Overlay({
			element: popupContainer,
			autoPan: true,
			autoPanAnimation: {
				duration: 250
			}
		});

		/**
		 * Add a click handler to hide the popup.
		 * @return {boolean} Don't follow the href.
		 */
		popupCloser.onclick = function() {
			popupOverlay.setPosition(undefined);
			popupCloser.blur();
			return false;
		};

		/**
		 * View of the map.
		 */
		var mapView = new ol.View({
			projection: 'EPSG:3857'
		});
		if (<?php print $num ?> == 1) {
			var feature = prospectSource.getFeatures()[0];
			var coordinates = feature.getGeometry().getCoordinates();
			mapView.fit([coordinates[0], coordinates[1], coordinates[0], coordinates[1]], {
				padding: [50, 50, 50, 50],
				constrainResolution: false
			})
			mapView.setCenter(coordinates);
			mapView.setZoom(<?php print (!empty($filterTown) ? 14 : 17) ?>);
		} else {
			mapView.setCenter([0, 0]);
			mapView.setZoom(1);
		}

		/**
		 * Create the map.
		 */
		var map = new ol.Map({
			target: 'display_map',
			layers: [osmLayer, prospectLayer],
			overlays: [popupOverlay],
			view: mapView
		});

		/**
		 * Spider layer for spread features and legs.
		 */
		var spiderSource = new ol.source.Vector();
		var spiderLayer  = new ol.layer.Vector({ source: spiderSource, zIndex: 10 });
		map.addLayer(spiderLayer);

		var spiderActive     = false;
		var spiderFeatureMap = {};

		function collapseSpider() {
			spiderSource.clear();
			spiderActive     = false;
			spiderFeatureMap = {};
			popupOverlay.setPosition(undefined);
		}

		function activateSpider(center, features) {
			spiderSource.clear();
			spiderFeatureMap = {};
			spiderActive     = true;

			var resolution = mapView.getResolution();
			var mapRadius  = 60 * resolution;

			features.forEach(function(feature, i) {
				var angle      = (2 * Math.PI * i) / features.length - Math.PI / 2;
				var spiderCoord = [
					center[0] + mapRadius * Math.cos(angle),
					center[1] + mapRadius * Math.sin(angle)
				];

				var leg = new ol.Feature({ geometry: new ol.geom.LineString([center, spiderCoord]) });
				leg.setStyle(new ol.style.Style({
					stroke: new ol.style.Stroke({ color: 'rgba(0,0,0,0.35)', width: 1.5 })
				}));
				spiderSource.addFeature(leg);

				var spiderFeature = new ol.Feature({ geometry: new ol.geom.Point(spiderCoord) });
				spiderFeature.set('desc', feature.get('desc'));
				spiderFeature.setStyle(styleFunction(feature));
				var spiderId = 'spider_' + i;
				spiderFeature.setId(spiderId);
				spiderFeatureMap[spiderId] = feature;
				spiderSource.addFeature(spiderFeature);
			});
		}

		mapView.on('change:resolution', function() {
			if (spiderActive) collapseSpider();
		});

		/**
		 * Fit map for markers.
		 */
		if (<?php print $num ?> > 1) {
			var extent = limitExtent(prospectSource.getExtent());

			if (mapView.getProjection() == 'EPSG:3857') extent = limitExtent(extent);

			mapView.fit(
				extent, {
					padding: [50, 50, 50, 50],
					constrainResolution: false
				}
			);
		}

		function limitExtent(extent) {
			const max_extent_coords = [-20037508.34, -20048966.1, 20037508.34, 20048966.1];
			for (let i = 0 ; i < 4 ; i++) {
				if (Math.abs(extent[i]) > Math.abs(max_extent_coords[i])) {
					extent[i] = max_extent_coords[i];
				}
			}
			return extent;
		}

		/**
		 * Route layer between markers.
		 */
		var routeSource = new ol.source.Vector();
		var routeLayer  = new ol.layer.Vector({ source: routeSource, zIndex: 2 });
		map.addLayer(routeLayer);
		var routeVisible = false;

		function nearestNeighborOrder(coords) {
			if (coords.length <= 1) return coords;
			var remaining = coords.slice();
			var ordered   = remaining.splice(0, 1);
			while (remaining.length > 0) {
				var last        = ordered[ordered.length - 1];
				var nearestIdx  = 0;
				var nearestDist = Infinity;
				remaining.forEach(function(c, i) {
					var dist = Math.pow(c[0] - last[0], 2) + Math.pow(c[1] - last[1], 2);
					if (dist < nearestDist) { nearestDist = dist; nearestIdx = i; }
				});
				ordered.push(remaining.splice(nearestIdx, 1)[0]);
			}
			return ordered;
		}

		function drawStraightRoute(coords3857) {
			routeSource.clear();
			var line = new ol.Feature({ geometry: new ol.geom.LineString(coords3857) });
			line.setStyle(new ol.style.Style({
				stroke: new ol.style.Stroke({ color: '#3498db', width: 3, lineDash: [8, 6] })
			}));
			routeSource.addFeature(line);
		}

		function drawRoute() {
			var points = prospectSource.getFeatures().filter(function(f) {
				return f.getGeometry().getType() === 'Point';
			});
			if (points.length < 2) return;

			var coords3857  = points.map(function(f) { return f.getGeometry().getCoordinates(); });
			var ordered3857 = nearestNeighborOrder(coords3857);
			var orderedWGS84 = ordered3857.map(function(c) {
				return ol.proj.transform(c, 'EPSG:3857', 'EPSG:4326');
			});

			var waypointsStr = orderedWGS84.map(function(c) {
				return c[0].toFixed(6) + ',' + c[1].toFixed(6);
			}).join(';');

			fetch('https://router.project-osrm.org/route/v1/driving/' + waypointsStr + '?overview=full&geometries=geojson')
				.then(function(r) {
					if (!r.ok) throw new Error('OSRM ' + r.status);
					return r.json();
				})
				.then(function(data) {
					if (!data.routes || data.routes.length === 0) throw new Error('no route');
					var routeFeature = (new ol.format.GeoJSON()).readFeature(
						{ type: 'Feature', geometry: data.routes[0].geometry },
						{ dataProjection: 'EPSG:4326', featureProjection: 'EPSG:3857' }
					);
					routeFeature.setStyle(new ol.style.Style({
						stroke: new ol.style.Stroke({ color: '#3498db', width: 3 })
					}));
					routeSource.clear();
					routeSource.addFeature(routeFeature);
				})
				.catch(function() {
					drawStraightRoute(ordered3857);
				});
		}

		var routeBtn = document.getElementById('route-toggle-button');

		routeBtn.addEventListener('click', function() {
			routeVisible = !routeVisible;
			routeLayer.setVisible(routeVisible);
			routeBtn.classList.toggle('route-active', routeVisible);
			if (routeVisible && routeSource.getFeatures().length === 0) drawRoute();
		});
		routeLayer.setVisible(false);

        /**
         * Initialize geolocation control.
         */
        var geolocation = new ol.Geolocation({
            trackingOptions: {
                enableHighAccuracy: true
            },
            projection: mapView.getProjection()
        });

        // Enable geolocation tracking
        geolocation.setTracking(true);

        /**
         * Handle geolocation error.
         */
        geolocation.on('error', function(error) {
            console.error('Geolocation error: ' + error.message);
        });

        /**
         * Geolocation marker style.
         */
        var positionFeature = new ol.Feature();
        var radiusFeature = new ol.Feature();

        // Set custom properties
        positionFeature.setProperties({
            customText: '<?php print $langs->trans('MyPosition') ?>'
        });

        var geolocationSource = new ol.source.Vector({
            features: [positionFeature, radiusFeature]
        });

        var geolocationLayer = new ol.layer.Vector({
            source: geolocationSource
        });

        map.addLayer(geolocationLayer);

        geolocation.on('change:position', function() {
            var coordinates = geolocation.getPosition();
            positionFeature.setGeometry(coordinates ? new ol.geom.Point(coordinates) : null);

            // Create a 1km radius circle around the user's location
            var radius = 1000; // 1 km radius
            var circle = new ol.geom.Circle(coordinates, radius);
            radiusFeature.setGeometry(circle);
        });

        // Style for the circle
        radiusFeature.setStyle(new ol.style.Style({
            stroke: new ol.style.Stroke({
                color: 'rgba(0, 0, 255, 0.5)',
                width: 2
            }),
        }));

        // Style for the position circle
        positionFeature.setStyle(new ol.style.Style({
            image: new ol.style.Circle({
                radius: 6,
                fill: new ol.style.Fill({
                    color: '#3399CC'
                }),
                stroke: new ol.style.Stroke({
                    color: '#fff',
                    width: 2
                })
            })
        }));

        /**
         * Add a click handler to the map to render the popup.
         */
        map.on('singleclick', function(evt) {
            // If spider is active, check if clicking on a spider feature
            if (spiderActive) {
                var spiderFeatureClicked = map.forEachFeatureAtPixel(evt.pixel, function(f) {
                    if (f.getId() && String(f.getId()).indexOf('spider_') === 0) return f;
                });
                if (spiderFeatureClicked) {
                    var coords    = spiderFeatureClicked.getGeometry().getCoordinates();
                    var desc      = spiderFeatureClicked.get('desc');
                    popupContent.innerHTML = desc;
                    popupOverlay.setPosition(coords);
                } else {
                    collapseSpider();
                }
                return;
            }

            // Collect all point features at clicked pixel
            var clickedFeatures = [];
            map.forEachFeatureAtPixel(evt.pixel, function(f) {
                if (f.getGeometry().getType() === 'Point') clickedFeatures.push(f);
            }, { hitTolerance: 6 });

            if (clickedFeatures.length === 0) {
                popupCloser.click();
                return;
            }

            if (clickedFeatures.length === 1) {
                var coordinates = clickedFeatures[0].getGeometry().getCoordinates();
                popupContent.innerHTML = clickedFeatures[0].get('customText') || clickedFeatures[0].get('desc');
                popupOverlay.setPosition(coordinates);
                return;
            }

            // Multiple features — check if they share the same coordinate
            var center    = clickedFeatures[0].getGeometry().getCoordinates();
            var threshold = mapView.getResolution() * 2;
            var stacked   = clickedFeatures.filter(function(f) {
                var c = f.getGeometry().getCoordinates();
                return Math.abs(c[0] - center[0]) <= threshold && Math.abs(c[1] - center[1]) <= threshold;
            });

            if (stacked.length > 1) {
                activateSpider(center, stacked);
            } else {
                popupContent.innerHTML = clickedFeatures[0].get('customText') || clickedFeatures[0].get('desc');
                popupOverlay.setPosition(center);
            }
        });

        // Center and zoom the map on geolocation when the button is clicked
        document.getElementById('geolocate-button').addEventListener('click', function() {
            var coordinates = geolocation.getPosition();
            if (coordinates) {
                mapView.setCenter(coordinates);
                mapView.setZoom(14); // Adjust the zoom level as needed
            } else {
                console.error('Geolocation position is not available.');
            }
        });

        /**
         * Quick date preset buttons (Today / This week / This month).
         * Fills the date fields generated by selectDate() and submits the form.
         */
        document.querySelectorAll('.map-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var preset = btn.getAttribute('data-preset');
                var now    = new Date();
                var start, end;

                if (preset === 'day') {
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                    end   = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                } else if (preset === 'week') {
                    var day   = now.getDay() === 0 ? 6 : now.getDay() - 1; // Monday=0
                    start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - day);
                    end   = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                } else if (preset === 'month') {
                    start = new Date(now.getFullYear(), now.getMonth(), 1);
                    end   = new Date(now.getFullYear(), now.getMonth(), now.getDate());
                } else {
                    return;
                }

                function fillDate(prefix, d) {
                    var dayEl   = document.querySelector('[name="' + prefix + 'day"]');
                    var monthEl = document.querySelector('[name="' + prefix + 'month"]');
                    var yearEl  = document.querySelector('[name="' + prefix + 'year"]');
                    if (dayEl)   dayEl.value   = d.getDate();
                    if (monthEl) monthEl.value = d.getMonth() + 1;
                    if (yearEl)  yearEl.value  = d.getFullYear();
                }

                fillDate('filter_date_start', start);
                fillDate('filter_date_end',   end);

                document.forms['formfilter'].submit();
            });
        });

        /**
         * "Around me" client-side radius filter.
         * Hides/shows prospect markers depending on distance from user position.
         * The radius circle updates to match the selected distance.
         */
        var nearMeActive   = false;
        var nearMeRadiusKm = <?php print (int)$filterNearRadius ?>;
        var allProspectFeatures = prospectSource.getFeatures().slice(); // snapshot

        // If a radius was previously selected (from URL param), apply immediately once position is known
        if (nearMeRadiusKm > 0) {
            var _nearInitHandler = geolocation.on('change:position', function() {
                applyNearMeFilter(nearMeRadiusKm);
                ol.Observable.unByKey(_nearInitHandler);
            });
        }

        function applyNearMeFilter(radiusKm) {
            var userPos = geolocation.getPosition();
            if (!userPos) {
                console.warn('Position GPS non disponible.');
                return;
            }

            var radiusM = radiusKm * 1000; // EPSG:3857 unit = metres

            // Update the radius circle to the chosen distance
            radiusFeature.setGeometry(new ol.geom.Circle(userPos, radiusM));

            // Show/hide features based on distance
            allProspectFeatures.forEach(function(feature) {
                if (feature.getGeometry().getType() !== 'Point') return;
                var coords = feature.getGeometry().getCoordinates();
                var dx     = coords[0] - userPos[0];
                var dy     = coords[1] - userPos[1];
                var dist   = Math.sqrt(dx * dx + dy * dy);
                feature.setStyle(dist <= radiusM ? styleFunction(feature) : new ol.style.Style({}));
            });

            nearMeActive = true;

            // Center on user with a zoom that roughly fits the radius
            mapView.setCenter(userPos);
            var zoomForRadius = Math.round(14 - Math.log2(radiusKm));
            mapView.setZoom(Math.min(Math.max(zoomForRadius, 8), 16));
        }

        function resetNearMeFilter() {
            allProspectFeatures.forEach(function(feature) {
                if (feature.getGeometry().getType() === 'Point') {
                    feature.setStyle(styleFunction(feature));
                }
            });
            // Reset radius circle to default 1 km visual hint
            var userPos = geolocation.getPosition();
            if (userPos) radiusFeature.setGeometry(new ol.geom.Circle(userPos, 1000));
            nearMeActive = false;
        }

        function onNearMeChange(radiusKm) {
            if (radiusKm > 0) {
                var pos = geolocation.getPosition();
                if (pos) {
                    applyNearMeFilter(radiusKm);
                } else {
                    // Wait for first position fix then apply
                    var _handler = geolocation.on('change:position', function() {
                        applyNearMeFilter(radiusKm);
                        ol.Observable.unByKey(_handler);
                    });
                }
            } else {
                resetNearMeFilter();
            }
        }
	</script>
<?php if ($user->hasRight('agenda', 'myactions', 'create')): ?>
	<link href="<?php echo dol_buildpath('/custom/reedcrm/css/reedcrm.min.css', 1); ?>" rel="stylesheet">
	<link href="<?php echo dol_buildpath('/custom/reedcrm/css/temp-framework.css', 1); ?>" rel="stylesheet">

	<div class="wpeo-modal modal-eventpro" id="eventproCardModal">
		<div class="modal-container wpeo-modal-event">
			<div class="modal-header">
				<h2 class="modal-title"><?php echo dol_escape_htmltag($langs->trans('QuickEventCreation')); ?></h2>
				<div class="modal-close"><i class="fas fa-times"></i></div>
			</div>
			<div class="modal-content">
				<div id="eventproCardModal-loader" class="wpeo-loader"></div>
				<div id="eventproCardModal-content"></div>
			</div>
		</div>
	</div>

	<script>
		jQuery(document).ready(function () {
			window.reedcrm.eventpro.init();
		});
	</script>
<?php endif; ?>
<?php

llxFooter();
$db->close();

<?php
/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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

// Load EasyCRM environment
if (file_exists('../easycrm.main.inc.php')) {
    require_once __DIR__ . '/../easycrm.main.inc.php';
} elseif (file_exists('../../easycrm.main.inc.php')) {
    require_once __DIR__ . '/../../easycrm.main.inc.php';
} else {
    die('Include of easycrm main fails');
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

// Load EasyCRM librairies
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
$filterCat     = GETPOST("search_category_" . $objectType ."_list", 'array');
$source        = GETPOSTISSET('source') ? GETPOST('source') : '';

// Initialize technical object
$objectInfos    = saturne_get_objects_metadata($objectType);
$className      = $objectInfos['class_name'];
$objectLinked   = new $className($db);
$geolocation    = new Geolocation($db);

// Initialize view objects
$form        = new Form($db);
$formCompany = new FormCompany($db);
if (isModEnabled('categorie')) {
    $formCategory = new FormCategory($db);
} else {
    $formCategory = null;
}

$hookmanager->initHooks(['easycrmmap', $objectType . 'map']);

// Security check - Protection if external user
$permissiontoread   = $user->rights->easycrm->address->read;
$permissiontoadd    = $user->rights->easycrm->address->write;
$permissiontodelete = $user->rights->easycrm->address->delete;
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
        $filterCat     = [];
        $filterId      = 0;
        $filterCountry = 0;
        $filterRegion  = 0;
        $filterState   = 0;
        $filterTown    = '';
        $filterType    = '';
    }
}

/*
 * View
 */

$title   = $langs->trans('Map');
$helpUrl = 'FR:Module_EasyCRM';

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
$typeFilter    = (dol_strlen($filterType) > 0 ? 'type = "' . $filterType . '" AND ' : '');
$townFilter    = (dol_strlen($filterTown) > 0 ? 'town = "' . $filterTown . '" AND ' : '');
$countryFilter = ($filterCountry > 0 ? 'fk_country = ' . $filterCountry . ' AND ' : '');
$regionFilter  = ($filterRegion > 0 ? 'fk_region = ' . $filterRegion . ' AND ' : '');
$stateFilter   = ($filterState > 0 ? 'fk_department = ' . $filterState . ' AND ' : '');

$allCat = '';
foreach($filterCat as $catId) {
    $allCat .= $catId . ',';
}
$allCat        = rtrim($allCat, ',');
$catFilter     = (dol_strlen($allCat) > 0 ? 'cp.fk_categorie IN (' . $allCat . ') AND ' : '');

$filter        = ['customsql' => $IdFilter . $typeFilter . $townFilter . $countryFilter . $regionFilter . $stateFilter . $catFilter . 'element_type = "'. $objectType .'" AND status >= 0'];

$icon          = dol_buildpath('/easycrm/img/dot.png', 1);
$objectList    = [];
$features      = [];
$num           = 0;
$allObjects    = saturne_fetch_all_object_type($objectInfos['class_name']);

//if ($conf->global->EASYCRM_DISPLAY_MAIN_ADDRESS) {
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
$filterSQL .= ($fromId > 0 ? ' AND t.fk_element = ' . $fromId : ($filterId > 0 ? ' AND t.fk_element = ' . $filterId : ''));

$geolocations = $geolocation->fetchAll('', '', 0, 0, ['customsql' => $filterSQL]);
if (is_array($geolocations) && !empty($geolocations)) {
    foreach($geolocations as $geolocation) {
        $geolocation->convertCoordinates();
        $objectLinked->fetch($geolocation->fk_element);

        if ($objectLinked->entity != $conf->entity || ($source == 'pwa' && empty($objectLinked->opp_status))) {
            continue;
        }

        $objectLinkedInfo  = $objectLinked->getNomUrl(1, '', 0, '', ' - ', 1) . '</br>';
        $objectLinkedInfo .= $langs->transnoentities('ProjectLabel') . ' : ' . $objectLinked->title . '</br>';
        $objectLinkedInfo .= $langs->transnoentities('Description') . ' : ' . $objectLinked->description . '</br>';
        $code = dol_getIdFromCode($db, $objectLinked->opp_status, 'c_lead_status', 'rowid', 'code');
        if ($code) {
            $objectLinkedInfo .= $langs->transnoentities('OpportunityStatus')  . ' : ' . $langs->trans('OppStatus' . $code) . '</br>';
        }
        if (strcmp($objectLinked->opp_amount, '')) {
            $objectLinkedInfo .= $langs->transnoentities('OpportunityAmount') . ' : ' . price($objectLinked->opp_amount, 0, $langs, 1, 0, -1, $conf->currency) . '</br>';
            if (strcmp($objectLinked->opp_percent, '')) {
                $objectLinkedInfo .= $langs->transnoentities('OpportunityWeightedAmountShort')  . ' : ' . price($objectLinked->opp_amount * $objectLinked->opp_percent / 100, 0, $langs, 1, 0, -1, $conf->currency);
            }
        }

        $num++;
        $objectList[$num]['color']  = '#' . randomColor();
        switch ($objectLinked->opp_percent) {
            case $objectLinked->opp_percent >= 40 && $objectLinked->opp_percent < 60:
                $objectList[$num]['scale'] = 1.5;
                break;
            case $objectLinked->opp_percent >= 60 && $objectLinked->opp_percent < 100:
                $objectList[$num]['scale'] = 1.75;
                break;
            case $objectLinked->opp_percent == 100:
                $objectList[$num]['scale'] = 2;
                break;
            default:
                $objectList[$num]['scale'] = 1;
                break;
        }

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

if ($fromId > 0) {
    $objectLinked->fetch($fromId);

    saturne_get_fiche_head($objectLinked, 'map', $title);

    $morehtml = '<a href="' . dol_buildpath('/' . $objectLinked->element . '/list.php', 1) . '?restore_lastsearch_values=1&from_type=' . $objectLinked->element . '">' . $langs->trans('BackToList') . '</a>';
    saturne_banner_tab($objectLinked, 'ref', $morehtml, 1, 'ref', 'ref', '', !empty($objectLinked->photo));
}

$backToMap = img_picto('project', 'fontawesome_project-diagram_fas_#ffffff') . ' ' . img_picto('create', 'fontawesome_plus_fas_#ffffff');
$iconBTM   = '<a class="wpeo-button" href="' . dol_buildpath('custom/easycrm/view/frontend/quickcreation.php?source=pwa', 1) . '">' . $backToMap . '</a>';
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

    //Categories project
    if (isModEnabled('categorie') && $user->rights->categorie->lire && $fromId <= 0) {
        if (in_array($objectType, Categorie::$MAP_ID_TO_CODE)) {
            print '<div class="divsearchfield">';
            print $langs->trans(ucfirst($objectInfos['langfile']) . 'CategoriesShort') . '</br>' . $formCategory->getFilterBox($objectType, $filterCat) . '</div>';
        }
    }

    // Morefilter buttons
    print '<div class="divsearchfield">';
    print $form->showFilterButtons() . '</div></div>';

    print '</form>';
}
?>
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/css/ol.css" type="text/css">
	<script src="https://cdn.polyfill.io/v2/polyfill.min.js?features=requestAnimationFrame,Element.prototype.classList"></script>
	<script src="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/build/ol.js"></script>
	<style>
		.ol-popup {
			position: absolute;
			background-color: white;
			-webkit-filter: drop-shadow(0 1px 4px rgba(0,0,0,0.2));
			filter: drop-shadow(0 1px 4px rgba(0,0,0,0.2));
			padding: 15px;
			border-radius: 10px;
			border: 1px solid #cccccc;
			bottom: 12px;
			left: -50px;
			min-width: 280px;
		}
		.ol-popup:after, .ol-popup:before {
			top: 100%;
			border: solid transparent;
			content: " ";
			height: 0;
			width: 0;
			position: absolute;
			pointer-events: none;
		}
		.ol-popup:after {
			border-top-color: white;
			border-width: 10px;
			left: 48px;
			margin-left: -10px;
		}
		.ol-popup:before {
			border-top-color: #cccccc;
			border-width: 11px;
			left: 48px;
			margin-left: -11px;
		}
		.ol-popup-closer {
			text-decoration: none;
			position: absolute;
			top: 2px;
			right: 8px;
		}
		.ol-popup-closer:after {
			content: "✖";
		}
	</style>

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
			source: new ol.source.OSM()
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
		 * Add a click handler to the map to render the popup.
		 */
		map.on('singleclick', function(evt) {
			var feature = map.forEachFeatureAtPixel(evt.pixel, function (feature) {
				return feature;
			});

			if (feature) {
				var coordinates = feature.getGeometry().getCoordinates();
				popupContent.innerHTML = feature.get('desc');
				popupOverlay.setPosition(coordinates);
			} else {
				popupCloser.click();
			}
		});
	</script>
<?php

llxFooter();
$db->close();

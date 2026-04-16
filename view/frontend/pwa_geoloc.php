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
 * \file    view/frontend/pwa_geoloc.php
 * \ingroup reedcrm
 * \brief   Page to show geolocated map on frontend App view
 */

// Load ReedCRM environment
if (file_exists('../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../reedcrm.main.inc.php';
} elseif (file_exists('../../reedcrm.main.inc.php')) {
    require_once __DIR__ . '/../../reedcrm.main.inc.php';
} else {
    die('Include of reedcrm main fails');
}

// Load Saturne librairies
require_once __DIR__ . '/../../../saturne/lib/object.lib.php';

// Load Dolibarr librairies
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';

// Load ReedCRM librairies
require_once __DIR__ . '/../../class/geolocation.class.php';

// Global variables definitions
global $conf, $db, $hookmanager, $langs, $user;

// Load translation files required by the page
saturne_load_langs(['projects', 'users', 'companies']);

// Get map filters parameters
$filterId  = GETPOST('filter_id');
$fromId    = GETPOST('from_id');
$preset    = GETPOST('preset', 'alpha'); // 'today', 'week', 'month', or ''

$now = dol_now();
if ($preset === 'today') {
    $filterDateStart = dol_mktime(0,  0,  0,  (int)date('m', $now), (int)date('d', $now), (int)date('Y', $now));
    $filterDateEnd   = dol_mktime(23, 59, 59, (int)date('m', $now), (int)date('d', $now), (int)date('Y', $now));
} elseif ($preset === 'week') {
    $dayOfWeek       = (int)date('N', $now); // 1=Mon … 7=Sun
    $monday          = $now - ($dayOfWeek - 1) * 86400;
    $sunday          = $monday + 6 * 86400;
    $filterDateStart = dol_mktime(0,  0,  0,  (int)date('m', $monday), (int)date('d', $monday), (int)date('Y', $monday));
    $filterDateEnd   = dol_mktime(23, 59, 59, (int)date('m', $sunday), (int)date('d', $sunday), (int)date('Y', $sunday));
} elseif ($preset === 'month') {
    $filterDateStart = dol_mktime(0,  0,  0,  (int)date('m', $now), 1,                                           (int)date('Y', $now));
    $filterDateEnd   = dol_mktime(23, 59, 59, (int)date('m', $now), (int)date('t', $now),                        (int)date('Y', $now));
} else {
    $filterDateStart = dol_mktime(0,  0,  0,  GETPOST('filter_date_startmonth', 'int'), GETPOST('filter_date_startday', 'int'), GETPOST('filter_date_startyear', 'int'));
    $filterDateEnd   = dol_mktime(23, 59, 59, GETPOST('filter_date_endmonth',   'int'), GETPOST('filter_date_endday',   'int'), GETPOST('filter_date_endyear',   'int'));
}

// Initialize technical objects
$geolocation  = new Geolocation($db);
$project      = new Project($db);
$contact      = new Contact($db);

// Security check
$permissiontoread = $user->rights->reedcrm->address->read;
saturne_check_access($permissiontoread);

/*
 * Build geoJSON data
 */

$icon     = dol_buildpath('/reedcrm/img/dot.png', 1);
$features = [];
$objectList = [];
$num = 0;

// Date SQL conditions for project creation date
$dateStartSQL = (!empty($filterDateStart) ? " AND p.datec >= '" . date('Y-m-d H:i:s', $filterDateStart) . "'" : '');
$dateEndSQL   = (!empty($filterDateEnd)   ? " AND p.datec <= '" . date('Y-m-d H:i:s', $filterDateEnd)   . "'" : '');

// Person filter
$geolocFilterUserId = getDolUserInt('REEDCRM_PWA_FILTER_USER_ID', 0, $user);
$userGeolocSQL = $geolocFilterUserId > 0 ? " AND p.fk_user_creat = " . (int) $geolocFilterUserId : '';

if ($fromId > 0) {
    $project->fetch($fromId);
    $projectDateC = (int) $project->date_creation;
    if ((!empty($filterDateStart) && $projectDateC < $filterDateStart) || (!empty($filterDateEnd) && $projectDateC > $filterDateEnd)) {
        $contacts = [];
    } else {
        $contacts = $project->liste_contact();
    }
} else {
    $contacts = saturne_fetch_all_object_type('contact', '', '', 0, 0, ['customsql' => 'ct.code = "PROJECTADDRESS"' . $dateStartSQL . $dateEndSQL . $userGeolocSQL], 'AND', 0, 0, 0, ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_contact as ec ON t.rowid = ec.fk_socpeople LEFT JOIN ' . MAIN_DB_PREFIX . 'c_type_contact as ct ON ec.fk_c_type_contact = ct.rowid LEFT JOIN ' . MAIN_DB_PREFIX . 'projet as p ON ec.element_id = p.rowid');
}

$geolocations = [];

if (is_array($contacts) && !empty($contacts)) {
    foreach ($contacts as $contactSingle) {
        $geolocationSingle = new Geolocation($db);

        if (is_object($contactSingle)) {
            $geolocationSingle->fetch(0, '', ' AND t.fk_element = ' . $contactSingle->id);
            $contactName    = $contactSingle->firstname . ' ' . $contactSingle->lastname;
            $contactAddress = $contactSingle->address;
            $contactPhone   = !empty($contactSingle->phone_mobile) ? $contactSingle->phone_mobile : $contactSingle->phone_pro;
            $contactEmail   = $contactSingle->email;
        } elseif (is_array($contactSingle) && $contactSingle['code'] == 'PROJECTADDRESS') {
            $geolocationSingle->fetch(0, '', ' AND t.fk_element = ' . $contactSingle['id']);
            $contact->fetch($contactSingle['id']);
            $contactName    = $contact->firstname . ' ' . $contact->lastname;
            $contactAddress = $contact->address;
            $contactPhone   = !empty($contact->phone_mobile) ? $contact->phone_mobile : $contact->phone_pro;
            $contactEmail   = $contact->email;
        } else {
            continue;
        }

        if ($geolocationSingle->latitude > 0 && $geolocationSingle->longitude > 0) {
            $geolocationSingle->address_name = $contactName;
            $geolocationSingle->tmp_address  = $contactAddress;
            $geolocationSingle->tmp_phone    = $contactPhone ?? '';
            $geolocationSingle->tmp_email    = $contactEmail ?? '';
            $geolocations[]                  = $geolocationSingle;
        }
    }
} else {
    $geolocations = $geolocation->fetchAll();
}

$objectLinked = new Project($db);

if (is_array($geolocations) && !empty($geolocations)) {
    foreach ($geolocations as $geoSingle) {
        $geoSingle->convertCoordinates();
        $result = -1;
        if (!empty($fromId)) {
            $result = $objectLinked->fetch($fromId);
        }
        if (empty($fromId) || $result <= 0) {
            $projects     = saturne_fetch_all_object_type('project', 'DESC', 'rowid', 1, 0, ['customsql' => 'ec.fk_socpeople = ' . $geoSingle->fk_element], 'AND', false, true, false, ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_contact as ec ON t.rowid = ec.element_id');
            $objectLinked = array_shift($projects);
        }

        if (empty($objectLinked) || empty($objectLinked->opp_status) && empty($objectLinked->fk_opp_status)) {
            continue;
        }

        $oppPercent       = (float) $objectLinked->opp_percent;
        $objectLinkedInfo  = '<div style="min-width:230px;font-family:inherit">';
        $objectLinkedInfo .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:2px">';
        $objectLinkedInfo .=   '<span style="font-weight:bold">' . $objectLinked->getNomUrl(1) . '</span>';
        $objectLinkedInfo .=   '<span style="color:#555;white-space:nowrap;margin-left:12px">' . number_format($oppPercent, 2) . ' %</span>';
        $objectLinkedInfo .= '</div>';
        $objectLinkedInfo .= '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">';
        $objectLinkedInfo .=   '<span style="color:#333">' . dol_escape_htmltag($objectLinked->title) . '</span>';
        $objectLinkedInfo .=   '<a href="' . dol_buildpath('/projet/card.php', 1) . '?id=' . $objectLinked->id . '" target="_blank" style="margin-left:8px">' . img_picto('', 'fontawesome_external-link-alt_fas_#28a745') . '</a>';
        $objectLinkedInfo .= '</div>';
        if (!empty($geoSingle->address_name) || !empty($geoSingle->tmp_phone)) {
            $contactLine = dol_escape_htmltag(trim($geoSingle->address_name));
            if (!empty($geoSingle->tmp_phone)) {
                $contactLine .= ' - ' . dol_escape_htmltag($geoSingle->tmp_phone);
            }
            $objectLinkedInfo .= '<div style="color:#555;font-size:0.9em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . $contactLine . '</div>';
        }
        if (!empty($geoSingle->tmp_email)) {
            $objectLinkedInfo .= '<div style="color:#555;font-size:0.9em;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' . dol_escape_htmltag($geoSingle->tmp_email) . '</div>';
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

        $features[] = [
            'type'     => 'Feature',
            'geometry' => [
                'type'        => 'Point',
                'coordinates' => [$geoSingle->longitude, $geoSingle->latitude]
            ],
            'properties' => [
                'desc'    => $objectLinkedInfo,
                'address' => $num
            ]
        ];
    }
}

/*
 * View
 */

$title   = $langs->trans('Map');
$moreJS  = ['/custom/saturne/js/saturne.min.js', '/custom/reedcrm/js/reedcrm.min.js'];
$moreCSS = ['/custom/reedcrm/css/reedcrm.min.css'];

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;
$conf->global->MAIN_FAVICON_URL = DOL_URL_ROOT . '/custom/reedcrm/img/reedcrm_color_512.png';

llxHeader('', $title, '', '', 0, 0, $moreJS, $moreCSS, '', 'template-pwa pwa-geoloc');

$pwaHeaderCenterHtml = '<div style="background: #e2e8f0; padding: 4px 10px; border-radius: 12px; font-size: 13px; font-weight: bold; color: #475569;"><i class="fas fa-map-marked-alt"></i> ' . $num . ' ' . $langs->trans('Opportunities') . '</div>';
require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_header.tpl.php';

// Filter bar
$selfUrl = $_SERVER['PHP_SELF'] . '?source=pwa';
print '<div class="pwa-map-filters">';
print '<a href="' . $selfUrl . '&amp;preset=today"  class="pwa-filter-btn' . ($preset === 'today' ? ' active' : '') . '">Aujourd\'hui</a>';
print '<a href="' . $selfUrl . '&amp;preset=week"   class="pwa-filter-btn' . ($preset === 'week'  ? ' active' : '') . '">Cette semaine</a>';
print '<a href="' . $selfUrl . '&amp;preset=month"  class="pwa-filter-btn' . ($preset === 'month' ? ' active' : '') . '">Ce mois</a>';
print '<a href="' . $selfUrl . '" class="pwa-filter-btn pwa-filter-clear' . (empty($preset) ? ' active' : '') . '"><i class="fas fa-times"></i></a>';
print '</div>';

$picto      = img_picto($langs->trans('MyPosition'), 'fontawesome_search-location_fas_#007BFF');
$pictoRoute = img_picto($langs->trans('ShowRoute'), 'fontawesome_route_fas_#007BFF');
print '<div id="geolocate-button" class="geolocate-button">' . $picto . '</div>';
print '<div id="route-toggle-button" class="route-toggle-button">' . $pictoRoute . '</div>';
?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/css/ol.css" type="text/css">
    <script src="https://cdn.polyfill.io/v2/polyfill.min.js?features=requestAnimationFrame,Element.prototype.classList"></script>
    <script src="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.15.1/build/ol.js"></script>

    <div id="display_map" class="display_map pwa-map"></div>
    <div id="popup" class="ol-popup">
        <a href="#" id="popup-closer" class="ol-popup-closer"></a>
        <div id="popup-content"></div>
    </div>

    <script type="text/javascript">
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
        console.log("Map features loaded: " + geojsonMarkers.features.length);

        /**
         * Prospect markers styles.
         */
        var markerStyles = {};
        $.map(<?php print json_encode($objectList) ?>, function (value, key) {
            if (!(key in markerStyles)) {
                markerStyles[key] = new ol.style.Style({
                    image: new ol.style.Icon({
                        anchor: [0.5, 1],
                        color: value.color,
                        crossOrigin: 'anonymous',
                        scale: value.scale,
                        src: '<?php print $icon ?>'
                    })
                });
            }
        });
        var badgeStyleCache = {};

        function createBadgeStyle(count) {
            if (badgeStyleCache[count]) return badgeStyleCache[count];

            var text    = String(count);
            var font    = 'bold 10px sans-serif';
            var padH    = 5, padV = 3;

            var tmpCanvas = document.createElement('canvas');
            var tmpCtx    = tmpCanvas.getContext('2d');
            tmpCtx.font   = font;
            var textW     = tmpCtx.measureText(text).width;

            var badgeH = 10 + padV * 2;
            var badgeW = Math.max(badgeH, textW + padH * 2);
            var r      = badgeH / 2;

            var canvas  = document.createElement('canvas');
            canvas.width  = badgeW;
            canvas.height = badgeH;
            var ctx = canvas.getContext('2d');

            // Pill shape
            ctx.beginPath();
            ctx.moveTo(r, 0);
            ctx.arcTo(badgeW, 0,    badgeW, badgeH, r);
            ctx.arcTo(badgeW, badgeH, 0,    badgeH, r);
            ctx.arcTo(0,      badgeH, 0,    0,      r);
            ctx.arcTo(0,      0,      badgeW, 0,    r);
            ctx.closePath();
            ctx.fillStyle = '#ff4757';
            ctx.fill();

            // Number
            ctx.fillStyle    = '#fff';
            ctx.font         = font;
            ctx.textAlign    = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, badgeW / 2, badgeH / 2);

            badgeStyleCache[count] = new ol.style.Style({
                image: new ol.style.Icon({
                    img:          canvas,
                    imgSize:      [badgeW, badgeH],
                    displacement: [badgeW / 2 + 4, 28]
                })
            });

            return badgeStyleCache[count];
        }

        var styleFunction = function(feature) {
            var baseStyle = markerStyles[feature.get('address')];
            if (!baseStyle) return baseStyle;
            var count = feature.get('stackCount');
            if (count && count > 1) {
                return [baseStyle, createBadgeStyle(count)];
            }
            return baseStyle;
        };

        /**
         * Prospect markers source & layer.
         */
        var prospectSource = new ol.source.Vector({
            features: (new ol.format.GeoJSON()).readFeatures(geojsonMarkers)
        });

        /**
         * Compute stack count per coordinate and set it on each feature.
         */
        (function() {
            var coordCountMap = {};
            prospectSource.getFeatures().forEach(function(f) {
                if (f.getGeometry().getType() !== 'Point') return;
                var c   = f.getGeometry().getCoordinates();
                var key = c[0].toFixed(1) + ',' + c[1].toFixed(1);
                coordCountMap[key] = (coordCountMap[key] || 0) + 1;
            });
            prospectSource.getFeatures().forEach(function(f) {
                if (f.getGeometry().getType() !== 'Point') return;
                var c   = f.getGeometry().getCoordinates();
                var key = c[0].toFixed(1) + ',' + c[1].toFixed(1);
                f.set('stackCount', coordCountMap[key]);
            });
        })();
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
                        .then(function(blob) { tile.getImage().src = URL.createObjectURL(blob); });
                }
            })
        });

        /**
         * Popup elements.
         */
        var popupContainer = document.getElementById('popup');
        var popupContent   = document.getElementById('popup-content');
        var popupCloser    = document.getElementById('popup-closer');

        var popupOverlay = new ol.Overlay({
            element: popupContainer,
            autoPan: true,
            autoPanAnimation: { duration: 250 }
        });

        popupCloser.onclick = function() {
            popupOverlay.setPosition(undefined);
            popupCloser.blur();
            return false;
        };

        /**
         * Map view.
         */
        var mapView = new ol.View({ projection: 'EPSG:3857' });

        if (<?php print $num ?> == 1) {
            var feature     = prospectSource.getFeatures()[0];
            var coordinates = feature.getGeometry().getCoordinates();
            mapView.fit([coordinates[0], coordinates[1], coordinates[0], coordinates[1]], {
                padding: [50, 50, 50, 50],
                constrainResolution: false
            });
            mapView.setCenter(coordinates);
            mapView.setZoom(14);
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
         * Spider layer for stacked markers.
         */
        var spiderSource     = new ol.source.Vector();
        var spiderLayer      = new ol.layer.Vector({ source: spiderSource, zIndex: 10 });
        var spiderActive     = false;
        var spiderFeatureMap = {};
        map.addLayer(spiderLayer);

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
                spiderFeature.setStyle(markerStyles[feature.get('address')]);
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
         * Fit map to all markers.
         */
        if (<?php print $num ?> > 1) {
            var extent = limitExtent(prospectSource.getExtent());
            mapView.fit(extent, { padding: [50, 50, 50, 50], constrainResolution: false });
        }

        function limitExtent(extent) {
            var max = [-20037508.34, -20048966.1, 20037508.34, 20048966.1];
            for (var i = 0; i < 4; i++) {
                if (Math.abs(extent[i]) > Math.abs(max[i])) extent[i] = max[i];
            }
            return extent;
        }

        /**
         * Route layer.
         */
        var routeSource  = new ol.source.Vector();
        var routeLayer   = new ol.layer.Vector({ source: routeSource, zIndex: 2 });
        var routeVisible = false;
        map.addLayer(routeLayer);
        routeLayer.setVisible(false);

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
                .catch(function() { drawStraightRoute(ordered3857); });
        }

        document.getElementById('route-toggle-button').addEventListener('click', function() {
            routeVisible = !routeVisible;
            routeLayer.setVisible(routeVisible);
            this.classList.toggle('route-active', routeVisible);
            if (routeVisible && routeSource.getFeatures().length === 0) drawRoute();
        });

        /**
         * Geolocation control.
         */
        var geolocation = new ol.Geolocation({
            trackingOptions: { enableHighAccuracy: true },
            projection: mapView.getProjection()
        });
        geolocation.setTracking(true);

        geolocation.on('error', function(error) {
            console.error('Geolocation error: ' + error.message);
        });

        var positionFeature = new ol.Feature();
        var radiusFeature   = new ol.Feature();

        positionFeature.setProperties({ customText: '<?php print dol_escape_js($langs->trans('MyPosition')) ?>' });

        var geolocationLayer = new ol.layer.Vector({
            source: new ol.source.Vector({ features: [positionFeature, radiusFeature] })
        });
        map.addLayer(geolocationLayer);

        geolocation.on('change:position', function() {
            var coordinates = geolocation.getPosition();
            positionFeature.setGeometry(coordinates ? new ol.geom.Point(coordinates) : null);
            radiusFeature.setGeometry(new ol.geom.Circle(coordinates, 1000));
        });

        radiusFeature.setStyle(new ol.style.Style({
            stroke: new ol.style.Stroke({ color: 'rgba(0, 0, 255, 0.5)', width: 2 })
        }));

        positionFeature.setStyle(new ol.style.Style({
            image: new ol.style.Circle({
                radius: 6,
                fill: new ol.style.Fill({ color: '#3399CC' }),
                stroke: new ol.style.Stroke({ color: '#fff', width: 2 })
            })
        }));

        /**
         * Map click handler.
         */
        map.on('singleclick', function(evt) {
            if (spiderActive) {
                var spiderFeatureClicked = map.forEachFeatureAtPixel(evt.pixel, function(f) {
                    if (f.getId() && String(f.getId()).indexOf('spider_') === 0) return f;
                });
                if (spiderFeatureClicked) {
                    popupContent.innerHTML = spiderFeatureClicked.get('desc');
                    popupOverlay.setPosition(spiderFeatureClicked.getGeometry().getCoordinates());
                } else {
                    collapseSpider();
                }
                return;
            }

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

        document.getElementById('geolocate-button').addEventListener('click', function() {
            var coordinates = geolocation.getPosition();
            if (coordinates) {
                mapView.setCenter(coordinates);
                mapView.setZoom(14);
            }
        });
    </script>
<?php if ($user->hasRight('agenda', 'myactions', 'create')): ?>
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

require_once __DIR__ . '/../../core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php';

llxFooter();
$db->close();

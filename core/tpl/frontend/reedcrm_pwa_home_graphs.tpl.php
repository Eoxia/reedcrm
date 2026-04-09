<?php
/**
 * \file    core/tpl/frontend/reedcrm_pwa_home_graphs.tpl.php
 * \ingroup reedcrm
 * \brief   Graphs section for the PWA home page (Chart.js)
 */

global $db, $conf;

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

// Week offset: 0 = current week, -1 = previous, etc. Future is not allowed.
$weekOffset = (int) GETPOST('week_offset', 'int');
if ($weekOffset > 0) {
    $weekOffset = 0;
}

// Build 7-day range based on offset, excluding weekends
$days = [];
for ($i = 6; $i >= 0; $i--) {
    $shift   = $i + (-$weekOffset * 7);
    $ts      = dol_time_plus_duree(dol_now(), -$shift, 'd');
    if (in_array(date('N', $ts), [6, 7])) { // 6 = samedi, 7 = dimanche
        continue;
    }
    $dateKey        = dol_print_date($ts, '%Y-%m-%d');
    $days[$dateKey] = [
        'label'           => dol_print_date($ts, '%a %d/%m'),
        'full_date'       => dol_print_date($ts, '%d/%m/%Y'),
        'count'           => 0,
        'count_50'        => 0,
        'count_80'        => 0,
        'amount'          => 0.0,
        'weighted_amount' => 0.0,
    ];
}

$startDate = array_key_first($days);
$endDate   = array_key_last($days);

$safeStart = preg_replace('/[^0-9\-]/', '', $startDate);
$safeEnd   = preg_replace('/[^0-9\-]/', '', $endDate);

$filter = [
    'customsql' => "t.usage_opportunity = 1"
                 . " AND DATE(t.datec) >= '" . $safeStart . "'"
                 . " AND DATE(t.datec) <= '" . $safeEnd . "'",
];

$projects = saturne_fetch_all_object_type('Project', 'ASC', 't.datec', 0, 0, $filter);

if (is_array($projects) && !empty($projects)) {
    foreach ($projects as $project) {
        $dayKey = dol_print_date($project->datec, '%Y-%m-%d');
        if (!isset($days[$dayKey])) {
            continue;
        }
        $amount      = (float) $project->opp_amount;
        $probability = (float) $project->opp_percent;

        $days[$dayKey]['count']++;
        $days[$dayKey]['amount']          += $amount;
        $days[$dayKey]['weighted_amount'] += $amount * ($probability / 100.0);
        if ($probability > 50) {
            $days[$dayKey]['count_50']++;
        }
        if ($probability > 80) {
            $days[$dayKey]['count_80']++;
        }
    }
}

// Date range label
$dateRangeLabel = $days[$startDate]['full_date'] . ' — ' . $days[$endDate]['full_date'];

// Weekly totals for the stats widget
$weekTotalCount    = 0;
$weekTotalCount50  = 0;
$weekTotalCount80  = 0;
$weekTotalAmount   = 0.0;
$weekTotalWeighted = 0.0;
foreach ($days as $day) {
    $weekTotalCount    += $day['count'];
    $weekTotalCount50  += $day['count_50'];
    $weekTotalCount80  += $day['count_80'];
    $weekTotalAmount   += $day['amount'];
    $weekTotalWeighted += $day['weighted_amount'];
}

// Prepare JS arrays
$urlBase           = dol_buildpath('/projet/list.php', 1);
$currentPageUrl    = dol_buildpath('/custom/reedcrm/view/frontend/pwa_home.php', 1);
$jsLabels          = [];
$jsCounts          = [];
$jsCounts50        = [];
$jsCounts80        = [];
$jsAmounts         = [];
$jsWeightedAmounts = [];
$jsUrls            = [];
$jsUrlsWeighted    = [];
$jsUrls50          = [];
$jsUrls80          = [];

foreach ($days as $dateKey => $day) {
    $jsLabels[]          = $day['label'];
    $jsCounts[]          = $day['count'];
    $jsCounts50[]        = $day['count_50'];
    $jsCounts80[]        = $day['count_80'];
    $jsAmounts[]         = round($day['amount'], 2);
    $jsWeightedAmounts[] = round($day['weighted_amount'], 2);

    $d   = (int) date('j', strtotime($dateKey));
    $m   = (int) date('n', strtotime($dateKey));
    $y   = (int) date('Y', strtotime($dateKey));
    $dFr = date('d/m/Y', strtotime($dateKey));

    $dateFilter = '&search_date_creation_start=' . urlencode($dFr)
                . '&search_date_creation_startday=' . $d
                . '&search_date_creation_startmonth=' . $m
                . '&search_date_creation_startyear=' . $y
                . '&search_date_creation_end=' . urlencode($dFr)
                . '&search_date_creation_endday=' . $d
                . '&search_date_creation_endmonth=' . $m
                . '&search_date_creation_endyear=' . $y;

    $baseOpp    = $urlBase . '?search_usage_opportunity=1' . $dateFilter;

    $jsUrls[]          = $baseOpp;
    $jsUrlsWeighted[]  = $baseOpp . '&search_opp_percent=>0&search_opp_amount=>0';
    $jsUrls50[]        = $baseOpp . '&search_opp_percent=>50';
    $jsUrls80[]        = $baseOpp . '&search_opp_percent=>80';
}

$prevOffset = $weekOffset - 1;
$nextOffset = $weekOffset + 1;
$prevUrl    = $currentPageUrl . '?week_offset=' . $prevOffset;
$nextUrl    = $currentPageUrl . '?week_offset=' . $nextOffset;
?>

<div class="pwa-graphs-container">

    <div class="pwa-graphs-nav">
        <a href="<?= $prevUrl ?>" class="pwa-graphs-nav-btn">
            <i class="fas fa-chevron-left"></i>
        </a>
        <div class="pwa-graphs-nav-info">
            <span class="pwa-graphs-title"><i class="fas fa-handshake"></i> Opportunités</span>
            <span class="pwa-graphs-date-range"><?= $dateRangeLabel ?></span>
        </div>
        <a href="<?= $nextUrl ?>" class="pwa-graphs-nav-btn <?= $weekOffset >= 0 ? 'disabled' : '' ?>">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <div class="pwa-stats-widget">
        <div class="pwa-stat-item">
            <span class="pwa-stat-value"><?= $weekTotalCount ?></span>
            <span class="pwa-stat-label">Total</span>
        </div>
        <div class="pwa-stat-divider"></div>
        <div class="pwa-stat-item">
            <span class="pwa-stat-value pwa-stat-value--violet"><?= $weekTotalCount50 ?></span>
            <span class="pwa-stat-label">&gt; 50%</span>
        </div>
        <div class="pwa-stat-divider"></div>
        <div class="pwa-stat-item">
            <span class="pwa-stat-value pwa-stat-value--green"><?= $weekTotalCount80 ?></span>
            <span class="pwa-stat-label">&gt; 80%</span>
        </div>
        <div class="pwa-stat-divider"></div>
        <div class="pwa-stat-item">
            <span class="pwa-stat-value"><?= price($weekTotalAmount, 0, '', 1, -1, 0) ?> €</span>
            <span class="pwa-stat-label">Montant</span>
        </div>
        <div class="pwa-stat-divider"></div>
        <div class="pwa-stat-item">
            <span class="pwa-stat-value pwa-stat-value--teal"><?= price($weekTotalWeighted, 0, '', 1, -1, 0) ?> €</span>
            <span class="pwa-stat-label">Pondéré</span>
        </div>
    </div>

    <p class="pwa-graphs-chart-label">Montant total &amp; pondéré (€)</p>
    <div class="pwa-chart-wrapper">
        <canvas id="pwaHomeAmountChart"></canvas>
    </div>

    <p class="pwa-graphs-chart-label pwa-graphs-chart-label--spaced">Nombre d'opportunités</p>
    <div class="pwa-chart-wrapper">
        <canvas id="pwaHomeCountChart"></canvas>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    var labels          = <?= json_encode($jsLabels) ?>;
    var counts          = <?= json_encode($jsCounts) ?>;
    var counts50        = <?= json_encode($jsCounts50) ?>;
    var counts80        = <?= json_encode($jsCounts80) ?>;
    var amounts         = <?= json_encode($jsAmounts) ?>;
    var weightedAmounts = <?= json_encode($jsWeightedAmounts) ?>;
    var urls            = <?= json_encode($jsUrls) ?>;
    var urlsWeighted    = <?= json_encode($jsUrlsWeighted) ?>;
    var urls50          = <?= json_encode($jsUrls50) ?>;
    var urls80          = <?= json_encode($jsUrls80) ?>;

    function makeClickHandler(urlList) {
        return function (event, elements) {
            if (elements.length > 0) {
                window.open(urlList[elements[0].index], '_blank');
            }
        };
    }

    function makeHoverHandler() {
        return function (event, elements) {
            event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
        };
    }

    var commonDatasetProps = {
        borderWidth: 2,
        borderRadius: 4,
        borderSkipped: false,
        barPercentage: 0.7,
        categoryPercentage: 0.75
    };

    var commonScales = {
        y: {
            beginAtZero: true,
            grid: { color: 'rgba(0,0,0,0.05)' },
            ticks: { font: { size: 9 }, color: '#64748b', maxTicksLimit: 4 }
        },
        x: {
            grid: { display: false },
            ticks: { font: { size: 9 }, color: '#64748b' }
        }
    };

    var commonTooltip = {
        callbacks: { footer: function () { return 'Cliquez pour ouvrir'; } }
    };

    // --- Graph 1 : Montant total + Montant pondéré ---
    new Chart(document.getElementById('pwaHomeAmountChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                Object.assign({ label: 'Montant total (€)',   data: amounts,         backgroundColor: '#A7C7E7', borderColor: '#6fa8d6' }, commonDatasetProps),
                Object.assign({ label: 'Montant pondéré (€)', data: weightedAmounts, backgroundColor: '#A8E6CF', borderColor: '#5dcca7' }, commonDatasetProps)
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { font: { size: 9 }, color: '#64748b', boxWidth: 8, padding: 6 }
                },
                tooltip: commonTooltip
            },
            scales: commonScales,
            onClick: function (event, elements) {
                if (elements.length === 0) return;
                var urlMap = [urls, urlsWeighted];
                var urlList = urlMap[elements[0].datasetIndex] || urls;
                window.open(urlList[elements[0].index], '_blank');
            },
            onHover: makeHoverHandler()
        }
    });

    // --- Graph 2 : Nombre d'opportunités ---
    new Chart(document.getElementById('pwaHomeCountChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                Object.assign({ label: 'Total', data: counts,   backgroundColor: '#A7C7E7', borderColor: '#6fa8d6' }, commonDatasetProps),
                Object.assign({ label: '> 50%', data: counts50, backgroundColor: '#CDB4DB', borderColor: '#a67fc4' }, commonDatasetProps),
                Object.assign({ label: '> 80%', data: counts80, backgroundColor: '#A8E6CF', borderColor: '#5dcca7' }, commonDatasetProps)
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: { font: { size: 9 }, color: '#64748b', boxWidth: 8, padding: 6 }
                },
                tooltip: commonTooltip
            },
            scales: Object.assign({}, commonScales, {
                y: Object.assign({}, commonScales.y, {
                    ticks: { stepSize: 1, font: { size: 11 }, color: '#64748b' }
                })
            }),
            onClick: function (event, elements) {
                if (elements.length === 0) return;
                var urlMap = [urls, urls50, urls80];
                var datasetIndex = elements[0].datasetIndex;
                var index        = elements[0].index;
                var urlList      = urlMap[datasetIndex] || urls;
                window.open(urlList[index], '_blank');
            },
            onHover: makeHoverHandler()
        }
    });
})();
</script>

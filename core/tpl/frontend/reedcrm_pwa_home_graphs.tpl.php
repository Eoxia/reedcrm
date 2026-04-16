<?php
/**
 * \file    core/tpl/frontend/reedcrm_pwa_home_graphs.tpl.php
 * \ingroup reedcrm
 * \brief   Graphs section for the PWA home page (Chart.js)
 */

global $db, $conf, $user;

require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

// -----------------------------------------------------------------------
// Resolve the displayed week from the week_start param (YYYY-MM-DD Monday)
// Fall back to the current week's Monday when the param is absent/invalid.
// -----------------------------------------------------------------------
$currentPageUrl = dol_buildpath('/custom/reedcrm/view/frontend/pwa_home.php', 1);

// Current Monday (midnight local)
$nowTs       = dol_now();
$todayDow    = (int) date('N', $nowTs); // 1 = Mon … 7 = Sun
$currentMondayTs = mktime(0, 0, 0, (int) date('n', $nowTs), (int) date('j', $nowTs) - ($todayDow - 1), (int) date('Y', $nowTs));

// Parse week_start param
$weekStartParam = GETPOST('week_start', 'alpha');
if (!empty($weekStartParam) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartParam)) {
    $parsedTs = strtotime($weekStartParam);
    // Snap to the Monday of that week
    $parsedDow    = (int) date('N', $parsedTs);
    $weekStartTs  = mktime(0, 0, 0, (int) date('n', $parsedTs), (int) date('j', $parsedTs) - ($parsedDow - 1), (int) date('Y', $parsedTs));
} else {
    $weekStartTs = $currentMondayTs;
}

// Clamp: never allow a future week
if ($weekStartTs > $currentMondayTs) {
    $weekStartTs = $currentMondayTs;
}

// Build weekdays (Mon–Fri) for this week
$days = [];
for ($i = 0; $i < 7; $i++) {
    $ts  = $weekStartTs + $i * 86400;
    $dow = (int) date('N', $ts);
    if ($dow >= 6) { // Saturday or Sunday
        continue;
    }
    $dateKey        = date('Y-m-d', $ts);
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

// User filter
$graphsFilterUserId = getDolUserInt('REEDCRM_PWA_FILTER_USER_ID', 0, $user);
$userFilterSql      = $graphsFilterUserId > 0 ? " AND t.fk_user_creat = " . (int) $graphsFilterUserId : '';

$filter = [
    'customsql' => "t.usage_opportunity = 1"
                 . $userFilterSql
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

        $days[$dayKey]['amount']          += $amount;
        $days[$dayKey]['weighted_amount'] += $amount * ($probability / 100.0);
        if ($probability <= 50) {
            $days[$dayKey]['count']++;
        }
        if ($probability > 50 && $probability <= 80) {
            $days[$dayKey]['count_50']++;
        }
        if ($probability > 80) {
            $days[$dayKey]['count_80']++;
        }
    }
}

// Date range label
$dateRangeLabel = $days[$startDate]['full_date'] . ' — ' . $days[$endDate]['full_date'];

// Weekly totals
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

    $baseOpp = $urlBase . '?search_usage_opportunity=1' . $dateFilter;

    $jsUrls[]         = $baseOpp . '&search_opp_percent=' . urlencode('<=50');
    $jsUrlsWeighted[] = $baseOpp . '&search_opp_percent=>0&search_opp_amount=>0';
    $jsUrls50[]       = $baseOpp . '&search_opp_percent=' . urlencode('>50 <=80');
    $jsUrls80[]       = $baseOpp . '&search_opp_percent=' . urlencode('>80');
}

// Navigation URLs
$prevWeekStart = date('Y-m-d', $weekStartTs - 7 * 86400);
$nextWeekStart = date('Y-m-d', $weekStartTs + 7 * 86400);
$isCurrentWeek = ($weekStartTs >= $currentMondayTs);
$prevUrl       = $currentPageUrl . '?week_start=' . $prevWeekStart;
$nextUrl       = $isCurrentWeek ? '' : $currentPageUrl . '?week_start=' . $nextWeekStart;

// Input[type=week] value format: YYYY-Www
$weekInputVal = date('Y', $weekStartTs) . '-W' . date('W', $weekStartTs);
$maxWeekVal   = date('Y', $currentMondayTs) . '-W' . date('W', $currentMondayTs);
?>

<div class="pwa-graphs-container">

    <div class="pwa-graphs-nav">
        <a href="<?= dol_escape_htmltag($prevUrl) ?>" class="pwa-graphs-nav-btn">
            <i class="fas fa-chevron-left"></i>
        </a>

        <div class="pwa-graphs-nav-info">
            <span class="pwa-graphs-title"><i class="fas fa-handshake"></i> Opportunités</span>
            <label class="pwa-week-picker-wrap" title="Choisir une semaine">
                <input
                    type="week"
                    id="pwa-week-picker"
                    value="<?= dol_escape_htmltag($weekInputVal) ?>"
                    max="<?= dol_escape_htmltag($maxWeekVal) ?>"
                    data-page-url="<?= dol_escape_htmltag($currentPageUrl) ?>"
                    class="pwa-week-picker-input">
                <span class="pwa-graphs-date-range">
                    <?= dol_escape_htmltag($dateRangeLabel) ?>
                    <i class="fas fa-calendar-alt pwa-week-picker-icon"></i>
                </span>
            </label>
        </div>

        <?php if ($isCurrentWeek) { ?>
        <span class="pwa-graphs-nav-btn disabled">
            <i class="fas fa-chevron-right"></i>
        </span>
        <?php } else { ?>
        <a href="<?= dol_escape_htmltag($nextUrl) ?>" class="pwa-graphs-nav-btn">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php } ?>
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
                var urlMap  = [urls, urlsWeighted];
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
                var urlMap       = [urls, urls50, urls80];
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

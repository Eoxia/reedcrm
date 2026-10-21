<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/index/reedcrm_home_dashboard.tpl.php
 * \ingroup reedcrm
 * \brief   Home page of ReedCRM: key figures, revenue, pipeline, signed proposals, payments, reminders, last actions
 *          and last sale of each product
 */

/**
 * The following variables must be defined by the caller:
 *
 * @var Conf      $conf
 * @var DoliDB    $db
 * @var Translate $langs
 * @var array     $home Data of the home page, from ReedcrmHomeDashboard::load_dashboard()
 */

// The chart draws in a fixed box stretched over the plot: a month is 100 units wide, the plot 300 units high
$chartHeight = 300;

$periods = ['month' => 'SalesPeriodMonth', 'quarter' => 'SalesPeriodQuarter', 'year' => 'SalesPeriodYear'];
$scopes  = ['all' => 'DirectionWholeCompany', 'me' => 'HomeScopeMine'];
?>
<div class="reedcrm-home">
    <div class="reedcrm-home__toolbar">
        <div class="reedcrm-home__range">
            <i class="far fa-calendar-alt"></i>
            <?php echo $langs->trans('HomeRange', dol_print_date($home['periodStart'], 'day', 'tzuserrel'), dol_print_date($home['periodEnd'], 'day', 'tzuserrel')); ?>
        </div>
        <div class="reedcrm-home__filters">
            <nav class="reedcrm-home-segmented" aria-label="<?php echo dol_escape_htmltag($langs->trans('SalesPeriodFilter')); ?>">
                <?php foreach ($periods as $periodKey => $periodLabel) : ?>
                    <a class="reedcrm-home-segmented__item<?php echo $home['period'] === $periodKey ? ' is-active' : ''; ?>" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF'] . '?' . http_build_query(['mainmenu' => 'reedcrm', 'period' => $periodKey, 'scope' => $home['scope']])); ?>"><?php echo $langs->trans($periodLabel); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php if ($home['canReadTeam']) : ?>
                <nav class="reedcrm-home-segmented" aria-label="<?php echo dol_escape_htmltag($langs->trans('SalesUserFilter')); ?>">
                    <?php foreach ($scopes as $scopeKey => $scopeLabel) : ?>
                        <a class="reedcrm-home-segmented__item<?php echo $home['scope'] === $scopeKey ? ' is-active' : ''; ?>" href="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF'] . '?' . http_build_query(['mainmenu' => 'reedcrm', 'period' => $home['period'], 'scope' => $scopeKey])); ?>"><?php echo $langs->trans($scopeLabel); ?></a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($home['kpis'])) : ?>
        <div class="reedcrm-home__kpis">
            <?php foreach ($home['kpis'] as $kpi) : ?>
                <a class="reedcrm-home-kpi reedcrm-home-kpi--<?php echo $kpi['tone']; ?>" href="<?php echo dol_escape_htmltag($kpi['url']); ?>">
                    <span class="reedcrm-home-kpi__head">
                        <span class="reedcrm-home-kpi__icon"><i class="<?php echo $kpi['icon']; ?>"></i></span>
                        <span class="reedcrm-home-kpi__title"><?php echo $kpi['title']; ?></span>
                    </span>
                    <span class="reedcrm-home-kpi__value"><?php echo $kpi['value']; ?></span>
                    <?php if (!empty($kpi['evolution'])) : ?>
                        <span class="reedcrm-home-kpi__trend"><?php echo $kpi['evolution']; ?><span class="reedcrm-home-kpi__compare"><?php echo $langs->trans('HomeComparedWith' . ucfirst($home['period'])); ?></span></span>
                    <?php endif; ?>
                    <span class="reedcrm-home-kpi__detail"><?php echo $kpi['detail']; ?></span>
                    <?php if (!empty($kpi['alert'])) : ?>
                        <span class="reedcrm-home-kpi__alert"><i class="fas fa-exclamation-triangle"></i><?php echo $kpi['alert']; ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="reedcrm-home__grid">
        <?php if (!empty($home['revenueChart'])) :
            $chart = $home['revenueChart']; ?>
            <section class="reedcrm-home-card reedcrm-home-card--wide">
                <header class="reedcrm-home-card__header">
                    <h3 class="reedcrm-home-card__title"><i class="fas fa-chart-bar"></i><?php echo $langs->trans('HomeRevenueChart'); ?></h3>
                    <span class="reedcrm-home-card__figure"><?php echo $chart['total'] . ' ' . $chart['evolution']; ?></span>
                </header>
                <div class="reedcrm-home-legend">
                    <span class="reedcrm-home-legend__item reedcrm-home-legend__item--current"><?php echo $langs->trans('HomeRevenueChartCurrent'); ?></span>
                    <span class="reedcrm-home-legend__item reedcrm-home-legend__item--previous"><?php echo $langs->trans('HomeRevenueChartPrevious'); ?></span>
                </div>
                <div class="reedcrm-home-chart">
                    <div class="reedcrm-home-chart__ticks">
                        <?php foreach ($chart['ticks'] as $tick) : ?>
                            <span class="reedcrm-home-chart__tick"><?php echo $tick; ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="reedcrm-home-chart__plot">
                        <svg class="reedcrm-home-chart__bars" viewBox="0 0 <?php echo count($chart['months']) * 100; ?> <?php echo $chartHeight; ?>" preserveAspectRatio="none" role="img" aria-label="<?php echo dol_escape_htmltag($langs->trans('HomeRevenueChart')); ?>">
                            <?php foreach ($chart['months'] as $index => $month) :
                                $previousHeight = $chart['scale'] > 0 ? round(max(0, $month['previous']) / $chart['scale'] * $chartHeight, 1) : 0;
                                $currentHeight  = $chart['scale'] > 0 ? round(max(0, $month['current']) / $chart['scale'] * $chartHeight, 1) : 0; ?>
                                <rect class="reedcrm-home-chart__bar reedcrm-home-chart__bar--previous" x="<?php echo $index * 100 + 16; ?>" y="<?php echo $chartHeight - $previousHeight; ?>" width="32" height="<?php echo $previousHeight; ?>"></rect>
                                <rect class="reedcrm-home-chart__bar reedcrm-home-chart__bar--current" x="<?php echo $index * 100 + 52; ?>" y="<?php echo $chartHeight - $currentHeight; ?>" width="32" height="<?php echo $currentHeight; ?>"></rect>
                                <rect class="reedcrm-home-chart__hit" x="<?php echo $index * 100; ?>" y="0" width="100" height="<?php echo $chartHeight; ?>"
                                      data-index="<?php echo $index; ?>"
                                      data-month="<?php echo dol_escape_htmltag(ucfirst($month['name'])); ?>"
                                      data-title="<?php echo dol_escape_htmltag(ucfirst($month['title'])); ?>"
                                      data-current="<?php echo dol_escape_htmltag(html_entity_decode($month['currentFormatted'], ENT_QUOTES, 'UTF-8')); ?>"
                                      data-previous-title="<?php echo dol_escape_htmltag(ucfirst($month['previousTitle'])); ?>"
                                      data-previous="<?php echo dol_escape_htmltag(html_entity_decode($month['previousFormatted'], ENT_QUOTES, 'UTF-8')); ?>"
                                      data-evolution="<?php echo dol_escape_htmltag($month['evolution']); ?>"></rect>
                            <?php endforeach; ?>
                        </svg>
                        <div class="reedcrm-home-chart__tooltip" hidden></div>
                    </div>
                    <div class="reedcrm-home-chart__months">
                        <?php foreach ($chart['months'] as $month) : ?>
                            <span><?php echo dol_escape_htmltag($month['label']); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <details class="reedcrm-home-chart__table">
                    <summary><?php echo $langs->trans('HomeRevenueChartDetail'); ?></summary>
                    <div class="div-table-responsive-no-min">
                        <table class="noborder centpercent">
                            <tr class="liste_titre">
                                <td><?php echo $langs->trans('Month'); ?></td>
                                <td class="right"><?php echo $langs->trans('HomeRevenueChartCurrent'); ?></td>
                                <td class="right"><?php echo $langs->trans('HomeRevenueChartPrevious'); ?></td>
                                <td class="right"><?php echo $langs->trans('HomeChange'); ?></td>
                            </tr>
                            <?php foreach ($chart['months'] as $month) : ?>
                                <tr class="oddeven">
                                    <td><?php echo dol_escape_htmltag(ucfirst($month['title'])); ?></td>
                                    <td class="right nowraponall"><?php echo $month['currentFormatted']; ?></td>
                                    <td class="right nowraponall"><?php echo $month['previousFormatted']; ?></td>
                                    <td class="right nowraponall"><?php echo $month['evolution']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                </details>
            </section>
        <?php endif; ?>

        <?php if (!empty($home['pipeline'])) :
            $pipeline = $home['pipeline']; ?>
            <section class="reedcrm-home-card reedcrm-home-card--narrow">
                <header class="reedcrm-home-card__header">
                    <h3 class="reedcrm-home-card__title"><i class="fas fa-filter"></i><?php echo $langs->trans('HomePipelineTitle'); ?></h3>
                    <a class="reedcrm-home-card__more" href="<?php echo DOL_URL_ROOT . '/projet/list.php?search_opp_status=openedopp'; ?>"><?php echo $langs->trans('HomeSeeAll'); ?><i class="fas fa-arrow-right"></i></a>
                </header>
                <?php if ($pipeline['nb'] > 0) : ?>
                    <div class="reedcrm-home-legend">
                        <span class="reedcrm-home-legend__item reedcrm-home-legend__item--amount"><?php echo $langs->trans('HomePipelineAmount'); ?></span>
                        <span class="reedcrm-home-legend__item reedcrm-home-legend__item--weighted"><?php echo $langs->trans('HomePipelineWeighted'); ?></span>
                    </div>
                    <ul class="reedcrm-home-pipe">
                        <?php foreach ($pipeline['stages'] as $stage) : ?>
                            <li>
                                <a class="reedcrm-home-pipe__stage" href="<?php echo dol_escape_htmltag($stage['url']); ?>">
                                    <span class="reedcrm-home-pipe__label"><?php echo dol_escape_htmltag($stage['label']); ?><span class="reedcrm-home-pipe__count"><?php echo $stage['nb']; ?></span></span>
                                    <span class="reedcrm-home-pipe__amount"><?php echo price(round($stage['amount']), 0, $langs, 1, -1, 0, $conf->currency); ?></span>
                                    <svg class="reedcrm-home-pipe__bar" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true">
                                        <rect class="reedcrm-home-pipe__amount-bar" width="<?php echo $pipeline['max'] > 0 ? round($stage['amount'] / $pipeline['max'] * 100, 1) : 0; ?>" height="10"></rect>
                                        <rect class="reedcrm-home-pipe__weighted-bar" width="<?php echo $pipeline['max'] > 0 ? round($stage['weighted'] / $pipeline['max'] * 100, 1) : 0; ?>" height="10"></rect>
                                    </svg>
                                    <?php if ($stage['weighted'] > 0) : ?>
                                        <span class="reedcrm-home-pipe__weighted"><?php echo $langs->trans('HomePipelineWeightedValue', price(round($stage['weighted']), 0, $langs, 1, -1, 0, $conf->currency)); ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="reedcrm-home-empty"><i class="fas fa-filter"></i><?php echo $langs->trans('HomePipelineEmpty'); ?></div>
                <?php endif; ?>
                <footer class="reedcrm-home-card__footer">
                    <i class="fas fa-trophy"></i><?php echo $langs->trans('HomePipelineWon', (string) $pipeline['wonNb'], price(round($pipeline['wonAmount']), 0, $langs, 1, -1, 0, $conf->currency)); ?>
                </footer>
            </section>
        <?php endif; ?>

        <?php if (!empty($home['signed'])) :
            $signed = $home['signed']; ?>
            <section class="reedcrm-home-card reedcrm-home-card--third">
                <header class="reedcrm-home-card__header">
                    <h3 class="reedcrm-home-card__title"><i class="fas fa-file-signature"></i><?php echo $langs->trans('HomeSignedTitle'); ?></h3>
                    <a class="reedcrm-home-card__more" href="<?php echo dol_escape_htmltag($signed['url']); ?>"><?php echo $langs->trans('HomeSeeAll') . ($signed['nb'] > 0 ? ' (' . $signed['nb'] . ')' : ''); ?><i class="fas fa-arrow-right"></i></a>
                </header>
                <?php if (!empty($signed['rows'])) : ?>
                    <ul class="reedcrm-home-list">
                        <?php foreach ($signed['rows'] as $row) : ?>
                            <li class="reedcrm-home-list__item">
                                <span class="reedcrm-home-list__main">
                                    <span class="reedcrm-home-list__title"><?php echo $row['link']; ?></span>
                                    <span class="reedcrm-home-list__meta"><?php echo $row['thirdparty']; ?><span class="reedcrm-home-list__date"><?php echo dol_print_date($row['date'], 'day', 'tzuserrel'); ?></span></span>
                                </span>
                                <span class="reedcrm-home-list__value"><?php echo $row['amount']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="reedcrm-home-empty"><i class="far fa-file-alt"></i><?php echo $langs->trans('HomeSignedEmpty'); ?></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($home['payments'])) :
            $payments = $home['payments']; ?>
            <section class="reedcrm-home-card reedcrm-home-card--third">
                <header class="reedcrm-home-card__header">
                    <h3 class="reedcrm-home-card__title"><i class="fas fa-hand-holding-usd"></i><?php echo $langs->trans('HomePaymentsTitle'); ?></h3>
                    <a class="reedcrm-home-card__more" href="<?php echo dol_escape_htmltag($payments['url']); ?>"><?php echo $langs->trans('HomeSeeAll') . ($payments['nb'] > 0 ? ' (' . $payments['nb'] . ')' : ''); ?><i class="fas fa-arrow-right"></i></a>
                </header>
                <?php if (!empty($payments['rows'])) : ?>
                    <ul class="reedcrm-home-list">
                        <?php foreach ($payments['rows'] as $row) : ?>
                            <li class="reedcrm-home-list__item">
                                <span class="reedcrm-home-list__main">
                                    <span class="reedcrm-home-list__title"><?php echo $row['thirdparty']; ?></span>
                                    <span class="reedcrm-home-list__meta"><?php echo $row['invoice']; ?><span class="reedcrm-home-list__date"><?php echo dol_print_date($row['date'], 'day', 'tzuserrel'); ?></span></span>
                                </span>
                                <a class="reedcrm-home-list__value" href="<?php echo dol_escape_htmltag($row['url']); ?>"><?php echo $row['amount']; ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="reedcrm-home-empty"><i class="fas fa-coins"></i><?php echo $langs->trans('HomePaymentsEmpty'); ?></div>
                <?php endif; ?>
                <?php if ($payments['lateNb'] > 0) : ?>
                    <footer class="reedcrm-home-card__footer reedcrm-home-card__footer--alert">
                        <a href="<?php echo dol_escape_htmltag($payments['lateUrl']); ?>"><i class="fas fa-exclamation-triangle"></i><?php echo $langs->trans('HomeLateInvoices', (string) $payments['lateNb'], price(round($payments['lateAmount']), 0, $langs, 1, -1, 0, $conf->currency)); ?></a>
                    </footer>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="reedcrm-home-card reedcrm-home-card--third reedcrm-reminders">
            <header class="reedcrm-home-card__header">
                <h3 class="reedcrm-home-card__title"><i class="fas fa-bell"></i><?php echo $langs->trans('UpcomingCallReminders'); ?></h3>
                <?php if (!empty($home['reminders'])) : ?>
                    <span class="reedcrm-reminders__count"><?php echo count($home['reminders']); ?></span>
                <?php endif; ?>
            </header>
            <?php if (empty($home['reminders'])) : ?>
                <div class="reedcrm-home-empty"><i class="far fa-bell-slash"></i><?php echo $langs->trans('NoUpcomingCallReminder'); ?></div>
            <?php else : ?>
                <ul class="reedcrm-reminders__list">
                    <?php foreach ($home['reminders'] as $reminder) :
                        $reminderTs = $db->jdate($reminder->datep); ?>
                        <li class="reedcrm-reminders__item">
                            <span class="reedcrm-reminders__tile">
                                <span class="reedcrm-reminders__day"><?php echo dol_print_date($reminderTs, '%d', 'tzuserrel'); ?></span>
                                <span class="reedcrm-reminders__month"><?php echo rtrim($langs->trans('MonthShort' . dol_print_date($reminderTs, '%m', 'tzuserrel')), '.'); ?></span>
                            </span>
                            <span class="reedcrm-reminders__content">
                                <a class="reedcrm-reminders__label" href="<?php echo DOL_URL_ROOT . '/comm/action/card.php?id=' . ((int) $reminder->id); ?>"><?php echo dol_escape_htmltag($reminder->label); ?></a>
                                <span class="reedcrm-reminders__meta">
                                    <span class="reedcrm-reminders__time"><i class="far fa-clock"></i><?php echo dol_print_date($reminderTs, '%H:%M', 'tzuserrel'); ?></span>
                                    <?php if (!empty($reminder->fk_soc)) : ?>
                                        <a class="reedcrm-reminders__thirdparty" href="<?php echo DOL_URL_ROOT . '/societe/card.php?socid=' . ((int) $reminder->fk_soc); ?>"><i class="fas fa-building"></i><?php echo dol_escape_htmltag($reminder->thirdparty_name); ?></a>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if (is_array($home['lastEvents'])) : ?>
            <section class="reedcrm-home-card reedcrm-home-card--half">
                <header class="reedcrm-home-card__header">
                    <h3 class="reedcrm-home-card__title"><i class="fas fa-history"></i><?php echo $langs->trans('HomeLastEvents'); ?></h3>
                    <a class="reedcrm-home-card__more" href="<?php echo DOL_URL_ROOT . '/comm/action/list.php?mode=show_list&status=done'; ?>"><?php echo $langs->trans('HomeSeeAgenda'); ?><i class="fas fa-arrow-right"></i></a>
                </header>
                <?php if (!empty($home['lastEvents'])) : ?>
                    <ul class="reedcrm-home-feed">
                        <?php foreach ($home['lastEvents'] as $event) : ?>
                            <li class="reedcrm-home-feed__item">
                                <span class="reedcrm-home-feed__icon" title="<?php echo dol_escape_htmltag($event['type']); ?>"><i class="<?php echo $event['icon']; ?>"></i></span>
                                <span class="reedcrm-home-feed__main">
                                    <a class="reedcrm-home-feed__label" href="<?php echo dol_escape_htmltag($event['url']); ?>"><?php echo dol_escape_htmltag($event['label']); ?></a>
                                    <?php if (!empty($event['thirdparty']) || !empty($event['project'])) : ?>
                                        <span class="reedcrm-home-feed__meta"><?php echo $event['thirdparty'] . $event['project']; ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="reedcrm-home-feed__aside">
                                    <span class="reedcrm-home-feed__date"><?php echo $event['date']; ?></span>
                                    <?php if (!empty($event['user'])) {
                                        echo $event['user']->getNomUrl(-2);
                                    } ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else : ?>
                    <div class="reedcrm-home-empty"><i class="fas fa-history"></i><?php echo $langs->trans('HomeLastEventsEmpty'); ?></div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($home['products'])) :
            $products = $home['products']; ?>
            <section class="reedcrm-home-card reedcrm-home-card--half reedcrm-home-products">
                <header class="reedcrm-home-card__header">
                    <h3 class="reedcrm-home-card__title"><i class="fas fa-box"></i><?php echo $langs->trans('HomeProductSales'); ?></h3>
                    <?php if ($products['hasStock']) : ?>
                        <div class="reedcrm-home-tabs" role="tablist">
                            <button type="button" class="reedcrm-home-tabs__tab is-active" role="tab" aria-selected="true" data-tab="recent"><?php echo $langs->trans('HomeProductRecent'); ?></button>
                            <button type="button" class="reedcrm-home-tabs__tab" role="tab" aria-selected="false" data-tab="dormant"><?php echo $langs->trans('HomeProductDormant', (string) $products['dormantMonths']); ?><span class="reedcrm-home-tabs__count"><?php echo $products['nbDormant']; ?></span></button>
                        </div>
                    <?php endif; ?>
                </header>
                <div class="reedcrm-home-tabs__panel" data-panel="recent">
                    <?php if (!empty($products['recent'])) : ?>
                        <div class="div-table-responsive-no-min">
                            <table class="reedcrm-home-table">
                                <thead>
                                    <tr>
                                        <th><?php echo $langs->trans('Product'); ?></th>
                                        <th class="reedcrm-home-table__date"><?php echo $langs->trans('HomeLastSale'); ?></th>
                                        <th class="reedcrm-home-table__customer"><?php echo $langs->trans('Customer'); ?></th>
                                        <th class="reedcrm-home-table__qty right"><?php echo $langs->trans('Qty'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products['recent'] as $row) : ?>
                                        <tr>
                                            <td><?php echo $row['product']; ?></td>
                                            <td class="nowraponall">
                                                <?php echo dol_print_date($row['date'], 'day', 'tzuserrel'); ?>
                                                <span class="reedcrm-home-table__sub"><?php echo $row['days'] > 0 ? $langs->trans('HomeDaysAgo', (string) $row['days']) : $langs->trans('Today'); ?></span>
                                            </td>
                                            <td class="reedcrm-home-table__customer">
                                                <?php echo $row['thirdparty']; ?>
                                                <span class="reedcrm-home-table__sub"><?php echo $row['document']; ?></span>
                                            </td>
                                            <td class="right nowraponall"><?php echo price($row['qty'], 0, $langs, 0, 0, floor($row['qty']) == $row['qty'] ? 0 : 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else : ?>
                        <div class="reedcrm-home-empty"><i class="fas fa-box-open"></i><?php echo $langs->trans('HomeProductEmpty'); ?></div>
                    <?php endif; ?>
                </div>
                <?php if ($products['hasStock']) : ?>
                    <div class="reedcrm-home-tabs__panel" data-panel="dormant" hidden>
                        <?php if (!empty($products['dormant'])) : ?>
                            <div class="div-table-responsive-no-min">
                                <table class="reedcrm-home-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo $langs->trans('Product'); ?></th>
                                            <th class="reedcrm-home-table__date"><?php echo $langs->trans('HomeLastSale'); ?></th>
                                            <th class="reedcrm-home-table__qty right"><?php echo $langs->trans('Stock'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($products['dormant'] as $row) : ?>
                                            <tr>
                                                <td><?php echo $row['product']; ?></td>
                                                <td class="nowraponall">
                                                    <?php if ($row['date'] > 0) : ?>
                                                        <?php echo dol_print_date($row['date'], 'day', 'tzuserrel'); ?>
                                                        <span class="reedcrm-home-table__sub"><?php echo $langs->trans('HomeDaysAgo', (string) $row['days']); ?></span>
                                                    <?php else : ?>
                                                        <span class="opacitymedium"><?php echo $langs->trans('HomeNeverSold'); ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="right nowraponall"><?php echo price($row['stock'], 0, $langs, 0, 0, floor($row['stock']) == $row['stock'] ? 0 : 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($products['nbDormant'] > count($products['dormant'])) : ?>
                                <footer class="reedcrm-home-card__footer">
                                    <?php echo $langs->trans('HomeProductDormantMore', (string) ($products['nbDormant'] - count($products['dormant']))); ?>
                                </footer>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="reedcrm-home-empty"><i class="fas fa-check"></i><?php echo $langs->trans('HomeProductDormantEmpty'); ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>

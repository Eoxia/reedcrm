<?php
/**
 * \file    core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php
 * \ingroup reedcrm
 * \brief   Bottom navigation bar for mobile/App frontend pages (per-user favorites + burger drawer)
 */

require_once __DIR__ . '/../../../lib/reedcrm_pwa_nav.lib.php';

global $user;

$navItems     = reedcrm_pwa_nav_get_items();
$navFavorites = reedcrm_pwa_nav_get_favorites($user);

// Find active tab based on the current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="pwa-bottom-nav">
    <div class="nav-group nav-favorites">
        <?php foreach ($navFavorites as $navSlug) {
            $navItem = $navItems[$navSlug]; ?>
        <a href="<?= $navItem['url'] ?>" class="pwa-nav-item <?= ($currentPage == $navItem['page']) ? 'active' : '' ?>" data-nav-slug="<?= $navSlug ?>">
            <i class="fas <?= $navItem['icon'] ?>"></i>
            <span><?= $navItem['label'] ?></span>
        </a>
        <?php } ?>
    </div>

    <button type="button" class="pwa-nav-item pwa-nav-burger" data-action="toggle-pwa-nav-drawer" aria-expanded="false" aria-label="Ouvrir le menu">
        <i class="fas fa-bars"></i>
        <span>Menu</span>
    </button>
</nav>

<div class="pwa-nav-drawer-overlay" data-action="close-pwa-nav-drawer"></div>
<div class="pwa-nav-drawer" data-ajax-url="<?= dol_buildpath('/custom/reedcrm/ajax/save_pwa_nav_favorites.php', 1) ?>" data-max-favorites="<?= REEDCRM_PWA_NAV_MAX_FAVORITES ?>">
    <div class="pwa-nav-drawer-header">
        <span class="pwa-nav-drawer-title">Menu</span>
        <span class="pwa-nav-drawer-hint"><i class="fas fa-star"></i> Favoris affichés en bas (max <?= REEDCRM_PWA_NAV_MAX_FAVORITES ?>)</span>
    </div>
    <div class="pwa-nav-drawer-grid">
        <?php foreach ($navItems as $navSlug => $navItem) {
            $isNavFavorite = in_array($navSlug, $navFavorites, true); ?>
        <div class="pwa-nav-drawer-item <?= ($currentPage == $navItem['page']) ? 'active' : '' ?>" data-nav-slug="<?= $navSlug ?>">
            <a href="<?= $navItem['url'] ?>" class="pwa-nav-drawer-link">
                <i class="fas <?= $navItem['icon'] ?>"></i>
                <span><?= $navItem['label'] ?></span>
            </a>
            <button type="button" class="pwa-nav-fav-toggle<?= $isNavFavorite ? ' is-favorite' : '' ?>" data-action="toggle-pwa-nav-favorite" aria-pressed="<?= $isNavFavorite ? 'true' : 'false' ?>" aria-label="<?= $isNavFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>">
                <i class="fas fa-star"></i>
            </button>
        </div>
        <?php } ?>
    </div>
</div>

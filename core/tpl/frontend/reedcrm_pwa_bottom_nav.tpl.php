<?php
/**
 * \file    core/tpl/frontend/reedcrm_pwa_bottom_nav.tpl.php
 * \ingroup reedcrm
 * \brief   Bottom navigation bar for mobile/App frontend pages
 */

$urlBase = dol_buildpath('/custom/reedcrm/view/frontend/', 1);

// Find active tab based on the current page
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="pwa-bottom-nav">
    <div class="nav-group nav-left">
        <a href="<?= $urlBase ?>quickcreation.php?source=pwa" class="pwa-nav-item <?= ($currentPage == 'quickcreation.php') ? 'active' : '' ?>">
            <i class="fas fa-handshake"></i>
            <span>+ Opp</span>
        </a>
        <a href="<?= $urlBase ?>pwa_projets.php?source=pwa" class="pwa-nav-item <?= ($currentPage == 'pwa_projets.php') ? 'active' : '' ?>">
            <i class="fas fa-project-diagram"></i>
            <span>Opp</span>
        </a>
        <a href="<?= $urlBase ?>pwa_devis.php?source=pwa" class="pwa-nav-item <?= ($currentPage == 'pwa_devis.php') ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Devis</span>
        </a>
        <a href="<?= $urlBase ?>pwa_geoloc.php?source=pwa" class="pwa-nav-item <?= ($currentPage == 'pwa_geoloc.php') ? 'active' : '' ?>">
            <i class="fas fa-map-marked-alt"></i>
            <span>Carte</span>
        </a>
    </div>

    <!-- Central spacer -->
    <div class="nav-spacer"></div>

    <div class="nav-group nav-right">
        <a href="<?= $urlBase ?>pwa_tickets.php?source=pwa" class="pwa-nav-item <?= ($currentPage == 'pwa_tickets.php') ? 'active' : '' ?>">
            <i class="fas fa-ticket-alt"></i>
            <span>Ticket</span>
        </a>
        <a href="<?= $urlBase ?>pwa_contacts.php?source=pwa" class="pwa-nav-item <?= ($currentPage == 'pwa_contacts.php') ? 'active' : '' ?>">
            <i class="fas fa-address-book"></i>
            <span>Contacts</span>
        </a>
        <a href="<?= $urlBase ?>pwa_tiers.php?source=pwa" class="pwa-nav-item <?= ($currentPage == 'pwa_tiers.php') ? 'active' : '' ?>">
            <i class="fas fa-building"></i>
            <span>Tiers</span>
        </a>
    </div>
</nav>

<style>
/* CSS styles for App Bottom Nav */
	.pwa-bottom-nav {
		position: fixed;
		bottom: 0;
		left: 0;
		width: 100%;
		background: #ffffff;
		display: flex;
		justify-content: space-between;
		align-items: center;
		padding: 6px 15px 12px 15px; /* slight padding offset for safe areas */
		box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
		z-index: 1000;
        box-sizing: border-box;
	}

    .nav-group {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .nav-spacer {
        flex-grow: 1;
    }

	.pwa-nav-item {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		color: #1e293b; /* Deeper color as seen on the screenshot */
		text-decoration: none !important;
		font-size: 10px;
        font-weight: 600;
		transition: all 0.2s ease;
		background: none !important;
		position: relative;
        min-width: 44px;
	}

	.pwa-nav-item i {
		font-size: 18px;
		margin-bottom: 5px;
	}

	.pwa-nav-item:hover, .pwa-nav-item:focus {
		color: #3b82f6; /* Active/Hover color */
	}

	.pwa-nav-item.active {
		color: #ffffff; /* Active text color inside the dark block of the mock-up */
	}

    /* The UI from the screenshot showed a dark blue block around the active tab */
    .pwa-nav-item.active::before {
        content: "";
        position: absolute;
        width: 110%;
        height: 120%;
        background: #1e293b; /* Dark background from the mock-up */
        border-radius: 4px;
        z-index: -1;
        top: -10%;
        left: -5%;
    }

	.pwa-nav-item.active i {
		color: #ffffff; /* White icon for the active state */
	}

    /* Padding to avoid content hiding behind the nav bar */
    body {
        padding-bottom: 74px !important; /* Slightly taller for new padding */
    }

    /* For very small devices adjust sizing */
    @media (max-width: 360px) {
        .nav-group {
            gap: 8px;
        }
        .pwa-nav-item {
            font-size: 9px;
            min-width: 38px;
        }
    }

</style>

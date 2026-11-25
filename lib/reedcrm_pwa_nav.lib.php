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
 * \file    lib/reedcrm_pwa_nav.lib.php
 * \ingroup reedcrm
 * \brief   Library functions for the PWA bottom navigation (items definition + per-user favorites + badges).
 */

// Maximum number of favorite items displayed in the bottom bar
if (!defined('REEDCRM_PWA_NAV_MAX_FAVORITES')) {
    define('REEDCRM_PWA_NAV_MAX_FAVORITES', 5);
}

/**
 * Return the canonical list of PWA bottom nav items.
 *
 * Keys are stable slugs persisted in the user conf (REEDCRM_PWA_NAV_FAVORITES),
 * array order is the display order in both the favorites bar and the burger drawer.
 *
 * @return array<string,array{url:string,page:string,icon:string,label:string}>
 */
function reedcrm_pwa_nav_get_items(): array
{
    $urlBase = dol_buildpath('/reedcrm/view/frontend/', 1);

    return [
        'quickcreation' => ['url' => $urlBase . 'quickcreation.php?source=pwa', 'page' => 'quickcreation.php', 'icon' => 'fa-handshake', 'label' => '+ Opp'],
        'projets'       => ['url' => $urlBase . 'pwa_projets.php?source=pwa', 'page' => 'pwa_projets.php', 'icon' => 'fa-project-diagram', 'label' => 'Opp'],
        'call_list'     => ['url' => $urlBase . 'pwa_call_list.php?source=pwa', 'page' => 'pwa_call_list.php', 'icon' => 'fa-headset', 'label' => 'Appel'],
        'devis'         => ['url' => $urlBase . 'pwa_devis.php?source=pwa', 'page' => 'pwa_devis.php', 'icon' => 'fa-file-invoice-dollar', 'label' => 'Devis'],
        'geoloc'        => ['url' => $urlBase . 'pwa_geoloc.php?source=pwa', 'page' => 'pwa_geoloc.php', 'icon' => 'fa-map-marked-alt', 'label' => 'Carte'],
        'tickets'       => ['url' => $urlBase . 'pwa_tickets.php?source=pwa', 'page' => 'pwa_tickets.php', 'icon' => 'fa-ticket-alt', 'label' => 'Ticket'],
        'contacts'      => ['url' => $urlBase . 'pwa_contacts.php?source=pwa', 'page' => 'pwa_contacts.php', 'icon' => 'fa-address-book', 'label' => 'Contacts'],
        'tiers'         => ['url' => $urlBase . 'pwa_tiers.php?source=pwa', 'page' => 'pwa_tiers.php', 'icon' => 'fa-building', 'label' => 'Tiers'],
    ];
}

/**
 * Return the user's favorite nav slugs, in canonical item order.
 *
 * Stored in the user personal conf (llx_user_param) under key
 * REEDCRM_PWA_NAV_FAVORITES as a CSV of slugs. The literal value 'none'
 * means "no favorite at all": an empty value would make dol_set_user_param
 * delete the param and silently bring the defaults back.
 *
 * @param  User  $user User to read the personal conf from
 * @return string[]    Favorite slugs (possibly empty)
 */
function reedcrm_pwa_nav_get_favorites(User $user): array
{
    $items    = reedcrm_pwa_nav_get_items();
    $defaults = ['quickcreation', 'projets', 'call_list', 'devis'];

    $raw = isset($user->conf->REEDCRM_PWA_NAV_FAVORITES) ? trim((string) $user->conf->REEDCRM_PWA_NAV_FAVORITES) : '';
    if ($raw === '') {
        return $defaults;
    }
    if ($raw === 'none') {
        return [];
    }

    // Keep only known slugs, in canonical order, capped at the bar capacity
    $wanted    = array_map('trim', explode(',', $raw));
    $favorites = array_values(array_intersect(array_keys($items), $wanted));

    return array_slice($favorites, 0, REEDCRM_PWA_NAV_MAX_FAVORITES);
}

/**
 * Return the counters displayed as a red badge on the PWA nav items.
 *
 * Only the call list has one: the lines still to call in the user's default
 * call list, the one the "Appel" item opens. The default list is read from the
 * user personal conf (REEDCRM_DEFAULT_CALL_LIST) and never created here, as the
 * nav is rendered on every PWA page.
 *
 * @param  User              $user User to count for
 * @return array<string,int>       Counter per nav slug, non-zero values only
 */
function reedcrm_pwa_nav_get_badges(User $user): array
{
    global $db;

    $badges = [];

    $canReadCallList = $user->hasRight('reedcrm', 'call_list', 'read') || $user->hasRight('reedcrm', 'call_list', 'read_subordinates') || $user->hasRight('reedcrm', 'call_list', 'read_all');
    $callListId      = isset($user->conf->REEDCRM_DEFAULT_CALL_LIST) ? (int) $user->conf->REEDCRM_DEFAULT_CALL_LIST : 0;
    if ($canReadCallList && $callListId > 0) {
        require_once __DIR__ . '/../class/calllistline.class.php';

        $sql  = 'SELECT COUNT(rowid) as nb FROM ' . $db->prefix() . 'reedcrm_call_list_line';
        $sql .= ' WHERE fk_call_list = ' . $callListId;
        $sql .= ' AND status = ' . CallListLine::STATUS_TO_CALL;

        $resql = $db->query($sql);
        if ($resql && ($obj = $db->fetch_object($resql)) && $obj->nb > 0) {
            $badges['call_list'] = (int) $obj->nb;
        }
    }

    return $badges;
}

/**
 * Return the HTML of a nav item red badge.
 *
 * Mirrored by window.reedcrm.pwaNav.setBadge() for the live updates.
 *
 * @param  int    $count Counter value
 * @return string        Badge HTML, empty when there is nothing to count
 */
function reedcrm_pwa_nav_badge_html(int $count): string
{
    if ($count <= 0) {
        return '';
    }

    return '<span class="pwa-nav-badge">' . ($count > 99 ? '99+' : $count) . '</span>';
}

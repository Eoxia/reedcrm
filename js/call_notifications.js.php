<?php
/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    js/call_notifications.js.php
 * \ingroup reedcrm
 * \brief   JavaScript file for call notifications management
 */

if (file_exists(__DIR__ . '/../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../saturne/saturne.main.inc.php';
} elseif (file_exists(__DIR__ . '/../../saturne/saturne.main.inc.php')) {
    require_once __DIR__ . '/../../saturne/saturne.main.inc.php';
} else {
    die('Include of saturne main fails');
}

header('Content-Type: application/javascript');

?>

/* JavaScript for ReedCRM Call Notifications */

jQuery(document).ready(function() {
    console.log('ReedCRM Call Notifications initialized');

    // Configuration
    var call_check_frequency = <?php echo max(2, getDolGlobalInt('REEDCRM_CALL_CHECK_FREQUENCY', 5)); ?>; // secondes
    var call_check_interval = null;

    // Démarrer la vérification des appels
    if (typeof reedcrmCallsEnabled === 'undefined' || reedcrmCallsEnabled) {
        setTimeout(function() {
            console.log('Starting call events check with frequency: ' + call_check_frequency + 's');
            check_call_events();
            call_check_interval = setInterval(check_call_events, call_check_frequency * 1000);
        }, 2000); // Attendre 2 secondes après le chargement de la page
    }

    /**
     * Vérifier les nouveaux événements d'appel
     */
    function check_call_events() {
        jQuery.ajax({
            url: '<?php echo dol_buildpath('/custom/reedcrm/ajax/check_call_events.php', 1); ?>',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                if (data && data.length > 0) {
                    console.log('Found ' + data.length + ' new call events');

                    jQuery.each(data, function(index, event) {
                        show_call_notification(event);
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('Error checking call events:', error);
            }
        });
    }

    /**
     * Afficher une notification d'appel
     */
    function show_call_notification(event) {
        console.log('Showing call notification for:', event);

        // Créer le contenu de la notification
        var title = '<?php echo dol_escape_js($langs->trans("IncomingCall")); ?>';
        var body = title + ' : ' + event.contact_name;
        if (event.caller) {
            body += '<br><?php echo dol_escape_js($langs->trans("From")); ?>: ' + event.caller;
        }
        if (event.contact_phone) {
            body += '<br><?php echo dol_escape_js($langs->trans("Phone")); ?>: ' + event.contact_phone;
        }
        if (event.contact_email) {
            body += '<br><?php echo dol_escape_js($langs->trans("Email")); ?>: ' + event.contact_email;
        }

        body += '<br><br>';
        body += '<button type="button" class="button" onclick="open_contact_card(' + event.id_contact + ', \'' + event.url + '\')">';
        body += '<?php echo dol_escape_js($langs->trans("ViewContact")); ?>';
        body += '</button>';

        jQuery.jnotify(body, 'success', true, {
            remove: function(){},
            sticky: true,
            timeout: 30000
        });

        // Optionnel: ouvrir automatiquement la fiche (configurable)
        var auto_open = <?php echo getDolGlobalInt('REEDCRM_AUTO_OPEN_CONTACT', 0) ? 'true' : 'false'; ?>;
        if (auto_open) {
            setTimeout(function() {
                open_contact_card(event.id_contact, event.url);
            }, 1000);
        }
    }
});

/**
 * Ouvrir la fiche contact
 */
function open_contact_card(contact_id, url) {
    console.log('Opening contact card for ID:', contact_id);

    var open_in_new_tab = <?php echo getDolGlobalInt('REEDCRM_OPEN_IN_NEW_TAB', 1) ? 'true' : 'false'; ?>;

    if (open_in_new_tab) {
        window.open(url, '_blank');
    } else {
        window.location.href = url;
    }
}

/**
 * Désactiver les notifications d'appel pour cette session
 */
function disable_call_notifications() {
    reedcrmCallsEnabled = false;
    if (call_check_interval) {
        clearInterval(call_check_interval);
    }
    console.log('Call notifications disabled for this session');
}

// Variable globale pour contrôler les notifications
var reedcrmCallsEnabled = true;

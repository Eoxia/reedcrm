window.saturne = window.saturne || {};
window.saturne.call_list = {};

window.saturne.call_list.init = function() {
    window.saturne.call_list.event();
};

window.saturne.call_list.event = function() {
    $(document).on('change', '#call_line_element_type', window.saturne.call_list.onTypeChange);
    $(document).on('change', '#propal_id', window.saturne.call_list.onElementChange);
    $(document).on('change', '#project_id', window.saturne.call_list.onElementChange);
    $(document).on('submit', '#add-call-line-form', window.saturne.call_list.onSubmit);
    // Namespaced: the card loads this file beside reedcrm.min.js, which bundles it, a second binding would move the row twice
    $(document).off('click.callListLineMove').on('click.callListLineMove', '.call-list-line-move', window.saturne.call_list.onMoveClick);
    $(document).off('click.callListLineUnlink').on('click.callListLineUnlink', '.call-list-line-unlink', window.saturne.call_list.onUnlinkClick);
    $(document).off('change.callListPhoneChoice').on('change.callListPhoneChoice', 'input[name="phone_choice"]', window.saturne.call_list.onPhoneChoiceChange);
    $(document).off('focus.callListManualPhone input.callListManualPhone').on('focus.callListManualPhone input.callListManualPhone', '#call_line_manual_phone', window.saturne.call_list.onManualPhoneInput);
};

/**
 * Unlink the line from the call list after confirmation, with the endpoint and the messages of the App:
 * the row leaves the page without reloading it, unless it was the last one, to show the empty list
 */
window.saturne.call_list.onUnlinkClick = function(event) {
    event.preventDefault();

    var $link = $(this);
    var $row  = $link.closest('.call-list-line-row');
    var $data = $('#call-list-data');
    if ($link.hasClass('loading') || !window.confirm($data.attr('data-unlink-confirm'))) {
        return;
    }

    var showError = function(message) {
        if ($.jnotify) {
            $.jnotify(message, 'error', true);
        } else {
            window.alert(message);
        }
    };

    $link.addClass('loading');
    $.ajax({
        url: $data.attr('data-unlink-url'),
        type: 'POST',
        dataType: 'json',
        data: { line_id: $row.data('lineId'), token: $data.attr('data-token') }
    }).done(function(res) {
        if (!res || !res.success) {
            showError(res && res.error ? res.error : $data.attr('data-unlink-error'));
            return;
        }
        $row.fadeOut(200, function() {
            $row.remove();
            if (!$('.call-list-line-row').length) {
                window.location.reload();
                return;
            }
            window.saturne.call_list.refreshMoveArrows();
        });
    }).fail(function() {
        showError($data.attr('data-unlink-error'));
    }).always(function() {
        $link.removeClass('loading');
    });
};

/**
 * Chain of the move requests: each one moves the line from the order the previous one left in base
 */
window.saturne.call_list.moveQueue = $.Deferred().resolve().promise();

window.saturne.call_list.onMoveClick = function(event) {
    event.preventDefault();

    var $row       = $(this).closest('.call-list-line-row');
    var direction  = $(this).data('direction');
    var $neighbour = direction === 'up' ? $row.prev('.call-list-line-row') : $row.next('.call-list-line-row');
    if (!$neighbour.length) {
        return;
    }

    // Moved at once on the page, saved behind
    window.saturne.call_list.moveRow($row, $neighbour, direction);
    window.saturne.call_list.refreshMoveArrows();

    // #call-list-data on the card, the cards container in the App
    var $data = $('[data-move-url]').first();
    var send  = function() {
        return $.ajax({
            url: $data.attr('data-move-url'),
            type: 'POST',
            dataType: 'json',
            data: { line_id: $row.data('lineId'), direction: direction, token: $data.attr('data-token') }
        });
    };

    var request = window.saturne.call_list.moveQueue.then(send, send);
    // Not saved: the page reloads to show the order really in base
    request.done(function(res) {
        if (!res || !res.success) {
            window.location.reload();
        }
    }).fail(function() {
        window.location.reload();
    });
    window.saturne.call_list.moveQueue = request;
};

/**
 * Swap the row with its neighbour so that the user sees it move: both rows slide from their old place to
 * the new one (their offset is applied, then released under a CSS transition), then the moved row flashes
 */
window.saturne.call_list.moveRow = function($row, $neighbour, direction) {
    var $rows  = $row.add($neighbour);
    var before = $rows.map(function() {
        return this.getBoundingClientRect().top;
    }).get();

    if (direction === 'up') {
        $row.insertBefore($neighbour);
    } else {
        $row.insertAfter($neighbour);
    }

    $rows.removeClass('call-list-line-sliding').each(function(index) {
        $(this).css('transform', 'translateY(' + (before[index] - this.getBoundingClientRect().top) + 'px)');
    });
    // Reading the layout makes the browser apply the offsets before the transition starts
    window.saturne.call_list.lastLayout = $row[0].offsetHeight;
    $rows.addClass('call-list-line-sliding').css('transform', '');

    // Removed then added back, so that a row moved twice in a row flashes again
    $row.removeClass('call-list-line-moved');
    window.saturne.call_list.lastLayout = $row[0].offsetHeight;
    $row.addClass('call-list-line-moved');
};

window.saturne.call_list.refreshMoveArrows = function() {
    var $rows = $('.call-list-line-row');
    $rows.find('.call-list-line-move').removeClass('hideobject');
    $rows.first().find('.call-list-line-move[data-direction="up"]').addClass('hideobject');
    $rows.last().find('.call-list-line-move[data-direction="down"]').addClass('hideobject');
};

window.saturne.call_list.onTypeChange = function() {
    var type = $(this).val();
    if (type === 'project') {
        $('#call-line-propal-wrap').addClass('hidden');
        $('#call-line-project-wrap').removeClass('hidden');
        if ($.fn.select2) {
            $('#project_id').select2({ width: '300px' });
        }
    } else {
        $('#call-line-project-wrap').addClass('hidden');
        $('#call-line-propal-wrap').removeClass('hidden');
        if ($.fn.select2) {
            $('#propal_id').select2({ width: '300px' });
        }
    }
    window.saturne.call_list.resetContact();
};

window.saturne.call_list.escapeHtml = function(str) {
    return $('<span>').text(str || '').html();
};

window.saturne.call_list.onPhoneChoiceChange = function() {
    var $checked = $('input[name="phone_choice"]:checked');
    var contactId = $checked.data('contactId') || '';
    $('#fk_contact_hidden').val(contactId ? String(contactId) : '');
    $('.call-list-phone-choice').removeClass('is-selected');
    $checked.closest('.call-list-phone-choice').addClass('is-selected');
    if ($checked.val() !== 'project') {
        $('#call_line_manual_phone').removeClass('is-invalid-phone');
        $('#call_line_manual_phone_error').addClass('hidden').text('');
    }
};

window.saturne.call_list.onElementChange = function() {
    var elementId   = $(this).val();
    var elementType = $('#call_line_element_type').val();
    var $data       = $('#call-list-data');

    if (!elementId) {
        window.saturne.call_list.resetContact();
        return;
    }

    $.ajax({
        url: $data.data('ajaxUrl'),
        dataType: 'json',
        data: { element_type: elementType, element_id: elementId },
        success: function(res) {
            if (!res || !res.success) {
                window.saturne.call_list.resetContact();
                return;
            }

            var esc     = window.saturne.call_list.escapeHtml;
            var choices = Array.isArray(res.phone_choices) ? res.phone_choices : [];
            var showOpp = !!res.can_save_on_project || !res.found;
            var oppChecked = res.source === 'project_phone' || !res.found;

            var oppPlaceholder = res.can_save_on_project
                ? ($data.data('labelOppPhonePlaceholder') || $data.data('labelEnterPhone'))
                : ($data.data('labelEnterPhoneGeneric') || $data.data('labelEnterPhone'));

            var html = '<div class="call-list-phone-choices">';

            if (showOpp) {
                var oppLabel = res.can_save_on_project
                    ? ($data.data('labelTelOpp') || 'Tél. opp.')
                    : ($data.data('labelNoPhoneFound') || 'Téléphone');
                html += '<div class="call-list-phone-choice call-list-phone-choice--opp' + (oppChecked ? ' is-selected' : '') + '">'
                    + '<div class="call-list-phone-choice-row">'
                    + '<label class="call-list-phone-choice-label">'
                    + '<input type="radio" name="phone_choice" value="project" data-contact-id="0"' + (oppChecked ? ' checked' : '') + '>'
                    + '<span class="call-list-phone-choice-title">' + esc(oppLabel) + '</span>'
                    + '</label>'
                    + '<input type="tel" name="manual_phone" id="call_line_manual_phone" class="flat" value="' + esc(res.project_phone || '') + '" placeholder="' + esc(oppPlaceholder) + '" autocomplete="tel">'
                    + '</div>'
                    + '<div class="call-list-phone-error hidden" id="call_line_manual_phone_error"></div>'
                    + '</div>';
            }

            choices.forEach(function(choice) {
                var isSelected = !oppChecked && !!choice.selected;
                var typeTitle  = choice.type === 'thirdparty'
                    ? ($data.data('labelTelTiers') || 'Tél. tiers')
                    : ($data.data('labelTelContact') || 'Tél. contact') + (choice.label ? ' (' + choice.label + ')' : '');

                html += '<label class="call-list-phone-choice' + (isSelected ? ' is-selected' : '') + '" title="' + esc(choice.label || '') + '">'
                    + '<input type="radio" name="phone_choice" value="' + esc(choice.type) + '" data-contact-id="' + esc(String(choice.contact_id || 0)) + '"' + (isSelected ? ' checked' : '') + '>'
                    + '<span class="call-list-phone-choice-title">' + esc(typeTitle) + '</span>'
                    + '<span class="call-list-phone-choice-num">' + esc(choice.phone) + '</span>'
                    + '</label>';
            });

            html += '</div>';

            $('#call_line_contact_info').html(html);
            window.saturne.call_list.onPhoneChoiceChange();
        },
        error: function() {
            window.saturne.call_list.resetContact();
        }
    });
};

window.saturne.call_list.resetContact = function() {
    var $data = $('#call-list-data');
    $('#call_line_contact_info').html('<span class="opacitymedium">' + window.saturne.call_list.escapeHtml($data.data('labelContact')) + ' : —</span>');
    $('#fk_contact_hidden').val('');
};

window.saturne.call_list.validateManualPhone = function(requireNonEmpty) {
    var $manualPhone = $('#call_line_manual_phone');
    var $errorBox    = $('#call_line_manual_phone_error');
    var $data        = $('#call-list-data');
    if (!$manualPhone.length) {
        return true;
    }

    var val = $.trim($manualPhone.val());
    if (val === '') {
        if (requireNonEmpty) {
            $manualPhone.addClass('is-invalid-phone');
            $errorBox.text($data.data('labelPhoneRequired') || '').removeClass('hidden');
            return false;
        }
        $manualPhone.removeClass('is-invalid-phone');
        $errorBox.addClass('hidden').text('');
        return true;
    }

    var isValid = window.reedcrm && typeof window.reedcrm.isValidPhone === 'function'
        ? window.reedcrm.isValidPhone(val)
        : /^[+0-9. ()-]{6,20}$/.test(val);

    if (!isValid) {
        $manualPhone.addClass('is-invalid-phone');
        $errorBox.text($data.data('labelPhoneInvalid') || '').removeClass('hidden');
        return false;
    }

    $manualPhone.removeClass('is-invalid-phone');
    $errorBox.addClass('hidden').text('');
    return true;
};

window.saturne.call_list.onManualPhoneInput = function(event) {
    var $oppRadio = $('input[name="phone_choice"][value="project"]');
    if ($oppRadio.length && !$oppRadio.prop('checked')) {
        $oppRadio.prop('checked', true);
        window.saturne.call_list.onPhoneChoiceChange();
    }

    if (event.type === 'focus') {
        return;
    }

    window.saturne.call_list.validateManualPhone(false);
};

window.saturne.call_list.onSubmit = function(e) {
    var type      = $('#call_line_element_type').val();
    var elementId = type === 'project' ? $('#project_id').val() : $('#propal_id').val();
    if (!elementId) {
        e.preventDefault();
        return false;
    }

    var selectedChoice = $('input[name="phone_choice"]:checked').val() || '';
    if (selectedChoice === 'project' && !window.saturne.call_list.validateManualPhone(true)) {
        e.preventDefault();
        $('#call_line_manual_phone').focus();
        return false;
    }
};

$(document).ready(function() {
    window.saturne.call_list.init();
});

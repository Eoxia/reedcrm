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

    var $data = $('#call-list-data');
    var send  = function() {
        return $.ajax({
            url: $data.data('moveUrl'),
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
            if (res && res.success && res.contact_id) {
                var html = '<b>' + res.lastname + ' ' + res.firstname + '</b>';
                if (res.phone) {
                    html += ' — ' + res.phone;
                } else {
                    html += ' <span class="opacitymedium badge badge-status1">⚠ ' + $data.data('labelNoPhone') + '</span>';
                }
                $('#call_line_contact_info').html(html);
                $('#fk_contact_hidden').val(res.contact_id);
            } else {
                $('#call_line_contact_info').html('<span class="opacitymedium">' + $data.data('labelNoContact') + '</span>');
                $('#fk_contact_hidden').val('');
            }
        },
        error: function() {
            window.saturne.call_list.resetContact();
        }
    });
};

window.saturne.call_list.resetContact = function() {
    var $data = $('#call-list-data');
    $('#call_line_contact_info').html('<span class="opacitymedium">' + $data.data('labelContact') + ' : —</span>');
    $('#fk_contact_hidden').val('');
};

window.saturne.call_list.onSubmit = function(e) {
    var type      = $('#call_line_element_type').val();
    var elementId = type === 'project' ? $('#project_id').val() : $('#propal_id').val();
    if (!elementId) {
        e.preventDefault();
        return false;
    }
};

$(document).ready(function() {
    window.saturne.call_list.init();
});

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
    $(document).on('click', '.reedcrm-default-call-list-star', window.saturne.call_list.onDefaultStarClick);
};

/**
 * Set the current call list as my favorite, without reloading the page.
 * A star already on is inert: a favorite must always be set, the widget of element cards adds into it.
 */
window.saturne.call_list.onDefaultStarClick = function(event) {
    event.preventDefault();

    var $star = $(this);
    if ($star.hasClass('reedcrm-default-call-list-star-on') || $star.prop('disabled')) {
        return;
    }

    $star.prop('disabled', true);

    var formData = new FormData();
    formData.append('call_list_id', $star.data('call-list-id'));
    formData.append('token', $('input[name="token"]').val() || '');

    fetch($star.data('ajax-url'), { method: 'POST', body: formData })
        .then(function(response) { return response.json(); })
        .then(function(result) {
            $star.prop('disabled', false);

            if (!result.success) {
                $.jnotify(result.message, 'error');
                return;
            }

            $star.addClass('reedcrm-default-call-list-star-on');
            $star.find('i').attr('class', 'fas fa-star');
            $star.find('.reedcrm-default-call-list-star-label').text($star.data('label-on'));
            $.jnotify(result.message);
        })
        .catch(function() {
            $star.prop('disabled', false);
            $.jnotify('Erreur réseau', 'error');
        });
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

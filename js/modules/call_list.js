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

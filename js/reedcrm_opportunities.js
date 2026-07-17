document.addEventListener("DOMContentLoaded", function() {
    if (typeof jQuery === 'undefined' || typeof Swal === 'undefined') return;
    
    // Base AJAX URL
    let url = (typeof dolibarr_main_url_root !== 'undefined' && dolibarr_main_url_root) ? dolibarr_main_url_root : '';
    if (!url) {
        if (document.URL.indexOf('/projet/') > 0) url = document.URL.substring(0, document.URL.indexOf('/projet/'));
        else if (document.URL.indexOf('/custom/') > 0) url = document.URL.substring(0, document.URL.indexOf('/custom/'));
    }
    let baseAjaxUrl = url + '/custom/reedcrm/view/frontend/quickcreation.php';

    // Client Selector Logic using SweetAlert2 + AJAX
    $(document).on('click', '.pwa-client-selector', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        let btn = $(this);
        let projectId = btn.data('project-id');
        if (!projectId) return;
        
        Swal.fire({
            title: 'Sélectionner un tiers',
            html: '<div class="swal-select-wrap" style="text-align: left; margin-top: 15px;"><select id="swal-ajax-select-client" style="width: 100%;"></select></div>',
            showCancelButton: true,
            confirmButtonText: 'Enregistrer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#9b59b6',
            didOpen: () => {
                let s2 = Swal.getPopup().querySelector('#swal-ajax-select-client');
                if (s2) {
                    $(s2).select2({ 
                        width: '100%', 
                        dropdownParent: $(Swal.getPopup()),
                        placeholder: 'Tapez 2 lettres min...',
                        minimumInputLength: 2,
                        ajax: {
                            url: baseAjaxUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    action: 'search_tiers_ajax',
                                    q: params.term
                                };
                            },
                            processResults: function (data) {
                                return { results: data.results };
                            }
                        }
                    });
                    $(s2).select2('open');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let s2 = Swal.getPopup().querySelector('#swal-ajax-select-client');
                let newSocid = $(s2).val();
                let token = $('input[name="token"]').val() || '';
                
                if (!newSocid || newSocid == 0) return;
                
                btn.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6;"></i> Enregistrement...');
                
                let ajaxUrl = baseAjaxUrl + '?action=updateoppsocid&token=' + token;
                $.post(ajaxUrl, { projectid: projectId, socid: newSocid }, function(res) {
                    window.location.reload();
                }).fail(function() {
                    window.location.reload();
                });
            }
        });
    });
    
    // Contact Selector Logic using SweetAlert2 + AJAX
    $(document).on('click', '.pwa-contact-selector', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        let btn = $(this);
        let projectId = btn.data('project-id');
        let hiddenWrap = $('#reedcrm-hidden-contact-selector-pwa-' + projectId);
        if (!projectId) return;
        
        let tiersId = hiddenWrap.data('tiers-id') || 0;
        
        // If no client assigned
        if (tiersId <= 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Client manquant',
                html: '<span style="color:#e74c3c;"><i class="fas fa-exclamation-triangle"></i> Veuillez d\'abord associer un client.</span>',
                confirmButtonColor: '#9b59b6',
                confirmButtonText: 'Compris'
            });
            return;
        }
        
        Swal.fire({
            title: 'Sélectionner un contact',
            html: '<div class="swal-select-wrap" style="text-align: left; margin-top: 15px;"><select id="swal-ajax-select-contact" style="width: 100%;"></select></div>',
            showCancelButton: true,
            confirmButtonText: 'Enregistrer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#9b59b6',
            didOpen: () => {
                let s2 = Swal.getPopup().querySelector('#swal-ajax-select-contact');
                if (s2) {
                    $(s2).select2({ 
                        width: '100%', 
                        dropdownParent: $(Swal.getPopup()),
                        placeholder: 'Tapez 2 lettres min...',
                        minimumInputLength: 2,
                        ajax: {
                            url: baseAjaxUrl,
                            dataType: 'json',
                            delay: 250,
                            data: function (params) {
                                return {
                                    action: 'search_contact_ajax',
                                    projectid: projectId,
                                    q: params.term
                                };
                            },
                            processResults: function (data) {
                                return { results: data.results };
                            }
                        }
                    });
                    $(s2).select2('open');
                }
            }
        }).then((result) => {
            if (result.isConfirmed) {
                let s2 = Swal.getPopup().querySelector('#swal-ajax-select-contact');
                let newContactId = $(s2).val();
                let token = $('input[name="token"]').val() || '';
                
                if (!newContactId || newContactId == 0) return;
                
                btn.html('<i class="fas fa-spinner fa-spin" style="color: #9b59b6;"></i> Enregistrement...');
                
                let ajaxUrl = baseAjaxUrl + '?action=updateoppcontactid&token=' + token;
                $.post(ajaxUrl, { projectid: projectId, contactid: newContactId }, function(res) {
                    window.location.reload();
                }).fail(function() {
                    window.location.reload();
                });
            }
        });
    });
});

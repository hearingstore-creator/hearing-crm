// sales.js - Handles all dynamic sales form logic for create/edit pages
// This file expects AJAX URLs to be provided as data attributes on the <form> element.

$(function () {
    // Read AJAX URLs from form data attributes
    var $form = $('form[data-lead-search]');
    var urls = {
        leadSearch: $form.data('lead-search'),
        customerSearch: $form.data('customer-search'),
    };

    // Initialize Select2 for dropdowns
    $('.select').select2({ width: '100%' });

    // Function to initialize Select2 for a newly added row
    function initializeSelect2ForRow($row) {
        $row.find('.select').select2({
            width: '100%'
        });
        
        $row.find(".lead").select2({
            width: '100%',
            ajax: { 
                url: urls.leadSearch,
                type: "GET",
                dataType: 'json',
                delay: 500,
                data: function (params) {
                    return {
                        search: params.term,
                        category_id: $('#category_id').val()
                    };
                },
                processResults: function (response) {
                    return { results: response };
                },
                cache: true
            }
        });
    }

    // Initialize Select2 for lead dropdown with AJAX search
    $(".lead").select2({
        width: '100%',
        ajax: {
            url: urls.leadSearch,
            type: "GET",
            dataType: 'json',
            delay: 500,
            data: function (params) {
                return {
                    search: params.term,
                    category_id: $('#category_id').val()
                };
            },
            processResults: function (response) {
                return { results: response };
            },
            cache: true
        }
    });

    // Initialize Select2 for customer dropdown with AJAX search
    $(".customer").select2({
        width: '100%',
        ajax: {
            url: urls.customerSearch,
            type: "GET",
            dataType: 'json',
            delay: 500,
            data: function (params) {
                return { search: params.term };
            },
            processResults: function (response) {
                return { results: response };
            },
            cache: true
        }
    });
});

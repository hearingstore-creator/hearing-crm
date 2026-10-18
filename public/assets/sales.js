// sales.js - Handles all dynamic sales form logic for create/edit pages
// This file expects AJAX URLs to be provided as data attributes on the <form> element.

$(function () {
    // Read AJAX URLs from form data attributes
    var $form = $('form[data-product-search]');
    var urls = {
        productSearch: $form.data('product-search'),
        customerSearch: $form.data('customer-search'),
        productInfo: $form.data('product-info'),
        addRow: $form.data('sale-add-row'),
        addServiceRow: $form.data('sale-add-service-row'),
        productSerialNumbers: $form.data('product-numbers-numbers'),
        customerRedeemPoints: $form.data('customer-redeem-points')
    };

    // Sale Type Radio Button Handler
    $('input[name="sale_type"]').on('change', function() {
        var saleType = $(this).val();

        if (saleType === 'product') {
            // Show product commission checkbox and add product button
            $('#product_commission_div').show();
            $('#service_commission_div').hide();
            $('#add_product_btn').show();
            $('#add_service_btn').hide();

            // Reset service-related fields
            $('#is_percent_service_commission').prop('checked', false);
            $('#is_percent_product_commission').prop('checked', true);

            // Clear service rows
            $('.service-body').empty();

            // Show product table head, hide service head
            $('.neworderbody').closest('table').find('thead').not('.service-head').show();
            $('.service-head').hide();

            totalCalculation();

        } else if (saleType === 'service') {
            // Show service commission checkbox and add service button
            $('#product_commission_div').hide();
            $('#service_commission_div').show();
            $('#add_product_btn').hide();
            $('#add_service_btn').show();

            // Reset product-related fields
            $('#is_percent_product_commission').prop('checked', false);
            $('#is_percent_service_commission').prop('checked', true);

            // Clear product rows
            $('.neworderbody').empty();

            // Show service head, hide product table head
            $('.service-head').show();
            $('.neworderbody').closest('table').find('thead').not('.service-head').hide();

            totalCalculation();
        }
    });


    // Initialize Select2 for dropdowns
    $('.select').select2({ width: '100%' });
    $('.batch').select2({ width: '100%' });
    $('.serial-no').select2({ width: '100%' });
    $('.doctor-select').select2({
        width: '100%',
        placeholder: "Select Doctor (Optional)",
        allowClear: true
    });
    $('.service-type').select2({
        width: '100%',
        placeholder: "Select Service Type",
        allowClear: true
    });

    // Function to initialize Select2 for a newly added row
    function initializeSelect2ForRow($row) {
        $row.find('.select').select2({
            width: '100%'
        });

        $row.find('.batch').select2({
            width: '100%'
        });

        $row.find('.serial-no').select2({
            width: '100%'
        });

        $row.find(".product").select2({
            width: '100%',
            ajax: {
                url: urls.productSearch,
                type: "GET",
                dataType: 'json',
                delay: 500,
                data: function (params) {
                    var currentTr = $(this).closest('tr');
                    var excludedIds = getSelectedProductIds(currentTr);
                    return {
                        search: params.term,
                        type: 'sale',
                        exclude_ids: excludedIds
                    };
                },
                processResults: function (response) {
                    return { results: response };
                },
                cache: true
            }
        });
    }

    // Initialize Select2 for product dropdown with AJAX search
    $(".product").select2({
        width: '100%',
        ajax: {
            url: urls.productSearch,
            type: "GET",
            dataType: 'json',
            delay: 500,
            data: function (params) {
                var currentTr = $(this).closest('tr');
                var excludedIds = getSelectedProductIds(currentTr);
                return {
                    search: params.term,
                    type: 'sale',
                    exclude_ids: excludedIds
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

    // Fetch customer redeem points when customer is selected
    $('.customer').on('select2:select', function (e) {
        var customerId = e.params.data.id;

        if (customerId && urls.customerRedeemPoints) {
            $.ajax({
                url: urls.customerRedeemPoints + '/' + customerId,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        $('#available-redeem-points').val(response.available_points.toFixed(2));

                        // Reset redeem points input
                        $('#redeem-points-input').val(0);
                        $('#redeem-points-input').attr('max', response.available_points);

                        // Trigger recalculation
                        totalCalculation();
                    }
                },
                error: function() {
                    $('#available-redeem-points').val('0.00');
                    $('#redeem-points-input').val(0);
                }
            });
        } else {
            $('#available-redeem-points').val('0.00');
            $('#redeem-points-input').val(0);
        }
    });

    // Handle redeem points input change
    $('#redeem-points-input').on('input', function() {
        var availablePoints = parseFloat($('#available-redeem-points').val()) || 0;
        var currentInputValue = $(this).val(); // Get current input value
        var existingRedeemedPoints = parseFloat($(this).attr('data-existing-redeemed')) || 0;

        // Calculate max allowed: available points + existing redeemed points
        // This allows reducing the redeemed amount
        var maxValue = availablePoints + existingRedeemedPoints;

        var inputValue = parseFloat(currentInputValue) || 0;

        // Ensure redeem points don't exceed max allowed
        if (inputValue > maxValue) {
            $(this).val(maxValue);
        }

        // Trigger recalculation
        totalCalculation();
    });

    // Calculate all totals for the sales form
    function totalCalculation() {
        var totalPrice = 0;
        $('.total-price').each(function () {
            var amt = parseFloat($(this).val()) || 0;
            if (!isNaN(amt)) {
                totalPrice += amt;
            }
        });

        // Add service totals
        $('.service-total-price').each(function () {
            var amt = parseFloat($(this).val()) || 0;
            if (!isNaN(amt)) {
                totalPrice += amt;
            }
        });

        $('.sub-total').val(totalPrice.toFixed(2));

        var totalDiscount = 0;
        $('.discount-taka').each(function () {
            var total = parseFloat($(this).val()) || 0;
            if (!isNaN(total)) {
                totalDiscount += total;
            }
        });

        // Add service discount totals
        $('.service-discount-taka').each(function () {
            var total = parseFloat($(this).val()) || 0;
            if (!isNaN(total)) {
                totalDiscount += total;
            }
        });

        var vat = parseFloat($('.vat').val());
        var invDiscount = parseFloat($('.invoice-discount').val());
        var redeemPoints = parseFloat($('#redeem-points-input').val()) || 0;

        if (isNaN(invDiscount)) invDiscount = 0;
        if (isNaN(vat)) vat = 0;
        if (isNaN(redeemPoints)) redeemPoints = 0;

        totalDiscount = totalDiscount + invDiscount;
        $('.total-discount').val(totalDiscount.toFixed(2));

        // Calculate grand total: subtotal + vat - invoice discount - redeem points
        var grandTotal = totalPrice + vat - invDiscount - redeemPoints;
        $('.grand-total').val(grandTotal.toFixed(2));
        var paid = parseFloat($('.paid-amount').val());
        if (isNaN(paid)) paid = 0;
        var due = grandTotal - paid;
        $('.due-amount').val(due.toFixed(2));

        // Calculate doctor commission
        calculateDoctorCommission(grandTotal);
    }

    // Calculate doctor commission based on category-wise overrides & service overrides
    function calculateDoctorCommission(grandTotal) {
        var doctorId = $('#reference_by').val();
        if (!doctorId) {
            $('.reference-amount').val('0.00');
            $('#doc-comm-breakdown').hide();
            return;
        }

        var rules = (window.crmDoctorRules && window.crmDoctorRules[doctorId]) ? window.crmDoctorRules[doctorId] : null;
        var selectedDoctor = $('#reference_by option:selected');

        var defaultProdPercent = rules ? rules.product_default.percent : (parseFloat(selectedDoctor.data('product-commission-percent')) || 0);
        var defaultProdFixed   = rules ? rules.product_default.amount  : (parseFloat(selectedDoctor.data('product-commission-amount')) || 0);
        var defaultSrvPercent  = rules ? rules.service_default.percent : (parseFloat(selectedDoctor.data('service-commission-percent')) || 0);
        var defaultSrvFixed    = rules ? rules.service_default.amount  : (parseFloat(selectedDoctor.data('service-commission-amount')) || 0);

        var categoryOverrides = (rules && rules.category_overrides) ? rules.category_overrides : {};
        var serviceOverrides  = (rules && rules.service_overrides)  ? rules.service_overrides  : {};

        var isProductPercent = $('#is_percent_product_commission').is(':checked');
        var isServicePercent = $('#is_percent_service_commission').is(':checked');

        var totalProductCommission = 0;
        var totalServiceCommission = 0;
        var catOverrideCount = 0;
        var srvOverrideCount = 0;

        // 1. Calculate Product Commissions
        $('.neworderbody tr').each(function () {
            var tr = $(this);
            var catId = tr.attr('data-category-id') || tr.data('category-id');
            var qty = parseFloat(tr.find('.qty').val()) || 0;
            var lineTotal = parseFloat(tr.find('.total-price').val()) || 0;

            if (qty <= 0 && lineTotal <= 0) return;

            if (catId && categoryOverrides[catId]) {
                var catRule = categoryOverrides[catId];
                catOverrideCount++;
                if (catRule.type === 'percent') {
                    totalProductCommission += (lineTotal * catRule.value) / 100;
                } else {
                    totalProductCommission += (qty * catRule.value);
                }
            } else {
                if (isProductPercent && defaultProdPercent > 0) {
                    totalProductCommission += (lineTotal * defaultProdPercent) / 100;
                } else if (defaultProdFixed > 0) {
                    totalProductCommission += (qty * defaultProdFixed);
                } else if (defaultProdPercent > 0) {
                    totalProductCommission += (lineTotal * defaultProdPercent) / 100;
                }
            }
        });

        // 2. Calculate Service Commissions
        $('.service-body tr').each(function () {
            var tr = $(this);
            var srvId = tr.find('.service-type').val();
            var qty = parseFloat(tr.find('.service-qty').val()) || 1;
            var unitPrice = parseFloat(tr.find('.service-price').val()) || 0;
            var discount = parseFloat(tr.find('.service-discount-taka').val()) || 0;
            var lineTotal = parseFloat(tr.find('.service-total-price').val());
            if (isNaN(lineTotal) || lineTotal <= 0) {
                lineTotal = (qty * unitPrice) - discount;
            }

            if (qty <= 0 && lineTotal <= 0) return;

            if (srvId && serviceOverrides[srvId]) {
                var srvRule = serviceOverrides[srvId];
                srvOverrideCount++;
                if (srvRule.type === 'percent') {
                    totalServiceCommission += (lineTotal * srvRule.value) / 100;
                } else {
                    totalServiceCommission += (qty * srvRule.value);
                }
            } else {
                if (isServicePercent && defaultSrvPercent > 0) {
                    totalServiceCommission += (lineTotal * defaultSrvPercent) / 100;
                } else if (defaultSrvFixed > 0) {
                    totalServiceCommission += (qty * defaultSrvFixed);
                } else if (defaultSrvPercent > 0) {
                    totalServiceCommission += (lineTotal * defaultSrvPercent) / 100;
                }
            }
        });

        var totalCommission = totalProductCommission + totalServiceCommission;
        $('.reference-amount').val(totalCommission.toFixed(2));

        var $breakdown = $('#doc-comm-breakdown');
        if ($breakdown.length) {
            var parts = [];
            if (totalProductCommission > 0) {
                parts.push('Products: ৳' + totalProductCommission.toFixed(2) + (catOverrideCount > 0 ? ' (' + catOverrideCount + ' cat overrides)' : ''));
            }
            if (totalServiceCommission > 0) {
                parts.push('Services: ৳' + totalServiceCommission.toFixed(2) + (srvOverrideCount > 0 ? ' (' + srvOverrideCount + ' srv overrides)' : ''));
            }
            if (parts.length > 0) {
                $breakdown.html('<i class="fas fa-calculator mr-1"></i>' + parts.join(' &nbsp;|&nbsp; ')).slideDown(100);
            } else {
                $breakdown.slideUp(100);
            }
        }
    }

    // Populate and sync chamber dropdown based on selected doctor
    function syncDoctorChambers(doctorId, preselectedChamberId) {
        var $chamberWrap = $('#doctor-chamber-wrap');
        var $chamberSelect = $('#doctor_chamber_id');

        if (!doctorId) {
            $chamberWrap.slideUp(150);
            $chamberSelect.empty().append('<option value="">Select Chamber (Optional)</option>');
            return;
        }

        function populateChambers(rules) {
            $chamberSelect.empty().append('<option value="">Select Chamber (Optional)</option>');
            var chambers = (rules && rules.chambers) ? rules.chambers : [];

            if (chambers.length > 0) {
                chambers.forEach(function (c) {
                    var isSelected = preselectedChamberId ? (c.id == preselectedChamberId) : c.is_primary;
                    var label = c.chamber_name + (c.address ? ' — ' + c.address : '');
                    var $opt = $('<option></option>').val(c.id).text(label);
                    if (isSelected) {
                        $opt.prop('selected', true);
                    }
                    $chamberSelect.append($opt);
                });
                $chamberWrap.slideDown(150);
            } else {
                $chamberWrap.slideUp(150);
            }
        }

        if (window.crmDoctorRules && window.crmDoctorRules[doctorId]) {
            populateChambers(window.crmDoctorRules[doctorId]);
        } else {
            $.ajax({
                url: '/admin/doctor/' + doctorId + '/commission-rules',
                type: 'GET',
                dataType: 'json',
                success: function (data) {
                    if (!window.crmDoctorRules) window.crmDoctorRules = {};
                    window.crmDoctorRules[doctorId] = data;
                    populateChambers(data);
                    calculateDoctorCommission();
                },
                error: function () {
                    $chamberWrap.slideUp(150);
                }
            });
        }
    }

    // Calculate totals for a single row (medicine line)
    function total(tr) {
        var percentage = tr.find('.discount').val();
        var discountTaka = tr.find('.discount-taka').val();
        var qty = tr.find('.qty').val();
        var unitPrice = tr.find('.unit-price').val();

        if (isNaN(percentage)) percentage = 0;
        if (isNaN(discountTaka)) discountTaka = 0;
        if (isNaN(unitPrice)) unitPrice = 0;
        if (isNaN(qty)) qty = 0;

        var totalPrice = qty * unitPrice;
        var discount;
        var calcPrice;

        // Determine if user is typing in percentage or taka field
        var currentElement = $(document.activeElement);

        if (currentElement.hasClass('discount-taka') && discountTaka > 0) {
            // User entered discount taka - calculate percentage
            discount = parseFloat(discountTaka);
            var calculatedPercentage = totalPrice > 0 ? (discount / totalPrice) * 100 : 0;
            tr.find('.discount').val(calculatedPercentage.toFixed(2));
        } else {
            // User entered percentage - calculate discount taka
            discount = (totalPrice / 100) * percentage;
            tr.find('.discount-taka').val(discount.toFixed(2));
        }

        calcPrice = totalPrice - discount;

        // Ensure discount doesn't exceed total price
        if (discount > totalPrice) {
            alert('Discount cannot be greater than total price');
            tr.find('.discount').val(0);
            tr.find('.discount-taka').val(0);
            calcPrice = totalPrice;
        }

        tr.find('.total-price').val(calcPrice.toFixed(2));
    }

    // Calculate totals for a service row
    function serviceTotal(tr) {
        var percentage = tr.find('.service-discount').val();
        var discountTaka = tr.find('.service-discount-taka').val();
        var qty = tr.find('.service-qty').val();
        var unitPrice = tr.find('.service-price').val();

        if (isNaN(percentage)) percentage = 0;
        if (isNaN(discountTaka)) discountTaka = 0;
        if (isNaN(unitPrice)) unitPrice = 0;
        if (isNaN(qty)) qty = 0;

        var totalPrice = qty * unitPrice;
        var discount;
        var calcPrice;

        // Determine if user is typing in percentage or taka field
        var currentElement = $(document.activeElement);

        if (currentElement.hasClass('service-discount-taka') && discountTaka > 0) {
            // User entered discount taka - calculate percentage
            discount = parseFloat(discountTaka);
            var calculatedPercentage = totalPrice > 0 ? (discount / totalPrice) * 100 : 0;
            tr.find('.service-discount').val(calculatedPercentage.toFixed(2));
        } else {
            // User entered percentage - calculate discount taka
            discount = (totalPrice / 100) * percentage;
            tr.find('.service-discount-taka').val(discount.toFixed(2));
        }

        calcPrice = totalPrice - discount;

        // Ensure discount doesn't exceed total price
        if (discount > totalPrice) {
            alert('Discount cannot be greater than total price');
            tr.find('.service-discount').val(0);
            tr.find('.service-discount-taka').val(0);
            calcPrice = totalPrice;
        }

        tr.find('.service-total-price').val(calcPrice.toFixed(2));
    }

    // VAT input handler
    $('.vat').keyup(function () {
        var subTotal = parseFloat($('.sub-total').val());
        var vat = $(this).val();
        if (isNaN(vat)) vat = 0;
        if (subTotal < vat) {
            alert('Vat cannot greater than the total amount');
            $('.vat').val(0.00);
        }
        totalCalculation();
    });

    // Invoice discount input handler
    $('.invoice-discount').keyup(function () {
        var subTotal = parseFloat($('.sub-total').val());
        var invDiscount = $(this).val();
        if (isNaN(invDiscount)) invDiscount = 0;
        if (subTotal < invDiscount) {
            alert('Discount cannot greater than the total amount');
            $('.invoice-discount').val(0.00);
        }
        totalCalculation();
    });

    // Paid amount input handler
    $('.paid-amount').keyup(function () {
        var paid = $(this).val();
        var grandTotal = parseFloat($('.grand-total').val());
        if (paid > grandTotal) {
            alert('Paid cannot be greater than grand total');
            $('.paid-amount').val(0.00);
            totalCalculation();
            return false;
        }
        totalCalculation();
    });

    // Function to get currently selected product IDs (excluding current row)
    function getSelectedProductIds(excludeTr) {
        var selectedIds = [];
        $('.neworderbody tr').each(function() {
            var tr = $(this);
            if (excludeTr && tr.is(excludeTr)) return; // Skip current row if specified

            var productId = tr.find('.product').val();
            if (productId) {
                selectedIds.push(productId);
            }
        });
        return selectedIds;
    }

    // Function to refresh product dropdowns in other rows
    function refreshProductDropdowns(excludeTr) {
        $('.neworderbody tr').each(function() {
            var tr = $(this);
            if (excludeTr && tr.is(excludeTr)) return; // Skip current row

            var productSelect = tr.find('.product');
            productSelect.trigger('change.select2'); // This will trigger the AJAX to reload with updated exclusions
        });
    }


    // Function to load serial numbers for a product
    function loadSerialNumbersForProduct(tr, productId) {
        if (!productId) {
            tr.find('.serial-no').empty().append('<option value="">Select Product First</option>');
            tr.find('.batch').prop('disabled', false).trigger('change');
            return;
        }

        $.ajax({
            type: 'GET',
            url: urls.productSerialNumbers + '/' + productId,
            dataType: 'json',
            success: function (response) {
                tr.find('.serial-no').empty().append('<option value="">Select Serial</option>');

                if (response.length > 0 && response[0].serial_number) {                    
                    // Serial numbers available - populate dropdown and disable batch
                    $.each(response, function (index, serial) {
                        tr.find('.serial-no').append($('<option>', {
                            value: serial.serial_number,
                            text: serial.serial_number,
                            'data-mrp-price': serial.mrp_price
                        }));
                    });
                    // Disable batch dropdown since we have serial numbers
                    tr.find('.batch').prop('disabled', true).val('').trigger('change');
                    tr.find('.serial-no').prop('disabled', false);
                } else {
                    // No serial numbers - enable batch dropdown
                    tr.find('.serial-no').prop('disabled', true);
                    tr.find('.batch').prop('disabled', false);
                }
            },
            error: function () {
                tr.find('.serial-no').empty().append('<option value="">No serial numbers available</option>');
                // Enable batch dropdown on error as well
                tr.find('.batch').prop('disabled', false);
            }
        });
    }

    // Add new product row handler
    let rowIndex = $('.neworderbody tr').length;
    let serviceIndex = 0;

    $('.add').click(function () {
        if ($('.product').val() == '') {
            alert('Select an item first');
        } else {
            $.ajax({
                type: 'GET',
                url: urls.addRow,
                datatype: 'json',
                 data: {
                    index: rowIndex
                },
                success: function (data) {
                    var $newRow = $(data);
                    $('.neworderbody').append($newRow);
                    initializeSelect2ForRow($newRow);
                    rowIndex++;
                },
                error: function () { }
            });
        }
    });

    // Add service row handler
    $('.add-service').click(function () {
        // Show service header if it's the first service row
        if ($('.service-body tr').length === 0) {
            $('.service-head').show();
        }

        $.ajax({
            type: 'GET',
            url: urls.addServiceRow,
            datatype: 'json',
            data: {
                index: serviceIndex
            },
            success: function (data) {
                var $newRow = $(data);
                $('.service-body').append($newRow);

                // Initialize service type dropdown with search
                $newRow.find('.service-type').select2({
                    width: '100%',
                    placeholder: "Select Service Type",
                    allowClear: true
                });

                serviceIndex++;
            },
            error: function () { }
        });
    });

    // Delete medicine row handler
    $('.neworderbody').delegate('.delete', 'click', function () {
        var tr = $(this).parent().parent();
        tr.remove();
        totalCalculation();
    });

    // Delete service row handler
    $(document).on('click', '.service-body .delete-service, .service-body .delete', function () {
        var tr = $(this).closest('tr');
        tr.remove();

        // Hide service header if no service rows left
        if ($('.service-body tr').length === 0) {
            $('.service-head').hide();
        }

        totalCalculation();
    });

    // Service type dropdown change handler
    $(document).on('change', '.service-body .service-type', function () {
        var tr = $(this).closest('tr');
        var selectedOption = $(this).find('option:selected');
        var price = selectedOption.data('price') !== undefined ? selectedOption.data('price') : (selectedOption.attr('data-price') || 0);

        tr.find('.service-price').val(price);
        if (!tr.find('.service-qty').val() || tr.find('.service-qty').val() == 0) {
            tr.find('.service-qty').val(1);
        }
        serviceTotal(tr);
        totalCalculation();
    });

    // Service input change handlers (support keyup, input, and change for real-time recalculation)
    $(document).on('keyup input change', '.service-body .service-qty, .service-body .service-price, .service-body .service-discount, .service-body .service-discount-taka', function () {
        var tr = $(this).closest('tr');
        serviceTotal(tr);
        totalCalculation();
    });

    // Product dropdown change handler (fetch price, stock, etc.)
    $('.neworderbody').delegate('.product', 'change', function () {
        var tr = $(this).parent().parent();
        var productId = $(this).val();

        // Reset dropdowns before loading new data
        tr.find('.batch').prop('disabled', false).val('').trigger('change');
        tr.find('.serial-no').empty().append('<option value="">Loading...</option>');

        if (!productId) {
            tr.find('.stock-qty').val('');
            tr.find('.purchase-price').val('');
            tr.find('.unit-price').val('');
            tr.find('.serial-no').empty().append('<option value="">Select Product First</option>');

            // Refresh other rows' product dropdowns when product is cleared
            refreshProductDropdowns(tr);

            total(tr);
            totalCalculation();
            return;
        }

        $.ajax({
            type: 'GET',
            url: urls.productInfo + '/' + productId,
            datatype: 'json',
            success: function (data) {
                if (data?.product?.category_id) {
                    tr.attr('data-category-id', data.product.category_id);
                }
                let batchSelect = tr.find('.batch');

                batchSelect.empty();
                batchSelect.append(
                    $('<option>', { value: '', text: 'Select Batch' })
                );

                if (data?.batch) {
                    batchSelect.prop('disabled', false);

                    let option = $('<option>', {
                        value: data.batch.id,
                        text: data.batch.batch_no
                    });

                    batchSelect.append(option);
                    batchSelect.val(String(data.batch.id)).trigger('change');

                    var purchasePrice = data.batch.purchase_price || 0;
                    var mrpPrice = data.batch.mrp_price || 0;
                    tr.find('.purchase-price').val(purchasePrice);
                    tr.find('.unit-price').val(mrpPrice);
                }
                
                // Load serial numbers for this product
                loadSerialNumbersForProduct(tr, productId);
                var stock = data.stock || 0;

                tr.find('.stock-qty').val(stock);

                var qty = tr.find('.qty').val();
                var unitPrice = tr.find('.unit-price').val();
                tr.find('.total-price').val(qty * unitPrice);

                total(tr);
                totalCalculation();

                // Refresh other rows' product dropdowns to exclude the newly selected product
                refreshProductDropdowns(tr);
            },
            error: function () {
                tr.find('.serial-no').empty().append('<option value="">Error loading product info</option>');
                tr.find('.batch').prop('disabled', false);
            }
        });
    });

    // Batch dropdown change handler (clear serial number selection)
    $('.neworderbody').delegate('.batch', 'change', function () {
        var tr = $(this).parent().parent();
        var batchId = $(this).val();

        // If batch is selected, clear serial number selection
        if (batchId) {
            tr.find('.serial-no').val([]).trigger('change');
        }

        // Recalculate totals
        total(tr);
        totalCalculation();
    });

    // Serial number dropdown change handler (set unit price based on serial's MRP price and auto-calc quantity)
    $('.neworderbody').delegate('.serial-no', 'change', function () {
        var tr = $(this).parent().parent();
        var selectedOptions = $(this).find('option:selected');
        var selectedCount = selectedOptions.length;

        // If serial numbers are selected, clear batch selection
        if (selectedCount > 0) {
            tr.find('.batch').val('').trigger('change');
        }

        // Auto-set quantity based on number of selected serial numbers
        if (selectedCount > 0) {
            tr.find('.qty').val(selectedCount);
        } else {
            tr.find('.qty').val(1);
        }

        // Set unit price based on first selected serial's MRP price (for consistency)
        var firstSelectedOption = selectedOptions.first();
        var mrpPrice = firstSelectedOption.data('mrp-price');

        if (mrpPrice) {
            tr.find('.unit-price').val(mrpPrice);
        }

        var qty = tr.find('.qty').val();
        var unitPrice = tr.find('.unit-price').val();
        tr.find('.total-price').val(qty * unitPrice);

        total(tr);
        totalCalculation();
    });

    // Quantity input handler
    $('.neworderbody').delegate('.qty', 'keyup', function () {
        var tr = $(this).parent().parent();
        var qty = $(this).val();
        var stockQty = tr.find('.stock-qty').val();
        if (parseInt(qty) > parseInt(stockQty)) {
            alert('Qty cannot be greater than stock qty');
            tr.find('.qty').val(1);
        }
        total(tr);
        totalCalculation();
    });

    // Unit price input handler
    $('.neworderbody').delegate('.unit-price', 'keyup', function () {
        var tr = $(this).parent().parent();
        total(tr);
        totalCalculation();
    });

    // Discount input handlers
    $('.neworderbody').delegate('.discount, .discount-taka', 'keyup', function () {
        var tr = $(this).parent().parent();
        total(tr);
        totalCalculation();
    });

    // Form submit handler (validate paid amount and format batch/serial data)
    $("form").on('submit', function (e) {
        e.preventDefault(); // Prevent default submission

        var $form = $(this);
        var grandTotal = parseFloat($('.grand-total').val());
        var paid = parseFloat($('.paid-amount').val());

        if (paid > grandTotal) {
            alert('Paid cannot be greater than grand total');
            $('.paid-amount').val(0.00);
            totalCalculation();
            return false;
        }

        // Remove any existing formatted inputs
        $form.find('input[name="batch_id_formatted"], input[name="serial_no_formatted"]').remove();

        // Format batch_id and serial_no arrays to ensure exclusivity
        var batchIds = [];
        var serialNos = [];
        var oldBatches = {};
        var oldSerials = {};

        // Handle both existing items (old) and new items
        $('.neworderbody tr').each(function(index) {
            var $tr = $(this);
            var batchId = $tr.find('.batch').val();
            var $serialSelect = $tr.find('.serial-no');
            var selectedSerials = $serialSelect.val() || [];

            // Check if this is an existing item (has old[...][id] input)
            var $hiddenId = $tr.find('input[name*="[id]"]');
            var isOldItem = $hiddenId.length > 0;

            // Ensure we're getting the right values
            if (batchId === null || batchId === undefined || batchId === '') {
                batchId = null;
            }

            // If batch is selected, set serial to null
            if (batchId !== null && batchId !== '') {
                if (isOldItem) {
                    // For existing items, store in old format
                    var itemKey = $hiddenId.attr('name').match(/old\[(\d+)\]/)[1];
                    oldBatches[itemKey] = batchId;
                    oldSerials[itemKey] = null;
                } else {
                    // For new items, use regular arrays
                    batchIds.push(batchId);
                    serialNos.push(null);
                }
            }
            // If serials are selected, set batch to null
            else if (selectedSerials && Array.isArray(selectedSerials) && selectedSerials.length > 0) {
                if (isOldItem) {
                    // For existing items, store in old format
                    var itemKey = $hiddenId.attr('name').match(/old\[(\d+)\]/)[1];
                    oldBatches[itemKey] = null;
                    oldSerials[itemKey] = selectedSerials;
                } else {
                    // For new items, use regular arrays
                    batchIds.push(null);
                    serialNos.push(selectedSerials);
                }
            }
            // Neither selected - this might be an error case
            else {
                if (isOldItem) {
                    var itemKey = $hiddenId.attr('name').match(/old\[(\d+)\]/)[1];
                    oldBatches[itemKey] = null;
                    oldSerials[itemKey] = null;
                } else {
                    batchIds.push(null);
                    serialNos.push(null);
                }
            }
        });

        // Create hidden inputs for the formatted arrays (new items)
        var batchInput = $('<input>').attr({
            type: 'hidden',
            name: 'batch_id_formatted',
            value: JSON.stringify(batchIds)
        });

        var serialInput = $('<input>').attr({
            type: 'hidden',
            name: 'serial_no_formatted',
            value: JSON.stringify(serialNos)
        });

        // Create hidden inputs for old items (existing sale details)
        var oldBatchInput = $('<input>').attr({
            type: 'hidden',
            name: 'old_batch_formatted',
            value: JSON.stringify(oldBatches)
        });

        var oldSerialInput = $('<input>').attr({
            type: 'hidden',
            name: 'old_serial_formatted',
            value: JSON.stringify(oldSerials)
        });

        $form.append(batchInput);
        $form.append(serialInput);
        $form.append(oldBatchInput);
        $form.append(oldSerialInput);

        // Submit the form programmatically
        this.submit();
    });

    // Recalculate doctor commission and sync chambers when doctor selection changes
    $('#reference_by').on('change', function() {
        var docId = $(this).val();
        syncDoctorChambers(docId);
        calculateDoctorCommission();
    });

    // Recalculate doctor commission when commission checkboxes change
    $('#is_percent_product_commission, #is_percent_service_commission').on('change', function() {
        calculateDoctorCommission();
    });

    // Initial check on page load for chambers and commission
    var initialDocId = $('#reference_by').val();
    if (initialDocId) {
        var preselectedChamberId = $('#doctor_chamber_id').data('initial-chamber') || $('#doctor_chamber_id').val();
        syncDoctorChambers(initialDocId, preselectedChamberId);
        calculateDoctorCommission();
    }

    // Initialize calculations for the first row and count pre-selected serial numbers
    $('.neworderbody tr').each(function() {
        var tr = $(this);
        if (tr.find('.product').val()) {
            total(tr);

            // Count pre-selected serial numbers and set quantity
            var selectedSerials = tr.find('.serial-no option:selected');
            var selectedCount = selectedSerials.length;
            if (selectedCount > 0) {
                tr.find('.qty').val(selectedCount);
            }
        }
    });

    // Initialize calculations for existing service rows
    $('.service-body tr').each(function() {
        var tr = $(this);
        serviceTotal(tr);
    });

    // Show service header if there are existing service rows
    if ($('.service-body tr').length > 0) {
        $('.service-head').show();
    }

    // Initialize discount calculations for edit page
    var subTotal = parseFloat($('.sub-total').val()) || 0;
    var invoiceDiscount = parseFloat($('.invoice-discount').val()) || 0;
    var totalDiscount = parseFloat($('.total-discount').val()) || 0;

    // Calculate and set vat if it exists
    var vat = parseFloat($('.vat').val()) || 0;
    var grandTotal = subTotal + vat - invoiceDiscount;

    // Update fields if they don't match expected values
    $('.grand-total').val(grandTotal.toFixed(2));

    // Calculate due amount
    var paid = parseFloat($('.paid-amount').val()) || 0;
    var due = grandTotal - paid;
    $('.due-amount').val(due.toFixed(2));

    // Initialize doctor commission calculation on page load
    var initialGrandTotal = parseFloat($('.grand-total').val()) || 0;
    calculateDoctorCommission(initialGrandTotal);

    // Run total calculation to ensure everything is synchronized
    totalCalculation();

    // =============================================
    // Customer Creation Modal AJAX
    // =============================================

    // Initialize Select2 for modal dropdowns
    $('#customerModal').on('shown.bs.modal', function () {
        $('#district_id', '#customerForm').select2({
            width: '100%',
            dropdownParent: $('#customerModal')
        });
        $('#gender', '#customerForm').select2({
            width: '100%',
            dropdownParent: $('#customerModal')
        });
    });

    // Handle customer form submission via AJAX
    $('#saveCustomerBtn').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $form = $('#customerForm');
        var $btn = $(this);
        var $spinner = $btn.find('.fa-spinner');
        var customerSearchUrl = urls.customerSearch;

        // Validate required fields
        var fullName = $form.find('[name="full_name"]').val();
        var phoneNumber = $form.find('[name="phone_number"]').val();
        var age = $form.find('[name="age"]').val();

        if (!fullName || !phoneNumber || !age) {
            alert('Please fill in all required fields (Full Name, Phone Number, Age)');
            return false;
        }

        // Prevent double submission
        if ($btn.data('submitting')) {
            return false;
        }

        // Show loading state
        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $btn.data('submitting', true);

        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            success: function (response) {
                if (response.success && response.customer_id) {
                    var $customerSelect = $('.customer');
                    var custId = response.customer_id;
                    var custPhone = response.phone || response.phone_number || phoneNumber;
                    var custText = (response.customer_name || fullName) +
                        (custPhone ? ' - ' + custPhone : '') +
                        (response.customer_uid ? ' (' + response.customer_uid + ')' : '');

                    // Create Select2 option if it doesn't already exist, and select it
                    if ($customerSelect.find("option[value='" + custId + "']").length) {
                        $customerSelect.val(custId).trigger('change');
                    } else {
                        var newOption = new Option(custText, custId, true, true);
                        $customerSelect.append(newOption).trigger('change');
                    }

                    // Trigger select2:select so redeem points and any linked listeners update
                    $customerSelect.trigger({
                        type: 'select2:select',
                        params: {
                            data: { id: custId, text: custText }
                        }
                    });

                    // Close the modal and remove focus
                    $('#customerModal').modal('hide');
                    $('#customerModal').find(':focus').blur();

                    // Alert or toast message
                    var message = response.message || (response.already_exists ? 'Existing customer selected.' : 'Customer created successfully!');
                    if (typeof toastr !== 'undefined') {
                        toastr.success(message);
                    } else {
                        alert(message);
                    }
                } else {
                    alert(response.message || 'Customer saved, but could not be selected automatically.');
                    $('#customerModal').modal('hide');
                }
            },
            error: function (xhr) {
                // Handle validation errors
                if (xhr.status === 422) {
                    var errors = xhr.responseJSON.errors;
                    var errorMessages = [];

                    $.each(errors, function (key, value) {
                        errorMessages.push(value[0]);
                    });

                    alert('Validation Error:\n' + errorMessages.join('\n'));
                } else if (xhr.status === 302 || xhr.status === 401) {
                    // Redirect on auth issues
                    window.location.href = xhr.getResponseHeader('Location') || '/login';
                } else {
                    var errorMsg = 'Error creating customer. Please try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    alert(errorMsg);
                }
            },
            complete: function () {
                // Hide loading state and reset submitting flag
                $btn.prop('disabled', false);
                $spinner.addClass('d-none');
                $btn.data('submitting', false);
            }
        });

        return false;
    });

    // Reset form when modal is closed
    $('#customerModal').on('hidden.bs.modal', function () {
        // Reset form fields
        $('#customerForm')[0].reset();

        // Reset Select2 dropdowns properly
        $('#customerForm').find('select').each(function() {
            $(this).val(null).trigger('change');
        });

        // Reset any validation error styles
        $('#customerForm .form-control').removeClass('is-invalid');
    });
});

$(function() {
    // Get dynamic URLs from form data attributes
    var $form = $("form[data-product-search]");
    var productSearchUrl = $form.data('product-search');
    var purchaseAddRowUrl = $form.data('purchase-add-row');
    var productInfoUrlBase = $form.data('product-info');

    // Initialize Select2 for dropdowns
    $('.select').select2({
        width: '100%'
    });

    // Initialize Select2 for product dropdown with AJAX search
    $(".product").select2({
        width: '100%',
        ajax: {
            url: productSearchUrl,
            type: "GET",
            dataType: 'json',
            delay: 500,
            data: function (params) {
                return {
                    search: params.term, // search term
                    type: 'purchase', // type term
                    supplier_id: $('#supplier_id').val() // type term
                };
            },
            processResults: function (response) {
                return {
                    results: response
                };
            },
            cache: true
        }
    });

    // Function to initialize Select2 for a newly added row
    function initializeSelect2ForRow($row) {
        $row.find('.select').select2({
            width: '100%'
        });

        $row.find(".product").select2({
            width: '100%',
            ajax: {
                url: productSearchUrl,
                type: "GET",
                dataType: 'json',
                delay: 500,
                data: function (params) {
                    return {
                        search: params.term,
                        type: 'purchase', // type term
                        supplier_id: $('#supplier_id').val() // type term
                    };
                },
                processResults: function (response) {
                    return { results: response };
                },
                cache: true
            }
        });
    }

    // Calculate totals for purchase form
    function totalCalculation() {
        var totalPurchasePrice = 0;
        $('.total-purchase-price').each(function(i, e) {
            var amt = $(this).val() - 0;
            totalPurchasePrice += amt;
        });

        $('.sub-total').val(totalPurchasePrice);
        var vat = parseFloat($('.vat').val());
        var discount = parseFloat($('.discount').val());
        if (isNaN(discount)) {
            discount = 0;
        }
        if (isNaN(vat)) {
            vat = 0;
        }
        $('.grand-total').val(totalPurchasePrice + vat - discount);
        var grandTotal = totalPurchasePrice + vat - discount;
        var paid = parseFloat($('.paid-amount').val());
        var due = grandTotal - paid;
        $('.due-amount').val(due);
    }

    
    // Handle VAT input changes
    $('.vat').keyup(function() {
        var subTotal = parseFloat($('.sub-total').val());
        var vat = $(this).val();
        if (isNaN(vat)) {
            vat = 0;
        }
        if (subTotal < vat) {
            alert('Vat cannot greater than the total amount');
            $('.vat').val(0.00);
        }
        totalCalculation();
    });

    // Handle discount input changes
    $('.discount').keyup(function() {
        var subTotal = parseFloat($('.sub-total').val());
        var discount = $(this).val();
        if (isNaN(discount)) {
            discount = 0;
        }
        if (subTotal < discount) {
            alert('Discount cannot greater than the total amount');
            $('.discount').val(0.00);
        }
        totalCalculation();
    });

    // Handle paid amount input changes
    $('.paid-amount').keyup(function() {
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

    // Add new purchase row via AJAX
    $('.add').click(function() {
        if ($('.product').val() == '') {
            alert('Select an item first');
        } else {
            $.ajax({
                type: 'GET', //THIS NEEDS TO BE GET
                url: purchaseAddRowUrl,
                datatype: 'json',
                success: function(data) {
                    var $newRow = $(data);
                    $('.neworderbody').append($newRow);
                    initializeSelect2ForRow($newRow);
                },
                error: function() {
                }
            });
        }
    });

    // Remove a purchase row
    $('.neworderbody').delegate('.delete', 'click', function() {
        $(this).parent().parent().remove();
        totalCalculation();
    });

    // When product changes, fetch info and update row
    $('.neworderbody').delegate('.product', 'change', function() {
        var tr = $(this).parent().parent();
        var productId = $(this).val();
        $.ajax({
            type: 'GET',
            url: productInfoUrlBase + '/' + productId,
            datatype: 'json',
            success: function(data) {
                var purchasePrice = data.purchase_price || 0;
                var mrpPrice = data.mrp_price || 0;
                tr.find('.purchase-price').val(purchasePrice);
                tr.find('.mrp-price').val(mrpPrice);
                tr.find('.stock-qty').val(data.stock || 0);
                var qty = tr.find('.qty').val() || 0;
                tr.find('.total-purchase-price').val(qty * purchasePrice);
                totalCalculation();
            },
            error: function() {
            }
        });
    });

    
    // When quantity changes, update total price
    $('.neworderbody').delegate('.qty', 'keyup', function() {
        var tr = $(this).parent().parent();
        var qty = $(this).val();
        var purchasePrice = tr.find('.purchase-price').val();
        tr.find('.total-purchase-price').val(qty * purchasePrice);
        totalCalculation();
    });

    // Function to count comma-separated serial numbers and update quantity
    function countSerialNumbersAndUpdateQuantity($serialInput) {
        var serialValue = $serialInput.val().trim();
        var count = 0;

        if (serialValue !== '') {
            // Split by comma and filter out empty strings
            var serialNumbers = serialValue.split(',');
            serialNumbers = serialNumbers.filter(function(serial) {
                return serial.trim() !== '';
            });
            count = serialNumbers.length;
        }

        // Update the quantity field in the same row
        var $qtyInput = $serialInput.closest('tr').find('.qty');
        $qtyInput.val(count);

        // Trigger the qty keyup event to update total price
        $qtyInput.trigger('keyup');

        return count;
    }

    // When serial number input changes, count serial numbers and update quantity
    $('.neworderbody').delegate('.serial-no', 'keyup', function() {  
        countSerialNumbersAndUpdateQuantity($(this));
    });

    // When serial number input loses focus, also update quantity
    $('.neworderbody').delegate('.serial-no', 'blur', function() {
        countSerialNumbersAndUpdateQuantity($(this));
    });

    // When serial number input is pasted into, update quantity after a short delay
    $('.neworderbody').delegate('.serial-no', 'paste', function() {
        var $this = $(this);
        setTimeout(function() {
            countSerialNumbersAndUpdateQuantity($this);
        }, 100);
    });


    // When purchase price changes, update total price
    $('.neworderbody').delegate('.purchase-price', 'keyup', function() {
        var tr = $(this).parent().parent();
        var purchasePrice = $(this).val();
        var qty = tr.find('.qty').val();
        tr.find('.total-purchase-price').val(qty * purchasePrice);
        totalCalculation();
    });

    // (Optional) When MRP changes, can add validation if needed
    $('.neworderbody').delegate('.mrp-price', 'keyup', function() {
        var tr = $(this).parent().parent();
        var mrpPrice = tr.find('.mrp-price').val();
    });

    
    // On form submit, validate paid amount is not greater than grand total
    $("form").submit(function() {
        var grandTotal = parseFloat($('.grand-total').val());
        var paid = parseFloat($('.paid-amount').val());
        if (paid > grandTotal) {
            alert('Paid cannot be greater than grand total');
            $('.paid-amount').val(0.00);
            totalCalculation();
            return false;
        }
    });
});

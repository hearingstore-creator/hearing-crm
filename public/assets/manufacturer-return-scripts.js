// Calculate and update the grand total and total deduction fields
function totalCalculation() {
    var totalPrice = 0;
    $('.total-price').each(function (i, e) {
        var amt = $(this).val() - 0;
        totalPrice += amt;
    });

    var totalDeduction = 0;
    $('.deduction-taka').each(function (i, e) {
        var total = $(this).val() - 0;
        totalDeduction += total;
    });

    // Update total deduction and grand total fields
    $('.total-deduction').val(totalDeduction);
    $('.grand-total').val(totalPrice);
}

// Calculate deduction and total price for a single row
function total(tr) {
    var deduction = tr.find('.deduction').val();
    var returnQty = tr.find('.return-qty').val();
    var unitPrice = tr.find('.unit-price').val();

    // Validate and set default values if not numeric
    if (isNaN(deduction)) {
        deduction = 0;
    }
    if (isNaN(unitPrice)) {
        unitPrice = 0;
    }
    if (isNaN(returnQty)) {
        returnQty = 0;
    }
    // Calculate total price and deduction amount
    var totalPrice = returnQty * unitPrice;
    var deductionTaka = (totalPrice / 100) * deduction;
    tr.find('.deduction-taka').val(deductionTaka.toFixed(2));
    var calcPrice = totalPrice - deductionTaka;
    tr.find('.total-price').val(calcPrice.toFixed(2));
}

// Document ready: set up event handlers for calculation and validation
$(function () {
    // Initial calculation on page load
    totalCalculation();

    // Handle changes to return quantity
    $('.neworderbody').delegate('.return-qty', 'keyup', function () {
        var tr = $(this).parent().parent();
        var returnQty = $(this).val();
        var unitPrice = tr.find('.unit-price').val();
        var stockQty = tr.find('.stock-qty').val();

        // Validate return quantity
        if (!$.isNumeric(returnQty)) {
            alert('Value must be numeric');
            tr.find('.return-qty').val(1);
        } else if (returnQty < 1) {
            alert('Value must be greater than 0');
            tr.find('.return-qty').val(1);
        }

        if (parseInt(returnQty) > parseInt(stockQty)) {
            alert('Return qty cannot be greater than stock qty');
            tr.find('.return-qty').val(1);
        }

        // Recalculate totals for this row and all rows
        total(tr);
        totalCalculation();
    });

    // Handle changes to unit price
    $('.neworderbody').delegate('.unit-price', 'keyup', function () {
        var tr = $(this).parent().parent();
        var unitPrice = $(this).val();

        // Validate unit price
        if (!$.isNumeric(unitPrice)) {
            alert('Value must be numeric');
            tr.find('.unit-price').val(0.00);
        }
        // Recalculate totals for this row and all rows
        total(tr);
        totalCalculation();
    });

    // Handle changes to deduction percentage
    $('.neworderbody').delegate('.deduction', 'keyup', function () {
        var tr = $(this).parent().parent();
        var deduction = parseFloat($(this).val());
        var qty = tr.find('.qty').val();
        var unitPrice = tr.find('.unit-price').val();

        // Validate values
        if (isNaN(unitPrice)) {
            unitPrice = 0;
        }
        if (isNaN(qty)) {
            qty = 0;
        }
        var totalPrice = qty * unitPrice;

        if (!$.isNumeric(deduction)) {
            alert('Value must be numeric');
            tr.find('.deduction').val(0.00);
        }
        var deductionTaka = (totalPrice / 100) * deduction;
        tr.find('.deduction-taka').val(deductionTaka.toFixed(2));

        // Prevent deduction from exceeding total price
        if (deductionTaka > totalPrice) {
            alert('deduction cannot be greater than total price');
            tr.find('.deduction').val(0.00);
        }
        // Recalculate totals for this row and all rows
        total(tr);
        totalCalculation();
    });

    // Validate paid amount before form submission
    $("form").submit(function () {
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

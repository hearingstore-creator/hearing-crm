
   /**
 * Calculate and update the grand total and total deduction for the customer return form
 * @description
 * - Sums up all product return line items' total prices
 * - Calculates the sum of all deductions in Taka
 * - Updates the total deduction field and grand total field
 * - This function should be called whenever any row values change
 * @returns {void}
 */
function totalCalculation() {
    // Calculate total price from all items
    var totalPrice = 0;
    $('.total-price').each(function(i, e) {
        var amt = $(this).val() - 0;
        totalPrice += amt;
    });
    
    // Calculate total deduction from all items
    var totalDeduction = 0;
    $('.deduction-taka').each(function(i, e) {
        var total = $(this).val() - 0;
        totalDeduction += total;
    });

    // Update the form totals
    $('.total-deduction').val(totalDeduction);
    $('.grand-total').val(totalPrice);
}

/**
 * Calculate total price and deduction for a single row
 * @param {Object} tr - jQuery object representing the table row
 */
function total(tr) {
    // Get values from the row
    var deduction = tr.find('.deduction').val();
    var returnQty = tr.find('.return-qty').val();
    var unitPrice = tr.find('.unit-price').val();

    // Validate and set default values for calculations
    if (isNaN(deduction)) {
        deduction = 0;
    }
    if (isNaN(unitPrice)) {
        unitPrice = 0;
    }
    if (isNaN(returnQty)) {
        returnQty = 0;
    }

    // Calculate row totals
    var totalPrice = returnQty * unitPrice;
    var deductionTaka = (totalPrice / 100) * deduction;
    
    // Update row values with formatted numbers
    tr.find('.deduction-taka').val(deductionTaka.toFixed(2));
    var calcPrice = totalPrice - deductionTaka;
    tr.find('.total-price').val(calcPrice.toFixed(2));
}

// Document ready handler
$(function() {
    // Initialize calculations on page load
    totalCalculation();

    // Handle return quantity changes
    $('.neworderbody').delegate('.return-qty', 'keyup', function() {
        var tr = $(this).parent().parent();
        var returnQty = $(this).val();
        var unitPrice = tr.find('.unit-price').val();
        var qty = tr.find('.qty').val();

        // Validate return quantity
        if (!$.isNumeric(returnQty)) {
            alert('Value must be numeric');
            tr.find('.return-qty').val(1);
        } else if (returnQty < 1) {
            alert('Value must be greater than 0');
            tr.find('.return-qty').val(1);
        }

        // Check if return quantity exceeds sold quantity
        if (parseInt(returnQty) > parseInt(qty)) {
            alert('Return qty cannot be greater than sold qty');
            tr.find('.return-qty').val(1);
        }

        // Update calculations
        total(tr);
        totalCalculation();
    });

    // Handle unit price changes
    $('.neworderbody').delegate('.unit-price', 'keyup', function() {
        var tr = $(this).parent().parent();
        var unitPrice = $(this).val();

        // Validate unit price
        if (!$.isNumeric(unitPrice)) {
            alert('Value must be numeric');
            tr.find('.unit-price').val(0.00);
        }
        
        // Update calculations
        total(tr);
        totalCalculation();
    });

    // Handle deduction percentage changes
    $('.neworderbody').delegate('.deduction', 'keyup', function() {
        var tr = $(this).parent().parent();
        var deduction = parseFloat($(this).val());
        var qty = tr.find('.qty').val();
        var unitPrice = tr.find('.unit-price').val();

        // Validate and set defaults
        if (isNaN(unitPrice)) {
            unitPrice = 0;
        }
        if (isNaN(qty)) {
            qty = 0;
        }

        // Calculate totals
        var totalPrice = qty * unitPrice;

        // Validate deduction
        if (!$.isNumeric(deduction)) {
            alert('Value must be numeric');
            tr.find('.deduction').val(0.00);
        }

        // Calculate deduction amount
        var deductionTaka = (totalPrice / 100) * deduction;
        tr.find('.deduction-taka').val(deductionTaka.toFixed(2));

        // Prevent deduction from exceeding total price
        if (deductionTaka > totalPrice) {
            alert('deduction cannot be greater than total price');
            tr.find('.deduction').val(0.00);
        }

        // Update calculations
        total(tr);
        totalCalculation();
    });

    // Form submission validation
    $("form").submit(function() {
        var grandTotal = parseFloat($('.grand-total').val());
        var paid = parseFloat($('.paid-amount').val());

        // Validate paid amount
        if (paid > grandTotal) {
            alert('Paid cannot be greater than grand total');
            $('.paid-amount').val(0.00);
            totalCalculation();
            return false;
        }
    });
});

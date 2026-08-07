/**
 * Calculates the gross salary based on basic salary, earnings, and deductions
 * Validates that earnings and deductions don't exceed basic salary
 * Updates the gross salary field with the calculated total
 */
function grossSalary() {
    // Parse input values from form fields
    var basic_salary = parseFloat($('#basic_salary').val());
    var earning = parseFloat($('#earning').val());
    var deduction = parseFloat($('#deduction').val());

    // Handle NaN cases for earning and deduction
    if (isNaN(earning)) {
        earning = 0;
    }
    if (isNaN(deduction)) {
        deduction = 0;
    }

    // Validate earning against basic salary
    if (earning > basic_salary) {
        alert('Earning cannot greater than the basic');
        $('#earning').val(0);
        earning = parseFloat($('#earning').val());
    }

    // Validate deduction against basic salary
    if (deduction > basic_salary) {
        alert('Deduction cannot greater than the basic');
        $('#deduction').val(0);
        deduction = parseFloat($('#deduction').val());
    }

    // Calculate and set the gross salary
    var total = 0;
    total = basic_salary + earning - deduction;
    $('#gross_salary').val(total);
}

// Initialize select2 for employee dropdown with AJAX search
$(".employee").select2({
    width: '100%',
    ajax: { 
        url: $('.employee').data('employee-search'), // URL should be defined in the blade template
        type: "GET",
        dataType: 'json',
        delay: 500,
        data: function (params) {
            return {
                search: params.term // search term
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

// Handle employee selection change
$("#employee_id").change(function() {
    var id = $(this).val();        
        
    $.ajax({
        type: "GET",
        data: {id: id},
        url: $('.employee').data('employee-salary'), // URL should be defined in the blade template
        cache: false,
        success: function(data) {
            $('#basic_salary').val(data.employee.salary);
            $('#salary_type').val(data.employee.salary_type);
            $('#due').val(data.due);
            grossSalary();
        } 
    });
});

// Recalculate gross salary when earning changes
$('#earning').keyup(function() {
    grossSalary();
});

// Recalculate gross salary when deduction changes
$('#deduction').keyup(function() {
    grossSalary();
});

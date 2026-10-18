/**
 * Displays a confirmation dialog before proceeding with a delete action.
 * @param {Event} event The event object.
 * @returns {boolean} True if the user confirms, false otherwise.
 */
function confirmDelete(event) {
    var x = confirm("Are you sure you want to delete?");
    if (x)
        return true;
    else
        event.preventDefault();
    return false;
}

/**
 * Asks for a cancellation reason before submitting an invoice / payment cancel form.
 * The reason is sent as "reason"; the server reverses the ledger entries instead of deleting them.
 * @param {Event} event The click event of the submit button.
 * @param {string} what Short description shown in the prompt, e.g. "this sale".
 */
function confirmCancelWithReason(event, what) {
    event.preventDefault();
    var form = event.target.closest('form');
    var reason = window.prompt('Cancel ' + (what || 'this record') + '?\n\nIts ledger entries will be reversed (kept in history), not deleted.\nPlease enter the reason:');
    if (reason === null) {
        return false;
    }
    reason = reason.trim();
    if (reason.length < 5) {
        alert('Please enter a clear reason (at least 5 characters).');
        return false;
    }
    var input = form.querySelector('input[name="reason"]');
    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'reason';
        form.appendChild(input);
    }
    input.value = reason;
    form.submit();
    return false;
}

$(document).ready(function($) {
    /**
     * Checks if a user has already seen the eBook modal.
     * If not, it displays the modal.
     */
    // Check if user has seen the modal
    var hadModal = localStorage.getItem('hadModal') || '';

    // Show modal if not seen
    if (!hadModal) {
        var $modal = $('#eBookModal');
        if ($modal.length) {
            $modal.modal('show');
        }
    }

    /**
     * Sets a flag in localStorage when the eBook modal is shown.
     */
    $('#eBookModal').on('shown.bs.modal', function () {
        localStorage.setItem('hadModal', 'true');
    });
});

/**
 * Clears the 'hadModal' item from localStorage on logout.
 */
$('#logout-link').on('click', function(event) {
    event.preventDefault();

    // Remove hadModal key from localStorage
    localStorage.removeItem('hadModal');

    // Submit the logout form
    document.getElementById('logout-form').submit();
});

/**
 * Adds the 'active' class to the current sidebar menu item.
 */
$(function () {
    var url = window.location;

    // for single sidebar menu
    $('ul.nav-sidebar a').filter(function () {
        return this.href == url;
    }).addClass('active');

    // for sidebar menu and treeview
    $('ul.nav-treeview a').filter(function () {
        return this.href == url;
    }).parentsUntil(".nav-sidebar > .nav-treeview")
        .css({'display': 'block'})
        .addClass('menu-open').prev('a')
        .addClass('active');
});

// Track the Chart.js instance globally
let salesChartInstance = null;

function initializeDashboardCharts(medicineNames, quantities, salesData) {
    // Destroy previous chart instance if it exists
    if (salesChartInstance) {
        salesChartInstance.destroy();
    }

    // Best Sales Chart Configuration
    salesChartInstance = new Chart(document.getElementById('myChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: medicineNames,
            datasets: [{
                label: '# Sales Quantity',
                data: quantities,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(255, 206, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(255, 159, 64, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });

    // CanvasJS chart (no need to destroy, as it handles re-rendering)
    const reportChart = new CanvasJS.Chart("chartContainer-topReference", {
        exportEnabled: true,
        animationEnabled: true,
        responsive: true,
        legend: {
            cursor: "pointer",
            horizontalAlign: "right",
            verticalAlign: "center",
            fontSize: 14,
            padding: {
                top: 20,
                bottom: 2,
                right: 20,
            }
        },
        data: [{
            type: "pie",
            startAngle: 45,
            showInLegend: true,
            legendText: "{label}",
            indexLabel: "{label} ({y})",
            yValueFormatString: "#,##0.#\"%\"",
            dataPoints: salesData
        }]
    });
    reportChart.render();
}

document.addEventListener('DOMContentLoaded', function() {
    if (window.dashboardData) {
        initializeDashboardCharts(
            window.dashboardData.medicineNames,
            window.dashboardData.qtys,
            window.dashboardData.salesData
        );
    }
});

// Print a specific div by id
function printDiv(divName) {
    var printContents = document.getElementById(divName).innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
}

// Print button handler
$(document).on('click', '.print-btn', function (e) {
    e.preventDefault();
    printDiv('print-area');
});

if ($('.select').length) {
    $('.select').select2({ width: '100%' });
}

if ($('.customer').length) {
    $(".customer").select2({
        width: '100%',
        ajax: { 
            url: $(".customer-payment").data('customer-search'),
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
}

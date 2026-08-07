/**
 * Receipt Print Handler
 * Vanilla JavaScript for handling receipt printing functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Auto print on page load
    window.addEventListener('load', function() {
        window.print();
    });
    
    // Handle buttons with class 'print-btn'
    const printButtons = document.querySelectorAll('.print-btn');
    printButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            window.print();
        });
    });

    // Handle legacy ID selector (for backward compatibility)
    const legacyPrintBtn = document.querySelector('#btnPrint');
    if (legacyPrintBtn) {
        legacyPrintBtn.addEventListener('click', function() {
            window.print();
        });
    }
    
    // Check if print is supported
    if (typeof window.print !== 'function') {
        console.warn('Print functionality is not supported in this browser');
    }
    
});

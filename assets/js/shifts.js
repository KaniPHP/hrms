(function () {
    'use strict';

    function initShifts() {
        if (window.HRMS) window.HRMS.init();
    }

    document.addEventListener('DOMContentLoaded', initShifts);
    document.addEventListener('hrms:content-updated', initShifts);
}());

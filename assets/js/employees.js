(function () {
    'use strict';

    function initEmployees() {
        if (window.HRMS) window.HRMS.init();
    }

    document.addEventListener('DOMContentLoaded', initEmployees);
    document.addEventListener('hrms:content-updated', initEmployees);
}());

(function () {
    'use strict';

    function initDashboard() {
        if (window.HRMS) window.HRMS.init();
    }

    document.addEventListener('DOMContentLoaded', initDashboard);
    document.addEventListener('hrms:content-updated', initDashboard);
}());

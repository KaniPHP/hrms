(function () {
    'use strict';

    function initAttendance() {
        if (window.HRMS) window.HRMS.init();
    }

    document.addEventListener('DOMContentLoaded', initAttendance);
    document.addEventListener('hrms:content-updated', initAttendance);
}());

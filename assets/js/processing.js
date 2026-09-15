(function () {
    'use strict';

    function initProcessing() {
        if (window.HRMS) window.HRMS.init();
    }

    document.addEventListener('DOMContentLoaded', initProcessing);
    document.addEventListener('hrms:content-updated', initProcessing);
}());

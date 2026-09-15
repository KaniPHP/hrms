(function () {
    'use strict';

    function initLeaveForm() {
        var from = document.querySelector('input[name="from_date"]');
        var to = document.querySelector('input[name="to_date"]');
        var duration = document.querySelector('select[name="leave_day"]');
        var days = document.querySelector('input[name="days_requested"]');
        if (!from || !to || !duration || !days || from.dataset.leaveBound === 'true') return;

        function calculate() {
            if (duration.value === 'half') {
                days.value = '0.5';
                to.value = from.value;
                to.min = from.value;
                return;
            }
            if (!from.value || !to.value) return;
            var start = new Date(from.value + 'T00:00:00');
            var end = new Date(to.value + 'T00:00:00');
            var difference = Math.round((end - start) / 86400000);
            days.value = difference >= 0 ? String(difference + 1) : '';
        }

        from.dataset.leaveBound = 'true';
        from.addEventListener('change', calculate);
        to.addEventListener('change', calculate);
        duration.addEventListener('change', calculate);
        document.querySelector('select[name="employee_id"]').addEventListener('change', renderBalances);
        calculate();
    }

    function renderBalances() {
        var select = document.querySelector('select[name="employee_id"]');
        var container = document.querySelector('#leave-balance-cards');
        if (!select || !container || !window.HRMSLeaveBalances) return;
        var balances = window.HRMSLeaveBalances[select.value] || {};
        var order = ['WO', 'EL', 'FL', 'CPL', 'CL'];
        container.innerHTML = '';
        order.forEach(function (code) {
            var balance = balances[code];
            var card = document.createElement('div');
            card.className = 'balance-card';
            card.innerHTML = '<strong>' + code + '</strong><span>Available <b>' +
                (balance ? balance.closing_balance : '0.00') +
                '</b></span><small>Taken: ' + (balance ? balance.utilized_balance : '0.00') +
                ' · Carry: ' + (balance ? balance.carry_forward : '0.00') + '</small>';
            container.appendChild(card);
        });
    }

    initLeaveForm();
    renderBalances();
    if (window.HRMS) window.HRMS.init();
    document.addEventListener('hrms:content-updated', initLeaveForm);
    document.addEventListener('hrms:content-updated', renderBalances);
    document.addEventListener('hrms:content-updated', function () {
        if (window.HRMS) window.HRMS.init();
    });
}());

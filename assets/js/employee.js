(function ($) {
    'use strict';

    function displayDate(value) {
        if (!value) return '';
        var parts = String(value).slice(0, 10).split('-');
        return parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : value;
    }

    function flash(message, type) {
        var box = $('<div class="flash"></div>').addClass(type || 'success').text(message);
        $('#employee-leave-flash').empty().append(box);
        window.setTimeout(function () { box.fadeOut(300, function () { $(this).remove(); }); }, 100000);
    }

    var balancesData = [];
    var historyData = [];
    var pageSize = 5;

    function renderPagination(selector, total, page, callback) {
        var pages = Math.max(1, Math.ceil(total / pageSize));
        var html = '';
        for (var i = 1; i <= pages; i++) {
            html += '<a href="#" class="' + (i === page ? 'active' : '') + '" data-page="' + i + '">' + i + '</a>';
        }
        $(selector).html(html).off('click').on('click', 'a', function (event) {
            event.preventDefault();
            callback(parseInt($(this).data('page'), 10));
        });
    }

    function renderBalances(page) {
        var start = (page - 1) * pageSize;
        var rows = balancesData.slice(start, start + pageSize);
        var html = '';
        if (!rows.length) html = '<div class="empty-state">No balance configured for this month.</div>';
        $.each(rows, function (_, row) {
            html += '<article class="employee-balance-card"><div class="balance-card-top"><span class="leave-code">' +
                row.leave_type_code + '</span><span class="balance-available">' + row.closing_balance +
                '</span></div><span class="balance-caption">Available balance</span><div class="balance-card-stats">' +
                '<span><small>Opening</small><b>' + row.opening_balance + '</b></span>' +
                '<span><small>Earned</small><b>' + row.earned_balance + '</b></span>' +
                '<span><small>Taken</small><b>' + row.utilized_balance + '</b></span>' +
                '<span><small>Carry forward</small><b>' + row.carry_forward + '</b></span></div></article>';
        });
        $('#leave-balance-cards').html(html);
        renderPagination('#leave-balances-pagination', balancesData.length, page, renderBalances);
    }

    function renderHistory(page) {
        var start = (page - 1) * pageSize;
        var rows = historyData.slice(start, start + pageSize);
        var history = '';
        if (!rows.length) history = '<tr><td colspan="6" class="empty-state">No leave applications.</td></tr>';
        $.each(rows, function (_, row) {
            var cancel = row.status === 'pending'
                ? '<form class="inline-form employee-cancel-form"><input type="hidden" name="csrf_token" value="' +
                  $('input[name="csrf_token"]').first().val() + '"><input type="hidden" name="action" value="cancel">' +
                  '<input type="hidden" name="id" value="' + row.id + '">' +
                  '<button class="btn btn-small" type="submit">Cancel</button></form>' : '';
            history += '<tr><td>' + row.leave_type_code + '</td><td>' + displayDate(row.from_date) + ' → ' +
                displayDate(row.to_date) + '</td><td>' + row.days_requested + '</td><td>' + row.status +
                '</td><td>' + (row.reason || '') + '</td><td>' + cancel + '</td></tr>';
        });
        $('#leave-history-body').html(history);
        renderPagination('#leave-history-pagination', historyData.length, page, renderHistory);
    }

    function toggleHalfDaySession() {
        var halfDay = $('select[name="leave_day"]').val() === 'half';
        $('.half-day-session-field').toggle(halfDay);
        $('select[name="half_day_session"]').prop('required', halfDay);
        if (!halfDay) $('select[name="half_day_session"]').val('');
    }

    function calculateDays() {
        var from = $('input[name="from_date"]').val();
        var to = $('input[name="to_date"]').val();
        function parseDate(value) {
            var parts = value.split('/');
            if (parts.length !== 3) return null;
            var date = new Date(Number(parts[2]), Number(parts[1]) - 1, Number(parts[0]));
            return date.getFullYear() === Number(parts[2]) &&
                date.getMonth() === Number(parts[1]) - 1 &&
                date.getDate() === Number(parts[0]) ? date : null;
        }
        if ($('select[name="leave_day"]').val() === 'half') {
            $('input[name="days_requested"]').val('0.5');
            if (from) $('input[name="to_date"]').val(from);
            return;
        }
        if (!from || !to) return;
        var start = parseDate(from);
        var end = parseDate(to);
        if (!start || !end) {
            $('input[name="days_requested"]').val('');
            return;
        }
        var difference = Math.round((end - start) / 86400000);
        $('input[name="days_requested"]').val(difference >= 0 ? difference + 1 : '');
    }

    function loadLeaveData(month) {
        $.ajax({
            url: 'leave.php',
            method: 'GET',
            data: { ajax: '1', month: month },
            dataType: 'json'
        }).done(function (data) {
            balancesData = data.balances || [];
            historyData = data.history || [];
            renderBalances(1);
            $('#leave-month-label').text(data.month);
            $('#balance-period').text(data.month);
            renderHistory(1);
        }).fail(function (xhr) {
            flash(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to load leave data.', 'error');
        });
    }

    $(function () {
        loadLeaveData($('input[name="month"]').val());
        calculateDays();
        toggleHalfDaySession();
        $(document).on('change', 'input[name="from_date"], input[name="to_date"], select[name="leave_day"]', function () {
            calculateDays();
            toggleHalfDaySession();
        });
        $('#leave-month-form').on('submit', function (event) {
            event.preventDefault();
            loadLeaveData($(this).find('input[name="month"]').val());
        });
        $('#employee-leave-form').on('submit', function (event) {
            event.preventDefault();
            $.ajax({
                url: 'leave.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).done(function (data) {
                flash(data.message, data.type);
                loadLeaveData($('input[name="month"]').val());
                $('#employee-leave-form')[0].reset();
                calculateDays();
            }).fail(function (xhr) {
                flash(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to submit leave request.', 'error');
            });
        });
        $(document).on('submit', '.employee-cancel-form', function (event) {
            event.preventDefault();
            var form = this;
            $.ajax({
                url: 'leave.php',
                method: 'POST',
                data: $(form).serialize(),
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).done(function (data) {
                flash(data.message, data.type);
                loadLeaveData($('input[name="month"]').val());
            }).fail(function (xhr) {
                flash(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to cancel leave request.', 'error');
            });
        });
    });
}(window.jQuery));

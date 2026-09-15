(function ($) {
    'use strict';

    function flash(message, type) {
        var box = $('<div class="flash"></div>').addClass(type || 'success').text(message);
        $('#employee-leave-flash').empty().append(box);
        window.setTimeout(function () { box.fadeOut(300, function () { $(this).remove(); }); }, 100000);
    }

    function calculateDays() {
        var from = $('input[name="from_date"]').val();
        var to = $('input[name="to_date"]').val();
        if ($('select[name="leave_day"]').val() === 'half') {
            $('input[name="days_requested"]').val('0.5');
            if (from) $('input[name="to_date"]').val(from);
            return;
        }
        if (!from || !to) return;
        var start = new Date(from + 'T00:00:00');
        var end = new Date(to + 'T00:00:00');
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
            var balances = '';
            if (!data.balances.length) {
                balances = '<tr><td colspan="6" class="empty-state">No balance configured.</td></tr>';
            } else {
                $.each(data.balances, function (_, row) {
                    balances += '<tr><td>' + row.leave_type_code + '</td><td>' + row.opening_balance +
                        '</td><td>' + row.earned_balance + '</td><td>' + row.utilized_balance +
                        '</td><td><strong>' + row.closing_balance + '</strong></td><td>' +
                        row.carry_forward + '</td></tr>';
                });
            }
            $('#leave-balances-body').html(balances);
            $('#leave-month-label').text(data.month);

            var history = '';
            if (!data.history.length) {
                history = '<tr><td colspan="6" class="empty-state">No leave applications.</td></tr>';
            } else {
                $.each(data.history, function (_, row) {
                    var cancel = row.status === 'pending'
                        ? '<form class="inline-form employee-cancel-form"><input type="hidden" name="csrf_token" value="' +
                          $('input[name="csrf_token"]').first().val() + '"><input type="hidden" name="action" value="cancel">' +
                          '<input type="hidden" name="id" value="' + row.id + '">' +
                          '<button class="btn btn-small" type="submit">Cancel</button></form>' : '';
                    history += '<tr><td>' + row.leave_type_code + '</td><td>' + row.from_date + ' → ' +
                        row.to_date + '</td><td>' + row.days_requested + '</td><td>' + row.status +
                        '</td><td>' + (row.reason || '') + '</td><td>' + cancel + '</td></tr>';
                });
            }
            $('#leave-history-body').html(history);
        }).fail(function (xhr) {
            flash(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Unable to load leave data.', 'error');
        });
    }

    $(function () {
        loadLeaveData($('input[name="month"]').val());
        calculateDays();
        $(document).on('change', 'input[name="from_date"], input[name="to_date"], select[name="leave_day"]', calculateDays);
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

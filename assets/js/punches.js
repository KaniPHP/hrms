(function () {
    'use strict';

    function isValidDate(value) {
        var parts = value.split('/');
        if (parts.length !== 3 || parts[0].length !== 2 || parts[1].length !== 2 || parts[2].length !== 4) {
            return false;
        }
        var day = Number(parts[0]);
        var month = Number(parts[1]);
        var year = Number(parts[2]);
        var date = new Date(year, month - 1, day);
        return day >= 1 && month >= 1 && month <= 12 &&
            date.getFullYear() === year &&
            date.getMonth() === month - 1 &&
            date.getDate() === day;
    }

    function bindDateInputs() {
        document.querySelectorAll('input[name="date"], input[name="punch_date"]').forEach(function (input) {
            if (input.dataset.sharedDateBound === 'true') return;
            if (input.dataset.dateBound === 'true') return;
            input.dataset.dateBound = 'true';
            var picker = document.createElement('input');
            picker.type = 'date';
            picker.tabIndex = -1;
            picker.setAttribute('aria-hidden', 'true');
            picker.style.position = 'absolute';
            picker.style.width = '1px';
            picker.style.height = '1px';
            picker.style.opacity = '0';
            picker.style.pointerEvents = 'none';
            picker.style.margin = '0';
            input.parentNode.insertBefore(picker, input.nextSibling);

            var calendarButton = document.createElement('button');
            calendarButton.type = 'button';
            calendarButton.textContent = '📅';
            calendarButton.title = 'Choose date';
            calendarButton.setAttribute('aria-label', 'Choose date');
            calendarButton.className = 'btn btn-small date-picker-button';
            input.parentNode.insertBefore(calendarButton, picker.nextSibling);

            function syncPicker() {
                if (!isValidDate(input.value)) {
                    picker.value = '';
                    return;
                }
                var parts = input.value.split('/');
                picker.value = parts[2] + '-' + parts[1] + '-' + parts[0];
            }

            input.addEventListener('input', function () {
                var digits = input.value.replace(/\D/g, '').slice(0, 8);
                var formatted = digits;
                if (digits.length > 2) formatted = digits.slice(0, 2) + '/' + digits.slice(2);
                if (digits.length > 4) formatted = digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4);
                input.value = formatted;
                input.setCustomValidity('');
                syncPicker();
            });
            input.addEventListener('blur', function () {
                input.setCustomValidity(isValidDate(input.value) ? '' : 'Enter a valid date as dd/mm/yyyy.');
            });
            calendarButton.addEventListener('click', function () {
                syncPicker();
                if (typeof picker.showPicker === 'function') {
                    picker.showPicker();
                } else {
                    picker.click();
                }
            });
            picker.addEventListener('change', function () {
                if (!picker.value) return;
                var parts = picker.value.split('-');
                input.value = parts[2] + '/' + parts[1] + '/' + parts[0];
                input.setCustomValidity('');
            });
            syncPicker();
        });
        document.querySelectorAll('form[data-date-form]').forEach(function (form) {
            if (form.dataset.dateFormBound === 'true') return;
            form.dataset.dateFormBound = 'true';
            form.addEventListener('submit', function (event) {
                var input = form.querySelector('input[name="date"]');
                if (!input || !isValidDate(input.value)) {
                    event.preventDefault();
                    if (input) {
                        input.setCustomValidity('Enter a valid date as dd/mm/yyyy.');
                        input.reportValidity();
                    }
                }
            });
        });
    }

    function initPunches() {
        bindDateInputs();
        if (window.HRMS) window.HRMS.init();
    }

    document.addEventListener('DOMContentLoaded', initPunches);
    document.addEventListener('hrms:content-updated', initPunches);
}());

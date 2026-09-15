(function () {
    'use strict';

    function showFlash(message, type) {
        var content = document.querySelector('.page-content');
        if (!content) return;
        var flash = document.createElement('div');
        flash.className = 'flash ' + (type || 'success');
        flash.textContent = message;
        content.insertBefore(flash, content.firstChild);
        window.setTimeout(function () {
            flash.classList.add('flash-hidden');
            window.setTimeout(function () { flash.remove(); }, 300);
        }, 100000);
    }

    function validDate(value) {
        var parts = value.split('/');
        if (parts.length !== 3 || parts[0].length !== 2 || parts[1].length !== 2 || parts[2].length !== 4) return false;
        var day = Number(parts[0]);
        var month = Number(parts[1]);
        var year = Number(parts[2]);
        var date = new Date(year, month - 1, day);
        return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day;
    }

    function bindDatePickers() {
        document.querySelectorAll('input[placeholder="dd/mm/yyyy"]').forEach(function (input) {
            if (input.dataset.sharedDateBound === 'true') return;
            input.dataset.sharedDateBound = 'true';
            var picker = document.createElement('input');
            picker.type = 'date';
            picker.tabIndex = -1;
            picker.setAttribute('aria-hidden', 'true');
            picker.style.position = 'absolute';
            picker.style.width = '1px';
            picker.style.height = '1px';
            picker.style.opacity = '0';
            picker.style.pointerEvents = 'none';
            input.parentNode.insertBefore(picker, input.nextSibling);

            function syncPicker() {
                if (!validDate(input.value)) {
                    picker.value = '';
                    return;
                }
                var parts = input.value.split('/');
                picker.value = parts[2] + '-' + parts[1] + '-' + parts[0];
            }

            input.addEventListener('click', function () {
                syncPicker();
                if (typeof picker.showPicker === 'function') picker.showPicker();
                else picker.click();
            });
            input.addEventListener('input', function () {
                var digits = input.value.replace(/\D/g, '').slice(0, 8);
                input.value = digits.length > 4
                    ? digits.slice(0, 2) + '/' + digits.slice(2, 4) + '/' + digits.slice(4)
                    : (digits.length > 2 ? digits.slice(0, 2) + '/' + digits.slice(2) : digits);
                input.setCustomValidity('');
                syncPicker();
            });
            input.addEventListener('blur', function () {
                input.setCustomValidity(validDate(input.value) ? '' : 'Enter a valid date as dd/mm/yyyy.');
            });
            picker.addEventListener('change', function () {
                if (!picker.value) return;
                var parts = picker.value.split('-');
                input.value = parts[2] + '/' + parts[1] + '/' + parts[0];
                input.setCustomValidity('');
            });
            syncPicker();
        });
        document.querySelectorAll('form').forEach(function (form) {
            if (form.dataset.sharedDateFormBound === 'true') return;
            var dateInput = form.querySelector('input[placeholder="dd/mm/yyyy"]');
            if (!dateInput) return;
            form.dataset.sharedDateFormBound = 'true';
            form.addEventListener('submit', function (event) {
                if (!validDate(dateInput.value)) {
                    event.preventDefault();
                    dateInput.setCustomValidity('Enter a valid date as dd/mm/yyyy.');
                    dateInput.reportValidity();
                }
            });
        });
    }

    function bindFlashMessages() {
        document.querySelectorAll('.flash:not([data-flash-bound])').forEach(function (message) {
            message.dataset.flashBound = 'true';
            window.setTimeout(function () {
                message.classList.add('flash-hidden');
                window.setTimeout(function () { message.remove(); }, 300);
            }, 100000);
        });
    }

    function refreshContent(url) {
        return fetch(url || window.location.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (response) {
            if (!response.ok) throw new Error('Unable to refresh page data.');
            return response.text();
        }).then(function (html) {
            var parsed = new DOMParser().parseFromString(html, 'text/html');
            var current = document.querySelector('.page-content');
            var replacement = parsed.querySelector('.page-content');
            if (current && replacement) {
                current.replaceWith(replacement);
                bindFlashMessages();
                bindDatePickers();
                document.dispatchEvent(new CustomEvent('hrms:content-updated'));
            }
        });
    }

    function bindAjaxForms() {
        document.querySelectorAll('form[data-ajax-form]:not([data-ajax-bound])').forEach(function (form) {
            form.dataset.ajaxBound = 'true';
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                var submit = form.querySelector('button[type="submit"], button:not([type])');
                if (submit) submit.disabled = true;
                var formUrl = form.getAttribute('action') || window.location.href;
                fetch(formUrl, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                }).then(function (response) {
                    return response.text().then(function (body) {
                        var data;
                        try {
                            data = JSON.parse(body);
                        } catch (parseError) {
                            if (body.indexOf('<!DOCTYPE') !== -1 || body.indexOf('<html') !== -1) {
                                throw new Error('The server returned an HTML error page. Check the PHP error log and try again.');
                            }
                            throw new Error(body || 'The server returned an empty response.');
                        }
                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'The operation could not be completed.');
                        }
                        return data;
                    });
                }).then(function (data) {
                    return refreshContent(data.refresh_url || formUrl).then(function () {
                        showFlash(data.message, data.type);
                    });
                }).catch(function (error) {
                    if (submit) submit.disabled = false;
                    showFlash(error.message, 'error');
                });
            });
        });
    }

    function init() {
        bindFlashMessages();
        bindDatePickers();
        bindAjaxForms();
    }

    window.HRMS = {
        init: init,
        refreshContent: refreshContent,
        showFlash: showFlash,
        ajax: function (url, options) {
            return fetch(url, Object.assign({
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }, options || {}));
        }
    };
    document.addEventListener('DOMContentLoaded', init);
}());

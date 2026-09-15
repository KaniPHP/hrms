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

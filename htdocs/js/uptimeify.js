/* uptimeify Plesk extension — dashboard interactions (vanilla JS, no deps) */
(function () {
    'use strict';

    // Plesk renders the CSRF token as <meta id/name="forgery_protection_token" content="...">.
    function forgeryToken() {
        var el = document.getElementById('forgery_protection_token') ||
            document.querySelector('meta[name="forgery_protection_token"]');
        if (!el) { return ''; }
        return el.content || el.getAttribute('content') || el.value || '';
    }

    function send(url, params) {
        var token = forgeryToken();
        if (token) { params.append('forgery_protection_token', token); }
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-Forgery-Protection-Token': token || ''
            },
            body: params.toString()
        }).then(function (r) { return r.json(); });
    }

    function post(url, data) {
        return send(url, new URLSearchParams(data));
    }

    // Inline, Plesk-styled message (used only for transport/JS errors; action
    // results are shown via Plesk's native status messages after reload).
    function banner(text) {
        var box = document.getElementById('uptimeify-msg');
        if (!box) {
            box = document.createElement('div');
            box.id = 'uptimeify-msg';
            box.className = 'msg-box msg-error';
            var wrap = document.querySelector('.uptimeify-dashboard') || document.body;
            wrap.insertBefore(box, wrap.firstChild);
        }
        var content = document.createElement('div');
        content.className = 'msg-content';
        content.textContent = text;
        box.innerHTML = '';
        box.appendChild(content);
    }

    // Server actions set a native Plesk status message and ask us to reload.
    function handled(res) {
        if (res && res.reload) { window.location.reload(); return true; }
        return false;
    }

    function bindFilter() {
        var input = document.getElementById('uptimeify-filter');
        if (!input) { return; }
        input.addEventListener('input', function () {
            var q = input.value.toLowerCase();
            document.querySelectorAll('.uptimeify-row').forEach(function (row) {
                var domain = (row.getAttribute('data-domain') || '').toLowerCase();
                row.style.display = domain.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    function bindEnable() {
        document.querySelectorAll('.uptimeify-enable-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var wrap = btn.closest('.uptimeify-enable');
                var domain = btn.getAttribute('data-domain');
                var customer = wrap ? (wrap.querySelector('.uptimeify-customer').value || 'auto') : 'auto';
                var pkg = wrap ? wrap.querySelector('.uptimeify-package').value : '';
                btn.disabled = true;
                post(window.UPTIMEIFY.enableUrl, {
                    domain: domain,
                    customerChoice: customer,
                    packageType: pkg
                }).then(function (res) {
                    if (!handled(res)) {
                        btn.disabled = false;
                        banner((res && res.message) || 'Request failed.');
                    }
                }).catch(function () {
                    btn.disabled = false;
                    banner('Network error.');
                });
            });
        });
    }

    // Disable uses an inline confirmation (no browser modal): unchecking the
    // toggle reveals "Remove / Cancel" controls rendered alongside it.
    function bindToggleOff() {
        document.querySelectorAll('.uptimeify-monitor-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                var cell = toggle.closest('td');
                var active = cell.querySelector('.uptimeify-active');
                var confirm = cell.querySelector('.uptimeify-confirm');
                if (!active || !confirm) { return; }
                if (!toggle.checked) {
                    active.style.display = 'none';
                    confirm.style.display = '';
                }
            });
        });

        document.querySelectorAll('.uptimeify-confirm-no').forEach(function (no) {
            no.addEventListener('click', function () {
                var cell = no.closest('td');
                var toggle = cell.querySelector('.uptimeify-monitor-toggle');
                cell.querySelector('.uptimeify-active').style.display = '';
                cell.querySelector('.uptimeify-confirm').style.display = 'none';
                if (toggle) { toggle.checked = true; }
            });
        });

        document.querySelectorAll('.uptimeify-confirm-yes').forEach(function (yes) {
            yes.addEventListener('click', function () {
                yes.disabled = true;
                post(window.UPTIMEIFY.disableUrl, {
                    domain: yes.getAttribute('data-domain'),
                    websitePublicId: yes.getAttribute('data-website')
                }).then(function (res) {
                    if (!handled(res)) {
                        yes.disabled = false;
                        banner((res && res.message) || 'Request failed.');
                    }
                }).catch(function () {
                    yes.disabled = false;
                    banner('Network error.');
                });
            });
        });
    }

    function ignoreHandler(selector, urlKey) {
        document.querySelectorAll(selector).forEach(function (btn) {
            btn.addEventListener('click', function () {
                btn.disabled = true;
                post(window.UPTIMEIFY[urlKey], { domain: btn.getAttribute('data-domain') })
                    .then(function (res) {
                        if (!handled(res)) { btn.disabled = false; banner((res && res.message) || 'Request failed.'); }
                    }).catch(function () {
                        btn.disabled = false;
                        banner('Network error.');
                    });
            });
        });
    }

    function bindIgnore() {
        ignoreHandler('.uptimeify-ignore-btn', 'ignoreUrl');
        ignoreHandler('.uptimeify-unignore-btn', 'unignoreUrl');
    }

    function selectedDomains() {
        var out = [];
        document.querySelectorAll('.uptimeify-select:checked').forEach(function (c) {
            out.push(c.getAttribute('data-domain'));
        });
        return out;
    }

    function bindSelectAll() {
        var all = document.getElementById('uptimeify-select-all');
        if (!all) { return; }
        all.addEventListener('change', function () {
            document.querySelectorAll('.uptimeify-select').forEach(function (c) {
                var row = c.closest('.uptimeify-row');
                if (row && row.style.display === 'none') { return; }
                c.checked = all.checked;
            });
        });
    }

    function runSync(btn, params) {
        btn.disabled = true;
        var original = btn.textContent;
        btn.textContent = '…';
        send(btn.getAttribute('data-url'), params).then(function (res) {
            if (!handled(res)) {
                btn.disabled = false;
                btn.textContent = original;
                banner((res && res.message) || 'Sync failed.');
            }
        }).catch(function () {
            btn.disabled = false;
            btn.textContent = original;
            banner('Network error.');
        });
    }

    function bindSyncSelected() {
        var btn = document.getElementById('uptimeify-sync-selected');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            var domains = selectedDomains();
            if (!domains.length) {
                banner(window.UPTIMEIFY.i18n.selectFirst);
                return;
            }
            var params = new URLSearchParams();
            domains.forEach(function (d) { params.append('domains[]', d); });
            runSync(btn, params);
        });
    }

    function bindSyncAll() {
        var btn = document.getElementById('uptimeify-sync-all');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            runSync(btn, new URLSearchParams());
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindFilter();
        bindEnable();
        bindToggleOff();
        bindIgnore();
        bindSelectAll();
        bindSyncSelected();
        bindSyncAll();
    });
})();

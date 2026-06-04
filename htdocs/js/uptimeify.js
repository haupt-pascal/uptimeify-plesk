/* uptimeify Plesk extension — dashboard interactions (vanilla JS, no deps) */
(function () {
    'use strict';

    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf_token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function send(url, params) {
        var token = getCsrfToken();
        if (token) {
            params.append('forgery_protection_token', token);
        }
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: params.toString()
        }).then(function (r) { return r.json(); });
    }

    function post(url, data) {
        return send(url, new URLSearchParams(data));
    }

    function summaryText(s) {
        return 'Customers created: ' + (s.customersCreated || 0) +
            ', monitors created: ' + (s.websitesCreated || 0);
    }

    function notify(message, isError) {
        // Fall back to a simple alert; Plesk's status messages render on reload.
        if (isError) {
            window.alert(message);
        }
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
                var customer = wrap.querySelector('.uptimeify-customer').value || 'auto';
                var pkg = wrap.querySelector('.uptimeify-package').value;
                btn.disabled = true;
                post(window.UPTIMEIFY.enableUrl, {
                    domain: domain,
                    customerChoice: customer,
                    packageType: pkg
                }).then(function (res) {
                    if (res.success) {
                        window.location.reload();
                    } else {
                        btn.disabled = false;
                        if (res.quota && res.upgradeUrl) {
                            if (window.confirm(res.message + '\n\nOpen upgrade page?')) {
                                window.open(res.upgradeUrl, '_blank');
                            }
                        } else {
                            notify(res.message || 'Failed to enable monitoring.', true);
                        }
                    }
                });
            });
        });
    }

    function bindToggleOff() {
        document.querySelectorAll('.uptimeify-monitor-toggle').forEach(function (toggle) {
            toggle.addEventListener('change', function () {
                if (toggle.checked) { return; }
                var domain = toggle.getAttribute('data-domain');
                var website = toggle.getAttribute('data-website');
                if (!window.confirm('Delete the uptimeify monitor for ' + domain + '? This is irreversible.')) {
                    toggle.checked = true;
                    return;
                }
                post(window.UPTIMEIFY.disableUrl, {
                    domain: domain,
                    websitePublicId: website
                }).then(function (res) {
                    if (res.success) {
                        window.location.reload();
                    } else {
                        toggle.checked = true;
                        notify(res.message || 'Failed to disable monitoring.', true);
                    }
                });
            });
        });
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
                if (row && row.style.display === 'none') { return; } // skip filtered-out rows
                c.checked = all.checked;
            });
        });
    }

    function bindSyncSelected() {
        var btn = document.getElementById('uptimeify-sync-selected');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            var domains = selectedDomains();
            if (!domains.length) {
                notify('Please select at least one domain.', true);
                return;
            }
            if (!window.confirm('Sync ' + domains.length + ' selected domain(s)?')) { return; }
            btn.disabled = true;
            var params = new URLSearchParams();
            domains.forEach(function (d) { params.append('domains[]', d); });
            send(btn.getAttribute('data-url'), params).then(function (res) {
                btn.disabled = false;
                if (res.success) {
                    var s = res.summary || {};
                    if (s.errors && s.errors.length) {
                        window.alert(summaryText(s) + '\n\nIssues:\n- ' + s.errors.join('\n- '));
                    }
                    window.location.reload();
                } else {
                    notify(res.message || 'Sync failed.', true);
                }
            });
        });
    }

    function bindSyncAll() {
        var btn = document.getElementById('uptimeify-sync-all');
        if (!btn) { return; }
        btn.addEventListener('click', function () {
            btn.disabled = true;
            var original = btn.textContent;
            btn.textContent = '…';
            post(btn.getAttribute('data-url'), {}).then(function (res) {
                btn.disabled = false;
                btn.textContent = original;
                if (res.success) {
                    var s = res.summary || {};
                    var msg = 'Customers created: ' + (s.customersCreated || 0) +
                        ', monitors created: ' + (s.websitesCreated || 0);
                    if (s.errors && s.errors.length) {
                        msg += '\n\nIssues:\n- ' + s.errors.join('\n- ');
                        window.alert(msg);
                    }
                    window.location.reload();
                } else {
                    notify(res.message || 'Sync failed.', true);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindFilter();
        bindEnable();
        bindToggleOff();
        bindSelectAll();
        bindSyncSelected();
        bindSyncAll();
    });
})();

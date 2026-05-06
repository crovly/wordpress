(function () {
    'use strict';

    var btn = document.getElementById('crovly-test-btn');
    if (!btn) return;

    var res = document.getElementById('crovly-test-result');
    var cfg = window.crovlyAdmin || {};

    btn.addEventListener('click', function () {
        btn.disabled = true;
        res.textContent = cfg.testing || 'Testing...';
        res.style.color = '#666';

        var fd = new FormData();
        fd.append('action', 'crovly_test_connection');
        fd.append('nonce', cfg.nonce || '');

        fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                res.textContent = (r && r.data && r.data.message) || '';
                res.style.color = r && r.success ? '#00a32a' : '#d63638';
                btn.disabled = false;
            })
            .catch(function () {
                res.textContent = cfg.failed || 'Request failed.';
                res.style.color = '#d63638';
                btn.disabled = false;
            });
    });
})();

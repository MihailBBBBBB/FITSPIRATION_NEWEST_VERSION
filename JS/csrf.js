(function() {
    function getCsrfToken() {
        if (document.body && document.body.dataset && document.body.dataset.csrfToken) {
            return document.body.dataset.csrfToken;
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    function ensureFormToken(form) {
        if (!form || !form.querySelector) {
            return;
        }

        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method !== 'post') {
            return;
        }

        if (form.querySelector('input[name="csrf_token"]')) {
            return;
        }

        var token = getCsrfToken();
        if (!token) {
            return;
        }

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = token;
        form.appendChild(input);
    }

    function appendCsrfToken(formData) {
        var token = getCsrfToken();
        if (formData && typeof formData.append === 'function' && token && !formData.has('csrf_token')) {
            formData.append('csrf_token', token);
        }
        return formData;
    }

    function getCsrfHeaders(headers) {
        var nextHeaders = headers || {};
        var token = getCsrfToken();
        if (token) {
            nextHeaders['X-CSRF-Token'] = token;
        }
        return nextHeaders;
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form').forEach(ensureFormToken);
    });

    window.getCsrfToken = getCsrfToken;
    window.appendCsrfToken = appendCsrfToken;
    window.getCsrfHeaders = getCsrfHeaders;
})();
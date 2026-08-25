/**
 * Central HTMX CSRF synchronization for the Control Panel (DOC-03 §11 / .cursorrules §4.5).
 *
 * - Reads token from <meta name="csrf-token"> (not from inline JS literals).
 * - Sends X-CSRF-TOKEN on every HTMX request via htmx:configRequest.
 * - After a response, updates meta + csrf_field inputs from X-CSRF-TOKEN header
 *   so regenerate=true does not leave the client with a stale token.
 *
 * Quill / Alpine remain independent; this file only wires HTMX + CSRF.
 */
(function () {
    'use strict';

    function metaContent(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        if (!el) {
            return '';
        }
        return el.getAttribute('content') || '';
    }

    function csrfToken() {
        return metaContent('csrf-token');
    }

    function csrfHeaderName() {
        return metaContent('csrf-header') || 'X-CSRF-TOKEN';
    }

    function csrfParamName() {
        return metaContent('csrf-param') || 'csrf_test_name';
    }

    function updateClientToken(token) {
        if (!token) {
            return;
        }

        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            meta.setAttribute('content', token);
        }

        var param = csrfParamName();
        document.querySelectorAll('input[name="' + param + '"]').forEach(function (input) {
            input.value = token;
        });
    }

    function onConfigRequest(event) {
        var token = csrfToken();
        if (!token) {
            return;
        }
        event.detail.headers[csrfHeaderName()] = token;
    }

    function onAfterRequest(event) {
        var xhr = event.detail.xhr;
        if (!xhr) {
            return;
        }
        var header = csrfHeaderName();
        var next = xhr.getResponseHeader(header);
        if (next) {
            updateClientToken(next);
        }
    }

    document.body.addEventListener('htmx:configRequest', onConfigRequest);
    document.body.addEventListener('htmx:afterRequest', onAfterRequest);
})();

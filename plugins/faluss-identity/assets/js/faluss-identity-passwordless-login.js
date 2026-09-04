(function () {
    'use strict';

    function setNotice(component, message) {
        var notice = component.querySelector('.faluss-identity-login__ajax-notice');
        if (!notice) { return; }
        notice.textContent = message || '';
        notice.hidden = !message;
    }

    function replaceWithOtp(form, markup) {
        var current = form.closest('.faluss-identity-login__otp-stage') || form;
        var wrapper = document.createElement('div');
        wrapper.innerHTML = markup;
        var stage = wrapper.firstElementChild;
        if (stage) { current.replaceWith(stage); }
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) { return; }
        var component = form.closest('[data-faluss-identity-login]');
        if (!component || !window.falussIdentityLogin) { return; }
        var action = form.querySelector('input[name="action"]');
        if (!action || (action.value !== 'faluss_identity_request_code' && action.value !== 'faluss_identity_verify_code')) { return; }
        event.preventDefault();
        var body = new FormData(form);
        body.set('action', action.value + '_ajax');
        setNotice(component, '');
        fetch(falussIdentityLogin.url, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result || !result.success) {
                    setNotice(component, result && result.data ? result.data.notice : 'Nous ne pouvons pas poursuivre cette vérification.');
                    return;
                }
                if (action.value === 'faluss_identity_verify_code') {
                    window.location.assign(result.data.redirect);
                    return;
                }
                setNotice(component, result.data.notice);
                replaceWithOtp(form, result.data.otp_html);
                var otp = component.querySelector('input[name="otp"]');
                if (otp) { otp.focus(); }
            })
            .catch(function () { setNotice(component, 'Nous ne pouvons pas poursuivre cette vérification.'); });
    });
}());

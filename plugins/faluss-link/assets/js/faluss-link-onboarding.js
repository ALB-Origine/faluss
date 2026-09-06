(function () {
    'use strict';

    var initialized = new WeakSet();
    var order = ['name', 'avatar', 'header', 'style', 'socials', 'links', 'finish'];

    function config() { return window.falussLinkOnboarding || {}; }
    function request(root, action, form, extra) {
        var data = new FormData(form);
        data.set('action', action);
        data.set('nonce', (extra && extra.avatar) ? (config().avatarNonce || '') : (config().nonce || ''));
        Object.keys(extra || {}).forEach(function (key) { if (key !== 'avatar') { data.set(key, extra[key]); } });
        return fetch(config().url || '', { method: 'POST', credentials: 'same-origin', body: data }).then(function (response) {
            return response.json().catch(function () { return { success: false }; });
        });
    }
    function notice(root, message, error) {
        var target = root.querySelector('.faluss-link-onboarding__notice');
        if (!target) { return; }
        target.textContent = message || '';
        target.hidden = !message;
        target.dataset.state = error ? 'error' : 'success';
    }
    function stepName(value) { return order.indexOf(value) !== -1 ? value : 'name'; }
    function setPending(root, value) {
        root.querySelectorAll('[data-onboarding-next],[data-onboarding-back],[data-onboarding-skip],[data-onboarding-finish]').forEach(function (button) {
            button.disabled = value;
            button.setAttribute('aria-busy', value ? 'true' : 'false');
        });
    }
    function show(root, value, focus) {
        var current = stepName(value), index = order.indexOf(current);
        root.dataset.currentStep = current;
        root.querySelectorAll('[data-onboarding-panel]').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-onboarding-panel') !== current;
        });
        root.querySelectorAll('[data-onboarding-progress]').forEach(function (item) {
            var itemIndex = order.indexOf(item.getAttribute('data-onboarding-progress'));
            item.classList.toggle('is-complete', itemIndex < index);
            item.classList.toggle('is-current', itemIndex === index);
            if (itemIndex === index) { item.setAttribute('aria-current', 'step'); } else { item.removeAttribute('aria-current'); }
        });
        var back = root.querySelector('[data-onboarding-back]'), skip = root.querySelector('[data-onboarding-skip]'), next = root.querySelector('[data-onboarding-next]'), finish = root.querySelector('[data-onboarding-finish]');
        if (back) { back.hidden = index === 0; }
        if (skip) { skip.hidden = order[index] !== 'avatar' && order[index] !== 'socials' && order[index] !== 'links'; }
        if (next) { next.hidden = current === 'finish'; }
        if (finish) { finish.hidden = current !== 'finish'; }
        if (current === 'links') { socialInputs(root); }
        if (focus) {
            var heading = root.querySelector('[data-onboarding-panel="' + current + '"] h2');
            if (heading) { heading.setAttribute('tabindex', '-1'); heading.focus(); }
        }
    }
    function preview(root) {
        var card = root.querySelector('[data-onboarding-preview] .faluss-link-card');
        if (!card) { return; }
        var name = root.querySelector('[name="display_name"]'), background = root.querySelector('[name="page_background"]:checked,[name="page_background"]');
        var treatment = root.querySelector('[name="name_treatment"]'), avatarBorder = root.querySelector('[name="avatar_border"]:checked'), linkStyle = root.querySelector('[name="link_style"]:checked');
        var nameTarget = card.querySelector('.faluss-link-card__name');
        if (nameTarget && name && name.value.trim()) { nameTarget.textContent = name.value.trim(); }
        if (background && /^#[0-9a-f]{6}$/i.test(background.value || '')) { card.style.setProperty('--fl-page-background', background.value); }
        if (treatment) {
            nameTarget.classList.remove('faluss-link-card__name--strong', 'faluss-link-card__name--editorial');
            nameTarget.classList.add('faluss-link-card__name--' + treatment.value);
        }
        card.classList.toggle('faluss-link-card--avatar-border-yes', !!(avatarBorder && avatarBorder.value === '1'));
        card.classList.toggle('faluss-link-card--avatar-border-no', !!(avatarBorder && avatarBorder.value !== '1'));
        if (linkStyle) {
            card.classList.remove('faluss-link-card--links-solid', 'faluss-link-card--links-outline', 'faluss-link-card--links-light');
            card.classList.add('faluss-link-card--links-' + linkStyle.value);
        }
    }
    function replacePreview(root, markup) {
        var target = root.querySelector('[data-onboarding-preview]');
        if (!target || !markup) { return; }
        target.querySelectorAll('.faluss-link-card').forEach(function (card) { card.remove(); });
        target.insertAdjacentHTML('beforeend', markup);
    }
    function selectedNetworks(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[name="social_selected[]"]:checked')).map(function (input) { return input.value; });
    }
    function socialInputs(root) {
        var wrap = root.querySelector('[data-onboarding-social-urls]');
        if (!wrap) { return; }
        var selected = selectedNetworks(root), retained = {};
        wrap.querySelectorAll('[data-onboarding-social-url]').forEach(function (row) { retained[row.getAttribute('data-onboarding-social-url')] = row.querySelector('input') ? row.querySelector('input').value : ''; });
        wrap.replaceChildren();
        selected.forEach(function (network) {
            var label = document.createElement('label'), input = document.createElement('input');
            label.dataset.onboardingSocialUrl = network;
            label.append(document.createTextNode(network.charAt(0).toUpperCase() + network.slice(1)));
            input.name = 'social_urls[' + network + ']'; input.type = 'text'; input.maxLength = 2048; input.placeholder = '@identifiant ou https://'; input.value = retained[network] || '';
            label.append(input); wrap.append(label);
        });
    }
    function linkRow() {
        var row = document.createElement('div'), id = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (value) { var random = Math.random() * 16 | 0; return (value === 'x' ? random : (random & 3 | 8)).toString(16); });
        row.className = 'faluss-link-onboarding__free-link';
        row.innerHTML = '<input name="wizard_links[][block_id]" type="hidden" value="' + id + '"><label>Libellé<input name="wizard_links[][label]" type="text" maxlength="80"></label><label>URL HTTPS<input name="wizard_links[][url]" type="url" maxlength="2048" placeholder="https://"></label><button type="button" class="faluss-link-onboarding__remove-link" aria-label="Supprimer ce lien">×</button>';
        return row;
    }
    function uploadAvatar(root, file) {
        if (!file || !/^image\//.test(file.type || '')) { notice(root, 'Choisissez une image valide.', true); return; }
        var form = root.querySelector('form'), data = new FormData(form);
        data.set('action', 'faluss_link_onboarding_upload_avatar'); data.set('nonce', config().avatarNonce || ''); data.set('avatar', file);
        setPending(root, true);
        fetch(config().url || '', { method: 'POST', credentials: 'same-origin', body: data }).then(function (response) { return response.json(); }).then(function (result) {
            if (!result || !result.success || !result.data || !result.data.id) { throw new Error('avatar'); }
            var input = form.querySelector('[name="faluss_identity_avatar_id"]'), previewTarget = root.querySelector('[data-onboarding-avatar-preview]'), cardAvatar = root.querySelector('[data-onboarding-preview] .faluss-link-card__avatar');
            if (input) { input.value = result.data.id; }
            if (previewTarget) { previewTarget.innerHTML = ''; var image = document.createElement('img'); image.src = result.data.url; image.alt = ''; previewTarget.append(image); }
            if (cardAvatar) { cardAvatar.hidden = false; cardAvatar.innerHTML = ''; var cardImage = document.createElement('img'); cardImage.src = result.data.url; cardImage.alt = ''; cardAvatar.append(cardImage); }
            notice(root, 'Photo ajoutée.', false);
        }).catch(function () { notice(root, 'L’image n’a pas pu être ajoutée.', true); }).finally(function () { setPending(root, false); });
    }
    function saveCurrent(root, next) {
        var current = stepName(root.dataset.currentStep), form = root.querySelector('form');
        if (current === 'name') {
            var name = form.querySelector('[name="display_name"]');
            if (!name || !name.value.trim()) { notice(root, 'Ajoutez un nom affiché.', true); if (name) { name.focus(); } return; }
        }
        setPending(root, true);
        request(root, 'faluss_link_onboarding_save', form, { step: current }).then(function (result) {
            if (!result || !result.success || !result.data) { throw new Error('save'); }
            replacePreview(root, result.data.preview || '');
            notice(root, 'Étape enregistrée.', false);
            show(root, next || String(result.data.step || '').replace(/^wizard_/, ''), true);
            preview(root);
        }).catch(function () { notice(root, 'Nous ne pouvons pas enregistrer cette étape. Vérifiez vos informations.', true); }).finally(function () { setPending(root, false); });
    }
    function init(root) {
        if (!(root instanceof HTMLElement) || initialized.has(root)) { return; }
        initialized.add(root); show(root, root.dataset.currentStep || 'name', false); preview(root);
        root.addEventListener('click', function (event) {
            var target = event.target.closest('button'); if (!target || !root.contains(target)) { return; }
            if (target.matches('[data-onboarding-next]')) { event.preventDefault(); saveCurrent(root); }
            if (target.matches('[data-onboarding-skip]')) { event.preventDefault(); saveCurrent(root); }
            if (target.matches('[data-onboarding-back]')) { event.preventDefault(); var index = Math.max(0, order.indexOf(stepName(root.dataset.currentStep)) - 1); show(root, order[index], true); }
            if (target.matches('[data-onboarding-add-link]')) { event.preventDefault(); var wrap = root.querySelector('[data-onboarding-free-links]'); if (wrap) { wrap.append(linkRow()); } }
            if (target.matches('.faluss-link-onboarding__remove-link')) { event.preventDefault(); target.closest('.faluss-link-onboarding__free-link').remove(); }
            if (target.matches('[data-onboarding-avatar-select]')) { event.preventDefault(); var input = document.createElement('input'); input.type = 'file'; input.accept = 'image/jpeg,image/png,image/webp,image/gif'; input.addEventListener('change', function () { uploadAvatar(root, input.files && input.files[0]); }); input.click(); }
            if (target.matches('[data-onboarding-finish]')) {
                event.preventDefault(); setPending(root, true);
                request(root, 'faluss_link_onboarding_finish', root.querySelector('form'), {}).then(function (result) {
                    if (!result || !result.success || !result.data || !result.data.redirect) { throw new Error('finish'); }
                    window.location.assign(result.data.redirect);
                }).catch(function () { notice(root, 'La publication n’a pas pu être terminée. Réessayez.', true); }).finally(function () { setPending(root, false); });
            }
        });
        root.addEventListener('change', function (event) { if (event.target && event.target.matches('[name="social_selected[]"]')) { socialInputs(root); } preview(root); });
        root.addEventListener('input', function () { preview(root); });
    }
    function boot(scope) { (scope || document).querySelectorAll('[data-faluss-link-onboarding]').forEach(init); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { boot(document); }, { once: true }); } else { boot(document); }
    window.addEventListener('elementor/frontend/init', function () { boot(document); });
    if (window.elementorFrontend && window.elementorFrontend.hooks) { window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_identity_onboarding.default', function (scope) { boot(scope && scope[0] ? scope[0] : document); }); }
}());

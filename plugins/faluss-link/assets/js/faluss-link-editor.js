(function ($) {
    'use strict';

    var icons = {
        instagram: '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>',
        tiktok: '<path d="M14 4v10.5a4 4 0 1 1-3-3.87"/><path d="M14 4c1 2.4 2.7 3.8 5 4"/>',
        youtube: '<path d="M21 12s0-4-1-5-7-1-8-1-7 0-8 1-1 5-1 5 0 4 1 5 7 1 8 1 7 0 8-1 1-5 1-5z"/>',
        x: '<path d="M5 4l14 16M19 4 5 20"/>',
        linkedin: '<path d="M6 9v9M6 6v.1M10 18v-5a3 3 0 0 1 6 0v5M10 12V9"/>',
        github: '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.9c0-1.1.1-1.8-.5-2.5 2-.2 4-1 4-4.5 0-1-.3-1.8-.8-2.5.1-.2.4-1.2-.1-2.5 0 0-.7-.2-2.5 1a8.6 8.6 0 0 0-4.5 0c-1.8-1.2-2.5-1-2.5-1-.5 1.3-.2 2.3-.1 2.5-.5.7-.8 1.5-.8 2.5 0 3.5 2 4.3 4 4.5-.4.4-.6 1-.6 2v3.9"/>'
    };

    function catalog() {
        var source = window.falussLinkCover && falussLinkCover.networks ? falussLinkCover.networks : {};
        if (Object.keys(source).length) {
            return Object.keys(source).filter(function (key) { return source[key].active; }).map(function (key) { return { key: key, label: source[key].label || key }; });
        }
        return Object.keys(icons).map(function (key) { return { key: key, label: key.charAt(0).toUpperCase() + key.slice(1) }; });
    }

    function card(studio) { return studio.find('.faluss-link-studio__preview .faluss-link-card'); }
    function svg(network) { return $('<svg>', { 'aria-hidden': 'true', viewBox: '0 0 24 24' }).html(icons[network] || ''); }
    function safeURL(value) { return /^https:\/\//i.test($.trim(value || '')); }

    function updateCursor(studio) {
        var tabs = studio.find('.faluss-link-studio__tabs');
        var active = tabs.find('[aria-selected="true"]');
        if (!active.length || !tabs.length) { return; }
        tabs[0].style.setProperty('--faluss-link-tab-left', active[0].offsetLeft + 'px');
        tabs[0].style.setProperty('--faluss-link-tab-width', active.outerWidth() + 'px');
    }

    function renumberRows(studio) {
        studio.find('.faluss-link-studio__link-row').each(function (index) {
            $(this).find('input').each(function () { this.name = this.name.replace(/links\[\d+\]/, 'links[' + index + ']'); });
        });
        studio.find('.faluss-link-studio__network-row').each(function (index) {
            $(this).find('select,input').each(function () { this.name = this.name.replace(/social_networks\[\d+\]/, 'social_networks[' + index + ']'); });
        });
    }

    function linkRow(index) {
        return $('<div>', { 'class': 'faluss-link-studio__link-row' })
            .append($('<input>', { type: 'hidden', name: 'links[' + index + '][position]', value: index }))
            .append($('<input>', { type: 'text', name: 'links[' + index + '][label]', maxlength: 80, placeholder: 'Libellé' }))
            .append($('<input>', { type: 'url', name: 'links[' + index + '][url]', maxlength: 2048, placeholder: 'https://' }))
            .append($('<button>', { type: 'button', 'class': 'faluss-link-studio__remove-row', text: 'Supprimer' }));
    }

    function networkRow(index) {
        var select = $('<select>', { name: 'social_networks[' + index + '][network]', 'aria-label': 'Réseau' });
        catalog().forEach(function (network) { select.append($('<option>', { value: network.key, text: network.label })); });
        return $('<div>', { 'class': 'faluss-link-studio__network-row' })
            .append(select)
            .append($('<input>', { type: 'url', name: 'social_networks[' + index + '][url]', maxlength: 2048, placeholder: 'https://', 'aria-label': 'URL HTTPS' }))
            .append($('<button>', { type: 'button', 'class': 'faluss-link-studio__remove-row', text: 'Supprimer' }));
    }

    function activate(studio, name, focus) {
        var next = studio.find('[data-fl-panel="' + name + '"]');
        var tabs = studio.find('[data-fl-tab]');
        if (!next.length) { return; }
        tabs.attr({ 'aria-selected': 'false', tabindex: '-1' });
        var tab = studio.find('[data-fl-tab="' + name + '"]').attr({ 'aria-selected': 'true', tabindex: '0' });
        studio.find('[data-fl-panel]').not(next).prop('hidden', true).removeClass('is-entering is-leaving');
        next.prop('hidden', false).addClass('is-entering');
        requestAnimationFrame(function () { next.removeClass('is-entering'); });
        updateCursor(studio);
        if (focus) { tab.trigger('focus'); }
        if (tab[0] && tab[0].scrollIntoView) { tab[0].scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
    }

    function updateSocials(studio) {
        var wrap = card(studio).find('.faluss-link-card__social');
        var layout = studio.find('[name="social_layout"]').val() || 'bubbles';
        wrap.empty().attr('class', 'faluss-link-card__social faluss-link-card__social--' + layout);
        studio.find('.faluss-link-studio__network-row').each(function () {
            var row = $(this), network = row.find('select').val(), url = row.find('input[type="url"]').val();
            if (!icons[network] || !safeURL(url)) { return; }
            wrap.append($('<a>', { href: url, target: '_blank', rel: 'noopener noreferrer nofollow', 'aria-label': row.find('option:selected').text() }).append(svg(network)));
        });
    }

    function updateLinks(studio) {
        var wrap = card(studio).find('.faluss-link-card__links').empty();
        studio.find('.faluss-link-studio__link-row').each(function () {
            var row = $(this), label = row.find('[name$="[label]"]').val(), url = row.find('[name$="[url]"]').val();
            if (!safeURL(url)) { return; }
            wrap.append($('<a>', { 'class': 'faluss-link-card__link', href: url, target: '_blank', rel: 'noopener noreferrer nofollow' }).text(label || url));
        });
    }

    function update(studio) {
        var preview = card(studio);
        if (!preview.length) { return; }
        var name = studio.find('[name="display_name"]').val() || 'Mon Faluss';
        var slug = studio.find('[name="public_slug"]').val();
        var mode = studio.find('[name="bio_mode"]').val() || 'editorial';
        var announcement = studio.find('[name="announcement"]').val() || '';
        var treatment = studio.find('[name="name_treatment"]').val() || 'strong';
        var style = studio.find('[name="link_style"]').val() || 'solid';
        var align = studio.find('[name="alignment"]').val() || 'left';
        var avatar = studio.find('[name="avatar_visible"]').prop('checked');
        preview.find('.faluss-link-card__name').text(name).attr('class', 'faluss-link-card__name faluss-link-card__name--' + treatment);
        preview.find('.faluss-link-card__handle').text(slug ? '@' + slug : '@—');
        preview.find('.faluss-link-card__bio').text(studio.find('[name="bio"]').val() || '').prop('hidden', mode === 'announcement');
        preview.find('.faluss-link-card__announcement').text(announcement).attr('class', 'faluss-link-card__announcement faluss-link-card__announcement--' + (studio.find('[name="announcement_variant"]').val() || 'accent')).prop('hidden', !(mode === 'announcement' && announcement));
        preview.find('.faluss-link-card__availability').prop('hidden', !studio.find('[name="available"]').prop('checked'));
        preview.find('.faluss-link-card__avatar').prop('hidden', !avatar);
        preview.removeClass('faluss-link-card--avatar-yes faluss-link-card--avatar-no faluss-link-card--links-solid faluss-link-card--links-outline faluss-link-card--align-left faluss-link-card--align-center')
            .addClass('faluss-link-card--avatar-' + (avatar ? 'yes' : 'no'))
            .addClass('faluss-link-card--links-' + style)
            .addClass('faluss-link-card--align-' + align);
        preview[0].style.setProperty('--fl-hero-transition-color', studio.find('[name="hero_transition_color"]').val() || '#FFFDF5');
        preview[0].style.setProperty('--fl-hero-transition-intensity', (studio.find('[name="hero_transition_intensity"]').val() || 82) + '%');
        preview[0].style.setProperty('--fl-hero-transition-position', (studio.find('[name="hero_transition_position"]').val() || 72) + '%');
        studio.find('[data-studio-member-name]').text(name);
        studio.find('[data-studio-member-handle]').text(slug ? '@' + slug : '@—');
        updateSocials(studio); updateLinks(studio);
    }

    function uploadCover(root, file, studio) {
        if (!file || !/^image\//.test(file.type || '') || !window.falussLinkCover) { return; }
        var data = new FormData();
        data.append('action', 'faluss_link_upload_cover'); data.append('nonce', falussLinkCover.nonce); data.append('cover', file);
        root.addClass('is-uploading');
        $.ajax({ url: falussLinkCover.url, type: 'POST', data: data, contentType: false, processData: false }).done(function (response) {
            if (!response || !response.success) { return; }
            root.find('.faluss-link-editor__cover-id').val(response.data.id);
            root.find('.faluss-link-editor__cover-preview').empty().append($('<img>', { src: response.data.url, alt: '' }));
            if (studio.length) {
                var preview = card(studio);
                preview.find('.faluss-link-card__cover').empty().append($('<img>', { src: response.data.url, alt: '' })).prop('hidden', false);
                preview.removeClass('faluss-link-card--cover-no').addClass('faluss-link-card--cover-yes');
            }
        }).always(function () { root.removeClass('is-uploading'); });
    }

    function initialize(root) {
        $(root).find('.faluss-link-studio').addBack('.faluss-link-studio').each(function () {
            var studio = $(this);
            if (studio.data('falussLinkStudioReady')) { updateCursor(studio); return; }
            studio.data('falussLinkStudioReady', true);
            studio.find('[data-fl-tab]').each(function (index) { $(this).attr('tabindex', index === 0 ? '0' : '-1'); });
            activate(studio, 'profile', false); update(studio);
        });
    }

    $(document)
        .off('.falussLink')
        .on('click.falussLink', '.faluss-link-editor__select-cover', function () {
            var root = $(this).closest('.faluss-link-editor__media'), input = $('<input>', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif' }), studio = $(this).closest('.faluss-link-studio');
            input.on('change', function () { uploadCover(root, this.files[0], studio); }).trigger('click');
        })
        .on('click.falussLink', '.faluss-link-editor__remove-cover', function () {
            var root = $(this).closest('.faluss-link-editor__media'), studio = $(this).closest('.faluss-link-studio');
            root.find('.faluss-link-editor__cover-id').val(''); root.find('.faluss-link-editor__cover-preview').empty();
            if (studio.length) { var preview = card(studio); preview.find('.faluss-link-card__cover').empty().prop('hidden', true); preview.removeClass('faluss-link-card--cover-yes').addClass('faluss-link-card--cover-no'); }
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-tab]', function () { activate($(this).closest('.faluss-link-studio'), $(this).data('fl-tab'), true); })
        .on('keydown.falussLink', '.faluss-link-studio [data-fl-tab]', function (event) {
            if (!/ArrowLeft|ArrowRight|Home|End/.test(event.key)) { return; }
            var tabs = $(this).closest('.faluss-link-studio').find('[data-fl-tab]'), index = tabs.index(this);
            if (event.key === 'ArrowRight') { index = (index + 1) % tabs.length; }
            if (event.key === 'ArrowLeft') { index = (index + tabs.length - 1) % tabs.length; }
            if (event.key === 'Home') { index = 0; } if (event.key === 'End') { index = tabs.length - 1; }
            event.preventDefault(); activate($(this).closest('.faluss-link-studio'), tabs.eq(index).data('fl-tab'), true);
        })
        .on('click.falussLink', '.faluss-link-studio__add-link', function () {
            var studio = $(this).closest('.faluss-link-studio'), list = $(this).siblings('.faluss-link-studio__link-list');
            if (list.find('.faluss-link-studio__link-row').length < 8) { list.append(linkRow(list.find('.faluss-link-studio__link-row').length)); update(studio); }
        })
        .on('click.falussLink', '.faluss-link-studio__add-network', function () {
            var studio = $(this).closest('.faluss-link-studio'), list = $(this).siblings('.faluss-link-studio__network-list');
            list.append(networkRow(list.find('.faluss-link-studio__network-row').length)); update(studio);
        })
        .on('click.falussLink', '.faluss-link-studio__remove-row', function () {
            var studio = $(this).closest('.faluss-link-studio'); $(this).closest('.faluss-link-studio__link-row,.faluss-link-studio__network-row').remove(); renumberRows(studio); update(studio);
        })
        .on('input.falussLink change.falussLink', '.faluss-link-studio input,.faluss-link-studio textarea,.faluss-link-studio select', function () { update($(this).closest('.faluss-link-studio')); })
        .on('change.falussLink', '.faluss-link-studio [name="faluss_identity_avatar"]', function () {
            var studio = $(this).closest('.faluss-link-studio'), file = this.files[0];
            if (!file || !/^image\//.test(file.type || '')) { return; }
            var reader = new FileReader(); reader.onload = function (event) { card(studio).find('.faluss-link-card__avatar').empty().append($('<img>', { src: event.target.result, alt: '' })); update(studio); }; reader.readAsDataURL(file);
        });

    $(function () {
        initialize(document);
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_link_studio.default', function (scope) { initialize(scope); });
        }
    });
    $(window).on('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_link_studio.default', function (scope) { initialize(scope); });
        }
    });
}(jQuery));

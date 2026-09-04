(function ($) {
    'use strict';
    var icons = {
        instagram: '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/>',
        tiktok: '<path d="M14 4v10.5a4 4 0 1 1-3-3.87"/><path d="M14 4c1 2.4 2.7 3.8 5 4"/>',
        youtube: '<path d="M21 12s0-4-1-5-7-1-8-1-7 0-8 1-1 5-1 5 0 4 1 5 7 1 8 1 7 0 8-1 1-5 1-5z"/>',
        x: '<path d="M5 4l14 16M19 4 5 20"/>',
        linkedin: '<path d="M6 9v9M6 6v.1M10 18v-5a3 3 0 0 1 6 0v5M10 12V9"/>',
        github: '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.9c0-1.1.1-1.8-.5-2.5 2-.2 4-1 4-4.5 0-1-.3-1.8-.8-2.5.1-.2.4-1.2-.1-2.5 0 0-.7-.2-2.5 1a8.6 8.6 0 0 0-4.5 0c-1.8-1.2-2.5-1-2.5-1-.5 1.3-.2 2.3-.1 2.5-.5.7-.8 1.5-.8 2.5 0 3.5 2 4.3 4 4.5-.4.4-.6 1-.6 2v3.9"/>'
    }, studioCount = 0;

    function card(studio) { return studio.find('.faluss-link-studio__preview .faluss-link-card'); }
    function svg(network) { return $('<svg>', { 'aria-hidden': 'true', viewBox: '0 0 24 24' }).html(icons[network] || ''); }
    function updateCursor(studio) {
        var tabs = studio.find('.faluss-link-studio__tabs'), active = tabs.find('[aria-selected="true"]');
        if (!active.length) { return; }
        tabs[0].style.setProperty('--faluss-link-tab-left', active[0].offsetLeft + 'px');
        tabs[0].style.setProperty('--faluss-link-tab-width', active.outerWidth() + 'px');
    }
    function ensureStudioMarkup(studio) {
        if (studio.data('falussLinkStudioReady')) { return; }
        studioCount += 1;
        var prefix = 'faluss-studio-' + studioCount, names = ['profile', 'links', 'style'];
        studio.find('[data-fl-tab]').each(function (index) {
            var tab = $(this), name = names[index];
            tab.attr({ id: prefix + '-tab-' + name, 'data-fl-tab': name, 'aria-controls': prefix + '-panel-' + name, tabindex: index === 0 ? '0' : '-1', 'aria-selected': index === 0 ? 'true' : 'false' });
            if (name === 'style') { tab.text('Style'); }
        });
        studio.find('[data-fl-panel]').each(function (index) {
            var panel = $(this), name = names[index];
            panel.attr({ id: prefix + '-panel-' + name, 'data-fl-panel': name, 'aria-labelledby': prefix + '-tab-' + name }).prop('hidden', index !== 0);
        });
        studio.data('falussLinkStudioReady', true);
        updateCursor(studio);
        update(studio);
    }
    function activate(studio, name, shouldFocus) {
        var tabs = studio.find('.faluss-link-studio__tabs'), tab = tabs.find('[data-fl-tab="' + name + '"]'), selected = studio.find('[data-fl-panel="' + name + '"]');
        if (!tab.length || !selected.length) { return; }
        studio.find('[data-fl-tab]').attr({ 'aria-selected': 'false', tabindex: '-1' });
        tab.attr({ 'aria-selected': 'true', tabindex: '0' });
        studio.find('[data-fl-panel]').not(selected).prop('hidden', true).removeClass('is-entering');
        selected.prop('hidden', false).addClass('is-entering');
        window.requestAnimationFrame(function () { selected.removeClass('is-entering'); });
        updateCursor(studio);
        if (tab[0].scrollIntoView) { tab[0].scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
        if (shouldFocus) { tab.trigger('focus'); }
    }
    function social(studio) {
        var wrap = card(studio).find('.faluss-link-card__social'), layout = studio.find('[name="social_layout"]').val() || 'bubbles';
        wrap.empty().attr('class', 'faluss-link-card__social faluss-link-card__social--' + layout);
        studio.find('[name="social_links"]').val().split(/\r?\n/).forEach(function (line) {
            var pair = line.split('|'), network = (pair[0] || '').trim().toLowerCase(), url = (pair[1] || '').trim();
            if (!icons[network] || !/^https:\/\//i.test(url)) { return; }
            wrap.append($('<a>', { href: url, target: '_blank',rel:'noopener noreferrer nofollow', 'aria-label': network }).append(svg(network)));
        });
    }
    function links(studio) {
        var wrap = card(studio).find('.faluss-link-card__links').empty();
        studio.find('.faluss-link-studio__link-row').each(function () {
            var row = $(this), label = row.find('[name$="[label]"]').val() || '', url = row.find('[name$="[url]"]').val() || '';
            if (!/^https:\/\//i.test(url)) { return; }
            wrap.append($('<a>', { 'class': 'faluss-link-card__link', href: url, target: '_blank',rel:'noopener noreferrer nofollow' }).text(label || url));
        });
    }
    function update(studio) {
        var preview = card(studio);
        if (!preview.length) { return; }
        var name = studio.find('[name="display_name"]').val() || 'Mon Faluss', slug = studio.find('[name="public_slug"]').val(), bio = studio.find('[name="bio"]').val(), announcement = studio.find('[name="announcement"]').val(), mode = studio.find('[name="bio_mode"]').val(), treatment = studio.find('[name="name_treatment"]').val() || 'strong', linkStyle = studio.find('[name="link_style"]').val() || 'solid', avatar = studio.find('[name="avatar_visible"]').prop('checked');
        preview.find('.faluss-link-card__name').text(name).attr('class', 'faluss-link-card__name faluss-link-card__name--' + treatment);
        preview.find('.faluss-link-card__handle').text(slug ? '@' + slug : '@—');
        preview.find('.faluss-link-card__bio').text(bio).prop('hidden', mode === 'announcement');
        preview.find('.faluss-link-card__announcement').text(announcement).attr('class', 'faluss-link-card__announcement faluss-link-card__announcement--' + (studio.find('[name="announcement_variant"]').val() || 'accent')).prop('hidden', !(mode === 'announcement' && announcement));
        preview.find('.faluss-link-card__availability').prop('hidden', !studio.find('[name="available"]').prop('checked'));
        preview.find('.faluss-link-card__avatar').prop('hidden', !avatar);
        preview.toggleClass('faluss-link-card--avatar-yes', avatar).toggleClass('faluss-link-card--avatar-no', !avatar).removeClass('faluss-link-card--links-solid faluss-link-card--links-outline').addClass('faluss-link-card--links-' + linkStyle);
        studio.find('[data-studio-member-name]').text(name);
        studio.find('[data-studio-member-handle]').text(slug ? '@' + slug : '@—');
        social(studio); links(studio);
    }
    function uploadCover(root, file, studio) {
        if (!file || !window.falussLinkCover) { return; }
        var data = new FormData();
        data.append('action', 'faluss_link_upload_cover'); data.append('nonce', falussLinkCover.nonce); data.append('cover', file);
        root.addClass('is-uploading');
        $.ajax({ url: falussLinkCover.url, type: 'POST', data: data, contentType: false, processData: false }).done(function (response) {
            if (!response.success) { return; }
            root.find('.faluss-link-editor__cover-id').val(response.data.id);
            root.find('.faluss-link-editor__cover-preview').empty().append($('<img>', { src: response.data.url, alt: '' }));
            if (studio.length) { var preview = card(studio); preview.find('.faluss-link-card__cover').empty().append($('<img>', { src: response.data.url, alt: '' })).prop('hidden', false); preview.removeClass('faluss-link-card--cover-no').addClass('faluss-link-card--cover-yes'); }
        }).always(function () { root.removeClass('is-uploading'); });
    }
    function bindEvents() {
        $(document).off('.falussLink')
            .on('click.falussLink', '.faluss-link-editor__select-cover', function () { var root = $(this).closest('.faluss-link-editor__media'), input = $('<input>', { type: 'file', accept: 'image/*' }), studio = $(this).closest('.faluss-link-studio'); input.on('change', function () { uploadCover(root, this.files[0], studio); }).trigger('click'); })
            .on('click.falussLink', '.faluss-link-editor__remove-cover', function () { var root = $(this).closest('.faluss-link-editor__media'), studio = $(this).closest('.faluss-link-studio'); root.find('.faluss-link-editor__cover-id').val(''); root.find('.faluss-link-editor__cover-preview').empty(); if (studio.length) { var preview = card(studio); preview.find('.faluss-link-card__cover').empty().prop('hidden', true); preview.removeClass('faluss-link-card--cover-yes').addClass('faluss-link-card--cover-no'); } })
            .on('click.falussLink', '.faluss-link-studio [data-fl-tab]', function () { activate($(this).closest('.faluss-link-studio'), $(this).attr('data-fl-tab'), true); })
            .on('keydown.falussLink', '.faluss-link-studio [data-fl-tab]', function (event) { if (!/ArrowLeft|ArrowRight|Home|End/.test(event.key)) { return; } var tabs = $(this).closest('.faluss-link-studio').find('[data-fl-tab]'), index = tabs.index(this); if (event.key === 'ArrowRight') { index = (index + 1) % tabs.length; } if (event.key === 'ArrowLeft') { index = (index + tabs.length - 1) % tabs.length; } if (event.key === 'Home') { index = 0; } if (event.key === 'End') { index = tabs.length - 1; } event.preventDefault(); activate($(this).closest('.faluss-link-studio'), tabs.eq(index).attr('data-fl-tab'), true); })
            .on('input.falussLink change.falussLink', '.faluss-link-studio input, .faluss-link-studio textarea, .faluss-link-studio select', function () { update($(this).closest('.faluss-link-studio')); })
            .on('change.falussLink', '.faluss-link-studio [name="faluss_identity_avatar"]', function () { var studio = $(this).closest('.faluss-link-studio'), file = this.files[0]; if (!file) { return; } var reader = new FileReader(); reader.onload = function (event) { card(studio).find('.faluss-link-card__avatar').empty().append($('<img>', { src: event.target.result, alt: '' })); update(studio); }; reader.readAsDataURL(file); });
    }
    function initialize(root) { $(root).find('.faluss-link-studio').addBack('.faluss-link-studio').each(function () { ensureStudioMarkup($(this)); }); }
    function attachElementor() { if (window.elementorFrontend && window.elementorFrontend.hooks) { window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_link_studio.default', function (scope) { initialize(scope); }); } }
    $(function () { bindEvents(); initialize(document); attachElementor(); });
    $(window).on('elementor/frontend/init', attachElementor);
}(jQuery));

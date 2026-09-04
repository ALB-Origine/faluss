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
        studio.find('.faluss-link-studio__network-row').each(function (index) {
            $(this).find('select,input').each(function () { this.name = this.name.replace(/social_networks\[\d+\]/, 'social_networks[' + index + ']'); });
        });
    }

    function blockId() {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (value) { var random = Math.random() * 16 | 0, bit = value === 'x' ? random : (random & 3 | 8); return bit.toString(16); });
    }

    function renumberBlocks(studio) {
        var blocks = studio.find('.faluss-link-content-block');
        blocks.each(function (index) {
            var block = $(this);
            block.find('[data-fl-block-field]').each(function () { this.name = 'content_blocks[' + index + '][' + $(this).data('fl-block-field') + ']'; });
            block.find('[data-fl-block-action="up"]').prop('disabled', index === 0);
            block.find('[data-fl-block-action="down"]').prop('disabled', index === blocks.length - 1);
        });
    }

    function contentBlock(type) {
        var titles = { section_title: 'Titre de section', text: 'Texte', link: 'Lien', media_teaser: 'Teaser média' }, block = $('<article>', { 'class': 'faluss-link-content-block', 'data-block-type': type }), header = $('<header>', { 'class': 'faluss-link-content-block__header' }), actions = $('<div>', { 'class': 'faluss-link-content-block__actions' });
        header.append($('<strong>', { text: titles[type] || 'Texte' })).append(actions
            .append($('<button>', { type: 'button', 'data-fl-block-action': 'up', 'aria-label': 'Monter cet élément', text: 'Monter' }))
            .append($('<button>', { type: 'button', 'data-fl-block-action': 'down', 'aria-label': 'Descendre cet élément', text: 'Descendre' }))
            .append($('<button>', { type: 'button', 'data-fl-block-action': 'remove', 'aria-label': 'Supprimer cet élément', text: 'Supprimer' })));
        block.append($('<input>', { type: 'hidden', 'data-fl-block-field': 'block_id', value: blockId() })).append($('<input>', { type: 'hidden', 'data-fl-block-field': 'type', value: type })).append(header);
        if (type === 'section_title') { block.append($('<label>', { text: 'Titre' }).append($('<input>', { type: 'text', maxlength: 80, 'data-fl-block-field': 'value' }))); }
        else if (type === 'link') { block.append($('<label>', { text: 'Libellé' }).append($('<input>', { type: 'text', maxlength: 80, 'data-fl-block-field': 'label' }))).append($('<label>', { text: 'URL HTTPS' }).append($('<input>', { type: 'url', maxlength: 2048, placeholder: 'https://', 'data-fl-block-field': 'url' }))); }
        else if (type === 'media_teaser') { block.append($('<div>', { 'class': 'faluss-link-content-block__media' }).append($('<input>', { type: 'hidden', 'data-fl-block-field': 'attachment_id' })).append($('<button>', { type: 'button', 'class': 'faluss-link-content-block__select-teaser', text: 'Choisir une image' })).append($('<button>', { type: 'button', 'class': 'faluss-link-content-block__remove-teaser', text: 'Retirer' })).append($('<div>', { 'class': 'faluss-link-content-block__media-preview' }))).append($('<label>', { text: 'Titre facultatif' }).append($('<input>', { type: 'text', maxlength: 80, 'data-fl-block-field': 'title' }))).append($('<label>', { text: 'Texte facultatif' }).append($('<textarea>', { maxlength: 240, rows: 3, 'data-fl-block-field': 'text' }))); }
        else { block.append($('<label>', { text: 'Texte' }).append($('<textarea>', { maxlength: 480, rows: 3, 'data-fl-block-field': 'value' }))); }
        return block;
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
        var preview = card(studio), wrap = preview.find('.faluss-link-card__content-blocks');
        if (!wrap.length) { wrap = $('<div>', { 'class': 'faluss-link-card__content-blocks faluss-link-card__links' }).insertAfter(preview.find('.faluss-link-card__social')); }
        wrap.empty();
        studio.find('.faluss-link-content-block').each(function () {
            var block = $(this), type = block.data('block-type'), value = block.find('[data-fl-block-field="value"]').val() || '', label = block.find('[data-fl-block-field="label"]').val() || '', url = block.find('[data-fl-block-field="url"]').val() || '';
            if (type === 'section_title' && $.trim(value)) { wrap.append($('<h3>', { 'class': 'faluss-link-card__section-title', text: value })); }
            else if (type === 'text' && $.trim(value)) { wrap.append($('<p>', { 'class': 'faluss-link-card__content-text', text: value })); }
            else if (type === 'link' && $.trim(label) && safeURL(url)) { wrap.append($('<a>', { 'class': 'faluss-link-card__link', href: url, target: '_blank', rel: 'noopener noreferrer nofollow', text: label })); }
            else if (type === 'media_teaser' && block.find('[data-fl-block-field="attachment_id"]').val()) {
                var image = block.find('.faluss-link-content-block__media-preview img').attr('src'), title = block.find('[data-fl-block-field="title"]').val() || '', text = block.find('[data-fl-block-field="text"]').val() || '';
                if (image) { var teaser = $('<section>', { 'class': 'faluss-link-card__media-teaser' }).append($('<img>', { src: image, alt: title })); if ($.trim(title) || $.trim(text)) { var copy = $('<div>', { 'class': 'faluss-link-card__media-teaser-copy' }); if ($.trim(title)) { copy.append($('<h3>', { text: title })); } if ($.trim(text)) { copy.append($('<p>', { text: text })); } teaser.append(copy); } wrap.append(teaser); }
            }
        });
        wrap.prop('hidden', wrap.children().length === 0);
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
        var nameColor = studio.find('[name="name_color"]:checked').val() || '#000000';
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
        preview[0].style.setProperty('--fl-page-background', studio.find('[name="page_background"]').val() || '#FFFDF5');
        preview[0].style.setProperty('--fl-name-color', nameColor);
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

    function uploadTeaser(block, file, studio) {
        if (!file || !/^image\//.test(file.type || '') || !window.falussLinkCover || !falussLinkCover.teaserNonce) { return; }
        var data = new FormData();
        data.append('action', 'faluss_link_upload_teaser'); data.append('nonce', falussLinkCover.teaserNonce); data.append('teaser', file);
        block.addClass('is-uploading');
        $.ajax({ url: falussLinkCover.url, type: 'POST', data: data, contentType: false, processData: false }).done(function (response) {
            if (!response || !response.success) { return; }
            block.find('[data-fl-block-field="attachment_id"]').val(response.data.id);
            block.find('.faluss-link-content-block__media-preview').empty().append($('<img>', { src: response.data.url, alt: '' }));
            update(studio);
        }).always(function () { block.removeClass('is-uploading'); });
    }

    function initialize(root) {
        $(root).find('.faluss-link-studio').addBack('.faluss-link-studio').each(function () {
            var studio = $(this);
            if (studio.data('falussLinkStudioReady')) { updateCursor(studio); return; }
            studio.data('falussLinkStudioReady', true);
            studio.find('[data-fl-tab]').each(function (index) { $(this).attr('tabindex', index === 0 ? '0' : '-1'); });
            renumberBlocks(studio); activate(studio, 'profile', false); update(studio);
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
        .on('click.falussLink', '.faluss-link-content-composer__add-button', function () {
            var studio = $(this).closest('.faluss-link-studio'), composer = $(this).closest('.faluss-link-content-composer'), list = composer.find('.faluss-link-content-composer__list'), type = composer.find('.faluss-link-content-composer__type').val();
            if (list.find('.faluss-link-content-block').length < 32 && /^(section_title|text|link|media_teaser)$/.test(type || '')) { list.append(contentBlock(type)); renumberBlocks(studio); update(studio); }
        })
        .on('click.falussLink', '.faluss-link-studio__add-network', function () {
            var studio = $(this).closest('.faluss-link-studio'), list = $(this).siblings('.faluss-link-studio__network-list');
            list.append(networkRow(list.find('.faluss-link-studio__network-row').length)); update(studio);
        })
        .on('click.falussLink', '.faluss-link-content-block [data-fl-block-action]', function () {
            var studio = $(this).closest('.faluss-link-studio'), block = $(this).closest('.faluss-link-content-block'), action = $(this).data('fl-block-action');
            if (action === 'up') { block.prev('.faluss-link-content-block').before(block); }
            else if (action === 'down') { block.next('.faluss-link-content-block').after(block); }
            else { block.remove(); }
            renumberBlocks(studio); update(studio);
        })
        .on('click.falussLink', '.faluss-link-content-block__select-teaser', function () {
            var block = $(this).closest('.faluss-link-content-block'), input = $('<input>', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif' }), studio = block.closest('.faluss-link-studio');
            input.on('change', function () { uploadTeaser(block, this.files[0], studio); }).trigger('click');
        })
        .on('click.falussLink', '.faluss-link-content-block__remove-teaser', function () {
            var block = $(this).closest('.faluss-link-content-block');
            block.find('[data-fl-block-field="attachment_id"]').val(''); block.find('.faluss-link-content-block__media-preview').empty(); update(block.closest('.faluss-link-studio'));
        })
        .on('click.falussLink', '.faluss-link-studio__remove-row', function () {
            var studio = $(this).closest('.faluss-link-studio'); $(this).closest('.faluss-link-studio__network-row').remove(); renumberRows(studio); update(studio);
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

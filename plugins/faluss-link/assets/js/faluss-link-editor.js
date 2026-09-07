(function ($) {
    'use strict';

    var teaserFormats = { landscape: 'Paysage', portrait: 'Portrait', square: 'Carré' };
    var teaserAccessModes = { public: 'Public', member: 'Membre Faluss', entitlement: 'Droit requis' };
    var styleFields = /^(page_background|hero_transition_color|name_color|alignment|social_variant|link_style)$/;

    function card(studio) { return studio.find('.faluss-link-studio__preview .faluss-link-card'); }
    function reducedMotion() { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
    function safeURL(value) {
        try {
            var url = new URL($.trim(value || ''));
            return url.protocol === 'https:' && !!url.hostname && !url.username && !url.password;
        } catch (error) { return false; }
    }

    function catalog() {
        var source = (window.falussLinkCover && falussLinkCover.networks) || {};
        return Object.keys(source).filter(function (key) {
            return source[key] && source[key].active;
        }).map(function (key) {
            return { key: key, label: source[key].label || key, outline: source[key].outline || null, full: source[key].full || null };
        });
    }
    function network(key) { return catalog().filter(function (item) { return item.key === key; })[0] || null; }
    function validAsset(asset) { return asset && typeof asset.src === 'string' && asset.src !== ''; }
    function networkAsset(item, variant) {
        if (!item) { return null; }
        var primary = variant === 'full' ? item.full : item.outline;
        var alternate = variant === 'full' ? item.outline : item.full;
        return validAsset(primary) ? primary : (validAsset(alternate) ? alternate : null);
    }
    function networkIcon(key, variant) {
        var item = network(key), asset = networkAsset(item, variant), image;
        if (!item || !asset) { return null; }
        image = $('<img>', { 'class': 'faluss-link-card__network-asset', src: asset.src, alt: item.label, loading: 'lazy' });
        if (asset.srcset) { image.attr('srcset', asset.srcset); }
        if (asset.sizes) { image.attr('sizes', asset.sizes); }
        return image;
    }

    function themes() { return (window.falussLinkCover && Array.isArray(falussLinkCover.themes)) ? falussLinkCover.themes : []; }
    function theme(slug) { return themes().filter(function (item) { return item && item.slug === slug; })[0] || null; }
    function themeLinkStyle(value) { return value === 'dark' ? 'solid' : (/^(solid|light|outline)$/.test(value || '') ? value : 'solid'); }
    function themeOverrides(studio) {
        var raw = studio.find('[name="theme_overrides"]').val() || '[]';
        try { raw = JSON.parse(raw); } catch (error) { raw = []; }
        return Array.isArray(raw) ? raw.filter(function (key, index, values) {
            return styleFields.test(key || '') && values.indexOf(key) === index;
        }) : [];
    }
    function setThemeOverrides(studio, overrides) { studio.find('[name="theme_overrides"]').val(JSON.stringify(overrides)); }
    function markThemeOverride(studio, name) {
        if (!studio.length || !styleFields.test(name || '')) { return; }
        var overrides = themeOverrides(studio);
        if (overrides.indexOf(name) === -1) { overrides.push(name); setThemeOverrides(studio, overrides); }
    }
    function applyTheme(studio, selected) {
        var preset = theme(selected);
        if (!preset || preset.locked || !studio.length) { return; }
        studio.data('falussLinkApplyingTheme', true);
        studio.find('[name="selected_theme"]').val(preset.slug);
        setThemeOverrides(studio, []);
        studio.find('[name="page_background"]').val(preset.page_background || '#FFFDF5');
        studio.find('[name="hero_transition_color"]').val(preset.hero_transition_color || '#FFFDF5');
        studio.find('[name="alignment"]').val(preset.alignment || 'left');
        studio.find('[name="social_variant"]').val(preset.social_variant || 'outline');
        studio.find('[name="link_style"]').val(themeLinkStyle(preset.link_style));
        studio.find('[name="name_color"][value="' + (preset.name_color || '#000000') + '"]').prop('checked', true);
        studio.find('.faluss-link-theme-picker__theme').attr('aria-pressed', 'false');
        studio.find('.faluss-link-theme-picker__theme[data-faluss-theme="' + preset.slug + '"]').attr('aria-pressed', 'true');
        studio.data('falussLinkApplyingTheme', false);
        update(studio);
    }

    function updateCursor(studio) {
        var tabs = studio.find('.faluss-link-studio__tabs'), active = tabs.find('[aria-selected="true"]');
        if (!active.length || !tabs.length) { return; }
        tabs[0].style.setProperty('--faluss-link-tab-left', active[0].offsetLeft + 'px');
        tabs[0].style.setProperty('--faluss-link-tab-width', active.outerWidth() + 'px');
    }
    function activeTab(studio) {
        var tab = studio.find('[data-fl-tab][aria-selected="true"]').data('fl-tab');
        return /^(profile|links|style)$/.test(tab || '') ? tab : 'profile';
    }
    function activate(studio, name, focus) {
        var next = studio.find('[data-fl-panel="' + name + '"]'), tabs = studio.find('[data-fl-tab]');
        if (!next.length) { return; }
        tabs.attr({ 'aria-selected': 'false', tabindex: '-1' });
        var tab = studio.find('[data-fl-tab="' + name + '"]').attr({ 'aria-selected': 'true', tabindex: '0' });
        studio.find('[data-fl-panel]').not(next).prop('hidden', true).removeClass('is-entering is-leaving');
        next.prop('hidden', false).addClass('is-entering');
        studio.find('[data-fl-active-tab]').val(name);
        studio.attr('data-faluss-studio-tab', name);
        requestAnimationFrame(function () { next.removeClass('is-entering'); });
        updateCursor(studio);
        if (focus) { tab.trigger('focus'); }
        if (tab[0] && tab[0].scrollIntoView) { tab[0].scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: reducedMotion() ? 'auto' : 'smooth' }); }
    }

    function renumberRows(studio) {
        studio.find('.faluss-link-studio__network-row').each(function (index) {
            $(this).find('select,input').each(function () {
                this.name = this.name.replace(/social_networks\[\d+\]/, 'social_networks[' + index + ']');
            });
        });
    }
    function blockId() {
        if (window.crypto && window.crypto.randomUUID) { return window.crypto.randomUUID(); }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (value) {
            var random = Math.random() * 16 | 0, bit = value === 'x' ? random : (random & 3 | 8);
            return bit.toString(16);
        });
    }
    function renumberBlocks(studio) {
        var blocks = studio.find('.faluss-link-content-block');
        blocks.each(function (index) {
            var block = $(this);
            block.find('[data-fl-block-field]').each(function () {
                this.name = 'content_blocks[' + index + '][' + $(this).data('fl-block-field') + ']';
            });
            block.find('[data-fl-block-action="up"]').prop('disabled', index === 0);
            block.find('[data-fl-block-action="down"]').prop('disabled', index === blocks.length - 1);
        });
    }
    function teaserFormatField(value) {
        var select = $('<select>', { 'data-fl-block-field': 'format', 'aria-label': 'Format de l’image' });
        Object.keys(teaserFormats).forEach(function (key) {
            select.append($('<option>', { value: key, text: teaserFormats[key], selected: (value || 'landscape') === key }));
        });
        return $('<label>', { text: 'Format image' }).append(select);
    }
    function teaserRights() {
        return (window.falussLinkCover && Array.isArray(falussLinkCover.teaserRights)) ? falussLinkCover.teaserRights.filter(function (right) {
            return right && typeof right.code === 'string' && right.code && typeof right.label === 'string' && right.label;
        }) : [];
    }
    function teaserAccessFields(mode, code) {
        var rights = teaserRights(), fieldset = $('<fieldset>', { 'class': 'faluss-link-content-block__access' });
        fieldset.append($('<legend>', { text: 'Accès au teaser' }));
        var access = $('<select>', { 'data-fl-block-field': 'access_mode', 'aria-label': 'Visibilité du teaser' });
        Object.keys(teaserAccessModes).forEach(function (key) {
            access.append($('<option>', { value: key, text: teaserAccessModes[key], selected: (mode || 'public') === key }));
        });
        fieldset.append($('<label>', { text: 'Visibilité' }).append(access));
        if (rights.length) {
            var entitlement = $('<select>', { 'data-fl-block-field': 'entitlement_code', 'aria-label': 'Droit requis', disabled: (mode || 'public') !== 'entitlement' });
            entitlement.append($('<option>', { value: '', text: 'Choisir un droit' }));
            rights.forEach(function (right) { entitlement.append($('<option>', { value: right.code, text: right.label, selected: code === right.code })); });
            fieldset.append($('<label>', { text: 'Droit requis' }).append(entitlement));
        } else {
            fieldset.append($('<p>', { 'class': 'faluss-link-content-block__access-hint', 'data-fl-entitlement-unavailable': '', text: 'Aucun droit lisible : vérifiez le Connector et la permission entitlements.read avant d’utiliser « Droit requis ».' }));
        }
        fieldset.append($('<p>', { 'class': 'faluss-link-content-block__access-state', 'data-fl-access-state': '', text: teaserAccessModes[mode] || teaserAccessModes.public }));
        return fieldset;
    }
    function syncTeaserAccess(block) {
        var mode = block.find('[data-fl-block-field="access_mode"]').val() || 'public';
        block.find('[data-fl-block-field="entitlement_code"]').prop('disabled', mode !== 'entitlement');
        block.find('[data-fl-access-state]').text(teaserAccessModes[mode] || teaserAccessModes.public);
    }
    function ensureTeaserFormats(studio) {
        studio.find('.faluss-link-content-block[data-block-type="media_teaser"]').each(function () {
            var block = $(this), source = block.next('.faluss-link-content-block__format-source'), format = source.val() || 'landscape';
            source.remove();
            if (!block.find('[data-fl-block-field="format"]').length) { block.find('.faluss-link-content-block__media').after(teaserFormatField(format)); }
            syncTeaserAccess(block);
        });
    }
    function contentBlock(type) {
        var titles = { section_title: 'Titre de section', text: 'Texte', link: 'Lien', media_teaser: 'Teaser média' }, id = blockId();
        var block = $('<article>', { 'class': 'faluss-link-content-block', 'data-block-id': id, 'data-block-type': type });
        var header = $('<header>', { 'class': 'faluss-link-content-block__header' });
        var actions = $('<div>', { 'class': 'faluss-link-content-block__actions' });
        actions.append($('<button>', { type: 'button', 'data-fl-block-action': 'up', 'aria-label': 'Monter cet élément', text: 'Monter' }));
        actions.append($('<button>', { type: 'button', 'data-fl-block-action': 'down', 'aria-label': 'Descendre cet élément', text: 'Descendre' }));
        actions.append($('<button>', { type: 'button', 'data-fl-block-action': 'remove', 'aria-label': 'Supprimer cet élément', text: 'Supprimer' }));
        header.append($('<strong>', { text: titles[type] || 'Texte' })).append(actions);
        block.append($('<input>', { type: 'hidden', 'data-fl-block-field': 'block_id', value: id }));
        block.append($('<input>', { type: 'hidden', 'data-fl-block-field': 'type', value: type })).append(header);
        if (type === 'section_title') {
            block.append($('<label>', { text: 'Titre' }).append($('<input>', { type: 'text', maxlength: 80, 'data-fl-block-field': 'value' })));
        } else if (type === 'link') {
            block.append($('<label>', { text: 'Libellé' }).append($('<input>', { type: 'text', maxlength: 80, 'data-fl-block-field': 'label' })));
            block.append($('<label>', { text: 'URL HTTPS' }).append($('<input>', { type: 'url', maxlength: 2048, placeholder: 'https://', 'data-fl-block-field': 'url' })));
        } else if (type === 'media_teaser') {
            block.append($('<div>', { 'class': 'faluss-link-content-block__media' })
                .append($('<input>', { type: 'hidden', 'data-fl-block-field': 'attachment_id' }))
                .append($('<button>', { type: 'button', 'class': 'faluss-link-content-block__select-teaser', text: 'Choisir une image' }))
                .append($('<button>', { type: 'button', 'class': 'faluss-link-content-block__remove-teaser', text: 'Retirer' }))
                .append($('<div>', { 'class': 'faluss-link-content-block__media-preview' })));
            block.append(teaserFormatField('landscape'));
            block.append($('<label>', { text: 'Titre facultatif' }).append($('<input>', { type: 'text', maxlength: 80, 'data-fl-block-field': 'title' })));
            block.append($('<label>', { text: 'Texte facultatif' }).append($('<textarea>', { maxlength: 240, rows: 3, 'data-fl-block-field': 'text' })));
            block.append(teaserAccessFields('public', ''));
        } else {
            block.append($('<label>', { text: 'Texte' }).append($('<textarea>', { maxlength: 480, rows: 3, 'data-fl-block-field': 'value' })));
        }
        return block;
    }
    function networkRow(index) {
        var select = $('<select>', { name: 'social_networks[' + index + '][network]', 'aria-label': 'Réseau' });
        catalog().forEach(function (item) { select.append($('<option>', { value: item.key, text: item.label })); });
        return $('<div>', { 'class': 'faluss-link-studio__network-row' })
            .append(select)
            .append($('<input>', { type: 'url', name: 'social_networks[' + index + '][url]', maxlength: 2048, placeholder: 'https://', 'aria-label': 'URL HTTPS' }))
            .append($('<button>', { type: 'button', 'class': 'faluss-link-studio__remove-row', text: 'Supprimer' }));
    }

    function blockData(block) {
        var data = { block_id: block.find('[data-fl-block-field="block_id"]').val() || '', type: block.data('block-type') || '' };
        block.find('[data-fl-block-field]').each(function () { data[$(this).data('fl-block-field')] = $(this).val() || ''; });
        data.image = block.find('.faluss-link-content-block__media-preview img').attr('src') || '';
        return data;
    }
    var blockRenderers = {
        section_title: function (data) { return $.trim(data.value) ? $('<h3>', { 'class': 'faluss-link-card__section-title', text: data.value }) : null; },
        text: function (data) { return $.trim(data.value) ? $('<p>', { 'class': 'faluss-link-card__content-text', text: data.value }) : null; },
        link: function (data) { return $.trim(data.label) && safeURL(data.url) ? $('<a>', { 'class': 'faluss-link-card__link', href: data.url, target: '_blank', rel: 'noopener noreferrer nofollow', text: data.label }) : null; },
        media_teaser: function (data) {
            if (!data.attachment_id || !data.image) { return null; }
            var hasCopy = $.trim(data.title) || $.trim(data.text);
            var format = teaserFormats[data.format] ? data.format : 'landscape';
            var teaser = $('<section>', { 'class': 'faluss-link-card__media-teaser faluss-link-card__media-teaser--format-' + format + (hasCopy ? '' : ' faluss-link-card__media-teaser--image-only') });
            teaser.append($('<img>', { src: data.image, alt: data.title || '' }));
            if (hasCopy) {
                var copy = $('<div>', { 'class': 'faluss-link-card__media-teaser-copy' });
                if ($.trim(data.title)) { copy.append($('<h3>', { text: data.title })); }
                if ($.trim(data.text)) { copy.append($('<p>', { text: data.text })); }
                teaser.append(copy);
            }
            return teaser;
        }
    };
    function updateLinks(studio) {
        var preview = card(studio), wrap = preview.find('.faluss-link-card__content-blocks');
        if (!wrap.length) { wrap = $('<div>', { 'class': 'faluss-link-card__content-blocks faluss-link-card__links' }).insertAfter(preview.find('.faluss-link-card__social')); }
        wrap.empty();
        studio.find('.faluss-link-content-block').each(function () {
            var data = blockData($(this)), renderer = blockRenderers[data.type], rendered = renderer ? renderer(data) : null;
            if (rendered) { wrap.append(rendered); }
        });
        wrap.prop('hidden', wrap.children().length === 0);
    }
    function updateSocials(studio) {
        var wrap = card(studio).find('.faluss-link-card__social');
        var layout = studio.find('[name="social_layout"]').val() || 'bubbles';
        var variant = studio.find('[name="social_variant"]').val() || 'outline';
        var rendered = 0;
        wrap.empty().attr('class', 'faluss-link-card__social faluss-link-card__social--' + layout + ' faluss-link-card__social--variant-' + variant).attr('data-faluss-social-variant', variant);
        studio.find('.faluss-link-studio__network-row').each(function () {
            var row = $(this), key = row.find('select').val(), url = row.find('input[type="url"]').val(), item = network(key), image = networkIcon(key, variant);
            if (!item || !image || !safeURL(url)) { return; }
            rendered += 1;
            wrap.append($('<a>', { href: url, target: '_blank', rel: 'noopener noreferrer nofollow', 'aria-label': item.label, 'data-faluss-network': key }).append(image));
        });
        wrap.prop('hidden', rendered === 0);
    }

    function formState(studio) {
        var state = {};
        studio.find('.faluss-link-studio__form').find('input,select,textarea').each(function () {
            var field = $(this), name = field.attr('name'), type = (field.attr('type') || '').toLowerCase();
            if (!name || /^(?:faluss_link_studio_nonce|_wp_http_referer|faluss_studio_tab)$/.test(name)) { return; }
            if (type === 'radio' && !field.prop('checked')) { return; }
            if (type === 'checkbox' && !field.prop('checked')) { state[name] = ''; return; }
            if (type === 'file') {
                var file = this.files && this.files[0];
                state[name] = file ? [file.name, file.size, file.lastModified].join(':') : '';
                return;
            }
            state[name] = field.val() || '';
        });
        return state;
    }
    function dirtyCount(studio) {
        var initial = studio.data('falussLinkInitialState') || {}, current = formState(studio), keys = {};
        Object.keys(initial).forEach(function (key) { keys[key] = true; });
        Object.keys(current).forEach(function (key) { keys[key] = true; });
        return Object.keys(keys).filter(function (key) { return initial[key] !== current[key]; }).length;
    }
    function updateDirty(studio) {
        var count = dirtyCount(studio), badge = studio.find('[data-fl-dirty-count]');
        badge.text(count).prop('hidden', count === 0).attr('aria-label', count ? count + ' modification' + (count > 1 ? 's' : '') + ' non enregistrée' + (count > 1 ? 's' : '') : '');
        studio.find('[data-fl-preview-toggle]').toggleClass('has-changes', count > 0);
    }
    function updateColorFields(studio) {
        studio.find('[data-fl-color-value]').each(function () {
            var output = $(this), input = studio.find('[name="' + output.data('fl-color-value') + '"]'), value = input.val() || '';
            output.text(String(value).toUpperCase());
        });
    }
    function updatePublication(studio) {
        var input = studio.find('[name="publication_status"]'), published = input.prop('checked');
        input.attr('aria-checked', published ? 'true' : 'false');
        studio.find('[data-fl-publication-state]').text(published ? 'Visible' : 'Masqué');
    }
    function update(studio) {
        var preview = card(studio);
        if (!preview.length) { return; }
        var name = studio.find('[name="display_name"]').val() || 'Mon Faluss';
        var slug = studio.find('[name="public_slug"]').val();
        var mode = studio.find('[name="bio_mode"]').val() || 'editorial';
        var announcement = studio.find('[name="announcement"]').val() || '';
        var treatment = studio.find('[name="name_treatment"]').val() || 'strong';
        var style = themeLinkStyle(studio.find('[name="link_style"]').val() || 'solid');
        var align = studio.find('[name="alignment"]').val() || 'left';
        var avatar = studio.find('[name="avatar_visible"]').prop('checked');
        var nameColor = studio.find('[name="name_color"]:checked').val() || '#000000';
        var selectedTheme = studio.find('[name="selected_theme"]').val() || 'faluss-default';
        preview.find('.faluss-link-card__name').text(name).attr('class', 'faluss-link-card__name faluss-link-card__name--' + treatment);
        var treatmentOption = studio.find('[name="name_treatment"] option:selected');
        preview[0].style.setProperty('--fl-name-weight', treatmentOption.data('name-weight') || 800);
        preview[0].style.setProperty('--fl-name-tracking', treatmentOption.data('name-tracking') || '-.045em');
        preview.find('.faluss-link-card__handle').text(slug ? '@' + slug : '@—');
        preview.find('.faluss-link-card__bio').text(studio.find('[name="bio"]').val() || '').prop('hidden', mode === 'announcement');
        preview.find('.faluss-link-card__announcement').text(announcement).attr('class', 'faluss-link-card__announcement faluss-link-card__announcement--' + (studio.find('[name="announcement_variant"]').val() || 'accent')).prop('hidden', !(mode === 'announcement' && announcement));
        preview.find('.faluss-link-card__availability').prop('hidden', !studio.find('[name="available"]').prop('checked'));
        preview.find('.faluss-link-card__avatar').prop('hidden', !avatar);
        preview.removeClass('faluss-link-card--avatar-yes faluss-link-card--avatar-no faluss-link-card--links-solid faluss-link-card--links-light faluss-link-card--links-outline faluss-link-card--align-left faluss-link-card--align-center')
            .addClass('faluss-link-card--avatar-' + (avatar ? 'yes' : 'no'))
            .addClass('faluss-link-card--links-' + style)
            .addClass('faluss-link-card--align-' + align)
            .attr('data-faluss-card-theme', selectedTheme);
        preview[0].style.setProperty('--fl-page-background', studio.find('[name="page_background"]').val() || '#FFFDF5');
        preview[0].style.setProperty('--fl-name-color', nameColor);
        preview[0].style.setProperty('--fl-hero-transition-color', studio.find('[name="hero_transition_color"]').val() || '#FFFDF5');
        preview[0].style.setProperty('--fl-hero-transition-intensity', (studio.find('[name="hero_transition_intensity"]').val() || 82) + '%');
        preview[0].style.setProperty('--fl-hero-transition-position', (studio.find('[name="hero_transition_position"]').val() || 72) + '%');
        studio.find('[data-studio-member-name]').text(name);
        studio.find('[data-studio-member-handle]').text(slug ? '@' + slug : '@—');
        updateSocials(studio);
        updateLinks(studio);
        updateColorFields(studio);
        updatePublication(studio);
        updateDirty(studio);
        if (window.FalussLinkCard) { window.FalussLinkCard.refresh(preview[0]); }
    }

    function uploadCover(root, file, studio) {
        if (!file || !/^image\//.test(file.type || '') || !window.falussLinkCover) { return; }
        var data = new FormData();
        data.append('action', 'faluss_link_upload_cover');
        data.append('nonce', falussLinkCover.nonce);
        data.append('cover', file);
        root.addClass('is-uploading');
        $.ajax({ url: falussLinkCover.url, type: 'POST', data: data, contentType: false, processData: false }).done(function (response) {
            if (!response || !response.success) { return; }
            root.find('.faluss-link-editor__cover-id').val(response.data.id);
            root.find('.faluss-link-editor__cover-preview').empty().append($('<img>', { src: response.data.url, alt: '' }));
            if (studio.length) {
                var preview = card(studio);
                preview.find('.faluss-link-card__cover').empty().append($('<img>', { src: response.data.url, alt: '' })).prop('hidden', false);
                preview.removeClass('faluss-link-card--cover-no').addClass('faluss-link-card--cover-yes');
                update(studio);
            }
        }).always(function () { root.removeClass('is-uploading'); });
    }
    function uploadTeaser(block, file, studio) {
        if (!file || !/^image\//.test(file.type || '') || !window.falussLinkCover || !falussLinkCover.teaserNonce) { return; }
        var data = new FormData();
        data.append('action', 'faluss_link_upload_teaser');
        data.append('nonce', falussLinkCover.teaserNonce);
        data.append('teaser', file);
        block.addClass('is-uploading');
        $.ajax({ url: falussLinkCover.url, type: 'POST', data: data, contentType: false, processData: false }).done(function (response) {
            if (!response || !response.success) { return; }
            block.find('[data-fl-block-field="attachment_id"]').val(response.data.id);
            block.find('.faluss-link-content-block__media-preview').empty().append($('<img>', { src: response.data.url, alt: '' }));
            update(studio);
        }).always(function () { block.removeClass('is-uploading'); });
    }
    function togglePreview(studio) {
        var preview = studio.find('[data-fl-preview]'), button = studio.find('[data-fl-preview-toggle]');
        if (!preview.length) { return; }
        var opening = preview.prop('hidden');
        preview.prop('hidden', !opening);
        button.attr('aria-expanded', opening ? 'true' : 'false');
        if (opening) {
            requestAnimationFrame(function () {
                if (preview[0].scrollIntoView) { preview[0].scrollIntoView({ block: 'start', behavior: reducedMotion() ? 'auto' : 'smooth' }); }
                try { preview[0].focus({ preventScroll: true }); } catch (error) { preview.trigger('focus'); }
            });
        }
    }
    function initialize(root) {
        $(root).find('.faluss-link-studio').addBack('.faluss-link-studio').each(function () {
            var studio = $(this);
            if (studio.data('falussLinkStudioReady')) { updateCursor(studio); return; }
            studio.data('falussLinkStudioReady', true);
            ensureTeaserFormats(studio);
            renumberBlocks(studio);
            activate(studio, activeTab(studio), false);
            update(studio);
            studio.data('falussLinkInitialState', formState(studio));
            updateDirty(studio);
            if (/(?:^|[?&])faluss_studio_notice=/.test(window.location.search)) { window.scrollTo({ top: 0, behavior: 'auto' }); }
        });
    }

    $(document).off('.falussLink')
        .on('click.falussLink', '.faluss-link-editor__select-cover', function () {
            var root = $(this).closest('.faluss-link-editor__media');
            var input = $('<input>', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif' });
            var studio = $(this).closest('.faluss-link-studio');
            input.on('change', function () { uploadCover(root, this.files[0], studio); }).trigger('click');
        })
        .on('click.falussLink', '.faluss-link-editor__remove-cover', function () {
            var root = $(this).closest('.faluss-link-editor__media'), studio = $(this).closest('.faluss-link-studio');
            root.find('.faluss-link-editor__cover-id').val('');
            root.find('.faluss-link-editor__cover-preview').empty();
            if (studio.length) {
                var preview = card(studio);
                preview.find('.faluss-link-card__cover').empty().prop('hidden', true);
                preview.removeClass('faluss-link-card--cover-yes').addClass('faluss-link-card--cover-no');
                update(studio);
            }
        })
        .on('click.falussLink', '.faluss-link-studio__copy-id', function () {
            var button = $(this), value = button.data('faluss-id'), status = button.siblings('[aria-live]');
            if (!value) { return; }
            var copied = function () { status.text('Identifiant Faluss copié.'); };
            if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(value).then(copied, function () { status.text('Copie indisponible.'); }); }
            else { status.text('Copie indisponible.'); }
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-tab]', function () { activate($(this).closest('.faluss-link-studio'), $(this).data('fl-tab'), true); })
        .on('click.falussLink', '.faluss-link-theme-picker__theme', function () { applyTheme($(this).closest('.faluss-link-studio'), $(this).data('faluss-theme')); })
        .on('click.falussLink', '.faluss-link-studio [data-fl-preview-toggle]', function () { togglePreview($(this).closest('.faluss-link-studio')); })
        .on('keydown.falussLink', '.faluss-link-studio [data-fl-tab]', function (event) {
            if (!/ArrowLeft|ArrowRight|Home|End/.test(event.key)) { return; }
            var tabs = $(this).closest('.faluss-link-studio').find('[data-fl-tab]'), index = tabs.index(this);
            if (event.key === 'ArrowRight') { index = (index + 1) % tabs.length; }
            if (event.key === 'ArrowLeft') { index = (index + tabs.length - 1) % tabs.length; }
            if (event.key === 'Home') { index = 0; }
            if (event.key === 'End') { index = tabs.length - 1; }
            event.preventDefault();
            activate($(this).closest('.faluss-link-studio'), tabs.eq(index).data('fl-tab'), true);
        })
        .on('click.falussLink', '.faluss-link-content-composer__add-button', function () {
            var studio = $(this).closest('.faluss-link-studio'), composer = $(this).closest('.faluss-link-content-composer');
            var list = composer.find('.faluss-link-content-composer__list'), type = composer.find('.faluss-link-content-composer__type').val();
            if (list.find('.faluss-link-content-block').length < 32 && /^(section_title|text|link|media_teaser)$/.test(type || '')) {
                list.append(contentBlock(type)); renumberBlocks(studio); update(studio);
            }
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
            var block = $(this).closest('.faluss-link-content-block'), input = $('<input>', { type: 'file', accept: 'image/jpeg,image/png,image/webp,image/gif' });
            input.on('change', function () { uploadTeaser(block, this.files[0], block.closest('.faluss-link-studio')); }).trigger('click');
        })
        .on('click.falussLink', '.faluss-link-content-block__remove-teaser', function () {
            var block = $(this).closest('.faluss-link-content-block');
            block.find('[data-fl-block-field="attachment_id"]').val(''); block.find('.faluss-link-content-block__media-preview').empty(); update(block.closest('.faluss-link-studio'));
        })
        .on('change.falussLink', '.faluss-link-content-block [data-fl-block-field="access_mode"]', function () {
            var block = $(this).closest('.faluss-link-content-block');
            syncTeaserAccess(block); update(block.closest('.faluss-link-studio'));
        })
        .on('click.falussLink', '.faluss-link-studio__remove-row', function () {
            var studio = $(this).closest('.faluss-link-studio');
            $(this).closest('.faluss-link-studio__network-row').remove(); renumberRows(studio); update(studio);
        })
        .on('input.falussLink change.falussLink', '.faluss-link-studio input,.faluss-link-studio textarea,.faluss-link-studio select', function () {
            var studio = $(this).closest('.faluss-link-studio');
            if (!studio.data('falussLinkApplyingTheme')) { markThemeOverride(studio, $(this).attr('name')); }
            update(studio);
        })
        .on('change.falussLink', '.faluss-link-studio [name="faluss_identity_avatar"]', function () {
            var studio = $(this).closest('.faluss-link-studio'), file = this.files[0];
            if (!file || !/^image\//.test(file.type || '')) { return; }
            var reader = new FileReader();
            reader.onload = function (event) { card(studio).find('.faluss-link-card__avatar').empty().append($('<img>', { src: event.target.result, alt: '' })); update(studio); };
            reader.readAsDataURL(file);
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

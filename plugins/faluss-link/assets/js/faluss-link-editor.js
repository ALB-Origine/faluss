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
        var alignment = preset.alignment || 'left';
        var alignmentRadios = studio.find('[name="alignment"][type="radio"]');
        if (alignmentRadios.length) { alignmentRadios.prop('checked', false).filter('[value="' + alignment + '"]').prop('checked', true); }
        else { studio.find('[name="alignment"]').val(alignment); }
        studio.find('[name="social_variant"]').val(preset.social_variant || 'outline');
        var linkStyle = themeLinkStyle(preset.link_style);
        var linkStyleRadios = studio.find('[name="link_style"][type="radio"]');
        if (linkStyleRadios.length) { linkStyleRadios.prop('checked', false).filter('[value="' + linkStyle + '"]').prop('checked', true); }
        else { studio.find('[name="link_style"]').val(linkStyle); }
        studio.find('[name="name_color"][value="' + (preset.name_color || '#000000') + '"]').prop('checked', true);
        studio.find('.faluss-link-theme-picker__theme').attr('aria-pressed', 'false');
        studio.find('.faluss-link-theme-picker__theme[data-faluss-theme="' + preset.slug + '"]').attr('aria-pressed', 'true');
        studio.data('falussLinkApplyingTheme', false);
        update(studio);
    }

    function updateCursor(studio) {
        var tabs = studio.find('.faluss-link-studio__tabs'), active = tabs.find('[aria-selected="true"]');
        if (active.length && tabs.length) {
            tabs[0].style.setProperty('--faluss-link-tab-left', active[0].offsetLeft + 'px');
            tabs[0].style.setProperty('--faluss-link-tab-width', active.outerWidth() + 'px');
        }
        var dock = studio.find('.faluss-link-studio__dock-tabs'), dockActive = dock.find('[data-fl-tab][aria-pressed="true"]');
        if (dock.length && dockActive.length) {
            dock[0].style.setProperty('--fl-dock-left', dockActive[0].offsetLeft + 'px');
            dock[0].style.setProperty('--fl-dock-width', dockActive.outerWidth() + 'px');
        }
        studio.find('.faluss-link-studio__context-tabs:visible').each(function () {
            var nav = $(this), selected = nav.find('[data-fl-context-tab][aria-selected="true"]');
            if (selected.length) {
                nav[0].style.setProperty('--fl-context-left', selected[0].offsetLeft + 'px');
                nav[0].style.setProperty('--fl-context-width', selected.outerWidth() + 'px');
            }
        });
    }
    function activeTab(studio) {
        var tab = studio.find('[data-fl-tab][aria-pressed="true"], [data-fl-tab][aria-selected="true"]').first().data('fl-tab');
        return /^(profile|links|style)$/.test(tab || '') ? tab : 'links';
    }
    function activate(studio, name, focus) {
        if (studio.find('[data-fl-main-panel]').length) {
            name = name === 'profile' ? 'style' : name;
            if (!/^(links|style)$/.test(name || '')) { name = 'links'; }
            var main = studio.find('[data-fl-main-panel="' + name + '"]');
            studio.find('[data-fl-main-panel]').not(main).prop('hidden', true).attr('inert', '');
            main.prop('hidden', false).removeAttr('inert');
            studio.find('.faluss-link-studio__dock-tabs [data-fl-tab]').attr('aria-pressed', 'false');
            var dockTab = studio.find('.faluss-link-studio__dock-tabs [data-fl-tab="' + name + '"]').attr('aria-pressed', 'true');
            studio.find('[data-fl-active-tab]').val(name);
            studio.attr('data-faluss-studio-tab', name);
            var section = name === 'style' ? 'appearance' : 'all';
            var current = studio.find('[data-fl-active-section]').val();
            if ((name === 'style' && /^(appearance|header|link-style)$/.test(current || '')) || (name === 'links' && /^(all|collections|collection)$/.test(current || ''))) { section = current; }
            activateSection(studio, section, false);
            updateCursor(studio);
            if (focus) { dockTab.trigger('focus'); }
            return;
        }
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

    function activateSection(studio, name, focus) {
        var tab = activeTab(studio), allowed = tab === 'style' ? /^(appearance|header|link-style)$/ : /^(all|collections|collection)$/;
        if (!allowed.test(name || '')) { name = tab === 'style' ? 'appearance' : 'all'; }
        if (name === 'collection' && !studio.find('[data-fl-section-panel="collection"]').length) { name = 'collections'; }
        var main = studio.find('[data-fl-main-panel="' + tab + '"]'), panel = main.find('[data-fl-section-panel="' + name + '"]');
        main.find('[data-fl-section-panel]').not(panel).prop('hidden', true).attr('inert', '');
        panel.prop('hidden', false).removeAttr('inert').addClass('is-entering');
        requestAnimationFrame(function () { panel.removeClass('is-entering'); });
        var contextName = name === 'collection' ? 'collections' : name;
        main.find('[data-fl-context-tab]').attr({ 'aria-selected': 'false', tabindex: '-1' });
        var contextTab = main.find('[data-fl-context-tab="' + contextName + '"]').attr({ 'aria-selected': 'true', tabindex: '0' });
        studio.find('[data-fl-active-section]').val(name);
        studio.attr('data-faluss-studio-section', name);
        if (name !== 'collection') {
            studio.find('[data-fl-active-collection]').val('');
            studio.attr('data-faluss-studio-collection', '');
        }
        updateUrlState(studio);
        updateCreateAction(studio);
        updateCursor(studio);
        if (focus) { contextTab.trigger('focus'); }
    }

    function updateUrlState(studio) {
        if (!window.history || !window.history.replaceState) { return; }
        var url = new URL(window.location.href);
        url.searchParams.set('faluss_studio_tab', activeTab(studio));
        url.searchParams.set('faluss_studio_section', studio.find('[data-fl-active-section]').val() || 'all');
        var collection = studio.find('[data-fl-active-collection]').val() || '';
        if (collection) { url.searchParams.set('faluss_studio_collection', collection); }
        else { url.searchParams.delete('faluss_studio_collection'); }
        url.searchParams.delete('faluss_studio_notice');
        window.history.replaceState({}, '', url.toString());
    }

    function updateCreateAction(studio) {
        var create = studio.find('[data-fl-create]'), enabled = activeTab(studio) === 'links';
        create.prop('disabled', !enabled).attr('aria-disabled', enabled ? 'false' : 'true');
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
        var blocks = studio.find('[data-fl-block-store] [data-fl-stored-block]');
        if (!blocks.length) { blocks = studio.find('.faluss-link-content-block'); }
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
        var data = { block_id: block.find('[data-fl-block-field="block_id"]').val() || block.data('block-id') || '', type: block.data('block-type') || '' };
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
        var blocks = studio.find('[data-fl-block-store] [data-fl-stored-block]');
        if (!blocks.length) { blocks = studio.find('.faluss-link-content-block'); }
        blocks.each(function () {
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
        var nameFont = studio.find('[name="name_font"] option:selected');
        var style = themeLinkStyle(studio.find('[name="link_style"]:checked').val() || studio.find('select[name="link_style"]').val() || 'solid');
        var align = studio.find('[name="alignment"]:checked').val() || studio.find('select[name="alignment"]').val() || 'left';
        var avatarField = studio.find('[name="avatar_visible"][type="checkbox"]');
        var avatar = avatarField.length ? avatarField.prop('checked') : true;
        var avatarBorderField = studio.find('[name="avatar_border"][type="checkbox"]');
        var avatarBorder = avatarBorderField.length ? avatarBorderField.prop('checked') : true;
        var nameColor = studio.find('[name="name_color"]:checked').val() || '#000000';
        var selectedTheme = studio.find('[name="selected_theme"]').val() || 'faluss-default';
        preview.find('.faluss-link-card__name').text(name).attr('class', 'faluss-link-card__name faluss-link-card__name--' + treatment);
        var treatmentOption = studio.find('[name="name_treatment"] option:selected');
        preview[0].style.setProperty('--fl-name-weight', treatmentOption.data('name-weight') || 800);
        preview[0].style.setProperty('--fl-name-tracking', treatmentOption.data('name-tracking') || '-.045em');
        preview[0].style.setProperty('--fl-name-font', nameFont.data('font-stack') || 'Outfit, ui-sans-serif, system-ui, sans-serif');
        preview.find('.faluss-link-card__handle').text(slug ? '@' + slug : '@—');
        preview.find('.faluss-link-card__bio').text(studio.find('[name="bio"]').val() || '').prop('hidden', mode === 'announcement');
        preview.find('.faluss-link-card__announcement').text(announcement).attr('class', 'faluss-link-card__announcement faluss-link-card__announcement--' + (studio.find('[name="announcement_variant"]').val() || 'accent')).prop('hidden', !(mode === 'announcement' && announcement));
        preview.find('.faluss-link-card__availability').prop('hidden', !studio.find('[name="available"][type="checkbox"]').prop('checked'));
        preview.find('.faluss-link-card__avatar').prop('hidden', !avatar);
        preview.removeClass('faluss-link-card--avatar-yes faluss-link-card--avatar-no faluss-link-card--avatar-border-yes faluss-link-card--avatar-border-no faluss-link-card--links-solid faluss-link-card--links-light faluss-link-card--links-outline faluss-link-card--align-left faluss-link-card--align-center')
            .addClass('faluss-link-card--avatar-' + (avatar ? 'yes' : 'no'))
            .addClass('faluss-link-card--avatar-border-' + (avatarBorder ? 'yes' : 'no'))
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

    function storedBlocks(studio) { return studio.find('[data-fl-block-store] [data-fl-stored-block]'); }
    function storedBlock(studio, id) { return storedBlocks(studio).filter('[data-block-id="' + id + '"]').first(); }
    function storedField(block, name) { return block.find('[data-fl-block-field="' + name + '"]'); }
    function setStoredField(block, name, value) {
        var field = storedField(block, name);
        if (!field.length) { field = $('<input>', { type: 'hidden', 'data-fl-block-field': name }).appendTo(block); }
        field.val(value || '');
    }
    function makeStoredBlock(type, values) {
        var id = (values && values.block_id) || blockId();
        var block = $('<div>', { 'data-fl-stored-block': '', 'data-block-id': id, 'data-block-type': type });
        setStoredField(block, 'block_id', id);
        setStoredField(block, 'type', type);
        Object.keys(values || {}).forEach(function (key) { if (key !== 'block_id' && key !== 'type') { setStoredField(block, key, values[key]); } });
        return block;
    }
    function insertStoredLink(studio, values, collectionId) {
        var store = studio.find('[data-fl-block-store]'), block = makeStoredBlock('link', values);
        if (collectionId) {
            var collection = storedBlock(studio, collectionId), next = collection.next();
            while (next.length && next.data('block-type') !== 'section_title') { collection = next; next = next.next(); }
            block.insertAfter(collection);
        } else {
            var firstCollection = store.children('[data-block-type="section_title"]').first();
            if (firstCollection.length) { block.insertBefore(firstCollection); } else { store.append(block); }
        }
        renumberBlocks(studio);
        update(studio);
        return block;
    }
    function insertStoredCollection(studio, name, description) {
        var store = studio.find('[data-fl-block-store]'), collection = makeStoredBlock('section_title', { value: name });
        store.append(collection);
        if ($.trim(description)) { store.append(makeStoredBlock('text', { value: description })); }
        renumberBlocks(studio);
        update(studio);
        return storedField(collection, 'block_id').val();
    }
    function collectionDescriptionBlock(collection) {
        var next = collection.next();
        return next.length && next.data('block-type') === 'text' ? next : $();
    }
    function syncLinkCard(studio, linkCard) {
        var block = storedBlock(studio, linkCard.data('block-id'));
        if (!block.length) { return false; }
        var label = $.trim(linkCard.find('[data-fl-link-label]').val() || '');
        var url = $.trim(linkCard.find('[data-fl-link-url]').val() || '');
        if (!label || !safeURL(url)) { showStatus(studio, 'Renseignez un nom et une URL HTTPS valide.', true); return false; }
        setStoredField(block, 'label', label);
        setStoredField(block, 'url', url);
        linkCard.find('.faluss-link-studio__link-summary span').first().text(label);
        renumberBlocks(studio);
        update(studio);
        return true;
    }
    function showStatus(studio, message, error) {
        var notice = studio.find('.faluss-link-studio__notice');
        notice.empty().append($('<p>', { 'class': 'faluss-link-notice' + (error ? ' is-error' : ''), text: message }));
    }
    function saveStudio(studio, reload) {
        var form = studio.find('.faluss-link-studio__form');
        if (!form.length || studio.data('falussLinkSaving')) { return Promise.resolve(false); }
        studio.data('falussLinkSaving', true).addClass('is-saving');
        showStatus(studio, 'Enregistrement…', false);
        return window.fetch(form.attr('action'), { method: 'POST', body: new FormData(form[0]), credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) { return response.json().catch(function () { return null; }).then(function (body) { return { ok: response.ok, body: body }; }); })
            .then(function (result) {
                if (!result.ok || !result.body || !result.body.success) { throw new Error(result.body && result.body.data && result.body.data.message ? result.body.data.message : 'Enregistrement impossible.'); }
                showStatus(studio, result.body.data.message || 'Studio enregistré.', false);
                studio.data('falussLinkInitialState', formState(studio));
                updateDirty(studio);
                form.find('input[type="file"]').val('');
                if (reload) { window.location.reload(); }
                return true;
            })
            .catch(function (error) { showStatus(studio, error.message || 'Enregistrement impossible.', true); return false; })
            .finally(function () { studio.data('falussLinkSaving', false).removeClass('is-saving'); });
    }
    function queueStudioSave(studio) {
        window.clearTimeout(studio.data('falussLinkSaveTimer'));
        studio.data('falussLinkSaveTimer', window.setTimeout(function () { saveStudio(studio, false); }, 450));
    }
    function showScreen(studio, name) {
        studio.find('[data-fl-studio-screen]').each(function () {
            var screen = $(this), active = screen.data('fl-studio-screen') === name;
            screen.prop('hidden', !active);
            if (active) { screen.removeAttr('inert'); } else { screen.attr('inert', ''); }
        });
        studio.attr('data-faluss-studio-screen', name);
        requestAnimationFrame(function () {
            var target = studio.find('[data-fl-studio-screen="' + name + '"] h2, [data-fl-studio-screen="' + name + '"] input').first();
            if (target.length) { target.attr('tabindex', '-1').trigger('focus'); }
        });
    }
    function openCreate(studio) {
        if (activeTab(studio) !== 'links') { return; }
        var section = studio.find('[data-fl-active-section]').val() || 'all';
        showScreen(studio, section === 'collections' ? 'create-collection' : 'create-link');
    }
    function closeTransient(studio) {
        if (!studio.find('[data-fl-preview]').prop('hidden')) { togglePreview(studio, false); return true; }
        if ((studio.attr('data-faluss-studio-screen') || 'main') !== 'main') { showScreen(studio, 'main'); return true; }
        if (studio.find('[data-fl-active-section]').val() === 'collection') { activateSection(studio, 'collections', true); return true; }
        return false;
    }
    function togglePreview(studio) {
        var preview = studio.find('[data-fl-preview]'), button = studio.find('[data-fl-preview-toggle]');
        if (!preview.length) { return; }
        var requested = arguments.length > 1 ? arguments[1] : null;
        var opening = requested === null ? preview.prop('hidden') : !!requested;
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
            showScreen(studio, 'main');
            activate(studio, activeTab(studio), false);
            update(studio);
            studio.data('falussLinkInitialState', formState(studio));
            updateDirty(studio);
            updateCreateAction(studio);
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
        .on('click.falussLink', '.faluss-link-studio [data-fl-context-tab]', function () { activateSection($(this).closest('.faluss-link-studio'), $(this).data('fl-context-tab'), true); })
        .on('click.falussLink', '.faluss-link-theme-picker__theme', function () { var studio = $(this).closest('.faluss-link-studio'); applyTheme(studio, $(this).data('faluss-theme')); queueStudioSave(studio); })
        .on('click.falussLink', '.faluss-link-studio [data-fl-preview-toggle]', function () { togglePreview($(this).closest('.faluss-link-studio')); })
        .on('click.falussLink', '.faluss-link-studio [data-fl-preview-close]', function () { togglePreview($(this).closest('.faluss-link-studio'), false); })
        .on('click.falussLink', '.faluss-link-studio [data-fl-studio-back]', function () {
            var studio = $(this).closest('.faluss-link-studio');
            if (!closeTransient(studio)) { window.history.back(); }
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-create]', function () { openCreate($(this).closest('.faluss-link-studio')); })
        .on('submit.falussLink', '.faluss-link-studio__form', function (event) { event.preventDefault(); saveStudio($(this).closest('.faluss-link-studio'), false); })
        .on('click.falussLink', '.faluss-link-studio [data-fl-create-link-submit]', function () {
            var studio = $(this).closest('.faluss-link-studio'), screen = $(this).closest('[data-fl-studio-screen]');
            var label = $.trim(screen.find('[data-fl-new-link-label]').val() || ''), url = $.trim(screen.find('[data-fl-new-link-url]').val() || '');
            if (!label || !safeURL(url)) { showStatus(studio, 'Renseignez un nom et une URL HTTPS valide.', true); return; }
            if (storedBlocks(studio).length >= 32) { showStatus(studio, 'Votre carte contient déjà le nombre maximal d’éléments.', true); return; }
            insertStoredLink(studio, { label: label, url: url }, studio.find('[data-fl-active-collection]').val() || '');
            saveStudio(studio, true);
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-create-collection-submit]', function () {
            var studio = $(this).closest('.faluss-link-studio'), screen = $(this).closest('[data-fl-studio-screen]');
            var name = $.trim(screen.find('[data-fl-new-collection-name]').val() || ''), description = $.trim(screen.find('[data-fl-new-collection-description]').val() || '');
            if (!name) { showStatus(studio, 'Le nom de la collection est requis.', true); return; }
            if (storedBlocks(studio).length + (description ? 2 : 1) > 32) { showStatus(studio, 'Votre carte contient déjà le nombre maximal d’éléments.', true); return; }
            insertStoredCollection(studio, name, description);
            activateSection(studio, 'collections', false);
            saveStudio(studio, true);
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-link-card] .faluss-link-studio__link-summary', function () {
            var cardNode = $(this).closest('[data-fl-link-card]'), studio = cardNode.closest('.faluss-link-studio'), opening = $(this).attr('aria-expanded') !== 'true';
            studio.find('[data-fl-link-card]').not(cardNode).each(function () { $(this).find('.faluss-link-studio__link-summary').attr('aria-expanded', 'false'); $(this).find('.faluss-link-studio__link-details').prop('hidden', true); });
            $(this).attr('aria-expanded', opening ? 'true' : 'false');
            cardNode.find('.faluss-link-studio__link-details').prop('hidden', !opening);
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-save-link]', function () {
            var studio = $(this).closest('.faluss-link-studio'), linkCard = $(this).closest('[data-fl-link-card]');
            if (syncLinkCard(studio, linkCard)) { saveStudio(studio, true); }
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-delete-link]', function () {
            var studio = $(this).closest('.faluss-link-studio'), linkCard = $(this).closest('[data-fl-link-card]');
            storedBlock(studio, linkCard.data('block-id')).remove();
            renumberBlocks(studio); update(studio); saveStudio(studio, true);
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-rename-collection]', function () {
            var editor = $(this).siblings('[data-fl-collection-editor]'), opening = editor.prop('hidden');
            editor.prop('hidden', !opening); $(this).attr('aria-expanded', opening ? 'true' : 'false');
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-save-collection]', function () {
            var studio = $(this).closest('.faluss-link-studio'), editor = $(this).closest('[data-fl-collection-editor]'), collection = storedBlock(studio, $(this).data('fl-save-collection'));
            var name = $.trim(editor.find('[data-fl-collection-name]').val() || ''), description = $.trim(editor.find('[data-fl-collection-description]').val() || ''), descriptionBlock = collectionDescriptionBlock(collection);
            if (!name || !collection.length) { showStatus(studio, 'Le nom de la collection est requis.', true); return; }
            setStoredField(collection, 'value', name);
            if (description) {
                if (!descriptionBlock.length) { descriptionBlock = makeStoredBlock('text', { value: description }).insertAfter(collection); }
                else { setStoredField(descriptionBlock, 'value', description); }
            } else if (descriptionBlock.length) { descriptionBlock.remove(); }
            renumberBlocks(studio); update(studio); saveStudio(studio, true);
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-dissolve-collection]', function () {
            var studio = $(this).closest('.faluss-link-studio'), collection = storedBlock(studio, $(this).data('fl-dissolve-collection'));
            if (!collection.length) { return; }
            var group = $(), next = collection.next();
            while (next.length && next.data('block-type') !== 'section_title') { group = group.add(next); next = next.next(); }
            var description = collectionDescriptionBlock(collection); if (description.length) { group = group.not(description); description.remove(); }
            var firstCollection = studio.find('[data-fl-block-store] [data-block-type="section_title"]').first();
            if (group.length && firstCollection.length) { group.insertBefore(firstCollection); }
            collection.remove(); renumberBlocks(studio); activateSection(studio, 'collections', false); update(studio); saveStudio(studio, true);
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-background]', function () {
            var studio = $(this).closest('.faluss-link-studio'), value = $(this).data('fl-background');
            studio.find('[name="page_background"]').val(value).trigger('change');
            studio.find('[data-fl-background]').attr('aria-pressed', 'false'); $(this).attr('aria-pressed', 'true');
        })
        .on('click.falussLink', '.faluss-link-studio [data-fl-open-color]', function () { $(this).closest('.faluss-link-studio').find('.faluss-link-studio__native-color').trigger('click'); })
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
        .on('keydown.falussLink', '.faluss-link-studio [data-fl-context-tab]', function (event) {
            if (!/ArrowLeft|ArrowRight|Home|End/.test(event.key)) { return; }
            var tabs = $(this).closest('.faluss-link-studio__context-tabs').find('[data-fl-context-tab]'), index = tabs.index(this);
            if (event.key === 'ArrowRight') { index = (index + 1) % tabs.length; }
            if (event.key === 'ArrowLeft') { index = (index + tabs.length - 1) % tabs.length; }
            if (event.key === 'Home') { index = 0; }
            if (event.key === 'End') { index = tabs.length - 1; }
            event.preventDefault(); activateSection($(this).closest('.faluss-link-studio'), tabs.eq(index).data('fl-context-tab'), true);
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
            var designControl = $(this).closest('[data-fl-main-panel="style"]').length;
            if (designControl && (/^(?:checkbox|radio|color|range)$/.test((this.type || '').toLowerCase()) || this.tagName.toLowerCase() === 'select')) { queueStudioSave(studio); }
        })
        .on('change.falussLink', '.faluss-link-studio [name="faluss_identity_avatar"]', function () {
            var studio = $(this).closest('.faluss-link-studio'), file = this.files[0];
            if (!file || !/^image\//.test(file.type || '')) { return; }
            var reader = new FileReader();
            reader.onload = function (event) { card(studio).find('.faluss-link-card__avatar').empty().append($('<img>', { src: event.target.result, alt: '' })); studio.find('.faluss-link-studio__avatar').empty().append($('<img>', { src: event.target.result, alt: '' })); update(studio); };
            reader.readAsDataURL(file);
        });

    $(document).on('keydown.falussLinkPreview', function (event) {
        if (event.key !== 'Escape') { return; }
        $('.faluss-link-studio').each(function () {
            var studio = $(this);
            if (!studio.find('[data-fl-preview]').prop('hidden') || (studio.attr('data-faluss-studio-screen') || 'main') !== 'main') { closeTransient(studio); }
        });
    });

    $(function () {
        initialize(document);
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_link_studio.default', function (scope) { initialize(scope); });
            window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_link_appearance.default', function (scope) { initialize(scope); });
        }
    });
    $(window).on('elementor/frontend/init', function () {
        if (window.elementorFrontend && window.elementorFrontend.hooks) {
            window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_link_studio.default', function (scope) { initialize(scope); });
            window.elementorFrontend.hooks.addAction('frontend/element_ready/faluss_link_appearance.default', function (scope) { initialize(scope); });
        }
    });
}(jQuery));

(() => {
  'use strict';

  const sections = {
    home: ['view', 'activity', 'discover'],
    analytics: ['view', 'performance', 'revenue', 'sources'],
    subscription: ['offer', 'compare'],
    billing: ['history', 'payment'],
    settings: ['general', 'notifications', 'preferences'],
    help: ['help', 'contact']
  };

  const routeFromLocation = (fallback) => {
    const parameters = new URLSearchParams(window.location.search);
    const section = parameters.get('faluss_portal');
    const safeSection = Object.prototype.hasOwnProperty.call(sections, section) ? section : fallback.section;
    const tab = parameters.get('faluss_portal_tab');
    return {
      section: safeSection,
      tab: sections[safeSection].includes(tab) ? tab : sections[safeSection][0]
    };
  };

  const routeURL = (source, route, profile) => {
    const url = new URL(source, window.location.origin);
    url.searchParams.set('faluss_portal', route.section);
    url.searchParams.set('faluss_portal_tab', route.tab);
    if (profile) {
      url.searchParams.set('faluss_portal_profile', '1');
    } else {
      url.searchParams.delete('faluss_portal_profile');
    }
    return url;
  };

  const initialise = (root) => {
    const state = {
      section: root.dataset.section || 'home',
      tab: root.dataset.tab || 'view',
      profilePushed: false,
      suppressDialogClose: false
    };
    const navigation = [...root.querySelectorAll('[data-faluss-portal-nav]')];
    const panels = [...root.querySelectorAll('[data-faluss-portal-panel]')];
    const sideIndicator = root.querySelector('.faluss-portal__nav-indicator');
    const tabIndicator = root.querySelector('.faluss-portal__tab-indicator');
    const dialog = root.querySelector('[data-faluss-portal-master]');
    const profileFeedback = root.querySelector('[data-faluss-portal-profile-feedback]');
    const drawer = root.querySelector('[data-faluss-portal-drawer-panel]');

    const activeLinks = () => navigation.filter((link) => link.dataset.falussPortalNav === state.section);

    const placeIndicators = () => {
      const sideLink = root.querySelector(`.faluss-portal__nav-link[data-faluss-portal-nav="${state.section}"]`);
      if (sideIndicator && sideLink) {
        const parent = sideIndicator.parentElement.getBoundingClientRect();
        const target = sideLink.getBoundingClientRect();
        root.style.setProperty('--fp-sidebar-indicator-y', `${target.top - parent.top}px`);
        root.style.setProperty('--fp-sidebar-indicator-h', `${target.height}px`);
      }
      const tabLink = root.querySelector(`.faluss-portal__tab[data-faluss-portal-nav="${state.section}"][data-faluss-portal-tab="${state.tab}"]`);
      if (tabIndicator && tabLink) {
        const parent = tabIndicator.parentElement.getBoundingClientRect();
        const target = tabLink.getBoundingClientRect();
        root.style.setProperty('--fp-tab-indicator-x', `${target.left - parent.left - 8}px`);
        root.style.setProperty('--fp-tab-indicator-w', `${target.width}px`);
      }
    };

    const activate = (route, updateHistory = false) => {
      if (!Object.prototype.hasOwnProperty.call(sections, route.section)) return;
      state.section = route.section;
      state.tab = sections[route.section].includes(route.tab) ? route.tab : sections[route.section][0];
      root.dataset.section = state.section;
      root.dataset.tab = state.tab;
      navigation.forEach((link) => {
        const side = link.classList.contains('faluss-portal__nav-link');
        const active = link.dataset.falussPortalNav === state.section && (!side || true) && (!link.classList.contains('faluss-portal__tab') || link.dataset.falussPortalTab === state.tab);
        link.classList.toggle('is-active', active);
        if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
      });
      panels.forEach((panel) => {
        const active = panel.dataset.falussPortalPanel === `${state.section}:${state.tab}`;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
      });
      window.requestAnimationFrame(placeIndicators);
      if (updateHistory) {
        history.pushState({ falussPortal: true }, '', routeURL(window.location.href, state, dialog && dialog.open));
      }
    };

    const openProfile = (updateHistory) => {
      if (!dialog) return;
      if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
      else dialog.setAttribute('open', 'open');
      root.classList.add('has-master-open');
      if (updateHistory) {
        state.profilePushed = true;
        history.pushState({ falussPortal: true, falussPortalProfile: true }, '', routeURL(window.location.href, state, true));
      }
      const target = dialog.querySelector('[data-faluss-portal-master-tab].is-active');
      if (target) target.focus({ preventScroll: true });
    };

    const closeProfile = (updateHistory) => {
      if (!dialog) return;
      state.suppressDialogClose = true;
      if (typeof dialog.close === 'function' && dialog.open) dialog.close();
      else dialog.removeAttribute('open');
      root.classList.remove('has-master-open');
      state.suppressDialogClose = false;
      if (updateHistory) {
        const url = routeURL(window.location.href, state, false);
        history.replaceState({ falussPortal: true }, '', url);
      }
    };

    const setMasterTab = (tab) => {
      if (!dialog) return;
      dialog.querySelectorAll('[data-faluss-portal-master-tab]').forEach((button) => {
        const active = button.dataset.falussPortalMasterTab === tab;
        button.classList.toggle('is-active', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      dialog.querySelectorAll('[data-faluss-portal-master-panel]').forEach((panel) => {
        const active = panel.dataset.falussPortalMasterPanel === tab;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
      });
    };

    const openDrawer = (name) => {
      if (!drawer) return;
      const title = drawer.querySelector('[data-faluss-portal-drawer-title]');
      const copy = drawer.querySelector('[data-faluss-portal-drawer-copy]');
      const messages = {
        quests: ['Quêtes', 'Le moteur de progression n’est pas encore actif. Aucune quête, récompense ou solde de démonstration n’est créé.'],
        shop: ['Boutique', 'L’inventaire et les achats Faluss ne sont pas encore actifs. Aucun achat n’est proposé.'],
        premium: ['Faluss Max', 'Consultez votre offre et les moyens de paiement disponibles dans Abonnement.']
      };
      const message = messages[name] || messages.quests;
      title.textContent = message[0];
      copy.textContent = message[1];
      drawer.hidden = false;
      drawer.querySelector('button').focus({ preventScroll: true });
      if (name === 'premium') {
        window.setTimeout(() => {
          closeProfile(false);
          activate({ section: 'subscription', tab: 'offer' }, true);
        }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 1 : 190);
      }
    };

    root.addEventListener('click', (event) => {
      const navigationLink = event.target.closest('[data-faluss-portal-nav]');
      if (navigationLink && root.contains(navigationLink)) {
        event.preventDefault();
        activate({ section: navigationLink.dataset.falussPortalNav, tab: navigationLink.dataset.falussPortalTab }, true);
        return;
      }
      if (event.target.closest('[data-faluss-portal-profile-open]')) {
        openProfile(true);
        return;
      }
      if (event.target.closest('[data-faluss-portal-profile-close]')) {
        closeProfile(true);
        return;
      }
      const masterTab = event.target.closest('[data-faluss-portal-master-tab]');
      if (masterTab) {
        setMasterTab(masterTab.dataset.falussPortalMasterTab);
        return;
      }
      const drawerTrigger = event.target.closest('[data-faluss-portal-drawer]');
      if (drawerTrigger) {
        openDrawer(drawerTrigger.dataset.falussPortalDrawer);
        return;
      }
      if (event.target.closest('[data-faluss-portal-drawer-close]') && drawer) {
        drawer.hidden = true;
        return;
      }
      if (event.target.closest('[data-faluss-portal-profile-unavailable]') && profileFeedback) {
        profileFeedback.textContent = 'Le modèle de profil universel n’est pas encore validé. Aucune donnée n’a été modifiée.';
        profileFeedback.hidden = false;
      }
    });

    if (dialog) {
      dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeProfile(true);
      });
      dialog.addEventListener('close', () => {
        root.classList.remove('has-master-open');
        if (!state.suppressDialogClose) {
          history.replaceState({ falussPortal: true }, '', routeURL(window.location.href, state, false));
        }
      });
    }

    window.addEventListener('popstate', () => {
      const route = routeFromLocation(state);
      activate(route, false);
      const profile = new URLSearchParams(window.location.search).get('faluss_portal_profile') === '1';
      if (profile) openProfile(false); else closeProfile(false);
    });
    window.addEventListener('resize', () => window.requestAnimationFrame(placeIndicators));

    const initialRoute = routeFromLocation(state);
    activate(initialRoute, false);
    if (new URLSearchParams(window.location.search).get('faluss_portal_profile') === '1') openProfile(false);
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-faluss-portal="v1"]').forEach(initialise);
  });
})();

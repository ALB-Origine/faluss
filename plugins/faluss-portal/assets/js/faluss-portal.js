(() => {
  'use strict';

  const sections = {
    home: ['view', 'activity', 'discover'],
    apps: ['my-apps', 'explore'],
    analytics: ['view', 'performance', 'revenue', 'sources'],
    subscription: ['offer', 'compare'],
    billing: ['history', 'payment'],
    settings: ['general', 'notifications', 'preferences'],
    help: ['help', 'contact']
  };

  const sidebarStorageKey = 'falussPortalSidebarCollapsed';

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
    if (document.body.classList.contains('faluss-portal-page')) {
      document.documentElement.classList.add('faluss-portal-page');
    }
    const state = {
      section: root.dataset.section || 'home',
      tab: root.dataset.tab || 'view',
      profilePushed: false,
      suppressDialogClose: false,
      profileTransition: 0,
      profileCloseTimer: 0,
      profileCloseHandler: null
    };
    const navigation = [...root.querySelectorAll('[data-faluss-portal-nav]')];
    const panels = [...root.querySelectorAll('[data-faluss-portal-panel]')];
    const tabGroups = [...root.querySelectorAll('[data-faluss-portal-tabs]')];
    const sideIndicator = root.querySelector('.faluss-portal__nav-indicator');
    const sidebarToggle = root.querySelector('[data-faluss-portal-sidebar-toggle]');
    const dialog = root.querySelector('[data-faluss-portal-master]');
    const profileFeedback = root.querySelector('[data-faluss-portal-profile-feedback]');
    const drawer = root.querySelector('[data-faluss-portal-drawer-panel]');

    const placeIndicators = () => {
      const sideLink = root.querySelector(`[data-faluss-portal-sidebar-item][data-faluss-portal-nav="${state.section}"]`);
      if (sideIndicator && sideLink) {
        const target = sideLink.getBoundingClientRect();
        root.style.setProperty('--fp-sidebar-indicator-y', `${target.top}px`);
        root.style.setProperty('--fp-sidebar-indicator-h', `${target.height}px`);
      }
    };

    const syncTabGroup = (group) => {
      const tabLinks = [...group.querySelectorAll('.faluss-portal__tab')];
      const activeIndex = tabLinks.findIndex((link) => link.dataset.falussPortalTab === state.tab);
      group.dataset.activeIndex = String(Math.max(0, activeIndex));
    };

    const setSidebarCollapsed = (collapsed, persist = true) => {
      root.classList.toggle('is-sidebar-collapsed', collapsed);
      if (sidebarToggle) {
        sidebarToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        sidebarToggle.setAttribute('aria-label', collapsed ? 'Afficher la navigation' : 'Replier la navigation');
        sidebarToggle.dataset.chevronDirection = collapsed ? 'right' : 'left';
        const label = sidebarToggle.querySelector('.screen-reader-text');
        if (label) label.textContent = collapsed ? 'Afficher la navigation' : 'Replier la navigation';
      }
      if (persist) {
        try {
          window.localStorage.setItem(sidebarStorageKey, collapsed ? '1' : '0');
        } catch (error) {
          // Private browsing can deny storage; the control still works for the page.
        }
      }
      window.requestAnimationFrame(placeIndicators);
    };

    const activate = (route, updateHistory = false) => {
      if (!Object.prototype.hasOwnProperty.call(sections, route.section)) return;
      state.section = route.section;
      state.tab = sections[route.section].includes(route.tab) ? route.tab : sections[route.section][0];
      root.dataset.section = state.section;
      root.dataset.tab = state.tab;
      navigation.forEach((link) => {
        const contextual = link.classList.contains('faluss-portal__tab');
        const active = link.dataset.falussPortalNav === state.section && (!contextual || link.dataset.falussPortalTab === state.tab);
        link.classList.toggle('is-active', active);
        if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
      });
      tabGroups.forEach((group) => {
        const active = group.dataset.falussPortalTabs === state.section;
        group.classList.toggle('is-active', active);
        group.hidden = !active;
        syncTabGroup(group);
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
      state.profileTransition += 1;
      window.clearTimeout(state.profileCloseTimer);
      if (state.profileCloseHandler) dialog.removeEventListener('transitionend', state.profileCloseHandler);
      state.profileCloseHandler = null;
      dialog.classList.remove('is-open', 'is-closing');
      if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
      else dialog.setAttribute('open', 'open');
      window.requestAnimationFrame(() => window.requestAnimationFrame(() => dialog.classList.add('is-open')));
      root.classList.add('has-master-open');
      if (updateHistory) {
        state.profilePushed = true;
        history.pushState({ falussPortal: true, falussPortalProfile: true }, '', routeURL(window.location.href, state, true));
      }
    };

    const closeProfile = (updateHistory) => {
      if (!dialog || !dialog.open) return;
      const transition = state.profileTransition + 1;
      state.profileTransition = transition;
      window.clearTimeout(state.profileCloseTimer);
      dialog.classList.remove('is-open');
      dialog.classList.add('is-closing');
      if (updateHistory) {
        const url = routeURL(window.location.href, state, false);
        history.replaceState({ falussPortal: true }, '', url);
      }
      const finish = () => {
        if (state.profileTransition !== transition || !dialog.open) return;
        window.clearTimeout(state.profileCloseTimer);
        if (state.profileCloseHandler) dialog.removeEventListener('transitionend', state.profileCloseHandler);
        state.profileCloseHandler = null;
        state.suppressDialogClose = true;
        if (typeof dialog.close === 'function') dialog.close();
        else dialog.removeAttribute('open');
        dialog.classList.remove('is-closing');
        root.classList.remove('has-master-open');
        state.suppressDialogClose = false;
      };
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        finish();
        return;
      }
      state.profileCloseHandler = (event) => {
        if (event.target === dialog && event.propertyName === 'transform') finish();
      };
      dialog.addEventListener('transitionend', state.profileCloseHandler);
      state.profileCloseTimer = window.setTimeout(finish, 520);
    };

    const setMasterTab = (tab) => {
      if (!dialog) return;
      const masterTabs = [...dialog.querySelectorAll('[data-faluss-portal-master-tab]')];
      const activeIndex = masterTabs.findIndex((button) => button.dataset.falussPortalMasterTab === tab);
      const masterSwitcher = dialog.querySelector('.faluss-portal__master-tabs');
      if (masterSwitcher) masterSwitcher.dataset.activeIndex = String(Math.max(0, activeIndex));
      masterTabs.forEach((button) => {
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
        return;
      }
      if (event.target.closest('[data-faluss-portal-sidebar-toggle]')) {
        setSidebarCollapsed(!root.classList.contains('is-sidebar-collapsed'));
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

    try {
      setSidebarCollapsed(window.localStorage.getItem(sidebarStorageKey) === '1', false);
    } catch (error) {
      setSidebarCollapsed(false, false);
    }
    const initialRoute = routeFromLocation(state);
    activate(initialRoute, false);
    setMasterTab('account');
    if (new URLSearchParams(window.location.search).get('faluss_portal_profile') === '1') openProfile(false);
  };

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-faluss-portal="v1"]').forEach(initialise);
  });
})();

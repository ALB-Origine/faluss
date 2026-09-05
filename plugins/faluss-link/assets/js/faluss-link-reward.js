(function () {
  'use strict';

  function requestClaim(component, button) {
    if (!window.falussLinkReward || !window.fetch || button.disabled) return;
    button.disabled = true;
    var body = new URLSearchParams();
    body.set('action', 'faluss_link_daily_reward_claim');
    body.set('nonce', window.falussLinkReward.nonce);

    window.fetch(window.falussLinkReward.url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json();
    }).then(function (response) {
      var data = response && response.data ? response.data : {};
      if (!response || !response.success || (data.state !== 'claimed' && data.state !== 'unavailable')) throw new Error('reward_unavailable');
      component.classList.remove('faluss-link-reward--eligible');
      component.classList.add('faluss-link-reward--' + data.state);
      var status = component.querySelector('.faluss-link-reward__status');
      if (!status) return;
      status.replaceChildren();
      var message = document.createElement('p');
      message.className = 'faluss-link-reward__message';
        if (data.state === 'claimed') {
        message.textContent = data.claimed_now ? 'Récompense obtenue.' : (component.dataset.falussRewardClaimedLabel || 'Récompense quotidienne déjà réclamée.');
        if (data.next_available_at) {
          var next = document.createElement('time');
          next.dateTime = data.next_available_at;
          var nextDate = new Date(data.next_available_at);
          next.textContent = ' Disponible à nouveau le ' + (isNaN(nextDate.getTime()) ? data.next_available_at : nextDate.toLocaleString()) + '.';
          message.appendChild(next);
        }
      } else {
        message.textContent = component.dataset.falussRewardUnavailableLabel || 'Récompense quotidienne indisponible.';
      }
      status.appendChild(message);
      var balance = component.querySelector('.faluss-link-reward__balance');
      if (balance && typeof data.balance === 'number' && data.unit) {
        balance.textContent = 'Solde : ' + data.balance.toLocaleString() + ' ' + data.unit;
      }
    }).catch(function () {
      button.disabled = false;
    });
  }

  function initialize(component) {
    if (component.dataset.falussRewardReady === '1') return;
    component.dataset.falussRewardReady = '1';
    component.addEventListener('click', function (event) {
      var button = event.target.closest('[data-faluss-reward-claim]');
      if (!button || !component.contains(button)) return;
      event.preventDefault();
      requestClaim(component, button);
    });
  }

  function boot(root) {
    (root || document).querySelectorAll('[data-faluss-link-reward]').forEach(initialize);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { boot(document); });
  else boot(document);
  window.addEventListener('elementor/frontend/init', function () { boot(document); });
}());

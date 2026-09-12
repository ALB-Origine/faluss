'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');
const { PNG } = require('pngjs');
const { chromium } = require('playwright');

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

const root = path.resolve(__dirname, '..');
const portal = path.join(root, 'plugins', 'faluss-portal');
const cssPath = path.join(portal, 'assets', 'css', 'faluss-portal.css');
const scriptPath = path.join(portal, 'assets', 'js', 'faluss-portal.js');
const mePath = path.join(portal, 'assets', 'images', 'apps', 'faluss-me.png');
const badgePath = path.join(portal, 'assets', 'images', 'pf', 'faluss-pf-badge.png');
const badgeHash = crypto.createHash('sha256').update(fs.readFileSync(badgePath)).digest('hex');
assert(badgeHash === 'a25533ca502e4e6286cb58c858de8d7a4de5d25b18a5d91894b31c46ff1f1955', 'The official PF badge changed.');

const me = PNG.sync.read(fs.readFileSync(mePath));
assert(me.alpha === true, 'Faluss Me must be an RGBA PNG with a real alpha channel.');
const pixel = (x, y) => {
  const index = (me.width * y + x) * 4;
  return Array.from(me.data.subarray(index, index + 4));
};
[[0, 0], [me.width - 1, 0], [0, me.height - 1], [me.width - 1, me.height - 1]]
  .forEach(([x, y]) => assert(pixel(x, y)[3] === 0, 'Every Faluss Me corner must be transparent.'));
let opaqueWhite = 0;
for (let index = 0; index < me.data.length; index += 4) {
  if (me.data[index + 3] >= 250 && me.data[index] >= 250 && me.data[index + 1] >= 250 && me.data[index + 2] >= 250) opaqueWhite++;
}
assert(opaqueWhite > 1000, 'Faluss Me must contain opaque white symbol pixels.');

const browserCandidates = [
  process.env.FALUSS_BROWSER_EXECUTABLE,
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe'
].filter(Boolean);
const executablePath = browserCandidates.find((candidate) => fs.existsSync(candidate));
assert(executablePath, 'No installed Chromium-compatible browser is available for AP-02A.2 rendering.');

const markup = [
  '<!doctype html><html><head><meta charset="utf-8"><title>AP-02A.2</title></head>',
  '<body class="elementor" tabindex="-1"><div class="elementor-widget">',
  '<main class="faluss-portal" data-faluss-portal="v1" data-section="apps" data-tab="my-apps">',
  '<article id="hub-card" class="faluss-portal__app-card faluss-portal__app-card--compact faluss-portal__app-card--hub-daily" data-faluss-app-card data-faluss-app="hub" style="--faluss-app-accent:#000000;--faluss-app-title-accent:#ffffff">',
  '<a class="faluss-portal__app-card-access" href="https://faluss.test/hub" target="_blank" rel="noopener noreferrer" aria-label="Ouvrir Faluss Hub"></a>',
  '<div class="faluss-portal__app-head"><span class="faluss-portal__app-logo"></span>',
  '<span class="faluss-portal__app-identity"><span class="faluss-portal__app-name">Faluss Hub</span><span class="faluss-portal__app-subtitle">M’y rendre</span></span>',
  '<span class="faluss-portal__hub-daily-action" data-faluss-portal-hub-daily-action data-faluss-portal-hub-daily-state="claimable">',
  '<form class="faluss-portal__hub-daily-form" data-faluss-portal-hub-daily-reward method="post" action="https://faluss.test/claim">',
  '<input type="hidden" name="action" value="faluss_portal_claim_hub_daily"><input type="hidden" name="faluss_portal_hub_daily_nonce" value="nonce">',
  '<button class="faluss-portal__app-open faluss-portal__app-open--reward" type="submit" data-faluss-portal-hub-daily-submit aria-label="Gain quotidien : 20 Points Faluss">',
  '<img class="faluss-portal__hub-daily-badge" data-faluss-portal-pf-badge src="https://faluss.test/badge.png" alt="" aria-hidden="true"><span class="faluss-portal__hub-daily-amount" data-faluss-portal-hub-daily-amount>20</span></button>',
  '<span class="faluss-portal__hub-daily-feedback" data-faluss-portal-hub-daily-feedback role="status" hidden></span></form></span></div></article>',
  '<article id="me-card" class="faluss-portal__app-card faluss-portal__app-card--compact" data-faluss-app-card data-faluss-app="me" style="--faluss-app-accent:#ee4a4a;--faluss-app-title-accent:#ee4a4a">',
  '<a class="faluss-portal__app-card-access" href="https://faluss.me/mon-faluss" target="_blank" rel="noopener noreferrer" aria-label="Ouvrir Faluss Me"></a>',
  '<div class="faluss-portal__app-head"><span class="faluss-portal__app-logo"><img src="https://faluss.test/faluss-me.png" alt=""></span>',
  '<span class="faluss-portal__app-identity"><span class="faluss-portal__app-name">Faluss Me</span><span class="faluss-portal__app-subtitle">M’y rendre</span></span><span class="faluss-portal__app-open" aria-hidden="true"></span>',
  '</div></article></main></div></body></html>'
].join('');

const hostileElementorCSS = [
  '.elementor button {',
  'appearance:auto;display:block;width:100%;margin:14px;padding:24px;',
  'border:5px solid rgb(255,0,128);border-radius:0;outline:6px solid rgb(255,0,128);',
  'background:rgb(255,0,128);box-shadow:0 0 0 8px rgb(255,0,128);',
  'color:rgb(255,0,128);font-size:40px;',
  '}',
  '.elementor button:focus { outline:6px solid rgb(255,0,128);box-shadow:0 0 0 8px rgb(255,0,128); }'
].join('\n');

const geometry = async (page) => page.evaluate(() => {
  const button = document.querySelector('[data-faluss-portal-hub-daily-submit]');
  const badge = button.querySelector('[data-faluss-portal-pf-badge]');
  const amount = button.querySelector('[data-faluss-portal-hub-daily-amount]');
  const buttonRect = button.getBoundingClientRect();
  const badgeRect = badge.getBoundingClientRect();
  const amountRect = amount.getBoundingClientRect();
  const style = getComputedStyle(button);
  const logoStyle = getComputedStyle(document.querySelector('#me-card .faluss-portal__app-logo img'));
  const cardRect = document.querySelector('#hub-card').getBoundingClientRect();
  const outsideTarget = document.elementFromPoint(cardRect.left + 12, cardRect.top + (cardRect.height / 2));
  return {
    button: { width: buttonRect.width, height: buttonRect.height },
    badge: { left: badgeRect.left, right: badgeRect.right, width: badgeRect.width, height: badgeRect.height },
    amount: { left: amountRect.left, right: amountRect.right, width: amountRect.width, height: amountRect.height, fontSize: getComputedStyle(amount).fontSize },
    buttonEdges: { left: buttonRect.left, right: buttonRect.right },
    style: { width: style.width, borderRadius: style.borderRadius, borderWidth: style.borderWidth, outlineStyle: style.outlineStyle, fontSize: style.fontSize, whiteSpace: style.whiteSpace },
    logo: { filter: logoStyle.filter, mixBlendMode: logoStyle.mixBlendMode, transform: logoStyle.transform, objectFit: logoStyle.objectFit },
    outsideIsCardLink: Boolean(outsideTarget && outsideTarget.matches('.faluss-portal__app-card-access'))
  };
});

(async () => {
  const browser = await chromium.launch({ headless: true, executablePath });
  try {
    for (const viewport of [{ name: 'desktop', width: 1200, height: 800 }, { name: 'mobile', width: 360, height: 760 }]) {
      const page = await browser.newPage({ viewport });
      let postCount = 0;
      await page.route('https://faluss.test/fixture', (route) => route.fulfill({ status: 200, contentType: 'text/html', body: markup }));
      await page.route('https://faluss.test/badge.png', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: fs.readFileSync(badgePath) }));
      await page.route('https://faluss.test/faluss-me.png', (route) => route.fulfill({ status: 200, contentType: 'image/png', body: fs.readFileSync(mePath) }));
      await page.route('**/claim*', (route) => {
        postCount++;
        assert(route.request().method() === 'POST', 'The reward request must stay POST.');
        return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ success: true, data: { status: 'claimed' } }) });
      });
      await page.goto('https://faluss.test/fixture');
      await page.addStyleTag({ path: cssPath });
      await page.addStyleTag({ content: hostileElementorCSS });
      await page.addScriptTag({ path: scriptPath });
      await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded', { bubbles: true })));

      const before = await geometry(page);
      assert(before.button.width > before.button.height, viewport.name + ': reward must be a pill wider than it is high.');
      assert(before.button.width < 180 && before.style.width !== '100%', viewport.name + ': reward pill must remain compact, never full width.');
      assert(Math.abs(before.button.height - (viewport.name === 'desktop' ? 72 : 52)) < 0.5, viewport.name + ': historical glass height must be preserved.');
      assert(before.badge.left >= before.buttonEdges.left && before.badge.right <= before.buttonEdges.right && before.amount.left >= before.buttonEdges.left && before.amount.right <= before.buttonEdges.right, viewport.name + ': badge and amount must not overflow.');
      assert(before.amount.fontSize === (viewport.name === 'desktop' ? '23px' : '20px'), viewport.name + ': reward amount must retain the approved responsive scale.');
      assert(before.style.borderRadius === '999px' && before.style.borderWidth === '0px' && before.style.outlineStyle === 'none' && before.style.whiteSpace === 'nowrap', viewport.name + ': Elementor must not replace the pill geometry or wrapping.');
      assert(before.logo.filter === 'none' && before.logo.mixBlendMode === 'normal' && before.logo.transform === 'none' && before.logo.objectFit === 'contain', viewport.name + ': Faluss Me must use the transparent asset without CSS repair effects.');
      assert(before.outsideIsCardLink, viewport.name + ': card area outside the pill must remain the Hub link.');

      await page.$eval('[data-faluss-portal-hub-daily-submit]', (button) => { button.type = 'button'; });
      await page.click('[data-faluss-portal-hub-daily-submit]');
      const pointerFocus = await page.$eval('[data-faluss-portal-hub-daily-submit]', (button) => {
        const style = getComputedStyle(button);
        return { shadow: style.boxShadow, outline: style.outlineStyle, border: style.borderWidth };
      });
      assert(pointerFocus.outline === 'none' && pointerFocus.border === '0px' && !pointerFocus.shadow.includes('255, 0, 128'), viewport.name + ': pointer focus must not show the Elementor pink rectangle.');
      await page.$eval('[data-faluss-portal-hub-daily-submit]', (button) => { button.type = 'submit'; });

      await page.focus('body');
      await page.keyboard.press('Tab');
      await page.keyboard.press('Tab');
      const keyboardFocus = await page.$eval('[data-faluss-portal-hub-daily-submit]', (button) => {
        const style = getComputedStyle(button);
        return { focused: document.activeElement === button, visible: button.matches(':focus-visible'), shadow: style.boxShadow, radius: style.borderRadius };
      });
      assert(keyboardFocus.focused && keyboardFocus.visible && keyboardFocus.radius === '999px' && keyboardFocus.shadow.includes('255, 255, 255') && !keyboardFocus.shadow.includes('255, 0, 128'), viewport.name + ': keyboard focus must be a white pill-shaped ring.');

      const beforeClaimURL = page.url();
      await page.click('[data-faluss-portal-hub-daily-submit]');
      await page.waitForSelector('[data-faluss-portal-hub-daily-state="claimed"] .faluss-portal__app-open--reward');
      const afterRect = await page.$eval('[data-faluss-portal-hub-daily-state="claimed"] .faluss-portal__app-open--reward', (element) => {
        const rect = element.getBoundingClientRect();
        return { width: rect.width, height: rect.height, role: element.getAttribute('role'), label: element.getAttribute('aria-label') };
      });
      assert(postCount === 1 && page.url() === beforeClaimURL, viewport.name + ': clicking pill must issue one POST and no card navigation.');
      assert(Math.abs(afterRect.width - before.button.width) < 0.5 && Math.abs(afterRect.height - before.button.height) < 0.5, viewport.name + ': claimed must preserve pill geometry.');
      assert(afterRect.role === 'status' && afterRect.label.includes('déjà reçu'), viewport.name + ': claimed must keep accessible received announcement.');
      process.stdout.write(viewport.name.toUpperCase() + ' pill=' + before.button.width + 'x' + before.button.height + ' badge=' + before.badge.width + 'x' + before.badge.height + ' amount=' + before.amount.fontSize + '\n');
      await page.close();
    }
  } finally {
    await browser.close();
  }
  process.stdout.write('AP-02A.2 alpha=' + me.alpha + ' size=' + me.width + 'x' + me.height + ' opaqueWhite=' + opaqueWhite + ' badgeSHA256=' + badgeHash + ': OK\n');
})().catch((error) => {
  process.stderr.write('FAIL: ' + error.message + '\n');
  process.exit(1);
});

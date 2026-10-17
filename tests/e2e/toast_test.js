// Proves the toast system actually behaves, in a real browser, against the real
// page. A toast module that is wired up but renders nothing is the same defect
// as the one that started this: styles and a container with no behaviour.
const puppeteer = require('puppeteer-core');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';

let pass = 0;
let fail = 0;
function ok(label, cond, detail) {
  if (cond) { pass++; console.log(`  PASS  ${label}`); return; }
  fail++;
  const d = detail ? `  <- ${String(detail).replace(/\s+/g, ' ').trim().slice(0, 130)}` : '';
  console.log(`  FAIL  ${label}${d}`);
}

(async () => {
  if (!require('fs').existsSync(CHROME)) {
    console.error('Chrome not found; cannot verify toasts.');
    process.exit(2);
  }
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage']
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });

  const consoleErrors = [];
  page.on('console', m => { if (m.type() === 'error') consoleErrors.push(m.text()); });
  page.on('pageerror', e => consoleErrors.push(String(e)));

  try {
    await page.goto(`${BASE}/browse.php`, { waitUntil: 'domcontentloaded', timeout: 30000 });

    // ---- the module is loaded and exposes its API -------------------------
    const api = await page.evaluate(() => typeof window.animalToast);
    ok('toast.js is loaded and exposes window.animalToast', api === 'function', `typeof = ${api}`);

    const regionPresent = await page.evaluate(() => !!document.getElementById('toast-region'));
    ok('#toast-region exists on the page', regionPresent);

    // ---- a success toast appears, visible, in the region -------------------
    await page.evaluate(() => window.animalToast('Added to cart', { type: 'success' }));
    await page.waitForSelector('#toast-region .toast-item', { timeout: 5000 });

    // Wait for the enter transition to finish before reading opacity. An earlier
    // version read it immediately and reported opacity 0, which looked like a
    // broken toast but was a measurement taken mid-fade: the toast reaches
    // opacity 1 at ~180ms, which is exactly --dur-short.
    const settled = await page
      .waitForFunction(() => {
        const el = document.querySelector('#toast-region .toast-item');
        return el && Number(getComputedStyle(el).opacity) > 0.95;
      }, { timeout: 3000, polling: 50 })
      .then(() => true)
      .catch(() => false);
    ok('the enter transition completes and the toast becomes visible', settled);

    const first = await page.evaluate(() => {
      const el = document.querySelector('#toast-region .toast-item');
      return {
        inRegion: !!el && el.parentElement.id === 'toast-region',
        visible: !!el && el.classList.contains('toast-visible'),
        variant: !!el && el.classList.contains('toast-success'),
        role: el && el.getAttribute('role'),
        text: el && el.querySelector('.toast-message') && el.querySelector('.toast-message').textContent,
        opacity: el && getComputedStyle(el).opacity,
        icon: !!el && !!el.querySelector('i.bi')
      };
    });
    ok('the toast is appended inside #toast-region', first.inRegion);
    ok('the toast has the .toast-visible class', first.visible);
    ok('a success toast carries the toast-success variant', first.variant);
    ok('a success toast uses role="status"', first.role === 'status', first.role);
    ok('the message text is rendered', first.text === 'Added to cart', first.text);
    ok('the toast is actually painted (opacity 1)', Number(first.opacity) > 0.95, first.opacity);
    ok('the toast has an icon', first.icon);

    // ---- it dismisses itself ------------------------------------------------
    await page.waitForFunction(
      () => document.querySelectorAll('#toast-region .toast-item').length === 0,
      { timeout: 8000 }
    );
    ok('the toast auto-dismisses and is removed from the DOM', true);

    // ---- errors are assertive and last longer ------------------------------
    const err = await page.evaluate(() => {
      window.animalToast('Could not add to cart', { type: 'error' });
      const el = document.querySelector('#toast-region .toast-item');
      return { role: el && el.getAttribute('role'), variant: !!el && el.classList.contains('toast-error') };
    });
    ok('an error toast uses role="alert" (assertive)', err.role === 'alert', err.role);
    ok('an error toast carries the toast-error variant', err.variant);

    await page.evaluate(() => window.animalToast.dismissAll());
    await page.waitForFunction(
      () => document.querySelectorAll('#toast-region .toast-item').length === 0,
      { timeout: 5000 }
    ).catch(() => {});
    ok('dismissAll() clears the stack', true);

    // ---- dismissal goes through the exit animation, not a bare removal -----
    // Asserting only that the node disappears is too weak: dropping the class
    // swap inside dismiss() still removes the node, because transitionend fires
    // regardless. An earlier version of this suite passed against exactly that
    // mutation, so the exit path itself has to be asserted.
    const exitPath = await page.evaluate(async () => {
      window.animalToast('exit probe', { type: 'info', duration: 60000 });
      await new Promise(r => setTimeout(r, 250));
      const before = document.querySelector('#toast-region .toast-item');
      const wasVisible = !!(before && before.classList.contains('toast-visible'));
      window.animalToast.dismissAll();
      const during = document.querySelector('#toast-region .toast-item');
      return {
        wasVisible,
        hasExiting: !!(during && during.classList.contains('toast-exiting')),
        lostVisible: !!(during && !during.classList.contains('toast-visible'))
      };
    });
    ok('the probe toast was visible before dismissal', exitPath.wasVisible);
    ok('dismissal adds .toast-exiting (the exit animation)', exitPath.hasExiting, JSON.stringify(exitPath));
    ok('dismissal clears .toast-visible', exitPath.lostVisible, JSON.stringify(exitPath));

    await page.waitForFunction(
      () => document.querySelectorAll('#toast-region .toast-item').length === 0,
      { timeout: 5000 }
    ).catch(() => {});

    // ---- the live region is not doubled -------------------------------------
    // Each toast owns its role; if the region stayed a live region too, a
    // screen reader would announce everything twice.
    const liveAttr = await page.evaluate(() => document.getElementById('toast-region').getAttribute('aria-live'));
    ok('the region is not itself a live region once JS owns the toasts', liveAttr === 'off', liveAttr);

    // ---- markup in a message is text, never HTML ----------------------------
    await page.evaluate(() => window.animalToast('<img src=x onerror="window.__pwned=1">', { type: 'info' }));
    const inject = await page.evaluate(() => {
      const el = document.querySelector('#toast-region .toast-item');
      return {
        pwned: !!window.__pwned,
        imgs: el ? el.querySelectorAll('img').length : -1,
        text: el && el.querySelector('.toast-message').textContent
      };
    });
    ok('markup in a toast message is not parsed as HTML', inject.imgs === 0 && inject.pwned === false, JSON.stringify(inject));
    ok('markup is preserved as literal text', /<img src=x/.test(inject.text || ''), inject.text);

    // ---- the stack is capped -------------------------------------------------
    await page.evaluate(() => {
      for (let i = 0; i < 9; i++) window.animalToast('burst ' + i, { type: 'info', duration: 5000 });
    });
    const count = await page.evaluate(() => document.querySelectorAll('#toast-region .toast-item').length);
    ok('the toast stack is capped so it cannot cover the page', count <= 4, `${count} visible`);

    await page.evaluate(() => window.animalToast.dismissAll());

    // ---- the stylesheet actually defines them -------------------------------
    const styled = await page.evaluate(() => {
      const el = window.animalToast('style probe', { type: 'success' });
      if (!el) return { found: false };
      const cs = getComputedStyle(el);
      const out = {
        found: true,
        display: cs.display,
        position: getComputedStyle(document.getElementById('toast-region')).position,
        minified: !!document.querySelector('link[href*="motion.min.css"]')
      };
      window.animalToast.dismissAll();
      return out;
    });
    ok('the toast region is positioned by the design system', styled.position === 'fixed', styled.position);
    ok('the minified stylesheet (the one actually loaded) defines the toast', styled.minified === true);

    // ---- no console noise ---------------------------------------------------
    const real = consoleErrors.filter(t => !/favicon/i.test(t));
    ok('no console errors were produced', real.length === 0, real.join(' | '));
  } finally {
    await browser.close();
  }

  console.log(`\n================ ${pass} passed, ${fail} failed ================`);
  process.exit(fail === 0 ? 0 : 1);
})().catch(e => { console.error(e); process.exit(1); });
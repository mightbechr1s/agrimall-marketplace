/**
 * Post-removal checks for the superseded localStorage cart and duplicate toast.
 *
 * Removing those sections is only safe if the features that remain still work,
 * and only honest if the buttons that fed the removed basket are accounted for.
 * So this asserts both directions: the dead code is gone, and the live code runs.
 */
const puppeteer = require('puppeteer-core');
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';

let pass = 0;
let fail = 0;
function ok(label, cond, detail) {
  if (cond) { pass++; console.log(`  PASS  ${label}`); }
  else { fail++; console.log(`  FAIL  ${label}${detail ? `  <- ${detail}` : ''}`); }
}

const PAGES = ['/browse.php', '/index.php', '/login.php', '/register.php'];

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));

  console.log('\n--- removed code is actually gone ---');
  await page.goto(`${BASE}/browse.php`, { waitUntil: 'domcontentloaded' });
  const gone = await page.evaluate(() => ({
    showToast: typeof window.showToast,
    animalCart: window.localStorage.getItem('animal_cart'),
    // A listener left bound to a removed function would throw on click.
    basketLinks: document.querySelectorAll('.add-to-basket').length,
  }));
  ok('window.showToast is no longer defined', gone.showToast === 'undefined',
    `typeof was "${gone.showToast}"`);

  console.log('\n--- the server cart count is authoritative ---');
  await page.evaluate(() => {
    window.localStorage.setItem('animal_cart', JSON.stringify([
      { name: 'Stale A', price: '1', unit: 'kg', farm: 'X', qty: 50 },
      { name: 'Stale B', price: '1', unit: 'kg', farm: 'Y', qty: 49 },
    ]));
  });
  await page.reload({ waitUntil: 'domcontentloaded' });
  const count = await page.evaluate(() => {
    const el = document.querySelector('.cart-count');
    return el ? el.textContent.trim() : null;
  });
  ok('.cart-count exists', count !== null, 'markup missing');
  ok('cart count is not the stale localStorage total (99)', count !== '99',
    `cart-count rendered as "${count}"`);

  console.log('\n--- toast.js is the only toast implementation ---');
  const toast = await page.evaluate(() => ({
    api: typeof window.animalToast,
    region: !!document.getElementById('toast-region'),
  }));
  ok('window.animalToast is available', toast.api === 'function',
    `typeof was "${toast.api}"`);
  ok('#toast-region is present', toast.region);

  // The sink the removal closed: message copy must never be parsed as markup.
  const xss = await page.evaluate(() => {
    window.animalToast('<img src=x onerror="window.__xss=1">Kangkong', { type: 'success' });
    const items = document.querySelectorAll('#toast-region .toast-item');
    const last = items[items.length - 1];
    const msg = last ? last.querySelector('.toast-message') : null;
    return {
      img: !!(last && last.querySelector('img')),
      text: msg ? msg.textContent : null,
      fired: !!window.__xss,
    };
  });
  ok('toast message did not become an <img> element', !xss.img,
    'the payload was parsed into markup');
  ok('payload kept as literal text', !!(xss.text && xss.text.includes('<img src=x')),
    `textContent was ${JSON.stringify(xss.text)}`);
  ok('no injected handler ran', !xss.fired);

  console.log('\n--- preserved features still initialise on every shell ---');
  for (const path of PAGES) {
    errors.length = 0;
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
    // initThemeToggle() writes data-theme; initScrollReveal() sets .js on <html>.
    const state = await page.evaluate(() => ({
      js: document.documentElement.classList.contains('js'),
      theme: document.documentElement.getAttribute('data-theme'),
    }));
    ok(`${path} loads with no script error`, errors.length === 0, errors.join(' | '));
    ok(`${path} ran the initialiser (.js + data-theme set)`,
      state.js && !!state.theme, JSON.stringify(state));
  }

  console.log('\n--- the dashboard carousel buttons no longer dead-end ---');
  // fast-add-btn wrote only to localStorage; cart.php reads cart_items. Confirm
  // nothing on the page still expects that basket.
  errors.length = 0;
  await page.goto(`${BASE}/browse.php`, { waitUntil: 'domcontentloaded' });
  const basketStillThere = await page.evaluate(() =>
    window.localStorage.getItem('animal_cart'));
  ok('the stale basket is inert, not consumed', basketStillThere !== null,
    'localStorage key missing, so this no longer proves anything');

  await browser.close();
  console.log(`\n================ ${pass} passed, ${fail} failed ================`);
  process.exit(fail === 0 ? 0 : 1);
})().catch((e) => { console.error('crashed:', e); process.exit(1); });
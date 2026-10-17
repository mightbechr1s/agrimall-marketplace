// Browser smoke test for quantity-rejection message. Run via `npm run test:e2e` (prepares test DB, starts server). Not runnable alone.
const puppeteer = require('puppeteer-core');

const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = 'http-block@x.test';
const PASSWORD = 'password123';
const LISTING_ID = process.env.LISTING_ID || '1';

let pass = 0;
let fail = 0;

function ok(label, cond, detail) {
  if (cond) {
    pass++;
    console.log(`  PASS  ${label}`);
  } else {
    fail++;
    const d = detail ? `  <- ${String(detail).replace(/\s+/g, ' ').trim().slice(0, 130)}` : '';
    console.log(`  FAIL  ${label}${d}`);
  }
}

// Favicon/devtools noise is not a product defect; anything else is.
function isRealError(text) {
  return !/favicon/i.test(text);
}

(async () => {
  if (!require('fs').existsSync(CHROME)) {
    console.error(`  FAIL  Chrome not found at ${CHROME}`);
    console.error('        Set CHROME_PATH or install Chrome, then rerun.');
    process.exit(1);
  }

  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });

  const consoleErrors = [];
  // Attribute each error to the page that produced it. This spec drives five
  // pages in one session, so a bare "Identifier 'x' has already been declared"
  // says nothing about which of them double-loaded a script.
  const where = () => {
    try { return new URL(page.url()).pathname; } catch { return '<no url>'; }
  };
  page.on('console', (m) => {
    if (m.type() === 'error' && isRealError(m.text())) {
      consoleErrors.push(`${where()} console: ${m.text()}`);
    }
  });
  page.on('pageerror', (e) => {
    consoleErrors.push(`${where()} pageerror: ${String(e.message || e)}`);
  });

  const bodyText = () => page.evaluate(() => document.body.innerText);
  const csrfValue = () =>
    page.evaluate(() => {
      const el = document.querySelector('input[name="csrf_token"]');
      return el ? el.value : null;
    });

  try {
    console.log('\n--- login ---');
    await page.goto(`${BASE}/login.php`, { waitUntil: 'domcontentloaded' });
    const token = await csrfValue();
    ok('login page renders and exposes csrf', !!token);

    await page.type('input[name="email"]', EMAIL);
    await page.type('input[name="password"]', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
    ok('login establishes a session', !(await page.url()).includes('login.php'), page.url());

    console.log('\n--- listing renders its order form ---');
    await page.goto(`${BASE}/listing.php?id=${LISTING_ID}`, { waitUntil: 'domcontentloaded' });
    const hasForm = await page.$('#quantity');
    ok('listing page offers the quantity form', !!hasForm);
    const stillAvailable = !(await bodyText()).match(/no longer available|temporarily unavailable/i);
    ok('listing is orderable in the seeded fixture', !!stillAvailable);

    console.log('\n--- rejected quantity is explained, not silent ---');
    // Type rather than assign: inline script copies visible input on 'input'.
    await page.click('#quantity', { clickCount: 3 });
    await page.keyboard.press('Backspace');
    await page.type('#quantity', 'abc');

    const synced = await page.evaluate(
      () => document.querySelector('form[data-ajax="true"] input[name="quantity"]').value
    );
    // assert the default was replaced, not the exact string
    ok(
      'the typed value replaced the default quantity',
      synced !== '1',
      `hidden field still held the default '${synced}'`
    );

    // Opt out of client-side validation so request reaches server.
    await page.evaluate(() => {
      document.querySelector('form[action="/order_request.php"]').noValidate = true;
    });
    await page.click('form[action="/order_request.php"] button[type="submit"]');
    // No catch: swallowed navigation would assert against stale content.
    await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 });
    ok(
      'the order failure lands on the listing page',
      /listing\.php/.test(page.url()),
      page.url()
    );
    const explained = await page
      .waitForFunction(() => /valid quantity|Enter a valid/i.test(document.body.innerText), {
        timeout: 5000,
      })
      .then(() => true)
      .catch(() => false);
    // Print page text; wrong redirect target is otherwise invisible.
    const landed = (await bodyText()).replace(/\s+/g, ' ').trim().slice(0, 220);
    const alertCount = await page.evaluate(() => document.querySelectorAll('.alert').length);
    ok(
      'a rejected order quantity shows the buyer a visible explanation',
      explained,
      `url=${page.url()} alerts=${alertCount} text="${landed}"`
    );

    const alertText = await page.evaluate(() =>
      Array.from(document.querySelectorAll('.alert'))
        .filter((el) => el.offsetParent !== null && window.getComputedStyle(el).display !== 'none')
        .map((el) => el.innerText.trim())
        .join(' | ')
    );
    ok(
      'the explanation is a visible alert, not hidden markup',
      /valid quantity|Enter a valid/i.test(alertText),
      `visible alerts were: ${alertText || '(none)'}`
    );

    console.log('\n--- the cart stayed empty ---');
    // Field resets to minimum after load; retype garbage first.
    await page.click('#quantity', { clickCount: 3 });
    await page.keyboard.press('Backspace');
    await page.type('#quantity', 'abc');
    await page.evaluate(() => {
      document.querySelector('form[data-ajax="true"]').noValidate = true;
    });
    await page.click('form[data-ajax="true"] button[type="submit"]');
    await page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }).catch(() => null);
    await page.goto(`${BASE}/cart.php`, { waitUntil: 'domcontentloaded' });
    const cart = await bodyText();
    ok('cart page loads for the buyer', !/not found|temporarily unavailable/i.test(cart));
    ok('nothing was added to the cart', /empty|no items|nothing here/i.test(cart), 'cart has rows');

    console.log('\n--- no unexpected console errors ---');
    ok(
      'no console errors during the flow',
      consoleErrors.length === 0,
      consoleErrors.join(' | ')
    );
  } finally {
    await browser.close();
  }

  console.log(`\n================ ${pass} passed, ${fail} failed ================`);
  process.exit(fail === 0 ? 0 : 1);
})().catch((e) => {
  console.error('e2e crashed:', e);
  process.exit(1);
});

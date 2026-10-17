// Layout regression spec: guards three defects found by visual audit.
//   1. stat-card values clipped on mobile (col-6 halved the card, leaving the
//      body too narrow for a peso total that cannot wrap; .stat-card clips it)
//   2. auth pages exposed two headings with the same text (the decorative hero
//      h2 rendered larger than the page h1)
//   3. browse cards gave no expiry signal, so FR-LIST-033's "presented as
//      expired" was only satisfied on the detail page
//
// Run via `npm run test:layout` (prepares test DB, starts server). Not runnable alone.
const puppeteer = require('puppeteer-core');

const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const FARMER = 'http-farmer@x.test';
const PASSWORD = 'password123';

// The banner's visibility transitions over --dur-moderate (0.24s), so a
// domcontentloaded load is not a settled render. puppeteer v25 removed
// page.waitForTimeout, so sleep directly.
const settle = (ms = 900) => new Promise((r) => setTimeout(r, ms));

let pass = 0;
let fail = 0;

function ok(label, cond, detail) {
  if (cond) {
    pass++;
    console.log(`  PASS  ${label}`);
  } else {
    fail++;
    const d = detail ? `  <- ${String(detail).replace(/\s+/g, ' ').trim().slice(0, 160)}` : '';
    console.log(`  FAIL  ${label}${d}`);
  }
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

  const consoleErrors = [];
  page.on('console', (m) => {
    if (m.type() === 'error' && !/favicon/i.test(m.text())) consoleErrors.push(m.text());
  });
  page.on('pageerror', (e) => consoleErrors.push(String(e.message || e)));

  // visibility:hidden elements still report a non-zero box, so a height or
  // offsetParent probe cannot see them. Ask the engine what is actually painted.
  const bannerPainted = () =>
    page.evaluate(() => {
      const el = document.getElementById('cookie-banner');
      if (!el) return null;
      return el.checkVisibility
        ? el.checkVisibility({ visibilityProperty: true, opacityProperty: true })
        : getComputedStyle(el).visibility !== 'hidden';
    });

  try {
    // ---------------------------------------------------------------- headings
    console.log('\n--- each auth page exposes exactly one heading ---');
    for (const p of ['/login.php', '/register.php']) {
      await page.setViewport({ width: 1440, height: 1000 });
      await page.goto(`${BASE}${p}`, { waitUntil: 'domcontentloaded' });
      const h = await page.evaluate(() => {
        const shown = (e) => {
          const r = e.getBoundingClientRect();
          const s = getComputedStyle(e);
          if (r.width === 0 || r.height === 0) return false;
          if (s.visibility === 'hidden' || s.display === 'none') return false;
          return !e.closest('[aria-hidden="true"]');
        };
        const exposed = [...document.querySelectorAll('h1,h2,h3,h4')].filter(shown);
        return {
          count: exposed.length,
          h1: exposed.filter((e) => e.tagName === 'H1').length,
          levels: exposed.map((e) => `${e.tagName}:${(e.innerText || '').trim().slice(0, 20)}`),
        };
      });
      ok(`${p} exposes exactly one heading`, h.count === 1, JSON.stringify(h.levels));
      ok(`${p} exposes exactly one h1`, h.h1 === 1, JSON.stringify(h.levels));
    }

    // ---------------------------------------------------------- cookie banner
    console.log('\n--- cookie consent persists across pages ---');
    await page.goto(`${BASE}/browse.php`, { waitUntil: 'domcontentloaded' });
    // Wait for the reveal rather than sleeping a guessed interval.
    const appeared = await page
      .waitForFunction(
        () => {
          const el = document.getElementById('cookie-banner');
          if (!el) return false;
          return el.checkVisibility
            ? el.checkVisibility({ visibilityProperty: true, opacityProperty: true })
            : getComputedStyle(el).visibility !== 'hidden';
        },
        { timeout: 6000 }
      )
      .then(() => true)
      .catch(() => false);
    ok('banner is shown on a device that has not chosen', appeared);

    await page.click('[data-cookie-accept]');
    await settle();
    ok('accepting hides the banner', (await bannerPainted()) === false);

    await page.goto(`${BASE}/listing.php?id=1`, { waitUntil: 'domcontentloaded' });
    await settle();
    const stored = await page.evaluate(() => localStorage.getItem('animCk'));
    ok('choice is stored', stored === '1', `animCk=${stored}`);
    ok(
      'banner stays hidden after navigating to another page',
      (await bannerPainted()) === false,
      'the stored choice was not honoured on the next page load'
    );

    // --------------------------------------------------------------- expiry
    console.log('\n--- expired produce stays visible AND is labelled (FR-LIST-033) ---');
    await page.goto(`${BASE}/browse.php`, { waitUntil: 'domcontentloaded' });
    await settle();
    const expiry = await page.evaluate(() => {
      const cards = [...document.querySelectorAll('.grid .card')];
      return {
        cards: cards.length,
        expiredBadges: cards.filter((c) => c.querySelector('[data-testid="expired-badge"]')).length,
      };
    });
    ok('browse lists the seeded listings', expiry.cards >= 2, JSON.stringify(expiry));
    ok(
      'the expired listing is still listed, per FR-LIST-033',
      expiry.expiredBadges >= 1,
      'expired produce must not be silently removed'
    );
    ok(
      'exactly one card carries the expired badge',
      expiry.expiredBadges === 1,
      JSON.stringify(expiry)
    );

    // ------------------------------------------------------------ stat cards
    console.log('\n--- stat card values are not clipped ---');
    await page.setViewport({ width: 1440, height: 1000 });
    await page.goto(`${BASE}/login.php`, { waitUntil: 'domcontentloaded' });
    await page.type('input[name="email"]', FARMER);
    await page.type('input[name="password"]', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
      page.click('button[type="submit"]'),
    ]);
    ok('farmer login establishes a session', !(await page.url()).includes('login.php'), page.url());

    // Measure text ink, not the element box: a box wider than its text means
    // nothing is cut, a box narrower than its text means the text is cut. Only
    // ink crossing the card's own clipping boundary is a real defect.
    for (const w of [390, 576, 768, 1440]) {
      await page.setViewport({ width: w, height: 1000 });
      await page.goto(`${BASE}/dashboard.php`, { waitUntil: 'domcontentloaded' });
      await settle(700);

      const res = await page.evaluate(() => {
        const spill = [];
        for (const card of document.querySelectorAll('.stat-card')) {
          const cr = card.getBoundingClientRect();
          for (const el of card.querySelectorAll('*')) {
            if (el.children.length) continue;
            const t = (el.textContent || '').trim();
            if (!t) continue;
            const range = document.createRange();
            range.selectNodeContents(el);
            const ink = range.getBoundingClientRect();
            if (ink.width === 0) continue;
            if (ink.right > cr.right + 1) {
              spill.push({ text: t.slice(0, 24), over: Math.round(ink.right - cr.right) });
            }
          }
        }
        const cards = [...document.querySelectorAll('.stat-card')];
        return {
          spill,
          count: cards.length,
          cardW: cards.length ? Math.round(cards[0].getBoundingClientRect().width) : 0,
          overflow: document.documentElement.scrollWidth - document.documentElement.clientWidth,
        };
      });

      ok(`${w}px: stat cards rendered`, res.count > 0, `${res.count} cards`);
      ok(
        `${w}px: no stat-card text is cut off`,
        res.spill.length === 0,
        res.spill.map((s) => `${s.text} over by ${s.over}px`).join(' | ')
      );
      ok(`${w}px: page has no horizontal scroll`, res.overflow <= 0, `${res.overflow}px`);
      // The regression: col-6 produced a ~167px card, leaving ~56px for the body
      // while the peso total needs ~111px. Mobile cards must be full width.
      if (w === 390 || w === 1440) {
        ok(`${w}px: stat cards are full width, not half`, res.cardW >= 300, `${res.cardW}px`);
      }
    }

    console.log('\n--- no unexpected console errors ---');
    ok('no console errors during the flow', consoleErrors.length === 0, consoleErrors.join(' | '));
  } finally {
    await browser.close();
  }

  console.log(`\n================ ${pass} passed, ${fail} failed ================`);
  process.exit(fail === 0 ? 0 : 1);
})().catch((e) => {
  console.error('layout spec crashed:', e);
  process.exit(1);
});
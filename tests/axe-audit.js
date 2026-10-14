/**
 * AniMAL Accessibility Audit. Run: node tests/axe-audit.js
 * Crawls key pages with axe-core, prints violations, writes tests/axe-report.json
 */

const puppeteer = require('puppeteer-core');
const { AxePuppeteer } = require('@axe-core/puppeteer');
const fs = require('fs');

const BASE_URL = process.env.BASE_URL || 'http://127.0.0.1:8009';
const CHROME = 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';

const PAGES = [
  { name: 'home', url: '/index.php' },
  { name: 'home-tl', url: '/index.php?lang=tl' },
  { name: 'browse', url: '/browse.php' },
  { name: 'login', url: '/login.php' },
  { name: 'register', url: '/register.php' },
];

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu'],
  });
  const page = await browser.newPage();
  const report = { generatedAt: new Date().toISOString(), results: [] };
  let totalViolations = 0;

  for (const p of PAGES) {
    const url = `${BASE_URL}${p.url}`;
    let status = 'ok';
    try {
      const resp = await page.goto(url, { waitUntil: 'networkidle2', timeout: 15000 });
      status = resp ? String(resp.status()) : 'no-response';
    } catch (e) {
      status = `error: ${e.message.slice(0, 80)}`;
    }

    const results = await new AxePuppeteer(page).analyze();

    const summary = results.violations.map(v => ({
      id: v.id,
      impact: v.impact,
      description: v.description,
      nodes: v.nodes.map(n => n.target.join(' ')),
    }));

    totalViolations += results.violations.reduce((n, v) => n + v.nodes.length, 0);

    report.results.push({
      page: p.name,
      url,
      status,
      violations: summary,
    });

    console.log(`\n=== ${p.name} (${url}) [${status}] ===`);
    if (summary.length === 0) {
      console.log('  PASS — no violations');
    } else {
      for (const v of summary) {
        console.log(`  [${v.impact}] ${v.id}: ${v.nodes.length} node(s)`);
        for (const t of v.nodes.slice(0, 5)) console.log(`      ${t}`);
      }
    }
  }

  await browser.close();
  report.totalViolations = totalViolations;

  fs.writeFileSync('tests/axe-report.json', JSON.stringify(report, null, 2));
  console.log(`\nTOTAL violations: ${totalViolations}`);
  console.log('Report written: tests/axe-report.json');
})();
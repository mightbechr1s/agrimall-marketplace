// Renders the AgriMALL tractor mark into the Expo app icon set.
//
//   node tools/build_app_icons.js
//
// Uses the local Chrome (same as the UI tests) instead of pulling an SVG
// rasteriser into the dependency tree. Output: mobile/assets/*.png

const fs = require('node:fs');
const path = require('node:path');
const puppeteer = require('puppeteer-core');

const CHROME = process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const ASSETS = path.join(__dirname, '..', 'mobile', 'assets');
const CREAM = '#f8f9fa';

const mark = fs
  .readFileSync(path.join(__dirname, '..', 'public', 'assets', 'branding', 'mark.svg'), 'utf8')
  .replace(/^<\?xml[^>]*>\s*/, '');

function svg(color, map) {
  let out = mark;
  // The mark paints #1b4332 (solid) and #f8f9fa (wheel cutouts). A dark
  // splash needs both flipped, so map them instead of flattening to one color.
  if (map) {
    out = out
      .replace(/fill="#1b4332"/gi, `fill="${map.solid}"`)
      .replace(/fill="#2d6a4f"/gi, `fill="${map.accent ?? map.solid}"`)
      .replace(/fill="#f8f9fa"/gi, `fill="${map.cutout}"`);
  }
  return color ? out.replace(/fill="#[0-9a-fA-F]{6}"/g, `fill="${color}"`) : out;
}

const targets = [
  { file: 'icon.png', size: 1024, mark: 620, background: CREAM },
  { file: 'android-icon-background.png', size: 1024, background: CREAM },
  { file: 'android-icon-foreground.png', size: 1024, mark: 560, background: null },
  { file: 'android-icon-monochrome.png', size: 1024, mark: 560, background: null, color: '#ffffff' },
  { file: 'splash-icon.png', size: 512, mark: 360, background: null },
  {
    file: 'splash-icon-dark.png',
    size: 512,
    mark: 360,
    background: null,
    map: { solid: '#52b788', accent: '#40916c', cutout: '#121212' },
  },
  { file: 'favicon.png', size: 64, mark: 44, background: CREAM },
];

(async () => {
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });

  try {
    for (const target of targets) {
      const page = await browser.newPage();
      await page.setViewport({ width: target.size, height: target.size, deviceScaleFactor: 1 });
      const inner = target.mark
        ? svg(target.color, target.map).replace(
            '<svg ',
            `<svg width="${target.mark}" height="${target.mark}" `,
          )
        : '';
      await page.setContent(
        `<!doctype html><html><body style="margin:0;width:${target.size}px;height:${target.size}px;` +
          `background:${target.background ?? 'transparent'};display:flex;align-items:center;` +
          `justify-content:center">${inner}</body></html>`,
        { waitUntil: 'load' },
      );
      await page.screenshot({
        path: path.join(ASSETS, target.file),
        omitBackground: !target.background,
      });
      await page.close();
      console.log(`wrote mobile/assets/${target.file}`);
    }
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const START_URL = 'https://thamani-academy.youware.app/';
const OUTPUT_DIR = path.resolve(__dirname, './thamani-academy.youware.app');
const ORIGIN = new URL(START_URL).origin;
const MAX_PAGES = 5000; // higher safety limit

function urlToFilePath(url) {
  const u = new URL(url);
  // Build a filesystem path for the URL. If the URL path ends with a slash or has no extension, save as index.html in that directory.
  let pathname = u.pathname;
  if (!pathname || pathname === '/') {
    return { dir: OUTPUT_DIR, file: path.join(OUTPUT_DIR, 'index.html') };
  }
  // normalize: remove leading slash
  if (pathname.startsWith('/')) pathname = pathname.slice(1);
  const hasExt = path.basename(pathname).includes('.');
  if (hasExt) {
    const file = path.join(OUTPUT_DIR, pathname);
    return { dir: path.dirname(file), file };
  } else {
    const dir = path.join(OUTPUT_DIR, pathname);
    return { dir, file: path.join(dir, 'index.html') };
  }
}

(async () => {
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  const browser = await puppeteer.launch({ args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  const page = await browser.newPage();
  await page.setUserAgent('Mozilla/5.0 (compatible; Puppeteer)');

  const visited = new Set();
  const queue = [START_URL];

  while (queue.length && visited.size < MAX_PAGES) {
    const url = queue.shift();
    if (visited.has(url)) continue;
    console.log('Visiting', url);

    try {
      await page.goto(url, { waitUntil: ['domcontentloaded', 'networkidle2'], timeout: 60000 });
    } catch (err) {
      console.warn('Failed to load', url, err.message);
      visited.add(url);
      continue;
    }

    // Wait a short time for client-side routing to settle
    await new Promise(r => setTimeout(r, 500));

    // Extract same-origin links (anchors) and also look for possible route-like hrefs in the page HTML
    const discovered = await page.evaluate(() => {
      const anchors = Array.from(document.querySelectorAll('a[href]'));
      const hrefs = anchors.map(a => a.href).filter(Boolean);
      // Search the page HTML for quoted href-like paths (e.g., "/some-path") to catch router-only links
      const html = document.documentElement.innerHTML;
      const extra = Array.from(html.matchAll(/href=\"(\/[^\"#\s>]+)\"/g)).map(m => new URL(m[1], location.href).toString());
      const combined = hrefs.concat(extra);
      // Deduplicate
      return Array.from(new Set(combined));
    });

    for (const link of discovered) {
      try {
        const u = new URL(link);
        if (u.origin === ORIGIN) {
          // normalize: strip hash and search for deterministic form
          u.hash = '';
          // remove trailing slash for normalization (but keep root '/'
          let normalized = u.toString();
          if (normalized.endsWith('/') && normalized !== ORIGIN + '/') normalized = normalized.replace(/\/$/, '');
          if (!visited.has(normalized) && !queue.includes(normalized)) {
            queue.push(normalized);
          }
        }
      } catch (e) {
        // ignore invalid URLs
      }
    }

    // Get rendered HTML
    let html = await page.content();

    // Rewrite absolute references to the site origin to root-relative paths so local assets are used
    const re = new RegExp(ORIGIN.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
    html = html.replace(re, '');

    // Save to file system
    const { dir, file } = urlToFilePath(url === ORIGIN + '/' ? START_URL : url);
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(file, html, 'utf8');
    console.log('Saved', file);

    visited.add(url);
  }

  await browser.close();
  console.log('Crawl finished. Visited', visited.size, 'pages.');
})();

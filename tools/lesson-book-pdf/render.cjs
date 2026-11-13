'use strict';
// CLI only: print locally generated, self-contained lesson-book HTML. No web server or login cookies.
const fs = require('node:fs/promises');
const path = require('node:path');
let browser;
const timeoutMs = Math.max(5000, Math.min(120000, Number(process.env.CDS_PDF_TIMEOUT_MS) || 90000));
let watchdog;
async function render(input, output, check = false) {
  const moduleRoot = process.env.CDS_PDF_NODE_MODULES;
  let puppeteer;
  try { puppeteer = require(moduleRoot ? require.resolve('puppeteer', {paths: [path.dirname(path.resolve(moduleRoot))]}) : 'puppeteer'); }
  catch { throw new Error('PDF_DEPENDENCIES_MISSING: Run npm ci in the configured Node.js application root.'); }
  const html = check ? '<!doctype html><html><head><meta charset="utf-8"><style>@page{size:A4;margin:15mm}body{font-family:"Times New Roman",serif}</style></head><body><section data-cds-lesson-print="1">CDS PDF: Tiếng Việt có dấu – Sổ đầu bài</section></body></html>' : await fs.readFile(input, 'utf8');
  if (Buffer.byteLength(html) > 32 * 1024 * 1024 || !html.includes('data-cds-lesson-print="1"')) throw new Error('PDF_INVALID_PRINT_SOURCE');
  const args = ['--disable-dev-shm-usage', '--disable-background-networking'];
  if (process.env.CDS_PDF_NO_SANDBOX === '1') args.push('--no-sandbox', '--disable-setuid-sandbox');
  const options = {headless: 'shell', pipe: true, timeout: Math.min(timeoutMs, 30000), args};
  if (process.env.CDS_PDF_CHROMIUM) options.executablePath = process.env.CDS_PDF_CHROMIUM;
  try { browser = await puppeteer.launch(options); }
  catch (error) { throw new Error('PDF_CHROMIUM_UNAVAILABLE: ' + error.message); }
  const page = await browser.newPage();
  page.setDefaultTimeout(Math.min(timeoutMs, 30000));
  await page.setJavaScriptEnabled(false);
  await page.setRequestInterception(true);
  let blocked = false;
  page.on('request', request => {
    const url = request.url();
    if (url === 'about:blank' || /^data:image\/(?:png|jpeg|gif|webp);base64,/i.test(url)) void request.continue();
    else { blocked = true; void request.abort(); }
  });
  await page.setContent(html, {waitUntil: 'load'});
  await page.emulateMediaType('print');
  await page.bringToFront();
  await page.evaluate(async () => { await document.fonts.ready; await Promise.all([...document.images].map(image => image.decode())); });
  if (blocked) throw new Error('PDF_EXTERNAL_RESOURCE_BLOCKED: Print template must contain inline assets only.');
  await page.waitForSelector('[data-cds-lesson-print="1"]');
  const pages = await page.$$eval('section.page', nodes => nodes.length);
  if (!check && !pages) throw new Error('PDF_EMPTY_PRINT_SOURCE');
  const bytes = await page.pdf({preferCSSPageSize: true, printBackground: true, displayHeaderFooter: false, waitForFonts: true, timeout: Math.min(timeoutMs, 60000)});
  if (Buffer.from(bytes).subarray(0, 5).toString() !== '%PDF-' || bytes.length < 100) throw new Error('PDF_INVALID_OUTPUT');
  if (!check) { await fs.writeFile(output, bytes, {mode: 0o600, flag: 'wx'}); }
  return {ok: true, bytes: bytes.length, sections: pages, node: process.versions.node};
}
async function main() {
  const check = process.argv[2] === '--check';
  if (!check && (process.argv.length !== 4 || path.resolve(process.argv[2]) === path.resolve(process.argv[3]))) throw new Error('Usage: node render.cjs input.html output.pdf | --check');
  watchdog = setTimeout(() => { void (browser ? browser.close() : Promise.resolve()).finally(() => { process.stderr.write('PDF_RENDER_TIMEOUT\n'); process.exit(1); }); }, timeoutMs);
  const result = await render(process.argv[2], process.argv[3], check);
  process.stdout.write(JSON.stringify(result) + '\n');
}
main().catch(error => { process.stderr.write(String(error.message).slice(0, 2000) + '\n'); process.exitCode = 1; })
  .finally(async () => { clearTimeout(watchdog); if (browser) await browser.close().catch(() => {}); });

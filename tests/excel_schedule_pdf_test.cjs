'use strict';
// Local synthetic HTML only. Assert no scripts, network access, partial PDFs or overwritten files.
const {execFileSync, spawnSync} = require('node:child_process');
const fs = require('node:fs'), os = require('node:os'), path = require('node:path'), assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..'), renderer = path.join(root, 'tools/lesson-book-pdf/render.cjs');
const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'cds-local-pdf-'));
const env = {...process.env, CDS_PDF_NO_SANDBOX: '1', CDS_PDF_TIMEOUT_MS: '30000'};
const input = path.join(dir, 'source.html'), output = path.join(dir, 'result.pdf');
const run = () => spawnSync(process.execPath, [renderer, input, output], {env, encoding: 'utf8', timeout: 40000});
try {
  fs.writeFileSync(input, '<html><body>Not a reviewed print template</body></html>');
  let r = run(); assert.notEqual(r.status, 0); assert.match(r.stderr, /PDF_INVALID_PRINT_SOURCE/); assert.equal(fs.existsSync(output), false);
  fs.writeFileSync(input, '<html><body data-cds-lesson-print="1"><section class="page"><img src="http://127.0.0.1:9/blocked"></section></body></html>');
  r = run(); assert.notEqual(r.status, 0); assert.equal(fs.existsSync(output), false, 'Failed image/blocked request cannot produce a partial PDF');
  fs.writeFileSync(input, '<html><head><style>@page{size:A4;margin:0}.page{height:297mm}</style></head><body data-cds-lesson-print="1"><section class="page">Đúng mẫu<script>location.href="http://127.0.0.1:9/script";</script></section></body></html>');
  r = run(); assert.equal(r.status, 0, r.stderr); assert.equal(JSON.parse(r.stdout).ok, true); assert.equal(fs.readFileSync(output).subarray(0, 5).toString(), '%PDF-');
  const before = fs.readFileSync(output); r = run(); assert.notEqual(r.status, 0); assert.deepEqual(fs.readFileSync(output), before, 'Existing path must never be overwritten');
  fs.unlinkSync(output); r = spawnSync(process.execPath, [renderer, '--check'], {env: {...env, CDS_PDF_NODE_MODULES: path.join(dir, 'missing/node_modules')}, encoding: 'utf8'});
  assert.notEqual(r.status, 0); assert.match(r.stderr, /PDF_DEPENDENCIES_MISSING/);
  console.log('PASS PDF renderer: local reviewed source, no JS/navigation, no external assets, fail closed, valid PDF and no overwrite');
} finally { fs.rmSync(dir, {recursive: true, force: true}); }

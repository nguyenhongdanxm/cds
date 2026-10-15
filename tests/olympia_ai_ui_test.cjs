const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
class Element {
  constructor() { this.children = []; this.events = {}; this.dataset = {}; this.value = ''; this.checked = false; this.hidden = false; this.disabled = false; }
  append(...nodes) { this.children.push(...nodes); }
  replaceChildren(...nodes) { this.children = nodes; }
  set textContent(text) { this.text = text; this.children = []; }
  get textContent() { return this.text || ''; }
  set innerHTML(_) { throw new Error('Unsafe HTML rendering'); }
  addEventListener(name, action) { this.events[name] = action; }
  querySelector(selector) {
    const key = selector.match(/^\[data-(.+)\]$/)?.[1];
    for (const child of this.children) {
      if (child.dataset && Object.hasOwn(child.dataset, key)) return child;
      const nested = child.querySelector?.(selector); if (nested) return nested;
    }
    return null;
  }
  querySelectorAll() { return []; }
}
function boot(responder) {
  const ids = {};
  for (const name of ['topic','reference','grade','stage','difficulty','count','points','status','generate','save','stop','drafts','save-area','reviewed']) ids[`oly-ai-${name}`] = new Element();
  const panel = new Element(); panel.dataset = { csrf: 'csrf', week: 'fixture-week', token: 'one-use-token' }; ids['olympia-ai-panel'] = panel;
  const get = name => ids[`oly-ai-${name}`];
  for (const [name,value] of Object.entries({topic:'Kiến thức thử',grade:'6',stage:'Khởi động',difficulty:'medium',count:'3',points:'10'})) get(name).value = value;
  const calls = [], location = { pathname: '/hoclieu_game_olympia.php', reload() { location.reloads = (location.reloads || 0) + 1; } };
  const document = { getElementById: id => ids[id] || null, createElement: () => new Element(), createTextNode: text => ({textContent:text}) };
  const context = { document, location, URLSearchParams, AbortController, setTimeout: resolve => resolve(), window: { confirm: () => true, addEventListener() {} },
    fetch: async (path, options) => { const fields = Object.fromEntries(options.body); calls.push(fields); return responder(fields, calls.length, options); } };
  vm.runInNewContext(fs.readFileSync('assets/olympia-ai.js','utf8'), context);
  return { get, calls, location };
}
const question = index => ({question_text:`Câu ${index}?`,answer_text:'<img src=x onerror=alert(1)>',explanation:'Giải thích',grade_scope:'6',stage_name:'Khởi động',points:10});
const ok = data => ({ok:true,json:async()=>({ok:true,...data})});
(async () => {
  const app = boot((fields, n) => fields.action === 'generate_olympia_ai' ? ok({questions:n===1?[question(1),question(2)]:[question(3)]}) : ok({saved:1}));
  await app.get('generate').events.click();
  assert.deepStrictEqual(app.calls.map(x=>x.count),['2','1']);
  assert.strictEqual(JSON.parse(app.calls[1].prior).questions.length,2);
  assert.strictEqual(app.get('drafts').children.length,3);
  assert.strictEqual(app.get('save-area').hidden,false);
  await app.get('save').events.click(); assert.strictEqual(app.calls.length,2,'Human review required');
  const cards = app.get('drafts').children;
  cards[0].querySelector('[data-select]').checked=false; cards[2].querySelector('[data-select]').checked=false;
  cards[1].querySelector('[data-question]').value='Câu đã chỉnh?'; app.get('reviewed').checked=true;
  const save=app.get('save').events.click(); const secondSave=app.get('save').events.click(); await Promise.all([save,secondSave]);
  assert.strictEqual(app.calls.length,3,'Double-click cannot send a second save');
  assert.strictEqual(app.calls[2].week_id,'fixture-week'); assert.strictEqual(app.calls[2].import_token,'one-use-token');
  assert.deepStrictEqual(JSON.parse(app.calls[2].questions).questions.map(x=>x.question_text),['Câu đã chỉnh?']);
  assert.strictEqual(app.location.reloads,1);
  const partial=boot((fields,n)=>n===1?ok({questions:[question(1),question(2)]}):{ok:false,json:async()=>({ok:false,message:'Lỗi giả lập'})});
  await partial.get('generate').events.click();
  assert.strictEqual(partial.get('drafts').children.length,2,'Partial results survive a later provider failure');
  assert(partial.get('status').textContent.includes('Giữ lại 2 câu'));
  partial.get('reviewed').checked=true;await partial.get('save').events.click();
  assert.strictEqual(partial.get('drafts').children.length,2,'Failed save retains editable preview');
  const cancelled=boot((fields,n,options)=>new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>{const error=new Error('Abort');error.name='AbortError';reject(error);}))); 
  const generating=cancelled.get('generate').events.click();cancelled.get('stop').events.click();await generating;
  assert.strictEqual(cancelled.calls.length,1);assert(cancelled.get('status').textContent.includes('Đã dừng'));
  const invalid=boot(()=>{throw new Error('Must not request');});invalid.get('count').value='0';await invalid.get('generate').events.click();assert.strictEqual(invalid.calls.length,0);
  console.log('PASS Olympia AI UI: chunked generation, partial errors, text-only rendering, review, selection, edited save, double-click and cancellation');
})().catch(error=>{console.error(error);process.exitCode=1;});

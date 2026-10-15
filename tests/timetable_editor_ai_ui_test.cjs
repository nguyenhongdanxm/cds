const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('assets/timetable-editor-ai.js', 'utf8');
function run(open) {
  const elements = {};
  function element(id) { return elements[id] = {dataset: {}, handlers: {}, value: '', disabled: false, textContent: '',
    addEventListener(type, fn) { this.handlers[type] = fn; },
    querySelectorAll() { return this.buttons || []; },
    click() { this.clicks = (this.clicks || 0) + 1; },
    requestSubmit() { this.requests = (this.requests || 0) + 1; this.handlers.submit(); }
  }; }
  ['sideAi','editorAiCommandForm','editorAiApplyForm','editorAiCheck','editorAiCommand','editorAiProgress','aiTab'].forEach(element);
  elements.sideAi.dataset.open = open;
  for (const id of ['editorAiCommandForm','editorAiApplyForm']) elements[id].buttons = [{disabled: false}];
  vm.runInNewContext(source, {document: {getElementById: id => elements[id], querySelector: () => elements.aiTab}});
  return elements;
}
let el = run('1');
assert.equal(el.aiTab.clicks, 1);
assert.equal(el.editorAiApplyForm.requests, undefined, 'Opening AI never applies');
el.editorAiCheck.handlers.click();
assert.equal(el.editorAiCommand.value, 'Kiểm tra lỗi TKB');
assert.equal(el.editorAiCommandForm.requests, 1);
assert.equal(el.editorAiCommandForm.buttons[0].disabled, true);
assert.match(el.editorAiProgress.textContent, /Chưa áp dụng/);
assert.equal(el.editorAiApplyForm.requests, undefined, 'Check only submits preview');
el.editorAiApplyForm.handlers.submit();
assert.equal(el.editorAiApplyForm.buttons[0].disabled, true);
assert.match(el.editorAiProgress.textContent, /kiểm tra lại/);
el = run('0');assert.equal(el.aiTab.clicks, undefined, 'Normal editor keeps current tab');
console.log('PASS editor UI: reopen AI, explicit preview/apply, check action, double-submit protection');

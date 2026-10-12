const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

class Element {
  constructor() {
    this.children = [];
    this.events = {};
    this.dataset = {};
    this.attributes = {};
    this.hidden = false;
    this.text = '';
  }
  set textContent(value) { this.text = value; this.children = []; }
  get textContent() { return this.text; }
  set innerHTML(value) {
    if (value.includes('voice-card')) {
      this.queries = {
        '.voice-status': new Element(),
        '.voice-text': new Element(),
        '.voice-options': new Element(),
        '.voice-stop': new Element(),
        '.voice-retry': new Element()
      };
    }
  }
  appendChild(child) { this.children.push(child); return child; }
  addEventListener(type, handler) { this.events[type] = handler; }
  setAttribute(name, value) { this.attributes[name] = value; }
  querySelector(selector) { return this.queries && this.queries[selector]; }
  focus() {}
  click() { if (this.events.click) this.events.click({ target: this }); }
}

function createNavigation(targets) {
  const data = new Element();
  data.textContent = JSON.stringify(targets);
  const button = new Element();
  const documentEvents = {};
  const recognitionInstances = [];
  const document = {
    activeElement: button,
    body: new Element(),
    getElementById: id => id === 'voiceNavTargets' ? data : null,
    querySelectorAll: selector => selector === '[data-voice-open]' ? [button] : [],
    createElement: () => new Element(),
    addEventListener: (type, handler) => { documentEvents[type] = handler; }
  };
  function SpeechRecognition() {
    recognitionInstances.push(this);
    this.start = () => { if (this.onstart) this.onstart(); };
    this.abort = () => {};
  }
  const location = {};
  const sandbox = {
    document,
    window: { SpeechRecognition, location },
    console,
    setTimeout,
    clearTimeout
  };
  vm.runInNewContext(fs.readFileSync(__dirname + '/../assets/voice-nav.js', 'utf8'), sandbox);
  return { button, document, documentEvents, location, recognitionInstances };
}

function recognize(nav, phrase) {
  nav.button.click();
  const rec = nav.recognitionInstances[0];
  rec.onresult({ resultIndex: 0, results: [Object.assign([{ transcript: phrase }], { isFinal: true })] });
  rec.onend();
}

const ambiguous = createNavigation([
  { name: 'Thư viện', url: 'thuvien.php', keywords: ['sách'] },
  { name: 'Thiết bị', url: 'thietbi.php', keywords: ['sách'] }
]);
recognize(ambiguous, 'mở sách');
const dialog = ambiguous.document.body.children[0];
assert.equal(dialog.dataset.state, 'choose');
assert.equal(dialog.queries['.voice-options'].children.length, 2);
ambiguous.documentEvents.keydown({ key: '2' });
assert.equal(ambiguous.location.href, 'thietbi.php');

const fuzzy = createNavigation([{ name: 'Thư viện', url: 'thuvien.php', keywords: ['thư viện'] }]);
recognize(fuzzy, 'mở thu vienn');
assert.equal(fuzzy.location.href, 'thuvien.php');

const unrecognized = createNavigation([{ name: 'Thư viện', url: 'thuvien.php', keywords: ['thư viện'] }]);
recognize(unrecognized, 'mở quê hương');
const errorDialog = unrecognized.document.body.children[0];
assert.equal(errorDialog.dataset.state, 'error');
assert(errorDialog.queries['.voice-text'].textContent.includes('chuyển đến thư viện'));

console.log('Voice navigation: fuzzy matching, numbered choices, navigation and clear errors passed.');

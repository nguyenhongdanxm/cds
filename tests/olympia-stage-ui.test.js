const fs = require('fs');
const vm = require('vm');
const assert = require('assert');

let source = fs.readFileSync('olympia_stage.php', 'utf8').match(/<script>([\s\S]*?)<\/script>/)?.[1];
assert(source, 'Missing stage script');
source = source.replace(/<\?=json_encode\(\$(?:mode|csrf|code|podiumSeat)\)\?>/g, '"test"');
new vm.Script(source);
source = source.slice(0, source.indexOf("function render(){"));

const context = {document: {getElementById: () => null}, Date, Audio: function () {}, fetch: () => {}, setInterval: () => {}};
vm.createContext(context);
vm.runInContext(source, context);

const base = {
  round: 'khoi_dong', scene: 'intro', question: 'Câu hỏi thử', answer_key: 'Đáp án', question_no: 1,
  mode: 'private', seat: 0, scores: [0, 10, 20, 30], names: ['A', 'B', 'C', 'D'],
  portraits: ['', '', '', ''], logo: '', media: '', pack: [20, 30, 20], pack_index: 0,
  puzzle_open: [false, false, false, false, false], puzzle_words: ['A', 'B', 'C', 'D'],
  answers: {0: {text: 'A', at: 1000, elapsed_ms: 1000, approved: true, awarded_points: 40}},
  timer_end: 0, timer_seconds: 20, revealed: false, event: 0, sounds: {}, buzz: null, star_active: false,
  tie_winner: null
};

for (const round of ['khoi_dong', 'vuot_chuong_ngai_vat', 'tang_toc', 've_dich', 'cau_hoi_phu']) {
  for (const scene of ['intro', 'question', 'results', 'puzzle', 'pack', 'star', 'score', 'finished']) {
    vm.runInContext(`state=${JSON.stringify({...base, round, scene})};result=stageHTML();`, context);
    assert(context.result.includes('class="stage '), `${round}/${scene} did not render`);
    assert(context.result.includes('ĐƯỜNG LÊN ĐỈNH OLYMPIA'), `${round}/${scene} lost branding`);
  }
}
assert(!fs.readFileSync('includes/olympia_stage.php','utf8').includes("unset($state['undo_judge']);"), 'Question bank must not be public');
const api = fs.readFileSync('olympia_stage_api.php','utf8');
for(const action of ['rooms','delete_room','edition','bank_save','bank_delete']) assert(api.includes(`'${action}'`), `${action} missing`);
for(const tab of ['session','content','control','settings']) assert(fs.readFileSync('olympia_stage.php','utf8').includes(`data-panel="${tab}"`), `${tab} tab missing`);
console.log('40 Olympia stage scenes and administration routes checked.');

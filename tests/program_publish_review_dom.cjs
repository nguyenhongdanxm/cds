const assert=require('node:assert/strict');
const fs=require('node:fs');
const {spawnSync}=require('node:child_process');
const {JSDOM}=require('jsdom');
const js=fs.readFileSync('assets/timetable-publish-review.js','utf8');
function render(mode){const p=spawnSync('php',['tests/program_publish_review_test.php','--html',mode],{encoding:'utf8'});assert.equal(p.status,0,p.stderr);const dom=new JSDOM(p.stdout,{runScripts:'outside-only'});dom.window.confirm=()=>true;dom.window.eval(js);return dom;}
let dom=render('mismatch'),w=dom.window,d=w.document,boxes=[...d.querySelectorAll('[name="gdp_ack[]"]')],button=d.getElementById('publishButton');
assert.equal(boxes.length,2);assert(boxes.every(b=>b.required&&!b.checked));assert(button.disabled);
boxes[0].checked=true;boxes[0].dispatchEvent(new w.Event('change'));assert(button.disabled);assert.match(d.getElementById('gdpReviewCount').textContent,/1\/2/);
boxes[1].checked=true;boxes[1].dispatchEvent(new w.Event('change'));assert(!button.disabled);
boxes[0].checked=false;boxes[0].dispatchEvent(new w.Event('change'));assert(button.disabled);
let event=new w.Event('submit',{cancelable:true});d.getElementById('publishForm').dispatchEvent(event);assert(event.defaultPrevented);
boxes[0].checked=true;boxes[0].dispatchEvent(new w.Event('change'));w.confirm=()=>false;
event=new w.Event('submit',{cancelable:true});d.getElementById('publishForm').dispatchEvent(event);assert(event.defaultPrevented,'Cancelling publication stops submit');
let requests=0;d.getElementById('weekReviewForm').requestSubmit=()=>requests++;d.getElementById('schoolWeek').dispatchEvent(new w.Event('change'));assert.equal(requests,1,'New week requests fresh server review');
dom=render('mismatch');assert([...dom.window.document.querySelectorAll('[name="gdp_ack[]"]')].every(b=>!b.checked),'Reload never carries old consent');
dom=render('clean');assert(!dom.window.document.getElementById('publishButton').disabled,'Matching teachers need no extra checkbox');
for(const mode of ['error','no-week']){dom=render(mode);assert.equal(dom.window.document.getElementById('publishForm'),null,'Cannot submit without valid week/review: '+mode);}
dom=render('incomplete');w=dom.window;d=w.document;[...d.querySelectorAll('[name="gdp_ack[]"]')].forEach(b=>{b.checked=true;b.dispatchEvent(new w.Event('change'))});let calls=0;w.confirm=()=>{calls++;return true};event=new w.Event('submit',{cancelable:true});d.getElementById('publishForm').dispatchEvent(event);assert(!event.defaultPrevented);assert.equal(calls,2);assert.equal(d.getElementById('confirmIncomplete').value,'1');
console.log('PASS publication DOM: every acknowledgement, disabled button, cancel, fresh week/reload, matching teachers, blocking errors and incomplete confirmation');

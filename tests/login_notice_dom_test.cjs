const assert = require('node:assert/strict');
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
const {JSDOM} = require('jsdom');
const html = execFileSync('php', ['tests/login_notice_dom_fixture.php'], {encoding:'utf8'});
const popupScript = fs.readFileSync('assets/login-notice.js','utf8');
const viewerScript = fs.readFileSync('assets/viewer-read-only.js','utf8');
async function scenario({viewer=false, trigger='button', failure=false, stale=false}={}) {
  const dom = new JSDOM(html,{url:'https://school.example/cds/admin.php',runScripts:'outside-only'});
  const {window:w}=dom, form=w.document.getElementById('cdsLnForm'), dialog=w.document.getElementById('cdsLoginNotice');
  let closed=0, calls=0;
  dialog.showModal=()=>dialog.setAttribute('open','');
  dialog.close=()=>{closed++;dialog.removeAttribute('open');};
  // Match browser form named-property clobbering, including engines jsdom does not emulate.
  Object.defineProperty(form,'action',{value:form.elements.namedItem('action'),configurable:true});
  assert.equal(typeof form.action,'object','Hidden input named action shadows native action property');
  w.fetch=async (url,options)=>{
    calls++;
    assert.equal(new URL(url,w.location.href).href,'https://school.example/cds/login_notice.php','POST must target the actual form action URL');
    assert.equal(options.method,'POST');assert.equal(options.credentials,'same-origin');
    assert.equal(options.body.get('action'),'dismiss');assert.equal(options.body.get('notice_id'),'fixture-notice');
    assert.equal(options.body.get('csrf').length,48,'CSRF included');
    if(failure&&calls===1)throw new Error('Offline');
    return {ok:!stale,status:stale?409:200,json:async()=>stale?{ok:false,message:'Thông báo đã thay đổi.'}:{ok:true,next:'/cds/admin.php'}};
  };
  if(viewer){
    w.eval(viewerScript);w.document.dispatchEvent(new w.Event('DOMContentLoaded'));
    assert([...form.elements].every(el=>!el.disabled),'Viewer can acknowledge own announcement');
    assert([...w.document.getElementById('businessForm').elements].every(el=>el.disabled),'Business form stays disabled');
  }
  w.eval(popupScript);
  const fire=()=>{if(trigger==='escape')dialog.dispatchEvent(new w.Event('cancel',{cancelable:true}));else form.querySelector(trigger==='x'?'.cds-ln-x':'.cds-ln-close').click();};
  fire();await new Promise(resolve=>setImmediate(resolve));
  if(failure){assert.equal(closed,0);assert(dialog.hasAttribute('open'));assert.equal(form.querySelector('.cds-ln-error').hidden,false);fire();await new Promise(resolve=>setImmediate(resolve));}
  if(stale){assert.equal(closed,0);assert(form.querySelector('.cds-ln-error button'),'Stale notice offers reload');}
  else assert.equal(closed,1,'Dialog closes after receipt succeeds');
  assert.equal(calls,failure?2:1);
  assert([...form.querySelectorAll('button')].every(el=>!el.disabled),'Buttons available after completion/retry');
  w.close();
}
(async()=>{
  for(const viewer of [false,true])for(const trigger of ['button','x','escape'])await scenario({viewer,trigger});
  await scenario({failure:true});await scenario({stale:true});
  console.log('PASS: actual popup template with clobbered form.action; Close, X, Escape, viewer isolation, network retry, stale notice');
})().catch(error=>{console.error(error);process.exit(1);});

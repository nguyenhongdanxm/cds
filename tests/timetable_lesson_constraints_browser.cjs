const fs=require('node:fs');
const assert=require('node:assert/strict');
const {chromium}=require('playwright');
const http=require('node:http');
const deadline=setTimeout(()=>{console.error('Timed out');process.exit(1);},45000);deadline.unref();
(async()=>{
 let server;
 const browser=await chromium.launch({headless:true});
 try{
 const page=await browser.newPage({viewport:{width:1100,height:650}});
 const html=fs.readFileSync('/tmp/lesson-constraints.html','utf8').replace(/<script src="https:[^"]+"[^>]*><\/script>/g,'');
 const asset=fs.readFileSync('assets/lesson-constraints.js','utf8');
 let posts=[];
 server=http.createServer(async(req,res)=>{
  const u=new URL(req.url,'http://localhost');
  if(u.pathname==='/assets/lesson-constraints.js'){res.writeHead(200,{'Content-Type':'application/javascript; charset=utf-8'});return res.end(asset);}
  if(u.pathname==='/tkb_xep.php'){
   let body='';for await(const chunk of req)body+=chunk;
   posts.push(new URLSearchParams(body));
   const action=posts.at(-1).get('action');
   const panel=action.startsWith('special')?'special':action.startsWith('double')?'double':action.startsWith('subject')?'sequence':'fixed';
   res.writeHead(303,{Location:'/chuyenmon/tkb_lesson_constraints.php?panel='+panel});return res.end();
  }
  if(u.pathname==='/chuyenmon/tkb_lesson_constraints.php'){
   const body=html.replace('var requestedPanel="special"','var requestedPanel='+JSON.stringify(u.searchParams.get('panel')||'special'));
   res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'});return res.end(body);
  }
  res.writeHead(404);res.end();
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 const origin='http://127.0.0.1:'+server.address().port;
 await page.route('**/*',route=>new URL(route.request().url()).origin===origin?route.continue():route.abort());
 await page.goto(origin+'/chuyenmon/tkb_lesson_constraints.php?panel=special');
 const first='.slot-grid td[data-day="2"][data-session="Sáng"][data-period="2"]';
 const second='.slot-grid td[data-day="3"][data-session="Chiều"][data-period="4"]';
 await page.locator(first).click();await page.locator(second).click();
 assert.equal(await page.locator('.slot-grid td.selected').count(),2);
 await page.locator(first).click();assert.equal(await page.locator('.slot-grid td.selected').count(),1);
 await page.locator(first).click();
 await page.locator('#specialUseClasses').check();
 await page.locator('#specialClasses [value="6A"]').check();
 await page.locator('#specialClasses [value="6B"]').check();
 await page.locator('#specialPanel input[name="label"]').fill('Chào cờ');
 await page.locator('#specialPanel .slot-submit').scrollIntoViewIfNeeded();
 const before=await page.evaluate(()=>scrollY);
 await Promise.all([page.waitForNavigation({waitUntil:'load'}),page.locator('#specialPanel .slot-submit').click()]);
 await page.waitForLoadState('load');
 assert.equal(posts.length,1);
 assert.deepEqual(posts[0].getAll('classes[]'),['6A','6B']);
 assert.equal(posts[0].get('scope'),'classes');
 assert.equal(posts[0].get('slots[0][day]'),'3');
 assert.equal(posts[0].get('slots[1][day]'),'2');
 assert.equal(posts[0].get('return_constraints'),'1');
 assert.equal(await page.locator('#specialPanel').evaluate(e=>e.style.display),'block');
 assert.equal(await page.locator('#specialUseClasses').isChecked(),true);
 assert.equal(await page.locator('#specialClasses [value="6A"]').isChecked(),true);
 assert.equal(await page.locator('#specialPanel input[name="label"]').inputValue(),'Chào cờ');
 assert.equal(await page.locator('.slot-grid td.selected').count(),2);
 assert.ok(Math.abs(await page.evaluate(()=>scrollY)-before)<100,'scroll position restored');
 await page.locator('#specialAllClasses').check();
 assert.equal(await page.locator('#specialClasses [name="classes[]"]:checked').count(),3);
 await page.locator('#specialClearSlots').click();
 assert.equal(await page.locator('#specialPanel .slot-submit').isDisabled(),true);
 // Fixed lessons still select exactly one slot; no multi-slot payload.
 await page.locator('[data-bs-target="#fixedPanel"]').click();
 await page.locator(first).click();await page.locator(second).click();
 assert.equal(await page.locator('.slot-grid td.selected').count(),1);
 assert.equal(await page.locator('#fixedPanel input[name="day"]').inputValue(),'3');
 assert.equal(await page.locator('#fixedPanel input[name="period"]').inputValue(),'4');
 // All tabs update URL and display without a reload or data request.
 await page.locator('[data-bs-target="#doublePanel"]').click();
 assert.equal(new URL(page.url()).searchParams.get('panel'),'double');
 await page.locator('[data-bs-target="#sequencePanel"]').click();
 assert.equal(await page.locator('#sequencePanel').evaluate(e=>e.style.display),'block');
 // Legacy school/grade mode remains available.
 await page.locator('[data-bs-target="#specialPanel"]').click();
 await page.locator('#specialUseClasses').uncheck();
 await page.locator('#specialPanel .scope-select').selectOption('grade');
 assert.equal(await page.locator('#specialPanel .grade-value').evaluate(e=>e.classList.contains('show')),true);
 assert.equal(await page.locator('#specialPanel .scope-select').isDisabled(),false);
 console.log('PASS browser: multi-slot toggle, selected/all classes, exact POST payload, restored tab/scroll/form/selections, fixed single-slot and legacy scope compatibility');
 }finally{await browser.close();if(server)await new Promise(resolve=>server.close(resolve));}
})().catch(e=>{console.error(e);process.exit(1);});

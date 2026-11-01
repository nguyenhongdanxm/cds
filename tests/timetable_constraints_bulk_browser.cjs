const fs=require('node:fs'),http=require('node:http'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
setTimeout(()=>{console.error('Browser timeout');process.exit(1);},45000).unref();
(async()=>{
 let server;const browser=await chromium.launch({headless:true});
 try{
 const page=await browser.newPage();let posts=[];
 const files={
 '/lesson.html':fs.readFileSync('/tmp/lesson-constraints.html','utf8').replace(/<script src="https:[^"]+"[^>]*><\/script>/g,''),
 '/summary.html':fs.readFileSync('/tmp/constraint-summary.html','utf8')+'<script src="/assets/timetable-constraints-bulk.js"></script>',
 '/preview.html':fs.readFileSync('/tmp/teacher-preview.html','utf8')+'<script src="/preview.js"></script>',
 '/preview.js':fs.readFileSync('/tmp/teacher-preview.js','utf8')+';for(var n=0;n<5000;n++)allEntries.push({teacher:"Other "+n,classes:["X"],subject:"X",day:2,session:"Sáng",period:1});renderTeacherPreview(document.getElementById("teacherPreviewSelect").value);document.getElementById("teacherPreviewSelect").addEventListener("change",function(){renderTeacherPreview(this.value)});'
 };
 server=http.createServer(async(req,res)=>{
  const u=new URL(req.url,'http://localhost');let body=files[u.pathname];
  if(u.pathname.startsWith('/assets/'))body=fs.readFileSync('.'+u.pathname,'utf8');
  if(u.pathname==='/tkb_xep.php'){let input='';for await(const c of req)input+=c;posts.push(new URLSearchParams(input));body='<meta charset="utf-8">Đã nhận';}
  if(body===undefined)body='<meta charset="utf-8">Lớp đích';
  res.writeHead(200,{'Content-Type':u.pathname.endsWith('.js')?'application/javascript; charset=utf-8':'text/html; charset=utf-8'});res.end(body);
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const origin='http://127.0.0.1:'+server.address().port;
 await page.route('**/*',r=>new URL(r.request().url()).origin===origin?r.continue():r.abort());
 await page.goto(origin+'/summary.html');
 const form=page.locator('.constraint-bulk-form'),button=form.locator('[data-bulk-submit]');
 assert.equal(await button.isDisabled(),true);
 await form.locator('[data-bulk-all]').check();
 const total=await form.locator('[name="selected_constraints[]"]:checked').count();assert.ok(total>=8);
 assert.equal(await button.textContent(),'Xóa đã chọn ('+total+')');
 page.once('dialog',d=>d.dismiss());await button.click();assert.equal(posts.length,0);
 page.once('dialog',d=>d.accept());await Promise.all([page.waitForNavigation(),button.click()]);
 assert.equal(posts.length,1);assert.equal(posts[0].getAll('selected_constraints[]').length,total);
 assert.equal(posts[0].get('confirm_bulk'),'1');
 await page.goto(origin+'/lesson.html');
 const special=page.locator('#specialPanel .constraint-bulk-form');assert.equal(await special.count(),1);
 await special.locator('[data-bulk-all]').check();
 assert.equal(await special.locator('[data-bulk-submit]').textContent(),'Xóa đã chọn (1)');
 assert.equal(await page.locator('#specialPanel input[name="selected_constraints[]"]:checked').count(),1);
 page.once('dialog',d=>d.accept());await Promise.all([page.waitForNavigation(),special.locator('[data-bulk-submit]').click()]);
 assert.equal(posts[1].get('bulk_panel'),'special');
 assert.equal(JSON.parse(posts[1].getAll('selected_constraints[]')[0]).field,'special_slots');
 // Actual production matrix and preview function: initial buttons work without changing teacher first.
 await page.goto(origin+'/preview.html?mode=class&class=6A');
 assert.equal(await page.locator('.teacher-preview-lesson').count(),2);
 const started=Date.now();
 for(let n=0;n<10;n++){await page.locator('#teacherPreviewSelect').selectOption('GV B');assert.equal(await page.locator('.teacher-preview-lesson').count(),1);await page.locator('#teacherPreviewSelect').selectOption('GV A');}
 assert.ok(Date.now()-started<5000,'indexed preview responds promptly with 5000 other teachers');
 await page.locator('.teacher-preview-lesson').filter({hasText:'6A'}).click();
 assert.equal(new URL(page.url()).pathname,'/preview.html');
 assert.match(await page.locator('.class-editor-card .lesson').getAttribute('style'),/outline/);
 await Promise.all([page.waitForURL('**/*class=6B*'),page.locator('.teacher-preview-lesson').filter({hasText:'6B'}).click()]);
 assert.equal(new URL(page.url()).searchParams.get('teacher'),'GV A');
 console.log('PASS bulk summary/lesson checkbox counts, confirm cancel/accept and exact tokens; initial and indexed teacher preview, same-class highlight and corresponding-class navigation');
 }finally{await browser.close();if(server)await new Promise(r=>server.close(r));}
})().catch(e=>{console.error(e);process.exit(1);});

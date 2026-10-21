const {chromium}=require('playwright');const cp=require('node:child_process'),fs=require('node:fs'),assert=require('node:assert/strict');
const html=cp.execFileSync(process.env.PHP_BINARY||'php',[__dirname+'/support/student_cards_selection_fixture.php'],{encoding:'utf8'});
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
 await page.addInitScript(()=>{window.QRCode=class{};QRCode.CorrectLevel={M:0};});
 const rows=[{id:'a',name:'Nguyễn An',class_name:'6A',code:'0001',photo_url:'',verify_url:'',printed:false},{id:'b',name:'Trần Bình',class_name:'7A',code:'0002',photo_url:'',verify_url:'',printed:false}];let submitted=[];
 await page.route('**/*',async route=>{const u=new URL(route.request().url());if(u.host!=='fixture.test')return route.fulfill({body:'',contentType:u.pathname.endsWith('.css')?'text/css':'text/javascript'});
   if(u.pathname==='/student_card_students.php'){const q=u.searchParams.get('q')||'';if(q==='slow')await new Promise(r=>setTimeout(r,250));const found=q==='slow'?[rows[0]]:rows.filter(s=>s.name.toLowerCase().includes(q.toLowerCase()));return route.fulfill({json:{ok:true,students:found}});}
   if(route.request().method()==='POST'){const data=route.request().postDataJSON();submitted=data.student_ids;rows.forEach(s=>{if(submitted.includes(s.id))s.printed=data.action==='mark';});return route.fulfill({json:{ok:true,printed:Object.fromEntries(rows.filter(s=>s.printed).map(s=>[s.id,{printed_at:'2026-10-21T10:00:00',printed_by:'GV'}]))}});}
   return route.fulfill({body:html,contentType:'text/html'});
 });
 await page.goto('http://fixture.test/csdl_student_cards.php');await page.locator('#studentsBtn').click();
 async function search(q){await page.locator('#search').fill(q);await page.locator('#search').press('Enter');await page.waitForFunction(()=>!document.getElementById('status').textContent.includes('Đang tải'));}
 await search('An');await page.locator('.stu[value="a"]').check();assert.equal(await page.locator('#count').textContent(),'1');
 await search('Bình');await page.locator('.stu[value="b"]').check();assert.equal(await page.locator('#count').textContent(),'2');assert.equal(await page.locator('#printGrid').evaluate(el=>el.children.length),2,'Both cached students included in print output');
 await search('An');assert.equal(await page.locator('.stu[value="a"]').isChecked(),true,'Restores check when result returns');await page.locator('#all').click();assert.equal(await page.locator('#count').textContent(),'2','Select visible preserves hidden selection');
 await page.locator('#selectedList [data-remove-student="b"]').click();assert.equal(await page.locator('#count').textContent(),'1');await search('Bình');await page.locator('.stu[value="b"]').check();
 await page.locator('#printFilter').selectOption('printed');assert.equal(await page.locator('.stu').count(),0);assert.equal(await page.locator('#count').textContent(),'2','Status filter retains selection');
 await page.locator('#markPrinted').click();await page.waitForFunction(()=>document.getElementById('batchHint').textContent.includes('Đã cập nhật'));
 assert.deepEqual(submitted,['a','b'],'Mark submits selected IDs across multiple searches');assert.equal(await page.evaluate(()=>selectedStudents().every(s=>s.printed)),true,'Hidden selected metadata refreshed');
 await page.locator('#printFilter').selectOption('all');
 await page.evaluate(async()=>{document.getElementById('search').value='slow';const old=loadStudents();document.getElementById('search').value='Bình';const fresh=loadStudents();await Promise.all([old,fresh]);});assert.equal(await page.locator('.stu').getAttribute('value'),'b','Stale response cannot replace newer search');
 fs.mkdirSync('/tmp/student-card-selection',{recursive:true});await page.screenshot({path:'/tmp/student-card-selection/selection.png',fullPage:true});
 await page.locator('#clear').click();assert.equal(await page.locator('#count').textContent(),'0');assert.equal(await page.locator('#selectedList button').count(),0,'Clear removes all selections');
 assert.deepEqual(errors,[]);console.log('PASS browser: cumulative search selection, restored checks, hidden removal, filters, printing, mark-all IDs, metadata refresh, request races and clear-all');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});

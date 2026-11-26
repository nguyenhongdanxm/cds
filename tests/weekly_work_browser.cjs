const {chromium}=require('playwright'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),{execFileSync,spawn}=require('node:child_process'),assert=require('node:assert/strict');
(async()=>{const dir=fs.mkdtempSync(path.join(os.tmpdir(),'weekly-work-browser-'));let browser,server;try{
 const php=process.env.PHP_BIN||'php';execFileSync(php,['tests/weekly_work_fixture.php',dir]);server=spawn(php,['-S','127.0.0.1:18769','-t',dir]);
 browser=await chromium.launch();const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:18769/admin.php?test_role=teacher');
 await page.locator('[name=date]').fill('2026-11-25');await page.locator('[name=time]').fill('07:30');await page.locator('[name=content]').fill('Họp chuyên môn <script>window.injected=1</script>');await page.locator('[name=people]').fill('Tổ chuyên môn');await page.locator('[name=place]').fill('Phòng họp');await page.getByRole('button',{name:'Lưu công việc',exact:true}).click();
 assert.equal(await page.locator('.ww-content').count(),1);assert.match(await page.locator('.ww-content').innerText(),/Họp chuyên môn/);assert.equal(await page.evaluate(()=>window.injected),undefined);
 assert.equal(await page.getByRole('button',{name:'Xóa',exact:true}).count(),0,'Edit permission cannot delete');
 await page.getByRole('link',{name:'Sửa',exact:true}).click();await page.locator('[name=content]').fill('Nội dung đã sửa');await page.getByRole('button',{name:'Lưu công việc',exact:true}).click();assert.equal(await page.locator('.ww-content').innerText(),'Nội dung đã sửa');
 await page.getByRole('link',{name:'Tuần sau ›',exact:true}).click();assert.equal(await page.locator('.ww-content').count(),0,'Neighbor week excludes entry');
 await page.goto('http://127.0.0.1:18769/admin.php?test_role=viewer');assert.equal(await page.locator('.ww-editor').count(),0);assert.equal(await page.locator('.ww-content').count(),1);
 const denied=await page.request.post('http://127.0.0.1:18769/admin.php?view=weekly_schedule',{form:{work_action:'save',content:'forged'}});assert.equal(denied.status(),403);
 for(const width of [1440,800,390]){await page.setViewportSize({width,height:1100});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Calendar fits viewport '+width);}
 await page.goto('http://127.0.0.1:18769/admin.php?schedule_week=2026-11-23');assert.equal(await page.locator('#weeklyWorkTitle').innerText(),'Lịch công tác tuần');assert.equal(await page.getByRole('link',{name:'Nhập lịch',exact:true}).count(),0);assert.equal(await page.locator('.ww-content').count(),1);
 await page.goto('http://127.0.0.1:18769/admin.php?test_role=admin');page.on('dialog',d=>d.accept());await page.getByRole('button',{name:'Xóa',exact:true}).click();assert.equal(await page.locator('.ww-content').count(),0);assert.deepEqual(errors,[]);
 console.log('PASS weekly calendar browser: create/edit/delete, roles, forged POST denial, inline escaping, week navigation, dashboard and mobile layout');
}finally{if(browser)await browser.close();if(server)server.kill();fs.rmSync(dir,{recursive:true,force:true});}})().catch(e=>{console.error(e);process.exit(1)});

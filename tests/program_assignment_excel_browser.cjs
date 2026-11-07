const {chromium}=require('playwright'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),{spawn,execFileSync}=require('node:child_process'),assert=require('node:assert/strict');
(async()=>{
const dir=fs.mkdtempSync(path.join(os.tmpdir(),'cds-gdp-excel-http-')),env={...process.env,GDP_EXCEL_TEST_DIR:dir},fixture=path.join(__dirname,'program_assignment_excel_http_fixture.php');
let server,browser;try{
 execFileSync('php',[fixture,'--init'],{env});const port=await new Promise(resolve=>{const s=net.createServer().listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 server=spawn('php',['-S',`127.0.0.1:${port}`,fixture],{env,stdio:['ignore','pipe','pipe']});let logs='';server.stderr.on('data',c=>logs+=c);
 const base=`http://127.0.0.1:${port}`;for(let i=0;i<50;i++){try{if((await fetch(base+'/state')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
 browser=await chromium.launch({headless:true});const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/them.php?program=1');await page.locator('#gdpExcelImport>summary').click();
 const response=await page.request.get(base+'/program_assignment_template.php');assert.equal(response.status(),200);assert.equal((await response.body()).subarray(0,2).toString(),'PK','Real template download is XLSX');
 const initial=await (await page.request.get(base+'/state')).json();
 await page.locator('[name="gdp_excel"]').setInputFiles({name:'bad.xlsx',mimeType:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',buffer:Buffer.from('bad')});await page.locator('#gdpExcelUpload button').click();await page.waitForLoadState();assert.match(await page.locator('#gdpExcelImport').textContent(),/Không mở được file XLSX/);assert.deepEqual(await (await page.request.get(base+'/state')).json(),initial);
 await page.locator('[name="gdp_excel"]').setInputFiles(path.join(dir,'valid.xlsx'));await page.locator('#gdpExcelUpload button').click();await page.locator('#gdpExcelCommit').waitFor();assert.match(await page.locator('#gdpExcelPreview').textContent(),/2 dải tiết, 1 lớp/);assert.deepEqual(await (await page.request.get(base+'/state')).json(),initial,'Preview cannot write');
 const token=await page.locator('#gdpExcelCommit [name="preview_token"]').inputValue();
 const post={action:'program_import_commit',version_id:'v1',csrf:'test-token',preview_token:token};
 for(const patch of [{csrf:'bad'},{version_id:'v2'},{preview_token:'bad'}]){const r=await page.request.post(base+'/them.php?program=1',{form:{...post,...patch}});assert.match(await r.text(),/hết hạn|đã đổi/);assert.deepEqual(await (await page.request.get(base+'/state')).json(),initial);}
 for(const width of [1440,390,320]){await page.setViewportSize({width,height:900});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Excel preview fits '+width);}
 page.once('dialog',d=>d.accept());await page.locator('#gdpExcelCommit button').click();await page.waitForURL('**/them.php?program=1');await page.waitForLoadState();
 const saved=await (await page.request.get(base+'/state')).json();assert.equal(saved['8A'].ranges[0].teacher,'Giáo viên B');assert.equal(saved['8A'].ranges[0].to,7);assert.deepEqual(saved['8B'],initial['8B'],'Unmentioned class unchanged');
 const replay=await page.request.post(base+'/them.php?program=1',{form:post});assert.match(await replay.text(),/Bản xem trước hết hạn/);assert.deepEqual(await (await page.request.get(base+'/state')).json(),saved,'Confirmed preview cannot replay');
 await page.request.get(base+'/readonly');const denied=await page.request.post(base+'/them.php?program=1',{form:post});assert.match(await denied.text(),/chưa có quyền/);assert.deepEqual(await (await page.request.get(base+'/state')).json(),saved);
 assert.deepEqual(errors,[]);assert.ok(!logs.includes('Fatal error'),logs);console.log('PASS real GDĐP XLSX upload/preview/confirmation: no preview writes, codes, selected-class update, CSRF, stale version/token, replay, view-only and responsive layout');
}finally{if(browser)await browser.close();if(server){server.kill();await new Promise(resolve=>server.once('exit',resolve));}fs.rmSync(dir,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exit(1)});

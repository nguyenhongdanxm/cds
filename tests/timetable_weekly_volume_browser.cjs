const {chromium}=require('playwright'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),{spawn,execFileSync}=require('node:child_process'),assert=require('node:assert/strict');
(async()=>{
 const dir=fs.mkdtempSync(path.join(os.tmpdir(),'cds-volume-')),env={...process.env,CDS_VOLUME_FIXTURE:dir},fixture=path.join(__dirname,'timetable_weekly_volume_fixture.php');
 let server,browser;try{
  execFileSync('php',[fixture,'--init'],{env});
  const port=await new Promise(resolve=>{const s=net.createServer().listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
  server=spawn('php',['-S',`127.0.0.1:${port}`,fixture],{env,stdio:['ignore','pipe','pipe']});let logs='';server.stderr.on('data',c=>logs+=c);
  const base=`http://127.0.0.1:${port}`;for(let n=0;n<50;n++){try{if((await fetch(base+'/state')).ok)break;}catch{}await new Promise(resolve=>setTimeout(resolve,100));}
  browser=await chromium.launch({headless:true,...(process.env.CDS_TEST_CHROMIUM?{executablePath:process.env.CDS_TEST_CHROMIUM}:{})});
  const page=await browser.newPage({viewport:{width:1280,height:850}}),errors=[];page.on('pageerror',error=>errors.push(error.message));
  const panel=()=>page.locator('#ttb-weekly-volume'),filter=()=>panel().locator('[data-volume-filter]');
  const state=async()=> (await page.request.get(base+'/state')).json();
  const offset=async()=>panel().evaluate(node=>node.getBoundingClientRect().top);
  const gdpInput=()=>panel().locator('tr').filter({hasText:'GDĐP'}).locator('input[type=number]');
  await page.goto(base+'/tkb_xep.php?tab=data');const before=await state();
  await panel().evaluate(node=>window.scrollTo(0,node.offsetTop-160));
  await panel().locator('[data-volume-table]').evaluate(node=>node.scrollTop=350);
  const top=await offset();await page.evaluate(()=>window.volumeDocumentMarker='same-document');
  async function choose(name,value){await filter().locator(`[name=${name}]`).selectOption(value);await page.waitForFunction(()=>!document.getElementById('ttb-weekly-volume').hasAttribute('aria-busy'));}
  await choose('scope','class');assert.equal(await page.evaluate(()=>window.volumeDocumentMarker),'same-document','Changing scope must not reload the document');assert.ok(Math.abs(await offset()-top)<3,'Scope keeps panel at same screen position');assert.equal(await panel().getAttribute('data-target'),'6A');
  await choose('target','6B');assert.ok(Math.abs(await offset()-top)<3,'Class filter keeps screen position');assert.equal(await panel().getAttribute('data-target'),'6B');
  await page.goBack();await page.waitForFunction(()=>document.getElementById('ttb-weekly-volume').dataset.target==='6A'&&!document.getElementById('ttb-weekly-volume').hasAttribute('aria-busy'));assert.ok(Math.abs(await offset()-top)<3,'Back restores filter without scrolling to page top');
  await gdpInput().fill('0');await panel().locator('[data-volume-table]').evaluate(node=>node.scrollTop=220);const savedScroll=await panel().locator('[data-volume-table]').evaluate(node=>node.scrollTop);
  await panel().locator('[data-volume-save]').first().locator('button').scrollIntoViewIfNeeded();const saveTop=await offset();await panel().locator('[data-volume-save]').first().locator('button').click();await page.waitForURL(/scope=class&target=6A/);await page.waitForLoadState('load');await page.waitForFunction(y=>Math.abs(document.querySelector('[data-volume-table]').scrollTop-y)<2,savedScroll);
  assert.ok(Math.abs(await offset()-saveTop)<3,'Saving keeps the panel screen position');assert.equal(await gdpInput().inputValue(),'0','GDĐP zero remains in the re-rendered actual view');
  let saved=await state();const gdp=cls=>saved.assignments.find(a=>a.class===cls&&a.subject==='GDĐP');assert.equal(gdp('6A').periods,0);assert.equal(gdp('6A').base_periods,1);assert.equal(saved.activities.filter(a=>a.classes.includes('6A')&&a.subject==='GDĐP').length,0,'No generated lesson for zero');
  page.once('dialog',dialog=>dialog.accept());await panel().locator('form').filter({has:page.locator('[name=action][value=period_overrides_copy_grade]')}).locator('button').click();await page.waitForLoadState('load');saved=await state();assert.equal(gdp('6B').periods,0,'Copy to grade includes GDĐP zero');assert.equal(gdp('9A').periods,1,'Other grade unchanged');
  await choose('scope','grade');assert.equal(await panel().getAttribute('data-target'),'6');await gdpInput().fill('1');await panel().locator('[data-volume-save] button').click();await page.waitForLoadState('load');saved=await state();assert.equal(gdp('6A').periods,1);assert.equal(gdp('6B').periods,1);
  await gdpInput().fill('0');await panel().locator('[data-volume-save] button').click();await page.waitForLoadState('load');saved=await state();assert.equal(gdp('6A').periods,0);assert.equal(gdp('6B').periods,0,'Grade save sets all matching GDĐP rows to zero');
  assert.equal(saved.source_hash,before.source_hash,'PCCM source stays byte-for-byte unchanged');assert.deepEqual(saved.data.plans,before.data.plans,'Existing plans are not rewritten');assert.deepEqual(errors,[]);assert.ok(!logs.includes('Fatal error'),logs);
  console.log('PASS actual weekly-volume view/actions: no-reload filtering/back, stable viewport, save/table scroll restoration, GDĐP zero, no generated lesson, grade save/copy, unchanged PCCM and existing plans');
 }finally{if(browser)await browser.close();if(server){server.kill();await new Promise(resolve=>server.once('exit',resolve));}fs.rmSync(dir,{recursive:true,force:true});}
})().catch(error=>{console.error(error);process.exit(1)});

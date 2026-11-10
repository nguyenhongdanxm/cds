const {chromium}=require('playwright'),fs=require('node:fs'),cp=require('node:child_process'),assert=require('node:assert/strict');
(async()=>{
 const root=process.cwd(),php=process.env.PHP_BINARY||'php';
 const html=cp.execFileSync(php,['tests/noitru_layout_fixture.php'],{encoding:'utf8'});
 for(const source of ['noitru.php','noitru_list.php','noitru_assign.php','includes/noitru_att_view.php']){
  const text=fs.readFileSync(source,'utf8');assert.match(text,/assets\/noitru-layout\.css\?v=20261110-1/);assert.doesNotMatch(text,/includes\/noitru_layout\.css/);
 }
 assert.match(fs.readFileSync('.htaccess','utf8'),/RewriteRule \^\(\?:includes\|/,'Private includes remain protected');
 const browser=await chromium.launch({headless:true,...(process.env.CDS_CHROMIUM?{executablePath:process.env.CDS_CHROMIUM}:{})});
 try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://mobile.test/**',route=>{const path=new URL(route.request().url()).pathname;
  if(path==='/bootstrap.css')return route.fulfill({contentType:'text/css',body:fs.readFileSync(require.resolve('bootstrap/dist/css/bootstrap.min.css'),'utf8')});
  if(path==='/school/assets/noitru-layout.css')return route.fulfill({contentType:'text/css',body:fs.readFileSync(root+'/assets/noitru-layout.css','utf8')});
  if(path.includes('/includes/'))return route.fulfill({status:403});
  return route.fulfill({contentType:'text/html; charset=utf-8',body:html});
 });
 for(const width of [320,390,430,768]){
  await page.setViewportSize({width,height:844});await page.goto('https://mobile.test/fixture');
  assert.equal(await page.locator('.nt-sidebar').isVisible(),false);assert.equal(await page.locator('.nt-bottom-nav').isVisible(),true);
  assert.equal(await page.locator('.nt-sheet:visible').count(),0,'Submenus hidden before interaction');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow at '+width);
  for(const key of ['boarding','meals']){
   await page.locator('.nt-bottom-nav [data-nt-sheet="'+key+'"]').click();await page.locator('[data-nt-panel="'+key+'"]').waitFor({state:'visible'});
   await page.waitForFunction(key=>getComputedStyle(document.querySelector('[data-nt-panel="'+key+'"]')).opacity==='1',key);
   assert.equal(await page.locator('.nt-sheet:visible').count(),1);assert.equal(await page.locator('[data-nt-panel="'+key+'"] a').count(),4);
   const sheet=await page.locator('[data-nt-panel="'+key+'"]').boundingBox(),nav=await page.locator('.nt-bottom-nav').boundingBox();assert.ok(sheet.y+sheet.height<=nav.y+1,'Menu stays above navigation');
   await page.locator('[data-nt-panel="'+key+'"] [data-nt-close]').click();await page.locator('[data-nt-panel="'+key+'"]').waitFor({state:'hidden'});
  }
 }
 if(process.env.CDS_SCREENSHOT){await page.setViewportSize({width:390,height:844});await page.screenshot({path:process.env.CDS_SCREENSHOT,fullPage:true});}
 await page.setViewportSize({width:1440,height:900});assert.equal(await page.locator('.nt-sidebar').isVisible(),true);assert.equal(await page.locator('.nt-bottom-nav').isVisible(),false);
 assert.deepEqual(errors,[]);console.log('PASS public stylesheet references, private includes protected, mobile 320/390/430/768 menus and close controls, no overflow, desktop sidebar; no school data loaded');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});

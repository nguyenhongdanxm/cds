const {chromium}=require('playwright'),fs=require('node:fs'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://gdp.test/**',route=>route.fulfill({contentType:route.request().url().endsWith('.css')?'text/css':'text/html',body:route.request().url().endsWith('.css')?fs.readFileSync(require.resolve('bootstrap/dist/css/bootstrap.min.css'),'utf8'):fs.readFileSync('/tmp/gdp-input.html','utf8')}));
 await page.goto('https://gdp.test/');const save=page.locator('#gdpSave');assert.equal(await save.isDisabled(),true);
 await page.locator('[name="classes[]"]').nth(0).check();await page.locator('[name="classes[]"]').nth(1).check();await page.locator('[name="teacher[]"]').selectOption('Giáo viên A');assert.equal(await save.isEnabled(),true);
 await page.locator('[name="to[]"]').fill('10');assert.equal(await save.isDisabled(),true);await page.locator('#gdpAdd').click();assert.equal(await page.locator('[name="from[]"]').nth(1).inputValue(),'11');
 await page.locator('[name="teacher[]"]').nth(1).selectOption('Giáo viên B');assert.equal(await save.isEnabled(),true);assert.match(await page.locator('#gdpCheck').textContent(),/35\/35 tiết · 2 lớp/);
 await page.locator('[name="from[]"]').nth(1).fill('10');assert.equal(await save.isDisabled(),true);assert.equal(await page.locator('.gdp-periods .duplicate').count(),1);
 await page.locator('[name="from[]"]').nth(1).fill('12');assert.equal(await page.locator('.gdp-periods .missing').count(),1);assert.equal(await save.isDisabled(),true);
 await page.locator('[name="from[]"]').nth(1).fill('11');assert.equal(await save.isEnabled(),true);
 for(const width of [1440,390,320]){await page.setViewportSize({width,height:900});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Form fits viewport '+width);}
 await page.locator('[data-remove]').nth(1).click();assert.equal(await save.isDisabled(),true);assert.equal(await page.locator('.gdp-range select').count(),1);
 assert.deepEqual(errors,[]);console.log('PASS GDĐP browser: multiple classes, quick ranges, complete/overlap/gap validation, removal, desktop and mobile fit');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});

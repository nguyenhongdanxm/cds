const {chromium}=require('playwright');const fs=require('node:fs');const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}});let imageEndpointCalls=0;
 await page.route('**/*',route=>{if(route.request().url().includes('teacher_signature.php'))imageEndpointCalls++;return route.fulfill({status:404,body:'Fixture: separate endpoints unavailable'});});
 await page.setContent(fs.readFileSync('/tmp/teacher-signature-preview.html','utf8'));const preview=page.locator('#teacherSignaturePreview');
 await preview.evaluate(img=>img.decode());assert.deepEqual(await preview.evaluate(img=>[img.naturalWidth,img.naturalHeight]),[480,160],'Real browser decodes the inline signature');
 assert.equal(await preview.getAttribute('src').then(s=>s.startsWith('data:image/png;base64,')),true);assert.equal(imageEndpointCalls,0,'Preview works even when separate image endpoint is missing');
 assert(await preview.isVisible());assert(await page.locator('[data-equipment-signature-guide]').isVisible());
 const open=page.locator('[data-equipment-loan="open"]');assert(await open.locator('[data-equipment-sign-button="borrow"]').isEnabled());assert(await open.locator('[data-equipment-sign-button="return"]').isDisabled());assert((await open.innerText()).includes('Chưa xác nhận trả thiết bị'));
 const returned=page.locator('[data-equipment-loan="returned"]');assert(await returned.locator('[data-equipment-sign-button="return"]').isEnabled());
 const other=page.locator('[data-equipment-loan="other"]');await other.locator('xpath=ancestor::details').locator('summary').click();assert(await other.locator('[data-equipment-sign-button="borrow"]').isVisible());assert(await other.locator('[data-equipment-sign-button="borrow"]').isDisabled());assert((await other.innerText()).includes('Chỉ giáo viên đứng tên mượn được ký'));
 await page.screenshot({path:'/tmp/teacher-signature-preview.png',fullPage:true});console.log('PASS Chromium: inline signature decodes with unavailable endpoints; own sign buttons visible, returned-only confirmation and clear disabled reasons');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1)});

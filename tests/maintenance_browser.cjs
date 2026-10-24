const {chromium}=require('playwright');
const fs=require('node:fs'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{
const page=await browser.newPage({viewport:{width:1280,height:1000}});
await page.setContent(fs.readFileSync('/tmp/maintenance-view.html','utf8'));
assert.equal(await page.locator('.mtGroup').count(),3);
await page.locator('#mtAll').check();assert.equal(await page.locator('.mtGroup:checked').count(),3);
await page.locator('.mtGroup').first().uncheck();assert.equal(await page.locator('#mtAll').isChecked(),false);assert.equal(await page.locator('#mtAll').evaluate(el=>el.indeterminate),true);
await page.setContent(fs.readFileSync('/tmp/maintenance-edit.html','utf8'));assert.equal(await page.locator('#mtAll').isDisabled(),true);await page.locator('#mtChangeScope').check();assert.equal(await page.locator('#mtAll').isDisabled(),false);
await page.setContent(fs.readFileSync('/tmp/maintenance-report.html','utf8'));await page.emulateMedia({media:'print'});
assert.equal(await page.locator('.toolbar').isVisible(),false);assert.equal(await page.locator('script').count(),0);assert.equal(await page.locator('tbody tr').count(),2);assert.equal(await page.locator('body').innerText().then(t=>t.includes('PRIVATE DEVICE')),false);
fs.mkdirSync('/tmp/maintenance-preview',{recursive:true});await page.screenshot({path:'/tmp/maintenance-preview/report.png',fullPage:true});
const pdf=await page.pdf({path:'/tmp/maintenance-preview/report.pdf',preferCSSPageSize:true,printBackground:true});
const sizes=[...pdf.toString('latin1').matchAll(/\/MediaBox\s*\[\s*0\s+0\s+([\d.]+)\s+([\d.]+)\s*\]/g)];assert(sizes.length>0);sizes.forEach(m=>{assert(Math.abs(Number(m[1])-595.28)<2);assert(Math.abs(Number(m[2])-841.89)<2);});
console.log('PASS Chromium: multi-group/all selection, explicit scope edits, escaped group-only report and A4 portrait PDF');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});

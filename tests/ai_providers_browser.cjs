const {chromium}=require('playwright');const fs=require('node:fs'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{const page=await browser.newPage();await page.setContent(fs.readFileSync('/tmp/ai-providers-fixture.html','utf8'));
assert.equal(await page.locator('[data-provider-panel]:visible').count(),4);
for(const el of await page.locator('[data-provider-toggle]').all())await el.uncheck();assert.equal(await page.locator('#aiProvider option:not([disabled])').count(),0);
await page.locator('#use-openai').check();await page.locator('#use-gemini').check();await page.locator('#aiProvider').selectOption('gemini');assert.equal(await page.locator('#aiProvider option:not([disabled])').count(),2);
await page.locator('#use-gemini').uncheck();assert.equal(await page.locator('#aiProvider').inputValue(),'openai');
await page.locator('#key-gemini').fill('NEW_UNSAVED_FIXTURE');await page.locator('#model-gemini').fill('custom-model');await page.locator('#use-gemini').check();await page.locator('#use-gemini').uncheck();assert.equal(await page.locator('#key-gemini').inputValue(),'NEW_UNSAVED_FIXTURE');assert.equal(await page.locator('#model-gemini').inputValue(),'custom-model');
assert.equal(await page.locator('[name=provider_selection_present]').inputValue(),'1');assert.equal(await page.locator('#key-openai').inputValue(),'');
console.log('PASS Chromium: four visible editable API cards, multiple toggles, allowed default and preserved edits');}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});

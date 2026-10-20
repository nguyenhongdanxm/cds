const {chromium}=require('playwright');
const fs=require('node:fs'),assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:1280,height:900}});await page.setContent(fs.readFileSync('/tmp/equipment-print-fixture.html','utf8'));
 await page.emulateMedia({media:'print'});fs.mkdirSync('/tmp/equipment-print-preview',{recursive:true});
 await page.screenshot({path:'/tmp/equipment-print-preview/layout.png',fullPage:true});
 const pdf=await page.pdf({path:'/tmp/equipment-print-preview/layout.pdf',preferCSSPageSize:true,printBackground:true});
 assert.equal((pdf.toString('latin1').match(/\/Type\s*\/Page\b/g)||[]).length,2,'21 equipment items fit in two A4 landscape pages');
 assert.equal(await page.locator('thead tr:first-child th').count(),12,'Six grouped headings repeated over two sheets');
 console.log('PASS Chromium: grouped table, real print pagination and landscape PDF');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});

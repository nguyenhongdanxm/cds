const {chromium}=require('playwright'),fs=require('node:fs'),os=require('node:os'),path=require('node:path'),assert=require('node:assert/strict'),{spawn,execFileSync}=require('node:child_process');
(async()=>{const dir=fs.mkdtempSync(path.join(os.tmpdir(),'calling-games-')),php=process.env.PHP_BINARY||'php';let server,browser;try{
execFileSync(php,['tests/calling_games_fixture.php',dir]);server=spawn(php,['-S','127.0.0.1:18765','-t',dir],{stdio:'ignore'});for(let i=0;i<40;i++){try{await fetch('http://127.0.0.1:18765/hoclieu_game_goiten.php');break}catch{await new Promise(r=>setTimeout(r,100))}}
browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE?{executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE}:{})});const page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));await page.clock.install();
for(const game of ['egg','ufo']){
 await page.goto('http://127.0.0.1:18765/hoclieu_game_goiten.php?game='+game);await page.locator('#sound').uncheck();
 assert.equal(await page.locator('iframe').count(),0);assert.equal(await page.locator('.pick').count(),0);assert.equal(await page.locator('#classSelect option').count(),3);
 await page.locator('#classSelect').selectOption('12A');assert.match(await page.locator('#total').innerText(),/3 bạn/);assert.equal((await page.locator('#list').inputValue()).match(/Nguyễn An/g).length,2);
 await page.locator('#durRange').evaluate(e=>{e.value='10';e.dispatchEvent(new Event('input',{bubbles:true}))});await page.locator('#go').click();assert.equal(await page.locator('#classSelect').isDisabled(),true);await page.clock.runFor(18000);
 assert.equal(await page.locator('#banner').isVisible(),true);assert.equal(await page.locator('#history li').count(),1);assert.match(await page.locator('#count').innerText(),/2\/3/);
 const first=await page.locator('#bName').innerText();await page.locator('#go').click();await page.clock.runFor(18000);assert.notEqual(await page.locator('#bName').innerText(),first);
 await page.locator('#classSelect').selectOption('12B');assert.match(await page.locator('#total').innerText(),/1 bạn/);assert.equal(await page.locator('#history .empty').count(),1);
 await page.locator('#classSelect').selectOption('12A');assert.equal(await page.locator('#history li').count(),2);await page.reload();assert.equal(await page.locator('#classSelect').inputValue(),'12A');assert.equal(await page.locator('#history li').count(),2);
 await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true);
}
await page.goto('http://127.0.0.1:18765/hoclieu_game_goiten.php?game=egg&user=teacher2');assert.equal(await page.locator('#history .empty').count(),1);
assert.equal((await page.goto('http://127.0.0.1:18765/hoclieu_game_goiten.php?denied=1')).status(),403);assert.equal((await page.goto('http://127.0.0.1:18765/hoclieu_game_goiten.php?guest=1')).status(),401);assert.deepEqual(errors,[]);console.log('PASS both games: real rounds, no repeat, duplicate names retained, class/user history isolation, busy lock, roster, mobile, auth');
}finally{if(browser)await browser.close();if(server)server.kill();fs.rmSync(dir,{recursive:true,force:true})}})().catch(e=>{console.error(e);process.exit(1)});

// Actual PHP state machine and exact UI in a local fixture server; no school config/DB/API.
const {chromium} = require('playwright');
const fs = require('node:fs'), http = require('node:http'), cp = require('node:child_process'), assert = require('node:assert/strict');
const root = require('node:path').resolve(__dirname,'..'), php = process.env.PHP_BINARY || 'php';
const rendered = Object.fromEntries(['host','screen','play'].map(view => [view,cp.execFileSync(php,[root+'/tests/support/buzzer_browser_fixture.php',view],{encoding:'utf8'})]));
function engine(action, params={}) { return JSON.parse(cp.execFileSync(php,[root+'/tests/support/buzzer_browser_state.php'],{input:JSON.stringify({action,...params,now:Date.now()}),encoding:'utf8'})); }
const code='12345678', screenKey='a'.repeat(48);let world=null;let globalAudio={},uploads={};
function snapshot(role,id) { const s=engine('view',{state:{...world,audio:{...(world.audio||{}),...globalAudio}},role,id}).result;s.code=code;if(role==='host')s.screen_key=screenKey;return s; }
const server=http.createServer(async(req,res)=>{
  const url=new URL(req.url,'http://fixture');
  if(url.pathname==='/hoclieu_game_buzzer.php'){res.setHeader('Content-Type','text/html; charset=utf-8');const view=url.searchParams.get('view')||'host';res.end(rendered[view].replace('"code":""','"code":'+JSON.stringify(url.searchParams.get('code')||'')));return;}
  if(['/assets/buzzer.js','/assets/buzzer.css'].includes(url.pathname)){res.setHeader('Content-Type',url.pathname.endsWith('.js')?'text/javascript':'text/css');res.end(fs.readFileSync(root+url.pathname));return;}
  if(url.pathname==='/hoclieu_game_buzzer_api.php'){
    let body='';for await(const chunk of req)body+=chunk;
    let args;if(req.headers['content-type']?.startsWith('multipart/form-data')){args={action:'audio_upload',role:'host'};for(const key of ['kind','code'])args[key]=body.split('name="'+key+'"')[1]?.split('\r\n\r\n')[1]?.split('\r\n')[0];}else args=req.method==='POST'?JSON.parse(body):Object.fromEntries(url.searchParams);let reply;
    if(args.action==='audio'){res.setHeader('Content-Type','audio/wav');res.end(Buffer.from('fixture audio'));return;}
    if(args.action==='audio_settings'){res.setHeader('Content-Type','application/json');res.end(JSON.stringify({ok:true,audio:globalAudio}));return;}
    if(args.action==='audio_begin'){const id=args.kind==='background'?'b'.repeat(48):'c'.repeat(48);uploads[id]={kind:args.kind,name:args.name,offset:0};res.setHeader('Content-Type','application/json');res.end(JSON.stringify({ok:true,upload_id:id}));return;}
    if(args.action==='audio_chunk'){uploads[args.upload_id].offset=args.offset+Buffer.from(args.data,'base64').length;res.setHeader('Content-Type','application/json');res.end(JSON.stringify({ok:true,offset:uploads[args.upload_id].offset}));return;}
    if(args.action==='audio_finish'){const u=uploads[args.upload_id];globalAudio[u.kind]={id:args.upload_id,name:u.name,mime:'audio/wav'};res.setHeader('Content-Type','application/json');res.end(JSON.stringify({ok:true,audio:globalAudio}));return;}
    if(args.action==='list')reply={ok:true,rooms:[]};
    else if(args.action==='create'){world=engine('initial',{title:args.title}).result;reply={ok:true,code,screen_key:screenKey};}
    else if(args.action==='state')reply={ok:true,state:snapshot(args.role,args.player_id)};
    else {
      const input={...args};if(args.action==='press')input.token=req.headers['x-buzzer-token'];
      const result=engine(args.action,{state:world,input});
      if(!result.ok){res.statusCode=result.status||409;reply=result;}
      else {world=result.result;let id=args.player_id;if(args.action==='join'){const hash=require('node:crypto').createHash('sha256').update(args.join_token).digest('hex');id=Object.values(world.players).find(p=>p.key_hash===hash).id;}
        reply={ok:true,state:snapshot(args.action==='join'?'player':args.role,id)};if(args.action==='join')reply.player_id=id;
      }
    }
    res.setHeader('Content-Type','application/json');res.end(JSON.stringify(reply));return;
  }
  res.statusCode=404;res.end();
});
(async()=>{
  await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const origin='http://127.0.0.1:'+server.address().port;
  const browser=await chromium.launch({headless:true});const context=await browser.newContext({viewport:{width:1440,height:1000}});
  const errors=[];const pages=[];const watch=page=>{pages.push(page);page.on('pageerror',e=>errors.push(e.message));};
  const host=await context.newPage();watch(host);const screen=await context.newPage();watch(screen);
  await screen.addInitScript(()=>{window.rings=0;window.customRings=0;window.musicStarts=0;window.musicStops=0;window.decodes=0;window.AudioContext=class {constructor(){this.currentTime=0;this.destination={};}async resume(){}async decodeAudioData(){window.decodes++;return {duration:1};}createBufferSource(){return {loop:false,connect(){},start(){if(this.loop)window.musicStarts++;else window.customRings++;},stop(){if(this.loop)window.musicStops++;}};}createOscillator(){return {frequency:{},connect(){},start(){window.rings++;},stop(){}};}createGain(){return {gain:{setValueAtTime(){},exponentialRampToValueAtTime(){}},connect(){}};}};});
  const students=[];
  fs.mkdirSync('/tmp/buzzer-screenshots',{recursive:true});
  try{
    await host.goto(origin+'/hoclieu_game_buzzer.php');await host.locator('#roomTitle').fill('Đấu trường tri thức');await host.locator('#createForm button').click();
    await host.locator('#roomCode').waitFor();await host.waitForFunction(()=>document.getElementById('roomCode').textContent==='12345678');
    const screenHref=await host.locator('#screenLink').getAttribute('href');assert.ok(screenHref.includes('#code='));
    await screen.goto(origin+screenHref);await screen.locator('#sound').click();assert.equal(await screen.evaluate(()=>window.rings),3,'Audio opt-in test tone');
    for(const name of ['Đội Khám Phá','Đội Tiên Phong','<img src=x onerror=alert(1)>']){
      const ctx=await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});const p=await ctx.newPage();watch(p);students.push(p);
      await p.goto(origin+'/hoclieu_game_buzzer.php?view=play&code='+code);await p.locator('#joinCode').fill(code);await p.locator('#playerName').fill(name);await p.locator('#joinForm button').click();
      await p.locator('#playerPanel').waitFor({state:'visible'});assert.equal(await p.locator('#bell').isDisabled(),true,'No early buzz');
    }
    await host.waitForFunction(()=>document.querySelectorAll('#players .player-card').length===3);
    await host.locator('#newQuestion').click();await host.waitForFunction(()=>document.getElementById('start').disabled===false);await host.locator('#start').click();
    for(const p of students)await p.waitForFunction(()=>document.getElementById('bell').disabled===false);
    await students[0].locator('#bell').click();await screen.waitForFunction(()=>window.rings===6);await screen.waitForFunction(()=>document.querySelectorAll('#players .winner').length===1);assert.equal(await screen.locator('#ringingBell').isVisible(),true);assert.equal(await screen.locator('.stage.buzzer-active').count(),1);
    await students[1].locator('#bell').click();await students[1].waitForFunction(()=>document.getElementById('myRank').textContent.includes('thứ 2'));
    await students[2].locator('#bell').click();await host.waitForFunction(()=>Array.from(document.querySelectorAll('#players .rank')).some(e=>e.textContent.includes('#3')));
    assert.equal(await screen.evaluate(()=>window.rings),6,'Second/third never ring');assert.equal(await screen.locator('#players img').count(),0,'Names rendered as text, never HTML');
    await screen.screenshot({path:'/tmp/buzzer-screenshots/projector-winner.png',fullPage:true});
    await host.locator('#wrong').click();await students[0].waitForFunction(()=>document.getElementById('myRank').textContent.includes('hết quyền'));
    await students[1].waitForFunction(()=>!document.getElementById('bell').disabled);await students[1].locator('#bell').click();await screen.waitForFunction(()=>window.rings===9);
    await host.waitForFunction(()=>document.getElementById('winner').textContent.includes('Đội Tiên Phong'));await host.locator('#correct').click();
    await students[2].waitForFunction(()=>document.getElementById('phase').textContent==='Câu đã kết thúc');assert.equal(await students[2].locator('#bell').isDisabled(),true);await screen.waitForFunction(()=>document.getElementById('ringingBell').hidden);
    assert.equal(await host.locator('.global-audio').isVisible(),true);
    for(const kind of ['background','bell']){await host.locator('#audio-'+kind).setInputFiles({name:'custom.wav',mimeType:'audio/wav',buffer:Buffer.from('fixture audio')});await host.locator('[data-audio-save="'+kind+'"]').click();await host.waitForFunction(kind=>document.getElementById('audio-name-'+kind).textContent==='custom.wav',kind);}
    await screen.waitForFunction(()=>window.decodes===2);
    await host.locator('#newQuestion').click();await host.waitForFunction(()=>!document.getElementById('start').disabled);await host.locator('#start').click();
    await screen.waitForFunction(()=>window.musicStarts===1);

    await students[0].waitForFunction(()=>!document.getElementById('bell').disabled);
    // Reload restores the original contestant token rather than adding another player.
    await students[0].reload();await students[0].locator('#playerPanel').waitFor({state:'visible'});assert.equal(Object.keys(world.players).length,3);
    await students[2].route('**/hoclieu_game_buzzer_api.php*',route=>route.abort());
    await students[2].waitForFunction(()=>document.getElementById('connection').classList.contains('offline'));assert.equal(await students[2].locator('#bell').isDisabled(),true,'Disconnected contestant cannot claim a local win');
    await students[2].unroute('**/hoclieu_game_buzzer_api.php*');await students[2].waitForFunction(()=>!document.getElementById('bell').disabled);
    await students[0].locator('#bell').click();await screen.waitForFunction(()=>window.customRings===1&&window.musicStops===1);
    await students[1].waitForFunction(()=>document.getElementById('phase').textContent==='Đã có người giành quyền trả lời');
    await host.waitForFunction(()=>document.getElementById('winner').textContent.includes('Đội Khám Phá'));await host.locator('#wrong').click();await screen.waitForFunction(()=>window.musicStarts===2);
    await students[1].waitForFunction(()=>document.getElementById('phase').textContent==='Chuông đã mở · hãy bấm ngay!');
    await students[1].waitForFunction(()=>!document.getElementById('bell').disabled);await students[1].locator('#bell').click();await screen.waitForFunction(()=>window.customRings===2&&window.musicStops===2);
    assert.equal(await screen.evaluate(()=>window.rings),9,'Custom bell replaces default; no extra beep');
    for(const [p,name]of [[host,'teacher'],[screen,'projector'],[students[0],'contestant-mobile']]){
      assert.equal(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),true,'No horizontal overflow: '+name);
      await p.screenshot({path:'/tmp/buzzer-screenshots/'+name+'.png',fullPage:true});
    }
    assert.deepEqual(errors,[],'No page errors');
    console.log('PASS browser: 3 real UI modes, join/rejoin, no early press, one sound, ranks 2/3 silent, wrong lock/reopen, correct/new question, offline protection, mobile layout and text escaping');
  }finally{
    for(let i=0;i<pages.length;i++)await pages[i].screenshot({path:'/tmp/buzzer-screenshots/final-'+i+'.png',fullPage:true}).catch(()=>{});
    await browser.close();await new Promise(resolve=>server.close(resolve));
  }
})().catch(error=>{console.error(error);server.close();process.exitCode=1;});

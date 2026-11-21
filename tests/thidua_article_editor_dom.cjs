const assert=require('node:assert/strict'),fs=require('node:fs');
const {JSDOM}=require('jsdom');
const script=fs.readFileSync('assets/thidua-article-editor.js','utf8');
function setup(options={}){
 const dom=new JSDOM('<details id="manager"><form><div id="tdArticleEditor"><textarea id="td_article_content" name="content"></textarea><div id="tdEditorStatus"></div></div><button type="submit">Lưu</button></form></details>',{runScripts:'outside-only',url:'https://school.test/thidua.php'});
 const w=dom.window,d=w.document,field=d.querySelector('textarea');field.value='<p>Nội dung cũ</p>';
 let config,editor,resolveUpload,submits=0;w.HTMLFormElement.prototype.submit=function(){submits++};
 if(!options.noTiny)w.tinymce={addI18n(){},get:()=>editor,init(c){config=c;const listeners={};editor={initialized:false,content:'',on(name,fn){name.split(' ').forEach(n=>listeners[n]=fn)},setContent(x){this.content=x},getContent(){return this.content},save(){field.value=this.content},uploadImages(){if(options.failImage)return Promise.reject(new Error('Ảnh bị lỗi'));return new Promise(r=>resolveUpload=r)}};c.setup(editor);return new Promise(r=>{w._finish=()=>{editor.initialized=true;listeners.init();r([editor])};})}};
 w.eval(script);
 return {w,d,field,open(){d.querySelector('details').open=true;d.querySelector('details').dispatchEvent(new w.Event('toggle'))},finish(){w._finish()},get config(){return config},get editor(){return editor},resolveUpload(){resolveUpload([])},get submits(){return submits}};
}
async function flush(){await new Promise(r=>setTimeout(r,0));}
(async()=>{
 let s=setup();assert(!s.config,'Lazy init while composer collapsed');s.w.setArticleEditorContent('tdArticleEditor','<table><tr><td>Tiếng Việt &amp; chữ</td></tr></table>');s.open();
 for(const name of ['fontfamily','fontsize','forecolor','backcolor','alignjustify','lineheight','table','image','link','searchreplace','fullscreen','preview','code'])assert(s.config.toolbar.join(' ').includes(name),'Full toolbar '+name);
 assert(s.config.paste_data_images&&s.config.image_caption&&s.config.convert_urls===false);
 s.w.setArticleEditorContent('tdArticleEditor','<p>Bản sửa mới trong lúc đang tải</p>');s.finish();await flush();assert(s.editor.content.includes('Bản sửa mới'),'Edit during async init is preserved');
 s.editor.content='<h2>Bài viết</h2><p><img src="data:image/png;base64,AAAA"></p>';
 const event=new s.w.Event('submit',{cancelable:true});s.d.querySelector('form').dispatchEvent(event);assert(event.defaultPrevented);assert.equal(s.submits,0,'Wait for pasted images before native submit');s.resolveUpload();await flush();assert.equal(s.submits,1);assert.equal(s.field.value,s.editor.content,'Sync complete HTML to actual form field');s.d.querySelector('form').dispatchEvent(new s.w.Event('submit',{cancelable:true}));await flush();assert.equal(s.submits,1,'A completed submit cannot send duplicate article');
 s.w.setArticleEditorContent('tdArticleEditor','');assert.equal(s.editor.content,'');assert.equal(s.field.value,'','Reset clears both sources');
 s=setup({noTiny:true});s.open();assert.match(s.d.getElementById('tdEditorStatus').textContent,/Chưa tải/);s.field.value='<p>Vẫn giữ nội dung</p>';s.d.querySelector('form').dispatchEvent(new s.w.Event('submit',{cancelable:true}));await flush();assert.equal(s.submits,1,'Fallback textarea can still save without CDN');
 s=setup({failImage:true});s.open();s.finish();await flush();s.editor.content='<p>Bài chưa lưu</p>';s.d.querySelector('form').dispatchEvent(new s.w.Event('submit',{cancelable:true}));await flush();assert.equal(s.submits,0);assert.match(s.d.getElementById('tdEditorStatus').textContent,/Ảnh bị lỗi/);assert(!s.d.querySelector('button').disabled,'Failed image save can retry');
 s=setup();s.open();s.finish();await flush();assert.match(await s.config.images_upload_handler({blob:()=>new s.w.Blob(['png'],{type:'image/png'})}),/^data:image\/png;base64,/);await assert.rejects(()=>s.config.images_upload_handler({blob:()=>new s.w.Blob(['x'],{type:'image/svg+xml'})}),/Chỉ nhận ảnh/);await assert.rejects(()=>s.config.images_upload_handler({blob:()=>new s.w.Blob([new Uint8Array(3*1024*1024+1)],{type:'image/png'})}),/3 MB/);
 console.log('PASS editor DOM: full toolbar, lazy init/edit race, image-before-submit, HTML sync, reset, CDN fallback and image failure/limits');
})().catch(e=>{console.error(e);process.exit(1)});

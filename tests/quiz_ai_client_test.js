const fs=require('fs'),vm=require('vm'),assert=require('assert');
class Element {
 constructor(tag='div'){this.tag=tag;this.children=[];this.events={};this.value='';this.dataset={};}
 appendChild(n){this.children.push(n);return n;}
 set textContent(v){this.text=v;this.children=[];}get textContent(){return this.text||'';}
 addEventListener(k,f){this.events[k]=f;}
 querySelector(s){return this.queries?.[s]||null;}
 focus(){}scrollIntoView(){}reportValidity(){return true;}
}
const ids={};['qp-ai-panel','qp-ai-preview','qp-ai-status','qp-ai-generate','qp-ai-stop','qp-ai-save','qp-ai-save-area','qp-ai-create-questions','qp-ai-topic','qp-ai-count','qp-ai-reference','qp-ai-grade','qp-ai-type','qp-ai-difficulty','create-set'].forEach(id=>ids[id]=new Element());
ids['qp-ai-panel'].dataset={existing:'0',csrf:'csrf-test',set:''};
Object.entries({'qp-ai-topic':'Phép cộng','qp-ai-count':'3','qp-ai-reference':'','qp-ai-grade':'6','qp-ai-type':'single','qp-ai-difficulty':'easy'}).forEach(([k,v])=>ids[k].value=v);
const button=new Element('button'),payload=new Element('input');ids['qp-ai-save'].queries={'button':button,'[name=ai_questions]':payload};
const createForm=new Element('form'),title=new Element('input');createForm.queries={'[name=title]':title};ids['qp-ai-create-questions'].form=createForm;
const requests=[];let total=0;
const sandbox={document:{getElementById:id=>ids[id],createElement:tag=>new Element(tag),createTextNode:text=>({text})},FormData,console,setTimeout:f=>{f();},fetch:async(url,request)=>{
 const body=request.body;requests.push(body);assert.equal(body.get('csrf'),'csrf-test');const count=Number(body.get('count'));const questions=Array.from({length:count},()=>({type:'single',text:'Câu '+(++total)+' <script>alert(1)</script>',choices:{A:'1',B:'2',C:'3',D:'4'},key:'D',seconds:20,explanation:'2+2=4'}));return {ok:true,json:async()=>({ok:true,questions})};
}};
vm.runInNewContext(fs.readFileSync(__dirname+'/../assets/quiz-ai.js','utf8'),sandbox);
(async()=>{
 await ids['qp-ai-generate'].events.click();assert.equal(requests.length,2);assert.equal(requests[0].get('count'),'2');assert.equal(requests[1].get('count'),'1');assert.equal(JSON.parse(requests[1].get('drafts')).questions.length,2);
 assert.equal(JSON.parse(ids['qp-ai-create-questions'].value).questions.length,3);assert.equal(ids['qp-ai-preview'].children.length,3);assert.equal(button.disabled,false);
 const first=ids['qp-ai-preview'].children[0],check=first.children[0].children[0].children[0];check.checked=false;check.events.change();assert.equal(JSON.parse(ids['qp-ai-create-questions'].value).questions.length,2);
 let prevented=false;ids['qp-ai-save'].events.submit({preventDefault(){prevented=true;},currentTarget:ids['qp-ai-save']});assert(prevented);assert.equal(title.value,'Phép cộng');assert.equal(JSON.parse(payload.value).questions.length,2);
 ids['qp-ai-count'].value='101';await ids['qp-ai-generate'].events.click();assert.equal(requests.length,2);
 console.log('Quiz AI client: batching, editing selection, safe text, create transfer and capacity checks passed.');
})().catch(e=>{console.error(e);process.exitCode=1;});

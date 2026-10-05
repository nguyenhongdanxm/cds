(function(){
'use strict';
const panel=document.getElementById('qp-ai-panel');if(!panel)return;
const el=id=>document.getElementById(id),preview=el('qp-ai-preview'),status=el('qp-ai-status'),generate=el('qp-ai-generate'),stop=el('qp-ai-stop');
let drafts=[],running=false,stopRequested=false;
const letters=['A','B','C','D'];
function node(tag,text,parent){const n=document.createElement(tag);if(text!==undefined)n.textContent=text;if(parent)parent.appendChild(n);return n;}
function field(parent,label,value,onchange,multiline=true){node('label',label,parent);const n=node(multiline?'textarea':'input',undefined,parent);if(multiline)n.rows=2;else n.type='number';n.value=value;n.addEventListener('input',()=>onchange(n.value));return n;}
function selected(){return drafts.filter(d=>d.selected).map(d=>d.q);}
function update(){el('qp-ai-save-area').hidden=!drafts.length;const create=el('qp-ai-create-questions');if(create)create.value=selected().length?JSON.stringify({questions:selected()}):'';}
function render(){preview.textContent='';drafts.forEach((draft,i)=>{
 const q=draft.q,card=node('article',undefined,preview);card.className='qp-ai-draft';const heading=node('div',undefined,card);heading.className='row';const label=node('label',undefined,heading),check=node('input',undefined,label);check.type='checkbox';check.checked=draft.selected;check.addEventListener('change',()=>{draft.selected=check.checked;update()});label.appendChild(document.createTextNode(' Chọn câu '+(i+1)));const remove=node('button','Bỏ câu',heading);remove.className='btn tiny';remove.type='button';remove.addEventListener('click',()=>{drafts.splice(i,1);render()});
 field(card,'Câu hỏi',q.text,value=>{q.text=value;update()}).maxLength=2000;
 if(q.type==='single'||q.type==='multi'){const grid=node('div',undefined,card);grid.className='answer-grid';letters.forEach(letter=>{const box=node('div',undefined,grid);field(box,letter,q.choices[letter],value=>{q.choices[letter]=value;update()}).maxLength=500});}
 if(q.type==='single'||q.type==='paper_logic'){node('label','Đáp án đúng',card);const select=node('select',undefined,card);letters.forEach(letter=>{const opt=node('option',q.type==='paper_logic'?letter+' · '+q.choices[letter]:letter,select);opt.value=letter});select.value=q.key;select.addEventListener('change',()=>{q.key=select.value;update()});}
 if(q.type==='multi'){node('label','Chọn các đáp án đúng (ít nhất 2)',card);const row=node('div',undefined,card);row.className='row';letters.forEach(letter=>{const label=node('label',undefined,row),check=node('input',undefined,label);check.type='checkbox';check.checked=q.keys.includes(letter);label.appendChild(document.createTextNode(' '+letter));check.addEventListener('change',()=>{q.keys=letters.filter(l=>l===letter?check.checked:q.keys.includes(l));update()});});}
 if(q.type==='fill')field(card,'Đáp án chấp nhận · mỗi dòng một đáp án',q.fill_answers.join('\n'),value=>{q.fill_answers=value.split(/\r?\n/).map(x=>x.trim()).filter(Boolean);update()});
 if(q.type==='order')field(card,'Các bước theo thứ tự đúng · mỗi dòng một bước',q.steps.join('\n'),value=>{q.steps=value.split(/\r?\n/).map(x=>x.trim()).filter(Boolean);update()});
 if(q.type==='match')field(card,'Cặp nối · mỗi dòng: vế trái | vế phải',q.pairs.map(p=>p.join(' | ')).join('\n'),value=>{q.pairs=value.split(/\r?\n/).filter(x=>x.trim()).map(x=>x.split('|').map(y=>y.trim()));update()});
 field(card,'Giải thích đáp án',q.explanation||'',value=>{q.explanation=value;update()}).maxLength=2000;
 const seconds=field(card,'Thời gian trên máy (giây)',q.seconds,value=>{q.seconds=Number(value);update()},false);seconds.min=10;seconds.max=300;
 });update();}
stop.addEventListener('click',()=>{stopRequested=true;stop.disabled=true;status.textContent='Sẽ dừng sau lượt AI đang xử lý; giữ các câu đã tạo.'});
generate.addEventListener('click',async()=>{
 if(running)return;const topic=el('qp-ai-topic').value.trim(),count=Number(el('qp-ai-count').value),existing=Number(panel.dataset.existing||0);
 if(!topic){status.textContent='Nhập chủ đề hoặc yêu cầu tạo câu hỏi.';el('qp-ai-topic').focus();return;}
 if(!Number.isInteger(count)||count<1||count>20||existing+drafts.length+count>100){status.textContent='Chọn 1–20 câu; tổng câu đã lưu và bản nháp không vượt 100.';return;}
 const config={topic,reference:el('qp-ai-reference').value.trim(),grade:el('qp-ai-grade').value,type:el('qp-ai-type').value,difficulty:el('qp-ai-difficulty').value};
 running=true;stopRequested=false;generate.disabled=true;stop.hidden=false;stop.disabled=false;el('qp-ai-save').querySelector('button').disabled=true;
 let added=0;
 try{while(added<count&&!stopRequested){status.textContent='Đang tạo '+(added+1)+'–'+Math.min(added+2,count)+' / '+count+' câu…';const form=new FormData();form.set('action','ai_generate');form.set('csrf',panel.dataset.csrf);form.set('set_id',panel.dataset.set);form.set('count',String(Math.min(2,count-added)));Object.entries(config).forEach(([key,value])=>form.set(key,value));if(drafts.length)form.set('drafts',JSON.stringify({questions:drafts.map(d=>d.q)}));
 const response=await fetch('hoclieu_game_quiz.php',{method:'POST',credentials:'same-origin',body:form});let data;try{data=await response.json()}catch(e){throw new Error('Máy chủ không trả JSON. Thử lại hoặc giảm số câu.');}
 if(!response.ok||!data.ok)throw new Error(data.message||'Không tạo được câu hỏi.');if(!Array.isArray(data.questions)||data.questions.length!==Math.min(2,count-added))throw new Error('Kết quả AI chưa đủ câu hỏi.');
 data.questions.forEach(q=>drafts.push({q,selected:true}));added+=data.questions.length;render();if(added<count&&!stopRequested)await new Promise(resolve=>setTimeout(resolve,2100));
 }status.textContent=(stopRequested?'Đã dừng. ':'')+'Đã tạo '+added+' câu mới. Kiểm tra và chọn câu trước khi lưu.';
 }catch(error){status.textContent='Đã tạo '+added+' câu; '+error.message+' Các bản nháp đã có được giữ lại.';}
 finally{running=false;generate.disabled=false;stop.hidden=true;el('qp-ai-save').querySelector('button').disabled=false;update();}
});
el('qp-ai-save').addEventListener('submit',event=>{
 if(running){event.preventDefault();return;}const questions=selected();if(!questions.length){event.preventDefault();status.textContent='Chọn ít nhất một câu để lưu.';return;}
 const payload=JSON.stringify({questions});event.currentTarget.querySelector('[name=ai_questions]').value=payload;
 if(!panel.dataset.set){event.preventDefault();const create=el('qp-ai-create-questions');create.value=payload;const form=create.form;const title=form.querySelector('[name=title]'),grade=form.querySelector('[name=grade_scope]');if(!title.value.trim())title.value=el('qp-ai-topic').value.trim().slice(0,255);if(grade&&grade.querySelector('option[value="'+el('qp-ai-grade').value+'"]'))grade.value=el('qp-ai-grade').value;
 status.textContent='Đã chọn '+questions.length+' câu. Hoàn thiện môn, khối và giới thiệu rồi bấm Tạo bộ câu hỏi.';el('create-set').scrollIntoView({behavior:'smooth',block:'start'});form.reportValidity();}
});
const create=el('qp-ai-create-questions');if(create)create.form.addEventListener('submit',event=>{if(running){event.preventDefault();status.textContent='Hãy đợi hoặc dừng AI trước khi lưu bộ.';}else update();});
})();

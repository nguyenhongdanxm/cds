(function(){
const roster=window.LB_CLASS_STUDENTS||{};
function norm(s){return String(s||'').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase();}
function searchKey(s){return norm(s).replace(/đ/g,'d');}
function splitNames(v){return String(v||'').split(/[,;\n]+/).map(x=>x.trim()).filter(Boolean);}
function unique(list){const seen={};const out=[];list.forEach(n=>{const k=norm(n);if(!k||seen[k])return;seen[k]=1;out.push(n);});return out;}
function boxOf(root){return root.querySelector('.lb-absent-chips');}
function hiddenOf(root){return root.querySelector('input[name="absent_names"]');}
function render(root){
  const hidden=hiddenOf(root), box=boxOf(root);
  if(!hidden||!box)return;
  const names=unique(splitNames(hidden.value));
  hidden.value=names.join(', ');
  box.replaceChildren();
  names.forEach(n=>{const chip=document.createElement('span');chip.className='lb-absent-chip';chip.appendChild(document.createTextNode(n));const button=document.createElement('button');button.type='button';button.dataset.name=n;button.setAttribute('aria-label','Gỡ tên');button.textContent='×';chip.appendChild(button);box.appendChild(chip);});
}
function closeSuggest(){document.querySelectorAll('.lb-absent-suggest').forEach(x=>x.remove());}
function showSuggest(root,input,list){
  closeSuggest();
  const chosen=unique(splitNames(hiddenOf(root).value)).map(norm);
  const q=searchKey(input.value);
  const hits=(list||[]).filter(n=>!chosen.includes(norm(n)) && (!q || searchKey(n).includes(q))).slice(0,12);
  if(!hits.length)return;
  const menu=document.createElement('div');
  menu.className='lb-absent-suggest';
  hits.forEach(n=>{const b=document.createElement('button');b.type='button';b.textContent=n;b.addEventListener('mousedown',function(ev){ev.preventDefault();addName(root,n);input.value='';closeSuggest();input.focus();});menu.appendChild(b);});
  const cell=root.getBoundingClientRect();
  menu.style.left=(cell.left+window.scrollX)+'px';
  menu.style.top=(cell.bottom+window.scrollY)+'px';
  menu.style.position='absolute';
  document.body.appendChild(menu);
}
const requests=new Map();
function openSuggest(root,input){
  const cls=root.dataset.class||'';
  if(Array.isArray(roster[cls])&&roster[cls].length){showSuggest(root,input,roster[cls]);return;}
  if(!requests.has(cls)){
    const url=new URL('sodaubai.php',location.href);url.searchParams.set('ajax','students');url.searchParams.set('class',cls);
    requests.set(cls,fetch(url,{credentials:'same-origin',headers:{Accept:'application/json'}}).then(r=>{if(!r.ok)throw new Error('roster');return r.json()}).then(data=>{
      if(!data||!data.ok||data.class!==cls||!Array.isArray(data.names))throw new Error('roster');
      roster[cls]=data.names.filter(n=>typeof n==='string');window.LB_CLASS_STUDENTS=roster;return roster[cls];
    }).finally(()=>requests.delete(cls)));
  }
  requests.get(cls).then(names=>{if(document.activeElement===input)showSuggest(root,input,names)}).catch(()=>{input.title='Chưa tải được danh sách học sinh. Bấm lại để thử.'});
}
function addName(root,name){
  const hidden=hiddenOf(root);
  hidden.value=unique(splitNames(hidden.value).concat([name])).join(', ');
  render(root);
  hidden.dispatchEvent(new Event('change',{bubbles:true}));
}
document.querySelectorAll('.lb-absent').forEach(function(root){
  render(root);
  root.addEventListener('lb:draft',function(){render(root)});
  const input=root.querySelector('.lb-absent-input');
  if(!input||root.dataset.disabled==='1')return;
  input.addEventListener('input',function(){openSuggest(root,input);});
  input.addEventListener('focus',function(){openSuggest(root,input);});
  input.addEventListener('blur',function(){setTimeout(closeSuggest,150);});
  input.addEventListener('keydown',function(ev){
    if(ev.key==='Enter'||ev.key===','){
      ev.preventDefault();
      const first=document.querySelector('.lb-absent-suggest button');
      if(first){addName(root,first.textContent);input.value='';closeSuggest();}
    }
  });
  root.addEventListener('click',function(ev){
    const btn=ev.target.closest('button[data-name]');
    if(!btn)return;
    const hidden=hiddenOf(root);
    const drop=norm(btn.getAttribute('data-name'));
    hidden.value=unique(splitNames(hidden.value).filter(n=>norm(n)!==drop)).join(', ');
    render(root);
    hidden.dispatchEvent(new Event('change',{bubbles:true}));
  });
});
})();

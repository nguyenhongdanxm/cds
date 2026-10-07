(function(){
'use strict';
const form=document.getElementById('pr-form');if(!form)return;
let dirty=false;const markDirty=()=>{dirty=true;const note=document.getElementById('pr-save-note');if(note)note.textContent='Có thay đổi chưa lưu.';};
const allowed=new Set(['P','DIV','BR','STRONG','B','EM','I','U','S','SPAN','UL','OL','LI','TABLE','THEAD','TBODY','TR','TD','TH','H3','H4','BLOCKQUOTE','SUP','SUB','A']);
// Chặn HTML chủ động từ clipboard trước khi đưa vào vùng soạn thảo.
function cleanPaste(html){const source=new DOMParser().parseFromString(html,'text/html');const fragment=document.createDocumentFragment();
function copy(node,target){if(node.nodeType===3){target.appendChild(document.createTextNode(node.textContent));return;}if(node.nodeType!==1)return;if(['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','SVG','MATH','FORM','IMG','VIDEO','AUDIO','INPUT','BUTTON'].includes(node.tagName))return;
let element=allowed.has(node.tagName)?document.createElement(node.tagName.toLowerCase()):target;
if(element!==target){if(['TD','TH'].includes(node.tagName))for(const attr of ['colspan','rowspan']){const n=Number(node.getAttribute(attr));if(n>1&&n<=12)element.setAttribute(attr,n);}if(node.tagName==='A'&&/^https?:\/\//i.test(node.getAttribute('href')||''))element.setAttribute('href',node.getAttribute('href'));target.appendChild(element);}for(const child of node.childNodes)copy(child,element);}
for(const child of source.body.childNodes)copy(child,fragment);const output=document.createElement('div');output.append(fragment);return output.innerHTML;}
form.querySelectorAll('[data-rich]').forEach(rich=>{
const editor=rich.querySelector('[contenteditable]'),hidden=rich.querySelector('textarea');let range=null;
const remember=()=>{const selection=window.getSelection();if(selection.rangeCount&&editor.contains(selection.anchorNode))range=selection.getRangeAt(0).cloneRange();};
function focus(){editor.focus();if(range&&editor.contains(range.commonAncestorContainer)){const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);}else{const selection=window.getSelection(),r=document.createRange();r.selectNodeContents(editor);r.collapse(false);selection.removeAllRanges();selection.addRange(r);} }
function command(name,value){if(editor.contentEditable!=='true')return;focus();document.execCommand(name,false,value);remember();hidden.value=editor.innerHTML;markDirty();}
editor.addEventListener('keyup',remember);editor.addEventListener('mouseup',remember);editor.addEventListener('touchend',remember);editor.addEventListener('blur',remember);editor.addEventListener('input',()=>{hidden.value=editor.innerHTML;markDirty();remember();});
rich.querySelectorAll('[data-cmd]').forEach(button=>{button.addEventListener('mousedown',e=>e.preventDefault());button.addEventListener('click',()=>command(button.dataset.cmd));});
rich.querySelector('[data-size]').addEventListener('change',e=>command('fontSize',e.target.value));rich.querySelector('[data-color]').addEventListener('input',e=>command('foreColor',e.target.value));
rich.querySelector('[data-link]').addEventListener('click',()=>{remember();const url=prompt('Đường dẫn https://');if(url&&/^https?:\/\//i.test(url.trim()))command('createLink',url.trim());else if(url)alert('Chỉ dùng đường dẫn http:// hoặc https://.');});
rich.querySelector('[data-table]').addEventListener('click',()=>{remember();const rows=Number(prompt('Số hàng (1–30)','3'));if(!Number.isInteger(rows)||rows<1||rows>30)return;const cols=Number(prompt('Số cột (1–10)','4'));if(!Number.isInteger(cols)||cols<1||cols>10)return;let html='<table><tbody>';for(let r=0;r<rows;r++)html+='<tr>'+Array.from({length:cols},()=>'<td><br></td>').join('')+'</tr>';command('insertHTML',html+'</tbody></table><p><br></p>');});
function selectedRow(){focus();const anchor=window.getSelection().anchorNode;const element=anchor&&anchor.nodeType===1?anchor:anchor&&anchor.parentElement;const row=element&&element.closest('tr');return row&&editor.contains(row)?row:null;}
rich.querySelector('[data-row]').addEventListener('click',()=>{const row=selectedRow();if(!row){alert('Đặt con trỏ vào một ô trong bảng.');return;}const clone=row.cloneNode(true);for(const cell of clone.cells){cell.removeAttribute('rowspan');cell.innerHTML='<br>';}row.after(clone);hidden.value=editor.innerHTML;markDirty();});
rich.querySelector('[data-delete-row]').addEventListener('click',()=>{const row=selectedRow();if(row&&confirm('Xóa hàng đang chọn?')){const table=row.closest('table');row.remove();if(!table.rows.length)table.remove();hidden.value=editor.innerHTML;markDirty();}});
editor.addEventListener('paste',e=>{e.preventDefault();const html=e.clipboardData.getData('text/html');if(html)command('insertHTML',cleanPaste(html));else command('insertText',e.clipboardData.getData('text/plain'));});
});
form.addEventListener('change',markDirty);form.addEventListener('submit',()=>{form.querySelectorAll('[data-rich]').forEach(rich=>{rich.querySelector('textarea').value=rich.querySelector('[contenteditable]').innerHTML;});dirty=false;});
window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
const month=form.querySelector('[name=month]'),next=form.querySelector('[name=next_month]');if(month&&next)month.addEventListener('change',()=>{const parts=month.value.split('-').map(Number);if(parts.length===2&&parts[1]>=1&&parts[1]<=12){const y=parts[0]+(parts[1]===12?1:0),m=parts[1]===12?1:parts[1]+1;next.value=y+'-'+String(m).padStart(2,'0');}});
})();

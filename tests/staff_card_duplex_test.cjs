const fs=require('fs'),vm=require('vm'),assert=require('assert');
const php=fs.readFileSync('csdl_staff_cards.php','utf8');
const functions=['duplexOrder','duplexFace','renderDuplex'].map(name=>php.split('\n').find(l=>l.startsWith('function '+name+'('))).join('\n');
for(const mode of ['back','front'])for(const count of [0,1,7,8,9,16,17]){
 const rows=Array.from({length:count},(_,i)=>({id:String(i+1)}));
 const nodes={count:{},duplexBackFace:{value:mode},printGrid:{classList:{add(){}},querySelectorAll(){return []}}};
 const ctx={$:id=>nodes[id],selectedStaff:()=>rows,esc:String,faceHtml:(face,s)=>`[${s.id}:${face}]`,qr(){}};
 vm.createContext(ctx);vm.runInContext(functions+';renderDuplex();',ctx);
 const sheets=[...nodes.printGrid.innerHTML.matchAll(/<div class="duplex-sheet duplex-(front|back)">([\s\S]*?)(?=<div class="duplex-sheet|$)/g)];
 assert.equal(sheets.length,Math.ceil(count/8)*2);
 sheets.forEach(([_,face,html],sheet)=>{
  const offset=Math.floor(sheet/2)*8;
  const slots=Array.from({length:8},(_,j)=>offset+j<count?offset+j+1:null);
  const expected=face==='front'?slots:[slots[3],slots[2],slots[1],slots[0],slots[7],slots[6],slots[5],slots[4]];
  const actual=[...html.matchAll(/class="duplex-card(?: duplex-blank)?"(?: data-staff="(\d+)")?/g)].map(m=>m[1]?Number(m[1]):null);
  assert.deepEqual(actual,expected);
  const faces=[...html.matchAll(/\[(\d+):(front|back)\]/g)];
  faces.forEach(m=>assert.equal(m[2],face==='front'?'front':mode));
 });
}
console.log('PASS: 14 cases; face choice, mirrored positions, blank slots, multiple sheets');

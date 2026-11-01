(function(){
'use strict';
var source=document.getElementById('constraintBulkItems'),items=source?JSON.parse(source.textContent):[];
var actions={fixed_lesson_delete:['fixed_lessons','activity_id'],special_slot_delete:['special_slots','id'],double_lesson_delete:['double_lessons','assignment_id'],subject_sequence_delete:['subject_sequences','id']};
document.querySelectorAll('.rule-list').forEach(function(list,index){
 var panel=list.closest('[id$="Panel"]');if(!panel)return;
 var form=document.createElement('form');form.id='constraintBulk'+index;form.method='post';form.action='/tkb_xep.php';form.className='constraint-bulk-form d-flex flex-wrap gap-2 align-items-center mb-2';
 var csrf=document.querySelector('input[name="csrf"]');
 Object.entries({csrf:csrf?csrf.value:'',action:'constraints_bulk_delete',confirm_bulk:'1',bulk_origin:'lesson',bulk_panel:panel.id.replace('Panel','')}).forEach(function(pair){var i=document.createElement('input');i.type='hidden';i.name=pair[0];i.value=pair[1];form.appendChild(i);});
 var label=document.createElement('label'),all=document.createElement('input');all.type='checkbox';all.dataset.bulkAll='1';label.append(all,document.createTextNode(' Chọn tất cả'));form.appendChild(label);
 var button=document.createElement('button');button.className='btn btn-sm btn-outline-danger';button.dataset.bulkSubmit='1';button.disabled=true;form.appendChild(button);
 var count=0;
 list.querySelectorAll('.rule-row').forEach(function(row){
  var action=row.querySelector('input[name="action"]'),spec=action&&actions[action.value];if(!spec)return;
  var key=row.querySelector('input[name="'+spec[1]+'"]'),item=key&&items.find(function(x){return x.field===spec[0]&&x.key===key.value;});if(!item)return;
  var box=document.createElement('input');box.type='checkbox';box.name='selected_constraints[]';box.value=item.token;box.setAttribute('form',form.id);box.className='me-2';box.setAttribute('aria-label','Chọn '+item.label);
  row.querySelector('span').prepend(box);count++;
 });
 if(count)list.before(form);
});
document.querySelectorAll('.constraint-bulk-form').forEach(function(form){
 var all=form.querySelector('[data-bulk-all]'),button=form.querySelector('[data-bulk-submit]');
 function boxes(){return Array.from(form.elements).filter(function(e){return e.name==='selected_constraints[]';});}
 function update(){var b=boxes(),n=b.filter(function(x){return x.checked;}).length;all.checked=b.length>0&&n===b.length;all.indeterminate=n>0&&n<b.length;button.disabled=!n;button.textContent='Xóa đã chọn ('+n+')';}
 all.addEventListener('change',function(){boxes().forEach(function(x){x.checked=all.checked;});update();});
 boxes().forEach(function(b){b.addEventListener('change',update);});
 form.addEventListener('submit',function(ev){var count=boxes().filter(function(x){return x.checked;}).length;if(!count||count>500||!confirm('Xóa '+count+' ràng buộc đã chọn trong phiên bản tuần hiện tại?'))ev.preventDefault();});
 update();
});
})();
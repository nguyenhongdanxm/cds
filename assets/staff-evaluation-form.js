(function(){
 'use strict';
 document.querySelectorAll('.se-person-detail').forEach(function(detail){
  var position=detail.querySelector('[data-se-position-input]'),profile=detail.querySelector('[data-se-profile]'),employment=detail.querySelector('[data-se-employment]'),external=detail.querySelector('[data-se-external]');
  function refresh(){detail.querySelector('[data-se-position]').textContent=position.value.trim()||'Chưa ghi chức danh';detail.querySelector('[data-se-profile-label]').textContent=profile.value==='manager'?'Quản lý':'Không quản lý';detail.querySelector('[data-se-employment-label]').textContent=employment.value==='hopdong'?'Hợp đồng':'Viên chức';detail.querySelector('[data-se-contract-basis]').hidden=employment.value!=='hopdong';detail.querySelector('[data-se-external-label]').hidden=!external.checked;}
  detail.addEventListener('input',refresh);detail.addEventListener('change',refresh);detail.querySelector('[data-se-close-detail]').addEventListener('click',function(){detail.open=false;detail.querySelector('summary').focus();});refresh();
 });
 function searchable(text){return text.toLocaleLowerCase('vi').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/đ/g,'d');}
 var search=document.querySelector('[data-se-staff-search]');if(search){search.addEventListener('input',function(){var q=searchable(search.value.trim()),count=0;document.querySelectorAll('[data-se-staff-row]').forEach(function(row){var name=searchable(row.querySelector('td').textContent);row.hidden=q!==''&&!name.includes(q);if(!row.hidden)count++;});document.querySelector('[data-se-search-empty]').hidden=count!==0;});}
 // Open collapsed invalid controls when native validation asks the user to correct them.
 document.addEventListener('invalid',function(event){var parent=event.target.parentElement;while(parent){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}var row=event.target.closest('[data-se-staff-row]');if(row&&row.hidden){row.hidden=false;if(search)search.value='';}},true);
 var form=document.getElementById('se-self-evaluation-form');
 function revealIssue(link,focus){var field=link.dataset.seIssueField,target=field.charAt(0)==='#'?document.getElementById(field.slice(1)):(form&&form.elements.namedItem(field));if(!target||!target.tagName)return;var parent=target;while(parent){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}if(target.matches('input,textarea,select')){target.setAttribute('aria-invalid','true');target.classList.add('se-invalid');if(focus)target.focus();}if(focus)target.scrollIntoView({block:'center',behavior:'smooth'});}
 var issueLinks=Array.from(document.querySelectorAll('[data-se-issue-field]'));issueLinks.forEach(function(link){revealIssue(link,false);link.addEventListener('click',function(event){event.preventDefault();revealIssue(link,true);});});if(issueLinks.length)revealIssue(issueLinks[0],true);
 if(!form)return;
 form.addEventListener('input',function(event){if(event.target.matches('input,textarea,select')){event.target.removeAttribute('aria-invalid');event.target.classList.remove('se-invalid');}});
 var inputs=Array.from(form.querySelectorAll('input[data-score-group]'));
 function update(){
  var sums={A:0,B:0},complete={A:true,B:true};
  inputs.forEach(function(input){var key=input.dataset.scoreGroup,value=input.value.trim();if(value===''||!input.validity.valid||!Number.isFinite(Number(value))){complete[key]=false;return;}sums[key]+=Number(value);});
  ['A','B','total'].forEach(function(key){var output=form.querySelector('[data-se-total="'+key+'"]');if(!output)return;var valid=key==='total'?complete.A&&complete.B:complete[key];output.textContent=valid?String(Math.round((key==='total'?sums.A+sums.B:sums[key])*100)/100):'Chưa đủ căn cứ';});
 }
 form.addEventListener('input',update);update();
})();

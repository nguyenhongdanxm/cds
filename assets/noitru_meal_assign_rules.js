(function(){
  'use strict';
  document.addEventListener('DOMContentLoaded', function(){
    if(!/noitru_assign\.php$/i.test(location.pathname)) return;
    var mode=new URLSearchParams(location.search).get('mode');
    if(mode!=='meals') return;

    var balance=document.querySelector('input[name="balance_gender"]');
    var gap=document.querySelector('select[name="max_grade_gap"]');
    if(!balance||!gap) return;

    // Quy tắc mặc định mới: chỉ cùng khối hoặc khối liền kề, không cho cách khối.
    if(!sessionStorage.getItem('ntMealRuleTouched')) gap.value='1';
    gap.addEventListener('change',function(){sessionStorage.setItem('ntMealRuleTouched','1');});
    Array.from(gap.options).forEach(function(opt){
      if(opt.value==='0') opt.textContent='Chỉ cùng khối';
      if(opt.value==='1') opt.textContent='Cùng khối, thiếu mới ghép khối liền kề';
    });

    var oldLabel=balance.closest('label');
    if(!oldLabel) return;
    balance.style.display='none';
    var wrap=document.createElement('div');
    wrap.className='nt-meal-gender-rule';
    wrap.innerHTML='<label class="small fw-semibold mb-1 d-block">Cách xếp theo giới tính</label>'+
      '<select class="form-select form-select-sm" id="ntMealGenderMode">'+
      '<option value="mixed">Khác giới – ưu tiên xen kẽ Nam/Nữ trong mâm</option>'+
      '<option value="same">Cùng giới tính – mỗi mâm chỉ xếp một giới</option>'+
      '</select>'+
      '<div class="text-muted mt-1" style="font-size:.72rem">Ưu tiên lớp và khối luôn cao hơn giới tính. Không ghép các khối cách nhau.</div>';
    oldLabel.insertAdjacentElement('afterend',wrap);
    oldLabel.style.display='none';
    var sel=wrap.querySelector('#ntMealGenderMode');
    sel.value=balance.checked?'mixed':'same';
    sel.addEventListener('change',function(){balance.checked=sel.value==='mixed';});

    var rule=gap.closest('.rule-box');
    if(rule){
      var title=rule.querySelector('.fw-semibold.small');
      if(title) title.textContent='Ưu tiên: cùng lớp → cùng khối → thiếu mới ghép khối liền kề';
    }
  });
})();

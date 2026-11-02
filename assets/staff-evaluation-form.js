(function(){
 'use strict';
 var form=document.getElementById('se-self-evaluation-form');if(!form)return;
 var inputs=Array.from(form.querySelectorAll('input[data-score-group]'));
 function update(){
  var sums={A:0,B:0},complete={A:true,B:true};
  inputs.forEach(function(input){var key=input.dataset.scoreGroup,value=input.value.trim();if(value===''||!input.validity.valid||!Number.isFinite(Number(value))){complete[key]=false;return;}sums[key]+=Number(value);});
  ['A','B','total'].forEach(function(key){var output=form.querySelector('[data-se-total="'+key+'"]');if(!output)return;var valid=key==='total'?complete.A&&complete.B:complete[key];output.textContent=valid?String(Math.round((key==='total'?sums.A+sums.B:sums[key])*100)/100):'Chưa đủ căn cứ';});
 }
 form.addEventListener('input',update);update();
})();

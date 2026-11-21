(function(){
 'use strict';
 var selector=document.getElementById('schoolWeek');
 if(selector)selector.addEventListener('change',function(){var publish=document.getElementById('publishButton');if(publish)publish.disabled=true;document.querySelectorAll('[name="gdp_ack[]"]').forEach(function(box){box.checked=false});this.form.requestSubmit()});
 var form=document.getElementById('publishForm');if(!form)return;
 var boxes=Array.from(form.querySelectorAll('[name="gdp_ack[]"]')),button=document.getElementById('publishButton'),count=document.getElementById('gdpReviewCount');
 function refresh(){var n=boxes.filter(function(b){return b.checked}).length;if(count)count.textContent='Đã xác nhận '+n+'/'+boxes.length+' tiết.'+(n<boxes.length?' Cần xác nhận đủ để công bố.':' Đã đủ xác nhận.');button.disabled=n<boxes.length;}
 boxes.forEach(function(box){box.addEventListener('change',refresh)});refresh();
 form.addEventListener('submit',function(event){
  if(boxes.some(function(b){return !b.checked})){event.preventDefault();return;}
  var incomplete=document.getElementById('confirmIncomplete');
  // The server still verifies incomplete plans and every GDĐP acknowledgement.
  if(form.dataset.incomplete==='1'&&!window.confirm('Phương án còn tiết chưa xếp hoặc lỗi/xung đột. Vẫn công bố phần đã xếp?')){event.preventDefault();return;}
  incomplete.value=form.dataset.incomplete==='1'?'1':'0';
  if(!window.confirm('Công bố và cập nhật TKB của tuần đã chọn?'))event.preventDefault();
 });
})();

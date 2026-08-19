document.addEventListener('DOMContentLoaded',function(){
 if(!/mode=rooms/.test(location.search))return;

 // MỤC 3: thu gọn thật sự, mặc định đóng.
 var heading=[].slice.call(document.querySelectorAll('h6')).find(function(h){return /3\.\s*Danh sách\s+phòng\s+và\s+sức\s+chứa/i.test((h.textContent||'').trim());});
 if(heading){
   var card=heading.closest('.assign-card');
   if(card){
     var header=heading.parentElement;
     var body=document.createElement('div');body.className='nt-room-list-collapse';
     [].slice.call(card.children).forEach(function(el){if(el!==header)body.appendChild(el);});
     card.appendChild(body);body.hidden=true;
     var count=body.querySelectorAll('form.group-row').length;
     var btn=document.createElement('button');btn.type='button';btn.className='btn btn-sm btn-outline-primary ms-auto';btn.setAttribute('aria-expanded','false');
     function paint(open){btn.innerHTML='<i class="bi bi-chevron-'+(open?'up':'down')+'"></i> '+(open?'Ẩn':'Mở')+' danh sách phòng'+(count?' ('+count+')':'');}
     paint(false);if(header)header.appendChild(btn);
     btn.addEventListener('click',function(){var open=body.hidden;body.hidden=!open;btn.setAttribute('aria-expanded',open?'true':'false');paint(open);});

     // Tự lưu giới tính và sức chứa; không cần nút Lưu.
     body.querySelectorAll('form.group-row').forEach(function(form){
       var save=[].slice.call(form.querySelectorAll('button')).find(function(b){return /Lưu/i.test(b.textContent||'');});if(save)save.style.display='none';
       var gender=form.querySelector('select[name="room_gender"]'),cap=form.querySelector('input[name="group_capacity"]'),old=form.querySelector('input[name="old_name"]');if(!gender||!old)return;
       var status=document.createElement('span');status.className='small text-muted';status.style.minWidth='58px';gender.insertAdjacentElement('afterend',status);
       var timer=null;
       function quickSave(){clearTimeout(timer);status.textContent='Đang lưu…';var fd=new FormData();fd.append('room',old.value);fd.append('gender',gender.value);fd.append('capacity',cap?cap.value:'1');fetch('noitru_room_quick_save.php',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(x){status.textContent=x.ok?'✓ Đã lưu':'Lỗi';if(x.ok)setTimeout(function(){status.textContent='';},1400);}).catch(function(){status.textContent='Lỗi';});}
       gender.addEventListener('change',quickSave);if(cap){cap.addEventListener('change',quickSave);cap.addEventListener('input',function(){clearTimeout(timer);timer=setTimeout(quickSave,700);});}
     });
   }
 }

 // MỤC 4: bỏ khối lọc trùng "Lọc học sinh để gán nhanh".
 var duplicateHeading=[].slice.call(document.querySelectorAll('h5,h6,.fw-bold')).find(function(el){return /Lọc\s+học\s+sinh\s+để\s+gán\s+nhanh/i.test((el.textContent||'').trim());});
 if(duplicateHeading){
   var duplicateCard=duplicateHeading.closest('.assign-card,.card,.rule-box');
   if(duplicateCard)duplicateCard.remove();
 }
});
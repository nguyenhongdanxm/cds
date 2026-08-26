(function(){
  function norm(s){return String(s||'').replace(/\s+/g,' ').trim().toLowerCase();}
  function removeTotalDayColumn(){
    document.querySelectorAll('table').forEach(function(table){
      var ths=[].slice.call(table.querySelectorAll('thead th'));
      var idx=ths.findIndex(function(th){return norm(th.textContent)==='tổng điểm ngày';});
      if(idx<0)return;
      table.querySelectorAll('tr').forEach(function(tr){var cells=tr.children;if(cells[idx])cells[idx].remove();});
    });
  }
  function val(name){var el=document.querySelector('[name="'+name+'"]');return el?el.value:'';}
  function currentWeek(){var p=new URLSearchParams(location.search);return p.get('week')||val('week_start')||val('week')||'';}
  function currentDay(){var p=new URLSearchParams(location.search);return p.get('day')||val('date')||'';}
  function csrf(){var el=document.querySelector('input[name="csrf"]');return el?el.value:'';}
  function actionForm(scope,shift,label,cls){
    var f=document.createElement('form');f.method='post';f.action=(window.TD_ROOM_BASE_URL||'')+'thidua_phongnoitru_delete.php';f.style.display='inline';
    [['csrf',csrf()],['scope',scope],['shift',shift||''],['week',currentWeek()],['date',currentDay()]].forEach(function(pair){var i=document.createElement('input');i.type='hidden';i.name=pair[0];i.value=pair[1];f.appendChild(i);});
    var b=document.createElement('button');b.type='submit';b.className='btn btn-sm '+cls;b.innerHTML='<i class="bi bi-arrow-counterclockwise"></i> '+label;
    b.addEventListener('click',function(e){var d=currentDay();if(!d){e.preventDefault();alert('Chưa xác định được ngày đang xem.');return;}var msg=scope==='day'?'Xóa toàn bộ dữ liệu Sáng + Chiều của ngày '+d+' và tính lại điểm?':'Xóa dữ liệu buổi '+(shift==='chieu'?'Chiều':'Sáng')+' ngày '+d+' và tính lại điểm?';if(!confirm(msg))e.preventDefault();});
    f.appendChild(b);return f;
  }
  function addDeleteControls(){
    if(!window.TD_ROOM_CAN_DELETE)return;
    var heading=[].slice.call(document.querySelectorAll('h1,h2,h3,h4,h5,strong')).find(function(el){return norm(el.textContent)==='dữ liệu đã chấm trong tuần';});
    if(!heading||document.getElementById('td-room-delete-tools'))return;
    var day=currentDay(); if(!day)return;
    var box=document.createElement('div');box.id='td-room-delete-tools';box.className='d-flex gap-2 flex-wrap align-items-center mb-3 p-2 border rounded bg-light';
    var title=document.createElement('span');title.className='small fw-bold me-auto';title.innerHTML='<i class="bi bi-clock-history"></i> Hoàn tác dữ liệu ngày <span class="text-primary">'+day.split('-').reverse().join('/')+'</span>';
    box.appendChild(title);box.appendChild(actionForm('shift','sang','Xóa buổi Sáng','btn-outline-warning'));box.appendChild(actionForm('shift','chieu','Xóa buổi Chiều','btn-outline-warning'));box.appendChild(actionForm('day','','Xóa cả ngày','btn-outline-danger'));
    var container=heading.closest('section')||heading.parentElement; if(container){var target=heading.nextElementSibling; if(target)container.insertBefore(box,target);else container.appendChild(box);}
  }
  function explainAverage(){
    document.querySelectorAll('table').forEach(function(table){var hs=[].slice.call(table.querySelectorAll('thead th')).map(function(x){return norm(x.textContent)});if(hs.indexOf('điểm tb/ngày')>=0){var cap=table.previousElementSibling;if(cap&&norm(cap.textContent).indexOf('tổng điểm các ngày')>=0)cap.textContent='Điểm TB/ngày = điểm chuẩn ngày − điểm trừ bình quân theo số ngày được tính';}});
  }
  function run(){removeTotalDayColumn();addDeleteControls();explainAverage();}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',run);else run();
})();

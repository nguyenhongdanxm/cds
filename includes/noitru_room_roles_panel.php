<?php
/** Gán GV phụ trách / Trưởng phòng / Phó phòng ngay tại Danh sách > Phòng. */
if (!can_perm_level('nt.chiaphong', 'edit')) return;
?>
<section class="card card-soft mb-3" id="ntRoomRolesPanel">
  <div class="card-body py-3">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
      <div>
        <div class="fw-bold"><i class="bi bi-person-badge me-1 text-primary"></i> Gán vai trò phòng</div>
        <div class="small text-muted">Dành cho quản trị hoặc tài khoản có quyền <strong>Chia phòng – Sửa</strong>.</div>
      </div>
      <span class="badge text-bg-light border" id="ntRoleState">Đang tải dữ liệu…</span>
    </div>
    <form id="ntRoomRolesForm" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="save_room_roles">
      <div class="col-md-3">
        <label class="form-label small mb-1">Phòng</label>
        <select class="form-select" name="room" id="ntRoleRoom" required><option value="">-- Chọn phòng --</option></select>
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Giáo viên phụ trách</label>
        <select class="form-select" name="teacher_id" id="ntRoleTeacher"><option value="">-- Chưa gán --</option></select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Trưởng phòng</label>
        <select class="form-select" name="leader_id" id="ntRoleLeader"><option value="">-- Chưa gán --</option></select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Phó phòng</label>
        <select class="form-select" name="deputy_id" id="ntRoleDeputy"><option value="">-- Chưa gán --</option></select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-primary" id="ntRoleSave" type="submit"><i class="bi bi-floppy"></i> Lưu phân công</button>
      </div>
    </form>
    <div class="small text-muted mt-2" id="ntRoleHint">Chọn phòng để hiển thị học sinh làm Trưởng/Phó phòng.</div>
  </div>
</section>
<script>
(function(){
 const panel=document.getElementById('ntRoomRolesPanel'); if(!panel)return;
 const room=document.getElementById('ntRoleRoom'),teacher=document.getElementById('ntRoleTeacher'),leader=document.getElementById('ntRoleLeader'),deputy=document.getElementById('ntRoleDeputy'),state=document.getElementById('ntRoleState'),hint=document.getElementById('ntRoleHint'),form=document.getElementById('ntRoomRolesForm'),save=document.getElementById('ntRoleSave');
 let data={rooms:[],teachers:[]};
 const esc=s=>String(s??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
 function opts(select,rows,empty,label){select.innerHTML='<option value="">'+empty+'</option>'+rows.map(x=>'<option value="'+esc(x.id)+'">'+esc(label(x))+'</option>').join('');}
 function current(){return data.rooms.find(x=>x.name===room.value)||null;}
 function renderRoom(){const r=current();opts(leader,r?.students||[],'-- Chưa gán --',x=>(x.class_name?x.class_name+' – ':'')+x.name);opts(deputy,r?.students||[],'-- Chưa gán --',x=>(x.class_name?x.class_name+' – ':'')+x.name);if(r){teacher.value=r.teacher_id||'';leader.value=r.leader_id||'';deputy.value=r.deputy_id||'';hint.textContent=(r.students?.length||0)+' học sinh trong phòng '+r.name+'.';}else{teacher.value='';hint.textContent='Chọn phòng để hiển thị học sinh làm Trưởng/Phó phòng.';}}
 fetch('noitru_room_roles_data.php',{cache:'no-store'}).then(r=>r.json()).then(d=>{if(!d.ok)throw new Error(d.message||'Không tải được dữ liệu.');data=d;room.innerHTML='<option value="">-- Chọn phòng --</option>'+data.rooms.map(x=>'<option value="'+esc(x.name)+'">'+esc(x.name)+' ('+(x.students?.length||0)+' HS)</option>').join('');opts(teacher,data.teachers,'-- Chưa gán --',x=>x.name);const q=new URLSearchParams(location.search).get('room');if(q&&data.rooms.some(x=>x.name===q)){room.value=q;renderRoom();}state.className='badge text-bg-success';state.textContent='Sẵn sàng';}).catch(e=>{state.className='badge text-bg-danger';state.textContent='Lỗi dữ liệu';hint.textContent=e.message;});
 room.addEventListener('change',renderRoom);
 form.addEventListener('submit',async function(e){e.preventDefault();if(!room.value){room.focus();return;}if(leader.value&&leader.value===deputy.value){alert('Trưởng phòng và Phó phòng phải là hai học sinh khác nhau.');return;}save.disabled=true;state.className='badge text-bg-warning';state.textContent='Đang lưu…';try{const res=await fetch('noitru_room_roles.php',{method:'POST',body:new FormData(form)});if(!res.ok)throw new Error('HTTP '+res.status);state.className='badge text-bg-success';state.textContent='Đã lưu';setTimeout(()=>location.reload(),350);}catch(err){state.className='badge text-bg-danger';state.textContent='Không lưu được';alert('Không lưu được phân công: '+err.message);}finally{save.disabled=false;}});
})();
</script>

<?php
/** Xóa phân công thủ công bằng endpoint độc lập, không phụ thuộc form trang. */
if (!isset($current) || !in_array($current, ['them', 'danhsach'], true)) return;

$file = dirname(__DIR__) . '/data/manual_assignments.json';
$rows = [];
if (is_file($file)) {
    $decoded = json_decode((string)file_get_contents($file), true);
    if (is_array($decoded)) $rows = array_values(array_filter($decoded, 'is_array'));
}
$rowsJson = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
?>
<script>
(function(){
  var rows=<?= $rowsJson ?>;
  var busy=false;
  function norm(v){return String(v==null?'':v).replace(/\s+/g,' ').trim().toLowerCase()}
  function number(v){return parseFloat(String(v||'').replace(',','.'))||0}

  function idFromForm(button){
    var form=button.closest('form');
    if(!form)return '';
    var input=form.querySelector('input[name="manual_id"]');
    return input ? String(input.value||'').trim() : '';
  }

  function idFromTableRow(button){
    var tr=button.closest('tr');
    if(!tr)return '';
    var cells=tr.cells;
    if(!cells||cells.length<4)return '';
    var teacher=norm(cells[0].textContent);
    var subject=norm(cells[1].textContent);
    var className=norm(cells[2].textContent);
    var periods=number(cells[3].textContent);
    for(var i=0;i<rows.length;i++){
      var r=rows[i];
      if(norm(r.teacher)===teacher && norm(r.subject)===subject && norm(r.class_name)===className && Math.abs(number(r.periods)-periods)<0.001){
        return String(r.id||'');
      }
    }
    return '';
  }

  function idFromChip(button){
    var chip=button.closest('.cds-manual-chip');
    if(!chip)return '';
    var text=norm(chip.textContent).replace(/\btc\b/g,'').replace(/[×x]$/,'').trim();
    for(var i=0;i<rows.length;i++){
      var r=rows[i];
      var expected=norm((r.subject||'')+' '+(r.class_name||'')+' ('+String(Math.round(number(r.periods)*10)/10).replace('.',',')+'t)');
      if(text.indexOf(expected)>=0)return String(r.id||'');
    }
    return '';
  }

  async function removeManual(id, button){
    if(busy||!id)return;
    if(!window.confirm('Xóa phân công thủ công này?'))return;
    busy=true;
    if(button)button.disabled=true;
    try{
      var body=new URLSearchParams();body.set('manual_id',id);
      var response=await fetch('/chuyenmon/cds_manual_delete.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Requested-With':'XMLHttpRequest'},
        credentials:'same-origin',
        body:body.toString()
      });
      var data=null;
      try{data=await response.json()}catch(e){}
      if(!response.ok||!data||!data.ok)throw new Error((data&&data.message)||('Không thể xóa, mã lỗi '+response.status));
      window.location.reload();
    }catch(error){
      window.alert(error&&error.message?error.message:'Không thể xóa phân công thủ công.');
      busy=false;if(button)button.disabled=false;
    }
  }

  document.addEventListener('click',function(e){
    var button=e.target.closest('button');
    if(!button)return;
    var isDelete=button.closest('.cds-manual-chip-delete') || button.closest('#cdsManualAssignments') && button.querySelector('.bi-trash');
    if(!isDelete)return;
    e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();
    var id=idFromForm(button)||idFromTableRow(button)||idFromChip(button);
    if(!id){window.alert('Không xác định được phân công cần xóa. Vui lòng tải lại trang và thử lại.');return;}
    removeManual(id,button);
  },true);

  document.addEventListener('submit',function(e){
    var form=e.target;
    if(!(form instanceof HTMLFormElement))return;
    var action=form.querySelector('input[name="cds_manual_action"][value="delete"]');
    if(!action)return;
    e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();
    var button=form.querySelector('button[type="submit"],button');
    var idInput=form.querySelector('input[name="manual_id"]');
    var id=idInput?String(idInput.value||'').trim():(button?(idFromTableRow(button)||idFromChip(button)):'');
    if(!id){window.alert('Không xác định được phân công cần xóa.');return;}
    removeManual(id,button);
  },true);
})();
</script>

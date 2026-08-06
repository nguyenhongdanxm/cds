<?php
/** Hiển thị và gắn phân công thủ công vào bảng phân công theo giáo viên. */
if (!isset($current) || !in_array($current, ['them', 'danhsach'], true)) return;

$manualPage = $current;
$manualFile = dirname(__DIR__) . '/data/manual_assignments.json';
$manualCanEdit = function_exists('cds_can_feature')
    ? cds_can_feature('cm.pccm', 'edit')
    : !empty($_SESSION['pccm_admin']);
$manualRows = [];
if (is_file($manualFile)) {
    $decoded = json_decode((string)file_get_contents($manualFile), true);
    if (is_array($decoded)) $manualRows = array_values(array_filter($decoded, 'is_array'));
}
$manualRowsJson = json_encode($manualRows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
$formatPeriods = static function ($value): string {
    return rtrim(rtrim(number_format((float)$value, 1, '.', ''), '0'), '.');
};
?>
<style>
.cds-manual-panel{margin:1rem 0;border:1px solid #dbe5ee;border-radius:14px;background:#fff;box-shadow:0 3px 14px rgba(15,23,42,.06);overflow:hidden}.cds-manual-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.8rem 1rem;background:#eef5fb;color:#173f65}.cds-manual-head h5{margin:0;font-size:1rem}.cds-manual-body{padding:1rem}.cds-manual-grid{display:grid;grid-template-columns:1.15fr 1.15fr .8fr .55fr 1fr auto;gap:.6rem;align-items:end}.cds-manual-list{margin-top:1rem}.cds-manual-badge{display:inline-flex;padding:.2rem .48rem;border-radius:999px;background:#e8f3ff;color:#174b73;font-weight:700}.cds-manual-empty{padding:1rem;text-align:center;color:#64748b}.cds-manual-chip{display:inline-flex;align-items:center;gap:.3rem;margin:.16rem .2rem .16rem 0;padding:.24rem .55rem;border:1px dashed #7c3aed;border-radius:999px;background:#f5f0ff;color:#5b21b6;font-size:.82rem;font-weight:650}.cds-manual-chip small{color:#6d28d9}.cds-manual-mark{margin-left:.25rem;padding:.08rem .28rem;border-radius:5px;background:#7c3aed;color:#fff;font-size:.62rem;font-weight:800}@media(max-width:1100px){.cds-manual-grid{grid-template-columns:1fr 1fr 1fr}.cds-manual-grid .manual-submit{grid-column:1/-1}}@media(max-width:650px){.cds-manual-grid{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var manualRows=<?= $manualRowsJson ?>;
  var esc=function(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]})};
  var periodText=function(v){var n=Math.round((parseFloat(v)||0)*10)/10;return String(n).replace('.',',')};
  var wrap=document.createElement('section');wrap.id='cdsManualAssignments';wrap.className='cds-manual-panel';
  wrap.innerHTML=<?= json_encode(
    '<div class="cds-manual-head"><h5><i class="bi bi-pencil-square me-1"></i> Phân công thủ công</h5><span class="small">Môn, lớp và số tiết tùy chọn</span></div><div class="cds-manual-body">'
    .($manualCanEdit?
      '<form method="post" class="cds-manual-grid">'
      .'<input type="hidden" name="cds_manual_action" value="save">'
      .'<input type="hidden" name="manual_return_page" value="'.htmlspecialchars($manualPage,ENT_QUOTES,'UTF-8').'">'
      .'<div><label class="form-label">Giáo viên</label><input class="form-control" name="manual_teacher" list="cdsTeacherNames" required placeholder="Chọn hoặc nhập giáo viên"><datalist id="cdsTeacherNames"></datalist></div>'
      .'<div><label class="form-label">Môn/Nội dung tùy chọn</label><input class="form-control" name="manual_subject" required maxlength="150" placeholder="Ví dụ: Giáo dục địa phương"></div>'
      .'<div><label class="form-label">Lớp</label><input class="form-control" name="manual_class" list="cdsClassNames" required placeholder="Chọn hoặc nhập lớp"><datalist id="cdsClassNames"></datalist></div>'
      .'<div><label class="form-label">Số tiết</label><input class="form-control" type="number" name="manual_periods" min="0.1" step="0.1" inputmode="decimal" required></div>'
      .'<div><label class="form-label">Ghi chú</label><input class="form-control" name="manual_note" maxlength="200"></div>'
      .'<div class="manual-submit"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Thêm</button></div></form>'
      :'<div class="alert alert-info mb-0">Bạn có quyền xem nhưng không có quyền thêm phân công thủ công.</div>')
    .'<div class="cds-manual-list"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Giáo viên</th><th>Môn/Nội dung</th><th>Lớp</th><th class="text-center">Số tiết</th><th>Ghi chú</th>'.($manualCanEdit?'<th></th>':'').'</tr></thead><tbody>'
    .(!$manualRows?'<tr><td colspan="6" class="cds-manual-empty">Chưa có phân công thủ công.</td></tr>':implode('',array_map(function($r)use($manualCanEdit,$manualPage,$formatPeriods){return '<tr><td><strong>'.htmlspecialchars((string)($r['teacher']??''),ENT_QUOTES,'UTF-8').'</strong></td><td>'.htmlspecialchars((string)($r['subject']??''),ENT_QUOTES,'UTF-8').'</td><td>'.htmlspecialchars((string)($r['class_name']??''),ENT_QUOTES,'UTF-8').'</td><td class="text-center"><span class="cds-manual-badge">'.$formatPeriods($r['periods']??0).'</span></td><td>'.htmlspecialchars((string)($r['note']??''),ENT_QUOTES,'UTF-8').'</td>'.($manualCanEdit?'<td><form method="post" onsubmit="return confirm(\'Xóa phân công thủ công này?\')"><input type="hidden" name="cds_manual_action" value="delete"><input type="hidden" name="manual_return_page" value="'.htmlspecialchars($manualPage,ENT_QUOTES,'UTF-8').'"><input type="hidden" name="manual_id" value="'.htmlspecialchars((string)($r['id']??''),ENT_QUOTES,'UTF-8').'"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>':'').'</tr>';},$manualRows)))
    .'</tbody></table></div></div></div>',
    JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES
  ) ?>;

  var host=null;
  <?php if ($manualPage === 'them'): ?>
  var cards=document.querySelectorAll('body > .container .card, body > .container section, main .card, main section');
  if(cards.length){cards[0].insertAdjacentElement('afterend',wrap);host=cards[0].parentElement}
  <?php endif; ?>
  if(!host){host=document.querySelector('body > .container')||document.querySelector('main')||document.body;host.appendChild(wrap)}

  var normalize=function(s){return String(s||'').replace(/\s+/g,' ').trim()};
  var teacherDl=document.getElementById('cdsTeacherNames'),classDl=document.getElementById('cdsClassNames');
  var teacherSeen={},classSeen={};
  document.querySelectorAll('select').forEach(function(sel){
    var n=(sel.name||'').toLowerCase();
    sel.querySelectorAll('option').forEach(function(op){
      var val=normalize(op.textContent);if(!val||val==='--')return;
      if(/giao|teacher|gv/.test(n)&&teacherDl&&!teacherSeen[val]){teacherSeen[val]=1;var o=document.createElement('option');o.value=val;teacherDl.appendChild(o)}
      if(/lop|class/.test(n)&&classDl&&!classSeen[val]){classSeen[val]=1;var c=document.createElement('option');c.value=val;classDl.appendChild(c)}
    })
  });

  function findTeacherBlock(name){
    var targets=document.querySelectorAll('h2,h3,h4,h5,h6,strong,.fw-bold,.teacher-name');
    for(var i=0;i<targets.length;i++){
      if(normalize(targets[i].textContent)!==normalize(name))continue;
      var node=targets[i];
      for(var depth=0;depth<7&&node;depth++,node=node.parentElement){
        var txt=normalize(node.textContent);
        if(/Dạy\s*[:0-9]/i.test(txt)&&(/KN\s*[:0-9]/i.test(txt)||/Tổng\s*[0-9]/i.test(txt)))return node;
      }
      return targets[i].parentElement;
    }
    return null;
  }
  function findTeachingLine(block){
    var nodes=block.querySelectorAll('div,p,span,td');
    for(var i=0;i<nodes.length;i++){
      var txt=normalize(nodes[i].textContent);
      if(/^Dạy\s*:/i.test(txt)||(/^Dạy\b/i.test(txt)&&txt.length<250))return nodes[i];
    }
    return null;
  }
  var sums={};manualRows.forEach(function(r){var k=normalize(r.teacher);sums[k]=(sums[k]||0)+(parseFloat(r.periods)||0)});
  Object.keys(sums).forEach(function(teacher){
    var block=findTeacherBlock(teacher);if(!block)return;
    var line=findTeachingLine(block)||block;
    manualRows.filter(function(r){return normalize(r.teacher)===teacher}).forEach(function(r){
      var chip=document.createElement('span');chip.className='cds-manual-chip';
      chip.innerHTML='<i class="bi bi-pencil-square"></i>'+esc(r.subject)+' '+esc(r.class_name||'')+' ('+periodText(r.periods)+'t)<span class="cds-manual-mark">TC</span>';
      line.appendChild(chip);
    });
    var add=Math.round(sums[teacher]*10)/10;
    block.querySelectorAll('*').forEach(function(el){
      if(el.children.length>0)return;
      var t=el.textContent||'',n=t;
      n=n.replace(/Dạy\s+([0-9]+(?:[.,][0-9]+)?)/i,function(m,v){return 'Dạy '+periodText(parseFloat(v.replace(',','.'))+add)});
      n=n.replace(/Tổng\s+([0-9]+(?:[.,][0-9]+)?)(\s*\/)/i,function(m,v,slash){return 'Tổng '+periodText(parseFloat(v.replace(',','.'))+add)+slash});
      if(n!==t)el.textContent=n;
    });
  });
});
</script>

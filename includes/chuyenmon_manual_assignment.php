<?php
/** Phân công thủ công môn và số tiết trên trang Phân công/Danh sách PCCM. */
if (!isset($current) || !in_array($current, ['them','danhsach'], true)) return;

$manualPage = $current;
$manualFile = dirname(__DIR__) . '/data/manual_assignments.json';
$manualCanEdit = function_exists('cds_can_feature') ? cds_can_feature('cm.pccm', 'edit') : !empty($_SESSION['pccm_admin']);

$manualLoad = static function () use ($manualFile): array {
    if (!is_file($manualFile)) return [];
    $rows = json_decode((string)file_get_contents($manualFile), true);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
};
$manualSave = static function (array $rows) use ($manualFile): void {
    $dir = dirname($manualFile);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    file_put_contents($manualFile, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
};
$manualNumber = static function ($value): float {
    $value = str_replace(',', '.', trim((string)$value));
    return round(max(0, (float)$value), 1);
};

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['cds_manual_action'])) {
    if (!$manualCanEdit) {
        http_response_code(403);
        exit('Bạn không có quyền sửa phân công chuyên môn.');
    }
    $rows = $manualLoad();
    $action = (string)$_POST['cds_manual_action'];
    if ($action === 'save') {
        $teacher = trim((string)($_POST['manual_teacher'] ?? ''));
        $subject = trim((string)($_POST['manual_subject'] ?? ''));
        $periods = $manualNumber($_POST['manual_periods'] ?? 0);
        $note = trim((string)($_POST['manual_note'] ?? ''));
        if ($teacher === '' || $subject === '' || $periods <= 0) {
            $_SESSION['pccm_flash'] = ['type'=>'danger','message'=>'Vui lòng nhập giáo viên, môn và số tiết lớn hơn 0.'];
        } else {
            $rows[] = [
                'id' => 'ma_' . bin2hex(random_bytes(5)),
                'teacher' => $teacher,
                'subject' => $subject,
                'periods' => $periods,
                'note' => $note,
                'created_by' => (string)($_SESSION['cds_user']['name'] ?? $_SESSION['cds_user']['username'] ?? ''),
                'created_at' => date('c'),
            ];
            $manualSave($rows);
            $_SESSION['pccm_flash'] = ['type'=>'success','message'=>'Đã thêm phân công thủ công.'];
        }
    } elseif ($action === 'delete') {
        $id = (string)($_POST['manual_id'] ?? '');
        $rows = array_values(array_filter($rows, static fn($row) => (string)($row['id'] ?? '') !== $id));
        $manualSave($rows);
        $_SESSION['pccm_flash'] = ['type'=>'warning','message'=>'Đã xóa phân công thủ công.'];
    }
    $returnPage = in_array((string)($_POST['manual_return_page'] ?? ''), ['them','danhsach'], true)
        ? (string)$_POST['manual_return_page']
        : $manualPage;
    header('Location: ' . $returnPage . '.php#cdsManualAssignments');
    exit;
}

$manualRows = $manualLoad();
?>
<style>
.cds-manual-panel{margin:1rem 0;border:1px solid #dbe5ee;border-radius:14px;background:#fff;box-shadow:0 3px 14px rgba(15,23,42,.06);overflow:hidden}.cds-manual-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.8rem 1rem;background:#eef5fb;color:#173f65}.cds-manual-head h5{margin:0;font-size:1rem}.cds-manual-body{padding:1rem}.cds-manual-grid{display:grid;grid-template-columns:1.25fr 1.25fr .55fr 1fr auto;gap:.6rem;align-items:end}.cds-manual-list{margin-top:1rem}.cds-manual-badge{display:inline-flex;padding:.2rem .48rem;border-radius:999px;background:#e8f3ff;color:#174b73;font-weight:700}.cds-manual-empty{padding:1rem;text-align:center;color:#64748b}@media(max-width:900px){.cds-manual-grid{grid-template-columns:1fr 1fr}.cds-manual-grid .manual-submit{grid-column:1/-1}}@media(max-width:600px){.cds-manual-grid{grid-template-columns:1fr}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){
  var wrap=document.createElement('section');wrap.id='cdsManualAssignments';wrap.className='cds-manual-panel';
  wrap.innerHTML=<?= json_encode('<div class="cds-manual-head"><h5><i class="bi bi-pencil-square me-1"></i> Phân công thủ công</h5><span class="small">Môn tự chọn · số tiết bước 0,1</span></div><div class="cds-manual-body">'.($manualCanEdit?'<form method="post" class="cds-manual-grid"><input type="hidden" name="cds_manual_action" value="save"><input type="hidden" name="manual_return_page" value="'.htmlspecialchars($manualPage,ENT_QUOTES,'UTF-8').'"><div><label class="form-label">Giáo viên</label><input class="form-control" name="manual_teacher" list="cdsTeacherNames" required placeholder="Nhập hoặc chọn giáo viên"><datalist id="cdsTeacherNames"></datalist></div><div><label class="form-label">Môn/Nội dung tùy chọn</label><input class="form-control" name="manual_subject" required maxlength="150" placeholder="Ví dụ: Giáo dục địa phương"></div><div><label class="form-label">Số tiết</label><input class="form-control" type="number" name="manual_periods" min="0.1" step="0.1" inputmode="decimal" required></div><div><label class="form-label">Ghi chú</label><input class="form-control" name="manual_note" maxlength="200"></div><div class="manual-submit"><button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg"></i> Thêm</button></div></form>':'<div class="alert alert-info mb-0">Bạn có quyền xem nhưng không có quyền thêm phân công thủ công.</div>').'<div class="cds-manual-list"><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Giáo viên</th><th>Môn/Nội dung</th><th class="text-center">Số tiết</th><th>Ghi chú</th><th>Người tạo</th>'.($manualCanEdit?'<th></th>':'').'</tr></thead><tbody>'.(!$manualRows?'<tr><td colspan="6" class="cds-manual-empty">Chưa có phân công thủ công.</td></tr>':implode('',array_map(function($r)use($manualCanEdit,$manualPage){return '<tr><td><strong>'.htmlspecialchars((string)($r['teacher']??''),ENT_QUOTES,'UTF-8').'</strong></td><td>'.htmlspecialchars((string)($r['subject']??''),ENT_QUOTES,'UTF-8').'</td><td class="text-center"><span class="cds-manual-badge">'.rtrim(rtrim(number_format((float)($r['periods']??0),1,'.',''),'0'),'.').'</span></td><td>'.htmlspecialchars((string)($r['note']??''),ENT_QUOTES,'UTF-8').'</td><td><small>'.htmlspecialchars((string)($r['created_by']??''),ENT_QUOTES,'UTF-8').'</small></td>'.($manualCanEdit?'<td><form method="post" onsubmit="return confirm(\'Xóa phân công thủ công này?\')"><input type="hidden" name="cds_manual_action" value="delete"><input type="hidden" name="manual_return_page" value="'.htmlspecialchars($manualPage,ENT_QUOTES,'UTF-8').'"><input type="hidden" name="manual_id" value="'.htmlspecialchars((string)($r['id']??''),ENT_QUOTES,'UTF-8').'"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td>':'').'</tr>';},$manualRows))).'</tbody></table></div></div></div>', JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  var host=null;
  <?php if ($manualPage === 'them'): ?>
  var cards=document.querySelectorAll('body > .container .card, body > .container section, main .card, main section');
  if(cards.length){
    var first=cards[0];
    first.insertAdjacentElement('afterend',wrap);
    host=first.parentElement;
  }
  <?php endif; ?>
  if(!host){host=document.querySelector('body > .container')||document.querySelector('main')||document.body;host.appendChild(wrap)}

  var dl=document.getElementById('cdsTeacherNames');if(dl){var seen={};document.querySelectorAll('select option, table tbody tr').forEach(function(el){var name='';if(el.tagName==='OPTION')name=(el.textContent||'').trim();else{var cell=el.querySelector('td');if(cell)name=(cell.textContent||'').trim()}name=name.replace(/\s+/g,' ');if(name&&name!=='--'&&name.length<100&&!seen[name]){seen[name]=1;var o=document.createElement('option');o.value=name;dl.appendChild(o)}})}
});
</script>

<?php
require_once __DIR__ . '/includes/student_card_store.php';
$id = trim((string)($_GET['id'] ?? ''));
$token = trim((string)($_GET['t'] ?? ''));
$validToken = student_card_is_valid_token($id, $token);
$student = $validToken ? csdl_student_find($id) : null;
$classes = student_card_class_map();
$class = $student ? ($classes[(string)($student['class_id'] ?? '')] ?? []) : [];
$active = $student && !empty($student['active']);
$status = !$validToken || !$student ? 'invalid' : ($active ? 'valid' : 'inactive');
$year = csdl_year_current()['label'] ?? SCHOOL_YEAR;
$homeroomTeacher = [];
if ($student && !empty($class['homeroom_teacher_id'])) {
    $homeroomTeacher = csdl_teacher_find((string)$class['homeroom_teacher_id']) ?? [];
}
$homeroomName = trim((string)($homeroomTeacher['name'] ?? $class['homeroom_teacher_name'] ?? ''));
$homeroomPhone = trim((string)($homeroomTeacher['phone'] ?? $class['homeroom_teacher_phone'] ?? ''));
$photoUrl = $validToken && $student
    ? BASE_URL . 'student_photo.php?id=' . rawurlencode($id) . '&t=' . rawurlencode($token)
    : '';
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow, noarchive');
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Xác minh thẻ học sinh</title>
<style>body{margin:0;background:#eef3f8;font-family:Arial,sans-serif;color:#183047}.wrap{max-width:560px;margin:0 auto;padding:24px}.card{background:#fff;border-radius:18px;box-shadow:0 8px 30px rgba(25,55,85,.12);overflow:hidden}.head{background:#1f4e79;color:#fff;padding:22px;text-align:center}.body{padding:24px}.status{border-radius:12px;padding:13px;text-align:center;font-weight:800;margin-bottom:18px}.valid{background:#dcfce7;color:#166534}.inactive{background:#fef3c7;color:#92400e}.invalid{background:#fee2e2;color:#991b1b}.portrait{width:120px;height:160px;display:block;margin:0 auto 14px;object-fit:cover;border:4px solid #fff;border-radius:10px;box-shadow:0 3px 14px rgba(20,50,80,.18);background:#e8eef5}.name{font-size:24px;font-weight:900;text-transform:uppercase;color:#1f4e79;text-align:center}.class-line{text-align:center;font-size:17px;font-weight:800;color:#315a80;margin-top:5px}.meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.item{background:#f5f8fb;padding:11px;border-radius:10px}.item.wide{grid-column:1/-1}.item small{display:block;color:#66788a;margin-bottom:4px}.item strong{overflow-wrap:anywhere}.foot{text-align:center;color:#6b7c8d;font-size:13px;margin-top:20px}@media(max-width:480px){.meta{grid-template-columns:1fr}.item.wide{grid-column:auto}.wrap{padding:12px}}</style></head><body><div class="wrap"><div class="card"><div class="head"><strong><?= htmlspecialchars(SCHOOL_NAME,ENT_QUOTES,'UTF-8') ?></strong><div style="margin-top:5px">XÁC MINH THẺ HỌC SINH</div></div><div class="body">
<?php if($status==='valid'): ?><div class="status valid">✓ Thẻ hợp lệ – Học sinh đang học</div><img class="portrait" src="<?=htmlspecialchars($photoUrl,ENT_QUOTES,'UTF-8')?>" alt="Ảnh học sinh" onerror="this.style.display='none'"><div class="name"><?=htmlspecialchars($student['name']??'',ENT_QUOTES,'UTF-8')?></div><div class="class-line">Lớp: <?=htmlspecialchars($class['name']??'—',ENT_QUOTES,'UTF-8')?></div><div class="meta"><div class="item"><small>Năm học</small><strong><?=htmlspecialchars($year,ENT_QUOTES,'UTF-8')?></strong></div><div class="item"><small>Số CCCD</small><strong><?=htmlspecialchars(trim((string)($student['cccd']??''))?:'—',ENT_QUOTES,'UTF-8')?></strong></div><div class="item wide"><small>Phụ huynh</small><strong><?=htmlspecialchars(trim((string)($student['parent_name']??''))?:'—',ENT_QUOTES,'UTF-8')?><?=!empty($student['parent_phone'])?' · '.htmlspecialchars((string)$student['parent_phone'],ENT_QUOTES,'UTF-8'):''?></strong></div><div class="item wide"><small>Giáo viên chủ nhiệm</small><strong><?=htmlspecialchars($homeroomName?:'—',ENT_QUOTES,'UTF-8')?><?=$homeroomPhone!==''?' · '.htmlspecialchars($homeroomPhone,ENT_QUOTES,'UTF-8'):''?></strong></div></div>
<?php elseif($status==='inactive'): ?><div class="status inactive">Thẻ không còn hiệu lực</div><div class="name"><?=htmlspecialchars($student['name']??'',ENT_QUOTES,'UTF-8')?></div><div class="foot">Học sinh hiện không ở trạng thái đang học. Vui lòng liên hệ nhà trường để kiểm tra.</div>
<?php else: ?><div class="status invalid">Mã thẻ không hợp lệ hoặc không tồn tại</div><div class="foot">Không thể xác minh thẻ này. Vui lòng kiểm tra lại mã QR hoặc liên hệ nhà trường.</div><?php endif; ?>
<div class="foot">Thông tin chỉ hiển thị khi mã xác minh trên thẻ hợp lệ.</div></div></div></div></body></html>

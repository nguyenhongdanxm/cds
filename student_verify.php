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
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Xác minh thẻ học sinh</title>
<style>body{margin:0;background:#eef3f8;font-family:Arial,sans-serif;color:#183047}.wrap{max-width:520px;margin:0 auto;padding:24px}.card{background:#fff;border-radius:18px;box-shadow:0 8px 30px rgba(25,55,85,.12);overflow:hidden}.head{background:#1f4e79;color:#fff;padding:22px;text-align:center}.body{padding:24px}.status{border-radius:12px;padding:13px;text-align:center;font-weight:800;margin-bottom:18px}.valid{background:#dcfce7;color:#166534}.inactive{background:#fef3c7;color:#92400e}.invalid{background:#fee2e2;color:#991b1b}.name{font-size:24px;font-weight:900;text-transform:uppercase;color:#1f4e79;text-align:center}.meta{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:18px}.item{background:#f5f8fb;padding:11px;border-radius:10px}.item small{display:block;color:#66788a;margin-bottom:4px}.foot{text-align:center;color:#6b7c8d;font-size:13px;margin-top:20px}@media(max-width:480px){.meta{grid-template-columns:1fr}.wrap{padding:12px}}</style></head><body><div class="wrap"><div class="card"><div class="head"><strong><?= htmlspecialchars(SCHOOL_NAME,ENT_QUOTES,'UTF-8') ?></strong><div style="margin-top:5px">XÁC MINH THẺ HỌC SINH</div></div><div class="body">
<?php if($status==='valid'): ?><div class="status valid">✓ Thẻ hợp lệ – Học sinh đang học</div><div class="name"><?=htmlspecialchars($student['name']??'',ENT_QUOTES,'UTF-8')?></div><div class="meta"><div class="item"><small>Mã học sinh</small><strong><?=htmlspecialchars(student_card_public_code($student),ENT_QUOTES,'UTF-8')?></strong></div><div class="item"><small>Lớp</small><strong><?=htmlspecialchars($class['name']??'—',ENT_QUOTES,'UTF-8')?></strong></div><div class="item"><small>Năm học</small><strong><?=htmlspecialchars($year,ENT_QUOTES,'UTF-8')?></strong></div><div class="item"><small>Trạng thái</small><strong>Đang học</strong></div></div>
<?php elseif($status==='inactive'): ?><div class="status inactive">Thẻ không còn hiệu lực</div><div class="name"><?=htmlspecialchars($student['name']??'',ENT_QUOTES,'UTF-8')?></div><div class="foot">Học sinh hiện không ở trạng thái đang học. Vui lòng liên hệ nhà trường để kiểm tra.</div>
<?php else: ?><div class="status invalid">Mã thẻ không hợp lệ hoặc không tồn tại</div><div class="foot">Không thể xác minh thẻ này. Vui lòng kiểm tra lại mã QR hoặc liên hệ nhà trường.</div><?php endif; ?>
<div class="foot">Trang xác minh không hiển thị CCCD, địa chỉ hay thông tin liên hệ riêng tư.</div></div></div></div></body></html>
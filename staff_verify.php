<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/staff_card_store.php';

$id = trim((string)($_GET['id'] ?? ''));
$token = trim((string)($_GET['t'] ?? ''));
$teacher = staff_card_is_valid_token($id, $token) ? csdl_teacher_find($id) : null;
$group = $teacher ? trim((string)($teacher['to_chuyen_mon'] ?? $teacher['pccm_group'] ?? '')) : '';
$position = $teacher ? trim((string)($teacher['position'] ?? $teacher['chuc_vu'] ?? '')) : '';
if ($teacher && $position === '' && function_exists('csdl_format_kiem_nhiem')) $position = csdl_format_kiem_nhiem($teacher['kiem_nhiem'] ?? []);
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Xác minh thẻ CBGV</title><style>body{font-family:Arial,sans-serif;background:#eef3f8;margin:0;padding:24px;color:#17324d}.card{max-width:560px;margin:5vh auto;background:#fff;border-radius:18px;padding:28px;box-shadow:0 12px 35px #123f6d22;border-top:8px solid #1551a8}.ok{color:#15803d}.bad{color:#b91c1c}.row{padding:9px 0;border-bottom:1px solid #e5e7eb}.label{color:#64748b;font-size:13px}.value{font-weight:700;margin-top:3px}</style></head><body><main class="card"><?php if(!$teacher):?><h2 class="bad">Không xác minh được thẻ</h2><p>Mã xác minh không hợp lệ hoặc CBGV không còn trong hệ thống.</p><?php else:?><h2 class="ok">✓ Thẻ CBGV hợp lệ</h2><div class="row"><div class="label">Họ và tên</div><div class="value"><?=e($teacher['name']??'')?></div></div><div class="row"><div class="label">Mã CBGV</div><div class="value"><?=e($teacher['code']??'')?></div></div><div class="row"><div class="label">Chuyên môn</div><div class="value"><?=e($teacher['specialty']??'')?></div></div><div class="row"><div class="label">Tổ</div><div class="value"><?=e($group)?></div></div><div class="row"><div class="label">Chức vụ / kiêm nhiệm</div><div class="value"><?=e($position?:'Giáo viên')?></div></div><p style="color:#64748b;margin-bottom:0">Dữ liệu được xác minh trực tiếp từ hệ thống nhà trường.</p><?php endif;?></main></body></html>

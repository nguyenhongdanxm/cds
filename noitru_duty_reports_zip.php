<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_login();
require_module('noitru', 'view');
if (!can_perm('nt.lichtruc')) { http_response_code(403); exit('Bạn không có quyền xuất biên bản trực.'); }

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { http_response_code(400); exit('Khoảng ngày không hợp lệ.'); }
$start = new DateTimeImmutable($from);
$end = new DateTimeImmutable($to);
if ($start > $end || $start->diff($end)->days > 366) { http_response_code(400); exit('Khoảng xuất phải từ 1 đến 367 ngày.'); }
if (!class_exists('ZipArchive')) { http_response_code(500); exit('Máy chủ chưa bật PHP ZipArchive.'); }

$escape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$temp = tempnam(sys_get_temp_dir(), 'bbtruc_');
$zip = new ZipArchive();
if ($temp === false || $zip->open($temp, ZipArchive::OVERWRITE) !== true) { http_response_code(500); exit('Không tạo được tệp ZIP.'); }
$count = 0;
for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
    $day = $date->format('Y-m-d');
    $report = noitru_duty_report_for_date($day) ?? [];
    $dutyNames = [];
    foreach (noitru_duty_all() as $row) if (($row['date'] ?? '') === $day) $dutyNames[] = trim((string)($row['teacher_name'] ?? ''));
    $manager = noitru_duty_manager_for_date($day) ?? [];
    $dutyNames = array_values(array_unique(array_filter(array_merge($dutyNames, (array)($manager['teacher_names'] ?? [])))));
    $next = $date->modify('+1 day')->format('Y-m-d');
    $location = trim((string)($report['location'] ?? '')) ?: 'Phòng trực nội trú';
    $shift = trim((string)($report['shift_label'] ?? '')) ?: '06:00 ngày '.$date->format('d/m/Y').' đến 06:00 ngày '.date('d/m/Y', strtotime($next));
    $section = static function(string $key, array $row) use ($escape): string { $value=trim((string)($row[$key]??'')); return nl2br($escape($value!==''?$value:'Không có')); };
    $html = '<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Biên bản trực '.$escape($day).'</title><style>body{font:16px/1.5 "Times New Roman",serif;max-width:800px;margin:40px auto;color:#000}header{display:grid;grid-template-columns:1fr 1.4fr;text-align:center;font-weight:bold}h1{text-align:center;font-size:22px;margin-top:35px}.meta{margin:20px 0}.section{margin:14px 0}.label{font-weight:bold}@media print{@page{size:A4;margin:18mm}}</style></head><body><header><div>SỞ GD&amp;ĐT TUYÊN QUANG<br>TRƯỜNG PTDT NỘI TRÚ<br>THCS&amp;THPT XÍN MẦN</div><div>CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM<br>Độc lập - Tự do - Hạnh phúc</div></header><h1>BIÊN BẢN TRỰC NỘI TRÚ HẰNG NGÀY</h1><div class="meta"><b>Ngày trực:</b> '.$escape($date->format('d/m/Y')).'<br><b>Thời gian:</b> '.$escape($shift).'<br><b>Địa điểm:</b> '.$escape($location).'<br><b>Thành phần trực:</b> '.$escape(implode(', ', $dutyNames) ?: 'Chưa có dữ liệu').'</div><div class="section"><span class="label">1. Tình hình sinh hoạt và kỷ luật</span><br>'.$section('discipline',$report).'</div><div class="section"><span class="label">2. Sự việc phát sinh/vấn đề tồn đọng</span><br>'.$section('incidents',$report).'</div><div class="section"><span class="label">3. Ý kiến nhận xét của người trực</span><br>'.$section('assessment',$report).'</div><div class="section"><span class="label">4. Bàn giao ca sau</span><br>'.$section('handover',$report).'</div><p><i>Cập nhật: '.$escape($report['updated_at']??'Chưa lưu').' — '.$escape($report['updated_by']??'').'</i></p></body></html>';
    $zip->addFromString('bien-ban-truc-'.$day.'.html', $html);
    $count++;
}
$zip->addFromString('THONG-TIN.txt', "Biên bản trực từ $from đến $to\nSố tệp: $count\nMỗi tệp HTML có thể mở bằng trình duyệt và in ra PDF.\n");
$zip->close();
$filename = 'bien-ban-truc-'.$from.'-den-'.$to.'.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($temp));
readfile($temp);
@unlink($temp);
exit;

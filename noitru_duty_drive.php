<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/drive_google_doc.php';
require_login();
require_perm('nt.lichtruc');
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'message'=>'Phương thức không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$csrf = (string)($_POST['csrf'] ?? '');
if ($csrf === '' || !hash_equals(cds_drive_csrf_token(), $csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$date = trim((string)($_POST['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['ok'=>false,'message'=>'Ngày biên bản không hợp lệ.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$content = trim((string)($_POST['content'] ?? ''));
if ($content === '' || strlen($content) > 2500000) {
    echo json_encode(['ok'=>false,'message'=>'Nội dung biên bản trống hoặc quá lớn.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$title = 'Biên bản trực nội trú - ' . date('d-m-Y', strtotime($date));
$css = <<<'CSS'
@page{size:A4 portrait;margin:20mm 15mm 20mm 20mm}
html,body{margin:0;padding:0;background:#fff;color:#000;font-family:"Times New Roman",serif;font-size:13pt;line-height:1.25}
.page{box-sizing:border-box;width:175mm;margin:0 auto;background:#fff}
.report-national{display:table;width:100%;table-layout:fixed;text-align:center;font-weight:700}
.report-national>div{display:table-cell;vertical-align:top}.report-national>div:first-child{width:40%}.report-national>div:last-child{width:60%;padding-left:8mm}
.report-national p{margin:0}.report-national .report-agency{font-weight:400}.underline{display:inline-block;border-bottom:1px solid #000;padding-bottom:2px}
.report-place{text-align:right;font-style:italic;margin:8mm 0 5mm}h1{margin:0;text-align:center;font-size:15pt;font-weight:700}.report-year{text-align:center;font-weight:700;margin:1mm 0 7mm}
.report-section{margin:3mm 0}.report-section-title,.report-subtitle{font-weight:700}.report-subtitle{margin-top:2.5mm}.report-text,.report-entry-preview{white-space:pre-wrap;text-align:justify}
.report-attendance{width:100%;margin:2mm 0;border-collapse:collapse;font-size:12pt}.report-attendance th,.report-attendance td{border:1px solid #000;padding:1.5mm;text-align:center}
.report-signatures{width:100%;margin-top:7mm;border-collapse:collapse;table-layout:fixed;page-break-inside:avoid}.report-signatures td{height:50mm;border:0;padding:2mm;vertical-align:top;text-align:center}.report-signatures strong{display:block}.report-sign-name{margin-top:12mm;text-align:center}
.report-empty{color:#555;font-style:italic}
CSS;
$html = '<!doctype html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body><div class="page">' . $content . '</div></body></html>';
$result = cds_drive_upload_google_doc($html, $title, 'duty_reports', [
    'date'=>$date,
    'source_action'=>'page:/noitru.php?tab=duty_report',
    'report_date'=>$date,
]);
if (empty($result['ok'])) {
    http_response_code(500);
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}
echo json_encode($result, JSON_UNESCAPED_UNICODE);

<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google_drive_storage.php';
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

/* Lưu tệp Word trực tiếp để không phải chuyển đổi qua Google Docs. */
if (class_exists('DOMDocument')) {
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="duty-export-root">'.$content.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    foreach (['script','button','input','textarea','form'] as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        for ($i = $nodes->length - 1; $i >= 0; $i--) {
            $node = $nodes->item($i);
            if ($node && $node->parentNode) $node->parentNode->removeChild($node);
        }
    }
    $root = $dom->getElementById('duty-export-root');
    if ($root) {
        $clean = '';
        foreach ($root->childNodes as $child) $clean .= $dom->saveHTML($child);
        if ($clean !== '') $content = $clean;
    }
}

$displayDate = date('d-m-Y', strtotime($date));
$filename = 'Biên bản trực nội trú ngày ' . $displayDate . '.doc';
$css = <<<'CSS'
@page WordSection1{size:595.3pt 841.9pt;margin:51pt 42.5pt 51pt 56.7pt}
html,body{margin:0;padding:0;background:#fff;color:#000;font-family:"Times New Roman",serif;font-size:13pt;line-height:1.25}
.page{page:WordSection1;width:100%;box-sizing:border-box}
.report-national{width:100%;table-layout:fixed;border-collapse:collapse;text-align:center;font-weight:700}
.report-national td{border:0;vertical-align:top;padding:0}.report-national td:first-child{width:40%}.report-national td:last-child{width:60%;padding-left:8mm}
.report-national p{margin:0}.report-national .report-agency{font-weight:400}.underline{display:inline-block;border-bottom:1px solid #000;padding-bottom:2px}
.report-place{text-align:right;font-style:italic;margin:7mm 0 4mm}h1{margin:0;text-align:center;font-size:15pt;font-weight:700}.report-year{text-align:center;font-weight:700;margin:1mm 0 6mm}
.report-section{margin:2.5mm 0}.report-section-title,.report-subtitle{font-weight:700}.report-info-line{margin:1.2mm 0 1.2mm 5mm}.report-info-label{display:inline-block;width:25mm;font-weight:700}
.report-text,.report-entry-preview{display:block;white-space:pre-wrap;text-align:justify}.report-attendance{width:100%;margin:2mm 0;border-collapse:collapse;table-layout:fixed;font-size:11.5pt}
.report-attendance th,.report-attendance td{border:1px solid #000;padding:1.6mm;text-align:center;vertical-align:top}.report-attendance .attendance-absent{text-align:left;font-size:10.5pt}
.attendance-type-row{padding:1mm 0;border-bottom:1px dotted #aaa}.attendance-type-label{font-weight:700}.attendance-class{margin-bottom:.7mm}
.report-signatures{width:100%;margin-top:7mm;border-collapse:collapse;table-layout:fixed;page-break-inside:avoid}.report-signatures td{height:45mm;border:0;padding:2mm;vertical-align:top;text-align:center}
.report-signatures strong{display:block}.report-sign-name{margin-top:11mm;text-align:center}.report-empty{color:#555;font-style:italic}
CSS;
$html = '<!doctype html><html><head><meta charset="UTF-8"><meta name="ProgId" content="Word.Document"><style>'.$css.'</style></head><body><div class="page">'.$content.'</div></body></html>';
$bytes = "\xEF\xBB\xBF".$html;
$settings = cds_drive_settings();
$folder = cds_drive_folder('duty_reports', $settings);
if (empty($settings['enabled']) || $folder === '') {
    http_response_code(500);
    echo json_encode(['ok'=>false,'message'=>'Drive chưa bật hoặc chưa cấu hình thư mục Biên bản trực nội trú.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$token = cds_drive_token($settings);
if (empty($token['ok'])) {
    http_response_code(500);
    echo json_encode($token, JSON_UNESCAPED_UNICODE);
    exit;
}

/* Một ngày chỉ có một tệp: tìm đúng file Word đã lưu của ngày này để cập nhật nội dung. */
$existingId = '';
foreach (cds_drive_history() as $old) {
    if (($old['type'] ?? '') !== 'duty_reports' || ($old['report_date'] ?? $old['date'] ?? '') !== $date) continue;
    if (($old['mime'] ?? '') !== 'application/msword' && strtolower((string)pathinfo((string)($old['name'] ?? ''), PATHINFO_EXTENSION)) !== 'doc') continue;
    if (!empty($old['file_id'])) { $existingId = (string)$old['file_id']; break; }
}
$result = [];
$action = 'create';
if ($existingId !== '') {
    $update = cds_drive_http(
        'https://www.googleapis.com/upload/drive/v3/files/'.rawurlencode($existingId).'?uploadType=media&supportsAllDrives=true&fields=id,name,webViewLink',
        'PATCH',
        ['Authorization: Bearer '.$token['token'], 'Content-Type: application/msword'],
        $bytes
    );
    $updated = json_decode($update['body'], true);
    if ($update['ok'] && !empty($updated['id'])) {
        $result = ['ok'=>true,'id'=>$updated['id'],'name'=>$filename,'webViewLink'=>$updated['webViewLink'] ?? ''];
        $action = 'update';
    }
}
if (empty($result['ok'])) {
    $boundary = 'cds-duty-' . bin2hex(random_bytes(12));
    $meta = json_encode([
        'name'=>$filename,
        'parents'=>[$folder],
        'appProperties'=>['cdsType'=>'duty_reports','cdsReportDate'=>$date],
    ], JSON_UNESCAPED_UNICODE);
    $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n"
        . "--$boundary\r\nContent-Type: application/msword\r\n\r\n$bytes\r\n--$boundary--";
    $upload = cds_drive_http(
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink',
        'POST',
        ['Authorization: Bearer '.$token['token'], 'Content-Type: multipart/related; boundary='.$boundary],
        $body
    );
    $created = json_decode($upload['body'], true);
    if (!$upload['ok'] || empty($created['id'])) {
        http_response_code(500);
        echo json_encode(['ok'=>false,'message'=>$created['error']['message'] ?? 'Không lưu được tệp Word lên Drive.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $result = ['ok'=>true,'id'=>$created['id'],'name'=>$filename,'webViewLink'=>$created['webViewLink'] ?? ''];
}
if (empty($result['webViewLink'])) $result['webViewLink'] = 'https://drive.google.com/file/d/'.rawurlencode((string)$result['id']).'/view';
cds_drive_history_add([
    'action'=>$action,
    'type'=>'duty_reports',
    'name'=>$filename,
    'file_id'=>(string)$result['id'],
    'folder_id'=>$folder,
    'mime'=>'application/msword',
    'web_view'=>$result['webViewLink'],
    'date'=>$date,
    'report_date'=>$date,
    'source_action'=>'page:/noitru.php?tab=duty_report',
]);
$result['filename'] = $filename;
$result['format'] = 'word';
echo json_encode($result, JSON_UNESCAPED_UNICODE);

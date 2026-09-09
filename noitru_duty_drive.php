<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_perm('nt.lichtruc');
header('Content-Type: application/json; charset=UTF-8');

function duty_drive_reply(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') duty_drive_reply(['ok'=>false,'message'=>'Phương thức không hợp lệ.'], 405);
$csrf = (string)($_POST['csrf'] ?? '');
if ($csrf === '' || !hash_equals(cds_drive_csrf_token(), $csrf)) duty_drive_reply(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ. Vui lòng tải lại trang.'], 403);
$date = trim((string)($_POST['date'] ?? ''));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) duty_drive_reply(['ok'=>false,'message'=>'Ngày biên bản không hợp lệ.'], 400);

$upload = $_FILES['pdf'] ?? null;
if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) duty_drive_reply(['ok'=>false,'message'=>'Chưa nhận được tệp PDF của biên bản.'], 400);
$tmp = (string)($upload['tmp_name'] ?? '');
$size = (int)($upload['size'] ?? 0);
if ($tmp === '' || $size < 5 || $size > 25000000) duty_drive_reply(['ok'=>false,'message'=>'Tệp PDF trống hoặc vượt quá 25 MB.'], 400);
$bytes = file_get_contents($tmp);
if ($bytes === false || strncmp($bytes, '%PDF-', 5) !== 0) duty_drive_reply(['ok'=>false,'message'=>'Tệp gửi lên không đúng định dạng PDF.'], 400);

$displayDate = date('d-m-Y', strtotime($date));
$filename = 'Biên bản trực nội trú ngày ' . $displayDate . '.pdf';
$settings = cds_drive_settings();
$folder = cds_drive_folder('duty_reports', $settings);
if (empty($settings['enabled']) || $folder === '') duty_drive_reply(['ok'=>false,'message'=>'Drive chưa bật hoặc chưa cấu hình thư mục Biên bản trực nội trú.'], 500);
$token = cds_drive_token($settings);
if (empty($token['ok'])) duty_drive_reply($token, 500);
$headers = ['Authorization: Bearer '.$token['token']];

$history = cds_drive_history();
$existingId = '';
$obsoleteIds = [];
foreach ($history as $old) {
    if (($old['type'] ?? '') !== 'duty_reports' || ($old['report_date'] ?? $old['date'] ?? '') !== $date || empty($old['file_id'])) continue;
    $id = (string)$old['file_id'];
    $isPdf = ($old['mime'] ?? '') === 'application/pdf' || strtolower((string)pathinfo((string)($old['name'] ?? ''), PATHINFO_EXTENSION)) === 'pdf';
    if ($isPdf && $existingId === '') $existingId = $id;
    else $obsoleteIds[$id] = true;
}

$result = [];
$action = 'create';
if ($existingId !== '') {
    $update = cds_drive_http(
        'https://www.googleapis.com/upload/drive/v3/files/'.rawurlencode($existingId).'?uploadType=media&supportsAllDrives=true&fields=id,name,webViewLink',
        'PATCH',
        array_merge($headers, ['Content-Type: application/pdf']),
        $bytes
    );
    $updated = json_decode($update['body'], true);
    if ($update['ok'] && !empty($updated['id'])) {
        cds_drive_http(
            'https://www.googleapis.com/drive/v3/files/'.rawurlencode($existingId).'?supportsAllDrives=true&fields=id,name',
            'PATCH',
            array_merge($headers, ['Content-Type: application/json; charset=UTF-8']),
            json_encode(['name'=>$filename,'appProperties'=>['cdsType'=>'duty_reports','cdsReportDate'=>$date]], JSON_UNESCAPED_UNICODE)
        );
        $result = ['ok'=>true,'id'=>$updated['id'],'name'=>$filename,'webViewLink'=>$updated['webViewLink'] ?? ''];
        $action = 'update';
        unset($obsoleteIds[$existingId]);
    }
}
if (empty($result['ok'])) {
    if ($existingId !== '') $obsoleteIds[$existingId] = true;
    $boundary = 'cds-duty-pdf-' . bin2hex(random_bytes(12));
    $meta = json_encode([
        'name'=>$filename,
        'parents'=>[$folder],
        'appProperties'=>['cdsType'=>'duty_reports','cdsReportDate'=>$date],
    ], JSON_UNESCAPED_UNICODE);
    $body = "--$boundary\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n"
        . "--$boundary\r\nContent-Type: application/pdf\r\n\r\n$bytes\r\n--$boundary--";
    $createdResponse = cds_drive_http(
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink',
        'POST',
        array_merge($headers, ['Content-Type: multipart/related; boundary='.$boundary]),
        $body
    );
    $created = json_decode($createdResponse['body'], true);
    if (!$createdResponse['ok'] || empty($created['id'])) duty_drive_reply(['ok'=>false,'message'=>$created['error']['message'] ?? 'Không lưu được tệp PDF lên Drive.'], 500);
    $result = ['ok'=>true,'id'=>$created['id'],'name'=>$filename,'webViewLink'=>$created['webViewLink'] ?? ''];
}

/* Chỉ giữ một biên bản của ngày: các bản Word/PDF cũ được chuyển vào thùng rác Drive. */
foreach (array_keys($obsoleteIds) as $oldId) {
    if ($oldId === (string)$result['id']) continue;
    cds_drive_http(
        'https://www.googleapis.com/drive/v3/files/'.rawurlencode($oldId).'?supportsAllDrives=true&fields=id,trashed',
        'PATCH',
        array_merge($headers, ['Content-Type: application/json']),
        '{"trashed":true}'
    );
}
if (empty($result['webViewLink'])) $result['webViewLink'] = 'https://drive.google.com/file/d/'.rawurlencode((string)$result['id']).'/view';
cds_drive_history_add([
    'action'=>$action,
    'type'=>'duty_reports',
    'name'=>$filename,
    'file_id'=>(string)$result['id'],
    'folder_id'=>$folder,
    'mime'=>'application/pdf',
    'web_view'=>$result['webViewLink'],
    'date'=>$date,
    'report_date'=>$date,
    'source_action'=>'page:/noitru.php?tab=duty_report',
]);
$result['filename'] = $filename;
$result['format'] = 'pdf';
duty_drive_reply($result);

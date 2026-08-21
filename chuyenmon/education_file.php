<?php
require_once __DIR__ . '/includes/functions.php';

if (!cds_user() || !cds_can_feature('cm.kehoach', 'view')) {
    http_response_code(403);
    exit('Tài khoản chưa có quyền Kế hoạch giáo dục.');
}

$id = trim((string)($_GET['id'] ?? ''));
$rows = load_json(DATA_PATH . '/education_plans.json', []);
$row = null;
foreach ((array)$rows as $candidate) {
    if (($candidate['id'] ?? '') === $id) { $row = $candidate; break; }
}
if (!$row) { http_response_code(404); exit('Không tìm thấy kế hoạch.'); }

$user = cds_user() ?? [];
$teacher = trim((string)($user['teacher_name'] ?? $user['name'] ?? ''));
$group = $teacher !== '' ? trim((string)get_teacher_group($teacher)) : '';
$role = (string)($user['role'] ?? '');
$leader = $role === 'totruong' || in_array('totruong', (array)($user['groups'] ?? []), true);
$norm = fn($value) => function_exists('mb_strtolower')
    ? mb_strtolower(trim((string)$value), 'UTF-8')
    : strtolower(trim((string)$value));

if ($role !== 'admin'
    && !($leader && $group !== '' && $norm($row['teacher_group'] ?? '') === $norm($group))
    && $norm($row['teacher'] ?? '') !== $norm($teacher)) {
    http_response_code(403);
    exit('Không có quyền xem tệp.');
}

$path = (string)($row['file_path'] ?? '');
if (!str_starts_with($path, 'gdrive:')) {
    header('Location: ' . BASE_URL . ltrim($path, '/'));
    exit;
}

$fileId = substr($path, 7);
$cacheDir = DATA_PATH . '/cache/education_pdf';
$cacheFile = $cacheDir . '/' . hash('sha256', $fileId) . '.pdf';

function cm_serve_cached_pdf(string $file): void {
    $size = filesize($file);
    $mtime = filemtime($file) ?: time();
    $etag = '"' . sha1($file . '|' . $size . '|' . $mtime) . '"';

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="ke-hoach-giao-duc.pdf"');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, max-age=86400');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

    if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        exit;
    }

    $start = 0;
    $end = max(0, $size - 1);
    $range = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
    if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $match)) {
        if ($match[1] === '' && $match[2] !== '') {
            $length = min((int)$match[2], $size);
            $start = $size - $length;
        } else {
            $start = (int)$match[1];
            if ($match[2] !== '') $end = min((int)$match[2], $end);
        }
        if ($start < 0 || $start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }
        http_response_code(206);
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $length = $end - $start + 1;
    header('Content-Length: ' . $length);
    if ($_SERVER['REQUEST_METHOD'] === 'HEAD') exit;

    while (ob_get_level()) ob_end_clean();
    $handle = fopen($file, 'rb');
    if (!$handle) { http_response_code(500); exit; }
    fseek($handle, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(262144, $remaining));
        if ($chunk === false) break;
        echo $chunk;
        $remaining -= strlen($chunk);
        flush();
    }
    fclose($handle);
    exit;
}

if (is_file($cacheFile) && filesize($cacheFile) > 5) {
    cm_serve_cached_pdf($cacheFile);
}

$token = cds_drive_token();
if (empty($token['ok'])) { http_response_code(503); exit('Không kết nối được Drive.'); }

while (ob_get_level()) ob_end_clean();
set_time_limit(0);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="ke-hoach-giao-duc.pdf"');
header('Accept-Ranges: bytes');
header('Cache-Control: private, max-age=3600');

$headers = ['Authorization: Bearer ' . $token['token']];
$rangeRequest = !empty($_SERVER['HTTP_RANGE']);
if ($rangeRequest) $headers[] = 'Range: ' . $_SERVER['HTTP_RANGE'];

$cacheHandle = null;
$tempFile = '';
if (!$rangeRequest && @mkdir($cacheDir, 0755, true) || is_dir($cacheDir)) {
    if (!is_file($cacheDir . '/.htaccess')) @file_put_contents($cacheDir . '/.htaccess', "Require all denied\nDeny from all\n");
    $tempFile = $cacheFile . '.tmp-' . bin2hex(random_bytes(4));
    $cacheHandle = @fopen($tempFile, 'wb');
}

$statusCode = 0;
$ch = curl_init('https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?supportsAllDrives=true&alt=media');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 300,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_BUFFERSIZE => 262144,
    CURLOPT_HEADERFUNCTION => function($curl, $line) use (&$statusCode) {
        $trim = trim($line);
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $trim, $match)) {
            $statusCode = (int)$match[1];
            http_response_code($statusCode);
        } elseif (stripos($trim, 'Content-Length:') === 0 || stripos($trim, 'Content-Range:') === 0) {
            header($trim);
        }
        return strlen($line);
    },
    CURLOPT_WRITEFUNCTION => function($curl, $chunk) use (&$cacheHandle) {
        if (is_resource($cacheHandle)) fwrite($cacheHandle, $chunk);
        echo $chunk;
        flush();
        return strlen($chunk);
    },
]);

$ok = curl_exec($ch);
if (is_resource($cacheHandle)) fclose($cacheHandle);
if ($ok !== false && $statusCode === 200 && $tempFile !== '' && is_file($tempFile) && filesize($tempFile) > 5) {
    @rename($tempFile, $cacheFile);
} elseif ($tempFile !== '' && is_file($tempFile)) {
    @unlink($tempFile);
}
if ($ok === false && !headers_sent()) http_response_code(502);
curl_close($ch);
exit;

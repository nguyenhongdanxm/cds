<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';
require_login();
require_module('noitru', 'view');
require_perm('nt.ravao');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!defined('NOITRU_EXIT_SETTINGS')) define('NOITRU_EXIT_SETTINGS', DATA_PATH . '/noitru/exit_settings.json');

function ntx_json(bool $ok, string $message, array $extra = [], int $status = 200): void {
    http_response_code($status);
    echo json_encode(array_merge(['ok'=>$ok, 'message'=>$message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ntx_settings(): array {
    return array_merge([
        'max_mb'=>10,
        'allow_images'=>true,
        'allow_pdf'=>true,
    ], (array)load_json(NOITRU_EXIT_SETTINGS, []));
}

function ntx_drive_token_and_settings(): array {
    $settings = cds_drive_settings();
    if (empty($settings['enabled'])) throw new RuntimeException('Google Drive đang tắt trong cấu hình hệ thống.');
    $token = cds_drive_token($settings);
    if (empty($token['ok']) || empty($token['token'])) throw new RuntimeException((string)($token['message'] ?? 'Không lấy được quyền truy cập Google Drive.'));
    return [$settings, (string)$token['token']];
}

function ntx_validate_folder(string $folderId, string $token): ?array {
    if ($folderId === '') return null;
    $url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($folderId)
        . '?supportsAllDrives=true&fields=id,name,mimeType,trashed,webViewLink,capabilities(canAddChildren)';
    $res = cds_drive_http($url, 'GET', ['Authorization: Bearer ' . $token]);
    $json = json_decode((string)($res['body'] ?? ''), true);
    if (empty($res['ok']) || !is_array($json)) return null;
    if (!empty($json['trashed']) || ($json['mimeType'] ?? '') !== 'application/vnd.google-apps.folder') return null;
    if (isset($json['capabilities']['canAddChildren']) && empty($json['capabilities']['canAddChildren'])) return null;
    return $json;
}

function ntx_prepare_folder(bool $create = true): array {
    [$settings, $token] = ntx_drive_token_and_settings();
    if (!isset($settings['types']) || !is_array($settings['types'])) $settings['types'] = [];
    if (!isset($settings['types']['ktx_exit_requests']) || !is_array($settings['types']['ktx_exit_requests'])) {
        $settings['types']['ktx_exit_requests'] = ['label'=>'Đơn xin ra vào KTX', 'folder_id'=>'', 'prefix'=>'Don-ra-vao-KTX'];
    }

    $folderId = trim((string)($settings['types']['ktx_exit_requests']['folder_id'] ?? ''));
    $folder = ntx_validate_folder($folderId, $token);
    if ($folder) return ['folder'=>$folder, 'token'=>$token, 'created'=>false];

    $escapedName = str_replace("'", "\\'", 'Đơn xin ra vào KTX');
    $query = "name = '" . $escapedName . "' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
    $findUrl = 'https://www.googleapis.com/drive/v3/files?spaces=drive&pageSize=20&fields=files(id,name,mimeType,trashed,webViewLink,capabilities(canAddChildren))&q=' . rawurlencode($query);
    $find = cds_drive_http($findUrl, 'GET', ['Authorization: Bearer ' . $token]);
    $found = json_decode((string)($find['body'] ?? ''), true);
    if (!empty($find['ok'])) {
        foreach ((array)($found['files'] ?? []) as $candidate) {
            if (empty($candidate['id'])) continue;
            if (isset($candidate['capabilities']['canAddChildren']) && empty($candidate['capabilities']['canAddChildren'])) continue;
            $folder = $candidate;
            $folderId = (string)$candidate['id'];
            break;
        }
    }

    if (!$folder && !$create) throw new RuntimeException('Chưa có thư mục “Đơn xin ra vào KTX” trên Google Drive.');
    $created = false;
    if (!$folder) {
        $metadata = json_encode(['name'=>'Đơn xin ra vào KTX', 'mimeType'=>'application/vnd.google-apps.folder'], JSON_UNESCAPED_UNICODE);
        $createRes = cds_drive_http(
            'https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id,name,mimeType,webViewLink',
            'POST',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8'],
            $metadata
        );
        $folder = json_decode((string)($createRes['body'] ?? ''), true);
        if (empty($createRes['ok']) || empty($folder['id'])) {
            throw new RuntimeException((string)($folder['error']['message'] ?? 'Không tạo được thư mục “Đơn xin ra vào KTX” trên Google Drive.'));
        }
        $folderId = (string)$folder['id'];
        $created = true;
    }

    $settings['types']['ktx_exit_requests'] = [
        'label'=>'Đơn xin ra vào KTX',
        'folder_id'=>$folderId,
        'prefix'=>'Don-ra-vao-KTX',
    ];
    if (!cds_drive_save_settings($settings)) throw new RuntimeException('Đã có thư mục Drive nhưng không lưu được Folder ID vào cấu hình hệ thống.');

    if (empty($folder['webViewLink'])) $folder['webViewLink'] = 'https://drive.google.com/drive/folders/' . rawurlencode($folderId);
    return ['folder'=>$folder, 'token'=>$token, 'created'=>$created];
}

function ntx_upload_file(array $file): array {
    $settings = ntx_settings();
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Bạn chưa chọn file đơn xin.');
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Máy chủ không nhận được file. Mã lỗi upload: ' . (int)$file['error'] . '.');

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('File tạm không hợp lệ hoặc đã hết phiên tải lên.');
    $maxBytes = max(1, min(25, (int)($settings['max_mb'] ?? 10))) * 1024 * 1024;
    $size = (int)($file['size'] ?? filesize($tmp));
    if ($size <= 0) throw new RuntimeException('File đơn xin đang rỗng.');
    if ($size > $maxBytes) throw new RuntimeException('File vượt quá ' . (int)($settings['max_mb'] ?? 10) . ' MB.');

    $name = basename((string)($file['name'] ?? 'don-xin'));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ($ext === 'pdf' && !empty($settings['allow_pdf']))
        || (in_array($ext, ['jpg','jpeg','png','webp'], true) && !empty($settings['allow_images']));
    if (!$allowed) throw new RuntimeException('Định dạng file không được phép. Chỉ nhận PDF hoặc ảnh theo cài đặt.');

    $bytes = file_get_contents($tmp);
    if ($bytes === false) throw new RuntimeException('Không đọc được nội dung file tạm.');
    $mime = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: 'application/octet-stream') : 'application/octet-stream';
    $prepared = ntx_prepare_folder(true);
    $folderId = (string)$prepared['folder']['id'];
    $token = (string)$prepared['token'];

    $boundary = '===============' . bin2hex(random_bytes(12));
    $meta = json_encode(['name'=>$name, 'parents'=>[$folderId]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = '--' . $boundary . "\r\n"
        . "Content-Type: application/json; charset=UTF-8\r\n\r\n" . $meta . "\r\n"
        . '--' . $boundary . "\r\n"
        . 'Content-Type: ' . $mime . "\r\n\r\n" . $bytes . "\r\n"
        . '--' . $boundary . "--\r\n";

    $upload = cds_drive_http(
        'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,mimeType,size,webViewLink',
        'POST',
        ['Authorization: Bearer ' . $token, 'Content-Type: multipart/related; boundary=' . $boundary],
        $body
    );
    $json = json_decode((string)($upload['body'] ?? ''), true);
    if (empty($upload['ok']) || empty($json['id'])) {
        throw new RuntimeException((string)($json['error']['message'] ?? 'Google Drive không trả về File ID sau khi tải lên.'));
    }

    $fileId = (string)$json['id'];
    $viewUrl = !empty($json['webViewLink']) ? (string)$json['webViewLink'] : 'https://drive.google.com/file/d/' . rawurlencode($fileId) . '/view';
    return [
        'path'=>'gdrive:' . $fileId,
        'file_id'=>$fileId,
        'name'=>(string)($json['name'] ?? $name),
        'url'=>$viewUrl,
        'folder_id'=>$folderId,
        'folder_url'=>'https://drive.google.com/drive/folders/' . rawurlencode($folderId),
    ];
}

try {
    $action = trim((string)($_REQUEST['action'] ?? 'status'));
    if ($action === 'status') {
        try {
            $prepared = ntx_prepare_folder(false);
            $folder = $prepared['folder'];
            ntx_json(true, 'Google Drive đã sẵn sàng.', [
                'ready'=>true,
                'folder_id'=>(string)$folder['id'],
                'folder_name'=>(string)($folder['name'] ?? 'Đơn xin ra vào KTX'),
                'folder_url'=>'https://drive.google.com/drive/folders/' . rawurlencode((string)$folder['id']),
            ]);
        } catch (Throwable $e) {
            ntx_json(true, $e->getMessage(), ['ready'=>false]);
        }
    }
    if ($action === 'prepare') {
        $prepared = ntx_prepare_folder(true);
        $folder = $prepared['folder'];
        ntx_json(true, !empty($prepared['created']) ? 'Đã tạo thư mục “Đơn xin ra vào KTX” trên Google Drive.' : 'Thư mục “Đơn xin ra vào KTX” đã sẵn sàng.', [
            'ready'=>true,
            'created'=>!empty($prepared['created']),
            'folder_id'=>(string)$folder['id'],
            'folder_url'=>'https://drive.google.com/drive/folders/' . rawurlencode((string)$folder['id']),
        ]);
    }
    if ($action === 'upload') {
        $result = ntx_upload_file($_FILES['attachment'] ?? []);
        ntx_json(true, 'Đã tải đơn lên Google Drive thành công.', $result);
    }
    ntx_json(false, 'Thao tác không hợp lệ.', [], 400);
} catch (Throwable $e) {
    ntx_json(false, $e->getMessage(), [], 400);
}

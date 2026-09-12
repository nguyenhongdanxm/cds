<?php
/** Dữ liệu và mã xác minh dùng chung cho thẻ cán bộ, giáo viên. */
require_once __DIR__ . '/csdl_store.php';

if (!defined('STAFF_CARD_SETTINGS')) define('STAFF_CARD_SETTINGS', DATA_PATH . '/staff_card_settings.json');

function staff_card_settings(): array {
    $settings = load_json(STAFF_CARD_SETTINGS, []);
    if (empty($settings['secret']) || !is_string($settings['secret'])) {
        try { $secret = bin2hex(random_bytes(32)); }
        catch (Throwable $e) { $secret = hash('sha256', uniqid('', true) . microtime(true)); }
        $settings = array_merge([
            'secret' => $secret,
            'issued_at' => date('c'),
            'school_name' => defined('SCHOOL_NAME') ? SCHOOL_NAME : '',
        ], is_array($settings) ? $settings : []);
        save_json(STAFF_CARD_SETTINGS, $settings);
    }
    return $settings;
}

function staff_card_public_code(array $teacher): string {
    $id = (string)($teacher['id'] ?? '');
    $code = trim((string)($teacher['code'] ?? ''));
    $base = $code !== '' ? $code : strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $id), -10));
    return 'XM-GV-' . strtoupper($base);
}

function staff_card_token(string $teacherId): string {
    $secret = (string)(staff_card_settings()['secret'] ?? '');
    return substr(hash_hmac('sha256', $teacherId, $secret), 0, 24);
}

function staff_card_verify_url(array $teacher): string {
    $id = (string)($teacher['id'] ?? '');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'cds.noitruxinman.edu.vn');
    $path = rtrim((defined('BASE_URL') ? BASE_URL : '/'), '/') . '/staff_verify.php';
    return $scheme . '://' . $host . $path . '?id=' . rawurlencode($id) . '&t=' . rawurlencode(staff_card_token($id));
}

function staff_card_is_valid_token(string $teacherId, string $token): bool {
    return $teacherId !== '' && $token !== '' && hash_equals(staff_card_token($teacherId), $token);
}

function staff_card_photo_file(string $teacherId): string {
    $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $teacherId);
    if ($safeId === '') return '';
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
        $file = DATA_PATH . '/teacher_photos/' . $safeId . '.' . $ext;
        if (is_file($file)) return $file;
    }
    return '';
}

function staff_card_photo_ensure_schema(): void {
    static $done = false;
    if ($done) return;
    cds_db()->exec("CREATE TABLE IF NOT EXISTS cds_teacher_photos (teacher_id VARCHAR(100) NOT NULL,image_data LONGBLOB NOT NULL,mime_type VARCHAR(80) NOT NULL,original_name VARCHAR(255) NOT NULL DEFAULT '',drive_file_id VARCHAR(255) NOT NULL DEFAULT '',checksum_sha256 CHAR(64) NOT NULL,file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,updated_by VARCHAR(100) NOT NULL DEFAULT '',updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (teacher_id),CONSTRAINT fk_teacher_photo_teacher FOREIGN KEY (teacher_id) REFERENCES cds_teachers(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function staff_card_photo_record(string $teacherId, bool $withBytes = false): ?array {
    if ($teacherId === '') return null;
    try {
        staff_card_photo_ensure_schema();
        $columns = $withBytes ? '*' : 'teacher_id,mime_type,original_name,drive_file_id,checksum_sha256,file_size,updated_at';
        $stmt = cds_db()->prepare('SELECT '.$columns.' FROM cds_teacher_photos WHERE teacher_id=?');
        $stmt->execute([$teacherId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Throwable $e) { return null; }
}

function staff_card_has_photo(string $teacherId): bool {
    return staff_card_photo_record($teacherId, false) !== null || staff_card_photo_file($teacherId) !== '';
}

function staff_card_save_photo(string $teacherId, string $bytes, string $mime, string $originalName, string $driveFileId, string $updatedBy): void {
    staff_card_photo_ensure_schema();
    $stmt = cds_db()->prepare('INSERT INTO cds_teacher_photos(teacher_id,image_data,mime_type,original_name,drive_file_id,checksum_sha256,file_size,updated_by) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE image_data=VALUES(image_data),mime_type=VALUES(mime_type),original_name=VALUES(original_name),drive_file_id=VALUES(drive_file_id),checksum_sha256=VALUES(checksum_sha256),file_size=VALUES(file_size),updated_by=VALUES(updated_by),updated_at=NOW()');
    $stmt->bindValue(1,$teacherId);$stmt->bindValue(2,$bytes,PDO::PARAM_LOB);$stmt->bindValue(3,$mime);$stmt->bindValue(4,$originalName);$stmt->bindValue(5,$driveFileId);$stmt->bindValue(6,hash('sha256',$bytes));$stmt->bindValue(7,strlen($bytes),PDO::PARAM_INT);$stmt->bindValue(8,$updatedBy);$stmt->execute();
}

function staff_card_upload_photo(string $teacherId, array $upload, string $updatedBy = ''): array {
    if ($teacherId === '' || !csdl_teacher_find($teacherId)) return ['ok'=>false,'message'=>'Không tìm thấy hồ sơ giáo viên để lưu ảnh.'];
    $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return ['ok'=>true,'changed'=>false,'message'=>''];
    if ($error !== UPLOAD_ERR_OK) return ['ok'=>false,'message'=>'Không nhận được ảnh tải lên (mã lỗi '.$error.').'];
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > 20*1024*1024) return ['ok'=>false,'message'=>'Ảnh phải có dung lượng không quá 20 MB.'];
    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok'=>false,'message'=>'Tệp ảnh tải lên không hợp lệ.'];
    $info = @getimagesize($tmp);$allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
    if (!isset($allowed[$mime])) return ['ok'=>false,'message'=>'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP hợp lệ.'];
    if ((int)($info[0] ?? 0) < 300 || (int)($info[1] ?? 0) < 400) return ['ok'=>false,'message'=>'Ảnh quá nhỏ. Vui lòng dùng ảnh tối thiểu 300 × 400 px.'];
    $bytes = @file_get_contents($tmp);if ($bytes === false) return ['ok'=>false,'message'=>'Không đọc được ảnh tải lên.'];
    try {
        $old=staff_card_photo_record($teacherId,false);$driveId=(string)($old['drive_file_id']??'');
        staff_card_save_photo($teacherId,$bytes,$mime,basename((string)($upload['name']??'')),$driveId,$updatedBy);
        return ['ok'=>true,'changed'=>true,'message'=>'Đã cập nhật ảnh thẻ giáo viên.'];
    } catch (Throwable $e) {
        return ['ok'=>false,'message'=>'Không lưu được ảnh thẻ: '.$e->getMessage()];
    }
}

function staff_card_delete_photo(string $teacherId): bool {
    if ($teacherId === '') return false;
    $deleted=false;
    try {
        staff_card_photo_ensure_schema();
        $stmt=cds_db()->prepare('DELETE FROM cds_teacher_photos WHERE teacher_id=?');$stmt->execute([$teacherId]);
        $deleted=$stmt->rowCount()>0;
    } catch (Throwable $e) {}
    $file=staff_card_photo_file($teacherId);
    if($file!==''&&is_file($file))$deleted=@unlink($file)||$deleted;
    return $deleted;
}


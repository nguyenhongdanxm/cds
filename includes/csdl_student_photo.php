<?php
/** Xử lý ảnh thẻ học sinh: xoay EXIF, cắt giữa 3:4, thu về 600x800 JPEG. */
if (!defined('CSDL_STUDENT_PHOTO_DIR')) define('CSDL_STUDENT_PHOTO_DIR', DATA_PATH . '/student_photos');

function csdl_student_photo_public_path(string $studentId): string {
    return 'student_photo.php?id=' . rawurlencode($studentId);
}

function csdl_student_photo_remove(string $studentId): void {
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);
    if ($safe === '') return;
    $file = CSDL_STUDENT_PHOTO_DIR . '/' . $safe . '.jpg';
    if (is_file($file)) @unlink($file);
}

function csdl_student_photo_save_upload(string $studentId, array $upload): array {
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok'=>true, 'changed'=>false];
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return ['ok'=>false, 'message'=>'Tải ảnh lên không thành công.'];
    if (($upload['size'] ?? 0) > 10 * 1024 * 1024) return ['ok'=>false, 'message'=>'Ảnh vượt quá 10 MB.'];
    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok'=>false, 'message'=>'File ảnh tải lên không hợp lệ.'];
    if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) return ['ok'=>false, 'message'=>'Máy chủ chưa bật thư viện GD để xử lý ảnh.'];
    $bytes = @file_get_contents($tmp);
    $src = $bytes !== false ? @imagecreatefromstring($bytes) : false;
    if (!$src) return ['ok'=>false, 'message'=>'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP hợp lệ.'];

    // Xoay ảnh JPEG theo EXIF khi có.
    if (function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $orientation = (int)($exif['Orientation'] ?? 1);
        if ($orientation === 3) $src = imagerotate($src, 180, 0);
        elseif ($orientation === 6) $src = imagerotate($src, -90, 0);
        elseif ($orientation === 8) $src = imagerotate($src, 90, 0);
    }

    $w = imagesx($src); $h = imagesy($src);
    if ($w < 120 || $h < 160) { imagedestroy($src); return ['ok'=>false, 'message'=>'Ảnh quá nhỏ để làm ảnh thẻ.']; }
    $targetRatio = 3 / 4;
    $ratio = $w / $h;
    if ($ratio > $targetRatio) { $cropH = $h; $cropW = (int)round($h * $targetRatio); $srcX = (int)(($w - $cropW) / 2); $srcY = 0; }
    else { $cropW = $w; $cropH = (int)round($w / $targetRatio); $srcX = 0; $srcY = (int)(($h - $cropH) / 2); }

    $dst = imagecreatetruecolor(600, 800);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);
    imagecopyresampled($dst, $src, 0, 0, $srcX, $srcY, 600, 800, $cropW, $cropH);
    if (!is_dir(CSDL_STUDENT_PHOTO_DIR) && !@mkdir(CSDL_STUDENT_PHOTO_DIR, 0755, true) && !is_dir(CSDL_STUDENT_PHOTO_DIR)) {
        imagedestroy($src); imagedestroy($dst); return ['ok'=>false, 'message'=>'Không tạo được thư mục lưu ảnh học sinh.'];
    }
    $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $studentId);
    $file = CSDL_STUDENT_PHOTO_DIR . '/' . $safe . '.jpg';
    $ok = @imagejpeg($dst, $file, 88);
    imagedestroy($src); imagedestroy($dst);
    if (!$ok) return ['ok'=>false, 'message'=>'Không ghi được ảnh học sinh.'];
    return ['ok'=>true, 'changed'=>true, 'path'=>csdl_student_photo_public_path($studentId)];
}

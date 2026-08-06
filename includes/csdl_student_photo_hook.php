<?php
/** Gắn xử lý ảnh vào action student_save mà không sửa trực tiếp csdl.php. */
if (defined('CSDL_STUDENT_PHOTO_HOOKED')) return;
define('CSDL_STUDENT_PHOTO_HOOKED', true);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'student_save') {
    require_once __DIR__ . '/csdl_student_photo.php';
    $hasUpload = isset($_FILES['student_photo']) && (($_FILES['student_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
    $removePhoto = !empty($_POST['remove_student_photo']);
    if ($hasUpload || $removePhoto) {
        $studentId = trim((string)($_POST['id'] ?? ''));
        if ($studentId === '') {
            $studentId = 'hs_' . bin2hex(random_bytes(4));
            $_POST['id'] = $studentId;
        }
        $upload = $hasUpload ? $_FILES['student_photo'] : null;
        register_shutdown_function(static function () use ($studentId, $upload, $removePhoto): void {
            if (!function_exists('csdl_student_save') || !function_exists('csdl_student_find')) return;
            if (!csdl_student_find($studentId)) return;
            if ($removePhoto) {
                csdl_student_photo_remove($studentId);
                csdl_student_save(['id'=>$studentId, 'photo'=>'']);
            }
            if ($upload) {
                $result = csdl_student_photo_save_upload($studentId, $upload);
                if (!empty($result['ok']) && !empty($result['changed'])) {
                    csdl_student_save(['id'=>$studentId, 'photo'=>(string)$result['path']]);
                } elseif (empty($result['ok'])) {
                    error_log('Student photo upload failed: ' . ($result['message'] ?? 'unknown error'));
                }
            }
        });
    }
}

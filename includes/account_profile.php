<?php
/** Hồ sơ cá nhân và mật khẩu tài khoản CDS. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csdl_store.php';
require_once __DIR__ . '/staff_card_store.php';
require_once __DIR__ . '/google_drive_storage.php';

function cds_account_csrf_token(): string {
    if (empty($_SESSION['cds_account_csrf'])) $_SESSION['cds_account_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['cds_account_csrf'];
}

function cds_account_csrf_valid(string $token): bool {
    return $token !== '' && hash_equals(cds_account_csrf_token(), $token);
}

function cds_account_teacher_for_user(array $user): ?array {
    $teacherId = trim((string)($user['teacher_id'] ?? ''));
    if ($teacherId !== '') {
        $teacher = csdl_teacher_find($teacherId);
        if ($teacher) return $teacher;
    }
    $wantedName = trim((string)($user['teacher_name'] ?? $user['name'] ?? ''));
    $wantedPhone = preg_replace('/\D+/', '', (string)($user['phone'] ?? $user['username'] ?? ''));
    foreach (csdl_teachers_all() as $teacher) {
        $teacherName = trim((string)($teacher['name'] ?? ''));
        $teacherPhone = preg_replace('/\D+/', '', (string)($teacher['phone'] ?? ''));
        if ($wantedPhone !== '' && $teacherPhone !== '' && $wantedPhone === $teacherPhone) return $teacher;
        if ($wantedName !== '' && $teacherName !== '' && function_exists('name_key') && name_key($wantedName) === name_key($teacherName)) return $teacher;
        if ($wantedName !== '' && $teacherName !== '' && mb_strtolower($wantedName, 'UTF-8') === mb_strtolower($teacherName, 'UTF-8')) return $teacher;
    }
    return null;
}

function cds_account_profile(array $user): array {
    $teacher = cds_account_teacher_for_user($user) ?? [];
    $pick = static fn(string $key, string $fallback = ''): string => trim((string)($teacher[$key] ?? $user[$key] ?? $fallback));
    return [
        'name' => $pick('name', (string)($user['username'] ?? '')),
        'dob' => $pick('dob'), 'gender' => $pick('gender'), 'phone' => $pick('phone'),
        'email' => $pick('email'), 'hometown' => $pick('hometown'), 'address' => $pick('address'),
        'teacher_id' => (string)($teacher['id'] ?? $user['teacher_id'] ?? ''),
    ];
}

function cds_account_update_profile(string $userId, array $input): array {
    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $dob = trim((string)($input['dob'] ?? ''));
    if ($name === '') return ['ok'=>false, 'message'=>'Họ và tên không được để trống.'];
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok'=>false, 'message'=>'Địa chỉ email không hợp lệ.'];
    if ($dob !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) return ['ok'=>false, 'message'=>'Ngày sinh không hợp lệ.'];
    $allowedGender = ['', 'Nam', 'Nữ', 'Khác'];
    $gender = trim((string)($input['gender'] ?? ''));
    if (!in_array($gender, $allowedGender, true)) $gender = '';
    $profile = [
        'name'=>$name, 'dob'=>$dob, 'gender'=>$gender,
        'phone'=>trim((string)($input['phone'] ?? '')), 'email'=>$email,
        'hometown'=>trim((string)($input['hometown'] ?? '')), 'address'=>trim((string)($input['address'] ?? '')),
    ];
    $users = get_users(); $record = null;
    foreach ($users as &$candidate) {
        if ((string)($candidate['id'] ?? '') !== $userId) continue;
        $record = $candidate;
        $teacher = cds_account_teacher_for_user($candidate);
        if ($teacher) {
            $teacher = array_merge($teacher, $profile);
            csdl_teacher_save($teacher); // cập nhật teachers.json và bảng cds_teachers trong MySQL
            $candidate['teacher_id'] = (string)$teacher['id'];
            $candidate['teacher_name'] = $name;
        }
        foreach ($profile as $key=>$value) $candidate[$key] = $value;
        $candidate['updated_at'] = date('c');
        break;
    }
    unset($candidate);
    if (!$record) return ['ok'=>false, 'message'=>'Không tìm thấy tài khoản.'];
    if (!save_users($users)) return ['ok'=>false, 'message'=>'Không lưu được hồ sơ tài khoản.'];
    refresh_current_user_session();
    if (function_exists('cds_audit_log')) cds_audit_log('profile_update', 'account', ['user_id'=>$userId]);
    return ['ok'=>true, 'message'=>'Đã cập nhật thông tin cá nhân vào CSDL.'];
}

function cds_account_upload_teacher_photo(string $userId, array $upload): array {
    $user = find_user_by_id($userId);
    if (!$user) return ['ok'=>false,'message'=>'Không tìm thấy tài khoản.'];
    $teacher = cds_account_teacher_for_user($user);
    if (!$teacher || empty($teacher['id'])) return ['ok'=>false,'message'=>'Tài khoản chưa liên kết với hồ sơ giáo viên trong CSDL. Quản trị cần gán đúng giáo viên trước khi tải ảnh.'];
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['ok'=>false,'message'=>'Vui lòng chọn ảnh thẻ.'];
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) return ['ok'=>false,'message'=>'Không nhận được ảnh tải lên (mã lỗi '.(int)$upload['error'].').'];
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > 20*1024*1024) return ['ok'=>false,'message'=>'Ảnh phải có dung lượng không quá 20 MB.'];
    $tmp = (string)($upload['tmp_name'] ?? '');
    $info = $tmp !== '' ? @getimagesize($tmp) : false;
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    $mime = is_array($info) ? (string)($info['mime'] ?? '') : '';
    if (!isset($allowed[$mime])) return ['ok'=>false,'message'=>'Chỉ chấp nhận ảnh JPG, PNG hoặc WebP hợp lệ.'];
    if ((int)($info[0] ?? 0) < 300 || (int)($info[1] ?? 0) < 400) return ['ok'=>false,'message'=>'Ảnh quá nhỏ. Vui lòng dùng ảnh tối thiểu 300 × 400 px.'];
    $bytes = file_get_contents($tmp);
    if ($bytes === false) return ['ok'=>false,'message'=>'Không đọc được ảnh tải lên.'];
    $settings = cds_drive_settings();
    $photoFolder = function_exists('cds_drive_folder') ? cds_drive_folder('photos',$settings) : trim((string)($settings['folders']['photos'] ?? ''));
    if (empty($settings['enabled']) || $photoFolder === '') return ['ok'=>false,'message'=>'Quản trị chưa bật hoặc chưa cấu hình thư mục Ảnh trên Google Drive. Ảnh chưa được thay đổi.'];
    $safeName = preg_replace('/[^a-zA-Z0-9_-]+/','-',(string)($teacher['code'] ?? $teacher['id']));
    $driveName = 'Anh-the-GV-'.$safeName.'-'.date('Ymd-His').'.'.$allowed[$mime];
    $drive = cds_drive_upload_bytes($bytes,$driveName,$mime,'photos');
    if (empty($drive['ok']) || empty($drive['id'])) return ['ok'=>false,'message'=>$drive['message'] ?? 'Không lưu được ảnh lên Google Drive. Ảnh chưa được thay đổi.'];
    try {
        staff_card_save_photo((string)$teacher['id'],$bytes,$mime,basename((string)($upload['name'] ?? $driveName)),(string)$drive['id'],$userId);
        $teacher['photo_path']='gdrive:'.(string)$drive['id'];$teacher['photo_drive_id']=(string)$drive['id'];$teacher['photo_updated_at']=date('c');
        csdl_teacher_save($teacher);
    } catch (Throwable $e) {
        return ['ok'=>false,'message'=>'Ảnh đã lên Drive nhưng chưa ghi được vào MySQL: '.$e->getMessage()];
    }
    if (function_exists('cds_audit_log')) cds_audit_log('teacher_photo_update','account',['user_id'=>$userId,'teacher_id'=>$teacher['id'],'drive_file_id'=>$drive['id']]);
    return ['ok'=>true,'message'=>'Đã lưu ảnh thẻ nguyên chất lượng vào MySQL, Google Drive và đồng bộ sang CSDL để in thẻ.'];
}

function cds_account_change_password(string $userId, string $current, string $password, string $confirm): array {
    if (strlen($password) < 8) return ['ok'=>false, 'message'=>'Mật khẩu mới phải có ít nhất 8 ký tự.'];
    if ($password !== $confirm) return ['ok'=>false, 'message'=>'Hai lần nhập mật khẩu mới không khớp.'];
    $users = get_users(); $changed = false;
    foreach ($users as &$candidate) {
        if ((string)($candidate['id'] ?? '') !== $userId) continue;
        if (!password_verify($current, (string)($candidate['password_hash'] ?? ''))) return ['ok'=>false, 'message'=>'Mật khẩu hiện tại không đúng.'];
        $candidate['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $candidate['must_change_password'] = false;
        $candidate['password_changed_at'] = date('c'); $changed = true; break;
    }
    unset($candidate);
    if (!$changed || !save_users($users)) return ['ok'=>false, 'message'=>'Không đổi được mật khẩu.'];
    if (function_exists('cds_audit_log')) cds_audit_log('password_change', 'account', ['user_id'=>$userId]);
    return ['ok'=>true, 'message'=>'Đã đổi mật khẩu thành công.'];
}

function cds_account_admin_reset_password(string $userId, string $password, string $confirm): array {
    if (strlen($password) < 8) return ['ok'=>false, 'message'=>'Mật khẩu mới phải có ít nhất 8 ký tự.'];
    if ($password !== $confirm) return ['ok'=>false, 'message'=>'Hai lần nhập mật khẩu không khớp.'];
    $users = get_users(); $changed = false; $target = '';
    foreach ($users as &$candidate) {
        if ((string)($candidate['id'] ?? '') !== $userId) continue;
        $candidate['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $candidate['must_change_password'] = false;
        $candidate['password_reset_at'] = date('c');
        $target = (string)($candidate['username'] ?? $userId); $changed = true; break;
    }
    unset($candidate);
    if (!$changed || !save_users($users)) return ['ok'=>false, 'message'=>'Không đặt lại được mật khẩu.'];
    if (function_exists('cds_audit_log')) cds_audit_log('password_reset', 'account', ['user_id'=>$userId, 'username'=>$target]);
    return ['ok'=>true, 'message'=>'Đã tạo lại mật khẩu cho tài khoản '.$target.'.'];
}

<?php
/** Read-only accounts never inherit administrative privileges. */
function cds_is_viewer(?array $user = null): bool {
    $user = $user ?? (is_array($_SESSION['cds_user'] ?? null) ? $_SESSION['cds_user'] : []);
    return ($user['role'] ?? '') === 'viewer';
}

/** Default deny: only reviewed read routes and filter parameters are admitted. */
function cds_viewer_read_routes(): array {
    $filters = 'tab q class class_name grade teacher subject date week month year from to from_week to_week session paper include_cover only_filled whole_week view_mode view scope category page sort dir section period_type anchor status';
    $routes = array_fill_keys([
        'index.php','admin.php','chuyenmon.php','csdl.php','noitru.php','noitru_list.php',
        'thuvien.php','vanban.php','hoclieu.php','thidua.php','trolyai.php',
        'thoikhoabieu.php','tienich_chunhiem.php','noitru_assign.php','noitru_attendance.php',
        'noitru_exit.php','noitru_exit_manager.php','tkb_xep.php','tkb_editor.php','tkb_constraints.php',
    ], $filters);
    $routes['noitru.php'] .= ' health_view health_q health_class health_from health_to medicine_view meal meal_view rice_view duty_view report_view list_view range attendance_view selected_date';
    $routes['thuvien.php'] .= ' equipment_q equipment_subject equipment_grade equipment_condition equipment_sort equipment_dir borrower_type';
    $routes['vanban.php'] .= ' type issuer_level field number_book number_q number_sort number_dir';
    $routes['thidua.php'] .= ' attendance_view score_view score_from score_to article';
    $routes['trolyai.php'] = 'assistant';
    $routes['tkb_xep.php'] = 'tab view teacher class scope target';
    $routes['tkb_editor.php'] = 'mode class teacher';
    $routes['tkb_constraints.php'] = 'panel type target scope scope_value';
    $routes['vanban_open.php'] = 'id file';
    $routes['vanban_preview.php'] = 'id file';
    $routes['vanban_preview_file.php'] = 'id file kind';
    $routes['hoclieu_file.php'] = 'id';
    $routes['admin.php'] = 'date week month year';
    $routes['login.php'] = '';
    $routes['logout.php'] = '';
    $routes['student_photo.php'] = 'id v';
    $routes['staff_photo.php'] = 'id v';
    $routes['student_card_students.php'] = 'q class page';
    $routes['staff_card_teachers.php'] = 'q group page';
    $routes['noitru_meal_quantity_data.php'] = 'from to date class month';
    $routes['noitru_overview_api.php'] = 'date month';
    $routes['noitru_room_roles_data.php'] = 'date';
    $routes['thidua_phongnoitru_history_api.php'] = 'room month from to';
    foreach (['index','tracuu','ketqua','tongquan','danhsach','rasoat','giaovien','monhoc','lop','kiemnhiem','thongke','kehoach','baocao','dugio','danhgia','phieudugio','kiemtrahoso','boiduong','activities','sodaubai','sodaubai_print','classroom_home','student_support'] as $page) {
        $routes['chuyenmon/'.$page.'.php'] = $filters.' version vid group khoi lop teacher_filter filter_teacher class_filter search baocao_view tiendo_view report_type semester limit offset';
    }
    $routes['chuyenmon/sodaubai.php'] .= ' ppct_load ppct_subject ppct_grade ppct_scope_type ppct_scope_target review_class stats_section stats_load stats_range stats_week stats_from stats_to stats_teacher stats_subject stats_class stats_completion audit_load audit_q audit_from audit_to';
    return $routes;
}
function cds_viewer_request_allowed(string $method, string $path, array $query): bool {
    if (!in_array($method, ['GET','HEAD'], true)) return false;
    $routes = cds_viewer_read_routes();
    if (!array_key_exists($path, $routes)) return false;
    $keys = array_filter(explode(' ', $routes[$path]));
    foreach ($query as $key=>$value) {
        if (!in_array($key, $keys, true) || !is_string($value)) return false;
    }
    // Settings containing secrets and mutation modes are not business records.
    foreach (['tab','view','scope','score_view','report_view'] as $key) {
        if (isset($query[$key]) && preg_match('/(?:^|_)(?:settings|config|setup|manage|edit|delete|remove|reset|save|sync|import|approve|sign|publish|generate)(?:_|$)/i', $query[$key])) return false;
    }
    return true;
}
function cds_viewer_guard(string $root): void {
    $session = is_array($_SESSION['cds_user'] ?? null) ? $_SESSION['cds_user'] : [];
    if (!$session) return;
    // Recheck the current record, so a stale administrative session cannot bypass a downgrade.
    $raw = @file_get_contents($root.'/data/users.json');
    $users = is_string($raw) ? json_decode($raw, true) : null;
    $record = null;
    foreach (is_array($users) ? $users : [] as $user) {
        if (!is_array($user)) continue;
        if ((!empty($session['id']) && ($user['id'] ?? null) === $session['id']) ||
            (empty($session['id']) && strcasecmp((string)($user['username'] ?? ''), (string)($session['username'] ?? '')) === 0)) { $record=$user; break; }
    }
    if (!cds_is_viewer($session) && !cds_is_viewer($record ?? [])) return;
    if (!$record || empty($record['active']) || (!empty($record['expires_at']) && (strtotime((string)$record['expires_at']) ?: 0) < time())) {
        unset($_SESSION['cds_user'], $_SESSION['pccm_admin']);
        http_response_code(401); exit('Tài khoản trải nghiệm đã khóa hoặc hết hạn.');
    }
    $_SESSION['cds_user'] = cds_session_user_from_record($record);
    $_SESSION['pccm_admin'] = false;
    if (!cds_is_viewer()) return;
    $file = realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $base = realpath($root);
    $path = $file && $base && str_starts_with($file, $base.DIRECTORY_SEPARATOR) ? str_replace(DIRECTORY_SEPARATOR, '/', substr($file, strlen($base)+1)) : '';
    if (!cds_viewer_request_allowed((string)($_SERVER['REQUEST_METHOD'] ?? ''), $path, $_GET)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('Tài khoản trải nghiệm chỉ được xem. Thao tác hoặc đường dẫn này không được phép. <a href="/admin.php">Về trang chủ</a>');
    }
    header('Cache-Control: private, no-store');
    require_once $root.'/includes/global_ui.php';
}

/** Atomic admin-only provisioning, never overwrite an existing account. */
function cds_viewer_create_account(array $actor, string $username, string $password, string $expiry, string $file): array {
    if (($actor['role'] ?? '') !== 'admin') throw new RuntimeException('Chỉ quản trị được tạo tài khoản.');
    $username = trim($username);
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D', $username)) throw new RuntimeException('Tên đăng nhập không hợp lệ.');
    if ($password === '') $password = bin2hex(random_bytes(12));
    if (strlen($password) < 12 || strlen($password) > 200) throw new RuntimeException('Mật khẩu cần từ 12 đến 200 ký tự.');
    $expires = '';
    if ($expiry !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expiry, new DateTimeZone('Asia/Ho_Chi_Minh'));
        if (!$date || $date->format('Y-m-d') !== $expiry) throw new RuntimeException('Ngày hết hạn không hợp lệ.');
        $expires = $date->setTime(23,59,59)->format(DATE_ATOM);
        if ($date->setTime(23,59,59)->getTimestamp() < time()) throw new RuntimeException('Ngày hết hạn đã qua.');
    }
    $record = ['id'=>'u_'.bin2hex(random_bytes(8)), 'username'=>$username, 'name'=>'Khách trải nghiệm', 'role'=>'viewer', 'active'=>true,
        'password_hash'=>password_hash($password, PASSWORD_DEFAULT), 'modules'=>[], 'perms'=>[], 'groups'=>[], 'classes'=>[], 'homeroom_classes'=>[],
        'permission_overrides'=>[], 'permission_model_version'=>2, 'expires_at'=>$expires, 'created_at'=>date(DATE_ATOM), 'updated_at'=>date(DATE_ATOM)];
    $ok = cds_json_update($file, static function($users) use ($record) {
        if (!is_array($users)) throw new RuntimeException('Dữ liệu tài khoản không hợp lệ.');
        foreach ($users as $user) if (strcasecmp((string)($user['username'] ?? ''), $record['username']) === 0) throw new RuntimeException('Tên đăng nhập đã tồn tại; không thay đổi tài khoản cũ.');
        $users[]=$record; return array_values($users);
    }, []);
    if (!$ok) throw new RuntimeException('Không lưu được tài khoản.');
    return ['username'=>$username, 'password'=>$password];
}

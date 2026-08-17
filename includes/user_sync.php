<?php
/**
 * Đồng bộ tài khoản từ CSDL GV + phân công kiêm nhiệm (PCCM).
 * - Tài khoản = SĐT (chỉ số)
 * - Mật khẩu mặc định tài khoản mới: Ntxm@2026
 * - Nhóm: BGH, QLNT, Văn thư, Kế toán, Đoàn-Đội, Thư viện-Thiết bị, Tổ CM, GVCN, GV
 */

define('DEFAULT_USER_PASSWORD', 'Ntxm@2026');

function user_group_presets() {
    $allCm = ['cm.dashboard','cm.tracuu','cm.pccm','cm.nhaplieu','cm.thongke','cm.kehoach','cm.baocao'];
    $allNt = ['nt.tongquan','nt.danhsach','nt.chiaphong','nt.chiamam','nt.diemdanh','nt.baoan','nt.buaan.tonghop','nt.gao','nt.ravao','nt.yte','nt.lichtruc','nt.thucdon','nt.thongke'];

    return [
        'bgh' => [
            'label' => 'Ban giám hiệu',
            'role' => 'bgh',
            'modules' => ['chuyenmon'=>'edit','csdl'=>'view','noitru'=>'view','vanban'=>'view','thidua'=>'view'],
            'perms' => array_merge($allCm, ['csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students','csdl.export'], ['nt.tongquan','nt.danhsach','nt.thongke']),
        ],
        'qlnt' => [
            'label' => 'Quản lý nội trú',
            'role' => 'ktx',
            'modules' => ['chuyenmon'=>'none','csdl'=>'view','noitru'=>'edit','vanban'=>'none','thidua'=>'none'],
            'perms' => array_merge(['csdl.overview','csdl.students'], $allNt),
        ],
        'vanthu' => [
            'label' => 'Văn thư',
            'role' => 'custom',
            'modules' => ['chuyenmon'=>'view','csdl'=>'view','noitru'=>'none','vanban'=>'edit','thidua'=>'view'],
            'perms' => ['cm.tracuu','cm.dashboard','csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students','csdl.export'],
        ],
        'ketoan' => [
            'label' => 'Kế toán',
            'role' => 'custom',
            'modules' => ['chuyenmon'=>'none','csdl'=>'view','noitru'=>'none','vanban'=>'view','thidua'=>'none'],
            'perms' => ['csdl.overview','csdl.students','csdl.export'],
        ],
        'doandoi' => [
            'label' => 'Đoàn – Đội',
            'role' => 'custom',
            'modules' => ['chuyenmon'=>'view','csdl'=>'view','noitru'=>'view','vanban'=>'none','thidua'=>'edit'],
            'perms' => ['cm.tracuu','cm.dashboard','cm.baocao','csdl.overview','csdl.students','nt.danhsach','nt.diemdanh'],
        ],
        'thuvien_thietbi' => [
            'label' => 'Thư viện – Thiết bị',
            'role' => 'custom',
            'modules' => ['chuyenmon'=>'view','csdl'=>'view','noitru'=>'none','vanban'=>'none','thidua'=>'none'],
            'perms' => ['cm.tracuu','cm.dashboard','csdl.overview'],
        ],
        'totruong' => [
            'label' => 'Quản lý tổ chuyên môn',
            'role' => 'totruong',
            'modules' => ['chuyenmon'=>'edit','csdl'=>'view','noitru'=>'none','vanban'=>'none','thidua'=>'none'],
            'perms' => ['cm.dashboard','cm.tracuu','cm.pccm','cm.thongke','cm.kehoach','cm.baocao','csdl.overview','csdl.statistics','csdl.teachers','csdl.classes','csdl.students'],
        ],
        'gvcn' => [
            'label' => 'GVCN',
            'role' => 'gvcn',
            'modules' => ['chuyenmon'=>'view','csdl'=>'view','noitru'=>'edit','vanban'=>'none','thidua'=>'none'],
            'perms' => ['cm.tracuu','cm.dashboard','csdl.overview','csdl.students','nt.diemdanh','nt.baoan','nt.ravao','nt.danhsach'],
        ],
        'gv' => [
            'label' => 'Giáo viên',
            'role' => 'gv',
            'modules' => ['chuyenmon'=>'view','csdl'=>'none','noitru'=>'view','vanban'=>'none','thidua'=>'none'],
            'perms' => ['cm.tracuu','cm.dashboard','cm.baocao','nt.danhsach'],
        ],
    ];
}

function normalize_phone($phone) {
    $d = preg_replace('/\D+/', '', (string)$phone);
    // bỏ mã quốc gia 84 → 0...
    if (strlen($d) >= 10 && str_starts_with($d, '84')) {
        $d = '0' . substr($d, 2);
    }
    return $d;
}

function text_blob_teacher(array $t) {
    $parts = [
        $t['chuc_vu'] ?? '',
        $t['kiem_nhiem_text'] ?? '',
        $t['note'] ?? '',
        $t['to_chuyen_mon'] ?? '',
    ];
    return mb_strtolower(implode(' | ', $parts), 'UTF-8');
}

function name_key($name) {
    $n = mb_strtolower(trim((string)$name), 'UTF-8');
    $n = preg_replace('/\s+/u', ' ', $n);
    return $n;
}

/** Đọc kiêm nhiệm PCCM: teacher => [roles...], GVCN => classes */
function pccm_role_index() {
    $out = ['roles' => [], 'gvcn_classes' => []];
    $base = defined('PCCM_DATA_PATH') && PCCM_DATA_PATH ? PCCM_DATA_PATH : (BASE_PATH . '/chuyenmon/data');
    if (!is_dir($base)) return $out;

    $vid = null;
    $av = $base . '/active_version.json';
    if (is_file($av)) {
        $j = json_decode(file_get_contents($av), true);
        $vid = $j['id'] ?? null;
    }
    $file = $vid ? $base . '/roles_' . $vid . '.json' : $base . '/role_assignments.json';
    if (!is_file($file)) {
        // thử file roles bất kỳ mới nhất
        $cands = glob($base . '/roles_*.json') ?: [];
        rsort($cands);
        $file = $cands[0] ?? '';
    }
    if (!$file || !is_file($file)) return $out;

    $items = json_decode(file_get_contents($file), true);
    if (!is_array($items)) return $out;

    foreach ($items as $a) {
        $teacher = trim($a['teacher'] ?? '');
        $role = trim($a['role'] ?? '');
        $class = trim($a['class'] ?? '');
        if ($teacher === '' || $role === '') continue;
        $nk = name_key($teacher);
        if (!isset($out['roles'][$nk])) $out['roles'][$nk] = [];
        $out['roles'][$nk][] = $role;
        if (mb_stripos($role, 'GVCN') !== false && $class !== '') {
            if (!isset($out['gvcn_classes'][$nk])) $out['gvcn_classes'][$nk] = [];
            $out['gvcn_classes'][$nk][] = $class;
        }
    }
    return $out;
}

/** Lớp chủ nhiệm từ CSDL classes */
function csdl_homeroom_index() {
    require_once __DIR__ . '/csdl_store.php';
    $map = []; // name_key => [classes]
    foreach (csdl_classes_all() as $c) {
        $tn = trim($c['homeroom_teacher_name'] ?? '');
        if ($tn === '' && !empty($c['homeroom_teacher_id'])) {
            $t = csdl_teacher_find($c['homeroom_teacher_id']);
            $tn = $t['name'] ?? '';
        }
        if ($tn === '') continue;
        $nk = name_key($tn);
        $cn = $c['name'] ?? '';
        if ($cn === '') continue;
        if (!isset($map[$nk])) $map[$nk] = [];
        $map[$nk][] = $cn;
    }
    return $map;
}

function merge_modules(array $a, array $b) {
    $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
    $out = [];
    foreach ($keys as $k) {
        $la = $a[$k] ?? 'none';
        $lb = $b[$k] ?? 'none';
        $out[$k] = level_rank($la) >= level_rank($lb) ? $la : $lb;
    }
    return $out;
}

function detect_groups_for_teacher(array $t, array $pccmRoles, array $gvcnClasses) {
    $groups = [];
    $blob = text_blob_teacher($t);
    $nk = name_key($t['name'] ?? '');

    // BGH
    if (!empty($t['is_principal']) || !empty($t['is_vice'])) $groups[] = 'bgh';
    if (preg_match('/hiệu\s*trưởng|phó\s*hiệu|bgh/u', $blob)) $groups[] = 'bgh';

    // QLNT
    if (preg_match('/nội\s*trú|qlnt|ktx|quản\s*lý\s*nội|nuôi\s*dưỡng|y\s*tế/u', $blob)) {
        $groups[] = 'qlnt';
    }

    if (preg_match('/văn\s*thư|hành\s*chính|văn\s*phòng/u', $blob)) $groups[] = 'vanthu';
    if (preg_match('/kế\s*toán|tài\s*chính|thủ\s*quỹ/u', $blob)) $groups[] = 'ketoan';
    if (preg_match('/thư\s*viện|thiết\s*bị/u', $blob)) $groups[] = 'thuvien_thietbi';

    // Đoàn Đội
    if (preg_match('/đoàn|đội|bí\s*thư|tổng\s*phụ\s*trách/u', $blob)) {
        $groups[] = 'doandoi';
    }
    foreach ($pccmRoles as $r) {
        if (preg_match('/đoàn|đội|bí\s*thư|tổng\s*phụ\s*trách/ui', $r)) $groups[] = 'doandoi';
        if (preg_match('/TTCM|TPCM|tổ\s*trưởng|tổ\s*phó/ui', $r)) $groups[] = 'totruong';
        if (mb_stripos($r, 'GVCN') !== false) $groups[] = 'gvcn';
    }

    if ($gvcnClasses) $groups[] = 'gvcn';

    $groups = array_values(array_unique($groups));
    if (!$groups) $groups[] = 'gv';
    return $groups;
}

/**
 * Chạy load hệ thống.
 * @return array{created:int,updated:int,skipped:int,no_phone:int,details:array}
 */
function sync_users_from_system() {
    require_once __DIR__ . '/csdl_store.php';
    require_once __DIR__ . '/permissions.php';

    $presets = user_group_presets();
    $pccm = pccm_role_index();
    $hr = csdl_homeroom_index();

    $users = get_users();
    $byUser = [];
    $byTeacher = [];
    foreach ($users as $i => $u) {
        $byUser[strtolower($u['username'] ?? '')] = $i;
        $tn = name_key($u['teacher_name'] ?? $u['name'] ?? '');
        if ($tn !== '') $byTeacher[$tn] = $i;
    }

    $created = 0; $updated = 0; $skipped = 0; $noPhone = 0;
    $details = [];

    foreach (csdl_teachers_all() as $t) {
        if (isset($t['active']) && empty($t['active'])) continue;

        $name = trim($t['name'] ?? '');
        if ($name === '') continue;
        $nk = name_key($name);

        $phone = normalize_phone($t['phone'] ?? '');
        if (strlen($phone) < 9) {
            $noPhone++;
            $details[] = ['name' => $name, 'status' => 'no_phone'];
            continue;
        }

        $pccmRoles = $pccm['roles'][$nk] ?? [];
        $classes = array_values(array_unique(array_merge(
            $pccm['gvcn_classes'][$nk] ?? [],
            $hr[$nk] ?? []
        )));

        $detectedGroups = detect_groups_for_teacher($t, $pccmRoles, $classes);

        // Với tài khoản đã chuyển sang mô hình phân quyền mới, danh sách
        // nhóm do quản trị chọn là nguồn chính xác. Đồng bộ chỉ cập nhật
        // nhóm gợi ý để tham khảo, tuyệt đối không tự gán lại nhóm đã gỡ.
        $idx = $byUser[strtolower($phone)] ?? ($byTeacher[$nk] ?? null);
        $hasManagedGroups = $idx !== null
            && ((int)($users[$idx]['permission_model_version'] ?? 0) >= 2
                || array_key_exists('groups', $users[$idx]));
        if ($hasManagedGroups) {
            $groups = is_array($users[$idx]['groups'] ?? null)
                ? array_values(array_unique(array_filter(array_map('strval', $users[$idx]['groups']))))
                : [];
        } else {
            $groups = $detectedGroups;
        }

        // merge quyền từ các nhóm
        $modules = ['chuyenmon'=>'none','csdl'=>'none','noitru'=>'none','vanban'=>'none','thidua'=>'none'];
        $perms = [];
        $primaryRole = 'gv';
        $priority = ['bgh'=>1,'qlnt'=>2,'vanthu'=>3,'ketoan'=>3,'doandoi'=>4,'thuvien_thietbi'=>4,'totruong'=>5,'gvcn'=>6,'gv'=>9];
        $best = 99;
        foreach ($groups as $g) {
            if (!isset($presets[$g])) continue;
            $p = $presets[$g];
            $modules = merge_modules($modules, $p['modules']);
            $perms = array_values(array_unique(array_merge($perms, $p['perms'])));
            $pr = $priority[$g] ?? 50;
            if ($pr < $best) { $best = $pr; $primaryRole = $p['role']; }
        }

        // Chỉ giới hạn lớp nếu có GVCN và không thuộc nhóm quản lý toàn trường.
        $scopeClasses = [];
        if (in_array('gvcn', $groups, true) && !array_intersect($groups, ['bgh','qlnt','vanthu','ketoan'])) {
            $scopeClasses = $classes;
        }

        // Phạm vi lớp khác là phần quản trị gán thủ công. Khi đồng bộ lại
        // giáo viên/PCCM chỉ cập nhật lớp chủ nhiệm phát hiện được, không xóa
        // phạm vi bổ sung mà quản trị đã cấp cho tài khoản hiện hữu.
        $manualClasses = $idx !== null && is_array($users[$idx]['classes'] ?? null)
            ? array_values(array_unique(array_filter(array_map('strval', $users[$idx]['classes']))))
            : $scopeClasses;

        $payload = [
            'username' => $phone,
            'name' => $name,
            'teacher_name' => $name,
            'role' => $primaryRole,
            'groups' => $groups,
            'detected_groups' => $detectedGroups,
            'modules' => $modules,
            'perms' => $perms,
            'classes' => $manualClasses,
            'homeroom_classes' => $classes,
            'active' => true,
            'phone' => $phone,
            'source' => 'sync_csdl',
            'permission_model_version' => 2,
            'updated_at' => date('c'),
        ];

        if ($idx !== null) {
            // không đụng admin hệ thống
            if (($users[$idx]['role'] ?? '') === 'admin' && ($users[$idx]['username'] ?? '') === DEFAULT_ADMIN_USER) {
                $skipped++;
                $details[] = ['name' => $name, 'status' => 'skip_admin'];
                continue;
            }
            $users[$idx] = array_merge($users[$idx], $payload);
            // không đổi mật khẩu khi cập nhật
            $updated++;
            $details[] = ['name' => $name, 'user' => $phone, 'status' => 'updated', 'groups' => $groups, 'classes' => $scopeClasses];
        } else {
            $users[] = array_merge($payload, [
                'id' => 'u' . bin2hex(random_bytes(4)),
                'password_hash' => password_hash(DEFAULT_USER_PASSWORD, PASSWORD_DEFAULT),
                'created_at' => date('c'),
                'must_change_password' => true,
            ]);
            $byUser[strtolower($phone)] = count($users) - 1;
            $byTeacher[$nk] = count($users) - 1;
            $created++;
            $details[] = ['name' => $name, 'user' => $phone, 'status' => 'created', 'groups' => $groups, 'classes' => $scopeClasses];
        }
    }

    $saved = save_users($users);
    return compact('created', 'updated', 'skipped', 'noPhone', 'details', 'saved');
}

<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function load_json($file, $default = []) {
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : $default;
    }
    return $default;
}

function save_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function init_users() {
    if (!file_exists(USERS_FILE)) {
        $users = [[
            'id' => 'u1',
            'username' => DEFAULT_ADMIN_USER,
            'password_hash' => password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT),
            'name' => 'Quản trị hệ thống',
            'role' => 'admin',
            'modules' => [
                'chuyenmon' => 'admin', 'csdl' => 'admin', 'noitru' => 'admin',
                'vanban' => 'admin', 'thidua' => 'admin',
            ],
            'perms' => [], // admin: full qua role
            'classes' => [],
            'active' => true,
            'created_at' => date('c'),
        ]];
        save_json(USERS_FILE, $users);
    }
}

function get_users() {
    init_users();
    return load_json(USERS_FILE, []);
}

function save_users(array $users) {
    save_json(USERS_FILE, array_values($users));
}

function find_user($username) {
    foreach (get_users() as $u) {
        if (strcasecmp($u['username'] ?? '', $username) === 0) return $u;
    }
    return null;
}

function find_user_by_id($id) {
    foreach (get_users() as $u) {
        if (($u['id'] ?? '') === $id) return $u;
    }
    return null;
}

function is_logged_in() {
    return !empty($_SESSION['cds_user']);
}

function current_user() {
    return $_SESSION['cds_user'] ?? null;
}

function session_user_from_record(array $u) {
    return [
        'id' => $u['id'] ?? '',
        'username' => $u['username'] ?? '',
        'name' => $u['name'] ?? ($u['username'] ?? ''),
        'role' => $u['role'] ?? 'gv',
        'modules' => is_array($u['modules'] ?? null) ? $u['modules'] : [],
        'perms' => is_array($u['perms'] ?? null) ? $u['perms'] : [],
        'classes' => is_array($u['classes'] ?? null) ? $u['classes'] : [],
        'teacher_name' => $u['teacher_name'] ?? '',
    ];
}

function attempt_login($username, $password) {
    $u = find_user($username);
    if (!$u || empty($u['active'])) return false;
    if (!password_verify($password, $u['password_hash'] ?? '')) return false;

    $_SESSION['cds_user'] = session_user_from_record($u);

    // Cầu nối Chuyên môn (PCCM)
    $role = $u['role'] ?? '';
    $cmLevel = $u['modules']['chuyenmon'] ?? 'none';
    $_SESSION['pccm_admin'] = ($role === 'admin')
        || in_array($role, ['bgh', 'totruong'], true)
        || in_array($cmLevel, ['edit', 'admin'], true)
        || in_array('cm.pccm', $u['perms'] ?? [], true);

    return true;
}

function logout_user() {
    unset($_SESSION['cds_user'], $_SESSION['pccm_admin']);
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . 'login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        exit;
    }
}

function require_admin() {
    require_login();
    $u = current_user();
    if (($u['role'] ?? '') !== 'admin') {
        flash('Chỉ quản trị hệ thống được truy cập.', 'danger');
        header('Location: ' . BASE_URL . 'admin.php');
        exit;
    }
}

function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function flash($msg, $type = 'success') {
    $_SESSION['cds_flash'] = ['message' => $msg, 'type' => $type];
}

function show_flash() {
    if (empty($_SESSION['cds_flash'])) return;
    $f = $_SESSION['cds_flash'];
    unset($_SESSION['cds_flash']);
    $type = $f['type'] ?? 'info';
    $cls = $type === 'danger' ? 'alert-danger' : ($type === 'warning' ? 'alert-warning' : 'alert-success');
    echo '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
        . e($f['message'] ?? '')
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

init_users();
require_once __DIR__ . '/permissions.php';

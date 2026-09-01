<?php
/**
 * Quản lý tài khoản + phân quyền 3 tầng + Load từ CSDL/PCCM
 */
require_once 'includes/auth.php';
require_once 'includes/user_sync.php';
require_once 'includes/account_profile.php';
require_admin();

$modCatalog = permission_modules_catalog();
$featCatalog = permission_features_catalog();
$presets = permission_role_presets();
$levels = permission_levels();
$groupPresets = user_group_presets();
$permissionGroups = permission_groups_all();
$accessLevels = permission_access_levels();
$overrideLevels = permission_access_levels(true);

/* Lớp từ CSDL */
$allClasses = [];
require_once 'includes/csdl_store.php';
foreach (csdl_classes_all() as $c) {
    if (!empty($c['name'])) $allClasses[] = $c['name'];
}
if (!$allClasses) {
    $allClasses = ['6A','6B','7A','7B','7C','8A','8B','8C','9A','9B','10A','10B','11A','11B','12A','12B'];
}
$allClasses = array_values(array_unique($allClasses));
usort($allClasses, 'csdl_compare_class_names');

$syncReport = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $users = get_users();

    if ($action === 'admin_reset_password') {
        if (!cds_account_csrf_valid((string)($_POST['csrf'] ?? ''))) {
            flash('Phiên làm việc không hợp lệ, vui lòng thử lại.', 'danger');
        } else {
            $result = cds_account_admin_reset_password((string)($_POST['id'] ?? ''), (string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));
            flash($result['message'], !empty($result['ok']) ? 'success' : 'danger');
        }
        header('Location: users.php'); exit;
    }

    if ($action === 'save_group') {
        $groupKey = trim($_POST['group_key'] ?? '');
        if (!isset($permissionGroups[$groupKey])) {
            flash('Nhóm quyền không hợp lệ.', 'danger');
            header('Location: users.php?view=groups'); exit;
        }
        $group = $permissionGroups[$groupKey];
        $group['label'] = trim($_POST['group_label'] ?? ($group['label'] ?? $groupKey));
        $group['access'] = [];
        foreach ($featCatalog as $code => $meta) {
            $level = $_POST['access'][$code] ?? 'none';
            $group['access'][$code] = in_array($level, ['none','view','edit','delete'], true) ? $level : 'none';
        }
        $permissionGroups[$groupKey] = $group;
        if (!permission_groups_save($permissionGroups)) {
            flash('Không thể ghi tệp nhóm quyền. Hãy kiểm tra quyền ghi thư mục data trên host.', 'danger');
            header('Location: users.php?view=groups&group=' . urlencode($groupKey)); exit;
        }
        $savedGroup = permission_groups_all()[$groupKey] ?? null;
        if (!is_array($savedGroup) || ($savedGroup['access'] ?? []) !== ($group['access'] ?? [])) {
            flash('Tệp đã ghi nhưng kết quả đối chiếu không khớp. Hệ thống chưa áp dụng thay đổi.', 'danger');
            header('Location: users.php?view=groups&group=' . urlencode($groupKey)); exit;
        }
        flash('Đã lưu và đối chiếu quyền cho nhóm ' . $group['label'] . '.');
        header('Location: users.php?view=groups&group=' . urlencode($groupKey)); exit;
    }

    if ($action === 'create_group') {
        $groupKey = strtolower(trim($_POST['new_group_key'] ?? ''));
        $groupKey = preg_replace('/[^a-z0-9_]+/', '_', $groupKey);
        $groupKey = trim($groupKey, '_');
        $groupLabel = trim($_POST['new_group_label'] ?? '');
        if ($groupKey === '' || $groupLabel === '') {
            flash('Hãy nhập mã nhóm không dấu và tên nhóm.', 'danger');
            header('Location: users.php?view=groups'); exit;
        }
        if (isset($permissionGroups[$groupKey])) {
            flash('Mã nhóm đã tồn tại.', 'warning');
            header('Location: users.php?view=groups&group=' . urlencode($groupKey)); exit;
        }
        $permissionGroups[$groupKey] = ['label' => $groupLabel, 'access' => []];
        if (!permission_groups_save($permissionGroups)) {
            flash('Không thể tạo nhóm quyền do tệp dữ liệu không ghi được.', 'danger');
            header('Location: users.php?view=groups'); exit;
        }
        flash('Đã tạo nhóm quyền ' . $groupLabel . '.');
        header('Location: users.php?view=groups&group=' . urlencode($groupKey)); exit;
    }

    if ($action === 'load_system') {
        $syncReport = sync_users_from_system();
        if (empty($syncReport['saved'])) {
            flash('Không thể lưu tài khoản sau khi nạp hệ thống. Dữ liệu quyền chưa bị thay đổi; hãy kiểm tra quyền ghi tệp users.json.', 'danger');
            header('Location: users.php');
            exit;
        }
        flash(
            'Load xong: tạo mới ' . $syncReport['created']
            . ', cập nhật ' . $syncReport['updated']
            . ', bỏ qua (không SĐT) ' . $syncReport['noPhone']
            . '. Mật khẩu mặc định tài khoản mới: ' . DEFAULT_USER_PASSWORD,
            'success'
        );
        header('Location: users.php?synced=1');
        exit;
    }

    if ($action === 'set_module_access' || $action === 'bulk_module_access') {
        $moduleKey = trim($_POST['module_key'] ?? '');
        $level = trim($_POST['level'] ?? 'none');
        $selectedIds = $action === 'set_module_access'
            ? [trim($_POST['user_id'] ?? '')]
            : array_values(array_filter(array_map('strval', (array)($_POST['selected_users'] ?? []))));

        if (!isset($modCatalog[$moduleKey]) || !in_array($level, ['none','view','edit','delete'], true) || !$selectedIds) {
            flash('Thiếu người dùng, module hoặc mức quyền hợp lệ.', 'danger');
            header('Location: users.php'); exit;
        }

        $changed = 0;
        foreach ($users as &$u) {
            if (!in_array((string)($u['id'] ?? ''), $selectedIds, true)) continue;
            if (($u['role'] ?? '') === 'admin') continue;
            $overrides = is_array($u['permission_overrides'] ?? null) ? $u['permission_overrides'] : [];
            foreach ($featCatalog as $code => $meta) {
                if (($meta['module'] ?? '') !== $moduleKey) continue;
                $overrides[$code] = $level;
            }
            $u['permission_overrides'] = $overrides;
            $u['permission_model_version'] = 2;
            $u['updated_at'] = date('c');
            $changed++;
        }
        unset($u);
        if (!save_users($users)) {
            flash('Không thể lưu quyền module. Hãy kiểm tra quyền ghi tệp users.json.', 'danger');
            header('Location: users.php'); exit;
        }
        flash('Đã cập nhật quyền ' . ($modCatalog[$moduleKey]['label'] ?? $moduleKey) . ' cho ' . $changed . ' người.');
        header('Location: users.php'); exit;
    }

    if ($action === 'bulk_feature_access') {
        $target = trim($_POST['permission_target'] ?? 'selected');
        $selectedIds = array_values(array_filter(array_map('strval', (array)($_POST['permission_users'] ?? []))));
        $applyCodes = array_values(array_filter(array_map('strval', (array)($_POST['apply_features'] ?? []))));
        $rights = is_array($_POST['feature_rights'] ?? null) ? $_POST['feature_rights'] : [];

        if (!$applyCodes) {
            flash('Chưa chọn chức năng nào để áp dụng.', 'warning');
            header('Location: users.php'); exit;
        }

        $newAccess = [];
        foreach ($applyCodes as $code) {
            if (!isset($featCatalog[$code])) continue;
            $selectedRights = array_values(array_filter(array_map('strval', (array)($rights[$code] ?? []))));
            if (in_array('delete', $selectedRights, true)) $newAccess[$code] = 'delete';
            elseif (in_array('edit', $selectedRights, true)) $newAccess[$code] = 'edit';
            elseif (in_array('view', $selectedRights, true)) $newAccess[$code] = 'view';
            else $newAccess[$code] = 'none';
        }

        if ($target !== 'selected') {
            if (!isset($permissionGroups[$target])) {
                flash('Nhóm quyền không hợp lệ.', 'danger');
                header('Location: users.php'); exit;
            }
            foreach ($newAccess as $code => $level) {
                if ($level === 'none') unset($permissionGroups[$target]['access'][$code]);
                else $permissionGroups[$target]['access'][$code] = $level;
            }
            if (!permission_groups_save($permissionGroups)) {
                flash('Không thể lưu quyền nhóm. Hãy kiểm tra quyền ghi thư mục data.', 'danger');
                header('Location: users.php'); exit;
            }
            flash('Đã cập nhật ' . count($newAccess) . ' chức năng cho nhóm ' . ($permissionGroups[$target]['label'] ?? $target) . '.');
            header('Location: users.php'); exit;
        }

        if (!$selectedIds) {
            flash('Hãy tích chọn ít nhất một giáo viên.', 'warning');
            header('Location: users.php'); exit;
        }
        $changed = 0;
        foreach ($users as &$u) {
            if (!in_array((string)($u['id'] ?? ''), $selectedIds, true)) continue;
            if (($u['role'] ?? '') === 'admin') continue;
            $overrides = is_array($u['permission_overrides'] ?? null) ? $u['permission_overrides'] : [];
            foreach ($newAccess as $code => $level) $overrides[$code] = $level;
            $u['permission_overrides'] = $overrides;
            $u['permission_model_version'] = 2;
            $u['updated_at'] = date('c');
            $changed++;
        }
        unset($u);
        if (!save_users($users)) {
            flash('Không thể lưu thay đổi nhóm người dùng.', 'danger');
            header('Location: users.php'); exit;
        }
        flash('Đã cập nhật ' . count($newAccess) . ' chức năng cho ' . $changed . ' giáo viên.');
        header('Location: users.php'); exit;
    }

    if ($action === 'reset_overrides') {
        $selectedIds = array_values(array_filter(array_map('strval', (array)($_POST['selected_users'] ?? []))));
        if (!$selectedIds) {
            flash('Hãy tích chọn ít nhất một người dùng.', 'warning');
            header('Location: users.php'); exit;
        }
        $changed = 0;
        foreach ($users as &$u) {
            if (!in_array((string)($u['id'] ?? ''), $selectedIds, true) || ($u['role'] ?? '') === 'admin') continue;
            $u['permission_overrides'] = [];
            $u['permission_model_version'] = 2;
            $u['updated_at'] = date('c');
            $changed++;
        }
        unset($u);
        if (!save_users($users)) {
            flash('Không thể đưa quyền cá nhân về theo nhóm.', 'danger');
            header('Location: users.php'); exit;
        }
        flash('Đã xóa quyền ghi đè của ' . $changed . ' người dùng. Quyền hiện tại được lấy hoàn toàn từ các nhóm đã gán.');
        header('Location: users.php'); exit;
    }

    if ($action === 'bulk_group') {
        $groupKey = trim($_POST['group_key'] ?? '');
        $mode = ($_POST['group_mode'] ?? 'add') === 'remove' ? 'remove' : 'add';
        $selectedIds = array_values(array_filter(array_map('strval', (array)($_POST['selected_users'] ?? []))));
        if (!isset($permissionGroups[$groupKey]) || !$selectedIds) {
            flash('Hãy chọn giáo viên và nhóm quyền.', 'danger');
            header('Location: users.php'); exit;
        }
        $changed = 0;
        foreach ($users as &$u) {
            if (!in_array((string)($u['id'] ?? ''), $selectedIds, true)) continue;
            $groups = array_values(array_unique(array_filter(array_map('strval', $u['groups'] ?? []))));
            if ($mode === 'add' && !in_array($groupKey, $groups, true)) $groups[] = $groupKey;
            if ($mode === 'remove') $groups = array_values(array_diff($groups, [$groupKey]));
            $u['groups'] = $groups;
            $u['permission_model_version'] = 2;
            $u['updated_at'] = date('c');
            $changed++;
        }
        unset($u);
        if (!save_users($users)) {
            flash('Không thể lưu tài khoản. Hãy kiểm tra quyền ghi tệp users.json.', 'danger');
            header('Location: users.php'); exit;
        }
        flash('Đã ' . ($mode === 'add' ? 'gán' : 'gỡ') . ' nhóm cho ' . $changed . ' người.');
        header('Location: users.php'); exit;
    }

    if ($action === 'save') {
        $id = trim($_POST['id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $role = trim($_POST['role'] ?? 'custom');
        $active = !empty($_POST['active']);
        $password = $_POST['password'] ?? '';

        if ($username === '') {
            flash('Thiếu tên đăng nhập.', 'danger');
            header('Location: users.php'); exit;
        }

        $modules = [];
        foreach (array_keys($modCatalog) as $mk) {
            $modules[$mk] = $_POST['mod_' . $mk] ?? 'none';
            if (!isset($levels[$modules[$mk]])) $modules[$mk] = 'none';
        }

        $perms = [];
        if (!empty($_POST['perms']) && is_array($_POST['perms'])) {
            foreach ($_POST['perms'] as $p) {
                if (isset($featCatalog[$p])) $perms[] = $p;
            }
        }

        $classes = [];
        if (!empty($_POST['classes']) && is_array($_POST['classes'])) {
            $classes = array_values(array_filter(array_map('trim', $_POST['classes'])));
        }
        $homeroomClasses = [];
        if (!empty($_POST['homeroom_classes']) && is_array($_POST['homeroom_classes'])) {
            $homeroomClasses = array_values(array_filter(array_map('trim', $_POST['homeroom_classes'])));
        }
        $groups = [];
        if (!empty($_POST['groups']) && is_array($_POST['groups'])) {
            foreach ($_POST['groups'] as $groupKey) {
                if (isset($permissionGroups[$groupKey])) $groups[] = $groupKey;
            }
        }
        $overrides = [];
        foreach ($featCatalog as $code => $meta) {
            $level = $_POST['permission_overrides'][$code] ?? 'inherit';
            if (in_array($level, ['none','view','edit','delete'], true)) $overrides[$code] = $level;
        }

        $found = false;
        foreach ($users as &$u) {
            if (($u['id'] ?? '') === $id) {
                foreach ($users as $o) {
                    if (($o['id'] ?? '') !== $id && strcasecmp($o['username'] ?? '', $username) === 0) {
                        flash('Tên đăng nhập đã tồn tại.', 'danger');
                        header('Location: users.php'); exit;
                    }
                }
                $u['username'] = $username;
                $u['name'] = $name !== '' ? $name : $username;
                $u['role'] = $role;
                $u['active'] = $active;
                $u['modules'] = $modules;
                $u['perms'] = $perms;
                $u['classes'] = $classes;
                $u['homeroom_classes'] = $homeroomClasses;
                $u['groups'] = $groups;
                $u['permission_overrides'] = $overrides;
                $u['permission_model_version'] = 2;
                $u['teacher_name'] = trim($_POST['teacher_name'] ?? $u['teacher_name'] ?? '');
                if ($password !== '') {
                    $u['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    $u['must_change_password'] = false;
                }
                $u['updated_at'] = date('c');
                $found = true;
                break;
            }
        }
        unset($u);

        if (!$found) {
            if (find_user($username)) {
                flash('Tên đăng nhập đã tồn tại.', 'danger');
                header('Location: users.php'); exit;
            }
            if ($password === '') $password = DEFAULT_USER_PASSWORD;
            $users[] = [
                'id' => 'u' . bin2hex(random_bytes(4)),
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name !== '' ? $name : $username,
                'role' => $role,
                'modules' => $modules,
                'perms' => $perms,
                'classes' => $classes,
                'homeroom_classes' => $homeroomClasses,
                'groups' => $groups,
                'permission_overrides' => $overrides,
                'permission_model_version' => 2,
                'teacher_name' => trim($_POST['teacher_name'] ?? ''),
                'active' => $active,
                'created_at' => date('c'),
            ];
        }

        if (!save_users($users)) {
            flash('Không thể lưu tài khoản. Hãy kiểm tra quyền ghi tệp users.json.', 'danger');
            header('Location: users.php'); exit;
        }
        flash('Đã lưu tài khoản.');
        header('Location: users.php'); exit;
    }

    if ($action === 'delete') {
        $id = trim($_POST['id'] ?? '');
        $me = current_user();
        if ($id === ($me['id'] ?? '')) {
            flash('Không thể xóa chính tài khoản đang đăng nhập.', 'warning');
            header('Location: users.php'); exit;
        }
        $users = array_values(array_filter($users, fn($u) => ($u['id'] ?? '') !== $id));
        if (!save_users($users)) {
            flash('Không thể xóa tài khoản do tệp users.json không ghi được.', 'danger');
            header('Location: users.php'); exit;
        }
        flash('Đã xóa tài khoản.', 'warning');
        header('Location: users.php'); exit;
    }
}

$users = get_users();
usort($users, fn($a, $b) => csdl_compare_person_names($a['name'] ?? $a['username'] ?? '', $b['name'] ?? $b['username'] ?? ''));
$userAccessMap = [];
foreach ($users as $userItem) {
    if (!empty($userItem['id'])) $userAccessMap[$userItem['id']] = permission_effective_access_for_user($userItem);
}

$featsByMod = [];
foreach ($featCatalog as $code => $meta) {
    $featsByMod[$meta['module']][$code] = $meta;
}
$view = ($_GET['view'] ?? 'users') === 'groups' ? 'groups' : 'users';
$selectedGroupKey = $_GET['group'] ?? array_key_first($permissionGroups);
if (!isset($permissionGroups[$selectedGroupKey])) $selectedGroupKey = array_key_first($permissionGroups);
$selectedGroup = $permissionGroups[$selectedGroupKey] ?? ['label' => '', 'access' => []];
function user_for_edit(array $user) {
    unset($user['password_hash']);
    return $user;
}
function user_module_access(array $user, string $moduleKey, array $featCatalog): string {
    $effective = permission_effective_access_for_user($user);
    $levels = [];
    foreach ($featCatalog as $code => $meta) {
        if (($meta['module'] ?? '') !== $moduleKey) continue;
        $levels[] = $effective[$code] ?? 'none';
    }
    $levels = array_values(array_unique($levels));
    if (count($levels) > 1) return 'mixed';
    return in_array($levels[0] ?? 'none', ['none','view','edit','delete'], true) ? ($levels[0] ?? 'none') : 'none';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tài khoản & phân quyền – CDS</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
:root{--primary:#1F4E79}
body{background:#f0f4f8}
.card{border:none;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.card-header{background:var(--primary);color:#fff;font-weight:600;border-radius:12px 12px 0 0!important}
.perm-box{max-height:220px;overflow:auto;border:1px solid #dee2e6;border-radius:8px;padding:.5rem;background:#fff}
.class-box{max-height:160px;overflow:auto;border:1px solid #dee2e6;border-radius:8px;padding:.5rem;background:#fff}
.table th{background:#e8f0fe;color:var(--primary);font-size:.85rem}
.badge-mod{font-size:.7rem}
.sync-box{background:#e8f5e9;border:1px solid #a5d6a7;border-radius:12px;padding:1rem 1.15rem}
.permission-matrix{min-width:1320px}
.permission-matrix th{position:sticky;top:0;z-index:2;white-space:nowrap;text-align:center}
.permission-matrix th:first-child,.permission-matrix td:first-child{position:sticky;left:0;z-index:1;background:#fff}
.permission-matrix thead th:first-child{z-index:3;background:#e8f0fe}
.permission-matrix td{vertical-align:middle}
.matrix-user{min-width:260px}
.matrix-module{min-width:132px}
.level-none{background:#f1f3f5;color:#6c757d;border-color:#ced4da}
.level-view{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}
.level-edit{background:#1f6feb;color:#fff;border-color:#1f6feb}
.level-delete{background:#dc3545;color:#fff;border-color:#dc3545}
.level-mixed{background:#f3e8ff;color:#7e22ce;border-color:#d8b4fe}
.permission-modal .nav-link{font-weight:600}
.permission-modal .feature-row{border-bottom:1px solid #e9ecef;padding:.7rem .25rem}
.permission-modal .feature-row:last-child{border-bottom:0}
.permission-modal .right-check{min-width:72px}
.permission-modal .apply-check{background:#fff7e6;border-radius:8px;padding:.35rem .55rem}
.matrix-toolbar{background:#f8fafc;border:1px solid #dbe5ef;border-radius:10px;padding:.75rem}
.selected-count{min-width:105px}
.editor-card{border:2px solid #b7cce0}
@media(max-width:767px){.matrix-user{min-width:220px}.permission-matrix{min-width:1180px}}
</style>
</head>
<body>
<?php
$nav_title = 'Tài khoản & phân quyền';
$nav_icon = 'bi-people-fill';
$nav_color = '#1F4E79';
$nav_module = 'admin';
if (is_file(__DIR__ . '/includes/nav_top.php')) include __DIR__ . '/includes/nav_top.php';
?>

<div class="container pb-5">
  <?php show_flash(); ?>

  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h3 class="mb-0"><i class="bi bi-shield-lock"></i> Tài khoản & phân quyền</h3>
      <div class="text-muted small">Mỗi trang một danh mục quyền · Quyền nhóm là nền · Quyền cá nhân chỉ dùng khi cần ngoại lệ</div>
    </div>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Quản trị</a>
  </div>

  <div class="alert alert-info py-2 small mb-3">
    <strong>Cách tính quyền:</strong> nhiều nhóm được cộng theo mức cao nhất; sau đó quyền cá nhân ghi đè kết quả nhóm.
    Chọn <strong>Theo nhóm</strong> hoặc dùng nút <strong>Xóa ghi đè cá nhân</strong> để tránh một quyền cá nhân cũ che quyền nhóm mới.
    Quản trị hệ thống luôn có toàn quyền.
  </div>

  <div class="btn-group mb-4">
    <a class="btn btn-sm <?= $view === 'users' ? 'btn-primary' : 'btn-outline-primary' ?>" href="users.php?view=users">
      <i class="bi bi-person"></i> Người dùng
    </a>
    <a class="btn btn-sm <?= $view === 'groups' ? 'btn-primary' : 'btn-outline-primary' ?>" href="users.php?view=groups">
      <i class="bi bi-people"></i> Nhóm quyền
    </a>
  </div>

  <?php if ($view === 'groups'): ?>
  <div class="row g-3">
    <div class="col-lg-3">
      <div class="list-group shadow-sm">
        <?php foreach ($permissionGroups as $groupKey => $group): ?>
        <a class="list-group-item list-group-item-action <?= $selectedGroupKey === $groupKey ? 'active' : '' ?>"
           href="users.php?view=groups&group=<?= urlencode($groupKey) ?>">
          <?= e($group['label'] ?? $groupKey) ?>
        </a>
        <?php endforeach; ?>
      </div>
      <form method="post" class="card mt-3">
        <input type="hidden" name="action" value="create_group">
        <div class="card-body p-3">
          <div class="fw-semibold mb-2"><i class="bi bi-plus-circle"></i> Tạo nhóm quyền</div>
          <input class="form-control form-control-sm mb-2" name="new_group_label" placeholder="Tên nhóm, ví dụ: Phụ trách bếp" required>
          <input class="form-control form-control-sm mb-2" name="new_group_key" placeholder="Mã không dấu: phutrach_bep" pattern="[A-Za-z0-9_]+" required>
          <button class="btn btn-sm btn-outline-primary w-100" type="submit">Tạo nhóm</button>
        </div>
      </form>
    </div>
    <div class="col-lg-9">
      <form method="post" class="card">
        <input type="hidden" name="action" value="save_group">
        <input type="hidden" name="group_key" value="<?= e($selectedGroupKey) ?>">
        <div class="card-header">Phân quyền nhóm</div>
        <div class="card-body">
          <label class="form-label fw-semibold">Tên nhóm</label>
          <input class="form-control mb-3" name="group_label" value="<?= e($selectedGroup['label'] ?? '') ?>" required>
          <div class="alert alert-info py-2 small">
            Không quyền: ẩn chức năng · Xem: chỉ đọc · Sửa: được cập nhật · Xóa: được xóa dữ liệu.
          </div>
          <?php foreach ($featsByMod as $mod => $feats): ?>
          <div class="card border mb-3 shadow-none">
            <div class="card-header py-2"><?= e($modCatalog[$mod]['label'] ?? $mod) ?></div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Chức năng</th><th style="width:180px">Mức quyền</th></tr></thead>
                <tbody>
                <?php foreach ($feats as $code => $meta):
                  $groupLevel = $selectedGroup['access'][$code] ?? 'none';
                ?>
                <tr>
                  <td><span class="badge text-bg-light border me-1"><?= e($meta['group'] ?? 'Chung') ?></span><?= e($meta['label']) ?><div class="text-muted small"><?= e($code) ?></div></td>
                  <td>
                    <select class="form-select form-select-sm" name="access[<?= e($code) ?>]">
                      <?php foreach ($accessLevels as $level => $label): ?>
                      <option value="<?= e($level) ?>" <?= $groupLevel === $level ? 'selected' : '' ?>><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endforeach; ?>
          <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> Lưu quyền nhóm</button>
        </div>
      </form>
    </div>
  </div>
  <?php else: ?>

  <div class="sync-box mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
      <div>
        <strong><i class="bi bi-cloud-download"></i> Load tài khoản từ hệ thống</strong>
        <ul class="small mb-0 mt-1 ps-3">
          <li>Lấy GV đang công tác trong <strong>CSDL</strong> có <strong>SĐT</strong> → tài khoản đăng nhập = SĐT</li>
          <li>Tài khoản <em>mới</em>: mật khẩu mặc định <code><?= e(DEFAULT_USER_PASSWORD) ?></code> (không ghi đè mật khẩu đã đổi)</li>
          <li>Tài khoản mới được gợi ý nhóm theo chức vụ; tài khoản hiện có giữ nguyên chính xác các nhóm quản trị đã gán hoặc gỡ</li>
          <li>GVCN: lấy lớp từ PCCM (kiêm nhiệm) + cột GVCN trong CSDL lớp</li>
        </ul>
      </div>
      <form method="post" onsubmit="return confirm('Load / cập nhật tài khoản từ CSDL + phân công?\n\nTài khoản mới: mật khẩu <?= e(DEFAULT_USER_PASSWORD) ?>');">
        <input type="hidden" name="action" value="load_system">
        <button type="submit" class="btn btn-success fw-semibold">
          <i class="bi bi-arrow-repeat"></i> Load hệ thống
        </button>
      </form>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <?php foreach ($groupPresets as $gk => $gp): ?>
    <div class="col-6 col-md-4 col-lg-2">
      <div class="card h-100"><div class="card-body py-2 px-2 small text-center">
        <div class="fw-semibold"><?= e($gp['label']) ?></div>
        <div class="text-muted" style="font-size:.7rem"><?= e($gk) ?></div>
      </div></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span><i class="bi bi-grid-3x3-gap"></i> Bảng phân quyền giáo viên (<?= count($users) ?>)</span>
      <button type="button" class="btn btn-sm btn-light" onclick="openEditor()">
        <i class="bi bi-person-plus"></i> Thêm tài khoản
      </button>
    </div>
    <div class="card-body pb-2">
      <div class="row g-2 mb-3">
        <div class="col-md-5">
          <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input class="form-control" id="matrixSearch" placeholder="Tìm họ tên hoặc số điện thoại…" oninput="filterMatrix()">
          </div>
        </div>
        <div class="col-md-3">
          <select class="form-select form-select-sm" id="matrixGroupFilter" onchange="filterMatrix()">
            <option value="">Tất cả nhóm</option>
            <?php foreach ($permissionGroups as $groupKey => $group): ?>
            <option value="<?= e($groupKey) ?>"><?= e($group['label'] ?? $groupKey) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="matrixClassFilter" onchange="filterMatrix()">
            <option value="">Tất cả lớp</option>
            <?php foreach ($allClasses as $cl): ?><option value="<?= e($cl) ?>"><?= e($cl) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <select class="form-select form-select-sm" id="matrixStatusFilter" onchange="filterMatrix()">
            <option value="">Mọi trạng thái</option>
            <option value="1">Đang hoạt động</option>
            <option value="0">Đã khóa</option>
          </select>
        </div>
      </div>

      <form method="post" id="bulkPermissionForm">
        <div class="matrix-toolbar mb-3">
          <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="selected-count">
              <div class="small text-muted">Đã chọn</div>
              <strong><span id="selectedCount">0</span> giáo viên</strong>
            </div>
            <div>
              <label class="small text-muted">Đối tượng phân quyền</label>
              <select class="form-select form-select-sm" id="permissionTarget">
                <option value="selected">Các giáo viên đã tích</option>
                <?php foreach ($permissionGroups as $groupKey => $group): ?>
                <option value="<?= e($groupKey) ?>">Nhóm: <?= e($group['label'] ?? $groupKey) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-sm btn-primary" type="button" onclick="openPermissionModal()">
              <i class="bi bi-shield-check"></i> Phân quyền
            </button>
            <button class="btn btn-sm btn-outline-secondary" name="action" value="reset_overrides" type="submit"
                    onclick="return confirm('Xóa toàn bộ quyền chỉnh riêng của người đã chọn và chuyển về dùng quyền nhóm?')">
              <i class="bi bi-arrow-counterclockwise"></i> Xóa ghi đè cá nhân
            </button>
            <div class="vr d-none d-lg-block"></div>
            <div>
              <label class="small text-muted">Nhóm quyền</label>
              <select class="form-select form-select-sm" name="group_key">
                <?php foreach ($permissionGroups as $groupKey => $group): ?>
                <option value="<?= e($groupKey) ?>"><?= e($group['label'] ?? $groupKey) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="small text-muted">Thao tác</label>
              <select class="form-select form-select-sm" name="group_mode">
                <option value="add">Gán nhóm</option>
                <option value="remove">Gỡ nhóm</option>
              </select>
            </div>
            <button class="btn btn-sm btn-outline-primary" name="action" value="bulk_group" type="submit">
              <i class="bi bi-people"></i> Áp dụng nhóm
            </button>
          </div>
        </div>

        <div class="table-responsive border rounded">
          <table class="table table-sm table-hover mb-0 permission-matrix">
            <thead>
              <tr>
                <th class="text-start matrix-user">
                  <input class="form-check-input me-2" type="checkbox" id="selectAllUsers" onchange="toggleAllUsers(this)">
                  Giáo viên
                </th>
                <th>Nhóm / lớp</th>
                <?php foreach ($modCatalog as $moduleKey => $module): ?>
                <th class="matrix-module" title="<?= e($module['label'] ?? $moduleKey) ?>">
                  <i class="bi <?= e($module['icon'] ?? 'bi-grid') ?>"></i>
                  <div><?= e($module['label'] ?? $moduleKey) ?></div>
                </th>
                <?php endforeach; ?>
                <th>Chi tiết</th>
              </tr>
            </thead>
            <tbody id="permissionMatrixBody">
            <?php foreach ($users as $u):
              $cls = array_values(array_unique(array_merge($u['classes'] ?? [], $u['homeroom_classes'] ?? [])));
              $gr = array_values(array_filter($u['groups'] ?? []));
              $isAdmin = ($u['role'] ?? '') === 'admin';
              $searchText = trim(($u['username'] ?? '') . ' ' . ($u['name'] ?? ''));
            ?>
              <tr class="matrix-row <?= empty($u['active']) ? 'table-secondary' : '' ?>"
                  data-search="<?= e($searchText) ?>"
                  data-groups="<?= e(implode('|', $gr)) ?>"
                  data-classes="<?= e(implode('|', $cls)) ?>"
                  data-active="<?= !empty($u['active']) ? '1' : '0' ?>">
                <td class="matrix-user">
                  <div class="d-flex align-items-start gap-2">
                    <input class="form-check-input user-select mt-1" type="checkbox"
                           name="selected_users[]" value="<?= e($u['id'] ?? '') ?>"
                           onchange="updateSelectedCount()" <?= $isAdmin ? 'disabled' : '' ?>>
                    <div>
                      <div class="fw-semibold"><?= e($u['name'] ?? $u['username'] ?? '') ?></div>
                      <div class="small text-muted"><?= e($u['username'] ?? '') ?>
                        <?= empty($u['active']) ? ' · Đã khóa' : '' ?>
                      </div>
                    </div>
                  </div>
                </td>
                <td class="small">
                  <?php if ($gr): foreach ($gr as $g): ?>
                    <span class="badge bg-light text-dark border badge-mod"><?= e($permissionGroups[$g]['label'] ?? $g) ?></span>
                  <?php endforeach; else: ?><span class="text-muted">Chưa gán nhóm</span><?php endif; ?>
                  <div class="mt-1"><?= $cls ? e(implode(', ', $cls)) : '<span class="text-muted">Mọi lớp</span>' ?></div>
                </td>
                <?php foreach ($modCatalog as $moduleKey => $module):
                  $moduleLevel = user_module_access($u, $moduleKey, $featCatalog);
                ?>
                <td class="text-center">
                  <?php if ($isAdmin): ?>
                    <span class="badge bg-dark">Quản trị</span>
                  <?php else: ?>
                    <?php $moduleLevelLabels = ['none'=>'Không','view'=>'Xem','edit'=>'Sửa','delete'=>'Xóa','mixed'=>'Tùy chỉnh']; ?>
                    <span class="badge level-<?= e($moduleLevel) ?>">
                      <?= e($moduleLevelLabels[$moduleLevel] ?? 'Không') ?>
                    </span>
                  <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td class="text-nowrap text-center">
                  <button type="button" class="btn btn-sm btn-outline-warning" title="Đặt lại mật khẩu"
                          onclick='openPasswordReset(<?=json_encode((string)($u['id']??''),JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_TAG|JSON_HEX_QUOT)?>,<?=json_encode((string)($u['name']??$u['username']??''),JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_TAG|JSON_HEX_QUOT)?>)'>
                    <i class="bi bi-key"></i>
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-primary"
                          onclick='editUser(<?= json_encode(user_for_edit($u), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT) ?>)'>
                    <i class="bi bi-sliders"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </form>
      <div class="small text-muted mt-2">
        <span class="badge level-none">Không</span> bị ẩn ·
        <span class="badge level-view">Xem</span> chỉ đọc ·
        <span class="badge level-edit">Sửa</span> được thao tác ·
        <span class="badge level-delete">Xóa</span> được xóa dữ liệu ·
        <span class="badge level-mixed">Tùy chỉnh</span> có nhiều mức quyền bên trong.
        Muốn phân quyền từng chức năng hoặc lớp chủ nhiệm, nhấn <i class="bi bi-sliders"></i>.
      </div>
    </div>
  </div>

  <div class="modal fade permission-modal" id="permissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <form method="post" id="permissionPopupForm">
          <input type="hidden" name="action" value="bulk_feature_access">
          <input type="hidden" name="permission_target" id="popupPermissionTarget" value="selected">
          <div id="popupSelectedUsers"></div>
          <div class="modal-header">
            <div>
              <h5 class="modal-title"><i class="bi bi-shield-check"></i> Phân quyền chức năng</h5>
              <div class="small text-muted" id="permissionTargetSummary"></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
          </div>
          <div class="modal-body">
            <div class="alert alert-info py-2 small">
              Tích <strong>Áp dụng</strong> ở chức năng muốn thay đổi. Quyền Xóa tự bao gồm quyền Xem và Sửa.
              Chức năng không tích “Áp dụng” sẽ được giữ nguyên. Khi phân cho người dùng, đây là quyền cá nhân và sẽ ghi đè quyền nhóm; muốn quay về nhóm hãy dùng “Xóa ghi đè cá nhân”.
            </div>
            <ul class="nav nav-tabs flex-nowrap overflow-auto" role="tablist">
              <?php $tabIndex = 0; foreach ($modCatalog as $moduleKey => $module): ?>
              <li class="nav-item" role="presentation">
                <button class="nav-link <?= $tabIndex === 0 ? 'active' : '' ?>" data-bs-toggle="tab"
                        data-bs-target="#permTab_<?= e($moduleKey) ?>" type="button" role="tab">
                  <i class="bi <?= e($module['icon'] ?? 'bi-grid') ?>"></i>
                  <?= e($module['label'] ?? $moduleKey) ?>
                </button>
              </li>
              <?php $tabIndex++; endforeach; ?>
            </ul>
            <div class="tab-content border border-top-0 rounded-bottom p-3">
              <?php $tabIndex = 0; foreach ($modCatalog as $moduleKey => $module): ?>
              <div class="tab-pane fade <?= $tabIndex === 0 ? 'show active' : '' ?>"
                   id="permTab_<?= e($moduleKey) ?>" role="tabpanel">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                  <strong><?= e($module['label'] ?? $moduleKey) ?></strong>
                  <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="selectModuleRights('<?= e($moduleKey) ?>','view')">Tất cả Xem</button>
                    <button type="button" class="btn btn-outline-primary" onclick="selectModuleRights('<?= e($moduleKey) ?>','edit')">Tất cả Sửa</button>
                    <button type="button" class="btn btn-outline-danger" onclick="selectModuleRights('<?= e($moduleKey) ?>','delete')">Tất cả Xóa</button>
                    <button type="button" class="btn btn-outline-dark" onclick="clearModuleRights('<?= e($moduleKey) ?>')">Bỏ quyền</button>
                  </div>
                </div>
                <?php foreach (($featsByMod[$moduleKey] ?? []) as $code => $meta): ?>
                <div class="feature-row row align-items-center g-2" data-module="<?= e($moduleKey) ?>">
                  <div class="col-lg-6">
                    <div class="fw-semibold"><?= e($meta['label']) ?></div>
                    <div class="small text-muted"><?= e($code) ?></div>
                  </div>
                  <div class="col-lg-6">
                    <div class="d-flex flex-wrap justify-content-lg-end align-items-center gap-3">
                      <label class="form-check apply-check mb-0">
                        <input class="form-check-input feature-apply" type="checkbox"
                               name="apply_features[]" value="<?= e($code) ?>">
                        <span class="form-check-label fw-semibold">Áp dụng</span>
                      </label>
                      <?php foreach (['view' => 'Xem', 'edit' => 'Sửa', 'delete' => 'Xóa'] as $right => $label): ?>
                      <label class="form-check right-check mb-0">
                        <input class="form-check-input feature-right" type="checkbox"
                               name="feature_rights[<?= e($code) ?>][]" value="<?= e($right) ?>"
                               data-code="<?= e($code) ?>" data-right="<?= e($right) ?>"
                               onchange="syncFeatureRights(this)">
                        <span class="form-check-label"><?= e($label) ?></span>
                      </label>
                      <?php endforeach; ?>
                    </div>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php $tabIndex++; endforeach; ?>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-save"></i> Lưu phân quyền
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="passwordResetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow">
      <form method="post" id="passwordResetForm">
        <input type="hidden" name="action" value="admin_reset_password">
        <input type="hidden" name="csrf" value="<?=e(cds_account_csrf_token())?>">
        <input type="hidden" name="id" id="resetPasswordUserId">
        <div class="modal-header"><div><h5 class="modal-title"><i class="bi bi-key text-warning"></i> Đặt lại mật khẩu</h5><div class="small text-muted" id="resetPasswordUserName"></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="alert alert-warning small">Mật khẩu mới có ít nhất 8 ký tự. Quản trị không thể xem lại mật khẩu sau khi lưu.</div><label class="form-label">Mật khẩu mới</label><div class="input-group mb-3"><input type="text" class="form-control" name="new_password" id="adminNewPassword" minlength="8" required autocomplete="new-password"><button type="button" class="btn btn-outline-secondary" onclick="generateAdminPassword()"><i class="bi bi-stars"></i> Tạo nhanh</button></div><label class="form-label">Nhập lại mật khẩu</label><input type="text" class="form-control" name="confirm_password" id="adminConfirmPassword" minlength="8" required autocomplete="new-password"></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Hủy</button><button class="btn btn-warning"><i class="bi bi-shield-check"></i> Lưu mật khẩu mới</button></div>
      </form>
    </div></div>
  </div>

  <div class="row g-3">
    <div class="col-12 collapse" id="userEditor">
      <div class="card editor-card">
        <div class="card-header" id="formTitle">Thêm / sửa thủ công</div>
        <div class="card-body">
          <form method="post" id="userForm">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" id="f_id" value="">
            <input type="hidden" name="teacher_name" id="f_teacher" value="">

            <div class="row g-2 mb-2">
              <div class="col-6">
                <label class="form-label small fw-semibold">Tên đăng nhập (SĐT)</label>
                <input type="text" name="username" id="f_username" class="form-control form-control-sm" required autocomplete="off">
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Họ tên</label>
                <input type="text" name="name" id="f_name" class="form-control form-control-sm">
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Mật khẩu <span class="text-muted fw-normal" id="pwHint">(trống khi tạo = <?= e(DEFAULT_USER_PASSWORD) ?>)</span></label>
              <input type="password" name="password" id="f_password" class="form-control form-control-sm" autocomplete="new-password">
            </div>

            <div class="mb-2">
              <label class="form-label small fw-semibold">Vai trò</label>
              <select name="role" id="f_role" class="form-select form-select-sm" onchange="applyPreset()">
                <?php foreach ($presets as $rk => $rp): ?>
                <option value="<?= e($rk) ?>"><?= e($rp['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="active" value="1" id="f_active" checked>
              <label class="form-check-label small" for="f_active">Đang hoạt động</label>
            </div>

            <h6 class="text-primary"><i class="bi bi-1-circle"></i> Nhóm quyền</h6>
            <div class="form-text mb-2">Có thể chọn đồng thời nhiều nhóm; hệ thống sẽ cộng quyền cao nhất của các nhóm.</div>
            <div class="perm-box mb-3">
              <?php foreach ($permissionGroups as $groupKey => $group): ?>
              <div class="form-check">
                <input class="form-check-input group-cb" type="checkbox" name="groups[]" value="<?= e($groupKey) ?>" id="g_<?= e($groupKey) ?>">
                <label class="form-check-label small" for="g_<?= e($groupKey) ?>"><?= e($group['label'] ?? $groupKey) ?></label>
              </div>
              <?php endforeach; ?>
            </div>

            <h6 class="text-primary"><i class="bi bi-2-circle"></i> Quyền cá nhân</h6>
            <div class="form-text mb-2"><strong>Theo nhóm</strong> là lựa chọn nên dùng. Chỉ chọn Không quyền / Xem / Sửa / Xóa khi người này cần ngoại lệ riêng; ngoại lệ cá nhân luôn ưu tiên hơn nhóm.</div>
            <div class="perm-box mb-3" style="max-height:360px">
              <?php foreach ($featsByMod as $mod => $feats): ?>
                <div class="small fw-semibold text-secondary mb-1 mt-2"><?= e($modCatalog[$mod]['label'] ?? $mod) ?></div>
                <?php foreach ($feats as $code => $meta): ?>
                <div class="row g-1 align-items-center mb-1">
                  <label class="col-8 small" for="ov_<?= e($code) ?>"><?= e($meta['label']) ?></label>
                  <div class="col-4">
                    <select class="form-select form-select-sm override-select" name="permission_overrides[<?= e($code) ?>]" id="ov_<?= e($code) ?>" data-code="<?= e($code) ?>">
                      <?php foreach ($overrideLevels as $level => $label): ?>
                      <option value="<?= e($level) ?>"><?= e($label) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>

            <h6 class="text-primary"><i class="bi bi-3-circle"></i> Lớp chủ nhiệm</h6>
            <div class="form-text mb-1">Dùng riêng cho nhóm GVCN; có thể chọn nhiều lớp.</div>
            <div class="class-box mb-3">
              <?php foreach ($allClasses as $cl): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input homeroom-cb" type="checkbox" name="homeroom_classes[]" value="<?= e($cl) ?>" id="hr_<?= e($cl) ?>">
                <label class="form-check-label small" for="hr_<?= e($cl) ?>"><?= e($cl) ?></label>
              </div>
              <?php endforeach; ?>
            </div>

            <h6 class="text-primary"><i class="bi bi-4-circle"></i> Phạm vi lớp khác</h6>
            <div class="form-text mb-1">Trống = mọi lớp, trừ GVCN luôn bị giới hạn theo lớp chủ nhiệm.</div>
            <div class="class-box mb-3">
              <?php foreach ($allClasses as $cl): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input class-cb" type="checkbox" name="classes[]" value="<?= e($cl) ?>" id="cl_<?= e($cl) ?>">
                <label class="form-check-label small" for="cl_<?= e($cl) ?>"><?= e($cl) ?></label>
              </div>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="btn btn-primary btn-sm w-100">Lưu</button>
            <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-1" onclick="resetForm()">Làm mới</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7 d-none">
      <div class="card">
        <div class="card-header">Danh sách (<?= count($users) ?>)</div>
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0 align-middle">
            <thead>
              <tr><th>Tài khoản</th><th>Nhóm / vai trò</th><th>Lớp</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u):
              $cls = $u['classes'] ?? [];
              $gr = $u['groups'] ?? [];
            ?>
              <tr class="<?= empty($u['active']) ? 'table-secondary' : '' ?>">
                <td>
                  <strong><?= e($u['username'] ?? '') ?></strong>
                  <div class="small text-muted"><?= e($u['name'] ?? '') ?></div>
                </td>
                <td class="small">
                  <?= e($presets[$u['role'] ?? '']['label'] ?? ($u['role'] ?? '')) ?>
                  <?php if ($gr): ?>
                    <div class="mt-1"><?php foreach ($gr as $g): ?>
                      <span class="badge bg-light text-dark border badge-mod"><?= e($permissionGroups[$g]['label'] ?? $g) ?></span>
                    <?php endforeach; ?></div>
                  <?php endif; ?>
                </td>
                <td class="small"><?= $cls ? e(implode(', ', $cls)) : '<span class="text-muted">Tất cả</span>' ?></td>
                <td class="text-nowrap">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode(user_for_edit($u), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT) ?>)'><i class="bi bi-pencil"></i></button>
                  <?php if (($u['id'] ?? '') !== (current_user()['id'] ?? '')): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Xóa?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= e($u['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
const PRESETS = <?= json_encode($presets, JSON_UNESCAPED_UNICODE) ?>;
const GROUP_ACCESS = <?= json_encode(array_map(fn($g) => $g['access'] ?? [], $permissionGroups), JSON_UNESCAPED_UNICODE) ?>;
const USER_ACCESS = <?= json_encode($userAccessMap, JSON_UNESCAPED_UNICODE) ?>;
function visibleUserCheckboxes(){
  return Array.from(document.querySelectorAll('.matrix-row'))
    .filter(function(row){return row.style.display!=='none';})
    .map(function(row){return row.querySelector('.user-select');})
    .filter(function(cb){return cb && !cb.disabled;});
}
function updateSelectedCount(){
  const selected=document.querySelectorAll('.user-select:checked').length;
  document.getElementById('selectedCount').textContent=selected;
  const visible=visibleUserCheckboxes();
  const selectAll=document.getElementById('selectAllUsers');
  selectAll.checked=visible.length>0 && visible.every(function(cb){return cb.checked;});
  selectAll.indeterminate=visible.some(function(cb){return cb.checked;}) && !selectAll.checked;
}
function toggleAllUsers(source){
  visibleUserCheckboxes().forEach(function(cb){cb.checked=source.checked;});
  updateSelectedCount();
}
function filterMatrix(){
  const query=(document.getElementById('matrixSearch').value||'').trim().toLowerCase();
  const group=document.getElementById('matrixGroupFilter').value;
  const className=document.getElementById('matrixClassFilter').value;
  const active=document.getElementById('matrixStatusFilter').value;
  document.querySelectorAll('.matrix-row').forEach(function(row){
    const groups=(row.dataset.groups||'').split('|');
    const classes=(row.dataset.classes||'').split('|');
    const visible=(!query || (row.dataset.search||'').toLowerCase().includes(query))
      && (!group || groups.includes(group))
      && (!className || classes.includes(className))
      && (active==='' || row.dataset.active===active);
    row.style.display=visible?'':'none';
  });
  updateSelectedCount();
}
function resetPermissionPopup(){
  document.querySelectorAll('#permissionPopupForm .feature-apply,#permissionPopupForm .feature-right')
    .forEach(function(cb){cb.checked=false;});
  document.getElementById('popupSelectedUsers').innerHTML='';
}
function setRightsForCode(code,level,apply){
  const order={none:0,view:1,edit:2,delete:3};
  document.querySelectorAll('.feature-right[data-code="'+CSS.escape(code)+'"]').forEach(function(cb){
    cb.checked=(order[cb.dataset.right]||0)<= (order[level]||0);
  });
  const applyBox=document.querySelector('.feature-apply[value="'+CSS.escape(code)+'"]');
  if(applyBox) applyBox.checked=!!apply;
}
function openPermissionModal(){
  const target=document.getElementById('permissionTarget').value;
  const selected=Array.from(document.querySelectorAll('.user-select:checked')).map(function(cb){return cb.value;});
  if(target==='selected' && selected.length===0){
    alert('Hãy tích chọn ít nhất một giáo viên, hoặc chọn một nhóm quyền.');
    return;
  }
  resetPermissionPopup();
  document.getElementById('popupPermissionTarget').value=target;
  if(target==='selected'){
    const holder=document.getElementById('popupSelectedUsers');
    selected.forEach(function(id){
      const input=document.createElement('input');
      input.type='hidden'; input.name='permission_users[]'; input.value=id;
      holder.appendChild(input);
    });
    document.getElementById('permissionTargetSummary').textContent=selected.length+' giáo viên đã chọn';
    if(selected.length===1){
      const access=USER_ACCESS[selected[0]]||{};
      Object.keys(access).forEach(function(code){setRightsForCode(code,access[code],false);});
    }
  }else{
    const option=document.querySelector('#permissionTarget option[value="'+CSS.escape(target)+'"]');
    document.getElementById('permissionTargetSummary').textContent=option?option.textContent:'Nhóm quyền';
    const access=GROUP_ACCESS[target]||{};
    Object.keys(access).forEach(function(code){setRightsForCode(code,access[code],true);});
  }
  bootstrap.Modal.getOrCreateInstance(document.getElementById('permissionModal')).show();
}
function syncFeatureRights(source){
  const code=source.dataset.code;
  const right=source.dataset.right;
  const get=function(r){return document.querySelector('.feature-right[data-code="'+CSS.escape(code)+'"][data-right="'+r+'"]');};
  const view=get('view'),edit=get('edit'),del=get('delete');
  if(source.checked){
    if(right==='edit'||right==='delete') view.checked=true;
    if(right==='delete') edit.checked=true;
  }else{
    if(right==='view'){edit.checked=false;del.checked=false;}
    if(right==='edit') del.checked=false;
  }
  const applyBox=document.querySelector('.feature-apply[value="'+CSS.escape(code)+'"]');
  if(applyBox) applyBox.checked=true;
}
function selectModuleRights(moduleKey,level){
  document.querySelectorAll('.feature-row[data-module="'+CSS.escape(moduleKey)+'"]').forEach(function(row){
    const apply=row.querySelector('.feature-apply');
    const code=apply.value;
    setRightsForCode(code,level,true);
  });
}
function clearModuleRights(moduleKey){
  document.querySelectorAll('.feature-row[data-module="'+CSS.escape(moduleKey)+'"]').forEach(function(row){
    row.querySelector('.feature-apply').checked=true;
    row.querySelectorAll('.feature-right').forEach(function(cb){cb.checked=false;});
  });
}
function openEditor(){
  resetForm();
  bootstrap.Collapse.getOrCreateInstance(document.getElementById('userEditor')).show();
  setTimeout(function(){document.getElementById('userEditor').scrollIntoView({behavior:'smooth',block:'start'});},150);
}
function applyPreset(){
  const p = PRESETS[document.getElementById('f_role').value];
  if (!p) return;
  if (p.modules) Object.keys(p.modules).forEach(function(mk){
    const el=document.getElementById('mod_'+mk); if(el) el.value=p.modules[mk];
  });
  document.querySelectorAll('.perm-cb').forEach(function(cb){
    cb.checked=(p.perms||[]).indexOf(cb.value)>=0;
  });
}
function resetForm(){
  document.getElementById('formTitle').textContent='Thêm / sửa thủ công';
  document.getElementById('f_id').value='';
  document.getElementById('f_username').value='';
  document.getElementById('f_name').value='';
  document.getElementById('f_teacher').value='';
  document.getElementById('f_password').value='';
  document.getElementById('f_active').checked=true;
  document.getElementById('f_role').value='gv';
  applyPreset();
  document.querySelectorAll('.group-cb').forEach(function(cb){cb.checked=false;});
  const gv=document.getElementById('g_gv'); if(gv) gv.checked=true;
  document.querySelectorAll('.override-select').forEach(function(el){el.value='inherit';});
  document.querySelectorAll('.class-cb').forEach(function(cb){cb.checked=false;});
  document.querySelectorAll('.homeroom-cb').forEach(function(cb){cb.checked=false;});
}
function editUser(u){
  document.getElementById('formTitle').textContent='Sửa: '+(u.username||'');
  document.getElementById('f_id').value=u.id||'';
  document.getElementById('f_username').value=u.username||'';
  document.getElementById('f_name').value=u.name||'';
  document.getElementById('f_teacher').value=u.teacher_name||u.name||'';
  document.getElementById('f_password').value='';
  document.getElementById('f_active').checked=!!u.active;
  document.getElementById('f_role').value=u.role||'custom';
  const mods=u.modules||{};
  Object.keys(mods).forEach(function(mk){const el=document.getElementById('mod_'+mk);if(el)el.value=mods[mk];});
  const perms=u.perms||[];
  document.querySelectorAll('.perm-cb').forEach(function(cb){cb.checked=perms.indexOf(cb.value)>=0;});
  const groups=u.groups||[];
  document.querySelectorAll('.group-cb').forEach(function(cb){cb.checked=groups.indexOf(cb.value)>=0;});
  const overrides=u.permission_overrides||{};
  document.querySelectorAll('.override-select').forEach(function(el){el.value=overrides[el.dataset.code]||'inherit';});
  const classes=u.classes||[];
  document.querySelectorAll('.class-cb').forEach(function(cb){cb.checked=classes.indexOf(cb.value)>=0;});
  const homeroom=u.homeroom_classes||classes;
  document.querySelectorAll('.homeroom-cb').forEach(function(cb){cb.checked=homeroom.indexOf(cb.value)>=0;});
  bootstrap.Collapse.getOrCreateInstance(document.getElementById('userEditor')).show();
  setTimeout(function(){document.getElementById('userEditor').scrollIntoView({behavior:'smooth',block:'start'});},150);
}
function openPasswordReset(id,name){
  document.getElementById('resetPasswordUserId').value=id;
  document.getElementById('resetPasswordUserName').textContent=name;
  document.getElementById('adminNewPassword').value='';
  document.getElementById('adminConfirmPassword').value='';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('passwordResetModal')).show();
}
function generateAdminPassword(){
  const chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#';
  const bytes=new Uint32Array(12);crypto.getRandomValues(bytes);
  const password=Array.from(bytes,n=>chars[n%chars.length]).join('');
  document.getElementById('adminNewPassword').value=password;
  document.getElementById('adminConfirmPassword').value=password;
}
resetForm();
document.getElementById('bulkPermissionForm').addEventListener('submit',function(event){
  if (!document.querySelector('.user-select:checked')) {
    event.preventDefault();
    alert('Hãy tích chọn ít nhất một giáo viên trong bảng.');
    return;
  }
  if (!confirm('Áp dụng thay đổi cho các giáo viên đã chọn?')) event.preventDefault();
});
document.getElementById('permissionPopupForm').addEventListener('submit',function(event){
  if (!document.querySelector('#permissionPopupForm .feature-apply:checked')) {
    event.preventDefault();
    alert('Hãy tích “Áp dụng” ở ít nhất một chức năng.');
    return;
  }
  if (!confirm('Lưu các quyền đã chọn?')) event.preventDefault();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

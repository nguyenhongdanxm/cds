<?php
/**
 * Quản lý tài khoản + phân quyền 3 tầng + Load từ CSDL/PCCM
 */
require_once 'includes/auth.php';
require_once 'includes/user_sync.php';
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
sort($allClasses, SORT_NATURAL);

$syncReport = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $users = get_users();

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
            if (in_array($level, ['view','edit'], true)) $group['access'][$code] = $level;
        }
        $permissionGroups[$groupKey] = $group;
        permission_groups_save($permissionGroups);
        flash('Đã lưu quyền cho nhóm ' . $group['label'] . '.');
        header('Location: users.php?view=groups&group=' . urlencode($groupKey)); exit;
    }

    if ($action === 'load_system') {
        $syncReport = sync_users_from_system();
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

        if (!isset($modCatalog[$moduleKey]) || !in_array($level, ['none','view','edit'], true) || !$selectedIds) {
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
        save_users($users);
        flash('Đã cập nhật quyền ' . ($modCatalog[$moduleKey]['label'] ?? $moduleKey) . ' cho ' . $changed . ' người.');
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
        save_users($users);
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
            if (in_array($level, ['none','view','edit'], true)) $overrides[$code] = $level;
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

        save_users($users);
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
        save_users($users);
        flash('Đã xóa tài khoản.', 'warning');
        header('Location: users.php'); exit;
    }
}

$users = get_users();
usort($users, fn($a, $b) => strcasecmp($a['username'] ?? '', $b['username'] ?? ''));

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
    return in_array($levels[0] ?? 'none', ['none','view','edit'], true) ? ($levels[0] ?? 'none') : 'none';
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
.level-select{font-weight:600;border-width:1px;text-align:center}
.level-none{background:#f1f3f5;color:#6c757d;border-color:#ced4da}
.level-view{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}
.level-edit{background:#1f6feb;color:#fff;border-color:#1f6feb}
.level-mixed{background:#f3e8ff;color:#7e22ce;border-color:#d8b4fe}
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
      <div class="text-muted small">Load từ CSDL · SĐT = tài khoản · Gán nhóm theo chức vụ / PCCM</div>
    </div>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Quản trị</a>
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
            Không quyền: ẩn chức năng · Xem: chỉ đọc · Sửa: được xem và thao tác.
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
                  <td><?= e($meta['label']) ?><div class="text-muted small"><?= e($code) ?></div></td>
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
          <li>Tự gán nhóm: BGH · QLNT · Văn phòng · Đoàn–Đội · Tổ CM · GVCN · GV</li>
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
              <label class="small text-muted">Module</label>
              <select class="form-select form-select-sm" name="module_key">
                <?php foreach ($modCatalog as $moduleKey => $module): ?>
                <option value="<?= e($moduleKey) ?>"><?= e($module['label'] ?? $moduleKey) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="small text-muted">Mức quyền</label>
              <select class="form-select form-select-sm" name="level">
                <option value="none">Không</option>
                <option value="view">Xem</option>
                <option value="edit">Sửa</option>
              </select>
            </div>
            <button class="btn btn-sm btn-primary" name="action" value="bulk_module_access" type="submit">
              <i class="bi bi-check2-square"></i> Áp dụng quyền
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
                    <span class="badge bg-light text-dark border badge-mod"><?= e($groupPresets[$g]['label'] ?? $g) ?></span>
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
                    <select class="form-select form-select-sm level-select level-<?= e($moduleLevel) ?>"
                            aria-label="<?= e(($module['label'] ?? $moduleKey) . ' của ' . ($u['name'] ?? '')) ?>"
                            onchange="setModuleAccess(this,'<?= e($u['id'] ?? '') ?>','<?= e($moduleKey) ?>')">
                      <?php if ($moduleLevel === 'mixed'): ?><option value="mixed" selected disabled>Tùy chỉnh</option><?php endif; ?>
                      <option value="none" <?= $moduleLevel === 'none' ? 'selected' : '' ?>>Không</option>
                      <option value="view" <?= $moduleLevel === 'view' ? 'selected' : '' ?>>Xem</option>
                      <option value="edit" <?= $moduleLevel === 'edit' ? 'selected' : '' ?>>Sửa</option>
                    </select>
                  <?php endif; ?>
                </td>
                <?php endforeach; ?>
                <td class="text-nowrap text-center">
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
        <span class="badge level-edit">Sửa</span> được thao tác.
        <span class="badge level-mixed">Tùy chỉnh</span> có nhiều mức quyền bên trong.
        Muốn phân quyền từng chức năng hoặc lớp chủ nhiệm, nhấn <i class="bi bi-sliders"></i>.
      </div>
    </div>
  </div>

  <form method="post" id="quickModuleForm" class="d-none">
    <input type="hidden" name="action" value="set_module_access">
    <input type="hidden" name="user_id" id="quickUserId">
    <input type="hidden" name="module_key" id="quickModuleKey">
    <input type="hidden" name="level" id="quickLevel">
  </form>

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
            <div class="perm-box mb-3">
              <?php foreach ($permissionGroups as $groupKey => $group): ?>
              <div class="form-check">
                <input class="form-check-input group-cb" type="checkbox" name="groups[]" value="<?= e($groupKey) ?>" id="g_<?= e($groupKey) ?>">
                <label class="form-check-label small" for="g_<?= e($groupKey) ?>"><?= e($group['label'] ?? $groupKey) ?></label>
              </div>
              <?php endforeach; ?>
            </div>

            <h6 class="text-primary"><i class="bi bi-2-circle"></i> Quyền cá nhân</h6>
            <div class="form-text mb-2">Theo nhóm hoặc ghi đè riêng: Không quyền / Xem / Sửa.</div>
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
                      <span class="badge bg-light text-dark border badge-mod"><?= e($groupPresets[$g]['label'] ?? $g) ?></span>
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
function paintLevelSelect(el){
  el.classList.remove('level-none','level-view','level-edit');
  el.classList.add('level-'+el.value);
}
function setModuleAccess(el,userId,moduleKey){
  paintLevelSelect(el);
  document.getElementById('quickUserId').value=userId;
  document.getElementById('quickModuleKey').value=moduleKey;
  document.getElementById('quickLevel').value=el.value;
  document.getElementById('quickModuleForm').submit();
}
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
resetForm();
document.querySelectorAll('.level-select').forEach(paintLevelSelect);
document.getElementById('bulkPermissionForm').addEventListener('submit',function(event){
  if (!document.querySelector('.user-select:checked')) {
    event.preventDefault();
    alert('Hãy tích chọn ít nhất một giáo viên trong bảng.');
    return;
  }
  if (!confirm('Áp dụng thay đổi cho các giáo viên đã chọn?')) event.preventDefault();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

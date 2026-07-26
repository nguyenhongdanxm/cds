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

  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card">
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

            <h6 class="text-primary"><i class="bi bi-1-circle"></i> Module</h6>
            <div class="table-responsive mb-3">
              <table class="table table-sm mb-0">
                <tbody>
                <?php foreach ($modCatalog as $mk => $mm): ?>
                  <tr>
                    <td class="small"><i class="bi <?= e($mm['icon']) ?>"></i> <?= e($mm['label']) ?></td>
                    <td>
                      <select name="mod_<?= e($mk) ?>" id="mod_<?= e($mk) ?>" class="form-select form-select-sm">
                        <?php foreach ($levels as $lv => $lb): ?>
                        <option value="<?= e($lv) ?>"><?= e($lb) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <h6 class="text-primary"><i class="bi bi-2-circle"></i> Chức năng</h6>
            <div class="perm-box mb-3">
              <?php foreach ($featsByMod as $mod => $feats): ?>
                <div class="small fw-semibold text-secondary mb-1 mt-2"><?= e($modCatalog[$mod]['label'] ?? $mod) ?></div>
                <?php foreach ($feats as $code => $meta): ?>
                <div class="form-check">
                  <input class="form-check-input perm-cb" type="checkbox" name="perms[]" value="<?= e($code) ?>" id="p_<?= e($code) ?>">
                  <label class="form-check-label small" for="p_<?= e($code) ?>"><?= e($meta['label']) ?></label>
                </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>

            <h6 class="text-primary"><i class="bi bi-3-circle"></i> Phạm vi lớp</h6>
            <div class="form-text mb-1">Trống = mọi lớp</div>
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

    <div class="col-lg-7">
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
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u, JSON_UNESCAPED_UNICODE) ?>)'><i class="bi bi-pencil"></i></button>
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
</div>

<script>
const PRESETS = <?= json_encode($presets, JSON_UNESCAPED_UNICODE) ?>;
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
  document.querySelectorAll('.class-cb').forEach(function(cb){cb.checked=false;});
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
  const classes=u.classes||[];
  document.querySelectorAll('.class-cb').forEach(function(cb){cb.checked=classes.indexOf(cb.value)>=0;});
  window.scrollTo({top:0,behavior:'smooth'});
}
applyPreset();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

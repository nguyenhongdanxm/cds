<?php
/**
 * Thanh menu trên cùng – dùng chung CSDL, Nội trú, Quản trị…
 * Biến: $nav_title, $nav_icon, $nav_color, $nav_module
 */
if (!function_exists('get_ecosystem_modules')) {
    require_once __DIR__ . '/modules.php';
}
$nav_title = $nav_title ?? 'CDS';
$nav_icon = $nav_icon ?? 'bi-hexagon-fill';
$nav_color = $nav_color ?? '#1F4E79';
$nav_module = $nav_module ?? '';
$nav_user = function_exists('current_user') ? current_user() : null;
$nav_modules = get_ecosystem_modules();

$nav_extra = [
    ['id' => 'home', 'title' => 'Hệ sinh thái', 'icon' => 'bi-house-door', 'url' => BASE_URL, 'external' => false, 'status' => 'live'],
    ['id' => 'admin', 'title' => 'Quản trị CDS', 'icon' => 'bi-speedometer2', 'url' => BASE_URL . 'admin.php', 'external' => false, 'status' => 'live'],
];
?>
<style>
.cds-nav{background:<?= htmlspecialchars($nav_color, ENT_QUOTES) ?>!important}
.cds-nav .dropdown-menu{min-width:260px;max-height:70vh;overflow-y:auto}
.cds-nav .dropdown-item.active,.cds-nav .dropdown-item:active{background:#e8f0fe;color:#1a56a8}
.cds-nav .dropdown-item .bi{width:1.25rem;display:inline-block}
.cds-nav .nav-mod-soon{opacity:.55}
.cds-nav .cds-legacy-module-menu{display:none!important}
body > nav.navbar-dark:not(.cds-nav){display:none!important}
</style>
<nav class="navbar navbar-expand-lg navbar-dark cds-nav mb-4">
  <div class="container-fluid px-3 px-lg-4">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>">
      <i class="bi <?= e($nav_icon) ?>"></i>
      <span><?= e($nav_title) ?></span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#cdsTopNav" aria-controls="cdsTopNav" aria-expanded="false" aria-label="Menu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="cdsTopNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
        <li class="nav-item dropdown cds-legacy-module-menu">
          <a class="nav-link dropdown-toggle text-white fw-semibold" href="#" id="cdsModulesDrop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-grid-3x3-gap"></i> Chuyển module
          </a>
          <ul class="dropdown-menu shadow" aria-labelledby="cdsModulesDrop">
            <?php foreach ($nav_extra as $m):
              $active = ($nav_module === ($m['id'] ?? ''));
            ?>
              <li>
                <a class="dropdown-item <?= $active ? 'active' : '' ?>" href="<?= e($m['url']) ?>">
                  <i class="bi <?= e($m['icon']) ?>"></i> <?= e($m['title']) ?>
                </a>
              </li>
            <?php endforeach; ?>
            <li><hr class="dropdown-divider"></li>
            <?php foreach ($nav_modules as $m):
              $active = ($nav_module === ($m['id'] ?? ''));
              $soon = ($m['status'] ?? '') === 'soon';
              $url = $soon ? '#' : ($m['url'] ?? '#');
              $ext = !empty($m['external']);
            ?>
              <li>
                <?php if ($soon): ?>
                  <span class="dropdown-item nav-mod-soon disabled">
                    <i class="bi <?= e($m['icon']) ?>" style="color:<?= e($m['color'] ?? '#666') ?>"></i>
                    <?= e($m['title']) ?>
                    <span class="badge bg-secondary ms-1" style="font-size:.65rem">Sắp ra</span>
                  </span>
                <?php else: ?>
                  <a class="dropdown-item <?= $active ? 'active' : '' ?>" href="<?= e($url) ?>" <?= $ext ? 'target="_blank" rel="noopener"' : '' ?>>
                    <i class="bi <?= e($m['icon']) ?>" style="color:<?= e($m['color'] ?? '#666') ?>"></i>
                    <?= e($m['title']) ?>
                    <?php if ($ext): ?><i class="bi bi-box-arrow-up-right small ms-1 opacity-50"></i><?php endif; ?>
                  </a>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        </li>
      </ul>
      <div class="d-flex flex-wrap align-items-center gap-2">
        <?php if ($nav_user): ?>
          <span class="text-white-50 small d-none d-md-inline">
            <i class="bi bi-person-circle"></i> <?= e($nav_user['name'] ?? $nav_user['username'] ?? '') ?>
          </span>
          <?php if (($nav_user['role'] ?? '') === 'admin'): ?><a href="<?= e(BASE_URL . 'instance_settings.php') ?>" class="btn btn-outline-light btn-sm"><i class="bi bi-sliders"></i> Cấu hình trường</a><?php endif; ?>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>" class="btn btn-outline-light btn-sm">Trang chủ</a>
        <a href="<?= BASE_URL ?>logout.php" class="btn btn-warning btn-sm text-dark">Thoát</a>
      </div>
    </div>
  </div>
</nav>
<?php require_once __DIR__.'/module_switcher.php'; ?>
<?php if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'users.php'): ?>
<script src="<?= e(BASE_URL . 'assets/users_permission_tree.js?v=20260828-2') ?>" defer></script>
<?php endif; ?>

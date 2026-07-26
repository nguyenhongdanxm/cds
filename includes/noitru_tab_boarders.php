<?php
/** Tab Danh sách nội trú: Học sinh | Lớp | Phòng | Mâm ăn */
if (!isset($nt_list_base)) {
    $nt_list_base = BASE_URL . 'noitru_list.php';
}
function nt_list_url($params = []) {
    global $nt_list_base;
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return $nt_list_base . ($params ? ('?' . http_build_query($params)) : '');
}

$view = $_GET['view'] ?? 'students';
if (!in_array($view, ['students', 'classes', 'rooms', 'meals'], true)) $view = 'students';

$q = trim($_GET['q'] ?? '');
$f_class = trim($_GET['class'] ?? '');
$f_room = trim($_GET['room'] ?? '');
$f_meal = trim($_GET['meal'] ?? '');

$byClass = [];
$byRoom = [];
$byMeal = [];
foreach ($boarders as $s) {
    $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
    $byClass[$cn][] = $s;
    $rm = trim($s['room_ktx'] ?? '') !== '' ? $s['room_ktx'] : '(Chưa phòng)';
    $byRoom[$rm][] = $s;
    $mg = trim($s['meal_group'] ?? '') !== '' ? $s['meal_group'] : '(Chưa mâm/nhóm ăn)';
    $byMeal[$mg][] = $s;
}
ksort($byClass, SORT_NATURAL);
ksort($byRoom, SORT_NATURAL);
ksort($byMeal, SORT_NATURAL);

function nt_boarders_table(array $list) {
    if (!$list) {
        echo '<div class="text-muted text-center py-4">Không có học sinh.</div>';
        return;
    }
    echo '<div class="table-responsive"><table class="table table-sm table-hover mb-0 align-middle">';
    echo '<thead><tr><th>STT</th><th>Họ tên</th><th>Lớp</th><th>Phòng</th><th>Mâm/nhóm ăn</th><th>PH / SĐT</th><th>Ghi chú</th></tr></thead><tbody>';
    foreach ($list as $i => $s) {
        echo '<tr>';
        echo '<td>' . ($i + 1) . '</td>';
        echo '<td><strong>' . e($s['name']) . '</strong>';
        if (!empty($s['code'])) echo '<div class="small text-muted">' . e($s['code']) . '</div>';
        echo '</td>';
        echo '<td>' . e($s['class_name'] ?: '—') . '</td>';
        echo '<td>' . ($s['room_ktx'] !== '' ? '<span class="badge badge-room">' . e($s['room_ktx']) . '</span>' : '—') . '</td>';
        echo '<td>' . ($s['meal_group'] !== '' ? '<span class="badge badge-meal">' . e($s['meal_group']) . '</span>' : '—') . '</td>';
        echo '<td class="small">' . e($s['parent_name'] ?? '');
        if (!empty($s['parent_phone'])) echo ' · ' . e($s['parent_phone']);
        echo '</td>';
        echo '<td class="small text-muted">' . e($s['note'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

$subTabs = [
    'students' => ['Học sinh', 'bi-person-lines-fill'],
    'classes'  => ['Lớp', 'bi-building'],
    'rooms'    => ['Phòng', 'bi-door-closed'],
    'meals'    => ['Mâm ăn', 'bi-egg-fried'],
];
?>

<ul class="nav nav-tabs mb-3 flex-wrap">
  <?php foreach ($subTabs as $vk => $vi): ?>
  <li class="nav-item">
    <a class="nav-link <?= $view === $vk ? 'active' : '' ?>" href="<?= e(nt_list_url(['view' => $vk])) ?>">
      <i class="bi <?= e($vi[1]) ?>"></i> <?= e($vi[0]) ?>
      <?php if ($vk === 'students'): ?><span class="badge bg-secondary ms-1"><?= count($boarders) ?></span><?php endif; ?>
      <?php if ($vk === 'classes'): ?><span class="badge bg-secondary ms-1"><?= count($byClass) ?></span><?php endif; ?>
      <?php if ($vk === 'rooms'): ?><span class="badge bg-secondary ms-1"><?= count($byRoom) ?></span><?php endif; ?>
      <?php if ($vk === 'meals'): ?><span class="badge bg-secondary ms-1"><?= count($byMeal) ?></span><?php endif; ?>
    </a>
  </li>
  <?php endforeach; ?>
</ul>

<?php if ($view === 'students'): ?>
  <?php
    $list = $boarders;
    if ($q !== '') {
        $qq = mb_strtolower($q, 'UTF-8');
        $list = array_values(array_filter($list, function ($s) use ($qq) {
            $blob = mb_strtolower(implode(' ', [
                $s['name'] ?? '', $s['code'] ?? '', $s['class_name'] ?? '',
                $s['room_ktx'] ?? '', $s['meal_group'] ?? '',
                $s['parent_name'] ?? '', $s['parent_phone'] ?? '',
            ]), 'UTF-8');
            return mb_strpos($blob, $qq) !== false;
        }));
    }
    if ($f_class !== '') {
        $list = array_values(array_filter($list, function ($s) use ($f_class) {
            $cn = $s['class_name'] !== '' ? $s['class_name'] : '(Chưa lớp)';
            return $cn === $f_class;
        }));
    }
    if ($f_room !== '') {
        $list = array_values(array_filter($list, function ($s) use ($f_room) {
            $rm = trim($s['room_ktx'] ?? '') !== '' ? $s['room_ktx'] : '(Chưa phòng)';
            return $rm === $f_room;
        }));
    }
    if ($f_meal !== '') {
        $list = array_values(array_filter($list, function ($s) use ($f_meal) {
            $mg = trim($s['meal_group'] ?? '') !== '' ? $s['meal_group'] : '(Chưa mâm/nhóm ăn)';
            return $mg === $f_meal;
        }));
    }
    $classKeys = array_keys($byClass);
  ?>

  <form method="get" class="card card-soft mb-3" action="<?= e($nt_list_base) ?>"><div class="card-body py-2">
    <input type="hidden" name="view" value="students">
    <div class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small mb-0">Tìm kiếm</label>
        <input type="search" name="q" class="form-control form-control-sm" value="<?= e($q) ?>" placeholder="Tên, mã, lớp, phòng, mâm, PH…">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-0">Lớp</label>
        <select name="class" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          <?php foreach ($classKeys as $ck): ?>
          <option value="<?= e($ck) ?>" <?= $f_class === $ck ? 'selected' : '' ?>><?= e($ck) ?> (<?= count($byClass[$ck]) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-0">Phòng</label>
        <select name="room" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          <?php foreach ($byRoom as $rk => $arr): ?>
          <option value="<?= e($rk) ?>" <?= $f_room === $rk ? 'selected' : '' ?>><?= e($rk) ?> (<?= count($arr) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-0">Mâm / nhóm ăn</label>
        <select name="meal" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          <?php foreach ($byMeal as $mk => $arr): ?>
          <option value="<?= e($mk) ?>" <?= $f_meal === $mk ? 'selected' : '' ?>><?= e($mk) ?> (<?= count($arr) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button class="btn btn-nt btn-sm flex-grow-1" type="submit">Lọc</button>
        <a href="<?= e(nt_list_url(['view' => 'students'])) ?>" class="btn btn-outline-secondary btn-sm">Xóa</a>
      </div>
    </div>
  </div></form>

  <?php if ($classKeys): ?>
  <div class="mb-2 d-flex flex-wrap gap-1">
    <a href="<?= e(nt_list_url(['view' => 'students'])) ?>" class="btn btn-sm <?= $f_class === '' && $q === '' && $f_room === '' && $f_meal === '' ? 'btn-nt' : 'btn-outline-secondary' ?>">Tất cả</a>
    <?php foreach ($classKeys as $ck): ?>
    <a href="<?= e(nt_list_url(['view' => 'students', 'class' => $ck])) ?>"
       class="btn btn-sm <?= $f_class === $ck ? 'btn-nt' : 'btn-outline-secondary' ?>"><?= e($ck) ?> <span class="opacity-75"><?= count($byClass[$ck]) ?></span></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="card card-soft">
    <div class="card-body py-2 border-bottom small text-muted">
      Hiển thị <strong><?= count($list) ?></strong> / <?= count($boarders) ?> HS nội trú
      <?php if ($f_class): ?> · Lớp <strong><?= e($f_class) ?></strong><?php endif; ?>
      <?php if ($f_room): ?> · Phòng <strong><?= e($f_room) ?></strong><?php endif; ?>
      <?php if ($f_meal): ?> · Mâm <strong><?= e($f_meal) ?></strong><?php endif; ?>
    </div>
    <?php nt_boarders_table($list); ?>
  </div>

<?php elseif ($view === 'classes'): ?>
  <?php if ($f_class !== '' && isset($byClass[$f_class])): ?>
    <div class="mb-2">
      <a href="<?= e(nt_list_url(['view' => 'classes'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Tất cả lớp</a>
      <span class="ms-2 fw-semibold">Lớp <?= e($f_class) ?></span>
      <span class="badge bg-secondary"><?= count($byClass[$f_class]) ?> HS</span>
    </div>
    <div class="card card-soft"><?php nt_boarders_table($byClass[$f_class]); ?></div>
  <?php else: ?>
    <div class="row g-2">
      <?php foreach ($byClass as $ck => $arr): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= e(nt_list_url(['view' => 'classes', 'class' => $ck])) ?>" class="text-decoration-none">
          <div class="card card-soft h-100">
            <div class="card-body text-center py-3">
              <div class="fw-bold" style="color:var(--primary);font-size:1.15rem"><?= e($ck) ?></div>
              <div class="text-muted small"><?= count($arr) ?> học sinh</div>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (!$byClass): ?><div class="col-12"><div class="text-muted text-center py-4">Chưa có HS nội trú.</div></div><?php endif; ?>
    </div>
  <?php endif; ?>

<?php elseif ($view === 'rooms'): ?>
  <?php if ($f_room !== '' && isset($byRoom[$f_room])): ?>
    <div class="mb-2">
      <a href="<?= e(nt_list_url(['view' => 'rooms'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Tất cả phòng</a>
      <span class="ms-2 fw-semibold">Phòng <?= e($f_room) ?></span>
      <span class="badge badge-room"><?= count($byRoom[$f_room]) ?> HS</span>
    </div>
    <div class="card card-soft"><?php nt_boarders_table($byRoom[$f_room]); ?></div>
  <?php else: ?>
    <div class="row g-2">
      <?php foreach ($byRoom as $rk => $arr): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= e(nt_list_url(['view' => 'rooms', 'room' => $rk])) ?>" class="text-decoration-none">
          <div class="card card-soft h-100">
            <div class="card-body text-center py-3">
              <i class="bi bi-door-closed fs-4" style="color:var(--primary)"></i>
              <div class="fw-bold mt-1"><?= e($rk) ?></div>
              <div class="text-muted small"><?= count($arr) ?> học sinh</div>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (!$byRoom): ?><div class="col-12"><div class="text-muted text-center py-4">Chưa có dữ liệu phòng.</div></div><?php endif; ?>
    </div>
  <?php endif; ?>

<?php elseif ($view === 'meals'): ?>
  <?php if ($f_meal !== '' && isset($byMeal[$f_meal])): ?>
    <div class="mb-2">
      <a href="<?= e(nt_list_url(['view' => 'meals'])) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> Tất cả mâm</a>
      <span class="ms-2 fw-semibold"><?= e($f_meal) ?></span>
      <span class="badge badge-meal"><?= count($byMeal[$f_meal]) ?> HS</span>
    </div>
    <div class="card card-soft"><?php nt_boarders_table($byMeal[$f_meal]); ?></div>
  <?php else: ?>
    <div class="row g-2">
      <?php foreach ($byMeal as $mk => $arr): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= e(nt_list_url(['view' => 'meals', 'meal' => $mk])) ?>" class="text-decoration-none">
          <div class="card card-soft h-100">
            <div class="card-body text-center py-3">
              <i class="bi bi-egg-fried fs-4 text-success"></i>
              <div class="fw-bold mt-1"><?= e($mk) ?></div>
              <div class="text-muted small"><?= count($arr) ?> học sinh</div>
            </div>
          </div>
        </a>
      </div>
      <?php endforeach; ?>
      <?php if (!$byMeal): ?><div class="col-12"><div class="text-muted text-center py-4">Chưa có mâm / nhóm ăn.</div></div><?php endif; ?>
    </div>
  <?php endif; ?>

<?php endif; ?>

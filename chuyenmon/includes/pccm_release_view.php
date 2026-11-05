<?php
$releaseVersion = (string)get_active_version_id();
$releaseState = pccm_release_state(DATA_PATH);
$releaseLocked = !empty($releaseState['locked'][$releaseVersion]);
$releasePublished = $releaseState['published'] ?? null;
?>
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
<span class="badge <?= $releaseLocked?'bg-secondary':'bg-light text-dark' ?>"><?= $releaseLocked?'Đã khóa':'Bản làm việc' ?></span>
<span class="small text-muted">Nguồn TKB · <?= $releasePublished ? e($releasePublished['name'].' · '.date('H:i d/m/Y',strtotime($releasePublished['published_at']))) : 'Chưa công bố, đang dùng bản làm việc' ?></span>
<?php if($releaseVersion!==''&&cds_can_feature('cm.pccm','edit')): ?>
<form method="post" action="<?=e(BASE_URL)?>them.php" onsubmit="return confirm(<?=e(json_encode($releaseLocked?'Mở khóa bản làm việc? TKB vẫn dùng bản đã công bố.':'Khóa phiên bản này và dùng bản chụp hiện tại làm nguồn xếp TKB?'))?>)">
<input type="hidden" name="pccm_token" value="<?=e($_SESSION['pccm_release_csrf'])?>">
<input type="hidden" name="pccm_version" value="<?=e($releaseVersion)?>">
<button name="pccm_release_action" value="<?=$releaseLocked?'unlock':'publish'?>" class="btn btn-sm btn-outline-primary"><?=$releaseLocked?'Mở khóa':'Khóa + công bố'?></button>
</form>
<?php endif; ?>
</div>

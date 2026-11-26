<?php
require_once __DIR__.'/weekly_work_store.php';
require_once __DIR__.'/weekly_work_view.php';
$workWeek=ww_week(is_string($_GET['schedule_week']??null)?$_GET['schedule_week']:'');
?>
<section class="panel feed-panel weekly-work-panel" aria-labelledby="weeklyWorkTitle">
  <div class="panel-head"><div><span class="section-kicker" id="weeklyWorkTitle">Lịch công tác tuần</span><p class="ww-range"><?=e($workWeek->format('d/m'))?> – <?=e($workWeek->modify('+6 days')->format('d/m/Y'))?></p></div><a class="ww-button ww-primary" href="<?=e(BASE_URL.'admin.php?view=weekly_schedule&week='.$workWeek->format('Y-m-d'))?>"><?=ww_allowed(current_user())?'Nhập lịch':'Xem đầy đủ'?></a></div>
  <?php ww_navigation($workWeek);ww_render_week($workWeek,ww_all());?>
</section>

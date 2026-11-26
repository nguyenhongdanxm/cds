<?php
function ww_render_week(DateTimeImmutable $week,array $items,bool $manage=false,string $onlyDate=''): void {
    $rows=ww_rows($items,$week);$days=['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ nhật'];
    $today=(new DateTimeImmutable('today',ww_timezone()))->format('Y-m-d');
    ?>
    <div class="ww-table-wrap"><table class="ww-table"><caption class="ww-sr-only">Lịch công tác từ <?=e($week->format('d/m/Y'))?> đến <?=e($week->modify('+6 days')->format('d/m/Y'))?></caption>
    <thead><tr><th scope="col">Ngày / buổi</th><th scope="col">Nội dung công tác</th><th scope="col">Thực hiện / địa điểm</th><?php if($manage):?><th scope="col">Thao tác</th><?php endif;?></tr></thead><tbody>
    <?php for($day=0;$day<7;$day++):$date=$week->modify('+'.$day.' days');$iso=$date->format('Y-m-d');if($onlyDate!==''&&$iso!==$onlyDate)continue;$daily=array_values(array_filter($rows,fn($r)=>($r['date']??'')===$iso));
      if(!$daily):?><tr class="ww-empty-day<?=$iso===$today?' ww-today':''?>"><th scope="row"><?=e($days[$day])?><small><?=e($date->format('d/m'))?></small><?php if($iso===$today):?><span class="ww-today-badge" aria-current="date">Hôm nay</span><?php endif;?></th><td colspan="<?=$manage?3:2?>">Chưa có lịch công tác</td></tr><?php
      else:foreach($daily as $index=>$row):?><tr class="<?=$iso===$today?'ww-today ':''?><?=$index===0?'ww-day-start':''?>">
      <th scope="row"><?php if($index===0):?><strong><?=e($days[$day])?></strong><small><?=e($date->format('d/m'))?></small><?php if($iso===$today):?><span class="ww-today-badge" aria-current="date">Hôm nay</span><?php endif;?><?php endif;?><span class="ww-session"><?=e(ww_sessions()[$row['session']]??'')?></span><small><?=e(($row['time']??'').(!empty($row['end_time'])?' – '.$row['end_time']:''))?></small></th>
      <td class="ww-content"><?=nl2br(e($row['content']??''))?><?php if(!empty($row['note'])):?><small class="ww-note">Ghi chú: <?=nl2br(e($row['note']))?></small><?php endif;?></td>
      <td><span><?=nl2br(e($row['people']??''))?></span><?php if(!empty($row['place'])):?><small class="ww-place">Địa điểm: <?=e($row['place'])?></small><?php endif;?></td>
      <?php if($manage):?><td class="ww-actions"><?php if(ww_allowed(current_user(),'edit')):?><a class="ww-button" href="<?=e(BASE_URL.'admin.php?view=weekly_schedule&week='.$week->format('Y-m-d').'&edit='.rawurlencode($row['id']))?>">Sửa</a><?php endif;?><?php if(ww_allowed(current_user(),'delete')):?><form method="post" action="<?=e(BASE_URL.'admin.php?view=weekly_schedule&week='.$week->format('Y-m-d'))?>"><input type="hidden" name="work_action" value="delete"><input type="hidden" name="csrf" value="<?=e(ww_csrf())?>"><input type="hidden" name="id" value="<?=e($row['id'])?>"><input type="hidden" name="version" value="<?=e($row['version'])?>"><button class="ww-button ww-danger" type="submit" onclick="return confirm('Xóa công việc này khỏi lịch?')">Xóa</button></form><?php endif;?></td><?php endif;?></tr><?php endforeach;endif;endfor;?>
    </tbody></table></div><?php
}
function ww_navigation(DateTimeImmutable $week,bool $manage=false): void {
    $base=BASE_URL.'admin.php?'.($manage?'view=weekly_schedule&week=':'schedule_week=');
    ?><nav class="ww-navigation" aria-label="Chọn tuần công tác"><a class="ww-button" href="<?=e($base.$week->modify('-7 days')->format('Y-m-d'))?>">‹ Tuần trước</a><a class="ww-button" href="<?=e($base.ww_week()->format('Y-m-d'))?>">Tuần này</a><a class="ww-button" href="<?=e($base.$week->modify('+7 days')->format('Y-m-d'))?>">Tuần sau ›</a><form method="get" action="<?=e(BASE_URL.'admin.php')?>"><?php if($manage):?><input type="hidden" name="view" value="weekly_schedule"><?php endif;?><label>Tuần có ngày <input aria-label="Tuần có ngày" type="date" name="<?=$manage?'week':'schedule_week'?>" value="<?=e($week->format('Y-m-d'))?>" required></label><button class="ww-button" type="submit">Xem</button></form></nav><?php
}

/** Compact homepage preview; full text remains on the day/week detail page. */
function ww_render_summary(DateTimeImmutable $week,array $items): void {
    $rows=ww_rows($items,$week);$days=['Thứ Hai','Thứ Ba','Thứ Tư','Thứ Năm','Thứ Sáu','Thứ Bảy','Chủ nhật'];
    $today=(new DateTimeImmutable('today',ww_timezone()))->format('Y-m-d');
    $base=BASE_URL.'admin.php?view=weekly_schedule&week='.$week->format('Y-m-d');
    ?><div class="ww-summary-scroll" tabindex="0" role="region" aria-label="Nội dung lịch tuần, cuộn để xem các ngày"><div class="ww-summary-list"><?php
    for($day=0;$day<7;$day++):$date=$week->modify('+'.$day.' days');$iso=$date->format('Y-m-d');$daily=array_values(array_filter($rows,fn($r)=>($r['date']??'')===$iso));?>
    <article class="ww-summary-day<?=$iso===$today?' ww-today':''?>"><div class="ww-summary-date"><strong><?=e($days[$day])?></strong><small><?=e($date->format('d/m'))?></small><?php if($iso===$today):?><span class="ww-today-badge" aria-current="date">Hôm nay</span><?php endif;?></div><div class="ww-summary-copy">
    <?php if(!$daily):?><span class="ww-summary-empty">Chưa có lịch công tác</span><?php else:foreach(array_slice($daily,0,2) as $row):?><p><span class="ww-summary-time"><?=e(ww_sessions()[$row['session']]??'')?><?=!empty($row['time'])?' · '.e($row['time']):''?></span><span class="ww-summary-title"><?=e($row['content']??'')?></span></p><?php endforeach;if(count($daily)>2):?><small class="ww-summary-more">+ <?=count($daily)-2?> công việc khác</small><?php endif;endif;?></div><a class="ww-day-detail" href="<?=e($base.'&date='.$iso)?>" aria-label="Xem chi tiết ngày <?=e($date->format('d/m/Y'))?>">Xem chi tiết ›</a></article>
    <?php endfor;?></div></div><div class="ww-summary-footer"><a class="ww-button" href="<?=e($base)?>">Xem chi tiết cả tuần →</a></div><?php
}

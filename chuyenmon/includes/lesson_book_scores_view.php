<?php
// This report is evaluated only after Xem in its own statistics tab.
if(empty($statsLoad)||($statsSection??'')!=='scores')return;
$scorePoints=lb_rating_points();$classWeekScores=lb_class_week_averages($statRows);
?>
<section class="lb-card"><h2 class="h5">Điểm quy đổi &amp; trung bình lớp theo tuần</h2><div class="d-flex flex-wrap gap-3"><?php foreach($scorePoints as$label=>$points):?><span><?=e($label)?>: <strong><?=e($points)?></strong> điểm</span><?php endforeach;?></div><p class="lb-note mt-2 mb-0">Điểm và xếp hạng tính theo bảng quy đổi đang cấu hình, giữ nguyên cách tính hiện có.</p></section>
<section class="lb-card"><h2 class="h5">Điểm trung bình lớp theo tuần</h2><div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Tuần</th><th>Lớp</th><th>Tiết có xếp loại</th><th>Tổng điểm</th><th>Điểm trung bình</th></tr></thead><tbody>
<?php foreach($classWeekScores as$score):?><tr><td><?=e($score['week_label'])?></td><td><?=e($score['class'])?></td><td><?=(int)$score['count']?></td><td><?=e($score['total'])?></td><td><?=number_format($score['average'],2,',','.')?></td></tr><?php endforeach;if(!$classWeekScores):?><tr><td colspan="5">Chưa có tiết được xếp loại trong phạm vi chọn.</td></tr><?php endif;?></tbody></table></div></section>
<?php foreach(['class'=>'Điểm theo lớp','actual_teacher'=>'Điểm theo giáo viên']as$field=>$heading):$scoreRows=lb_score_ranking($statRows,$field);?>
<section class="lb-card"><h2 class="h5"><?=e($heading)?></h2><div class="table-responsive"><table class="table table-bordered table-sm"><thead><tr><th>Hạng</th><th>Đối tượng</th><th>Tiết có xếp loại</th><th>Tổng điểm</th><th>Trung bình</th></tr></thead><tbody>
<?php foreach($scoreRows as$score):?><tr><td><?=(int)$score['rank']?></td><td><?=e($score['name'])?></td><td><?=(int)$score['count']?></td><td><?=e($score['total'])?></td><td><?=number_format($score['average'],2,',','.')?></td></tr><?php endforeach;if(!$scoreRows):?><tr><td colspan="5">Chưa có tiết được xếp loại trong phạm vi chọn.</td></tr><?php endif;?></tbody></table></div></section>
<?php endforeach;?>

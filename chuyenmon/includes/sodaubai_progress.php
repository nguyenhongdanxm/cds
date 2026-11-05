<?php
require_once __DIR__.'/lesson_book_statistics_access.php';
$statsRangeInput=(string)($_GET['stats_range']??'week');
$statsRange=in_array($statsRangeInput,['total','week','custom'],true)?$statsRangeInput:'week';
$statsWeekKey=(string)($_GET['stats_week']??$weekKey);$statsWeek=lb_week($statsWeekKey)?:$week;
$allWeeks=lb_weeks();$firstWeek=$allWeeks[0]??$week;$lastWeek=$allWeeks?end($allWeeks):$week;
if($statsRange==='total'){$statsFrom=(string)($firstWeek['start']??$week['start']);$statsTo=(string)($lastWeek['end']??$week['end']);}
elseif($statsRange==='week'){$statsFrom=(string)$statsWeek['start'];$statsTo=(string)$statsWeek['end'];}
else{$statsFrom=(string)($_GET['stats_from']??$week['start']);$statsTo=(string)($_GET['stats_to']??$week['end']);}
if($statsTo<$statsFrom){[$statsFrom,$statsTo]=[$statsTo,$statsFrom];}
$statsTeacher=trim((string)($_GET['stats_teacher']??''));$statsSubject=trim((string)($_GET['stats_subject']??''));$statsClass=trim((string)($_GET['stats_class']??''));
$statsCompletionInput=(string)($_GET['stats_completion']??'all');
$statsCompletion=in_array($statsCompletionInput,['all','completed','incomplete','saved_unsigned','not_saved'],true)?$statsCompletionInput:'all';
$allAccessible=$statsLoad?lb_stat_rows($statsFrom,$statsTo,'','','','all',$statsSection!=='indicators'):[];$teachers=[];$subjects=[];$statClasses=[];
foreach($allAccessible as$r){$teacherName=trim((string)($r['actual_teacher']??$r['scheduled_teacher']??''));if($teacherName!=='')$teachers[$teacherName]=true;if(trim((string)($r['subject']??''))!=='')$subjects[(string)$r['subject']]=true;if(trim((string)($r['class']??''))!=='')$statClasses[(string)$r['class']]=true;}
foreach([['value'=>$statsTeacher,'kind'=>'teacher'],['value'=>$statsSubject,'kind'=>'subject'],['value'=>$statsClass,'kind'=>'class']]as$selected){if($selected['value']==='')continue;if($selected['kind']==='teacher')$teachers[$selected['value']]=true;elseif($selected['kind']==='subject')$subjects[$selected['value']]=true;else$statClasses[$selected['value']]=true;}
$teachers=array_keys($teachers);$subjects=array_keys($subjects);$statClasses=array_keys($statClasses);sort($teachers,SORT_NATURAL);sort($subjects,SORT_NATURAL);sort($statClasses,SORT_NATURAL);
$statRows=lb_stat_filter_rows($allAccessible,$statsTeacher,$statsSubject,$statsClass,$statsCompletion);
$exportQuery=array_filter(['stats_from'=>$statsFrom,'stats_to'=>$statsTo,'stats_teacher'=>$statsTeacher,'stats_subject'=>$statsSubject,'stats_class'=>$statsClass,'stats_completion'=>$statsCompletion,'stats_range'=>$statsRange,'stats_week'=>$statsWeekKey],'strlen');
if($statsLoad&&$statsSection==='indicators'){
$tot=lb_stat_totals($statRows);
require_once __DIR__.'/lesson_book_statistics_detail.php';
$statMetric=lb_stats_metric((string)($_GET['stats_metric']??''));
$statPanel=lb_stats_panel((string)($_GET['stats_group']??''));
$detailRows=$statMetric!==''?lb_stats_metric_rows($statRows,$statMetric):[];
$groupRows=$statMetric!==''?lb_stat_group($detailRows,['teacher'=>'actual_teacher','subject'=>'subject','class'=>'class','group'=>'teacher_group','week'=>'week_label'][$statPanel]):[];
$detailQuery=['tab'=>'progress','week'=>$weekKey,'stats_section'=>'indicators','stats_load'=>'1']+$exportQuery;
$rate=$tot['scheduled']?($tot['completed']*100/$tot['scheduled']):0;
$statusCards=[
 ['scheduled','Tổng tiết TKB',$tot['scheduled'],'#173f67'],
 ['completed','Đã hoàn thành (ký)',$tot['completed'],'#166534'],
 ['incomplete','Chưa hoàn thành',$tot['incomplete'],'#991b1b'],
 ['taught','Đã dạy',$tot['taught'],'#1d4ed8'],
 ['substitute','Dạy thay',$tot['substitute'],'#7c3aed'],
 ['makeup','Dạy bù',$tot['makeup'],'#0f766e'],
 ['off','Nghỉ / hoãn / hủy',$tot['off'],'#b45309'],
 ['missing_ppct','Môn chưa có PPCT',$tot['missing_ppct'],'#be123c'],
 ['rate','Tỷ lệ hoàn thành',number_format($rate,1,',','.').'%',$rate>=80?'#166534':'#b45309'],
];
$cols=['scheduled'=>'Tổng','completed'=>'Hoàn thành','incomplete'=>'Chưa HT','missing_ppct'=>'Thiếu PPCT','taught'=>'Đã dạy','substitute'=>'Thay','makeup'=>'Bù','online'=>'Trực tuyến','off'=>'Nghỉ/hoãn/hủy','holiday'=>'Nghỉ lễ','teacher_absent'=>'GV nghỉ','class_absent'=>'Lớp nghỉ','postponed'=>'Hoãn','cancelled'=>'Hủy','pending'=>'Chưa ghi'];
}
?>
<style>
.lb-stat-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}
@media(max-width:900px){.lb-stat-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.lb-stat-kpi{display:block;text-decoration:none;border:1px solid transparent;background:#fff;border-radius:12px;padding:12px 14px;box-shadow:0 4px 16px #183b5b12}
.lb-stat-kpi:hover,.lb-stat-kpi:focus-visible,.lb-stat-kpi[aria-current=true]{border-color:#2c72a5;box-shadow:0 0 0 2px #2c72a51c}.lb-stat-kpi small{font-size:.7rem;color:#516b81}.lb-stat-kpi b{display:block;font-size:1.45rem;line-height:1.1}
.lb-stat-kpi span{color:#64748b;font-size:.8rem}
.lb-stat-switch{display:flex;gap:6px;flex-wrap:wrap}
.lb-stat-switch button{border:1px solid #cbd8e6;background:#fff;color:#1f4e79;border-radius:8px;padding:6px 10px;font-weight:700}
.lb-stat-switch button.active{background:#1f4e79;color:#fff}
.lb-stat-panel{display:none}.lb-stat-panel.active{display:block}
.lb-stat-table th,.lb-stat-table td{white-space:nowrap;font-size:.86rem}
</style>
<form class="lb-filter" method="get">
 <input type="hidden" name="tab" value="progress"><input type="hidden" name="stats_section" value="<?=e($statsSection)?>"><input type="hidden" name="stats_load" value="1"><input type="hidden" name="week" value="<?=e($weekKey)?>">
 <div class="row g-2 align-items-end">
  <div class="col-lg-2"><label class="form-label fw-bold">Phạm vi thời gian</label>
   <select class="form-select" name="stats_range" id="lbStatsRange">
    <option value="week" <?=$statsRange==='week'?'selected':''?>>Theo tuần</option>
    <option value="custom" <?=$statsRange==='custom'?'selected':''?>>Đến thời điểm / khoảng ngày</option>
    <option value="total" <?=$statsRange==='total'?'selected':''?>>Cả năm học</option>
   </select>
  </div>
  <div class="col-lg-3 stats-range-week"><label class="form-label fw-bold">Tuần xem</label>
   <select class="form-select" name="stats_week"><?php foreach($allWeeks as$w):?><option value="<?=e($w['key'])?>" <?=$statsWeekKey===(string)$w['key']?'selected':''?>><?=e($w['label'])?> · <?=date('d/m',strtotime($w['start']))?>–<?=date('d/m',strtotime($w['end']))?></option><?php endforeach;?></select>
  </div>
  <div class="col-lg-2 stats-range-custom"><label class="form-label fw-bold">Từ ngày</label><input class="form-control" type="date" name="stats_from" value="<?=e($statsFrom)?>"></div>
  <div class="col-lg-2 stats-range-custom"><label class="form-label fw-bold">Đến ngày</label><input class="form-control" type="date" name="stats_to" value="<?=e($statsTo)?>"></div>
  <div class="col-lg-3"><label class="form-label fw-bold">Tình trạng sổ</label>
   <select class="form-select" name="stats_completion">
    <option value="all" <?=$statsCompletion==='all'?'selected':''?>>Tất cả tiết TKB</option>
    <option value="completed" <?=$statsCompletion==='completed'?'selected':''?>>Đã ký / hoàn thành</option>
    <option value="incomplete" <?=$statsCompletion==='incomplete'?'selected':''?>>Chưa hoàn thành</option>
    <option value="saved_unsigned" <?=$statsCompletion==='saved_unsigned'?'selected':''?>>Đã lưu, chưa ký</option>
    <option value="not_saved" <?=$statsCompletion==='not_saved'?'selected':''?>>Chưa ghi sổ</option>
   </select>
  </div>
 </div>
 <div class="row g-2 align-items-end mt-1">
  <div class="col-lg-3"><label class="form-label fw-bold">Giáo viên</label><input class="form-control" name="stats_teacher" value="<?=e($statsTeacher)?>" list="lbStats_teacher" placeholder="Tất cả"><datalist id="lbStats_teacher"><?php foreach($teachers as$x):?><option value="<?=e($x)?>"><?php endforeach;?></datalist></div>
  <div class="col-lg-3"><label class="form-label fw-bold">Môn</label><input class="form-control" name="stats_subject" value="<?=e($statsSubject)?>" list="lbStats_subject" placeholder="Tất cả"><datalist id="lbStats_subject"><?php foreach($subjects as$x):?><option value="<?=e($x)?>"><?php endforeach;?></datalist></div>
  <div class="col-lg-2"><label class="form-label fw-bold">Lớp</label><input class="form-control" name="stats_class" value="<?=e($statsClass)?>" list="lbStats_class" placeholder="Tất cả"><datalist id="lbStats_class"><?php foreach($statClasses as$x):?><option value="<?=e($x)?>"><?php endforeach;?></datalist></div>
  <div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-eye"></i> Xem</button></div>
  <div class="col-lg-2 d-flex gap-2">
   <a class="btn btn-outline-secondary flex-fill" href="<?=BASE_URL?>sodaubai.php?tab=progress&stats_section=<?=e($statsSection)?>&week=<?=urlencode($weekKey)?>">Xóa lọc</a>
   <?php if($statsLoad&&$statsSection==='indicators'):?><a class="btn btn-success flex-fill" href="<?=BASE_URL?>sodaubai_stats_export.php?<?=e(http_build_query($exportQuery))?>"><i class="bi bi-download"></i> Excel</a><?php endif;?>
  </div>
 </div>
 <div class="lb-note mt-2">Đang xem <?=date('d/m/Y',strtotime($statsFrom))?> – <?=date('d/m/Y',strtotime($statsTo))?>. Hoàn thành = tiết đã ký. Nghỉ/hoãn/hủy gồm nghỉ lễ, GV nghỉ, lớp nghỉ, hoãn, hủy.</div>
</form>

<?php if(!$statsLoad):?><section class="lb-card text-muted">Chưa tải nội dung. Chọn phạm vi rồi bấm <strong>Xem</strong> để tải riêng mục này.</section><?php elseif($statsSection==='scores'):require __DIR__.'/lesson_book_scores_view.php';elseif($statsSection==='attendance'):require __DIR__.'/lesson_book_attendance_view.php';else:?>
<?php if(lb_stat_team_scoped()):$leaderGroup=lb_stat_leader_group();?><div class="alert alert-info"><?=e($leaderGroup!==''?'Phạm vi thống kê: tổ '.$leaderGroup.'. Chỉ tính các tiết của tổ viên; bộ lọc và file xuất áp dụng cùng phạm vi.':'Chưa xác định được tổ chuyên môn. Vui lòng kiểm tra giáo viên liên kết tài khoản và tổ trong CSDL cán bộ.')?></div><?php endif;?>
<div class="lb-stat-kpis">
<?php foreach($statusCards as[$metric,$label,$value,$color]):?>
 <a class="lb-stat-kpi" aria-current="<?=$statMetric===$metric?'true':'false'?>" href="<?=e(BASE_URL.'sodaubai.php?'.http_build_query($detailQuery+['stats_metric'=>$metric,'stats_group'=>$statPanel]).'#lb-stat-detail')?>"><span><?=e($label)?></span><b style="color:<?=$color?>"><?=is_numeric($value)?number_format((float)$value,0,',','.'):e((string)$value)?></b><small>Xem chi tiết</small></a>
<?php endforeach;?>
</div>
<?php if($statMetric===''):?>
<p class="lb-note">Bấm vào chỉ số cần xem để tải chi tiết theo giáo viên, môn, lớp, tổ hoặc tuần.</p>
<?php else:?>
<section class="lb-card" id="lb-stat-detail">
 <a class="btn btn-sm btn-outline-secondary mb-2" href="<?=e(BASE_URL.'sodaubai.php?'.http_build_query($detailQuery))?>">Thu gọn chi tiết</a>
 <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div><h2 class="h5 mb-1"><?=e(lb_stats_metric_labels()[$statMetric])?></h2><div class="lb-note">Chi tiết các tiết thuộc chỉ số đã chọn.</div></div>
  <div class="lb-stat-switch"><?php foreach(['teacher'=>'Giáo viên','subject'=>'Môn','class'=>'Lớp','group'=>'Tổ','week'=>'Tuần']as$panel=>$label):?><a data-stat-panel="<?=$panel?>" class="btn btn-sm <?=$panel===$statPanel?'btn-primary':'btn-outline-primary'?>" href="<?=e(BASE_URL.'sodaubai.php?'.http_build_query($detailQuery+['stats_metric'=>$statMetric,'stats_group'=>$panel]).'#lb-stat-detail')?>"><?=e($label)?></a><?php endforeach;?></div>
 </div>
 <div data-stat-content="<?=e($statPanel)?>" class="lb-stat-panel active">
  <div class="table-responsive"><table class="table table-sm table-hover lb-stat-table">
   <thead><tr><th><?=e(['teacher'=>'Giáo viên','subject'=>'Môn','class'=>'Lớp','group'=>'Tổ','week'=>'Tuần'][$statPanel])?></th><?php foreach($cols as$lab):?><th class="text-center"><?=e($lab)?></th><?php endforeach;?><th class="text-center">Đã ký / Cần ký</th><th class="text-center">Tỷ lệ</th></tr></thead>
   <tbody>
   <?php foreach($groupRows as$g):$gr=$g['scheduled']?($g['completed']*100/$g['scheduled']):0;?>
    <tr>
     <td class="fw-bold <?=in_array($statPanel,['teacher','subject'],true)&&$g['incomplete']>0?'text-danger':''?>"><?=e($g['name'])?></td>
     <?php foreach(array_keys($cols) as$c):?><td class="text-center"><?=isset($g[$c])?(is_float($g[$c])?number_format($g[$c],1,',','.'):(int)$g[$c]):0?></td><?php endforeach;?>
     <td class="text-center <?=$g['incomplete']>0?'text-danger fw-bold':''?>"><?=$g['completed']?> / <?=$g['scheduled']?></td><td class="text-center"><span class="badge <?=$gr>=100?'text-bg-success':($gr>=80?'text-bg-warning':'text-bg-light')?>"><?=number_format($gr,1,',','.')?>%</span></td>
    </tr>
   <?php endforeach;if(!$groupRows):?><tr><td colspan="<?=count($cols)+3?>" class="lb-empty">Không có dữ liệu trong phạm vi lọc.</td></tr><?php endif;?>
   </tbody>
  </table></div>
 </div>
</section>
<?php endif;?>
<?php endif;?>
<script>(function(){const range=document.getElementById('lbStatsRange');function sync(){document.querySelectorAll('.stats-range-week').forEach(x=>x.style.display=range.value==='week'?'':'none');document.querySelectorAll('.stats-range-custom').forEach(x=>x.style.display=range.value==='custom'?'':'none')}if(range){range.addEventListener('change',sync);sync()}})()</script>

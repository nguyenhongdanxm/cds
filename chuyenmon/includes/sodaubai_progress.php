<?php
$statsRange=in_array((string)($_GET['stats_range']??'week'),['total','week','custom'],true)?(string)$_GET['stats_range']:'week';
$statsWeekKey=(string)($_GET['stats_week']??$weekKey);$statsWeek=lb_week($statsWeekKey)?:$week;
$allWeeks=lb_weeks();$firstWeek=$allWeeks[0]??$week;$lastWeek=$allWeeks?end($allWeeks):$week;
if($statsRange==='total'){$statsFrom=(string)($firstWeek['start']??$week['start']);$statsTo=(string)($lastWeek['end']??$week['end']);}
elseif($statsRange==='week'){$statsFrom=(string)$statsWeek['start'];$statsTo=(string)$statsWeek['end'];}
else{$statsFrom=(string)($_GET['stats_from']??$week['start']);$statsTo=(string)($_GET['stats_to']??$week['end']);}
if($statsTo<$statsFrom){[$statsFrom,$statsTo]=[$statsTo,$statsFrom];}
$statsTeacher=trim((string)($_GET['stats_teacher']??''));$statsSubject=trim((string)($_GET['stats_subject']??''));$statsClass=trim((string)($_GET['stats_class']??''));
$statsCompletion=in_array((string)($_GET['stats_completion']??'all'),['all','completed','incomplete','saved_unsigned','not_saved'],true)?(string)$_GET['stats_completion']:'all';
$allAccessible=lb_stat_rows($statsFrom,$statsTo);$teachers=[];$subjects=[];$statClasses=[];
foreach($allAccessible as$r){$teacherName=trim((string)($r['actual_teacher']??$r['scheduled_teacher']??''));if($teacherName!=='')$teachers[$teacherName]=true;if(trim((string)($r['subject']??''))!=='')$subjects[(string)$r['subject']]=true;if(trim((string)($r['class']??''))!=='')$statClasses[(string)$r['class']]=true;}
$teachers=array_keys($teachers);$subjects=array_keys($subjects);$statClasses=array_keys($statClasses);sort($teachers,SORT_NATURAL);sort($subjects,SORT_NATURAL);sort($statClasses,SORT_NATURAL);
$statRows=lb_stat_rows($statsFrom,$statsTo,$statsTeacher,$statsSubject,$statsClass,$statsCompletion);
$tot=lb_stat_totals($statRows);
$teacherStats=lb_stat_group($statRows,'actual_teacher');$subjectStats=lb_stat_group($statRows,'subject');$classStats=lb_stat_group($statRows,'class');$weekStats=lb_stat_group($statRows,'week_label');
$exportQuery=array_filter(['stats_from'=>$statsFrom,'stats_to'=>$statsTo,'stats_teacher'=>$statsTeacher,'stats_subject'=>$statsSubject,'stats_class'=>$statsClass,'stats_completion'=>$statsCompletion,'stats_range'=>$statsRange,'stats_week'=>$statsWeekKey],'strlen');
$rate=$tot['scheduled']?($tot['completed']*100/$tot['scheduled']):0;
$statusCards=[
 ['Tổng tiết TKB',$tot['scheduled'],'#173f67'],
 ['Đã hoàn thành (ký)',$tot['completed'],'#166534'],
 ['Chưa hoàn thành',$tot['incomplete'],'#991b1b'],
 ['Đã dạy',$tot['taught'],'#1d4ed8'],
 ['Dạy thay',$tot['substitute'],'#7c3aed'],
 ['Dạy bù',$tot['makeup'],'#0f766e'],
 ['Nghỉ / hoãn / hủy',$tot['off'],'#b45309'],
 ['Tỷ lệ hoàn thành',number_format($rate,1,',','.').'%',$rate>=80?'#166534':'#b45309'],
];
$cols=['scheduled'=>'Tổng','completed'=>'Hoàn thành','incomplete'=>'Chưa HT','taught'=>'Đã dạy','substitute'=>'Thay','makeup'=>'Bù','online'=>'Trực tuyến','off'=>'Nghỉ/hoãn/hủy','holiday'=>'Nghỉ lễ','teacher_absent'=>'GV nghỉ','class_absent'=>'Lớp nghỉ','postponed'=>'Hoãn','cancelled'=>'Hủy','pending'=>'Chưa ghi'];
?>
<style>
.lb-stat-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-bottom:16px}
@media(max-width:900px){.lb-stat-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
.lb-stat-kpi{background:#fff;border-radius:12px;padding:12px 14px;box-shadow:0 4px 16px #183b5b12}
.lb-stat-kpi b{display:block;font-size:1.45rem;line-height:1.1}
.lb-stat-kpi span{color:#64748b;font-size:.8rem}
.lb-stat-switch{display:flex;gap:6px;flex-wrap:wrap}
.lb-stat-switch button{border:1px solid #cbd8e6;background:#fff;color:#1f4e79;border-radius:8px;padding:6px 10px;font-weight:700}
.lb-stat-switch button.active{background:#1f4e79;color:#fff}
.lb-stat-panel{display:none}.lb-stat-panel.active{display:block}
.lb-stat-table th,.lb-stat-table td{white-space:nowrap;font-size:.86rem}
</style>
<form class="lb-filter" method="get">
 <input type="hidden" name="tab" value="progress"><input type="hidden" name="week" value="<?=e($weekKey)?>">
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
  <div class="col-lg-3"><label class="form-label fw-bold">Giáo viên</label><select class="form-select" name="stats_teacher"><option value="">Tất cả</option><?php foreach($teachers as$x):?><option <?=$x===$statsTeacher?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
  <div class="col-lg-3"><label class="form-label fw-bold">Môn</label><select class="form-select" name="stats_subject"><option value="">Tất cả</option><?php foreach($subjects as$x):?><option <?=$x===$statsSubject?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
  <div class="col-lg-2"><label class="form-label fw-bold">Lớp</label><select class="form-select" name="stats_class"><option value="">Tất cả</option><?php foreach($statClasses as$x):?><option <?=$x===$statsClass?'selected':''?>><?=e($x)?></option><?php endforeach;?></select></div>
  <div class="col-lg-2"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Lọc</button></div>
  <div class="col-lg-2 d-flex gap-2">
   <a class="btn btn-outline-secondary flex-fill" href="<?=BASE_URL?>sodaubai.php?tab=progress&week=<?=urlencode($weekKey)?>">Xóa lọc</a>
   <a class="btn btn-success flex-fill" href="<?=BASE_URL?>sodaubai_stats_export.php?<?=e(http_build_query($exportQuery))?>"><i class="bi bi-download"></i> Excel</a>
  </div>
 </div>
 <div class="lb-note mt-2">Đang xem <?=date('d/m/Y',strtotime($statsFrom))?> – <?=date('d/m/Y',strtotime($statsTo))?>. Hoàn thành = tiết đã ký. Nghỉ/hoãn/hủy gồm nghỉ lễ, GV nghỉ, lớp nghỉ, hoãn, hủy.</div>
</form>

<div class="lb-stat-kpis">
<?php foreach($statusCards as[$label,$value,$color]):?>
 <div class="lb-stat-kpi"><span><?=e($label)?></span><b style="color:<?=$color?>"><?=is_numeric($value)?number_format((float)$value,0,',','.'):e((string)$value)?></b></div>
<?php endforeach;?>
</div>

<section class="lb-card">
 <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div><h2 class="h5 mb-0">Bảng chỉ số theo đối tượng</h2><div class="lb-note">Tách theo tuần, giáo viên, môn hoặc lớp.</div></div>
  <div class="lb-stat-switch"><?php foreach(['teacher'=>'Giáo viên','subject'=>'Môn','class'=>'Lớp','week'=>'Tuần']as$k=>$label):?><button type="button" data-stat-panel="<?=$k?>" class="<?=$k==='teacher'?'active':''?>"><?=e($label)?></button><?php endforeach;?></div>
 </div>
 <?php foreach(['teacher'=>$teacherStats,'subject'=>$subjectStats,'class'=>$classStats,'week'=>$weekStats]as$panel=>$groupRows):?>
 <div class="lb-stat-panel <?=$panel==='teacher'?'active':''?>" data-stat-content="<?=$panel?>">
  <div class="table-responsive"><table class="table table-sm table-hover lb-stat-table">
   <thead><tr><th><?=e(['teacher'=>'Giáo viên','subject'=>'Môn','class'=>'Lớp','week'=>'Tuần'][$panel])?></th><?php foreach($cols as$c=>$lab):?><th class="text-center"><?=e($lab)?></th><?php endforeach;?><th class="text-center">Tỷ lệ</th></tr></thead>
   <tbody>
   <?php foreach($groupRows as$g):$gr=$g['scheduled']?($g['completed']*100/$g['scheduled']):0;?>
    <tr>
     <td class="fw-bold"><?=e($g['name'])?></td>
     <?php foreach(array_keys($cols) as$c):?><td class="text-center"><?=isset($g[$c])?(is_float($g[$c])?number_format($g[$c],1,',','.'):(int)$g[$c]):0?></td><?php endforeach;?>
     <td class="text-center"><span class="badge <?=$gr>=100?'text-bg-success':($gr>=80?'text-bg-warning':'text-bg-light')?>"><?=number_format($gr,1,',','.')?>%</span></td>
    </tr>
   <?php endforeach;if(!$groupRows):?><tr><td colspan="<?=count($cols)+2?>" class="lb-empty">Không có dữ liệu trong phạm vi lọc.</td></tr><?php endif;?>
   </tbody>
  </table></div>
 </div>
 <?php endforeach;?>
</section>
<script>(function(){const range=document.getElementById('lbStatsRange');function sync(){document.querySelectorAll('.stats-range-week').forEach(x=>x.style.display=range.value==='week'?'':'none');document.querySelectorAll('.stats-range-custom').forEach(x=>x.style.display=range.value==='custom'?'':'none')}if(range){range.addEventListener('change',sync);sync()}document.querySelectorAll('[data-stat-panel]').forEach(b=>b.addEventListener('click',()=>{document.querySelectorAll('[data-stat-panel]').forEach(x=>x.classList.toggle('active',x===b));document.querySelectorAll('[data-stat-content]').forEach(x=>x.classList.toggle('active',x.dataset.statContent===b.dataset.statPanel))}))})()</script>

<?php
$page_title='Tải giảng theo ngày';
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/timetable_store.php';
$user=cds_user();
if(!$user){header('Location: /login.php?next='.urlencode($_SERVER['REQUEST_URI']??''));exit;}
$isAdmin=(($user['role']??'')==='admin');
if(!cds_can_feature('cm.tracuu','view')&&!$isAdmin){http_response_code(403);exit('Bạn chưa có quyền xem Thời khóa biểu.');}
$teachers=array_values(array_filter(array_map('strval',(array)load_json(TEACHERS_FILE,[]))));
$teachers=sort_teachers_by_ten($teachers);
$date=(string)($_GET['date']??date('Y-m-d'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))$date=date('Y-m-d');
$countFilter=(string)($_GET['lessons']??'all');
$weekId=(string)($_GET['week_id']??'');
$weeks=tkb_weeks();
$week=$weekId!==''?tkb_week_by_id($weekId):null;
if(!$week){foreach($weeks as $w){$start=(string)($w['start_date']??'');if(!$start)continue;$end=date('Y-m-d',strtotime($start.' +6 days'));if($date>=$start&&$date<=$end){$week=$w;break;}}}
if(!$week)$week=tkb_active_week();
$slots=$week?tkb_resolved_slots($week):[];
$day=(int)date('N',strtotime($date))+1;
$counts=array_fill_keys($teachers,0);$details=array_fill_keys($teachers,[]);
foreach($slots as $s){if((int)($s['day']??0)!==$day)continue;$teacher=trim((string)($s['teacher']??''));if($teacher===''||!array_key_exists($teacher,$counts))continue;$sub=tkb_substitution_for_slot($week,$s);$actual=$teacher;if(is_array($sub)&&($sub['status']??'')==='approved'&&($sub['date']??'')===$date){$replacement=trim((string)($sub['substitute_teacher']??''));if($replacement!==''){$actual=$replacement;$counts[$teacher]=max(0,$counts[$teacher]-0);}}
if($actual!==$teacher){if(array_key_exists($actual,$counts)){$counts[$actual]++;$details[$actual][]=$s;}continue;}
$counts[$teacher]++;$details[$teacher][]=$s;}
$rows=[];foreach($teachers as$t){$n=(int)($counts[$t]??0);if($countFilter!=='all'&&$n!==(int)$countFilter)continue;$rows[]=['teacher'=>$t,'count'=>$n,'details'=>$details[$t]??[]];}
usort($rows,function($a,$b){if($a['count']!==$b['count'])return$a['count']<=>$b['count'];return strnatcasecmp($a['teacher'],$b['teacher']);});
$summary=[];for($i=0;$i<=8;$i++)$summary[$i]=count(array_filter($counts,fn($n)=>(int)$n===$i));
require_once __DIR__.'/includes/header.php';
?>
<style>.load-chip{border:1px solid #dbe5ee;border-radius:12px;background:#fff;padding:.7rem;text-align:center}.load-chip strong{display:block;font-size:1.45rem;color:#1f4e79}.load-zero{background:#fff8e1}.load-table td{vertical-align:middle}.lesson-pill{display:inline-block;margin:2px;padding:3px 7px;border-radius:12px;background:#e8f0fe;font-size:.78rem;color:#174a72}</style>
<div class="container pb-4">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3"><div><h3 class="fw-bold text-primary mb-1"><i class="bi bi-person-lines-fill"></i> Tải giảng giáo viên theo ngày</h3><div class="text-muted">Xem nhanh giáo viên không có tiết, có 1 tiết, 2 tiết… trong ngày đã chọn.</div></div><a class="btn btn-outline-primary" href="<?=BASE_URL?>thoikhoabieu.php?tab=lookup"><i class="bi bi-arrow-left"></i> Thời khóa biểu</a></div>
<form class="card p-3 mb-3" method="get"><div class="row g-2 align-items-end"><div class="col-md-4"><label class="form-label fw-semibold">Ngày xem</label><input class="form-control" type="date" name="date" value="<?=e($date)?>"></div><div class="col-md-4"><label class="form-label fw-semibold">Số tiết lên lớp</label><select class="form-select" name="lessons"><option value="all">Tất cả giáo viên</option><?php for($i=0;$i<=8;$i++):?><option value="<?=$i?>" <?=$countFilter===(string)$i?'selected':''?>><?=$i===0?'Không có tiết':$i.' tiết'?></option><?php endfor;?></select></div><div class="col-md-4"><button class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Lọc danh sách</button></div></div></form>
<div class="row g-2 mb-3"><?php for($i=0;$i<=4;$i++):?><div class="col-6 col-md"><a class="text-decoration-none" href="?date=<?=urlencode($date)?>&lessons=<?=$i?>"><div class="load-chip <?=$i===0?'load-zero':''?>"><strong><?=intval($summary[$i]??0)?></strong><span><?=$i===0?'Không có tiết':$i.' tiết'?></span></div></a></div><?php endfor;?></div>
<div class="card overflow-hidden"><div class="card-header d-flex justify-content-between"><span>Ngày <?=date('d/m/Y',strtotime($date))?></span><span><?=count($rows)?> giáo viên</span></div><div class="table-responsive"><table class="table table-hover load-table mb-0"><thead><tr><th style="width:55px">STT</th><th>Giáo viên</th><th class="text-center">Số tiết</th><th>Chi tiết tiết dạy</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="4" class="text-center text-muted py-4">Không có giáo viên phù hợp bộ lọc.</td></tr><?php endif;?><?php foreach($rows as$i=>$r):?><tr><td><?=($i+1)?></td><td class="fw-semibold"><?=e($r['teacher'])?></td><td class="text-center"><span class="badge <?=$r['count']===0?'text-bg-warning':'text-bg-primary'?> fs-6"><?=$r['count']?></span></td><td><?php if(!$r['details']):?><span class="text-muted">Không có tiết lên lớp</span><?php else:?><?php foreach($r['details'] as$s):?><span class="lesson-pill"><?=e((string)($s['session']??''))?> · Tiết <?=intval($s['period']??0)?> · <?=e((string)($s['class']??$s['class_raw']??''))?> · <?=e((string)($s['subject']??''))?></span><?php endforeach;?><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>

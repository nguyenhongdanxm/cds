<?php
require_once __DIR__.'/includes/timetable_store.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
function public_tkb_h($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$weeks=array_values(array_filter(tkb_weeks(),fn($w)=>!empty($w['slots'])&&is_array($w)));
usort($weeks,fn($a,$b)=>strcmp((string)($b['start_date']??''),(string)($a['start_date']??'')));
$weekId=trim((string)($_GET['week_id']??''));
$week=null;
foreach($weeks as$item)if((string)($item['id']??'')===$weekId){$week=$item;break;}
if(!$week)$week=$weeks[0]??null;
$slots=$week?tkb_resolved_slots($week):[];
$classes=[];
foreach($slots as$slot){$name=trim((string)($slot['class']??$slot['class_raw']??''));if($name!=='')$classes[$name]=$name;}
$classes=array_values($classes);sort($classes,SORT_NATURAL|SORT_FLAG_CASE);
$className=trim((string)($_GET['class']??''));
if(!in_array($className,$classes,true))$className=$classes[0]??'';
$classSlots=array_values(array_filter($slots,fn($s)=>trim((string)($s['class']??$s['class_raw']??''))===$className));
$grid=[];$max=['Sáng'=>0,'Chiều'=>0];
foreach($classSlots as$slot){$session=mb_stripos((string)($slot['session']??''),'chiều',0,'UTF-8')!==false?'Chiều':'Sáng';$period=max(1,(int)($slot['period']??1));$day=(int)($slot['day']??0);if($day<2||$day>7)continue;$max[$session]=max($max[$session],$period);$grid[$session][$period][$day][]=$slot;}
$school=defined('SCHOOL_NAME')?SCHOOL_NAME:'Thời khóa biểu';
?><!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Thời khóa biểu học sinh · <?=public_tkb_h($school)?></title>
<style>
:root{--blue:#195b87;--blue2:#267dad;--line:#d7e2eb;--soft:#eef5fa;--text:#172536;--muted:#64748b}*{box-sizing:border-box}body{margin:0;background:#f3f7fb;color:var(--text);font-family:system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif}.hero{background:linear-gradient(120deg,#164b72,#2582b6);color:#fff;padding:25px 18px}.hero-inner,.main{max-width:1180px;margin:auto}.hero h1{margin:0 0 5px;font-size:clamp(1.45rem,4vw,2.1rem)}.hero p{margin:0;opacity:.86}.main{padding:18px}.filter{display:grid;grid-template-columns:1fr 1fr auto;gap:12px;align-items:end;padding:16px;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 20px rgba(28,65,94,.06);margin-bottom:16px}label{display:block;font-size:.82rem;font-weight:750;margin-bottom:5px;color:#344b60}select,button{width:100%;height:42px;border:1px solid #c7d5e0;border-radius:10px;background:#fff;padding:0 12px;font-size:.95rem}button{width:auto;background:var(--blue);border-color:var(--blue);color:#fff;font-weight:750;cursor:pointer}.meta{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin:8px 2px 12px}.meta h2{margin:0;color:var(--blue);font-size:1.25rem}.meta span{color:var(--muted);font-size:.9rem}.table-wrap{overflow:auto;background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 20px rgba(28,65,94,.05)}table{border-collapse:collapse;width:100%;min-width:780px;table-layout:fixed}th,td{border-right:1px solid var(--line);border-bottom:1px solid var(--line);text-align:center}thead th{background:#e9f2f8;color:#203a50;padding:11px 6px}.session{width:62px;background:#edf5fa;font-weight:800;color:var(--blue)}.period{width:58px;background:#f7fafc;font-weight:800}td{height:78px;padding:6px;background:#fff}.lesson+.lesson{border-top:1px dashed #cbd5df;margin-top:5px;padding-top:5px}.subject{font-weight:800;font-size:.9rem}.teacher{color:#536b7e;font-size:.78rem;margin-top:3px}.empty{padding:28px;text-align:center;background:#fff;border:1px solid var(--line);border-radius:14px;color:var(--muted)}.foot{text-align:center;color:#718096;font-size:.78rem;padding:22px 8px}@media(max-width:700px){.filter{grid-template-columns:1fr}.filter button{width:100%}.main{padding:12px}.hero{padding:20px 14px}td{height:68px}.subject{font-size:.8rem}.teacher{font-size:.7rem}}@media print{body{background:#fff}.filter,.foot{display:none}.main{max-width:none;padding:0}.table-wrap{box-shadow:none}.hero{padding:10px;background:#fff;color:#111}}
</style></head><body>
<header class="hero"><div class="hero-inner"><h1>Thời khóa biểu học sinh</h1><p><?=public_tkb_h($school)?> · Tra cứu không cần đăng nhập</p></div></header>
<main class="main">
<form class="filter" method="get">
 <div><label for="week_id">Chọn tuần</label><select id="week_id" name="week_id" onchange="this.form.submit()"><?php foreach($weeks as$item):?><option value="<?=public_tkb_h($item['id']??'')?>" <?=($week['id']??'')===($item['id']??'')?'selected':''?>><?=public_tkb_h($item['label']??'Tuần học')?> · <?=!empty($item['start_date'])?date('d/m/Y',strtotime($item['start_date'])):''?>–<?=!empty($item['end_date'])?date('d/m/Y',strtotime($item['end_date'])):''?></option><?php endforeach;?></select></div>
 <div><label for="class">Chọn lớp</label><select id="class" name="class" onchange="this.form.submit()"><?php foreach($classes as$class):?><option value="<?=public_tkb_h($class)?>" <?=$className===$class?'selected':''?>>Lớp <?=public_tkb_h($class)?></option><?php endforeach;?></select></div>
 <button type="submit">Xem TKB</button>
</form>
<?php if(!$week):?><div class="empty">Chưa có thời khóa biểu được cập nhật.</div>
<?php elseif($className===''):?><div class="empty">Tuần đã chọn chưa có dữ liệu lớp.</div>
<?php else:?><div class="meta"><h2>Thời khóa biểu lớp <?=public_tkb_h($className)?></h2><span><?=public_tkb_h($week['label']??'')?> · <?=date('d/m/Y',strtotime((string)$week['start_date']))?>–<?=date('d/m/Y',strtotime((string)$week['end_date']))?></span></div>
<div class="table-wrap"><table><thead><tr><th style="width:62px">Buổi</th><th style="width:58px">Tiết</th><?php for($day=2;$day<=7;$day++):?><th>Thứ <?=$day?></th><?php endfor;?></tr></thead><tbody>
<?php foreach(['Sáng','Chiều']as$session):$count=(int)($max[$session]??0);if($count<1)continue;for($period=1;$period<=$count;$period++):?><tr><?php if($period===1):?><th class="session" rowspan="<?=$count?>"><?=$session?></th><?php endif;?><th class="period"><?=$period?></th><?php for($day=2;$day<=7;$day++):?><td><?php foreach((array)($grid[$session][$period][$day]??[])as$item):?><div class="lesson"><div class="subject"><?=public_tkb_h($item['subject']??'')?></div><div class="teacher"><?=public_tkb_h($item['teacher']??$item['teacher_raw']??'')?></div></div><?php endforeach;?></td><?php endfor;?></tr><?php endfor;endforeach;?>
</tbody></table></div><?php endif;?>
<div class="foot">Dữ liệu được cập nhật từ hệ thống CDS · <?=date('d/m/Y H:i')?></div>
</main></body></html>
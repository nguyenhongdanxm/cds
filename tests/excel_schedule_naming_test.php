<?php
require __DIR__.'/../includes/excel_schedule_naming.php';require __DIR__.'/../includes/excel_schedule_store.php';
function nc($v,$m){if(!$v)throw new RuntimeException($m);}
$now=new DateTimeImmutable('2026-11-11 17:30:05',new DateTimeZone('UTC'));
$job=['name'=>'Sổ đầu bài','file_mode'=>'replace','_resolved_params'=>['__auto_name'=>'1','class'=>'6A','week'=>'10','from'=>'2026-11-01','to'=>'2026-11-07'],'_week_label'=>'Tuần 10'];$report=['format'=>'xlsx','label'=>'Sổ đầu bài'];
$name=xlsched_filename($job,[], $report,$now);nc(str_contains($name,'Lớp 6A')&&str_contains($name,'Tuần 10')&&str_contains($name,'2026-11-01')&&str_contains($name,'đến 2026-11-07')&&str_ends_with($name,'2026-11-12_003005.xlsx'),'Actual filters and Vietnam timestamp');
$job['_resolved_params']['class']='6A/../../<bad>';nc(!str_contains(xlsched_filename($job,[],$report,$now),'/'),'Filename escaping');
$legacy=['name'=>'Biên bản','file_mode'=>'snapshot'];nc(xlsched_filename($legacy,['period_key'=>'manual:2026-11-12 01:00:00'],['format'=>'zip','label'=>'ZIP'],$now)==='Biên bản-manual-2026-11-12-01-00-00.zip','Existing naming preserved');
$catalog=xlsched_catalog();foreach($catalog as$r)nc(in_array($r['file_group'],['excel','word','pdf','archive','csv'],true),'Every source has a file group');
nc(xlsched_match('document_archive_export.php','GET',['extension'=>'pdf'])['id']==='original_pdf','Specific PDF source wins over generic archive');nc(xlsched_match('document_archive_export.php','GET',['extension'=>'word'])['id']==='original_word','Specific Word source');nc($catalog['original_pdf']['format']==='zip'&&$catalog['original_pdf']['file_group']==='pdf','PDF archives labelled truthfully');
nc(xlsched_params($catalog['lb_class'],['__auto_name'=>'1','class'=>'6A','week'=>'10'])['__auto_name']==='1','Naming option persists separately from source filters');
try{xlsched_params($catalog['lb_class'],['__auto_name'=>['1']]);throw new RuntimeException('Array option accepted');}catch(InvalidArgumentException $e){}
echo "PASS default names: actual class/week/date scope, Vietnam timestamp, escaped paths, legacy compatibility, file groups and precise source matching\n";

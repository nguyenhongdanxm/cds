<?php
/** Render the actual publication view with synthetic data; no app login or live writes. */
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function show_flash(){}
$mode=$argv[2]??'mismatch';
$plan=['id'=>'fixture','name'=>'Phương án <kiểm thử>','entries'=>[[],[]],'unplaced'=>[],'errors'=>[]];
$valid=$mode!=='incomplete';$weeks=[['key'=>'11','label'=>'Tuần 11','start'=>'2026-11-16']];$published=[];$csrf='fixture';
$selectedKey=$mode==='no-week'?'':'11';$selectedWeek=$selectedKey===''?null:$weeks[0];$hasExisting=true;
$review=['token'=>'snapshot','rows'=>[]];$reviewError=$mode==='error'?'Trùng lịch giáo viên GDĐP.':'';
if(!in_array($mode,['clean','error','no-week'],true))foreach(['8A','9A']as$i=>$class)$review['rows'][]=['key'=>'key'.$i,'class'=>$class,'ppct_period'=>11,'day'=>2+$i,'session'=>'Sáng','period'=>1,'expected'=>'GV Phân công','actual'=>'GV <Thực tế>'];
$source=file_get_contents(__DIR__.'/../tkb_publish.php');$start=strpos($source,'?><!doctype html>');check($start!==false,'Actual view marker exists');
ob_start();eval(substr($source,$start));$html=ob_get_clean();
if(($argv[1]??'')==='--html'){echo $html;exit;}
check(substr_count($html,'name="gdp_ack[]"')===2,'Each mismatched lesson has own acknowledgement');
check(str_contains($html,'gdp_review_token')&&str_contains($html,'value="snapshot"'),'Snapshot token posted');
check(str_contains($html,'GV &lt;Thực tế&gt;')&&!str_contains($html,'GV <Thực tế>'),'Teacher text escaped');
check(str_contains($html,'gdp-expected')&&str_contains($html,'gdp-actual')&&str_contains($html,'Quay lại sửa giáo viên'),'Clear comparison and correction path');
$editor=file_get_contents(__DIR__.'/../tkb_editor.php');
check(str_contains($editor,'gdp-lesson-badge')&&str_contains($editor,'gdp-review-badge'),'Mismatch visible in cell and toolbar');
check(!str_contains($editor,'background:#ffd5e2!important;color:#111!important'),'Old forced black toast styling removed');
$css=file_get_contents(__DIR__.'/../assets/timetable-warnings.css');
check(str_contains($css,'CẢNH BÁO — CẦN KIỂM TRA')&&str_contains($css,'KHÔNG THỂ THỰC HIỆN')&&str_contains($css,'max-height:80vh'),'Severity titles and bounded scroll');
echo "PASS real publication view: per-lesson consent, snapshot, escaped teachers, correction link, visible editor badges and distinct warning styles\n";

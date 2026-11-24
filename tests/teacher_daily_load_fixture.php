<?php
$dir=$argv[1];mkdir($dir.'/includes');$root=dirname(__DIR__);
copy($root.'/chuyenmon/tkb_taigiang.php',$dir.'/tkb_taigiang.php');copy($root.'/chuyenmon/includes/teacher_daily_load.php',$dir.'/includes/teacher_daily_load.php');
file_put_contents($dir.'/includes/functions.php', <<<'STUB'
<?php
session_start();define('BASE_URL','/');define('TEACHERS_FILE','fixture');function cds_user(){return ($_COOKIE['role']??'admin')==='guest'?null:['role'=>$_COOKIE['role']??'admin'];}function cds_can_daily_teacher_load(){return ($_COOKIE['role']??'admin')!=='denied';}function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function load_json($f,$d=[]){return ['An','Bình','Chi','GV <script>'];}function sort_teachers_by_ten($rows){natcasesort($rows);return array_values($rows);}
STUB);
file_put_contents($dir.'/includes/timetable_store.php', <<<'STUB'
<?php
function tkb_weeks(){return [['id'=>'week','label'=>'Tuần kiểm tra','start_date'=>'2026-11-23']];}function tkb_week_by_id($id){return $id==='week'?tkb_weeks()[0]:null;}function tkb_active_week(){return tkb_weeks()[0];}
function tkb_resolved_slots($week){$b=['teacher'=>'An','day'=>4,'session'=>'Sáng','period'=>1,'class'=>'6A','class_raw'=>'6A','subject'=>'Toán'];return [$b,array_merge($b,['period'=>2,'subject'=>'Văn','class'=>'7B','class_raw'=>'7B']),array_merge($b,['session'=>'Chiều']),array_merge($b,['period'=>3,'subject'=>'Lý']),array_merge($b,['teacher'=>'Chi','day'=>5]),array_merge($b,['teacher'=>'Bình','day'=>5])];}
function tkb_slot_key($slot){return $slot['day'].'|'.$slot['session'].'|'.$slot['period'].'|'.$slot['class_raw'];}
function tkb_week_substitutions($week,$approved){return [['slot_key'=>'4|Sáng|3|6A','date'=>'2026-11-25','substitute_teacher'=>'Bình']];}
STUB);
file_put_contents($dir.'/includes/header.php','<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="https://fixture.test/bootstrap.css"></head><body><main>');file_put_contents($dir.'/includes/footer.php','</main></body></html>');

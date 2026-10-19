<?php
// No application bootstrap, database credentials or operating school data.
$_GET=['scope'=>$argv[1]??'class','class'=>$argv[2]??'6A','teacher'=>'GV bộ môn','paper'=>$argv[3]??'a4_pair','from_week'=>'w1','to_week'=>'w1'];
define('BASE_URL','/chuyenmon/');
function require_login(){}
function lb_norm($s){return strtolower(preg_replace('/\s+/u','',trim($s)));}
function lb_same($a,$b){return lb_norm($a)===lb_norm($b);}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function cds_core_sql_rows($type){return ['classes'=>[['name'=>'6A','school_year_id'=>'y','homeroom_teacher_id'=>'a'],['name'=>'7A','school_year_id'=>'y','homeroom_teacher_id'=>'b']],'teachers'=>[['id'=>'a','name'=>'GVCN lớp 6A'],['id'=>'b','name'=>'GVCN lớp 7A']],'years'=>[['id'=>'y','is_current'=>1]]][$type];}
function load_json($path,$default){throw new RuntimeException('Fixture must not access files');}
function cds_school_year_resolve(){return ['id'=>'y','label'=>'2026–2027'];}
function lb_weeks(){return [['key'=>'w1','start'=>'2026-09-07','end'=>'2026-09-13','label'=>'Tuần 1']];}
function lb_week($key){return lb_weeks()[0];}
function lb_slots($week){return [['class'=>$_GET['class'],'date'=>'2026-09-07','session'=>'Sáng','period'=>1,'subject'=>'Toán','scheduled_teacher'=>'GV bộ môn','lesson_title'=>'Bài 1']];}
function lb_can_view($row){return true;}
function lb_print_validation($rows){return [];}
function lb_weekly_reviews($key,$class){return [];}
function lb_settings(){return ['print'=>['homeroom_name'=>'TÊN CHUNG CŨ KHÔNG ĐƯỢC IN','leader_name'=>'BGH','mirrored_margins'=>true]];}
require __DIR__.'/../../chuyenmon/includes/lesson_book_print_homeroom.php';
$path=__DIR__.'/../../chuyenmon/sodaubai_print.php';$source=file_get_contents($path);
foreach(["require_once __DIR__ . '/includes/functions.php';","require_once __DIR__ . '/includes/lesson_book_store.php';","require_once __DIR__ . '/includes/lesson_book_print_homeroom.php';"]as$line)$source=str_replace($line,';',$source);
eval('?>'.$source);

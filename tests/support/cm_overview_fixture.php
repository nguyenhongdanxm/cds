<?php
function cmov_fixture():string{
 $root=dirname(__DIR__,2);$dir=sys_get_temp_dir().'/cm-overview-'.bin2hex(random_bytes(6));foreach(['chuyenmon/includes','assets','data']as$p)mkdir($dir.'/'.$p,0700,true);
 foreach(['index.php','includes/cm_overview.php','includes/lesson_book_attendance.php','includes/periodic_report.php']as$p)copy($root.'/chuyenmon/'.$p,$dir.'/chuyenmon/'.$p);copy($root.'/assets/cm-overview.css',$dir.'/assets/cm-overview.css');
 file_put_contents($dir.'/chuyenmon/includes/header.php','<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0;padding:20px;background:#f0f4f8;font:15px/1.5 system-ui}.visually-hidden{position:absolute;clip:rect(0,0,0,0);width:1px;height:1px;overflow:hidden}</style></head><body>');file_put_contents($dir.'/chuyenmon/includes/footer.php','</body></html>');
 file_put_contents($dir.'/chuyenmon/includes/functions.php', <<<'STUB'
<?php
define('BASE_URL','/chuyenmon/');function require_login(){}function cds_user(){return ['role'=>($_GET['as']??'admin')==='admin'?'admin':'gv','teacher_name'=>'Fixture teacher','groups'=>[]];}function cds_user_has_group($u,$g){return false;}function cds_can_feature($code,$level){return ($_GET['as']??'admin')!=='denied';}function get_teachers_sorted(){return ['Fixture teacher'];}function get_teacher_group($name){return 'A';}function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function lb_norm($v){return mb_strtolower(trim($v),'UTF-8');}function tkb_key($v){return strtolower($v);}function lb_is_admin(){return cds_user()['role']==='admin';}function lb_is_leader(){return false;}
function cds_school_year_resolve(){return ['label'=>'2026–2027'];}function lb_weeks(){return [['key'=>'7','label'=>'Tuần 7','start'=>'2026-10-19','end'=>'2026-10-25'],['key'=>'8','label'=>'Tuần 8','start'=>'2026-10-26','end'=>'2026-11-01']];}
function lb_record_map_range($from,$to){return [];}
function lb_slots($week,$map,$light){if($week['key']==='8')return[];$a=['slot_id'=>'a','date'=>$week['start'],'class'=>'6A','session'=>'Sáng','status'=>'taught','signed_at'=>'signed','absent_names'=>'An; Bình'];return [$a,array_replace($a,['slot_id'=>'b','signed_at'=>'']),array_replace($a,['slot_id'=>'foreign','class'=>'9A'])];}
function lb_saved_row_for_base($m,$all,$b,&$matched){return [];}function lb_merge_independent_records($rows,$all,$w){return $rows;}function lb_stat_can_view($r){return lb_is_admin()||($r['class']??'')==='6A';}function lb_stat_status($r){return $r['status']??'pending';}
function tkb_substitution_status($r){return $r['status']??'approved';}function tkb_substitutions(){return [['id'=>'s1','date'=>'2026-10-19','type'=>'makeup','class'=>'6A','status'=>'approved'],['id'=>'s2','date'=>'2026-10-19','type'=>'fill','class'=>'9A','status'=>'approved']];}
function cm_docs_all(){return array_map(fn($i)=>['id'=>'r'.$i,'section'=>'bc_dinhky','title'=>'Báo cáo '.$i.' <script>window.bad=1</script>','report_group'=>$i===6?'B':'A','updated_at'=>'2026-10-0'.$i],range(1,6));}
STUB);
 foreach(['lesson_book_store.php','cm_docs.php']as$p)file_put_contents($dir.'/chuyenmon/includes/'.$p,'<?php');
 foreach(['teachers'=>[['id'=>'t1'],['id'=>'t2','active'=>false]],'classes'=>[['id'=>'c1']],'students'=>[['id'=>'p1'],['id'=>'p2','departure_date'=>'2000-01-01']]]as$entity=>$rows)file_put_contents($dir.'/data/'.$entity.'.json',json_encode($rows));return $dir;
}

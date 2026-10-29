<?php
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$dir=sys_get_temp_dir().'/cds_lb_roster_'.bin2hex(random_bytes(5));mkdir($dir.'/chuyenmon/includes',0700,true);mkdir($dir.'/includes',0700,true);
try{
$source=file_get_contents(__DIR__.'/../chuyenmon/includes/lesson_book_store.php');$start=strpos($source,'function lb_class_student_names(');$end=strpos($source,'function lb_id(',$start);check($start!==false&&$end!==false,'Production roster helpers found');
file_put_contents($dir.'/chuyenmon/includes/roster.php',"<?php\n".substr($source,$start,$end-$start));
file_put_contents($dir.'/includes/csdl_store.php', <<<'STUB'
<?php
$GLOBALS['loaded']=($GLOBALS['loaded']??0)+1;
function csdl_students_all(){$GLOBALS['rosterReads']=($GLOBALS['rosterReads']??0)+1;return [
 ['id'=>'1','name'=>'Nguyễn An','class_id'=>'c6','active'=>true],
 ['id'=>'2','name'=>'Trần Bình','class_name'=>'Lớp 6A','active'=>true],
 ['id'=>'3','name'=>'Chuyển trường','class_id'=>'c6','active'=>false],
 ['id'=>'4','ho_ten'=>'Lê Chi','lop'=>'6B'],
 ['id'=>'5','name'=>'<script>test</script>','class'=>'6A'],
 ['id'=>'6','name'=>'Nguyễn An','class_id'=>'c6','active'=>true],
 ];}
function csdl_classes_all(){return [['id'=>'c6','name'=>'6A']];}
STUB);
function lb_norm($s){$s=mb_strtolower(trim($s),'UTF-8');return preg_replace('/[^\p{L}\p{N}]/u','',$s);}
check(!function_exists('csdl_students_all'),'Roster starts unloaded, as actual daily page');require $dir.'/chuyenmon/includes/roster.php';
$names=lb_class_student_names('6A');check(count($names)===3&&in_array('Nguyễn An',$names,true)&&in_array('Trần Bình',$names,true),'Lazy load before cache; class_id/name and class prefix resolved');check(!in_array('Chuyển trường',$names,true),'Inactive student excluded');check($GLOBALS['loaded']===1&&$GLOBALS['rosterReads']===1,'Shared store and full roster loaded once');
check(lb_class_student_names('Lớp 6A')===$names,'Class alias works');$map=lb_visible_class_students([['class'=>'6A'],['class'=>'6B'],['class'=>'6A']]);check(count($map)===2&&$map['6B']===['Lê Chi']&&$GLOBALS['rosterReads']===1,'All visible classes use single shared map');
$page=file_get_contents(__DIR__.'/../chuyenmon/sodaubai.php');check(str_contains($page,'json_encode($lbClassStudents,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT'),'Inline roster script is HTML-safe');check(str_contains($page,'/assets/lesson-book-absence.js?v=20261029'),'Daily route loads actual picker asset');
echo "PASS roster: shared CSDL lazy bootstrap, one-pass cache, class_id/class-name aliases, active students only, existing names untouched, safe embedded JSON\n";
}finally{foreach([$dir.'/chuyenmon/includes/roster.php',$dir.'/includes/csdl_store.php']as$f)if(is_file($f))unlink($f);rmdir($dir.'/chuyenmon/includes');rmdir($dir.'/chuyenmon');rmdir($dir.'/includes');rmdir($dir);}

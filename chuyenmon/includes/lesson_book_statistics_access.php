<?php
/** Team-leader access is confined to statistics, never record editing/signing. */
function lb_stat_team_scoped():bool {
 if(lb_is_admin())return false;
 $u=lb_user();if(($u['role']??'')==='viewer'||lb_is_management())return false;
 return lb_is_leader();
}
function lb_stat_staff_rows():array {
 if(isset($GLOBALS['lb_stat_staff_cache']))return $GLOBALS['lb_stat_staff_cache'];
 $root=dirname(__DIR__,2);$reader=$root.'/includes/database_sql_read.php';
 if(is_file($reader))require_once $reader;
 if(function_exists('cds_core_sql_rows')){try{$rows=cds_core_sql_rows('teachers');if(is_array($rows)){$GLOBALS['lb_stat_staff_authoritative']=true;return $GLOBALS['lb_stat_staff_cache']=array_values(array_filter($rows,'is_array'));}}catch(Throwable $error){error_log('[CDS lesson book statistics staff] '.$error->getMessage());}}
 $file=$root.'/data/teachers.json';$GLOBALS['lb_stat_staff_authoritative']=is_file($file);$rows=is_file($file)?json_decode((string)file_get_contents($file),true):[];
 return $GLOBALS['lb_stat_staff_cache']=is_array($rows)?array_values(array_filter($rows,'is_array')):[];
}
function lb_stat_staff_index():array {
 if(isset($GLOBALS['lb_stat_staff_index']))return $GLOBALS['lb_stat_staff_index'];
 $index=['ids'=>[],'names'=>[]];foreach(lb_stat_staff_rows()as$row){$id=trim((string)($row['id']??''));$name=lb_norm((string)($row['name']??''));if($id!=='')$index['ids'][$id][]=$row;if($name!=='')$index['names'][$name][]=$row;}
 return $GLOBALS['lb_stat_staff_index']=$index;
}
function lb_stat_staff_group(array $row):string {
 if(array_key_exists('active',$row)&&empty($row['active']))return '';
 foreach(['to_chuyen_mon','pccm_group']as$key)if(array_key_exists($key,$row))return trim((string)$row[$key]);
 return function_exists('get_teacher_group')?trim((string)get_teacher_group((string)($row['name']??''))):'';
}
function lb_stat_teacher_group(string $name):string {
 $name=trim($name);if($name==='')return '';$index=lb_stat_staff_index();$rows=$index['names'][lb_norm($name)]??[];
 if($rows)return count($rows)===1?lb_stat_staff_group($rows[0]):'';
 if(!empty($GLOBALS['lb_stat_staff_authoritative']))return '';
 return function_exists('get_teacher_group')?trim((string)get_teacher_group($name)):'';
}
function lb_stat_leader_group():string {
 $u=lb_user();$id=trim((string)($u['teacher_id']??''));$index=lb_stat_staff_index();
 if($id!==''){$rows=$index['ids'][$id]??[];return count($rows)===1?lb_stat_staff_group($rows[0]):'';}
 // Editable display names and query parameters never establish team identity.
 return lb_stat_teacher_group((string)($u['teacher_name']??''));
}
function lb_stat_can_view(array $row):bool {
 if(!lb_stat_team_scoped())return lb_can_view($row);
 $group=lb_stat_leader_group();$name=trim((string)($row['actual_teacher']??''));if($name==='')$name=trim((string)($row['scheduled_teacher']??''));
 $other=lb_stat_teacher_group($name);return $group!==''&&$other!==''&&lb_same($group,$other);
}


<?php
/** Read-only class homeroom lookup; never use one shared signer for all classes. */
function lb_print_class_key(string $class): string {
    return lb_norm((string)preg_replace('/^lớp\s+/iu','',trim($class)));
}
function lb_print_homeroom_map(array $classes,array $teachers,string $yearId): array {
    $names=[];foreach($teachers as$t){if(!is_array($t))continue;$names[(string)($t['id']??'')]=trim((string)($t['name']??''));}
    $choices=[];
    foreach($classes as$c){
        if(!is_array($c)||(array_key_exists('active',$c)&&empty($c['active'])))continue;
        $year=trim((string)($c['school_year_id']??''));
        if($year!==''&&($yearId===''||$year!==$yearId))continue;
        $key=lb_print_class_key((string)($c['name']??''));if($key==='')continue;
        $priority=$year!==''?2:1;$name=$names[(string)($c['homeroom_teacher_id']??'')]??'';
        if(!isset($choices[$key])||$priority>$choices[$key]['priority'])$choices[$key]=['priority'=>$priority,'names'=>[$name]];
        elseif($priority===$choices[$key]['priority'])$choices[$key]['names'][]=$name;
    }
    $map=[];foreach($choices as$key=>$choice){$unique=array_unique($choice['names']);$map[$key]=count($unique)===1?(string)reset($unique):'';}return $map;
}
function lb_print_homeroom_names(): array {
    static $map=null;if($map!==null)return $map;
    if(!function_exists('cds_core_sql_rows'))require_once dirname(__DIR__,2).'/includes/database_sql_read.php';
    $root=dirname(__DIR__,2).'/data/';$rows=[];
    foreach(['classes'=>'classes.json','teachers'=>'teachers.json','years'=>'school_years.json']as$entity=>$file){
        $sql=cds_core_sql_rows($entity);$json=is_array($sql)?$sql:load_json($root.$file,[]);$rows[$entity]=is_array($json)?$json:[];
    }
    $yearId='';foreach($rows['years']as$y)if(!empty($y['is_current'])){$yearId=(string)($y['id']??'');break;}
    if($yearId===''&&function_exists('cds_school_year_resolve'))$yearId=(string)(cds_school_year_resolve()['id']??'');
    return $map=lb_print_homeroom_map($rows['classes'],$rows['teachers'],$yearId);
}

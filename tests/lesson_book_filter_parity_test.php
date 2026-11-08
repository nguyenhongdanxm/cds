<?php
require __DIR__.'/support/load_functions.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function lb_norm($s){return strtolower(trim($s));}function lb_same($a,$b){return lb_norm($a)===lb_norm($b);}function lb_subject_match($a,$b){return lb_same($a,$b);}function lb_subject_key($s){return lb_norm($s);}function lb_grade($s){return preg_replace('/\D+/','',$s);}
function lb_record_map_range($a,$b){return [];}
function lb_weeks(){return [['start'=>'2026-09-01','end'=>'2026-09-07']];}
function lb_slots(...$args){return $GLOBALS['rows'];}
function lb_stat_can_view($r){return ($r['class']??'')!=='Hidden';}
function lb_stat_team_scoped(){return false;}
function lb_curriculum_titles_for(...$args){$GLOBALS['lookups']++;return ['1'=>'Title'];}
function lb_curriculum_for(...$args){$GLOBALS['lookups']++;return ['title'=>'Title'];}
function get_teacher_group($t){return 'Group';}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_stat_rows','lb_stat_filter_rows']);require __DIR__.'/support/lesson_book_statistics_filter_reference.php';
$rows=[];foreach(['A','B','C']as$t)foreach(['6A','6B','Hidden']as$c)foreach(['Math','Art']as$s)foreach([true,false]as$signed)$rows[]=['date'=>'2026-09-01','period'=>count($rows)+1,'class'=>$c,'subject'=>$s,'actual_teacher'=>$t,'created_at'=>$signed?'2026-09-01':'','signed_at'=>$signed?'2026-09-01':'','ppct_period'=>1];$GLOBALS['rows']=$rows;$original=serialize($rows);
foreach([['','','','all'],['A','','','all'],['','Art','','completed'],['','','6A','incomplete'],['A','Math','6A','not_saved'],['','','','saved_unsigned'],['None','','','all']]as$args){$GLOBALS['lookups']=0;$expected=reference_lb_stat_rows('2026-09-01','2026-09-07',...$args);$before=$GLOBALS['lookups'];$GLOBALS['lookups']=0;$actual=lb_stat_rows('2026-09-01','2026-09-07',...$args);check($actual===$expected,'Same row order, totals inputs and permissions for every filter');check($GLOBALS['lookups']<=$before,'No extra PPCT work');if($args[0]==='None')check($GLOBALS['lookups']===0,'Rejected rows never load PPCT');}
check(serialize($GLOBALS['rows'])===$original,'Signed source remains unchanged');echo "PASS filter parity: exact rows/order/scope with less PPCT work for excluded rows\n";

<?php
require __DIR__.'/support/load_functions.php';
function check($v,$m){if(!$v)throw new RuntimeException($m);}
function lb_norm($s){return mb_strtolower(trim($s),'UTF-8');}
function lb_same($a,$b){return lb_norm($a)===lb_norm($b);}
function lb_same_subject($a,$b){return lb_same($a,$b);}
load_functions(__DIR__.'/../chuyenmon/includes/lesson_book_store.php',['lb_saved_row_for_base']);
$rows=[];for($i=0;$i<16000;$i++)$rows[]=['slot_id'=>'s'.$i,'date'=>date('Y-m-d',strtotime('2026-08-01 +'.intdiv($i,100).' days')),'period'=>$i%5+1,'session'=>$i%2?'Sáng':'Chiều','class'=>'Lớp '.($i%20),'subject'=>$i%3?'Toán':'Văn','week_key'=>'','book_type'=>'main','signed_at'=>$i%4?'2026-09-01':'','updated_at'=>'2026-09-01','signature_path'=>'signature.png','signed_snapshot'=>['lesson_title'=>'Đầu bài gốc']];
// Include legacy direct ids, manual rows, competing signed/draft rows and conflicting week keys.
foreach([0,1,2,3]as$i){$r=$rows[1234];$r['slot_id']='legacy'.$i;$r['manual_slot']=$i===0;$r['week_key']=$i===1?'other':'';$r['signed_at']=$i===2?'':'2026-10-01';$rows[]=$r;}
$before=hash('sha256',serialize($rows));$saved=[];$index=[];foreach($rows as$r){$saved[$r['slot_id']]=$r;$index[(string)($r['date']??'').'|'.(int)($r['period']??0)][]=$r;}
$fullTime=0;$indexedTime=0;$fullMatched=[];$indexedMatched=[];
foreach([1234,1234,1,22,100,501,9999,15000]as$i){$base=$rows[$i];$base['slot_id']='changed-'.$i;$base['week_key']='current';$start=hrtime(true);$full=lb_saved_row_for_base($saved,$rows,$base,$fullMatched);$fullTime+=hrtime(true)-$start;$start=hrtime(true);$fast=lb_saved_row_for_base($saved,$index[$base['date'].'|'.(int)$base['period']]??[],$base,$indexedMatched);$indexedTime+=hrtime(true)-$start;check($full===$fast&&$fullMatched===$indexedMatched,'Exact signed row, snapshot and match order preserved');}
check(hash('sha256',serialize($rows))===$before,'Source signed records unchanged');
check(count($index[$rows[1234]['date'].'|'.$rows[1234]['period']])<100,'Candidate scan bounded to date and period');
echo 'PASS candidate index: exact legacy matching and signed snapshots; 16000 rows, lookup '.round($fullTime/1e6,2).'ms -> '.round($indexedTime/1e6,2)."ms (synthetic fixture, not hosting)\n";

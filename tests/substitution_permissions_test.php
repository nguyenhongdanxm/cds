<?php
function cds_user_has_group($u,$g){return ($u['role']??'')===$g||in_array($g,$u['groups']??[],true);}
function cds_can_feature($f,$l){return !empty($GLOBALS['wide']);}
function tkb_key($s){return strtolower(trim($s));}
function get_teacher_group($name){return ['Leader'=>'A','Teacher A'=>'A','Teacher B'=>'B'][$name]??'';}
require __DIR__.'/../chuyenmon/includes/substitution_permissions.php';
function check($value){if(!$value)throw new RuntimeException('Permission regression');}
$leader=['name'=>'Leader','groups'=>['totruong']];$teacher=['name'=>'Teacher A','groups'=>['gv']];
check(tkbs_can_approve($leader));check(!tkbs_can_approve($teacher));
foreach(['substitution','fill','makeup'] as $kind){
 check(tkbs_row_in_scope($leader,['registration_type'=>$kind,'absent_teacher'=>'Teacher A','substitute_teacher'=>'Teacher B']));
 check(!tkbs_row_in_scope($leader,['registration_type'=>$kind,'absent_teacher'=>'Teacher B','substitute_teacher'=>'Teacher A']));
}
check(tkbs_row_in_scope($leader,['absent_teacher'=>'','substitute_teacher'=>'Teacher A']));
check(!tkbs_teacher_in_scope(['name'=>'Unknown','groups'=>['totruong']],'Teacher A'));
check(tkbs_row_in_scope(['role'=>'admin'],['absent_teacher'=>'Teacher B']));
$GLOBALS['wide']=true;check(tkbs_teacher_in_scope($teacher,'Teacher B'));
echo "PASS: team leader approval, all three modes, own team, outside team denial, missing team, admin and existing broad permissions.\n";

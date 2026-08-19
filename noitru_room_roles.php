<?php
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/noitru_assignment_store.php';
require_login();
require_perm_level('nt.chiaphong','edit');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Location: '.BASE_URL.'noitru_assign.php?mode=rooms');exit;}
$data=noitru_assignments_data();
$room=trim((string)($_POST['room']??''));
$action=(string)($_POST['action']??'save_room_roles');
$by=(string)((current_user()['name']??current_user()['username']??''));
if($room===''){flash('Không xác định được phòng cần cập nhật.','danger');header('Location: '.BASE_URL.'noitru_assign.php?mode=rooms');exit;}
$members=[];foreach(noitru_assignment_apply(noitru_boarders_live()) as $student)if(trim((string)($student['room_ktx']??''))===$room)$members[(string)($student['id']??'')]=$student;
if($action==='save_room_roles'){
  $leader=(string)($_POST['leader_id']??'');$deputy=(string)($_POST['deputy_id']??'');$teacher=(string)($_POST['teacher_id']??'');
  if($leader!==''&&!isset($members[$leader]))$leader='';if($deputy!==''&&!isset($members[$deputy]))$deputy='';if($leader!==''&&$leader===$deputy)$deputy='';
  $teacherName='';if($teacher!==''){foreach(csdl_teachers_all() as $t)if((string)($t['id']??'')===$teacher){$teacherName=trim((string)($t['name']??''));break;}if($teacherName==='')$teacher='';}
  $data['room_leaders']=is_array($data['room_leaders']??null)?$data['room_leaders']:[];$data['room_teachers']=is_array($data['room_teachers']??null)?$data['room_teachers']:[];
  $data['room_leaders'][$room]=['leader_id'=>$leader,'deputy_id'=>$deputy];
  $data['room_teachers'][$room]=['teacher_id'=>$teacher,'teacher_name'=>$teacherName];
  noitru_assignments_save($data,$by);flash('Đã lưu Trưởng phòng, Phó phòng và giáo viên gắn với '.$room.'.','success');
}
header('Location: '.BASE_URL.'noitru_assign.php?mode=rooms#room-roles');exit;

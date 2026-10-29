<?php
/** Online registration scope, using server-side homeroom assignments only. */
function cmact_online_homeroom_students(array $students,array $classes,array $teachers):array{
 $u=cds_user()??[];if(!$u||($u['role']??'')==='viewer')return[];
 $keys=[];$add=static function($class)use(&$keys):void{foreach(is_array($class)?array_intersect_key($class,array_flip(['id','name','class_id','class_name'])):[$class]as$value)if(is_scalar($value)&&trim((string)$value)!=='')$keys[cmact_key((string)$value)]=true;};
 foreach((array)($u['homeroom_classes']??[])as$class)$add($class);
 $teacherId=trim((string)($u['teacher_id']??''));$teacherName='';
 if($teacherId!==''){if(isset($teachers[$teacherId]))$teacherName=cmact_person_key((string)$teachers[$teacherId]);else$teacherId='';}
 elseif(trim((string)($u['teacher_name']??''))!==''){
  $name=cmact_person_key((string)$u['teacher_name']);$matches=array_keys(array_filter($teachers,fn($value)=>cmact_person_key((string)$value)===$name));
  if(count($matches)===1){$teacherId=(string)$matches[0];$teacherName=$name;}
 }
 foreach($classes as$class){$id=trim((string)($class['homeroom_teacher_id']??''));$name=cmact_person_key((string)($class['homeroom_teacher_name']??$class['homeroom_teacher']??''));
  if(($id!==''&&$teacherId!==''&&$id===$teacherId)||($id===''&&$name!==''&&$teacherName!==''&&$name===$teacherName))$add($class);
 }
 if(!$keys&&(($u['role']??'')==='gvcn'||in_array('gvcn',(array)($u['groups']??[]),true)))foreach((array)($u['classes']??[])as$class)$add($class);
 foreach($classes as$class)if(isset($keys[cmact_key((string)($class['id']??''))])||isset($keys[cmact_key((string)($class['name']??''))]))$add($class);
 return array_filter($students,static function($student)use($keys){$id=trim((string)($student['class_id']??''));return $id!==''?isset($keys[cmact_key($id)]):isset($keys[cmact_key((string)($student['class']??''))]);});
}
function cmact_can_submit_online(array $input,array $students,array $classes,array $teachers,array $groups):bool{
 $u=cds_user()??[];if(!$u||($u['role']??'')==='viewer')return false;
 if(cmact_online_admin())return true;
 if(!empty($input['enrollment_id']))return false;
 $groupId=is_scalar($input['online_group_id']??'')?trim((string)($input['online_group_id']??'')):'invalid';
 if($groupId!=='')return cmact_can_add_online_members(cmact_find_club($groups,$groupId));
 $own=cmact_online_homeroom_students($students,$classes,$teachers);$ids=$input['student_ids']??[];
 if(!is_array($ids)||!$ids)return false;
 foreach($ids as$id)if(!is_scalar($id)||!isset($own[(string)$id]))return false;
 return true;
}
/** New homeroom registrations append under a lock; preserve all unrelated data. */
function cmact_append_homeroom_online(array $rows,array $students,array $classes,array $teachers):bool{
 $own=cmact_online_homeroom_students($students,$classes,$teachers);if(!$rows)return false;
 foreach($rows as$row)if(!isset($own[(string)($row['student_id']??'')])||!empty($row['group_id']))return false;
 return cds_json_update(CM_ACTIVITY_FILE,static function($current)use($rows){
  if(!is_array($current)||(isset($current['online_enrollments'])&&!is_array($current['online_enrollments'])))return false;
  $current['online_enrollments']=array_merge($current['online_enrollments']??[],$rows);return$current;
 },[]);
}


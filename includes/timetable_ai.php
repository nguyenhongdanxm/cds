<?php
/** AI giải thích các phương án do bộ xếp TKB kiểm tra; không thực thi mã AI. */
function ttb_ai_fingerprint(array $data, array $assignments, array $plan): string {
    unset($data['plans'], $data['updated_at'], $data['_workspace']);
    return hash('sha256', serialize([$data,$assignments,$plan]));
}
function ttb_ai_busy_reason(array $data,array $entry,string $key): string {
    $teacher=(string)($entry['teacher']??'');
    if(ttb_blocked($data,$teacher,$key))return 'Ràng buộc giáo viên bận đã lưu: '.$teacher.' tại '.$key;
    foreach((array)($entry['classes']??[])as $class)if(ttb_class_blocked($data,[$class],$key))return 'Ràng buộc lớp bận đã lưu: '.$class.' tại '.$key;
    foreach((array)($data['scope_blocks']??[])as $rule){
        if(($rule['key']??'')!==$key)continue;
        $scope=(string)($rule['scope']??'school');$value=(string)($rule['value']??'');
        foreach((array)($entry['classes']??[])as $class)if($scope==='school'||($scope==='class'&&$class===$value)||($scope==='grade'&&ttb_class_grade($class)===(ltrim($value,'0')?:'0')))
            return 'Ràng buộc không học đã lưu: '.($scope==='school'?'toàn trường':($scope==='grade'?'khối ':'lớp ').$value).' tại '.$key;
    }
    return '';
}
function ttb_ai_collision_details(array $entries): array {
    $teachers=[];$classes=[];$errors=[];
    foreach($entries as $entry){
        $slot=ttb_slot_key((int)$entry['day'],(string)$entry['session'],(int)$entry['period']);
        $label=$entry['subject'].' lớp '.implode('+',(array)$entry['classes']);
        $teacherKey=$entry['teacher'].'|'.$slot;
        if(isset($teachers[$teacherKey])&&$teachers[$teacherKey]['id']!==$entry['activity_id'])
            $errors[]='Trùng lịch dạy '.$entry['teacher'].' tại '.ttb_ai_slot_text($entry).': '.$teachers[$teacherKey]['label'].' và '.$label.' (hai tiết cùng giờ, không phải ràng buộc bận).';
        $teachers[$teacherKey]=['id'=>$entry['activity_id'],'label'=>$label];
        foreach((array)$entry['classes']as $class){
            $classKey=$class.'|'.$slot;
            if(isset($classes[$classKey])&&$classes[$classKey]['id']!==$entry['activity_id'])
                $errors[]='Trùng lịch lớp '.$class.' tại '.ttb_ai_slot_text($entry).': '.$classes[$classKey]['label'].' và '.$label.'.';
            $classes[$classKey]=['id'=>$entry['activity_id'],'label'=>$label];
        }
    }
    return array_values(array_unique($errors));
}
function ttb_ai_errors(array $data,array $assignments,array $plan): array {
    $activities=ttb_apply_lesson_rules(ttb_activities($assignments,$data['groups']),$data);
    $map=array_column($activities,null,'id');$entries=[];$errors=[];$seen=[];
    foreach((array)($plan['entries']??[]) as $entry){
        $id=(string)($entry['activity_id']??'');
        if(isset($seen[$id]))$errors[]='Tiết được xếp nhiều lần: '.$id;
        $seen[$id]=true;
        $entry=array_merge($entry,$map[$id]??[]);
        $entries[]=$entry;
        $day=(int)($entry['day']??0);$session=(string)($entry['session']??'');$period=(int)($entry['period']??0);
        $label=($entry['subject']??'').' · '.implode('+',(array)($entry['classes']??[])).' · '.($entry['teacher']??'').' tại '.$day.' '.$session.' tiết '.$period;
        $max=$session==='Sáng'?(int)$data['settings']['morning_periods']:($session==='Chiều'?(int)$data['settings']['afternoon_periods']:0);
        if(!in_array($day,$data['settings']['days'],true)||$period<1||$period>$max)$errors[]='Khung giờ không hợp lệ: '.$label;
        $error=ttb_plan_slot_error($plan,$day,$session);if($error!=='')$errors[]=$error.': '.$label;
        $busy=ttb_ai_busy_reason($data,$entry,ttb_slot_key($day,$session,$period));
        $error=ttb_manual_slot_error($data,$entry,$day,$session,$period);if($error!=='')$errors[]=($busy!==''?$busy:$error).': '.$label;
    }
    foreach((array)($plan['unplaced']??[])as $entry){$id=(string)($entry['id']??'');if(isset($seen[$id]))$errors[]='Tiết vừa được xếp vừa nằm trong danh sách chưa xếp: '.$id;$seen[$id]=true;}
    foreach($activities as $a)if(!isset($seen[$a['id']]))$errors[]='Thiếu tiết trong phương án: '.$a['subject'].' · '.implode('+',$a['classes']).' · '.$a['teacher'];
    return array_values(array_unique(array_merge($errors,array_values(array_filter(ttb_validate_entries($entries,$activities),fn($message)=>!str_starts_with($message,'Trùng giáo viên ')&&!str_starts_with($message,'Trùng lớp '))),ttb_ai_collision_details($entries),ttb_subject_constraint_errors($data,$entries),ttb_room_conflicts($entries,$data))));
}
function ttb_ai_transition_errors(array $before,array $after): array {
    $inventory=static function(array $plan):array{$ids=[];foreach($plan['entries']??[]as $e)$ids[]=(string)($e['activity_id']??'');foreach($plan['unplaced']??[]as $e)$ids[]=(string)($e['id']??'');sort($ids,SORT_STRING);return $ids;};
    $errors=[];if($inventory($before)!==$inventory($after))$errors[]='Phương án làm thay đổi danh sách hoặc số lượng tiết.';
    $new=array_column($after['entries']??[],null,'activity_id');
    foreach($before['entries']??[]as $entry)if(!empty($entry['locked'])){
        $candidate=$new[$entry['activity_id']]??null;
        if(!$candidate||ttb_ai_slot_text($candidate)!==ttb_ai_slot_text($entry))$errors[]='Phương án thay đổi tiết đã khóa: '.($entry['subject']??'');
    }
    return $errors;
}
function ttb_ai_slot_text(array $entry): string {return ((int)($entry['day']??0)===8?'CN':'Thứ '.(int)($entry['day']??0)).' · '.($entry['session']??'').' · tiết '.(int)($entry['period']??0);}
function ttb_ai_changes(array $before,array $after): array {
    $old=array_column($before['entries']??[],null,'activity_id');$changes=[];
    foreach($after['entries']??[] as $entry){$previous=$old[$entry['activity_id']]??null;if($previous&&ttb_ai_slot_text($previous)===ttb_ai_slot_text($entry))continue;
        $changes[]=['lesson'=>($entry['subject']??'').' · '.implode('+',$entry['classes']??[]).' · '.($entry['teacher']??''),'from'=>$previous?ttb_ai_slot_text($previous):'Chưa xếp','to'=>ttb_ai_slot_text($entry)];}
    return $changes;
}
function ttb_ai_candidates(array $data,array $assignments,array $plan,string $selected,?callable $rank=null,array $targetIds=[],float $seconds=5): array {
    $baseline=ttb_ai_errors($data,$assignments,$plan);$result=[];$slots=ttb_slots($data['settings']);$deadline=microtime(true)+max(0.1,min(5,$seconds));
    $activities=array_column(ttb_apply_lesson_rules(ttb_activities($assignments,$data['groups']),$data),null,'id');
    $targets=[];
    foreach($plan['unplaced']??[] as $u){$id=(string)$u['id'];if(($selected===''||$selected===$id)&&(!$targetIds||in_array($id,$targetIds,true)))$targets[]=['id'=>$id,'entry'=>$activities[$id]??$u,'index'=>null];}
    foreach($plan['entries']??[] as $i=>$e){$id=(string)$e['activity_id'];if(($selected===''||$selected===$id)&&(!$targetIds||in_array($id,$targetIds,true))&&empty($e['locked'])&&empty(($activities[$id]??$e)['fixed_day']))$targets[]=['id'=>$id,'entry'=>array_merge($e,$activities[$id]??[]),'index'=>$i];}
    foreach($targets as $target)foreach($slots as $slot){
        if(microtime(true)>$deadline)break 2;
        $entry=$target['entry'];if($target['index']!==null&&ttb_ai_slot_text($entry)===ttb_ai_slot_text($slot))continue;
        $copy=$plan;$moved=array_merge($entry,['activity_id'=>$target['id'],'day'=>$slot['day'],'session'=>$slot['session'],'period'=>$slot['period']]);
        if($target['index']===null){$copy['entries'][]=$moved;$copy['unplaced']=array_values(array_filter($copy['unplaced'],fn($a)=>(string)$a['id']!==$target['id']));}
        else {
            $copy['entries'][$target['index']]=$moved;
            // Đổi chỗ khi có đúng một tiết cản của lớp/giáo viên/phòng.
            $blockers=[];foreach($plan['entries'] as $j=>$other){if($j===$target['index']||ttb_ai_slot_text($other)!==ttb_ai_slot_text($slot))continue;
                $room=ttb_entry_room($data,$entry);if($other['teacher']===$entry['teacher']||array_intersect($other['classes'],$entry['classes'])||($room!==''&&$room===ttb_entry_room($data,$other)))$blockers[]=$j;}
            if(count($blockers)>1)continue;
            if($blockers){$j=$blockers[0];if(!empty($plan['entries'][$j]['locked']))continue;$copy['entries'][$j]['day']=$entry['day'];$copy['entries'][$j]['session']=$entry['session'];$copy['entries'][$j]['period']=$entry['period'];}
        }
        $errors=ttb_ai_errors($data,$assignments,$copy);if(ttb_ai_transition_errors($plan,$copy))continue;if(array_diff($errors,$baseline))continue;
        $changes=ttb_ai_changes($plan,$copy);$score=count($copy['unplaced']??[])*100000+count($errors)*1000000+ttb_global_penalty($copy['entries'],$data['settings']);
        $score=$rank?$rank($copy):$score;$copy['errors']=$errors;$copy['score']=$score;$key=hash('sha256',serialize($changes));
        $result[$key]=['plan'=>$copy,'changes'=>$changes,'score'=>$score,'errors'=>$errors];
        if(count($result)>30){uasort($result,fn($a,$b)=>$a['score']<=>$b['score']);$result=array_slice($result,0,12,true);}
    }
    usort($result,fn($a,$b)=>$a['score']<=>$b['score']);return array_slice($result,0,6);
}


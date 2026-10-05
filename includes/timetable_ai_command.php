<?php
/** Lệnh ngôn ngữ tự nhiên: chỉ thực hiện chuyển lịch rõ ngày, buổi và phạm vi. */
function ttb_ai_parse_transfer(string $text): array {
    $text=mb_strtolower(trim($text),'UTF-8');
    $text=strtr($text,['đ'=>'d','á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y']);
    $text=preg_replace('/\s+/u',' ',$text);
    $text=trim($text," .!\r\n\t");
    $day='(?:thu\s*(2|3|4|5|6|7|hai|ba|tu|bon|nam|sau|bay)|(?:chu nhat))';
    $pattern='/^(?:hay |toi (?:can|muon) )?chuyen (?:toan bo |tat ca |ca )?(?:buoi (?:hoc )?)?(sang|chieu) '.$day.' (?:sang|qua|den) (?:buoi (?:hoc )?)?(sang|chieu) '.$day.' (?:cua |cho )?(toan truong|lop [a-z0-9]+|khoi (?:6|7|8|9|10|11|12))$/u';
    if(!preg_match($pattern,$text,$m))throw new RuntimeException('Lệnh chưa rõ hoặc chưa hỗ trợ. Hãy ghi một yêu cầu chuyển buổi, ví dụ: Chuyển toàn bộ buổi chiều thứ 4 sang chiều thứ 5 của toàn trường.');
    $days=['hai'=>2,'ba'=>3,'tu'=>4,'bon'=>4,'nam'=>5,'sau'=>6,'bay'=>7];
    $source=$m[2]!==''?($days[$m[2]]??(int)$m[2]):8;$target=$m[4]!==''?($days[$m[4]]??(int)$m[4]):8;
    $scope=$m[5]==='toan truong'?'school':(str_starts_with($m[5],'lop ')?'class':'grade');
    return ['source_day'=>$source,'source_session'=>$m[1]==='sang'?'Sáng':'Chiều','target_day'=>$target,'target_session'=>$m[3]==='sang'?'Sáng':'Chiều','scope'=>$scope,'value'=>$scope==='school'?'':substr($m[5],$scope==='class'?4:5)];
}
function ttb_ai_transfer_command(array $data,array $assignments,array $plan,array $intent): array {
    $scope=$intent['scope'];$value=$intent['value'];
    if($scope==='class'){
        $found=false;foreach(array_unique(array_column($assignments,'class'))as $class)if(mb_strtolower($class,'UTF-8')===$value){$value=$class;$found=true;break;}
        if(!$found)throw new RuntimeException('Không tìm thấy lớp trong phân công hiện tại.');
    }
    $sourceDay=$intent['source_day'];$sourceSession=$intent['source_session'];$day=$intent['target_day'];$session=$intent['target_session'];
    if(!in_array($sourceDay,$data['settings']['days'],true)||!in_array($day,$data['settings']['days'],true))throw new RuntimeException('Ngày nguồn hoặc ngày đích không nằm trong các ngày học đã cài.');
    if($sourceDay===$day&&$sourceSession===$session)throw new RuntimeException('Buổi nguồn và đích trùng nhau.');
    $selected=[];foreach($plan['entries']??[]as $i=>$entry){
        if((int)$entry['day']!==$sourceDay||$entry['session']!==$sourceSession||!ttb_transfer_entry_matches($entry,$scope,$value))continue;
        if(!ttb_transfer_entry_fully_matches($entry,$scope,$value))throw new RuntimeException('Tiết ghép có lớp ngoài phạm vi. Hãy chọn phạm vi bao gồm tất cả lớp của tiết ghép.');
        if(!empty($entry['locked']))throw new RuntimeException('Có tiết đã khóa trong buổi nguồn: '.$entry['subject'].' · '.implode('+',$entry['classes']).'. Hãy mở khóa trước khi chuyển.');
        $selected[]=$i;
    }
    if(!$selected)throw new RuntimeException('Không có tiết học trong buổi nguồn và phạm vi đã yêu cầu.');
    $copy=$plan;ttb_plan_remember($copy,'Lệnh AI: chuyển buổi học');
    $max=$session==='Sáng'?(int)$data['settings']['morning_periods']:(int)$data['settings']['afternoon_periods'];
    foreach($selected as $i){$entry=$copy['entries'][$i];$period=(int)$entry['period'];
        if($period<1||$period>$max)throw new RuntimeException('Buổi đích không có tiết '.$period.'. Chưa chuyển tiết nào.');
        $error=ttb_plan_slot_error($copy,$day,$session);if($error!=='')throw new RuntimeException($error);
        $copy['entries'][$i]['day']=$day;$copy['entries'][$i]['session']=$session;
    }
    $errors=ttb_ai_errors($data,$assignments,$copy);$newErrors=array_values(array_diff($errors,ttb_ai_errors($data,$assignments,$plan)));
    if($newErrors)throw new RuntimeException('Chưa chuyển tiết nào vì: '.implode(' | ',array_slice($newErrors,0,5)));
    if(ttb_ai_transition_errors($plan,$copy))throw new RuntimeException('Phương án thay đổi tiết khóa hoặc số lượng tiết.');
    $copy['errors']=$errors;$copy['score']=count($copy['unplaced']??[])*100000+ttb_global_penalty($copy['entries'],$data['settings']);$copy['updated_at']=date('c');
    return ['plan'=>$copy,'changes'=>ttb_ai_changes($plan,$copy),'count'=>count($selected),'scope'=>$scope==='school'?'toàn trường':($scope==='class'?'lớp '.$value:'khối '.$value)];
}

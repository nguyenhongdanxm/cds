<?php
/** Hiểu lệnh bằng AI; máy chủ chỉ thực hiện các thao tác TKB được định nghĩa. */
function ttb_ai_normalize(string $value): string {
    $value=mb_strtolower(trim($value),'UTF-8');
    $value=strtr($value,['đ'=>'d','á'=>'a','à'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a','ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a','â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a','é'=>'e','è'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e','ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e','í'=>'i','ì'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i','ó'=>'o','ò'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o','ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o','ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o','ú'=>'u','ù'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u','ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u','ý'=>'y','ỳ'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y']);
    return preg_replace('/\s+/u',' ',$value)??$value;
}
function ttb_ai_bind_name(string $wanted,array $names,string $kind): string {
    if($wanted==='')return '';
    $needle=ttb_ai_normalize($wanted);if($kind==='giáo viên')$needle=preg_replace('/^(co|thay|giao vien|gv)\s+/','',$needle);
    $matches=[];foreach(array_unique($names)as $name){$normal=ttb_ai_normalize((string)$name);if($normal===$needle)return (string)$name;if($kind==='giáo viên'&&str_ends_with(' '.$normal,' '.$needle))$matches[]=(string)$name;}
    if(count($matches)===1)return $matches[0];
    throw new RuntimeException(count($matches)>1?'Có nhiều '.$kind.' phù hợp. Hãy ghi họ tên đầy đủ: '.implode(', ',$matches):'Không tìm thấy '.$kind.' “'.$wanted.'” trong dữ liệu TKB.');
}
function ttb_ai_selector(array $selector,array $assignments): array {
    if(array_diff(array_keys($selector),['class','teacher','subject','day','session','period']))throw new RuntimeException('Lệnh có điều kiện chưa hỗ trợ; hãy làm rõ yêu cầu.');
    $out=[];
    foreach(['class'=>'lớp','teacher'=>'giáo viên','subject'=>'môn']as $key=>$kind){
        $value=$selector[$key]??'';if(!is_string($value))throw new RuntimeException('Thông tin '.$kind.' không hợp lệ.');
        if($value!=='')$out[$key]=ttb_ai_bind_name($value,array_column($assignments,$key),$kind);
    }
    if(isset($selector['day'])&&$selector['day']!==null){if(!is_int($selector['day'])||$selector['day']<2||$selector['day']>8)throw new RuntimeException('Thứ trong lệnh không hợp lệ.');$out['day']=$selector['day'];}
    if(isset($selector['session'])&&$selector['session']!==null&&$selector['session']!==''){if(!in_array($selector['session'],['Sáng','Chiều'],true))throw new RuntimeException('Buổi trong lệnh không hợp lệ.');$out['session']=$selector['session'];}
    if(isset($selector['period'])&&$selector['period']!==null){if(!is_int($selector['period'])||$selector['period']<1||$selector['period']>8)throw new RuntimeException('Số tiết không hợp lệ.');$out['period']=$selector['period'];}
    return $out;
}
function ttb_ai_entry_matches(array $entry,array $selector): bool {
    foreach($selector as $key=>$value){if($key==='class'){if(!in_array($value,$entry['classes']??[],true))return false;}elseif(($entry[$key]??null)!==$value)return false;}
    return true;
}
function ttb_ai_unique_entry(array $plan,array $selector): int {
    if(!$selector)throw new RuntimeException('Hãy chỉ rõ lớp, môn hoặc giáo viên và thời gian của tiết cần xử lý.');
    $found=[];foreach($plan['entries']??[]as $i=>$entry)if(ttb_ai_entry_matches($entry,$selector))$found[]=$i;
    if(count($found)!==1){$examples=[];foreach(array_slice($found,0,5)as $i){$e=$plan['entries'][$i];$examples[]=($e['subject']??'').' · '.implode('+',$e['classes']??[]).' · '.$e['teacher'].' · '.ttb_ai_slot_text($e);}
        throw new RuntimeException(!$found?'Không có tiết khớp yêu cầu.':'Có '.count($found).' tiết khớp. Hãy bổ sung thứ, buổi hoặc số tiết: '.implode(' | ',$examples));}
    return $found[0];
}
function ttb_ai_interpret(string $text,array $assignments): array {
    // Lệnh chuyển buổi đầy đủ vẫn hoạt động ngay cả khi dịch vụ AI tạm ngừng.
    try{return ['action'=>'transfer_session','transfer'=>ttb_ai_parse_transfer($text)];}catch(RuntimeException $e){}
    require_once __DIR__.'/ai_service.php';
    $context=['command'=>$text,'classes'=>array_values(array_unique(array_column($assignments,'class'))),'teachers'=>array_values(array_unique(array_column($assignments,'teacher'))),'subjects'=>array_values(array_unique(array_column($assignments,'subject')))];
    $response=cds_ai_call('tkb','command',json_encode($context,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
    if(empty($response['ok']))throw new RuntimeException((string)($response['message']??'Không hiểu được lệnh AI.'));
    $content=trim((string)$response['content']);$content=preg_replace('/^```(?:json)?\s*|\s*```$/u','',$content);
    $intent=json_decode($content,true,32,JSON_THROW_ON_ERROR);if(!is_array($intent))throw new RuntimeException('AI chưa trả về lệnh hợp lệ.');
    return $intent;
}
function ttb_ai_gap_count(array $entries,string $teacher=''): int {
    $groups=[];foreach($entries as $entry){if($teacher!==''&&$entry['teacher']!==$teacher)continue;$groups[$entry['teacher'].'|'.$entry['day'].'|'.$entry['session']][]=(int)$entry['period'];}
    $gaps=0;foreach($groups as $periods){$periods=array_unique($periods);if($periods)$gaps+=max($periods)-min($periods)+1-count($periods);}
    return $gaps;
}
function ttb_ai_finish_command(array $data,array $assignments,array $before,array $after,string $label): array {
    $errors=ttb_ai_errors($data,$assignments,$after);$newErrors=array_values(array_diff($errors,ttb_ai_errors($data,$assignments,$before)));
    if($newErrors)throw new RuntimeException('Chưa thay đổi lịch vì: '.implode(' | ',array_slice($newErrors,0,5)));
    if(ttb_ai_transition_errors($before,$after))throw new RuntimeException('Lệnh làm thay đổi tiết khóa hoặc số lượng tiết.');
    $changes=ttb_ai_changes($before,$after);if(!$changes)return ['changed'=>false,'message'=>'Không có thay đổi phù hợp để thực hiện.','changes'=>[]];
    $state=$before;ttb_plan_remember($state,'Lệnh AI: '.$label);$after['undo_stack']=$state['undo_stack'];
    $after['errors']=$errors;$after['score']=count($after['unplaced']??[])*100000+ttb_global_penalty($after['entries'],$data['settings']);$after['updated_at']=date('c');
    return ['changed'=>true,'plan'=>$after,'changes'=>$changes,'count'=>count($changes),'scope'=>$label,'message'=>$label.': đã xử lý '.count($changes).' tiết; còn '.count($after['unplaced']??[]).' tiết chưa xếp.'];
}
function ttb_ai_execute_intent(array $data,array $assignments,array $plan,array $intent): array {
    $action=$intent['action']??'';
    if(!in_array($action,['transfer_session','move','swap','fill','optimize_gaps','check','clarify'],true))throw new RuntimeException('Chưa hỗ trợ yêu cầu này. Có thể chuyển buổi/tiết, đổi hai tiết, giảm tiết trống, xếp tiết còn thiếu hoặc kiểm tra lỗi.');
    $allowed=['transfer_session'=>['action','transfer'],'move'=>['action','selector','target'],'swap'=>['action','selector','other'],'fill'=>['action','scope','selector'],'optimize_gaps'=>['action','scope','selector'],'check'=>['action'],'clarify'=>['action','question']];
    if(array_diff(array_keys($intent),$allowed[$action]))throw new RuntimeException('Lệnh có yêu cầu chưa hỗ trợ. Chưa thay đổi lịch; hãy nhập một thao tác rõ ràng.');
    if($action==='clarify')throw new RuntimeException(is_string($intent['question']??null)?mb_substr($intent['question'],0,1000):'Hãy bổ sung lớp, giáo viên hoặc thời gian cần xử lý.');
    if($action==='check')return ['changed'=>false,'message'=>'Kết quả kiểm tra: '.count(ttb_ai_errors($data,$assignments,$plan)).' vấn đề; '.count($plan['unplaced']??[]).' tiết chưa xếp.','errors'=>ttb_ai_errors($data,$assignments,$plan),'changes'=>[]];
    if($action==='transfer_session'){
        $transfer=$intent['transfer']??[];if(is_array($transfer)&&array_diff(array_keys($transfer),['scope','value','source_day','source_session','target_day','target_session']))throw new RuntimeException('Lệnh chuyển có điều kiện chưa hỗ trợ.');if(!is_array($transfer)||!in_array($transfer['scope']??'', ['school','class','grade'],true))throw new RuntimeException('Hãy chỉ rõ phạm vi chuyển buổi.');
        foreach(['source_day','target_day']as $key)if(!is_int($transfer[$key]??null)||$transfer[$key]<2||$transfer[$key]>8)throw new RuntimeException('Hãy chỉ rõ thứ nguồn và thứ đích.');
        foreach(['source_session','target_session']as $key)if(!in_array($transfer[$key]??'', ['Sáng','Chiều'],true))throw new RuntimeException('Hãy chỉ rõ buổi nguồn và buổi đích.');
        if(!is_string($transfer['value']??null))throw new RuntimeException('Phạm vi lớp/khối không hợp lệ.');
        if($transfer['scope']==='grade'&&!preg_match('/^(6|7|8|9|10|11|12)$/',$transfer['value']))throw new RuntimeException('Khối không hợp lệ.');
        if($transfer['scope']==='class')$transfer['value']=mb_strtolower(ttb_ai_bind_name($transfer['value'],array_column($assignments,'class'),'lớp'),'UTF-8');
        $result=ttb_ai_transfer_command($data,$assignments,$plan,$transfer);$result['changed']=true;$result['message']='Đã chuyển '.$result['count'].' tiết của '.$result['scope'].'.';return $result;
    }
    if(!is_array($intent['selector']??[]))throw new RuntimeException('Thông tin chọn tiết không hợp lệ.');
    $selector=ttb_ai_selector($intent['selector']??[],$assignments);
    if($action==='move'||$action==='swap'){
        $i=ttb_ai_unique_entry($plan,$selector);$copy=$plan;
        if($action==='move'){
            if(!is_array($intent['target']??null))throw new RuntimeException('Hãy chỉ rõ thứ, buổi, số tiết đích.');
            $target=ttb_ai_selector($intent['target'],$assignments);if(count($target)!==3||!isset($target['day'],$target['session'],$target['period']))throw new RuntimeException('Hãy chỉ rõ thứ, buổi, số tiết đích.');
            foreach($target as $key=>$value)$copy['entries'][$i][$key]=$value;
        }else{
            if(!is_array($intent['other']??null))throw new RuntimeException('Hãy chỉ rõ tiết thứ hai cần đổi.');
            $j=ttb_ai_unique_entry($plan,ttb_ai_selector($intent['other'],$assignments));if($i===$j)throw new RuntimeException('Hai tiết cần đổi đang là cùng một tiết.');
            foreach(['day','session','period']as $key){$copy['entries'][$i][$key]=$plan['entries'][$j][$key];$copy['entries'][$j][$key]=$plan['entries'][$i][$key];}
        }
        // Tiết ghép phải được xử lý rõ toàn bộ các lớp, không ngầm thay lớp khác.
        foreach($action==='swap'?[$i,$j]:[$i]as $index)if(count($plan['entries'][$index]['classes']??[])>1)throw new RuntimeException('Tiết ghép nhiều lớp: hãy dùng Chuyển lịch theo phạm vi bao gồm các lớp của tiết ghép.');
        return ttb_ai_finish_command($data,$assignments,$plan,$copy,$action==='move'?'Chuyển tiết theo lệnh':'Đổi hai tiết theo lệnh');
    }
    $scope=$intent['scope']??'';if(!$selector&&$scope!=='school')throw new RuntimeException('Hãy chỉ rõ giáo viên, lớp hoặc toàn trường.');
    if($action==='optimize_gaps'&&array_diff(array_keys($selector),['teacher']))throw new RuntimeException('Giảm tiết trống hiện hỗ trợ một giáo viên hoặc toàn trường. Hãy chỉ rõ họ tên giáo viên.');
    $deadline=microtime(true)+8;$copy=$plan;$teacher=$selector['teacher']??'';$beforeGaps=ttb_ai_gap_count($plan['entries'],$teacher);$round=0;
    while(microtime(true)<$deadline&&$round++<20){
        $ids=[];$source=$action==='fill'?($copy['unplaced']??[]):($copy['entries']??[]);
        foreach($source as $entry)if(ttb_ai_entry_matches($entry,$selector))$ids[]=(string)($entry[$action==='fill'?'id':'activity_id']??'');
        if(!$ids)break;
        $rank=$action==='fill'?static fn($p)=>count($p['unplaced']??[])*100000+ttb_global_penalty($p['entries'],$data['settings']):static fn($p)=>ttb_ai_gap_count($p['entries'],$teacher)*100000+ttb_global_penalty($p['entries'],$data['settings']);
        $choices=ttb_ai_candidates($data,$assignments,$copy,'',$rank,$ids,max(0.1,min(3,$deadline-microtime(true))));
        if(!$choices)break;$best=$choices[0]['plan'];
        if($action==='fill'&&count($best['unplaced']??[])>=count($copy['unplaced']??[]))break;
        if($action==='optimize_gaps'&&ttb_ai_gap_count($best['entries'],$teacher)>=ttb_ai_gap_count($copy['entries'],$teacher))break;
        $copy=$best;
    }
    $result=ttb_ai_finish_command($data,$assignments,$plan,$copy,$action==='fill'?'Xếp các tiết còn thiếu':'Giảm tiết trống'.($teacher!==''?' cho '.$teacher:''));
    if($action==='optimize_gaps')$result['message'].=' Số tiết trống giữa buổi: '.$beforeGaps.' → '.ttb_ai_gap_count($copy['entries'],$teacher).'.';
    if($action==='fill')$result['message'].=' Còn '.count($copy['unplaced']??[]).' tiết chưa xếp; các tiết chưa có vị trí hợp lệ được giữ lại để xử lý tiếp.';
    return $result;
}

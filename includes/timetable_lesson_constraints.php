<?php
/** Expand a validated batch into the existing one-scope/one-slot schema. No solver changes. */
function tlc_special_batch(array $data, array $assignments, array $input): array {
    $audiences=$input['display_groups']??(isset($input['display_groups_present'])?[]:['school']);
    if(!is_array($audiences)||!$audiences||array_diff($audiences,['school','teacher','student']))throw new RuntimeException('Hãy chọn ít nhất một nhóm hiển thị hợp lệ.');
    $audiences=array_values(array_unique($audiences));
    $scope = (string)($input['scope'] ?? 'school');
    $value = trim((string)($input['scope_value'] ?? ''));
    if (!in_array($scope, ['school','grade','class','classes'], true)) throw new RuntimeException('Hãy chọn đúng phạm vi.');
    $known = array_fill_keys(array_map('strval', array_column($assignments, 'class')), true);
    $targets = [];
    if ($scope === 'classes') {
        $selected = $input['classes'] ?? [];
        if (!is_array($selected) || !$selected || count($selected) > count($known)) throw new RuntimeException('Hãy tích chọn các lớp áp dụng.');
        foreach ($selected as $class) {
            if (!is_string($class) || !isset($known[$class])) throw new RuntimeException('Lớp được chọn không còn trong PCCM.');
            $targets[$class] = ['scope'=>'class','value'=>$class];
        }
    } else {
        if ($scope === 'class' && !isset($known[$value])) throw new RuntimeException('Lớp được chọn không còn trong PCCM.');
        if ($scope === 'grade') {
            $grades = array_map(fn($c)=>ttb_class_grade((string)$c), array_keys($known));
            if ($value === '' || !in_array(ltrim($value,'0') ?: '0', $grades, true)) throw new RuntimeException('Khối được chọn không còn trong PCCM.');
        }
        $targets[] = ['scope'=>$scope,'value'=>$scope === 'school' ? '' : $value];
    }
    $slots = $input['slots'] ?? [['day'=>$input['day']??0,'session'=>$input['session']??'','period'=>$input['period']??0]];
    if (!is_array($slots) || !$slots || count($slots) > 200) throw new RuntimeException('Hãy chọn từ 1 đến 200 ô trên bảng TKB.');
    $validSlots = [];
    foreach ($slots as $slot) {
        if (!is_array($slot) || !is_scalar($slot['day']??null) || !is_scalar($slot['period']??null) || !is_string($slot['session']??null)) throw new RuntimeException('Vị trí không hợp lệ.');
        $day = filter_var($slot['day'], FILTER_VALIDATE_INT);
        $period = filter_var($slot['period'], FILTER_VALIDATE_INT);
        $session = $slot['session'];
        $max = $session === 'Sáng' ? (int)$data['settings']['morning_periods'] : (int)$data['settings']['afternoon_periods'];
        if (!in_array($day, (array)$data['settings']['days'], true) || !in_array($session, ['Sáng','Chiều'], true) || $period < 1 || $period > $max) throw new RuntimeException('Ô được chọn không hợp lệ theo cấu hình TKB.');
        $key = ttb_slot_key($day, $session, $period);
        $validSlots[$key] = ['day'=>$day,'session'=>$session,'period'=>$period,'key'=>$key];
    }
    if (count($targets) * count($validSlots) > 5000) throw new RuntimeException('Mỗi lần áp dụng tối đa 5.000 vị trí lớp.');
    if (!is_string($input['label']??'')) throw new RuntimeException('Nội dung không hợp lệ.');
    $label = mb_substr(trim((string)($input['label']??'')) ?: 'Nghỉ/không xếp tiết', 0, 120, 'UTF-8');
    $rows = [];
    foreach ($targets as $target) foreach ($validSlots as $slot) {
        $id = 'special_'.bin2hex(random_bytes(6));
        $rows[$id] = ['id'=>$id] + $target + ['label'=>$label,'display_groups'=>$audiences] + $slot + ['source'=>'lesson_constraints'];
    }
    return $rows;
}
/** Local, allowlisted return location for lesson constraints only. */
function tlc_return_url(array $input, string $panel): string {
    $query = ['panel'=>$panel];
    foreach (['double_scope','double_value','double_subject','double_q'] as $key) {
        $value = $input['return_'.$key] ?? null;
        if (is_string($value) && strlen($value) <= 300) $query[$key] = $value;
    }
    return '/chuyenmon/tkb_lesson_constraints.php?'.http_build_query($query);
}

function tlc_bulk_fields(): array {
    return ['blocked'=>'Giáo viên bận','class_blocked'=>'Lớp bận','scope_blocks'=>'Ô không học','fixed_lessons'=>'Tiết cố định','special_slots'=>'Hoạt động chung','double_lessons'=>'Hai tiết liền nhau','subject_sequences'=>'Thứ tự môn','subject_rules'=>'Ràng buộc môn','subject_limit_exceptions'=>'Giới hạn riêng của môn','period_overrides'=>'Khối lượng tiết điều chỉnh'];
}
function tlc_bulk_items(array $data): array {
    $items=[];
    foreach(tlc_bulk_fields() as $field=>$title) foreach((array)($data[$field]??[]) as $key=>$row){
        if($field==='scope_blocks'&&!empty($row['special_id']))continue;
        $label=is_array($row)?implode(' · ',array_filter([
            $row['teacher']??'', $row['class']??'', implode('+',(array)($row['classes']??[])),
            $row['subject']??'', $row['label']??'', isset($row['scope'])?($row['scope'].' '.($row['value']??'')):'',
            isset($row['day'])?'Thứ '.$row['day'].' '.($row['session']??'').' tiết '.($row['period']??''):'',
            implode(' → ',(array)($row['subjects']??[]))
        ],fn($v)=>$v!=='')):(string)$row;
        $items[]=['field'=>$field,'key'=>(string)$key,'title'=>$title,'label'=>$label!==''?$label:(string)$key,
            'token'=>json_encode(['field'=>$field,'key'=>(string)$key,'hash'=>hash('sha256',serialize($row))],JSON_UNESCAPED_UNICODE)];
    }
    return $items;
}
function tlc_bulk_delete(array $data, array $input): array {
    $selected=$input['selected_constraints']??null;
    if(($input['confirm_bulk']??'')!=='1'||!is_array($selected)||!$selected||count($selected)>500)throw new RuntimeException('Hãy chọn tối đa 500 ràng buộc và xác nhận xóa.');
    $targets=[];$fields=tlc_bulk_fields();
    foreach($selected as$token){
        if(!is_string($token))throw new RuntimeException('Lựa chọn không hợp lệ.');
        $r=json_decode($token,true);
        if(!is_array($r)||!is_string($r['field']??null)||!isset($fields[$r['field']])||!is_string($r['key']??null)||!is_string($r['hash']??null))throw new RuntimeException('Lựa chọn không hợp lệ.');
        $field=$r['field'];$key=$r['key'];
        if(!array_key_exists($key,(array)($data[$field]??[]))||!hash_equals(hash('sha256',serialize($data[$field][$key])),$r['hash']))throw new RuntimeException('Ràng buộc đã thay đổi. Hãy tải lại trang và chọn lại; chưa xóa mục nào.');
        if($field==='scope_blocks'&&!empty($data[$field][$key]['special_id']))throw new RuntimeException('Hãy xóa hoạt động chung tương ứng để bỏ khóa ô.');
        $targets[$field][$key]=true;
    }
    foreach($targets as$field=>$keys)foreach($keys as$key=>$_){
        if($field==='special_slots'){
            $specialId=(string)($data[$field][$key]['id']??$key);
            $data['scope_blocks']=array_filter((array)$data['scope_blocks'],fn($r)=>(string)($r['special_id']??'')!==$specialId);
        }
        unset($data[$field][$key]);
    }
    $data['scope_blocks']=array_values((array)$data['scope_blocks']);
    return $data;
}

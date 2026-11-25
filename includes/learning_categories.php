<?php
function lhc_scopes(): array {return ['resources'=>'Học liệu','exams'=>'Kiểm tra và thi','games'=>'Tiện ích'];}
function lhc_empty(): array {return ['categories'=>[],'game_categories'=>[],'revision'=>''];}
function lhc_check_store(array $data): array {
    if(!is_array($data['categories']??null)||!is_array($data['game_categories']??null)||!is_string($data['revision']??null))throw new RuntimeException('Dữ liệu chuyên mục không hợp lệ. Vui lòng kiểm tra trước khi cập nhật.');
    foreach($data['categories']as$id=>$row)if(!is_string($id)||!preg_match('/^cat_[a-f0-9]{16}$/D',$id)||!is_array($row)||!isset(lhc_scopes()[$row['section']??''])||!is_string($row['name']??null)||!is_int($row['order']??null)||!is_string($row['description']??null))throw new RuntimeException('Dữ liệu chuyên mục không hợp lệ.');
    foreach($data['game_categories']as$key=>$value)if(!is_string($key)||!is_string($value))throw new RuntimeException('Phân loại tiện ích không hợp lệ.');
    return $data;
}
function lhc_load(): array {
    $file=DATA_PATH.'/learning_categories.json';if(!is_file($file))return lhc_empty();
    $data=json_decode((string)file_get_contents($file),true);if(!is_array($data))throw new RuntimeException('Không đọc được dữ liệu chuyên mục.');return lhc_check_store($data);
}
function lhc_categories(array $store,string $scope): array {
    $rows=array_filter($store['categories'],static fn($row)=>$row['section']===$scope);
    uasort($rows,static fn($a,$b)=>($a['order']<=>$b['order'])?:strcmp(mb_strtolower($a['name']),mb_strtolower($b['name'])));return $rows;
}
function lhc_category_id(array $store,string $scope,string $id): string {return $id!==''&&isset($store['categories'][$id])&&$store['categories'][$id]['section']===$scope?$id:'';}
function lhc_validate_item_category(array $store,string $scope,string $id): string {
    if($id!==''&&lhc_category_id($store,$scope,$id)==='')throw new InvalidArgumentException('Chuyên mục không thuộc phần này hoặc đã bị xóa. Hãy chọn lại.');return $id;
}
/** Pure mutation for the locked store; never changes or deletes learning items. */
function lhc_change(array $store,string $scope,string $action,array $input,array $gameKeys): array {
    if(!isset(lhc_scopes()[$scope]))throw new InvalidArgumentException('Phần chuyên mục không hợp lệ.');
    $id=(string)($input['category_id']??'');
    if($action==='save_category'){
        if($id!==''&&lhc_category_id($store,$scope,$id)==='')throw new InvalidArgumentException('Không tìm thấy chuyên mục trong phần này.');
        $name=trim(preg_replace('/\s+/u',' ',(string)($input['name']??''))??'');$description=trim((string)($input['description']??''));$order=(string)($input['order']??'0');
        if($name===''||mb_strlen($name)>80||mb_strlen($description)>250||preg_match('/[\x00-\x1f]/',$name)||!preg_match('/^[0-9]{1,4}$/D',$order))throw new InvalidArgumentException('Tên chuyên mục cần 1–80 ký tự, mô tả tối đa 250 ký tự, thứ tự từ 0 đến 9999.');
        foreach(lhc_categories($store,$scope)as$key=>$row)if($key!==$id&&mb_strtolower($row['name'])===mb_strtolower($name))throw new InvalidArgumentException('Tên chuyên mục đã có trong phần này.');
        if($id===''){if(count(lhc_categories($store,$scope))>=100)throw new InvalidArgumentException('Mỗi phần tối đa 100 chuyên mục.');$id='cat_'.bin2hex(random_bytes(8));}
        $store['categories'][$id]=['section'=>$scope,'name'=>$name,'description'=>$description,'order'=>(int)$order];
    }elseif($action==='delete_category'){
        if(lhc_category_id($store,$scope,$id)==='')throw new InvalidArgumentException('Không tìm thấy chuyên mục trong phần này.');
        unset($store['categories'][$id]);foreach($store['game_categories']as$key=>$value)if($value===$id)unset($store['game_categories'][$key]);
    }elseif($action==='assign_game_category'){
        if($scope!=='games'||!in_array($input['game_key']??'', $gameKeys,true))throw new InvalidArgumentException('Tiện ích không hợp lệ.');
        lhc_validate_item_category($store,'games',$id);$store['game_categories'][$input['game_key']]=$id;
    }else throw new InvalidArgumentException('Thao tác chuyên mục không hợp lệ.');
    return $store;
}
function lhc_save_change(string $revision,string $scope,string $action,array $input,array $gameKeys): void {
    $error=null;
    $ok=cds_json_update(DATA_PATH.'/learning_categories.json',static function($current)use($revision,$scope,$action,$input,$gameKeys,&$error){try{$current=lhc_check_store($current);if(!hash_equals($current['revision'],$revision))throw new RuntimeException('Chuyên mục đã được cập nhật ở phiên khác. Hãy tải lại trang.');$next=lhc_change($current,$scope,$action,$input,$gameKeys);$next['revision']=bin2hex(random_bytes(16));return $next;}catch(Throwable $e){$error=$e;throw $e;}},lhc_empty());
    if(!$ok)throw $error??new RuntimeException('Không lưu được chuyên mục. Hãy kiểm tra quyền ghi và tải lại trang.');
}

function lhc_shelves(array $store,string $scope,array $items,string $selected=''): array {
    $categories=lhc_categories($store,$scope);$groups=[];
    foreach($categories as$id=>$category)$groups[$id]=['id'=>$id,'name'=>$category['name'],'description'=>$category['description'],'items'=>[]];
    $groups['unassigned']=['id'=>'unassigned','name'=>'Chưa phân chuyên mục','description'=>'Nội dung chưa được xếp vào chuyên mục.','items'=>[]];
    foreach($items as$item){$id=$scope==='games'?lhc_game_category($store,$item['key']):(string)($item['category_id']??'');$key=lhc_category_id($store,$scope,$id)?:'unassigned';$groups[$key]['items'][]=$item;}
    if($selected!=='')return isset($groups[$selected])?[$groups[$selected]]:[];
    if($categories&&empty($groups['unassigned']['items']))unset($groups['unassigned']);
    return array_values($groups);
}
function lhc_url(string $scope,string $category='',string $query='',string $grade=''): string {return '?'.http_build_query(array_filter(['tab'=>$scope,'category'=>$category,'q'=>$query,'grade'=>$grade],static fn($v)=>$v!==''));}

function lhc_game_category(array $store,string $key): string {
    if(array_key_exists($key,$store['game_categories']))return (string)$store['game_categories'][$key];
    if(in_array($key,['egg','ufo'],true))foreach(lhc_categories($store,'games')as$id=>$category)if(mb_strtolower(trim($category['name']))==='tiện ích gọi tên')return $id;
    return '';
}

<?php
/** Read PCCM without loading the CM application or changing its active version. */
require_once __DIR__.'/pccm_release.php';
require_once __DIR__.'/staff_identity.php';
function ab_pccm_source(string $dir): array {
    $out=['available'=>false,'label'=>'Chưa có phân công chuyên môn','version'=>'','rows'=>[],'ignored'=>[],'warnings'=>[],'input_count'=>0];
    if($dir===''||!is_dir($dir))return $out;
    $published=pccm_published_source($dir);
    $active=$published?[]:cds_json_load_for_update($dir.'/active_version.json',[]);$version=trim((string)($active['id']??''));
    if(!$published&&$version==='')foreach(cds_json_load_for_update($dir.'/versions.json',[]) as $v)if(!empty($v['id'])){$version=(string)$v['id'];break;}
    if($published){$version=(string)($published['version']??'');$rows=(array)($published['rows']??[]);$manual=(array)($published['manual']??[]);$configs=(array)($published['configs']??[]);$out['label']='PCCM đã công bố · '.(string)($published['name']??$version);$out['available']=true;}
    else{
        if($version!==''&&!preg_match('/^[a-zA-Z0-9_-]+$/D',$version))throw new RuntimeException('Mã phiên bản PCCM không hợp lệ.');
        $file=$version!==''?$dir.'/assignments_'.$version.'.json':$dir.'/assignments.json';
        $rows=cds_json_load_for_update($file,[]);$manual=pccm_manual_scope(cds_json_load_for_update($dir.'/manual_assignments.json',[]),$version,$version);
        $configs=$version!==''?cds_json_load_for_update($dir.'/program_assignments_'.$version.'.json',[]):[];
        $out['available']=is_file($file)||$version!==''||!empty($manual)||!empty($configs);$out['label']=$out['available']?'PCCM đang làm việc · '.($version?:'hiện tại'):$out['label'];
    }
    $out['version']=$version;
    if($published){
        try{$working=cds_json_load_for_update($dir.'/active_version.json',[]);$workingId=(string)($working['id']??'');
            if($workingId!==''&&$workingId!==$version)$out['warnings'][]='Đang dùng bản đã công bố '.$version.'. Bản đang làm việc '.$workingId.' chưa được áp dụng; hãy công bố bản mới nếu muốn thay đổi quyền.';
            elseif($workingId!==''&&preg_match('/^[a-zA-Z0-9_-]+$/D',$workingId)){
                $draft=cds_json_load_for_update($dir.'/assignments_'.$workingId.'.json',[]);
                $draftManual=pccm_manual_scope(cds_json_load_for_update($dir.'/manual_assignments.json',[]),$workingId,$workingId);
                $draftConfigs=cds_json_load_for_update($dir.'/program_assignments_'.$workingId.'.json',[]);
                if($draft!=$rows||$draftManual!=$manual||$draftConfigs!=$configs)$out['warnings'][]='Bản đang làm việc có thay đổi so với bản đã công bố. Bảng thành tích vẫn theo bản công bố; hãy công bố lại sau khi kiểm tra.';
            }
        }catch(Throwable $e){$out['warnings'][]='Không đọc được bản đang làm việc để đối chiếu; vẫn dùng bản đã công bố.';}
    }
    $ignore=static function(array $row,string $reason)use(&$out){$out['ignored'][]=['teacher'=>(string)($row['teacher']??$row['teacher_name']??''),'class'=>(string)($row['class']??$row['class_name']??''),'subject'=>(string)($row['subject']??''),'reason'=>$reason];};
    foreach(array_merge($rows,$manual) as $r){
        $out['input_count']++;if(!is_array($r)){$ignore([],'Dòng PCCM không đúng cấu trúc.');continue;}if(array_key_exists('active',$r)&&!$r['active']){$ignore($r,'Phân công đã ngừng hoạt động.');continue;}
        $class=trim((string)($r['class']??$r['class_name']??''));$subject=trim((string)($r['subject']??''));
        if($class===''||$subject===''){$ignore($r,'Thiếu lớp hoặc môn học trong PCCM.');continue;}
        // Program-range teachers below replace the ordinary GDĐP row for this class.
        $gdp=preg_replace('/[\s.]+/u','',si_name($subject));
        if(isset($configs[$class])&&in_array($gdp,['gdđp','gddp','giáodụcđịaphương','nộidunggiáodụcđịaphương'],true)){$ignore($r,'Dòng GDĐP được thay bằng giáo viên theo giai đoạn bên dưới.');continue;}
        // Zero weekly periods does not cancel an academic-year teaching assignment.
        $out['rows'][]=array_merge($r,['class'=>$class,'subject'=>$subject]);
    }
    foreach($configs as $class=>$config)foreach((array)($config['ranges']??[]) as $range){
        $out['input_count']++;if(!is_array($range)||(!trim((string)($range['teacher']??''))&&empty($range['teacher_id'])&&empty($range['teacher_code']))){$ignore(['class'=>$class,'subject'=>$config['subject']??'GDĐP'],'Giai đoạn GDĐP chưa có giáo viên.');continue;}
        $out['rows'][]=array_merge($range,['class'=>(string)$class,'subject'=>(string)(($config['subject']??'')?:'GDĐP')]);
    }
    return $out;
}
function ab_pccm_account_teacher(array $u,array $index): ?string {
    $found=[];$explicit=false;
    foreach(['teacher_id','staff_id','person_id','teacher_code','staff_code'] as $field){
        $v=trim((string)($u[$field]??''));if($v==='')continue;$explicit=true;
        $id=si_resolve($v,$index,str_ends_with($field,'_code')?'code':'either');if($id===null)return null;$found[$id]=true;
    }
    if($explicit)return count($found)===1?(string)array_key_first($found):null;
    // Display names can be edited in the personal profile; require a stable staff link.
    return null;
}
function ab_pccm_prepare(array $source,array $teachers,string $year,array $classes=[],array $subjects=[]): array {
    $teachers=array_values(array_filter($teachers,fn($t)=>is_array($t)&&(!array_key_exists('active',$t)||!empty($t['active']))));$index=si_index($teachers);$pairs=[];$rows=[];$ignored=$source['ignored']??[];$classIndex=[];foreach($classes as $c)$classIndex[preg_replace('/\s+/u','',si_name($c))][]=$c;
    foreach($source['rows'] as $row){
        $class=$row['class'];if($classes&&!isset($classes[$class])){
            $canonical=$classIndex[preg_replace('/\s+/u','',si_name($class))]??[];
            if(count($canonical)===1)$class=$canonical[0];else{$ignored[]=array_merge($row,['reason'=>'Lớp không có hoặc đã ngừng hoạt động trong CSDL.']);continue;}
        }
        $subject=$row['subject'];foreach($subjects as $s)if(si_name($s)===si_name($subject)){$subject=$s;break;}
        $matches=[];foreach($teachers as $teacher)if(si_match($row,(string)$teacher['id'],$index)!=='')$matches[]=(string)$teacher['id'];
        $id=count($matches)===1?$matches[0]:null;
        $rows[]=['teacher_id'=>$id,'teacher'=>(string)($row['teacher']??$row['teacher_name']??($id!==null?($teachers[array_search($id,array_column($teachers,'id'),true)]['name']??''):'')),'class'=>$class,'subject'=>$subject,'reason'=>$id!==null?'':ab_pccm_row_reason($row,$index)];
        if($id!==null)$pairs[$id][$class][$subject]=true;
    }
    $ctx=['available'=>$source['available'],'label'=>$source['label'],'version'=>$source['version'],'year'=>$year,'index'=>$index,'pairs'=>$pairs,'rows'=>$rows,'ignored'=>$ignored,'warnings'=>$source['warnings']??[],'input_count'=>$source['input_count']??count($source['rows'])];
    $GLOBALS['ab_pccm_context']=$ctx;return $ctx;
}
function ab_pccm_context(): array {return $GLOBALS['ab_pccm_context']??['available'=>false,'label'=>'Chưa có phân công chuyên môn','version'=>'','year'=>'','index'=>['id'=>[],'code'=>[],'name'=>[]],'pairs'=>[],'rows'=>[]];}
function ab_pccm_pairs(array $u): array {$c=ab_pccm_context();$id=ab_pccm_account_teacher($u,$c['index']);return $id!==null?($c['pairs'][$id]??[]):[];}
function ab_pccm_pair(array $u,string $class,string $subject): bool {return !empty(ab_pccm_pairs($u)[$class][$subject]);}
/** Navigation hint only; the tool validates against unique CSDL identity before reading points. */
function ab_pccm_navigation_hint(array $u): bool {
    if(($u['role']??'')==='viewer'||($u['permission_overrides']['hl.thanhtich']??'')==='none'||!defined('PCCM_DATA_PATH'))return false;
    try{$source=ab_pccm_source(PCCM_DATA_PATH);$name=si_staff_name((string)(trim((string)($u['teacher_name']??''))!==''?$u['teacher_name']:($u['name']??'')));foreach($source['rows'] as $r){
        foreach(['teacher_id','teacher_code'] as $field)if(!empty($u[$field])&&isset($r[$field])&&(string)$u[$field]===(string)$r[$field])return true;
        if($name!==''&&$name===si_staff_name((string)($r['teacher']??$r['teacher_name']??'')))return true;
    }}catch(Throwable $e){return false;}return false;
}

function ab_pccm_row_reason(array $row,array $index): string {
    foreach(['teacher_id','person_id','staff_id','teacher_code','person_code','staff_code'] as $key)if(trim((string)($row[$key]??''))!=='')return 'Mã/ID giáo viên không khớp duy nhất với giáo viên đang hoạt động trong CSDL (hoặc các mã mâu thuẫn).';
    $name=si_staff_name((string)($row['teacher']??$row['teacher_name']??''));$ids=$index['name']['v:'.$name]??[];
    return count($ids)>1?'Trùng tên giáo viên trong CSDL; cần gán mã/ID để phân biệt.':($name===''?'PCCM chưa có tên hoặc mã giáo viên.':'Tên giáo viên PCCM không khớp CSDL hoặc giáo viên đã ngừng hoạt động.');
}
function ab_pccm_account_reason(array $u): string {
    if(($u['role']??'')==='admin')return 'Quản trị viên: được quản lý tất cả lớp–môn. Xem đối chiếu PCCM tại tab Phân quyền.';
    if(($u['permission_overrides']['hl.thanhtich']??'')==='none')return 'Tài khoản đang bị đặt quyền Bảng thành tích: Không truy cập.';
    $ctx=ab_pccm_context();if(!$ctx['available'])return 'Chưa có nguồn PCCM để đồng bộ.';
    $id=ab_pccm_account_teacher($u,$ctx['index']);
    if($id===null){$explicit=false;foreach(['teacher_id','staff_id','person_id','teacher_code','staff_code'] as $key)if(!empty($u[$key]))$explicit=true;
        return $explicit?'Mã/ID liên kết tài khoản không khớp duy nhất với CSDL hoặc giáo viên đã ngừng hoạt động.':'Tài khoản chưa liên kết mã/ID giáo viên CSDL. Quản trị chọn đúng giáo viên rồi lưu tại Tài khoản và quyền.';}
    if(empty($ctx['pairs'][$id]))return 'Đã khớp giáo viên CSDL nhưng chưa có lớp–môn trong nguồn PCCM đang áp dụng.';
    if(($u['role']??'')==='viewer'||($u['permission_overrides']['hl.thanhtich']??'')==='view')return 'Đã nhận PCCM; tài khoản đang ở chế độ chỉ xem.';
    return 'Đã khớp giáo viên và nhận quyền từ PCCM.';
}

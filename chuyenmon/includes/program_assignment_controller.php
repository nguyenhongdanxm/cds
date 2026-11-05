<?php
if(empty($_SESSION['gdp_csrf']))$_SESSION['gdp_csrf']=bin2hex(random_bytes(24));
if(($_SERVER['REQUEST_METHOD']??'')==='POST'&&in_array((string)($_POST['action']??''),['program_save','program_disable'],true)){
    try{
        if(!hash_equals($_SESSION['gdp_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('Phiên thao tác hết hạn, hãy tải lại trang.');
        if(function_exists('cds_can_feature')&&!cds_can_feature('cm.pccm','edit'))throw new RuntimeException('Bạn chưa có quyền sửa phân công.');
        $version=(string)get_active_version_id();if($version===''||$version!==($_POST['version_id']??''))throw new RuntimeException('Phiên bản đã thay đổi, hãy tải lại trang.');
        $classes=array_values(array_unique(array_map('strval',(array)($_POST['classes']??[]))));if(!$classes)throw new RuntimeException('Chọn ít nhất một lớp.');
        foreach($classes as$c)if(!in_array($c,get_classes(),true))throw new RuntimeException('Lớp không hợp lệ.');
        $file=gdp_file(DATA_PATH,$version);$fingerprint=hash('sha256',json_encode(gdp_load(DATA_PATH,$version)));
        if(!hash_equals($fingerprint,(string)($_POST['fingerprint']??'')))throw new RuntimeException('Phân công vừa được cập nhật ở cửa sổ khác, hãy tải lại trang.');
        $ranges=[];$subject=trim((string)($_POST['subject']??''));
        if($_POST['action']==='program_save'){
            if(!gdp_subject($subject)||!array_key_exists($subject,get_subjects()))throw new RuntimeException('Chọn môn GDĐP có trong danh mục.');
            $teacherNames=gdp_teacher_names(get_teachers());$rows=[];
            foreach((array)($_POST['teacher']??[])as$i=>$teacher)$rows[]=['teacher'=>$teacher,'from'=>$_POST['from'][$i]??'','to'=>$_POST['to'][$i]??''];
            $ranges=gdp_validate($rows,$teacherNames);
        }
        $ok=cds_json_update($file,function($current)use($classes,$subject,$ranges,$fingerprint){
            if(!hash_equals($fingerprint,hash('sha256',json_encode((array)$current))))throw new RuntimeException('Dữ liệu vừa thay đổi, hãy tải lại trang.');
            foreach($classes as$c){if($_POST['action']==='program_disable')unset($current[$c]);else$current[$c]=['subject'=>$subject,'ranges'=>$ranges];}return$current;
        },[]);
        if(!$ok)throw new RuntimeException('Không lưu được phân công.');
        flash('Đã cập nhật '.count($classes).' lớp. TKB đã công bố và sổ đã ký được giữ nguyên.','success');
        header('Location: '.BASE_URL.'them.php?program=1');exit;
    }catch(Throwable $e){$GLOBALS['gdp_form_error']=$e->getMessage();$_GET['program']='1';}
}

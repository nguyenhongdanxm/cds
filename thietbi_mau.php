<?php
require_once __DIR__.'/includes/auth.php';
require_login();
$user=current_user()??[];
if(($user['role']??'')!=='admin'&&!can_perm_level('tb.danhmuc','edit')&&!can_perm_level('tb.nguongoc','edit')){http_response_code(403);exit('Không có quyền tải mẫu dữ liệu thiết bị.');}
require_once __DIR__.'/includes/library_book_excel.php';
require_once __DIR__.'/includes/library_equipment_excel.php';
require_once __DIR__.'/includes/library_equipment_template.php';
$path=null;
try{
    $data=load_json(DATA_PATH.'/library_equipment.json',[]);
    $path=lib_equipment_template_file((array)($data['equipment']??[]));
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="mau-thiet-bi-co-du-lieu-'.date('Ymd').'.xlsx"');
    header('Content-Length: '.filesize($path));header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');readfile($path);
}catch(Throwable$e){error_log('[CDS equipment template] '.$e->getMessage());http_response_code(503);echo 'Chưa xuất được mẫu dữ liệu thiết bị. Vui lòng thử lại hoặc kiểm tra ZipArchive/DOM trên hosting.';}
finally{if($path!==null)@unlink($path);}

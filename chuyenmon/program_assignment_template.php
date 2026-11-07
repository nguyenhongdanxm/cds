<?php
require_once __DIR__.'/includes/functions.php';
require_login();
if(!cds_can_feature('cm.pccm','edit')){http_response_code(403);exit('Bạn chưa có quyền sửa phân công.');}
require_once dirname(__DIR__).'/includes/program_assignment_excel.php';
require_once __DIR__.'/includes/program_excel_template.php';
$target=null;
try{
    $version=(string)get_active_version_id();if($version==='')throw new RuntimeException('Chọn phiên bản phân công trước.');
    $target=tempnam(sys_get_temp_dir(),'gdp-xlsx-');
    if($target===false||!gdp_excel_template(gdp_excel_context(),gdp_load(DATA_PATH,$version),(string)(get_version($version)['name']??$version),$target))throw new RuntimeException('Không tạo được mẫu Excel. Kiểm tra PHP ZipArchive.');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="phan-cong-GDDP-1-35.xlsx"');header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Content-Length: '.filesize($target));readfile($target);
}catch(Throwable $error){http_response_code(400);header('Content-Type: text/plain; charset=UTF-8');echo $error->getMessage();}finally{if(is_string($target)&&is_file($target))unlink($target);}

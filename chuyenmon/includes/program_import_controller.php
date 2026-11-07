<?php
$gdpImportAction=(string)($_POST['action']??'');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!in_array($gdpImportAction,['program_import_preview','program_import_commit'],true))return;
require_once dirname(__DIR__,2).'/includes/program_assignment_excel.php';
try{
    if(!cds_can_feature('cm.pccm','edit'))throw new RuntimeException('Bạn chưa có quyền sửa phân công.');
    if(!hash_equals((string)($_SESSION['gdp_csrf']??''),(string)($_POST['csrf']??'')))throw new RuntimeException('Phiên thao tác hết hạn, hãy tải lại trang.');
    $version=(string)get_active_version_id();
    if($version===''||$version!==(string)($_POST['version_id']??''))throw new RuntimeException('Phiên bản đã đổi. Hãy tải lại trang và nhập lại.');
    if(!empty(pccm_release_state(DATA_PATH)['locked'][$version]))throw new RuntimeException('Phiên bản đã khóa. Mở khóa trước khi nhập.');
    $context=gdp_excel_context();
    if($gdpImportAction==='program_import_preview'){
        unset($_SESSION['gdp_excel_preview']);
        $upload=$_FILES['gdp_excel']??[];
        if(($upload['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new RuntimeException('Chọn file Excel .xlsx để xem trước (tối đa 5 MB).');
        if(strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION))!=='xlsx')throw new RuntimeException('Chỉ nhận file .xlsx. Với file .xls, hãy lưu lại thành .xlsx.');
        $preview=gdp_excel_preview(gdp_excel_read($upload['tmp_name']),$context,trim((string)($_POST['import_subject']??'')));
        $preview['version']=$version;$preview['fingerprint']=hash('sha256',json_encode(gdp_load(DATA_PATH,$version)));$preview['context_hash']=gdp_excel_context_hash($context);$preview['created']=time();$preview['token']=bin2hex(random_bytes(24));
        $GLOBALS['gdp_excel_preview']=$preview;
        if(!$preview['error_count'])$_SESSION['gdp_excel_preview']=$preview;
    }else{
        $preview=$_SESSION['gdp_excel_preview']??[];
        if(!$preview||!hash_equals((string)$preview['token'],(string)($_POST['preview_token']??''))||$preview['version']!==$version||time()-(int)$preview['created']>1800)throw new RuntimeException('Bản xem trước hết hạn hoặc đã thay đổi. Hãy tải file và xem trước lại.');
        if(!hash_equals($preview['context_hash'],gdp_excel_context_hash($context)))throw new RuntimeException('Danh mục giáo viên, lớp hoặc môn vừa thay đổi. Hãy xem trước lại.');
        gdp_excel_apply(DATA_PATH,$version,$preview['configs'],$preview['fingerprint']);
        unset($_SESSION['gdp_excel_preview']);
        flash('Đã nhập GDĐP cho '.count($preview['configs']).' lớp. TKB lấy phân công mới sau khi khóa và công bố phiên bản.','success');
        header('Location: '.BASE_URL.'them.php?program=1');exit;
    }
}catch(Throwable $error){$GLOBALS['gdp_excel_error']=$error->getMessage();}
$_GET['program']='1';

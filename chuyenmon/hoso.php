<?php
$page_title='Hồ sơ chuyên môn';
require_once __DIR__.'/includes/functions.php';
require_login();
require_once __DIR__.'/includes/academic_drive.php';
header('Cache-Control: no-store');
$admin=(cds_user()['role']??'')==='admin';$error='';$notice='';$config=['id'=>'','resource_key'=>'','name'=>'','revision'=>''];
try{$config=acd_config();}catch(Throwable $ex){$error=$ex->getMessage();}
if(empty($_SESSION['academic_drive_csrf']))$_SESSION['academic_drive_csrf']=bin2hex(random_bytes(24));
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if(!$admin){http_response_code(403);exit('Chỉ quản trị hệ thống được thay đổi thư mục.');}
    try{
        if($error!=='')throw new RuntimeException($error);
        if(!is_string($_POST['csrf']??null)||!hash_equals($_SESSION['academic_drive_csrf'],$_POST['csrf']))throw new RuntimeException('Phiên thao tác không hợp lệ. Hãy tải lại trang.');
        if(!is_string($_POST['revision']??null))throw new InvalidArgumentException('Thiếu phiên bản cấu hình.');
        $action=$_POST['action']??'';
        if($action==='link_folder'){
            if(!is_string($_POST['folder_link']??null))throw new InvalidArgumentException('Liên kết thư mục không hợp lệ.');
            $next=acd_folder_link($_POST['folder_link']);$keys=[$next['id']=>$next['resource_key']];$item=acd_metadata($next['id'],acd_token(),$keys);
            if(($item['mimeType']??'')!=='application/vnd.google-apps.folder')throw new InvalidArgumentException('Liên kết phải trỏ tới một thư mục Google Drive.');
            $next['resource_key']=$keys[$next['id']]??$next['resource_key'];$next['name']=(string)($item['name']??'');
        }elseif($action==='unlink_folder'){$next=['id'=>'','resource_key'=>'','name'=>''];}
        else throw new InvalidArgumentException('Thao tác không hợp lệ.');
        acd_config_save($next,$_POST['revision']);unset($_SESSION['academic_drive_keys']);
        header('Location: '.BASE_URL.'hoso.php?saved=1',true,303);exit;
    }catch(Throwable $ex){$error=$ex->getMessage();http_response_code(400);}
}
if(isset($_GET['api'])){
    header('Content-Type: application/json; charset=utf-8');
    try{
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'||($_GET['api']??'')!=='list')throw new InvalidArgumentException('Yêu cầu không hợp lệ.');
        if($error!=='')throw new RuntimeException($error);
        $args=[];foreach(['folder'=>$config['id'],'sort'=>'name','q'=>'','cursor'=>'']as$key=>$default){$args[$key]=$_GET[$key]??$default;if(!is_string($args[$key]))throw new InvalidArgumentException('Bộ lọc không hợp lệ.');}
        $scope=hash('sha256',$config['revision'].'|'.$config['id']);$saved=$_SESSION['academic_drive_keys']??[];
        $keys=($saved['scope']??'')===$scope?(array)($saved['keys']??[]):[];
        $data=acd_browse($config,$args['folder'],$args['sort'],$args['q'],$args['cursor'],acd_token(),$keys);
        $_SESSION['academic_drive_keys']=['scope'=>$scope,'keys'=>$keys];
        echo json_encode(['ok'=>true]+$data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    }catch(Throwable $ex){http_response_code($ex instanceof InvalidArgumentException?400:502);echo json_encode(['ok'=>false,'message'=>$ex->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}
    exit;
}
require __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="assets/academic-drive.css?v=1">
<section id="academic-drive" data-root="<?=e($config['id'])?>">
    <h1 class="h3"><i class="bi bi-folder2-open"></i> Hồ sơ chuyên môn</h1>
    <p class="text-muted">Duyệt thư mục và xem tài liệu trực tiếp từ Google Drive của nhà trường.</p>
    <?php if($error!==''):?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif;?>
    <?php if(($_GET['saved']??'')==='1'):?><div class="alert alert-success" role="status">Đã cập nhật liên kết thư mục.</div><?php endif;?>
    <?php if($admin):?>
    <details class="card p-3 mb-3" <?=!$config['id']?'open':''?>>
        <summary class="fw-semibold">Cấu hình thư mục Google Drive</summary>
        <form method="post" action="hoso.php" class="mt-3">
            <input type="hidden" name="csrf" value="<?=e($_SESSION['academic_drive_csrf'])?>">
            <input type="hidden" name="revision" value="<?=e($config['revision'])?>">
            <label class="form-label" for="acd-folder-link">Liên kết thư mục gốc</label>
            <input class="form-control" type="url" id="acd-folder-link" name="folder_link" placeholder="https://drive.google.com/drive/folders/…" value="<?=e($config['id']?acd_drive_url($config['id'],true,$config['resource_key']):'')?>">
            <p class="form-text">Tài khoản Google đã kết nối với hệ thống cần quyền đọc thư mục này. Người được cấp quyền Hồ sơ chuyên môn sẽ thấy tên thư mục và tài liệu; quyền mở nội dung do Google Drive kiểm soát. Không cần bật chia sẻ công khai.</p>
            <button class="btn btn-primary" name="action" value="link_folder">Lưu thư mục</button>
            <?php if($config['id']):?><button class="btn btn-outline-secondary" name="action" value="unlink_folder" formnovalidate>Gỡ liên kết</button><?php endif;?>
            <div class="form-text">Gỡ liên kết chỉ bỏ thư mục khỏi mục Hồ sơ chuyên môn.</div>
        </form>
    </details>
    <?php endif;?>
    <?php if(!$config['id']):?><div class="alert alert-info">Chưa liên kết thư mục Hồ sơ chuyên môn. Quản trị hệ thống có thể gắn thư mục Google Drive tại đây.</div>
    <?php else:?>
    <div class="card p-3">
        <nav id="acd-breadcrumbs" aria-label="Đường dẫn thư mục"></nav>
        <div class="acd-toolbar">
            <form id="acd-search" class="d-flex gap-2"><input id="acd-query" class="form-control" type="search" maxlength="150" aria-label="Tìm trong thư mục hiện tại" placeholder="Tìm trong thư mục hiện tại"><button class="btn btn-outline-primary">Tìm</button></form>
            <select id="acd-sort" class="form-select" aria-label="Sắp xếp"><option value="name">Tên A → Z</option><option value="name_desc">Tên Z → A</option><option value="updated">Cập nhật mới nhất</option></select>
            <button type="button" id="acd-refresh" class="btn btn-outline-primary">Làm mới</button>
            <a id="acd-source" class="btn btn-outline-secondary" href="<?=e(acd_drive_url($config['id'],true,$config['resource_key']))?>" target="_blank" rel="noopener noreferrer">Mở thư mục trên Drive</a>
        </div>
        <p class="form-text">Tên và cấu trúc lấy từ Drive; thư mục hiển thị trước tài liệu. Sắp xếp áp dụng theo lựa chọn ở trên.</p>
        <p id="acd-status" role="status" aria-live="polite"></p>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tên thư mục / tài liệu</th><th>Cập nhật</th><th>Nguồn</th></tr></thead><tbody id="acd-items"></tbody></table></div>
        <button type="button" id="acd-more" class="btn btn-outline-primary align-self-start" hidden>Xem thêm</button>
    </div>
    <dialog id="acd-preview" aria-labelledby="acd-preview-title"><div class="acd-preview-header"><h2 id="acd-preview-title" class="h5 mb-0"></h2><button id="acd-close" class="btn btn-outline-secondary" type="button">Đóng</button></div><p class="small">Nếu không xem được, hãy đăng nhập tài khoản Google có quyền truy cập hoặc <a id="acd-preview-source" target="_blank" rel="noopener noreferrer">mở tài liệu trên Drive</a>.</p><iframe id="acd-frame" title="Xem tài liệu từ Google Drive" referrerpolicy="no-referrer" allow="fullscreen"></iframe></dialog>
    <script src="assets/academic-drive.js?v=1" defer></script>
    <?php endif;?>
</section>
<?php require __DIR__.'/includes/footer.php';?>

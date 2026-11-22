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
        if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'||!in_array($_GET['api']??'',['list','file'],true))throw new InvalidArgumentException('Yêu cầu không hợp lệ.');
        if($error!=='')throw new RuntimeException($error);
        $scope=hash('sha256',$config['revision'].'|'.$config['id']);$saved=$_SESSION['academic_drive_keys']??[];
        $keys=($saved['scope']??'')===$scope?(array)($saved['keys']??[]):[];
        if($_GET['api']==='file'){
            if(!is_string($_GET['id']??null)||!acd_id($_GET['id'])||!in_array($_GET['download']??'0',['0','1'],true))throw new InvalidArgumentException('Tài liệu không hợp lệ.');
            $token=acd_token();session_write_close();
            $content=acd_content($config,$_GET['id'],$token,$keys);
            try{
                header('Content-Type: '.$content['mime']);header('X-Content-Type-Options: nosniff');header('Cross-Origin-Resource-Policy: same-origin');
                header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'self\'; sandbox');
                $disposition=$content['inline']&&($_GET['download']??'0')!=='1'?'inline':'attachment';
                header("Content-Disposition: ".$disposition."; filename*=UTF-8''".rawurlencode($content['name']));
                header('Content-Length: '.$content['size']);fpassthru($content['stream']);
            }finally{fclose($content['stream']);}
            exit;
        }
        $args=[];foreach(['folder'=>$config['id'],'sort'=>'name','q'=>'','cursor'=>'']as$key=>$default){$args[$key]=$_GET[$key]??$default;if(!is_string($args[$key]))throw new InvalidArgumentException('Bộ lọc không hợp lệ.');}
        $data=acd_browse($config,$args['folder'],$args['sort'],$args['q'],$args['cursor'],acd_token(),$keys);
        $_SESSION['academic_drive_keys']=['scope'=>$scope,'keys'=>$keys];
        echo json_encode(['ok'=>true]+$data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    }catch(Throwable $ex){http_response_code($ex instanceof InvalidArgumentException?400:502);echo json_encode(['ok'=>false,'message'=>$ex->getMessage()],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}
    exit;
}
require __DIR__.'/includes/header.php';
?>
<link rel="stylesheet" href="assets/academic-drive.css?v=2">
<section id="academic-drive" data-root="<?=e($config['id'])?>">
    <header class="acd-heading"><div><span class="acd-eyebrow">THƯ VIỆN NHÀ TRƯỜNG</span><h1><i class="bi bi-collection" aria-hidden="true"></i> Hồ sơ chuyên môn</h1><p>Tra cứu, sắp xếp và khai thác hồ sơ chuyên môn tập trung.</p></div><span class="acd-heading-icon" aria-hidden="true"><i class="bi bi-journals"></i></span></header>
    <?php if($error!==''):?><div class="alert alert-danger" role="alert"><?=e($error)?></div><?php endif;?>
    <?php if(($_GET['saved']??'')==='1'):?><div class="alert alert-success" role="status">Đã cập nhật cấu hình thư viện.</div><?php endif;?>
    <?php if(!$config['id']):?><div class="acd-empty"><i class="bi bi-folder-plus" aria-hidden="true"></i><h2>Thư viện đang được chuẩn bị</h2><p>Quản trị hệ thống có thể thiết lập thư mục hồ sơ bên dưới.</p></div>
    <?php else:?>
    <div class="acd-library">
        <nav id="acd-breadcrumbs" aria-label="Đường dẫn thư mục"></nav>
        <div class="acd-toolbar">
            <form id="acd-search"><label class="visually-hidden" for="acd-query">Tìm trong thư mục hiện tại</label><i class="bi bi-search" aria-hidden="true"></i><input id="acd-query" type="search" maxlength="150" placeholder="Tìm hồ sơ trong thư mục này…"><button type="submit">Tìm kiếm</button></form>
            <select id="acd-sort" aria-label="Sắp xếp"><option value="name">Tên A → Z</option><option value="name_desc">Tên Z → A</option></select>
            <button type="button" id="acd-refresh" class="acd-tool" aria-label="Làm mới thư viện" title="Làm mới"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
            <div class="acd-view-switch" role="group" aria-label="Kiểu hiển thị"><button id="acd-grid" class="acd-tool" type="button" aria-label="Dạng lưới" aria-pressed="true"><i class="bi bi-grid" aria-hidden="true"></i></button><button id="acd-list" class="acd-tool" type="button" aria-label="Dạng danh sách" aria-pressed="false"><i class="bi bi-list-ul" aria-hidden="true"></i></button></div>
        </div>
        <div class="acd-results-heading"><h2 id="acd-folder-title">Danh mục hồ sơ</h2><span id="acd-count"></span></div>
        <p id="acd-status" role="status" aria-live="polite"></p>
        <div id="acd-items" class="acd-grid" aria-label="Thư mục và tài liệu"></div>
        <div id="acd-empty" class="acd-empty" hidden><i class="bi bi-folder2-open" aria-hidden="true"></i><h3>Chưa có hồ sơ phù hợp</h3><p>Thử từ khóa khác hoặc quay lại thư mục cha.</p></div>
        <button type="button" id="acd-more" class="btn btn-outline-primary" hidden>Xem thêm hồ sơ</button>
    </div>
    <dialog id="acd-preview" aria-labelledby="acd-preview-title"><div class="acd-preview-header"><div><span class="acd-eyebrow">XEM HỒ SƠ</span><h2 id="acd-preview-title"></h2></div><div class="acd-preview-actions"><a id="acd-download" class="btn btn-primary">Tải tài liệu</a><button id="acd-close" class="btn btn-outline-secondary" type="button">Đóng</button></div></div><p id="acd-preview-status" role="status" aria-live="polite"></p><iframe id="acd-frame" title="Nội dung hồ sơ" hidden></iframe><div id="acd-no-preview" class="acd-empty" hidden><i class="bi bi-file-earmark-arrow-down" aria-hidden="true"></i><h3>Tài liệu sẵn sàng tải xuống</h3><p>Định dạng này chưa hỗ trợ xem trực tiếp. Chọn “Tải tài liệu” để mở bằng ứng dụng phù hợp.</p></div></dialog>
    <script src="assets/academic-drive.js?v=2" defer></script>
    <?php endif;?>
    <?php if($admin):?>
    <details class="acd-settings" <?=!$config['id']?'open':''?>>
        <summary><i class="bi bi-gear" aria-hidden="true"></i> Quản lý thư viện</summary>
        <form method="post" action="hoso.php" class="mt-3">
            <input type="hidden" name="csrf" value="<?=e($_SESSION['academic_drive_csrf'])?>"><input type="hidden" name="revision" value="<?=e($config['revision'])?>">
            <label class="form-label" for="acd-folder-link">Liên kết thư mục lưu trữ</label>
            <input class="form-control" type="url" id="acd-folder-link" name="folder_link" placeholder="Dán liên kết thư mục nguồn" value="<?=e($config['id']?acd_drive_url($config['id'],true,$config['resource_key']):'')?>">
            <p class="form-text">Tài khoản kết nối cần quyền đọc và tải nội dung thư mục nguồn. Tài khoản có quyền Xem Hồ sơ chuyên môn được xem và tải các tài liệu trong thư viện thông qua hệ thống. Chỉ liên kết thư mục phù hợp với phạm vi chia sẻ này.</p>
            <button class="btn btn-primary" name="action" value="link_folder">Lưu cấu hình</button>
            <?php if($config['id']):?><button class="btn btn-outline-secondary" name="action" value="unlink_folder" formnovalidate>Gỡ liên kết</button><?php endif;?>
            <div class="form-text">Gỡ liên kết không xóa tài liệu tại nguồn lưu trữ.</div>
        </form>
    </details>
    <?php endif;?>
</section>
<?php require __DIR__.'/includes/footer.php';?>

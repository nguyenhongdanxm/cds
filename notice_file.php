<?php
// Local attachments belong to the same public notices exposed by public_notice.php.
define('CDS_AUTH_LOGIN_ONLY',true);define('CDS_SKIP_DRIVE_ACTION_REGISTRATION',true);
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/local_download.php';
$relative=(string)($_GET['path']??'');$allowed=false;
foreach(load_json(DATA_PATH.'/cm_docs.json',[]) as $row){
    if(is_array($row)&&($row['section']??'')==='kh_thongbao'&&($row['file_path']??'')===$relative){$allowed=true;break;}
}
$file=$allowed?cds_local_upload_path(DATA_PATH,$relative):'';
if($file===''){http_response_code(404);exit;}
cds_stream_local_file($file,!empty($_GET['download']));

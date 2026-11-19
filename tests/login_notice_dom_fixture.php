<?php
// Render the production popup template without a real account or application database.
define('BASE_URL','/cds/');$_SESSION=[];
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
require_once __DIR__.'/../includes/login_notice_store.php';
$loginNotice=['id'=>'fixture-notice','title'=>'Thông báo kiểm thử','body'=>'<p>Nội dung ngắn gọn</p>','theme'=>'blue','icon'=>'📢','ends_at'=>''];
$loginNoticeGate=false;
echo '<!doctype html><html><head><meta charset="utf-8"></head><body>';
require __DIR__.'/../includes/login_notice_popup.php';
echo '<form id="businessForm" method="post" action="/cds/users.php"><input name="name"><button type="submit">Lưu dữ liệu</button></form></body></html>';

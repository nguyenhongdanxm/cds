<?php
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/ai_service.php';
require_once __DIR__.'/includes/ai_document_templates.php';
require_login();
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Phương thức không hợp lệ.');}
if(!hash_equals((string)($_SESSION['ai_csrf']??''),(string)($_POST['csrf']??''))){http_response_code(403);exit('Phiên làm việc không hợp lệ.');}
if(!can_perm('ai.vanban')){http_response_code(403);exit('Bạn chưa được cấp quyền xuất văn bản.');}
$content=trim((string)($_POST['content']??''));$templateId=trim((string)($_POST['template_id']??''));
if($content===''){http_response_code(422);exit('Chưa có nội dung để xuất.');}
if(mb_strlen($content,'UTF-8')>100000){http_response_code(422);exit('Nội dung quá dài.');}
$tmp=tempnam(sys_get_temp_dir(),'ai_doc_');if($tmp===false){http_response_code(500);exit('Không tạo được tệp tạm.');}
$res=cds_ai_docx_generate($content,$templateId,$tmp);
if(empty($res['ok'])){@unlink($tmp);http_response_code(500);exit((string)($res['message']??'Không xuất được Word.'));}
$name='Van-ban-AI-'.date('Ymd-His').'.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="'.$name.'"');
header('Content-Length: '.filesize($tmp));header('Cache-Control: no-store');readfile($tmp);@unlink($tmp);

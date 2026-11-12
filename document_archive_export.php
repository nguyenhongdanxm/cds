<?php
require_once __DIR__.'/includes/auth.php';require_admin();
require_once __DIR__.'/includes/document_schedule_archive.php';
$temp=null;try{$temp=tempnam(sys_get_temp_dir(),'cds-documents-');if(!$temp)throw new RuntimeException('Không tạo được tệp tạm.');cds_document_archive(__DIR__,$_GET,$temp);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="ho-so-word-pdf.zip"');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($temp));readfile($temp);}catch(Throwable $e){http_response_code(400);header('Content-Type: text/plain; charset=utf-8');echo $e->getMessage();}finally{if($temp&&is_file($temp))unlink($temp);}

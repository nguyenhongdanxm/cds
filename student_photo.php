<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_student_photo.php';
require_login();
$id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['id'] ?? ''));
if($id===''){http_response_code(404);exit;}
$record=csdl_student_photo_record($id,true);
if($record&&!empty($record['image_data'])){$bytes=(string)$record['image_data'];header('Content-Type: '.($record['mime_type']?:'application/octet-stream'));header('Content-Length: '.strlen($bytes));header('Cache-Control: private, max-age=86400');echo $bytes;exit;}
$file='';foreach(['jpg','jpeg','png','webp'] as $ext){$candidate=CSDL_STUDENT_PHOTO_DIR.'/'.$id.'.'.$ext;if(is_file($candidate)){$file=$candidate;break;}}
if($file===''){$driveId=csdl_student_photo_drive_id($id);if($driveId!==''){$remote=cds_drive_download($driveId);if(!empty($remote['ok'])){$info=@getimagesizefromstring((string)$remote['body']);$mime=(string)($info['mime']??$remote['mime']??'application/octet-stream');header('Content-Type: '.$mime);header('Content-Length: '.strlen($remote['body']));header('Cache-Control: private, max-age=86400');echo $remote['body'];exit;}}http_response_code(404);exit;}
$mime=function_exists('mime_content_type')?(mime_content_type($file)?:'application/octet-stream'):'application/octet-stream';header('Content-Type: '.$mime);header('Content-Length: '.filesize($file));header('Cache-Control: private, max-age=86400');readfile($file);

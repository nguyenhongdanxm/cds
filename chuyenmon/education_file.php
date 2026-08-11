<?php
require_once __DIR__ . '/includes/functions.php';
require_login();
$id=trim((string)($_GET['id']??''));$rows=load_json(DATA_PATH.'/education_plans.json',[]);$row=null;foreach((array)$rows as $candidate)if(($candidate['id']??'')===$id){$row=$candidate;break;}
if(!$row){http_response_code(404);exit('Không tìm thấy kế hoạch.');}
$user=cds_user()??[];$teacher=trim((string)($user['teacher_name']??$user['name']??''));$group=$teacher!==''?trim((string)get_teacher_group($teacher)):'';$role=(string)($user['role']??'');$leader=$role==='totruong'||in_array('totruong',(array)($user['groups']??[]),true);
$norm=fn($v)=>function_exists('mb_strtolower')?mb_strtolower(trim((string)$v),'UTF-8'):strtolower(trim((string)$v));
if($role!=='admin'&&!($leader&&$group!==''&&$norm($row['teacher_group']??'')===$norm($group))&&$norm($row['teacher']??'')!==$norm($teacher)){http_response_code(403);exit('Không có quyền xem tệp.');}
$path=(string)($row['file_path']??'');if(!str_starts_with($path,'gdrive:')){header('Location: '.BASE_URL.ltrim($path,'/'));exit;}
$fileId=substr($path,7);$token=cds_drive_token();if(empty($token['ok'])){http_response_code(503);exit('Không kết nối được Drive.');}
while(ob_get_level())ob_end_clean();set_time_limit(0);header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="ke-hoach-giao-duc.pdf"');header('Accept-Ranges: bytes');header('Cache-Control: private, max-age=300');
$headers=['Authorization: Bearer '.$token['token']];if(!empty($_SERVER['HTTP_RANGE']))$headers[]='Range: '.$_SERVER['HTTP_RANGE'];
$ch=curl_init('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'?supportsAllDrives=true&alt=media');
curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>300,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HEADERFUNCTION=>function($ch,$line){$trim=trim($line);if(preg_match('#^HTTP/\S+\s+(\d+)#',$trim,$m))http_response_code((int)$m[1]);elseif(stripos($trim,'Content-Length:')===0)header($trim);elseif(stripos($trim,'Content-Range:')===0)header($trim);return strlen($line);},CURLOPT_WRITEFUNCTION=>function($ch,$chunk){echo $chunk;if(function_exists('fastcgi_finish_request')){}flush();return strlen($chunk);}]);
$ok=curl_exec($ch);if($ok===false&&!headers_sent())http_response_code(502);curl_close($ch);exit;

<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/noitru_store.php';

$fileId=trim((string)($_GET['id']??''));
if(!preg_match('/^[A-Za-z0-9_-]{10,}$/',$fileId)){http_response_code(404);exit('File không hợp lệ.');}
$matched=false;$title='Đơn xin ra vào KTX';
foreach(noitru_exits_all() as $r){
 $att=(string)($r['attachment']??'');
 if($att==='gdrive:'.$fileId){$matched=true;$title='Đơn xin ra vào KTX - '.trim((string)($r['student_name']??''));break;}
}
if(!$matched){http_response_code(404);exit('Không tìm thấy đơn xin hợp lệ.');}
try{
 $settings=cds_drive_settings();$token=cds_drive_token($settings);if(!empty($token['ok'])&&!empty($token['token'])){
  $permBody=json_encode(['role'=>'reader','type'=>'anyone'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($fileId).'/permissions?supportsAllDrives=true','POST',['Authorization: Bearer '.$token['token'],'Content-Type: application/json; charset=UTF-8'],$permBody);
 }
}catch(Throwable $e){}
$preview='https://drive.google.com/file/d/'.rawurlencode($fileId).'/preview';
$direct='https://drive.google.com/file/d/'.rawurlencode($fileId).'/view';
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title><?=htmlspecialchars($title,ENT_QUOTES,'UTF-8')?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>*{box-sizing:border-box}html,body{margin:0;height:100%;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:#0f172a}.viewer{height:100%;display:grid;grid-template-rows:auto 1fr}.bar{display:flex;align-items:center;gap:.6rem;padding:.65rem .8rem;background:#fff;border-bottom:1px solid #dbe4ee}.bar strong{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.bar a,.bar button{min-height:40px;border:1px solid #cbd5e1;border-radius:10px;padding:.5rem .75rem;background:#fff;color:#1e293b;text-decoration:none;font:inherit;font-weight:700}.bar button{background:#0f4c81;color:#fff;border-color:#0f4c81}.frame{width:100%;height:100%;border:0;background:#fff}@media(max-width:600px){.label{display:none}}</style></head><body><div class="viewer"><div class="bar"><button type="button" onclick="history.length>1?history.back():window.close()"><i class="bi bi-x-lg"></i> <span class="label">Đóng</span></button><strong><?=htmlspecialchars($title,ENT_QUOTES,'UTF-8')?></strong><a href="<?=htmlspecialchars($direct,ENT_QUOTES,'UTF-8')?>" target="_blank" rel="noopener"><i class="bi bi-google"></i> <span class="label">Mở trên Drive</span></a></div><iframe class="frame" src="<?=htmlspecialchars($preview,ENT_QUOTES,'UTF-8')?>"></iframe></div></body></html>

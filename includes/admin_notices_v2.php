<?php
require_login();
$user=current_user();
$isAdmin=($user['role']??'')==='admin';
if(!function_exists('mb_strimwidth')){function mb_strimwidth($s,$start,$width,$mark='',$enc=null){$s=(string)$s;$x=substr($s,(int)$start,(int)$width);return strlen($s)>(int)$width?$x.$mark:$x;}}

function notice_fields_v2(): array {return [
 'chung'=>['label'=>'Thông báo chung','color'=>'#475569','module'=>''],
 'chuyenmon'=>['label'=>'Chuyên môn','color'=>'#2563eb','module'=>'chuyenmon'],
 'vanban'=>['label'=>'Văn bản · Hành chính','color'=>'#7c3aed','module'=>'vanban'],
 'noitru'=>['label'=>'Nội trú','color'=>'#be185d','module'=>'noitru'],
 'thuvien'=>['label'=>'Thư viện · Thiết bị','color'=>'#0f766e','module'=>'thuvien'],
 'thidua'=>['label'=>'Thi đua','color'=>'#b45309','module'=>'thidua'],
 'csdl'=>['label'=>'Cơ sở dữ liệu','color'=>'#0369a1','module'=>'csdl'],
];}
function notice_can_field_v2(string $key): bool {$u=current_user();if(($u['role']??'')==='admin')return true;$f=notice_fields_v2()[$key]??null;if(!$f||$key==='chung')return false;$m=(string)($f['module']??'');return $m!==''&&can_module($m,'edit');}
function notice_allowed_v2(): array {return array_filter(notice_fields_v2(),fn($v,$k)=>notice_can_field_v2((string)$k),ARRAY_FILTER_USE_BOTH);}
function notice_rows_all_v2(): array {$rows=cds_json_load(DATA_PATH.'/cm_docs.json',[]);return is_array($rows)?$rows:[];}
function notice_save_all_v2(array $rows): bool {return cds_json_save(DATA_PATH.'/cm_docs.json',array_values($rows));}
function notice_rows_v2(): array {$out=[];foreach(notice_rows_all_v2() as $r){if(($r['section']??'')!=='kh_thongbao')continue;if(empty($r['field']))$r['field']='chuyenmon';$out[]=$r;}usort($out,fn($a,$b)=>strcmp((string)($b['date']??''),(string)($a['date']??''))?:strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));return $out;}

function notice_drive_folder_v2(): array {
 $settings=cds_drive_settings();
 if(empty($settings['enabled']))return ['ok'=>false,'message'=>'Google Drive đang tắt. Hãy bật Google Drive trong phần quản trị.'];
 if(!isset($settings['types']['notifications'])||!is_array($settings['types']['notifications']))$settings['types']['notifications']=['label'=>'Thông báo','folder_id'=>'','prefix'=>'Thong-bao'];
 $folder=trim((string)($settings['types']['notifications']['folder_id']??''));
 $token=cds_drive_token($settings);if(empty($token['ok']))return $token;
 $headers=['Authorization: Bearer '.$token['token']];
 if($folder!==''){
   $test=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode($folder).'?supportsAllDrives=true&fields=id,name,mimeType,trashed,capabilities(canAddChildren)','GET',$headers);
   $j=json_decode($test['body'],true);
   if($test['ok']&&($j['mimeType']??'')==='application/vnd.google-apps.folder'&&empty($j['trashed'])&&!empty($j['capabilities']['canAddChildren']))return ['ok'=>true,'folder_id'=>$folder,'created'=>false,'name'=>$j['name']??'Thông báo'];
   $settings['types']['notifications']['folder_id']='';$folder='';
 }
 // Tìm thư mục Thông báo hiện có trước khi tạo mới.
 $q="name = 'Thông báo' and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
 $find=cds_drive_http('https://www.googleapis.com/drive/v3/files?spaces=drive&pageSize=10&fields=files(id,name,capabilities(canAddChildren))&q='.rawurlencode($q),'GET',$headers);
 $fj=json_decode($find['body'],true);
 if($find['ok'])foreach((array)($fj['files']??[]) as $f)if(!empty($f['id'])&&!empty($f['capabilities']['canAddChildren'])){$folder=(string)$f['id'];break;}
 if($folder===''){
   $meta=json_encode(['name'=>'Thông báo','mimeType'=>'application/vnd.google-apps.folder'],JSON_UNESCAPED_UNICODE);
   $create=cds_drive_http('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id,name','POST',array_merge($headers,['Content-Type: application/json; charset=UTF-8']),$meta);
   $cj=json_decode($create['body'],true);
   if(!$create['ok']||empty($cj['id']))return ['ok'=>false,'message'=>$cj['error']['message']??'Không tạo được thư mục “Thông báo” trên Google Drive.'];
   $folder=(string)$cj['id'];$created=true;
 } else $created=false;
 $settings['types']['notifications']=['label'=>'Thông báo','folder_id'=>$folder,'prefix'=>'Thong-bao'];
 if(!cds_drive_save_settings($settings))return ['ok'=>false,'message'=>'Đã tạo thư mục nhưng không lưu được Folder ID vào cấu hình.'];
 return ['ok'=>true,'folder_id'=>$folder,'created'=>$created,'name'=>'Thông báo'];
}
function notice_upload_v2(string $field): string {
 $up=$_FILES[$field]??null;if(!$up||($up['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
 if(($up['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK)throw new RuntimeException('Không nhận được file tải lên. Mã lỗi '.(int)$up['error'].'.');
 $tmp=(string)($up['tmp_name']??'');$bytes=$tmp!==''?@file_get_contents($tmp):false;if($bytes===false)throw new RuntimeException('Không đọc được file tạm trên máy chủ.');
 if(strlen($bytes)>25*1024*1024)throw new RuntimeException('File vượt quá 25 MB.');
 $folder=notice_drive_folder_v2();if(empty($folder['ok']))throw new RuntimeException((string)($folder['message']??'Không chuẩn bị được thư mục Thông báo.'));
 // Xóa fingerprint chết của đúng thư mục thông báo để bắt buộc kiểm tra/upload thật.
 $fingerprint=hash('sha256',(string)$folder['folder_id'].'|'.$bytes);$hist=cds_drive_history();$changed=false;$token=cds_drive_token();
 if(!empty($token['ok']))foreach($hist as $i=>$row){if(($row['fingerprint']??'')!==$fingerprint||empty($row['file_id']))continue;$check=cds_drive_http('https://www.googleapis.com/drive/v3/files/'.rawurlencode((string)$row['file_id']).'?supportsAllDrives=true&fields=id,trashed','GET',['Authorization: Bearer '.$token['token']]);$cj=json_decode($check['body'],true);if(!$check['ok']||!empty($cj['trashed'])){unset($hist[$i]);$changed=true;}}
 if($changed)@file_put_contents(CDS_DRIVE_HISTORY,json_encode(array_values($hist),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
 $mime=function_exists('mime_content_type')?(mime_content_type($tmp)?:'application/octet-stream'):'application/octet-stream';
 $result=cds_drive_upload_bytes($bytes,basename((string)($up['name']??'file')),$mime,'notifications',['action'=>'notice','source_action'=>'page:/notices.php']);
 if(empty($result['ok']))throw new RuntimeException((string)($result['message']??'Không tải được file lên thư mục Thông báo.'));
 return (string)($result['path']??('gdrive:'.($result['id']??'')));
}
function notice_file_url_v2(string $path): string {return $path!==''&&str_starts_with($path,'gdrive:')?BASE_URL.'admin.php?drive_file='.rawurlencode(substr($path,7)):'';}

$allowed=notice_allowed_v2();if(!$allowed){http_response_code(403);echo '<div style="font-family:system-ui;padding:2rem">Bạn chưa được phân quyền đăng thông báo.</div>';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $ajax=!empty($_POST['notice_ajax']);
 try{$action=(string)($_POST['notice_action']??'');
  if($action==='save'){
   $field=(string)($_POST['field']??'');if(!isset($allowed[$field]))throw new RuntimeException('Bạn không có quyền đăng ở lĩnh vực này.');
   $id=trim((string)($_POST['id']??''));$new=$id==='';if($new)$id='cm_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
   $title=trim((string)($_POST['title']??''));if($title==='')throw new RuntimeException('Hãy nhập tiêu đề thông báo.');
   $rows=notice_rows_all_v2();$old=null;foreach($rows as $r)if(($r['id']??'')===$id){$old=$r;break;}
   $file=notice_upload_v2('file');$oldFile=trim((string)($_POST['file_path']??($old['file_path']??'')));
   $record=array_merge(is_array($old)?$old:[],['id'=>$id,'section'=>'kh_thongbao','kind'=>'notice','field'=>$field,'title'=>$title,'date'=>trim((string)($_POST['date']??date('Y-m-d'))),'has_deadline'=>!empty($_POST['has_deadline']),'due_date'=>!empty($_POST['has_deadline'])?trim((string)($_POST['due_date']??'')):'','has_assignees'=>false,'assignees'=>[],'completed'=>!empty($_POST['completed']),'content'=>trim((string)($_POST['content']??'')),'link'=>trim((string)($_POST['link']??'')),'file_path'=>$file!==''?$file:$oldFile,'by'=>$user['name']??'','updated_at'=>date('c')]);if($new)$record['created_at']=date('c');
   $found=false;foreach($rows as &$r)if(($r['id']??'')===$id){$r=$record;$found=true;break;}unset($r);if(!$found)$rows[]=$record;if(!notice_save_all_v2($rows))throw new RuntimeException('Không lưu được dữ liệu thông báo.');
   if($new&&!$record['completed'])cds_push_publish($title,mb_strimwidth(strip_tags((string)$record['content']),0,180,'…','UTF-8'),'/notices.php?notice='.rawurlencode($id),['source_id'=>'notice:'.$id,'audience'=>['all'],'expires_at'=>$record['due_date']]);
   $message=$file!==''?'Đã tải file vào thư mục Google Drive “Thông báo” và lưu thông báo.':'Đã lưu thông báo.';
  }elseif($action==='toggle'){$id=(string)($_POST['id']??'');$rows=notice_rows_all_v2();$ok=false;foreach($rows as &$r)if(($r['id']??'')===$id&&($r['section']??'')==='kh_thongbao'){if(!notice_can_field_v2((string)($r['field']??'chuyenmon')))throw new RuntimeException('Bạn không có quyền cập nhật.');$r['completed']=empty($r['completed']);$ok=true;break;}unset($r);if(!$ok)throw new RuntimeException('Không tìm thấy thông báo.');notice_save_all_v2($rows);$message='Đã cập nhật trạng thái.';
  }elseif($action==='delete'){$id=(string)($_POST['id']??'');$rows=notice_rows_all_v2();$kept=[];$found=false;foreach($rows as $r){if(($r['id']??'')===$id&&($r['section']??'')==='kh_thongbao'){$found=true;continue;}$kept[]=$r;}if(!$found)throw new RuntimeException('Không tìm thấy thông báo.');notice_save_all_v2($kept);$message='Đã xóa thông báo.';}else throw new RuntimeException('Thao tác không hợp lệ.');
  if($ajax){header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'message'=>$message],JSON_UNESCAPED_UNICODE);exit;}flash($message,'success');
 }catch(Throwable $e){if($ajax){header('Content-Type: application/json; charset=utf-8');http_response_code(400);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;}flash($e->getMessage(),'danger');}
 header('Location: '.BASE_URL.'notices.php');exit;
}
$items=notice_rows_v2();$fields=notice_fields_v2();
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Thông báo – CDS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"><style>body{background:#f4f7fb}.wrap{max-width:1450px}.badge-field{color:#fff;border-radius:999px;padding:.3rem .55rem;font-weight:800;font-size:.75rem}.overlay{position:fixed;inset:0;background:#0f172a99;display:none;place-items:center;z-index:9999}.overlay.show{display:grid}.box{width:min(520px,calc(100% - 2rem));background:#fff;border-radius:18px;padding:1.4rem;box-shadow:0 24px 80px #0004}.progress{height:14px}@media(max-width:900px){.grid{grid-template-columns:1fr!important}}</style></head><body><main class="container-fluid wrap py-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><a href="admin.php" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Tổng quan</a><h2 class="mb-0"><i class="bi bi-megaphone-fill text-primary"></i> Thông báo</h2><div class="text-muted">File đính kèm được lưu riêng trong thư mục Google Drive <strong>Thông báo</strong>.</div></div></div><?php show_flash();?><div class="grid" style="display:grid;grid-template-columns:minmax(340px,.75fr) minmax(0,1.65fr);gap:1rem"><section class="card border-0 shadow-sm"><div class="card-header bg-primary text-white fw-bold">Thêm / cập nhật thông báo</div><div class="card-body"><form id="noticeForm" method="post" enctype="multipart/form-data"><input type="hidden" name="notice_action" value="save"><input type="hidden" name="notice_ajax" value="1"><input type="hidden" name="id" id="n_id"><input type="hidden" name="file_path" id="n_file"><div class="mb-2"><label class="form-label fw-semibold">Lĩnh vực</label><select class="form-select" name="field" id="n_field"><?php foreach($allowed as $k=>$f):?><option value="<?=e($k)?>"><?=e($f['label'])?></option><?php endforeach;?></select></div><div class="mb-2"><label class="form-label fw-semibold">Tiêu đề *</label><input class="form-control" name="title" id="n_title" required></div><div class="mb-2"><label class="form-label fw-semibold">Ngày ban hành / sự kiện</label><input class="form-control" type="date" name="date" id="n_date" value="<?=date('Y-m-d')?>"></div><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="has_deadline" value="1" id="n_deadline"><label class="form-check-label" for="n_deadline">Có hạn thực hiện / báo cáo</label></div><div class="mb-2" id="dueBox" hidden><label class="form-label">Hạn</label><input class="form-control" type="date" name="due_date" id="n_due"></div><div class="mb-2"><label class="form-label fw-semibold">Nội dung</label><textarea class="form-control" rows="4" name="content" id="n_content"></textarea></div><div class="mb-2"><label class="form-label">Link</label><input class="form-control" type="url" name="link" id="n_link" placeholder="https://..."></div><div class="mb-2"><label class="form-label fw-semibold">File đính kèm</label><input class="form-control" type="file" name="file" id="n_upload"><div class="form-text">Tải vào thư mục Google Drive “Thông báo” – tối đa 25 MB.</div></div><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="completed" value="1" id="n_completed"><label class="form-check-label">Đã hoàn thành</label></div><button class="btn btn-primary w-100" id="saveBtn"><i class="bi bi-cloud-arrow-up"></i> Lưu thông báo</button></form></div></section><section class="card border-0 shadow-sm"><div class="card-header fw-bold">Danh sách thông báo (<?=count($items)?>)</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Ngày</th><th>Lĩnh vực</th><th>Tiêu đề</th><th>Hạn</th><th>Trạng thái</th><th>Tài liệu</th><th style="width:150px">Thao tác</th></tr></thead><tbody><?php foreach($items as $it):$f=$fields[$it['field']??'chuyenmon']??$fields['chuyenmon'];?><tr><td><?=e($it['date']??'')?></td><td><span class="badge-field" style="background:<?=e($f['color'])?>"><?=e($f['label'])?></span></td><td><strong><?=e($it['title']??'')?></strong><div class="small text-muted"><?=e(mb_strimwidth(strip_tags((string)($it['content']??'')),0,90,'…','UTF-8'))?></div></td><td><?=e($it['due_date']??'—')?></td><td><?=!empty($it['completed'])?'<span class="badge bg-success">Hoàn thành</span>':'<span class="badge bg-warning text-dark">Đang theo dõi</span>'?></td><td><?php if(!empty($it['file_path'])):?><a class="btn btn-sm btn-outline-primary" target="_blank" href="<?=e(notice_file_url_v2((string)$it['file_path']))?>"><i class="bi bi-file-earmark"></i> File</a><?php elseif(!empty($it['link'])):?><a target="_blank" href="<?=e($it['link'])?>">Link</a><?php else:?>—<?php endif;?></td><td class="text-nowrap"><?php if(notice_can_field_v2((string)($it['field']??'chuyenmon'))):?><button type="button" class="btn btn-sm btn-outline-primary editBtn" data-row='<?=e(json_encode($it,JSON_UNESCAPED_UNICODE|JSON_HEX_APOS|JSON_HEX_QUOT))?>'><i class="bi bi-pencil"></i></button><form method="post" class="d-inline"><input type="hidden" name="notice_action" value="toggle"><input type="hidden" name="id" value="<?=e($it['id']??'')?>"><button class="btn btn-sm btn-outline-success"><i class="bi bi-check2"></i></button></form><form method="post" class="d-inline" onsubmit="return confirm('Xóa thông báo này?')"><input type="hidden" name="notice_action" value="delete"><input type="hidden" name="id" value="<?=e($it['id']??'')?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></section></div></main><div class="overlay" id="progressOverlay"><div class="box"><h5 id="progressTitle">Đang tải file lên thư mục Thông báo…</h5><div class="text-muted mb-3" id="progressText">Đang chuẩn bị</div><div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar" style="width:4%">4%</div></div></div></div><script>
const form=document.getElementById('noticeForm'),overlay=document.getElementById('progressOverlay'),bar=document.getElementById('progressBar'),txt=document.getElementById('progressText'),title=document.getElementById('progressTitle');
document.getElementById('n_deadline').addEventListener('change',e=>document.getElementById('dueBox').hidden=!e.target.checked);
form.addEventListener('submit',e=>{e.preventDefault();const xhr=new XMLHttpRequest(),fd=new FormData(form);overlay.classList.add('show');title.textContent='Đang tải lên Google Drive / Thông báo';txt.textContent='Đang gửi file lên máy chủ…';xhr.upload.onprogress=ev=>{if(ev.lengthComputable){const p=Math.min(80,Math.max(5,Math.round(ev.loaded/ev.total*80)));bar.style.width=p+'%';bar.textContent=p+'%'}};xhr.upload.onload=()=>{bar.style.width='88%';bar.textContent='88%';txt.textContent='Đang tạo/kiểm tra thư mục “Thông báo” và lưu file lên Google Drive…'};xhr.onload=()=>{let d={};try{d=JSON.parse(xhr.responseText)}catch(_){d={ok:false,message:'Phản hồi máy chủ không hợp lệ.'}}if(xhr.status>=200&&xhr.status<300&&d.ok){bar.style.width='100%';bar.textContent='100%';bar.classList.remove('progress-bar-animated');title.textContent='Hoàn tất';txt.textContent=d.message||'Đã lưu.';setTimeout(()=>location.reload(),900)}else{bar.classList.add('bg-danger');title.textContent='Không thành công';txt.textContent=d.message||('HTTP '+xhr.status);setTimeout(()=>overlay.classList.remove('show'),2500)}};xhr.onerror=()=>{title.textContent='Lỗi kết nối';txt.textContent='Không kết nối được máy chủ.'};xhr.open('POST',location.href);xhr.send(fd)});
document.querySelectorAll('.editBtn').forEach(b=>b.addEventListener('click',()=>{const r=JSON.parse(b.dataset.row);n_id.value=r.id||'';n_field.value=r.field||'chuyenmon';n_title.value=r.title||'';n_date.value=r.date||'';n_content.value=r.content||'';n_link.value=r.link||'';n_file.value=r.file_path||'';n_completed.checked=!!r.completed;n_deadline.checked=!!r.has_deadline;dueBox.hidden=!n_deadline.checked;n_due.value=r.due_date||'';scrollTo({top:0,behavior:'smooth'})}));
</script></body></html>
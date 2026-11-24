<?php
$dir=sys_get_temp_dir().'/signature-preview-'.bin2hex(random_bytes(6));mkdir($dir);mkdir($dir.'/teacher_signature_images');
define('DATA_PATH',$dir);define('CDS_TEACHER_SIGNATURE_DATA',$dir);define('CDS_TEACHER_SIGNATURE_LEGACY_DATA',$dir.'/cm');define('BASE_URL','/missing/');
require __DIR__.'/../includes/json_store.php';require __DIR__.'/../includes/teacher_signatures.php';require __DIR__.'/../includes/library_equipment_ledgers.php';
function load_json($f,$d=[]){return cds_json_load($f,$d);}function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}function lib_date($v){return e($v);}
function csdl_teachers_all(){return [['id'=>'owner','name'=>'Giáo viên thử nghiệm'],['id'=>'other','name'=>'Giáo viên khác']];}
function current_user(){return ['id'=>'user','teacher_id'=>'owner','role'=>'teacher','name'=>'Giáo viên thử nghiệm'];}
$path=$dir.'/teacher_signature_images/signature.png';$image=imagecreatetruecolor(480,160);imagefill($image,0,0,imagecolorallocate($image,255,255,255));$ink=imagecolorallocate($image,20,50,120);imagesetthickness($image,5);imageline($image,30,100,100,30,$ink);imageline($image,100,30,160,110,$ink);imageline($image,160,110,300,45,$ink);imageline($image,220,120,430,120,$ink);imagepng($image,$path);imagedestroy($image);
cds_json_save($dir.'/teacher_signatures.json',[['teacher_id'=>'owner','path'=>$path,'version'=>'fixture','source'=>'lesson_book']]);
$editing=csdl_teachers_all()[0];$teacherReadOnly=false;$_SESSION=['teacher_signature_csrf'=>'fixture'];
$csrf='fixture';$userName='Giáo viên thử nghiệm';$canEquipmentLoanEdit=true;$canEquipmentLoanDelete=false;
$base=['borrower'=>'Giáo viên thử nghiệm','subject'=>'Toán','school_year'=>'2026 - 2027','equipment_name'=>'Thiết bị thử','quantity'=>1,'borrowed_at'=>'2026-11-20'];
$data=['equipment_loans'=>[array_merge($base,['id'=>'open','returned_at'=>'']),array_merge($base,['id'=>'returned','returned_at'=>'2026-11-21']),array_merge($base,['id'=>'other','borrower'=>'Giáo viên khác'])]];
ob_start();?><!doctype html><html lang="vi"><head><meta charset="utf-8"><style>body{font:16px sans-serif;margin:20px}.card{border:1px solid #ccc;margin:10px 0;padding:10px}table{border-collapse:collapse;width:100%}td,th{border:1px solid #ccc;padding:6px}.modal-body{max-width:1000px}.text-muted{color:#555}button{padding:6px;margin:3px}small{font-size:12px}</style><script>window.bootstrap={Modal:class{show(){}}};</script></head><body><h1>Hồ sơ giáo viên</h1><section id="teacher-preview"><?php require __DIR__.'/../includes/csdl_modal_teacher.php';?></section><h1>Sổ mượn–trả</h1><section id="equipment-preview"><?php require __DIR__.'/../includes/library_equipment_ledgers_view.php';?></section></body></html><?php
$html=ob_get_clean();file_put_contents('/tmp/teacher-signature-preview.html',$html);echo "PASS created real teacher modal and equipment view fixture with valid PNG\n";

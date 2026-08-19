<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_login();
header('Content-Type: application/json; charset=utf-8');
$user=current_user()??[];$isAdmin=(string)($user['role']??'')==='admin';
if(!$isAdmin&&!can_perm_level('tv.danhmuc','edit')){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Tài khoản chưa có quyền cập nhật danh mục sách.'],JSON_UNESCAPED_UNICODE);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);echo json_encode(['ok'=>false,'message'=>'Phương thức không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
$csrf=(string)($_SESSION['library_csrf']??'');if($csrf===''||!hash_equals($csrf,(string)($_POST['csrf']??''))){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'Phiên làm việc không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
$id=trim((string)($_POST['id']??''));$qty=max(1,(int)($_POST['quantity']??1));$date=trim((string)($_POST['import_date']??date('Y-m-d')));$source=trim((string)($_POST['source']??''));$note=trim((string)($_POST['note']??''));
if($id===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)){http_response_code(422);echo json_encode(['ok'=>false,'message'=>'Thiếu đầu sách hoặc ngày bổ sung không hợp lệ.'],JSON_UNESCAPED_UNICODE);exit;}
$file=DATA_PATH.'/library_equipment.json';$data=load_json($file,['books'=>[],'loans'=>[],'equipment'=>[],'equipment_loans'=>[],'maintenance'=>[]]);$data['books']=is_array($data['books']??null)?array_values($data['books']):[];$index=-1;foreach($data['books'] as $i=>$book)if((string)($book['id']??'')===$id){$index=$i;break;}
if($index<0){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'Không tìm thấy đầu sách.'],JSON_UNESCAPED_UNICODE);exit;}
$book=$data['books'][$index];$oldQty=max(0,(int)($book['quantity']??0));$history=is_array($book['intake_history']??null)?array_values($book['intake_history']):[];
if(!$history&&$oldQty>0)$history[]=['type'=>'initial','date'=>(string)($book['import_date']??''),'quantity'=>$oldQty,'source'=>(string)($book['source']??''),'note'=>'Số lượng trước khi hệ thống theo dõi lịch sử nhập kho','at'=>(string)($book['created_at']??'')];
$history[]=['type'=>'supplement','date'=>$date,'quantity'=>$qty,'source'=>$source,'note'=>$note,'by'=>(string)($user['teacher_name']??$user['name']??$user['username']??''),'at'=>date('c')];
$book['intake_history']=$history;$book['quantity']=$oldQty+$qty;$book['last_supplement_date']=$date;$book['updated_at']=date('c');if($source!=='')$book['source']=$source;if($note!==''){$oldNote=trim((string)($book['note']??''));$book['note']=trim($oldNote.($oldNote!==''?"\n":'').'Bổ sung '.$date.': '.$note);}$data['books'][$index]=$book;save_json($file,$data);
echo json_encode(['ok'=>true,'message'=>'Đã bổ sung '.$qty.' cuốn. Tổng hiện có trong danh mục: '.$book['quantity'].' cuốn.','quantity'=>$book['quantity'],'history_count'=>count($history)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
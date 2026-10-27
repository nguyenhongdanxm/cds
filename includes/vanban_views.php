<?php
/** Theo dõi người dùng đã mở hoặc chủ động xác nhận đã xem văn bản. */
require_once __DIR__.'/json_store.php';
if (!defined('VANBAN_VIEWS_FILE')) define('VANBAN_VIEWS_FILE', DATA_PATH . '/vanban_document_views.json');

function vb_view_user_key(array $user): string {
    $key = trim((string)($user['username'] ?? $user['email'] ?? $user['id'] ?? ''));
    if ($key === '') return '';
    return function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
}

function vb_document_views_all(): array {
    $rows = load_json(VANBAN_VIEWS_FILE, []);
    return is_array($rows) ? $rows : [];
}

function vb_document_views(string $documentId): array {
    $all = vb_document_views_all();
    $rows = $all[$documentId] ?? [];
    if (!is_array($rows)) return [];
    uasort($rows, fn($a,$b)=>strcmp((string)($b['last_viewed_at']??''),(string)($a['last_viewed_at']??'')));
    return array_values($rows);
}

function vb_document_view_count(string $documentId): int {
    return count(vb_document_views($documentId));
}

function vb_document_view_row(array $old,array $user,string $now): array {
    $userKey=vb_view_user_key($user);
    return array_merge($old,[
        'user_key'=>$userKey,'name'=>(string)($user['name']??$user['fullname']??$user['username']??$userKey),
        'username'=>(string)($user['username']??''),'first_viewed_at'=>(string)($old['first_viewed_at']??$now),
        'last_viewed_at'=>$now,'view_count'=>(int)($old['view_count']??0)+1,
    ]);
}
function vb_record_document_view(string $documentId,array $user): void {
    $documentId=trim($documentId);$key=vb_view_user_key($user);if($documentId===''||$key==='')return;
    // Share the same atomic writer with quick acknowledgements to retain concurrent viewers.
    cds_json_update(VANBAN_VIEWS_FILE,function($all)use($documentId,$user,$key){
        if(!is_array($all))return false;
        if(isset($all[$documentId])&&!is_array($all[$documentId]))return false;
        $old=$all[$documentId][$key]??[];if(!is_array($old))return false;
        $all[$documentId][$key]=vb_document_view_row($old,$user,date('c'));return $all;
    },[]);
}
function vb_mark_documents_seen(array $ids,array $user,array $documents): array {
    $key=vb_view_user_key($user);if($key==='')throw new RuntimeException('Không xác định được tài khoản đang đăng nhập.');
    if(!$ids||count($ids)>200)throw new RuntimeException('Chọn từ 1 đến 200 văn bản để xử lý nhanh.');
    $allowed=[];foreach($documents as $document){$id=(string)($document['id']??'');if($id!=='')$allowed[$id]=true;}
    $selected=[];foreach($ids as $id){if(!is_string($id)||!isset($allowed[$id]))throw new RuntimeException('Có văn bản không tồn tại hoặc không được phép xem. Chưa ghi nhận văn bản nào.');$selected[$id]=true;}
    $updated=0;$already=0;$now=date('c');
    $ok=cds_json_update(VANBAN_VIEWS_FILE,function($all)use($selected,$user,$key,$now,&$updated,&$already){
        if(!is_array($all))return false;
        foreach($selected as $id=>$_){if(isset($all[$id])&&!is_array($all[$id]))return false;
            if(isset($all[$id][$key])){if(!is_array($all[$id][$key]))return false;$already++;continue;}
            $all[$id][$key]=vb_document_view_row([],$user,$now)+['quick_seen_at'=>$now];$updated++;
        }return $all;
    },[]);
    if(!$ok)throw new RuntimeException('Không lưu được trạng thái đã xem. Dữ liệu cũ được giữ nguyên.');
    return ['updated'=>$updated,'already_seen'=>$already];
}

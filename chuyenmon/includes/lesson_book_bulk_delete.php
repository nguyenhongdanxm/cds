<?php
function lb_bulk_delete_version(array $row): string {return hash('sha256',(string)json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
/** Admin-only; validate every selection against JSON under its write lock. */
function lb_bulk_delete_signed(array $week,string $json,string $reason,string $confirmed): array {
    if(!lb_is_admin())return ['ok'=>false,'message'=>'Chỉ quản trị được xóa hàng loạt tiết đã ký.'];
    $reason=trim($reason);
    if($confirmed!=='1'||$reason===''||mb_strlen($reason,'UTF-8')>1000)return ['ok'=>false,'message'=>'Xác nhận thao tác và nhập lý do xóa (tối đa 1000 ký tự).'];
    if(strlen($json)>100000)return ['ok'=>false,'message'=>'Danh sách chọn quá lớn.'];
    $choices=json_decode($json,true);$expected=[];
    if(!is_array($choices)||!array_is_list($choices)||!$choices||count($choices)>200)return ['ok'=>false,'message'=>'Chọn từ 1 đến 200 tiết đã ký.'];
    foreach($choices as $choice){
        if(!is_array($choice)||!is_string($choice['id']??null)||$choice['id']===''||strlen($choice['id'])>200||!is_string($choice['signed_at']??null)||$choice['signed_at']===''||!is_string($choice['updated_at']??null)||!is_string($choice['version']??null)||strlen($choice['version'])!==64)return ['ok'=>false,'message'=>'Danh sách tiết không hợp lệ.'];
        if(isset($expected[$choice['id']])&&$expected[$choice['id']]!==$choice)return ['ok'=>false,'message'=>'Dữ liệu lựa chọn không nhất quán.'];
        $expected[$choice['id']]=$choice;
    }
    $shadowEnabled=cds_lb_shadow_enabled();$hadPending=(bool)cds_lb_pending_status();$marked=false;
    if($shadowEnabled&&!$hadPending){if(!cds_lb_pending_mark('bulk_delete_signed'))return ['ok'=>false,'message'=>'Chưa chuẩn bị được đồng bộ; chưa xóa tiết nào.'];$marked=true;}
    $deleted=[];$error='';
    $ok=cds_json_update(LB_RECORDS_FILE,function($rows)use($week,$expected,&$deleted,&$error){
        if(!is_array($rows)){$error='Không đọc được sổ; chưa xóa tiết nào.';return false;}
        $next=[];$found=[];
        foreach($rows as $row){
            $id=is_array($row)?(string)($row['slot_id']??''):'';
            if(!isset($expected[$id])){$next[]=$row;continue;}
            $e=$expected[$id];$date=(string)($row['date']??'');
            if(isset($found[$id])||!hash_equals(lb_bulk_delete_version($row),$e['version'])||empty($row['signed_at'])||(string)$row['signed_at']!==$e['signed_at']||(string)($row['updated_at']??'')!==$e['updated_at']||(string)($row['week_key']??'')!==(string)($week['key']??'')||$date<(string)($week['start']??'')||$date>(string)($week['end']??'')){$error='Một tiết đã thay đổi hoặc không thuộc tuần đang xem. Chưa xóa tiết nào; hãy tải lại sổ.';return false;}
            $found[$id]=true;$deleted[]=$row;
        }
        if(count($found)!==count($expected)){$error='Một tiết không còn tồn tại. Chưa xóa tiết nào; hãy tải lại sổ.';return false;}
        return $next;
    },[]);
    if(!$ok){if($marked)cds_lb_pending_clear();return ['ok'=>false,'message'=>$error?:'Không ghi được sổ; chưa xóa tiết nào.'];}
    $shadowOk=true;
    if($shadowEnabled){try{$pdo=cds_db();$pdo->beginTransaction();$stmt=$pdo->prepare('DELETE FROM cds_lesson_book_records WHERE slot_id IN ('.implode(',',array_fill(0,count($expected),'?')).')');$stmt->execute(array_keys($expected));$pdo->commit();foreach(cds_json_load(LB_RECORDS_FILE,[]) as $current)if(is_array($current)&&isset($expected[(string)($current['slot_id']??'')]))$shadowOk=false;if($marked&&$shadowOk)$shadowOk=cds_lb_pending_clear();}catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();$shadowOk=false;error_log('[CDS bulk delete signed shadow] '.$e->getMessage());}}
    lb_rows_bust(LB_RECORDS_FILE);
    if(!$shadowOk||$hadPending){$GLOBALS['cds_force_json_lb_read']=true;unset($GLOBALS['cds_lb_read_status']);}
    lb_audit('bulk_delete_signed',['week_key'=>$week['key'],'count'=>count($deleted),'reason'=>$reason,'deleted_records'=>$deleted]);
    return ['ok'=>true,'deleted'=>count($deleted),'deleted_ids'=>array_keys($expected),'message'=>'Đã xóa dữ liệu và chữ ký của '.count($deleted).' tiết đã chọn. Tiết trong TKB vẫn được giữ.'.(!$shadowOk||$hadPending?' Bản MySQL đang chờ đồng bộ; hệ thống dùng dữ liệu JSON mới nhất.':'')];
}

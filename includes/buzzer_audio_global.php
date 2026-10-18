<?php
/** Cấu hình toàn trường; upload JSON từng phần, không phụ thuộc multipart/upload_max_filesize. */
declare(strict_types=1);
require_once __DIR__.'/buzzer_audio.php';
function bz_audio_admin(array $actor): void {if(($actor['role']??'')!=='host'||empty($actor['audio_admin'])||empty($actor['owner']))bz_fail('Chỉ quản trị viên được cài âm thanh dùng chung.',403);}
function bz_audio_global_set(PDO $db,array $actor,string $kind,?array $meta): array {
    bz_audio_admin($actor);bz_audio_kind($kind);bz_global_audio_schema($db);
    if($meta){bz_audio_path($meta['id']);bz_text($meta['name'],80);}
    $db->beginTransaction();
    try{
        $q=$db->prepare('SELECT metadata_json FROM cds_buzzer_audio_settings WHERE kind=? FOR UPDATE');$q->execute([$kind]);$old=$q->fetchColumn();$old=$old!==false?json_decode($old,true):null;
        $q=$db->prepare('INSERT INTO cds_buzzer_audio_settings(kind,metadata_json,updated_by) VALUES(?,?,?) ON DUPLICATE KEY UPDATE metadata_json=VALUES(metadata_json),updated_by=VALUES(updated_by)');
        $q->execute([$kind,json_encode($meta,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$actor['owner']]);$db->commit();
        // File có thể đang được màn hình trình chiếu đọc. Không xóa trong lượt chơi.
        return ['audio'=>bz_global_audio($db)];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function bz_audio_prepare_dir(): void {
    $dir=bz_audio_dir();if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))bz_fail('Không ghi được thư mục data/buzzer_audio. Kiểm tra quyền ghi của PHP.',503);
    if(!is_file($dir.'/.htaccess')&&file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n")===false)bz_fail('Không bảo vệ được thư mục âm thanh.',503);
}
function bz_audio_cleanup(PDO $db): void {
    $protected=[];foreach(bz_global_audio($db)as$m)if($m)$protected[$m['id']]=true;
    $count=0;foreach(new DirectoryIterator(bz_audio_dir())as$f){
        if($count>=100)break;if(!$f->isFile())continue;
        if(preg_match('/^([a-f0-9]{48})\.(bin|part|json)$/D',$f->getFilename(),$m)&&!isset($protected[$m[1]])&&$f->getMTime()<time()-($m[2]==='bin'?172800:3600)){@unlink($f->getPathname());$count++;}
    }
}
function bz_audio_upload_step(PDO $db,array $actor,string $action,array $input): array {
    bz_audio_admin($actor);bz_audio_prepare_dir();
    if($action==='audio_begin'){
        bz_audio_kind($input['kind']??'');$size=filter_var($input['size']??0,FILTER_VALIDATE_INT);if(!$size||$size>10485760)bz_fail('Chọn file từ 1 byte đến 10 MB.',422);
        $name=bz_text(mb_substr((string)($input['name']??''),0,80,'UTF-8'),80);bz_global_audio_schema($db);bz_audio_cleanup($db);
        $active=0;foreach(glob(bz_audio_dir().'/*.json')as$f){$m=json_decode((string)file_get_contents($f),true);if(($m['owner']??'')===$actor['owner'])$active++;}if($active>=10)bz_fail('Có quá nhiều lần tải chưa hoàn tất. Hãy thử lại sau.',429);
        $id=bin2hex(random_bytes(24));$meta=['owner'=>$actor['owner'],'kind'=>$input['kind'],'size'=>$size,'name'=>$name,'created'=>time()];
        if(file_put_contents(bz_audio_dir().'/'.$id.'.json',json_encode($meta,JSON_THROW_ON_ERROR),LOCK_EX)===false)bz_fail('Không tạo được phiên upload.',503);
        @chmod(bz_audio_dir().'/'.$id.'.json',0600);return ['upload_id'=>$id,'offset'=>0];
    }
    $id=(string)($input['upload_id']??'');bz_audio_path($id);$manifest=bz_audio_dir().'/'.$id.'.json';
    // Hoàn tất lại sau khi mạng mất phản hồi: không tạo file/cấu hình trùng.
    if(!is_file($manifest)&&$action==='audio_finish'){foreach(bz_global_audio($db)as$m)if(($m['id']??'')===$id)return ['audio'=>bz_global_audio($db)];}
    $lock=@fopen($manifest,'r+');if(!$lock)bz_fail('Phiên upload đã hết hạn. Chọn file và tải lại.',404);
    try{
        if(!flock($lock,LOCK_EX))bz_fail('Chưa khóa được phiên upload.',503);$meta=json_decode(stream_get_contents($lock),true,16,JSON_THROW_ON_ERROR);
        if(($meta['owner']??'')!==$actor['owner']||time()-(int)$meta['created']>3600)bz_fail('Phiên upload không hợp lệ hoặc đã hết hạn.',403);
        $part=bz_audio_dir().'/'.$id.'.part';clearstatcache(true,$part);$offset=is_file($part)?(int)filesize($part):0;
        if($action==='audio_cancel'){@unlink($part);@unlink($manifest);return ['cancelled'=>true];}
        if($action==='audio_chunk'){
            $bytes=base64_decode((string)($input['data']??''),true);$start=filter_var($input['offset']??-1,FILTER_VALIDATE_INT);
            if($bytes===false||strlen($bytes)<1||strlen($bytes)>131072||$start===false||$start<0||$start+strlen($bytes)>$meta['size'])bz_fail('Phần âm thanh không hợp lệ.',422);
            if($start<$offset){$f=fopen($part,'rb');fseek($f,$start);$old=fread($f,strlen($bytes));fclose($f);if(!hash_equals($old,$bytes))bz_fail('Phần tải lại không khớp.',409);return ['offset'=>$offset];}
            if($start!==$offset)bz_fail('Thứ tự phần tải chưa khớp. Hãy thử lại.',409);
            $f=fopen($part,'c+b');if(!$f)bz_fail('Không ghi được file tạm.',503);@chmod($part,0600);fseek($f,$offset);$written=fwrite($f,$bytes);
            if($written!==strlen($bytes)){ftruncate($f,$offset);fclose($f);bz_fail('Chưa ghi đủ dữ liệu. Kiểm tra dung lượng đĩa.',503);}fflush($f);fclose($f);return ['offset'=>$offset+$written];
        }
        if($action!=='audio_finish')bz_fail('Thao tác upload không hợp lệ.',422);
        if($offset!==$meta['size'])bz_fail('File chưa tải đủ. Hãy tải lại.',409);
        $mime=bz_audio_validate($part,$offset);$path=bz_audio_path($id);
        if(!rename($part,$path))bz_fail('Không hoàn tất được file âm thanh.',503);@chmod($path,0600);
        try{$result=bz_audio_global_set($db,$actor,$meta['kind'],['id'=>$id,'name'=>$meta['name'],'mime'=>$mime]);}catch(Throwable $e){@rename($path,$part);throw $e;}
        @unlink($manifest);return $result;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}

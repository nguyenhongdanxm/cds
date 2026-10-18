<?php
declare(strict_types=1);
require_once __DIR__.'/buzzer_store.php';
function bz_audio_kind($kind): string {if(!in_array($kind,['background','bell'],true))bz_fail('Loại âm thanh không hợp lệ.',422);return $kind;}
function bz_audio_dir(): string {return DATA_PATH.'/buzzer_audio';}
function bz_audio_path(string $id): string {if(!preg_match('/^[a-f0-9]{48}$/D',$id))bz_fail('Tệp âm thanh không hợp lệ.',404);return bz_audio_dir().'/'.$id.'.bin';}
function bz_audio_validate(string $path,int $size): string {
    if($size<1||$size>10*1024*1024)bz_fail('Mỗi file âm thanh tối đa 10 MB.',422);
    if(!class_exists('finfo'))bz_fail('Hosting cần bật PHP fileinfo để kiểm tra âm thanh.',503);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($path);
    if(!in_array($mime,['audio/mpeg','audio/wav','audio/x-wav','audio/ogg','application/ogg','audio/mp4','audio/x-m4a'],true))bz_fail('Chọn file MP3, WAV, OGG hoặc M4A hợp lệ.',422);
    return $mime==='application/ogg'?'audio/ogg':$mime;
}
function bz_audio_set(PDO $db,string $code,array $actor,string $kind,?array $media): array {
    bz_audio_kind($kind);if(($actor['role']??'')!=='host')bz_fail('Chỉ giáo viên quản lý phòng được cài âm thanh.',403);
    $db->beginTransaction();
    try{
        $row=bz_room($db,$code,true);bz_authorize($row,$actor);$s=$row['state'];
        if(in_array($s['status'],['running','answering','closed'],true))bz_fail('Cài âm thanh khi câu chưa bắt đầu hoặc đã kết thúc.');
        $old=$s['audio'][$kind]??null;$s['audio'][$kind]=$media;$s['version']++;
        $q=$db->prepare('UPDATE cds_buzzer_rooms SET state_json=? WHERE code=?');$q->execute([json_encode($s,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$code]);$view=bz_view($s,bz_now(),'host');$view['code']=$code;$view['screen_key']=$row['screen_key'];$db->commit();
        if($old&&($old['id']??'')!==($media['id']??''))@unlink(bz_audio_path($old['id']));
        return ['state'=>$view];
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function bz_audio_upload(PDO $db,string $code,array $actor,string $kind,array $file): array {
    bz_audio_kind($kind);if(($actor['role']??'')!=='host')bz_fail('Không có quyền tải âm thanh.',403);
    $row=bz_room($db,$code);bz_authorize($row,$actor);
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)bz_fail('Chưa tải được file. Kiểm tra giới hạn upload của hosting (tối đa 10 MB).',422);
    $tmp=(string)($file['tmp_name']??'');if(!is_uploaded_file($tmp))bz_fail('Tệp tải lên không hợp lệ.',422);
    $mime=bz_audio_validate($tmp,(int)filesize($tmp));$id=bin2hex(random_bytes(24));$dir=bz_audio_dir();
    if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))bz_fail('Không tạo được thư mục âm thanh.',503);
    if(!is_file($dir.'/.htaccess')&&file_put_contents($dir.'/.htaccess',"Require all denied\nDeny from all\n")===false)bz_fail('Không bảo vệ được thư mục âm thanh.',503);
    $path=bz_audio_path($id);if(!move_uploaded_file($tmp,$path))bz_fail('Không lưu được âm thanh.',503);@chmod($path,0600);
    try{$name=mb_substr((string)($file['name']??'Âm thanh'),0,80,'UTF-8');$result=bz_audio_set($db,$code,$actor,$kind,['id'=>$id,'name'=>bz_text($name,80),'mime'=>$mime]);}
    catch(Throwable $e){@unlink($path);throw $e;}
    // Chỉ dọn file riêng của tiện ích đã quá 48 giờ; phòng hết hạn sau 24 giờ.
    $removed=0;foreach(new DirectoryIterator($dir)as$f){if($removed>=100)break;if($f->isFile()&&preg_match('/^[a-f0-9]{48}\.bin$/D',$f->getFilename())&&$f->getMTime()<time()-172800){@unlink($f->getPathname());$removed++;}}
    return $result;
}
function bz_audio_file(PDO $db,string $code,array $actor,string $kind): array {
    bz_audio_kind($kind);if(!in_array($actor['role']??'',['host','screen'],true))bz_fail('Không có quyền đọc âm thanh.',403);
    $row=bz_room($db,$code);bz_authorize($row,$actor);$s=bz_audio_effective($db,$row['state']);$meta=$s['audio'][$kind]??null;
    if(!$meta||!is_file($path=bz_audio_path($meta['id'])))bz_fail('Không tìm thấy âm thanh.',404);
    return ['path'=>$path,'mime'=>$meta['mime']];
}

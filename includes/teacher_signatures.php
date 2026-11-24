<?php
/** Canonical teacher signature registry. Images and document snapshots are immutable. */
if(is_file(__DIR__.'/database_sql_read.php'))require_once __DIR__.'/database_sql_read.php';
function cds_ts_data_dir(): string {return defined('CDS_TEACHER_SIGNATURE_DATA')?CDS_TEACHER_SIGNATURE_DATA:dirname(__DIR__).'/data';}
function cds_ts_legacy_dir(): string {return defined('CDS_TEACHER_SIGNATURE_LEGACY_DATA')?CDS_TEACHER_SIGNATURE_LEGACY_DATA:dirname(__DIR__).'/chuyenmon/data';}
function cds_ts_norm(string $s): string { $s=trim(preg_replace('/\s+/u',' ',$s)??$s);return function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s); }
function cds_ts_teachers(): array {if(function_exists('csdl_teachers_all'))return csdl_teachers_all();if(function_exists('cds_core_sql_rows')){$rows=cds_core_sql_rows('teachers');if(is_array($rows))return$rows;}return (array)load_json(cds_ts_data_dir().'/teachers.json',[]);}
function cds_ts_teacher(array $user,?array $teachers=null): ?array {
    $teachers=$teachers??cds_ts_teachers();$id=trim((string)($user['teacher_id']??''));
    if($id!==''){foreach($teachers as$t)if((string)($t['id']??'')===$id)return$t;return null;}
    $name=cds_ts_norm((string)($user['teacher_name']??$user['name']??$user['full_name']??''));if($name==='')return null;
    $matches=array_values(array_filter($teachers,static fn($t)=>cds_ts_norm((string)($t['name']??''))===$name));return count($matches)===1?$matches[0]:null;
}
function cds_ts_path(string $path): string {
    if($path==='')return '';foreach([cds_ts_data_dir().'/teacher_signature_images',cds_ts_legacy_dir().'/lesson_book_signatures',cds_ts_data_dir().'/lesson_book_signatures']as$directory){
        $folder=basename($directory);$base=realpath($directory);if(!$base)continue;
        $candidate=realpath($path);if(!$candidate&&str_contains(str_replace('\\','/',$path),'/'.$folder.'/'))$candidate=realpath($base.'/'.basename($path));
        if($candidate&&str_starts_with($candidate,$base.DIRECTORY_SEPARATOR)&&is_file($candidate))return$candidate;
    }return '';
}
function cds_ts_data_uri(?array $signature): string {
    $path=cds_ts_path((string)($signature['path']??''));if($path===''||filesize($path)>5*1024*1024)return '';
    $info=@getimagesize($path);$mime=$info['mime']??'';if(!in_array($mime,['image/png','image/jpeg','image/webp'],true))return '';
    $raw=@file_get_contents($path);return $raw===false?'':'data:'.$mime.';base64,'.base64_encode($raw);
}
function cds_ts_legacy_map(): array {
    $teachers=cds_ts_teachers();$users=function_exists('get_users')?get_users():(array)load_json(cds_ts_data_dir().'/users.json',[]);$byUser=[];foreach($users as$u)$byUser[(string)($u['id']??'')]=$u;
    $legacy=array_merge((array)load_json(cds_ts_data_dir().'/lesson_book_signatures.json',[]),(array)load_json(cds_ts_legacy_dir().'/lesson_book_signatures.json',[]));
    $map=[];foreach($legacy as$sig){
        if(!is_array($sig)||cds_ts_data_uri($sig)==='')continue;
        $uid=(string)($sig['user_id']??'');$teacher=null;
        if(isset($byUser[$uid]))$teacher=cds_ts_teacher($byUser[$uid],$teachers);
        else $teacher=cds_ts_teacher(['teacher_name'=>$sig['teacher']??''],$teachers);
        if(!$teacher)continue;$tid=(string)$teacher['id'];
        // Existing append order is a tiebreaker; timestamps select the newest version.
        if(isset($map[$tid])&&strcmp((string)($sig['created_at']??$sig['version']??''),(string)($map[$tid]['created_at']??$map[$tid]['version']??''))<0)continue;
        $map[$tid]=array_merge($sig,['teacher_id'=>$tid,'path'=>cds_ts_path((string)$sig['path']),'source'=>'lesson_book']);
    }return$map;
}
function cds_ts_meta(string $teacherId): ?array {
    if($teacherId==='')return null;$rows=(array)load_json(cds_ts_data_dir().'/teacher_signatures.json',[]);
    foreach(array_reverse($rows)as$row)if(is_array($row)&&($row['teacher_id']??'')===$teacherId)return empty($row['deleted'])&&cds_ts_data_uri($row)!==''?$row:null;
    return cds_ts_legacy_map()[$teacherId]??null;
}
function cds_ts_for_user(array $user): ?array {$teacher=cds_ts_teacher($user);return$teacher?cds_ts_meta((string)$teacher['id']):null;}
function cds_ts_migrate_legacy(): array {
    $map=cds_ts_legacy_map();$count=0;if(!$map)return ['ok'=>true,'count'=>0];
    $ok=cds_json_update(cds_ts_data_dir().'/teacher_signatures.json',static function($rows)use($map,&$count){$seen=[];foreach($rows as$r)if(is_array($r))$seen[(string)($r['teacher_id']??'')]=true;foreach($map as$tid=>$sig)if(!isset($seen[$tid])){$rows[]=$sig;$count++;}return $count?$rows:false;},[]);
    return ['ok'=>$count===0||$ok,'count'=>$ok?$count:0];
}
function cds_ts_can_manage(string $teacherId,array $user): bool {
    if(($user['role']??'')==='viewer')return false;
    if(($user['role']??'')==='admin'||(function_exists('can_perm_level')&&can_perm_level('csdl.teachers','edit')))return true;
    $own=cds_ts_teacher($user);return$own&&(string)$own['id']===$teacherId;
}
function cds_ts_upload(string $teacherId,array $file,array $actor): array {
    $fail=static fn($message)=>['ok'=>false,'message'=>$message];
    if(!cds_ts_can_manage($teacherId,$actor))return$fail('Không có quyền thay chữ ký giáo viên này.');
    $exists=false;foreach(cds_ts_teachers()as$t)if((string)($t['id']??'')===$teacherId)$exists=true;if(!$exists)return$fail('Không tìm thấy giáo viên.');
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)return$fail('Chưa nhận được ảnh chữ ký.');
    $tmp=(string)($file['tmp_name']??'');if(!is_uploaded_file($tmp))return$fail('Tệp tải lên không hợp lệ.');
    if(filesize($tmp)<1||filesize($tmp)>5*1024*1024)return$fail('Ảnh chữ ký tối đa 5 MB.');
    $info=@getimagesize($tmp);$mime=$info['mime']??'';$w=(int)($info[0]??0);$h=(int)($info[1]??0);
    if(!in_array($mime,['image/png','image/jpeg','image/webp'],true)||$w<1||$h<1||$w>6000||$h>6000||$w*$h>20000000)return$fail('Chọn ảnh PNG, JPG hoặc WebP hợp lệ, tối đa 6000 px và 20 triệu điểm ảnh.');
    $dir=cds_ts_data_dir().'/teacher_signature_images';if(!is_dir($dir)&&!@mkdir($dir,0750,true))return$fail('Không tạo được thư mục chữ ký.');
    $version=date('YmdHis').'_'.bin2hex(random_bytes(6));$path=$dir.'/'.hash('sha256',$teacherId).'_'.$version.'.png';
    if(!function_exists('imagecreatetruecolor')){
        if($mime!=='image/png'||$w!==480||$h!==160)return$fail('Hosting chưa bật GD. Hãy dùng ảnh PNG chuẩn 480 × 160 px.');
        if(!move_uploaded_file($tmp,$path))return$fail('Không lưu được ảnh chữ ký.');
    }else{
        $src=match($mime){'image/png'=>@imagecreatefrompng($tmp),'image/jpeg'=>@imagecreatefromjpeg($tmp),'image/webp'=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($tmp):false};if(!$src)return$fail('Không đọc được ảnh chữ ký.');
        if(!imageistruecolor($src))imagepalettetotruecolor($src);
        $minX=$w;$minY=$h;$maxX=-1;$maxY=-1;
        for($y=0;$y<$h;$y++)for($x=0;$x<$w;$x++){$c=imagecolorat($src,$x,$y);if((($c>>24)&127)<120&&min(($c>>16)&255,($c>>8)&255,$c&255)<238){$minX=min($minX,$x);$maxX=max($maxX,$x);$minY=min($minY,$y);$maxY=max($maxY,$y);}}
        if($maxX<0){imagedestroy($src);return$fail('Không nhận thấy nét chữ ký trong ảnh.');}
        $cw=$maxX-$minX+1;$ch=$maxY-$minY+1;$scale=min(440/$cw,120/$ch);$nw=max(1,(int)round($cw*$scale));$nh=max(1,(int)round($ch*$scale));
        $dst=imagecreatetruecolor(480,160);imagealphablending($dst,false);imagesavealpha($dst,true);imagefill($dst,0,0,imagecolorallocatealpha($dst,255,255,255,127));imagecopyresampled($dst,$src,(int)((480-$nw)/2),(int)((160-$nh)/2),$minX,$minY,$nw,$nh,$cw,$ch);
        $saved=imagepng($dst,$path,9);imagedestroy($src);imagedestroy($dst);if(!$saved)return$fail('Không lưu được ảnh chữ ký.');
    }
    @chmod($path,0640);$row=['teacher_id'=>$teacherId,'path'=>$path,'version'=>$version,'created_at'=>date('c'),'updated_by'=>(string)($actor['id']??''),'source'=>'csdl'];
    if(!cds_json_update(cds_ts_data_dir().'/teacher_signatures.json',static function($rows)use($row){$rows[]=$row;return$rows;},[])){@unlink($path);return$fail('Không lưu được dữ liệu chữ ký.');}
    return ['ok'=>true,'message'=>'Đã lưu chữ ký dùng chung trong CSDL.','signature'=>$row];
}
function cds_ts_remove(string $teacherId,array $actor): bool {
    if(!cds_ts_can_manage($teacherId,$actor))return false;
    return cds_json_update(cds_ts_data_dir().'/teacher_signatures.json',static function($rows)use($teacherId,$actor){$rows[]=['teacher_id'=>$teacherId,'deleted'=>true,'created_at'=>date('c'),'updated_by'=>$actor['id']??''];return$rows;},[]);
}
/** Freeze the current version only when this user explicitly confirms their own document. */
function cds_ts_snapshot(array $actor,string $teacherName): ?array {
    if(($actor['role']??'')==='viewer')return null;
    $t=cds_ts_teacher($actor);$target=cds_ts_teacher(['name'=>$teacherName]);if(!$t||!$target||(string)$target['id']!==(string)$t['id']||cds_ts_norm((string)$t['name'])!==cds_ts_norm($teacherName))return null;
    $sig=cds_ts_meta((string)$t['id']);if(!$sig)return null;return array_merge($sig,['signed_by'=>$t['name'],'signed_at'=>date('c')]);
}

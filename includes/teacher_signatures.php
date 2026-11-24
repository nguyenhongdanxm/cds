<?php
/** Canonical teacher signature registry. Images and document snapshots are immutable. */
if(is_file(__DIR__.'/database_sql_read.php'))require_once __DIR__.'/database_sql_read.php';
function cds_ts_data_dir(): string {return defined('CDS_TEACHER_SIGNATURE_DATA')?CDS_TEACHER_SIGNATURE_DATA:dirname(__DIR__).'/data';}
function cds_ts_legacy_dir(): string {return defined('CDS_TEACHER_SIGNATURE_LEGACY_DATA')?CDS_TEACHER_SIGNATURE_LEGACY_DATA:dirname(__DIR__).'/chuyenmon/data';}
function cds_ts_norm(string $s): string { $s=trim(preg_replace('/\s+/u',' ',$s)??$s);return function_exists('mb_strtolower')?mb_strtolower($s,'UTF-8'):strtolower($s); }
function cds_ts_teachers(): array {if(function_exists('csdl_teachers_all'))return csdl_teachers_all();if(function_exists('cds_core_sql_rows')){$rows=cds_core_sql_rows('teachers');if(is_array($rows))return$rows;}return (array)load_json(cds_ts_data_dir().'/teachers.json',[]);}
/** Exact links first, then unique contact identifiers, exact names, and unique short suffix aliases. */
function cds_ts_name_alias(string $name): string {return preg_replace('/\s*\([a-zđ]{1,4}\)\s*$/iu','',cds_ts_norm($name))??cds_ts_norm($name);}
function cds_ts_phone(string $phone): string {$phone=preg_replace('/\D+/','',$phone)??'';if(str_starts_with($phone,'84')&&strlen($phone)===11)$phone='0'.substr($phone,2);return $phone;}
function cds_ts_teacher(array $user,?array $teachers=null): ?array {
    $teachers=$teachers??cds_ts_teachers();$id=trim((string)($user['teacher_id']??''));
    if($id!==''){foreach($teachers as$t)if((string)($t['id']??'')===$id)return$t;return null;}
    $contacts=[];$phone=cds_ts_phone((string)($user['phone']??''));if($phone===''&&preg_match('/^\+?[0-9]{9,12}$/D',(string)($user['username']??'')))$phone=cds_ts_phone((string)$user['username']);
    foreach(['phone'=>$phone,'email'=>cds_ts_norm((string)($user['email']??'')),'code'=>cds_ts_norm((string)($user['teacher_code']??''))]as$field=>$wanted){
        if($wanted==='')continue;$matches=array_values(array_filter($teachers,static fn($t)=>($field==='phone'?cds_ts_phone((string)($t[$field]??'')):cds_ts_norm((string)($t[$field]??'')))===$wanted));
        if(count($matches)===1)$contacts[(string)$matches[0]['id']]=$matches[0];
    }
    if(count($contacts)>1)return null;if(count($contacts)===1)return reset($contacts);
    $name='';foreach(['teacher_name','name','full_name']as$field){$name=cds_ts_norm((string)($user[$field]??''));if($name!=='')break;}if($name==='')return null;
    $matches=array_values(array_filter($teachers,static fn($t)=>cds_ts_norm((string)($t['name']??''))===$name));
    if(count($matches)>0)return count($matches)===1?$matches[0]:null;
    $alias=cds_ts_name_alias($name);$matches=array_values(array_filter($teachers,static fn($t)=>cds_ts_name_alias((string)($t['name']??''))===$alias));
    return count($matches)===1?$matches[0]:null;
}
/** Editing/signing is bound to an existing teacher ID, imported account ID, or immutable phone login. */
function cds_ts_actor_teacher(array $actor): ?array {
    if(!empty($actor['teacher_id']))return cds_ts_teacher(['teacher_id'=>$actor['teacher_id']]);
    $id=(string)($actor['id']??'');$ids=[];
    if($id!=='')foreach((array)load_json(cds_ts_data_dir().'/teacher_signatures.json',[])as$row)if(is_array($row)&&($row['user_id']??'')===$id&&!empty($row['teacher_id']))$ids[(string)$row['teacher_id']]=true;
    if(count($ids)>1)return null;if(count($ids)===1)return cds_ts_teacher(['teacher_id'=>(string)array_key_first($ids)]);
    $login=(string)($actor['username']??'');if(!preg_match('/^\+?[0-9]{9,12}$/D',$login))return null;
    return cds_ts_teacher(['phone'=>cds_ts_phone($login)]);
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
function cds_ts_legacy_scan(): array {
    $teachers=cds_ts_teachers();$users=function_exists('get_users')?get_users():(array)load_json(cds_ts_data_dir().'/users.json',[]);$byUser=[];foreach($users as$u){$uid=(string)($u['id']??'');if($uid!=='')$byUser[$uid]=$u;}
    $legacy=array_merge((array)load_json(cds_ts_data_dir().'/lesson_book_signatures.json',[]),(array)load_json(cds_ts_legacy_dir().'/lesson_book_signatures.json',[]));
    $result=['map'=>[],'sources'=>[],'total'=>0,'unmatched'=>0,'missing_images'=>0];
    foreach($legacy as$sig){
        if(!is_array($sig))continue;$result['total']++;if(cds_ts_data_uri($sig)===''){$result['missing_images']++;continue;}
        $uid=(string)($sig['user_id']??'');$account=$byUser[$uid]??[];$teacher=null;
        if($account)$teacher=cds_ts_teacher($account,$teachers);else $teacher=cds_ts_teacher(['teacher_name'=>$sig['teacher']??''],$teachers);
        // Contacts cannot silently move a saved signature to a different named person.
        $sourceName=trim((string)($sig['teacher']??''));if($teacher&&empty($account['teacher_id'])&&$sourceName!==''&&cds_ts_name_alias($sourceName)!==cds_ts_name_alias((string)($teacher['name']??'')))$teacher=null;
        // For an account without an explicit link, the saved signature name can recover an old display name.
        if(!$teacher&&empty($account['teacher_id'])&&empty($account['phone'])&&empty($account['email'])&&empty($account['teacher_code'])&&!preg_match('/^\+?[0-9]{9,12}$/D',(string)($account['username']??'')))$teacher=cds_ts_teacher(['teacher_name'=>$sig['teacher']??''],$teachers);
        $key=hash('sha256',json_encode($sig,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $result['sources'][$key]=['signature'=>$sig,'label'=>trim((string)($sig['teacher']??''))?:trim((string)($account['teacher_name']??$account['name']??''))?:'Chưa xác định giáo viên','teacher_id'=>$teacher?(string)$teacher['id']:''];
        if(!$teacher){$result['unmatched']++;continue;}$tid=(string)$teacher['id'];
        if(isset($result['map'][$tid])&&strcmp((string)($sig['created_at']??$sig['version']??''),(string)($result['map'][$tid]['created_at']??$result['map'][$tid]['version']??''))<0)continue;
        $result['map'][$tid]=array_merge($sig,['teacher_id'=>$tid,'path'=>cds_ts_path((string)$sig['path']),'source'=>'lesson_book']);
    }return $result;
}
function cds_ts_legacy_map(): array {return cds_ts_legacy_scan()['map'];}
/** An explicit administrator/owner selection never accepts client-supplied file paths. */
function cds_ts_link_legacy(string $teacherId,string $sourceKey,array $actor): array {
    if(!cds_ts_can_manage($teacherId,$actor))return ['ok'=>false,'message'=>'Không có quyền đồng bộ chữ ký giáo viên này.'];
    $teacher=null;foreach(cds_ts_teachers()as$t)if((string)($t['id']??'')===$teacherId)$teacher=$t;
    $source=cds_ts_legacy_scan()['sources'][$sourceKey]??null;
    if(!$teacher||!$source)return ['ok'=>false,'message'=>'Không tìm thấy giáo viên hoặc ảnh chữ ký nguồn.'];
    // Only CSDL editors may resolve a legacy signature belonging to an unresolved account.
    $editor=($actor['role']??'')==='admin'||(function_exists('can_perm_level')&&can_perm_level('csdl.teachers','edit'));
    if(!$editor&&($source['teacher_id']??'')!==$teacherId)return ['ok'=>false,'message'=>'Chữ ký nguồn chưa ghép đúng hồ sơ của bạn.'];
    $sig=array_merge($source['signature'],['teacher_id'=>$teacherId,'path'=>cds_ts_path((string)$source['signature']['path']),'source'=>'lesson_book_manual_link','linked_at'=>date('c'),'linked_by'=>$actor['id']??'']);
    $ok=cds_json_update(cds_ts_data_dir().'/teacher_signatures.json',static function($rows)use($sig,$teacherId){foreach(array_reverse($rows)as$r)if(($r['teacher_id']??'')===$teacherId){if(empty($r['deleted']))return false;break;}$rows[]=$sig;return$rows;},[]);
    return ['ok'=>$ok,'message'=>$ok?'Đã lấy chữ ký Sổ đầu bài vào hồ sơ giáo viên.':'Chữ ký CSDL đã có hoặc không lưu được. Hãy tải lại để kiểm tra.'];
}
function cds_ts_meta(string $teacherId): ?array {
    if($teacherId==='')return null;$rows=(array)load_json(cds_ts_data_dir().'/teacher_signatures.json',[]);
    foreach(array_reverse($rows)as$row)if(is_array($row)&&($row['teacher_id']??'')===$teacherId)return empty($row['deleted'])&&cds_ts_data_uri($row)!==''?$row:null;
    return cds_ts_legacy_map()[$teacherId]??null;
}
function cds_ts_for_user(array $user): ?array {$teacher=cds_ts_actor_teacher($user);return$teacher?cds_ts_meta((string)$teacher['id']):null;}
function cds_ts_migrate_legacy(): array {
    $map=cds_ts_legacy_map();$count=0;if(!$map)return ['ok'=>true,'count'=>0];
    $ok=cds_json_update(cds_ts_data_dir().'/teacher_signatures.json',static function($rows)use($map,&$count){$seen=[];foreach($rows as$r)if(is_array($r))$seen[(string)($r['teacher_id']??'')]=true;foreach($map as$tid=>$sig)if(!isset($seen[$tid])){$rows[]=$sig;$count++;}return $count?$rows:false;},[]);
    return ['ok'=>$count===0||$ok,'count'=>$ok?$count:0];
}
function cds_ts_can_manage(string $teacherId,array $user): bool {
    if(($user['role']??'')==='viewer')return false;
    if(($user['role']??'')==='admin'||(function_exists('can_perm_level')&&can_perm_level('csdl.teachers','edit')))return true;
    $own=cds_ts_actor_teacher($user);return$own&&(string)$own['id']===$teacherId;
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
    $t=cds_ts_actor_teacher($actor);$target=cds_ts_teacher(['name'=>$teacherName]);if(!$t||!$target||(string)$target['id']!==(string)$t['id'])return null;
    $sig=cds_ts_meta((string)$t['id']);if(!$sig)return null;return array_merge($sig,['signed_by'=>$t['name'],'signed_at'=>date('c')]);
}

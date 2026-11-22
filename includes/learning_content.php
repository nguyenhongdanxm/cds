<?php
function lc_schema(string $section='resource'): array {
    if($section==='exam')return array_fill_keys(['Đề kiểm tra','Thi thử','Kỳ thi'],['upload','gdrive','link']);
    return ['Bài viết'=>['article','upload','gdrive','link'],'Văn bản'=>['upload','gdrive','link'],'Ứng dụng'=>['html','gdrive','link'],'Video'=>['upload','youtube','vimeo','gdrive','video_url'],'Trình chiếu'=>['upload','gdrive','link'],'Hình ảnh'=>['upload','gdrive','image_url'],'Trò chơi'=>['html','gdrive','link']];
}
function lc_sources(): array {return ['article'=>'Soạn bài viết trực tiếp','upload'=>'Tải tệp lên','html'=>'Tải tệp HTML tương tác','gdrive'=>'Google Drive — xem trong trang','link'=>'Liên kết website / tài liệu','youtube'=>'Liên kết YouTube','vimeo'=>'Liên kết Vimeo','video_url'=>'Liên kết tệp video (MP4, WebM, OGV)','image_url'=>'Liên kết hình ảnh'];}
function lc_extensions(string $kind,string $source): array {
    if($source==='html')return ['html','htm'];
    return match($kind){'Video'=>['mp4','webm','ogv'],'Hình ảnh'=>['png','jpg','jpeg','gif','webp'],'Trình chiếu'=>['ppt','pptx','odp','pdf'],'Văn bản'=>['pdf','doc','docx','odt','rtf','txt'],default=>['pdf','doc','docx','odt','rtf','txt','ppt','pptx','odp','xls','xlsx','ods']};
}
function lc_web_url(string $url): string {
    $url=trim($url);$p=parse_url($url);
    return is_array($p)&&in_array(strtolower($p['scheme']??''),['http','https'],true)&&!empty($p['host'])&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['port'])&&!preg_match('/[\x00-\x20\\\\]/',$url)?$url:'';
}
function lc_youtube(string $url): string {
    if(!lc_web_url($url))return '';$p=parse_url($url);$host=strtolower($p['host']);$path=$p['path']??'';parse_str($p['query']??'',$q);$id='';
    if($host==='youtu.be')$id=trim($path,'/');
    elseif(in_array($host,['youtube.com','www.youtube.com','m.youtube.com','www.youtube-nocookie.com','youtube-nocookie.com'],true)){
        if($path==='/watch')$id=is_string($q['v']??null)?$q['v']:'';
        elseif(preg_match('~^/(?:embed|shorts|live)/([A-Za-z0-9_-]{11})/?$~',$path,$m))$id=$m[1];
    }
    return preg_match('/^[A-Za-z0-9_-]{11}$/D',$id)?'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0':'';
}
function lc_vimeo(string $url): string {
    if(!lc_web_url($url))return '';$p=parse_url($url);if(!in_array(strtolower($p['host']),['vimeo.com','www.vimeo.com','player.vimeo.com'],true))return '';
    if(!preg_match('~^/(?:video/)?([0-9]{1,15})(?:/([A-Za-z0-9]{6,64}))?/?$~',$p['path']??'',$m))return '';
    parse_str($p['query']??'',$q);$hash=$m[2]??($q['h']??'');if(!is_string($hash)||($hash!==''&&!preg_match('/^[A-Za-z0-9]{6,64}$/D',$hash)))return '';
    return 'https://player.vimeo.com/video/'.$m[1].($hash!==''?'?h='.$hash:'');
}
/** Normalize only known Google file URLs; never embed arbitrary supplied HTML. */
function lc_drive(string $url): string {
    if(!lc_web_url($url))return '';
    $p=parse_url($url);$host=strtolower($p['host']);$path=$p['path']??'';parse_str($p['query']??'',$q);$id='';
    if(in_array($host,['drive.google.com','www.drive.google.com'],true)){
        if(preg_match('~^/file/(?:u/[0-9]+/)?d/([A-Za-z0-9_-]{10,200})(?:/(?:view|preview|edit))?/?$~D',$path,$m))$id=$m[1];
        elseif(in_array($path,['/open','/uc'],true)&&is_string($q['id']??null))$id=$q['id'];
    }elseif(in_array($host,['docs.google.com','www.docs.google.com'],true)&&preg_match('~^/(?:document|spreadsheets|presentation)/(?:u/[0-9]+/)?d/([A-Za-z0-9_-]{10,200})(?:/(?:edit|view|preview|viewform|present|copy|export))?/?$~D',$path,$m))$id=$m[1];
    if(!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$id))return '';
    $key=$q['resourcekey']??'';if(!is_string($key)||($key!==''&&!preg_match('/^[A-Za-z0-9_-]{1,200}$/D',$key)))return '';
    $src='https://drive.google.com/file/d/'.$id.'/preview';
    return $src.($key!==''?(str_contains($src,'?')?'&':'?').'resourcekey='.rawurlencode($key):'');
}
function lc_validate_choice(string $section,string $kind,string $source,string $url): string {
    $schema=lc_schema($section);if(!isset($schema[$kind])||!in_array($source,$schema[$kind],true))throw new InvalidArgumentException('Loại và hình thức nội dung chưa phù hợp. Hãy chọn lại.');
    if(in_array($source,['article','upload','html'],true))return '';
    $url=lc_web_url($url);if($url==='')throw new InvalidArgumentException('Vui lòng nhập liên kết http:// hoặc https:// hợp lệ.');
    if($source==='gdrive'&&lc_drive($url)==='')throw new InvalidArgumentException('Vui lòng dán link tệp Google Drive, Google Docs, Sheets hoặc Slides hợp lệ; không dùng link thư mục.');
    if($source==='youtube'&&lc_youtube($url)==='')throw new InvalidArgumentException('Vui lòng nhập liên kết video YouTube hợp lệ.');
    if($source==='vimeo'&&lc_vimeo($url)==='')throw new InvalidArgumentException('Vui lòng nhập liên kết video Vimeo hợp lệ.');
    if(in_array($source,['video_url','image_url'],true)&&parse_url($url,PHP_URL_SCHEME)!=='https')throw new InvalidArgumentException('Liên kết video/hình ảnh cần bắt đầu bằng https://.');
    if($source==='video_url'&&!preg_match('/\.(mp4|webm|ogv)$/i',parse_url($url,PHP_URL_PATH)??''))throw new InvalidArgumentException('Liên kết video trực tiếp cần trỏ tới tệp .mp4, .webm hoặc .ogv; hãy chọn YouTube/Vimeo cho liên kết từ các nền tảng đó.');
    return $url;
}
function lc_upload_mime(string $kind,string $source,string $name,string $bytes,string $detected): string {
    $extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($extension,lc_extensions($kind,$source),true))throw new InvalidArgumentException('Tệp tải lên không đúng định dạng của loại nội dung đã chọn.');
    if($source==='html')return 'text/html';
    if($kind==='Hình ảnh'){$info=@getimagesizefromstring($bytes);if(!$info||!in_array($info['mime']??'',['image/png','image/jpeg','image/gif','image/webp'],true))throw new InvalidArgumentException('Tệp hình ảnh không hợp lệ.');return $info['mime'];}
    if($kind==='Video'){
        $valid=match($extension){'mp4'=>substr($bytes,4,4)==='ftyp','webm'=>str_starts_with($bytes,"\x1a\x45\xdf\xa3"),'ogv'=>str_starts_with($bytes,'OggS'),default=>false};
        if(!$valid)throw new InvalidArgumentException('Tệp video không hợp lệ. Hỗ trợ MP4, WebM và OGV.');
        return ['mp4'=>'video/mp4','webm'=>'video/webm','ogv'=>'video/ogg'][$extension];
    }
    return $detected;
}
/** One authorization gate for the wrapper, authored HTML and stored file bytes. */
function lc_require_item(string $id): array {
    $data=load_json(DATA_PATH.'/learning_hub.json',['items'=>[]]);$row=null;foreach((array)($data['items']??[])as$item)if(is_array($item)&&($item['id']??'')===$id){$row=$item;break;}
    if(!$row){http_response_code(404);exit('Không tìm thấy học liệu.');}
    $user=current_user()??[];$admin=($user['role']??'')==='admin';$code=($row['section']??'resource')==='exam'?'hl.kiemtra':'hl.xem';
    if(!$admin&&!can_perm_level($code,'view')){http_response_code(403);exit('Bạn chưa có quyền xem nội dung này.');}
    if(empty($row['approved'])&&!$admin&&!cds_is_viewer($user)&&($row['author_id']??'')!==(string)($user['id']??$user['username']??'')){http_response_code(403);exit('Nội dung chưa được duyệt.');}
    return $row;
}
function lc_preview(array $row): array {
    $source=(string)($row['source_kind']??'');$kind=(string)($row['kind']??'');$self=BASE_URL.'hoclieu_file.php?id='.rawurlencode((string)$row['id']);
    if($source==='article'||$source==='html')return ['mode'=>'frame','src'=>$self,'trusted'=>false];
    if(in_array($source,['link','youtube','vimeo','gdrive','video_url','image_url'],true)){
        $url=lc_web_url((string)($row['url']??''));if($url==='')return ['mode'=>'unavailable','src'=>''];
        if(($drive=lc_drive($url))!=='')return ['mode'=>'frame','src'=>$drive,'trusted'=>true];
        if($source==='gdrive')return ['mode'=>'unavailable','src'=>''];
        if($source==='youtube'||($kind==='Video'&&lc_youtube($url)!==''))return ['mode'=>'frame','src'=>lc_youtube($url),'trusted'=>true];
        if($source==='vimeo'||($kind==='Video'&&lc_vimeo($url)!==''))return ['mode'=>'frame','src'=>lc_vimeo($url),'trusted'=>true];
        if($source==='video_url'||($kind==='Video'&&preg_match('/\.(mp4|webm|ogv)$/i',parse_url($url,PHP_URL_PATH)??'')))return ['mode'=>'video','src'=>$url];
        if($source==='image_url')return ['mode'=>'image','src'=>$url];
        return ['mode'=>'frame','src'=>$url,'trusted'=>false];
    }
    if($source==='upload'&&in_array($kind,['Video','Hình ảnh'],true))return ['mode'=>$kind==='Video'?'video':'image','src'=>$self.'&inline=1'];
    $path=(string)($row['file_path']??'');if(preg_match('/^gdrive:([A-Za-z0-9_-]{10,200})$/D',$path,$m))return ['mode'=>'frame','src'=>'https://drive.google.com/file/d/'.$m[1].'/preview','trusted'=>true];
    return ['mode'=>'unavailable','src'=>''];
}

/** Only original files and recognized Google files offer a download. */
function lc_drive_download_url(string $url): string {
    $preview=lc_drive($url);if($preview==='')return '';
    $p=parse_url($url);$path=$p['path']??'';
    preg_match('~/file/d/([A-Za-z0-9_-]+)/preview~',$preview,$m);$id=$m[1];
    parse_str(parse_url($preview,PHP_URL_QUERY)??'',$q);$key=isset($q['resourcekey'])?'&resourcekey='.rawurlencode($q['resourcekey']):'';
    if(in_array(strtolower($p['host']),['docs.google.com','www.docs.google.com'],true)&&preg_match('~^/(document|spreadsheets|presentation)/~',$path,$type)){
        $format=['document'=>'pdf','spreadsheets'=>'xlsx','presentation'=>'pptx'][$type[1]];
        return 'https://docs.google.com/'.$type[1].'/d/'.$id.'/export?format='.$format.$key;
    }
    return 'https://drive.google.com/uc?export=download&id='.$id.$key;
}
function lc_can_download(array $row): bool {
    if(in_array($row['source_kind']??'',['link','gdrive','video_url','image_url'],true)&&lc_drive_download_url((string)($row['url']??''))!=='')return true;
    return in_array($row['source_kind']??'',['upload','html'],true)&&preg_match('/^gdrive:[A-Za-z0-9_-]{10,200}$/D',(string)($row['file_path']??''))===1;
}

function lc_byte_range(string $range,int $size): ?array {
    if($range==='')return null;
    if($size<1||!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$m)||($m[1]===''&&$m[2]===''))throw new InvalidArgumentException('Khoảng dữ liệu không hợp lệ.');
    if($m[1]===''){$suffix=(int)$m[2];if($suffix<1)throw new InvalidArgumentException('Khoảng dữ liệu không hợp lệ.');return [max(0,$size-$suffix),$size-1];}
    $start=(int)$m[1];$end=$m[2]===''?$size-1:min($size-1,(int)$m[2]);if($start>=$size||$end<$start)throw new InvalidArgumentException('Khoảng dữ liệu không hợp lệ.');return [$start,$end];
}

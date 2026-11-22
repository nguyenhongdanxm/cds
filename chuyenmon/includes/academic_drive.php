<?php
/** Read-only, root-bound Google Drive navigation. Credentials stay on the server. */
function acd_id(string $id): bool { return preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$id)===1; }
function acd_key(string $key): bool { return $key===''||preg_match('/^[A-Za-z0-9_-]{1,200}$/D',$key)===1; }
function acd_folder_link(string $value): array {
    $value=trim($value);$id=$value;$key='';
    if(!acd_id($id)){
        $url=parse_url($value);
        if(!is_array($url)||($url['scheme']??'')!=='https'||!in_array(strtolower((string)($url['host']??'')),['drive.google.com','www.drive.google.com'],true)||isset($url['user'])||isset($url['port']))throw new InvalidArgumentException('Nhập đường dẫn thư mục Google Drive hoặc ID hợp lệ.');
        parse_str((string)($url['query']??''),$query);$id='';
        if(preg_match('~/folders/([A-Za-z0-9_-]+)/*$~',(string)($url['path']??''),$m))$id=$m[1];
        elseif(in_array($url['path']??'',['/open','/drive/open'],true)&&is_string($query['id']??null))$id=$query['id'];
        $key=$query['resourcekey']??'';
    }
    if(!acd_id($id)||!is_string($key)||!acd_key($key))throw new InvalidArgumentException('Liên kết thư mục hoặc khóa truy cập không hợp lệ.');
    return ['id'=>$id,'resource_key'=>$key];
}
function acd_config_path(): string { return DATA_PATH.'/academic_drive_folder.json'; }
function acd_config(): array {
    $path=acd_config_path();if(!is_file($path))return ['id'=>'','resource_key'=>'','name'=>'','revision'=>''];
    $raw=json_decode((string)file_get_contents($path),true);
    if(!is_array($raw)||!is_string($raw['id']??null)||($raw['id']!==''&&!acd_id($raw['id']))||!is_string($raw['resource_key']??'')||!acd_key($raw['resource_key']??'')||!is_string($raw['revision']??'')||!is_string($raw['name']??''))throw new RuntimeException('Cấu hình Hồ sơ chuyên môn không đọc được. Hãy kiểm tra tệp cấu hình trước khi thay thế.');
    return $raw+['resource_key'=>'','name'=>'','revision'=>''];
}
function acd_config_save(array $next,string $revision): void {
    $ok=cds_json_update(acd_config_path(),static function($current)use($next,$revision){
        if(!is_array($current)||!hash_equals((string)($current['revision']??''),$revision))throw new RuntimeException('Thư mục đã được thay đổi ở một phiên khác. Hãy tải lại trang.');
        return array_replace($next,['revision'=>bin2hex(random_bytes(16)),'updated_at'=>date('c')]);
    },[]);
    if(!$ok)throw new RuntimeException('Không lưu được thư mục Hồ sơ chuyên môn.');
}
function acd_token(): string {
    $settings=cds_drive_settings();if(empty($settings['enabled']))throw new RuntimeException('Thư viện chưa được kết nối. Vui lòng liên hệ quản trị.');
    $result=cds_drive_token($settings);if(empty($result['ok'])||empty($result['token']))throw new RuntimeException('Chưa kết nối được kho hồ sơ. Vui lòng liên hệ quản trị.');
    return (string)$result['token'];
}
if(!function_exists('acd_transport')){
function acd_transport(string $url,array $headers): array {
    if(!function_exists('curl_init'))throw new RuntimeException('Hosting cần bật PHP cURL để đọc thư viện.');
    $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_FOLLOWLOCATION=>false]);
    $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    return ['status'=>$status,'body'=>$body===false?'':$body];
}}
function acd_request(string $route,array $query,string $token,array $keys=[]): array {
    if($route!=='files'&&!preg_match('~^files/[A-Za-z0-9_-]{10,200}$~D',$route))throw new InvalidArgumentException('Đường dẫn Drive không hợp lệ.');
    $headers=['Authorization: Bearer '.$token,'Accept: application/json'];$pairs=[];
    foreach($keys as$id=>$key)if(acd_id((string)$id)&&$key!==''&&acd_key((string)$key))$pairs[]=$id.'/'.$key;
    if($pairs)$headers[]='X-Goog-Drive-Resource-Keys: '.implode(',',array_slice($pairs,-30));
    $response=acd_transport('https://www.googleapis.com/drive/v3/'.$route.'?'.http_build_query($query),$headers);
    if($response['status']!==200){$messages=[401=>'Kết nối thư viện đã hết hiệu lực. Vui lòng liên hệ quản trị.',403=>'Nguồn lưu trữ chưa cho phép đọc nội dung này. Vui lòng liên hệ quản trị.',404=>'Thư mục hoặc tài liệu không còn tồn tại hoặc chưa được cấp quyền truy cập.'];throw new RuntimeException($messages[$response['status']]??'Không đọc được thư viện. Hãy thử lại sau.');}
    $data=json_decode($response['body'],true);if(!is_array($data))throw new RuntimeException('Dữ liệu thư viện không hợp lệ.');return $data;
}
function acd_metadata(string $id,string $token,array &$keys): array {
    if(!acd_id($id))throw new InvalidArgumentException('Mã thư mục không hợp lệ.');
    if(isset($keys[$id])){$key=$keys[$id];unset($keys[$id]);$keys[$id]=$key;}
    $item=acd_request('files/'.$id,['fields'=>'id,name,mimeType,parents,trashed,resourceKey,driveId,size,capabilities(canDownload)','supportsAllDrives'=>'true'],$token,$keys);
    if(($item['id']??'')!==$id||!empty($item['trashed']))throw new RuntimeException('Thư mục đã bị xóa hoặc không còn truy cập được.');
    $key=(string)($item['resourceKey']??'');if($key!==''&&acd_key($key))$keys[$id]=$key;return $item;
}
function acd_drive_url(string $id,bool $folder=false,string $key='',bool $preview=false): string {
    if(!acd_id($id)||!acd_key($key))return '';
    return 'https://drive.google.com/'.($folder?'drive/folders/'.$id:'file/d/'.$id.($preview?'/preview':'/view')).($key!==''?'?resourcekey='.rawurlencode($key):'');
}
/** Resolve a fresh parent chain for every listing; never trust client breadcrumbs or cached membership. */
function acd_path(array $config,string $folder,string $token,array &$keys): array {
    $root=(string)$config['id'];$keys[$root]=(string)$config['resource_key'];$trail=[];$seen=[];
    for($depth=0;$depth<20;$depth++){
        if(isset($seen[$folder]))break;$seen[$folder]=true;$item=acd_metadata($folder,$token,$keys);
        if(($item['mimeType']??'')!=='application/vnd.google-apps.folder')throw new RuntimeException('Đối tượng được chọn không phải thư mục.');
        $trail[]=['id'=>$folder,'name'=>(string)($item['name']??''),'drive_id'=>(string)($item['driveId']??'')];
        if($folder===$root)return array_reverse($trail);
        $parents=(array)($item['parents']??[]);$folder=(string)($parents[0]??'');if(!acd_id($folder))break;
    }
    throw new RuntimeException('Thư mục không nằm trong Hồ sơ chuyên môn đã liên kết hoặc cây thư mục quá sâu.');
}
function acd_browse(array $config,string $folder,string $sort,string $query,string $cursor,string $token,array &$keys): array {
    if(!acd_id((string)($config['id']??'')))throw new RuntimeException('Chưa liên kết thư mục Hồ sơ chuyên môn.');
    $orders=['name'=>'folder,name_natural','name_desc'=>'folder,name_natural desc','updated'=>'folder,modifiedTime desc,name_natural'];
    if(!isset($orders[$sort])||mb_strlen($query)>150||preg_match('/[\x00-\x1f]/',$query)||strlen($cursor)>4096||preg_match('/[\x00-\x1f]/',$cursor))throw new InvalidArgumentException('Bộ lọc không hợp lệ.');
    $trail=acd_path($config,$folder,$token,$keys);$escape=static fn($s)=>str_replace(['\\',"'"],['\\\\',"\\'"],$s);
    $q="'".$escape($folder)."' in parents and trashed = false";if($query!=='')$q.=" and name contains '".$escape($query)."'";
    $params=['q'=>$q,'pageSize'=>100,'orderBy'=>$orders[$sort],'fields'=>'nextPageToken,incompleteSearch,files(id,name,mimeType,parents,resourceKey,size)','supportsAllDrives'=>'true','includeItemsFromAllDrives'=>'true'];
    if($cursor!=='')$params['pageToken']=$cursor;$last=$trail[count($trail)-1];if($last['drive_id']!==''){$params['corpora']='drive';$params['driveId']=$last['drive_id'];}
    $list=acd_request('files',$params,$token,$keys);$items=[];
    foreach((array)($list['files']??[])as$item){if(!is_array($item)||!acd_id((string)($item['id']??''))||!in_array($folder,(array)($item['parents']??[]),true))continue;
        $id=(string)$item['id'];$key=(string)($item['resourceKey']??'');if(!acd_key($key))$key='';if($key!=='')$keys[$id]=$key;
        $isFolder=($item['mimeType']??'')==='application/vnd.google-apps.folder';$shortcut=($item['mimeType']??'')==='application/vnd.google-apps.shortcut';
        $kind=acd_file_kind($item);
        $items[]=['id'=>$id,'name'=>(string)($item['name']??''),'folder'=>$isFolder,'shortcut'=>$shortcut,'mime'=>(string)($item['mimeType']??''),'size'=>(string)($item['size']??''),'kind'=>$kind,'previewable'=>in_array($kind,['pdf','image','document','sheet','slides'],true)&&(!$isFolder&&!$shortcut)&&acd_inline_mime((string)($item['mimeType']??''))!==''];
    }
    // Bound header/session growth. Root key is always reinserted on the next request.
    if(count($keys)>500)$keys=array_slice($keys,-500,null,true);
    return ['items'=>$items,'breadcrumbs'=>$trail,'folder'=>$folder,'next_cursor'=>(string)($list['nextPageToken']??''),'incomplete'=>!empty($list['incompleteSearch'])];
}

/** Presentation types; only a small allowlist is rendered inline on our origin. */
function acd_file_kind(array $item): string {
    $mime=(string)($item['mimeType']??'');$name=strtolower((string)($item['name']??''));
    if($mime==='application/vnd.google-apps.folder')return 'folder';
    if($mime==='application/vnd.google-apps.shortcut')return 'shortcut';
    if($mime==='application/pdf'||str_ends_with($name,'.pdf'))return 'pdf';
    if(str_starts_with($mime,'image/'))return 'image';
    if(in_array($mime,['application/vnd.google-apps.document','application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],true)||preg_match('/\.(docx?|odt)$/',$name))return 'document';
    if(in_array($mime,['application/vnd.google-apps.spreadsheet','application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],true)||preg_match('/\.(xlsx?|csv|ods)$/',$name))return 'sheet';
    if(in_array($mime,['application/vnd.google-apps.presentation','application/vnd.ms-powerpoint','application/vnd.openxmlformats-officedocument.presentationml.presentation'],true)||preg_match('/\.(pptx?|odp)$/',$name))return 'slides';
    if(preg_match('/\.(zip|rar|7z)$/',$name))return 'archive';
    return 'file';
}
function acd_exportable(string $mime): bool {return in_array($mime,['application/vnd.google-apps.document','application/vnd.google-apps.spreadsheet','application/vnd.google-apps.presentation','application/vnd.google-apps.drawing'],true);}
function acd_inline_mime(string $mime): string {
    if(acd_exportable($mime))return 'application/pdf';
    return in_array($mime,['application/pdf','image/png','image/jpeg','image/gif','image/webp'],true)?$mime:'';
}
/** Revalidate the file and its complete ancestor chain before reading any bytes. */
function acd_content_plan(array $config,string $id,string $token,array &$keys): array {
    if(!acd_id((string)($config['id']??'')))throw new RuntimeException('Thư viện chưa được cấu hình.');
    $keys[$config['id']]=(string)($config['resource_key']??'');
    $file=acd_metadata($id,$token,$keys);$mime=(string)($file['mimeType']??'');
    if(in_array($mime,['application/vnd.google-apps.folder','application/vnd.google-apps.shortcut'],true))throw new RuntimeException('Mục này không hỗ trợ xem tài liệu.');
    $parent=(string)($file['parents'][0]??'');acd_path($config,$parent,$token,$keys);
    if(($file['capabilities']['canDownload']??false)!==true)throw new RuntimeException('Tài liệu chưa được phép xem hoặc tải xuống trong thư viện.');
    $export=acd_exportable($mime);
    if(!$export&&str_starts_with($mime,'application/vnd.google-apps.'))throw new RuntimeException('Định dạng này chưa hỗ trợ xem hoặc tải xuống.');
    if(!$export&&(float)($file['size']??0)>32*1024*1024)throw new RuntimeException('Tài liệu vượt giới hạn 32 MB của trình xem.');
    $name=trim(preg_replace('/[\x00-\x1f\x7f\\\\\/]/u',' ',(string)($file['name']??'Tài liệu'))??'Tài liệu');
    if($export&&!str_ends_with(strtolower($name),'.pdf'))$name.='.pdf';
    $query=$export?['mimeType'=>'application/pdf']:['alt'=>'media','supportsAllDrives'=>'true'];
    $headers=['Authorization: Bearer '.$token];if(!empty($keys[$id]))$headers[]='X-Goog-Drive-Resource-Keys: '.$id.'/'.$keys[$id];
    return ['url'=>'https://www.googleapis.com/drive/v3/files/'.$id.($export?'/export':'').'?'.http_build_query($query),'headers'=>$headers,'name'=>$name?:'Tài liệu','mime'=>acd_inline_mime($mime),'limit'=>$export?10*1024*1024:32*1024*1024];
}
/** Bounded temporary stream. Never redirects to an arbitrary URL or persists file content. */
if(!function_exists('acd_binary_transport')){
function acd_binary_transport(string $url,array $headers,$stream,int $limit): int {
    if(!function_exists('curl_init'))throw new RuntimeException('Máy chủ chưa hỗ trợ đọc nội dung tài liệu.');
    $bytes=0;$overflow=false;$ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>7,CURLOPT_TIMEOUT=>60,CURLOPT_WRITEFUNCTION=>static function($ch,$chunk)use($stream,$limit,&$bytes,&$overflow){$bytes+=strlen($chunk);if($bytes>$limit){$overflow=true;return 0;}return fwrite($stream,$chunk);}]);
    $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
    if($overflow)throw new RuntimeException('Tài liệu quá lớn để xem trực tiếp.');
    if($ok===false)throw new RuntimeException('Không tải được nội dung tài liệu. Hãy thử lại sau.');
    return $status;
}}
function acd_content(array $config,string $id,string $token,array &$keys): array {
    $plan=acd_content_plan($config,$id,$token,$keys);$stream=fopen('php://temp/maxmemory:2097152','w+b');
    if($stream===false)throw new RuntimeException('Không tạo được phiên xem tài liệu.');
    try{
        $status=acd_binary_transport($plan['url'],$plan['headers'],$stream,$plan['limit']);
        if($status!==200)throw new RuntimeException('Không mở được tài liệu. Nội dung có thể bị hạn chế truy cập hoặc vượt giới hạn chuyển đổi.');
        $size=ftell($stream);if($size===false||$size===0||$size>$plan['limit'])throw new RuntimeException('Nội dung tài liệu không hợp lệ hoặc quá lớn.');
        rewind($stream);$signature=fread($stream,12);rewind($stream);
        $mime=$plan['mime'];$valid=match($mime){'application/pdf'=>str_starts_with($signature,'%PDF-'),'image/png'=>str_starts_with($signature,"\x89PNG\r\n\x1a\n"),'image/jpeg'=>str_starts_with($signature,"\xff\xd8\xff"),'image/gif'=>str_starts_with($signature,'GIF87a')||str_starts_with($signature,'GIF89a'),'image/webp'=>str_starts_with($signature,'RIFF')&&substr($signature,8,4)==='WEBP',default=>false};
        return ['stream'=>$stream,'size'=>$size,'name'=>$plan['name'],'mime'=>$valid?$mime:'application/octet-stream','inline'=>$valid];
    }catch(Throwable $ex){fclose($stream);throw $ex;}
}

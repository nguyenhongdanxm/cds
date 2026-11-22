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
    $settings=cds_drive_settings();if(empty($settings['enabled']))throw new RuntimeException('Google Drive chưa được bật. Quản trị cần kiểm tra kết nối Drive của trường.');
    $result=cds_drive_token($settings);if(empty($result['ok'])||empty($result['token']))throw new RuntimeException('Chưa kết nối được Google Drive. Quản trị cần kiểm tra lại kết nối.');
    return (string)$result['token'];
}
if(!function_exists('acd_transport')){
function acd_transport(string $url,array $headers): array {
    if(!function_exists('curl_init'))throw new RuntimeException('Hosting cần bật PHP cURL để đọc Google Drive.');
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
    if($response['status']!==200){$messages=[401=>'Kết nối Drive đã hết hiệu lực. Hãy nhờ quản trị kết nối lại.',403=>'Tài khoản Google kết nối chưa có quyền đọc thư mục hoặc Drive chưa cho phép ứng dụng đọc nội dung này.',404=>'Thư mục/tệp không tồn tại hoặc tài khoản Google kết nối chưa được chia sẻ quyền xem.'];throw new RuntimeException($messages[$response['status']]??'Không đọc được Google Drive. Hãy thử lại sau.');}
    $data=json_decode($response['body'],true);if(!is_array($data))throw new RuntimeException('Phản hồi Google Drive không hợp lệ.');return $data;
}
function acd_metadata(string $id,string $token,array &$keys): array {
    if(!acd_id($id))throw new InvalidArgumentException('Mã thư mục không hợp lệ.');
    if(isset($keys[$id])){$key=$keys[$id];unset($keys[$id]);$keys[$id]=$key;}
    $item=acd_request('files/'.$id,['fields'=>'id,name,mimeType,parents,trashed,resourceKey,driveId','supportsAllDrives'=>'true'],$token,$keys);
    if(($item['id']??'')!==$id||!empty($item['trashed']))throw new RuntimeException('Thư mục đã bị xóa hoặc không còn truy cập được.');
    $key=(string)($item['resourceKey']??'');if($key!==''&&acd_key($key))$keys[$id]=$key;return $item;
}
function acd_drive_url(string $id,bool $folder=false,string $key='',bool $preview=false): string {
    if(!acd_id($id)||!acd_key($key))return '';
    return 'https://drive.google.com/'.($folder?'drive/folders/'.$id:'file/d/'.$id.($preview?'/preview':'/view')).($key!==''?'?resourcekey='.rawurlencode($key):'');
}
function acd_file_link(array $item): string {
    $url=(string)($item['webViewLink']??'');$parts=parse_url($url);
    if(is_array($parts)&&($parts['scheme']??'')==='https'&&in_array($parts['host']??'',['drive.google.com','docs.google.com'],true)&&!isset($parts['user'])&&!isset($parts['port'])&&!preg_match('/[\x00-\x20]/',$url))return $url;
    return acd_drive_url((string)$item['id'],false,(string)($item['resourceKey']??''));
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
    $params=['q'=>$q,'pageSize'=>100,'orderBy'=>$orders[$sort],'fields'=>'nextPageToken,incompleteSearch,files(id,name,mimeType,parents,resourceKey,webViewLink,modifiedTime,size)','supportsAllDrives'=>'true','includeItemsFromAllDrives'=>'true'];
    if($cursor!=='')$params['pageToken']=$cursor;$last=$trail[count($trail)-1];if($last['drive_id']!==''){$params['corpora']='drive';$params['driveId']=$last['drive_id'];}
    $list=acd_request('files',$params,$token,$keys);$items=[];
    foreach((array)($list['files']??[])as$item){if(!is_array($item)||!acd_id((string)($item['id']??''))||!in_array($folder,(array)($item['parents']??[]),true))continue;
        $id=(string)$item['id'];$key=(string)($item['resourceKey']??'');if(!acd_key($key))$key='';if($key!=='')$keys[$id]=$key;
        $isFolder=($item['mimeType']??'')==='application/vnd.google-apps.folder';$shortcut=($item['mimeType']??'')==='application/vnd.google-apps.shortcut';
        $items[]=['id'=>$id,'name'=>(string)($item['name']??''),'folder'=>$isFolder,'shortcut'=>$shortcut,'mime'=>(string)($item['mimeType']??''),'modified'=>(string)($item['modifiedTime']??''),'size'=>(string)($item['size']??''),'open_url'=>$isFolder?acd_drive_url($id,true,$key):acd_file_link($item),'preview_url'=>($isFolder||$shortcut)?'':acd_drive_url($id,false,$key,true)];
    }
    $sourceUrl=acd_drive_url($folder,true,(string)($keys[$folder]??''));
    // Bound header/session growth. Root key is always reinserted on the next request.
    if(count($keys)>500)$keys=array_slice($keys,-500,null,true);
    return ['items'=>$items,'breadcrumbs'=>$trail,'folder'=>$folder,'source_url'=>$sourceUrl,'next_cursor'=>(string)($list['nextPageToken']??''),'incomplete'=>!empty($list['incompleteSearch'])];
}

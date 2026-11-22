<?php
const LT_MAX_BYTES=2*1024*1024;
function lt_valid_file(string $file): bool {return preg_match('/^[a-f0-9]{32}\.(?:png|jpg|gif|webp)$/D',$file)===1;}
function lt_image_info(string $name,string $bytes): array {
    if(strlen($bytes)>LT_MAX_BYTES||$bytes==='')throw new InvalidArgumentException('Ảnh đại diện cần có dung lượng không quá 2 MB.');
    if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['png','jpg','jpeg','gif','webp'],true))throw new InvalidArgumentException('Ảnh đại diện chỉ nhận PNG, JPG, GIF hoặc WebP.');
    $info=@getimagesizefromstring($bytes);$types=['image/png'=>'png','image/jpeg'=>'jpg','image/gif'=>'gif','image/webp'=>'webp'];
    if(!$info||!isset($types[$info['mime']??''])||$info[0]<1||$info[1]<1||$info[0]>4096||$info[1]>4096)throw new InvalidArgumentException('Ảnh đại diện không hợp lệ hoặc vượt kích thước 4096 × 4096.');
    return ['mime'=>$info['mime'],'extension'=>$types[$info['mime']],'width'=>$info[0],'height'=>$info[1]];
}
function lt_select_upload(string $old,?array $upload,bool $remove): string {
    if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return $remove?'':$old;
    if(($upload['error']??-1)!==UPLOAD_ERR_OK||!is_string($upload['tmp_name']??null)||!is_uploaded_file($upload['tmp_name']))throw new InvalidArgumentException('Không đọc được ảnh đại diện tải lên.');
    $bytes=file_get_contents($upload['tmp_name'],false,null,0,LT_MAX_BYTES+1);if($bytes===false)throw new RuntimeException('Không đọc được ảnh đại diện.');
    $info=lt_image_info((string)($upload['name']??''),$bytes);$directory=DATA_PATH.'/learning_thumbnails';
    if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Không tạo được thư mục ảnh đại diện.');
    $name=bin2hex(random_bytes(16)).'.'.$info['extension'];$path=$directory.'/'.$name;
    if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)){@unlink($path);throw new RuntimeException('Không lưu được ảnh đại diện.');}
    @chmod($path,0640);return $name;
}
function lt_delete_file(string $file): void {if(lt_valid_file($file))@unlink(DATA_PATH.'/learning_thumbnails/'.$file);}
function lt_thumbnail_url(array $row): string {
    $file=(string)($row['thumbnail_file']??'');if(!lt_valid_file($file)||!is_file(DATA_PATH.'/learning_thumbnails/'.$file))return '';
    return BASE_URL.'hoclieu_file.php?id='.rawurlencode((string)($row['id']??'')).'&thumbnail=1&v='.substr($file,0,12);
}
function lt_read_thumbnail(array $row): array {
    $file=(string)($row['thumbnail_file']??'');$root=realpath(DATA_PATH.'/learning_thumbnails');
    if(!lt_valid_file($file)||$root===false)throw new RuntimeException('Không tìm thấy ảnh đại diện.');
    $path=realpath($root.'/'.$file);if($path===false||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)||filesize($path)>LT_MAX_BYTES)throw new RuntimeException('Không tìm thấy ảnh đại diện.');
    $bytes=file_get_contents($path);if($bytes===false)throw new RuntimeException('Không đọc được ảnh đại diện.');$info=lt_image_info($file,$bytes);
    return ['body'=>$bytes,'mime'=>$info['mime'],'etag'=>'"'.hash('sha256',$bytes).'"'];
}
function lt_presentation(string $kind): array {
    return match($kind){'Bài viết'=>['article','bi-journal-richtext','Bài viết'],'Văn bản'=>['document','bi-file-earmark-text','Văn bản'],'Video'=>['video','bi-play-btn','Video'],'Hình ảnh'=>['image','bi-image','Hình ảnh'],'Trình chiếu'=>['slides','bi-easel2','Trình chiếu'],'Ứng dụng'=>['app','bi-window','Ứng dụng'],'Trò chơi'=>['game','bi-controller','Trò chơi'],'Đề kiểm tra','Thi thử','Kỳ thi'=>['exam','bi-ui-checks',$kind],default=>['document','bi-collection','Học liệu']};
}
function lt_cover(array $row): string {
    [$theme,$icon,$label]=lt_presentation((string)($row['kind']??''));$url=lt_thumbnail_url($row);$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
    if($url!=='')return '<span class="learning-cover learning-cover--custom"><img src="'.$esc($url).'" alt="'.$esc('Ảnh đại diện: '.($row['title']??'Học liệu')).'" loading="lazy" decoding="async"></span>';
    return '<span class="learning-cover learning-cover--'.$theme.'" role="img" aria-label="'.$esc('Ảnh đại diện '.$label).'"><span class="learning-cover-symbol"><i class="bi '.$icon.'" aria-hidden="true"></i></span><span class="learning-cover-label">'.$esc($label).'</span></span>';
}

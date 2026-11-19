<?php
/** One scheduled announcement; per-account receipts live separately from business data. */
function cds_ln_icons(): array { return ['📢'=>'Thông báo','🔔'=>'Nhắc nhở','📌'=>'Lưu ý','🎉'=>'Chúc mừng','📚'=>'Học tập','🗓️'=>'Lịch công tác','⚠️'=>'Quan trọng','💡'=>'Hướng dẫn']; }
function cds_ln_path(): string { return DATA_PATH.'/login_notice.json'; }
function cds_ln_load(): array {
    $raw=load_json(cds_ln_path(),[]);
    return array_merge(['id'=>'','enabled'=>false,'title'=>'Thông báo nhà trường','body'=>'','icon'=>'📢','theme'=>'blue','starts_at'=>'','ends_at'=>'','updated_at'=>'','version'=>''],$raw);
}
function cds_ln_account_key(array $user): string {
    $id=trim((string)($user['id']??''));
    return $id!==''?'id:'.$id:'username:'.strtolower(trim((string)($user['username']??'')));
}
function cds_ln_receipt_path(array $user,string $noticeId): string {
    return DATA_PATH.'/login_notice_receipts/'.hash('sha256',$noticeId).'/'.hash('sha256',cds_ln_account_key($user)).'.json';
}
function cds_ln_has_text(string $html): bool {
    return preg_replace('/[\s\x{00a0}]+/u','',html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'))!=='';
}
function cds_ln_active(array $notice,?int $now=null): bool {
    $now=$now??time();
    if(empty($notice['enabled'])||empty($notice['id'])||!cds_ln_has_text((string)($notice['body']??'')))return false;
    foreach(['starts_at','ends_at'] as $key)if(!empty($notice[$key])){
        $stamp=strtotime((string)$notice[$key]);if($stamp===false)return false;
        if($key==='starts_at'&&$now<$stamp)return false;
        if($key==='ends_at'&&$now>=$stamp)return false;
    }
    return true;
}
function cds_ln_pending(array $user,?int $now=null): ?array {
    if(cds_ln_account_key($user)==='username:')return null;
    $notice=cds_ln_load();
    return cds_ln_active($notice,$now)&&!is_file(cds_ln_receipt_path($user,(string)$notice['id']))?$notice:null;
}
function cds_ln_dismiss(array $user,string $noticeId): bool {
    if($noticeId===''||cds_ln_account_key($user)==='username:')return false;
    $file=cds_ln_receipt_path($user,$noticeId);if(is_file($file))return true;
    $dir=dirname($file);if(!is_dir($dir)&&!@mkdir($dir,0750,true)&&!is_dir($dir))return false;
    // This narrowly scoped preference write also works for read-only accounts.
    // Identity is always supplied by the authenticated endpoint, never by a request field.
    $tmp=@tempnam($dir,'.receipt-');if($tmp===false)return false;
    $json=json_encode(['notice_id'=>$noticeId,'closed_at'=>gmdate('c')],JSON_UNESCAPED_UNICODE);
    if($json===false||@file_put_contents($tmp,$json,LOCK_EX)!==strlen($json)){@unlink($tmp);return false;}
    @chmod($tmp,0640);$ok=@rename($tmp,$file);if(!$ok)@unlink($tmp);return $ok;
}
function cds_ln_csrf(): string {
    if(empty($_SESSION['cds_login_notice_csrf']))$_SESSION['cds_login_notice_csrf']=bin2hex(random_bytes(24));
    return (string)$_SESSION['cds_login_notice_csrf'];
}
function cds_ln_safe_next($next): string {
    return is_string($next)&&str_starts_with($next,'/')&&!str_starts_with($next,'//')&&!str_contains($next,'\\')&&!str_contains($next,':')&&!preg_match('/[\x00-\x20]/',$next)?$next:BASE_URL.'admin.php';
}
function cds_ln_date(string $value): string {
    if($value==='')return '';
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,new DateTimeZone('Asia/Ho_Chi_Minh'));
    if(!$date||$date->format('Y-m-d\TH:i')!==$value)throw new RuntimeException('Thời gian không hợp lệ.');
    return $date->format(DATE_ATOM);
}
function cds_ln_date_input(string $value): string {
    if($value==='')return '';
    try{return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Asia/Ho_Chi_Minh'))->format('Y-m-d\TH:i');}catch(Throwable $e){return '';}
}
function cds_ln_clean(string $html): string {
    if(strlen($html)>50000)throw new RuntimeException('Nội dung thông báo tối đa 50 KB.');
    if(!class_exists('DOMDocument'))return nl2br(htmlspecialchars(strip_tags($html),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'));
    $doc=new DOMDocument('1.0','UTF-8');$prior=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>',LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($prior);
    $allowed=explode(' ','div p br span strong b em i u s sub sup h2 h3 blockquote ul ol li a table thead tbody tr td th');
    $clean=function($node)use(&$clean,$allowed):void{
        foreach(iterator_to_array($node->childNodes) as $child){
            if($child instanceof DOMElement){
                if(!in_array(strtolower($child->tagName),$allowed,true)){$node->removeChild($child);continue;}
                foreach(iterator_to_array($child->attributes) as $attr){
                    $name=strtolower($attr->name);$value=trim($attr->value);$ok=false;
                    if($name==='href'&&strtolower($child->tagName)==='a')$ok=preg_match('#^https?://#i',$value)===1;
                    if(in_array($name,['colspan','rowspan'],true))$ok=preg_match('/^[1-9][0-9]?$/D',$value)===1;
                    if($name==='style'){
                        $styles=[];foreach(explode(';',$value) as $part){$bits=explode(':',$part,2);if(count($bits)!==2)continue;[$key,$v]=array_map('trim',$bits);$key=strtolower($key);
                            if(in_array($key,['color','background-color','font-size','font-weight','font-style','text-align','text-decoration'],true)&&preg_match('/^[#a-zA-Z0-9(),.%\s-]+$/D',$v)&&!preg_match('/url|expression|var|!|@/i',$v))$styles[]=$key.':'.$v;
                        }
                        if($styles){$child->setAttribute('style',implode(';',$styles));$ok=true;}
                    }
                    if(!$ok)$child->removeAttribute($attr->name);
                }
                if(strtolower($child->tagName)==='a')$child->setAttribute('rel','noopener noreferrer');
                $clean($child);
            }elseif(!($child instanceof DOMText))$node->removeChild($child);
        }
    };
    $body=$doc->getElementsByTagName('body')->item(0);if(!$body)return '';
    $clean($body);$out='';foreach($body->childNodes as $child)$out.=$doc->saveHTML($child);return $out;
}
function cds_ln_save(array $input,array $actor): array {
    if(($actor['role']??'')!=='admin')throw new RuntimeException('Chỉ quản trị được cài đặt thông báo.');
    foreach(['title','body','icon','theme','starts_at','ends_at','version'] as $key)if(isset($input[$key])&&!is_string($input[$key]))throw new RuntimeException('Dữ liệu cài đặt không hợp lệ.');
    $title=trim((string)($input['title']??''));$body=cds_ln_clean((string)($input['body']??''));
    if(strlen($title)>500)throw new RuntimeException('Tiêu đề quá dài.');
    if(!empty($input['enabled'])&&($title===''||!cds_ln_has_text($body)))throw new RuntimeException('Hãy nhập tiêu đề và nội dung trước khi bật thông báo.');
    $start=cds_ln_date((string)($input['starts_at']??''));$end=cds_ln_date((string)($input['ends_at']??''));
    if($start!==''&&$end!==''&&strtotime($end)<=strtotime($start))throw new RuntimeException('Thời gian kết thúc phải sau thời gian bắt đầu.');
    $saved=[];
    $ok=cds_json_update(cds_ln_path(),function($old)use($input,$title,$body,$start,$end,&$saved){
        if((string)($old['version']??'')!==(string)($input['version']??''))throw new RuntimeException('Cấu hình đã thay đổi.');
        $saved=['id'=>!empty($input['new_notice'])||empty($old['id'])?'notice_'.bin2hex(random_bytes(16)):$old['id'],
            'enabled'=>!empty($input['enabled']),'title'=>$title,'body'=>$body,
            'icon'=>array_key_exists((string)($input['icon']??''),cds_ln_icons())?(string)$input['icon']:'📢',
            'theme'=>in_array($input['theme']??'',['blue','green','amber','violet'],true)?$input['theme']:'blue',
            'starts_at'=>$start,'ends_at'=>$end,'updated_at'=>gmdate('c'),'version'=>bin2hex(random_bytes(16))];
        return $saved;
    },[]);
    if(!$ok)throw new RuntimeException('Không lưu được thông báo hoặc cấu hình đã được người khác thay đổi. Hãy tải lại trang và thử lại.');
    return $saved;
}

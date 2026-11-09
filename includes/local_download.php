<?php
/** Only callers that have checked record access may stream private files. */
function cds_local_upload_path(string $dataRoot, string $relative): string
{
    if (!preg_match('~^uploads/[A-Za-z0-9_.-]+\.(?:pdf|docx?|xlsx?|pptx?|png|jpe?g|gif|zip|rar)$~iD', $relative)) return '';
    if (is_link($dataRoot.'/uploads')) return '';
    $base=realpath($dataRoot.'/uploads');$path=realpath($dataRoot.'/'.$relative);
    return $base && $path && is_file($path) && !is_link($dataRoot.'/'.$relative) && str_starts_with($path,$base.'/') ? $path : '';
}
function cds_stream_local_file(string $path, bool $download=false): void
{
    if (!is_file($path) || !is_readable($path)) { http_response_code(404); exit; }
    $size=filesize($path);$start=0;$end=$size-1;
    $mime=['pdf'=>'application/pdf','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','mp3'=>'audio/mpeg','ogg'=>'audio/ogg','wav'=>'audio/wav','m4a'=>'audio/mp4','mp4'=>'video/mp4','webm'=>'video/webm'][strtolower(pathinfo($path,PATHINFO_EXTENSION))]??'application/octet-stream';
    header("Content-Security-Policy: sandbox; default-src 'none'");
    header('Content-Type: '.$mime);header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');header('Accept-Ranges: bytes');
    header("Content-Disposition: ".($download?'attachment':'inline')."; filename*=UTF-8''".rawurlencode(basename($path)));
    if(isset($_SERVER['HTTP_RANGE'])){
        if(!preg_match('/^bytes=(\d*)-(\d*)$/D',$_SERVER['HTTP_RANGE'],$m)||($m[1]===''&&$m[2]==='')){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
        if($m[1]==='')$start=max(0,$size-(int)$m[2]);else{$start=(int)$m[1];if($m[2]!=='')$end=min($end,(int)$m[2]);}
        if($start>$end||$start>=$size){http_response_code(416);header('Content-Range: bytes */'.$size);exit;}
        http_response_code(206);header("Content-Range: bytes $start-$end/$size");
    }
    header('Content-Length: '.max(0,$end-$start+1));
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    if(($_SERVER['REQUEST_METHOD']??'GET')==='HEAD')exit;
    $stream=fopen($path,'rb');if(!$stream){http_response_code(404);exit;}fseek($stream,$start);$left=$end-$start+1;
    while($left>0&&!feof($stream)){ $bytes=fread($stream,min(65536,$left));if($bytes===false||$bytes==='')break;echo $bytes;$left-=strlen($bytes); }fclose($stream);exit;
}

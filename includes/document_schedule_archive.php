<?php
/** Read-only backup of original Word/PDF uploads from fixed application folders. */
function cds_document_archive(string $root,array $params,string $output): int {
    $collections=['all'=>['data/uploads','chuyenmon/data/uploads'],'documents'=>['data/uploads/vanban'],'academic'=>['chuyenmon/data/uploads']];
    $collection=trim((string)($params['collection']??''))?:'all';$extension=trim((string)($params['extension']??''))?:'all';
    if(!isset($collections[$collection])||!in_array($extension,['all','pdf','doc','docx'],true))throw new InvalidArgumentException('Nhóm hoặc định dạng hồ sơ không hợp lệ.');
    $bounds=[];foreach(['from','to']as$key){$value=trim((string)($params[$key]??''));if($value==='')continue;$date=DateTimeImmutable::createFromFormat('!Y-m-d',$value,new DateTimeZone('Asia/Ho_Chi_Minh'));if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Ngày thay đổi tệp không hợp lệ.');$bounds[$key]=$key==='to'?$date->modify('+1 day')->getTimestamp():$date->getTimestamp();}
    if(isset($bounds['from'],$bounds['to'])&&$bounds['from']>=$bounds['to'])throw new InvalidArgumentException('Khoảng ngày không hợp lệ.');
    $root=rtrim((string)realpath($root),'/');if($root==='')throw new RuntimeException('Không tìm thấy gốc ứng dụng.');
    $files=[];$seen=0;$total=0;
    foreach($collections[$collection]as$relative){
        $dir=$root;foreach(explode('/',$relative)as$part){$dir.='/'.$part;if(is_link($dir))throw new RuntimeException('Không sao lưu thư mục liên kết tượng trưng.');}
        if(!is_dir($dir))continue;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS));
        foreach($it as$file){if(++$seen>20000)throw new RuntimeException('Quá nhiều mục; chia nhỏ nhóm hồ sơ.');if($file->isLink())throw new RuntimeException('Phát hiện tệp liên kết; chưa tạo bản sao lưu.');if(!$file->isFile())continue;
            $ext=strtolower($file->getExtension());if(!in_array($ext,['pdf','doc','docx'],true)||($extension!=='all'&&$extension!==$ext))continue;
            $mtime=$file->getMTime();if(isset($bounds['from'])&&$mtime<$bounds['from']||isset($bounds['to'])&&$mtime>=$bounds['to'])continue;
            $size=$file->getSize();if($size>25*1024*1024)throw new RuntimeException('Có tệp vượt 25 MB; chia nhóm hoặc xử lý riêng.');$total+=$size;
            if($total>64*1024*1024||count($files)>=5000)throw new RuntimeException('Gói hồ sơ vượt 64 MB hoặc 5.000 tệp; chia nhỏ bộ lọc.');
            $path=$file->getPathname();$real=realpath($path);if($real===false||!str_starts_with($real,$dir.'/'))throw new RuntimeException('Tệp nằm ngoài thư mục cho phép.');
            $name=substr($path,strlen($root)+1);if(str_contains($name,'\\')||preg_match('/[\x00-\x1F]/',$name))throw new RuntimeException('Tên tệp không hợp lệ.');$files[$name]=['path'=>$path,'size'=>$size,'mtime'=>$mtime];
        }
    }
    if(!$files)throw new RuntimeException('Không có Word/PDF trên hosting phù hợp. Tệp đã lưu thẳng lên Drive không nằm trong gói này.');
    ksort($files,SORT_STRING);$zip=new ZipArchive();if($zip->open($output,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Không tạo được gói hồ sơ.');$manifest=[];
    try{foreach($files as$name=>$file){$bytes=file_get_contents($file['path']);clearstatcache(true,$file['path']);if($bytes===false||strlen($bytes)!==$file['size']||filemtime($file['path'])!==$file['mtime'])throw new RuntimeException('Tệp thay đổi trong lúc sao lưu; thử lại lượt sau.');if(!$zip->addFromString($name,$bytes))throw new RuntimeException('Không thêm được tệp vào gói.');$manifest[]=['path'=>$name,'bytes'=>strlen($bytes),'sha256'=>hash('sha256',$bytes),'modified_at'=>gmdate('c',$file['mtime'])];unset($bytes);}
        $zip->addFromString('CDS-DANH-MUC.json',json_encode(['created_at'=>gmdate('c'),'scope'=>'Word/PDF gốc trên hosting; không phải bản sao toàn bộ cơ sở dữ liệu','date_filter'=>'Ngày sửa tệp trên hosting','files'=>$manifest],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));if(!$zip->close())throw new RuntimeException('Không hoàn tất được gói hồ sơ.');
    }catch(Throwable $e){@$zip->close();@unlink($output);throw$e;}
    return count($files);
}

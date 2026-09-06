<?php
require_once dirname(__DIR__).'/includes/auth.php';
require_login();

$user=current_user()??[];
$isAdmin=(($user['role']??'')==='admin') || (function_exists('can_perm_level') && can_perm_level('cm.pccm','edit'));
if(!$isAdmin){http_response_code(403);exit('Chỉ quản trị được chạy chuyển đổi GDTC.');}

$pccmDir=__DIR__.'/data';
$rootData=defined('DATA_PATH')?DATA_PATH:dirname(__DIR__).'/data';
$targets=[
  '10A'=>'GDTC (Bóng chuyền)','11A'=>'GDTC (Bóng chuyền)','12A'=>'GDTC (Bóng chuyền)',
  '10B'=>'GDTC (Bóng rổ)','11B'=>'GDTC (Bóng rổ)','12B'=>'GDTC (Bóng rổ)'
];
$read=function(string $file):array{$v=is_file($file)?json_decode((string)file_get_contents($file),true):[];return is_array($v)?$v:[];};
$write=function(string $file,array $rows):bool{if(!is_dir(dirname($file)))@mkdir(dirname($file),0755,true);return file_put_contents($file,json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX)!==false;};
$classOf=function(array $row):string{foreach(['class','class_name','class_key','scope_target'] as $k){$v=trim((string)($row[$k]??''));if($v!=='')return strtoupper(preg_replace('/\s+/u','',$v));}return '';};
$subjectOf=function(array $row):string{return trim((string)($row['subject']??$row['subject_name']??$row['mon']??''));};
$variant=function(array $row)use($classOf,$targets,$subjectOf):?string{$subject=$subjectOf($row);if(strcasecmp(trim($subject),'GDTC')!==0)return null;$class=$classOf($row);return $targets[$class]??null;};

$preview=['pccm'=>0,'tkb'=>0,'ppct'=>0,'files'=>[]];
$scan=function(string $file,string $kind)use(&$preview,$read,$variant){
  $rows=$read($file);$count=0;
  if($kind==='tkb'){foreach($rows as $version)foreach((array)($version['entries']??[]) as $row)if(is_array($row)&&$variant($row)!==null)$count++;}
  else foreach($rows as $row)if(is_array($row)&&$variant($row)!==null)$count++;
  if($count)$preview[$kind]+=$count;
  if($count)$preview['files'][]=['kind'=>$kind,'file'=>$file,'count'=>$count];
};
$versions=$read($pccmDir.'/versions.json');
foreach($versions as $v){$id=(string)($v['id']??'');if($id!=='')$scan($pccmDir.'/assignments_'.$id.'.json','pccm');}
$scan($pccmDir.'/assignments.json','pccm');$scan($pccmDir.'/manual_assignments.json','pccm');
$scan($rootData.'/timetable_versions.json','tkb');$scan($rootData.'/lesson_book_curriculum.json','ppct');

$message='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='run'){
  $changed=['pccm'=>0,'tkb'=>0,'ppct'=>0];$seen=[];
  foreach($preview['files'] as $f){
    $rows=$read($f['file']);$dirty=false;
    if($f['kind']==='tkb'){
      foreach($rows as &$version)foreach((array)($version['entries']??[]) as $i=>$row){if(!is_array($row))continue;$new=$variant($row);if($new===null)continue;$version['entries'][$i]['subject']=$new;$changed[$f['kind']]++;$dirty=true;}unset($version);
    }else{
      foreach($rows as &$row){if(!is_array($row))continue;$new=$variant($row);if($new===null)continue;$row['subject']=$new;$changed[$f['kind']]++;$dirty=true;}unset($row);
    }
    if($dirty)$write($f['file'],$rows);
  }
  $message='Đã chuyển đổi '.($changed['pccm']+$changed['tkb']+$changed['ppct']).' dòng: PCCM '.$changed['pccm'].', TKB '.$changed['tkb'].', PPCT '.$changed['ppct'].'. Sổ đầu bài cũ không bị thay đổi.';
  $preview=['pccm'=>0,'tkb'=>0,'ppct'=>0,'files'=>[]];
  foreach($versions as $v){$id=(string)($v['id']??'');if($id!=='')$scan($pccmDir.'/assignments_'.$id.'.json','pccm');}
  $scan($pccmDir.'/assignments.json','pccm');$scan($pccmDir.'/manual_assignments.json','pccm');$scan($rootData.'/timetable_versions.json','tkb');$scan($rootData.'/lesson_book_curriculum.json','ppct');
}
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Chuyển đổi GDTC – CDS</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-4" style="max-width:900px"><div class="card shadow-sm"><div class="card-body p-4"><h1 class="h4">Chuyển GDTC theo lớp</h1><p class="text-muted">Chỉ đổi PCCM, TKB và PPCT hiện có. Dữ liệu Sổ đầu bài đã ghi được giữ nguyên.</p><?php if($message):?><div class="alert alert-success"><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></div><?php endif;?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Lớp</th><th>Môn mới</th></tr></thead><tbody><?php foreach($targets as $class=>$subject):?><tr><td><strong><?=htmlspecialchars($class,ENT_QUOTES,'UTF-8')?></strong></td><td><?=htmlspecialchars($subject,ENT_QUOTES,'UTF-8')?></td></tr><?php endforeach;?></tbody></table></div><div class="alert alert-warning"><strong>Dự kiến còn đổi:</strong> PCCM <?= (int)$preview['pccm'] ?> dòng · TKB <?= (int)$preview['tkb'] ?> dòng · PPCT <?= (int)$preview['ppct'] ?> dòng.</div><form method="post" onsubmit="return confirm('Chạy chuyển đổi GDTC theo lớp? Sổ đầu bài cũ sẽ không bị đổi.')"><input type="hidden" name="action" value="run"><button class="btn btn-primary">Chạy chuyển đổi</button><a class="btn btn-outline-secondary ms-2" href="<?=htmlspecialchars(defined('BASE_URL')?BASE_URL:'/',ENT_QUOTES,'UTF-8')?>">Quay lại</a></form></div></div></main></body></html>
<?php
// Isolated HTTP fixture: actual page/helper/assets with synthetic auth and Drive transport.
$dir=$argv[1]??'';if($dir===''||!is_dir($dir))throw new RuntimeException('Supply temporary directory');
mkdir($dir.'/includes');mkdir($dir.'/assets');mkdir($dir.'/data');
copy(__DIR__.'/../chuyenmon/includes/academic_drive_cache.php',$dir.'/includes/academic_drive_cache.php');
copy(__DIR__.'/../chuyenmon/hoso.php',$dir.'/hoso.php');copy(__DIR__.'/../chuyenmon/includes/academic_drive.php',$dir.'/includes/academic_drive.php');
foreach(['css','js']as$ext)copy(__DIR__.'/../chuyenmon/assets/academic-drive.'.$ext,$dir.'/assets/academic-drive.'.$ext);
file_put_contents($dir.'/data/academic_drive_folder.json',json_encode(['id'=>'rootfolder123','name'=>'Hồ sơ','resource_key'=>'','revision'=>'initial']));
file_put_contents($dir.'/includes/header.php','<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><base href="/"><link rel="stylesheet" href="https://fixture.test/bootstrap.css"><link rel="stylesheet" href="https://fixture.test/icons.css"></head><body>');
file_put_contents($dir.'/includes/footer.php','</body></html>');
$store=var_export(realpath(__DIR__.'/../includes/json_store.php'),true);
$stub= <<<'STUB'
<?php
session_start();define('BASE_URL','/');define('DATA_PATH',dirname(__DIR__).'/data');
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function cds_user(){return ['role'=>$_COOKIE['test_role']??'admin'];}
function require_login(){if(($_COOKIE['test_role']??'')==='denied'){http_response_code(403);exit('Denied');}}
function cds_drive_settings(){return ['enabled'=>true];}
function cds_drive_token($s){return ['ok'=>true,'token'=>'fixture-token'];}
function acd_transport($url,$headers){static $delayed=false;if(($_SERVER['HTTP_X_FIXTURE_SLOW']??'')==='1'&&!$delayed){$delayed=true;file_put_contents(DATA_PATH.'/slow-started','1');usleep(2000000);}$p=parse_url($url);parse_str($p['query']??'',$q);$id=basename($p['path']);$folder='application/vnd.google-apps.folder';
if($id==='files'){$root=str_contains($q['q'],'rootfolder123');$items=$root?[['id'=>'childfolder123','name'=>'Tổ KHTN','mimeType'=>$folder,'parents'=>['rootfolder123']],['id'=>'document12345','name'=>'<img src=x onerror=alert(1)>','mimeType'=>'application/pdf','parents'=>['rootfolder123'],'modifiedTime'=>'2026-11-21T01:00:00Z']]:[['id'=>'nestedfile123','name'=>'Kế hoạch tổ','parents'=>['childfolder123'],'mimeType'=>'application/vnd.google-apps.document','webViewLink'=>'https://docs.google.com/document/d/nestedfile123/edit']];if(isset($q['pageToken']))$items=[['id'=>'secondpage123','name'=>'Trang tiếp theo','mimeType'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','parents'=>['rootfolder123']]];if(str_contains($q['q'],"name contains 'empty'"))$items=[];$data=['files'=>$items];if($root&&!isset($q['pageToken'])&&!str_contains($q['q'],'name contains'))$data['nextPageToken']='page2';}
elseif(in_array($id,['document12345','nestedfile123','officefile123','htmlfile12345','lockedfile123','outsidefile123'],true)){$data=['id'=>$id,'name'=>$id==='nestedfile123'?'Kế hoạch tổ':'Tài liệu','mimeType'=>$id==='nestedfile123'?'application/vnd.google-apps.document':($id==='officefile123'?'application/vnd.openxmlformats-officedocument.wordprocessingml.document':'application/pdf'),'parents'=>[$id==='outsidefile123'?'outsidefolder123':'rootfolder123'],'version'=>'1','capabilities'=>['canDownload'=>$id!=='lockedfile123']];}
elseif(in_array($id,['rootfolder123','childfolder123'],true)){$data=['id'=>$id,'name'=>$id==='rootfolder123'?'Hồ sơ':'Tổ KHTN','mimeType'=>$folder];if($id!=='rootfolder123')$data['parents']=['rootfolder123'];}
else return ['status'=>404,'body'=>'{}'];return ['status'=>200,'body'=>json_encode($data)];}
function acd_binary_transport($url,$headers,$stream,$limit){fwrite($stream,str_contains($url,'htmlfile12345')?'<html>not a PDF</html>':"%PDF-1.4\n1 0 obj<</Type /Catalog>>endobj\n%%EOF");return 200;}
STUB;
file_put_contents($dir.'/includes/functions.php',$stub."\nrequire_once ".$store.";\n");

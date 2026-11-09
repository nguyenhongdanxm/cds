<?php
require_once __DIR__.'/includes/functions.php';
require_login();
require_once __DIR__.'/includes/cm_docs.php';
require_once dirname(__DIR__).'/includes/local_download.php';
$relative=(string)($_GET['path']??'');$allowed=false;
foreach(cm_docs_all() as $row){
    if(!is_array($row)||($row['file_path']??'')!==$relative)continue;
    $section=(string)($row['section']??'');
    $feature=str_starts_with($section,'kh_')?'cm.kehoach':[
        'bc_dinhky'=>'cm.baocao.dinhky','bc_thang'=>'cm.baocao.dinhky',
        'bc_tiendo'=>'cm.baocao.tiendo','bc_dugio'=>'cm.baocao.dugio','bc_kythi'=>'cm.baocao.kythi',
    ][$section]??'';
    if($feature!==''&&cds_can_feature($feature,'view')){$allowed=true;break;}
}
$file=$allowed?cds_local_upload_path(DATA_PATH,$relative):'';
if($file===''){http_response_code(404);exit;}
cds_stream_local_file($file,!empty($_GET['download']));

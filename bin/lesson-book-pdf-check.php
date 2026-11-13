<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/instance_config.php';
require_once dirname(__DIR__).'/includes/excel_schedule_pdf.php';
try{
    if(!function_exists('proc_open'))throw new RuntimeException('PHP CLI cần bật proc_open.');
    [$command,$env]=xlsched_pdf_command(null,null,45);
    $process=proc_open($command,[0=>['file','/dev/null','r'],1=>STDOUT,2=>STDERR],$pipes,dirname(__DIR__),$env);
    if(!is_resource($process))throw new RuntimeException('Không khởi chạy được Node.js.');exit(proc_close($process));
}catch(Throwable $e){fwrite(STDERR,'CDS_PDF_CHECK_ERROR: '.$e->getMessage().PHP_EOL);exit(1);}

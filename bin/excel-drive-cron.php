<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('CDS_AUTH_LOGIN_ONLY',true);define('CDS_SKIP_DRIVE_ACTION_REGISTRATION',true);
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once dirname(__DIR__).'/includes/auth.php';
if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
require_once dirname(__DIR__).'/includes/excel_schedule_runner.php';
require_once dirname(__DIR__).'/includes/excel_schedule_drive.php';
try{if(!xlsched_ready())throw new RuntimeException('Chưa cài migration lịch Excel. Mở Quản trị MySQL để cập nhật schema.');if(in_array('--check',$argv,true)){echo json_encode(['ready'=>true,'enabled'=>(bool)xlsched_state()['enabled'],'jobs'=>count(xlsched_jobs()),'timezone'=>'Asia/Ho_Chi_Minh'],JSON_UNESCAPED_UNICODE).PHP_EOL;exit;}
$result=xlsched_tick();echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR).PHP_EOL;}
catch(Throwable $e){fwrite(STDERR,'CDS_EXCEL_CRON_ERROR: '.$e->getMessage().PHP_EOL);exit(1);}

<?php
/** Isolated export request, invoked only by the CLI scheduler. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/excel_schedule_store.php';
require_once dirname(__DIR__).'/includes/session_user.php';
$payload=json_decode((string)@file_get_contents($argv[1]??''),true);
if(!is_array($payload)||!xlsched_admin_record((string)($payload['owner_id']??''))){fwrite(STDERR,"OWNER_NOT_ACTIVE_ADMIN\n");exit(1);}
$report=xlsched_report((string)$payload['report_id']);$params=xlsched_params($report,(array)($payload['params']??[]));$params=array_replace($params,$report['fixed']);
unset($params['__auto_name']);
$root=dirname(__DIR__);$entry=$root.'/'.$report['path'];
if(!is_file($entry)||is_link($entry)){fwrite(STDERR,"EXPORT_SOURCE_MISSING\n");exit(1);}
if(!empty($report['asset'])){readfile($entry);exit;}
$meta=(string)($payload['meta']??'');
register_shutdown_function(static function()use($meta){$last=error_get_last();$fatal=$last&&in_array($last['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR],true);if($meta!=='')file_put_contents($meta,json_encode(['status'=>http_response_code()?:200,'fatal'=>$fatal]),LOCK_EX);});
date_default_timezone_set('Asia/Ho_Chi_Minh');ini_set('display_errors','stderr');
ini_set('session.save_path',dirname((string)$argv[1]));session_name('CDS_EXCEL_WORKER');session_id(bin2hex(random_bytes(20)));session_start();
$user=xlsched_admin_record((string)$payload['owner_id']);if(!$user){exit(1);}$_SESSION=['cds_user'=>cds_session_user_from_record($user),'pccm_admin'=>true];
if(!empty($report['csrf_key'])){$_SESSION[$report['csrf_key']]=bin2hex(random_bytes(24));$params['csrf']=$_SESSION[$report['csrf_key']];}
$_SERVER['REQUEST_METHOD']=$report['method'];$_SERVER['SCRIPT_NAME']='/'.$report['path'];$_SERVER['PHP_SELF']='/'.$report['path'];$_SERVER['SCRIPT_FILENAME']=$entry;
$_SERVER['HTTP_HOST']=(string)($payload['site_host']??'localhost');$_SERVER['HTTPS']=!empty($payload['https'])?'on':'off';$_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_USER_AGENT']='CDS-ScheduledExcel/1';
$_GET=$report['method']==='GET'?$params:[];$_POST=$report['method']==='POST'?$params:[];$_REQUEST=array_merge($_GET,$_POST);$_FILES=[];
$_SERVER['QUERY_STRING']=http_build_query($_GET);$_SERVER['REQUEST_URI']='/'.$report['path'].($_GET?'?'.http_build_query($_GET):'');
// Prevent incidental Drive registration on ordinary page bootstrap. Export calculations remain unchanged.
define('CDS_SKIP_DRIVE_ACTION_REGISTRATION',true);define('CDS_EXCEL_BACKGROUND_EXPORT',true);
chdir(dirname($entry));require $entry;

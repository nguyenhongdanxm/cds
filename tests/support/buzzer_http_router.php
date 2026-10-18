<?php
// Disposable CI only: real endpoint, fixture authentication and isolated PDO/files.
if(getenv('GITHUB_ACTIONS')!=='true'||getenv('CDS_TEST_MYSQL')!=='disposable-ci'){http_response_code(403);exit;}
$root=getenv('CDS_BUZZER_AUDIO_FIXTURE');if(!is_dir($root)||!str_starts_with($root,sys_get_temp_dir().'/cds-buzzer-http-'))exit;
define('DATA_PATH',$root);session_start();$_SESSION['buzzer_csrf']='fixture-csrf';
function current_user(){return ['id'=>$_SERVER['HTTP_X_TEST_USER']??'fixture-admin','role'=>$_SERVER['HTTP_X_TEST_ROLE']??'admin'];}
function can_perm_level($key,$level){return true;}
function qp_game_visibility(){return [];}
function cds_db(){static $db;if(!$db)$db=new PDO('mysql:host=127.0.0.1;port='.getenv('CDS_TEST_MYSQL_PORT').';dbname=cds_disposable_test;charset=utf8mb4','cds_fixture','fixture-only-password',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);return $db;}
$path=realpath(__DIR__.'/../../hoclieu_game_buzzer_api.php');$s=file_get_contents($path);
foreach(["require_once __DIR__.'/includes/config.php';","require_once __DIR__.'/includes/auth.php';","require_once __DIR__.'/includes/database.php';","require_once __DIR__.'/includes/quiz_paper_store.php';"]as$line)$s=str_replace($line,';',$s);
$s=str_replace('__DIR__',var_export(dirname($path),true),$s);$s=str_replace('declare(strict_types=1);','',$s);eval('?>'.$s);

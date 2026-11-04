<?php
require __DIR__.'/support/login_fixture.php';$dir=login_fixture();if(($argv[1]??'')==='--fixture'){echo $dir;exit;}
function check($v,$m){if(!$v)throw new RuntimeException($m);}
try{
 define('CDS_SKIP_DRIVE_ACTION_REGISTRATION',true);define('CDS_AUTH_LOGIN_ONLY',true);ini_set('session.save_path',$dir.'/sessions');require $dir.'/includes/auth.php';
 $original=file_get_contents(USERS_FILE);check(!is_logged_in(),'Starts anonymous');
 foreach([['missing','FixturePass!123'],['teacher','wrong'],['inactive','FixturePass!123']]as[$u,$p]){check(!attempt_login($u,$p)&&!is_logged_in(),'Wrong/missing/inactive never authenticates');check(empty($GLOBALS['cds_login_error']),'Credential failure stays generic');}
 file_put_contents(USERS_FILE,'{broken');check(!attempt_login('teacher','FixturePass!123')&&!is_logged_in()&&!empty($GLOBALS['cds_login_error']),'Unreadable store gives temporary error, never an account failure');check(file_get_contents(USERS_FILE)==='{broken','Corrupt file is not overwritten');
 file_put_contents(USERS_FILE,$original);$before=session_id();check(attempt_login('TEACHER','FixturePass!123')&&is_logged_in(),'Existing case-insensitive username login works after storage recovery');check(session_id()!==$before,'Successful login rotates session');check(!isset(current_user()['password_hash']),'Session contains no password hash');check(empty($GLOBALS['cds_login_error']),'Temporary error cleared on next attempt');
 check(!file_exists(DATA_PATH.'/google_drive_actions.json.lock'),'Login never takes Drive registry lock');check(!function_exists('cds_home_controls_file'),'Login skips unrelated dashboard bootstrap');check(file_get_contents(USERS_FILE)===$original,'Account records unchanged');session_write_close();echo "PASS login: valid/invalid/inactive, transient corrupt store, no privilege fallback, session rotation, no hash, no Drive lock/dashboard load and unchanged account data\n";
}finally{if(session_status()===PHP_SESSION_ACTIVE)session_write_close();login_wipe($dir);}

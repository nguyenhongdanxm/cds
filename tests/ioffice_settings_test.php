<?php
// Exercise the real controller against an isolated authentication fixture.
$root = sys_get_temp_dir() . '/cds-settings-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700); mkdir($root . '/app', 0700); mkdir($root . '/app/includes', 0700); mkdir($root . '/vault', 0700);
copy(__DIR__ . '/../ioffice_settings.php', $root . '/app/ioffice_settings.php');
copy(__DIR__ . '/../includes/ioffice_vault.php', $root . '/app/includes/ioffice_vault.php');
require_once __DIR__ . '/../includes/ioffice_vault.php';
putenv('CDS_IOFFICE_VAULT_DIR=' . $root . '/vault'); cds_ioffice_vault_init();
file_put_contents($root . '/app/includes/auth.php', <<<'PHP'
<?php
define('BASE_URL', '/');
function require_login(){if(empty($_SESSION['cds_user']))exit('LOGIN_REQUIRED');}
function current_user(){return $_SESSION['cds_user'];}
function e($s){return htmlspecialchars($s??'', ENT_QUOTES, 'UTF-8');}
function flash($s,$type='success'){echo $s;}
function show_flash(){}
PHP);
file_put_contents($root . '/request.php', <<<'PHP'
<?php
$input=json_decode(stream_get_contents(STDIN),true);
$_SERVER=['REQUEST_METHOD'=>$input['method']??'GET','HTTPS'=>$input['https']??'on','DOCUMENT_ROOT'=>__DIR__.'/app'];
$_SESSION=['ioffice_csrf'=>'fixture-token'];
if(!empty($input['role']))$_SESSION['cds_user']=['role'=>$input['role']];
$_POST=$input['post']??[];
include __DIR__.'/app/ioffice_settings.php';
PHP);
function request_fixture(string $root, array $input): string {
    $proc = proc_open([PHP_BINARY, '-n', $root . '/request.php'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    fwrite($pipes[0], json_encode($input)); fclose($pipes[0]);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    if(proc_close($proc)!==0||$err!=='')throw new RuntimeException('Controller fixture failed: '.$err);
    return $out;
}
function ensure_settings(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
try {
    ensure_settings(request_fixture($root,[])==='LOGIN_REQUIRED','Guests must authenticate');
    ensure_settings(str_contains(request_fixture($root,['role'=>'teacher']),'Chỉ quản trị viên'),'Teachers cannot access');
    ensure_settings(str_contains(request_fixture($root,['role'=>'admin','https'=>'off']),'chỉ hoạt động qua HTTPS'),'HTTP rejected');
    $post=['csrf'=>'wrong','consent'=>'1','username'=>'fake-only','password'=>'fake-password'];
    request_fixture($root,['role'=>'admin','method'=>'POST','post'=>$post]);
    ensure_settings(cds_ioffice_vault_read()===null,'Invalid CSRF cannot save');
    $post['csrf']='fixture-token';unset($post['consent']);
    request_fixture($root,['role'=>'admin','method'=>'POST','post'=>$post]);
    ensure_settings(cds_ioffice_vault_read()===null,'Consent required');
    $post['consent']='1';
    $result=request_fixture($root,['role'=>'admin','method'=>'POST','post'=>$post]);
    ensure_settings(str_contains($result,'Đã lưu tài khoản mã hóa'),'Authorized POST saved');
    ensure_settings(cds_ioffice_vault_read()['password']==='fake-password','Stored intended fake credential');
    $html=request_fixture($root,['role'=>'admin']);
    ensure_settings(!str_contains($html,'fake-password')&&!str_contains($html,'fake-only'),'GET never reveals saved credentials');
    ensure_settings(str_contains($html,'Chưa triển khai bộ thu thập'),'UI never claims automatic login');
    echo "PASS: authentication, admin role, HTTPS, CSRF, consent, save and non-disclosure\n";
} finally {
    putenv('CDS_IOFFICE_VAULT_DIR');
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($root);
}

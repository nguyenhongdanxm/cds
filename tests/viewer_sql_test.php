<?php
// Explicit disposable CI database only. Never use instance/hosting credentials.
require_once __DIR__.'/../includes/viewer_access.php';
if (($argv[1] ?? '') === 'worker') {
    if (!isset($argv[2]) || !str_starts_with($argv[2], sys_get_temp_dir().'/cds_viewer_sql_')) exit(2);
    $_SESSION=['cds_user'=>['role'=>($argv[3]??'')==='cached'?'admin':'viewer']];
    require $argv[2].'/database.php';
    $pdo=cds_db();
    if (($argv[3]??'')==='cached') {
        $pdo->beginTransaction();
        $pdo->exec('UPDATE viewer_fixture SET name="uncommitted" WHERE id=1');
        $_SESSION['cds_user']['role']='viewer';
        if (cds_db()!==$pdo || $pdo->inTransaction()) throw new RuntimeException('Cached writer connection was not made read-only');
    }
    if ((int)$pdo->query('SELECT COUNT(*) FROM viewer_fixture')->fetchColumn()!==1) throw new RuntimeException('Viewer cannot read');
    foreach (['INSERT INTO viewer_fixture VALUES (2, "changed")','UPDATE viewer_fixture SET name="changed"','DELETE FROM viewer_fixture','DROP TABLE viewer_fixture'] as $sql) {
        try { $pdo->exec($sql); throw new LogicException('Write unexpectedly admitted'); }
        catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) !== 1792) throw $e; }
    }
    if ((string)$pdo->query('SELECT name FROM viewer_fixture WHERE id=1')->fetchColumn()!=='unchanged') throw new RuntimeException('Data changed');
    echo "PASS viewer MySQL/MariaDB: real connection reads, DML and DDL denied, original data retained\n";
    exit;
}
$host=getenv('CDS_VIEWER_TEST_DB_HOST');
if (!$host || !in_array($host,['127.0.0.1','localhost'],true) || getenv('CDS_VIEWER_TEST_DB_NAME')!=='cds_viewer_test') throw new RuntimeException('Disposable CI database must be explicitly configured');
$port=(int)(getenv('CDS_VIEWER_TEST_DB_PORT')?:3306);$user=getenv('CDS_VIEWER_TEST_DB_USER')?:'root';$pass=getenv('CDS_VIEWER_TEST_DB_PASSWORD')?:'';
$pdo=new PDO("mysql:host=$host;port=$port;dbname=cds_viewer_test;charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$tmp=sys_get_temp_dir().'/cds_viewer_sql_'.bin2hex(random_bytes(6));mkdir($tmp);
try {
    $pdo->exec('CREATE TABLE viewer_fixture (id INT PRIMARY KEY, name VARCHAR(100)) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO viewer_fixture VALUES (1,"unchanged")');
    copy(__DIR__.'/../includes/database.php',$tmp.'/database.php');
    file_put_contents($tmp.'/database.conf',"host=$host\nport=$port\ndatabase=cds_viewer_test\nusername=$user\npassword=$pass\ncharset=utf8mb4\n");
    $env=getenv();$env['CDS_DB_CONFIG']=$tmp.'/database.conf';
    $out='';
    foreach(['fresh','cached']as$mode){
        $p=proc_open([PHP_BINARY,__FILE__,'worker',$tmp,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);fclose($pipes[0]);$chunk=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException($chunk.$err);$out.=$mode.': '.$chunk;
    }
    if ((string)$pdo->query('SELECT name FROM viewer_fixture WHERE id=1')->fetchColumn()!=='unchanged') throw new RuntimeException('Original row changed');
    $pdo->exec('UPDATE viewer_fixture SET name="admin still writes" WHERE id=1');
    if ((string)$pdo->query('SELECT name FROM viewer_fixture WHERE id=1')->fetchColumn()!=='admin still writes')throw new RuntimeException('Admin affected');
    echo $out;
} finally {
    $pdo->exec('DROP TABLE IF EXISTS viewer_fixture');
    foreach (glob($tmp.'/*') as $file)unlink($file);rmdir($tmp);
}

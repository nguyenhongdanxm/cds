<?php
/** One-time CLI provisioning. No existing data is overwritten. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$options = getopt('', ['config:', 'code:', 'name:', 'short:', 'year:', 'department::', 'place::', 'admin::', 'help']);
if (isset($options['help'])) {
    echo "php tools/setup_school.php --config=/private/SCHOOL/instance.json --code=SCHOOL --name='Tên trường' --short='Tên ngắn' --year=2026-2027 [--department='Cơ quan'] [--place='Địa danh'] [--admin=admin]\nPassword: CDS_SETUP_PASSWORD (minimum 12 characters), or a generated secret displayed once.\n"; exit;
}
$root = dirname(__DIR__);
$lock = null;
$rootLock = null;
$created = [];
try {
    foreach (['config','code','name','short','year'] as $key) if (!is_string($options[$key] ?? null) || trim($options[$key]) === '') throw new RuntimeException('Missing --' . $key);
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $options['code'])) throw new RuntimeException('Invalid school code.');
    if (!preg_match('/^(\d{4})[-–](\d{4})$/uD', $options['year'], $years) || (int)$years[2] !== (int)$years[1]+1) throw new RuntimeException('Invalid academic year.');
    $path = $options['config'];
    if ($path[0] !== '/' || str_contains($path, "\0") || preg_match('#(?:^|/)\.\.(?:/|$)#', $path)) throw new RuntimeException('Config must be an absolute path outside the website.');
    $rootLock = fopen($root.'/includes/.school-setup.lock', 'c');
    if (!$rootLock || !flock($rootLock, LOCK_EX)) throw new RuntimeException('Cannot lock installation.');
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir,0700,true)) throw new RuntimeException('Cannot create private directory.');
    $dir = realpath($dir);
    if (!$dir || $dir === $root || str_starts_with($dir, $root.'/')) throw new RuntimeException('Config must be outside the website.');
    $path = $dir . '/' . basename($path);
    $lock = fopen($dir.'/.setup.lock','c');
    if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Cannot lock setup.');
    foreach ([$path,$root.'/includes/instance_binding.php',$root.'/data/users.json'] as $existing) if (file_exists($existing) || is_link($existing)) throw new RuntimeException('Already provisioned; no files were changed.');
    foreach ([$root.'/data',$root.'/chuyenmon/data'] as $dataDir) {
        if (is_link($dataDir)) throw new RuntimeException('Data directory must not be a symlink.');
        if (is_dir($dataDir)) foreach (new DirectoryIterator($dataDir) as $item) if (!$item->isDot() && $item->getFilename() !== '.htaccess') throw new RuntimeException('Runtime data already exists; use a clean release.');
    }
    $generated = !is_string(getenv('CDS_SETUP_PASSWORD')) || getenv('CDS_SETUP_PASSWORD') === '';
    $password = $generated ? bin2hex(random_bytes(16)) : (string)getenv('CDS_SETUP_PASSWORD');
    if (strlen($password) < 12 || strlen($password) > 72) throw new RuntimeException('Admin password must contain 12–72 bytes.');
    $admin = trim((string)($options['admin'] ?? 'admin'));
    if (!preg_match('/^[A-Za-z0-9_.@-]{3,80}$/D',$admin)) throw new RuntimeException('Invalid admin username.');
    putenv('CDS_INSTANCE_CONFIG='.$path);
    require_once $root.'/includes/instance_config.php';
    $config = ['version'=>1,'school'=>[
        'code'=>$options['code'],'name'=>trim($options['name']),'short_name'=>trim($options['short']),
        'department'=>trim((string)($options['department']??'')),'school_year'=>$options['year'],
        'cds_title'=>'CDS - '.trim($options['name']),'cds_short_title'=>'CDS '.trim($options['short']),
        'website'=>'','logo'=>'assets/icons/cds-192.png','levels'=>['thcs'=>true,'thpt'=>true],
        'chuyenmon'=>['seed_profile'=>'empty','defaults_file'=>''],
        'modules'=>['noitru'=>false,'yte'=>false],
        'forms'=>['place'=>trim((string)($options['place']??'')),'signer_name'=>'','signer_title'=>'HIỆU TRƯỞNG'],
    ],'deployment'=>['profile'=>'school','target_path'=>$root]];
    if (!cds_instance_save($config)) throw new RuntimeException('Cannot save private configuration.');
    $created[] = $path;
    foreach ([$root.'/data',$root.'/chuyenmon/data'] as $dataDir) {
        if (!is_dir($dataDir) && !mkdir($dataDir,0700,true)) throw new RuntimeException('Cannot create data directory.');
        if (file_put_contents($dataDir.'/.htaccess',"Require all denied\n", LOCK_EX) === false) throw new RuntimeException('Cannot protect data directory.');
    }
    $users = [['id'=>'u1','username'=>$admin,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'name'=>'Quản trị hệ thống','role'=>'admin','modules'=>['chuyenmon'=>'admin','csdl'=>'admin','noitru'=>'admin','vanban'=>'admin','thidua'=>'admin'],'perms'=>[],'classes'=>[],'active'=>true,'created_at'=>date('c')]];
    $file=$root.'/data/users.json';$tmp=$file.'.tmp.'.bin2hex(random_bytes(5));
    if (file_put_contents($tmp,json_encode($users,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX)===false) throw new RuntimeException('Cannot save admin.');
    chmod($tmp,0600);if(!rename($tmp,$file))throw new RuntimeException('Cannot publish admin.');
    $created[] = $file;
    $binding="<?php\n// Local installation binding; never commit or copy to another school.\nreturn ".var_export($path,true).";\n";
    if(file_put_contents($root.'/includes/instance_binding.php',$binding,LOCK_EX)===false)throw new RuntimeException('Cannot save local instance binding.');
    $created[] = $root.'/includes/instance_binding.php';
    chmod($root.'/includes/instance_binding.php',0600);
    echo "CDS_SCHOOL_SETUP_OK\nAdmin: ".$admin."\n";
    if ($generated) echo "Initial password (save securely): ".$password."\n";
    echo "Open /instance_settings.php to configure MySQL, school levels, forms and integrations; then /initial_setup.php.\n";
} catch (Throwable $e) { foreach(array_reverse($created) as $ownedFile) @unlink($ownedFile); fwrite(STDERR,'SETUP_FAILED: '.$e->getMessage()."\n"); exit(1); }
finally { if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);} if(is_resource($rootLock)){flock($rootLock,LOCK_UN);fclose($rootLock);} }

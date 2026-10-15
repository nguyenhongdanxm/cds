<?php
require __DIR__ . '/../includes/json_store.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$dir = sys_get_temp_dir() . '/cds-rows-test-' . bin2hex(random_bytes(8)); mkdir($dir,0700);
$file = $dir . '/rows.json';
try {
    mt_srand(20261015);
    for ($case=0;$case<100;$case++) {
        $rows=[];
        for($i=0,$n=mt_rand(0,30);$i<$n;$i++)$rows[]=['id'=>sprintf('%08d',$i),'number'=>mt_rand(-1000,1000),'bool'=>$i%2===0,'null'=>null,'unicode'=>'Tiếng Việt 😀','escaped'=>"\\\"\n],}{[",'nested'=>['x'=>[$i,'[a]'],'y'=>['z'=>'value']]];
        if($case===0)$rows[]=['large'=>str_repeat('é\\"{}[]',20000)];
        check(cds_json_save($file,$rows),'Seed fixture');
        check(iterator_to_array(cds_json_rows($file),false)===$rows,'Reader matches json_decode');
        check(cds_json_rows_save($file,cds_json_rows($file)),'Same-file atomic rewrite');
        check(cds_json_load($file)===$rows,'All values/order/types preserved');
    }
    foreach(['','{}','null','[1]','[{},]','[,{}]','[{} {}]','[{}','[{}]x','[{}] []','[{"x":"bad\\q"}]','[{"x":"unterminated}]']as$raw){
        file_put_contents($file,$raw);$before=hash_file('sha256',$file);
        check(!cds_json_rows_save($file,cds_json_rows($file)),'Invalid source rejected');
        check(hash_file('sha256',$file)===$before,'Invalid source bytes unchanged');
    }
    cds_json_save($file,[['id'=>'old']]);$before=hash_file('sha256',$file);
    $broken=(static function(){yield ['id'=>'valid-before-error'];throw new RuntimeException('Injected generator failure');})();
    check(!cds_json_rows_save($file,$broken)&&hash_file('sha256',$file)===$before,'Mid-stream error cannot publish partial source');
    check(!glob($file.'.tmp.*'),'Temporary files cleaned');
    echo "PASS: 100 JSON parity fixtures, UTF-8/escape/chunk boundaries, invalid/truncated input, mid-stream failure, atomic rewrite\n";
}finally{foreach(glob($dir.'/*')as$p)unlink($p);rmdir($dir);}

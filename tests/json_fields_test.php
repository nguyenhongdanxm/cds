<?php
require __DIR__ . '/../includes/json_store.php';
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$dir=sys_get_temp_dir().'/cds-fields-'.bin2hex(random_bytes(8));mkdir($dir,0700);$file=$dir.'/object.json';
try{
    for($case=0;$case<100;$case++){
        $source=['unknown'=>['text'=>str_repeat('Việt 😀 \\"{}[]', $case===0?10000:5),'bool'=>false,'null'=>null],
            'settings'=>['time'=>'20:00'],'reports'=>[['id'=>'old','nested'=>['a'=>[1,2,null]]]],'states'=>[]];
        check(cds_json_save($file,$source),'Seed object');
        check(iterator_to_array(cds_json_field_rows($file,'reports'),false)===$source['reports'],'Array region parity');
        check(cds_json_field_save($file,'reports',static function($rows){foreach($rows as$row){$row['added']='Tiếng Việt';yield$row;}yield ['id'=>'new'];}),'Stream field replacement');
        $expected=$source;$expected['reports'][0]['added']='Tiếng Việt';$expected['reports'][]=['id'=>'new'];
        check(cds_json_load($file)===$expected,'Unknown fields and types preserved');
        check(cds_json_field_save($file,'settings',static fn($old)=>array_merge($old,['new'=>true]),true),'Metadata value replacement');
        $expected['settings']['new']=true;check(cds_json_load($file)===$expected,'Metadata parity');
        check(cds_json_field_save($file,'added',static fn($old)=>['x'=>true],true),'Missing field append');
        $expected['added']=['x'=>true];check(cds_json_load($file)===$expected,'Append preserves object');
    }
    foreach(['{}'," { } \n",'{"reports":null}']as$raw){file_put_contents($file,$raw);check(cds_json_field_save($file,'reports',static fn($rows)=>[['id'=>'new']]),'Empty/null source supported');check(cds_json_load($file)['reports']===[['id'=>'new']],'Empty append parity');}
    foreach(['','[]','null','{"reports":[],}','{"reports":[{}],"bad":','{"reports":[{},]}','{"reports":[],"unknown":{"x":}}','{"reports":[{}]}x']as$raw){
        file_put_contents($file,$raw);$hash=hash_file('sha256',$file);
        check(!cds_json_field_save($file,'reports',static fn($rows)=>[]),'Malformed object refused');
        check(hash_file('sha256',$file)===$hash,'Malformed source unchanged');
    }
    cds_json_save($file,['reports'=>[['id'=>'original']],'settings'=>[]]);$hash=hash_file('sha256',$file);
    check(!cds_json_field_save($file,'reports',static function($rows){yield ['id'=>'partial'];throw new RuntimeException('Injected transform failure');}),'Transform failure refused');
    check(hash_file('sha256',$file)===$hash,'Partial transform never published');
    check(count(glob($dir.'/*'))===1,'Temporary files cleaned');
    echo "PASS: 100 object fixtures, nested/UTF-8/chunk boundaries, unknown fields, metadata, absent/null fields, corrupt sources and interrupted transformation\n";
}finally{foreach(glob($dir.'/*')as$p)unlink($p);rmdir($dir);}

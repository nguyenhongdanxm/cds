<?php
$tmp=sys_get_temp_dir().'/teacher-signatures-'.bin2hex(random_bytes(6));mkdir($tmp);define('DATA_PATH',$tmp);define('CDS_TEACHER_SIGNATURE_DATA',$tmp);define('CDS_TEACHER_SIGNATURE_LEGACY_DATA',$tmp.'/cm');
require __DIR__.'/../includes/json_store.php';require __DIR__.'/../includes/teacher_signatures.php';
function load_json($f,$d=[]){return cds_json_load($f,$d);}function check($v,$m){if(!$v)throw new RuntimeException($m);}
$teachers=[['id'=>'a','name'=>'Nguyễn An'],['id'=>'b','name'=>'Trần Bình'],['id'=>'d1','name'=>'Trùng Tên'],['id'=>'d2','name'=>'Trùng Tên']];
function csdl_teachers_all(){global$teachers;return$teachers;}
function get_users(){return [['id'=>'ua','teacher_id'=>'a','name'=>'Nguyễn An'],['id'=>'ub','teacher_id'=>'b','name'=>'Trần Bình'],['id'=>'bad','teacher_id'=>'missing','name'=>'Nguyễn An']];}
function can_perm_level($p,$l){return false;}
$actor=['id'=>'ua','teacher_id'=>'a','name'=>'Nguyễn An','role'=>'teacher'];
try{
 mkdir($tmp.'/lesson_book_signatures');$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5N8AAAAASUVORK5CYII=');file_put_contents($tmp.'/lesson_book_signatures/old.png',$png);file_put_contents($tmp.'/lesson_book_signatures/new.png',$png);
 $legacy=[['user_id'=>'ua','teacher'=>'Nguyễn An','path'=>$tmp.'/lesson_book_signatures/old.png','version'=>'1','created_at'=>'2026-01-01'],['user_id'=>'ua','teacher'=>'Nguyễn An','path'=>$tmp.'/lesson_book_signatures/new.png','version'=>'2','created_at'=>'2026-02-01'],['user_id'=>'unknown','teacher'=>'Trùng Tên','path'=>$tmp.'/lesson_book_signatures/old.png'],['user_id'=>'bad','teacher'=>'Nguyễn An','path'=>$tmp.'/lesson_book_signatures/old.png']];cds_json_save($tmp.'/lesson_book_signatures.json',$legacy);
 check(cds_ts_teacher(['teacher_id'=>'missing','name'=>'Nguyễn An'])===null,'Explicit invalid link never guesses by name');check(cds_ts_teacher(['name'=>'Trùng Tên'])===null,'Duplicate names fail closed');
 check(cds_ts_teacher(['name'=>' NGUYỄN  AN '])['id']==='a','Unique normalized name match');
 check(cds_ts_meta('a')['version']==='2','Latest legacy image available without migration');check(!is_file($tmp.'/teacher_signatures.json'),'Read has no write side effect');
 $before=file_get_contents($tmp.'/lesson_book_signatures.json');$m=cds_ts_migrate_legacy();check($m['ok']&&$m['count']===1,'Only unambiguous existing teacher imported');check(cds_ts_migrate_legacy()['count']===0,'Idempotent migration');check(file_get_contents($tmp.'/lesson_book_signatures.json')===$before,'Legacy snapshots unchanged');
 check(!cds_ts_can_manage('b',$actor),'Cannot change other teacher');check(!cds_ts_can_manage('a',array_merge($actor,['role'=>'viewer'])),'Viewer cannot manage own signature');
 $snapshot=cds_ts_snapshot($actor,'Nguyễn An');check($snapshot&&$snapshot['version']==='2','Confirmation freezes own version');check(cds_ts_snapshot($actor,'Trần Bình')===null,'Cannot sign another teacher name');check(cds_ts_snapshot(array_merge($actor,['role'=>'viewer']),'Nguyễn An')===null,'Viewer cannot sign');
 check(cds_ts_remove('a',$actor),'Own removal recorded');check(cds_ts_meta('a')===null,'Removal blocks legacy fallback');check(cds_ts_migrate_legacy()['count']===0,'Migration cannot revive removed signature');check(cds_ts_data_uri($snapshot)!=='','Frozen document image still available after removal');
 file_put_contents($tmp.'/secret.png',$png);check(cds_ts_data_uri(['path'=>$tmp.'/secret.png'])==='','Outside signature directories denied');symlink($tmp.'/secret.png',$tmp.'/lesson_book_signatures/link.png');check(cds_ts_path($tmp.'/lesson_book_signatures/link.png')==='','Symlink outside private roots denied');
 check(cds_ts_data_uri(['path'=>'https://example.com/image.png'])==='','External URLs never fetched');
 echo "PASS teacher registry: unique ID resolution, latest legacy import, idempotence, removed tombstones, immutable snapshots, permissions and confined image paths\n";
}finally{foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$p){if($p->isDir()&&!$p->isLink())rmdir($p->getPathname());else unlink($p->getPathname());}rmdir($tmp);}

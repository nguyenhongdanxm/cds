<?php
require_once __DIR__.'/../includes/json_store.php';
$root=sys_get_temp_dir().'/notice-multi-'.bin2hex(random_bytes(6));mkdir($root);define('DATA_PATH',$root);define('BASE_URL','/');
function load_json($file,$default=[]){return cds_json_load($file,$default);}
function check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
function reject($fn,$msg){try{$fn();}catch(RuntimeException $e){return;}throw new LogicException($msg);}
require_once __DIR__.'/../includes/login_notice_store.php';
$admin=['role'=>'admin'];$a=['id'=>'a'];$b=['id'=>'b'];
try{
 $legacy=['id'=>'legacy','version'=>'legacy-version','enabled'=>true,'title'=>'Thông báo cũ','body'=>'<p>Cũ</p>','starts_at'=>'','ends_at'=>'','updated_at'=>'2026-01-01T00:00:00Z'];file_put_contents(cds_ln_path(),json_encode($legacy));
 check(cds_ln_pending($a)['id']==='legacy','Legacy readable');check(cds_ln_dismiss($a,'legacy'),'Legacy receipt');
 $edit=cds_ln_save(array_merge($legacy,['starts_at'=>'','ends_at'=>'','enabled'=>false]),$admin);
 check(count(cds_ln_all())===1&&cds_ln_load('legacy')['id']==='legacy','Migration preserves identity');check(is_file(cds_ln_receipt_path($a,'legacy')),'Migration preserves receipt');
 $ids=[];for($i=0;$i<5;$i++){$n=cds_ln_save(['id'=>'','version'=>'','enabled'=>true,'title'=>'Thông báo '.($i+1),'body'=>'<p>Nội dung</p>','starts_at'=>'2099-01-01T10:'.sprintf('%02d',$i*10),'ends_at'=>'2099-01-01T12:00'],$admin);$ids[]=$n['id'];}
 check(count(cds_ln_all())===6&&cds_ln_planned_count(cds_ln_all())===5,'Five schedules plus history');
 reject(fn()=>cds_ln_save(['id'=>'','version'=>'','enabled'=>true,'title'=>'Thứ sáu','body'=>'<p>Không</p>'],$admin),'Sixth planned notice rejected');
 $time=strtotime('2099-01-01T11:00:00+07:00');check(array_column(cds_ln_pending_all($a,$time),'id')===$ids,'Queue follows start time');
 check(cds_ln_dismiss($a,$ids[0]),'Dismiss first');check(count(cds_ln_pending_all($a,$time))===4&&count(cds_ln_pending_all($b,$time))===5,'Receipts independent for account and notice');
 $old=cds_ln_load($ids[1]);$update=array_merge($old,['title'=>'Đã sửa','starts_at'=>cds_ln_date_input($old['starts_at']),'ends_at'=>cds_ln_date_input($old['ends_at'])]);$updated=cds_ln_save($update,$admin);
 check(cds_ln_load($ids[1])['title']==='Đã sửa'&&count(cds_ln_all())===6,'Edit targets one record');
 $before=file_get_contents(cds_ln_path());reject(fn()=>cds_ln_save($update,$admin),'Stale record version rejected');check(file_get_contents(cds_ln_path())===$before,'Conflict atomic');
 reject(fn()=>cds_ln_delete($ids[1],$updated['version'],['role'=>'teacher']),'Delete requires admin');
 cds_ln_delete($ids[1],$updated['version'],$admin);check(cds_ln_load($ids[1])['id']===''&&count(cds_ln_all())===5,'Delete only chosen notice');
 check(count(cds_ln_pending_all($a,$time))===3,'Deleted notice leaves queue');
 $draft=cds_ln_save(['id'=>'','version'=>'','title'=>'Bản nháp','body'=>'<p>Nháp</p>','enabled'=>false],$admin);check(cds_ln_planned_count(cds_ln_all())===4,'Draft does not occupy planned slot');
 cds_ln_delete($draft['id'],$draft['version'],$admin);
 echo "PASS: legacy migration and receipts, five schedules, capacity limit, chronological queue, independent edits/deletes, conflicts, drafts\n";
}finally{$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($root);}

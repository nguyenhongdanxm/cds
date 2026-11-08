<?php
/** Opt-in authoritative lesson store. No DB connection unless the exact store is routed. */
function cds_lb_primary_file():string{return defined('LB_RECORDS_FILE')?(string)LB_RECORDS_FILE:__DIR__.'/../chuyenmon/data/lesson_book_records.json';}
function cds_lb_primary_marker():string{return dirname(cds_lb_primary_file()).'/lesson_book_sql_primary.php';}
function cds_lb_primary_enabled():bool{$p=cds_lb_primary_marker();clearstatcache(true,$p);return is_file($p);}
function cds_lb_primary_is_store(string $file):bool{
 $target=cds_lb_primary_file();if(basename($file)!==basename($target))return false;
 $a=realpath(dirname($file));$b=realpath(dirname($target));return $a!==false&&$a===$b;
}
function cds_lb_primary_routes(string $file):bool{return cds_lb_primary_is_store($file)&&cds_lb_primary_enabled();}
function cds_lb_primary_date_index(PDO $pdo):void{
 $stmt=$pdo->query("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='cds_lesson_book_records' AND index_name='idx_cds_lb_lesson_date'");
 if(!(int)$stmt->fetchColumn())$pdo->exec('ALTER TABLE cds_lesson_book_records ADD KEY idx_cds_lb_lesson_date (lesson_date,session_code,timetable_period,class_name)');
}

function cds_lb_primary_boot():void{if(!function_exists('cds_lb_upsert'))require_once __DIR__.'/database_lesson_book.php';}
function cds_lb_primary_rows():array{cds_lb_primary_boot();return cds_lb_sql_all();}
function cds_lb_snapshot_rows():array{
 $p=cds_lb_primary_file();if(!is_file($p))return[];$raw=file_get_contents($p);if($raw===false)throw new RuntimeException('Không đọc được bản JSON sổ đầu bài.');$rows=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($rows)||!array_is_list($rows))throw new RuntimeException('Bản JSON sổ đầu bài không hợp lệ.');return$rows;
}
function cds_lb_primary_map(array $rows):array{
 $out=[];$claims=[];foreach($rows as$r){if(!is_array($r))throw new RuntimeException('Sổ đầu bài có dòng không hợp lệ.');$id=(string)($r['slot_id']??'');if($id===''||trim($id)!==$id||strlen($id)>64||isset($out[$id]))throw new RuntimeException('Mã tiết trống, quá dài hoặc trùng; chưa thay đổi dữ liệu.');$out[$id]=$r;
 if(cds_lb_claims_ppct($r)){$en=cds_lb_enrich($r);$key=$en['school_year_key'].'|'.$en['class_key'].'|'.$en['subject_key'].'|'.(int)$en['ppct_period'];if(isset($claims[$key]))throw new RuntimeException('Trùng tiết PPCT đã ký; chưa thay đổi dữ liệu.');$claims[$key]=true;}}
 return$out;
}
function cds_lb_primary_put(PDO $pdo,array $row):void{
 $id=(string)$row['slot_id'];$check=$pdo->prepare('SELECT slot_id FROM cds_lesson_book_records WHERE slot_id=?');$check->execute([$id]);$existing=$check->fetchColumn();if($existing!==false&&(string)$existing!==$id)throw new RuntimeException('Mã tiết xung đột theo đối chiếu MySQL.');
 if(cds_lb_claims_ppct($row)){$en=cds_lb_enrich($row);$check=$pdo->prepare('SELECT slot_id FROM cds_lesson_book_records WHERE school_year_key=? AND class_key=? AND subject_key=? AND ppct_period=?');$check->execute([$en['school_year_key'],$en['class_key'],$en['subject_key'],(int)$en['ppct_period']]);$owner=$check->fetchColumn();if($owner!==false&&(string)$owner!==$id)throw new RuntimeException('Tiết PPCT đã có bản ghi khác; chưa thay đổi dữ liệu.');}
 cds_lb_upsert($pdo,$row);
 // Indexes may be normalized, but the signed payload must remain exactly as supplied.
 $pdo->prepare('UPDATE cds_lesson_book_records SET raw_json=? WHERE slot_id=?')->execute([json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),(string)$row['slot_id']]);
}
/** Caller holds the same per-store lock used by legacy JSON writers and mode changes. */
function cds_lb_primary_update(callable $change):bool{
 cds_lb_primary_boot();$pdo=cds_db();if($pdo->inTransaction())throw new RuntimeException('Không thể lồng giao dịch sổ đầu bài.');$pdo->beginTransaction();
 try{
  $stmt=$pdo->query('SELECT raw_json FROM cds_lesson_book_records ORDER BY lesson_date,session_code,timetable_period,class_name,slot_id FOR UPDATE');$rows=[];while(($raw=$stmt->fetchColumn())!==false){$r=json_decode($raw,true,512,JSON_THROW_ON_ERROR);if(!is_array($r))throw new RuntimeException('Dữ liệu MySQL không hợp lệ.');$rows[]=$r;}
  $old=cds_lb_primary_map($rows);$next=$change($rows);if($next===false){$pdo->rollBack();return false;}if(!is_array($next))throw new RuntimeException('Kết quả cập nhật không hợp lệ.');$new=cds_lb_primary_map($next);
  $changed=[];foreach($new as$id=>$r)if(!isset($old[$id])||$old[$id]!==$r)$changed[$id]=$r;
  // Release claims inside the transaction so valid multi-row PPCT moves do not collide transiently.
  $release=$pdo->prepare('UPDATE cds_lesson_book_records SET ppct_period=NULL WHERE slot_id=?');foreach($changed as$id=>$r)$release->execute([$id]);
  $delete=$pdo->prepare('DELETE FROM cds_lesson_book_records WHERE slot_id=?');foreach($old as$id=>$r)if(!isset($new[$id]))$delete->execute([$id]);
  foreach($changed as$r)cds_lb_primary_put($pdo,$r);$pdo->commit();return true;
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();if($e instanceof RuntimeException)throw $e;throw new RuntimeException('Dữ liệu SQL không hợp lệ; chưa thay đổi sổ.',0,$e);}
}
function cds_lb_primary_replace(array $rows):bool{return cds_json_update(cds_lb_primary_file(),static fn()=>array_values($rows),[]);}
function cds_lb_snapshot_publish(array $rows):void{
 $file=cds_lb_primary_file();$temp=tempnam(dirname($file),'.lb-snapshot-');if($temp===false)throw new RuntimeException('Không tạo được bản JSON mới.');
 try{$raw=json_encode(array_values($rows),JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);if(file_put_contents($temp,$raw)!==strlen($raw)||!chmod($temp,0640)||!rename($temp,$file))throw new RuntimeException('Không xuất được bản JSON mới nhất.');}finally{if(is_file($temp))unlink($temp);}
}
/** Called only from the existing authenticated/CSRF-protected database admin action. */
function cds_lb_primary_switch(bool $enabled):void{
 cds_lb_primary_boot();$file=cds_lb_primary_file();$lock=fopen($file.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Không khóa được nguồn sổ đầu bài.');
 try{
  if($enabled===cds_lb_primary_enabled())return;
  if($enabled){
   if(!cds_lb_shadow_enabled()||!cds_lb_read_configured()||cds_lb_pending_status())throw new RuntimeException('Cần bật ghi bản sao, đọc MySQL và xử lý hết đồng bộ chờ trước.');
   $json=cds_lb_snapshot_rows();$sql=cds_lb_primary_rows();cds_lb_primary_map($json);if(cds_lb_hash_map($json)!==cds_lb_hash_map($sql))throw new RuntimeException('JSON và MySQL chưa đối chiếu khớp; chưa đổi nguồn.');
   cds_lb_primary_date_index(cds_db());
   // Keep the JSON untouched as the pre-switch backup and preserve exact signed payloads in SQL.
   $pdo=cds_db();$pdo->beginTransaction();try{foreach($json as$r)$pdo->prepare('UPDATE cds_lesson_book_records SET raw_json=? WHERE slot_id=?')->execute([json_encode($r,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),(string)$r['slot_id']]);$pdo->commit();}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
   $marker=cds_lb_primary_marker();$temp=tempnam(dirname($marker),'.lb-mode-');if($temp===false)throw new RuntimeException('Không tạo được cấu hình nguồn.');try{if(file_put_contents($temp,"<?php http_response_code(404); exit; ?>\n")===false||!chmod($temp,0640)||!rename($temp,$marker))throw new RuntimeException('Không bật được nguồn MySQL.');}finally{if(is_file($temp))unlink($temp);}
  }else{
   // Never disable routing before a current, verified JSON snapshot exists.
   $sql=cds_lb_primary_rows();$expected=cds_lb_primary_map($sql);cds_lb_snapshot_publish($sql);$actual=cds_lb_primary_map(cds_lb_snapshot_rows());ksort($expected);ksort($actual);if($expected!==$actual)throw new RuntimeException('Bản JSON mới chưa khớp; vẫn giữ nguồn MySQL.');if(!unlink(cds_lb_primary_marker()))throw new RuntimeException('Không đổi được nguồn; vẫn giữ MySQL.');
  }
  unset($GLOBALS['cds_lb_read_status'],$GLOBALS['cds_force_json_lb_read'],$GLOBALS['lb_rows_cache'],$GLOBALS['lb_slots_cache']);
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}

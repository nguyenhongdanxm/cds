<?php
/** Optional meal SQL authority. No connection or schema change until explicitly enabled. */
function cds_meal_primary_marker(): string {
    return defined('DATA_PATH') ? DATA_PATH.'/noitru/meal_sql_primary.php' : '';
}
function cds_meal_primary_enabled(): bool {
    $path=cds_meal_primary_marker();
    if($path==='')return false;
    clearstatcache(true,$path);
    return is_file($path);
}
function cds_meal_primary_key(string $file): ?string {
    if(!defined('DATA_PATH'))return null;
    $dir=realpath(DATA_PATH.'/noitru');
    if($dir===false||realpath(dirname($file))!==$dir)return null;
    return [
        'meals_daily.json'=>'daily', 'meal_reports.json'=>'reports',
        'meal_history.json'=>'history', 'meal_history_deleted.json'=>'deleted',
        'meal_history_deleted_targets.json'=>'targets',
    ][basename($file)]??null;
}
function cds_meal_primary_routes(string $file): bool {
    return empty($GLOBALS['cds_meal_primary_raw']) && cds_meal_primary_key($file)!==null && cds_meal_primary_enabled();
}
/** The same lock covers source switching, JSON writers, and SQL writers. */
function cds_meal_domain_lock() {
    $dir=DATA_PATH.'/noitru';
    if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('Không tạo được kho báo ăn.');
    $lock=fopen($dir.'/.meal-write.lock','c');
    if(!$lock||!flock($lock,LOCK_EX)){if(is_resource($lock))fclose($lock);throw new RuntimeException('Không khóa được báo ăn.');}
    return $lock;
}
function cds_meal_primary_json($value): string {
    try{return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    catch(Throwable $e){throw new RuntimeException('Không mã hóa được dữ liệu báo ăn.',0,$e);}
}
function cds_meal_primary_decode(string $raw) {
    try{return json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
    catch(Throwable $e){throw new RuntimeException('Dữ liệu báo ăn MySQL không hợp lệ; không dùng JSON cũ.',0,$e);}
}
function cds_meal_primary_schema(): void {
    cds_db()->exec("CREATE TABLE IF NOT EXISTS cds_meal_primary_rows (
        kind VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        row_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        source_position BIGINT UNSIGNED NOT NULL,
        meal_date DATE NOT NULL,
        student_id VARCHAR(100) NOT NULL DEFAULT '',
        id_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        visibility_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        raw_json LONGTEXT NOT NULL,
        PRIMARY KEY(kind,row_key),
        KEY idx_meal_primary_id(kind,id_key),
        KEY idx_meal_primary_visibility(kind,visibility_key),
        KEY idx_meal_primary_order(kind,source_position),
        KEY idx_meal_primary_date(kind,meal_date,source_position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
    cds_db()->exec("CREATE TABLE IF NOT EXISTS cds_meal_primary_meta (
        store_key VARCHAR(32) NOT NULL PRIMARY KEY, raw_json LONGTEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin");
}
function cds_meal_primary_identity(string $kind,array $row): string {
    $date=(string)($row['date']??'');
    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date))throw new RuntimeException('Báo ăn thiếu ngày hợp lệ.');
    $parts=$kind==='daily'?[$date,(string)($row['student_id']??'')]:[$date,(string)($row['class_name']??''),(string)($row['meal']??'')];
    if(trim($parts[1])===''||($kind==='reports'&&!in_array($parts[2],['sang','trua','toi'],true)))throw new RuntimeException('Báo ăn thiếu học sinh/lớp/bữa.');
    return hash('sha256',cds_meal_primary_json($parts));
}
/** Keyset pages retain original source order, including same-date legacy rows. */
function cds_meal_primary_rows(string $kind,?string $from=null,?string $to=null): Generator {
    if(!in_array($kind,['daily','reports'],true))throw new RuntimeException('Kho báo ăn không hợp lệ.');
    $last=-1;
    do{
        $index=($from!==null&&$to!==null&&strtotime($to)-strtotime($from)<=31*86400)?' FORCE INDEX (idx_meal_primary_date)':'';
        $sql='SELECT source_position,raw_json FROM cds_meal_primary_rows'.$index.' WHERE kind=? AND source_position>?';
        $values=[$kind,$last];
        if($from!==null){$sql.=' AND meal_date>=?';$values[]=$from;}
        if($to!==null){$sql.=' AND meal_date<=?';$values[]=$to;}
        $stmt=cds_db()->prepare($sql.' ORDER BY source_position LIMIT 250');$stmt->execute($values);
        $batch=$stmt->fetchAll(PDO::FETCH_ASSOC);$stmt->closeCursor();
        foreach($batch as $record){
            $last=(int)$record['source_position'];$row=cds_meal_primary_decode($record['raw_json']);
            if(!is_array($row))throw new RuntimeException('Bản ghi báo ăn MySQL không hợp lệ.');
            yield $last=>$row;
        }
    }while(count($batch)===250);
}
function cds_meal_primary_meta(string $key) {
    $stmt=cds_db()->prepare('SELECT raw_json FROM cds_meal_primary_meta WHERE store_key=?');$stmt->execute([$key]);
    $raw=$stmt->fetchColumn();
    if($raw===false)throw new RuntimeException('Thiếu trạng thái nguồn báo ăn MySQL.');
    return cds_meal_primary_decode($raw);
}
function cds_meal_primary_meta_save(string $key,$value): void {
    cds_db()->prepare('INSERT INTO cds_meal_primary_meta(store_key,raw_json) VALUES(?,?) ON DUPLICATE KEY UPDATE raw_json=VALUES(raw_json)')->execute([$key,cds_meal_primary_json($value)]);
}
function cds_meal_primary_load(string $file) {
    $key=cds_meal_primary_key($file);
    if($key==='daily')return iterator_to_array(cds_meal_primary_rows('daily'),false);
    if($key==='reports'){$meta=cds_meal_primary_meta('reports');$meta['reports']=iterator_to_array(cds_meal_primary_rows('reports'),false);return $meta;}
    return cds_meal_primary_meta($key);
}
/** Nested failures doom the whole meal operation, even if an old caller ignores false. */
function cds_meal_primary_atomic(callable $write,$failure=false) {
    if(!empty($GLOBALS['cds_meal_primary_transaction'])){
        try{$result=$write();if($result===false)$GLOBALS['cds_meal_primary_failed']=true;return $result;}
        catch(Throwable $e){$GLOBALS['cds_meal_primary_failed']=true;throw $e;}
    }
    $lock=null;$pdo=null;
    try{
        if(empty($GLOBALS['noitru_meal_write_active']))$lock=cds_meal_domain_lock();
        if(!cds_meal_primary_enabled())throw new RuntimeException('Nguồn báo ăn đã đổi; hãy tải lại.');
        $pdo=cds_db();$pdo->beginTransaction();
        $GLOBALS['cds_meal_primary_transaction']=true;$GLOBALS['cds_meal_primary_failed']=false;
        $result=$write();
        if($result===false||!empty($GLOBALS['cds_meal_primary_failed'])||($failure!==false&&$result===$failure)){$pdo->rollBack();return $failure;}
        $pdo->commit();return $result;
    }catch(Throwable $e){
        if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();
        error_log('[CDS meal primary] '.$e->getMessage());return $failure;
    }finally{
        unset($GLOBALS['cds_meal_primary_transaction'],$GLOBALS['cds_meal_primary_failed']);
        if(function_exists('noitru_meal_read_cache_clear'))noitru_meal_read_cache_clear();
        if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}
    }
}
function cds_meal_primary_put(string $kind,int $position,array $row,string $table='cds_meal_primary_rows'): void {
    if(!in_array($table,['cds_meal_primary_rows','cds_meal_primary_stage'],true))throw new RuntimeException('Bảng không hợp lệ.');
    $key=cds_meal_primary_identity($kind,$row);
    $sql='INSERT INTO '.$table.'(kind,row_key,source_position,meal_date,student_id,id_key,visibility_key,raw_json) VALUES(?,?,?,?,?,?,?,?)';
    if($table==='cds_meal_primary_rows')$sql.=' ON DUPLICATE KEY UPDATE source_position=VALUES(source_position),id_key=VALUES(id_key),visibility_key=VALUES(visibility_key),raw_json=VALUES(raw_json)';
    cds_db()->prepare($sql)->execute([$kind,$key,$position,$row['date'],(string)($row['student_id']??''),hash('sha256',(string)($row['id']??'')),hash('sha256',implode('|',array_map('trim',[(string)($row['date']??''),(string)($row['class_name']??''),(string)($row['meal']??'')]))),cds_meal_primary_json($row)]);
}
/** Rare whole-list transforms stage on SQL; unchanged payloads are not rewritten. */
function cds_meal_primary_replace_rows(string $kind,iterable $rows): bool {
    return cds_meal_primary_atomic(static function()use($kind,$rows){
        $pdo=cds_db();$pdo->exec('DROP TEMPORARY TABLE IF EXISTS cds_meal_primary_stage');
        $pdo->exec('CREATE TEMPORARY TABLE cds_meal_primary_stage LIKE cds_meal_primary_rows');
        try{
            $position=0;foreach($rows as $row){if(!is_array($row))throw new RuntimeException('Dòng báo ăn không hợp lệ.');cds_meal_primary_put($kind,$position++,$row,'cds_meal_primary_stage');}
            $stmt=$pdo->prepare('DELETE live FROM cds_meal_primary_rows live LEFT JOIN cds_meal_primary_stage stage ON live.kind=stage.kind AND live.row_key=stage.row_key WHERE live.kind=? AND stage.row_key IS NULL');$stmt->execute([$kind]);
            $pdo->exec('INSERT INTO cds_meal_primary_rows(kind,row_key,source_position,meal_date,student_id,id_key,visibility_key,raw_json)
                SELECT stage.kind,stage.row_key,stage.source_position,stage.meal_date,stage.student_id,stage.id_key,stage.visibility_key,stage.raw_json FROM cds_meal_primary_stage stage
                LEFT JOIN cds_meal_primary_rows live ON live.kind=stage.kind AND live.row_key=stage.row_key
                WHERE live.row_key IS NULL OR live.source_position<>stage.source_position OR BINARY live.raw_json<>BINARY stage.raw_json
                ON DUPLICATE KEY UPDATE source_position=VALUES(source_position),id_key=VALUES(id_key),visibility_key=VALUES(visibility_key),raw_json=VALUES(raw_json)');
            return true;
        }finally{$pdo->exec('DROP TEMPORARY TABLE IF EXISTS cds_meal_primary_stage');}
    });
}
function cds_meal_primary_save(string $file,$data): bool {
    return cds_meal_primary_atomic(static function()use($file,$data){
        if(!is_array($data))throw new RuntimeException('Kho báo ăn phải là danh sách/đối tượng.');
        $key=cds_meal_primary_key($file);
        if($key==='daily')return cds_meal_primary_replace_rows('daily',$data);
        if($key==='reports'){
            if(!cds_meal_primary_replace_rows('reports',$data['reports']??[]))return false;
            unset($data['reports']);
        }
        cds_meal_primary_meta_save($key,$data);return true;
    });
}
function cds_meal_primary_field_save(string $name,callable $build,bool $valueMode): bool {
    return cds_meal_primary_atomic(static function()use($name,$build,$valueMode){
        if($name==='reports')return cds_meal_primary_replace_rows('reports',$build($valueMode?iterator_to_array(cds_meal_primary_rows('reports'),false):cds_meal_primary_rows('reports')));
        $meta=cds_meal_primary_meta('reports');$meta[$name]=$build($meta[$name]??[]);
        cds_meal_primary_meta_save('reports',$meta);return true;
    });
}
/** Select only requested daily keys. Keep positions for the unchanged PHP merge rules. */
function cds_meal_primary_candidates(array $updates): Generator {
    $keys=[];foreach($updates as $row)$keys[cds_meal_primary_identity('daily',$row)]=true;
    foreach(array_chunk(array_keys($keys),200)as $batch){
        $stmt=cds_db()->prepare("SELECT source_position,raw_json FROM cds_meal_primary_rows WHERE kind='daily' AND row_key IN (".implode(',',array_fill(0,count($batch),'?')).') ORDER BY source_position');$stmt->execute($batch);
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as $record)yield (int)$record['source_position']=>cds_meal_primary_decode($record['raw_json']);
    }
}
function cds_meal_primary_patch(array $changes,array $append): bool {
    return cds_meal_primary_atomic(static function()use($changes,$append){
        $pdo=cds_db();
        foreach($changes as $position=>$row){
            $stmt=$pdo->prepare("SELECT row_key FROM cds_meal_primary_rows WHERE kind='daily' AND source_position=?");$stmt->execute([$position]);
            if($stmt->fetchColumn()!==cds_meal_primary_identity('daily',$row))throw new RuntimeException('Vị trí báo ăn đã thay đổi.');
            cds_meal_primary_put('daily',(int)$position,$row);
        }
        $position=(int)$pdo->query("SELECT COALESCE(MAX(source_position),-1)+1 FROM cds_meal_primary_rows WHERE kind='daily'")->fetchColumn();
        foreach($append as $row){
            $key=cds_meal_primary_identity('daily',$row);$stmt=$pdo->prepare("SELECT row_key FROM cds_meal_primary_rows WHERE kind='daily' AND row_key=?");$stmt->execute([$key]);
            if($stmt->fetchColumn()!==false)throw new RuntimeException('Báo ăn đã tồn tại; không ghi đè.');
            cds_meal_primary_put('daily',$position++,$row);
        }
        return true;
    });
}
function cds_meal_primary_files(): array {
    $dir=DATA_PATH.'/noitru/';
    return ['daily'=>$dir.'meals_daily.json','reports'=>$dir.'meal_reports.json','history'=>$dir.'meal_history.json','deleted'=>$dir.'meal_history_deleted.json','targets'=>$dir.'meal_history_deleted_targets.json'];
}
function cds_meal_primary_digest(iterable $rows): string {
    $hash=hash_init('sha256');
    foreach($rows as $row){$raw=cds_meal_primary_json($row);hash_update($hash,strlen($raw).':'.$raw);}
    return hash_final($hash);
}
/** Explicit, locked source transition. Never activate on a normal page load. */
function cds_meal_primary_switch(bool $enabled): void {
    if(function_exists('cds_is_viewer')&&cds_is_viewer())throw new RuntimeException('Tài khoản chỉ xem.');
    $lock=cds_meal_domain_lock();$pdo=null;$temps=[];
    try{
        if($enabled===cds_meal_primary_enabled())return;
        $files=cds_meal_primary_files();
        if($enabled){
            if(!cds_meal_shadow_enabled()||!cds_meal_sql_read_effective()||cds_meal_pending_status()||is_file(cds_meal_inflight_path()))throw new RuntimeException('Cần đồng bộ, đối chiếu và bật đọc MySQL trước; không còn lần ghi đang chờ.');
            if(empty(cds_meal_compare_snapshot()['is_match']))throw new RuntimeException('Báo ăn JSON/MySQL chưa khớp.');
            cds_meal_primary_schema();$pdo=cds_db();$pdo->beginTransaction();
            $pdo->exec('DELETE FROM cds_meal_primary_rows');$pdo->exec('DELETE FROM cds_meal_primary_meta');
            foreach(cds_json_rows($files['daily'])as $i=>$row)cds_meal_primary_put('daily',$i,$row);
            foreach(cds_json_field_rows($files['reports'],'reports')as $i=>$row)cds_meal_primary_put('reports',$i,$row);
            $meta=[];$fields=cds_json_fields($files['reports']);
            foreach($fields as $name=>[$at,$length])if($name!=='reports')$meta[$name]=cds_meal_primary_decode(file_get_contents($files['reports'],false,null,$at,$length));
            cds_meal_primary_meta_save('reports',$meta);
            foreach(['history','deleted','targets']as $key)cds_meal_primary_meta_save($key,cds_json_load_for_update($files[$key],[]));
            foreach(['daily','reports']as $kind){
                $rows=$kind==='daily'?cds_json_rows($files[$kind]):cds_json_field_rows($files[$kind],'reports');
                if(cds_meal_primary_digest($rows)!==cds_meal_primary_digest(cds_meal_primary_rows($kind)))throw new RuntimeException('Thứ tự/nội dung nguồn chưa khớp, có thể có khóa trùng; chưa đổi nguồn.');
            }
            $pdo->commit();
            $tmp=tempnam(dirname(cds_meal_primary_marker()),'meal-mode.');$temps[]=$tmp;
            $bytes="<?php http_response_code(404); exit; /* CDS meal SQL authority */\n";
            if($tmp===false||file_put_contents($tmp,$bytes)!==strlen($bytes)||!chmod($tmp,0640)||!rename($tmp,cds_meal_primary_marker()))throw new RuntimeException('Không công bố được nguồn MySQL; JSON vẫn là nguồn chính.');
        }else{
            // Prepare every latest file first. Partial publication still leaves SQL authoritative.
            foreach($files as $key=>$file){
                $tmp=tempnam(dirname($file),'meal-rollback.');if($tmp===false)throw new RuntimeException('Không tạo được bản quay về JSON.');$temps[]=$tmp;
                if($key==='daily'){
                    if(!cds_json_rows_save($tmp,cds_meal_primary_rows('daily'))||cds_meal_primary_digest(cds_json_rows($tmp))!==cds_meal_primary_digest(cds_meal_primary_rows('daily')))throw new RuntimeException('Không đối chiếu được báo ăn cá nhân mới nhất.');
                }elseif($key==='reports'){
                    $meta=cds_meal_primary_meta('reports');
                    if(!cds_json_save($tmp,$meta?:new stdClass())||!cds_json_field_save($tmp,'reports',static fn()=>cds_meal_primary_rows('reports')))throw new RuntimeException('Không xuất được phiếu báo ăn mới nhất.');
                    $out=cds_json_load_for_update($tmp,[]);unset($out['reports']);
                    if($out!==$meta||cds_meal_primary_digest(cds_json_field_rows($tmp,'reports'))!==cds_meal_primary_digest(cds_meal_primary_rows('reports')))throw new RuntimeException('Phiếu báo ăn mới nhất chưa khớp.');
                }else{
                    $value=cds_meal_primary_meta($key);
                    if(!cds_json_save($tmp,$value)||cds_json_load_for_update($tmp,[])!==$value)throw new RuntimeException('Không đối chiếu được nhật ký/trạng thái xóa.');
                }
                $prepared[$file]=$tmp;
            }
            foreach($prepared as $file=>$tmp)if(!rename($tmp,$file))throw new RuntimeException('Không công bố được JSON mới nhất; tiếp tục dùng MySQL.');
            // Old shadow tables are frozen while primary is on. Never read them after rollback.
            if(!cds_meal_pending_mark('primary_rollback_refresh'))throw new RuntimeException('Không khóa được bản sao MySQL cũ; tiếp tục dùng nguồn chính.');
            if(!unlink(cds_meal_primary_marker()))throw new RuntimeException('Không đổi được nguồn; tiếp tục dùng MySQL.');
        }
        if(function_exists('noitru_meal_read_cache_clear'))noitru_meal_read_cache_clear();
        unset($GLOBALS['cds_meal_sql_read_status_cache']);
    }catch(Throwable $e){if($pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    finally{foreach($temps as $tmp)if(is_string($tmp)&&is_file($tmp))unlink($tmp);flock($lock,LOCK_UN);fclose($lock);}
}

/** Same sequential merge/visibility rules as the PHP report callback, with indexed candidates. */
function cds_meal_primary_report_upsert(array $pending,array $deletedIds,array $deletedTargets): bool {
    return cds_meal_primary_atomic(static function()use($pending,$deletedIds,$deletedTargets){
        $pdo=cds_db();
        foreach(['id_key'=>$deletedIds,'visibility_key'=>$deletedTargets]as $column=>$deleted){
            foreach(array_chunk(array_keys($deleted),200)as $keys){
                $hashes=array_map(static fn($key)=>hash('sha256',(string)$key),$keys);
                $stmt=$pdo->prepare("DELETE FROM cds_meal_primary_rows WHERE kind='reports' AND ".$column.' IN ('.implode(',',array_fill(0,count($hashes),'?')).')');$stmt->execute($hashes);
            }
        }
        $existing=[];$keys=[];
        foreach($pending as $row)$keys[]=cds_meal_primary_identity('reports',$row);
        foreach(array_chunk($keys,200)as $batch){
            $stmt=$pdo->prepare("SELECT row_key,source_position,raw_json FROM cds_meal_primary_rows WHERE kind='reports' AND row_key IN (".implode(',',array_fill(0,count($batch),'?')).')');$stmt->execute($batch);
            foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as $record)$existing[$record['row_key']]=$record;
        }
        $position=(int)$pdo->query("SELECT COALESCE(MAX(source_position),-1)+1 FROM cds_meal_primary_rows WHERE kind='reports'")->fetchColumn();
        foreach($pending as $row){
            $saved=$existing[cds_meal_primary_identity('reports',$row)]??null;
            $next=$saved?array_merge(cds_meal_primary_decode($saved['raw_json']),$row,['updated_at'=>noitru_now()]):array_merge(['id'=>noitru_uid('mr'),'created_at'=>noitru_now()],$row);
            cds_meal_primary_put('reports',$saved?(int)$saved['source_position']:$position++,$next);
        }
        return true;
    });
}

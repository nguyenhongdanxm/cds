<?php
/** Bounded report I/O; the public store still exposes the historical array API. */
function noitru_meal_report_meta(): array {
    if(isset($GLOBALS['noitru_meal_meta_cache']))return $GLOBALS['noitru_meal_meta_cache'];
    $handle=null;
    if(empty($GLOBALS['noitru_meal_write_active'])){
        noitru_ensure_dir();$handle=fopen(NOITRU_DIR.'/.meal-write.lock','c');
        if(!$handle||!flock($handle,LOCK_SH)){if(is_resource($handle))fclose($handle);throw new RuntimeException('Không khóa được trạng thái báo cáo.');}
    }
    try{
    unset($GLOBALS['cds_meal_sql_read_status_cache']);
    if(cds_meal_sql_read_effective()){ 
        try{$data=cds_meal_sql_report_meta();}
        catch(Throwable$e){cds_meal_sql_fallback('report_meta',$e);$data=null;}
    }
    if(!isset($data)){
        $fields=cds_json_fields(NOITRU_MEAL_REPORTS);$data=[];
        foreach(['states','settings']as$key){$data[$key]=isset($fields[$key])?json_decode(file_get_contents(NOITRU_MEAL_REPORTS,false,null,$fields[$key][0],$fields[$key][1]),true):[];}
    }
    $data['states']=$data['states']??[];
    $data['settings']=array_merge(['sang_lock_time'=>'20:00','trua_lock_time'=>'09:00','toi_lock_time'=>'15:00'],$data['settings']??[]);
    return $GLOBALS['noitru_meal_meta_cache']=$data;
    }finally{if(is_resource($handle)){flock($handle,LOCK_UN);fclose($handle);}}
}

function noitru_meal_report_visible(array$row,array$deletedIds,array$deletedTargets):bool{
    return !isset($deletedIds[(string)($row['id']??'')])&&!isset($deletedTargets[noitru_meal_target_key($row['date']??'',$row['class_name']??'',$row['meal']??'')]);
}

function noitru_meal_report_rows($date=null):Generator{
    $handle=null;
    if(empty($GLOBALS['noitru_meal_write_active'])){
        noitru_ensure_dir();$handle=fopen(NOITRU_DIR.'/.meal-write.lock','c');
        if(!$handle||!flock($handle,LOCK_SH)){if(is_resource($handle))fclose($handle);throw new RuntimeException('Không khóa được nguồn báo cáo.');}
    }
    try{
        unset($GLOBALS['cds_meal_sql_read_status_cache']);
        $deletedIds=array_fill_keys(array_map('strval',(array)load_json(NOITRU_DIR.'/meal_history_deleted.json',[])),true);
        $deletedTargets=noitru_meal_deleted_targets();
        $rows=cds_meal_sql_read_effective()?cds_meal_sql_report_rows($date):cds_json_field_rows(NOITRU_MEAL_REPORTS,'reports');
        foreach($rows as$row){
            if($date!==null&&($row['date']??'')!==$date)continue;
            if(noitru_meal_report_visible($row,$deletedIds,$deletedTargets))yield$row;
        }
    }finally{if(is_resource($handle)){flock($handle,LOCK_UN);fclose($handle);}}
}

function noitru_meal_reports_flush_sql():void{
    $data=noitru_meal_report_meta();$data['reports']=cds_json_field_rows(NOITRU_MEAL_REPORTS,'reports');
    if(!cds_meal_shadow_reports_data($data))cds_meal_shadow_notify_failure();
}

/** Called under the shared meal write lock. Other object fields are retained. */
function noitru_meal_reports_mutate(?callable$reports=null,?array$meta=null):bool{
    $meta=$meta??noitru_meal_report_meta();
    if($reports!==null&&!cds_json_field_save(NOITRU_MEAL_REPORTS,'reports',$reports))return false;
    foreach(['states','settings']as$key){
        $fields=cds_json_fields(NOITRU_MEAL_REPORTS);
        $current=isset($fields[$key])?json_decode(file_get_contents(NOITRU_MEAL_REPORTS,false,null,$fields[$key][0],$fields[$key][1]),true):null;
        $next=$meta[$key]??[];
        if($current!==$next&&!cds_json_field_save(NOITRU_MEAL_REPORTS,$key,static fn($old)=>$next,true))return false;
    }
    noitru_meal_read_cache_clear();noitru_meal_reports_flush_sql();return true;
}

function noitru_meal_report_upsert_many(array$updates):bool{
    return noitru_meal_write(static function()use($updates){
        if(!$updates)return true;
        $deletedIds=array_fill_keys(array_map('strval',(array)load_json(NOITRU_DIR.'/meal_history_deleted.json',[])),true);
        $deletedBefore=noitru_meal_deleted_targets();$deletedAfter=$deletedBefore;$pending=[];
        foreach($updates as$row){
            $row=noitru_meal_report_snapshot($row);
            $key=serialize([$row['date']??'',$row['class_name']??'',$row['meal']??'']);
            $pending[$key]=isset($pending[$key])?array_merge($pending[$key],$row,['updated_at'=>noitru_now()]):$row;
            unset($deletedAfter[noitru_meal_target_key($row['date']??'',$row['class_name']??'',$row['meal']??'')]);
        }
        // Publish source first. If the later tombstone write fails, inflight recovery keeps SQL blocked.
        $ok=noitru_meal_reports_mutate(static function($rows)use($pending,$deletedIds,$deletedBefore){
            $found=[];
            foreach($rows as$row){
                if(!noitru_meal_report_visible($row,$deletedIds,$deletedBefore))continue;
                $key=serialize([$row['date']??'',$row['class_name']??'',$row['meal']??'']);
                if(isset($pending[$key])&&!isset($found[$key])){$row=array_merge($row,$pending[$key],['updated_at'=>noitru_now()]);$found[$key]=true;}
                yield$row;
            }
            foreach($pending as$key=>$row)if(!isset($found[$key]))yield array_merge(['id'=>noitru_uid('mr'),'created_at'=>noitru_now()],$row);
        });
        if(!$ok)return false;
        if($deletedAfter!==$deletedBefore&&!noitru_meal_deleted_targets_save($deletedAfter))return false;
        return true;
    },false);
}

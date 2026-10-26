<?php
function lb_bulk_review_row(array $week,string $class,string $content,string $year,?array $old=null): array {
    $now=date('c');$by=lb_teacher_name();
    return array_merge($old??[],['id'=>(string)($old['id']??lb_id('review')),'key'=>$year.'|'.$week['key'].'|'.lb_norm($class).'|leadership','school_year_key'=>$year,'week_key'=>(string)$week['key'],'week_label'=>(string)$week['label'],'class'=>$class,'type'=>'leadership','content'=>$content,'created_at'=>(string)($old['created_at']??$now),'created_by'=>(string)($old['created_by']??$by),'updated_at'=>$now,'updated_by'=>$by]);
}
function lb_bulk_review_merge(array $rows,array $saved): array {
    $map=[];foreach($saved as $row)$map[cds_lb_weekly_review_enrich($row)['review_key']]=$row;
    $seen=[];foreach($rows as &$row){if(!is_array($row))continue;$key=cds_lb_weekly_review_enrich($row)['review_key'];if(isset($map[$key])){$row=$map[$key];$seen[$key]=true;}}unset($row);
    return array_values(array_merge($rows,array_values(array_diff_key($map,$seen))));
}
function lb_save_weekly_reviews_bulk(array $week,array $in): array {
    if(!lb_is_management())return ['ok'=>false,'message'=>'Chỉ Ban giám hiệu hoặc quản trị được nhận xét BGH hàng loạt.'];
    $classes=$in['classes']??[];$content=$in['content']??'';
    if(!is_array($classes)||!$classes||count($classes)>200||!is_string($content)||trim($content)===''||mb_strlen($content,'UTF-8')>10000)return ['ok'=>false,'message'=>'Chọn từ 1 đến 200 lớp và nhập nhận xét tối đa 10.000 ký tự.'];
    $overwrite=($in['overwrite']??'')==='1';if($overwrite&&($in['confirmed']??'')!=='1')return ['ok'=>false,'message'=>'Cần xác nhận trước khi ghi đè nhận xét BGH đã có.'];
    $allowed=[];foreach(lb_slots($week) as $row){$class=trim((string)($row['class']??''));if($class!==''&&lb_can_review_class($class,'leadership'))$allowed[lb_norm($class)]=$class;}
    $selected=[];foreach($classes as $class){if(!is_string($class)||!isset($allowed[lb_norm($class)]))return ['ok'=>false,'message'=>'Có lớp không thuộc tuần hoặc phạm vi được nhận xét. Chưa lưu lớp nào.'];$selected[lb_norm($class)]=$allowed[lb_norm($class)];}
    $year=cds_lb_weekly_review_school_year([]);$saved=[];$skipped=[];$content=trim($content);
    $sqlReady=cds_lb_weekly_review_table_ready();
    if($sqlReady){
        $legacy=[];foreach(cds_json_load(LB_WEEKLY_REVIEWS_FILE,[]) as $row){if(!is_array($row))continue;$row=cds_lb_weekly_review_enrich($row);if($row['school_year_key']===$year&&$row['week_key']===(string)$week['key']&&$row['type']==='leadership'&&isset($selected[lb_norm($row['class'])]))$legacy[]=$row;}
        if($legacy&&!cds_lb_weekly_reviews_import_json($legacy))return ['ok'=>false,'message'=>'Chưa đồng bộ được nhận xét đã có. Chưa lưu nhận xét mới.'];
        try{$pdo=cds_db();$pdo->beginTransaction();$lock=$pdo->prepare('SELECT raw_json FROM cds_lesson_book_weekly_reviews WHERE review_key=? FOR UPDATE');
            // Stable lock order reduces deadlocks when two BGH users select overlapping classes.
            ksort($selected,SORT_STRING);
            foreach($selected as $class){$key=cds_lb_weekly_review_enrich(['school_year_key'=>$year,'week_key'=>$week['key'],'class'=>$class,'type'=>'leadership'])['review_key'];$lock->execute([$key]);$raw=$lock->fetchColumn();$old=$raw!==false?json_decode((string)$raw,true):null;
                if($raw!==false&&!is_array($old))throw new RuntimeException('Invalid existing review payload');
                if(!$overwrite&&trim((string)($old['content']??''))!==''){$skipped[]=$class;continue;}
                $saved[]=cds_lb_weekly_review_write($pdo,lb_bulk_review_row($week,$class,$content,$year,$old));
            }$pdo->commit();
        }catch(Throwable $e){if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();error_log('[CDS bulk weekly review] '.$e->getMessage());return ['ok'=>false,'message'=>'Không lưu được nhận xét vào MySQL. Giao dịch đã hoàn tác; vui lòng thử lại.'];}
        $jsonOk=!$saved||cds_json_update(LB_WEEKLY_REVIEWS_FILE,fn($rows)=>lb_bulk_review_merge(is_array($rows)?$rows:[],$saved),[]);
    }else{
        $jsonOk=cds_json_update(LB_WEEKLY_REVIEWS_FILE,function($rows)use($selected,$overwrite,$week,$content,$year,&$saved,&$skipped){
            if(!is_array($rows))return false;$existing=[];foreach($rows as $row)if(is_array($row)){$key=cds_lb_weekly_review_enrich($row)['review_key'];if(!isset($existing[$key])||trim((string)($row['content']??''))!=='')$existing[$key]=$row;}
            foreach($selected as $class){$key=cds_lb_weekly_review_enrich(['school_year_key'=>$year,'week_key'=>$week['key'],'class'=>$class,'type'=>'leadership'])['review_key'];$old=$existing[$key]??null;
                if(!$overwrite&&trim((string)($old['content']??''))!==''){$skipped[]=$class;continue;}
                $saved[]=lb_bulk_review_row($week,$class,$content,$year,$old);
            }return lb_bulk_review_merge($rows,$saved);
        },[]);
        if(!$jsonOk)return ['ok'=>false,'message'=>'Không ghi được nhận xét tuần. Chưa lưu lớp nào.'];
    }
    if($jsonOk)lb_rows_bust(LB_WEEKLY_REVIEWS_FILE);
    if($saved)lb_audit('save_weekly_review_bulk',['week_key'=>$week['key'],'review_type'=>'leadership','classes'=>array_column($saved,'class'),'overwrite'=>$overwrite,'storage'=>$sqlReady?'mysql+json':'json']);
    return ['ok'=>true,'type'=>$jsonOk?'success':'warning','saved'=>count($saved),'skipped'=>count($skipped),'message'=>'Đã lưu nhận xét BGH cho '.count($saved).' lớp của '.$week['label'].'.'.($skipped?' Bỏ qua '.count($skipped).' lớp đã có nhận xét.':'').(!$jsonOk?' MySQL đã lưu; bản JSON dự phòng chưa cập nhật.':'')];
}

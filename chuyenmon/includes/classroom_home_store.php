<?php
/** Dữ liệu riêng của tiện ích lớp chủ nhiệm; không ghi vào CSDL nội trú/CSDL học sinh. */
function cmhome_install(PDO $db): void {
    // Bảng cuối là dấu hiệu cài đặt hoàn tất; trang thường chỉ cần một truy vấn kiểm tra.
    if($db->query("SHOW TABLES LIKE 'cmhome_refunds'")->fetchColumn()) {
        cmhome_install_extensions($db);
        return;
    }
    $schema=[
      'cmhome_roles'=>'(class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,student_id VARCHAR(100) NOT NULL,role_name VARCHAR(80) NOT NULL,updated_by VARCHAR(255) NOT NULL,PRIMARY KEY(class_id,school_year,student_id,role_name))',
      'cmhome_seats'=>'(class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,seat_no SMALLINT UNSIGNED NOT NULL,student_id VARCHAR(100) NOT NULL,PRIMARY KEY(class_id,school_year,seat_no),UNIQUE KEY uq_seat_student(class_id,school_year,student_id))',
      'cmhome_notes'=>'(class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,student_id VARCHAR(100) NOT NULL,talent TEXT NULL,circumstance TEXT NULL,support_note TEXT NULL,updated_by VARCHAR(255) NOT NULL,updated_at DATETIME NOT NULL,PRIMARY KEY(class_id,school_year,student_id))',
      'cmhome_points'=>'(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,student_id VARCHAR(100) NOT NULL,event_date DATE NOT NULL,points SMALLINT NOT NULL,criterion VARCHAR(80) NOT NULL,note VARCHAR(500) NOT NULL DEFAULT \'\',created_by VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_class_date(class_id,school_year,event_date),KEY idx_student_date(student_id,event_date))',
      'cmhome_reviews'=>'(class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,student_id VARCHAR(100) NOT NULL,period_type VARCHAR(10) NOT NULL,period_start DATE NOT NULL,rank_label VARCHAR(30) NOT NULL,comment VARCHAR(500) NOT NULL DEFAULT \'\',updated_by VARCHAR(255) NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(class_id,school_year,student_id,period_type,period_start))',
      'cmhome_plans'=>'(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,title VARCHAR(255) NOT NULL,due_date DATE NOT NULL,owner VARCHAR(255) NOT NULL DEFAULT \'\',note TEXT NULL,status VARCHAR(20) NOT NULL DEFAULT \'open\',created_by VARCHAR(255) NOT NULL,PRIMARY KEY(id),KEY idx_class_due(class_id,school_year,due_date))',
      'cmhome_ledger'=>'(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,entry_date DATE NOT NULL,kind VARCHAR(10) NOT NULL,amount DECIMAL(14,0) NOT NULL,description VARCHAR(500) NOT NULL,student_id VARCHAR(100) NOT NULL DEFAULT \'\',created_by VARCHAR(255) NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_class_date(class_id,school_year,entry_date))',
      'cmhome_settings'=>'(class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,rate_sang DECIMAL(12,0) NOT NULL DEFAULT 0,rate_trua DECIMAL(12,0) NOT NULL DEFAULT 0,rate_toi DECIMAL(12,0) NOT NULL DEFAULT 0,good_at SMALLINT NOT NULL DEFAULT 8,fair_at SMALLINT NOT NULL DEFAULT 3,pass_at SMALLINT NOT NULL DEFAULT 0,PRIMARY KEY(class_id,school_year))',
      'cmhome_refunds'=>'(id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,month_key CHAR(7) NOT NULL,student_id VARCHAR(100) NOT NULL,meals_sang SMALLINT UNSIGNED NOT NULL DEFAULT 0,meals_trua SMALLINT UNSIGNED NOT NULL DEFAULT 0,meals_toi SMALLINT UNSIGNED NOT NULL DEFAULT 0,amount DECIMAL(14,0) NOT NULL DEFAULT 0,status VARCHAR(20) NOT NULL DEFAULT \'pending\',note VARCHAR(500) NOT NULL DEFAULT \'\',created_by VARCHAR(255) NOT NULL,decided_by VARCHAR(255) NOT NULL DEFAULT \'\',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_student_month(class_id,school_year,month_key,student_id))'
    ];
    foreach($schema as $table=>$columns) $db->exec('CREATE TABLE IF NOT EXISTS '.$table.' '.$columns.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    cmhome_install_extensions($db);
}
function cmhome_install_extensions(PDO $db): void {
    if($db->query("SHOW TABLES LIKE 'cmhome_class_reviews'")->fetchColumn() && $db->query("SHOW TABLES LIKE 'cmhome_layout'")->fetchColumn() && $db->query("SHOW TABLES LIKE 'cmhome_family_profiles'")->fetchColumn()) return;
    $db->exec('CREATE TABLE IF NOT EXISTS cmhome_layout (class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,seat_rows TINYINT UNSIGNED NOT NULL DEFAULT 6,seat_aisles TINYINT UNSIGNED NOT NULL DEFAULT 2,seats_per_desk TINYINT UNSIGNED NOT NULL DEFAULT 2,PRIMARY KEY(class_id,school_year)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS cmhome_class_reviews (class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,week_start DATE NOT NULL,rank_label VARCHAR(30) NOT NULL,comment VARCHAR(1000) NOT NULL DEFAULT \'\',updated_by VARCHAR(255) NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(class_id,school_year,week_start)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    $db->exec('CREATE TABLE IF NOT EXISTS cmhome_family_profiles (class_id VARCHAR(100) NOT NULL,school_year VARCHAR(20) NOT NULL,student_id VARCHAR(100) NOT NULL,commune VARCHAR(150) NOT NULL DEFAULT \'\',father_job VARCHAR(150) NOT NULL DEFAULT \'\',mother_job VARCHAR(150) NOT NULL DEFAULT \'\',updated_by VARCHAR(255) NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(class_id,school_year,student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
}
function cmhome_commune(string $address): string {
    if(preg_match('/(?:^|[,;])\s*((?:xã|phường|thị trấn)\s+[^,;]+)/ui',$address,$match))return trim($match[1]);
    return '';
}
function cmhome_rows(PDO $db,string $sql,array $params=[]): array { $q=$db->prepare($sql);$q->execute($params);return $q->fetchAll(PDO::FETCH_ASSOC); }
function cmhome_one(PDO $db,string $sql,array $params=[]): array { $rows=cmhome_rows($db,$sql,$params);return $rows[0]??[]; }
function cmhome_exec(PDO $db,string $sql,array $params=[]): void { $q=$db->prepare($sql);$q->execute($params); }
function cmhome_date($value): bool { $date=DateTimeImmutable::createFromFormat('!Y-m-d',(string)$value);return $date && $date->format('Y-m-d')===$value; }
function cmhome_dob($value): string {
    $value=trim((string)$value);
    if(cmhome_date($value))return $value;
    // Cùng định dạng ngày mà bảng CSDL chấp nhận khi hiển thị hồ sơ.
    if(preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[ T].*)?$/',$value,$m)){
        $y=(int)$m[1];$mo=(int)$m[2];$d=(int)$m[3];
        return checkdate($mo,$d,$y)?sprintf('%04d-%02d-%02d',$y,$mo,$d):'';
    }
    if(preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{2}|\d{4})$/',$value,$m)){
        $d=(int)$m[1];$mo=(int)$m[2];$y=(int)$m[3];
        if(strlen($m[3])===2)$y=$y<=(int)date('y')?2000+$y:1900+$y;
        return checkdate($mo,$d,$y)?sprintf('%04d-%02d-%02d',$y,$mo,$d):'';
    }
    if(preg_match('/^\d+(?:\.\d+)?$/',$value)){
        $serial=(int)floor((float)$value);
        if($serial>=1&&$serial<=100000){$date=DateTimeImmutable::createFromFormat('!Y-m-d','1899-12-30');return $date?$date->modify('+'.$serial.' days')->format('Y-m-d'):'';}
    }
    return '';
}
function cmhome_month($value): bool { return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/',(string)$value)===1; }
function cmhome_money($value): int { if(!preg_match('/^\d{1,12}$/',(string)$value))throw new RuntimeException('Số tiền phải là số nguyên không âm.');return (int)$value; }
function cmhome_meal_summary(string $className,array $studentIds,string $month): array {
    // Chỉ nạp lớp đọc báo ăn: noitru_store kéo theo auth.php và xung đột với
    // functions.php của Chuyên môn khi trang chủ nhiệm mở tab thu chi.
    require_once dirname(__DIR__,2).'/includes/database_meal_read.php';
    $readJson=static function(string $file,array $fallback=[]): array {
        $data=is_file($file)?json_decode((string)file_get_contents($file),true):null;
        return is_array($data)?$data:$fallback;
    };
    $noitruDir=dirname(__DIR__,2).'/data/noitru';
    $from=$month.'-01';$to=date('Y-m-t',strtotime($from));$members=array_fill_keys($studentIds,true);
    $result=[];foreach($members as $id=>$_)$result[$id]=['sang'=>0,'trua'=>0,'toi'=>0];
    $reports=[];$stateRows=[];$mealSettings=[];$sqlRead=!is_file($noitruDir.'/meal_mysql_shadow_pending.json')&&cds_meal_sql_read_effective();
    if($sqlRead){
        try{
            $daily=cds_meal_sql_daily_for_range($from,$to);
            $reportRows=cds_meal_sql_decode_rows('SELECT raw_json FROM cds_meal_reports WHERE meal_date BETWEEN ? AND ? AND class_name=?',[$from,$to,$className]);
            $stateRows=cds_meal_sql_decode_rows('SELECT raw_json FROM cds_meal_states WHERE meal_date BETWEEN ? AND ?',[$from,$to]);
            $rawSettings=cmhome_one(cds_db(),'SELECT raw_json FROM cds_meal_settings WHERE id=1');
            $mealSettings=json_decode((string)($rawSettings['raw_json']??''),true)?:[];
        }catch(Throwable $ex){$sqlRead=false;}
    }
    if(!$sqlRead){$daily=$readJson($noitruDir.'/meals_daily.json');$stored=$readJson($noitruDir.'/meal_reports.json');$reportRows=$stored['reports']??[];$stateRows=$stored['states']??[];$mealSettings=$stored['settings']??[];}
    $deleted=$readJson($noitruDir.'/meal_history_deleted.json');
    if($deleted){$deleted=array_fill_keys(array_map('strval',$deleted),true);$reportRows=array_values(array_filter($reportRows,static fn($r)=>!isset($deleted[(string)($r['id']??'')])));}
    foreach($reportRows as $r){$date=(string)($r['date']??'');$meal=(string)($r['meal']??'');if($date>=$from&&$date<=$to&&($r['class_name']??'')===$className&&in_array($meal,['sang','trua','toi'],true))$reports[$date][$meal]=true;}
    $states=[];foreach($stateRows as $r){$date=(string)($r['date']??'');$meal=(string)($r['meal']??'');if($date>=$from&&$date<=$to)$states[$date][$meal]=(string)($r['status']??'');}
    $timezone=new DateTimeZone('Asia/Ho_Chi_Minh');$now=new DateTimeImmutable('now',$timezone);
    $isLocked=static function($date,$meal)use($states,$mealSettings,$timezone,$now):bool{
        $status=$states[$date][$meal]??'';if($status==='locked')return true;if(in_array($status,['off','open_override'],true))return false;
        $lock=(string)($mealSettings[$meal.'_lock_time']??['sang'=>'20:00','trua'=>'09:00','toi'=>'15:00'][$meal]);
        if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$lock))return false;
        $day=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$timezone);if(!$day)return false;
        if($meal==='sang')$day=$day->modify('-1 day');
        [$hour,$minute]=array_map('intval',explode(':',$lock));return $now>=$day->setTime($hour,$minute);
    };
    $locked=[];
    foreach($daily as $r){$date=(string)($r['date']??'');$id=(string)($r['student_id']??'');if($date<$from||$date>$to||!isset($members[$id]))continue;
        foreach(['sang','trua','toi'] as $meal){if(($r[$meal]??'')!=='yes'||!isset($reports[$date][$meal]))continue;$key=$date.'|'.$meal;if(!isset($locked[$key]))$locked[$key]=$isLocked($date,$meal);if($locked[$key])$result[$id][$meal]++;}
    }
    $rice=$readJson($noitruDir.'/rice.json');$grams=array_merge(['sang_grams'=>0,'trua_grams'=>180,'toi_grams'=>180],(array)($rice['settings']??[]));
    foreach($result as &$r)$r['rice_kg']=round(($r['sang']*$grams['sang_grams']+$r['trua']*$grams['trua_grams']+$r['toi']*$grams['toi_grams'])/1000,3);unset($r);
    return [$result,$grams];
}

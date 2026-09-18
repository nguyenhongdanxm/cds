<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csdl_store.php';

function olympia_db(): PDO { return cds_db(); }
function olympia_admin(): bool { $u=current_user()??[]; return ($u['role']??'')==='admin'; }
function olympia_uid(string $prefix): string { return $prefix.'_'.bin2hex(random_bytes(8)); }
function olympia_setting(string $key,string $default=''): string { $s=olympia_db()->prepare('SELECT setting_value FROM cds_olympia_settings WHERE setting_key=?');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v; }
function olympia_settings(): array { $out=[];foreach(olympia_db()->query('SELECT setting_key,setting_value FROM cds_olympia_settings') as $r)$out[(string)$r['setting_key']]=(string)$r['setting_value'];return $out; }
function olympia_save_setting(string $key,string $value): void { $s=olympia_db()->prepare('INSERT INTO cds_olympia_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$s->execute([$key,$value]); }
function olympia_asset_url(string $path,string $fallback=''): string { return $path!==''?BASE_URL.ltrim($path,'/'):$fallback; }
function olympia_rankings(string $weekId,string $classId): array { $s=olympia_db()->prepare('SELECT student_id,student_name,class_name,SUM(points) points,COUNT(*) correct FROM cds_olympia_scores WHERE week_id=? AND class_id=? GROUP BY student_id,student_name,class_name ORDER BY points DESC,student_name ASC');$s->execute([$weekId,$classId]);return array_map(fn($r)=>['id'=>(string)$r['student_id'],'name'=>(string)$r['student_name'],'class_name'=>(string)$r['class_name'],'points'=>(int)$r['points'],'correct'=>(int)$r['correct']],$s->fetchAll()); }

function olympia_upload_asset(string $field,string $kind): string {
    $upload=$_FILES[$field]??null;if(!$upload||($upload['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)return '';
    if(($upload['error']??UPLOAD_ERR_OK)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($upload['tmp_name']??'')))throw new RuntimeException('Không nhận được tệp '.($kind==='image'?'ảnh':'âm thanh').' hợp lệ.');
    $tmp=(string)$upload['tmp_name'];$size=(int)($upload['size']??0);$max=$kind==='image'?8*1024*1024:25*1024*1024;if($size<1||$size>$max)throw new RuntimeException('Tệp vượt quá dung lượng cho phép (ảnh 8 MB, nhạc 25 MB).');
    $ext=strtolower(pathinfo((string)($upload['name']??''),PATHINFO_EXTENSION));$allowed=$kind==='image'?['png'=>['image/png'],'jpg'=>['image/jpeg'],'jpeg'=>['image/jpeg'],'webp'=>['image/webp']]:['mp3'=>['audio/mpeg','audio/mp3','audio/x-mpeg'],'ogg'=>['audio/ogg','application/ogg'],'wav'=>['audio/wav','audio/x-wav','audio/wave']];
    if(!isset($allowed[$ext]))throw new RuntimeException($kind==='image'?'Chỉ nhận PNG, JPG hoặc WEBP.':'Chỉ nhận MP3, OGG hoặc WAV.');
    $mime=function_exists('finfo_open')?(new finfo(FILEINFO_MIME_TYPE))->file($tmp):(function_exists('mime_content_type')?mime_content_type($tmp):'');if(!in_array((string)$mime,$allowed[$ext],true))throw new RuntimeException('Nội dung tệp không khớp với phần mở rộng.');
    $dir=DATA_PATH.'/game_assets/olympia';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Không tạo được thư mục lưu tài nguyên Olympia.');
    $name=bin2hex(random_bytes(16)).'.'.$ext;if(!move_uploaded_file($tmp,$dir.'/'.$name))throw new RuntimeException('Không lưu được tệp lên máy chủ.');@chmod($dir.'/'.$name,0644);return 'data/game_assets/olympia/'.$name;
}

function olympia_ensure_schema(): void {
    $db=olympia_db();
    $sql=[
        "CREATE TABLE IF NOT EXISTS cds_olympia_weeks (id VARCHAR(40) NOT NULL,title VARCHAR(180) NOT NULL,week_label VARCHAR(100) NOT NULL DEFAULT '',open_at DATETIME NULL,close_at DATETIME NULL,access_mode VARCHAR(12) NOT NULL DEFAULT 'auto',logo_path VARCHAR(255) NOT NULL DEFAULT '',created_by VARCHAR(100) NOT NULL DEFAULT '',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_olympia_week_time(open_at,close_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_week_classes (week_id VARCHAR(40) NOT NULL,class_id VARCHAR(100) NOT NULL,class_name VARCHAR(100) NOT NULL,PRIMARY KEY(week_id,class_id),KEY idx_olympia_wc_class(class_id),CONSTRAINT fk_olympia_wc_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_questions (id VARCHAR(40) NOT NULL,week_id VARCHAR(40) NOT NULL,stage_name VARCHAR(60) NOT NULL DEFAULT 'Khởi động',question_text TEXT NOT NULL,answer_text TEXT NOT NULL,points INT NOT NULL DEFAULT 10,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_olympia_q_week(week_id,sort_order),CONSTRAINT fk_olympia_q_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_scores (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,week_id VARCHAR(40) NOT NULL,question_id VARCHAR(40) NOT NULL,class_id VARCHAR(100) NOT NULL,class_name VARCHAR(100) NOT NULL,student_id VARCHAR(100) NOT NULL,student_name VARCHAR(255) NOT NULL,points INT NOT NULL DEFAULT 0,marked_by VARCHAR(255) NOT NULL DEFAULT '',marked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_olympia_score(question_id,class_id,student_id),KEY idx_olympia_score_week(week_id,class_id),CONSTRAINT fk_olympia_score_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE,CONSTRAINT fk_olympia_score_question FOREIGN KEY(question_id) REFERENCES cds_olympia_questions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_settings (setting_key VARCHAR(80) NOT NULL,setting_value TEXT NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ,"CREATE TABLE IF NOT EXISTS cds_olympia_live_sessions (week_id VARCHAR(40) NOT NULL,class_id VARCHAR(100) NOT NULL,state_json LONGTEXT NOT NULL,controlled_by VARCHAR(255) NOT NULL DEFAULT '',updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(week_id,class_id),KEY idx_olympia_live_updated(updated_at),CONSTRAINT fk_olympia_live_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ,"CREATE TABLE IF NOT EXISTS cds_olympia_play_controls (week_id VARCHAR(40) NOT NULL,class_id VARCHAR(100) NOT NULL,is_locked TINYINT(1) NOT NULL DEFAULT 0,max_attempts INT UNSIGNED NOT NULL DEFAULT 1,updated_by VARCHAR(255) NOT NULL DEFAULT '',updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(week_id,class_id),CONSTRAINT fk_olympia_control_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ,"CREATE TABLE IF NOT EXISTS cds_olympia_play_runs (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,week_id VARCHAR(40) NOT NULL,class_id VARCHAR(100) NOT NULL,class_name VARCHAR(100) NOT NULL,attempt_no INT UNSIGNED NOT NULL DEFAULT 1,status VARCHAR(20) NOT NULL DEFAULT 'running',started_by VARCHAR(255) NOT NULL DEFAULT '',started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,ended_at DATETIME NULL,PRIMARY KEY(id),UNIQUE KEY uq_olympia_run_attempt(week_id,class_id,attempt_no),KEY idx_olympia_run_lookup(week_id,class_id,status),CONSTRAINT fk_olympia_run_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];
    foreach($sql as $statement)$db->exec($statement);
    try{$db->exec("ALTER TABLE cds_olympia_play_controls ADD COLUMN max_attempts INT UNSIGNED NOT NULL DEFAULT 1 AFTER is_locked");}catch(Throwable $e){}
}

function olympia_classes(): array { $rows=array_values(array_filter(csdl_classes_all(),fn($r)=>!isset($r['active'])||!empty($r['active']))); csdl_sort_classes($rows); return $rows; }
function olympia_class_map(): array { $out=[];foreach(olympia_classes() as $c)$out[(string)($c['id']??'')]=$c;return $out; }
function olympia_allowed_class_ids(): ?array {
    if(olympia_admin())return null;
    $u=current_user()??[];$names=array_values(array_unique(array_filter(array_map('strval',array_merge((array)($u['classes']??[]),(array)($u['homeroom_classes']??[]))))));
    if(!in_array('gvcn',(array)($u['groups']??[]),true))return [];
    $ids=[];foreach(olympia_classes() as $c)if(in_array((string)($c['name']??''),$names,true))$ids[]=(string)$c['id'];
    return array_values(array_unique($ids));
}
function olympia_weeks(): array { return olympia_db()->query("SELECT w.*,GROUP_CONCAT(wc.class_name ORDER BY wc.class_name SEPARATOR ', ') class_names FROM cds_olympia_weeks w LEFT JOIN cds_olympia_week_classes wc ON wc.week_id=w.id GROUP BY w.id ORDER BY COALESCE(w.open_at,'9999-12-31'),w.created_at DESC")->fetchAll(); }
function olympia_week(string $id): ?array { $s=olympia_db()->prepare('SELECT * FROM cds_olympia_weeks WHERE id=?');$s->execute([$id]);$r=$s->fetch();return $r?:null; }
function olympia_week_class_ids(string $id): array { $s=olympia_db()->prepare('SELECT class_id FROM cds_olympia_week_classes WHERE week_id=?');$s->execute([$id]);return array_map('strval',$s->fetchAll(PDO::FETCH_COLUMN)); }
function olympia_questions(string $week): array { $s=olympia_db()->prepare('SELECT * FROM cds_olympia_questions WHERE week_id=? ORDER BY sort_order,id');$s->execute([$week]);return $s->fetchAll(); }
function olympia_students(string $class): array { $rows=array_values(array_filter(csdl_students_all(),fn($r)=>(string)($r['class_id']??'')===$class&&(!isset($r['active'])||!empty($r['active']))));csdl_sort_students($rows);return $rows; }
function olympia_is_open(array $w): bool { if(($w['access_mode']??'auto')==='open')return true;if(($w['access_mode']??'auto')==='locked')return false;$now=date('Y-m-d H:i:s');return (empty($w['open_at'])||$w['open_at']<=$now)&&(empty($w['close_at'])||$w['close_at']>=$now); }
function olympia_can_class(string $class): bool { $ids=olympia_allowed_class_ids();return $ids===null||in_array($class,$ids,true); }

function olympia_play_locked(string $weekId,string $classId): bool {
    $s=olympia_db()->prepare('SELECT is_locked FROM cds_olympia_play_controls WHERE week_id=? AND class_id=?');$s->execute([$weekId,$classId]);return (int)$s->fetchColumn()===1;
}
function olympia_play_set_locked(string $weekId,string $classId,bool $locked,string $actor): void {
    $s=olympia_db()->prepare('INSERT INTO cds_olympia_play_controls(week_id,class_id,is_locked,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE is_locked=VALUES(is_locked),updated_by=VALUES(updated_by)');$s->execute([$weekId,$classId,$locked?1:0,$actor]);
    if($locked){$x=olympia_db()->prepare("UPDATE cds_olympia_play_runs SET status='locked',ended_at=COALESCE(ended_at,NOW()) WHERE week_id=? AND class_id=? AND status='running'");$x->execute([$weekId,$classId]);}
}
function olympia_play_max_attempts(string $weekId,string $classId): int {
    $s=olympia_db()->prepare('SELECT max_attempts FROM cds_olympia_play_controls WHERE week_id=? AND class_id=?');$s->execute([$weekId,$classId]);$value=$s->fetchColumn();return $value===false?1:max(1,(int)$value);
}
function olympia_play_set_max_attempts(string $weekId,string $classId,int $maxAttempts,string $actor): void {
    $maxAttempts=max(1,min(99,$maxAttempts));$s=olympia_db()->prepare('INSERT INTO cds_olympia_play_controls(week_id,class_id,max_attempts,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE max_attempts=VALUES(max_attempts),updated_by=VALUES(updated_by)');$s->execute([$weekId,$classId,$maxAttempts,$actor]);
}
function olympia_play_start(string $weekId,string $classId,string $actor): array {
    if(olympia_play_locked($weekId,$classId))throw new RuntimeException('Quản trị đang khóa lượt chơi của lớp này.');
    $db=olympia_db();$db->beginTransaction();try{$s=$db->prepare("SELECT * FROM cds_olympia_play_runs WHERE week_id=? AND class_id=? AND status='running' ORDER BY id DESC LIMIT 1 FOR UPDATE");$s->execute([$weekId,$classId]);$row=$s->fetch();if(!$row){$n=$db->prepare('SELECT COALESCE(MAX(attempt_no),0)+1 FROM cds_olympia_play_runs WHERE week_id=? AND class_id=?');$n->execute([$weekId,$classId]);$attempt=(int)$n->fetchColumn();$maxAttempts=olympia_play_max_attempts($weekId,$classId);if($attempt>$maxAttempts)throw new RuntimeException('Lớp đã dùng đủ '.$maxAttempts.' lượt chơi trong tuần này. Vui lòng liên hệ quản trị để reset hoặc tăng số lượt.');$class=olympia_class_map()[$classId]??[];$i=$db->prepare("INSERT INTO cds_olympia_play_runs(week_id,class_id,class_name,attempt_no,status,started_by) VALUES(?,?,?,?,'running',?)");$i->execute([$weekId,$classId,(string)($class['name']??''),$attempt,$actor]);$id=(int)$db->lastInsertId();$s=$db->prepare('SELECT * FROM cds_olympia_play_runs WHERE id=?');$s->execute([$id]);$row=$s->fetch();}$db->commit();return $row?:[];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function olympia_play_finish(string $weekId,string $classId): void {$s=olympia_db()->prepare("UPDATE cds_olympia_play_runs SET status='completed',ended_at=NOW() WHERE week_id=? AND class_id=? AND status='running'");$s->execute([$weekId,$classId]);}
function olympia_play_stats(string $weekId): array {
    $s=olympia_db()->prepare("SELECT wc.class_id,wc.class_name,COALESCE(pc.is_locked,0) is_locked,COALESCE(pc.max_attempts,1) max_attempts,COUNT(r.id) play_count,MIN(r.started_at) first_started_at,MAX(r.started_at) last_started_at,MAX(r.ended_at) last_ended_at,SUM(r.status='running') running_count FROM cds_olympia_week_classes wc LEFT JOIN cds_olympia_play_controls pc ON pc.week_id=wc.week_id AND pc.class_id=wc.class_id LEFT JOIN cds_olympia_play_runs r ON r.week_id=wc.week_id AND r.class_id=wc.class_id WHERE wc.week_id=? GROUP BY wc.class_id,wc.class_name,pc.is_locked,pc.max_attempts ORDER BY wc.class_name");$s->execute([$weekId]);return $s->fetchAll();
}
function olympia_play_runs(string $weekId): array {$s=olympia_db()->prepare('SELECT * FROM cds_olympia_play_runs WHERE week_id=? ORDER BY started_at DESC,id DESC');$s->execute([$weekId]);return $s->fetchAll();}

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
    ];
    foreach($sql as $statement)$db->exec($statement);
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

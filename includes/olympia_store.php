<?php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/csdl_store.php';

function olympia_db(): PDO { return cds_db(); }
function olympia_admin(): bool { $u=current_user()??[]; return ($u['role']??'')==='admin'; }
function olympia_uid(string $prefix): string { return $prefix.'_'.bin2hex(random_bytes(8)); }

function olympia_ensure_schema(): void {
    $db=olympia_db();
    $sql=[
        "CREATE TABLE IF NOT EXISTS cds_olympia_weeks (id VARCHAR(40) NOT NULL,title VARCHAR(180) NOT NULL,week_label VARCHAR(100) NOT NULL DEFAULT '',open_at DATETIME NULL,close_at DATETIME NULL,access_mode VARCHAR(12) NOT NULL DEFAULT 'auto',logo_path VARCHAR(255) NOT NULL DEFAULT '',created_by VARCHAR(100) NOT NULL DEFAULT '',created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_olympia_week_time(open_at,close_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_week_classes (week_id VARCHAR(40) NOT NULL,class_id VARCHAR(100) NOT NULL,class_name VARCHAR(100) NOT NULL,PRIMARY KEY(week_id,class_id),KEY idx_olympia_wc_class(class_id),CONSTRAINT fk_olympia_wc_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_questions (id VARCHAR(40) NOT NULL,week_id VARCHAR(40) NOT NULL,stage_name VARCHAR(60) NOT NULL DEFAULT 'Khởi động',question_text TEXT NOT NULL,answer_text TEXT NOT NULL,points INT NOT NULL DEFAULT 10,sort_order INT NOT NULL DEFAULT 0,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(id),KEY idx_olympia_q_week(week_id,sort_order),CONSTRAINT fk_olympia_q_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_scores (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,week_id VARCHAR(40) NOT NULL,question_id VARCHAR(40) NOT NULL,class_id VARCHAR(100) NOT NULL,class_name VARCHAR(100) NOT NULL,student_id VARCHAR(100) NOT NULL,student_name VARCHAR(255) NOT NULL,points INT NOT NULL DEFAULT 0,marked_by VARCHAR(255) NOT NULL DEFAULT '',marked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(id),UNIQUE KEY uq_olympia_score(question_id,class_id,student_id),KEY idx_olympia_score_week(week_id,class_id),CONSTRAINT fk_olympia_score_week FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id) ON DELETE CASCADE,CONSTRAINT fk_olympia_score_question FOREIGN KEY(question_id) REFERENCES cds_olympia_questions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS cds_olympia_settings (setting_key VARCHAR(80) NOT NULL,setting_value TEXT NOT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(setting_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
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

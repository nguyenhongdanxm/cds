<?php
require_once __DIR__ . '/database.php';

function qp_db(): PDO { return cds_db(); }
function qp_schema(): void {
    static $ready = false;
    if ($ready) return;
    qp_db()->exec("CREATE TABLE IF NOT EXISTS cds_quiz_sets (
        id VARCHAR(40) PRIMARY KEY, owner_id VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL, questions_json LONGTEXT NOT NULL,
        is_public TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        INDEX(owner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $setColumns = qp_db()->query('SHOW COLUMNS FROM cds_quiz_sets')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('is_public',$setColumns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sets ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0 AFTER questions_json");
    if (!in_array('category',$setColumns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sets ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT '' AFTER title");
    if (!in_array('intro',$setColumns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sets ADD COLUMN intro VARCHAR(500) NOT NULL DEFAULT '' AFTER category");
    qp_db()->exec("CREATE TABLE IF NOT EXISTS cds_quiz_sessions (
        code VARCHAR(10) PRIMARY KEY, set_id VARCHAR(40) NOT NULL,
        class_id VARCHAR(80) NOT NULL, owner_id VARCHAR(100) NOT NULL,
        questions_json LONGTEXT NOT NULL, roster_json LONGTEXT NULL, mode VARCHAR(12) NOT NULL DEFAULT 'computer',
        status VARCHAR(12) NOT NULL DEFAULT 'open', current_index INT NOT NULL DEFAULT 0,
        phase VARCHAR(12) NOT NULL DEFAULT 'question',
        show_correct TINYINT(1) NOT NULL DEFAULT 0,
        show_graph TINYINT(1) NOT NULL DEFAULT 0,
        show_names TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
        INDEX(set_id), INDEX(class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = qp_db()->query('SHOW COLUMNS FROM cds_quiz_sessions')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('roster_json',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN roster_json LONGTEXT NULL AFTER questions_json");
    if (!in_array('mode',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN mode VARCHAR(12) NOT NULL DEFAULT 'computer' AFTER questions_json");
    if (!in_array('current_index',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN current_index INT NOT NULL DEFAULT 0 AFTER status");
    if (!in_array('phase',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN phase VARCHAR(12) NOT NULL DEFAULT 'question' AFTER current_index");
    if (!in_array('show_correct',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN show_correct TINYINT(1) NOT NULL DEFAULT 0 AFTER phase");
    if (!in_array('show_graph',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN show_graph TINYINT(1) NOT NULL DEFAULT 0 AFTER show_correct");
    if (!in_array('show_names',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN show_names TINYINT(1) NOT NULL DEFAULT 1 AFTER show_graph");
    if (!in_array('class_ids_json',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN class_ids_json VARCHAR(300) NULL AFTER class_id");
    qp_db()->exec("CREATE TABLE IF NOT EXISTS cds_quiz_answers (
        session_code VARCHAR(10) NOT NULL, student_id VARCHAR(80) NOT NULL,
        question_index INT NOT NULL, answer TEXT NOT NULL,
        answered_at DATETIME NOT NULL,
        PRIMARY KEY(session_code,student_id,question_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $answerColumn = qp_db()->query("SHOW COLUMNS FROM cds_quiz_answers LIKE 'answer'")->fetch(PDO::FETCH_ASSOC);
    if ($answerColumn && preg_match('/^char\(1\)/i',(string)$answerColumn['Type'])) qp_db()->exec("ALTER TABLE cds_quiz_answers MODIFY answer TEXT NOT NULL");
    qp_db()->exec("CREATE TABLE IF NOT EXISTS cds_quiz_game_visibility (game_key VARCHAR(40) PRIMARY KEY, visible TINYINT(1) NOT NULL DEFAULT 1) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}
function qp_owner(): string {
    $user = current_user() ?? [];
    return (string)($user['id'] ?? $user['username'] ?? '');
}
function qp_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function qp_sets(): array {
    $sql = qp_admin() ? 'SELECT * FROM cds_quiz_sets ORDER BY updated_at DESC' : 'SELECT * FROM cds_quiz_sets WHERE owner_id=? OR is_public=1 ORDER BY updated_at DESC';
    $st = qp_db()->prepare($sql); $st->execute(qp_admin() ? [] : [qp_owner()]);
    return $st->fetchAll();
}
function qp_set(string $id, bool $editable = false): ?array {
    $st = qp_db()->prepare('SELECT * FROM cds_quiz_sets WHERE id=?');
    $st->execute([$id]); $set = $st->fetch();
    if (!$set) return null;
    if ($editable && !qp_admin() && $set['owner_id'] !== qp_owner()) return null;
    $set['questions'] = json_decode((string)$set['questions_json'], true) ?: [];
    return $set;
}
function qp_session(string $code): ?array {
    $st = qp_db()->prepare('SELECT * FROM cds_quiz_sessions WHERE code=? AND expires_at>NOW()');
    $st->execute([$code]); return $st->fetch() ?: null;
}
function qp_questions(array $set): array {
    return array_values(array_filter($set['questions'] ?? [], static fn($q) => is_array($q) && trim((string)($q['text'] ?? '')) !== ''));
}

function qp_game_visibility(): array {
    qp_schema();
    return qp_db()->query('SELECT game_key,visible FROM cds_quiz_game_visibility')->fetchAll(PDO::FETCH_KEY_PAIR);
}
function qp_question_type(array $q): string { return (string)($q['type'] ?? 'single'); }
function qp_answer_correct(array $q, string $answer): bool {
    $type=qp_question_type($q);
    if ($type==='multi') {
        $expected=array_values(array_intersect(['A','B','C','D'],(array)($q['keys']??[])));
        $chosen=json_decode($answer,true);
        if (!is_array($chosen)) return false;
        $chosen=array_values(array_intersect(['A','B','C','D'],$chosen));sort($expected);sort($chosen);
        return $chosen===$expected && count($chosen)===count(array_unique($chosen));
    }
    if ($type==='fill') {
        $normalize=static fn($v)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',(string)$v)),'UTF-8');
        return in_array($normalize($answer),array_map($normalize,(array)($q['fill_answers']??[])),true);
    }
    if ($type==='order') {
        $chosen=json_decode($answer,true);
        return is_array($chosen) && array_values($chosen)===array_values((array)($q['steps']??[]));
    }
    if ($type==='match') {
        $chosen=json_decode($answer,true);
        $pairs=(array)($q['pairs']??[]);
        if (!is_array($chosen) || count($chosen)!==count($pairs)) return false;
        foreach ($pairs as $i=>$pair) if (($chosen[$i]??null)!==($pair[1]??null)) return false;
        return true;
    }
    return $answer===(string)($q['key']??'');
}
function qp_correct_label(array $q): string {
    if (qp_question_type($q)==='fill') return implode(' / ',(array)($q['fill_answers']??[]));
    if (qp_question_type($q)==='order') return implode(' → ',(array)($q['steps']??[]));
    if (qp_question_type($q)==='multi') return implode(', ',(array)($q['keys']??[]));
    if (qp_question_type($q)==='match') return implode('; ',array_map(static fn($p)=>implode(' → ',(array)$p),(array)($q['pairs']??[])));
    return (string)($q['key']??'');
}
function qp_answer_label(string $answer): string {
    $v=json_decode($answer,true);
    return is_array($v)?implode('; ',array_map('strval',$v)):$answer;
}

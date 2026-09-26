<?php
require_once __DIR__ . '/database.php';

function qp_db(): PDO { return cds_db(); }
function qp_schema(): void {
    static $ready = false;
    if ($ready) return;
    qp_db()->exec("CREATE TABLE IF NOT EXISTS cds_quiz_sets (
        id VARCHAR(40) PRIMARY KEY, owner_id VARCHAR(100) NOT NULL,
        title VARCHAR(255) NOT NULL, questions_json LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        INDEX(owner_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    qp_db()->exec("CREATE TABLE IF NOT EXISTS cds_quiz_sessions (
        code VARCHAR(10) PRIMARY KEY, set_id VARCHAR(40) NOT NULL,
        class_id VARCHAR(80) NOT NULL, owner_id VARCHAR(100) NOT NULL,
        questions_json LONGTEXT NOT NULL, roster_json LONGTEXT NULL, mode VARCHAR(12) NOT NULL DEFAULT 'computer',
        status VARCHAR(12) NOT NULL DEFAULT 'open', current_index INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL, expires_at DATETIME NOT NULL,
        INDEX(set_id), INDEX(class_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $columns = qp_db()->query('SHOW COLUMNS FROM cds_quiz_sessions')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('roster_json',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN roster_json LONGTEXT NULL AFTER questions_json");
    if (!in_array('mode',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN mode VARCHAR(12) NOT NULL DEFAULT 'computer' AFTER questions_json");
    if (!in_array('current_index',$columns,true)) qp_db()->exec("ALTER TABLE cds_quiz_sessions ADD COLUMN current_index INT NOT NULL DEFAULT 0 AFTER status");
    qp_db()->exec("CREATE TABLE IF NOT EXISTS cds_quiz_answers (
        session_code VARCHAR(10) NOT NULL, student_id VARCHAR(80) NOT NULL,
        question_index INT NOT NULL, answer CHAR(1) NOT NULL,
        answered_at DATETIME NOT NULL,
        PRIMARY KEY(session_code,student_id,question_index)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $ready = true;
}
function qp_owner(): string {
    $user = current_user() ?? [];
    return (string)($user['id'] ?? $user['username'] ?? '');
}
function qp_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function qp_sets(): array {
    $sql = qp_admin() ? 'SELECT * FROM cds_quiz_sets ORDER BY updated_at DESC' : 'SELECT * FROM cds_quiz_sets WHERE owner_id=? ORDER BY updated_at DESC';
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

<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/database.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Vui lòng đăng nhập lại.'], JSON_UNESCAPED_UNICODE);
    exit;
}

function keoco_reply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function keoco_csrf_token(): string {
    if (empty($_SESSION['keoco_question_bank_csrf'])) {
        $_SESSION['keoco_question_bank_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['keoco_question_bank_csrf'];
}

function keoco_value($value, int $limit): string {
    $value = trim(is_string($value) ? $value : '');
    if ($value === '') return '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
}

function keoco_can_edit(array $set, array $user): bool {
    return ($user['role'] ?? '') === 'admin'
        || ($set['owner_user_id'] ?? '') === (string) ($user['id'] ?? '');
}

function keoco_set_where(array $user): array {
    if (($user['role'] ?? '') === 'admin') return ['', []];
    return [' WHERE (owner_user_id = ? OR is_shared = 1)', [(string) ($user['id'] ?? '')]];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = [];
if ($method === 'POST') {
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0) { $input = $_POST; } else {
        $raw = file_get_contents('php://input', false, null, 0, 2097153);
        if (strlen($raw) > 2097152) keoco_reply(413, ['ok'=>false,'message'=>'Dữ liệu quá lớn.']);
        $input = json_decode($raw ?: '', true);
    }
    if (!is_array($input)) keoco_reply(400, ['ok' => false, 'message' => 'Dữ liệu gửi lên không hợp lệ.']);
    if (!hash_equals(keoco_csrf_token(), (string) ($input['csrf'] ?? ''))) {
        keoco_reply(403, ['ok' => false, 'message' => 'Phiên làm việc không hợp lệ.']);
    }
} elseif ($method !== 'GET') {
    keoco_reply(405, ['ok' => false, 'message' => 'Phương thức không được hỗ trợ.']);
}

$action = (string) ($method === 'GET' ? ($_GET['action'] ?? 'list') : ($input['action'] ?? ''));
$user = current_user() ?: [];
if ($method === 'GET' && !in_array($action, ['list','get'], true)) keoco_reply(405, ['ok'=>false,'message'=>'Thao tác này cần POST.']);
if (($user['role']??'')!=='admin' && !can_perm_level('hl.xem','view')) keoco_reply(403,['ok'=>false,'message'=>'Không có quyền xem học liệu.']);
if (empty($user['id'])) keoco_reply(401, ['ok' => false, 'message' => 'Phiên đăng nhập không hợp lệ.']);

try {
    require_once __DIR__.'/includes/keoco_match.php';
    if (in_array($action,['create_match','import_excel','ai_generate'],true)) {
        if ($method !== 'POST') keoco_reply(405,['ok'=>false,'message'=>'Cần gửi POST.']);
        if (($user['role']??'')!=='admin' && (in_array($user['role']??'',['hs','student','phhs','parent'],true)||!can_perm_level('hl.xem','view'))) keoco_reply(403,['ok'=>false,'message'=>'Bạn chưa có quyền sử dụng trò chơi.']);
        if ($action === 'create_match') {
            $match=kc_new((array)($input['questions']??[]),(array)($input['settings']??[]),microtime(true));
            $code=kc_create_file($match);
            keoco_reply(200,['ok'=>true,'code'=>$code,'tokens'=>$match['tokens']]);
        }
        if ($action === 'import_excel') {
            $file=$_FILES['excel']??[];
            if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||($file['size']??0)>5*1024*1024||strtolower(pathinfo($file['name']??'',PATHINFO_EXTENSION))!=='xlsx') keoco_reply(422,['ok'=>false,'message'=>'Chọn file .xlsx tối đa 5 MB.']);
            require_once __DIR__.'/includes/keoco_excel.php';
            try { $questions=kc_excel_questions($file['tmp_name']); } catch (RuntimeException $e) { keoco_reply(422,['ok'=>false,'message'=>$e->getMessage()]); }
            keoco_reply(200,['ok'=>true,'questions'=>$questions]);
        }
        if (($user['role']??'')!=='admin'&&!can_perm('ai.dayhoc')) keoco_reply(403,['ok'=>false,'message'=>'Bạn chưa có quyền dùng AI dạy học.']);
        if(microtime(true)-(float)($_SESSION['kc_ai_last']??0)<2) keoco_reply(429,['ok'=>false,'message'=>'Vui lòng chờ vài giây.']);
        $_SESSION['kc_ai_last']=microtime(true);
        require_once __DIR__.'/includes/quiz_ai.php';require_once __DIR__.'/includes/ai_service.php';
        $existing=[];foreach(array_slice((array)($input['existing']??[]),-80) as$q)$existing[]=['text'=>(string)($q['question_text']??'')];
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        $result=qp_ai_generate(['type'=>'single','topic'=>$input['topic']??'','reference'=>$input['reference']??'','grade'=>(string)($input['grade']??'all'),'difficulty'=>$input['difficulty']??'medium','count'=>min(2,max(1,(int)($input['count']??2)))],$existing);
        if(empty($result['ok'])) keoco_reply(502,['ok'=>false,'message'=>'AI chưa phản hồi. Kiểm tra cấu hình Trợ lý AI hoặc thử lại.']);
        $rows=[];foreach($result['questions']as$q)$rows[]=['question_text'=>$q['text'],'option_a'=>$q['choices']['A'],'option_b'=>$q['choices']['B'],'option_c'=>$q['choices']['C'],'option_d'=>$q['choices']['D'],'correct_option'=>ord($q['key'])-64];
        keoco_reply(200,['ok'=>true,'questions'=>kc_questions($rows)]);
    }
    $pdo = cds_db();
    if ($action === 'list') {
        [$where, $params] = keoco_set_where($user);
        $sql = 'SELECT s.id, s.title, s.owner_user_id, s.owner_name, s.is_shared, s.updated_at, COUNT(q.id) AS question_count
                FROM cds_game_question_sets s
                LEFT JOIN cds_game_questions q ON q.question_set_id = s.id'
             . $where . ' GROUP BY s.id ORDER BY s.updated_at DESC, s.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sets = $stmt->fetchAll();
        foreach ($sets as &$set) {
            $set['id'] = (int) $set['id'];
            $set['is_shared'] = (bool) $set['is_shared'];
            $set['question_count'] = (int) $set['question_count'];
            $set['can_edit'] = keoco_can_edit($set, $user);
        }
        unset($set);
        keoco_reply(200, ['ok' => true, 'sets' => $sets]);
    }

    $id = filter_var($input['id'] ?? $_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $creating = $action === 'save' && !$id;
    if (!$id && !$creating) keoco_reply(422, ['ok' => false, 'message' => 'Bộ câu hỏi không hợp lệ.']);
    if ($creating) {
        $set = ['owner_user_id' => (string) $user['id']];
    } else {
        [$where, $params] = keoco_set_where($user);
        $stmt = $pdo->prepare('SELECT id, title, owner_user_id, owner_name, is_shared, updated_at FROM cds_game_question_sets' . $where . ($where ? ' AND' : ' WHERE') . ' id = ?');
        $stmt->execute(array_merge($params, [(int) $id]));
        $set = $stmt->fetch();
        if (!$set) keoco_reply(404, ['ok' => false, 'message' => 'Không tìm thấy hoặc không được phép xem bộ câu hỏi.']);
    }

    if ($action === 'get') {
        $questions = $pdo->prepare('SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option FROM cds_game_questions WHERE question_set_id = ? ORDER BY sort_order, id');
        $questions->execute([(int) $id]);
        $set['id'] = (int) $set['id'];
        $set['is_shared'] = (bool) $set['is_shared'];
        $set['can_edit'] = keoco_can_edit($set, $user);
        keoco_reply(200, ['ok' => true, 'set' => $set, 'questions' => $questions->fetchAll()]);
    }
    if (!in_array($action, ['save', 'delete'], true)) keoco_reply(400, ['ok' => false, 'message' => 'Thao tác không hợp lệ.']);
    if (!keoco_can_edit($set, $user)) keoco_reply(403, ['ok' => false, 'message' => 'Bạn chỉ được sửa bộ câu hỏi của mình.']);

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM cds_game_question_sets WHERE id = ?')->execute([(int) $id]);
        keoco_reply(200, ['ok' => true, 'message' => 'Đã xóa bộ câu hỏi.']);
    }

    $title = keoco_value($input['title'] ?? '', 150);
    $rows = $input['questions'] ?? null;
    if ($title === '' || !is_array($rows) || count($rows) < 1 || count($rows) > 500) {
        keoco_reply(422, ['ok' => false, 'message' => 'Nhập tên và từ 1 đến 500 câu hỏi hợp lệ.']);
    }
    $rows = kc_questions($rows);
    $questions = [];
    foreach ($rows as $position => $row) {
        if (!is_array($row)) keoco_reply(422, ['ok' => false, 'message' => 'Dữ liệu câu hỏi không hợp lệ.']);
        $question = keoco_value($row['question_text'] ?? '', 5000);
        $options = [];
        foreach (['option_a', 'option_b', 'option_c', 'option_d'] as $key) $options[] = keoco_value($row[$key] ?? '', 2000);
        $correct = filter_var($row['correct_option'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 4]]);
        if ($question === '' || in_array('', $options, true) || !$correct) {
            keoco_reply(422, ['ok' => false, 'message' => 'Mỗi câu cần nội dung, 4 đáp án và đáp án đúng.']);
        }
        $questions[] = array_merge([$question], $options, [(int) $correct, (int) $position]);
    }
    $pdo->beginTransaction();
    try {
        if ($creating) {
            $ownerName = keoco_value($user['teacher_name'] ?? $user['name'] ?? $user['username'] ?? '', 255);
            $pdo->prepare('INSERT INTO cds_game_question_sets (title, owner_user_id, owner_name, is_shared) VALUES (?, ?, ?, ?)')
                ->execute([$title, (string) $user['id'], $ownerName, !empty($input['is_shared']) ? 1 : 0]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE cds_game_question_sets SET title = ?, is_shared = ? WHERE id = ?')
                ->execute([$title, !empty($input['is_shared']) ? 1 : 0, (int) $id]);
        }
        $pdo->prepare('DELETE FROM cds_game_questions WHERE question_set_id = ?')->execute([(int) $id]);
        $insert = $pdo->prepare('INSERT INTO cds_game_questions (question_set_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($questions as $question) $insert->execute(array_merge([(int) $id], $question));
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    keoco_reply(200, ['ok' => true, 'message' => 'Đã lưu bộ câu hỏi.', 'id' => (int) $id]);
} catch (InvalidArgumentException $e) {
    keoco_reply(422, ['ok'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Keo co question bank API: ' . $e->getMessage());
    keoco_reply(500, ['ok' => false, 'message' => 'Không thể xử lý ngân hàng câu hỏi. Hãy thử lại sau.']);
}

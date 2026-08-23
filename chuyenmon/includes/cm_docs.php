<?php
/** Văn bản / thông báo / chỉ tiêu / báo cáo chuyên môn */
if (!defined('CM_DOCS_FILE')) define('CM_DOCS_FILE', DATA_PATH . '/cm_docs.json');
if (!defined('CM_UPLOAD_DIR')) define('CM_UPLOAD_DIR', DATA_PATH . '/uploads');

/*
 * Thông báo chuyên môn dùng chung kho Google Drive "Kế hoạch và báo cáo".
 * Xử lý sớm tại đây để không bị binding theo URL ghi đè sang một loại kho chưa cấu hình,
 * đồng thời trả lỗi thân thiện thay vì để request rơi vào HTTP 500.
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (string)($_POST['action'] ?? '') === 'save'
    && (string)($_GET['tab'] ?? '') === 'thongbao'
    && isset($_FILES['file'])
    && (int)($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $upload = $_FILES['file'];
    $uploadError = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Tệp vượt quá giới hạn upload_max_filesize của máy chủ.',
            UPLOAD_ERR_FORM_SIZE => 'Tệp vượt quá dung lượng cho phép của biểu mẫu.',
            UPLOAD_ERR_PARTIAL => 'Tệp chỉ được tải lên một phần. Vui lòng thử lại.',
            UPLOAD_ERR_NO_TMP_DIR => 'Máy chủ thiếu thư mục tạm để nhận tệp.',
            UPLOAD_ERR_CANT_WRITE => 'Máy chủ không ghi được tệp tạm.',
            UPLOAD_ERR_EXTENSION => 'PHP đã dừng quá trình tải tệp bởi một extension.',
        ];
        if (function_exists('flash')) flash($messages[$uploadError] ?? ('Không tải được tệp. Mã lỗi: ' . $uploadError), 'danger');
        header('Location: ' . BASE_URL . 'kehoach.php?tab=thongbao');
        exit;
    }

    if (function_exists('cds_drive_settings') && function_exists('cds_drive_upload_bytes')) {
        $settings = cds_drive_settings();
        $plansFolder = trim((string)($settings['types']['plans']['folder_id'] ?? ''));
        if (!empty($settings['enabled']) && $plansFolder !== '') {
            $tmp = (string)($upload['tmp_name'] ?? '');
            $bytes = $tmp !== '' ? @file_get_contents($tmp) : false;
            if ($bytes === false) {
                if (function_exists('flash')) flash('Không đọc được tệp tạm để tải lên Google Drive.', 'danger');
                header('Location: ' . BASE_URL . 'kehoach.php?tab=thongbao');
                exit;
            }
            $mime = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: 'application/octet-stream') : 'application/octet-stream';
            $result = cds_drive_upload_bytes(
                $bytes,
                basename((string)($upload['name'] ?? 'file')),
                $mime,
                'plans'
            );
            if (empty($result['ok'])) {
                if (function_exists('flash')) flash('Không tải được tệp thông báo lên Google Drive: ' . (string)($result['message'] ?? 'Lỗi không xác định.'), 'danger');
                header('Location: ' . BASE_URL . 'kehoach.php?tab=thongbao');
                exit;
            }
            // kehoach.php giữ file_path cũ khi helper phía sau không nhận tệp mới.
            $_POST['file_path'] = (string)($result['path'] ?? '');
            $_FILES['file']['error'] = UPLOAD_ERR_NO_FILE;
            $_FILES['file']['tmp_name'] = '';
        }
    }
}

function cm_docs_all() {
    if (!is_dir(CM_UPLOAD_DIR)) @mkdir(CM_UPLOAD_DIR, 0755, true);
    return load_json(CM_DOCS_FILE, []);
}

function cm_docs_save_all(array $rows) {
    save_json(CM_DOCS_FILE, array_values($rows));
}

function cm_docs_by_section($section) {
    $out = [];
    foreach (cm_docs_all() as $r) {
        if (($r['section'] ?? '') === $section) $out[] = $r;
    }
    usort($out, fn($a, $b) => strcmp($b['date'] ?? '', $a['date'] ?? ''));
    return $out;
}

function cm_doc_uid() {
    return 'doc_' . bin2hex(random_bytes(4));
}

function cm_doc_save(array $data) {
    $rows = cm_docs_all();
    $id = $data['id'] ?? '';
    $found = false;
    foreach ($rows as &$r) {
        if (($r['id'] ?? '') === $id) {
            $r = array_merge($r, $data);
            $r['updated_at'] = date('c');
            $found = true;
            break;
        }
    }
    unset($r);
    if (!$found) {
        $id = $id ?: cm_doc_uid();
        $data['id'] = $id;
        $data['created_at'] = date('c');
        $rows[] = $data;
    }
    cm_docs_save_all($rows);
    return $id;
}

function cm_doc_delete($id) {
    $rows = array_values(array_filter(cm_docs_all(), fn($r) => ($r['id'] ?? '') !== $id));
    cm_docs_save_all($rows);
}

function cm_handle_upload($field = 'file') {
    if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return '';
    }
    if (!is_dir(CM_UPLOAD_DIR)) @mkdir(CM_UPLOAD_DIR, 0755, true);
    $name = $_FILES[$field]['name'] ?? 'file';
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allow = ['pdf','doc','docx','xls','xlsx','ppt','pptx','png','jpg','jpeg','gif','zip','rar'];
    if ($ext && !in_array($ext, $allow, true)) return '';
    $safe = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9._-]+/', '_', $name);
    $dest = CM_UPLOAD_DIR . '/' . $safe;
    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dest)) return '';
    return 'uploads/' . $safe;
}

function cm_file_url($rel) {
    if (!$rel) return '';
    if (preg_match('#^https?://#i', $rel)) return $rel;
    return BASE_URL . 'data/' . ltrim($rel, '/');
}

function cm_section_meta($section) {
    static $map = [
        'kh_vanban' => ['Kế hoạch · Văn bản', 'kehoach.php?tab=vanban', 'bi-file-earmark-pdf', 'kh'],
        'kh_thongbao' => ['Kế hoạch · Thông báo', 'kehoach.php?tab=thongbao', 'bi-megaphone', 'kh'],
        'kh_chitieu' => ['Kế hoạch · Chỉ tiêu', 'kehoach.php?tab=chitieu', 'bi-bullseye', 'kh'],
        'bc_dinhky' => ['Báo cáo định kỳ', 'baocao.php?tab=dinhky', 'bi-calendar-month', 'bc'],
        'bc_thang' => ['Báo cáo định kỳ', 'baocao.php?tab=dinhky', 'bi-calendar-month', 'bc'],
        'bc_tiendo' => ['Tiến độ chương trình', 'baocao.php?tab=tiendo', 'bi-graph-up', 'bc'],
        'bc_dugio' => ['Dự giờ', 'baocao.php?tab=dugio', 'bi-eye', 'bc'],
        'bc_kythi' => ['Kết quả cuộc thi', 'baocao.php?tab=kythi', 'bi-trophy', 'bc'],
    ];
    return $map[$section] ?? ['Khác', 'index.php', 'bi-folder', 'other'];
}

/**
 * Tính hạn hiệu lực:
 * - due_date (YYYY-MM-DD) ưu tiên
 * - hoặc cửa sổ hàng tháng day_from..day_to (vd 22–25)
 */
function cm_resolve_deadline(array $row, $today = null) {
    $today = $today ?: date('Y-m-d');
    $tsToday = strtotime($today . ' 12:00:00');

    if (!empty($row['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['due_date'])) {
        $due = $row['due_date'];
        $days = (int) round((strtotime($due . ' 12:00:00') - $tsToday) / 86400);
        return [
            'due_date' => $due,
            'start_date' => $row['date'] ?? $due,
            'days_left' => $days,
            'window' => '',
            'recurring' => false,
            'status' => $days < 0 ? 'overdue' : ($days <= 5 ? 'urgent' : ($days <= 14 ? 'soon' : 'ok')),
        ];
    }

    $from = isset($row['day_from']) && $row['day_from'] !== '' ? (int)$row['day_from'] : 0;
    $to = isset($row['day_to']) && $row['day_to'] !== '' ? (int)$row['day_to'] : 0;
    if ($from >= 1 && $to >= 1 && $from <= 31 && $to <= 31) {
        if ($to < $from) { $tmp = $from; $from = $to; $to = $tmp; }
        $y = (int)date('Y', $tsToday);
        $m = (int)date('n', $tsToday);
        $d = (int)date('j', $tsToday);

        // Kỳ hiện tại hoặc kỳ tiếp theo
        $endThis = strtotime(sprintf('%04d-%02d-%02d 12:00:00', $y, $m, min($to, (int)date('t', $tsToday))));
        $startThis = strtotime(sprintf('%04d-%02d-%02d 12:00:00', $y, $m, min($from, (int)date('t', $tsToday))));

        if ($d > $to) {
            // sang tháng sau
            $nm = $m === 12 ? 1 : $m + 1;
            $ny = $m === 12 ? $y + 1 : $y;
            $dim = (int)date('t', strtotime("$ny-$nm-01"));
            $startThis = strtotime(sprintf('%04d-%02d-%02d 12:00:00', $ny, $nm, min($from, $dim)));
            $endThis = strtotime(sprintf('%04d-%02d-%02d 12:00:00', $ny, $nm, min($to, $dim)));
        }

        $due = date('Y-m-d', $endThis);
        $start = date('Y-m-d', $startThis);
        $days = (int) round(($endThis - $tsToday) / 86400);
        $inWindow = ($tsToday >= $startThis && $tsToday <= $endThis);

        return [
            'due_date' => $due,
            'start_date' => $start,
            'days_left' => $days,
            'window' => $from . '–' . $to . ' hàng tháng',
            'recurring' => true,
            'in_window' => $inWindow,
            'status' => $days < 0 ? 'overdue' : ($inWindow || $days <= 5 ? 'urgent' : ($days <= 14 ? 'soon' : 'ok')),
        ];
    }

    // fallback: dùng date sự kiện
    if (!empty($row['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $row['date'])) {
        $due = $row['date'];
        $days = (int) round((strtotime($due . ' 12:00:00') - $tsToday) / 86400);
        return [
            'due_date' => $due,
            'start_date' => $due,
            'days_left' => $days,
            'window' => '',
            'recurring' => false,
            'status' => $days < 0 ? 'past' : ($days <= 5 ? 'urgent' : ($days <= 14 ? 'soon' : 'ok')),
        ];
    }

    return null;
}

function cm_enrich_doc(array $row) {
    $meta = cm_section_meta($row['section'] ?? '');
    $dl = cm_resolve_deadline($row);
    $row['_label'] = $meta[0];
    $row['_href'] = BASE_URL . $meta[1];
    $row['_icon'] = $meta[2];
    $row['_group'] = $meta[3];
    $row['_deadline'] = $dl;
    return $row;
}

/** Feed dashboard: mọi mục có ngày / hạn */
function cm_dashboard_items() {
    $out = [];
    foreach (cm_docs_all() as $r) {
        if (($r['kind'] ?? '') === 'result') continue; // kết quả con không hiện riêng
        $out[] = cm_enrich_doc($r);
    }
    return $out;
}

function cm_week_bounds($offsetWeeks = 0) {
    $ts = strtotime('monday this week') + $offsetWeeks * 7 * 86400;
    $start = date('Y-m-d', $ts);
    $end = date('Y-m-d', $ts + 6 * 86400);
    return [$start, $end];
}

function cm_in_range($date, $start, $end) {
    if (!$date) return false;
    return $date >= $start && $date <= $end;
}

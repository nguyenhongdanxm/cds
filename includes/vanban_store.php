<?php

if (!defined('VANBAN_DOCUMENTS_FILE')) define('VANBAN_DOCUMENTS_FILE', DATA_PATH . '/vanban_documents.json');
if (!defined('VANBAN_NUMBERS_FILE')) define('VANBAN_NUMBERS_FILE', DATA_PATH . '/vanban_numbers.json');
if (!defined('VANBAN_ARCHIVES_FILE')) define('VANBAN_ARCHIVES_FILE', DATA_PATH . '/vanban_archives.json');

function vb_rows(string $file): array {
    $rows = load_json($file, []);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

function vb_save_rows(string $file, array $rows): bool {
    return save_json($file, array_values($rows));
}

function vb_id(string $prefix): string {
    return $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
}

function vb_clean(string $value, int $limit = 500): string {
    $value = preg_replace('/\s+/u', ' ', trim($value));
    return mb_substr((string)$value, 0, $limit, 'UTF-8');
}

function vb_date(string $value): string {
    if ($value === '') return '';
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : '';
}

function vb_datetime_local(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    $date = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    return $date && $date->format('Y-m-d\TH:i') === $value ? $value : '';
}

function vb_document_types(): array {
    return ['Quyết định','Kế hoạch','Hướng dẫn','Chỉ thị','Thông tư','Quy chế','Quy định','Công văn','Thông báo','Báo cáo','Biên bản','Khác'];
}

function vb_issuer_levels(): array {
    return ['Bộ/ngành Trung ương','Tỉnh','Sở','Trường','Xã','Đơn vị khác'];
}

function vb_archive_types(): array {
    return ['Biểu mẫu','Giấy đi đường','Thanh toán tàu xe','Hóa đơn/chứng từ','Mẫu biên bản','Mẫu báo cáo','Hồ sơ hành chính','Khác'];
}

function vb_upload(string $field, string $type, array $extra = []): string {
    $upload = $_FILES[$field] ?? null;
    if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Tệp tải lên không hợp lệ.');
    if ((int)($upload['size'] ?? 0) > 25 * 1024 * 1024) throw new RuntimeException('Tệp vượt quá 25 MB.');
    $extension = strtolower((string)pathinfo((string)($upload['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png'], true)) {
        throw new RuntimeException('Chỉ nhận PDF, Word, Excel, PowerPoint hoặc ảnh.');
    }
    $tmp = (string)($upload['tmp_name'] ?? '');
    $bytes = $tmp !== '' ? @file_get_contents($tmp) : false;
    if ($bytes === false) throw new RuntimeException('Không đọc được tệp đã chọn.');
    $mime = function_exists('mime_content_type') ? (mime_content_type($tmp) ?: 'application/octet-stream') : 'application/octet-stream';
    $mappedType = cds_drive_type_for_action(cds_drive_page_action(), $type);
    $settings = cds_drive_settings();
    if (!empty($settings['enabled']) && cds_drive_folder($mappedType, $settings) !== '') {
        $result = cds_drive_upload_bytes($bytes, basename((string)$upload['name']), $mime, $mappedType, $extra);
        if (empty($result['ok'])) throw new RuntimeException($result['message'] ?? 'Không lưu được tệp lên Google Drive.');
        return (string)$result['path'];
    }
    $dir = DATA_PATH . '/uploads/vanban';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Không tạo được thư mục lưu tệp.');
    $name = date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extension;
    if (!move_uploaded_file($tmp, $dir . '/' . $name)) throw new RuntimeException('Không lưu được tệp trên máy chủ.');
    return 'data/uploads/vanban/' . $name;
}

function vb_file_url(string $path): string {
    if ($path === '') return '';
    return str_starts_with($path, 'gdrive:') ? cds_storage_file_url($path) : BASE_URL . ltrim($path, '/');
}

function vb_next_number(string $book, int $year): int {
    $max = 0;
    foreach (vb_rows(VANBAN_NUMBERS_FILE) as $row) {
        if (($row['book'] ?? '') === $book && (int)($row['year'] ?? 0) === $year) $max = max($max, (int)($row['number'] ?? 0));
    }
    return $max + 1;
}

function vb_number_symbol(string $book, int $number): string {
    return str_pad((string)$number, 2, '0', STR_PAD_LEFT) . ($book === 'decision' ? '/QĐ-PTDTNT' : '/PTDTNT');
}

/**
 * Gợi ý số kế tiếp và giữ nguyên phần ký hiệu của số gần nhất trong cùng sổ.
 * Ví dụ: 66/QĐ-NTXM → 67/QĐ-NTXM.
 */
function vb_next_symbol(string $book, int $year): string {
    $next = vb_next_number($book, $year);
    $latestNumber = -1;
    $suffix = '';
    foreach (vb_rows(VANBAN_NUMBERS_FILE) as $row) {
        if (($row['book'] ?? '') !== $book || (int)($row['year'] ?? 0) !== $year) continue;
        $number = (int)($row['number'] ?? 0);
        if ($number < $latestNumber) continue;
        if (preg_match('/^\\s*\\d+(.*)$/u', (string)($row['symbol'] ?? ''), $match)) {
            $latestNumber = $number;
            $suffix = (string)($match[1] ?? '');
        }
    }
    if ($suffix === '') return vb_number_symbol($book, $next);
    return str_pad((string)$next, 2, '0', STR_PAD_LEFT) . $suffix;
}

function vb_find_number(string $id): ?array {
    foreach (vb_rows(VANBAN_NUMBERS_FILE) as $row) if (($row['id'] ?? '') === $id) return $row;
    return null;
}

function vb_norm(string $value): string {
    $value = mb_strtolower(trim($value), 'UTF-8');
    return preg_replace('/\s+/u', ' ', $value);
}

function vb_matches(array $row, array $filters): bool {
    $q = vb_norm((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $haystack = vb_norm(implode(' ', [(string)($row['title'] ?? ''), (string)($row['symbol'] ?? ''), (string)($row['issuer'] ?? ''), (string)($row['signer'] ?? '')]));
        if (!str_contains($haystack, $q)) return false;
    }
    foreach (['type','issuer_level','field'] as $key) {
        $wanted = trim((string)($filters[$key] ?? ''));
        if ($wanted !== '' && ($row[$key] ?? '') !== $wanted) return false;
    }
    return true;
}

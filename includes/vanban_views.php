<?php
/** Theo dõi người dùng đã mở văn bản. */
if (!defined('VANBAN_VIEWS_FILE')) define('VANBAN_VIEWS_FILE', DATA_PATH . '/vanban_document_views.json');

function vb_view_user_key(array $user): string {
    $key = trim((string)($user['username'] ?? $user['email'] ?? $user['id'] ?? ''));
    return $key !== '' ? mb_strtolower($key, 'UTF-8') : '';
}

function vb_document_views_all(): array {
    $rows = load_json(VANBAN_VIEWS_FILE, []);
    return is_array($rows) ? $rows : [];
}

function vb_document_views(string $documentId): array {
    $all = vb_document_views_all();
    $rows = $all[$documentId] ?? [];
    if (!is_array($rows)) return [];
    uasort($rows, fn($a,$b)=>strcmp((string)($b['last_viewed_at']??''),(string)($a['last_viewed_at']??'')));
    return array_values($rows);
}

function vb_document_view_count(string $documentId): int {
    return count(vb_document_views($documentId));
}

function vb_record_document_view(string $documentId, array $user): void {
    $documentId = trim($documentId);
    $userKey = vb_view_user_key($user);
    if ($documentId === '' || $userKey === '') return;
    $all = vb_document_views_all();
    if (!isset($all[$documentId]) || !is_array($all[$documentId])) $all[$documentId] = [];
    $old = $all[$documentId][$userKey] ?? [];
    $now = date('c');
    $all[$documentId][$userKey] = [
        'user_key'=>$userKey,
        'name'=>(string)($user['name'] ?? $user['fullname'] ?? $user['username'] ?? $userKey),
        'username'=>(string)($user['username'] ?? ''),
        'first_viewed_at'=>(string)($old['first_viewed_at'] ?? $now),
        'last_viewed_at'=>$now,
        'view_count'=>(int)($old['view_count'] ?? 0) + 1,
    ];
    save_json(VANBAN_VIEWS_FILE, $all);
}

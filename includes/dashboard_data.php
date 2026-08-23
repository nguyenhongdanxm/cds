<?php
require_once __DIR__ . '/csdl_store.php';

function cds_dashboard_gender_key($value): string {
    $value = trim((string)$value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    if (in_array($value, ['nam','male','m','1'], true)) return 'male';
    if (in_array($value, ['nữ','nu','female','f','2'], true)) return 'female';
    return 'other';
}

function cds_dashboard_gender_stats(array $rows): array {
    $out = ['total'=>count($rows),'male'=>0,'female'=>0,'other'=>0];
    foreach ($rows as $row) $out[cds_dashboard_gender_key($row['gender'] ?? '')]++;
    return $out;
}

/* Giữ nguyên toàn bộ các hàm dashboard hiện có trong bản triển khai; phần dưới chỉ là marker tránh ghi đè sai. */

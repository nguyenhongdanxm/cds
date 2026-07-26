<?php
/** Gọi sau khi biết $tab — chuyển Danh sách sang noitru_list.php */
if (isset($tab) && $tab === 'boarders') {
    $q = $_GET;
    unset($q['tab']);
    $q['view'] = $q['view'] ?? 'students';
    $qs = http_build_query(array_filter($q, fn($v) => $v !== '' && $v !== null));
    header('Location: ' . BASE_URL . 'noitru_list.php' . ($qs !== '' ? '?' . $qs : ''));
    exit;
}

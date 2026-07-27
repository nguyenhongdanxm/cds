<?php
/** Redirect tab boarders → noitru_list.php */
if (($tab ?? $_GET['tab'] ?? '') === 'boarders') {
    $qs = $_GET;
    unset($qs['tab']);
    $q = http_build_query(array_filter($qs, fn($v) => $v !== null && $v !== ''));
    header('Location: ' . BASE_URL . 'noitru_list.php' . ($q ? ('?' . $q) : ''));
    exit;
}

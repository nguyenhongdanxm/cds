<?php
/** Chèn nút Tạo/In thẻ vào trang CSDL học sinh. */
if (defined('STUDENT_CARD_LINK_BUFFERED')) return;
define('STUDENT_CARD_LINK_BUFFERED', true);
function student_card_link_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl\.php$~', $path) || ($_GET['tab'] ?? '') !== 'students') return $html;
    if (strpos($html, 'csdl_student_cards.php') !== false) return $html;
    $button = '<a href="' . (defined('BASE_URL') ? BASE_URL : '/') . 'csdl_student_cards.php" class="btn btn-success btn-sm ms-2"><i class="bi bi-person-badge"></i> Tạo / In thẻ học sinh</a>';
    $pattern = '~(<button[^>]+data-bs-target="#modalStudent"[^>]*>.*?</button>)~si';
    $html = preg_replace($pattern, '$1' . $button, $html, 1) ?? $html;
    return $html;
}
ob_start('student_card_link_filter');

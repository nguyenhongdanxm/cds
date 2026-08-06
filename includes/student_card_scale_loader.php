<?php
if (defined('STUDENT_CARD_SCALE_LOADER')) return;
define('STUDENT_CARD_SCALE_LOADER', true);
function student_card_scale_loader_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl_student_cards\.php$~', $path) || stripos($html, '</body>') === false) return $html;
    $src = (defined('BASE_URL') ? BASE_URL : '/') . 'assets/student-card-scale-fix.js?v=20260806-1';
    $tag = '<script src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"></script>';
    return preg_replace('/<\/body>/i', $tag . '</body>', $html, 1) ?? $html;
}
ob_start('student_card_scale_loader_filter');

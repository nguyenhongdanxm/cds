<?php
require_once dirname(__DIR__) . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');
$subject = $_GET['subject'] ?? '';
$class = $_GET['class'] ?? '';
$periods = get_periods($subject, $class);
echo json_encode(['periods' => $periods], JSON_UNESCAPED_UNICODE);

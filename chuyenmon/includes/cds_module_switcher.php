<?php
// Tệp này được giữ đồng nhất ở nguồn chung và bản chạy trong Chuyên môn.
$cdsSwitcherRoot = basename(dirname(__DIR__)) === 'chuyenmon' ? dirname(__DIR__, 2) : dirname(__DIR__);
require_once $cdsSwitcherRoot . '/includes/module_switcher.php';

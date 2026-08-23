<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_perm('nt.ravao');
$query = $_GET;
$target = BASE_URL . 'noitru_exit.php' . ($query ? '?' . http_build_query($query) : '');
header('Location: ' . $target);
exit;

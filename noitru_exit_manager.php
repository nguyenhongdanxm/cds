<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_perm('nt.ravao');
$query = $_GET;
if (!isset($query['view'])) $query['view'] = 'register';
$target = BASE_URL . 'noitru_exit.php?' . http_build_query($query);
header('Location: ' . $target);
exit;

<?php
$next = (string)($_GET['next'] ?? '/chuyenmon/');
header('Location: /login.php?next=' . urlencode($next));
exit;

<?php
// Evaluation is shared by all staff and lives in Thi dua; preserve one canonical entry.
require_once __DIR__.'/includes/functions.php';
require_login();
header('Location: /thidua_danhgia.php');
exit;

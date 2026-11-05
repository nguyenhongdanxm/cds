<?php
/** Legacy overview entry: assignments now have one workspace. */
require_once __DIR__.'/includes/functions.php';
require_login();
header('Location: '.BASE_URL.'them.php',true,302);
exit;

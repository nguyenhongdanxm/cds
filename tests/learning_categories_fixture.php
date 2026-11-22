<?php
require __DIR__.'/learning_content_fixture.php';
file_put_contents($dir.'/includes/auth.php',"\nrequire_once ".var_export($root.'/includes/json_store.php',true).";\n",FILE_APPEND);

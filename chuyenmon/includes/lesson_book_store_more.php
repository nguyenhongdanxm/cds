<?php
require_once __DIR__ . '/lesson_book_store_rest.php';
function lb_rows_bust(?string $file=null): void {if($file===null){unset($GLOBALS['lb_rows_cache'],$GLOBALS['lb_slots_cache']);return;}unset($GLOBALS['lb_rows_cache'][$file]);if($file===LB_RECORDS_FILE)unset($GLOBALS['lb_slots_cache']);}

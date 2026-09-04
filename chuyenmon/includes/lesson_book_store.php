<?php
$__full = __DIR__ . '/lesson_book_store.full.php';
if (!is_file($__full) || filesize($__full) < 10000) {
    $__raw = gzuncompress(base64_decode('PLACEHOLDER'));
    if ($__raw === false) {
        http_response_code(500);
        exit('Khong giai nen duoc ma nguon So dau bai.');
    }
    file_put_contents($__full, "<?php\n".$__raw, LOCK_EX);
}
require_once $__full;

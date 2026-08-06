<?php
/** Vá mã Chuyên môn trên hosting để không ép số tiết về số nguyên. */
$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
    fwrite(STDERR, "Không tìm thấy thư mục Chuyên môn.\n");
    exit(1);
}

$changed = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $path = $file->getPathname();
    $content = file_get_contents($path);
    if ($content === false) continue;
    $original = $content;

    // Các trường POST có tên liên quan đến số tiết/định mức phải giữ dạng float.
    $content = preg_replace_callback(
        '~\(int\)\s*\(\s*\$_POST\[\s*([\'\"])([^\'\"]*(?:tiet|dinh[_-]?muc)[^\'\"]*)\1\s*\]\s*\)~i',
        static fn($m) => '(float)($_POST[' . $m[1] . $m[2] . $m[1] . '])',
        $content
    );
    $content = preg_replace_callback(
        '~intval\s*\(\s*\$_POST\[\s*([\'\"])([^\'\"]*(?:tiet|dinh[_-]?muc)[^\'\"]*)\1\s*\]\s*\)~i',
        static fn($m) => '(float)($_POST[' . $m[1] . $m[2] . $m[1] . '])',
        $content
    );

    // Biến hoặc phần tử mảng có tên số tiết đang được ép (int).
    $content = preg_replace(
        '~(\$[A-Za-z_][A-Za-z0-9_]*(?:tiet|dinh[_-]?muc)[A-Za-z0-9_]*\s*=\s*)\(int\)~i',
        '$1(float)',
        $content
    );
    $content = preg_replace(
        '~(\$[A-Za-z_][A-Za-z0-9_]*\s*\[\s*[\'\"][^\'\"]*(?:tiet|dinh[_-]?muc)[^\'\"]*[\'\"]\s*\]\s*=\s*)\(int\)~i',
        '$1(float)',
        $content
    );

    if ($content !== $original) {
        file_put_contents($path, $content);
        $changed++;
    }
}

echo "Đã cập nhật $changed file để lưu số tiết thập phân.\n";

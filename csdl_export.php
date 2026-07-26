<?php
/**
 * Xuất CSV hoặc tải mẫu nhập — theo schema CSDL chuẩn.
 * ?entity=teachers|classes|students
 * &mode=export|template
 * &fields[]=name&fields[]=code… (export)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_io.php';
require_login();

$entity = $_GET['entity'] ?? $_POST['entity'] ?? '';
if (!in_array($entity, ['teachers', 'classes', 'students'], true)) {
    http_response_code(400);
    echo 'entity không hợp lệ';
    exit;
}

$mode = $_GET['mode'] ?? $_POST['mode'] ?? 'export';
if ($mode === 'template') {
    csdl_io_template($entity);
}

$fields = $_GET['fields'] ?? $_POST['fields'] ?? [];
if (!is_array($fields)) $fields = [];
csdl_io_export($entity, $fields);

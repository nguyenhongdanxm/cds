<?php
/**
 * Xóa dữ liệu văn bản của các mục Chuyên môn đã bị thay thế và không còn menu.
 * Kế hoạch giáo dục hiện dùng quy trình/phân hệ riêng, vì vậy dữ liệu kh_vanban
 * cũ không còn nguồn quản lý và không được phép xuất hiện trên bảng tổng quan.
 */
if (!function_exists('cm_docs_all')) {
    $cmDocsFile = __DIR__ . '/cm_docs.php';
    if (is_file($cmDocsFile)) require_once $cmDocsFile;
}
if (!function_exists('cm_docs_all') || !function_exists('cm_doc_delete')) return;

$obsoleteSections = ['kh_vanban'];
foreach (cm_docs_all() as $row) {
    if (!is_array($row) || !in_array((string)($row['section'] ?? ''), $obsoleteSections, true)) continue;
    $id = trim((string)($row['id'] ?? ''));
    if ($id !== '') cm_doc_delete($id);
}

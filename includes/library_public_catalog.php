<?php
// Public catalogue is a separate read-only route; management keeps its login gate.
function lib_public_catalog_request(string $method, array $query): bool {
    if ($method !== 'GET' || ($query['tab'] ?? null) !== 'library_books') return false;
    if (array_diff(array_keys($query), ['tab','q','category','grade','page'])) return false;
    foreach ($query as $value) if (!is_string($value)) return false;
    return true;
}
function lib_public_catalog_text($value): string {
    return is_scalar($value) ? (string)$value : '';
}
function lib_public_catalog_norm(string $value): string {
    return function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
}
function lib_public_catalog(array $data, array $query): array {
    // Aggregate once. Never expose borrower records or arbitrary book metadata.
    $borrowed = [];
    foreach (is_array($data['loans'] ?? null) ? $data['loans'] : [] as $loan) {
        if (!is_array($loan) || !empty($loan['returned_at']) || !in_array((string)($loan['status'] ?? 'approved'), ['approved','borrowed'], true)) continue;
        $id = lib_public_catalog_text($loan['book_id'] ?? '');
        $borrowed[$id] = ($borrowed[$id] ?? 0) + (int)($loan['quantity'] ?? 0);
    }
    $q = trim(substr(lib_public_catalog_text($query['q'] ?? ''), 0, 500));
    $category = trim(lib_public_catalog_text($query['category'] ?? ''));
    $grade = trim(lib_public_catalog_text($query['grade'] ?? ''));
    $rows = []; $categories = []; $grades = []; $total = 0;
    foreach (is_array($data['books'] ?? null) ? $data['books'] : [] as $book) {
        if (!is_array($book)) continue;
        $row = [];
        foreach (['title','code','barcode','category','subject','grade','publisher','publication_year','shelf'] as $key) $row[$key] = lib_public_catalog_text($book[$key] ?? '');
        $total++;
        if ($row['category'] !== '') $categories[$row['category']] = $row['category'];
        if ($row['grade'] !== '') $grades[$row['grade']] = $row['grade'];
        if ($category !== '' && $row['category'] !== $category) continue;
        if ($grade !== '' && $row['grade'] !== $grade) continue;
        if ($q !== '' && !str_contains(lib_public_catalog_norm(implode(' ', $row)), lib_public_catalog_norm($q))) continue;
        $row['quantity'] = max(0, (int)($book['quantity'] ?? 0));
        $row['available'] = max(0, (int)($book['quantity'] ?? 0) - ($borrowed[lib_public_catalog_text($book['id'] ?? '')] ?? 0));
        $rows[] = $row;
    }
    natcasesort($categories); natcasesort($grades);
    $count = count($rows); $pages = max(1, (int)ceil($count / 30));
    $page = min($pages, max(1, (int)lib_public_catalog_text($query['page'] ?? '1')));
    return ['rows'=>array_slice($rows, ($page-1)*30, 30), 'count'=>$count, 'total'=>$total, 'page'=>$page, 'pages'=>$pages, 'categories'=>array_values($categories), 'grades'=>array_values($grades), 'q'=>$q, 'category'=>$category, 'grade'=>$grade];
}

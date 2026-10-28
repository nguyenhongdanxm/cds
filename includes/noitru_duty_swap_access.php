<?php
/** Scoped approval only; no general duty edit/delete permission is granted. */
function noitru_duty_swap_actor(): array {
    $user = function_exists('current_user') ? current_user() : null;
    return is_array($user) ? $user : [];
}
function noitru_duty_swap_teacher_id(): string {
    $user = noitru_duty_swap_actor();
    if (!$user || ($user['role'] ?? '') === 'viewer') return '';
    $teachers = function_exists('csdl_teachers_all') ? csdl_teachers_all() : [];
    $id = trim((string)($user['teacher_id'] ?? ''));
    $name = trim((string)($user['teacher_name'] ?? ''));
    if ($id === '' && $name === '') return '';
    $norm = static fn($value) => function_exists('mb_strtolower') ? mb_strtolower(trim((string)$value), 'UTF-8') : strtolower(trim((string)$value));
    $matches = [];
    foreach ($teachers as $teacher) {
        $candidateId = (string)($teacher['id'] ?? '');
        if ($candidateId === '') continue;
        if ($id !== '' ? $candidateId === $id : $norm($teacher['name'] ?? '') === $norm($name)) $matches[] = $teacher;
    }
    // Never guess between duplicate names/IDs, or use a client-supplied display name.
    if (count($matches) !== 1 || (array_key_exists('active', $matches[0]) && empty($matches[0]['active']))) return '';
    return (string)$matches[0]['id'];
}
function noitru_duty_swap_manager_dates(): array {
    $id = noitru_duty_swap_teacher_id();
    if ($id === '') return [];
    $dates = []; $seen = [];
    foreach (noitru_duty_managers_all() as $row) {
        $ids = is_array($row['teacher_ids'] ?? null) ? array_map('strval', $row['teacher_ids']) : [];
        $date = (string)($row['date'] ?? '');
        // Match noitru_duty_manager_for_date(): the first saved row is authoritative.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date) || isset($seen[$date])) continue;
        $seen[$date] = true;
        if (in_array($id, $ids, true)) $dates[$date] = true;
    }
    return $dates;
}
function noitru_duty_swap_manager_related(array $request, array $dates): bool {
    return isset($dates[(string)($request['source_date'] ?? '')]) || isset($dates[(string)($request['target_date'] ?? '')]);
}
function noitru_duty_swap_can_manage_all(): bool {
    $user = noitru_duty_swap_actor();
    if (!$user || ($user['role'] ?? '') === 'viewer') return false;
    if (($user['role'] ?? '') === 'admin') return true;
    return function_exists('can_edit_perm') && can_edit_perm('nt.lichtruc')
        && function_exists('allowed_classes') && allowed_classes() === null;
}
function noitru_duty_swap_can_decide(array $request, ?array $dates = null): bool {
    $user = noitru_duty_swap_actor();
    if (!$user || ($user['role'] ?? '') === 'viewer' || ($request['status'] ?? '') !== 'pending') return false;
    if (noitru_duty_swap_can_manage_all()) return true;
    return noitru_duty_swap_manager_related($request, $dates ?? noitru_duty_swap_manager_dates());
}
function noitru_duty_swap_csrf_token(): string {
    if (empty($_SESSION['duty_swap_csrf'])) $_SESSION['duty_swap_csrf'] = bin2hex(random_bytes(24));
    return (string)$_SESSION['duty_swap_csrf'];
}
function noitru_duty_swap_csrf_valid($token): bool {
    $expected = $_SESSION['duty_swap_csrf'] ?? null;
    return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}
function noitru_duty_swap_require_decision_access(string $id): void {
    foreach (noitru_duty_swap_requests_all() as $request) {
        if ((string)($request['id'] ?? '') === $id && noitru_duty_swap_can_decide($request)) return;
    }
    http_response_code(403);
    exit('Bạn không có quyền duyệt yêu cầu này hoặc yêu cầu không còn chờ duyệt.');
}

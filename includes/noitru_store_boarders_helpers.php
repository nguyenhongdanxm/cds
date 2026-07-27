<?php
/**
 * Helper functions cho boarders — merge vào includes/noitru_store.php nếu chưa có.
 * Không ghi đè nếu function đã tồn tại.
 */

if (!function_exists('noitru_get_boarders_live')) {
    function noitru_get_boarders_live(): array {
        $path = (defined('DATA_PATH') ? DATA_PATH : (__DIR__ . '/../data')) . '/noitru_boarders_live.json';
        if (is_file($path)) {
            $data = json_decode((string)file_get_contents($path), true);
            return is_array($data) ? $data : [];
        }
        if (function_exists('csdl_get_students')) {
            $all = csdl_get_students();
            return array_values(array_filter($all, function ($s) {
                return !empty($s['noi_tru']) || !empty($s['is_boarder']) || !empty($s['boarder']);
            }));
        }
        return [];
    }
}

if (!function_exists('noitru_boarders_stats')) {
    function noitru_boarders_stats(array $boarders): array {
        $male = $female = 0;
        $rooms = $meals = [];
        foreach ($boarders as $b) {
            $g = mb_strtolower((string)($b['gioi_tinh'] ?? $b['gender'] ?? $b['gt'] ?? ''), 'UTF-8');
            if (in_array($g, ['nam', 'male', 'm'], true)) {
                $male++;
            } else {
                $female++;
            }
            $r = trim((string)($b['phong'] ?? $b['room'] ?? $b['ten_phong'] ?? $b['phong_o'] ?? ''));
            $m = trim((string)($b['mam'] ?? $b['mam_an'] ?? $b['nhom_an'] ?? $b['meal'] ?? $b['meal_group'] ?? $b['nhom'] ?? ''));
            if ($r !== '') $rooms[$r] = true;
            if ($m !== '') $meals[$m] = true;
        }
        return [
            'total'  => count($boarders),
            'male'   => $male,
            'female' => $female,
            'rooms'  => count($rooms),
            'meals'  => count($meals),
        ];
    }
}

if (!function_exists('noitru_sync_boarders_from_csdl')) {
    function noitru_sync_boarders_from_csdl(): array {
        if (!function_exists('csdl_get_students')) {
            return ['ok' => false, 'message' => 'Chưa có hàm csdl_get_students() — không thể đồng bộ'];
        }
        $all = csdl_get_students();
        $boarders = [];
        foreach ($all as $s) {
            if (empty($s['noi_tru']) && empty($s['is_boarder']) && empty($s['boarder'])) {
                continue;
            }
            $boarders[] = [
                'id'        => $s['id'] ?? $s['ma_hs'] ?? $s['student_id'] ?? '',
                'ho_ten'    => $s['ho_ten'] ?? $s['hoten'] ?? $s['name'] ?? $s['ten'] ?? '',
                'lop'       => $s['lop'] ?? $s['ten_lop'] ?? $s['class'] ?? '',
                'phong'     => $s['phong'] ?? $s['phong_o'] ?? $s['room'] ?? $s['ten_phong'] ?? '',
                'mam'       => $s['mam'] ?? $s['mam_an'] ?? $s['nhom_an'] ?? $s['meal'] ?? $s['meal_group'] ?? $s['nhom'] ?? '',
                'gioi_tinh' => $s['gioi_tinh'] ?? $s['gender'] ?? $s['gt'] ?? '',
                'sdt'       => $s['sdt'] ?? $s['dien_thoai'] ?? $s['phone'] ?? $s['so_dien_thoai'] ?? '',
            ];
        }
        $dir  = defined('DATA_PATH') ? DATA_PATH : (__DIR__ . '/../data');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $path = $dir . '/noitru_boarders_live.json';
        $ok = (bool)file_put_contents(
            $path,
            json_encode($boarders, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
        if (!$ok) {
            return ['ok' => false, 'message' => 'Không ghi được file boarders_live'];
        }
        return [
            'ok'      => true,
            'message' => 'Đã đồng bộ ' . count($boarders) . ' học sinh nội trú từ CSDL',
            'count'   => count($boarders),
        ];
    }
}

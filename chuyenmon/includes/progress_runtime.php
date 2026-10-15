<?php
require_once dirname(__DIR__, 2) . '/includes/json_store.php';

/** Stream current list format; retain compatibility with older keyed JSON objects. */
function cm_progress_records_for_view(string $file): iterable {
    $handle = @fopen($file, 'rb');
    if (!$handle) return [];
    $first = '';
    try {
        while (!feof($handle) && $first === '') {
            $chunk = fread($handle,4096);
            if ($chunk === false) break;
            $trimmed = ltrim($chunk); if ($trimmed !== '') $first = $trimmed[0];
        }
    } finally { fclose($handle); }
    if ($first === '[') return cds_json_rows($file);
    $rows = load_json($file, []);
    return is_array($rows) ? $rows : [];
}

function cm_progress_grade(string $class): string {
    return preg_match('/\d+/', $class, $match) ? (string)(int)$match[0] : '';
}
function cm_progress_selected_week(array $weeks, $requested, string $today): ?array {
    $number = is_scalar($requested) ? max(0,(int)$requested) : 0;
    foreach ($weeks as $week) if ((int)($week['number'] ?? 0) === $number && $number > 0) return $week;
    $nearest = null;
    foreach ($weeks as $week) {
        if ($today >= $week['start'] && $today <= $week['end']) return $week;
        if ($week['start'] <= $today && (!$nearest || $week['start'] > $nearest['start'])) $nearest = $week;
    }
    return $nearest ?? ($weeks[0] ?? null);
}
/** Fingerprint all schedule sources. Never cache signed PPCT/actual progress. */
function cm_progress_schedule_version(): ?string {
    $files = [__FILE__, dirname(__DIR__).'/baocao.php', __DIR__.'/lesson_book_store.php', __DIR__.'/timetable_store.php', dirname(__DIR__,2).'/includes/database_lesson_book.php'];
    foreach (['TKB_WEEKS_FILE','TKB_MAPPING_FILE','TKB_SUBSTITUTIONS_FILE','TKB_ROOMS_FILE','TKB_ROOM_BOOKINGS_FILE'] as $name) {
        if (!defined($name)) return null;
        $files[] = constant($name);
    }
    $hashes = [];
    foreach ($files as $file) {
        if (!file_exists($file)) { $hashes[$file] = 'missing'; continue; }
        $hash = @hash_file('sha256', $file);
        if ($hash === false) return null;
        $hashes[$file] = $hash;
    }
    return hash('sha256', json_encode($hashes, JSON_THROW_ON_ERROR));
}
function cm_progress_schedule_cache_dir(): string {
    return sys_get_temp_dir().'/cds-progress-'.substr(hash('sha256', __DIR__.'|'.DATA_PATH),0,24);
}
function cm_progress_schedule_counts(array $week, ?string $version, string $calendar): array {
    $key = hash('sha256', json_encode([$version,$calendar,$week], JSON_THROW_ON_ERROR));
    $dir = cm_progress_schedule_cache_dir();
    $file = $dir.'/'.$key.'.json';
    if ($version !== null && !is_link($dir) && is_file($file)) {
        $cached = json_decode((string)@file_get_contents($file), true);
        if (is_array($cached) && ($cached['key'] ?? '') === $key && is_array($cached['counts'] ?? null)
            && hash('sha256', json_encode($cached['counts'], JSON_THROW_ON_ERROR)) === ($cached['checksum'] ?? '')) {
            return $cached['counts'];
        }
    }
    $counts = [];
    foreach (lb_slots($week, [], true) as $row) {
        $date = (string)($row['date'] ?? '');
        if ($date === '') continue;
        $scope = cm_progress_scope_key((string)($row['class'] ?? ''), (string)($row['subject'] ?? ''));
        $counts[$date][$scope] = ($counts[$date][$scope] ?? 0) + 1;
    }
    // Optional cache failure must never prevent a correct fresh calculation.
    if ($version !== null && !is_link($dir) && (is_dir($dir) || @mkdir($dir,0700,true))
        && cm_progress_schedule_version() === $version) {
        cds_json_save($file, ['key'=>$key,'counts'=>$counts,'checksum'=>hash('sha256',json_encode($counts,JSON_THROW_ON_ERROR))]);
        $entries = glob($dir.'/*.json') ?: [];
        if (count($entries) > 128) {
            $ages = []; foreach ($entries as $entry) $ages[$entry] = (int)@filemtime($entry);
            asort($ages); foreach (array_slice(array_keys($ages),0,count($entries)-128) as $old) if ($old !== $file) @unlink($old);
        }
    }
    return $counts;
}

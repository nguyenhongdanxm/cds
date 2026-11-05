<?php
/** A published copy is independent of the editable working version. */
function pccm_release_state(string $dir): array {
    $file = $dir.'/pccm_release.php';
    if (!is_file($file)) return [];
    $raw = file_get_contents($file);
    $prefix = "<?php http_response_code(404); exit; ?>\n";
    $state = is_string($raw) && str_starts_with($raw,$prefix) ? json_decode(substr($raw,strlen($prefix)),true) : null;
    if (!is_array($state)) throw new RuntimeException('Không đọc được bản công bố phân công.');
    return $state;
}

function pccm_save_release_state(string $dir, array $state): bool {
    $json = json_encode($state,JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    $contents = "<?php http_response_code(404); exit; ?>\n".$json;
    $temp = tempnam($dir,'.pccm-release-');
    if ($temp === false) return false;
    try {
        if (file_put_contents($temp,$contents) !== strlen($contents)) return false;
        chmod($temp,0640);
        return rename($temp,$dir.'/pccm_release.php');
    } finally {
        if (is_file($temp)) unlink($temp);
    }
}

function pccm_release_transaction(string $dir, callable $operation) {
    if (isset($GLOBALS['pccm_request_locks'][realpath($dir)])) return $operation(pccm_release_state($dir));
    $lock = pccm_open_lock($dir);
    try {
        return $operation(pccm_release_state($dir));
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

function pccm_open_lock(string $dir) {
    $lock = fopen($dir.'/pccm_release.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) {
        if ($lock) fclose($lock);
        throw new RuntimeException('Không khóa được kho phân công.');
    }
    return $lock;
}

function pccm_lock_request(string $dir): void {
    $key = realpath($dir);
    if (isset($GLOBALS['pccm_request_locks'][$key])) return;
    $lock = pccm_open_lock($dir);
    $GLOBALS['pccm_request_locks'][$key] = $lock;
    register_shutdown_function(static function () use ($lock,$key) {
        unset($GLOBALS['pccm_request_locks'][$key]);
        fclose($lock);
    });
}

function pccm_manual_scope(array $rows, string $version, string $active): array {
    return array_values(array_filter($rows, static function ($row) use ($version, $active) {
        if (!is_array($row)) return false;
        $owner = trim((string)($row['version_id'] ?? ''));
        return ($owner === '' ? $active : $owner) === $version;
    }));
}

function pccm_assignment_write(string $file, callable $operation, ?array $nextManual = null): bool {
    $dir = dirname($file);
    $name = basename($file);
    $matches = [];
    $manual = $name === 'manual_assignments.json';
    if (!$manual && !preg_match('/^(?:assignments|roles|program_assignments)_([a-zA-Z0-9_-]+)\.json$/D', $name, $matches)) {
        return $operation();
    }
    return pccm_release_transaction($dir, function ($state) use ($dir, $file, $manual, $matches, $operation, $nextManual) {
        $locked = $state['locked'] ?? [];
        if (!$manual && !empty($locked[$matches[1]])) return false;
        if ($manual && $locked) {
            if ($nextManual === null) return false;
            $active = (string)get_active_version_id();
            $old = cds_json_load_for_update($file, []);
            foreach ($locked as $version => $value) {
                if ($value && pccm_manual_scope($old,(string)$version,$active) !== pccm_manual_scope($nextManual,(string)$version,$active)) return false;
            }
        }
        return $operation();
    });
}

function pccm_published_source(string $dir): ?array {
    return pccm_release_state($dir)['published'] ?? null;
}

function pccm_publish_version(string $dir, string $version, string $name, bool $unlock): void {
    pccm_release_transaction($dir, function ($state) use ($dir, $version, $name, $unlock) {
        if ((string)get_active_version_id() !== $version) throw new RuntimeException('Phiên bản đang làm việc đã đổi. Hãy tải lại trang.');
        if ($unlock) {
            unset($state['locked'][$version]);
        } else {
            if (!empty($state['locked'][$version])) throw new RuntimeException('Phiên bản này đã khóa.');
            $rows = cds_json_load_for_update(assignments_file($version), []);
            $manual = pccm_manual_scope(cds_json_load_for_update($dir.'/manual_assignments.json', []), $version, $version);
            $configs = cds_json_load_for_update(gdp_file($dir,$version), []);
            if (!$rows && !$manual && !$configs) throw new RuntimeException('Phiên bản chưa có phân công để công bố.');
            $state['published'] = [
                'version'=>$version, 'name'=>$name, 'published_at'=>date('c'),
                'rows'=>$rows, 'manual'=>$manual, 'configs'=>$configs,
                'roles'=>cds_json_load_for_update(role_assignments_file($version), []),
            ];
            $state['locked'][$version] = true;
        }
        if (!pccm_save_release_state($dir, $state)) throw new RuntimeException('Không lưu được trạng thái công bố.');
    });
}

function pccm_delete_manual_rows(string $dir, string $version, array $ids): void {
    if (!$ids) throw new RuntimeException('Hãy chọn phân công thủ công cần xóa.');
    pccm_release_transaction($dir, function ($state) use ($dir,$version,$ids) {
        if ($version !== (string)get_active_version_id() || !empty($state['locked'][$version])) throw new RuntimeException('Phiên bản đã khóa hoặc đã đổi.');
        $file = $dir.'/manual_assignments.json';
        $rows = cds_json_load_for_update($file, []);
        $eligible = pccm_manual_scope($rows,$version,$version);
        $matches = array_filter($eligible,static fn($row)=>in_array((string)($row['id']??''),$ids,true));
        if (count(array_unique(array_column($matches,'id'))) !== count($ids)) throw new RuntimeException('Danh sách đã thay đổi. Hãy tải lại trang và chọn lại.');
        $next = array_values(array_filter($rows,static fn($row)=>!in_array($row,$matches,true)));
        if (!cds_json_save($file,$next)) throw new RuntimeException('Không ghi được danh sách phân công thủ công.');
    });
}

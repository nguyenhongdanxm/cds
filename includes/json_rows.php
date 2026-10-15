<?php
/** Incremental JSON-list I/O. Existing files are published only after full validation. */
function cds_json_rows(string $file): Generator {
    if (!file_exists($file)) return;
    $handle = @fopen($file, 'rb');
    if (!$handle) throw new RuntimeException('Không đọc được dữ liệu: ' . basename($file));
    $phase = 'root'; $record = ''; $depth = 0; $string = false; $escaped = false;
    try {
        while (!feof($handle)) {
            $chunk = fread($handle, 65536);
            if ($chunk === false) throw new RuntimeException('Lỗi đọc dữ liệu: ' . basename($file));
            for ($i = 0, $length = strlen($chunk); $i < $length; $i++) {
                $char = $chunk[$i];
                if ($phase === 'record') {
                    if (!$escaped) {
                        $span = strcspn($chunk, $string ? "\\\"" : '"{}[]', $i);
                        if ($span > 0) {
                            $record .= substr($chunk, $i, $span);
                            $i += $span - 1;
                            continue;
                        }
                    }
                    $record .= $char;
                    if ($string) {
                        if ($escaped) $escaped = false;
                        elseif ($char === '\\') $escaped = true;
                        elseif ($char === '"') $string = false;
                    } elseif ($char === '"') $string = true;
                    elseif ($char === '{' || $char === '[') $depth++;
                    elseif ($char === '}' || $char === ']') $depth--;
                    if ($depth === 0 && !$string) {
                        $row = json_decode($record, true);
                        if (json_last_error() !== JSON_ERROR_NONE || !is_array($row)) throw new RuntimeException('Bản ghi JSON không hợp lệ.');
                        $phase = 'separator'; $record = '';
                        yield $row;
                    }
                    continue;
                }
                if (strpos(" \t\r\n", $char) !== false) continue;
                if ($phase === 'root' && $char === '[') { $phase = 'first'; continue; }
                if (($phase === 'first' || $phase === 'separator') && $char === ']') { $phase = 'end'; continue; }
                if ($phase === 'separator' && $char === ',') { $phase = 'next'; continue; }
                if (($phase === 'first' || $phase === 'next') && ($char === '{' || $char === '[')) {
                    $phase = 'record'; $record = $char; $depth = 1; continue;
                }
                throw new RuntimeException('Danh sách JSON không hợp lệ; giữ nguyên nguồn: ' . basename($file));
            }
        }
        if ($phase !== 'end') throw new RuntimeException('Danh sách JSON chưa hoàn chỉnh; giữ nguyên nguồn: ' . basename($file));
    } finally { fclose($handle); }
}

/** Caller holds the domain write lock for the complete read/transform/publish sequence. */
function cds_json_rows_save(string $file, iterable $rows): bool {
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) return false;
    $tmp = @tempnam($dir, basename($file) . '.tmp.');
    if ($tmp === false) return false;
    $handle = @fopen($tmp, 'wb');
    if (!$handle) { @unlink($tmp); return false; }
    $write = static function (string $bytes) use ($handle): void {
        for ($offset = 0, $size = strlen($bytes); $offset < $size; $offset += $written) {
            $written = fwrite($handle, substr($bytes, $offset));
            if ($written === false || $written === 0) throw new RuntimeException('Không ghi được tệp JSON tạm.');
        }
    };
    try {
        $write("[\n"); $first = true;
        foreach ($rows as $row) {
            if (!is_array($row)) throw new RuntimeException('Bản ghi cần lưu không hợp lệ.');
            $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($encoded === false) throw new RuntimeException('Không mã hóa được bản ghi JSON.');
            $write(($first ? '' : ",\n") . $encoded); $first = false;
        }
        $write("\n]");
        if (!fflush($handle)) throw new RuntimeException('Không hoàn tất tệp JSON tạm.');
        fclose($handle); $handle = null;
        @chmod($tmp, 0640);
        if (!@rename($tmp, $file)) throw new RuntimeException('Không công bố được tệp JSON.');
        clearstatcache(true, $file);
        return true;
    } catch (Throwable $e) {
        error_log('[CDS JSON rows] ' . $e->getMessage());
        return false;
    } finally {
        if (is_resource($handle)) fclose($handle);
        if (is_file($tmp)) @unlink($tmp);
    }
}

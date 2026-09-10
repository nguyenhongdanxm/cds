<?php

function cmact_online_slots(array $row): array {
    $slots = is_array($row['slots'] ?? null) ? $row['slots'] : [];
    if (!$slots) $slots = [[
        'days' => (array)($row['days'] ?? []),
        'session' => (string)($row['session'] ?? ''),
        'start_time' => (string)($row['start_time'] ?? ''),
        'end_time' => (string)($row['end_time'] ?? ''),
    ]];
    return $slots;
}

function cmact_online_day_code(DateTimeImmutable $date): string {
    $isoDay = (int)$date->format('N');
    return $isoDay === 7 ? 'CN' : (string)($isoDay + 1);
}

function cmact_online_day_label(DateTimeImmutable $date): string {
    $code = cmact_online_day_code($date);
    return ($code === 'CN' ? 'Chủ nhật' : 'Thứ ' . $code) . ', ngày ' . $date->format('d/m/Y');
}

function cmact_online_schedule(array $enrollments, array $students, ?DateTimeImmutable $now = null): array {
    $now = $now ?: new DateTimeImmutable('now');
    $today = $now->setTime(0, 0);
    $current = [];
    $upcoming = [];

    for ($offset = 0; $offset <= 7; $offset++) {
        $date = $today->modify('+' . $offset . ' days');
        $dateKey = $date->format('Y-m-d');
        $dayCode = cmact_online_day_code($date);
        foreach ($enrollments as $row) {
            if (isset($row['active']) && !$row['active']) continue;
            $from = (string)($row['program_start'] ?? '');
            $to = (string)($row['program_end'] ?? '');
            if (($from !== '' && $dateKey < $from) || ($to !== '' && $dateKey > $to)) continue;
            $studentId = (string)($row['student_id'] ?? '');
            $student = $students[$studentId] ?? ['name' => 'Học sinh không còn trong CSDL', 'class' => ''];
            foreach (cmact_online_slots($row) as $slot) {
                if (!in_array($dayCode, array_map('strval', (array)($slot['days'] ?? [])), true)) continue;
                $startText = (string)($slot['start_time'] ?? '');
                $endText = (string)($slot['end_time'] ?? '');
                if (!preg_match('/^\d{2}:\d{2}$/', $startText) || !preg_match('/^\d{2}:\d{2}$/', $endText)) continue;
                $start = new DateTimeImmutable($dateKey . ' ' . $startText, $now->getTimezone());
                $end = new DateTimeImmutable($dateKey . ' ' . $endText, $now->getTimezone());
                if ($end <= $start) continue;
                $item = [
                    'start' => $start,
                    'end' => $end,
                    'session' => (string)($slot['session'] ?? ''),
                    'program' => (string)($row['program'] ?? ''),
                    'student_id' => $studentId,
                    'student' => $student,
                ];
                if ($start <= $now && $now < $end) $current[] = $item;
                elseif ($start > $now) $upcoming[] = $item;
            }
        }
    }

    usort($upcoming, fn($a, $b) => $a['start'] <=> $b['start']);
    if ($upcoming) {
        $nextTimestamp = $upcoming[0]['start']->getTimestamp();
        $upcoming = array_values(array_filter($upcoming, fn($item) => $item['start']->getTimestamp() === $nextTimestamp));
    }
    return ['now' => $now, 'current' => cmact_online_group_schedule($current), 'upcoming' => cmact_online_group_schedule($upcoming)];
}

function cmact_online_group_schedule(array $items): array {
    $groups = [];
    foreach ($items as $item) {
        $key = $item['start']->format('c') . '|' . $item['end']->format('c') . '|' . $item['session'] . '|' . $item['program'];
        if (!isset($groups[$key])) $groups[$key] = array_merge($item, ['students' => []]);
        $studentKey = $item['student_id'] !== '' ? $item['student_id'] : $item['student']['class'] . '|' . $item['student']['name'];
        $groups[$key]['students'][$studentKey] = $item['student'];
    }
    foreach ($groups as &$group) $group['students'] = array_values($group['students']);
    unset($group);
    return array_values($groups);
}

function cmact_store_online_template(array $upload): array {
    $error = (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) return [];
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Tải tệp mẫu đơn không thành công.');
    $size = (int)($upload['size'] ?? 0);
    if ($size < 1 || $size > 10 * 1024 * 1024) throw new RuntimeException('Tệp mẫu đơn phải có dung lượng từ 1 byte đến 10 MB.');
    $original = basename((string)($upload['name'] ?? ''));
    $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['pdf', 'doc', 'docx', 'odt'];
    if (!in_array($ext, $allowed, true)) throw new RuntimeException('Chỉ nhận tệp PDF, DOC, DOCX hoặc ODT.');
    $tmp = (string)($upload['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) throw new RuntimeException('Máy chủ không nhận được tệp tải lên.');
    $dir = DATA_PATH . '/cm_online_templates';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Không tạo được thư mục lưu mẫu đơn.');
    $stored = 'online_application_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    if (!move_uploaded_file($tmp, $dir . '/' . $stored)) throw new RuntimeException('Không lưu được tệp mẫu đơn trên máy chủ.');
    return ['stored_name' => $stored, 'original_name' => cmact_text($original, 180), 'size' => $size, 'uploaded_at' => date('c')];
}

function cmact_sanitize_rich_html($value, int $max = 50000): string {
    $html = trim((string)$value);
    if ($html === '') return '';
    if (strlen($html) > $max) $html = substr($html, 0, $max);
    if (!preg_match('/<\/?[a-z][^>]*>/i', $html)) $html = nl2br(htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
    if (!class_exists('DOMDocument')) {
        $safe = strip_tags($html, '<p><div><br><strong><b><em><i><u><s><h1><h2><h3><h4><ul><ol><li><blockquote><table><thead><tbody><tr><th><td>');
        return (string)preg_replace('/\s+(?:on\w+|style|href|src)\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $safe);
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="cm-rich-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $doc->getElementById('cm-rich-root');
    if (!$root) return '';
    $allowed = ['p','div','br','strong','b','em','i','u','s','h1','h2','h3','h4','ul','ol','li','blockquote','a','span','font','table','thead','tbody','tr','th','td'];
    $clean = function (DOMNode $parent) use (&$clean, $allowed): void {
        for ($node = $parent->firstChild; $node;) {
            $next = $node->nextSibling;
            if ($node instanceof DOMComment) $parent->removeChild($node);
            elseif ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);
                if (!in_array($tag, $allowed, true)) {
                    $clean($node);
                    while ($node->firstChild) $parent->insertBefore($node->firstChild, $node);
                    $parent->removeChild($node);
                } else {
                    foreach (iterator_to_array($node->attributes) as $attribute) {
                        $name = strtolower($attribute->name);
                        $value = trim($attribute->value);
                        $keep = false;
                        if ($tag === 'a' && $name === 'href' && preg_match('#^(https?://|mailto:|/)#i', $value)) $keep = true;
                        elseif ($tag === 'a' && in_array($name, ['title','target'], true)) $keep = true;
                        elseif ($tag === 'font' && in_array($name, ['face','size','color'], true) && !preg_match('/[<>"\']/', $value)) $keep = true;
                        elseif (in_array($tag, ['p','div','h1','h2','h3','h4','td','th'], true) && $name === 'align' && in_array(strtolower($value), ['left','center','right','justify'], true)) $keep = true;
                        elseif ($name === 'style') {
                            $parts = [];
                            foreach (explode(';', $value) as $rule) {
                                if (!str_contains($rule, ':')) continue;
                                [$property, $setting] = array_map('trim', explode(':', $rule, 2));
                                $property = strtolower($property);
                                if (in_array($property, ['text-align','font-family','font-size','color','background-color'], true) && preg_match('/^[#(),.%\-\w\s"\']+$/u', $setting) && !preg_match('/url|expression/i', $setting)) $parts[] = $property . ':' . $setting;
                            }
                            if ($parts) {$node->setAttribute('style', implode(';', $parts));$keep = true;}
                        }
                        if (!$keep) $node->removeAttribute($attribute->name);
                    }
                    if ($tag === 'a' && $node->hasAttribute('href')) $node->setAttribute('rel', 'noopener noreferrer');
                    $clean($node);
                }
            }
            $node = $next;
        }
    };
    $clean($root);
    $output = '';
    foreach ($root->childNodes as $child) $output .= $doc->saveHTML($child);
    return trim($output);
}

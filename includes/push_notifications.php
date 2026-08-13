<?php
/**
 * CDS Web Push (RFC 8291 + VAPID) không phụ thuộc Composer.
 * Dữ liệu thiết bị và khóa được lưu ngoài mã nguồn trong thư mục data.
 */
require_once __DIR__ . '/config.php';

/* Khóa VAPID và endpoint thiết bị không đặt trong public_html. */
if (!defined('CDS_PUSH_DATA_PATH')) {
    /* Luôn tính từ thư mục gốc của CDS, không phụ thuộc BASE_PATH của module con. */
    $applicationRoot = dirname(__DIR__);
    $privateDir = dirname($applicationRoot) . '/cds_private';
    if (!is_dir($privateDir)) @mkdir($privateDir, 0750, true);
    define('CDS_PUSH_DATA_PATH', $privateDir);
}
if (!defined('CDS_PUSH_SUBSCRIPTIONS_FILE')) define('CDS_PUSH_SUBSCRIPTIONS_FILE', CDS_PUSH_DATA_PATH . '/push_subscriptions.json');
if (!defined('CDS_PUSH_NOTIFICATIONS_FILE')) define('CDS_PUSH_NOTIFICATIONS_FILE', CDS_PUSH_DATA_PATH . '/push_notifications.json');
if (!defined('CDS_PUSH_READ_FILE')) define('CDS_PUSH_READ_FILE', CDS_PUSH_DATA_PATH . '/push_notification_reads.json');
if (!defined('CDS_PUSH_KEYS_FILE')) define('CDS_PUSH_KEYS_FILE', CDS_PUSH_DATA_PATH . '/push_vapid_keys.json');
if (!defined('CDS_PUSH_DASHBOARD_SYNC_FILE')) define('CDS_PUSH_DASHBOARD_SYNC_FILE', CDS_PUSH_DATA_PATH . '/push_dashboard_sync.json');

function cds_push_b64url_encode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
function cds_push_b64url_decode(string $value): string {
    $value = strtr($value, '-_', '+/');
    return (string)base64_decode($value . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
}
function cds_push_json_rows(string $file): array {
    if (!is_file($file)) return [];
    $rows = json_decode((string)file_get_contents($file), true);
    return is_array($rows) ? $rows : [];
}
function cds_push_save_json(string $file, array $data): bool {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0750, true);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;
    $tmp = tempnam(dirname($file), '.push-');
    if ($tmp === false || file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    @chmod($tmp, 0640);
    if (!@rename($tmp, $file)) { @unlink($tmp); return false; }
    return true;
}
function cds_push_user_key(?array $user = null): string {
    $user = $user ?? ($_SESSION['cds_user'] ?? []);
    return trim((string)($user['id'] ?? $user['username'] ?? ''));
}
function cds_push_public_from_details(array $details): string {
    $ec = (array)($details['ec'] ?? []);
    if (empty($ec['x']) || empty($ec['y'])) return '';
    return "\x04" . $ec['x'] . $ec['y'];
}
function cds_push_keys(): array {
    $saved = cds_push_json_rows(CDS_PUSH_KEYS_FILE);
    if (!empty($saved['public_key']) && !empty($saved['private_pem'])) return $saved;
    if (!function_exists('openssl_pkey_new')) return [];
    $key = openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC, 'curve_name'=>'prime256v1']);
    if (!$key || !openssl_pkey_export($key, $pem)) return [];
    $details = openssl_pkey_get_details($key);
    $public = is_array($details) ? cds_push_public_from_details($details) : '';
    if ($public === '') return [];
    $saved = ['public_key'=>cds_push_b64url_encode($public), 'private_pem'=>$pem, 'created_at'=>date('c')];
    return cds_push_save_json(CDS_PUSH_KEYS_FILE, $saved) ? $saved : [];
}
function cds_push_public_key(): string { return (string)(cds_push_keys()['public_key'] ?? ''); }
function cds_push_subscription_valid(array $row): bool {
    return preg_match('#^https://#i', (string)($row['endpoint'] ?? ''))
        && strlen(cds_push_b64url_decode((string)($row['p256dh'] ?? ''))) === 65
        && strlen(cds_push_b64url_decode((string)($row['auth'] ?? ''))) === 16;
}
function cds_push_save_subscription(array $subscription, array $user): bool {
    $endpoint = trim((string)($subscription['endpoint'] ?? ''));
    $keys = (array)($subscription['keys'] ?? []);
    $row = [
        'endpoint'=>$endpoint, 'p256dh'=>(string)($keys['p256dh'] ?? ''), 'auth'=>(string)($keys['auth'] ?? ''),
        'user_key'=>cds_push_user_key($user), 'username'=>(string)($user['username'] ?? ''),
        'name'=>(string)($user['name'] ?? ''), 'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
        'updated_at'=>date('c'), 'created_at'=>date('c'),
    ];
    if ($row['user_key'] === '' || !cds_push_subscription_valid($row)) return false;
    $rows = cds_push_json_rows(CDS_PUSH_SUBSCRIPTIONS_FILE); $found = false;
    foreach ($rows as &$old) if (hash_equals((string)($old['endpoint'] ?? ''), $endpoint)) {
        $row['created_at'] = $old['created_at'] ?? $row['created_at']; $old = $row; $found = true; break;
    }
    unset($old); if (!$found) $rows[] = $row;
    return cds_push_save_json(CDS_PUSH_SUBSCRIPTIONS_FILE, array_values($rows));
}
function cds_push_delete_subscription(string $endpoint, ?array $user = null): bool {
    $userKey = cds_push_user_key($user); $rows = cds_push_json_rows(CDS_PUSH_SUBSCRIPTIONS_FILE);
    $rows = array_values(array_filter($rows, fn($r)=>!(hash_equals((string)($r['endpoint'] ?? ''), $endpoint) && ($userKey === '' || ($r['user_key'] ?? '') === $userKey))));
    return cds_push_save_json(CDS_PUSH_SUBSCRIPTIONS_FILE, $rows);
}
function cds_push_current_device_count(?array $user = null): int {
    $key = cds_push_user_key($user); if ($key === '') return 0;
    return count(array_filter(cds_push_json_rows(CDS_PUSH_SUBSCRIPTIONS_FILE), fn($r)=>(string)($r['user_key'] ?? '') === $key));
}
function cds_push_add_notification(string $title, string $body, string $url, array $options = []): array {
    $rows = cds_push_json_rows(CDS_PUSH_NOTIFICATIONS_FILE);
    $expiresAt = trim((string)($options['expires_at'] ?? ''));
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiresAt)) $expiresAt .= ' 23:59:59';
    $row = [
        'id'=>'push_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)), 'title'=>trim($title),
        'body'=>trim($body), 'url'=>$url !== '' ? $url : BASE_URL . 'admin.php',
        'source_id'=>(string)($options['source_id'] ?? ''), 'level'=>(string)($options['level'] ?? 'normal'),
        'audience'=>(array)($options['audience'] ?? ['all']), 'created_at'=>date('c'),
        'expires_at'=>$expiresAt, 'created_by'=>(string)($_SESSION['cds_user']['name'] ?? 'Hệ thống'),
    ];
    array_unshift($rows, $row);
    return cds_push_save_json(CDS_PUSH_NOTIFICATIONS_FILE, array_slice($rows, 0, 500)) ? $row : [];
}
function cds_push_visible_to(array $notification, array $user): bool {
    $audience = (array)($notification['audience'] ?? ['all']);
    if (!$audience || in_array('all', $audience, true)) return true;
    $keys = array_filter([cds_push_user_key($user), (string)($user['username'] ?? ''), (string)($user['role'] ?? '')]);
    return (bool)array_intersect($audience, $keys);
}
function cds_push_notifications_for_user(array $user, int $limit = 30): array {
    $now = time(); $rows = [];
    foreach (cds_push_json_rows(CDS_PUSH_NOTIFICATIONS_FILE) as $row) {
        $expiry = strtotime((string)($row['expires_at'] ?? ''));
        if ($expiry !== false && $expiry < $now) continue;
        if (cds_push_visible_to($row, $user)) $rows[] = $row;
        if (count($rows) >= $limit) break;
    }
    return $rows;
}
function cds_push_read_ids(array $user): array {
    $all = cds_push_json_rows(CDS_PUSH_READ_FILE);
    return array_values(array_filter((array)($all[cds_push_user_key($user)] ?? []), 'is_string'));
}
function cds_push_unread_count(array $user): int {
    $read = array_flip(cds_push_read_ids($user)); $count = 0;
    foreach (cds_push_notifications_for_user($user, 200) as $row) if (!isset($read[(string)($row['id'] ?? '')])) $count++;
    return $count;
}
function cds_push_mark_read(array $user, string $id = '', bool $all = false): bool {
    $key = cds_push_user_key($user); if ($key === '') return false;
    $rows = cds_push_json_rows(CDS_PUSH_READ_FILE); $read = array_flip((array)($rows[$key] ?? []));
    if ($all) foreach (cds_push_notifications_for_user($user, 500) as $row) $read[(string)$row['id']] = true;
    elseif ($id !== '') $read[$id] = true;
    $rows[$key] = array_slice(array_keys($read), -1000);
    return cds_push_save_json(CDS_PUSH_READ_FILE, $rows);
}
function cds_push_hkdf_extract(string $salt, string $ikm): string { return hash_hmac('sha256', $ikm, $salt, true); }
function cds_push_hkdf_expand(string $prk, string $info, int $length): string {
    $out = ''; $last = ''; $counter = 1;
    while (strlen($out) < $length) { $last = hash_hmac('sha256', $last . $info . chr($counter++), $prk, true); $out .= $last; }
    return substr($out, 0, $length);
}
function cds_push_raw_public_pem(string $raw): string {
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
function cds_push_encrypt(string $payload, string $clientPublic, string $auth): ?string {
    if (!function_exists('openssl_pkey_derive') || !function_exists('openssl_encrypt')) return null;
    $serverKey = openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_EC, 'curve_name'=>'prime256v1']);
    $clientKey = openssl_pkey_get_public(cds_push_raw_public_pem($clientPublic));
    if (!$serverKey || !$clientKey) return null;
    $shared = openssl_pkey_derive($clientKey, $serverKey, 32);
    $details = openssl_pkey_get_details($serverKey); $serverPublic = is_array($details) ? cds_push_public_from_details($details) : '';
    if ($shared === false || $serverPublic === '') return null;
    $prkKey = cds_push_hkdf_extract($auth, $shared);
    $ikm = cds_push_hkdf_expand($prkKey, "WebPush: info\0" . $clientPublic . $serverPublic, 32);
    $salt = random_bytes(16); $prk = cds_push_hkdf_extract($salt, $ikm);
    $cek = cds_push_hkdf_expand($prk, "Content-Encoding: aes128gcm\0", 16);
    $nonce = cds_push_hkdf_expand($prk, "Content-Encoding: nonce\0", 12);
    $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($cipher === false) return null;
    return $salt . pack('N', 4096) . chr(strlen($serverPublic)) . $serverPublic . $cipher . $tag;
}
function cds_push_der_signature_to_raw(string $der, int $size = 32): string {
    $offset = 2;
    if ((ord($der[1]) & 0x80) !== 0) $offset = 2 + (ord($der[1]) & 0x7f);
    if (($der[$offset] ?? '') !== "\x02") return '';
    $rLen = ord($der[++$offset]); $r = substr($der, ++$offset, $rLen); $offset += $rLen;
    if (($der[$offset] ?? '') !== "\x02") return '';
    $sLen = ord($der[++$offset]); $s = substr($der, ++$offset, $sLen);
    return str_pad(ltrim($r, "\0"), $size, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), $size, "\0", STR_PAD_LEFT);
}
function cds_push_vapid_headers(string $endpoint): array {
    $keys = cds_push_keys(); if (empty($keys['private_pem']) || empty($keys['public_key'])) return [];
    $parts = parse_url($endpoint); if (empty($parts['scheme']) || empty($parts['host'])) return [];
    $aud = $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '');
    $head = cds_push_b64url_encode(json_encode(['typ'=>'JWT','alg'=>'ES256']));
    $claim = cds_push_b64url_encode(json_encode(['aud'=>$aud,'exp'=>time()+43200,'sub'=>'mailto:admin@noitruxinman.edu.vn']));
    $input = $head . '.' . $claim;
    if (!openssl_sign($input, $der, $keys['private_pem'], OPENSSL_ALGO_SHA256)) return [];
    $signature = cds_push_der_signature_to_raw($der); if ($signature === '') return [];
    return ['Authorization: vapid t=' . $input . '.' . cds_push_b64url_encode($signature) . ', k=' . $keys['public_key']];
}
function cds_push_prepare_request(array $subscription, array $notification): ?array {
    if (!cds_push_subscription_valid($subscription)) return null;
    $payload = json_encode([
        'title'=>$notification['title'] ?? 'CDS – Thông báo mới', 'body'=>$notification['body'] ?? '',
        'url'=>$notification['url'] ?? BASE_URL . 'admin.php', 'id'=>$notification['id'] ?? '',
        'level'=>$notification['level'] ?? 'normal', 'badgeCount'=>1,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $body = cds_push_encrypt((string)$payload, cds_push_b64url_decode((string)$subscription['p256dh']), cds_push_b64url_decode((string)$subscription['auth']));
    $vapid = cds_push_vapid_headers((string)$subscription['endpoint']);
    if ($body === null || !$vapid) return null;
    $urgency = ($notification['level'] ?? '') === 'urgent' ? 'high' : 'normal';
    return ['url'=>$subscription['endpoint'], 'body'=>$body, 'headers'=>array_merge($vapid, ['Content-Encoding: aes128gcm','Content-Type: application/octet-stream','TTL: 86400','Urgency: ' . $urgency])];
}
function cds_push_send(array $notification, array $audience = ['all']): array {
    if (!function_exists('curl_multi_init')) return ['sent'=>0,'failed'=>0,'unsupported'=>true];
    $subscriptions = cds_push_json_rows(CDS_PUSH_SUBSCRIPTIONS_FILE); $targets = [];
    foreach ($subscriptions as $index=>$sub) {
        if ($audience && !in_array('all', $audience, true) && !array_intersect($audience, [(string)($sub['user_key']??''),(string)($sub['username']??'')])) continue;
        $request = cds_push_prepare_request($sub, $notification); if ($request) $targets[$index] = $request;
    }
    if (!$targets) return ['sent'=>0,'failed'=>0,'devices'=>0];
    $multi = curl_multi_init(); $handles = [];
    foreach ($targets as $index=>$request) {
        $ch = curl_init($request['url']);
        curl_setopt_array($ch, [CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$request['body'],CURLOPT_HTTPHEADER=>$request['headers'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12]);
        curl_multi_add_handle($multi, $ch); $handles[$index] = $ch;
    }
    do { $status = curl_multi_exec($multi, $active); if ($active) curl_multi_select($multi, 1.0); } while ($active && $status === CURLM_OK);
    $sent=0; $failed=0; $expired=[];
    foreach ($handles as $index=>$ch) {
        $code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        if ($code>=200 && $code<300) $sent++; else { $failed++; if (in_array($code,[404,410],true)) $expired[]=$subscriptions[$index]['endpoint']??''; }
        curl_multi_remove_handle($multi,$ch); curl_close($ch);
    }
    curl_multi_close($multi);
    if ($expired) {
        $subscriptions=array_values(array_filter($subscriptions,fn($r)=>!in_array((string)($r['endpoint']??''),$expired,true)));
        cds_push_save_json(CDS_PUSH_SUBSCRIPTIONS_FILE,$subscriptions);
    }
    return ['sent'=>$sent,'failed'=>$failed,'devices'=>count($targets)];
}
function cds_push_publish(string $title, string $body, string $url, array $options = []): array {
    $notification = cds_push_add_notification($title, $body, $url, $options);
    if (!$notification) return ['notification'=>[],'sent'=>0,'failed'=>0,'devices'=>0,'saved'=>false];
    $result = cds_push_send($notification, (array)($options['audience'] ?? ['all']));
    return ['notification'=>$notification,'saved'=>true] + $result;
}

function cds_push_dashboard_source_id(array $item): string {
    $module = trim((string)($item['_dashboard_module'] ?? $item['module'] ?? 'dashboard')) ?: 'dashboard';
    $id = trim((string)($item['id'] ?? $item['uuid'] ?? ''));
    if ($id === '') {
        $identity = implode('|', [
            (string)($item['title'] ?? $item['name'] ?? $item['content'] ?? ''),
            (string)($item['_dashboard_start'] ?? $item['start_date'] ?? $item['date'] ?? ''),
            (string)($item['_dashboard_end'] ?? $item['due_date'] ?? $item['end_date'] ?? ''),
            (string)($item['url'] ?? $item['link'] ?? ''),
        ]);
        $id = substr(hash('sha256', $identity), 0, 24);
    }
    return 'dashboard:' . preg_replace('/[^a-z0-9_-]+/i', '-', $module) . ':' . $id;
}

/** Gửi đúng một lần cho mỗi nội dung mới xuất hiện trong bảng Tổng quan. */
function cds_push_sync_dashboard_feed(array $items, array $user): array {
    $lockPath = CDS_PUSH_DASHBOARD_SYNC_FILE . '.lock';
    if (!is_dir(dirname($lockPath))) @mkdir(dirname($lockPath), 0750, true);
    $lock = @fopen($lockPath, 'c');
    if (!$lock || !flock($lock, LOCK_EX)) { if ($lock) fclose($lock); return ['sent'=>0,'skipped'=>count($items)]; }
    $exists = is_file(CDS_PUSH_DASHBOARD_SYNC_FILE);
    $state = $exists ? cds_push_json_rows(CDS_PUSH_DASHBOARD_SYNC_FILE) : [];
    $seen = array_fill_keys(array_values(array_filter((array)($state['seen'] ?? []), 'is_string')), true);
    $sourceIds = [];
    foreach (cds_push_json_rows(CDS_PUSH_NOTIFICATIONS_FILE) as $notice) {
        $sourceId = trim((string)($notice['source_id'] ?? ''));
        if ($sourceId !== '') $sourceIds[$sourceId] = true;
    }
    $current = []; $sent = 0; $skipped = 0;
    foreach ($items as $item) {
        if (!is_array($item)) continue;
        $sourceId = cds_push_dashboard_source_id($item); $current[$sourceId] = true;
        if (!$exists || isset($seen[$sourceId]) || isset($sourceIds[$sourceId])) { $seen[$sourceId] = true; $skipped++; continue; }
        $title = trim((string)($item['title'] ?? $item['name'] ?? $item['content'] ?? 'Nội dung mới'));
        $detail = trim((string)($item['_dashboard_detail'] ?? 'Có nội dung mới trong Thông báo đang và sắp diễn ra.'));
        $url = trim((string)($item['url'] ?? $item['link'] ?? BASE_URL . 'admin.php')) ?: BASE_URL . 'admin.php';
        $kind = (string)($item['kind'] ?? 'notice');
        $audience = in_array($kind, ['task','salary','seniority'], true) ? [cds_push_user_key($user)] : ['all'];
        $result = cds_push_publish($title, $detail, $url, [
            'source_id'=>$sourceId, 'audience'=>$audience,
            'expires_at'=>(string)($item['_dashboard_end'] ?? ''), 'level'=>'normal',
        ]);
        $sent += (int)($result['sent'] ?? 0); $seen[$sourceId] = true;
    }
    foreach (array_keys($seen) as $sourceId) if (!isset($current[$sourceId]) && count($seen) > 1500) unset($seen[$sourceId]);
    cds_push_save_json(CDS_PUSH_DASHBOARD_SYNC_FILE, ['initialized_at'=>$state['initialized_at'] ?? date('c'),'updated_at'=>date('c'),'seen'=>array_slice(array_keys($seen), -1500)]);
    flock($lock, LOCK_UN); fclose($lock);
    return ['sent'=>$sent,'skipped'=>$skipped];
}

<?php
/** Local encrypted credential storage. No network login or plaintext HTTP endpoint. */
function cds_ioffice_vault_dir(): string
{
    $path = (string)getenv('CDS_IOFFICE_VAULT_DIR');
    if ($path === '' || $path[0] !== '/' || is_link($path)) {
        throw new RuntimeException('Cần cấu hình CDS_IOFFICE_VAULT_DIR ngoài thư mục web.');
    }
    $real = realpath($path);
    if ($real === false || !is_dir($real)) throw new RuntimeException('Kho chưa được khởi tạo trên hosting.');
    $roots = [dirname(__DIR__), $_SERVER['DOCUMENT_ROOT'] ?? ''];
    // Also reject a sibling directory inside a common shared-hosting public root.
    for ($parent = $real; $parent !== '/'; $parent = dirname($parent)) {
        if (in_array(basename($parent), ['public_html', 'www', 'htdocs'], true)) $roots[] = $parent;
    }
    foreach ($roots as $root) {
        $root = $root !== '' ? realpath($root) : false;
        if ($root !== false && ($real === $root || str_starts_with($real, rtrim($root, '/') . '/'))) {
            throw new RuntimeException('Kho phải nằm ngoài thư mục web.');
        }
    }
    clearstatcache(true, $real);
    if ((fileperms($real) & 0777) !== 0700 || !is_writable($real)) {
        throw new RuntimeException('Thư mục kho phải có quyền 0700 và cho phép ghi.');
    }
    if (!function_exists('sodium_crypto_secretbox')) throw new RuntimeException('Hosting cần bật PHP Sodium.');
    return $real;
}

function cds_ioffice_private_file(string $path): string
{
    clearstatcache(true, $path);
    if (is_link($path) || !is_file($path) || (fileperms($path) & 0777) !== 0600) {
        throw new RuntimeException('Tệp kho không hợp lệ hoặc chưa có quyền 0600.');
    }
    $data = @file_get_contents($path);
    if (!is_string($data)) throw new RuntimeException('Không đọc được kho bảo mật.');
    return $data;
}

function cds_ioffice_vault_key(string $dir): string
{
    $key = cds_ioffice_private_file($dir . '/master.key');
    if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('Khóa kho không hợp lệ.');
    return $key;
}

function cds_ioffice_vault_write(string $path, string $contents): void
{
    if (is_link($path)) throw new RuntimeException('Không ghi vào liên kết tệp.');
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(12));
    $mask = umask(0077);
    try {
        $stream = @fopen($tmp, 'xb');
        if ($stream === false) throw new RuntimeException('Không tạo được tệp kho.');
        try {
            if (fwrite($stream, $contents) !== strlen($contents) || !fflush($stream)) {
                throw new RuntimeException('Không ghi được tệp kho.');
            }
        } finally { fclose($stream); }
        if (!chmod($tmp, 0600) || !rename($tmp, $path)) throw new RuntimeException('Không lưu được kho.');
    } finally {
        if (is_file($tmp)) @unlink($tmp);
        umask($mask);
    }
}

/** Initialization is deliberately CLI-only and never replaces an existing key. */
function cds_ioffice_vault_init(): void
{
    if (PHP_SAPI !== 'cli') throw new RuntimeException('Chỉ khởi tạo kho bằng Terminal cPanel.');
    $dir = cds_ioffice_vault_dir();
    $mask = umask(0077);
    try {
        $stream = @fopen($dir . '/master.key', 'xb');
        if ($stream === false) throw new RuntimeException('Khóa đã tồn tại hoặc không tạo được khóa. Không ghi đè.');
        try {
            $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
            if (fwrite($stream, $key) !== strlen($key) || !fflush($stream)) throw new RuntimeException('Không ghi được khóa.');
        } finally { fclose($stream); }
    } finally { umask($mask); }
}

function cds_ioffice_vault_save(string $username, string $password): void
{
    $username = trim($username);
    if ($username === '' || strlen($username) > 250 || $password === '' || strlen($password) > 4096) {
        throw new RuntimeException('Tên truy cập hoặc mật khẩu không hợp lệ.');
    }
    $dir = cds_ioffice_vault_dir();
    $key = cds_ioffice_vault_key($dir);
    $plain = json_encode(['origin'=>'https://vpdttq.vnptioffice.vn', 'username'=>$username,
        'password'=>$password, 'updated_at'=>gmdate('c')], JSON_THROW_ON_ERROR);
    try {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = sodium_crypto_secretbox($plain, $nonce, $key);
        cds_ioffice_vault_write($dir . '/account.enc', 'CDSIO1:' . base64_encode($nonce . $encrypted));
    } finally { sodium_memzero($plain); sodium_memzero($key); }
}

/** Only internal server-side code may use this; never echo or log the result. */
function cds_ioffice_vault_read(): ?array
{
    $dir = cds_ioffice_vault_dir();
    $key = cds_ioffice_vault_key($dir);
    try {
        if (!file_exists($dir . '/account.enc') && !is_link($dir . '/account.enc')) return null;
        $raw = cds_ioffice_private_file($dir . '/account.enc');
        $bytes = str_starts_with($raw, 'CDSIO1:') ? base64_decode(substr($raw, 7), true) : false;
        if ($bytes === false || strlen($bytes) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
            throw new RuntimeException('Dữ liệu kho không hợp lệ.');
        }
        $plain = sodium_crypto_secretbox_open(substr($bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $key);
        if ($plain === false) throw new RuntimeException('Không giải mã được kho; kiểm tra khóa và bản sao lưu.');
        try {
            $record = json_decode($plain, true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($record) || ($record['origin'] ?? '') !== 'https://vpdttq.vnptioffice.vn'
                || !is_string($record['username'] ?? null) || !is_string($record['password'] ?? null)) {
                throw new RuntimeException('Tài khoản trong kho không hợp lệ.');
            }
            return $record;
        } finally { sodium_memzero($plain); }
    } finally { sodium_memzero($key); }
}

function cds_ioffice_vault_status(): array
{
    try {
        $record = cds_ioffice_vault_read();
        $at = $record['updated_at'] ?? '';
        if ($record !== null) sodium_memzero($record['password']);
        return ['ready'=>true, 'saved'=>$record !== null, 'updated_at'=>$at, 'message'=>'Kho đã sẵn sàng.'];
    } catch (Throwable $e) {
        return ['ready'=>false, 'saved'=>false, 'updated_at'=>'', 'message'=>$e instanceof RuntimeException
            ? $e->getMessage() : 'Không đọc được kho bảo mật.'];
    }
}

<?php
require_once __DIR__ . '/../includes/ioffice_vault.php';
function check($ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function rejected(callable $fn): void {
    try { $fn(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException('Expected fail-closed rejection');
}
function remove_fixture(string $dir): void {
    foreach (scandir($dir) as $name) {
        if ($name === '.' || $name === '..') continue;
        $p = $dir . '/' . $name;
        if (is_dir($p) && !is_link($p)) remove_fixture($p); else unlink($p);
    }
    rmdir($dir);
}
$root = sys_get_temp_dir() . '/cds-vault-test-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
mkdir($root . '/vault', 0700);
putenv('CDS_IOFFICE_VAULT_DIR=' . $root . '/vault');
try {
    check(!cds_ioffice_vault_status()['ready'], 'Missing key must not claim readiness');
    cds_ioffice_vault_init();
    check(cds_ioffice_vault_read() === null, 'No account before input');
    $key = file_get_contents($root . '/vault/master.key');
    rejected(fn()=>cds_ioffice_vault_init());
    check(file_get_contents($root . '/vault/master.key') === $key, 'Init never rotates key');
    $password = 'fake-test-only-❄-! ';
    cds_ioffice_vault_save(' example.user ', $password);
    $record = cds_ioffice_vault_read();
    check($record['username'] === 'example.user' && $record['password'] === $password, 'Round trip preserves password bytes');
    $first = file_get_contents($root . '/vault/account.enc');
    check(!str_contains($first, $password) && !str_contains($first, 'example.user'), 'Account and password encrypted');
    check(cds_ioffice_vault_status()['saved'] && !isset(cds_ioffice_vault_status()['password']), 'Status contains no secret');
    cds_ioffice_vault_save('example.user', $password);
    $second = file_get_contents($root . '/vault/account.enc');
    check($second !== $first, 'Fresh nonce each save');
    check((fileperms($root . '/vault/account.enc') & 0777) === 0600, 'Ciphertext permissions');
    check((fileperms($root . '/vault/master.key') & 0777) === 0600, 'Key permissions');
    check(count(glob($root . '/vault/*.tmp.*')) === 0, 'No plaintext or temporary remnants');
    rejected(fn()=>cds_ioffice_vault_save('', 'x'));
    rejected(fn()=>cds_ioffice_vault_save('u', ''));
    check(file_get_contents($root . '/vault/account.enc') === $second, 'Invalid input never overwrites');
    $bytes = base64_decode(substr($second, 7)); $bytes[30] = chr(ord($bytes[30]) ^ 1);
    file_put_contents($root . '/vault/account.enc', 'CDSIO1:' . base64_encode($bytes));
    rejected(fn()=>cds_ioffice_vault_read());
    file_put_contents($root . '/vault/account.enc', $second);
    file_put_contents($root . '/vault/master.key', random_bytes(32));
    rejected(fn()=>cds_ioffice_vault_read());
    file_put_contents($root . '/vault/master.key', $key);
    chmod($root . '/vault/account.enc', 0644); rejected(fn()=>cds_ioffice_vault_read());
    chmod($root . '/vault/account.enc', 0600);
    unlink($root . '/vault/account.enc');
    file_put_contents($root . '/target', 'unchanged');
    symlink($root . '/target', $root . '/vault/account.enc');
    rejected(fn()=>cds_ioffice_vault_save('u', 'p'));
    check(file_get_contents($root . '/target') === 'unchanged', 'Symlink target untouched');
    unlink($root . '/vault/account.enc');
    $_SERVER['DOCUMENT_ROOT'] = $root;
    rejected(fn()=>cds_ioffice_vault_dir());
    unset($_SERVER['DOCUMENT_ROOT']);
    mkdir($root . '/public_html', 0700); mkdir($root . '/public_html/vault', 0700);
    putenv('CDS_IOFFICE_VAULT_DIR=' . $root . '/public_html/vault');
    rejected(fn()=>cds_ioffice_vault_dir());
    putenv('CDS_IOFFICE_VAULT_DIR=' . dirname(__DIR__));
    rejected(fn()=>cds_ioffice_vault_dir());
    putenv('CDS_IOFFICE_VAULT_DIR=relative/path');
    rejected(fn()=>cds_ioffice_vault_dir());
    putenv('CDS_IOFFICE_VAULT_DIR=' . $root . '/vault');
    chmod($root . '/vault', 0755); rejected(fn()=>cds_ioffice_vault_dir());
    chmod($root . '/vault', 0700);
    echo "PASS: encryption, tampering, key retention, permissions, path isolation, symlink and input rejection\n";
} finally { putenv('CDS_IOFFICE_VAULT_DIR'); remove_fixture($root); }

<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$manifest = [
    'id' => BASE_URL,
    'name' => CDS_TITLE,
    'short_name' => CDS_SHORT_TITLE,
    'description' => CDS_DESCRIPTION,
    'start_url' => BASE_URL . 'admin.php?source=pwa',
    'scope' => BASE_URL,
    'display' => 'standalone',
    'background_color' => (string)cds_school_config('pwa.background_color', '#f4f7fb'),
    'theme_color' => (string)cds_school_config('pwa.theme_color', '#0f4c81'),
    'icons' => [
        [
            'src' => BASE_URL . ltrim((string)cds_school_config('pwa.icon_192', 'assets/icons/cds-192.png'), '/'),
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
        [
            'src' => BASE_URL . ltrim((string)cds_school_config('pwa.icon_512', 'assets/icons/cds-512.png'), '/'),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

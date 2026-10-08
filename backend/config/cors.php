<?php

/**
 * CORS untuk SPA beda-origin.
 * PENTING: `localhost` dan `127.0.0.1` adalah origin BERBEDA bagi peramban,
 * jadi keduanya harus didaftarkan. `exposed_headers` wajib memuat
 * Content-Disposition agar JavaScript dapat membaca nama berkas saat unduh PDF/Excel.
 */
$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173,http://127.0.0.1:5173'))
)));

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => ['Content-Disposition'],
    'max_age' => 0,
    'supports_credentials' => false,
];

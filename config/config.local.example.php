<?php
/**
 * Helyi / szerverfüggő felülírások.
 * Másold erre: config.local.php (ez ne menjen gitbe).
 */
return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'alatinfo',
    'DB_USER' => 'alatinfo',
    'DB_PASS' => 'jelszo',
    'DB_CHARSET' => 'utf8mb4',

    // Ha a projekt alkönyvtárban fut: pl. '/Alatinfo'
    'BASE_URL' => '',

    // Opcionális: ha máshova szeretnéd az uploadokat
    // 'UPLOAD_PATH' => __DIR__ . '/../uploads/szamlak',
    // 'UPLOAD_URL' => '/uploads/szamlak',

    'SITE_NAME' => 'Latinfo.hu',
    'APP_TIMEZONE' => 'Europe/Budapest',
    'APP_DISPLAY_ERRORS' => '1',

    // Min. 32 karakter, ajánlott 64 hex
    'EMAIL_ENCRYPT_KEY' => 'change-this-to-a-random-secret-key',
];

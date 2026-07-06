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

    // Ha a projekt alkönyvtárban fut: pl. '/Alatinfo' (vezető perjel, domain nélkül).
    // Üresen hagyva a rendszer megpróbálja kitalálni a SCRIPT_NAME / REQUEST_URI alapján (/nextgen/ vagy /lanueva/ előtti rész).
    // Ne állítsd '/nextgen'-re, ha a La nueva a /lanueva/ alatt van (testvér mappa) – ilyenkor BASE_URL maradjon '' vagy a közös szülő útvonal.
    'BASE_URL' => '',

    // Backoffice URL szegmens (alap: nextgen). Ha a BASE_URL már .../nextgen-re végződik, automatikusan nem duplázódik.
    // 'NEXTGEN_WEB' => '/nextgen',

    // Opcionális: ha máshova szeretnéd az uploadokat (alapértelmezés: nextgen/core/config.php)
    // 'UPLOAD_PATH' => __DIR__ . '/../uploads/szamlak',
    // 'UPLOAD_URL' => '/nextgen/uploads/szamlak',

    // Nyilvános naptár: /{EVENTS_HOME_PATH}/ (alap: events)
    // 'EVENTS_HOME_PATH' => 'events',

    // Nyilvános esemény: /{EVENTS_PUBLIC_PATH}/{slug}/ (alap: event)
    // 'EVENTS_PUBLIC_PATH' => 'event',

    // Esemény megjelenítő: logó és lábléc „főoldal” link (alap: https://latinfo.hu/)
    // 'LATINFO_PUBLIC_HOME_URL' => 'http://localhost/wordpress',

    'SITE_NAME' => 'Latinfo.hu',
    'APP_TIMEZONE' => 'Europe/Budapest',
    'APP_DISPLAY_ERRORS' => '1',

    // Min. 32 karakter, ajánlott 64 hex
    'EMAIL_ENCRYPT_KEY' => 'change-this-to-a-random-secret-key',

    // Google Drive mentés – OAuth alkalmazás (Web kliens a Cloud Console-ból)
    // A felhasználói token nem ide kerül: a mentés oldalon Google-bejelentkezés kell.
    // 'GOOGLE_DRIVE_OAUTH_CLIENT_ID' => '....apps.googleusercontent.com',
    // 'GOOGLE_DRIVE_OAUTH_CLIENT_SECRET' => 'GOCSPX-...',
    // 'GOOGLE_DRIVE_BACKUP_FOLDER_ID' => '1BOBSMtZDB10LWKNcJxDWtq6AnFx4W9mJ',

    // GA4 mérőazonosító — nyilvános esemény oldalak (üres string = kikapcsolva)
    // 'GA4_MEASUREMENT_ID' => 'G-RCTY9NEJRJ',

    // Cron – központi ütemező (nextgen/cron/run.php)
    // 'CRON_TOKEN' => 'állíts-be-erős-véletlen-token-min-32-karakter',
    // 'CRON_ENABLED' => true,
    // 'CRON_LOG_PATH' => '', // alap: nextgen/data/cron/cron.log
];

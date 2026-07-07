<?php
declare(strict_types=1);

/**
 * PM Tools – oldal metaadatok (név, mire jó).
 * Kulcs: webes PHP útvonal, pl. /nextgen/admin/backup/index.php
 *
 * @return array<string, array{display_name:string, purpose:string}>
 */
function pm_tools_page_catalog(): array
{
    return [
        '/nextgen/index.php' => [
            'display_name' => 'Finance főoldal',
            'purpose' => 'Backoffice Finance dashboard – szervezők, pénzügyi áttekintés.',
        ],
        '/nextgen/apps.php' => [
            'display_name' => 'Alkalmazások',
            'purpose' => 'Alkalmazásválasztó: Finance, Event Admin, NextGen.',
        ],
        '/nextgen/login.php' => [
            'display_name' => 'Admin bejelentkezés',
            'purpose' => 'Latinfo.hu backoffice admin bejelentkezés.',
        ],
        '/nextgen/admin/pm/index.php' => [
            'display_name' => 'PM Tools',
            'purpose' => 'Prototípus jegyzetek PHP oldalakhoz – központi admin felület.',
        ],
        '/nextgen/admin/backup/index.php' => [
            'display_name' => 'Drive mentés',
            'purpose' => 'Adatbázis és fájlok mentése Google Drive-ra.',
        ],
        '/nextgen/admin/partnerek/index.php' => [
            'display_name' => 'Partnerek',
            'purpose' => 'Partner fiókok listázása és kezelése.',
        ],
        '/nextgen/admin/partnerek/uzenetek.php' => [
            'display_name' => 'Partner üzenetek',
            'purpose' => 'Partner portál üzenőfal – admin inbox és válaszok.',
        ],
        '/nextgen/admin/adminok/index.php' => [
            'display_name' => 'Adminok',
            'purpose' => 'Backoffice admin felhasználók kezelése.',
        ],
        '/nextgen/admin/log.php' => [
            'display_name' => 'Logok',
            'purpose' => 'Rendszeresemény napló megtekintése.',
        ],
        '/nextgen/admin/email/index.php' => [
            'display_name' => 'E-mail sablonok',
            'purpose' => 'E-mail sablonok listázása és szerkesztése.',
        ],
        '/nextgen/admin/exporter/index.php' => [
            'display_name' => 'Exporter',
            'purpose' => 'Adatexport profilok és futtatás.',
        ],
        '/nextgen/google_drive_beallitas.php' => [
            'display_name' => 'Google Drive fiók',
            'purpose' => 'Google fiók összekapcsolása a Drive mentéshez.',
        ],
        '/nextgen/events/events_admin.php' => [
            'display_name' => 'Események lista',
            'purpose' => 'Event Admin – események listája és kezelése.',
        ],
        '/nextgen/events/events_naptar.php' => [
            'display_name' => 'Esemény naptár',
            'purpose' => 'Események naptár nézetben.',
        ],
        '/nextgen/events/letrehoz.php' => [
            'display_name' => 'Új esemény',
            'purpose' => 'Új esemény létrehozása.',
        ],
        '/nextgen/events/venues.php' => [
            'display_name' => 'Helyszínek',
            'purpose' => 'Esemény helyszínek kezelése.',
        ],
        '/nextgen/events/organizers.php' => [
            'display_name' => 'Szervezők (events)',
            'purpose' => 'Esemény szervezők listája az Event Adminban.',
        ],
        '/nextgen/config/cimkek.php' => [
            'display_name' => 'Címkék',
            'purpose' => 'NextGen config – címkék kezelése.',
        ],
        '/nextgen/config/lanueva.php' => [
            'display_name' => 'LaNueva',
            'purpose' => 'LaNueva táblázat konfiguráció.',
        ],
    ];
}

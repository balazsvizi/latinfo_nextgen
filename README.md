# Latinfo.hu Backoffice

Táncosoknak szóló weboldal backoffice része. PHP + MySQL.

## Követelmények

- PHP 7.4+ (PDO, session, password_hash)
- MySQL 5.7+ / MariaDB 10.2+
- **Apache:** a gyökér `.htaccess` a `/` kérést a `nextgen/index.php`-re irányítja (`DirectoryIndex`). **Nginx** esetén állíts be ehhez hasonló index / `try_files` szabályt, vagy használd közvetlenül a `/nextgen/index.php` URL-t kezdőlapnak.

## Telepítés

1. **Adatbázis létrehozása**
   - MySQL-ben hozz létre egy `alatinfo` adatbázist (utf8mb4).
   - Importáld a sémát:
     ```bash
     mysql -u root -p alatinfo < nextgen/database/schema.sql
     ```
   - Laragon: Hegyezd be a MySQL-t, majd phpMyAdmin-ban hozd létre az `alatinfo` adatbázist és futtasd a `nextgen/database/schema.sql` tartalmát.

2. **Konfiguráció**
   - A szerverfüggő beállításokat a `nextgen/core/config.local.php` fájlban add meg (mintát ad: `nextgen/core/config.local.example.php`).
   - Alternatíva: környezeti változók (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL`, `EMAIL_ENCRYPT_KEY`, stb.).
   - A `nextgen/core/config.php` maradjon közös, környezetfüggetlen alapkonfig.

3. **Belépés**
   - Belépési oldal: **/nextgen/login.php** (pl. `https://latinfo.hu/nextgen/login.php`) – a régi **/belepes** ugyanazt a lapot tölti be.
   - Alapértelmezett bejelentkezés: **felhasználónév:** `admin`, **jelszó:** `password`
   - Az első belépés után érdemes új admint létrehozni és a defaultot letiltani, vagy jelszót módosítani (jelen verzióban nincs jelszó módosítás, csak új admin).

## Funkciók

- **Szervezők**: név, címkék (szabadon felvett, újrahasznosítható), megjegyzések (időbélyeges log), szervező log (történések)
- **Kontaktok**: név, e-mail, telefon, FB, egyéb; megjegyzések; N:N kapcsolat szervezőkkel
- **Számlázási címek**: szervezőnként több cím, egy primary (alapértelmezett)
- **Számlák**: szám, dátum, összeg, belső megjegyzés, státusz (generált / kiküldve / kiegyenlítve / egyéb), több fájl csatolás
- **Számlázandó**: időszak (év/hó, több hónap kijelölhető), összeg, megjegyzés
- **Adminok**: név, felhasználónév, jelszó (bcrypt), **szint** (Admin / Superadmin); admin felvétele és letiltása. Az **Admin** menü és az admin kezelő oldalak csak **Superadmin**nak látszanak/jelentkeznek.
- **Rendszer log**: minden entitás létrehozás/módosítás rögzítése

**Meglévő telepítés:** ha már fut az adatbázis, futtasd egyszer a migrációt (admin szint oszlop + minden meglévő user superadmin):  
`mysql -u root -p alatinfo < nextgen/database/migration_admin_szint.sql`

## Mappa struktúra

A projekt gyökérben két fő mappa: **`nextgen/`** (backoffice, adatbázis-sémák, core config) és **`lanueva/`** (nyilvános La nueva landing + statikus assetek).

```
├── nextgen/              # Backoffice, core, migrációk, feltöltések
│   ├── core/             # config.php, database.php, config.local.example
│   ├── database/         # schema.sql, migrációk
│   ├── config/           # címkék, levélsablonok, La nueva admin lista
│   ├── includes/, partials/, admin/, contacts/, …
│   └── login.php, logout.php, index.php   # belépés, kijelentkezés, dashboard
├── lanueva/              # Nyilvános landing (/lanueva/)
│   ├── landing_public.php
│   └── assets/           # landing.css, képek (OG)
├── .htaccess             # Apache: DirectoryIndex → nextgen/index.php (a / címhez)
└── composer.json
```

## Nyelv

Minden felület és adatbázis mező magyar nyelvű.

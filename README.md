# Latinfo.hu Backoffice

Táncosoknak szóló weboldal backoffice része. PHP + MySQL.

## Követelmények

- PHP 7.4+ (PDO, session, password_hash)
- MySQL 5.7+ / MariaDB 10.2+

## Telepítés

1. **Adatbázis létrehozása**
   - MySQL-ben hozz létre egy `alatinfo` adatbázist (utf8mb4).
   - Importáld a sémát:
     ```bash
     mysql -u root -p alatinfo < database/schema.sql
     ```
   - Laragon: Hegyezd be a MySQL-t, majd phpMyAdmin-ban hozd létre az `alatinfo` adatbázist és futtasd a `database/schema.sql` tartalmát.

2. **Konfiguráció**
   - A szerverfüggő beállításokat a `config/config.local.php` fájlban add meg (mintát ad: `config/config.local.example.php`).
   - Alternatíva: környezeti változók (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `BASE_URL`, `EMAIL_ENCRYPT_KEY`, stb.).
   - A `config/config.php` maradjon közös, környezetfüggetlen alapkonfig.

3. **Belépés**
   - Belépési oldal: **/belepes** (pl. `https://latinfo.hu/belepes`)
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
`mysql -u root -p alatinfo < database/migration_admin_szint.sql`

## Mappa struktúra

```
Latinfo.hu/
├── config/          # config.php, database.php
├── database/        # schema.sql
├── includes/        # auth.php, functions.php
├── partials/        # header.php, footer.php
├── assets/css/      # style.css
├── szervezok/       # lista, megtekint, szerkeszt, letrehoz, kontakt hozzáadás/levétel
├── kontaktok/       # lista, megtekint, szerkeszt, letrehoz
├── cimek/           # számlázási címek (letrehoz, szerkeszt)
├── szamlak/         # számlák (letrehoz, megtekint, fájl letöltés)
├── szamlazando/     # számlázandó (letrehoz, szerkeszt)
├── adminok/         # admin lista, újonnan felvétel, letiltás/engedélyezés
├── uploads/szamlak/ # számla mellékletek (automatikusan létrejön)
├── index.php        # dashboard
├── belepes/index.php   # Belépés (URL: /belepes)
├── login.php           # Átirányít /belepes-re
├── logout.php
├── log.php          # rendszer log
├── cimkek.php       # címkék kezelése
└── README.md
```

## Nyelv

Minden felület és adatbázis mező magyar nyelvű.

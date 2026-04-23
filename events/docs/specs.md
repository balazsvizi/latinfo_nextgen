# Funkcionális specifikáció

> **Dokumentum célja:** egyértelmű, közös alap a fejlesztéshez, teszteléshez és átadáshoz.  
> **Státusz:** vázlat / jóváhagyásra vár / éles  
> **Verzió:** 0.1  
> **Utolsó módosítás:** _YYYY-MM-DD_

---

## 1. Összefoglaló

| Mező | Érték |
|------|--------|
| Rövid név | A Latinfo.hu új naptár modulja |
| Egy mondatos leírás | Táncos események gyűjteménye |
| Prioritás | Közepes |

---

## 2. Célok és nem célok

### 2.1 Üzleti / termék célok

Modern naprakész, felhasználóbarát naptár alkalmazás.


### 2.2 Explicit nem célok (out of scope)


---

## 3. Érintettek és szerepkörök

| Szerepkör | Felelősség | Kapcsolat |
|------------|------------|-----------|
| Termék / üzlet | Balázs | |
| Fejlesztés | Balázs | |
| Tesztelés | Balázs | |
| Üzemeltetés | Latinfo.hu csapat | |

---

## 4. Fogalomtár és rövidítések

| Kifejezés | Jelentés |
|-----------|----------|
| _pl. Esemény_ | _Definíció_ |

---

## 5. Hatókör és függőségek

### 5.1 Hatókör (scope)

- Ez egy új modul lesz, ami majd kiváltja az addig működő naptárt


### 5.2 Függőségek és előfeltételek

- Migráljuk majd go-live-kor az összes eseményt a régi naptárból

### 5.3 Érintett interfészek

- Még nincs

---

## 6. Felhasználói történetek és forgatókönyvek

### 6.1 Felhasználói történet (sablon)

> **Mint** Admin  
> **azt szeretném**, hogy a szervezők tudjanak új eseményeket felvinni és módosítani  
> **azért**, hogy az olvasók használják a naptárat.

**Elfogadási kritériumok (Given / When / Then):**

1. _Adott …, amikor …, akkor …_
2. …

### 6.2 Főbb felhasználási esetek (UC)

| Azonosító | Rövid leírás | Prioritás |
|-----------|--------------|-----------|
| UC-01 |Admin adminiosztrálja a rendszert | |
| UC-02 | Szervező felviszi az eseményeket| |
| UC-03 | Olvasó olvassa az eseményeket| |


---

## 7. Funkcionális követelmények

| ID | Követelmény | Részletek | Prioritás (MoSCoW) |
|----|--------------|-----------|---------------------|
| F-01 | Egyszerű működés|Egyszerúen működjön | Must |
| F-02 | Reszponzív| |  Must|
| F-03 | SEO és AI barát| |  Must|
| F-04 | Gyors|Gyorsan működik |  Must|
| F-05 | Biztonságos|nem lehet feltörni |  Must|

---

## 8. Nem funkcionális követelmények

| Terület | Követelmény | Mérhető cél (ha van) |
|---------|--------------|----------------------|
| Teljesítmény |Gyors | 1 mp válaszidő |
| Rendelkezésre állás |non-stop | |
| Biztonság |Magas | |
| Adatvédelem | Nem releváns | |
| Akadálymentesség | Nem követelmény | |
| Böngésző / platform | Minden elterjedt| |

---

## 9. Adatmodell és állapotok (ha releváns)

# Events tábla
- ID (100000-el kezdődjön az újak esetén, az importált az lehet kisebb)
- event_name (Esemény neve)
- event_slug (egyedi url)
- event_content (leírás (HTML))
- created
- modified
- event_status
- event_start (DATETIME)
- event_end (DATETIME)
- event_allday
- event_cost_from 
- event_cost_to
- event_url
- event_LatinfohuPartner
- organizerID
- venueID



### 9.1 Entitások

- _Entitás neve, kulcs mezők, kapcsolatok_

### 9.2 Állapotgép / státuszok

| Státusz | Leírás | Ki indulhat / hova mehet |
|---------|--------|---------------------------|
| | | |

---

## 10. Felület és viselkedés (UI / UX)

- Event edit képernyőn az event összes adata
-- Content-hez HTML szerkesztő

- Majd a megjelenítés így lesz: /eventmappa/ez_az_event_címe

- A program fájlok a /events mappába kerüljenek

- Config
-- Eventmappa

- Stats

- Organizer dashboard

- Admin dashboard

- Logok

---


## 11. Naplózás, audit és monitoring

- Minden entitás mentését logoljuk
- Minden olvasó általi event megtekintést mérünk


## 12. Átmenet és élesítés

Most még megy az előző naptár, majd onnan migrálunk külső eszköz exporttal és importtal

---

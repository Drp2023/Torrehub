# SITE-MAP.md
## Torrehub — Oldal- és képernyőtérkép

**Verzió**: 1.0  
**Dátum**: 2026-08-28  
**Állapot**: Draft — URL struktúra megerősítése szükséges

---

## Jelölések

- 🌍 Publikus — mindenki láthatja
- 👤 Member — bejelentkezés szükséges
- 🏷️ Private Seller — eladói fiók szükséges
- 🏢 Business Seller — üzleti fiók szükséges
- 🔑 Admin — WordPress admin

---

## 1. PUBLIKUS OLDALAK

### 1.1 Főoldal
- **URL**: `/`
- **Hozzáférés**: 🌍 Mindenki
- **Komponensek**:
  - Hero / Keresősáv (kulcsszó + kategória + helyszín)
  - Kiemelt kategóriák rács (ikonokkal)
  - Legújabb / Kiemelt hirdetések (carousel vagy grid)
  - Statisztikák (hirdetések száma, kategóriák, helyszínek)
  - Hívó szöveg (CTA) eladóknak
  - Partnerek / Trust jelzők (opcionális)

### 1.2 Hirdetések listája (Archive)
- **URL**: `/listings/` (vagy WordPress taxonomy/archive URL)
- **Hozzáférés**: 🌍 Mindenki
- **Variánsok**:
  - Alap lista (szűrők nélkül)
  - Keresési eredmények (URL paraméterekkel: `?q=...&category=...&location=...`)
  - Kategória archive: `/listing-category/{slug}/`
  - Helyszín archive: `/listing-location/{slug}/`
  - Tag archive: `/listing-tag/{slug}/`
- **Komponensek**:
  - Oldalsó szűrő panel (desktop) / Pop-up szűrő (mobil)
  - Rendezési opciók (Legújabb / Legrégebbi / Ár növekvő / Ár csökkenő / Véletlenszerű)
  - Lista / Rács nézet váltó
  - Térkép nézet (toggle)
  - Hirdetés kártya lista
  - Lapozás (pagination)
  - Találatok száma

### 1.3 Hirdetés részletek (Single Listing)
- **URL**: `/listing/{slug}/`
- **Hozzáférés**: 🌍 Mindenki (korlátozott funkciókkal)
- **Szekciók** (jelenlegi rendszerből: `Shortcode.php`):
  - **Header** (`[cldirectory_listing_header_2]`): Banner kép, navigációs menü
  - **Heading** (`[cldirectory_listing_header]`): Logó, cím, kategória, ár, gombok, badge-ek
  - **Galéria**: Képek slideshow
  - **Tartalom terület** (accordion szekciók):
    - Leírás (`[cldirectory_listing_description]`)
    - Custom mezők / Amenities (`[cldirectory_listing_custom_fields]`)
    - ~~Étterem menü~~ — ⛔ DISABLED (enable_restaurant_listing: "")
    - Videó (`[cldirectory_listing_video]`)
    - Térkép (`[cldirectory_listing_map]`)
    - Értékelések (`[cldirectory_listing_review]`)
  - **Sidebar** (`[cldirectory_listing_sidebar]`):
    - Eladói info (Owner Info / Store Info, típustól függően)
    - Nyitvatartás (business hours)
    - Email form (kapcsolat)
    - Widget sidebar
- **Bejelentkezett felhasználó extra funkciók**:
  - Telefon megjelenítése gomb
  - WhatsApp gomb
  - Chat gomb
  - Kedvencekhez adás
  - ~~Összehasonlítás~~ — ⛔ DISABLED (enable_compare: "")

### 1.4 Üzlet lista (Store Directory) — ⛔ DISABLED

**Státusz**: Store modul KIKAPCSOLVA (`enable_store: ""`)  
**URL**: `/stores/` — nem aktív  
Teendő: Client döntés szükséges → lásd CLIENT-CONFIRMATION.md B1

### 1.5 Üzlet részletek (Single Store) — ⛔ DISABLED

**Státusz**: Store modul KIKAPCSOLVA  
**URL**: `/store/{slug}/` — nem aktív

### 1.6 Bejelentkezés
- **URL**: `/bejelentkezes/` (slug kliens által megerősítendő)
- **Hozzáférés**: 🌍 Nem bejelentkezett (átirányítás ha már belépett)
- **Komponensek**:
  - Email mező
  - Jelszó mező
  - "Maradj bejelentkezve" checkbox
  - Bejelentkezés gomb
  - "Elfelejtett jelszó" link
  - "Nincs fiókod? Regisztrálj" link

### 1.7 Regisztráció
- **URL**: `/regisztracio/` (slug kliens által megerősítendő)
- **Hozzáférés**: 🌍 Nem bejelentkezett
- **Komponensek**:
  - Fiók típus választó (3 opció vizuálisan elkülönítve):
    - MEMBER
    - PRIVATE SELLER (NIE szükséges)
    - BUSINESS SELLER (NIF szükséges)
  - Dinamikus form (típusonként eltérő mezők)
  - Feltételek elfogadása checkbox
  - Regisztrálás gomb
  - "Már van fiókod? Lépj be" link

### 1.8 Jelszó visszaállítás
- **URL**: `/elfelejtett-jelszo/`
- **Hozzáférés**: 🌍 Mindenki
- **Lépések**:
  1. Email cím megadása
  2. Email visszaigazolás küldése
  3. Link alapján jelszó visszaállítás form

### 1.9 Árszabás / Membership tervek — ⛔ DISABLED

**Státusz**: Membership modul KIKAPCSOLVA (`enable: ""`)  
**URL**: `/arak/` — nem aktív, nincs konfigurált csomag  
Teendő: Client döntés szükséges → lásd CLIENT-CONFIRMATION.md C1

### 1.10 Keresés / Ajánlatok keresése
- **URL**: `/search/` (opcionális külön oldal — lehet integrálva az archive-ba)
- **Hozzáférés**: 🌍 Mindenki

### 1.11 Statikus oldalak
- **URL**: Kliens által meghatározandó
- **Tartalom**:
  - Rólunk (`/rolunk/`)
  - Kapcsolat (`/kapcsolat/`)
  - Adatvédelmi irányelvek (`/adatvedelmi-iranyelvek/`)
  - Felhasználási feltételek (`/felhasznalasi-feltetelek/`)
  - Gyik / FAQS (opcionális)

---

## 2. MEMBER DASHBOARD

**Alap URL**: `/fiokom/` (My Account page — `[rtcl_my_account]` shortcode)

### 2.1 Dashboard főoldal
- **URL**: `/fiokom/`
- **Hozzáférés**: 👤 Bejelentkezve
- **Tartalom**:
  - Üdvözlés (név + fiók típus badge)
  - Statisztikák kártyák: Kedvencek száma, Aktív chat-ek, Mentett keresések
  - Gyorslinkek a főbb funkciókhoz

### 2.2 Kedvenceim
- **URL**: `/fiokom/favourites/`
- **Hozzáférés**: 👤 Bejelentkezve
- **Tartalom**:
  - Mentett hirdetések listája (kártya nézetben)
  - Eltávolítás gomb minden kártyán
  - Üres állapot: "Még nincs kedvenc hirdetésed"

### 2.3 Üzeneteim (Chat)
- **URL**: `/fiokom/chat/`
- **Hozzáférés**: 👤 Bejelentkezve
- **Tartalom**:
  - Bal oldal: Beszélgetések listája (név, utolsó üzenet, időbélyeg, olvasatlan badge)
  - Jobb oldal: Aktív chat ablak
  - Üzenetek küldése
  - Hirdetésre hivatkozás a chatben
  - Üres állapot: "Még nincs üzeneted"

### 2.4 Keresési értesítők
- **URL**: `/fiokom/search-alert/`
- **Hozzáférés**: 👤 Bejelentkezve
- **Tartalom**:
  - Mentett keresések listája (szűrőkkel: kategória, helyszín, ár stb.)
  - Értesítési frekvencia: Napi / Heti / Havi
  - Törlés gomb
  - Keresés aktiválása / deaktiválása
  - Üres állapot: "Még nincs mentett keresésed"

### 2.5 Profil szerkesztése
- **URL**: `/fiokom/edit-account/`
- **Hozzáférés**: 👤 Bejelentkezve
- **Tartalom**: → Lásd FORM-SPECIFICATIONS.md: "Profil szerkesztési form"

### 2.6 Fiók beállítások
- **URL**: `/fiokom/profile-settings/`
- **Hozzáférés**: 👤 Bejelentkezve
- **Tartalom**: Email értesítési preferenciák

---

## 3. PRIVATE SELLER DASHBOARD

*Mindent tartalmaz amit a Member Dashboard + az alábbiak:*

### 3.1 Hirdetéseim
- **URL**: `/fiokom/listings/`
- **Hozzáférés**: 🏷️ Private Seller / Business Seller
- **Tartalom**:
  - Hirdetések listája táblázatban:
    - Cím
    - Státusz badge (Pending / Active / Expired / Temp / Reviewed)
    - Kategória
    - Feladás dátuma
    - Lejárat dátuma
    - Megtekintések száma
    - Műveletek: Szerkesztés / Törlés / Megújítás / Kiemelés
  - "Hirdetés feladása" gomb
  - Szűrő státusz szerint
  - Üres állapot: "Még nincs hirdetésed"

### 3.2 Hirdetés feladása
- **URL**: `/fiokom/add-listing/`
- **Hozzáférés**: 🏷️ Private Seller / Business Seller
- **Folyamat**: Multi-step wizard → Lásd FORM-SPECIFICATIONS.md
- **Lépések**:
  1. Kategória választó
  2. Alap adatok (cím, leírás, típus)
  3. Custom mezők (kategória-specifikus)
  4. Galéria feltöltés
  5. Elérhetőség + helyszín
  6. Csomag választó (ingyenes / fizetős)
  7. Előnézet + Beküldés

### 3.3 Hirdetés szerkesztése
- **URL**: `/fiokom/listing/{id}/edit/` (RTCL generálja)
- **Hozzáférés**: 🏷️ Saját hirdetés tulajdonosa
- **Tartalom**: Előtöltött hirdetés form (azonos az "Add Listing" formmal)

### 3.4 Fizetési előzmények
- **URL**: `/fiokom/payments/`
- **Hozzáférés**: 🏷️ Private Seller / Business Seller
- **Tartalom**:
  - Fizetések listája (dátum, összeg, típus, státusz)
  - Státuszok: Kész / Függőben / Meghiúsult / Visszatérítve
  - Számla letöltés (ha implementálva)
  - Üres állapot: "Még nincs fizetési előzményed"

### 3.5 Eladói hitelesítés
- **URL**: `/fiokom/my-documents/`
- **Hozzáférés**: 🏷️ Private Seller / Business Seller
- **Tartalom**:
  - Jelenlegi státusz (Nincs / Feltöltve / Ellenőrzött)
  - "Ellenőrzött Eladó" badge (ha verified)
  - Fotó azonosító feltöltés (NIE/NIF fotó — `photo_id` user meta)
  - Egyéb dokumentum feltöltés (`other_document_id` user meta)
  - Admin visszajelzés szövege (ha van)
  - Elfogadott formátumok: PNG, JPG, JPEG (foto) + PDF + egyéb (business dok.)

### 3.6 Membership vásárlás (checkout)
- **URL**: `/rtcl-checkout/` (RTCL belső — megerősítendő)
- **Hozzáférés**: 🏷️ Private Seller / Business Seller
- **Tartalom**:
  - Kiválasztott csomag részletei
  - Fizetési mód választó
  - Fizetés megerősítés

---

## 4. BUSINESS SELLER DASHBOARD

*Mindent tartalmaz amit a Private Seller Dashboard + az alábbiak:*

### 4.1 Üzletem (Store szerkesztés)
- **URL**: `/fiokom/store/` (megerősítendő)
- **Hozzáférés**: 🏢 Business Seller
- **Tartalom**: → Lásd FORM-SPECIFICATIONS.md: "Store profil form"
  - Store logó feltöltés (200×150 px)
  - Store banner feltöltés (992×300 px)
  - Cégnév
  - Leírás (WYSIWYG editor)
  - Store kategória
  - Elérhetőségek (tel, web, cím)
  - Közösségi média linkek

---

## 5. RENDSZER OLDALAK

### 5.1 Email megerősítés
- **URL**: Emailben küldött link → `/fiokom/?action=verify&token=...`
- **Tartalom**: Sikeres / Sikertelen megerősítés üzenet

### 5.2 Checkout / Fizetés
- **URL**: RTCL belső checkout oldal
- **Tartalom**: Kosár, fizetési form, visszaigazolás

### 5.3 Hirdetés előnézet
- **Státusz**: `rtcl-temp` post status
- **Hozzáférés**: 🏷️ Saját hirdetés szerzője
- **Tartalom**: Hirdetés single page nézet "Közzététel" / "Szerkesztés" gombokkal

---

## 6. HIBÁS ÁLLAPOTOK

| Oldal | URL | Tartalom |
|-------|-----|---------|
| 404 — Nem található | `/404` (WP kezeli) | "Az oldal nem található" + keresés + főoldal link |
| 401 — Bejelentkezés szükséges | Átirányítás `/bejelentkezes/` | Bejelentkezési form + visszairányítás |
| 403 — Nincs jogosultság | Inline üzenet | "Ehhez a funkcióhoz eladói fiók szükséges" |
| Lejárt hirdetés | Hirdetés URL | Lejárt banner + "Hasonló hirdetések" |
| Lejárt membership | Dashboard | Figyelmeztető banner + "Membership megújítása" CTA |
| Felfüggesztett hirdetés | — | Admin üzenet |

---

## 7. ADMINISZTRÁCIÓS FELÜLET

*(WordPress /wp-admin — nem tervezési feladat, de felsoroljuk a kapcsolódó részeket)*

| Felület | URL | Tartalom |
|---------|-----|---------|
| Hirdetések | `/wp-admin/edit.php?post_type=rtcl_listing` | Lista, jóváhagyás, elutasítás |
| Custom mezők | `/wp-admin/edit.php?post_type=rtcl_cf` | Field builder |
| Custom mező csoportok | `/wp-admin/edit.php?post_type=rtcl_cfg` | Group builder |
| Kategóriák | `rtcl_category` taxonomy | — |
| Helyszínek | `rtcl_location` taxonomy | — |
| Felhasználók | `/wp-admin/users.php` | + Document Status oszlop (rtcl-seller-verification) |
| Felhasználó profil | `/wp-admin/user-edit.php?user_id=X` | + Seller Documents szekció |
| Membership beállítások | RTCL Settings | free ads, limits |
| Fizetési átjárók | RTCL Payment Settings | aktív gateways |

---

## Megjegyzések

- **DATABASE EXPORT REQUIRED**: Kategória slug-ok, helyszín slug-ok, összes statikus oldal tényleges URL-je
- **URL konfiguráció**: A fenti URL-ek javasolt struktúrák — a tényleges permalink beállítások klienstől megerősítendők
- **wppb státusz**: Elementor exportban `[wppb-login]`/`[wppb-register]` shortcode-ok láthatók, de a plugin nincs telepítve. Az új rendszer RTCL saját account rendszerét használja.
- **Nyelv**: A URL slug-ok spanyol/magyar/angol nyelv klienstől megerősítendő

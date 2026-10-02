# TARGET-FUNCTIONAL-SPEC.md
## Torrehub — Jelenlegi vs. Célrendszer összehasonlítás

**Verzió**: 2.0  
**Dátum**: 2026-08-29  
**Cél**: Egyedi WordPress téma fejlesztéséhez funkcionális iránymutatás

**⚠️ v2.0 frissítés (2026-08-29)**: DB export alapján korrigált adatok. ProfileBuilder CONFIRMED ACTIVE, NIE/NIF már implementálva, Store/Membership/Compare DISABLED.

---

## 1. Összefoglaló

| Terület | Jelenlegi rendszer | Célrendszer | Változás típusa |
|---------|-------------------|-------------|-----------------|
| Téma | CLDirectory (RadiusTheme v3.2.0) | Egyedi custom téma | ♻️ Csere |
| Oldal builder | Elementor + Elementor Pro | PHP sablonok (nincs Elementor) | ❌ Eltávolítás |
| RTCL plugin stack | RTCL Free + Pro + Store | RTCL Free + Pro + Store (marad) | ✅ Megmarad |
| Felhasználó típusok | 2: seller/buyer | 3: Member/Private Seller/Business Seller | 🆕 Bővítés |
| Regisztrációs mezők | Email + Jelszó | Típusonként eltérő + NIE/NIF | 🆕 Bővítés |
| Regisztrációs rendszer | wppb ProfileBuilder CONFIRMED ACTIVE v3.16.3 | wppb ProfileBuilder (marad, már implementált NIE/NIF-fel) | ✅ Megmarad |
| Store | ⛔ DISABLED (enable_store: "") | Client döntés szükséges | ❓ |
| Seller verification | rtcl-seller-verification (foto+dok) | rtcl-seller-verification (marad) + NIE/NIF specifikus | 🔧 Módosítás |
| Chat | RTCL Pro chat (Pusher) | RTCL Pro chat (marad) | ✅ Megmarad |
| Membership | ⛔ DISABLED (enable: "") | Client döntés szükséges | ❓ |
| Compare | ⛔ DISABLED (enable_compare: "") | Marad DISABLED | ❌ |
| Search Alert | rtcl-search-alert | rtcl-search-alert (marad) | ✅ Megmarad |
| Review | review-schema + pro | review-schema + pro (marad) | ✅ Megmarad |

---

## 2. Részletes összehasonlítás

### 2.1 Témarendszer

| Aspektus | Jelenlegi | Cél | Feladat |
|----------|-----------|-----|---------|
| Alap téma | CLDirectory (RadiusTheme) | Egyedi téma (teljesen új) | Fejlesztés |
| Gyerek téma | cldirectory-child (minimális) | Nem szükséges (új alap téma) | — |
| Template hierarchia | CLDirectory + Override mappák | Egyedi PHP template hierarchia | Fejlesztés |
| CSS keretrendszer | Bootstrap (CLDirectory-ban) | CLIENT-CONFIRM: Bootstrap 5 marad? | Döntés szükséges |
| JavaScript | Vanilla JS + jQuery (RTCL) | Vanilla JS + jQuery (RTCL-hez szükséges) | Minimális |
| Icon rendszer | cl-icon font (custom) | CLIENT-CONFIRM: Marad? Vagy Font Awesome 6? | Döntés szükséges |
| Elementor | Szükséges (oldal builder) | ❌ Nem szükséges | Eltávolítható |

### 2.2 Oldal sablonok

| Oldal típus | Jelenlegi (Elementor) | Cél (PHP sablon) | Prioritás |
|-------------|----------------------|------------------|-----------|
| Főoldal | Elementor page template | `front-page.php` | 🔴 Magas |
| Hirdetés lista | RTCL archive + Toolkits widget | `archive-rtcl_listing.php` | 🔴 Magas |
| Hirdetés single | Elementor + rtcl_builder (10 sablon) | `single-rtcl_listing.php` + per-cat | 🔴 Magas |
| Login | Elementor + `[wppb-login]` | `page-login.php` + RTCL form | 🔴 Magas |
| Register | Elementor + `[wppb-register]` | `page-register.php` + egyedi form | 🔴 Magas |
| My Account | Elementor + `[rtcl_my_account]` | `page-myaccount.php` + RTCL shortcode | 🟡 Közepes |
| Store lista | Elementor + `[rtcl_stores]` | ⛔ DISABLED / N.A. | — |
| Single Store | Elementor + `[rtcl_store_page]` | ⛔ DISABLED / N.A. | — |
| Pricing | Elementor + `[rtcl_membership_pricing_table]` | ⛔ DISABLED (Membership modul kikapcsolva) | — |
| 404 | Elementor | `404.php` | 🟢 Alacsony |
| Statikus oldalak | Elementor | `page.php` + sablonok | 🟢 Alacsony |

### 2.3 Regisztráció / Account rendszer

| Funkció | Jelenlegi | Cél | Megjegyzés |
|---------|-----------|-----|------------|
| Login form | wppb-login (ProfileBuilder page ID: 27211, CONFIRMED) | wppb-login (ProfileBuilder page ID: 27211, CONFIRMED) | ✅ |
| Register form | wppb-register (ProfileBuilder page ID: 27210, CONFIRMED) | wppb-register (ProfileBuilder page ID: 27210, CONFIRMED) | ✅ |
| NIE/NIF mező | ✅ LÉTEZIK — custom_field_1 (NIE), custom_field_2 (NIF) — wppb conditional fields | Megmarad, esetleg UI javítás | ✅ |
| Felhasználó típus | 3 WP role: customer/seller/business (wppb Select User Role mező, CONFIRMED) | customer→MEMBER, seller→PRIVATE SELLER, business→BUSINESS SELLER (rebrand) | ✅ |
| Password recovery | wppb-recover-password (page ID: 27213, CONFIRMED) | wppb-recover-password (page ID: 27213, CONFIRMED) | ✅ |
| Account dashboard | `[rtcl_my_account]` shortcode | `[rtcl_my_account]` shortcode (marad) | ✅ |
| Email verification | RTCL beállítás | RTCL beállítás (marad) | ✅ |

### 2.4 Hirdetési rendszer

| Funkció | Jelenlegi | Cél | Változás |
|---------|-----------|-----|---------|
| Hirdetés feladás form | RTCL Form Builder (FB) VAGY standard | RTCL (marad) | ✅ |
| Custom mezők | `rtcl_cfg` + `rtcl_cf` system | `rtcl_cfg` + `rtcl_cf` system (marad) | ✅ |
| Galéria | RTCL upload | RTCL upload (marad) | ✅ |
| Térkép | Google Maps (RTCL) | Google Maps (marad) | ✅ |
| Per-kategória sablonok | 10 rtcl_builder Elementor sablon | PHP per-category templates | Fejlesztés |

### 2.5 Keresés / Szűrés

| Funkció | Jelenlegi | Cél | Változás |
|---------|-----------|-----|---------|
| Search widget | Toolkits Elementor widget | Egyedi PHP search form | Fejlesztés |
| Szűrők | Toolkits `rtcl-listing-search-form` | Egyedi sidebar szűrő PHP | Fejlesztés |
| Térkép nézet | Toolkits + RTCL | RTCL map (marad) | Minimális |
| Listing Showcase | Toolkits Elementor widget | Egyedi WP_Query + loop | Fejlesztés |

### 2.6 Seller Verification

| Funkció | Jelenlegi | Cél | Változás |
|---------|-----------|-----|---------|
| Dokumentum feltöltés | rtcl-seller-verification plugin | rtcl-seller-verification (marad) | ✅ |
| Admin jóváhagyás | Manual admin checkbox | Manual admin checkbox (marad) | ✅ |
| Badge megjelenítés | Plugin hook-on keresztül | Plugin hook (marad) | ✅ |
| NIE/NIF specifikus | ❌ | Dokumentum feltöltés NIE/NIF specifikus leírással | Tartalom |

---

## 3. Szükséges új fejlesztések

### 3.1 Téma fejlesztési feladatok

| Feladat | Komplexitás | Prioritás | Megjegyzés |
|---------|------------|-----------|------------|
| Alap téma struktúra | Magas | 🔴 | functions.php, hooks, helpers |
| Header + Footer | Közepes | 🔴 | — |
| Főoldal sablon | Magas | 🔴 | — |
| Archive sablon (listings) | Magas | 🔴 | Szűrők + AJAX |
| Single listing sablon | Magas | 🔴 | Per-kategória variánsok |
| Login/Register oldalak | Magas | 🔴 | 3-típusos regisztráció |
| Dashboard template-ek | Magas | 🔴 | 3 típus × N endpoint |
| Store sablonok | Közepes | 🟡 | — |
| 404, Pricing, Statikus oldalak | Alacsony | 🟢 | — |

### 3.2 Egyedi plugin/logika fejlesztések

| Feladat | Hol | Megjegyzés |
|---------|-----|------------|
| 3-típusos regisztráció | MÁR IMPLEMENTÁLVA — wppb ProfileBuilder (nem kell fejleszteni) | — |
| NIE/NIF user meta | MÁR IMPLEMENTÁLVA — custom_field_1, custom_field_2 (wppb, nem kell fejleszteni) | — |
| NIE/NIF validáció | Frontend regex validáció javítása/megerősítése szükséges lehet | JS |
| NIE/NIF megjelenítése admin profil oldalon | Egyedi | WP admin user profile hook |
| Felhasználó típus routing | Egyedi | Dashboard melyik widget jelenjen meg |

### 3.3 NEM szükséges fejlesztések (marad RTCL / már implementált)

- ✅ Hirdetés feladás logika és form kezelés
- ✅ Listing meta mentése és lekérdezése
- ✅ Chat rendszer (Pusher)
- ✅ Seller verification dokumentum kezelés
- ✅ Search alert scheduler
- ✅ Review rendszer
- ✅ Account endpoint routing
- ✅ wppb alapú regisztráció (már implementált)
- ⛔ Membership és fizetési rendszer — DISABLED, client döntés szükséges
- ⛔ Store post type és kezelés — DISABLED, client döntés szükséges
- ⛔ Compare logika — DISABLED

---

## 4. Pluginek — Marad / Eltávolítható

| Plugin | Jelenlegi szerep | Célrendszerben | Döntés |
|--------|-----------------|----------------|--------|
| Elementor | Oldal builder | Nincs szükség | ❌ Eltávolítható |
| Elementor Pro | Pro widgetek | Nincs szükség | ❌ Eltávolítható |
| classified-listing | Alap RTCL | Marad | ✅ |
| classified-listing-pro | Pro funkciók | Marad | ✅ |
| classified-listing-store | ⛔ Store DISABLED | Client döntés szükséges | ❓ |
| classified-listing-toolkits | Elementor widgetek | PHP-ben újraírjuk a logikáját | ⚠️ Deaktiválható (de Search Form helper logika kellhet) |
| cldirectory-core | CLDirectory specifikus | Vizsgálat szükséges | ⚠️ |
| rt-framework | CLDirectory specifikus | Vizsgálat szükséges | ⚠️ |
| rtcl-seller-verification | Dok. hitelesítés | Marad | ✅ |
| rtcl-verification | OTP phone | Marad (ha aktív) | ✅ |
| rtcl-search-alert | Keresési értesítők | Marad | ✅ |
| review-schema | Alap review | Marad | ✅ |
| review-schema-pro | Pro review | Marad | ✅ |
| easy-demo-importer | Demo import | ❌ Eltávolítható | ❌ |
| wp-profile-builder (wppb) | CONFIRMED ACTIVE — regisztrációs rendszer (v3.16.3, lifetime license) | ✅ MARAD | ✅ |
| fluentform | Kontakt form | Marad (kapcsolat oldal) | ✅ |
| radius-booking | Foglalás | KLIENS DÖNTÉS | ❓ |

---

## 5. Technikai követelmények — Új téma

### 5.1 WordPress minimum verziók
- WordPress: 6.0+
- PHP: 7.4+ (CLDirectory minimum — érdemes PHP 8.1+ célozni)
- MySQL: 5.7+

### 5.2 Kötelező téma funkciók
```php
add_theme_support( 'post-thumbnails' );
add_theme_support( 'title-tag' );
add_theme_support( 'html5', [...] );
add_theme_support( 'custom-logo' );
add_theme_support( 'menus' );
```

### 5.3 Kötelező widget területek
- `single-listing-sidebar` — Hirdetés részletek sidebar (RTCL elvárja)
- `footer-1`, `footer-2`, stb.

### 5.4 RTCL Template override struktúra
Az RTCL sablonok felülírása a téma mappájából:
```
{new-theme}/classified-listing/
├── single-listing.php
├── archive-listing.php
├── myaccount/
│   ├── dashboard.php
│   ├── listings.php
│   └── ...
└── custom/
    ├── listing-heading.php
    └── ...
```

### 5.5 JavaScript függőségek
- jQuery (WordPress bundle — RTCL igényli)
- Google Maps JS API (térkép funktciókhoz)
- Pusher JS (chat real-time — csak ha chat aktív)
- Vanilla JS (custom interakciók)

---

## 6. Fejlesztési prioritási sorrend (Javasolt)

| Fázis | Feladatok |
|-------|-----------|
| **1. Alap** | Téma struktúra, Header/Footer, Alap oldalak (főoldal, 404, statikus) |
| **2. Hirdetések** | Archive + Single listing sablonok (per-kategória) |
| **3. Account** | Login/Register (3 típus + NIE/NIF), Dashboard, Profil |
| **4. Eladói** | Hirdetés feladás UI, Hirdetéseim, Fizetések |
| **5. Business** | Store sablonok, Business Seller dashboard |
| **6. Finomítás** | Mobil optimalizálás, performance, SEO |

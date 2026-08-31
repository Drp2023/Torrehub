# DESIGN-READY-CHECKLIST.md
## Torrehub — Design Elkészültségi Ellenőrző Lista

**Verzió**: 1.0
**Dátum**: 2026-08-29
**Cél**: Összefoglalja mi kész, mi hiányzik a 3 design direction elkészítéséhez

---

## ✅ SECTION 1: KÉSZ — Design megkezdhető ezekkel

### Termékvízió és pozicionálás
- ✅ **Platform típusa**: Costa Blanca helyi hub — üzleti könyvtár + apróhirdetések + helyi információk
- ✅ **Célközönség**: Helyi lakosok + expatok (skandinávok, britek, románok, magyarok) + turisták
- ✅ **Elsődleges helyszín**: Torrevieja és a Costa Blanca régió 35 városa

### Kategóriák és helyszínek
- ✅ **Kategória struktúra**: 10 főkategória, 152 összesen (teljes fa-struktúra ismert)
  - Auto/Moto/Boats · Events · Jobs · Leisure & Sport · Marketplace · Properties · Public Information · Restaurants & Nightlife · Services · Tourist Attractions
- ✅ **Helyszínek**: 35 lapos lista, Costa Blanca városok (Torrevieja, Alicante, Benidorm, Orihuela Costa, stb.)

### Fiók típusok
- ✅ **3 fiók típus** (WP role-ok, már implementálva):
  - MEMBER (`customer`) — böngésző, kedvencek, kapcsolatfelvétel
  - PRIVATE SELLER (`seller`) — hirdetésfeladás, NIE szükséges
  - BUSINESS SELLER (`business`) — hirdetésfeladás, NIF szükséges
- ✅ **NIE/NIF mezők**: Már implementálva a ProfileBuilder-ben (custom_field_1, custom_field_2)
- ✅ **Admin jóváhagyás**: Minden regisztráló kézzel hagyja jóvá az admin

### Funkciók (aktív, tervezhető)
- ✅ **Chat (Pusher)**: Real-time üzenetküldő — aktív
- ✅ **Keresési értesítők**: Mentett keresés, email értesítők — aktív
- ✅ **Google Maps**: Hirdetések térképen, API key konfigurált
- ✅ **Eladói hitelesítés**: Verified badge rendszer — aktív
- ✅ **Hirdetésfeltöltés korlátok**: 5 kép max (2MB), 15 nap lejárat, 5 ingyenes / 30 nap
- ✅ **Review rendszer**: Hirdetés értékelések — aktív

### Funkciók (DISABLED — tervezési döntés egyszerűbb)
- ✅ **Store**: ⛔ DISABLED — Business Seller-nek nincs külön üzlet oldal (jelenleg)
- ✅ **Membership / Előfizetés**: ⛔ DISABLED — nincs fizetős csomag
- ✅ **Compare**: ⛔ DISABLED — hirdetés összehasonlítás nincs
- ✅ **Restaurant listing (food menu)**: ⛔ DISABLED
- ✅ **Hirdetés típus (Sell/Rent/Wanted)**: ⛔ HIDDEN — a form elrejti

### Brand színek
- ✅ **Primary**: `#0056b3` (főszín)
- ✅ **Button / Featured**: `#FF8C00` (gombok, kiemelt hirdetések)

### Technikai keretrendszer
- ✅ **Platform**: WordPress custom theme (Elementor nélkül)
- ✅ **Mobile-first**: Kötelező — teljesítmény prioritás
- ✅ **Regisztrációs rendszer**: ProfileBuilder (wppb) — 9 mezős form, már implementált
- ✅ **TILOS**: Glassmorphism, box-shadow, komplex gradiensek, 0.2s-nál hosszabb animációk

### Oldalstruktúra
- ✅ **Ismert oldalak**: Főoldal, Hirdetés lista (archive), Hirdetés részletek, Login, Register, Dashboard (3 típus), Keresési értesítők, Profil szerkesztés, Eladói hitelesítés
- ✅ **Dashboard végpontok**: dashboard, listings, favourites, chat, search-alert, edit-account, profile-settings, verify, my-documents

---

## ❓ SECTION 2: KLIENSDÖNTÉS SZÜKSÉGES — Ezek nélkül a design nem véglegesíthető

| # | Kérdés | Hatás a designra | Referencia |
|---|--------|-----------------|------------|
| D1 | Logó fájl megléte? | Logo zóna mérete a headerben | CLIENT-CONFIRMATION.md D2 |
| D2 | Store funkció kell-e? | Business Seller dashboard + Store oldal sablonok | CLIENT-CONFIRMATION.md B1 |
| D3 | Membership csomagok lesznek-e? | Pricing oldal, Dashboard membership szekció | CLIENT-CONFIRMATION.md C1 |
| D4 | Hirdetés típus (Sell/Rent/Wanted) szükséges-e? | Form design (hirdetésfeladás step 2) | CLIENT-CONFIRMATION.md A1 |
| D5 | Member látja-e a telefonszámot? | Hirdetés részletek oldal elérhetőség szekció | CLIENT-CONFIRMATION.md A5 |
| D6 | Van-e design referencia (inspiráció)? | 3 design direction hangvétele | CLIENT-CONFIRMATION.md D1 |
| D7 | Végleges nyelvi lista? | Navigáció, flag ikonok, language switcher pozíció | CLIENT-CONFIRMATION.md D3 |

---

## 🔧 SECTION 3: TECHNIKAI DÖNTÉS — Fejlesztő dönti el, de designer-t érinti

| # | Kérdés | Hatás a designra | Státusz |
|---|--------|-----------------|--------|
| T1 | CSS keretrendszer (Bootstrap 5 vs. custom)? | Grid rendszer, breakpointok | TECHNICAL |
| T2 | Ikon rendszer (Font Awesome 6 vs. SVG sprite vs. custom)? | Ikon stílus konzisztenciája | TECHNICAL |
| T3 | Language switcher elhelyezése (header vs. footer)? | Header layout | TECHNICAL |
| T4 | WPML vs. egyéb fordítási plugin? | URL struktúra (prefix: /es/, /en/) | TECHNICAL |
| T5 | Dashboard nav mobil: tab bar vagy hamburger? | Mobil dashboard UX | TECHNICAL |
| T6 | NIE/NIF titkosítva tárolandó-e? (GDPR) | Nincs design hatás, technikai döntés | TECHNICAL |

---

## ❌ SECTION 4: ISMERETLEN / HIÁNYZÓ ADAT

| # | Mi hiányzik | Hatás | Hogyan szerezhető meg |
|---|-------------|-------|----------------------|
| ~~M1~~ | ~~Form Builder mezők~~ | ~~UNKNOWN~~ | ✅ MEGOLDVA — 09-rtcl-forms.json: 10 form, 257 mező, teljes feltérképezés (→ LISTING-FIELDS.md 5. szekció) |
| M2 | **Logó fájl** | Header, favicon design | Kliens mellékeli |
| M3 | **Kategória ikonok** (melyik ikon = melyik kategória) | Főoldal kategória rács design | Kliens / designer dönti el |
| M4 | **Referencia oldal / design inspiráció** | 3 design direction hangvétele | Kliens megadja |
| M5 | **Review-schema mezők** (review-schema-pro plugin) | Review form design | DB export vagy plugin docs |
| M6 | **Claim listing plugin** aktív-e? | Hirdetés részletek oldal (claim gomb) | DB export: `SELECT option_name, option_value FROM wp_options WHERE option_name LIKE 'rtcl_claim%'` |

---

## Section 4 frissítve (2026-08-29)

A DESIGN-BLOCKING UNKNOWN lista lezárult. Az egyetlen fennmaradó hiányok (logó, referencia oldal) nem technikai és nem design-blocking — a designer ezek nélkül is elkezdhet 3 direction-t.

---

## Összefoglaló: KÉSZEN ÁLLUNK-E A 3 DESIGN DIRECTION ELKÉSZÍTÉSÉRE?

| Terület | Státusz |
|---------|---------|
| Termékvízió és pozicionálás | ✅ KÉSZ |
| Célközönség definíció | ✅ KÉSZ |
| Kategóriák és helyszínek | ✅ KÉSZ |
| Fiók típusok és jogosultságok | ✅ KÉSZ |
| Funkcionális scope | ✅ KÉSZ (disabled funkciók tisztázva) |
| Brand színek (alap) | ✅ KÉSZ (2 szín ismert) |
| Teljes szín paletta | ⚠️ Designer dönti el (design direction részeként) |
| Betűtípus rendszer | ⚠️ Designer dönti el (design direction részeként) |
| Logó fájl | ❌ HIÁNYZIK |
| Design referencia / inspiráció | ❌ HIÁNYZIK |
| Store funkció döntés | ❌ KLIENS DÖNTÉS |
| Membership döntés | ❌ KLIENS DÖNTÉS |

### Verdict

**A 3 design direction MEGKEZDHETŐ**, az alábbi feltételekkel:

1. ✅ A **termékvízió, célközönség, kategóriák, fiók típusok, aktív/disabled funkciók** teljesen tisztázottak
2. ✅ Az alap **brand színek** ismertek (`#0056b3`, `#FF8C00`)
3. ⚠️ A **logó** hiányában a design direction **logó placeholder-rel** készül
4. ⚠️ A **Store és Membership** döntések hiányában ezek **opcionális blokként** tervezhetők (on/off)
5. ✅ **Form Builder kategória-specifikus mezők** — KÉSZ: 10 form, 257 mező, teljes feltérképezés (→ LISTING-FIELDS.md 5. szekció)

**Ha a kliens megadja a logót és a referencia oldalt, a design direction teljes értékűen elkészíthető.**

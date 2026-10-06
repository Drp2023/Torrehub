# BUILD-PLAN.md — Torrehub téma (Direction C · Modern Local Hub)

**Verzió:** 2.0 — a 2026-10-02-i döntések szerint (felülírja a promptot és a v1 tervet)
**Állapot:** 0–3. fázis jóváhagyva · 4. fázis kész, jóváhagyásra vár
**Kapcsolódó:** `RTCL-INTEGRATION-MAP.md`, `RTCL-FREE-CAPABILITIES.md`, `../design/INVENTORY.md`, `../../hotfix/README.md`

---

## 0. Döntések (v2) — ezek az irányadók

1. **Egyetlen fizetős plugin sem maradhat.** A szállított termék **egy WordPress téma**; minden funkció a témában, `inc/` alatt, modulárisan.
2. **Ingyenes wp.org pluginok maradhatnak, ha tényleg kellenek**: `classified-listing` (free) = listing-motor, `fluentform` = kapcsolat. Minden mást a téma ad; a feleslegessé váló ingyenes pluginokat jelezzük (2. pont).
3. **Nincs visszafelé kompatibilitás** (régi shortcode-ok, régi URL-ek, wppb). **Adat marad**: 19 hirdetés, userek, kategóriák (152), helyszínek (34), `custom_field_1` (NIE) / `custom_field_2` (NIF).
4. **Auth teljesen a témában**: login, regisztráció (3 szerepkör + NIE/NIF + e-mail megerősítés + admin jóváhagyás), jelszó-visszaállítás.
5. **A témában újraépül**: chat (polling; Pusher opcionális), eladó-hitelesítés (feltöltés + admin jóváhagyás + badge), keresési értesítő (mentett keresés + e-mail cron), értékelések, egyedi single listing oldal.
6. **A téma mappája a git repó**: `wp-content/themes/torrehub/`. Dev-anyag: `_dev/`, `bin/`, `hotfix/` — ezek nem kerülnek a csomagba. **Élesre csak a `bin/build-zip.sh` kimenete mehet.** A `backup/` a repón kívül marad (`C:\Users\Miklos\Local Sites\Torrehub\backup`, `BACKUP_DIR`).
7. **Biztonsági hotfix** (`hotfix/torrehub-security.php`) az éles oldalra — kész, telepítési leírással.
8. **Nyelvválasztó** a GTranslate beállításából olvas, nincs hardcode-olt lista.
9. Jóváhagyott v1-javaslatok: kvóta-sáv rejtve, kedvencek rejtve amíg ki vannak kapcsolva, Open now madridi időben, hiányzó képernyők a meglévő komponensekből „design review” jelöléssel.
10. **(2026-10-06)** Cookie banner: a 8. fázisban saját, könnyű, témába épített consent sáv, amely a hozzájárulásig **ténylegesen blokkolja** a nem szükséges scripteket; utána a `gdpr-cookie-compliance` plugin megy.
11. **(2026-10-06)** WPCode snippetek: mindegyik abban a fázisban kerül a témába, ahová tartozik (NIE/NIF az 5.-ben), a feleslegesek kimaradnak; a végén a WPCode plugin is megy. Audit: `WPCODE-AUDIT.md`.
12. **(2026-10-06)** Városképek: placeholder marad, a képeket a tartalomfeltöltésnél kapják.

---

## 1. Plugin-végállapot

| Plugin | Most | Végállapot | Indok |
|---|---|---|---|
| `classified-listing` (free) | aktív | **marad** | listing-motor: CPT, taxonómiák, Form Builder, moderáció, my-account endpointok, értesítő e-mailek |
| `fluentform` (free) | aktív | **marad** | kapcsolat-űrlap |
| `gtranslate` (free) | aktív | **marad** | a nyelvválasztó a beállításából olvas (döntés 8) |
| `fluent-smtp` (free) | aktív (lokálisan ki) | **marad (ajánlott)** | élesben a kézbesíthetőséghez (auth-, chat-, értesítő-e-mailek); a téma nem küld SMTP-n |
| `gdpr-cookie-compliance` (free) | aktív | **eltávolítva → téma `Consent` (8. fázis)** | döntés 10: saját consent sáv, a hozzájárulásig blokkolja a nem szükséges scripteket. (A plugin bannere most az LCP-elem: 3,0 s vs 1,6 s.) |
| `backup-backup` (free) | aktív | **marad** | üzemeltetés (backup); nem téma-feladat |
| `classified-listing-pro` | aktív | **eltávolítva → téma** | 3. pont |
| `classified-listing-store` | aktív | **eltávolítva** | store/membership ki van kapcsolva; a kvóta-modul a témában (rejtve) |
| `rtcl-seller-verification` | aktív | **eltávolítva → téma** | `Verification` modul |
| `rtcl-search-alert` | aktív | **eltávolítva → téma** | `SearchAlerts` modul |
| `rtcl-verification` (OTP) | telepítve, inaktív | **eltávolítva, nem épül újra** | élesen sem futott; CLIENT A3 nyitott — ha kell, külön modul |
| `rtcl-elementor-builder` | aktív | **eltávolítva → téma** | saját single listing |
| `review-schema-pro` | aktív | **eltávolítva → téma** | `Reviews` modul + saját JSON-LD |
| `elementor`, `elementor-pro` | aktív | **eltávolítva** | PHP template-ek; a statikus oldalak tartalma blokkokba migrálva (7. fázis) |
| `cldirectory-core`, `rt-framework` | aktív | **eltávolítva** | csak a régi téma Elementor-widgetjei |
| `review-schema` (free) | aktív | **feleslegessé válik** ⚠ | saját értékelés-rendszer + JSON-LD; két séma-forrás ütközne |
| `classified-listing-toolkits` (free) | aktív | **feleslegessé válik** ⚠ | csak Elementor-widgetek / kereső-widgetek — a téma adja |
| `advanced-custom-fields` (free) | aktív | **valószínűleg felesleges** ⚠ | a téma saját metaboxokat használ; ellenőrizni, van-e mezőcsoport (DB) |
| `insert-headers-and-footers` (free, **WPCode**) | aktív | **eltávolítva a végén** | döntés 11: a 14 snippet fázisonként a témába (audit: `WPCODE-AUDIT.md`, 14. pont); utolsóként a plugin |
| `duplicate-page` (free) | aktív | **felesleges** ⚠ | admin-kényelmi; nem a termék része |
| `filester` (free) | aktív | **eltávolítandó** ⚠ | böngészős fájlkezelő az élesen = felesleges támadási felület |

---

## 2. Fizetős funkció → téma-modul → fázis

A modulok `inc/modules/<modul>/` alatt, mindegyik saját osztállyal (`Torrehub\Modules\<Modul>\Module`), ki-be kapcsolható (Téma beállítások), és **graceful degradation**: ha a `classified-listing` nincs aktív, a listinghez kötött modulok nem töltődnek be.

| Kiváltott funkció | Eredeti forrás | Téma-modul | Adattárolás | Fázis |
|---|---|---|---|---|
| Single listing oldal (mind a 10 form) | rtcl-elementor-builder + Elementor | `Listing` modul (`View` + `template-parts/listing/*`) + közös mező-formázó `Data\ListingFields` | RTCL meta (változatlan) | 4 ✅ |
| Galéria + lightbox (PhotoSwipe) | Pro | `gallery.js` (vanilla, `<dialog>`) | — | 4 ✅ |
| Kártyán megjelenő FB mezők (listable fields) | Pro | `Listing\Card` | — | 2–3 |
| Grid/list nézetváltó | Pro | `Archive\View` | — | 3 |
| FB custom-field szűrők az archive-on (+ a WPCode „Filter Builder Active” snippet) | Pro UI + snippet 6510 | `Archive` modul: saját meta_query (`f[mező]`), ár, város + sugár, verified, rendezés, nézetek | téma-opció `th_archive_filters` (az LFB 14 csoportjából importálva; admin: Megjelenés › Archive filters); fallback: a form `filterable` flagje | 3 ✅ |
| **`repeater` mezőtípus** (Service, Property „Amenities”) | Pro | `Compat\FormBuilderRepeater` (`rtcl_fb_fields`) — **élesítési kapu**: nélküle Pro nélkül a szerkesztés törli a repeater adatot | meta változatlan | 1 (kapu) |
| Account endpointok `chat`, `my-documents` | Pro / SV | `Account` regisztrálja (`rtcl_account_menu_items` + endpoint) | — | 5–6 |
| Térkép nézet (archive) | Pro widget | `Archive` map view — **Leaflet 1.9.4 (vendored) + OpenStreetMap**, csak megnyitáskor tölt (DECISION: kulcs nélkül, lokálisan is működik; csempe-szolgáltató a `th_map_tiles` filterrel cserélhető) | listing `latitude/longitude`, ha van; különben a város középpontja (ma mind a 19) | 3 ✅ |
| Chat (Pusher + polling) | Pro | `Chat` | saját táblák `th_chat_threads`, `th_chat_messages` | 6 |
| E-mail megerősítés regisztrációnál | Pro (`user_verification`) | `Auth` | user meta `th_account_status`, hash-elt token | 5 |
| Login / regisztráció / jelszó-visszaállítás | wppb (hiányzik) + RTCL | `Auth` | core `wp_users` + meta; `custom_field_1/2` megtartva | 5 |
| Admin-jóváhagyás | wppb | `Auth\Approval` (Users lista oszlop, szűrő, sor- és tömeges művelet, e-mailek) | `th_account_status` | 5 |
| Seller-info csak belépve („registered_only”) | Pro | `Listing` kontakt (Customizer: `th_contact_email_login` igen, `th_contact_phone_login` nem — a design szerint; A5 nyitott) | theme_mod | 4 ✅ |
| Online státusz | Pro | **nem épül** (nincs a designban) | — | — |
| Mark as sold | Pro | **nem épül** (nincs a designban; hook-pont marad) | — | — |
| Értékelések + összesítő + eloszlás | review-schema(-pro) / Pro (a számítás free-ben van, csak a hookok Pro-ban) | `Reviews` | WP comments `comment_type=review`, meta `rating` (a meglévő 2 értékelés marad) + az RTCL free által olvasott `_rtcl_average_rating` frissítése | 4 ✅ |
| JSON-LD (Product/LocalBusiness + AggregateRating, BreadcrumbList, ItemList, Article, FAQPage) | review-schema(-pro) | listing: `Listing\Schema` (4 ✅); guide/FAQ: 7 | — | 4, 7 |
| Eladó-hitelesítés | rtcl-seller-verification | `Verification` | **privát** feltöltési mappa (nem Media Library, nincs publikus URL), meta `photo_id`, `other_document_id`, `rtcl_verified_seller` (+ `th_verification_status`, elutasítási ok) | 5 |
| Keresési értesítő | rtcl-search-alert | `SearchAlerts` | saját tábla `th_search_alerts`; WP-Cron napi/heti + azonnali (publish hook); aláírt leiratkozó link | 7 |
| Ingyenes kvóta (5 / 30 nap) | Store | `Quota` — **alapból kikapcsolva** (döntés 9) | számolás a szerző `rtcl_listing` posztjaiból | 6 |
| Hirdetésfeladás munkaterület | — (új UX) | `ListingForm` — réteg az RTCL free React Form Builder fölött | — | 6 |
| Kedvencek UI | RTCL core (`has_favourites`, jelenleg ki) | `Favourites` (UI, rejtve amíg az RTCL opció ki van) | RTCL core meta `rtcl_favourites` | 3 |
| Fiók-dashboard, „My listings” | RTCL core endpointok | `Account` (template override) | — | 5 |
| Ad type / Store / Membership / Compare / Fizetés | Pro/Store, mind kikapcsolva | **nem épül**, hook-pontok maradnak | — | — |

> A „mi működik a free `classified-listing`-ben Pro nélkül” részletes, kód-hivatkozásos listája: `RTCL-FREE-CAPABILITIES.md`. Ahol a free plugin egy funkciót Pro mögé zár, ott a téma-modul pótolja (fenti tábla).

---

## 3. Architektúra

```
torrehub/                               (= repo gyökér = téma gyökér)
├── style.css  functions.php  theme.json  screenshot.png  README.md
├── inc/
│   ├── bootstrap.php                   autoloader (Torrehub\ → inc/src/), modulok betöltése
│   ├── src/Core/                       Theme, ModuleRegistry, Settings (Téma beállítások admin oldal), Installer (dbDelta, verzió-option)
│   ├── src/Modules/
│   │   ├── Auth/                       login, register, confirm, approve, reset, rate-limit, e-mailek, NIE/NIF validátor
│   │   ├── Verification/               privát feltöltés, admin felület, badge, e-mailek
│   │   ├── Chat/                       táblák, AJAX/REST + nonce, polling, opcionális Pusher (saját HMAC-aláírás, SDK nélkül)
│   │   ├── SearchAlerts/               tábla, mentés, cron, digest e-mail, leiratkozás
│   │   ├── Reviews/                    űrlap, moderáció, aggregátum-cache
│   │   ├── Listing/                    single, card, gallery, contact, business hours (Europe/Madrid), fields renderer
│   │   ├── Archive/                    filters (meta_query), view, map, sort
│   │   ├── ListingForm/                munkaterület-réteg + kategória drill-in (`?_fb=`)
│   │   ├── Account/                    my-account override-ok, dashboard
│   │   ├── Favourites/  Quota/  Location/  Languages/ (GTranslate)  Guides/  Seo/
│   │   └── Compat/                     RTCL beállítások szűrése (pl. RTCL saját regisztrációja ki), elementor-maradványok semlegesítése
│   ├── setup.php  assets.php  template-tags.php  category-map.php  customizer.php
├── template-parts/{header,footer,components,home,listing,account,auth,guides,errors}/
├── classified-listing/                 RTCL free template override-ok
├── page-templates/                     styleguide, login, register, lost-password, about, contact, faq, legal
├── front-page.php home.php single.php archive.php page.php search.php 404.php index.php
├── assets/{css,js,fonts,icons,img}     forrás + build (a buildelt fájlok commitolva)
├── languages/torrehub.pot
├── _dev/  bin/  hotfix/                → export-ignore, nem kerül a zipbe
```

**Elvek**
- Saját táblák `dbDelta`-val, `{prefix}th_*`, verzió-option alapján migrálva (`after_switch_theme` + `admin_init` ellenőrzés). Téma-váltáskor az adat megmarad, a funkció nem fut.
- Minden űrlap/AJAX: nonce + capability + ownership; minden kimenet escape-elve; privát fájlok csak hitelesített letöltő-endpointon.
- E-mailek: `wp_mail` + téma-sablonok (fordítható), lokálisan Mailpit.
- Cron: WP-Cron eseményekkel; **élesre valódi rendszer-cron ajánlott** (`DISABLE_WP_CRON` + szerver cron) — üzemeltetési teendő.
- A fizetős pluginok kódját **nem másoljuk** — tisztán újraírjuk (licenc + karbantarthatóság).

---

## 4. RTCL free integráció (marad)

- Override mappa: `classified-listing/`; `add_theme_support('rtcl')` kötelező.
- Archive GET paraméterek: `q`, `rtcl_category`, `rtcl_location`, `geo_address`, `center_lat/lng`, `distance`, `filters[price][min|max]`, `filters[{field_name}]`, `orderby`, `view` — **mind a free `Query.php` kezeli**, a téma csak UI-t ad (lásd `RTCL-FREE-CAPABILITIES.md` §3).
- Free-ben is működik: kapcsolat-e-mail az eladónak, visszaélés-jelentés, telefon-felfedés, `_views` számláló, kapcsolódó hirdetések, `featured` + Featured/New badge, kedvencek (ha bekapcsolják), RTCL értesítő e-mailek.
- Auth: a free `FormHandler` login/registration/lost/reset handlerei (`wp_loaded`), a `rtcl_login_request`/`rtcl_registration_request` AJAX és a `lostpassword_url` filter **leszedve**; a `myaccount/form-login.php` override a téma `/login/` oldalára visz.
- Single mező-renderer: `Listing::getForm()` → szekciók → `FBField::getFormattedCustomFieldValue()` + `FBHelper::getFormattedFieldHtml()`; szekció-láthatóság `FBHelper::isValidateCondition()`.
- Hirdetésfeladás: RTCL free React Form Builder (`#rtcl-form-builder`) + téma-réteg (szekció-pillek, kitöltöttség, élő előnézet); kategória-választó → `?_fb={form-slug}` (10 root ↔ 10 form).
- Assetek: `rtcl-public` marad; Swiper/PhotoSwipe/Font Awesome dequeue a téma template-jein; Google Maps lazy (IntersectionObserver / kattintás).
- Beállítás-tények: `listing_duration=0` (nem jár le), új/szerkesztett → `pending`, `has_favourites=''`, radius mérföld → km filterrel (DECISION), képek: 5 db / 10 MB / jpeg,jpg,png,webp a form `images` mezőjéből, site-időzóna UTC → Open now `Europe/Madrid` szerint számolva.
- RTCL saját login/regisztráció kikapcsolva; a my-account kijelentkezett nézete a téma `/login/` oldalára irányít.

---

## 5. Auth (5. fázis) — részletes terv

| Lépés | Megvalósítás |
|---|---|
| Oldalak | `/login/` (4999 újrahasznosítva), `/register/` (5014), `/lost-password/` (új) — page template-tel; a téma aktiváláskor létrehozza/hozzárendeli, ha hiányzik |
| Regisztráció | 1. lépés: 3 fiókkártya (radio szemantika) → 2. lépés: mezők; NIE (`custom_field_1`, csak seller) / NIF (`custom_field_2`, csak business) **szerveroldali** validáció mod-23 kontrolbetűvel (NIE `^[XYZ]\d{7}[A-Z]$`, NIF/DNI `^\d{8}[A-Z]$`, CIF `^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$`), kliensoldalon ugyanez; username/email egyediség; jelszó-erősség; ÁSZF checkbox; honeypot + időzítés-alapú bot-szűrő (reCAPTCHA nélkül) |
| Állapotgép | `unconfirmed` → (e-mail link, 48 óra, hash-elt token) → `pending_approval` → (admin) → `active`; `rejected` |
| Login | e-mail vagy username; állapot szerinti üzenetek (A-02: awaiting approval / megerősítetlen + „küldd újra”); `authenticate` filter tiltja a nem-aktívakat; rate-limit (IP+user transient, „X próbálkozás maradt”); `redirect_to` validálva (`wp_validate_redirect`); kontextus-kártya listingről érkezéskor |
| Jelszó-visszaállítás | core `get_password_reset_key` / `check_password_reset_key` / `reset_password`, téma UI és e-mail; egységes válasz (nem árulja el, létezik-e a fiók) |
| Admin | Users lista: „Státusz” oszlop + szűrő, Approve / Reject sorművelet + tömeges; user profilon NIE/NIF megjelenítés |
| Meglévő userek | státusz-meta nélkül = `active` (nincs migráció) |
| wp-login.php | a front-end login/register/lostpassword URL-ek a téma oldalaira mutatnak (`login_url`, `register_url`, `lostpassword_url`); `wp-login.php?action=register` → `/register/`; admin-login működik |
| RTCL | `enable_myaccount_registration` szűrve ki; `default_role` a téma regisztrációján nem érvényes (szerepkör a kártyából, whitelistről) |

---

## 6. Fázisok (v2)

| # | Tartalom | Elfogadás |
|---|---|---|
| 0 | ✅ env, audit, integrációs térkép, terv · ✅ hotfix · ✅ repó a téma mappájában | jóváhagyva |
| 1 ✅ | Téma-váz: `style.css`, bootstrap/autoloader, ModuleRegistry, Settings oldal váz, Installer · tokenek, fontok, ikon-sprite · komponens-könyvtár (PHP partials + CSS + vanilla JS) · rejtett `/styleguide` (F-01…F-05) · `build-zip.sh` · `Compat\FormBuilderRepeater` (élesítési kapu) · **baseline: a site csak free pluginokkal + új témával fatal nélkül fut** | Playwright 1440/390 vs design; `php -l`, PHPCS, Stylelint, ESLint |
| 2 ✅ | Header/footer/drawer/bottom nav/location/nyelvválasztó (GTranslate) + főoldal | + Lighthouse mobil ≥ 90 |
| 3 ✅ | Archive + szűrők (meta_query) + sheet + térkép + nézetváltó + skeleton/üres/vég + kedvencek UI · WPCode audit | + keresés-smoke (`_dev/tests/e2e/archive.mjs`, 39 ellenőrzés) |
| 4 ✅ | Single listing (10 form) + kontakt + galéria + `Reviews` + JSON-LD + lejárt/pending + bejelentés (TLRS port) | + 10 form render-teszt, `_dev/tests/e2e/listing.mjs` (25 ellenőrzés) |
| 5 | `Auth` + `Account` dashboard + `Verification` | + regisztráció 3 role, Mailpit, admin jóváhagyás |
| 6 | `ListingForm` munkaterület + `Quota` + `Chat` · **térképes pin-választó a hirdetésfeladásnál** (Leaflet, a 3. fázis térképével közös; a listing `latitude`/`longitude` metája → pontos sugár- és térképkeresés, a város-közép közelítés csak tartalék) | + 1 hirdetés/kategória, chat polling, pin mentése és megjelenése a térképen |
| 7 | `SearchAlerts` + Guides + statikus oldalak (Elementor → blokk tartalom-migráció) + 404/401/403 | + értesítő cron e-mail |
| 8 | i18n, a11y, performance, SEO audit · **`Consent` modul** (döntés 10) · **élesítési runbook** (plugin-eltávolítási sorrend, hotfix, adat-ellenőrzés, cron) | DoD |

Minden fázis előtt `bin/reset-db.sh`; témaváltás `wp theme activate torrehub|cldirectory-child`; **egyetlen branch: `main`**, fázisonként egy commit.

---

## 7. Kockázatok

| # | Kockázat | Kezelés |
|---|---|---|
| R0 | **`repeater` adatvesztés** Pro nélkül (Service, Property) | `Compat\FormBuilderRepeater` az 1. fázisban; élesítési checklist első pontja; teszt: Pro kikapcsolva szerkesztés után a repeater meta megmarad |
| R1 | Sok újraépítendő funkció a témában (chat, auth, verification, alerts, reviews) → nagyobb felület, saját biztonsági felelősség | modulonként nonce/capability/ownership checklist, PHPCS security sniffek, kézi IDOR-tesztek |
| R2 | A React Form Builder markupja verziófüggő | csak osztály/`data-id` szelektor; fallback = natív RTCL form |
| R3 | Funkciók a témában: témaváltáskor elérhetetlenek (az adat marad) | dokumentálva a README-ben; ez a választott termék-modell |
| R4 | Kevés adat (19 listing) → vizuális teszthez lokális seed (`bin/seed-demo.php`, csak `local`) | Q |
| R5 | ~~Google Maps lokálisan nem tölt~~ | megoldva: Leaflet + OSM (3. fázis). Az OSM csempe-szabályzata mérsékelt forgalmat vár → nagy forgalomnál fizetős csempe (`th_map_tiles`) |
| R6 | GTranslate gépi fordítás DOM-átírás, hosszú szövegek | nincs fix szélesség; switcher `notranslate` |
| R7 | WP-Cron megbízhatatlan alacsony forgalomnál (search alert) | élesen rendszer-cron |
| R8 | Elementor-oldalak tartalma | 7. fázis: tartalom-migráció blokkokba, oldalanként ellenőrizve |

---

## 8. Nyitott kérdések

1. Nyelvlista — a 8-as döntés szerint a GTranslate beállítása az igazság (élesben 9: en, fi, de, hu, ro, ru, es, sv, uk). A design 8-at mutat — a komponens dinamikus, nincs teendő, csak tudomásul.
2. Guides: URL (`/blog/` oldal létezik, `page_for_posts=0`) — javaslat: új `/guides/` oldal posts page-nek; a 8 demo-poszt (Bangkok stb.) törlése/piszkozatba tétele a te döntésed.
3. Élesítéskor mi a front page (most „Coming Soon” 6513)?
4. Logó SVG (header, favicon, sötét változat).
5. Lokális demo-seed adat mehet? (R4)
6. „Typical reply within 1 hour” — a `Chat` modul ki tudja számolni a tényleges medián válaszidőt → megjelenítjük, ha van elég adat (DECISION, alapból rejtve). „Comes to you” = Service form `Mobile Service` mezőjéből.
7. OTP telefon-ellenőrzés (A3) — nem épül, hacsak nem kéritek.
8. Kedvencek / kvóta bekapcsolása — később, beállításból.
9. CLIENT-CONFIRMATION.md további nyitott pontjai (A1 ad type, A2 social login, A4 Member → Seller upgrade, A5 telefon-láthatóság, B1 store, B2 verified kötelező-e, B3 booking, C1–C3 fizetés) — konfigurálható alapértékkel épül, `// DECISION:`.

---

## 9. Környezet (kész, 0. fázis)

| | Éles | Lokális |
|---|---|---|
| URL | `https://torrehub.com` | `http://localhost:10004` |
| WP / PHP / MySQL | 7.1.2 / 8.2.34 / 8.4.7 | 7.1.2 / 8.2.27 / 8.0.35 |
| Prefix | `wp_d5c58b26b6_` | ugyanaz |
| Mail | fluent-smtp | Mailpit (`:10001`, web `:10000`) |

Scriptek (`bin/`): `env.sh`, `setup-local.sh` (idempotens; import karbantartási módban → anonimizálás → külső szolgáltatások leválasztása → `dev`/`dev` admin → snapshot), `anonymize.php`, `detach-services.php`, `mu-plugins/th-local-safety.php`, `reset-db.sh`, `build-zip.sh`. A `backup/` és a snapshot a repón kívül: `BACKUP_DIR` (alap: `C:\Users\Miklos\Local Sites\Torrehub\backup`).

## 10. Biztonság

- **Hotfix** (`hotfix/`): `export_user` blokkolva (mindenkinél — a plugin a hitelesítés előtt fut, admin nem különíthető el); seller-verification upload/delete/**download** csak saját userre (kivétel `edit_users`).
- A régi `909e296` commit élő kulcsokat tartalmaz → rotálni (OpenAI, Pusher, Google Maps, licencek).
- A régi téma nonce nélküli AJAX-ai és a `cldirectory-core` a v2-ben eltávolításra kerülnek.

## 11. Design-leltár döntések (1. fázis)

Részletek: `../design/INVENTORY.md`. Tokenek: `assets/css/tokens.css`.
- Új tokenek a design tényleges értékeiből: `--th-blue-label #2F5C8F`, `--th-success-label #2C6B58`, `--th-on-blue #F1F7FD`, `--th-on-blue-2 #EDF4FC`, `--th-on-photo-muted #C6CFE8`, `--th-disabled #A0A8C0`, `--th-skeleton #F2F2F7`, `--th-blue-loading #2A66A6`, `--th-error-field #FFFDFD`, `--th-ghost-line #3C4766`, `--th-footer-muted #97A1BE`, `--th-clay-line #E5BFAE`, `--th-clay-hover #C75B39`.
- Árnyék: egyetlen `--th-lift` (+ felfelé vetülő változat a mobil sticky barhoz); a design .22–.36 alfái egységesítve.
- Radius: + `--th-r-sheet`, `--th-r-check`; spacing 4px skálára kerekítve; badge 24px; Figtree 800 → 700.
- H1: a képernyők mérete (34–52 / 24–26) az irányadó.
- Ikonok: 68 szimbólum (`assets/icons/sprite.svg`; build: `_dev/tools/build-sprite.mjs`); a `cat-*` a kanonikus kategória-ikon; +11 téma-ikon (nyilak, eye, bell, log-out, upload, external, star, settings, file, send) + `heart-filled`.
- Hiányzó képernyők (23 kód + recover, desktop dashboard, chat, Business reg. 2. lépés, All categories/towns) a meglévő komponensekből, **„design review” jelöléssel**.

## 12. Eltérések a korábbi dokumentumoktól

Lásd v1 (git history) és `RTCL-INTEGRATION-MAP.md` → „Corrections”. Legfontosabbak: wppb nincs; login/register oldal 4999/5014; listing URL `/listings/{slug}/`; helyszín 34 (nem 35); listing lejárat 0; képméret 10 MB; kvóta nem érvényesül; kedvencek ki; időzóna UTC; radius mérföld.

## 13. Maradék plugin-táblák (nem töröljük)

GeoDirectory (8), WooCommerce (34), CookieYes (3), Real Cookie Banner (16), Elementor submissions/notes (6), WPForms (6), WP Data Access (13), WP All Import/Export (12 + `wpie_new_export` role), UsersWP/User Registration (9), Yoast (5), WP Mail SMTP/Post SMTP (4), egyéb (`jet_*`, `cube_relationships`, `cwp_forms_leads`, `countries`, `rctagr`, `real_queue`, `social_users`, `sd_edi_taxonomy_import`, `godaddy_mwc_received_webhooks`, `wpaas_activity_log`, `wpfm_backup`, `ff_scheduled_actions`, `listing_*`). Élesítés után opcionális takarítás — külön döntés.

## 14. WPCode snippetek — audit kész (3. fázis eleje)

Részletek: **`WPCODE-AUDIT.md`**. Röviden:

| ID | Cím | Ítélet | Fázis |
|---|---|---|---|
| 6510 | Filter Builder Active (138 KB) | kiváltja az `Archive` modul; a 14 szűrő-csoport importálva (`th_archive_filters`) | 3 |
| 6030, 6472 | Favorites fix (JS), Listing style (CSS) | felesleges | 3 |
| 5643 | Listing Report System (TLRS) | átírás `Listing\Report`-ba, meglévő meta-kulcsokkal | 4 |
| 6476 | Keep the form open | felesleges (a téma kontakt modulja nyitott) | 4 |
| 5120 | Gallery image as featured | hibás post type miatt sosem futott → elvetve | — |
| 7263, 7280, 7264 | NIF/NIE mező, wp-admin tiltás, account CSS | `Auth` modul (`custom_field_1/2` marad; a snippet `nif_nie` kulcsa üres) | 5 |
| 5121, 5123, 5642, 5684, 6040 | piszkozatok | nem futnak; 5642 szövege → „Staying safe” (4), 5684 → runbook (8), a többi elvetve | — |

## 15. Élesítési runbook — eddigi tételek

1. `hotfix/torrehub-security.php` (most).
2. Téma-zip telepítése (`bin/build-zip.sh`), aktiválás előtt backup.
3. **`wp media regenerate --skip-delete --yes`** — a téma kép-méretei (`th-card`, `th-town` …) a meglévő képekhez, **WebP-ben** (3. fázis DECISION: az alméretek WebP-k, az eredeti marad; a `--skip-delete` megtartja a régi fájlokat, ha tartalom hivatkozik rájuk). Lokálisan 182 kép.
4. Pluginok kivezetése a 1. pont sorrendjében — **Pro csak a `repeater` kapu ellenőrzése után** (R0).
5. WPCode snippetek kivezetése a 14. pont szerint, **a téma aktiválásával egy időben** a már portolt/felesleges snippeteket kikapcsolni: 6030, 6472 (3. fázis), 5643 TLRS, 6476 (4. fázis) — különben a TLRS lebegő „Report” gombja duplán jelenik meg. Lokálisan kikapcsolva 2026-10-06. A többi (7263, 7264, 7280, 6510, 5120) az 5. fázis után; végül a WPCode plugin.
8. `review-schema` (free) plugin kikapcsolása a téma aktiválásakor (a téma adja az értékeléseket és a JSON-LD-t; két séma-forrás ütközne).
7. **Form Builder adathiba javítása élesen:** `wp torrehub fix-option-values` (dry run, kiírja a változásokat), majd `wp torrehub fix-option-values --apply`. A parancs a témával érkezik (csak WP-CLI alatt töltődik). Az üres értékű opciók a címkéjüket kapják értéknek (Restaurants „Price Range” `€`, Leisure „Price” `€`/`€€`/`€€€`). Idempotens; csak a `rtcl_forms.fields`-et írja. Lokálisan lefuttatva 2026-10-06 (4 opció).
6. Site időzóna `Europe/Madrid`; valódi rendszer-cron.

## 17. 3. fázis — döntések és tények

- **Szűrők forrása:** a WPCode „Filter Builder Active” 14 csoportja importálva → `th_archive_filters` (65 mező; 3 kulcs nélküli bejegyzés kimaradt; az Auto/Moto/Boats „füles” gyökér csak a közös szűrőket kapja, az alkategóriák a sajátjukat). A legközelebbi konfigurált ős nyer. Admin: *Megjelenés › Archive filters* (mezők be/ki, címke, sorrend, újra-import). Az LFB opciók érintetlenek.
- **Szűrő-UI mezőtípusonként:** select/checkbox → chipek (több választás, `IN`), ≤ 3 opciós radio → szegmentált (Any + opciók), sok opció → legördülő, egyetlen „yes” checkbox → kapcsoló, number → min–max (adatból számolt határokkal; ha minden érték azonos, rejtve), date → nap. Szöveges mezők (pl. Car Make) → a meglévő értékekből választható lista. **Üres értékű opciók kimaradnak** (a `€` opciók adathibája, runbook 7).
- **URL-ek:** egy keresés = egy URL (`/listing-category/x/?…`, `/listing-location/y/`, `/listings/?…`); üres paraméterek nélkül (302 a tiszta URL-re; utm/gclid megmarad); `f[mező][]=…`. Szűrt/rendezett/lapozott változat `noindex,follow`.
- **Sugár (DECISION):** a listingeknek nincs koordinátája → a sugár a **városközpontok** távolságával számol (10/25/50 km, „Csak ez a város”); „Nearest” rendezés ugyanígy, városonként. **6. fázis:** pin-választó a hirdetésfeladásnál; ahol van saját koordináta, a sugár és a „Nearest” azt használja (Haversine a `latitude/longitude` metán), a város-közép csak tartalék.
- **Térkép:** pin = listing koordináta, ha van; egyébként a város közepe + determinisztikus szórás (≈ 300–900 m), a térkép kiírja: „Pins show the town, not the exact address”.
- **Darabszám:** `?th_count=1` ugyanarra az URL-re → JSON (ugyanaz a fő lekérdezés, nem tér el a megjelenítettől). Oldalméret: Customizer `th_archive_per_page` (12); „Load N more” = sima link a következő oldalra, JS-sel helyben fűz.
- **JS nélkül** minden működik: a Filters link `?th_sheet=1`-gyel nyitott sheetet renderel, sima GET submit, rendezés gombbal.
- **Kártya:** „Contact” → a listing kontakt szekciója (DECISION, A5 nyitott: telefon/WhatsApp nem kerül az archive-ra). Chat gomb a 6. fázisban.
- **Kedvencek:** `favourites.js` az RTCL saját AJAX-ával (`rtcl_favourites`), csak ha az RTCL-ben be van kapcsolva (most ki → rejtve).
- **„Save this search”:** hook-pont `th_archive_head_actions` (SearchAlerts, 7. fázis).
- **Teljesítmény:** az archive CSS-e inline (mint a főoldalé); RTCL front-end kit nem töltődik az archive-on; Leaflet csak a térkép-nézetben. Kép-alméretek WebP-ben (fent, runbook 3).
- **Javított globális hiba:** a dialógus scroll-lock (`html:has(dialog[open])`) JS nélkül, nem modális sheetnél a teljes oldalt görgethetetlenné tette → csak `:modal`.

## 18. 4. fázis — döntések és tények

- **Mezők:** az RTCL a tárolt *értéket* adja („full_time”, „private_owner”) — a `Data\ListingFields` az opció *címkéjét* mutatja (kártyán és single-on is). Csoportok: részletek (csempék), jellemzők (✓ chipek: igen-checkbox, több-checkbox, repeater sorok), linkek (url, fájl), cím, logó.
- **Személyes azonosítók rejtve (DECISION):** NIF/NIE/DNI/CIF, VIN, rendszám nem jelenik meg nyilvánosan, bár a formok „single-on mutasd”-ra vannak állítva. A VUT/RAICV (turisztikai engedélyszám) látszik — a valenciai szabály szerint kötelező a hirdetésben. Filter: `th_listing_private_field`.
- **Kontakt:** a telefonszám/WhatsApp nincs a HTML-ben; kattintásra jön (AJAX, IP-nkénti limit 30/10 perc, az RTCL `_rtcl_reveal_phone_whatsapp` számlálója nő). E-mail üzenet csak belépve (design), az RTCL saját kontakt-e-mailjeivel (Mailpitben ellenőrizve), 5 üzenet/10 perc. JS nélkül minden működik (`?th_contact=1`, sima POST).
- **Az űrlapok a listing URL-jére postolnak, nem az `admin-post.php`-ra:** a WPCode 7280 minden admin-kérést (az admin-post-ot is) a főoldalra irányít nem-admin felhasználóknál. Közös segéd: `th_on_front_post()`.
- **Értékelések:** belépve, hirdetésenként egyszer, a saját hirdetésre nem; moderálás után jelennek meg (a moderátor a WP szokásos e-mailjét kapja); az összesítők az RTCL saját metáiban (`_rtcl_average_rating`, `_rtcl_review_count`, `_rtcl_rating_count`) — jóváhagyás/szerkesztés/törlés után újraszámolva (ellenőrizve). IP-cím nem tárolódik.
- **Bejelentés (TLRS port):** a meta-kulcsok és a `tlrs_threshold` (élesen 50) maradnak; az ok is mentődik (`th_report_log` — a TLRS elnyelte); admin e-mail; a küszöbnél **pending** lesz a hirdetés (nem draft). Admin: *Listings › Reports* (küszöb, okok, „Clear reports”).
- **Lejárt / függő oldal:** az RTCL a sellereknek nem ad WP-szerkesztési jogot, így a WP 404-et adna. A téma a lejárt hirdetést mindenkinek mutatja („See similar”), a függőt/piszkozatot csak a szerzőjének (ID-alapú URL, mert a függő hirdetésnek még nincs slugja); mindkettő `noindex`.
- **JSON-LD:** Product+Offer / LocalBusiness (Restaurant) / TouristAttraction / Event (kezdő dátummal); Jobs-nál nincs (a JobPosting kötelező mezői hiányoznak); AggregateRating, ha van jóváhagyott értékelés; BreadcrumbList, ha nincs SEO-plugin.
- **Térkép:** Leaflet, csak görgetésre tölt; közelítő pozíciónál kör a város körül és „Approximate location” felirat. „Open in Google Maps” link (kulcs nélkül).
- **Videó:** lejátszás előtt semmi nem töltődik a YouTube-ról (kattintás után youtube-nocookie).
- **„Staying safe”:** Customizer-szöveg (a nem publikált 5642 disclaimer helyett). **„Typical reply”** rejtve (Chat, 6. fázis). **Chat gomb** hook-pont: `th_listing_contact_buttons`.
- **„All listings from this seller”** → `/listings/?seller=<nicename>` (új archive-szűrő, „By …” chip).
- **Kapcsolódó hirdetések:** „More in {gyökérkategória}”, 4 kártya, a legközelebbi kategória elöl.

## 16. Mérési napló (Lighthouse 12, mobil, lokális Local site)

| Dátum | Oldal | Állapot | Perf | A11y | BP | SEO* | LCP | FCP | CLS | TBT |
|---|---|---|---|---|---|---|---|---|---|---|
| 2026-10-06 | főoldal | RTCL assetek mindenhol (kiinduló) | 55 | 100 | 100 | 61 | 10,0 s | 6,6 s | 0 | 190 ms |
| 2026-10-06 | főoldal | RTCL-asset trim + képméretek + meta description + inline CSS + async cookie CSS | 91–92 | 100 | 100 | 69 | 3,0 s | 2,2 s | 0 | 0 ms |
| 2026-10-06 | főoldal | ugyanez, cookie-banner plugin nélkül (a téma saját értéke) | 97–100 | — | — | — | 1,6–1,7 s | 1,1 s | 0 | — |

| 2026-10-06 | `/listings/` (archive) | első mérés (PNG kártyakép 514 KB, CSS linkelve) | 74 | 100 | 100 | 58 | 6,5 s | 2,4 s | 0 | 0 ms |
| 2026-10-06 | `/listings/` | WebP alméretek + inline CSS + LCP-kép preload + modálisok a footerben + block-stílusok le | 85–88 | 100 | 100 | 58 | 3,8–3,9 s | 1,8–2,3 s | 0 | 0 ms |
| 2026-10-06 | `/listings/` | ugyanez jQuery és cookie-plugin nélkül (a téma saját értéke) | 97 | 100 | — | — | 2,6 s | 1,2 s | 0 | 30 ms |
| 2026-10-06 | főoldal | 3. fázis után (regresszió-ellenőrzés) | 92 | 100 | 100 | 69 | 3,1 s | 2,2 s | 0 | 0 ms |

⚠ **Archive LCP-büdzsé (< 2,0 s) még nincs meg.** A maradék: (1) a fejben render-blokkoló jQuery + migrate (100 KB) — a WPCode 7263 inline jQuery-je miatt, 5. fázisban megszűnik; (2) a cookie-plugin (8. fázis: saját consent); (3) lokális, cache nélküli TTFB 0,55–0,77 s. A téma saját értéke 2,6 s; a TTFB-t élesben a hosting page cache viszi le. A 8. fázisban újramérjük.

| 2026-10-06 | listing (`/listings/amrit-restaurant/`) | 4. fázis | 83–86 | 100 | 100 | 69 | 3,8–4,1 s | 2,3–2,4 s | 0 | 0–50 ms |
| 2026-10-06 | listing | ugyanez jQuery és cookie-plugin nélkül | 96 | 100 | — | — | 2,7 s | 1,4 s | 0 | 30–40 ms |

\* SEO lokálisan a szándékos `noindex` miatt alacsony (is-crawlable). A nyers JSON-riportok `_dev/reports/` alatt, gitignore-olva (URL-ekben kulcs lehet).

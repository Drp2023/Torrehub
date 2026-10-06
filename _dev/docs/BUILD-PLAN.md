# BUILD-PLAN.md — Torrehub téma (Direction C · Modern Local Hub)

**Verzió:** 2.0 — a 2026-10-02-i döntések szerint (felülírja a promptot és a v1 tervet)
**Állapot:** 0–4. fázis jóváhagyva · 5. fázis kész, jóváhagyásra vár
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
13. **(2026-10-06, ügyfél, GDPR)** Private Seller regisztráció: **nincs NIE mező** (se kötelező, se opcionális). Business Seller: a **NIF (`custom_field_2`) kötelező**, ellenőrzőkarakteres validációval (DNI / NIE-formájú NIF / CIF). A meglévő NIE-értékek (`custom_field_1`, és a 7263-as snippet `nif_nie` kulcsa) **élesítéskor törlendők**; lokálisan már törölve, és sehol nem jelennek meg.
14. **(2026-10-06)** Bejelentési küszöb: **3** (beállítható: *Listings › Reports*).

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
| `insert-headers-and-footers` (free, **WPCode**) | aktív | **eltávolítható** (5. fázis után) | döntés 11: mind a 14 snippet átvéve vagy elvetve (audit: `WPCODE-AUDIT.md`) |
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
| Account endpointok `chat`, `verification` | Pro / SV | a modulok regisztrálják (`rtcl_my_account_endpoint` + `th_account_sections`) | — | 5–6 ✅ |
| Térkép nézet (archive) | Pro widget | `Archive` map view — **Leaflet 1.9.4 (vendored) + OpenStreetMap**, csak megnyitáskor tölt (DECISION: kulcs nélkül, lokálisan is működik; csempe-szolgáltató a `th_map_tiles` filterrel cserélhető) | listing `latitude/longitude` (6. fázistól a pin-választóból), ha van; különben a város középpontja | 3 ✅ |
| Chat | Pro | `Chat` — fiók › Messages, REST + polling (Pusher nélkül, 20.6), e-mail értesítő, badge-ek, `wp torrehub import-chat` | saját táblák `th_chat_threads`, `th_chat_messages` | 6 ✅ |
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
| Ingyenes kvóta (5 / 30 nap) | Store | `Quota` — **alapból kikapcsolva** (döntés 9); beállítás: Megjelenés › Torrehub | számolás a szerző `rtcl_listing` posztjaiból | 6 ✅ |
| Hirdetésfeladás munkaterület | — (új UX) | `ListingForm` — a téma rendereli a Form Builder definíciókból, az RTCL saját AJAX-ával ment (20.1) + Leaflet pin-választó + piszkozatok | RTCL meta (változatlan); piszkozat: `rtcl-temp` poszt `th_draft*` metával | 6 ✅ |
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
- Hirdetésfeladás (6. fázis): a téma rendereli a formot a `rtcl_forms` definíciókból; mentés az RTCL `rtcl_update_listing`, `rtcl_fb_gallery_*`, `rtcl_fb_file_*` AJAX-án (validálás, szanitálás, meta-kulcsok, hookok az RTCL-éi). A React app nem töltődik. Kategória-választó → `?th_cat={term}` (10 root ↔ 10 form a `th_category_map()`-ből; a régi `?_fb={form-slug}` linkek átirányítanak).
- Assetek: `rtcl-public` marad; Swiper/PhotoSwipe/Font Awesome dequeue a téma template-jein; Google Maps lazy (IntersectionObserver / kattintás).
- Beállítás-tények: `listing_duration=0` (nem jár le), új/szerkesztett → `pending`, `has_favourites=''`, radius mérföld → km filterrel (DECISION), képek: 5 db / 10 MB / jpeg,jpg,png,webp a form `images` mezőjéből, site-időzóna UTC → Open now `Europe/Madrid` szerint számolva.
- RTCL saját login/regisztráció kikapcsolva; a my-account kijelentkezett nézete a téma `/login/` oldalára irányít.

---

## 5. Auth (5. fázis) — részletes terv

| Lépés | Megvalósítás |
|---|---|
| Oldalak | `/login/` (4999 újrahasznosítva), `/register/` (5014), `/lost-password/` (új) — page template-tel; a téma aktiváláskor létrehozza/hozzárendeli, ha hiányzik |
| Regisztráció | 1. lépés: 3 fiókkártya (radio szemantika) → 2. lépés: mezők; **NIF (`custom_field_2`) csak business, kötelező** — **szerveroldali** validáció kontrolkarakterrel (DNI `^\d{8}[A-Z]$` mod-23, NIE-formájú NIF `^[XYZ]\d{7}[A-Z]$`, CIF `^[ABCDEFGHJNPQRSUVW]\d{7}[0-9A-J]$`), kliensoldalon ugyanez; **Private Seller: nincs NIE mező (döntés 13)**; username/email egyediség; jelszó-erősség; ÁSZF checkbox; honeypot + időzítés-alapú bot-szűrő (reCAPTCHA nélkül) |
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
| 5 ✅ | `Auth` + `Account` dashboard + `Verification` · WPCode 7263/7264/7280 · jQuery a footerbe | + `_dev/tests/e2e/auth.mjs` (26) és `account.mjs` (22): regisztráció, Mailpit, admin jóváhagyás, jelszó-visszaállítás, hitelesítés |
| 6 ✅ | `ListingForm` munkaterület + `Quota` + `Chat` · **térképes pin-választó a hirdetésfeladásnál** (Leaflet, a 3. fázis térképével közös; a listing `latitude`/`longitude` metája → pontos sugár- és térképkeresés, a város-közép közelítés csak tartalék) | + `_dev/tests/e2e/listing-form.mjs` (37: 1 hirdetés/kategória mind a 10 formmal, pin → térkép, piszkozat, szerkesztés) és `chat.mjs` (23: polling, Seen, e-mail, REST-jogosultság, no-JS, rate limit) |
| 7 | `SearchAlerts` + Guides + statikus oldalak (Elementor → blokk tartalom-migráció) + 404/401/403 | + értesítő cron e-mail |
| 8 | i18n, a11y, performance, SEO audit · **`Consent` modul** (döntés 10) · **élesítési runbook** (plugin-eltávolítási sorrend, hotfix, adat-ellenőrzés, cron) | DoD |

Minden fázis előtt `bin/reset-db.sh`; témaváltás `wp theme activate torrehub|cldirectory-child`; **egyetlen branch: `main`**, fázisonként egy commit.

---

## 7. Kockázatok

| # | Kockázat | Kezelés |
|---|---|---|
| R0 | **`repeater` adatvesztés** Pro nélkül (Service, Property) | `Compat\FormBuilderRepeater` az 1. fázisban; élesítési checklist első pontja; teszt: Pro kikapcsolva szerkesztés után a repeater meta megmarad |
| R1 | Sok újraépítendő funkció a témában (chat, auth, verification, alerts, reviews) → nagyobb felület, saját biztonsági felelősség | modulonként nonce/capability/ownership checklist, PHPCS security sniffek, kézi IDOR-tesztek |
| R2 | ~~A React Form Builder markupja verziófüggő~~ | megszűnt (6. fázis): a téma a form-definíciókból renderel. Új kockázat: ha az RTCL megváltoztatja a `rtcl_update_listing` paramétereit (`formData` parse_str, `listingId`, `formId`) — a `listing-form.mjs` 10 kategóriás tesztje jelzi |
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
6. ~~„Typical reply within 1 hour”~~ — kész (6. fázis): a `Chat` modul méri (medián első válasz, 90 nap, ≥ 3 beszélgetés); megjelenítés a Customizerben kapcsolható (Listing page › Show “Typical reply”), alapból ki. „Comes to you” = Service form `Mobile Service` mezőjéből.
7. OTP telefon-ellenőrzés (A3) — nem épül, hacsak nem kéritek.
8. Kedvencek / kvóta bekapcsolása — később, beállításból (kvóta: Megjelenés › Torrehub › „Free listing allowance” + darabszám/napok).
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
5. **WPCode:** a téma aktiválásával egy időben **mind a 14 snippet kikapcsolása** (különben pl. a TLRS „Report” gombja és a régi NIF/NIE mező duplán jelenik meg; a 7280 a nem-adminokat az `admin-post.php`-ról is kitiltaná), majd a WPCode plugin eltávolítása. Lokálisan mind kikapcsolva 2026-10-06.
6. `review-schema` (free) plugin kikapcsolása a téma aktiválásakor (a téma adja az értékeléseket és a JSON-LD-t; két séma-forrás ütközne).
7. **Bejelentési küszöb:** `wp option update tlrs_threshold 3` (élesen most 50; döntés 14).
8. **NIE-adatok törlése (GDPR, döntés 13):** `wp torrehub purge-nie` (dry run, darabszám), majd `wp torrehub purge-nie --apply` — törli a `custom_field_1` és `nif_nie` user metát; a business NIF (`custom_field_2`) marad. Előtte DB-backup.
9. **Form Builder adathiba javítása élesen:** `wp torrehub fix-option-values` (dry run, kiírja a változásokat), majd `wp torrehub fix-option-values --apply`. A parancs a témával érkezik (csak WP-CLI alatt töltődik). Az üres értékű opciók a címkéjüket kapják értéknek (Restaurants „Price Range” `€`, Leisure „Price” `€`/`€€`/`€€€`). Idempotens; csak a `rtcl_forms.fields`-et írja. Lokálisan lefuttatva 2026-10-06 (4 opció).
10. **Régi hitelesítési dokumentumok (GDPR):** a rtcl-seller-verification kikapcsolása után `wp torrehub purge-old-verification-docs`, majd `--apply` — a Media Libraryben *nyilvánosan* tárolt személyi okmányokat és hivatkozásaikat törli; a már hitelesített eladók badge-e marad. Addig a `hotfix/torrehub-security.php` maradjon fent.
11. **Auth oldalak:** a téma aktiválása után egy admin-oldal megnyitása létrehozza a `/lost-password/` oldalt; ellenőrizni, hogy a `/login/`, `/register/`, `/lost-password/`, `/my-account/` **ki van zárva a hosting page cache-ből** (a téma `nocache` fejlécet küld, de a szerveroldali cache-t érdemes külön is kizárni).
12. **Füstteszt élesen:** regisztráció (business, valós NIF) → megerősítő e-mail → admin jóváhagyás → belépés; jelszó-visszaállítás; hitelesítési feltöltés → döntés → a fájl törlődött.
13. Site időzóna `Europe/Madrid`; valódi rendszer-cron.
14. **Chat-táblák:** a téma aktiválása / az első admin-oldal megnyitása létrehozza a `th_chat_threads` és `th_chat_messages` táblát (Installer, `th_schema_versions`). Ellenőrzés: `wp db query "SHOW TABLES LIKE '%th_chat%'"`.
15. **Régi Pro chat átvétele:** a Pro chat-táblák törlése előtt `wp torrehub import-chat` (dry run), majd `--apply`. Idempotens (a már átvett beszélgetést kihagyja); csak szöveges üzenetek. Lokálisan a Pro táblák üresek; szintetikus adattal tesztelve.
16. **Page cache kizárás:** a `/listing-form/` és a `/my-account/` minden aloldala (a téma `nocache` fejlécet küld). A REST (`/wp-json/torrehub/v1/chat/…`) belépett felhasználóknak legyen elérhető (biztonsági plugin ne tiltsa).
17. **Kvóta:** alapból ki (döntés 9). Ha az ügyfél kéri: Megjelenés › Torrehub › modul be + limit/napok (alap 5 / 30).
18. **Füstteszt:** hirdetésfeladás egy kategóriában fotóval és pinnel → pending → admin jóváhagyás → a pin a térképen és a sugárkeresésben; chat tag ↔ eladó (e-mail megérkezik, a válasz polling-gal megjelenik).

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
| 2026-10-06 | főoldal / archive / listing / login | 5. fázis: jQuery a footerben (WPCode kivezetve), cookie-plugin még fent | 92–94 / 87–89 / 90 / 96 | 100 / 100 / 100 / 100 | 100 | 58–69 | 3,0 / 3,7–3,9 / 3,5 / 2,7 s | 1,2 / 1,1–1,4 / 1,3 / 1,1 s | 0 | 0–30 ms |

| 2026-10-06 | hirdetésfeladás (`/listing-form/?th_cat=41`, belépve) | 6. fázis | 92–93 | 95 → 100 | 100 | — | 3,2 s | 1,4–1,5 s | 0,014 | 10 ms |
| 2026-10-06 | fiók › Messages (belépve) | 6. fázis, RTCL-kit még betöltve | 46 | 100 | 100 | — | 9,0 s | 6,8 s | 0 | 450 ms |
| 2026-10-06 | fiók › Messages / dashboard | a téma által renderelt fiók-szekciókon az RTCL-kit lekapcsolva | 93 / 94 | 100 / 100 | 100 | — | 3,1 / 3,0 s | 1,3 / 1,2 s | 0 | 30 / 0 ms |
| 2026-10-06 | főoldal / listing | 6. fázis regresszió | 94 / 86 | 100 / 100 | 100 | — | 3,0 / 3,8 s | 1,3 / 1,6 s | 0 | 0 / 70 ms |

\* SEO lokálisan a szándékos `noindex` miatt alacsony (is-crawlable). A nyers JSON-riportok `_dev/reports/` alatt, gitignore-olva (URL-ekben kulcs lehet).

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

## 19. 5. fázis — döntések és tények

- **Auth a témában** (`Auth` modul): `/login/` (4999), `/register/` (5014) a téma sablonjával renderel (a régi Elementor-tartalom figyelmen kívül marad), `/lost-password/` az első admin-kéréskor jön létre (lokálisan: 7357). A `wp_login_url`, `wp_registration_url`, `wp_lostpassword_url` ezekre mutat; a `wp-login.php` a staffnak működik, a register/lostpassword akciói a téma oldalaira irányítanak. Az RTCL saját login/regisztráció/jelszó-handlerei és AJAX-ai kikapcsolva, a kijelentkezett fiókoldal a `/login/`-ra visz.
- **Regisztráció:** 1. lépés fióktípus (GET, JS nélkül is), 2. lépés adatok. **Private Seller: nincs NIE** (döntés 13). **Business: NIF kötelező**, kontrolkarakteres ellenőrzés szerveren és böngészőben ugyanazzal az algoritmussal (DNI, NIE-formájú NIF, CIF; 8 esetre egyezik). Jelszó ≥ 10 karakter, nem a név/e-mail. Bot-szűrés captcha nélkül: honeypot + aláírt kitöltési idő (3 s alatt vagy 12 h fölött elutasítva) + IP-nkénti limit (5 fiók/óra). Fióktípus → szerepkör (member → customer, seller, business) + az RTCL buyer/seller jelzője.
- **Állapotgép:** `unconfirmed` → e-mail link (48 h, hash-elt token) → `pending` → admin → `active` | `rejected`. A meglévő fiókok (állapot-meta nélkül) **aktívak** (13 felhasználónak van be nem váltott RTCL e-mail-kulcsa — nem blokkoljuk őket). A nem aktív fiók nem tud belépni (staff kivétel), az oldal állapot szerint válaszol („Awaiting approval”, „Confirm your e-mail first” + újraküldés).
- **Login:** e-mail vagy felhasználónév; 5 hibás próbálkozás IP-re vagy fiókra → 15 perc zár, az utolsó két próbánál figyelmeztet; `redirect_to` validálva; hirdetésről érkezve kontextus-kártya. Elfelejtett jelszó: mindig ugyanaz a válasz, 24 órás, egyszer használható link.
- **Admin:** Users lista „Account” oszlop (típus, állapot, „NIF on file” — a szám soha), nézetek (Awaiting approval / E-mail not confirmed / Rejected), Approve / Reject / Resend sor- és tömeges műveletek, profilon állapot + elutasítási ok + NIF (validálva). Minden döntésről e-mail megy (`Core\Mailer`, HTML + szöveges változat).
- **wp-admin csak staffnak** (a WPCode 7280 portja, képesség alapon: `edit_others_posts`); admin bar is csak nekik. Az AJAX, `admin-post.php`, `async-upload.php` nyitva marad.
- **Fiók** (`Account` modul): a fiókoldal a téma elrendezésében (oldalsó menü, mobilon fülsor), saját dashboard (G-02/G-03: köszöntés madridi idő szerint, Active / Views / Pending csempék, hitelesítési kártya, legutóbbi hirdetések), saját „My listings” (állapot-fülek, szerkesztés, törlés kukába megerősítéssel és tulajdonos-ellenőrzéssel). A kvóta-sáv rejtve (döntés v1 #9); „Unread” a Chat modullal (6. fázis). A tagok (member) menüjéből a My listings / Add listing kimarad. Az RTCL saját fiók-űrlapjai (Account details, Privacy settings) maradnak, a téma stílusával.
- **Eladó-hitelesítés** (`Verification` modul, a rtcl-seller-verification helyett): feltöltés (JPG/PNG/WebP/PDF ≤ 10 MB, tartalom-ellenőrzés, beleegyezés), admin felület (*Users › Verification*, dokumentum megtekintése csak adminnak, nosniff), jóváhagyás → `rtcl_verified_seller` = 1 + e-mail. **DECISION (GDPR): a dokumentum a döntés után azonnal törlődik**, csak az eredmény marad. Tárolás: `uploads/th-private/` véletlen névvel és `<?php exit; ?>` védőfejjel (`.php` kiterjesztés) — közvetlen URL-en üres választ ad Apache-on és nginx-en is (a `.htaccess` egyedül nem elég: a lokális nginx figyelmen kívül hagyja).
- **WPCode:** mind a 14 snippet kivezetve (lokálisan mind draft) — a WPCode plugin élesítéskor eltávolítható.
- **jQuery a footerben** a téma által renderelt oldalakon (főoldal, archive, listing, auth); az RTCL saját képernyőin (hirdetésfeladás, fiók-űrlapok) a fejben marad. Az RTCL jQuery-validátora a listing oldalon le van szedve (a téma nem az RTCL űrlapját használja). FCP ~2,3 s → ~1,2 s.
- **Design-eltérés (a11y):** a login kártyán a „Forgot?” link narancs helyett fehér, aláhúzott (narancs a kéken 3,0:1).
- **E-mailek lokálisan** Mailpitben ellenőrizve: megerősítés, admin-értesítés, jóváhagyás, jelszó-visszaállítás, hitelesítési kérés és döntés.

## 20. 6. fázis — döntések és tények

1. **DECISION — a hirdetésfeladó űrlapot a téma rendereli** (eltérés a tervezett „réteg a React Form Builder fölött” megoldástól). Ok: a szekció-pillek, a feltételes szekciók „Hidden” állapota, az élő előnézet, a fotó-állapotok és a pin-választó olyan markupot igényelnek, amit a React app nem ad ki; a fölé tett réteg verziófüggő lett volna (R2). Az adatút változatlan: a mezők, feltételek és szabályok a `rtcl_forms` táblából jönnek, a mentés az RTCL `rtcl_update_listing` AJAX-a (szerveroldali validálás, szanitálás, meta-kulcsok, `rtcl_listing_form_after_save_or_update` hook), a fotók/fájlok az RTCL feltöltő endpointjai. Mind a 25 használt mezőtípus támogatott. JS nélkül az oldal jelzi, hogy JavaScript kell (ahogy a React app is igényelte).
2. **Kategória-választás (S-02):** gyökér (10 csempe) → alkategóriák, amíg levélhez nem ér; a form a gyökér `th_category_map()` bejegyzéséből, a listing type (`ad_type`, az RTCL elrejti) szintén onnan. A szekciót kapcsoló select („Item type”) választókártya, és a kategória alapján előre ki van választva (Cars → Car). A régi `?_fb=` linkek átirányítanak.
3. **Munkaterület (S-04/S-14):** egyszerre egy szekció; pillek kitöltöttség-számmal, a feltétel miatt kikapcsolt szekció „Hidden” (nem küldődik, nem validálódik — ugyanaz a logika, mint `FBHelper::isValidateCondition`); kliens oldali ellenőrzés a form szabályaiból, a szerver hibái a megfelelő mezőhöz kerülnek; élő előnézet (borító, ár, cím, város); fotók: progress, borító, törlés, típus/méret/darab hiba. A galéria-feltöltés limitjeit a téma **szerveroldalon is** ellenőrzi (az RTCL csak a böngészőben tette). Szerkesztés: `/listing-form/edit/{id}/` ugyanebben a munkaterületben, a kategória nem változtatható (RTCL-szabály), a mentés után újra „pending”.
4. **Piszkozatok:** automatikus mentés 2,5 s-mal az utolsó változás után + „Save & exit”. A piszkozat az RTCL saját ideiglenes posztja (`rtcl-temp`, amit a fotófeltöltő is létrehoz) `th_draft` metával. **DECISION: 30 napig marad** (az RTCL 2 óra után törölné — a téma kiveszi ebből), utána a napi cron fotóstul törli (`th_listing_draft_days`). Lista: a feladás kezdőképernyőjén és a My listings tetején („Continue a draft”, törlés megerősítéssel).
5. **Pin-választó** (Form Builder `map` mező → `latitude`/`longitude`): Leaflet + OSM (közös `lib/leaflet.js`), kattintás/húzás, „Pin at map centre” billentyűzettel, „Use my location”, „Remove pin”, a választott város közepéről indul. **Archive:** a sugár és a „Nearest” Haversine-távolságot számol a listing saját pinjétől, pin nélkül a városa közepétől (`Archive\Module::geo_clauses`); a térkép megjegyzése csak a közelítő pinekre szól. Tesztelve: az Alicante-városú, de Torreviejában pinelt hirdetés a „Torrevieja + 10 km”-ben benne van, az „Alicante + 10 km”-ben nincs.
6. **Chat:** saját táblák, egy beszélgetés = hirdetés + érdeklődő. Fiók › Messages (lista + beszélgetés, mobilon külön nézet), JS nélkül is küld; REST (`torrehub/v1/chat`, cookie + `wp_rest` nonce, csak résztvevő érheti el). **DECISION: polling** (nyitott beszélgetés 5 s, lista 20 s, háttér-fülön szünetel), külső push-szolgáltatás nélkül; a `th_chat_message_sent` hookra később ráköthető. Rate limit 30 üzenet / 10 perc / fő (429). Sima szöveg, max 2000 karakter (HTML és script tartalommal együtt kiszűrve). **E-mail** csak akkor megy, ha a címzettnek abban a beszélgetésben nem volt olvasatlan üzenete. Olvasottság: „Seen”. Badge: fejléc, fiók-menü, mobil alsó menü („Chats” a Saved/Guides helyén belépve, a design szerint), dashboard „Unread” csempe. Belépés nélkül a „Chat” gomb a loginra visz, a saját hirdetésen nincs. Felhasználó törlésekor a beszélgetései is törlődnek (GDPR).
7. **Kvóta** (`Quota`, alapból ki): a szerző utolsó N napban létrehozott hirdetései (bármilyen állapot, a kukában lévő is; a piszkozat nem), staff kivétel; szerveroldali tiltás az RTCL mentésén (`rtcl_fb_extra_form_validation`, csak új hirdetésre), a munkaterület helyett „No free listings left” panel dátummal, sáv a rail-ben és a dashboardon. A modul-kernel új `always()` hookja miatt a beállítás kikapcsolt modulnál is szerkeszthető.
8. **Teljesítmény:** a fiók téma által renderelt szekcióin (dashboard, My listings, Messages, Verification) az RTCL front-end kitje (fejbeli jQuery, Google Maps, Swiper, moment, Font Awesome) nem töltődik (Messages: Perf 46 → 93); az RTCL saját űrlapjain (Account details, Privacy settings) marad. A hirdetésfeladó oldalon sem töltődik az RTCL kit, a React app és a TinyMCE.
9. **Ismert:** az RTCL a fiók saját űrlap-oldalain a Google Maps-et a *live* kulccsal tölti; lokálisan ez időnként `google is not defined` hibát dob (a 6. fázis előtt is így volt). A 8. fázis teljesítmény-auditjában kezeljük.

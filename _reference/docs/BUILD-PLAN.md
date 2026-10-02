# BUILD-PLAN.md — Torrehub téma (Direction C · Modern Local Hub)

**Állapot:** 0. fázis kész — **jóváhagyásra vár** (2 blokkoló döntés, lásd 0. pont)
**Dátum:** 2026-10-02
**Kapcsolódó:** `RTCL-INTEGRATION-MAP.md` (plugin-kód bizonyítékokkal), `../design/INVENTORY.md` (képernyők, komponensek, token-audit)

> Ez a dokumentum **a valódi telepített kódból és az importált (anonimizált) éles adatbázisból** készült. Ahol eltér a korábbi MD-dokumentumoktól, azt a 9. pont sorolja fel — ezekben a kód/DB az igazság.

---

## 0. Blokkolók — itt megállok

### B1. ProfileBuilder (wppb) nincs meg — a regisztrációs terv erre épült

| Tény | Forrás |
|---|---|
| `profile-builder` mappa nincs a backupban és a site-on | `backup/wordpress/wp-content/plugins/` |
| Az éles `active_plugins` (21 db) **nem tartalmazza** | DB `active_plugins`, mentve: `th_live_active_plugins` |
| A backup-log nem említi (nem kizárás miatt hiányzik) | `bmi_logs_this_backup.log` |
| A wppb beállításai (64 `wppb_*` option, v3.16.3) **árván** maradtak a DB-ben | DB |
| A `wppb_user_pages` szerinti oldalak (27210/27211/27212/27213) **nem léteznek** | DB `posts` |
| A valódi login/register oldal: **4999 `/login/`** és **5014 `/register/`** (Elementor), bennük `[wppb-login]` / `[wppb-register]` shortcode | DB `_elementor_data` |
| **Élesben ezek az oldalak jelenleg nyers `[wppb-login]` szöveget mutatnak** — regisztráció/belépés csak a `/my-account/` RTCL-formján működik | lokális render (azonos konfig) |
| A `wppb_license_key` értéke `123456-123456-123456-123456` (tipikus placeholder) | 08-profilebuilder.json |
| 4 usernél van `custom_field_1/2` (NIE/NIF) adat — a meta megmaradt | DB (aggregált) |

**Opciók (te döntesz):**
- **(a)** Megadod a licencelt Profile Builder (Pro/Basic, a „Select (User Role)” + conditional logic + admin approval miatt) telepítőjét → lokálisan telepítem, a brief szerinti wppb-alapú auth épül. Élesre is telepíteni kell, és újra létre kell hozni / össze kell kötni a user oldalakat.
- **(b)** Áttérés az **RTCL saját login/regisztrációjára** (`[rtcl_my_account]`, `registration` endpoint, `rtcl_account_settings.separate_registration_form = yes`, Pro e-mail verifikáció aktív). A témában kell hozzá: role-választó (customer/seller/business), NIE/NIF mező (**a meglévő `custom_field_1` / `custom_field_2` meta-kulccsal**, hogy a 4 meglévő adat megmaradjon), szerveroldali validáció, admin-jóváhagyás (RTCL-ben nincs — kicsi saját modul `pending_approval` user metával + `authenticate` filterrel). Ez **új funkcionalitás a témában**, ezért jóváhagyás kell.
- **(c)** A wp.org-os ingyenes Profile Builder — ellenőrizni kell, hogy a role-select és a conditional mezők benne vannak-e (szerintem nem mind) → valószínűleg nem elég.

**Javaslatom:** ha van érvényes licenc → (a); ha nincs → (b). Addig az 5. fázis (Auth) nem tervezhető véglegesen; az 1–4. fázist nem blokkolja.

### B2. Repó ↔ Local site elhelyezés

A brief egy mappát feltételez, a valóság kettő:
- `C:\Users\Miklos\Local Sites\Torrehub\` — git repó (`Drp2023/Torrehub`), itt van `_reference/`, `backup/`, `bin/`
- `C:\Users\Miklos\Local Sites\torrehub-website\` — a **LocalWP site** (`app/public`, site id `1K5Hwz2sd`, `http://localhost:10004`)
- `…\torrehub-website\app\public\wp-content\themes\torrehub\` **nem üres**: a repó egy második, érintetlen klónja (`909e296`, 0 változás).

**Javaslat:** a téma forrása a repóban: `Torrehub/theme/` (ez maga a téma gyökere, zip-elhető), és egy **directory junction** `themes/torrehub` → `Torrehub/theme`. Ehhez a `themes/torrehub` klónt törölni kell (érintetlen, nincs benne egyedi munka). **Ehhez a törléshez a jóváhagyásodat kérem.**

---

## 1. Környezet (kész)

| | Éles (backup) | Lokális |
|---|---|---|
| Domain | `https://torrehub.com` | `http://localhost:10004` (Local router: localhost mód) |
| WordPress | 7.1.2 | 7.1.2 (Local) |
| PHP | 8.2.34 | 8.2.27 |
| MySQL | 8.4.7 | 8.0.35 (port 10005) |
| Tábla-prefix | `wp_d5c58b26b6_` | ugyanaz |
| Mail | fluent-smtp (valódi SMTP) | **Mailpit** `:10001` (web: `http://localhost:10000`) |

**Scriptek** (`bin/`, Git Bash-ből vagy Local site shellből; a `bin/env.sh` maga állítja be a Local PHP/MySQL/WP-CLI környezetet, így nem kell a Local shell):

| Script | Mit csinál |
|---|---|
| `bin/env.sh` | `source`-olható: PATH (Local PHP 8.2 + MySQL 8.0), `MYSQL_HOME` (Windows-útvonallal), `wp` függvény |
| `bin/setup-local.sh` | idempotens, 11 lépés: GoDaddy drop-inek kikapcsolása (átnevezés) → safety mu-plugin → import (karbantartási módban) → `wp-config` → search-replace → fluent-smtp off → anonimizálás → külső szolgáltatások leválasztása → `dev`/`dev` admin → ellenőrzés → snapshot. **Kétszer egymás után lefuttatva: 180/180 tábla, 0 hiba.** |
| `bin/anonymize.php` | userek (email/login/név/jelszó), NIE/NIF → formailag érvényes hamis (mod-23), telefon/WhatsApp/cím, listing kontakt-meta, review-szerzők, seller-verification hivatkozások, session/app-password; 33 log/submission tábla ürítése. Csak darabszámokat ír ki. |
| `bin/detach-services.php` | OpenAI kulcs, Pusher (kulcsok + `pusher_enable` off → a chat AJAX pollinggal megy), MaxMind, fizetési kulcsok, Fluent Forms captcha, fluent-smtp kapcsolatok, Insert Headers&Footers tracking (másolat `*__live_copy`-ba) → üres; Search Alert események törlése; `blog_public=0` |
| `bin/mu-plugins/th-local-safety.php` | **csak `local` környezetben fut**: minden `wp_mail` → Mailpit (PHP_INT_MAX prioritás), fluent-smtp runtime kiszűrése, noindex |
| `bin/reset-db.sh` | tiszta állapot visszaállítása `backup/local-clean.sql`-ből (~27 mp) |

**Import-megjegyzések** (a scriptben kezelve): a Backup Migration `1790897159_` ideiglenes prefixszel írja a táblákat (stream közben levágva, mert 1 tábla neve így >64 karakter); `0000-00-00` default dátumok miatt laza `sql_mode`; FK-sorrend miatt `FOREIGN_KEY_CHECKS=0`; import közben `.maintenance` (különben a futó site pluginjai újra létrehozzák a táblákat seed-sorokkal).

**Ellenőrizve:** régi téma (`cldirectory-child`) HTTP 200; `siteurl` = Local URL; teszt-email a Mailpitben landol; fluent-smtp inaktív; 0 nem-anonimizált email.

**Nem ellenőrizhető lokálisan:** Google Maps (a kulcs domain-korlátozott → térkép-placeholder), Pusher realtime (lekapcsolva).

---

## 2. Fájlstruktúra (a valódi plugin-verziókhoz igazítva)

```
theme/                                  (= wp-content/themes/torrehub, junctionnel)
├── style.css  functions.php  theme.json  screenshot.png  README.md
├── inc/
│   ├── setup.php            theme supports (+ add_theme_support('rtcl') — KÖTELEZŐ), menük, image size-ok (4:3 kártya, galéria)
│   ├── assets.php           feltételes enqueue; RTCL dequeue @ wp_enqueue_scripts ≥1000; Maps lazy; preload fontok
│   ├── template-tags.php    th_icon, th_badge, th_price (Functions::price wrap), th_listing_card, th_breadcrumb
│   ├── category-map.php     10 root (term ID/slug) → ikon, tint, form ID, form slug (`?_fb=`)
│   ├── location.php         th_location cookie + RTCL paraméterek; geolokáció
│   ├── rtcl/
│   │   ├── compat-elementor-builder.php   rtcl-elementor-builder single-hijack semlegesítése (lásd 4.1)
│   │   ├── hooks-loop.php / hooks-single.php / hooks-account.php
│   │   ├── fields-renderer.php           egységes Form Builder → tile renderer (4.3)
│   │   ├── business-hours.php            open-now Europe/Madrid szerint (4.8)
│   │   ├── filters.php                   pill-sáv + sheet a RTCL GET paramétereivel (4.4)
│   │   └── quota.php                     free-ads kvóta (csak ha az RTCL ténylegesen érvényesíti, 4.7)
│   ├── auth/                ← B1 döntéstől függ (wppb/ VAGY rtcl-auth/)
│   ├── seo.php              csak ami nem ütközik a Review Schema JSON-LD-jével (4.10)
│   ├── compat.php           cldirectory_* és rt_contact no-op shortcode-ok; [listing_filters]; wppb-* (B1 szerint)
│   ├── security.php         régi téma insecure AJAX-ai NEM kerülnek át; X-param blokk (8. pont)
│   └── customizer.php
├── template-parts/{header,footer,components,home,listing,account,guides}/
├── classified-listing/      RTCL override-ok (core + Pro + SA + SV ugyanitt, almappa nélkül)
│   ├── archive-rtcl_listing.php, content-listing.php, listing/loop/*.php, listing/view-switcher.php
│   ├── single-rtcl_listing.php, content-single-rtcl_listing.php, listing/{gallery,map,business-hours,email-to-seller-form,listing-sidebar}.php
│   ├── listing-form/form-builder.php      (React mount köré épített munkaterület-keret)
│   ├── myaccount/{my-account,navigation,dashboard,my-listings,form-edit-account,profile-settings,chat-conversation,search-alert,my-documents,form-login,form-registration,form-lost-password,form-reset-password}.php
│   └── notices/*.php
├── review-schema/           reviews.php + summary/review layout (külön mappa! — RS saját loadere)
├── page-templates/          about, contact, faq, legal, (login/register/recover — B1 szerint), styleguide
├── front-page.php  home.php  single.php  archive.php  page.php  search.php  404.php  index.php
├── assets/{css,js,fonts,icons,img}  (src + build: esbuild + Lightning CSS, a lefordított fájlok is commitolva)
└── languages/torrehub.pot
```

---

## 3. Template-térkép (képernyő-családok → fájl)

A képernyőnkénti részletes leképezés és a komponens-állapotok: **`_reference/design/INVENTORY.md`**. Összefoglaló:

| Design | Megvalósítás |
|---|---|
| F-01…F-05 (alapok) | `page-templates/styleguide.php` — rejtett `/styleguide` oldal, csak `manage_options` / `local` környezetben; vizuális regressziós alap |
| G-01…G-11 (globális) | `header.php`, `footer.php`, `template-parts/header/*` (mega-panel, location pill, drawer), `template-parts/footer/*`, mobil bottom nav |
| P-01 főoldal | `front-page.php` + `template-parts/home/*` |
| P-03…P-14 archive/keresés/térkép/üres | `classified-listing/archive-rtcl_listing.php` + `taxonomy-*` + `template-parts/listing/{filters-bar,filter-sheet,map-view,discovery-tile,skeleton,end-of-results,empty}.php` |
| L-01…L-19 single | `classified-listing/single-rtcl_listing.php` + `content-single-rtcl_listing.php` + részek |
| A-01…A-09 auth | **B1-től függ** |
| Dashboard (G-10 + DASHBOARD-SPEC) | `classified-listing/myaccount/*` |
| S-02/S-04/S-14/S-18 hirdetésfeladás | `classified-listing/listing-form/form-builder.php` + `assets/js/listing-workspace.js` |
| B-01/B-02 Guides | `home.php`, `single.php`, `template-parts/guides/*` |
| T-01…T-08 statikus + hibaoldalak | `page-templates/{about,contact,faq,legal}.php`, `404.php`, 401/403 → `template-parts/errors/*` (`th_render_error(401|403)`) |

---

## 4. RTCL-integrációs döntések (a valódi kód alapján)

### 4.1 rtcl-elementor-builder elviszi az összes single-t — **KRITIKUS**
`template_include` @100, mind a 10 formhoz van `rtcl_tb_template_default_single_{2..11}` → `rtcl_builder` poszt. Az új `single-rtcl_listing.php` **soha nem töltene be**.
**Terv:** a téma `wp`-n `remove_filter`-rel leveszi (guardolva `function_exists('rtclElb')`), opcióhoz nem nyúl (visszaállítható). Élesítéskor javaslat: `elementor`, `elementor-pro`, `rtcl-elementor-builder`, `cldirectory-core`, `rt-framework`, `classified-listing-toolkits` deaktiválása (DoD: Elementor nélkül is működjön) — **a te döntésed, külön lépésben**.

### 4.2 A hirdetésfeladás egy React app
Form Builder ON → PHP csak egy üres `#rtcl-form-builder` mount-ot ad. A design szekcionált munkaterülete (S-04/S-14) ezért **CSS + MutationObserver-alapú JS-réteg** a React DOM fölött (`.rtcl-fb-section`, `.rtcl-fb-field-wrap[data-id]`): szekció-pill navigáció, kitöltöttség-számláló, élő kártya-előnézet. Mentés/validáció/feltételes logika 100% RTCL.
- **Kategória-választó (S-02):** az RTCL **nem kategória, hanem form szerint** választ (`?_fb={form-slug}`); a 10 root ↔ 10 form 1:1 → a drill-in a megfelelő `?_fb=` URL-re visz, az alkategóriát a formon belüli category mező adja.
- „Hidden” szekciók: a React a rejtett szekciót **nem rendereli** → a nav-ban szürke „Hidden” pill csak a form JSON-jából (`rtclFB.form.sections` + `logics`) számolható. Megoldható, de törékeny RTCL-frissítéskor → regressziós teszt kell.
- „Draft saved”: RTCL-ben nincs autosave → csak valódi mentésnél / localStorage-vázlatnál jelenik meg.
- **Kockázat:** a React markup minifikált és verziófüggő; minden RTCL-frissítés után vizuális teszt.

### 4.3 Single: egy közös mező-renderer
`$listing->getForm()` → szekciók → `FBField` → `getFormattedCustomFieldValue()` + `FBHelper::getFormattedFieldHtml()`; szekció-láthatóság `FBHelper::isValidateCondition()`-nel (pl. Car/Motorbike/Boat). Preset mezők (ár, kontakt, térkép, nyitvatartás) külön modulokba. A single-layout builder (`single_layout.active=1` mind a 10 formon) **nem** használjuk — saját layout a design szerint, de a mezősorrendet a form adja.

### 4.4 Archive szűrők
Pill-sáv + bottom sheet, a **valós RTCL paraméterekkel**: `q`, `rtcl_category`, `rtcl_location` (slug; az RTCL JS `/listing-category/{cat}/listing-location/{loc}/` URL-re írja át), `geo_address`, `center_lat/lng`, `distance`, `filters[price][min|max]`, `filters[{field_name}]` (`[min|max]` szám, `[]` választós), `orderby`, `view`. Kereshető mezők: a form mezők `filterable` flagje (pl. Auto/Moto/Boats: 27). A Search Alert „Save search” gombot az SA JS a `.rtcl-active-filters-container` + `.rtcl-clear-filters` markupra injektálja → **ezt a markupot megtartjuk** a saját sávban.

### 4.5 Assetek
- Swiper csak a single galériához kell → saját (vanilla) galéria + lightbox, `rtcl-single-listing` + PhotoSwipe dequeue a singlen.
- Google Maps: dequeue `rtcl-google-map`/`rtcl-map` a singlen és archive-on; IntersectionObserver/kattintásra injektálva (RTCL `gmap.js` utána). A hirdetésfeladásnál és edit-accountnál marad eager (cím-autocomplete).
- `rtcl-public` marad (telefon-felfedés, kontakt, report, AJAX filter).
- Font Awesome (`fontawesome` handle) — dequeue, ahol a mi template-jeink futnak; ha RTCL markupban marad `fa-` ikon, ott SVG-re cseréljük.

### 4.6 Láthatóság (telefon/chat/email)
`rtcl_single_listing_settings.registered_only` = seller info csak belépve (mindkét kulcs be van kapcsolva), user-szintű `_rtcl_display_{phone,whatsapp,email}_public`, chat: `Fns::is_enable_chat()`. A5 (Member látja-e) → `// DECISION:` Customizer-kapcsoló, alapértelmezés = a jelenlegi RTCL viselkedés. **Megjegyzés:** a telefon „maszkolása” az RTCL-ben kozmetikai — a teljes szám benne van a HTML `data-options`-ben.

### 4.7 Free listing kvóta — jelenleg **nincs érvényesítve**
A 5/30 nap a Store plugin `rtcl_membership_settings`-ében van, és csak `enable === 'yes'` esetén fut; most `''`. **A kvóta-modul ezért alapból rejtve** (hamis állapotot nem mutatunk); ha bekapcsoljátok, a `RtclStore\Helpers\Functions::user_is_valid_to_post_as_free()` adja a maradékot. → nyitott kérdés Q7.

### 4.8 Időzóna — az éles site **UTC**-n van
`timezone_string = ''`, `gmt_offset = 0`. Az RTCL `openStatus()` a site-időzónát használja → az „Open now” élesben 1–2 órát téved. **Terv:** a téma a `_rtcl_bhs` adatot `Europe/Madrid` szerint értékeli (`// DECISION:`); **javaslat:** az éles site időzónáját állítsátok `Europe/Madrid`-ra (admin beállítás).

### 4.9 Egyéb RTCL-beállítás tények
- `listing_duration = 0` → **a hirdetések nem járnak le** (a doksik 15 napja egy rejtett legacy opció). L-17 (lejárt) állapot megépül, de jelenleg nem fordul elő.
- Új/szerkesztett hirdetés → `pending` (moderáció, L-18 releváns).
- Kedvencek: `has_favourites = ''` → **ki vannak kapcsolva** (nincs Save gomb, nincs `favourites` endpoint). A design „Saved” elemei rejtve maradnak, amíg nincs bekapcsolva → Q8.
- Radius: RTCL alap **mérföld**, max 300, default 30 → a design km-t mutat → `rtcl_radius_search_options` filterrel km (`// DECISION:`).
- Képek: a formok `images` mezője **5 db / 10 MB / jpeg,jpg,png,webp** (nem 2 MB) — a feltöltő UI ezt olvassa.
- Store, membership, payments, compare, quick view: off → nem építjük, linkek opcióból rejtve.

### 4.10 Reviews + SEO
A Review Schema `comments_template` @99-cel átveszi (RTCL Pro félreáll); override: `theme/review-schema/`. JSON-LD: a Review Schema (+Pro) adja a BreadcrumbList-et, Product/Offer/AggregateRating-et és archive-on az ItemList-et → **a téma ezeket nem írja**, csak a Guides `Article` sémáját és az FAQPage-et, ha nem ütközik. SEO plugin: Yoast táblák vannak, de **nincs aktív SEO plugin** → a téma ad minimális `meta description`-t és canonical-t (filterrel kikapcsolható, ha később lesz SEO plugin).

### 4.11 Nyelv
GTranslate free (`widget_look=float`), **9 nyelv: en, fi, de, hu, ro, ru, es, sv, uk**, kliensoldali gépi fordítás, **nincs URL-prefix** (D4 kérdés így tárgytalan, hacsak nem vesztek GTranslate Pro-t). `th_get_languages()` a GTranslate `fincl_langs`-ból olvas; a saját switcher `a[data-gt-lang].notranslate` linkeket renderel, a lebegő GTranslate widgetet elrejtjük.

---

## 5. Fázisok

| # | Tartalom | Elfogadás |
|---|---|---|
| 0 | ✅ unbundle, inventory, lokális env, anonimizálás, integrációs térkép, ez a terv | **jóváhagyás + B1/B2** |
| 1 | setup, tokenek (`tokens.css`), self-hosted fontok (latin+latin-ext), SVG sprite, komponens-könyvtár `/styleguide`-on (F-01…F-05) | Playwright 1440/390 vs design; PHPCS/Stylelint tiszta |
| 2 | header/footer/drawer/bottom nav/location + főoldal; elementor-builder semlegesítés | + Lighthouse mobil ≥90 főoldal |
| 3 | archive + pill-szűrők + sheet + térkép (lazy) + skeleton/üres/végállapot | + keresés-smoke, Search Alert mentés |
| 4 | single (közös renderer mind a 10 formra — mindhez van élő listing) + kontakt + reviews + lejárt/pending | + 10 form vizuális teszt |
| 5 | Auth (**B1**) + dashboard + account endpointok | + regisztráció 3 role, Mailpit e-mailek |
| 6 | hirdetésfeladás munkaterület (React fölötti réteg) | + 1 hirdetés/kategória feladás |
| 7 | Guides + statikus + 404/401/403 | |
| 8 | i18n, a11y (WCAG 2.2 AA), performance, SEO audit | DoD |

Minden fázis előtt `bin/reset-db.sh`; témaváltás `wp theme activate torrehub|cldirectory-child`; branch fázisonként (`phase-1-foundation` …).

---

## 6. Kockázatok

| # | Kockázat | Kezelés |
|---|---|---|
| R1 | React Form Builder markupja verziófüggő → a munkaterület-réteg eltörhet RTCL-frissítéskor | csak osztály/`data-id` szelektorok, MutationObserver, graceful fallback = natív RTCL form; vizuális regressziós teszt |
| R2 | rtcl-elementor-builder single-hijack | 4.1 |
| R3 | Kevés adat (19 listing, 1 review, 2 valódi Guide) → a képernyők üresebbek, mint a design | **javaslat:** `bin/seed-demo.php` csak lokálisan (hamis listingek a 10 formra) — Q12 |
| R4 | Google Maps lokálisan nem tölt (domain-korlátozott kulcs) | placeholder; vagy adj egy lokális dev kulcsot |
| R5 | GTranslate gépi fordítás átírja a DOM-ot → hosszabb szövegek, layout-törés | nincs fix szélesség, FI/HU/DE teszt; switcher `notranslate` |
| R6 | Elementor-oldalak (About, Contact, FAQ, Legal) tartalma `_elementor_data`-ban | Elementor nélkül a `post_content` HTML-másolata renderelődik — oldalanként ellenőrizni, szükség esetén Gutenbergbe migrálni (tartalom-munka, nem kód) |
| R7 | Sok régi plugin/tábla (GeoDirectory, WooCommerce, CookieYes, RCB, WPForms, wpda…) | nem töröljük, csak dokumentáljuk (10. pont) |
| R8 | Front page élesben a „Coming Soon” (6513, `elementor_canvas`) | a `front-page.php` minden statikus front page-re érvényes → élesítéskor eldöntendő, Q10 |

---

## 7. Nyitott kérdések

**Új (0. fázisból):**
1. **B1** — wppb: licencelt plugin (a) vagy RTCL-auth (b)?
2. **B2** — repó/junction elrendezés + a `themes/torrehub` klón törlése rendben?
3. **Biztonság (élesen!)** — lásd 8. pont: kikapcsoljátok-e most a `cldirectory-core`-t / blokkoljátok az `export_user` paramétert?
4. Nyelvlista: brief 6 (EN ES SV FI RO HU) vs design 8 (EN ES SV RO HU DE NL FR) vs **GTranslate élesben 9 (en fi de hu ro ru es sv uk)**.
5. Guides scope jóváhagyott? Melyik URL (`/blog/` oldal 196 létezik; `page_for_posts = 0`)? A 8 demo-poszt (Bangkok stb.) maradhat?
6. Brief multi-step wizard vs design szekcionált munkaterület — a design szerint építem (megerősítés).
7. Free-ads kvóta: bekapcsoljátok (`membership enable`) vagy maradjon rejtve a modul?
8. Kedvencek (`has_favourites`): bekapcsoljátok? (A design Saved-elemei enélkül rejtve.)
9. Site időzóna `Europe/Madrid`-ra állítása élesen?
10. Élesítéskor mi legyen a front page (most „Coming Soon”)?
11. Logó csak PNG → kell SVG (header, favicon, sötét változat).
12. Lokális demo-seed adat a vizuális tesztekhez — mehet?
13. Kép-források: listing 1. kép = RTCL galéria első képe; town-képek = `rtcl_location` term meta (mind a 34 városnak van) — rendben?
15. **Hiányzó design-képernyők** (11. pont): G-07, P-02, P-05–P-08, P-11, P-12, L-02–L-12, L-16, A-04, A-06, A-08 sehol nincsenek; jelszó-visszaállítás, desktop dashboard + összes `myaccount/*`, chat, Business Seller regisztráció 2. lépés, „All categories / All towns” oldalak sincsenek megrajzolva → a meglévő komponensekből építem, design-review-ra jelölve. Rendben, vagy jön még design?
16. Hardcode-olt design-számok: „35 towns” → valójában **34**; Services 42 / Auto 9 alkategória → DB-ben 37 / 8 — a téma mindig a DB-t mutatja.
14. „Typical reply within 1 hour”, „Comes to you” — nincs ilyen adat (a „Comes to you” a Service form `Mobile Service` radio-jából levezethető) → rejtve / levezetve?

**CLIENT-CONFIRMATION.md 15 kérdése** — továbbra is nyitott (A1–A5, B1–B3, C1–C3, D1–D4). Megjegyzés: D4 (URL prefix) GTranslate free mellett tárgytalan; A3 (OTP): az `rtcl-verification` **telepítve, de élesen inaktív**.

---

## 8. Biztonsági megállapítások (élesen most is érvényes)

| Súlyosság | Hol | Mi |
|---|---|---|
| **Kritikus** | `cldirectory-core/cldirectory-core.php:39` → `demo-users/user-importer.php:116-134` | **Hitelesítés nélkül** `?export_user=1` bármely URL-en kiírja a 2–9 ID-jű userek teljes sorát (email, jelszó-hash) + usermetát a plugin mappájába (`demo-users/users.json`, `usermeta.json`), ami jellemzően publikusan letölthető. A backupban lévő fájlok dátuma = plugin telepítés (2026-06-12) → valószínűleg még nem futott le, de **nyitott**. Javaslat: `cldirectory-core` deaktiválása vagy a paraméter blokkolása (WAF/.htaccess). |
| Magas | `rtcl-seller-verification` AJAX upload/delete | POST-olt `user_id`-ben megbízik, nonce/capability nélkül (IDOR) — mások dokumentumai törölhetők/cserélhetők. Plugin-hiba; a téma nem hívja. |
| Közepes | régi téma `delete_listing_logo_attachment`, `delete_food_attachment` | nonce/jogosultság nélkül — nem portoljuk; téma-váltással megszűnik. |
| Info | `wp-config` adatok | a `backup/bmi_backup_manifest.json` tartalmazza az éles DB jelszót és a saltokat — a `backup/` gitignore-olva, de érdemes tudni. |

Élő rendszerhez nem nyúltam.

---

## 9. Eltérések a korábbi dokumentumoktól (a kód/DB az igazság)

- wppb: „CONFIRMED ACTIVE, lifetime license” → **nincs telepítve, nem aktív**; user oldalai nem léteznek (B1).
- Login/Register oldal ID: 27211/27210 → **4999 / 5014**; recover-oldal nincs (RTCL `lost-password` endpoint van).
- Listing URL: `/listing/{slug}/` → **`/listings/{slug}/`**; archive `/listings/`, `/listing-category/{slug}/`, `/listing-location/{slug}/`.
- Store post type `store` (nem `rtcl_store`); `rtcl_builder` = rtcl-elementor-builder, form-onként (nem kategóriánként).
- Custom mezők: Form Builder, meta-kulcs = mezőnév (`select_mo47kwc1`…), nem `_field_N`; a `[listing_filters]` shortcode **sehol nincs regisztrálva**.
- Listing lejárat: 15 nap → **0 (nem jár le)**; képméret 2 MB → **10 MB** (form-szinten).
- Free kvóta 5/30: beállítva, de **nem érvényesül**; kedvencek: **kikapcsolva**.
- Időzóna: **UTC**; radius: **mérföld**.
- Pro verzió 4.2.5 (nem 4.2.3); cldirectory-core 3.1.2.
- `rtcl-verification` telepítve, **inaktív**; `profile-settings` endpoint slugja `privacy-settings`; `default_role = seller`.
- Teljes lista: `RTCL-INTEGRATION-MAP.md` → „Corrections”.

---

## 11. Design-leltár eredménye (részletek: `../design/INVENTORY.md`, `../design/tokens-audit.json`)

- A design egy statikus, inline-stílusos React-vászon: 18 eszköz-keret (6× 1440, 12× 390). **Hover-állapot, toast, számozott lapozás, toggle-off nincs megrajzolva** → ezeket a tokenekből vezetem le, `/styleguide`-on review-ra.
- Képernyőkódok: 41 címkézett, 8 csak szekciófejlécben, **23 hiányzik** (7. pont / Q15).
- **Token-döntések (1. fázis, `// DECISION:` jelöléssel):**
  - Bekerülnek tokenként (a design ténylegesen használja): label-tinten `#2F5C8F` / `#2C6B58`, kéken szöveg `#F1F7FD`, town-kártya szöveg `#C6CFE8`, disabled szöveg `#A0A8C0`, skeleton `#F2F2F7`, loading gomb `#2A66A6`; scrim/overlay ink-alfák.
  - Clay: a kitöltés `#A8482B`; a design-tábla `#C75B39` értékét (hover) `--th-clay-hover`-ként vesszük fel.
  - Árnyék: a design lebegő elemein ink .22–.36 alfák szerepelnek — **egyetlen `--th-lift` tokenre egységesítem** (brief 3.3), a térkép-pin kivétel nélkül ugyanezt kapja.
  - Radius plusz: `--th-r-sheet: 24px 24px 0 0`, `--th-r-check: 6px`; a 8/18 px egyszeri értékek a legközelebbi tokenre kerekítve.
  - Spacing: a páratlan értékek (5,7,9,11,13,15) a 4px-es skálára kerekítve; badge-magasság egységesen 24px.
  - Figtree 800 (rating-szám) → 700 (brief); a fontok variable woff2-k, latin-ext benne (ő/ű/ș/ț/ă ellenőrizve), vietnami subset elhagyható.
  - H1-méretek: a képernyők 34–52 / 24–26 px-t használnak, a brief skálája 78/52/40/34 → 30–36 → **a képernyőket követem** (vizuális kérdésben a design nyer), a 78 csak a hero.
- Ikonok: 64 SVG (`currentColor`) → sprite. A drawer és a kategória-tile ikonjai 4 kategóriánál eltérnek → **a tile-ikon a kanonikus**. A „WhatsApp” ikon generikus buborék → márkajelzés kell-e (Q-hoz)?
- A kártyakép-arányok (1.77–2.62) nem egyeznek az RTCL vágással (416×270, 600×460) → saját image size-ok (brief: 4:3) + `object-fit`.
- Header: home 80px, egyéb 72px kompakt — két variáns.
- Inputoknál a design nem ad szövegszínt → `--th-ink`.

## 10. Maradék plugin-táblák (nem töröljük, csak dokumentáljuk)

Aktív pluginokhoz nem tartozó táblacsaládok az éles DB-ben (sor-becslés import után; a PII-t tartalmazókat a lokális anonimizálás üríti):

| Család | Táblák | Sorok | Megjegyzés |
|---|---|---|---|
| GeoDirectory `geodir_*` | 8 | 0 | régi kísérlet |
| WooCommerce `wc_*`, `woocommerce_*` | 34 | 0 | |
| CookieYes `cky_*` | 3 | 7 | a `gdpr-cookie-compliance` az aktív |
| Real Cookie Banner `rcb_*` | 16 | 16 | consent-logok lokálisan ürítve |
| Elementor `e_submissions*`, `e_events`, `e_notes*` | 6 | 0 | |
| WPForms `wpforms_*` | 6 | 0 | |
| WP Data Access `wpda_*` | 13 | 0 | |
| WP All Import/Export `pmxi_*`, `pmxe_*`, `wpie_template` | 12 | 0 | + `wpie_new_export` user role (1 user) |
| UsersWP `uwp_*`, User Registration `ur_*`, `user_registration_sessions` | 9 | 0 | |
| Yoast `yoast_*` | 5 | 0 | Yoast nem aktív |
| WP Mail SMTP / Post SMTP | 4 | 7 | lokálisan ürítve |
| Egyéb: `jet_*`, `cube_relationships`, `cwp_forms_leads`, `countries`, `rctagr`, `real_queue`, `social_users`, `sd_edi_taxonomy_import`, `godaddy_mwc_received_webhooks`, `wpaas_activity_log`, `wpfm_backup`, `ff_scheduled_actions`, `listing_*` | | | |

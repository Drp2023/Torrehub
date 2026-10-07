# GO-LIVE-RUNBOOK.md — Torrehub téma élesítése

**Kinek:** aki az élesítést végzi. **SSH/WP-CLI nem feltétel:** minden lépésnek van admin-útja (**Eszközök › Torrehub migration**, angol felületen *Tools › Torrehub migration*), és — ha van SSH — WP-CLI-útja. A kettő ugyanazt a kódot futtatja, és mindkettő a migrációs oldal naplójába ír. A két út keverhető.
**Forrás:** BUILD-PLAN §15 (1–30. tétel), végrehajtási sorrendbe rendezve. A §15 marad a „miért”, ez a „mit, milyen sorrendben”.
**Alapelv:** minden adatot módosító lépés először **próbafuttatás** (dry run: kiírja, mit tenne, nem ír semmit), csak utána **élesítés** (Apply / `--apply`). Az admin-oldalon az „Apply” gomb csak sikeres próbafuttatás után, egy órán át aktív; a két GDPR-törlés ezen felül backup-megerősítést kér. Minden lépés után az ellenőrzés; ha nem stimmel → **Visszaállítás** (H).

> Nincs staging. A lépéseket lokálisan (Local, `bin/reset-db.sh` után) mindkét úton végigjátszottuk (`_dev/tests/e2e/migration.mjs`); élesben alacsony forgalomnál fussanak.

Jelölés: **Admin:** = wp-adminból, **CLI:** = SSH + WP-CLI.

---

## A. Előkészítés (élesítés előtti nap)

| # | Lépés | Ellenőrzés |
|---|---|---|
| A1 | **Biztonsági hotfix fent van:** `wp-content/mu-plugins/torrehub-security.php` (`hotfix/README.md`; fájlkezelővel/SFTP-vel, SSH nem kell). | Bővítmények › Must-Use: „Torrehub — security hotfix” |
| A2 | PHP ≥ 8.1 (élesen 8.2.34 ✓), WordPress ≥ 6.4 (élesen 7.1.2 ✓). | Eszközök › Webhely állapota |
| A3 | **Teljes backup:** adatbázis + `wp-content/uploads` + `wp-content/plugins` + `wp-content/themes`. **Admin:** a hosting backup-felülete vagy a `backup-backup` plugin. **CLI:** `wp db export` + a `wp-content` archiválása. A backup **nem** kerül gitbe és nem a téma mappájába. | a backup-fájlok mérete > 0, letöltve a gépre |
| A4 | Telepítőcsomag: `bin/build-zip.sh` → **`dist/torrehub-1.0.0.zip`** (csak commitolt fájlok; a script megáll, ha dev- vagy érzékeny fájl kerülne bele). **Élesre csak ez mehet.** | a script kimenete: „Built dist/torrehub-1.0.0.zip …” |

## B. Téma telepítése

| # | Lépés | Ellenőrzés |
|---|---|---|
| B1 | Megjelenés › Témák › Új hozzáadása › Téma feltöltése: a zip. **Még ne aktiváld.** | a „Torrehub 1.0.0” téma a listában |
| B2 | **WPCode: mind a 14 snippet kikapcsolása** (§15/5) közvetlenül az aktiválás előtt — különben a „Report” gomb és a régi NIF/NIE mező duplán jelenik meg, a 7280-as snippet pedig kizárná a nem-adminokat az `admin-post.php`-ról. **Admin:** Code Snippets › mind „Inactive”. **CLI:** — (a WPCode-nak nincs parancsa; adminból). | Code Snippets lista: mind „Inactive” |
| B3 | **Téma aktiválása, azonnal utána karbantartási mód be.** **Admin:** Megjelenés › Témák › Torrehub › Aktiválás, majd Eszközök › Torrehub migration › *Maintenance mode* › „Switch on”. **CLI:** `wp theme activate torrehub && wp torrehub maintenance on`. A téma saját karbantartási módja a látogatóknak 503-as „Back soon” oldalt mutat, **a wp-admin és a bejelentkezés működik** (a WordPress saját `.maintenance`-e az admint is kizárná). Az aktiválás után az első admin-oldal létrehozza a téma tábláit, a `/lost-password/` és a `/guides/` oldalt. | kijelentkezve (inkognitóban) „Back soon”; az admin-sávban „Maintenance mode on”; a migrációs oldal *Data check* táblázatában megjelennek a „chat ·” és „saved searches” sorok (= a téma táblái léteznek) |
| B4 | **Kiinduló számok (baseline).** **Admin:** Torrehub migration › *Data check* › „Save as baseline (before)”. **CLI:** `wp torrehub data-check --save-baseline`. Csak darabszámok, személyes adat nélkül. | „Baseline saved …” |
| B5 | `review-schema` (free) kikapcsolása (két séma-forrás ütközne). **Admin:** Bővítmények. **CLI:** `wp plugin deactivate review-schema`. | listing oldalon egy `Restaurant`/`Product` JSON-LD blokk, nem kettő |
| B6 | **Képméretek** (a téma WebP-alméretei; a régi fájlok maradnak). **Admin:** a „Regenerate Thumbnails” (Alex Mills, ingyenes) plugin **ideiglenes** telepítése → Eszközök › Regenerate Thumbnails › a „Delete thumbnail files for old unregistered sizes” **ne** legyen bepipálva → Regenerate All; utána a plugin kikapcsolása és törlése. **CLI:** `wp media regenerate --skip-delete --yes`. Hosszabb ideig futhat. | archive-kártyák `…-600x480.webp` képpel |

## C. Adat-migrációk (mind: próbafuttatás → élesítés)

Sorrend számít: előbb az, ami a még aktív Pro/Elementor adatából olvas. **Admin:** Eszközök › Torrehub migration › a lépés kártyája › „Dry run” → az eredmény a kártyán → „Apply”. **CLI:** a parancs `--apply` nélkül (próba), majd `--apply`-jal.

| # | Lépés | CLI | Mit csinál | Ellenőrzés |
|---|---|---|---|---|
| C1 | *C1 · Form Builder: empty option values* | `wp torrehub fix-option-values` | Az üres értékű opciók megkapják a címkéjüket (élesen ~4 opció). Csak a Form Builder-űrlapok. | a próba listája = az élesítés listája |
| C2 | *C2 · Content pages: Elementor → blocks* | `wp torrehub migrate-pages` | About, Contact, FAQ, Privacy, Terms, Aviso Legal → blokkok + téma-sablonok (az Elementor által tárolt HTML-másolatból). Az eredeti metában + revízióban; a „Roll back” gomb / `--rollback` visszaállít. **Az Elementor csak ezután kapcsolható ki.** | a próba szószámai ≈ egyeznek; a 6 oldal megnyitva rendben |
| C2b | **FAQ oldalcím: „Faq” → „FAQ”** (8. fázis jóváhagyása). **Admin:** Oldalak › „Faq” › cím: `FAQ` › Frissítés (az URL `/faq/` marad). | `wp post update $(wp post list --post_type=page --name=faq --field=ID) --post_title=FAQ` | Az oldal címe az adatban „Faq”; ez jelenik meg a fejlécben és a böngészőfülön. | `/faq/` címe „FAQ”, a böngészőfülön „FAQ – Torrehub” |
| C3 | *C3 · Messages: import the old chat* | `wp torrehub import-chat` | Pro chat → téma chat (csak szöveges üzenetek; ismételt futtatás nem duplikál). | Data check: „chat · conversations / messages” = „old chat · …” |
| C4 | *C4 · Saved searches: import the old alerts* | `wp torrehub import-search-alerts` | rtcl-search-alert → téma mentett keresések (ismételhető). | Data check: „saved searches” = „old saved searches” (a kihagyottak száma az összegzésben) |
| C5 | *C5 · Demo posts and pages to the trash* | `wp torrehub trash-demo` | **Ügyfél-döntés:** 8 demo-poszt + 11 demo-oldal **kukába** (nem végleges törlés; a kezdőlapot és a bejegyzések oldalát soha nem érinti). | a próba listája csak demo-tartalom; utána Bejegyzések/Oldalak › Lomtár |
| C6 | *C6 · GDPR: delete stored NIE numbers* | `wp torrehub purge-nie` | GDPR (döntés 13): `custom_field_1` és `nif_nie` törlése; a business NIF (`custom_field_2`) marad. **Végleges** — admin-úton a „I have a fresh database backup” pipa kell hozzá (A3). | Data check: „user meta · custom_field_1” és „· nif_nie” = 0, „· custom_field_2” változatlan |
| C7 | **Bejelentési küszöb: 3** (döntés 14; élesen most 50). **Admin:** Listings › Reports › mező: 3 › Save. | `wp option update tlrs_threshold 3` | | Listings › Reports: 3 |

## D. Pluginok kivezetése (sorrendben)

**Admin:** Bővítmények › Kikapcsolás. **CLI:** `wp plugin deactivate <név>`. Minden sor után: kezdőlap, egy archive, egy listing, `/my-account/` betöltése hiba nélkül (karbantartási módban adminként látod az oldalt).

| # | Plugin | Teendő | Feltétel / megjegyzés |
|---|---|---|---|
| D1 | `classified-listing-pro` | kikapcsolás | **R0 kapu:** utána egy Service és egy Property hirdetés szerkesztése a `/listing-form/`-on → mentés → a Data check „rtcl_listing · meta rows” értéke nem csökkent (a repeater-mezők megmaradtak). Ha csökkent: Pro vissza, megállni. |
| D2 | `rtcl-elementor-builder`, `elementor-pro`, `elementor` | kikapcsolás | C2 után. |
| D3 | `cldirectory-core`, `rt-framework` | kikapcsolás | csak a régi témának kellett (a hotfix 1. pontja tárgytalanná válik). |
| D4 | `rtcl-seller-verification` | kikapcsolás, **utána** *D4 · GDPR: delete the old verification documents* (**Admin:** Torrehub migration, backup-pipával; **CLI:** `wp torrehub purge-old-verification-docs` → `--apply`) | a Media Libraryben **nyilvánosan** tárolt okmányok törlése; a hitelesített eladók badge-e marad. Amíg a plugin aktív, az „Apply” tiltva. Data check: „photo_id”, „other_document_id” = 0. |
| D5 | `rtcl-search-alert` | kikapcsolás | C4 után. |
| D6 | `classified-listing-store`, `rtcl-verification` | kikapcsolás | |
| D7 | `review-schema-pro` | kikapcsolás | B5 már megtörtént. |
| D8 | `gdpr-cookie-compliance` | kikapcsolás | a téma `Consent` modulja váltja (Megjelenés › Torrehub › *Cookie consent*). Nincs nem-szükséges script → nincs sáv; a láblécben „Cookie settings” mindig van. Később analitika/pixel csak a megfelelő kategóriába, ne a fejlécbe. |
| D9 | `insert-headers-and-footers` (WPCode) | kikapcsolás | mind a 14 snippet átvéve vagy elvetve (`WPCODE-AUDIT.md`); B2 már kikapcsolta őket. |
| D10 | `filester` | kikapcsolás **és törlés** | böngészős fájlkezelő élesen = felesleges támadási felület. |
| D11 | `classified-listing-toolkits`, `duplicate-page` | kikapcsolás | Elementor-widgetek / admin-kényelem; a téma nem használja. |
| D12 | `fluentform` | kikapcsolás | a kapcsolat-űrlap a témáé; a 4 Fluent Forms-űrlapot semmi nem használja. **Előtte:** Fluent Forms › Entries → export (CSV), ha a régi beküldéseket meg kell őrizni (a backup szerint 0 beküldés). |
| D13 | `advanced-custom-fields` | kikapcsolás | egyetlen mezőcsoport (Category Image), a téma nem használja. A meta-adat a DB-ben marad. |
| D14 | Marad: `classified-listing` (free), `gtranslate`, `fluent-smtp` (kézbesíthetőség), `backup-backup` (üzemeltetés). | | |
| D15 | A kikapcsolt pluginok **törlése** csak 1–2 hét stabil működés után (addig gyors visszaállíthatóság). A plugin-táblák maradnak (§13). | | |

## E. Beállítások

| # | Lépés | Ellenőrzés |
|---|---|---|
| E1 | Időzóna `Europe/Madrid`. **Admin:** Beállítások › Általános. **CLI:** `wp option update timezone_string Europe/Madrid`. | |
| E2 | **Valódi cron** (mentett keresések e-mailjei, RTCL-takarítás). Mindhárom úton a `wp-config.php`-be `define( 'DISABLE_WP_CRON', true );` kerül (fájlkezelővel), és 5 percenként fut: **CLI-s hoston:** `*/5 * * * * cd <webroot> && wp cron event run --due-now >/dev/null 2>&1`. **SSH nélkül, cPanel „Cron Jobs”-szal:** `*/5 * * * * wget -q -O - "https://torrehub.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1`. **GoDaddy Managed WordPress (nincs cron-felület):** a `DISABLE_WP_CRON` **ne** kerüljön be (marad a látogatás-alapú WP-Cron), vagy egy külső időzítő (pl. cron-job.org) hívja 5 percenként a fenti `wp-cron.php` URL-t. Események: `th_search_alerts_digest` (óránként), `th_search_alerts_instant` (egyszeri), `rtcl_hourly/daily_scheduled_events`, `rtcl_cleanup_*`. | **Admin:** Eszközök › Webhely állapota — nincs „késésben lévő ütemezett esemény” figyelmeztetés. **CLI:** `wp cron event list` → egyik sem késik 10 percnél többet |
| E3 | **Page cache kizárás** (hosting-felület): `/login/`, `/register/`, `/lost-password/`, `/my-account/*`, `/listing-form/*`, és minden kérés `wordpress_logged_in_*` cookie-val. A REST (`/wp-json/torrehub/v1/…`) belépett usernek elérhető. | belépve a fejlécben a saját neved, kilépve nem (cache-elt oldalon sem) |
| E4 | **„Discourage search engines” KI.** **Admin:** Beállítások › Olvasás. **CLI:** `wp option update blog_public 1`. A téma `wp-sitemap.xml`-t ad (felhasználó-lista és `noindex` oldalak nélkül). | `https://torrehub.com/robots.txt` → `Sitemap:` sor; `wp-sitemap.xml`-ben nincs `users` |
| E5 | Megjelenés › Testreszabás › *Guides & pages*: kapcsolat-címzett (üresen az admin e-mail), nyilvános e-mail (`info@torrehub.com`); iroda-cím és nyitvatartás **üresen marad** (ügyfél-döntés, az ügyféltől jön). | `/contact/` |
| E6 | Kvóta: alapból **ki** (döntés 9). A főoldal szövege kvóta nélkül „Post your listings for free”; bekapcsolva „5 free listings every 30 days” (a beállítást követi). | főoldal „sellers” blokk |
| E7 | FluentSMTP: a `List-Unsubscribe` fejlécet ne írja felül (mentett keresés e-mailek). | teszt-riasztás fejlécei |
| E8 | **Karbantartási mód ki.** **Admin:** Torrehub migration › *Maintenance mode* › „Switch off” (vagy az admin-figyelmeztetés „Switch off” linkje). **CLI:** `wp torrehub maintenance off`. | kijelentkezve a kezdőlap 200-zal jön |

## F. Biztonság élesítés után

1. A régi `909e296` commitban szereplő kulcsok **rotálása** (OpenAI, Pusher, Google Maps, licencek) — ha még nem történt meg.
2. `wp-content/rtcl-fb-debug.log` ne létezzen (a téma törli Form Builder-mentéskor; plugin-frissítés után fájlkezelővel ellenőrizni).
3. `WP_DEBUG_DISPLAY` false élesen; a `debug.log` az első héten naponta átnézve (`Torrehub\` prefixű hibák).
4. A `torrehub-security.php` hotfix maradhat (ha az érintett pluginok nincsenek, nem csinál semmit).

## G. Ellenőrzések

### G1 — aggregált számok (B4 baseline ↔ most)

**Admin:** Torrehub migration › *Data check* — a megváltozott sorok kiemelve. **CLI:** `wp torrehub data-check` (a `changed` oszlopban `*`). Csak darabszámok, személyes adat nélkül.

Elvárt: hirdetések, userek, kategóriák (152), helyszínek (34), Form Builder-űrlapok (10) **változatlanok**; „rtcl_listing · meta rows” nem csökken; NIE (`custom_field_1`, `nif_nie`) = 0 (C6); `custom_field_2` változatlan; `photo_id` / `other_document_id` = 0 (D4); „chat ·” = „old chat ·”, „saved searches” = „old saved searches” (C3–C4); posztok/oldalak: a demo-elemek a `trash` sorban (C5).

### G2 — füstteszt (böngésző, kijelentkezve és belépve)

1. Kezdőlap, `/listings/`, egy kategória, egy város, térkép-nézet, egy listing (galéria, térkép, kontakt, értékelés).
2. Regisztráció business-ként (valós NIF) → megerősítő e-mail → admin jóváhagyás → belépés; jelszó-visszaállítás.
3. Hirdetésfeladás egy kategóriában fotóval és pinnel → pending → admin jóváhagyás → a pin a térképen és a sugárkeresésben.
4. Chat tag ↔ eladó: e-mail megérkezik, a válasz megjelenik.
5. Hitelesítési feltöltés → admin döntés → a feltöltött fájl törlődött, badge látszik.
6. Mentett keresés → új egyező hirdetés → e-mail (cron után) → leiratkozó link működik.
7. Kapcsolat-űrlap → e-mail a címzettnek.
8. Guides, egy cikk, About, **FAQ (cím: „FAQ”)**, Privacy, Terms, Aviso Legal, 404.
9. Lábléc „Cookie settings” megnyílik; a sáv nem jelenik meg (nincs opcionális kód).
10. Mobilon (390 px): alsó navigáció, szűrő-sheet, hirdetésfeladás.
11. Lighthouse mobil a kezdőlapon és egy listingen (cél: Perf ≥ 90; a lokális mérések: BUILD-PLAN §16).
12. Search Console: `wp-sitemap.xml` beküldése; a régi Yoast-sitemap URL-ek (ha voltak) 404-et adnak — ez rendben van.

## H. Visszaállítás

- **Téma-szint (percek):** előbb az oldalak, **a torrehub témával aktívan** — **Admin:** Torrehub migration › C2 › „Roll back”; **CLI:** `wp torrehub migrate-pages --rollback`. Ha a karbantartási mód be van kapcsolva: **Admin:** „Switch off”; **CLI:** `wp torrehub maintenance off`. Utána a régi téma (**Admin:** Megjelenés › Témák; **CLI:** `wp theme activate cldirectory-child`) és a D-ben kikapcsolt pluginok vissza (**Admin:** Bővítmények; **CLI:** `wp plugin activate …`). Az adat (hirdetések, userek, meta) a témától független, megmarad.
- **Teljes:** az A3 backup visszatöltése (DB + `wp-content`) — hosting backup-felület / `backup-backup` vagy `wp db import`.
- Ami nem fordítható vissza backup nélkül: C6 (NIE-törlés) és D4 (okmány-törlés) — szándékosan véglegesek (GDPR).

## I. Élesítés után (1–2 hét)

1. Kikapcsolt pluginok törlése (D15); felesleges plugin-táblák takarítása — külön döntés (§13).
2. Nyitott ügyfél-kérdések: BUILD-PLAN §8 (hirdetés-élettartam a FAQ-ba, iroda-cím/nyitvatartás, logó SVG, CLIENT-CONFIRMATION nyitott pontjai).
3. Téma-frissítés menete: `style.css` Version emelése (1.0.1, 1.1.0 … — a JS/CSS cache-busting ebből jön) → `bin/build-zip.sh` → Megjelenés › Témák › Téma feltöltése (a WordPress felajánlja a meglévő cseréjét).
4. A migrációs oldal (Eszközök › Torrehub migration) a témában marad: a naplója az élesítés nyoma; a lépések ismételt futtatása nem okoz kárt.

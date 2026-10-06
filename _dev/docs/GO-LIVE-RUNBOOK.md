# GO-LIVE-RUNBOOK.md — Torrehub téma élesítése

**Kinek:** aki az élesítést végzi (WP-admin + SSH/WP-CLI hozzáférés a hostinghoz).
**Forrás:** BUILD-PLAN §15 (1–25. tétel) + a 8. fázis tételei, végrehajtási sorrendbe rendezve. A §15 marad a „miért”, ez a „mit, milyen sorrendben”.
**Alapelv:** minden adatot módosító parancs először **dry run** (kiírja, mit tenne), csak utána `--apply`. Minden lépés után az adott ellenőrzés; ha nem stimmel → **Visszaállítás** (H).

> Nincs staging. A lépéseket lokálisan (Local, `bin/reset-db.sh` után) ugyanebben a sorrendben végigjátszottuk; élesben karbantartási ablakban fussanak, alacsony forgalomnál.

---

## A. Előkészítés (élesítés előtti nap)

| # | Lépés | Ellenőrzés |
|---|---|---|
| A1 | **Biztonsági hotfix fent van:** `wp-content/mu-plugins/torrehub-security.php` (`hotfix/README.md`). | Plugins › Must-Use: „Torrehub — security hotfix” |
| A2 | **WP-CLI elérhető** a hoston (`wp --info`). Ha nincs: a hosting-szolgáltatótól SSH/WP-CLI kérése — a C–D lépések parancsai csak WP-CLI-ből futnak. | `wp torrehub --help` a téma aktiválása után |
| A3 | PHP ≥ 8.1 (élesen 8.2.34 ✓), WordPress ≥ 6.5 (élesen 7.1.2 ✓). | Eszközök › Webhely állapota |
| A4 | **Teljes backup:** adatbázis + `wp-content/uploads` + `wp-content/plugins` + `wp-content/themes`. A backup **nem** kerül gitbe és nem a téma mappájába. | a backup-fájlok mérete > 0, letöltve a gépre |
| A5 | Telepítőcsomag: `bin/build-zip.sh` → `dist/torrehub-<verzió>.zip` (csak commitolt fájlok; a script megáll, ha dev- vagy érzékeny fájl kerülne bele). **Élesre csak ez mehet.** | a script kimenete: „Built … files” |
| A6 | **Kiinduló számok rögzítése** (G1 lekérdezések) — ezekhez hasonlítunk a végén. | számok elmentve |

## B. Téma telepítése

| # | Lépés | Ellenőrzés |
|---|---|---|
| B1 | Karbantartási mód be (pl. `wp maintenance-mode activate`). | a kezdőlap a karbantartási üzenetet mutatja kijelentkezve |
| B2 | Megjelenés › Témák › Új hozzáadása › Feltöltés: a zip. **Még ne aktiváld.** | a „Torrehub” téma a listában |
| B3 | **WPCode: mind a 14 snippet kikapcsolása** (§15/5) — a téma aktiválásával egy időben, különben a „Report” gomb és a régi NIF/NIE mező duplán jelenik meg, a 7280-as snippet pedig kizárná a nem-adminokat az `admin-post.php`-ról. | Code Snippets lista: mind „Inactive” |
| B4 | **Téma aktiválása.** Utána egy admin-oldal megnyitása: létrejönnek a téma táblái (`th_chat_threads`, `th_chat_messages`, `th_search_alerts`), a `/lost-password/` és a `/guides/` oldal. | `wp db query "SHOW TABLES LIKE '%th\_%'"` → a 3 tábla; `wp option get th_schema_versions` |
| B5 | `review-schema` (free) kikapcsolása (két séma-forrás ütközne). | listing oldalon egy `Restaurant`/`Product` JSON-LD blokk, nem kettő |
| B6 | Képméretek: `wp media regenerate --skip-delete --yes` (a téma WebP-alméretei; a régi fájlok maradnak). Hosszabb ideig futhat. | archive-kártyák `…-600x480.webp` képpel |

## C. Adat-migrációk (mind: dry run → `--apply`)

Sorrend számít: előbb az, ami a még aktív Pro/Elementor adatából olvas.

| # | Parancs | Mit csinál | Ellenőrzés |
|---|---|---|---|
| C1 | `wp torrehub fix-option-values` | Form Builder: üres értékű opciók megkapják a címkéjüket (élesen ~4 opció). Csak `rtcl_forms.fields`. | dry run lista = apply lista |
| C2 | `wp torrehub migrate-pages` | Elementor-oldalak → blokkok + téma-sablonok (About, Contact, FAQ, Privacy, Terms, Aviso Legal). Eredeti tartalom metában + revízióban; `--rollback` visszaállít. **Az Elementor csak ezután kapcsolható ki.** | dry run szószámok ≈ egyeznek; az 6 oldal megnyitva rendben |
| C3 | `wp torrehub import-chat` | Pro chat → téma chat (idempotens, csak szöveges üzenetek). | „imported N threads / M messages” = a Pro táblák számai (G1) |
| C4 | `wp torrehub import-search-alerts` | rtcl-search-alert → téma mentett keresések (idempotens). | importált = a régi tábla sorai |
| C5 | `wp torrehub trash-demo` | **8. fázis, ügyfél-döntés 1:** 8 demo-poszt + 11 demo-oldal **kukába** (nem végleges törlés; a front page és a posts page-et soha nem érinti). | dry run: a lista csak demo-tartalom; utána Bejegyzések/Oldalak › Lomtár |
| C6 | `wp torrehub purge-nie` | GDPR (döntés 13): `custom_field_1` és `nif_nie` user meta törlése; a business NIF (`custom_field_2`) marad. **Előtte DB-backup** (A4). | G1: NIE-meta = 0, NIF-meta változatlan |
| C7 | `wp option update tlrs_threshold 3` | Bejelentési küszöb (döntés 14; élesen most 50). | Listings › Reports: 3 |

## D. Pluginok kivezetése (sorrendben)

Minden sor után: kezdőlap, egy archive, egy listing, `/my-account/` betöltése fatal nélkül (lásd G2).

| # | Plugin | Teendő | Feltétel / megjegyzés |
|---|---|---|---|
| D1 | `classified-listing-pro` | kikapcsolás | **R0 kapu:** utána egy Service és egy Property hirdetés szerkesztése a `/listing-form/`-on → mentés → a repeater-mezők (G1 meta-szám) változatlanok. Ha nem: Pro vissza, megállni. |
| D2 | `rtcl-elementor-builder`, `elementor-pro`, `elementor` | kikapcsolás | C2 után. |
| D3 | `cldirectory-core`, `rt-framework` | kikapcsolás | csak a régi témának kellett (hotfix 1. pont tárgytalanná válik). |
| D4 | `rtcl-seller-verification` | kikapcsolás | utána: `wp torrehub purge-old-verification-docs` (dry run → `--apply`) — a Media Libraryben **nyilvánosan** tárolt okmányok törlése; a hitelesített eladók badge-e marad. |
| D5 | `rtcl-search-alert` | kikapcsolás | C4 után. |
| D6 | `classified-listing-store`, `rtcl-verification` | kikapcsolás | |
| D7 | `review-schema-pro`, `review-schema` | kikapcsolás | B5 már megtörtént. |
| D8 | `gdpr-cookie-compliance` | kikapcsolás | **8. fázis:** a téma `Consent` modulja váltja (Megjelenés › Torrehub › *Cookie consent*). Nincs nem-szükséges script → nincs sáv; a láblécben „Cookie settings” mindig van. Ha később analitika/pixel kell: a kódot a megfelelő kategóriába kell beilleszteni, ne a fejlécbe. |
| D9 | `insert-headers-and-footers` (WPCode) | kikapcsolás | mind a 14 snippet átvéve vagy elvetve (`WPCODE-AUDIT.md`); B3 már kikapcsolta őket. |
| D10 | `filester` | kikapcsolás **és törlés** | böngészős fájlkezelő élesen = felesleges támadási felület. |
| D11 | `classified-listing-toolkits`, `duplicate-page` | kikapcsolás | Elementor-widgetek / admin-kényelem; a téma nem használja. |
| D12 | `fluentform` | kikapcsolás | **8. fázis:** a kapcsolat-űrlap a témáé; a 4 Fluent Forms űrlap egyike sincs használatban. **Előtte:** Fluent Forms › Entries → export (CSV), ha a régi beküldéseket meg kell őrizni (a backup szerint 0 beküldés). |
| D13 | `advanced-custom-fields` | kikapcsolás | **8. fázis:** egyetlen mezőcsoport (Category Image), a téma nem használja. A meta-adat a DB-ben marad. |
| D14 | Marad: `classified-listing` (free), `gtranslate`, `fluent-smtp` (kézbesíthetőség), `backup-backup` (üzemeltetés). | | |
| D15 | A kikapcsolt pluginok **törlése** csak 1–2 hét stabil működés után (addig gyors visszaállíthatóság). A plugin-táblák maradnak (§13). | | |

## E. Beállítások

| # | Lépés | Ellenőrzés |
|---|---|---|
| E1 | Beállítások › Általános: időzóna `Europe/Madrid`. | |
| E2 | **Rendszer-cron:** `DISABLE_WP_CRON` true a `wp-config.php`-ben + szerver-cron: `*/5 * * * * cd <webroot> && wp cron event run --due-now >/dev/null 2>&1`. A téma eseményei: `th_search_alerts_digest` (óránként), `th_search_alerts_instant` (egyszeri, 1 perc), az RTCL-é: `rtcl_hourly/daily_scheduled_events`, `rtcl_cleanup_*`. | `wp cron event list` → egyik sem „késik” 10 percnél többet |
| E3 | **Page cache kizárás** (hosting): `/login/`, `/register/`, `/lost-password/`, `/my-account/*`, `/listing-form/*`, és minden kérés, ahol `wordpress_logged_in_*` cookie van. A REST (`/wp-json/torrehub/v1/…`) belépett usernek elérhető. | belépve a fejlécben a saját neved, kilépve nem (cache-elt oldalon sem) |
| E4 | Beállítások › Olvasás: **„Discourage search engines” KI** (élesen). A téma `wp-sitemap.xml`-t ad (felhasználó-lista nélkül, a `noindex` oldalak nélkül). | `https://torrehub.com/robots.txt` → `Sitemap:` sor; `wp-sitemap.xml` 7 al-sitemap, `users` nincs |
| E5 | Megjelenés › Testreszabás › *Guides & pages*: kapcsolat-címzett (üresen az admin e-mail), nyilvános e-mail (`info@torrehub.com`); iroda-cím és nyitvatartás **üresen marad** (ügyfél-döntés 4, az ügyféltől jön). | `/contact/` |
| E6 | Kvóta: alapból **ki** (döntés 9). A főoldal szövege kvóta nélkül „Post your listings for free”; bekapcsolva „5 free listings every 30 days” (a beállítást követi). | főoldal „sellers” blokk |
| E7 | FluentSMTP: a `List-Unsubscribe` fejlécet ne írja felül (mentett keresés e-mailek). | teszt-riasztás fejlécei |
| E8 | Karbantartási mód ki (`wp maintenance-mode deactivate`). | |

## F. Biztonság élesítés után

1. A régi `909e296` commitban szereplő kulcsok **rotálása** (OpenAI, Pusher, Google Maps, licencek) — ha még nem történt meg.
2. `wp-content/rtcl-fb-debug.log` ne létezzen (a téma törli Form Builder-mentéskor; plugin-frissítés után ellenőrizni).
3. `WP_DEBUG_DISPLAY` false élesen; `debug.log` első hét: naponta átnézni (`Torrehub\` prefixű hibák).
4. A `torrehub-security.php` hotfix maradhat (ha az érintett pluginok nincsenek, nem csinál semmit).

## G. Ellenőrzések

### G1 — aggregált számok (előtte / utána; személyes adat nélkül)

Ha a hoston nincs `mysql` kliens, ugyanezek `wp eval 'global $wpdb; …'`-vel futtathatók.

```bash
P=$(wp db prefix)
wp db query "SELECT post_type, post_status, COUNT(*) n FROM ${P}posts WHERE post_type IN ('rtcl_listing','post','page','rtcl_cf','attachment') GROUP BY 1,2 ORDER BY 1,2"
wp db query "SELECT COUNT(*) listing_meta FROM ${P}postmeta m JOIN ${P}posts p ON p.ID=m.post_id WHERE p.post_type='rtcl_listing'"
wp db query "SELECT taxonomy, COUNT(*) n FROM ${P}term_taxonomy WHERE taxonomy IN ('rtcl_category','rtcl_location','category') GROUP BY 1"
wp db query "SELECT meta_key, COUNT(*) n FROM ${P}usermeta WHERE meta_key IN ('custom_field_1','custom_field_2','nif_nie','th_verification') GROUP BY 1"
wp user list --format=count; wp db query "SELECT COUNT(*) forms FROM ${P}rtcl_forms"
wp db query "SELECT (SELECT COUNT(*) FROM ${P}th_chat_threads) threads, (SELECT COUNT(*) FROM ${P}th_chat_messages) messages, (SELECT COUNT(*) FROM ${P}th_search_alerts) alerts"
```

Elvárt: hirdetések, userek, kategóriák (152), helyszínek (34), űrlapok (10) száma **változatlan**; `listing_meta` nem csökken; `custom_field_1`/`nif_nie` = 0 (C6 után); `custom_field_2` változatlan; chat/alerts = a régi táblák sorai.

### G2 — füstteszt (böngésző, kijelentkezve és belépve)

1. Kezdőlap, `/listings/`, egy kategória, egy város, térkép-nézet, egy listing (galéria, térkép, kontakt, értékelés).
2. Regisztráció business-ként (valós NIF) → megerősítő e-mail → admin jóváhagyás → belépés; jelszó-visszaállítás.
3. Hirdetésfeladás egy kategóriában fotóval és pinnel → pending → admin jóváhagyás → a pin a térképen és a sugárkeresésben.
4. Chat tag ↔ eladó: e-mail megérkezik, a válasz megjelenik.
5. Hitelesítési feltöltés → admin döntés → a feltöltött fájl törlődött, badge látszik.
6. Mentett keresés → új egyező hirdetés → e-mail (cron után) → leiratkozó link működik.
7. Kapcsolat-űrlap → e-mail a címzettnek.
8. Guides, egy cikk, About, FAQ, Privacy, Terms, Aviso Legal, 404.
9. Lábléc „Cookie settings” megnyílik; a sáv nem jelenik meg (nincs opcionális kód).
10. Mobilon (390 px): alsó navigáció, szűrő-sheet, hirdetésfeladás.
11. Lighthouse mobil a kezdőlapon és egy listingen (cél: Perf ≥ 90; a lokális mérések: BUILD-PLAN §16).
12. Search Console: `wp-sitemap.xml` beküldése; a régi Yoast-sitemap URL-ek (ha voltak) 404-et adnak — ez rendben van.

## H. Visszaállítás

- **Téma-szint (percek):** `wp theme activate cldirectory-child` + a D-ben kikapcsolt pluginok vissza (`wp plugin activate …`). Az adat (hirdetések, userek, meta) a témától független, megmarad. Az oldalak: `wp torrehub migrate-pages --rollback` **a torrehub témával aktívan**, *mielőtt* visszaváltasz (a parancs a témával érkezik).
- **Teljes:** az A4 backup visszatöltése (DB + `wp-content`).
- Ami nem fordítható vissza backup nélkül: C6 (NIE-törlés) és D4 utáni okmány-törlés — ezek szándékosan véglegesek (GDPR).

## I. Élesítés után (1–2 hét)

1. Kikapcsolt pluginok törlése (D15); felesleges plugin-táblák takarítása — külön döntés (§13).
2. Nyitott ügyfél-kérdések: BUILD-PLAN §8 (hirdetés-élettartam a FAQ-ba, iroda-cím/nyitvatartás, logó SVG, CLIENT-CONFIRMATION nyitott pontjai).
3. Téma-frissítés menete: új zip (`bin/build-zip.sh`, `style.css` Version emelve → a JS/CSS cache-busting ebből jön) → Megjelenés › Témák › feltöltés (felülírás).

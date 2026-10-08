# Torrehub téma

Klasszikus PHP WordPress téma a torrehub.com-hoz (Direction C · Modern Local Hub): Costa Blanca-i cégjegyzék, apróhirdetés és helyi infó. Az Elementor + CLDirectory + fizetős Classified Listing-bővítmények helyére lép; **a meglévő adat (hirdetések, userek, kategóriák, helyszínek, űrlapok) változatlanul marad.**

- Követelmény: WordPress ≥ 6.4, PHP ≥ 8.1, a **Classified Listing** (free) plugin.
- Nincs page builder és jQuery-függőség a téma oldalain. A JS natív ES-modulokból áll, amelyeket az oldal igény szerint tölt be; a CSS oldaltípusonként csomagolt és inline.
- Fordítható: text domain `torrehub`, sablon: `languages/torrehub.pot`.

## Telepítés

Élesre **csak** a `bin/build-zip.sh` kimenete mehet (`dist/torrehub-<verzió>.zip`, csak commitolt fájlok, dev-mappák nélkül): Megjelenés › Témák › Feltöltés. Első éles verzió: **1.0.0** (most 1.0.1). Az élesítés teljes menete: **`_dev/docs/GO-LIVE-RUNBOOK.md`** — minden lépés wp-adminból (Eszközök › Torrehub migration) vagy WP-CLI-ből; SSH nem feltétel.

Frissítéskor a `style.css` `Version` sorát emelni kell — a JS/CSS cache-busting ebből jön.

## Pluginok

| Plugin | Szerep |
|---|---|
| `classified-listing` (free) | **kötelező** — hirdetés-CPT, taxonómiák, Form Builder, moderáció, fiók-végpontok |
| `gtranslate` (free) | nyelvválasztó; a téma a beállításából olvassa a nyelveket |
| `fluent-smtp` (free) | ajánlott: e-mail kézbesítés (a téma `wp_mail()`-t használ) |

Minden más (auth, chat, eladó-hitelesítés, mentett keresések, értékelések, kvóta, cookie consent, SEO-meta) a témában van. Ha a Classified Listing nincs aktív, a hozzá kötött modulok nem töltődnek be (az oldal nem áll le).

## Beállítások

| Hol | Mi |
|---|---|
| Megjelenés › **Torrehub** | modulok ki/be (chat, értékelések, mentett keresések, hitelesítés, kvóta, consent …), kvóta limit/napok, **Listing lifetime & renewal** (napok fióktípusonként: Private 15, Business 30, staff 0 = nem jár le; emlékeztető 3 nappal előtte), **Cookie consent** (szöveg, kategóriánkénti scriptek, újrakérés) |
| Megjelenés › **Archive filters** | kategóriánkénti szűrőcsoportok az archive-on |
| Megjelenés › **Testreszabás** | főoldal-blokkok és szövegek, header/footer, közösségi linkek, *Listing page*, *Guides & pages* (kapcsolat-címzett, nyilvános e-mail, iroda/nyitvatartás) |
| Megjelenés › **Listing cards** | mely Form Builder-mezők látszanak a kártyákon |
| Listings › **Reports** | bejelentési küszöb és bejelentett hirdetések |
| Users › **Verification** | eladó-hitelesítési kérelmek |
| Eszközök › **Torrehub migration** | élesítési adat-lépések (próbafuttatás → élesítés, napló), adat-ellenőrzés (baseline ↔ most), karbantartási mód (a látogatóknak 503 „Back soon”, az admin működik) |
| Classified Listing › Settings | kedvencek (be/ki), moderáció, értesítő e-mailek — a téma követi |
| Classified Listing › Form Builder | a 10 hirdetés-űrlap mezői (Pro nélkül is szerkeszthető; a használt opció-értékeket a téma védi) |

Ahol ügyfél-döntés még nincs, a kódban `// DECISION:` jelöli a beállítható alapértéket (lista: `_dev/docs/BUILD-PLAN.md` §8).

## Modulok (`inc/src/Modules/`)

| Modul | Feladat |
|---|---|
| `RtclCompat` | a Classified Listing asset-jeinek visszavágása, Form Builder-védelem, repeater-mező Pro nélkül |
| `Location` | város-választás (`th_location` cookie), sugárkeresés |
| `Auth` | login / regisztráció (Member, Private Seller, Business Seller + NIF) / e-mail megerősítés / admin jóváhagyás / jelszó-visszaállítás |
| `Languages` | GTranslate-alapú nyelvválasztó |
| `Archive` | listázás, szűrők, rendezés, térkép-nézet, mobil szűrő-sheet |
| `Listing` | egyedi hirdetés-oldal, galéria, kontakt, bejelentés, JSON-LD |
| `ListingForm` | hirdetésfeladás / szerkesztés munkaterület, piszkozatok, térképes pin |
| `Quota` | ingyenes hirdetés-keret (alapból ki) |
| `Lifetime` | hirdetés-élettartam fióktípus szerint, ingyenes egykattintásos megújítás (My listings + az e-mail aláírt linkje), lejárat előtti e-mail; a lezárást a Classified Listing óránkénti cronja végzi; FAQ-shortcode `[torrehub_listing_lifetime show=duration\|renewal]` |
| `Reviews` | értékelések + AggregateRating |
| `Account` | fiók-dashboard és szekciók |
| `Verification` | eladó-hitelesítés (feltöltés → admin döntés → badge, a fájl utána törlődik) |
| `Chat` | üzenetek (polling, e-mail értesítő) — saját táblák |
| `SearchAlerts` | mentett keresések, napi/azonnali e-mail — saját tábla |
| `Guides` | `/guides/` cikkek, Article JSON-LD |
| `Content` | About / Contact / FAQ / jogi oldal-sablonok, kapcsolat-űrlap, FAQPage JSON-LD |
| `Consent` | cookie-sáv: a nem szükséges scriptek és beágyazások a hozzájárulásig **nem futnak**; sáv csak akkor, ha van mit kérdezni |
| `Migration` | Eszközök › Torrehub migration: a runbook adat-lépései (`Steps`, ugyanaz a kód, mint a `wp torrehub …` parancsoké), adat-ellenőrzés, karbantartási mód |
| `Styleguide` | rejtett `/styleguide` komponens-könyvtár (csak admin) |

A mag: `inc/src/Core/` (`Theme`, `Module`, `Settings`, `Installer` — saját táblák `dbDelta`-val, `th_schema_versions`; `Mailer`). Procedurális segédek: `inc/*.php` (`seo.php`: description, archív canonical, Open Graph, Organization/WebSite JSON-LD, sitemap-szűrés).

## Adattárolás

- Saját táblák: `{prefix}th_chat_threads`, `{prefix}th_chat_messages`, `{prefix}th_search_alerts`.
- Opciók / theme mod: `th_*` (pl. `th_consent`, `th_auth_pages`, `th_archive_filters`); Customizer: `th_*` theme mod.
- Meta: a hirdetések a Classified Listing kulcsait használják; téma-kulcsok `th_*` (pl. `th_published_at`, `th_draft*`), user: `th_verification`, `custom_field_2` (NIF).
- A téma váltásakor a funkciók elérhetetlenek, **az adat megmarad**.

## WP-CLI

Az adat-lépések alapból próbafuttatások, `--apply`-jal írnak; ugyanezek gombként: Eszközök › Torrehub migration (a két út közös naplót ír). Részletek és sorrend: runbook B–D, G. Súgó: `wp help torrehub`.

`wp torrehub fix-option-values` · `migrate-pages [--rollback]` · `import-chat` · `import-search-alerts` · `trash-demo` · `purge-nie` · `trash-listings` · `purge-old-verification-docs` · `data-check [--save-baseline]` · `maintenance on|off|status`

## Bővítési pontok (filterek / actionök)

`th_module_classes`, `th_settings_sections`, `th_page_css_bundles`, `th_rtcl_assets_needed`, `th_account_sections`, `th_account_lean_sections`, `th_header_actions`, `th_bottom_nav_items`, `th_footer_legal_links`, `th_listing_fields`, `th_listing_card_args`, `th_listing_contact_buttons`, `th_listing_schema`, `th_archive_filter_fields`, `th_consent_script_handles` (enqueue-olt scriptek kategóriába sorolása), `th_share_image`, `th_noindex_page_ids`, `th_listing_lifetime_days` / `th_listing_can_renew` / `th_listing_renewed` (később fizetős csomagok: hosszabb élettartam, feltételhez kötött megújítás — most minden ingyenes), `th_town_coordinates`, `th_hours_timezone`, `th_chat_email_notify`, `th_registration_open`; események: `th_account_registered|approved|rejected`, `th_chat_message_sent`.

## Fejlesztés

```bash
bin/setup-local.sh          # Local site: import → anonimizálás → külső szolgáltatások leválasztása → dev/dev admin
source bin/env.sh           # wp() wrapper a Local PHP-jával
bin/reset-db.sh             # tesztkör előtt: tiszta, anonimizált DB → téma aktív → migrációk → plugin-végállapot → teszt-fiókok
npm run build               # ikon-sprite + CSS-csomagok (assets/css/build/)
npm run lint                # ESLint + Stylelint + PHPCS (WordPress Coding Standards)
bash _dev/tests/run-all.sh  # minden alábbi teszt, csomagonként egy sor
node _dev/tests/e2e/<suite>.mjs   # auth, account, archive, header, listing, listing-form, chat, search-alerts, content, admin-forms, consent, migration, lifetime
node _dev/tests/a11y.mjs    # axe-core WCAG 2.1 AA, 28 URL × 1440/390
node _dev/tests/seo.mjs     # title/description/canonical/robots/OG/JSON-LD/sitemap
bin/build-zip.sh            # telepítőcsomag
```

Szabályok: a `backup/` mappa és bármilyen DB-dump, `.env`, kulcs, plugin-forrás, uploads **soha nem kerül gitbe**; a lokális másolat e-mailjei Mailpitbe mennek, és a keresők elől el van zárva (`bin/mu-plugins/th-local-safety.php`). Egy branch (`main`).

Dokumentáció: `_dev/docs/BUILD-PLAN.md` (döntések, fázisok, mérési napló), `GO-LIVE-RUNBOOK.md`, `RTCL-INTEGRATION-MAP.md`, `WPCODE-AUDIT.md`, `CLIENT-CONFIRMATION.md`.

## Ismert korlátok

- OpenStreetMap-csempék: mérsékelt forgalomra; nagy forgalomnál fizetős csempe-szolgáltató (`th_map_tiles`).
- GTranslate: gépi fordítás a böngészőben; a fordított oldalak nem indexelődnek külön URL-en.
- WP-Cron helyett rendszer-cron kell (mentett keresések e-mailjei).

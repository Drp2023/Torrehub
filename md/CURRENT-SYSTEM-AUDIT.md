# CURRENT-SYSTEM-AUDIT.md
**Torrehub WordPress Listing Site — Teljes Technikai Audit**
Audit dátuma: 2026-08-28
Auditor: Claude Code (claude-sonnet-4-6)

---

## Figyelmeztetés az audit korlátairól

A plugins/ könyvtár **üres** — a production szerveren telepített pluginok kódja lokálisan NEM áll rendelkezésre.
Az audit az alábbiakra támaszkodik:
- `cldirectory` parent theme teljes forráskódja ✅
- `cldirectory-child` child theme forráskódja ✅
- `p.json` — 21 MB-os Elementor `_elementor_data` export (1145 rekord, ebből 40 non-revision) ✅
- A plugin-bundle ZIP-ek metadata-ja (nem unzip-pelve) ✅

Ahol plugin kód kellene, `ADATBÁZIS EXPORT SZÜKSÉGES` vagy `UNKNOWN` jelölés szerepel.

---

## 1. Executive Summary

**Platform**: WordPress + CLDirectory theme (RadiusTheme) v3.2.0
**Listing motor**: Classified Listing (RTCL) plugin + Pro + Store addons — **NEM GeoDirectory**
**Regisztráció**: WP ProfileBuilder (wppb) plugin — `[wppb-login]`, `[wppb-register]`
**Oldal builder**: Elementor + CLDirectory Core + RT Framework
**Review rendszer**: Review Schema + Review Schema Pro
**Kategóriák**: 9 különálló listing típus/kategória, mindegyiknek saját `rtcl_builder` oldalsor

**Kritikus döntés az újraépítéshez**: Az egész rendszer az RTCL plugin körül forog. Az RTCL plugin adatmodelljét (táblák, meta kulcsok, taxonómiák) meg kell érteni az adatbázis exportból az újraépítés előtt.

---

## 2. WordPress Architecture

```
wp-content/
├── themes/
│   ├── cldirectory/          ← Parent theme (RadiusTheme v3.2.0)
│   ├── cldirectory-child/    ← Child theme (minimális override)
│   └── p.json                ← Elementor _elementor_data export (21 MB)
├── plugins/                  ← ÜRES lokálisan (csak index.php)
└── uploads/                  ← Médiafájlok (nem vizsgálva)
```

**WordPress verzió**: Nem meghatározható forráskódból (wp-config.php vizsgálaton kívül)
**PHP minimum**: 7.4 (theme header alapján)
**Főbb JS library-k**: Bootstrap 5.x, Select2, Swiper, ion.rangeSlider, Isotope, Magnific Popup, jQuery

---

## 3. Parent Theme — cldirectory

**Forrás**: RadiusTheme (https://www.radiustheme.com)
**Verzió**: 3.2.0
**Szöveg domain**: `cldirectory`
**Licensz**: RadiusTheme License (kereskedelmi)

### Fő osztályok

| Osztály | Fájl | Felelősség |
|---------|------|-----------|
| `ClDirectory_Main` | `functions.php` | Bootstrap, textdomain betöltés |
| `Constants` | `inc/constants.php` | Statikus útvonal/verzió konstansok |
| `RDTheme` | `inc/rdtheme.php` | Singleton — témaopciók (`get_theme_mod()`) |
| `General_Setup` | `inc/general.php` | Témabeállítás, sidebarok, body classes |
| `Scripts` | `inc/scripts.php` | CSS/JS enqueue, dinamikus stílusok |
| `Shortcode` | `inc/shortcode.php` | 9 listing-oldal shortcode |
| `Layouts` | `inc/layout-settings.php` | Per-oldal layout meghatározás |
| `Helper` | `inc/helper.php` | Statikus utility class |
| `Listing_Functions` | `classified-listing/custom/functions.php` | RTCL hook override-ok, AJAX handlerek |
| `TGM_Config` | `inc/tgm-config.php` | Plugin telepítési konfiguráció |

### Regisztrált Sidebarok

| Sidebar ID | Megnevezés |
|-----------|-----------|
| `sidebar` | Általános sidebar |
| `footer-1` – `footer-4` | Footer widgetek |
| `single-listing-sidebar` | Egyedi listing sidebar |
| `store-sidebar` | Store sidebar |
| `listing-archive-sidebar` | Listing archív sidebar |
| `listing-author-sidebar` | Szerző sidebar |
| `listing-map-archive-sidebar` | Térkép sidebar |

**Felülírt RTCL sidebarok** (CONFIRMED): `rtcl-archive-sidebar`, `rtcl-single-sidebar` — a theme saját sidebarjaival váltja ki.

### Template struktúra

```
cldirectory/
├── classified-listing/         ← RTCL plugin template override-ok
│   ├── archive-rtcl_listing.php
│   ├── single-rtcl_listing.php
│   ├── content-listing.php
│   ├── content-single-rtcl_listing.php
│   ├── custom/                 ← Theme-specifikus testreszabások
│   │   ├── functions.php       ← Fő listing logika
│   │   ├── grid.php            ← Grid kártya layout
│   │   ├── list.php            ← Lista kártya layout
│   │   ├── listing-form.php    ← Form sablon
│   │   ├── food-menu.php       ← Éttermi menü megjelenítő
│   │   └── ...
│   ├── listing-form/           ← Listing beküldési form részek
│   │   ├── form.php
│   │   ├── category.php
│   │   └── information.php
│   ├── listing/                ← Single listing részek
│   │   ├── listable-fields.php
│   │   ├── business-hours.php
│   │   ├── social-profiles.php
│   │   ├── gallery.php
│   │   ├── email-to-seller-form.php
│   │   └── ...
│   ├── elementor/              ← Elementor widget template-ek
│   ├── myaccount/              ← Fiók/dashboard template-ek
│   ├── checkout/               ← Fizetési template-ek
│   ├── emails/                 ← Email template-ek
│   └── claim/                  ← Claim listing form
├── templates/                  ← Egyedi WP page template-ek
│   ├── blank.php               ← Üres oldal (Elementor)
│   ├── listing-map.php         ← Térkép oldal
│   └── ...
└── plugin-bundle/              ← Bundled premium pluginok (ZIP)
```

---

## 4. Child Theme — cldirectory-child

**CONFIRMED**: A child theme minimális — mindössze 3 fájlból áll:

1. **`style.css`** — Csak metadata header, CSS nélkül (parent: `cldirectory`)
2. **`functions.php`** — Csak 2 dolog:
   - `style.css` enqueue (priority 18 via `wp_enqueue_scripts`)
   - Child theme textdomain betöltés
3. **`screenshot.jpg`** — Admin képernyőkép

**Nincs egyetlen template override, hook, filter, shortcode, regisztrációs logika sem a child theme-ben.** Minden valódi logika a parent theme-ben van.

---

## 5. Plugins

### 5.1 Kötelező (TGM config alapján — CONFIRMED)

| Plugin | Forrás | Verzió | Szerep |
|--------|--------|--------|--------|
| **Elementor** | WP.org repo | - | Page builder |
| **Classified Listing** (RTCL free) | WP.org repo | - | Core listing motor |
| **Classified Listing Toolkits** | WP.org repo | - | Kiegészítő eszközök, szűrők |
| **CLDirectory Core** | Bundled ZIP | 3.2.0 | Theme-specifikus Elementor widgetek |
| **RT Framework** | Bundled ZIP | 2.9 | Közös RadiusTheme keretrendszer |
| **Classified Listing Pro** | Bundled ZIP (premium) | 4.2.3 | Pro funkciók: compare, online status, membership |
| **Classified Listing Store** | Bundled ZIP (premium) | 3.2.1 | Vendor/store rendszer |

### 5.2 Opcionális (TGM config + kód utalások alapján)

| Plugin | Forrás | Verzió | Szerep |
|--------|--------|--------|--------|
| **Review Schema** | WP.org repo | - | Vélemény rendszer (alap) |
| **WP Fluent Forms** | WP.org repo | - | Egyedi formok (Home Four oldalon) |
| **Radius Booking** | WP.org repo | - | Foglalási rendszer |
| **Easy Demo Importer** | WP.org repo | - | Demo import |
| **Review Schema Pro** | Bundled ZIP (premium) | 2.0.1 | Fejlett értékelések |

### 5.3 Kódban talált, TGM-ben nem szereplő pluginok (LIKELY)

| Plugin | Namespace/class | Funkció |
|--------|----------------|---------|
| **WP ProfileBuilder (wppb)** | `[wppb-*]` shortcodes | Login/Register/Password recovery |
| **Elementor Pro** | `theme-site-logo`, `mega-menu`, `theme-post-*`, `loop-grid` widgetek | Pro Elementor widgetek |
| **RtclMarketplace** | `RtclMarketplace\Hooks\ActionHooks` | Marketplace buy button, kosár |
| **RtclClaimListing** | `RtclClaimListing\Helpers\Functions` | Listing claim/igénylés funkció |
| **Classified Listing Elementor Addon** (`cldirectory-core`) | `rtcl-listing-items`, `rtcl-listing-headerbtn` widgetek | RTCL specifikus Elementor widgetek |

### 5.4 Komponensek ↔ Felelősség

| Funkció | Felelős komponens |
|---------|-----------------|
| Listing post type | Classified Listing (RTCL free) |
| Listing mezők (alap) | Classified Listing (RTCL free) |
| Listing egyedi mezők | Classified Listing Pro / Toolkits |
| Regisztráció | WP ProfileBuilder (wppb) |
| Account/Dashboard | Classified Listing (RTCL free) |
| User dashboardja | Classified Listing (RTCL) |
| Keresés/szűrés | Classified Listing + Toolkits |
| Kategóriák | Classified Listing (rtcl_category taxonómia) |
| Lokációk | Classified Listing (rtcl_location taxonómia) |
| Képfeltöltés (listing) | Classified Listing (RTCL) |
| Képfeltöltés (logo) | Theme (Listing_Functions::listing_form_save) |
| Moderáció | Classified Listing Pro (LIKELY) |
| Store/vendor | Classified Listing Store |
| Értékelések | Review Schema + Pro |
| Fizetés/csomag | Classified Listing (RTCL) + Pro |
| Oldal layout | Elementor + CLDirectory Core |

---

## 6. GeoDirectory szerepe

**CONFIRMED: GeoDirectory NEM szerepel a rendszerben.**

A téma neve ellenére (CLDirectory = Classified Listing Directory) ez egy **Classified Listing by RadiusTheme** alapú rendszer, nem GeoDirectory.

Helyes plugin: `classified-listing` (slug), namespace `Rtcl\*`

---

## 7. Elementor szerepe

### 7.1 Elementor oldalak és template-ek (p.json alapján)

**Alap oldalak (page post type):**

| ID | Cím | Típus | Tartalom |
|----|-----|-------|---------|
| 40 | Home One | page | `rtcl-listing-search-sortable-form`, `rtcl-listing-items`, listing category widgetek |
| 1660 | Home Two | page | Hasonló, parallax háttérrel |
| 4194 | Home Three | page | `rtcl-listing-search-sortable-form`, `rtcl-listing-cat-box` |
| 3920 | Home Four | page | `fluent-form-widget` is szerepel |
| 5561 | Home New | page | Hasonló Home Three-hez |
| 4999 | Login | page | `[wppb-login]` shortcode |
| 5014 | Register | page | `[wppb-register]` shortcode |
| 2 | Recover Password | page | `[wppb-recover-password]` shortcode |
| 2681 | Pricing | page | `rt-pricing-tab` widget |
| 6513 | Coming Soon | page | Elementor form widget (email feliratkozás) |

**Elementor Library template-ek:**

| ID | Cím | Tartalom típusa | Widgetek |
|----|-----|----------------|---------|
| 5030 | Main Header | Global header | `rtcl-listing-headerbtn`, `mega-menu`, logo |
| 5114 | Main Footer | Global footer | Linkek, email form |
| 5140 | Single Listing Page | Loop (üres!) | Nincs tartalom — `rtcl_builder` veszi át |
| 5154 | Listings | Loop | `rtcl-listing-items` custom widget |
| 5192 | Category Listings | Loop | `theme-post-featured-image`, `theme-post-title` |
| 5199 | Category Page | Archive | `[listing_filters filter_group="auto"]` + `loop-grid` |
| 5260 | Vehicle Category Page | Archive | `[listing_filters filter_group="cars-for-sale"]` + `loop-grid` |
| 7063 | Restaurants & Nightlife Category Page | Archive | `[listing_filters filter_group="auto"]` + `loop-grid` |
| 5220 | Single Page | Loop | Csak heading (minimális) |
| 6784 | Disclaimer | - | Jogi szöveg |

**rtcl_builder template-ek (RTCL saját page builder):**

| ID | Cím | Widgetek |
|----|-----|---------|
| 5409 | Post a job single | `rt-listing-*` widgetek, form builder |
| 6662 | Auto/Moto/Boats | Teljes single listing layout |
| 6688 | Tourist Attraction | Teljes single listing layout |
| 6703 | Leisure/Sports | Teljes single listing layout |
| 6712 | Marketplace | Teljes single listing layout |
| 6717 | Restaurants/Nightlife | Teljes single listing layout (+ food menu) |
| 6725 | Property for Rent or Sale | Social profiles + térkép |
| 6730 | Submit An Event | Social profiles + térkép |
| 6735 | Public Information | Alap layout |
| 6746 | Service | Social profiles + térkép |

### 7.2 Elementor widget típusok azonosítva

| Widget | Típus | Forrás |
|--------|-------|--------|
| `shortcode` | D — Shortcode widget | Elementor natív |
| `heading`, `text-editor`, `image`, `icon`, `button`, `spacer` | B — Natív Elementor | Elementor free |
| `form` | C — Elementor Form | Elementor Pro |
| `theme-site-logo`, `theme-post-featured-image`, `theme-post-title`, `theme-archive-title`, `loop-grid`, `post-info`, `post-comments` | F — Theme widget | Elementor Pro |
| `mega-menu` | F — Theme widget | Elementor Pro |
| `rt-title`, `rt-btn`, `rt-image`, `rt-info-box`, `rt-parallax`, `rt-testimonial-carousel`, `rt-post`, `rt-call-to-action`, `rt-video-icon`, `rt-pricing-tab`, `rt-single-listing-category`, `rt-listing-tab`, `rt-listing-location-single-box` | E — Custom Elementor widget | CLDirectory Core plugin |
| `rtcl-listing-items`, `rtcl-listing-search-sortable-form`, `rtcl-listing-slider`, `rtcl-listing-category-slider`, `rtcl-listing-cat-box`, `rtcl-listing-headerbtn` | E — Custom Elementor widget | CLDirectory Core plugin |
| `fluent-form-widget` | F — Plugin widget | WP Fluent Forms |
| `rt-listing-meta`, `rt-listing-description`, `rt-listing-price`, `rt-listing-image`, `rt-listing-seller-information`, `rt-listing-listing-title`, `rt-listing-form-builder-data`, `rt-listing-social-profiles`, `rt-listing-map` | E — Custom (rtcl_builder) | Classified Listing Pro |
| `rtrs-average-rating-count`, `rtrs-review-summary`, `rtrs-review-list`, `rtrs-review-form` | F — Plugin widget | Review Schema plugin |

---

## 8. Custom Post Types

### 8.1 `rtcl_listing` — Fő listing post type

| Attribútum | Érték |
|-----------|-------|
| **Slug** | `rtcl_listing` |
| **Label** | (plugin határozza meg) |
| **Regisztrálva** | Classified Listing plugin |
| **Kezeli** | RTCL plugin + theme override template-ek |
| **Taxonómiák** | `rtcl_category`, `rtcl_location` |
| **Template (archive)** | `classified-listing/archive-rtcl_listing.php` |
| **Template (single)** | `classified-listing/single-rtcl_listing.php` + rtcl_builder per kategória |
| **Form (létrehozás)** | `classified-listing/listing-form/form.php` (RTCL plugin page-en) |
| **Form (szerkesztés)** | Ugyanaz a form, edit módban |

### 8.2 `rtcl_store` — Vendor store

| Attribútum | Érték |
|-----------|-------|
| **Slug** | `rtcl_store` (LIKELY: `store` is látható a kódban) |
| **Regisztrálva** | Classified Listing Store plugin |
| **Template (single)** | `classified-listing/single-store.php` |
| **Template (archive)** | `classified-listing/archive-store.php` |

### 8.3 `rtcl_builder` — Single listing page builder

| Attribútum | Érték |
|-----------|-------|
| **Slug** | `rtcl_builder` |
| **Regisztrálva** | Classified Listing Pro plugin (LIKELY) |
| **Célja** | Per-kategória single listing oldal template-ek Elementorban |
| **Aktív template-ek** | 10 (lásd 7. fejezet) |

---

## 9. Taxonomies

| Taxonómia slug | Label | Regisztrálva | Hierarchikus |
|---------------|-------|-------------|-------------|
| `rtcl_category` | Listing Category | RTCL plugin | Igen (2+ szint) |
| `rtcl_location` | Listing Location | RTCL plugin | Igen |

**Term meta (rtcl_category):**
- `_rtcl_image` — Kategória kép (WP attachment ID)
- `_rtcl_icon` — Kategória ikon CSS osztály

**Ismert kategóriák** (rtcl_builder page-ek alapján, CONFIRMED):
1. Auto/Moto/Boats
2. Tourist Attraction
3. Leisure/Sports
4. Marketplace
5. Restaurants/Nightlife
6. Property for Rent or Sale
7. Events ("Submit An Event")
8. Public Information
9. Service
+ Jobs/Recruiting ("Post a job single") — LIKELY

**ADATBÁZIS EXPORT SZÜKSÉGES**: A teljes kategória fa (minden szint, slug, term ID) a `wp_terms`, `wp_term_taxonomy`, `wp_termmeta` táblákból.

---

## 10. Account Types

**CONFIRMED**: A rendszer WP ProfileBuilder (wppb) plugint használ regisztrációhoz.

**UNKNOWN**: Pontosan hány account típus létezik, milyen WordPress role-okkal és milyen különbséggel.

**Megfigyelt bizonyítékok**:
- `[wppb-register]` shortcode — valószínűleg különböző regisztrációs form-ok léteznek (privát személy vs. vállalkozás)
- `_rtcl_manager_id` meta key a listingeken — van egy manager/sub-account rendszer (egy listingen van egy "manager" aki kezeli a másik nevében)
- Az RTCL plugin saját account menü rendszere (`Functions::get_account_menu_items()`)

**ADATBÁZIS EXPORT SZÜKSÉGES**: `wp_users`, `wp_usermeta` (különösen wppb és rtcl_ előtagú mezők), `wp_user_roles` option.

---

## 11. User Roles és Capabilities

**UNKNOWN**: Pontos role lista.

**LIKELY**: Az RTCL plugin alapértelmezett role-jai + esetleg wppb által hozzáadott szerepkörök.

**ADATBÁZIS EXPORT SZÜKSÉGES**: `wp_options` WHERE `option_name = 'wp_user_roles'`

---

## 12. Registration Flow

### 12.1 Bejelentkezés

```
Felhasználó → /login oldal (ID: 4999)
  → Elementor shortcode widget
  → [wppb-login] shortcode
  → WP ProfileBuilder plugin kezeli
  → WordPress standard wp_signon() / wp_set_auth_cookie()
```

### 12.2 Regisztráció

```
Felhasználó → /register oldal (ID: 5014)
  → Elementor shortcode widget
  → [wppb-register] shortcode
  → WP ProfileBuilder plugin kezeli
  → wp_create_user() / wp_insert_user() (a plugin hívja)
  → User meta mentés (wppb + rtcl_ mezők)
```

### 12.3 Jelszó visszaállítás

```
Felhasználó → /recover-password (ID: 2)
  → [wppb-recover-password] shortcode
  → WP ProfileBuilder email küldés
```

---

## 13. Registration Fields

**ADATBÁZIS EXPORT SZÜKSÉGES**: A wppb plugin az admin felületen konfigurálható regisztrációs mezőket ment el, ezek az adatbázisban vannak, nem PHP fájlban.

**Biztosan létező user meta mezők** (kódból CONFIRMED):

| Meta key | Típus | Forrás |
|---------|-------|--------|
| `_rtcl_phone` | string | RTCL plugin |
| `_rtcl_whatsapp_number` | string | RTCL plugin |
| `_rtcl_website` | string | RTCL plugin |
| `_rtcl_pp_id` | int (attachment ID) | RTCL plugin |
| `_rtcl_address` | string | RTCL plugin |

**LIKELY létező user meta mezők**:

| Meta key | Forrás |
|---------|--------|
| Wppb regisztrációs mezők | WP ProfileBuilder |
| WordPress standard: `first_name`, `last_name`, `description` | WordPress core |

**ADATBÁZIS EXPORT SZÜKSÉGES**: `wp_usermeta` minta rekordok.

---

## 14. User Profile Fields

**CONFIRMED** (template kódból):

Szerzői profil oldalon (`listing/author-content.php`) megjelenítve:
- Avatar (custom `_rtcl_pp_id` vagy WordPress gravatar, 220px)
- Display name
- Bio / description
- `_rtcl_phone` — telefon (visibility ellenőrzéssel)
- `_rtcl_whatsapp_number` — WhatsApp (visibility ellenőrzéssel)
- Email (visibility ellenőrzéssel — WordPress `user_email`)
- `_rtcl_website` — weboldal
- Social profilok (`Functions::get_user_social_profile($user_id)`)
  - twitter/X (`fa-x-twitter`)
  - Egyéb hálózatok (`rtcl-icon-{network}`)

**Owner info sidebar-on** (`listing-owner-info.php`) megjelenítve:
- `_rtcl_address` — cím
- Email (visibility gated)
- `_rtcl_phone` (visibility gated)
- `_rtcl_whatsapp_number` (visibility gated)
- `_rtcl_website`
- Online/Offline státusz (RtclPro `Fns::is_online()`)
- Social profilok (`do_action('rtcl_single_listing_social_profiles')`)

---

## 15. Listing Types (Kategóriák)

A rendszer egyetlen `rtcl_listing` post type-ot használ, de az rtcl_builder template-ek révén category-specifikus megjelenítés van.

**CONFIRMED listing kategóriák** (p.json rtcl_builder lapok alapján):

| Kategória neve | rtcl_builder ID | Különlegességek |
|---------------|----------------|----------------|
| Auto/Moto/Boats | 6662 | Social profiles + térkép |
| Tourist Attraction | 6688 | Social profiles + térkép |
| Leisure/Sports | 6703 | Térkép (social nélkül) |
| Marketplace | 6712 | Térkép (social nélkül) |
| Restaurants/Nightlife | 6717 | Food menu, térkép |
| Property for Rent or Sale | 6725 | Social profiles + térkép |
| Submit An Event | 6730 | Social profiles + térkép |
| Public Information | 6735 | Térkép (social nélkül) |
| Service | 6746 | Social profiles + térkép |
| Post a job (Jobs?) | 5409 | Form builder data, nincs térkép |

**UNKNOWN**: Ezeknek milyen `rtcl_category` taxonomy term slugjai vannak, és van-e alkategória struktúra.

---

## 16. Listing Fields — KRITIKUS SZEKCIÓ

### 16.1 Standard post meta mezők (CONFIRMED kódból)

| Megjelenő név | Internal name (meta key) | Típus | Tárolt |
|--------------|------------------------|-------|--------|
| Cím (title) | `post_title` | text | `wp_posts` |
| Leírás | `post_content` | textarea/wysiwyg | `wp_posts` |
| Ár | (RTCL saját mező) | text | `wp_postmeta` |
| Max ár | `_rtcl_max_price` | text | `wp_postmeta` |
| Ár típus | `_rtcl_listing_pricing` | radio (fixed/negotiable/free) | `wp_postmeta` |
| Telefon | `phone` | text | `wp_postmeta` |
| Email | `email` | email | `wp_postmeta` |
| Weboldal | `website` | url | `wp_postmeta` |
| WhatsApp | `_rtcl_whatsapp_number` | tel | `wp_postmeta` |
| Logo | `listing_logo_img` (legacy) / `listing_logo` (FB mode) | file/attachment ID | `wp_postmeta` |
| Videó URL | `_rtcl_video_urls` | array (serialized) | `wp_postmeta` |
| Térkép elrejtés | `hide_map` | checkbox | `wp_postmeta` |
| Nézettség | `rt_post_views_count` | int | `wp_postmeta` |
| Manager ID | `_rtcl_manager_id` | int (user ID) | `wp_postmeta` |
| Éttermi menü | `cldirectory_food_list` | serialized array | `wp_postmeta` |

### 16.2 Éttermi menü struktúra (`cldirectory_food_list` meta)

```php
[
  [
    'gtitle'    => string,       // Menü csoport neve
    'food_list' => [
      [
        'attachment_id' => int,  // WP attachment ID (kép)
        'title'         => string,
        'foodprice'     => string,
        'description'   => string,
      ],
      // ...
    ]
  ],
  // ...
]
```

### 16.3 Egyedi mezők (Custom Field Groups — CFG)

**KRITIKUS**: Az egyedi mezők (custom fields) az RTCL plugin saját adatbázis tábláiban vannak tárolva, **NEM PHP fájlokban és NEM wp_postmeta-ban kizárólagosan**.

**ADATBÁZIS EXPORT SZÜKSÉGES**:
- RTCL custom fields táblák (valószínű nevek: `wp_rtcl_cf`, `wp_rtcl_cfg`, `wp_rtcl_cfgm` vagy hasonló)
- A tényleges mező értékek tárolási módja (wp_postmeta vs. custom table)

Az egyedi mezők típusai a kódból:
- `text` — szöveges mező
- `textarea` — hosszú szöveg
- `select` — legördülő
- `radio` — választógombok
- `checkbox` — jelölőnégyzetek
- `number` — szám
- `url` — URL
- `repeater` — ismétlődő mező csoport
- `date` — dátum
- `file` — fájlfeltöltés (PDF, kép)

### 16.4 Listing form input name-ek (CONFIRMED)

| Input name | Típus | Tartalom |
|-----------|-------|---------|
| `type` | select | Hirdetés típusa |
| `title` | text | Listing cím |
| `price_type` | select | Ár típus |
| `price` | text | Ár |
| `_rtcl_max_price` | text | Max ár |
| `_rtcl_listing_pricing` | radio | Árazási modell |
| `description` | textarea | Leírás |
| `listing_logo_img` | file | Logo feltöltés |
| `logo_attachment_id` | hidden | Meglévő logo ID |
| `cldirectory_food_list[N][gtitle]` | text | Éttermi csoport cím |
| `cldirectory_food_list[N][food_list][M][title]` | text | Étel neve |
| `cldirectory_food_list[N][food_list][M][foodprice]` | text | Étel ára |
| `cldirectory_food_list[N][food_list][M][description]` | textarea | Étel leírása |
| `cldirectory_food_list[N][food_list][M][attachment_id]` | hidden | Étel kép ID |
| `cldirectory_food_images[N][food_list][M]` | file | Étel kép feltöltés |

**Form elem**: `<form id="rtcl-post-form" enctype="multipart/form-data">`

### 16.5 Keresési form input name-ek (CONFIRMED)

| Input name | Típus | Tartalom |
|-----------|-------|---------|
| `q` | text (autocomplete) | Kulcsszó keresés |
| `rtcl_category` | select | Kategória szűrő |
| `rtcl_location` | select | Lokáció szűrő |
| `geo_address` | text | Geo cím |
| `center_lat` | hidden | Szélességi fok |
| `center_lng` | hidden | Hosszúsági fok |
| `distance` | range slider | Keresési sugár (km/miles) |
| `filters[price][min]` | hidden/text | Min ár |
| `filters[price][max]` | hidden/text | Max ár |
| `filters[_field_N]` | varies | Egyedi mező szűrők |

---

## 17. Listing Submission Flow

### Létrehozás

```
1. Felhasználó bejelentkezik
2. Dashboard → "Add Listing" → Link::get_listing_form_page_link()
3. Listing form oldal betöltődik (plugin kezeli)
4. Form: <form id="rtcl-post-form" enctype="multipart/form-data">
   ├── Ad type selection (ha nem disabled)
   ├── Category selection (parent + dynamic sub-category AJAX)
   └── Information section (rtcl_listing_form hook)
       ├── Logo upload (theme: listing_logo_img)
       ├── Title (post_title)
       ├── Pricing (pricing model, price type, price, max price)
       ├── Custom fields (wp_ajax_rtcl_custom_fields_listings AJAX)
       ├── Description (textarea vagy TinyMCE)
       └── Restaurant food menu (ha restaurant kategória + enabled)
5. Submit → Plugin AJAX handler feldolgoz
6. Hook: rtcl_listing_form_after_save_or_update (plugin)
   → Priority 12: Listing_Functions::listing_form_save() (theme)
      ├── Logo: wp_handle_upload() → wp_insert_attachment() → update_post_meta('listing_logo_img')
      └── Food list: update_post_meta('cldirectory_food_list', sanitized_array)
7. Post status: UNKNOWN (valószínűleg 'pending' moderációval vagy 'publish' közvetlenül)
8. Listing megjelenik frontend-en
```

### Szerkesztés

Ugyanaz a form, edit módban. Az `rtcl_listing_form_after_save_or_update` hook ugyanúgy fut, de update_post_meta hívással.

---

## 18. Listing Editing

**CONFIRMED**: Ugyanaz a `form.php` template-t használja az RTCL plugin, csak edit módban tölti ki az értékekkel.

**UNKNOWN**: Az edit folyamat URL struktúrája és jogosultság ellenőrzése (valószínűleg az account dashboard "My Listings" endpointról indul).

---

## 19. Dashboard

### Account Navigation

```
Dashboard sidebar nav: Functions::get_account_menu_items() → endpoint => label array
- "add-listing" endpoint → Link::get_listing_form_page_link() (listing form oldal)
- Minden más endpoint → Link::get_account_endpoint_url($endpoint)
```

### Hook-ok

| Hook | Funkció |
|------|---------|
| `rtcl_account_navigation` | Sidebar nav megjelenítés |
| `rtcl_account_content` | Dashboard tartalom |
| `rtcl_before_account_navigation` | Nav előtt |
| `rtcl_after_account_navigation_list` | `<ul>` után |
| `rtcl_after_account_navigation` | Nav után |

**UNKNOWN**: Pontosan milyen endpointok vannak (my-listings, orders, membership, etc.) — az RTCL plugin határozza meg.

**ADATBÁZIS EXPORT SZÜKSÉGES**: Account endpoint struktúra nincs PHP fájlban, a plugin wp_options-ban tárolja az oldalakat.

---

## 20. Search és Filter

### Search/Filter komponensek

| Komponens | Implementáció |
|----------|--------------|
| **Keyword** | `<input name="q">` + RTCL autocomplete AJAX |
| **Kategória** | `<select name="rtcl_category">` via `wp_dropdown_categories()`, Select2 |
| **Lokáció** | `<select name="rtcl_location">` via `wp_dropdown_categories()`, Select2 (csak "local" módban) |
| **Geo keresés** | `geo_address` + `center_lat/lng` + `distance` range slider |
| **Ár szűrő** | Range slider (ion.rangeSlider) VAGY text inputok (konfig függő) |
| **Custom field szűrők** | Dinamikusan generált: `filters[_field_N]` (text/select/checkbox/radio/number/url) |
| **Shortcode szűrők** | `[listing_filters filter_group="auto"]` és `filter_group="cars-for-sale"` |

### RTCL plugin által kezelt search

- `Functions::get_filter_form_url()` — form action URL
- `Functions::get_cf_ids(['is_searchable'=>true])` — kereshető egyedi mezők
- `RtclOptions::radius_search_options()` — geo beállítások (units, max_distance, default_distance)
- AJAX autocomplete: `rtcl-autocomplete` CSS class a plugin JS-e kezeli
- Geo geocoding: `rtcl-geo-address-input` a plugin JS-e kezeli

### WP_Query / keresési logika

**ADATBÁZIS EXPORT SZÜKSÉGES**: A tényleges `WP_Query` paraméterek és `meta_query`/`tax_query` logika az RTCL plugin kódjában van (plugin nem elérhető lokálisan).

**LIKELY** a plugin `pre_get_posts` hook-ot használ a GET paraméterek WP_Query meta_query-vé konvertálásához.

---

## 21. Media Handling

### Listing képek (galéria)

- **RTCL plugin kezeli** — plugin saját attachment rendszer
- Image méretek: `rtcl-gallery` (fő slide), `rtcl-gallery-thumbnail` (nav), `full` (lightbox)
- Swiper.js slider

### Listing logo

- **Theme kezeli** (`Listing_Functions::listing_form_save()`)
- Input: `name="listing_logo_img"` (file input) + `name="logo_attachment_id"` (hidden, meglévő)
- Handler: `wp_handle_upload()` → `wp_insert_attachment()` → `update_post_meta('listing_logo_img')`
- AJAX handler: `wp_ajax_delete_listing_logo_attachment`

### Éttermi étel képek

- **Theme kezeli**
- Input: `name="cldirectory_food_images[N][food_list][M]"` (file input)
- AJAX handler: `wp_ajax_delete_food_attachment`
- ⚠️ BIZTONSÁGI PROBLÉMA: Egyik AJAX handler sem ellenőriz nonce-t!

### User avatar/profilkép

- Meta key: `_rtcl_pp_id` (WP attachment ID)
- Az RTCL plugin saját profilkép feltöltési logikája

---

## 22. Moderation

**UNKNOWN**: A moderációs logika az RTCL plugin (és Pro addon) kódjában van.

**LIKELY** mechanizmusok (kód utalások alapján):
- Listing post status: 'publish' / 'pending' / 'trash'
- Az RTCL Pro claim listing (`rtclClaimListing`) plugin lehetővé teszi listing igénylést moderációval
- Admin approval folyamat valószínűleg az RTCL plugin wp-admin menüjén keresztül

**ADATBÁZIS EXPORT SZÜKSÉGES**: `wp_posts` minta rekordok az `rtcl_listing` post_status értékeivel.

---

## 23. AJAX és REST

### Theme AJAX handlerek (CONFIRMED)

| Action | Handler | Auth | Nonce |
|--------|---------|------|-------|
| `wp_ajax_delete_listing_logo_attachment` | `Listing_Functions::delete_listing_logo_attachment()` | Logged-in only | ❌ HIÁNYZIK |
| `wp_ajax_delete_food_attachment` | `Listing_Functions::delete_food_attachment()` | Logged-in only | ❌ HIÁNYZIK |

### Plugin AJAX handlerek (LIKELY — plugin kód nélkül)

| Funkció | Valószínű AJAX action |
|---------|----------------------|
| Listing form submit | rtcl AJAX (plugin) |
| Keyword autocomplete | rtcl-autocomplete (plugin) |
| Category-based custom fields betöltés | `wp_ajax_rtcl_custom_fields_listings` |
| Contact email küldés | RTCL plugin (plugin JS kezeli `#rtcl-contact-form`) |
| Claim listing | RtclClaimListing plugin (`.rtcl-claim-listing-form` JS hook) |
| Report abuse | RTCL plugin (`#rtcl-report-abuse-form`) |
| Favourites | RTCL Pro plugin |
| Compare | RTCL Pro plugin (`$_SESSION['rtcl_compare_ids']`) |
| Geo geocoding | RTCL plugin (`.rtcl-geo-address-input`) |

### REST API

**CONFIRMED**: Nincsenek REST endpointok a theme-ben.
**UNKNOWN**: RTCL plugin regisztrál-e REST endpointokat.

---

## 24. Database Storage

### wp_posts

| Tartalom | `post_type` |
|---------|------------|
| Listings | `rtcl_listing` |
| Stores | `rtcl_store` |
| Single listing builder | `rtcl_builder` |
| Standard oldalak | `page` |
| Elementor template-ek | `elementor_library` |

### wp_postmeta (rtcl_listing)

**CONFIRMED meta key-ek**:
- `phone`, `email`, `address`, `website`
- `_rtcl_whatsapp_number`, `_rtcl_max_price`, `_rtcl_listing_pricing`
- `listing_logo_img` (legacy) / `listing_logo` (FormBuilder mode)
- `cldirectory_food_list` (serialized)
- `_rtcl_video_urls` (serialized array)
- `hide_map`, `rt_post_views_count`
- `_rtcl_manager_id`
- `_elementor_data` (Elementor tartalom)

### wp_usermeta

**CONFIRMED meta key-ek**:
- `_rtcl_phone`, `_rtcl_whatsapp_number`, `_rtcl_website`
- `_rtcl_pp_id`, `_rtcl_address`
- `rtcl_favourites`

### wp_terms / wp_term_taxonomy

- `rtcl_category` taxonómia
- `rtcl_location` taxonómia

### wp_termmeta

- `_rtcl_image` — kategória kép attachment ID
- `_rtcl_icon` — kategória ikon CSS class

### RTCL Plugin Custom Tables (ADATBÁZIS EXPORT SZÜKSÉGES)

**A plugin valószínűleg custom táblákat használ** az alábbi adatokhoz:

| Adattípus | ADATBÁZIS EXPORT SZÜKSÉGES |
|----------|--------------------------|
| Custom field definíciók | RTCL CF táblák |
| Custom field group (CFG) definíciók | RTCL CFG táblák |
| Fizetési csomagok | RTCL packages táblák |
| Tranzakciók | RTCL payments táblák |
| Store membership | RtclStore táblák |
| Business hours konfigurációja | Valószínűleg wp_postmeta |
| RTCL oldalak (listing form page, account page) | wp_options |

### wp_options

**CONFIRMED option group-ok a kódban**:
- `rtcl_general_settings` — RTCL általános beállítások
- `rtcl_single_listing_settings` — Single listing oldal beállítások
- `rtcl_archive_listing_settings` — Archive oldal beállítások
- `cldirectory_*` — Theme customizer opciók

---

## 25. External Dependencies

| Függőség | Típus | Célra |
|---------|-------|-------|
| Google Maps API | JS API | Térkép megjelenítés, geo keresés |
| Google Fonts | CDN | Tipográfia |
| Google reCAPTCHA | API | Spam védelem (report abuse, contact form) |
| WhatsApp API | URL scheme | `https://api.whatsapp.com/send?phone=...` |
| Swiper.js | JS library | Galéria slider |
| ion.rangeSlider | JS library | Ár szűrő slider |
| Bootstrap 5.x | CSS/JS framework | Layout, modal |
| Select2 | JS library | Fancy dropdown |
| isotope.pkgd | JS library | Listing grid szűrés |
| Magnific Popup | JS library | Lightbox |
| Font Awesome 6 | Icon font | Ikonok |
| Custom CL Icons | Icon font | Egyedi ikonok |

---

## 26. Biztosan újraépítendő funkciók

Az alábbiak 100%-osan azonosíthatók kódból és újraépíthetők:

1. **Single listing oldal layout** — Bootstrap grid, accordion szekciók (leírás, egyedi mezők, galéria, térkép, videó, menü, review)
2. **Listing card (grid/list)** — hook-alapú, 4 komponens: thumbnail, meta, excerpt, footer
3. **Listing logo feltöltés** — `wp_handle_upload()` + `wp_insert_attachment()` (témában implementált)
4. **Éttermi food menu** — custom meta struktúra, accordion megjelenítés
5. **Contact email sablon** — name/email/phone/message mezők, standard email HTML
6. **Claim listing modal form** — name/email/phone/message/PDF fields
7. **Business hours megjelenítő** — nap-alapú accordion, nyitvatartási státusz
8. **Social profilok** — platform slug → URL → ikon
9. **Szerzői profil oldal** — avatar, bio, kontakt mezők, listing lista
10. **Search widget** — keyword + category + location + geo + price + custom fields
11. **Sidebar regisztrált widgetek** — 9 sidebar slot
12. **Breadcrumb** — `do_action('cldirectory_breadcrumb')`
13. **Buy button (marketplace)** — POST form, add-to-cart + quantity
14. **Report abuse modal** — textarea + recaptcha + AJAX submit
15. **Compare funkció** — session-alapú, `$_SESSION['rtcl_compare_ids']`
16. **Favourites gomb** — `rtcl_favourites` user meta

---

## 27. Bizonytalan pontok

| Kérdés | Státusz | Megjegyzés |
|--------|---------|-----------|
| Pontosan hány account típus/role van? | UNKNOWN | Adatbázis kell |
| wppb regisztrációs form mezők? | UNKNOWN | Adatbázis kell |
| Listing moderációs státuszok? | UNKNOWN | Plugin kód kellene |
| RTCL custom field DB struktúra? | UNKNOWN | Plugin kód + DB kell |
| Payment gatewayek listája? | UNKNOWN | Plugin kód kell |
| Membership csomag struktúra? | UNKNOWN | DB kell |
| Store membership működése? | UNKNOWN | Plugin kód kell |
| Claim listing email folyamat? | UNKNOWN | Plugin kód kell |
| Marketplace checkout flow? | UNKNOWN | Plugin kód kell |
| `[listing_filters filter_group="..."]` shortcode implementációja? | UNKNOWN | Classified Listing Toolkits kód kell |
| rtcl_builder és rtcl_category kapcsolat? | UNKNOWN | DB kell |
| Is the `listing_logo` vs `listing_logo_img` meta key a Form Builder módtól függ? | LIKELY | Kód utalás: `Functions::isEnableFb()` |
| RTCL AJAX action nevek? | UNKNOWN | Plugin kód kell |
| Geo search "radius" vs "local" mód aktuális beállítása? | UNKNOWN | DB kell |

---

## 28. Hiányzó adatbázis adatok

Az alábbi exportok szükségesek a teljes audit folytatásához:

### Kritikus (újraépítéshez kötelező)

1. **RTCL custom field táblák** — minden egyedi mező definíció és érték
   ```sql
   -- Valószínű táblanevek (pontos neveket DB-ből kell lekérdezni):
   SHOW TABLES LIKE '%rtcl%';
   ```

2. **Kategória struktúra**:
   ```sql
   SELECT t.term_id, t.name, t.slug, tt.parent, tt.taxonomy, tt.count
   FROM wp_terms t
   JOIN wp_term_taxonomy tt ON t.term_id = tt.term_id
   WHERE tt.taxonomy IN ('rtcl_category', 'rtcl_location')
   ORDER BY tt.taxonomy, tt.parent, t.name;
   ```

3. **Kategória term meta** (ikonok, képek):
   ```sql
   SELECT * FROM wp_termmeta
   WHERE meta_key IN ('_rtcl_image', '_rtcl_icon');
   ```

4. **WordPress user roles**:
   ```sql
   SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles';
   ```

5. **RTCL oldalbeállítások** (melyik page az account page, listing form page stb.):
   ```sql
   SELECT * FROM wp_options WHERE option_name LIKE 'rtcl_%';
   ```

6. **User meta minta** (account típusok megértéséhez):
   ```sql
   SELECT user_id, meta_key, meta_value
   FROM wp_usermeta
   WHERE meta_key LIKE '%rtcl%' OR meta_key LIKE '%wppb%'
   LIMIT 50;
   ```

### Fontos (funkcionalitás megértéséhez)

7. **Listing minta rekordok** (meta keys listája):
   ```sql
   SELECT DISTINCT meta_key FROM wp_postmeta pm
   JOIN wp_posts p ON pm.post_id = p.ID
   WHERE p.post_type = 'rtcl_listing'
   ORDER BY meta_key;
   ```

8. **Membership/packages**:
   ```sql
   SHOW TABLES LIKE '%rtcl%';
   -- Majd: SELECT * FROM wp_rtcl_pricing (vagy hasonló)
   ```

9. **Payment gateway beállítások**:
   ```sql
   SELECT * FROM wp_options WHERE option_name LIKE 'rtcl_payment%';
   ```

10. **ProfileBuilder form konfigurációk**:
    ```sql
    SELECT * FROM wp_options WHERE option_name LIKE 'wppb%';
    SELECT * FROM wp_posts WHERE post_type LIKE 'wppb%';
    ```

---

## 29. Következő audit lépések

### 1. Azonnali tennivalók (adatbázis nélkül is lehetséges)

- [ ] Plugin-bundle ZIP-ek kicsomagolása és klasszikus-listing-pro kód auditja (custom fields DB struktúra)
- [ ] wp-config.php olvasása (DB prefix, charset, debug mód)
- [ ] `classified-listing/elementor/` könyvtár teljes auditja (Elementor widget PHP implementációk)
- [ ] `inc/customizer/settings/listings.php` és `listing-single-layout.php` auditja (témaopciók teljes listája)

### 2. Adatbázis export után

- [ ] RTCL custom field táblák teljes struktúra feltérképezése
- [ ] Minden listing kategória és sub-kategória slug azonosítása
- [ ] Account típusok és WordPress role-ok azonosítása
- [ ] wppb regisztrációs mezők feltérképezése
- [ ] Membership csomag struktúra dokumentálása

### 3. Plugin kód elérése után (production szerver)

- [ ] `classified-listing/includes/` — RTCL AJAX handler-ek
- [ ] `classified-listing-pro/` — Pro funkciók (compare, online status, membership)
- [ ] `classified-listing-store/` — Store post type, membership
- [ ] `cldirectory-core/` — CLDirectory Core Elementor widgetek PHP
- [ ] `classified-listing-toolkits/` — `[listing_filters]` shortcode implementáció
- [ ] WP ProfileBuilder plugin — regisztrációs logika

### 4. Újraépítési döntések (audit után)

- [ ] Melyik RTCL adatmodell marad (custom tables vs. wp_postmeta)
- [ ] Megmarad-e az RTCL plugin az új témával, vagy teljesen custom?
- [ ] wppb helyett custom regisztrációs rendszer?
- [ ] Elementor Pro helyett natív PHP template-ek?

---

## 30. DB Export Kiegészítések (2026-08-29)

Az audit után 8+1 adatbázis export érkezett, amelyek az alábbi UNKNOWN tételeket lezárták:

### Lezárt kérdések (09-rtcl-forms.json alapján)

| Korábban UNKNOWN | Mostani státusz |
|-----------------|----------------|
| Form Builder mezők | CURRENT CONFIRMED: 10 form, 257 mező, wp_rtcl_forms tábla |
| listing_logo vs listing_logo_img | CONFIRMED: FB módban `listing_logo` (array), legacy: `listing_logo_img` |

### RTCL Form Builder — Teljes struktúra (CURRENT CONFIRMED)

| Form ID | Cím | Kategória | Mezők |
|---------|-----|-----------|-------|
| 2 | Post a Job | Jobs | 22 (DEFAULT) |
| 3 | Service | Services | 26 |
| 4 | Public Information | Public Information | 20 |
| 5 | Auto/Moto/Boats | Auto/Moto/Boats | 49 |
| 6 | Submit an Event | Events | 23 |
| 7 | Property for Rent or Sale | Properties | 33 |
| 8 | Restaurants/Nightlife | Restaurants & Nightlife | 24 |
| 9 | Marketplace | Marketplace | 19 |
| 10 | Leisure/Sports | Leisure & Sport | 21 |
| 11 | Tourist Attraction | Tourist Attractions | 20 |

Minden root kategóriának (10/10) van saját Form Builder formja.  
Részletes mezőlista: → LISTING-FIELDS.md Szekció 5.

### Egyéb lezárt kérdések (01-08 json alapján)

| Korábban UNKNOWN | Mostani státusz |
|-----------------|----------------|
| Kategória struktúra | CONFIRMED: 152 kategória, 10 root |
| Helyszínek | CONFIRMED: 35 flat Costa Blanca town |
| wppb regisztrációs mezők | CONFIRMED: 9 mező, NIE=custom_field_1, NIF=custom_field_2 |
| Membership csomagok | CONFIRMED: 0 csomag, Membership DISABLED |
| WordPress role-ok | CONFIRMED: customer/seller/business/rtcl_manager |
| RTCL beállítások | CONFIRMED: 100+ beállítás dokumentálva |

---

*Audit vége. Implementáció NEM kezdődött.*

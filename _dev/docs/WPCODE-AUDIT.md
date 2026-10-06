# WPCode snippet-audit (3. fázis eleje, 2026-10-06)

Forrás: a lokális (anonimizált) DB `wpcode` post type-ja, 14 snippet. A kódot csak olvastuk; titkos kulcs nincs bennük.
Döntés (2026-10-06): **minden snippet abban a fázisban kerül át a témába, ahová tartozik; a feleslegesek kimaradnak; a végén a WPCode plugin is megy.** Állapot az 5. fázis után: **mind a 14 kész** (átvéve vagy elvetve), lokálisan mind kikapcsolva.

## Összefoglaló

| ID | Állapot | Cím | Mit csinál valójában | Ítélet | Fázis |
|---|---|---|---|---|---|
| **6510** | publish | Filter Builder Active (138 KB, „LFB 3.3.0”) | saját szűrő-építő: admin oldal (jQuery UI), `[listing_filters]` shortcode, AJAX függő opciók, `pre_get_posts` meta/tax query, „online” követés, kézi „verified” jelölő a user profilon | ✅ **kiváltja az `Archive` modul**; a beállított szűrő-csoportok **importálva** (lásd lent) | 3 |
| 5643 | publish | Torrehub Listing Report System (TLRS) | bejelentés gomb (login kell), AJAX + nonce, `tlrs_report_count` / `tlrs_reported_users` meta, admin lista + beállítások, küszöb felett piszkozatba tesz; elrejti az RTCL saját report modalját | ✅ **átírva** `Listing\Report`-ba a meglévő meta-kulcsokkal (+ ok mentése, admin e-mail, küszöbnél pending); lokálisan kikapcsolva | 4 |
| 6476 | publish | Keep the form open in the single list | JS: a single oldalon automatikusan kinyitja az RTCL e-mail űrlapot | ✅ **felesleges** — a téma kontakt modulja nyitott e-mail űrlapot ad; lokálisan kikapcsolva | 4 |
| 5120 | publish | Add gallery image as featured image | `save_post`-on az első galéria-képet thumbnailnek állítja | **hibás, sosem futott**: `get_post_type() !== 'listing'` (a post type `rtcl_listing`). 17/19 listingnek így is van `_thumbnail_id` (RTCL állítja). A téma kártyája az első galéria-képet használja → **elvetve** | — |
| 7263 | publish | Add NIF/NIE field to the registration form | RTCL regisztrációs űrlapra `nif_nie` mező + validálás + fiók/admin profil | ✅ **kiváltja az `Auth` modul** (5. fázis; a 13. döntés szerint NIE nincs, csak a business NIF). ⚠ Más meta-kulcsot használ (`nif_nie`, 0 db érték) mint a régi wppb (`custom_field_1` NIE: 4, `custom_field_2` NIF: 4) → a téma a `custom_field_1/2`-t tartja meg | 5 |
| 7280 | publish | Restrict WordPress Dashboard Access | admin bar elrejtése + wp-admin tiltása admin/editor kivételével (AJAX engedve) | ✅ **átírva** `Auth`-ba (képesség alapon: `edit_others_posts`; az AJAX / admin-post / upload nyitva) | 5 |
| 7264 | publish | My account edit page design (CSS) | RTCL fiók-űrlap margók | ✅ **felesleges** (saját account sablonok) | 5 |
| 6030 | publish | Favorites issue fix (JS) | RTCL kedvenc-link `href` javítás jQuery-vel | ✅ **felesleges** (kedvencek ki; a téma saját gombja `<button>`); lokálisan kikapcsolva | 3 |
| 6472 | publish | Listing style correction (CSS) | régi RTCL kártya/slider képarány | ✅ **felesleges** (saját kártya, fix képarány); lokálisan kikapcsolva | 3 |
| 5121 | draft | Hide Button for Customers and Redirect Users Correctly | szerepkör szerinti menü-rejtés CSS-sel, login/logout redirectek, wp-admin tiltás | nem fut; az ötletek az `Auth`/`Account` tervben vannak | — |
| 5123 | draft | Hide Listing Forms For Seller Role | (vázlat) | nem fut, elvetve | — |
| 5642 | draft | Add disclaimer to every listing | JS-sel beszúrt sárga jogi nyilatkozat a single oldal aljára | ✅ nem fut; a „Staying safe” blokk Customizer-szövege váltja ki | 4 |
| 5684 | draft | Fix login stability | `DONOTCACHE*` belépve + 7 napos auth cookie | nem fut; a cache-kizárás hosting-feladat, runbookba | 8 |
| 6040 | draft | Test Field Value collect | admin debug: összes post meta kiírása a single oldalon | nem fut, **elvetve** (adatszivárgás veszélyes, ha élesítik) | — |

A snippetek közül a jQuery-függők (6030, 7263) kivezetése után a jQuery a footerbe mozdítható (`inc/assets.php` DECISION) — 5. fázis után.

## 6510 „Filter Builder Active” — részletek

**Felépítés:** 2751 sor egy snippetben: admin builder (≈ 1100 sor inline jQuery/HTML), front-end renderer + ≈ 550 sor inline JS (AJAX rácscsere DOMParserrel, a válaszban érkező `<script>`-ek újrafuttatása), lekérdezés-építő, online-követő, user-profil mező. Opciók: `lfb_filter_groups` (14 csoport), `lfb_settings` (színek, `grid_selector=.rtcl-listings-wrapper`), `lfb_cache_version`.

**Hibák / kockázatok a jelenlegi kódban**
1. A front-end harmadik féltől tölt (`tom-select` a jsDelivr CDN-ről) — consent és teljesítmény szempontból is kerülendő.
2. A „Date” / „Pickup date” naptár-szűrő `=` összehasonlítást csinál egy `Y-m-d H:i:s` értékű metára → **soha nem talál**.
3. Több konfigurált `uuid` nem létező meta-kulcs: `pricing` (Property, Rental „Price per day”), `address`, `text_mojy38cg_copy`, üres uuid-ok (Events „Date”, Jobs „Posted Date”, Property „Agency or Private”, Services „Verified”) → ezek a szűrők nem szűrnek vagy hibásan szűrnek.
4. A „Verified” szűrő a `rtcl_verified` user metát nézi (0 db), a kártyák/Pro a `rtcl_verified_seller`-t (0 db) — két forrás; a téma egyet használ (`Verification` modul, 5. fázis).
5. „Online” követés: minden belépett kérésnél user meta írás (`_lfb_last_seen`, 9 user) — a szűrőben nincs bekapcsolva; **elvetve** (a design nem kéri; BUILD-PLAN „Online státusz: nem épül”).
6. Kézi egyezés: a kézzel beírt opciók („Hospital”, „Full_time”…) nem az űrlap értékei, csak a MySQL kis/nagybetű-érzéketlensége miatt működnek. A téma az **űrlap mezőinek opcióit** (value + label) használja → mindig egyezik.
7. Adathiba az űrlapokban (nem a snippeté): a `€` / `€€` / `€€€` opciók **értéke üres** (Restaurants „Price Range”, Leisure „Price”) — ezekre nem lehet szűrni; javítás a Form Builderben (értékadás pl. `eur1`, `eur2`) → **kliens-teendő**, a téma az üres értékű opciókat kihagyja.

**Mit viszünk át (import, egyszer):** a 14 csoport → téma-opció `th_archive_filters` (kategória-slug → mező-lista sorrendben, címkével), a 3. fázis `Archive` modulja. Szabályok:
- csak a kategória űrlapjában **létező** mezők (select, radio, checkbox, number, date, text) maradnak; a nem létező kulcsok kimaradnak (3. pont), naplózva az import-összesítőben;
- `price` / `pricing` → a közös ár-szűrő (RTCL `filters[price]`); `rtcl_location` / `address` / helyszín-szövegmezők → a közös „Where” (város + sugár); `user_verified` → a közös „Verified sellers only” kapcsoló; `rtcl_category` → a közös kategória-választó;
- a csoport a **kategóriára és minden leszármazottjára** érvényes, a legközelebbi ős nyer (az LFB „auto” viselkedése);
- az Auto/Moto/Boats gyökér „fül” (heading) szerkezete nem jön át: a gyökéren a közös szűrők + alkategória-chipek, az alkategóriákon (cars, motorcycles, boats, vehicles-for-rent) a saját csoportjuk;
- az admin felület egyszerű lista (kategória → mezők be/ki, sorrend) a Téma beállításokban; az LFB opciók érintetlenek maradnak (visszaállíthatóság), a snippet a plugin kivezetésekor megy.

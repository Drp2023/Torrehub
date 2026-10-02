# PAGE-SPECIFICATIONS.md
## Torrehub — Oldal tartalom specifikációk

**Verzió**: 2.0 (DB export alapján frissítve)
**Dátum**: 2026-08-29
**Állapot**: Kategóriák CONFIRMED (10 főkategória, 152 összesen); Store/Membership DISABLED jelölve

---

## PAGE-01: Főoldal (Home)

**URL**: `/`  
**Sablon**: `front-page.php` (custom)  
**Hozzáférés**: Mindenki

### Tartalom szekciók (sorrendben)

#### 1. Hero szekció
- **Háttér**: Nagy hirdetés/helyszín fotó VAGY videó háttér (kliens dönti el)
- **Szöveg**: Főcím + alcím (szerkeszthető a WP adminban)
- **Keresőform** (beágyazva):
  - Kulcsszó mező
  - Kategória legördülő
  - Helyszín legördülő
  - "Keresés" gomb
- **Quick stats** (opcionális): "X hirdetés | Y kategória | Z helyszín"

#### 2. Kategória rács
- **Forrás**: `rtcl_category` taxonomy — CONFIRMED: 10 főkategória (Auto/Moto/Boats, Events, Jobs, Leisure & Sport, Marketplace, Properties, Public Information, Restaurants & Nightlife, Services, Tourist Attractions)
- **Layout**: 4-6-8 kártya grid (kategóriánként: ikon + név + hirdetések száma)
- **Interakció**: Kattintásra → Kategória archive oldal

#### 3. Kiemelt / Legújabb hirdetések
- **Forrás**: Kiemelt (`featured = 1`) VAGY legújabb `rtcl_listing` post-ok
- **Layout**: 3-4 oszlopos kártya grid
- **Kártya tartalom**: Galéria thumbnail, cím, ár, kategória, helyszín, "Kiemelt" badge
- **CTA**: "Összes hirdetés megtekintése →"

#### 4. Hogyan működik szekció (statikus)
- 3-4 lépéses folyamat (Regisztrálj → Hirdetj → Vegyél kapcsolatot → Köss üzletet)
- Szerkeszthető a WP adminban (opcionális: ACF vagy egyedi metaboxok)

#### 5. Kategória Slider (opcionális)
- Horizontálisan görgethető kategória kártyák
- Toolkits: `[rtcl-listing-category-slider]` widgettől inspirált

#### 6. Legutóbbi Stores — ⛔ DISABLED (Store modul kikapcsolva)
- Store szekció csak client döntés után kerül a főoldalra → CLIENT-CONFIRMATION.md B1

#### 7. Számok (Trust indicators)
- Hirdetések száma (live count)
- Regisztrált felhasználók
- Kategóriák száma
- Ellenőrzött eladók száma

#### 8. Regisztrációs CTA (nem bejelentkezett)
- "Hirdetj ingyenesen!" — Regisztrálj CTA
- Private Seller / Business Seller típus kiemelve

---

## PAGE-02: Hirdetések lista (Archive)

**URL**: `/listings/` + szűrő URL paraméterek  
**Sablon**: `archive-rtcl_listing.php` + `taxonomy-rtcl_category.php` + `taxonomy-rtcl_location.php`  
**Hozzáférés**: Mindenki

### Oldal fejléc
- Cím: "Hirdetések" VAGY "[Kategória neve]" VAGY "[Helyszín neve]"
- Breadcrumb: Főoldal > Hirdetések > [Kategória]
- Hirdetések száma: "X hirdetés találva"

### Szűrő panel (Sidebar — desktop bal oldal)

#### Keresés blokk
- Kulcsszó mező + Keresés gomb

#### Kategória szűrő
- Hierarchikus checkbox lista
- Szülő kategória → Al-kategóriák

#### Helyszín szűrő
- Hierarchikus checkbox/select
- Ország → Tartomány → Város

#### Ár tartomány
- Min. ár slider + mező
- Max. ár slider + mező
- Pénznem szimbólum

#### Hirdetés típusa
- Checkbox: Eladó / Kiadó / Keresett

#### Custom mezők szűrők
- Kategória-specifikus szűrők: CONFIRMED — 10 form, 257 mező (→ LISTING-FIELDS.md 5. szekció)
- Toolkits Search Form widget-ből

#### Reset gomb
- "Szűrők törlése"

### Hirdetés lista terület

#### Eszköztár
- Bal: "X hirdetés"
- Közép: Rendezés (Legújabb / Ár ↑ / Ár ↓ / Releváns)
- Jobb: Lista / Rács nézet ikonok + Térkép toggle

#### Hirdetés kártyák (Grid nézet)
Minden kártyán:
- Thumbnail kép (aspect ratio: 4:3 ajánlott)
- "Kiemelt" badge (ha featured)
- "Sürgős" badge (ha urgent)
- Ár
- Cím
- Kategória ikon + név
- Helyszín ikon + név
- Feladás dátuma
- ❤️ Kedvencekhez gomb (AJAX)
- "Összehasonlítás" ikon (AJAX)

#### Hirdetés kártyák (Lista nézet)
- Bal: Kisebb thumbnail
- Jobb: Cím, ár, kategória, helyszín, dátum, leírás snippet

#### Térkép nézet
- Google Maps / OpenStreetMap
- Marker-ek a hirdetés pozíciójában
- Marker kattintásra: mini kártya popup

#### Üres állapot
- "Nem találtunk hirdetéseket"
- [Szűrők törlése] gomb

#### Pagination
- Lapozás (numbered) VAGY "Több betöltése" (AJAX load more)

---

## PAGE-03: Hirdetés részletek (Single Listing)

**URL**: `/listing/{slug}/`  
**Sablon**: `single-rtcl_listing.php` (per-kategória variánsok)  
**Hozzáférés**: Mindenki

### Fejléc terület (listing-heading-2 alapján)

#### Banner / Hero galéria
- Nagy kép (banner nézet) VAGY slideshow
- Thumbnails
- Lightbox megnyitás kattintásra

#### Navigációs sáv (sticky scroll esetén)
- [Leírás] [Amenities] [Galéria] [Térkép] [Videó] [Menü] [Értékelések]
- Jobb oldal: Ár megjelenítése (Stílus 2 esetén)
- Kattintásra smooth scroll

### Listing heading szekció (listing-heading alapján)

#### Bal oldal
- Listing logó (ha van: `listing_logo_img` attachment)
- Cím (H1)
- Nyitvatartás státusz badge
- Badges: Featured, Urgent, stb.
- Rating csillagok + review szám link
- Meta: Kategória ikon, Telefon (rejtett gomb), Helyszín, Dátum, Megtekintések

#### Jobb oldal
- Ár (`price` meta) — Stílus 1 esetén
- Max. ár ha range
- Ár típus label (Fixed/Range/Negotiable)
- [Vásárlás gomb] — ha RtclMarketplace aktív
- Gombok sáv:
  - ❤️ Kedvencekhez
  - ⚖️ Összehasonlítás
  - 🔗 Megosztás (Social share dropdown)
  - 🖨️ Nyomtatás
  - 🐛 Visszaélés bejelentése (csak bejelentkezett)
  - ✅ Claim listing (ha rtcl-claim-listing aktív)

### Tartalom terület (accordion)

#### Leírás szekció
- Post content (HTML/rich text)
- Csak ha van tartalom

#### Custom mezők (Amenities)
- Csoportokba rendezve (rtcl_cfg alapján)
- Lista VAGY rács layout
- Ikon + Label + Érték

#### Étterem menü (csak restaurant kategóriánál)
- `cldirectory_food_list` meta
- Szekciók: Starter, Main, Dessert, stb.
- Étel: Név + Leírás + Ár + Fotó

#### Galéria szekció (ha ≥ 2 kép)
- Masonry VAGY grid layout
- Lightbox megnyitás

#### Videó szekció
- YouTube/Vimeo embed (16:9 ratio)
- Csak ha `_rtcl_video_urls` van

#### Térkép szekció
- Google Maps embed
- Pontosan a `latitude`/`longitude` meta alapján
- Csak ha `has_map()` igaz és `hide_map` nem igaz

#### Értékelések szekció
- Review lista (review-schema plugin)
- Review form (bejelentkezett, nem saját hirdetés esetén)

### Sidebar

#### Eladói/Store info blokk (típusonként):
- **Owner Info** (Private Seller): Avatar, Név, Cím, Tel, WhatsApp, Web, Közösségi média, "Ellenőrzött" badge, Online státusz
- **Store Info** (Business Seller): Store logó, Cégnév, Kategória, "Ellenőrzött" badge, Tel, Web, Store linkje
- **Content Info**: Egyéb tartalom (fallback)

#### Nyitvatartás blokk
- Napok + Nyitás/Zárás idők
- "Nyitva" / "Zárva" státusz (jelenlegi időpont alapján)

#### Email kapcsolatfelvételi form
- Csak ha `has_contact_form` beállítás aktív és email van

#### Widget sidebar
- `single-listing-sidebar` widget terület (WordPress widget)

---

## PAGE-04: Store lista (Store Directory)

**URL**: `/stores/`  
**Sablon**: `archive-rtcl_store.php` (egyedi)  
**Hozzáférés**: Mindenki

### Tartalom
- Store kártyák:
  - Store logó (200×150)
  - Cégnév
  - Store kategória
  - Hirdetések száma
  - "Ellenőrzött" badge (ha verified)
- Szűrő: Store kategória szerint
- Rendezés: Legújabb / Név szerint

---

## PAGE-05: Store részletek (Single Store)

**URL**: `/store/{slug}/`  
**Sablon**: `single-rtcl_store.php`  
**Hozzáférés**: Mindenki

### Tartalom
- **Banner** (992×300 px)
- **Logó** (200×150 px) + Cégnév
- **Leírás** (store post_content)
- **"Ellenőrzött Üzlet" badge** (ha verified)
- **Elérhetőségek**: Tel, Web, Cím, Social media ikonok
- **Store hirdetések listája**: `[rtcl_store_page id="X"]` — hirdetés kártyák a store hirdetéseivel
- **Értékelések** (ha store-ra is lehessen értékelni — KLIENS kérdés)

---

## PAGE-06: Bejelentkezés

**URL**: `/bejelentkezes/`  
**Sablon**: `page-login.php`  
**Hozzáférés**: Nem bejelentkezett; átirányítás ha bejelentkezett

### Tartalom
- **Bal oldal** (desktop): Marketing szöveg / kép / benefits lista
- **Jobb oldal** (login form):
  - Oldal cím: "Üdvözöljük!"
  - Email mező
  - Jelszó mező + szemgolyó toggle (jelszó megmutatása)
  - "Maradj bejelentkezve" checkbox
  - "Bejelentkezés" gomb (primary)
  - Elválasztó: "— VAGY —"
  - Szociális bejelentkezés gombok (ha van — KLIENS: kell? Google/Facebook)
  - Linkek:
    - "Elfelejtett jelszó?"
    - "Nincs még fiókod? Regisztrálj ingyen!"

---

## PAGE-07: Regisztráció

**URL**: `/regisztracio/`  
**Sablon**: `page-register.php`  
**Hozzáférés**: Nem bejelentkezett

### Tartalom

#### Fiók típus választó (3 kártya)
```
┌──────────────┐  ┌──────────────────┐  ┌──────────────────┐
│  👤 MEMBER   │  │  🏷️ PRIVATE       │  │  🏢 BUSINESS     │
│              │  │  SELLER           │  │  SELLER          │
│ Böngész,     │  │ Magánszemély      │  │ Cég / Vállalkozás│
│ üzenetelj,   │  │ hirdetők          │  │ hirdetők + Store │
│ értékelj     │  │ NIE szükséges     │  │ NIF szükséges    │
│              │  │                   │  │                  │
│ [Kiválaszt]  │  │ [Kiválaszt]       │  │ [Kiválaszt]      │
└──────────────┘  └──────────────────┘  └──────────────────┘
```

#### Dinamikus form (típusonként változik — lásd FORM-SPECIFICATIONS.md)

#### Feltételek
- "Elfogadom a Felhasználási feltételeket és Adatvédelmi irányelveket"
- Link mindkét dokumentumra

#### "Regisztrálás" gomb

#### Link
- "Már van fiókod? Lépj be →"

---

## PAGE-08: Jelszó visszaállítás

**URL**: `/elfelejtett-jelszo/`  
**Sablon**: `page-reset-password.php`

### Tartalom
- Oldal cím: "Elfelejtett jelszó"
- Leírás: "Add meg email címed és küldünk egy visszaállítási linket"
- Email mező
- "Link küldése" gomb
- "Vissza a bejelentkezéshez" link

---

## PAGE-09: Árszabás / Membership

**URL**: `/arak/`  
**Sablon**: `page-pricing.php`

### Tartalom
- Cím: "Válassz csomagot"
- Számlázási időszak toggle: Havi / Éves (ha van éves opció)
- Membership kártyák:
  - Csomag neve
  - Ár / időszak
  - Hirdetések száma
  - Képek / hirdetés
  - Promóciók (Featured/Urgent elérhető?)
  - Lejárat
  - [Vásárlás] gomb
  - Legjobb érték kiemelés (pl. "Legnépszerűbb" badge)
- Funkció összehasonlítás táblázat
- GYIK szekció (opcionális)
- **Forrás**: `[rtcl_membership_pricing_table]` shortcode VAGY egyedi lekérdezés (`rtcl_pricing` post type)

---

## PAGE-10: My Account / Dashboard főoldal

**URL**: `/fiokom/`  
**Sablon**: `page-myaccount.php` → `[rtcl_my_account]` shortcode  
**Hozzáférés**: Bejelentkezett

*Lásd DASHBOARD-SPECIFICATIONS.md — részletes tartalom típusonként*

---

## PAGE-11: 404 oldal

**URL**: Bármely nem létező URL  
**Sablon**: `404.php`

### Tartalom
- Nagy "404" szám (vizuálisan kiemelve)
- Üzenet: "Az oldal nem található"
- Leírás: "A keresett oldal nem létezik vagy áthelyezték."
- Keresőmező (hirdetések keresésére)
- [Vissza a főoldalra] gomb
- [Legújabb hirdetések megtekintése] link

---

## PAGE-12: Kapcsolat

**URL**: `/kapcsolat/` (KLIENS: pontos URL)  
**Sablon**: `page.php` + custom template VAGY Contact plugin

### Tartalom
- Cím, cím, térkép
- Kapcsolatfelvételi form (FluentForms vagy Contact Form 7)
- Email, telefon, nyitvatartás

---

## Globális elemek (minden oldalon)

### Header (fejléc)
- Logo (bal)
- Fő navigáció (középen)
- Kereső ikon / sáv
- Bejelentkezés / Dashboard gomb (jobb)
- "Hirdetés feladása" CTA gomb (hangsúlyos)
- Mobil: Hamburger menü

### Footer (lábléc)
- Logo + rövid leírás
- Navigációs linkek (Rólunk, Kapcsolat, GYIK, Adatvédelem, Feltételek)
- Kategória linkek
- Közösségi média ikonok
- Copyright szöveg
- Fizetési logók (ha van fizetés)

### Notification bar (értesítési sáv)
- Siker / Hiba / Figyelmeztetés Toast üzenetek (jobb felső)
- Belső értesítők (membership lejárat, hitelesítési státusz)

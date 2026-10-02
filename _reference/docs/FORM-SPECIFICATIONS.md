# FORM-SPECIFICATIONS.md
## Torrehub — Form (Űrlap) specifikációk

**Verzió**: 2.0 (DB export alapján frissítve — ProfileBuilder CONFIRMED)
**Dátum**: 2026-08-29
**Állapot**: ProfileBuilder megerősítve; Store DISABLED; NIE=custom_field_1, NIF=custom_field_2

---

## Jelölések

- **R** = Kötelező (Required)
- **O** = Opcionális (Optional)
- **RIF** = Regisztrációtól függ (Registration type dependent)

---

## FORM-01: Bejelentkezési form

**Helyszín**: `/bejelentkezes/` (login oldal)  
**AJAX handler**: `rtcl_login_request`  
**Elérhető**: Nem bejelentkezett felhasználók

| Mező neve | Típus | Kötelező | Validáció | Placeholder |
|-----------|-------|----------|-----------|-------------|
| Email cím | email | R | Valid email formátum | "Email cím" |
| Jelszó | password | R | Min. 1 karakter | "Jelszó" |
| Maradj bejelentkezve | checkbox | O | — | "Emlékezz rám" |

**Gombok**: "Bejelentkezés"  
**Linkek**: "Elfelejtett jelszó", "Regisztrálj ingyen"  
**Sikeres**: Átirányítás `/fiokom/` (dashboard)  
**Hibás**: Inline hibaüzenet ("Hibás email vagy jelszó")

---

## FORM-02: Regisztrációs form — MEMBER típus

**Helyszín**: `/regisztracio/` (wppb-register shortcode, page ID: 27210)  
**System**: ProfileBuilder (wppb) v3.16.3 — CURRENT CONFIRMED  
**Elérhető**: Nem bejelentkezett felhasználók

| Mező neve | Típus | Kötelező | Validáció | wppb mező ID | Megjegyzés |
|-----------|-------|----------|-----------|--------------|------------|
| Fiók típus | Select (User Role) | R | — | id:14 | customer = MEMBER |
| Keresztnév | text | R | — | id:17, meta: first_name | — |
| Vezetéknév | text | R | — | id:18, meta: last_name | — |
| Felhasználónév | text | R | Egyedi, nem foglalt | id:2/3 | wppb username mező |
| Email cím | email | R | Valid email; nem foglalt | id:8 | — |
| Jelszó | password | R | wppb szabályok szerint | id:12 | — |
| Jelszó megerősítése | password | R | Egyezés | id:13 | — |

**Gombok**: "Regisztráció"  
**Linkek**: "Már van fiókod? Lépj be"  
**Sikeres**: Email megerősítő küldése (AKTÍV — emailConfirmation: "yes") → Átirányítás: `https://torrehub.com/login/`  
**Admin jóváhagyás**: KÖTELEZŐ — a fiók csak admin jóváhagyás után aktív  
**Hibás**: Inline mezőszintű hibaüzenetek

---

## FORM-03: Regisztrációs form — PRIVATE SELLER típus

**Helyszín**: `/regisztracio/` (wppb-register shortcode, page ID: 27210)  
**System**: ProfileBuilder (wppb) v3.16.3 — CURRENT CONFIRMED  
**NIE mező**: wppb conditional field — csak akkor jelenik meg, ha Account Type = "seller"

| Mező neve | Típus | Kötelező | Validáció | wppb mező ID | Megjegyzés |
|-----------|-------|----------|-----------|--------------|------------|
| Fiók típus | Select (User Role) | R | — | id:14 | seller = PRIVATE SELLER |
| Keresztnév | text | R | — | id:17, meta: first_name | — |
| Vezetéknév | text | R | — | id:18, meta: last_name | — |
| Felhasználónév | text | R | Egyedi, nem foglalt | id:2/3 | wppb username mező |
| NIE szám | text | R | Spanyol NIE: [X\|Y\|Z]+7 szám+betű | id:15, meta: **custom_field_1** | Conditional: csak seller |
| Email cím | email | R | Valid email; nem foglalt | id:8 | — |
| Jelszó | password | R | wppb szabályok szerint | id:12 | — |
| Jelszó megerősítése | password | R | Egyezés | id:13 | — |

**NIE validáció**: `[X|Y|Z]\d{7}[A-Z]` regex (spanyol NIE formátum)  
**Tárolás**: NIE → `custom_field_1` (wppb user meta — CURRENT CONFIRMED, már implementálva)  
**Sikeres**: Email megerősítő küldése → Átirányítás: `https://torrehub.com/login/`  
**Admin jóváhagyás**: KÖTELEZŐ — a fiók csak admin jóváhagyás után aktív

---

## FORM-04: Regisztrációs form — BUSINESS SELLER típus

**Helyszín**: `/regisztracio/` (wppb-register shortcode, page ID: 27210)  
**System**: ProfileBuilder (wppb) v3.16.3 — CURRENT CONFIRMED  
**NIF mező**: wppb conditional field — csak akkor jelenik meg, ha Account Type = "business"

| Mező neve | Típus | Kötelező | Validáció | wppb mező ID | Megjegyzés |
|-----------|-------|----------|-----------|--------------|------------|
| Fiók típus | Select (User Role) | R | — | id:14 | business = BUSINESS SELLER |
| Keresztnév | text | R | — | id:17, meta: first_name | — |
| Vezetéknév | text | R | — | id:18, meta: last_name | — |
| Felhasználónév | text | R | Egyedi, nem foglalt | id:2/3 | wppb username mező |
| NIF szám | text | R | Spanyol NIF: 8 szám+betű | id:16, meta: **custom_field_2** | Conditional: csak business |
| Email cím | email | R | Valid email; nem foglalt | id:8 | — |
| Jelszó | password | R | wppb szabályok szerint | id:12 | — |
| Jelszó megerősítése | password | R | Egyezés | id:13 | — |

**NIF validáció**: `\d{8}[A-Z]` regex (spanyol NIF formátum)  
**Tárolás**: NIF → `custom_field_2` (wppb user meta — CURRENT CONFIRMED, már implementálva)  
**Sikeres**: Email megerősítő küldése → Átirányítás: `https://torrehub.com/login/`  
**Admin jóváhagyás**: KÖTELEZŐ — a fiók csak admin jóváhagyás után aktív  
**Megjegyzés**: A wppb form jelenleg nem tartalmaz külön Cégnév/Kapcsolattartó mezőt — ezek az RTCL profil-settings-en keresztül tölthetők ki

---

## FORM-05: Jelszó visszaállítás

**Helyszín**: `/elfelejtett-jelszo/`  
**Handler**: Standard WP password reset

### 5a. Lépés 1 — Email megadás
| Mező neve | Típus | Kötelező | Validáció |
|-----------|-------|----------|-----------|
| Email cím | email | R | Létező fiókhoz tartozó email |

**Gomb**: "Visszaállítási link küldése"  
**Sikeres**: "Email elküldve — ellenőrizd a postaládádat"

### 5b. Lépés 2 — Jelszó visszaállítás (emailben kapott link)
| Mező neve | Típus | Kötelező | Validáció |
|-----------|-------|----------|-----------|
| Új jelszó | password | R | Min. 8 karakter |
| Jelszó megerősítése | password | R | Egyezés |

**Gomb**: "Jelszó mentése"

---

## FORM-06: Profil szerkesztés — Közös (minden típus)

**Helyszín**: `/fiokom/edit-account/`  
**Handler**: RTCL `edit-account` endpoint

| Mező neve | Típus | Kötelező | Kinek jelenik meg | Megjegyzés |
|-----------|-------|----------|-------------------|------------|
| Profilkép | image upload | O | Mindenki | WP avatar |
| Keresztnév | text | R | Mindenki | WP first_name |
| Vezetéknév | text | R | Mindenki | WP last_name |
| Email cím | email | R | Mindenki | — |
| Jelenlegi jelszó | password | R* | Mindenki | *csak ha jelszót változtat |
| Új jelszó | password | O | Mindenki | — |
| Jelszó megerősítése | password | O | Mindenki | — |

---

## FORM-07: Profil szerkesztés — Eladói adatok (Seller)

**Helyszín**: `/fiokom/profile-settings/` VAGY `/fiokom/edit-account/` (kiegészítve)  
**Handler**: RTCL `profile-settings` endpoint  
**Kinek**: Private Seller + Business Seller

| Mező neve | Típus | Kötelező | WP meta kulcs | Megjegyzés |
|-----------|-------|----------|---------------|------------|
| Telefonszám | tel | R | `_rtcl_phone` | Hirdetésen megjelenő szám |
| WhatsApp szám | tel | O | `_rtcl_whatsapp_number` | — |
| Telegram | text | O | `_rtcl_telegram` | — |
| Website | url | O | `_rtcl_website` | — |
| Cím | textarea | O | `_rtcl_address` | — |
| Facebook | url | O | `_rtcl_social[facebook]` | — |
| Instagram | url | O | `_rtcl_social[instagram]` | — |
| Twitter / X | url | O | `_rtcl_social[twitter]` | — |
| LinkedIn | url | O | `_rtcl_social[linkedin]` | KLIENS: Szükséges? |

**Business Seller extra mezők**:
| Mező neve | Típus | Kötelező | Meta kulcs | Megjegyzés |
|-----------|-------|----------|------------|------------|
| Cégnév | text | R | WP display_name | — |
| Kapcsolattartó neve | text | R | Egyedi meta kulcs (fejlesztendő) | — |
| NIF szám | text | R* | `custom_field_2` (CURRENT CONFIRMED) | *Csak megjelenítés, nem szerkeszthető regisztráció után? |

---

## FORM-08: Hirdetés feladása — Multi-step form

**Helyszín**: `/fiokom/add-listing/`  
**Handler**: `rtcl_post_new_listing` AJAX  
**Kinek**: Private Seller + Business Seller

### Lépés 1: Kategória választó
| Elem | Leírás |
|------|--------|
| Kategória grid | Vizuális kártyák ikonokkal; kattintásra al-kategóriák |
| Keresés | Szöveges keresés kategóriák közt |
| DATABASE EXPORT REQUIRED | Tényleges kategóriák listája |

### Lépés 2: Alap adatok

| Mező neve | Típus | Kötelező | Meta kulcs | Validáció |
|-----------|-------|----------|------------|-----------|
| Hirdetés típusa | radio | R | `ad_type` | Sell / Rent / Wanted (KLIENS: szükséges mind?) |
| Cím (Title) | text | R | WP post_title | Min. 5 karakter |
| Leírás | wysiwyg/textarea | R | WP post_content | Min. 20 karakter |
| Ár | number | O | `price` | Szám > 0 |
| Max. ár | number | O | `_rtcl_max_price` | Szám > ár (range esetén) |
| Ár típus | select | O | `price_type` | Fixed / Range / Negotiable / On call |
| Ár egység | select | O | `_rtcl_price_unit` | DATABASE EXPORT REQUIRED |
| Telefonszám | tel | O | `phone` | — |
| WhatsApp | tel | O | `_rtcl_whatsapp_number` | — |
| Email (hirdetésen) | email | O | `email` | — |
| Website | url | O | `website` | — |
| Telegram | text | O | `_rtcl_telegram` | — |

### Lépés 3: Custom mezők (kategória-specifikus) — CURRENT CONFIRMED

**Forrás**: 09-rtcl-forms.json (`wp_rtcl_forms` tábla) + 03-rtcl-custom-fields.json  
**Rendszer**: RTCL Form Builder (aktív) + rtcl_cfg/rtcl_cf standard mezők  
**Teljes adatbázis**: 10 form, 257 mező — minden root kategóriának saját form

#### Kategória → Form Builder megfeleltetés

| Kategória | Form | Összes mező | Kategória-specifikus input típusok |
|-----------|------|-------------|-----------------------------------|
| Jobs | Form 2 "Post a Job" | 22 | select, radio, number, url, file |
| Services | Form 3 "Service" | 26 | text, select, radio, file, repeater |
| Public Information | Form 4 "Public Info" | 20 | select, checkbox |
| Auto/Moto/Boats | Form 5 "Auto/Moto/Boats" | 49 | select, radio, text, number, date + **feltételes szekciók** |
| Events | Form 6 "Submit an Event" | 23 | select, radio, date/datetime |
| Properties | Form 7 "Property" | 33 | select, radio, number, file, repeater |
| Restaurants/Nightlife | Form 8 "Restaurants" | 24 | select, radio, checkbox |
| Marketplace | Form 9 "Marketplace" | 19 | select, text |
| Leisure & Sport | Form 10 "Leisure/Sports" | 21 | select, radio, checkbox |
| Tourist Attractions | Form 11 "Tourist Attraction" | 20 | select, radio, checkbox |

#### Minden kategóriában közös PRESET mezők
title, description, images/gallery, pricing, phone, whatsapp, email, website, map, location, listing_type, category, terms_and_condition

#### Designer számára szükséges input komponensek (teljes lista)
text · number · select · radio · checkbox · date/datetime · file · url/website · pricing (RTCL) · repeater · business_hours · map · social_profiles

**Teljes mezőlista**: → LISTING-FIELDS.md 5. szekció (5.1–5.11)

### Lépés 4: Galéria

| Elem | Leírás |
|------|--------|
| Képek feltöltése | Drag & drop; multi-select |
| Sorrend változtatás | Drag & drop rendezés |
| Max. képek száma | Membership által korlátozott (`_rtcl_image_count`) |
| Elfogadott formátumok | JPG, PNG, WEBP |
| Max. fájlméret | KLIENS / WordPress beállítás |

### Lépés 5: Helyszín

| Mező neve | Típus | Kötelező | Meta kulcs | Megjegyzés |
|-----------|-------|----------|------------|------------|
| Cím szöveges | text | O | `address` | — |
| Irányítószám | text | O | `zipcode` | — |
| Helyszín (taxonomy) | hierarchical select | O | `rtcl_location` taxonomy | Ország → Tartomány → Város |
| Térkép kijelölés | map picker | O | `latitude` + `longitude` | Google Maps API |
| Geo cím (auto) | hidden | — | `_rtcl_geo_address` | Geocoding alapján töltődik |
| Térkép elrejtése | checkbox | O | `hide_map` | — |

### Lépés 6: Videó

| Mező neve | Típus | Kötelező | Meta kulcs | Megjegyzés |
|-----------|-------|----------|------------|------------|
| Videó URL | url | O | `_rtcl_video_urls[]` | YouTube/Vimeo embed URL |

### Lépés 7: Csomag választó

**⚠️ Membership DISABLED** (`rtcl_membership_settings.enable: ""`) — Jelenleg nincs fizetős csomag.

| Elem | Leírás | Státusz |
|------|--------|--------|
| Ingyenes opció | 5 ingyenes hirdetés / 30 nap (number_of_free_ads: 5) | CURRENT CONFIRMED |
| Fizetős csomagok | Membership-hez kötött — jelenleg nincs konfigurálva | ⛔ DISABLED |
| Kiemelési lehetőségek | Featured / Urgent add-on | PLUGIN CAPABILITY |

### Lépés 8: Előnézet + Beküldés

| Elem | Leírás |
|------|--------|
| Hirdetés előnézete | Full single listing nézet |
| "Vissza szerkeszteni" gomb | — |
| "Közzététel" gomb | → `rtcl_post_new_listing` AJAX → post status: pending/temp |
| Sikeres üzenet | "Hirdetés beküldve — admin jóváhagyásra vár" |

---

## FORM-09: Hirdetés szerkesztése

**Helyszín**: Listingsből → Szerkesztés gomb  
**Tartalom**: Azonos az Add Listing formmal, előtöltött adatokkal  
**Különbség**: Kategória nem változtatható (KLIENS: változtatható legyen?)

---

## FORM-10: Eladóval kapcsolatfelvétel (Email form)

**Helyszín**: Hirdetés részletek oldal → Sidebar  
**Handler**: `rtcl_public_send_contact_email` AJAX  
**Elérhető**: Bejelentkezett felhasználók (KLIENS: csak bejelentkezve?)

| Mező neve | Típus | Kötelező | Megjegyzés |
|-----------|-------|----------|------------|
| Név | text | R | Auto-kitöltve ha bejelentkezett |
| Email | email | R | Auto-kitöltve ha bejelentkezett |
| Telefonszám | tel | O | — |
| Üzenet | textarea | R | Min. 10 karakter |
| reCAPTCHA | — | Beállítástól függően | Google reCAPTCHA |

**Gomb**: "Üzenet küldése"  
**Sikeres**: "Üzenet elküldve"  
**Hibás**: Inline hibaüzenet

---

## FORM-11: Visszaélés bejelentése (Report Abuse)

**Helyszín**: Hirdetés részletek oldal → "Report" gomb → Modal  
**Handler**: `rtcl_public_report_abuse` AJAX  
**Elérhető**: Bejelentkezett felhasználók

| Mező neve | Típus | Kötelező | Megjegyzés |
|-----------|-------|----------|------------|
| Panasz szövege | textarea | R | — |
| reCAPTCHA | — | Beállítástól | — |

**Gomb**: "Küldés"  
**Modal cím**: "Visszaélés bejelentése"

---

## FORM-12: Értékelés írása (Review)

**Helyszín**: Hirdetés részletek oldal → Review szekció  
**Plugin**: review-schema / review-schema-pro  
**Elérhető**: Bejelentkezett felhasználók (nem saját hirdetésre)

| Mező neve | Típus | Kötelező | Megjegyzés |
|-----------|-------|----------|------------|
| Értékelés (csillagok) | 1-5 star rating | R | — |
| Review cím | text | O | — |
| Review szöveg | textarea | R | Min. 10 karakter |
| Ajánlod? | radio (Igen/Nem) | O | DATABASE EXPORT REQUIRED: tényleges mezők |

**Megjegyzés**: A review-schema-pro pontos mezői DATABASE EXPORT REQUIRED

---

## FORM-13: Keresési szűrő form

**Helyszín**: Listings archive oldal (sidebar + top bar)  
**Widget**: Toolkits "rtcl-listing-search-form" Elementor widget  
**Elérhető**: Mindenki

| Mező neve | Típus | Kötelező | Megjegyzés |
|-----------|-------|----------|------------|
| Kulcsszó | text | O | Post title + description keresés |
| Kategória | hierarchical select | O | `rtcl_category` taxonomy |
| Helyszín | hierarchical select | O | `rtcl_location` taxonomy |
| Hirdetés típusa | select | O | Sell / Rent / Wanted |
| Min. ár | number | O | — |
| Max. ár | number | O | — |
| Saját custom szűrők | — | O | DATABASE EXPORT REQUIRED |

**Gomb**: "Keresés"  
**Reset**: "Szűrők törlése"  
**Keresési értesítő mentése**: Gomb a szűrő alatt (rtcl-search-alert plugin)

---

## FORM-14: Dokumentum feltöltés (Eladói hitelesítés)

**Helyszín**: `/fiokom/my-documents/`  
**Plugin**: rtcl-seller-verification  
**Kinek**: Private Seller + Business Seller

| Mező neve | Típus | Kötelező | Meta kulcs | Elfogadott formátumok |
|-----------|-------|----------|------------|----------------------|
| Fotó azonosító (NIE/NIF fotó) | file upload | R | `photo_id` (WP attachment ID) | PNG, JPG, JPEG |
| Üzleti dokumentum / Egyéb | file upload | O | `other_document_id` (WP attachment ID) | Minden fájlformátum (PDF stb.) |

**Gomb**: "Dokumentumok feltöltése"  
**Státusz megjelenítés**: 
- Nincs feltöltve → Piros ikon + "Töltsd fel dokumentumaidat a hitelesítéshez"
- Feltöltve → Sárga ikon + "Dokumentumaid ellenőrzés alatt"
- Hitelesítve → Zöld ikon + "Ellenőrzött Eladó" badge

---

## FORM-15: Store profil szerkesztése (Business Seller) — ⛔ DISABLED

**Státusz**: Store modul KIKAPCSOLVA (`rtcl_membership_settings.enable_store: ""`)  
**Plugin**: RtclStore — telepítve de deaktivált  
**Teendő**: Client döntés szükséges az új rendszerhez (lásd CLIENT-CONFIRMATION.md B1)

*Ez a form csak akkor releváns, ha a kliens aktiválni akarja a Store modult.*

---

## Megjegyzések

1. **NIE/NIF validáció**: Spanyol formátumok implementálása szükséges custom PHP validációval
2. **Multi-step form**: Az Add Listing form lépéseinek pontos sorrendje klienstől megerősítendő
3. **reCAPTCHA**: Formok védelme — aktív-e a jelenlegi rendszerben? (DATABASE EXPORT REQUIRED)
4. **Telefonszám OTP**: Ha rtcl-verification aktív, a telefonszám mező OTP ellenőrzéssel egészül ki
5. **Custom mezők**: A tényleges custom mező lista kategóriánként DATABASE EXPORT REQUIRED — lásd LISTING-FIELDS.md

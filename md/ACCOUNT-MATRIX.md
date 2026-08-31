# ACCOUNT-MATRIX.md
## Torrehub — Felhasználói szerepkörök és funkciók mátrix

**Verzió**: 2.0 (DB export alapján frissítve)  
**Dátum**: 2026-08-29  
**Állapot**: CURRENT CONFIRMED adatokkal frissítve  
**Platform**: WordPress klasszifikált hirdetési platform — Costa Blanca régió, Spanyolország

---

## Státusz jelölések magyarázata

| Jelölés | Jelentés |
|---|---|
| CURRENT CONFIRMED | A jelenlegi rendszerben DB export alapján megerősített, aktív funkció |
| CLIENT CONFIRMED TARGET | Kliens által megerősített célállapot (tervezési fázis) |
| PLUGIN CAPABILITY | A plugin képes rá, de nincs konfigurálva / tesztelve |
| UNKNOWN | Adatbázis exportból nem derül ki, további vizsgálat szükséges |
| ⛔ DISABLED | Kikapcsolt funkció — DB export alapján megerősített |

---

## Fiók típusok definíciója (CURRENT CONFIRMED)

| Típus | WP szerepkör | wppb regisztrációs mezők | Célja |
|---|---|---|---|
| MEMBER | `customer` | Account Type, First Name, Last Name, Username, Email, Password, Confirm Password | Böngészés, hirdetések megtekintése, nem eladó |
| PRIVATE SELLER | `seller` | Account Type, First Name, Last Name, Username, NIE (conditional: csak seller), Email, Password, Confirm Password | Magánszemély hirdetéseladó, spanyol NIE igazolással |
| BUSINESS SELLER | `business` | Account Type, First Name, Last Name, Username, NIF (conditional: csak business), Email, Password, Confirm Password | Céges hirdetéseladó, spanyol NIF igazolással |

**ProfileBuilder (wppb) CONFIRMED ACTIVE**: v3.16.3, lifetime license érvényes.  
A wppb kezeli a teljes regisztrációs folyamatot.  
NIE = `custom_field_1` (meglévő conditional mező, csak `seller` szerepkörnél jelenik meg)  
NIF = `custom_field_2` (meglévő conditional mező, csak `business` szerepkörnél jelenik meg)  
Admin jóváhagyás: **KÖTELEZŐ** minden szerepkörnél (customer, seller, business, editor, author).  
Email visszaigazolás: **AKTÍV** (emailConfirmation: "yes").  
Regisztráció utáni redirect: `https://torrehub.com/login/`

---

## Funkciók mátrix

### REGISZTRÁCIÓ ÉS HITELESÍTÉS

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Regisztráció (wppb ProfileBuilder) | Igen | Igen | Igen | CURRENT CONFIRMED | wppb v3.16.3 aktív, kezeli az összes regisztrációs formot |
| Account Type választó (Select/User Role) | customer | seller | business | CURRENT CONFIRMED | wppb mező: "Account Type", értékek: customer / seller / business |
| Email visszaigazolás regisztrációkor | Igen | Igen | Igen | CURRENT CONFIRMED | emailConfirmation: "yes" |
| Admin jóváhagyás szükséges aktiváláshoz | Igen | Igen | Igen | CURRENT CONFIRMED | Admin approval required: customer, seller, business, editor, author |
| NIE mező a regisztrációs formon | Nem | Igen (conditional) | Nem | CURRENT CONFIRMED | custom_field_1 — wppb conditional mező, seller szerepkörnél jelenik meg |
| NIF mező a regisztrációs formon | Nem | Nem | Igen (conditional) | CURRENT CONFIRMED | custom_field_2 — wppb conditional mező, business szerepkörnél jelenik meg |
| Bejelentkezés email + jelszóval | Igen | Igen | Igen | CURRENT CONFIRMED | WordPress standard login |
| Jelszó visszaállítás | Igen | Igen | Igen | CURRENT CONFIRMED | WordPress standard funkció |
| Regisztráció utáni redirect | login oldalra | login oldalra | login oldalra | CURRENT CONFIRMED | Redirect: https://torrehub.com/login/ |

---

### PROFIL ADATOK

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Első név (First Name) | Igen | Igen | Igen | CURRENT CONFIRMED | wppb standard mező |
| Vezetéknév (Last Name) | Igen | Igen | Igen | CURRENT CONFIRMED | wppb standard mező |
| Felhasználónév (Username) | Igen | Igen | Igen | CURRENT CONFIRMED | wppb standard mező |
| Email cím | Igen | Igen | Igen | CURRENT CONFIRMED | wppb standard mező |
| NIE szám | Nem | Igen | Nem | CURRENT CONFIRMED | wppb: custom_field_1 — meglévő mező, nem új implementáció |
| NIF szám | Nem | Nem | Igen | CURRENT CONFIRMED | wppb: custom_field_2 — meglévő mező, nem új implementáció |
| Profilkép (avatar) | Igen | Igen | Igen | PLUGIN CAPABILITY | wppb képes rá, tényleges konfiguráció ellenőrzendő |
| Telefonszám | Nem | Igen | Igen | PLUGIN CAPABILITY | `_rtcl_phone` user meta — konfiguráció ellenőrzendő |
| WhatsApp szám | Nem | Igen | Igen | PLUGIN CAPABILITY | `_rtcl_whatsapp_number` user meta — konfiguráció ellenőrzendő |
| Website URL | Nem | Igen | Igen | PLUGIN CAPABILITY | `_rtcl_website` user meta — konfiguráció ellenőrzendő |
| Cím (Address) | Nem | Igen | Igen | PLUGIN CAPABILITY | `_rtcl_address` user meta — konfiguráció ellenőrzendő |
| Közösségi média linkek | Nem | Igen | Igen | PLUGIN CAPABILITY | `_rtcl_social` user meta — konfiguráció ellenőrzendő |
| Cég neve (Business Name) | Nem | Nem | Igen | UNKNOWN | DB exportból nem derül ki külön mezőként |
| Kapcsolattartó személy neve | Nem | Nem | Igen | UNKNOWN | DB exportból nem derül ki |
| Jelszó szerkesztése | Igen | Igen | Igen | CURRENT CONFIRMED | WordPress standard funkció |

---

### BÖNGÉSZÉS ÉS KERESÉS

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Hirdetések böngészése (bejelentkezés nélkül is) | Igen | Igen | Igen | CURRENT CONFIRMED | Nyilvános CPT rtcl_listing |
| Hirdetés részleteinek megtekintése | Igen | Igen | Igen | CURRENT CONFIRMED | Single listing template |
| Keresés kulcsszóra | Igen | Igen | Igen | CURRENT CONFIRMED | Alap keresési funkció aktív |
| Szűrés kategória szerint | Igen | Igen | Igen | CURRENT CONFIRMED | rtcl_category taxonomy aktív |
| Szűrés helyszín szerint | Igen | Igen | Igen | CURRENT CONFIRMED | Google Maps API konfigurált és aktív |
| Szűrés ár szerint | Igen | Igen | Igen | CURRENT CONFIRMED | Rtcl ár szűrő aktív |
| Lista / Rács nézet váltás | Igen | Igen | Igen | PLUGIN CAPABILITY | Toolkits widget — konfiguráció ellenőrzendő |
| Térkép nézet | Igen | Igen | Igen | CURRENT CONFIRMED | Google Maps API aktív |
| Rendezés (dátum / ár / relevancia) | Igen | Igen | Igen | PLUGIN CAPABILITY | Toolkits orderby — konfiguráció ellenőrzendő |
| Ad Type szűrő (Sell/Rent/Wanted) | Rejtett | Rejtett | Rejtett | ⛔ DISABLED | hide_form_fields: [ad_type] — a mező el van rejtve |
| Hirdetések összehasonlítása | N/A | N/A | N/A | ⛔ DISABLED | rtcl_general_settings.enable_compare: "" — Compare modul kikapcsolva |

---

### TELEFONSZÁM ÉS ELÉRHETŐSÉG

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Telefonszám megjelenítése (hirdetésen) | UNKNOWN | Igen | Igen | PLUGIN CAPABILITY | rtcl_phone_click AJAX — konfiguráció ellenőrzendő |
| Telefonszám elrejtése (kattintásra mutat) | UNKNOWN | Igen | Igen | PLUGIN CAPABILITY | Konfiguráció ellenőrzendő |
| WhatsApp gomb (hirdetésen) | UNKNOWN | Igen | Igen | PLUGIN CAPABILITY | rtcl_whatsapp_click AJAX — konfiguráció ellenőrzendő |

---

### KAPCSOLATFELVÉTEL (CHAT)

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Üzenetküldés eladónak (Chat) | Igen | Igen | Igen | CURRENT CONFIRMED | Pusher Chat aktív: app_id: 2148163, cluster: eu |
| Valós idejű chat értesítések | Igen | Igen | Igen | CURRENT CONFIRMED | Pusher WebSocket kapcsolat konfigurálva és aktív |
| Chat előzmények megtekintése | Igen | Igen | Igen | CURRENT CONFIRMED | Chat modul aktív |
| Üzenet fogadása vevőtől | Nem releváns | Igen | Igen | CURRENT CONFIRMED | Pusher Chat aktív |

---

### KEDVENCEK

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Hirdetés mentése kedvencekbe | Igen | Igen | Igen | PLUGIN CAPABILITY | rtcl_public_add_remove_favorites AJAX — bejelentkezés szükséges |
| Kedvencek lista megtekintése | Igen | Igen | Igen | PLUGIN CAPABILITY | favourites account endpoint — konfiguráció ellenőrzendő |

---

### ÉRTÉKELÉSEK (REVIEWS)

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Eladó / hirdetés értékelése | Igen | Igen | Igen | PLUGIN CAPABILITY | review-schema plugin — konfiguráció ellenőrzendő |
| Saját hirdetésre review tiltva | Nem releváns | Tiltva | Tiltva | PLUGIN CAPABILITY | Standard WP restriction — konfiguráció ellenőrzendő |
| Értékelés megválaszolása | Nem | Igen | Igen | PLUGIN CAPABILITY | Konfiguráció ellenőrzendő |

---

### KERESÉSI ÉRTESÍTŐK (SEARCH ALERT)

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Keresési értesítő létrehozása | Igen | Igen | Igen | CURRENT CONFIRMED | Search Alert v1.2.0 aktív |
| Email értesítés új találatról | Igen | Igen | Igen | CURRENT CONFIRMED | SearchAlertScheduler aktív |
| Értesítő frekvencia (Daily/Weekly/Monthly) | Igen | Igen | Igen | CURRENT CONFIRMED | Search Alert modul aktív |
| Értesítők kezelése (törlés, módosítás) | Igen | Igen | Igen | CURRENT CONFIRMED | search-alert account endpoint aktív |
| Leiratkozás értesítőből | Igen | Igen | Igen | CURRENT CONFIRMED | Leiratkozási link az emailben |

---

### HIRDETÉS FELADÁSA

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Hirdetés feladása | Nem | Igen | Igen | CURRENT CONFIRMED | rtcl_post_new_listing AJAX aktív |
| Ingyenes hirdetések száma | N/A | 5 db / 30 nap | 5 db / 30 nap | CURRENT CONFIRMED | number_of_free_ads: 5 |
| Hirdetés jóváhagyási folyamat | N/A | Admin jóváhagyás kötelező | Admin jóváhagyás kötelező | CURRENT CONFIRMED | Új és szerkesztett hirdetés státusza: "pending" — admin jóváhagyás szükséges |
| Hirdetés lejárata | N/A | 15 nap | 15 nap | CURRENT CONFIRMED | Listing duration: 15 nap default |
| Automatikus törlés lejárat után | N/A | 15 nappal lejárat után | 15 nappal lejárat után | CURRENT CONFIRMED | Törlés 15 nappal a lejárat után |
| Képek száma hirdetésenként | N/A | Max 5 db | Max 5 db | CURRENT CONFIRMED | max images: 5 |
| Engedélyezett képformátumok | N/A | PNG / JPG / JPEG / WEBP | PNG / JPG / JPEG / WEBP | CURRENT CONFIRMED | Formátumok konfigurálva |
| Max képméret | N/A | 2 MB / kép | 2 MB / kép | CURRENT CONFIRMED | Max 2MB per image |
| Ad Type mező (Sell/Rent/Wanted) | N/A | Rejtett | Rejtett | ⛔ DISABLED | hide_form_fields: [ad_type] — a mező el van rejtve a formon |
| Helyszín megadása (Google Maps) | N/A | Igen | Igen | CURRENT CONFIRMED | Google Maps API aktív és konfigurált |
| AI által generált leírás (GPT-4o) | N/A | Igen | Igen | CURRENT CONFIRMED | OpenAI GPT-4o konfigurálva |
| Pénznem | N/A | EUR (€) | EUR (€) | CURRENT CONFIRMED | Currency: EUR, pozíció: bal oldal |
| Fizetés (prémium hirdetés) | N/A | Banki átutalás | Banki átutalás | CURRENT CONFIRMED | Bank transfer only — placeholder státuszban |
| Videó URL megadása | N/A | Igen | Igen | PLUGIN CAPABILITY | _rtcl_video_urls meta — konfiguráció ellenőrzendő |
| Restaurant listing típus | N/A | N/A | N/A | ⛔ DISABLED | enable_restaurant_listing: "" — kikapcsolva |

---

### HIRDETÉS KEZELÉSE

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Saját hirdetések listája | Nem | Igen | Igen | CURRENT CONFIRMED | Dashboard — "My Listings" endpoint |
| Hirdetés szerkesztése | Nem | Igen | Igen | CURRENT CONFIRMED | Szerkesztés után státusz: "pending" — admin jóváhagyás szükséges |
| Hirdetés törlése | Nem | Igen | Igen | CURRENT CONFIRMED | rtcl_delete_listing AJAX aktív |
| Hirdetés megújítása | Nem | Igen | Igen | PLUGIN CAPABILITY | rtcl_ajax_renew_listing AJAX — konfiguráció ellenőrzendő |
| Hirdetés státuszok | N/A | Pending / Active / Expired / Temp / Reviewed | Pending / Active / Expired / Temp / Reviewed | CURRENT CONFIRMED | rtcl-pending, rtcl-reviewed, rtcl-expired, rtcl-temp státuszok |
| Megtekintések száma | N/A | Igen | Igen | PLUGIN CAPABILITY | get_view_counts() — konfiguráció ellenőrzendő |

---

### HIRDETÉS PROMÓCIÓ

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Kiemelt (Featured) hirdetés | Nem | Igen | Igen | PLUGIN CAPABILITY | featured meta — fizetési integráció placeholder |
| Sürgős (Urgent) jelölés | Nem | Igen | Igen | PLUGIN CAPABILITY | Membership promotions rendszer — konfiguráció ellenőrzendő |
| Top hirdetés pozíció | Nem | Igen | Igen | PLUGIN CAPABILITY | Konfiguráció ellenőrzendő |
| Promóció vásárlása | Nem | Igen | Igen | PLUGIN CAPABILITY | RTCL Pro payment — fizetési integráció placeholder |

---

### ELADÓI HITELESÍTÉS (VERIFICATION)

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| NIE szám megadása | Nem | KÖTELEZŐ (conditional mező) | Nem | CURRENT CONFIRMED | custom_field_1 — wppb conditional mező, meglévő implementáció |
| NIF szám megadása | Nem | Nem | KÖTELEZŐ (conditional mező) | CURRENT CONFIRMED | custom_field_2 — wppb conditional mező, meglévő implementáció |
| Admin általi fiók jóváhagyás | Igen | Igen | Igen | CURRENT CONFIRMED | Admin approval required minden szerepkörnél |
| NIE / NIF formátum validáció | N/A | UNKNOWN | UNKNOWN | UNKNOWN | wppb tárolja a mezőt, de az automatikus validáció szintje DB-ből nem derül ki |
| Dokumentum feltöltés (fotó) | Nem | Igen | Igen | PLUGIN CAPABILITY | rtcl-seller-verification plugin — konfiguráció ellenőrzendő |
| "Ellenőrzött eladó" badge | Nem | UNKNOWN | UNKNOWN | UNKNOWN | DB exportból nem derül ki, hogy aktív-e |

---

### MEMBERSHIP / ELŐFIZETÉS

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Membership csomag vásárlása | N/A | N/A | N/A | ⛔ DISABLED | rtcl_membership_settings.enable: "" — Membership modul kikapcsolva |
| Aktív membership megtekintése | N/A | N/A | N/A | ⛔ DISABLED | Membership modul kikapcsolva |
| Membership lejárati értesítő | N/A | N/A | N/A | ⛔ DISABLED | Membership modul kikapcsolva |
| Membership megújítása | N/A | N/A | N/A | ⛔ DISABLED | Membership modul kikapcsolva |
| Membership árszabás oldal | N/A | N/A | N/A | ⛔ DISABLED | Membership modul kikapcsolva |
| Csomag alapú hirdetési limit | N/A | N/A | N/A | ⛔ DISABLED | Membership modul kikapcsolva |

---

### ÜZLET (STORE)

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Store oldal (üzlet profil) | N/A | N/A | N/A | ⛔ DISABLED | rtcl_membership_settings.enable_store: "" — Store modul kikapcsolva |
| Store menüpont a dashboardon | N/A | N/A | N/A | ⛔ DISABLED | Store disabled — menüpont nem jelenik meg |
| Store logó feltöltése | N/A | N/A | N/A | ⛔ DISABLED | Store modul kikapcsolva |
| Store banner feltöltése | N/A | N/A | N/A | ⛔ DISABLED | Store modul kikapcsolva |
| Store leírás | N/A | N/A | N/A | ⛔ DISABLED | Store modul kikapcsolva |
| Store hirdetések listája | N/A | N/A | N/A | ⛔ DISABLED | Store modul kikapcsolva |

---

### ÖSSZEHASONLÍTÁS (COMPARE)

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Hirdetések összehasonlítása | N/A | N/A | N/A | ⛔ DISABLED | rtcl_general_settings.enable_compare: "" — Compare modul kikapcsolva |

---

### VISSZAÉLÉS BEJELENTÉSE (REPORT)

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Hirdetés bejelentése (Report) | Igen (bejelentkezve) | Igen | Igen | PLUGIN CAPABILITY | rtcl_public_report_abuse AJAX — konfiguráció ellenőrzendő |
| Felhasználó bejelentése | UNKNOWN | UNKNOWN | UNKNOWN | UNKNOWN | DB exportból nem derül ki |

---

### NYOMTATÁS

| Funkció | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | DB alapú megerősítés |
|---|---|---|---|---|---|
| Hirdetés nyomtatása | Igen | Igen | Igen | PLUGIN CAPABILITY | window.print() JS hívás — konfiguráció ellenőrzendő |

---

### DASHBOARD MENÜ

| Menüpont | Member | Private Seller | Business Seller | Jelenlegi rendszer státusz | Megjegyzés |
|---|---|---|---|---|---|
| Dashboard főoldal | Igen | Igen | Igen | CURRENT CONFIRMED | dashboard endpoint |
| Profilom (wppb) | Igen | Igen | Igen | CURRENT CONFIRMED | wppb profil oldal — edit-account endpoint |
| Hirdetéseim (My Listings) | Nem | Igen | Igen | CURRENT CONFIRMED | listings endpoint — seller / business csak |
| Kedvenceim | Igen | Igen | Igen | PLUGIN CAPABILITY | favourites endpoint — konfiguráció ellenőrzendő |
| Üzenetek (Chat) | Igen | Igen | Igen | CURRENT CONFIRMED | Pusher Chat aktív — chat endpoint |
| Keresési értesítők | Igen | Igen | Igen | CURRENT CONFIRMED | Search Alert v1.2.0 — search-alert endpoint |
| Fizetéseim | Nem | Igen | Igen | PLUGIN CAPABILITY | payments endpoint — fizetési rendszer placeholder |
| Eladói hitelesítés (Verify) | Nem | Igen | Igen | PLUGIN CAPABILITY | verify endpoint — konfiguráció ellenőrzendő |
| Fiók beállítások | Igen | Igen | Igen | CURRENT CONFIRMED | WordPress account settings |
| Üzletem (Store) | N/A | N/A | N/A | ⛔ DISABLED | Store disabled — menüpont nem jelenik meg |
| Membership | N/A | N/A | N/A | ⛔ DISABLED | Membership disabled — menüpont nem jelenik meg |

---

## Jelenlegi rendszer vs. Új rendszer — Főbb különbségek

| Terület | Jelenlegi (DB CONFIRMED) | Új rendszer célállapot | Változás típusa |
|---|---|---|---|
| Felhasználó típusok | 3 WP role: `customer` / `seller` / `business` (CURRENT CONFIRMED) | MEMBER / PRIVATE SELLER / BUSINESS SELLER (megjelenített nevek) | Átnevezés / rebrand — WP role marad ugyanaz |
| Regisztrációs rendszer | wppb ProfileBuilder CONFIRMED ACTIVE v3.16.3 | wppb ProfileBuilder MARAD (már implementálva) | Nincs változás — megtartjuk |
| NIE mező | CONFIRMED — custom_field_1 (wppb conditional, seller) | Megmarad, esetleg validáció pontosítása | Minimális módosítás |
| NIF mező | CONFIRMED — custom_field_2 (wppb conditional, business) | Megmarad, esetleg validáció pontosítása | Minimális módosítás |
| Admin jóváhagyás (regisztráció) | KÖTELEZŐ minden szerepkörnél (CURRENT CONFIRMED) | Marad kötelező | Nincs változás |
| Email visszaigazolás | AKTÍV (CURRENT CONFIRMED) | Marad aktív | Nincs változás |
| Chat | Pusher aktív — app_id: 2148163, cluster: eu (CURRENT CONFIRMED) | Marad aktív | Nincs változás |
| Search Alert | v1.2.0 AKTÍV (CURRENT CONFIRMED) | Marad aktív | Nincs változás |
| Google Maps | API konfigurált és aktív (CURRENT CONFIRMED) | Marad aktív | Nincs változás |
| AI (GPT-4o) | OpenAI konfigurált (CURRENT CONFIRMED) | Marad aktív | Nincs változás |
| Ad Type mező (Sell/Rent/Wanted) | ⛔ HIDDEN — hide_form_fields: [ad_type] (CURRENT CONFIRMED) | CLIENT DÖNTÉS SZÜKSÉGES | Üzleti döntés |
| Ingyenes hirdetések limitje | 5 db / 30 nap (CURRENT CONFIRMED) | CLIENT DÖNTÉS SZÜKSÉGES | Üzleti döntés |
| Listing lejárat | 15 nap (CURRENT CONFIRMED) | CLIENT DÖNTÉS SZÜKSÉGES | Üzleti döntés |
| Listing státusz (új/szerkesztett) | "pending" — admin jóváhagyás kötelező (CURRENT CONFIRMED) | CLIENT DÖNTÉS SZÜKSÉGES | Üzleti döntés |
| Képek száma / méret | Max 5 db, 2MB limit (CURRENT CONFIRMED) | CLIENT DÖNTÉS SZÜKSÉGES | Üzleti döntés |
| Store modul | ⛔ DISABLED — enable_store: "" (CURRENT CONFIRMED) | CLIENT DÖNTÉS SZÜKSÉGES | Üzleti döntés |
| Membership modul | ⛔ DISABLED — enable: "" (CURRENT CONFIRMED) | CLIENT DÖNTÉS SZÜKSÉGES | Üzleti döntés |
| Compare funkció | ⛔ DISABLED — enable_compare: "" (CURRENT CONFIRMED) | Marad DISABLED (vagy CLIENT DÖNTÉS) | Üzleti döntés |
| Restaurant listing | ⛔ DISABLED — enable_restaurant_listing: "" (CURRENT CONFIRMED) | Marad DISABLED | Nincs változás |
| Fizetési rendszer | Bank transfer only — placeholder (CURRENT CONFIRMED) | Teljes fizetési integráció szükséges | Fejlesztés szükséges |

---

## Nyitott kérdések összefoglalója (KLIENS DÖNTÉS SZÜKSÉGES)

Ezek kizárólag valódi üzleti döntések, amelyeket a DB export nem tud megválaszolni:

1. **Store modul**: Bekapcsoljuk a Business Seller számára? Ha igen, milyen funkciókat akarunk elérhetővé tenni?
2. **Membership / előfizetési csomagok**: Bekapcsoljuk? Milyen csomagok legyenek (ingyenes / prémium)?
3. **Ad Type (Sell/Rent/Wanted)**: Megjelenítjük a hirdetési formon? Ha igen, milyen értékek legyenek?
4. **Admin jóváhagyás — hirdetések**: Minden hirdetés "pending" státuszban indul — ez maradjon így, vagy bizonyos szerepköröknél automatikus jóváhagyás legyen?
5. **Ingyenes hirdetések limitje**: 5 db / 30 nap maradjon, vagy role-onként eltérő legyen?
6. **Listing lejárat**: 15 nap maradjon, vagy hosszabb legyen (pl. 30 nap)?
7. **NIE / NIF validáció**: Kell-e automatikus formátum-ellenőrzés (spanyol NIE / NIF regex validáció)?
8. **Verified badge**: Legyen-e vizuális jelölés (pipa / badge) az admin által jóváhagyott eladókon?
9. **Compare funkció**: Maradjon DISABLED, vagy igény van rá?
10. **Fizetési rendszer**: Mikor kerül sor a bank transfer placeholder lecserélésére valódi fizetési átjáróra (Stripe / más)?
11. **Képek száma / méret**: 5 kép / 2MB limit megfelelő, vagy szükséges módosítás?
12. **Telefonszám láthatóság**: Bejelentkezett Member (böngésző) láthatja-e az eladó telefonszámát?

**Részletes kérdéslista**: → CLIENT-CONFIRMATION.md

---

*Dokumentum vége — ACCOUNT-MATRIX.md v2.0*  
*Utoljára frissítve: 2026-08-29 — DB export alapján*

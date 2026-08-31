# USER-FLOWS.md
## Torrehub — Felhasználói folyamatok (User Flows)

**Verzió**: 2.0  
**Dátum**: 2026-08-29  
**Állapot**: DB export alapján korrigált — Store/Membership/Compare DISABLED, ProfileBuilder CONFIRMED ACTIVE

---

## FLOW-01: Regisztráció — MEMBER

```
[Főoldal] → [Regisztráció kattintás]
  ↓
[Regisztrációs oldal]
  ↓
[Fiók típus választás: MEMBER kiválasztva]
  ↓
[Form kitöltés: Név + Email + Jelszó]
  ↓
[Feltételek elfogadása]
  ↓
[Regisztráció gomb kattintás]
  ↓
[wppb ProfileBuilder: wp_insert_user() + custom_field meta + email confirmation]
  ↓
  ├── [Hiba] → Inline hibaüzenet (foglalt email, rövid jelszó)
  │               ↓ [Javítás után újra beküld]
  └── [Siker]
        ↓
       [Email megerősítés AKTÍV]
        ├── IGEN → [Megerősítő email küldés] → [Dashboard korlátozott]
        │             → [Email kattintás] → [Dashboard teljes]
        └── NEM → [Átirányítás: /fiokom/ dashboard]
  ↓
[Admin jóváhagyás értesítő emailt kap: torrehub@yahoo.com]
  ↓
[Admin approve → User account aktív lesz]
  ↓
[Felhasználó értesítő: "Fiókod jóváhagyva!"]
```

---

## FLOW-02: Regisztráció — PRIVATE SELLER

```
[Regisztrációs oldal]
  ↓
[Fiók típus: PRIVATE SELLER]
  ↓
[Form: Név + Email + Jelszó + NIE]
  ↓
[NIE validáció frontend: [X|Y|Z]\d{7}[A-Z]]
  ├── [Hibás NIE formátum] → Inline hibaüzenet
  └── [Valid NIE]
        ↓
[Regisztráció gomb]
  ↓
[wppb ProfileBuilder: wp_insert_user() + custom_field meta + email confirmation]
  ↓
[Siker → Dashboard]
  ↓
[Dashboard: "Töltsd fel NIE dokumentumodat!" CTA banner]
  ↓
[Eladói hitelesítés oldal → Dokumentum feltöltés]
  ↓
[Admin értesítő email]
  ↓
[Admin jóváhagy → rtcl_verified_seller = 1]
  ↓
["Ellenőrzött Eladó" badge megjelenik a profilon és hirdetéseken]
  ↓
[Admin jóváhagyás értesítő emailt kap: torrehub@yahoo.com]
  ↓
[Admin approve → User account aktív lesz]
  ↓
[Felhasználó értesítő: "Fiókod jóváhagyva!"]
```

---

## FLOW-03: Regisztráció — BUSINESS SELLER

```
[Regisztrációs oldal]
  ↓
[Fiók típus: BUSINESS SELLER]
  ↓
[Form: Cégnév + Kapcsolattartó + Email + Jelszó + NIF]
  ↓
[NIF validáció frontend]
  ↓
[wppb ProfileBuilder: wp_insert_user() + custom_field meta + email confirmation]
  ↓
[Siker → Dashboard]
  ↓
[Dashboard: "Töltsd fel NIF dokumentumodat!" CTA (→FLOW-11)]
  ↓
[Admin jóváhagyás befejezéséig: korlátozott állapot]
  ↓
[Admin jóváhagyás értesítő emailt kap: torrehub@yahoo.com]
  ↓
[Admin approve → User account aktív lesz]
  ↓
[Felhasználó értesítő: "Fiókod jóváhagyva!"]
```

---

## FLOW-04: Bejelentkezés

```
[Bármely protected oldal] → [Átirányítás: /bejelentkezes/]
  VAGY
[Navigáció → Bejelentkezés]
  ↓
[Email + Jelszó megadás]
  ↓
[AJAX: rtcl_login_request]
  ├── [Hiba: hibás hitelesítők] → "Hibás email vagy jelszó" + [Elfelejtett jelszó link]
  └── [Siker]
        ↓
       [Visszairányítás az eredeti oldalra] VAGY [Dashboard]
```

---

## FLOW-05: Hirdetés böngészése és kapcsolatfelvétel (Member)

```
[Főoldal / Keresési eredmények]
  ↓
[Hirdetés kártya kattintás]
  ↓
[Hirdetés részletei oldal]
  ↓
[Tartalom megtekintése: leírás, képek, custom mezők, térkép]
  ↓
[Elérhetőség szekció]
  ├── [Telefonszám gomb] → [AJAX: rtcl_phone_click] → Telefon megjelenítve
  ├── [WhatsApp gomb] → [AJAX: rtcl_whatsapp_click] → WhatsApp link megnyílik
  ├── [Chat gomb] → [/fiokom/chat/?conversation=X]
  │     ├── [Ha nincs bejelentkezve] → Login modal → Vissza
  │     └── [Ha bejelentkezett] → Chat ablak megnyílik
  ├── [Email form] → [Üzenet írása] → [AJAX: rtcl_public_send_contact_email]
  └── [Kedvencekhez adás ❤️] → [AJAX: rtcl_public_add_remove_favorites]
```

---

## FLOW-06: Keresés és szűrés

```
[Főoldal keresősáv]
  ↓
[Kulcsszó + Kategória + Helyszín megadás]
  ↓
[Keresés gomb]
  ↓
[Hirdetések archive oldal — URL paraméterekkel]
  ↓
[Oldalsó szűrők módosítása]
  ├── Kategória szűrő → URL frissítés
  ├── Ár tartomány → URL frissítés
  ├── Hirdetés típusa → URL frissítés
  └── Custom mezők → URL frissítés
  ↓
[Lista / Rács / Térkép nézet váltás]
  ↓
[Rendezés módosítása]
  ↓
[KERESÉSI ÉRTESÍTŐ MENTÉSE]
  ↓
[rtcl-search-alert: paraméterek mentése MD5 hash-sel]
  ↓
["Értesítő elmentve — Frekvencia beállítása: [Napi/Heti/Havi]"]
```

---

## FLOW-07: Hirdetés feladása

```
[Dashboard → Hirdetés feladása VAGY /fiokom/add-listing/]
  ↓
[Jogosultság ellenőrzés]
  ├── [Nem seller] → "Ehhez eladói fiók szükséges" üzenet
  └── [Seller]
        ↓
[1. lépés: Kategória választás]
  ↓
[2. lépés: Alap adatok]
  ↓
[3. lépés: Category-specifikus custom mezők]
  ↓
[4. lépés: Galéria feltöltés]
  ↓
[5. lépés: Helyszín + Térkép]
  ↓
[6. lépés: Videó URL (opcionális)]
  ↓
[7. lépés: Csomag választás]
  ├── [Ingyenes keret elérhető] → Ingyenes opció
  ├── [Membership keret] → Membership slot
  └── [Fizetős] → Checkout → Fizetés → Visszairányítás
  ↓
[8. lépés: Előnézet]
  ↓
[Beküldés]
  ↓
[AJAX: rtcl_post_new_listing]
  ↓
[post_status = rtcl-temp → rtcl-pending]
  ↓
["Hirdetés beküldve — adminisztrátori jóváhagyásra vár"]
  ↓
[Admin email értesítő]
  ↓
[Admin jóváhagyja → post_status = publish]
  ↓
[Eladó email értesítő: "Hirdetésed élesítve!"]
```

---

## FLOW-08: Hirdetés megújítása (lejárt)

```
[Dashboard → Hirdetéseim → Lejárt hirdetés]
  ↓
[Megújítás gomb]
  ↓
[AJAX: rtcl_ajax_renew_listing]
  ├── [Van aktív membership keret] → Automatikus megújítás
  └── [Nincs keret]
        ↓
       [Membership vásárlás CTA]
        ↓
       [Membership checkout → Fizetés → Megújítás]
```

---

## FLOW-09: Membership vásárlás — ⛔ DISABLED

**Státusz**: Membership modul KIKAPCSOLVA (rtcl_membership_settings.enable: "")  
**Teendő**: Client döntés szükséges az új rendszerhez.  
*Ez a flow az új rendszerben csak akkor releváns, ha a client aktiválni akarja a membership modult.*

---

## FLOW-10: Store létrehozás (Business Seller) — ⛔ DISABLED

**Státusz**: Store modul KIKAPCSOLVA (rtcl_membership_settings.enable_store: "")  
**Teendő**: Client döntés szükséges az új rendszerhez.  
*Ez a flow az új rendszerben csak akkor releváns, ha a client aktiválni akarja a Store modult.*

---

## FLOW-11: Eladói hitelesítés

```
[Dashboard → Eladói hitelesítés]
  ↓
[Dokumentumok feltöltése form]
  ├── Fotó azonosító (NIE/NIF fotó) — kötelező
  └── Egyéb dokumentum (opcionális)
  ↓
[Feltöltés gomb]
  ↓
[AJAX: dokumentum feltöltés]
  ↓
[WP Media Library: attachment ID mentve → photo_id user meta]
  ↓
[Admin email értesítő: "Új hitelesítési kérelem: [Felhasználó neve]"]
  ↓
[Admin → WP Users → [Felhasználó] → Seller Documents szekció]
  ↓
[Admin megtekinti a dokumentumokat]
  ↓
  ├── [Elfogad] → "Verify the seller" checkbox pipálása
  │                 → rtcl_verified_seller = 1 user meta
  │                 → Felhasználó email értesítő: "Hitelesítés jóváhagyva!"
  │                 → "Ellenőrzött Eladó" badge megjelenik
  └── [Elutasít] → Felhasználó értesítő: "Hitelesítés elutasítva"
                     → Lehetőség új dokumentum feltöltésre
```

---

## FLOW-12: Keresési értesítő kezelése

```
[Keresés elvégzése az archive oldalon]
  ↓
["Értesítő mentése" gomb]
  ↓
[Ha nincs bejelentkezve → Login modal]
  ↓
[rtcl-search-alert: szűrő paraméterek mentése + MD5 hash]
  ↓
["Értesítő elmentve!" megerősítés]
  ↓
[Frekvencia beállítása: Napi / Heti / Havi]
  ↓
[Scheduler: WooCommerce Action Scheduler]
  ↓
[Ütemezett időpontban:]
  → Mentett szűrő futtatása
  → Egyező new listing-ek lekérése
  → Email küldés: "Új hirdetések a keresésednek megfelelően"
  → Leiratkozási link az emailben
```

---

## FLOW-13: Review írása

```
[Hirdetés részletek oldal → Review szekció]
  ↓
[Ha nincs bejelentkezve → Login CTA]
  ↓
[Review form megjelenítése]
  ↓
[Csillag értékelés + Szöveg kitöltése]
  ↓
[Beküldés]
  ├── [Saját hirdetés] → "Saját hirdetésedet nem értékelheted" hiba
  └── [Más hirdetése]
        ↓
       [review-schema plugin kezeli]
        ↓
       [Admin moderáció (ha bekapcsolva)]
        ↓
       [Review megjelenik a hirdetésen + Rating frissül]
```

---

## FLOW-14: Összehasonlítás (Compare) — ⛔ DISABLED

**Státusz**: Compare modul KIKAPCSOLVA (rtcl_general_settings.enable_compare: "")  
**Megjegyzés**: Ez a funkció a jelenlegi rendszerben is inaktív.

---

## FLOW-15: Jelszó visszaállítás

```
[Bejelentkezési oldal → "Elfelejtett jelszó"]
  ↓
[Email megadás]
  ↓
[Email nem létezik] → "Ha ez az email regisztrált, küldtünk emailt" (biztonsági szempontból)
  ↓
[Email küldés: jelszó visszaállítási link]
  ↓
[Link kattintás]
  ↓
[Lejárt token] → "A link lejárt — kérj új jelszó-visszaállítást"
  ↓
[Érvényes token]
  ↓
[Új jelszó megadása]
  ↓
[Jelszó mentése]
  ↓
[Automatikus bejelentkezés + Dashboard]
```

---

## FLOW-16: Kijelentkezés

```
[Dashboard nav → Kijelentkezés]
  ↓
[Session törlése + Compare session törlése]
  ↓
[Átirányítás: Főoldal]
```

---

## Kritikus flow összefoglalók (designer számára)

| Flow | Lépések száma | Komplex? | Megjegyzés |
|------|--------------|---------|------------|
| MEMBER regisztráció | 5 lépés + admin approve | Egyszerű | wppb ProfileBuilder |
| PRIVATE SELLER regisztráció | 6 lépés + admin approve | Közepes | NIE validáció, wppb |
| BUSINESS SELLER regisztráció | 6 lépés + admin approve | Közepes | NIF validáció, wppb |
| Hirdetés feladása | 8 lépés multi-step | Összetett | Legkritikusabb UX |
| Eladói hitelesítés | 3 lépés user + admin | Közepes | Manual admin step |
| Membership vásárlás | — | ⛔ DISABLED | Client döntés szükséges |
| Store létrehozás | — | ⛔ DISABLED | Client döntés szükséges |
| Compare | — | ⛔ DISABLED | Jelenleg is inaktív |
| Keresés + szűrés | Instant | Egyszerű | — |

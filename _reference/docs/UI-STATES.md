# UI-STATES.md
## Torrehub — UI állapotok teljes listája

**Verzió**: 1.0  
**Dátum**: 2026-08-28

---

## 1. HIRDETÉS STÁTUSZ ÁLLAPOTOK

### 1.1 Hirdetés kártya állapotok (archive / listing grid)

| Állapot | Megjelenítés | Badge | Interakció |
|---------|-------------|-------|-----------|
| Aktív | Normal | — | Kattintható |
| Kiemelt (Featured) | Kiemelve (border/label) | "Kiemelt" / "Featured" | Kattintható |
| Sürgős (Urgent) | Piros jelzés | "Sürgős" / "Urgent" | Kattintható |
| Lejárt | Halvány / overlay | "Lejárt" | Kattintható (részletekhez) |
| Függőben | Csak saját dashboard-on | "Jóváhagyásra vár" | Nem publikus |
| Temp/Piszkozat | Csak saját dashboard-on | "Piszkozat" | Nem publikus |

### 1.2 Hirdetés részletek oldal (single listing) állapotok

| Állapot | Fejléc tartalom | Sidebar | Műveletek |
|---------|-----------------|---------|-----------|
| Aktív — nem bejelentkezett | Teljes tartalom | Email form | Telefon rejtett; Chat → Login |
| Aktív — bejelentkezett (Member) | Teljes tartalom | Email form + Chat | Telefon + WhatsApp látható |
| Aktív — saját hirdetés | Teljes tartalom | Szerkesztés/Törlés gombok | Szerkesztés; Nincs self-contact |
| Lejárt hirdetés | "Ez a hirdetés lejárt" sáv | Korlátozott | Elrejtett contact gombok |
| Felfüggesztett | "Ez a hirdetés nem elérhető" | — | — |
| Nem található (404) | 404 oldal | — | Hasonló hirdetések ajánlat |

---

## 2. ELADÓI HITELESÍTÉS ÁLLAPOTOK

| Állapot | User meta | Badge | Dashboard üzenet |
|---------|-----------|-------|-----------------|
| Nem hitelesített | `rtcl_verified_seller` = nincs | — | ⚠️ "Töltsd fel dokumentumodat" |
| Dokumentum feltöltve | `photo_id` van; verified = nincs | — | 🕐 "Ellenőrzés alatt" |
| Hitelesített | `rtcl_verified_seller = 1` | ✅ "Ellenőrzött" | ✅ "Ellenőrzött Eladó" |
| Elutasított | Admin által (custom state?) | — | ❌ "Elutasítva — Töltsd fel újra" |

**Hol jelenik meg a badge** (rtcl-seller-verification plugin alapján):
- Hirdetés részletek → Author meta után (`rtcl_after_author_meta` hook)
- Hirdetés sidebar → Eladói info szekció (`rtcl_listing_seller_information` hook)
- Store oldal → Store cím után (`rtcl_after_store_title` hook)

---

## 3. MEMBERSHIP ÁLLAPOTOK — ⛔ DISABLED

**Státusz**: Membership modul KIKAPCSOLVA (`rtcl_membership_settings.enable: ""`)  
Nincs konfigurált csomag. Client döntés szükséges → CLIENT-CONFIRMATION.md C1

**Jelenlegi helyett** — Ingyenes keret állapotok:

| Állapot | Megjelenítés | CTA |
|---------|-------------|-----|
| Van szabad keret | Zöld info: "[X] / 5 ingyenes hirdetés marad ebben a hónapban" | — |
| Kifogyott a keret | Sárga figyelmeztetés: "5 ingyenes hirdetésből 5-öt felhasználtál" | — |

---

## 4. FIZETÉSI TRANZAKCIÓ ÁLLAPOTOK

| Állapot | Post status | Badge szín | Leírás |
|---------|------------|------------|--------|
| Kész | `rtcl-created` / `rtcl-completed` | Zöld | Sikeres fizetés |
| Függőben | `rtcl-processing` / `rtcl-on-hold` | Sárga | Feldolgozás alatt |
| Meghiúsult | `rtcl-failed` | Piros | Fizetési hiba |
| Lemondva | `rtcl-cancelled` | Szürke | Lemondva |
| Visszatérítve | `rtcl-refunded` | Kék | Visszatérítve |

---

## 5. HIRDETÉS FELADÁSI FOLYAMAT ÁLLAPOTOK

| Lépés | Állapot | Üzenet |
|-------|---------|--------|
| Kategória választás | — | "Válassz kategóriát a folytatáshoz" |
| Alap adatok kitöltés | Folyamatban | Progress bar mutatja |
| Galéria feltöltés | Feltöltés közben | Loader + progress százalék |
| Galéria feltöltés kész | Kész | Bélyegképek megjelennek |
| Galéria feltöltés hiba | Hiba | "A fájl mérete meghaladja a maximumot" |
| Beküldés folyamatban | Loading | "Hirdetés feldolgozás alatt..." |
| Beküldés sikeres | Siker | "Hirdetésed beküldve! Admin jóváhagyásra vár." |
| Beküldés hiba | Hiba | "Hiba történt — próbáld újra" |

---

## 6. FORM VALIDÁCIÓS ÁLLAPOTOK

### 6.1 Mező állapotok

| Állapot | Vizuális jelzés | Szöveg |
|---------|-----------------|--------|
| Alap | Normál keret | — |
| Fókuszban | Kiemelés (border/glow) | — |
| Kitöltve — érvényes | Zöld check ikon | — |
| Kitöltve — érvénytelen | Piros keret | Hibaüzenet a mező alatt |
| Kötelező — üresen hagyta | Piros keret | "Ez a mező kötelező" |
| NIE/NIF formátum hiba | Piros keret | "Érvénytelen NIE formátum (pl: X1234567A)" |
| Email formátum hiba | Piros keret | "Érvénytelen email cím" |
| Email már foglalt | Piros keret | "Ez az email cím már regisztrált" |
| Jelszó nem egyezik | Piros keret | "A jelszavak nem egyeznek" |

### 6.2 Form szintű állapotok

| Állapot | Megjelenítés |
|---------|-------------|
| Beküldés előtt | Normál form |
| Beküldés folyamatban | Gomb: letiltva + spinner |
| Hiba (szerver) | Form tetején piros üzenet |
| Siker | Form eltűnik + zöld üzenet / átirányítás |

---

## 7. BETÖLTÉSI ÁLLAPOTOK (Loading States)

| Összetevő | Loading állapot |
|-----------|----------------|
| Hirdetés lista | Skeleton cards (szürke placeholder kártyák) |
| Kép feltöltés | Progress bar + százalék |
| Térkép betöltés | Szürke placeholder |
| Chat üzenet küldés | Buborék "elküldés" state |
| AJAX műveletek | Spinner + gomb letiltva |
| Oldal első betöltés | — (SSR/PHP — nem kell JS loading state) |

---

## 8. ÜRES ÁLLAPOTOK (Empty States)

| Oldal / Szekció | Üzenet | CTA |
|-----------------|--------|-----|
| Hirdetés archive — nincs találat | "Nem találtunk hirdetéseket a keresési feltételeknek megfelelően." | [Szűrők törlése] |
| Kedvenceim — üres | "Még nincsenek kedvenc hirdetéseid. Böngészd a hirdetéseket!" | [Hirdetések böngészése] |
| Hirdetéseim — üres | "Még nincs egyetlen hirdetésed sem." | [Hirdetés feladása] |
| Chat — üres | "Még nincsenek üzeneteid." | [Hirdetések böngészése] |
| Keresési értesítők — üres | "Még nincsenek mentett kereséseid." | [Keresés indítása] |
| Fizetési előzmények — üres | "Még nincsenek fizetési előzményeid." | [Hirdetések böngészése] |
| Kategória oldal — nincs hirdetés | "Ebben a kategóriában még nincsenek hirdetések." | [Összes hirdetés megtekintése] |

---

## 9. JOGOSULTSÁGI ÁLLAPOTOK

| Szituáció | Üzenet | CTA |
|-----------|--------|-----|
| Nem bejelentkezett → Telefon | "A telefonszám megtekintéséhez be kell jelentkezni" | [Bejelentkezés] |
| Nem bejelentkezett → Chat | "Az üzenetküldéshez be kell jelentkezni" | [Bejelentkezés] |
| Nem bejelentkezett → Kedvencek | "A kedvencekhez adáshoz be kell jelentkezni" | [Bejelentkezés] |
| Member → Hirdetés feladás | "Hirdetés feladásához eladói fiókot kell létrehoznod" | [Fiók frissítése] |
| Nem hitelesített Seller → Store | ⛔ Store DISABLED — nem releváns jelenleg | — |
| Lejárt membership → Hirdetés | ⛔ Membership DISABLED — nem releváns jelenleg | — |

---

## 10. RENDSZER ÉRTESÍTÉSEK (Notifications)

### 10.1 Email értesítők (RTCL küld)

| Esemény | Címzett | Tartalom |
|---------|---------|---------|
| Új regisztráció | Admin | Új felhasználó adatai |
| Email megerősítés | Felhasználó | Megerősítő link |
| Hirdetés beküldve | Admin | Jóváhagyási kérelem |
| Hirdetés jóváhagyva | Eladó | "Hirdetésed élesítve!" |
| Hirdetés elutasítva | Eladó | "Hirdetésed nem lett jóváhagyva" |
| Hirdetés lejárt | Eladó | "Hirdetésed lejárt — megújítsd!" |
| Új chat üzenet | Mindkét fél | Üzenet értesítő |
| Membership lejárás közeleg | ~~Eladó~~ | ⛔ DISABLED — Membership modul kikapcsolva |
| Membership fizetés | ~~Eladó~~ | ⛔ DISABLED — Membership modul kikapcsolva |
| Hitelesítési kérelem | Admin | "Új dokumentum feltöltve: [Felhasználó]" |
| Hitelesítés jóváhagyva | Eladó | "Ellenőrzött Eladó badge megszereztél!" |
| Új keresési értesítő találat | Felhasználó | "X új hirdetés a keresésednek megfelelően" |
| Kapcsolatfelvételi email | Eladó | Vevő üzenete |

### 10.2 In-app értesítők (frontend)

| Esemény | Típus | Hely |
|---------|-------|------|
| Sikeres bejelentkezés | Success toast | Jobb felső sarok |
| Sikeres hirdetés feladás | Success banner | Dashboard |
| Kedvencekhez adva | Mini toast | Kártya mellett |
| Hiba (form) | Inline piros szöveg | Mező alatt |
| Hiba (AJAX) | Error toast | Jobb felső sarok |
| Chat olvasatlan üzenet | Badge | Nav ikonon |
| Membership lejárat | ⛔ DISABLED | — |

---

## 11. RESPONSIVE ÁLLAPOTOK

| Töréspontok | Változások |
|------------|-----------|
| Desktop (> 1200px) | Teljes oldalsó nav, 3-4 oszlopos grid, side-by-side chat |
| Tablet (768-1200px) | Összeomlasztható nav, 2 oszlopos grid, modal chat |
| Mobil (< 768px) | Bottom nav, 1 oszlopos, full-screen chat, modal filters |

### Mobil-specifikus állapotok
- **Szűrő panel**: Összecsukott → gombra megnyílik (full-screen overlay)
- **Térkép**: Toggle gomb — lista VAGY térkép (egyszerre nem mindkettő)
- **Chat**: Lista → tap → full-screen chat ablak
- **Galéria**: Swipe navigation
- **Hirdetés feladás**: Step-by-step full-screen wizard

---

## 12. HIBA OLDALAK

| Hibakód | URL | Tartalom |
|---------|-----|---------|
| 404 | Bármely nem létező URL | "Oldal nem található" + keresőmező + főoldal gomb |
| Lejárt session | — | "Session lejárt, kérlek lépj be újra" → Login átirányítás |
| Szerver hiba | — | "Valami hiba történt — próbáld újra" |
| Karbantartási mód | — | Egyedi maintenance page |

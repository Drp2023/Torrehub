# CLIENT-CONFIRMATION.md
## Torrehub — Klienstől szükséges döntések

**Verzió**: 2.0 (DB export alapján minimalizálva)
**Dátum**: 2026-08-29
**Állapot**: Csak valódi üzleti/termék döntések — technikai és ismert adatok eltávolítva

---

## Mit tudunk már a DB exportból? (Nem kell kérdezni)

| Terület | Adat | Forrás |
|---------|------|--------|
| Kategóriák | 152 db, 10 főkategória, Costa Blanca helyszínek | 01-categories.json |
| Helyszínek | 35 lapos listás Costa Blanca város | 02-locations.json |
| Regisztrációs rendszer | ProfileBuilder (wppb) v3.16.3 ACTIVE, lifetime license | 08-profilebuilder.json |
| 3 fiók típus | customer / seller / business WP role (már implementálva) | 07-account-types.json |
| NIE mező | custom_field_1 (wppb conditional, ha seller) — MÁR LÉTEZIK | 08-profilebuilder.json |
| NIF mező | custom_field_2 (wppb conditional, ha business) — MÁR LÉTEZIK | 08-profilebuilder.json |
| Email megerősítés | AKTÍV (emailConfirmation: "yes") | 08-profilebuilder.json |
| Admin jóváhagyás | KÖTELEZŐ minden szerepkörnél | 07-account-types.json |
| Google Maps | AKTÍV, API key konfigurált, központ: Torrevieja | 06-rtcl-options.json |
| Chat (Pusher) | AKTÍV (app_id: 2148163, cluster: eu) | 06-rtcl-options.json |
| Pénznem | EUR (€), bal pozíció | 06-rtcl-options.json |
| Fizetés (jelenlegi) | Banki átutalás (placeholder) — Stripe/PayPal nincs konfigurálva | 06-rtcl-options.json |
| Ingyenes hirdetések | 5 db / 30 nap | 06-rtcl-options.json |
| Hirdetés lejárat | 15 nap default, törlés +15 nap múlva | 06-rtcl-options.json |
| Max. képek | 5 db, PNG/JPG/JPEG/WEBP, max 2MB | 06-rtcl-options.json |
| Membership | ⛔ DISABLED | 06-rtcl-options.json |
| Store | ⛔ DISABLED | 06-rtcl-options.json |
| Compare (összehasonlítás) | ⛔ DISABLED | 06-rtcl-options.json |
| Restaurant listing | ⛔ DISABLED | 06-rtcl-options.json |
| Hirdetés típus (Sell/Rent/Wanted) | HIDDEN a jelenlegi formban | 06-rtcl-options.json |
| Brand színek | Primary: #0056b3, Button/Featured: #FF8C00 | 06-rtcl-options.json |
| Keresési értesítő | AKTÍV (rtcl-search-alert v1.2.0) | 06-rtcl-options.json |
| AI (GPT-4o) | AKTÍV, OpenAI API key konfigurált | 06-rtcl-options.json |

---

## DÖNTÉSEK SZÜKSÉGESEK

### SZEKCIÓ A: Regisztráció és fiók

**A1. Hirdetés típus (Sell / Rent / Wanted) szükséges-e?**

A jelenlegi rendszerben az `ad_type` mező REJTETT (`hide_form_fields: [ad_type]`).  
Az új rendszerben mi legyen?

- [ ] Maradjon rejtett — minden hirdetés típus nélkül jelenik meg
- [ ] Legyen elérhető — Sell / Rent / Wanted választó a hirdetésfeladásnál
- [ ] Módosított változat: csak Sell és Rent (Wanted nélkül)

**Válasz**: _________________________________

---

**A2. Social Login (közösségi média bejelentkezés) szükséges-e?**

A jelenlegi rendszerben nincs Google/Facebook bejelentkezés.

- [ ] Nem szükséges — email + jelszó elegendő
- [ ] Google-lal bejelentkezés
- [ ] Facebook-kal bejelentkezés
- [ ] Google + Facebook

**Válasz**: _________________________________

---

**A3. OTP telefonos ellenőrzés szükséges-e?**

(rtcl-verification plugin — Firebase/SMS gateway szükséges, extra költség)

- [ ] Nem szükséges
- [ ] Igen — Firebase alapú
- [ ] Igen — SMS Gateway (melyik?)

**Válasz**: _________________________________

---

**A4. Member → Seller fiók frissítés lehetséges-e?**

Ha valaki MEMBER-ként regisztrál, később válhat-e PRIVATE SELLER-ré vagy BUSINESS SELLER-ré?

- [ ] Igen — fiók típus frissíthető (kell NIE/NIF pótlás + admin jóváhagyás)
- [ ] Nem — a fiók típus rögzített regisztrációkor, csak új fiókkal lehet másik típus

**Válasz**: _________________________________

---

**A5. Bejelentkezett MEMBER látja-e a hirdetésen a telefonszámot?**

- [ ] Igen — minden bejelentkezett felhasználó látja (Member + Seller + Business Seller)
- [ ] Nem — csak eladók (Seller + Business Seller) látják

**Válasz**: _________________________________

---

### SZEKCIÓ B: Üzleti funkciók

**B1. Store (Üzlet profil) szükséges-e az új rendszerben?**

Jelenleg DISABLED. Business Seller-eknek legyen-e nyilvános üzlet oldal (cégnév, logó, banner, leírás, saját hirdetések listája)?

- [ ] Igen — kell Store funkció Business Seller-eknek
- [ ] Nem — Business Seller-ek is csak sima profillal rendelkeznek
- [ ] Igen, de csak bizonyos feltételekkel: _____________

**Válasz**: _________________________________

---

**B2. Eladói hitelesítés (Verified badge) kötelező-e hirdetés feladáshoz?**

- [ ] Kötelező — nem hitelesített eladó nem adhat fel hirdetést
- [ ] Ajánlott, de nem kötelező — nem hitelesített is feladhat hirdetést, de badge nélkül
- [ ] Nem kötelező — a hitelesítés csak opcionális prémium funkció

**Válasz**: _________________________________

---

**B3. Foglalás (Booking) funkció szükséges-e?**

(radius-booking plugin — szálláshely, étterem, szolgáltatás foglalások)

- [ ] Nem szükséges
- [ ] Igen — bizonyos kategóriákhoz (melyek?): _____________

**Válasz**: _________________________________

---

### SZEKCIÓ C: Pénzügy és membership

**C1. Membership / Előfizetési csomagok lesznek-e az új rendszerben?**

A jelenlegi rendszerben Membership DISABLED és nincs egyetlen csomag sem konfigurálva.

- [ ] Nem — minden hirdetés ingyenes (max 5 db / 30 nap a jelenlegi limit)
- [ ] Igen — lesznek fizetős csomagok (kérjük adja meg: csomag neve, ár, hirdetés szám, lejárat)

Ha igen, csomag adatok:

| Csomag neve | Ár (EUR) | Hirdetések száma | Érvényesség (nap) | Promóciók |
|-------------|----------|------------------|--------------------|-----------|
| | | | | |
| | | | | |

**Válasz**: _________________________________

---

**C2. Fizetési rendszer az új rendszerben?**

Jelenleg csak banki átutalás van (placeholder). Mi legyen aktív?

- [ ] Banki átutalás marad (manuális visszaigazolás)
- [ ] Stripe (kártya)
- [ ] PayPal
- [ ] Authorize.NET
- [ ] Más: _____________
- [ ] Egyelőre nem kell — a site csak ingyenes hirdetésekkel indul

**Válasz**: _________________________________

---

**C3. Számla kiállítás szükséges-e fizetések után?**

- [ ] Igen — automatikus számla
- [ ] Nem szükséges

**Válasz**: _________________________________

---

### SZEKCIÓ D: Design és tartalom

**D1. Van-e referencia oldal vagy design inspiráció?**

Milyen stílusú, hangulatú weboldalt szeretne? Hasonló platformok Costa Blanca régióban vagy általánosan:

**Válasz**: _________________________________

---

**D2. Logó fájl és branding anyagok rendelkezésre állnak-e?**

Ismert brand színek a DB-ből: Primary `#0056b3`, Button/Featured `#FF8C00`.  
Mi szükséges még:

- [ ] Logó fájl (SVG vagy PNG, legalább 300px széles) → kérjük mellékelni
- [ ] Van kész logó fájl: Igen / Nem
- [ ] Favicon fájl: Van / Nincs / Logóból legyen generálva

**Válasz**: _________________________________

---

**D3. Végleges nyelvi scope megerősítése**

Ismert tervezett nyelvek: **EN, ES, SV, FI, RO, HU**

- [ ] Ez a lista végleges
- [ ] Módosítandó: _____________
- [ ] Az induláskor csak: _____ (a többi később)

A fordítások honnan jönnek? (ki fordítja?)

**Válasz**: _________________________________

---

**D4. URL struktúra többnyelvű rendszerben?**

Ha WPML vagy más fordítási plugin aktív, mi legyen az URL prefix?

| Nyelv | URL prefix példa |
|-------|-----------------|
| Angol | `/en/` VAGY `/` (alap) |
| Spanyol | `/es/` VAGY `/` (alap) |
| Svéd | `/sv/` |
| Finn | `/fi/` |
| Román | `/ro/` |
| Magyar | `/hu/` |

- [ ] Spanyol az alap (prefix nélkül), többi prefixel
- [ ] Angol az alap (prefix nélkül), többi prefixel
- [ ] Minden nyelvnek prefix kell

**Válasz**: _________________________________

---

## Megerősítési státusz

| Szekció | Kérdések száma | Megválaszolva | Utolsó frissítés |
|---------|---------------|---------------|-----------------|
| A — Regisztráció és fiók | 5 | 0/5 | — |
| B — Üzleti funkciók | 3 | 0/3 | — |
| C — Pénzügy és membership | 3 | 0/3 | — |
| D — Design és tartalom | 4 | 0/4 | — |
| **ÖSSZESEN** | **15** | **0/15** | **2026-08-29** |

---

## Megjegyzés a fejlesztőnek

A 35 kérdéses v1.0 változatból 20 kérdés eltávolítva, mert a DB export megválaszolta azokat.  
A maradék 15 kérdés valódi termék/üzleti döntés, amit csak a kliens tud megválaszolni.

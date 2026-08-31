# DESIGN-BRIEF.md
## Torrehub — Design Brief (Tervezési iránymutatás)

**Verzió**: 2.0 (DB export + termékvízió alapján frissítve)
**Dátum**: 2026-08-29
**Célközönség**: Grafikai designer / UI/UX tervező

---

## 1. Projekt összefoglaló

**Projekt neve**: Torrehub  
**Típus**: Costa Blanca helyi hub — üzleti könyvtár + apróhirdetések + helyi információk  
**Helyszín**: Costa Blanca régió, Spanyolország (Torrevieja és környéke)  
**Platform**: Egyedi WordPress téma (Elementor nélkül)  
**Cél**: A jelenlegi, Elementor-alapú CLDirectory weboldal teljes újratervezése egyedi, performáns custom témával

### Mit csinálja az oldal?

Torrehub nem egyszerű apróhirdetési oldal — a **Costa Blanca régió helyi digitális hub-ja**:

- **Üzleti könyvtár (Business Directory)**: Helyi vállalkozások, éttermek, szolgáltatók, turisztikai attrakciók megtalálása
- **Apróhirdetések (Classifieds)**: Magánszemély és üzleti eladók hirdetései (ingatlan, jármű, piactér, munkahirdetések)
- **Helyi információk (Local Info)**: Események, közérdekű információk, turistáknak szóló tartalom
- **3 fiók típus**: Böngésző tag (Member) + Magánszemély eladó (Private Seller) + Üzleti eladó (Business Seller)
- **Spanyol jogszabályi megfelelés**: NIE/NIF azonosítók az eladói regisztrációban

### Célközönség

| Célcsoport | Leírás | Elsődleges igény |
|------------|--------|-----------------|
| **Helyi lakosok (Locals)** | Spanyol anyanyelvűek, Alicante tartomány | Helyi hirdetések, munkahirdetések, szolgáltatók keresése |
| **Expatok (Expats)** | Külföldi letelepedők — főként skandinávok, britek, románok, magyarok | Lakáshirdetések, megbízható helyi vállalkozások keresése |
| **Turisták (Tourists)** | Szezonális látogatók | Éttermek, turisztikai attrakciók, szálláshirdetések |

### Nyelvek

A platform 6 nyelven érhető el:

| Kód | Nyelv | Célközönség |
|-----|-------|-------------|
| EN | Angol | Britek, általános lingua franca |
| ES | Spanyol | Helyi lakosok |
| SV | Svéd | Skandináv expatok |
| FI | Finn | Finn expatok |
| RO | Román | Román munkavállalók és letelepedők |
| HU | Magyar | Magyar expatok |

### Kategóriák (CONFIRMED — 152 db, 10 főkategória)

Auto/Moto/Boats · Events · Jobs · Leisure & Sport · Marketplace · Properties · Public Information · Restaurants & Nightlife · Services · Tourist Attractions

---

## 2. Design elvek

### 2.1 Prioritások sorrendben
1. **Sebesség** — Minden elemnek mobilon is gyorsan kell töltődnie
2. **Egyszerűség** — Kevesebb az több; semmi fölösleges vizuális elem
3. **Megbízhatóság** — A felhasználónak biztonságban kell éreznie magát (hitelesítési badge-ek, verified seller)
4. **Konverzió** — Egyértelmű CTA-k a regisztrációhoz és hirdetés feladáshoz

### 2.2 Design stílus
- **Minimalista** — Tiszta, strukturált layout
- **Professional** — Nem játékos, megbízható megjelenés
- **Mobile-first** — Elsőként mobilra tervezni

### 2.3 SZIGORÚAN TILOS
- ❌ Glassmorphism (backdrop-filter, blur effektek)
- ❌ Box-shadow (árnyékok lassítják a mobilt)
- ❌ Komplex gradiensek (csak egyszerű solid colorok)
- ❌ Bonyolult animációk (max 0.2s, ease-out)
- ❌ Felesleges effektek
- ❌ "Szép de lassú" megoldások

### 2.4 MEGENGEDETT / AJÁNLOTT
- ✅ Egyszínű (solid) háttérszínek
- ✅ Egyszerű border/border-radius
- ✅ Minimális hover state változás (szín/opacity)
- ✅ Gyors CSS transition (max 0.15-0.2s)
- ✅ Ikonok (SVG alapú — Font Awesome vagy egyedi)
- ✅ Fehér tér (whitespace) — sokat! 
- ✅ Grid-alapú layout (12-oszlopos)

---

## 3. Szín paletta

### 3.1 Meglévő brand színek (CURRENT CONFIRMED — DB exportból)

| Szín neve | Hex | Jelenlegi használat |
|-----------|-----|---------------------|
| Primary | `#0056b3` | Fő brandszín (rtcl primary color) |
| Button / Featured | `#FF8C00` | Gombok, kiemelt hirdetés jelzők |
| wppb Primary | `#1079f3` | ProfileBuilder form elemek |

### 3.2 Design directions — nyitott döntések

**⚠️ A 3 design direction keretében a designer határozza meg:**
- Teljes szín paletta (primary mellé: secondary, success, warning, danger, neutral árnyalatok)
- Tipográfiai rendszer (betűtípus választás, méret skála, súly hierarchia)
- Vizuális stílus (minimalizmus mértéke, spacing rendszer, border-radius konvenció)

A meglévő `#0056b3` és `#FF8C00` brandszíneket a design directions-be be kell illeszteni — felhasználhatók, módosíthatók vagy kiegészíthetők a designer javaslata alapján.

---

## 4. Tipográfia

**⚠️ Betűtípus választás a 3 design direction részeként — nem előre meghatározott.**

Technikai elvárások a betűtípus választáshoz:
- **Teljesítmény**: Maximum 2 betűtípus (heading + body), max 3-4 weight variáns összesen
- **Multilinguális**: Latin karakterkészlet minden célnyelvet fed (EN/ES/SV/FI/RO/HU) — Google Fonts Latin Extended elegendő
- **Alternatíva**: System font stack (leggyorsabb, nulla extra betöltési idő):
  ```
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
  ```
- **Minimális méret hierarchia**: H1 / H2 / H3 / body / small / label — a konkrét méretek a design direction-ben

**Tipográfiai szerepek** (méret nélkül, a designer határozza meg):
- Főcím (H1): Nagy, erős, bizalmat sugárzó
- Alcím (H2): Struktúra, szekció jelölő
- Body: Olvasható, közepes méret, hosszabb szövegekre optimalizált
- Label / Meta: Kis méretű kiegészítő információk (ár, helyszín, dátum)

---

## 5. Komponens könyvtár (Tervező számára)

### 5.1 Gombok
- **Primary** — Solid háttér + fehér szöveg + kis border-radius
- **Secondary** — Outline gomb
- **Danger** — Piros (törlés, visszaélés)
- **Ghost** — Háttér nélkül, csak ikon
- **Loading state** — Spinner + letiltott
- **Méretek**: sm / md / lg

### 5.2 Hirdetés kártya
*(Szükséges variánsok)*
- Grid kártya (négyzetes thumbnail)
- Lista kártya (széles, kisebb thumbnail)
- Kiemelt (Featured) variáns — vizuálisan kiemelve
- Sürgős (Urgent) variáns — piros jelzővel
- Lejárt variáns — halvány overlay
- Dashboard lista sor (szerkesztés/törlés gombokkal)

### 5.3 Store kártya
- Logó + Név + Kategória + Verified badge + Hirdetések száma

### 5.4 Badge-ek
- "Ellenőrzött Eladó" (zöld, pipa ikon)
- "Ellenőrzött Üzlet" (kék, épület ikon)
- "Kiemelt" (arany/sárga)
- "Sürgős" (piros)
- "Lejárt" (szürke)
- "Függőben" (sárga)
- Fiók típus: "Member" / "Private Seller" / "Business Seller"

### 5.5 Form elemek
- Input mező (alap, fókusz, error, disabled állapot)
- Select / Dropdown
- Checkbox + Radio
- Textarea
- File upload (drag & drop zóna)
- Star rating (5 csillag)
- Range slider (ár szűrőhöz)

### 5.6 Navigáció
- Főmenü (desktop + mobil hamburger)
- Dashboard sidebar nav
- Breadcrumb
- Pagination
- Tab bar (mobil bottom nav — opcionális)

### 5.7 Modálok
- Report Abuse modal
- Claim Listing modal
- Képek lightbox

### 5.8 Értesítések
- Toast (jobb felső, success/error/warning)
- Inline alert sáv (membership lejárat, hitelesítési CTA)
- Empty state kártya

---

## 6. Oldal-specifikus design megjegyzések

### 6.1 Főoldal
- A hero keresőform a legfontosabb konverziós elem — nagy, jól látható
- Kategória ikonok: egységes ikon stílus szükséges (DATABASE EXPORT REQUIRED a kategóriákhoz)
- Mobilon a keresőform és kategória rács elsőként látható

### 6.2 Hirdetés lista (Archive)
- Desktop: 2-3 oszlopos grid + bal sidebar szűrő
- Tablet: 2 oszlop, collapsed szűrő
- Mobil: 1 oszlop, floating szűrő gomb

### 6.3 Hirdetés részletek (Single Listing)
- Galéria meghatározó elem — magas minőségű képek tárolásra tervezve
- Sticky sidebar desktop-on (scroll közben rögzített)
- Mobil: Sticky bottom contact bar (Tel / WhatsApp / Chat / Email gombok)

### 6.4 Regisztráció — 3 típusos form
- A fiók típus választó a legfontosabb UX elem ezen az oldalon
- A 3 típusnak vizuálisan EGYÉRTELMŰEN kell elkülönülnie
- NIE/NIF mezőnek legyen magyarázó szöveg (mi ez, miért kell, hol találod)

### 6.5 Dashboard
- Sidebar nav + tartalom terület layout (desktop)
- Mobilon: Tab bar VAGY hamburger menü a nav-hoz

### 6.6 Hirdetés feladás — Multi-step
- Progress indicator (lépések száma, jelenlegi lépés)
- Minden lépés egy képernyő (nem scroll)
- "Előző" / "Következő" navigáció
- Mentett haladás (ne veszítse el a user a beírt adatokat)

---

## 7. Mobil UX irányelvek

### 7.1 Érintési célpontok
- Minimum gomb méret: 44×44px (Apple HIG standard)
- Távolság gombok között: minimum 8px

### 7.2 Navigáció
- Hamburger menü: egyszerű overlay (nem oldalsó fiók)
- Dashboard mobil nav: Sticky top bar VAGY bottom tab bar (max 4-5 ikon)

### 7.3 Formok mobilon
- Nagy input mezők (height: min 44-48px)
- Numerikus keyboard a tel/ár mezőknél (`type="tel"`, `inputmode="numeric"`)
- NIE/NIF mező: Captial letters auto (uppercase CSS + pattern attribute)

### 7.4 Hirdetés lista mobilon
- Egy oszlop
- Szűrő: Floating gomb → Full-screen overlay

### 7.5 Chat mobilon
- Teljes képernyős chat nézet
- Billentyűzet megjelenésekor az üzenet input látható marad (fixed pozíció)

---

## 8. Ikon rendszer

**Szükséges ikonok** (legalább):
- Kategória ikonok (DATABASE EXPORT REQUIRED — kliens által meghatározandó)
- Navigációs ikonok: Keresés, Felhasználó, Szív, Bell, Chat, Menu
- Action ikonok: Szerkesztés, Törlés, Megújítás, Kiemelés, Megosztás, Nyomtatás, Visszaélés
- Státusz ikonok: Check, Warning, Error, Clock, Star
- Közösségi média: Facebook, Instagram, Twitter/X, LinkedIn, WhatsApp, Telegram
- Meta ikonok: Helyszín pin, Telefon, Email, Web, Naptár, Szem (nézetek)

**Ajánlott forrás**: Font Awesome 6 Free VAGY SVG ikon sprite

---

## 9. Szükséges design deliverables (Mit készítsen a designer)

### Kötelező
1. **Style guide**: Szín paletta, tipográfia, spacing system, komponens dokumentáció
2. **Desktop designs** (min. 1440px széles):
   - Főoldal
   - Hirdetés lista (grid + lista nézet)
   - Hirdetés részletek (kép-gazdag hirdetés)
   - Regisztráció (3-típusos)
   - Dashboard (mind 3 fiók típus)
   - Hirdetés feladás (minden lépés)
3. **Mobil designs** (375px széles — iPhone standard):
   - Főoldal
   - Hirdetés lista
   - Hirdetés részletek
   - Regisztráció
   - Dashboard
   - Hirdetés feladás

### Opcionális (de ajánlott)
4. **Tablet designs** (768px)
5. **Component library** (Figma/Sketch — fejlesztőknek)
6. **Interakciós prototípus** (key user flows)

---

## 10. Reference oldalak (Inspiráció — kliens által meghatározandó)

**KLIENS KÉRDÉS**: Van-e referencia oldal amit tetszik a kliensnek?

Tipikus hasonló platformok (inspiráció, nem másolás):
- Idealista.com (spanyol ingatlan)
- Wallapop.com (spanyol apróhirdetés)
- Milanuncios.com (spanyol hirdetések)
- Fotocasa.es (spanyol ingatlan)

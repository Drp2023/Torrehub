# DASHBOARD-SPECIFICATIONS.md
## Torrehub — Dashboard layout specifikációk

**Verzió**: 2.0 (DB export alapján frissítve — Membership/Store DISABLED)
**Dátum**: 2026-08-29
**Állapot**: CURRENT CONFIRMED adatokkal frissítve

---

## Közös dashboard struktúra (minden fiók típus)

### Navigációs sáv (Sidebar / Top nav)

**Desktop** (bal oldalsó nav):
```
[Profilkép + Név + Fiók típus badge]
─────────────────────────────────────
🏠 Dashboard
⭐ Kedvenceim
💬 Üzeneteim
🔔 Keresési értesítők
─────────────────────────────────────
[CSAK SELLER + BUSINESS SELLER:]
📋 Hirdetéseim
➕ Hirdetés feladása
💳 Fizetéseim
🛡️ Eladói hitelesítés
[CSAK BUSINESS SELLER — csak ha Store aktív:]
🏪 Üzletem ⚠️ (Store jelenleg DISABLED)
─────────────────────────────────────
⚙️ Profil szerkesztése
🔧 Beállítások
🚪 Kijelentkezés
```

**Mobil** (hamburger menü vagy bottom navigation bar)

---

## DASHBOARD-01: Member Dashboard

**URL**: `/fiokom/`  
**Hozzáférés**: Bejelentkezett Member

### Oldal fejléc
```
┌─────────────────────────────────────────────────────┐
│  Üdvözöljük, [Név]!                    [MEMBER badge] │
│  Fiók létrehozva: [dátum]                            │
└─────────────────────────────────────────────────────┘
```

### Statisztika kártyák (3 kártya, responsive grid)
```
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  ⭐          │  │  💬          │  │  🔔          │
│  Kedvencek   │  │  Üzenetek    │  │  Értesítők   │
│              │  │              │  │              │
│     [szám]   │  │  [olvasatlan]│  │    [aktív]   │
└──────────────┘  └──────────────┘  └──────────────┘
```

### Gyorslinkek szekció
```
┌─────────────────────────────────────────────────────┐
│ Gyors műveletek                                      │
│ [Kedvenceim böngészése]  [Üzenetek megnyitása]       │
│ [Hirdetések keresése]    [Profil frissítése]         │
└─────────────────────────────────────────────────────┘
```

### Legutóbbi kedvencek (3-4 kártya preview)
```
┌─────────────────────────────────────────────────────┐
│ Legutóbbi kedvenceim                    [Összes →]   │
│                                                      │
│ [Kártya 1]  [Kártya 2]  [Kártya 3]                  │
│                                                      │
│ Üres állapot: "Még nincsenek kedvenc hirdetéseid"    │
└─────────────────────────────────────────────────────┘
```

---

## DASHBOARD-02: Private Seller Dashboard

**URL**: `/fiokom/`  
**Hozzáférés**: Bejelentkezett Private Seller

### Oldal fejléc
```
┌─────────────────────────────────────────────────────────┐
│  Üdvözöljük, [Név]!              [PRIVATE SELLER badge]  │
│  [Hitelesítési státusz sáv — ha nem verified:]          │
│  ⚠️ "Töltsd fel NIE dokumentumodat a badge megszerzéséhez" │
│     [Dokumentum feltöltése →]                           │
└─────────────────────────────────────────────────────────┘
```

### Statisztika kártyák (3-4 kártya)
```
┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐
│ 📋         │ │ 👁️          │ │ ⭐         │ │ 🆓         │
│ Hirdetések │ │ Megtekint. │ │ Kedvencek  │ │ Ingyenes   │
│            │ │            │ │            │ │ keret      │
│  [aktív/   │ │  [összes   │ │  [szám]    │ │ [X / 5]   │
│  összes]   │ │  nézet]    │ │            │ │ / 30 nap  │
└────────────┘ └────────────┘ └────────────┘ └────────────┘
```

**⚠️ Membership státusz sáv: DISABLED** (`rtcl_membership_settings.enable: ""`)  
Jelenleg nincs membership rendszer — az ingyenes keret (5 hirdetés / 30 nap) jelenik meg.

### Hirdetések quick panel (utolsó 3-5)
```
┌─────────────────────────────────────────────────────────┐
│ Hirdetéseim                           [Összes hirdetés →]│
│                                                          │
│ [Cím]          [Kategória]  [Státusz badge]  [Műveletek] │
│ [Cím]          [Kategória]  [Státusz badge]  [Műveletek] │
│ [Cím]          [Kategória]  [Státusz badge]  [Műveletek] │
│                                                          │
│ [+ Új hirdetés feladása]                                 │
│ Üres: "Még nincs hirdetésed. [Adj fel egyet!]"           │
└─────────────────────────────────────────────────────────┘
```

### Hitelesítési CTA (ha nem verified)
```
┌─────────────────────────────────────────────────────────┐
│ 🛡️ Szerezd meg az "Ellenőrzött Eladó" badge-t!          │
│ Töltsd fel NIE dokumentumodat az adminisztrátorok által │
│ ellenőrzéshez.                                           │
│                                [Dokumentumok feltöltése]│
└─────────────────────────────────────────────────────────┘
```

---

## DASHBOARD-03: Business Seller Dashboard

**URL**: `/fiokom/`  
**Hozzáférés**: Bejelentkezett Business Seller

### Oldal fejléc
```
┌─────────────────────────────────────────────────────────┐
│  [Store logó]  [Cégnév]          [BUSINESS SELLER badge] │
│                [Kapcsolattartó neve]                      │
│  [Hitelesítési státusz sáv — ha nem verified:]          │
│  ⚠️ "Töltsd fel NIF dokumentumodat"                     │
└─────────────────────────────────────────────────────────┘
```

### Statisztika kártyák (3-4 kártya)
```
┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐
│ 📋       │ │ 👁️        │ │ ⭐       │ │ 🆓       │
│ Hirdetés │ │ Nézetek  │ │ Kedvenc  │ │ Ingyenes │
│          │ │          │ │          │ │ keret    │
│ [X/Y]    │ │ [összeg] │ │ [szám]   │ │ [X / 5] │
└──────────┘ └──────────┘ └──────────┘ └──────────┘
```

**⚠️ Store statisztika kártya**: DISABLED — Store modul kikapcsolva (`enable_store: ""`)

---

## DASHBOARD-04: Hirdetéseim oldal

**URL**: `/fiokom/listings/`  
**Hozzáférés**: Private Seller + Business Seller

### Oldal fejléc + CTA
```
┌─────────────────────────────────────────────────────────┐
│ Hirdetéseim                    [+ Új hirdetés feladása] │
│ [Szűrők: Összes | Aktív | Függőben | Lejárt | Temp]    │
└─────────────────────────────────────────────────────────┘
```

### Hirdetés lista táblázat

```
┌────────┬────────────────┬──────────────┬───────────┬─────────────┬──────────┬──────────────────────┐
│ Kép    │ Cím            │ Kategória    │ Státusz   │ Lejárat     │ Nézetek  │ Műveletek            │
├────────┼────────────────┼──────────────┼───────────┼─────────────┼──────────┼──────────────────────┤
│ [thumb]│ [post_title]   │ [kategória]  │ [badge]   │ [dátum]     │ [szám]   │ Szerk│Törlés│Megújít │
├────────┼────────────────┼──────────────┼───────────┼─────────────┼──────────┼──────────────────────┤
│ ...    │ ...            │ ...          │ ...       │ ...         │ ...      │ ...                  │
└────────┴────────────────┴──────────────┴───────────┴─────────────┴──────────┴──────────────────────┘
```

**Státusz badge-ek**:
- 🟢 `publish` / rtcl-reviewed — "Aktív"
- 🟡 `rtcl-pending` — "Jóváhagyásra vár"
- 🔴 `rtcl-expired` — "Lejárt"
- ⚪ `rtcl-temp` — "Piszkozat"

**Műveletek per hirdetés**:
- **Szerkesztés** — Edit listing form
- **Törlés** — Megerősítő modállal; `rtcl_delete_listing` AJAX
- **Megújítás** — Ha lejárt vagy közel jár; `rtcl_ajax_renew_listing` AJAX
- **Kiemelés vásárlása** — Ha nem kiemelt; Featured/Urgent promóció

**Üres állapot**:
```
Még nincsenek hirdetéseid.
[Adj fel most egy hirdetést!]
```

---

## DASHBOARD-05: Kedvenceim oldal

**URL**: `/fiokom/favourites/`  
**Hozzáférés**: Minden bejelentkezett felhasználó

### Layout
```
┌─────────────────────────────────────────────────────────┐
│ Kedvenceim                        Rendezés: [Legújabb ▼] │
└─────────────────────────────────────────────────────────┘

[Listing kártya grid — azonos a keresési eredményekkel]
[Minden kártyán: ❤️ Eltávolítás gomb]

Üres állapot:
"Még nincsenek kedvenc hirdetéseid."
"Böngészd a hirdetéseket és mentsd el a szívednek."
[Hirdetések böngészése →]
```

---

## DASHBOARD-06: Üzeneteim (Chat) oldal

**URL**: `/fiokom/chat/`  
**Hozzáférés**: Minden bejelentkezett felhasználó  
**Backend**: RTCL Pro — `wp_rtcl_conversations` + `wp_rtcl_conversation_messages`

### Desktop layout (két oszlopos)
```
┌─────────────────────┬───────────────────────────────────┐
│ Beszélgetések        │ [Chat neve] — [Hirdetés neve]     │
│ ─────────────────── │ ─────────────────────────────────  │
│ [Avatar] Név         │                                   │
│ [Utolsó üzenet...]  │  [Üzenet buborék — Más]           │
│ [Időbélyeg]  [●3]   │  [Üzenet buborék — Más]           │
│ ─────────────────── │                                   │
│ [Avatar] Név         │  [Üzenet buborék — Én]            │
│ [Utolsó üzenet...]  │                                   │
│ [Időbélyeg]         │ ─────────────────────────────────  │
│ ─────────────────── │ [Üzenet írása...]      [Küldés]   │
└─────────────────────┴───────────────────────────────────┘
```

**[●3]** = Olvasatlan üzenetek száma badge

### Mobil layout
- Lista nézet → Tap to open → Full screen chat

### Üres állapot
```
Még nincsenek üzeneteid.
Ha kapcsolatba szeretnél lépni egy eladóval,
nyisd meg a hirdetést és kattints a "Chat" gombra.
```

---

## DASHBOARD-07: Keresési értesítők oldal

**URL**: `/fiokom/search-alert/`  
**Plugin**: rtcl-search-alert

### Layout
```
┌─────────────────────────────────────────────────────────┐
│ Keresési értesítőim                                      │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 🔍 Keresés: "Barcelona lakás"                            │
│ Kategória: Ingatlan | Min. ár: 800€ | Max. ár: 1500€   │
│ Frekvencia: [Napi ▼]              [🗑️ Törlés]           │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│ 🔍 Keresés: "Honda motorkerékpár"                        │
│ Kategória: Járművek | Helyszín: Madrid                  │
│ Frekvencia: [Heti ▼]              [🗑️ Törlés]           │
└─────────────────────────────────────────────────────────┘

Üres állapot:
"Még nincsenek mentett kereséseid."
"Hajts végre egy keresést és mentsd el értesítőként!"
[Keresés indítása →]
```

---

## DASHBOARD-08: Fizetési előzmények

**URL**: `/fiokom/payments/`  
**Hozzáférés**: Private Seller + Business Seller

### Layout
```
┌────────┬────────────────────┬────────┬──────────┬──────────┐
│ Dátum  │ Leírás             │ Összeg │ Módszer  │ Státusz  │
├────────┼────────────────────┼────────┼──────────┼──────────┤
│ [dát.] │ [Csomag neve]      │ [ár]   │ [Stripe] │ [badge]  │
│ [dát.] │ [Featured hirdet.] │ [ár]   │ [Stripe] │ [badge]  │
└────────┴────────────────────┴────────┴──────────┴──────────┘
```

**Státusz badge-ek**: Kész / Függőben / Meghiúsult / Visszatérítve / Lemondva

---

## DASHBOARD-09: Eladói hitelesítés oldal

**URL**: `/fiokom/my-documents/`  
**Plugin**: rtcl-seller-verification

### Ha nem hitelesített
```
┌─────────────────────────────────────────────────────────┐
│ Eladói hitelesítés                                       │
│                                                          │
│ Státusz: ⚠️ Nincs hitelesítve                           │
│                                                          │
│ A hitelesítéshez töltsd fel az alábbi dokumentumokat:   │
│                                                          │
│ 1. Fotó azonosító (NIE / NIF) [Fájl kiválasztása...]    │
│    Elfogadott: PNG, JPG, JPEG                           │
│                                                          │
│ 2. Egyéb dokumentum (opcionális) [Fájl kiválasztása...] │
│    Elfogadott: PDF, képek                               │
│                                                          │
│                          [Dokumentumok feltöltése]       │
└─────────────────────────────────────────────────────────┘
```

### Ha feltöltve, de nem jóváhagyva
```
│ Státusz: 🕐 Ellenőrzés alatt                            │
│ Dokumentumaidat bekaptuk — az adminisztrátor            │
│ hamarosan ellenőrzi.                                    │
```

### Ha hitelesített
```
│ Státusz: ✅ Ellenőrzött Eladó                           │
│ Gratulálunk! Megkaptad az "Ellenőrzött Eladó" badge-t. │
│ [Badge preview]                                         │
```

---

## DASHBOARD-10: Store szerkesztés (Business Seller) — ⛔ DISABLED

**Státusz**: Store modul KIKAPCSOLVA (`rtcl_membership_settings.enable_store: ""`)  
**Teendő**: Client döntés szükséges (lásd CLIENT-CONFIRMATION.md B1)

*Ez a dashboard szekció csak akkor releváns, ha a kliens aktiválni akarja a Store modult.*

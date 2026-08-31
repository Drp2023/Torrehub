# LISTING-FIELDS.md
## Torrehub — Hirdetési mezők, kategóriák, helyszínek

**Verzió**: 3.0 — Form Builder (09-rtcl-forms.json) alapján teljesen feltérképezve
**Dátum**: 2026-08-29

---

## Státusz jelölések

- **CURRENT CONFIRMED** — jelenlegi rendszerből bizonyított
- **PLUGIN CAPABILITY** — plugin tudja, de Torrehubon nem aktív
- **UNKNOWN** — nem bizonyítható

---

## 1. TELJES KATEGÓRIAFA — CURRENT CONFIRMED

**Összesen: 152 kategória** (10 root, max. 4 szintmély)  
*Forrás: 01-categories.json, wp_terms + wp_term_taxonomy, taxonomy = rtcl_category*

### 1.1 Auto / Moto / Boats (slug: auto-moto-boats, id: 38) — 4 hirdetés
```
├── Vehicles for Sale (vehicles-for-sale, id: 39)
│   ├── Cars (cars, id: 41)
│   ├── Motorcycles (motorcycles, id: 42)
│   └── Boats (boats, id: 43)
└── Vehicles for Rent (vehicles-for-rent, id: 40)
    ├── Cars (cars-vehicle-rentals, id: 44)
    ├── Motorbikes/Scooters/ATV (motorbikes-scooters-atv, id: 45)
    └── Boats/Jetski (boats-jetski, id: 46)
```

### 1.2 Events (slug: events, id: 183) — 1 hirdetés
```
├── Concerts (concerts, id: 187)
├── Cultural Events (cultural-events, id: 185)
├── Family & Kids (family-kids, id: 188)
├── Festivals & Fiestas (festivals-fiestas, id: 184)
├── Markets & Fairs (markets-fairs, id: 189)
├── Music & Nightlife (music-nightlife, id: 186)
└── Wellness & Retreats (wellness-retreats, id: 190)
```

### 1.3 Jobs (slug: jobs, id: 61) — 3 hirdetés
```
├── Administrative & Secretarial (administrative-secretarial, id: 65)
├── Agriculture (agriculture, id: 68)
├── Beauty & Personal Care (beauty-personal-care, id: 69)
├── Building & Trades (building-trades, id: 70)
├── Business Administration (business-administration, id: 76)
├── Construction & Architecture (construction-architecture, id: 67)
├── Drivers & Couriers (drivers-couriers, id: 66)
├── Education & Training (education-training, id: 74)
├── Finance & Accounting (finance-accounting, id: 63)
├── Healthcare & Medical (healthcare-medical, id: 75)
├── Hospitality & Restaurant (hospitality-restaurant, id: 72)
├── Marketing, PR & Media (marketing-pr-media, id: 71)
├── Retail & Cashiers (retail-cashiers, id: 73)
├── Sales (sales, id: 62)
├── Security & Protection (security-protection, id: 77)
└── Warehouse & Logistics (warehouse-logistics, id: 64)
```

### 1.4 Leisure & Sport (slug: leisure-sport, id: 142) — 1 hirdetés
```
├── Sports (sports, id: 143)
│   ├── Airsoft (airsoft, id: 151)
│   ├── Fishing (fishing, id: 148)
│   ├── Football (football, id: 147)
│   ├── Golf (golf, id: 146)
│   ├── Gym/Fitness (gym-fitness, id: 154)
│   ├── Martial Arts (martial-arts, id: 152)
│   ├── Parasailing (parasailing, id: 150)
│   ├── Swimming (swimming, id: 153)
│   └── Water Sports (water-sports, id: 149)
├── Activities (activities, id: 144)
│   ├── Dance (dance, id: 155)
│   └── Hiking (hiking, id: 156)
└── Recreation & Places (recreation-places, id: 145)
    ├── International Food Shops (international-food-shops, id: 160)
    ├── Local Markets (local-markets, id: 158)
    ├── Shopping Centres (shopping-centres, id: 159)
    └── Water Parks (water-parks, id: 157)
```

### 1.5 Marketplace (slug: marketplace, id: 134) — 2 hirdetés
```
├── Electronics & Appliances (electronics-appliances, id: 135)
├── Fashion & Beauty (fashion-beauty, id: 139)
├── Home & Garden (home-garden, id: 137)
├── Mother & Child (mother-child, id: 138)
├── Pets (pets, id: 141)
└── Tools (tools, id: 136)
```

### 1.6 Properties (slug: properties, id: 47) — 1 hirdetés
```
├── Residential for Sale (residential-for-sale, id: 48)
│   ├── Apartments/Studios (apartments-studios-residential-for-sale, id: 60)
│   └── Houses/Villas (houses-villas-residential-for-sale, id: 59)
├── Residential for Rent (residential-for-rent, id: 49)
│   ├── Apartments/Studios (apartments-studios, id: 57)
│   └── Houses/Villas (houses-villas, id: 58)
├── Commercial Properties (commercial-properties, id: 51)
│   ├── Commercial Spaces (commercial-spaces, id: 56)
│   └── Offices (offices, id: 55)
├── Land (land, id: 50)
└── Parking (parking, id: 52)
    ├── Garages (garages, id: 54)
    └── Parking Lots (parking-lots, id: 53)
```

### 1.7 Public Information (slug: public-information, id: 161) — 1 hirdetés
```
├── Government & Administration (government-administration, id: 162)
├── Safety & Emergency (safety-emergency, id: 163)
│   ├── Fire Brigade (fire-brigade, id: 175)
│   └── Police (police, id: 174)
├── Environment & Public Spaces (environment-public-spaces, id: 164)
├── Transport (transport, id: 165)
│   ├── Airport Information (airport-information, id: 173)
│   ├── Bus & Train (bus-train, id: 171)
│   └── Taxis (taxis, id: 172)
├── Healthcare (healthcare, id: 166)
│   ├── Hospitals (hospitals, id: 169)
│   └── Pharmacies (pharmacies, id: 170)
└── Education (education, id: 167)
    └── Schools (schools, id: 168)
```

### 1.8 Restaurants & Nightlife (slug: restaurants-nightlife, id: 116) — 1 hirdetés
```
├── Restaurants (restaurants, id: 117)
│   ├── Asian (asian, id: 125)
│   ├── Beach Bars (Chiringuitos) (beach-bars-chiringuitos, id: 122)
│   ├── Fast Food/Takeaway (fast-food-takeaway, id: 126)
│   ├── Greek (greek, id: 124)
│   ├── International (international, id: 127)
│   ├── Italian (italian, id: 123)
│   ├── Seafood (seafood, id: 121)
│   └── Spanish/Traditional (spanish-traditional, id: 120)
├── Bars & Cafés (bars-cafes, id: 118)
│   ├── Beach/Sea View Cafés (beach-sea-view-cafes, id: 130)
│   ├── Cocktail Bars (cocktail-bars, id: 128)
│   └── Irish Pubs (irish-pubs, id: 129)
└── Clubs & Entertainment (clubs-entertainment, id: 119)
    ├── Casinos (casinos, id: 133)
    ├── Gentleman's Clubs (gentlemans-clubs, id: 132)
    └── Nightclubs/Disco (nightclubs-disco, id: 131)
```

### 1.9 Services (slug: services, id: 78) — 1 hirdetés
```
├── Professional Services (professional-services, id: 79)
│   ├── Accountant (accountant, id: 85)
│   ├── Insurance (insurance, id: 91)
│   ├── Lawyer (lawyer, id: 88)
│   ├── Mortgage Broker (mortgage-broker, id: 89)
│   ├── Notary (notary, id: 86)
│   ├── Real Estate Agency (real-estate-agency, id: 90)
│   ├── Tax Advisor (tax-advisor, id: 87)
│   └── Translator (translator, id: 92)
├── Home & Maintenance (home-maintenance, id: 80)
│   ├── Air Conditioning (air-conditioning, id: 98)
│   ├── Building Services (building-services, id: 97)
│   ├── Electrical (electrical, id: 95)
│   ├── Garden Services (garden-services, id: 99)
│   ├── Handyman (handyman, id: 93)
│   ├── Painting (painting, id: 96)
│   └── Plumbing (plumbing, id: 94)
├── Health & Care (health-care, id: 81)
│   ├── Care Services (care-services, id: 101)
│   ├── Medical/Dental (medical-dental, id: 100)
│   └── Veterinary (veterinary, id: 102)
├── Repair Services (repair-services, id: 82)
│   ├── Appliances (appliances, id: 103)
│   ├── Electronics (electronics, id: 104)
│   └── Telephone Repair (telephone-repair, id: 105)
├── Beauty Services (beauty-services, id: 83)
│   ├── Hairdressing (hairdressing, id: 108)
│   ├── Makeup (makeup, id: 107)
│   ├── Massage (massage, id: 110)
│   ├── Nails (nails, id: 106)
│   └── Tattoo (tattoo, id: 109)
└── Automotive Services (automotive-services, id: 84)
    ├── Body Repair & Paint (body-repair-paint, id: 112)
    ├── Car Wash (car-wash, id: 115)
    ├── Car Wrapping (car-wrapping, id: 114)
    ├── Mechanic (mechanic, id: 111)
    └── Tyers (tyers, id: 113)
```

### 1.10 Tourist Attractions (slug: tourist-attractions, id: 176) — 4 hirdetés
```
├── Beaches (beaches, id: 177)
├── Historical Sites (historical-sites, id: 178)
├── Nature & Parks (nature-parks, id: 179)
├── Museums & Cultural Spots (museums-cultural-spots, id: 180)
├── Day Trips (day-trips, id: 181)
└── Nearby Destinations (nearby-destinations, id: 182)
```

---

## 2. HELYSZÍNEK (Locations) — CURRENT CONFIRMED

**Összesen: 35 helyszín** — mind egy szinten (flat), nincs hierarchia  
**Helyszín típus**: `local` (nem ország/régió alapú)  
**Szintek definiálva**: State → City → Town (de a tényleges adatok mind root szintűek)  
**Térkép közép**: Torrevieja (lat: 37.994, lng: -0.679) — Google Maps  
*Forrás: 02-locations.json, taxonomy = rtcl_location*

| Helyszín neve | Slug | Hirdetés |
|---------------|------|---------|
| Alcoi | alcoi | 3 |
| Algorfa | algorfa | 1 |
| Alicante | alicante | 2 |
| Almoradí | almoradi | 3 |
| Altea | altea | 3 |
| Benidorm | benidorm | 0 |
| Benijófar | benijofar | 1 |
| Benissa | benissa | 1 |
| Cabo Roig | cabo-roig | 0 |
| Calp | calp | 1 |
| Catral | catral | 0 |
| Dénia | denia | 0 |
| Dolores | dolores | 0 |
| El Campello | el-campello | 0 |
| Elche (Elx) | elcheelx | 0 |
| Elda | elda | 0 |
| Finestrat | finestrat | 0 |
| Guardamar del Segura | guardamar-del-segura | 0 |
| La Vila Joiosa | la-vila-joiosa | 0 |
| La Zenia | la-zenia | 1 |
| Mil Palmeras | mil-palmeras | 0 |
| Moraira | moraira | 0 |
| Orihuela | orihuela | 1 |
| Orihuela Costa | orihuela-costa | 0 |
| Pilar de la Horadada | pilar-de-la-horadada | 0 |
| Punta Prima | punta-prima | 0 |
| Quesada | quesada | 0 |
| Rojales | rojales | 0 |
| Santa Pola | santa-pola | 0 |
| Teulada | teulada | 0 |
| Torrevieja | torrevieja | 1 |
| Villamartin | villamartin | 0 |
| Villena | villena | 0 |
| Xàbia | xabia | 0 |

**Megfigyelés**: A helyszínek csak Costa Blanca városok. Nincs ország/tartomány szint. Benidorm — a régió fő turisztikai helyszíne — 0 hirdetéssel (tesztrendszer).

---

## 3. CUSTOM FIELD CSOPORTOK — CURRENT CONFIRMED

**Összesen: 3 field group**  
*Forrás: 04-rtcl-field-groups.json, rtcl_cfg post type*

| ID | Csoport neve | Hozzárendelés | Megjegyzés |
|----|-------------|---------------|------------|
| 220 | Amenities | `all` — minden kategória | Minden hirdetésen megjelenik |
| 225 | Features | `all` — minden kategória | Minden hirdetésen megjelenik |
| 396 | Event | `categories` — kategória-specifikus | Valószínűleg Events kategóriához |

---

## 4. STANDARD CUSTOM FIELDS (rtcl_cf) — CURRENT CONFIRMED

**Összesen: 5 standard rtcl_cf mező + 1 speciális**  
*Forrás: 03-rtcl-custom-fields.json, rtcl_cf post type*

### 4.1 "Amenities" csoport (id: 220) — MINDEN kategória

| Mező ID | Label | Típus | Slug | Required | Searchable | Opciók |
|---------|-------|-------|------|----------|------------|--------|
| 224 | Listing Amenites | checkbox | listing-amenites | ✅ igen | ✅ igen | Accepts Credit Cards, Outdoor Seating, Reservations, Cupon, Bike Parking, Smoking Allowed, Wifi Facility, Swimming Pool |

**Meta kulcs tárolás**: `_field_224` wp_postmeta-ban

### 4.2 "Features" csoport (id: 225) — MINDEN kategória

| Mező ID | Label | Típus | Slug | Required | Searchable | Opciók |
|---------|-------|-------|------|----------|------------|--------|
| 226 | Filter by Features | checkbox | filter-by-features | ❌ nem | ✅ igen | Full Bar, Wheel Chair, Reservations, Accessible, Seating, Cafe, Serve, Hotel, Valet Parking, Waitstaff |

**Meta kulcs tárolás**: `_field_226` wp_postmeta-ban

### 4.3 "Event" csoport (id: 396) — Kategória-specifikus

| Mező ID | Label | Típus | Sorrend | Required | Searchable | Megjegyzés |
|---------|-------|-------|---------|----------|------------|------------|
| 397 | Event Place | text | 0 | ❌ nem | ❌ nem | Ikon: map-marker-alt |
| 398 | Ticket Price | text | 1 | ❌ nem | ❌ nem | Ikon: money |
| 399 | Event Date | date (date_time) | 2 | ❌ nem | ❌ nem | Ikon: calendar, formátum: Y-m-d, g:i A |

### 4.4 Speciális mező — Listing Logo

| Mező ID | Label | Típus | Slug/Meta | Required | Megjegyzés |
|---------|-------|-------|-----------|----------|------------|
| 3591 | Listing Logo | text | listing_logo_img | ❌ nem | WP Attachment ID-t tárol |

---

## 5. FORM BUILDER MEZŐK — CURRENT CONFIRMED

**Verzió frissítve**: 3.0 — 09-rtcl-forms.json alapján teljesen feltérképezve  
**Táblázat**: `wp_d5c58b26b6_rtcl_forms`

### 5.0 Összefoglaló

| Adat | Érték |
|------|-------|
| Összes Form Builder form | **10 db** |
| Összes mező (total) | **257 db** |
| Default form | Form 2 "Post a Job" (default: 1) |
| Minden root kategóriának | saját FB form van |

**PRESET mezők** (minden formban megjelennek, RTCL standard kezelés):
title, description, images, pricing, phone, whatsapp, email, website, map, location, listing_type, category, terms_and_condition, social_profiles, business_hours, video_urls

**Kategória-specifikus mezők** alább, formánként részletezve.

---

### 5.1 Form 2 — "Post a Job" (22 mező) — DEFAULT FORM
**Kategória**: Jobs  
**Sections**: Jobs Basic Info → Job Information → Company Details

| # | Field Key | Label | Típus | Kötelező | Opciók |
|---|-----------|-------|-------|----------|--------|
| 1 | 7be2f... | Job Category | category | opt | PRESET |
| 2 | mnu8n42n | Apply Link | url | opt | — |
| 3 | dbdcc2... | Job Title | title | opt | PRESET |
| 4 | mbewj4uk | Company Logo | file | opt | Feltöltés |
| 5 | mp0eynaw | Images | images | opt | PRESET |
| 6 | b3130c... | Salary | pricing | opt | PRESET, conditional |
| 7 | mob53sts | Salary Monthly | number | opt | HUF/EUR összeg |
| 8 | 9ce89... | Job Description | description | opt | PRESET |
| 9 | mnu87vf0 | Job Type | select | opt | Full-time, Part-time, Contract, Remote, Hybrid |
| 10 | mnu8b2s8 | Experience Level | select | opt | Entry, Junior, Mid, Senior, No experience needed |
| 11 | mnu8ghuy | Company Type | radio | opt | Direct Employer, Recruitment Agency |
| 12 | mojpj595 | Location/Address | text | opt | — |
| + | PRESET | Phone, WhatsApp, Email, Location, Map, Social, Terms | — | — | — |

---

### 5.2 Form 3 — "Service" (26 mező)
**Kategória**: Services  
**Sections**: Basic Details → Branding → Service Info → Contact → Location → Operational

| # | Field Key | Label | Típus | Kötelező | Opciók |
|---|-----------|-------|-------|----------|--------|
| 1 | mnwpg0tp | Tagline | text | opt | Rövid szlogen |
| 2 | mnwpiplg | Logo | file | opt | Feltöltés |
| 3 | mnwpjlnz | Cover Image | file | opt | Feltöltés |
| 4 | mnwpp2ph | City / Area | text | opt | — |
| 5 | moa3cxr4 | Mobile Service | radio | opt | Yes, No-comes to you |
| 6 | moa3exha | Emergency Service | radio | opt | Yes, No, 24/7 available |
| 7 | moa3gux0 | Languages Spoken | select | opt | English, Spanish, Swedish, German, Other |
| 8 | mob57bk6 | Service Category | select | opt | Accountant, Notary, Tax advisor, Lawyer, Mortgage broker, Real estate agency, Insurance, Translator, Handyman/Plumber/Electrician/Painter, Builder, AC service, Garden service, Medical/Dentist/Care, Vet, Appliance repair, Electronics repair, Nail, Makeup, Hairdresser, Tattoo, Massage, Mechanic, Body repair/Paint, Tyre, Car wrapping, Carwash (26 opció) |
| 9 | mob59po0 | Price Model | select | opt | Hourly Rate, Fixed Fee, Free Consultation |
| 10 | mnwpsvp9 | Amenities | repeater | opt | Szabad szöveg tételek |
| 11 | mojpi01p | Address | text | opt | — |
| + | PRESET | Title, Description, Images, Pricing, Phone, WhatsApp, Email, Website, Business Hours, Social, Location, Map, Terms | — | — | — |

---

### 5.3 Form 4 — "Public Information" (20 mező)
**Kategória**: Public Information  
**Sections**: Details → Gallery → Details → Location → Extra

| # | Field Key | Label | Típus | Kötelező | Opciók / Megjegyzés |
|---|-----------|-------|-------|----------|---------------------|
| 1 | moa40k06 | Type | select | opt | Hospital, Pharmacy, School, Police, Fire brigade, Taxi, Bus/Train station, Airport, Municipal office |
| 2 | mnwqhxd4 | Entry Fee (if any €) | text | opt | — |
| 3 | moa44a33 | Online Service | checkbox | opt | Yes — conditional |
| 4 | moa42v38 | 24/7 Available | checkbox | opt | Yes — conditional |
| 5 | mnwqox58 | Tips / Guidelines | text | opt | — |
| 6 | mnwqpoch | Best Time to Visit | text | opt | — |
| 7 | mojphc2r | Location Address | text | opt | — |
| + | PRESET | Title, Description, Images, Business Hours, Phone, WhatsApp, Email, Website, Location, Map, Terms | — | — | — |

---

### 5.4 Form 5 — "Auto/Moto/Boats" (49 mező) ⭐ LEGKOMPLEXEBB
**Kategória**: Auto/Moto/Boats  
**Sections**: Basic Info → Details → Car Specs → Motorcycle → Boat → Location → Contact

**KULCSMEZŐ: Item Type** (select: Car / Motorbike / Boat) → feltételes szekciók megjelenítése

#### 5.4.1 Közös mezők (minden jármű típushoz)
| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mo48xoy6 | Item Type | select | Car, Motorbike, Boat — **FŐ SZELEKTOR** |
| 2 | moh5gq8e | License Plate | text | — |
| 3 | moh5hay1 | VIN number | text | — |
| 4 | moh6pc3c | NIF Number | text | Conditional |
| + | PRESET | Title, Description, Images, Price, Location, Map, Phone, WhatsApp, Email, Website, Social, Terms | — | — |

#### 5.4.2 Car szekció (feltételes: Item Type = Car)
| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mp108r7c | Available for | select | Sale, Rent |
| 2 | mnwr5dyo | Seller Type | radio | Private, Dealership |
| 3 | mnwqi4ji | Condition | radio | New, Used |
| 4 | mojy1qy5 | Make | text | — |
| 5 | mnwqkc1e | Model | text | — |
| 6 | mo47ovql | Body Type | select | Sedan, SUV, Hatchback, Coupe, Convertible, Wagon, Minivan, Pickup |
| 7 | mo47kwc1 | Fuel Type | select | Petrol, Diesel, Hybrid, Electric, LPG — conditional |
| 8 | mo47nhca | Transmission | radio | Manual, Automatic |
| 9 | mp06rqbv | Engine cc | select | 1.0L, 1.2L, 1.5L, 1.6L, 2.0L, 2.5L, 3.0L+ |
| 10 | mo47wupe | Year | number | — |
| 11 | mnwqsqip | Mileage | text | conditional |
| 12 | mp08crjz | With License | radio | Yes, No — conditional |
| 13 | mojwwsqv | Location/Address | text | — |

#### 5.4.3 Motorbike szekció (feltételes: Item Type = Motorbike)
| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mq69dmj7 | Available for | select | Sale, Rent |
| 2 | mq697bbz | Seller Type | radio | Private, Dealership |
| 3 | mo47wupa | Condition | radio | New, Used |
| 4 | mojy2i6a | Make | text | — |
| 5 | mo47wupd | Model | text | — |
| 6 | mo47wupf | Engine cc | select | 50, 125, 250, 400, 600, 750, 1000, 1200+ |
| 7 | mo47wupe | Year | number | — |

#### 5.4.4 Boat szekció (feltételes: Item Type = Boat)
| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mq69dtjd | Available for | select | Rent |
| 2 | mq698271 | Seller Type | radio | Private, Business |
| 3 | mo482tl7 | Condition | radio | New, Used |
| 4 | mojy38cg | Make | text | — |
| 5 | mo482tlc | Type | select | Motorboat, Sailboat, RIB, Yacht, Fishing boat — conditional |
| 6 | mo482tlb | Length (m) | number | — |
| 7 | mo487nne | Year Built | number | — |
| 8 | mo49gez0 | Pickup Location | text | — |
| 9 | mo49h5b6 | Pickup Date | date | — |
| 10 | mo49ik54 | Dropoff Date | date | — |
| 11 | mo49jz2j | Fuel Included | radio | Yes, No — conditional |

---

### 5.5 Form 6 — "Submit an Event" (23 mező)
**Kategória**: Events  
**Sections**: Basic Info → Gallery → Event Info → Location → Organizer

| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mra9lueq | Event Type | select | Fiesta, Festival, Cultural, Music & Nightlife, Concert, Family & Children, Market fair, Wellness retreat |
| 2 | mnwreb2k | Start Date & Time | date | Dátum+idő picker |
| 3 | mnwrfe8s | End Date & Time | date | Dátum+idő picker |
| 4 | moa3pp30 | Indoor/Outdoor | radio | Indoor, Outdoor, Both |
| 5 | moa3qzmr | Age Restriction | radio | All ages, 18+, 21+ |
| 6 | moa3ntiz | Free/Paid | radio | Free, Paid |
| 7 | mnwrjs8e | Ticket Price | pricing | Conditional (ha Paid) |
| 8 | mnwrkw3m | Ticket Link | website | PRESET |
| 9 | mnwrhg15 | Venue Name | text | — |
| 10 | mnwrj527 | Online Link | text | — |
| 11 | mnwrls0g | Organizer Name | text | — |
| 12 | mojpfvao | Location Address | text | — |
| + | PRESET | Title, Description, Images, Phone, WhatsApp, Email, Social, Location, Map, Terms | — | — |

---

### 5.6 Form 7 — "Property for Rent or Sale" (33 mező)
**Kategória**: Properties  
**Sections**: Basic Info → Gallery → Pricing → Property Details → Location → Contact → Extra

| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mnwrivgp | Transaction Type | radio | Sale, Rent |
| 2 | mnwrcrc5 | Property Type | select | Apartment, House, Land, Office, Studio, Commercial |
| 3 | mra7bhb8 | Bedrooms | select | 1, 2, 3, 4, 5+ |
| 4 | mnwrllik | Bathrooms | number | — |
| 5 | mnwrlze3 | Area (sqm) | number | — |
| 6 | mp08zut8 | Energy Certificate | radio | A, B, C, D, E, F, G |
| 7 | mnwrmm8b | Furnishing | select | Yes, No, Partially |
| 8 | moa24c2b | Agency or Private | radio | Agency, Private Owner |
| 9 | moa2bbt0 | Parking | radio | Yes, No |
| 10 | moa2chxf | Elevator | radio | Yes, No |
| 11 | moa2e4qq | Floor | select | Ground Floor, 1st, 2nd, 3rd, 4th, 5th+, Top floor, Basement |
| 12 | mob508g2 | Year Built | number | — |
| 13 | moh517od | RAICV number | text | Conditional (tourist rental reg.) |
| 14 | moh532aa | VUT number | text | Conditional (holiday rental lic.) |
| 15 | moh7ksa3 | NIF number | text | Conditional |
| 16 | mnwrrkgf | Amenities | repeater | Szabad tételek listája |
| 17 | mnwrt3b3 | Floor Plan | file | PDF/kép feltöltés |
| 18 | mojmq289 | Location Address | text | — |
| + | PRESET | Title, Description, Images, Pricing, Phone, WhatsApp, Email, Website, Social, Location, Map, Business Hours, Terms | — | — |

---

### 5.7 Form 8 — "Restaurants/Nightlife" (24 mező)
**Kategória**: Restaurants & Nightlife  
**Sections**: Basic Details → Detailed Information → Location

| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mob5lxdo | Type | select | Restaurant, Bar, Terrace, Club |
| 2 | mob5ql9j | Cuisine/Theme | select | Spanish, Seafood, Chiringuitos, Italian, Greek, Asian, Fast food, International, Cocktail bar, Irish pub, Disco, Casino |
| 3 | mob5s0kv | Price Range | radio | €, ££, £££, ££££ |
| 4 | mob5t7lm | Outdoor Seating | checkbox | Yes |
| 5 | mob5u1oa | Live Music | checkbox | Yes |
| 6 | mob5u1xm | Delivery | checkbox | Yes |
| 7 | mob5u26i | Takeaway | checkbox | Yes |
| 8 | mob5uku2 | Sea View | checkbox | Yes |
| 9 | mol0s3lo | Address | text | — |
| + | PRESET | Title, Description, Images, Phone, WhatsApp, Email, Website, Business Hours, Video, Social, Location, Map, Terms | — | — |

---

### 5.8 Form 9 — "Marketplace" (19 mező)
**Kategória**: Marketplace  
**Sections**: Basic Info → Item Information → Location

| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mob66x0q | Category | select | Electronics, Tools, Home & garden, Mother & child, Fashion & beauty, Sports & leisure, Pets |
| 2 | mob68f09 | Condition | select | New, Like new, Used, For parts |
| 3 | mob6aghj | Brand | text | — |
| 4 | mojj0sto | Location Address | text | — |
| + | PRESET | Title, Description, Images, Pricing, Phone, WhatsApp, Email, Website, Social, Business Hours, Location, Map, Terms | — | — |

---

### 5.9 Form 10 — "Leisure/Sports" (21 mező)
**Kategória**: Leisure & Sport  
**Sections**: Basic Info → Details → Media/Location/Contact

| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mob6e4l7 | Activity Type | select | Golf, Football, Fishing, Water sports, Gym, Swimming pool, Dance, Hiking, Martial Arts, Shopping centre, Water park, Parasailing, Airsoft, Local market, International food shops (15 opció) |
| 2 | mob6fc3q | Price | select | €, ££, £££, Membership |
| 3 | mob6geh1 | Indoor/Outdoor | radio | Indoor, Outdoor, Both |
| 4 | mob6jr3b | Equipment Rental | checkbox | Yes |
| 5 | mob6lds3 | Age Suitability | select | Kids, Teens, Adults, Seniors, All ages |
| 6 | mojmrsjq | Location/Address | text | Conditional |
| + | PRESET | Title, Description, Pricing, Images, Phone, WhatsApp, Email, Website, Social, Business Hours, Location, Map, Terms | — | — |

---

### 5.10 Form 11 — "Tourist Attraction" (20 mező)
**Kategória**: Tourist Attractions  
**Sections**: Basic Info → Gallery → Details → Location

| # | Field Key | Label | Típus | Opciók |
|---|-----------|-------|-------|--------|
| 1 | mob6ymj0 | Attraction Type | select | Beach, Historical site, Nature & park, Museum, Cultural spot, Day trips, Nearby places |
| 2 | mob70eip | Entry Fee | radio | Free, Paid |
| 3 | mp09mwuh | Pricing | pricing | Conditional (ha Paid) |
| 4 | mob70yqq | Kid Friendly | checkbox | Yes |
| 5 | mp0a6um0 | Accessibility | checkbox | Wheelchair, Lift, Pet Friendly |
| 6 | mojmr2xz | Address/Location | text | — |
| + | PRESET | Title, Description, Business Hours, Phone, WhatsApp, Email, Website, Social, City/Location, Map, Terms | — | — |

---

### 5.11 Designer számára — Input komponens típusok

A Form Builder az alábbi input komponenseket használja (designernek szükséges):

| Komponens típus | Példa mezők |
|----------------|-------------|
| **text** | Make, Model, Address, Tagline, Organizer Name |
| **number** | Bathrooms, Area sqm, Year Built, Salary Monthly |
| **select (dropdown)** | Property Type, Job Type, Activity Type, Cuisine |
| **radio** | Transaction Type, Condition, Indoor/Outdoor, Seller Type |
| **checkbox** | Outdoor Seating, Sea View, Kid Friendly, Accessibility |
| **date / datetime** | Start Date & Time, End Date & Time, Pickup Date |
| **file upload** | Company Logo, Floor Plan, Cover Image |
| **url** | Apply Link, Ticket Link |
| **pricing** (RTCL spec.) | Salary, Ticket Price, Property Price, Leisure Price |
| **repeater** | Amenities (Services), Property Amenities |
| **business_hours** | Restaurants, Services, Properties nyitvatartás |
| **map** | Minden formban — Google Maps picker |
| **social_profiles** | Minden formban — FB/IG/TW/LinkedIn linkek |

---

## 6. ALAP HIRDETÉSI MEZŐK — CURRENT CONFIRMED

*Forrás: SaveListingMetaData.php kód + 06-rtcl-options.json*

### 6.1 Minden hirdetésen közös mezők

| Mező label | Meta kulcs | Típus | Megjegyzés |
|-----------|------------|-------|------------|
| Cím | post_title | text | Max limit: nincs beállítva |
| Leírás | post_content | wysiwyg/textarea | Max limit: nincs beállítva |
| Kategória | rtcl_category taxonomy | hierarchical | Kötelező |
| Helyszín | rtcl_location taxonomy | flat (35 town) | Opcionális |
| Galéria | WP attachments | images | Max 5 kép; png/jpg/jpeg/webp; max 2MB/kép |
| Ár | price | number | EUR (€) bal pozícióban |
| Max. ár | _rtcl_max_price | number | Range esetén |
| Listing Logo | listing_logo_img | attachment ID | Opcionális |
| Telefonszám | phone | tel | Megjeleníthető hirdetésen |
| WhatsApp | _rtcl_whatsapp_number | tel | — |
| Email | email | email | — |
| Website | website | url | — |
| Telegram | _rtcl_telegram | text | — |
| Cím szöveg | address | textarea | — |
| Irányítószám | zipcode | text | — |
| Helyszín (GPS) | latitude + longitude | coordinates | Google Maps picker |
| Geo cím | _rtcl_geo_address | hidden | Automatikus |
| Térkép elrejtés | hide_map | checkbox | — |
| Videó URL | _rtcl_video_urls[] | url | YouTube/Vimeo |
| Nyitvatartás | business_hours | custom | CURRENT CONFIRMED aktív |
| Közösségi média | _rtcl_social | array | CURRENT CONFIRMED aktív |
| Lejárat | expiry_date | date | 15 nap alapértelmezett |
| Listing Logo (FB) | listing_logo | array | FB rendszerben |

### 6.2 REJTETT mező — CURRENT CONFIRMED

| Mező | Beállítás | Megjegyzés |
|------|-----------|------------|
| Hirdetés típusa (ad_type) | `hide_form_fields: [ad_type]` | **ELREJTVE** — Sell/Rent/Wanted nem elérhető hirdetőknek |

---

## 7. RTCL LISTING TYPES (Template rendszer) — CURRENT CONFIRMED

*Forrás: rtcl_listing_types option + rtcl_tb_template_default_single_* options*

A rendszer 11 listing type-ot definiál (template-hez), amelyek **nem azonosak a kategóriákkal**:

| Listing Type slug | Neve | Template post ID |
|-------------------|------|-----------------|
| job | Job | — |
| automotoboats | Auto/Moto/Boats | rtcl_builder post #5409 (type 2) |
| events | Events | rtcl_builder post #6688 (type 11) |
| leisure_sport | Leisure & Sport | rtcl_builder post #6703 (type 10) |
| marketplace | Marketplace | rtcl_builder post #6712 (type 9) |
| properties | Properties | rtcl_builder post #6717 (type 8) |
| public_information | Public Information | rtcl_builder post #6725 (type 7) |
| tourist_attractions | Tourist Attractions | rtcl_builder post #6730 (type 6) |
| services | Services | rtcl_builder post #6735 (type 4) |
| restaurants_nightlife | Restaurants & Nightlife | rtcl_builder post #6746 (type 3) |
| business | Business | rtcl_builder post #6662 (type 5) |

**Ezek Elementor-ban épített per-típus single listing sablonok.** Az új témában PHP template-ekkel kell felváltani őket.

---

## 8. MEMBERSHIP ÉS ÁRAZÁS — CURRENT CONFIRMED

### CONFIRMED: Membership LETILTVA

```
rtcl_membership_settings:
  enable: "" → DISABLED
  enable_store: "" → DISABLED
```

**Nincsenek konfigurált fizetős csomagok** — 05-memberships.json üres eredményt adott.

### CONFIRMED: Ingyenes hirdetések aktívak

```
rtcl_membership_settings:
  enable_free_ads: "yes" → AKTÍV
  number_of_free_ads: 5 → 5 ingyenes hirdetés
  renewal_days_for_free_ads: "30" → 30 naponként reset
  unlimited_free_ads_membership: "yes" → Membership nélkül is unlimited (memberships off)
```

### CONFIRMED: Hirdetés lejárat

```
rtcl_moderation_settings:
  listing_duration: "15" → 15 nap
  delete_expired_listings: "15" → Lejárat után 15 nappal törlés
```

---

## 9. KÉPMÉRET BEÁLLÍTÁSOK — CURRENT CONFIRMED

*Forrás: rtcl_misc_media_settings*

| Típus | Méret | Crop |
|-------|-------|------|
| Galéria (nagy) | 600×460 px | igen |
| Galéria thumbnail | 150×105 px | igen |
| Listing kártya | 416×270 px | igen |
| Store banner | 992×300 px | igen |
| Store logó | 200×150 px | igen |

**Engedélyezett formátumok**: PNG, JPG, JPEG, WEBP  
**Max fájlméret**: 2 MB / kép  
**Max képek**: 5 / hirdetés

---

## 10. ÖSSZEFOGLALÓ SZÁMOK — v3.0

**Verzió**: 3.0 — 09-rtcl-forms.json alapján teljesen feltérképezve (2026-08-29)

| Adat | Szám | Státusz |
|------|------|---------|
| Root kategóriák | 10 | CURRENT CONFIRMED |
| Összes kategória | 152 | CURRENT CONFIRMED |
| Helyszínek (flat) | 35 | CURRENT CONFIRMED |
| Field group (rtcl_cf) | 3 | CURRENT CONFIRMED |
| Standard rtcl_cf mező | 5 (+1 logo) | CURRENT CONFIRMED |
| Form Builder formok | **10 db** | CURRENT CONFIRMED |
| Form Builder összes mező | **257 db** | CURRENT CONFIRMED |
| Auto/Moto/Boats FB mező | **49 db** | CURRENT CONFIRMED |
| Összes kategória kap FB formot | **10/10** | CURRENT CONFIRMED |
| Listing type template | 11 | CURRENT CONFIRMED |
| Membership csomagok | 0 (disabled) | CURRENT CONFIRMED |
| Ingyenes hirdetés / 30 nap | 5 | CURRENT CONFIRMED |
| Listing lejárat (nap) | 15 | CURRENT CONFIRMED |
| Max képek / listing | 5 | CURRENT CONFIRMED |

### DESIGN-BLOCKING UNKNOWN státusz

**NINCS DESIGN-BLOCKING UNKNOWN.** Minden szükséges adat ismert a design megkezdéséhez.

Az egyetlen hiányzó adat (logó fájl és design referencia) nem technikai — kliens mellékeli.

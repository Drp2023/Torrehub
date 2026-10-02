# Direction C — Modern Local Hub · Design inventory

Source: `_dev/design/Torrehub - Direction C, Modern Local Hub.html` (bundled export).
Unbundled with `_dev/tools/unbundle.mjs` to `_dev/design/extracted/direction-c.html` + `assets/`.
Rendered in headless Chrome (playwright-core, `_dev/tools/render-preview.mjs`) to `extracted/preview.png` and `extracted/direction-c.rendered.html`.
Machine-readable values: `_dev/design/tokens-audit.json`. Icons: `extracted/icons/*.svg` (64 files).

## 0. How to read this

**What the file is.** It is not a set of separate pages. It is one "design canvas" document. A `<x-dc>` template is rendered by a small React runtime (`assets/dc-runtime.js`, React 18.3.1 UMD) plus an `<image-slot>` custom element (`assets/image-slot-element.js`). The markup itself is **static HTML with inline styles**: there is no JSX, no app state, no hover/focus CSS. The only stylesheet rules are `body{background:#0E1526}`, `a{color:#0056B3}` and `a:hover{color:#C75B39}`. So every state you see is **drawn as a separate static specimen**.

**Layout of the canvas** (1920px boards, top to bottom):

| Board | Content | Frames |
|---|---|---|
| Intro (dark) | "Direction C · Modern Local Hub", rationale cards | — |
| F-01 / F-02 | Colour, type, spacing, depth + category iconography | foundation board, 1920 |
| F-03 / F-04 / F-05 | Controls, inputs, cards | foundation board, 1920 |
| G-01 … G-11 · P-01 | Navigation + homepage | D-HOME (1440), M-HOME, M-NAV, M-LOGGEDIN (390) |
| P-03 … P-14 · L-01 | Archive | D-ARCHIVE (1440), M-ARCHIVE, M-FILTERS, M-MAP (390) |
| L-01 · L-13 · L-14 · L-19 | Single listing | D-LISTING (1440), M-LISTING, M-LISTING-STATES (390) |
| A-01 … A-09 · S-02 · S-04 · S-14 | Auth + add listing | D-AUTH-SUBMIT (1440), M-LOGIN, M-REGISTER-SUBMIT (390) |
| B-01 · B-02 · T-01 … T-08 | Guides, static and error pages | D-GUIDES, D-STATIC (1440), M-GUIDES, M-STATIC (390) |

The frame IDs (`D-HOME`, `M-NAV`…) are my names for the 18 device frames. I use them below and in `tokens-audit.json`. Desktop frames are `width:1440px; border-radius:24px; border:1px solid #17203A`. Mobile frames are `width:390px; border-radius:28px`, with a grey label strip at the top. **The frame outline, its radius and the label strip are presentation chrome, not UI.**

**Screen codes.** Codes come from two places: label strips/eyebrows inside frames (e.g. "P-13 — Filter sheet"), and dark board headers that name ranges (e.g. "G-01 … G-11 navigation"). Several desktop frames hold more than one screen stacked vertically, with no per-screen label. Where I assign a code to an unlabelled region, it is marked **(inferred)**.

## 1. Screen list

### 1.1 Codes found vs expected (72 expected)

| Status | Codes |
|---|---|
| **Labelled in the design** (41) | F-01 F-02 F-03 F-04 F-05 · G-02 G-03 G-04 G-05 G-06 G-08 G-09 G-10 · P-01 P-03 P-04 P-09 P-10 P-13 · L-01 L-15 L-17 L-18 · A-01 A-02 A-03 A-05 A-07 A-09 · S-02 S-18 · B-01 B-02 · T-01 T-02 T-03 T-04 T-05 T-06 T-07 T-08 |
| **Named only in a board header, no labelled region** (8) | G-01, G-11 (endpoints of "G-01 … G-11"), P-14 (endpoint of "P-03 … P-14"), L-13, L-14, L-19 (listed in "L-01 · L-13 · L-14 · L-19 — Single listing"), S-04, S-14 (listed in "A-01 … A-09 auth · S-02 · S-04 · S-14 add listing") |
| **Missing: not mentioned anywhere** (23) | G-07 · P-02, P-05, P-06, P-07, P-08, P-11, P-12 · L-02 – L-12, L-16 · A-04, A-06, A-08 |
| **Extra** (not in the expected list) | none |

### 1.2 Screens

Viewport: **D** = desktop frame 1440 wide, **M** = mobile frame 390 wide, **F** = 1920 foundation board.

| Code | Title as shown | VP | Where (frame) | Contents | Target template |
|---|---|---|---|---|---|
| F-01 | "F-01 / F-02 — Colour, type, spacing, depth" | F | board 2 | Brand swatches (blue, deep blue, accent, clay), 6 neutrals, 4 semantic pills | `/styleguide` (hidden page, e.g. `page-templates/styleguide.php`) |
| F-02 | (same board) | F | board 2 | Type system card, spacing scale (8/16/24/32/48/80), radius & depth, 10 category tiles | `/styleguide` |
| F-03 | "F-03 / F-04 / F-05 — Controls, inputs, cards" | F | board 3 | Buttons: all variants and states, icon buttons | `/styleguide` |
| F-04 | (same board) | F | board 3 | Fields: default, focused, error, verified, read-only, select | `/styleguide` |
| F-05 | "F-05 — Card system: default, featured, urgent, expired, dashboard row" | F | board 3 | Choice/chips/badges/rating panel + 4 card variants + dashboard row | `/styleguide` |
| G-01 (inferred) | — | D | D-HOME top | Desktop header 80px: logo, location pill, search, Explore▾, Guides, EN▾, Log in, Post a listing | `header.php` → `template-parts/header/header-desktop.php` |
| G-02 / G-03 / G-10 | "G-02 / G-03 / G-10 — logged in" | M | M-LOGGEDIN | Logged-in header (chat button with badge "3", avatar), greeting "Good morning, Marco" + 3 stat tiles + verify-badge prompt + free-listing allowance bar, bottom nav with "Chats 3". The split between the three codes is not shown (my guess: G-02 header, G-03 account summary, G-10 bottom nav) | `template-parts/header/header-mobile.php`, `template-parts/account/summary.php`, `template-parts/header/bottom-nav.php` |
| G-04 | "G-04 · 10 roots → 152" | D | D-HOME, below header | Mega panel: root list with counts, 3 subcategory columns, dark "Popular near you" card | `template-parts/header/mega-panel.php` |
| G-05 | "G-09 drawer · G-05 location · G-06 language" | M | M-NAV | Location block: current town + Change, "Use my current location", town chips, "All 35 towns" | `template-parts/header/location-picker.php` |
| G-06 | (same frame) | M | M-NAV bottom | Language chips (8) | `template-parts/header/language-switcher.php` |
| G-08 | "P-01 / G-08 — Mobile homepage" | M | M-HOME | Mobile header (status bar, menu, logo, EN, Log in, location pill, search pill with orange button) + 5-item bottom nav | `template-parts/header/header-mobile.php`, `template-parts/header/bottom-nav.php` |
| G-09 | (M-NAV) | M | M-NAV | Drawer: close, Post + Log in, location block, Explore list of 10 categories, Language | `template-parts/header/drawer.php` |
| G-11 (inferred) | — | D | D-HOME bottom | Footer (#17203A): brand blurb + Facebook/Instagram, 4 link columns (Browse / Discover / Account / Torrehub), © line, 8-language row | `footer.php` → `template-parts/footer/footer.php` |
| P-01 | "P-01 / G-08 — Mobile homepage" (desktop not labelled) | D + M | D-HOME, M-HOME | Hero search module (blue), "This weekend" orange module, stats + suggestion chips, Explore the hub (10 tiles, mobile 6 + "All 10 →"), New near you (4 cards, mobile 1 + 2 horizontal), Towns worth exploring (5, mobile 2), For sellers (dark), Why Torrehub | `front-page.php` + `template-parts/home/*` |
| P-03 | "P-03 / P-04 — Mobile archive" (desktop not labelled) | D + M | D-ARCHIVE, M-ARCHIVE | "Plumbing in Torrevieja": breadcrumb, H1 + intro, Save this search, filter pill bar (Filters·5 + 5 active chips + Clear all), result count, sort, grid/list/map toggle, cards, inline map discovery module, skeleton card, end-of-results card; mobile adds Load 6 more | `classified-listing/` archive override (RTCL `archive-rtcl_listing.php`; confirm the exact file name against the installed plugin) + `template-parts/listing/card-*.php` |
| P-04 | (same label) | M | M-ARCHIVE | The same frame is a keyword search ("plumber · Torrevieja"), so P-04 is probably search results (inferred) | RTCL archive with search query (`search.php` only if WP-native search is kept) |
| P-09 | "P-09 map · P-10 no results" | M | M-MAP top | Full-height map slot, search pill, price pins, zoom +/− and locate buttons, floating listing card | archive map mode → `template-parts/listing/map-view.php` |
| P-10 | (same) | M | M-MAP bottom | Empty state "No results here" + Search within 25 km + Clear all filters | `template-parts/components/empty-state.php` (used by the archive) |
| P-13 | "P-13 — Filter sheet" | M | M-FILTERS | Bottom sheet: Category, Where (town + radius chips), Hourly rate range, Service details "Form 3" (segmented Any/Emergency/24/7, language chips, 2 toggles), Reset / Show 9 results | `template-parts/listing/filter-sheet.php` |
| P-14 (inferred, uncertain) | — | D | D-ARCHIVE | Unlabelled. Candidates: end-of-results card "That's all 9", skeleton card, Save this search | archive partials |
| L-01 | "L-01 / L-15 — Mobile listing + sticky bar" (desktop not labelled) | D + M | D-LISTING, M-LISTING | Gallery (1 large + 2, "+3 photos"), badges (Featured, Verified seller, Open now · until 20:00), logo, H1, rating, address, listed date, views, Save/Share/Print/Report, sticky pill tabs, Service details tiles, About, Amenities, map + Hours, Reviews (summary + 2 reviews), right column: blue contact module (price, Show phone number, WhatsApp, Chat, email enquiry form), seller card, Staying safe | `classified-listing/` single override (`single-rtcl_listing.php`) + `template-parts/listing/*` |
| L-13 / L-14 / L-19 (header only) | — | D | D-LISTING | Not labelled. They are probably sub-blocks of the listing page (reviews, contact module, seller card, safety note…), but which code is which cannot be determined | — |
| L-15 | "L-01 / L-15 — … sticky bar" and "L-15 sheet" | M | M-LISTING bottom, M-LISTING-STATES top | Sticky contact bar (Call accent, WhatsApp green, 52px heart). Contact sheet: seller row, 4 × 54px buttons (phone, WhatsApp, Chat on Torrehub, Send an email), clay note "Chat and email require an account" | `template-parts/listing/contact-bar.php`, `template-parts/listing/contact-sheet.php` |
| L-17 | "L-17 expired" | M | M-LISTING-STATES | Grey notice "This listing has expired … See 8 similar plumbers" | `template-parts/listing/notice-expired.php` |
| L-18 | "L-18 pending" | M | M-LISTING-STATES | Clay notice "Pending approval — only you can see this", Edit listing / My listings | `template-parts/listing/notice-pending.php` |
| A-01 | "A-01 · A-02 — Mobile login & states" | M | M-LOGIN | "Welcome back", context row (log in to chat with this seller), email/password pill fields, Keep me logged in, Forgot?, Log in, "No account yet?" card | `page-templates/login.php` |
| A-02 | (same) | M | M-LOGIN | States: error alert ("Email or password is incorrect · Two attempts left"), loading button "Logging you in…", "Awaiting approval" clay card | `page-templates/login.php` |
| A-03 | "A-03 · Step 1 of 2" / "A-03 mobile" | D + M | D-AUTH-SUBMIT top, M-REGISTER-SUBMIT top | Account type: Member / Private Seller (selected) / Business Seller cards with feature lists and document requirement | `page-templates/register.php` |
| A-05 / A-07 | "A-05 / A-07 · Step 2 of 2 · Private Seller" | D | D-AUTH-SUBMIT | "Your details" form: names, username (error "Already taken"), NIE (verified), email, password + strength meter, confirm, terms checkbox, Create my account. Side panel "What happens next" + "Why we ask for a NIE". Which part is A-05 and which is A-07 is not stated | `page-templates/register.php` |
| A-09 | "A-09 · after approval" | D | D-AUTH-SUBMIT | Clay card "Email confirmed — awaiting approval" + Browse listings while you wait. The title says "after approval" but the content is *awaiting* approval | `page-templates/register.php` (post-confirmation state) or an account notice |
| S-02 | "S-02 · category drill-in" | M | M-REGISTER-SUBMIT | Breadcrumb Auto / Moto / Boats › Vehicles for Sale, rows Cars 4 / Motorcycles 0 / Boats 0, note "Auto form · 49 fields, you'll see about 20" | `classified-listing/listing-form/` (category step) |
| S-04 / S-14 (header only; inferred) | — | D | D-AUTH-SUBMIT bottom | "New listing" workspace: header (Draft saved, Save & exit, Publish listing), left rail (category, 8 section pills with progress / Hidden, free-listing allowance, live preview card), main "Car specifications" form (item type radio cards, segmented controls, inputs, selects, info note), Photos uploader (cover, remove, uploading 68%, add, empty, file-size error), Preview listing / Continue to location. Probably S-04 = form workspace and S-14 = photos (guess) | `classified-listing/listing-form/*` |
| S-18 | "S-18 submitted" | M | M-REGISTER-SUBMIT bottom | "Listing submitted … within 48 hours" + View my listings / Add another listing | `classified-listing/listing-form/` success state (or `myaccount` notice) |
| B-01 | "Torrehub Guides — added scope" (desktop) / "B-01 / B-02 — Mobile guides + post" | D + M | D-GUIDES top, M-GUIDES | Guides index: H1, topic chips (All, Moving here, Property, Paperwork, Towns, What's on), featured guide, 3 image cards, 3 text cards, Load more guides | `home.php` + `template-parts/guides/*` |
| B-02 | "B-02 · single post" / "B-02 · reading view" | D + M | D-GUIDES bottom, M-GUIDES | Post: category badge, H1, author row, share/print, hero, body, H2, amber pull-quote, "Mentioned in this guide" (links to listing categories), sticky TOC "In this guide", "Need help with this?" CTA; mobile reading-progress bar | `single.php` + `template-parts/guides/*` |
| T-01 | "T-01 · About" | D + M | D-STATIC, M-STATIC | Blue hero with 4 stat tiles (152 / 35 / 318 / 8), "Why we built it", image | `page-templates/about.php` |
| T-02 | "T-02 · Contact" | D + M | D-STATIC, M-STATIC | Form (name, email with error, subject select, message + counter), Send, success alert "Message sent", contact info tiles, map | `page-templates/contact.php` |
| T-03 | "T-03 · FAQ" | D + M | D-STATIC, M-STATIC | FAQ search, accordion (1 open + 5 closed) | `page-templates/faq.php` |
| T-04 / T-05 | "T-04 / T-05 · Legal" | D | D-STATIC | Privacy Policy with numbered TOC (active pill) + sections; "Terms uses this identical layout" | `page-templates/legal.php` |
| T-06 | "T-06 404" | D + M | D-STATIC, M-STATIC | Grey panel: "404", "This page has moved on", search, Back to homepage, Browse all categories | `404.php` |
| T-07 | "T-07 401" | D | D-STATIC | Blue-tint panel: "Log in to see your favourites", Log in / Create an account, "Returns to /favourites" | `template-parts/components/access-denied.php` (called by account/favourites templates). WordPress has no 401 template |
| T-08 | "T-08 403" | D | D-STATIC | Clay panel: "You need a seller account", member chip, Contact us to upgrade / Back to browsing | same partial, 403 variant |

### 1.3 Planned templates with no design

| Planned file | Status |
|---|---|
| `page-templates/recover.php` | **No design.** There is only a "Forgot?" link (A-01). A-04/A-06/A-08 are missing; one of them is probably recovery. |
| `page-templates/my-account.php`, `classified-listing/myaccount/*` | **Only fragments:** the mobile logged-in summary (G-03), the F-05 dashboard row, L-18 "My listings" button and S-18. There is no desktop dashboard, my-listings table, favourites, messages/chat, profile edit or verification-upload screen. |
| `archive.php` / `search.php` (WordPress posts) | No generic post archive or search. Guides topic filtering is shown only as chips on B-01. |
| Register — Business Seller step 2 | Only the Private Seller variant is drawn. |
| "All 152 categories" / "All 35 towns" / town landing / "What's on" events page | Linked from the homepage but not designed. |
| `/styleguide` | Covered by F-01…F-05. |

## 2. Components and states

Measurements are the authored inline values: px, font size/weight, and **authored border widths** (Chrome renders 1.5px borders as 1px at DPR 1). "Screens" uses the frame IDs from §0.

### 2.1 Buttons

All buttons are pills (`border-radius:100px`), Figtree 700, `font-family:inherit`. Specimen (F-03): height 46, padding 14×24, 15px.

| Variant | Colours (bg / text / border) | States drawn | Used in |
|---|---|---|---|
| Accent | #FF8C00 / #17203A | default; **pressed #B25E00 with #FFFFFF text** (the text colour flips) | Post a listing (all headers), Search, Create a seller account, Show phone number, Call, Show 9 results, Log in (M-LOGIN, T-07), Publish, Continue, Create my account, Send message |
| Primary | #0056B3 / #FFFFFF | default; **focus = `outline:3px solid rgba(0,86,179,.3); outline-offset:3px`**; loading = bg #2A66A6 + spinner + label (also M-LOGIN "Logging you in…") | Contact (archive cards), Chat on Torrehub, See 8 similar, Search within 25 km (M-MAP), Call (M-ARCHIVE) |
| Outline (ink) | #FFFFFF / #17203A / 1.5px #17203A, padding 13×24 | default | Log in, Save search, Load more, Reset, Show all 64 reviews, Preview listing, Create a free account |
| Soft blue | #E6EFFA / #00458F | default | "Soft — Show phone", Save this search (D-ARCHIVE) |
| Destructive (soft) | #FBE9EC / #96223A | default. No solid red button exists | specimen only. Delete icon button (dashboard row) #FBE9EC / #C0304A |
| Disabled | #EDEDF4 / #A0A8C0 | — | specimen only |
| Ink solid *(not in F-03)* | #17203A / #FFFFFF | default | Write a review, Search within 25 km (D-ARCHIVE), Back to homepage |
| Clay solid *(not in F-03)* | #A8482B / #FFFFFF | default | Upload document, Edit listing, Browse listings meanwhile / while you wait, Contact us to upgrade |
| WhatsApp *(not in F-03)* | #0E7A5F / #FFFFFF | default | WhatsApp buttons, View my listings (S-18) |
| Neutral soft *(not in F-03)* | #F7F7FB / #17203A | default | Save / Share / Print (38px), Get directions, Send an email, Save & exit |
| White on tinted/dark surfaces *(not in F-03)* | #FFFFFF / #17203A, #00458F, #9C4529, #8A5309 or #0B5F49 | default | Open map view, Chat (contact module), Create an account (T-07), Back to browsing (T-08), Browse advisors, Add another listing |
| Ghost on dark / blue *(not in F-03)* | transparent / #FFFFFF / 1.5px #3C4766 or rgba(255,255,255,.45) | default | How it works (For sellers), Send message (contact module) |
| Clay outline *(not in F-03)* | #FFFFFF / #9C4529 / 1.5px #E5BFAE | default | My listings (L-18) |
| Text button | transparent / #5F6A8A, 13.5px 400 | default | Report |
| Icon button (round) | 46px specimen: white + 1px #E2E2EC; active favourite = #FBE9EC bg with #C0304A filled heart | default, active | Card favourite 36px (desktop) / 44px (mobile), share/print 38px, map controls 46px with shadow, chat badge button 44px, photo remove 24px |

Heights in use: **36, 38, 39, 40, 41, 42, 43, 44, 46, 48, 50, 51, 52, 54**. The F-03 rule says "44px mobile minimum · 46px desktop · 50–54px mobile CTA". The desktop compact headers use 39–41px buttons, and the desktop card favourite button and Save/Share/Print are 36–38px. The **hover state is not drawn** for any button. Spinner icon: stroke 2.6, white.

### 2.2 Form controls

| Control | Spec | States drawn | Screens |
|---|---|---|---|
| Text input (F-04) | h48, padding 14×16, radius 12, 1.5px #DCDCE8, bg #F7F7FB, 15px/400. Label above: 11.5px 700 caps. Helper: 12.5px #5F6A8A | default+placeholder; **focused** bg #FFF, 1.5px #0056B3, `box-shadow:0 0 0 4px rgba(0,86,179,.12)`; **error** bg #FFFDFD, 1.5px #C0304A, helper with alert-circle #C0304A; **verified** bg #FFF, 1.5px #0E7A5F, check icon right (padding-right 44); **read-only** bg #EDEDF4, border #EDEDF4, text #5F6A8A. Disabled is **not drawn** | F-04, D-AUTH-SUBMIT, D-STATIC |
| Input text colour | not set, so it renders browser-default **#000000**, not ink | — | all inputs |
| Prefix / suffix input | € prefix (padding-left 34), "km" suffix (padding-right 48) | default | D-AUTH-SUBMIT |
| Pill input (variant) | radius 100: login fields h49, 16px, white, no border; contact-module fields h42 white; search pills h46 (white or #F7F7FB + 1px #E2E2EC); FAQ search h48 | default | M-LOGIN, D-LISTING, D-HOME, D-STATIC, M-STATIC |
| Select (div mock) | same box as input + chevron-down 16px right | default | F-04, D-AUTH-SUBMIT (Body type, Fuel, Engine cc), D-STATIC (Subject) |
| Textarea | radius 16, padding 14×16 (D-LISTING: white, no border, 72h; D-STATIC: #F7F7FB + #DCDCE8, 130h; M-STATIC 100h); counter "0 / 2000 characters" | default | D-LISTING, D-STATIC, M-STATIC |
| Checkbox | 20×20, radius 6 | checked #0056B3 + white check; unchecked 1.5px #C4C7D8 on #FFF; disabled 1.5px #EDEDF4 on #F7F7FB, label #A0A8C0 | F-05 panel, D-AUTH-SUBMIT (terms), M-LOGIN (keep me logged in) |
| Radio | 20px circle | selected #0056B3 + white check; unselected 2px #C4C7D8 | account-type cards (A-03), item type cards (S-04) |
| Toggle | 48×28 track #0056B3, 22px white knob, inset 3 | **only ON is drawn** (2×) | M-FILTERS |
| Segmented | track #EDEDF4, radius 100, padding 4, gap 4; active #FFF 700 #17203A; inactive 14px **500** #5F6A8A (specimen) | active/inactive | F-05 panel (37h), D-AUTH-SUBMIT (40h: Sale/Rent, Private/Dealership, New/Used, Manual/Automatic), M-FILTERS (Any/Emergency/24/7) |
| Range (dual) | track 5px #E2E2EC, fill #0056B3, thumbs 23px white with 2.5px #0056B3 | default | M-FILTERS |
| Password strength | 4 segments (3 filled #0E7A5F), "Strong" 12.5px 600 #0B5F49 | one state | D-AUTH-SUBMIT |

### 2.3 Chips

| Type | Spec | States | Screens |
|---|---|---|---|
| Active filter chip (F-05 specimen) | h33, padding 8 10 8 14, 13px 700, × icon 12px | ink #17203A/#FFF; blue soft #E6EFFA/#00458F; "Clear all" white + 1px #DCDCE8, #5F6A8A | F-05 |
| Desktop filter bar | container: white, 1px #E2E2EC, radius 100, padding 8×10. Chips h36, 13.5px 700: "Filters · 5" ink + filter icon; active filters #E6EFFA/#00458F + ×; "Verified only" #E4F4EE/#0B5F49; "Clear all" text | active only | D-ARCHIVE |
| Mobile filter chips | h35, 13px 700, same colours | active only | M-ARCHIVE |
| Choice chips | h39, 14px; selected #0056B3/#FFF 700; unselected #F7F7FB/#3A4562 600 | selected/unselected | M-FILTERS (languages) |
| Radius chips | h35, 13px; selected ink; unselected #F7F7FB/#3A4562 | selected/unselected | M-FILTERS |
| Suggestion chips | h29, 12.5px 600, #F7F7FB/#3A4562 | default | D-HOME stats card |
| Attribute chips (cards) | h22–24, padding 4×9 or 5×10, 11.5px 600 #3A4562 on #F7F7FB (or #FFF on tinted cards) | default | all cards |
| Amenity chips | desktop h39 14px 600 #F7F7FB + green check; mobile h33 13px | default | D-LISTING, M-LISTING |
| Topic chips (Guides) | h38 white + 1px #E2E2EC, 13.5px 600; active one is ink | active/inactive | D-GUIDES, M-GUIDES (h35) |
| Town chips | h31, 12.5px; active #0056B3/#FFF; others #FFF/#3A4562 | active/inactive | M-NAV |
| Language chips | drawer h33 13px (active ink, others #F7F7FB); footer h26 12px (active #2A3454/#FFF, others transparent #A3AECB) | active/inactive | M-NAV, D-HOME footer |
| Location pill | desktop h46 padding 0 18, #E6EFFA, pin + town 14.5px 700 #00458F + chevron; compact h39 | default | all headers with location |

### 2.4 Badges and status pills

Specimen (F-05): h26, padding 6×11, 11.5px 700 uppercase. Letter-spacing is **0.06em** for solid badges and **0.04em** for tinted ones.

| Badge | Colours | Where |
|---|---|---|
| Featured | #FF8C00 / #17203A | cards, single listing, guides featured |
| Urgent | #A8482B / #FFFFFF | F-05 card |
| Verified (+check 11px) | #E4F4EE / #0B5F49 | cards, single ("Verified seller"), map card |
| Pending | #FCEEE7 / #9C4529 | F-05 panel |
| Expired | tinted #EDEDF4 / #5F6A8A (panel); **solid #5F6A8A / #FFF** on the expired card | F-05 |
| Open now / Open · to 20:00 / Open | #E4F4EE / #0B5F49 | D-HOME restaurant card, D-LISTING, M-LISTING, hours header |
| Active (listing status) | #E4F4EE / #0B5F49 | dashboard row |
| Not verified | #EDEDF4 / #5F6A8A | D-ARCHIVE card 3 |
| Selected | #0056B3 / #FFF, tab-like on the card edge | A-03 card |
| Account type "Member" | green tint pill | T-08 |
| Guide category | #FFFFFF / #00458F, uppercase | D-GUIDES cards |
| Cover | #17203A / #FFF | photo uploader |
| Photo count / gallery counter | rgba(14,21,38,.72) or .78 / #FFF, not uppercase | D-ARCHIVE, M-LISTING ("1 / 5") |
| Notification count | #C0304A / #FFF, h17–19, 11.5px | M-LOGGEDIN header + bottom nav |
| "Within 10 km" / "10 km" | #E4F4EE / #0B5F49, caps | section headers |
| Free-listing allowance | amber tint #FFF1E0, text #8A5309, orange progress bar | D-AUTH-SUBMIT rail, M-LOGGEDIN |

Badge heights in screens vary: **22, 24 and 26px** (paddings 4×9, 5×10, 6×11/12). Account-type badges for Private/Business Seller are not drawn as badges; only the Member chip appears.

### 2.5 Rating pill

#FFF1E0 background, star-filled #FF8C00 (11–15px), value in **Figtree 800** 12–14px #17203A. Review count sits outside the pill, 13px #5F6A8A. Heights 22–31. Variant with a word label: "★ Excellent". Used on F-05, D-HOME, D-ARCHIVE, M-ARCHIVE, D-LISTING (header, summary, review cards), M-LISTING. The reviews summary shows "4.9" at 48px (34px mobile), Space Grotesk 700, with distribution bars (fill #0E7A5F on #E2E2EC).

### 2.6 Listing cards

| Variant | Spec | Screens |
|---|---|---|
| Default (vertical) | #FFF, 1px #E2E2EC, radius 20, padding 10, gap 12. Image radius 14, 170–186h. Favourite 36px circle top-right. Body padding 0 8 8, gap 7. Price Space Grotesk 21/700 −0.025em (mobile 19–22). Title 14.5px 600. Attribute chips. Meta 12.5px #5F6A8A (location left, age right) | F-05, D-HOME |
| Featured | bg #FFF1E0, border #FFD9A8, Featured badge on the image; meta "✓ Verified" green | F-05, D-HOME, M-HOME, D-ARCHIVE, M-ARCHIVE |
| Urgent | bg #FCEEE7, border #F0CDBC, Urgent badge; meta right "Ends in 2 days" #9C4529 700 | F-05 only |
| Expired | bg #F7F7FB, image veil rgba(247,247,251,.7), muted price/title, "Listing ended 4 days ago", "See similar →" | F-05 only (single-page expired = L-17) |
| Service / archive card | Verified badge + rating row, "From €45 / hour", tags, "Torrevieja · 0.8 km" + age, full-width Contact (primary h44) + 44px round chat/heart button, photo-count chip. Mobile: Call (primary) + WhatsApp (green) h46 | D-ARCHIVE, M-ARCHIVE |
| Venue card | Open now badge, rating + count, "€€" + cuisine chips, "Closes 23:30" | D-HOME |
| Horizontal / list | 110×110 image, #FFF, radius 20, padding 8, grid 110px + 1fr | M-HOME (a desktop list-view card is **not drawn**, although the list toggle exists) |
| Map card | floating, 100×100 image, radius 20, padding 8, `0 12px 32px -8px rgba(23,32,58,.36)` | M-MAP |
| Dashboard row | h94 row, radius 20, padding 10: 96×72 thumb (radius 12), title + category path, price 17/700, Active badge, "412 views", edit + delete icon buttons 38px | F-05 only |
| Skeleton | blocks #EDEDF4 / #F2F2F7, radii 14 / 8 / 6 / 100 | D-ARCHIVE (1 card) |
| End-of-results | #FFF, **1px dashed #C4C7D8**, radius 20, padding 24, centred: search-empty icon in a 54px circle, Space Grotesk 19/700 "That's all 9", explanation, ink button | D-ARCHIVE |
| Live preview card | small card under the submission rail | D-AUTH-SUBMIT |
| Guide cards | image 170h + category badge + h3 19/600 + excerpt + "date · min"; text-only cards (h3 17/600); mobile horizontal 104×100 | D-GUIDES, M-GUIDES |

### 2.7 Category tile, town card, discovery modules

- **Category tile**: radius 20, padding 20, tinted background (§5.2), stroke icon, name 15px 700 #17203A, meta 13px #5F6A8A "48 listings · 42 categories". Desktop 266×117 with icon 26. Mobile 176×124 with icon 24, count only. F-02 specimen 181×104, centred. Drawer variant: 48px row, radius 16, icon 20 + chevron-right 15. Mega-panel root row: pill h37, active #E6EFFA with 14px 700 #00458F label.
- **Town card**: radius 20, image-slot 200h (mobile 150×180), overlay `linear-gradient(180deg, rgba(14,21,38,0) 45%, rgba(14,21,38,.82) 100%)` (.84 on mobile), name Space Grotesk 20/700 white (17 mobile), count 12.5px #C6CFE8.
- **Discovery modules (home)**:
  - Hero search: #0056B3, radius 24, padding 40, min-h 280. Eyebrow pill, H1 52/700, 17px #F1F7FD lead, white 46px search pill with an inner accent "Search" button.
  - "This weekend": #FF8C00, radius 24 (20 mobile), padding 26/22, headline 30/26 Space Grotesk, link underlined with `border-bottom:1.5px solid rgba(255,255,255,.5)`.
  - Stats card: 3 × 26/700 numbers, suggestion chips.
  - For sellers: #17203A, radius 24, padding 32, eyebrow #FFD09A.
  - Why Torrehub: white module, 40px tinted icon circles.
- **Other modules**:
  - Inline map discovery module: #17203A, map slot with price pins, eyebrow "DISCOVERY MODULE" #FFD09A, white button. D-ARCHIVE, M-ARCHIVE.
  - Blue contact module: #0056B3, radius 20, padding 24, sticky. D-LISTING.
  - Seller card with tinted fact rows: "Typical reply · Within 1 hour" on green tint.
  - "Staying safe": clay tint.
  - "Mentioned in this guide": links back to listing categories.
  - "Need help with this?": amber CTA, 8A5309 button text.
  - "What happens next": blue panel with `rgba(255,255,255,.14)` step cards and an orange 3rd step.
- **Section header**: h2 Space Grotesk 26/600 −0.025em (mobile 19/600), optional green chip ("Within 10 km"), right link "See all →" / "All 152 categories →" (#0056B3, 700). Eyebrow labels: 11.5px 700 caps, letter-spacing 0.14em (0.1em inside cards).

### 2.8 Navigation and structure

| Component | Spec | Screens |
|---|---|---|
| Desktop header (full) | h80, padding 0 32, white, bottom 1px #E2E2EC; logo h32; location pill h46; search pill h46 max-w 400; Explore▾ / Guides / EN▾ 14.5px; Log in outline h42; Post accent h44 | D-HOME |
| Desktop header (compact) | h72, padding 0 28; logo h28; location h39; search; Log in h39; Post h41 | D-ARCHIVE, D-LISTING |
| Header variants | Guides: logo + Explore + Guides (active tint pill) + Post, no location/search/login. Static: logo + Post only. Auth: logo + "Already registered? Log in". Submit: logo + "New listing" + Draft saved + Save & exit + Publish | D-GUIDES, D-STATIC, D-AUTH-SUBMIT |
| Mobile header | status-bar mock (44h, not to build); 44px menu button, logo h24, "EN▾" pill h39, Log in outline h44; location pill h42; search h56 (#F7F7FB pill, 5px inset accent search button 46). Logged-in: location + chat button (badge) + 38px avatar | M-HOME, M-LOGGEDIN, M-LOGIN, M-GUIDES |
| Drawer (G-09) | white, close 44, Post (accent) + Log in (outline) h50, location block (#E6EFFA radius 20 padding 16), Explore list (10 tinted rows), Language chips | M-NAV |
| Bottom nav | h94 (padding 8 10 18), white, top 1px #E2E2EC, 5 columns, labels 11.5px. Active #0056B3 700 with icon on a tint pill; inactive #5F6A8A. Centre "Post" = **48×44 accent pill**. Logged-out: Home / Search / Post / Saved / Account. Logged-in: Saved becomes Chats + count badge | M-HOME, M-LOGGEDIN |
| Mega panel (G-04) | white, padding 28 32, grid 250px / 1fr / 280px, gap 32. Subgroup headers 11.5px caps #0056B3 with bottom rule. Links 13.5px. Dark card #17203A radius 20 padding 22 | D-HOME |
| Footer | #17203A, padding 44 32 28, text #A3AECB, grid 1.4fr + 4 cols, column titles 11.5px caps, social icon circles 38px #2A3454, © 12.5px #97A1BE, divider #2A3454 | D-HOME (footer appears only here) |
| Breadcrumb | 12.5px #5F6A8A, "/" separators, current item 700 #17203A, padding 22 28 0; optional right "← Back to 9 results" #0056B3 | D-ARCHIVE, D-LISTING |
| Sticky pill-tab bar | Desktop: white, 1px #E2E2EC, radius 100, padding 6, gap 4, `position:sticky; top:16px`. Tabs 13.5px, padding 11×18; active #17203A/#FFF 700, inactive 600 #5F6A8A. Mobile: full-width white bar, sticky top 0; tabs h35 13px, inactive on #F7F7FB | D-LISTING, M-LISTING |
| Pagination / load more | **No numbered pagination anywhere.** Load more = outline button (mobile full-width h50 "Load 6 more"; desktop centred h48 "Load more guides"). End-of-results card | M-ARCHIVE, D-GUIDES, D-ARCHIVE |
| Sort / view toggle | "Sort: Nearest ▾" white pill h40 with 1px #E2E2EC; segmented grid / list / Map (#EDEDF4 track) | D-ARCHIVE, M-ARCHIVE |
| Bottom sheet | scrim rgba(14,21,38,.5); sheet #FFF, radius 24 24 0 0; grab handle 44×4 #C4C7D8; title row Space Grotesk 19/700 + "Clear all"; sticky footer (padding 12 16 22, grid 1fr 1.5fr) | M-FILTERS (P-13), M-LISTING-STATES (L-15) |
| Accordion | open item #E6EFFA with minus icon, question 700 #00458F; closed white rows with plus | D-STATIC, M-STATIC |
| Legal TOC | numbered list, active item #E6EFFA pill | D-STATIC |
| Hours table | today row highlighted #E6EFFA pill, "Open" badge | D-LISTING, M-LISTING (today only) |
| Detail tiles | tinted blocks (#E6EFFA / #E4F4EE / #F7F7FB), label 11.5px caps #2F5C8F / #2C6B58 / #5F6A8A, value 14px 700 | D-LISTING, M-LISTING |
| Stepper / progress | allowance bar (orange on #E2E2EC), section pills with x/y counts (done #E4F4EE, current #0056B3, hidden #EDEDF4), upload progress 68%, reading-progress bar (orange, `radius 0 100px 100px 0`) | D-AUTH-SUBMIT, M-LOGGEDIN, M-GUIDES |
| Map pins | price pills h27–31, #0056B3 / #FF8C00 / #FFF, `box-shadow:0 6px 16px rgba(0,0,0,.2–.3)`; map controls 46px circles with `0 8px 24px -8px rgba(23,32,58,.28)` | D-ARCHIVE, M-ARCHIVE, M-MAP |

### 2.9 Feedback

| Component | Spec | Screens |
|---|---|---|
| Toast | **Not drawn.** The nearest things are the "Draft saved 12:41" status pill (#E4F4EE / #0B5F49, h33) and the inline "Message sent" alert | — |
| Inline alert: success | #E4F4EE, radius 16, padding 14×16, check icon, title 14px 700 #0B5F49 | D-STATIC (T-02) |
| Inline alert: error | #FBE9EC, radius 20, padding 16, alert-circle #C0304A, title 14px 700 #96223A | M-LOGIN (A-02); photo-size error banner D-AUTH-SUBMIT |
| Notice: pending / attention (clay) | #FCEEE7, radius 20, padding 16–18, title #9C4529 | L-18, A-02/A-09 awaiting approval, verify-badge prompt, "Staying safe", "Chat and email require an account" |
| Notice: neutral | #EDEDF4, radius 20 (L-17 expired) or pill with info icon ("With licence appears only…") | M-LISTING-STATES, D-AUTH-SUBMIT |
| Empty state | P-10: 28px search-empty icon in a circle, Space Grotesk 22/700, 2 × h50 buttons. Error panels T-06/07/08: tinted panels (#EDEDF4 / #E6EFFA / #FCEEE7), radius 20, padding 26, 404 numeral 56/700 #C4C7D8. Uploader "Empty" tile | M-MAP, D-STATIC, M-STATIC, D-AUTH-SUBMIT |

## 3. Token audit

Counts are occurrences in authored inline styles and SVG `fill`/`stroke` attributes inside `<x-dc>`. **presentation** = canvas chrome, **F** = foundation boards, **screens** = the 18 frames (this includes the frame outlines themselves). Full tables are in `tokens-audit.json`.

### 3.1 Colours (69 distinct values)

| Value | Count | Token | Typical use |
|---|---:|---|---|
| #5F6A8A | 365 | muted | meta text, icons, labels |
| #FFFFFF | 361 | white | surfaces, text on dark |
| #17203A | 240 | ink | text, ink buttons, footer bg, frame outline |
| #E2E2EC | 161 | line | borders |
| #0056B3 | 128 | blue | primary, links, icons |
| #F7F7FB | 124 | bg | page bg, input bg |
| #3A4562 | 124 | text | body copy, chips |
| #EDEDF4 | 65 | surface-2 | neutral tiles, tracks |
| #0E7A5F | 61 | success-fg | check icons, WhatsApp, bars |
| #FF8C00 | 60 | orange | accent CTA, stars |
| #E6EFFA | 59 | tint-blue bg | soft blue |
| #00458F | 52 | blue-ink | text on blue tint |
| #0B5F49 | 43 | success-text | |
| #A3AECB | 36 | footer text | |
| #E4F4EE | 35 | success-bg | |
| #FFF1E0 | 26 | tint-amber bg | |
| #C4C7D8 | 24 | line-3 | radio/checkbox borders, dashed borders, 404 numeral |
| #A8482B | 23 | clay | |
| #9C4529 | 23 | clay text on tint | |
| #DCDCE8 | 21 | line-2 | input borders |
| #FCEEE7 | 20 | tint-clay bg | |
| #FFD09A | 19 | accent text | 10 of the 19 are presentation chrome |
| #C0304A | 15 | error-fg | |
| **#2F5C8F** | 15 | — | caps labels on blue-tint tiles/stats |
| #C97A16 | 9 | tint-amber icon | |
| #FBE9EC | 8 | error-bg | |
| **#F1F7FD** | 8 | — | light text on blue (hero lead, "What happens next") |
| #96223A | 7 | error-text | |
| **#A0A8C0** | 7 | — | disabled text |
| #FFD9A8 | 7 | tint-amber border | |
| **#C6CFE8** | 7 | — | town-card count text |
| #8A5309 | 7 | tint-amber text | |
| **#2C6B58** | 6 | — | caps labels on green-tint tiles |
| **#EDF4FC** | 6 | — | light text on blue contact module |
| #2A3454 | 5 | footer chip | social circles, divider, active language |
| **#FFFDFD** | 3 | — | error input background |
| **#F2F2F7** | 3 | — | skeleton blocks |
| **#2A66A6** | 2 | — | loading button bg |
| **#3C4766**, **#97A1BE**, **#E5BFAE** | 1 each | — | ghost border on dark; footer © text; clay-outline border |
| #003D82 / #B25E00 / #F0CDBC | 1 each | blue-deep / orange-pressed / tint-clay border | swatch / pressed-button specimen / urgent card only |
| #0E1526 | 6 | night | presentation chrome only (board backgrounds) |
| #7A85A8, #D5DBEC | 5 / 4 | — | presentation chrome only |
| rgba(14,21,38,0 → .5/.6/.72/.78/.82/.84) | 22 | — | gradients, scrims, photo chips, "+3 photos" veil |
| rgba(23,32,58,.18/.22/.28/.3/.36) | 9 | — | shadows |
| rgba(0,0,0,.2/.3) | 3 | — | map-pin shadows |
| rgba(255,255,255,.14/.45/.5/.55) | 5 | — | on-blue/orange step cards, borders, underlines |
| rgba(0,86,179,.12/.3) | 2 | — | focus rings |
| rgba(247,247,251,.7) | 1 | — | expired-card veil |
| rgba(255,140,0,.16) | 1 | — | presentation chrome |

**Colours used in the design but missing from the proposed tokens** (screens/F): **#2F5C8F, #2C6B58** (label-on-tint), **#F1F7FD, #EDF4FC** (text-on-blue), **#C6CFE8** (text-on-photo), **#A0A8C0** (disabled text), **#FFFDFD** (error input bg), **#F2F2F7** (skeleton), **#2A66A6** (loading primary), **#3C4766**, **#97A1BE**, **#E5BFAE**. Plus the alpha values above: overlay/scrim ink alphas, shadow ink alphas, white alphas, and the focus-ring blue alphas.

**Proposed tokens not used in screens**: blue-deep #003D82 (one F-01 swatch), orange-pressed #B25E00 (one F-03 specimen), tint-clay border #F0CDBC (only the F-05 urgent card), night #0E1526 (presentation chrome only). All other proposed colours are used.

**#C75B39 contradiction.** F-01 labels the clay swatch "#C75B39" and the semantic pill "Pending · #C75B39", and the stylesheet has `a:hover{color:#C75B39}`. But every drawn clay fill is **#A8482B**, and #C75B39 never appears as a fill. Treat #C75B39 as a stale label. Note that the global link hover colour is that stale clay.

### 3.2 Typography

| Family × weight (rendered text nodes) | Count | Sizes | Notes |
|---|---:|---|---|
| Figtree 700 | 517 | 11.5 (222×), 13–16, 17, 18, 20 | labels, buttons, titles |
| Figtree 400 | 420 | 11.5–17, 19 | body |
| Figtree 600 | 114 | 11.5–17.5 | listing titles, chips, meta |
| Space Grotesk 700 | 81 | 17–78 | display, prices, numerals |
| Space Grotesk 600 | 42 | 12–26 | module headings (h2/h3), pull quote |
| **Figtree 800** | 12 | 11.5–14 | **rating numbers** in rating pills/review cards, "Excellent" |
| **Figtree 500** | 1 | 14 | **inactive segment** "For rent" (F-05 panel) |

- Font sizes (32 distinct): 11.5 (301), 13.5 (133), 13 (116), 12.5 (102), 14 (101), 14.5 (88), 15 (82), 12 (55), 16 (32), 15.5 (30), 20, 26, 17, 22, 19, 24, 18, 21, 34, 30, 28, 40, 44, 78, 46, 52, 48, 32, 36, 17.5, 56, 25. Nothing is below 11.5, so the "11.5 minimum" rule holds. Body 15.5 is rarely used: most running copy is 13.5–15.
- Display sizes in screens fall short of the F-02 rule ("H1 46–78 desktop, 30–36 mobile"). Desktop H1s are 34 (archive, listing), 36/40 (guides), 40 (auth), 44 (about), 52 (home). Mobile H1s are 24–26.
- Letter-spacing (15 values): −0.05, −0.04, −0.035, −0.03, −0.025 (51×), −0.02, −0.01, 0.04, 0.05, 0.06, 0.08, 0.1 (48×), 0.12, 0.14 (45×), 0.16em.
- Line-height (27 values): 0.9 – 1.72. The most common are 1.6, 1.55, 1.35, 1.5 and 1.3.
- Text-transform uppercase: 195 elements (micro-labels, badges).
- `font-family:inherit` on 115 elements (buttons/inputs inherit Figtree from the root wrapper).

### 3.3 Radii

| Value | Count | Token | Notes |
|---|---:|---|---|
| 100px | 444 | pill | |
| 20px | 153 | card/module | |
| 16px | 63 | tile | inner panels, alerts, textarea, drawer rows |
| 14px | 48 | card-image | card images, F-01 neutral swatches, uploader tiles |
| 12px | 27 | input | inputs, dashboard thumb |
| 24px | 13 | hero | 6 are desktop frame outlines; 7 product (hero, weekend, for-sellers, about hero…) |
| **28px** | 12 | — | mobile device frame outline only (presentation) |
| **24px 24px 0 0** | 2 | — | bottom sheets |
| **6px** | 6 | — | checkboxes, skeleton lines |
| **2px** | 6 | — | spacing-scale bars (F-02 only) |
| **50%** | 6 | — | dots |
| **8px**, **18px**, **0 100px 100px 0** | 1 each | — | skeleton block; guides hero image; reading-progress fill |

### 3.4 Shadows, gradients, other effects

| Value | Count | Use |
|---|---:|---|
| `0 8px 24px -8px rgba(23,32,58,0.18)` (**proposed token**) | **1** | F-02 "Lifted — sheet, dropdown" specimen only |
| `0 8px 24px -8px rgba(23,32,58,0.28)` | 5 | map search pill, map controls |
| `0 8px 24px -8px rgba(23,32,58,0.3)` | 1 | map address card (D-LISTING) |
| `0 12px 32px -8px rgba(23,32,58,0.36)` | 1 | floating map listing card |
| `0 -8px 24px -10px rgba(23,32,58,0.22)` | 1 | mobile sticky contact bar |
| `0 6px 16px rgba(0,0,0,0.3)` / `…0.2` | 2 / 1 | map price pins |
| `0 0 0 4px rgba(0,86,179,0.12)` | 1 | input focus ring |
| `outline: 3px solid rgba(0,86,179,0.3); outline-offset: 3px` | 1 | button focus ring |
| `linear-gradient(180deg, rgba(14,21,38,0) 45%, rgba(14,21,38,0.82) 100%)` | 5 | town cards desktop |
| same with 0.84 | 2 | town cards mobile |
| `filter: brightness(0) invert(1)` | 2 | white logo on dark (intro + footer) |

The intro board claims "no gradients, no blur". There is no blur, but the town-card overlay gradient is used 7 times. Resting surfaces use a 1px #E2E2EC border and no shadow, as specified. Shadows appear only on floating elements, and those use **4 different ink alphas** (.18/.22/.28/.3/.36), not the single token.

### 3.5 Spacing

Values used in padding/margin/gap (count): 14 (218), 10 (210), 8 (191), 12 (186), 16 (176), 5 (121), 9 (110), 4 (104), 11 (86), 7 (79), 6 (78), 18 (76), 13 (59), 20 (51), 24 (42), 3 (35), 2 (31), 15 (30), 28 (28), 32 (24), 22 (20), 26 (14), 80 (13), 40 (11), 44 (11), 64 (8), 1 (6), 19 (4), 30 (3), 48 (2), 36 (2), 34, 46, 60, 72, 160, −11.

F-02 states a "4px base" (8 / 16 / 24 / 32 / 48 / 80). In practice the design uses odd steps heavily: 5, 7, 9, 11, 13, 15, 19. The most common values are 14 and 10, which are not on the 4/8 scale. Typical module paddings: 22–24 (cards/modules), 26 (F panels), 32 (desktop modules), 14 (mobile page gutter), 28 (desktop compact frame gutter), 32 (home gutter).

## 4. Icons

64 distinct inline SVGs, 264 instances. Almost all are `viewBox="0 0 24 24"`, stroke-based (`fill="none"`, round caps/joins), with stroke widths 1.8–3.4 varying by size (2 is the most common). Each is saved as `extracted/icons/<name>.svg` with `currentColor`; the file header comment lists the stroke widths and sizes seen.

| Name | Mode | Uses | Screens |
|---|---|---:|---|
| cat-auto-moto-boats (car) | stroke | 4 | F-02, D-HOME, M-HOME, D-AUTH-SUBMIT (item type "Car") |
| cat-events (calendar) | stroke | 2 | F-02, D-HOME |
| cat-jobs (briefcase) | stroke | 3 | F-02, D-HOME, M-HOME |
| cat-leisure-sport (ball) | stroke | 3 | F-02, D-HOME, M-NAV |
| cat-properties (house + door) | stroke | 3 | F-02, D-HOME, M-HOME |
| cat-marketplace (box) | stroke | 3 | F-02, D-HOME, M-HOME |
| cat-public-information (i-circle) | stroke | 4 | F-02, D-HOME, M-NAV, D-GUIDES |
| cat-restaurants-nightlife (fork & knife) | stroke | 4 | F-02, D-HOME, M-HOME, M-NAV |
| cat-services (hammer) | stroke | 5 | F-02, D-HOME, M-HOME, M-NAV, D-GUIDES |
| cat-tourist-attractions (star outline) | stroke | 3 | F-02, D-HOME, M-NAV |
| drawer-auto-moto-boats / drawer-jobs / drawer-marketplace / drawer-events | stroke | 1 each | M-NAV: **different, simplified drawings** of 4 category icons (see §4.1) |
| heart | stroke (filled #C0304A when active) | 16 | F-03, D-HOME, M-HOME, D-ARCHIVE, M-ARCHIVE, D-LISTING, M-LISTING |
| share | stroke | 4 | F-03, D-LISTING, M-LISTING, D-GUIDES |
| print | stroke | 3 | F-03, D-LISTING, D-GUIDES |
| check | stroke | 45 | almost everywhere (badges, amenities, checkboxes, lists) |
| chevron-down | stroke | 13 | selects, location pill, nav dropdowns, sort |
| chevron-right | stroke | 13 | drawer rows, S-02 rows |
| chevron-left | stroke | 3 | mobile back buttons, gallery |
| close (×) | stroke | 14 | filter chips, drawer close, Post listings "×" |
| plus | stroke | 10 | bottom-nav Post, uploader Add, FAQ closed |
| minus | stroke | 2 | FAQ open item |
| search | stroke | 13 | search fields, buttons |
| search-empty (magnifier with minus) | stroke | 2 | end-of-results, P-10 |
| filter (funnel lines) | stroke | 2 | "Filters · 5", map search |
| view-grid / view-list | stroke | 1 / 1 | archive view toggle |
| menu (hamburger) | stroke | 2 | mobile header |
| home | stroke | 3 | bottom nav, drawer |
| user | stroke | 4 | bottom nav Account, Member account type |
| map-pin-dot | stroke | 4 | location pill, "Search where you actually are" |
| map-pin | stroke | 7 | map toggle, addresses, distance |
| locate (crosshair) | stroke | 2 | "Use my current location", map control |
| phone | stroke | 7 | Contact / Call / Show phone |
| whatsapp (round chat bubble) | stroke | 6 | WhatsApp buttons. A generic bubble, **not the WhatsApp logo** |
| chat (square bubble) | stroke | 6 | Chat, Chats, contact directly |
| mail | stroke | 1 | Send an email |
| shield-check | stroke | 2 | Verified sellers, Why we ask for a NIE |
| shield | stroke | 1 | Get your verified badge |
| alert-circle | stroke | 3 | field error, upload error, login error |
| warning (triangle) | stroke | 1 | Report |
| info | stroke | 1 | conditional-field note |
| clock | stroke | 1 | A-09 awaiting approval |
| lock | stroke | 1 | T-07 401 |
| ban | stroke | 1 | T-08 403 |
| edit (pencil) | stroke | 1 | dashboard row |
| trash | stroke | 1 | dashboard row (#C0304A) |
| star-filled | fill #FF8C00 | 12 | rating pills |
| spinner | stroke (white, 2.6) | 2 | loading buttons |
| account-private-seller / -m | stroke | 1 / 1 | A-03 desktop / mobile (two different drawings) |
| account-business-seller / -m | stroke | 1 / 1 | A-03 desktop / mobile (two different drawings) |
| motorbike, boat | stroke | 1 each | item-type cards (S-04) |
| facebook, instagram | stroke | 1 each | seller card social (D-LISTING) |
| globe | stroke | 1 | seller website |
| facebook-filled, instagram-filled | fill #A3AECB | 1 each | footer |
| status-signal, status-battery | fill | 2 each | phone status-bar mock: **do not build** (viewBox 0 0 16 11 / 0 0 24 12) |

### 4.1 Category icons and tints

| Category | Icon file | Tile bg | Icon colour | Tint family |
|---|---|---|---|---|
| Services | cat-services | #E6EFFA | #0056B3 | blue |
| Properties | cat-properties | #E6EFFA | #0056B3 | blue |
| Auto / Moto / Boats | cat-auto-moto-boats | #E6EFFA | #0056B3 | blue |
| Restaurants & Nightlife | cat-restaurants-nightlife | #FCEEE7 | #A8482B | clay |
| Events | cat-events | #FCEEE7 | #A8482B | clay |
| Jobs | cat-jobs | #EDEDF4 | #3A4562 | neutral |
| Public Information | cat-public-information | #EDEDF4 | #3A4562 (drawer chevron #5F6A8A) | neutral |
| Marketplace | cat-marketplace | #FFF1E0 | #C97A16 | amber |
| Tourist Attractions | cat-tourist-attractions | #FFF1E0 | #C97A16 | amber |
| Leisure & Sport | cat-leisure-sport | #E4F4EE | #0E7A5F | green |

The mapping is consistent across F-02, D-HOME, M-HOME and M-NAV. Tiles are only 5 tint families for 10 roots, so pairs share a colour. **Inconsistency:** the M-NAV drawer draws Auto, Jobs, Marketplace and Events with different glyphs (`drawer-*` files). For a sprite, use one set (the `cat-*` set from F-02).

## 5. Image slots (`<image-slot>`, 60)

All are `shape="rect"` except avatars (`shape="circle"`). The placeholder text is the purpose hint; some give the intended source size.

| Frame | Slot (id) | Size | Ratio | Purpose |
|---|---|---|---:|---|
| F-05 | c-cs-1…4 | 446×170 | 2.62 | card image (Default/Featured/Urgent/Expired) |
| F-05 | c-cs-row | 96×72 | 1.33 | dashboard thumb |
| D-HOME | c-h1…h4 | 312×176 | 1.77 | card images (Villa, Car, Restaurant, Tradesperson) |
| D-HOME | c-t1…t5 | 266×200 | 1.33 | town photos (Torrevieja, Alicante, Benidorm, Orihuela Costa, Altea) |
| M-HOME | c-mh1 | 344×170 | 2.02 | featured card |
| M-HOME | c-mh2, c-mh3 | 110×110 | 1.00 | horizontal-card thumbs |
| M-HOME | c-mt1, c-mt2 | 150×180 | 0.83 | town photos |
| M-LOGGEDIN | c-nav-av | 38×38 ○ | 1 | user avatar |
| D-ARCHIVE | c-ar1…ar3 | 430×180 | 2.39 | card images (Plumber van, Bathroom, Pool plumbing) |
| D-ARCHIVE | c-armap | 432×180 | 2.40 | map module "Map · 9 results" |
| M-ARCHIVE | c-mar1, c-mar2 | 344×186 | 1.85 | card images |
| M-ARCHIVE | c-marmap | 346×150 | 2.31 | map module |
| M-MAP | c-map | 390×460 | 0.85 | full map "Map · Torrevieja" |
| M-MAP | c-mapcard | 100×100 | 1 | map card thumb |
| D-LISTING | c-sl1 | 657×328 | 2.00 | main gallery image "Main · 1200×760" |
| D-LISTING | c-sl2, c-sl3 | 299×160 | 1.87 | gallery (Work photo, Team + "+3 photos" veil) |
| D-LISTING | c-sl-logo | 72×72 | 1 | business logo |
| D-LISTING | c-slmap | 626×250 | 2.50 | location map |
| D-LISTING | c-rev1, c-rev2 ○ 40; c-slseller ○ 50 | | 1 | reviewer / seller avatars |
| M-LISTING | c-msl | 366×250 | 1.46 | gallery |
| M-LISTING | c-mslmap | 344×170 | 2.02 | map |
| M-LISTING | c-mrev ○ 34 | | 1 | reviewer avatar |
| M-LISTING-STATES | c-sheetseller ○ 46 | | 1 | seller avatar |
| D-AUTH-SUBMIT | c-add-prev | 238×130 | 1.83 | live-preview cover |
| D-AUTH-SUBMIT | c-add-i1, i2 | 203×152 | 1.34 | uploaded photos (4:3 tiles) |
| M-LOGIN | c-mlogin-ctx | 40×40 | 1 | listing context thumb |
| D-GUIDES | c-blog-hero | 704×280 | 2.51 | featured guide "Feature · 800×560" |
| D-GUIDES | c-blog-1…3 | 430×170 | 2.53 | guide cards |
| D-GUIDES | c-post-hero | 864×340 | 2.54 | post hero "Feature image · 1440×680" |
| D-GUIDES | c-blog-author ○ 36, c-post-author ○ 38 | | 1 | author avatars |
| D-STATIC | c-about-img | 685×300 | 2.28 | "Costa Blanca street · 1200×760" |
| D-STATIC | c-contact-map | 623×200 | 3.12 | office map |
| D-STATIC | c-403-av ○ 32 | | 1 | member avatar |
| M-GUIDES | c-mblog-hero | 346×170 | 2.04 | featured guide |
| M-GUIDES | c-mblog-1, 2 | 104×100 | 1.04 | guide thumbs |
| M-GUIDES | c-mpost-author ○ 34 | | 1 | author avatar |
| M-STATIC | c-mabout-img | 362×170 | 2.13 | about image |

Card image ratios vary (1.77 home, 2.39 archive, 2.62 F-05, 1.85–2.02 mobile). RTCL's configured "Listing card" crop is **416×270 (1.54)** and the gallery crop is **600×460 (1.30)** (LISTING-FIELDS.md §9). The design's hinted "Main · 1200×760" is 1.58. Image sizes need deciding (CSS `object-fit` + new `add_image_size`).

## 6. Copy / i18n notes

- **Language switchers**: header "EN ▾" (desktop and mobile), footer row, drawer chips. All show **8 languages: English, Español, Svenska, Română, Magyar, Deutsch, Nederlands, Français**, and About says "8 Languages". DESIGN-BRIEF.md specifies **6: EN, ES, SV, FI, RO, HU**. The design **drops Finnish (Suomi)** and **adds German, Dutch and French**. This needs a decision.
- **Hardcoded stats** (need dynamic sources or removal):
  - "1,240 listings", "318 verified", "152 categories", "35 towns" / "Costa Blanca · 35 towns" / "All 35 towns" (audit DB has **34** locations), "10 roots", "8 languages".
  - Per-root counts "48 listings · 42 categories", etc. Per the DB subtree counts, Services has 37 descendants (design says 42) and Auto/Moto/Boats has 8 (design says 9). The other roots match.
  - Town listing counts, "11 events near Torrevieja", "42 guides · updated weekly", "12 verified advisors", "Two attempts left", "1,284 views", "412 views", "Draft saved 12:41", "Resets 14 September".
  - Mega panel Services groups: "Professional / Home & Maintenance / Beauty & Automotive". The DB has 6 groups (Automotive, Beauty, Health & Care, Home & Maintenance, Professional, Repair).
- **Data shown that may not exist in the backend** (needs a source or a decision):
  - "Typical reply · Within 1 hour" / "replies within 1 hour" (response time): not an RTCL field.
  - "Open now · until 20:00", "Closes 23:30", "Open" badge, today's row in hours: derivable from RTCL `business_hours` only where that preset is on the form, and needs a timezone-aware "open now" computation.
  - Distance "0.8 km", "Within 10 km", radius chips 10/25/50 km, "Nearest" sort: need geo search (lat/lng + radius query). "Search within 25 km" also implies a count query.
  - "Comes to you" / "24/7" / "Emergency" / languages "EN · ES · SV" / "Hourly rate · negotiable · IVA included" / "Tagline: One hour, any hour" / "Area covered": map to Service form fields (Mobile Service, Emergency Service, Languages Spoken, Price Model, Tagline, City/Area). "negotiable · IVA included" has no field.
  - Rating pills + "Excellent" label + distribution bars + "88% five star": needs review-schema aggregation, and the label thresholds are undefined.
  - "Verified seller / Verified business · NIF on file", "Not verified", "Business" chip: NIE/NIF verification flag (exists per TARGET-FUNCTIONAL-SPEC) plus account type on the card.
  - "Member since March 2024", "Active listings 4", "All listings from this seller": author data (fine). Social links on the seller card (RTCL social profiles preset).
  - "Urgent" / "Ends in 2 days" and "Featured": RTCL promotions. Memberships are disabled in the current system; check that featured/urgent flags can be set at all.
  - Saved searches ("Save this search"), favourites/"Saved", chat counts ("Chats 3", "Unread"), "Views 412": needs RTCL Pro chat + favourites + a saved-search feature, which does not exist today.
  - "+3 photos", "5 photos" (max 5 images per listing is consistent with config). "Cover" photo, upload progress, 2 MB limit and JPG/PNG/WEBP: consistent with the config.
  - "Five free listings every 30 days", "Listings run 15 days", "renew in one tap", "reviewed within 48 hours", "Resets 14 September": the limits match the RTCL config (5 / 30 days, 15-day duration). The 48h SLA and one-tap renew are copy promises.
  - "Draft saved" autosave and "Save & exit" (drafts in the RTCL form) are not standard RTCL behaviour.
  - Section progress "11 / 20", "6 required fields left", "29 of this form's 49 don't apply": needs form-builder metadata.
  - Guides: author, read time ("7 min read"), topics, "Mentioned in this guide" (links to listing categories with counts), TOC.
  - "Returns to /favourites" (T-07) implies a `/favourites` route.
- Locale formats: prices use "€12,450" (comma thousands, English style) and dates use "24 August 2026" / "12 March 2026". For ES/HU/etc. these must come from localised number/date formatting, not hardcoded strings.
- Spanish terms kept in English copy: "presupuesto", "gestoría", "padrón", "IVA", "Sede Electrónica". Treat them as intentional.
- Typos and odd copy: "Cupon accepted" (an amenity, likely mirrors the existing DB value), "Listing Amenites" (DB label). "A-09 · after approval" shows the *awaiting* approval state.

## 7. Fonts

Embedded woff2 files (`extracted/assets/`). Each file is referenced by several `@font-face` rules, one per declared weight. The files are **variable fonts**, confirmed by parsing the woff2 `fvar` table.

| File | Family | Subset | Declared weights | Actual `wght` axis | unicode-range | Codepoints |
|---|---|---|---|---|---|---:|
| figtree-latin-400-800.woff2 | Figtree | latin | 400, 500, 600, 700, 800 | 300–900 | U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD | 222 |
| figtree-latin-ext-400-800.woff2 | Figtree | **latin-ext** | 400–800 | 300–900 | U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF | 136 |
| space-grotesk-latin-500-700.woff2 | Space Grotesk | latin | 500, 600, 700 | 300–700 | (latin range as above) | 230 |
| space-grotesk-latin-ext-500-700.woff2 | Space Grotesk | **latin-ext** | 500, 600, 700 | 300–700 | (latin-ext range as above) | 333 |
| space-grotesk-vietnamese-500-700.woff2 | Space Grotesk | vietnamese | 500, 600, 700 | 300–700 | U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+0300-0301, U+0303-0304, U+0308-0309, U+0323, U+0329, U+1EA0-1EF9, U+20AB | 113 |

- **latin-ext is included for both families.** The glyph probe confirms Hungarian **ő ű**, Romanian **ș ț ă** (comma-below), Swedish **å ä ö**, Spanish **ñ**, and **€**. Figtree latin-ext lacks the legacy cedilla **ţ** (U+0163); Space Grotesk has it. That is fine, because ț (comma-below) is the correct Romanian character.
- The vietnamese subset is unnecessary for this site and can be dropped.
- Space Grotesk **500 is declared but never used**. Used weights: Space Grotesk 600/700; Figtree 400/600/700, plus 800 (12× rating numerals) and 500 (1× inactive segmented label).
- File names reflect the `@font-face` weight declarations; the real axis is wider (300–900 / 300–700). For self-hosting: one variable woff2 per family per subset (latin + latin-ext) = 4 files, about 72 KB total.

## 8. Other observations

1. The intro says "Parity with A and B". This file is Direction C only.
2. **Header is inconsistent across desktop frames**: 80px full on home vs 72px compact elsewhere. Guides/static pages drop the location pill, search and Log in. There is no logged-in desktop header.
3. **No hover states** anywhere except the global `a:hover` (stale #C75B39). No disabled input, no toggle-off, no dropdown/menu open states except the mega panel and the language/location blocks in the drawer.
4. Contrast and tap targets to check at build time:
   - The accent button is ink on orange, but its pressed state switches to white on #B25E00.
   - The "See what's on" link on the orange module uses ink text with a white underline (`rgba(255,255,255,.5)`).
   - #A0A8C0 disabled text on #EDEDF4 is low contrast (normal for disabled).
   - #C6CFE8 text on the town-card gradient depends on the photo.
   - Some interactive targets are below 44px: desktop card favourite 36px, Save/Share/Print 38px, uploader remove buttons 24px.
5. The WhatsApp icon is a generic chat bubble, not the brand mark.
6. The status bar ("9:41", signal, battery) in mobile frames is device mockup chrome.
7. Inputs have no text colour set, so they render browser-default black.
8. `tokens-audit.json → componentMetrics` holds the computed metrics for all 117 buttons, 31 fields and 238 pill elements, if you need exact per-instance values.

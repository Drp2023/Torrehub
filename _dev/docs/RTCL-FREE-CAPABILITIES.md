# RTCL free 6.1.5 — what still works without Pro and the add-ons

Static code audit (2026-10-02), nothing executed. Paths: **CL** = `plugins/classified-listing/` (6.1.5), **PRO** = `plugins/classified-listing-pro/` (4.2.5), **STORE** = `plugins/classified-listing-store/` (3.2.1).
Pro is detected in exactly one place: `rtcl()->has_pro()` = `class_exists(RtclPro::class)` (CL `app/Rtcl.php:524-526`).

> **Go-live gate:** `repeater` is a Pro field type. Without Pro, a front-end edit overwrites stored repeater values with `''` (§1.3). Forms using it: **Service** (`Amenities`, `mnwpsvp9`) and **Property** (`Amenities`, `mnwrrkgf`). The theme must register `repeater` via `rtcl_fb_fields` **before** Pro is deactivated.

## 1. Form Builder (front-end React form)

### 1.1 Wiring — all free
| Item | Evidence |
|---|---|
| Bundle `rtcl-form-builder` → `assets/form-builder/form-builder.js` (ES module) | `app/Controllers/Admin/ScriptLoader.php:156-170, 71-73` |
| `rtclFB` localize (contains `hasPro`) | `ScriptLoader.php:367-391` (:372) |
| Save AJAX `rtcl_update_listing` (priv + nopriv), **no `has_pro` check** | `app/Controllers/Ajax/FormBuilderAjax.php:38, 55, 66-542` |
| Gallery/file/tags/terms/location/AI AJAX | `FormBuilderAjax.php:21-59`; AI :1649-1670 |
| `form-builder.js` never reads `hasPro`; a field renders only if its element is in `rtclFB.fields` (= `AvailableFields::get()`) | bundle grep |
| `hasPro` only in the admin editor (`form-builder/admin.js`): bulk options, bulk category, `isPro` settings, AI toggle on custom fields | admin.js |
| Without Pro, `chat` is removed from the single-layout palette | `ScriptLoader.php:1376-1383` |

### 1.2 Field types / settings
| Element / feature | Status | Evidence |
|---|---|---|
| `social_profiles`, `video_urls`, `business_hours` | Free | `AvailableFields.php:512, 571, 594`; saved `FormBuilderAjax.php:297-302, 371-384` |
| `date`, `file`, `color_picker`, `select`, `radio`, `checkbox`, `switch`, `number`, `url`, `text`, `textarea`, `custom_html`, `input_hidden` | Free | `AvailableFields.php:663-1085` |
| **`repeater`** | **Pro** (injected via `rtcl_fb_fields`) | PRO `app/Controllers/Hooks/FilterHooks.php:125, 144-180`; free filter `AvailableFields.php:1098` |
| Conditional logic (`logics`) | Free | `FBHelper.php:664-733, 1172-1193` |
| `filterable*`, `archive_view` flags | Pro *admin toggle*; stored values keep working (not stripped on save) | `ElementCustomization.php:971-1032`; `FieldSanitization.php:67-100` |
| `option_depends_on` | Pro flag | `ElementCustomization.php:908` |
| AI on custom text/textarea | Pro; title/description/excerpt AI only needs AI on | `AvailableFields.php:1405-1420` |
| Single-layout builder | Free | `FBHelper.php:38-46`; `templates/single-layout/builder.php:158-168` |
| Single-layout `chat` | Pro (template returns early) | `templates/single-layout/elements/chat.php:17-24` |
| Single-layout `store_info` | no template → `doing_it_wrong` notice (non-fatal) | `AvailableFields.php:1343` |

### 1.3 A stored form with a Pro-only element (e.g. `repeater`) in free
| Stage | Behaviour | Evidence |
|---|---|---|
| Edit data sent to React | skipped | `FBHelper.php:253` |
| React render | not rendered | bundle |
| Server validation | skipped | `FBHelper.php:1185` |
| **Save** | loops over **all** stored fields; missing value → `''` → `update_post_meta($id, $name, '')` — **data loss** | `FormBuilderAjax.php:199-202, 393-401, 484-488`; `FBHelper.php:1221-1222` |
| Single display | still rendered | `Form.php:223-240`; `templates/listing/c-fields.php` |

Fix: register `repeater` through `add_filter('rtcl_fb_fields', …)` (the bundle supports it), or strip those entries in `rtcl_fb_metadata_fields_before_save` (`FormBuilderAjax.php:449`).

## 2. Display helpers — all free
`FBField::getFormattedCustomFieldValue()` `FBField.php:212-225` · `FBHelper::getFormattedFieldHtml()` `FBHelper.php:1904-1987` · `FBHelper::isValidateCondition()` :664 · `BusinessHoursController::openStatus()` :60 / `get_business_hours()` :454 · `Listing::get_price_html()` `Listing.php:1452` · `Functions::price()` `Functions.php:798` · `FBField::isFilterable()/isListable()/isArchiveViewAble()` `FBField.php:254-277`.

## 3. Archive filtering — free
| Param | Handler |
|---|---|
| `q` | `app/Controllers/Query.php:675-682` |
| `orderby` (`date/title/price/views/rand/id/menu_order` + `-asc/-desc`) | `Query.php:584-672` |
| `rtcl_category`, `rtcl_location`, `category`, `location` | `Query.php:1184-1235` |
| `filter_category/location/tag` (ids) | `Query.php:1155-1182` |
| `filter_price`, `filters[price][min/max]` | `Query.php:740-822` |
| `filter_ad_type`, `filters[ad_type]` | `Query.php:733-738, 825-833` |
| **`cf_{name}` / `filters[{name}]` → meta_query** (stored `filterable` fields only) | **free** `Query.php:721-731, 836-1132` (gate :857) |
| Radius `center_lat`, `center_lng`, `distance` (default 30 mi) | **free** `ActionHooks.php:20, 53-76`; `GeoQuery.php:15-90`; `Options.php:2504-2512` |
| `page`, `__cat/__loc/__tag` | `Query.php:696-699, 1237-1264` |
| Entry | `pre_get_posts` → `listing_query()` `Query.php:37, 391-511, 662-704` (hook `rtcl_listing_query` :703) |

Pro only adds rating meta-queries and the classic widget's CF UI. Ajax-filter widget: free core, but its CF/directory/rating items are Pro (PRO `FilterHooks.php:189-238, 1565-1640`).

## 4. My Account — free
dashboard, listings, favourites, payments, edit-account, profile-settings (`privacy-settings`), lost-password, add-listing, logout (`Functions.php:473-487`, menu :3374-3406). `registration` only with `separate_registration_form`. payments/favourites removed when disabled (`AppliedBothEndHooks.php:239-252`). Buyers lose listings/payments/add-listing (`FilterHooks.php:54-55, 86-108`). **`chat`, `verify` = Pro** (PRO `FilterHooks.php:744-748, 659-665`). Favourites AJAX `rtcl_public_add_remove_favorites` + meta `rtcl_favourites` = free (`PublicUser.php:59, 1098-1114`).

## 5. Pro-only → theme rebuilds (and what is already free)
| Feature | Owner | Evidence |
|---|---|---|
| Chat tables `rtcl_conversations`, `rtcl_conversation_messages` | Pro migration only | PRO `app/Helpers/Installer.php:252-288` |
| User e-mail verification | Pro | PRO `AuthController.php:55-114` |
| `registered_only` seller info | Pro | PRO `Fns.php:268` |
| Mark as sold | Pro | PRO `RtclProAjax.php:18, 42-49` |
| Grid/list switcher | Pro | PRO `TemplateHooks.php:36, 600` |
| Listable fields on cards (render) | Pro (flag free) | PRO `TemplateHooks.php:69, 130` |
| PhotoSwipe | Pro | PRO `ScriptController.php:38` |
| Online status | Pro | PRO `ActionHooks.php:49-57` |
| Review rating on comments | **logic free, hooks Pro** (`Comments.php:166-213, 382-447`; hooked by PRO `CommentController.php:16-39`) | |
| `_rtcl_average_rating` | free | `Listing.php:1398-1402` |
| Related listings, `featured` + Featured/New badges, `_views` counter, phone reveal, contact seller e-mail, report abuse | **free** | `Listing.php:1726-1777, 475-517`; `PageController.php:17, 81-86`; `PublicUser.php:63-90, 983-986, 1071` |
| `_top`, `_bump_up`, Popular/Top badges | Pro | PRO `FilterHooks.php:819-834` |
| `_urgent` | not found anywhere | — |
| Chat link in seller box | Pro | PRO `TemplateHooks.php:77-81` |

## 6. Store
`rtcl_posting_log` only created by STORE (`Install.php:220-236`). Quota/membership = Store only. Free only increments user meta `_rtcl_ads` (`FormBuilderAjax.php:499-501`). Quota hook point for the theme: `rtcl_fb_extra_form_validation` (`FormBuilderAjax.php:124`).

## 7. Auth in free — to disable/replace
- POST handlers on `wp_loaded`: `FormHandler::process_login/registration/lost_password/reset_password` (`FormHandler.php:17-20`) → `remove_action('wp_loaded', …, 20)`.
- AJAX nopriv `rtcl_login_request`, `rtcl_registration_request` (`PublicUser.php:74-75`).
- `lostpassword_url` filter (`PageController.php:15`; `Link.php:54-75`) → remove.
- Logged-out my-account → `myaccount/form-login` (`MyAccount.php:35-68`); other login forms via `Functions::login_form()` + `rtcl_login_form_template_path` (`Functions.php:3361-3371`).
- URL filters: `rtcl_get_myaccount_page_permalink`, `rtcl_get_account_endpoint_url`, `rtcl_get_endpoint_url` (`Link.php:47-143`).
- Account settings read by free: `enable_myaccount_registration`, `separate_registration_form`, `user_role`, `disable_name_phone_registration`.

## 8. E-mails sent by free (stay)
Listing submitted (owner/admin), published, updated (admin), expired/renewal/reminder (cron), moderation note, contact seller (owner/admin), report abuse, new user (admin/user — fires from RTCL registration only), reset password, orders. Registry: `app/Models/RtclEmails.php:73-95`.

## 9. Unguarded Pro/Store calls in free
None found (all behind `has_pro()` / `class_exists`). Toolkits (34 files) and cldirectory-core (4 files) reference Pro/Store — not audited; both are removed anyway.

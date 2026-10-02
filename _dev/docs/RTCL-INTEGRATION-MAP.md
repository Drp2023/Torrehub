# RTCL Integration Map: new classic PHP theme ↔ installed plugins

**Status:** CONFIRMED from plugin source (read-only audit, 2026-10-02).
**Scope:** classified-listing 6.1.5 · classified-listing-pro 4.2.5 · classified-listing-store 3.2.1 · classified-listing-toolkits 1.3.2 · rtcl-search-alert 1.2.0 · rtcl-seller-verification 1.2.1 · rtcl-verification 1.6.0 · rtcl-elementor-builder 3.2.0 · review-schema 3.1.1 · review-schema-pro 2.0.1 · fluentform 6.2.15 · gtranslate 5.0.1 · cldirectory-core 3.1.2 · rt-framework 2.9. WordPress 7.1.2, PHP 8.2.

**Path abbreviations.** All paths are relative to `wp-content/`.

| Abbreviation | Path |
|---|---|
| `CL/` | `plugins/classified-listing/` |
| `PRO/` | `plugins/classified-listing-pro/` |
| `STORE/` | `plugins/classified-listing-store/` |
| `SA/` | `plugins/rtcl-search-alert/` |
| `SV/` | `plugins/rtcl-seller-verification/` |
| `VER/` | `plugins/rtcl-verification/` |
| `ELB/` | `plugins/rtcl-elementor-builder/` |
| `TK/` | `plugins/classified-listing-toolkits/` |
| `RS/` | `plugins/review-schema/` |
| `RSP/` | `plugins/review-schema-pro/` |
| `FF/` | `plugins/fluentform/` |
| `GT/` | `plugins/gtranslate/` |
| `CORE/` | `plugins/cldirectory-core/` |
| `OLD/` | `themes/cldirectory/` |

**Markers.** **CONFIRMED** means verified in code at the cited `file:line`. **NOT FOUND** means searched for and absent. **DB** means the value comes from the production option export (`_dev/audit-data/06-rtcl-options.json`, `09-rtcl-forms.json`) and is not a plugin default.

> ⚠ The options export contains live secrets: the Google Maps API key, the Pusher app key/secret and the MaxMind license key. They are **not** reproduced here. Never commit that export to the theme repo, and consider rotating the Pusher secret.

---

## 0. TL;DR for the theme developer

1. **Override folder: `your-theme/classified-listing/`**, for core, Pro, Store, Seller Verification and Toolkits alike. Only two things use other locations:
   - the review-schema templates, which go in `your-theme/review-schema/`;
   - the search-alert *email*, which goes in `child-or-active-theme/rtcl-search-alert/emails/`.
2. **Declare `add_theme_support( 'rtcl' )`.**
   - With it, RTCL's `template_include` loader is used, plus the Pro `comments_template` loader.
   - Without it, RTCL falls back to "unsupported theme" shortcode injection.
   - The DB option `rtcl_advanced_settings.template_base = rtcl_template` also enables this. The old theme declares it anyway.
3. **The Form Builder is ON** (`rtcl_fb_options.active = 1`, DB).
   - The add/edit form is a **React app** (`rtcl-form-builder` ES module). PHP renders only an empty mount `<div>`. You can restyle it with CSS, but you cannot template it in PHP.
   - Custom field values are stored as post meta **named exactly like the field `name`** (e.g. `select_mo47kwc1`).
4. **rtcl-elementor-builder hijacks every single listing.**
   - It does this through `template_include` at priority 100 whenever `rtcl_tb_template_default_single_{formId}` points at a published `rtcl_builder` post.
   - All 10 forms have such a mapping (DB).
   - So the new theme's `single-rtcl_listing.php` will **never load** unless the plugin is deactivated, or that filter/option is neutralised (see §2.4).
5. **Archive GET params** (confirmed):
   - Search and taxonomy: `q`, `rtcl_category`, `rtcl_location` (slugs; pretty URL `/{listings-page}/listing-category/{cat}/listing-location/{loc}/` via `__cat`/`__loc`).
   - Geo: `geo_address`, `center_lat`, `center_lng`, `distance`.
   - Filters: `filters[price][min|max]`, `filters[ad_type]`, `filters[{field_name}]` (`[min|max]` for numbers, `[]` for choices).
   - AJAX-filter style: `cf_{field_name}=a,b`, `filter_category`, `filter_location`, `filter_tag`, `filter_price`, `filter_ad_type`, `directory`.
   - Display: `orderby=date-desc|price-asc|…`, `view=grid|list`, `page`.
6. **Swiper is only needed by the single gallery.** It is loaded by `rtcl-single-listing`, which (with Pro) also pulls `photoswipe-ui-default` and `zoom`. If you replace the gallery, you can dequeue `rtcl-single-listing` and `swiper`. Phone reveal etc. live in `rtcl-public`.
7. **Maps:**
   - `map_type = google` (DB). `rtcl-google-map` (Maps JS v3, `libraries=geometry,places`) is loaded synchronously.
   - It is enqueued only on single listing, listing form and edit-account pages, through handle `rtcl-map` (`gmap.js`).
   - It can be lazy-loaded (see §5.4).
8. **Chat:** Pusher is enabled (`pusher_enable = yes`, DB). Handles are `pusher-js`, `rtcl-chat` (single listing), `rtcl-user-chat` (account `chat` endpoint) and `rtcl-pro-public`. The JS also polls via `setInterval` every `refresh_interval` (20 s).
9. **The free-ads quota (5 per 30 days) is NOT enforced** right now.
   - It lives in the Store plugin and only runs when `rtcl_membership_settings.enable === 'yes'`. In the DB it is `''`.
   - When enabled, count rows in `{prefix}rtcl_posting_log` from the last N days.
10. **Currently inactive account endpoints** (DB):
    - `favourites`: `rtcl_general_settings.has_favourites` is `''`.
    - `payments`: `rtcl_payment_settings.payment` is `''`.
    - `store`: `enable_store` is `''`.
    - The favourite button is hidden too.

---

## 1. Template override mechanism

### 1.1 Core functions (classified-listing 6.1.5)

| Item | Status | Evidence |
|---|---|---|
| Theme folder name `classified-listing/` | CONFIRMED | `CL/app/Rtcl.php:368-369`: `get_template_path()` returns `apply_filters('rtcl_template_path','classified-listing/')` |
| `Functions::locate_template( $name, $template_path='', $default_path='' )` | CONFIRMED | `CL/app/Helpers/Functions.php:3451-3483`. Appends `.php`. Theme lookup via WP `locate_template()` of `classified-listing/{name}.php` (3472-3475); falls back to `$default_path` or `CL/templates/` (3477-3480). |
| Legacy names `listings/…` and `listings/single/…` mapped to `listing/…` | CONFIRMED | `Functions.php:3462-3471` |
| `Functions::get_template( $name, $args, $template_path, $default_path )` | CONFIRMED | `Functions.php:3545-3567`. `extract($args)`; fires `rtcl_before_template_part` / `rtcl_after_template_part` |
| `Functions::get_template_html()` (buffered) | CONFIRMED | `Functions.php:3579-3584` |
| `Functions::get_template_part( $slug, $name )` | CONFIRMED | `Functions.php:3494-3534`. Looks in `{slug}-{name}.php` and `classified-listing/{slug}-{name}.php` (theme), then the plugin, then `{slug}.php`. Result is cached in object cache group `rtcl`. |
| Filter `rtcl_template_path` | CONFIRMED | `Rtcl.php:369` |
| Filter `rtcl_locate_template_files` (candidate list) | CONFIRMED | `Functions.php:3475` |
| Filter `rtcl_locate_template` ($template, $name) | CONFIRMED | `Functions.php:3482` |
| Filter `rtcl_get_template` ($located, $name, $args) | CONFIRMED | `Functions.php:3560` |
| Filter `rtcl_get_template_part` | CONFIRMED | `Functions.php:3530` |
| `RTCL_TEMPLATE_DEBUG_MODE` disables theme overrides | CONFIRMED | `Functions.php:3478`, `Rtcl.php:579-580` |
| A function named `rtcl_get_template()` | NOT FOUND | Only static `Functions::` methods exist |

**Page-level loader (`template_include`).** Class `Rtcl\Controllers\Hooks\TemplateLoader` (`CL/app/Controllers/Hooks/TemplateLoader.php`):
- Activation: `init()` (35-55). With a block theme it delegates to `BlockTemplateController`. When theme support is on (`Functions::is_enable_template_support()`), it hooks `template_include` at priority 99, or at default priority when Elementor Pro or Divi is present (lines 46-49). Otherwise it uses `unsupported_theme_init` (53).
- Theme support check: `is_enable_template_support()` returns `current_theme_supports('rtcl') || get_base_template()==='rtcl_template'` (`CL/app/Traits/Functions/TemplateTrait.php:90-92`). `get_base_template()` reads `rtcl_advanced_settings.template_base` (`CL/app/Traits/Functions/SettingsTrait.php:48-52`).
- Default file (88-111):
  - `single-rtcl_listing.php`
  - `taxonomy-rtcl_category.php`, `taxonomy-rtcl_location.php`, `taxonomy-rtcl_tag.php`
  - `archive-rtcl_listing.php`, used for the CPT archive **and the Listings page**
  - `author-rtcl_listing.php`, used for `is_author()`
  - Filter: `rtcl_template_loader_default_file`
- Search list (113-137): `{file}`, `classified-listing/{file}`, `classified-listing/listings/{file}`. Filter: `rtcl_template_loader_files`; fallback filter `rtcl_template_loader_fallback_file` (80).
  - **Bug:** lines 119-126 build `single-rtcl_listing-{slug}.php` candidates, but line 128 then *reassigns* `$templates`, so slug-specific single templates are **not** honoured.
- RTCL also hooks `template_include` at 99 for its own page templates (`FilterHooks::assign_page_template`, `CL/app/Controllers/Hooks/FilterHooks.php:41, 232-…`). That only applies when a page has an RTCL page template selected.

### 1.2 How Pro / add-ons register template paths

All of them call `Functions::get_template( $name, $args, '' /*theme path = classified-listing/*/, $plugin_templates_dir )`. **So theme overrides always go in `your-theme/classified-listing/<same relative path>`.** There is no sub-folder per add-on.

| Plugin | Default path accessor | Theme override location | Evidence |
|---|---|---|---|
| Pro | `rtclPro()->get_plugin_template_path()` returns `PRO/templates/` | `classified-listing/…` | `PRO/app/RtclPro.php:201-203`; e.g. `PRO/app/Controllers/Hooks/TemplateHooks.php:294`, `ScriptController.php:146` |
| Pro reviews (`comments_template`) | own loader | `single-rtcl_listing-reviews.php` or `classified-listing/single-rtcl_listing-reviews.php` | `PRO/app/Controllers/Hooks/TemplateLoader.php:18, 54-75` |
| Store | `rtclStore()->get_plugin_template_path()` plus filters on `rtcl_locate_template` / `rtcl_get_template_part` | `classified-listing/…` | `STORE/app/RtclStore.php:147-148`; `STORE/app/Controllers/Hooks/RtclApplyHook.php:21-22, 1243-1276` |
| Store page loader | own `template_include` | `classified-listing/archive-store.php`, `single-store.php`, `taxonomy-store_category.php`. **A theme-root `rtcl.php` captures store pages.** | `STORE/app/Controllers/Hooks/TemplateLoader.php:43, 56-132` (105 for `rtcl.php`) |
| Seller Verification | `rtclSellerVerification()->get_plugin_template_path()` | `classified-listing/myaccount/my-documents.php`, `classified-listing/emails/seller-document-email.php` | `SV/inc/init.php:158-159`; `SV/inc/hooks/RtclSellerActionHooks.php:16` |
| Search Alert (account page) | `RtclSearchHelper::get_template_path()` | `classified-listing/myaccount/search-alert.php` | `SA/includes/Hooks/TemplateHook.php:20`; `SA/includes/Helpers/RtclSearchHelper.php:438-439` |
| Search Alert (email) | own loader, **stylesheet dir only** | `{child}/rtcl-search-alert/emails/search-alert.php` | `SA/includes/Hooks/TemplateLoader.php:9-29` |
| Toolkits (Elementor/Divi) | `Helper::get_plugin_template_path()` | `classified-listing/elementor/…`, `classified-listing/divi/…` | `TK/includes/Hooks/Helper.php:11-12` |
| rtcl-verification | no templates (inline echo) | none | NOT FOUND (no `templates/` dir) |
| review-schema (+pro) | `Rtrs Functions::get_template_part()` | `your-theme/{slug}.php` **or** `your-theme/review-schema/{slug}.php` | `RS/app/Helpers/Functions.php:225-262`; `RS/app/Rtrs.php:174-176` (`rtrs_template_path`) |

**Watch out.** Store's `widgets/search/inline.php` and `vertical.php` share paths with core's `CL/templates/widgets/search/*`. A single theme override will therefore hit both.

### 1.3 How the old cldirectory theme does it

- It declares `add_theme_support('rtcl')` (`OLD/classified-listing/custom/functions.php:180`). CONFIRMED.
- Override folder: `OLD/classified-listing/` holds 77 files (58 real RTCL/Pro/Store overrides and 19 theme-only partials under `custom/`). The child theme `cldirectory-child` has **no** overrides; it is a 10-line stub that only enqueues `style.css` and the text domain.
- `OLD/review-schema/` contains `reviews.php`, `review/layout-three.php` and `summary/layout-one.php`.
- `OLD/woocommerce/checkout/form-checkout.php` exists.
- The old theme also hooks `template_include` (`custom/functions.php:286, 818-834`). It swaps the **My Account page** for `classified-listing/custom/listing-account.php` and the **listing-form page** for `custom/listing-form.php`.
- Bootstrap: `inc/includes.php:33-41` loads `custom/functions.php` (class `Listing_Functions`), `inc/data-migration.php` and `inc/shortcode.php` when `class_exists('Rtcl')`.

---

## 2. Templates to override (exact relative paths, 6.1.5 / Pro 4.2.5 / Store 3.2.1)

✅ means the old theme overrides this file (`OLD/classified-listing/…`). Unless marked [PRO], [STORE], [SA] or [SV], a file is in `CL/templates/`.

### 2.1 Archive / loop

| Template | Old theme | Notes |
|---|---|---|
| `archive-rtcl_listing.php` | ✅ | Hooks (CL/templates/archive-rtcl_listing.php):<br>• `rtcl_before_content_wrapper` (23), `rtcl_archive_description` (38), `rtcl_before_main_content` (59), `rtcl_before_listing_loop` (67)<br>• `Functions::listing_loop_start()` (70), `rtcl_listing_loop_prepend_data` (75), `rtcl_listing_loop` (84), `get_template_part('content','listing')` (86), `listing_loop_end()` (91)<br>• `rtcl_no_listings_found` (99), `rtcl_after_listing_loop` (107), `rtcl_after_main_content` (114), `rtcl_sidebar` (121), `rtcl_after_content_wrapper` (124) |
| `taxonomy-rtcl_category.php`, `taxonomy-rtcl_location.php`, `taxonomy-rtcl_tag.php` | – | Thin wrappers around the archive |
| `author-rtcl_listing.php` | ✅ | |
| `content-listing.php` | ✅ | Card. Root is `<div class="rtcl-listing-card …">` built by `Functions::listing_class()` plus `listing_data_attr_options()` (11). Hooks: `rtcl_before_listing_loop_item` (18), `rtcl_listing_loop_item_start` (25), `rtcl_listing_loop_item` (41), `rtcl_listing_loop_item_end` (49), `rtcl_after_listing_loop_item` (57) |
| `listing/loop/loop-start.php`, `listing/loop/loop-end.php` | – | `loop-start` uses `Functions::listing_loop_start_class()` |
| `listing/loop/actions.php` | – | Wrapper firing `rtcl_listing_loop_action` |
| `listing/loop/result-count.php` | ✅ | |
| `listing/loop/orderby.php` | ✅ | |
| `listing/loop/pagination.php`, `global/pagination.php` | ✅ ✅ | |
| `listing/loop/thumbnail.php` | ✅ | |
| `listing/loop/price.php` | – | |
| `listing/loop/no-listings-found.php` | – | |
| `listing/view-switcher.php` [PRO] | ✅ | Grid/list switch; anchors `.rtcl-view-trigger[data-type]` |
| `listing/listable-fields.php` [PRO], `listing/listable.php` [PRO] | ✅ (`listable-fields`) | Form Builder fields with `archive_view` |
| `listing/badges.php`, `listing/labels.php`, `listing/meta.php`, `listing/meta-buttons.php`, `listing/actions.php` | `meta` ✅ | |
| `listing/quick-view.php` [PRO], `compare/*.php` [PRO] | – | Quick view and compare are **disabled** (DB) |
| `global/wrapper-start.php`, `global/wrapper-end.php`, `global/breadcrumb.php`, `global/sidebar.php` | `wrapper-*` ✅ | |
| `widgets/filter.php`, `widgets/ajax-filter.php`, `widgets/ajax-filter-result.php`, `widgets/search/inline.php`, `widgets/search/vertical.php`, `widgets/search.php` [PRO], `widgets/listings.php`, `widgets/listings-map.php` [PRO], `widgets/categories.php` | `widgets/listings.php` ✅ | |
| `categories/categories-grid.php`, `categories/categories-list.php` | – | `[rtcl_categories]` |

### 2.2 Single listing

| Template | Old theme | Notes |
|---|---|---|
| `single-rtcl_listing.php` | ✅ | Picks the **single-layout builder** when `FBHelper::isEnableSingleBuilder($listing)` is true, else `content-single-rtcl_listing` (`CL/templates/single-rtcl_listing.php:38-50`). All 10 DB forms have `single_layout.settings.active=1`. |
| `content-single-rtcl_listing.php` | ✅ | Hooks (file:line): `rtcl_before_single_listing` 31, `rtcl_single_listing_content` 37, price via `$listing->get_price_html()` 44, `rtcl_single_listing_before_content` 48, description 51, `rtcl_single_listing_sidebar` 55/80, `rtcl_single_listing_inner_sidebar` 58, `rtcl_single_listing_content_end` (map) 64, `rtcl_single_listing_business_hours` 67, `rtcl_single_listing_social_profiles` 70, related 73, `rtcl_single_listing_review` 76, `rtcl_after_single_listing` 85 |
| `single-layout/builder.php` | ✅ | Form Builder single layout (rows → columns → sections → containers → fields) |
| `single-layout/render-element.php` | – | Dispatcher to `single-layout/elements/{element}.php` |
| `single-layout/elements/*.php` (45 files: address, author-info, booking, business-hours, category, chat, checkbox, color-picker, contact-to-seller, date, description, email, excerpt, file, html, images, listing-actions, listing-contact, listing-header, listing-meta, listing-type, location, map, number, phone, pricing, radio, repeater, select, shortcode, social-profiles, spacer, switch, tag, telegram, text, textarea, title, url, video-urls, website, whatsapp, zipcode) | `date.php`, `repeater.php` ✅ | |
| `listing/gallery.php` | ✅ | Swiper markup: `#rtcl-slider-wrapper.rtcl-slider-wrapper > .rtcl-slider > .swiper-wrapper > .swiper-slide.rtcl-slider-item`; thumbs `.rtcl-slider-nav` (`CL/templates/listing/gallery.php:31-90`) |
| `listing/photoswipe.php` [PRO] | – | Printed in `wp_footer` on singles (`PRO/app/Controllers/ScriptController.php:135-147`) |
| `listing/meta.php` | ✅ | |
| `listing/c-fields.php` | ✅ | Form Builder custom fields "Overview" block (classic path) |
| `listing/custom-fields.php` | – | Legacy `rtcl_cf` fields (Form Builder off) |
| `listing/listing-sidebar.php` | ✅ | Seller box, which fires `rtcl_listing_seller_information` |
| `listing/user-information.php`, `listing/author-content.php`, `listing/author-listing.php` | ✅ ✅ ✅ | |
| `listing/email-to-seller-form.php` | ✅ | Contact seller form (`#rtcl-contact-form`) |
| `listing/map.php`, `listing/map-content.php` | – | `.rtcl-map[data-options] > .marker[data-latitude][data-longitude][data-address]` (`CL/templates/listing/map.php`) |
| `listing/business-hours.php` | ✅ | |
| `listing/social-profiles.php`, `listing/social-share.php` | `social-profiles` ✅ | |
| `listing/related-listings.php` | (`related-listings-dump.php`) | |
| `single-rtcl_listing-reviews.php` [PRO] | ✅ | Only used when review-schema is **not** handling `rtcl_listing` (see §7) |
| `listing/review.php`, `listing/review-meta.php`, `listing/review-rating.php` [PRO] | `review.php` ✅ | |
| `single-listing-restricted.php` | – | Pro "logged-in only" details (`single_listing_logged_in`) |

### 2.3 Listing form (add/edit)

- **With the Form Builder ON (current):** `listing-form/form-builder.php` holds only the React mount point (see §4.2).
- **Legacy (Form Builder OFF), used by the old theme:** `listing-form/form.php` ✅, `category.php` ✅, `category-section.php`, `information.php` ✅, `custom-field.php`, `gallery.php`, `contact.php`, `business-hours.php`, `social-profiles.php`, `video-urls.php`, `price-unit.php`, `recaptcha.php`, `terms-conditions.php`.
- The old theme's `listing-form/*` overrides are **dead code** while the Form Builder is on (`CL/app/Shortcodes/ListingForm.php:46-50`).

### 2.4 rtcl-elementor-builder (single/archive hijack)

`ELB/app/Controllers/Builder/TemplateBuilderFrontend.php:33, 44-48` returns `ELB/templates/elementor/listing-fullwidth.php` from `template_include` at **priority 100**. It does this when:
- `is_builder_page_single()`: the option `rtcl_tb_template_default_single_{_rtcl_form_id}` points to a published `rtcl_builder` post (`ELB/app/Traits/ELTempleateBuilderTraits.php:40-56, 78-84, 163-165`); or
- the same holds for `archive` or `store-single`.

DB mapping (option → `rtcl_builder` post ID): `_2→5409` (Post a job single), `_3→6746`, `_4→6735`, `_5→6662`, `_6→6730`, `_7→6725`, `_8→6717`, `_9→6712`, `_10→6703`, `_11→6688`.

**Theme action required.** Do one of the following:
- (a) Deactivate `rtcl-elementor-builder`.
- (b) In the theme run `remove_filter('template_include', ['RtclElb\Controllers\Builder\TemplateBuilderFrontend','el_template_loader_default_file'], 100)` on `wp` (after the plugin init). Verify the exact namespace with `class_exists()` first.
- (c) Filter `pre_option_rtcl_tb_template_default_single_{2..11}` to `0`.

### 2.5 My Account

| Template | Old theme | Notes |
|---|---|---|
| `myaccount/my-account.php` | ✅ | Fires `rtcl_account_navigation` and `rtcl_account_content` |
| `myaccount/navigation.php` | ✅ | Uses `Functions::get_account_menu_items()` and `get_account_menu_item_classes()` |
| `myaccount/dashboard.php`, `myaccount/user-info.php` | – | Dashboard. Hooks: `rtcl_account_dashboard` (user_information) and `rtcl_account_dashboard_report` (Pro subscription 20, Store membership stats) |
| `myaccount/my-listings.php`, `myaccount/my-listings-table.php` | – | `listings` endpoint (`CL/app/Shortcodes/MyAccount.php:143-194`) |
| `myaccount/favourite-listings.php` | – | `favourites` endpoint (**disabled**, DB) |
| `myaccount/payment-history.php`, `myaccount/popup-pricing-info.php` | – | `payments` endpoint (**disabled**, DB) |
| `myaccount/form-edit-account.php` | – | `edit-account` (`MyAccount.php:197-218`) |
| `myaccount/profile-settings.php` | – | key `profile-settings`, slug `privacy-settings` (DB) |
| `myaccount/form-login.php`, `form-registration.php`, `form-lost-password.php`, `form-reset-password.php`, `lost-password-confirmation.php`, `terms-conditions.php`, `global/form-login.php`, `global/email-login-form.php` | – | Logged-out views (`MyAccount.php:67, 231-266`) |
| `myaccount/chat-conversation.php` [PRO] | – | `chat` endpoint (`PRO/app/Controllers/Hooks/TemplateHooks.php:293-294`) |
| `myaccount/subscription-report.php` [PRO] | – | |
| `myaccount/search-alert.php` [SA] | – | `search-alert` endpoint |
| `myaccount/my-documents.php` [SV] | – | `my-documents` endpoint |
| `myaccount/store.php`, `membership-statistic.php`, `popup-membership-pricing-info.php`, `store-manager-action.php` [STORE] | – | Inactive (store and membership are off) |
| `checkout/*.php` (core 14 files) and Store `checkout/membership*.php` | `checkout/membership-promotions.php`, `payment-method.php` ✅ | |
| `notices/error.php`, `notice.php`, `success.php` | – | |

### 2.6 Search Alert / Seller Verification / Store

- **SA:** `myaccount/search-alert.php` (live) and `search-alert/search-alert.php` (unused; its hook is commented out at `SA/includes/Hooks/TemplateHook.php:14`). The email template `emails/search-alert.php` uses its own path.
- **SV:** `myaccount/my-documents.php` and `emails/seller-document-email.php`.
- **STORE (for later):**
  - Root: `archive-store.php` ✅, `single-store.php` ✅, `content-store.php` ✅, `content-single-store.php`, `taxonomy-store_category.php`
  - `store/`: `ad-listing.php` ✅, `contact-form.php`, `details-modal.php` ✅, `expired-content.php`, `sidebar.php`, `single-store.php`, `social-media.php`, `membership-pricing-table.php`, `store-link-to-user-information.php`
  - `store/loop/`: `actions.php`, `loop-start.php`, `loop-end.php`, `no-stores-found.php`, `thumbnail.php` ✅
  - Emails: `emails/store-*.php`
- **Old theme extras (not RTCL templates):** `buy-button.php` (RtclMarketplace) and `claim/claim-popup-form.php` (RtclClaimListing). **Neither plugin is installed.**

---

## 3. Action / filter hooks

### 3.1 Archive / loop hooks (default callbacks)

Core callbacks are registered in `CL/app/Controllers/Hooks/TemplateHooks.php` `init()`. Pro callbacks are in `PRO/app/Controllers/Hooks/TemplateHooks.php`.

| Hook | Priority → callback | Evidence |
|---|---|---|
| `rtcl_before_content_wrapper` | 1 → `container_start` | TH:64 |
| `rtcl_after_content_wrapper` | 100 → `container_end` | TH:65 |
| `rtcl_before_main_content` | 6 → `breadcrumb`; 7 → `output_all_notices`; 8 → `output_main_wrapper_start`; 10 → `output_content_wrapper` | TH:66-68, 105 |
| `rtcl_after_main_content` | 10 → `output_content_wrapper_end` | TH:69 |
| `rtcl_sidebar` | 10 → `get_sidebar`; 15 → `output_main_wrapper_end` | TH:76-77 |
| `rtcl_archive_description` | 10 → `taxonomy_archive_description`, `listing_archive_description` | TH:79-80 |
| `rtcl_before_listing_loop` | 20 → `listing_actions` (template `listing/loop/actions`) | TH:82, 1719 |
| `rtcl_listing_loop_action` | 10 → `result_count`; 20 → `catalog_ordering`; 30 → Pro `view_switcher` | TH:83-84; PRO TH:34 |
| `rtcl_listing_loop_prepend_data` | 20 → Pro `top_listing_items` (if top listings enabled) | PRO TH:37 |
| `rtcl_no_listings_found`, `rtcl_shortcode_listings_loop_no_results` | → `no_listings_found` | TH:85-86 |
| `rtcl_listing_loop_item_start` | 10 → `listing_thumbnail` | TH:88, 1865 |
| `rtcl_after_listing_thumbnail` | 10 → `loop_item_meta_buttons` | TH:55, 1820 |
| `rtcl_listing_meta_buttons` | 10 → `add_favourite_button` (only if `has_favourites`); 20 → Pro quick view (if enabled); 30 → Pro compare (if enabled) | TH:203, 998-1008; PRO TH:65, 68 |
| `rtcl_listing_loop_item` | 10 → `loop_item_wrapper_start`; 20 → `loop_item_listing_title`; 30 → `loop_item_badges`; 40 → Pro `loop_item_listable_fields`; 50 → `loop_item_meta`; 70 → `loop_item_excerpt`; 80 → `listing_price`; 100 → `loop_item_wrapper_end` | TH:91-98; PRO TH:71 |
| `rtcl_listing_badges` | 10 → new; 20 → featured; 30 → Pro popular; 40 → Pro top; 50 → Pro bump-up | TH:185-186; PRO TH:41-43 |
| `rtcl_after_listing_loop_item` | 10 → Pro `sold_out_banner` | PRO TH:20 |
| `rtcl_after_listing_loop` | 10 → `pagination`; 20 → Pro `remove_top_listing_items` | TH:100; PRO TH:38 |

**Old theme changes** (`OLD/classified-listing/custom/functions.php`, all CONFIRMED):
- Removed: `loop_item_listing_title` (20), `loop_item_meta` (50), `loop_item_excerpt` (70) and `listing_price` (80) on `rtcl_listing_loop_item` (558-561). They are replaced by theme versions plus `loop_item_footer` at 80 (563-566).
- Removed: `breadcrumb` (6) and `output_main_wrapper_start` (8) on `rtcl_before_main_content` (547-548); `output_content_wrapper_end` (549); `output_main_wrapper_end` (550); Pro `sold_out_banner` (546); `listing_form_submit_button` (552); Pro `my_listing_mark_as_sold_button` (555).
- Filters: `rtcl_loop_listing_per_page` (186), `rtcl_listings_grid_columns_class` (209), `rtcl_related_slider_options` (200, 239), `rtcl_text_add_to_favourite` / `rtcl_text_remove_from_favourite` / `rtcl_text_report_abuse` returning `''` (249/258/267), `rtcl_get_listing_display_options` (289), `rtcl_get_listing_detail_page_display_options` (290), `rtcl_bootstrap_dequeue` returning false (295).
- `rtcl_map_localized_options` hard-codes the cluster centre to 39.76,-104.02 with zoom 4 (53). This is a demo leftover; do not port it.

### 3.2 Single listing hooks

| Hook | Priority → callback | Evidence |
|---|---|---|
| `rtcl_single_listing_content` | 5 → `add_single_listing_title`; 10 → `add_single_listing_meta`; 20 → Pro `sold_out_banner`; 30 → `add_single_listing_gallery` | TH:124-126; PRO TH:21 |
| `rtcl_single_listing_content_end` | 10 → `single_listing_map_content` (returns early if the form has no `map` field) | TH:127, 1655-1690 |
| `rtcl_single_listing_review` | 10 → `add_single_listing_review`, which runs `comments_template()` only with theme support and comments open or present | TH:129, 1620-1623 |
| `rtcl_single_listing_sidebar` | 10 → `add_single_listing_sidebar` (template `listing/listing-sidebar`) | TH:130 |
| `rtcl_single_listing_inner_sidebar` | 10 → `add_single_listing_inner_sidebar_custom_field`, which calls `$listing->custom_fields()` when the Form Builder is on and `the_custom_fields()` otherwise; 20 → `add_single_listing_inner_sidebar_action` | TH:131-135, 1630-1637 |
| `rtcl_single_listing_business_hours` | → `BusinessHoursController::display_business_hours` (Form Builder: only if the form has a `business_hours` field) | `CL/app/Controllers/BusinessHoursController.php:41, 49, 497-…` |
| `rtcl_single_listing_social_profiles` | → `SocialProfilesController::display_social_profiles` | `CL/app/Controllers/SocialProfilesController.php:27, 32` |
| `rtcl_listing_seller_information` | 1 → Pro login link (replaces **all** others for guests when `registered_only` contains `listing_seller_information`, DB = yes); 5 → SV verified badge; 8 → `author_information`; 10 → `seller_location`; 20 → `seller_phone_whatsapp_number`; 25 → `seller_telegram`; 30 → `seller_email`; 40 → Pro `add_chat_link`; 50 → Pro `add_user_online_status` and core `seller_website` | TH:213-218; PRO TH:81-87; `SV/inc/hooks/RtclSellerActionHooks.php:10` |
| `rtcl_after_author_meta` | SV "Verified" badge `span.rtcl-sv-sign` | `SV/inc/hooks/RtclSellerActionHooks.php:9, 19-31` |
| `comments_template` | Pro 10 → `single-rtcl_listing-reviews.php`; review-schema 99 → `reviews.php` (wins) | `PRO/app/Controllers/Hooks/TemplateLoader.php:18`; `RS/app/Modules/Review/Hooks/ReviewFrontend.php:51` |

### 3.3 My Account: endpoints and menu

- **Endpoint map:** `Functions::get_my_account_page_endpoints()` (`CL/app/Helpers/Functions.php:473-486`) reads its option keys from `rtcl_advanced_settings`, then applies the filter `rtcl_my_account_endpoint`.

| Key | Option key | DB slug |
|---|---|---|
| `listings` | `myaccount_listings_endpoint` | `listings` |
| `favourites` | `myaccount_favourites_endpoint` | `favourites` |
| `payments` | `myaccount_payments_endpoint` | `payments` |
| `profile-settings` | `myaccount_profile_settings_endpoint` | `privacy-settings` |
| `edit-account` | `myaccount_edit_account_endpoint` | `edit-account` |
| `lost-password` | `myaccount_lost_password_endpoint` | `lost-password` |
| `add-listing` | (a URL, not an endpoint) | |
| `logout` | `myaccount_logout_endpoint` | `logout` |

- **Endpoints added by filters:**
  - `registration`: `myaccount_registration_endpoint`, only when the registration form is separate (`FilterHooks.php:35, 535-538`).
  - Pro `chat`: `myaccount_chat_endpoint` (`PRO/app/Controllers/Hooks/FilterHooks.php:87, 744-747`).
  - Pro `verify`: `myaccount_verify`, when `rtcl_account_settings.user_verification` is set (`PRO FilterHooks:97, 659-665`).
  - SA `search-alert`: hard-coded (`SA/includes/Hooks/Common.php:35, 119-120`).
  - SV `my-documents`: `myaccount_documents_endpoint` (`SV/inc/hooks/RtclSellerFilterHooks.php:12, 84-88`).
  - Store `store`: `myaccount_store_endpoint` (`STORE/app/Controllers/Hooks/RtclApplyHook.php:377, 1228-1241`).
- **Endpoints removed:**
  - `payments` when `rtcl_payment_settings.payment` is not `yes`; `favourites` when `rtcl_general_settings.has_favourites` is not `yes` (`CL/app/Controllers/Hooks/AppliedBothEndHooks.php:25, 239-251`). **Both are off in the DB.**
  - For **buyers** (`_rtcl_user_type = buyer` with `enable_user_type = yes`, DB = yes): `listings`, `payments` and `add-listing` are removed (`FilterHooks.php:54-55, 86-108`; `CL/app/Traits/Functions/UserTrait.php:9-17`).
- **Rewrite:**
  - `Query::add_endpoints()` calls `add_rewrite_endpoint( $slug, EP_PAGES )` (`CL/app/Controllers/Query.php:107-118`). Query vars come from `init_query_vars()` (endpoint and checkout keys, 377-380) and are filterable through `rtcl_get_query_vars` (1315-1316).
  - Paged listings use `/{account}/listings/page/N/` (Query.php:136-138).
- **Menu:** `Functions::get_account_menu_items()` (`Functions.php:3374-3406`).
  - Filters: `rtcl_account_default_menu_items` (defaults: dashboard, listings, favourites, payments, edit-account, profile-settings, logout, add-listing) and **`rtcl_account_menu_items`** (final).
  - Item classes come from `get_account_menu_item_classes()`: `rtcl-MyAccount-navigation-link`, `--{endpoint}` and `is-active` (3408-3430).
  - Pro adds `chat` before favourites (`PRO FilterHooks:723-736`). SA adds `search-alert` at position 3. SV adds `my-documents` before `edit-account`.
- **Content dispatch:**
  - `TemplateHooks::account_content()` (TH:2055-2075) fires `do_action("rtcl_account_{$key}_endpoint", $value)` for the first query var that has a handler; otherwise it renders `myaccount/dashboard`.
  - Core handlers (TH:107-114, 2115-2133):
    - `rtcl_account_listings_endpoint`
    - `rtcl_account_favourites_endpoint`
    - `rtcl_account_edit-account_endpoint` and `rtcl_account_rtcl_edit_account_endpoint`
    - `rtcl_account_payments_endpoint`
    - `rtcl_account_profile-settings_endpoint`
  - Pro: `rtcl_account_chat_endpoint` (PRO TH:53). SA: `rtcl_account_search-alert_endpoint`. SV: `rtcl_account_my-documents_endpoint`. Store: `rtcl_account_store_endpoint`.
- **Other account hooks:** `rtcl_my_listing_actions` (promotion, renew 15, edit 20, delete 30, Pro mark-as-sold 40; TH:207-210), `rtcl_edit_account_form*`, `rtcl_register_form*`, `rtcl_login_form_end`, `rtcl_after_login_form`.
- **Edit URL:** `Link::get_listing_edit_page_link($id)` returns `{listing-form-page}/edit/{id}/` (`CL/app/Helpers/Link.php:279-293`). The rewrite is `{form-page}/([^/]+)/([0-9]+)` → `rtcl_action`, `rtcl_listing_id` (Query.php:124-127).

### 3.4 Body classes, post classes, query vars

- **`body_class`** (TH:24, 1964-1998):
  - RTCL pages: `rtcl rtcl-page`. Checkout: `rtcl-checkout rtcl-page`. Account: `rtcl-account rtcl-page`, plus `rtcl-page-registration` / `rtcl-page-login`. Listing form page: `rtcl-form-page rtcl-page`.
  - `rtcl-single-no-sidebar` / `rtcl-archive-no-sidebar` when the sidebars `rtcl-single-sidebar` / `rtcl-archive-sidebar` are inactive.
  - Always `rtcl-no-js`, swapped to `rtcl-js` by an inline footer script (`no_js`, 2036-2043).
- **`post_class`:** `listing_post_class` at priority 20 (TH:25).
- **Query vars and rewrite tags:**
  - `__cat`, `__loc`, `__tag`, `__page` (pretty Listings-page URLs, Query.php:163-286)
  - `rtcl_listing_id`, `rtcl_action`, `rtcl_payment_id` (289-291)
  - Taxonomies `rtcl_category`, `rtcl_location`, `rtcl_tag` registered with `query_var => true` (`CL/app/Controllers/Admin/RegisterPostType.php:58, 103, 147`)
- **Permalinks:** `Functions::get_permalink_structure()` (`CL/app/Traits/Functions/UtilityTrait.php:288-316`).
  - `listing_base` comes from `rtcl_advanced_settings.permalink` (DB `listings`), so **single URL = `/listings/{slug}/`**.
  - `category_base` is DB `listing-category`; `location_base` is DB `listing-location`; `tag_base` defaults to `listing-tag`.

### 3.5 Archive query modification

| Hook / function | Evidence |
|---|---|
| `Query::pre_get_posts` (main query: Listings page, CPT archive, listing taxonomies) → `listing_query($q)` | `CL/app/Controllers/Query.php:36-37, 391-…, 662-704` |
| action **`rtcl_listing_query`** ($q, $Query) | Query.php:703 |
| filter `rtcl_listing_query_meta_query` | Query.php:1136 |
| filter `rtcl_listing_query_tax_query` | Query.php:1266 |
| filter `rtcl_get_catalog_ordering_args`, `rtcl_default_catalog_orderby` | Query.php:594, 652 |
| filter `rtcl_loop_listing_per_page` (default `rtcl_archive_listing_settings.listings_per_page`, DB 7) | Query.php:693-695 |
| filter `rtcl_loop_listing_post_in` (semantic search results) | Query.php:688 |
| filters `rtcl_filter_widget_default_filter_item`, `rtcl_listing_custom_fields_meta_query`, `rtcl_cf_sub_meta_queries`, `rtcl_cf_date_range_meta_queries` | Query.php:749, 1131, 899, 937 |
| geo: `ActionHooks::add_geo_query` on `rtcl_listing_query` sets query var `rtcl_geo_query`; `GeoQuery` adds the haversine SQL via `posts_fields`/`posts_join`/`posts_where`/`posts_orderby`; filter `rtcl_listing_query_geo_query` | `CL/app/Controllers/Hooks/ActionHooks.php:20, 53-75`; `CL/app/Controllers/GeoQuery.php:15-…` |
| `rtcl_listing_query_author__not_in`, `rtcl_listing_query_post__not_in` (blocked users) | Query.php:1286, 1307 |

### 3.6 GET parameters (exact names, CONFIRMED)

| Param | Meaning | Evidence |
|---|---|---|
| `q` | Keyword: sets `s`, or semantic search when enabled | Query.php:675-682; `widgets/search/inline.php:169` |
| `rtcl_category`, `rtcl_location` | Term **slugs** (also accepts numeric ids, and `category`/`location` aliases; comma-separated allowed). Applied in `get_tax_query` when **not** on the Listings page. On the Listings page the search JS rewrites the form action to `{listings}/{category_base}/{cat}/{location_base}/{loc}`, which becomes the `__cat`/`__loc` query vars. | Query.php:1184-1238, 1240-1264; `CL/assets/js/rtcl-public.js` (`.rtcl-widget-search-form` change handler) |
| `rtcl_tag` | Tag slug (filter widget) | TH:466 |
| `geo_address`, `center_lat`, `center_lng`, `distance` | Radius search. `distance` defaults to `radius_search_options()['default_distance']` (30 miles default) | ActionHooks.php:55-60; TH:537-543; `CL/app/Resources/Options.php:2504-2510`; `inline.php:31-59` |
| `filters[price][min]`, `filters[price][max]` | Price (meta `price` / `_rtcl_max_price`) | Query.php:752-822; `inline.php:148-156` |
| `filters[ad_type]` | Ad type (meta `ad_type`). Hidden in the DB (`hide_form_fields = [ad_type]`) | Query.php:825-833 |
| `filters[{field_name}]` | Form Builder custom field (must be `filterable`). Numbers use `filters[name][min]` / `[max]`; checkbox/select/radio use `filters[name][]` (LIKE, AND); text uses LIKE; date uses a single value or `start - end` | Query.php:836-997; Pro widget `PRO/app/Controllers/Hooks/FilterHooks.php:955-1064` |
| `cf_{field_name}` | Ajax-filter style. Comma-separated and converted to `filters[...]` server-side | Query.php:721-730 |
| `filter_category`, `filter_location`, `filter_tag` | Comma-separated **term IDs** | Query.php:1155-1182 |
| `filter_price` (`min,max`), `filter_ad_type` | Ajax-filter style | Query.php:733-745 |
| `directory` | Form id(s) or `all`; scopes which form fields the custom-field filters use | Query.php:841-845 |
| `orderby` | `title-asc`, `title-desc`, `date-desc`, `date-asc`, `views-desc`, `views-asc`, `price-asc`, `price-desc` (`relevance` when searching) | Query.php:584-653; `Options.php:2811-2827`; TH:1731-1760 |
| `view` | `grid` or `list` (Pro) | `PRO/app/Controllers/Hooks/TemplateHooks.php:604`; `FilterHooks.php:1506` |
| `page` / `__page` | Pagination on the Listings page | Query.php:696 |
| `p` (+ pending, author) | Author can preview own pending listing | Query.php:55-64 |

---

## 4. Form Builder (core, table `{prefix}rtcl_forms`; prod prefix `wp_d5c58b26b6_`)

### 4.1 Storage, model, selection

- **Table:** created in `CL/app/Database/Migrations/Forms.php:12, 21-42`.
  - Columns: `id`, `title`, `slug` (unique), `status`, the JSON columns `fields`, `sections`, `single_layout`, `slug_builder`, `translations`, `settings`, plus `type`, `default`, `created_by`, `created_at`, `updated_at`.
  - Later migrations: `FormsMigration600.php:34-169`.
- **Model:** `Rtcl\Models\Form\Form` (`CL/app/Models/Form/Form.php:29`):
  - `getFields()` :112, keyed by uuid
  - `getSections()` :169, `getSectionBy()` :198
  - `getFieldByName()` :79, `getFieldByUuid()` :89
  - `getFieldAsGroup('preset'|'custom')` :223
  - `getSingleLayout()` :116, `getSingleLayoutRows()` :133
  - `getArchiveViewAbleFields()` :257
  - `getFieldByElement()`
- **Helpers:** `Rtcl\Services\FormBuilder\FBHelper` (`FBHelper.php:15`) and `FBField` (`FBField.php:8`).
- **On/off:** `FBHelper::isEnabled()` reads `rtcl_fb_options['active']` (`FBHelper.php:69`, 1871-1878). `Functions::isEnableFb()` wraps it (`Functions.php:5704`). DB: **on**.
- **Form per listing:** post meta **`_rtcl_form_id`** (`CL/app/Models/Listing.php:131`; `Listing::getForm()` :1601).
  - Written at save (`CL/app/Controllers/Ajax/FormBuilderAjax.php:427-430`); backfilled by cron (`CL/app/Controllers/Admin/Cron.php:42, 65`).
  - `FBHelper::getFormById()` :22, `getFormBySlug()` :53, `getDefaultForm()` :598 (`default=1`, DB form 2 "Post a Job"), `getFormList()` :609.
- **Per-category form selection: NOT FOUND.** For new listings the React app picks, in order:
  1. `rtclFB.form` (edit);
  2. the single form if only one exists;
  3. URL `?_fb={form-slug}`;
  4. otherwise a form chooser.

  Categories only appear inside a form (the category field plus conditional logic). None of the DB forms set `top_level_ids`.

**DB forms** (`09-rtcl-forms.json`; fields = preset + custom; filterable = custom fields marked filterable):

| id | title | slug | fields | sections | filterable |
|---|---|---|---|---|---|
| 2 | Post a Job (default) | `post-a-job` | 22 | 3 | 4 |
| 3 | Service | `service` | 26 | 6 | 2 |
| 4 | Public Information | `public-information` | 20 | 5 | 2 |
| 5 | Auto/Moto/Boats | `moto-boats-cars` | 49 | 7 | 27 |
| 6 | Submit an Event | `submit-an-event` | 23 | 5 | 3 |
| 7 | Property for Rent or Sale | `property-for-rent-or-sale` | 33 | 7 | 8 |
| 8 | Restaurants/Nightlife | `restaurants-nithtlife` (sic) | 24 | 3 | 8 |
| 9 | Marketplace | `marketplace` | 19 | 3 | 3 |
| 10 | Leisure/Sports | `leisure-sports` | 21 | 3 | 4 |
| 11 | Tourist Attraction | `tourist-attraction` | 20 | 4 | 1 |

### 4.2 Front-end render entry point

- **Shortcode:** `[rtcl_listing_form]` maps to `Shortcodes::listing_form` (`CL/app/Controllers/Shortcodes.php:22`), then `Rtcl\Shortcodes\ListingForm::output` (`CL/app/Shortcodes/ListingForm.php:14-97`).
  - Edit mode is detected from query vars `rtcl_action=edit` and `rtcl_listing_id`.
  - Form Builder on → template `listing-form/form-builder` (:46-50).
- **Template:** `CL/templates/listing-form/form-builder.php:13-18` outputs only `<div id="rtcl-form-builder-container"><div id="rtcl-form-builder"><div class="rtcl-fb-loader-container">…`.
  - An optional jQuery side form `#rtcl-fb-extra-form` is driven by action `rtcl_fb_extra_form` (:19-33).
- **JS:** handle `rtcl-form-builder` = `CL/assets/form-builder/form-builder.js`.
  - React, loaded as `type="module"` with `strategy: async`. Shared chunks live in `CL/assets/js/chunks/`.
  - Dependencies: `jquery`, `rtcl-public`, `wp-tinymce` (`CL/app/Controllers/Admin/ScriptLoader.php:156-169`; module tag at :70-77). CSS handle `rtcl-form-builder` = `form-builder/public.css` (:170).
  - Enqueued on the listing-form page with `wp_enqueue_editor()`, `rtcl-gallery`, `select2` and `rt-field-dependency` (:500-520).
- **Localized object:** **`rtclFB`** (`ScriptLoader.php:353-391`). Keys:
  - fields, isAdminEnd, hasPro, postStatus
  - forms (published forms plus defaults), form, listingId, formData, options
  - ajaxurl, apiurl, restNonce, nonceId, nonce, i18n, countryPhoneList, phoneDefaults
  - Filters: `rtcl_fb_forms`, `rtcl_localize_fb_params`.
- **Save:** AJAX `rtcl_update_listing` (priv and nopriv) → `FormBuilderAjax::update_listing()` (`FormBuilderAjax.php:38, 55, 66-541`).
  - Nonce field `__rtcl_wpnonce`, action `rtcl_nonce_secret` (verified at :69).
  - POST fields: `formData` (urlencoded, `parse_str` at :102), `formId`, `listingId`, `isAdminEnd`.
  - Response JSON includes `redirect_url`.
  - Other Form Builder AJAX actions: `rtcl_fb_get_category`, `rtcl_get_terms`, `rtcl_fb_get_location`, `rtcl_fb_gallery_image_upload` / `_delete` / `_update_order` / `_update_as_feature`, `rtcl_fb_file_upload` / `_delete`, `rtcl_fb_get_tags`, `rtcl_fb_add_new_tag`, `rtcl_fb_write_with_ai` (:21-59).
- **New listing status:** `rtcl_general_settings.new_listing_status` (DB `pending`). Edited: `edited_listing_status` (DB `pending`) (`FormBuilderAjax.php:168-177`).

### 4.3 Markup and conditional logic (React output; minified, no line numbers)

- **Sections:** `div.rtcl-fb-sections > div.rtcl-fb-section.rtcl-fb-sec-{id}[.css_class][data-title]#rtcl-fb-section-{id}`.
  - Header: `.section-header > .section-icon i + .section-title`.
  - Body: `.rtcl-fb-section-body`. Containers: `.rtcl-fb-container-body`.
- **Field:** `div.rtcl-fb-field-wrap[.css_class][.label-{placement}][data-element][data-id=uuid]` containing `.rtcl-fb-field-label`, `.rtcl-fb-field-content`, `.rtcl-fb-input-{type}`, `.rtcl-fb-errors`.
- **Submit:** `.rtcl-form-submit-btn > .rtcl-fb-btn`.
- **Conditional logic is JSON, not data attributes.** Each field and each section has a `logics` object: `{"status":true,"relation":"and"|"or","conditions":[{"fieldId":"<uuid>","operator":"=","value":…}]}`.
  - JS evaluator: chunk `CL/assets/js/chunks/aiUtility-*.js` (export `ai`, imported as `ye` in the bundle). Operators: `= != > >= < <= contains doNotContains startsWith endsWith empty notEmpty`.
  - Hidden fields are not rendered, and their values are dropped.
  - PHP mirror: `FBHelper::isValidateCondition()` :664 and `checkCondition()` :695-720.
  - DB examples: in form 5, sections "Car Specs", "Motorcycle" and "Boat" are gated on `select_mo48xoy6` (Item Type). In form 4, two checkboxes show when category `contains` 161.

### 4.4 Value storage (meta keys)

- **Custom fields:** meta key = field `name` (`FBField::getMetaKey()` `FBField.php:124-128`; save loop `FormBuilderAjax.php:393-401, 483-488`).
  - Auto-generated names follow `{element}_{uuid}`, e.g. `select_mo47kwc1`, `number_mo47iwil`.
  - Checkbox: **one meta row per value** (`add_post_meta`, :468-476); read with `get_post_meta(...,false)`.
  - Date range: `{name}_start` and `{name}_end` (:460-464).
  - `file`: skipped in the loop; stored via the `rtcl_fb_file_upload` flow.
- **Preset fields** (fixed keys, :203-392):
  - `ad_type`, `price`, `_rtcl_max_price`, `price_type`, `_rtcl_listing_pricing`, `_rtcl_price_unit`
  - `zipcode`, `address`, `_rtcl_geo_address`, `latitude`, `longitude`, `hide_map`
  - `phone`, `_rtcl_whatsapp_number`, `_rtcl_telegram`, `email`, `website`
  - `_rtcl_social_profiles`, `_rtcl_bhs` (business hours), `_rtcl_video_urls`, `_views`, `rtcl_agree`
  - Title, description and excerpt go to post fields; category, location and tag go to taxonomies.
- **Old-theme logo field:** `listing_logo` (a Form Builder `file` field, array of attachment IDs). It replaces legacy `listing_logo_img`; the theme switches on `Functions::isEnableFb()` (`OLD/classified-listing/custom/listing-heading.php:34-40`).
- **Legacy (Form Builder off):** post types `rtcl_cf` / `rtcl_cfg`, meta `_field_{ID}` (`CL/app/Models/RtclCFGField.php:69, 563`).

### 4.5 Displaying values on the single listing (generic tile renderer)

There are three paths in core:
- (a) **Single-layout builder** (active for all DB forms). `CL/templates/single-layout/builder.php:96-244` walks `rows → columns → sections → containers → fields(uuid)`.
  - For each field it builds `new FBField($fields[$uuid])` and renders `single-layout/render-element` (`render-element.php:16-60` skips `custom_html`, `input_hidden`, `terms_and_condition` and `view_count`).
  - Wrappers: `.rtcl-sl-sections > .rtcl-sl-row > .rtcl-sl-column > .rtcl-sl-section.rtcl-sl-section-{uuid}` and `.rtcl-sl-element-wrap.rtcl-sl-element-{uuid}[data-element]`.
  - Element markup (e.g. `select.php:23-53`): `.rtcl-sl-element > .rtcl-slf-label-wrap(.rtcl-field-icon,.rtcl-slf-label) + .rtcl-slf-value`.
- (b) **Classic `content-single`**: `listing/c-fields.php` uses `$form->getFieldAsGroup(FBField::CUSTOM)` and `FBHelper::reOrderCustomField()`, skipping fields that are not `isSingleViewAble()` or are empty.
  - Markup: `.rtcl-single-custom-fields > .rtcl-cf-properties > .rtcl-cfp-item.rtcl-cfp-{element}[data-name][data-uuid] > .rtcl-cfp-label-wrap + .cfp-value`.
  - Title filter: `rtcl_custom_fields_section_title`.
- (c) **Legacy:** `listing/custom-fields.php`.

**API for a theme tile renderer (signatures, CONFIRMED):**

```php
$listing = rtcl()->factory->get_listing( $post_id );           // Rtcl\Models\Listing
$form    = $listing->getForm();                               // Rtcl\Models\Form\Form|null
foreach ( $form->getSections() as $section ) {                // ['uuid','title','icon','logics','containers'=>[['fields'=>[uuid,...]]]]
  foreach ( $section['containers'] as $c ) foreach ( $c['fields'] as $uuid ) {
    $raw = $form->getFieldByUuid( $uuid ); if ( ! $raw ) continue;
    $f = new \Rtcl\Services\FormBuilder\FBField( $raw );      // FBField.php:38
    if ( ! empty( $raw['preset'] ) || ! $f->isSingleViewAble() ) continue;   // :275
    $val = $f->getFormattedCustomFieldValue( $listing->get_id() ); // :212 (filter rtcl_fb_formatted_custom_field_value)
    if ( $val === '' || $val === null || $val === [] ) continue;
    $label = $f->getLabel();                                  // :133
    $icon  = $f->getIconData();                               // :72 ['type'=>'class','class'=>…]
    $html  = \Rtcl\Services\FormBuilder\FBHelper::getFormattedFieldHtml( $val, $f ); // FBHelper.php:1904 (option labels, color, file, textarea)
  }
}
```

Other accessors: `FBField::getName()` :110, `getUuid()` :117, `getElement()` :65, `getOptions()` :95, `getData($k)` :103, `getValue($id)` :168 (raw; filter `rtcl_fb_get_field_value`), `getOptionLabel()` :459, `isArchiveViewAble()` :268, `isFilterable()` :254.

**Section logics:** evaluate them with `FBHelper::isValidateCondition( $formData, $section['logics'], $fields )`, where `$formData` comes from `FBHelper::getFormData($listing_id, $form)` :229. That way hidden sections, such as Boat-only specs, are not shown.

### 4.6 Search-filterable fields (archive filters)

- **Flag:** field setting `filterable` (Pro-only setting) with `filterable_range`, `filterable_range_min/max/step`, `filterable_date_type` and `filterable_disable_logic` (`CL/app/Services/FormBuilder/ElementCustomization.php:970-1009`).
  - There is **no** `searchable` flag for Form Builder fields; `is_searchable` exists only on legacy `rtcl_cf`.
  - Allowed filter elements: text, textarea, number, checkbox, select, radio, date (`PRO/app/Helpers/Fns.php:337-347`, filter `rtcl_ajax_filter_allow_fields`).
- **Classic widget** `Rtcl\Widgets\Filter` (template `widgets/filter.php`, `form.rtcl-filter-form` GET to `Functions::get_filter_form_url()`).
  - Hook `rtcl_widget_filter_form`: ad type 10, category 20, location 30, tag 35, radius 40, Pro rating 40, Pro custom fields 50, price 90 (TH:41-46; PRO TH:45-46).
  - Custom-field inputs are injected by Pro (`PRO/app/Controllers/Hooks/FilterHooks.php:65, 955-1064`) as `filters[{name}]…` (see §3.6).
- **Ajax filter** `Rtcl\Widgets\AjaxFilter` (`CL/app/Widgets/AjaxFilter.php:9-80`). The instance key is **`filter_id`**, read from option `rtcl_filter_settings[filter_id]`. DB filter ids: `job-filter` (empty) and `cars-for-sale-filter` (price range plus 42 cf fields).
  - Render with `the_widget( \Rtcl\Widgets\AjaxFilter::class, [ 'filter_id' => 'cars-for-sale-filter' ] )`.
  - Item hooks: `rtcl_widget_ajax_filter_render_{search|ad_type|category|location|tag|price_range|radius_filter|cf|rating|directory}` (TH:231-240; PRO TH:49-51).
  - AJAX `rtcl_ajax_filter_load_data` (`CL/app/Controllers/Ajax/FilterAjax.php:17-18, 97`; **no nonce check**). URL state: `cf_{name}=…`, `filter_*`, `directory` via `history.pushState`.
- **`[listing_filters filter_group="…"]` (used on Elementor pages 5199/5260/7063): NOT FOUND.** No plugin or theme in this install registers it; the closest equivalent is the AjaxFilter widget above.

---

## 5. Assets

### 5.1 Core (`CL/app/Controllers/Admin/ScriptLoader.php`)

Registration happens on `wp_enqueue_scripts` priority 1 (`register_script`, :48, 395-…).

| Handle | File / deps | Where enqueued |
|---|---|---|
| `rtcl-public` (css) | `css/rtcl-public.min.css` | **Every front-end page** (:468) |
| `rtcl-public` (js) | `js/rtcl-public.min.js`; deps `jquery`, `jquery-ui-autocomplete`, `rtcl-common` (+ filter `rtcl_public_script_dependencies`) | **Every page** (:550). Localized `rtcl` (:819) and `rtclAjaxFilterObj` (:831). Handles phone reveal, favourites, contact, report, AJAX filter, login/registration |
| `rtcl-common` | `js/rtcl-common.min.js` (jquery) | dep |
| `daterangepicker` | vendor, deps `jquery`, `moment` | **Every page** (:549) |
| `fontawesome` (css 6.7.1) | `vendor/fontawesome/css/all.min.css` (filter `rtcl_fontawesome_css_source`) | account, form, single, checkout, archives, author |
| `swiper` (7.4.1) | `vendor/swiper/swiper-bundle.min.js`; deps `jquery`, `imagesloaded` (filter `rtcl_swiper_js_source`) | Only as a dependency of `rtcl-single-listing` (:406-414) |
| `rtcl-single-listing` | `js/single-listing.min.js`; deps from `rtcl_single_listing_script_dependencies` = `['swiper']` plus Pro `photoswipe-ui-default`, `zoom` | `is_singular('rtcl_listing')` (:415-423). **Only** the gallery slider, PhotoSwipe and zoom (6.7 KB) |
| `select2` (4.1.0-rc.0) | vendor | listing form, checkout |
| `rtcl-gallery` | deps `plupload-all`, `jquery-ui-sortable`, `jcrop`, `wp-util`, … | listing form |
| `rt-field-dependency` | | listing form |
| `rtcl-form-builder` (js module and css) | §4.2 | listing form when the Form Builder is on |
| `rtcl-public-add-post` | deps `jquery`, `daterangepicker` | legacy form; edit-account |
| `rtcl-validator` / `jquery-validator` | | account (guests and edit), form, single, checkout |
| `rtcl-edit-account` | intl phone | account |
| `rtcl-recaptcha` | Google reCAPTCHA v2/v3 | per `rtcl_misc_settings.recaptcha_forms` (DB empty) |
| `rtcl-google-map` | `https://maps.googleapis.com/maps/api/js?v=3.exp&libraries=geometry,places&key=…` (`Options::google_map_script_options()` + filter `rtcl_google_map_script_options`, `CL/app/Resources/Options.php:2495-2502`), **header, synchronous** | dep of `rtcl-map` |
| `rtcl-map` | `js/gmap.js` (google) or `js/osm-map.js` (bundles Leaflet 1.9.4) | single listing, listing form, edit-account (:488, 519, 530). Localized `rtcl_map` (:198) |

- **Map selection:** registered only if `Functions::has_map()` (`rtcl_misc_map_settings.has_map`, DB yes) and type is `google` with an API key (DB google plus key) or `osm` (:173-198).
- **Archive pages:** no map script by default; the `.rtcl-map-view` map view only comes from widgets.

### 5.2 Pro (`PRO/app/Controllers/ScriptController.php:37-142`)

- `photoswipe` and `photoswipe-ui-default` (4.1.3), `zoom` (jquery.zoom 1.7.21), css `photoswipe` and `photoswipe-default-skin` (singles).
- `rtcl-pro-public` js/css on every page; the js depends on `pusher-js` when chat is enabled.
- Chat (when `rtcl_chat_settings.enable`, DB yes):
  - `pusher-js` (8.4.0, `vendor/pusher.min.js`)
  - `rtcl-chat`, enqueued on singular listings
  - `rtcl-user-chat`, enqueued on the account `chat` endpoint
  - Localized `rtcl_chat` with `refresh_interval` (20000 ms, filter `rtcl_chat_refresh_interval`), nonce `__rtcl_wpnonce`, `rest_api_url`, and `pusher{app_key,app_cluster}` when `pusher_enable=yes` (DB yes) (`PRO/app/Helpers/Fns.php:957-970`).
  - **Transport:** Pusher plus AJAX fallback/polling. `rtcl-chat.min.js` contains `setInterval(..., rtcl_chat.refresh_interval)`.
  - AJAX actions: `rtcl_chat_ajax_start_conversation` (also nopriv), `rtcl_chat_ajax_send_message`, `rtcl_chat_ajax_visitor_send_message`, `rtcl_chat_ajax_get_messages`, `rtcl_chat_ajax_get_conversations`, `rtcl_chat_ajax_message_mark_as_read`, `rtcl_chat_ajax_get_unread_message_num`, `rtcl_chat_ajax_conversations_remove` (`PRO/app/Controllers/ChatController.php:21-61`).

### 5.3 Add-ons

| Plugin | Assets |
|---|---|
| Search Alert | `rtcl-search-alert-public` js and css on **every** front-end page (`SA/includes/Assets/LoadAssets.php:46-85, 137-139`); localized `rtcl_search_alert_data.security` |
| Seller Verification | css `rtcl-seller-verification` everywhere; js only on `my-documents` (`SV/inc/init.php:61-62, 103-106`) |
| rtcl-verification | `rtcl-verification` js/css on account and form pages; `firebase` (gstatic 8.3.1) when the gateway is firebase (DB `verification_gateway=firebase`) (`VER/app/RtclVerification.php:109-143`) |
| Store | `rtcl-store` and `rtcl-store-public` (inactive) |
| Review Schema | `rtrs-app` css/js, `featherlight`, `rtrs-sc` (`RS/app/Modules/Review/Scripts/ReviewScriptLoader.php:42-51`) |
| Fluent Forms | `fluent-form-styles`, `fluentform-public-default`, `fluent-form-submission` (§9) |

### 5.4 Safe dequeue / replace

- **Dequeue timing:** dequeue at `wp_enqueue_scripts` priority **≥ 1000**. RTCL enqueues at priority 1 inside `register_script`, and `frontend_script` runs at 999.
- **Swiper:** needed only for the core gallery. If the theme ships its own gallery (overriding `listing/gallery.php`):
  - `wp_dequeue_script('rtcl-single-listing')`. Do not deregister `swiper` if Elementor or other widgets use it.
  - Then also drop `photoswipe-default-skin` and the `wp_footer` PhotoSwipe placeholder (`remove_action('wp_footer',[RtclPro\Controllers\ScriptController::class,'photoswipe_placeholder'])`).
  - Alternatively keep it and just restyle `.rtcl-slider*`.
- **Do not dequeue `rtcl-public`:** it carries phone reveal (`.reveal-phone[data-options]`; the reveal itself is client-side, and AJAX `rtcl_phone_whatsapp_revealed` only counts it), favourites, contact/report forms, login/registration and the AJAX filter.
- **Lazy Google Maps:**
  - `gmap.js` instantiates `google.maps.Geocoder` at load, so Google must load **before** it.
  - It auto-renders every `.rtcl-map` on jQuery ready and exposes `window.rtcl_render_map(el)`, `rtcl_render_map_view()`, `rtcl_startGeoAutoSuggestion()` and `rtcl_getCurrentLocation()`.
  - Recipe for singles: dequeue `rtcl-map` and `rtcl-google-map`; print `window.rtcl_map = <?= wp_json_encode( Functions::get_map_localized_options() ) ?>` yourself; when `.rtcl-map` scrolls into view (IntersectionObserver or click-to-load), inject the Maps API `<script>` and on load inject `gmap.js`; its ready-handler then renders the maps.
  - Keep eager loading on the listing form and edit-account pages (address autocomplete).
  - The `rtcl_google_map_script_options` filter can add `loading=async`, but gmap.js has no callback, so the injection approach is the safe one.

---

## 6. Settings the theme must read

Read settings with `Rtcl\Helpers\Functions::get_option_item( $option, $key, $default, $type )`. The `checkbox` type means `=== 'yes'`; `multi_checkbox` means `in_array($default, …)` (`Functions.php:2890-2909`).

| Concern | Option → key | DB value | Code reading it |
|---|---|---|---|
| Moderation: new / edited status | `rtcl_general_settings` → `new_listing_status` / `edited_listing_status` | `pending` / `pending` | `FormBuilderAjax.php:168-177`; `PublicUser.php:1311-1319` |
| Listing duration (days; 0 = never) | `rtcl_general_settings.listing_duration` | `0` | `Functions.php:4473` |
| Free-ads quota | `rtcl_membership_settings` → `enable` (master), `enable_free_ads`, `number_of_free_ads`, `renewal_days_for_free_ads`, `unlimited_free_ads_membership`, `categories_of_free_ads` | enable=`''` (**off**), free=yes, 5, 30 | Enforced only if membership is enabled (`STORE/app/Controllers/Hooks/MembershipHook.php:18-31, 346-410`) |
| Remaining-quota helpers | `RtclStore\Helpers\Functions::user_is_valid_to_post_as_free($uid)` returns the remaining count; `get_posted_ads_as_free($uid)` counts rows in `{prefix}rtcl_posting_log` with `created_at` in the last N days | | `STORE/app/Helpers/Functions.php:49-101` |
| Toggle helpers | `is_membership_enabled()`, `is_enable_free_ads()`, `is_store_enabled()` (`enable_store`) | | `STORE/app/Helpers/Functions.php:362-407` |
| Core listing limit (independent of Store) | | | NOT FOUND |
| Max images | **Form Builder:** field `images` → `validation.max_file_count`, `max_file_size` (MB), `allowed_image_types` | All 10 forms: 5 images, 10 MB, `jpeg,jpg,png,webp`; logo/file fields 1 file of 2–4 MB | `FormBuilderAjax.php:1042-1049`; defaults `CL/app/Services/FormBuilder/AvailableFields.php:540-560` |
| Max images (legacy uploader) | `rtcl_moderation_settings.maximum_images_per_listing` | `5` | `CL/app/Resources/Gallery.php:32` |
| Upload size (legacy, avatar) | `rtcl_misc_media_settings.image_allowed_memory` (MB) → `Functions::get_max_upload()` | `2` | `Functions.php:4512-4515` |
| Upload types (legacy, avatar) | `rtcl_misc_media_settings.image_allowed_type` | `png,jpg,jpeg,webp` | `Gallery.php:50`; `ScriptLoader.php:807` |
| Seller info: guests see login link | `rtcl_single_listing_settings.registered_only` (multi: `listing_seller_information`, `store_contact`) → `RtclPro\Helpers\Fns::registered_user_only($key)` | both set | `PRO/app/Helpers/Fns.php:268-270`; `PRO TH:85-87` |
| Phone / WhatsApp / email visibility per user | user meta `_rtcl_display_phone_public`, `_rtcl_display_whatsapp_public`, `_rtcl_display_email_public` (empty means visible; filter `rtcl_default_display_visibility`) → `Functions::check_visibility($user,'phone'|'whatsapp'|'email')` | | `Functions.php:6704-6735` |
| Phone masking | Last 3 digits replaced by `XXX` (filter `rtcl_phone_number_placeholder`). **The masking is cosmetic only:** the hidden digits (`phone_hidden`, `whatsapp_hidden`) are already in the `.reveal-phone[data-options]` JSON in the HTML. AJAX `rtcl_phone_whatsapp_revealed` / `rtcl_phone_click` only counts reveals | | TH:723-780; `CL/app/Controllers/Ajax/PublicUser.php:89-91` |
| Email seller (contact form) | `rtcl_single_listing_settings.has_contact_form` and listing meta `email`; hidden for the owner | yes | TH:695-720 |
| Chat link | `rtcl_chat_settings.enable` (`Fns::is_enable_chat()`); hidden for the owner; filter `rtcl_is_chat_link_available` | yes | `PRO/app/Helpers/Fns.php:214-216`; `PRO TH:613-636` |
| Report abuse | `rtcl_single_listing_settings.has_report_abuse` | yes | `Listing.php:1626` |
| Reviews / rating | `rtcl_single_listing_settings.has_comment_form`, `enable_review_rating` | yes, yes | `PRO/app/Controllers/CommentController.php:231-240`; `Comments.php:168` |
| Detail-page display options | `rtcl_single_listing_settings.display_options_detail` (array) | category, date, user, views, price, … | TH single meta |
| Archive card options | `rtcl_archive_listing_settings.display_options`, `listings_per_page` (7), `default_view` (grid), `listings_per_row` | | |
| Favourites | `rtcl_general_settings.has_favourites` → `Functions::is_enable_favourite()` | `''` (**off**) | `Functions.php:4757-4759`; `650-656` |
| Compare / quick view | `rtcl_general_settings.enable_compare`, `compare_limit`, `enable_quick_view` → `Fns::is_enable_compare()` / `is_enable_quick_view()` | off / 3 / off | `PRO/app/Helpers/Fns.php:222-232` |
| Mark as sold | `rtcl_general_settings.enable_mark_as_sold` | yes | |
| Store / membership toggles | `rtcl_membership_settings.enable_store`, `.enable` | both off | `STORE Functions.php:362-399` |
| Payments | `rtcl_payment_settings.payment` → `Functions::is_payment_disabled()` | `''` (off) | `Functions.php:550-556` |
| User type (buyer/seller) | `rtcl_account_settings.enable_user_type`; user meta `_rtcl_user_type` | yes | `SettingsTrait.php:57-61`; `UserTrait.php:9-17` |
| Registration separate | `rtcl_account_settings.separate_registration_form`, `enable_myaccount_registration` | yes / yes | `Functions.php:3350-3356` |
| Email verification (Pro) | `rtcl_account_settings.user_verification`; user meta `rtcl_verification_key`; link = `{account}/verify/?user_id=&verify_email=` | yes | `PRO/app/Emails/UserVerifyLinkEmailToUser.php:61-64`; `PRO/app/Models/UserAuthentication.php:40-123` |
| Map | `rtcl_misc_map_settings` → `has_map`, `map_type` (`osm` default), `map_api_key`, `map_zoom_level`, `map_center` → `Functions::has_map()`, `get_map_type()` | yes / google / 12 / Torrevieja | `Functions.php:4933, 4944` |
| Location type | `rtcl_general_location_settings.location_type` (`local`/`geo`) → `Functions::location_type()` | `local` | |
| Radius search | `Options::radius_search_options()`: units `miles`, max 300, default 30 (filterable) | | `Options.php:2504-2510` |
| Currency | `rtcl_general_currency_settings` → `currency` (EUR), `currency_position` (left), `currency_thousands_separator` (,), `currency_decimal_separator` (.) | | `Functions.php:2127-2150` |
| Price formatting | `Functions::price( $price, $only_formatted=false, $args )` (filter `rtcl_price_args`), `Functions::get_currency_symbol()`, `Functions::format_decimal()`, `$listing->get_price_html()` | | `Functions.php:798, 2143, 4730`; `Listing.php:1452` |
| | `Functions::get_formatted_price()` is **deprecated** (`_deprecated_function`) | | `Functions.php:748-752` |
| Business hours data | listing meta `_rtcl_bhs` (+ `_rtcl_special_bhs`) → `BusinessHoursController::get_business_hours($id)` returns `['active','type','days','bhs'=>[0..6=>['open','times'=>[['start','end']]]]]` with today's special hours merged | | `BusinessHoursController.php:454-497` |
| "Open now" | `BusinessHoursController::openStatus( $bhs['bhs'] )` returns `true`/`1`/`false`; `isOpen($t1,$t2)`; `isOpenAllDayLong($day)` | | `BusinessHoursController.php:60-80, 406-448` |
| Timezone | Uses **site timezone**: `current_time('timestamp')`, `current_datetime()` (WP `timezone_string`). No per-listing timezone. | | same lines |
| Classic business-hours toggle | `rtcl_moderation_settings.enable_business_hours` (only when the Form Builder is off) | yes | `Functions.php:4782-4784`; `BusinessHoursController.php:23` |

**Legacy settings caveat.** The `rtcl_moderation_settings` admin tab is **hidden when the Form Builder is on** (`FilterHooks::remove_classic_form_settings`, `FilterHooks.php:110-115`). Its stale DB values (`maximum_images_per_listing`, `hide_form_fields=[ad_type]`, `enable_business_hours`, etc.) are still read by legacy code paths. For Form Builder listings, rely on the per-form field validation instead.

---

## 7. Review Schema

- **Render path:** review-schema hooks `comments_template` at **99** (`RS/app/Modules/Review/Hooks/ReviewFrontend.php:51, 410-423`). Pro RTCL hooks it at 10, so **review-schema wins**.
  - It returns `reviews.php` when the post is singular, comments are open or present, and an `rtrs` config post with `rtrs_post_type=rtcl_listing` and `rtrs_support=1` exists (`RS/app/Helpers/Functions.php:478-552`).
  - It forces `comments_open` (56, 396-408).
  - RTCL triggers it via `rtcl_single_listing_review` → `comments_template()` (TH:1620-1623).
- **RTCL steps aside:** if `rtrs()` exists and such a config exists (and is not schema-only), Pro does **not** init its own `CommentController` (`PRO/app/RtclPro.php:112-138`).
- **Shared data:**
  - Comment meta `rating` is written by both. Titles differ: `rt_title` in review-schema, `title` in RTCL.
  - review-schema lists only `comment_type=review` (`RS/templates/reviews.php:117-122`).
  - review-schema keeps RTCL aggregates in sync via `Rtcl\Controllers\Hooks\Comments::clear_transients()` (`ReviewFrontend.php:622-625`).
  - Post meta: `rtrs_avg_rating`, `rtrs_rating_count`. RTCL aggregates: `_rtcl_average_rating`, `_rtcl_review_count`, `_rtcl_rating_count`.
- **Template overrides:**
  - `your-theme/review-schema/{slug}.php` (or the theme root `{slug}.php`!), then Pro, then free (`RS/app/Helpers/Functions.php:225-262`).
  - Filters: `rtrs_template_path`, `rtrs_get_template_part`, `rtrs_locate_template`.
  - Old theme: `review-schema/reviews.php`, `review/layout-three.php`, `summary/layout-one.php`, plus filter `rtrs_review_form_string_list` (`OLD/inc/general.php:47`).
- **Markup classes:**
  - Wrapper: `.rtrs-review-wrap#comments`.
  - Form: `rtrs-form-box`, `rtrs-review-form`, `rtrs-form-group`, `rtrs-form-control`, `rtrs-submit-btn` (`ReviewFrontend.php:980-988`).
  - Shortcodes: `rtrs-review-list`, `rtrs-review-form`, `rtrs-review-summary`, `rtrs-average-rating-stars`, `rtrs-average-rating-count`, `rtrs-affiliate` (`RS/app/Modules/Review/Shortcodes/ShortcodesInit.php:16-23`).
- **JSON-LD:**
  - review-schema prints `<script type="application/ld+json">` on `wp_head` priority 1 (`RS/app/Modules/Schema/Hooks/SchemaFrontend.php:27`).
  - Graph: BreadcrumbList, WebPage/CollectionPage, and the per-post-type type (Article/Product/LocalBusiness/RealEstateListing…) plus AggregateRating (`Hooks/AggregateRatingInjector.php:57`).
  - Pro adds a **Product + Offer + AggregateRating + Review** schema for `rtcl_listing` and ItemList for archives (`RSP/app/Modules/Schema/FrontEnd/FrontendHook.php:165-171, 243-306, 412-413, 2216-2323`).
  - The AI renderer also hooks `wp_head` priority 1 when `rtrs_ai_settings.ai_enabled=yes`.
  - **RTCL itself outputs no JSON-LD (NOT FOUND).** So the theme must **not** add listing, breadcrumb or organization schema while review-schema's `schema_enabled=yes` (code default). Alternatively it can disable that via `rtrs_general_settings.schema_enabled`, per-post meta `_rtrs_disable_snippet_generator`, or filter `rtrs_schema_graph_data`.
- **Options:** `rtrs_general_settings`, `rtrs_review_settings`, `rtrs_schema_*`, `rtrs_ai_settings`.

---

## 8. Search Alert / Seller Verification / rtcl-verification

**Nonce convention (core).** `Functions::verify_nonce()` checks `$_REQUEST['__rtcl_wpnonce']` against action `rtcl_nonce_secret` (`CL/app/Traits/Functions/CoreTrait.php:12-20`; `CL/app/Rtcl.php:125, 130`). Output it with `wp_nonce_field( rtcl()->nonceText, rtcl()->nonceId )`. The names `rtcl_nonce` / `rtcl-nonce` are NOT FOUND.

### 8.1 rtcl-search-alert 1.2.0

- **Templates:** see §1.2 / §2.6.
- **Account:** menu item `search-alert` at position 3 and endpoint `search-alert` (`SA/includes/Hooks/Common.php:34-35, 112-120`). Content via `rtcl_account_search-alert_endpoint` (TemplateHook.php:13).
- **AJAX** (`SA/includes/Ajax/SaveSearchAlert.php:16-30`): `save_search_alert` (+nopriv), `rtcl_toggle_search_alert_status`, `rtcl_delete_search_alert`, `rtcl_search_get_filter_data` (+nopriv), `rtcl_search_criteria_data`, `rtcl_get_search_found_listing_statistics`, `rtcl_export_search_alerts`, plus admin actions.
  - **Nonce:** `check_ajax_referer('rtcl_save_search_nonce','security')`, localized as `rtcl_search_alert_data.security` (`SA/includes/Assets/LoadAssets.php:131-148`).
- **REST:** `POST rtcl/v1/search-alert`, `scheduler_type` = `daily|weekly|monthly|when-match|no-alert`.
- **Storage:** table `{prefix}rtcl_search_alerts` (id, title, filter, email, user_id, status, scheduler_type, available_time, email_info, hash, listing_count, frequency, …). There is no post type or meta.
- **Scheduling:** Action Scheduler hook `rtcl_search_alert_run`; also `publish_rtcl_listing` for "when-match". Unsubscribe link: `?action=rtcl_search_alert_unsubscribe&hash=`.
- **Settings:** `rtcl_misc_settings.enable_search_alert_notification` (DB yes) and `enable_search_data_autosave`.
- **UI:** the JS injects a "Save search" button before `.rtcl-clear-filters` when `.rtcl-active-filters-container` has children (`SA/assets/js/rtcl-search-alert.js:66-109`). **The theme must keep that markup** (or call the AJAX itself) if it builds a custom filter bar.

### 8.2 rtcl-seller-verification 1.2.1

- **User meta (CONFIRMED):**
  - **`photo_id`** = attachment ID (`SV/inc/helpers/functions.php:10, 29`).
  - **`other_document_id`** = attachment ID (`:50, 66`).
  - `rtcl_verified_seller` = `1` (`:78`).
  - Status is computed by `rtcl_sv_get_user_status($uid)`: 1 = verified, 2 = submitted, 3 = none (`:81-111`). `rtcl_sv_check_verified_user($uid)` is at `:77`.
- **Template helpers:** `rtcl_seller_verification_get_photo_id()`, `_get_the_photo()`, `_get_document_file_url()`, `_get_max_file_upload_size()`.
- **Hooks:**
  - `rtcl_account_menu_items` adds `my-documents` before `edit-account`.
  - The endpoint comes from `rtcl_advanced_settings.myaccount_documents_endpoint` (DB `my-documents`).
  - `rtcl_after_author_meta` and `rtcl_listing_seller_information` (priority 5) add the Verified badge. `rtcl_after_store_title`.
- **AJAX** (`SV/inc/hooks/RtclSellerAjaxHooks.php:9-13`): `rtcl_ajax_documents_photo_upload` (file field `banner`), `rtcl_ajax_documents_photo_delete`, `rtcl_ajax_document_file_upload` (field `document`), `rtcl_ajax_documents_file_delete`, `rtcl_ajax_documents_file_download`.
  - **Only download checks the nonce** (`__rtcl_wpnonce`).
  - Upload and delete trust a POSTed `user_id` with no nonce or capability check (lines 51, 108, 147, 206). **This is an IDOR risk.** Report it and do not rely on it from the theme.
- **Localized object:** `rtcl_seller` {`__rtcl_wpnonce`, `user_id`} (`SV/inc/init.php:56-72`).
- **REST:** `GET rtcl/v1/my/documents`, `POST …/photo_id`, `POST …/other`.

### 8.3 rtcl-verification 1.6.0 (phone OTP)

- **Templates:** none; markup is echoed inline.
- **Hooks:** `rtcl_register_form_phone_inner`/`_end`, `rtcl_before_login_form`, `rtcl_login_tab_inner_content` (OTP login tab), `rtcl_listing_form_end`, `rtcl_listing_form_phone_warning`; filters `rtcl_edit_account_phone_field`, `rtcl_registration_errors`, `rtcl_listing_form_contact_tpl_attributes`, `rtcl_verification_listing_form_phone_field` (`VER/app/Hooks/ActionHooks.php:13-39`; `FilterHooks.php:14-22, 206-232`).
- **AJAX:** `rtcl_send_otp`, `rtcl_verify_otp`, `rtcl_firebase_otp_verified` (all +nopriv), `rtcl_my_account_firebase_otp_verified`, `rtcl_send_login_otp` (nopriv). All use core `verify_nonce()`.
  - OTP login form: nonce field `rtcl-otp-login-nonce`, action `rtcl-login`.
- **Storage:** user meta `_rtcl_phone`; tables `{prefix}rtcl_phone` and `{prefix}rtcl_phone_verification`; cookie `rtcl_device_id`.
- **Settings:** `rtcl_misc_settings.verification_gateway` (DB `firebase`), `verification_expired_time` (100), `verification_post_restriction`, `enable_otp_login`, `enable_email_login`, SMS-protection keys.
- **Account menu or endpoint:** NOT FOUND.

---

## 9. Fluent Forms 6.2.15

- **Embed options:**
  - `[fluentform id="N"]` (attributes: `id`, `title`, `css_classes`, `permission`, `type`, `theme`, `permission_message`; `FF/app/Modules/Component/Component.php:474-492`).
  - PHP `fluentFormRender(['id'=>N])` (`FF/boot/globals.php:298-311`).
  - Block `fluentfom/guten-block`, widget `fluentform_widget`, Elementor widget.
- **Markup:**
  - Wrapper: `div.fluentform.ff-default.fluentform_wrapper_{id}` (+ `css_classes`; `ff-inherit-theme-style` when the form style is "Inherit Theme Style") (`FF/app/Services/FormBuilder/FormBuilder.php:144-152`).
  - Form: `form#fluentform_{id}.frm-fluent-form.fluent_form_{id}.ff-el-form-{top|left|right}`.
  - Groups and fields: `.ff-el-group`, `.ff-el-is-required`, `.ff-el-input--label`, `.ff-el-input--content`, `.ff-el-form-control`, `.ff-el-form-check(-input|-label)`, `.ff-el-help-message`, `.ff-t-container.ff-column-container > .ff-t-cell`.
  - Submit: `.ff_submit_btn_wrapper > button.ff-btn.ff-btn-submit.ff-btn-{md|lg|sm}` (`ff_btn_style` / `ff_btn_no_style` / `wpf_has_custom_css`).
  - Messages: `.ff-errors-in-stack`, `.ff-message-success`.
- **Styles:**
  - Drop `fluentform-public-default` with `add_filter('fluentform/load_default_public','__return_false')` (`Component.php:180, 616`).
  - `fluent-form-styles` (layout CSS) is always enqueued on render (`Component.php:602`). To remove it, call `wp_dequeue_style('fluent-form-styles')` late (e.g. `wp_footer` priority 1, or `wp_print_styles`).
  - Per-form option: style preset "Inherit Theme Style" (`_ff_selected_style=ffs_inherit_theme`).
  - A global "disable all styles" setting: NOT FOUND.
- **Old theme:** CSS only (`OLD/assets/css/styles.css:3320-3369, 4557-4609, 5281-5291`). There are no PHP calls.

## 10. GTranslate 5.0.1

- **Outputs:**
  - Shortcodes `[gtranslate]` / `[GTranslate]` (`GT/gtranslate.php:47-48, 260-267`) render `<div class="gtranslate_wrapper" id="gt-wrapper-{rand}">` plus the widget script.
  - `[gt-link lang="hu" widget_look="flags_name" label=""]` renders `<a href="#" data-gt-lang="hu">` (49, 269-336).
  - Widget class `GTranslateWidget`; block `gtranslate/language-switcher`; a menu item titled `[gtranslate]`; the `show_in_menu` setting; a floating selector on `wp_footer` (2241-2255).
- **Defaults in code** (`load_defaults`, 1720-1780; the real config is in option `GTranslate`, not read here):
  - `default_language` comes from the WP locale (`hu_HU` → `hu`).
  - `widget_look=float`, `floating_language_selector=no`, `enable_cdn=''`, `wrapper_selector=.gtranslate_wrapper`.
  - `incl_langs` / `fincl_langs` = `en,es,it,pt,de,fr,ru,nl,ar,zh-CN`. **Hungarian is not in the default list.**
  - `pro_version` / `enterprise_version` are off, so `url_structure=none`.
- **Scripts:**
  - Handle `gt_widget_script_{rand}`. The source is `GT/js/{look}.js`, or with CDN `https://cdn.gtranslate.net/widgets/latest/{look}.js`.
  - Inline before it: `window.gtranslateSettings[id] = {default_language, languages, url_structure, wrapper_selector, …}`.
  - The tag is rewritten to `defer`.
- **JS API (free mode):**
  - `window.doGTranslate('en|hu')` lazy-loads `https://cdn.gtranslate.net/widgets/latest/lib.min.js` and translates client-side (`GT/js/base.js:91-92`).
  - The current language is read from `<html lang>` or localStorage `__GT_TRANSLATE_LANGS`.
  - `base.js` binds every `a[data-gt-lang]` and marks the active one with `gt-current-lang`.
  - The dropdown look uses `select.gt_selector.notranslate`.
  - The `googtrans` cookie and `#googtrans(..)` hash: NOT FOUND in this version.
- **Custom switcher:**
  - (a) Render your own `<a href="#" data-gt-lang="xx" class="notranslate">` items and make sure one GTranslate script is on the page (e.g. a hidden `do_shortcode('[gt-link lang="en"]')`); or
  - (b) call `doGTranslate('hu|en')` from your own buttons; or
  - (c) in paid sub-directory mode, link to `/xx/{path}`.
  - Add the class `notranslate` to the switcher.
- **Old theme references:** NOT FOUND.

---

## 11. Old-theme leftovers to neutralise

### 11.1 Shortcodes (register no-op compat versions)

| Shortcode | Registered in | Attributes |
|---|---|---|
| `cldirectory_listing_header`, `cldirectory_listing_header_2`, `cldirectory_listing_sidebar`, `cldirectory_listing_description`, `cldirectory_listing_custom_fields`, `cldirectory_listing_food_menu`, `cldirectory_listing_video`, `cldirectory_listing_map`, `cldirectory_listing_review` | `OLD/inc/shortcode.php:24-61` (`add_shortcode` loop :84; callbacks 98-400) | **none** (they read the global `$listing`) |
| `rt_contact` | `CORE/inc/shortcode.php:10` | `address`, `mail`, `phone`, `website` (`shortcode_atts` :22-27) |
| `[listing_filters filter_group="auto|cars-for-sale"]` (in Elementor page content) | **NOT FOUND** anywhere | `filter_group` |
| wppb shortcodes (`[wppb-login]`, `[wppb-register]`, …, in Elementor data) | **plugin missing** | see §11.4 |

The theme also adds the filter `rtcl/fb/single_layout/fields` (`OLD/inc/shortcode.php:69-70`), which exposes those shortcodes in the Form Builder single layout. If any `single_layout` uses a `shortcode` element with them, the compat shortcodes must output something meaningful. Check `single_layout.fields` in the DB.

**Elementor widgets** (cldirectory-core, only relevant if Elementor stays):
- `rt-title`, `rt-video-icon`, `rt-post`, `rt-btn`, `rt-info-box`, `rt-testimonial-carousel`, `rt-testimonial-single`, `rt-parallax`, `rt-contact-info`, `rt-call-to-action`, `rt-pricing-tab`, `rt-about`, `rt-count`, `rt-image`
- RTCL-only: `rt-listing-tab`, `rt-listing-locations`, `rt-listing-location-single-box`, `rt-single-listing-category`
- Source: `CORE/elementor/init.php:59-83`

### 11.2 Insecure AJAX (CONFIRMED)

| Action | Handler | Problem |
|---|---|---|
| `wp_ajax_delete_listing_logo_attachment` | `OLD/classified-listing/custom/functions.php:39`, handler 1006-1015 | **No nonce, no capability or ownership check.** Raw `$_POST['post_id']` / `attachment_id` go to `delete_post_meta` and `wp_delete_attachment` |
| `wp_ajax_delete_food_attachment` | same file :40, handler 1328-1337 | Same, and it wipes the whole `cldirectory_food_list` meta |
| `listing_form_save` on `rtcl_listing_form_after_save_or_update` (priority 12) | :46-49, 1028-1029, 1076-1077 | Trusts the raw `$_POST['logo_attachment_id']` and food attachment IDs |
| `?export_user=1` | `CORE/cldirectory-core.php:39-41` → `demo-users/user-importer.php:116-135` | **Unauthenticated dump** of `wp_users` rows 2-9 (hashes, emails) and their usermeta into `CORE/demo-users/*.json`. **Deactivate cldirectory-core on go-live, or block the parameter.** |

Do not port these handlers. If the logo or food features are kept, reimplement them with `check_ajax_referer()` plus `current_user_can('edit_post',$id)`.

### 11.3 Meta, options and image sizes used by the old theme

- **Post meta:** `listing_logo_img` (legacy) / `listing_logo` (Form Builder); `cldirectory_food_list` / `rtcl_cldirectory_food_list` (Form Builder repeater); `listing_layout`; `rt_post_views_count` (incremented on `the_content`); `cldirectory_layout_settings`.
- **Options:** `rtcl_cldirectory_data_migrated`, `cldirectory_pre_migration_version`, `theme_mods_cldirectory`.
- **Extra keys in `rtcl_general_settings`:** `enable_restaurant_listing`, `cldirectory_food_list_section_label`, `cldirectory_top_author_roles`, `cldirectory_top_author_per_page`.
- **Image sizes:** `rdtheme-size1` (1320×655) and `rdtheme-size2` (355×275). RTCL sizes come from `rtcl_misc_media_settings.image_size_*` (gallery 600×460, thumbnail 416×270, gallery-thumb 150×105, DB).

### 11.4 Profile Builder (wppb)

- **Plugin missing:** `plugins/profile-builder` does **not exist**.
- **Code references:** `wppb` is referenced **nowhere** in the cldirectory, cldirectory-child, cldirectory-core or rt-framework code (0 hits).
- **References outside code:** they appear only in the audit data, which comes from the DB. These are the `wppb_*` options (`08-profilebuilder.json`: `wppb_version` 3.16.3, `wppb_user_pages` register 27210 / login 27211 / edit 27212 / lost-password 27213, `wppb_cr_global` redirect to /login/, `wppb-roles-editor` posts) and Elementor page data containing `[wppb-…]` shortcodes (`p.json`, e.g. Login page revisions).
- **What the theme should do:** do not emulate wppb markup. Either point those pages at RTCL's `[rtcl_my_account]` login/registration (registration endpoint `registration`, DB) or register no-op `wppb-*` shortcodes that redirect to the RTCL account page.

---

## 12. Graceful degradation: guard these symbols

| Plugin | Guard |
|---|---|
| Core | `function_exists('rtcl')` (`CL/app/Rtcl.php:601`); `defined('RTCL_VERSION')` (`CL/classified-listing.php:21`); `class_exists('Rtcl\Helpers\Functions')`, `'Rtcl\Helpers\Link'`, `'Rtcl\Models\Listing'`, `'Rtcl\Models\Form\Form'`, `'Rtcl\Services\FormBuilder\FBHelper'`, `'Rtcl\Services\FormBuilder\FBField'`, `'Rtcl\Controllers\BusinessHoursController'`, `'Rtcl\Widgets\AjaxFilter'`, `'Rtcl\Widgets\Filter'`, `'Rtcl\Controllers\Hooks\TemplateHooks'` (for `remove_action`) |
| Pro | `function_exists('rtclPro')` (`PRO/app/RtclPro.php:248`); `defined('RTCL_PRO_VERSION')`; `class_exists('RtclPro\Helpers\Fns')`, `'RtclPro\Controllers\Hooks\TemplateHooks'`, `'RtclPro\Controllers\ScriptController'` |
| Store | `function_exists('rtclStore')` (`STORE/app/RtclStore.php:182`); `defined('RTCL_STORE_VERSION')`; `class_exists('RtclStore\Helpers\Functions')` |
| Search Alert | `defined('RTCL_SEARCH_ALERT_VERSION')`; `class_exists('RTCL\SearchAlert\Helpers\RtclSearchHelper')` |
| Seller Verification | `function_exists('rtclSellerVerification')` (`SV/inc/init.php:173`); `defined('RTCL_SELLER_VERSION')`; `function_exists('rtcl_sv_check_verified_user')` |
| Verification | `function_exists('rtclVerification')`; `defined('RTCL_VERIFICATION_VERSION')` |
| Elementor builder | `function_exists('rtclElb')` |
| Review Schema | `function_exists('rtrs')` (`RS/review-schema.php:66`); `defined('RTRS_VERSION')`; Pro `defined('RTRSP_VERSION')`; `class_exists('Rtrs\Helpers\Functions')` |
| Fluent Forms | `defined('FLUENTFORM')`; `function_exists('fluentFormRender')` |
| GTranslate | `defined('GTRANSLATE_VERSION')`; `shortcode_exists('gtranslate')` |

Always wrap RTCL template overrides with `defined( 'ABSPATH' ) || exit;` and use `global $listing` (set by RTCL's loop/`setup_postdata`) or `rtcl()->factory->get_listing( get_the_ID() )`.

---

## Corrections to CURRENT-SYSTEM-AUDIT.md / SITE-MAP.md

| Doc claim | Verdict | Evidence |
|---|---|---|
| AUDIT §5.1: Classified Listing Pro version 4.2.3 | **Outdated.** Installed 4.2.5 | `PRO/classified-listing-pro.php:21` |
| AUDIT §5.1: CLDirectory Core 3.2.0 | **Outdated.** Installed 3.1.2 | `CORE/cldirectory-core.php:6` |
| AUDIT §5.3 (LIKELY): WP ProfileBuilder (wppb) present | **Partly refuted.** The plugin is **not installed**; it is configured only in DB options and Elementor content | §11.4 |
| AUDIT §5.3 (LIKELY): RtclMarketplace, RtclClaimListing | **Not installed** (no plugin folders). Old-theme templates `buy-button.php` and `claim/claim-popup-form.php` are dead | plugins dir listing |
| AUDIT §5.3: "Classified Listing Elementor Addon (`cldirectory-core`)" | **Corrected.** RTCL Elementor single/archive templates come from **rtcl-elementor-builder 3.2.0**; cldirectory-core only adds `rt-*` widgets and Elementor field filters | §2.4, §11.1 |
| AUDIT §5.4: "Listing egyedi mezők: Pro / Toolkits" (custom listing fields come from Pro / Toolkits) | **Refuted.** Form Builder custom fields are **core** (`rtcl_forms`). Pro adds only filterable/listable extras. Toolkits has only Elementor/Divi widgets | §4.1; `TK/includes/Hooks/*` empty |
| AUDIT §5.4 (LIKELY): Moderation by Pro | **Refuted.** Core handles it: `rtcl_general_settings.new_listing_status` / `edited_listing_status` (DB `pending`/`pending`) | `FormBuilderAjax.php:168-177` |
| AUDIT §5.4: "Regisztráció: wppb" (registration is handled by wppb) | **Refuted for current code.** RTCL registration is built in (`rtcl_account_settings.enable_myaccount_registration=yes`, separate `registration` endpoint, user types buyer/seller); wppb is absent | §3.3, §6 |
| AUDIT §8.1: Template (single) "+ rtcl_builder per kategória" (one rtcl_builder per category) | **Corrected.** It is per **form** (`_rtcl_form_id`), not per category: options `rtcl_tb_template_default_single_{formId}` | `ELB/app/Traits/ELTempleateBuilderTraits.php:40-56` |
| AUDIT §8.1: Form template `classified-listing/listing-form/form.php` | **Outdated.** With the Form Builder on, it is the React app in `listing-form/form-builder.php`; `form.php` is legacy | `ListingForm.php:46-50` |
| AUDIT §8.2 (LIKELY): slug `rtcl_store` | **Refuted.** Post type is **`store`**, taxonomy `store_category`, registered only if `enable_store=yes` (DB off) | `STORE/app/RtclStore.php:28-29`; `STORE/app/Controllers/Hooks/Init.php:140-144` |
| AUDIT §8.3 (LIKELY): `rtcl_builder` registered by Pro | **Refuted.** Registered by **rtcl-elementor-builder** | `ELB/app/Traits/ELTempleateBuilderTraits.php:26`; `TemplateBuilder.php:36` |
| AUDIT §9 (LIKELY): Jobs/Recruiting category | **Confirmed (DB).** Form 2 "Post a Job" (default form) plus builder 5409 "Post a job single" | 09-rtcl-forms.json; option `rtcl_tb_template_default_single_2` |
| AUDIT §9: taxonomies only `rtcl_category`, `rtcl_location` | **Incomplete.** Also `rtcl_tag` (query var `rtcl_tag`, base `listing-tag`) | `RegisterPostType.php:147-161` |
| AUDIT §13 (LIKELY): user meta from wppb fields | **Unverifiable in code** (plugin missing). RTCL user meta confirmed: `_rtcl_phone`, `_rtcl_whatsapp_number`, `_rtcl_website`, `_rtcl_pp_id`, `_rtcl_address`, `_rtcl_user_type`, `_rtcl_display_{phone,whatsapp,email}_public`, `_rtcl_latitude/_longitude/_geo_address`, `rtcl_verification_key`, seller docs `photo_id`, `other_document_id`, `rtcl_verified_seller` | §6, §8 |
| AUDIT §17 step 4: form `<form id="rtcl-post-form">` with category AJAX `wp_ajax_rtcl_custom_fields_listings` | **Outdated (legacy only).** Currently it is the React Form Builder saving via `rtcl_update_listing`. `rtcl_custom_fields_listings` is an admin-side legacy handler (`CL/app/Controllers/Ajax/ListingAdminAjax.php:11`) | §4.2 |
| AUDIT §17 step 7: post status UNKNOWN | **Resolved.** `pending` for both new and edited listings (DB) | §6 |
| AUDIT §18: edit URL UNKNOWN | **Resolved.** `{listing-form-page}/edit/{id}/` (pretty) or `?rtcl_action=edit&rtcl_listing={id}` | `Link.php:279-293`; `Query.php:124-127` |
| AUDIT §19: endpoints UNKNOWN | **Resolved.** See §3.3: listings, favourites (off), payments (off), edit-account, privacy-settings, lost-password, logout, registration, chat, verify, search-alert, my-documents, store (off). Checkout: submission, promote, payment-receipt, payment-failure, membership | §3.3 |
| AUDIT §20 (LIKELY): plugin uses `pre_get_posts` to turn GET into meta_query | **Confirmed** | `Query.php:36-37, 662-704, 714-1136` |
| AUDIT §20: custom field filters `filters[_field_N]` | **Outdated.** That is the legacy format. Form Builder filters are `filters[{field_name}]` or `cf_{field_name}` | §3.6 |
| AUDIT §20: `Functions::get_cf_ids(['is_searchable'=>true])` | **Legacy only.** Form Builder uses the `filterable` flag | §4.6 |
| AUDIT §20: `[listing_filters filter_group]` from Toolkits (UNKNOWN) | **Refuted.** No such shortcode in Toolkits or any installed plugin/theme; filter configs live in `rtcl_filter_settings` (`job-filter`, `cars-for-sale-filter`) for the AjaxFilter widget | §4.6 |
| AUDIT §20: price filter uses ion.rangeSlider | **Not verified**; no ion.rangeSlider handle in the core ScriptLoader. The ajax filter uses its own range UI | ScriptLoader.php |
| AUDIT §21: gallery uses Swiper.js | **Confirmed** (`swiper` 7.4.1 via `rtcl-single-listing`; Pro adds PhotoSwipe and zoom) | §5.1 |
| AUDIT §22 (LIKELY): moderation via Pro / claim plugin | **Refuted.** Core moderation (`pending`); the claim plugin is not installed | §6 |
| AUDIT §23 (LIKELY): plugin AJAX handlers | **Resolved.** Form submit `rtcl_update_listing`; autocomplete `rtcl_inline_search_autocomplete` / `rtcl_json_search_taxonomy`; contact `rtcl_public_send_contact_email`; report `rtcl_public_report_abuse`; favourites `rtcl_public_add_remove_favorites` (**core**, not Pro); delete `rtcl_delete_listing`; phone reveal `rtcl_phone_whatsapp_revealed`; filter `rtcl_ajax_filter_load_data` | `CL/app/Controllers/Ajax/PublicUser.php:40-91`; `InlineSearchAjax.php:29-37` |
| AUDIT §23: compare uses `$_SESSION['rtcl_compare_ids']` | **Corrected.** It uses `rtcl()->session` key `rtcl_compare_ids` (RTCL session handler, not PHP `$_SESSION`); compare is disabled in the DB | `PRO/app/Controllers/CompareController.php:48-120` |
| AUDIT §23: RTCL REST UNKNOWN | **Resolved.** Yes, namespace `rtcl/v1` (57 `register_rest_route` calls across core and Pro, plus SA, SV and VER routes) | §8 |
| AUDIT §23: theme AJAX `delete_listing_logo_attachment` / `delete_food_attachment` lack nonces | **Confirmed**, and they also lack capability/ownership checks | §11.2 |
| AUDIT §24: `rtcl_store` post type | **Refuted** (it is `store`) | above |
| AUDIT §24 / §864 (LIKELY): `listing_logo` vs `listing_logo_img` depends on Form Builder mode | **Confirmed** | `OLD/classified-listing/custom/listing-heading.php:34-40` |
| AUDIT §866: geo search mode UNKNOWN | **Resolved (DB).** `location_type=local` (taxonomy locations); radius search params still accepted | §6 |
| SITE-MAP: listing URL `/listing/{slug}/` | **Refuted (DB).** `/listings/{slug}/` (`rtcl_advanced_settings.permalink=listings`) | `UtilityTrait.php:288-316` |
| SITE-MAP: archive `?q=…&category=…&location=…` | **Corrected.** Use `q`, `rtcl_category`, `rtcl_location` (slugs); the search JS rewrites to `/{listings-page}/listing-category/{cat}/listing-location/{loc}/`. `category`/`location` aliases work only off the Listings page | §3.6 |
| SITE-MAP: `/fiokom/favourites/` | **Currently inactive.** The endpoint is removed because `has_favourites` is off (DB) | `AppliedBothEndHooks.php:246-249` |
| SITE-MAP: `/fiokom/payments/` | **Currently inactive.** Payments are disabled (DB) | `AppliedBothEndHooks.php:242-244` |
| SITE-MAP: `/fiokom/profile-settings/` | **Refuted (DB).** The slug is `/privacy-settings/` (key `profile-settings`) | `Functions.php:479`; DB |
| SITE-MAP: `/fiokom/add-listing/` | **Refuted.** `add-listing` is not an endpoint; the menu links to the listing-form page (ID 212, DB) | `Functions.php:482` |
| SITE-MAP: `/fiokom/listing/{id}/edit/` | **Refuted.** It is `{listing-form-page}/edit/{id}/` | `Link.php:289` |
| SITE-MAP: `/fiokom/chat/`, `/fiokom/search-alert/`, `/fiokom/my-documents/`, `/fiokom/edit-account/`, `/fiokom/listings/` | **Confirmed** (slugs from the DB / hard-coded) | §3.3 |
| SITE-MAP: `/fiokom/store/` | **Inactive.** The endpoint exists only when the store is enabled (DB off) | `STORE RtclApplyHook.php:376-377` |
| SITE-MAP: `/stores/`, `/store/{slug}/` "not active" | **Confirmed inactive.** Slugs would be `store` (`permalink_store`) with archive at the Store page or `stores` | `STORE Init.php:62-73` |
| SITE-MAP: verify link `/fiokom/?action=verify&token=…` | **Refuted.** It is `{account}/verify/?user_id={id}&verify_email={32-hex}` | `PRO/app/Emails/UserVerifyLinkEmailToUser.php:61-64`; `UserAuthentication.php:58-60` |
| SITE-MAP: `/rtcl-checkout/` | **Unverifiable in code.** The checkout page is ID 210 (DB); its endpoints are `submission`, `promote`, `payment-receipt`, `payment-failure`, `membership` | `Functions.php:489-498` (`get_checkout_page_endpoints`) |
| SITE-MAP: login `/bejelentkezes/`, registration `/regisztracio/` | **wppb pages (27210/27211, DB), plugin missing.** RTCL's own login is the account page; registration is `{account}/registration/` | §3.3, §11.4 |
| SITE-MAP: admin `edit.php?post_type=rtcl_cf` / `rtcl_cfg` used for fields | **Outdated.** With the Form Builder active, fields live in the `rtcl_forms` table (admin Form Builder page); `rtcl_cf`/`rtcl_cfg` are legacy | §4.1 |

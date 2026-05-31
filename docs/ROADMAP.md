# Shuriken Reviews Roadmap

What's planned and why. For deep details, see:
- Hooks/API: [guides/hooks-reference.md](guides/hooks-reference.md)
- Architecture: [ARCHITECTURE.md](ARCHITECTURE.md)
- Guides: [guides/dependency-injection.md](guides/dependency-injection.md), [guides/exception-handling.md](guides/exception-handling.md), [guides/testing.md](guides/testing.md)

---

## In Progress

- [ ] **PHPUnit test suite** — proper test configuration and unit tests

---

## Shipped

### Shortcode / Block Feature Parity

- `[shuriken_grouped_rating]`: `gap` attribute (maps to `--shuriken-gap`) + `button_color` (maps to `--shuriken-button-color`)
- `[shuriken_rating]`: `button_color` attribute (maps to `--shuriken-button-color`)
- Single rating block (`shuriken-rating`): `buttonColor` attribute with Button Color swatch in Colors panel (only visible when type is `numeric`); emitted as `--shuriken-button-color` in PHP render callback

### Contextual Voting (DB v1.6.0)

Ratings are created once and reused across any number of posts. Votes are scoped per post via `context_id` / `context_type` columns on the votes table — one grouped rating with all its sub-ratings serves every post in a template; each post gets its own independent vote tallies.

- `context_id` (BIGINT) + `context_type` (VARCHAR 50) columns on `wp_shuriken_votes`
- Unique key updated to `(rating_id, user_id, user_ip, context_id, context_type)` via COALESCE for NULL safety
- `get_contextual_stats()` / `get_contextual_stats_batch()` methods on the Database service
- `GET /ratings/stats` REST endpoint accepts optional `context_id` + `context_type`
- Both single and grouped rating blocks expose a **"Per-post voting"** toggle (`postContext` attribute)
- Block `usesContext: ["postId", "postType"]` — PHP render reads FSE context and passes it through the entire stack
- Frontend JS groups on-page ratings by context and makes batched stats requests per group
- `shuriken_allowed_context_types` filter controls which post types are accepted (default: `post`, `page`, `product`)
- Post Linked Ratings block removed — superseded by `postContext` mode
- Content injection disabled by default — superseded by per-post contextual blocks

### Admin & Editor Enhancements (v1.14.4)

Per-post voting visibility — admin pages and the block editor now surface contextual vote data.

- **Block editor sidebar panel** — `PluginDocumentSettingPanel` fetches `GET /context-stats` and shows per-post rating stats when editing any post that has contextual votes
- **Archive sorting** — `pre_get_posts` hook sorts archive pages by contextual rating scores; configurable via Settings → General (rating selector, order by average or votes)
- **Ratings management indicators** — Type column shows a 📍 badge with distinct-post count for any rating that has received contextual votes
- **Analytics context column** — Recent Activity table includes a Context column showing the post title (linked to edit screen) for contextual votes, or "Global" for non-contextual ones
- **Contextual Posts card** — Analytics overview grid shows a "Posts with Per-Post Votes" stat card when contextual votes exist
- `get_context_usage_counts()` / `get_ratings_for_context()` DB methods + interface additions
- `GET /shuriken-reviews/v1/context-stats` REST endpoint (editor-only, `can_edit_posts` permission)

### Modern PHP & Architecture (v1.15.5)

Steps 1–5 and the majority of Step 6 shipped in 1.15.5.

#### Step 1 — `RatingType` Backed Enum ✅

`Shuriken_Rating_Type` backed enum shipped in `includes/enum-shuriken-rating-type.php`. Cases: `Stars`, `LikeDislike`, `Numeric`, `Approval`. Methods: `isBinary()`, `maxScale()`, `constrainScale()`, `typeClass()`, `values()`. Adopted across 13 files — `get_type_class()` deleted from REST API, all `$allowed_types` arrays and binary guards replaced.

#### Step 2 — First-Class Callables for Hooks ✅

Replaced all `array($this, 'method_name')` callback syntax with `$this->method(...)` first-class callables across 6 classes: `Shuriken_Admin` (19), `Shuriken_REST_API` (30), `Shuriken_Block` (5), `Shuriken_AJAX` (2), `Shuriken_Shortcodes` (2), `Shuriken_Frontend` (2). `Shuriken_Rate_Limiter` had none. Zero logic changes — pure syntax sweep.

#### Step 3 — `readonly` Properties + Constructor Property Promotion ✅

Applied CPP + `readonly` to 5 classes: `Shuriken_Admin` (2 promoted props), `Shuriken_Block`, `Shuriken_AJAX`, `Shuriken_Shortcodes` (1 each), `Shuriken_Analytics` (1 promoted + 3 readonly derived). `Shuriken_Frontend` has no injected deps — skipped. Constructor params made non-nullable (singletons always pass resolved instances). Also fixed all `@since 1.15.0` → `1.15.5` (33 occurrences).

#### Step 4 — `Shuriken_Database` Repository Decomposition ✅

Decomposed the ~1,694-line monolithic `Shuriken_Database` class into three focused repository classes + a slim delegation façade. All classes use CPP + `readonly` constructors. `Shuriken_Database_Interface` kept intact — façade implements it for full backward compatibility. Zero public API changes; callers use `shuriken_db()` as before.

| New class | Lines | Responsibility |
|---|---|---|
| `Shuriken_Rating_Repository` | ~1,041 | Rating CRUD, search, pagination, hierarchy, mirrors, contextual stats, export |
| `Shuriken_Vote_Repository` | ~326 | Vote CRUD, rate-limit timestamp queries, transactional vote+total updates |
| `Shuriken_Schema_Manager` | ~204 | `create_tables()`, `tables_exist()`, column migrations |
| `Shuriken_Database` (façade) | ~401 | Singleton, constants, static helpers, delegates all 28 interface methods to repos |

All 10 callers now type-hint the specific repository they need. Per-repo helper functions added: `shuriken_ratings_repo()`, `shuriken_votes_repo()`, `shuriken_schema_manager()`. Container bindings updated. `Shuriken_Database_Interface` and the façade kept for backward compatibility (`shuriken_db()` still works).

#### Step 5 — `Shuriken_REST_API` Controller Split ✅

Split the ~1,046-line monolithic `Shuriken_REST_API` class into two focused controllers + a thin bootstrap. Both controllers use CPP + `readonly` constructors and own their route registration, arg schemas, and permission callbacks. Cross-cutting filters (auth bypass, output buffer cleaning, CDN cache headers) remain on the bootstrap. Zero public API or hook changes.

| New class | Lines | Responsibility |
|---|---|---|
| `Shuriken_REST_Ratings_Controller` | ~689 | 11 rating endpoints: CRUD, hierarchy, mirrors, search, batch + arg schemas + permissions |
| `Shuriken_REST_Votes_Controller` | ~268 | 3 endpoints: stats (public), context-stats (editor), nonce (public) |
| `Shuriken_REST_API` (bootstrap) | ~210 | Singleton, controller wiring, `register_routes()` delegation, REST filters |

#### Step 6 — Coding Standards & DRY Sweep (partial ✅)

##### 6a — `Shuriken_Analytics` Decomposition ✅

| Class | Lines | Responsibility |
|---|---|---|
| `Shuriken_Analytics` (coordinator) | ~1,748 | Core + dashboard analytics, delegates formatting, ranking, and context queries |
| `Shuriken_Analytics_Formatter` | ~153 | `format_average_display()`, `format_vote_display()`, `format_time_ago()`, `format_date()`, `get_date_range_label()` |
| `Shuriken_Analytics_Ranking` | ~184 | `get_top_rated()`, `get_most_voted()`, `get_low_performers()` — consolidated into single parametric `get_ranked()` with `get_inversion_sql()` static helper |
| `Shuriken_Analytics_Context` | ~587 | 12 per-post/contextual methods |

7 pairs of scoped/base method duplicates merged; 12 contextual methods (660 lines) moved to `Shuriken_Analytics_Context`. `is_binary_type()` and `build_empty_distribution()` promoted to `Shuriken_Analytics_Helpers` trait.

- **Decomposed `get_parent_rating_stats_breakdown()`** ✅ — decomposed into four focused private methods (`get_direct_votes_breakdown()`, `calculate_sub_ratings_rating_totals()`, `get_sub_ratings_breakdown()`, `combine_votes_breakdown()`), reducing the main method's footprint to under 65 lines.
- **Split jumbo `Shuriken_Analytics_Interface`** ✅ — split into three focused sub-interfaces (`Shuriken_Analytics_Formatter_Interface`, `Shuriken_Analytics_Ranking_Interface`, `Shuriken_Analytics_Context_Interface`); the core `Shuriken_Analytics_Interface` now `extends` all three, preserving the full 54-method contract for backward compatibility. The three concern classes (`Shuriken_Analytics_Formatter`, `Shuriken_Analytics_Ranking`, `Shuriken_Analytics_Context`) now `implements` their respective sub-interface, so add-on decorators can implement only the slice they need.

##### 6b — Admin Template DRY ✅ (core items)

- Extracted `partials/pagination.php`, `partials/date-filter-bar.php`, `partials/votes-table.php`
- Extracted helpers: `shuriken_format_rating_value()`, `shuriken_render_voter_cell()`
- **Extracted chart init to `assets/js/admin-charts.js`** ✅ — the near-identical Chart.js setup blocks (~450 lines across item-stats, context-stats, and voter-activity) are now factory functions (`initApprovalRing`, `initApprovalTrend`, `initCumulative`, `initDistribution`, `initDualAxis`, `initTopContexts`, `initContextAvgDist`, `initContextActivity`, `initVoterDistribution`, `initVoterActivity`). The item-stats global view and context-stats share a single `initTypeAwareCharts(data, ids)` dispatcher. Pages now emit only a small inline data object (the same pattern as the analytics dashboard) and the new script auto-initializes from it.

##### 6e — JS Modernization ✅

All 10 project JS files modernized: 135 `var` → `const`/`let`, arrow functions, template literals, `e.key` over `e.which`.

**Still open in 1.15.x:**
- [ ] **Optional chaining** — `typeof x !== 'undefined'` checks where `x?.prop` suffices

#### Step 7 — Platform & Add-on Extensibility ✅

All hook, filter, and action slots shipped in v1.15.5 for fully decoupled third-party add-ons.

**Admin UI:** `shuriken_admin_submenu`, `shuriken_ratings_columns` (filter), `shuriken_after_ratings_list`, `shuriken_after_analytics_overview`, `shuriken_after_settings_card`, `shuriken_settings_sidebar_{tab}`, `shuriken_save_settings`

**REST API:** `shuriken_rest_register_routes`, `shuriken_rating_stats_response` (filter), `shuriken_stats_permission_callback` (filter — replaces hard-coded `__return_true`)

**Analytics:** `shuriken_overall_stats`, `shuriken_top_rated`, `shuriken_most_voted`, `shuriken_low_performers` output filters; `Shuriken_Analytics_Extension_Interface` for third-party stats decorators.

**Frontend / Blocks:** `shurikenVoteRequest` filter + `shurikenVoteSuccess` action (via `wp.hooks`); `shurikenBlockSettings_rating` + `shurikenBlockSettings_groupedRating` registration filters; `shuriken_block_view_data` filter + per-block `wp_localize_script`.

**Lifecycle / AJAX / DI:** `shuriken_deactivate`, `shuriken_container_ready`, `shuriken_uninstall`, `shuriken_ajax_register_handlers`; opt-in "Delete Data on Uninstall" toggle (Settings → General → Data Management).

---

## Up Next

### 1.15.x — Remaining Code Quality & Extensibility Work

#### Step 6 (remaining) — Coding Standards & DRY Sweep

##### 6c — Block JS Decomposition (grouped-rating: 1,797 → 904 lines) ✅

`blocks/shuriken-grouped-rating/index.js` was the largest JS file — a single `edit()` function with 40+ `useState` hooks, 30+ handlers, and 3 inline modals. The three modals and the Inspector panels are now extracted into `blocks/shuriken-grouped-rating/components/`, bringing the file from 1,797 to 904 lines. Each extracted component is a plain function that receives a single `ctx` object carrying the `edit()`-local values/handlers it needs, keeping it decoupled from the closure.

- [x] **Consolidate form state with `useReducer`** — the create/edit/add-child form clusters (the bulk of the `useState` sprawl) are now backed by reducers. The grouped block uses three reducers (`parentForm`, `editParentForm`, `childForm` via `groupFormReducer`) and the single-rating block uses two (`createForm`, `editForm` via `ratingFormReducer`), each with `SET_FIELD`/`MERGE`/`RESET` transitions and a small `setXField(field, value)` helper. Field/reset logic is co-located instead of scattered across a dozen setters. The grouped block keeps the same `modalCtx` keys (mapped to reducer reads + setter wrappers), so the extracted modal components needed zero changes. Remaining `useState` hooks are genuinely independent UI flags (open/creating/updating/drag/search), which is the idiomatic use of `useState`.
- [x] **Extract modal components** — `CreateParentModal`, `EditParentModal`, `ManageChildrenModal` moved to `blocks/shuriken-grouped-rating/components/*.js` (172 / 255 / 457 lines). Inspector (sidebar) panels also extracted to `components/inspector-panels.js` (282 lines).
- [x] **Extract shared rating type/scale fields** — the duplicated "Rating Type" + "Scale" control pair (with its type-aware scale-clamp logic) is now a single `renderRatingTypeScaleFields(opts)` helper in `block-helpers.js`, reused across all five call sites (single create/edit, grouped parent create/edit, and the manage-children add + per-child rows). A full `<CreateRatingForm>` was still not a clean extraction — the single-rating form carries mirror/parent/effect logic the grouped form lacks — but the type/scale pair was the genuinely shared, high-value slice.
- [x] **Extract `useApiErrorHandling()` hook** — centralized in `block-helpers.js` (`useState` + `makeErrorHandler` + `makeErrorDismissers` + `retryLastAction`); both block `edit()` functions now consume it.

##### 6d — Block Build Toolchain: `@wordpress/scripts` + JSX ✅

The blocks already use React (`wp.element` is React) and React hooks, but previously without a build step — elements were created via `wp.element.createElement`, all `wp.*` packages were consumed as runtime globals, and every block lived in a single file because there was no module system. Adopting `@wordpress/scripts` adds the missing build layer (webpack + Babel/JSX) and automatically externalises all `wp.*` packages so bundle sizes stay small.

- [x] **Add `package.json` + install `@wordpress/scripts`** — added as a dev dependency with `build`/`start`/`lint` scripts.
- [x] **Add `webpack.config.js` entry points** — one entry per block plus the shared store/helpers (6 entries → `build/`).
- [x] **Replace IIFEs with ES module imports** — blocks now `import` from `@wordpress/*`; webpack externalises these back to the `wp.*` globals, so runtime behaviour is identical. Within-bundle component files use ES imports; cross-bundle shared modules still publish `window` globals to preserve load order. A small `const wp = { element: { createElement, Fragment } }` shim keeps the existing `createElement` call sites working without a full JSX rewrite.
- [x] **Update block script registration** — `includes/class-shuriken-block.php` now registers the compiled `build/` outputs (each with its generated `index.asset.php` dependency array).
- [x] **Update `.gitignore`** — excludes `node_modules/` and `build/`.

> **Why before 6c:** This was a prerequisite for 6c. Extracting modal/inspector components into separate files is only tractable with ES module imports. No PHP runtime, REST API, or frontend runtime behaviour changed — this is purely an editor-toolchain change.

##### 6f — Frontend JS & CSS Cleanup ✅

- [x] **Define constants for selectors and timeouts** — `SELECTORS` (`.shuriken-rating`, `.rating-stats`) and `TIMEOUTS` (`fade`, `buttonPulse`, `thankYou`, `feedback`, `starRefresh`) objects added at module scope in `shuriken-reviews.js` and applied across all call sites.
- [x] **Fix `setInterval` memory leak** — `shuriken-reviews.js` set up polling intervals cleaned only by `MutationObserver` on DOM removal, which doesn't fire on client-side page navigation. Added a `ratingIntervals` registry plus a `wp-js-interactivity:navigated` cleanup handler that clears intervals for detached rating elements.
- [x] **Remove `getTypeClass()` duplication** — kept intentionally local. `admin-ratings.js` only depends on `jquery`; the shared `block-helpers.js` carries block-editor globals (`wp.element`, `wp.i18n`, `wp.components`), so loading it on the admin ratings screen to share a one-line pure function would be over-coupling.
- [x] **Remove unused `useRef` import** — verified in use (`block-helpers.js` line 183, `useSearchHandler`); no change needed. Roadmap note was stale.
- [x] **Audit unused CSS classes** — verified `.rating-text` (admin ratings template) and `.display-only-notice` (shortcodes + frontend styles) are both in active use; no removal needed. Roadmap note was stale.
- [x] **Optional chaining** — replaced `typeof x !== 'undefined'` / `a && a.b` guards with optional chaining (`window.wp?.hooks?.applyFilters`, `nonceResponse?.nonce`, `xhr.responseJSON?.data`) throughout `shuriken-reviews.js`.

#### Step 8 — Performance

- [ ] Server-side render pre-fetch — batch query on frontend page load to avoid per-block queries
- [ ] Statistics caching — TTL-based cache service in container; invalidate on vote change; optional Redis support
- [ ] Rate limit performance caching — cache vote counts in transients per user/IP with TTL; invalidate on new vote

> **Why eighth:** Stable service boundaries from Steps 4–5 and clean code from Step 6 make a cache service cleanly injectable without coupling it to bloated classes.

### Known bugs and Gaps

- [ ] FSE blocks Preview only shows the state of block where no Rating is selected
- [ ] **Contextual ratings for WordPress comments** — add first-class support for comment-level context (e.g. `context_type=comment`, `context_id=<comment_id>`) across validation defaults, editor/shortcode UX, and analytics surfaces
- [x] **Rating label description** — optional description text displayed beneath a rating's title; stored as a `label_description` field on the rating; exposed in block editor, shortcodes, and REST API
- [x] **Hide title & description** — `hideTitle` block attribute and `hide_title` shortcode attribute suppress the rating name and description; particularly useful in Query Loop layouts where each item shouldn't repeat the rating name
- [ ] **Star rating with multiple icons** — the current star rating type only supports a single icon for all stars. We want to support multiple icons (e.g. 1 star = 😡, 2 stars = 🙁, 3 stars = 😐, 4 stars = 🙂, 5 stars = 😍) with a mapping of icon per rating value. This is a separate system from Emoji reactions system.
- [x] **Date filter not working on contextual item-stats page** — the time period `<select>` change handler was only wired up in the global view's `<script>` block; the contextual view's script was missing the jQuery bindings entirely. Fixed by centralizing into `admin-analytics.js`.
- [x] **Best Performing avg wrong for binary types** — `get_rating_context_summary()` used `denormalize_average()` on like/dislike's 0–1 ratio, showing `0.2` instead of `100%`. Fixed to compute percentage for binary types.
- [x] **Admin analytics JS DRY cleanup** — Consolidated 5 duplicate date-range filter handlers, 4 duplicate `formatDate()` definitions, 3 duplicate clickable-row handlers, 2 duplicate `shuriken_sort_link()` definitions, and hardcoded chart color constants. Shared utilities now live in `admin-analytics.js` (`initDateRangeFilter`, `initClickableRows`, `formatDate`, `colors`) with `shurikenAnalyticsShared` localized i18n. `shuriken_sort_link()` moved to `class-shuriken-admin.php` as a global helper.

---

## Later

### Engagement & Analytics
- [ ] Mirror vote tracking — mirror vs. original vote breakdown, per-mirror stats, comparison view, CSV export
- [ ] Engagement factor metric — new field on stats response; formula based on votes-to-views ratio; configurable thresholds for "high engagement" badges in analytics, and potential frontend display, a base for social-network algorithmic sorting features in the future

### Content Features
- [ ] Rating notes/comments — notes table + CRUD; frontend UI; admin moderation; REST endpoints
- [ ] Votes & notes management — admin listing/search; bulk operations; exports; "my activity" view for users
- [ ] Emoji reactions — separate system from rating types
- [ ] **HTML embed code** — `GET /ratings/{id}/embed` REST endpoint returns a self-contained `<iframe>` snippet (similar to Google Maps embed); block editor and admin ratings page surface a "Get embed code" button with a copy-to-clipboard UI
- [x] **Shortcode contextual support** — extend `[shuriken_rating]` and `[shuriken_grouped_rating]` shortcodes with `context_id` and `context_type` attributes so contextual voting works outside the block editor

### Internationalization
- [ ] Alternative calendar display hook — `shuriken_display_date` filter; route all dates through helper (Jalali/Shamsi)
- [ ] Native multilingual support — WPML/Polylang compatibility for rating names/descriptions.

---

## 2.0.0+ (Future)

### Email Notifications
- Notify admins on low ratings; digest emails

### Webhook Integration
- POST rating events to external services; retry/failure handling

---

## Backlog & Research

- [ ] Rate limiting defaults that fit common sites
- [ ] Best caching strategy for rating stats
- [ ] Webhook retry guarantees and failure modes
- [ ] Email template customization approach

---

## Breaking Changes

None planned. Backward compatibility is a goal for upcoming versions.

---

## Support

- GitHub Issues: https://github.com/Skilledup/shuriken-reviews/issues
- Documentation index: [INDEX.md](INDEX.md)

---

## License

Licensed under [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html)

Developed by [Skilledup](https://skilledup.ir)

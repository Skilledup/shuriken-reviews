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

---

## Up Next

### 1.15.5 — Modern PHP & Architecture

Items are ordered by dependency and impact. The enum is the load-bearing foundation for everything — decomposing without it means string guards get duplicated into each new file. Callables and CPP are quick sweeps done while the class shapes are still stable. The two decompositions follow in dependency order (DB before REST). Platform extensibility is easiest to do surgically once the classes are small. Performance caps the series with a stable service layer to hang caches on.

#### ~~Step 1 — `RatingType` Backed Enum~~ ✅

`Shuriken_Rating_Type` backed enum shipped in `includes/enum-shuriken-rating-type.php`. Cases: `Stars`, `LikeDislike`, `Numeric`, `Approval`. Methods: `isBinary()`, `maxScale()`, `constrainScale()`, `typeClass()`, `values()`. Adopted across 13 files — `get_type_class()` deleted from REST API, all `$allowed_types` arrays and binary guards replaced.

#### ~~Step 2 — First-Class Callables for Hooks~~ ✅

Replaced all `array($this, 'method_name')` callback syntax with `$this->method(...)` first-class callables across 6 classes: `Shuriken_Admin` (19), `Shuriken_REST_API` (30), `Shuriken_Block` (5), `Shuriken_AJAX` (2), `Shuriken_Shortcodes` (2), `Shuriken_Frontend` (2). `Shuriken_Rate_Limiter` had none. Zero logic changes — pure syntax sweep.

#### ~~Step 3 — `readonly` Properties + Constructor Property Promotion~~ ✅

Applied CPP + `readonly` to 5 classes: `Shuriken_Admin` (2 promoted props), `Shuriken_Block`, `Shuriken_AJAX`, `Shuriken_Shortcodes` (1 each), `Shuriken_Analytics` (1 promoted + 3 readonly derived). `Shuriken_Frontend` has no injected deps — skipped. Constructor params made non-nullable (singletons always pass resolved instances). Also fixed all `@since 1.15.0` → `1.15.5` (33 occurrences).

#### ~~Step 4 — `Shuriken_Database` Repository Decomposition~~ ✅

Decomposed the ~1,694-line monolithic `Shuriken_Database` class into three focused repository classes + a slim delegation façade. All classes use CPP + `readonly` constructors. `Shuriken_Database_Interface` kept intact — façade implements it for full backward compatibility. Zero public API changes; callers use `shuriken_db()` as before.

| New class | Lines | Responsibility |
|---|---|---|
| `Shuriken_Rating_Repository` | ~1,041 | Rating CRUD, search, pagination, hierarchy, mirrors, contextual stats, export |
| `Shuriken_Vote_Repository` | ~326 | Vote CRUD, rate-limit timestamp queries, transactional vote+total updates |
| `Shuriken_Schema_Manager` | ~204 | `create_tables()`, `tables_exist()`, column migrations |
| `Shuriken_Database` (façade) | ~401 | Singleton, constants, static helpers, delegates all 28 interface methods to repos |

> ~~**Adoption gap (deferred to PHPUnit milestone):**~~ ✅ All 8 callers now type-hint the specific repository they need instead of the 28-method `Shuriken_Database_Interface`. Per-repo helper functions added: `shuriken_ratings_repo()`, `shuriken_votes_repo()`, `shuriken_schema_manager()`. Container bindings updated. `Shuriken_Database_Interface` and the façade kept for backward compatibility (`shuriken_db()` still works).
>
> | Caller | Was | Now |
> |---|---|---|
> | `Shuriken_Admin` | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` |
> | `Shuriken_Block` | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` |
> | `Shuriken_Shortcodes` | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` |
> | `Shuriken_REST_Ratings_Controller` | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` |
> | `Shuriken_REST_Votes_Controller` | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` |
> | `Shuriken_REST_API` (bootstrap) | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` |
> | `Shuriken_AJAX` | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` + `Shuriken_Vote_Repository` |
> | `Shuriken_Rate_Limiter` | `Shuriken_Database_Interface` | `Shuriken_Vote_Repository` |
> | `Shuriken_Analytics` | `Shuriken_Database_Interface` | `Shuriken_Rating_Repository` |
> | `Shuriken_Voter_Analytics` | `Shuriken_Database_Interface` | `\wpdb` + table names directly |

#### ~~Step 5 — `Shuriken_REST_API` Controller Split~~ ✅

Split the ~1,046-line monolithic `Shuriken_REST_API` class into two focused controllers + a thin bootstrap. Both controllers use CPP + `readonly` constructors and own their route registration, arg schemas, and permission callbacks. Cross-cutting filters (auth bypass, output buffer cleaning, CDN cache headers) remain on the bootstrap. Zero public API or hook changes.

| New class | Lines | Responsibility |
|---|---|---|
| `Shuriken_REST_Ratings_Controller` | ~689 | 11 rating endpoints: CRUD, hierarchy, mirrors, search, batch + arg schemas + permissions |
| `Shuriken_REST_Votes_Controller` | ~268 | 3 endpoints: stats (public), context-stats (editor), nonce (public) |
| `Shuriken_REST_API` (bootstrap) | ~210 | Singleton, controller wiring, `register_routes()` delegation, REST filters |

#### Step 6 — Coding Standards & DRY Sweep

Full codebase audit identified **~4,200+ lines** of redundancy, bloat, and maintainability debt across PHP, JS, and admin templates. Items ordered by impact and dependency — decompositions first (they unblock later DRY work), then template/JS cleanup.

##### ~~6a — `Shuriken_Analytics` Decomposition (2,608 → ~1,300 lines)~~ ✅

The largest file in the codebase. Single class responsible for formatting, ranking, contextual analytics, pagination, and chart data preparation. Decomposed into three focused classes + a slimmed-down coordinator.

| Class | Lines | Responsibility |
|---|---|---|
| `Shuriken_Analytics` (coordinator) | ~2,186 | Core analytics queries, delegates formatting + ranking, scoped methods merged |
| `Shuriken_Analytics_Formatter` | ~153 | `format_average_display()`, `format_vote_display()`, `format_time_ago()`, `format_date()`, `get_date_range_label()` |
| `Shuriken_Analytics_Ranking` | ~184 | `get_top_rated()`, `get_most_voted()`, `get_low_performers()` — consolidated into single parametric `get_ranked()` with `get_inversion_sql()` static helper |

- [x] **Extract `Shuriken_Analytics_Formatter`** — 5 display methods moved to stateless class. Analytics delegates via composed `$this->formatter`.
- [x] **Extract `Shuriken_Analytics_Ranking`** — 3 ranking methods consolidated into single parametric `get_ranked()` engine (cached + date-filtered paths). Effect-type inversion SQL extracted to `get_inversion_sql()` static helper. Analytics delegates via composed `$this->ranking`.
- [x] **Merge scoped method duplicates** — 7 pairs merged. Base methods gained `?string $scope = null` param: `get_votes_over_time`, `get_rating_stats`, `get_rating_distribution`, `get_approval_trend`, `get_cumulative_approvals`, `get_votes_with_rolling_avg`, `get_rating_votes_paginated`. `_scoped()` methods retained as thin delegates for backward compat. Interface updated. `admin/item-stats.php` callers switched to base+scope.
- [x] **DRY effect-type inversion SQL** — `Shuriken_Analytics_Ranking::get_inversion_sql()` static method extracts the CASE WHEN fragment used by ranking queries.
- [ ] **Decompose `get_parent_rating_stats_breakdown()`** (~250 → ~150 lines) — deferred; internal refactor, no interface impact.

##### 6b — Admin Template DRY (~1,500 lines of duplication across 4+ files)

Admin templates (`item-stats.php`, `analytics.php`, `context-stats.php`, `voter-activity.php`, `ratings.php`) contain heavily duplicated HTML/PHP/JS patterns.

- [ ] **Extract partial: `partials/date-filter-bar.php`** — identical date range filter form (preset select + custom range inputs + hidden fields) appears in 4 files (~200 lines total). Accept `$page`, `$range_type`, `$preset_value`, `$start_date`, `$end_date`, `$hidden_fields` as template vars.
- [ ] **Extract partial: `partials/stats-grid.php`** — `.shuriken-stats-grid` with `.shuriken-stat-card` children repeated 15+ times across 4 files (~300 lines total). Accept `$cards` array of `['icon', 'value', 'label']`.
- [ ] **Extract partial: `partials/votes-table.php`** — identical vote history table structure (thead + voter rendering + pagination) appears in 3 files (~300 lines). Extract voter display logic into `shuriken_render_voter_cell()` helper.
- [ ] **Extract partial: `partials/pagination.php`** — identical `paginate_links()` block with `displaying-num` + `pagination-links` repeated 6+ times (~100 lines).
- [ ] **Extract chart init to `assets/js/admin-charts.js`** — inline `<script>` blocks with Chart.js setup (~450 lines across 4 files) are nearly identical: distribution bar chart, dual-axis vote activity chart, approval ring chart, approval trend line chart, cumulative chart. Extract factory functions: `initDistributionChart()`, `initVoteActivityChart()`, `initApprovalChart()`, etc. Pages pass data via `wp_localize_script()` instead of inline JSON.
- [ ] **Extract helper: `shuriken_format_stat_display()`** — rating-type-conditional display logic (`if like_dislike → %, if approval → count, else → denormalized avg`) repeated 20+ times. Single helper method in `class-shuriken-admin.php`.

##### 6c — Block JS Decomposition (grouped-rating: 1,791 → ~600 lines)

`blocks/shuriken-grouped-rating/index.js` is the largest JS file — a single `edit()` function with 40+ `useState` hooks, 30+ handlers, and 3 inline modals.

- [ ] **Consolidate state into structured objects** — replace 40+ individual `useState` hooks with ~5 state objects: `modals`, `createForm`, `editForm`, `loadingState`, `selectedItems`. Reduces prop drilling and makes state flow tractable.
- [ ] **Extract modal components** — `<CreateParentModal>`, `<EditParentModal>`, `<ManageChildrenModal>` as separate components. Each modal is 100–200 lines of inline JSX.
- [ ] **Extract `<CreateRatingForm>` shared component** — rating type selection, scale validation, description field, display-only toggle repeated across `shuriken-rating/index.js` and `shuriken-grouped-rating/index.js` (~250 lines duplicated). Move to `block-helpers.js`.
- [ ] **Extract `useApiErrorHandling()` hook** — identical error handler setup (`makeErrorHandler`, `makeErrorDismissers`, `retryLastAction`) duplicated in both block `edit()` functions (~60 lines). Centralize in `block-helpers.js`.

##### 6d — Frontend JS & CSS Cleanup

- [ ] **Define constants for selectors and timeouts** — `shuriken-reviews.js` has `.shuriken-rating` hardcoded 20+ times, `.rating-stats` 15+ times, and `4000` ms timeout repeated 4 times. Extract `SELECTORS` and `TIMEOUTS` objects at module scope.
- [ ] **Fix `setInterval` memory leak** — `shuriken-reviews.js` sets up polling intervals cleaned only by `MutationObserver` on DOM removal, which doesn't fire on client-side page navigation. Add `wp-js-interactivity:navigated` cleanup handler.
- [ ] **Remove `getTypeClass()` duplication** — identical function in `admin-ratings.js` and `block-helpers.js`. Admin file should reference the shared version.
- [ ] **Remove unused `useRef` import** — `block-helpers.js` imports `wp.element.useRef` but never uses it.
- [ ] **Audit unused CSS classes** — `.rating-text` and `.display-only-notice` defined in `shuriken-reviews.css` but not referenced in any template or JS. Remove or verify usage from dynamic output.

#### Step 7 — Platform & Add-on Extensibility

A gap audit was done using the "engagement factor (views vs votes)" feature as a test case to measure how close the plugin is to a WooCommerce-style platform where completely decoupled add-ons can be shipped. The findings below are the concrete openings that need to be closed. Each item describes: what is missing, why it matters, and what minimal change fixes it.

> **Why seventh:** Adding hook slots to focused ~300-line controllers (from Step 5) is surgical. Step 6 DRY work reduces the surface area these hooks touch, so slots added here stay stable.

**Admin UI**

- [ ] **No hook slots in admin page templates** — `admin/ratings.php`, `admin/analytics.php`, and the settings partials contain zero `do_action()` calls. An add-on cannot inject a UI panel, column, or stat card anywhere without monkey-patching or adding its own submenu. Fix: add `do_action('shuriken_after_ratings_list', $ratings)`, `do_action('shuriken_after_analytics_overview', $date_range)`, and `do_action('shuriken_after_settings_card', $current_tab)` at logical break points in each template.
- [ ] **`register_menu()` is not filterable** — add-ons cannot add a submenu under the Shuriken top-level menu without hardcoding the slug `'shuriken-reviews'` as the parent. Fix: fire `do_action('shuriken_admin_submenu')` inside `register_menu()` after the last built-in `add_submenu_page()` call.
- [ ] **Settings form save is not hookable** — `settings-general.php` and `settings-rate-limiting.php` process their own `$_POST` directly with no `do_action('shuriken_save_settings', $tab)` around or after the save. Add-on settings saved on the same page (via `shuriken_settings_tabs`) cannot piggyback on the existing save flow.
- [ ] **Settings sidebar is hard-coded per tab** — the sidebar tips block in `settings.php` is a plain `if/elseif` over built-in tab slugs. A tab registered via `shuriken_settings_tabs` gets no sidebar. Fix: add `do_action('shuriken_settings_sidebar_' . $current_tab)` so add-ons can render their own tips.
- [ ] **Ratings list columns are hard-coded** — `get_ratings_columns()` returns a static array. Add-ons cannot inject a column (e.g. "Views") into the ratings table. Fix: wrap the return with `apply_filters('shuriken_ratings_columns', $columns)`.

**REST API**

- [ ] **`/ratings/stats` response has no filter** — the stats array is built and returned directly with no `apply_filters()`. This is the single most critical headless gap: any add-on that stores extra per-rating data (views, engagement score, flags) cannot surface it through the public stats endpoint that headless frontends rely on. Fix: `$stats = apply_filters('shuriken_rating_stats_response', $stats, $ids, $context_id, $context_type)` before `rest_ensure_response($stats)`.
- [ ] **No hook for registering add-on REST routes under the plugin namespace** — add-ons that want to live under `shuriken-reviews/v1` must call `register_rest_route()` themselves on `rest_api_init` with no guarantee of ordering or nonce/auth parity. Fix: fire `do_action('shuriken_rest_register_routes', self::NAMESPACE)` at the end of `register_routes()`.
- [ ] **`get_rating_stats()` permission callback is not filterable** — the stats endpoint is always public. If an add-on needs to attach auth-gated extra fields to the stats response, there is no way to conditionally expose them. Fix: `apply_filters('shuriken_stats_permission_callback', '__return_true', $request)`.

**Analytics & Voter Analytics Services**

- [ ] **`Shuriken_Analytics_Interface` is closed** — the interface defines a fixed method set. An add-on implementing a decorator (e.g. `Engagement_Analytics` wrapping `Shuriken_Analytics`) must either implement every interface method or not implement the interface at all, losing type safety. Fix: introduce a minimal `Shuriken_Analytics_Extension_Interface` (just `get_extra_stats()`), or document the decorator pattern with `__call()` forwarding as the blessed extension approach.
- [ ] **No filter on analytics method output** — methods like `get_overall_stats()`, `get_top_rated()`, `get_most_voted()` return raw objects/arrays with no `apply_filters()` wrapper. An add-on cannot attach extra fields (e.g. view counts) to items already fetched. Fix: wrap return values in named filters, e.g. `apply_filters('shuriken_overall_stats', $stats)`.

**Frontend JS / Blocks**

- [ ] **No JS plugin API** — `shuriken-reviews.js` has no `wp.hooks` integration. Add-ons cannot filter vote request payloads, intercept responses, or augment the rendered widget via JS filters. Fix: expose `wp.hooks.applyFilters('shurikenVoteRequest', data)` before the AJAX call and `wp.hooks.doAction('shurikenVoteSuccess', response)` after.
- [ ] **`shurikenReviews` localized object is not extensible at the block level** — `shuriken_localized_data` filter exists (good), but blocks enqueue their own `block.json`-sourced assets and receive no equivalent filter for their `viewScript` data. Fix: pass block-level config through a `wp_localize_script` call on the view script handle and wrap it with `apply_filters('shuriken_block_view_data', $data, $block)`.
- [ ] **No block filter hooks** (`wp.hooks.addFilter('blocks.registerBlockType', ...)`) — the block `index.js` files register block types with no third-party override points for attributes or the edit/save components.

**Lifecycle & AJAX**

- [ ] **AJAX action is a single hard-coded handler** — `wp_ajax_submit_rating` is the only action. Add-ons cannot register their own AJAX handlers on the same nonce lifecycle or share the rate-limiter check without duplicating the entire `handle_submit_rating()` flow. No `do_action('shuriken_ajax_register_handlers')` exists for add-ons to co-register.
- [ ] **No deactivation/uninstall hooks** — there is no `register_deactivation_hook()` and no `uninstall.php`. An add-on that registers its own options or tables during activation has no standard signal from the parent plugin to clean up when Shuriken is deactivated or deleted.

**Dependency Injection Container**

- [ ] **Container is not externally observable** — `shuriken_container()` is public (good) and `set()` / `bind()` / `singleton()` are all accessible. But there is no event fired after the container is fully built. An add-on that loads after `plugins_loaded` priority 10 must call `shuriken_container()->set(...)` defensively without knowing whether the container has already resolved the service it wants to replace. Fix: fire `do_action('shuriken_container_ready', $container)` at the end of `init_modules()` in the main plugin class.

#### Step 8 — Performance

- [ ] Server-side render pre-fetch — batch query on frontend page load to avoid per-block queries
- [ ] Statistics caching — TTL-based cache service in container; invalidate on vote change; optional Redis support
- [ ] Rate limit performance caching — cache vote counts in transients per user/IP with TTL; invalidate on new vote

> **Why eighth:** Stable service boundaries from Steps 4–5 and clean code from Step 6 make a cache service cleanly injectable without coupling it to bloated classes.

### Known bugs and Gaps

- [ ] FSE blocks Preview only shows the state of block where no Rating is selected
- [ ] **Contextual ratings for WordPress comments** — add first-class support for comment-level context (e.g. `context_type=comment`, `context_id=<comment_id>`) across validation defaults, editor/shortcode UX, and analytics surfaces
- [x] **Rating label description** — optional description text displayed beneath a rating's title; stored as a `label_description` field on the rating; exposed in block editor, shortcodes, and REST API
- [x] **Hide title & description** — `hideTitle` block attribute and `hide_title` shortcode attribute suppress the rating title and description; particularly useful in Query Loop layouts where each item shouldn't repeat the rating name
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
- [ ] Native multilingual support — WPML/Polylang compatibility for rating titles/descriptions.

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

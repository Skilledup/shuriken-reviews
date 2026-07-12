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
- Shortcodes support `context_id` and `context_type` attributes for contextual voting outside the block editor (v1.14.5)
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

### Modern PHP & Architecture (v1.15.5–1.15.6-rc)

Steps 1–5 shipped in v1.15.5. Step 6 (6a–6f) and Step 7 completed in v1.15.6-rc **Shingetsu** (foundational Step 6 work — formatter, ranking, context, JS modernisation, block decomposition — began in v1.15.5).

#### Step 1 — `RatingType` Backed Enum ✅

`Shuriken_Rating_Type` backed enum shipped in `includes/enum-shuriken-rating-type.php`. Cases: `Stars`, `LikeDislike`, `Numeric`, `Approval`. Methods: `isBinary()`, `maxScale()`, `constrainScale()`, `typeClass()`, `values()`. Adopted across 13 files — `get_type_class()` deleted from REST API, all `$allowed_types` arrays and binary guards replaced.

#### Step 2 — First-Class Callables for Hooks ✅

Replaced all `array($this, 'method_name')` callback syntax with `$this->method(...)` first-class callables across 6 classes: `Shuriken_Admin` (19), `Shuriken_REST_API` (30), `Shuriken_Block` (5), `Shuriken_AJAX` (2), `Shuriken_Shortcodes` (2), `Shuriken_Frontend` (2). `Shuriken_Rate_Limiter` had none. Zero logic changes — pure syntax sweep.

#### Step 3 — `readonly` Properties + Constructor Property Promotion ✅

Applied CPP + `readonly` to 5 classes: `Shuriken_Admin` (2 promoted props), `Shuriken_Block`, `Shuriken_AJAX`, `Shuriken_Shortcodes` (1 each), `Shuriken_Analytics` (1 promoted + 3 readonly derived). `Shuriken_Frontend` has no injected deps — skipped. Constructor params made non-nullable (singletons always pass resolved instances). Also fixed all `@since 1.15.0` → `1.15.5` (33 occurrences).

#### Step 4 — `Shuriken_Database` Repository Decomposition ✅

Decomposed the ~1,694-line monolithic `Shuriken_Database` class into three focused repository classes + a slim delegation façade. All classes use CPP + `readonly` constructors. `Shuriken_Database_Interface` kept intact — façade implements it for full backward compatibility. Zero public API changes; callers use `shuriken_db()` as before.


| New class                    | Lines  | Responsibility                                                                    |
| ---------------------------- | ------ | --------------------------------------------------------------------------------- |
| `Shuriken_Rating_Repository` | ~1,041 | Rating CRUD, search, pagination, hierarchy, mirrors, contextual stats, export     |
| `Shuriken_Vote_Repository`   | ~326   | Vote CRUD, rate-limit timestamp queries, transactional vote+total updates         |
| `Shuriken_Schema_Manager`    | ~204   | `create_tables()`, `tables_exist()`, column migrations                            |
| `Shuriken_Database` (façade) | ~401   | Singleton, constants, static helpers, delegates all 28 interface methods to repos |


All 10 callers now type-hint the specific repository they need. Per-repo helper functions added: `shuriken_ratings_repo()`, `shuriken_votes_repo()`, `shuriken_schema_manager()`. Container bindings updated. `Shuriken_Database_Interface` and the façade kept for backward compatibility (`shuriken_db()` still works).

#### Step 5 — `Shuriken_REST_API` Controller Split ✅

Split the ~1,046-line monolithic `Shuriken_REST_API` class into two focused controllers + a thin bootstrap. Both controllers use CPP + `readonly` constructors and own their route registration, arg schemas, and permission callbacks. Cross-cutting filters (auth bypass, output buffer cleaning, CDN cache headers) remain on the bootstrap. Zero public API or hook changes.


| New class                          | Lines | Responsibility                                                                           |
| ---------------------------------- | ----- | ---------------------------------------------------------------------------------------- |
| `Shuriken_REST_Ratings_Controller` | ~689  | 11 rating endpoints: CRUD, hierarchy, mirrors, search, batch + arg schemas + permissions |
| `Shuriken_REST_Votes_Controller`   | ~268  | 3 endpoints: stats (public), context-stats (editor), nonce (public)                      |
| `Shuriken_REST_API` (bootstrap)    | ~210  | Singleton, controller wiring, `register_routes()` delegation, REST filters               |


#### Step 6 — Coding Standards & DRY Sweep ✅

##### 6a — `Shuriken_Analytics` Decomposition ✅ (completed v1.15.6-rc)

Formatter, Ranking, and Context services shipped in v1.15.5; Dashboard and Rating_Stats extraction plus the final ~278-line coordinator shipped in v1.15.6-rc.


| Class                              | Lines | Responsibility                                                                                                                                              |
| ---------------------------------- | ----- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Shuriken_Analytics` (coordinator) | ~278  | Thin delegate façade; filter hooks for extensibility                                                                                                        |
| `Shuriken_Analytics_Formatter`     | ~153  | `format_average_display()`, `format_vote_display()`, `format_time_ago()`, `format_date()`, `get_date_range_label()`                                         |
| `Shuriken_Analytics_Ranking`       | ~184  | `get_top_rated()`, `get_most_voted()`, `get_low_performers()` — consolidated into single parametric `get_ranked()` with `get_inversion_sql()` static helper |
| `Shuriken_Analytics_Dashboard`     | ~528  | Site-wide overview: `get_overall_stats()`, heatmap, momentum, participation, type benchmarks                                                                |
| `Shuriken_Analytics_Rating_Stats`  | ~919  | Per-rating SQL: stats, breakdown, distribution, paginated votes, chart data, approval trends                                                                |
| `Shuriken_Analytics_Context`       | ~587  | 12 per-post/contextual methods                                                                                                                              |


7 pairs of scoped/base method duplicates merged; 12 contextual methods (660 lines) moved to `Shuriken_Analytics_Context`. `is_binary_type()` and `build_empty_distribution()` promoted to `Shuriken_Analytics_Helpers` trait.

- **Decomposed `get_parent_rating_stats_breakdown()`** ✅ — decomposed into four focused private methods on `Shuriken_Analytics_Rating_Stats` (`get_direct_votes_breakdown()`, `calculate_sub_ratings_rating_totals()`, `get_sub_ratings_breakdown()`, `combine_votes_breakdown()`), reducing the main method's footprint to under 65 lines.
- **Split jumbo `Shuriken_Analytics_Interface`** ✅ — split into five focused sub-interfaces (`Shuriken_Analytics_Formatter_Interface`, `Shuriken_Analytics_Ranking_Interface`, `Shuriken_Analytics_Dashboard_Interface`, `Shuriken_Analytics_Rating_Stats_Interface`, `Shuriken_Analytics_Context_Interface`); the core `Shuriken_Analytics_Interface` now `extends` all five, preserving the full contract for backward compatibility.

##### 6b — Admin Template DRY ✅ (v1.15.5–1.15.6)

- Extracted `partials/pagination.php`, `partials/date-filter-bar.php`, `partials/votes-table.php`
- Extracted helpers: `shuriken_format_rating_value()`, `shuriken_render_voter_cell()`
- **Extracted chart init to `assets/js/admin-charts.js`** ✅ — the near-identical Chart.js setup blocks (~450 lines across item-stats, context-stats, and voter-activity) are now factory functions (`initApprovalRing`, `initApprovalTrend`, `initCumulative`, `initDistribution`, `initDualAxis`, `initTopContexts`, `initContextAvgDist`, `initContextActivity`, `initVoterDistribution`, `initVoterActivity`). The item-stats global view and context-stats share a single `initTypeAwareCharts(data, ids)` dispatcher. Pages now emit only a small inline data object (the same pattern as the analytics dashboard) and the new script auto-initializes from it.

##### 6e — JS Modernization ✅

All 10 project JS files modernized: 135 `var` → `const`/`let`, arrow functions, template literals, `e.key` over `e.which`. Optional chaining applied across frontend (`shuriken-reviews.js`), admin (`admin-charts.js`, `admin-analytics.js`), and block editor (`shuriken-rating/index.js`, `shuriken-grouped-rating/index.js`).

##### 6c — Block JS Decomposition ✅

`blocks/shuriken-grouped-rating/index.js` reduced from 1,797 to ~904 lines. Modals and Inspector panels extracted to `blocks/shuriken-grouped-rating/components/`. Form state consolidated with `useReducer`; shared `renderRatingTypeScaleFields()` and `useApiErrorHandling()` in `block-helpers.js`.

##### 6d — Block Build Toolchain: `@wordpress/scripts` + JSX ✅

`package.json`, `webpack.config.js` (6 entries → `build/`), ES module imports with `wp.*` externalisation, compiled script registration in `class-shuriken-block.php`.

##### 6f — Frontend JS & CSS Cleanup ✅

`SELECTORS`/`TIMEOUTS` constants, `setInterval` memory-leak fix via `wp-js-interactivity:navigated`, optional chaining in frontend JS, CSS class audit (no removals needed).

#### Step 7 — Platform & Add-on Extensibility ✅ (v1.15.6-rc)

All hook, filter, and action slots shipped in v1.15.6-rc for fully decoupled third-party add-ons.

**Admin UI:** `shuriken_admin_submenu`, `shuriken_ratings_columns` (filter), `shuriken_after_ratings_list`, `shuriken_after_analytics_overview`, `shuriken_after_settings_card`, `shuriken_settings_sidebar_{tab}`, `shuriken_save_settings`

**REST API:** `shuriken_rest_register_routes`, `shuriken_rating_stats_response` (filter), `shuriken_stats_permission_callback` (filter — replaces hard-coded `__return_true`)

**Analytics:** `shuriken_overall_stats`, `shuriken_top_rated`, `shuriken_most_voted`, `shuriken_low_performers` output filters; `Shuriken_Analytics_Extension_Interface` for third-party stats decorators.

**Frontend / Blocks:** `shurikenVoteRequest` filter + `shurikenVoteSuccess` action (via `wp.hooks`); `shurikenBlockSettings_rating` + `shurikenBlockSettings_groupedRating` registration filters; `shuriken_block_view_data` filter + consolidated `shurikenBlockViewData` localize + `shurikenBlockViewData` JS filter

**Lifecycle / AJAX / DI:** `shuriken_deactivate`, `shuriken_container_ready`, `shuriken_uninstall`, `shuriken_ajax_register_handlers`; opt-in "Delete Data on Uninstall" toggle (Settings → General → Data Management).

### Content & Display (v1.15.5)

- **Rating label description** — optional `label_description` field on ratings; exposed in block editor, shortcodes, and REST API
- **Hide title & description** — `hideTitle` block attribute and `hide_title` shortcode attribute suppress the rating name and description (useful in Query Loop layouts)

### Bug Fixes & Admin Polish (v1.15.5–1.15.6)

- **Date filter on contextual item-stats page** — time-period `<select>` handler centralised in `admin-analytics.js` (contextual view was missing bindings)
- **Best Performing avg wrong for binary types** — `get_rating_context_summary()` now computes percentage for like/dislike instead of denormalising the 0–1 ratio
- **Admin analytics JS DRY cleanup** — shared date-range filters, `formatDate()`, clickable-row handlers, and chart colours consolidated into `admin-analytics.js`; `shuriken_sort_link()` moved to `class-shuriken-admin.php`

---

## Up Next

### Step 8 — Performance (v1.15.x)

Batch infrastructure already exists for REST (`get_contextual_stats_batch`, grouped `/ratings/stats` JS) but SSR still runs per-block queries, the client always re-fetches stats on load, and rate limiting hits the DB on every vote. Step 8 closes those gaps in priority order — quick wins first, then prefetch, then caching.

> **Why eighth:** Stable service boundaries from Steps 4–5 and clean code from Step 6 make a cache service cleanly injectable without coupling it to bloated classes.

> **Cache compatibility:** Server-side caching (transients / object cache) is separate from edge/CDN caching. Cached pages still need a fresh nonce and stats refresh; uncached pages can trust SSR stats and skip the REST stats round-trip. REST responses keep `Cache-Control: no-store` for CDN safety.

#### 8a — Quick Wins (low risk, high ROI) ✅

- [x] **Conditional asset enqueue** — `viewScript` in block.json + `shuriken_enqueue_frontend_assets()` from block/shortcode render callbacks (WP 7.0+)
- [x] **Request-scoped rating memo** — dedupe `get_rating()` within a single request; clone-on-return prevents contextual stat leakage
- [x] **Grouped block batch fetch** — `render_grouped_block()` uses one `get_ratings_by_ids()` call
- [x] **Block view data wiring** — consolidated `shurikenBlockViewData` localize + `shurikenBlockViewData` JS filter for add-ons
- [x] **Revisit star `setInterval`** — removed; vote success explicitly syncs stars

#### 8b — SSR Batch Pre-fetch ✅

- [x] **Contextual stats collector** — `Shuriken_Contextual_Stats_Collector` gathers `(source_id, context_id, context_type, scale)` tuples during block/shortcode render, then calls `get_contextual_stats_batch()` once per context group instead of `get_contextual_stats()` per widget in `render_rating_html()`
- [x] **Serve from in-request map** — `render_rating_html()` reads pre-fetched stats from the collector; falls back to single query if collector not active (e.g. direct shortcode call outside normal render flow)
- [x] **Early content registration** — `the_content` priority 1 scans parsed blocks and shortcodes; `pre_render_block` supplements Query Loop dynamic context and mirror/sub-rating IDs

> `get_contextual_stats_batch()` already exists and is used by REST — this step wires it into the PHP render path.

#### 8c — Smart Client Fetch ✅

- [x] **Always fetch nonce** — `GET /nonce` on every page load (required for full-page CDN cache compatibility)
- [x] **Skip stats REST on uncached pages** — when `ssr_rendered_at` is within threshold, trust embedded `data-average` / `data-scaled-average` and skip `GET /ratings/stats`
- [x] **Always refresh after vote** — post-vote AJAX response already returns updated stats; no change needed
- [x] **Detect cached-page context** — stale `ssr_rendered_at` timestamp + bfcache (`pageshow` + `persisted`) trigger batched stats refresh

#### 8d — Statistics Cache Service ✅

- [x] **`Shuriken_Cache` service** — TTL-based cache in the DI container; `get` / `set` / `delete` with configurable TTL (default ~60s for stats)
- [x] **Object cache compatible** — backed by `wp_cache_*` (automatically uses Redis/Memcached when an object cache drop-in is present; no bespoke Redis wiring)
- [x] **Invalidate on mutations** — hook vote create/update and rating update/delete paths to bust rating, parent, and per-context keys; mirrors remain request-scoped to avoid lookup queries during vote writes
- [x] **Integrate at REST + AJAX + archive sort** — cache `get_contextual_stats_batch()` results and denormalized global stats reads; archive sorting uses WordPress's native `WP_Query` result cache with targeted vote-generation invalidation; `shuriken_rating_stats_response` remains the add-on extension point

> Resolves backlog research item: *Best caching strategy for rating stats*.

#### 8e — Rate Limit Performance

- [ ] **Transient vote counters** — cache hourly/daily counts per user/IP with TTL = window remainder; increment on vote write; invalidate on vote update
- [ ] **DB indexes** — add `(user_id, date_modified)` and `(user_ip, user_id, date_modified)` indexes on `wp_shuriken_votes` for COUNT queries
- [ ] **Cooldown cache** — cache `get_last_vote_time()` per `(rating_id, user_id|ip, context)` with TTL = cooldown duration

> Lower priority — rate limiting is off by default. Indexes alone may be sufficient before adding transient counters.

### Known bugs and Gaps

- [ ] FSE blocks Preview only shows the state of block where no Rating is selected
- [ ] **Contextual ratings for WordPress comments** — add first-class support for comment-level context (e.g. `context_type=comment`, `context_id=<comment_id>`) across validation defaults, editor/shortcode UX, and analytics surfaces
- [ ] **Star rating with multiple icons** — the current star rating type only supports a single icon for all stars. We want to support multiple icons (e.g. 1 star = 😡, 2 stars = 🙁, 3 stars = 😐, 4 stars = 🙂, 5 stars = 😍) with a mapping of icon per rating value. This is a separate system from Emoji reactions system.

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
- [x] Best caching strategy for rating stats — resolved in Step 8d (server-side TTL cache via `wp_cache_*`; CDN pages keep client refresh, uncached pages trust SSR)
- [ ] **Lazy nonce fetch on first interaction** — fetch `GET /nonce` when the user first engages a votable widget (click/focus) instead of on every page load; keep silent retry-on-failure as fallback. Saves one REST request per page for read-only visitors; trade-off: `logged_in` / `allow_guest_voting` stay stale until interaction unless always-fetch is retained for auth UI
- [ ] Webhook retry guarantees and failure modes
- [ ] Email template customization approach

---

## Breaking Changes

None planned. Backward compatibility is a goal for upcoming versions.

---

## Support

- GitHub Issues: [https://github.com/Skilledup/shuriken-reviews/issues](https://github.com/Skilledup/shuriken-reviews/issues)
- Documentation index: [INDEX.md](INDEX.md)

---

## License

Licensed under [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html)

Developed by [Skilledup](https://skilledup.ir)
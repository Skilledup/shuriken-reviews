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

### 1.15.x — Modern PHP & Architecture

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

> **Adoption gap (deferred to PHPUnit milestone):** All 6 callers (`Shuriken_Admin`, `Shuriken_AJAX`, `Shuriken_Block`, `Shuriken_Shortcodes`, `Shuriken_Analytics`, both REST controllers) still type-hint `Shuriken_Database_Interface` — the full 28-method contract — even though each class only uses a small slice of it. Full adoption means narrowing each caller to the specific repository it actually needs, adding per-repo helper functions (`shuriken_ratings_repo()`, `shuriken_vote_repo()`, `shuriken_schema_manager()`), and deleting `Shuriken_Database_Interface` and the façade entirely. Do this during the PHPUnit suite — mock narrowing is the direct payoff.

#### ~~Step 5 — `Shuriken_REST_API` Controller Split~~ ✅

Split the ~1,046-line monolithic `Shuriken_REST_API` class into two focused controllers + a thin bootstrap. Both controllers use CPP + `readonly` constructors and own their route registration, arg schemas, and permission callbacks. Cross-cutting filters (auth bypass, output buffer cleaning, CDN cache headers) remain on the bootstrap. Zero public API or hook changes.

| New class | Lines | Responsibility |
|---|---|---|
| `Shuriken_REST_Ratings_Controller` | ~689 | 11 rating endpoints: CRUD, hierarchy, mirrors, search, batch + arg schemas + permissions |
| `Shuriken_REST_Votes_Controller` | ~268 | 3 endpoints: stats (public), context-stats (editor), nonce (public) |
| `Shuriken_REST_API` (bootstrap) | ~210 | Singleton, controller wiring, `register_routes()` delegation, REST filters |

#### Step 6 — Platform & Add-on Extensibility

A gap audit was done using the "engagement factor (views vs votes)" feature as a test case to measure how close the plugin is to a WooCommerce-style platform where completely decoupled add-ons can be shipped. The findings below are the concrete openings that need to be closed. Each item describes: what is missing, why it matters, and what minimal change fixes it.

> **Why sixth:** Adding hook slots to focused ~300-line controllers (from Step 5) is surgical. Doing this work on the pre-split 1,064-line monolith would require re-touching the same lines during the controller split anyway.

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

#### Step 7 — Performance

- [ ] Server-side render pre-fetch — batch query on frontend page load to avoid per-block queries
- [ ] Statistics caching — TTL-based cache service in container; invalidate on vote change; optional Redis support
- [ ] Rate limit performance caching — cache vote counts in transients per user/IP with TTL; invalidate on new vote

> **Why seventh:** Stable service boundaries from Steps 4–5 make a cache service cleanly injectable into the container without coupling it to the monolith.

### Known bugs and Gaps

- [ ] FSE blocks Preview only shows the state of block where no Rating is selected
- [ ] **Contextual ratings for WordPress comments** — add first-class support for comment-level context (e.g. `context_type=comment`, `context_id=<comment_id>`) across validation defaults, editor/shortcode UX, and analytics surfaces
- [x] **Rating label description** — optional description text displayed beneath a rating's title; stored as a `label_description` field on the rating; exposed in block editor, shortcodes, and REST API
- [x] **Hide title & description** — `hideTitle` block attribute and `hide_title` shortcode attribute suppress the rating title and description; particularly useful in Query Loop layouts where each item shouldn't repeat the rating name
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

# Changelog

All notable changes to **Shuriken Reviews** are documented here.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

---

## [1.14.7] — 2026-04-04

### Added
- All rating and stats objects now carry a `display_average` field — the average denormalized to the rating's own display scale — alongside the existing `average` (internal 1–5 normalized value). This applies to every method that returns rating or stats objects: `get_rating()`, `get_ratings_by_ids()`, `get_all_ratings()`, `get_ratings_paginated()`, `get_sub_ratings()`, `get_mirrors()`, `get_child_ratings()`, `search_ratings()`, `get_contextual_stats()`, `get_contextual_stats_batch()`, and `get_ratings_for_context()`.
- Analytics layer attaches display-scale variants to all computed aggregates: `display_daily_avg` on rolling-average rows, `display_delta` / `display_recent_avg` / `display_prev_avg` on momentum items, and `display_average` on all stats/breakdown objects.
- `get_contextual_stats()` and `get_contextual_stats_batch()` accept a `$scale` / `$scales` parameter so callers can request the correct display scale without a second round-trip.
- `get_votes_with_rolling_avg()` and `get_votes_with_rolling_avg_for_ids()` accept a `$scale` parameter for `display_daily_avg`.

### Changed
- **Architectural (SRP fix):** Denormalization of the internal 1–5 average to display scale is now performed exclusively inside the data layer (`Shuriken_Database::attach_averages()`). All consumers (admin views, REST API, AJAX, shortcodes, FSE block helpers) read the pre-computed `display_average` instead of calling `denormalize_average()` inline.
- FSE `calculateScaledAverage()` in `block-helpers.js` now prefers `rating.display_average` from the API response and falls back to client-side math only for legacy data that pre-dates this release.
- Interface signatures for `get_contextual_stats`, `get_contextual_stats_batch`, `get_votes_with_rolling_avg`, and `get_votes_with_rolling_avg_for_ids` updated to reflect new optional parameters.

### Fixed
- Sub-rating distribution SQL in `get_parent_rating_stats_breakdown()` was using `r.scale` (the sub-rating's display scale) as the normalization divisor instead of the internal `RATING_SCALE_DEFAULT`, producing incorrect bucket ranges for non-default scales.
 - Sub-rating distribution SQL in `get_parent_rating_stats_breakdown()` was using `r.scale` (the sub-rating's display scale) as the normalization divisor instead of the internal `RATING_SCALE_DEFAULT`, producing incorrect bucket ranges for non-default scales.

---

## [1.14.6] — 2026-04-03

### Added
- Numeric rating display with slider support — single-rating numeric type now renders a display-only slider, value readout, and compact submit control in the block and frontend; the Ratings admin list now shows a compact numeric progress bar with scaled average and vote counts. Files updated: `blocks/shared/block-helpers.js`, `assets/css/shuriken-reviews.css`, `assets/css/admin-ratings.css`, `admin/ratings.php`.
- Database precision: migrated `rating_value` (votes) and `total_rating` (aggregates) from `INT` to `DECIMAL` to allow fractional values; corresponding schema changes and migration logic were added. Database version bumped to `1.7.0`.

### Changed
- Slider and admin styling refinements (thumbs, buttons, and checkbox column width).

### Fixed
- Migration scripts hardened with `SHOW COLUMNS` guards and safer index updates to handle partial migrations and include context-aware keys.

---

## [1.14.5] — 2026-03-31

### Added
- Comments system settings page with conditional hook registration for comment filtering.
- Participation tracking with user feedback messages and loading state indicators.
- Analytics: rolling average calculations now support multiple rating IDs simultaneously.
- **About tab** in Settings (`admin/partials/settings-about.php`) — consolidates What's New, Quick Start, Shortcode Reference, Developer Resources, and System Info into the existing settings UI; About tab styles added to `admin-settings.css`.
- Shortcodes now support optional `context_id` and `context_type` attributes so contextual voting can be used outside the block editor.

### Removed
- Standalone About admin page (`admin/about.php`) merged into Settings → About tab.
- `assets/css/admin-about.css` — styles migrated into `admin-settings.css`.

### Fixed
- SQL queries updated to use `COUNT(DISTINCT context_id)` for accurate post-count metrics.

---

## [1.14.4] — 2026-03-31

### Added
- **Block editor sidebar panel** — `PluginDocumentSettingPanel` fetches per-post contextual vote stats while editing any post that has received contextual votes.
- **Archive sorting** — `pre_get_posts` hook orders archive pages by contextual rating score; configurable via Settings → General (rating selector, sort by average or total votes).
- **Ratings management indicators** — Type column shows a pin badge with the distinct-post count for ratings that have contextual votes.
- **Analytics context column** — Recent Activity table shows the linked post title for contextual votes, or "Global" for non-contextual ones.
- **Contextual Posts card** — Analytics overview grid gains a "Posts with Per-Post Votes" stat card when contextual votes exist.
- `get_context_usage_counts()` / `get_ratings_for_context()` methods on the Database service and interface.
- `GET /shuriken-reviews/v1/context-stats` REST endpoint (requires `edit_posts` capability).
- Analytics enhancements: vote-change percentage, benchmark stats, and voter-type (member vs. guest) breakdown.

---

## [1.14.0] — 2026-03-31

### Added
- **Contextual Voting (Per-Post Ratings)** — a single rating or grouped rating placed in a post template now records independent vote tallies per post without requiring duplicate rating configurations.
- `context_id` (BIGINT) and `context_type` (VARCHAR 50) columns on `wp_shuriken_votes`; unique key updated via `COALESCE` for `NULL` safety. DB version bumped to **1.6.0**.
- `get_contextual_stats()` / `get_contextual_stats_batch()` methods on the Database service.
- `GET /ratings/stats` REST endpoint accepts optional `context_id` and `context_type`.
- Both single- and grouped-rating blocks expose a **"Per-post voting"** toggle (`postContext` attribute).
- Blocks declare `usesContext: ["postId", "postType"]`; PHP render passes FSE context through the full stack.
- Frontend JS batches stats requests per context group to reduce HTTP round-trips.
- `shuriken_allowed_context_types` filter controls accepted post types (default: `post`, `page`, `product`).

### Changed
- AJAX and shortcode submission handlers updated to forward context parameters.

### Removed
- **Post Linked Ratings block** — superseded by the `postContext` block attribute.
- Content-injection (post meta) defaults disabled — superseded by per-post contextual blocks.

---

## [1.13.0] — 2026-03-30

### Added
- New rating **types** (Star, Numeric, Thumbs) and configurable **scales** with editor previews.
- Type-aware rating display filters and shortcode handling.
- **Type compatibility checks** — the UI warns when incompatible types are linked (mirrors, parent-child).
- Linked-ratings style and color settings in the block editor.
- `Shuriken_Exception_Interface` and `Shuriken_Exception_Trait` for unified exception handling.
- Custom blocked-message support on `Shuriken_Rate_Limit_Exception`.
- `Shuriken_Voter_Analytics` service (DI-injected) extracted from `Shuriken_Analytics` for voter-specific data.

### Changed
- Exception classes refactored to enforce strict type safety.
- `should_bypass()` method signature enforced throughout rate-limiter hierarchy.
- Rating effect options given descriptive labels and help text in the editor.

### Fixed
- Ratings management form CSS fixes.
- Various UI alignment and backend fixes.

### Internal
- Hardcoded rating scales and limits replaced with named constants.
- Removed BOM characters from PHP source files.
- `var` replaced with `const`/`let` throughout `ratings-store.js`; input validation added.

---

## [1.12.x] — 2026-03-28 *(untagged)*

### Added
- Post meta filters for content injection and JSON-LD structured data (see [Hooks Reference](guides/hooks-reference.md)).
- Mirror management in the Grouped Rating block with cache invalidation.
- Mirrors support in the REST API (`GET /ratings/{id}/mirrors`).

---

## [1.11.4] — 2026-03-27

### Added
- REST API request **deduplication** — the client-side store de-dupes in-flight requests to prevent redundant calls on cached pages.
- CDN compatibility — REST API nonce is fetchable from a dedicated endpoint, enabling cached-page delivery without stale nonces.
- `[shuriken_grouped_rating]` shortcode with `style`, `accent_color`, `layout` parameters.
- Enhanced shortcode reference section on the About page.

### Changed
- `[shuriken_rating]` shortcode extended with `style`, `accent_color`, and `star_color` attributes.

---

## [1.11.1] — 2026-03-25

### Added
- **Batch-fetching endpoint** — `POST /ratings/stats/batch` retrieves stats for multiple rating IDs in a single HTTP request.
- Shared block helpers module (`blocks/shared/block-helpers.js`) used by both FSE blocks.
- Mirror management UI in the Grouped Rating block (add/remove mirrors with automatic cache invalidation).

### Changed
- Documentation updated to cover batch API, mirror management, and shared block helpers.

---

## [1.10.3] — 2026-02-23

### Added
- FSE block style **presets** for the Grouped Rating block (`gradient`, `minimal`, `boxed`, `dark`, `outlined`).
- Single Rating FSE block gains style and color settings (presets: `classic`, `card`, `minimal`, `dark`, `outlined`).
- Child-layout support and simplified CSS variables for the rating block.

### Changed
- Block editor UI redesigned for enhanced settings discoverability.
- Branding: "Skilledup Hub" renamed to "Skilledup".

---

## [1.10.1] — 2026-02-22

### Added
- GitHub Actions workflow for generating and uploading plugin release assets.
- Revised admin toolbar layout with unified bulk-actions and search for the Ratings management page.

### Changed
- License upgraded from **GPL v2** to **GPL v3**.
- GitHub repository links updated to reflect new ownership.
- Minimum requirements raised to **PHP 8.1** and **WordPress 6.2**.

### Fixed
- RTL notification margin and `border-radius` corrections.

---

## [1.9.1] — 2026-01-29

### Added
- **Voter Activity page** — admin page listing individual voter records with IP, user, rating, and timestamp.
- Source column on Item Stats page distinguishing direct, sub-rating, and total vote contributions.

### Changed
- Full dependency injection applied across all services (Database, Analytics, Rate Limiter, REST API, AJAX).

---

## [1.9.0] — 2026-01-29

### Added
- Loading-state feedback (opacity transition + spinner) on the analytics data-refresh action.
- Nonce bypass for public REST endpoints to support cached-page delivery.

### Changed
- Major performance optimizations across database queries and frontend asset loading.

### Fixed
- Date-range filter now applies correctly to Top Rated, Most Voted, and Low Performers queries on the Analytics page.
- Vote-history bug on the Item Stats page.

---

## [1.7.5] — 2025-12-27

### Added
- **Rate Limiting** — configurable cooldown, hourly limit, and daily limit for both members and guests. Settings page with JavaScript toggles.
- Settings admin page (`admin/settings.php`) with General and Rate Limiting tabs.

### Changed
- `Shuriken_Rate_Limiter` class introduced and integrated via DI container.

---

## [1.7.2] — 2025-12-26

### Added
- **Developer Resources section** on the About page: Hooks & Filters, Interfaces & Testing, Dependency Injection, Exception System, REST API, and Helper Functions cards.
- Popular Hooks quick-reference table on the About page.
- New AJAX filters: `shuriken_allow_guest_vote` and `shuriken_before_rating_submit`.

---

## [1.7.0] — 2025-12-26

### Added
- **Modular architecture** — plugin bootstrapped through a central `Shuriken_Reviews` class; REST API, shortcodes, blocks, AJAX, and frontend assets each live in dedicated classes.
- **Hooks system** — 20+ filters and actions (see [Hooks Reference](guides/hooks-reference.md)).
- **Dependency Injection container** (`Shuriken_Container`) for flexible service management and testability.
- **Exception system** — 6 typed exception classes (`Configuration`, `Database`, `Integration`, `Logic`, `NotFound`, `Permission`, `RateLimit`, `Validation`) with automatic logging.
- Interfaces for `Database`, `Analytics`, and `RateLimiter` services enabling mock-based unit tests.
- Dynamic star scaling: AJAX normalises vote values against the configured max stars.
- Shortcodes and Gutenberg blocks unified via a shared `render()` method.
- REST nonce forwarded from frontend JS for authenticated API calls.

### Changed
- Voter activity, analytics, and admin pages refactored to use injected service instances.

---

## [1.6.0] — 2025-12-16

### Added
- REST API endpoints: `GET /ratings/{id}/stats` (live stats) and `GET /ratings/nonce` (fresh nonce).
- Client-side data fetching refactored to always request fresh stats after page load (cache bypass).

---

## [1.5.8] — 2025-12-07

### Added
- **About page** (`admin/about.php`) with hero banner, features grid, Quick Start Guide, and System Information.

---

## [1.5.x] — 2025-12-07 – 2025-12-16

### Added
- Edit functionality for existing ratings via REST API (`GET /ratings/{id}`, `PUT /ratings/{id}`).
- Parent and mirror rating selection in the create/edit form.
- Delete rating functionality in the Grouped Rating block.
- Front-end stylesheet registered for the Rating block.
- FSE block editor placeholder styles.

### Fixed
- Display-only value normalised in edit modal.

---

## [1.4.x] — 2025-12-05 – 2025-12-06

### Added
- **Sub-ratings** — parent-child rating relationships with configurable effect types (`add`, `average`, `weight`) and display-only flag.
- **Mirror ratings** — a rating can reference another rating's vote data without duplicating entries.
- Hierarchical stats on the Item Stats page (direct, sub-rating, and total breakdowns).
- RTL stylesheet support.
- "Convert from mirror" action in the inline ratings editor.

---

## [1.3.x] — 2025-12-04 – 2025-12-05

### Added
- `Shuriken_Database` class encapsulating all CRUD operations.
- `Shuriken_Analytics` class encapsulating statistics retrieval.
- **Item Stats page** with date-range filtering and Chart.js visualisations.

---

## [1.2.x] — 2025-04-12 – 2025-11-29

### Added
- **Full Site Editor (FSE) block** — `shuriken/shuriken-rating` Gutenberg block for the block editor.
- **Guest voting** — unauthenticated users can cast votes (configurable).
- Comments management: exclude author comments and/or reply comments from the Latest Comments block.
- Search and pagination on the Ratings management page.
- Settings page for comment-exclusion preferences.
- Translation support (`.pot` / `.po` files, Persian `fa_IR` locale).

---

## [1.1.x] — 2025-03-26 – 2025-03-29

### Added
- Anchor tag linking (`anchor_tag` shortcode attribute).
- Full **keyboard navigation** and **screen reader** accessibility for the star widget.
- Login prompt for unauthenticated users attempting to vote.
- Automatic star reset after voting (4-second interval).
- Average display initialisation on page load.
- RTL-aware CSS layout improvements.

---

## [1.0.0] — 2025-03-25

### Added
- Initial plugin release.
- Star rating widget with AJAX vote submission and per-user vote tracking.
- `[shuriken_rating]` shortcode.
- Admin ratings management page (list, create, delete).
- Latest Comments block with author-comment exclusion and Swiper slider.
- Basic analytics dashboard.

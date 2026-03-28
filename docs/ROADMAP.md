# Shuriken Reviews Roadmap

This document is a high-level roadmap (what’s done + what’s next). For deep details, use:
- Hooks/API details: [guides/hooks-reference.md](guides/hooks-reference.md)
- Architecture: [ARCHITECTURE.md](ARCHITECTURE.md)
- Developer guides: [guides/dependency-injection.md](guides/dependency-injection.md), [guides/exception-handling.md](guides/exception-handling.md), [guides/testing.md](guides/testing.md)

---

## Status (Today)

✅ Already shipped:
- Core rating system (ratings, voting, stats, block, shortcode, REST, AJAX)
- Extensibility (hooks/filters/actions)
- Testing infrastructure (interfaces + mock DB)
- Exception system + handler
- Dependency injection container
- Parent/child "grouped ratings" block
- Data retrieval efficiency optimizations (shared store, AJAX search, batch queries)
- Voter Activity page (member & guest tracking, stats, charts, CSV export)
- Vote rate limiting with modern settings UI
- FSE block v2 — style presets for both single and grouped rating blocks
- Mirror management in block editor (CRUD + inline rename, unified search, shared helpers)
- Editor request deduplication & CDN compatibility (1.11.4)
- Rating types: stars, like/dislike, numeric, approval (1.12.0)
- Post Meta Box: link ratings to posts/pages with auto-injection & JSON-LD (1.12.0)

🚧 Next up:
- Server-side render pre-fetch (batch query for frontend pages)
- Statistics caching
- Rate limit performance caching

🚧 Later:
- Mirror vote tracking (engagement analytics for mirrors vs. originals)
- Rating notes/comments
- Votes/notes management UI
- Alternative calendar display hook (Jalali/Shamsi)
- Emoji reactions (separate system from rating types)
- Archive injection (pre_get_posts sorting by rating)
- Bulk-link tool (assign ratings to multiple posts at once)
- Block editor sidebar: show linked rating info in post sidebar
- Type-aware analytics display (like/dislike charts, approval gauges)

🚧 Future:
- Email notifications
- Webhook integration

---

## 1.12.x

### Rating Types

Four rating type modes with full-stack support from DB to frontend.

**Types:**
- **Stars** (default) — Classic 1–N star rating, scale configurable 2–10
- **Like/Dislike** — Thumbs up/down binary vote; rating_value=1 (like) or 0 (dislike), total_rating stores like count
- **Numeric** — Same as stars but without star visual metaphor, scale 2–10
- **Approval** — Single upvote button, rating_value always 1

**DB Changes:**
- New columns: `rating_type VARCHAR(20) DEFAULT 'stars'`, `scale TINYINT UNSIGNED DEFAULT 5`
- Migration v1.5.0 adds columns to existing tables
- Binary types (like_dislike, approval) force scale=1; stars/numeric allow 2–10
- Vote validation allows 0 for dislike votes

**Full-Stack Changes:**
- Database: create_rating/update_rating accept rating_type + scale; vote validation updated
- REST API: rating_type + scale in create/update args; stats response includes total_rating
- AJAX: Type-aware vote normalization; binary types stored as-is; response includes rating_type
- Shortcodes: Type-branched HTML rendering (thumb buttons, upvote button, or stars)
- Frontend JS: submitBinaryRating() for like/dislike/approval; fetchFreshData branches by type
- Frontend CSS: Like/dislike button styles, approval button styles, dark preset overrides
- Admin: Rating type + scale fields in create form and inline edit; type badge in list column; JS toggle hides scale for binary types

### Post Meta Box

Link ratings to posts/pages directly from the post editor. Automatic content injection and JSON-LD structured data.

**Features:**
- **Meta Box** — Checkbox list in post editor sidebar to select ratings for the current post
- **Content Injection** — Linked ratings auto-rendered before or after post content via `the_content` filter
- **JSON-LD** — AggregateRating structured data output in `wp_head` for star/numeric ratings with votes
- **Admin Columns** — "Ratings" column in post list tables shows linked rating names
- **REST API Field** — `shuriken_rating_ids` field on supported post types (read + write)
- **Settings** — New "Content" settings tab: select enabled post types, choose injection position (before/after/disabled)
- **Filters** — `shuriken_meta_box_post_types`, `shuriken_content_injection_position`, `shuriken_rating_jsonld`

**Files Added:**
- `includes/class-shuriken-post-meta.php` — Post meta box class
- `admin/partials/settings-content.php` — Content settings tab

---

## 1.11.x

### Mirror Management in Block Editor

Full mirror CRUD and management integrated into the Grouped Rating block editor, eliminating the need to use the admin Ratings page for mirror operations.

**Features:**
- **Unified Rating Selector** — Single `ComboboxControl` dropdown searches both parent ratings and mirrors (`parents_and_mirrors` search type). Selecting a mirror auto-decomposes into `ratingId` (source) + `mirrorId` (display override)
- **Mirror CRUD in Modals** — Create, rename, and delete mirrors for the parent rating (Edit Parent modal) and for each sub-rating (Manage Sub-Ratings modal)
- **Inline Rename** — Click the edit icon on any mirror card to rename it in-place; supports Enter to save, Escape to cancel, with busy indicator
- **Shared Block Helpers** — Common utilities extracted to `blocks/shared/block-helpers.js`
- **Polished Modal UI** — All three modals (Create, Edit Parent, Manage Sub-Ratings) redesigned with CSS classes, card-based mirror rows, section headers with count badges, consistent empty/loading states
- **Block Inserter Preview** — Both blocks now provide an `example` property in block.json for a proper inserter thumbnail
- **Batch Mirror Fetching** — New `GET /ratings/batch?ids=…` endpoint and `fetchRatingsBatch()` store thunk load all mirror data in one API call, replacing N+1 individual requests
- **Graceful Error Recovery** — Mirror/batch fetch failures no longer show user-facing errors; batch falls back to individual fetches for compatibility with stale caches

**New REST Endpoints:**
- `GET /ratings/{id}/mirrors` — Returns all mirrors of a given rating (permission: `edit_posts`)
- `GET /ratings/batch?ids=1,2,3` — Batch-fetch multiple ratings by ID with mirror vote data resolved (permission: `edit_posts`, max 50)

**New Search Type:**
- `parents_and_mirrors` — Search type for `/ratings/search` that returns parent ratings plus mirrors whose source is a parent, with vote data batch-resolved from source ratings

### 1.11.4 — Editor Request Optimization & CDN Compatibility

Addresses HTTP 508 (Resource Limit) errors on LiteSpeed/shared hosting when editing posts with multiple Shuriken blocks, and prevents Cloudflare/CDN from caching or transforming REST API JSON responses.

**Root Cause:** When N blocks mount simultaneously in the block editor, each dispatches `fetchRating`, `fetchParentRatings`, and `fetchMirrorableRatings` in the same React render tick — before any response arrives. This produces 3×N concurrent requests plus WordPress's own ~10 editor API calls, overwhelming PHP process limits.

**Fixes:**
- **Promise-level deduplication** (`dedup()`) — An in-flight map ensures only one network request per unique key runs at a time. Subsequent calls for the same key receive the existing promise. Applied to all five store thunks.
- **Automatic batch scheduling** (`scheduleBatchFetch()` / `flushBatchFetch()`) — Individual `fetchRating(id)` calls across blocks are collected during a `setTimeout(0)` microtask tick and flushed as a single `GET /ratings/batch?ids=…` request.
- **Scoped authentication filter** — `rest_authentication_errors` filter now only bypasses nonce verification for the two public endpoints (`/nonce` and `/ratings/stats`), instead of globally returning `true` for all logged-in users on all REST endpoints.
- **CDN-safe response headers** — `rest_post_dispatch` adds `Cache-Control: no-store`, `CDN-Cache-Control: no-store`, and `X-Content-Type-Options: nosniff` to all Shuriken REST responses.
- **Output buffer cleaning** — `rest_pre_serve_request` discards stray PHP output (from other plugins) before JSON serialization on Shuriken routes.

**Net Effect:** A post with 5 blocks now makes 3 Shuriken API requests (1 batch + 1 parents + 1 mirrorable) instead of 15+.
- `parents_and_mirrors` — Search type for `/ratings/search` that returns parent ratings plus mirrors whose source is a parent, with vote data batch-resolved from source ratings

### Shortcode Extensions

Shortcode system expanded to match block editor capabilities.

**Features:**
- **Grouped Rating Shortcode** — New `[shuriken_grouped_rating]` shortcode renders a parent rating with all sub-ratings, supporting `grid` and `list` layouts
- **Preset Styles** — Both `[shuriken_rating]` and `[shuriken_grouped_rating]` accept a `style` parameter to apply the same presets available in blocks (e.g. `card`, `dark`, `gradient`, `boxed`)
- **Custom Colors** — `accent_color` and `star_color` parameters inject CSS custom properties (`--shuriken-user-accent`, `--shuriken-user-star-color`) for per-shortcode color overrides

---

## 1.10.3

### FSE Block Redesign — Style Presets (v2)

Both Gutenberg blocks redesigned with a preset-based visual system, replacing the previous large set of granular style attributes.

**Changes:**
- **Style presets per block** — WordPress Block Styles API (`styles` array in block.json) drives visual variants
- **Single Rating Block** (5 presets): Classic (default, backward-compatible), Card, Minimal, Dark, Outlined
- **Grouped Rating Block** (5 presets): Gradient (default), Minimal, Boxed, Dark, Outlined
- **CSS custom properties**: Two user-overridable variables (`--shuriken-user-accent`, `--shuriken-user-star-color`) fed via colour picker attributes (`accentColor`, `starColor`)
- **Simplified inspector panels**: Single rating → 2 panels (Settings, Colors); Grouped → 3 panels (Settings, Layout, Colors)
- **Live editor preview fix**: Block wrapper and frontend output share the same element, ensuring `is-style-*` classes target the correct DOM node in both contexts
- **PHP trim fix**: `wrap_with_block_attributes()` now `trim()`s `ob_start()` output before regex matching
- **Code cleanup**: Removed unused store selectors, unified `STORE_NAME` fallback pattern, surfaced `storeError` in grouped block, removed dead CSS rule in grouped editor.css, removed duplicate `style`/`editor_style` args from `register_block_type()` PHP calls

**Files Changed:**
- `blocks/shuriken-rating/block.json` — v2.0.0, 5 style presets, 5 attributes (ratingId, titleTag, anchorTag, accentColor, starColor)
- `blocks/shuriken-rating/index.js` — Rewritten editor UI with preset-aware blockProps, PanelColorSettings
- `blocks/shuriken-rating/editor.css` — Minimal editor-only overrides (transition, pointer-events)
- `blocks/shuriken-grouped-rating/block.json` — v2.0.0, 5 style presets, 6 attributes (adds childLayout)
- `blocks/shuriken-grouped-rating/index.js` — Rewritten editor UI with merged blockProps, Layout panel
- `blocks/shuriken-grouped-rating/editor.css` — Removed dead descendant selector
- `assets/css/shuriken-reviews.css` — Added full preset CSS for both blocks (~600 new lines)
- `includes/class-shuriken-block.php` — CSS variable injection, `trim()` fix, simplified `register_block_type()` calls

---

## 1.10.0 (Released)

### Vote Rate Limiting

Comprehensive vote rate limiting system to prevent abuse and spam, with a modern tabbed settings UI.

**Features:**
- **Cooldown Between Votes** - Configurable delay between votes on the same rating item
- **Hourly & Daily Limits** - Separate limits for logged-in members and guests
- **Admin Bypass** - Administrators bypass rate limits by default (extendable via filter)
- **Modern Settings UI** - New tabbed settings interface with card-based layout
- **Extensive Hooks** - 5 new filters and 2 new actions for complete customization
- **Disabled by Default** - Opt-in feature, won't affect existing sites until enabled

**New Settings:**
| Setting | Default (Members) | Default (Guests) |
|---------|-------------------|------------------|
| Vote Cooldown | 60 seconds | 60 seconds |
| Hourly Limit | 30 votes | 10 votes |
| Daily Limit | 100 votes | 30 votes |

**New Hooks:**
- `shuriken_rate_limit_settings` - Modify rate limits programmatically
- `shuriken_bypass_rate_limit` - Bypass limits for specific users/roles
- `shuriken_rate_limit_check_result` - Override rate limit check results
- `shuriken_get_user_ip` - Customize IP detection (for proxies/CDNs)
- `shuriken_before_rate_limit_check` - Action fired before checks
- `shuriken_rate_limit_exceeded` - Action fired when limit is hit (for logging/analytics)
- `shuriken_settings_tabs` - Add custom settings tabs

**Files Added:**
- `includes/interfaces/interface-shuriken-rate-limiter.php` - Rate limiter interface
- `includes/class-shuriken-rate-limiter.php` - Rate limiter service implementation
- `admin/partials/settings-general.php` - General settings tab partial
- `admin/partials/settings-rate-limiting.php` - Rate limiting settings tab partial
- `assets/css/admin-settings.css` - Modern settings page styles
- `assets/js/admin-settings.js` - Settings page interactions
- `tests/example-mock-rate-limiter.php` - Mock rate limiter for testing

**Files Changed:**
- `admin/settings.php` - Refactored to tabbed navigation system
- `includes/class-shuriken-database.php` - New rate limiting query methods:
  - `get_last_vote_time()` - Get timestamp of last vote on a rating
  - `count_votes_since()` - Count votes within a time window
  - `get_oldest_vote_in_window()` - Get oldest vote for reset calculation
- `includes/interfaces/interface-shuriken-database.php` - Added new method signatures
- `includes/class-shuriken-container.php` - Registered rate_limiter service
- `includes/class-shuriken-ajax.php` - Integrated rate limiting checks
- `includes/class-shuriken-admin.php` - Added settings scripts enqueue
- `shuriken-reviews.php` - Added rate limiter includes, version bump

---

## 1.9.1 (Released)

### Voter Activity Page

Comprehensive voter tracking and analytics for both members and guests.

**Features:**
- **Clickable Voter Names** - Click any voter in Analytics or Item Stats to view their complete voting history
- **Member & Guest Support** - Track votes from registered users (by user ID) and guests (by IP address)
- **Voter Statistics** - Total votes, average rating, and voting tendency (generous/balanced/critical)
- **Visual Charts** - Star distribution and activity over time using Chart.js
- **CSV Export** - Export individual voter's complete vote history
- **Date Range Filtering** - Filter by last 7 days, 30 days, 90 days, or all time
- **Source Column** - Vote History for parent ratings shows which sub-rating each vote belongs to
- **Dark Mode Support** - Complete dark mode styling for the new page

**Files Added:**
- `admin/voter-activity.php` - Voter activity page template

**Files Changed:**
- `includes/class-shuriken-analytics.php` - New voter methods:
  - `get_voter_votes_paginated()` - Paginated vote history
  - `get_voter_stats()` - Summary statistics
  - `get_voter_rating_distribution()` - Star distribution data
  - `get_voter_activity_over_time()` - Activity trend data
  - `get_user_info()` - Get user details by ID
  - `get_voter_votes_for_export()` - CSV export data
- `includes/class-shuriken-admin.php` - Voter activity page registration and export handler
- `admin/item-stats.php` - Added Source column, clickable voter names
- `admin/analytics.php` - Clickable voter names in Recent Activity
- `assets/css/admin-analytics.css` - Voter activity page styles (light & dark mode)

---

## 1.9.0 (Released)

### Data Retrieval Efficiency

Major performance optimization for FSE editor and frontend.

**What was done:**
- Shared `@wordpress/data` store prevents duplicate API calls across block instances
- AJAX search-as-you-type dropdown replaces loading all ratings upfront
- Batch `get_ratings_by_ids()` DB method — single query for stats endpoint
- `/ratings/search` and `/ratings/{id}/children` REST endpoints
- Loading state feedback when cached data refreshes

**Still pending:**
- Server-side render pre-fetch (collect all block IDs, one batch query for frontend pages)

---

## 1.7.5 (Released)

✅ Major refactor and stabilization
- Split the large main plugin file into focused modules
- Added DI container + service wiring
- Added interfaces and mock implementations for testing
- Added exception types + centralized handling
- Fixed nonce validation with cached pages (REST API nonce)
- Fixed star rating normalization behavior
- Added Parent/Child Grouped Ratings Block with full CRUD management
  - Create/edit parent ratings
  - Add/edit/delete sub-ratings with batch updates (Apply button)
  - Manage effect types (positive/negative)
  - Visual indicators for unsaved changes
  - New grouped rating frontend styling
- Implemented comprehensive error handling in FSE blocks
  - User-friendly error messages
  - Retry functionality for failed operations
  - Integration with backend exception system
  - DELETE endpoint for ratings

---

## Planned:

### Rate Limit Performance Caching
Goal: reduce DB queries for rate limit checks on high-traffic sites.

Implementation checklist:
- [ ] Cache vote counts in transients (per user/IP)
- [ ] Set appropriate TTL (e.g., 60 seconds)
- [ ] Invalidate on new vote
- [ ] Add filter to disable caching if needed

### Statistics Caching
Goal: reduce repeated DB work for rating stats.

Implementation checklist:
- [ ] Add cache service to container
- [ ] Add cache get/set to analytics layer (TTL)
- [ ] Invalidate cache on vote changes
- [ ] (Optional) Redis support

### Mirror Vote Tracking
Goal: let admins see how voters engage with mirrors vs. original (parent) ratings — compare vote volume, averages, and trends across mirrors and their sources.

Implementation checklist:
- [ ] Analytics query methods for mirror vs. original vote breakdown
- [ ] Per-mirror stats: vote count, average rating, trend over time
- [ ] Comparison view: side-by-side mirror vs. original engagement
- [ ] Admin UI page/tab for mirror vote analytics
- [ ] Charts (mirror vs. original distribution, activity over time)
- [ ] CSV export for mirror engagement data
- [ ] Hooks for extending mirror analytics (`shuriken_mirror_vote_stats`, etc.)

### Rating Notes / Comments
Goal: let users attach notes/comments to ratings.

Implementation checklist:
- [ ] New notes table
- [ ] Notes CRUD in database service
- [ ] Frontend notes display and submission UI
- [ ] Admin moderation/management page
- [ ] Hooks for note create/update/delete
- [ ] REST endpoints

### Votes & Notes Management
Goal: admin + user dashboards for managing votes/notes.

Implementation checklist:
- [ ] Admin pages for listing/searching
- [ ] Bulk operations
- [ ] Exports
- [ ] User-facing “my activity” view

### Calendar Display Hook
Goal: allow alternative date formats (e.g. Jalali/Shamsi) without changing stored dates.

Implementation checklist:
- [ ] Add `shuriken_display_date` filter hook
- [ ] Route all date displays through a helper
- [ ] Document usage + examples

---

## 2.0.0+ (Future)

### Email Notifications
- Notify admins on low ratings
- Notify item owners on new votes
- Digest emails

### Webhook Integration
- POST rating events to external services
- Retry/failure handling

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

# Shuriken Reviews Architecture Overview

This document provides a high-level overview of the plugin's structure, design decisions, and how different components interact.

## Table of Contents

- [Core Components](#core-components)
- [Module Responsibilities](#module-responsibilities)
- [Data Flow](#data-flow)
- [Design Patterns](#design-patterns)
- [Service Container](#service-container)
- [Entry Points](#entry-points)

---

## Core Components

### 1. Main Plugin File (`shuriken-reviews.php`)

**Purpose:** Orchestration and hook registration

**Responsibilities:**
- Register plugin hooks
- Initialize the service container
- Load and instantiate modules
- Load text domain for translations
- Handle plugin activation/deactivation

**Key Functions:**
- `shuriken_container()` - Get the DI container
- `shuriken_db()` - Get database service
- `shuriken_analytics()` - Get analytics service
- `shuriken_voter_analytics()` - Get voter analytics service

### 2. Service Container (`class-shuriken-container.php`)

**Purpose:** Centralized service management

**Features:**
- Register singleton and transient services
- Resolve service dependencies
- Type-safe service retrieval
- Support for factory callbacks

**Registered Services:**
```php
database        → Shuriken_Database (implements Shuriken_Database_Interface; façade delegates to repositories)
ratings_repo    → Shuriken_Rating_Repository
votes_repo      → Shuriken_Vote_Repository
schema_manager  → Shuriken_Schema_Manager
analytics       → Shuriken_Analytics (implements Shuriken_Analytics_Interface; delegates to formatter, ranking, dashboard, rating_stats, context)
voter_analytics → Shuriken_Voter_Analytics (implements Shuriken_Voter_Analytics_Interface)
rate_limiter    → Shuriken_Rate_Limiter (implements Shuriken_Rate_Limiter_Interface)
rest_api      → Shuriken_REST_API (bootstrap; wires Shuriken_REST_Ratings_Controller + Shuriken_REST_Votes_Controller)
shortcodes    → Shuriken_Shortcodes
block         → Shuriken_Block
ajax          → Shuriken_AJAX
frontend      → Shuriken_Frontend
admin         → Shuriken_Admin
```

**Note:** `Shuriken_Exception_Handler` is a utility class loaded directly (not registered in the container) — it provides static-style error formatting and does not depend on other services.

### 3. Database Layer

**Façade:** `class-shuriken-database.php` (`Shuriken_Database`)

The `Shuriken_Database` class is a singleton façade that implements `Shuriken_Database_Interface` for full backward compatibility. all 28 interface methods delegate to three focused repository classes. Use `shuriken_db()` for generic access, or prefer the narrower helpers when only one repo is needed.

**Helper functions:**
- `shuriken_db()` — returns the `Shuriken_Database` façade (backward-compatible)
- `shuriken_ratings_repo()` — returns `Shuriken_Rating_Repository` directly
- `shuriken_votes_repo()` — returns `Shuriken_Vote_Repository` directly
- `shuriken_schema_manager()` — returns `Shuriken_Schema_Manager` directly
- `shuriken_cache()` — returns the `Shuriken_Cache_Interface` object-cache adapter

#### 3a. Rating Repository (`class-shuriken-rating-repository.php`)

**Implements:** focused repository (not the full interface)

**Responsibilities:**
- Rating CRUD, search, pagination, hierarchy (parent/child), mirrors
- Contextual stats (`get_contextual_stats()`, `get_contextual_stats_batch()`)
- Bulk export

#### 3b. Vote Repository (`class-shuriken-vote-repository.php`)

**Implements:** focused repository

**Responsibilities:**
- Vote CRUD (`create_vote()`, `get_user_vote()`, `get_votes_for_rating()`)
- Rate-limit timestamp queries (`get_last_vote_time()`, hourly/daily counts)
- Transactional vote+total updates (`recalculate_parent_rating()`)

#### 3c. Schema Manager (`class-shuriken-schema-manager.php`)

**Responsibilities:**
- `create_tables()` — initial table creation and column migrations
- `tables_exist()` — health check
- DB version tracking

#### 3d. Statistics Cache (`class-shuriken-cache.php`)

`Shuriken_Cache` is a small TTL-aware adapter over WordPress `wp_cache_*`.
`Shuriken_Rating_Repository` uses it for resolved global rating reads and
scale-independent contextual totals. Persistent object-cache drop-ins such as
Redis or Memcached make entries reusable across requests; without a drop-in,
the persistent layer stays disabled because the repository and SSR collector
already provide request-local memoization. Mirror objects also remain
request-scoped so vote invalidation never adds mirror-lookup database queries.

The default statistics TTL is 60 seconds and is filterable through
`shuriken_stats_cache_ttl`; caching can be disabled through
`shuriken_stats_cache_enabled`. Vote and rating mutation hooks invalidate
rating, parent, and contextual keys. Archive sorting keeps its indexed
SQL aggregate and uses WordPress's native `WP_Query` result cache with a
Shuriken-specific generation invalidated by contextual vote changes.

**Key Methods (on the façade / repositories):**
- `get_rating($id)` - Retrieve rating
- `get_ratings_by_ids($ids)` - Batch retrieve multiple ratings (single query)
- `get_all_ratings($orderby, $order)` - Retrieve all ratings
- `get_ratings_paginated()` - Paginated ratings for admin list
- `search_ratings($term, $limit, $type)` - Search ratings by name (for AJAX autocomplete)
- `create_rating($name, $parent_id, $effect_type, $display_only, $mirror_of, $rating_type, $scale)` - Insert new rating
- `update_rating($id, $data)` - Update rating (type/scale locked when votes exist)
- `delete_rating($id)` - Delete rating
- `get_rating_children($parent_id)` - Get child ratings for a parent
- `get_rating_mirrors($rating_id)` - Get mirror ratings for a source
- `create_vote($rating_id, $value, $user_id, $context_id, $context_type)` - Record vote (context optional)
- `get_user_vote($rating_id, $user_id, $user_ip, $context_id, $context_type)` - Check existing vote for context
- `get_last_vote_time($rating_id, $user_id, $user_ip, $context_id, $context_type)` - Last vote timestamp for rate limiting
- `get_contextual_stats($rating_id, $context_id, $context_type, $scale)` - Per-context vote totals, `average`, and `display_average`
- `get_contextual_stats_batch($rating_ids, $context_id, $context_type, $scales)` - Batch contextual stats; `$scales` is a `rating_id => display_scale` map
- `get_votes_for_rating($rating_id)` - Get all votes

**Exception Handling:**
Throws `Shuriken_Database_Exception` on failures instead of returning false.

### 4. Analytics Service (`class-shuriken-analytics.php`)

**Implements:** `Shuriken_Analytics_Interface`
**Uses:** `Shuriken_Analytics_Helpers` trait
**Composes:** `Shuriken_Analytics_Formatter`, `Shuriken_Analytics_Ranking`, `Shuriken_Analytics_Dashboard`, `Shuriken_Analytics_Rating_Stats`, `Shuriken_Analytics_Context`

**Responsibilities:**
- Thin coordinator (~278 lines) — delegates to five composed services; applies extensibility filters on ranking and overall stats
- Type-aware analytics (stars, like/dislike, numeric, approval)

**Sub-interfaces:** `Shuriken_Analytics_Interface` extends `Shuriken_Analytics_Formatter_Interface`, `Shuriken_Analytics_Ranking_Interface`, `Shuriken_Analytics_Dashboard_Interface`, `Shuriken_Analytics_Rating_Stats_Interface`, and `Shuriken_Analytics_Context_Interface` for backward-compatible full-contract access.

#### 4a. Analytics Formatter (`class-shuriken-analytics-formatter.php`)

Stateless display formatting service composed into `Shuriken_Analytics`.

**Methods:** `format_average_display()`, `format_vote_display()`, `format_time_ago()`, `format_date()`, `get_date_range_label()`

#### 4b. Analytics Ranking (`class-shuriken-analytics-ranking.php`)

Ranking engine composed into `Shuriken_Analytics`.

**Methods:** parametric `get_ranked()` (replaces `get_top_rated()`, `get_most_voted()`, `get_low_performers()`); `get_inversion_sql()` static helper for effect-type inversion.

#### 4c. Analytics Context (`class-shuriken-analytics-context.php`)

Per-post contextual analytics service composed into `Shuriken_Analytics`.

**Key Methods:** `has_contextual_votes()`, `get_rating_context_summary()`, `get_rating_contexts_paginated()`, `get_context_rating_stats()`, and 8 more contextual methods.

#### 4d. Analytics Dashboard (`class-shuriken-analytics-dashboard.php`)

Site-wide overview analytics composed into `Shuriken_Analytics`.

**Methods:** `get_overall_stats()`, `get_contextual_post_count()`, `get_rating_type_counts()`, `get_vote_counts()`, `get_vote_change_percent()`, `get_type_benchmark()`, `get_voting_heatmap()`, `get_votes_over_time_by_type()`, `get_per_type_summary()`, `get_participation_rate()`, `get_momentum_items()`

#### 4e. Analytics Rating Stats (`class-shuriken-analytics-rating-stats.php`)

Per-rating SQL and breakdown queries composed into `Shuriken_Analytics`.

**Methods:** `get_rating_stats()`, `get_parent_rating_stats_breakdown()`, `get_rating_distribution()`, `get_votes_over_time()`, `get_rating_votes_paginated()`, `get_chart_data()`, `get_approval_trend()`, `get_cumulative_approvals()`, `get_votes_with_rolling_avg()`, `build_scope_condition()`, and more.

### 4f. Voter Analytics Service (`class-shuriken-voter-analytics.php`)

**Implements:** `Shuriken_Voter_Analytics_Interface`
**Uses:** `Shuriken_Analytics_Helpers` trait

**Responsibilities:**
- Voter-specific activity history and statistics
- Voting distribution per voter
- Voter data export

**Key Methods:**
- `get_voter_votes_paginated($user_id, $user_ip)` - Paginated vote history
- `get_voter_stats($user_id, $user_ip)` - Voting statistics and tendency
- `get_voter_rating_distribution($user_id, $user_ip)` - Deviation-from-average distribution
- `get_voter_activity_over_time($user_id, $user_ip)` - Activity timeline
- `get_user_info($user_id)` - WordPress user profile data
- `get_voter_votes_for_export($user_id, $user_ip)` - All votes for CSV export

### 4c. Analytics Helpers Trait (`trait-shuriken-analytics-helpers.php`)

**Purpose:** Shared query helpers for analytics classes

**Methods:**
- `build_date_condition($date_range, $column)` - SQL date filtering (relative days, custom range, all time)

### 5. Rate Limiter Service (`class-shuriken-rate-limiter.php`)

**Implements:** `Shuriken_Rate_Limiter_Interface`

**Responsibilities:**
- Enforce vote rate limits (cooldown, hourly, daily)
- Track user voting activity
- Provide bypass rules for administrators
- Fire hooks for custom rate limiting logic

**Key Methods:**
- `can_vote($user_id, $user_ip, $rating_id)` - Check if user can vote (throws exception if blocked)
- `get_limits($user_id)` - Get current rate limit settings
- `get_usage($user_id, $user_ip)` - Get current voting usage statistics
- `get_cooldown_remaining($user_id, $user_ip, $rating_id)` - Seconds until cooldown expires
- `should_bypass($user_id, $user_ip)` - Check if user bypasses rate limiting

**Settings (configurable via admin):**
- `shuriken_rate_limiting_enabled` - Enable/disable rate limiting
- `shuriken_vote_cooldown` - Seconds between votes on same rating (default: 60)
- `shuriken_hourly_vote_limit` - Max votes per hour for members (default: 30)
- `shuriken_daily_vote_limit` - Max votes per day for members (default: 100)
- `shuriken_guest_hourly_limit` - Max votes per hour for guests (default: 10)
- `shuriken_guest_daily_limit` - Max votes per day for guests (default: 30)

**Exception Handling:**
Throws `Shuriken_Rate_Limit_Exception` with specific error codes and retry-after times.

### 6. Exception Handler (`class-shuriken-exception-handler.php`)

**Purpose:** Unified error handling across contexts

**Features:**
- Maps exceptions to appropriate HTTP status codes
- Logs exceptions with context
- Formats error responses for different contexts (AJAX, REST, Admin)
- Converts to WP_Error for WordPress compatibility

**Methods:**
- `handle_ajax_exception($exception)` - Send JSON error response
- `handle_rest_exception($exception)` - Return WP_REST_Response
- `handle_admin_exception($exception, $redirect)` - Show admin notice
- `safe_execute($callback, $context, $default)` - Execute with fallback

---

## Module Responsibilities

```
┌─────────────────────────────────────────────────────────────────┐
│                    shuriken-reviews.php                         │
│                    (Initialization & Routing)                   │
└─────────────────────────────────────────────────────────────────┘
                               │
                ┌──────────────┼──────────────┐
                │              │              │
        ┌───────▼─────┐  ┌─────▼──────┐  ┌──▼────────────┐
        │  Frontend    │  │  REST API  │  │    Admin      │
        │              │  │            │  │               │
        │ - Enqueue    │  │ - GET/POST │  │ - Pages       │
        │   CSS/JS     │  │   /ratings │  │ - Forms       │
        │ - Localize   │  │ - /votes   │  │ - Settings    │
        │   data       │  │ - /stats   │  │ - Analytics   │
        └──────┬───────┘  └─────┬──────┘  └──┬────────────┘
               │                │            │
        ┌──────▼──────┐  ┌──────▼────┐  ┌───▼──────────┐
        │   AJAX      │  │ Shortcode  │  │   Block      │
        │             │  │            │  │              │
        │ - Vote      │  │ - Render   │  │ - Register   │
        │   submission│  │   rating   │  │ - Render     │
        │ - Error     │  │ - Apply    │  │ - Edit UI    │
        │   handling  │  │   hooks    │  │              │
        └──────┬──────┘  └──────┬─────┘  └───┬──────────┘
               │                │            │
               └────────────────┼────────────┘
                                │
                ┌───────────────┴────────────────┐
                │                                │
        ┌───────▼──────────┐          ┌─────────▼────────┐
        │  DI Container    │          │ Exception Handler│
        │                  │          │                  │
        │ - Register       │          │ - Log errors     │
        │   services       │          │ - Format         │
        │ - Resolve        │          │   responses      │
        │   dependencies   │          │ - Map HTTP codes │
        └────────┬─────────┘          └──────────────────┘
                 │
        ┌────────┴──────────────────────────┐
        │                                    │
    ┌───▼──────────┐          ┌─────────────▼───────┐
    │  Database    │          │    Analytics        │
    │  Service     │          │    Service          │
    │              │          │                     │
    │ - CRUD ops   │          │ - Statistics        │
    │ - Throw      │          │ - Trends            │
    │   exceptions │          │ - Reports           │
    └──────────────┘          └─────────────────────┘
```

### Frontend Module (`class-shuriken-frontend.php`)

Responsible for enqueuing frontend assets and localizing JavaScript data.

**Hooks:**
- `wp_enqueue_scripts` - Load CSS/JS
- `wp_localize_script` - Pass PHP data to JS
- `wp_footer` - Output inline scripts

### REST API Module (`class-shuriken-rest-api.php`)

`Shuriken_REST_API` is a thin bootstrap (singleton). all route logic lives in two focused controllers. Cross-cutting concerns (auth bypass, output buffer cleaning, CDN cache headers) remain on the bootstrap.

**Controllers:**
- `Shuriken_REST_Ratings_Controller` — 11 rating endpoints (CRUD, hierarchy, mirrors, search, batch) with arg schemas and permission callbacks
- `Shuriken_REST_Votes_Controller` — 3 endpoints: stats (public), context-stats (editor), nonce (public)

**Endpoints:**
- `GET    /wp-json/shuriken-reviews/v1/ratings` - List all ratings
- `POST   /wp-json/shuriken-reviews/v1/ratings` - Create rating (rating_type, scale supported)
- `GET    /wp-json/shuriken-reviews/v1/ratings/{id}` - Get single rating
- `PUT    /wp-json/shuriken-reviews/v1/ratings/{id}` - Update rating
- `DELETE /wp-json/shuriken-reviews/v1/ratings/{id}` - Delete rating
- `GET    /wp-json/shuriken-reviews/v1/ratings/search` - AJAX autocomplete search
- `GET    /wp-json/shuriken-reviews/v1/ratings/parents` - Parent ratings only
- `GET    /wp-json/shuriken-reviews/v1/ratings/mirrorable` - Mirrorable ratings
- `GET    /wp-json/shuriken-reviews/v1/ratings/batch` - Batch-fetch by IDs (max 50)
- `GET    /wp-json/shuriken-reviews/v1/ratings/{id}/children` - Child ratings
- `GET    /wp-json/shuriken-reviews/v1/ratings/{id}/mirrors` - Mirror ratings
- `GET    /wp-json/shuriken-reviews/v1/ratings/stats` - Batch stats (optimized; supports `context_id` + `context_type` for per-post stats)
- `GET    /wp-json/shuriken-reviews/v1/nonce` - Fresh nonce for AJAX fallback

**Note:** Vote submission uses the WordPress AJAX handler (`wp_ajax_submit_rating`) rather than a REST endpoint.

### Shortcodes Module (`class-shuriken-shortcodes.php`)

Handles `[shuriken_rating]` and `[shuriken_grouped_rating]` shortcode registration and rendering.

**Responsibility:**
- Parse shortcode attributes (id, tag, anchor_tag, style, accent_color, star_color, layout, context_id, context_type) — `context_id` and `context_type` enable per-context (per-post) voting when provided.
- Validate input: `id` is coerced to int; `context_id` is coerced to int; `context_type` is sanitized and validated against the `shuriken_allowed_context_types` filter (defaults: `post`, `page`, `product`).
- When contextual parameters are present, the shortcodes pass them to `render_rating_html()` so the returned HTML includes `data-context-id` / `data-context-type` and per-context stats overlay vote totals. Since v1.15.6, `render_rating_html()` reads from `Shuriken_Contextual_Stats_Collector` (batch pre-fetch) when active, falling back to `get_contextual_stats()` for isolated calls.
- For grouped ratings, the same context is applied to the parent and all children so vote tallies are scoped consistently.
- Render single or grouped ratings with preset style classes and CSS custom properties
- Return HTML

**Usage examples:**

Shortcode (in post content):

```
[shuriken_rating id="5" context_id="42" context_type="post"]
[shuriken_grouped_rating id="3" context_id="42" context_type="post"]
```

PHP (in theme templates):

```
echo do_shortcode( '[shuriken_rating id="5" context_id="' . get_the_ID() . '" context_type="post"]' );
```

### Contextual Stats Collector (`class-shuriken-contextual-stats-collector.php`)

Request-scoped batch pre-fetch for contextual stats during SSR (Step 8b).

**Responsibility:**
- Register `source_id` per context group before widgets render
- Flush pending IDs per context group via `get_contextual_stats_batch()` on first `get()` miss (vote totals are scale-independent)
- Denormalize `display_average` per widget in `get()` from the requested scale
- Serve stats to `render_rating_html()`; inactive outside frontend render (admin, REST, AJAX, isolated calls fall back to single query)

**Registration hooks** (wired in `Shuriken_Frontend`):
- `the_content` priority 1 — `parse_blocks()` + shortcode scan on stored content before `do_blocks()`
- `pre_render_block` — supplements Query Loop dynamic `postId` / mirror and sub-rating IDs

**Helper:** `shuriken_contextual_stats_collector()`

### Block Module (`class-shuriken-block.php`)

Registers and renders Gutenberg blocks.

**Features:**
- FSE (Full Site Editor) compatible
- Uses same rendering as shortcode
- Gutenberg editor UI
- Block attributes management
- Shared data store registration

**Blocks:**
- `shuriken-reviews/rating` - Single rating display (supports `postContext` for per-post voting)
- `shuriken-reviews/grouped-rating` - Parent with child ratings (supports `postContext` for per-post voting; `gap` attribute controls `--shuriken-gap` vertical spacing)

### Shared Data Store (`blocks/shared/ratings-store.js`)

Centralized state management for all rating blocks using `@wordpress/data`.

**Features:**
- Prevents duplicate API calls across multiple blocks
- Caches fetched ratings by ID
- Debounced AJAX search for rating selection
- Shared state between all block instances
- **Promise-level deduplication** — `dedup(key, fn)` maintains an `_inflight` map of in-progress promises. If a request for the same key is already in flight, the existing promise is returned instead of starting a new network call. Applied to all async thunks (`fetchRating`, `fetchParentRatings`, `fetchMirrorableRatings`, `fetchChildRatings`, `fetchMirrorsForRating`).
- **Automatic batch scheduling** — `scheduleBatchFetch(ratingId, args)` collects individual rating IDs during the current microtask tick via `setTimeout(0)`. When the tick flushes (`flushBatchFetch`), all collected IDs are fetched in a single `GET /ratings/batch?ids=…` request, with results dispatched individually. Falls back to a single-ID fetch when only one rating is queued.

**Store Name:** `shuriken-reviews`

**Key Selectors:**
- `getRating(id)` - Get cached rating
- `getSearchResults()` - Current search results
- `getParentRatings()` - All parent ratings
- `getMirrorsForRating(id)` - Get cached mirrors for a rating
- `isSearching()` - Search loading state
- `isLoadingMirrors(id)` - Mirror loading state

**Key Actions:**
- `fetchRating(id)` - Fetch single rating (cache-first)
- `fetchRatingsBatch(ids)` - Batch-fetch multiple ratings in one API call (skips cached)
- `searchRatings(term, type, limit)` - AJAX search
- `fetchParentRatings()` - Load parent ratings
- `fetchMirrorsForRating(id)` - Fetch mirrors for a rating
- `invalidateMirrorsCache(id)` - Clear cached mirrors
- `createRating(data)` - Create new rating
- `updateRating(id, data)` - Update rating
- `deleteRating(id)` - Delete rating

### Shared Block Helpers (`blocks/shared/block-helpers.js`)

Reusable utilities shared between both block editors.

**Exposed via:** `window.ShurikenBlockHelpers`

**Functions:**
- `formatApiError(error)` - Format API errors for display
- `makeErrorHandler(setError, setLastFailed)` - Create error handler callbacks
- `makeErrorDismissers(setError, setLastFailed, clearError)` - Create dismiss/retry helpers
- `useSearchHandler(searchFn, type, limit, delay)` - Debounced search hook
- `titleTagOptions` - Shared array of title tag select options
- `calculateAverage(rating)` - Calculate rating average from total_rating/total_votes
- `ratingTypeOptions` - Shared array of rating type select options (stars, like/dislike, numeric, approval)
- `getScaleRange(ratingType)` - Get valid {min, max} scale range for a rating type
- `getRatingType(rating)` - Safe accessor for rating_type (defaults to 'stars')
- `getRatingScale(rating)` - Safe accessor for scale (defaults to 5)
- `calculateScaledAverage(rating)` - Read `rating.display_average` (pre-computed by the data layer) and return it directly; no client-side math
- `renderRatingPreview(rating, createElement)` - Type-branched editor preview returning `[widgetEl, statsEl]`. For display-only numeric ratings renders a `.shuriken-numeric-display` span (matching PHP output) instead of the full slider widget
- `formatCompactStats(rating)` - One-line type-aware stats string for compact display

### AJAX Module (`class-shuriken-ajax.php`)

Handles AJAX requests for vote submission.

**Responsibility:**
- Validate AJAX requests
- Process vote submission
- Handle errors
- Return JSON response

### Admin Module (`class-shuriken-admin.php`)

Manages WordPress admin pages.

**Pages:**
- Ratings management
- Comments settings
- Analytics dashboard
- Plugin settings (includes **About tab** with What's New, Quick Start, Shortcode Reference, Developer Resources, and System Info)

---

## Data Flow

### Vote Submission Flow

```
User clicks star
        │
        ▼
JavaScript sends AJAX request
(or REST request)
        │
        ▼
AJAX Handler validates
- Check nonce
- Validate input
- Check permissions
        │
        ▼
Database operations
- Check if vote exists
- Create or update vote
- Recalculate rating average
  (negative sub-ratings inverted using RATING_SCALE_DEFAULT+1 = 6, not display scale)
- Update total votes
        │
        ▼
Hooks fired
- shuriken_before_submit_vote
- shuriken_vote_created/updated
- shuriken_after_submit_vote
        │
        ▼
Return response to frontend
- New average (contextual if context provided)
- New total votes (contextual)
- Parent rating updates
        │
        ▼
JavaScript updates display
- Animate stars
- Update statistics
- Show message
```

### Rating Creation Flow

```
Admin clicks "Create Rating"
        │
        ▼
Form submitted to REST API
or Admin page handler
        │
        ▼
Validate input
- Required fields
- Data types
- Permissions
        │
        ▼
Apply filter
- shuriken_before_create_rating
        │
        ▼
Database insert
- Create rating record
- Set defaults
- Handle hierarchy (parent/mirror)
        │
        ▼
Fire action
- shuriken_rating_created
        │
        ▼
Return success response
- Redirect to ratings page
- Show success message
```

---

## Contextual Voting

Ratings are **standalone entities** — not tied to any specific post. Contextual voting (DB v1.6.0) lets votes be scoped per post/page/product without duplicating the rating configuration.

### How it works

The votes table has two extra columns: `context_id` (BIGINT) and `context_type` (VARCHAR 50). When a vote is cast without context it behaves exactly as before. When cast with context the vote is tagged to that post, and per-post independent tallies are computed on demand.

| Column | Purpose |
|--------|---------|
| `context_id` | The post/page/product ID (NULL = global) |
| `context_type` | The post type slug: `post`, `page`, `product`, … |

**Unique key:** `(rating_id, user_id, user_ip, context_id, context_type)` — each user can vote once per rating per context. NULLs are accounted for via `COALESCE`.

### Global vs contextual aggregates

`total_votes` / `total_rating` on the ratings table remain **global** aggregates (backward compatible). Per-context totals and averages are computed from the votes table via `get_contextual_stats()` or batched via `get_contextual_stats_batch()` through the request-scoped `Shuriken_Contextual_Stats_Collector` during SSR.

### Block integration

Both `shuriken-reviews/rating` and `shuriken-reviews/grouped-rating` expose a **"Per-post voting"** toggle (`postContext` attribute). When enabled, the PHP render callback reads the `postId` / `postType` from the block's FSE context and passes them through the entire render → AJAX → DB chain. The rendered HTML carries `data-context-id` and `data-context-type` attributes which the frontend JS reads when submitting votes and refreshing stats.

### Allowed context types

```php
// Default: post, page, product
apply_filters('shuriken_allowed_context_types', ['post', 'page', 'product']);
```

---

## Design Patterns

### 1. Service Locator / Dependency Injection

Services are registered in the container and retrieved when needed:

```php
// Container registration
shuriken_container()->singleton('database', function($container) {
    return new Shuriken_Database();
});

// Usage
$db = shuriken_container()->get('database');
// or use helper
$db = shuriken_db();
```

### 2. Factory Pattern

Exceptions use factory methods for consistent error creation:

```php
throw Shuriken_Not_Found_Exception::rating($id);
// Instead of: throw new Shuriken_Not_Found_Exception('Rating not found');
```

### 3. Strategy Pattern

Interfaces allow swapping implementations:

```php
// Production
$db = new Shuriken_Database();

// Testing
$db = new Mock_Shuriken_Database();

$analytics = new Shuriken_Analytics($db); // Works with either
```

### 4. Observer Pattern

WordPress hooks (actions and filters) provide extension points:

```php
// Plugin fires hooks at key points
do_action('shuriken_vote_created', $rating_id, $value, ...);

// Other code responds
add_action('shuriken_vote_created', 'my_email_notification');
```

### 5. Decorator Pattern

Hooks allow wrapping/modifying data:

```php
// Filter modifies data before display
add_filter('shuriken_rating_css_classes', function($classes, $rating) {
    if ($rating->average >= 4) {
        $classes .= ' high-rated';
    }
    return $classes;
});
```

### 6. Interface + Trait Pattern (Exceptions)

Exceptions share behaviour across the SPL hierarchy via an interface and a trait:

```php
// Contract for all plugin exceptions
interface Shuriken_Exception_Interface extends Throwable {
    public function get_error_code();
    public function to_wp_error();
    public function log($context);
}

// Shared implementation mixed into every exception class
trait Shuriken_Exception_Trait { /* get_error_code, to_wp_error, log */ }

// Runtime-family extends \RuntimeException
class Shuriken_Exception extends \RuntimeException
    implements Shuriken_Exception_Interface { use Shuriken_Exception_Trait; }

// Logic-family extends SPL counterparts
class Shuriken_Validation_Exception extends \InvalidArgumentException
    implements Shuriken_Exception_Interface { use Shuriken_Exception_Trait; }
```

All catch sites use `catch (Shuriken_Exception_Interface $e)` for unified handling.
Subclasses still provide factory methods (e.g. `Shuriken_Database_Exception::insert_failed()`).
```

---

## Service Container

The container manages all plugin services and their dependencies.

### DI Adoption Status

All services with database dependencies now use constructor injection:

| Service | Dependencies | DI Status |
|---------|--------------|-----------|
| `Shuriken_Database` | Foundation | Base singleton |
| `Shuriken_Analytics` | `database` | ✅ DI-ready |
| `Shuriken_REST_API` | `database` | ✅ DI-ready |
| `Shuriken_Admin` | `database`, `analytics` | ✅ DI-ready |
| `Shuriken_AJAX` | `database` | ✅ DI-ready |
| `Shuriken_Block` | `database` | ✅ DI-ready |
| `Shuriken_Shortcodes` | `database` | ✅ DI-ready |
| `Shuriken_Frontend` | None | No DI needed |

**Coverage:** 100% (all services with dependencies)

### Service Registration

```php
// Singleton - same instance every time
shuriken_container()->singleton('database', function($container) {
    return Shuriken_Database::get_instance();
});

// With dependencies injected
shuriken_container()->singleton('analytics', function($container) {
    return new Shuriken_Analytics($container->get('database'));
});

shuriken_container()->singleton('admin', function($container) {
    return new Shuriken_Admin(
        $container->get('database'),
        $container->get('analytics')
    );
});

shuriken_container()->singleton('rest_api', function($container) {
    return new Shuriken_REST_API($container->get('database'));
});
```

### Service Resolution

```php
// Get from container
$db = shuriken_container()->get('database');

// Magic property access
$db = shuriken_container()->database;

// Helper function (backward compatible)
$db = shuriken_db();
```

### Benefits of DI

1. **Testability** - Inject mocks instead of real services
2. **Flexibility** - Swap implementations without changing code
3. **Clarity** - Dependencies visible in constructor
4. **Loose Coupling** - Classes depend on interfaces, not concrete classes

### Constructor Injection Pattern

All DI-ready services follow this pattern:

```php
class Shuriken_REST_API {
    /** @var Shuriken_Database_Interface */
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: shuriken_db();
    }
    
    public function get_db() {
        return $this->db;
    }
}
```

This allows:
- **Testing:** `new Shuriken_REST_API($mock_db)`
- **Production:** Container injects real database automatically

---

## Entry Points

### 1. Plugin Initialization

```php
// shuriken-reviews.php
if (!defined('ABSPATH')) {
    exit;
}

define('SHURIKEN_REVIEWS_VERSION', '1.15.6-rc');
define('SHURIKEN_REVIEWS_DIR', plugin_dir_path(__FILE__));
define('SHURIKEN_REVIEWS_URL', plugin_dir_url(__FILE__));

// Load core files
require_once SHURIKEN_REVIEWS_DIR . 'includes/class-shuriken-container.php';
require_once SHURIKEN_REVIEWS_DIR . 'includes/class-shuriken-database.php';
// ... etc

// Initialize container and modules
Shuriken_Reviews::init();
```

### 2. Frontend (Page Request)

```
WordPress loads plugins
    ↓
Plugin loads and initializes
    ↓
Shortcode or block renders (calls shuriken_enqueue_frontend_assets())
    ↓
Frontend module enqueues CSS/JS on demand
    ↓
JavaScript in footer enables interactivity
    ↓
User can see and vote on ratings
```

### 3. AJAX Request

```
JavaScript sends AJAX
    ↓
AJAX module receives request
    ↓
Validates nonce and input
    ↓
Database service processes vote
    ↓
Exceptions caught by handler
    ↓
Response sent to JavaScript
    ↓
Frontend updates display
```

### 4. REST API Request

```
External client makes HTTP request
    ↓
REST API module receives request
    ↓
WordPress REST framework validates
    ↓
Endpoint handler executes
    ↓
Database service performs operation
    ↓
Response returned as JSON
```

### 5. Admin Page Load

```
Admin clicks menu item
    ↓
Admin module renders page
    ↓
Page retrieves data from services
    ↓
HTML with forms/tables displayed
    ↓
Admin interacts (submit form, etc)
    ↓
Form handler processes in POST
    ↓
Database updated, redirect with message
```

---

## Summary

The Shuriken Reviews architecture emphasizes:

- **Modularity** - Each component has a clear, focused responsibility
- **Extensibility** - 50+ hooks for customization without modifying core
- **Testability** - Interfaces and dependency injection enable unit testing
- **Robustness** - Comprehensive exception handling and validation
- **Maintainability** - Clear structure makes code easy to understand and modify

This design allows the plugin to be both powerful for end users and developer-friendly for extensions and customization.

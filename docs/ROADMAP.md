# Shuriken Reviews Roadmap

Current Version: **1.10.0**

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

🚧 Next up:
- Server-side render pre-fetch (batch query for frontend pages)
- Statistics caching
- Rate limit performance caching

🚧 Later:
- Rating notes/comments
- Votes/notes management UI
- Alternative calendar display hook (Jalali/Shamsi)

🚧 Future:
- Email notifications
- Webhook integration

---

## 1.10.0 (Current)

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

**Problem Solved:**
- Each block instance was making 3 separate API calls fetching ALL ratings
- No shared state between block instances in FSE editor
- REST stats endpoint made N database queries for N ratings

#### Phase 1: Database Foundation ✅

| # | Task | Status |
|---|------|--------|
| 1 | Add batch database method `get_ratings_by_ids($ids)` | ✅ Done |
| 2 | Add search database method `search_ratings($term, $limit, $type)` | ✅ Done |
| 9 | Update database interface with new signatures | ✅ Done |
| - | *Bonus:* Add `get_child_ratings($parent_id)` for grouped blocks | ✅ Done |

#### Phase 2: REST API Improvements ✅

| # | Task | Status |
|---|------|--------|
| 3 | Add `/ratings/search` endpoint for AJAX autocomplete | ✅ Done |
| 4 | Optimize `/ratings/stats` to use batch query | ✅ Done |
| - | *Bonus:* Add `/ratings/{id}/children` endpoint | ✅ Done |

#### Phase 3: FSE Editor Optimization ✅

| # | Task | Status |
|---|------|--------|
| 5 | Create shared `@wordpress/data` store | ✅ Done |
| 6 | Convert rating dropdown to AJAX (search only when typing) | ✅ Done |
| 7 | Update grouped-rating block with same patterns | ✅ Done |

#### Phase 4: Server-side Optimization 🚧

| # | Task | Status |
|---|------|--------|
| 8 | Add server-side render pre-fetch | 🚧 Pending |

**Goal:** On frontend page render, collect all rating block IDs, execute single batch query, distribute data to blocks.

**Implementation checklist:**
- [ ] Hook into block render to collect rating IDs
- [ ] After all blocks collected, batch fetch via `get_ratings_by_ids()`
- [ ] Pass pre-fetched data to individual block renders
- [ ] Avoid duplicate queries on pages with many rating blocks

#### Phase 5: Validation ✅

| # | Task | Status |
|---|------|--------|
| 10 | Test and validate all optimizations | ✅ Done |
| - | *Bonus:* Add loading state feedback for data refresh | ✅ Done |

**UX Enhancement:** When cached data is refreshed on page load, ratings now show a subtle opacity fade and spinner indicator for better user feedback.

**Dependency Graph:**
```
[1] Batch DB method ──┬──► [4] Optimize stats endpoint ✅
                      │
                      └──► [8] Server-side pre-fetch 🚧

[2] Search DB method ────► [3] REST search endpoint ✅ ────► [6] AJAX dropdown ✅
                                                             │
                                                             └──► [7] Grouped block ✅

[5] Shared data store ✅ ──► [6] AJAX dropdown ✅
                             │
                             └──► [7] Grouped block ✅

[9] Update interface ✅ ────► (parallel with 1 & 2)
```

**Performance Improvements Achieved:**

| Scenario | Before | After |
|----------|--------|-------|
| 10 rating blocks in FSE | 30 API calls (all fetching ALL ratings) | 1 shared store + on-demand fetches |
| Rating dropdown with 1000 ratings | All 1000 loaded upfront | Search-as-you-type, max 20 results |
| Frontend stats for 50 ratings | 50 database queries | 1 batch query |
| Grouped block children | Not fetched on load | Fetched via dedicated endpoint |

**Files Changed:**
- `includes/class-shuriken-database.php` - Batch/search/children methods
- `includes/interfaces/interface-shuriken-database.php` - Interface updates
- `includes/class-shuriken-rest-api.php` - Search + children endpoints, optimized stats
- `includes/class-shuriken-block.php` - Shared store registration
- `blocks/shared/ratings-store.js` - Centralized data store with thunks
- `blocks/shuriken-rating/index.js` - Rewritten for shared store + AJAX search
- `blocks/shuriken-grouped-rating/index.js` - Updated for shared store + child fetching
- `assets/css/shuriken-reviews.css` - Loading state styles with opacity transition
- `assets/js/shuriken-reviews.js` - Refreshing state feedback on data fetch

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

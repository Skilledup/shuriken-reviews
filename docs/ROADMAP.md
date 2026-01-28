# Shuriken Reviews Roadmap

Current Version: **1.9.0**

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

🚧 Next up:
- Server-side render pre-fetch (batch query for frontend pages)
- Vote rate limiting
- Statistics caching

🚧 Later:
- Rating notes/comments
- Votes/notes management UI
- Alternative calendar display hook (Jalali/Shamsi)

🚧 Future:
- Email notifications
- Webhook integration

---

## 1.9.0 (Current)

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

### Vote Rate Limiting
Goal: prevent voting abuse/spam.

Planned:
- Cooldown between votes (per user)
- Daily/hourly vote limits (per user)
- Guest/IP-based limits

Implementation checklist:
- [ ] Track vote timestamps (user meta / post meta)
- [ ] Enforce limits inside AJAX voting handler
- [ ] Throw `Shuriken_Rate_Limit_Exception` when exceeded
- [ ] Map to HTTP 429 in exception handling
- [ ] Add settings to configure thresholds
- [ ] Add hook to bypass limits for trusted users

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

- GitHub Issues: https://github.com/qasedak/shuriken-reviews/issues
- Documentation index: [INDEX.md](INDEX.md)

---

## License

Licensed under [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

Developed by [Skilledup Hub](https://skilledup.ir)

---

Last Updated: January 2026

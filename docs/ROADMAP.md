# Shuriken Reviews Roadmap

This document is a high-level roadmap (what's done + what's next). For deep details, use:
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
- Rating types: stars, like/dislike, numeric, approval — backend + frontend shortcodes + CSS (1.12.0)
- Post Meta Box: link ratings to posts/pages with auto-injection & JSON-LD (1.12.0)
- Analytics type-safe aggregation + display — scale-aware helpers, type-branched admin pages, dynamic chart labels (1.12.1)
- Admin ratings redesign — form overhaul, screen options, numeric slider UI, backend protections (1.12.2)
- Exception system SPL refactor — interface + trait, SPL base classes (1.12.2)
- FSE blocks: type-aware editor preview + create/edit modal fields + block-helpers + keywords (1.12.3)
- Post Linked Ratings block — dynamic FSE block for site editor templates (1.12.4)
- PHP 8.1+ type hints — native property types, parameter types, return types across all classes (1.13.0)
- Magic number constants — hardcoded values extracted to named class constants (1.13.0)
- Vote normalization helper — shared `normalize_vote_value()` + `denormalize_average()` (1.13.0)
- Frontend JS modernization — `const`/`let`, strict mode, event cleanup via MutationObserver (1.13.0)
- Block store input validation — `isValidId()` guard in all store thunks (1.13.0)

🚧 In progress (1.13.x — code quality):
- **PHPUnit test suite** — add proper test configuration and unit tests (later)

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

🚧 Future:
- Email notifications
- Webhook integration

---

## 1.12.x

### Rating Types (1.12.0)

Four rating type modes with full-stack support from DB to frontend shortcodes.

**Types:**
- **Stars** (default) — Classic 1–N star rating, scale configurable 2–10
- **Like/Dislike** — Thumbs up/down binary vote; rating_value=1 (like) or 0 (dislike), total_rating stores like count
- **Numeric** — HTML5 range slider with live value display and submit button, scale 2–100
- **Approval** — Single upvote button, rating_value always 1
- **Mirror** — Shares vote data with a source rating; type and scale inherited from source, cannot be changed

**DB Changes:**
- New columns: `rating_type VARCHAR(20) DEFAULT 'stars'`, `scale TINYINT UNSIGNED DEFAULT 5`
- Binary types (like_dislike, approval) force scale=1; stars allow 2–10; numeric allows 2–100
- Vote validation allows 0 for dislike votes
- Mirrors inherit type/scale from source; type/scale locked when votes exist

**Stack (all done):**
- Database, REST API, AJAX, Shortcodes, Frontend JS, Frontend CSS, Admin UI
- FSE blocks: type-aware editor preview, create/edit modals, block-helpers, keywords
- Analytics & Admin: scale-aware inversion, type-branched display, dynamic chart labels, screen options
- Numeric slider: HTML5 range input, scale 2–100, `format_vote_display()` returns `X/N`

### Exception System SPL Refactor (1.12.2)

- `Shuriken_Exception_Interface extends Throwable` + `Shuriken_Exception_Trait` for shared error code logic
- Logic-family extends SPL counterparts (`LogicException`, `InvalidArgumentException`, `DomainException`)
- Runtime-family stays under `Shuriken_Exception` (`RuntimeException`)
- All catch blocks use `Shuriken_Exception_Interface`

### Post Linked Ratings Block (1.12.4)

Dynamic FSE block (`shuriken/post-linked-ratings`) for site editor templates.

- [x] New dynamic FSE block registered with `usesContext: ["postId", "postType"]`
- [x] Reads `_shuriken_rating_ids` from current post context
- [x] Server-side renders linked ratings (delegates to shortcode renderer via `do_shortcode`)
- [x] Placeholder in editor showing linked rating count
- [x] Alternative to `the_content` auto-injection — use block positioning in templates

### Post Meta Box (1.12.0)

Link ratings to posts/pages directly from the post editor.

- Meta box checkbox list, `the_content` injection (before/after/disabled), JSON-LD structured data
- Admin columns, REST API field, settings tab, extensible via filters

---

## 1.13.x — Code Quality & Standards

### PHP 8.1+ Native Type Hints (1.13.0)

All PHP classes — property types, parameter types, return types. All 17 classes + interfaces done.

### Magic Number Constants (1.13.0)

- `Shuriken_Database`: `RATING_SCALE_DEFAULT`, `SCALE_MIN`, `STARS_SCALE_MAX`, `NUMERIC_SCALE_MAX`, `RATINGS_PER_PAGE_DEFAULT`, `BATCH_IDS_MAX`, `SEARCH_LIMIT_MAX`
- `Shuriken_Rate_Limiter`: `COOLDOWN_DEFAULT`, `GUEST_HOURLY_LIMIT_DEFAULT`, `MEMBER_HOURLY_LIMIT_DEFAULT`, `GUEST_DAILY_LIMIT_DEFAULT`, `MEMBER_DAILY_LIMIT_DEFAULT`

### Vote Normalization Helper (1.13.0)

- `Shuriken_Database::normalize_vote_value(float, string, int): float` — validates + normalizes raw votes
- `Shuriken_Database::denormalize_average(float, int): float` — converts stored averages back to display scale

### Frontend JS Modernization (1.13.0)

- `shuriken-reviews.js`: all `var` → `const`/`let`, `'use strict'`, MutationObserver cleanup for `setInterval` on removed elements
- `ratings-store.js`: all `var` → `const`/`let`, `isValidId()` helper guards all thunks accepting `ratingId`/`parentId`

---

## 1.11.x

### Mirror Management in Block Editor

- Unified `ComboboxControl` searches parents + mirrors; mirror auto-decomposes into source + display override
- Mirror CRUD in modals (create, rename, delete), inline rename with Enter/Escape
- Batch mirror fetching via `GET /ratings/batch?ids=…` endpoint
- Graceful error recovery — failures don't propagate to UI

### 1.11.4 — Editor Request Optimization & CDN Compatibility

- Promise-level deduplication (`dedup()`) + automatic batch scheduling (`scheduleBatchFetch()`)
- Scoped authentication filter (only public endpoints bypass nonce)
- CDN-safe response headers (`Cache-Control: no-store`, `X-Content-Type-Options: nosniff`)
- Output buffer cleaning before JSON serialization

**Net effect:** 5 blocks → 3 API requests (was 15+)

### Shortcode Extensions

- `[shuriken_grouped_rating]` with `grid`/`list` layouts
- `style` parameter for presets (`card`, `dark`, `gradient`, `boxed`)
- `accent_color` / `star_color` parameters for CSS custom properties

---

## 1.10.x

### FSE Block Redesign — Style Presets v2 (1.10.3)

- WordPress Block Styles API (`styles` array in block.json) drives visual variants
- 5 presets per block; CSS custom properties (`--shuriken-user-accent`, `--shuriken-user-star-color`)
- Simplified inspector panels; live editor preview fix

### Vote Rate Limiting (1.10.0)

- Cooldown between votes, hourly & daily limits (separate for members/guests)
- Admin bypass by default; modern tabbed settings UI
- 5 filters + 2 actions for customization; disabled by default

---

## 1.9.x

### Voter Activity Page (1.9.1)

- Clickable voter names → full voting history (members + guests)
- Statistics, charts (Chart.js), CSV export, date range filtering, dark mode

### Data Retrieval Efficiency (1.9.0)

- Shared `@wordpress/data` store; AJAX search-as-you-type; batch DB queries
- `/ratings/search` and `/ratings/{id}/children` REST endpoints

---

## 1.7.5

- Split monolithic plugin file into focused modules
- DI container + service wiring; interfaces + mocks for testing
- Exception types + centralized handling
- Nonce fix for cached pages; star normalization fix
- Parent/Child Grouped Ratings Block with full CRUD

---

## Planned

### Rate Limit Performance Caching
- [ ] Cache vote counts in transients (per user/IP) with TTL
- [ ] Invalidate on new vote; filter to disable

### Statistics Caching
- [ ] Cache service in container; TTL-based analytics caching
- [ ] Invalidate on vote changes; optional Redis support

### Mirror Vote Tracking
- [ ] Mirror vs. original vote breakdown analytics
- [ ] Per-mirror stats, comparison view, charts, CSV export

### Rating Notes / Comments
- [ ] Notes table + CRUD; frontend UI; admin moderation; REST endpoints

### Votes & Notes Management
- [ ] Admin listing/search; bulk operations; exports; user "my activity" view

### Calendar Display Hook
- [ ] `shuriken_display_date` filter; route all dates through helper

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

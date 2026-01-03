# Shuriken Reviews Roadmap

Current Version: **1.7.5-beta1**

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

🚧 Next up:
- Vote rate limiting
- Statistics caching

🚧 Later:
- Rating notes/comments
- Votes/notes management UI
- Parent/child “grouped ratings” block
- Alternative calendar display hook (Jalali/Shamsi)

🚧 Future:
- Email notifications
- Webhook integration

---

## 1.7.5-beta1 (Current)

✅ Major refactor and stabilization
- Split the large main plugin file into focused modules
- Added DI container + service wiring
- Added interfaces and mock implementations for testing
- Added exception types + centralized handling
- Fixed nonce validation with cached pages (REST API nonce)
- Fixed star rating normalization behavior

---

## 1.8.0 (Planned)

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

---

## 1.9.0 (Planned)

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

### Parent/Child Grouped Ratings Block
Goal: one block showing a parent rating + its child ratings.

Implementation checklist:
- [ ] Register new block + editor UI
- [ ] Frontend rendering
- [ ] Styling for hierarchy

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

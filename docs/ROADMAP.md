# Shuriken Reviews Development Roadmap

Current Version: **1.7.5-beta1**

## Status Overview

| Category | Status | Details |
|----------|--------|---------|
| Core Rating System | ✅ Complete | All rating features implemented |
| Extensibility | ✅ Complete | 20 hooks (12 filters + 8 actions) |
| Testing Infrastructure | ✅ Complete | Interfaces and mocks ready |
| Exception System | ✅ Complete | 9 exception types with handlers |
| Dependency Injection | ✅ Complete | DI container with services |
| Rate Limiting | 🚧 Planned | Reserved for v1.8.0 |
| Caching Layer | 🚧 Planned | Reserved for v1.8.0+ |
| Email Notifications | 🚧 Planned | Reserved for v1.9.0+ |
| Webhook Integration | 🚧 Planned | Reserved for v1.9.0+ |

---

## Version 1.7.5-beta1 (Current)

### ✅ Major Refactoring Complete

**Modular Architecture**
- Split main plugin file (1,345 lines) into 8 focused modules
- Each module ~200 lines with single responsibility
- Improvements to code navigation and maintenance

**Extensibility System (20 Hooks)**

*Filters (12):*
- `shuriken_rating_data` - Modify rating before display
- `shuriken_rating_css_classes` - Add custom CSS classes
- `shuriken_rating_max_stars` - Change star count (with normalization)
- `shuriken_rating_star_symbol` - Use custom symbols (★, ♥, etc.)
- `shuriken_rating_html` - Modify complete HTML output
- `shuriken_allow_guest_voting` - Control guest voting
- `shuriken_can_submit_vote` - Custom voting permissions
- `shuriken_vote_response_data` - Modify AJAX response
- `shuriken_before_create_rating` - Modify data before insert
- `shuriken_before_update_rating` - Modify data before update
- `shuriken_localized_data` - Add custom JS data
- `shuriken_i18n_strings` - Customize translations

*Actions (8):*
- `shuriken_after_rating_stats` - Add content after stats
- `shuriken_before_submit_vote` - Before vote processing
- `shuriken_vote_created` - After new vote
- `shuriken_vote_updated` - After vote update
- `shuriken_after_submit_vote` - After vote processing
- `shuriken_rating_created` - After rating created
- `shuriken_rating_updated` - After rating updated
- `shuriken_before_delete_rating` - Before deletion
- `shuriken_rating_deleted` - After deletion

**Testing Infrastructure**
- `Shuriken_Database_Interface` - Database contract
- `Shuriken_Analytics_Interface` - Analytics contract
- `Mock_Shuriken_Database` - Mock implementation
- Example-based testing documentation

**Exception System (9 Types)**

Ready to use:
- `Shuriken_Exception` (base)
- `Shuriken_Database_Exception`
- `Shuriken_Validation_Exception`
- `Shuriken_Not_Found_Exception`
- `Shuriken_Permission_Exception`
- `Shuriken_Logic_Exception`
- `Shuriken_Configuration_Exception`

Reserved for future:
- `Shuriken_Rate_Limit_Exception`
- `Shuriken_Integration_Exception`

**Dependency Injection**
- Lightweight DI container
- Service registration with dependencies
- Constructor injection pattern
- Backward compatible helper functions

**Bug Fixes**
- Fixed nonce validation with cached pages (REST API nonce)
- Fixed star rating system (unified filter with normalization)

**Documentation**
- Complete hooks reference (859 lines)
- Dependency injection guide (475 lines)
- Exception system guide (567 lines)
- Testing utilities (176 lines)
- Architecture overview
- Software design improvements

---

## Version 1.8.0 (Planned)

### Vote Rate Limiting

**Objective:** Prevent voting abuse and spam

**Features:**
- User-level cooldown (prevent rapid re-voting)
- Daily vote limits per user
- Hourly vote limits per user
- IP-based limits for guests

**Implementation:**
- [ ] Track vote timestamps in user/post meta
- [ ] Add rate limit checking in AJAX handler
- [ ] Throw `Shuriken_Rate_Limit_Exception` on limits exceeded
- [ ] Update exception handler for 429 HTTP status
- [ ] Add settings page option to configure limits
- [ ] Add hook to bypass limits for trusted users

**Status:** Exception class reserved, feature pending

**Example Factory Methods:**
```php
Shuriken_Rate_Limit_Exception::voting_too_fast($retry_after, $limit);
Shuriken_Rate_Limit_Exception::daily_vote_limit($limit);
Shuriken_Rate_Limit_Exception::hourly_vote_limit($limit);
Shuriken_Rate_Limit_Exception::vote_cooldown($retry_after);
```

### Caching Optimization

**Objective:** Improve performance with statistics caching

**Features:**
- Cache rating statistics with TTL
- Automatic cache invalidation on vote
- Transient-based caching
- Cache warming on admin save

**Implementation:**
- [ ] Add cache service to container
- [ ] Implement cache getter/setter in Analytics
- [ ] Add cache invalidation hooks
- [ ] Performance testing and benchmarks
- [ ] Optional Redis support

---

## Version 1.9.0+ (Planned)

### Email Notifications

**Objective:** Notify users of rating activity

**Features:**
- Email admins on low ratings
- Email item owners when voted on
- Digest reports (daily/weekly)
- Customizable templates

**Status:** Reserved via `Shuriken_Integration_Exception::email_failed()`

### Webhook Integration

**Objective:** Send data to external services

**Features:**
- POST rating data to webhooks
- Handle delivery failures
- Retry logic
- Event filtering

**Status:** Reserved via `Shuriken_Integration_Exception::webhook_failed()`

### Advanced Caching

**Objective:** Further performance optimization

**Features:**
- Intelligent cache strategies
- Cache warming
- Distributed caching
- Cache versioning

**Status:** Reserved via `Shuriken_Integration_Exception::cache_failed()`

---

## Feature Status Matrix

### Core Rating System

| Feature | Status | Version | Notes |
|---------|--------|---------|-------|
| Basic ratings (1-5 stars) | ✅ | 1.0.0 | Foundational feature |
| Parent-child relationships | ✅ | 1.4.0 | Hierarchical ratings |
| Mirror ratings | ✅ | 1.4.0 | Vote synchronization |
| Display-only aggregate ratings | ✅ | 1.4.0 | Calculated ratings |
| Guest voting | ✅ | 1.3.0 | IP-tracked voting |
| Custom star count | ✅ | 1.7.5 | With auto-normalization |
| Custom star symbols | ✅ | 1.7.5 | Hearts, icons, emojis |

### Integration Options

| Feature | Status | Version | Notes |
|---------|--------|---------|-------|
| Gutenberg block | ✅ | 1.1.0 | FSE compatible |
| Shortcode | ✅ | 1.1.0 | Full shortcode API |
| REST API | ✅ | 1.6.0 | Complete endpoints |
| AJAX voting | ✅ | 1.0.0 | Smooth submissions |
| Cache bypass | ✅ | 1.6.0 | Nonce freshness |

### Developer Features

| Feature | Status | Version | Notes |
|---------|--------|---------|-------|
| WordPress hooks | ✅ | 1.7.0 | 20+ hooks |
| Interfaces | ✅ | 1.7.5 | Database, Analytics |
| Dependency injection | ✅ | 1.7.5 | Lightweight DI container |
| Exception system | ✅ | 1.7.5 | 9 exception types |
| Mock implementations | ✅ | 1.7.5 | Testing support |
| API documentation | ✅ | 1.7.5 | 2,500+ lines |

### Admin Features

| Feature | Status | Version | Notes |
|---------|--------|---------|-------|
| Rating management | ✅ | 1.0.0 | CRUD interface |
| Comments settings | ✅ | 1.2.0 | Comment filtering |
| Analytics dashboard | ✅ | 1.5.0 | Charts & stats |
| CSV export | ✅ | 1.5.0 | Data download |
| Settings page | ✅ | 1.7.0 | Configuration options |
| About page | ✅ | 1.5.8 | Quick start guide |

### User Features

| Feature | Status | Version | Notes |
|---------|--------|---------|-------|
| Vote submission | ✅ | 1.0.0 | Core feature |
| Vote update | ✅ | 1.0.0 | Change vote |
| Average calculation | ✅ | 1.0.0 | Auto-calculated |
| Vote counting | ✅ | 1.0.0 | Statistics |
| RTL support | ✅ | 1.0.0 | Right-to-left languages |
| Internationalization | ✅ | 1.0.0 | Translatable |
| Responsive design | ✅ | 1.0.0 | Mobile friendly |
| Accessibility | ✅ | 1.0.0 | Keyboard nav, screen reader |

### Planned Features

| Feature | Status | Version | Notes |
|---------|--------|---------|-------|
| Vote cooldown | 🚧 | 1.8.0 | Rate limiting |
| Daily vote limits | 🚧 | 1.8.0 | Rate limiting |
| Hourly vote limits | 🚧 | 1.8.0 | Rate limiting |
| API rate limiting | 🚧 | 1.8.0 | Rate limiting |
| Statistics caching | 🚧 | 1.8.0 | Performance |
| Email notifications | 🚧 | 1.9.0 | Notifications |
| Webhook integration | 🚧 | 1.9.0 | External services |
| Advanced caching | 🚧 | 1.9.0+ | Performance |

---

## Implementation Roadmap

### Q1 2026 (Current)
- ✅ Release v1.7.5-beta1 with major refactoring
- ✅ Complete documentation
- 🚧 Gather feedback from beta testers
- 🚧 Bug fixes and refinements

### Q2 2026 (Planned)
- Implement vote rate limiting (v1.8.0)
- Add caching layer
- Performance optimization
- Beta testing and feedback

### Q3 2026 (Planned)
- Implement email notifications (v1.9.0)
- Webhook integration
- Advanced features
- Stability improvements

### Q4 2026+ (Future)
- Additional integrations
- Extended plugin ecosystem
- Advanced analytics
- Enterprise features

---

## Backlog & Considerations

### Research Needed
- [ ] Optimal caching strategies for rating data
- [ ] Best practices for email template customization
- [ ] Webhook retry and delivery guarantees
- [ ] Rate limiting thresholds for different use cases

### Technical Debt
- None currently tracked (full v1.7.5 refactor addressed existing issues)

### Community Requests
- Custom voting messages/reactions
- A/B testing support
- Advanced filtering and sorting
- Export to multiple formats

### Breaking Changes
None planned. All future versions will maintain backward compatibility.

---

## Contributing

See [CONTRIBUTING.md](../CONTRIBUTING.md) (when available) for:
- Development setup
- Code standards
- Testing requirements
- PR process
- Feature request process

---

## Support

- **GitHub Issues:** [Report bugs or request features](https://github.com/qasedak/shuriken-reviews/issues)
- **Documentation:** [Complete guides and API reference](INDEX.md)
- **Examples:** [Code examples and use cases](guides/hooks-reference.md)

---

## Version Numbering

The plugin uses semantic versioning:

- **MAJOR** (1.x.0) - Breaking changes, major features, or significant refactoring
- **MINOR** (1.0.x) - New features, non-breaking additions
- **PATCH** (1.0.0-x) - Bug fixes, patches, beta versions

Examples:
- 1.0.0 - Initial release
- 1.5.0 - New analytics feature
- 1.6.2 - Bug fix
- 1.7.5-beta1 - Beta version

---

## License

Licensed under [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

Developed with ❤️ by [Skilledup Hub](https://skilledup.ir)

---

Last Updated: January 2026

# Shuriken Reviews Roadmap

This roadmap describes current priorities and future direction. For a detailed record of released changes, see the [Changelog](CHANGELOG.md).

## Status

**Current release:** 1.15.7 Shingetsu

Items in this document are not commitments or release-date estimates. `[ ]` marks work that is still pending; `[x]` marks work that has shipped.

## In Progress

- [ ] **PHPUnit test suite** — complete the test configuration and add unit coverage.

## Known Issues

- [ ] **Cross-context live totals** — a vote updates widgets in the same context, but copies in other contexts or in a global view may wait for a reload. Determine how to refresh these copies without applying one context's totals to another.
- [ ] **Contextual parent aggregates** — for contextual grouped blocks, sub-rating votes update the sub-ratings and their mirrors, but the contextual parent does not currently aggregate those votes.
- [ ] **FSE block preview** — preview rendering only works when no rating is selected.
- [ ] **Multiple icons for star ratings** — allow each star value to use a distinct icon. This is separate from emoji reactions.

## Planned

### Engagement and Analytics

- [ ] Mirror vote tracking — show mirror and original vote breakdowns, comparisons, and CSV exports.
- [ ] Engagement factor — add a configurable votes-to-views metric and optional frontend analytics badges.

### Content and Display

- [ ] Rating notes and comments — add storage, CRUD, moderation, frontend UI, and REST endpoints.
- [ ] Vote and activity management — provide admin search, bulk actions, exports, and a “my activity” view.
- [ ] Emoji reactions — introduce reactions as a separate feature from rating types.
- [ ] HTML embed code — add an embed REST endpoint and copyable block/admin UI.
- [ ] Comment-form review ratings — let authors submit a read-only score with a comment, distinct from per-comment voting.

### Internationalization

- [ ] Alternative calendar display hook — route dates through `shuriken_display_date` for calendars such as Jalali/Shamsi.
- [ ] Native multilingual support — improve compatibility with WPML and Polylang for names and descriptions.

## Backlog and Research

- [ ] Determine suitable rate-limiter defaults for common sites.
- [ ] Lazy-load the vote nonce on first interaction while preserving authentication and guest-voting behavior.
- [ ] Define webhook retry guarantees and failure handling.
- [ ] Establish an approach for customizable email templates.
- [ ] **Caching strategy for rating stats** — server-side TTL caching with `wp_cache_*` is implemented. Continue evaluating CDN and invalidation behavior.

## Shipped

### 1.15.7 Shingetsu

- [x] Grouped mirror totals now stay synchronized after a vote across stars, numeric, like/dislike, and approval ratings.

### 1.15.6 Shingetsu

- [x] Comment-level contextual voting for blocks and shortcodes.
- [x] Contextual vote analytics and comment editor integration.
- [x] SSR batch pre-fetch, statistics caching, smart client refresh, and transient rate-limit counters.
- [x] Database decomposition and REST controller separation.
- [x] Analytics service decomposition and shared admin/Chart.js utilities.
- [x] Third-party add-on hooks and an add-on development guide.
- [x] Conditional frontend asset loading and uninstall data controls.

### Earlier Releases

- [x] Contextual voting across posts, pages, products, and comments.
- [x] Per-post and per-comment block editor controls.
- [x] Rating and shortcode block feature parity.
- [x] Modern PHP architecture, dependency injection, and repository separation.
- [x] Rating backed enum and modernized JavaScript.
- [x] Archive sorting and contextual analytics surfaces.
- [x] Shortcode, block, and REST API support for rating label descriptions and hidden titles.

For release-by-release details, see [CHANGELOG.md](CHANGELOG.md).

## 2.0.0+

Potential future directions include:

- Admin notifications and digest emails for low ratings.
- Webhook delivery for rating events, with retries and failure handling.

These items require further design and are not scheduled.

## Breaking Changes

No breaking changes are planned. Backward compatibility remains a goal for upcoming releases.

## Supporting Links

- [Documentation index](INDEX.md)
- [Architecture overview](ARCHITECTURE.md)
- [GitHub issues](https://github.com/Skilledup/shuriken-reviews/issues)

## License

Licensed under [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html).

Developed by [Skilledup](https://skilledup.ir).

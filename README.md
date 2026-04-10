# Shuriken Reviews

A professional WordPress rating plugin built for flexibility, performance, and extensibility. Supports multiple rating types, per-post contextual voting, full Site Editor integration, a built-in analytics dashboard, and a rich developer API — all fully compatible with aggressive page caching and CDN delivery.

![Version](https://img.shields.io/badge/version-1.15.4-blue)
![License](https://img.shields.io/badge/license-GPL--3.0%2B-green)
![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-purple)

---

## Table of Contents

- [Overview](#overview)
- [Requirements](#requirements)
- [Installation](#installation)
- [Rating Types & Scales](#rating-types--scales)
- [Rating Structure](#rating-structure)
- [Block Editor Integration](#block-editor-integration)
- [Shortcodes](#shortcodes)
- [Contextual (Per-Post) Voting](#contextual-per-post-voting)
- [Rate Limiting](#rate-limiting)
- [Analytics Dashboard](#analytics-dashboard)
- [REST API](#rest-api)
- [Cache Compatibility](#cache-compatibility)
- [Settings Reference](#settings-reference)
- [Developer API](#developer-api)
- [Architecture](#architecture)
- [License](#license)

---

## Overview

Shuriken Reviews is designed around three core principles:

**Cache-proof by default.** Every page load fetches fresh vote statistics and a valid security nonce from the REST API. If a cached nonce is rejected during a vote submission, the plugin automatically retrieves a new one and retries — transparently, with no user-facing error. No cache exclusion rules or special CDN configuration are required.

**Type-aware and scale-aware.** Ratings can be Stars (1–N), Numeric sliders (configurable range), or Thumbs (Like/Dislike, Approval). All votes are normalised to an internal 1–5 scale for consistent cross-rating analytics, while each rating's own display scale is always preserved for presentation.

**Developer-first architecture.** Services are resolved through a dependency injection container and backed by interfaces, making them fully swappable for unit testing or custom implementations. Thirty-plus WordPress hooks cover every significant operation. Nine typed exception classes map directly to HTTP status codes.

---

## Requirements

| Dependency | Minimum Version |
|---|---|
| WordPress | 6.2 |
| PHP | 8.1 |

---

## Installation

1. Upload the `shuriken-reviews` directory to `/wp-content/plugins/`.
2. Activate through **Plugins → Installed Plugins**.
3. Navigate to **Shuriken Reviews → Ratings** to create your first rating.

The plugin creates its database tables on activation. No manual database setup is needed.

---

## Rating Types & Scales

| Type | Interaction | Configurable Scale |
|---|---|---|
| **Star** | Click or keyboard-select 1–N stars | Yes (default: 5) |
| **Numeric** | Drag a slider to any value in range | Yes (default: 10) |
| **Thumbs** | Single binary vote — Like/Dislike or Approval | No |

Votes are stored on an internal normalised 1–5 scale regardless of display scale, enabling consistent analytics and comparisons across ratings of different scales. The denormalised `display_average` (on the rating's own scale) is pre-computed by the database layer and available on every rating and stats object.

---

## Rating Structure

Ratings support hierarchical and linked relationships:

| Concept | Description |
|---|---|
| **Parent rating** | Aggregates values from sub-ratings; can be display-only (no direct votes) |
| **Sub-rating** | Contributes to a parent with a positive or negative effect |
| **Mirror rating** | Shares vote tallies with another rating; kept in sync automatically |
| **Display-only** | Calculated aggregate — accepts no direct votes |

Type compatibility is enforced: the block editor warns when incompatible types are linked as mirrors or parent/child pairs.

---

## Block Editor Integration

Two Full Site Editor blocks are included.

### Shuriken Rating

Displays a single interactive rating for user voting.

**Block sidebar options:**
- Searchable rating selector — pick an existing rating or create one without leaving the editor
- Title tag (h1–h6, div, p, span) and Anchor ID
- **Per-post voting** toggle for contextual mode
- Visual preset (Classic, Card, Minimal, Dark, Outlined)
- Per-block accent colour and star colour override

### Shuriken Grouped Rating

Displays a parent rating with all its child sub-ratings in a unified section.

**Block sidebar options:**
- Unified searchable dropdown for parent ratings and mirrors
- Title tag and Anchor ID
- **Per-post voting** toggle
- Visual preset (Gradient, Minimal, Boxed, Dark, Outlined)
- Grid or List layout for child ratings
- Gap control (`--shuriken-gap`) — accepts any CSS size value (e.g. `24px`, `2rem`)
- Per-block colour overrides, including a dedicated button colour for numeric grouped ratings
- **Inline mirror management** — create, rename, and delete mirrors for the parent and each sub-rating directly from the block editor

Both blocks render a live editor preview that matches the frontend output. They also opt into WordPress client-side navigation support, and the frontend ratings script re-initialises after Interactivity Router navigations so voting widgets stay live on modern block-theme page transitions.

---

## Shortcodes

### `[shuriken_rating]`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `id` | int | required | Rating ID |
| `tag` | string | `h2` | Title HTML tag: h1–h6, div, p, span |
| `anchor_tag` | string | — | Anchor ID for deep-linking |
| `style` | string | — | Preset: `classic`, `card`, `minimal`, `dark`, `outlined` |
| `accent_color` | string | — | Hex colour override (e.g. `#e74c3c`) |
| `star_color` | string | — | Hex colour override |
| `button_color` | string | — | Hex colour override for numeric submit buttons |
| `context_id` | int | — | Post ID for contextual voting |
| `context_type` | string | — | Post type for contextual voting |

```
[shuriken_rating id="1"]
[shuriken_rating id="1" tag="h3" anchor_tag="product-rating"]
[shuriken_rating id="1" style="card" accent_color="#e74c3c" star_color="#f39c12"]
[shuriken_rating id="8" style="minimal" star_color="#0f766e" button_color="#155e75"]
[shuriken_rating id="1" context_id="42" context_type="post"]
```

### `[shuriken_grouped_rating]`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `id` | int | required | Parent rating ID |
| `tag` | string | `h2` | Title HTML tag |
| `anchor_tag` | string | — | Anchor ID |
| `style` | string | — | Preset: `gradient`, `minimal`, `boxed`, `dark`, `outlined` |
| `accent_color` | string | — | Hex colour override |
| `star_color` | string | — | Hex colour override |
| `button_color` | string | — | Hex colour override for numeric submit buttons |
| `layout` | string | `grid` | Child layout: `grid` or `list` |
| `context_id` | int | — | Post ID for contextual voting |
| `context_type` | string | — | Post type for contextual voting |

```
[shuriken_grouped_rating id="1"]
[shuriken_grouped_rating id="1" style="dark" layout="list"]
[shuriken_grouped_rating id="5" tag="h3" style="boxed" accent_color="#667eea" button_color="#1d4ed8" layout="list"]
```

---

## Contextual (Per-Post) Voting

A rating placed in a post template collects **independent vote tallies per post** without requiring separate rating configurations for each post.

**How to enable:**
- Block: toggle **Per-post voting** in the block inspector.
- Shortcode: pass `context_id` and `context_type` attributes.

**Additional integration:**
- **Block editor sidebar panel** — while editing a post, a Document Settings panel displays live contextual vote stats for that post.
- **Archive sorting** — configure **Settings → General → Archive Sorting** to order archive pages by contextual rating average or total votes using a `pre_get_posts` hook.
- **Analytics** — the Recent Activity table links contextual votes to the originating post; an overview card counts posts with contextual votes.
- **Item Stats scope views** — ratings with contextual votes gain a **Per-Post Votes** view, a **Global Votes** comparison view, top-post and trending tables, and dedicated drill-down pages for each post context.
- **REST endpoint** — `GET /context-stats` (requires `edit_posts`) returns per-post stats programmatically.

Accepted post types default to `post`, `page`, and `product`. Extend with the `shuriken_allowed_context_types` filter.

---

## Rate Limiting

Disabled by default. Enable under **Settings → Rate Limiting**.

| Setting | Default | Description |
|---|---|---|
| Cooldown period | 60 s | Minimum delay between votes on the same rating per user |
| Hourly limit — members | 30 | Maximum votes per hour for logged-in users |
| Hourly limit — guests | 10 | Maximum votes per hour for guest users |
| Daily limit — members | 100 | Maximum votes per day for logged-in users |
| Daily limit — guests | 30 | Maximum votes per day for guest users |

Administrators automatically bypass all limits. Five dedicated hooks allow custom rate-limit logic to be injected.

---

## Analytics Dashboard

Available at **Shuriken Reviews → Analytics**.

| Section | Description |
|---|---|
| Overview cards | Total ratings, votes, averages, unique voters, posts with contextual votes |
| Date range filter | Last 7 / 30 / 90 / 365 days, all time, or a custom range |
| Top rated / most voted | Sortable leaderboards with vote-change percentages and benchmark comparisons |
| Vote distribution | Visual breakdown per rating-value bucket |
| Votes over time | Trend chart with rolling average |
| Item Stats | Scope-aware detail screens with Global vs. Per-Post toggles, contextual leaderboards, and per-post drilldowns |
| Voter breakdown | Member vs. guest voter-type split |
| Voter Activity page | Full voting history, per-rating stats, and charts for any individual voter (members and guests) |
| Export | Download all data as CSV |

---

## REST API

All endpoints are prefixed with `/wp-json/shuriken-reviews/v1/`.

### Ratings

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/ratings` | Public | List all ratings |
| GET | `/ratings/{id}` | Public | Get a single rating |
| POST | `/ratings` | Write cap | Create a rating |
| PUT | `/ratings/{id}` | Write cap | Update a rating |
| DELETE | `/ratings/{id}` | Write cap | Delete a rating |
| GET | `/ratings/search` | Public | Search ratings by name |
| GET | `/ratings/parents` | Public | List parent ratings |
| GET | `/ratings/mirrorable` | Public | List mirrorable ratings |
| GET | `/ratings/{id}/children` | Public | List child ratings |
| GET | `/ratings/{id}/mirrors` | Public | List mirrors of a rating |
| GET | `/ratings/batch?ids=1,2,3` | Public | Batch-fetch ratings by ID |

### Voting & Stats

| Method | Path | Auth | Description |
|---|---|---|---|
| GET | `/ratings/stats?ids=1,2,3` | Public | Fresh vote statistics; supports `context_id` and `context_type` |
| GET | `/nonce` | Public | Fresh AJAX nonce (CDN-safe) |
| GET | `/context-stats` | `edit_posts` | Per-post contextual vote statistics |

### Write Capability

The minimum WordPress capability for POST / PUT / DELETE defaults to `manage_options` (Administrators). Configure it in **Settings → General → REST API Access**, or override it at runtime:

```php
add_filter( 'shuriken_rest_manage_capability', function( $cap ) {
    return 'edit_posts'; // Allow Editors and Authors
} );
```

---

## Cache Compatibility

Shuriken Reviews is fully compatible with full-page caching and CDNs without any cache-rule configuration:

1. On every page load, the plugin's JavaScript fetches fresh vote statistics and a valid nonce from the REST API.
2. If a vote is submitted with a stale nonce (e.g. from a cached page), the plugin automatically fetches a fresh nonce and retries the request — the user sees no error.
3. The `/nonce` endpoint can itself be served cached; the retry mechanism handles any edge cases.

---

## Settings Reference

### General Tab

| Setting | Description |
|---|---|
| Guest Voting | Allow non-logged-in users to vote; votes are tracked by IP address |
| REST API Access | Minimum capability for write operations: Administrator, Editor, Author, or Custom |
| Archive Sorting | Order archive pages by contextual rating — choose a rating, sort by average or total votes |

### Rate Limiting Tab

Enable rate limiting and configure all thresholds. See [Rate Limiting](#rate-limiting).

### Comments Tab

| Setting | Description |
|---|---|
| Exclude Author Comments | Remove the post author's own comments from the Latest Comments block |
| Exclude Reply Comments | Remove comment replies from the Latest Comments block |

---

## Developer API

### WordPress Hooks

30+ hooks (19 filters + 11 actions). Key hooks:

| Hook | Type | Description |
|---|---|---|
| `shuriken_rating_html` | Filter | Modify the full rendered rating HTML |
| `shuriken_can_submit_vote` | Filter | Control vote eligibility per user/rating |
| `shuriken_rating_star_symbol` | Filter | Override the rating symbol (★, ❤, etc.) |
| `shuriken_rest_manage_capability` | Filter | Override write capability for REST write operations |
| `shuriken_allowed_context_types` | Filter | Control which post types accept contextual votes |
| `shuriken_rate_limit_exceeded` | Filter | Customise the rate-limit error response |
| `shuriken_vote_created` | Action | Fires after a new vote is successfully recorded |
| `shuriken_after_rating_stats` | Action | Append content below the rating stats block |
| `shuriken_settings_tabs` | Filter | Add or modify Settings page tabs |

→ [Full Hooks Reference](docs/guides/hooks-reference.md)

### Helper Functions

```php
shuriken_db()         // Returns Shuriken_Database_Interface
shuriken_analytics()  // Returns Shuriken_Analytics_Interface
shuriken_container()  // Returns Shuriken_Container (DI service container)
```

### Service Interfaces

| Interface | Covers |
|---|---|
| `Shuriken_Database_Interface` | All read/write rating and vote operations |
| `Shuriken_Analytics_Interface` | Aggregate statistics, trends, and breakdowns |
| `Shuriken_Voter_Analytics_Interface` | Per-voter history and distribution |
| `Shuriken_Rate_Limiter_Interface` | Pluggable rate-limit implementation |

Mock implementations are provided in `tests/` for all major interfaces, enabling unit testing with no database dependency.

→ [Testing Guide](docs/guides/testing.md) · [Dependency Injection](docs/guides/dependency-injection.md)

### Exception System

Nine typed exception classes each map to an HTTP status code and implement a shared `Shuriken_Exception_Interface`:

| Class | HTTP | Use |
|---|---|---|
| `Shuriken_Database_Exception` | 500 | Database query failures |
| `Shuriken_Validation_Exception` | 422 | Input validation errors |
| `Shuriken_Not_Found_Exception` | 404 | Resource not found |
| `Shuriken_Permission_Exception` | 403 | Capability or auth failures |
| `Shuriken_Rate_Limit_Exception` | 429 | Rate limit exceeded |
| `Shuriken_Logic_Exception` | 422 | Domain-rule violations |
| `Shuriken_Configuration_Exception` | 500 | Misconfiguration |
| `Shuriken_Integration_Exception` | 502 | Third-party integration errors |

All exceptions are caught and logged by `Shuriken_Exception_Handler`.

→ [Exception System Guide](docs/guides/exception-handling.md)

---

## Architecture

All services are resolved through `Shuriken_Container`. Any service can be replaced with an alternative implementation (including mocks) by binding a new factory before the container resolves it.

Internal votes are stored on a normalised 1–5 scale (`RATING_SCALE_DEFAULT`). Denormalisation to each rating's display scale is performed exclusively inside `Shuriken_Database::attach_averages()` and exposed as `display_average` on all returned objects — no consumer performs inline scale conversion.

→ [Architecture Overview](docs/ARCHITECTURE.md)

---

## License

Licensed under [GPLv3 or later](https://www.gnu.org/licenses/gpl-3.0.html).

Developed by [Skilledup](https://skilledup.ir).

---

## Support

- [GitHub Discussions](https://github.com/Skilledup/shuriken-reviews/discussions) — bug reports and feature requests
- [Changelog](docs/CHANGELOG.md) — full version history
- [Developer Documentation](docs/INDEX.md) — all guides and API references



# Shuriken Reviews Roadmap

What's planned and why. For deep details, see:
- Hooks/API: [guides/hooks-reference.md](guides/hooks-reference.md)
- Architecture: [ARCHITECTURE.md](ARCHITECTURE.md)
- Guides: [guides/dependency-injection.md](guides/dependency-injection.md), [guides/exception-handling.md](guides/exception-handling.md), [guides/testing.md](guides/testing.md)

---

## In Progress

### 1.13.x — Code Quality
- [ ] **PHPUnit test suite** — proper test configuration and unit tests

---

## Shipped

### Contextual Voting (DB v1.6.0)

Ratings are created once and reused across any number of posts. Votes are scoped per post via `context_id` / `context_type` columns on the votes table — one grouped rating with all its sub-ratings serves every post in a template; each post gets its own independent vote tallies.

- `context_id` (BIGINT) + `context_type` (VARCHAR 50) columns on `wp_shuriken_votes`
- Unique key updated to `(rating_id, user_id, user_ip, context_id, context_type)` via COALESCE for NULL safety
- `get_contextual_stats()` / `get_contextual_stats_batch()` methods on the Database service
- `GET /ratings/stats` REST endpoint accepts optional `context_id` + `context_type`
- Both single and grouped rating blocks expose a **"Per-post voting"** toggle (`postContext` attribute)
- Block `usesContext: ["postId", "postType"]` — PHP render reads FSE context and passes it through the entire stack
- Frontend JS groups on-page ratings by context and makes batched stats requests per group
- `shuriken_allowed_context_types` filter controls which post types are accepted (default: `post`, `page`, `product`)
- Post Linked Ratings block removed — superseded by `postContext` mode
- Content injection disabled by default — superseded by per-post contextual blocks

---

## Up Next

### 1.14.x — Modern PHP & Architecture

#### `RatingType` Backed Enum

Replace the raw `string` rating type (`'stars'`, `'like_dislike'`, `'numeric'`, `'approval'`) with a PHP 8.1 backed enum.

- `enum RatingType: string` with cases `Stars`, `LikeDislike`, `Numeric`, `Approval`
- `isBinary(): bool` method absorbs the repeated `if ($type === 'like_dislike' || $type === 'approval')` guards in `Shuriken_Database` and `Shuriken_REST_API`
- `maxScale(): int` absorbs scale-constraint logic in `create_rating()` / `update_rating()`
- `get_type_class()` in `Shuriken_REST_API` is deleted — replaced by `RatingType::from($type)->isBinary()`
- DB-compatible: serialises to/from the existing `VARCHAR(20)` column via `->value` / `RatingType::from()`
- All `$allowed_types = array(...)` validation guards replaced by `RatingType::tryFrom()`

#### `readonly` Properties + Constructor Property Promotion

All injected dependencies and immutable table/config values are set once and never mutated. Apply PHP 8.1 language features throughout.

- `readonly` on: `$ratings_table`, `$votes_table`, `$wpdb` in `Shuriken_Database`; all `$db` / `$analytics` / `$rate_limiter` injection fields in every class
- Constructor property promotion on classes that accept injected dependencies: `Shuriken_REST_API`, `Shuriken_Admin`, `Shuriken_Shortcodes`, `Shuriken_Block`, `Shuriken_AJAX`, `Shuriken_Frontend`, `Shuriken_Analytics`
- Removes roughly 2–4 redundant lines per class (explicit property declaration + manual `$this->x = $x` assignment)

#### First-Class Callables for Hooks

Replace all `array($this, 'method_name')` callback syntax in `add_action` / `add_filter` calls.

- `add_action('rest_api_init', array($this, 'register_routes'))` → `add_action('rest_api_init', $this->register_routes(...))`
- Applies across `Shuriken_Admin`, `Shuriken_REST_API`, `Shuriken_Frontend`, `Shuriken_Block`, `Shuriken_AJAX`, `Shuriken_Rate_Limiter`
- Statically analysable — IDEs and PHPStan can follow the callable to the method without string resolution

#### `Shuriken_Database` Repository Decomposition

The ~1,250-line class covers four distinct responsibilities. Split into focused classes while keeping the existing interface contract.

| New class | Responsibility |
|---|---|
| `Shuriken_Rating_Repository` | Rating CRUD, search, pagination, mirror resolution |
| `Shuriken_Vote_Repository` | Vote insert/update, rate-limit timestamp queries |
| `Shuriken_Schema_Manager` | `create_tables()`, column migrations |
| `Shuriken_Database` (façade) | DI wiring, shared table name resolution, delegates to repositories |

- `Shuriken_Database_Interface` splits into `Shuriken_Rating_Repository_Interface` + `Shuriken_Vote_Repository_Interface`, or the façade keeps implementing the current interface for backward compatibility
- No public API or hook changes — callers use `shuriken_db()` as before

#### `Shuriken_REST_API` Controller Split

The ~900-line class mixes route registration, arg schemas, permission callbacks, and 15+ handler methods. Refactor following the WordPress `WP_REST_Controller` pattern.

| New class | Responsibility |
|---|---|
| `Shuriken_REST_Ratings_Controller` | Rating CRUD handlers (`get_ratings`, `create_rating`, `update_rating`, `delete_rating`, `get_single_rating`, `get_rating_mirrors`, `search_ratings`, `get_ratings_batch`) |
| `Shuriken_REST_Votes_Controller` | Vote handler, stats handler, nonce endpoint |
| `Shuriken_REST_Router` | Route registration, arg definitions, permission callbacks |

- Each controller extends `WP_REST_Controller` or holds its own `register_routes()`
- Arg definition arrays (`get_rating_id_args()`, `get_rating_create_args()`, etc.) move to the controller they belong to
- `Shuriken_REST_API` becomes a thin bootstrap that instantiates the three controllers

#### Performance

- [ ] Server-side render pre-fetch — batch query on frontend page load to avoid per-block queries
- [ ] Statistics caching — TTL-based cache service in container; invalidate on vote change; optional Redis support
- [ ] Rate limit performance caching — cache vote counts in transients per user/IP with TTL; invalidate on new vote

---

## Later

### Engagement & Analytics
- [ ] Mirror vote tracking — mirror vs. original vote breakdown, per-mirror stats, comparison view, CSV export

### Content Features
- [ ] Rating notes/comments — notes table + CRUD; frontend UI; admin moderation; REST endpoints
- [ ] Votes & notes management — admin listing/search; bulk operations; exports; "my activity" view for users
- [ ] Emoji reactions — separate system from rating types

### Admin & Editor
- [ ] Block editor sidebar — show contextual rating info in post sidebar
- [ ] Archive injection — `pre_get_posts` sorting by rating

### Internationalization
- [ ] Alternative calendar display hook — `shuriken_display_date` filter; route all dates through helper (Jalali/Shamsi)

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

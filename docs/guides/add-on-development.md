# Add-on Development Guide

How to extend Shuriken Reviews without modifying core plugin files. Add-ons are separate WordPress plugins (or mu-plugins) that hook into Shuriken's public APIs.

For a complete hook catalog, see [Hooks Reference](hooks-reference.md). For service overrides, see [Dependency Injection](dependency-injection.md).

---

## Before You Start

**Prefer hooks over forks.** Shuriken exposes 50+ filters and actions across PHP, REST, AJAX, admin UI, block editor JS, and frontend JS. If a hook exists for your use case, use it.

**Check the container is ready.** Most PHP integration runs after Shuriken loads. Hook early, integrate late:

```php
add_action('plugins_loaded', function () {
    if (!function_exists('shuriken_container')) {
        return; // Shuriken not active
    }

    // Register hooks, REST routes, admin pages, etc.
}, 20);
```

**Override services only when necessary.** `shuriken_container_ready` fires after core services are built. Use it to swap implementations (e.g. a custom analytics decorator), not for everyday feature work.

---

## Add-on Plugin Skeleton

```php
<?php
/**
 * Plugin Name: My Shuriken Add-on
 * Requires Plugins: shuriken-reviews
 */

defined('ABSPATH') || exit;

define('MY_SHURIKEN_ADDON_VERSION', '1.0.0');

final class My_Shuriken_Addon {
    public static function init(): void {
        add_action('plugins_loaded', [self::class, 'boot'], 20);
    }

    public static function boot(): void {
        if (!function_exists('shuriken_ratings_repo')) {
            return;
        }

        add_action('shuriken_container_ready', [self::class, 'on_container_ready']);
        add_filter('shuriken_block_view_data', [self::class, 'filter_block_view_data'], 10, 2);
        add_action('shuriken_vote_created', [self::class, 'on_vote_created'], 10, 8);
    }

    public static function on_container_ready(Shuriken_Container $container): void {
        // Optional: swap a container service
    }

    public static function filter_block_view_data(array $data, WP_Block $block): array {
        $data['my_addon_enabled'] = true;
        return $data;
    }

    public static function on_vote_created(
        int $rating_id,
        $rating_value,
        $normalized_value,
        int $user_id,
        string $user_ip,
        object $rating,
        int $max_stars,
        ?int $context_id,
        ?string $context_type
    ): void {
        // React to a new vote
    }
}

My_Shuriken_Addon::init();
```

---

## Integration Surfaces

### PHP — Display & Data

| Goal | Hook | Layer |
|------|------|-------|
| Change rating HTML | `shuriken_rating_html`, symbol filters | Render |
| Control who can vote | `shuriken_can_vote`, `shuriken_allow_guest_voting` | AJAX |
| Modify vote AJAX response | `shuriken_vote_response_data` | AJAX |
| React to votes | `shuriken_vote_created`, `shuriken_vote_updated` | AJAX |
| Decorate analytics output | `shuriken_overall_stats`, `shuriken_top_rated`, etc. | Analytics |
| Register custom AJAX | `shuriken_ajax_register_handlers` | AJAX |

**Example — append data to vote response:**

```php
add_filter('shuriken_vote_response_data', function ($data, $rating_id, $rating_value, $is_update, $rating, $max_stars) {
    $data['my_addon_badge'] = 'verified';
    return $data;
}, 10, 6);
```

### PHP — Admin UI

| Goal | Hook |
|------|------|
| Add submenu page | `shuriken_admin_submenu` |
| Extra ratings list column | `shuriken_ratings_columns` |
| Content after analytics | `shuriken_after_analytics_overview` |
| Custom settings tab save | `shuriken_save_settings` |
| Sidebar on a settings tab | `shuriken_settings_sidebar_{tab}` |

**Example — admin submenu:**

```php
add_action('shuriken_admin_submenu', function () {
    add_submenu_page(
        'shuriken-reviews',
        'My Add-on',
        'My Add-on',
        'manage_options',
        'my-shuriken-addon',
        'my_addon_admin_page'
    );
});
```

### REST API

Register routes on the Shuriken namespace:

```php
add_action('shuriken_rest_register_routes', function (string $namespace) {
    register_rest_route($namespace, '/my-addon/summary', [
        'methods'             => 'GET',
        'callback'            => 'my_addon_rest_summary',
        'permission_callback' => function () {
            return current_user_can('edit_posts');
        },
    ]);
});
```

Decorate public stats responses:

```php
add_filter('shuriken_rating_stats_response', function ($stats, $ids, $context_id, $context_type) {
    foreach ($stats as $id => &$row) {
        $row['my_addon_engagement'] = my_addon_compute_engagement((int) $id);
    }
    return $stats;
}, 10, 4);
```

See [REST API Reference](rest-api.md) for endpoint shapes.

### Block Editor (JavaScript)

Block editor scripts use `wp.hooks` filters registered at build time:

| Hook | Purpose |
|------|---------|
| `shurikenBlockSettings_rating` | Modify single-rating block settings |
| `shurikenBlockSettings_groupedRating` | Modify grouped-rating block settings |

Enqueue a script that depends on the block handle and register a filter:

```js
wp.hooks.addFilter(
    'shurikenBlockSettings_rating',
    'my-addon/extra-inspector',
    (settings) => {
        // Return modified block settings object
        return settings;
    }
);
```

See [Error Handling in FSE Blocks](error-handling-blocks.md) for block editor patterns.

### Frontend (JavaScript)

Frontend voting uses `shuriken-reviews.js` with optional `wp.hooks`:

| Hook | Type | Purpose |
|------|------|---------|
| `shurikenVoteRequest` | Filter | Modify AJAX payload before submit |
| `shurikenVoteSuccess` | Action | React after successful vote |
| `shurikenBlockViewData` | Filter | Modify per-block view config on init |

**Per-block view data (PHP → JS pipeline):**

1. PHP: filter `shuriken_block_view_data` during block render.
2. Shuriken localizes a consolidated `shurikenBlockViewData` map (keyed by rating ID).
3. JS: filter `shurikenBlockViewData` when each widget initializes.

```php
add_filter('shuriken_block_view_data', function (array $data, WP_Block $block): array {
    $data['show_confetti'] = true;
    return $data;
}, 10, 2);
```

```js
wp.hooks.addFilter('shurikenBlockViewData', 'my-addon/confetti', (data, ratingId, $rating) => {
    if (data?.show_confetti) {
        // $rating is a jQuery wrapper; data is also on $rating.data('shuriken-block-view')
    }
    return data;
});
```

**Example — modify vote payload:**

```js
wp.hooks.addFilter('shurikenVoteRequest', 'my-addon/extra-field', (postData, $rating, value) => {
    postData.my_addon_source = 'homepage';
    return postData;
});
```

Handle the extra field in PHP via existing vote filters or a custom `shuriken_ajax_register_handlers` endpoint.

---

## Service Access

Use helper functions rather than instantiating core classes:

```php
$rating = shuriken_ratings_repo()->get_rating(42);
$stats  = shuriken_analytics()->get_rating_stats(42);
$votes  = shuriken_votes_repo();
```

Repository list: [Helper Functions](helper-functions.md).

**Swap a service (advanced):**

```php
add_action('shuriken_container_ready', function (Shuriken_Container $container) {
    $container->set('analytics', new My_Custom_Analytics(
        shuriken_ratings_repo(),
        shuriken_votes_repo()
    ));
});
```

Your replacement should implement `Shuriken_Analytics_Interface` (or the relevant sub-interface).

---

## Caching & Frontend Behavior

Shuriken pages are often full-page cached (WP Rocket, host edge cache, etc.). Design add-ons with this in mind:

| Data | Source on cached pages |
|------|------------------------|
| Vote nonce | Always refreshed via `GET /shuriken-reviews/v1/nonce` on load |
| Rating stats | SSR HTML when fresh; REST refresh when `ssr_rendered_at` is stale |
| Block view config | Baked into cached HTML at cache-write time |
| Vote submission | `admin-ajax.php` (never cached) — uses fresh nonce |

**Implications for add-ons:**

- Do not rely on the embedded `shurikenReviews.nonce` being current — the plugin refreshes it automatically.
- Dynamic per-user UI must load after page render (AJAX/REST), not only via PHP-localized data.
- Per-block view data from `shuriken_block_view_data` is static for the lifetime of a cache entry — suitable for config, not live stats.

Tune the SSR freshness window if needed:

```php
add_filter('shuriken_ssr_fresh_threshold', fn () => 60); // seconds
```

---

## Analytics Extensions

Implement `Shuriken_Analytics_Extension_Interface` for custom stats bundles:

```php
class My_Analytics_Extension implements Shuriken_Analytics_Extension_Interface {
    public function get_extra_stats(string|int|array $date_range = 'all'): array {
        return [
            'referrer_breakdown' => $this->query_referrers($date_range),
        ];
    }
}
```

Wire it in via container override or by filtering `shuriken_overall_stats` for lighter touches.

---

## Lifecycle Hooks

| Hook | When |
|------|------|
| `shuriken_container_ready` | After DI container and core services are initialized |
| `shuriken_deactivate` | Shuriken plugin deactivation |
| `shuriken_uninstall` | Shuriken uninstall (respect opt-in data deletion) |

---

## Testing

- **Unit tests:** Mock repositories via interfaces — see [Testing Guide](testing.md).
- **Vote flow:** Test with a stale nonce (cached HTML snapshot) to confirm retry logic.
- **REST:** Public endpoints `/nonce` and `/ratings/stats` must stay uncached at the CDN layer.

---

## Checklist

1. Guard on `function_exists('shuriken_container')` or `shuriken_ratings_repo`.
2. Use hooks from [Hooks Reference](hooks-reference.md) — avoid editing Shuriken core.
3. Keep dynamic data off cached HTML; fetch via REST/AJAX when needed.
4. For block features: PHP `shuriken_block_view_data` + JS `shurikenBlockViewData`.
5. For editor features: `shurikenBlockSettings_*` filters.
6. For server logic: vote actions/filters or `shuriken_rest_register_routes`.
7. Document your add-on's hooks and settings for site owners.

---

## Related Docs

- [Hooks Reference](hooks-reference.md) — full hook catalog
- [REST API Reference](rest-api.md)
- [Dependency Injection](dependency-injection.md)
- [Helper Functions](helper-functions.md)
- [Architecture Overview](../ARCHITECTURE.md)

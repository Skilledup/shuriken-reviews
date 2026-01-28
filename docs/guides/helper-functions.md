# Helper Functions Reference

Shuriken Reviews provides global helper functions for easy access to plugin services and components. These functions follow WordPress conventions and provide a clean API for interacting with the plugin.

---

## Overview

Helper functions are global PHP functions that provide convenient access to singleton instances and services. They're designed to:

- Simplify access to plugin functionality
- Provide backward compatibility
- Support the dependency injection container
- Make code more readable and maintainable

---

## Core Helper Functions

### `shuriken_reviews()`

Get the main plugin instance.

```php
function shuriken_reviews(): Shuriken_Reviews
```

**Returns:** `Shuriken_Reviews` - The main plugin singleton instance.

**Usage:**
```php
// Access the main plugin instance
$plugin = shuriken_reviews();

// Check plugin version
$version = SHURIKEN_REVIEWS_VERSION;
```

**File:** [shuriken-reviews.php](../../shuriken-reviews.php)

---

### `shuriken_container()`

Get the dependency injection container instance.

```php
function shuriken_container(): Shuriken_Container
```

**Returns:** `Shuriken_Container` - The DI container singleton.

**Usage:**
```php
// Get the container
$container = shuriken_container();

// Register a custom service
$container->register('my_service', function($c) {
    return new My_Custom_Service();
});

// Retrieve a service
$service = $container->get('my_service');
```

**File:** [class-shuriken-container.php](../../includes/class-shuriken-container.php)

**See Also:** [Dependency Injection Guide](dependency-injection.md)

---

## Service Helper Functions

These functions provide quick access to registered services via the DI container.

### `shuriken_db()`

Get the database service for all database operations.

```php
function shuriken_db(): Shuriken_Database_Interface
```

**Returns:** `Shuriken_Database_Interface` - The database service.

**Usage:**
```php
// Get all ratings
$ratings = shuriken_db()->get_all_ratings();

// Get a single rating
$rating = shuriken_db()->get_rating(1);

// Get rating statistics
$stats = shuriken_db()->get_rating_stats(1);

// Create a new rating
$new_id = shuriken_db()->create_rating('My Rating', null, 'positive', false, null);

// Record a vote
shuriken_db()->record_vote(1, 5, $user_id, $ip_address);

// Get child ratings
$children = shuriken_db()->get_child_ratings($parent_id);

// Search ratings
$results = shuriken_db()->search_ratings('quality', 10, 'all');
```

**File:** [class-shuriken-container.php](../../includes/class-shuriken-container.php)

**Interface:** [interface-shuriken-database.php](../../includes/interfaces/interface-shuriken-database.php)

---

### `shuriken_analytics()`

Get the analytics service for statistics and reporting.

```php
function shuriken_analytics(): Shuriken_Analytics_Interface
```

**Returns:** `Shuriken_Analytics_Interface` - The analytics service.

**Usage:**
```php
// Get analytics instance
$analytics = shuriken_analytics();

// Get trending ratings
$trending = $analytics->get_trending_ratings(7); // Last 7 days

// Get vote distribution
$distribution = $analytics->get_vote_distribution($rating_id);

// Get daily stats
$daily = $analytics->get_daily_stats($rating_id, '2024-01-01', '2024-01-31');
```

**File:** [class-shuriken-container.php](../../includes/class-shuriken-container.php)

**Interface:** [interface-shuriken-analytics.php](../../includes/interfaces/interface-shuriken-analytics.php)

---

## Component Helper Functions

These functions provide access to specific plugin components.

### `shuriken_admin()`

Get the admin component instance.

```php
function shuriken_admin(): Shuriken_Admin
```

**Returns:** `Shuriken_Admin` - The admin component singleton.

**Usage:**
```php
// Access admin functionality
$admin = shuriken_admin();
```

**File:** [class-shuriken-admin.php](../../includes/class-shuriken-admin.php)

---

### `shuriken_ajax()`

Get the AJAX handler instance.

```php
function shuriken_ajax(): Shuriken_Ajax
```

**Returns:** `Shuriken_Ajax` - The AJAX handler singleton.

**Usage:**
```php
// Access AJAX handler
$ajax = shuriken_ajax();
```

**File:** [class-shuriken-ajax.php](../../includes/class-shuriken-ajax.php)

---

### `shuriken_block()`

Get the block editor component instance.

```php
function shuriken_block(): Shuriken_Block
```

**Returns:** `Shuriken_Block` - The block component singleton.

**Usage:**
```php
// Access block functionality
$block = shuriken_block();
```

**File:** [class-shuriken-block.php](../../includes/class-shuriken-block.php)

---

### `shuriken_frontend()`

Get the frontend component instance.

```php
function shuriken_frontend(): Shuriken_Frontend
```

**Returns:** `Shuriken_Frontend` - The frontend component singleton.

**Usage:**
```php
// Access frontend functionality
$frontend = shuriken_frontend();
```

**File:** [class-shuriken-frontend.php](../../includes/class-shuriken-frontend.php)

---

### `shuriken_shortcodes()`

Get the shortcodes component instance.

```php
function shuriken_shortcodes(): Shuriken_Shortcodes
```

**Returns:** `Shuriken_Shortcodes` - The shortcodes component singleton.

**Usage:**
```php
// Access shortcode functionality
$shortcodes = shuriken_shortcodes();
```

**File:** [class-shuriken-shortcodes.php](../../includes/class-shuriken-shortcodes.php)

---

### `shuriken_rest_api()`

Get the REST API component instance.

```php
function shuriken_rest_api(): Shuriken_REST_API
```

**Returns:** `Shuriken_REST_API` - The REST API component singleton.

**Usage:**
```php
// Access REST API functionality
$rest_api = shuriken_rest_api();
```

**File:** [class-shuriken-rest-api.php](../../includes/class-shuriken-rest-api.php)

**See Also:** [REST API Reference](rest-api.md)

---

## Common Usage Patterns

### Working with Ratings

```php
// Get database service
$db = shuriken_db();

// Create a parent rating
$parent_id = $db->create_rating('Overall Quality', null, 'positive', false, null);

// Create child ratings
$db->create_rating('Design', $parent_id, 'positive', false, null);
$db->create_rating('Performance', $parent_id, 'positive', false, null);
$db->create_rating('Value', $parent_id, 'positive', false, null);

// Get all child ratings
$children = $db->get_child_ratings($parent_id);

// Get aggregated parent stats
$parent = $db->get_rating($parent_id);
echo "Average: " . $parent->average;
```

### Recording Votes

```php
$db = shuriken_db();

// Record a vote
$rating_id = 1;
$score = 4;
$user_id = get_current_user_id();
$ip_address = $_SERVER['REMOTE_ADDR'];

$result = $db->record_vote($rating_id, $score, $user_id, $ip_address);

if ($result) {
    // Recalculate parent if needed
    $rating = $db->get_rating($rating_id);
    if ($rating->parent_id) {
        $db->recalculate_parent_rating($rating->parent_id);
    }
}
```

### Checking User Votes

```php
$db = shuriken_db();

$user_id = get_current_user_id();
$rating_id = 1;

// Check if user has already voted
$existing_vote = $db->get_user_vote($rating_id, $user_id);

if ($existing_vote) {
    echo "You already voted: " . $existing_vote->score;
} else {
    echo "You haven't voted yet!";
}
```

### Getting Analytics Data

```php
$analytics = shuriken_analytics();

// Get top-rated items
$top_ratings = $analytics->get_top_ratings(10);

// Get recent activity
$recent = $analytics->get_recent_votes(20);

// Get statistics for date range
$stats = $analytics->get_stats_for_period('2024-01-01', '2024-12-31');
```

---

## Testing with Mock Services

Helper functions work seamlessly with the DI container's mock capabilities:

```php
// In your test setup
$container = shuriken_container();

// Register a mock database
$mock_db = new Mock_Shuriken_Database();
$mock_db->add_rating(array(
    'id' => 1,
    'name' => 'Test Rating',
    'average' => 4.5,
    'total_votes' => 100,
));

$container->register('database', function() use ($mock_db) {
    return $mock_db;
});

// Now shuriken_db() returns the mock
$db = shuriken_db();
$rating = $db->get_rating(1); // Returns mock data
```

**See Also:** [Testing Guide](testing.md)

---

## Best Practices

1. **Use Helper Functions** - Prefer helper functions over direct class instantiation for consistency and testability.

2. **Leverage DI Container** - For custom services, register them in the container rather than creating globals.

3. **Interface-Based Development** - When extending functionality, implement the relevant interface to maintain compatibility.

4. **Avoid Direct Database Access** - Always use `shuriken_db()` instead of direct `$wpdb` queries for plugin tables.

5. **Cache Results When Possible** - Helper functions return singleton instances, but data should be cached appropriately.

---

## Version Compatibility

| Function | Since Version |
|----------|---------------|
| `shuriken_reviews()` | 1.0.0 |
| `shuriken_db()` | 1.0.0 |
| `shuriken_admin()` | 1.0.0 |
| `shuriken_ajax()` | 1.0.0 |
| `shuriken_frontend()` | 1.0.0 |
| `shuriken_shortcodes()` | 1.0.0 |
| `shuriken_container()` | 1.7.5 |
| `shuriken_analytics()` | 1.7.5 |
| `shuriken_block()` | 1.5.0 |
| `shuriken_rest_api()` | 1.7.0 |

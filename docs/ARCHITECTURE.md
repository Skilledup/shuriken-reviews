# Shuriken Reviews Architecture Overview

This document provides a high-level overview of the plugin's structure, design decisions, and how different components interact.

## Table of Contents

- [Directory Structure](#directory-structure)
- [Core Components](#core-components)
- [Module Responsibilities](#module-responsibilities)
- [Data Flow](#data-flow)
- [Design Patterns](#design-patterns)
- [Service Container](#service-container)
- [Entry Points](#entry-points)

---

## Directory Structure

```
shuriken-reviews/
│
├── shuriken-reviews.php          # Main plugin file - orchestration only
├── README.md                      # User documentation & quick start
│
├── admin/                         # Admin pages (rendered templates)
│   ├── about.php                  # About page with quick start
│   ├── analytics.php              # Analytics dashboard page
│   ├── comments.php               # Comments settings page
│   ├── ratings.php                # Ratings management page
│   ├── settings.php               # Plugin settings page
│   ├── item-stats.php             # Per-item statistics page
│   └── voter-activity.php         # Voter activity details page
│
├── assets/                        # CSS and JavaScript files
│   ├── css/
│   │   ├── shuriken-reviews.css           # Frontend styles
│   │   ├── admin-analytics.css            # Analytics page styles
│   │   ├── admin-ratings.css              # Ratings page styles
│   │   └── admin-about.css                # About page styles
│   ├── js/
│   │   ├── shuriken-reviews.js            # Frontend vote submission
│   │   ├── admin-analytics.js             # Analytics chart.js
│   │   └── admin-ratings.js               # Ratings page interactions
│   └── images/                    # UI images and icons
│
├── blocks/                        # Gutenberg block definitions
│   ├── shared/
│   │   └── ratings-store.js       # Shared @wordpress/data store
│   ├── shuriken-rating/
│   │   ├── index.js               # Block registration & editor
│   │   ├── block.json             # Block metadata
│   │   └── editor.css             # Editor-only styles
│   └── shuriken-grouped-rating/
│       ├── index.js               # Grouped rating block
│       ├── block.json             # Block metadata
│       └── editor.css             # Editor-only styles
│
├── includes/                      # Core PHP classes
│   ├── class-shuriken-*.php       # Feature modules
│   ├── comments.php               # Comment-related utilities
│   │
│   ├── interfaces/                # Service contracts
│   │   ├── interface-shuriken-database.php
│   │   └── interface-shuriken-analytics.php
│   │
│   └── exceptions/                # Error handling
│       ├── class-shuriken-exception.php (base)
│       ├── class-shuriken-database-exception.php
│       ├── class-shuriken-validation-exception.php
│       ├── class-shuriken-not-found-exception.php
│       ├── class-shuriken-permission-exception.php
│       ├── class-shuriken-logic-exception.php
│       ├── class-shuriken-configuration-exception.php
│       ├── class-shuriken-rate-limit-exception.php
│       └── class-shuriken-integration-exception.php
│
├── docs/                          # Developer documentation
│   ├── INDEX.md                   # Documentation index & quick start
│   ├── ARCHITECTURE.md            # This file
│   ├── ROADMAP.md                 # Feature roadmap & status
│   └── guides/
│       ├── hooks-reference.md          # All 20 hooks documented
│       ├── dependency-injection.md     # DI container guide + adoption status
│       ├── exception-handling.md       # Exception usage guide
│       ├── helper-functions.md         # Helper functions reference
│       ├── rest-api.md                 # REST API reference
│       ├── error-handling-blocks.md    # Block error handling
│       └── testing.md                  # Testing with mocks
│
├── languages/                     # Internationalization
│   ├── shuriken-reviews.pot       # Translation template
│   └── shuriken-reviews-fa_IR.* # Farsi translations
│
└── tests/                         # Testing utilities
    └── example-mock-database.php  # Mock implementation
```

---

## Core Components

### 1. Main Plugin File (`shuriken-reviews.php`)

**Purpose:** Orchestration and hook registration

**Responsibilities:**
- Register plugin hooks
- Initialize the service container
- Load and instantiate modules
- Load text domain for translations
- Handle plugin activation/deactivation

**Key Functions:**
- `shuriken_container()` - Get the DI container
- `shuriken_db()` - Get database service
- `shuriken_analytics()` - Get analytics service

### 2. Service Container (`class-shuriken-container.php`)

**Purpose:** Centralized service management

**Features:**
- Register singleton and transient services
- Resolve service dependencies
- Type-safe service retrieval
- Support for factory callbacks

**Registered Services:**
```php
database      → Shuriken_Database (implements Shuriken_Database_Interface)
analytics     → Shuriken_Analytics (implements Shuriken_Analytics_Interface)
rest_api      → Shuriken_REST_API
shortcodes    → Shuriken_Shortcodes
block         → Shuriken_Block
ajax          → Shuriken_AJAX
frontend      → Shuriken_Frontend
admin         → Shuriken_Admin
```

### 3. Database Service (`class-shuriken-database.php`)

**Implements:** `Shuriken_Database_Interface`

**Responsibilities:**
- All database operations (CRUD)
- Query building and execution
- Exception throwing on failures
- Data validation before insert/update
- Transaction management

**Key Methods:**
- `get_rating($id)` - Retrieve rating
- `get_ratings_by_ids($ids)` - Batch retrieve multiple ratings (single query)
- `search_ratings($term, $limit, $type)` - Search ratings by name (for AJAX autocomplete)
- `create_rating($data)` - Insert new rating
- `update_rating($id, $data)` - Update rating
- `delete_rating($id)` - Delete rating
- `create_vote($rating_id, $value, $user_id)` - Record vote
- `get_votes_for_rating($rating_id)` - Get votes

**Exception Handling:**
Throws `Shuriken_Database_Exception` on failures instead of returning false.

### 4. Analytics Service (`class-shuriken-analytics.php`)

**Implements:** `Shuriken_Analytics_Interface`

**Responsibilities:**
- Calculate rating statistics
- Generate analytics reports
- Identify trends and patterns
- Format data for display

**Key Methods:**
- `get_rating_stats($rating_id, $date_range)` - Get statistics
- `get_top_rated($limit)` - Top performers
- `get_most_voted($limit)` - Most popular
- `get_vote_distribution($rating_id)` - Votes per rating value
- `get_votes_over_time($rating_id, $interval)` - Trend data

### 5. Exception Handler (`class-shuriken-exception-handler.php`)

**Purpose:** Unified error handling across contexts

**Features:**
- Maps exceptions to appropriate HTTP status codes
- Logs exceptions with context
- Formats error responses for different contexts (AJAX, REST, Admin)
- Converts to WP_Error for WordPress compatibility

**Methods:**
- `handle_ajax_exception($exception)` - Send JSON error response
- `handle_rest_exception($exception)` - Return WP_REST_Response
- `handle_admin_exception($exception, $redirect)` - Show admin notice
- `safe_execute($callback, $context, $default)` - Execute with fallback

---

## Module Responsibilities

```
┌─────────────────────────────────────────────────────────────────┐
│                    shuriken-reviews.php                         │
│                    (Initialization & Routing)                   │
└─────────────────────────────────────────────────────────────────┘
                               │
                ┌──────────────┼──────────────┐
                │              │              │
        ┌───────▼─────┐  ┌─────▼──────┐  ┌──▼────────────┐
        │  Frontend    │  │  REST API  │  │    Admin      │
        │              │  │            │  │               │
        │ - Enqueue    │  │ - GET/POST │  │ - Pages       │
        │   CSS/JS     │  │   /ratings │  │ - Forms       │
        │ - Localize   │  │ - /votes   │  │ - Settings    │
        │   data       │  │ - /stats   │  │ - Analytics   │
        └──────┬───────┘  └─────┬──────┘  └──┬────────────┘
               │                │            │
        ┌──────▼──────┐  ┌──────▼────┐  ┌───▼──────────┐
        │   AJAX      │  │ Shortcode  │  │   Block      │
        │             │  │            │  │              │
        │ - Vote      │  │ - Render   │  │ - Register   │
        │   submission│  │   rating   │  │ - Render     │
        │ - Error     │  │ - Apply    │  │ - Edit UI    │
        │   handling  │  │   hooks    │  │              │
        └──────┬──────┘  └──────┬─────┘  └───┬──────────┘
               │                │            │
               └────────────────┼────────────┘
                                │
                ┌───────────────┴────────────────┐
                │                                │
        ┌───────▼──────────┐          ┌─────────▼────────┐
        │  DI Container    │          │ Exception Handler│
        │                  │          │                  │
        │ - Register       │          │ - Log errors     │
        │   services       │          │ - Format         │
        │ - Resolve        │          │   responses      │
        │   dependencies   │          │ - Map HTTP codes │
        └────────┬─────────┘          └──────────────────┘
                 │
        ┌────────┴──────────────────────────┐
        │                                    │
    ┌───▼──────────┐          ┌─────────────▼───────┐
    │  Database    │          │    Analytics        │
    │  Service     │          │    Service          │
    │              │          │                     │
    │ - CRUD ops   │          │ - Statistics        │
    │ - Throw      │          │ - Trends            │
    │   exceptions │          │ - Reports           │
    └──────────────┘          └─────────────────────┘
```

### Frontend Module (`class-shuriken-frontend.php`)

Responsible for enqueuing frontend assets and localizing JavaScript data.

**Hooks:**
- `wp_enqueue_scripts` - Load CSS/JS
- `wp_localize_script` - Pass PHP data to JS
- `wp_footer` - Output inline scripts

### REST API Module (`class-shuriken-rest-api.php`)

Provides REST endpoints for programmatic access and AJAX fallback.

**Endpoints:**
- `GET /wp-json/shuriken-reviews/v1/ratings`
- `GET /wp-json/shuriken-reviews/v1/ratings/search` - AJAX autocomplete search
- `GET /wp-json/shuriken-reviews/v1/ratings/parents` - Parent ratings only
- `GET /wp-json/shuriken-reviews/v1/ratings/mirrorable` - Mirrorable ratings
- `POST /wp-json/shuriken-reviews/v1/votes`
- `GET /wp-json/shuriken-reviews/v1/ratings/stats` - Batch stats (optimized)
- `GET /wp-json/shuriken-reviews/v1/nonce`

### Shortcodes Module (`class-shuriken-shortcodes.php`)

Handles `[shuriken_rating]` shortcode registration and rendering.

**Responsibility:**
- Parse shortcode attributes
- Validate input
- Render rating with hooks
- Return HTML

### Block Module (`class-shuriken-block.php`)

Registers and renders Gutenberg blocks.

**Features:**
- FSE (Full Site Editor) compatible
- Uses same rendering as shortcode
- Gutenberg editor UI
- Block attributes management
- Shared data store registration

**Blocks:**
- `shuriken-reviews/rating` - Single rating display
- `shuriken-reviews/grouped-rating` - Parent with child ratings

### Shared Data Store (`blocks/shared/ratings-store.js`)

Centralized state management for all rating blocks using `@wordpress/data`.

**Features:**
- Prevents duplicate API calls across multiple blocks
- Caches fetched ratings by ID
- Debounced AJAX search for rating selection
- Shared state between all block instances

**Store Name:** `shuriken-reviews`

**Key Selectors:**
- `getRating(id)` - Get cached rating
- `getSearchResults()` - Current search results
- `getParentRatings()` - All parent ratings
- `isSearching()` - Search loading state

**Key Actions:**
- `fetchRating(id)` - Fetch single rating
- `searchRatings(term, type, limit)` - AJAX search
- `fetchParentRatings()` - Load parent ratings
- `createRating(data)` - Create new rating
- `updateRating(id, data)` - Update rating
- `deleteRating(id)` - Delete rating

### AJAX Module (`class-shuriken-ajax.php`)

Handles AJAX requests for vote submission.

**Responsibility:**
- Validate AJAX requests
- Process vote submission
- Handle errors
- Return JSON response

### Admin Module (`class-shuriken-admin.php`)

Manages WordPress admin pages.

**Pages:**
- Ratings management
- Comments settings
- Analytics dashboard
- Plugin settings
- About page

---

## Data Flow

### Vote Submission Flow

```
User clicks star
        │
        ▼
JavaScript sends AJAX request
(or REST request)
        │
        ▼
AJAX Handler validates
- Check nonce
- Validate input
- Check permissions
        │
        ▼
Database operations
- Check if vote exists
- Create or update vote
- Recalculate rating average
- Update total votes
        │
        ▼
Hooks fired
- shuriken_before_submit_vote
- shuriken_vote_created/updated
- shuriken_after_submit_vote
        │
        ▼
Return response to frontend
- New average
- New total votes
- Parent rating updates
        │
        ▼
JavaScript updates display
- Animate stars
- Update statistics
- Show message
```

### Rating Creation Flow

```
Admin clicks "Create Rating"
        │
        ▼
Form submitted to REST API
or Admin page handler
        │
        ▼
Validate input
- Required fields
- Data types
- Permissions
        │
        ▼
Apply filter
- shuriken_before_create_rating
        │
        ▼
Database insert
- Create rating record
- Set defaults
- Handle hierarchy (parent/mirror)
        │
        ▼
Fire action
- shuriken_rating_created
        │
        ▼
Return success response
- Redirect to ratings page
- Show success message
```

---

## Design Patterns

### 1. Service Locator / Dependency Injection

Services are registered in the container and retrieved when needed:

```php
// Container registration
shuriken_container()->singleton('database', function($container) {
    return new Shuriken_Database();
});

// Usage
$db = shuriken_container()->get('database');
// or use helper
$db = shuriken_db();
```

### 2. Factory Pattern

Exceptions use factory methods for consistent error creation:

```php
throw Shuriken_Not_Found_Exception::rating($id);
// Instead of: throw new Shuriken_Not_Found_Exception('Rating not found');
```

### 3. Strategy Pattern

Interfaces allow swapping implementations:

```php
// Production
$db = new Shuriken_Database();

// Testing
$db = new Mock_Shuriken_Database();

$analytics = new Shuriken_Analytics($db); // Works with either
```

### 4. Observer Pattern

WordPress hooks (actions and filters) provide extension points:

```php
// Plugin fires hooks at key points
do_action('shuriken_vote_created', $rating_id, $value, ...);

// Other code responds
add_action('shuriken_vote_created', 'my_email_notification');
```

### 5. Decorator Pattern

Hooks allow wrapping/modifying data:

```php
// Filter modifies data before display
add_filter('shuriken_rating_css_classes', function($classes, $rating) {
    if ($rating->average >= 4) {
        $classes .= ' high-rated';
    }
    return $classes;
});
```

### 6. Template Method Pattern

Base exception class defines the template, subclasses provide specifics:

```php
class Shuriken_Exception extends Exception {
    public function get_error_code() { ... }
    public function log($context) { ... }
}

class Shuriken_Database_Exception extends Shuriken_Exception {
    public static function insert_failed($table) { ... }
}
```

---

## Service Container

The container manages all plugin services and their dependencies.

### DI Adoption Status

All services with database dependencies now use constructor injection:

| Service | Dependencies | DI Status |
|---------|--------------|-----------|
| `Shuriken_Database` | Foundation | Base singleton |
| `Shuriken_Analytics` | `database` | ✅ DI-ready |
| `Shuriken_REST_API` | `database` | ✅ DI-ready |
| `Shuriken_Admin` | `database`, `analytics` | ✅ DI-ready |
| `Shuriken_AJAX` | `database` | ✅ DI-ready |
| `Shuriken_Block` | `database` | ✅ DI-ready |
| `Shuriken_Shortcodes` | `database` | ✅ DI-ready |
| `Shuriken_Frontend` | None | No DI needed |

**Coverage:** 87.5% (7 of 8 services)

### Service Registration

```php
// Singleton - same instance every time
shuriken_container()->singleton('database', function($container) {
    return Shuriken_Database::get_instance();
});

// With dependencies injected
shuriken_container()->singleton('analytics', function($container) {
    return new Shuriken_Analytics($container->get('database'));
});

shuriken_container()->singleton('admin', function($container) {
    return new Shuriken_Admin(
        $container->get('database'),
        $container->get('analytics')
    );
});

shuriken_container()->singleton('rest_api', function($container) {
    return new Shuriken_REST_API($container->get('database'));
});
```

### Service Resolution

```php
// Get from container
$db = shuriken_container()->get('database');

// Magic property access
$db = shuriken_container()->database;

// Helper function (backward compatible)
$db = shuriken_db();
```

### Benefits of DI

1. **Testability** - Inject mocks instead of real services
2. **Flexibility** - Swap implementations without changing code
3. **Clarity** - Dependencies visible in constructor
4. **Loose Coupling** - Classes depend on interfaces, not concrete classes

### Constructor Injection Pattern

All DI-ready services follow this pattern:

```php
class Shuriken_REST_API {
    /** @var Shuriken_Database_Interface */
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: shuriken_db();
    }
    
    public function get_db() {
        return $this->db;
    }
}
```

This allows:
- **Testing:** `new Shuriken_REST_API($mock_db)`
- **Production:** Container injects real database automatically

---

## Entry Points

### 1. Plugin Initialization

```php
// shuriken-reviews.php
if (!defined('ABSPATH')) {
    exit;
}

define('SHURIKEN_REVIEWS_VERSION', '2.0.0');
define('SHURIKEN_REVIEWS_DIR', plugin_dir_path(__FILE__));
define('SHURIKEN_REVIEWS_URL', plugin_dir_url(__FILE__));

// Load core files
require_once SHURIKEN_REVIEWS_DIR . 'includes/class-shuriken-container.php';
require_once SHURIKEN_REVIEWS_DIR . 'includes/class-shuriken-database.php';
// ... etc

// Initialize container and modules
Shuriken_Reviews::init();
```

### 2. Frontend (Page Request)

```
WordPress loads plugins
    ↓
Plugin loads and initializes
    ↓
Frontend module enqueues CSS/JS
    ↓
Shortcode or block renders
    ↓
JavaScript in footer enables interactivity
    ↓
User can see and vote on ratings
```

### 3. AJAX Request

```
JavaScript sends AJAX
    ↓
AJAX module receives request
    ↓
Validates nonce and input
    ↓
Database service processes vote
    ↓
Exceptions caught by handler
    ↓
Response sent to JavaScript
    ↓
Frontend updates display
```

### 4. REST API Request

```
External client makes HTTP request
    ↓
REST API module receives request
    ↓
WordPress REST framework validates
    ↓
Endpoint handler executes
    ↓
Database service performs operation
    ↓
Response returned as JSON
```

### 5. Admin Page Load

```
Admin clicks menu item
    ↓
Admin module renders page
    ↓
Page retrieves data from services
    ↓
HTML with forms/tables displayed
    ↓
Admin interacts (submit form, etc)
    ↓
Form handler processes in POST
    ↓
Database updated, redirect with message
```

---

## Summary

The Shuriken Reviews architecture emphasizes:

- **Modularity** - Each component has a clear, focused responsibility
- **Extensibility** - 20+ hooks for customization without core modifications
- **Testability** - Interfaces and dependency injection enable unit testing
- **Robustness** - Comprehensive exception handling and validation
- **Maintainability** - Clear structure makes code easy to understand and modify

This design allows the plugin to be both powerful for end users and developer-friendly for extensions and customization.

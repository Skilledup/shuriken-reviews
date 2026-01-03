# Dependency Injection in Shuriken Reviews

The plugin uses a lightweight dependency injection (DI) container to manage services and their dependencies.

## What is Dependency Injection?

Dependency Injection is a design pattern where objects receive their dependencies from external sources rather than creating them internally.

### Before DI (Tightly Coupled)

```php
class Analytics {
    private $db;
    
    public function __construct() {
        // Hard-coded dependency - difficult to test
        $this->db = shuriken_db();
    }
}
```

### After DI (Loosely Coupled)

```php
class Analytics {
    private $db;
    
    public function __construct(Shuriken_Database_Interface $db = null) {
        // Dependency injected - easy to test with mocks
        $this->db = $db ?: shuriken_db();
    }
}

// Usage
$db = shuriken_db();
$analytics = new Analytics($db);

// Testing
$mock_db = new Mock_Database();
$analytics = new Analytics($mock_db);
```

## The Container

The `Shuriken_Container` class manages all plugin services.

### Getting the Container

```php
$container = shuriken_container();
```

### Registered Services

The following services are automatically registered:

| Service Name | Class | Interface |
|-------------|-------|-----------|
| `database` | `Shuriken_Database` | `Shuriken_Database_Interface` |
| `analytics` | `Shuriken_Analytics` | `Shuriken_Analytics_Interface` |
| `rest_api` | `Shuriken_REST_API` | - |
| `shortcodes` | `Shuriken_Shortcodes` | - |
| `block` | `Shuriken_Block` | - |
| `ajax` | `Shuriken_AJAX` | - |
| `frontend` | `Shuriken_Frontend` | - |
| `admin` | `Shuriken_Admin` | - |

### Getting Services

```php
// Get database service
$db = shuriken_container()->get('database');

// Get analytics service
$analytics = shuriken_container()->get('analytics');

// Or use magic property access
$db = shuriken_container()->database;
$analytics = shuriken_container()->analytics;
```

### Backward Compatibility

Helper functions maintain backward compatibility:

```php
// Old way (still works)
$db = shuriken_db();
$analytics = shuriken_analytics();

// New way (recommended)
$db = shuriken_container()->get('database');
$analytics = shuriken_container()->get('analytics');
```

## Registering Custom Services

### Transient Services

Created fresh each time:

```php
shuriken_container()->bind('my_service', function($container) {
    return new My_Service();
});

// Each call creates a new instance
$service1 = shuriken_container()->get('my_service');
$service2 = shuriken_container()->get('my_service');
// $service1 !== $service2
```

### Singleton Services

Created once and reused:

```php
shuriken_container()->singleton('my_service', function($container) {
    return new My_Service();
});

// Same instance returned each time
$service1 = shuriken_container()->get('my_service');
$service2 = shuriken_container()->get('my_service');
// $service1 === $service2
```

### Services with Dependencies

```php
shuriken_container()->singleton('my_service', function($container) {
    // Inject dependencies
    $db = $container->get('database');
    $analytics = $container->get('analytics');
    
    return new My_Service($db, $analytics);
});
```

## Constructor Injection Pattern

Classes should accept dependencies via constructor with optional default:

```php
class My_Service {
    private $db;
    private $analytics;
    
    /**
     * Constructor with dependency injection
     *
     * @param Shuriken_Database_Interface|null   $db        Database service.
     * @param Shuriken_Analytics_Interface|null  $analytics Analytics service.
     */
    public function __construct($db = null, $analytics = null) {
        $this->db = $db ?: shuriken_db();
        $this->analytics = $analytics ?: shuriken_analytics();
    }
}
```

This pattern allows:
1. **Explicit injection** for testing: `new My_Service($mock_db, $mock_analytics)`
2. **Automatic resolution** for production: `new My_Service()`

## Testing with Dependency Injection

### Injecting Mocks

```php
class Test_My_Service extends WP_UnitTestCase {
    public function test_my_function() {
        // Create mock database
        $mock_db = new Mock_Shuriken_Database([
            (object) ['id' => 1, 'name' => 'Test']
        ]);
        
        // Inject mock into service
        $service = new My_Service($mock_db);
        
        // Test with predictable data
        $result = $service->get_rating_name(1);
        $this->assertEquals('Test', $result);
    }
}
```

### Replacing Services in Container

```php
// Replace database service with mock
$mock_db = new Mock_Shuriken_Database();
shuriken_container()->set('database', $mock_db);

// Now all services that use the database will get the mock
$analytics = shuriken_container()->get('analytics');
// $analytics now uses $mock_db
```

### Resetting Container

```php
// Reset container between tests
public function tearDown() {
    shuriken_container()->reset();
    parent::tearDown();
}
```

## Benefits of Dependency Injection

### 1. Testability

**Without DI:**
```php
class Analytics {
    public function __construct() {
        $this->db = shuriken_db(); // Hard to mock
    }
}

// Testing requires real database
$analytics = new Analytics(); // Uses real DB
```

**With DI:**
```php
class Analytics {
    public function __construct($db = null) {
        $this->db = $db ?: shuriken_db();
    }
}

// Testing with mock
$mock_db = new Mock_Database();
$analytics = new Analytics($mock_db); // Uses mock
```

### 2. Flexibility

Easy to swap implementations:

```php
// Production: Use MySQL database
$db = new Shuriken_Database();
$analytics = new Analytics($db);

// Development: Use mock database
$db = new Mock_Database();
$analytics = new Analytics($db);

// Alternative: Use different storage
$db = new Redis_Database();
$analytics = new Analytics($db);
```

### 3. Explicit Dependencies

Constructor shows what the class needs:

```php
// Clear dependencies
public function __construct(
    Shuriken_Database_Interface $db,
    Shuriken_Analytics_Interface $analytics,
    WP_User $user
) {
    // Dependencies are obvious
}
```

### 4. Loose Coupling

Classes depend on interfaces, not concrete implementations:

```php
// Depends on interface (good)
public function __construct(Shuriken_Database_Interface $db) {
    $this->db = $db;
}

// Depends on concrete class (less flexible)
public function __construct(Shuriken_Database $db) {
    $this->db = $db;
}
```

## Best Practices

### 1. Type Hint Interfaces

```php
// ✅ Good: Type hint interface
public function __construct(Shuriken_Database_Interface $db = null) {
    $this->db = $db ?: shuriken_db();
}

// ❌ Less ideal: Type hint concrete class
public function __construct(Shuriken_Database $db = null) {
    $this->db = $db ?: shuriken_db();
}
```

### 2. Provide Defaults

Allow both explicit injection and automatic resolution:

```php
// ✅ Good: Optional parameter with default
public function __construct($db = null) {
    $this->db = $db ?: shuriken_db();
}

// ❌ Less flexible: Required parameter
public function __construct($db) {
    $this->db = $db; // Must always provide
}
```

### 3. Inject Services, Not Container

```php
// ✅ Good: Inject specific services
public function __construct($db, $analytics) {
    $this->db = $db;
    $this->analytics = $analytics;
}

// ❌ Service Locator anti-pattern
public function __construct($container) {
    $this->db = $container->get('database');
    $this->analytics = $container->get('analytics');
}
```

### 4. Keep Container Usage Minimal

```php
// ✅ Good: Use container in bootstrap/factory code
public function init_modules() {
    $container = shuriken_container();
    $container->get('rest_api');
    $container->get('shortcodes');
}

// ❌ Bad: Use container everywhere
public function my_function() {
    $db = shuriken_container()->get('database'); // Use helper instead
}
```

## Example: Complete Service with DI

```php
/**
 * Example service with proper dependency injection
 */
class My_Rating_Service {
    
    /**
     * @var Shuriken_Database_Interface
     */
    private $db;
    
    /**
     * @var Shuriken_Analytics_Interface
     */
    private $analytics;
    
    /**
     * Constructor
     *
     * @param Shuriken_Database_Interface|null   $db        Database service.
     * @param Shuriken_Analytics_Interface|null  $analytics Analytics service.
     */
    public function __construct($db = null, $analytics = null) {
        $this->db = $db ?: shuriken_db();
        $this->analytics = $analytics ?: shuriken_analytics();
    }
    
    /**
     * Get top rated items
     *
     * @param int $limit Number of items.
     * @return array
     */
    public function get_top_rated($limit = 10) {
        return $this->analytics->get_top_rated($limit);
    }
    
    /**
     * Create a rating
     *
     * @param string $name Rating name.
     * @return int|false Rating ID or false on failure.
     */
    public function create_rating($name) {
        try {
            return $this->db->create_rating($name);
        } catch (Shuriken_Database_Exception $e) {
            $e->log('My Rating Service');
            return false;
        }
    }
}

// Register in container
shuriken_container()->singleton('rating_service', function($container) {
    return new My_Rating_Service(
        $container->get('database'),
        $container->get('analytics')
    );
});

// Usage in production
$service = shuriken_container()->get('rating_service');
$top_rated = $service->get_top_rated(10);

// Usage in tests
$mock_db = new Mock_Database();
$mock_analytics = new Mock_Analytics();
$service = new My_Rating_Service($mock_db, $mock_analytics);
$top_rated = $service->get_top_rated(10);
```

## Migration Guide

### Step 1: Add Optional Constructor Parameter

```php
// Before
public function __construct() {
    $this->db = shuriken_db();
}

// After
public function __construct($db = null) {
    $this->db = $db ?: shuriken_db();
}
```

### Step 2: Type Hint Interface

```php
public function __construct(Shuriken_Database_Interface $db = null) {
    $this->db = $db ?: shuriken_db();
}
```

### Step 3: Register in Container

```php
shuriken_container()->singleton('my_service', function($container) {
    return new My_Service($container->get('database'));
});
```

### Step 4: Update Tests

```php
// Old
$service = new My_Service(); // Uses real DB

// New
$mock_db = new Mock_Database();
$service = new My_Service($mock_db); // Uses mock
```

## Resources

- [Dependency Injection Explained](https://en.wikipedia.org/wiki/Dependency_injection)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Inversion of Control](https://en.wikipedia.org/wiki/Inversion_of_control)

See [INDEX.md](../INDEX.md) for complete documentation index.

# Testing & Testing Utilities

Learn how to test your code with mock implementations without requiring a database.

## Interfaces for Testability

The plugin now provides interfaces for core classes, making it easy to create mock implementations for testing:

- `Shuriken_Database_Interface` - Database operations
- `Shuriken_Analytics_Interface` - Analytics and statistics

## Using Mock Implementations

### Example: Testing with Mock Database

```php
require_once 'tests/example-mock-database.php';

// Create test data
$test_ratings = [
    (object) [
        'id' => 1,
        'name' => 'Product Quality',
        'total_votes' => 10,
        'total_rating' => 45,
        'average' => 4.5,
        'parent_id' => null,
        'effect_type' => 'positive',
        'display_only' => false,
        'mirror_of' => null,
        'source_id' => 1,
        'date_created' => '2024-01-01 00:00:00'
    ]
];

// Create mock database
$mock_db = new Mock_Shuriken_Database($test_ratings);

// Test your code
$rating = $mock_db->get_rating(1);
assert($rating->name === 'Product Quality');
assert($rating->average === 4.5);

// Test vote creation
$mock_db->create_vote(1, 5, 123);
$updated = $mock_db->get_rating(1);
assert($updated->total_votes === 11);
assert($updated->average === 4.6); // (45 + 5) / 11
```

### Benefits of Interface-Based Testing

1. **No Database Required** - Tests run without WordPress database
2. **Fast Execution** - In-memory operations are instant
3. **Predictable Results** - Control exactly what data is returned
4. **Isolation** - Tests don't affect each other or production data
5. **Easy Mocking** - Create custom implementations for specific scenarios

## Writing Your Own Mocks

To create a mock implementation:

1. Implement the interface:
   ```php
   class My_Mock_Database implements Shuriken_Database_Interface {
       // Implement all required methods
   }
   ```

2. Return predictable test data:
   ```php
   public function get_rating($rating_id) {
       return (object) [
           'id' => $rating_id,
           'name' => 'Test Rating',
           'average' => 4.0,
           // ... other properties
       ];
   }
   ```

3. Use in your tests:
   ```php
   $mock = new My_Mock_Database();
   $result = my_function_that_uses_database($mock);
   assert($result === 'expected value');
   ```

## Integration with Testing Frameworks

### PHPUnit Example

```php
use PHPUnit\Framework\TestCase;

class RatingTest extends TestCase {
    private $mock_db;
    
    protected function setUp(): void {
        $this->mock_db = new Mock_Shuriken_Database([
            (object) ['id' => 1, 'name' => 'Test', 'average' => 4.5]
        ]);
    }
    
    public function test_get_rating() {
        $rating = $this->mock_db->get_rating(1);
        $this->assertEquals('Test', $rating->name);
        $this->assertEquals(4.5, $rating->average);
    }
    
    public function test_create_vote() {
        $result = $this->mock_db->create_vote(1, 5, 123);
        $this->assertTrue($result);
        
        $rating = $this->mock_db->get_rating(1);
        $this->assertEquals(1, $rating->total_votes);
    }
}
```

### WordPress Test Suite

```php
class Test_Shuriken_Reviews extends WP_UnitTestCase {
    public function test_rating_display() {
        $mock_db = new Mock_Shuriken_Database([
            (object) ['id' => 1, 'name' => 'Test', 'average' => 4.5]
        ]);
        
        // Test shortcode rendering with mock data
        $shortcode = new Shuriken_Shortcodes();
        // Inject mock database (if using dependency injection)
        $html = $shortcode->render_rating(['id' => 1]);
        
        $this->assertStringContainsString('Test', $html);
        $this->assertStringContainsString('4.5', $html);
    }
}
```

## Type Hinting with Interfaces

Use interfaces in function signatures for better testability:

```php
// Before (tightly coupled)
function calculate_average(Shuriken_Database $db, $rating_id) {
    $rating = $db->get_rating($rating_id);
    return $rating->average;
}

// After (loosely coupled, testable)
function calculate_average(Shuriken_Database_Interface $db, $rating_id) {
    $rating = $db->get_rating($rating_id);
    return $rating->average;
}

// Now you can test with either real or mock database
$result = calculate_average($mock_db, 1);
$result = calculate_average(shuriken_db(), 1);
```

## Testing with Dependency Injection

```php
// Service with injected dependencies
class My_Service {
    public function __construct(Shuriken_Database_Interface $db = null) {
        $this->db = $db ?: shuriken_db();
    }
    
    public function get_top_rated($limit = 10) {
        return $this->db->get_top_rated($limit);
    }
}

// Testing
$mock_db = new Mock_Shuriken_Database([
    (object) ['id' => 1, 'name' => 'Best', 'average' => 5.0],
    (object) ['id' => 2, 'name' => 'Good', 'average' => 4.0]
]);

$service = new My_Service($mock_db);
$top = $service->get_top_rated(1);

assert($top[0]->name === 'Best');
```

## Testing Hooks and Filters

```php
class Test_Hooks extends WP_UnitTestCase {
    public function test_rating_data_filter() {
        // Add a test filter
        add_filter('shuriken_rating_data', function($rating, $tag, $anchor) {
            $rating->name = strtoupper($rating->name);
            return $rating;
        }, 10, 3);
        
        // Create a mock rating
        $rating = (object) [
            'id' => 1,
            'name' => 'test',
            'average' => 4.0
        ];
        
        // Apply filter
        $filtered = apply_filters('shuriken_rating_data', $rating, 'h2', '');
        
        $this->assertEquals('TEST', $filtered->name);
    }
    
    public function test_vote_created_action() {
        // Track if action was called
        $called = false;
        
        add_action('shuriken_vote_created', function() use (&$called) {
            $called = true;
        });
        
        // Trigger action
        do_action('shuriken_vote_created', 1, 5, 5, 1, '', null, 5);
        
        $this->assertTrue($called);
    }
}
```

## Example: Complete Test Suite

```php
class Test_Rating_Service extends WP_UnitTestCase {
    private $mock_db;
    private $service;
    
    protected function setUp(): void {
        parent::setUp();
        
        // Create mock database with test data
        $this->mock_db = new Mock_Shuriken_Database([
            (object) [
                'id' => 1,
                'name' => 'Product Quality',
                'average' => 4.5,
                'total_votes' => 10,
                'total_rating' => 45,
                'display_only' => false
            ]
        ]);
        
        // Inject into service
        $this->service = new Shuriken_Analytics($this->mock_db);
    }
    
    public function test_get_rating_stats() {
        $stats = $this->service->get_rating_stats(1);
        
        $this->assertEquals(4.5, $stats['average']);
        $this->assertEquals(10, $stats['total_votes']);
    }
    
    public function test_vote_affects_average() {
        // Create vote
        $this->mock_db->create_vote(1, 5, 123);
        
        // Get updated rating
        $stats = $this->service->get_rating_stats(1);
        
        // Average should be updated
        $expected_average = (45 + 5) / 11; // 4.545...
        $this->assertAlmostEquals($expected_average, $stats['average'], 2);
    }
    
    public function test_multiple_votes() {
        // Add several votes
        $this->mock_db->create_vote(1, 5, 123);
        $this->mock_db->create_vote(1, 4, 124);
        $this->mock_db->create_vote(1, 3, 125);
        
        // Check totals
        $stats = $this->service->get_rating_stats(1);
        
        $this->assertEquals(13, $stats['total_votes']);
        $expected_avg = (45 + 5 + 4 + 3) / 13;
        $this->assertAlmostEquals($expected_avg, $stats['average'], 2);
    }
    
    public function test_top_rated() {
        // Add more ratings
        $this->mock_db->ratings[] = (object) [
            'id' => 2,
            'name' => 'Good',
            'average' => 3.5,
            'total_votes' => 5
        ];
        
        $top = $this->service->get_top_rated(1);
        
        // Best rating should be first
        $this->assertEquals(1, $top[0]['id']);
        $this->assertEquals(4.5, $top[0]['average']);
    }
}
```

## Testing Exceptions

```php
class Test_Exception_Handling extends WP_UnitTestCase {
    public function test_not_found_exception() {
        // Create empty database
        $mock_db = new Mock_Shuriken_Database([]);
        
        // Should throw exception
        try {
            $rating = $mock_db->get_rating(999);
            if (!$rating) {
                throw Shuriken_Not_Found_Exception::rating(999);
            }
        } catch (Shuriken_Not_Found_Exception $e) {
            $this->assertEquals(404, $e->get_http_status());
        }
    }
    
    public function test_validation_exception() {
        $this->expectException(Shuriken_Validation_Exception::class);
        
        throw Shuriken_Validation_Exception::invalid_rating_value(0, 5);
    }
}
```

## Best Practices for Testing

### 1. Use Fixtures

```php
protected function setUp(): void {
    $this->test_ratings = [
        (object) ['id' => 1, 'name' => 'Rating 1', 'average' => 4.5],
        (object) ['id' => 2, 'name' => 'Rating 2', 'average' => 3.5],
    ];
    
    $this->mock_db = new Mock_Shuriken_Database($this->test_ratings);
}
```

### 2. Test One Thing

```php
// ✅ Good: Test one behavior
public function test_rating_average_calculation() {
    $this->mock_db->create_vote(1, 5, 123);
    $rating = $this->mock_db->get_rating(1);
    $this->assertEquals(4.6, round($rating->average, 1));
}

// ❌ Bad: Testing multiple things
public function test_everything() {
    $this->mock_db->create_vote(1, 5, 123);
    $rating = $this->mock_db->get_rating(1);
    $stats = $this->service->get_stats($rating);
    // ... testing too much
}
```

### 3. Use Descriptive Names

```php
// ✅ Good: Clear what's being tested
public function test_guest_vote_updates_total_count() { }

// ❌ Bad: Vague
public function test_vote() { }
```

### 4. Arrange-Act-Assert

```php
public function test_something() {
    // ARRANGE - Set up test data
    $mock_db = new Mock_Shuriken_Database([...]);
    $service = new My_Service($mock_db);
    
    // ACT - Execute the code being tested
    $result = $service->do_something();
    
    // ASSERT - Verify the result
    $this->assertEquals(expected, $result);
}
```

## Running Tests

### PHPUnit

```bash
# Run all tests
phpunit

# Run specific test file
phpunit tests/MyTest.php

# Run specific test method
phpunit tests/MyTest.php::test_method_name
```

### WordPress Test Suite

```bash
# Run WordPress tests
cd /path/to/wordpress/wp-content/plugins/shuriken-reviews
wp test
```

## Next Steps

1. **Write Unit Tests** - Test individual methods with mocks
2. **Write Integration Tests** - Test with real WordPress database
3. **Write Acceptance Tests** - Test user workflows
4. **Set Up CI/CD** - Automate testing on commits

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Testing](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)
- [Test-Driven Development](https://en.wikipedia.org/wiki/Test-driven_development)

See [INDEX.md](../INDEX.md) for complete documentation index.

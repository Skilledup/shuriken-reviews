# Shuriken Reviews Testing

This directory contains examples and utilities for testing the Shuriken Reviews plugin.

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

## Next Steps

1. **Implement Dependency Injection** - Pass database instances instead of using global functions
2. **Create Test Suites** - Build comprehensive PHPUnit or WordPress test suites
3. **Add CI/CD** - Automate testing in your deployment pipeline
4. **Mock Analytics** - Create mock implementations for `Shuriken_Analytics_Interface`

## Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Plugin Testing](https://make.wordpress.org/cli/handbook/plugin-unit-tests/)
- [Test-Driven Development](https://en.wikipedia.org/wiki/Test-driven_development)


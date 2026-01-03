# Exception System in Shuriken Reviews

Learn about the plugin's exception hierarchy and error handling system for robust, maintainable code.

## Exception Hierarchy

```
Exception (PHP)
    └── Shuriken_Exception (Base)
            ├── Shuriken_Database_Exception ✓ Implemented
            ├── Shuriken_Validation_Exception ✓ Implemented
            ├── Shuriken_Not_Found_Exception ✓ Implemented
            ├── Shuriken_Permission_Exception ✓ Implemented
            ├── Shuriken_Logic_Exception ✓ Implemented
            ├── Shuriken_Configuration_Exception ✓ Implemented
            ├── Shuriken_Rate_Limit_Exception (TODO: Rate limiting not yet implemented)
            └── Shuriken_Integration_Exception (Partial: Some features reserved for future use)
```

### Implementation Status

| Exception | Status | When to Use |
|-----------|--------|------------|
| Base, Database, Validation, Not_Found, Permission, Logic | ✅ Ready | All current features |
| Configuration | ✅ Ready | Plugin settings validation |
| Rate_Limit | 🚧 TODO | Reserved for vote throttling/cooldown (not implemented) |
| Integration | ⚠️ Partial | HTTP/API failures work; webhooks/cache/email reserved for future |

## Exception Types

### 1. `Shuriken_Exception` (Base)

The base exception all plugin exceptions extend from.

**Features:**
- Error code for logging/debugging
- Convert to `WP_Error` for WordPress compatibility
- Built-in logging method
- Stores previous exception for chaining

**Methods:**
- `get_error_code()` - Get the error code
- `to_wp_error()` - Convert to WP_Error
- `log($context)` - Log the exception

### 2. `Shuriken_Database_Exception`

For database operation failures.

**Static Factory Methods:**
```php
Shuriken_Database_Exception::insert_failed('ratings');
Shuriken_Database_Exception::update_failed('ratings', 123);
Shuriken_Database_Exception::delete_failed('votes', 456);
Shuriken_Database_Exception::query_failed('get_ratings');
Shuriken_Database_Exception::transaction_failed();
```

**Usage:**
```php
try {
    $result = $wpdb->insert($table, $data);
    if ($result === false) {
        throw Shuriken_Database_Exception::insert_failed('ratings');
    }
} catch (Shuriken_Database_Exception $e) {
    $e->log('Creating new rating');
    return false;
}
```

### 3. `Shuriken_Validation_Exception`

For input validation failures.

**Static Factory Methods:**
```php
Shuriken_Validation_Exception::required_field('rating_id');
Shuriken_Validation_Exception::invalid_value('rating_value', $value, 'integer 1-5');
Shuriken_Validation_Exception::out_of_range('rating_value', $value, 1, 5);
Shuriken_Validation_Exception::invalid_rating_value($value, $max_stars);
```

**Additional Methods:**
- `get_field()` - Get the field that failed validation
- `get_invalid_value()` - Get the invalid value

**Usage:**
```php
if (empty($rating_id)) {
    throw Shuriken_Validation_Exception::required_field('rating_id');
}

if ($rating_value < 1 || $rating_value > 5) {
    throw Shuriken_Validation_Exception::out_of_range('rating_value', $rating_value, 1, 5);
}
```

### 4. `Shuriken_Not_Found_Exception`

For missing resources (404 errors).

**Static Factory Methods:**
```php
Shuriken_Not_Found_Exception::rating($rating_id);
Shuriken_Not_Found_Exception::vote($vote_id);
Shuriken_Not_Found_Exception::parent_rating($parent_id);
```

**Additional Methods:**
- `get_resource_type()` - Get the type of resource (e.g., 'rating')
- `get_resource_id()` - Get the resource ID

**Usage:**
```php
$rating = $db->get_rating($id);
if (!$rating) {
    throw Shuriken_Not_Found_Exception::rating($id);
}
```

### 5. `Shuriken_Permission_Exception`

For authorization failures (403 errors).

**Static Factory Methods:**
```php
Shuriken_Permission_Exception::unauthorized('delete ratings');
Shuriken_Permission_Exception::guest_not_allowed();
Shuriken_Permission_Exception::missing_capability('manage_options');
Shuriken_Permission_Exception::voting_not_allowed('voting period has ended');
```

**Additional Methods:**
- `get_required_permission()` - Get the required permission

**Usage:**
```php
if (!current_user_can('manage_options')) {
    throw Shuriken_Permission_Exception::missing_capability('manage_options');
}

if (!is_user_logged_in() && !$allow_guest_voting) {
    throw Shuriken_Permission_Exception::guest_not_allowed();
}
```

### 6. `Shuriken_Logic_Exception`

For business logic violations.

**Static Factory Methods:**
```php
Shuriken_Logic_Exception::display_only_rating();
Shuriken_Logic_Exception::circular_reference();
Shuriken_Logic_Exception::invalid_parent();
Shuriken_Logic_Exception::invalid_mirror_target();
Shuriken_Logic_Exception::duplicate_vote();
Shuriken_Logic_Exception::vote_limit_reached(10);
```

**Usage:**
```php
if ($rating->display_only) {
    throw Shuriken_Logic_Exception::display_only_rating();
}

if ($parent_id === $rating_id) {
    throw Shuriken_Logic_Exception::circular_reference();
}
```

### 7. `Shuriken_Configuration_Exception`

For plugin configuration and settings errors.

**Static Factory Methods:**
```php
Shuriken_Configuration_Exception::invalid_option('max_stars', $value, 'integer 1-10');
Shuriken_Configuration_Exception::missing_option('api_key');
Shuriken_Configuration_Exception::invalid_max_stars($value);
Shuriken_Configuration_Exception::invalid_effect_type($value);
Shuriken_Configuration_Exception::invalid_table('custom_table');
Shuriken_Configuration_Exception::conflicting_options('option1', 'option2');
```

**Additional Methods:**
- `get_config_key()` - Get the configuration key
- `get_config_value()` - Get the invalid value

**Usage:**
```php
$max_stars = get_option('shuriken_max_stars');
if (!$max_stars || $max_stars < 1) {
    throw Shuriken_Configuration_Exception::invalid_max_stars($max_stars);
}
```

### 8. `Shuriken_Rate_Limit_Exception`

For rate limiting and throttling (429 errors).

**Static Factory Methods:**
```php
Shuriken_Rate_Limit_Exception::voting_too_fast($retry_after, $limit);
Shuriken_Rate_Limit_Exception::daily_vote_limit($limit);
Shuriken_Rate_Limit_Exception::hourly_vote_limit($limit);
Shuriken_Rate_Limit_Exception::vote_cooldown($retry_after);
Shuriken_Rate_Limit_Exception::api_limit_exceeded($retry_after, $limit);
Shuriken_Rate_Limit_Exception::too_many_failures($action, $retry_after);
```

**Status:** Reserved for future implementation (v1.8.0+)

### 9. `Shuriken_Integration_Exception`

For external service and integration failures (502 errors).

**Working Factory Methods:**
```php
Shuriken_Integration_Exception::http_request_failed($url, $status, $error);
Shuriken_Integration_Exception::api_connection_failed($api_name, $error);
Shuriken_Integration_Exception::api_auth_failed($api_name);
Shuriken_Integration_Exception::service_timeout($service_name, $timeout);
```

**Reserved for Future:**
```php
Shuriken_Integration_Exception::webhook_failed($webhook_url, $error);
Shuriken_Integration_Exception::cache_failed($operation, $error);
Shuriken_Integration_Exception::email_failed($error);
Shuriken_Integration_Exception::plugin_dependency_missing($plugin_name);
Shuriken_Integration_Exception::plugin_version_incompatible($plugin, $current, $required);
```

## Exception Handler

The `Shuriken_Exception_Handler` class provides utilities for handling exceptions in WordPress contexts.

### AJAX Requests

```php
public function handle_ajax_request() {
    try {
        // Your code here
        if (!$rating) {
            throw Shuriken_Not_Found_Exception::rating($id);
        }
        
        wp_send_json_success($data);
    } catch (Shuriken_Exception $e) {
        Shuriken_Exception_Handler::handle_ajax_exception($e);
    }
}
```

### REST API Requests

```php
public function rest_endpoint($request) {
    try {
        // Your code here
        if (!current_user_can('manage_options')) {
            throw Shuriken_Permission_Exception::missing_capability('manage_options');
        }
        
        return rest_ensure_response($data);
    } catch (Shuriken_Exception $e) {
        return Shuriken_Exception_Handler::handle_rest_exception($e);
    }
}
```

### Admin Pages

```php
public function handle_form_submission() {
    try {
        // Your code here
        if (empty($_POST['rating_name'])) {
            throw Shuriken_Validation_Exception::required_field('rating_name');
        }
        
        // Redirect on success
        wp_safe_redirect($redirect_url);
    } catch (Shuriken_Exception $e) {
        Shuriken_Exception_Handler::handle_admin_exception($e, $redirect_url);
    }
}
```

### Safe Execution

For operations where you want a default value on error:

```php
$rating = Shuriken_Exception_Handler::safe_execute(
    function() use ($db, $id) {
        $rating = $db->get_rating($id);
        if (!$rating) {
            throw Shuriken_Not_Found_Exception::rating($id);
        }
        return $rating;
    },
    'Getting rating in widget',
    null // default value
);

if ($rating === null) {
    // Handle missing rating gracefully
}
```

## Best Practices

### 1. Use Specific Exceptions

```php
// ❌ Bad: Generic exception
throw new Exception('Rating not found');

// ✅ Good: Specific exception
throw Shuriken_Not_Found_Exception::rating($id);
```

### 2. Chain Exceptions

```php
try {
    $result = some_operation();
} catch (Exception $e) {
    throw new Shuriken_Database_Exception(
        'Failed to complete operation',
        'operation',
        $e // Pass previous exception
    );
}
```

### 3. Log Exceptions

```php
catch (Shuriken_Exception $e) {
    $e->log('User registration process');
    // Handle the error
}
```

### 4. Convert to WP_Error for WordPress APIs

```php
public function validate_data($data) {
    try {
        // Validation logic
        if (empty($data['name'])) {
            throw Shuriken_Validation_Exception::required_field('name');
        }
        return true;
    } catch (Shuriken_Exception $e) {
        return $e->to_wp_error();
    }
}
```

### 5. Use Factory Methods

Factory methods provide consistent error messages and codes:

```php
// ✅ Good: Factory method with consistent message
throw Shuriken_Not_Found_Exception::rating($id);

// ❌ Less ideal: Manual construction
throw new Shuriken_Not_Found_Exception("Rating $id not found", 'rating', $id);
```

## HTTP Status Codes

The exception handler automatically maps exceptions to HTTP status codes:

| Exception Type | HTTP Status |
|---------------|-------------|
| `Shuriken_Not_Found_Exception` | 404 |
| `Shuriken_Permission_Exception` | 403 |
| `Shuriken_Validation_Exception` | 400 |
| `Shuriken_Rate_Limit_Exception` | 429 |
| `Shuriken_Database_Exception` | 500 |
| Other | 500 |

## Example: Complete AJAX Handler

```php
public function handle_submit_rating() {
    try {
        // Validate nonce
        if (!check_ajax_referer('my-nonce', 'nonce', false)) {
            throw new Shuriken_Exception('Invalid nonce', 'invalid_nonce');
        }
        
        // Validate input
        if (!isset($_POST['rating_id'])) {
            throw Shuriken_Validation_Exception::required_field('rating_id');
        }
        
        $rating_id = intval($_POST['rating_id']);
        
        // Check if resource exists
        $rating = $db->get_rating($rating_id);
        if (!$rating) {
            throw Shuriken_Not_Found_Exception::rating($rating_id);
        }
        
        // Check permissions
        if (!is_user_logged_in()) {
            throw Shuriken_Permission_Exception::guest_not_allowed();
        }
        
        // Business logic
        if ($rating->display_only) {
            throw Shuriken_Logic_Exception::display_only_rating();
        }
        
        // Perform operation
        $result = $db->create_vote($rating_id, $value, $user_id);
        if (!$result) {
            throw Shuriken_Database_Exception::insert_failed('votes');
        }
        
        wp_send_json_success(['message' => 'Vote submitted']);
        
    } catch (Shuriken_Exception $e) {
        Shuriken_Exception_Handler::handle_ajax_exception($e);
    }
}
```

## Benefits

✅ **Clearer Error Handling** - Know exactly what went wrong  
✅ **Better Debugging** - Stack traces and error codes  
✅ **Type Safety** - Can catch specific exception types  
✅ **Consistent Errors** - Factory methods ensure consistent messages  
✅ **WordPress Compatible** - Converts to WP_Error when needed  
✅ **Easier Testing** - Can test that specific exceptions are thrown  

See [INDEX.md](../INDEX.md) for complete documentation index.

# Error Handling in FSE Blocks

This guide explains how error handling works in Shuriken Reviews FSE blocks and how the blocks integrate with the WordPress REST API error system.

## Overview

The Shuriken Reviews FSE blocks use a comprehensive error handling system that:
- Catches and displays API errors to users in a friendly way
- Provides retry functionality for failed operations
- Maps backend exceptions to user-friendly messages
- Logs errors to the console for debugging

## Error Flow

### 1. Backend Exceptions

When errors occur on the server side, they're handled by the `Shuriken_Exception_Handler` class:

```php
// In REST API endpoints
try {
    $result = shuriken_db()->create_rating($name, $parent_id);
} catch (Shuriken_Exception $e) {
    return Shuriken_Exception_Handler::handle_rest_exception($e);
}
```

The exception handler converts exceptions to WP_Error objects with appropriate HTTP status codes:
- `400` - Validation errors
- `403` - Permission errors
- `404` - Not found errors
- `429` - Rate limit errors
- `500` - Server/database errors

### 2. Frontend Error Handling

In the block editor, errors are caught and displayed using the `handleApiError` helper:

```javascript
function handleApiError(err, action) {
    // Parse error response
    var errorMessage = __('An unexpected error occurred.', 'shuriken-reviews');
    
    if (err.message) {
        errorMessage = err.message;
    } else if (err.data && err.data.message) {
        errorMessage = err.data.message;
    }
    
    // Map error codes to user-friendly messages
    if (err.code === 'rest_forbidden') {
        errorMessage = __('Permission denied. Please refresh the page and try again.', 'shuriken-reviews');
    }
    // ... more mappings
    
    setError(errorMessage);
    setLastFailedAction(action);
}
```

### 3. User Interface

Errors are displayed using WordPress `Notice` component:

```javascript
error && wp.element.createElement(
    Notice,
    {
        status: 'error',
        onRemove: dismissError,
        isDismissible: true,
        actions: lastFailedAction ? [
            {
                label: __('Retry', 'shuriken-reviews'),
                onClick: retryLastAction
            }
        ] : []
    },
    error
)
```

## Error Types and Messages

### Permission Errors
- **Code**: `rest_forbidden`, `rest_cookie_invalid_nonce`
- **User Message**: "Permission denied. Please refresh the page and try again."
- **Cause**: User doesn't have required capabilities or nonce expired
- **Solution**: Refresh page or login again

### Not Found Errors
- **Code**: `not_found`, `404`
- **User Message**: "The requested resource was not found."
- **Cause**: Rating or resource doesn't exist
- **Solution**: Select a different rating or refresh data

### Rate Limit Errors
- **Code**: `rate_limit_exceeded`, `429`
- **User Message**: "Too many requests. Please wait a moment and try again."
- **Cause**: Too many API calls in short period
- **Solution**: Wait and retry

### Validation Errors
- **Code**: `validation_error`, `400`
- **User Message**: Specific validation message from server
- **Cause**: Invalid input data
- **Solution**: Fix the input and try again

### Server Errors
- **Code**: `internal_server_error`, `500`
- **User Message**: "Server error. Please try again later."
- **Cause**: Database error or unexpected server issue
- **Solution**: Check server logs, retry later

### API Errors
- **Code**: `rest_no_route`
- **User Message**: "API endpoint not found. Please ensure the plugin is properly installed."
- **Cause**: Plugin not properly activated or endpoints not registered
- **Solution**: Deactivate and reactivate plugin

## Retry Functionality

The retry system allows users to re-attempt failed operations without re-entering data:

1. When an API call fails, the `lastFailedAction` is stored
2. A "Retry" button appears in the error notice
3. Clicking retry calls the stored action function again
4. Error is cleared before retry attempt

```javascript
function retryLastAction() {
    setError(null);
    if (lastFailedAction) {
        lastFailedAction();
        setLastFailedAction(null);
    }
}
```

## Best Practices

### For Developers

1. **Always provide retry actions** for transient errors (network, rate limit)
2. **Use specific error codes** from the backend exception system
3. **Log errors to console** for debugging while showing friendly messages to users
4. **Clear errors on success** to avoid stale error messages
5. **Test error scenarios** including network failures, permission errors, and validation errors

### Error Message Guidelines

1. **Be specific but friendly**: Avoid technical jargon
2. **Provide actionable guidance**: Tell users what to do next
3. **Use consistent language**: Follow WordPress UI patterns
4. **Translate all messages**: Use `__()` function for i18n
5. **Avoid exposing sensitive info**: Don't show database queries or file paths to non-admins

## Example: Complete Error Handling Flow

```javascript
function createNewParentRating() {
    if (!newParentName.trim() || creating) {
        return;
    }

    setCreating(true);
    
    apiFetch({
        path: '/shuriken-reviews/v1/ratings',
        method: 'POST',
        data: { name: newParentName, display_only: true }
    })
        .then(function (data) {
            // Success: update state and clear errors
            setRatings(prev => [data, ...prev]);
            setError(null);
            setCreating(false);
            setIsCreateModalOpen(false);
        })
        .catch(function (err) {
            // Error: show message with retry option
            setCreating(false);
            handleApiError(err, createNewParentRating);
        });
}
```

## Testing Error Handling

### Manual Testing Checklist

- [ ] Test with expired nonce (wait 12+ hours)
- [ ] Test without proper permissions (logged out or subscriber role)
- [ ] Test with invalid data (empty names, invalid IDs)
- [ ] Test with deleted/non-existent ratings
- [ ] Test with network disconnected
- [ ] Test retry functionality after each error type
- [ ] Test error dismissal
- [ ] Test that errors clear on subsequent successful operations

### Simulating Errors

You can simulate errors for testing using browser dev tools:

```javascript
// In console, override apiFetch to return errors
const originalFetch = wp.apiFetch;
wp.apiFetch = function(options) {
    if (options.path.includes('/ratings')) {
        return Promise.reject({
            code: 'rest_forbidden',
            message: 'Simulated permission error'
        });
    }
    return originalFetch(options);
};
```

## Related Documentation

- [Exception Handling Guide](exception-handling.md) - Backend exception system
- [REST API Reference](hooks-reference.md#rest-api) - API endpoints
- [Testing Guide](testing.md) - Testing strategies

## Troubleshooting

### Error not showing in UI
- Check browser console for JavaScript errors
- Verify Notice component is imported
- Ensure error state is set correctly

### Retry not working
- Verify action function is being stored
- Check that function doesn't require additional parameters
- Ensure action is cleared after successful retry

### Generic errors instead of specific ones
- Check REST API error response format
- Verify error code mapping in handleApiError
- Check backend exception handling
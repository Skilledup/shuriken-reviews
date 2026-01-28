# REST API Reference

Complete reference for all REST API endpoints provided by Shuriken Reviews.

## Overview

Shuriken Reviews provides a comprehensive REST API under the namespace `shuriken-reviews/v1`. The API enables programmatic access to ratings management, statistics, and authentication features.

**Base URL:** `/wp-json/shuriken-reviews/v1/`

---

## Authentication

Most endpoints require authentication. The API supports:

- **Cookie Authentication** - Automatically handled for logged-in users
- **Application Passwords** - WordPress native application passwords
- **Nonce-based Authentication** - For AJAX requests from the frontend

### Permission Levels

| Permission | Required Capability | Endpoints |
|------------|---------------------|-----------|
| Read | `edit_posts` | GET endpoints (except public) |
| Write | `manage_options` | POST, PUT, DELETE endpoints |
| Public | None | `/nonce`, `/ratings/stats` |

---

## Endpoints

### Ratings Collection

#### GET `/ratings`

Retrieve all ratings.

**Permission:** `edit_posts`

**Response:**
```json
[
  {
    "id": 1,
    "name": "Overall Rating",
    "parent_id": null,
    "mirror_of": null,
    "effect_type": "positive",
    "display_only": false,
    "average": 4.5,
    "total_votes": 120
  }
]
```

---

#### POST `/ratings`

Create a new rating.

**Permission:** `manage_options`

**Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `name` | string | Yes | - | Rating name |
| `parent_id` | integer | No | null | Parent rating ID for sub-ratings |
| `mirror_of` | integer | No | null | ID of rating to mirror |
| `effect_type` | string | No | `positive` | Effect type (`positive` or `negative`) |
| `display_only` | boolean | No | `false` | Whether rating is display-only |

**Example Request:**
```json
{
  "name": "Quality",
  "parent_id": 1,
  "effect_type": "positive"
}
```

**Response:** Returns the created rating object.

---

### Single Rating

#### GET `/ratings/{id}`

Retrieve a single rating by ID.

**Permission:** `edit_posts`

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Rating ID |

**Response:** Returns the rating object or 404 error.

---

#### PUT `/ratings/{id}`

Update an existing rating.

**Permission:** `manage_options`

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Rating ID |
| `name` | string | No | New rating name |
| `parent_id` | integer | No | New parent rating ID |
| `mirror_of` | integer | No | New mirror source ID |
| `effect_type` | string | No | New effect type |
| `display_only` | boolean | No | New display-only status |

**Response:** Returns the updated rating object.

---

#### DELETE `/ratings/{id}`

Delete a rating.

**Permission:** `manage_options`

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Rating ID |

**Response:**
```json
{
  "deleted": true,
  "id": 1
}
```

---

### Specialized Rating Queries

#### GET `/ratings/parents`

Retrieve all parent ratings (ratings without a parent_id).

**Permission:** `edit_posts`

**Response:** Array of parent rating objects.

---

#### GET `/ratings/mirrorable`

Retrieve all ratings that can be used as mirror sources.

**Permission:** `edit_posts`

**Response:** Array of mirrorable rating objects.

---

#### GET `/ratings/{id}/children`

Retrieve all child ratings of a parent rating.

**Permission:** `edit_posts`

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Parent rating ID |

**Response:** Array of child rating objects.

**Since:** 1.9.0

---

#### GET `/ratings/search`

Search ratings by name (for autocomplete functionality).

**Permission:** `edit_posts`

**Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `q` | string | No | `""` | Search term to match against rating names |
| `limit` | integer | No | `20` | Maximum results (1-100) |
| `type` | string | No | `all` | Filter type: `all`, `parents`, or `mirrorable` |

**Example:**
```
GET /wp-json/shuriken-reviews/v1/ratings/search?q=quality&limit=10&type=all
```

**Response:** Array of matching rating objects.

**Since:** 1.9.0

---

### Public Endpoints

These endpoints do not require authentication and are designed to bypass page caching.

#### GET `/ratings/stats`

Get fresh rating statistics for specified rating IDs.

**Permission:** Public (no authentication required)

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `ids` | string | Yes | Comma-separated list of rating IDs |

**Example:**
```
GET /wp-json/shuriken-reviews/v1/ratings/stats?ids=1,2,3
```

**Response:**
```json
{
  "1": {
    "average": 4.5,
    "total_votes": 120,
    "source_id": null
  },
  "2": {
    "average": 3.8,
    "total_votes": 85,
    "source_id": 1
  }
}
```

**Note:** This endpoint uses batch queries for efficiency and bypasses caching.

---

#### GET `/nonce`

Get a fresh nonce for AJAX requests (useful for cached pages with stale nonces).

**Permission:** Public (no authentication required)

**Response:**
```json
{
  "nonce": "abc123def456",
  "logged_in": true,
  "allow_guest_voting": false
}
```

**Note:** This endpoint sends `nocache_headers()` to prevent caching.

---

## Error Handling

The API uses WordPress `WP_Error` objects for error responses. Errors are handled through the plugin's exception system.

### Error Response Format

```json
{
  "code": "shuriken_not_found",
  "message": "Rating with ID 999 not found.",
  "data": {
    "status": 404
  }
}
```

### Common Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `shuriken_not_found` | 404 | Resource not found |
| `shuriken_validation_error` | 400 | Invalid input data |
| `shuriken_database_error` | 500 | Database operation failed |
| `rest_forbidden` | 403 | Permission denied |

---

## Usage Examples

### JavaScript (Fetch API)

```javascript
// Get all ratings (requires authentication)
fetch('/wp-json/shuriken-reviews/v1/ratings', {
  headers: {
    'X-WP-Nonce': wpApiSettings.nonce
  }
})
.then(response => response.json())
.then(ratings => console.log(ratings));
```

### JavaScript (Fresh Stats for Cached Pages)

```javascript
// Get fresh stats (public endpoint, no auth needed)
const ids = [1, 2, 3];
fetch(`/wp-json/shuriken-reviews/v1/ratings/stats?ids=${ids.join(',')}`)
.then(response => response.json())
.then(stats => {
  // Update UI with fresh stats
  Object.entries(stats).forEach(([id, data]) => {
    document.querySelector(`[data-rating-id="${id}"] .average`)
      .textContent = data.average;
  });
});
```

### PHP (WordPress HTTP API)

```php
// Create a rating programmatically
$response = wp_remote_post(
  rest_url('shuriken-reviews/v1/ratings'),
  array(
    'headers' => array(
      'X-WP-Nonce' => wp_create_nonce('wp_rest'),
      'Content-Type' => 'application/json',
    ),
    'body' => wp_json_encode(array(
      'name' => 'New Rating',
      'effect_type' => 'positive',
    )),
  )
);

if (!is_wp_error($response)) {
  $rating = json_decode(wp_remote_retrieve_body($response));
}
```

### cURL (Command Line)

```bash
# Get fresh nonce
curl -X GET "https://example.com/wp-json/shuriken-reviews/v1/nonce"

# Get rating stats
curl -X GET "https://example.com/wp-json/shuriken-reviews/v1/ratings/stats?ids=1,2,3"
```

---

## Best Practices

1. **Use Fresh Stats for Cached Sites** - When using page caching, call `/ratings/stats` on page load to get accurate vote counts.

2. **Refresh Nonces** - For long-lived pages, periodically call `/nonce` to get fresh authentication tokens.

3. **Batch Requests** - Use `/ratings/stats` with multiple IDs instead of individual calls for better performance.

4. **Error Handling** - Always check for `WP_Error` responses and handle them appropriately.

5. **Rate Limiting** - Be mindful of request frequency, especially for public endpoints.

---

## Version History

| Version | Changes |
|---------|---------|
| 1.9.0 | Added `/ratings/search` and `/ratings/{id}/children` endpoints |
| 1.7.0 | Initial REST API implementation |

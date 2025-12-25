# Shuriken Reviews Hooks Reference

This document lists all available hooks (actions and filters) in the Shuriken Reviews plugin. Use these hooks to extend and customize the plugin's functionality.

> **Version:** 1.7.0+

---

## Table of Contents

- [Filters](#filters)
  - [Rating Display](#rating-display-filters)
  - [Vote Submission](#vote-submission-filters)
  - [Database Operations](#database-filters)
  - [Frontend Assets](#frontend-filters)
- [Actions](#actions)
  - [Rating Display](#rating-display-actions)
  - [Vote Submission](#vote-submission-actions)
  - [Database Operations](#database-actions)

---

## Filters

### Rating Display Filters

#### `shuriken_rating_data`

Filters the rating data before rendering.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating` | object | The rating object |
| `$tag` | string | The HTML tag for the title |
| `$anchor_id` | string | The anchor ID |

**Example:**
```php
add_filter('shuriken_rating_data', function($rating, $tag, $anchor_id) {
    // Modify rating name
    $rating->name = strtoupper($rating->name);
    return $rating;
}, 10, 3);
```

---

#### `shuriken_rating_css_classes`

Filters the CSS classes for the rating container.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$css_classes` | string | The CSS classes string |
| `$rating` | object | The rating object |

**Example:**
```php
add_filter('shuriken_rating_css_classes', function($classes, $rating) {
    if ($rating->average >= 4) {
        $classes .= ' high-rated';
    }
    return $classes;
}, 10, 2);
```

---

#### `shuriken_rating_max_stars`

Filters the maximum number of stars displayed.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$max_stars` | int | Maximum number of stars (default: 5) |
| `$rating` | object | The rating object |

**Example:**
```php
add_filter('shuriken_rating_max_stars', function($max, $rating) {
    return 10; // Use 10-star rating system
}, 10, 2);
```

---

#### `shuriken_rating_star_symbol`

Filters the star character/symbol used in ratings.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$star_symbol` | string | The star symbol (default: '★') |
| `$rating` | object | The rating object |

**Example:**
```php
add_filter('shuriken_rating_star_symbol', function($symbol, $rating) {
    return '❤'; // Use hearts instead of stars
}, 10, 2);
```

---

#### `shuriken_rating_html`

Filters the complete rating HTML output.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$html` | string | The rendered HTML |
| `$rating` | object | The rating object |
| `$tag` | string | The HTML tag for the title |
| `$anchor_id` | string | The anchor ID |

**Example:**
```php
add_filter('shuriken_rating_html', function($html, $rating, $tag, $anchor_id) {
    // Wrap rating in custom container
    return '<div class="my-rating-wrapper">' . $html . '</div>';
}, 10, 4);
```

---

### Vote Submission Filters

#### `shuriken_allow_guest_voting`

Filters whether guest voting is allowed.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$allow_guest_voting` | bool | Whether guest voting is allowed |

**Example:**
```php
add_filter('shuriken_allow_guest_voting', function($allow) {
    // Allow guest voting only on weekends
    return date('N') >= 6;
});
```

---

#### `shuriken_min_rating_value`

Filters the minimum allowed rating value.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$min_value` | int | Minimum rating value (default: 1) |

---

#### `shuriken_max_rating_value`

Filters the maximum allowed rating value.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$max_value` | int | Maximum rating value (default: 5) |

---

#### `shuriken_can_submit_vote`

Filters whether the user can submit a vote. Return `false` to prevent the vote, or return a `WP_Error` for a custom error message.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$can_vote` | bool\|WP_Error | Whether the user can vote (default: true) |
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The rating value |
| `$user_id` | int | The user ID (0 for guests) |
| `$rating` | object | The rating object |

**Example:**
```php
add_filter('shuriken_can_submit_vote', function($can_vote, $rating_id, $value, $user_id, $rating) {
    // Prevent voting on archived ratings
    if (get_post_meta($rating_id, '_archived', true)) {
        return new WP_Error('archived', 'This rating is archived.');
    }
    return $can_vote;
}, 10, 5);
```

---

#### `shuriken_vote_response_data`

Filters the vote submission response data.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$response_data` | array | The response data |
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The rating value |
| `$is_update` | bool | Whether this was an update or new vote |
| `$updated_rating` | object | The updated rating object |

**Example:**
```php
add_filter('shuriken_vote_response_data', function($data, $rating_id, $value, $is_update, $rating) {
    $data['message'] = $is_update ? 'Vote updated!' : 'Thanks for voting!';
    return $data;
}, 10, 5);
```

---

### Database Filters

#### `shuriken_before_create_rating`

Filters the rating data before insertion.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$insert_data` | array | The data to insert |

**Example:**
```php
add_filter('shuriken_before_create_rating', function($data) {
    // Add custom default value
    $data['custom_field'] = 'default_value';
    return $data;
});
```

---

#### `shuriken_before_update_rating`

Filters the rating data before update.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$update_data` | array | The data to update |
| `$rating_id` | int | The rating ID |

---

### Frontend Filters

#### `shuriken_localized_data`

Filters the localized data passed to JavaScript.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$data` | array | The localized data array |

**Example:**
```php
add_filter('shuriken_localized_data', function($data) {
    $data['custom_setting'] = get_option('my_custom_setting');
    return $data;
});
```

---

#### `shuriken_i18n_strings`

Filters the i18n strings passed to JavaScript.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$strings` | array | The i18n strings array |

---

## Actions

### Rating Display Actions

#### `shuriken_after_rating_stats`

Fires after the rating stats, inside the rating wrapper. Use to add custom content after the stats display.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating` | object | The rating object |

**Example:**
```php
add_action('shuriken_after_rating_stats', function($rating) {
    if ($rating->total_votes >= 100) {
        echo '<div class="rating-badge">🔥 Popular!</div>';
    }
});
```

---

### Vote Submission Actions

#### `shuriken_before_submit_vote`

Fires before a vote is submitted.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The rating value |
| `$user_id` | int | The user ID (0 for guests) |
| `$user_ip` | string | The user IP (for guests) |
| `$rating` | object | The rating object |

**Example:**
```php
add_action('shuriken_before_submit_vote', function($rating_id, $value, $user_id, $ip, $rating) {
    // Log vote attempt
    error_log("Vote attempt: Rating $rating_id, Value $value, User $user_id");
}, 10, 5);
```

---

#### `shuriken_vote_created`

Fires after a new vote is created.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The rating value |
| `$user_id` | int | The user ID (0 for guests) |
| `$user_ip` | string | The user IP (for guests) |
| `$rating` | object | The rating object |

**Example:**
```php
add_action('shuriken_vote_created', function($rating_id, $value, $user_id, $ip, $rating) {
    // Send notification on new vote
    wp_mail('admin@example.com', 'New Vote', "Rating $rating_id received a $value star vote.");
}, 10, 5);
```

---

#### `shuriken_vote_updated`

Fires after a vote is updated.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$vote_id` | int | The vote ID |
| `$rating_id` | int | The rating ID |
| `$old_value` | int | The previous rating value |
| `$new_value` | int | The new rating value |
| `$user_id` | int | The user ID (0 for guests) |
| `$rating` | object | The rating object |

---

#### `shuriken_after_submit_vote`

Fires after a vote is successfully submitted.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The rating value |
| `$user_id` | int | The user ID (0 for guests) |
| `$is_update` | bool | Whether this was an update or new vote |
| `$updated_rating` | object | The updated rating object |

**Example:**
```php
add_action('shuriken_after_submit_vote', function($rating_id, $value, $user_id, $is_update, $rating) {
    // Clear cache after vote
    wp_cache_delete('rating_' . $rating_id, 'shuriken_reviews');
}, 10, 5);
```

---

### Database Actions

#### `shuriken_rating_created`

Fires after a rating is created.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The new rating ID |
| `$insert_data` | array | The inserted data |

**Example:**
```php
add_action('shuriken_rating_created', function($rating_id, $data) {
    // Log new rating creation
    error_log("New rating created: ID $rating_id, Name: {$data['name']}");
}, 10, 2);
```

---

#### `shuriken_rating_updated`

Fires after a rating is updated.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$update_data` | array | The updated data |

---

#### `shuriken_before_delete_rating`

Fires before a rating is deleted.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID about to be deleted |

**Example:**
```php
add_action('shuriken_before_delete_rating', function($rating_id) {
    // Backup rating data before deletion
    $rating = shuriken_db()->get_rating($rating_id);
    update_option('deleted_rating_backup_' . $rating_id, $rating);
});
```

---

#### `shuriken_rating_deleted`

Fires after a rating is deleted.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The deleted rating ID |

---

## Need Help?

- [GitHub Repository](https://github.com/qasedak/shuriken-reviews)
- [Report an Issue](https://github.com/qasedak/shuriken-reviews/issues)


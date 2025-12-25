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

Filters the rating object before it's rendered. Use this to modify any rating property (name, average, total_votes, etc.) before display.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating` | object | The rating object containing: `id`, `name`, `average`, `total_votes`, `display_only`, `mirror_of`, `source_id` |
| `$tag` | string | The HTML tag for the title (e.g., 'h2', 'h3') |
| `$anchor_id` | string | The anchor ID for linking |

**Example 1: Add a prefix to rating names**
```php
add_filter('shuriken_rating_data', function($rating, $tag, $anchor_id) {
    // Add emoji prefix to all rating names
    $rating->name = '⭐ ' . $rating->name;
    return $rating;
}, 10, 3);
```

**Example 2: Round average to whole numbers**
```php
add_filter('shuriken_rating_data', function($rating, $tag, $anchor_id) {
    // Display rounded averages instead of decimals
    $rating->average = round($rating->average);
    return $rating;
}, 10, 3);
```

---

#### `shuriken_rating_css_classes`

Filters the CSS classes applied to the rating container `<div>`. Use this to add custom classes for styling based on rating properties.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$css_classes` | string | Space-separated CSS classes (default includes: `shuriken-rating`, optionally `display-only`, `mirror-rating`) |
| `$rating` | object | The rating object |

**Example 1: Add class for high-rated items**
```php
add_filter('shuriken_rating_css_classes', function($classes, $rating) {
    // Add 'high-rated' class for ratings with average >= 4
    if ($rating->average >= 4) {
        $classes .= ' high-rated';
    }
    // Add 'popular' class for ratings with many votes
    if ($rating->total_votes >= 100) {
        $classes .= ' popular';
    }
    return $classes;
}, 10, 2);
```

**Example 2: Add class based on rating ID**
```php
add_filter('shuriken_rating_css_classes', function($classes, $rating) {
    // Add a unique class for specific ratings
    $classes .= ' rating-' . $rating->id;
    return $classes;
}, 10, 2);
```

---

#### `shuriken_rating_max_stars`

Filters the maximum number of stars displayed in the rating widget. By default, the plugin uses a 5-star system.

> **Note:** Changing this only affects the display. The actual vote values are still stored as 1-5 in the database. For a true 10-star system, you'd also need to filter `shuriken_max_rating_value`.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$max_stars` | int | Maximum number of stars (default: 5) |
| `$rating` | object | The rating object |

**Example: Use 10 stars for specific ratings**
```php
add_filter('shuriken_rating_max_stars', function($max, $rating) {
    // Use 10 stars only for ratings with "detailed" in the name
    if (strpos($rating->name, 'Detailed') !== false) {
        return 10;
    }
    return $max;
}, 10, 2);
```

---

#### `shuriken_rating_star_symbol`

Filters the character/symbol used for stars. By default, the plugin uses `★` (filled star).

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$star_symbol` | string | The star symbol (default: '★') |
| `$rating` | object | The rating object |

**Example 1: Use hearts for a "Love" rating**
```php
add_filter('shuriken_rating_star_symbol', function($symbol, $rating) {
    if (strpos(strtolower($rating->name), 'love') !== false) {
        return '❤';
    }
    return $symbol;
}, 10, 2);
```

**Example 2: Use different symbols based on rating type**
```php
add_filter('shuriken_rating_star_symbol', function($symbol, $rating) {
    // Use thumbs up for "Recommend" ratings
    if (strpos($rating->name, 'Recommend') !== false) {
        return '👍';
    }
    // Use fire for "Hot" ratings
    if (strpos($rating->name, 'Hot') !== false) {
        return '🔥';
    }
    return $symbol;
}, 10, 2);
```

---

#### `shuriken_rating_html`

Filters the complete HTML output of a rating. This is the final filter before the rating is displayed, allowing you to wrap it, modify it, or replace it entirely.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$html` | string | The complete rendered HTML |
| `$rating` | object | The rating object |
| `$tag` | string | The HTML tag for the title |
| `$anchor_id` | string | The anchor ID |

**Example 1: Wrap rating in a custom container**
```php
add_filter('shuriken_rating_html', function($html, $rating, $tag, $anchor_id) {
    return '<div class="my-custom-wrapper">' . $html . '</div>';
}, 10, 4);
```

**Example 2: Add a "Verified" badge for ratings with many votes**
```php
add_filter('shuriken_rating_html', function($html, $rating, $tag, $anchor_id) {
    if ($rating->total_votes >= 50) {
        $badge = '<span class="verified-badge">✓ Verified Rating</span>';
        // Insert badge before the closing div
        $html = str_replace('</div></div>', $badge . '</div></div>', $html);
    }
    return $html;
}, 10, 4);
```

---

### Vote Submission Filters

#### `shuriken_allow_guest_voting`

Filters whether non-logged-in users can submit votes. This overrides the admin setting.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$allow_guest_voting` | bool | Whether guest voting is allowed (from Settings) |

**Example 1: Always allow guest voting**
```php
add_filter('shuriken_allow_guest_voting', '__return_true');
```

**Example 2: Allow guest voting only during business hours**
```php
add_filter('shuriken_allow_guest_voting', function($allow) {
    $hour = (int) date('G'); // 0-23
    // Allow guest voting only between 9 AM and 6 PM
    return ($hour >= 9 && $hour < 18);
});
```

**Example 3: Allow guest voting only on specific pages**
```php
add_filter('shuriken_allow_guest_voting', function($allow) {
    // Allow guest voting only on single product pages
    if (function_exists('is_product') && is_product()) {
        return true;
    }
    return $allow;
});
```

---

#### `shuriken_min_rating_value`

Filters the minimum allowed rating value. Default is 1.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$min_value` | int | Minimum rating value (default: 1) |

**Example: Start ratings from 0**
```php
add_filter('shuriken_min_rating_value', function($min) {
    return 0;
});
```

---

#### `shuriken_max_rating_value`

Filters the maximum allowed rating value. Default is 5.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$max_value` | int | Maximum rating value (default: 5) |

**Example: Allow 10-star ratings**
```php
add_filter('shuriken_max_rating_value', function($max) {
    return 10;
});
```

---

#### `shuriken_can_submit_vote`

Filters whether a specific user can submit a vote for a specific rating. This is the most powerful filter for controlling voting permissions.

Return `true` to allow the vote, `false` to block it with a generic message, or a `WP_Error` object to block it with a custom error message.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$can_vote` | bool\|WP_Error | Whether the user can vote (default: true) |
| `$rating_id` | int | The rating ID being voted on |
| `$rating_value` | int | The star value (1-5) being submitted |
| `$user_id` | int | The user ID (0 for guests) |
| `$rating` | object | The rating object |

**Example 1: Require minimum user role**
```php
add_filter('shuriken_can_submit_vote', function($can_vote, $rating_id, $value, $user_id, $rating) {
    // Only allow users with 'subscriber' role or higher to vote
    if ($user_id === 0) {
        return new WP_Error('login_required', 'Please log in to vote.');
    }
    
    $user = get_user_by('id', $user_id);
    if (!$user || !in_array('subscriber', $user->roles)) {
        return new WP_Error('role_required', 'You need to be a subscriber to vote.');
    }
    
    return $can_vote;
}, 10, 5);
```

**Example 2: Limit votes per day**
```php
add_filter('shuriken_can_submit_vote', function($can_vote, $rating_id, $value, $user_id, $rating) {
    if ($user_id === 0) {
        return $can_vote; // Skip limit for guests
    }
    
    // Check how many votes user has made today
    $today = date('Y-m-d');
    $votes_today = get_user_meta($user_id, 'shuriken_votes_' . $today, true) ?: 0;
    
    if ($votes_today >= 10) {
        return new WP_Error('limit_reached', 'You have reached your daily voting limit (10 votes).');
    }
    
    return $can_vote;
}, 10, 5);

// Don't forget to increment the counter when vote is created
add_action('shuriken_vote_created', function($rating_id, $value, $user_id, $ip, $rating) {
    if ($user_id > 0) {
        $today = date('Y-m-d');
        $votes_today = get_user_meta($user_id, 'shuriken_votes_' . $today, true) ?: 0;
        update_user_meta($user_id, 'shuriken_votes_' . $today, $votes_today + 1);
    }
}, 10, 5);
```

**Example 3: Block voting on specific ratings**
```php
add_filter('shuriken_can_submit_vote', function($can_vote, $rating_id, $value, $user_id, $rating) {
    // Block voting on ratings that contain "Closed" in the name
    if (strpos($rating->name, 'Closed') !== false) {
        return new WP_Error('voting_closed', 'Voting is closed for this item.');
    }
    
    return $can_vote;
}, 10, 5);
```

**Example 4: Only allow voting during a specific time period**
```php
add_filter('shuriken_can_submit_vote', function($can_vote, $rating_id, $value, $user_id, $rating) {
    $start_date = strtotime('2024-01-01');
    $end_date = strtotime('2024-12-31');
    $now = time();
    
    if ($now < $start_date || $now > $end_date) {
        return new WP_Error('voting_period', 'Voting is only available during 2024.');
    }
    
    return $can_vote;
}, 10, 5);
```

---

#### `shuriken_vote_response_data`

Filters the AJAX response data sent back to the browser after a successful vote. Use this to add custom data to the response.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$response_data` | array | Response data containing: `new_average`, `new_total_votes`, and optionally `parent_id`, `parent_average`, `parent_total_votes` |
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The submitted rating value |
| `$is_update` | bool | `true` if user changed their existing vote, `false` if new vote |
| `$updated_rating` | object | The updated rating object |

**Example 1: Add a custom message**
```php
add_filter('shuriken_vote_response_data', function($data, $rating_id, $value, $is_update, $rating) {
    if ($is_update) {
        $data['custom_message'] = 'Your vote has been updated!';
    } else {
        $data['custom_message'] = 'Thank you for voting!';
    }
    return $data;
}, 10, 5);
```

**Example 2: Add ranking information**
```php
add_filter('shuriken_vote_response_data', function($data, $rating_id, $value, $is_update, $rating) {
    // Calculate this rating's rank among all ratings
    global $wpdb;
    $table = $wpdb->prefix . 'shuriken_ratings';
    $rank = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) + 1 FROM $table WHERE total_rating / NULLIF(total_votes, 0) > %f",
        $rating->average
    ));
    $data['rank'] = $rank;
    return $data;
}, 10, 5);
```

---

### Database Filters

#### `shuriken_before_create_rating`

Filters the rating data array before it's inserted into the database. Use this to modify or add data when a rating is created.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$insert_data` | array | Data to insert: `name`, `effect_type`, `display_only`, optionally `parent_id` or `mirror_of` |

**Example: Log all rating creations**
```php
add_filter('shuriken_before_create_rating', function($data) {
    error_log('Creating rating: ' . print_r($data, true));
    return $data;
});
```

---

#### `shuriken_before_update_rating`

Filters the rating data array before it's updated in the database.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$update_data` | array | Data to update (only fields being changed) |
| `$rating_id` | int | The rating ID being updated |

**Example: Prevent renaming certain ratings**
```php
add_filter('shuriken_before_update_rating', function($data, $rating_id) {
    // Prevent changing the name of rating ID 1
    if ($rating_id === 1 && isset($data['name'])) {
        unset($data['name']);
    }
    return $data;
}, 10, 2);
```

---

### Frontend Filters

#### `shuriken_localized_data`

Filters the JavaScript configuration object (`shurikenReviews`) that's passed to the frontend. Use this to add custom settings or modify existing ones.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$data` | array | Contains: `ajaxurl`, `rest_url`, `nonce`, `logged_in`, `allow_guest_voting`, `login_url`, `i18n` |

**Example 1: Add custom JavaScript settings**
```php
add_filter('shuriken_localized_data', function($data) {
    $data['animation_speed'] = 300;
    $data['show_confetti'] = true;
    return $data;
});
```

**Example 2: Change the login URL**
```php
add_filter('shuriken_localized_data', function($data) {
    // Use a custom login page
    $data['login_url'] = home_url('/my-login-page/');
    return $data;
});
```

---

#### `shuriken_i18n_strings`

Filters the translatable strings passed to JavaScript. Use this to customize the user-facing messages.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$strings` | array | Contains: `pleaseLogin`, `thankYou`, `averageRating`, `error`, `genericError` |

**Example: Customize the thank you message**
```php
add_filter('shuriken_i18n_strings', function($strings) {
    $strings['thankYou'] = '🎉 Thanks for your feedback!';
    $strings['genericError'] = 'Oops! Something went wrong. Please try again.';
    return $strings;
});
```

---

## Actions

### Rating Display Actions

#### `shuriken_after_rating_stats`

Fires after the rating stats are displayed, inside the rating wrapper div. Use this to add custom HTML content below the "Average: X/5 (Y votes)" text.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating` | object | The rating object |

**Example 1: Add a "Popular" badge**
```php
add_action('shuriken_after_rating_stats', function($rating) {
    if ($rating->total_votes >= 100) {
        echo '<div class="popularity-badge">🔥 Popular Choice!</div>';
    }
});
```

**Example 2: Show voting breakdown**
```php
add_action('shuriken_after_rating_stats', function($rating) {
    if ($rating->total_votes > 0) {
        echo '<div class="vote-breakdown">';
        echo '<small>Based on ' . $rating->total_votes . ' reviews</small>';
        echo '</div>';
    }
});
```

**Example 3: Add a "Share" button**
```php
add_action('shuriken_after_rating_stats', function($rating) {
    $url = urlencode(get_permalink());
    $text = urlencode('Check out this rating: ' . $rating->name);
    echo '<div class="share-rating">';
    echo '<a href="https://twitter.com/intent/tweet?url=' . $url . '&text=' . $text . '" target="_blank">Share on Twitter</a>';
    echo '</div>';
});
```

---

### Vote Submission Actions

#### `shuriken_before_submit_vote`

Fires immediately before a vote is processed. The vote has passed all validation checks at this point.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The star value (1-5) |
| `$user_id` | int | The user ID (0 for guests) |
| `$user_ip` | string | The user's IP address (only for guests) |
| `$rating` | object | The rating object |

**Example: Log all vote attempts**
```php
add_action('shuriken_before_submit_vote', function($rating_id, $value, $user_id, $ip, $rating) {
    $log = sprintf(
        '[%s] Vote attempt - Rating: %d (%s), Value: %d, User: %s',
        date('Y-m-d H:i:s'),
        $rating_id,
        $rating->name,
        $value,
        $user_id > 0 ? "User #$user_id" : "Guest ($ip)"
    );
    error_log($log);
}, 10, 5);
```

---

#### `shuriken_vote_created`

Fires after a NEW vote is successfully saved to the database. Does not fire for vote updates.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The star value (1-5) |
| `$user_id` | int | The user ID (0 for guests) |
| `$user_ip` | string | The user's IP address (only for guests) |
| `$rating` | object | The rating object (before the vote was counted) |

**Example 1: Send email notification for new votes**
```php
add_action('shuriken_vote_created', function($rating_id, $value, $user_id, $ip, $rating) {
    // Only notify for low ratings
    if ($value <= 2) {
        $subject = 'Low Rating Alert: ' . $rating->name;
        $message = sprintf(
            "A %d-star rating was submitted for '%s'.\n\nUser: %s\nTime: %s",
            $value,
            $rating->name,
            $user_id > 0 ? "User #$user_id" : "Guest",
            date('Y-m-d H:i:s')
        );
        wp_mail(get_option('admin_email'), $subject, $message);
    }
}, 10, 5);
```

**Example 2: Award points to users for voting**
```php
add_action('shuriken_vote_created', function($rating_id, $value, $user_id, $ip, $rating) {
    if ($user_id > 0) {
        // Award 5 points for voting (integrate with a points plugin)
        $current_points = get_user_meta($user_id, 'user_points', true) ?: 0;
        update_user_meta($user_id, 'user_points', $current_points + 5);
    }
}, 10, 5);
```

---

#### `shuriken_vote_updated`

Fires after an existing vote is changed. This happens when a user changes their rating.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$vote_id` | int | The vote record ID |
| `$rating_id` | int | The rating ID |
| `$old_value` | int | The previous star value |
| `$new_value` | int | The new star value |
| `$user_id` | int | The user ID (0 for guests) |
| `$rating` | object | The rating object |

**Example: Track vote changes**
```php
add_action('shuriken_vote_updated', function($vote_id, $rating_id, $old_value, $new_value, $user_id, $rating) {
    $log = sprintf(
        'Vote changed: Rating "%s" - User #%d changed from %d to %d stars',
        $rating->name,
        $user_id,
        $old_value,
        $new_value
    );
    error_log($log);
}, 10, 6);
```

---

#### `shuriken_after_submit_vote`

Fires after any vote (new or update) is successfully processed. This is the best place for post-vote cleanup or notifications.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | int | The star value (1-5) |
| `$user_id` | int | The user ID (0 for guests) |
| `$is_update` | bool | `true` if vote was updated, `false` if new |
| `$updated_rating` | object | The rating object with updated totals |

**Example 1: Clear cache after voting**
```php
add_action('shuriken_after_submit_vote', function($rating_id, $value, $user_id, $is_update, $rating) {
    // Clear any cached rating data
    wp_cache_delete('shuriken_rating_' . $rating_id);
    
    // If using a caching plugin, clear the page cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
}, 10, 5);
```

**Example 2: Update a "trending" list**
```php
add_action('shuriken_after_submit_vote', function($rating_id, $value, $user_id, $is_update, $rating) {
    // Get current trending list
    $trending = get_option('shuriken_trending_ratings', []);
    
    // Add/update this rating with timestamp
    $trending[$rating_id] = time();
    
    // Keep only last 24 hours
    $trending = array_filter($trending, function($time) {
        return $time > (time() - 86400);
    });
    
    update_option('shuriken_trending_ratings', $trending);
}, 10, 5);
```

---

### Database Actions

#### `shuriken_rating_created`

Fires after a new rating is created in the database (via admin or REST API).

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The new rating ID |
| `$insert_data` | array | The data that was inserted |

**Example: Set up default meta for new ratings**
```php
add_action('shuriken_rating_created', function($rating_id, $data) {
    // Store creation metadata
    update_option('shuriken_rating_' . $rating_id . '_created_by', get_current_user_id());
    update_option('shuriken_rating_' . $rating_id . '_created_at', current_time('mysql'));
}, 10, 2);
```

---

#### `shuriken_rating_updated`

Fires after a rating is updated in the database.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$update_data` | array | The data that was updated |

**Example: Log rating changes**
```php
add_action('shuriken_rating_updated', function($rating_id, $data) {
    $user = wp_get_current_user();
    $log = sprintf(
        '[%s] Rating #%d updated by %s. Changes: %s',
        date('Y-m-d H:i:s'),
        $rating_id,
        $user->user_login,
        json_encode($data)
    );
    error_log($log);
}, 10, 2);
```

---

#### `shuriken_before_delete_rating`

Fires just before a rating is deleted. Use this to backup data or perform cleanup.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID about to be deleted |

**Example: Backup rating before deletion**
```php
add_action('shuriken_before_delete_rating', function($rating_id) {
    // Get the rating data before it's deleted
    $rating = shuriken_db()->get_rating($rating_id);
    
    if ($rating) {
        // Store in a backup option
        $backups = get_option('shuriken_deleted_ratings_backup', []);
        $backups[] = [
            'rating' => $rating,
            'deleted_at' => current_time('mysql'),
            'deleted_by' => get_current_user_id()
        ];
        update_option('shuriken_deleted_ratings_backup', $backups);
    }
});
```

---

#### `shuriken_rating_deleted`

Fires after a rating and its votes have been deleted from the database.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The deleted rating ID |

**Example: Clean up related data**
```php
add_action('shuriken_rating_deleted', function($rating_id) {
    // Clean up any custom options related to this rating
    delete_option('shuriken_rating_' . $rating_id . '_created_by');
    delete_option('shuriken_rating_' . $rating_id . '_created_at');
    
    // Log the deletion
    error_log('Rating #' . $rating_id . ' was deleted');
});
```

---

## Common Use Cases

### Restrict Voting to Logged-in Users Only

```php
// Override the guest voting setting
add_filter('shuriken_allow_guest_voting', '__return_false');
```

### Add Custom Styling Based on Rating Value

```php
add_filter('shuriken_rating_css_classes', function($classes, $rating) {
    if ($rating->average >= 4.5) {
        $classes .= ' excellent-rating';
    } elseif ($rating->average >= 3.5) {
        $classes .= ' good-rating';
    } elseif ($rating->average >= 2.5) {
        $classes .= ' average-rating';
    } else {
        $classes .= ' poor-rating';
    }
    return $classes;
}, 10, 2);
```

### Send Slack Notification on New Votes

```php
add_action('shuriken_vote_created', function($rating_id, $value, $user_id, $ip, $rating) {
    $webhook_url = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    $message = sprintf(
        '⭐ New %d-star rating for "%s"',
        $value,
        $rating->name
    );
    
    wp_remote_post($webhook_url, [
        'body' => json_encode(['text' => $message]),
        'headers' => ['Content-Type' => 'application/json']
    ]);
}, 10, 5);
```

---

## Need Help?

- [GitHub Repository](https://github.com/qasedak/shuriken-reviews)
- [Report an Issue](https://github.com/qasedak/shuriken-reviews/issues)

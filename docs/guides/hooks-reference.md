# Shuriken Reviews Hooks Reference

This document lists all available hooks (actions and filters) in the Shuriken Reviews plugin. Use these hooks to extend and customize the plugin's functionality.

> **Version:** 1.10.0+

> **Note:** All rating display hooks work consistently for both **Shortcodes** (`[shuriken_rating]`) and **Gutenberg Blocks**. The block renderer uses the same underlying render method as shortcodes, ensuring your customizations apply everywhere.

---

## Table of Contents

- [Filters](#filters)
  - [Rating Display](#rating-display-filters)
  - [Vote Submission](#vote-submission-filters)
  - [Rate Limiting](#rate-limiting-filters)
  - [Database Operations](#database-filters)
  - [Frontend Assets](#frontend-filters)
- [Actions](#actions)
  - [Rating Display](#rating-display-actions)
  - [Vote Submission](#vote-submission-actions)
  - [Rate Limiting](#rate-limiting-actions)
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

Filters the maximum number of stars displayed AND accepted for voting. By default, the plugin uses a 5-star system.

> **How it works:** When you change this to 10 stars, users can click any of the 10 stars. The vote is automatically **normalized to a 1-5 scale** for storage. For example:
> - User clicks star 8 out of 10 → stored as `4.0` (8/10 × 5)
> - User clicks star 3 out of 10 → stored as `1.5` (3/10 × 5)
> 
> The average is then **scaled back** for display. An average of `4.0` displays as "8/10" on a 10-star rating.
> 
> This approach ensures all existing votes remain valid and no database changes are needed.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$max_stars` | int | Maximum number of stars (default: 5) |
| `$rating` | object | The rating object |

**Example 1: Use 10 stars for all ratings**
```php
add_filter('shuriken_rating_max_stars', function($max, $rating) {
    return 10;
}, 10, 2);
```

**Example 2: Use 10 stars only for specific ratings**
```php
add_filter('shuriken_rating_max_stars', function($max, $rating) {
    // Use 10 stars only for ratings with "Detailed" in the name
    if (strpos($rating->name, 'Detailed') !== false) {
        return 10;
    }
    return $max;
}, 10, 2);
```

**Example 3: Use 3 stars (simple thumbs up/neutral/down style)**
```php
add_filter('shuriken_rating_max_stars', function($max, $rating) {
    return 3;
}, 10, 2);

// Optionally use custom symbols
add_filter('shuriken_rating_star_symbol', function($symbol, $rating) {
    return '●'; // Simple circles
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
add_action('shuriken_vote_created', function($rating_id, $value, $normalized, $user_id, $ip, $rating, $max_stars) {
    if ($user_id > 0) {
        $today = date('Y-m-d');
        $votes_today = get_user_meta($user_id, 'shuriken_votes_' . $today, true) ?: 0;
        update_user_meta($user_id, 'shuriken_votes_' . $today, $votes_today + 1);
    }
}, 10, 7);
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

### Rate Limiting Filters

> **Since:** 1.10.0

#### `shuriken_rate_limit_settings`

Filters the rate limit settings before they are applied. Use this to dynamically adjust limits based on time, user role, or other conditions.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$settings` | array | Rate limit settings: `enabled`, `cooldown`, `hourly_limit`, `daily_limit` |
| `$user_id` | int | User ID (0 for guests) |
| `$is_guest` | bool | Whether the user is a guest |

**Example 1: Stricter limits during peak hours**
```php
add_filter('shuriken_rate_limit_settings', function($settings, $user_id, $is_guest) {
    $hour = (int) date('G'); // 0-23
    
    // Stricter limits between 9 AM and 5 PM
    if ($hour >= 9 && $hour < 17) {
        $settings['hourly_limit'] = 15;
        $settings['daily_limit'] = 50;
    }
    
    return $settings;
}, 10, 3);
```

**Example 2: Higher limits for premium users**
```php
add_filter('shuriken_rate_limit_settings', function($settings, $user_id, $is_guest) {
    if ($user_id > 0 && user_can($user_id, 'premium_member')) {
        $settings['hourly_limit'] = 100;
        $settings['daily_limit'] = 500;
        $settings['cooldown'] = 0; // No cooldown
    }
    return $settings;
}, 10, 3);
```

---

#### `shuriken_bypass_rate_limit`

Filters whether a user should bypass rate limiting entirely. By default, administrators bypass rate limits.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$bypass` | bool | Whether to bypass rate limiting (default: true for admins) |
| `$user_id` | int | User ID (0 for guests) |
| `$user_ip` | string\|null | User IP address |

**Example 1: Allow editors to bypass**
```php
add_filter('shuriken_bypass_rate_limit', function($bypass, $user_id, $user_ip) {
    if ($user_id > 0 && user_can($user_id, 'edit_posts')) {
        return true;
    }
    return $bypass;
}, 10, 3);
```

**Example 2: Whitelist specific IPs**
```php
add_filter('shuriken_bypass_rate_limit', function($bypass, $user_id, $user_ip) {
    $whitelisted_ips = ['192.168.1.100', '10.0.0.50'];
    
    if (in_array($user_ip, $whitelisted_ips)) {
        return true;
    }
    return $bypass;
}, 10, 3);
```

---

#### `shuriken_rate_limit_check_result`

Filters the final result of a rate limit check. Use this to implement custom rate limiting logic or to override the built-in checks.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$can_vote` | bool\|WP_Error | Whether the vote is allowed (default: true after passing all checks) |
| `$user_id` | int | User ID (0 for guests) |
| `$user_ip` | string\|null | User IP address |
| `$rating_id` | int | Rating ID being voted on |
| `$limits` | array | Current rate limit settings |
| `$usage` | array | Current usage statistics: `hourly_votes`, `daily_votes` |

**Example: Block votes from suspicious patterns**
```php
add_filter('shuriken_rate_limit_check_result', function($can_vote, $user_id, $user_ip, $rating_id, $limits, $usage) {
    // Block if user is voting too consistently (potential bot)
    if ($usage['hourly_votes'] > 5) {
        // Add your bot detection logic here
        if (is_suspicious_voting_pattern($user_id, $rating_id)) {
            return new WP_Error('suspicious_activity', 'Unusual voting pattern detected.');
        }
    }
    return $can_vote;
}, 10, 6);
```

---

#### `shuriken_get_user_ip`

Filters the detected user IP address. Use this to customize IP detection for sites behind load balancers or CDNs like Cloudflare.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$ip` | string | Detected IP address |

**Example: Use Cloudflare's real IP header**
```php
add_filter('shuriken_get_user_ip', function($ip) {
    // Cloudflare passes the real IP in CF-Connecting-IP header
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $cf_ip = sanitize_text_field($_SERVER['HTTP_CF_CONNECTING_IP']);
        if (filter_var($cf_ip, FILTER_VALIDATE_IP)) {
            return $cf_ip;
        }
    }
    return $ip;
});
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
| `$rating_value` | float | The star value in display scale (e.g., 8 for 8/10 stars) |
| `$normalized_value` | float | The normalized value (1-5 scale) that will be stored |
| `$user_id` | int | The user ID (0 for guests) |
| `$user_ip` | string | The user's IP address (only for guests) |
| `$rating` | object | The rating object |
| `$max_stars` | int | The maximum stars for this rating |

**Example: Log all vote attempts**
```php
add_action('shuriken_before_submit_vote', function($rating_id, $value, $normalized, $user_id, $ip, $rating, $max_stars) {
    $log = sprintf(
        '[%s] Vote attempt - Rating: %d (%s), Value: %s/%d (normalized: %s), User: %s',
        date('Y-m-d H:i:s'),
        $rating_id,
        $rating->name,
        $value,
        $max_stars,
        $normalized,
        $user_id > 0 ? "User #$user_id" : "Guest ($ip)"
    );
    error_log($log);
}, 10, 7);
```

---

#### `shuriken_vote_created`

Fires after a NEW vote is successfully saved to the database. Does not fire for vote updates.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | float | The star value in display scale |
| `$normalized_value` | float | The normalized value (1-5 scale) that was stored |
| `$user_id` | int | The user ID (0 for guests) |
| `$user_ip` | string | The user's IP address (only for guests) |
| `$rating` | object | The rating object (before the vote was counted) |
| `$max_stars` | int | The maximum stars for this rating |

**Example 1: Send email notification for low ratings**
```php
add_action('shuriken_vote_created', function($rating_id, $value, $normalized, $user_id, $ip, $rating, $max_stars) {
    // Notify for ratings in the bottom 40% of the scale
    $threshold = $max_stars * 0.4;
    if ($value <= $threshold) {
        $subject = 'Low Rating Alert: ' . $rating->name;
        $message = sprintf(
            "A %s/%d star rating was submitted for '%s'.\n\nUser: %s\nTime: %s",
            $value,
            $max_stars,
            $rating->name,
            $user_id > 0 ? "User #$user_id" : "Guest",
            date('Y-m-d H:i:s')
        );
        wp_mail(get_option('admin_email'), $subject, $message);
    }
}, 10, 7);
```

**Example 2: Award points to users for voting**
```php
add_action('shuriken_vote_created', function($rating_id, $value, $normalized, $user_id, $ip, $rating, $max_stars) {
    if ($user_id > 0) {
        // Award 5 points for voting (integrate with a points plugin)
        $current_points = get_user_meta($user_id, 'user_points', true) ?: 0;
        update_user_meta($user_id, 'user_points', $current_points + 5);
    }
}, 10, 7);
```

---

#### `shuriken_vote_updated`

Fires after an existing vote is changed. This happens when a user changes their rating.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$vote_id` | int | The vote record ID |
| `$rating_id` | int | The rating ID |
| `$old_value` | float | The previous normalized value (1-5 scale) |
| `$new_value` | float | The new value in display scale |
| `$normalized_value` | float | The new normalized value (1-5 scale) |
| `$user_id` | int | The user ID (0 for guests) |
| `$rating` | object | The rating object |
| `$max_stars` | int | The maximum stars for this rating |

**Example: Track vote changes**
```php
add_action('shuriken_vote_updated', function($vote_id, $rating_id, $old_norm, $new_value, $new_norm, $user_id, $rating, $max_stars) {
    // Convert old normalized value to display scale for logging
    $old_display = ($old_norm / 5) * $max_stars;
    $log = sprintf(
        'Vote changed: Rating "%s" - User #%d changed from %s to %s (out of %d)',
        $rating->name,
        $user_id,
        $old_display,
        $new_value,
        $max_stars
    );
    error_log($log);
}, 10, 8);
```

---

#### `shuriken_after_submit_vote`

Fires after any vote (new or update) is successfully processed. This is the best place for post-vote cleanup or notifications.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$rating_id` | int | The rating ID |
| `$rating_value` | float | The star value in display scale |
| `$normalized_value` | float | The normalized value (1-5 scale) |
| `$user_id` | int | The user ID (0 for guests) |
| `$is_update` | bool | `true` if vote was updated, `false` if new |
| `$updated_rating` | object | The rating object with updated totals |
| `$max_stars` | int | The maximum stars for this rating |

**Example 1: Clear cache after voting**
```php
add_action('shuriken_after_submit_vote', function($rating_id, $value, $normalized, $user_id, $is_update, $rating, $max_stars) {
    // Clear any cached rating data
    wp_cache_delete('shuriken_rating_' . $rating_id);
    
    // If using a caching plugin, clear the page cache
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
}, 10, 7);
```

**Example 2: Update a "trending" list**
```php
add_action('shuriken_after_submit_vote', function($rating_id, $value, $normalized, $user_id, $is_update, $rating, $max_stars) {
    // Get current trending list
    $trending = get_option('shuriken_trending_ratings', []);
    
    // Add/update this rating with timestamp
    $trending[$rating_id] = time();
    
    // Keep only last 24 hours
    $trending = array_filter($trending, function($time) {
        return $time > (time() - 86400);
    });
    
    update_option('shuriken_trending_ratings', $trending);
}, 10, 7);
```

---

### Rate Limiting Actions

> **Since:** 1.10.0

#### `shuriken_before_rate_limit_check`

Fires before rate limit checks are performed. Useful for logging or analytics.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$user_id` | int | User ID (0 for guests) |
| `$user_ip` | string\|null | User IP address |
| `$rating_id` | int | Rating ID being voted on |

**Example: Log rate limit check attempts**
```php
add_action('shuriken_before_rate_limit_check', function($user_id, $user_ip, $rating_id) {
    error_log(sprintf(
        '[Rate Limit Check] User: %d, IP: %s, Rating: %d',
        $user_id,
        $user_ip ?? 'N/A',
        $rating_id
    ));
}, 10, 3);
```

---

#### `shuriken_rate_limit_exceeded`

Fires when a rate limit is exceeded. Use this for logging, analytics, notifications, or triggering security measures.

**Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `$type` | string | Type of limit exceeded: `cooldown`, `hourly`, or `daily` |
| `$user_id` | int | User ID (0 for guests) |
| `$user_ip` | string\|null | User IP address |
| `$retry_after` | int | Seconds until limit resets |

**Example 1: Log rate limit violations**
```php
add_action('shuriken_rate_limit_exceeded', function($type, $user_id, $user_ip, $retry_after) {
    error_log(sprintf(
        '[Rate Limit Exceeded] Type: %s, User: %d, IP: %s, Retry after: %d seconds',
        $type,
        $user_id,
        $user_ip ?? 'N/A',
        $retry_after
    ));
}, 10, 4);
```

**Example 2: Track abuse patterns**
```php
add_action('shuriken_rate_limit_exceeded', function($type, $user_id, $user_ip, $retry_after) {
    // Track violations per IP
    $key = 'shuriken_violations_' . md5($user_ip);
    $violations = get_transient($key) ?: 0;
    set_transient($key, $violations + 1, DAY_IN_SECONDS);
    
    // Alert on excessive violations
    if ($violations > 10) {
        // Trigger security measure (e.g., temporary ban, CAPTCHA requirement)
        do_action('shuriken_potential_abuse_detected', $user_ip, $violations);
    }
}, 10, 4);
```

**Example 3: Send Slack notification on abuse**
```php
add_action('shuriken_rate_limit_exceeded', function($type, $user_id, $user_ip, $retry_after) {
    // Only notify on repeated daily limit violations
    if ($type !== 'daily') {
        return;
    }
    
    $webhook_url = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    $message = sprintf(
        '⚠️ Rate limit exceeded: %s limit hit by User %d (IP: %s)',
        $type,
        $user_id,
        $user_ip ?? 'unknown'
    );
    
    wp_remote_post($webhook_url, [
        'body' => json_encode(['text' => $message]),
        'headers' => ['Content-Type' => 'application/json']
    ]);
}, 10, 4);
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

### Use 10-Star Rating System

```php
// Display 10 stars instead of 5
// Votes are automatically normalized (e.g., 8/10 → 4/5 internally)
add_filter('shuriken_rating_max_stars', function($max, $rating) {
    return 10;
}, 10, 2);
```

### Add Custom Styling Based on Rating Value

```php
add_filter('shuriken_rating_css_classes', function($classes, $rating) {
    // Note: $rating->average is always in 1-5 scale (normalized)
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
add_action('shuriken_vote_created', function($rating_id, $value, $normalized, $user_id, $ip, $rating, $max_stars) {
    $webhook_url = 'https://hooks.slack.com/services/YOUR/WEBHOOK/URL';
    
    $message = sprintf(
        '⭐ New %s/%d star rating for "%s"',
        $value,
        $max_stars,
        $rating->name
    );
    
    wp_remote_post($webhook_url, [
        'body' => json_encode(['text' => $message]),
        'headers' => ['Content-Type' => 'application/json']
    ]);
}, 10, 7);
```

---

## Need Help?

- [GitHub Repository](https://github.com/Skilledup/shuriken-reviews)
- [Report an Issue](https://github.com/Skilledup/shuriken-reviews/issues)

See [INDEX.md](../INDEX.md) for complete documentation index.

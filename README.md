# Shuriken Reviews

Shuriken Reviews is a powerful and flexible WordPress plugin that enhances your website with a comprehensive rating system and improved comment functionality.

![Version](https://img.shields.io/badge/version-1.5.7-blue)
![License](https://img.shields.io/badge/license-GPL--2.0%2B-green)
![WordPress](https://img.shields.io/badge/WordPress-5.6%2B-blue)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple)

## Features


### Rating System

- **Unlimited Ratings**: Create as many ratings as you need for any content
- **Parent-Child Relationships**: Organize ratings hierarchically with parent and sub-ratings
- **Mirror Ratings**: Link ratings together so votes are synchronized
- **Effect Types**: Configure positive or negative effect on parent ratings
- **Display-Only Ratings**: Create aggregate ratings calculated from sub-ratings
- **Guest Voting**: Optional support for non-logged-in users to submit ratings
- **Cache-Proof Voting**: Always displays up-to-date ratings and allows voting even with full-page caching (see below)


### Integration Options

- **FSE Block**: Full Site Editor block for seamless Gutenberg integration
- **Shortcode**: Display ratings anywhere with `[shuriken_rating]`
- **AJAX Submissions**: Smooth rating submissions without page reloads
- **REST API Endpoints**: Fetches fresh rating data and nonces to bypass cache issues
### Caching & Nonce Handling

**Shuriken Reviews is fully compatible with aggressive page caching and CDNs.**

- On every page load, the plugin's JavaScript fetches fresh rating statistics and a valid security nonce via the WordPress REST API, ensuring users always see the latest data and can vote successfully.
- If a user votes and the nonce is expired (due to caching), the plugin automatically fetches a new nonce and retries the vote.
- REST endpoints:
   - `GET /wp-json/shuriken-reviews/v1/ratings/stats?ids=1,2,3` — Returns up-to-date stats for one or more ratings
   - `GET /wp-json/shuriken-reviews/v1/nonce` — Returns a fresh nonce for secure voting

**No special cache configuration is required.**

### Analytics Dashboard

- **Comprehensive Statistics**: Track total ratings, votes, and averages
- **Date Range Filtering**: View stats for custom time periods
- **Charts & Visualizations**: Visual representation of rating trends
- **Export to CSV**: Download your ratings data for external analysis
- **Top Performers**: Identify highest-rated and most-voted items
- **Recent Activity**: Monitor the latest voting activity

### Comments Enhancement

- **Customizable Latest Comments Block**: Filter out author and/or reply comments
- **Flexible Configuration**: Easy settings to control comment display

### User Experience

- **Responsive Design**: Looks great on all devices
- **Accessibility**: Full keyboard navigation and screen reader support
- **RTL Support**: Right-to-left language support
- **Translation Ready**: Fully internationalized with .pot file included

## Installation

1. Upload the `shuriken-reviews` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to 'Shuriken Reviews' in the admin menu to get started

## Usage

### Admin Menu

The plugin adds a **Shuriken Reviews** menu to your WordPress admin with the following pages:

- **Ratings**: Create and manage all your ratings
- **Comments Settings**: Configure comment display options
- **Analytics**: View statistics and insights
- **Settings**: Configure plugin options like guest voting
- **About**: Plugin information, quick start guide, and documentation

### Creating Ratings

1. Navigate to **Shuriken Reviews > Ratings** in WordPress admin
2. Fill in the rating details:
   - **Name**: The display name for your rating
   - **Parent Rating**: Optional parent for hierarchical structure
   - **Effect Type**: Positive or negative effect on parent
   - **Mirror Of**: Link to another rating to share votes
   - **Display Only**: Check to make it a calculated aggregate
3. Click **Create Rating**

### FSE Block (Recommended)

The **Shuriken Rating** block can be added in the WordPress block editor or Full Site Editor:

1. Add a new block and search for "Shuriken Rating"
2. Select an existing rating from the searchable dropdown
3. Optionally create a new rating directly from the block
4. Configure options in the block sidebar:
   - **Select Rating**: Choose from existing ratings
   - **Create New Rating**: Add a new rating without leaving the editor
   - **Title Tag**: Choose the HTML heading tag (h1-h6, div, p, span)
   - **Anchor ID**: Optional ID for linking to this rating

### Shortcode

Use `[shuriken_rating]` to display ratings anywhere:

| Parameter | Description | Default | Options |
|-----------|-------------|---------|---------|
| `id` | Rating ID (required) | - | Any valid rating ID |
| `tag` | HTML tag for title | `h2` | h1, h2, h3, h4, h5, h6, div, p, span |
| `anchor_tag` | Anchor ID for linking | - | Any valid HTML ID |

**Examples:**

```shortcode
[shuriken_rating id="1"]
[shuriken_rating id="1" tag="h3"]
[shuriken_rating id="1" tag="h4" anchor_tag="product-rating"]
```

### Analytics

Access detailed statistics at **Shuriken Reviews > Analytics**:

- **Overview Cards**: Total ratings, votes, averages, and unique voters
- **Time Period Filter**: Last 7/30/90/365 days, all time, or custom range
- **Top Rated Items**: See your best-performing ratings
- **Most Voted**: Identify the most popular items
- **Vote Distribution**: Visual breakdown of rating values
- **Votes Over Time**: Trend chart of voting activity
- **Export**: Download all data as CSV

### Settings

Configure plugin behavior at **Shuriken Reviews > Settings**:

- **Guest Voting**: Allow non-logged-in users to vote (tracked by IP)

## Requirements

- WordPress 5.6 or higher
- PHP 7.4 or higher

## Changelog


### 1.6.0

- **Cache-bypass for ratings**: Always shows fresh rating data and valid nonce, even with full-page caching or CDNs
- **Automatic nonce refresh**: Voting never fails due to expired nonce; plugin fetches a new one and retries automatically
- **REST API endpoints**: Public endpoints for fetching live rating stats and nonces

### 1.5.11

- Now Shuriken Rating block preview follows Front-end

### 1.5.10

- Fixed, Now Shuriken Rating block fetchs Display only checkbox state

### 1.5.9
- Ratings and Analytics page Styles modernized.

### 1.5.8
- Added About page with quick start guide and documentation

### 1.5.7
- Performance improvements and bug fixes

### 1.5.0
- Added Analytics dashboard with comprehensive statistics
- Added CSV export functionality
- Added date range filtering

### 1.4.0
- Added parent-child rating relationships
- Added mirror ratings feature
- Added display-only aggregate ratings

### 1.3.0
- Added guest voting support
- Enhanced admin interface

### 1.2.0
- Added Comments Settings page
- Improved accessibility

### 1.1.0
- Added FSE Block support
- Added shortcode functionality

### 1.0.0
- Initial release

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

Developed with ❤️ by [Skilledup Hub](https://skilledup.ir).

## Support

For support and feature requests, please visit our [GitHub repository](https://github.com/qasedak/shuriken-reviews).

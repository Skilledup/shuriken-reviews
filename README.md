# Shuriken Reviews

Shuriken Reviews is a WordPress plugin that enhances the comment section with additional functionalities, including rating systems and improved comment display.

## Features

- **Rating System**: Allows users to rate anything, anywhere on your website.
- **FSE Block**: Full Site Editor block for easy rating integration in the block editor.
- **Admin Settings**: Manage ratings from the WordPress admin dashboard.
- **Shortcode**: Display ratings using the `[shuriken_rating]` shortcode.
- **AJAX Rating Submission**: Users can submit ratings without reloading the page.
- **Customizable Latest Comments Block**: Excludes author comments and/or reply comments based on plugin settings from latest comments block.
- **Responsive Design**: Ensures the rating system looks great on all devices.
- **Accessibility options**: Keyboard navigation and Screen reader support.

## Usage

### Admin Settings

1. **Creating Ratings**
    - Navigate to 'Shuriken Reviews > Ratings' in WordPress admin
    - Enter rating name and settings
    - Click 'Create Rating'
    - Update or delete Rating if needed

2. **Comments Settings**
    - Navigate to 'Shuriken Reviews > Comments Settings' in WordPress admin
    - Check comment exclusion function as desired

### FSE Block (Recommended)

The **Shuriken Rating** block can be added directly in the WordPress block editor (Gutenberg) or Full Site Editor:

1. Add a new block and search for "Shuriken Rating"
2. Select an existing rating from the searchable dropdown, or create a new one directly from the block
3. Configure options in the block sidebar:
   - **Select Rating**: Choose from existing ratings (searchable)
   - **Create New Rating**: Add a new rating without leaving the editor
   - **Title Tag**: Choose the HTML heading tag (h1-h6, div, p, span)
   - **Anchor ID**: Optional ID for linking to this rating

### Shortcode

Use the shortcode `[shuriken_rating]` to display ratings anywhere on your site. The shortcode accepts the following parameters:

- `id`: The numeric ID of the rating you want to display (required)
- `tag`: HTML tag to wrap the rating title (optional, defaults to h2)
- `anchor_tag`: Anchor Tag Linking Id (optional)

Example:

```shortcode
[shuriken_rating id="1" tag="h3" anchor_tag="rating-1"]
```

This will display rating #1 with its title wrapped in an h3 tag.

### AJAX Rating Submission

The plugin uses AJAX to handle rating submissions, ensuring a smooth user experience without page reloads.

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

Developed by Skilledup Hub. For more information, visit [Skilledup](https://skilledup.ir).

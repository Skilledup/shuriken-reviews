# Shuriken Reviews

Shuriken Reviews is a WordPress plugin that enhances the comment section with additional functionalities, including rating systems and improved comment display.

## Features

- **Rating System**: Allows users to rate anything, anywhere on your website.
- **Admin Settings**: Manage ratings from the WordPress admin dashboard.
- **Shortcode**: Display ratings using the `[shuriken_rating]` shortcode.
- **AJAX Rating Submission**: Users can submit ratings without reloading the page.
- **Customizable Latest Comments Block**: Excludes author comments and displays comments in a grid layout.
- **Responsive Design**: Ensures the rating system looks great on all devices.
- **Accessibility options**: Keyboard navigation and Sreen reader support.

## Usage

### Admin Settings

- **Create New Rating**: Fill in the rating name and click 'Create Rating'.
- **Update Rating**: Modify the rating name and click 'Update'.
- **Delete Rating**: Click 'Delete' to remove a rating.

### Shortcode

Use the shortcode `[shuriken_rating]` to display ratings anywhere on your site. The shortcode accepts two parameters:

- `id`: The numeric ID of the rating you want to display (required)
- `tag`: HTML tag to wrap the rating title (optional, defaults to h2)
- `anchor_tag`: Anchor Tag Linking Id (optional)

Example:
```
[shuriken_rating id="1" tag="h2" anchor_tag="rating-1"]
```

This will display rating #1 with its title wrapped in an h3 tag.

### AJAX Rating Submission

The plugin uses AJAX to handle rating submissions, ensuring a smooth user experience without page reloads.

## License

This plugin is licensed under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

Developed by Skilledup Hub. For more information, visit [Skilledup](https://skilledup.ir).
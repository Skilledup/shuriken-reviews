<?php
/**
 * About Tab Partial
 *
 * Displays plugin info, what's new, quick start, shortcode reference,
 * developer resources, and system information inside the Settings page.
 *
 * @package Shuriken_Reviews
 * @since 1.14.5
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Hero Strip -->
<div class="about-hero-strip" style="background-image: url('<?php echo esc_url(plugins_url('assets/images/mascot.avif', SHURIKEN_REVIEWS_PLUGIN_FILE)); ?>')">
    <div class="about-hero-content">
        <span class="about-hero-eyebrow">
            <span class="dashicons dashicons-star-filled"></span>
            <?php esc_html_e('WordPress Rating Plugin', 'shuriken-reviews'); ?>
        </span>
        <h2><?php esc_html_e('Shuriken Reviews', 'shuriken-reviews'); ?></h2>
        <p class="about-hero-tagline">
            <?php esc_html_e('A powerful and flexible rating system for WordPress', 'shuriken-reviews'); ?>
        </p>
    </div>
    <div class="about-hero-aside">
        <span class="about-version-badge">
            <?php printf(esc_html__('v%s', 'shuriken-reviews'), SHURIKEN_REVIEWS_VERSION); ?>
        </span>
        <div class="about-hero-links">
            <a href="https://github.com/Skilledup/shuriken-reviews" target="_blank" rel="noopener noreferrer">
                <span class="dashicons dashicons-github"></span>
                <?php esc_html_e('GitHub', 'shuriken-reviews'); ?>
            </a>
            <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/CHANGELOG.md" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Changelog', 'shuriken-reviews'); ?>
            </a>
        </div>
    </div>
</div>

<!-- What's New -->
<div class="shuriken-settings-card shuriken-settings-card-highlight">
    <div class="settings-card-header">
        <span class="settings-card-icon">🆕</span>
        <h3><?php printf(esc_html__("What's New in %s", 'shuriken-reviews'), esc_html(SHURIKEN_REVIEWS_VERSION)); ?></h3>
    </div>
    <div class="settings-card-body">
        <div class="about-new-highlight">
            <h4><?php esc_html_e('Contextual Voting (Per-Post Ratings)', 'shuriken-reviews'); ?></h4>
            <p><?php esc_html_e('Shuriken now competes head-to-head with traditional post-rating plugins — but without forcing you to create a separate rating for every post. Place one rating (or grouped rating) in your single post template, flip the "Per-post voting" toggle, and every post on your site immediately gets its own independent vote tallies.', 'shuriken-reviews'); ?></p>
            <ul class="about-features-list">
                <li>
                    <strong><?php esc_html_e('Zero-Config Per-Post Ratings', 'shuriken-reviews'); ?></strong>
                    <?php esc_html_e('Add a rating block to your Single Post template, enable Per-post voting — done. Every post gets its own scores without creating hundreds of individual ratings.', 'shuriken-reviews'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('One Template, Many Posts', 'shuriken-reviews'); ?></strong>
                    <?php esc_html_e('The rating entity is defined once. Votes are scoped to each post via context_id / context_type columns on the votes table. Global aggregates remain intact for standalone use.', 'shuriken-reviews'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Grouped Ratings Too', 'shuriken-reviews'); ?></strong>
                    <?php esc_html_e('The grouped rating block supports the same toggle. One parent + sub-rating configuration serves all posts — add or remove sub-ratings once and every post reflects the change instantly.', 'shuriken-reviews'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('REST Compatible', 'shuriken-reviews'); ?></strong>
                    <?php esc_html_e('The /ratings/stats endpoint accepts optional context_id and context_type parameters, returning per-post stats for any custom frontend or headless WordPress setup.', 'shuriken-reviews'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Extensible Context Types', 'shuriken-reviews'); ?></strong>
                    <?php esc_html_e('Supports post, page, and product out of the box. Add any custom post type via the shuriken_allowed_context_types filter.', 'shuriken-reviews'); ?>
                </li>
                <li>
                    <strong><?php esc_html_e('Fully Backward Compatible', 'shuriken-reviews'); ?></strong>
                    <?php esc_html_e('Per-post voting is opt-in per block. Existing standalone ratings and global tallies are completely unchanged.', 'shuriken-reviews'); ?>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Quick Start Guide -->
<div class="shuriken-settings-card">
    <div class="settings-card-header">
        <span class="settings-card-icon">📖</span>
        <h3><?php esc_html_e('Quick Start Guide', 'shuriken-reviews'); ?></h3>
    </div>
    <div class="settings-card-body">
        <div class="about-steps">
            <div class="about-step-card">
                <div class="about-step-number">1</div>
                <div class="about-step-content">
                    <h4><?php esc_html_e('Create a Rating', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Go to Ratings and create your first rating with a name and optional settings.', 'shuriken-reviews'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews')); ?>" class="about-step-link">
                        <?php esc_html_e('Go to Ratings', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            <div class="about-step-card">
                <div class="about-step-number">2</div>
                <div class="about-step-content">
                    <h4><?php esc_html_e('Add to Your Site', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Use the Shuriken Rating block in the editor or add a shortcode to your content.', 'shuriken-reviews'); ?></p>
                    <code class="about-shortcode-example">[shuriken_rating id="1"]</code>
                </div>
            </div>
            <div class="about-step-card">
                <div class="about-step-number">3</div>
                <div class="about-step-content">
                    <h4><?php esc_html_e('Monitor Performance', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Track your ratings with the built-in analytics dashboard.', 'shuriken-reviews'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews-analytics')); ?>" class="about-step-link">
                        <?php esc_html_e('View Analytics', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shortcode Reference -->
<div class="shuriken-settings-card">
    <div class="settings-card-header">
        <span class="settings-card-icon">💻</span>
        <h3><?php esc_html_e('Shortcode Reference', 'shuriken-reviews'); ?></h3>
    </div>
    <div class="settings-card-body">
        <div class="about-shortcode-grid">
            <p class="about-shortcode-group-title"><?php esc_html_e('Single Rating — [shuriken_rating]', 'shuriken-reviews'); ?></p>

            <div class="about-shortcode-box">
                <h4><?php esc_html_e('Basic Usage', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="1"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Display rating with ID 1', 'shuriken-reviews'); ?></p>
            </div>

            <div class="about-shortcode-box">
                <h4><?php esc_html_e('Custom Title Tag', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="1" tag="h3"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Use h3 for the title (options: h1–h6, div, p, span)', 'shuriken-reviews'); ?></p>
            </div>

            <div class="about-shortcode-box">
                <h4><?php esc_html_e('With Anchor', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="1" anchor_tag="my-rating"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Add an anchor ID for deep-linking', 'shuriken-reviews'); ?></p>
            </div>

            <div class="about-shortcode-box">
                <h4><?php esc_html_e('Preset Style + Colors', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="1" style="card" accent_color="#e74c3c" star_color="#f39c12"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Presets: classic, card, minimal, dark, outlined', 'shuriken-reviews'); ?></p>
            </div>

            <p class="about-shortcode-group-title"><?php esc_html_e('Grouped Rating — [shuriken_grouped_rating]', 'shuriken-reviews'); ?></p>

            <div class="about-shortcode-box">
                <h4><?php esc_html_e('Basic Grouped', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_grouped_rating id="1"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Display a parent rating with all its sub-ratings in grid layout', 'shuriken-reviews'); ?></p>
            </div>

            <div class="about-shortcode-box">
                <h4><?php esc_html_e('List Layout + Preset', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_grouped_rating id="1" style="dark" layout="list"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Presets: gradient, minimal, boxed, dark, outlined. Layouts: grid, list', 'shuriken-reviews'); ?></p>
            </div>

            <div class="about-shortcode-box">
                <h4><?php esc_html_e('Full Example', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_grouped_rating id="5" tag="h3" style="boxed" accent_color="#667eea" layout="list"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Complete grouped shortcode with all parameters', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Developer Resources -->
<div class="shuriken-settings-card">
    <div class="settings-card-header">
        <span class="settings-card-icon">🛠️</span>
        <h3><?php esc_html_e('Developer Resources', 'shuriken-reviews'); ?></h3>
    </div>
    <div class="settings-card-body">
        <div class="about-dev-grid">
            <div class="about-resource-card">
                <div class="about-resource-icon">
                    <span class="dashicons dashicons-admin-plugins"></span>
                </div>
                <div class="about-resource-content">
                    <h4><?php esc_html_e('Hooks & Filters', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('30+ available hooks (19 filters + 11 actions). Modify rating display, control voting, and integrate with your code.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/hooks-reference.md" target="_blank" rel="noopener noreferrer" class="about-resource-link">
                        <?php esc_html_e('View Hooks Documentation', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>

            <div class="about-resource-card">
                <div class="about-resource-icon">
                    <span class="dashicons dashicons-saved"></span>
                </div>
                <div class="about-resource-content">
                    <h4><?php esc_html_e('Interfaces & Testing', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Mock implementations for unit tests — no database required. Interfaces for Database, Analytics, and Voter Analytics.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/testing.md" target="_blank" rel="noopener noreferrer" class="about-resource-link">
                        <?php esc_html_e('Testing Guide', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>

            <div class="about-resource-card">
                <div class="about-resource-icon">
                    <span class="dashicons dashicons-admin-tools"></span>
                </div>
                <div class="about-resource-content">
                    <h4><?php esc_html_e('Dependency Injection', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Lightweight service container for flexible dependency management — swap any service implementation at runtime.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/dependency-injection.md" target="_blank" rel="noopener noreferrer" class="about-resource-link">
                        <?php esc_html_e('DI Documentation', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>

            <div class="about-resource-card">
                <div class="about-resource-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="about-resource-content">
                    <h4><?php esc_html_e('Exception System', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('8 typed exception classes, unified interface and trait. Automatic logging and HTTP-status code mapping.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/exception-handling.md" target="_blank" rel="noopener noreferrer" class="about-resource-link">
                        <?php esc_html_e('Exception Guide', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>

            <div class="about-resource-card">
                <div class="about-resource-icon">
                    <span class="dashicons dashicons-rest-api"></span>
                </div>
                <div class="about-resource-content">
                    <h4><?php esc_html_e('REST API', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Full CRUD + stats endpoints. Supports batch-fetching, contextual queries, and CDN-compatible nonce endpoint.', 'shuriken-reviews'); ?></p>
                    <ul class="about-code-list">
                        <li><code>GET /ratings/stats?ids=1,2,3</code></li>
                        <li><code>GET /ratings/stats?ids=1&amp;context_id=42&amp;context_type=post</code></li>
                    </ul>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/rest-api.md" target="_blank" rel="noopener noreferrer" class="about-resource-link">
                        <?php esc_html_e('REST API Docs', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>

            <div class="about-resource-card">
                <div class="about-resource-icon">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="about-resource-content">
                    <h4><?php esc_html_e('Helper Functions', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Global helper functions for accessing plugin services from themes and other plugins.', 'shuriken-reviews'); ?></p>
                    <ul class="about-code-list">
                        <li><code>shuriken_db()->get_rating($id)</code></li>
                        <li><code>shuriken_analytics()->get_top_rated()</code></li>
                    </ul>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/helper-functions.md" target="_blank" rel="noopener noreferrer" class="about-resource-link">
                        <?php esc_html_e('Helper Functions Reference', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
        </div>

        <div class="about-hooks-summary">
            <h4><?php esc_html_e('Popular Hooks', 'shuriken-reviews'); ?></h4>
            <table class="about-hooks-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Hook', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Type', 'shuriken-reviews'); ?></th>
                        <th><?php esc_html_e('Description', 'shuriken-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>shuriken_rating_html</code></td>
                        <td><?php esc_html_e('Filter', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Modify the rating HTML output', 'shuriken-reviews'); ?></td>
                    </tr>
                    <tr>
                        <td><code>shuriken_can_submit_vote</code></td>
                        <td><?php esc_html_e('Filter', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Control who can vote', 'shuriken-reviews'); ?></td>
                    </tr>
                    <tr>
                        <td><code>shuriken_rating_star_symbol</code></td>
                        <td><?php esc_html_e('Filter', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Change the rating symbol (★, ❤, etc.)', 'shuriken-reviews'); ?></td>
                    </tr>
                    <tr>
                        <td><code>shuriken_vote_created</code></td>
                        <td><?php esc_html_e('Action', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Triggered after a new vote is recorded', 'shuriken-reviews'); ?></td>
                    </tr>
                    <tr>
                        <td><code>shuriken_after_rating_stats</code></td>
                        <td><?php esc_html_e('Action', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Add content after the rating stats block', 'shuriken-reviews'); ?></td>
                    </tr>
                    <tr>
                        <td><code>shuriken_settings_tabs</code></td>
                        <td><?php esc_html_e('Filter', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Add or modify Settings page tabs', 'shuriken-reviews'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- System Information -->
<div class="shuriken-settings-card">
    <div class="settings-card-header">
        <span class="settings-card-icon">ℹ️</span>
        <h3><?php esc_html_e('System Information', 'shuriken-reviews'); ?></h3>
    </div>
    <div class="settings-card-body">
        <div class="about-system-grid">
            <div class="about-system-item">
                <span class="about-system-label"><?php esc_html_e('Plugin Version', 'shuriken-reviews'); ?></span>
                <span class="about-system-value"><?php echo esc_html(SHURIKEN_REVIEWS_VERSION); ?></span>
            </div>
            <div class="about-system-item">
                <span class="about-system-label"><?php esc_html_e('Database Version', 'shuriken-reviews'); ?></span>
                <span class="about-system-value"><?php echo esc_html(SHURIKEN_REVIEWS_DB_VERSION); ?></span>
            </div>
            <div class="about-system-item">
                <span class="about-system-label"><?php esc_html_e('WordPress', 'shuriken-reviews'); ?></span>
                <span class="about-system-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
            </div>
            <div class="about-system-item">
                <span class="about-system-label"><?php esc_html_e('PHP', 'shuriken-reviews'); ?></span>
                <span class="about-system-value"><?php echo esc_html(PHP_VERSION); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Credit Footer -->
<p class="about-credit-footer">
    <?php
    printf(
        /* translators: %s: author name with link */
        esc_html__('Shuriken Reviews — developed by %s — licensed under GPL v3', 'shuriken-reviews'),
        '<a href="https://skilledup.ir" target="_blank" rel="noopener noreferrer">Skilledup</a>'
    );
    ?>
    &nbsp;·&nbsp;
    <a href="https://github.com/Skilledup/shuriken-reviews" target="_blank" rel="noopener noreferrer">GitHub</a>
</p>

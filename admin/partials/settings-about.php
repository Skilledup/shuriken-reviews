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
            <?php Shuriken_Icons::render('star', array('width' => 13, 'height' => 13)); ?>
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
                <?php Shuriken_Icons::render('github', array('width' => 15, 'height' => 15)); ?>
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
        <span class="settings-card-icon"><?php Shuriken_Icons::render('sparkles'); ?></span>
        <h3><?php printf(esc_html__("What's New in %s", 'shuriken-reviews'), esc_html(SHURIKEN_REVIEWS_VERSION)); ?></h3>
    </div>
    <div class="settings-card-body">
        <div class="about-new-highlight">
            <p class="about-new-intro"><?php esc_html_e('Yozora is the 1.15.4 release. It focuses on deeper contextual analytics, safer frontend re-initialization on modern block-theme navigation, and clearer mixed-scope reporting throughout the admin.', 'shuriken-reviews'); ?></p>
            <ul class="about-features-list">
                <li>
                    <div>
                        <strong><?php esc_html_e('Per-Post Analytics Workspace', 'shuriken-reviews'); ?></strong>
                        <span><?php esc_html_e('Ratings with contextual votes now open into a dedicated Per-Post view with top-post charts, average distribution across posts, trending contexts, and sortable context tables.', 'shuriken-reviews'); ?></span>
                    </div>
                </li>
                <li>
                    <div>
                        <strong><?php esc_html_e('Global vs. Per-Post Scope Toggle', 'shuriken-reviews'); ?></strong>
                        <span><?php esc_html_e('When a rating has both classic global votes and contextual votes, Item Stats now exposes separate Global Votes and Per-Post Votes views so totals, charts, and tables are never mixed accidentally.', 'shuriken-reviews'); ?></span>
                    </div>
                </li>
                <li>
                    <div>
                        <strong><?php esc_html_e('Context Drill-Down Screens', 'shuriken-reviews'); ?></strong>
                        <span><?php esc_html_e('Every post, page, or product in the Per-Post view now links to its own detail screen with context-specific summary cards, charts, and a paginated vote history table.', 'shuriken-reviews'); ?></span>
                    </div>
                </li>
                <li>
                    <div>
                        <strong><?php esc_html_e('Client-Side Navigation Support', 'shuriken-reviews'); ?></strong>
                        <span><?php esc_html_e('Both rating blocks now opt into WordPress client navigation, and the frontend ratings script re-initialises automatically after Interactivity Router navigations so widgets stay interactive on block-theme transitions.', 'shuriken-reviews'); ?></span>
                    </div>
                </li>
                <li>
                    <div>
                        <strong><?php esc_html_e('Mixed-Scope Rating Badges', 'shuriken-reviews'); ?></strong>
                        <span><?php esc_html_e('The Ratings list now distinguishes ratings that have both global and contextual activity, showing a mixed badge like “Global + 6 posts” instead of implying that all votes come from one source.', 'shuriken-reviews'); ?></span>
                    </div>
                </li>
                <li>
                    <div>
                        <strong><?php esc_html_e('Unified, Responsive Filter Bar', 'shuriken-reviews'); ?></strong>
                        <span><?php esc_html_e('The Item Stats filter controls were reorganised into a cleaner responsive bar that preserves date range, scope, and parent-view selections more reliably while switching modes.', 'shuriken-reviews'); ?></span>
                    </div>
                </li>
                <li>
                    <div>
                        <strong><?php esc_html_e('Chart and Display Accuracy', 'shuriken-reviews'); ?></strong>
                        <span><?php esc_html_e('Parent breakdown charts now respect the active scope and date range, while star and binary displays across analytics and the ratings list received icon and formatting polish for clearer at-a-glance stats.', 'shuriken-reviews'); ?></span>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Quick Start Guide -->
<div class="shuriken-settings-card">
    <div class="settings-card-header">
        <span class="settings-card-icon"><?php Shuriken_Icons::render('book-open'); ?></span>
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
        <span class="settings-card-icon"><?php Shuriken_Icons::render('code'); ?></span>
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
                <code>[shuriken_rating id="8" style="minimal" accent_color="#0f766e" star_color="#14b8a6" button_color="#155e75"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Presets: classic, card, minimal, dark, outlined. Use button_color for numeric submit buttons.', 'shuriken-reviews'); ?></p>
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
                <code>[shuriken_grouped_rating id="5" tag="h3" style="boxed" accent_color="#667eea" button_color="#1d4ed8" layout="list"]</code>
                <p class="about-shortcode-desc"><?php esc_html_e('Complete grouped shortcode with style, layout, and numeric button colour override', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Developer Resources -->
<div class="shuriken-settings-card">
    <div class="settings-card-header">
        <span class="settings-card-icon"><?php Shuriken_Icons::render('hammer'); ?></span>
        <h3><?php esc_html_e('Developer Resources', 'shuriken-reviews'); ?></h3>
    </div>
    <div class="settings-card-body">
        <div class="about-dev-grid">
            <div class="about-resource-card">
                <div class="about-resource-icon">
                    <?php Shuriken_Icons::render('package', array('width' => 20, 'height' => 20)); ?>
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
                    <?php Shuriken_Icons::render('check-circle', array('width' => 20, 'height' => 20)); ?>
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
                    <?php Shuriken_Icons::render('wrench', array('width' => 20, 'height' => 20)); ?>
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
                    <?php Shuriken_Icons::render('triangle-alert', array('width' => 20, 'height' => 20)); ?>
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
                    <?php Shuriken_Icons::render('plug', array('width' => 20, 'height' => 20)); ?>
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
                    <?php Shuriken_Icons::render('database', array('width' => 20, 'height' => 20)); ?>
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
        <span class="settings-card-icon"><?php Shuriken_Icons::render('info'); ?></span>
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

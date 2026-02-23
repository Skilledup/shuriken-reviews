<?php
/**
 * Shuriken Reviews About Page
 *
 * @package Shuriken_Reviews
 * @since 1.5.8
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap shuriken-about-wrap">
    <!-- Hero Section -->
    <div class="shuriken-about-hero mascot-hero">
        <div class="hero-content">
            <div class="hero-icon">
                <span class="dashicons dashicons-star-filled"></span>
            </div>
            <h1><?php esc_html_e('Shuriken Reviews', 'shuriken-reviews'); ?></h1>
            <p class="version-badge">
                <?php printf(esc_html__('Version %s', 'shuriken-reviews'), SHURIKEN_REVIEWS_VERSION); ?>
            </p>
            <p class="hero-tagline">
                <?php esc_html_e('A powerful and flexible rating system for WordPress', 'shuriken-reviews'); ?>
            </p>
        </div>
    </div>

    <!-- Features Grid -->
    <div class="shuriken-about-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-awards"></span>
            <?php esc_html_e('Features', 'shuriken-reviews'); ?>
        </h2>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-star-filled"></span>
                </div>
                <h3><?php esc_html_e('Rating System', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Create unlimited ratings with parent-child relationships, mirrors, and effect types for comprehensive review systems.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-block-default"></span>
                </div>
                <h3><?php esc_html_e('FSE Block', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Full Site Editor block for seamless integration with the Gutenberg block editor. Create ratings directly from blocks.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-shortcode"></span>
                </div>
                <h3><?php esc_html_e('Shortcode Support', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Display ratings anywhere using the flexible [shuriken_rating] shortcode with customizable parameters.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <h3><?php esc_html_e('AJAX Submissions', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Smooth user experience with AJAX-powered rating submissions - no page reloads required.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <h3><?php esc_html_e('Analytics Dashboard', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Comprehensive statistics and analytics with charts, trends, and detailed insights into your ratings.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-admin-comments"></span>
                </div>
                <h3><?php esc_html_e('Comments Enhancement', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Customize the Latest Comments block by excluding author and/or reply comments based on your preferences.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-smartphone"></span>
                </div>
                <h3><?php esc_html_e('Responsive Design', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Beautiful and responsive design that looks great on all devices - desktop, tablet, and mobile.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-universal-access"></span>
                </div>
                <h3><?php esc_html_e('Accessibility', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Full keyboard navigation and screen reader support for an inclusive user experience.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-shield"></span>
                </div>
                <h3><?php esc_html_e('Rate Limiting', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Protect ratings from spam with configurable cooldowns, hourly limits, and daily limits for both members and guests.', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <span class="dashicons dashicons-code-standards"></span>
                </div>
                <h3><?php esc_html_e('Developer-Friendly', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('25+ hooks, interfaces for testing, dependency injection, and comprehensive exception handling. Built for extensibility.', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>

    <!-- What's New -->
    <div class="shuriken-about-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-megaphone"></span>
            <?php printf( esc_html__( "What's New in %s", 'shuriken-reviews' ), esc_html( SHURIKEN_REVIEWS_VERSION ) ); ?>
        </h2>
        
        <div class="whats-new-content">
            <div class="new-feature-highlight">
                <h3><?php esc_html_e('FSE Block Redesign — Style Presets (v2)', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('Both FSE blocks have been completely redesigned with a clean preset-based system, replacing the previous complex per-attribute settings:', 'shuriken-reviews'); ?></p>

                <h4 style="margin-top: 1.5em;"><?php esc_html_e('Shuriken Rating Block', 'shuriken-reviews'); ?></h4>
                <ul class="new-features-list">
                    <li>
                        <strong><?php esc_html_e('5 Visual Presets', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Classic (default — fully backward-compatible), Card, Minimal, Dark, Outlined', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Accent & Star Colour Pickers', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Two colour overrides that cascade through the selected preset via CSS custom properties', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Live Editor Preview', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Preset classes applied directly to the block wrapper so the FSE preview matches the frontend exactly', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Backward Compatible', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Existing blocks without a style class automatically render as Classic — no content migration needed', 'shuriken-reviews'); ?>
                    </li>
                </ul>

                <h4 style="margin-top: 1.5em;"><?php esc_html_e('Shuriken Grouped Rating Block', 'shuriken-reviews'); ?></h4>
                <ul class="new-features-list">
                    <li>
                        <strong><?php esc_html_e('5 Visual Presets', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Gradient (default), Minimal, Boxed, Dark, Outlined — each with distinct parent and child card styles', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Grid / List Layout', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Switch child ratings between a responsive card grid and a full-width stacked list from the Layout panel', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Accent & Star Colour Pickers', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Same colour override system as the single rating block, applied to parent and child cards', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Simplified Settings Panels', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Reduced from 5 inspector panels to 3 (Settings, Layout, Colors) for a cleaner editing experience', 'shuriken-reviews'); ?>
                    </li>
                </ul>

                <h4 style="margin-top: 1.5em;"><?php esc_html_e('How Presets Work', 'shuriken-reviews'); ?></h4>
                <ul class="new-features-list">
                    <li><?php esc_html_e('Select a preset from the block styles panel (the palette icon in the inspector)', 'shuriken-reviews'); ?></li>
                    <li><?php esc_html_e('WordPress adds an is-style-{name} class to the block wrapper', 'shuriken-reviews'); ?></li>
                    <li><?php esc_html_e('CSS scoped to that class handles all visual differences — no inline styles per attribute', 'shuriken-reviews'); ?></li>
                    <li><?php esc_html_e('Addon colours flow through --shuriken-user-accent and --shuriken-user-star-color CSS variables', 'shuriken-reviews'); ?></li>
                </ul>

                <p class="new-features-note">
                    <?php esc_html_e('The Classic preset is the default for the single rating block and matches the previous visual style exactly — existing shortcode and block output is unchanged.', 'shuriken-reviews'); ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Quick Start Guide -->
    <div class="shuriken-about-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-book"></span>
            <?php esc_html_e('Quick Start Guide', 'shuriken-reviews'); ?>
        </h2>
        
        <div class="quick-start-steps">
            <div class="step-card">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h4><?php esc_html_e('Create a Rating', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Go to Ratings page and create your first rating with a name and optional settings.', 'shuriken-reviews'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews')); ?>" class="step-link">
                        <?php esc_html_e('Go to Ratings', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            
            <div class="step-card">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h4><?php esc_html_e('Add to Your Site', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Use the Shuriken Rating block in the editor or add the shortcode to your content.', 'shuriken-reviews'); ?></p>
                    <code class="shortcode-example">[shuriken_rating id="1"]</code>
                </div>
            </div>
            
            <div class="step-card">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h4><?php esc_html_e('Monitor Performance', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Track your ratings performance with the built-in analytics dashboard.', 'shuriken-reviews'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=shuriken-reviews-analytics')); ?>" class="step-link">
                        <?php esc_html_e('View Analytics', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Shortcode Reference -->
    <div class="shuriken-about-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-editor-code"></span>
            <?php esc_html_e('Shortcode Reference', 'shuriken-reviews'); ?>
        </h2>
        
        <div class="shortcode-reference">
            <div class="shortcode-box">
                <h4><?php esc_html_e('Basic Usage', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="1"]</code>
                <p class="shortcode-desc"><?php esc_html_e('Display rating with ID 1', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="shortcode-box">
                <h4><?php esc_html_e('Custom Title Tag', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="1" tag="h3"]</code>
                <p class="shortcode-desc"><?php esc_html_e('Use h3 for the title (options: h1-h6, div, p, span)', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="shortcode-box">
                <h4><?php esc_html_e('With Anchor', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="1" anchor_tag="my-rating"]</code>
                <p class="shortcode-desc"><?php esc_html_e('Add an anchor ID for linking', 'shuriken-reviews'); ?></p>
            </div>
            
            <div class="shortcode-box">
                <h4><?php esc_html_e('Full Example', 'shuriken-reviews'); ?></h4>
                <code>[shuriken_rating id="5" tag="h4" anchor_tag="product-rating"]</code>
                <p class="shortcode-desc"><?php esc_html_e('Complete shortcode with all parameters', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>

    <!-- Developer Resources -->
    <div class="shuriken-about-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-code-standards"></span>
            <?php esc_html_e('Developer Resources', 'shuriken-reviews'); ?>
        </h2>
        
        <div class="developer-resources">
            <div class="resource-card">
                <div class="resource-icon">
                    <span class="dashicons dashicons-admin-plugins"></span>
                </div>
                <div class="resource-content">
                    <h4><?php esc_html_e('Hooks & Filters', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Extend and customize the plugin with 20+ available hooks (12 filters + 8 actions). Modify rating display, control voting behavior, and integrate with your custom code.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/hooks-reference.md" target="_blank" rel="noopener noreferrer" class="resource-link">
                        <?php esc_html_e('View Hooks Documentation', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            
            <div class="resource-card">
                <div class="resource-icon">
                    <span class="dashicons dashicons-saved"></span>
                </div>
                <div class="resource-content">
                    <h4><?php esc_html_e('Interfaces & Testing', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Test your code with mock implementations. No database required for unit tests. Interfaces available for Database and Analytics services.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/testing.md" target="_blank" rel="noopener noreferrer" class="resource-link">
                        <?php esc_html_e('Testing Guide', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            
            <div class="resource-card">
                <div class="resource-icon">
                    <span class="dashicons dashicons-admin-tools"></span>
                </div>
                <div class="resource-content">
                    <h4><?php esc_html_e('Dependency Injection', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Flexible service container for managing dependencies. Easy to inject mocks for testing or swap implementations.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/dependency-injection.md" target="_blank" rel="noopener noreferrer" class="resource-link">
                        <?php esc_html_e('DI Documentation', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            
            <div class="resource-card">
                <div class="resource-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="resource-content">
                    <h4><?php esc_html_e('Exception System', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Comprehensive error handling with 6 exception types. Type-safe error catching with automatic logging and WordPress integration.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/exception-handling.md" target="_blank" rel="noopener noreferrer" class="resource-link">
                        <?php esc_html_e('Exception Guide', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            
            <div class="resource-card">
                <div class="resource-icon">
                    <span class="dashicons dashicons-rest-api"></span>
                </div>
                <div class="resource-content">
                    <h4><?php esc_html_e('REST API', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Access ratings data programmatically via REST API endpoints. Perfect for headless WordPress setups and custom integrations.', 'shuriken-reviews'); ?></p>
                    <div class="api-endpoints">
                        <code>GET /wp-json/shuriken-reviews/v1/ratings</code>
                        <code>GET /wp-json/shuriken-reviews/v1/ratings/search?q=term</code>
                        <code>GET /wp-json/shuriken-reviews/v1/ratings/{id}/children</code>
                        <code>GET /wp-json/shuriken-reviews/v1/ratings/stats?ids=1,2,3</code>
                    </div>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/rest-api.md" target="_blank" rel="noopener noreferrer" class="resource-link">
                        <?php esc_html_e('REST API Documentation', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            
            <div class="resource-card">
                <div class="resource-icon">
                    <span class="dashicons dashicons-database"></span>
                </div>
                <div class="resource-content">
                    <h4><?php esc_html_e('Helper Functions', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Use built-in helper functions to access plugin functionality in your themes and plugins.', 'shuriken-reviews'); ?></p>
                    <div class="code-examples">
                        <code>shuriken_db()->get_rating($id)</code>
                        <code>shuriken_analytics()->get_top_rated()</code>
                    </div>
                    <a href="https://github.com/Skilledup/shuriken-reviews/blob/main/docs/guides/helper-functions.md" target="_blank" rel="noopener noreferrer" class="resource-link">
                        <?php esc_html_e('Helper Functions Reference', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
        </div>
        
        <div class="hooks-summary">
            <h4><?php esc_html_e('Popular Hooks', 'shuriken-reviews'); ?></h4>
            <table class="hooks-table">
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
                        <td><?php esc_html_e('Change the star symbol (★, ❤, etc.)', 'shuriken-reviews'); ?></td>
                    </tr>
                    <tr>
                        <td><code>shuriken_vote_created</code></td>
                        <td><?php esc_html_e('Action', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Triggered after a new vote', 'shuriken-reviews'); ?></td>
                    </tr>
                    <tr>
                        <td><code>shuriken_after_rating_stats</code></td>
                        <td><?php esc_html_e('Action', 'shuriken-reviews'); ?></td>
                        <td><?php esc_html_e('Add content after rating stats', 'shuriken-reviews'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System Info -->
    <div class="shuriken-about-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-info-outline"></span>
            <?php esc_html_e('System Information', 'shuriken-reviews'); ?>
        </h2>
        
        <div class="system-info-grid">
            <div class="info-item">
                <span class="info-label"><?php esc_html_e('Plugin Version', 'shuriken-reviews'); ?></span>
                <span class="info-value"><?php echo esc_html(SHURIKEN_REVIEWS_VERSION); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><?php esc_html_e('Database Version', 'shuriken-reviews'); ?></span>
                <span class="info-value"><?php echo esc_html(SHURIKEN_REVIEWS_DB_VERSION); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><?php esc_html_e('WordPress Version', 'shuriken-reviews'); ?></span>
                <span class="info-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label"><?php esc_html_e('PHP Version', 'shuriken-reviews'); ?></span>
                <span class="info-value"><?php echo esc_html(PHP_VERSION); ?></span>
            </div>
        </div>
    </div>

    <!-- Credits Section -->
    <div class="shuriken-about-section credits-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-heart"></span>
            <?php esc_html_e('Credits', 'shuriken-reviews'); ?>
        </h2>
        
        <div class="credits-content">
            <p>
                <?php 
                printf(
                    esc_html__('Developed with %s by %s', 'shuriken-reviews'),
                    '<span class="heart">❤️</span>',
                    '<a href="https://skilledup.ir" target="_blank" rel="noopener noreferrer">Skilledup</a>'
                );
                ?>
            </p>
                <p class="license-info">
                <?php esc_html_e('Licensed under GPL v3 or later', 'shuriken-reviews'); ?>
            </p>
            <div class="social-links">
                <a href="https://skilledup.ir" target="_blank" rel="noopener noreferrer" class="social-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="15" viewBox="0 0 174 130.12" fill="currentColor" style="vertical-align: middle;"><path d="M146.97,1.59v22.83c0,.88-.71,1.59-1.59,1.59H60.78c-5.96,0-11.27,3.69-13.37,9.24-.03.08-.06.16-.1.24l-3.58,9.97c-1.43,3.96-5.17,6.58-9.35,6.58h-15.65c-6.04,0-11.43-3.8-13.47-9.48l-3.01-8.4v-.02L.09,28.14c-.37-1.04.4-2.13,1.5-2.13h39.05c6.03,0,11.39-3.77,13.44-9.41.02-.02.03-.05.03-.06l3.58-9.95c1.42-3.96,5.15-6.58,9.33-6.58h78.35c.88,0,1.59.71,1.59,1.59Z"/><path d="M174,79.67v22.85c0,.88-.71,1.59-1.59,1.59h-51.37c-6.03,0-11.39,3.77-13.44,9.41-.02.02-.03.05-.03.06l-3.58,9.95c-1.42,3.96-5.15,6.58-9.33,6.58h-50.2c-4.69,0-8.89-2.96-10.48-7.38l-3.78-10.56-2.13-5.95c-.37-1.03.4-2.13,1.49-2.13h71.35c5.96,0,11.27-3.69,13.37-9.24.03-.08.06-.16.1-.24l3.58-9.97c1.43-3.96,5.17-6.58,9.35-6.58h45.1c.88,0,1.59.71,1.59,1.59Z"/><path d="M126.55,60.55l-2.87,8.02c-2.04,5.69-7.43,9.49-13.47,9.49H41.09c-4.4,0-7.47-4.36-5.99-8.51l2.87-8.02c2.04-5.69,7.43-9.49,13.47-9.49h69.11c4.4,0,7.47,4.36,5.99,8.51Z"/></svg>
                    <?php esc_html_e('Skilledup', 'shuriken-reviews'); ?>
                </a>
                <a href="https://github.com/Skilledup/shuriken-reviews" target="_blank" rel="noopener noreferrer" class="social-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align: middle;"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                    <?php esc_html_e('GitHub', 'shuriken-reviews'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

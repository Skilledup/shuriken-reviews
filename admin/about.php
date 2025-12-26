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
    <div class="shuriken-about-hero">
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
                    <span class="dashicons dashicons-code-standards"></span>
                </div>
                <h3><?php esc_html_e('Developer-Friendly', 'shuriken-reviews'); ?></h3>
                <p><?php esc_html_e('20+ hooks, interfaces for testing, dependency injection, and comprehensive exception handling. Built for extensibility.', 'shuriken-reviews'); ?></p>
            </div>
        </div>
    </div>

    <!-- What's New -->
    <div class="shuriken-about-section">
        <h2 class="section-title">
            <span class="dashicons dashicons-megaphone"></span>
            <?php esc_html_e('What\'s New in 1.7.5', 'shuriken-reviews'); ?>
        </h2>
        
        <div class="whats-new-content">
            <div class="new-feature-highlight">
                <h3><?php esc_html_e('Major Software Design Improvements', 'shuriken-reviews'); ?></h3>
                <ul class="new-features-list">
                    <li>
                        <strong><?php esc_html_e('Modular Architecture', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Refactored into 8 focused modules for better maintainability', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('20+ WordPress Hooks', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Complete extensibility with filters and actions', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Interfaces for Testing', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Mock implementations for unit testing without database', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Exception System', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('6 custom exception types with unified error handling', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Dependency Injection', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Flexible service container for better testability', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Unified Star Rating', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('Single filter supports any star count (3, 5, 10, etc.) with automatic normalization', 'shuriken-reviews'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Block & Shortcode Consistency', 'shuriken-reviews'); ?></strong>
                        <?php esc_html_e('All hooks work for both Gutenberg blocks and shortcodes', 'shuriken-reviews'); ?>
                    </li>
                </ul>
                <p class="new-features-note">
                    <?php esc_html_e('All improvements maintain 100% backward compatibility. Existing code continues to work unchanged.', 'shuriken-reviews'); ?>
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
                    <a href="https://github.com/qasedak/shuriken-reviews/blob/main/docs/hooks-reference.md" target="_blank" rel="noopener noreferrer" class="resource-link">
                        <?php esc_html_e('View Hooks Documentation', 'shuriken-reviews'); ?> →
                    </a>
                </div>
            </div>
            
            <div class="resource-card">
                <div class="resource-icon">
                    <span class="dashicons dashicons-testing"></span>
                </div>
                <div class="resource-content">
                    <h4><?php esc_html_e('Interfaces & Testing', 'shuriken-reviews'); ?></h4>
                    <p><?php esc_html_e('Test your code with mock implementations. No database required for unit tests. Interfaces available for Database and Analytics services.', 'shuriken-reviews'); ?></p>
                    <a href="https://github.com/qasedak/shuriken-reviews/blob/main/tests/README.md" target="_blank" rel="noopener noreferrer" class="resource-link">
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
                    <a href="https://github.com/qasedak/shuriken-reviews/blob/main/docs/dependency-injection.md" target="_blank" rel="noopener noreferrer" class="resource-link">
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
                    <a href="https://github.com/qasedak/shuriken-reviews/blob/main/includes/exceptions/README.md" target="_blank" rel="noopener noreferrer" class="resource-link">
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
                        <code>GET /wp-json/shuriken-reviews/v1/ratings/stats?ids=1,2,3</code>
                    </div>
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
                    '<a href="https://skilledup.ir" target="_blank" rel="noopener noreferrer">Skilledup Hub</a>'
                );
                ?>
            </p>
            <p class="license-info">
                <?php esc_html_e('Licensed under GPL v2 or later', 'shuriken-reviews'); ?>
            </p>
            <div class="social-links">
                <a href="https://skilledup.ir" target="_blank" rel="noopener noreferrer" class="social-link">
                    <span class="dashicons dashicons-admin-site"></span>
                    <?php esc_html_e('Website', 'shuriken-reviews'); ?>
                </a>
                <a href="https://github.com/qasedak/shuriken-reviews" target="_blank" rel="noopener noreferrer" class="social-link">
                    <span class="dashicons dashicons-github"></span>
                    <?php esc_html_e('GitHub', 'shuriken-reviews'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
/**
 * Shuriken Reviews REST API Bootstrap
 *
 * Thin entry point that wires the two focused controllers and registers
 * cross-cutting REST filters. All handler logic lives in the controllers.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_REST_API
 *
 * @since 1.7.0
 */
class Shuriken_REST_API {

    /**
     * @var Shuriken_REST_API|null Singleton instance
     */
    private static ?self $instance = null;

    /**
     * REST API namespace
     */
    const NAMESPACE = 'shuriken-reviews/v1';

    /**
     * @var Shuriken_Rating_Repository Rating repository
     */
    private readonly Shuriken_Rating_Repository $db;

    /**
     * @var Shuriken_REST_Ratings_Controller Rating endpoints
     */
    private readonly Shuriken_REST_Ratings_Controller $ratings_controller;

    /**
     * @var Shuriken_REST_Votes_Controller Stats/nonce endpoints
     */
    private readonly Shuriken_REST_Votes_Controller $votes_controller;

    /**
     * Constructor
     *
     * @param Shuriken_Rating_Repository|null $db Rating repository (optional, for dependency injection).
     */
    public function __construct(?Shuriken_Rating_Repository $db = null) {
        $this->db = $db ?: shuriken_ratings_repo();

        $this->ratings_controller = new Shuriken_REST_Ratings_Controller($this->db, self::NAMESPACE);
        $this->votes_controller   = new Shuriken_REST_Votes_Controller($this->db, self::NAMESPACE);

        add_action('rest_api_init', $this->register_routes(...));

        // Skip nonce verification for public endpoints BEFORE authentication runs
        add_filter('rest_authentication_errors', $this->rest_authentication_errors(...), 5, 1);

        // Clean stray PHP output before JSON is sent (prevents invalid_json errors)
        add_filter('rest_pre_serve_request', $this->clean_rest_buffer(...), 1, 4);

        // Add CDN-friendly no-cache headers to prevent Cloudflare from caching/transforming responses
        add_filter('rest_post_dispatch', $this->set_rest_cache_headers(...), 10, 3);
    }

    // =========================================================================
    // Singleton + Accessors
    // =========================================================================

    /**
     * Get singleton instance
     *
     * @return Shuriken_REST_API
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self(shuriken_ratings_repo());
        }
        return self::$instance;
    }

    /**
     * Get the rating repository
     *
     * @return Shuriken_Rating_Repository
     */
    public function get_db(): Shuriken_Rating_Repository {
        return $this->db;
    }

    /**
     * Initialize the REST API
     *
     * @return void
     */
    public static function init(): void {
        self::get_instance();
    }

    // =========================================================================
    // Route Registration (delegates to controllers)
    // =========================================================================

    /**
     * Register REST API routes
     *
     * @return void
     * @since 1.7.0
     */
    public function register_routes(): void {
        $this->ratings_controller->register_routes();
        $this->votes_controller->register_routes();
    }

    // =========================================================================
    // Cross-Cutting REST Filters
    // =========================================================================

    /**
     * Handle REST API authentication errors
     *
     * Bypasses nonce verification ONLY for Shuriken's public endpoints (/nonce and /ratings/stats)
     * that need to work without authentication (e.g., for cached pages with stale nonces).
     *
     * All other endpoints (including editor endpoints) use WordPress's default
     * cookie + nonce authentication, which the block editor handles automatically.
     *
     * @param WP_Error|null|bool $result Authentication result.
     * @return WP_Error|null|bool
     */
    public function rest_authentication_errors(\WP_Error|null|bool $result): \WP_Error|null|bool {
        // If there's already a result from a previous filter, respect it
        if ($result !== null) {
            return $result;
        }

        // Check if this is a request to our public endpoints
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';

        // Allow ONLY Shuriken's public endpoints to bypass nonce verification.
        // These endpoints use '__return_true' as permission_callback and must work
        // even when a cached page sends a stale nonce.
        if (strpos($request_uri, '/shuriken-reviews/v1/nonce') !== false ||
            strpos($request_uri, '/shuriken-reviews/v1/ratings/stats') !== false) {
            return true;
        }

        // Let WordPress handle authentication for all other endpoints
        return null;
    }

    /**
     * Clean output buffer before serving Shuriken REST responses
     *
     * Any stray PHP output (warnings, notices, whitespace from other plugins)
     * in the buffer will be prepended to the JSON body, making it unparseable.
     * This discards that pollution right before WordPress outputs the JSON.
     *
     * @param bool             $served  Whether the request has already been served.
     * @param WP_HTTP_Response $result  Result to send to the client.
     * @param WP_REST_Request  $request Request used to generate the response.
     * @param WP_REST_Server   $server  Server instance.
     * @return bool
     */
    public function clean_rest_buffer(bool $served, \WP_HTTP_Response $result, \WP_REST_Request $request, \WP_REST_Server $server): bool {
        if (strpos($request->get_route(), '/shuriken-reviews/') === 0) {
            if (ob_get_level() > 0 && ob_get_length() > 0) {
                ob_clean();
            }
        }
        return $served;
    }

    /**
     * Set cache-control headers for Shuriken REST API responses
     *
     * Prevents CDNs (especially Cloudflare) from caching or transforming
     * REST API responses. Without these headers Cloudflare may cache the
     * JSON or apply HTML-oriented features (Rocket Loader, Auto Minify,
     * Email Obfuscation) that corrupt the response body.
     *
     * @param WP_REST_Response $response Response object.
     * @param WP_REST_Server   $server   Server instance.
     * @param WP_REST_Request  $request  Request object.
     * @return WP_REST_Response
     */
    public function set_rest_cache_headers(\WP_REST_Response $response, \WP_REST_Server $server, \WP_REST_Request $request): \WP_REST_Response {
        if (strpos($request->get_route(), '/shuriken-reviews/') === 0) {
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->header('CDN-Cache-Control', 'no-store');
            $response->header('X-Content-Type-Options', 'nosniff');
        }
        return $response;
    }
}

/**
 * Helper function to get REST API instance
 *
 * @return Shuriken_REST_API
 */
function shuriken_rest_api(): Shuriken_REST_API {
    return Shuriken_REST_API::get_instance();
}

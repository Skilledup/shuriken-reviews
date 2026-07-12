<?php
/**
 * Dependency Injection Container for Shuriken Reviews
 *
 * Simple service container for managing plugin dependencies.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Container
 *
 * Lightweight dependency injection container.
 *
 * @since 1.7.0
 */
class Shuriken_Container {

    /**
     * @var Shuriken_Container Singleton instance
     */
    private static ?self $instance = null;

    /**
     * @var array<string, array{resolver: callable, singleton: bool}> Registered services
     */
    private array $services = array();

    /**
     * @var array<string, mixed> Service instances (singletons)
     */
    private array $instances = array();

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance(): self {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        $this->register_core_services();
    }

    /**
     * Register core plugin services
     *
     * Services are registered with their dependencies injected via the container.
     * This enables proper dependency injection and testability.
     *
     * @return void
     */
    private function register_core_services(): void {
        // Statistics cache (wp_cache_* adapter + invalidation hooks)
        $this->singleton('cache', function($container) {
            $db = Shuriken_Database::get_instance();
            return new Shuriken_Cache($db->get_wpdb(), $db->get_ratings_table());
        });

        // Rate-limit transients (portable options/object-cache adapter)
        $this->singleton('rate_limit_cache', function($container) {
            $db = Shuriken_Database::get_instance();
            return new Shuriken_Rate_Limit_Cache($db->get_wpdb());
        });

        // Register database service (foundation - wires cache into rating repository)
        $this->singleton('database', function($container) {
            $db = Shuriken_Database::get_instance();
            $db->get_rating_repository()->set_cache($container->get('cache'));
            return $db;
        });

        // Register individual repositories (extracted from database façade)
        $this->singleton('rating_repository', function($container) {
            return $container->get('database')->get_rating_repository();
        });

        $this->singleton('vote_repository', function($container) {
            return $container->get('database')->get_vote_repository();
        });

        $this->singleton('schema_manager', function($container) {
            return $container->get('database')->get_schema_manager();
        });

        // Register analytics service (depends on rating repository)
        $this->singleton('analytics', function($container) {
            return new Shuriken_Analytics($container->get('rating_repository'));
        });

        // Register voter analytics service (depends on database for raw wpdb)
        $this->singleton('voter_analytics', function($container) {
            $db = $container->get('database');
            return new Shuriken_Voter_Analytics($db->get_wpdb(), $db->get_ratings_table(), $db->get_votes_table());
        });

        // Register REST API service (depends on rating repository)
        $this->singleton('rest_api', function($container) {
            return new Shuriken_REST_API($container->get('rating_repository'));
        });

        // Register shortcodes service (depends on rating repository)
        $this->singleton('shortcodes', function($container) {
            return new Shuriken_Shortcodes($container->get('rating_repository'));
        });

        // Register block service (depends on rating repository)
        $this->singleton('block', function($container) {
            return new Shuriken_Block($container->get('rating_repository'));
        });

        // Register AJAX service (depends on rating + vote repositories)
        $this->singleton('ajax', function($container) {
            return new Shuriken_AJAX(
                $container->get('rating_repository'),
                $container->get('vote_repository')
            );
        });

        // Register rate limiter service (depends on vote repository + transient cache)
        $this->singleton('rate_limiter', function($container) {
            return new Shuriken_Rate_Limiter(
                $container->get('vote_repository'),
                $container->get('rate_limit_cache')
            );
        });

        // Register frontend service (no dependencies)
        $this->singleton('frontend', function($container) {
            return Shuriken_Frontend::get_instance();
        });

        // Request-scoped contextual stats batch collector (depends on rating repository)
        $this->singleton('contextual_stats_collector', function($container) {
            return new Shuriken_Contextual_Stats_Collector($container->get('rating_repository'));
        });

        // Register admin service (depends on rating repository + analytics)
        $this->singleton('admin', function($container) {
            return new Shuriken_Admin(
                $container->get('rating_repository'),
                $container->get('analytics')
            );
        });
    }

    /**
     * Register a service
     *
     * @param string   $name     Service name.
     * @param callable $resolver Function that creates the service.
     * @return void
     */
    public function bind(string $name, callable $resolver): void {
        $this->services[$name] = array(
            'resolver' => $resolver,
            'singleton' => false
        );
    }

    /**
     * Register a singleton service
     *
     * @param string   $name     Service name.
     * @param callable $resolver Function that creates the service.
     * @return void
     */
    public function singleton(string $name, callable $resolver): void {
        $this->services[$name] = array(
            'resolver' => $resolver,
            'singleton' => true
        );
    }

    /**
     * Resolve a service
     *
     * @param string $name Service name.
     * @return mixed Service instance.
     * @throws Exception If service not found.
     */
    public function get(string $name): mixed {
        if (!isset($this->services[$name])) {
            throw new \RuntimeException("Service '{$name}' not found in container");
        }

        $service = $this->services[$name];

        // Return existing instance if singleton
        if ($service['singleton'] && isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        // Resolve the service
        $instance = call_user_func($service['resolver'], $this);

        // Store instance if singleton
        if ($service['singleton']) {
            $this->instances[$name] = $instance;
        }

        return $instance;
    }

    /**
     * Check if service exists
     *
     * @param string $name Service name.
     * @return bool
     */
    public function has(string $name): bool {
        return isset($this->services[$name]);
    }

    /**
     * Set a service instance directly
     *
     * Useful for testing - inject mocks.
     *
     * @param string $name     Service name.
     * @param mixed  $instance Service instance.
     * @return void
     */
    public function set(string $name, mixed $instance): void {
        $this->instances[$name] = $instance;
        
        // Also register it as a singleton that returns this instance
        $this->singleton($name, function() use ($instance) {
            return $instance;
        });
    }

    /**
     * Magic method to get services as properties
     *
     * @param string $name Service name.
     * @return mixed
     */
    public function __get(string $name): mixed {
        return $this->get($name);
    }

    /**
     * Reset the container (mainly for testing)
     *
     * @return void
     */
    public function reset(): void {
        $this->instances = array();
    }
}

/**
 * Get the container instance
 *
 * @return Shuriken_Container
 */
function shuriken_container(): Shuriken_Container {
    return Shuriken_Container::get_instance();
}

/**
 * Get the database service
 *
 * Backward compatibility wrapper.
 *
 * @return Shuriken_Database_Interface
 */
function shuriken_db(): Shuriken_Database_Interface {
    return shuriken_container()->get('database');
}

/**
 * Get the analytics service
 *
 * Backward compatibility wrapper.
 *
 * @return Shuriken_Analytics_Interface
 */
function shuriken_analytics(): Shuriken_Analytics_Interface {
    return shuriken_container()->get('analytics');
}

/**
 * Get the voter analytics service
 *
 * @return Shuriken_Voter_Analytics_Interface
 */
function shuriken_voter_analytics(): Shuriken_Voter_Analytics_Interface {
    return shuriken_container()->get('voter_analytics');
}

/**
 * Get the rating repository
 *
 * @return Shuriken_Rating_Repository
 * @since 1.15.5
 */
function shuriken_ratings_repo(): Shuriken_Rating_Repository {
    return shuriken_container()->get('rating_repository');
}

/**
 * Get the vote repository
 *
 * @return Shuriken_Vote_Repository
 * @since 1.15.5
 */
function shuriken_votes_repo(): Shuriken_Vote_Repository {
    return shuriken_container()->get('vote_repository');
}

/**
 * Get the schema manager
 *
 * @return Shuriken_Schema_Manager
 * @since 1.15.5
 */
function shuriken_schema_manager(): Shuriken_Schema_Manager {
    return shuriken_container()->get('schema_manager');
}

/**
 * Get the contextual stats collector for SSR batch pre-fetch.
 *
 * @return Shuriken_Contextual_Stats_Collector
 * @since 1.15.7
 */
function shuriken_contextual_stats_collector(): Shuriken_Contextual_Stats_Collector {
    return shuriken_container()->get('contextual_stats_collector');
}

/**
 * Get the statistics cache service.
 *
 * @return Shuriken_Cache_Interface
 * @since 1.15.7
 */
function shuriken_cache(): Shuriken_Cache_Interface {
    return shuriken_container()->get('cache');
}

/**
 * Get the rate-limit transient cache service.
 *
 * @return Shuriken_Rate_Limit_Cache_Interface
 * @since 1.15.6
 */
function shuriken_rate_limit_cache(): Shuriken_Rate_Limit_Cache_Interface {
    return shuriken_container()->get('rate_limit_cache');
}


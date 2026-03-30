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
        // Register database service (foundation - no dependencies)
        $this->singleton('database', function($container) {
            return Shuriken_Database::get_instance();
        });

        // Register analytics service (depends on database)
        $this->singleton('analytics', function($container) {
            return new Shuriken_Analytics($container->get('database'));
        });

        // Register voter analytics service (depends on database)
        $this->singleton('voter_analytics', function($container) {
            return new Shuriken_Voter_Analytics($container->get('database'));
        });

        // Register REST API service (depends on database)
        $this->singleton('rest_api', function($container) {
            return new Shuriken_REST_API($container->get('database'));
        });

        // Register shortcodes service (depends on database)
        $this->singleton('shortcodes', function($container) {
            return new Shuriken_Shortcodes($container->get('database'));
        });

        // Register block service (depends on database)
        $this->singleton('block', function($container) {
            return new Shuriken_Block($container->get('database'));
        });

        // Register AJAX service (depends on database)
        $this->singleton('ajax', function($container) {
            return new Shuriken_AJAX($container->get('database'));
        });

        // Register rate limiter service (depends on database)
        $this->singleton('rate_limiter', function($container) {
            return new Shuriken_Rate_Limiter($container->get('database'));
        });

        // Register frontend service (no dependencies)
        $this->singleton('frontend', function($container) {
            return Shuriken_Frontend::get_instance();
        });

        // Register admin service (depends on database + analytics)
        $this->singleton('admin', function($container) {
            return new Shuriken_Admin(
                $container->get('database'),
                $container->get('analytics')
            );
        });

        // Register post meta service (depends on database)
        $this->singleton('post_meta', function($container) {
            return new Shuriken_Post_Meta($container->get('database'));
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


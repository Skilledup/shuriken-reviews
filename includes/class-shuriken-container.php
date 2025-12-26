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
    private static $instance = null;

    /**
     * @var array Registered services
     */
    private $services = array();

    /**
     * @var array Service instances (singletons)
     */
    private $instances = array();

    /**
     * Get singleton instance
     *
     * @return Shuriken_Container
     */
    public static function get_instance() {
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
     * @return void
     */
    private function register_core_services() {
        // Register database service
        $this->singleton('database', function($container) {
            return Shuriken_Database::get_instance();
        });

        // Register analytics service (inject database dependency)
        $this->singleton('analytics', function($container) {
            return new Shuriken_Analytics($container->get('database'));
        });

        // Register REST API service
        $this->singleton('rest_api', function($container) {
            return Shuriken_REST_API::get_instance();
        });

        // Register shortcodes service
        $this->singleton('shortcodes', function($container) {
            return Shuriken_Shortcodes::get_instance();
        });

        // Register block service
        $this->singleton('block', function($container) {
            return Shuriken_Block::get_instance();
        });

        // Register AJAX service
        $this->singleton('ajax', function($container) {
            return Shuriken_AJAX::get_instance();
        });

        // Register frontend service
        $this->singleton('frontend', function($container) {
            return Shuriken_Frontend::get_instance();
        });

        // Register admin service
        $this->singleton('admin', function($container) {
            return Shuriken_Admin::get_instance();
        });
    }

    /**
     * Register a service
     *
     * @param string   $name     Service name.
     * @param callable $resolver Function that creates the service.
     * @return void
     */
    public function bind($name, $resolver) {
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
    public function singleton($name, $resolver) {
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
    public function get($name) {
        if (!isset($this->services[$name])) {
            throw new Exception("Service '{$name}' not found in container");
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
    public function has($name) {
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
    public function set($name, $instance) {
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
    public function __get($name) {
        return $this->get($name);
    }

    /**
     * Reset the container (mainly for testing)
     *
     * @return void
     */
    public function reset() {
        $this->instances = array();
    }
}

/**
 * Get the container instance
 *
 * @return Shuriken_Container
 */
function shuriken_container() {
    return Shuriken_Container::get_instance();
}

/**
 * Get the database service
 *
 * Backward compatibility wrapper.
 *
 * @return Shuriken_Database_Interface
 */
function shuriken_db() {
    return shuriken_container()->get('database');
}

/**
 * Get the analytics service
 *
 * Backward compatibility wrapper.
 *
 * @return Shuriken_Analytics_Interface
 */
function shuriken_analytics() {
    return shuriken_container()->get('analytics');
}


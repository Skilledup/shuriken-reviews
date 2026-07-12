<?php
/**
 * Shuriken Reviews Rate Limit Cache
 *
 * Stores rolling vote counters and per-rating cooldown timestamps in WordPress
 * transients so the optimization works with both the options table and a
 * persistent object-cache drop-in.
 *
 * @package Shuriken_Reviews
 * @since 1.15.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Rate_Limit_Cache
 *
 * @since 1.15.6
 */
class Shuriken_Rate_Limit_Cache implements Shuriken_Rate_Limit_Cache_Interface {

    private const WINDOWS = array('hourly', 'daily');
    private const GENERATION_OPTION = 'shuriken_rate_limit_cache_generation';

    private ?string $generation = null;

    /**
     * Constructor.
     *
     * @param \wpdb $wpdb WordPress database instance.
     */
    public function __construct(
        private readonly \wpdb $wpdb,
    ) {
        add_action(
            'update_option_shuriken_rate_limiting_enabled',
            $this->handle_rate_limiting_toggled(...),
            10,
            2
        );
    }

    /**
     * @inheritDoc
     */
    public function get_counter(int $user_id, ?string $user_ip, string $window): array|false {
        if (!$this->is_enabled() || !$this->is_valid_window($window)) {
            return false;
        }

        $count_key = $this->counter_key($user_id, $user_ip, $window);
        $reset_key = $this->counter_reset_key($user_id, $user_ip, $window);
        $count = get_transient($count_key);
        $resets_at = get_transient($reset_key);
        $now = $this->now();

        if (!is_numeric($count) || !is_numeric($resets_at) || (int) $resets_at <= $now) {
            $this->delete_counter($user_id, $user_ip, $window);
            return false;
        }

        return array(
            'count'     => max(0, (int) $count),
            'resets_at' => (int) $resets_at,
        );
    }

    /**
     * @inheritDoc
     */
    public function set_counter(int $user_id, ?string $user_ip, string $window, int $count, int $resets_at): bool {
        if (!$this->is_enabled() || !$this->is_valid_window($window)) {
            return false;
        }

        $ttl = max(1, $resets_at - $this->now());
        $count_set = set_transient(
            $this->counter_key($user_id, $user_ip, $window),
            max(0, $count),
            $ttl
        );
        $reset_set = set_transient(
            $this->counter_reset_key($user_id, $user_ip, $window),
            $resets_at,
            $ttl
        );

        if (!$count_set || !$reset_set) {
            $this->delete_counter($user_id, $user_ip, $window);
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function increment_counter(int $user_id, ?string $user_ip, string $window): void {
        if (!$this->is_enabled() || !$this->is_valid_window($window)) {
            return;
        }

        $counter = $this->get_counter($user_id, $user_ip, $window);
        if ($counter === false) {
            return;
        }

        $key = $this->counter_key($user_id, $user_ip, $window);

        if (wp_using_ext_object_cache()) {
            $result = wp_cache_incr($key, 1, 'transient');
        } else {
            $option_name = '_transient_' . $key;
            $result = $this->wpdb->query($this->wpdb->prepare(
                "UPDATE {$this->wpdb->options}
                 SET option_value = CAST(option_value AS UNSIGNED) + 1
                 WHERE option_name = %s",
                $option_name
            ));

            if ($result === 1) {
                wp_cache_delete($option_name, 'options');
            }
        }

        if ($result === false || $result === 0) {
            $this->delete_counter($user_id, $user_ip, $window);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete_counters(int $user_id, ?string $user_ip): void {
        foreach (self::WINDOWS as $window) {
            $this->delete_counter($user_id, $user_ip, $window);
        }
    }

    /**
     * @inheritDoc
     */
    public function get_cooldown(int $rating_id, int $user_id, ?string $user_ip, ?int $context_id, ?string $context_type): string|false {
        if (!$this->is_enabled()) {
            return false;
        }

        $value = get_transient($this->cooldown_key(
            $rating_id,
            $user_id,
            $user_ip,
            $context_id,
            $context_type
        ));

        return is_string($value) ? $value : false;
    }

    /**
     * @inheritDoc
     */
    public function set_cooldown(int $rating_id, int $user_id, ?string $user_ip, ?int $context_id, ?string $context_type, ?string $last_vote_time, int $ttl): bool {
        if (!$this->is_enabled() || $ttl <= 0) {
            return false;
        }

        return set_transient(
            $this->cooldown_key(
                $rating_id,
                $user_id,
                $user_ip,
                $context_id,
                $context_type
            ),
            $last_vote_time ?? '',
            max(1, $ttl)
        );
    }

    /**
     * Rotate cache keys when rate limiting is toggled.
     *
     * Votes written while rate limiting is disabled do not maintain counters.
     * A new generation prevents those stale entries from being reused if the
     * setting is enabled again before their normal expiry.
     *
     * @param mixed $old_value Previous option value.
     * @param mixed $new_value New option value.
     * @return void
     */
    public function handle_rate_limiting_toggled(mixed $old_value, mixed $new_value): void {
        if ($old_value === $new_value) {
            return;
        }

        $this->generation = wp_generate_uuid4();
        update_option(self::GENERATION_OPTION, $this->generation, false);
    }

    /**
     * Delete one rolling-window counter and its reset timestamp.
     *
     * @param int         $user_id User ID (0 for guests).
     * @param string|null $user_ip Guest IP address.
     * @param string      $window  Window name.
     * @return void
     */
    private function delete_counter(int $user_id, ?string $user_ip, string $window): void {
        delete_transient($this->counter_key($user_id, $user_ip, $window));
        delete_transient($this->counter_reset_key($user_id, $user_ip, $window));
    }

    /**
     * Build a fixed-length transient name without exposing guest IP addresses.
     *
     * @param string $type  Cache entry type.
     * @param mixed  ...$parts Stable identity parts.
     * @return string
     */
    private function key(string $type, mixed ...$parts): string {
        return 'shuriken_rl_' . $type . '_' . hash_hmac(
            'sha256',
            wp_json_encode(array($this->get_generation(), $parts)),
            wp_salt('auth')
        );
    }

    /**
     * Get the current cache-key generation.
     *
     * @return string
     */
    private function get_generation(): string {
        if ($this->generation !== null) {
            return $this->generation;
        }

        $generation = (string) get_option(self::GENERATION_OPTION, '');
        if ($generation === '') {
            $generation = wp_generate_uuid4();
            if (!add_option(self::GENERATION_OPTION, $generation, '', false)) {
                $generation = (string) get_option(self::GENERATION_OPTION, $generation);
            }
        }

        $this->generation = $generation;
        return $this->generation;
    }

    /**
     * Build a counter transient key.
     *
     * @param int         $user_id User ID.
     * @param string|null $user_ip Guest IP.
     * @param string      $window  Window name.
     * @return string
     */
    private function counter_key(int $user_id, ?string $user_ip, string $window): string {
        return $this->key('count', $this->identity($user_id, $user_ip), $window);
    }

    /**
     * Build a counter reset transient key.
     *
     * @param int         $user_id User ID.
     * @param string|null $user_ip Guest IP.
     * @param string      $window  Window name.
     * @return string
     */
    private function counter_reset_key(int $user_id, ?string $user_ip, string $window): string {
        return $this->key('reset', $this->identity($user_id, $user_ip), $window);
    }

    /**
     * Build a cooldown transient key.
     *
     * @param int         $rating_id    Rating ID.
     * @param int         $user_id      User ID.
     * @param string|null $user_ip      Guest IP.
     * @param int|null    $context_id   Context ID.
     * @param string|null $context_type Context type.
     * @return string
     */
    private function cooldown_key(int $rating_id, int $user_id, ?string $user_ip, ?int $context_id, ?string $context_type): string {
        return $this->key(
            'cooldown',
            $rating_id,
            $this->identity($user_id, $user_ip),
            $context_id ?? 0,
            $context_type ?? 'global'
        );
    }

    /**
     * Build the member or guest identity used in cache keys.
     *
     * @param int         $user_id User ID.
     * @param string|null $user_ip Guest IP.
     * @return string
     */
    private function identity(int $user_id, ?string $user_ip): string {
        return $user_id > 0 ? 'user:' . $user_id : 'guest:' . ($user_ip ?? '');
    }

    /**
     * Validate a supported rolling window.
     *
     * @param string $window Window name.
     * @return bool
     */
    private function is_valid_window(string $window): bool {
        return in_array($window, self::WINDOWS, true);
    }

    /**
     * Get a timestamp in the same site-local frame used by rate-limit queries.
     *
     * @return int
     */
    private function now(): int {
        return strtotime(current_time('mysql'));
    }

    /**
     * Whether rate-limit transient caching is enabled.
     *
     * @return bool
     */
    private function is_enabled(): bool {
        /**
         * Filter whether rate-limit transients are enabled.
         *
         * @since 1.15.6
         * @param bool $enabled Whether transient caching is enabled. Default true.
         */
        return (bool) apply_filters('shuriken_rate_limit_cache_enabled', true);
    }
}

<?php
/**
 * Regression tests: analytics demoralize / low-performer / momentum fixes
 *
 * Covers:
 *   (a) Momentum excludes binary rating types (like_dislike, approval)
 *   (b) Inversion CASE expression scopes to the sub-rating vote (not the parent's direct votes)
 *   (c) Low-performer icon/type rendering: numeric uses hash icon, binary uses no icon
 *   (d) Binary types (like_dislike, approval) are excluded from threshold-based ranking
 *       (top-rated and low-performers)
 *
 * These are standalone unit tests — no WordPress bootstrap or database required.
 * They exercise only pure PHP logic (SQL string generation, formatter, enum helpers).
 *
 * How to run (PHP CLI):
 *   php tests/test-analytics-demoralize.php
 *
 * Expected output on success:
 *   All X tests passed.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// ---------------------------------------------------------------------------
// Minimal stubs so the classes can be loaded outside WordPress
// ---------------------------------------------------------------------------

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

// Stub the wpdb class (only prepare() is needed)
if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public function prepare(string $sql, ...$args): string {
            // Very thin stub – just returns the raw SQL for assertion purposes.
            return $sql;
        }
    }
}

// ---------------------------------------------------------------------------
// Load plugin classes under test (order matters – dependencies first)
// ---------------------------------------------------------------------------

$plugin_dir = dirname(__DIR__);

// Stub the interface so class-shuriken-database.php can be parsed without error.
require_once $plugin_dir . '/includes/interfaces/interface-shuriken-database.php';
require_once $plugin_dir . '/includes/class-shuriken-database.php';
require_once $plugin_dir . '/includes/enum-shuriken-rating-type.php';
require_once $plugin_dir . '/includes/class-shuriken-icons.php';
require_once $plugin_dir . '/includes/traits/trait-shuriken-analytics-helpers.php';
require_once $plugin_dir . '/includes/class-shuriken-analytics-ranking.php';
require_once $plugin_dir . '/includes/class-shuriken-analytics-formatter.php';

// ---------------------------------------------------------------------------
// Tiny test harness
// ---------------------------------------------------------------------------

$tests_run    = 0;
$tests_failed = 0;

function assert_true(bool $condition, string $message): void {
    global $tests_run, $tests_failed;
    $tests_run++;
    if (!$condition) {
        $tests_failed++;
        echo "FAIL: {$message}\n";
    }
}

function assert_false(bool $condition, string $message): void {
    assert_true(!$condition, $message);
}

function assert_contains(string $needle, string $haystack, string $message): void {
    assert_true(str_contains($haystack, $needle), $message . " (expected to find '{$needle}')");
}

function assert_not_contains(string $needle, string $haystack, string $message): void {
    assert_false(str_contains($haystack, $needle), $message . " (expected NOT to find '{$needle}')");
}

function assert_equals(mixed $expected, mixed $actual, string $message): void {
    assert_true($expected === $actual, $message . " (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")");
}

// ---------------------------------------------------------------------------
// (a) Momentum query excludes binary types
//     Verify the SQL string built by get_momentum_items() contains BOTH
//     'like_dislike' and 'approval' in the NOT IN exclusion list.
// ---------------------------------------------------------------------------

// We test the constant SQL fragment that was changed (line ~1320 in class-shuriken-analytics.php).
// We confirm it no longer only excludes 'approval' alone.
$momentum_exclusion_fragment = "r.rating_type NOT IN ('like_dislike', 'approval')";

// Verify the fix exists in the actual source file:
$analytics_source = file_get_contents($plugin_dir . '/includes/class-shuriken-analytics.php');

assert_contains(
    $momentum_exclusion_fragment,
    $analytics_source,
    '(a) Momentum SQL excludes like_dislike together with approval'
);

assert_not_contains(
    "NOT IN ('approval')",
    $analytics_source,
    '(a) Momentum SQL no longer has the incomplete exclusion of only approval'
);

// ---------------------------------------------------------------------------
// (b) Inversion CASE scopes to sub-rating votes (v.rating_id = sub.id)
// ---------------------------------------------------------------------------

$inversion_sql = Shuriken_Analytics_Ranking::get_inversion_sql('sub', 'v');

assert_contains(
    'v.rating_id = sub.id AND sub.effect_type = \'negative\' AND sub.rating_type IN (\'like_dislike\', \'approval\')',
    $inversion_sql,
    '(b) Binary inversion CASE condition includes v.rating_id = sub.id guard'
);

assert_contains(
    'v.rating_id = sub.id AND sub.effect_type = \'negative\'',
    $inversion_sql,
    '(b) Scaled inversion CASE condition includes v.rating_id = sub.id guard'
);

// With custom aliases the same pattern must hold:
$inversion_sql_r = Shuriken_Analytics_Ranking::get_inversion_sql('r', 'v');

assert_contains(
    'v.rating_id = r.id AND r.effect_type = \'negative\'',
    $inversion_sql_r,
    '(b) Inversion SQL uses the supplied rating alias in the vote-id guard'
);

// The old, unguarded form must NOT appear:
assert_not_contains(
    "WHEN sub.effect_type = 'negative'",
    $inversion_sql,
    '(b) Old unguarded WHEN clause is gone'
);

// ---------------------------------------------------------------------------
// (c) Low-performer icon rendering: numeric → hash, binary → no icon
//     We test this indirectly by inspecting the source of admin/analytics.php.
// ---------------------------------------------------------------------------

$analytics_admin_source = file_get_contents($plugin_dir . '/admin/analytics.php');

// The fix should check for Numeric and render 'hash':
assert_contains(
    "Shuriken_Rating_Type::Numeric",
    $analytics_admin_source,
    '(c) analytics.php low-performer cell checks for Numeric type'
);

assert_contains(
    "'hash'",
    $analytics_admin_source,
    '(c) analytics.php low-performer cell renders hash icon for numeric ratings'
);

// The unconditional star-for-all-non-binary pattern must be gone (was a single isBinary check):
assert_not_contains(
    "if (!(Shuriken_Rating_Type::tryFrom(\$item->rating_type ?? 'stars') ?? Shuriken_Rating_Type::Stars)->isBinary()) : ?><span class=\"star-display low\">",
    $analytics_admin_source,
    '(c) Old single isBinary guard for star icon is replaced with type-specific logic'
);

// ---------------------------------------------------------------------------
// (d) Binary types excluded from threshold rankings
//     Check ranking source for the binary exclusion condition.
// ---------------------------------------------------------------------------

$ranking_source = file_get_contents($plugin_dir . '/includes/class-shuriken-analytics-ranking.php');

assert_contains(
    "rating_type NOT IN ('like_dislike', 'approval')",
    $ranking_source,
    '(d) Ranking cached query excludes binary types when threshold is applied'
);

// The exclusion must appear in BOTH get_ranked_cached and get_ranked_filtered:
// Search for the function definitions specifically (not the call-site references).
$cached_def_pos   = strpos($ranking_source, 'function get_ranked_cached');
$filtered_def_pos = strpos($ranking_source, 'function get_ranked_filtered');

$exclusion_needle = "rating_type NOT IN ('like_dislike', 'approval')";
$first_occ  = strpos($ranking_source, $exclusion_needle, $cached_def_pos);
$second_occ = strpos($ranking_source, $exclusion_needle, $filtered_def_pos);

assert_true(
    $first_occ !== false,
    '(d) Binary exclusion present in get_ranked_cached section'
);

assert_true(
    $second_occ !== false && $second_occ !== $first_occ,
    '(d) Binary exclusion also present in get_ranked_filtered section'
);

// ---------------------------------------------------------------------------
// (e) format_average_display formatter sanity checks
//     Ensures binary types produce percentage strings, not numeric averages.
// ---------------------------------------------------------------------------

// Stub dependencies used by the formatter
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string { return $text; }
}
if (!function_exists('number_format_i18n')) {
    function number_format_i18n(float $number, int $decimals = 0): string {
        return number_format($number, $decimals);
    }
}

$formatter = new Shuriken_Analytics_Formatter();

// like_dislike: 8 likes out of 10 → 80% positive
$result = $formatter->format_average_display(0.8, 'like_dislike', 1, 10, 8);
assert_contains('80%', $result, '(e) like_dislike formatter shows percentage');
assert_contains('positive', $result, '(e) like_dislike formatter shows "positive" label');

// approval: 7 out of 10 → 70% approved
$result = $formatter->format_average_display(0.7, 'approval', 1, 10, 7);
assert_contains('70%', $result, '(e) approval formatter shows percentage');
assert_contains('approved', $result, '(e) approval formatter shows "approved" label');

// stars: internal average 3.5 (stored on 0–5 scale, displayed on scale of 5) → "3.5/5"
$result = $formatter->format_average_display(3.5, 'stars', 5, 10, 7);
assert_contains('3.5', $result, '(e) stars formatter shows decimal average');
assert_contains('/5', $result, '(e) stars formatter shows scale denominator');

// numeric at scale 10: denormalize_average(avg, scale) = round((avg / RATING_SCALE_DEFAULT) * scale, 1)
// RATING_SCALE_DEFAULT = 5. Internal average 3.0 → (3.0/5)*10 = 6.0 → displayed as "6.0/10".
$result = $formatter->format_average_display(3.0, 'numeric', 10, 10, 6);
assert_contains('/10', $result, '(e) numeric formatter shows correct scale denominator');

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

if ($tests_failed === 0) {
    echo "All {$tests_run} tests passed.\n";
    exit(0);
} else {
    echo "{$tests_failed} of {$tests_run} tests FAILED.\n";
    exit(1);
}

<?php
/**
 * Shuriken Reviews Analytics Formatter
 *
 * Stateless display formatting methods extracted from Shuriken_Analytics.
 * Handles rating display, date formatting, and vote value rendering.
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Shuriken_Analytics_Formatter
 *
 * @since 1.15.5
 */
class Shuriken_Analytics_Formatter {

    /**
     * Get human-readable label for the current date range
     *
     * @param string|int|array $date_range Date range value
     * @return string Human-readable label
     */
    public function get_date_range_label(string|int|array $date_range): string {
        if (is_array($date_range)) {
            $start = !empty($date_range['start']) ? date_i18n(get_option('date_format'), strtotime($date_range['start'])) : '';
            $end = !empty($date_range['end']) ? date_i18n(get_option('date_format'), strtotime($date_range['end'])) : '';
            
            if ($start && $end) {
                return sprintf(__('%s to %s', 'shuriken-reviews'), $start, $end);
            } elseif ($start) {
                return sprintf(__('From %s', 'shuriken-reviews'), $start);
            } elseif ($end) {
                return sprintf(__('Until %s', 'shuriken-reviews'), $end);
            }
            return __('All Time', 'shuriken-reviews');
        }
        
        return match ((string) $date_range) {
            '7' => __('Last 7 Days', 'shuriken-reviews'),
            '30' => __('Last 30 Days', 'shuriken-reviews'),
            '90' => __('Last 90 Days', 'shuriken-reviews'),
            '365' => __('Last Year', 'shuriken-reviews'),
            default => __('All Time', 'shuriken-reviews'),
        };
    }

    /**
     * Format an average rating value for display, adapting to rating type
     *
     * Stars/numeric: "3.5/5"
     * Like/dislike: "72% positive"
     * Approval: "85% approved"
     *
     * @param float  $average      The average value
     * @param string $rating_type  Rating type
     * @param int    $scale        Rating scale
     * @param int    $total_votes  Total votes
     * @param int    $total_rating Total rating sum
     * @return string Formatted display string
     */
    public function format_average_display(float $average, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT, int $total_votes = 0, int $total_rating = 0): string {
        $type_enum = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;

        if ($type_enum === Shuriken_Rating_Type::LikeDislike) {
            $pct = $total_votes > 0 ? round(($total_rating / $total_votes) * 100) : 0;
            return $pct . '% ' . __('positive', 'shuriken-reviews');
        }
        if ($type_enum === Shuriken_Rating_Type::Approval) {
            $pct = $total_votes > 0 ? round(($total_rating / $total_votes) * 100) : 0;
            return $pct . '% ' . __('approved', 'shuriken-reviews');
        }
        $display_avg = Shuriken_Database::denormalize_average((float) $average, $scale);
        return number_format($display_avg, 1) . '/' . intval($scale);
    }

    /**
     * Render a vote value for display in tables, adapting to rating type
     *
     * Stars: filled/empty star SVG icons
     * Numeric: X/N format
     * Like/dislike: thumbs-up or thumbs-down SVG icon
     * Approval: thumbs-up SVG icon
     *
     * @param float|int $rating_value The vote value
     * @param string    $rating_type  Rating type
     * @param int       $scale        Rating scale
     * @return string HTML display string
     */
    public function format_vote_display(float|int $rating_value, string $rating_type = 'stars', int $scale = Shuriken_Database::RATING_SCALE_DEFAULT): string {
        $symbols = Shuriken_Icons::rating_symbols(14);
        $type_enum = Shuriken_Rating_Type::tryFrom($rating_type) ?? Shuriken_Rating_Type::Stars;

        if ($type_enum === Shuriken_Rating_Type::LikeDislike) {
            return intval($rating_value) === 1 ? $symbols['thumbs_up'] : $symbols['thumbs_down'];
        }
        if ($type_enum === Shuriken_Rating_Type::Approval) {
            return $symbols['thumbs_up'];
        }
        $s = (int) $scale;
        $display_value = (int) round(((float) $rating_value / Shuriken_Database::RATING_SCALE_DEFAULT) * $s);
        if ($type_enum === Shuriken_Rating_Type::Numeric) {
            return $display_value . '/' . $s;
        }
        $out = '';
        for ($i = 1; $i <= $s; $i++) {
            if ($i <= $display_value) {
                $out .= '<span class="svg-star filled">' . $symbols['star_filled'] . '</span>';
            } else {
                $out .= '<span class="svg-star empty">' . $symbols['star_empty'] . '</span>';
            }
        }
        return $out;
    }

    /**
     * Format time ago string with proper timezone handling
     *
     * @param string $mysql_date MySQL datetime string
     * @return string Formatted "X time ago" string
     */
    public function format_time_ago(string $mysql_date): string {
        if (empty($mysql_date)) {
            return '-';
        }
        return human_time_diff(mysql2date('U', $mysql_date), current_time('timestamp')) 
               . ' ' . __('ago', 'shuriken-reviews');
    }

    /**
     * Format date with WordPress settings
     *
     * @param string $mysql_date   MySQL datetime string
     * @param bool   $include_time Whether to include time
     * @return string Formatted date string
     */
    public function format_date(string $mysql_date, bool $include_time = true): string {
        if (empty($mysql_date)) {
            return '-';
        }
        $format = get_option('date_format');
        if ($include_time) {
            $format .= ' ' . get_option('time_format');
        }
        return mysql2date($format, $mysql_date);
    }
}

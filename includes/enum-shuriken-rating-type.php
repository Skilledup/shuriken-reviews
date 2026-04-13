<?php
/**
 * Shuriken Reviews Rating Type Enum
 *
 * Backed enum replacing raw string rating types throughout the plugin.
 * Serialises to/from the existing VARCHAR(20) column via ->value / ::from().
 *
 * @package Shuriken_Reviews
 * @since 1.15.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rating type backed enum.
 *
 * @since 1.15.0
 */
enum Shuriken_Rating_Type: string {

    case Stars      = 'stars';
    case LikeDislike = 'like_dislike';
    case Numeric    = 'numeric';
    case Approval   = 'approval';

    /**
     * Whether this type uses binary (0/1) vote values.
     *
     * Binary types (like_dislike, approval) store 0 or 1 per vote.
     * Continuous types (stars, numeric) normalize votes to an internal scale.
     *
     * @return bool
     */
    public function isBinary(): bool {
        return match ($this) {
            self::LikeDislike, self::Approval => true,
            default => false,
        };
    }

    /**
     * Maximum allowed display scale for this type.
     *
     * @return int
     */
    public function maxScale(): int {
        return match ($this) {
            self::LikeDislike, self::Approval => 1,
            self::Numeric => Shuriken_Database::NUMERIC_SCALE_MAX,
            self::Stars   => Shuriken_Database::STARS_SCALE_MAX,
        };
    }

    /**
     * Constrain the given scale to this type's valid range.
     *
     * Binary types always return 1. Stars and numeric clamp between
     * SCALE_MIN and their respective max.
     *
     * @param int $scale Raw scale value.
     * @return int Clamped scale.
     */
    public function constrainScale(int $scale): int {
        if ($this->isBinary()) {
            return 1;
        }

        return max(Shuriken_Database::SCALE_MIN, min($this->maxScale(), $scale));
    }

    /**
     * Get all valid type values as a plain array of strings.
     *
     * Useful for REST API enum definitions and legacy validation.
     *
     * @return string[]
     */
    public static function values(): array {
        return array_map(
            static fn(self $case) => $case->value,
            self::cases()
        );
    }

    /**
     * Type class label: 'binary' or 'continuous'.
     *
     * @return string
     */
    public function typeClass(): string {
        return $this->isBinary() ? 'binary' : 'continuous';
    }
}

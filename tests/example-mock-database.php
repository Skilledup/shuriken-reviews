<?php
/**
 * Example Mock Database Implementation
 *
 * This is an example of how the Shuriken_Database_Interface can be used
 * for testing purposes. You can create mock implementations that return
 * predictable test data without touching the actual database.
 *
 * @package Shuriken_Reviews
 * @since 1.7.0
 */

/**
 * Class Mock_Shuriken_Database
 *
 * Example mock implementation for testing.
 *
 * @since 1.7.0
 */
class Mock_Shuriken_Database implements Shuriken_Database_Interface {

    /**
     * @var array In-memory storage for ratings
     */
    private $ratings = [];

    /**
     * @var array In-memory storage for votes
     */
    private $votes = [];

    /**
     * @var int Auto-increment ID counter
     */
    private $next_id = 1;

    /**
     * Constructor - can be initialized with test data
     *
     * @param array $test_ratings Optional test ratings.
     * @param array $test_votes   Optional test votes.
     */
    public function __construct($test_ratings = [], $test_votes = []) {
        $this->ratings = $test_ratings;
        $this->votes = $test_votes;
        
        if (!empty($test_ratings)) {
            $this->next_id = max(array_column($test_ratings, 'id')) + 1;
        }
    }

    /**
     * Get a single rating by ID
     */
    public function get_rating($rating_id) {
        foreach ($this->ratings as $rating) {
            if ($rating->id === $rating_id) {
                return $rating;
            }
        }
        return null;
    }

    /**
     * Get all ratings
     */
    public function get_all_ratings($orderby = 'id', $order = 'DESC') {
        $ratings = $this->ratings;
        
        // Simple sorting
        usort($ratings, function($a, $b) use ($orderby, $order) {
            $result = $a->$orderby <=> $b->$orderby;
            return $order === 'DESC' ? -$result : $result;
        });
        
        return $ratings;
    }

    /**
     * Get paginated ratings
     */
    public function get_ratings_paginated($per_page = 20, $page = 1, $search = '', $orderby = 'id', $order = 'DESC') {
        $all_ratings = $this->get_all_ratings($orderby, $order);
        
        // Apply search filter
        if (!empty($search)) {
            $all_ratings = array_filter($all_ratings, function($rating) use ($search) {
                return stripos($rating->name, $search) !== false;
            });
        }
        
        $total = count($all_ratings);
        $offset = ($page - 1) * $per_page;
        $ratings = array_slice($all_ratings, $offset, $per_page);
        
        return [
            'ratings' => $ratings,
            'total' => $total,
            'total_pages' => ceil($total / $per_page)
        ];
    }

    /**
     * Create a new rating
     */
    public function create_rating($name, $parent_id = null, $effect_type = 'positive', $display_only = false, $mirror_of = null) {
        $id = $this->next_id++;
        
        $rating = (object) [
            'id' => $id,
            'name' => $name,
            'total_votes' => 0,
            'total_rating' => 0,
            'parent_id' => $parent_id,
            'effect_type' => $effect_type,
            'display_only' => $display_only,
            'mirror_of' => $mirror_of,
            'date_created' => current_time('mysql'),
            'average' => 0,
            'source_id' => $mirror_of ?: $id
        ];
        
        $this->ratings[] = $rating;
        return $id;
    }

    /**
     * Update a rating
     */
    public function update_rating($rating_id, $data) {
        foreach ($this->ratings as &$rating) {
            if ($rating->id === $rating_id) {
                foreach ($data as $key => $value) {
                    $rating->$key = $value;
                }
                
                // Recalculate average if totals changed
                if (isset($data['total_votes']) || isset($data['total_rating'])) {
                    $rating->average = $rating->total_votes > 0 
                        ? round($rating->total_rating / $rating->total_votes, 1) 
                        : 0;
                }
                
                return true;
            }
        }
        return false;
    }

    /**
     * Delete a rating and its votes
     */
    public function delete_rating($rating_id) {
        // Remove rating
        $this->ratings = array_filter($this->ratings, function($rating) use ($rating_id) {
            return $rating->id !== $rating_id;
        });
        
        // Remove associated votes
        $this->votes = array_filter($this->votes, function($vote) use ($rating_id) {
            return $vote->rating_id !== $rating_id;
        });
        
        return true;
    }

    /**
     * Get sub-ratings of a parent rating
     */
    public function get_sub_ratings($parent_id) {
        return array_filter($this->ratings, function($rating) use ($parent_id) {
            return $rating->parent_id === $parent_id;
        });
    }

    /**
     * Get all parent ratings
     */
    public function get_parent_ratings($exclude_id = null) {
        return array_filter($this->ratings, function($rating) use ($exclude_id) {
            return $rating->parent_id === null 
                && $rating->mirror_of === null 
                && $rating->id !== $exclude_id;
        });
    }

    /**
     * Recalculate parent rating totals
     */
    public function recalculate_parent_rating($parent_id) {
        $sub_ratings = $this->get_sub_ratings($parent_id);
        
        $total_votes = 0;
        $total_rating = 0;
        
        foreach ($sub_ratings as $sub) {
            if ($sub->total_votes > 0) {
                $total_votes += $sub->total_votes;
                
                if ($sub->effect_type === 'negative') {
                    $inverted_rating = ($sub->total_votes * 6) - $sub->total_rating;
                    $total_rating += $inverted_rating;
                } else {
                    $total_rating += $sub->total_rating;
                }
            }
        }
        
        return $this->update_rating($parent_id, [
            'total_votes' => $total_votes,
            'total_rating' => $total_rating
        ]);
    }

    /**
     * Get ratings that can be mirrored
     */
    public function get_mirrorable_ratings($exclude_id = null) {
        return array_filter($this->ratings, function($rating) use ($exclude_id) {
            return $rating->mirror_of === null && $rating->id !== $exclude_id;
        });
    }

    /**
     * Get mirrors of a rating
     */
    public function get_mirrors($rating_id) {
        return array_filter($this->ratings, function($rating) use ($rating_id) {
            return $rating->mirror_of === $rating_id;
        });
    }

    /**
     * Get user's vote for a rating
     */
    public function get_user_vote($rating_id, $user_id, $user_ip = null) {
        foreach ($this->votes as $vote) {
            if ($vote->rating_id === $rating_id) {
                if ($user_id > 0 && $vote->user_id === $user_id) {
                    return $vote;
                }
                if ($user_id === 0 && $vote->user_ip === $user_ip) {
                    return $vote;
                }
            }
        }
        return null;
    }

    /**
     * Create a new vote
     */
    public function create_vote($rating_id, $rating_value, $user_id = 0, $user_ip = null) {
        $vote = (object) [
            'id' => count($this->votes) + 1,
            'rating_id' => $rating_id,
            'rating_value' => $rating_value,
            'user_id' => $user_id,
            'user_ip' => $user_ip,
            'date_created' => current_time('mysql')
        ];
        
        $this->votes[] = $vote;
        
        // Update rating totals
        foreach ($this->ratings as &$rating) {
            if ($rating->id === $rating_id) {
                $rating->total_votes++;
                $rating->total_rating += $rating_value;
                $rating->average = round($rating->total_rating / $rating->total_votes, 1);
                break;
            }
        }
        
        return true;
    }

    /**
     * Update an existing vote
     */
    public function update_vote($vote_id, $rating_id, $old_value, $new_value) {
        foreach ($this->votes as &$vote) {
            if ($vote->id === $vote_id) {
                $vote->rating_value = $new_value;
                
                // Update rating totals
                foreach ($this->ratings as &$rating) {
                    if ($rating->id === $rating_id) {
                        $rating->total_rating = $rating->total_rating - $old_value + $new_value;
                        $rating->average = round($rating->total_rating / $rating->total_votes, 1);
                        break;
                    }
                }
                
                return true;
            }
        }
        return false;
    }

    /**
     * Create database tables (no-op for mock)
     */
    public function create_tables() {
        return true;
    }

    /**
     * Check if database tables exist (always true for mock)
     */
    public function tables_exist() {
        return true;
    }

    /**
     * Get the ratings table name
     */
    public function get_ratings_table() {
        return 'mock_ratings';
    }

    /**
     * Get the votes table name
     */
    public function get_votes_table() {
        return 'mock_votes';
    }
}

/**
 * Example Usage:
 *
 * // Create a mock database with test data
 * $test_ratings = [
 *     (object) [
 *         'id' => 1,
 *         'name' => 'Test Rating',
 *         'total_votes' => 10,
 *         'total_rating' => 45,
 *         'average' => 4.5,
 *         'parent_id' => null,
 *         'effect_type' => 'positive',
 *         'display_only' => false,
 *         'mirror_of' => null,
 *         'source_id' => 1,
 *         'date_created' => '2024-01-01 00:00:00'
 *     ]
 * ];
 *
 * $mock_db = new Mock_Shuriken_Database($test_ratings);
 *
 * // Now you can test your code without touching the real database
 * $rating = $mock_db->get_rating(1);
 * assert($rating->name === 'Test Rating');
 * assert($rating->average === 4.5);
 *
 * // Test creating a vote
 * $mock_db->create_vote(1, 5, 123);
 * $updated_rating = $mock_db->get_rating(1);
 * assert($updated_rating->total_votes === 11);
 */


<?php
/**
 * Vote history table partial for admin pages
 *
 * Renders the standard vote history table card used by item-stats and
 * context-stats pages: wrapper div, heading, description, sortable column
 * headers, vote rows (with rating display, voter cell, IP, date), and
 * pagination include.
 *
 * @var array                        $votes              Array of vote row objects
 * @var Shuriken_Analytics_Interface $analytics          Analytics instance
 * @var object                       $rating             Rating object (fallback type/scale)
 * @var string                       $votes_sort_by      Current sort column
 * @var string                       $votes_sort_order   Current sort direction
 * @var string                       $sort_base_url      Base URL for sort links
 * @var int                          $total_votes_count  Total number of votes
 * @var int                          $offset             Current offset
 * @var int                          $per_page           Per-page count
 * @var int                          $total_pages        Total pages
 * @var int                          $current_page       Current page number
 * @var bool                         $show_source_column Whether to show Source column (default false)
 * @var int                          $rating_id          Rating ID for Source column logic (required when $show_source_column is true)
 * @var string                       $empty_message      Empty state message (default 'No votes recorded yet')
 *
 * @package Shuriken_Reviews
 * @since 1.15.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$show_source_column = $show_source_column ?? false;
$empty_message      = $empty_message ?? __( 'No votes recorded yet', 'shuriken-reviews' );
$col_count          = $show_source_column ? 6 : 5;
?>
<div class="shuriken-table-card full-width">
    <h2>
        <?php Shuriken_Icons::render( 'list', array( 'width' => 18, 'height' => 18 ) ); ?>
        <?php esc_html_e( 'Vote History', 'shuriken-reviews' ); ?>
    </h2>
    <p class="table-description">
        <?php printf(
            esc_html__( 'Showing %1$d-%2$d of %3$d votes', 'shuriken-reviews' ),
            min( $offset + 1, $total_votes_count ),
            min( $offset + $per_page, $total_votes_count ),
            $total_votes_count
        ); ?>
    </p>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th class="column-id"><?php esc_html_e( 'ID', 'shuriken-reviews' ); ?></th>
                <th class="column-rating"><?php echo shuriken_sort_link( 'rating', $votes_sort_by, $votes_sort_order, $sort_base_url, __( 'Rating', 'shuriken-reviews' ), 'votes_sort_by', 'votes_sort_order' ); ?></th>
                <?php if ( $show_source_column ) : ?>
                <th class="column-source"><?php esc_html_e( 'Source', 'shuriken-reviews' ); ?></th>
                <?php endif; ?>
                <th class="column-voter"><?php esc_html_e( 'Voter', 'shuriken-reviews' ); ?></th>
                <th class="column-ip"><?php esc_html_e( 'IP Address', 'shuriken-reviews' ); ?></th>
                <th class="column-date"><?php echo shuriken_sort_link( 'date', $votes_sort_by, $votes_sort_order, $sort_base_url, __( 'Date & Time', 'shuriken-reviews' ), 'votes_sort_by', 'votes_sort_order' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( $votes ) : ?>
                <?php foreach ( $votes as $vote ) : ?>
                    <tr>
                        <td class="column-id"><?php echo esc_html( $vote->id ); ?></td>
                        <td class="column-rating">
                            <span class="star-rating-display">
                                <?php echo $analytics->format_vote_display( $vote->rating_value, $vote->rating_type ?? $rating->rating_type ?? 'stars', $vote->scale ?? $rating->scale ?? 5 ); ?>
                            </span>
                            <?php
                            $vote_type      = $vote->rating_type ?? $rating->rating_type ?? 'stars';
                            $vote_type_enum = Shuriken_Rating_Type::tryFrom( $vote_type ) ?? Shuriken_Rating_Type::Stars;
                            if ( ! $vote_type_enum->isBinary() ) :
                                $vote_scale  = $vote->scale ?? $rating->scale ?? 5;
                                $denorm_vote = round( ( (float) $vote->rating_value / Shuriken_Database::RATING_SCALE_DEFAULT ) * $vote_scale, 1 );
                            ?>
                            <span class="rating-number">(<?php echo esc_html( $denorm_vote ); ?>)</span>
                            <?php endif; ?>
                        </td>
                        <?php if ( $show_source_column ) : ?>
                        <td class="column-source">
                            <?php if ( (int) $vote->rating_id === (int) $rating_id ) : ?>
                                <span class="source-badge direct" title="<?php esc_attr_e( 'Direct vote on parent', 'shuriken-reviews' ); ?>">
                                    <?php Shuriken_Icons::render( 'star', array( 'width' => 14, 'height' => 14 ) ); ?>
                                    <?php esc_html_e( 'Direct', 'shuriken-reviews' ); ?>
                                </span>
                            <?php else : ?>
                                <span class="source-badge sub" title="<?php echo esc_attr( $vote->rating_name ); ?>">
                                    <?php Shuriken_Icons::render( 'arrow-right', array( 'width' => 14, 'height' => 14 ) ); ?>
                                    <?php echo esc_html( $vote->rating_name ); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td class="column-voter">
                            <?php shuriken_render_voter_cell( $vote ); ?>
                        </td>
                        <td class="column-ip">
                            <?php if ( empty( $vote->user_ip ) ) : ?>
                                <em><?php esc_html_e( 'N/A', 'shuriken-reviews' ); ?></em>
                            <?php else : ?>
                                <code><?php echo esc_html( $vote->user_ip ); ?></code>
                            <?php endif; ?>
                        </td>
                        <td class="column-date">
                            <?php echo esc_html( $analytics->format_date( $vote->date_created ) ); ?>
                            <br>
                            <small class="timeago"><?php echo esc_html( $analytics->format_time_ago( $vote->date_created ) ); ?></small>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="<?php echo $col_count; ?>"><?php echo esc_html( $empty_message ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php
    $total_count = $total_votes_count;
    include __DIR__ . '/pagination.php';
    ?>
</div>

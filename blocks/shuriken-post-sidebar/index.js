/**
 * Shuriken Reviews — Post Sidebar Panel
 *
 * Registers a PluginDocumentSettingPanel that displays per-post rating
 * statistics when editing a post/page that has contextual votes.
 *
 * @package Shuriken_Reviews
 * @since   1.15.0
 */
( function () {
    'use strict';

    const el           = wp.element.createElement;
    const useState     = wp.element.useState;
    const useEffect    = wp.element.useEffect;
    const useSelect    = wp.data.useSelect;
    const registerPlugin = wp.plugins.registerPlugin;
    const PluginDocumentSettingPanel = wp.editPost
        ? wp.editPost.PluginDocumentSettingPanel
        : ( wp.editor && wp.editor.PluginDocumentSettingPanel )
            ? wp.editor.PluginDocumentSettingPanel
            : null;
    const Spinner      = wp.components.Spinner;
    const Icon         = wp.components.Icon;
    const apiFetch     = wp.apiFetch;
    const __           = wp.i18n.__;

    if ( ! PluginDocumentSettingPanel ) {
        return; // Not available in this editor context (e.g. widgets).
    }

    /**
     * Format a rating display string based on type.
     */
    const formatRating = ( item ) => {
        if ( item.rating_type === 'like_dislike' ) {
            const likes    = item.total;
            const dislikes = item.votes - likes;
            return `+${likes}  -${dislikes}`;
        }
        if ( item.rating_type === 'approval' ) {
            return `${item.votes} ${__( 'votes', 'shuriken-reviews' )}`;
        }
        return `${item.average} / ${item.scale}  (${item.votes})`;
    };

    /**
     * Sidebar panel component.
     */
    const ShurikenPostSidebar = () => {
        const postId   = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
        const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );

        const stateData    = useState( null );
        const stateLoading = useState( false );
        const stateError   = useState( null );

        const data    = stateData[0],    setData    = stateData[1];
        const loading = stateLoading[0], setLoading = stateLoading[1];
        const error   = stateError[0],   setError   = stateError[1];

        useEffect( () => {
            if ( ! postId || ! postType ) {
                return;
            }
            setLoading( true );
            setError( null );
            apiFetch( {
                path: `/shuriken-reviews/v1/context-stats?context_id=${postId}&context_type=${postType}`,
            } ).then( ( res ) => {
                setData( res );
                setLoading( false );
            } ).catch( ( err ) => {
                setError( err.message || __( 'Failed to load', 'shuriken-reviews' ) );
                setLoading( false );
            } );
        }, [ postId, postType ] );

        // Don't render the panel at all until we know whether there is data.
        if ( ! loading && ( ! data || data.length === 0 ) && ! error ) {
            return null; // nothing to show
        }

        return el(
            PluginDocumentSettingPanel,
            {
                name:      'shuriken-post-ratings',
                title:     __( 'Shuriken Ratings', 'shuriken-reviews' ),
                icon:      'star-filled',
                className: 'shuriken-post-sidebar',
            },
            loading
                ? el( 'div', { className: 'shuriken-sidebar-loading' },
                    el( Spinner ),
                    ' ',
                    __( 'Loading…', 'shuriken-reviews' )
                  )
                : error
                    ? el( 'p', { className: 'shuriken-sidebar-error' }, error )
                    : el( 'div', { className: 'shuriken-sidebar-list' },
                        data.map( ( item ) => {
                            return el( 'div', { key: item.id, className: 'shuriken-sidebar-item' },
                                el( 'div', { className: 'shuriken-sidebar-item-name' }, item.name ),
                                el( 'div', { className: 'shuriken-sidebar-item-stats' }, formatRating( item ) )
                            );
                        } )
                      )
        );
    };

    registerPlugin( 'shuriken-post-sidebar', {
        render: ShurikenPostSidebar,
        icon:   'star-filled',
    } );
} )();

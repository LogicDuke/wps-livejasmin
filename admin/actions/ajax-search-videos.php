<?php
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Search for videos in Ajax or PHP call, now supporting performer CSV searches.
 */
function lvjm_normalize_performer_name( $name ) {
    $normalized = strtolower( trim( (string) $name ) );
    $normalized = str_replace( ' ', '', $normalized );
    return preg_replace( '/[^a-z0-9]/', '', $normalized );
}

function lvjm_find_model_video_ids( $performer_query ) {
    $performer_query = lvjm_normalize_performer_name( $performer_query );
    if ( '' === $performer_query ) {
        return array(
            'model_name' => '',
            'video_ids'  => array(),
        );
    }

    $file_path = dirname( __DIR__, 2 ) . '/data/models_with_video_ids.csv';
    if ( ! file_exists( $file_path ) ) {
        // CSV missing: log for debugging.
        error_log( '[TMW-FIX] models_with_video_ids.csv is missing at ' . $file_path );
        return array(
            'model_name' => '',
            'video_ids'  => array(),
        );
    }

    $handle = fopen( $file_path, 'r' );
    if ( false === $handle ) {
        // CSV unreadable: log for debugging.
        error_log( '[TMW-FIX] Unable to read models_with_video_ids.csv at ' . $file_path );
        return array(
            'model_name' => '',
            'video_ids'  => array(),
        );
    }

    $is_header     = true;

    while ( false !== ( $row = fgetcsv( $handle ) ) ) {
        if ( $is_header ) {
            $is_header = false;
            continue;
        }
        if ( empty( $row[0] ) || empty( $row[2] ) ) {
            continue;
        }
        $model_name     = trim( (string) $row[0] );
        $model_name_lc  = lvjm_normalize_performer_name( $model_name );
        $video_ids_raw  = (string) $row[2];
        $video_ids_list = array_filter( array_map( 'trim', preg_split( '/\s*\|\s*/', $video_ids_raw ) ) );

        if ( $model_name_lc === $performer_query ) {
            fclose( $handle );
            return array(
                'model_name' => $model_name,
                'video_ids'  => $video_ids_list,
            );
        }
    }

    fclose( $handle );

    return array(
        'model_name' => '',
        'video_ids'  => array(),
    );
}

function lvjm_load_vpapi_master_index() {
    static $index = null;

    if ( null !== $index ) {
        return $index;
    }

    $index     = array();
    $file_path = dirname( __DIR__, 2 ) . '/data/vpapi_master.csv';
    if ( ! file_exists( $file_path ) ) {
        // CSV missing: log for debugging.
        error_log( '[TMW-FIX] vpapi_master.csv is missing at ' . $file_path );
        return $index;
    }

    $handle = fopen( $file_path, 'r' );
    if ( false === $handle ) {
        // CSV unreadable: log for debugging.
        error_log( '[TMW-FIX] Unable to read vpapi_master.csv at ' . $file_path );
        return $index;
    }

    $is_header = true;
    while ( false !== ( $row = fgetcsv( $handle ) ) ) {
        if ( $is_header ) {
            $is_header = false;
            continue;
        }

        if ( empty( $row[0] ) ) {
            continue;
        }

        $video_id = trim( (string) $row[0] );
        $index[ $video_id ] = array(
            'video_id'   => $video_id,
            'model_name' => isset( $row[1] ) ? trim( (string) $row[1] ) : '',
            'title'      => isset( $row[2] ) ? trim( (string) $row[2] ) : '',
            'tags'       => isset( $row[3] ) ? trim( (string) $row[3] ) : '',
        );
    }

    fclose( $handle );

    return $index;
}

function lvjm_get_partner_existing_ids( $partner_id ) {
    if ( empty( $partner_id ) ) {
        return array();
    }

    global $wpdb;

    $custom_post_type = xbox_get_field_value( 'lvjm-options', 'custom-video-post-type' );
    $custom_post_type = '' !== $custom_post_type ? $custom_post_type : 'post';

    $query_str = "
        SELECT wposts.ID, wpostmetaVideoId.meta_value videoId
        FROM $wpdb->posts wposts, $wpdb->postmeta wpostmetasponsor, $wpdb->postmeta wpostmetaVideoId
        WHERE wposts.ID = wpostmetasponsor.post_id
        AND ( wpostmetasponsor.meta_key = 'partner' AND wpostmetasponsor.meta_value = %s )
        AND (wposts.ID =  wpostmetaVideoId.post_id AND wpostmetaVideoId.meta_key = 'video_id')
        AND wposts.post_type = %s
    ";

    $bdd_videos                  = $wpdb->get_results( $wpdb->prepare( $query_str, $partner_id, $custom_post_type ), OBJECT );
    $partner_existing_videos_ids = array();
    foreach ( (array) $bdd_videos as $bdd_video ) {
        $partner_existing_videos_ids[] = (string) $bdd_video->videoId;
    }
    unset( $bdd_videos );

    $partner_unwanted_videos_ids = array();
    $unwanted_videos_ids         = WPSCORE()->get_product_option( 'LVJM', 'removed_videos_ids' );
    if ( isset( $unwanted_videos_ids[ $partner_id ] ) && is_array( $unwanted_videos_ids[ $partner_id ] ) ) {
        $partner_unwanted_videos_ids = $unwanted_videos_ids[ $partner_id ];
    }
    unset( $unwanted_videos_ids );

    return array_map( 'strval', array_merge( $partner_existing_videos_ids, $partner_unwanted_videos_ids ) );
}

function lvjm_fetch_video_details_cached( $video_id, $partner_id, $locale = '' ) {
    $video_id = (string) $video_id;
    if ( '' === $video_id ) {
        return array();
    }

    $transient_key = 'lvjm_vpapi_details_' . $video_id;
    $cached        = get_transient( $transient_key );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    $saved_partner_options = WPSCORE()->get_product_option( 'LVJM', 'livejasmin_options' );
    $json_feed_url         = isset( $saved_partner_options['json_feed_url'] ) ? $saved_partner_options['json_feed_url'] : '';
    if ( '' === $json_feed_url ) {
        return array();
    }

    $parsed_url = wp_parse_url( $json_feed_url );
    if ( empty( $parsed_url['path'] ) ) {
        return array();
    }

    $path = $parsed_url['path'];
    if ( false !== strpos( $path, '/client/list' ) ) {
        $path = str_replace( '/client/list', '/client/details/' . rawurlencode( $video_id ), $path );
    } else {
        $path = rtrim( $path, '/' ) . '/client/details/' . rawurlencode( $video_id );
    }
    $parsed_url['path'] = $path;

    $scheme   = isset( $parsed_url['scheme'] ) ? $parsed_url['scheme'] . '://' : '';
    $host     = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
    $port     = isset( $parsed_url['port'] ) ? ':' . $parsed_url['port'] : '';
    $user     = isset( $parsed_url['user'] ) ? $parsed_url['user'] : '';
    $pass     = isset( $parsed_url['pass'] ) ? ':' . $parsed_url['pass'] : '';
    $pass     = ( $user || $pass ) ? "$pass@" : '';
    $query    = isset( $parsed_url['query'] ) ? '?' . $parsed_url['query'] : '';
    $fragment = isset( $parsed_url['fragment'] ) ? '#' . $parsed_url['fragment'] : '';

    $details_url = "$scheme$user$pass$host$port$path$query$fragment";
    if ( '' !== $locale ) {
        $details_url .= ( false === strpos( $details_url, '?' ) ? '?' : '&' ) . 'locale=' . rawurlencode( $locale );
    }

    $args = array(
        'timeout'   => 300,
        'sslverify' => false,
    );

    $response = wp_remote_get( $details_url, $args );
    if ( is_wp_error( $response ) ) {
        return array();
    }

    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $response_body ) ) {
        return array();
    }

    set_transient( $transient_key, $response_body, 12 * HOUR_IN_SECONDS );

    return $response_body;
}

function lvjm_search_videos( $params = '' ) {
    $ajax_call = '' === $params;

    if ( $ajax_call ) {
        check_ajax_referer( 'ajax-nonce', 'nonce' );
        $params = $_POST;
    }

    $errors = array();
    $videos = array();

    $performer_raw     = isset( $params['performer'] ) ? (string) $params['performer'] : '';
    $performer         = sanitize_text_field( $performer_raw );
    $category_id       = '';
    if ( isset( $params['category_id'] ) ) {
        $category_id = (string) $params['category_id'];
    } elseif ( isset( $params['cat_s'] ) ) {
        $category_id = (string) $params['cat_s'];
    } elseif ( isset( $params['category'] ) ) {
        $category_id = (string) $params['category'];
    }

    $is_performer_csv = '' !== $performer && 'all_straight' === $category_id;

    if ( $is_performer_csv ) {
        $match            = lvjm_find_model_video_ids( $performer );
        $vpapi_index      = lvjm_load_vpapi_master_index();
        $existing_ids     = lvjm_get_partner_existing_ids( isset( $params['partner']['id'] ) ? $params['partner']['id'] : '' );
        $existing_lookup  = array_fill_keys( $existing_ids, true );
        $video_ids        = isset( $match['video_ids'] ) ? $match['video_ids'] : array();
        $performer_name   = isset( $match['model_name'] ) && '' !== $match['model_name'] ? $match['model_name'] : $performer;
        $performer_label  = '' !== trim( $performer_raw ) ? trim( $performer_raw ) : $performer_name;
        $limit            = isset( $params['limit'] ) ? intval( $params['limit'] ) : 60;
        $limit            = min( 60, max( 0, $limit ) );
        $added            = 0;
        $thumb_url        = plugin_dir_url( dirname( __DIR__, 2 ) . '/wps-livejasmin.php' ) . 'admin/assets/img/loading-thumb.gif';
        $video_ids        = array_values( array_unique( array_map( 'strval', $video_ids ) ) );

        if ( function_exists( 'WPSCORE' ) ) {
            WPSCORE()->write_log( 'info', '[TMW-FIX] Performer CSV search for ' . $performer_name . '.', __FILE__, __LINE__ );
        }

        foreach ( $video_ids as $video_id ) {
            $video_id = (string) $video_id;
            if ( '' === $video_id ) {
                continue;
            }
            if ( isset( $existing_lookup[ $video_id ] ) ) {
                continue;
            }
            $detail = isset( $vpapi_index[ $video_id ] ) ? $vpapi_index[ $video_id ] : array();
            $actors = ! empty( $detail['model_name'] ) ? $detail['model_name'] : $performer_label;
            $videos[] = array(
                'id'               => $video_id,
                'title'            => isset( $detail['title'] ) ? $detail['title'] : '',
                'tags'             => isset( $detail['tags'] ) ? $detail['tags'] : '',
                'actors'           => $actors,
                'actors_names'     => $performer_label,
                'actors_img'       => '',
                'categories_names' => '',
                'duration'         => 0,
                'thumb_url'        => $thumb_url,
                'thumbs_urls'      => array(),
                'trailer_url'      => '',
                'tracking_url'     => '',
                'quality'          => '',
                'isHd'             => false,
                'uploader'         => '',
                'video_url'        => '',
                'url'              => '',
                'checked'          => false,
                'desc'             => '',
                'embed'            => '',
            );
            ++$added;
            if ( $added >= $limit ) {
                break;
            }
        }

        if ( empty( $match['model_name'] ) ) {
            $errors[] = 'No matching performer found in the CSV.';
        } elseif ( empty( $videos ) ) {
            $errors[] = 'No videos available after filtering imported/removed IDs.';
        }

        if ( $ajax_call ) {
            wp_send_json(
                array(
                    'videos'        => $videos,
                    'errors'        => $errors,
                    'searched_data' => array(
                        'mode'      => 'performer_csv',
                        'performer' => $performer_label,
                        'count'     => count( $videos ),
                    ),
                )
            );
            wp_die();
        }

        return $videos;
    }

    $search_videos = new LVJM_Search_Videos( $params );
    if ( ! $search_videos->has_errors() ) {
        $videos = $search_videos->get_videos();
    }

    // Performer filtering
    if ( '' !== $performer ) {
        $filtered = array();
        if ( ! function_exists( 'lvjm_get_embed_and_actors' ) ) {
            $actions_file = dirname( __FILE__ ) . '/ajax-get-embed-and-actors.php';
            if ( file_exists( $actions_file ) ) {
                require_once $actions_file;
            }
        }
        foreach ( (array) $videos as $v ) {
            $match = false;
            $actors = isset($v['actors']) ? (string)$v['actors'] : '';
            if ( '' !== $actors && false !== stripos( $actors, $performer ) ) {
                $match = true;
            } elseif ( function_exists( 'lvjm_get_embed_and_actors' ) && isset( $v['id'] ) ) {
                try {
                    $more = lvjm_get_embed_and_actors( array( 'video_id' => $v['id'] ) );
                    if ( ! empty( $more['performer_name'] ) && false !== stripos( $more['performer_name'], $performer ) ) {
                        $match = true;
                        $v['actors'] = $more['performer_name'];
                    }
                } catch ( \Throwable $e ) {}
            }
            if ( $match ) {
                $filtered[] = $v;
            }
        }
        $videos = $filtered;
    }

    if ( ! $ajax_call ) {
        return $videos;
    }

    wp_send_json(array(
        'videos'        => $videos,
        'errors'        => $errors,
    ));

    wp_die();
}
add_action( 'wp_ajax_lvjm_search_videos', 'lvjm_search_videos' );

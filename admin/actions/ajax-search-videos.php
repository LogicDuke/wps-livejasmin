<?php
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

require_once dirname( __DIR__ ) . '/includes/vpapi-helpers.php';

/**
 * Search for videos in Ajax or PHP call, now supporting performer CSV searches.
 */
function lvjm_normalize_performer_name( $name ) {
    $normalized = strtolower( trim( (string) $name ) );
    $normalized = str_replace( ' ', '', $normalized );
    return preg_replace( '/[^a-z0-9]/', '', $normalized );
}

function lvjm_debug_importer_enabled() {
    return defined( 'LVJM_DEBUG_IMPORTER' ) && LVJM_DEBUG_IMPORTER;
}

function lvjm_log_importer_video_debug( $context, $video ) {
    static $logged = array();

    if ( isset( $logged[ $context ] ) || ! lvjm_debug_importer_enabled() ) {
        return;
    }
    $logged[ $context ] = true;

    $keys         = is_array( $video ) ? array_keys( $video ) : array();
    $thumbs_urls  = isset( $video['thumbs_urls'] ) && is_array( $video['thumbs_urls'] ) ? $video['thumbs_urls'] : array();
    $checked      = isset( $video['checked'] ) ? $video['checked'] : null;
    $thumb_url    = isset( $video['thumb_url'] ) ? $video['thumb_url'] : '';
    $trailer_url  = isset( $video['trailer_url'] ) ? $video['trailer_url'] : '';

    $message = sprintf(
        '[TMW-FIX] Importer debug (%s): keys=[%s] checked=%s thumb_url=%s trailer_url=%s thumbs_urls_count=%d',
        $context,
        implode( ',', $keys ),
        var_export( $checked, true ),
        $thumb_url,
        $trailer_url,
        count( $thumbs_urls )
    );

    if ( function_exists( 'WPSCORE' ) ) {
        WPSCORE()->write_log( 'info', $message, __FILE__, __LINE__ );
    } else {
        error_log( $message );
    }
}

function lvjm_normalize_thumb_urls( $thumbs_urls ) {
    $normalized = array();
    foreach ( (array) $thumbs_urls as $thumb ) {
        $normalized[] = function_exists( 'lvjm_https_url' ) ? lvjm_https_url( $thumb ) : $thumb;
    }
    return array_values( array_unique( array_filter( $normalized ) ) );
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
        $partner_id       = isset( $params['partner']['id'] ) ? $params['partner']['id'] : '';
        $locale           = isset( $params['partner']['locale'] ) ? $params['partner']['locale'] : ( isset( $params['locale'] ) ? $params['locale'] : '' );
        $existing_ids     = lvjm_get_partner_existing_ids( $partner_id );
        $existing_lookup  = array_fill_keys( $existing_ids, true );
        $video_ids        = isset( $match['video_ids'] ) ? $match['video_ids'] : array();
        $performer_name   = isset( $match['model_name'] ) && '' !== $match['model_name'] ? $match['model_name'] : $performer;
        $performer_label  = '' !== trim( $performer_raw ) ? trim( $performer_raw ) : $performer_name;
        $limit            = isset( $params['limit'] ) ? intval( $params['limit'] ) : 60;
        $limit            = min( 60, max( 0, $limit ) );
        $added            = 0;
        $loading_thumb_url = plugin_dir_url( dirname( __DIR__, 2 ) . '/wps-livejasmin.php' ) . 'admin/assets/img/loading-thumb.gif';
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
            $details_payload = lvjm_fetch_video_details_cached( $video_id, $partner_id, $locale );
            $details_video   = lvjm_extract_vpapi_video_object( $details_payload );

            if ( empty( $details_video ) ) {
                $detail_keys = is_array( $details_payload ) ? implode( ',', array_keys( $details_payload ) ) : 'non-array';
                $message     = '[TMW-FIX] VPAPI details missing for video_id ' . $video_id . ' (keys: ' . $detail_keys . ').';
                if ( function_exists( 'WPSCORE' ) ) {
                    WPSCORE()->write_log( 'warning', $message, __FILE__, __LINE__ );
                } elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( $message );
                }
            }

            $thumbs_urls = lvjm_collect_vpapi_thumbs_urls( $details_video );
            $thumbs_urls = lvjm_normalize_thumb_urls( $thumbs_urls );
            $thumb_url   = lvjm_https_url( lvjm_get_vpapi_detail_value( $details_video, array( 'thumbUrl', 'thumb_url', 'thumbURL', 'thumb' ) ) );
            if ( '' === $thumb_url && ! empty( $thumbs_urls ) ) {
                $thumb_url = $thumbs_urls[0];
            }
            if ( '' === $thumb_url ) {
                $thumb_url = $loading_thumb_url;
            }

            $trailer_url  = lvjm_https_url( lvjm_get_vpapi_detail_value( $details_video, array( 'trailerUrl', 'trailer_url', 'trailer' ) ) );
            $tracking_url = lvjm_https_url( lvjm_get_vpapi_detail_value( $details_video, array( 'trackingUrl', 'tracking_url' ) ) );
            $video_url    = lvjm_https_url( lvjm_get_vpapi_detail_value( $details_video, array( 'videoUrl', 'video_url', 'url' ) ) );
            $duration     = lvjm_get_vpapi_detail_value( $details_video, array( 'duration', 'length' ), 0 );
            $quality      = lvjm_get_vpapi_detail_value( $details_video, array( 'quality' ) );
            $is_hd        = lvjm_get_vpapi_detail_value( $details_video, array( 'isHd', 'is_hd', 'hd' ), false );
            $description  = lvjm_get_vpapi_detail_value( $details_video, array( 'description', 'desc' ) );
            if ( is_string( $is_hd ) ) {
                $is_hd = filter_var( $is_hd, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
                if ( null === $is_hd ) {
                    $is_hd = false;
                }
            } else {
                $is_hd = (bool) $is_hd;
            }

            $video_entry = LVJM_Item::get_data_for_json(
                array(
                    'id'            => $video_id,
                    'title'         => isset( $detail['title'] ) ? $detail['title'] : '',
                    'desc'          => $description,
                    'tags'          => isset( $detail['tags'] ) ? $detail['tags'] : '',
                    'length'        => is_numeric( $duration ) ? (int) $duration : 0,
                    'length_format' => 'ss',
                    'thumb_url'     => $thumb_url,
                    'thumbs_urls'   => $thumbs_urls,
                    'trailer_url'   => $trailer_url,
                    'video_url'     => $video_url,
                    'tracking_url'  => $tracking_url,
                    'quality'       => $quality,
                    'isHd'          => $is_hd,
                    'uploader'      => '',
                    'code'          => '',
                    'actors'        => $actors,
                )
            );
            $videos[] = $video_entry;
            if ( lvjm_debug_importer_enabled() ) {
                $details_url = isset( $GLOBALS['lvjm_vpapi_last_details_url'] ) ? lvjm_mask_sensitive_payload( $GLOBALS['lvjm_vpapi_last_details_url'] ) : 'n/a';
                lvjm_importer_log(
                    'info',
                    sprintf(
                        'Performer CSV thumbs video_id=%s partner_id=%s locale=%s thumb_url=%s thumbs_samples=%s details_url=%s',
                        $video_id,
                        $partner_id,
                        $locale,
                        $thumb_url,
                        empty( $thumbs_urls ) ? 'none' : implode( ',', array_slice( $thumbs_urls, 0, 2 ) ),
                        $details_url
                    )
                );
            }
            lvjm_log_importer_video_debug( 'performer_csv', $video_entry );
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
        foreach ( $videos as &$video ) {
            if ( isset( $video['thumb_url'] ) && function_exists( 'lvjm_https_url' ) ) {
                $video['thumb_url'] = lvjm_https_url( $video['thumb_url'] );
            }
            if ( isset( $video['thumbs_urls'] ) ) {
                $video['thumbs_urls'] = lvjm_normalize_thumb_urls( $video['thumbs_urls'] );
            }
        }
        unset( $video );
        if ( ! empty( $videos ) && isset( $videos[0] ) ) {
            lvjm_log_importer_video_debug( 'normal_search', $videos[0] );
            if ( lvjm_debug_importer_enabled() ) {
                $first = $videos[0];
                $first_samples = isset( $first['thumbs_urls'] ) ? array_slice( (array) $first['thumbs_urls'], 0, 2 ) : array();
                lvjm_importer_log(
                    'info',
                    sprintf(
                        'Search thumbs video_id=%s partner_id=%s locale=%s thumb_url=%s thumbs_samples=%s',
                        isset( $first['id'] ) ? $first['id'] : 'n/a',
                        isset( $params['partner']['id'] ) ? $params['partner']['id'] : 'n/a',
                        isset( $params['partner']['locale'] ) ? $params['partner']['locale'] : ( isset( $params['locale'] ) ? $params['locale'] : '' ),
                        isset( $first['thumb_url'] ) ? $first['thumb_url'] : '',
                        empty( $first_samples ) ? 'none' : implode( ',', $first_samples )
                    )
                );
            }
        }
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

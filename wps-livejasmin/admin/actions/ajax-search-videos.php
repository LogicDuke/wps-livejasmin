<?php
defined( 'ABSPATH' ) || die( 'Cheatin&#8217; uh?' );

/**
 * Search for videos in Ajax or PHP call, now supporting multi-category straight searches.
 */
function lvjm_search_videos( $params = '' ) {
    $ajax_call = '' === $params;

    if ( $ajax_call ) {
        check_ajax_referer( 'ajax-nonce', 'nonce' );
        $params = $_POST;
    }

    $errors = array();
    // Force brutal loop if All Straight Categories is chosen
    if ( isset($params['cat_s']) && $params['cat_s'] === 'all_straight' ) {
        $params['multi_category_search'] = '1';
    }

    $videos = array();

    $is_multi_straight = isset($params['multi_category_search']) && $params['multi_category_search'] === '1';
    $performer = isset($params['performer']) ? sanitize_text_field((string)$params['performer']) : '';

    if ( $is_multi_straight ) {
        $straight_categories = ['69', 'Above Average', 'Amateur', 'Anal', 'Angry', 'Asian', 'Ass', 'Ass To mouth', 'Athletic', 'Auburn Hair', 'Babe', 'Bald', 'Ball Sucking', 'Bathroom', 'Bbc', 'BBW', 'Bdsm', 'Bed', 'Big Ass', 'Big Boobs', 'Big Booty', 'Big Breasts', 'Big Cock', 'Big Tists', 'Bizarre', 'Black Eyes', 'Black Girl', 'Black Hair', 'Blonde', 'Blond Hair', 'Blowjob', 'Blue Eyes', 'Blue Hair', 'Bondage', 'Boots', 'Booty', 'Bossy', 'Brown Eyes', 'Brown Hair', 'Brunette', 'Butt Plug', 'Cam Girl', 'Cam Porn', 'Cameltoe', 'Celebrity', 'Cfnm', 'Cheerleader', 'Clown Hair', 'Cock', 'College Girl', 'Cop', 'Cosplay', 'Cougar', 'Couple', 'Cowgirl', 'Creampie', 'Crew Cut', 'Cum', 'Cum On Tits', 'Cumshot', 'Curious', 'Cut', 'Cute', 'Dance', 'Deepthroat', 'Dilde', 'Dirty', 'Doctor', 'Doggy', 'Domination', 'Double Penetration', 'Ebony', 'Erotic', 'Eye Contact', 'Facesitting', 'Facial', 'Fake Tits', 'Fat Ass', 'Fetish', 'Fingering', 'Fire Red Hair', 'Fishnet', 'Fisting', 'Flirting', 'Foot Sex', 'Footjob', 'Fuck', 'Gag', 'Gaping', 'Gilf', 'Girl', 'Glamour', 'Glasses', 'Green Eyes', 'Grey Eyes', 'Group', 'Gym', 'Hairy', 'Handjob', 'Hard Cock', 'Hd', 'High Heels', 'Homemade', 'Homy', 'Hot', 'Hot Flirt', 'Housewife', 'Huge Cock', 'Huge Tits', 'Innocent', 'Interracial', 'Intim Piercing', 'Jeans', 'Kitchen', 'Ladyboy', 'Large Build', 'Latex', 'Latin', 'Latina', 'Leather', 'Lesbian', 'Lick', 'Lingerie', 'Live Sex', 'Long Hair', 'Long Nails', 'Machine', 'Maid', 'Massage', 'Masturbation', 'Mature', 'Milf', 'Missionary', 'Misstress', 'Moaning', 'Muscular', 'Muslim', 'Naked', 'Nasty', 'Natural Tits', 'Normal Cock', 'Normal Tits', 'Nurse', 'Nylon', 'Office', 'Oiled', 'Orange Hair', 'Orgasm', 'Orgy', 'Outdoor', 'Party', 'Pawg', 'Petite', 'Piercing', 'Pink Hair', 'Pissing', 'Pool', 'Pov', 'Pregnant', 'Princess', 'Public', 'punish', 'Pussy', 'Pvc', 'Quicky', 'Redhead', 'Remote Toy', 'Reverse Cowgirl', 'Riding', 'Rimjob', 'Roleplay', 'Romantic', 'Room', 'Rough', 'Schoolgirl', 'Scissoring', 'Scream', 'Secretary', 'Sensual', 'Sextoy', 'Sexy', 'Shaved', 'Short Girl', 'Short Hair', 'Shoulder Lenght Hair', 'Shy', 'Skinny', 'Slave', 'Sloppy', 'Slutty', 'Small Ass', 'Small Cock', 'Smoking', 'Solo', 'Sologirl', 'squirt', 'Stockings', 'Strap On', 'Stretching', 'Striptease', 'Stroking', 'Suck', 'Swallow', 'Tall', 'Tattoo', 'Teacher', 'Teasing', 'Teen', 'Treesome', 'Tight', 'Tiny Tits', 'Titjob', 'Toy', 'Trimmed', 'Uniform', 'Virgin', 'Watching', 'Wet', 'White', 'Lesbian'];

        $seen_ids = array();
        foreach ( $straight_categories as $cat ) {
            $params['category'] = $cat;
            $params['cat_s'] = $cat;
            $search_videos = new LVJM_Search_Videos( $params );

            if ( ! $search_videos->has_errors() ) {
                $new_videos = $search_videos->get_videos();
                foreach ($new_videos as $v) {
                    $vid = null;
                    if (is_array($v)) {
                        $vid = isset($v['id']) ? $v['id'] : null;
                    } elseif (is_object($v)) {
                        $vid = isset($v->id) ? $v->id : null;
                    }
                    if ($vid && !isset($seen_ids[$vid])) {
                        $videos[] = $v;
                        $seen_ids[$vid] = true;
                    }
                }

                $msg = '→ ' . $cat . ' (' . count($new_videos) . ' videos)';
                error_log('[WPS-LiveJasmin] Brutal search ' . $msg);
                if (!isset($GLOBALS['lvjm_debug'])) { $GLOBALS['lvjm_debug'] = array(); }
                $GLOBALS['lvjm_debug'][] = $msg;

            }
        }
    } else {
        $search_videos = new LVJM_Search_Videos( $params );
        if ( ! $search_videos->has_errors() ) {
            $videos = $search_videos->get_videos();
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

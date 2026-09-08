<?php
/**
 * Plugin Name: Atelier Workshop Schedule
 * Description: A small portfolio plugin: display upcoming WordPress workshops with a timezone-correct shortcode.
 * Version: 1.0.0
 * Author: Luca — portfolio sample
 * License: GPL-2.0-or-later
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', 'atelier_register_content');
function atelier_register_content(): void {
    register_post_type('atelier_workshop', [
        'labels' => ['name' => 'Workshops', 'singular_name' => 'Workshop'],
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-calendar-alt',
        'supports' => ['title', 'editor', 'excerpt'],
    ]);
    register_taxonomy('atelier_topic', 'atelier_workshop', [
        'labels' => ['name' => 'Topics', 'singular_name' => 'Topic'],
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
    ]);
}

/** The filter is the clock seam used by deterministic WordPress integration tests. */
function atelier_now(): DateTimeImmutable {
    $now = apply_filters('atelier_schedule_now', current_datetime());
    if (!$now instanceof DateTimeImmutable) {
        throw new UnexpectedValueException('atelier_schedule_now must return DateTimeImmutable.');
    }
    return $now->setTimezone(wp_timezone());
}

/**
 * Both the saved start and cutoff are real Unix timestamps.
 * current_time('timestamp') adds the site offset and must not be compared with them.
 */
function atelier_workshop_query_args(string $topic = '', int $limit = 6): array {
    $args = [
        'post_type' => 'atelier_workshop',
        'post_status' => 'publish',
        'posts_per_page' => max(1, min(12, $limit)),
        'no_found_rows' => true,
        'meta_key' => '_atelier_start_utc',
        'meta_query' => [[
            'key' => '_atelier_start_utc',
            'value' => atelier_now()->getTimestamp(),
            'compare' => '>=',
            'type' => 'NUMERIC',
        ]],
        'orderby' => ['meta_value_num' => 'ASC', 'ID' => 'ASC'],
    ];
    if ($topic !== '' && $topic !== 'all') {
        $args['tax_query'] = [[
            'taxonomy' => 'atelier_topic',
            'field' => 'slug',
            'terms' => sanitize_title($topic),
        ]];
    }
    return $args;
}

function atelier_upcoming_workshops(string $topic = '', int $limit = 6): array {
    return (new WP_Query(atelier_workshop_query_args($topic, $limit)))->posts;
}

function atelier_render_workshops(array $posts): string {
    if (!$posts) {
        return '<div class="schedule-empty" role="status"><h3>No workshops scheduled</h3><p>There are no upcoming workshops for this topic.</p></div>';
    }
    $html = '<div class="workshop-grid">';
    foreach ($posts as $workshop) {
        $start = (int) get_post_meta($workshop->ID, '_atelier_start_utc', true);
        $terms = get_the_terms($workshop->ID, 'atelier_topic');
        $term = !is_wp_error($terms) && $terms ? reset($terms) : null;
        $topic = $term ? $term->slug : 'werkstatt';
        $topic_name = $term ? $term->name : 'Workshop';
        $fee = get_post_meta($workshop->ID, '_atelier_fee', true);
        $price = $fee === '0' ? 'Free' : ($fee === '' ? 'Price on request' : 'CHF ' . number_format_i18n((float) $fee, 0));
        $iso = wp_date('c', $start, wp_timezone());
        $html .= '<article class="workshop-card topic-' . esc_attr($topic) . '" data-workshop-id="' . $workshop->ID . '">';
        $art = apply_filters('atelier_workshop_art', '', $workshop, $topic);
        $html .= '<div class="workshop-art">' . $art . '</div>';
        $html .= '<div class="workshop-body"><div class="card-meta"><span>' . esc_html($topic_name) . '</span><span>' . esc_html($price) . '</span></div>';
        $html .= '<h3>' . esc_html(get_the_title($workshop)) . '</h3><p>' . esc_html(get_the_excerpt($workshop)) . '</p>';
        $html .= '<time datetime="' . esc_attr($iso) . '">' . esc_html(wp_date('d M Y · H:i', $start, wp_timezone())) . ' <span>' . esc_html(wp_date('T', $start, wp_timezone())) . '</span></time>';
        $html .= '</div></article>';
    }
    return $html . '</div>';
}

add_shortcode('atelier_schedule', 'atelier_schedule_shortcode');
function atelier_schedule_shortcode($attributes): string {
    $attributes = shortcode_atts(['topic' => '', 'limit' => 6], $attributes, 'atelier_schedule');
    return atelier_render_workshops(atelier_upcoming_workshops((string) $attributes['topic'], (int) $attributes['limit']));
}

add_action('add_meta_boxes', function (): void {
    add_meta_box('atelier-details', 'Workshop details', 'atelier_workshop_fields', 'atelier_workshop', 'normal');
});
function atelier_workshop_fields(WP_Post $post): void {
    wp_nonce_field('atelier_save_workshop', 'atelier_workshop_nonce');
    $timestamp = (int) get_post_meta($post->ID, '_atelier_start_utc', true);
    $local = $timestamp ? wp_date('Y-m-d\TH:i', $timestamp, wp_timezone()) : '';
    echo '<p><label>Start time (' . esc_html(wp_timezone_string()) . ')<br><input type="datetime-local" name="atelier_start" value="' . esc_attr($local) . '"></label></p>';
    echo '<p class="description">Unchanged local times preserve the saved instant. A new ambiguous time at the end of daylight saving selects the later occurrence. Nonexistent times during the spring transition are not saved.</p>';
    echo '<p><label>Fee in CHF, 0 for free<br><input type="number" name="atelier_fee" min="0" step="1" value="' . esc_attr(get_post_meta($post->ID, '_atelier_fee', true)) . '"></label></p>';
}
/** Parse a new wall time, choosing the later instant during a DST overlap. */
function atelier_parse_local_start(string $input): ?int {
    $format = 'Y-m-d\TH:i';
    $zone = wp_timezone();
    $start = DateTimeImmutable::createFromFormat('!' . $format, $input, $zone);
    if (!$start || $start->format($format) !== $input) {
        return null; // Reject invalid calendar dates and nonexistent spring-forward times.
    }
    $latest = $start->getTimestamp();
    $wall = DateTimeImmutable::createFromFormat('!' . $format, $input, new DateTimeZone('UTC'));
    // Both adjacent offsets must be considered; PHP's default overlap choice varies by zone.
    $transitions = $zone->getTransitions($latest - 2 * DAY_IN_SECONDS, $latest + 2 * DAY_IN_SECONDS);
    foreach ($transitions ?: [] as $transition) {
        $candidate = $wall->getTimestamp() - $transition['offset'];
        if (wp_date($format, $candidate, $zone) === $input) {
            $latest = max($latest, $candidate);
        }
    }
    return $latest;
}
add_action('save_post_atelier_workshop', function (int $post_id): void {
    if (!isset($_POST['atelier_workshop_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['atelier_workshop_nonce'])), 'atelier_save_workshop')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id) || wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    $input = isset($_POST['atelier_start']) ? sanitize_text_field(wp_unslash($_POST['atelier_start'])) : '';
    $stored = get_post_meta($post_id, '_atelier_start_utc', true);
    if ($input === '') {
        delete_post_meta($post_id, '_atelier_start_utc');
    } elseif ($stored === '' || wp_date('Y-m-d\TH:i', (int) $stored, wp_timezone()) !== $input) {
        // An unchanged minute cannot distinguish DST occurrences or represent stored seconds.
        // Preserve the original instant instead of reparsing that lossy editor representation.
        $timestamp = atelier_parse_local_start($input);
        if ($timestamp !== null) {
            update_post_meta($post_id, '_atelier_start_utc', $timestamp);
        }
    }
    if (isset($_POST['atelier_fee'])) {
        $fee = sanitize_text_field(wp_unslash($_POST['atelier_fee']));
        if ($fee === '') {
            delete_post_meta($post_id, '_atelier_fee');
        } elseif (ctype_digit($fee)) {
            update_post_meta($post_id, '_atelier_fee', (int) $fee);
        }
    }
});

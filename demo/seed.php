<?php
/** CLI-only fictitious portfolio content. Call after bootstrapping WordPress. */
if (PHP_SAPI !== 'cli' || !defined('ABSPATH')) {
    exit('Load this CLI fixture from WordPress.');
}
atelier_register_content();
// Update the owned fixtures in place: repeated seeding preserves their IDs.
$existing = get_posts([
    'post_type' => 'atelier_workshop', 'post_status' => 'any',
    'posts_per_page' => -1, 'fields' => 'ids',
    'meta_key' => '_atelier_demo_fixture', 'meta_value' => '1',
    'orderby' => 'ID', 'order' => 'ASC',
]);
foreach (['papier' => 'Paper', 'holz' => 'Wood', 'reparieren' => 'Repair'] as $slug => $name) {
    $term = term_exists($slug, 'atelier_topic');
    if ($term) {
        wp_update_term((int) $term['term_id'], 'atelier_topic', ['name' => $name]);
    } else {
        wp_insert_term($name, 'atelier_topic', ['slug' => $slug]);
    }
}
$clock = new DateTimeImmutable('2026-09-08 18:30:00', new DateTimeZone('Europe/Zurich'));
update_option('atelier_demo_clock', $clock->format(DateTimeInterface::ATOM));
$fixtures = [
    ['Hand-bound notebook', 'Fold, stitch and bind a small notebook with paper and linen thread.', 'papier', '+30 minutes', 35],
    ['Clothing repairs', 'Replace a button and mend a torn seam with a needle and thread.', 'reparieren', '+75 minutes', 0],
    ['Wooden pencil holder', 'Shape, sand and oil a simple wooden pencil holder by hand.', 'holz', '+1 day', 45],
    ['Paper folding', 'Learn basic folds to make paper boats and cranes.', 'papier', '+2 days', 25],
    ['Wood restoration', 'Sand and refinish a small wooden object.', 'reparieren', '+3 days', 30],
    ['Wooden tray', 'Build a small tray while learning about materials and hand tools.', 'holz', '+5 days', 55],
    ['A past sample workshop', 'This completed workshop must not appear in the upcoming programme.', 'papier', '-1 minute', 15],
];
$fixture_keys = ['notizbuch', 'reparieren', 'stiftehalter', 'falten', 'lieblingsstueck', 'ablage', 'vergangen'];
foreach ($fixtures as $index => [$title, $excerpt, $topic, $relative, $fee]) {
    $post = ['post_type' => 'atelier_workshop', 'post_status' => 'publish', 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $excerpt];
    if (isset($existing[$index])) { $post['ID'] = $existing[$index]; }
    $id = wp_insert_post($post, true);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    wp_set_object_terms($id, $topic, 'atelier_topic');
    update_post_meta($id, '_atelier_start_utc', $clock->modify($relative)->getTimestamp());
    update_post_meta($id, '_atelier_fee', $fee);
    update_post_meta($id, '_atelier_demo_fixture', 1);
    update_post_meta($id, '_atelier_demo_key', $fixture_keys[$index]);
}
update_option('timezone_string', 'Europe/Zurich');
update_option('WPLANG', 'en_US');
update_option('blogname', 'Atelier Nord — Workshops');
update_option('blogdescription', 'Workshops in paper, wood and repair.');
switch_theme('atelier-portfolio-theme');
$front = get_page_by_path('portfolio-demo');
$page = ['post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'portfolio-demo', 'post_title' => 'Atelier Nord — Workshops', 'post_content' => '[atelier_schedule]'];
if ($front) { $page['ID'] = $front->ID; }
$page_id = wp_insert_post($page, true);
if (is_wp_error($page_id)) { throw new RuntimeException($page_id->get_error_message()); }
update_option('show_on_front', 'page');
update_option('page_on_front', $page_id);
echo 'Seeded seven fictional WordPress workshops in English; six upcoming, existing fixture IDs preserved.' . PHP_EOL;

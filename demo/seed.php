<?php
/** CLI-only fictitious portfolio content. Call after bootstrapping WordPress. */
if (PHP_SAPI !== 'cli' || !defined('ABSPATH')) {
    exit('Load this CLI fixture from WordPress.');
}
atelier_register_content();
$existing = get_posts(['post_type' => 'atelier_workshop', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids']);
foreach ($existing as $id) {
    if (get_post_meta($id, '_atelier_demo_fixture', true)) {
        wp_delete_post($id, true);
    }
}
foreach (['papier' => 'Papier', 'holz' => 'Holz', 'reparieren' => 'Reparieren'] as $slug => $name) {
    if (!term_exists($slug, 'atelier_topic')) {
        wp_insert_term($name, 'atelier_topic', ['slug' => $slug]);
    }
}
$clock = new DateTimeImmutable('2026-09-08 18:30:00', new DateTimeZone('Europe/Zurich'));
update_option('atelier_demo_clock', $clock->format(DateTimeInterface::ATOM));
$fixtures = [
    ['Papier mit Charakter', 'Ein kleines Notizbuch binden — vom ersten Falz bis zur letzten Naht.', 'papier', '+30 minutes', 35],
    ['Reparieren statt ersetzen', 'Knöpfe, Nähte und kleine Alltagsdinge: gemeinsam wieder brauchbar machen.', 'reparieren', '+75 minutes', 0],
    ['Holz, ganz unkompliziert', 'Ein schlichter Stiftehalter, selbst geschliffen und von Hand geölt.', 'holz', '+1 day', 45],
    ['Falten mit Ruhe', 'Aus einem Blatt Papier werden Formen mit Ecken, Kanten und Persönlichkeit.', 'papier', '+2 days', 25],
    ['Lieblingsstück gerettet', 'Einem alten Holzgegenstand mit einfachen Mitteln neues Leben geben.', 'reparieren', '+3 days', 30],
    ['Ein Platz für Kleinigkeiten', 'Eine kleine Ablage bauen. Material verstehen und Werkzeuge kennenlernen.', 'holz', '+5 days', 55],
    ['Vergangener Beispieltermin', 'Dieser abgeschlossene Workshop darf im kommenden Programm nicht erscheinen.', 'papier', '-1 minute', 15],
];
foreach ($fixtures as [$title, $excerpt, $topic, $relative, $fee]) {
    $id = wp_insert_post(['post_type' => 'atelier_workshop', 'post_status' => 'publish', 'post_title' => $title, 'post_excerpt' => $excerpt, 'post_content' => $excerpt], true);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    wp_set_object_terms($id, $topic, 'atelier_topic');
    update_post_meta($id, '_atelier_start_utc', $clock->modify($relative)->getTimestamp());
    update_post_meta($id, '_atelier_fee', $fee);
    update_post_meta($id, '_atelier_demo_fixture', 1);
}
update_option('timezone_string', 'Europe/Zurich');
switch_theme('atelier-portfolio-theme');
$front = get_page_by_path('portfolio-demo');
$page_id = $front ? $front->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'portfolio-demo', 'post_title' => 'Atelier Nord — Portfolio-Demo', 'post_content' => '[atelier_schedule]']);
update_option('show_on_front', 'page');
update_option('page_on_front', $page_id);
echo 'Seeded seven fictitious WordPress workshops; six upcoming, two expose the legacy timezone defect.' . PHP_EOL;

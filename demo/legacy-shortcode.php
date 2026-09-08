<?php
/**
 * Deliberately faulty BEFORE fixture for the portfolio demonstration only.
 * Not loaded by the distributable plugin.
 */
function atelier_demo_legacy_workshops(string $topic = '', int $limit = 6): array {
    $args = atelier_workshop_query_args($topic, $limit);
    $now = atelier_now();
    // This recreates the offset-bearing current_time('timestamp') mistake.
    $args['meta_query'][0]['value'] = $now->getTimestamp() + $now->getOffset();
    return (new WP_Query($args))->posts;
}
add_shortcode('atelier_schedule_before', function ($attributes): string {
    $attributes = shortcode_atts(['topic' => '', 'limit' => 6], $attributes);
    return atelier_render_workshops(atelier_demo_legacy_workshops((string) $attributes['topic'], (int) $attributes['limit']));
});

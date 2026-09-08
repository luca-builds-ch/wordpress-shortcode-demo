<?php
/**
 * Optional portfolio environment; load from a local mu-plugin after copying
 * this demo directory beside it. Keep this out of a client/production plugin.
 */
add_action('plugins_loaded', function (): void {
    if (function_exists('atelier_schedule_shortcode')) {
        require_once __DIR__ . '/legacy-shortcode.php';
    }
});
add_filter('atelier_schedule_now', function (DateTimeImmutable $now): DateTimeImmutable {
    $frozen = get_option('atelier_demo_clock');
    return $frozen ? new DateTimeImmutable($frozen, wp_timezone()) : $now;
});

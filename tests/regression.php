<?php
/**
 * Real WordPress integration tests. No mocked WP functions or database.
 * Usage: php regression.php bootstrap.php
 * Set WP_DEMO_TEST=1 and ATELIER_WORDPRESS_ROOT to a disposable WordPress root.
 */
if (PHP_SAPI !== 'cli' || getenv('WP_DEMO_TEST') !== '1' || !isset($argv[1])) {
    fwrite(STDERR, "Use CLI with WP_DEMO_TEST=1 and a disposable WordPress bootstrap path.\n");
    exit(2);
}
require $argv[1];
if (!defined('DB_FILE') || DB_FILE !== 'tests.sqlite') {
    throw new RuntimeException('Refusing to change data outside the isolated tests.sqlite database.');
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$activated = activate_plugin('atelier-workshops/atelier-workshops.php');
if (is_wp_error($activated)) {
    throw new RuntimeException($activated->get_error_message());
}
// The isolated installation bootstrap skips active plugins while WP_INSTALLING is true.
require_once WP_PLUGIN_DIR . '/atelier-workshops/atelier-workshops.php';
atelier_register_content();
require_once dirname(__DIR__) . '/demo/legacy-shortcode.php';

$created_ids = [];
$passed = 0;
$failed = 0;
$original_user_id = get_current_user_id();
$test_editor_id = wp_insert_user([
    'user_login' => 'atelier_test_' . bin2hex(random_bytes(8)),
    'user_pass' => wp_generate_password(32, true, true),
    'role' => 'editor',
]);
if (is_wp_error($test_editor_id)) {
    throw new RuntimeException('Could not create the temporary test editor.');
}
$clock = new DateTimeImmutable('2030-07-01T10:00:00Z');
add_filter('atelier_schedule_now', function () use (&$clock): DateTimeImmutable { return $clock; }, 100);
function same($actual, $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message . '; expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }
}
function check(string $name, callable $test): void {
    global $passed, $failed;
    try {
        $test();
        ++$passed;
        echo 'PASS ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        ++$failed;
        echo 'FAIL ' . $name . ': ' . $error->getMessage() . PHP_EOL;
    }
}
function workshop(string $title, int $timestamp, string $topic = 'papier', int $fee = 0, string $status = 'publish'): int {
    global $created_ids;
    $id = wp_insert_post(['post_type' => 'atelier_workshop', 'post_status' => $status, 'post_title' => $title, 'post_excerpt' => 'Fictitious regression fixture'], true);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    $created_ids[] = $id;
    wp_set_object_terms($id, $topic, 'atelier_topic');
    update_post_meta($id, '_atelier_start_utc', $timestamp);
    update_post_meta($id, '_atelier_fee', $fee);
    return $id;
}
function clear_fixtures(): void {
    global $created_ids;
    foreach ($created_ids as $id) {
        wp_delete_post($id, true);
    }
    $created_ids = [];
}
function ids(array $posts): array { return array_map(static fn(WP_Post $post): int => $post->ID, $posts); }
function editor_save(int $id, string $local): void {
    global $test_editor_id;
    $old_post = $_POST;
    $old_user_id = get_current_user_id();
    try {
        wp_set_current_user($test_editor_id);
        same(current_user_can('edit_post', $id), true, 'Temporary editor has the real edit capability');
        $_POST = [
            'atelier_workshop_nonce' => wp_create_nonce('atelier_save_workshop'),
            'atelier_start' => $local,
            'atelier_fee' => '0',
        ];
        $result = wp_update_post(['ID' => $id, 'post_title' => 'Editor saved workshop'], true);
        if (is_wp_error($result)) {
            throw new RuntimeException($result->get_error_message());
        }
    } finally {
        $_POST = $old_post;
        wp_set_current_user($old_user_id);
    }
}

try {
    check('UTC: excludes past, includes exact start, orders by real start time', function () use (&$clock): void {
        update_option('timezone_string', 'UTC');
        $t = $clock->getTimestamp();
        workshop('Past', $t - 1);
        $later = workshop('Later', $t + 3600);
        $boundary = workshop('Starts now', $t);
        $soon = workshop('Soon', $t + 60);
        same(ids(atelier_upcoming_workshops()), [$boundary, $soon, $later], 'UTC inclusion/order');
        clear_fixtures();
    });
    check('Zurich summer: upcoming hour survives; legacy offset demonstrably fails', function () use (&$clock): void {
        update_option('timezone_string', 'Europe/Zurich');
        $soon = workshop('Upcoming in 30 minutes', $clock->getTimestamp() + 1800);
        same(ids(atelier_upcoming_workshops()), [$soon], 'Upcoming workshop must be visible');
        same(ids(atelier_demo_legacy_workshops()), [], 'The deliberately faulty fixture must reproduce the loss');
        $html = do_shortcode('[atelier_schedule]');
        same(str_contains($html, '12:30'), true, 'Display uses WordPress site timezone');
        same(str_contains($html, 'Kostenlos'), true, 'Zero fee is not an empty value');
        clear_fixtures();
    });
    check('New York: past event stays excluded in a negative-offset timezone', function () use (&$clock): void {
        update_option('timezone_string', 'America/New_York');
        $past = workshop('Already started', $clock->getTimestamp() - 60);
        $future = workshop('Upcoming', $clock->getTimestamp() + 60);
        same(ids(atelier_upcoming_workshops()), [$future], 'Negative offset must not revive past events');
        same(ids(atelier_demo_legacy_workshops()), [$past, $future], 'Legacy fixture exposes the inverse error');
        clear_fixtures();
    });
    check('Kathmandu: fractional timezone offset changes display, never the cutoff', function () use (&$clock): void {
        update_option('timezone_string', 'Asia/Kathmandu');
        $future = workshop('Quarter-hour zone', $clock->getTimestamp() + 1800);
        same(ids(atelier_upcoming_workshops()), [$future], 'Fractional-offset inclusion');
        same(str_contains(do_shortcode('[atelier_schedule]'), '16:15'), true, 'Correct +05:45 local display');
        clear_fixtures();
    });
    check('DST repeated hour: two identical local labels remain distinct instants', function () use (&$clock): void {
        update_option('timezone_string', 'Europe/Zurich');
        $clock = new DateTimeImmutable('2030-10-27T01:00:00Z');
        workshop('First 02:30, already past', (new DateTimeImmutable('2030-10-27T00:30:00Z'))->getTimestamp());
        $second = workshop('Second 02:30, upcoming', (new DateTimeImmutable('2030-10-27T01:30:00Z'))->getTimestamp());
        same(ids(atelier_upcoming_workshops()), [$second], 'DST cutoff compares real instants');
        clear_fixtures();
        $clock = new DateTimeImmutable('2030-07-01T10:00:00Z');
    });
    check('Shortcode: topic, limit, draft exclusion and empty state use real WP_Query', function () use (&$clock): void {
        update_option('timezone_string', 'UTC');
        $t = $clock->getTimestamp();
        workshop('Wrong topic', $t + 10, 'holz');
        workshop('Draft must not appear', $t + 15, 'papier', 20, 'draft');
        $first = workshop('First paper', $t + 20, 'papier');
        workshop('Second paper', $t + 30, 'papier');
        $html = do_shortcode('[atelier_schedule topic="papier" limit="1"]');
        same(substr_count($html, 'data-workshop-id='), 1, 'Shortcode limit');
        same(str_contains($html, 'data-workshop-id="' . $first . '"'), true, 'Filtered earliest result');
        same(str_contains(do_shortcode('[atelier_schedule topic="unknown-topic"]'), 'schedule-empty'), true, 'Unknown topic has an empty state');
        clear_fixtures();
    });
    check('Shortcode leaves the main WordPress post unchanged', function () use (&$clock): void {
        $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Main page']);
        $original = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = get_post($page);
        workshop('Inner schedule item', $clock->getTimestamp() + 60);
        do_shortcode('[atelier_schedule]');
        same($GLOBALS['post']->ID, $page, 'Main loop context');
        $GLOBALS['post'] = $original;
        wp_delete_post($page, true);
        clear_fixtures();
    });
    check('WordPress editor save: local datetime becomes UTC storage and round-trips in the shortcode', function () use (&$clock): void {
        update_option('timezone_string', 'Europe/Zurich');
        $id = workshop('Saved through WordPress', $clock->getTimestamp() + 60);
        try {
            editor_save($id, '2030-07-01T12:30');
            same((int) get_post_meta($id, '_atelier_start_utc', true), (new DateTimeImmutable('2030-07-01T10:30:00Z'))->getTimestamp(), 'Editor stores the real UTC instant');
            same(str_contains(do_shortcode('[atelier_schedule]'), '12:30'), true, 'Editor value round-trips in site timezone');
            same(str_contains(do_shortcode('[atelier_schedule]'), 'Kostenlos'), true, 'Editor zero price remains meaningful');
        } finally {
            clear_fixtures();
        }
    });
    check('Editor DST overlap: unchanged first and second 02:30 preserve their UTC instants', function (): void {
        update_option('timezone_string', 'Europe/Zurich');
        try {
            foreach (['2030-10-27T00:30:00Z', '2030-10-27T01:30:00Z'] as $iso) {
                $timestamp = (new DateTimeImmutable($iso))->getTimestamp();
                $id = workshop('Repeated local hour', $timestamp);
                ob_start();
                atelier_workshop_fields(get_post($id));
                $fields = ob_get_clean();
                same(str_contains($fields, 'value="2030-10-27T02:30"'), true, 'Actual editor renders the repeated wall time');
                editor_save($id, '2030-10-27T02:30');
                same((int) get_post_meta($id, '_atelier_start_utc', true), $timestamp, 'Unchanged editor input preserves ' . $iso);
            }
        } finally {
            clear_fixtures();
        }
    });
    check('Editor DST overlap: new ambiguous Zurich and New York values choose the later occurrence', function (): void {
        try {
            foreach ([
                ['Europe/Zurich', '2030-10-27T02:30', '2030-10-27T01:30:00Z'],
                ['America/New_York', '2030-11-03T01:30', '2030-11-03T06:30:00Z'],
            ] as [$zone, $local, $iso]) {
                update_option('timezone_string', $zone);
                $id = workshop('New ambiguous editor input', 0);
                delete_post_meta($id, '_atelier_start_utc');
                editor_save($id, $local);
                same((int) get_post_meta($id, '_atelier_start_utc', true), (new DateTimeImmutable($iso))->getTimestamp(), 'Later occurrence is selected in ' . $zone);
            }
        } finally {
            clear_fixtures();
        }
    });
    check('Editor: unchanged minute preserves seconds; nonexistent DST gap preserves the original value', function (): void {
        update_option('timezone_string', 'Europe/Zurich');
        try {
            $timestamp = (new DateTimeImmutable('2030-07-01T10:30:45Z'))->getTimestamp();
            $id = workshop('Precise imported timestamp', $timestamp);
            editor_save($id, '2030-07-01T12:30');
            same((int) get_post_meta($id, '_atelier_start_utc', true), $timestamp, 'Unchanged minute does not erase stored seconds');
            editor_save($id, '2030-03-31T02:30');
            same((int) get_post_meta($id, '_atelier_start_utc', true), $timestamp, 'Nonexistent spring-forward time cannot overwrite the instant');
        } finally {
            clear_fixtures();
        }
    });
} finally {
    clear_fixtures();
    wp_set_current_user($original_user_id);
    if (!wp_delete_user($test_editor_id)) {
        throw new RuntimeException('Could not clean up the temporary test editor.');
    }
}
echo PHP_EOL . $passed . ' passed; ' . $failed . ' failed. WordPress ' . get_bloginfo('version') . ', PHP ' . PHP_VERSION . ', DB engine ' . DB_ENGINE . '.' . PHP_EOL;
exit($failed === 0 ? 0 : 1);

<?php
/** CLI-only configuration for a disposable, real WordPress/SQLite test site. */
if (PHP_SAPI !== 'cli' || getenv('WP_DEMO_TEST') !== '1') {
    throw new RuntimeException('Test bootstrap requires CLI and WP_DEMO_TEST=1.');
}
$wp_root = realpath((string) getenv('ATELIER_WORDPRESS_ROOT'));
if (!$wp_root || !is_file($wp_root . '/wp-settings.php')
    || !is_file($wp_root . '/wp-content/db.php')
    || !is_file($wp_root . '/wp-content/plugins/sqlite-database-integration/load.php')
    || !is_file($wp_root . '/wp-content/plugins/atelier-workshops/atelier-workshops.php')) {
    throw new RuntimeException('Set ATELIER_WORDPRESS_ROOT to a disposable WordPress root with the SQLite drop-in and Atelier plugin installed.');
}
$test_data = getenv('ATELIER_TEST_DATA_DIR') ?: __DIR__ . '/.test-data';
if (!is_dir($test_data) && !mkdir($test_data, 0770, true)) {
    throw new RuntimeException('Could not create the isolated test data directory.');
}
define('ABSPATH', $wp_root . '/');
define('DB_NAME', 'atelier_tests');
define('DB_USER', 'local');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('DB_DIR', realpath($test_data));
define('DB_FILE', 'tests.sqlite');
define('DB_ENGINE', 'sqlite');
define('WP_HOME', 'http://127.0.0.1');
define('WP_SITEURL', 'http://127.0.0.1');
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISALLOW_FILE_MODS', true);
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_INSTALLING', true);
foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'] as $key) {
    define($key, bin2hex(random_bytes(32)));
}
$table_prefix = 'atelier_test_';
// Intentionally bypass an existing wp-config.php: its production DB is never loaded.
require ABSPATH . 'wp-settings.php';
add_filter('pre_wp_mail', static fn(): bool => true);
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
if (!is_blog_installed()) {
    wp_install('Disposable Atelier tests', 'atelier_bootstrap', 'test@example.invalid', false, '', wp_generate_password(32, true, true));
}
wp_installing(false);

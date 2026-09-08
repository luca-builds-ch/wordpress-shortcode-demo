<?php
add_action('after_setup_theme', function (): void {
    add_theme_support('title-tag');
    add_theme_support('html5', ['style', 'script']);
});
add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_style('atelier-portfolio', get_stylesheet_uri(), [], '2.1.0');
});
/** Each fictional workshop has its own photograph, independent of its topic. */
add_filter('atelier_workshop_art', function (string $fallback, WP_Post $workshop): string {
    $photographs = [
        'notizbuch' => ['handmade-notebook.png', 'Hand-bound notebook with exposed linen-thread stitching'],
        'reparieren' => ['clothing-repair.png', 'A stitched fabric patch with a button, needle and thread'],
        'stiftehalter' => ['wood-pencil-holder.png', 'A wooden pencil holder with two pencils beside sandpaper'],
        'falten' => ['paper-folding.png', 'A paper crane, paper boat and unfolded sheet on a table'],
        'lieblingsstueck' => ['wood-restoration.png', 'A partially sanded wooden box with sandpaper and a scraper'],
        'ablage' => ['wooden-tray.png', 'A shallow wooden tray on a workbench'],
    ];
    $key = get_post_meta($workshop->ID, '_atelier_demo_key', true);
    if (!isset($photographs[$key])) { return $fallback; }
    [$filename, $alt] = $photographs[$key];
    $url = get_template_directory_uri() . '/assets/workshops/' . $filename;
    return '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" width="1448" height="1086" loading="lazy" decoding="async">';
}, 10, 2);

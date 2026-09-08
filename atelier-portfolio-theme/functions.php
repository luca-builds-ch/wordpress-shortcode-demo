<?php
add_action('after_setup_theme', function (): void {
    add_theme_support('title-tag');
    add_theme_support('html5', ['style', 'script']);
});
add_action('wp_enqueue_scripts', function (): void {
    wp_enqueue_style('atelier-portfolio', get_stylesheet_uri(), [], '1.0.0');
});

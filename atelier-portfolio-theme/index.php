<?php
/** Original, fictitious portfolio study. No bookings or customer claims. */
$mode = isset($_GET['version']) && $_GET['version'] === 'before' ? 'before' : 'after';
$topics = ['all' => 'All workshops', 'papier' => 'Paper', 'holz' => 'Wood', 'reparieren' => 'Repair'];
$topic = isset($_GET['topic']) ? sanitize_key(wp_unslash($_GET['topic'])) : 'all';
if (!isset($topics[$topic])) { $topic = 'all'; }
$now = atelier_now();
$fixed = atelier_upcoming_workshops('all', 12);
$before = function_exists('atelier_demo_legacy_workshops') ? atelier_demo_legacy_workshops('all', 12) : [];
?><!doctype html>
<html lang="en">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="description" content="A fictional workshop programme built with WordPress."><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#workshops">Skip to workshops</a>
<header class="site-header wrap"><a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="Atelier Nord home">Atelier Nord</a><nav aria-label="Main navigation"><a href="#workshops">Workshops</a><a href="#the-build">Implementation</a></nav></header>
<main>
<section class="hero wrap" aria-labelledby="hero-title">
<div class="hero-heading"><h1 id="hero-title">Workshops in paper,<br><span>wood and repair</span></h1></div>
<div class="hero-bottom"><p>Learn bookbinding, simple woodwork and practical repairs in small workshops.</p><a class="button" href="#workshops">View workshops</a></div>
<figure class="hero-visual"><img src="<?php echo esc_url(get_template_directory_uri() . '/assets/atelier-material-hero.png'); ?>" width="1672" height="941" alt="Sculptural folded paper and pale ash wood on a studio surface" fetchpriority="high"></figure>

</section>
<section class="program wrap" id="workshops" aria-labelledby="program-title">
<div class="section-heading"><div><h2 id="program-title">September workshops</h2></div><p>September 2026<br><span>Europe/Zurich</span></p></div>
<div class="program-toolbar"><nav class="topic-filters" aria-label="Filter workshops by material"><?php foreach ($topics as $slug => $label) : ?><a <?php echo $topic === $slug ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url(add_query_arg(['version' => $mode, 'topic' => $slug], home_url('/')) . '#workshops'); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></nav></div>
<?php if ($mode === 'before') : ?><p class="issue-note"><strong>Before view.</strong> This intentionally faulty version hides two upcoming workshops. <a href="<?php echo esc_url(add_query_arg(['version' => 'after', 'topic' => $topic], home_url('/')) . '#workshops'); ?>">View the corrected schedule</a></p><?php endif; ?>
<?php echo do_shortcode('[' . ($mode === 'before' ? 'atelier_schedule_before' : 'atelier_schedule') . ' topic="' . $topic . '" limit="6"]'); ?>
<p class="program-footnote">All workshops and fees are fictional.</p>
</section>
<section class="case-study wrap" id="the-build" aria-labelledby="case-title">
<div class="case-heading"><h2 id="case-title">WordPress implementation</h2><p>A custom WordPress theme and a small content plugin. The same workshop records, with one deliberately introduced timezone error and its correction.</p><a class="text-link" href="https://github.com/luca-builds-ch/wordpress-shortcode-demo">View source on GitHub</a></div>
<div class="case-detail"><div class="comparison"><div><span class="eyebrow">Compare the behaviour</span><strong><?php echo $mode === 'before' ? 'Before — 4 workshops' : 'After — 6 workshops'; ?></strong></div><div class="version-switch" role="group" aria-label="Compare original and corrected schedule"><a <?php echo $mode === 'before' ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url(add_query_arg(['version' => 'before', 'topic' => $topic], home_url('/')) . '#workshops'); ?>">Before</a><a <?php echo $mode === 'after' ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url(add_query_arg(['version' => 'after', 'topic' => $topic], home_url('/')) . '#workshops'); ?>">After</a></div></div>
<div class="case-steps"><article><span>01</span><div><h3>Content storage</h3><p>Workshop posts hold genuine UTC timestamps. WordPress owns the content, topics and local date display.</p></div></article><article><span>02</span><div><h3>Timezone correction</h3><p>Compare actual instants. Apply the site timezone when displaying them. Upcoming events stay visible until they start.</p></div></article><article><span>03</span><div><h3>Regression tests</h3><p>Eleven PHP regression tests cover timezone boundaries, daylight saving, editor saves, filtering and query behaviour.</p></div></article></div>
<p class="clock-note">Fixed demonstration clock: <?php echo esc_html($now->format('j F Y · H:i T')); ?>.<br>The production plugin uses the current WordPress time.</p></div>
</section>
</main>
<footer class="site-footer wrap"><span class="footer-brand">Atelier Nord</span><p>Portfolio demonstration with fictional workshops. Booking is unavailable.</p><a href="#hero-title">Back to top</a></footer>
<?php wp_footer(); ?></body></html>

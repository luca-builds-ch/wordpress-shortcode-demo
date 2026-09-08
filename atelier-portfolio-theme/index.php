<?php
/** Purpose-built local portfolio theme. All business content is fictitious. */
$mode = isset($_GET['version']) && $_GET['version'] === 'before' ? 'before' : 'after';
$topics = ['all' => 'Alle Workshops', 'papier' => 'Papier', 'holz' => 'Holz', 'reparieren' => 'Reparieren'];
$topic = isset($_GET['topic']) ? sanitize_key(wp_unslash($_GET['topic'])) : 'all';
if (!isset($topics[$topic])) {
    $topic = 'all';
}
$now = atelier_now();
$fixed = atelier_upcoming_workshops('all', 12);
$before = function_exists('atelier_demo_legacy_workshops') ? atelier_demo_legacy_workshops('all', 12) : [];
$lost = count($fixed) - count($before);
?><!doctype html>
<html lang="de-CH">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#workshops">Zum Programm</a>
<div class="demo-ribbon"><span><i></i> Portfolio-Demo · Fiktive Werkstatt</span><a href="#arbeitsprobe">Zur Arbeitsprobe <span aria-hidden="true">↗</span></a></div>
<header class="site-header wrap"><a class="brand" href="<?php echo esc_url(home_url('/')); ?>"><span class="brand-mark" aria-hidden="true"><b></b><b></b><b></b><b></b></span>Atelier Nord<span class="brand-period">.</span></a><nav aria-label="Hauptnavigation"><a href="#workshops">Workshops</a><a href="#arbeitsprobe">Die Idee dahinter <span aria-hidden="true">↗</span></a></nav></header>
<main>
<section class="hero wrap" aria-labelledby="hero-title"><div class="hero-copy"><p class="eyebrow">Ein Ort für neugierige Hände</p><h1 id="hero-title">Gute Dinge.<br><em>Selbst gemacht.</em></h1><p class="hero-lead">Papier falten. Holz verstehen. Liebgewonnenes reparieren. Kleine Workshops mit Raum zum Ausprobieren.</p><a class="button" href="#workshops">Das Programm entdecken <span aria-hidden="true">↘</span></a><p class="hero-note"><span aria-hidden="true">✳</span> Beispielprogramm einer erfundenen Werkstatt</p></div><div class="hero-visual" aria-hidden="true"><div class="paper-stack p-back"></div><div class="paper-stack p-front"><span class="paper-label">MACH MAL<br>WIEDER WAS.</span><div class="paper-circle"></div><span class="paper-number">01 / 06</span></div><div class="wood-disc"></div><div class="little-note">Neues lernen.<br>Mitnehmen.</div><span class="visual-caption">FORM. MATERIAL. IDEE.</span></div></section>
<section class="program wrap" id="workshops" aria-labelledby="program-title"><div class="section-heading"><div><p class="eyebrow">Das Beispielprogramm</p><h2 id="program-title">Zeit für etwas Neues.</h2></div><p>Bevorstehende Termine<br><span><?php echo esc_html(wp_timezone_string()); ?></span></p></div>
<div class="demo-controls"><div><span class="live-dot"></span><strong><?php echo $mode === 'before' ? 'Vorher: Zeitzonenfehler' : 'Nachher: korrigierter Shortcode'; ?></strong><small>Demozeit: <?php echo esc_html($now->format('d.m.Y · H:i T')); ?></small></div><div class="version-switch" role="group" aria-label="Fehler und Korrektur vergleichen"><a <?php echo $mode === 'before' ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url(add_query_arg(['version' => 'before', 'topic' => $topic], home_url('/')) . '#workshops'); ?>">Vorher</a><a <?php echo $mode === 'after' ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url(add_query_arg(['version' => 'after', 'topic' => $topic], home_url('/')) . '#workshops'); ?>">Nachher</a></div></div>
<?php if ($mode === 'before') : ?><p class="issue-note"><strong>Absichtlich eingebauter Fehler:</strong> <?php echo (int) $lost; ?> <?php echo $lost === 1 ? 'kommender Termin fehlt' : 'kommende Termine fehlen'; ?>, weil der Zeitzonenversatz doppelt berücksichtigt wird. „Nachher“ zeigt dieselben WordPress-Inhalte mit der Korrektur.</p><?php endif; ?>
<nav class="topic-filters" aria-label="Workshop-Thema"><?php foreach ($topics as $slug => $label) : ?><a <?php echo $topic === $slug ? 'aria-current="true"' : ''; ?> href="<?php echo esc_url(add_query_arg(['version' => $mode, 'topic' => $slug], home_url('/')) . '#workshops'); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></nav>
<?php echo do_shortcode('[' . ($mode === 'before' ? 'atelier_schedule_before' : 'atelier_schedule') . ' topic="' . $topic . '" limit="6"]'); ?>
<p class="program-footnote">Alle Termine, Preise und Beschreibungen sind fiktive Beispieldaten. Es werden keine Buchungen angenommen.</p></section>
<section class="case-study wrap" id="arbeitsprobe" aria-labelledby="case-title"><div class="case-heading"><p class="eyebrow">Eine kleine WordPress-Arbeitsprobe</p><h2 id="case-title">Ein Detail.<br>Ein spürbarer Unterschied.</h2><p>Das Programm wird aus echten WordPress-Inhalten erzeugt. Ein eigenes Plugin liefert den Shortcode, diese Demo macht die Korrektur sichtbar.</p></div><div class="case-steps"><article><span>01</span><div><h3>Den Fehler eingrenzen</h3><p>Ein Termin in der nächsten Stunde ist bereits verschwunden. Die gespeicherte Startzeit und der Vergleichswert verwenden unterschiedliche Zeitmaßstäbe.</p></div></article><article><span>02</span><div><h3>Die Zeit korrekt vergleichen</h3><p>Unix-Zeit bleibt Unix-Zeit. Erst für die Anzeige wird die WordPress-Zeitzone verwendet. Themenfilter und Sortierung bleiben erhalten.</p></div></article><article><span>03</span><div><h3>Das Verhalten prüfen</h3><p>PHP-Regressiontests laufen mit echtem WordPress und SQLite: UTC, Zürich, andere Zeitzonen, Startgrenze, Filter und Seiteneffekte.</p></div></article></div></section>
</main><footer class="site-footer wrap"><span class="footer-brand">Atelier Nord.</span><p>Absichtliche Portfolio-Demo · Eigenes Plugin &amp; Theme<br>Keine Kundenreferenz · Keine Verbindung zu einem realen Atelier</p><a href="#hero-title">Nach oben ↑</a></footer>
<?php wp_footer(); ?></body></html>

# Atelier Nord — WordPress workshop demo

A WordPress shortcode that filters and orders upcoming workshops correctly across timezones. The sample reproduces an offset-based cutoff error: the faulty fixture shows four workshops; comparing genuine Unix timestamps restores all six upcoming workshops.

Atelier Nord is an independent portfolio exercise with fictional events, fees and generated imagery, not a customer project. It includes a custom plugin, an English demonstration theme and tests running against real WordPress.

## Views

![Desktop view](wordpress-demo-desktop.png?revision=photos-20260908)

![Workshop schedule](wordpress-demo-gallery.png?revision=photos-20260908)

[Mobile view](wordpress-demo-mobile.png)

## What the plugin does

```text
[atelier_schedule]
[atelier_schedule topic="papier" limit="3"]
```

- Reads published `atelier_workshop` posts through real `WP_Query`.
- Filters by the `atelier_topic` taxonomy; an unknown topic has a useful empty state.
- Includes workshops whose start is now or later and orders them by their actual start instant.
- Preserves the page's main post context.
- Provides WordPress editor fields for a local start date/time and a CHF example fee. `0` displays as “Free”.

`_atelier_start_utc` stores a genuine Unix timestamp; the site's timezone controls display and editor conversion. An unchanged displayed minute preserves its existing instant and seconds. A **new or changed** ambiguous local time selects the **later occurrence**: Zurich `2030-10-27 02:30` becomes `01:30 UTC`, while an unchanged entry stored as `00:30 UTC` keeps that instant. Invalid dates and nonexistent spring-forward times leave the previous value untouched. The editor explains this policy beside the field.

## The specific correction

A value from `current_time('timestamp')` includes the site's GMT offset. Comparing it with genuine Unix timestamps moves the cutoff: Zurich summer events can disappear two hours early, while negative offsets can reintroduce past events.

The fixed code compares `current_datetime()->getTimestamp()` with stored Unix timestamps and uses `wp_date()` for local display. The intentionally faulty behavior exists only in `demo/legacy-shortcode.php`; the distributable `atelier-workshops` plugin never loads it.

API references: [current_time](https://developer.wordpress.org/reference/functions/current_time/), [current_datetime](https://developer.wordpress.org/reference/functions/current_datetime/), [wp_date](https://developer.wordpress.org/reference/functions/wp_date/), [WP_Query](https://developer.wordpress.org/reference/classes/wp_query/), and [DateTimeZone::getTransitions](https://www.php.net/manual/en/datetimezone.gettransitions.php).

## Files

```text
atelier-workshops/        Standalone custom plugin; contains the corrected code
atelier-portfolio-theme/ Fictitious demonstration theme
demo/                    Explicit BEFORE fixture, frozen demo clock and sample content
tests/                   Integration tests and a self-contained SQLite test bootstrap
```

Only this source directory is intended for publication. WordPress core, PHP binaries, SQLite plugin binaries, local databases, server logs and administrator credentials are excluded.

## Try the plugin in a disposable WordPress site

1. Copy `atelier-workshops/` into `wp-content/plugins/`, then activate **Atelier Workshop Schedule**.
2. In **Workshops**, create published entries with a start date/time, excerpt, topic and optional fee. Editor input uses the site's configured timezone.
3. Add `[atelier_schedule]` to a page. Use `topic` and `limit` when needed.

The plugin works independently of the theme. Tested with WordPress 7.1 and official SQLite integration 3.0.1; other WordPress/database combinations are unverified.

The plugin emits semantic HTML and class names; an existing site supplies its own CSS. The demo theme adds the layout and local images through `atelier_workshop_art`. Stable `_atelier_demo_key` values map six records to six individual photographs with descriptive alt text, explicit dimensions, lazy loading and bounded thumbnails. The plugin does not depend on these images.

## Recreate the optional before/after site

Use a disposable local installation with the plugin active. Copy the theme into `wp-content/themes/`. Copy `demo/` to `wp-content/mu-plugins/atelier-demo/`, then create this root-level mu-plugin:

```php
<?php
require __DIR__ . '/atelier-demo/environment.php';
```

From CLI, bootstrap that WordPress installation and require `wp-content/mu-plugins/atelier-demo/seed.php`. The seed creates or updates `_atelier_demo_fixture` posts, preserving fixture IDs and UTC instants on repeat runs. It sets the clock to **8 September 2026, 18:30 Europe/Zurich**, activates the theme, and changes the site title, language and sample taxonomy labels. Use it only in a disposable site.

The **Before/After** control in “WordPress implementation” compares the faulty fixture and corrected shortcode using the same content. Topic filters work in both modes, and the fixed clock is shown beside the explanation.

## Actual validation

Executed locally with **PHP 8.4.25**, **WordPress 7.1**, **SQLite Database Integration 3.0.1** and **SQLite 3.53.4**:

**11 tests passed, 0 failed**, using genuine WordPress functions, hooks, queries, shortcodes, capabilities and editor saves against a separate SQLite database. No WordPress or database functions are mocked.

1. UTC: past excluded, exact start included, chronological order.
2. Zurich summer: next-hour event retained; faulty fixture demonstrably loses it.
3. New York: negative offset does not reintroduce past events.
4. Kathmandu: fractional offset affects display only.
5. Autumn DST repeated hour: identical wall-clock labels remain distinct instants.
6. Real shortcode filtering, limit, draft exclusion and empty state.
7. Main WordPress post remains unchanged after rendering the shortcode.
8. WordPress editor save converts local input to UTC storage and back to the correct display.
9. Unchanged first and second occurrences of Zurich's autumn 02:30 both preserve their UTC instants.
10. Newly entered ambiguous times select the later occurrence in Zurich and New York.
11. An unchanged minute preserves stored seconds; a nonexistent spring-forward input cannot overwrite it.

All **8 PHP files passed syntax checks**. HTTP checks returned **200**, verified 6 corrected / 4 faulty / 2 paper-topic results, all six title/photo/alt associations and six photo endpoints. All seven fixture IDs and UTC instants were preserved after seeding.

The current English page passed browser review at 1440px desktop and 375px mobile: six matching photographs loaded, no horizontal overflow was detected, Paper displayed its two workshops, and Before/After displayed four/six workshops. The screenshots above are actual browser captures of this revision.

## Run the real WordPress tests

Use PHP 8.4 with the `pdo_sqlite`, `sqlite3` and `mbstring` extensions enabled. No Composer dependencies, database server, browser or running HTTP server are needed.

1. Extract [WordPress 7.1](https://downloads.wordpress.org/release/wordpress-7.1.zip) to a disposable directory. Do not use a live site's files: WordPress also loads any installed mu-plugins during bootstrap.
2. Extract the official [SQLite Database Integration 3.0.1](https://downloads.wordpress.org/plugin/sqlite-database-integration.3.0.1.zip) into that directory's `wp-content/plugins/sqlite-database-integration/`.
3. Copy that plugin's `db.copy` to `wp-content/db.php`.
4. Copy this repository's `atelier-workshops/` directory into `wp-content/plugins/`.
5. From this source directory, run the following commands, replacing the WordPress root with the extracted directory:

```powershell
$env:WP_DEMO_TEST = '1'
$env:ATELIER_WORDPRESS_ROOT = 'C:\path\to\disposable\wordpress'
php tests/regression.php tests/bootstrap.php
```

Or with a POSIX shell:

```sh
WP_DEMO_TEST=1 ATELIER_WORDPRESS_ROOT=/path/to/disposable/wordpress \
  php tests/regression.php tests/bootstrap.php
```

`tests/bootstrap.php` bypasses any existing `wp-config.php` and installs WordPress into `tests/.test-data/tests.sqlite`. It blocks HTTP requests and mail and disables cron and automatic updates. No administrator credentials are needed or printed. `ATELIER_TEST_DATA_DIR` optionally selects another disposable directory; the database filename must remain `tests.sqlite`. The default data folder is excluded by `.gitignore` and must not be published.

The suite creates a temporary **Editor**, verifies the actual edit capability, and removes that user and its workshop fixtures even after failed assertions. The database remains for repeat runs; an empty data directory exercises first-install bootstrap. Both paths passed. Other installed plugins, web authentication and browser appearance are outside this suite's scope.

## Scope

This sample covers a content shortcode and a timezone correctness fix. It has no booking or payment flow and does not demonstrate Elementor/Divi specialization, production-scale load testing or a third-party plugin compatibility audit.

Code license: [GPL-2.0-or-later](LICENSE). WordPress and the SQLite integration retain their own licenses and are not bundled in this source directory.

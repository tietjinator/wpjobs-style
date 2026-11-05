# WPJM Custom Styles

A stylesheet for [WP Job Manager](https://github.com/Automattic/WP-Job-Manager) with its own tiny loader plugin. Written against plugin version 2.4.9.

## Install

1. Zip this folder, or copy it to `wp-content/plugins/wpjm-custom-styles/`.
2. Activate **WPJM Custom Styles** in the WordPress admin.

If you would rather not run a plugin, copy `css/wpjm-custom.css` into your child theme and enqueue it from `functions.php` at priority 100 or higher.

## Live preview

Open this in a browser to get a throwaway WordPress site with WP Job Manager, this plugin and sample listings (needs a public repo, and it pulls the `main` branch, so reload after each push):

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tietjinator/wpjobs-style/main/blueprint.json

## Sidebar filters

`css/wpjm-sidebar.css` turns the `[jobs]` filter form into a sticky left sidebar with the results on the right (screens 900px and wider; it stacks above the results on smaller screens). It is pure CSS on the plugin's own markup, so no template overrides are needed. Width, gap and sticky offset are variables at the top of the file (`--wpjm-sidebar-width`, `--wpjm-sidebar-gap`, `--wpjm-sidebar-top`).

`includes/sidebar-filters.php` adds a **Sort by** dropdown to the form (featured first, newest, oldest, title A to Z, title Z to A) and applies it to the AJAX query. Change the options with the `wpjm_cs_sort_options` filter. Listings without the filter form (`show_filters="false"`, widgets) are not affected.

## Geocoding (replaces Google Maps)

WP Job Manager geocodes a listing's location text when the listing is saved and stores coordinates, formatted address, state and country as post meta. This repo replaces Google with a configurable provider in `includes/geocoding.php`. Google is always disabled, so the Google Maps API key field in Job Listings > Settings is ignored. Results are cached for 7 days.

The default is OpenStreetMap's Nominatim, with no setup. To change it, add one of these to `wp-config.php`, above the "stop editing" line.

Switch to Geoapify (hosted, OpenStreetMap based, free tier, allows storing results):

```php
define( 'WPJM_CS_GEOCODER', 'geoapify' );
define( 'WPJM_CS_GEOAPIFY_KEY', 'your-api-key' );
```

Turn geocoding off entirely:

```php
define( 'WPJM_CS_GEOCODER', 'off' );
```

With geocoding off, nothing is looked up or saved. Location search still works on the text people typed. You lose the stored coordinates and searching by state name when the listing only says a city.

The contact email sent to Nominatim, so OpenStreetMap can reach you if your site causes problems, defaults to `design@wesleyan.org`. Override it with:

```php
define( 'WPJM_CS_GEOCODER_EMAIL', 'you@example.com' );
```

Notes:

- Nominatim's public server allows at most 1 request per second and no bulk use. The code waits between requests and caches results, which is fine for people posting jobs, not for importing thousands of listings. See https://operations.osmfoundation.org/policies/nominatim/.
- Only listings saved or edited after you switch are geocoded. Existing listings keep their Google data. To refresh one, edit its location and save.
- A failed lookup (network error, bad Geoapify key) is not cached and saves nothing, so the next save tries again. A location with no match is cached for a day.
- Both providers' responses are mapped onto the plugin's own `geolocation_*` fields, so search and any add-on that reads them keep working. Add-ons that load Google Maps themselves are unaffected by this.

## Customising

Start with the tokens at the top of `css/wpjm-custom.css` (colours, radius, spacing, job type colours). The file is split into numbered sections, one per area: notices, search filters, listings, single page, submit form, dashboard, pagination, widgets. Put site-specific rules in section 12.

Selectors are prefixed with `body` to beat the plugin's own specificity without `!important`. If a rule does not apply, check specificity in your browser's dev tools before reaching for `!important`.

The plugin also exposes `--jm-ui-*` CSS variables for the dashboard and newer UI components. The two most useful are already mapped to the tokens, and the full list is in the plugin's `assets/css/ui.neutral.scss`.

## Notes

- Job type colours depend on the type slug (`full-time`, `part-time`, ...). Add a rule in section 5 for each type you create.
- The plugin only loads its CSS on pages it detects as job pages. If you use the shortcodes in unusual places, see the `job_manager_enqueue_frontend_style` filter.
- Plugin markup changes between major versions. After upgrading WP Job Manager, re-check the listings, single page and dashboard.

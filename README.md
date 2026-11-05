# WPJM Custom Styles

A stylesheet for [WP Job Manager](https://github.com/Automattic/WP-Job-Manager) with its own tiny loader plugin. Written against plugin version 2.4.9.

## Install

1. Zip this folder, or copy it to `wp-content/plugins/wpjm-custom-styles/`.
2. Activate **WPJM Custom Styles** in the WordPress admin.

If you would rather not run a plugin, copy `css/wpjm-custom.css` into your child theme and enqueue it from `functions.php` at priority 100 or higher.

## Live preview

Open this in a browser to get a throwaway WordPress site with WP Job Manager, this plugin and sample listings (needs a public repo, and it pulls the `main` branch, so reload after each push).:

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tietjinator/wpjobs-style/main/blueprint.json

## Sidebar filters

`css/wpjm-sidebar.css` turns the `[jobs]` filter form into a sticky left sidebar with the results on the right (screens 900px and wider; it stacks above the results on smaller screens). It is pure CSS on the plugin's own markup, so no template overrides are needed. Width, gap and sticky offset are variables at the top of the file (`--wpjm-sidebar-width`, `--wpjm-sidebar-gap`, `--wpjm-sidebar-top`).

The sidebar order is keywords, location, a collapsible **Job type** drawer (job types plus the remote checkbox), category, then the Search Jobs and Reset Filters buttons. The drawer is a native `<details>` element, open by default; return `false` from the `wpjm_cs_types_drawer_open` filter to start it closed. `includes/sidebar-filters.php` adds a **Sort by** dropdown at the top right of the results (featured first, newest, oldest, title A to Z, title Z to A) and applies it to the AJAX query. The dropdown sits outside the filter form, so `js/sidebar.js` links it back to the form and clears it on Reset Filters. Change the options with the `wpjm_cs_sort_options` filter. Listings without the filter form (`show_filters="false"`, widgets) are not affected.

## Geolocation is off

WP Job Manager normally sends each listing's location to Google's Geocoding API on save and stores coordinates. `includes/no-geolocation.php` turns that off, so no location data leaves your site and no API key is needed. Location search still works by matching the text people typed (for example "Austin" matches "Austin, TX"). Searching by state name (such as "Texas") only matches if the listing text contains it. Listings saved earlier keep any coordinates Google already gave them.

An OpenStreetMap (Nominatim) and Geoapify version of this, with a switch to turn it off, is in the git history at commit `d3204b1` if you want it back.

## Customising

Start with the tokens at the top of `css/wpjm-custom.css` (colours, radius, spacing, job type colours). The file is split into numbered sections, one per area: notices, search filters, listings, single page, submit form, dashboard, pagination, widgets. Put site-specific rules in section 12.

Selectors are prefixed with `body` to beat the plugin's own specificity without `!important`. If a rule does not apply, check specificity in your browser's dev tools before reaching for `!important`.

The plugin also exposes `--jm-ui-*` CSS variables for the dashboard and newer UI components. The two most useful are already mapped to the tokens, and the full list is in the plugin's `assets/css/ui.neutral.scss`.

## Notes

- Job type colours depend on the type slug (`full-time`, `part-time`, ...). Add a rule in section 5 for each type you create.
- The plugin only loads its CSS on pages it detects as job pages. If you use the shortcodes in unusual places, see the `job_manager_enqueue_frontend_style` filter.
- Plugin markup changes between major versions. After upgrading WP Job Manager, re-check the listings, single page and dashboard.

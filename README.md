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

The sidebar order is keywords, location, a collapsible **Job type** drawer (job types plus the remote checkbox), category, then the Search Jobs and Reset Filters buttons. The drawer header shows a hint of what is filtered ("All types", "2 of 5", plus "Remote"), which `js/sidebar.js` keeps up to date. The drawer is a native `<details>` element, closed by default; return `true` from the `wpjm_cs_types_drawer_open` filter to start it open. `includes/sidebar-filters.php` adds a **Sort by** dropdown above the block at the top right, on the same line as your page heading (newest first by default, then oldest first, featured first, title A to Z, title Z to A) and applies it to the AJAX query. The dropdown sits outside the filter form, so `js/sidebar.js` links it back to the form and clears it on Reset Filters. Change the options with the `wpjm_cs_sort_options` filter. Listings without the filter form (`show_filters="false"`, widgets) are not affected.

## Geolocation is off

WP Job Manager normally sends each listing's location to Google's Geocoding API on save and stores coordinates. `includes/no-geolocation.php` turns that off, so no location data leaves your site and no API key is needed. Location search still works by matching the text people typed (for example "Austin" matches "Austin, TX"). Searching by state name (such as "Texas") only matches if the listing text contains it. Listings saved earlier keep any coordinates Google already gave them.

An OpenStreetMap (Nominatim) and Geoapify version of this, with a switch to turn it off, is in the git history at commit `d3204b1` if you want it back.

The Sort by control is positioned above the filters and results, so the first listing lines up with the top of the filter card. How far above is `--wpjm-sort-lift` at the top of `css/wpjm-sidebar.css`. Adjust it until the dropdown lines up with your page heading, since the gap under a heading differs between themes. On screens under 900px it sits right-aligned above the results instead.

## City, State / Province and ZIP fields

`includes/location-fields.php` replaces the single Location field with three: City, State / Province (free text) and ZIP / Postal code, on the "Post a Job" form, the edit form and the admin "Job Listing Data" box. All three are optional. They are saved separately (`_job_city`, `_job_state`, `_job_postcode`) and combined into the plugin's own location value, for example `Austin, TX 78701`. WP Job Manager only knows that combined value, so listing cards, the single job page and the Location search box keep working, and searching "Austin", "TX" or "78701" finds the listing.

- State names are shortened when a listing is saved: "Texas", "texas" or "tx" all become `TX`. This covers US states, DC, US territories and Canadian provinces. Anything not in that list, such as a region in another country, is kept as typed. Add or remove entries with the `wpjm_cs_state_map` filter, or return `false` from `wpjm_cs_abbreviate_state` to turn it off. "Washington" means the state (WA). Type "Washington DC" or "DC" for the district.
- Searching the full name still works. Typing "Texas" in the Location box also searches for `, TX`, so it finds listings saved as `Austin, TX 78701`.
- Remote listings with no city, state or ZIP show just "Remote".
- Older listings keep their existing location text until someone fills in the new fields. Editing an old listing without filling them in does not wipe its location.
- In the admin the old Location field is relabelled "Location (combined)" and is filled in automatically when any part is set.
- To change the combined format, use the `wpjm_cs_format_location` filter.
- Listing page URLs no longer include the location, because the plugin builds that from the old single field. Titles and company names still apply.

## Keeping up with WP Job Manager updates

Nothing here edits WP Job Manager, so its updates install as normal. The location fields, sort and geolocation switch use its documented hooks. The sidebar layout depends on its current page markup. After updating WP Job Manager, open the Playground link above (it builds a fresh site from the latest release) and check:

1. The sidebar order and the Job type drawer, including its hint.
2. Search, Reset Filters and Sort by reload the results.
3. Posting a job shows City, State and ZIP, and the saved listing displays "City, ST 12345".
4. Searching a city, a state and a ZIP finds the listing.

`includes/compat.php` also shows an admin warning on the Dashboard, Plugins and Job Listings screens when the installed WP Job Manager has a newer major version than the one this was built for (2.x).

## Customising

Start with the tokens at the top of `css/wpjm-custom.css` (colours, radius, spacing, job type colours). The file is split into numbered sections, one per area: notices, search filters, listings, single page, submit form, dashboard, pagination, widgets. Put site-specific rules in section 12.

Selectors are prefixed with `body` to beat the plugin's own specificity without `!important`. If a rule does not apply, check specificity in your browser's dev tools before reaching for `!important`.

The plugin also exposes `--jm-ui-*` CSS variables for the dashboard and newer UI components. The two most useful are already mapped to the tokens, and the full list is in the plugin's `assets/css/ui.neutral.scss`.

## Notes

- Job type colours depend on the type slug (`full-time`, `part-time`, ...). Add a rule in section 5 for each type you create.
- The plugin only loads its CSS on pages it detects as job pages. If you use the shortcodes in unusual places, see the `job_manager_enqueue_frontend_style` filter.
- Plugin markup changes between major versions. After upgrading WP Job Manager, re-check the listings, single page and dashboard.

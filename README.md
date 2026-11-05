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
- Searching by state works with the code or the full name, in any case: `TX`, `tx`, `Texas`, `TEXAS` all find the same listings, including ones with no city. Typing a state alone searches for that exact state, so `IN` finds Indiana and not every listing with "in" inside a word, and `Virginia` does not also return West Virginia. "Washington" returns Washington state and the listing in Washington, DC. How it works: each time a listing is saved, `geolocation_state_long` (a field the plugin's Location search already checks) is set to the name plus a bracketed code, such as `Texas (TX)`, and a state-only search looks for the bracketed code. Older listings saved as `Austin, TX` are found through a `, TX` search until they are next saved.
- **Location box hidden.** The sidebar no longer shows a separate Location box. Keywords is relabelled "Keywords or city", and it finds a city, a ZIP, "City, ST" or "City, Texas" as well as titles, companies and descriptions. State has its own dropdown. Typing a state name in Keywords works too, because the full name is added to the fields Keywords searches. Short codes are noisy in Keywords (it matches letters anywhere, so "IN" matches many listings), which is why the dropdown is the precise way to filter by state. The Location search code still exists and still works if a link passes `?search_location=`. To bring the box back, delete the last rule in `css/wpjm-sidebar.css`.
- **State / Province filter.** The sidebar has a State / Province dropdown under Location, listing only the states and provinces that have published listings, grouped under United States and Canada, with a count for each ("Texas (3)"). Choosing one filters the results straight away, and Reset Filters clears it. It combines with the Location box, keywords, job types, remote and category (all must match). The list is built from each listing's saved state and cached for an hour. It refreshes when a listing is saved, published, trashed or deleted. Older listings saved as "Austin, TX" are included in the results for that state, but are not counted in the dropdown until they are next saved. If no listing has a state, the dropdown is not shown.
- "City, State" works in any form: `Austin, TX`, `Austin, Texas`, `Austin TX` and `Austin Texas` all find `Austin, TX 78701`. Other text, a partial name like "Tex", a city, or a ZIP, is searched as typed.
- With "Remote positions only" ticked, a listing has to be remote and match the location.
- Remote listings with no city, state or ZIP show just "Remote".
- Older listings keep their existing location text until someone fills in the new fields. Editing an old listing without filling them in does not wipe its location.
- In the admin the old Location field is relabelled "Location (combined)" and is filled in automatically when any part is set.
- To change the combined format, use the `wpjm_cs_format_location` filter.
- Listing page URLs no longer include the location, because the plugin builds that from the old single field. Titles and company names still apply.

## Testing the state search

`blueprint-states.json` is a Playground blueprint with a listing for every US state, DC and Canadian province, entered the way people type them (full names, lower and upper case codes, some with no city):

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tietjinator/wpjobs-style/main/blueprint-states.json

For an automated check against any WordPress site that has this plugin and the seeded listings, `tests/seed-states.php` creates them (`wp eval-file tests/seed-states.php`) and `tests/state-filter.php` checks the State / Province dropdown (every state alone, combined with other filters, and edge cases). `tests/search-matrix.php` runs about 600 searches (add `keywords` as a second argument to type them into the Keywords box instead of the Location box) (code, lower-case code, full name in three cases, city, ZIP, "City, ST", "City, Full name", "City ST", "City Full name", and a name followed by an unrelated term) through the plugin's search request and reports whether the right listing comes back and what else matches with it:

```bash
php tests/search-matrix.php https://your-site.example
php tests/state-filter.php https://your-site.example
```

## Keeping up with WP Job Manager updates

Nothing here edits WP Job Manager, so its updates install as normal. The location fields, sort and geolocation switch use its documented hooks. The sidebar layout depends on its current page markup. After updating WP Job Manager, open the Playground link above (it builds a fresh site from the latest release) and check:

1. The sidebar order and the Job type drawer, including its hint.
2. Search, Reset Filters and Sort by reload the results.
3. Posting a job shows City, State and ZIP, and the saved listing displays "City, ST 12345".
4. Searching a city, a state and a ZIP finds the listing.

`includes/compat.php` also shows an admin warning on the Dashboard, Plugins and Job Listings screens when the installed WP Job Manager has a newer major version than the one this was built for (2.x).

## Brand colours

The colours come from the Wesleyan brand refresh guide and are defined once at the top of `css/wpjm-custom.css` (`--twc-*`). Everything else points at them, so changing a swatch there changes it everywhere. The guide lists print (CMYK) values only, so the hex values here are those swatches converted to RGB. If you have official web values, replace them in that one block.

| Colour | Hex | Used for |
|---|---|---|
| Navy | `#08496f` | Buttons, links, focus rings, the drawer hint |
| Deep Navy | `#00344d` | Text, button hover |
| Bright Blue | `#34a6de` | Freelance job type (accent only, see below) |
| Mist | `#d9eae8` | Card borders and dividers |
| Milky | `#f3f0e9` | Soft panels (company box, application details, notices) |
| Hazy | `#cbcaca` | Form field borders |
| Charcoal | `#414042` | Secondary text (company, location, labels) |
| Gold | `#f1a846` | Featured listings, Part Time |
| Scarlet | `#cb2d4c` | Errors, Temporary, filled and expired notices |
| Seafoam | `#78b4af` | Full Time |
| Celery | `#adbb73` | Internship |

Two accessibility choices follow from the palette. White text on Bright Blue is only 2.7:1, so buttons and links use Navy (9.6:1) and Bright Blue is kept for accents. Celery, Seafoam and Gold are about 2:1 as text on white, so the job type pills use a darker mix of each colour (45% of the colour, 55% Deep Navy; 70% for Scarlet) on a light tint of it, which gives 4.9:1 or better. The coloured dots in the sidebar use the pure brand colours. The Job type drawer header is `#e7e7e7`, which you asked for and is not a brand colour. Change `--wpjm-drawer-bg` in `css/wpjm-sidebar.css` to `var(--twc-milky)` or `var(--twc-hazy)` to bring it into the palette.

## Type scale

All sizes come from a few tokens at the top of `css/wpjm-custom.css` (`--wpjm-fs-*`), in `rem`, so they no longer depend on how large the theme sets its text (many themes use 20px or more, which made the listings huge and the form fields tiny by comparison).

| Token | Size | Used for |
|---|---|---|
| `--wpjm-fs-label` | 12px | Sidebar labels, drawer header, job type pills, "Sort by" |
| `--wpjm-fs-small` | 13px | Posted dates, the drawer hint, help text, tagline |
| `--wpjm-fs-body` | 15px | Company, location, checkbox labels, buttons, notices, form labels |
| `--wpjm-fs-input` | 15px (16px under 900px) | Form fields. Phones zoom the page on fields smaller than 16px |
| `--wpjm-fs-title` | 18px | Listing titles, company name on the job page |
| `--wpjm-fs-reading` | 16px | Job description |

Other changes made with it: form fields and buttons use the theme font instead of the browser default, the company logo/placeholder icon is hidden in the listing rows, and each row is a grid (title and company, location, then job type and date) instead of the plugin's floated percentage columns. The row switches to a stacked layout when the listings column is narrower than 640px, which happens on phones and also on a laptop with the sidebar open, so dates no longer wrap.

## Customising

Start with the tokens at the top of `css/wpjm-custom.css` (colours, radius, spacing, job type colours). The file is split into numbered sections, one per area: notices, search filters, listings, single page, submit form, dashboard, pagination, widgets. Put site-specific rules in section 12.

Selectors are prefixed with `body` to beat the plugin's own specificity without `!important`. If a rule does not apply, check specificity in your browser's dev tools before reaching for `!important`.

The plugin also exposes `--jm-ui-*` CSS variables for the dashboard and newer UI components. The two most useful are already mapped to the tokens, and the full list is in the plugin's `assets/css/ui.neutral.scss`.

## Notes

- Job type colours depend on the type slug (`full-time`, `part-time`, ...). Add a rule in section 5 for each type you create.
- The plugin only loads its CSS on pages it detects as job pages. If you use the shortcodes in unusual places, see the `job_manager_enqueue_frontend_style` filter.
- Plugin markup changes between major versions. After upgrading WP Job Manager, re-check the listings, single page and dashboard.

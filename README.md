# WPJM Custom Styles

Customisations for [WP Job Manager](https://github.com/Automattic/WP-Job-Manager), kept in a small separate plugin so the main plugin is never edited and its updates install as normal. Written against WP Job Manager 2.4.9.

What it does:

- A sidebar layout for the `[jobs]` page: keywords or city, State / Province, a collapsible Job type drawer (with the remote checkbox), category, Search Jobs and Reset Filters. Sort by sits at the top right of the results.
- City, State / Province and ZIP / Postal code fields in place of the single Location field, with state names shortened to codes ("Texas" becomes "TX").
- A State / Province filter and searches that find states, cities and ZIP codes.
- The Wesleyan brand colours and a consistent type scale across listings, the job page and the forms.
- Google geolocation turned off.

## Contents

| File | What it is |
|---|---|
| `wpjm-custom-styles.php` | The plugin file. Loads the files below. |
| `css/wpjm-custom.css` | Colours, type scale, listings, job page, forms, dashboard. Start here. |
| `css/wpjm-sidebar.css` | The sidebar layout, Job type drawer and Sort by control. |
| `js/sidebar.js` | Links Sort by to the filter form, clears it on Reset, and keeps the drawer hint up to date. |
| `includes/sidebar-filters.php` | Sort by, the Job type drawer markup, the Reset Filters button, and removing the "Showing N jobs" message and RSS link. |
| `includes/job-page.php` | The single job page as two columns: adds the title inside the page and wraps the details and main column. |
| `includes/submit-form.php` | The Post a Job form: "Church / Company" wording, removed company fields, the Remote Position checkbox and the job description PDF upload. |
| `includes/location-fields.php` | City / State / ZIP fields, state shortening, the State / Province filter, and the location search rules. |
| `includes/no-geolocation.php` | Turns off Google geolocation. |
| `includes/compat.php` | Admin warning when WP Job Manager is a newer major version than this was built for. |
| `blueprint.json`, `blueprint-states.json` | WordPress Playground blueprints for previewing and testing. |
| `tests/` | Seed data and automated search checks. |

## Install

1. Zip this folder, or copy it to `wp-content/plugins/wpjm-custom-styles/`.
2. Activate **WPJM Custom Styles** in the WordPress admin.

Requires WP Job Manager. Browser support: the layout uses `:has()`, container queries and `color-mix()`, which need Chrome and Edge 111+, Safari 16.2+ or Firefox 121+ (all from 2023). Older browsers show the plugin's plain styles with the sidebar stacked above the results.

## Live preview

Open this in a browser for a throwaway WordPress site with WP Job Manager, this plugin and sample listings. It needs the repo to be public and it pulls the `main` branch, so reload after each push.

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tietjinator/wpjobs-style/main/blueprint.json

The preview site also has the other screens. Add `&url=/page/` to the link to open one directly:

- Post a Job form: `&url=/post-a-job/`
- Employer dashboard: `&url=/job-dashboard/`
- Jobs list (the default): `&url=/jobs/`

For example, https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tietjinator/wpjobs-style/main/blueprint.json&url=/post-a-job/

GitHub caches raw files for about five minutes, so a link opened right after a push can build the site from the previous version (for example a menu with no "Post a Job" page). To be sure of the latest, replace `main` in the link with the full commit ID (`git rev-parse HEAD`), which is never cached.

## Sidebar and sorting

`css/wpjm-sidebar.css` turns the `[jobs]` filter form into a sticky left sidebar with the results on the right on screens 900px and wider. Below that the filters stack above the results. It works on the plugin's own markup, with no template overrides. Wrappers without a filter form (`[jobs show_filters="false"]`, widgets) are not touched.

Sidebar order: **Keywords or city**, **State / Province**, the **Job type** drawer, **Category**, then **Search Jobs** and **Reset Filters**. The separate Location box is hidden (see below).

- **Job type drawer.** A native `<details>` element holding the job types and the "Remote positions only" checkbox. It starts closed. The header shows what is filtered ("All types", "2 of 5", plus "Remote"), kept up to date by `js/sidebar.js`. While open or focused the header has a grey background (`--wpjm-drawer-bg`). To start it open, return `true` from the `wpjm_cs_types_drawer_open` filter.
- **Reset Filters.** A button under Search Jobs. It uses the plugin's own reset handling, and also clears Sort by.
- **Sort by.** A dropdown above the block at the top right, on the same line as your page heading. Options are Newest first (default), Oldest first, Featured first, Title A to Z and Title Z to A. It sits outside the filter form, so `js/sidebar.js` links it back to the form. Change the options with the `wpjm_cs_sort_options` filter. Featured listings are only pinned to the top when "Featured first" is chosen.
- **Removed on purpose.** The "Showing N jobs" message and its RSS link under the filters.
- **Layout variables** at the top of `css/wpjm-sidebar.css`:
  - `--wpjm-sidebar-width` and `--wpjm-sidebar-gap`
  - `--wpjm-sidebar-top`: raise it if you have a sticky site header.
  - `--wpjm-listings-width`: the width of the whole filters and results block (default `80vw`). Use e.g. `1440px` for a fixed width, or `100%` to stay inside the theme's column.
  - `--wpjm-sort-lift`: how far above the block Sort by sits. Adjust it until the dropdown lines up with your page heading, since the gap under a heading differs between themes.

## Location, State / Province and search

### Fields

`includes/location-fields.php` replaces the single Location field with **City**, **State / Province** (free text) and **ZIP / Postal code** on the "Post a Job" form, the edit form and the admin "Job Listing Data" box. All three are optional. They are saved separately (`_job_city`, `_job_state`, `_job_postcode`) and combined into the plugin's own location value, for example `Austin, TX 78701`. WP Job Manager only reads that combined value, so listing cards, the job page and search keep working.

- Remote listings with no city, state or ZIP show just "Remote".
- Older listings keep their existing location text until someone fills in the new fields. Editing an old listing without filling them in does not wipe its location.
- In the admin the old field is relabelled "Location (combined)" and is filled in automatically when any part is set.
- Listing page URLs no longer include the location, because the plugin builds that from the old single field. Titles and company names still apply.
- Change the combined format with the `wpjm_cs_format_location` filter.

### State names become codes

When a listing is saved, "Texas", "texas" or "tx" all become `TX`. This covers US states, DC, US territories and Canadian provinces. Anything not in that list, such as a region in another country, is kept as typed. "Washington" means the state (WA); type "Washington DC" or "DC" for the district.

Add or remove entries with the `wpjm_cs_state_map` filter, or return `false` from `wpjm_cs_abbreviate_state` to turn shortening off.

### State / Province filter

A dropdown under Keywords. It lists only the states and provinces that have published listings, grouped under United States and Canada, with a count for each ("Texas (3)"). Choosing one filters straight away, and Reset Filters clears it. It combines with keywords, job types, remote and category (all must match). The list is built from each listing's saved state and cached for an hour. It refreshes when a listing is saved, published, trashed or deleted. If no listing has a state, the dropdown is not shown.

Older listings saved as "Austin, TX" appear in that state's results, but are not counted in the dropdown until they are next saved.

### Searching

The separate Location box is hidden, and Keywords is relabelled "Keywords or city". Keywords finds a city, a ZIP, "City, ST" or "City, Texas" as well as titles, companies and descriptions. A full state name typed in Keywords also works, because the full name is added to the fields Keywords searches. Typing a short code such as "IN" in Keywords is noisy, since it matches those letters anywhere, which is why the dropdown is the precise way to filter by state.

The old Location search still exists and still works for a link that passes `?search_location=`. For it, a state typed alone matches that exact state, so `IN` finds Indiana and `Virginia` does not also return West Virginia. "Washington" returns Washington state and the listing in Washington, DC (extend that list with `wpjm_cs_state_terms_also_searched_as_text`). "Austin, Texas" and "Austin TX" are read as "Austin, TX". To show the Location box again, delete the last rule in `css/wpjm-sidebar.css`.

How it works: each time a listing is saved, `geolocation_state_long` (a field the plugin's searches already check) is set to the name plus a bracketed code, such as `Texas (TX)`, and a state-only search looks for the bracketed code. Older listings are found through a `, TX` search until they are next saved.

With "Remote positions only" ticked, a listing has to be remote and match the other filters.

### Geolocation is off

WP Job Manager normally sends each listing's location to Google's Geocoding API on save and stores coordinates. `includes/no-geolocation.php` turns that off, so no location data leaves your site and no API key is needed. Listings saved earlier keep any coordinates Google already gave them. A version that used OpenStreetMap (Nominatim) or Geoapify instead, with a switch to turn it off, is in the git history at commit `d3204b1`.

The job page still links the location to Google Maps. Change that with the plugin's `the_job_location_map_link` filter.

## Post a Job form

Styled in section 15 of `css/wpjm-custom.css`. Beyond the colours and type scale, the changes are:

- Section headings such as "Company Details" are 20px semi-bold instead of the theme's large thin heading.
- The label column is a little wider (34%) so "X / Twitter username (optional)" stays on one line. On phones the plugin stacks the label above the field, and that is left alone.
- "(optional)" is smaller, lighter and on the same line as its label.
- "Have an account? Sign in" is a soft panel.
- The company logo upload has a branded Choose File button and a dashed field.
- The Preview step is a soft title bar joined to a light bordered box, with Edit listing (outlined) and Submit Listing (filled) side by side.
- Notices ("Job listed successfully") fit the page column. The plugin padded them without including the padding in their width, so they stuck out.
- Preview and Submit Listing fill the row on phones. The outlined buttons (Save Draft, Edit listing) turn white text on dark navy when hovered or focused.
- On screens 900px and wider the form and its preview are wider than the theme's narrow column and centred on the page, like the Jobs page. The width is `--wpjm-form-width` at the top of `css/wpjm-custom.css` (default `min(80vw, 1000px)`; use `80vw` to match the Jobs page exactly or `100%` to stay in the theme's column).
- Checkbox fields such as Remote Position show their help text to the right of the box instead of underneath.
- Wording: "Company Details", "Company name" and the name placeholder become "Church / Company ...", set in `includes/submit-form.php`. This applies on the public site only, not in the admin.
- **Job description PDF.** An optional "Job description (PDF)" upload sits right after the Description box. It accepts only `.pdf`, up to 5 MB (or the server's upload limit if that is lower; change it with the `wpjm_cs_pdf_max_size` filter). A "Download the full job description (PDF)" button then appears under the description on the preview and the job page. It is not shown on the Jobs list rows. In the admin "Job Listing Data" box the field is a file address plus an Upload button, so staff can attach or replace a PDF. The file is saved in the plugin's `uploads/job-manager-uploads/` folder, and is public to anyone with the link, like logos were. Only files in this site's uploads folder are linked.
- **Account panel.** The "Your account: You are currently signed in as ... Sign out" panel is hidden for logged-in people. Visitors who are not signed in still see "Have an account? Sign in", because that is how returning users sign in and where account creation fields live. Return `true` from the `submit_job_form_show_signin` filter (the plugin's own) to bring the logged-in panel back.
- **Field order.** Remote Position sits directly under Job type.
- **Remote Position checkbox.** The form only offers it when "Remote Position" is switched on in Job Listings > Settings, and the plugin treats a setting that was never saved as off. This plugin treats "never saved" as on, so the checkbox shows by default. Switching it off in the settings still hides it.
- Removed from the form: X / Twitter username, tagline, video and logo upload. Because no listing gets a logo now, the placeholder icon in the company box on the job page and preview is hidden. This only stops the form asking for them. Listings that already have those values keep them, and the admin edit screen still shows the fields. To bring one back, return a shorter list from the `wpjm_cs_removed_company_fields` filter.

The form was tested end to end in a browser: filling it in, previewing, and publishing. Typing "texas" in State saved `TX`, the combined location `Austin, TX 78701`, and the State / Province dropdown count updated.

## Job page

The single job page is two columns, matching the Jobs board: a details card on the left at the board's sidebar width (290px), and the title, description and Apply button on the right. It is as wide as the Jobs board, so moving between the two lines the columns up exactly. Both use `--wpjm-listings-width`, `--wpjm-sidebar-width` and `--wpjm-sidebar-gap` (see Sidebar and sorting). `--wpjm-job-width` in `css/wpjm-custom.css` is the page width and follows `--wpjm-listings-width` by default. Below 900px the page is one column: title, details, description, Apply.

- **Details card.** Job type (as the same pill used in the list), location, how long ago it was posted, salary if there is one, and the church or company with its website link. It sticks to the top as you scroll, like the sidebar on the Jobs board.
- **Main column.** The title, the description (kept to a readable line length, with headings in the same style as the form), the job description PDF button if there is one, and Apply for job.
- **How it is built.** `includes/job-page.php` wraps the plugin's own output with its `single_job_listing_start` and `single_job_listing_end` hooks and adds the title inside the page, so the title can share the wide column with the description. The theme's own title is hidden with CSS when ours is on the page. Only the main job page is changed. A `[job]` shortcode, the preview step and an expired listing keep the plugin's normal markup.
- **Theme leftovers hidden.** Twenty Twenty-Five prints blog-post parts around a job: an empty "Written by ... in ..." line, previous / next post links and a "More posts" list. They are hidden on job pages. These rules use that theme's block class names, so on another theme they do nothing, and you may need equivalent rules for its leftovers.
- The plugin puts an icon-font glyph on the location and date. The card's small labels override that font, or they would show as symbols.

## Brand colours

The colours come from the Wesleyan brand refresh guide and are defined once at the top of `css/wpjm-custom.css` (`--twc-*`). Everything else points at them, so changing a swatch there changes it everywhere. The guide lists print (CMYK) values only, so these hex values are those swatches converted to RGB. If you have official web values, replace them in that one block.

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
| White | `#ffffff` | Surfaces, text on buttons |

Derived colours: featured listing background `#fdf3e5` (a light tint of Gold). Job type pills, text on background: Full Time `#366e79` on `#e7f2f1`, Part Time `#6c684a` on `#fcefde`, Internship `#4e715e` on `#f0f3e6`, Freelance `#17678e` on `#daeff9`, Temporary `#8e2f4c` on `#f6d9df`.

Two accessibility choices follow from the palette. White text on Bright Blue is only 2.7:1, so buttons and links use Navy (9.6:1) and Bright Blue is kept for accents. Celery, Seafoam and Gold are about 2:1 as text on white, so the pills use a darker mix of each colour (45% of the colour and 55% Deep Navy; 70% for Scarlet) on a light tint of it, which gives 4.9:1 or better. The coloured dots in the sidebar use the pure brand colours.

The Job type drawer header is `#e7e7e7`, which is not a brand colour. Set `--wpjm-drawer-bg` in `css/wpjm-sidebar.css` to `var(--twc-milky)` or `var(--twc-hazy)` to bring it into the palette.

Add a colour for a new job type by adding a `--wpjm-type-<slug>` token and a rule in section 5 of `css/wpjm-custom.css`, following the pattern of the existing types.

## Type scale

All sizes come from tokens at the top of `css/wpjm-custom.css` (`--wpjm-fs-*`), in `rem`, so they do not depend on how large the theme sets its text. Many themes use 20px or more, which made the listings huge and the form fields tiny by comparison.

| Token | Size | Used for |
|---|---|---|
| `--wpjm-fs-label` | 12px | Sidebar labels, drawer header, job type pills, "Sort by" |
| `--wpjm-fs-small` | 13px | Posted dates, the drawer hint, help text, tagline |
| `--wpjm-fs-body` | 15px | Company, location, checkbox labels, buttons, notices, form labels |
| `--wpjm-fs-input` | 15px (16px under 900px) | Form fields. Phones zoom the page on fields smaller than 16px |
| `--wpjm-fs-title` | 18px | Listing titles, company name on the job page |
| `--wpjm-fs-reading` | 16px | Job description |

Related changes: form fields and buttons use the theme font instead of the browser default; the company logo and placeholder icon are hidden in the listing rows (the job page keeps its own); and each listing row is a grid (title and company, location, then job type and date) instead of the plugin's floated percentage columns. The row stacks, with the location under the title, when the listings column is narrower than 640px. That happens on phones and also on a laptop with the sidebar open, so dates no longer wrap.

## Customising

`css/wpjm-custom.css` is split into numbered sections: tokens, notices, search filters, listings, job type colours, the single job page, the submit form, the dashboard, pagination, widgets, responsive rules, button hover, type scale, job page extras and the Post a Job form. Put your own rules in the last section, "Your overrides", at the end of the file, so they win over everything above.

Selectors are prefixed with `body` to beat the plugin's own specificity without `!important`. If a rule does not apply, check specificity in your browser's dev tools before reaching for `!important`.

The plugin also exposes `--jm-ui-*` CSS variables for the dashboard and newer UI components. The accent, danger and radius ones are mapped to the tokens, and the full list is in the plugin's `assets/css/ui.neutral.scss`.

The plugin only loads its CSS on pages it detects as job pages. If you use the shortcodes elsewhere, see the `job_manager_enqueue_frontend_style` filter.

### Filters this plugin adds

| Filter | What it does |
|---|---|
| `wpjm_cs_sort_options` | Change the Sort by options |
| `wpjm_cs_types_drawer_open` | Return `true` to start the Job type drawer open |
| `wpjm_cs_state_map` | Add or remove state and province names |
| `wpjm_cs_abbreviate_state` | Return `false` to keep state names as typed |
| `wpjm_cs_state_terms_also_searched_as_text` | Names that should also be searched as plain text (default: `washington`) |
| `wpjm_cs_format_location` | Change the combined location text |
| `wpjm_cs_pdf_max_size` | Largest job description PDF in bytes (default 5 MB) |
| `wpjm_cs_removed_company_fields` | Which company fields are removed from the Post a Job form (default: Twitter, tagline, video, logo) |

## Keeping up with WP Job Manager updates

Nothing here edits WP Job Manager. The location fields, sort and geolocation switch use its documented hooks. The sidebar layout depends on its current page markup, so that is the part that can need a touch-up after a major update. After updating WP Job Manager, open the Playground link above (it builds a fresh site from the latest release) and check:

1. The sidebar order and the Job type drawer, including its hint.
2. Search, State / Province, Reset Filters and Sort by reload the results.
3. Posting a job shows City, State and ZIP, and the saved listing displays "City, ST 12345".
4. Searching a city, a state and a ZIP finds the listing.

`includes/compat.php` also shows an admin warning on the Dashboard, Plugins and Job Listings screens when the installed WP Job Manager has a newer major version than the one this was built for (2.x).

## Testing the state search

`blueprint-states.json` is a Playground blueprint with a listing for every US state, DC and Canadian province, entered the way people type them (full names, lower and upper case codes, some with no city):

https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/tietjinator/wpjobs-style/main/blueprint-states.json

For automated checks you need a WordPress site that has WP Job Manager and this plugin active, such as one from Local, wp-env or a similar tool. The listings are titled "Listing 01", "Listing 02" and so on, and avoid state names and codes, so a search can only match through the saved location.

```bash
wp eval-file tests/seed-states.php
php tests/state-filter.php https://your-site.example
php tests/search-matrix.php https://your-site.example
php tests/search-matrix.php https://your-site.example keywords
```

- `tests/seed-states.php` creates the 64 listings.
- `tests/state-filter.php` checks the State / Province dropdown: every state alone, combined with other filters, and edge cases.
- `tests/search-matrix.php` runs about 600 searches (code, lower-case code, full name in three cases, city, ZIP, "City, ST", "City, Full name", "City ST", "City Full name", and a name followed by an unrelated term). It reports whether the right listing comes back and what else matches with it. Add `keywords` to type the searches into the Keywords box instead of the old Location box.

The plugin caches search results. If a result looks stale after a code change, save or publish any listing to clear the cache.

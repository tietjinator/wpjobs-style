# WPJM Custom Styles

A stylesheet for [WP Job Manager](https://github.com/Automattic/WP-Job-Manager) with its own tiny loader plugin. Written against plugin version 2.4.9.

## Install

1. Zip this folder, or copy it to `wp-content/plugins/wpjm-custom-styles/`.
2. Activate **WPJM Custom Styles** in the WordPress admin.

If you would rather not run a plugin, copy `css/wpjm-custom.css` into your child theme and enqueue it from `functions.php` at priority 100 or higher.

## Customising

Start with the tokens at the top of `css/wpjm-custom.css` (colours, radius, spacing, job type colours). The file is split into numbered sections, one per area: notices, search filters, listings, single page, submit form, dashboard, pagination, widgets. Put site-specific rules in section 12.

Selectors are prefixed with `body` to beat the plugin's own specificity without `!important`. If a rule does not apply, check specificity in your browser's dev tools before reaching for `!important`.

The plugin also exposes `--jm-ui-*` CSS variables for the dashboard and newer UI components. The two most useful are already mapped to the tokens, and the full list is in the plugin's `assets/css/ui.neutral.scss`.

## Notes

- Job type colours depend on the type slug (`full-time`, `part-time`, ...). Add a rule in section 5 for each type you create.
- The plugin only loads its CSS on pages it detects as job pages. If you use the shortcodes in unusual places, see the `job_manager_enqueue_frontend_style` filter.
- Plugin markup changes between major versions. After upgrading WP Job Manager, re-check the listings, single page and dashboard.

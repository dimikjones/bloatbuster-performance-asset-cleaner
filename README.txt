=== BloatBuster – Performance & Asset Cleaner ===
Contributors: dimikjones
Tags: performance, optimization, speed, cleanup, database
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Strip away WordPress bloat, cut HTTP requests and tune background tasks for a faster, lighter site.

== Description ==

Streamline your site's performance instantly. BloatBuster strips away unwanted WordPress bloat, reduces HTTP requests, and optimizes background API execution - all without breaking your site. Toggle the features you need to keep your frontend fast, light, and clean.

Every option is off by default, so nothing changes until you turn it on.

= Assets =

* Disable Gutenberg block styles on the frontend
* Disable classic theme styles on the frontend
* Disable global styles on the frontend (classic themes only)
* Disable Dashicons on the frontend when the toolbar is hidden
* Disable jQuery Migrate on the frontend
* Disable emoji scripts and styles

= WordPress Core =

* Disable oEmbed discovery and embed script (embeds from YouTube and other services keep working)
* Disable comments site-wide
* Disable self-pingbacks
* Limit post revisions to a set number
* Remove unnecessary head tags
* Disable XML-RPC
* Disable core XML sitemaps
* Disable user enumeration

= Database =

* Database statistics: size, largest tables, revisions, auto-drafts, trash and spam counts
* Clean expired transients
* Delete old revisions, keeping the newest ones per post
* Delete old auto-drafts

= Autoloaded Options =

* Summary of autoloaded options: total size, count, largest option and options to review
* Browse, search, filter and sort options, with the likely plugin or theme owner
* Transients and their timeout rows are hidden by default; use "Include transients" to show them
* Autoload summary totals include hidden transients
* Enable or disable autoload, one by one or in bulk
* Delete options left behind by removed plugins
* View option values, with secrets like API keys hidden until you reveal them
* Export options to a file and restore them from that file
* Core WordPress options are protected from changes

= Background Processes =

* WP-Cron status: overdue events and the next scheduled events
* Disable the Heartbeat API on the frontend
* Control the Heartbeat API interval for the frontend, dashboard and post editor
* Control the post editor autosave interval

= Media =

* Disable image sizes you don't need, for new uploads
* Control large image scaling (the 2560 pixel limit)

= Advanced =

* Control speculative loading mode and eagerness
* Disable capital_P_dangit
* Delete plugin data on uninstall

== Installation ==

1. In your WordPress admin, go to Plugins → Add New Plugin and search for "BloatBuster". You can also upload the plugin zip file with Upload Plugin.
2. Install and activate the plugin.
3. Open BloatBuster in the admin menu.
4. Turn on the options you need, one tab at a time, and check your site after each change.

== Frequently Asked Questions ==

= Will it break my site? =

Every option is off by default. Turn options on one by one and check your site after each change. Each option explains what it removes. Some need care: only disable block styles if your theme does not use blocks, don't disable XML-RPC if you use Jetpack or the WordPress mobile app, and keep the Heartbeat API on the frontend if a plugin like live chat or a membership plugin needs it.

= I use a block theme. Can I disable global styles? =

No. A block theme's design comes from global styles, so the option is locked when a block theme is active. It is meant for classic themes that don't use blocks.

= How do I show transients in Autoloaded Options? =

Check "Include transients" and click "Apply filters". This works with or without a search term and applies across views, sorting and pagination. Transient values and timeout rows are hidden by default, but autoloaded transients are always included in the summary cards. Showing or hiding them does not delete or change any options. Autoload cannot be enabled for ordinary transients with timeout rows, because WordPress would skip their expiration checks. They can still be viewed, exported, deleted or have autoload disabled. Imports also skip changes that would bypass transient expiration.

= Can I undo changes in Autoloaded Options? =

Changing autoload only changes when WordPress loads an option, the value is kept, and you can turn it back. Deleting an option can't be undone, so use the Export bulk action first. You can restore exported options later with "Restore from backup". Core WordPress options are protected and can't be changed or deleted.

= Does it work on multisite? =

Yes. Each site has its own settings. In the Autoloaded Options tab, only network administrators can change, delete or restore options, site administrators can browse, view and export them.

= What is the WP-Cron status for? =

WP-Cron runs scheduled tasks like publishing scheduled posts and update checks. The status shows overdue tasks, which means WP-Cron is not running. On busy sites you can run WP-Cron from a server cron job instead of on page visits, the status links to a guide.

= Does it replace a caching plugin? =

No. BloatBuster removes what WordPress loads but your site may not need. It works next to caching plugins.

== Changelog ==

= 1.0.0 - 2026-10-22 =
* Initial release.

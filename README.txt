=== BloatBuster – Performance & Asset Cleaner ===
Contributors: (this should be a list of wordpress.org userid's)
Donate link: http://example.com/
Tags: speed, optimization
Requires at least: 6.6
Tested up to: 6.8
Stable tag: 5.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Strip away WordPress bloat, cut HTTP requests and tune background API execution for a faster, lighter frontend.

== Description ==

Streamline your site's performance instantly. BloatBuster strips away unwanted WordPress bloat, reduces HTTP requests, and optimizes background API execution - all without breaking your site. Toggle the features below to keep your frontend fast, light, and clean.

= Features =

* Disable Gutenberg block styles on the frontend
* Disable classic theme styles on the frontend
* Disable global styles on the frontend (classic themes only)
* Disable the Heartbeat API on the frontend
* Control Heartbeat API interval for the frontend, dashboard and post editor
* Disable Dashicons on the frontend when the toolbar is hidden
* Disable jQuery Migrate on the frontend
* Disable emoji scripts and styles
* Disable oEmbed discovery and embed script (external embeds keep working)
* Disable self-pingbacks
* Limit post revisions to a configurable number
* Disable capital_P_dangit
* Remove unnecessary head tags
* Disable XML-RPC
* WP-Cron status checker with overdue events and next scheduled events
* Control the post editor autosave interval
* Disable user enumeration
* Control speculative loading mode and eagerness
* Manually clean expired transients
* Delete old revisions, keeping the newest ones per post
* Delete old auto-drafts
* Database statistics: size, overhead, largest tables, revisions, auto-drafts, trash and spam counts
* Autoloaded options manager: browse, search, filter and sort options, see the likely owner, change autoload, delete, view values, export and restore
* Disable comments site-wide

== Installation ==

This section describes how to install the plugin and get it working.

e.g.

1. Upload `bloatbuster-performance-asset-cleaner.php` to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Place `<?php do_action('plugin_name_hook'); ?>` in your templates

== Frequently Asked Questions ==

= A question that someone might have =

An answer to that question.

= What about foo bar? =

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 1.0 =
* A change since the previous version.
* Another change.

= 0.5 =
* List versions from most recent at top to oldest at bottom.

== Upgrade Notice ==

= 1.0 =
Upgrade notices describe the reason a user should upgrade.  No more than 300 characters.

= 0.5 =
This version fixes a security related bug.  Upgrade immediately.

== Arbitrary section ==

You may provide arbitrary sections, in the same format as the ones above.  This may be of use for extremely complicated
plugins where more information needs to be conveyed that doesn't fit into the categories of "description" or
"installation."  Arbitrary sections will be shown below the built-in sections outlined above.

== A brief Markdown Example ==

Ordered list:

1. Some feature
1. Another feature
1. Something else about the plugin

Unordered list:

* something
* something else
* third thing

Here's a link to [WordPress](http://wordpress.org/ "Your favorite software") and one to [Markdown's Syntax Documentation][markdown syntax].
Titles are optional, naturally.

[markdown syntax]: http://daringfireball.net/projects/markdown/syntax
            "Markdown is what the parser uses to process much of the readme file"

Markdown uses email style notation for blockquotes and I've been told:
> Asterisks for *emphasis*. Double it up  for **strong**.

`<?php code(); // goes in backticks ?>`

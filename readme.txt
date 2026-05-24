=== Code Block Enhancer ===
Contributors: coywolf
Tags: code, syntax highlighting, prism, copy code, gutenberg
Requires at least: 6.3
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhances the WordPress core Code block with a language selector, Prism.js syntax highlighting (custom token palette), and a copy-to-clipboard button.

== Description ==

Code Block Enhancer extends the built-in `core/code` block. It adds a
language picker in the block sidebar, highlights code on the front end with
Prism.js using a custom colour palette, and renders a copy-to-clipboard
button on each block.

* Adds a "Code language" dropdown to the core Code block's sidebar.
* Highlights code on the front end with Prism.js, using a custom colour palette.
* Adds a copy-to-clipboard button with an accessible status message.
* Assets load only on singular posts/pages that contain a code block.
* Front-end scripts use the `defer` strategy so they don't block rendering.
* In-WordPress updates: new versions are pulled from this project's GitHub
  Releases through the standard Dashboard → Updates flow.

== How it works ==

The chosen language is stored as a block attribute (in the block delimiter
comment), so existing code blocks are never invalidated. On render, the
language is applied to the markup server-side via WP_HTML_Tag_Processor
(`data-language` on `<pre>`, `language-xxx` on `<code>`).

Prism core and the language grammars are loaded from cdnjs (an external
CDN). If your site enforces a Content Security Policy, allow
`cdnjs.cloudflare.com` for scripts.

== Installation ==

1. Upload the `code-block-enhancer` folder to `/wp-content/plugins/`, or
   upload the .zip via Plugins → Add New → Upload Plugin.
2. Activate the plugin through the Plugins screen in WP Admin.
3. Edit a post or page, add (or open) a Code block, and pick a language
   from the block sidebar. The code is highlighted on the front end and a
   "Copy" button appears in the top-right of the block.

== Frequently Asked Questions ==

= Which languages are supported out of the box? =

PHP, JavaScript, CSS, HTML/markup, Bash, JSON, Python, SQL, and YAML.

= How do I add another language? =

1. Add the Prism grammar to the `$chain` array in `code-block-enhancer.php`
   (mind dependency order — e.g. `markup-templating` must load before
   `php`).
2. Add a matching entry to the `LANGUAGES` list in `js/code-language.js`
   so it appears in the editor dropdown.

= Will this break my existing code blocks? =

No. The language is stored as a block attribute rather than baked into the
saved markup, so blocks without a language remain valid and existing
content is not migrated.

= Where do plugin updates come from? =

Releases are published to this plugin's GitHub repository. The plugin
checks GitHub Releases (cached for 6 hours) and offers any newer version
through the standard WordPress Dashboard → Updates / "Update Now" flow.

== Changelog ==

= 1.0.1 =
* Add GitHub updater, uninstall cleanup, expanded readme (#2).

= 1.0.0 =
* Initial release.

=== Code Block Enhancer ===
Requires at least: 6.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhances the WordPress core Code block with a language selector, Prism.js syntax
highlighting (custom token palette), and a copy-to-clipboard button.

== Description ==

* Adds a "Code language" dropdown to the core Code block's sidebar.
* Highlights code on the front end with Prism.js, using a custom colour palette.
* Adds a copy-to-clipboard button with an accessible status message.
* Assets load only on singular posts/pages that contain a code block.
* Front-end scripts use the `defer` strategy so they don't block rendering.

== How it works ==

* The chosen language is stored as a block attribute (in the block delimiter
  comment), so existing code blocks are never invalidated.
* On render, the language is applied to the markup server-side via
  WP_HTML_Tag_Processor (`data-language` on <pre>, `language-xxx` on <code>).
* Prism core and the language grammars are loaded from cdnjs (an external CDN).
  If your site enforces a Content Security Policy, allow `cdnjs.cloudflare.com`.

== Adding more languages ==

1. Add the grammar to the `$chain` array in `code-block-enhancer.php`
   (mind dependency order — e.g. `markup-templating` before `php`).
2. Add a matching entry to the `LANGUAGES` list in `js/code-language.js`.

== Changelog ==

= 1.0.0 =
* Initial release.

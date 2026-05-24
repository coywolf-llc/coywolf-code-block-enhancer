<img src=".wordpress-org/icon-256x256.png" alt="Coywolf Code Block Enhancer logo" width="128" />

# Coywolf Code Block Enhancer

Adds syntax highlighting and a copy-to-clipboard button to the native WordPress Code block, plus a language picker in the editor sidebar. Assets load only on posts that actually contain a code block.

- **Version:** 1.0.11
- **Requires WordPress:** 6.3 or later
- **Tested up to:** 6.7
- **Requires PHP:** 7.4 or later
- **License:** [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)

## Description

Coywolf Code Block Enhancer extends the built-in `core/code` block. In the editor it adds a "Code language" dropdown to the block sidebar; on the front end it highlights the code with Prism.js using a custom token palette, prints the language name as a small label on the block, and pins a copy-to-clipboard button to the top-right corner.

- Adds a "Code language" dropdown to the core Code block's sidebar (Bash/Shell, CSS, HTML/Markup, JavaScript, JSON, PHP, Python, SQL, YAML, plus "None" for plain text).
- Highlights code on the front end with Prism.js. Pick from **45 bundled themes** — the 8 stock Prism themes (Default, Coy, Dark, Funky, Okaidia, Solarized Light, Tomorrow Night, Twilight) plus 37 community themes from [PrismJS/prism-themes](https://github.com/PrismJS/prism-themes) (a11y Dark, Atom Dark, Dracula, Nord, One Dark, Night Owl, Synthwave '84, Gruvbox, Material, VS Code Dark+, and more) — or the default **Claude** palette.
- Adds a small language label in the top-left of each highlighted block (only when a language is set).
- Adds an accessible copy-to-clipboard button — `aria-label`, a polite status region that announces "Copied to clipboard," and a visible "✓" state for two seconds after a successful copy. Falls back to `document.execCommand('copy')` on non-HTTPS or older browsers.
- Assets load only on singular posts/pages that contain a code block; Prism core and grammars are loaded with the `defer` strategy so they never block rendering.
- **Dark-mode aware** out of the box — with the default Claude theme, code blocks follow the visitor's `prefers-color-scheme` automatically. Override the behaviour from **Tools → Code Blocks** by switching to **Claude — Always light** / **Always dark**, or by picking any of the static Prism themes.
- In-WordPress updates: new versions are pulled from this project's GitHub Releases through the standard **Dashboard → Updates** flow (latest release cached for 6 hours). Downloads are pinned to a GitHub host allowlist as a safety check.

### How it works

The chosen language is stored as a `language` block attribute on `core/code`, which lives in the block delimiter comment rather than the saved markup. That means blocks without a language stay valid and existing content is never migrated.

On render, the plugin uses `WP_HTML_Tag_Processor` to add `data-language` to the `<pre>` and `language-xxx` to the `<code>` server-side — so KSES won't strip `data-*` attributes for non-admin authors, and there is no block-validation churn.

Prism core and the per-language grammars are bundled under `assets/prism/` at v1.30.0 (MIT — see `assets/prism/LICENSE`). They register as deferred scripts with explicit dependency ordering (e.g. `markup-templating` before `php`, `clike` before languages that extend it). The copy-button script depends on the last grammar in the chain, so all of Prism is present before the copy UI is wired up. Reading `code.textContent` returns the original source even after Prism wraps tokens in spans, so the copied text is unaffected by highlighting.

Self-hosting Prism (rather than loading from a public CDN) keeps the third-party-script supply chain off the plugin's surface and means the plugin works on sites with strict CSPs or no external egress.

## Installation

1. Upload the `code-block-enhancer` folder to `/wp-content/plugins/`, or upload the .zip via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin.
3. Edit a post or page, add (or open) a Code block, and pick a language from the "Code language" panel in the block sidebar. The code is highlighted on the front end and a copy button appears in the top-right of the block.

## Frequently Asked Questions

### Which languages are supported out of the box?

Bash/Shell, CSS, HTML/Markup, JavaScript, JSON, PHP, Python, SQL, and YAML. The dropdown also includes "None (plain text)" to render a block without highlighting.

### How do I add another language?

Two places need to stay in sync:

1. Add the Prism grammar to the `$chain` array in `code-block-enhancer.php`, minding dependency order (e.g. `markup-templating` must load before `php`; languages that extend `clike` need `clike` registered first).
2. Add a matching entry — `{ label, value }` — to the `LANGUAGES` list in `js/code-language.js` so it appears in the editor dropdown.

### Will this break my existing code blocks?

No. The language is stored as a block attribute, not baked into the saved markup, so blocks without a language stay valid and the front-end language class is applied at render time. Existing content is not migrated and is unaffected.

### Does Prism load on every page?

No. The token CSS only enqueues on singular posts/pages where `has_block( 'core/code' )` is true, and the Prism scripts are only enqueued from inside the `render_block` filter for `core/code` — so a page with no code block ships none of these assets. Prism is also loaded with the `defer` strategy so it never blocks rendering.

### My site has a Content Security Policy. What do I need to allow?

Nothing extra. Prism and the copy-button script are bundled with the plugin and served from your own origin, so a `script-src 'self'` policy is enough. There is no external CDN call from the front end.

### How do I change the theme?

Go to **Tools → Code Blocks** in WP Admin. The **Code block theme** dropdown lists every bundled theme in three groups: **Coywolf** (Auto / Always light / Always dark), **Prism (built-in)**, and **Prism Themes (community)**. The selected theme's stylesheet is enqueued only on posts that contain a code block; only one theme file is ever loaded per request.

### How do I lock code blocks to light or dark mode for everyone?

If you're on the default **Claude — Auto** theme, switch to **Claude — Always light** or **Always dark** in **Tools → Code Blocks**. Picking any of the Prism themes also locks the appearance — those themes are static and don't react to OS dark mode. The lock is implemented in CSS — there is no inline `<style>` injected per request — so it composes cleanly with caching plugins.

### Where do the Prism themes come from?

The 8 stock themes are bundled from [PrismJS/prism](https://github.com/PrismJS/prism) at v1.30.0 (MIT — see `assets/themes/LICENSE-prism`). The 37 community themes are bundled from [PrismJS/prism-themes](https://github.com/PrismJS/prism-themes) (MIT — see `assets/themes/LICENSE-prism-themes`). They are served from your own origin alongside the rest of the plugin's assets — no external request at runtime.

### Why is the language label not appearing on a particular block?

The label only renders when a language is set (the CSS rule is `.wp-block-code[data-language]::before`). If the block was created before the plugin was installed, open it in the editor and pick a language from the sidebar so the `language` attribute is saved.

### Where do plugin updates come from?

Releases are published to this plugin's [GitHub repository](https://github.com/coywolf-llc/coywolf-code-block-enhancer). The plugin checks GitHub Releases (cached for 6 hours) and offers any newer version through the standard WordPress **Dashboard → Updates** / "Update Now" flow. Downloads are restricted to a GitHub host allowlist as a safety check.

## Changelog

### 1.0.11
- Bundle all 45 Prism themes; theme picker on settings page (#12).

### 1.0.10
- Auto dark mode + Tools settings page for theme override (#11).

### 1.0.9
- No-op release to verify the plugin icon shows on the Updates row once the site is running an icon-aware updater (1.0.8+).

### 1.0.8
- Show plugin icon on Updates / Plugins / View-details (#9).

### 1.0.7
- Self-host Prism and allowlist the language attribute (security) (#8).

### 1.0.6
- Add coywolf logo to .wordpress-org/ and readme.md (#7).

### 1.0.5
- Mirror readme.md as a peer to readme.txt; dual-bump in release workflow (#6).

### 1.0.4
- Rename plugin title to 'Coywolf Code Block Enhancer' (#5).

### 1.0.3
- Mirror readme.txt into README.md for GitHub rendering (#4).

### 1.0.2
- Rewrite readme.txt in coywolf-link-checker format (#3).

### 1.0.1
- Add GitHub updater, uninstall cleanup, expanded readme (#2).

### 1.0.0
- Initial release.

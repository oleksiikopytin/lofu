# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## 🗂️ Repository Scope

- Project: **LOFU** (site title `LOFU`, table prefix `wp_`). Machine-specific details (local URL, paths, database access) live in the gitignored `CLAUDE.local.md`. Create one per machine.
- This is a **full WordPress installation** (`wp-admin/`, `wp-includes/`, core files, plus third-party plugins in `wp-content/plugins/`: ACF Pro, Classic Editor, Yoast SEO, Wordfence, WPS Hide Login, Post Types Order, SVG Support, Disable Comments, Disable XML-RPC API, Cyr2Lat, Auto Image Attributes, and the WPML stack: WPML Multilingual CMS, String Translation, Media Translation, ACFML and Yoast SEO Multilingual). None of that is custom. Treat it as vendored, read-only reference material, not "the codebase."
- The **only** custom code lives at:
  ```
  wp-content/themes/lofu-theme/
  ```
  This is the **active** theme (confirmed via `wp_options.template`/`stylesheet` = `lofu-theme`) and the only theme on disk.
- There is **no custom plugin**. Every plugin under `wp-content/plugins/` is third-party.
- If a task says "the theme" without qualification, it means `lofu-theme`.
- Theme author / owner: **okopytin** (`style.css` Author, `readme.txt` Contributors).

### Git

- The repo root is the WordPress site root, branch `main`, remote `origin` = `git@github.com:oleksiikopytin/lofu.git`.
- **Tracked:** `wp-content/themes/lofu-theme/` (including its `lib/vendor/` and the compiled `assets/css/**`), `wp-content/plugins/` (third-party plugins are committed), `.gitignore`, `README.md`.
- **Also tracked at the root:** `CLAUDE.md`, `composer.json`, `composer.lock`, `phpcs.xml.dist`.
- **Ignored:** WordPress core (`/wp-admin/`, `/wp-includes/`, root core files), `wp-config.php`, `.htaccess`, `/wp-content/uploads/`, `/wp-content/languages/`, `.cursor`, `/vendor/` (rebuild with `composer install`), and `/CLAUDE.local.md`.
- After a fresh clone: install WordPress core alongside the repo, create `wp-config.php`, import the database, run `composer install`, and enable the compiler under wp-admin → SCSS Options.
- `lofu-theme/lib/vendor/` (scssphp) is **intentionally tracked**, so the runtime SCSS compiler works right after a clone without running `composer install` in `lib/`.
- Compiled CSS is committed. When you change SCSS, load a page so the runtime compiler rebuilds `assets/css/**`, and commit the SCSS together with the regenerated CSS/maps.
- Commit or push only when asked.

### Naming

- Theme Name **LOFU** (`style.css`, `readme.txt`), text domain **`'lofu'`** (`load_theme_textdomain( 'lofu' )`), `@subpackage lofu` in file headers.
- Prefix all theme functions, global variables and hook callbacks with **`lofu_`**; image size `lofu-featured-image`.
- Exception: the SCSS compiler (`lib/scss-compiler.php`) uses the `iatheme_` prefix and the `iatheme_options` option key. Keep it, since renaming the key would drop the saved SCSS settings.
- The theme was cloned from an earlier "Family Security Plan" theme (`fsp_` / `family-security-plan`); those names were fully replaced on 2026-11-22. Don't reintroduce them.

## ⚙️ Commands & Build

- No JS build/test tooling: no `package.json`, no `phpunit.xml`, no `wp-cli.yml`, no bundler. `wp-cli` is not on `PATH`.
- Edits are made directly to theme PHP/JS/SCSS files, served by a local web server. The theme requires PHP 7.4+ (`style.css`); PHP and Composer must be on `PATH` for linting.

### SCSS compilation

**SCSS is compiled server-side at runtime** on page load, not via a JS build step:

- Source: `assets/scss/**/*.scss` (entry point `main.scss`). Output: `assets/css/main.css`.
- Logic: `lib/scss-compiler.php`, using `scssphp/scssphp` (`^2.1`, see `lib/composer.json`) installed at `lib/vendor/`. `iatheme_compile_main_scss_to_css()` runs on the `wp` hook when enabled on the "SCSS Options" admin page (menu slug `mytheme-options`, option `iatheme_options` with `scss_toggle`, `scss_compilation_type` = `EXPANDED`/`COMPRESSED`, `scss_recompile_all`).
- Currently **enabled**, `EXPANDED`, with "Recompile all" on.
- `main.css` is rebuilt when `main.scss` or any file it imports is newer than `main.css`. With "Recompile all" on, every non-partial `.scss` under `assets/scss/` is also compiled to the matching `assets/css/**` path (with a `.map`) whenever its source is newer. **Any frontend page load triggers this**, including a `curl` used for testing.
- Compile errors → `assets/css/scss-error-log.txt` (cleared on each run), shown in `wp_head` for logged-in admins only when `WP_DEBUG` is on. ⚠️ On error the old `main.css` stays in place silently, so check the log after SCSS edits.
- If `lib/vendor/` is lost, restore it with `composer install` in `lib/`.
- `main.scss`'s `@import` list mixes live and commented-out imports, several tagged `NEED CHECK DESIGN INNER PAGES` or `NEED TO REMOVE`. Treat those as the author's own TODO markers, not dead code to silently delete.

## 🧹 Linting

- `phpcs.xml.dist` (site root, "LOFU Theme Coding Standards") lints PHP in `wp-content/themes/lofu-theme` only, excluding `lib/vendor/`.
- Built on `WordPress-Extra`, with:
  - `WordPress.Files.FileName` disabled: the theme's flat layout doesn't follow WP's class-to-filename convention.
  - `PrefixAllGlobals` accepting `lofu` (plus `iatheme` for the SCSS compiler).
  - `I18n` requiring text domain `lofu`.
  - Minimum supported WP version `5.9`.
- Tools come from the root `composer.json` / `composer.lock` (PHPCS 3.13, WPCS 3.4) into root `vendor/`. Restore with `composer install`.
- Run from the site root: `composer lint` or `php vendor/bin/phpcs [file]`; auto-fix with `composer lint:fix` or `php vendor/bin/phpcbf [file]`.
- Existing code still has ~70 errors / ~20 warnings, mostly formatting that `phpcbf` can fix. Lint the files you change. Don't mass-fix the whole theme as part of an unrelated task.
- The `.cursor/rules/*.mdc` files describe an aspirational OOP/PSR style the ruleset does **not** enforce. The actual theme code is flat, procedural and hook-based. Match that.

## 📐 Coding Standards (Team Guidelines)

The agreed going-forward standard for new work, distinct from the "what already exists" notes in Architecture below.

### 🏗️ Template & asset structure

```
template-parts/{page-name}/{section-name}.php
```
`{page-name}` carries a `-page` suffix (`home-page`, `about-page`, ...), e.g. `template-parts/home-page/hero.php`, **not** `template-parts/home/hero.php`.

```
assets/scss/pages/{page-name}/{section-name}.scss
```
e.g. `assets/scss/pages/home-page/hero.scss`. Reusable/shared components → `template-parts/components/` (doesn't exist yet; create it the first time a section is reused across pages, rather than duplicating markup).

**Global SCSS** (`assets/scss/global/`): reuse these rather than redefining colors/spacing/fonts locally:

| Purpose | Path |
|---|---|
| Variables (fonts, breakpoints, container width) | `global/utils/variables.scss` |
| Colors | `global/utils/colors.scss` |
| Spacing | `global/utils/spacing.scss` |
| Mixins | `global/mixins/mixin.scss`, `global/mixins/grid.scss` |
| Typography | `global/content/typography.scss` |
| Layout/grid | `global/layout/grid.scss` |
| Buttons | `global/components/button.scss` |
| Flex utilities | `global/utils/flex.scss` |

**Reuse existing utility classes instead of hand-rolling the same CSS in a component partial:**
- Give each page section's `<section>` the `.module` class (`global/layout/module.scss`): vertical padding `$module-spacing` / `$module-spacing-md` (8rem / 12rem from `$breakpoint-md`), the top divider line, white background, ink text and `scroll-margin-top`. Don't repeat those in the section partial. Trim it in markup with the spacing/border utilities (`module pt-0 border-0`, `module pb-0`); only a genuinely different value (e.g. contacts' larger bottom gap above the footer) belongs in the partial. The hero and modals don't use it.
- Section headings use the shared classes in `global/components/section-heading.scss`: `.section-title` (large, e.g. Projects/Contacts) or `.section-label` (small uppercase, e.g. About/Partners). Keep only margins in the partial. For a masked slide-up on scroll in a section without its own script, add `reveal` to the heading and wrap its text in `<span class="reveal__inner">` (driven by `assets/js/components/reveal.js`, enqueued for the home page; easing `$ease-out`).
- Wrap section content in `.ia-container` (from `global/layout/grid.scss`, via the `container` mixin) for the standard `$container-max-width` (`1440px`) + responsive gutter. Don't hardcode horizontal section padding in a component's own SCSS.
- Use the `flex.scss` utility classes (`d-flex`, `align-items-center`, `justify-content-*`, `flex-wrap`, `gap-*`) directly in markup for layout instead of writing `display: flex; ...` by hand in the partial. Keep only genuinely component-specific rules in the partial.
- Use the `bg--{name}` / `text--{name}` / `border--{name}` classes generated in `colors.scss` from `$theme-colors` instead of writing `background-color: $color;` in a component partial.
- ⚠️ These utility classes are declared `!important`. A responsive override for the same property inside a component partial must also use `!important` to win.

**Never hand-edit a generated `.css` file.** Every file in `assets/css/` except the vendor files (`animate.css`, `jquery.fancybox.min.css`) is compiler output.

### ⚡ JavaScript

```
assets/js/pages/{page-name}/{section-name}.js
```
e.g. `assets/js/pages/home-page/hero.js`.

- Wrap in an IIFE/module pattern, avoid polluting globals.
- Guard against a missing element: `if (!el) return;`. JS should enhance, not gate, core functionality.
- Enqueue section CSS/JS conditionally, from the section's template part or for the template that uses it, rather than adding it to the global `lofu_scripts()`.
- Prefer vanilla JS. jQuery, Slick and Fancybox are already loaded globally if needed.

### 🧩 PHP / ACF

- Match the theme's existing flat, procedural, hook-based style. Don't introduce namespaces or classes.
- One ACF Field Group per section, with descriptive field names (`section_title`, `section_description`, `cta_button`, etc.).
- Always escape output (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`) and internationalize user-facing strings with the `'lofu'` text domain.
- For repeaters/flexible content, use `have_rows()` / `the_row()` / `get_sub_field()`.

### ♿ Accessibility

Follow WCAG 2.1 AA as **best practice** for new/changed markup (not a hard contractual requirement on this project):

- Semantic HTML, proper heading hierarchy (no skipped levels); use the global typography classes (`.h1`–`.h6`, `global/content/typography.scss`) on a different tag when semantic structure requires it, e.g. `<h2 class="h1">`.
- Real `<button>` for actions, valid `href` on links, native elements over ARIA roles where possible.
- Visible focus states: don't remove `outline` without an accessible replacement.
- No positive `tabindex`.
- Respect `prefers-reduced-motion` for any animation.
- Labels connected to inputs via `for`/`id`; `alt` on images.

### 🔐 Security

- Never trust user input; validate and sanitize it.
- Escape all output (see PHP / ACF above).
- Use nonces on any form or AJAX action that changes state. ⚠️ `lib/ajax.php`'s `lofu_ajax_popup_dimension` scaffold has no nonce check. Add one when that handler stops being a stub.

### 🚀 Performance

- Load assets only where the section/template needs them.
- Avoid pulling in large libraries when a small one (or vanilla JS/CSS) will do.
- Lazy-load images where practical.

### 🧪 Code quality

- Keep functions small and readable; use meaningful variable names.
- Avoid inline styles/scripts.
- Comment only when the *why* isn't obvious from the code.

### 📦 Naming / breakpoints

- Files: `section-name.php` / `.scss` / `.js`. Classes: BEM (`.block`, `.block__element`, `.block--modifier`).
- Breakpoints, per `assets/scss/global/utils/variables.scss`. Use these variables rather than hardcoding new values:

| Variable | Value |
|---|---|
| `$breakpoint-lg` | 1200px |
| `$breakpoint-md` | 1024px |
| `$breakpoint-sm` | 768px |
| `$breakpoint-xs` | 480px |

### ✅ Definition of done (new section/layout work)

- [ ] Template part created at the correct `template-parts/{page-name}/{section-name}.php` path
- [ ] ACF field group added, one per section
- [ ] SCSS written at the matching `assets/scss/pages/{page-name}/{section-name}.scss` path, compiled without errors (`scss-error-log.txt` empty)
- [ ] JS added under `assets/js/pages/{page-name}/{section-name}.js`, if needed, guarded against a missing element
- [ ] Output escaped, strings internationalized with `'lofu'`
- [ ] Changed PHP files pass `php vendor/bin/phpcs <file>`
- [ ] Accessibility checked against the list above

## 🏛️ Architecture

The theme was stripped back to a minimal base on 2026-11-22 (the flexible-page builder, global-styles page, GSAP animation system, highlight.js and the old self-hosted fonts were removed; Montserrat was then added as the single theme font). Expect to build most templates from scratch.

### Multilingual (WPML)

The site is bilingual through WPML: **Ukrainian (`uk`) is the default language**, with English (`en-us`) as the second language. For all new work:

- Every user-facing string in theme PHP goes through `__()` / `esc_html__()` / `esc_html_e()` etc. with the `'lofu'` text domain, so WPML String Translation can pick it up. Never hardcode visible text.
- ACF content is translated through ACFML. When creating a field group, set each field's translation preference (usually *Translate* for text/WYSIWYG, *Copy* for layout settings, images and links that don't vary by language). Those settings are saved into the `acf-json/` file with the group.
- Link to other pages through WordPress functions (`get_permalink()`, `home_url()`, menus), not hardcoded URLs, so WPML can map them to the current language.
- Strings stored in ACF options pages need the options page itself to be set up for translation in WPML.

### Bootstrap

`functions.php`: `lofu_setup()` (on `after_setup_theme`) requires each `lib/` file via `locate_template()` (`post-types.php`, `config.php`, `scripts.php`, `shortcode.php`, `scss-compiler.php`, `ajax.php`), calls `load_theme_textdomain( 'lofu' )`, and adds `automatic-feed-links`, `title-tag`, `custom-logo` and `customize-selective-refresh-widgets` support. The rest is a flat tail: a pingback header (`lofu_pingback_header`), an ACF options page (`acf_add_options_page()` with no args), emoji-disabling (`lofu_disable_emojis*`), a single `sidebar-1` widget area (`lofu_widgets_init`), self-pingback prevention (`lofu_no_self_ping`), an oEmbed `frameborder` stripper (`lofu_remove_frameborder`), an unused search-highlight helper `lofu_highlight_words()`, and `add_filter( 'auto_update_plugin', '__return_false' )`.

### `lib/` responsibilities

| File | Contains |
|---|---|
| `post-types.php` | Empty. No `register_post_type()` anywhere in the theme. |
| `config.php` | Registers a single `main` nav menu location, `post-thumbnails` support, image size `lofu-featured-image` (1800×9999, no crop), and `html5` support (comment-form, comment-list, gallery, caption). |
| `scripts.php` | `lofu_scripts()` enqueues globally: `animate.css`, `jquery.fancybox.min.css`, `main.css`, `slick.min.js`, `jquery.fancybox.min.js`, `scripts.js`. Also prints the `no-js`→`js` snippet (`lofu_javascript_detection`), dequeues `jquery-ui-core` (`lofu_dequeue_jquery_ui`) and the `wp-block-library` CSS (`lofu_remove_block_library_css`). |
| `shortcode.php` | Empty stub. |
| `ajax.php` | `wp_ajax_popup_dimension` / `wp_ajax_nopriv_popup_dimension` hooks → `lofu_ajax_popup_dimension()`, which is just `wp_die()` (scaffold). |
| `scss-compiler.php` | The runtime SCSS pipeline (see Commands above). |
| `composer.json` / `composer.lock` / `vendor/` | scssphp for the compiler. |

### Templates

- Generic top-level templates: `index.php`, `page.php`, `single.php`, `archive.php`, `category.php`, `tag.php`, `search.php`, `404.php`, `comments.php`, `sidebar.php`, `header.php`, `footer.php`. No `Walker_Nav_Menu` subclass, and `header.php` doesn't call `wp_nav_menu()` yet.
- `page-templates/front-page.php` (Template Name "Home page"): outputs `the_content()`, then loops the ACF `hero` rows into `template-parts/home-page/hero.php`. That partial, `assets/js/pages/home-page/hero.js` and `assets/scss/pages/home-page/hero.scss` are currently empty placeholders being rebuilt.
- `category.php` and `single.php` contain commented-out enqueues for files that don't exist (`pages/knowledge-center/*`, `pages/post/hero.css`). They're leftovers from the source theme.

### ACF fields

**No ACF field groups yet.** The `hero` field referenced by `front-page.php` still needs to be created.

`lofu-theme/acf-json/` exists (kept in git via `.gitkeep`). ACF Pro auto-detects it, so any field group saved in wp-admin is written there as a Local JSON file. Commit those JSON files with the code. On another machine, sync changed groups via wp-admin → ACF → Field Groups → "Sync available".

### Custom post types & taxonomies

None. The database only has `page`, `post`, `revision` and `wp_navigation` posts.

### Assets

Hand-written, no bundler.

- `assets/js/`: vendor `slick.min.js`, `jquery.fancybox.min.js`; theme `scripts.js` (global, enqueued with a `jquery` dependency, currently empty), `components/accordion.js` (not enqueued; pairs with `global/components/accordion.scss`, which `main.scss` has commented out), `pages/home-page/hero.js`.
- `assets/css/`: vendor `animate.css`, `jquery.fancybox.min.css`; everything else is compiler output.
- `assets/scss/`: `global/` (base, components, content, layout, mixins, utils, `slick.scss`), `header/`, `footer/`, `pages/` (`home-page/`, plus `animation-page/` and `global-page/` sources left from removed templates, not imported by `main.scss`). Several `global/components/*.scss` (icons, pagination, breadcrumbs, close, tabs, accordion, start-quiz, tooltip, vimeo, hero-zoom, bg-move, divider-line) exist but aren't imported.
- `assets/images/`: `logo.svg`, `placeholder.png`.
- `assets/fonts/montserrat/`: **Montserrat**, self-hosted as a variable font (weights 100–900, normal and italic, `latin`, `latin-ext`, `cyrillic` and `cyrillic-ext` subsets, `.woff2`, from `@fontsource-variable/montserrat` 5.1.1). The `@font-face` rules are generated in `global/base/font.scss` (imported by `main.scss`) with `unicode-range` per subset and `font-display: swap`. `variables.scss` sets `$font-base` and `$font-base-heading` to Montserrat; `$font-deco` is a system monospace stack. No Google Fonts or other external font requests. To use another weight, just set `font-weight`, since the variable font covers the whole range.

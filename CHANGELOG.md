# Changelog — BlueFairy_RecipesHyva

## 1.4.0 — 2026-11-27

**This package is now free and open source under the MIT licence, and lives in its own public
repository**, `github.com/bluefairydevelopment/module-recipes-hyva`, published on packagist.org.
It was a package inside the paid `module-recipes` monorepo and declared `proprietary`.

Nothing about the templates or their behaviour changes. What changes is how you get it and what
you may do with it:

- `composer require bluefairy/module-recipes-hyva` now works with no repository configuration and
  no credentials, because the package is on public Packagist.
- The core module it extends, `bluefairy/module-recipes` (`^1.3`), is still a paid extension and is
  installed from wherever you bought it - your Private Packagist repository, or the Adobe Commerce
  Marketplace with your Marketplace access keys. Adobe's repository carries no `hyva-themes/*`
  packages, which is why a Hyvä companion cannot be listed there and is distributed this way
  instead.
- The README and FEATURES.md no longer say this package is licensed together with the core module,
  because it is not. No licence key or purchase covers it; the MIT licence does.
- `LICENSE.txt` is the MIT licence. It was the paid end-user licence agreement.

A minor version rather than a patch, because the licence and the distribution channel both change
even though no code does. Existing installations resolve 1.4.0 from packagist.org on their next
`composer update` without any configuration change, since the package name is unchanged.

## 1.3.2 — 2026-11-22

- The Tailwind content configuration follows the Magento JavaScript coding standard and is formatted consistently. No change to the templates' behaviour.
- The development test configuration is now `phpunit.xml.dist` (was `phpunit.xml`). It is still not shipped to merchants.

## 1.3.1 — 2026-10-15

Released alongside `bluefairy/module-recipes` 1.3.1; one tag releases both. No change to the templates' behaviour.

## 1.3.0 — 2026-10-14

Released alongside `bluefairy/module-recipes` 1.3.0; one tag releases both. Minor release that raises the minimum Magento: this version requires Magento Open Source 2.4.8 or 2.4.9 and no longer installs on 2.4.7 or earlier. No template or behaviour change.

### Changed
- `bluefairy/module-recipes` is required at `^1.3` (it was `^1.0`): this package is released together with the 1.3 core package.
- `magento/framework` is required at `~103.0.8` (it was `^103.0`). The README and product description name the supported releases.

### Requirements
- Requires Magento Open Source 2.4.8 or 2.4.9 (`magento/framework ~103.0.8`); Magento 2.4.7 and earlier are no longer supported by this release; it no longer installs on them. Run `composer update` after upgrading.
- PHP 8.3, 8.4 or 8.5 (unchanged).

### Licence
The independent-contractor clause of the licence is reworded to "Nothing in this Agreement creates a joint venture, agency, franchise or employment relationship". The meaning is unchanged.

## 1.2.1 — 2026-09-25

- No changes to this package. Released alongside `bluefairy/module-recipes` 1.2.1 because one
  tag releases both; see that package's changelog.

## 1.2.0 — 2026-09-16

### Fixed
- `TemplateResolver` ran on **every block on every frontend request, in every
  theme**. It is declared on `Magento\Framework\View\Element\Template`, and it
  swapped all fourteen recipe templates with no theme check at all — Alpine
  markup handed to a theme with no Alpine. Now gated on the theme read at
  render time, which is what a layout argument cannot do without 500ing the
  page. The cheap template-prefix test stays first: this plugin is called
  hundreds of times per request.

### Added
- Tailwind registration: `view/frontend/tailwind/module.css` with explicit
  `@source` globs, and an observer on `hyva_config_generate_before` so this
  module reaches `app/etc/hyva-themes.json` and therefore the compiled CSS.
  A test guards the reason no safelist is needed — Tailwind's scanner is a
  plain-text scan, and every class token here is a **literal** in the file it
  scans. An edit that builds a class name from a variable now fails the suite
  rather than vanishing silently from the compiled CSS.

### Removed
- Four empty, deprecated layout files (`blue_recipe_index_index.xml`,
  `blue_recipe_index_view.xml`, `blue_recipe_index_writer.xml`,
  `blue_recipes_override.xml`) — zero block or container declarations between
  them, rendering nothing on any theme.

### Internal
- A coding-standard gate (`phpcs.xml.dist`), which this package did not have.
  It reads `.phtml` as well as `.php`, so the 14 templates here are examined
  by something for the first time. Exits 0.
- The unit suite now tests this repository rather than the copy installed in
  the Magento install's `vendor/`, and carries its own static-analysis bootstrap.
  Static analysis is at zero.

### Security & pre-release hardening
- The recipe-submission photo field accepts WebP again in the Hyvä submit
  template (the server accepts it; a prior change removed it from the picker
  only).
- The writer profile and listing blocks now emit cache identities, so a writer
  edit purges their full-page-cache entries instead of serving stale.
- `FEATURES.md` now lists all fourteen template overrides (was twelve), pinned to
  the template map by a test.

## 1.1.3 — 2026-09-05

### Changed
- PHP constraint widened to `~8.3.0||~8.4.0||~8.5.0`, the range Magento 2.4.9
  itself declares. Magento 2.4.9 moved to PHP 8.5 and the previous constraint
  meant composer refused this package outright, blocking installs and locks
  that had nothing to do with it. No code change.

## 1.1.2 — 2026-08-12

### Added
- `README.md` — companion installation guide with template map, Alpine.js feature notes, and Tailwind scanning instructions
- `FEATURES.md` — feature reference for the Hyvä companion module
- `docs/MARKETING.txt` — marketplace copy (3-sentence summary and HTML long description)

### Fixed
- Remove hardcoded `"version"` field from `composer.json` so git tags are authoritative (same fix applied to `BlueFairy_Recipes` in v1.0.2)

## 1.1.1 — 2026-08-12

### Changed
- Released in step with BlueFairy_Recipes 1.1.1 (docs update only; no code changes)

## 1.1.0 — 2026-08-12

### Added
- Hyva templates for nutrition, schema (JSON-LD), steps, and recipe submission (`nutrition.phtml`, `schema.phtml`, `steps.phtml`, `submit.phtml`)
- Hyva template overrides for product page recipe list and tab (`product/recipes.phtml`, `product/recipes-tab.phtml`)
- Dedicated Hyva layout handles: `hyva_blue_recipe_index_index.xml`, `hyva_blue_recipe_index_view.xml`, `hyva_blue_recipe_index_submit.xml`
- `etc/frontend/` routes registration for Hyva-specific handles

### Improved
- `TemplateResolver`: converted `$templateMap` to typed `private const array TEMPLATE_MAP` (PHP 8.3)
- `TemplateResolver`: return type widened to `?string`; early-return guard skips non-Recipes templates without a map lookup
- `TemplateResolver`: expanded template map to cover all 13 Recipes templates that existed at the time (up from 7). It covers 15 today - see the README table, which is generated from the map.
- Unit tests updated for `TemplateResolver` and `View` block to match refactored plugin

## 1.0.3 — 2026-08-11

### Fixed
- Remove stray 0x01 control byte from all 8 phtml templates

## 1.0.2 — 2026-08-11

### Fixed
- Remove hardcoded version field from composer.json so git tags are authoritative

## 1.0.1 — 2026-08-11

### Fixed
- Add `declare(strict_types=1)` to all 8 phtml templates


## 1.0.0 — 2026-08-11

### Added
- Hyvä theme companion — replaces all Luma frontend templates with Tailwind CSS and Alpine.js equivalents
- Recipe listing, recipe detail, and writer profile page templates
- Featured Recipes widget template for Hyvä storefronts
- Plugin and Block overrides for Hyvä-compatible rendering

# BlueFairy_RecipesHyva

**Free and open source (MIT).** The Hyvä theme companion for the paid `bluefairy/module-recipes` extension. Published on packagist.org; the core module is sold separately.

Hyvä theme companion for [BlueFairy_Recipes](https://hyva.bluefairydevelopment.com/recipes.html). Replaces all Luma frontend templates with Tailwind CSS and Alpine.js equivalents, makes the recipe listing's search and filters submit themselves (the page reloads with the results), and registers Hyvä-native layout handles.

**This package is MIT-licensed and free.** It is not part of the core extension's licence agreement, and no licence key or purchase covers it. The core module it extends, `bluefairy/module-recipes`, is sold separately.

---

## Requirements

- Magento Open Source or Adobe Commerce 2.4.8 or 2.4.9 (`magento/framework ~103.0.8`). The constraint refuses 2.4.7 and earlier and any 103.1 framework.
- PHP 8.3, 8.4 or 8.5 (composer.json admits `~8.3.0||~8.4.0||~8.5.0`).
- [Hyvä Theme](https://hyva.io/) (`hyva-themes/magento2-theme-module ^1.0`)
- `BlueFairy_Recipes ^1.3` (the core module, installed first)

---

## Installation

```bash
# 1. This companion is free and public - no credentials needed
composer require bluefairy/module-recipes-hyva

# The core module, bluefairy/module-recipes, is a paid extension. Install it from
# wherever you bought it: your Private Packagist repository, or the Adobe Commerce
# Marketplace with your Marketplace access keys. This package requires it (^1.3)
# and composer will tell you if it is not reachable.

# 2. Enable both modules (on a Luma storefront, enable BlueFairy_Recipes alone and do not install this package)
php bin/magento module:enable BlueFairy_Recipes BlueFairy_RecipesHyva

# 3. Upgrade, compile, deploy
php bin/magento setup:upgrade
php -d memory_limit=2G bin/magento setup:di:compile
php bin/magento setup:static-content:deploy
php bin/magento cache:flush
```

---

## What It Does

The module registers a `TemplateResolver` plugin that intercepts `getTemplate()` calls on any Recipes block. When a Hyvä theme is active, the plugin swaps each Luma template for its Hyvä equivalent — no layout XML changes required on the storefront side.

### Templates replaced (14 total)

| Luma template | Hyvä equivalent (full identifier) |
|---|---|
| `product/recipes-tab.phtml` | `BlueFairy_RecipesHyva::product/recipes-tab.phtml` |
| `recipe/affiliate-drawer.phtml` | `BlueFairy_RecipesHyva::recipe/affiliate-drawer.phtml` |
| `recipe/ingredients.phtml` | `BlueFairy_RecipesHyva::recipe/ingredients.phtml` |
| `recipe/list.phtml` | `BlueFairy_RecipesHyva::recipe/list.phtml` |
| `recipe/nutrition.phtml` | `BlueFairy_RecipesHyva::recipe/nutrition.phtml` |
| `recipe/reviews.phtml` | `BlueFairy_RecipesHyva::recipe/reviews.phtml` |
| `recipe/schema.phtml` | `BlueFairy_RecipesHyva::recipe/schema.phtml` |
| `recipe/share.phtml` | `BlueFairy_RecipesHyva::recipe/share.phtml` |
| `recipe/steps.phtml` | `BlueFairy_RecipesHyva::recipe/steps.phtml` |
| `recipe/submit.phtml` | `BlueFairy_RecipesHyva::recipe/submit.phtml` |
| `recipe/view.phtml` | `BlueFairy_RecipesHyva::recipe/view.phtml` |
| `widget/featured.phtml` | `BlueFairy_RecipesHyva::widget/featured.phtml` |
| `writer/list.phtml` | `BlueFairy_RecipesHyva::writer/list.phtml` |
| `writer/profile.phtml` | `BlueFairy_RecipesHyva::writer/profile.phtml` |

### Layout handles added

- `hyva_blue_recipe_index_index` — recipe listing
- `hyva_blue_recipe_index_view` — recipe detail
- `hyva_blue_recipe_index_submit` — submission form

### Alpine.js enhancements

- **Recipe listing** — the search and the filters submit themselves: the page reloads with the results about 600 ms after you stop typing and 250 ms after you change a filter, so no button needs pressing
- **Add to Cart** — reactive button state (loading spinner → success tick) on ingredient cart submission
- **Affiliate drawer** — off-canvas drawer controlled by Alpine `x-data` toggle; it is marked `role="dialog"` with `aria-modal="true"`, moves focus to its close button when opened, and closes on the Escape key and on a click on the backdrop

---

## Tailwind CSS Source Scanning

The Hyvä templates use Tailwind utility classes, and the theme build has to scan them. This package registers its own Tailwind source with Hyvä's configuration generator (it listens to the `hyva_config_generate_before` event, `Observer/RegisterModuleForHyvaConfig.php`), so a standard Hyvä theme needs no manual `@source` lines. After installing or upgrading the package, run:

```bash
php bin/magento hyva:config:generate
cd app/design/frontend/<Vendor>/<theme>/web/tailwind
npm ci && npm run build
php bin/magento cache:flush
```

If your theme build does not use the generated configuration, add the following `@source` directives to your child theme's Tailwind entry point instead. Adjust the relative path to match your theme's directory depth.

```css
/* BlueFairy Recipes — Hyvä templates */
@source "../../../../../../../vendor/bluefairy/module-recipes-hyva/view/frontend/**/*.phtml";
@source "../../../../../../../vendor/bluefairy/module-recipes-hyva/view/frontend/**/*.xml";
```

After adding them, rebuild your theme CSS and flush the cache:

```bash
cd app/design/frontend/<Vendor>/<theme>/web/tailwind
npm run build
php bin/magento cache:flush
```

---

## Troubleshooting

**Hyvä templates not loading**
- Confirm the module is enabled: `php bin/magento module:status BlueFairy_RecipesHyva`
- Run `php bin/magento setup:di:compile && php bin/magento cache:flush`
- Confirm your active theme extends the Hyvä base theme

**Filter/search does nothing when I type or change a filter**
- Confirm `BlueFairy_RecipesHyva` is enabled (not just `BlueFairy_Recipes`)
- Check browser console for Alpine.js errors — ensure Hyvä's `alpine.js` bundle is loading

**Tailwind utility classes missing (unstyled output)**
- Run `php bin/magento hyva:config:generate`, confirm `app/etc/hyva-themes.json` has an entry whose `src` is `vendor/bluefairy/module-recipes-hyva`, and rebuild the theme CSS
- If your build does not use the generated configuration, add the `@source` directives above

---

## Full Documentation

See the core extension's own README, shipped in `bluefairy/module-recipes`, for full configuration, admin usage, ACL roles and troubleshooting.

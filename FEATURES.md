# BlueFairy_RecipesHyva — Features

Hyvä theme companion for BlueFairy_Recipes. Free and open source under the MIT licence; the core module is sold separately.

---

## Template Overrides (14 templates)

All Luma `.phtml` templates are replaced with Tailwind CSS + Alpine.js equivalents via a `TemplateResolver` plugin. No layout XML changes are needed on the storefront.

- **Recipe listing** (`list.phtml`) — card grid with filter panel and keyword search
- **Recipe detail** (`view.phtml`) — hero, meta badges, writer attribution
- **Ingredients** (`ingredients.phtml`) — ingredient list with add-to-cart and affiliate drawer trigger
- **Steps** (`steps.phtml`) — numbered cooking instructions
- **Nutrition** (`nutrition.phtml`) — nutritional facts table (populated fields only)
- **Schema** (`schema.phtml`) — Schema.org `Recipe` JSON-LD output
- **Reviews** (`reviews.phtml`) — approved reviews list and write-a-review form
- **Recipe share** (`share.phtml`) — social share buttons using the native Web Share API with per-platform fallbacks
- **Submit** (`submit.phtml`) — customer recipe submission form
- **Writer profile** (`writer/profile.phtml`) — full profile page with recipe grid
- **Writer listing** (`writer/list.phtml`) — grid of all writers, each linking to its profile
- **Featured widget** (`widget/featured.phtml`) — CMS widget recipe cards
- **Product recipes tab** (`product/recipes-tab.phtml`) — product page tab wrapper
- **Affiliate drawer** (`recipe/affiliate-drawer.phtml`) — off-canvas affiliate links panel

---

## Alpine.js Features

- **Self-submitting filters and search** — the recipe listing reloads with the results about 600 ms after keyword typing stops and 250 ms after a taxonomy filter changes, so no button needs pressing. The page does reload.
- **Add-to-cart feedback** — reactive button state (idle → loading spinner → success tick) when adding the linked ingredient products to the cart.
- **Affiliate drawer** — off-canvas slide-in panel listing affiliate ingredient links, toggled via Alpine `x-data` / `x-show`. Closes on the Escape key and on a click on the backdrop.

---

## Layout Handles

Three Hyvä-specific layout handles allow Hyvä-aware blocks and containers to be targeted independently from Luma:

| Handle | Page |
|---|---|
| `hyva_blue_recipe_index_index` | Recipe listing (`/recipes/`) |
| `hyva_blue_recipe_index_view` | Recipe detail (`/recipes/{url-key}`) |
| `hyva_blue_recipe_index_submit` | Submission form (`/recipes/index/submit`) |

---

## Developer Notes

- Template swapping is implemented as an `after` plugin on `\Magento\Framework\View\Element\Template::getTemplate()` — no layout XML overrides required
- `TemplateResolver` uses a typed `private const array TEMPLATE_MAP` (PHP 8.3 syntax) covering all 14 recipe templates
- No additional database tables or cron jobs. One observer is registered — `RegisterModuleForHyvaConfig` on the `hyva_config_generate_before` event — which adds this module's templates to the Hyvä Tailwind build (see the README's Tailwind CSS Source Scanning section)
- A navigation plugin (`Plugin/Navigation/AddRecipesLink.php`) adds the Recipes link to the Hyvä menu, using the Enabled, Show in Navigation Menu and Navigation Menu Label settings

<?php
/**
 * Copyright © 2025 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\RecipesHyva\Plugin;

use Hyva\Theme\Service\CurrentTheme;
use Magento\Framework\View\Element\Template;

/**
 * After-plugin that replaces BlueFairy_Recipes Luma templates with their Hyva equivalents.
 *
 * IT ACTS ONLY WHEN A HYVA THEME IS ACTIVE.
 *
 * This plugin is declared on Magento\Framework\View\Element\Template in
 * etc/frontend/di.xml, so it runs for every block on every frontend request. The theme check
 * keeps it from handing Alpine markup to a Luma theme that has no Alpine.
 *
 * THE LAYOUT FILES ARE LIVE. There are three - hyva_blue_recipe_index_{index,submit,view}.xml.
 * Each declares `layout="1column"`, and that setting is applied: a block added to one of them
 * renders on any Hyva theme.
 *
 * The mechanism is Hyva's, not ours: `hyva-themes/magento2-theme-module` ships
 * Observer/AddLayoutHandles.php, which prefixes every layout handle with `hyva_`
 * when a Hyva theme is active. Nothing in these two packages adds a `hyva_`-prefixed
 * handle. The template SWAP lives here, in this plugin; the handles are a separate facility
 * that a developer can target independently of Luma.
 *
 * WHY A RENDER-TIME CHECK AND NOT A LAYOUT ONE. Passing Hyva's CurrentTheme as a LAYOUT ARGUMENT
 * 500s the whole page. Reading it here is different: afterGetTemplate() runs while a block
 * renders, long after the design theme is resolved, and CurrentTheme::isHyva() only asks
 * DesignInterface for the theme it already chose.
 */
class TemplateResolver
{
    public function __construct(private readonly CurrentTheme $currentTheme)
    {
    }

    private const array TEMPLATE_MAP = [
        'BlueFairy_Recipes::recipe/affiliate-drawer.phtml' => 'BlueFairy_RecipesHyva::recipe/affiliate-drawer.phtml',
        'BlueFairy_Recipes::recipe/ingredients.phtml'      => 'BlueFairy_RecipesHyva::recipe/ingredients.phtml',
        'BlueFairy_Recipes::recipe/list.phtml'             => 'BlueFairy_RecipesHyva::recipe/list.phtml',
        'BlueFairy_Recipes::recipe/nutrition.phtml'        => 'BlueFairy_RecipesHyva::recipe/nutrition.phtml',
        'BlueFairy_Recipes::recipe/reviews.phtml'          => 'BlueFairy_RecipesHyva::recipe/reviews.phtml',
        'BlueFairy_Recipes::recipe/schema.phtml'           => 'BlueFairy_RecipesHyva::recipe/schema.phtml',
        'BlueFairy_Recipes::recipe/share.phtml'            => 'BlueFairy_RecipesHyva::recipe/share.phtml',
        'BlueFairy_Recipes::recipe/steps.phtml'            => 'BlueFairy_RecipesHyva::recipe/steps.phtml',
        'BlueFairy_Recipes::recipe/submit.phtml'           => 'BlueFairy_RecipesHyva::recipe/submit.phtml',
        'BlueFairy_Recipes::recipe/view.phtml'             => 'BlueFairy_RecipesHyva::recipe/view.phtml',
        'BlueFairy_Recipes::product/recipes-tab.phtml'     => 'BlueFairy_RecipesHyva::product/recipes-tab.phtml',
        'BlueFairy_Recipes::widget/featured.phtml'         => 'BlueFairy_RecipesHyva::widget/featured.phtml',
        'BlueFairy_Recipes::writer/list.phtml'             => 'BlueFairy_RecipesHyva::writer/list.phtml',
        'BlueFairy_Recipes::writer/profile.phtml'          => 'BlueFairy_RecipesHyva::writer/profile.phtml',
    ];

    /**
     * Replace a Luma template identifier with its Hyva counterpart, if one exists.
     *
     * @param Template    $subject
     * @param string|null $result
     * @return string|null
     */
    public function afterGetTemplate(Template $subject, ?string $result): ?string
    {
        // The cheap test comes first, and the order matters. This plugin is on
        // Magento\Framework\View\Element\Template, so it is called for every block on the
        // page - hundreds of times per request. A string prefix comparison rejects all of
        // them but ours before anything asks the design system a question.
        if ($result === null || !str_starts_with($result, 'BlueFairy_Recipes::')) {
            return $result;
        }

        // The base package's own template is the correct answer on a non-Hyva
        // theme, so it is returned unchanged.
        if (!$this->currentTheme->isHyva()) {
            return $result;
        }

        return self::TEMPLATE_MAP[$result] ?? $result;
    }
}

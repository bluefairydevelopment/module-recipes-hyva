<?php
/**
 * Copyright © 2025 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\RecipesHyva\Plugin\Navigation;

use BlueFairy\Recipes\Helper\Config;
use Hyva\Theme\ViewModel\Navigation;
use Magento\Framework\UrlInterface;

/**
 * Adds the Recipes link to the Hyva menu.
 *
 * It type-hints Hyva\Theme\ViewModel\Navigation, so it lives in this package, which requires Hyva;
 * BlueFairy_Recipes requires only php and magento/framework. It reads the settings
 * blue_recipes/general/show_in_navigation and navigation_label. On Luma the same two settings are read
 * by BlueFairy\Recipes\Observer\AddRecipesToTopMenu.
 */
class AddRecipesLink
{
    /**
     * @param Config $config
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly UrlInterface $urlBuilder,
    ) {
    }

    /**
     * Append a Recipes link to the navigation menu after category items.
     *
     * @param Navigation $subject
     * @param array|false $result
     * @return array|false
     */
    public function afterGetNavigation(Navigation $subject, $result)
    {
        if (!is_array($result)) {
            return $result;
        }

        if (!$this->config->isEnabled() || !$this->config->isShowInNavigation()) {
            return $result;
        }

        $route = $this->config->getRecipeRoute() ?: 'recipes';
        $label = $this->config->getNavigationLabel() ?: __('Recipes');

        $result[] = [
            'id'        => 'blue-recipes-nav',
            'name'      => (string) $label,
            'url'       => $this->urlBuilder->getUrl('', ['_direct' => $route]),
            'is_active' => false,
            'level'     => 0,
            'path'      => '',
            'childData' => [],
        ];

        return $result;
    }
}

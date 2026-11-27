<?php
/**
 * Copyright © 2025 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\RecipesHyva\Test\Unit\Plugin\Navigation;

use BlueFairy\Recipes\Helper\Config;
use BlueFairy\RecipesHyva\Plugin\Navigation\AddRecipesLink;
use Hyva\Theme\ViewModel\Navigation;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class AddRecipesLinkTest extends TestCase
{
    private Config&Stub $config;
    private UrlInterface $urlBuilder;
    private Navigation&Stub $navigation;
    private AddRecipesLink $plugin;

    /** @var array<string, bool> Collaborators this test verifies; the rest are stubs. */
    private array $mocked = [];

    protected function setUp(): void
    {
        $this->build();
    }

    /**
     * A collaborator double: a mock when the test asked for one (mock()), otherwise a stub,
     * so a test that verifies nothing on a collaborator never carries an unconfigured mock.
     *
     * @param string $property
     * @param class-string $type
     * @return object
     */
    private function double(string $property, string $type): object
    {
        return isset($this->mocked[$property]) ? $this->createMock($type) : $this->createStub($type);
    }

    /**
     * Make these collaborators mocks for this test and rebuild everything around them.
     * Call it first in a test that sets expectations on them.
     *
     * @param string ...$properties
     * @return void
     */
    private function mock(string ...$properties): void
    {
        foreach ($properties as $property) {
            $this->mocked[$property] = true;
        }
        $this->build();
    }

    private function build(): void
    {
        $this->config     = $this->createStub(Config::class);
        $this->urlBuilder = $this->double('urlBuilder', UrlInterface::class);
        $this->navigation = $this->createStub(Navigation::class);

        $this->plugin = new AddRecipesLink($this->config, $this->urlBuilder);
    }

    public function testAppendsRecipesLinkWhenEnabled(): void
    {
        $this->mock('urlBuilder');
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInNavigation')->willReturn(true);
        $this->config->method('getRecipeRoute')->willReturn('recipes');
        $this->config->method('getNavigationLabel')->willReturn('Recipes');

        $this->urlBuilder->method('getUrl')
            ->with('', ['_direct' => 'recipes'])
            ->willReturn('http://example.com/recipes');

        $menuItems = [
            ['id' => 'cat-1', 'name' => 'Category 1', 'url' => 'http://example.com/cat1', 'childData' => []],
        ];

        $result = $this->plugin->afterGetNavigation($this->navigation, $menuItems);

        $this->assertCount(2, $result);
        $this->assertSame('blue-recipes-nav', $result[1]['id']);
        $this->assertSame('Recipes', $result[1]['name']);
        $this->assertSame('http://example.com/recipes', $result[1]['url']);
    }

    public function testDoesNotAppendWhenModuleDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->config->method('isShowInNavigation')->willReturn(true);

        $menuItems = [['id' => 'cat-1', 'name' => 'Category 1', 'url' => 'http://example.com/cat1', 'childData' => []]];

        $result = $this->plugin->afterGetNavigation($this->navigation, $menuItems);

        $this->assertCount(1, $result);
    }

    public function testDoesNotAppendWhenNavigationDisabled(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInNavigation')->willReturn(false);

        $menuItems = [['id' => 'cat-1', 'name' => 'Category 1', 'url' => 'http://example.com/cat1', 'childData' => []]];

        $result = $this->plugin->afterGetNavigation($this->navigation, $menuItems);

        $this->assertCount(1, $result);
    }

    public function testUsesCustomLabel(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInNavigation')->willReturn(true);
        $this->config->method('getRecipeRoute')->willReturn('rezepte');
        $this->config->method('getNavigationLabel')->willReturn('Our Recipes');

        $this->urlBuilder->method('getUrl')->willReturn('http://example.com/rezepte');

        $result = $this->plugin->afterGetNavigation($this->navigation, []);

        $this->assertSame('Our Recipes', $result[0]['name']);
    }

    public function testReturnsFalseWhenInputIsFalse(): void
    {
        $result = $this->plugin->afterGetNavigation($this->navigation, false);

        $this->assertFalse($result);
    }

    public function testFallsBackToDefaultRouteWhenEmpty(): void
    {
        $this->mock('urlBuilder');
        $this->config->method('isEnabled')->willReturn(true);
        $this->config->method('isShowInNavigation')->willReturn(true);
        $this->config->method('getRecipeRoute')->willReturn('');
        $this->config->method('getNavigationLabel')->willReturn('');

        $this->urlBuilder->expects($this->once())
            ->method('getUrl')
            ->with('', ['_direct' => 'recipes'])
            ->willReturn('http://example.com/recipes');

        $result = $this->plugin->afterGetNavigation($this->navigation, []);

        $this->assertCount(1, $result);
        $this->assertSame('Recipes', $result[0]['name']);
    }
}

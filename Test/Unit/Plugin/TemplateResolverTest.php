<?php
/**
 * Copyright © 2025 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\RecipesHyva\Test\Unit\Plugin;

use BlueFairy\RecipesHyva\Plugin\TemplateResolver;
use Hyva\Theme\Service\CurrentTheme;
use Magento\Framework\View\Element\Template;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TemplateResolverTest extends TestCase
{
    /** @var TemplateResolver */
    private TemplateResolver $plugin;

    /** @var Template&\PHPUnit\Framework\MockObject\MockObject */
    private Template $subject;

    /** @var CurrentTheme&MockObject */
    private CurrentTheme $currentTheme;

    protected function setUp(): void
    {
        $this->currentTheme = $this->createStub(CurrentTheme::class);

        // M2MAP-342. Hyva by default, so every case below tests what it always
        // tested. The cases that care about LUMA say so explicitly - and they
        // are the ones the plugin had no answer for.
        $this->currentTheme->method('isHyva')->willReturn(true);

        $this->plugin  = new TemplateResolver($this->currentTheme);
        $this->subject = $this->createStub(Template::class);
    }

    /**
     * A plugin built on a stated theme, for the cases that must vary it.
     */
    private function pluginOnTheme(bool $isHyva): TemplateResolver
    {
        $theme = $this->createStub(CurrentTheme::class);
        $theme->method('isHyva')->willReturn($isHyva);

        return new TemplateResolver($theme);
    }

    /**
     * M2MAP-342. THE ASSERTION THAT DISCRIMINATES.
     *
     * This plugin is declared on Magento\Framework\View\Element\Template, so
     * it ran for every block on every frontend request in EVERY theme. With no
     * theme check, a Luma store view with this package installed had all
     * fourteen recipe templates swapped for Hyva ones - Alpine markup handed to
     * a theme with no Alpine to interpret it.
     *
     * The end state does not discriminate: both themes render A template and
     * return a non-null string. WHICH template is the whole question.
     */
    public function testALumaThemeKeepsTheBasePackageTemplate(): void
    {
        $luma = $this->pluginOnTheme(false);

        foreach (array_keys(self::templatePairsOnDisk()) as $template) {
            self::assertSame(
                $template,
                $luma->afterGetTemplate($this->subject, $template),
                sprintf(
                    'on a NON-Hyva theme the plugin swapped %s for its Hyva counterpart. That is an '
                    . 'Alpine template rendered into a theme with no Alpine: it looks present and '
                    . 'does nothing.',
                    $template
                )
            );
        }
    }

    /**
     * ...and the Hyva side still swaps, so the gate is a gate and not an off
     * switch. Without this, a plugin that ALWAYS returned the base template
     * would satisfy the case above while breaking every Hyva store.
     */
    public function testAHyvaThemeStillGetsTheHyvaTemplate(): void
    {
        $hyva = $this->pluginOnTheme(true);

        foreach (self::templatePairsOnDisk() as $luma => $expected) {
            self::assertSame(
                $expected,
                $hyva->afterGetTemplate($this->subject, $luma),
                sprintf('the Hyva theme no longer receives the Hyva template for %s', $luma)
            );
        }
    }

    /**
     * THE THEME IS NEVER ASKED ABOUT SOMEBODY ELSE'S TEMPLATE.
     *
     * The prefix test has to come first. This plugin is called for every block
     * on the page - hundreds of times per request - and isHyva() reaches into
     * the design system. Ordering the checks the other way would put that on
     * the hot path of every Magento block in the store.
     */
    public function testTheDesignSystemIsNotConsultedForForeignTemplates(): void
    {
        $theme = $this->createMock(CurrentTheme::class);
        $theme->expects(self::never())->method('isHyva');

        $plugin = new TemplateResolver($theme);
        foreach (['Magento_Catalog::product/view.phtml', 'Magento_Checkout::cart.phtml', null] as $t) {
            $plugin->afterGetTemplate($this->subject, $t);
        }
    }

    private const PACKAGE_MARKER = 'RecipesHyva/etc/module.xml';

    /**
     * M2MAP-410. THE MAP IS DERIVED FROM DISK, NOT COPIED BY HAND.
     *
     * Three hand-written copies of TEMPLATE_MAP lived in this file - a const,
     * a data provider and an inline array in testAllMappedTemplatesUseHyvaModule
     * - and all three listed FOURTEEN entries while the product's map had
     * FIFTEEN. Every one was missing writer/list.phtml, added by M2MAP-387.
     *
     * Deleting that line from the real TEMPLATE_MAP left this suite at
     * OK (40 tests), exit 0: a Hyva store would have rendered Luma markup for
     * the writer listing and nothing here would have noticed. Because the test
     * iterated its OWN list, it could only ever verify entries it already knew
     * about - the list was both the subject and the oracle.
     *
     * The pairs on disk are the oracle now.
     *
     * @return array<string, string> Luma template id => Hyva template id
     */
    private static function templatePairsOnDisk(): array
    {
        $root = dirname(__DIR__, 4);

        if (!is_file($root . '/' . self::PACKAGE_MARKER)) {
            throw new \RuntimeException(sprintf(
                'walked up to %s, which does not contain %s - the directory depth is wrong and '
                . 'this sweep was about to examine somebody else\'s package',
                $root,
                self::PACKAGE_MARKER
            ));
        }

        $base = $root . '/Recipes/view/frontend/templates';
        $hyva = $root . '/RecipesHyva/view/frontend/templates';

        $pairs = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $path = (string) $file;

            if (!str_ends_with($path, '.phtml')) {
                continue;
            }

            $relative = substr($path, strlen($base) + 1);

            if (!is_file($hyva . '/' . $relative)) {
                continue;
            }

            $pairs['BlueFairy_Recipes::' . $relative] = 'BlueFairy_RecipesHyva::' . $relative;
        }

        ksort($pairs);

        return $pairs;
    }

    /**
     * THE COMPLETENESS ASSERTION THE OLD TEST COULD NOT MAKE. Every template
     * that exists in BOTH packages must be in the map, and every map entry
     * must have a file on both sides.
     */
    public function testTheTemplateMapCoversEveryPairOnDisk(): void
    {
        $pairs = self::templatePairsOnDisk();

        // FLOOR ON THE SEARCH SPACE. A wrong path would otherwise report
        // "the map covers every pair" while covering nothing.
        self::assertGreaterThanOrEqual(
            // M2MAP-432. WAS 15. product/recipes.phtml went from both
            // packages with its TEMPLATE_MAP entry - it was rendered only
            // by a plugin declared disabled="true", so nothing had ever
            // loaded it. A DELIBERATE DELETION is the only thing that may
            // move this number; drift must still fail. If it moves again,
            // the commit has to say what went and why.
            14,
            count($pairs),
            'only ' . count($pairs) . ' template pairs found on disk. There were 14 when this '
            . 'floor was set. Retarget the discovery - do not lower this number.'
        );

        // Read via reflection rather than widening the constant to public.
        // The map is an internal detail; letting a test change production
        // visibility is the test shaping the product.
        $map = (new \ReflectionClassConstant(TemplateResolver::class, 'TEMPLATE_MAP'))->getValue();
        self::assertIsArray($map);

        self::assertSame(
            [],
            array_diff_key($pairs, $map),
            'these templates exist in BOTH packages but are absent from TEMPLATE_MAP, so a Hyva '
            . 'store renders the LUMA markup for them - silently, with no error and no 500'
        );

        self::assertSame(
            [],
            array_diff_key($map, $pairs),
            'these TEMPLATE_MAP entries name a template that does not exist on both sides'
        );

        foreach ($pairs as $luma => $expectedHyva) {
            self::assertSame(
                $expectedHyva,
                $map[$luma],
                $luma . ' is mapped to the wrong Hyva template'
            );
        }
    }

    /**
     * M2MAP-581. The FEATURES.md "Template Overrides" list is generated prose that
     * drifted: the heading said 14, the list enumerated 12. This pins both the
     * heading count and the number of template bullets to TEMPLATE_MAP, so a
     * dropped or added bullet - or a stale heading - reddens instead of shipping.
     */
    public function testTheFeaturesDocTemplateListMatchesTheMap(): void
    {
        $map = (new \ReflectionClassConstant(TemplateResolver::class, 'TEMPLATE_MAP'))->getValue();
        self::assertIsArray($map);
        $expected = count($map);

        $features = (string) file_get_contents(dirname(__DIR__, 4) . '/RecipesHyva/FEATURES.md');

        self::assertSame(
            1,
            preg_match('/Template Overrides \((\d+) templates\)/', $features, $h),
            'the FEATURES.md "Template Overrides (N templates)" heading is missing or reworded'
        );
        self::assertSame(
            $expected,
            (int) $h[1],
            'the FEATURES.md heading claims a template count that disagrees with TEMPLATE_MAP'
        );

        $bullets = preg_match_all('/^- \*\*[^*]+\*\* \(`[^`]+\.phtml`\)/m', $features);
        self::assertSame(
            $expected,
            $bullets,
            'the FEATURES.md template list has ' . $bullets . ' bullets but TEMPLATE_MAP has '
            . $expected . ' - a bullet was dropped or added without updating the other'
        );
    }

    /**
     * M2MAP-410. BOTH ANNOTATION FORMS, DELIBERATELY.
     *
     * PHPUnit 9.6 honours the doc-comment and IGNORES the attribute; PHPUnit
     * 10+ does the reverse. Carrying only one means that on the other runner
     * the cases stop being supplied and the test COUNT FALLS SILENTLY, with no
     * failure. M2MAP-361 paired every provider in the Recipes package and
     * never swept this one - a cure that covered one of two packages.
     *
     * @dataProvider lumaToHyvaTemplateProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('lumaToHyvaTemplateProvider')]
    public function testLumaTemplateIsReplacedWithHyvaTemplate(
        string $lumaTemplate,
        string $expectedHyvaTemplate
    ): void {
        $result = $this->plugin->afterGetTemplate($this->subject, $lumaTemplate);

        $this->assertSame(
            $expectedHyvaTemplate,
            $result,
            "Expected Luma template '{$lumaTemplate}' to be replaced with Hyva template '{$expectedHyvaTemplate}'."
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function lumaToHyvaTemplateProvider(): array
    {
        $cases = [];

        foreach (self::templatePairsOnDisk() as $luma => $hyva) {
            $cases[substr($luma, strlen('BlueFairy_Recipes::'))] = [$luma, $hyva];
        }

        if (count($cases) < 14) {
            throw new \RuntimeException(
                'only ' . count($cases) . ' template pairs found - a provider that returns too '
                . 'few cases passes at 0-of-0, so this THROWS rather than asserting'
            );
        }

        return $cases;
    }


    public function testNonRecipeTemplateIsPassedThrough(): void
    {
        $nonRecipeTemplate = 'Magento_Catalog::product/view.phtml';

        $result = $this->plugin->afterGetTemplate($this->subject, $nonRecipeTemplate);

        $this->assertSame(
            $nonRecipeTemplate,
            $result,
            'Templates not in the BlueFairy_Recipes namespace must be returned unchanged.'
        );
    }

    public function testEmptyTemplateIsPassedThrough(): void
    {
        $result = $this->plugin->afterGetTemplate($this->subject, '');

        $this->assertSame('', $result, 'An empty template string must be returned unchanged.');
    }

    public function testUnmappedRecipesTemplateIsPassedThrough(): void
    {
        $unmapped = 'BlueFairy_Recipes::recipe/unknown-future-template.phtml';

        $result = $this->plugin->afterGetTemplate($this->subject, $unmapped);

        $this->assertSame(
            $unmapped,
            $result,
            'A BlueFairy_Recipes template with no Hyva mapping must be returned unchanged.'
        );
    }

    public function testHyvaTemplatePrecedenceOverLuma(): void
    {
        $lumaTemplate = 'BlueFairy_Recipes::recipe/view.phtml';
        $result       = $this->plugin->afterGetTemplate($this->subject, $lumaTemplate);

        $this->assertStringStartsWith(
            'BlueFairy_RecipesHyva::',
            $result,
            'Hyva templates must take precedence: result must reference BlueFairy_RecipesHyva module.'
        );
    }

    public function testAllMappedTemplatesUseHyvaModule(): void
    {
        $lumaTemplates = array_keys(self::templatePairsOnDisk());

        foreach ($lumaTemplates as $luma) {
            $hyva = $this->plugin->afterGetTemplate($this->subject, $luma);

            $this->assertStringStartsWith(
                'BlueFairy_RecipesHyva::',
                $hyva,
                "Template '{$luma}' must resolve to a Hyva template."
            );

            $this->assertNotSame(
                $luma,
                $hyva,
                "Template '{$luma}' must be replaced, not returned as-is."
            );
        }
    }

    public function testNonRecipesModuleTemplateIsNotIntercepted(): void
    {
        $templates = [
            'Magento_Checkout::cart.phtml',
            'Magento_Customer::account/login.phtml',
            'BlueFairy_OrderComments::order/comments.phtml',
        ];

        foreach ($templates as $template) {
            $result = $this->plugin->afterGetTemplate($this->subject, $template);

            $this->assertSame(
                $template,
                $result,
                "Non-BlueFairy_Recipes template '{$template}' must not be intercepted."
            );
        }
    }
}

<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\RecipesHyva\Test\Unit\Package;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * PORTED from module-recipes, where these ran from the base package's suite because both
 * packages were in one tree. This package left that repository on 2026-11-27 and they
 * came with it.
 *
 * WHAT THESE PROVE. This package replaces the core's Luma templates with Hyva ones
 * through one TemplateResolver map. Three things can drift apart and nothing else notices:
 * the map, the README table that documents it, and the core templates the map's left-hand
 * side names. A map entry for a core template that no longer exists silently stops
 * resolving - the shopper gets the Luma template on a Hyva store, which is a broken page,
 * not an error.
 *
 * THE CROSS-PACKAGE HALF READS THE INSTALLED CORE. bluefairy/module-recipes is a hard
 * `require` of this package (^1.3), so wherever this package is installed the core is in
 * the Magento root's vendor/. It is deliberately NOT a require-dev of this repository: the
 * core is not on public Packagist, so that would break `composer install` here for anyone
 * without Private Packagist credentials.
 *
 * In the monorepo this read a sibling DIRECTORY out of one tree. Reading the installed
 * copy is the better test now and not the M2MAP-233 trap that warning described: there,
 * vendor/ held a different copy of a package that was also in the tree under test; here
 * the core genuinely is an external dependency, and the copy in vendor/ at the version
 * this package's constraint admits is the copy a merchant runs.
 */
#[CoversNothing]
class TheGuidesAndTheResolverMatchTheCoreTemplatesTest extends TestCase
{
    private static function read(string $relative): string
    {
        $path = dirname(__DIR__, 3) . '/' . $relative;
        self::assertFileExists($path, $relative . ' is not in this package');

        return (string) file_get_contents($path);
    }

    /** interface/template map entries: core template => this package's template. */
    private static function resolverMap(): array
    {
        $source = self::read('Plugin/TemplateResolver.php');
        preg_match_all(
            "/'BlueFairy_Recipes::([^']+)'\\s*=>\\s*'BlueFairy_RecipesHyva::([^']+)'/",
            $source,
            $m,
            PREG_SET_ORDER
        );
        $map = [];
        foreach ($m as $pair) {
            $map[$pair[1]] = $pair[2];
        }

        // FLOOR ON THE SEARCH SPACE. Every assertion below is about the entries found. An
        // empty map - a renamed class, a changed quote style, a regex that stopped matching
        // - would satisfy all of them and read exactly like a clean run.
        self::assertGreaterThanOrEqual(
            10,
            count($map),
            'the TemplateResolver map parsed to ' . count($map) . ' entries. There were at least 10 '
            . 'when this floor was set; a shrunken search space reads exactly like a clean one, so '
            . 'retarget this floor deliberately, never lower it to make a run pass'
        );

        return $map;
    }

    // --------------------------------------------- this package's own consistency

    public function testTheReadmeDocumentsExactlyTheTemplatesTheResolverReplaces(): void
    {
        $readme = self::read('README.md');
        $count = count(self::resolverMap());

        self::assertSame(1, preg_match('/Templates replaced \((\d+) total\)/', $readme, $m),
            'README.md no longer states how many templates are replaced');
        self::assertSame($count, (int) $m[1], 'README.md states a different template count from the resolver map');
        self::assertSame(
            $count,
            preg_match_all('/^\| `[a-z\/-]+\.phtml` \| `BlueFairy_RecipesHyva::/m', $readme),
            'README.md table rows and the resolver map differ'
        );
        self::assertStringNotContainsString('drifted', $readme);
    }

    public function testTheReadmePointsAtThisPackagesOwnVendorPath(): void
    {
        $readme = self::read('README.md');

        self::assertStringContainsString('vendor/bluefairy/module-recipes-hyva/view/frontend/', $readme);
        self::assertStringNotContainsString(
            'vendor/bluefairy/module-recipes/',
            $readme,
            "this package's README sends the reader into the CORE's vendor path"
        );

        $composer = json_decode(self::read('composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('bluefairy/module-recipes-hyva', $composer['name']);
    }

    public function testTheHyvaConfigEventIsBothWiredAndDocumented(): void
    {
        self::assertStringContainsString('hyva_config_generate_before', self::read('etc/frontend/events.xml'));
        self::assertStringContainsString('hyva_config_generate_before', self::read('README.md'));
    }

    public function testTheListingNavigatesAndNoDocClaimsOtherwise(): void
    {
        self::assertStringContainsString(
            'window.location.href',
            self::read('view/frontend/templates/recipe/list.phtml'),
            'the listing no longer navigates: update the guides, which say a page load happens'
        );

        $examined = 0;
        foreach (['README.md', 'FEATURES.md', 'docs/MARKETING.txt'] as $file) {
            $examined++;
            self::assertDoesNotMatchRegularExpression(
                '/without (?:a )?(?:full )?page (?:reload|refresh)|needs no page reload|'
                . 'no page reload (?:when|required)|full page reload instead of live/i',
                self::read($file),
                $file
            );
        }
        self::assertSame(3, $examined, "this package's three customer-facing documents");
    }

    public function testTheMarketingMakesNoSponsoredRelClaim(): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/slide-in panel with[^.]{0,20}rel="sponsored"|drawer lists all affiliate links with[^.]{0,20}rel="sponsored"/',
            self::read('docs/MARKETING.txt')
        );
    }

    public function testTheInstallStepsEnableBothModules(): void
    {
        self::assertMatchesRegularExpression(
            '/php bin\/magento module:enable BlueFairy_Recipes BlueFairy_RecipesHyva/',
            self::read('README.md'),
            'the install steps no longer enable both modules'
        );
    }

    // --------------------------------------------- the contract with the core

    public function testEveryTemplateTheResolverReplacesExistsInTheInstalledCore(): void
    {
        $core = self::coreRoot();
        $checked = 0;
        $missing = [];
        foreach (array_keys(self::resolverMap()) as $coreTemplate) {
            $path = $core . '/view/frontend/templates/' . $coreTemplate;
            if (!is_file($path)) {
                $missing[] = $coreTemplate;
            }
            $checked++;
        }

        self::assertGreaterThanOrEqual(10, $checked, 'no map entry was checked against the core');
        self::assertSame(
            [],
            $missing,
            'the resolver maps core templates the installed core does not ship. Such an entry never '
            . 'resolves, so a Hyva shopper silently gets the Luma template - a broken page, not an error'
        );
    }

    public function testEveryTemplateTheResolverMapsToIsShippedByThisPackage(): void
    {
        $missing = [];
        foreach (self::resolverMap() as $core => $own) {
            if (!is_file(dirname(__DIR__, 3) . '/view/frontend/templates/' . $own)) {
                $missing[] = $own;
            }
        }

        self::assertSame($missing, [], 'the resolver maps to templates this package does not ship');
    }

    /**
     * The installed core's package root.
     *
     * NOT A SKIP when it is absent. A skipped check whose reason never expires is how a
     * suite goes quiet; this package hard-requires the core, so its absence means the
     * installation is broken and is worth failing over.
     */
    private static function coreRoot(): string
    {
        $magento = getenv('MAGENTO_ROOT') ?: '';
        self::assertNotSame(
            '',
            $magento,
            'MAGENTO_ROOT is not set, so the installed core cannot be found. Run: '
            . 'MAGENTO_ROOT=/path/to/magento /path/to/magento/vendor/bin/phpunit -c phpunit.xml'
        );

        $root = $magento . '/vendor/bluefairy/module-recipes';
        self::assertDirectoryExists(
            $root,
            'bluefairy/module-recipes is not installed at ' . $root . '. This package declares it as '
            . 'a hard require (^1.3), so it should be there. It is deliberately NOT a require-dev of '
            . 'this repository: the core is not on public Packagist, so that would break composer '
            . 'install here for anyone without Private Packagist credentials.'
        );

        return $root;
    }
}

<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 */

declare(strict_types=1);

namespace BlueFairy\RecipesHyva\Test\Unit\Package;

use PHPUnit\Framework\TestCase;

/**
 * M2MAP-233. THE FOUR THINGS THAT HAVE TO AGREE FOR A CLASS TO SURVIVE.
 *
 * Hyvä's Tailwind build does not scan module templates. The theme uses
 * `@import "tailwindcss" source(none)` with explicit `@source` globs, and
 * `generated/hyva-source.css` lists only modules registered in
 * `app/etc/hyva-themes.json`. A module gets in by observing
 * `hyva_config_generate_before` and appending its own path.
 *
 * A utility appearing ONLY in this module's templates is otherwise absent from
 * the compiled CSS, and the element renders as though the class had never been
 * written - no console error, no 404. Whether anything LOOKED broken depended on
 * whether the theme happened to use the same class elsewhere. That is luck, and
 * it changes whenever the theme's markup changes.
 */
class TailwindSourcesReachTheThemeBuildTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
    }

    public function testTheTailwindEntryPointExistsAndNamesTheTemplates(): void
    {
        $css = $this->root . '/view/frontend/tailwind/module.css';
        self::assertFileExists($css, 'hyva-sources imports this file; without it the module contributes nothing');
        self::assertStringContainsString(
            '@source',
            (string) file_get_contents($css),
            'module.css exists but names no sources, so the Tailwind 4 build scans nothing from this module'
        );
    }

    public function testTheObserverIsRegisteredOnHyvasGeneratorEvent(): void
    {
        $events = $this->root . '/etc/frontend/events.xml';
        self::assertFileExists($events, 'nothing puts this module into the generator input');

        $xml = (string) file_get_contents($events);
        self::assertStringContainsString(
            'hyva_config_generate_before',
            $xml,
            'the observer is declared on some other event, so hyva:config:generate never calls it'
        );
        self::assertStringContainsString(
            'BlueFairy\RecipesHyva\Observer\RegisterModuleForHyvaConfig',
            $xml,
            'the event is observed by a class that is not this module\'s registrar'
        );
        self::assertFileExists(
            $this->root . '/Observer/RegisterModuleForHyvaConfig.php',
            'events.xml names an observer class that does not exist - which fails at runtime, not here'
        );
    }

    /** Tailwind utility prefixes this module actually uses. */
    private const CLASS_PREFIXES = 'bg|text|border|from|via|to|ring|fill|stroke|shadow|'
        . 'outline|decoration|accent|caret|divide|placeholder|grid-cols|col-span|gap|w|h';

    /**
     * The shapes in which a class name is completed at runtime.
     *
     * M2MAP-424. The previous predicate matched FOUR of six realistic shapes and
     * missed two - one of them `"bg-{$name}"`, WHICH ITS OWN DOCBLOCK GAVE AS AN
     * EXAMPLE OF WHAT IT CATCHES. Proven by mutation: appending
     * `<span class="bg-{$p4}"></span>` to recipe/reviews.phtml, with the edit
     * asserted applied by an occurrence count of 0 -> 1, left the suite green.
     *
     * The old `{$var}-\d{2,3}` limb required a trailing shade number, so it saw
     * `{$c}-500` and not `bg-{$name}`. Each limb below is now proven against a
     * planted specimen by testThePredicateFindsEveryShapeItClaimsTo - which is
     * the half that was missing, and the half that would have caught this.
     *
     * @return array<string, string> what the shape is called => the pattern
     */
    private static function constructedClassPatterns(): array
    {
        $p = self::CLASS_PREFIXES;

        return [
            // 'text-' . $colour . '-500'   and   'bg-' . $v
            'php concatenation'   => '/[\'"](?:' . $p . ')-[a-z0-9-]*[\'"]\s*\.\s*\$/',
            // "bg-{$name}"
            'braced interpolation' => '/(?:' . $p . ')-\{\$[A-Za-z_]/',
            // "text-$colour"
            'bare interpolation'  => '/(?:' . $p . ')-\$[A-Za-z_]/',
            // sprintf('bg-%s-500', $v)
            'sprintf'             => '/sprintf\(\s*[\'"][^\'"]*(?:' . $p . ')-%/',
            // `bg-${x}` in a JS template literal
            'js template literal' => '/`[^`]*(?:' . $p . ')-\$\{/',
            // 'bg-' + name   in inline JS or an Alpine expression
            'js concatenation'    => '/[\'"](?:' . $p . ')-[a-z0-9-]*[\'"]\s*\+\s*[A-Za-z_$]/',
            // M2MAP-555. A short-echo or php-echo tag dropped straight into a class
            // attribute (bg- then an open PHP echo tag). The canonical PHTML shape,
            // and the one the old predicate missed.
            'php echo in attribute' => '/(?:' . $p . ')-<\?(?:=|php)/',
        ];
    }

    /**
     * Every shipped template, found recursively.
     *
     * M2MAP-424, the related half: the old glob was `templates/*` + `/*.phtml`,
     * exactly two levels, while module.css uses `@source "../templates/**"`.
     * Both cover all fourteen templates today, so it was latent - but a
     * three-level template would be scanned by Tailwind and silently dropped
     * from this test's search space. PHP's glob has no globstar.
     *
     * @return list<string>
     */
    private function shippedTemplates(): array
    {
        $dir = $this->root . '/view/frontend/templates';

        if (!is_dir($dir)) {
            self::fail($dir . ' is not there - this scan has no subject');
        }

        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'phtml') {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * THE CASE THE GLOB CANNOT COVER, AND THE REASON module.css SAYS SO.
     *
     * Tailwind's scanner is a plain-text scan. It finds `bg-[#1877F2]` inside a
     * PHP array literal and `text-yellow-400` inside an Alpine `:class`
     * expression, because both are literal text in a scanned file. It CANNOT
     * find a class assembled from a variable, because that string never
     * appears anywhere.
     *
     * recipe/share.phtml already builds its class at runtime from a PHP map and
     * recipe/reviews.phtml has three `:class` expressions, so this module is one
     * edit away from the failing shape. This test is the tripwire: if it goes
     * red, the cure is an `@source inline(...)` safelist in module.css, NOT a
     * looser assertion here.
     */
    public function testNoTemplateBuildsAClassNameFromAVariable(): void
    {
        $templates = $this->shippedTemplates();

        // Floor: an empty file list would make the loop vacuous, and this test
        // would pass on a module with no templates at all.
        self::assertGreaterThanOrEqual(
            // M2MAP-432. WAS 15, until product/recipes.phtml was deleted
            // for being rendered only by a disabled plugin. Retargeted onto
            // the real count, not lowered to accommodate a shortfall.
            14,
            count($templates),
            sprintf(
                'found %d templates for this scan, where the module ships 14. Check the '
                . 'discovery before trusting a pass - a shrunken search space reads exactly '
                . 'like a clean one',
                count($templates)
            )
        );

        $offenders = [];

        foreach ($templates as $file) {
            $source = (string) file_get_contents($file);

            foreach (self::constructedClassPatterns() as $shape => $pattern) {
                if (preg_match($pattern, $source)) {
                    $offenders[] = basename(dirname($file)) . '/' . basename($file)
                        . ' (' . $shape . ')';
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            'these templates build a Tailwind class name from a variable, so the compiled CSS '
            . 'cannot contain it however the glob is written: ' . implode(', ', $offenders)
            . '. Add an @source inline(...) safelist to view/frontend/tailwind/module.css '
            . 'naming every class the expression can produce.'
        );
    }

    /**
     * THE POSITIVE CONTROL THIS TEST NEVER HAD.
     *
     * A tripwire that has never been seen to trip is an untested instrument, and
     * this one was green over two of the six shapes it was written to catch -
     * including the one its own docblock offered as an example. Each shape is
     * planted here and each must be found; the denominator is asserted, so a
     * limb that is deleted or stops matching cannot leave silently.
     */
    public function testThePredicateFindsEveryShapeItClaimsTo(): void
    {
        $specimens = [
            'php concatenation'    => '<span class="<?= \'text-\' . $colour . \'-500\' ?>">',
            'braced interpolation' => '<span class="bg-{$name}"></span>',
            'bare interpolation'   => '<span class="text-$colour"></span>',
            'sprintf'              => '<span class="<?= sprintf(\'bg-%s-500\', $v) ?>">',
            'js template literal'  => '<div :class="`bg-${tone}-100`"></div>',
            'js concatenation'     => '<div :class="\'text-\' + tone">',
            'php echo in attribute' => '<span class="bg-<?= $v ?>-500"></span>',
        ];

        $patterns = self::constructedClassPatterns();

        self::assertSame(
            array_keys($patterns),
            array_keys($specimens),
            'every limb of the predicate needs a specimen, or it is unproven - which is how '
            . 'two of six shapes went unmatched with the suite green'
        );

        foreach ($specimens as $shape => $markup) {
            self::assertSame(
                1,
                preg_match($patterns[$shape], $markup),
                sprintf('positive control: the "%s" shape must be found in %s', $shape, $markup)
            );
        }

        // And the whole predicate together must find each one, so that a shape
        // caught by no limb cannot hide behind another limb's pass.
        foreach ($specimens as $shape => $markup) {
            $matched = false;

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $markup)) {
                    $matched = true;
                    break;
                }
            }

            self::assertTrue($matched, sprintf('"%s" is matched by no limb at all', $shape));
        }
    }

    /**
     * AND IT MUST NOT FLAG ORDINARY MARKUP. A predicate that matched everything
     * would pass the control above and make the sweep useless.
     */
    public function testThePredicateLeavesLiteralClassesAlone(): void
    {
        $innocent = [
            'a plain utility list'   => '<div class="rounded-xl bg-gray-100 text-sm text-gray-700">',
            'an arbitrary value'     => '<span class="bg-[#1877F2] hover:bg-[#1664D8]"></span>',
            'an Alpine literal'      => '<div :class="open ? \'bg-blue-600\' : \'bg-gray-200\'">',
            'an escaped whole class' => '<span class="<?= $escaper->escapeHtmlAttr($colorClass) ?>">',
        ];

        foreach ($innocent as $what => $markup) {
            foreach (self::constructedClassPatterns() as $shape => $pattern) {
                self::assertSame(
                    0,
                    preg_match($pattern, $markup),
                    sprintf('negative control: %s must not be flagged as "%s"', $what, $shape)
                );
            }
        }
    }

    /**
     * M2MAP-424. THE ONE LIVE CASE, WHICH IS SAFE BY ACCIDENT.
     *
     * recipe/share.phtml emits a WHOLE class string through
     * `escapeHtmlAttr($colorClass)` - not a class built from a fragment, so the
     * tripwire above correctly leaves it alone. It works only because
     * `$colorMap`'s literal values sit in the SAME template, where Tailwind
     * will scan them.
     *
     * That safety was entirely unasserted. The day somebody moves $colorMap
     * into a PHP class or a ViewModel - a perfectly reasonable tidy-up - every
     * share button loses its colour, with the whole suite green and nothing
     * anywhere saying why.
     */
    public function testTheShareButtonColoursAreLiteralInAScannedFile(): void
    {
        $file = $this->root . '/view/frontend/templates/recipe/share.phtml';

        self::assertFileExists($file, 'recipe/share.phtml moved - retarget this test');

        $source = (string) file_get_contents($file);

        self::assertStringContainsString(
            '$colorClass',
            $source,
            'share.phtml no longer emits a runtime class string; if that is deliberate this '
            . 'case can go, but do not simply point it somewhere else'
        );

        // Every colour the map can produce, including the fallback.
        $required = [
            'bg-[#1877F2]',
            'bg-[#1DA1F2]',
            'bg-[#E60023]',
            'from-[#833AB4]',
            'via-[#FD1D1D]',
            'to-[#FCAF45]',
            'bg-gray-600',
            'bg-gray-500',
        ];

        foreach ($required as $class) {
            self::assertStringContainsString(
                $class,
                $source,
                sprintf(
                    '"%s" is a class the share buttons can render and it is not literally in '
                    . 'this template. Tailwind scans text: a class it cannot see is a class it '
                    . 'does not compile, and the button ships with no colour. Either keep the '
                    . 'map here or add an @source inline(...) safelist to module.css',
                    $class
                )
            );
        }
    }
}

<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 *
 * THIS SUITE USED TO TEST THE INSTALLED COPY, NOT THIS REPOSITORY.
 *
 * Magento's unit bootstrap registers its own autoloader with prepend = true, so
 * `BlueFairy\...` resolved to whatever sits in the Magento install's
 * vendor/bluefairy/ - a DIFFERENT COPY, with a different inode. Proven with a
 * sentinel rather than inferred: a method added to this repository's
 * Model/Filter/DataProvider.php was ABSENT from the class the suite loaded, and
 * ReflectionClass::getFileName() named the vendor path.
 *
 * So every green this suite has ever produced was a statement about the
 * DEPLOYED package. An edit here could not fail it, and a defect here could not
 * be caught by it.
 *
 * The cure is to register this package's own PSR-4 mapping AFTER Magento's
 * bootstrap and with prepend = true, so that it wins. Magento's autoloader is
 * still there for framework classes; only this one namespace is claimed.
 *
 * It also removes the hardcoded path the phpunit.xml carried, which pointed
 * either four directories up (assuming the package sat inside a Magento install
 * at app/code) or at one developer's home directory.
 */

declare(strict_types=1);

$magentoRoot = getenv('MAGENTO_ROOT') ?: '';
if ($magentoRoot === '' || !is_file($magentoRoot . '/vendor/autoload.php')) {
    fwrite(
        STDERR,
        "MAGENTO_ROOT must point at a Magento installation whose vendor/ carries the framework:\n"
        . "    MAGENTO_ROOT=/path/to/magento /path/to/magento/vendor/bin/phpunit -c phpunit.xml.dist\n"
    );
    // phpcs:ignore Magento2.Security.LanguageConstruct -- a test bootstrap that refuses to run exits non-zero; the exit is the refusal
    exit(1);
}

// vendor/autoload.php, NEVER app/bootstrap.php and never the unit-test
// bootstrap. Magento's app/bootstrap.php calls stream_wrapper_unregister('phar'),
// and phpstan runs FROM a .phar - it then dies inside its own error renderer
// naming a class unrelated to anything. The unit bootstrap is no better for
// static analysis: it does not give the analyser a composer loader it can use
// to find Magento's own classes, so phpstan reports real methods and constants
// as undefined. Measured here: it called
// Magento\Framework\Controller\ResultFactory::TYPE_JSON undefined, while
// reflection through this same bootstrap shows the constant present.
require $magentoRoot . '/vendor/autoload.php';

if (!defined('BP')) {
    define('BP', $magentoRoot);
}

// AND the unit-test framework, but ONLY outside a phar. PHPUnit needs it -
// Magento's TestFramework ObjectManager helper lives there, and without it 274
// of these tests error. phpstan runs FROM a .phar, and that bootstrap reaches
// app/bootstrap.php, which calls stream_wrapper_unregister('phar') and leaves
// the analyser unable to read its own archive: it then dies inside its own
// error renderer naming a class unrelated to anything.
//
// So: the composer loader above is what BOTH tools need, and this is what only
// PHPUnit needs.
if (\Phar::running() === '' && is_file($magentoRoot . '/dev/tests/unit/framework/bootstrap.php')) {
    require $magentoRoot . '/dev/tests/unit/framework/bootstrap.php';
}


$packageRoot = dirname(__DIR__);
spl_autoload_register(
    static function (string $class) use ($packageRoot): void {
        $prefix = 'BlueFairy\\RecipesHyva\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $file = $packageRoot . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    },
    true,
    true // PREPEND: Magento's autoloader also prepends, so this must be registered after it AND prepend.
);

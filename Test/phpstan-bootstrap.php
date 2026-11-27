<?php

/**
 * Copyright © 2026 Blue Fairy Development. All rights reserved.
 *
 * STATIC-ANALYSIS bootstrap. Deliberately NOT Test/bootstrap.php.
 *
 * Test/bootstrap.php loads Magento's unit-test framework, which PHPUnit needs -
 * without it 274 of these tests error. But that framework SYNTHESISES EMPTY
 * STAND-IN CLASSES into dev/tests/unit/tmp/generated/code and autoloads them
 * ahead of the real ones. 134 of them on this installation. They shadow real
 * Magento classes with shells that have no constants and wrong signatures:
 *
 *     dev/tests/unit/tmp/generated/code/.../Controller/ResultFactory.php
 *         class ResultFactory { public function create(array $data = []) {} }
 *
 *     vendor/magento/framework/Controller/ResultFactory.php
 *         const TYPE_JSON = 'json'; ... public function create($type, array $arguments = [])
 *
 * So phpstan reported "Access to undefined constant ResultFactory::TYPE_JSON"
 * about a constant that plainly exists, and "Parameter #1 $data expects array"
 * naming a parameter the real method does not have. Those are not findings
 * about this package; they are measurements of the bootstrap.
 *
 * The guard that was supposed to prevent this tested Phar::running(), on the
 * assumption phpstan runs from a .phar. It does not here - it is a composer
 * install - so the guard never fired and looked correct while doing nothing.
 * Hence a separate file rather than a clever condition.
 *
 * Related: the same class of defect in reverse made an analyser go QUIET once a
 * typo'd factory was stood in for. A stand-in can manufacture findings or hide
 * them; either way the number stops being about the code.
 */

declare(strict_types=1);

$magentoRoot = getenv('MAGENTO_ROOT') ?: '';
if ($magentoRoot === '' || !is_file($magentoRoot . '/vendor/autoload.php')) {
    fwrite(STDERR, "MAGENTO_ROOT must point at a Magento installation.\n");
    // phpcs:ignore Magento2.Security.LanguageConstruct -- a test bootstrap that refuses to run exits non-zero; the exit is the refusal
    exit(1);
}

// vendor/autoload.php, never app/bootstrap.php: that file unregisters the phar
// stream wrapper, and a phar-based phpstan then dies inside its own renderer.
/** @var \Composer\Autoload\ClassLoader $composerLoader */
$composerLoader = require $magentoRoot . '/vendor/autoload.php';

if (!defined('BP')) {
    define('BP', $magentoRoot);
}

// GENERATED DI CODE, WITHOUT WHICH THE COUNT IS MEANINGLESS.
//
// Magento writes factories, proxies and interceptors to generated/code at
// di:compile time - ForwardFactory, every CollectionFactory, and this package's
// own. They exist nowhere else. Without this, phpstan reported 454 "Call to
// method create() on an unknown class ...Factory" findings that are not defects
// in this package at all, and they buried the ~100 that are.
//
// REFUSE rather than guess. With generated/code empty the honest answer is that
// the run cannot be done, not a number made mostly of missing files. A count
// produced against an uncompiled install is a measurement of the install.
$generated = $magentoRoot . '/generated/code';
if (!is_dir($generated) || count((array) glob($generated . '/*', GLOB_ONLYDIR)) === 0) {
    fwrite(
        STDERR,
        "generated/code is missing or empty at $generated.\n"
        . "Magento's factories and proxies live there and exist nowhere else, so an analysis run\n"
        . "now would report hundreds of unknown-class errors that are really one absent directory.\n"
        . "Run:  bin/magento setup:di:compile\n"
    );
    // phpcs:ignore Magento2.Security.LanguageConstruct -- a test bootstrap that refuses to run exits non-zero; the exit is the refusal
    exit(1);
}
$composerLoader->addPsr4('', $generated);


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
    true
);

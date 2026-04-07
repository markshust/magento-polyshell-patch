<?php

declare(strict_types=1);

$autoloadCandidates = array_filter([
    __DIR__ . '/../vendor/autoload.php',
    getenv('MAGENTO_AUTOLOAD') ?: null,
]);

$autoloadLoaded = false;
foreach ($autoloadCandidates as $autoloadFile) {
    if (is_string($autoloadFile) && is_file($autoloadFile)) {
        require_once $autoloadFile;
        $autoloadLoaded = true;
        break;
    }
}

if (!$autoloadLoaded) {
    fwrite(STDERR, "Unable to locate Composer autoload.php for Magento dependencies.\n");
    fwrite(STDERR, "Set MAGENTO_AUTOLOAD=/path/to/vendor/autoload.php or install local dependencies.\n");
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MarkShust\\PolyshellPatch\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = dirname(__DIR__) . '/' . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

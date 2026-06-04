<?php

/**
 * PHPUnit bootstrap: load Composer autoload, the Plesk SDK stubs and the
 * extension's library classes (which use the underscore "Modules_*" naming
 * convention resolved by Plesk's own autoloader at runtime).
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/stubs/plesk-sdk.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Modules_Uptimeify_')) {
        return;
    }
    $relative = substr($class, strlen('Modules_Uptimeify_'));
    $path = __DIR__ . '/../plib/library/' . str_replace('_', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

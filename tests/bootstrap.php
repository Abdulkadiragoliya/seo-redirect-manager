<?php

if (file_exists(__DIR__ . '/../../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

// Register PSR-4 for the plugin and tests
spl_autoload_register(function ($class) {
    $testsPrefix = 'abdulkadir\\seoredirectmanager\\tests\\';
    $srcPrefix = 'abdulkadir\\seoredirectmanager\\';

    if (str_starts_with($class, $testsPrefix)) {
        $relative = substr($class, strlen($testsPrefix));
        $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }

    if (str_starts_with($class, $srcPrefix)) {
        $relative = substr($class, strlen($srcPrefix));
        $file = __DIR__ . '/../src/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

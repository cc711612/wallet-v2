<?php

ini_set('display_errors', 'stderr');

$_ENV['APP_RUNNING_IN_CONSOLE'] = false;

$basePath = $_SERVER['APP_BASE_PATH'] ?? $_ENV['APP_BASE_PATH'] ?? $serverState['octaneConfig']['base_path'] ?? null;

if (! is_string($basePath)) {
    echo 'Cannot find application base path.';

    exit(11);
}

$vendorDir = $_ENV['COMPOSER_VENDOR_DIR'] ?? "{$basePath}/vendor";

if (! is_file($autoloadFile = "{$vendorDir}/autoload.php")) {
    echo "Composer autoload file was not found. Did you install the project's dependencies?";

    exit(10);
}

require_once $autoloadFile;

return $basePath;
